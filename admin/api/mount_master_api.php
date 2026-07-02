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

error_reporting(E_ERROR);
ini_set('display_errors', 0);
set_time_limit(0);

define('LOG_FILE', '/var/www/minib/logs/mount_master.log');
define('ERROR_LOG_FILE', '/var/www/minib/logs/mount_master_error.log');

function writeLog($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents(LOG_FILE, "[{$timestamp}] [{$level}] {$message}" . PHP_EOL, FILE_APPEND);
}

function writeError($message, $context = []) {
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? ' | Context: ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
    file_put_contents(ERROR_LOG_FILE, "[{$timestamp}] [ERROR] {$message}{$contextStr}" . PHP_EOL, FILE_APPEND);
    file_put_contents(LOG_FILE, "[{$timestamp}] [ERROR] {$message}{$contextStr}" . PHP_EOL, FILE_APPEND);
}

function execCmd($cmd, $sudo = true, $timeout = 60, $useNsenter = false) {
    $fullCmd = $cmd;
    if ($useNsenter) {
        $fullCmd = "nsenter -t 1 -m " . $cmd;
    }
    if ($sudo) {
        $fullCmd = "sudo " . $fullCmd;
    }
    $fullCmd .= " 2>&1";
    
    $process = proc_open($fullCmd, [
        0 => ["pipe", "r"],
        1 => ["pipe", "w"],
        2 => ["pipe", "w"]
    ], $pipes);
    
    if (!is_resource($process)) {
        return '';
    }
    
    $output = '';
    $start = time();
    
    while (true) {
        $read = [$pipes[1]];
        $write = null;
        $except = null;
        
        if (stream_select($read, $write, $except, 0, 200000) > 0) {
            $output .= fread($pipes[1], 8192);
        }
        
        $status = proc_get_status($process);
        if (!$status['running']) {
            while (!feof($pipes[1])) {
                $output .= fread($pipes[1], 8192);
            }
            break;
        }
        
        if (time() - $start > $timeout) {
            proc_terminate($process, 9);
            return '';
        }
        
        usleep(10000);
    }
    
    fclose($pipes[0]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);
    
    return trim($output);
}

function getSystemUsers() {
    $users = [];
    $output = execCmd("getent passwd 2>/dev/null", false, 10);
    if ($output) {
        $lines = explode("\n", trim($output));
        foreach ($lines as $line) {
            if (empty($line)) continue;
            $parts = explode(':', $line);
            if ($parts[2] >= 1000 && $parts[2] < 65534) {
                $users[] = ['username' => $parts[0], 'uid' => $parts[2], 'gid' => $parts[3]];
            }
        }
    }
    return $users;
}

function getAvailableDevices() {
    $devices = [];
    
    $lsblkJson = execCmd("lsblk -J -o NAME,TYPE,SIZE,FSTYPE,LABEL,UUID,MOUNTPOINT 2>/dev/null", true, 10);
	if (!empty($lsblkJson)) {
		$data = json_decode($lsblkJson, true);
		if ($data && isset($data['blockdevices'])) {
			foreach ($data['blockdevices'] as $disk) {
				if ($disk['type'] === 'disk') {
					if (isset($disk['children']) && count($disk['children']) > 0) {
						foreach ($disk['children'] as $part) {
							if ($part['type'] !== 'part' || 
								!empty($part['mountpoint']) ||
								empty($part['fstype']) ||
								$part['size'] === '1K' || 
								$part['size'] === '0' ||
								$part['size'] === '1M' ||
								in_array($part['mountpoint'] ?? '', ['/', '/boot', '/boot/efi', '/swap'])) {
								writeLog("Skipping partition: {$part['name']} (Type: {$part['type']}, FS: {$part['fstype']}, Size: {$part['size']}, Mount: {$part['mountpoint']})");
								continue;
							}
							
							$devices[] = [
								'name' => $part['name'],
								'path' => '/dev/' . $part['name'],
								'size' => $part['size'] ?? 'Unknown',
								'fstype' => $part['fstype'] ?? null,
								'label' => $part['label'] ?? null,
								'uuid' => $part['uuid'] ?? null,
								'type' => 'partition',
								'source_type' => 'partition',
								'is_whole_disk' => false
							];
							writeLog("Added partition: /dev/{$part['name']} ({$part['size']}) - {$part['fstype']}");
						}
					} 
					elseif (!empty($disk['fstype']) && empty($disk['mountpoint']) && 
							$disk['size'] !== '1K' && $disk['size'] !== '0') {
						$devices[] = [
							'name' => $disk['name'],
							'path' => '/dev/' . $disk['name'],
							'size' => $disk['size'] ?? 'Unknown',
							'fstype' => $disk['fstype'],
							'label' => $disk['label'] ?? null,
							'uuid' => $disk['uuid'] ?? null,
							'type' => 'disk',
							'source_type' => 'whole_disk',
							'is_whole_disk' => true
						];
						writeLog("Detected whole-disk filesystem on /dev/{$disk['name']} with fstype {$disk['fstype']}");
					}
				}
			}
		}
	}
    
    // RAID устройства (md*)
    $mdOutput = execCmd("lsblk -J -o NAME,TYPE,SIZE,FSTYPE,LABEL,UUID,MOUNTPOINT /dev/md* 2>/dev/null", true, 10);
		if (!empty($mdOutput)) {
			$mdData = json_decode($mdOutput, true);
			if ($mdData && isset($mdData['blockdevices'])) {
				foreach ($mdData['blockdevices'] as $raid) {
					if (!empty($raid['mountpoint'])) {
						writeLog("Skipping mounted RAID: /dev/{$raid['name']} -> {$raid['mountpoint']}");
						continue;
					}
					
					if (empty($raid['fstype']) || 
						$raid['fstype'] === 'LVM2_member' ||
						in_array($raid['size'], ['1K', '0', '1M'])) {
						writeLog("Skipping RAID without FS or LVM2: /dev/{$raid['name']} ({$raid['fstype']})");
						continue;
					}
					
					$devices[] = [
						'name' => $raid['name'],
						'path' => '/dev/' . $raid['name'],
						'size' => $raid['size'] ?? 'Unknown',
						'fstype' => $raid['fstype'] ?? null,
						'label' => $raid['label'] ?? null,
						'uuid' => $raid['uuid'] ?? null,
						'type' => 'raid',
						'source_type' => 'raid',
						'is_whole_disk' => false
					];
					writeLog("Added RAID: /dev/{$raid['name']} ({$raid['size']}) - {$raid['fstype']}");
					
					if (isset($raid['children'])) {
						foreach ($raid['children'] as $part) {
							if (!empty($part['mountpoint'])) {
								writeLog("Skipping mounted RAID partition: /dev/{$part['name']} -> {$part['mountpoint']}");
								continue;
							}
							
							if (empty($part['fstype']) || 
								$part['fstype'] === 'LVM2_member' ||
								in_array($part['size'], ['1K', '0', '1M'])) {
								writeLog("Skipping RAID partition without FS: /dev/{$part['name']} ({$part['fstype']})");
								continue;
							}
							
							$devices[] = [
								'name' => $part['name'],
								'path' => '/dev/' . $part['name'],
								'size' => $part['size'] ?? 'Unknown',
								'fstype' => $part['fstype'] ?? null,
								'label' => $part['label'] ?? null,
								'uuid' => $part['uuid'] ?? null,
								'type' => 'raid_partition',
								'source_type' => 'raid_partition',
								'is_whole_disk' => false
							];
							writeLog("Added RAID partition: /dev/{$part['name']} ({$part['size']}) - {$part['fstype']}");
						}
					}
				}
			}
		}
    
    // LVM Logical Volumes
    $lvsOutput = execCmd("lvs --noheadings -o lv_name,vg_name,lv_size,lv_path,lv_attr 2>/dev/null", true, 10);
    if (!empty($lvsOutput)) {
        $lines = explode("\n", trim($lvsOutput));
        foreach ($lines as $line) {
            if (empty(trim($line))) continue;
            $parts = preg_split('/\s+/', trim($line));
            if (count($parts) >= 5) {
                $lvName = $parts[0];
                $vgName = $parts[1];
                $size = $parts[2];
                $lvPath = $parts[3];
                
                $mountCheck = execCmd("mount | grep '{$lvPath} '", true, 5);
                if (empty($mountCheck)) {
                    $fstype = trim(execCmd("blkid -s TYPE -o value {$lvPath} 2>/dev/null", true, 5));
                    $devices[] = [
                        'name' => $lvName,
                        'path' => $lvPath,
                        'size' => $size,
                        'fstype' => $fstype ?: null,
                        'vg_name' => $vgName,
                        'type' => 'lvm',
                        'source_type' => 'lvm',
                        'is_whole_disk' => false
                    ];
                }
            }
        }
    }
    
    return $devices;
}

function getMountEntries() {
    $entries = [];
    
    $fstabContent = @file_get_contents('/etc/fstab');
    $fstabMountPoints = [];
    if ($fstabContent !== false) {
        $lines = explode("\n", $fstabContent);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || $line[0] === '#') continue;
            
            $parts = preg_split('/\s+/', $line);
            if (count($parts) >= 2) {
                $mountPoint = $parts[1];
                $mountPoint = str_replace('\\040', ' ', $mountPoint);
                $fstabMountPoints[] = $mountPoint;
            }
        }
    }
    
    // Получаем все монтирования включая CIFS
    $mountOutput = execCmd("mount | grep -E '^/dev/|^//|^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+:|^[a-zA-Z0-9.-]+:' | grep -v 'loop'", true, 10);
    if (!empty($mountOutput)) {
        $lines = explode("\n", trim($mountOutput));
        foreach ($lines as $line) {
            if (empty(trim($line))) continue;
            
            // Обработка CIFS
            if (preg_match('/^\/\/(\S+)\/(\S+)\s+on\s+(\S+)\s+type\s+cifs/', $line, $matches)) {
                $server = $matches[1];
                $share = $matches[2];
                $mountPoint = $matches[3];
                
                // Извлекаем опции
                preg_match('/\((.+)\)$/', $line, $optMatches);
                $options = $optMatches[1] ?? '';
                
                $inFstab = in_array($mountPoint, $fstabMountPoints);
                
                $entries[] = [
                    'device' => "//{$server}/{$share}",
                    'device_path' => "//{$server}/{$share}",
                    'mount_point' => $mountPoint,
                    'fstype' => 'cifs',
                    'options' => $options,
                    'in_fstab' => $inFstab,
                    'fstab_entry' => null,
                    'is_managed' => false,
                    'is_network' => true,
                    'network_type' => 'cifs',
                    'is_system' => false
                ];
                continue;
            }
            
            // Обработка NFS
            if (preg_match('/^(\S+):(\S+)\s+on\s+(\S+)\s+type\s+nfs/', $line, $matches)) {
                $server = $matches[1];
                $export = $matches[2];
                $mountPoint = $matches[3];
                
                preg_match('/\((.+)\)$/', $line, $optMatches);
                $options = $optMatches[1] ?? '';
                
                $inFstab = in_array($mountPoint, $fstabMountPoints);
                
                $entries[] = [
                    'device' => "{$server}:{$export}",
                    'device_path' => "{$server}:{$export}",
                    'mount_point' => $mountPoint,
                    'fstype' => 'nfs',
                    'options' => $options,
                    'in_fstab' => $inFstab,
                    'fstab_entry' => null,
                    'is_managed' => false,
                    'is_network' => true,
                    'network_type' => 'nfs',
                    'is_system' => false
                ];
                continue;
            }
            
            // Обработка локальных устройств
            if (preg_match('/^\/dev\/(\S+)\s+on\s+(\S+)\s+type\s+(\S+)\s+\((.+)\)/', $line, $matches)) {
                $device = $matches[1];
                $mountPoint = $matches[2];
                $fstype = $matches[3];
                $options = $matches[4];
                
                $systemMounts = ['/tmp', '/var/tmp', '/run', '/proc', '/sys', '/dev', '/dev/shm', '/run/lock'];
                if (in_array($mountPoint, $systemMounts) || $fstype === 'tmpfs') {
                    continue;
                }
                
                $inFstab = in_array($mountPoint, $fstabMountPoints);
                
                $entries[] = [
                    'device' => $device,
                    'device_path' => '/dev/' . $device,
                    'mount_point' => $mountPoint,
                    'fstype' => $fstype,
                    'options' => $options,
                    'in_fstab' => $inFstab,
                    'fstab_entry' => null,
                    'is_managed' => false,
                    'is_network' => false,
                    'network_type' => null,
                    'is_system' => false
                ];
            }
        }
    }
    
    return $entries;
}

function checkMountPointCreatedByUs($mountPoint) {
    $markerFile = $mountPoint . '/.mount_created_by_mount_master';
    return file_exists($markerFile);
}

function getFstabEntries() {
    $entries = [];
    $fstab = @file_get_contents('/etc/fstab');
    if (!$fstab) return $entries;
    
    $lines = explode("\n", $fstab);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || $line[0] === '#') continue;
        
        $parts = preg_split('/\s+/', $line);
        if (count($parts) >= 3) {
            $device = $parts[0];
            $mountPoint = $parts[1];
            $fstype = $parts[2];
            $options = $parts[3] ?? 'defaults';
            
            $entries[] = [
                'device' => $device,
                'mount_point' => $mountPoint,
                'fstype' => $fstype,
                'options' => $options,
                'dump' => $parts[4] ?? '0',
                'pass' => $parts[5] ?? '0',
                'full_line' => $line
            ];
        }
    }
    
    return $entries;
}

function addToFstab($device, $mountPoint, $fstype, $options = 'defaults', $dump = 0, $pass = 2) {
    global $lang267, $lang4539, $lang4540;
    writeLog("Adding to fstab: {$device} -> {$mountPoint}");
    
    $uuid = getDeviceUUID($device);
    $deviceToUse = $uuid ? "UUID={$uuid}" : $device;
    
    $escapedMountPoint = str_replace(' ', '\\040', $mountPoint);
    
    $fstabLine = "{$deviceToUse} {$escapedMountPoint} {$fstype} {$options} {$dump} {$pass}\n";
    
    $currentFstab = @file_get_contents('/etc/fstab');
    if ($currentFstab !== false) {
        if (strpos($currentFstab, $escapedMountPoint) !== false) {
            writeLog("Entry for {$mountPoint} already exists in fstab");
            return ['success' => true, 'message' => $lang4539];
        }
        
        if (strpos($currentFstab, $deviceToUse) !== false) {
            writeLog("Entry for {$deviceToUse} already exists in fstab");
            return ['success' => true, 'message' => $lang4539];
        }
    }
    
    $result = file_put_contents('/etc/fstab', $fstabLine, FILE_APPEND);
    if ($result !== false) {
        writeLog("Added to fstab: {$fstabLine}");
        return ['success' => true, 'message' => $lang267];
    } else {
        $error = $lang4540;
        writeError($error);
        return ['success' => false, 'error' => $error];
    }
}

function removeFromFstab($mountPoint) {
    writeLog("Removing from fstab by mount point: {$mountPoint}");
    
    $fstab = @file_get_contents('/etc/fstab');
    if ($fstab === false) {
        writeError("Cannot read /etc/fstab");
        return false;
    }
    
    $lines = explode("\n", $fstab);
    $newLines = [];
    $found = false;
    
    $escapedMountPoint = str_replace(' ', '\\040', $mountPoint);
    
    foreach ($lines as $line) {
        $lineTrimmed = trim($line);
        if (empty($lineTrimmed) || $lineTrimmed[0] === '#') {
            $newLines[] = $line;
            continue;
        }
        
        if (strpos($line, $mountPoint) !== false || strpos($line, $escapedMountPoint) !== false) {
            $found = true;
            writeLog("Removing fstab entry: {$line}");
            continue;
        }
        
        $newLines[] = $line;
    }
    
    if ($found) {
        $newContent = implode("\n", $newLines);
        $newContent = preg_replace('/\n{3,}/', "\n\n", $newContent);
        $result = file_put_contents('/etc/fstab', $newContent);
        if ($result !== false) {
            writeLog("Entry removed from fstab for mount point: {$mountPoint}");
            return true;
        } else {
            writeError("Failed to write /etc/fstab");
            return false;
        }
    } else {
        writeLog("No entry found in fstab for mount point: {$mountPoint}");
        return true;
    }
}

function getDeviceUUID($device) {
    if (strpos($device, 'UUID=') === 0) {
        return substr($device, 5);
    }
    
    $realPath = realpath($device);
    if (!$realPath) {
        return null;
    }
    
    $output = execCmd("blkid -s UUID -o value {$realPath} 2>/dev/null", true, 5);
    if (!empty($output)) {
        $uuid = trim($output);
        if (!empty($uuid)) {
            return $uuid;
        }
    }
    
    return null;
}

function isInFstab($mountPoint) {
    $fstab = @file_get_contents('/etc/fstab');
    if ($fstab === false) {
        return false;
    }
    
    $escapedMountPoint = str_replace(' ', '\\040', $mountPoint);
    
    $lines = explode("\n", $fstab);
    foreach ($lines as $line) {
        $lineTrimmed = trim($line);
        if (empty($lineTrimmed) || $lineTrimmed[0] === '#') {
            continue;
        }
        
        if (strpos($line, $mountPoint) !== false || strpos($line, $escapedMountPoint) !== false) {
            return true;
        }
    }
    
    return false;
}

function mountLocalDevice($device, $mountPoint, $options = [], $addToFstab = false) {
    global $lang4541, $lang4542, $lang4543, $lang4544, $lang4545;
    writeLog("Mounting local device: {$device} -> {$mountPoint}");
    
    $devicePath = $device;
    
    $isLvm = false;
    if (strpos($device, '/dev/mapper/') === 0) {
        $isLvm = true;
        writeLog("LVM mapper device detected: {$devicePath}");
    } elseif (strpos($device, '/dev/') === 0 && strpos($device, '/dev/mapper/') !== 0) {
        $pathParts = explode('/', $device);
        if (count($pathParts) >= 4 && $pathParts[1] === 'dev' && 
            (strpos($pathParts[2], 'vg_') === 0 || strpos($pathParts[2], 'vg-') === 0)) {
            $vgName = $pathParts[2];
            $lvName = $pathParts[3];
            $devicePath = "/dev/mapper/{$vgName}-{$lvName}";
            $isLvm = true;
            writeLog("Converted LVM path: {$device} -> {$devicePath}");
        }
    } elseif (strpos($device, 'vg_') === 0 || strpos($device, 'vg-') === 0) {
        $lvName = $device;
        $lvmInfo = execCmd("lvs --noheadings -o vg_name,lv_name,lv_path 2>/dev/null | grep -E '\\s+{$lvName}\\s+'", true, 5);
        if (!empty($lvmInfo)) {
            $parts = preg_split('/\s+/', trim($lvmInfo));
            if (count($parts) >= 3) {
                $vgName = $parts[0];
                $devicePath = "/dev/mapper/{$vgName}-{$lvName}";
                $isLvm = true;
                writeLog("Found LVM device: {$device} -> {$devicePath}");
            }
        }
    } else {
        if (strpos($device, '/dev/') !== 0) {
            $devicePath = '/dev/' . $device;
        }
    }
    
    writeLog("Final device path: {$devicePath}, isLvm: " . ($isLvm ? 'yes' : 'no'));
    
    if (!file_exists($devicePath)) {
        if ($isLvm) {
            $altPath = str_replace('/dev/mapper/', '/dev/', $devicePath);
            $altPath = str_replace('-', '/', $altPath);
            if (file_exists($altPath)) {
                writeLog("Using alternative LVM path: {$altPath}");
                $devicePath = $altPath;
            } else {
                $lvCheck = execCmd("lvs --noheadings -o lv_path | grep -E '{$devicePath}|" . str_replace('/dev/mapper/', '', $devicePath) . "' 2>/dev/null", true, 5);
                if (empty($lvCheck)) {
                    $error = $lang4541 . $devicePath . $lang4542 . " (LVM device not found)";
                    writeError($error);
                    return ['success' => false, 'error' => $error];
                }
            }
        } else {
            $error = $lang4541 . $devicePath . $lang4542;
            writeError($error);
            return ['success' => false, 'error' => $error];
        }
    }
    
    $isMounted = false;
    $mountCheck = execCmd("mount | grep -E '{$devicePath} |" . str_replace('/dev/mapper/', '/dev/', $devicePath) . " '", true, 5);
    if (!empty($mountCheck)) {
        $isMounted = true;
        writeLog("Device is already mounted: {$mountCheck}");
    }
    
    if (!$isMounted && $isLvm) {
        $altPath = str_replace('/dev/mapper/', '/dev/', $devicePath);
        $altPath = str_replace('-', '/', $altPath);
        $mountCheckAlt = execCmd("mount | grep '{$altPath} '", true, 5);
        if (!empty($mountCheckAlt)) {
            $isMounted = true;
            writeLog("Device is already mounted via alternative path: {$mountCheckAlt}");
        }
    }
    
    if ($isMounted) {
        $error = $lang4543 . " (device: {$devicePath})";
        writeLog($error);
        return ['success' => false, 'error' => $error];
    }
    
    $fstype = $options['fstype'] ?? 'auto';
    writeLog("Requested fstype: {$fstype}");
    
    if ($fstype === 'auto') {
        $fstype = trim(execCmd("blkid -s TYPE -o value {$devicePath} 2>/dev/null", true, 5));
        writeLog("blkid result: " . ($fstype ?: 'empty'));
        
        if (empty($fstype) && $isLvm) {
            $altPath = str_replace('/dev/mapper/', '/dev/', $devicePath);
            $altPath = str_replace('-', '/', $altPath);
            $fstype = trim(execCmd("blkid -s TYPE -o value {$altPath} 2>/dev/null", true, 5));
            writeLog("blkid on alt path result: " . ($fstype ?: 'empty'));
        }
        
        if (empty($fstype)) {
            $fstype = trim(execCmd("lsblk -n -o FSTYPE {$devicePath} 2>/dev/null", true, 5));
            writeLog("lsblk result: " . ($fstype ?: 'empty'));
        }
        
        if (empty($fstype)) {
            $fstype = 'auto';
        }
    }
    writeLog("Final filesystem type: {$fstype}");
    
    $mountPointCreated = false;
    if (!is_dir($mountPoint)) {
        $parentDir = dirname($mountPoint);
        if (!is_dir($parentDir) && $parentDir !== '/') {
            execCmd("mkdir -p \"{$parentDir}\"", true, 5);
        }
        
        $result = execCmd("mkdir -p \"{$mountPoint}\"", true, 5);
        if (!empty($result) && strpos($result, 'cannot create') !== false) {
            $error = $lang4544 . $result;
            writeError($error);
            return ['success' => false, 'error' => $error];
        }
        $mountPointCreated = true;
        writeLog("Mount point created: {$mountPoint}");
    } else {
        writeLog("Mount point already exists: {$mountPoint}");
    }
    
    $uid = $options['uid'] ?? 'www-data';
    $gid = $options['gid'] ?? 'www-data';
    writeLog("UID: {$uid}, GID: {$gid}");
    
    if (is_numeric($uid)) {
        $uidNum = $uid;
    } else {
        $uidNum = trim(execCmd("id -u {$uid} 2>/dev/null", false, 3));
        if (empty($uidNum)) $uidNum = 33;
    }
    writeLog("UID number: {$uidNum}");
    
    if (is_numeric($gid)) {
        $gidNum = $gid;
    } else {
        $gidNum = trim(execCmd("id -g {$gid} 2>/dev/null", false, 3));
        if (empty($gidNum)) $gidNum = 33;
    }
    writeLog("GID number: {$gidNum}");
    
    if ($fstype === 'ntfs' || $fstype === 'ntfs-3g') {
        $mountOptions = "uid={$uidNum},gid={$gidNum},umask=000,fmask=000,dmask=000,big_writes";
    } elseif ($fstype === 'exfat') {
        $mountOptions = "uid={$uidNum},gid={$gidNum},umask=000,fmask=000,dmask=000";
    } elseif ($fstype === 'vfat' || $fstype === 'fat32') {
        $mountOptions = "uid={$uidNum},gid={$gidNum},umask=000,fmask=000,dmask=000,shortname=mixed,utf8";
    } elseif ($fstype === 'ext4' || $fstype === 'ext3' || $fstype === 'ext2') {
        $mountOptions = "defaults,noatime";
    } elseif ($fstype === 'xfs') {
        $mountOptions = "defaults,noatime";
    } elseif ($fstype === 'btrfs') {
        $mountOptions = "defaults,noatime";
    } else {
        $mountOptions = "defaults";
    }
    
    writeLog("Mount options: {$mountOptions}");
    
    if ($fstype === 'auto') {
        $cmd = "mount -o {$mountOptions} {$devicePath} \"{$mountPoint}\" 2>&1";
    } else {
        $cmd = "mount -t {$fstype} -o {$mountOptions} {$devicePath} \"{$mountPoint}\" 2>&1";
		$output = execCmd($cmd, true, 30, true);
    }
    
    writeLog("Executing: {$cmd}");
    $output = execCmd($cmd, true, 30);
    writeLog("Command output: " . ($output ?: 'empty'));
    
    sleep(2);
    
    $checkMount = execCmd("cat /proc/mounts | grep -E '{$devicePath} |" . str_replace('/dev/mapper/', '/dev/', $devicePath) . " '", true, 5, true);
    if (empty($checkMount) && $isLvm) {
        $altPath = str_replace('/dev/mapper/', '/dev/', $devicePath);
        $altPath = str_replace('-', '/', $altPath);
        $checkMount = execCmd("mount | grep '{$altPath} '", true, 5);
    }
    writeLog("Mount check result: " . ($checkMount ?: 'not mounted'));
    
    if (empty($checkMount)) {
        if ($mountPointCreated) {
            execCmd("rmdir \"{$mountPoint}\" 2>/dev/null", true, 3);
            writeLog("Removed empty mount point");
        }
        
        $errorMsg = $output ?: 'Unknown error';
        
        if (strpos($output, 'mount point') !== false) {
            $errorMsg = "Mount point error: " . $output;
        } elseif (strpos($output, 'no such device') !== false) {
            $errorMsg = "Device not found: " . $output;
        } elseif (strpos($output, 'not a valid block device') !== false) {
            $errorMsg = "Not a valid block device: " . $output;
        } elseif (strpos($output, 'unknown filesystem type') !== false) {
            $errorMsg = "Unknown filesystem type '{$fstype}': " . $output;
        } elseif (strpos($output, 'permission denied') !== false) {
            $errorMsg = "Permission denied: " . $output;
        } elseif (strpos($output, 'already mounted') !== false) {
            $errorMsg = "Device already mounted: " . $output;
        }
        
        writeError("Mount failed: {$errorMsg}");
        return ['success' => false, 'error' => $lang4545 . $errorMsg];
    }
    
    writeLog("Device successfully mounted");
    
    if ($fstype === 'ext4' || $fstype === 'ext3' || $fstype === 'ext2') {
        writeLog("Setting permissions for ext filesystem");
        execCmd("chown {$uidNum}:{$gidNum} \"{$mountPoint}\"", true, 3);
        execCmd("chmod 755 \"{$mountPoint}\"", true, 3);
    }
    
    execCmd("touch \"{$mountPoint}/.mount_created_by_mount_master\"", true, 3);
    writeLog("Created mount marker");
    
    if ($addToFstab) {
        writeLog("Adding to fstab");
        $uuid = trim(execCmd("blkid -s UUID -o value {$devicePath} 2>/dev/null", true, 5));
        if (empty($uuid) && $isLvm) {
            $altPath = str_replace('/dev/mapper/', '/dev/', $devicePath);
            $altPath = str_replace('-', '/', $altPath);
            $uuid = trim(execCmd("blkid -s UUID -o value {$altPath} 2>/dev/null", true, 5));
        }
        if (!empty($uuid)) {
            $fstabDevice = "UUID={$uuid}";
            writeLog("Using UUID: {$uuid}");
        } else {
            $fstabDevice = $devicePath;
            writeLog("Using device path: {$devicePath}");
        }
        $fstabOptions = "defaults,noatime";
        addToFstab($fstabDevice, $mountPoint, $fstype, $fstabOptions);
    }
    
    writeLog("Device {$device} mounted successfully at {$mountPoint}");
    
    return [
        'success' => true,
        'mount_point' => $mountPoint,
        'mount_point_created' => $mountPointCreated,
        'fstype' => $fstype,
        'device_path' => $devicePath
    ];
}

function mountCifsShare($server, $share, $mountPoint, $options = [], $addToFstab = false) {
    global $lang4546, $lang4547, $lang4548;
    writeLog("Mounting CIFS share: //{$server}/{$share} -> {$mountPoint}");
    
    $source = "//{$server}/{$share}";
    
    $mountCheck = execCmd("mount | grep '{$source} '", true, 5);
    if (!empty($mountCheck)) {
        writeLog("Share already mounted, trying to unmount first: {$mountPoint}");
        execCmd("umount -l \"{$mountPoint}\" 2>/dev/null", true, 5);
        sleep(1);
        
        $mountCheck = execCmd("mount | grep '{$source} '", true, 5);
        if (!empty($mountCheck)) {
            return ['success' => false, 'error' => $lang4546 . " (device busy)"];
        }
    }
    
    if (is_dir($mountPoint) && !empty(execCmd("mount | grep ' {$mountPoint} '", true, 5))) {
        writeLog("Mount point is already used, trying to unmount: {$mountPoint}");
        execCmd("umount -l \"{$mountPoint}\" 2>/dev/null", true, 5);
        sleep(1);
    }
    
    $mountPointCreated = false;
    if (!is_dir($mountPoint)) {
        $parentDir = dirname($mountPoint);
        if (!is_dir($parentDir) && $parentDir !== '/') {
            execCmd("mkdir -p \"{$parentDir}\"", true, 5);
        }
        
        $result = execCmd("mkdir -p \"{$mountPoint}\"", true, 5);
        if (!empty($result) && strpos($result, 'cannot create') !== false) {
            return ['success' => false, 'error' => $lang4547 . $result];
        }
        $mountPointCreated = true;
    }
    
    $uid = $options['uid'] ?? 'www-data';
    $gid = $options['gid'] ?? 'www-data';
    
    if (is_numeric($uid)) {
        $uidNum = $uid;
    } else {
        $uidNum = trim(execCmd("id -u {$uid} 2>/dev/null", false, 3));
        if (empty($uidNum)) $uidNum = 33;
    }
    
    if (is_numeric($gid)) {
        $gidNum = $gid;
    } else {
        $gidNum = trim(execCmd("id -g {$gid} 2>/dev/null", false, 3));
        if (empty($gidNum)) $gidNum = 33;
    }
    
    $credFile = null;
    if (!empty($options['username']) && !empty($options['password'])) {
        $credFile = createCifsCredentialsFile(
            $server, 
            $share, 
            $options['username'], 
            $options['password'], 
            $options['domain'] ?? ''
        );
    }
    
    $mountOptions = "uid={$uidNum},gid={$gidNum},file_mode=0777,dir_mode=0777,noperm";
    
    if ($credFile && file_exists($credFile)) {
        $mountOptions .= ",credentials={$credFile}";
        writeLog("Using credentials file for mount: {$credFile}");
    } elseif (!empty($options['username']) && !empty($options['password'])) {
        $mountOptions .= ",username={$options['username']},password={$options['password']}";
        writeLog("Using direct credentials for mount");
    }
    
    if (!empty($options['domain'])) {
        $mountOptions .= ",domain={$options['domain']}";
    }
    
    if (!empty($options['vers'])) {
        $mountOptions .= ",vers={$options['vers']}";
    } else {
        $mountOptions .= ",vers=3.0";
    }
    
    if (!empty($options['iocharset'])) {
        $mountOptions .= ",iocharset={$options['iocharset']}";
    }
    
    $mountOptions .= ",nounix,sec=ntlmssp";
    
    $maxRetries = 3;
    $retryCount = 0;
    $mounted = false;
    $output = '';
    
    while ($retryCount < $maxRetries && !$mounted) {
        if ($retryCount > 0) {
            writeLog("Retry mount attempt {$retryCount}/{$maxRetries}");
            sleep(2);
            execCmd("umount -l \"{$mountPoint}\" 2>/dev/null", true, 5);
            sleep(1);
        }
        
        $cmd = "mount -t cifs -o {$mountOptions} \"{$source}\" \"{$mountPoint}\" 2>&1";
        writeLog("Executing: {$cmd}");
        $output = execCmd($cmd, true, 30);
        writeLog("Mount output: " . ($output ?: 'empty'));
        
        sleep(2);
        $checkMount = execCmd("mount | grep '{$source} '", true, 5);
        writeLog("Mount check: " . ($checkMount ?: 'not mounted'));
        
        if (!empty($checkMount)) {
            $mounted = true;
            break;
        }
        
        $retryCount++;
    }
    
    if (!$mounted) {
        if ($mountPointCreated) {
            execCmd("rmdir \"{$mountPoint}\" 2>/dev/null", true, 3);
        }
        writeError("CIFS mount failed after {$maxRetries} attempts: {$output}");
        return ['success' => false, 'error' => $lang4548 . ($output ?: 'Unknown error')];
    }
    
    execCmd("touch \"{$mountPoint}/.mount_created_by_mount_master\"", true, 3);
    
    if ($addToFstab) {
        writeLog("Adding CIFS share to fstab: {$source} -> {$mountPoint}");
        
        $fstabOptions = "_netdev,x-systemd.automount,x-systemd.mount-timeout=30,x-systemd.requires=network-online.target,uid={$uidNum},gid={$gidNum},file_mode=0777,dir_mode=0777,noperm,nounix,sec=ntlmssp";
        
        if ($credFile && file_exists($credFile)) {
            $fstabOptions .= ",credentials={$credFile}";
            writeLog("Using credentials file in fstab: {$credFile}");
        } elseif (!empty($options['username']) && !empty($options['password'])) {
            $fstabOptions .= ",username={$options['username']},password={$options['password']}";
        }
        
        if (!empty($options['domain'])) {
            $fstabOptions .= ",domain={$options['domain']}";
        }
        
        if (!empty($options['vers'])) {
            $fstabOptions .= ",vers={$options['vers']}";
        }
        
        if (!empty($options['iocharset'])) {
            $fstabOptions .= ",iocharset={$options['iocharset']}";
        }
        
        addToFstab($source, $mountPoint, 'cifs', $fstabOptions, 0, 0);
        writeLog("Added to fstab with options: {$fstabOptions}");
        
        execCmd("systemctl daemon-reload", true, 5);
    }
    
    writeLog("CIFS share mounted successfully at {$mountPoint}");
    
    return [
        'success' => true,
        'mount_point' => $mountPoint,
        'mount_point_created' => $mountPointCreated,
        'mount_options' => $mountOptions,
        'credentials_file' => $credFile
    ];
}

function createCifsCredentialsFile($server, $share, $username, $password, $domain = '') {
    $credDir = '/var/www/minib/creds';
    
    if (!is_dir($credDir)) {
        mkdir($credDir, 0755, true);
    }
    
    $credFile = $credDir . '/credentials.' . md5($server . $share . $username);
    
    $credContent = "username={$username}\n";
    $credContent .= "password={$password}\n";
    if (!empty($domain)) {
        $credContent .= "domain={$domain}\n";
    }
    
    file_put_contents($credFile, $credContent);
    
    chmod($credFile, 0600);
    @chown($credFile, 'root');
    @chgrp($credFile, 'root');
    
    writeLog("Created CIFS credentials file: {$credFile}");
    return $credFile;
}

function removeCifsCredentialsFile($server, $share, $username) {
    $credFile = '/var/www/minib/creds/credentials.' . md5($server . $share . $username);
    if (file_exists($credFile)) {
        unlink($credFile);
        writeLog("Removed CIFS credentials file: {$credFile}");
        return true;
    }
    return false;
}

function mountNfsShare($server, $export, $mountPoint, $options = [], $addToFstab = false) {
	global $lang4549, $lang4550, $lang4551;
    writeLog("Mounting NFS share: {$server}:{$export} -> {$mountPoint}");
    
    $source = "{$server}:{$export}";
    
    $mountCheck = execCmd("mount | grep '{$source} '", true, 5);
    if (!empty($mountCheck)) {
        return ['success' => false, 'error' => $lang4549];
    }
    
    $mountPointCreated = false;
    if (!is_dir($mountPoint)) {
        $parentDir = dirname($mountPoint);
        if (!is_dir($parentDir) && $parentDir !== '/') {
            execCmd("mkdir -p \"{$parentDir}\"", true, 5);
        }
        
        $result = execCmd("mkdir -p \"{$mountPoint}\"", true, 5);
        if (!empty($result) && strpos($result, 'cannot create') !== false) {
            return ['success' => false, 'error' => $lang4550 . $result];
        }
        $mountPointCreated = true;
    }
    
    $mountOptions = $options['mount_options'] ?? 'defaults';
    
    if (!empty($options['nfs_version'])) {
        $mountOptions .= ",vers={$options['nfs_version']}";
    }
    
    if (!empty($options['rsize'])) {
        $mountOptions .= ",rsize={$options['rsize']}";
    }
    
    if (!empty($options['wsize'])) {
        $mountOptions .= ",wsize={$options['wsize']}";
    }
    
    if (!empty($options['soft_hard']) && $options['soft_hard'] === 'soft') {
        $mountOptions .= ",soft";
    }
    
    if (!empty($options['intr'])) {
        $mountOptions .= ",intr";
    }
    
    if (!empty($options['noatime'])) {
        $mountOptions .= ",noatime";
    }
    
    $cmd = "mount -t nfs -o {$mountOptions} \"{$source}\" \"{$mountPoint}\" 2>&1";
    $output = execCmd($cmd, true, 30);
    
    sleep(1);
    $checkMount = execCmd("mount | grep '{$source} '", true, 5);
    
    if (empty($checkMount)) {
        if ($mountPointCreated) {
            execCmd("rmdir \"{$mountPoint}\" 2>/dev/null", true, 3);
        }
        return ['success' => false, 'error' => $lang4551 . ($output ?: 'Unknown error')];
    }
    
    execCmd("touch \"{$mountPoint}/.mount_created_by_mount_master\"", true, 3);
    
    if ($addToFstab) {
        addToFstab($source, $mountPoint, 'nfs', $mountOptions . ',_netdev');
    }
    
    writeLog("NFS share mounted successfully at {$mountPoint}");
    
    return [
        'success' => true,
        'mount_point' => $mountPoint,
        'mount_point_created' => $mountPointCreated
    ];
}

function unmountPoint($mountPoint, $force = false, $removeFromFstab = false) {
	global $lang4552, $lang4553, $lang4554, $lang4555;
    writeLog("Unmounting: {$mountPoint}, force: " . ($force ? 'true' : 'false'));
    
    $mountCheck = execCmd("cat /proc/mounts | grep ' {$mountPoint} '", true, 5, true);
    if (empty($mountCheck)) {
        return ['success' => false, 'error' => $lang4552 . $mountPoint];
    }
    
    if (preg_match('/^(\S+)\s+on\s+(\S+)\s+type/', $mountCheck, $matches)) {
        $device = $matches[1];
    } else {
        $device = explode(' ', $mountCheck)[0];
    }
    
    $wasManaged = checkMountPointCreatedByUs($mountPoint);
    
    if ($removeFromFstab) {
        removeFromFstab($mountPoint);
    }
    
    if ($force) {
        $output = execCmd("umount -l \"{$mountPoint}\" 2>&1", true, 10);
    } else {
        $output = execCmd("umount \"{$mountPoint}\" 2>&1", true, 10, true);

    }
    
    sleep(1);
    
    $stillMounted = execCmd("mount | grep ' {$mountPoint} '", true, 5);
    
    if (!empty($stillMounted)) {
        if (!$force) {
            writeLog("Normal unmount failed, trying force...");
            $output = execCmd("umount -l \"{$mountPoint}\" 2>&1", true, 10);
            sleep(1);
            $stillMounted = execCmd("mount | grep ' {$mountPoint} '", true, 5);
            
            if (!empty($stillMounted)) {
                return ['success' => false, 'error' => $lang4553 . ($output ?: 'Unknown error')];
            }
        } else {
            return ['success' => false, 'error' => $lang4554 . ($output ?: 'Unknown error')];
        }
    }
    
    if ($wasManaged && is_dir($mountPoint)) {
        $markerFile = $mountPoint . '/.mount_created_by_mount_master';
        $wasCreatedByUs = file_exists($markerFile);
        
        if ($wasCreatedByUs) {
            execCmd("rm -f \"{$markerFile}\"", true, 3);
            
            $dirContent = scandir($mountPoint);
            if ($dirContent !== false) {
                $content = array_diff($dirContent, ['.', '..']);
                if (empty($content)) {
                    execCmd("rmdir \"{$mountPoint}\" 2>/dev/null", true, 3);
                    writeLog("Removed empty mount point: {$mountPoint}");
                } else {
                    writeLog("Mount point not empty, keeping: {$mountPoint}");
                }
            }
        }
    }
    
    writeLog("Successfully unmounted {$mountPoint}");
    
    return ['success' => true, 'message' => $lang4555, 'was_managed' => $wasManaged];
}

function browseDirectory($path) {
	global $lang4556, $lang4557, $lang4558, $lang4559;
    if (empty($path) || $path === '' || $path === '/') {
        $path = '/';
    }
    
    $path = '/' . ltrim($path, '/');
    $path = str_replace('//', '/', $path);
    
    if ($path[0] !== '/') {
        $path = '/' . $path;
    }
    
    $realPath = realpath($path);
    
    if ($realPath === false) {
        if (is_dir($path)) {
            $realPath = $path;
        } else {
            $testPath = $path;
            while ($testPath !== '/' && $testPath !== '') {
                $testPath = dirname($testPath);
                if ($testPath === '' || $testPath === '.') $testPath = '/';
                if (is_dir($testPath)) {
                    $realPath = $testPath;
                    break;
                }
            }
            if ($realPath === false) {
                $realPath = '/';
            }
        }
    }
    
    if (!is_dir($realPath)) {
        return ['success' => false, 'error' => $lang4556 . $realPath];
    }
    
    if (!is_readable($realPath)) {
        return ['success' => false, 'error' => $lang4557 . $realPath];
    }
    
    $items = [];
    
    try {
        $dirs = scandir($realPath);
        if ($dirs === false) {
            return ['success' => false, 'error' => $lang4558 . $realPath];
        }
        
        foreach ($dirs as $item) {
            if ($item === '.' || $item === '..') continue;
            
            $fullPath = $realPath . '/' . $item;
            
            if (is_dir($fullPath) && !is_link($fullPath)) {
                $isReadable = is_readable($fullPath);
                $items[] = [
                    'name' => $item,
                    'path' => $fullPath,
                    'type' => 'dir',
                    'readable' => $isReadable
                ];
            }
        }
        
        usort($items, function($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $lang4559 . $e->getMessage()];
    }
    
    $parentPath = '/';
    if ($realPath !== '/') {
        $parentPath = dirname($realPath);
        if ($parentPath === '' || $parentPath === '.') $parentPath = '/';
    }
    
    return [
        'success' => true,
        'path' => $realPath,
        'parent' => $parentPath,
        'items' => $items,
        'is_readable' => is_readable($realPath),
        'is_writable' => is_writable($realPath)
    ];
}

function getRootDirectories() {
    $roots = [];
    
    $rootPath = '/';
    $dirs = scandir($rootPath);
    
    if ($dirs) {
        foreach ($dirs as $item) {
            if ($item === '.' || $item === '..') continue;
            $fullPath = $rootPath . $item;
            if (is_dir($fullPath)) {
                $roots[] = [
                    'name' => $item,
                    'path' => $fullPath,
                    'type' => 'dir'
                ];
            }
        }
    }
    
    $systemPaths = ['/mnt', '/media', '/srv', '/var', '/home', '/root', '/opt', '/usr/local'];
    foreach ($systemPaths as $sysPath) {
        if (is_dir($sysPath) && !in_array($sysPath, array_column($roots, 'path'))) {
            $roots[] = [
                'name' => basename($sysPath),
                'path' => $sysPath,
                'type' => 'dir'
            ];
        }
    }
    
    usort($roots, function($a, $b) {
        return strcasecmp($a['name'], $b['name']);
    });
    
    return $roots;
}

function createDirectory($path, $name) {
	global $lang4560, $lang4561;
    $newPath = rtrim($path, '/') . '/' . $name;
    
    if (file_exists($newPath)) {
        return ['success' => false, 'error' => $lang4560];
    }
    
    $result = mkdir($newPath, 0777, true);
    if ($result) {
        execCmd("chmod 777 \"{$newPath}\"", true, 3);
        return ['success' => true, 'path' => $newPath];
    } else {
        return ['success' => false, 'error' => $lang4561];
    }
}

function getLogs($lines = 100) {
    $logs = [];
    if (file_exists(LOG_FILE)) {
        $content = file(LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($content) {
            $content = array_reverse($content);
            $content = array_slice($content, 0, $lines);
            $logs = array_reverse($content);
        }
    }
    return $logs;
}

function clearLogs() {
    if (file_exists(LOG_FILE)) {
        file_put_contents(LOG_FILE, '');
    }
    if (file_exists(ERROR_LOG_FILE)) {
        file_put_contents(ERROR_LOG_FILE, '');
    }
    return true;
}

// ==================== ОБРАБОТЧИК ЗАПРОСОВ ====================

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$input = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $json = file_get_contents('php://input');
    if ($json) {
        $input = json_decode($json, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $action = $input['action'] ?? $action;
        } else {
            $input = $_POST;
        }
    } else {
        $input = $_POST;
    }
}

$response = ['success' => false, 'error' => 'Unknown action'];

try {
    switch ($action) {
        case 'get_all':
            $response = [
                'success' => true,
                'mounted' => getMountEntries(),
                'fstab_entries' => getFstabEntries(),
                'available_devices' => getAvailableDevices(),
                'system_users' => getSystemUsers()
            ];
            break;
            
        case 'get_mounted':
            $response = ['success' => true, 'mounted' => getMountEntries()];
            break;
            
        case 'get_available_devices':
            $response = ['success' => true, 'devices' => getAvailableDevices()];
            break;
            
        case 'get_fstab':
            $response = ['success' => true, 'entries' => getFstabEntries()];
            break;
            
        case 'mount_local':
			global $lang4562;
            $device = $input['device'] ?? '';
            $mountPoint = $input['mount_point'] ?? '';
            $addToFstab = filter_var($input['add_to_fstab'] ?? false, FILTER_VALIDATE_BOOLEAN);
            
            if (empty($device) || empty($mountPoint)) {
                $response = ['success' => false, 'error' => $lang4562];
                break;
            }
            
            $options = [
                'fstype' => $input['fstype'] ?? 'auto',
                'uid' => $input['uid'] ?? 'www-data',
                'gid' => $input['gid'] ?? 'www-data',
                'mount_options' => $input['mount_options'] ?? 'defaults'
            ];
            
            $response = mountLocalDevice($device, $mountPoint, $options, $addToFstab);
            break;
            
        case 'mount_cifs':
			global $lang4563;
            $server = $input['server'] ?? '';
            $share = $input['share'] ?? '';
            $mountPoint = $input['mount_point'] ?? '';
            $addToFstab = filter_var($input['add_to_fstab'] ?? false, FILTER_VALIDATE_BOOLEAN);
            
            if (empty($server) || empty($share) || empty($mountPoint)) {
                $response = ['success' => false, 'error' => $lang4563];
                break;
            }
            
            $options = [
                'uid' => $input['uid'] ?? 'www-data',
                'gid' => $input['gid'] ?? 'www-data',
                'username' => $input['username'] ?? '',
                'password' => $input['password'] ?? '',
                'domain' => $input['domain'] ?? '',
                'vers' => $input['vers'] ?? '3.0',
                'iocharset' => $input['iocharset'] ?? 'utf8'
            ];
            
            $response = mountCifsShare($server, $share, $mountPoint, $options, $addToFstab);
            break;
            
        case 'mount_nfs':
			global $lang4564;
            $server = $input['server'] ?? '';
            $export = $input['export'] ?? '';
            $mountPoint = $input['mount_point'] ?? '';
            $addToFstab = filter_var($input['add_to_fstab'] ?? false, FILTER_VALIDATE_BOOLEAN);
            
            if (empty($server) || empty($export) || empty($mountPoint)) {
                $response = ['success' => false, 'error' => $lang4564];
                break;
            }
            
            $options = [
                'mount_options' => $input['mount_options'] ?? 'defaults',
                'nfs_version' => $input['nfs_version'] ?? '4.2',
                'rsize' => $input['rsize'] ?? '',
                'wsize' => $input['wsize'] ?? '',
                'soft_hard' => $input['soft_hard'] ?? 'hard',
                'intr' => filter_var($input['intr'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'noatime' => filter_var($input['noatime'] ?? false, FILTER_VALIDATE_BOOLEAN)
            ];
            
            $response = mountNfsShare($server, $export, $mountPoint, $options, $addToFstab);
            break;
            
        case 'unmount':
			global $lang4565;
            $mountPoint = $input['mount_point'] ?? '';
            $force = filter_var($input['force'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $removeFromFstab = filter_var($input['remove_from_fstab'] ?? false, FILTER_VALIDATE_BOOLEAN);
            
            if (empty($mountPoint)) {
                $response = ['success' => false, 'error' => $lang4565];
                break;
            }
            
            $response = unmountPoint($mountPoint, $force, $removeFromFstab);
            break;
            
        case 'add_to_fstab':
			global $lang4566;
            $device = $input['device'] ?? '';
            $mountPoint = $input['mount_point'] ?? '';
            $fstype = $input['fstype'] ?? 'ext4';
            $options = $input['options'] ?? 'defaults';
            
            if (empty($device) || empty($mountPoint)) {
                $response = ['success' => false, 'error' => $lang4566];
                break;
            }
            
            $response = addToFstab($device, $mountPoint, $fstype, $options);
            break;
            
        case 'remove_from_fstab':
			global $lang4567;
            $mountPoint = $input['mount_point'] ?? '';
            
            if (empty($mountPoint)) {
                $response = ['success' => false, 'error' => $lang4567];
                break;
            }
            
            $response = ['success' => removeFromFstab($mountPoint)];
            break;
            
        case 'change_mount_point':
			global $lang4568, $lang4569, $lang4570, $lang4571;
            $oldMountPoint = $input['old_mount_point'] ?? '';
            $newMountPoint = $input['new_mount_point'] ?? '';
            $updateFstab = filter_var($input['update_fstab'] ?? false, FILTER_VALIDATE_BOOLEAN);
            
            if (empty($oldMountPoint) || empty($newMountPoint)) {
                $response = ['success' => false, 'error' => $lang4568];
                break;
            }
            
            $mountCheck = execCmd("cat /proc/mounts | grep ' {$oldMountPoint} '", true, 5, true);
            if (empty($mountCheck)) {
                $response = ['success' => false, 'error' => $lang4569 . $oldMountPoint];
                break;
            }
            
            if (preg_match('/^(\S+)\s+on\s+(\S+)\s+type\s+(\S+)\s+\((.+)\)/', $mountCheck, $matches)) {
                $device = $matches[1];
                $fstype = $matches[3];
                $options = $matches[4];
            } else {
                $parts = explode(' ', $mountCheck);
                $device = $parts[0];
                $fstype = $parts[4] ?? 'auto';
                $options = '';
            }
            
            $wasManaged = checkMountPointCreatedByUs($oldMountPoint);
            $markerContent = null;
            $markerFile = $oldMountPoint . '/.mount_created_by_mount_master';
            if (file_exists($markerFile)) {
                $markerContent = file_get_contents($markerFile);
            }
            
            if (!is_dir($newMountPoint)) {
                $parentDir = dirname($newMountPoint);
                if (!is_dir($parentDir) && $parentDir !== '/') {
                    execCmd("mkdir -p \"{$parentDir}\"", true, 5);
                }
                execCmd("mkdir -p \"{$newMountPoint}\"", true, 5);
            }
            
            $remountCmd = "mount --bind \"{$oldMountPoint}\" \"{$newMountPoint}\" 2>&1";
            $output = execCmd($remountCmd, true, 10);
            
            if (strpos($output, 'Error') !== false || strpos($output, 'error') !== false) {
                $response = ['success' => false, 'error' => $lang4570 . $output];
                break;
            }
            
            execCmd("umount \"{$oldMountPoint}\" 2>&1", true, 10);
            
            if ($wasManaged) {
                execCmd("touch \"{$newMountPoint}/.mount_created_by_mount_master\"", true, 3);
                execCmd("rm -f \"{$markerFile}\"", true, 3);
                
                $dirContent = scandir($oldMountPoint);
                if ($dirContent !== false) {
                    $content = array_diff($dirContent, ['.', '..']);
                    if (empty($content)) {
                        execCmd("rmdir \"{$oldMountPoint}\" 2>/dev/null", true, 3);
                    }
                }
            }
            
            if ($updateFstab) {
                removeFromFstab($oldMountPoint);
                addToFstab($device, $newMountPoint, $fstype, $options);
            }
            
            $response = ['success' => true, 'message' => $lang4571];
            break;
            
        case 'browse':
			$path = $input['path'] ?? $_GET['path'] ?? '/';
			$path = urldecode($path);
			$path = '/' . ltrim($path, '/');
			$response = browseDirectory($path);
			break;
            
        case 'create_folder':
			global $lang4572;
            $path = $input['path'] ?? '';
            $name = $input['name'] ?? '';
            
            if (empty($path) || empty($name)) {
                $response = ['success' => false, 'error' => $lang4572];
                break;
            }
            
            $response = createDirectory($path, $name);
            break;
            
        case 'get_logs':
            $lines = (int)($input['lines'] ?? 100);
            $response = ['success' => true, 'logs' => getLogs($lines)];
            break;
            
        case 'clear_logs':
            $response = ['success' => clearLogs()];
            break;
        
		case 'get_root_dirs':
    $response = ['success' => true, 'directories' => getRootDirectories()];
    break;
		
        default:
            $response = ['success' => false, 'error' => "Unknown action: {$action}"];
    }
} catch (Exception $e) {
    writeError("Exception: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
    $response = ['success' => false, 'error' => $e->getMessage()];
}

writeLog("Response for action {$action}: " . json_encode(['success' => $response['success'] ?? false]));
echo json_encode($response, JSON_UNESCAPED_UNICODE);