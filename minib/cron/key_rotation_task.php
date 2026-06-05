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

stream_context_set_default([
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false,
        'allow_self_signed' => true
    ]
]);

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', '/var/www/html/admin');
}

if (file_exists(ROOT_PATH . '/config.php')) {
    require_once ROOT_PATH . '/config.php';
}

define('LOG_FILE', '/var/www/minib/logs/key_rotation.log');

function logMessage($message) {
    $timestamp = date('Y-m-d H:i:s');
    @file_put_contents(LOG_FILE, "[$timestamp] $message\n", FILE_APPEND);
    echo "[$timestamp] $message\n";
}

function getTargetHost($db, $sn) {
    $stmt = $db->prepare('SELECT * FROM hosts WHERE hostSn = :sn');
    $stmt->bindValue(':sn', $sn, SQLITE3_TEXT);
    $result = $stmt->execute();
    return $result->fetchArray(SQLITE3_ASSOC);
}

function updateTaskStatus($db, $taskId, $status, $error = null, $attempts = null) {
    if ($attempts !== null) {
        $stmt = $db->prepare('
            UPDATE key_rotation_tasks 
            SET status = :status, lastError = :error, attempts = :attempts, dateUpdateStatus = datetime("now")
            WHERE taskId = :taskId
        ');
        $stmt->bindValue(':attempts', $attempts, SQLITE3_INTEGER);
    } else {
        $stmt = $db->prepare('
            UPDATE key_rotation_tasks 
            SET status = :status, lastError = :error, dateUpdateStatus = datetime("now")
            WHERE taskId = :taskId
        ');
    }
    $stmt->bindValue(':status', $status, SQLITE3_TEXT);
    $stmt->bindValue(':error', $error, SQLITE3_TEXT);
    $stmt->bindValue(':taskId', $taskId, SQLITE3_INTEGER);
    $stmt->execute();
}

function sendMasterToSlave($targetHost, $newKey, $oldKey) {
    $proto = $targetHost['hostProto'] ?? 'http';
    $port = $targetHost['hostPort'] ?? ($proto === 'https' ? 443 : 80);
    $path = rtrim($targetHost['hostApiPath'] ?? '/', '/');
    $url = "{$proto}://{$targetHost['hostIp']}:{$port}{$path}/api_inspector.php?action=receive_new_key";
    
    $data = json_encode([
        'target_sn' => $targetHost['hostSn'],
        'new_api_key' => $newKey
    ]);
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-API-SN: ' . $targetHost['hostSn'],
            'X-API-KEY: ' . $oldKey,
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['success' => false, 'error' => $error];
    }
    
    $decoded = json_decode($response, true);
    return [
        'success' => ($httpCode === 200 && isset($decoded['status']) && $decoded['status'] === 'success'),
        'response' => $decoded
    ];
}

function sendSlaveToMaster($masterHost, $slaveSn, $newKey, $oldKey, $taskId) {
    $proto = $masterHost['hostProto'] ?? 'http';
    $port = $masterHost['hostPort'] ?? ($proto === 'https' ? 443 : 80);
    $path = rtrim($masterHost['hostApiPath'] ?? '/', '/');
    $url = "{$proto}://{$masterHost['hostIp']}:{$port}{$path}/api_inspector.php?action=master_receive_slave_key";
    
    $data = json_encode([
        'slave_sn' => $slaveSn,
        'new_api_key' => $newKey,
        'old_api_key' => $oldKey,
        'task_id' => $taskId
    ]);
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-API-SN: ' . $slaveSn,
            'X-API-KEY: ' . $oldKey,
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['success' => false, 'error' => $error];
    }
    
    $decoded = json_decode($response, true);
    return [
        'success' => ($httpCode === 200 && isset($decoded['status']) && $decoded['status'] === 'success'),
        'response' => $decoded
    ];
}

function checkPendingTasks($db) {
    $stmt = $db->prepare('
        SELECT * FROM key_rotation_tasks 
        WHERE status = "pending" AND attempts < maxAttempts
        AND (
            dateUpdateStatus IS NULL 
            OR julianday(datetime("now")) - julianday(dateUpdateStatus) > 
               CASE attempts
                   WHEN 0 THEN 0
                   WHEN 1 THEN 0.0007
                   WHEN 2 THEN 0.0021
                   WHEN 3 THEN 0.0069
                   ELSE 0.0208
               END
        )
        ORDER BY 
            CASE WHEN attempts = 0 THEN 0 ELSE 1 END,
            taskId ASC
        LIMIT 10
    ');
    $result = $stmt->execute();
    $tasks = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $tasks[] = $row;
    }
    return $tasks;
}

function processMasterToSlaveTask($db, $task) {
    logMessage("Processing MASTER_TO_SLAVE task ID: {$task['taskId']} for SN: {$task['targetSn']}");
    
    $targetHost = getTargetHost($db, $task['targetSn']);
    if (!$targetHost) {
        updateTaskStatus($db, $task['taskId'], 'failed', 'Target host not found');
        return false;
    }
    
    updateTaskStatus($db, $task['taskId'], 'in_progress', null);
    
    $result = sendMasterToSlave($targetHost, $task['newApiKey'], $task['oldApiKey']);
    
    if ($result['success']) {
        logMessage("Task {$task['taskId']}: Successfully sent, waiting for confirmation");
        return true;
    } else {
        $error = "Failed: " . ($result['error'] ?? $result['response']['error'] ?? 'Unknown');
        $newAttempts = $task['attempts'] + 1;
        if ($newAttempts >= $task['maxAttempts']) {
            updateTaskStatus($db, $task['taskId'], 'failed', "Max attempts: $error", $newAttempts);
        } else {
            updateTaskStatus($db, $task['taskId'], 'pending', $error, $newAttempts);
        }
        return false;
    }
}

function processSlaveToMastersTask($db, $task) {
    logMessage("Processing SLAVE_TO_MASTERS task ID: {$task['taskId']}");
    
    $masterSns = json_decode($task['targetMastersJson'], true);
    if (empty($masterSns)) {
        $stmt = $db->prepare('
            UPDATE key_rotation_tasks SET status = "completed", completedDate = datetime("now")
            WHERE taskId = :taskId
        ');
        $stmt->bindValue(':taskId', $task['taskId'], SQLITE3_INTEGER);
        $stmt->execute();
        return true;
    }
    
    $successCount = 0;
    foreach ($masterSns as $masterSn) {
        $masterHost = getTargetHost($db, $masterSn);
        if (!$masterHost) continue;
        
        $result = sendSlaveToMaster($masterHost, $task['targetSn'], $task['newApiKey'], $task['oldApiKey'], $task['taskId']);
        if ($result['success']) {
            $successCount++;
            logMessage("Task {$task['taskId']}: Sent to master {$masterSn}");
        }
    }
    
    if ($successCount > 0) {
        $stmt = $db->prepare('
            UPDATE hosts SET hostOldApiKey = :oldKey, hostApiKey = :newKey, hostDateApiUpdtae = datetime("now")
            WHERE hostSn = :sn
        ');
        $stmt->bindValue(':oldKey', $task['oldApiKey'], SQLITE3_TEXT);
        $stmt->bindValue(':newKey', $task['newApiKey'], SQLITE3_TEXT);
        $stmt->bindValue(':sn', $task['targetSn'], SQLITE3_TEXT);
        $stmt->execute();
        
        $stmt = $db->prepare('
            UPDATE key_rotation_tasks SET status = "completed", completedDate = datetime("now")
            WHERE taskId = :taskId
        ');
        $stmt->bindValue(':taskId', $task['taskId'], SQLITE3_INTEGER);
        $stmt->execute();
        return true;
    } else {
        $newAttempts = $task['attempts'] + 1;
        if ($newAttempts >= $task['maxAttempts']) {
            updateTaskStatus($db, $task['taskId'], 'failed', 'Failed to send to all masters', $newAttempts);
        } else {
            updateTaskStatus($db, $task['taskId'], 'pending', 'Retry sending to masters', $newAttempts);
        }
        return false;
    }
}

function processMasterCascadeTask($db, $task) {
    logMessage("Processing MASTER_CASCADE task ID: {$task['taskId']}");
    
    $masterSns = json_decode($task['targetMastersJson'], true);
    
    if (empty($masterSns)) {
        updateTaskStatus($db, $task['taskId'], 'completed', null);
        return true;
    }
    
    $successCount = 0;
    $failedMasters = [];
    
    foreach ($masterSns as $masterSn) {
        $masterHost = getTargetHost($db, $masterSn);
        if (!$masterHost) {
            $failedMasters[] = $masterSn;
            logMessage("Master {$masterSn} NOT FOUND");
            continue;
        }
        
        $result = sendSlaveToMaster($masterHost, $task['targetSn'], $task['newApiKey'], $task['oldApiKey'], $task['taskId']);
        
        if ($result['success']) {
            $successCount++;
        } else {
            $failedMasters[] = $masterSn;
            logMessage("Failed to send to master {$masterSn}: " . ($result['error'] ?? 'Unknown'));
        }
    }
    
    logMessage("Task {$task['taskId']}: Sent to {$successCount}/" . count($masterSns) . " masters");
    
    if ($successCount === count($masterSns)) {
        updateTaskStatus($db, $task['taskId'], 'completed', null);
        return true;
    }
    
    $newAttempts = $task['attempts'] + 1;
    $failedMastersJson = json_encode($failedMasters);
    $error = "Failed to send to masters: " . implode(', ', $failedMasters);
    
    if ($newAttempts >= $task['maxAttempts']) {
        updateTaskStatus($db, $task['taskId'], 'failed', $error, $newAttempts);
        logMessage("Task {$task['taskId']}: FAILED after {$newAttempts} attempts");
    } else {
        $stmt = $db->prepare('
            UPDATE key_rotation_tasks 
            SET status = "pending", attempts = :attempts, lastError = :error, 
                targetMastersJson = :failedMasters, dateUpdateStatus = datetime("now")
            WHERE taskId = :taskId
        ');
        $stmt->bindValue(':attempts', $newAttempts, SQLITE3_INTEGER);
        $stmt->bindValue(':error', $error, SQLITE3_TEXT);
        $stmt->bindValue(':failedMasters', $failedMastersJson, SQLITE3_TEXT);
        $stmt->bindValue(':taskId', $task['taskId'], SQLITE3_INTEGER);
        $stmt->execute();
        
        logMessage("Task {$task['taskId']}: Will retry, failed masters: " . $failedMastersJson);
    }
    
    return $successCount > 0;
}

function processSlaveCascadeTask($db, $task) {
    logMessage("Processing SLAVE_CASCADE task ID: {$task['taskId']}");
    
    $masterSns = json_decode($task['targetMastersJson'], true);
    if (empty($masterSns)) {
        $stmt = $db->prepare('
            UPDATE key_rotation_tasks SET status = "completed", completedDate = datetime("now")
            WHERE taskId = :taskId
        ');
        $stmt->bindValue(':taskId', $task['taskId'], SQLITE3_INTEGER);
        $stmt->execute();
        return true;
    }
    
    $successCount = 0;
    foreach ($masterSns as $masterSn) {
        $masterHost = getTargetHost($db, $masterSn);
        if (!$masterHost) continue;
        
        $result = sendSlaveToMaster($masterHost, $task['targetSn'], $task['newApiKey'], $task['oldApiKey'], $task['taskId']);
        if ($result['success']) {
            $successCount++;
        }
    }
    
    $stmt = $db->prepare('
        UPDATE key_rotation_tasks SET status = "completed", completedDate = datetime("now")
        WHERE taskId = :taskId
    ');
    $stmt->bindValue(':taskId', $task['taskId'], SQLITE3_INTEGER);
    $stmt->execute();
    
    logMessage("Task {$task['taskId']}: Slave cascade sent to {$successCount}/" . count($masterSns) . " masters"); 
    return true;
}

function resetStuckTasks($db) {
    $stmt = $db->prepare('
        UPDATE key_rotation_tasks 
        SET status = "pending", lastError = "Stuck, reset", dateUpdateStatus = datetime("now")
        WHERE status = "in_progress" 
        AND julianday(datetime("now")) - julianday(dateUpdateStatus) > 0.0035
        AND attempts < maxAttempts
    ');
    $stmt->execute();
    return $db->changes();
}

function cleanupOldTasks($db) {
    $db->exec('
        DELETE FROM key_rotation_tasks 
        WHERE status IN ("completed", "failed") AND completedDate IS NOT NULL
        AND julianday(datetime("now")) - julianday(completedDate) > 30
    ');
}

function main() {
    logMessage("=== Key Rotation Scheduler Started ===");
    
    try {
        $db = getDB();
        
        $resetCount = resetStuckTasks($db);
        if ($resetCount > 0) {
            logMessage("Reset {$resetCount} stuck tasks");
        }
        
        $tasks = checkPendingTasks($db);
        
        if (empty($tasks)) {
            logMessage("No pending tasks found");
        } else {
            logMessage("Found " . count($tasks) . " pending tasks");
            
            foreach ($tasks as $task) {
                switch ($task['rotationType']) {
                    case 'master_to_slave':
                        processMasterToSlaveTask($db, $task);
                        break;
                    case 'slave_to_masters':
                        processSlaveToMastersTask($db, $task);
                        break;
                    case 'master_cascade':
                        processMasterCascadeTask($db, $task);
                        break;
                    case 'slave_cascade':
                        processSlaveCascadeTask($db, $task);
                        break;
                    default:
                        logMessage("Unknown task type: {$task['rotationType']}");
                        updateTaskStatus($db, $task['taskId'], 'failed', "Unknown type");
                }
                sleep(1);
            }
        }
        
        cleanupOldTasks($db);
        
        logMessage("=== Key Rotation Scheduler Finished ===");
        
    } catch (Exception $e) {
        logMessage("CRITICAL ERROR: " . $e->getMessage());
    }
}

main();