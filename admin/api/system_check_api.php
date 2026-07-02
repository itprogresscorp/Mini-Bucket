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

// Настройки логов
define('LOG_FILE', '/var/log/install_script.log');
define('ERROR_LOG', '/var/log/install_script_error.log');
define('SYSTEM_CHECK_LOG', '/var/log/system_check.log');

// Функция логирования
function logMessage($message, $type = 'INFO') {
    $logEntry = "[" . date('Y-m-d H:i:s') . "] [$type] $message\n";
    file_put_contents(SYSTEM_CHECK_LOG, $logEntry, FILE_APPEND);
    return $logEntry;
}

// Функция выполнения команды
function runCommand($command, &$output = null) {
    $output = [];
    $returnVar = 0;
    exec("sudo " . $command . " 2>&1", $output, $returnVar);
    return $returnVar === 0;
}

// Получение статуса службы
function getServiceStatus($service) {
    $status = trim(shell_exec("systemctl is-active $service 2>/dev/null"));
    return $status === 'active';
}

// Проверка установленного пакета
function isPackageInstalled($package) {
    $result = shell_exec("dpkg -l $package 2>/dev/null | grep '^ii'");
    return !empty(trim($result));
}

// Список всех проверок
function getChecksList() {
	global $lang4470, $lang4471, $lang4472, $lang4473, $lang4474, $lang4475, $lang4476, $lang4477, $lang4478, $lang4479, $lang4480;
    return [
        'packages' => [
            'name' => $lang4470,
            'items' => [
                'apache2' => 'Apache2 web server',
                'php' => 'PHP',
                'php-cli' => 'PHP CLI',
                'php-fpm' => 'PHP-FPM',
                'php-zip' => 'PHP Zip',
                'php-sqlite3' => 'PHP SQLite3',
                'php-ssh2' => 'PHP SSH2',
				'php-mail' => 'PHP Mail',
				'php-mbstring' => 'PHP MbString',
                'sqlite3' => 'SQLite3',
                'mc' => 'Midnight Commander',
                'tar' => 'Tar archiver',
                'zip' => 'Zip archiver',
                'unzip' => 'Unzip archiver',
                'sudo' => 'Sudo',
                'acl' => 'ACL',
                'ntp' => 'NTP',
                'lvm2' => 'LVM2',
                'mdadm' => 'Mdadm RAID',
                'parted' => 'Parted',
                'dosfstools' => 'DOS FS Tools',
                'ntfs-3g' => 'NTFS-3G',
                'exfat-utils' => 'exFAT-utils',
				'exfat-fuse' => 'exFAT-fuse',
                'xfsprogs' => 'XFS',
                'btrfs-progs' => 'BTRFS',
                'smartmontools' => 'SMART',
                'net-tools' => 'Net Tools',
                'nfs-kernel-server' => 'NFS Server',
                'samba' => 'Samba',
                'smbclient' => 'SMB Client',
                'cifs-utils' => 'CIFS Utils',
                'nfs-common' => 'NFS Common',
                'rsync' => 'Rsync',
                'vsftpd' => 'VSFTPD',
				'ufw' => 'UFW'
            ]
        ],
        'services' => [
            'name' => $lang4471,
            'items' => [
                'apache2' => 'WEB-Apache2',
                'ntp' => 'NTP',
				'rsync' => 'Rsync',
				'smbd' => 'SMB-SMBD',
				'nfs-server' => 'NFS',
				'ufw' => 'Firewall-UFW',
                'vsftpd' => 'FTP-VSFTPD',
                'php7.0-fpm' => 'PHP-FPM'
            ]
        ],
        'files_configs' => [
            'name' => $lang4472,
            'items' => [
                'apache_admin_config' => $lang4473,
                'sudo_www_data' => $lang4474,
                'admin_directory' => $lang4475 . ' /var/www/html/admin',
                'admin_temp_dir' => $lang4475 . ' /var/www/minib/tmp',
                //'ssh_keys_root' => 'SSH keys root',
                'ssh_keys_key' => $lang4476
            ]
        ],
        'permissions' => [
            'name' => $lang4477,
            'items' => [
                'user_groups' => $lang4478,
                'acl_mnt' => $lang4479 . ' /mnt',
                'mdadm_perms' => $lang4480 . ' /etc/mdadm',
                'lvm_perms' => $lang4480 . ' /etc/lvm'
            ]
        ]
    ];
}

// Проверка конкретного пункта
function checkItem($category, $item) {
    switch($category) {
        case 'packages':
            return isPackageInstalled($item);
            
        case 'services':
            return getServiceStatus($item);
            
        case 'files_configs':
            switch($item) {
                case 'apache_admin_config':
                    return file_exists('/etc/apache2/sites-available/admin.conf');
                case 'sudo_www_data':
                    return file_exists('/etc/sudoers.d/www-data');
                case 'admin_directory':
                    return is_dir('/var/www/html/admin');
                case 'admin_temp_dir':
                    return is_dir('/var/www/html/admin/tmp');
                //case 'ssh_keys_root':
                    //return file_exists('/root/.ssh/id_rsa');
                case 'ssh_keys_key':
                    return file_exists('/key/id_rsa');
                default:
                    return false;
            }
            
        case 'permissions':
            switch($item) {
                case 'user_groups':
					$user = trim(shell_exec('whoami'));

					$groups = shell_exec("groups $user 2>/dev/null");
					
					$hasSudo = strpos($groups, 'sudo') !== false || strpos($groups, 'wheel') !== false;
					$hasDisk = strpos($groups, 'disk') !== false;
					$hasPlugdev = strpos($groups, 'plugdev') !== false;
					
					return $hasSudo && $hasDisk && $hasPlugdev;
                case 'acl_mnt':
                    $acl = shell_exec("getfacl /mnt 2>/dev/null | grep www-data");
                    return !empty($acl);
                case 'mdadm_perms':
                    return is_writable('/etc/mdadm');
                case 'lvm_perms':
                    return is_writable('/etc/lvm');
                default:
                    return false;
            }
            
        default:
            return false;
    }
}

// Функция исправления
function fixItem($category, $item) {
    $result = ['success' => false, 'message' => '', 'output' => []];
    
    switch($category) {
        case 'packages':
			global $lang4481, $lang4482, $lang4483, $lang4484, $lang4485;
            if (!isPackageInstalled($item)) {
                runCommand("apt update", $output);
                $success = runCommand("apt install -y $item", $output);
                $result['success'] = $success;
                $result['message'] = $success ? $lang4481 . $item . $lang4482 : $lang4483 . $item;
                $result['output'] = $output;
            } else {
                $result['success'] = true;
                $result['message'] = $lang4484 . $item . $lang4485;
            }
            break;
            
        case 'services':
			global $lang4486, $lang4487, $lang4488, $lang4489, $lang4490;
            if (!getServiceStatus($item)) {
                $success = runCommand("systemctl restart $item", $output);
                if (!$success) {
                    $success = runCommand("systemctl enable $item && systemctl start $item", $output);
                }
                $result['success'] = $success;
                $result['message'] = $success ? $lang4486 . $item . $lang4487 : $lang4488 . $item;
                $result['output'] = $output;
            } else {
                $result['success'] = true;
                $result['message'] = $lang4489 . $item . $lang4490;
            }
            break;
            
        case 'files_configs':
            switch($item) {
                case 'apache_admin_config':
					global $lang4491;
                    $config = 'Listen 1488

<VirtualHost *:1488>
    ServerAdmin webmaster@localhost
    DocumentRoot /var/www/html/admin
    
    <Directory /var/www/html/admin>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/admin_error.log
    CustomLog ${APACHE_LOG_DIR}/admin_access.log combined
</VirtualHost>';
                    file_put_contents('/etc/apache2/sites-available/admin.conf', $config);
                    runCommand("a2ensite admin.conf", $output);
                    runCommand("systemctl restart apache2", $output);
                    $result['success'] = true;
                    $result['message'] = $lang4491;
                    break;
                    
                case 'sudo_www_data':
					global $lang4492;
                    file_put_contents('/etc/sudoers.d/www-data', "www-data ALL=(ALL) NOPASSWD: ALL\n");
                    chmod('/etc/sudoers.d/www-data', 0440);
                    $result['success'] = true;
                    $result['message'] = $lang4492;
                    break;
                    
                case 'admin_directory':
					global $lang4493;
                    runCommand("mkdir -p /var/www/html/admin", $output);
                    runCommand("chown -R www-data:www-data /var/www/html/admin", $output);
                    $result['success'] = true;
                    $result['message'] = $lang4493;
                    break;
                    
                case 'admin_temp_dir':
					global $lang4494;
                    runCommand("mkdir -p /var/www/minib/tmp", $output);
                    runCommand("chmod 777 /var/www/minib/tmp", $output);
                    $result['success'] = true;
                    $result['message'] = $lang4494;
                    break;
                    
                //case 'ssh_keys_root':
                    //runCommand("mkdir -p /root/.ssh", $output);
                    //runCommand("ssh-keygen -t rsa -b 4096 -N '' -f /root/.ssh/id_rsa", $output);
                    //runCommand("cat /root/.ssh/id_rsa.pub >> /root/.ssh/authorized_keys", $output);
                    //$result['success'] = true;
                    //$result['message'] = "SSH keys root created";
                   // break;
                    
                case 'ssh_keys_key':
					global $lang4495;
                    runCommand("mkdir -p /key", $output);
                    runCommand("cp -r /root/.ssh/* /key/", $output);
                    runCommand("chown -R www-data:www-data /key", $output);
                    $result['success'] = true;
                    $result['message'] = $lang4495;
                    break;
            }
            break;
            
        case 'permissions':
            switch($item) {
                case 'user_groups':
					global $lang4496, $lang4497;
					$user = trim(shell_exec('whoami'));
					$output = [];
					
					runCommand("usermod -a -G sudo $user", $output);
					runCommand("usermod -a -G disk $user", $output);
					runCommand("usermod -a -G plugdev $user", $output);
					
					// add to www-data group
					// runCommand("usermod -a -G www-data $user", $output);
					
					$result['success'] = true;
					$result['message'] = $lang4496 . $user . $lang4497;
					$result['output'] = $output;
					break;
                    
                case 'acl_mnt':
					global $lang4498;
                    runCommand("setfacl -R -m u:www-data:rwX,d:u:www-data:rwX,g:users:rwX,d:g:users:rwX /mnt", $output);
                    $result['success'] = true;
                    $result['message'] = $lang4498;
                    break;
                    
                case 'mdadm_perms':
					global $lang4499;
                    runCommand("chmod -R 777 /etc/mdadm", $output);
                    runCommand("chown -R www-data:www-data /etc/mdadm", $output);
                    $result['success'] = true;
                    $result['message'] = $lang4499;
                    break;
                    
                case 'lvm_perms':
					global $lang4500;
                    runCommand("chmod -R 777 /etc/lvm", $output);
                    runCommand("chown -R www-data:www-data /etc/lvm", $output);
                    $result['success'] = true;
                    $result['message'] = $lang4500;
                    break;
            }
            break;
    }
    
    return $result;
}

// API роутинг
$action = $_GET['action'] ?? $_POST['action'] ?? 'check';

if ($action === 'check_all') {
    $checks = getChecksList();
    $results = [];
    
    foreach ($checks as $catKey => $category) {
        $results[$catKey] = [
            'name' => $category['name'],
            'items' => []
        ];
        
        foreach ($category['items'] as $itemKey => $itemName) {
            $status = checkItem($catKey, $itemKey);
            $results[$catKey]['items'][$itemKey] = [
                'name' => $itemName,
                'status' => $status,
                'fixable' => !$status
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'results' => $results,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} elseif ($action === 'fix') {
	global $lang4501;
    $category = $_POST['category'] ?? '';
    $item = $_POST['item'] ?? '';
    
    if (!$category || !$item) {
        echo json_encode(['success' => false, 'error' => $lang4501]);
        exit;
    }
    
    $result = fixItem($category, $item);
    echo json_encode($result);
    
} elseif ($action === 'fix_all' && $_POST['confirm'] === 'yes') {
    $checks = getChecksList();
    $fixResults = [];
    
    foreach ($checks as $catKey => $category) {
        foreach ($category['items'] as $itemKey => $itemName) {
            if (!checkItem($catKey, $itemKey)) {
                $fixResults[$catKey][$itemKey] = fixItem($catKey, $itemKey);
                usleep(500000);
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'results' => $fixResults
    ]);
    
} elseif ($action === 'check_log') {
    $logFile = $_GET['log'] ?? 'install';
    $file = $logFile === 'error' ? ERROR_LOG : LOG_FILE;
    
    if (file_exists($file)) {
        $content = shell_exec("tail -n 100 $file 2>/dev/null");
        echo json_encode(['success' => true, 'content' => $content]);
    } else {
		global $lang4502;
        echo json_encode(['success' => true, 'content' => $lang4502]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Unknown action']);
}
?>