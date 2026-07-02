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

// ========== ПРОВЕРКА API КЛЮЧА ==========
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
    
    $apiKey = '';
    if (isset($headers['X-API-Key'])) {
        $apiKey = $headers['X-API-Key'];
    } elseif (isset($headers['X-Api-Key'])) {
        $apiKey = $headers['X-Api-Key'];
    } elseif (isset($_SERVER['HTTP_X_API_KEY'])) {
        $apiKey = $_SERVER['HTTP_X_API_KEY'];
    }
    
    if (empty($apiKey)) {
        if (isset($_SESSION['user_id'])) {
            return true;
        }
        http_response_code(401);
        echo json_encode(['error' => 'API key required in X-API-Key header']);
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

require_once '../lang/loader.php';

// ========== ФУНКЦИИ ДЛЯ РАБОТЫ С БАЗОЙ ДАННЫХ ОБНОВЛЕНИЙ ==========

function ensureUpdatesTable() {
    global $db;
    
    if (!$db) {
        try {
            $db = getDB();
        } catch (Exception $e) {
            return false;
        }
    }
    
    $db->exec("CREATE TABLE IF NOT EXISTS \"updates\" (
        \"idUp\" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
        \"lastCheckDate\" TEXT
    )");
    
    $result = $db->query("SELECT COUNT(*) as cnt FROM updates");
    $row = $result->fetchArray(SQLITE3_ASSOC);
    if ($row['cnt'] == 0) {
        $db->exec("INSERT INTO updates (lastCheckDate) VALUES (NULL)");
    }
    
    return true;
}

// Сохранение даты последней проверки
function saveLastCheckDate() {
    global $db;
    
    if (!$db) {
        try {
            $db = getDB();
        } catch (Exception $e) {
            return false;
        }
    }
    
    ensureUpdatesTable();
    
    $now = date('Y-m-d H:i:s');
    $stmt = $db->prepare("UPDATE updates SET lastCheckDate = :date WHERE idUp = 1");
    $stmt->bindValue(':date', $now, SQLITE3_TEXT);
    $stmt->execute();
    
    return $now;
}

// Получение даты последней проверки
function getLastCheckDate() {
    global $db;
    
    if (!$db) {
        try {
            $db = getDB();
        } catch (Exception $e) {
            return null;
        }
    }
    
    ensureUpdatesTable();
    
    $result = $db->query("SELECT lastCheckDate FROM updates WHERE idUp = 1");
    $row = $result->fetchArray(SQLITE3_ASSOC);
    
    return $row ? $row['lastCheckDate'] : null;
}

// ========== ФУНКЦИИ ДЛЯ РАБОТЫ С ОБНОВЛЕНИЯМИ ==========

function getCurrentVersion() {
    $config_path = ROOT_PATH . '/config.php';
    $current_version = 'Unknown';
    $type_pro = 'Unknown';
    
    if (file_exists($config_path)) {
        $config_content = file_get_contents($config_path);
        if (preg_match('/\$version\s*=\s*"([^"]+)"/', $config_content, $matches)) {
            $current_version = $matches[1];
        }
        if (preg_match('/\$type_pro\s*=\s*"([^"]+)"/', $config_content, $matches)) {
            $type_pro = $matches[1];
        }
    }
    
    return [
        'version' => $current_version,
        'type_pro' => $type_pro
    ];
}

// Получение информации о системе
function getSystemInfo() {
    $os_type = php_uname('s');
    $os_version = php_uname('r');
    $php_version = phpversion();
    
    if (file_exists('/etc/os-release')) {
        $os_release = file_get_contents('/etc/os-release');
        if (preg_match('/PRETTY_NAME="([^"]+)"/', $os_release, $matches)) {
            $os_type = $matches[1];
        } elseif (preg_match('/NAME="([^"]+)"/', $os_release, $matches)) {
            $os_type = $matches[1];
        }
    }
    
    return [
        'os_type' => $os_type,
        'os_version' => $os_version,
        'php_version' => $php_version
    ];
}

// Проверка доступности URL
function checkUrlAvailability($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    return [
        'available' => ($http_code > 0 && $http_code < 500) || empty($curl_error),
        'http_code' => $http_code,
        'error' => $curl_error ?: null
    ];
}

// Проверка SSL сертификата
function verifySSL($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    
    $result = curl_exec($ch);
    $ssl_verified = ($result !== false && curl_errno($ch) === 0);
    
    curl_close($ch);
    
    return [
        'verified' => $ssl_verified
    ];
}

// Получение статуса сервера обновлений
function getUpdateServerStatus() {
    $update_server = 'https://update.mini-bucket.ru/minib/update.php';
    
    $availability = checkUrlAvailability($update_server);
    
    $ssl_check = verifySSL($update_server);
    
    return [
        'url' => $update_server,
        'available' => $availability['available'],
        'ssl_verified' => $ssl_check['verified'],
        'http_code' => $availability['http_code'],
        'error' => $availability['error']
    ];
}

// Проверка обновлений на сервере
function checkForUpdates($current_version, $type_pro, $system_info) {
	global $lang4172;
    $update_server = 'https://update.mini-bucket.ru/minib/update.php';
    
    $post_data = [
        'current_version' => $current_version,
        'type_pro' => $type_pro,
        'os_type' => $system_info['os_type'],
        'os_version' => $system_info['os_version'],
        'php_version' => $system_info['php_version'],
        'request' => 'check_update'
    ];
    
    $ch = curl_init($update_server);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($curl_error) {
        return [
            'success' => false,
            'error' => 'CURL error: ' . $curl_error
        ];
    }
    
    if ($http_code !== 200) {
        return [
            'success' => false,
            'error' => 'HTTP error: ' . $http_code
        ];
    }
    
    $data = json_decode($response, true);
    if (!$data) {
        return [
            'success' => false,
            'error' => $lang4172
        ];
    }
    
    return $data;
}

// Загрузка обновления
function downloadUpdate($download_url, $version) {
	global $lang4173, $lang4174, $lang4175, $lang4176, $lang4177;
    $update_dir = '/var/www/minib/updates';
    
    if (!is_dir($update_dir)) {
        mkdir($update_dir, 0755, true);
    }
    
    if (!is_writable($update_dir)) {
        return [
            'success' => false,
            'error' => $lang4173 . $update_dir
        ];
    }
    
	
    $archive_path = $update_dir . "/update_{$version}.zip";
    
    $ch = curl_init($download_url);
    $fp = fopen($archive_path, 'wb');
    
    if (!$fp) {
        return [
            'success' => false,
            'error' => $lang4174 . $archive_path
        ];
    }
    
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 300);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'UpdateClient/1.0');
    
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    $verbose = fopen('php://temp', 'w+');
    curl_setopt($ch, CURLOPT_STDERR, $verbose);
    
    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    
    curl_close($ch);
    fclose($fp);
    
    $log_data = [
        'url' => $download_url,
        'http_code' => $http_code,
        'content_type' => $content_type,
        'error' => $curl_error,
        'file_size' => file_exists($archive_path) ? filesize($archive_path) : 0
    ];
    
    file_put_contents('/var/www/minib/logs/download_log.txt', 
        date('Y-m-d H:i:s') . ' - ' . json_encode($log_data) . "\n", 
        FILE_APPEND);
    
    if ($curl_error) {
        return [
            'success' => false,
            'error' => 'CURL error: ' . $curl_error
        ];
    }
    
    if ($http_code !== 200) {
        return [
            'success' => false,
            'error' => "HTTP error: $http_code"
        ];
    }
    
    if (!file_exists($archive_path) || filesize($archive_path) === 0) {
        return [
            'success' => false,
            'error' => $lang4175
        ];
    }
    
    if (!class_exists('ZipArchive')) {
        return [
            'success' => false,
            'error' => $lang4176
        ];
    }
    
    $zip = new ZipArchive();
    if ($zip->open($archive_path) !== true) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $archive_path);
        finfo_close($finfo);
        
        return [
            'success' => false,
            'error' => $lang4177 . $mime_type
        ];
    }
    $zip->close();
    
    return [
        'success' => true,
        'path' => $archive_path,
        'size' => filesize($archive_path)
    ];
}

// Распаковка ZIP файла
function extractUpdate($archive_path) {
	global $lang4178, $lang4179, $lang4180;
    $update_dir = dirname($archive_path);
    $extract_dir = $update_dir . '/extracted';
    
    if (is_dir($extract_dir)) {
        exec("sudo rm -rf $extract_dir 2>&1");
    }
    exec("sudo mkdir -p $extract_dir 2>&1");
    
    exec("sudo unzip -o $archive_path -d $extract_dir 2>&1", $output, $returnCode);
    
    if ($returnCode !== 0) {
        $zip = new ZipArchive();
        if ($zip->open($archive_path) === TRUE) {
            $zip->extractTo($extract_dir);
            $zip->close();
            $returnCode = 0;
        } else {
            return [
                'success' => false,
                'error' => $lang4178
            ];
        }
    }
    
    if ($returnCode !== 0) {
        return [
            'success' => false,
            'error' => $lang4179 . implode("\n", $output)
        ];
    }
    
    $update_script = findUpdateScript($extract_dir);
    
    if (!$update_script) {
        return [
            'success' => false,
            'error' => $lang4180
        ];
    }
    
    exec("sudo chmod +x $update_script 2>&1");
    
    return [
        'success' => true,
        'extract_dir' => $extract_dir,
        'update_script' => $update_script
    ];
}

function findUpdateScript($dir) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    
    foreach ($iterator as $file) {
        if ($file->getFilename() === 'update_minib.sh') {
            return $file->getPathname();
        }
    }
    
    return null;
}

// Выполнение скрипта обновления
function runUpdateScript($update_script) {
    $command = "sudo bash $update_script 2>&1";
    
    $descriptorspec = [
        0 => ["pipe", "r"],
        1 => ["pipe", "w"],
        2 => ["pipe", "w"]
    ];
    
    $process = proc_open($command, $descriptorspec, $pipes);
    
    if (is_resource($process)) {
        fclose($pipes[0]);
        
        while (!feof($pipes[1])) {
            $output = fgets($pipes[1]);
            if ($output !== false) {
                yield $output;
            }
        }
        
        fclose($pipes[1]);
        fclose($pipes[2]);
        $returnCode = proc_close($process);
        
        return $returnCode === 0;
    }
    
    return false;
}

// ========== API ОБРАБОТЧИК ==========
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_server_status':
        $status = getUpdateServerStatus();
        echo json_encode([
            'success' => true,
            'status' => $status
        ]);
        break;
        
    case 'get_last_check':
        $lastCheck = getLastCheckDate();
        echo json_encode([
            'success' => true,
            'last_check_date' => $lastCheck
        ]);
        break;
        
    case 'check_update':
        $current = getCurrentVersion();
        $system_info = getSystemInfo();
        
        $update_info = checkForUpdates($current['version'], $current['type_pro'], $system_info);
        
        $lastCheckDate = saveLastCheckDate();
        
        if (isset($update_info['success']) && $update_info['success'] === false) {
            echo json_encode($update_info);
        } else {
            echo json_encode([
                'success' => true,
                'current_version' => $current['version'],
                'type_pro' => $current['type_pro'],
                'system_info' => $system_info,
                'last_check_date' => $lastCheckDate,
                'update_available' => isset($update_info['request']) && $update_info['request'] === 'accept',
                'new_version' => $update_info['qurent_version'] ?? null,
                'version_info' => $update_info['version_info'] ?? null,
                'release_date' => $update_info['date_relis'] ?? null,
                'download_url' => $update_info['download_url'] ?? null,
                'update_data' => $update_info
            ]);
        }
        break;
        
    case 'download_update':
		global $lang4181;
        $download_url = $_POST['download_url'] ?? '';
        $version = $_POST['version'] ?? '';
        
        if (empty($download_url)) {
            echo json_encode(['success' => false, 'error' => $lang4181]);
            break;
        }
        
        $result = downloadUpdate($download_url, $version);
        echo json_encode($result);
        break;
        
    case 'extract_update':
		global $lang4182;
        $archive_path = $_POST['archive_path'] ?? '';
        
        if (empty($archive_path) || !file_exists($archive_path)) {
            echo json_encode(['success' => false, 'error' => $lang4182]);
            break;
        }
        
        $result = extractUpdate($archive_path);
        echo json_encode($result);
        break;
    
case 'check_versions':
    $current = getCurrentVersion();
    $system_info = getSystemInfo();
    echo json_encode([
        'success' => true,
        'current_version' => $current['version'],
        'type_pro' => $current['type_pro'],
        'system_info' => [
            'os_type' => $system_info['os_type'],
            'php_version' => $system_info['php_version']
        ]
    ]);
    break;
    
    case 'run_update':
		global $lang4183;
        $update_script = $_POST['update_script'] ?? '';
        
        if (empty($update_script) || !file_exists($update_script)) {
            echo json_encode(['success' => false, 'error' => $lang4183]);
            break;
        }
        
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('X-Accel-Buffering: no');
        
        $output_generator = runUpdateScript($update_script);
        foreach ($output_generator as $line) {
            echo "data: " . json_encode(['output' => $line]) . "\n\n";
            ob_flush();
            flush();
        }
        
        echo "data: " . json_encode(['complete' => true]) . "\n\n";
        break;
        
    case 'cleanup':
        $extract_dir = $_POST['extract_dir'] ?? '';
        
        if (!empty($extract_dir) && is_dir($extract_dir)) {
            exec("sudo rm -rf $extract_dir 2>&1");
        }
        
        echo json_encode(['success' => true]);
        break;
     
case 'run_update_background':
	global $lang4184;
    $update_script = $_POST['update_script'] ?? '';
    $extract_dir = $_POST['extract_dir'] ?? '';
    
    if (!$update_script || !file_exists($update_script)) {
        echo json_encode(['success' => false, 'error' => $lang4184]);
        break;
    }
    
    chmod($update_script, 0755);
    
    $log_file = '/tmp/updatelog.txt';
    $pid_file = '/tmp/update_process.pid';
    
    if (file_exists($log_file)) {
        unlink($log_file);
    }
    
    $cmd = "sudo /bin/bash " . escapeshellarg($update_script) . " > $log_file 2>&1 & echo $! > $pid_file";
    exec($cmd);
    
    echo json_encode([
        'success' => true,
        'log_file' => $log_file,
        'pid_file' => $pid_file
    ]);
    break;

case 'get_update_status':
	global $lang4185;
    $log_file = '/tmp/updatelog.txt';
    $pid_file = '/tmp/update_process.pid';
    
    if (!file_exists($log_file)) {
        echo json_encode([
            'success' => true,
            'output' => $lang4185,
            'complete' => false
        ]);
        break;
    }
    
    $log_content = file_get_contents($log_file);
    $lines = explode("\n", $log_content);
    $last_lines = array_slice($lines, -30);
    
    $is_complete = false;
    
    if (strpos($log_content, 'UPDATE COMPLETED SUCCESSFULLY') !== false ||
        strpos($log_content, 'СКРИПТ УСПЕШНО ВЫПОЛНЕН') !== false) {
        $is_complete = true;
    }
    
    if (file_exists($pid_file)) {
        $pid = trim(file_get_contents($pid_file));
        $process_running = false;
        exec("ps -p $pid 2>&1", $ps_output, $ps_return);
        $process_running = ($ps_return === 0);
        
        if (!$process_running && !$is_complete) {
            $is_complete = true;
        }
    }
    
    echo json_encode([
        'success' => true,
        'output' => implode("\n", $last_lines),
        'complete' => $is_complete,
        'log_file_exists' => file_exists($log_file),
        'pid_exists' => file_exists($pid_file)
    ]);
    break;

case 'get_update_status':
    $log_file = '/tmp/update_output.log';
    $pid_file = '/tmp/update_process.pid';
    
    if (file_exists($log_file)) {
        $output = file_get_contents($log_file);
        $lines = explode("\n", $output);
        $last_lines = array_slice($lines, -20);
        
        $is_complete = !file_exists($pid_file) || !posix_kill(file_get_contents($pid_file), 0);
        
        echo json_encode([
            'success' => true,
            'output' => implode("\n", $last_lines),
            'complete' => $is_complete
        ]);
    } else {
		global $lang4186;
        echo json_encode(['success' => true, 'output' => $lang4186, 'complete' => false]);
    }
    break;
	 
    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
}