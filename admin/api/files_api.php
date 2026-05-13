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

set_time_limit(0);
ignore_user_abort(true);


function sendJsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}


function formatSize($bytes) {
    if ($bytes === 0) return '0 B';
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $i = floor(log($bytes, 1024));
    return round($bytes / pow(1024, $i), 1) . ' ' . $units[$i];
}


function getPermsText($perms) {
    if (($perms & 0xC000) == 0xC000) $info = 's';
    elseif (($perms & 0xA000) == 0xA000) $info = 'l';
    elseif (($perms & 0x8000) == 0x8000) $info = '-';
    elseif (($perms & 0x6000) == 0x6000) $info = 'b';
    elseif (($perms & 0x4000) == 0x4000) $info = 'd';
    elseif (($perms & 0x2000) == 0x2000) $info = 'c';
    elseif (($perms & 0x1000) == 0x1000) $info = 'p';
    else $info = 'u';
    $info .= (($perms & 0x0100) ? 'r' : '-');
    $info .= (($perms & 0x0080) ? 'w' : '-');
    $info .= (($perms & 0x0040) ? (($perms & 0x0800) ? 's' : 'x') : (($perms & 0x0800) ? 'S' : '-'));
    $info .= (($perms & 0x0020) ? 'r' : '-');
    $info .= (($perms & 0x0010) ? 'w' : '-');
    $info .= (($perms & 0x0008) ? (($perms & 0x0400) ? 's' : 'x') : (($perms & 0x0400) ? 'S' : '-'));
    $info .= (($perms & 0x0004) ? 'r' : '-');
    $info .= (($perms & 0x0002) ? 'w' : '-');
    $info .= (($perms & 0x0001) ? (($perms & 0x0200) ? 't' : 'x') : (($perms & 0x0200) ? 'T' : '-'));
    return $info;
}

function fnmatchToRegex($pattern) {
    $pattern = preg_quote($pattern, '/');
    $pattern = str_replace('\*', '.*', $pattern);
    $pattern = str_replace('\?', '.', $pattern);
    return '/^' . $pattern . '$/i';
}

function getSafeFileSize($path) {
    if (!is_file($path)) return 0;
    
    $size = @filesize($path);
    
    if ($size === false || $size < 0) {
        if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
            $size = trim(@shell_exec('stat -c %s ' . escapeshellarg($path)));
            if (is_numeric($size)) {
                return (float)$size;
            }
        }
        
        $fp = @fopen($path, 'rb');
        if ($fp) {
            fseek($fp, 0, SEEK_END);
            $size = ftell($fp);
            fclose($fp);
            return (float)$size;
        }
        
        return 0;
    }
    
    return $size;
}

function getOperationId() {
    return uniqid() . '_' . time() . '_' . rand(1000, 9999);
}


function getOperationProgressFile($operationId) {
    $tmpDir = dirname(__DIR__) . '/tmp';
    if (!is_dir($tmpDir)) mkdir($tmpDir, 0777, true);
    return $tmpDir . '/fm_progress_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $operationId) . '.json';
}


function getOperationLockFile($operationId) {
    $tmpDir = dirname(__DIR__) . '/tmp';
    if (!is_dir($tmpDir)) mkdir($tmpDir, 0777, true);
    return $tmpDir . '/fm_lock_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $operationId) . '.lock';
}


function getOperationLogFile($operationId) {
    $tmpDir = dirname(__DIR__) . '/tmp';
    if (!is_dir($tmpDir)) mkdir($tmpDir, 0777, true);
    return $tmpDir . '/fm_log_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $operationId) . '.txt';
}


function startBackgroundOperation($operation, $sourceDir, $targetDir, $files) {
    $operationId = getOperationId();
    
    $total = 0;
    foreach ($files as $file) {
        $path = $sourceDir . '/' . $file;
        if (!file_exists($path)) continue;
        
        if (is_dir($path)) {
            $output = [];
            exec('find ' . escapeshellarg($path) . ' -type f 2>/dev/null | wc -l', $output);
            $fileCount = isset($output[0]) ? (int)trim($output[0]) : 0;
            $total += $fileCount + 1;
        } else {
            $total++;
        }
    }
    
    $progress = array(
        'id' => $operationId,
        'active' => true,
        'operation' => $operation,
        'source_dir' => $sourceDir,
        'target_dir' => $targetDir,
        'files' => $files,
        'total' => $total,
        'current' => 0,
        'current_file' => '',
        'current_percent' => 0,
        'overall_percent' => 0,
        'cancel' => false,
        'start_time' => time(),
        'last_update' => time(),
        'completed' => false
    );
    
    $progressFile = getOperationProgressFile($operationId);
    file_put_contents($progressFile, json_encode($progress));
    chmod($progressFile, 0666);
    
    $logFile = getOperationLogFile($operationId);
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Operation started: $operation\n");
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Total items: $total\n", FILE_APPEND);
    chmod($logFile, 0666);
    
    $workerScript = dirname(__DIR__) . '/file_worker.php';
    $cmd = 'nohup php ' . escapeshellarg($workerScript) . ' ' . escapeshellarg($operationId) . ' > /dev/null 2>&1 &';
    exec($cmd);
    
    $_SESSION['current_operation_id'] = $operationId;
    
    return $operationId;
}


function cleanupStaleOperations() {
    $tmpDir = dirname(__DIR__) . '/tmp';
    if (!is_dir($tmpDir)) return;
    
    $files = glob($tmpDir . '/fm_progress_*.json');
    $now = time();
    
    foreach ($files as $file) {
        $data = json_decode(file_get_contents($file), true);
        if ($data) {
            if (($data['active'] === true || !isset($data['completed'])) && 
                isset($data['start_time']) && 
                ($now - $data['start_time']) > 3600) {
                $data['active'] = false;
                $data['completed'] = true;
                $data['error'] = 'Operation timed out';
                file_put_contents($file, json_encode($data));
            }
            if (isset($data['completed']) && $data['completed'] === true && 
                isset($data['last_update']) && 
                ($now - $data['last_update']) > 300) {
                @unlink($file);
                $logFile = str_replace('fm_progress_', 'fm_log_', $file);
                @unlink($logFile);
            }
        }
    }
}

cleanupStaleOperations();

function getMountedDrives() {
    $drives = array();
    $dataPath = defined('DATA_PATH') ? DATA_PATH : '/mnt/data';
    
    if (is_dir($dataPath)) {
        $drives[] = array('mount' => $dataPath, 'label' => 'Data');
    }
    
    if (is_dir('/mnt')) {
        $scan = @scandir('/mnt');
        if ($scan) {
            foreach ($scan as $item) {
                if ($item != '.' && $item != '..') {
                    $path = '/mnt/' . $item;
                    if (is_dir($path) && $path != $dataPath) {
                        $drives[] = array('mount' => $path, 'label' => $item);
                    }
                }
            }
        }
    }
    
    if (is_dir('/media')) {
        $scan = @scandir('/media');
        if ($scan) {
            foreach ($scan as $item) {
                if ($item != '.' && $item != '..') {
                    $path = '/media/' . $item;
                    if (is_dir($path)) {
                        $drives[] = array('mount' => $path, 'label' => $item);
                    }
                }
            }
        }
    }
    
    if (empty($drives)) {
        $drives[] = array('mount' => '/', 'label' => 'Root');
    }
    
    return $drives;
}

function searchRecursive($dir, $pattern, $basePath, &$results, $maxDepth = 30, $depth = 0) {
    if ($depth > $maxDepth || !is_dir($dir)) return;
    if (!is_readable($dir)) return;
    
    $items = @scandir($dir);
    if (!$items) return;
    
    $isWildcard = (strpos($pattern, '*') !== false || strpos($pattern, '?') !== false);
    if ($isWildcard) {
        $regex = fnmatchToRegex($pattern);
    }
    
    foreach ($items as $item) {
        if ($item == '.' || $item == '..') continue;
        $path = $dir . '/' . $item;
        
        $matches = false;
        if ($isWildcard) {
            if (preg_match($regex, $item)) {
                $matches = true;
            }
        } else {
            if (stripos($item, $pattern) !== false) {
                $matches = true;
            }
        }
        
        if ($matches) {
            $perms = @fileperms($path);
            $results[] = array(
                'name' => $item,
                'path' => $path,
                'is_dir' => is_dir($path),
                'size' => is_file($path) ? @filesize($path) : 0,
                'perms_text' => $perms ? getPermsText($perms) : '---------',
                'modified' => file_exists($path) ? date('Y-m-d H:i:s', @filemtime($path)) : 'Unknown',
                'rel_path' => str_replace($basePath, '', $path),
                'owner' => (function_exists('posix_getpwuid') && file_exists($path)) ? @posix_getpwuid(@fileowner($path))['name'] : (@fileowner($path) ?: 'unknown'),
                'group' => (function_exists('posix_getgrgid') && file_exists($path)) ? @posix_getgrgid(@filegroup($path))['name'] : (@filegroup($path) ?: 'unknown')
            );
        }
        
        if (is_dir($path) && is_readable($path)) {
            searchRecursive($path, $pattern, $basePath, $results, $maxDepth, $depth + 1);
        }
    }
}

function getDirectoryContents($dir, $basePath, $search = '', $recursive = false) {
    $items = array();
    
    if (!is_dir($dir) || !is_readable($dir)) {
        return $items;
    }
    
    if ($recursive && !empty($search)) {
        searchRecursive($dir, $search, $basePath, $items);
        usort($items, function($a, $b) {
            if ($a['is_dir'] != $b['is_dir']) return $b['is_dir'] - $a['is_dir'];
            return strcasecmp($a['name'], $b['name']);
        });
        return $items;
    }
    
    $files = scandir($dir);
    if (!$files) return $items;
    
    foreach ($files as $f) {
        if ($f == '.' || $f == '..') continue;
        $path = $dir . '/' . $f;
        
        if (!empty($search)) {
            $isWildcard = (strpos($search, '*') !== false || strpos($search, '?') !== false);
            if ($isWildcard) {
                $regex = fnmatchToRegex($search);
                if (!preg_match($regex, $f)) continue;
            } else {
                if (stripos($f, $search) === false) continue;
            }
        }
        
        $perms = @fileperms($path);
        $item = array(
            'name' => $f,
            'path' => $path,
            'is_dir' => is_dir($path),
            'size' => is_file($path) ? getSafeFileSize($path) : 0,
            'perms' => $perms ? substr(sprintf('%o', $perms), -4) : '0000',
            'perms_text' => $perms ? getPermsText($perms) : '---------',
            'owner' => (function_exists('posix_getpwuid') && file_exists($path)) ? @posix_getpwuid(@fileowner($path))['name'] : (@fileowner($path) ?: 'unknown'),
            'group' => (function_exists('posix_getgrgid') && file_exists($path)) ? @posix_getgrgid(@filegroup($path))['name'] : (@filegroup($path) ?: 'unknown'),
            'modified' => file_exists($path) ? date('Y-m-d H:i:s', @filemtime($path)) : 'Unknown',
            'rel_path' => str_replace($basePath, '', $path)
        );
        $items[] = $item;
    }
    
    usort($items, function($a, $b) {
        if ($a['is_dir'] != $b['is_dir']) return $b['is_dir'] - $a['is_dir'];
        return strcasecmp($a['name'], $b['name']);
    });
    
    return $items;
}

function getSystemUsers() {
    $users = array('www-data', 'nobody', 'root', 'daemon', 'davfs2', 'ftpuser');
    
    if (function_exists('posix_getpwnam')) {
        $passwd = @file('/etc/passwd');
        if ($passwd) {
            foreach ($passwd as $line) {
                $parts = explode(':', $line);
                if (isset($parts[0]) && !in_array($parts[0], $users) && $parts[0] != '') {
                    $users[] = $parts[0];
                }
            }
        }
    }
    
    return array_unique($users);
}

function getSystemGroups() {
    $groups = array('www-data', 'nogroup', 'root', 'staff', 'ftpuser');
    
    $groupFile = @file('/etc/group');
    if ($groupFile) {
        foreach ($groupFile as $line) {
            $parts = explode(':', $line);
            if (isset($parts[0]) && !in_array($parts[0], $groups) && $parts[0] != '') {
                $groups[] = $parts[0];
            }
        }
    }
    
    return array_unique($groups);
}

function validatePath($path, $basePath = null) {
    $realPath = realpath($path);
    if ($realPath === false) return false;
    
    if ($basePath !== null) {
        $realBase = realpath($basePath);
        if ($realBase !== false && strpos($realPath, $realBase) !== 0) {
            return false;
        }
    }
    
    return $realPath;
}


$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    switch ($action) {
        case 'get_drives':
            $drives = getMountedDrives();
            sendJsonResponse(['success' => true, 'drives' => $drives]);
            break;
            
        case 'get_system_users':
            $users = getSystemUsers();
            sendJsonResponse(['success' => true, 'users' => $users]);
            break;
            
        case 'get_system_groups':
            $groups = getSystemGroups();
            sendJsonResponse(['success' => true, 'groups' => $groups]);
            break;
            
        case 'list_directory':
            $drive = isset($_GET['drive']) ? $_GET['drive'] : '/mnt/data';
            $dir = isset($_GET['dir']) ? $_GET['dir'] : '';
            $search = isset($_GET['search']) ? $_GET['search'] : '';
            $recursive = isset($_GET['recursive']) && $_GET['recursive'] == '1';
            
            $fullPath = $drive . ($dir ? '/' . ltrim($dir, '/') : '');
            $validPath = validatePath($fullPath, $drive);
            
            if (!$validPath || !is_dir($validPath)) {
                sendJsonResponse(['success' => false, 'error' => 'Invalid directory path']);
                break;
            }
            
            $items = getDirectoryContents($validPath, $drive, $search, $recursive);
            sendJsonResponse(['success' => true, 'items' => $items, 'path' => $validPath]);
            break;
            
        case 'check_progress':
            $operationId = isset($_GET['operation_id']) ? $_GET['operation_id'] : null;
            if (!$operationId) {
                sendJsonResponse(['success' => false, 'error' => 'Operation ID required']);
                break;
            }
            
            $progressFile = getOperationProgressFile($operationId);
            if (file_exists($progressFile)) {
                $data = json_decode(file_get_contents($progressFile), true);
                sendJsonResponse(['success' => true, 'data' => $data]);
            } else {
                sendJsonResponse(['success' => true, 'data' => ['active' => false, 'completed' => true]]);
            }
            break;
            
        case 'get_current_operation':
            $operationId = isset($_SESSION['current_operation_id']) ? $_SESSION['current_operation_id'] : null;
            if ($operationId) {
                $progressFile = getOperationProgressFile($operationId);
                if (file_exists($progressFile)) {
                    $data = json_decode(file_get_contents($progressFile), true);
                    if ($data && $data['active'] === true && !$data['completed']) {
                        sendJsonResponse(['success' => true, 'operation_id' => $operationId]);
                        break;
                    }
                }
            }
            sendJsonResponse(['success' => true, 'operation_id' => null]);
            break;
            
        case 'get_operation_log':
            $operationId = isset($_GET['operation_id']) ? $_GET['operation_id'] : null;
            if (!$operationId) {
                sendJsonResponse(['success' => false, 'error' => 'Operation ID required']);
                break;
            }
            
            $logFile = getOperationLogFile($operationId);
            if (file_exists($logFile)) {
                $log = file_get_contents($logFile);
                sendJsonResponse(['success' => true, 'log' => $log]);
            } else {
                sendJsonResponse(['success' => true, 'log' => 'No log file found']);
            }
            break;
            
        case 'cancel_operation':
            $operationId = isset($_GET['operation_id']) ? $_GET['operation_id'] : null;
            if (!$operationId) {
                sendJsonResponse(['success' => false, 'error' => 'Operation ID required']);
                break;
            }
            
            $progressFile = getOperationProgressFile($operationId);
            if (file_exists($progressFile)) {
                $data = json_decode(file_get_contents($progressFile), true);
                if ($data) {
                    $data['cancel'] = true;
                    file_put_contents($progressFile, json_encode($data));
                    sendJsonResponse(['success' => true]);
                    break;
                }
            }
            sendJsonResponse(['success' => false, 'error' => 'Operation not found']);
            break;
            
        case 'get_clipboard':
            $clipboard = isset($_SESSION['filemanager_clipboard']) ? $_SESSION['filemanager_clipboard'] : null;
            sendJsonResponse(['success' => true, 'clipboard' => $clipboard]);
            break;
            
        case 'download':
            $path = isset($_GET['path']) ? $_GET['path'] : '';
            if (!$path || !file_exists($path) || is_dir($path) || !is_readable($path)) {
                sendJsonResponse(['success' => false, 'error' => 'File not found or not readable']);
                break;
            }
            
            $validPath = validatePath($path);
            if (!$validPath) {
                sendJsonResponse(['success' => false, 'error' => 'Invalid file path']);
                break;
            }
            
            $fileSize = filesize($validPath);
            $fileName = basename($validPath);
            
            while (ob_get_level()) ob_end_clean();
            
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
            header('Content-Length: ' . $fileSize);
            header('Cache-Control: no-cache, must-revalidate');
            header('Expires: 0');
            header('Pragma: public');
            
            readfile($validPath);
            exit;
            break;
            
        case 'download_folder':
            $path = isset($_GET['path']) ? $_GET['path'] : '';
            if (!$path || !file_exists($path) || !is_dir($path) || !is_readable($path)) {
                sendJsonResponse(['success' => false, 'error' => 'Folder not found or not readable']);
                break;
            }
            
            $validPath = validatePath($path);
            if (!$validPath) {
                sendJsonResponse(['success' => false, 'error' => 'Invalid folder path']);
                break;
            }
            
            $folderName = basename($validPath);
            $parentDir = dirname($validPath);
            $tempTar = tempnam(sys_get_temp_dir(), 'folder_') . '.tar';
            
            $cmd = 'tar -cf ' . escapeshellarg($tempTar) . ' -C ' . escapeshellarg($parentDir) . ' ' . escapeshellarg($folderName) . ' 2>&1';
            exec($cmd, $output, $returnCode);
            
            if ($returnCode === 0 && file_exists($tempTar) && filesize($tempTar) > 0) {
                $fileSize = filesize($tempTar);
                
                while (ob_get_level()) ob_end_clean();
                
                header('Content-Description: File Transfer');
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . $folderName . '.tar"');
                header('Content-Length: ' . $fileSize);
                header('Cache-Control: no-cache, must-revalidate');
                header('Expires: 0');
                header('Pragma: public');
                
                readfile($tempTar);
                @unlink($tempTar);
                exit;
            }
            
            @unlink($tempTar);
            sendJsonResponse(['success' => false, 'error' => 'Failed to create archive']);
            break;
            
        default:
            sendJsonResponse(['success' => false, 'error' => 'Unknown action'], 400);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if ($input === null && !empty($_POST)) {
        $input = $_POST;
    }
    
    switch ($action) {
        case 'copy_to_other':
            $sourceDir = isset($input['source_dir']) ? $input['source_dir'] : '';
            $targetDir = isset($input['target_dir']) ? $input['target_dir'] : '';
            $files = isset($input['files']) ? $input['files'] : [];
            
            if (empty($sourceDir) || empty($targetDir) || empty($files)) {
                sendJsonResponse(['success' => false, 'error' => 'Missing parameters']);
                break;
            }
            
            $validFiles = [];
            foreach ($files as $file) {
                if (file_exists($sourceDir . '/' . $file)) {
                    $validFiles[] = $file;
                }
            }
            
            if (empty($validFiles)) {
                sendJsonResponse(['success' => false, 'error' => 'No valid files to copy']);
                break;
            }
            
            $operationId = startBackgroundOperation('copy', $sourceDir, $targetDir, $validFiles);
            sendJsonResponse(['success' => true, 'operation_id' => $operationId, 'message' => 'Copy operation started in background']);
            break;
            
        case 'move_to_other':
            $sourceDir = isset($input['source_dir']) ? $input['source_dir'] : '';
            $targetDir = isset($input['target_dir']) ? $input['target_dir'] : '';
            $files = isset($input['files']) ? $input['files'] : [];
            
            if (empty($sourceDir) || empty($targetDir) || empty($files)) {
                sendJsonResponse(['success' => false, 'error' => 'Missing parameters']);
                break;
            }
            
            $validFiles = [];
            foreach ($files as $file) {
                if (file_exists($sourceDir . '/' . $file)) {
                    $validFiles[] = $file;
                }
            }
            
            if (empty($validFiles)) {
                sendJsonResponse(['success' => false, 'error' => 'No valid files to move']);
                break;
            }
            
            $operationId = startBackgroundOperation('move', $sourceDir, $targetDir, $validFiles);
            sendJsonResponse(['success' => true, 'operation_id' => $operationId, 'message' => 'Move operation started in background']);
            break;
            
        case 'copy_to_clipboard':
            $sourceDir = isset($input['source_dir']) ? $input['source_dir'] : '';
            $files = isset($input['files']) ? $input['files'] : [];
            
            if (empty($sourceDir) || empty($files)) {
                sendJsonResponse(['success' => false, 'error' => 'Missing parameters']);
                break;
            }
            
            $_SESSION['filemanager_clipboard'] = [
                'files' => $files,
                'source_dir' => $sourceDir,
                'operation' => 'copy'
            ];
            
            sendJsonResponse(['success' => true, 'clipboard' => $_SESSION['filemanager_clipboard'], 'message' => count($files) . ' item(s) copied to clipboard']);
            break;
            
        case 'cut_to_clipboard':
            $sourceDir = isset($input['source_dir']) ? $input['source_dir'] : '';
            $files = isset($input['files']) ? $input['files'] : [];
            
            if (empty($sourceDir) || empty($files)) {
                sendJsonResponse(['success' => false, 'error' => 'Missing parameters']);
                break;
            }
            
            $_SESSION['filemanager_clipboard'] = [
                'files' => $files,
                'source_dir' => $sourceDir,
                'operation' => 'cut'
            ];
            
            sendJsonResponse(['success' => true, 'clipboard' => $_SESSION['filemanager_clipboard'], 'message' => count($files) . ' item(s) cut to clipboard']);
            break;
            
        case 'paste_from_clipboard':
            $targetDir = isset($input['target_dir']) ? $input['target_dir'] : '';
            $clipboard = isset($input['clipboard']) ? $input['clipboard'] : null;
            
            if (empty($targetDir) || !$clipboard || empty($clipboard['files'])) {
                sendJsonResponse(['success' => false, 'error' => 'Clipboard is empty or missing parameters']);
                break;
            }
            
            $validFiles = [];
            foreach ($clipboard['files'] as $file) {
                if (file_exists($clipboard['source_dir'] . '/' . $file)) {
                    $validFiles[] = $file;
                }
            }
            
            if (empty($validFiles)) {
                sendJsonResponse(['success' => false, 'error' => 'No valid files to paste']);
                break;
            }
            
            $operation = ($clipboard['operation'] === 'copy') ? 'copy' : 'move';
            $operationId = startBackgroundOperation($operation, $clipboard['source_dir'], $targetDir, $validFiles);
            
            if ($clipboard['operation'] === 'cut') {
                $_SESSION['filemanager_clipboard'] = null;
            }
            
            sendJsonResponse(['success' => true, 'operation_id' => $operationId, 'message' => ucfirst($operation) . ' operation started in background']);
            break;
            
        case 'clear_clipboard':
            $_SESSION['filemanager_clipboard'] = null;
            sendJsonResponse(['success' => true, 'message' => 'Clipboard cleared']);
            break;
            
        case 'delete':
            $targetDir = isset($input['target_dir']) ? $input['target_dir'] : '';
            $files = isset($input['files']) ? $input['files'] : [];
            
            if (empty($targetDir) || empty($files)) {
                sendJsonResponse(['success' => false, 'error' => 'Missing parameters']);
                break;
            }
            
            $deleted = 0;
            foreach ($files as $file) {
                $target = $targetDir . '/' . basename($file);
                if (file_exists($target)) {
                    if (is_dir($target)) {
                        $items = scandir($target);
                        foreach ($items as $item) {
                            if ($item != '.' && $item != '..') {
                                @unlink($target . '/' . $item);
                            }
                        }
                        @rmdir($target);
                    } else {
                        @unlink($target);
                    }
                    $deleted++;
                }
            }
            
            sendJsonResponse(['success' => true, 'message' => "Deleted $deleted item(s)"]);
            break;
            
        case 'mkdir':
            $targetDir = isset($input['target_dir']) ? $input['target_dir'] : '';
            $dirname = isset($input['dirname']) ? trim($input['dirname']) : '';
            
            if (empty($targetDir) || empty($dirname)) {
                sendJsonResponse(['success' => false, 'error' => 'Missing parameters']);
                break;
            }
            
            $newDir = $targetDir . '/' . $dirname;
            if (!file_exists($newDir)) {
                if (@mkdir($newDir, 0755, true)) {
                    sendJsonResponse(['success' => true, 'message' => 'Folder created']);
                } else {
                    sendJsonResponse(['success' => false, 'error' => 'Failed to create folder']);
                }
            } else {
                sendJsonResponse(['success' => false, 'error' => 'Folder already exists']);
            }
            break;
            
        case 'create_archive':
            $targetDir = isset($input['target_dir']) ? $input['target_dir'] : '';
            $files = isset($input['files']) ? $input['files'] : [];
            $archiveName = isset($input['archive_name']) ? $input['archive_name'] : 'archive';
            
            if (empty($targetDir) || empty($files)) {
                sendJsonResponse(['success' => false, 'error' => 'Missing parameters']);
                break;
            }
            
            $archiveName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', trim($archiveName));
            if (empty($archiveName)) $archiveName = 'archive';
            $archivePath = $targetDir . '/' . $archiveName . '.tar';
            
            $validFiles = [];
            foreach ($files as $file) {
                if (file_exists($targetDir . '/' . $file)) {
                    $validFiles[] = $file;
                }
            }
            
            if (empty($validFiles)) {
                sendJsonResponse(['success' => false, 'error' => 'No valid files to archive']);
                break;
            }
            
            $baseDirEsc = escapeshellarg($targetDir);
            $archivePathEsc = escapeshellarg($archivePath);
            $filesList = '';
            foreach ($validFiles as $file) {
                $filesList .= ' ' . escapeshellarg($file);
            }
            $cmd = "cd {$baseDirEsc} && tar -cf {$archivePathEsc}{$filesList} 2>&1";
            exec($cmd, $output, $code);
            
            if ($code === 0) {
                sendJsonResponse(['success' => true, 'message' => 'Archive created: ' . $archiveName . '.tar']);
            } else {
                sendJsonResponse(['success' => false, 'error' => 'Failed to create archive']);
            }
            break;
            
        case 'extract_archive':
            $targetDir = isset($input['target_dir']) ? $input['target_dir'] : '';
            $files = isset($input['files']) ? $input['files'] : [];
            
            if (empty($targetDir) || empty($files)) {
                sendJsonResponse(['success' => false, 'error' => 'Missing parameters']);
                break;
            }
            
            $extracted = 0;
            foreach ($files as $file) {
                $archivePath = $targetDir . '/' . $file;
                if (file_exists($archivePath) && !is_dir($archivePath)) {
                    $extractDir = $targetDir . '/' . pathinfo($file, PATHINFO_FILENAME);
                    if (!is_dir($extractDir)) {
                        mkdir($extractDir, 0755, true);
                    }
                    $archivePathEsc = escapeshellarg($archivePath);
                    $extractDirEsc = escapeshellarg($extractDir);
                    $ext = strtolower(pathinfo($archivePath, PATHINFO_EXTENSION));
                    if ($ext === 'zip') {
                        $cmd = "unzip -o {$archivePathEsc} -d {$extractDirEsc} 2>&1";
                    } else {
                        $cmd = "tar -xf {$archivePathEsc} -C {$extractDirEsc} 2>&1";
                    }
                    exec($cmd, $output, $code);
                    if ($code === 0) $extracted++;
                }
            }
            
            sendJsonResponse(['success' => true, 'message' => "Extracted $extracted archive(s)"]);
            break;
            
        case 'set_permissions':
            $targetDir = isset($input['target_dir']) ? $input['target_dir'] : '';
            $files = isset($input['files']) ? $input['files'] : [];
            $owner = isset($input['owner']) && $input['owner'] ? $input['owner'] : null;
            $group = isset($input['group']) && $input['group'] ? $input['group'] : null;
            $perms = isset($input['permissions']) ? $input['permissions'] : '0755';
            $recursive = isset($input['recursive']) && $input['recursive'];
            
            if (empty($targetDir) || empty($files)) {
                sendJsonResponse(['success' => false, 'error' => 'Missing parameters']);
                break;
            }
            
            $recursiveFlag = $recursive ? '-R ' : '';
            $processed = 0;
            
            foreach ($files as $file) {
                $target = $targetDir . '/' . $file;
                if (file_exists($target)) {
                    $targetEsc = escapeshellarg($target);
                    if ($owner) exec("chown " . escapeshellarg($owner) . " {$targetEsc} 2>&1");
                    if ($group) exec("chgrp " . escapeshellarg($group) . " {$targetEsc} 2>&1");
                    exec("chmod {$recursiveFlag}{$perms} {$targetEsc} 2>&1", $output, $code);
                    if ($code === 0) $processed++;
                }
            }
            
            sendJsonResponse(['success' => true, 'message' => "Permissions updated for $processed item(s)"]);
            break;
            
        case 'set_acl_permissions':
            $targetDir = isset($input['target_dir']) ? $input['target_dir'] : '';
            $files = isset($input['files']) ? $input['files'] : [];
            $userPerms = isset($input['user_perms']) ? $input['user_perms'] : [];
            $groupPerms = isset($input['group_perms']) ? $input['group_perms'] : [];
            $recursive = isset($input['recursive']) && $input['recursive'];
            
            if (empty($targetDir) || empty($files)) {
                sendJsonResponse(['success' => false, 'error' => 'Missing parameters']);
                break;
            }
            
            $processed = 0;
            
            foreach ($files as $file) {
                $target = $targetDir . '/' . $file;
                if (file_exists($target)) {
                    $targetEsc = escapeshellarg($target);
                    exec("setfacl -b {$targetEsc} 2>&1");
                    foreach ($userPerms as $up) {
                        exec("setfacl -m u:" . escapeshellarg($up['user']) . ":" . escapeshellarg($up['perms']) . " {$targetEsc} 2>&1");
                    }
                    foreach ($groupPerms as $gp) {
                        exec("setfacl -m g:" . escapeshellarg($gp['group']) . ":" . escapeshellarg($gp['perms']) . " {$targetEsc} 2>&1");
                    }
                    if ($recursive && is_dir($target)) {
                        foreach ($userPerms as $up) {
                            exec("setfacl -Rm u:" . escapeshellarg($up['user']) . ":" . escapeshellarg($up['perms']) . " {$targetEsc} 2>&1");
                        }
                        foreach ($groupPerms as $gp) {
                            exec("setfacl -Rm g:" . escapeshellarg($gp['group']) . ":" . escapeshellarg($gp['perms']) . " {$targetEsc} 2>&1");
                        }
                    }
                    $processed++;
                }
            }
            
            sendJsonResponse(['success' => true, 'message' => "ACL permissions updated for $processed item(s)"]);
            break;
            
        case 'upload':
            if (!isset($_FILES['files'])) {
                sendJsonResponse(['success' => false, 'error' => 'No files uploaded']);
                break;
            }
            
            $targetDir = isset($_POST['target_dir']) ? $_POST['target_dir'] : '';
            if (empty($targetDir)) {
                sendJsonResponse(['success' => false, 'error' => 'Target directory required']);
                break;
            }
            
            $uploaded = 0;
            $errors = 0;
            $files = $_FILES['files'];
            
            if (isset($files['name']) && is_array($files['name'])) {
                for ($i = 0; $i < count($files['name']); $i++) {
                    if ($files['error'][$i] === UPLOAD_ERR_OK) {
                        $target = $targetDir . '/' . basename($files['name'][$i]);
                        if (move_uploaded_file($files['tmp_name'][$i], $target)) {
                            $uploaded++;
                        } else {
                            $errors++;
                        }
                    } else {
                        $errors++;
                    }
                }
            } else {
                if ($files['error'] === UPLOAD_ERR_OK) {
                    $target = $targetDir . '/' . basename($files['name']);
                    if (move_uploaded_file($files['tmp_name'], $target)) {
                        $uploaded++;
                    } else {
                        $errors++;
                    }
                } else {
                    $errors++;
                }
            }
            
            sendJsonResponse(['success' => true, 'message' => "Uploaded $uploaded file(s)" . ($errors ? " $errors failed" : "")]);
            break;
            
        default:
            sendJsonResponse(['success' => false, 'error' => 'Unknown action'], 400);
    }
}

sendJsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
?>