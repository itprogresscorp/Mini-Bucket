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
 
header('Content-Type: application/json');

define('ROOT_PATH', dirname(dirname(__FILE__)));

if (file_exists(ROOT_PATH . '/config.php')) {
    require_once ROOT_PATH . '/config.php';
}

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-API-Key");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$action = $_GET['action'] ?? '';

$headers = getallheaders();
$apiKey = $headers['X-API-Key'] ?? '';
$db = getDB();

$checkStmt = $db->prepare("SELECT idHost FROM hosts WHERE hostApiKey = :key");
$checkStmt->bindValue(':key', $apiKey, SQLITE3_TEXT);
$result = $checkStmt->execute();

if (!$result->fetchArray()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid API Key']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = $_POST['data'] ?? '';
    $size = $_POST['size'] ?? 0;
    
    if ($data) {
        $decodedData = base64_decode($data);
        echo json_encode([
            'success' => true,
            'received' => strlen($decodedData),
            'expected' => $size * 1024
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No data received']);
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'download') {
    $size = (int)($_GET['size'] ?? 10240);
    $sizeBytes = $size * 1024;
    
    $data = '';
    $chunkSize = 65536;
    for ($i = 0; $i < $sizeBytes; $i += $chunkSize) {
        $data .= random_bytes(min($chunkSize, $sizeBytes - $i));
    }
    
    header('Content-Length: ' . strlen($data));
    header('Content-Type: application/octet-stream');
    echo $data;
    
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request. Use POST for upload or GET with action=download for download test'
    ]);
}
?>