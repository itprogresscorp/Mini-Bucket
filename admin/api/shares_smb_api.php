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

require_once '../lang/loader.php';

// ========== КОНСТАНТЫ ==========
define('SMB_CONF_DIR', '/etc/samba/conf.d/');
define('SMB_SHARES_FILE', SMB_CONF_DIR . 'shares.conf');

// ========== ФУНКЦИИ ДЛЯ РАБОТЫ С SMB ==========
function getSmbServiceStatus() {
    $status = [
        'running' => false,
        'enabled' => false,
        'pid' => '',
        'version' => '',
        'ports' => []
    ];
    
    $output = shell_exec('sudo systemctl is-active smbd 2>/dev/null');
    $status['running'] = trim($output) === 'active';
    
    $output = shell_exec('sudo systemctl is-enabled smbd 2>/dev/null');
    $status['enabled'] = trim($output) === 'enabled';
    
    $output = shell_exec('sudo pidof smbd 2>/dev/null');
    $status['pid'] = trim($output);
    
    $output = shell_exec('smbd -V 2>/dev/null');
    if ($output) {
        preg_match('/Version ([0-9.]+)/', $output, $matches);
        $status['version'] = $matches[1] ?? 'unknown';
    }
    
    return $status;
}

function ensureIncludeInSmbConf() {
    $smbConf = '/etc/samba/smb.conf';
    
    if (!file_exists($smbConf)) {
        error_log("SMB config file not found: $smbConf");
        return false;
    }
    
    $backup = $smbConf . '.' . date('Y-m-d_H-i-s');
    if (!copy($smbConf, $backup)) {
        error_log("Failed to create backup: $backup");
        return false;
    }
    error_log("Backup created: $backup");
    
    $content = file_get_contents($smbConf);
    if ($content === false) {
        error_log("Cannot read SMB config file");
        return false;
    }
    
    $lines = explode("\n", $content);
    $newLines = [];
    $inGlobal = false;
    $inHomes = false;
    $includeAdded = false;
    $globalFound = false;
    $removedOldIncludes = false;
    $homesCommented = false;
    $homesFound = false;
    
    foreach ($lines as $line) {
        $trimmedLine = trim($line);
        
        if (preg_match('/^\s*\[global\]\s*$/i', $trimmedLine)) {
            $inGlobal = true;
            $inHomes = false;
            $globalFound = true;
            $newLines[] = $line;
            continue;
        }
        
        if (preg_match('/^\s*\[homes\]\s*$/i', $trimmedLine)) {
            $inHomes = true;
            $inGlobal = false;
            $homesFound = true;
            $newLines[] = "# " . $line;
            $homesCommented = true;
            error_log("Found [homes] section, commenting it out");
            continue;
        }
        
        if ($inHomes) {
            if (empty($trimmedLine) || preg_match('/^\s*\[.*\]\s*$/', $trimmedLine)) {
                $inHomes = false;
                $newLines[] = $line;
            } else {
                $newLines[] = "# " . $line;
                error_log("Commenting homes line: $line");
            }
            continue;
        }
        
        if ($inGlobal && preg_match('/^\s*\[.*\]\s*$/', $trimmedLine) && !preg_match('/^\s*\[global\]\s*$/i', $trimmedLine)) {
            if (!$includeAdded) {
                $newLines[] = "include = /etc/samba/conf.d/shares.conf";
                $includeAdded = true;
            }
            $inGlobal = false;
            $newLines[] = $line;
            continue;
        }
        
        if ($inGlobal && preg_match('/^\s*include\s*=/', $trimmedLine)) {
            if (!$removedOldIncludes) {
                error_log("Removing old include line: $line");
                $removedOldIncludes = true;
            }
            continue;
        }
        
        $newLines[] = $line;
    }
    
    if ($globalFound && !$includeAdded) {
        $newLines[] = "include = /etc/samba/conf.d/shares.conf";
        $includeAdded = true;
        error_log("Added include line at end of global section");
    }
    
    if (!$globalFound) {
        $newContent = "[global]\n";
        $newContent .= "include = /etc/samba/conf.d/shares.conf\n";
        $newContent .= "\n";
        $newContent .= implode("\n", $newLines);
        $newLines = explode("\n", $newContent);
        error_log("Created new [global] section");
    }
    
    if ($homesCommented) {
        $newLines[] = "# [homes] section was automatically commented out by script on " . date('Y-m-d H:i:s');
        $newLines[] = "# To restore, uncomment these lines or restore from backup: $backup";
        error_log("[homes] section has been commented out");
    }
    
    if (!$homesFound) {
        error_log("No [homes] section found in config");
    }
    
    $newContent = implode("\n", $newLines);
    $result = file_put_contents($smbConf, $newContent);
    
    if ($result === false) {
        error_log("Failed to write smb.conf");
        copy($backup, $smbConf);
        error_log("Restored from backup");
        return false;
    }
    
    $test = shell_exec("testparm -s $smbConf 2>&1");
    if (strpos($test, "Loaded services file OK") === false) {
        error_log("Invalid smb.conf after changes! Restoring from backup");
        copy($backup, $smbConf);
        return false;
    }
    
    error_log("smb.conf successfully updated with include line and [homes] commented if found");
    return true;
}

function startSmbService() {
    shell_exec('sudo systemctl start smbd nmbd 2>&1');
    sleep(1);
    return getSmbServiceStatus()['running'];
}

function stopSmbService() {
    shell_exec('sudo systemctl stop smbd nmbd 2>&1');
    sleep(1);
    return !getSmbServiceStatus()['running'];
}

function restartSmbService() {
    shell_exec('sudo systemctl restart smbd nmbd 2>&1');
    sleep(1);
    return getSmbServiceStatus()['running'];
}

function enableSmbService() {
    shell_exec('sudo systemctl enable smbd nmbd 2>&1');
    return true;
}

function disableSmbService() {
    shell_exec('sudo systemctl disable smbd nmbd 2>&1');
    return true;
}

// ========== ФУНКЦИИ ДЛЯ РАБОТЫ С КОНФИГУРАЦИЕЙ ==========
function getSmbGlobalConfig() {
    $config = [
        'workgroup' => 'WORKGROUP',
        'server_string' => 'NAS',
        'security' => 'user',
        'map_to_guest' => 'Bad User',
        'guest_account' => 'nobody',
        'log_level' => '1',
        'max_log_size' => '1000',
        'deadtime' => '15',
        'use_sendfile' => 'yes'
    ];
    
    $smbConf = '/etc/samba/smb.conf';
    if (file_exists($smbConf)) {
        $content = file_get_contents($smbConf);
        if ($content) {
            $lines = explode("\n", $content);
            $inGlobal = false;
            
            foreach ($lines as $line) {
                if (preg_match('/^\s*\[global\]\s*$/i', $line)) {
                    $inGlobal = true;
                    continue;
                }
                if ($inGlobal && preg_match('/^\s*\[.*\]\s*$/', $line) && !preg_match('/^\s*\[global\]\s*$/i', $line)) {
                    $inGlobal = false;
                    continue;
                }
                
                if ($inGlobal) {
                    $line = trim($line);
                    if (empty($line) || $line[0] == '#' || $line[0] == ';') {
                        continue;
                    }
                    
                    if (strpos($line, '=') !== false) {
                        list($key, $value) = explode('=', $line, 2);
                        $key = trim($key);
                        $value = trim($value);
                        
                        switch ($key) {
                            case 'workgroup': $config['workgroup'] = $value; break;
                            case 'server string': $config['server_string'] = $value; break;
                            case 'security': $config['security'] = $value; break;
                            case 'map to guest': $config['map_to_guest'] = $value; break;
                            case 'guest account': $config['guest_account'] = $value; break;
                            case 'log level': $config['log_level'] = $value; break;
                            case 'max log size': $config['max_log_size'] = $value; break;
                            case 'deadtime': $config['deadtime'] = $value; break;
                            case 'use sendfile': $config['use_sendfile'] = $value; break;
                        }
                    }
                }
            }
        }
    }
    
    return $config;
}

function saveSmbGlobalConfig($config) {
    $smbConf = '/etc/samba/smb.conf';
    if (!file_exists($smbConf)) {
        return false;
    }
    
    $content = file_get_contents($smbConf);
    if ($content === false) return false;
    
    $lines = explode("\n", $content);
    $newLines = [];
    $inGlobal = false;
    $globalSectionFound = false;
    $paramsUpdated = [];
    
    foreach ($lines as $line) {
        $trimmedLine = trim($line);
        
        if (preg_match('/^\s*\[global\]\s*$/i', $trimmedLine)) {
            $inGlobal = true;
            $globalSectionFound = true;
            $newLines[] = $line;
            continue;
        }
        
        if ($inGlobal && preg_match('/^\s*\[.*\]\s*$/', $trimmedLine) && !preg_match('/^\s*\[global\]\s*$/i', $trimmedLine)) {
            $inGlobal = false;
            foreach ($config as $key => $value) {
                $paramKey = convertToSmbKey($key);
                if (!isset($paramsUpdated[$paramKey])) {
                    $newLines[] = "$paramKey = $value";
                    $paramsUpdated[$paramKey] = true;
                }
            }
            $newLines[] = $line;
            continue;
        }
        
        if ($inGlobal) {
            if (empty($trimmedLine) || $trimmedLine[0] == '#' || $trimmedLine[0] == ';') {
                $newLines[] = $line;
                continue;
            }
            
            if (strpos($trimmedLine, '=') !== false) {
                list($key, $value) = explode('=', $trimmedLine, 2);
                $key = trim($key);
                
                $smbKey = null;
                foreach ($config as $confKey => $confValue) {
                    if (convertToSmbKey($confKey) === $key) {
                        $smbKey = $confKey;
                        break;
                    }
                }
                
                if ($smbKey !== null) {
                    $newValue = $config[$smbKey];
                    $newLines[] = "$key = $newValue";
                    $paramsUpdated[convertToSmbKey($smbKey)] = true;
                } else {
                    $newLines[] = $line;
                }
            } else {
                $newLines[] = $line;
            }
        } else {
            $newLines[] = $line;
        }
    }
    
    if (!$globalSectionFound) {
        $newContent = "[global]\n";
        foreach ($config as $key => $value) {
            $newContent .= convertToSmbKey($key) . " = $value\n";
        }
        $newContent .= "\n" . implode("\n", $newLines);
    } else {
        $finalLines = [];
        $paramsAdded = false;
        foreach ($newLines as $line) {
            $finalLines[] = $line;
            if (!$paramsAdded && preg_match('/^\s*\[global\]\s*$/i', trim($line))) {
                foreach ($config as $key => $value) {
                    $paramKey = convertToSmbKey($key);
                    if (!isset($paramsUpdated[$paramKey])) {
                        $finalLines[] = "$paramKey = $value";
                    }
                }
                $paramsAdded = true;
            }
        }
        $newContent = implode("\n", $finalLines);
    }
    
    file_put_contents($smbConf, $newContent);
    return true;
}

function convertToSmbKey($key) {
    $map = [
        'workgroup' => 'workgroup',
        'server_string' => 'server string',
        'security' => 'security',
        'map_to_guest' => 'map to guest',
        'guest_account' => 'guest account',
        'log_level' => 'log level',
        'max_log_size' => 'max log size',
        'deadtime' => 'deadtime',
        'use_sendfile' => 'use sendfile'
    ];
    return $map[$key] ?? $key;
}

// ========== ФУНКЦИИ ДЛЯ РАБОТЫ С ПОЛЬЗОВАТЕЛЯМИ ==========
function getSmbUsers() {
    $users = [];
    $output = shell_exec("sudo pdbedit -L 2>/dev/null");
    if ($output && trim($output) != '') {
        $lines = explode("\n", trim($output));
        foreach ($lines as $line) {
            if (preg_match('/^([^:]+):/', $line, $matches)) {
                $users[] = $matches[1];
            }
        }
    }
    return array_values(array_unique($users));
}

function createSmbUser($username, $password) {
    error_log("Creating SMB user: $username");
    
    $userExists = shell_exec("getent passwd $username 2>/dev/null");
    if (empty(trim($userExists))) {
        shell_exec("sudo useradd -m -s /bin/bash $username 2>&1");
        sleep(1);
    }
    
    $tempFile = tempnam(sys_get_temp_dir(), 'smbpass');
    file_put_contents($tempFile, "$password\n$password\n");
    $cmd = "sudo smbpasswd -s -a $username < $tempFile 2>&1";
    $output = shell_exec($cmd);
    error_log("smbpasswd output: " . trim($output));
    unlink($tempFile);
    
    sleep(1);
    
    $check = shell_exec("sudo pdbedit -L 2>/dev/null | grep '^$username:'");
    if (!empty(trim($check))) {
        shell_exec("sudo smbpasswd -e $username 2>&1");
        error_log("User $username successfully created in Samba");
        return true;
    }
    
    error_log("Failed to create SMB user $username");
    return false;
}

function updateSmbUserPassword($username, $password) {
    $tempFile = tempnam(sys_get_temp_dir(), 'smbpass');
    file_put_contents($tempFile, "$password\n$password\n");
    $cmd = "sudo smbpasswd -s $username < $tempFile 2>&1";
    $output = shell_exec($cmd);
    unlink($tempFile);
    return strpos($output, 'successfully') !== false;
}

function deleteSmbUser($username) {
	global $lang2288, $lang2289, $lang2290;
    $shares = getSmbShares();
    $userInShares = false;
    foreach ($shares as $share) {
        if (in_array($username, $share['read_list'] ?? []) || 
            in_array($username, $share['write_list'] ?? [])) {
            $userInShares = true;
            break;
        }
    }
    
    if ($userInShares) {
        return ['success' => false, 'message' => $lang2288];
    }
    
    $output = shell_exec("sudo smbpasswd -x $username 2>&1");
    
    if (strpos($output, 'deleted') !== false || empty($output)) {
        return ['success' => true, 'message' => $lang2289];
    }
    
    return ['success' => false, 'message' => $lang2290];
}

// ========== ФУНКЦИИ ДЛЯ РАБОТЫ С ШАРАМИ ==========
function getSmbShares() {
    ensureIncludeInSmbConf();
    
    $shares = [];
    
    if (!is_dir(SMB_CONF_DIR)) {
        shell_exec("sudo mkdir -p " . SMB_CONF_DIR . " 2>/dev/null");
        shell_exec("sudo chmod 755 " . SMB_CONF_DIR . " 2>/dev/null");
    }
    
    if (!file_exists(SMB_SHARES_FILE)) {
        file_put_contents(SMB_SHARES_FILE, "# SMB Shares Configuration\n");
        shell_exec("sudo chmod 644 " . SMB_SHARES_FILE . " 2>/dev/null");
        return $shares;
    }
    
    $content = file_get_contents(SMB_SHARES_FILE);
    if (!$content) return $shares;
    
    $lines = explode("\n", $content);
    $currentShare = null;
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || $line[0] == '#' || $line[0] == ';') continue;
        
        if (preg_match('/^\[(.*)\]$/', $line, $matches)) {
            if ($currentShare && isset($currentShare['name'])) {
                $shares[] = $currentShare;
            }
            $currentShare = [
                'name' => $matches[1],
                'path' => '',
                'comment' => '',
                'writable' => false,
                'public' => false,
                'valid_users' => [],
                'read_list' => [],
                'write_list' => []
            ];
        } 
        elseif ($currentShare && strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            switch ($key) {
                case 'path': 
                    $currentShare['path'] = preg_replace('#/+#', '/', $value);
                    break;
                case 'comment': 
                    $currentShare['comment'] = $value; 
                    break;
                case 'writable': 
                case 'writeable': 
                    $currentShare['writable'] = ($value == 'yes'); 
                    break;
                case 'public': 
                case 'guest ok': 
                    $currentShare['public'] = ($value == 'yes'); 
                    break;
                case 'valid users': 
                    $currentShare['valid_users'] = array_map('trim', explode(',', $value)); 
                    break;
                case 'read list': 
                    $currentShare['read_list'] = array_map('trim', explode(',', $value)); 
                    break;
                case 'write list': 
                    $currentShare['write_list'] = array_map('trim', explode(',', $value)); 
                    break;
            }
        }
    }
    
    if ($currentShare && isset($currentShare['name'])) {
        $shares[] = $currentShare;
    }
    
    foreach ($shares as &$share) {
        $share['path'] = preg_replace('#/+#', '/', $share['path']);
    }
    
    return $shares;
}

function saveSmbShares($shares) {
    ensureIncludeInSmbConf();
    
    if (!is_dir(SMB_CONF_DIR)) {
        shell_exec("sudo mkdir -p " . SMB_CONF_DIR . " 2>/dev/null");
        shell_exec("sudo chmod 755 " . SMB_CONF_DIR . " 2>/dev/null");
    }
    
    $content = "# SMB Shares Configuration\n";
    $content .= "# Generated by NAS Panel on " . date('Y-m-d H:i:s') . "\n\n";
    
    foreach ($shares as $share) {
        $cleanPath = preg_replace('#/+#', '/', $share['path']);
        
        if (!file_exists($cleanPath)) {
            error_log("Creating directory: $cleanPath");
            $oldUmask = umask(0);
            mkdir($cleanPath, 0777, true);
            umask($oldUmask);
        }
        
        $content .= "\n[{$share['name']}]\n";
        $content .= "path = $cleanPath\n";
        $content .= "browseable = yes\n";
        $content .= "create mask = 0770\n";
        $content .= "directory mask = 0770\n";
        $content .= "force user = www-data\n";
        $content .= "force group = www-data\n";
        $content .= "writable = " . ($share['writable'] ? 'yes' : 'no') . "\n";
        $content .= "public = " . ($share['public'] ? 'yes' : 'no') . "\n";
        $content .= "guest ok = " . ($share['public'] ? 'yes' : 'no') . "\n";
        
        if (!empty($share['comment'])) {
            $content .= "comment = {$share['comment']}\n";
        }
        
        if (!$share['public'] && !empty($share['valid_users'])) {
            $content .= "valid users = " . implode(',', $share['valid_users']) . "\n";
        }
        
        if (!empty($share['read_list'])) {
            $content .= "read list = " . implode(',', $share['read_list']) . "\n";
        }
        
        if (!empty($share['write_list'])) {
            $content .= "write list = " . implode(',', $share['write_list']) . "\n";
        }
    }
    
    file_put_contents(SMB_SHARES_FILE, $content);
    shell_exec("sudo chmod 644 " . SMB_SHARES_FILE . " 2>/dev/null");
    
    $testOutput = shell_exec('sudo testparm -s 2>&1');
    error_log("testparm output: " . $testOutput);
    
    shell_exec('sudo systemctl restart smbd 2>/dev/null');
    shell_exec('sudo systemctl restart nmbd 2>/dev/null');
    sleep(1);
    
    return true;
}

function fixFolderPermissions($path, $users = []) {
    if (!file_exists($path)) {
        $oldUmask = umask(0);
        mkdir($path, 0777, true);
        umask($oldUmask);
    }
    
    if (!is_dir($path)) {
        return false;
    }
    
    @shell_exec("sudo chown -R www-data:www-data \"$path\" 2>/dev/null");
    @shell_exec("sudo chmod 2770 \"$path\" 2>/dev/null");
    @shell_exec("sudo setfacl -b \"$path\" 2>/dev/null");
    
    foreach ($users as $user) {
        if (!empty($user) && shell_exec("id $user 2>/dev/null")) {
            @shell_exec("sudo setfacl -m u:$user:rwx \"$path\" 2>/dev/null");
            @shell_exec("sudo usermod -a -G www-data $user 2>/dev/null");
        }
    }
    
    @shell_exec("sudo setfacl -m u:www-data:rwx \"$path\" 2>/dev/null");
    @shell_exec("sudo setfacl -d -m u::rwx,g::rwx,o::--- \"$path\" 2>/dev/null");
    @shell_exec("sudo setfacl -d -m u:www-data:rwx \"$path\" 2>/dev/null");
    
    foreach ($users as $user) {
        if (!empty($user) && shell_exec("id $user 2>/dev/null")) {
            @shell_exec("sudo setfacl -d -m u:$user:rwx \"$path\" 2>/dev/null");
        }
    }
    
    return true;
}

function setFolderPermissions($path, $public = false, $users = []) {
    if (!file_exists($path)) {
        $oldUmask = umask(0);
        mkdir($path, 0777, true);
        umask($oldUmask);
    }
    
    if ($public) {
        @shell_exec("sudo chmod 2777 \"$path\" 2>/dev/null");
        @shell_exec("sudo chown -R nobody:nogroup \"$path\" 2>/dev/null");
    } else {
        @shell_exec("sudo chmod 2770 \"$path\" 2>/dev/null");
        @shell_exec("sudo chown -R www-data:www-data \"$path\" 2>/dev/null");
        @shell_exec("sudo setfacl -b \"$path\" 2>/dev/null");
        
        foreach ($users as $user) {
            if (!empty($user) && shell_exec("id $user 2>/dev/null")) {
                @shell_exec("sudo setfacl -m u:$user:rwx \"$path\" 2>/dev/null");
                @shell_exec("sudo setfacl -m g:$user:rwx \"$path\" 2>/dev/null");
                @shell_exec("sudo usermod -a -G www-data $user 2>/dev/null");
            }
        }
    }
}

// ========== ФУНКЦИИ ДЛЯ РАБОТЫ С ФАЙЛОВОЙ СИСТЕМОЙ ==========
function getDirectoryContents($path) {
    $items = [];
    
    $path = preg_replace('#/+#', '/', $path);
    
    $basePath = '/';
    $realBasePath = realpath($basePath);
    $realRequestedPath = realpath($path);
    
    if (!$realRequestedPath || strpos($realRequestedPath, $realBasePath) !== 0) {
        $path = $basePath;
        if (!is_dir($basePath)) {
            mkdir($basePath, 0755, true);
        }
    }
    
    if (!is_dir($path)) return $items;
    
    $dirs = scandir($path);
    foreach ($dirs as $item) {
        if ($item != '.' && $item != '..') {
            $fullPath = $path . '/' . $item;
            $fullPath = preg_replace('#/+#', '/', $fullPath);
            
            if (is_dir($fullPath)) {
                $items[] = [
                    'name' => $item,
                    'path' => $fullPath,
                    'type' => 'dir',
                    'writable' => is_writable($fullPath)
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
        
        if ($result) {
            @shell_exec("sudo chown www-data:www-data \"$path\" 2>/dev/null");
            @shell_exec("sudo chmod 2777 \"$path\" 2>/dev/null");
        }
        return $result;
    }
    return false;
}

// ========== ФУНКЦИИ ДЛЯ ПОЛУЧЕНИЯ ВСЕХ ДИСКОВ/РАЗДЕЛОВ ==========
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

// Получение активных SMB сессий
function getSmbSessions() {
    $detailed = getSmbSessionsDetailed();
    $sessions = [];
    
    foreach ($detailed as $session) {
        $sessions[] = [
            'service' => $session['username'] . "'s session",
            'pid' => $session['pid'],
            'ip' => $session['machine'],
            'user' => $session['username'],
            'connected_at' => '',
            'encryption' => $session['encryption'],
            'signing' => $session['signing']
        ];
    }
    
    return $sessions;
}

function getSmbSessionsDetailed() {
    $sessions = [];
    
    $output = shell_exec("sudo smbstatus -v 2>/dev/null");
    
    if (empty($output)) {
        return $sessions;
    }
    
    $lines = explode("\n", $output);
    $inSessions = false;
    $headerPassed = false;
    
    foreach ($lines as $line) {
        $line = trim($line);
        
        if (empty($line)) continue;
        
        if (strpos($line, 'PID') !== false && strpos($line, 'Username') !== false) {
            $inSessions = true;
            $headerPassed = false;
            continue;
        }
        
        if ($inSessions && (strpos($line, '---') !== false || strpos($line, '=') !== false)) {
            $headerPassed = true;
            continue;
        }
        
        if ($inSessions && $headerPassed && preg_match('/^\s*(\d+)/', $line)) {
            $parts = preg_split('/\s+/', $line);
            if (count($parts) >= 5) {
                $session = [
                    'pid' => $parts[0],
                    'username' => $parts[1],
                    'group' => $parts[2],
                    'machine' => $parts[3],
                    'protocol' => $parts[4] ?? '',
                    'encryption' => $parts[5] ?? '',
                    'signing' => $parts[6] ?? ''
                ];
                $sessions[] = $session;
            }
        }
        
        if (strpos($line, 'Locked files:') !== false) {
            break;
        }
    }
    
    return $sessions;
}

function getUserOpenFiles($username) {
    $allFiles = getSmbOpenFiles();
    return array_filter($allFiles, function($file) use ($username) {
        return $file['user'] === $username;
    });
}

function closeSmbFile($pid, $filePath) {
    $cmd = "sudo smbcontrol $pid close-share '$filePath' 2>&1";
    $output = shell_exec($cmd);
    
    if (empty($output)) {
        error_log("File closed: $filePath for PID $pid");
        return true;
    }
    
    error_log("Failed to close file: $output");
    return false;
}

function killSmbSession($pid) {
	global $lang2291, $lang2292;
    $output = shell_exec("sudo kill -9 $pid 2>&1");
    sleep(1);
    
    $check = shell_exec("ps -p $pid 2>/dev/null | grep -v PID");
    if (empty(trim($check))) {
        error_log("Session with PID $pid terminated");
        return ['success' => true, 'message' => $lang2291];
    } else {
        error_log("Failed to terminate session with PID $pid");
        return ['success' => false, 'message' => $lang2292];
    }
}

function killUserSessions($username) {
	global $lang2293, $lang2294, $lang2295;
    $sessions = getSmbSessionsDetailed();
    $killed = [];
    
    foreach ($sessions as $session) {
        if ($session['username'] === $username) {
            $result = killSmbSession($session['pid']);
            if ($result['success']) {
                $killed[] = $session['pid'];
            }
        }
    }
    
    if (count($killed) > 0) {
        return ['success' => true, 'message' => $lang2293 . count($killed), 'pids' => $killed];
    }
    
    return ['success' => false, 'message' => $lang2294 . $username . $lang2295];
}

function closeSmbFileByPath($pid, $fullPath) {
	global $lang2296, $lang2297;
    $cmd = "sudo smbcontrol $pid close-share '$fullPath' 2>&1";
    $output = shell_exec($cmd);
    
    if (empty($output)) {
        error_log("File closed: $fullPath for PID $pid");
        return ['success' => true, 'message' => $lang2296];
    }
    
    error_log("Failed to close file: $output");
    return ['success' => false, 'message' => $lang2297 . $output];
}

function closeAllUserFiles($username) {
	global $lang2298, $lang2299;
    $files = getUserOpenFiles($username);
    $closed = 0;
    
    foreach ($files as $file) {
        $result = closeSmbFileByPath($file['pid'], $file['full_path']);
        if ($result['success']) {
            $closed++;
        }
    }
    
    if ($closed > 0) {
        return ['success' => true, 'message' => $lang2298 . $closed];
    }
    
    return ['success' => false, 'message' => $lang2299 . $username];
}

function getSmbOpenFiles() {
    $files = [];
    
    $sessions = getSmbSessionsDetailed();
    $pidToUser = [];
    foreach ($sessions as $session) {
        $pidToUser[$session['pid']] = $session['username'];
    }
    
    $output = shell_exec("sudo smbstatus -O 2>/dev/null");
    
    if (empty($output)) {
        return $files;
    }
    
    $lines = explode("\n", $output);
    $inLockedFiles = false;
    $headerSkipped = false;
    
    foreach ($lines as $line) {
        $line = rtrim($line);
        
        if (strpos($line, 'Locked files:') !== false) {
            $inLockedFiles = true;
            $headerSkipped = false;
            continue;
        }
        
        if (!$inLockedFiles) continue;
        
        if (strpos($line, 'Pid') !== false && strpos($line, 'Uid') !== false) {
            $headerSkipped = true;
            continue;
        }
        
        if (strpos($line, '---') !== false) {
            $headerSkipped = true;
            continue;
        }
        
        if (empty(trim($line))) continue;
        
        if (!$headerSkipped) continue;
        
        if (preg_match('/^\s*(\d+)\s+(\d+)\s+(\S+)\s+(\S+)\s+(\S+)\s+(\S+)\s+(.+?)\s+(.+?)\s+(.+)$/u', $line, $matches)) {
            $pid = $matches[1];
            $username = $pidToUser[$pid] ?? getUsernameByUID($matches[2]);
            $sharePath = trim($matches[7]);
            $name = trim($matches[8]);
            $time = trim($matches[9]);
            
            if (!preg_match('/\w{3}\s+\w{3}\s+\d{1,2}\s+\d{2}:\d{2}:\d{2}\s+\d{4}/', $time)) {
                $timeParts = explode(' ', $time);
                $name .= ' ' . $timeParts[0];
                $time = implode(' ', array_slice($timeParts, 1));
            }
            
            $files[] = [
                'pid' => $pid,
                'uid' => $matches[2],
                'user' => $username,
                'deny_mode' => $matches[3],
                'access' => $matches[4],
                'rw' => $matches[5],
                'oplock' => $matches[6],
                'share_path' => $sharePath,
                'name' => $name,
                'time' => $time,
                'full_path' => rtrim($sharePath, '/') . '/' . $name
            ];
        }
    }
    
    return $files;
}

function parseSmbStatusLine($line) {
    if (preg_match('/^\s*(\d+)\s+(\d+)\s+(\S+)\s+(\S+)\s+(\S+)\s+(\S+)\s+(.+?)\s+(.+?)\s+(.+)$/u', $line, $matches)) {
        $uid = $matches[2];
        $username = getUsernameByUID($uid);
        
        $sharePath = trim($matches[7]);
        $name = trim($matches[8]);
        $time = trim($matches[9]);
        
        if (!preg_match('/\w{3}\s+\w{3}\s+\d{1,2}\s+\d{2}:\d{2}:\d{2}\s+\d{4}/', $time)) {
            $timeParts = explode(' ', $time);
            $name .= ' ' . $timeParts[0];
            $time = implode(' ', array_slice($timeParts, 1));
        }
        
        return [
            'pid' => $matches[1],
            'uid' => $uid,
            'user' => $username,
            'deny_mode' => $matches[3],
            'access' => $matches[4],
            'rw' => $matches[5],
            'oplock' => $matches[6],
            'share_path' => $sharePath,
            'name' => $name,
            'time' => $time,
            'full_path' => rtrim($sharePath, '/') . '/' . $name
        ];
    }
    
    if (preg_match('/^\s*(\d+)\s+(\d+)\s+(\S+)\s+(\S+)\s+(\S+)\s+(\S+)\s+(\S+)\s+(\S+)\s+(.+)$/', $line, $matches)) {
        $uid = $matches[2];
        $username = getUsernameByUID($uid);
        
        return [
            'pid' => $matches[1],
            'uid' => $uid,
            'user' => $username,
            'deny_mode' => $matches[3],
            'access' => $matches[4],
            'rw' => $matches[5],
            'oplock' => $matches[6],
            'share_path' => $matches[7],
            'name' => $matches[8],
            'time' => $matches[9],
            'full_path' => rtrim($matches[7], '/') . '/' . $matches[8]
        ];
    }
    
    error_log("Failed to parse line: " . $line);
    return null;
}

function getUsernameByUID($uid) {
    static $userCache = [];
    
    if (isset($userCache[$uid])) {
        return $userCache[$uid];
    }
    
    $output = shell_exec("getent passwd $uid 2>/dev/null");
    if ($output && preg_match('/^([^:]+):/', $output, $matches)) {
        $userCache[$uid] = $matches[1];
        return $matches[1];
    }
    
    return "unknown (UID: $uid)";
}

function getUserDetails($username) {
    $details = [
        'username' => $username,
        'uid' => '',
        'gid' => '',
        'home' => '',
        'shell' => '',
        'nt_status' => 'unknown',
        'account_flags' => '',
        'last_login' => ''
    ];
    
    $passwd = shell_exec("getent passwd $username 2>/dev/null");
    if ($passwd) {
        $fields = explode(':', $passwd);
        if (count($fields) >= 7) {
            $details['uid'] = $fields[2];
            $details['gid'] = $fields[3];
            $details['home'] = $fields[5];
            $details['shell'] = $fields[6];
        }
    }
    
    $output = shell_exec("sudo pdbedit -L -v $username 2>/dev/null");
    if ($output) {
        if (strpos($output, 'Account Flags:') !== false) {
            preg_match('/Account Flags:\s*\[(.*?)\]/', $output, $matches);
            $details['account_flags'] = $matches[1] ?? '';
        }
        if (strpos($output, 'Password last set:') !== false) {
            preg_match('/Password last set:\s*(.+)/', $output, $matches);
            $details['last_login'] = trim($matches[1] ?? '');
        }
    }
    
    return $details;
}

// ========== API ОБРАБОТЧИК ==========
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_status':
        echo json_encode(['success' => true, 'data' => getSmbServiceStatus()]);
        break;
        
    case 'service_action':
        $action_type = $_POST['service_action'] ?? '';
        $result = false;
        switch ($action_type) {
            case 'start': $result = startSmbService(); break;
            case 'stop': $result = stopSmbService(); break;
            case 'restart': $result = restartSmbService(); break;
            case 'enable': $result = enableSmbService(); break;
            case 'disable': $result = disableSmbService(); break;
        }
        echo json_encode(['success' => $result, 'data' => getSmbServiceStatus()]);
        break;
        
    case 'get_config':
        echo json_encode(['success' => true, 'data' => getSmbGlobalConfig()]);
        break;
        
    case 'save_config':
		global $lang2300, $lang2301;
        $config = [
            'workgroup' => $_POST['workgroup'] ?? 'WORKGROUP',
            'server_string' => $_POST['server_string'] ?? 'NAS',
            'security' => $_POST['security'] ?? 'user',
            'map_to_guest' => $_POST['map_to_guest'] ?? 'Bad User',
            'guest_account' => $_POST['guest_account'] ?? 'nobody',
            'log_level' => $_POST['log_level'] ?? '1',
            'max_log_size' => $_POST['max_log_size'] ?? '1000',
            'deadtime' => $_POST['deadtime'] ?? '15',
            'use_sendfile' => $_POST['use_sendfile'] ?? 'yes'
        ];
        $result = saveSmbGlobalConfig($config);
        if ($result) {
            restartSmbService();
        }
        echo json_encode(['success' => $result, 'message' => $result ? $lang2300 : $lang2301]);
        break;
        
    case 'get_users':
        echo json_encode(['success' => true, 'data' => getSmbUsers()]);
        break;
        
    case 'create_user':
		global $lang2302, $lang2303, $lang2304, $lang2305, $lang2306, $lang2307;
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $password2 = $_POST['password2'] ?? '';
        
        if (empty($username)) {
            echo json_encode(['success' => false, 'error' => $lang2302]);
        } elseif (!preg_match('/^[a-z_][a-z0-9_-]*$/', $username)) {
            echo json_encode(['success' => false, 'error' => $lang2303]);
        } elseif (strlen($password) < 4) {
            echo json_encode(['success' => false, 'error' => $lang2304]);
        } elseif ($password !== $password2) {
            echo json_encode(['success' => false, 'error' => $lang2305]);
        } else {
            $result = createSmbUser($username, $password);
            echo json_encode(['success' => $result, 'message' => $result ? $lang2306 : $lang2307]);
        }
        break;
        
    case 'change_password':
		global $lang2308, $lang2309, $lang2310, $lang2311;
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $password2 = $_POST['password2'] ?? '';
        
        if (strlen($password) < 4) {
            echo json_encode(['success' => false, 'error' => $lang2308]);
        } elseif ($password !== $password2) {
            echo json_encode(['success' => false, 'error' => $lang2309]);
        } else {
            $result = updateSmbUserPassword($username, $password);
            echo json_encode(['success' => $result, 'message' => $result ? $lang2310 : $lang2311]);
        }
        break;
        
    case 'delete_user':
        $username = $_POST['username'] ?? '';
        $result = deleteSmbUser($username);
        echo json_encode($result);
        break;
        
    case 'get_shares':
        echo json_encode(['success' => true, 'data' => getSmbShares()]);
        break;
        
    case 'create_share':
		global $lang2312, $lang2313, $lang2314, $lang2315, $lang2316;
        $name = trim($_POST['share_name'] ?? '');
        $path = trim($_POST['path'] ?? '');
        $comment = trim($_POST['comment'] ?? '');
        $writable = isset($_POST['writable']);
        $public = isset($_POST['public']);
        $read_list = $_POST['read_list'] ?? [];
        $write_list = $_POST['write_list'] ?? [];
        
        if (empty($name)) {
            echo json_encode(['success' => false, 'error' => $lang2312]);
        } elseif (empty($path) || !file_exists($path)) {
            echo json_encode(['success' => false, 'error' => $lang2313]);
        } else {
            $shares = getSmbShares();
            
            foreach ($shares as $share) {
                if ($share['name'] === $name) {
                    echo json_encode(['success' => false, 'error' => $lang2314]);
                    exit;
                }
            }
            
            $all_users = array_unique(array_merge($read_list, $write_list));
            
            $newShare = [
                'name' => $name,
                'path' => $path,
                'comment' => $comment,
                'writable' => $writable,
                'public' => $public,
                'valid_users' => $public ? [] : $all_users,
                'read_list' => $read_list,
                'write_list' => $write_list
            ];
            
            $shares[] = $newShare;
            if (saveSmbShares($shares)) {
                setFolderPermissions($path, $public, $all_users);
                restartSmbService();
                echo json_encode(['success' => true, 'message' => $lang2315, 'data' => $newShare]);
            } else {
                echo json_encode(['success' => false, 'error' => $lang2316]);
            }
        }
        break;
        
    case 'update_share':
		global $lang2317, $lang2318, $lang2319;
        $old_name = $_POST['old_name'] ?? '';
        $name = trim($_POST['share_name'] ?? '');
        $path = trim($_POST['path'] ?? '');
        $comment = trim($_POST['comment'] ?? '');
        $writable = isset($_POST['writable']);
        $public = isset($_POST['public']);
        $read_list = $_POST['read_list'] ?? [];
        $write_list = $_POST['write_list'] ?? [];
        
        if (empty($name) || empty($path) || !file_exists($path)) {
            echo json_encode(['success' => false, 'error' => $lang2317]);
        } else {
            $shares = getSmbShares();
            $updated = false;
            $all_users = array_unique(array_merge($read_list, $write_list));
            
            foreach ($shares as &$share) {
                if ($share['name'] === $old_name) {
                    $share['name'] = $name;
                    $share['path'] = $path;
                    $share['comment'] = $comment;
                    $share['writable'] = $writable;
                    $share['public'] = $public;
                    $share['valid_users'] = $public ? [] : $all_users;
                    $share['read_list'] = $read_list;
                    $share['write_list'] = $write_list;
                    $updated = true;
                    break;
                }
            }
            
            if ($updated && saveSmbShares($shares)) {
                setFolderPermissions($path, $public, $all_users);
                restartSmbService();
                echo json_encode(['success' => true, 'message' => $lang2318]);
            } else {
                echo json_encode(['success' => false, 'error' => $lang2319]);
            }
        }
        break;
        
    case 'delete_share':
		global $lang2320, $lang2321;
        $name = $_GET['name'] ?? '';
        $shares = getSmbShares();
        $newShares = [];
        
        foreach ($shares as $share) {
            if ($share['name'] !== $name) {
                $newShares[] = $share;
            }
        }
        
        if (saveSmbShares($newShares)) {
            restartSmbService();
            echo json_encode(['success' => true, 'message' => $lang2320]);
        } else {
            echo json_encode(['success' => false, 'error' => $lang2321]);
        }
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
		global $lang2322, $lang2323, $lang2324, $lang2325;
        $path = $_POST['path'] ?? '';
        $name = trim($_POST['name'] ?? '');
        
        if (empty($name)) {
            echo json_encode(['success' => false, 'error' => $lang2322]);
        } elseif (!preg_match('/^[a-zA-Z0-9_\-\.\s]+$/', $name)) {
            echo json_encode(['success' => false, 'error' => $lang2323]);
        } else {
            $fullPath = rtrim($path, '/') . '/' . $name;
            if (createDirectory($fullPath)) {
                echo json_encode(['success' => true, 'message' => $lang2324, 'path' => $fullPath]);
            } else {
                echo json_encode(['success' => false, 'error' => $lang2325]);
            }
        }
        break;
        
    case 'get_sessions':
        echo json_encode(['success' => true, 'sessions' => getSmbSessions(), 'files' => getSmbOpenFiles()]);
        break;
        
    case 'get_storages':
        echo json_encode(['success' => true, 'storages' => getAllStorages()]);
        break;
      
	 case 'diagnose':
		$diagnostic = [];
		
		$smbConf = '/etc/samba/smb.conf';
		$diagnostic['smb_conf_exists'] = file_exists($smbConf);
		if (file_exists($smbConf)) {
			$content = file_get_contents($smbConf);
			$diagnostic['has_include'] = preg_match('/include\s*=\s*\/etc\/samba\/conf\.d\/\*\.conf/', $content) ? 'yes' : 'no';
		}
		
		$diagnostic['conf_d_exists'] = is_dir(SMB_CONF_DIR);
		$diagnostic['shares_conf_exists'] = file_exists(SMB_SHARES_FILE);
		
		$status = getSmbServiceStatus();
		$diagnostic['smbd_running'] = $status['running'] ? 'yes' : 'no';
		
		$shareTest = shell_exec('smbclient -L localhost -N 2>&1');
		$diagnostic['shares_visible'] = strpos($shareTest, 'tst') !== false ? 'yes' : 'no';
		
		$diagnostic['configured_shares'] = getSmbShares();
		
		echo json_encode(['success' => true, 'diagnostic' => $diagnostic]);
		break; 
	  
	 case 'get_user_details':
		global $lang2326;
		$username = $_GET['username'] ?? '';
		if (empty($username)) {
			echo json_encode(['success' => false, 'error' => $lang2326]);
			break;
		}
		echo json_encode(['success' => true, 'data' => getUserDetails($username)]);
		break; 
	  
	  
	 case 'kill_session':
			global $lang2327;
			$pid = $_POST['pid'] ?? '';
			if (empty($pid)) {
				echo json_encode(['success' => false, 'error' => $lang2327]);
				break;
			}
			echo json_encode(killSmbSession($pid));
			break;

		case 'kill_user_sessions':
			global $lang2328;
			$username = $_POST['username'] ?? '';
			if (empty($username)) {
				echo json_encode(['success' => false, 'error' => $lang2328]);
				break;
			}
			echo json_encode(killUserSessions($username));
			break;

		case 'close_file':
			global $lang2329;
			$pid = $_POST['pid'] ?? '';
			$filePath = $_POST['file_path'] ?? '';
			if (empty($pid) || empty($filePath)) {
				echo json_encode(['success' => false, 'error' => $lang2329]);
				break;
			}
			echo json_encode(closeSmbFileByPath($pid, $filePath));
			break;

		case 'close_user_files':
			global $lang2330;
			$username = $_POST['username'] ?? '';
			if (empty($username)) {
				echo json_encode(['success' => false, 'error' => $lang2330]);
				break;
			}
			echo json_encode(closeAllUserFiles($username));
			break;
	
    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
}
?>