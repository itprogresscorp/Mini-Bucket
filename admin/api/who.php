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

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Content-Type: application/json');
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, X-API-Key, X-PIN, X-Requested-With");
    header("Access-Control-Max-Age: 86400");
    http_response_code(200);
    exit();
}

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-API-Key, X-PIN, X-Requested-With");

define('ROOT_PATH', dirname(dirname(__FILE__)));

if (file_exists(ROOT_PATH . '/config.php')) {
    require_once ROOT_PATH . '/config.php';
}

$db = getDB();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

require_once '../lang/loader.php';

/**
 * Получить локальную информацию о сервере
 */
function getLocalServerInfo($db) {
	global $lang4538;
    $stmt = $db->prepare("SELECT hostSn, hostName, hostVersion FROM hosts WHERE idHost = 1");
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    
    if ($row) {
        return [
            'success' => true,
            'serial_number' => $row['hostSn'] ?: '',
            'name' => $row['hostName'] ?: '',
            'version' => $row['hostVersion'] ?: ''
        ];
    }
    
    return ['success' => false, 'message' => $lang4538];
}

/**
 * Проверка API (тест соединения)
 */
function testApi($db) {
    try {
        $result = $db->query("SELECT 1");
        if ($result) {
            return [
                'success' => true,
                'test' => 'ok',
                'timestamp' => date('Y-m-d H:i:s'),
                'database' => 'connected',
                'php_version' => PHP_VERSION
            ];
        }
    } catch (Exception $e) {
        return [
            'success' => false,
            'test' => 'failed',
            'message' => $e->getMessage()
        ];
    }
    
    return ['success' => false, 'test' => 'failed', 'message' => 'Unknown error'];
}

// ========== API ROUTER ==========

switch ($action) {
    case 'who':
        echo json_encode(getLocalServerInfo($db));
        break;
        
    case 'test_api':
        echo json_encode(testApi($db));
        break;
        
    default:
        echo json_encode([
            'success' => false, 
            'message' => 'Unknown action. Available actions: who, test_api'
        ]);
}
?>