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
define('NFS_EXPORTS_FILE', '/etc/exports');

// ========== ФУНКЦИИ ДЛЯ РАБОТЫ С NFS ==========
function getNfsServiceStatus() {
    $status = [
        'running' => false,
        'enabled' => false,
        'pid' => '',
        'version' => '',
        'ports' => ['2049', '111'],
        'exports_count' => 0
    ];
    
    $output = shell_exec('sudo systemctl is-active nfs-server 2>/dev/null');
    if (trim($output) !== 'active') {
        $output = shell_exec('sudo systemctl is-active nfs-kernel-server 2>/dev/null');
    }
    $status['running'] = trim($output) === 'active';
    
    $output = shell_exec('sudo systemctl is-enabled nfs-server 2>/dev/null');
    if (trim($output) !== 'enabled') {
        $output = shell_exec('sudo systemctl is-enabled nfs-kernel-server 2>/dev/null');
    }
    $status['enabled'] = trim($output) === 'enabled';
    
    $output = shell_exec('sudo pidof nfsd 2>/dev/null');
    $status['pid'] = trim($output);
    
    $output = shell_exec('nfsstat --version 2>/dev/null');
    if ($output && preg_match('/(\d+\.\d+\.\d+)/', $output, $matches)) {
        $status['version'] = $matches[1];
    } else {
        $status['version'] = 'unknown';
    }
    
    $exports = getNfsExports();
    $status['exports_count'] = count($exports);
    
    return $status;
}

function normalizePath($path) {
    $path = preg_replace('#/+#', '/', $path);
    $path = rtrim($path, '/');

    if (empty($path)) {
        return '/';
    }
    return $path;
}

function startNfsService() {
    shell_exec('sudo systemctl start nfs-server nfs-kernel-server rpcbind 2>&1');
    sleep(2);
    return getNfsServiceStatus()['running'];
}

function stopNfsService() {
    shell_exec('sudo systemctl stop nfs-server nfs-kernel-server 2>&1');
    sleep(1);
    return !getNfsServiceStatus()['running'];
}

function restartNfsService() {
    shell_exec('sudo systemctl restart nfs-server nfs-kernel-server rpcbind 2>&1');
    sleep(2);
    return getNfsServiceStatus()['running'];
}

function enableNfsService() {
    shell_exec('sudo systemctl enable nfs-server nfs-kernel-server rpcbind 2>&1');
    return true;
}

function disableNfsService() {
    shell_exec('sudo systemctl disable nfs-server nfs-kernel-server 2>&1');
    return true;
}

// ========== ФУНКЦИИ ДЛЯ РАБОТЫ С КОНФИГУРАЦИЕЙ ==========
function getNfsGlobalConfig() {
    $config = [
        'threads' => '8',
        'nfsv4_lease_time' => '90',
        'nfsv4_grace_period' => '90',
        'mountd_port' => '',
        'statd_port' => '',
        'lockd_port' => '',
        'need_statd' => false,
        'need_idmapd' => false,
        'need_gssd' => false
    ];
    
    $nfsDefaults = '/etc/default/nfs-kernel-server';
    if (file_exists($nfsDefaults)) {
        $content = file_get_contents($nfsDefaults);
        if (preg_match('/^RPCNFSDCOUNT\s*=\s*(\d+)/m', $content, $matches)) {
            $config['threads'] = $matches[1];
        }
        if (preg_match('/^NFSv4LEASETIME\s*=\s*(\d+)/m', $content, $matches)) {
            $config['nfsv4_lease_time'] = $matches[1];
        }
        if (preg_match('/^NFSv4GRACE\s*=\s*(\d+)/m', $content, $matches)) {
            $config['nfsv4_grace_period'] = $matches[1];
        }
        if (preg_match('/^RPCMOUNTDOPTS.*--port\s+(\d+)/', $content, $matches)) {
            $config['mountd_port'] = $matches[1];
        }
        if (preg_match('/^STATDOPTS.*--port\s+(\d+)/', $content, $matches)) {
            $config['statd_port'] = $matches[1];
        }
        if (preg_match('/^LOCKDOPTS.*--port\s+(\d+)/', $content, $matches)) {
            $config['lockd_port'] = $matches[1];
        }
    }
    
    $nfsCommon = '/etc/default/nfs-common';
    if (file_exists($nfsCommon)) {
        $content = file_get_contents($nfsCommon);
        if (preg_match('/^NEED_STATD\s*=\s*(.+)$/m', $content, $matches)) {
            $config['need_statd'] = trim($matches[1]) === 'yes';
        }
        if (preg_match('/^NEED_IDMAPD\s*=\s*(.+)$/m', $content, $matches)) {
            $config['need_idmapd'] = trim($matches[1]) === 'yes';
        }
        if (preg_match('/^NEED_GSSD\s*=\s*(.+)$/m', $content, $matches)) {
            $config['need_gssd'] = trim($matches[1]) === 'yes';
        }
    }
    
    return $config;
}

function saveNfsGlobalConfig($config) {
    $nfsDefaults = '/etc/default/nfs-kernel-server';
    $content = "# NFS Kernel Server Configuration\n";
    $content .= "# Generated by NAS Panel on " . date('Y-m-d H:i:s') . "\n\n";
    
    $content .= "RPCNFSDCOUNT={$config['threads']}\n";
    $content .= "RPCNFSDPRIORITY=0\n";
    
    $mountdOpts = "--manage-gids";
    if (!empty($config['mountd_port'])) {
        $mountdOpts .= " --port {$config['mountd_port']}";
    }
    $content .= "RPCMOUNTDOPTS=\"$mountdOpts\"\n";
    
    if (!empty($config['statd_port'])) {
        $content .= "STATDOPTS=\"--port {$config['statd_port']}\"\n";
    }
    if (!empty($config['lockd_port'])) {
        $content .= "LOCKDOPTS=\"--port {$config['lockd_port']}\"\n";
    }
    
    $content .= "\n# NFS v4 options\n";
    $content .= "NFSv4LEASETIME={$config['nfsv4_lease_time']}\n";
    $content .= "NFSv4GRACE={$config['nfsv4_grace_period']}\n";
    
    file_put_contents($nfsDefaults, $content);
    
    $nfsCommon = '/etc/default/nfs-common';
    $commonContent = "# NFS Common Configuration\n";
    $commonContent .= "# Generated by NAS Panel on " . date('Y-m-d H:i:s') . "\n\n";
    $commonContent .= "NEED_STATD=" . ($config['need_statd'] ? 'yes' : 'no') . "\n";
    $commonContent .= "NEED_IDMAPD=" . ($config['need_idmapd'] ? 'yes' : 'no') . "\n";
    $commonContent .= "NEED_GSSD=" . ($config['need_gssd'] ? 'yes' : 'no') . "\n";
    
    file_put_contents($nfsCommon, $commonContent);
    
    return true;
}

// ========== ФУНКЦИИ ДЛЯ РАБОТЫ С ЭКСПОРТАМИ ==========
function getNfsExports() {
    $exports = [];
    
    if (!file_exists(NFS_EXPORTS_FILE)) {
        return $exports;
    }
    
    $content = file_get_contents(NFS_EXPORTS_FILE);
    if (!$content) return $exports;
    
    $lines = explode("\n", $content);
    $inNasBlock = false;
    
    foreach ($lines as $line) {
        $line = trim($line);
        
        if (strpos($line, '# === NAS NFS EXPORTS ===') !== false) {
            $inNasBlock = true;
            continue;
        }
        if (strpos($line, '# === END NAS NFS EXPORTS ===') !== false) {
            $inNasBlock = false;
            continue;
        }
        
        if ($inNasBlock && !empty($line) && $line[0] != '#') {
            $line = preg_replace('#/+#', '/', $line);
            
            if (preg_match('/^(\/\S+)\s+([^\s(]+)\(([^)]+)\)$/', $line, $matches)) {
                $exports[] = [
                    'path' => normalizePath($matches[1]),
                    'client' => $matches[2],
                    'options' => $matches[3]
                ];
            }
            elseif (preg_match('/^(\/\S+)\s+\*\(([^)]+)\)$/', $line, $matches)) {
                $exports[] = [
                    'path' => normalizePath($matches[1]),
                    'client' => '*',
                    'options' => $matches[2]
                ];
            }
            elseif (preg_match('/^(\/\S+)\s+(.+)$/', $line, $matches)) {
                $normPath = normalizePath($matches[1]);
                $parts = preg_split('/\s+/', $matches[2]);
                foreach ($parts as $part) {
                    if (preg_match('/^([^\s(]+)\(([^)]+)\)$/', $part, $clientMatches)) {
                        $exports[] = [
                            'path' => $normPath,
                            'client' => $clientMatches[1],
                            'options' => $clientMatches[2]
                        ];
                    }
                }
            }
        }
    }
    
    return $exports;
}

function saveNfsExports($exports) {
    $newBlock = "# === NAS NFS EXPORTS ===\n";
    $newBlock .= "# Generated by NAS Panel on " . date('Y-m-d H:i:s') . "\n\n";
    
    $grouped = [];
    foreach ($exports as $export) {
        $path = normalizePath($export['path']);
        if (!isset($grouped[$path])) {
            $grouped[$path] = [];
        }
        $grouped[$path][] = $export;
    }
    
    foreach ($grouped as $path => $exportsList) {
        $line = $path . ' ';
        $entries = [];
        foreach ($exportsList as $export) {
            $entries[] = "{$export['client']}({$export['options']})";
        }
        $line .= implode(' ', $entries);
        $newBlock .= $line . "\n";
    }
    
    $newBlock .= "# === END NAS NFS EXPORTS ===\n";
    
    $current = '';
    if (file_exists(NFS_EXPORTS_FILE)) {
        $current = file_get_contents(NFS_EXPORTS_FILE);
    }
    
    $lines = explode("\n", $current);
    $newContent = [];
    $skip = false;
    
    foreach ($lines as $line) {
        if (strpos($line, '# === NAS NFS EXPORTS ===') !== false) {
            $skip = true;
            continue;
        }
        if (strpos($line, '# === END NAS NFS EXPORTS ===') !== false) {
            $skip = false;
            continue;
        }
        if (!$skip && trim($line) !== '') {
            $newContent[] = $line;
        }
    }
    
    $finalContent = implode("\n", $newContent);
    $finalContent = trim($finalContent);
    
    if (!empty($finalContent)) {
        $finalContent .= "\n\n";
    }
    $finalContent .= $newBlock;
    
    $tempFile = tempnam(sys_get_temp_dir(), 'nfs');
    file_put_contents($tempFile, $finalContent);
    shell_exec("sudo cp $tempFile " . NFS_EXPORTS_FILE);
    shell_exec("sudo chmod 644 " . NFS_EXPORTS_FILE);
    unlink($tempFile);
    
    shell_exec("sudo exportfs -ra 2>&1");
    shell_exec("sudo systemctl restart nfs-kernel-server 2>&1");
    
    return true;
}

// ========== ФУНКЦИИ ДЛЯ СТАТИСТИКИ ==========
function getNfsStats() {
    $stats = [
        'total_exports' => 0,
        'clients_connected' => 0
    ];
    
    $exports = getNfsExports();
    $stats['total_exports'] = count($exports);
    
    $output = shell_exec("sudo showmount -a 2>/dev/null");
    if ($output && trim($output) !== 'All mount points on localhost:') {
        $lines = explode("\n", trim($output));
        $clients = [];
        foreach ($lines as $line) {
            if (preg_match('/^([^:]+):/', $line, $matches)) {
                $clients[] = $matches[1];
            }
        }
        $stats['clients_connected'] = count(array_unique($clients));
    }
    
    return $stats;
}

function getNfsClients() {
    $clients = [];
    $output = shell_exec("sudo showmount -a 2>/dev/null");
    if ($output && trim($output) !== 'All mount points on localhost:') {
        $lines = explode("\n", trim($output));
        foreach ($lines as $line) {
            if (preg_match('/^([^:]+):(.+)$/', $line, $matches)) {
                $clients[] = [
                    'client' => $matches[1],
                    'path' => $matches[2]
                ];
            }
        }
    }
    return $clients;
}

// ========== ФУНКЦИИ ДЛЯ ФАЙЛОВОЙ СИСТЕМЫ ==========
function getDirectoryContents($path) {
    $items = [];
    if (!is_dir($path)) return $items;
    
    $dirs = scandir($path);
    foreach ($dirs as $item) {
        if ($item != '.' && $item != '..') {
            $fullPath = $path . '/' . $item;
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
        echo json_encode(['success' => true, 'data' => getNfsServiceStatus()]);
        break;
        
    case 'service_action':
        $action_type = $_POST['service_action'] ?? '';
        $result = false;
        switch ($action_type) {
            case 'start': $result = startNfsService(); break;
            case 'stop': $result = stopNfsService(); break;
            case 'restart': $result = restartNfsService(); break;
            case 'enable': $result = enableNfsService(); break;
            case 'disable': $result = disableNfsService(); break;
        }
        echo json_encode(['success' => $result, 'data' => getNfsServiceStatus()]);
        break;
        
    case 'get_config':
        echo json_encode(['success' => true, 'data' => getNfsGlobalConfig()]);
        break;
        
    case 'save_config':
		global $lang2429, $lang2430;
        $config = [
            'threads' => $_POST['threads'] ?? '8',
            'nfsv4_lease_time' => $_POST['nfsv4_lease_time'] ?? '90',
            'nfsv4_grace_period' => $_POST['nfsv4_grace_period'] ?? '90',
            'mountd_port' => $_POST['mountd_port'] ?? '',
            'statd_port' => $_POST['statd_port'] ?? '',
            'lockd_port' => $_POST['lockd_port'] ?? '',
            'need_statd' => isset($_POST['need_statd']),
            'need_idmapd' => isset($_POST['need_idmapd']),
            'need_gssd' => isset($_POST['need_gssd'])
        ];
        $result = saveNfsGlobalConfig($config);
        if ($result) {
            restartNfsService();
        }
        echo json_encode(['success' => $result, 'message' => $result ? $lang2429 : $lang2430]);
        break;
        
    case 'get_exports':
        echo json_encode(['success' => true, 'data' => getNfsExports()]);
        break;
        
    case 'create_export':
		global $lang2431, $lang2432, $lang2433, $lang2434, $lang2435, $lang2436;
		$path = normalizePath(trim($_POST['export_path'] ?? ''));
		$client = trim($_POST['client'] ?? '');
		$options = trim($_POST['options'] ?? '');
		
		if (empty($path)) {
			echo json_encode(['success' => false, 'error' => $lang2431]);
		} elseif (!file_exists($path)) {
			echo json_encode(['success' => false, 'error' => $lang2432]);
		} elseif (empty($client)) {
			echo json_encode(['success' => false, 'error' => $lang2433]);
		} elseif (empty($options)) {
			echo json_encode(['success' => false, 'error' => $lang2434]);
		} else {
			$exports = getNfsExports();
			$exports[] = [
				'path' => $path,
				'client' => $client,
				'options' => $options
			];
			if (saveNfsExports($exports)) {
				echo json_encode(['success' => true, 'message' => $lang2435]);
			} else {
				echo json_encode(['success' => false, 'error' => $lang2436]);
			}
		}
		break;
        
    case 'update_export':
		global $lang2437, $lang2438, $lang2439;
		$old_path = normalizePath($_POST['old_path'] ?? '');
		$old_client = $_POST['old_client'] ?? '';
		$path = normalizePath(trim($_POST['export_path'] ?? ''));
		$client = trim($_POST['client'] ?? '');
		$options = trim($_POST['options'] ?? '');
		
		if (empty($path) || empty($client) || empty($options)) {
			echo json_encode(['success' => false, 'error' => $lang2437]);
		} else {
			$exports = getNfsExports();
			$updated = false;
			
			foreach ($exports as &$export) {
				if ($export['path'] === $old_path && $export['client'] === $old_client) {
					$export['path'] = $path;
					$export['client'] = $client;
					$export['options'] = $options;
					$updated = true;
					break;
				}
			}
			
			if ($updated && saveNfsExports($exports)) {
				echo json_encode(['success' => true, 'message' => $lang2438]);
			} else {
				echo json_encode(['success' => false, 'error' => $lang2439]);
			}
		}
		break;
        
    case 'delete_export':
		global $lang2440, $lang2441, $lang2442;
        $index = intval($_POST['index'] ?? -1);
        $exports = getNfsExports();
        
        if (isset($exports[$index])) {
            unset($exports[$index]);
            if (saveNfsExports(array_values($exports))) {
                echo json_encode(['success' => true, 'message' => $lang2440]);
            } else {
                echo json_encode(['success' => false, 'error' => $lang2441]);
            }
        } else {
            echo json_encode(['success' => false, 'error' => $lang2442]);
        }
        break;
        
    case 'get_stats':
        echo json_encode(['success' => true, 'data' => getNfsStats()]);
        break;
        
    case 'get_clients':
        echo json_encode(['success' => true, 'data' => getNfsClients()]);
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
		global $lang2443, $lang2444, $lang2445, $lang2446;
        $path = $_POST['path'] ?? '';
        $name = trim($_POST['name'] ?? '');
        
        if (empty($name)) {
            echo json_encode(['success' => false, 'error' => $lang2443]);
        } elseif (!preg_match('/^[a-zA-Z0-9_\-\.\s]+$/', $name)) {
            echo json_encode(['success' => false, 'error' => $lang2444]);
        } else {
            $fullPath = rtrim($path, '/') . '/' . $name;
            if (createDirectory($fullPath)) {
                echo json_encode(['success' => true, 'message' => $lang2445, 'path' => $fullPath]);
            } else {
                echo json_encode(['success' => false, 'error' => $lang2446]);
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