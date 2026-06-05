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

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-API-Key, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Max-Age: 86400");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

define('ROOT_PATH', dirname(dirname(__FILE__)));

if (file_exists(ROOT_PATH . '/config.php')) {
    require_once ROOT_PATH . '/config.php';
}


    function disable_ssl_verify($ch) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        return $ch;
}

isAuthenticated();

$db = getDB();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) {
        return (string)$needle !== '' && strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}

/**
 * Получить все хосты
 */
function getHosts($db) {
    $result = $db->query("SELECT * FROM hosts ORDER BY idHost DESC");
    $hosts = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $hosts[] = $row;
    }
    return $hosts;
}

/**
 * Получить один хост по ID
 */
function getHostById($db, $idHost) {
    $stmt = $db->prepare("SELECT * FROM hosts WHERE idHost = :id");
    $stmt->bindValue(':id', $idHost, SQLITE3_INTEGER);
    $result = $stmt->execute();
    return $result->fetchArray(SQLITE3_ASSOC);
}

function createMasterOnSlave($db, $slaveData) {
    // 1. Собираем данные о текущем сервере (себе - Master)
    $masterStmt = $db->prepare("SELECT hostSn, hostName, hostVersion, hostApiKey, hostPin FROM hosts WHERE idHost = 1");
    $masterResult = $masterStmt->execute();
    $master = $masterResult->fetchArray(SQLITE3_ASSOC);
    
    if (!$master) {
        return ['success' => false, 'message' => 'Master server not found in database'];
    }
    
    // 2. Определяем реальный IP текущего сервера
    $serverIp = $_SERVER['SERVER_ADDR'];
    if ($serverIp === '127.0.0.1' || $serverIp === '::1') {
        $serverIp = gethostbyname(gethostname());
        if ($serverIp === '127.0.0.1') {
            $externalIp = @file_get_contents('https://api.ipify.org');
            if ($externalIp) {
                $serverIp = trim($externalIp);
            }
        }
    }
    
    // 3. Определяем реальный протокол и порт
    $proto = 'http';
    $port = 80;
    if (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
    ) {
        $proto = 'https';
        $port = 443;
    }
    if (!empty($_SERVER['SERVER_PORT'])) {
        $port = (int)$_SERVER['SERVER_PORT'];
    }
    
    // 4. Собираем данные Master для отправки
    $masterData = [
        'hostIp' => $serverIp,
        'hostName' => $master['hostName'],
        'hostApiKey' => 'Secret',
        'hostProto' => $proto,
        'hostPort' => $port,
        'hostApiPath' => '/api',
        'hostSn' => $master['hostSn'],
        'hostType' => 'master',
        'hostComment' => 'Automatically created from remote Master on ' . date('Y-m-d H:i:s')
    ];
    
    // 5. Строим URL для запроса к Slave
    $slaveProto = $slaveData['hostProto'] ?? 'http';
    $slaveIp = $slaveData['hostIp'];
    $slavePort = $slaveData['hostPort'] ? ':' . $slaveData['hostPort'] : '';
    $slaveApiPath = rtrim($slaveData['hostApiPath'] ?? '/api', '/');
    $slaveApiKey = $slaveData['hostApiKey'];
    
    $url = $slaveProto . '://' . $slaveIp . $slavePort . $slaveApiPath . '/host_manager_api.php';
    
    // 6. Отправляем POST запрос на Slave с action=create_master
    $postData = [
        'action' => 'create_master',
        'master_data' => $masterData
    ];
    
    try {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            'X-API-Key: ' . $slaveApiKey
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            return [
                'success' => false,
                'message' => 'Slave host added, but failed to create master on slave: ' . $curlError,
                'slave_created' => true
            ];
        }
        
        $responseData = json_decode($response, true);
        
        if ($httpCode === 200 && isset($responseData['success']) && $responseData['success']) {
            return [
                'success' => true,
                'message' => 'Slave host added and master created on slave successfully',
                'slave_created' => true,
                'master_created' => true
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Slave added but master creation failed: ' . ($responseData['message'] ?? 'Unknown error'),
                'slave_created' => true,
                'master_created' => false
            ];
        }
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Slave added but error creating master: ' . $e->getMessage(),
            'slave_created' => true,
            'master_created' => false
        ];
    }
}

/**
 * Сохранить хост (добавить или обновить)
 */
function saveHost($db, $data) {
    $idHost = $data['idHost'] ?? '';
    $hostIp = trim($data['hostIp'] ?? '');
    $hostName = trim($data['hostName'] ?? '');
    $hostApiKey = trim($data['hostApiKey'] ?? '');
    $hostComment = trim($data['hostComment'] ?? '');
    $hostProto = trim($data['hostProto'] ?? 'http');
    $hostPort = trim($data['hostPort'] ?? '');
    $hostApiPath = trim($data['hostApiPath'] ?? '/api');
    $hostSn = trim($data['hostSn'] ?? '');
    $hostType = 'slave';
    $skipApiTest = isset($data['skip_api_test']) ? filter_var($data['skip_api_test'], FILTER_VALIDATE_BOOLEAN) : false;
    
    if (!empty($idHost) && $idHost == 1) {
        $hostType = 'master';
    } elseif (!empty($idHost)) {
        $checkStmt = $db->prepare("SELECT hostType FROM hosts WHERE idHost = :id");
        $checkStmt->bindValue(':id', $idHost, SQLITE3_INTEGER);
        $checkResult = $checkStmt->execute();
        $existing = $checkResult->fetchArray(SQLITE3_ASSOC);
        if ($existing && $existing['hostType'] === 'master') {
            $hostType = 'master';
        }
    }
    
    // Validation
    if (empty($hostIp) || empty($hostName) || empty($hostApiKey)) {
        return ['success' => false, 'message' => 'IP, Name and API Key are required'];
    }
    
    if (!empty($hostPort) && (!is_numeric($hostPort) || $hostPort < 1 || $hostPort > 65535)) {
        return ['success' => false, 'message' => 'Port must be between 1 and 65535'];
    }
    
    if (!empty($hostApiPath) && !str_starts_with($hostApiPath, '/')) {
        $hostApiPath = '/' . $hostApiPath;
    }
    
    if (empty($idHost) && !$skipApiTest) {
        $testData = [
            'hostIp' => $hostIp,
            'hostName' => $hostName,
            'hostApiKey' => $hostApiKey,
            'hostProto' => $hostProto,
            'hostPort' => $hostPort,
            'hostApiPath' => $hostApiPath
        ];
        
        $apiTest = testHostApi($db, $testData);
        
        if (!$apiTest['success']) {
            return [
                'success' => false, 
                'message' => 'API test failed: ' . $apiTest['message'],
                'api_test' => $apiTest,
                'can_force' => true
            ];
        }
    }
    
    try {
        if (empty($idHost)) {
            $stmt = $db->prepare("INSERT INTO hosts (
                hostIp, hostName, hostApiKey, hostComment, hostStatus, hostLive, 
                hostDateApiUpdtae, hostVersion, hostProto, hostPort, hostApiPath, hostSn, hostType
            ) VALUES (
                :ip, :name, :apikey, :comment, 'pending', 'unknown', 
                datetime('now'), 'unknown', :proto, :port, :apipath, :sn, :type
            )");
            $stmt->bindValue(':type', $hostType, SQLITE3_TEXT);
            $stmt->bindValue(':ip', $hostIp, SQLITE3_TEXT);
            $stmt->bindValue(':name', $hostName, SQLITE3_TEXT);
            $stmt->bindValue(':apikey', $hostApiKey, SQLITE3_TEXT);
            $stmt->bindValue(':comment', $hostComment, SQLITE3_TEXT);
            $stmt->bindValue(':proto', $hostProto, SQLITE3_TEXT);
            $stmt->bindValue(':port', $hostPort ?: null, SQLITE3_TEXT);
            $stmt->bindValue(':apipath', $hostApiPath, SQLITE3_TEXT);
            $stmt->bindValue(':sn', $hostSn ?: null, SQLITE3_TEXT);
            
            if ($stmt->execute()) {
                $newId = $db->lastInsertRowID();
                
                if ($hostType === 'slave') {
                    $slaveData = [
                        'idHost' => $newId,
                        'hostIp' => $hostIp,
                        'hostName' => $hostName,
                        'hostApiKey' => $hostApiKey,
                        'hostProto' => $hostProto,
                        'hostPort' => $hostPort,
                        'hostApiPath' => $hostApiPath,
                        'hostSn' => $hostSn,
                        'hostType' => $hostType
                    ];
                    
                    $result = createMasterOnSlave($db, $slaveData);
                    
                    if ($result['success']) {
                        return [
                            'success' => true, 
                            'message' => $result['message'],
                            'idHost' => $newId,
                            'api_test' => $apiTest ?? null
                        ];
                    } else {
                        return [
                            'success' => true, 
                            'message' => $result['message'],
                            'idHost' => $newId,
                            'warning' => true,
                            'api_test' => $apiTest ?? null
                        ];
                    }
                }
                
                return [
                    'success' => true, 
                    'message' => 'Host added successfully', 
                    'idHost' => $newId,
                    'api_test' => $apiTest ?? null
                ];
            }
            return ['success' => false, 'message' => 'Failed to add host'];
        } else {
            $currentSn = null;
            if (empty($hostSn)) {
                $getSnStmt = $db->prepare("SELECT hostSn FROM hosts WHERE idHost = :id");
                $getSnStmt->bindValue(':id', $idHost, SQLITE3_INTEGER);
                $getSnResult = $getSnStmt->execute();
                $existingHost = $getSnResult->fetchArray(SQLITE3_ASSOC);
                $currentSn = $existingHost['hostSn'] ?? null;
            } else {
                $currentSn = $hostSn;
            }
            
            $stmt = $db->prepare("UPDATE hosts SET 
                hostIp = :ip, hostName = :name, hostApiKey = :apikey, 
                hostComment = :comment, hostProto = :proto, hostPort = :port,
                hostApiPath = :apipath, hostSn = :sn, hostType = :type
                WHERE idHost = :id");
            $stmt->bindValue(':type', $hostType, SQLITE3_TEXT);
            $stmt->bindValue(':ip', $hostIp, SQLITE3_TEXT);
            $stmt->bindValue(':name', $hostName, SQLITE3_TEXT);
            $stmt->bindValue(':apikey', $hostApiKey, SQLITE3_TEXT);
            $stmt->bindValue(':comment', $hostComment, SQLITE3_TEXT);
            $stmt->bindValue(':proto', $hostProto, SQLITE3_TEXT);
            $stmt->bindValue(':port', $hostPort ?: null, SQLITE3_TEXT);
            $stmt->bindValue(':apipath', $hostApiPath, SQLITE3_TEXT);
            $stmt->bindValue(':sn', $currentSn, SQLITE3_TEXT);
            $stmt->bindValue(':id', $idHost, SQLITE3_INTEGER);
            
            if ($stmt->execute()) {
                return ['success' => true, 'message' => 'Host updated successfully', 'idHost' => $idHost];
            }
            return ['success' => false, 'message' => 'Failed to update host'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

function createMasterHost($db, $masterData) {
    // Проверяем обязательные поля
    if (empty($masterData['hostIp']) || empty($masterData['hostName']) || empty($masterData['hostApiKey'])) {
        return ['success' => false, 'message' => 'Missing required master data: IP, Name or API Key'];
    }
    
    // Проверяем, не существует ли уже хост с таким IP
    $checkStmt = $db->prepare("SELECT idHost FROM hosts WHERE hostIp = :ip");
    $checkStmt->bindValue(':ip', $masterData['hostIp'], SQLITE3_TEXT);
    $checkResult = $checkStmt->execute();
    
    if ($checkResult->fetchArray(SQLITE3_ASSOC)) {
        return ['success' => false, 'message' => 'Master host with this IP already exists'];
    }
    
    // Подготавливаем данные
    $hostIp = trim($masterData['hostIp']);
    $hostName = trim($masterData['hostName']);
    $hostApiKey = trim($masterData['hostApiKey']);
    $hostProto = trim($masterData['hostProto'] ?? 'http');
    $hostPort = trim($masterData['hostPort'] ?? '');
    $hostApiPath = trim($masterData['hostApiPath'] ?? '/api');
    $hostSn = trim($masterData['hostSn'] ?? '');
    $hostType = 'master';
    $hostComment = trim($masterData['hostComment'] ?? 'Created by remote Master server');
    
    // Валидация порта
    if (!empty($hostPort) && (!is_numeric($hostPort) || $hostPort < 1 || $hostPort > 65535)) {
        return ['success' => false, 'message' => 'Port must be between 1 and 65535'];
    }
    
    // Проверяем API путь
    if (!empty($hostApiPath) && !str_starts_with($hostApiPath, '/')) {
        $hostApiPath = '/' . $hostApiPath;
    }
    
    try {
        $stmt = $db->prepare("INSERT INTO hosts (
            hostIp, hostName, hostApiKey, hostComment, hostStatus, hostLive, 
            hostDateApiUpdtae, hostVersion, hostProto, hostPort, hostApiPath, hostSn, hostType
        ) VALUES (
            :ip, :name, :apikey, :comment, 'pending', 'unknown', 
            datetime('now'), 'unknown', :proto, :port, :apipath, :sn, 'master'
        )");
        
        $stmt->bindValue(':ip', $hostIp, SQLITE3_TEXT);
        $stmt->bindValue(':name', $hostName, SQLITE3_TEXT);
        $stmt->bindValue(':apikey', $hostApiKey, SQLITE3_TEXT);
        $stmt->bindValue(':comment', $hostComment, SQLITE3_TEXT);
        $stmt->bindValue(':proto', $hostProto, SQLITE3_TEXT);
        $stmt->bindValue(':port', $hostPort ?: null, SQLITE3_TEXT);
        $stmt->bindValue(':apipath', $hostApiPath, SQLITE3_TEXT);
        $stmt->bindValue(':sn', $hostSn ?: null, SQLITE3_TEXT);
        
        if ($stmt->execute()) {
            $newId = $db->lastInsertRowID();
            return [
                'success' => true, 
                'message' => 'Master host created successfully',
                'idHost' => $newId
            ];
        }
        
        return ['success' => false, 'message' => 'Failed to create master host'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

/**
 * Удалить хост
 */
function deleteHost($db, $idHost) {
    $stmt = $db->prepare("DELETE FROM hosts WHERE idHost = :id");
    $stmt->bindValue(':id', $idHost, SQLITE3_INTEGER);
    return ['success' => $stmt->execute(), 'message' => $stmt->execute() ? 'Host deleted' : 'Delete failed'];
}

function testHostApi($db, $hostData) {
    $proto = $hostData['hostProto'] ?? 'http';
    $ip = $hostData['hostIp'];
    $port = $hostData['hostPort'] ? ':' . $hostData['hostPort'] : '';
    $apiPath = rtrim($hostData['hostApiPath'] ?? '/api', '/');
    $apiKey = $hostData['hostApiKey'] ?? '';
    
    // Пробуем оба endpoint'а: who.php и test_api
    $endpoints = [
        '/who.php?action=who',
        '/who.php?action=test_api',
        '/api/test_api'
    ];
    
    $results = [];
    
    foreach ($endpoints as $endpoint) {
        $url = $proto . '://' . $ip . $port . $apiPath . $endpoint;
        
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            
            if (!empty($apiKey)) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'X-API-Key: ' . $apiKey,
                    'Content-Type: application/json'
                ]);
            }
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($curlError) {
                $results[] = [
                    'endpoint' => $endpoint,
                    'success' => false,
                    'message' => 'CURL error: ' . $curlError
                ];
                continue;
            }
            
            if ($httpCode === 200) {
                $data = json_decode($response, true);
                if ($data && (isset($data['success']) || isset($data['test']))) {
                    return [
                        'success' => true,
                        'message' => 'API is accessible',
                        'endpoint' => $endpoint,
                        'http_code' => $httpCode,
                        'response' => $data
                    ];
                }
            }
            
            $results[] = [
                'endpoint' => $endpoint,
                'success' => false,
                'http_code' => $httpCode,
                'message' => 'HTTP ' . $httpCode
            ];
            
        } catch (Exception $e) {
            $results[] = [
                'endpoint' => $endpoint,
                'success' => false,
                'message' => 'Exception: ' . $e->getMessage()
            ];
        }
    }
    
    // Если ни один endpoint не сработал
    return [
        'success' => false,
        'message' => 'API not accessible. Tried: ' . implode(', ', array_column($results, 'endpoint')),
        'details' => $results
    ];
}

function fullTestHost($db, $idHost) {
    $host = getHostById($db, $idHost);
    if (!$host) {
        return ['success' => false, 'message' => 'Host not found', 'status' => 'offline'];
    }
    
    $results = [
        'ping' => null,
        'api' => null,
        'overall' => false
    ];
    
    // 1. Ping тест
    $ip = $host['hostIp'];
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $ping = exec("ping -n 1 -w 2 " . escapeshellarg($ip) . " 2>&1", $output, $return_var);
    } else {
        $ping = exec("ping -c 1 -W 2 " . escapeshellarg($ip) . " 2>&1", $output, $return_var);
    }
    
    if ($return_var === 0) {
        $results['ping'] = ['success' => true, 'message' => 'Host is reachable'];
    } else {
        $results['ping'] = ['success' => false, 'message' => 'Host is not reachable'];
    }
    
    // 2. API тест
    $apiTest = testHostApi($db, $host);
    $results['api'] = $apiTest;
    
    // 3. Общий вердикт
    if ($results['ping']['success'] && $results['api']['success']) {
        $results['overall'] = true;
        $results['message'] = 'Host is online and API is accessible';
        $status = 'online';
    } elseif ($results['ping']['success'] && !$results['api']['success']) {
        $results['overall'] = false;
        $results['message'] = 'Host is reachable but API is not accessible';
        $status = 'pending';
    } else {
        $results['overall'] = false;
        $results['message'] = 'Host is offline';
        $status = 'offline';
    }
    
    // Обновляем статус в БД
    $update = $db->prepare("UPDATE hosts SET hostLive = :status, hostStatus = :status, hostDateApiUpdtae = datetime('now') WHERE idHost = :id");
    $update->bindValue(':status', $status, SQLITE3_TEXT);
    $update->bindValue(':id', $idHost, SQLITE3_INTEGER);
    $update->execute();
    
    return $results;
}

/**
 * Тест соединения с хостом (ping)
 */
function testHost($db, $idHost) {
    $result = fullTestHost($db, $idHost);
    
    return [
        'success' => $result['overall'],
        'message' => $result['message'],
        'status' => $result['overall'] ? 'online' : ($result['ping']['success'] ? 'pending' : 'offline'),
        'details' => [
            'ping' => $result['ping'],
            'api' => $result['api']
        ]
    ];
}

/**
 * Обновить статус всех хостов (массовый пинг)
 */
function refreshAllHostsStatus($db) {
    $hosts = getHosts($db);
    $results = [];
    foreach ($hosts as $host) {
        $results[] = testHost($db, $host['idHost']);
    }
    return ['success' => true, 'results' => $results];
}

/**
 * Получить входящие запросы (agent_request)
 */
function getIncomingRequests($db) {
    $stmt = $db->prepare("SELECT * FROM agent_request WHERE arReq IS NULL OR arReq = '' OR arReq = 'incoming' ORDER BY arId DESC");
    $result = $stmt->execute();
    $requests = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $requests[] = $row;
    }
    return $requests;
}

/**
 * Получить исходящие запросы
 */
function getOutgoingRequests($db) {
    $stmt = $db->prepare("SELECT * FROM agent_request WHERE arReq = 'outgoing' ORDER BY arDate DESC");
    $result = $stmt->execute();
    $requests = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $requests[] = $row;
    }
    return $requests;
}

/**
 * Принять входящий запрос в хосты
 */
function joinRequestToHosts($db, $arId) {
    $stmt = $db->prepare("SELECT * FROM agent_request WHERE arId = :id");
    $stmt->bindValue(':id', $arId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $request = $result->fetchArray(SQLITE3_ASSOC);
    
    if (!$request) {
        return ['success' => false, 'message' => 'Request not found'];
    }
    
    // Проверяем существует ли уже хост с таким IP
    $checkStmt = $db->prepare("SELECT idHost FROM hosts WHERE hostIp = :ip");
    $checkStmt->bindValue(':ip', $request['arIp'], SQLITE3_TEXT);
    $checkResult = $checkStmt->execute();
    if ($checkResult->fetchArray(SQLITE3_ASSOC)) {
        return ['success' => false, 'message' => 'Host with this IP already exists'];
    }
    
    $hostApiKey = $request['arApiKey'];
    
    $insertStmt = $db->prepare("INSERT INTO hosts (
        hostIp, hostName, hostApiKey, hostComment, hostStatus, hostLive,
        hostDateApiUpdtae, hostVersion, hostProto, hostPort, hostApiPath,
        hostType, hostAddedData, hostWarning, hostOldApiKey, hostSn
    ) VALUES (
        :ip, :name, :apikey, :comment, 'pending', 'unknown',
        datetime('now'), :version, :proto, :port, :apipath,
        :type, :addeddata, '', '', :sn
    )");
    
    $insertStmt->bindValue(':ip', $request['arIp'], SQLITE3_TEXT);
    $insertStmt->bindValue(':name', $request['arName'], SQLITE3_TEXT);
    $insertStmt->bindValue(':apikey', $hostApiKey, SQLITE3_TEXT);  // ← Ключ из запроса
    $insertStmt->bindValue(':comment', "Added from agent request (ID: {$request['arId']}) on " . date('Y-m-d H:i:s') . "\nOriginal type: {$request['arType']}", SQLITE3_TEXT);
    $insertStmt->bindValue(':version', $request['arVersion'], SQLITE3_TEXT);
    $insertStmt->bindValue(':proto', $request['arProto'], SQLITE3_TEXT);
    $insertStmt->bindValue(':port', $request['arPort'], SQLITE3_TEXT);
    $insertStmt->bindValue(':apipath', $request['arPath'], SQLITE3_TEXT);
    $insertStmt->bindValue(':type', $request['arType'], SQLITE3_TEXT);
    $insertStmt->bindValue(':addeddata', $request['arDate'], SQLITE3_TEXT);
    $insertStmt->bindValue(':sn', $request['arHostSn'], SQLITE3_TEXT);
    
    if ($insertStmt->execute()) {
        $deleteStmt = $db->prepare("DELETE FROM agent_request WHERE arId = :id");
        $deleteStmt->bindValue(':id', $arId, SQLITE3_INTEGER);
        $deleteStmt->execute();
        
        return ['success' => true, 'message' => 'Host joined and request deleted successfully!'];
    }
    return ['success' => false, 'message' => 'Failed to join host'];
}

/**
 * Удалить входящий запрос
 */
function deleteIncomingRequest($db, $arId) {
    $stmt = $db->prepare("DELETE FROM agent_request WHERE arId = :id AND (arReq IS NULL OR arReq = '' OR arReq = 'incoming')");
    $stmt->bindValue(':id', $arId, SQLITE3_INTEGER);
    return ['success' => $stmt->execute(), 'message' => $stmt->execute() ? 'Request deleted' : 'Delete failed'];
}

/**
 * Удалить все входящие запросы
 */
function deleteAllIncomingRequests($db) {
    $stmt = $db->prepare("DELETE FROM agent_request WHERE arReq IS NULL OR arReq = '' OR arReq = 'incoming'");
    return ['success' => $stmt->execute(), 'message' => $stmt->execute() ? 'All requests deleted' : 'Delete failed'];
}

/**
 * Удалить исходящий запрос
 */
function deleteOutgoingRequest($db, $arId) {
    $stmt = $db->prepare("DELETE FROM agent_request WHERE arId = :id AND arReq = 'outgoing'");
    $stmt->bindValue(':id', $arId, SQLITE3_INTEGER);
    return ['success' => $stmt->execute(), 'message' => $stmt->execute() ? 'Outgoing request revoked' : 'Delete failed'];
}

/**
 * Удалить все исходящие запросы
 */
function deleteAllOutgoingRequests($db) {
    $stmt = $db->prepare("DELETE FROM agent_request WHERE arReq = 'outgoing'");
    return ['success' => $stmt->execute(), 'message' => $stmt->execute() ? 'All outgoing requests revoked' : 'Delete failed'];
}

/**
 * Получить PIN для хоста (idHost=1)
 */
function getHostPin($db) {
    $stmt = $db->prepare("SELECT hostPin FROM hosts WHERE idHost = 1");
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    return $row ? ($row['hostPin'] ?: '') : '';
}

/**
 * Обновить PIN
 */
function regenerateHostPin($db) {
    $chars = '0123456789';
    $newPin = '';
    for ($i = 0; $i < 4; $i++) {
        $newPin .= $chars[random_int(0, strlen($chars) - 1)];
    }
    $stmt = $db->prepare("UPDATE hosts SET hostPin = :pin WHERE idHost = 1");
    $stmt->bindValue(':pin', $newPin, SQLITE3_TEXT);
    if ($stmt->execute()) {
        return ['success' => true, 'pin' => $newPin];
    }
    return ['success' => false, 'message' => 'Failed to update PIN'];
}

/**
 * Создать исходящий запрос к удалённому серверу
 */
function createOutgoingRequest($db, $data) {
    $serverIp = $data['server_ip'] ?? '';
    $serverPin = $data['server_pin'] ?? '';
    $serverProto = $data['server_proto'] ?? 'http';
    $serverPort = $data['server_port'] ?? '';
    $serverApiPath = $data['server_api_path'] ?? '/api';
    $targetHostname = $data['target_hostname'] ?? '';
    $targetVersion = $data['target_version'] ?? '';
    $targetApiKey = $data['target_api_key'] ?? '';
    $targetHostSn = $data['target_host_sn'] ?? '';
    
    if (empty($serverIp) || empty($serverPin) || empty($targetApiKey)) {
        return ['success' => false, 'message' => 'Server IP, PIN and API Key are required'];
    }
    
    // Проверяем, не существует ли уже хост с таким IP
    $checkStmt = $db->prepare("SELECT idHost FROM hosts WHERE hostIp = :ip");
    $checkStmt->bindValue(':ip', $serverIp, SQLITE3_TEXT);
    $checkResult = $checkStmt->execute();
    if ($checkResult->fetchArray(SQLITE3_ASSOC)) {
        return ['success' => false, 'message' => 'Host with this IP already exists'];
    }
    
    // 1. СРАЗУ СОЗДАЕМ MASTER ХОСТ В ТАБЛИЦУ hosts
    $insertHostStmt = $db->prepare("INSERT INTO hosts (
        hostIp, hostName, hostApiKey, hostComment, hostStatus, hostLive, 
        hostDateApiUpdtae, hostVersion, hostProto, hostPort, hostApiPath, 
        hostSn, hostType
    ) VALUES (
        :ip, :name, :apikey, :comment, 'pending', 'unknown',
        datetime('now'), :version, :proto, :port, :apipath,
        :sn, 'master'
    )");
    
    $insertHostStmt->bindValue(':ip', $serverIp, SQLITE3_TEXT);
    $insertHostStmt->bindValue(':name', 'Pending...', SQLITE3_TEXT);
    $insertHostStmt->bindValue(':apikey', 'Secret', SQLITE3_TEXT);
    $insertHostStmt->bindValue(':comment', "Master server added on " . date('Y-m-d H:i:s'), SQLITE3_TEXT);
    $insertHostStmt->bindValue(':version', 'Pending...', SQLITE3_TEXT);
    $insertHostStmt->bindValue(':proto', $serverProto, SQLITE3_TEXT);
    $insertHostStmt->bindValue(':port', $serverPort ?: null, SQLITE3_TEXT);
    $insertHostStmt->bindValue(':apipath', $serverApiPath, SQLITE3_TEXT);
    $insertHostStmt->bindValue(':sn', 'Pending...', SQLITE3_TEXT);
    
    if (!$insertHostStmt->execute()) {
        return ['success' => false, 'message' => 'Failed to create master host in database'];
    }
    
    $newHostId = $db->lastInsertRowID();
    
    // 2. ОТПРАВЛЯЕМ ЗАПРОС НА УДАЛЕННЫЙ СЕРВЕР
    $url = $serverProto . '://' . $serverIp;
    if ($serverPort) $url .= ':' . $serverPort;
    $url .= $serverApiPath . '/connector_trap.php';
    
    $postData = [
        'version' => $targetVersion,
        'name' => $targetHostname,
        'type' => 'slave',
        'api_key' => $targetApiKey,
        'path' => $serverApiPath,
        'host_sn' => $targetHostSn
    ];
    
    try {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-PIN: ' . $serverPin
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            return [
                'success' => true, 
                'message' => 'Master host saved, but remote server unreachable: ' . $curlError,
                'host_id' => $newHostId,
                'remote_reachable' => false
            ];
        }
        
        $responseData = json_decode($response, true);
        
        if ($httpCode === 200 && isset($responseData['success']) && $responseData['success']) {
            return [
                'success' => true, 
                'message' => 'Master host added and remote server approved connection',
                'host_id' => $newHostId,
                'remote_reachable' => true,
                'remote_response' => $responseData
            ];
        } else {
            return [
                'success' => true,
                'message' => 'Master host saved, but remote server returned: ' . ($responseData['message'] ?? 'Unknown error'),
                'host_id' => $newHostId,
                'remote_reachable' => true,
                'remote_response' => $responseData
            ];
        }
    } catch (Exception $e) {
        return [
            'success' => true,
            'message' => 'Master host saved, but connection error: ' . $e->getMessage(),
            'host_id' => $newHostId,
            'remote_reachable' => false
        ];
    }
}

function getHostApiKey($db) {
    $stmt = $db->prepare("SELECT hostApiKey FROM hosts WHERE idHost = 1");
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    return $row ? ($row['hostApiKey'] ?: '') : '';
}

function getLocalHostSn($db) {
    $stmt = $db->prepare("SELECT hostSn, hostName, hostVersion FROM hosts WHERE idHost = 1");
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    
    if ($row) {
        return [
            'success' => true, 
            'sn' => $row['hostSn'] ?: '',
            'name' => $row['hostName'] ?: '',
            'version' => $row['hostVersion'] ?: ''
        ];
    }
    
    return ['success' => false, 'message' => 'Host not found'];
}

function saveHostSn($db, $idHost, $sn, $name = null, $version = null) {
    if ($name !== null && $version !== null) {
        // Обновляем SN, Name и Version
        $stmt = $db->prepare("UPDATE hosts SET hostSn = :sn, hostName = :name, hostVersion = :version WHERE idHost = :id");
        $stmt->bindValue(':name', $name, SQLITE3_TEXT);
        $stmt->bindValue(':version', $version, SQLITE3_TEXT);
    } else {
        // Обновляем только SN
        $stmt = $db->prepare("UPDATE hosts SET hostSn = :sn WHERE idHost = :id");
    }
    
    $stmt->bindValue(':sn', $sn, SQLITE3_TEXT);
    $stmt->bindValue(':id', $idHost, SQLITE3_INTEGER);
    
    if ($stmt->execute()) {
        return ['success' => true, 'message' => 'Host info saved successfully'];
    }
    return ['success' => false, 'message' => 'Failed to save host info'];
}


/**
 * Подтверждение или отклонение исходящего запроса
 */
function confirmOutgoingRequest($db, $arId, $action) {
    $stmt = $db->prepare("SELECT * FROM agent_request WHERE arId = :id AND arReq = 'outgoing'");
    $stmt->bindValue(':id', $arId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $request = $result->fetchArray(SQLITE3_ASSOC);
    
    if (!$request) {
        return ['success' => false, 'message' => 'Outgoing request not found'];
    }
    
    if ($action === 'accept') {
        // Проверяем существует ли уже хост с таким IP
        $checkStmt = $db->prepare("SELECT idHost FROM hosts WHERE hostIp = :ip");
        $checkStmt->bindValue(':ip', $request['arIp'], SQLITE3_TEXT);
        $checkResult = $checkStmt->execute();
        if ($checkResult->fetchArray(SQLITE3_ASSOC)) {
            return ['success' => false, 'message' => 'Host with this IP already exists'];
        }
        
        // Создаем хост из запроса
        $insertStmt = $db->prepare("INSERT INTO hosts (
            hostIp, hostName, hostApiKey, hostComment, hostStatus, hostLive,
            hostDateApiUpdtae, hostVersion, hostProto, hostPort, hostApiPath,
            hostType, hostAddedData, hostWarning, hostOldApiKey, hostSn
        ) VALUES (
            :ip, :name, :apikey, :comment, 'pending', 'unknown',
            datetime('now'), :version, :proto, :port, :apipath,
            'slave', :addeddata, '', '', :sn
        )");
        
        $comment = "Created from outgoing request (ID: {$request['arId']}) on " . date('Y-m-d H:i:s') . 
                   "\nRemote server requested connection.\nOriginal type: {$request['arType']}";
        
        $insertStmt->bindValue(':ip', $request['arIp'], SQLITE3_TEXT);
        $insertStmt->bindValue(':name', $request['arName'], SQLITE3_TEXT);
        $insertStmt->bindValue(':apikey', $request['arApiKey'], SQLITE3_TEXT);
        $insertStmt->bindValue(':comment', $comment, SQLITE3_TEXT);
        $insertStmt->bindValue(':version', $request['arVersion'], SQLITE3_TEXT);
        $insertStmt->bindValue(':proto', $request['arProto'], SQLITE3_TEXT);
        $insertStmt->bindValue(':port', $request['arPort'], SQLITE3_TEXT);
        $insertStmt->bindValue(':apipath', $request['arPath'], SQLITE3_TEXT);
        $insertStmt->bindValue(':addeddata', $request['arDate'], SQLITE3_TEXT);
        $insertStmt->bindValue(':sn', $request['arHostSn'], SQLITE3_TEXT);
        
        if ($insertStmt->execute()) {
            $newId = $db->lastInsertRowID();
            
            // Удаляем запрос
            $deleteStmt = $db->prepare("DELETE FROM agent_request WHERE arId = :id");
            $deleteStmt->bindValue(':id', $arId, SQLITE3_INTEGER);
            $deleteStmt->execute();
            
            return [
                'success' => true, 
                'message' => 'Request accepted and host created successfully',
                'host_id' => $newId
            ];
        }
        return ['success' => false, 'message' => 'Failed to create host from request'];
        
    } elseif ($action === 'reject') {
        // Просто удаляем запрос
        $deleteStmt = $db->prepare("DELETE FROM agent_request WHERE arId = :id");
        $deleteStmt->bindValue(':id', $arId, SQLITE3_INTEGER);
        
        if ($deleteStmt->execute()) {
            return ['success' => true, 'message' => 'Request rejected and removed'];
        }
        return ['success' => false, 'message' => 'Failed to remove request'];
    }
    
    return ['success' => false, 'message' => 'Invalid action. Use "accept" or "reject"'];
}

/**
 * Тест скорости между хостами
 */
function speedTestBetweenHosts($db, $sourceHostId, $targetHostId, $testSize = 10240) {
    // Получаем данные исходного хоста
    $sourceHost = getHostById($db, $sourceHostId);
    if (!$sourceHost) {
        return ['success' => false, 'message' => 'Source host not found'];
    }
    
    // Получаем данные целевого хоста
    $targetHost = getHostById($db, $targetHostId);
    if (!$targetHost) {
        return ['success' => false, 'message' => 'Target host not found'];
    }
    
    // Определяем, кто мы (текущий сервер)
    $currentHostId = 1;
    $isSourceCurrent = ($sourceHostId == $currentHostId);
    $isTargetCurrent = ($targetHostId == $currentHostId);
    
    $results = [
        'download_speed' => null,
        'upload_speed' => null,
        'ping' => null,
        'jitter' => null,
        'packet_loss' => null,
        'test_size_kb' => $testSize,
        'speed_samples' => []
    ];
    
    // Тест задержки (ping) - 5 попыток
    $pingResults = [];
    for ($i = 0; $i < 5; $i++) {
        $start = microtime(true);
        $pingSuccess = testPing($targetHost['hostIp']);
        $end = microtime(true);
        if ($pingSuccess) {
            $pingResults[] = ($end - $start) * 1000;
        }
        usleep(100000);
    }
    
    if (count($pingResults) > 0) {
        $results['ping'] = round(array_sum($pingResults) / count($pingResults), 2);
        $results['jitter'] = count($pingResults) > 1 ? round(calculateJitter($pingResults), 2) : 0;
        $results['packet_loss'] = round((5 - count($pingResults)) / 5 * 100, 1);
    } else {
        $results['packet_loss'] = 100;
    }
    
    // ========== Если packet_loss 100% - хост недоступен ==========
    if ($results['packet_loss'] >= 100) {
        return [
            'success' => false,
            'message' => 'Target host is offline or unreachable. Packet loss: ' . $results['packet_loss'] . '%',
            'results' => $results,
            'from_host' => $sourceHost,
            'to_host' => $targetHost
        ];
    }
    
    // Продолжаем тест скорости только если хост доступен
    if ($isSourceCurrent || $isTargetCurrent) {
        $remoteHost = $isSourceCurrent ? $targetHost : $sourceHost;
        
        // Тест DOWNLOAD
        $downloadSpeed = testDownloadSpeedCorrect($db, $remoteHost, $testSize);
        if ($downloadSpeed) {
            $results['download_speed'] = $downloadSpeed['final'];
            $results['speed_samples'] = array_merge($results['speed_samples'], $downloadSpeed['samples']);
        }
        
        // Тест UPLOAD
        $uploadSpeed = testUploadSpeedCorrect($db, $remoteHost, $testSize);
        if ($uploadSpeed) {
            $results['upload_speed'] = $uploadSpeed['final'];
            $results['speed_samples'] = array_merge($results['speed_samples'], $uploadSpeed['samples']);
        }
        
        if (!empty($results['speed_samples'])) {
            usort($results['speed_samples'], function($a, $b) {
                return $a['time_sec'] <=> $b['time_sec'];
            });
        }
    } else {
        return speedTestProxy($db, $sourceHost, $targetHost, $testSize);
    }
    
    return [
        'success' => true,
        'results' => $results,
        'from_host' => $sourceHost,
        'to_host' => $targetHost
    ];
}

function testUploadSpeedWithSamples($db, $remoteHost, $testSizeKB = 1024) {
    $url = buildApiUrl($remoteHost) . '/speed_test.php';
    
    // Разбиваем данные на чанки для получения промежуточных замеров
    $chunkSizeKB = 64;
    $totalChunks = ceil($testSizeKB / $chunkSizeKB);
    $samples = [];
    $totalDataSent = 0;
    $startTime = microtime(true);
    
    try {
        // Первый запрос для инициализации теста
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'action' => 'init_upload_test',
            'total_size' => $testSizeKB * 1024,
            'chunk_size' => $chunkSizeKB * 1024
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-API-Key: ' . $remoteHost['hostApiKey'],
            'Content-Type: application/x-www-form-urlencoded'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        // Отправляем данные чанками и записываем промежуточные скорости
        for ($chunk = 1; $chunk <= $totalChunks; $chunk++) {
            $chunkStartTime = microtime(true);
            $chunkData = generateTestData($chunkSizeKB);
            
            $chunkCurl = curl_init();
            curl_setopt($chunkCurl, CURLOPT_URL, $url);
            curl_setopt($chunkCurl, CURLOPT_POST, true);
            curl_setopt($chunkCurl, CURLOPT_POSTFIELDS, http_build_query([
                'data' => base64_encode($chunkData),
                'size' => $chunkSizeKB,
                'chunk' => $chunk,
                'total_chunks' => $totalChunks
            ]));
            curl_setopt($chunkCurl, CURLOPT_HTTPHEADER, [
                'X-API-Key: ' . $remoteHost['hostApiKey'],
                'Content-Type: application/x-www-form-urlencoded'
            ]);
            curl_setopt($chunkCurl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($chunkCurl, CURLOPT_TIMEOUT, 30);
            curl_setopt($chunkCurl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($chunkCurl, CURLOPT_SSL_VERIFYHOST, false);
            
            $chunkResponse = curl_exec($chunkCurl);
            $chunkEndTime = microtime(true);
            curl_close($chunkCurl);
            
            $chunkTime = $chunkEndTime - $chunkStartTime;
            $dataSizeBits = ($chunkSizeKB * 1024 * 8);
            $speedMbps = ($dataSizeBits / $chunkTime) / 1000000;
            
            $totalDataSent += $chunkSizeKB * 1024;
            $elapsedTime = $chunkEndTime - $startTime;
            
            // Сохраняем промежуточный замер
            $samples[] = [
                'time_sec' => round($elapsedTime, 2),
                'speed_mbps' => round($speedMbps, 2)
            ];
            
            // Небольшая задержка между чанками для стабильности
            usleep(10000);
        }
        
        // Финальный запрос для завершения теста
        $finalCh = curl_init();
        curl_setopt($finalCh, CURLOPT_URL, $url);
        curl_setopt($finalCh, CURLOPT_POST, true);
        curl_setopt($finalCh, CURLOPT_POSTFIELDS, http_build_query([
            'action' => 'finalize_upload_test'
        ]));
        curl_setopt($finalCh, CURLOPT_HTTPHEADER, [
            'X-API-Key: ' . $remoteHost['hostApiKey'],
            'Content-Type: application/x-www-form-urlencoded'
        ]);
        curl_setopt($finalCh, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($finalCh, CURLOPT_TIMEOUT, 5);
        curl_setopt($finalCh, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($finalCh, CURLOPT_SSL_VERIFYHOST, false);
        curl_exec($finalCh);
        curl_close($finalCh);
        
        $totalTime = microtime(true) - $startTime;
        $totalDataBits = ($testSizeKB * 1024 * 8);
        $averageSpeedMbps = ($totalDataBits / $totalTime) / 1000000;
        
        return [
            'final' => [
                'speed_mbps' => round($averageSpeedMbps, 2),
                'speed_mb_s' => round($averageSpeedMbps / 8, 2),
                'time_seconds' => round($totalTime, 3),
                'data_size_kb' => $testSizeKB
            ],
            'samples' => $samples
        ];
        
    } catch (Exception $e) {
        return null;
    }
}

function testDownloadSpeedCorrect($db, $remoteHost, $testSizeKB = 10240) {
     if (!testPing($remoteHost['hostIp'])) {
        error_log("Host {$remoteHost['hostIp']} is not reachable, skipping download test");
        return null;
    }
	
	$url = buildApiUrl($remoteHost) . '/speed_test.php?action=download&size=' . $testSizeKB;
    $samples = [];
    
    $startTime = microtime(true);
    $startMemory = memory_get_usage();
    
    try {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-API-Key: ' . $remoteHost['hostApiKey']
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        // Включаем прогресс для получения промежуточных замеров
        curl_setopt($ch, CURLOPT_NOPROGRESS, false);
        curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, function($resource, $downloadSize, $downloaded, $uploadSize, $uploaded) use ($startTime, &$samples, $testSizeKB) {
            static $lastProgressTime = 0;
            static $lastDownloaded = 0;
            $now = microtime(true);
            
            // Собираем sample каждые 0.5 секунды или каждые 10% прогресса
            if ($downloaded > 0 && ($now - $lastProgressTime >= 0.5 || ($downloaded - $lastDownloaded) > ($downloadSize / 20))) {
                $elapsed = $now - $startTime;
                if ($elapsed > 0 && $downloaded > 0) {
                    $bitsPerSecond = ($downloaded * 8) / $elapsed;
                    $speedMbps = $bitsPerSecond / 1000000;
                    
                    $samples[] = [
                        'time_sec' => round($elapsed, 2),
                        'download_mbps' => round($speedMbps, 2),
                        'upload_mbps' => 0,
                        'progress_percent' => round(($downloaded / $downloadSize) * 100, 1)
                    ];
                }
                $lastProgressTime = $now;
                $lastDownloaded = $downloaded;
            }
            return 0;
        });
        
        $response = curl_exec($ch);
        $endTime = microtime(true);
        $endMemory = memory_get_usage();
        
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $downloadSize = curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD);
        curl_close($ch);
        
        if ($curlError || $httpCode !== 200 || $downloadSize == 0) {
            error_log("Download test failed: $curlError, HTTP: $httpCode, Size: $downloadSize");
            return null;
        }
        
        // Расчет средней скорости
        $timeTaken = $endTime - $startTime;
        if ($timeTaken <= 0) return null;
        
        $dataSizeBits = ($downloadSize * 8);
        $speedMbps = ($dataSizeBits / $timeTaken) / 1000000;
        
        // Добавляем финальный sample если нужно
        if (empty($samples) || end($samples)['time_sec'] < $timeTaken - 0.1) {
            $samples[] = [
                'time_sec' => round($timeTaken, 2),
                'download_mbps' => round($speedMbps, 2),
                'upload_mbps' => 0,
                'progress_percent' => 100
            ];
        }
        
        return [
            'final' => [
                'speed_mbps' => round($speedMbps, 2),
                'speed_mb_s' => round($speedMbps / 8, 2),
                'time_seconds' => round($timeTaken, 3),
                'data_size_kb' => round($downloadSize / 1024, 2)
            ],
            'samples' => $samples
        ];
        
    } catch (Exception $e) {
        error_log("Download speed test exception: " . $e->getMessage());
        return null;
    }
}


/**
 * Простой тест ping
 */
function testPing($ip) {
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        exec("ping -n 1 -w 2 " . escapeshellarg($ip) . " 2>&1", $output, $return_var);
    } else {
        exec("ping -c 1 -W 2 " . escapeshellarg($ip) . " 2>&1", $output, $return_var);
    }
    return $return_var === 0;
}

/**
 * Рассчет джиттера
 */
function calculateJitter($pingResults) {
    $jitters = [];
    for ($i = 1; $i < count($pingResults); $i++) {
        $jitters[] = abs($pingResults[$i] - $pingResults[$i-1]);
    }
    return array_sum($jitters) / count($jitters);
}

/**
 * Тест скорости загрузки (upload) на удаленный хост
 */
function testUploadSpeedCorrect($db, $remoteHost, $testSizeKB = 10240) {
    // Проверяем доступность хоста
    if (!testPing($remoteHost['hostIp'])) {
        error_log("Host {$remoteHost['hostIp']} is not reachable, skipping upload test");
        return null;
    }
    
    // Ограничиваем максимальный размер для upload
    $maxUploadKB = 5120;
    if ($testSizeKB > $maxUploadKB) {
        $testSizeKB = $maxUploadKB;
        error_log("Upload test size reduced to {$maxUploadKB} KB due to server limits");
    }
    
    $url = buildApiUrl($remoteHost) . '/speed_test.php';
    $samples = [];
    
    // Генерируем тестовые данные чанками для больших файлов
    $chunkSizeKB = 512;
    $totalChunks = ceil($testSizeKB / $chunkSizeKB);
    
    $startTime = microtime(true);
    $totalUploaded = 0;
    
    try {
        for ($chunk = 1; $chunk <= $totalChunks; $chunk++) {
            $chunkStartTime = microtime(true);
            $chunkSize = min($chunkSizeKB, $testSizeKB - ($chunk-1) * $chunkSizeKB);
            $testData = generateTestData($chunkSize);
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
                'data' => base64_encode($testData),
                'size' => $chunkSize,
                'chunk' => $chunk,
                'total_chunks' => $totalChunks
            ]));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'X-API-Key: ' . $remoteHost['hostApiKey'],
                'Content-Type: application/x-www-form-urlencoded'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            
            $response = curl_exec($ch);
            $chunkEndTime = microtime(true);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($curlError || $httpCode !== 200) {
                error_log("Upload chunk $chunk failed: $curlError, HTTP: $httpCode");
                if ($chunk == 1) return null;
                break;
            }
            
            $chunkTime = $chunkEndTime - $chunkStartTime;
            $dataSizeBits = ($chunkSize * 1024 * 8);
            $speedMbps = ($dataSizeBits / $chunkTime) / 1000000;
            
            $totalUploaded += $chunkSize;
            $elapsedTime = $chunkEndTime - $startTime;
            
            $samples[] = [
                'time_sec' => round($elapsedTime, 2),
                'download_mbps' => 0,
                'upload_mbps' => round($speedMbps, 2),
                'progress_percent' => round(($totalUploaded / $testSizeKB) * 100, 1)
            ];
            
            // задержка между чанками
            usleep(50000);
        }
        
        $totalTime = microtime(true) - $startTime;
        $totalDataBits = ($testSizeKB * 1024 * 8);
        $averageSpeedMbps = ($totalDataBits / $totalTime) / 1000000;
        
        if (empty($samples)) {
            return null;
        }
        
        return [
            'final' => [
                'speed_mbps' => round($averageSpeedMbps, 2),
                'speed_mb_s' => round($averageSpeedMbps / 8, 2),
                'time_seconds' => round($totalTime, 3),
                'data_size_kb' => $testSizeKB
            ],
            'samples' => $samples
        ];
        
    } catch (Exception $e) {
        error_log("Upload speed test exception: " . $e->getMessage());
        return null;
    }
}

/**
 * Тест скорости скачивания (download) с удаленного хоста
 */
function testDownloadSpeed($db, $remoteHost, $testSizeKB = 1024) {
    $url = buildApiUrl($remoteHost) . '/speed_test.php?action=download&size=' . $testSizeKB;
    
    $startTime = microtime(true);
    $startMemory = memory_get_usage();
    
    try {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-API-Key: ' . $remoteHost['hostApiKey']
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        $response = curl_exec($ch);
        $endTime = microtime(true);
        $endMemory = memory_get_usage();
        
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $downloadSize = curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD);
        curl_close($ch);
        
        if ($curlError || $httpCode !== 200) {
            return null;
        }
        
        // Расчет скорости в Mbps
        $timeTaken = $endTime - $startTime;
        $dataSizeBits = ($downloadSize * 8);
        $speedMbps = ($dataSizeBits / $timeTaken) / 1000000;
        
        return [
            'speed_mbps' => round($speedMbps, 2),
            'speed_mb_s' => round($speedMbps / 8, 2),
            'time_seconds' => round($timeTaken, 3),
            'data_size_kb' => round($downloadSize / 1024, 2),
            'memory_used_mb' => round(($endMemory - $startMemory) / 1048576, 2)
        ];
        
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Построение URL API для хоста
 */
function buildApiUrl($host) {
    $proto = $host['hostProto'] ?? 'http';
    $ip = $host['hostIp'];
    $port = $host['hostPort'] ? ':' . $host['hostPort'] : '';
    $apiPath = rtrim($host['hostApiPath'] ?? '/api', '/');
    return $proto . '://' . $ip . $port . $apiPath;
}

/**
 * Генерация тестовых данных
 */
function generateTestData($sizeKB) {
    $sizeBytes = $sizeKB * 1024;
    $chunkSize = 8192;
    $data = '';
    
    for ($i = 0; $i < $sizeBytes; $i += $chunkSize) {
        $data .= random_bytes(min($chunkSize, $sizeBytes - $i));
    }
    
    return $data;
}

/**
 * Прокси тест скорости между двумя удаленными хостами
 */
function speedTestProxy($db, $host1, $host2, $testSize = 1024) {
    // Текущий сервер выступает прокси для теста между двумя удаленными хостами
    $results = [
        'host1_to_host2' => null,
        'host2_to_host1' => null,
        'proxy_mode' => true
    ];
    
    // Запрос к host1 для теста скорости до host2
    $url1 = buildApiUrl($host1) . '/host_manager_api.php';
    $postData = [
        'action' => 'proxy_speed_test',
        'target_host_id' => $host2['idHost'],
        'test_size' => $testSize
    ];
    
    $results['host1_to_host2'] = performProxySpeedTest($url1, $host1['hostApiKey'], $postData);
    
    // Запрос к host2 для теста скорости до host1
    $url2 = buildApiUrl($host2) . '/host_manager_api.php';
    $postData = [
        'action' => 'proxy_speed_test',
        'target_host_id' => $host1['idHost'],
        'test_size' => $testSize
    ];
    
    $results['host2_to_host1'] = performProxySpeedTest($url2, $host2['hostApiKey'], $postData);
    
    return [
        'success' => true,
        'results' => $results,
        'from_host' => $host1,
        'to_host' => $host2,
        'proxy_mode' => true
    ];
}

/**
 * Выполнение прокси теста скорости
 */
function performProxySpeedTest($url, $apiKey, $postData) {
    try {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-API-Key: ' . $apiKey,
            'Content-Type: application/x-www-form-urlencoded'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            return json_decode($response, true);
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
    
    return ['success' => false, 'message' => 'Proxy test failed'];
}

/**
 * Проверить статус исходящего запроса
 */
function checkOutgoingRequestStatus($db, $arId) {
    $stmt = $db->prepare("SELECT * FROM agent_request WHERE arId = :id AND arReq = 'outgoing'");
    $stmt->bindValue(':id', $arId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $request = $result->fetchArray(SQLITE3_ASSOC);
    
    if ($request) {
        return ['success' => true, 'exists' => true, 'request' => $request];
    }
    return ['success' => true, 'exists' => false, 'message' => 'Request already processed or does not exist'];
}

function getTrapStatus($db) {
    $stmt = $db->prepare("SELECT setValue FROM settings WHERE setId = 3");
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    
    $enabled = ($row && $row['setValue'] == '1') ? true : false;
    
    return [
        'success' => true,
        'enabled' => $enabled,
        'value' => $enabled ? '1' : '0'
    ];
}

/**
 * Установить статус Trap
 */
function setTrapStatus($db, $enabled) {
    $value = $enabled ? '1' : '0';
    
    $checkStmt = $db->prepare("SELECT setId FROM settings WHERE setId = 3");
    $checkResult = $checkStmt->execute();
    $exists = $checkResult->fetchArray(SQLITE3_ASSOC);
    
    if ($exists) {
        $stmt = $db->prepare("UPDATE settings SET setValue = :value WHERE setId = 3");
    } else {
        $stmt = $db->prepare("INSERT INTO settings (setId, setValue, setCom) VALUES (3, :value, 'Trap enable/disable')");
    }
    
    $stmt->bindValue(':value', $value, SQLITE3_TEXT);
    
    if ($stmt->execute()) {
        return [
            'success' => true,
            'enabled' => $enabled,
            'message' => $enabled ? 'Trap enabled' : 'Trap disabled'
        ];
    }
    
    return ['success' => false, 'message' => 'Failed to update trap status'];
}

// ========== API ROUTER ==========

switch ($action) {
    case 'get_hosts':
        echo json_encode(['success' => true, 'data' => getHosts($db)]);
        break;
        
    case 'get_host':
        $idHost = (int)($_GET['idHost'] ?? 0);
        echo json_encode(['success' => true, 'data' => getHostById($db, $idHost)]);
        break;
        
    case 'save_host':
        echo json_encode(saveHost($db, $_POST));
        break;
        
    case 'delete_host':
        $idHost = (int)($_GET['idHost'] ?? 0);
        echo json_encode(deleteHost($db, $idHost));
        break;
        
    case 'test_host':
        $idHost = (int)($_GET['idHost'] ?? 0);
        echo json_encode(testHost($db, $idHost));
        break;
        
    case 'refresh_status':
        echo json_encode(refreshAllHostsStatus($db));
        break;
        
    case 'get_incoming_requests':
        echo json_encode(['success' => true, 'data' => getIncomingRequests($db)]);
        break;
        
    case 'get_outgoing_requests':
        echo json_encode(['success' => true, 'data' => getOutgoingRequests($db)]);
        break;
        
    case 'join_request':
        $arId = (int)($_GET['arId'] ?? 0);
        echo json_encode(joinRequestToHosts($db, $arId));
        break;
        
    case 'delete_incoming_request':
        $arId = (int)($_GET['arId'] ?? 0);
        echo json_encode(deleteIncomingRequest($db, $arId));
        break;
        
    case 'delete_all_incoming_requests':
        echo json_encode(deleteAllIncomingRequests($db));
        break;
        
    case 'delete_outgoing_request':
        $arId = (int)($_GET['arId'] ?? 0);
        echo json_encode(deleteOutgoingRequest($db, $arId));
        break;
        
    case 'delete_all_outgoing_requests':
        echo json_encode(deleteAllOutgoingRequests($db));
        break;
        
    case 'get_pin':
        echo json_encode(['success' => true, 'pin' => getHostPin($db)]);
        break;
        
    case 'regenerate_pin':
        echo json_encode(regenerateHostPin($db));
        break;
        
    case 'create_outgoing_request':
        echo json_encode(createOutgoingRequest($db, $_GET));
        break;
    
	case 'get_host_api_key':
		echo json_encode(['success' => true, 'api_key' => getHostApiKey($db)]);
		break;
    
	case 'get_local_sn':
		echo json_encode(getLocalHostSn($db));
		break;
	
	case 'save_host_sn':
		$idHost = (int)($_POST['idHost'] ?? 0);
		$sn = $_POST['sn'] ?? '';
		$name = $_POST['name'] ?? null;
		$version = $_POST['version'] ?? null;
		echo json_encode(saveHostSn($db, $idHost, $sn, $name, $version));
		break;
	
	
	case 'create_master':
		$masterData = $_POST['master_data'] ?? [];
		if (empty($masterData)) {
			$input = json_decode(file_get_contents('php://input'), true);
			if ($input && isset($input['master_data'])) {
				$masterData = $input['master_data'];
			}
		}
		echo json_encode(createMasterHost($db, $masterData));
		break;
	
	case 'test_host_api':
		$idHost = (int)($_GET['idHost'] ?? 0);
		if (!$idHost) {
			echo json_encode(['success' => false, 'message' => 'Host ID required']);
			break;
		}
		$host = getHostById($db, $idHost);
		if (!$host) {
			echo json_encode(['success' => false, 'message' => 'Host not found']);
			break;
		}
		echo json_encode(testHostApi($db, $host));
		break;
		
		case 'speed_test':
		$sourceId = (int)($_GET['source_id'] ?? 0);
		$targetId = (int)($_GET['target_id'] ?? 0);
		$testSize = (int)($_GET['test_size'] ?? 1024);
		
		if (!$sourceId || !$targetId) {
			echo json_encode(['success' => false, 'message' => 'Source and Target host IDs are required']);
			break;
		}
		
		echo json_encode(speedTestBetweenHosts($db, $sourceId, $targetId, $testSize));
		break;

	case 'proxy_speed_test':
		$targetHostId = (int)($_POST['target_host_id'] ?? 0);
		$testSize = (int)($_POST['test_size'] ?? 1024);
		
		if (!$targetHostId) {
			echo json_encode(['success' => false, 'message' => 'Target host ID required']);
			break;
		}
		
		$targetHost = getHostById($db, $targetHostId);
		if (!$targetHost) {
			echo json_encode(['success' => false, 'message' => 'Target host not found']);
			break;
		}
		
		$result = testUploadSpeed($db, $targetHost, $testSize);
		if ($result) {
			echo json_encode(['success' => true, 'upload_speed' => $result]);
		} else {
			echo json_encode(['success' => false, 'message' => 'Speed test failed']);
		}
		break;
	
	case 'get_trap_status':
		echo json_encode(getTrapStatus($db));
		break;
		
	case 'set_trap_status':
		$enabled = isset($_POST['enabled']) ? filter_var($_POST['enabled'], FILTER_VALIDATE_BOOLEAN) : false;
		echo json_encode(setTrapStatus($db, $enabled));
		break;
	
	case 'get_host_info':
		$idHost = (int)($_GET['idHost'] ?? 0);
		if (!$idHost) {
			echo json_encode(['success' => false, 'message' => 'Host ID required']);
			break;
		}
		$host = getHostById($db, $idHost);
		if ($host) {
			echo json_encode(['success' => true, 'data' => $host]);
		} else {
			echo json_encode(['success' => false, 'message' => 'Host not found']);
		}
		break;
	
case 'confirm_outgoing_request':
    $arId = (int)($_GET['arId'] ?? 0);
    $action = $_GET['confirm_action'] ?? ''; // 'accept' или 'reject'
    echo json_encode(confirmOutgoingRequest($db, $arId, $action));
    break;
	
    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
}
?>