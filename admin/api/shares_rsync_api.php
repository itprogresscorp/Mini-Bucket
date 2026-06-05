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

// ========== КОНСТАНТЫ ==========
define('RSYNC_CONF', '/etc/rsyncd.conf');
define('RSYNC_SECRETS', '/etc/rsyncd.secrets');

// ========== ФУНКЦИИ ДЛЯ РАБОТЫ С RSYNC ==========
function getRsyncServiceStatus() {
    $status = [
        'running' => false,
        'enabled' => false,
        'pid' => '',
        'version' => '',
        'port' => '873',
        'modules_count' => 0
    ];
    
    $output = shell_exec('sudo systemctl is-active rsync 2>/dev/null');
    $status['running'] = trim($output) === 'active';
    
    $output = shell_exec('sudo systemctl is-enabled rsync 2>/dev/null');
    $status['enabled'] = trim($output) === 'enabled';
    
    $output = shell_exec('sudo pidof rsync 2>/dev/null');
    $status['pid'] = trim($output);
    
    $output = shell_exec('rsync --version 2>/dev/null | head -1');
    if ($output && preg_match('/version (\d+\.\d+\.\d+)/', $output, $matches)) {
        $status['version'] = $matches[1];
    } else {
        $status['version'] = 'unknown';
    }
    
    $modules = getRsyncModules();
    $status['modules_count'] = count($modules);
    
    return $status;
}

function startRsyncService() {
    shell_exec('sudo systemctl enable rsync 2>/dev/null');
    shell_exec('sudo systemctl start rsync 2>&1');
    sleep(2);
    return getRsyncServiceStatus()['running'];
}

function stopRsyncService() {
    shell_exec('sudo systemctl stop rsync 2>&1');
    sleep(1);
    return !getRsyncServiceStatus()['running'];
}

function restartRsyncService() {
    shell_exec('sudo systemctl restart rsync 2>&1');
    sleep(2);
    return getRsyncServiceStatus()['running'];
}

function enableRsyncService() {
    shell_exec('sudo systemctl enable rsync 2>&1');
    return true;
}

function disableRsyncService() {
    shell_exec('sudo systemctl disable rsync 2>&1');
    return true;
}

// ========== ФУНКЦИИ ДЛЯ РАБОТЫ С КОНФИГУРАЦИЕЙ ==========
function getRsyncGlobalConfig() {
    $config = [
        'uid' => 'nobody',
        'gid' => 'nogroup',
        'use_chroot' => 'yes',
        'max_connections' => '10',
        'timeout' => '300',
        'transfer_logging' => 'yes',
        'log_file' => '/var/log/rsyncd.log'
    ];
    
    if (file_exists(RSYNC_CONF)) {
        $content = file_get_contents(RSYNC_CONF);
        if ($content) {
            $lines = explode("\n", $content);
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line) || $line[0] == '#' || $line[0] == '[') continue;
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

function saveRsyncGlobalConfig($config) {
    $content = "# Rsync Configuration\n";
    $content .= "# Generated by NAS Panel on " . date('Y-m-d H:i:s') . "\n\n";
    
    foreach ($config as $key => $value) {
        if (!empty($value)) {
            $content .= "$key = $value\n";
        }
    }
    
    $content .= "\n";
    
    $modules = getRsyncModules();
    foreach ($modules as $module) {
        $content .= "[{$module['name']}]\n";
        $content .= "path = {$module['path']}\n";
        if (!empty($module['comment'])) {
            $content .= "comment = {$module['comment']}\n";
        }
        $content .= "read only = " . ($module['read_only'] ? 'true' : 'false') . "\n";
        $content .= "list = " . ($module['list'] ? 'true' : 'false') . "\n";
        
        if (!empty($module['auth_users'])) {
            $content .= "auth users = " . implode(',', $module['auth_users']) . "\n";
            $content .= "secrets file = " . RSYNC_SECRETS . "\n";
        }
        
        $content .= "\n";
    }
    
    $tempFile = tempnam(sys_get_temp_dir(), 'rsync');
    file_put_contents($tempFile, $content);
    shell_exec("sudo cp $tempFile " . RSYNC_CONF . " 2>&1");
    shell_exec("sudo chmod 644 " . RSYNC_CONF . " 2>&1");
    unlink($tempFile);
    
    restartRsyncService();
    return true;
}

// ========== ФУНКЦИИ ДЛЯ РАБОТЫ С МОДУЛЯМИ ==========
function getRsyncModules() {
    $modules = [];
    
    if (!file_exists(RSYNC_CONF)) {
        return $modules;
    }
    
    $content = file_get_contents(RSYNC_CONF);
    if (!$content) return $modules;
    
    $lines = explode("\n", $content);
    $currentModule = null;
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || $line[0] == '#') continue;
        
        if (preg_match('/^\[(.*)\]$/', $line, $matches)) {
            if ($currentModule && isset($currentModule['name'])) {
                $modules[] = $currentModule;
            }
            $currentModule = [
                'name' => $matches[1],
                'path' => '',
                'comment' => '',
                'read_only' => true,
                'list' => true,
                'auth_users' => []
            ];
        } 
        elseif ($currentModule && strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            switch ($key) {
                case 'path': $currentModule['path'] = $value; break;
                case 'comment': $currentModule['comment'] = $value; break;
                case 'read only': $currentModule['read_only'] = ($value == 'true' || $value == 'yes'); break;
                case 'list': $currentModule['list'] = ($value == 'true' || $value == 'yes'); break;
                case 'auth users': $currentModule['auth_users'] = array_map('trim', explode(',', $value)); break;
            }
        }
    }
    
    if ($currentModule && isset($currentModule['name'])) {
        $modules[] = $currentModule;
    }
    
    return $modules;
}

function saveRsyncModules($modules) {
    $config = getRsyncGlobalConfig();
    
    $content = "# Rsync Configuration\n";
    $content .= "# Generated by NAS Panel on " . date('Y-m-d H:i:s') . "\n\n";
    
    foreach ($config as $key => $value) {
        if (!empty($value)) {
            $content .= "$key = $value\n";
        }
    }
    
    $content .= "\n";
    
    foreach ($modules as $module) {
        $path = preg_replace('#/+#', '/', $module['path']);
        $content .= "[{$module['name']}]\n";
        $content .= "path = $path\n";
        if (!empty($module['comment'])) {
            $content .= "comment = {$module['comment']}\n";
        }
        $content .= "read only = " . ($module['read_only'] ? 'true' : 'false') . "\n";
        $content .= "list = " . ($module['list'] ? 'true' : 'false') . "\n";
        
        if (!empty($module['auth_users'])) {
            $content .= "auth users = " . implode(',', $module['auth_users']) . "\n";
            $content .= "secrets file = " . RSYNC_SECRETS . "\n";
        }
        
        $content .= "\n";
    }
    
    $tempFile = tempnam(sys_get_temp_dir(), 'rsync');
    file_put_contents($tempFile, $content);
    shell_exec("sudo cp $tempFile " . RSYNC_CONF . " 2>&1");
    shell_exec("sudo chmod 644 " . RSYNC_CONF . " 2>&1");
    unlink($tempFile);
    
    restartRsyncService();
    return true;
}

// ========== ФУНКЦИИ ДЛЯ РАБОТЫ С ПОЛЬЗОВАТЕЛЯМИ ==========
function getRsyncUsers() {
    $users = [];
    if (file_exists(RSYNC_SECRETS)) {
        $content = shell_exec("sudo cat " . RSYNC_SECRETS . " 2>/dev/null");
        if ($content) {
            $lines = explode("\n", trim($content));
            foreach ($lines as $line) {
                $line = trim($line);
                if (!empty($line) && strpos($line, ':') !== false) {
                    list($user, $pass) = explode(':', $line, 2);
                    $users[] = ['username' => $user, 'password' => '********'];
                }
            }
        }
    }
    return $users;
}

function addRsyncUser($username, $password) {
    $users = getRsyncUsers();
    
    foreach ($users as $user) {
        if ($user['username'] === $username) {
            return false;
        }
    }
    
    $tempFile = tempnam(sys_get_temp_dir(), 'rsyncsecrets');
    $newContent = "";
    
    if (file_exists(RSYNC_SECRETS)) {
        $existing = shell_exec("sudo cat " . RSYNC_SECRETS . " 2>/dev/null");
        if ($existing && trim($existing) != '') {
            $newContent = $existing . "\n";
        }
    }
    $newContent .= "$username:$password\n";
    
    file_put_contents($tempFile, $newContent);
    shell_exec("sudo cp $tempFile " . RSYNC_SECRETS . " 2>&1");
    shell_exec("sudo chmod 600 " . RSYNC_SECRETS . " 2>&1");
    shell_exec("sudo chown root:root " . RSYNC_SECRETS . " 2>&1");
    unlink($tempFile);
    
    return true;
}

function updateRsyncUserPassword($username, $password) {
    $users = getRsyncUsers();
    $tempFile = tempnam(sys_get_temp_dir(), 'rsyncsecrets');
    $newContent = "";
    
    foreach ($users as $user) {
        if ($user['username'] === $username) {
            $newContent .= "$username:$password\n";
        } else {
            $existingPass = shell_exec("sudo grep '^{$user['username']}:' " . RSYNC_SECRETS . " | cut -d: -f2 2>/dev/null");
            if ($existingPass && trim($existingPass) != '') {
                $newContent .= $user['username'] . ':' . trim($existingPass) . "\n";
            }
        }
    }
    
    if (trim($newContent) != '') {
        file_put_contents($tempFile, $newContent);
        shell_exec("sudo cp $tempFile " . RSYNC_SECRETS . " 2>&1");
        shell_exec("sudo chmod 600 " . RSYNC_SECRETS . " 2>&1");
        shell_exec("sudo chown root:root " . RSYNC_SECRETS . " 2>&1");
    }
    unlink($tempFile);
    
    return true;
}

function deleteRsyncUser($username) {
    $users = getRsyncUsers();
    $tempFile = tempnam(sys_get_temp_dir(), 'rsyncsecrets');
    $newContent = "";
    
    foreach ($users as $user) {
        if ($user['username'] !== $username) {
            $existingPass = shell_exec("sudo grep '^{$user['username']}:' " . RSYNC_SECRETS . " | cut -d: -f2 2>/dev/null");
            if ($existingPass && trim($existingPass) != '') {
                $newContent .= $user['username'] . ':' . trim($existingPass) . "\n";
            }
        }
    }
    
    if (trim($newContent) != '') {
        file_put_contents($tempFile, $newContent);
        shell_exec("sudo cp $tempFile " . RSYNC_SECRETS . " 2>&1");
        shell_exec("sudo chmod 600 " . RSYNC_SECRETS . " 2>&1");
    } else {
        shell_exec("sudo rm -f " . RSYNC_SECRETS . " 2>&1");
    }
    unlink($tempFile);
    
    $modules = getRsyncModules();
    $updated = false;
    
    foreach ($modules as &$module) {
        if (in_array($username, $module['auth_users'])) {
            $module['auth_users'] = array_values(array_filter($module['auth_users'], function($u) use ($username) {
                return $u !== $username;
            }));
            $updated = true;
        }
    }
    
    if ($updated) {
        saveRsyncModules($modules);
    }
    
    return true;
}

// ========== ФУНКЦИИ ДЛЯ ЛОГОВ ==========
function getRsyncLogs($lines = 50) {
    $logs = [];
    $output = shell_exec("sudo tail -n $lines /var/log/rsyncd.log 2>/dev/null");
    if ($output) {
        $logs = explode("\n", trim($output));
    }
    return array_reverse(array_filter($logs));
}

// ========== ФУНКЦИИ ДЛЯ ФАЙЛОВОЙ СИСТЕМЫ ==========
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
                
                if ($mount == '/boot' || $mount == '/boot/efi') {
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

// ========== API ОБРАБОТЧИК ==========
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_status':
        echo json_encode(['success' => true, 'data' => getRsyncServiceStatus()]);
        break;
        
    case 'service_action':
        $action_type = $_POST['service_action'] ?? '';
        $result = false;
        switch ($action_type) {
            case 'start': $result = startRsyncService(); break;
            case 'stop': $result = stopRsyncService(); break;
            case 'restart': $result = restartRsyncService(); break;
            case 'enable': $result = enableRsyncService(); break;
            case 'disable': $result = disableRsyncService(); break;
        }
        echo json_encode(['success' => $result, 'data' => getRsyncServiceStatus()]);
        break;
        
    case 'get_config':
        echo json_encode(['success' => true, 'data' => getRsyncGlobalConfig()]);
        break;
        
    case 'save_config':
        $config = [
            'uid' => $_POST['uid'] ?? 'nobody',
            'gid' => $_POST['gid'] ?? 'nogroup',
            'use_chroot' => isset($_POST['use_chroot']) ? 'yes' : 'no',
            'max_connections' => $_POST['max_connections'] ?? '10',
            'timeout' => $_POST['timeout'] ?? '300',
            'transfer_logging' => isset($_POST['transfer_logging']) ? 'yes' : 'no',
            'log_file' => '/var/log/rsyncd.log'
        ];
        $result = saveRsyncGlobalConfig($config);
        echo json_encode(['success' => $result, 'message' => $result ? 'Конфигурация сохранена' : 'Ошибка сохранения']);
        break;
        
    case 'get_modules':
        echo json_encode(['success' => true, 'data' => getRsyncModules()]);
        break;
        
    case 'create_module':
        $name = trim($_POST['name'] ?? '');
        $path = trim($_POST['path'] ?? '');
        $comment = trim($_POST['comment'] ?? '');
        $read_only = isset($_POST['read_only']);
        $list = isset($_POST['list']);
        $auth_users = $_POST['users'] ?? [];
        
        if (empty($name)) {
            echo json_encode(['success' => false, 'error' => 'Введите имя модуля']);
        } elseif (empty($path) || !file_exists($path)) {
            echo json_encode(['success' => false, 'error' => 'Укажите существующий путь']);
        } elseif (!preg_match('/^[a-zA-Z0-9_-]+$/', $name)) {
            echo json_encode(['success' => false, 'error' => 'Имя модуля может содержать только латинские буквы, цифры, _ и -']);
        } else {
            $modules = getRsyncModules();
            
            foreach ($modules as $module) {
                if ($module['name'] === $name) {
                    echo json_encode(['success' => false, 'error' => 'Модуль с таким именем уже существует']);
                    exit;
                }
            }
            
            $path = preg_replace('#/+#', '/', $path);
            $modules[] = [
                'name' => $name,
                'path' => $path,
                'comment' => $comment,
                'read_only' => $read_only,
                'list' => $list,
                'auth_users' => $auth_users
            ];
            
            if (saveRsyncModules($modules)) {
                echo json_encode(['success' => true, 'message' => 'Модуль создан']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Ошибка сохранения']);
            }
        }
        break;
        
    case 'update_module':
        $old_name = $_POST['old_name'] ?? '';
        $name = trim($_POST['name'] ?? '');
        $path = trim($_POST['path'] ?? '');
        $comment = trim($_POST['comment'] ?? '');
        $read_only = isset($_POST['read_only']);
        $list = isset($_POST['list']);
        $auth_users = $_POST['users'] ?? [];
        
        if (empty($name) || empty($path) || !file_exists($path)) {
            echo json_encode(['success' => false, 'error' => 'Заполните все поля']);
        } elseif (!preg_match('/^[a-zA-Z0-9_-]+$/', $name)) {
            echo json_encode(['success' => false, 'error' => 'Имя модуля может содержать только латинские буквы, цифры, _ и -']);
        } else {
            $modules = getRsyncModules();
            $updated = false;
            $newModules = [];
            
            foreach ($modules as $module) {
                if ($module['name'] === $old_name) {
                    $module['name'] = $name;
                    $module['path'] = preg_replace('#/+#', '/', $path);
                    $module['comment'] = $comment;
                    $module['read_only'] = $read_only;
                    $module['list'] = $list;
                    $module['auth_users'] = $auth_users;
                    $updated = true;
                }
                $newModules[] = $module;
            }
            
            if ($updated && saveRsyncModules($newModules)) {
                echo json_encode(['success' => true, 'message' => 'Модуль обновлен']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Ошибка обновления']);
            }
        }
        break;
        
    case 'delete_module':
        $name = $_POST['name'] ?? '';
        $modules = getRsyncModules();
        $newModules = [];
        
        foreach ($modules as $module) {
            if ($module['name'] !== $name) {
                $newModules[] = $module;
            }
        }
        
        if (saveRsyncModules($newModules)) {
            echo json_encode(['success' => true, 'message' => 'Модуль удален']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Ошибка удаления']);
        }
        break;
        
    case 'get_users':
        echo json_encode(['success' => true, 'data' => getRsyncUsers()]);
        break;
        
    case 'create_user':
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $password2 = $_POST['password2'] ?? '';
        
        if (empty($username)) {
            echo json_encode(['success' => false, 'error' => 'Введите имя пользователя']);
        } elseif (!preg_match('/^[a-z_][a-z0-9_-]*$/i', $username)) {
            echo json_encode(['success' => false, 'error' => 'Недопустимые символы в имени']);
        } elseif (strlen($password) < 4) {
            echo json_encode(['success' => false, 'error' => 'Пароль должен быть не менее 4 символов']);
        } elseif ($password !== $password2) {
            echo json_encode(['success' => false, 'error' => 'Пароли не совпадают']);
        } else {
            $result = addRsyncUser($username, $password);
            echo json_encode(['success' => $result, 'message' => $result ? 'Пользователь создан' : 'Пользователь уже существует']);
        }
        break;
        
    case 'change_password':
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $password2 = $_POST['password2'] ?? '';
        
        if (strlen($password) < 4) {
            echo json_encode(['success' => false, 'error' => 'Пароль слишком короткий']);
        } elseif ($password !== $password2) {
            echo json_encode(['success' => false, 'error' => 'Пароли не совпадают']);
        } else {
            $result = updateRsyncUserPassword($username, $password);
            echo json_encode(['success' => $result, 'message' => $result ? 'Пароль изменен' : 'Ошибка изменения']);
        }
        break;
        
    case 'delete_user':
        $username = $_POST['username'] ?? '';
        $result = deleteRsyncUser($username);
        echo json_encode(['success' => $result, 'message' => $result ? 'Пользователь удален' : 'Ошибка удаления']);
        break;
        
    case 'get_logs':
        echo json_encode(['success' => true, 'data' => getRsyncLogs(50)]);
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