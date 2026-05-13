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
 
libxml_use_internal_errors(true);
stream_context_set_default([
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false,
        'allow_self_signed' => true
    ]
]);

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(dirname(__FILE__)));
}

if (file_exists(ROOT_PATH . '/config.php')) {
    require_once ROOT_PATH . '/config.php';
}

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-API-Key, X-API-SN, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

function logApiAction($message) {
    $logFile = '/var/www/minib/logs/api_inspector.log';
    $timestamp = date('Y-m-d H:i:s');
    @file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

function generateApiKey($sn = null) {
    return bin2hex(random_bytes(32));
}

function authenticate($db) {
    $headers = getallheaders();
    
    $sn = $headers['X-API-SN'] ?? $headers['X-Api-Sn'] ?? null;
    $apiKey = $headers['X-API-KEY'] ?? $headers['X-Api-Key'] ?? null;
    
    if (!$sn || !$apiKey) {
        http_response_code(401);
        echo json_encode(['error' => 'Missing authentication headers: X-API-SN and X-API-KEY']);
        exit;
    }
    
    $stmt = $db->prepare('SELECT * FROM hosts WHERE hostSn = :sn');
    $stmt->bindValue(':sn', $sn, SQLITE3_TEXT);
    $result = $stmt->execute();
    $host = $result->fetchArray(SQLITE3_ASSOC);
    
    if (!$host) {
        http_response_code(401);
        echo json_encode(['error' => 'Host not found']);
        exit;
    }
    
    $keyValid = false;
    $keyType = null;
    
    if ($host['hostApiKey'] === $apiKey) {
        $keyValid = true;
        $keyType = 'current';
    } elseif ($host['hostOldApiKey'] === $apiKey) {
        $keyValid = true;
        $keyType = 'old';
    }
    
    if (!$keyValid) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid API key']);
        exit;
    }
    
    return ['host' => $host, 'keyType' => $keyType];
}

function getHostBySn($db, $sn) {
    $stmt = $db->prepare('SELECT * FROM hosts WHERE hostSn = :sn');
    $stmt->bindValue(':sn', $sn, SQLITE3_TEXT);
    $result = $stmt->execute();
    return $result->fetchArray(SQLITE3_ASSOC);
}

function getCurrentHostSn($db) {
    static $currentSn = null;
    if ($currentSn === null) {
        $stmt = $db->prepare('SELECT hostSn FROM hosts WHERE hostType = "master" LIMIT 1');
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

function getAllMastersExceptCurrent($db, $currentSn) {
    $stmt = $db->prepare('SELECT hostSn FROM hosts WHERE hostType = "master" AND hostSn != :currentSn');
    $stmt->bindValue(':currentSn', $currentSn, SQLITE3_TEXT);
    $result = $stmt->execute();
    $masters = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $masters[] = $row['hostSn'];
    }
    return $masters;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? null;

try {
    $db = getDB();
    
    if ($method === 'POST' && $action === 'generate_new_key') {
        handleGenerateNewKey($db);
    } elseif ($method === 'POST' && $action === 'send_new_key') {
        handleSendNewKey($db);
    } elseif ($method === 'POST' && $action === 'receive_new_key') {
        handleReceiveNewKey($db);
    } elseif ($method === 'POST' && $action === 'command_new_key') {
        handleCommandNewKey($db);
    } elseif ($method === 'GET' && $action === 'status') {
        handleStatus($db);
    } elseif ($method === 'POST' && $action === 'slave_initiate_key_rotation') {
        handleSlaveInitiateKeyRotation($db);
    } elseif ($method === 'POST' && $action === 'master_receive_slave_key') {
        handleMasterReceiveSlaveKey($db);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint not found']);
    }
} catch (Exception $e) {
    logApiAction("CRITICAL ERROR: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error', 'message' => $e->getMessage()]);
}

function handleGenerateNewKey($db) {
    logApiAction("handleGenerateNewKey called");
    
    $auth = authenticate($db);
    $initiatorSn = $auth['host']['hostSn'];
    
    $input = json_decode(file_get_contents('php://input'), true);
    $targetSn = $input['target_sn'] ?? null;
    
    if (!$targetSn) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing target_sn']);
        return;
    }
    
    $targetHost = getHostBySn($db, $targetSn);
    if (!$targetHost) {
        http_response_code(404);
        echo json_encode(['error' => 'Target host not found']);
        return;
    }
    
    $newKey = generateApiKey();
    $oldKey = $targetHost['hostApiKey'];
    
    $stmt = $db->prepare('
        INSERT INTO key_rotation_tasks 
        (initiatorSn, targetSn, newApiKey, oldApiKey, status, attempts, maxAttempts, 
         rotationType, initiationDate)
        VALUES (:initiator, :target, :newKey, :oldKey, "pending", 0, 5, 
                "master_to_slave", datetime("now"))
    ');
    $stmt->bindValue(':initiator', $initiatorSn, SQLITE3_TEXT);
    $stmt->bindValue(':target', $targetSn, SQLITE3_TEXT);
    $stmt->bindValue(':newKey', $newKey, SQLITE3_TEXT);
    $stmt->bindValue(':oldKey', $oldKey, SQLITE3_TEXT);
    $stmt->execute();
    
    $taskId = $db->lastInsertRowID();
    
    logApiAction("Task created: ID={$taskId}, target={$targetSn}");
    
    echo json_encode([
        'status' => 'success',
        'task_id' => $taskId,
        'new_api_key' => $newKey
    ]);
}

function handleSendNewKey($db) {
    logApiAction("handleSendNewKey called");
    
    $input = json_decode(file_get_contents('php://input'), true);
    $targetSn = $input['target_sn'] ?? null;
    $newKey = $input['new_api_key'] ?? null;
    $oldKey = $input['old_api_key'] ?? null;
    
    if (!$targetSn || !$newKey || !$oldKey) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing parameters']);
        return;
    }
    
    $targetHost = getHostBySn($db, $targetSn);
    if (!$targetHost) {
        http_response_code(404);
        echo json_encode(['error' => 'Target host not found']);
        return;
    }
    
    $proto = $targetHost['hostProto'] ?? 'http';
    $port = $targetHost['hostPort'] ?? ($proto === 'https' ? 443 : 80);
    $path = rtrim($targetHost['hostApiPath'] ?? '/', '/');
    $url = "{$proto}://{$targetHost['hostIp']}:{$port}{$path}/api_inspector.php?action=receive_new_key";
    
    $data = json_encode([
        'target_sn' => $targetSn,
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
            'X-API-SN: ' . $targetSn,
            'X-API-KEY: ' . $oldKey,
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        http_response_code(500);
        echo json_encode(['error' => $curlError]);
    } elseif ($httpCode === 200) {
        echo json_encode(['status' => 'success', 'response' => json_decode($response, true)]);
    } else {
        http_response_code($httpCode);
        echo $response;
    }
}

function handleReceiveNewKey($db) {
    logApiAction("handleReceiveNewKey called");
    
    $auth = authenticate($db);
    $host = $auth['host'];
    
    $input = json_decode(file_get_contents('php://input'), true);
    $targetSn = $input['target_sn'] ?? null;
    $newKey = $input['new_api_key'] ?? null;
    
    if (!$targetSn || !$newKey) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing target_sn or new_api_key']);
        return;
    }
    
    if ($targetSn !== $host['hostSn']) {
        http_response_code(403);
        echo json_encode(['error' => 'SN mismatch']);
        return;
    }
    
    $oldKey = $host['hostApiKey'];
    
    $stmt = $db->prepare('
        UPDATE hosts 
        SET hostOldApiKey = :oldKey, hostApiKey = :newKey, hostDateApiUpdtae = datetime("now")
        WHERE hostSn = :sn
    ');
    $stmt->bindValue(':oldKey', $oldKey, SQLITE3_TEXT);
    $stmt->bindValue(':newKey', $newKey, SQLITE3_TEXT);
    $stmt->bindValue(':sn', $targetSn, SQLITE3_TEXT);
    $stmt->execute();
    
    logApiAction("Keys updated for SN: {$targetSn}");
    
    $stmt = $db->prepare('
        SELECT taskId, initiatorSn FROM key_rotation_tasks 
        WHERE targetSn = :sn AND newApiKey = :key AND status = "in_progress"
        ORDER BY taskId DESC LIMIT 1
    ');
    $stmt->bindValue(':sn', $targetSn, SQLITE3_TEXT);
    $stmt->bindValue(':key', $newKey, SQLITE3_TEXT);
    $result = $stmt->execute();
    $task = $result->fetchArray(SQLITE3_ASSOC);
    
    if ($task) {
        $stmt = $db->prepare('
            UPDATE key_rotation_tasks 
            SET status = "completed", completedDate = datetime("now")
            WHERE taskId = :taskId
        ');
        $stmt->bindValue(':taskId', $task['taskId'], SQLITE3_INTEGER);
        $stmt->execute();
        
        logApiAction("Task {$task['taskId']} completed");
        
        $initiatorSn = $task['initiatorSn'];
        $currentMasterSn = getCurrentHostSn($db);
        
        if ($initiatorSn === $currentMasterSn) {
            $otherMasters = getAllMastersExceptCurrent($db, $currentMasterSn);
            
            if (!empty($otherMasters)) {
                $stmt = $db->prepare('
                    SELECT taskId FROM key_rotation_tasks 
                    WHERE targetSn = :slaveSn AND rotationType = "master_cascade"
                    AND status IN ("pending", "in_progress")
                ');
                $stmt->bindValue(':slaveSn', $targetSn, SQLITE3_TEXT);
                $result = $stmt->execute();
                $existingTask = $result->fetchArray(SQLITE3_ASSOC);
                
                if (!$existingTask) {
                    $mastersJson = json_encode($otherMasters);
                    $stmt = $db->prepare('
                        INSERT INTO key_rotation_tasks 
                        (initiatorSn, targetSn, newApiKey, oldApiKey, status, attempts, maxAttempts, 
                         rotationType, targetMastersJson, initiationDate, parentTaskId)
                        VALUES (:initiator, :target, :newKey, :oldKey, "pending", 0, 5, 
                                "master_cascade", :mastersJson, datetime("now"), :parentTaskId)
                    ');
                    $stmt->bindValue(':initiator', $initiatorSn, SQLITE3_TEXT);
                    $stmt->bindValue(':target', $targetSn, SQLITE3_TEXT);
                    $stmt->bindValue(':newKey', $newKey, SQLITE3_TEXT);
                    $stmt->bindValue(':oldKey', $oldKey, SQLITE3_TEXT);
                    $stmt->bindValue(':mastersJson', $mastersJson, SQLITE3_TEXT);
                    $stmt->bindValue(':parentTaskId', $task['taskId'], SQLITE3_INTEGER);
                    $stmt->execute();
                    
                    logApiAction("Created master_cascade task for " . count($otherMasters) . " other masters");
                }
            }
        }
    }
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Key updated successfully'
    ]);
}

function handleCommandNewKey($db) {
    logApiAction("handleCommandNewKey called");
    
    $headers = getallheaders();
    $sn = $headers['X-API-SN'] ?? $headers['X-Api-Sn'] ?? null;
    $apiKey = $headers['X-API-KEY'] ?? $headers['X-Api-Key'] ?? null;
    
    if (!$sn || !$apiKey) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing X-API-SN or X-API-KEY headers']);
        return;
    }
    
    $stmt = $db->prepare('
        SELECT * FROM key_rotation_tasks 
        WHERE targetSn = :sn AND newApiKey = :key AND status = "pending"
        ORDER BY taskId DESC LIMIT 1
    ');
    $stmt->bindValue(':sn', $sn, SQLITE3_TEXT);
    $stmt->bindValue(':key', $apiKey, SQLITE3_TEXT);
    $result = $stmt->execute();
    $task = $result->fetchArray(SQLITE3_ASSOC);
    
    if (!$task) {
        http_response_code(404);
        echo json_encode(['error' => 'Task not found or already confirmed']);
        return;
    }
    
    $stmt = $db->prepare('
        UPDATE key_rotation_tasks 
        SET status = "completed", completedDate = datetime("now")
        WHERE taskId = :taskId
    ');
    $stmt->bindValue(':taskId', $task['taskId'], SQLITE3_INTEGER);
    $stmt->execute();
    
    logApiAction("Task {$task['taskId']} confirmed");
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Key rotation confirmed'
    ]);
}

function handleStatus($db) {
    $auth = authenticate($db);
    $sn = $auth['host']['hostSn'];
    $taskId = $_GET['task_id'] ?? null;
    
    if ($taskId) {
        $stmt = $db->prepare('SELECT * FROM key_rotation_tasks WHERE taskId = :taskId');
        $stmt->bindValue(':taskId', $taskId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $task = $result->fetchArray(SQLITE3_ASSOC);
        echo json_encode(['task' => $task]);
    } else {
        $stmt = $db->prepare('
            SELECT * FROM key_rotation_tasks 
            WHERE initiatorSn = :sn OR targetSn = :sn
            ORDER BY taskId DESC LIMIT 10
        ');
        $stmt->bindValue(':sn', $sn, SQLITE3_TEXT);
        $result = $stmt->execute();
        $tasks = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $tasks[] = $row;
        }
        echo json_encode(['tasks' => $tasks]);
    }
}

function handleSlaveInitiateKeyRotation($db) {
    logApiAction("handleSlaveInitiateKeyRotation called");
    
    $auth = authenticate($db);
    $slaveHost = $auth['host'];
    $slaveSn = $slaveHost['hostSn'];
    
    $newKey = generateApiKey();
    $oldKey = $slaveHost['hostApiKey'];
    
    $stmt = $db->prepare('SELECT hostSn FROM hosts WHERE hostType = "master"');
    $result = $stmt->execute();
    
    $masterSns = [];
    while ($master = $result->fetchArray(SQLITE3_ASSOC)) {
        $masterSns[] = $master['hostSn'];
    }
    
    if (empty($masterSns)) {
        $stmt = $db->prepare('
            UPDATE hosts 
            SET hostOldApiKey = :oldKey, hostApiKey = :newKey, hostDateApiUpdtae = datetime("now")
            WHERE hostSn = :sn
        ');
        $stmt->bindValue(':oldKey', $oldKey, SQLITE3_TEXT);
        $stmt->bindValue(':newKey', $newKey, SQLITE3_TEXT);
        $stmt->bindValue(':sn', $slaveSn, SQLITE3_TEXT);
        $stmt->execute();
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Key rotated locally (no masters)',
            'new_api_key' => $newKey
        ]);
        return;
    }
    
    $mastersJson = json_encode($masterSns);
    
    $stmt = $db->prepare('
        INSERT INTO key_rotation_tasks 
        (initiatorSn, targetSn, newApiKey, oldApiKey, status, attempts, maxAttempts, 
         rotationType, targetMastersJson, initiationDate)
        VALUES (:initiator, :target, :newKey, :oldKey, "pending", 0, 5, 
                "slave_to_masters", :mastersJson, datetime("now"))
    ');
    $stmt->bindValue(':initiator', $slaveSn, SQLITE3_TEXT);
    $stmt->bindValue(':target', $slaveSn, SQLITE3_TEXT);
    $stmt->bindValue(':newKey', $newKey, SQLITE3_TEXT);
    $stmt->bindValue(':oldKey', $oldKey, SQLITE3_TEXT);
    $stmt->bindValue(':mastersJson', $mastersJson, SQLITE3_TEXT);
    $stmt->execute();
    
    $taskId = $db->lastInsertRowID();
    
    logApiAction("Slave task created: ID={$taskId}, masters=" . count($masterSns));
    
    echo json_encode([
        'status' => 'success',
        'task_id' => $taskId,
        'new_api_key' => $newKey,
        'masters_count' => count($masterSns)
    ]);
}

function handleMasterReceiveSlaveKey($db) {
    logApiAction("handleMasterReceiveSlaveKey called");
    
    $input = json_decode(file_get_contents('php://input'), true);
    $slaveSn = $input['slave_sn'] ?? null;
    $newKey = $input['new_api_key'] ?? null;
    $oldKey = $input['old_api_key'] ?? null;
    $sourceTaskId = $input['task_id'] ?? null;
    
    if (!$slaveSn || !$newKey || !$oldKey) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing parameters']);
        return;
    }
    
    $slaveHost = getHostBySn($db, $slaveSn);
    if (!$slaveHost) {
        http_response_code(404);
        echo json_encode(['error' => 'Slave host not found']);
        return;
    }
      
    $currentKey = $slaveHost['hostApiKey'];
    
    $stmt = $db->prepare('
        UPDATE hosts 
        SET hostOldApiKey = :currentKey, hostApiKey = :newKey, hostDateApiUpdtae = datetime("now")
        WHERE hostSn = :sn
    ');
    $stmt->bindValue(':currentKey', $currentKey, SQLITE3_TEXT);
    $stmt->bindValue(':newKey', $newKey, SQLITE3_TEXT);
    $stmt->bindValue(':sn', $slaveSn, SQLITE3_TEXT);
    $stmt->execute();
    
    logApiAction("Master updated key for slave: {$slaveSn} (old was " . substr($currentKey, 0, 10) . "...)");
    
    $currentMasterSn = getCurrentHostSn($db);
    $otherMasters = getAllMastersExceptCurrent($db, $currentMasterSn);
    
    if (!empty($otherMasters)) {
        $mastersJson = json_encode($otherMasters);
        
        $stmt = $db->prepare('
            SELECT taskId FROM key_rotation_tasks 
            WHERE targetSn = :slaveSn AND rotationType = "slave_cascade"
            AND status IN ("pending", "in_progress")
        ');
        $stmt->bindValue(':slaveSn', $slaveSn, SQLITE3_TEXT);
        $result = $stmt->execute();
        $existingTask = $result->fetchArray(SQLITE3_ASSOC);
        
        if (!$existingTask) {
            $stmt = $db->prepare('
                INSERT INTO key_rotation_tasks 
                (initiatorSn, targetSn, newApiKey, oldApiKey, status, attempts, maxAttempts, 
                 rotationType, targetMastersJson, initiationDate, parentTaskId)
                VALUES (:initiator, :target, :newKey, :oldKey, "pending", 0, 5, 
                        "slave_cascade", :mastersJson, datetime("now"), :parentTaskId)
            ');
            $stmt->bindValue(':initiator', $slaveSn, SQLITE3_TEXT);
            $stmt->bindValue(':target', $slaveSn, SQLITE3_TEXT);
            $stmt->bindValue(':newKey', $newKey, SQLITE3_TEXT);
            $stmt->bindValue(':oldKey', $currentKey, SQLITE3_TEXT); // старый ключ
            $stmt->bindValue(':mastersJson', $mastersJson, SQLITE3_TEXT);
            $stmt->bindValue(':parentTaskId', $sourceTaskId, SQLITE3_INTEGER);
            $stmt->execute();
            
            logApiAction("Created slave_cascade task for " . count($otherMasters) . " other masters");
        }
    }
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Slave key updated successfully'
    ]);
}