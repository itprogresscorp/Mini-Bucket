#!/usr/bin/env php
<?php
/*
 * Copyright (C) 2026 Mamontov Roman Igorevich
 *
 * This file is part of "Mini-Bucket - NAS Control Panel".
 *
 * Mini-Bucket - NAS Control Panel is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version, with the plugin exception (see LICENSE file).
 * Commercial use requires purchasing a separate commercial license from the copyright holder.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 * https://mini-b.itp-corp.ru/
 */

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', '/var/www/html/admin');
}


$configPath = '/var/www/html/admin/config.php';
if (file_exists($configPath)) {
    require_once $configPath;
}

define('LOG_FILE', '/var/www/minib/logs/auto_rotation.log');

function logMessage($message) {
    $timestamp = date('Y-m-d H:i:s');
    @file_put_contents(LOG_FILE, "[$timestamp] $message\n", FILE_APPEND);
    echo "[$timestamp] $message\n";
}

function getRotationInterval($db) {
    $stmt = $db->prepare('SELECT setValue FROM settings WHERE setId = 4');
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    
    if ($row && isset($row['setValue'])) {
        $interval = (int)$row['setValue'];
        logMessage("Rotation interval from settings: {$interval} days");
        return $interval;
    }
    
    logMessage("Settings record not found, using default: 30 days");
    return 30;
}

function getCurrentHost($db) {
    $stmt = $db->prepare('SELECT * FROM hosts WHERE idHost = 1');
    $result = $stmt->execute();
    $host = $result->fetchArray(SQLITE3_ASSOC);
    
    if (!$host) {
        throw new Exception("Local host (idHost=1) not found in database");
    }
    
    return $host;
}

function generateApiKey() {
    return bin2hex(random_bytes(32));
}

function shouldRotate($db, $host) {
    $rotationInterval = getRotationInterval($db);
    
    if ($rotationInterval === 0) {
        logMessage("Rotation is disabled (interval = 0 days), skipping");
        return false;
    }
    
    if (empty($host['hostDateApiUpdtae'])) {
        logMessage("No previous rotation date found, will rotate");
        return true;
    }
    
    $lastRotation = strtotime($host['hostDateApiUpdtae']);
    $daysPassed = (time() - $lastRotation) / 86400;
    
    logMessage("Last rotation: {$host['hostDateApiUpdtae']} (" . round($daysPassed, 2) . " days ago)");
    logMessage("Rotation interval: {$rotationInterval} days");
    
    return $daysPassed >= $rotationInterval;
}

function main($argv) {
    $force = in_array('--force', $argv);
    
    logMessage("=== Auto Key Rotation Started ===");
    
    try {
        $db = getDB();
        
        $rotationInterval = getRotationInterval($db);
        
        if ($rotationInterval === 0 && !$force) {
            logMessage("Rotation is disabled (interval = 0 days). Use --force to override.");
            logMessage("=== Auto Key Rotation Finished ===");
            return;
        }
        
        if ($rotationInterval === 0 && $force) {
            logMessage("WARNING: Rotation is disabled but --force flag used, forcing rotation anyway");
        }
        
        $host = getCurrentHost($db);
        
        logMessage("Current host: {$host['hostName']} (SN: {$host['hostSn']})");
        logMessage("Host type: {$host['hostType']}");
        
        if (!$force && !shouldRotate($db, $host)) {
            logMessage("Key is still fresh, skipping rotation");
            logMessage("=== Auto Key Rotation Finished ===");
            return;
        }
        
        $newKey = generateApiKey();
        $oldKey = $host['hostApiKey'];
        
        logMessage("Generating new API key...");
        logMessage("Old key: " . substr($oldKey, 0, 16) . "...");
        logMessage("New key: " . substr($newKey, 0, 16) . "...");
        
        $stmt = $db->prepare('
            UPDATE hosts 
            SET hostOldApiKey = :oldKey, 
                hostApiKey = :newKey, 
                hostDateApiUpdtae = datetime("now")
            WHERE hostSn = :sn
        ');
        $stmt->bindValue(':oldKey', $oldKey, SQLITE3_TEXT);
        $stmt->bindValue(':newKey', $newKey, SQLITE3_TEXT);
        $stmt->bindValue(':sn', $host['hostSn'], SQLITE3_TEXT);
        $stmt->execute();
        
        logMessage("✓ Key updated in database");
        
        if ($host['hostType'] === 'master') {
            $stmt = $db->prepare('SELECT * FROM hosts WHERE hostType = "slave"');
            $result = $stmt->execute();
            
            $slaves = [];
            while ($slave = $result->fetchArray(SQLITE3_ASSOC)) {
                $slaves[] = $slave;
            }
            
            if (!empty($slaves)) {
                logMessage("Creating tasks for " . count($slaves) . " slave(s)...");
                
                foreach ($slaves as $slave) {
                    $stmt = $db->prepare('
                        INSERT INTO key_rotation_tasks 
                        (initiatorSn, targetSn, newApiKey, oldApiKey, status, attempts, maxAttempts, 
                         rotationType, initiationDate)
                        VALUES (:initiator, :target, :newKey, :oldKey, "pending", 0, 5, 
                                "master_to_slave", datetime("now"))
                    ');
                    $stmt->bindValue(':initiator', $host['hostSn'], SQLITE3_TEXT);
                    $stmt->bindValue(':target', $slave['hostSn'], SQLITE3_TEXT);
                    $stmt->bindValue(':newKey', $newKey, SQLITE3_TEXT);
                    $stmt->bindValue(':oldKey', $oldKey, SQLITE3_TEXT);
                    $stmt->execute();
                    
                    $taskId = $db->lastInsertRowID();
                    logMessage("  Task {$taskId} created for slave: {$slave['hostSn']}");
                }
            } else {
                logMessage("No slaves found to notify");
            }
        } else {
            $stmt = $db->prepare('SELECT * FROM hosts WHERE hostType = "master"');
            $result = $stmt->execute();
            
            $masters = [];
            while ($master = $result->fetchArray(SQLITE3_ASSOC)) {
                $masters[] = $master;
            }
            
            if (!empty($masters)) {
                $mastersJson = json_encode(array_column($masters, 'hostSn'));
                
                $stmt = $db->prepare('
                    INSERT INTO key_rotation_tasks 
                    (initiatorSn, targetSn, newApiKey, oldApiKey, status, attempts, maxAttempts, 
                     rotationType, targetMastersJson, initiationDate)
                    VALUES (:initiator, :target, :newKey, :oldKey, "pending", 0, 5, 
                            "slave_to_masters", :mastersJson, datetime("now"))
                ');
                $stmt->bindValue(':initiator', $host['hostSn'], SQLITE3_TEXT);
                $stmt->bindValue(':target', $host['hostSn'], SQLITE3_TEXT);
                $stmt->bindValue(':newKey', $newKey, SQLITE3_TEXT);
                $stmt->bindValue(':oldKey', $oldKey, SQLITE3_TEXT);
                $stmt->bindValue(':mastersJson', $mastersJson, SQLITE3_TEXT);
                $stmt->execute();
                
                $taskId = $db->lastInsertRowID();
                logMessage("Task {$taskId} created for " . count($masters) . " master(s)");
            } else {
                logMessage("No masters found to notify");
            }
        }
        
        $backupFile = '/var/www/minib/logs/last_rotated_key_' . date('Y-m-d') . '.txt';
        file_put_contents($backupFile, 
            "Date: " . date('Y-m-d H:i:s') . "\n" .
            "Host: {$host['hostSn']}\n" .
            "Old Key: {$oldKey}\n" .
            "New Key: {$newKey}\n"
        );
        logMessage("Backup saved: {$backupFile}");
        
        logMessage("✓ SUCCESS! Key rotation completed");
        
    } catch (Exception $e) {
        logMessage("✗ ERROR: " . $e->getMessage());
        logMessage("=== Auto Key Rotation Failed ===");
        exit(1);
    }
    
    logMessage("=== Auto Key Rotation Finished ===");
}

main($argv);