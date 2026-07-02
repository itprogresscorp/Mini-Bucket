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

header('Content-Type: application/json');

define('ROOT_PATH', dirname(dirname(__FILE__)));

if (file_exists(ROOT_PATH . '/config.php')) {
    require_once ROOT_PATH . '/config.php';
}

function getTrapStatus($db) {
    $stmt = $db->prepare("SELECT setValue FROM settings WHERE setId = 3");
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    
    $enabled = ($row && $row['setValue'] == '1') ? true : false;
    return $enabled;
}

$db = getDB();
$trap_enabled = getTrapStatus($db);

require_once '../lang/loader.php';

if ($trap_enabled !== true) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Trap is disabled'
    ]);
    exit;
}

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-API-Key, Authorization, X-Requested-With, X-PIN");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Max-Age: 86400");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Function to get client IP address
function getClientIp($data = []) {
    $realIp = !empty($data['my_real_ip']) ? trim($data['my_real_ip']) : '';
    
    if (empty($realIp)) {
        $realIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    $realIp = explode(',', $realIp)[0];
    $realIp = explode(':', $realIp)[0];
    $realIp = trim($realIp);
    
    return $realIp;
}

// Function to detect real protocol (http/https)
function getRealProtocol() {
    if (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
        (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') ||
        (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
    ) {
        return 'https';
    }
    return 'http';
}

// Function to detect real port
function getRealPort() {
    if (!empty($_SERVER['HTTP_X_FORWARDED_PORT'])) {
        return (int)$_SERVER['HTTP_X_FORWARDED_PORT'];
    }
    if (!empty($_SERVER['SERVER_PORT'])) {
        return (int)$_SERVER['SERVER_PORT'];
    }
    return (getRealProtocol() === 'https') ? 443 : 80;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Use POST.'
    ]);
    exit;
}

// Get PIN
$pin = $_SERVER['HTTP_X_PIN'] ?? $_SERVER['HTTP_X_API_PIN'] ?? $_SERVER['HTTP_PIN'] ?? '';

if (empty($pin)) {
	global $lang4573;
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => $lang4573
    ]);
    exit;
}

// Verify PIN against database
try {
	global $lang4574, $lang4575;
    $stmt = $db->prepare("SELECT hostPin FROM hosts WHERE idHost = 1");
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    
    $storedPin = $row['hostPin'] ?? '';
    
    if (empty($storedPin) || $pin !== $storedPin) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => $lang4574
        ]);
        exit;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $lang4575 . $e->getMessage()
    ]);
    exit;
}

// Get JSON input
$jsonInput = file_get_contents('php://input');
if (empty($jsonInput)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'No JSON data received'
    ]);
    exit;
}

$data = json_decode($jsonInput, true);
if ($data === null) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid JSON format'
    ]);
    exit;
}

// Extract data from JSON
$arVersion = !empty($data['version']) ? trim($data['version']) : 
             (!empty($data['arVersion']) ? trim($data['arVersion']) : '');
             
$arApiKey = !empty($data['api_key']) ? trim($data['api_key']) : 
            (!empty($data['arApiKey']) ? trim($data['arApiKey']) : '');
            
$arName = !empty($data['name']) ? trim($data['name']) : 
          (!empty($data['arName']) ? trim($data['arName']) : '');
          
$arPath = !empty($data['path']) ? trim($data['path']) : 
          (!empty($data['arPath']) ? trim($data['arPath']) : '/api');
          
$arType = !empty($data['type']) ? trim($data['type']) : 
          (!empty($data['arType']) ? trim($data['arType']) : 'registration');

$arHostSn = !empty($data['host_sn']) ? trim($data['host_sn']) : 
            (!empty($data['arHostSn']) ? trim($data['arHostSn']) : '');

// Detect real connection data
$realProtocol = getRealProtocol();
$realPort = getRealPort();
$realIp = getClientIp($data);

$arDate = date('Y-m-d H:i:s');
$arReq = "incoming";

// Validation
if (empty($arName) && empty($arApiKey) && empty($arHostSn)) {
	global $lang4576;
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $lang4576
    ]);
    exit;
}

try {
    $checkColumn = $db->query("PRAGMA table_info(agent_request)");
    $columnExists = false;
    while ($col = $checkColumn->fetchArray(SQLITE3_ASSOC)) {
        if ($col['name'] === 'arReq') {
            $columnExists = true;
            break;
        }
    }
    
    if (!$columnExists) {
        $db->exec("ALTER TABLE agent_request ADD COLUMN arReq TEXT DEFAULT 'incoming'");
    }
    
    $hostSnColumnExists = false;
    $checkColumn = $db->query("PRAGMA table_info(agent_request)");
    while ($col = $checkColumn->fetchArray(SQLITE3_ASSOC)) {
        if ($col['name'] === 'arHostSn') {
            $hostSnColumnExists = true;
            break;
        }
    }
    
    if (!$hostSnColumnExists) {
        $db->exec("ALTER TABLE agent_request ADD COLUMN arHostSn TEXT");
    }
    
    $checkStmt = $db->prepare("SELECT arId, arName, arVersion, arDate, arHostSn FROM agent_request WHERE arIp = :ip ORDER BY arDate DESC LIMIT 1");
    $checkStmt->bindValue(':ip', $realIp, SQLITE3_TEXT);
    $checkResult = $checkStmt->execute();
    $existingHost = $checkResult->fetchArray(SQLITE3_ASSOC);
    
    if ($existingHost) {
        $updateStmt = $db->prepare("UPDATE agent_request SET 
            arVersion = :version,
            arApiKey = :apikey,
            arName = :name,
            arPort = :port,
            arPath = :path,
            arType = :type,
            arDate = :date,
            arProto = :proto,
            arReq = :req,
            arHostSn = :hostsn
            WHERE arIp = :ip");
        
        $updateStmt->bindValue(':version', $arVersion, SQLITE3_TEXT);
        $updateStmt->bindValue(':apikey', $arApiKey, SQLITE3_TEXT);
        $updateStmt->bindValue(':name', $arName, SQLITE3_TEXT);
        $updateStmt->bindValue(':port', $realPort, SQLITE3_TEXT);
        $updateStmt->bindValue(':path', $arPath, SQLITE3_TEXT);
        $updateStmt->bindValue(':type', $arType, SQLITE3_TEXT);
        $updateStmt->bindValue(':date', $arDate, SQLITE3_TEXT);
        $updateStmt->bindValue(':proto', $realProtocol, SQLITE3_TEXT);
        $updateStmt->bindValue(':req', $arReq, SQLITE3_TEXT);
        $updateStmt->bindValue(':hostsn', $arHostSn, SQLITE3_TEXT);
        $updateStmt->bindValue(':ip', $realIp, SQLITE3_TEXT);
        
        if ($updateStmt->execute()) {
			global $lang4577;
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => $lang4577,
                'action' => 'updated',
                'ip' => $realIp,
                'protocol' => $realProtocol,
                'port' => $realPort,
                'previous_name' => $existingHost['arName'],
                'previous_version' => $existingHost['arVersion'],
                'timestamp' => $arDate,
                'requestType' => $arReq,
                'host_sn' => $arHostSn
            ]);
        } else {
			global $lang4578;
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $lang4578
            ]);
        }
    } else {
        $insertStmt = $db->prepare("INSERT INTO agent_request (
            arVersion, 
            arApiKey, 
            arIp, 
            arName, 
            arPort, 
            arPath, 
            arType, 
            arDate,
            arProto,
            arReq,
            arHostSn
        ) VALUES (
            :version, 
            :apikey, 
            :ip, 
            :name, 
            :port, 
            :path, 
            :type, 
            :date,
            :proto,
            :req,
            :hostsn
        )");
        
        $insertStmt->bindValue(':version', $arVersion, SQLITE3_TEXT);
        $insertStmt->bindValue(':apikey', $arApiKey, SQLITE3_TEXT);
        $insertStmt->bindValue(':ip', $realIp, SQLITE3_TEXT);
        $insertStmt->bindValue(':name', $arName, SQLITE3_TEXT);
        $insertStmt->bindValue(':port', $realPort, SQLITE3_TEXT);
        $insertStmt->bindValue(':path', $arPath, SQLITE3_TEXT);
        $insertStmt->bindValue(':type', $arType, SQLITE3_TEXT);
        $insertStmt->bindValue(':date', $arDate, SQLITE3_TEXT);
        $insertStmt->bindValue(':proto', $realProtocol, SQLITE3_TEXT);
        $insertStmt->bindValue(':req', $arReq, SQLITE3_TEXT);
        $insertStmt->bindValue(':hostsn', $arHostSn, SQLITE3_TEXT);
        
        if ($insertStmt->execute()) {
            $insertId = $db->lastInsertRowID();
            global $lang4579;
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => $lang4579,
                'action' => 'inserted',
                'request_id' => $insertId,
                'ip' => $realIp,
                'protocol' => $realProtocol,
                'port' => $realPort,
                'timestamp' => $arDate,
                'requestType' => $arReq,
                'host_sn' => $arHostSn
            ]);
        } else {
			global $lang4580;
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $lang4580
            ]);
        }
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
    exit;
}
?>