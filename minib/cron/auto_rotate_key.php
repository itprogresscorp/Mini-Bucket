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
 * https://mini-bucket.ru/
 */

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', '/var/www/html/admin');
}

$configPath = '/var/www/html/admin/config.php';
if (file_exists($configPath)) {
    require_once $configPath;
}

define('LOG_FILE', '/var/www/minib/logs/auto_rotation.log');

//ping status
function getCurrentHostSn($db) {
	$db = getDB();
    static $currentSn = null;
    if ($currentSn === null) {
        $stmt = $db->prepare('SELECT hostSn FROM hosts WHERE idHost = 1');
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        $currentSn = $row ? $row['hostSn'] : null;
        if (!$currentSn) {
            $result = $db->query('SELECT hostSn FROM hosts LIMIT 1');
            $row = $result->fetchArray(SQLITE3_ASSOC);
            $currentSn = $row ? $row['hostSn'] : null;
        }
    }
    return $currentSn;
}

function sendPing($db) {
	$db = getDB();
    $currentSn = getCurrentHostSn($db);
    
    if (!$currentSn) {
        error_log("Не удалось получить hostSn для отправки пинга");
        return false;
    }
    
    $data = [
        'hostSn' => $currentSn
    ];
    
    $ch = curl_init('https://update.mini-bucket.ru/minib/ping.php');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        error_log("CURL error sending ping: " . $curlError);
        return false;
    }
    
    if ($httpCode !== 200) {
        error_log("HTTP error sending ping: " . $httpCode . " - " . $response);
        return false;
    }
    
    $result = json_decode($response, true);
    if (isset($result['status']) && $result['status'] === 'success') {
        return true;
    }
    
    return false;
}

$db = getDB();
sendPing($db);

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
        
        $stmt = $db->prepare('SELECT hostSn FROM hosts WHERE hostType = "master"');
        $result = $stmt->execute();
        
        $masterSns = [];
        while ($master = $result->fetchArray(SQLITE3_ASSOC)) {
            $masterSns[] = $master['hostSn'];
        }
        
        if (empty($masterSns)) {
            logMessage("No masters found, key rotated locally only");
            logMessage("✓ SUCCESS! Key rotation completed");
            return;
        }
        
        logMessage("Found " . count($masterSns) . " master(s): " . implode(', ', $masterSns));
        
        $mastersJson = json_encode($masterSns);
        
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
        
        if ($stmt->execute()) {
            $taskId = $db->lastInsertRowID();
            logMessage("✓ Task {$taskId} created for " . count($masterSns) . " master(s)");
        } else {
            throw new Exception("Failed to create task: " . $db->lastErrorMsg());
        }
        
        $backupFile = '/var/www/minib/logs/last_rotated_key_' . date('Y-m-d') . '.txt';
        file_put_contents($backupFile, 
            "Date: " . date('Y-m-d H:i:s') . "\n" .
            "Host: {$host['hostSn']}\n" .
            "Host type: {$host['hostType']}\n" .
            "Old Key: {$oldKey}\n" .
            "New Key: {$newKey}\n" .
            "Masters: " . implode(', ', $masterSns) . "\n"
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