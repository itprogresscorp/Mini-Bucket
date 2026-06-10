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
    define('ROOT_PATH', dirname(dirname(__FILE__)));
}

if (file_exists(ROOT_PATH . '/config.php')) {
    require_once ROOT_PATH . '/config.php';
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-API-Key, Authorization");
header("Access-Control-Allow-Credentials: true");

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

define('PLUGINS_REPOS_DIR', '/var/www/minib/plugins/repositories');
define('PLUGINS_EXTRACT_DIR', '/var/www/minib/plugins/extract');

foreach ([PLUGINS_REPOS_DIR, PLUGINS_EXTRACT_DIR] as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
        exec("chown www-data:www-data " . escapeshellarg($dir));
    }
}

function installPlugin($zipFile, $saveToRepository = true) {
    if (!extension_loaded('zip')) {
        return ['success' => false, 'error' => 'ZIP extension not loaded'];
    }
    
    $zip = new ZipArchive();
    if ($zip->open($zipFile) !== true) {
        return ['success' => false, 'error' => 'Cannot open ZIP archive'];
    }
    
    $sysname = null;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $filename = $zip->getNameIndex($i);
        $basename = basename($filename);
        
        if ($basename === 'info.json') {
            $infoContent = $zip->getFromIndex($i);
            $info = json_decode($infoContent, true);
            if ($info && isset($info['sysname'])) {
                $sysname = $info['sysname'];
            }
            break;
        }
    }
    
    if (!$sysname) {
        $sysname = pathinfo($zipFile, PATHINFO_FILENAME);
    }
    
    $zip->close();
    
    if ($saveToRepository) {
        $repoZipPath = PLUGINS_REPOS_DIR . '/' . $sysname . '.zip';
        
        $counter = 1;
        while (file_exists($repoZipPath)) {
            $repoZipPath = PLUGINS_REPOS_DIR . '/' . $sysname . '_v' . $counter . '.zip';
            $counter++;
        }
        
        if (!copy($zipFile, $repoZipPath)) {
            error_log("Failed to copy ZIP to repository: " . $repoZipPath);
        } else {
            chmod($repoZipPath, 0644);
            exec("chown www-data:www-data " . escapeshellarg($repoZipPath));
            error_log("ZIP saved to repository: " . $repoZipPath);
        }
    }
    
    $extractPath = PLUGINS_EXTRACT_DIR . '/' . $sysname;
    
    if (file_exists($extractPath)) {
        exec("rm -rf " . escapeshellarg($extractPath));
    }
    
    $zip = new ZipArchive();
    if ($zip->open($zipFile) !== true) {
        return ['success' => false, 'error' => 'Cannot open ZIP archive for extraction'];
    }
    
    $zip->extractTo($extractPath);
    $zip->close();
    
    exec("chown -R www-data:www-data " . escapeshellarg($extractPath));
    exec("chmod -R 755 " . escapeshellarg($extractPath));
    
    $installScript = $extractPath . '/install.sh';
    if (file_exists($installScript)) {
        chmod($installScript, 0755);
        exec("cd " . escapeshellarg($extractPath) . " && sudo bash install.sh 2>&1", $output, $returnCode);
        
        if ($returnCode !== 0) {
            return ['success' => false, 'error' => 'Install script failed', 'output' => $output];
        }
    } else {
        return ['success' => false, 'error' => 'install.sh not found'];
    }
    
    exec("rm -rf " . escapeshellarg($extractPath));
    
    return ['success' => true, 'message' => 'Plugin installed', 'sysname' => $sysname];
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'check_plugin':
        $sysname = $_GET['sysname'] ?? '';
        $pluginApiPath = '/var/www/minib/plugins/installed/' . $sysname . '/' . $sysname . '_api.php';
        $installed = file_exists($pluginApiPath);
        
        echo json_encode([
            'success' => true,
            'installed' => $installed,
            'sysname' => $sysname
        ]);
        break;
        
    case 'install_plugin':
        if (!isset($_FILES['plugin_file']) || $_FILES['plugin_file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'error' => 'Plugin file required']);
            break;
        }
        
        $file = $_FILES['plugin_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if ($ext !== 'zip') {
            echo json_encode(['success' => false, 'error' => 'Only ZIP files are allowed']);
            break;
        }
        
        $tempZip = '/tmp/' . basename($file['name']);
        if (!move_uploaded_file($file['tmp_name'], $tempZip)) {
            echo json_encode(['success' => false, 'error' => 'Failed to save ZIP file']);
            break;
        }
        
        chmod($tempZip, 0644);
        $result = installPlugin($tempZip, true);
        unlink($tempZip);
        
        echo json_encode($result);
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
}
?>