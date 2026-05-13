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

define('VSFTPD_CONF', '/etc/vsftpd.conf');
define('FTP_USERS_FILE', '/etc/vsftpd.userlist');
define('FTP_SHARES_FILE', '/etc/vsftpd_shares.json');

function getFtpServiceStatus() {
    $status = [
        'running' => false,
        'enabled' => false,
        'pid' => '',
        'version' => '',
        'ports' => ['21'],
        'clients_connected' => 0
    ];
    
    $output = shell_exec('sudo systemctl is-active vsftpd 2>/dev/null');
    $status['running'] = trim($output) === 'active';
    
    $output = shell_exec('sudo systemctl is-enabled vsftpd 2>/dev/null');
    $status['enabled'] = trim($output) === 'enabled';
    
    $output = shell_exec('sudo pidof vsftpd 2>/dev/null');
    $status['pid'] = trim($output);
    
    $output = shell_exec('vsftpd -v 2>&1');
    if ($output && preg_match('/version (\d+\.\d+\.\d+)/', $output, $matches)) {
        $status['version'] = $matches[1];
    } else {
        $status['version'] = 'unknown';
    }
    
    $output = shell_exec('sudo ss -tn state established 2>/dev/null | grep :21 | wc -l');
    $status['clients_connected'] = (int)trim($output);
    
    return $status;
}

function startFtpService() {
    shell_exec('sudo systemctl start vsftpd 2>&1');
    sleep(2);
    return getFtpServiceStatus()['running'];
}

function stopFtpService() {
    shell_exec('sudo systemctl stop vsftpd 2>&1');
    sleep(1);
    return !getFtpServiceStatus()['running'];
}

function restartFtpService() {
    shell_exec('sudo systemctl restart vsftpd 2>&1');
    sleep(2);
    return getFtpServiceStatus()['running'];
}

function enableFtpService() {
    shell_exec('sudo systemctl enable vsftpd 2>&1');
    return true;
}

function disableFtpService() {
    shell_exec('sudo systemctl disable vsftpd 2>&1');
    return true;
}

function getFtpConfig() {
    $config = [
        'anonymous_enable' => 'NO',
        'local_enable' => 'YES',
        'write_enable' => 'YES',
        'local_umask' => '022',
        'chroot_local_user' => 'YES',
        'ssl_enable' => 'NO',
        'pasv_enable' => 'YES',
        'pasv_min_port' => '30000',
        'pasv_max_port' => '31000',
        'max_clients' => '100',
        'max_per_ip' => '5',
        'idle_session_timeout' => '300',
        'data_connection_timeout' => '120'
    ];
    
    if (file_exists(VSFTPD_CONF)) {
        $content = file_get_contents(VSFTPD_CONF);
        if ($content) {
            $lines = explode("\n", $content);
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line) || $line[0] == '#') continue;
                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value);
                    if (isset($config[$key])) {
                        $config[$key] = $value;
                    }
                }
            }
        }
    }
    
    return $config;
}

function saveFtpConfig($config) {
    $content = "# FTP Configuration\n";
    $content .= "# Generated by NAS Panel on " . date('Y-m-d H:i:s') . "\n\n";
    
    $content .= "listen=YES\n";
    $content .= "listen_ipv6=NO\n";
    $content .= "dirmessage_enable=YES\n";
    $content .= "xferlog_enable=YES\n";
    $content .= "connect_from_port_20=YES\n";
    $content .= "allow_writeable_chroot=YES\n";
    $content .= "secure_chroot_dir=/var/run/vsftpd/empty\n";
    $content .= "pam_service_name=vsftpd\n";
    $content .= "rsa_cert_file=/etc/ssl/certs/ssl-cert-snakeoil.pem\n";
    $content .= "rsa_private_key_file=/etc/ssl/private/ssl-cert-snakeoil.key\n";
    $content .= "userlist_enable=YES\n";
    $content .= "userlist_deny=NO\n";
    $content .= "userlist_file=" . FTP_USERS_FILE . "\n";
    
    foreach ($config as $key => $value) {
        if (!empty($value) || $value === '0') {
            $value = ($value === 'YES' || $value === 'NO') ? $value : $value;
            $content .= "$key=$value\n";
        }
    }
    
    $tempFile = tempnam(sys_get_temp_dir(), 'ftp');
    file_put_contents($tempFile, $content);
    shell_exec("sudo cp $tempFile " . VSFTPD_CONF . " 2>&1");
    shell_exec("sudo chmod 644 " . VSFTPD_CONF . " 2>&1");
    unlink($tempFile);
    
    restartFtpService();
    return true;
}

function getFtpUsers() {
    $users = [];
    if (file_exists(FTP_USERS_FILE)) {
        $content = file_get_contents(FTP_USERS_FILE);
        if ($content) {
            $lines = explode("\n", trim($content));
            foreach ($lines as $line) {
                $line = trim($line);
                if (!empty($line)) {
                    $users[] = $line;
                }
            }
        }
    }
    return $users;
}

function addFtpUser($username) {
    $users = getFtpUsers();
    if (!in_array($username, $users)) {
        $users[] = $username;
        $tempFile = tempnam(sys_get_temp_dir(), 'ftpusers');
        file_put_contents($tempFile, implode("\n", $users) . "\n");
        shell_exec("sudo cp $tempFile " . FTP_USERS_FILE . " 2>&1");
        shell_exec("sudo chmod 644 " . FTP_USERS_FILE . " 2>&1");
        unlink($tempFile);
        return true;
    }
    return false;
}

function removeFtpUser($username) {
    $users = getFtpUsers();
    $users = array_filter($users, function($u) use ($username) {
        return $u !== $username;
    });
    $tempFile = tempnam(sys_get_temp_dir(), 'ftpusers');
    file_put_contents($tempFile, implode("\n", $users) . "\n");
    shell_exec("sudo cp $tempFile " . FTP_USERS_FILE . " 2>&1");
    unlink($tempFile);
    return true;
}

function getSystemUsers() {
    $users = [];
    $output = shell_exec("getent passwd | grep '/home/' | cut -d: -f1 2>/dev/null");
    if ($output && trim($output) != '') {
        $users = array_filter(explode("\n", trim($output)));
    }
    return array_values($users);
}

function getFtpShares() {
    if (!file_exists(FTP_SHARES_FILE)) {
        return [];
    }
    
    $content = file_get_contents(FTP_SHARES_FILE);
    if (!$content) return [];
    
    $shares = json_decode($content, true);
    return $shares ?: [];
}

function saveFtpShares($shares) {
    $tempFile = tempnam(sys_get_temp_dir(), 'ftpshares');
    file_put_contents($tempFile, json_encode($shares, JSON_PRETTY_PRINT));
    shell_exec("sudo cp $tempFile " . FTP_SHARES_FILE . " 2>&1");
    shell_exec("sudo chmod 644 " . FTP_SHARES_FILE . " 2>&1");
    unlink($tempFile);
    return true;
}

function getFtpLogs($lines = 50) {
    $logs = [];
    $output = shell_exec("sudo tail -n $lines /var/log/vsftpd.log 2>/dev/null");
    if ($output) {
        $logs = explode("\n", trim($output));
    }
    return array_reverse(array_filter($logs));
}

function getDirectoryContents($path) {
    $items = [];
    $path = rtrim($path, '/');
    if ($path === '') $path = '/';
    
    if (!is_dir($path)) return $items;
    
    $dirs = scandir($path);
    foreach ($dirs as $item) {
        if ($item != '.' && $item != '..') {
            $fullPath = rtrim($path, '/') . '/' . $item;
            if (is_dir($fullPath)) {
                $items[] = [
                    'name' => $item,
                    'path' => $fullPath,
                    'type' => 'dir'
                ];
            }
        }
    }
    usort($items, function($a, $b) {
        return strcasecmp($a['name'], $b['name']);
    });
    return $items;
}

function createDirectory($path) {
    if (!file_exists($path)) {
        $oldUmask = umask(0);
        $result = mkdir($path, 0777, true);
        umask($oldUmask);
        return $result;
    }
    return false;
}

function getAllStorages() {
    $storages = [];
    
    $dfOutput = shell_exec("df -h -x tmpfs -x devtmpfs -x squashfs 2>/dev/null | grep '^/dev/'");
    if ($dfOutput) {
        $lines = explode("\n", trim($dfOutput));
        foreach ($lines as $line) {
            if (empty($line)) continue;
            $parts = preg_split('/\s+/', $line);
            if (count($parts) >= 6) {
                $device = $parts[0];
                $size = $parts[1];
                $used = $parts[2];
                $available = $parts[3];
                $usedPercent = (int)rtrim($parts[4], '%');
                $mount = $parts[5];
                
                if ($mount == '/' || $mount == '/boot' || $mount == '/boot/efi') {
                    continue;
                }
                
                $type = 'partition';
                if (strpos($device, 'mapper') !== false || strpos($device, 'dm-') !== false) {
                    $type = 'lvm2';
                } elseif (strpos($device, 'md') !== false) {
                    $type = 'raid';
                }
                
                $storages[] = [
                    'name' => basename($device),
                    'device' => $device,
                    'size_gb' => $size,
                    'used_percent' => $usedPercent,
                    'used' => $used,
                    'available' => $available,
                    'mount' => $mount,
                    'type' => $type
                ];
            }
        }
    }
    
    $unique = [];
    foreach ($storages as $s) {
        $key = $s['mount'];
        if (!isset($unique[$key])) {
            $unique[$key] = $s;
        }
    }
    
    return array_values($unique);
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_status':
        echo json_encode(['success' => true, 'data' => getFtpServiceStatus()]);
        break;
        
    case 'service_action':
        $action_type = $_POST['service_action'] ?? '';
        $result = false;
        switch ($action_type) {
            case 'start': $result = startFtpService(); break;
            case 'stop': $result = stopFtpService(); break;
            case 'restart': $result = restartFtpService(); break;
            case 'enable': $result = enableFtpService(); break;
            case 'disable': $result = disableFtpService(); break;
        }
        echo json_encode(['success' => $result, 'data' => getFtpServiceStatus()]);
        break;
        
    case 'get_config':
        echo json_encode(['success' => true, 'data' => getFtpConfig()]);
        break;
        
    case 'save_config':
        $config = [
            'anonymous_enable' => isset($_POST['anonymous_enable']) ? 'YES' : 'NO',
            'local_enable' => isset($_POST['local_enable']) ? 'YES' : 'NO',
            'write_enable' => isset($_POST['write_enable']) ? 'YES' : 'NO',
            'chroot_local_user' => isset($_POST['chroot_local_user']) ? 'YES' : 'NO',
            'ssl_enable' => isset($_POST['ssl_enable']) ? 'YES' : 'NO',
            'pasv_enable' => isset($_POST['pasv_enable']) ? 'YES' : 'NO',
            'local_umask' => $_POST['local_umask'] ?? '022',
            'pasv_min_port' => $_POST['pasv_min_port'] ?? '30000',
            'pasv_max_port' => $_POST['pasv_max_port'] ?? '31000',
            'max_clients' => $_POST['max_clients'] ?? '100',
            'max_per_ip' => $_POST['max_per_ip'] ?? '5',
            'idle_session_timeout' => $_POST['idle_session_timeout'] ?? '300',
            'data_connection_timeout' => $_POST['data_connection_timeout'] ?? '120'
        ];
        $result = saveFtpConfig($config);
        echo json_encode(['success' => $result, 'message' => $result ? 'Конфигурация сохранена' : 'Ошибка сохранения']);
        break;
        
    case 'get_users':
        echo json_encode(['success' => true, 'data' => getFtpUsers()]);
        break;
        
    case 'get_system_users':
        echo json_encode(['success' => true, 'data' => getSystemUsers()]);
        break;
        
    case 'create_user':
        $username = trim($_POST['username'] ?? '');
        if (empty($username)) {
            echo json_encode(['success' => false, 'error' => 'Выберите пользователя']);
        } else {
            $result = addFtpUser($username);
            echo json_encode(['success' => $result, 'message' => $result ? 'Пользователь добавлен' : 'Пользователь уже существует']);
        }
        break;
        
    case 'delete_user':
        $username = $_POST['username'] ?? '';
        $result = removeFtpUser($username);
        echo json_encode(['success' => $result, 'message' => $result ? 'Пользователь удален' : 'Ошибка удаления']);
        break;
        
    case 'get_shares':
        echo json_encode(['success' => true, 'data' => getFtpShares()]);
        break;
        
    case 'create_share':
        $name = trim($_POST['name'] ?? '');
        $path = trim($_POST['path'] ?? '');
        $users = $_POST['users'] ?? [];
        
        if (empty($name)) {
            echo json_encode(['success' => false, 'error' => 'Введите название каталога']);
        } elseif (empty($path) || !file_exists($path)) {
            echo json_encode(['success' => false, 'error' => 'Укажите существующий путь']);
        } else {
            $shares = getFtpShares();
            $shares[] = [
                'id' => uniqid(),
                'name' => $name,
                'path' => $path,
                'users' => $users,
                'created' => date('Y-m-d H:i:s')
            ];
            if (saveFtpShares($shares)) {
                echo json_encode(['success' => true, 'message' => 'FTP каталог создан']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Ошибка сохранения']);
            }
        }
        break;
        
    case 'update_share':
        $id = $_POST['old_id'] ?? '';
        $name = trim($_POST['name'] ?? '');
        $path = trim($_POST['path'] ?? '');
        $users = $_POST['users'] ?? [];
        
        if (empty($name) || empty($path) || !file_exists($path)) {
            echo json_encode(['success' => false, 'error' => 'Заполните все поля']);
        } else {
            $shares = getFtpShares();
            $updated = false;
            
            foreach ($shares as &$share) {
                if ($share['id'] === $id) {
                    $share['name'] = $name;
                    $share['path'] = $path;
                    $share['users'] = $users;
                    $updated = true;
                    break;
                }
            }
            
            if ($updated && saveFtpShares($shares)) {
                echo json_encode(['success' => true, 'message' => 'FTP каталог обновлен']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Ошибка обновления']);
            }
        }
        break;
        
    case 'delete_share':
        $id = $_POST['id'] ?? '';
        $shares = getFtpShares();
        $shares = array_filter($shares, function($share) use ($id) {
            return $share['id'] !== $id;
        });
        if (saveFtpShares(array_values($shares))) {
            echo json_encode(['success' => true, 'message' => 'FTP каталог удален']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Ошибка удаления']);
        }
        break;
        
    case 'get_logs':
        echo json_encode(['success' => true, 'data' => getFtpLogs(50)]);
        break;
        
    case 'browse':
        $path = $_GET['path'] ?? '/';
        if (!is_dir($path)) {
            $path = '/';
        }
        $directories = getDirectoryContents($path);
        echo json_encode([
            'success' => true,
            'path' => $path,
            'items' => $directories,
            'parent' => dirname($path)
        ]);
        break;
        
    case 'create_folder':
        $path = $_POST['path'] ?? '';
        $name = trim($_POST['name'] ?? '');
        
        if (empty($name)) {
            echo json_encode(['success' => false, 'error' => 'Введите имя папки']);
        } elseif (!preg_match('/^[a-zA-Z0-9_\-\.\s]+$/', $name)) {
            echo json_encode(['success' => false, 'error' => 'Недопустимые символы']);
        } else {
            $fullPath = rtrim($path, '/') . '/' . $name;
            if (createDirectory($fullPath)) {
                echo json_encode(['success' => true, 'message' => 'Папка создана', 'path' => $fullPath]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Не удалось создать папку']);
            }
        }
        break;
        
    case 'get_storages':
        echo json_encode(['success' => true, 'storages' => getAllStorages()]);
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
}
?>