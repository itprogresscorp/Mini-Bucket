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
 
define('ROOT_PATH', dirname(dirname(__FILE__)));

if (file_exists(ROOT_PATH . '/config.php')) {
    require_once ROOT_PATH . '/config.php';
}

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-API-Key, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Max-Age: 86400");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

header('Content-Type: application/json');


function validateApiKey() {
    global $db;
    
    if (!$db) {
        try {
            $db = getDB();
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database connection failed']);
            exit;
        }
    }
    
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $apiKey = $headers['X-API-Key'] ?? $_GET['api_key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '';
    
    if (empty($apiKey)) {
        if (isset($_SESSION['user_id'])) {
            return true;
        }
        http_response_code(401);
        echo json_encode(['error' => 'API key required']);
        exit;
    }
    
    $stmt = $db->prepare("SELECT idHost, hostName FROM hosts WHERE hostApiKey = :key");
    $stmt->bindValue(':key', $apiKey, SQLITE3_TEXT);
    $result = $stmt->execute();
    $host = $result->fetchArray(SQLITE3_ASSOC);
    
    if (!$host) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid API key']);
        exit;
    }
    
    return true;
}

validateApiKey();

error_reporting(E_ERROR);
ini_set('display_errors', 0);
set_time_limit(0);

define('SSH_SESSION_DIR', dirname(__FILE__) . '/tmp/console_sessions/');

if (!is_dir(SSH_SESSION_DIR)) {
    mkdir(SSH_SESSION_DIR, 0755, true);
}

function initConsole() {
    if (!function_exists('ssh2_connect')) {
        return ['success' => false, 'error' => 'SSH2 module not installed'];
    }
    
    $sessionId = session_id() . '_' . time() . '_' . rand(1000, 9999);
    $sessionFile = SSH_SESSION_DIR . $sessionId . '.json';
    
    file_put_contents($sessionFile, json_encode([
        'session_id' => $sessionId,
        'created' => time()
    ]));
    
    $connection = ssh2_connect('127.0.0.1', 22);
    if (!$connection) {
        return ['success' => false, 'error' => 'SSH connection failed'];
    }
    
    $auth = ssh2_auth_pubkey_file(
        $connection,
        'root',
        '/key/.ssh/id_rsa.pub',
        '/key/.ssh/id_rsa'
    );
    
    if (!$auth) {
        return ['success' => false, 'error' => 'SSH key auth failed'];
    }
    
    $stream = ssh2_exec($connection, 'echo "=== CONSOLE READY ===" && pwd && whoami');
    stream_set_blocking($stream, true);
    $output = stream_get_contents($stream);
    fclose($stream);
    
    ssh2_disconnect($connection);
    
    return [
        'success' => true,
        'session_id' => $sessionId,
        'output' => $output ?: "Console ready\n",
        'prompt' => '#'
    ];
}

function sendCommand($sessionId, $command) {
    $sessionFile = SSH_SESSION_DIR . $sessionId . '.json';
    if (!file_exists($sessionFile)) {
        return ['success' => false, 'error' => 'Session expired'];
    }
    
    $connection = ssh2_connect('127.0.0.1', 22);
    if (!$connection) {
        return ['success' => false, 'error' => 'SSH connection failed'];
    }
    
    $auth = ssh2_auth_pubkey_file(
        $connection,
        'root',
        '/key/.ssh/id_rsa.pub',
        '/key/.ssh/id_rsa'
    );
    
    if (!$auth) {
        return ['success' => false, 'error' => 'SSH key auth failed'];
    }
    
    $stream = ssh2_exec($connection, $command . ' 2>&1');
    stream_set_blocking($stream, true);
    $output = stream_get_contents($stream);
    fclose($stream);
    
    ssh2_disconnect($connection);
    
    $isExited = (trim($command) === 'exit' || trim($command) === 'logout');
    
    if ($isExited) {
        unlink($sessionFile);
    }
    
    if (empty(trim($output))) {
        $output = "Command executed successfully (no output)\n";
    }
    
    return [
        'success' => true,
        'output' => $output,
        'is_exited' => $isExited
    ];
}

function closeSession($sessionId) {
    $sessionFile = SSH_SESSION_DIR . $sessionId . '.json';
    if (file_exists($sessionFile)) {
        unlink($sessionFile);
    }
    return ['success' => true];
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

$input = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $json = file_get_contents('php://input');
    if ($json) {
        $input = json_decode($json, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $action = $input['action'] ?? $action;
        } else {
            $input = $_POST;
        }
    } else {
        $input = $_POST;
    }
}

$response = ['success' => false, 'error' => 'Unknown action'];

try {
    switch ($action) {
        case 'init':
            $response = initConsole();
            break;
            
        case 'command':
            $sessionId = $input['session_id'] ?? '';
            $command = $input['command'] ?? '';
            if (empty($sessionId)) {
                $response = ['success' => false, 'error' => 'Session ID required'];
                break;
            }
            $response = sendCommand($sessionId, $command);
            break;
            
        case 'close':
            $sessionId = $input['session_id'] ?? '';
            $response = closeSession($sessionId);
            break;
            
        default:
            $response = ['success' => false, 'error' => 'Unknown action: ' . $action];
    }
} catch (Exception $e) {
    $response = ['success' => false, 'error' => $e->getMessage()];
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>