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

function execCmd($cmd, $sudo = true, $timeout = 60) {
    $fullCmd = $sudo ? "sudo " . $cmd : $cmd;
    $fullCmd .= " 2>&1";
    
    $descriptorspec = [
        0 => ["pipe", "r"],
        1 => ["pipe", "w"],
        2 => ["pipe", "w"]
    ];
    
    $process = proc_open($fullCmd, $descriptorspec, $pipes);
    
    if (!is_resource($process)) {
        return '';
    }
    
    $start = time();
    $output = '';
    
    while (true) {
        $read = [$pipes[1]];
        $write = null;
        $except = null;
        $streams = stream_select($read, $write, $except, 0, 200000);
        
        if ($streams > 0) {
            $output .= fread($pipes[1], 8192);
        }
        
        $status = proc_get_status($process);
        if (!$status['running']) {
            break;
        }
        
        if (time() - $start > $timeout) {
            proc_terminate($process, 9);
            break;
        }
        
        usleep(10000);
    }
    
    fclose($pipes[0]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);
    
    return trim($output);
}

function parseSize($sizeStr) {
    if (empty($sizeStr)) return 0;
    $sizeStr = trim($sizeStr);
    $sizeStr = str_replace('B', '', $sizeStr);
    $sizeStr = str_replace(' ', '', $sizeStr);
    
    if (is_numeric($sizeStr)) return (float)$sizeStr;
    
    if (preg_match('/(\d+(?:\.\d+)?)\s*([KMGTP]?)/i', $sizeStr, $matches)) {
        $num = floatval($matches[1]);
        $unit = strtoupper($matches[2]);
        switch($unit) {
            case 'K': return $num * 1024;
            case 'M': return $num * 1024 * 1024;
            case 'G': return $num * 1024 * 1024 * 1024;
            case 'T': return $num * 1024 * 1024 * 1024 * 1024;
            default: return $num;
        }
    }
    return 0;
}

function formatSize($bytes) {
    if ($bytes >= 1099511627776) return round($bytes / 1099511627776, 2) . ' TB';
    if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return round($bytes / 1024, 2) . ' KB';
    return $bytes . ' B';
}

function getAllDisks() {
    $disks = [];
    $rootDisk = getRootDisk();
    
    $lsblkJson = execCmd("lsblk -J -o NAME,TYPE,SIZE,MODEL,RM,RO,ROTA,STATE 2>/dev/null", true, 10);
    
    $allPVs = getAllPVs();
    $pvPaths = [];
    foreach ($allPVs as $pv) {
        $pvPaths[] = $pv['name'];
    }
    
    if (!empty($lsblkJson)) {
        $data = json_decode($lsblkJson, true);
        if ($data && isset($data['blockdevices'])) {
            foreach ($data['blockdevices'] as $device) {
                if ($device['type'] !== 'disk') continue;
                
                $diskName = $device['name'];
                if (strpos($diskName, 'mtd') !== false || strpos($diskName, 'loop') === 0) continue;
                
                $size = $device['size'] ?? '0';
                $model = $device['model'] ?? '';
                $removable = ($device['rm'] ?? '0') == '1';
                $isSystem = ($diskName === $rootDisk);
                
                $partitions = getPartitions($diskName, $pvPaths);
                
                $tableType = null;
                $partedOut = execCmd("parted /dev/{$diskName} print 2>/dev/null | grep 'Partition Table'", true, 5);
                if (!empty($partedOut)) {
                    if (strpos($partedOut, 'gpt') !== false) $tableType = 'gpt';
                    elseif (strpos($partedOut, 'msdos') !== false) $tableType = 'mbr';
                }
                
                $disks[] = [
                    'name' => $diskName,
                    'path' => '/dev/' . $diskName,
                    'size_bytes' => parseSize($size),
                    'size_formatted' => $size,
                    'model' => $model ?: 'Unknown',
                    'removable' => $removable,
                    'is_system' => $isSystem,
                    'partition_table_type' => $tableType,
                    'partitions' => $partitions
                ];
            }
        }
    }
    
    return $disks;
}

function getPartitions($diskName, $pvPaths = []) {
    $partitions = [];
    
    $lsblkJson = execCmd("lsblk -J -o NAME,SIZE,FSTYPE,MOUNTPOINT,LABEL,UUID /dev/{$diskName} 2>/dev/null", true, 10);
    
    if (!empty($lsblkJson)) {
        $data = json_decode($lsblkJson, true);
        if ($data && isset($data['blockdevices'][0]['children'])) {
            foreach ($data['blockdevices'][0]['children'] as $part) {
                $size = $part['size'] ?? '0';
                $fstype = $part['fstype'] ?? '';
                $mountPoint = $part['mountpoint'] ?? '';
                $partPath = '/dev/' . $part['name'];
                
                $isPV = in_array($partPath, $pvPaths);
                
                $isLV = false;
                $lvInfo = null;
                
                if (strpos($fstype, 'LVM2_member') !== false) {
                    $isPV = true;
                }
                
                if (strpos($part['name'], '--') !== false || strpos($part['name'], '-') !== false) {
                    $lvCheck = execCmd("lvs /dev/" . $part['name'] . " 2>/dev/null", true, 5);
                    if (!empty($lvCheck)) {
                        $isLV = true;
                    }
                }
                
                $partition = [
                    'name' => $part['name'],
                    'path' => $partPath,
                    'size_bytes' => parseSize($size),
                    'size_formatted' => $size,
                    'fstype' => $fstype ?: null,
                    'mount_point' => $mountPoint ?: null,
                    'label' => $part['label'] ?? null,
                    'uuid' => $part['uuid'] ?? null,
                    'is_pv' => $isPV,
                    'is_lv' => $isLV
                ];
                
                $partitions[] = $partition;
            }
        }
    }
    
    return $partitions;
}

function getRootDisk() {
    $rootDev = execCmd("df / | tail -1 | awk '{print $1}'", true, 5);
    $rootDev = preg_replace('/[0-9]+$/', '', $rootDev);
    $rootDev = preg_replace('/p\d+$/', '', $rootDev);
    $rootDev = str_replace('/dev/', '', $rootDev);
    return trim($rootDev);
}


function getAllPVs() {
    $pvs = [];
    
    $cmd = "pvs --noheadings --units g -o pv_name,pv_size,pv_used,vg_name 2>/dev/null";
    $result = execCmd($cmd, true, 10);
    
    if (!empty($result)) {
        $lines = explode("\n", trim($result));
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            $parts = preg_split('/\s+/', $line);
            if (count($parts) >= 3) {
                $size = floatval($parts[1]) * 1024 * 1024 * 1024;
                $used = isset($parts[2]) ? floatval($parts[2]) * 1024 * 1024 * 1024 : 0;
                $vgName = isset($parts[3]) ? trim($parts[3]) : '';
                
                $pvs[] = [
                    'name' => $parts[0],
                    'size' => $size,
                    'size_formatted' => $parts[1] . ' GB',
                    'used' => $used,
                    'used_formatted' => round($used / (1024*1024*1024), 2) . ' GB',
                    'free' => $size - $used,
                    'free_formatted' => round(($size - $used) / (1024*1024*1024), 2) . ' GB',
                    'vg_name' => ($vgName !== '' && $vgName !== ' ') ? $vgName : null
                ];
            }
        }
    }
    
    return $pvs;
}

function getAllVGs() {
    $vgs = [];
    
    $cmd = "vgs --noheadings --units g -o vg_name,vg_size,vg_free,vg_attr,pv_count,lv_count 2>/dev/null";
    $result = execCmd($cmd, true, 10);
    
    if (!empty($result)) {
        $lines = explode("\n", trim($result));
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            $parts = preg_split('/\s+/', $line);
            if (count($parts) >= 6) {
                $size = floatval($parts[1]) * 1024 * 1024 * 1024;
                $free = floatval($parts[2]) * 1024 * 1024 * 1024;
                $vgs[] = [
                    'name' => $parts[0],
                    'size' => $size,
                    'size_formatted' => $parts[1] . ' GB',
                    'free' => $free,
                    'free_formatted' => $parts[2] . ' GB',
                    'used' => $size - $free,
                    'used_formatted' => round(($size - $free) / (1024*1024*1024), 2) . ' GB',
                    'used_percent' => $size > 0 ? round(($size - $free) / $size * 100, 1) : 0,
                    'attr' => isset($parts[3]) ? $parts[3] : '',
                    'pv_count' => isset($parts[4]) ? intval($parts[4]) : 0,
                    'lv_count' => isset($parts[5]) ? intval($parts[5]) : 0,
                    'pe_size' => '4.00 MB'
                ];
            }
        }
    }
    
    return $vgs;
}

function getAllLVs() {
    $lvs = [];
    
    $cmd = "lvs --noheadings --units g -o lv_name,vg_name,lv_size,lv_path,lv_attr 2>/dev/null";
    $result = execCmd($cmd, true, 10);
    
    if (!empty($result)) {
        $lines = explode("\n", trim($result));
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            $parts = preg_split('/\s+/', $line);
            if (count($parts) >= 5) {
                $lvPath = $parts[3];
                $mapperPath = "/dev/mapper/" . str_replace('-', '--', $parts[0]);
                $vgName = $parts[1];
                $lvName = $parts[0];
                
                $mountPoint = null;
                
                $mountsOutput = execCmd("nsenter -t 1 -m cat /proc/mounts 2>/dev/null", true, 5);
                if (!empty($mountsOutput)) {
                    $mountLines = explode("\n", trim($mountsOutput));
                    foreach ($mountLines as $mountLine) {
                        if (empty(trim($mountLine))) continue;
                        $mountParts = preg_split('/\s+/', trim($mountLine));
                        if (count($mountParts) >= 2) {
                            $device = $mountParts[0];
                            $mount = $mountParts[1];
                            
                            if ($device === $lvPath || 
                                $device === $mapperPath ||
                                $device === "/dev/mapper/{$vgName}-{$lvName}" ||
                                $device === "/dev/{$vgName}/{$lvName}" ||
                                $device === $mapperPath . " " ||  
                                strpos($device, $mapperPath) === 0) {
                                $mountPoint = $mount;
                                break;
                            }
                        }
                    }
                }
                
                if (empty($mountPoint)) {
                    $findmnt = execCmd("nsenter -t 1 -m findmnt -n -o TARGET " . escapeshellarg($mapperPath) . " 2>/dev/null", true, 5);
                    if (!empty($findmnt)) {
                        $mountPoint = trim($findmnt);
                    }
                }
                
                if (empty($mountPoint)) {
                    $findmnt2 = execCmd("nsenter -t 1 -m findmnt -n -o TARGET " . escapeshellarg($lvPath) . " 2>/dev/null", true, 5);
                    if (!empty($findmnt2)) {
                        $mountPoint = trim($findmnt2);
                    }
                }
                
                $sizeCmd = execCmd("lvs --noheadings --units b -o lv_size " . escapeshellarg($lvName) . " 2>/dev/null", true, 5);
                $sizeBytes = 0;
                if (!empty($sizeCmd)) {
                    $sizeBytes = (int)str_replace('B', '', trim($sizeCmd));
                }
                
                $isActive = (strpos($parts[4], 'a') !== false);
                
                $fsType = '';
                $hasFilesystem = false;
                
                $blkidCheck = execCmd("sudo blkid -s TYPE -o value " . escapeshellarg($lvPath) . " 2>/dev/null", true, 5);
                if (!empty($blkidCheck)) {
                    $fsType = trim($blkidCheck);
                    $hasFilesystem = true;
                } else {
                    $lsblkCheck = execCmd("lsblk -n -o FSTYPE " . escapeshellarg($lvPath) . " 2>/dev/null", true, 5);
                    if (!empty($lsblkCheck)) {
                        $fsType = trim($lsblkCheck);
                        $hasFilesystem = true;
                    }
                }
                
                $lvs[] = [
                    'name' => $lvName,
                    'vg_name' => $vgName,
                    'size' => $sizeBytes,
                    'size_formatted' => $parts[2] . ' GB',
                    'path' => $lvPath,
                    'mapper_path' => $mapperPath,
                    'attr' => $parts[4],
                    'is_active' => $isActive,
                    'mount_point' => $mountPoint,
                    'has_filesystem' => $hasFilesystem,
                    'filesystem' => $fsType ?: null
                ];
            }
        }
    }
    
    return $lvs;
}

function getAvailablePVs() {
    $available = [];
    $allPVs = getAllPVs();
    
    foreach ($allPVs as $pv) {
        if ($pv['vg_name'] === null) {
            $available[] = [
                'name' => $pv['name'],
                'size_formatted' => $pv['size_formatted']
            ];
        }
    }
    
    return $available;
}

function getPVsInVG($vgName) {
    $pvs = [];
    $allPVs = getAllPVs();
    
    foreach ($allPVs as $pv) {
        if ($pv['vg_name'] === $vgName) {
            $pvs[] = $pv;
        }
    }
    
    return $pvs;
}

function getAllRawDevices() {
    $devices = [];
    $disks = getAllDisks();
    
    foreach ($disks as $disk) {
        $devices[] = [
            'name' => $disk['path'],
            'size_formatted' => $disk['size_formatted'],
            'type' => 'disk'
        ];
        
        foreach ($disk['partitions'] as $part) {
            $devices[] = [
                'name' => $part['path'],
                'size_formatted' => $part['size_formatted'],
                'type' => 'partition'
            ];
        }
    }
    
    return $devices;
}

function getLVInfo($lvPath) {
    $lvPath = preg_replace('/^\/dev\//', '', $lvPath);
    
    $allLVs = getAllLVs();
    foreach ($allLVs as $lv) {
        if ($lv['path'] === '/dev/' . $lvPath || $lv['mapper_path'] === '/dev/' . $lvPath) {
            return $lv;
        }
    }
    
    $info = [];
    
    $lvsInfo = execCmd("lvs --noheadings --units b -o lv_name,vg_name,lv_size,lv_path,lv_attr " . escapeshellarg('/dev/' . $lvPath) . " 2>/dev/null", true, 10);
    if (!empty($lvsInfo)) {
        $parts = preg_split('/\s+/', trim($lvsInfo));
        if (count($parts) >= 5) {
            $sizeBytes = (int)str_replace('B', '', $parts[2]);
            $info = [
                'name' => $parts[0],
                'vg_name' => $parts[1],
                'path' => $parts[3],
                'size_bytes' => $sizeBytes,
                'size_formatted' => formatSize($sizeBytes),
                'is_active' => strpos($parts[4], 'a') !== false,
                'mount_point' => null,
                'filesystem' => null,
                'has_filesystem' => false
            ];
            
            $mountsOutput = execCmd("nsenter -t 1 -m cat /proc/mounts 2>/dev/null | grep -E '^" . preg_quote($parts[3], '/') . "\\s' | awk '{print $2}'", true, 5);
            if (!empty($mountsOutput)) {
                $info['mount_point'] = trim($mountsOutput);
            }
            
            $fsType = execCmd("blkid -s TYPE -o value " . escapeshellarg($parts[3]) . " 2>/dev/null", true, 5);
            if (!empty($fsType)) {
                $info['filesystem'] = trim($fsType);
                $info['has_filesystem'] = true;
            }
            
            return $info;
        }
    }
    
    return null;
}

function getPartitionInfo($partition) {
    $partition = preg_replace('/^\/dev\//', '', $partition);
    $info = [];
    
    $lsblkJson = execCmd("lsblk -J -o NAME,SIZE,FSTYPE,MOUNTPOINT,LABEL,UUID /dev/{$partition} 2>/dev/null", true, 10);
    
    if (!empty($lsblkJson)) {
        $data = json_decode($lsblkJson, true);
        if ($data && isset($data['blockdevices'][0])) {
            $dev = $data['blockdevices'][0];
            $info = [
                'name' => $dev['name'],
                'path' => '/dev/' . $dev['name'],
                'size' => $dev['size'] ?? '0',
                'size_bytes' => parseSize($dev['size'] ?? '0'),
                'fstype' => $dev['fstype'] ?? null,
                'mount_point' => $dev['mountpoint'] ?? null,
                'label' => $dev['label'] ?? null,
                'uuid' => $dev['uuid'] ?? null
            ];
        }
    }
    
    $allPVs = getAllPVs();
    $info['is_pv'] = false;
    $info['vg_name'] = null;
    
    foreach ($allPVs as $pv) {
        if ($pv['name'] === '/dev/' . $partition) {
            $info['is_pv'] = true;
            $info['vg_name'] = $pv['vg_name'];
            break;
        }
    }
    
    return $info;
}

function getDiskInfo($disk) {
    $disks = getAllDisks();
    foreach ($disks as $d) {
        if ($d['name'] === $disk) {
            return $d;
        }
    }
    return null;
}

// ==================== LVM ОПЕРАЦИИ ====================

function createPV($device) {
	global $lang2080, $lang2081;
    $checkDevice = execCmd("lsblk " . escapeshellarg($device) . " 2>/dev/null", true, 5);
    if (empty($checkDevice)) {
        return ['success' => false, 'error' => $lang2080 . $device . $lang2081];
    }
    
    $result = execCmd("pvcreate -f " . escapeshellarg($device), true, 30);
    if (strpos($result, 'successfully created') !== false || strpos($result, 'Physical Volume') !== false) {
        execCmd("vgscan 2>/dev/null", true, 5);
        return ['success' => true];
    }
    return ['success' => false, 'error' => $result];
}

function deletePV($pvName) {
	global $lang2082, $lang2083, $lang2084;
    $checkVg = execCmd("pvs --noheadings -o vg_name " . escapeshellarg($pvName) . " 2>/dev/null", true, 5);
    $vgName = trim($checkVg);
    
    if (!empty($vgName) && $vgName !== '') {
        return ['success' => false, 'error' => $lang2082 . $pvName . $lang2083 . $vgName . $lang2084];
    }
    
    $result = execCmd("pvremove -f " . escapeshellarg($pvName), true, 30);
    if (strpos($result, 'successfully removed') !== false || strpos($result, 'Labels on physical volume') !== false) {
        return ['success' => true];
    }
    return ['success' => false, 'error' => $result];
}

function createVG($vgName, $devices) {
    $cmd = "vgcreate " . escapeshellarg($vgName) . " " . implode(' ', array_map('escapeshellarg', $devices));
    $result = execCmd($cmd, true, 30);
    if (strpos($result, 'successfully created') !== false || strpos($result, 'Volume group') !== false) {
        return ['success' => true];
    }
    return ['success' => false, 'error' => $result];
}

function deleteVG($vgName) {
    $lvs = getAllLVs();
    foreach ($lvs as $lv) {
        if ($lv['vg_name'] === $vgName) {
            execCmd("lvchange -an " . escapeshellarg($lv['path']) . " 2>/dev/null", true, 10);
            sleep(1);
        }
    }
    
    $result = execCmd("vgremove -f " . escapeshellarg($vgName), true, 30);
    if (strpos($result, 'successfully removed') !== false || strpos($result, 'Volume group') !== false) {
        return ['success' => true];
    }
    return ['success' => false, 'error' => $result];
}

function extendVG($vgName, $devices) {
    $cmd = "vgextend " . escapeshellarg($vgName) . " " . implode(' ', array_map('escapeshellarg', $devices));
    $result = execCmd($cmd, true, 30);
    if (strpos($result, 'successfully extended') !== false || strpos($result, 'Volume group') !== false) {
        return ['success' => true];
    }
    return ['success' => false, 'error' => $result];
}

function reduceVG($vgName, $devices) {
    $pvsInVg = getPVsInVG($vgName);
    
    $errors = [];
    $successDevices = [];
    
    foreach ($devices as $device) {
        $pvInfo = null;
        foreach ($pvsInVg as $pv) {
            if ($pv['name'] === $device) {
                $pvInfo = $pv;
                break;
            }
        }
        
        if ($pvInfo && $pvInfo['used'] > 0) {
            $pvmoveCmd = "pvmove " . escapeshellarg($device) . " 2>&1";
            $moveResult = execCmd($pvmoveCmd, true, 300);
            if (strpos($moveResult, 'No data to move') === false && strpos($moveResult, 'successfully') === false) {
                $errors[] = "Cannot move data from {$device}: " . $moveResult;
                continue;
            }
        }
        
        $reduceCmd = "vgreduce " . escapeshellarg($vgName) . " " . escapeshellarg($device);
        $result = execCmd($reduceCmd, true, 30);
        if (strpos($result, 'successfully removed') !== false || strpos($result, 'Volume group') !== false) {
            $successDevices[] = $device;
        } else {
            $errors[] = "Failed to remove {$device}: " . $result;
        }
    }
    
    if (count($successDevices) > 0) {
        return ['success' => true, 'removed' => $successDevices, 'errors' => $errors];
    }
    return ['success' => false, 'error' => implode('; ', $errors)];
}

function renameVG($oldName, $newName) {
    $result = execCmd("vgrename " . escapeshellarg($oldName) . " " . escapeshellarg($newName), true, 30);
    if (strpos($result, 'successfully renamed') !== false || strpos($result, 'Volume group') !== false) {
        return ['success' => true];
    }
    return ['success' => false, 'error' => $result];
}

function createLV($vgName, $lvName, $size, $format = false, $fsType = 'ext4') {
	global $lang2085, $lang2086;
    $size = trim($size);
    
    if (preg_match('/^(\d+(?:\.\d+)?)%FREE$/i', $size, $matches)) {
        $percent = $matches[1];
        $cmd = "sudo lvcreate -n " . escapeshellarg($lvName) . " -l " . escapeshellarg($percent . '%FREE') . " " . escapeshellarg($vgName);
    } 
    elseif (preg_match('/^(\d+(?:\.\d+)?)%$/', $size, $matches)) {
        $percent = $matches[1];
        $cmd = "sudo lvcreate -n " . escapeshellarg($lvName) . " -l " . escapeshellarg($percent . '%VG') . " " . escapeshellarg($vgName);
    }
    else {
        if (is_numeric($size)) {
            $size = $size . 'G';
        }
        $cmd = "sudo lvcreate -n " . escapeshellarg($lvName) . " -L " . escapeshellarg($size) . " " . escapeshellarg($vgName);
    }
    
    $result = execCmd($cmd, true, 60);
    
    if (strpos($result, 'successfully created') !== false || 
        strpos($result, 'Logical volume') !== false ||
        strpos($result, 'Volume group') !== false) {
        
        sleep(2);
        
        $lvPath = "/dev/{$vgName}/{$lvName}";
        $mapperPath = "/dev/mapper/" . str_replace('-', '--', $lvName);
        
        $deviceExists = false;
        for ($i = 0; $i < 10; $i++) {
            if (file_exists($lvPath) || file_exists($mapperPath)) {
                $deviceExists = true;
                break;
            }
            sleep(1);
        }
        
        if (!$deviceExists) {
            return ['success' => false, 'error' => $lang2085];
        }
        
        execCmd("sudo lvchange -ay " . escapeshellarg($lvPath) . " 2>/dev/null", true, 10);
        sleep(1);
        
        if ($format) {
            $formatResult = formatLV($lvPath, $fsType);
            if (!$formatResult['success']) {
                return ['success' => false, 'error' => $lang2086 . $formatResult['error']];
            }
        }
        
        return ['success' => true];
    }
    return ['success' => false, 'error' => $result];
}

function formatLV($lvPath, $fsType = 'ext4') {
	global $lang2087, $lang2088, $lang2089, $lang2090, $lang2091, $lang2092, $lang2093, $lang2094;
    $originalPath = $lvPath;
    $lvPath = preg_replace('/^\/dev\//', '', $lvPath);
    $fullPath = '/dev/' . $lvPath;
    $mapperPath = '/dev/mapper/' . str_replace('-', '--', str_replace('/', '-', $lvPath));
    
    $devicePath = null;
    if (file_exists($fullPath)) {
        $devicePath = $fullPath;
    } elseif (file_exists($mapperPath)) {
        $devicePath = $mapperPath;
    } else {
        return ['success' => false, 'error' => $lang2087 . $originalPath];
    }
    
    $isMounted = false;
    $mountPoint = '';
    
    $procMountsContent = execCmd("cat /proc/mounts 2>/dev/null", true, 5);
    if (!empty($procMountsContent)) {
        $lines = explode("\n", $procMountsContent);
        foreach ($lines as $line) {
            if (empty(trim($line))) continue;
            $parts = preg_split('/\s+/', trim($line));
            if (count($parts) >= 2) {
                $mountedDevice = $parts[0];
                $mountedPath = $parts[1];
                
                if ($mountedDevice === $devicePath || 
                    $mountedDevice === $fullPath || 
                    $mountedDevice === $mapperPath) {
                    $isMounted = true;
                    $mountPoint = $mountedPath;
                    break;
                }
            }
        }
    }
    
    if ($isMounted) {
        return ['success' => false, 'error' => $lang2088 . $mountPoint];
    }
    
    // ============================================================
    // АКТИВАЦИЯ LV если не активен
    // ============================================================
    $activeCheck = execCmd("lvs --noheadings -o lv_attr " . escapeshellarg($devicePath) . " 2>/dev/null", true, 5);
    if (empty($activeCheck) || strpos($activeCheck, 'a') === false) {
        execCmd("lvchange -ay " . escapeshellarg($devicePath) . " 2>/dev/null", true, 10);
        sleep(2);
        
        $activeCheck2 = execCmd("lvs --noheadings -o lv_attr " . escapeshellarg($devicePath) . " 2>/dev/null", true, 5);
        if (empty($activeCheck2) || strpos($activeCheck2, 'a') === false) {
            return ['success' => false, 'error' => $lang2089 . $devicePath];
        }
    }
    
    execCmd("udevadm settle 2>/dev/null", true, 5);
    sleep(1);
    
    $permsCheck = execCmd("test -w " . escapeshellarg($devicePath) . " && echo 'writable' || echo 'not writable'", true, 5);
    if (strpos($permsCheck, 'not writable') !== false) {
        execCmd("chmod 666 " . escapeshellarg($devicePath) . " 2>/dev/null", true, 5);
        sleep(1);
    }
    
    // ============================================================
    // ФОРМАТИРОВАНИЕ
    // ============================================================
    $formatCmd = "";
    switch ($fsType) {
        case 'ext4':
            $formatCmd = "mkfs.ext4 -F " . escapeshellarg($devicePath) . " 2>&1";
            break;
        case 'ext3':
            $formatCmd = "mkfs.ext3 -F " . escapeshellarg($devicePath) . " 2>&1";
            break;
        case 'ext2':
            $formatCmd = "mkfs.ext2 -F " . escapeshellarg($devicePath) . " 2>&1";
            break;
        case 'xfs':
            $formatCmd = "mkfs.xfs -f " . escapeshellarg($devicePath) . " 2>&1";
            break;
        case 'ntfs':
            $formatCmd = "mkfs.ntfs -Q -F " . escapeshellarg($devicePath) . " 2>&1";
            break;
        case 'vfat':
            $formatCmd = "mkfs.vfat -F 32 " . escapeshellarg($devicePath) . " 2>&1";
            break;
        case 'btrfs':
            $formatCmd = "mkfs.btrfs -f " . escapeshellarg($devicePath) . " 2>&1";
            break;
        default:
            $formatCmd = "mkfs.ext4 -F " . escapeshellarg($devicePath) . " 2>&1";
    }
    
    $result = execCmd("sudo " . $formatCmd, true, 120);
    
    $successPatterns = ['Creating filesystem', 'Writing superblocks', 'Allocating group tables', 'done', 'mkfs', 'mke2fs', 'complete', 'successfully'];
    $success = false;
    foreach ($successPatterns as $pattern) {
        if (stripos($result, $pattern) !== false) {
            $success = true;
            break;
        }
    }
    
    $errorPatterns = ['error', 'failed', 'cannot', 'unable', 'not found', 'No such file'];
    $hasError = false;
    foreach ($errorPatterns as $pattern) {
        if (stripos($result, $pattern) !== false && stripos($result, 'WARNING:') === false) {
            $hasError = true;
            break;
        }
    }
    
    if ($success && !$hasError) {
        execCmd("partprobe " . escapeshellarg($devicePath) . " 2>/dev/null", true, 5);
        execCmd("udevadm settle 2>/dev/null", true, 5);
        sleep(1);
        
        $checkFs = execCmd("blkid -s TYPE -o value " . escapeshellarg($devicePath) . " 2>/dev/null", true, 5);
        if (!empty($checkFs)) {
            return ['success' => true, 'message' => $lang2090 . $fsType, 'filesystem' => trim($checkFs)];
        }
        return ['success' => true, 'message' => $lang2091 . $fsType];
    }
    
    if (stripos($result, 'WARNING:') !== false && (stripos($result, 'done') !== false || stripos($result, 'Creating') !== false)) {
        return ['success' => true, 'message' => $lang2092 . $fsType . $lang2093];
    }
    
    return ['success' => false, 'error' => $lang2094 . substr($result, 0, 500)];
}

function deleteLV($vgName, $lvName) {
    $lvPath = "/dev/$vgName/$lvName";
    $mapperPath = "/dev/mapper/" . str_replace('-', '--', $lvName);
    
    $mountCheck = execCmd("nsenter -t 1 -m cat /proc/mounts | grep -E '^" . preg_quote($lvPath, '/') . "\\s|^" . preg_quote($mapperPath, '/') . "\\s' | awk '{print $2}'", true, 5);
    if (!empty($mountCheck)) {
        $mountPoint = trim($mountCheck);
        execCmd("nsenter -t 1 -m umount -f " . escapeshellarg($mountPoint) . " 2>/dev/null", true, 10);
        sleep(1);
    }
    
    execCmd("lvchange -an " . escapeshellarg($lvPath) . " 2>/dev/null", true, 10);
    sleep(1);
    
    $result = execCmd("lvremove -f " . escapeshellarg($vgName . '/' . $lvName), true, 30);
    
    if (strpos($result, 'successfully removed') !== false || strpos($result, 'Logical volume') !== false) {
        return ['success' => true];
    }
    return ['success' => false, 'error' => $result];
}

function extendLV($vgName, $lvName, $size) {
    $lvPath = "/dev/$vgName/$lvName";
    
    $activeCheck = execCmd("lvs --noheadings -o lv_attr " . escapeshellarg($lvPath) . " 2>/dev/null", true, 5);
    if (!empty($activeCheck) && strpos($activeCheck, 'a') === false) {
        execCmd("lvchange -ay " . escapeshellarg($lvPath) . " 2>/dev/null", true, 10);
        sleep(1);
    }
    
    $extendCmd = "lvextend -L " . escapeshellarg($size) . " " . escapeshellarg($lvPath);
    $result = execCmd($extendCmd, true, 60);
    
    if (strpos($result, 'successfully resized') !== false || strpos($result, 'Logical volume') !== false) {
        $fsType = execCmd("blkid -s TYPE -o value " . escapeshellarg($lvPath) . " 2>/dev/null", true, 5);
        if (!empty($fsType)) {
            if ($fsType === 'ext4' || $fsType === 'ext3') {
                execCmd("resize2fs " . escapeshellarg($lvPath) . " 2>&1", true, 60);
            } elseif ($fsType === 'xfs') {
                execCmd("xfs_growfs " . escapeshellarg($lvPath) . " 2>&1", true, 60);
            }
        }
        return ['success' => true];
    }
    return ['success' => false, 'error' => $result];
}

function renameLV($vgName, $oldName, $newName) {
    $result = execCmd("lvrename " . escapeshellarg($vgName) . " " . escapeshellarg($oldName) . " " . escapeshellarg($newName), true, 30);
    if (strpos($result, 'successfully renamed') !== false || strpos($result, 'Logical volume') !== false) {
        return ['success' => true];
    }
    return ['success' => false, 'error' => $result];
}

// ==================== ФУНКЦИИ МОНТИРОВАНИЯ ====================

function mountLV($device, $mountPoint, $fsType = 'auto', $addToFstab = false) {
    $logFile = '/var/www/minib/logs/lvm.log';
    
    // Логируем входные параметры
    $logMessage = date('Y-m-d H:i:s') . " | START | Device: {$device}, MountPoint: {$mountPoint}, FSType: {$fsType}, AddToFstab: " . ($addToFstab ? 'true' : 'false') . "\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
    
    // Определяем правильный путь к устройству
    $device = preg_replace('/^\/dev\//', '', $device);
    $devicePath = "/dev/{$device}";
    $mapperPath = "/dev/mapper/" . str_replace('-', '--', $device);
    
    $logMessage = date('Y-m-d H:i:s') . " | CHECK | DevicePath: {$devicePath}, MapperPath: {$mapperPath}\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
    
    // Выбираем существующий путь
    $usePath = null;
    if (file_exists($devicePath)) {
        $usePath = $devicePath;
    } elseif (file_exists($mapperPath)) {
        $usePath = $mapperPath;
    } else {
        $errorMsg = "Device not found: {$devicePath} or {$mapperPath}";
        $logMessage = date('Y-m-d H:i:s') . " | ERROR | {$errorMsg}\n";
        file_put_contents($logFile, $logMessage, FILE_APPEND);
        return ['success' => false, 'error' => $errorMsg];
    }
    
    $logMessage = date('Y-m-d H:i:s') . " | INFO | Using path: {$usePath}\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
    
    // Получаем UID и GID
    $uid = trim(execCmd("id -u www-data 2>/dev/null", false, 3));
    $gid = trim(execCmd("id -g www-data 2>/dev/null", false, 3));
    if (empty($uid)) $uid = 33;
    if (empty($gid)) $gid = 33;
    
    // ПРОВЕРКА: Не смонтировано ли уже устройство (проверяем оба пути)
    $checkMount = function($path) {
        return execCmd("nsenter -t 1 -m cat /proc/mounts | grep -E '^" . preg_quote($path, '/') . "\\s+'", true, 5);
    };
    
    $mountInfo = $checkMount($usePath);
    if (empty($mountInfo)) {
        // Проверяем маппер путь если используем dev путь
        if ($usePath === $devicePath) {
            $mountInfo = $checkMount($mapperPath);
        }
    }
    
    if (!empty($mountInfo)) {
        $existingPoint = trim(execCmd("echo '{$mountInfo}' | awk '{print $2}'", true, 5));
        
        // Если уже смонтировано в нужную точку - возвращаем успех
        if ($existingPoint === $mountPoint) {
            $logMessage = date('Y-m-d H:i:s') . " | SUCCESS | Already mounted at: {$mountPoint}\n";
            file_put_contents($logFile, $logMessage, FILE_APPEND);
            
            if ($addToFstab) {
                addToFstab($usePath, $mountPoint, $fsType, $logFile);
            }
            
            return ['success' => true, 'mount_point' => $mountPoint];
        }
        
        // Если смонтировано в другую точку - размонтируем
        $logMessage = date('Y-m-d H:i:s') . " | INFO | Device mounted at {$existingPoint}, unmounting...\n";
        file_put_contents($logFile, $logMessage, FILE_APPEND);
        execCmd("nsenter -t 1 -m umount {$usePath} 2>/dev/null", true, 5);
        execCmd("nsenter -t 1 -m umount {$mapperPath} 2>/dev/null", true, 5);
        sleep(3);
    }
    
    // ПРОВЕРКА: Точка монтирования не занята другим устройством
    $mpCheck = execCmd("nsenter -t 1 -m cat /proc/mounts | grep -E '\\s+" . preg_quote($mountPoint, '/') . "\\s+'", true, 5);
    if (!empty($mpCheck)) {
        $mountedDevice = trim(execCmd("echo '{$mpCheck}' | awk '{print $1}'", true, 5));
        
        // Если точка занята нашим устройством - возвращаем успех
        if ($mountedDevice === $usePath || $mountedDevice === $mapperPath) {
            $logMessage = date('Y-m-d H:i:s') . " | SUCCESS | Already mounted at: {$mountPoint}\n";
            file_put_contents($logFile, $logMessage, FILE_APPEND);
            
            if ($addToFstab) {
                addToFstab($usePath, $mountPoint, $fsType, $logFile);
            }
            
            return ['success' => true, 'mount_point' => $mountPoint];
        }
        
        // Иначе ошибка
        $errorMsg = "Mount point {$mountPoint} is busy with device {$mountedDevice}";
        $logMessage = date('Y-m-d H:i:s') . " | ERROR | {$errorMsg}\n";
        file_put_contents($logFile, $logMessage, FILE_APPEND);
        return ['success' => false, 'error' => $errorMsg];
    }
    
    // АКТИВАЦИЯ LV если нужно
    $activeCheck = execCmd("lvs --noheadings -o lv_attr " . escapeshellarg($usePath) . " 2>/dev/null", true, 5);
    if (!empty($activeCheck) && strpos($activeCheck, 'a') === false) {
        $logMessage = date('Y-m-d H:i:s') . " | INFO | Activating LV: {$usePath}\n";
        file_put_contents($logFile, $logMessage, FILE_APPEND);
        execCmd("lvchange -ay " . escapeshellarg($usePath) . " 2>/dev/null", true, 10);
        sleep(3);
    }
    
    // ОПРЕДЕЛЕНИЕ ФАЙЛОВОЙ СИСТЕМЫ
    if ($fsType === 'auto') {
        $detectedFs = trim(execCmd("blkid -s TYPE -o value {$usePath} 2>/dev/null", true, 5));
        if (empty($detectedFs)) {
            $detectedFs = trim(execCmd("lsblk -n -o FSTYPE {$usePath} 2>/dev/null", true, 5));
        }
        if (empty($detectedFs)) {
            $errorMsg = "Could not detect filesystem on {$usePath}";
            $logMessage = date('Y-m-d H:i:s') . " | ERROR | {$errorMsg}\n";
            file_put_contents($logFile, $logMessage, FILE_APPEND);
            return ['success' => false, 'error' => $errorMsg];
        }
        $fsType = $detectedFs;
        $logMessage = date('Y-m-d H:i:s') . " | INFO | Detected FS: {$fsType}\n";
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }
    
    // СОЗДАНИЕ ТОЧКИ МОНТИРОВАНИЯ
    if (!is_dir($mountPoint)) {
        $parentDir = dirname($mountPoint);
        if (!is_dir($parentDir) && $parentDir !== '/') {
            execCmd("mkdir -p " . escapeshellarg($parentDir) . " 2>/dev/null", true, 5);
        }
        
        $mkdirResult = execCmd("mkdir -p " . escapeshellarg($mountPoint) . " 2>&1", true, 5);
        if (strpos($mkdirResult, 'cannot create') !== false) {
            $errorMsg = "Failed to create mount point: {$mkdirResult}";
            $logMessage = date('Y-m-d H:i:s') . " | ERROR | {$errorMsg}\n";
            file_put_contents($logFile, $logMessage, FILE_APPEND);
            return ['success' => false, 'error' => $errorMsg];
        }
        
        $logMessage = date('Y-m-d H:i:s') . " | INFO | Created mount point: {$mountPoint}\n";
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }
    
    // МОНТИРОВАНИЕ
    $logMessage = date('Y-m-d H:i:s') . " | INFO | Mounting {$usePath} to {$mountPoint} with FS: {$fsType}\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
    
    // Формируем опции монтирования
    $mountOptions = 'rw,noatime';
    if ($fsType === 'ntfs' || $fsType === 'ntfs-3g') {
        $fsType = 'ntfs-3g';
        $mountOptions = "uid={$uid},gid={$gid},umask=000,fmask=000,dmask=000,big_writes";
    } elseif ($fsType === 'vfat' || $fsType === 'fat32') {
        $fsType = 'vfat';
        $mountOptions = "uid={$uid},gid={$gid},umask=000,fmask=000,dmask=000,shortname=mixed,utf8";
    } elseif ($fsType === 'exfat') {
        $mountOptions = "uid={$uid},gid={$gid},umask=000,fmask=000,dmask=000";
    } else {
        $mountOptions = "rw,noatime,uid={$uid},gid={$gid}";
    }
    
    // Пытаемся смонтировать
    $mountSuccess = false;
    $actualMountPoint = $mountPoint;
    
    // Способ 1: с опциями
    $mountCmd = "nsenter -t 1 -m mount -t {$fsType} -o {$mountOptions} {$usePath} " . escapeshellarg($mountPoint) . " 2>&1";
    $logMessage = date('Y-m-d H:i:s') . " | MOUNT | Executing: {$mountCmd}\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
    execCmd($mountCmd, true, 30);
    sleep(3);
    
    // Проверяем успех (проверяем оба пути)
    $verify = function() use ($usePath, $mapperPath, $mountPoint) {
        $result = execCmd("nsenter -t 1 -m cat /proc/mounts | grep -E '^" . preg_quote($usePath, '/') . "\\s+'", true, 5);
        if (empty($result) && $usePath !== $mapperPath) {
            $result = execCmd("nsenter -t 1 -m cat /proc/mounts | grep -E '^" . preg_quote($mapperPath, '/') . "\\s+'", true, 5);
        }
        return $result;
    };
    
    $verifyResult = $verify();
    
    if (!empty($verifyResult)) {
        $actualMountPoint = trim(execCmd("echo '{$verifyResult}' | awk '{print $2}'", true, 5));
        $mountSuccess = true;
        $logMessage = date('Y-m-d H:i:s') . " | SUCCESS | Mounted at: {$actualMountPoint}\n";
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    } else {
        // Способ 2: без опций
        $logMessage = date('Y-m-d H:i:s') . " | WARN | First mount attempt failed, trying without options...\n";
        file_put_contents($logFile, $logMessage, FILE_APPEND);
        
        $mountCmd = "nsenter -t 1 -m mount {$usePath} " . escapeshellarg($mountPoint) . " 2>&1";
        $logMessage = date('Y-m-d H:i:s') . " | MOUNT | Executing: {$mountCmd}\n";
        file_put_contents($logFile, $logMessage, FILE_APPEND);
        execCmd($mountCmd, true, 30);
        sleep(3);
        
        $verifyResult = $verify();
        if (!empty($verifyResult)) {
            $actualMountPoint = trim(execCmd("echo '{$verifyResult}' | awk '{print $2}'", true, 5));
            $mountSuccess = true;
            $logMessage = date('Y-m-d H:i:s') . " | SUCCESS | Mounted at: {$actualMountPoint}\n";
            file_put_contents($logFile, $logMessage, FILE_APPEND);
        }
    }
    
    // ФИНАЛЬНАЯ ПРОВЕРКА через lsblk
    if (!$mountSuccess) {
        $logMessage = date('Y-m-d H:i:s') . " | INFO | Checking mount status via lsblk...\n";
        file_put_contents($logFile, $logMessage, FILE_APPEND);
        
        $lsblkCheck = execCmd("lsblk -n -o MOUNTPOINT {$usePath} 2>/dev/null", true, 5);
        if (!empty($lsblkCheck) && trim($lsblkCheck) === $mountPoint) {
            $mountSuccess = true;
            $actualMountPoint = $mountPoint;
            $logMessage = date('Y-m-d H:i:s') . " | SUCCESS | Mounted at: {$actualMountPoint} (verified by lsblk)\n";
            file_put_contents($logFile, $logMessage, FILE_APPEND);
        }
    }
    
    if (!$mountSuccess) {
        $errorMsg = "Mount failed - device not found in /proc/mounts";
        $logMessage = date('Y-m-d H:i:s') . " | ERROR | {$errorMsg}\n";
        file_put_contents($logFile, $logMessage, FILE_APPEND);
        
        // Но если устройство все же смонтировано (по lsblk), возвращаем успех
        $lsblkCheck = execCmd("lsblk -n -o MOUNTPOINT {$usePath} 2>/dev/null", true, 5);
        if (!empty($lsblkCheck)) {
            $actualMountPoint = trim($lsblkCheck);
            $logMessage = date('Y-m-d H:i:s') . " | SUCCESS | Device mounted at: {$actualMountPoint} (detected by lsblk)\n";
            file_put_contents($logFile, $logMessage, FILE_APPEND);
            
            if ($addToFstab) {
                addToFstab($usePath, $actualMountPoint, $fsType, $logFile);
            }
            
            return ['success' => true, 'mount_point' => $actualMountPoint];
        }
        
        return ['success' => false, 'error' => $errorMsg];
    }
    
    // Устанавливаем права
    execCmd("nsenter -t 1 -m chmod 777 \"{$actualMountPoint}\" 2>/dev/null", true, 5);
    execCmd("nsenter -t 1 -m chown {$uid}:{$gid} \"{$actualMountPoint}\" 2>/dev/null", true, 5);
    
    // Добавляем в fstab если нужно
    if ($addToFstab) {
        addToFstab($usePath, $actualMountPoint, $fsType, $logFile);
    }
    
    $logMessage = date('Y-m-d H:i:s') . " | COMPLETE | Mount successful, mount point: {$actualMountPoint}\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
    
    return ['success' => true, 'mount_point' => $actualMountPoint];
}

//  функция для добавления в fstab
function addToFstab($device, $mountPoint, $fsType, $logFile) {
    $uuid = trim(execCmd("blkid -s UUID -o value {$device} 2>/dev/null", true, 5));
    if (empty($uuid)) {
        $logMessage = date('Y-m-d H:i:s') . " | FSTAB | Cannot get UUID for {$device}\n";
        file_put_contents($logFile, $logMessage, FILE_APPEND);
        return;
    }
    
    $fstabOptions = 'defaults,noatime';
    if ($fsType === 'ntfs-3g') {
        $fstabOptions = 'defaults,noatime,uid=0,gid=0,umask=000,fmask=000,dmask=000';
    } elseif ($fsType === 'vfat') {
        $fstabOptions = 'defaults,noatime,uid=0,gid=0,umask=000,fmask=000,dmask=000,shortname=mixed,utf8';
    } elseif ($fsType === 'exfat') {
        $fstabOptions = 'defaults,noatime,uid=0,gid=0,umask=000,fmask=000,dmask=000';
    }
    
    $fstabLine = "UUID={$uuid} {$mountPoint} {$fsType} {$fstabOptions} 0 2\n";
    $currentFstab = @file_get_contents('/etc/fstab');
    
    if ($currentFstab !== false) {
        if (strpos($currentFstab, $uuid) === false && strpos($currentFstab, $mountPoint) === false) {
            @file_put_contents('/etc/fstab', $fstabLine, FILE_APPEND);
            $logMessage = date('Y-m-d H:i:s') . " | FSTAB | Added: {$fstabLine}\n";
            file_put_contents($logFile, $logMessage, FILE_APPEND);
        } else {
            $logMessage = date('Y-m-d H:i:s') . " | FSTAB | Entry already exists, skipped\n";
            file_put_contents($logFile, $logMessage, FILE_APPEND);
        }
    }
}

function umountLV($mountPoint, $removeFromFstab = false) {
    global $lang2109, $lang2110;
    
    $logFile = '/var/www/minib/logs/lvm.log';
    $logMessage = date('Y-m-d H:i:s') . " | UMOUNT | Start | MountPoint: {$mountPoint}, RemoveFromFstab: " . ($removeFromFstab ? 'true' : 'false') . "\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
    
    // Проверяем, смонтирована ли точка
    $checkMount = execCmd("nsenter -t 1 -m cat /proc/mounts | grep -E '\\s+" . preg_quote($mountPoint, '/') . "\\s+'", true, 5);
    if (empty($checkMount)) {
        $logMessage = date('Y-m-d H:i:s') . " | UMOUNT | ERROR | Not mounted: {$mountPoint}\n";
        file_put_contents($logFile, $logMessage, FILE_APPEND);
        return ['success' => false, 'error' => $lang2109];
    }
    
    // Получаем информацию об устройстве
    $device = execCmd("nsenter -t 1 -m cat /proc/mounts | grep -E '\\s+" . preg_quote($mountPoint, '/') . "\\s+' | awk '{print $1}'", true, 5);
    $device = trim($device);
    
    $logMessage = date('Y-m-d H:i:s') . " | UMOUNT | Device: {$device}\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
    
    // Получаем UUID устройства для удаления из fstab
    $uuid = trim(execCmd("blkid -s UUID -o value {$device} 2>/dev/null", true, 5));
    
    // Проверяем, есть ли запись в fstab
    $fstabEntry = null;
    $fstabContent = @file_get_contents('/etc/fstab');
    if ($fstabContent !== false) {
        $lines = explode("\n", $fstabContent);
        foreach ($lines as $line) {
            if (empty(trim($line)) || strpos($line, '#') === 0) continue;
            
            $parts = preg_split('/\s+/', trim($line));
            if (count($parts) >= 2) {
                // Проверяем по UUID или точке монтирования
                if (!empty($uuid) && strpos($parts[0], $uuid) !== false) {
                    $fstabEntry = $line;
                    break;
                }
                if ($parts[1] === $mountPoint) {
                    $fstabEntry = $line;
                    break;
                }
                // Проверяем по пути устройства
                if ($parts[0] === $device) {
                    $fstabEntry = $line;
                    break;
                }
            }
        }
    }
    
    // Удаляем из fstab если нужно
    if ($removeFromFstab && $fstabEntry !== null && $fstabContent !== false) {
        $newFstab = str_replace($fstabEntry . "\n", '', $fstabContent);
        $newFstab = str_replace($fstabEntry, '', $newFstab);
        
        // Убираем лишние пустые строки
        $newFstab = preg_replace("/\n{3,}/", "\n\n", $newFstab);
        $newFstab = trim($newFstab) . "\n";
        
        $result = @file_put_contents('/etc/fstab', $newFstab);
        if ($result !== false) {
            $logMessage = date('Y-m-d H:i:s') . " | UMOUNT | FSTAB | Removed entry: {$fstabEntry}\n";
            file_put_contents($logFile, $logMessage, FILE_APPEND);
        } else {
            $logMessage = date('Y-m-d H:i:s') . " | UMOUNT | FSTAB | Failed to remove entry\n";
            file_put_contents($logFile, $logMessage, FILE_APPEND);
        }
    }
    
    // Пытаемся размонтировать
    $result = execCmd("nsenter -t 1 -m umount " . escapeshellarg($mountPoint), true, 30);
    
    $markerFile = $mountPoint . '/.mount_created_by_disk_manager';
    $wasCreatedByMount = file_exists($markerFile);
    
    if (strpos($result, 'not mounted') !== false) {
        $result = '';
    }
    
    if (empty($result) || strpos($result, 'success') !== false || strpos($result, 'umount') !== false) {
        if ($wasCreatedByMount && is_dir($mountPoint)) {
            execCmd("rm -f " . escapeshellarg($markerFile), true, 3);
            
            $isEmpty = true;
            $dirContent = @scandir($mountPoint);
            if ($dirContent !== false) {
                $content = array_diff($dirContent, ['.', '..']);
                if (!empty($content)) {
                    $isEmpty = false;
                }
            }
            
            if ($isEmpty) {
                execCmd("rmdir " . escapeshellarg($mountPoint) . " 2>/dev/null", true, 3);
                $logMessage = date('Y-m-d H:i:s') . " | UMOUNT | Removed empty mount point: {$mountPoint}\n";
                file_put_contents($logFile, $logMessage, FILE_APPEND);
            }
        }
        
        $logMessage = date('Y-m-d H:i:s') . " | UMOUNT | SUCCESS | Unmounted: {$mountPoint}\n";
        file_put_contents($logFile, $logMessage, FILE_APPEND);
        return ['success' => true];
    }
    
    // Пробуем принудительное размонтирование
    $result2 = execCmd("nsenter -t 1 -m umount -l " . escapeshellarg($mountPoint), true, 10);
    if (empty($result2)) {
        if ($wasCreatedByMount && is_dir($mountPoint)) {
            execCmd("rm -f " . escapeshellarg($markerFile), true, 3);
            
            $isEmpty = true;
            $dirContent = @scandir($mountPoint);
            if ($dirContent !== false) {
                $content = array_diff($dirContent, ['.', '..']);
                if (!empty($content)) {
                    $isEmpty = false;
                }
            }
            
            if ($isEmpty) {
                execCmd("rmdir " . escapeshellarg($mountPoint) . " 2>/dev/null", true, 3);
                $logMessage = date('Y-m-d H:i:s') . " | UMOUNT | Removed empty mount point: {$mountPoint}\n";
                file_put_contents($logFile, $logMessage, FILE_APPEND);
            }
        }
        
        $logMessage = date('Y-m-d H:i:s') . " | UMOUNT | SUCCESS | Lazy umount: {$mountPoint}\n";
        file_put_contents($logFile, $logMessage, FILE_APPEND);
        return ['success' => true, 'message' => $lang2110];
    }
    
    $logMessage = date('Y-m-d H:i:s') . " | UMOUNT | ERROR | Failed: {$result}\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
    return ['success' => false, 'error' => $result];
}

// ==================== ФУНКЦИИ ДЛЯ СНАПШОТОВ ====================

function getAllSnapshots() {
    $snapshots = [];
    
    $cmd = "lvs --noheadings --units g -o lv_name,vg_name,lv_size,lv_attr,origin,data_percent,metadata_percent 2>/dev/null";
    $result = execCmd($cmd, true, 10);
    
    if (!empty($result)) {
        $lines = explode("\n", trim($result));
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            $parts = preg_split('/\s+/', $line);
            if (count($parts) >= 5) {
                $lvName = $parts[0];
                $vgName = $parts[1];
                $size = isset($parts[2]) ? $parts[2] : '0';
                $attr = isset($parts[3]) ? $parts[3] : '';
                $origin = isset($parts[4]) ? $parts[4] : '';
                $dataPercent = isset($parts[5]) ? trim($parts[5], '%') : '0';
                $metaPercent = isset($parts[6]) ? trim($parts[6], '%') : '0';
                
                $isSnapshot = (strpos($attr, 's') !== false || strpos($attr, 'S') !== false || !empty($origin));
                
                if ($isSnapshot) {
                    $lvPath = "/dev/{$vgName}/{$lvName}";
                    
                    $mountPoint = null;
                    $mountsOutput = execCmd("nsenter -t 1 -m cat /proc/mounts 2>/dev/null | grep " . escapeshellarg($lvPath) . " | awk '{print $2}'", true, 5);
                    if (!empty($mountsOutput)) {
                        $mountPoint = trim($mountsOutput);
                    }
                    
                    $snapshots[] = [
                        'name' => $lvName,
                        'vg_name' => $vgName,
                        'origin' => $origin ?: 'unknown',
                        'size' => $size,
                        'size_bytes' => parseSize($size),
                        'attr' => $attr,
                        'data_percent' => floatval($dataPercent),
                        'metadata_percent' => floatval($metaPercent),
                        'path' => $lvPath,
                        'is_active' => strpos($attr, 'a') !== false,
                        'is_merging' => strpos($attr, 'M') !== false || strpos($attr, 'm') !== false,
                        'mount_point' => $mountPoint
                    ];
                }
            }
        }
    }
    
    $cmd2 = "lvdisplay -c 2>/dev/null | grep -i snapshot";
    $result2 = execCmd($cmd2, true, 10);
    if (!empty($result2)) {
        $lines2 = explode("\n", trim($result2));
        foreach ($lines2 as $line) {
            if (empty($line)) continue;
            if (preg_match('/^([^\/]+)\/([^:]+)/', $line, $matches)) {
                $vgName = $matches[1];
                $lvName = $matches[2];
                
                $exists = false;
                foreach ($snapshots as $s) {
                    if ($s['name'] == $lvName && $s['vg_name'] == $vgName) {
                        $exists = true;
                        break;
                    }
                }
                
                if (!$exists) {
                    $sizeCmd = "lvs --noheadings --units g -o lv_size " . escapeshellarg($vgName . '/' . $lvName) . " 2>/dev/null";
                    $size = execCmd($sizeCmd, true, 5);
                    $originCmd = "lvs --noheadings -o origin " . escapeshellarg($vgName . '/' . $lvName) . " 2>/dev/null";
                    $origin = execCmd($originCmd, true, 5);
                    
                    $snapshots[] = [
                        'name' => $lvName,
                        'vg_name' => $vgName,
                        'origin' => trim($origin) ?: 'unknown',
                        'size' => trim($size) ?: '0',
                        'size_bytes' => parseSize($size),
                        'attr' => 's',
                        'data_percent' => 0,
                        'metadata_percent' => 0,
                        'path' => "/dev/{$vgName}/{$lvName}",
                        'is_active' => true,
                        'is_merging' => false,
                        'mount_point' => null
                    ];
                }
            }
        }
    }
    
    usort($snapshots, function($a, $b) {
        return strcmp($a['name'], $b['name']);
    });
    
    return $snapshots;
}

function createSnapshot($vgName, $originLvName, $snapshotName, $size) {
	global $lang2111, $lang2112, $lang2113, $lang2114;
    $checkCmd = "lvs --noheadings -o lv_name " . escapeshellarg($vgName . '/' . $snapshotName) . " 2>/dev/null";
    $check = execCmd($checkCmd, true, 5);
    
    $finalName = $snapshotName;
    $counter = 1;
    
    while (!empty(trim($check))) {
        $finalName = $snapshotName . '_v' . $counter;
        $checkCmd = "lvs --noheadings -o lv_name " . escapeshellarg($vgName . '/' . $finalName) . " 2>/dev/null";
        $check = execCmd($checkCmd, true, 5);
        $counter++;
        if ($counter > 100) break;
    }
    

    $originCheck = execCmd("lvs --noheadings -o lv_name " . escapeshellarg($vgName . '/' . $originLvName) . " 2>/dev/null", true, 5);
    if (empty(trim($originCheck))) {
        return ['success' => false, 'error' => $lang2111 . $originLvName . $lang2112];
    }
    

    $originAttr = execCmd("lvs --noheadings -o lv_attr " . escapeshellarg($vgName . '/' . $originLvName) . " 2>/dev/null", true, 5);
    if (strpos($originAttr, 's') !== false || strpos($originAttr, 'S') !== false) {
        return ['success' => false, 'error' => $lang2113];
    }
    

    $sizeArg = '';
    if (preg_match('/^(\d+(?:\.\d+)?)%$/', $size, $matches)) {
        $percent = $matches[1];
        $sizeArg = "-l " . escapeshellarg($percent . '%ORIGIN');
    } elseif (preg_match('/^(\d+(?:\.\d+)?)%FREE$/i', $size, $matches)) {
        $percent = $matches[1];
        $sizeArg = "-l " . escapeshellarg($percent . '%FREE');
    } else {
        if (is_numeric($size)) {
            $size = $size . 'G';
        }
        $sizeArg = "-L " . escapeshellarg($size);
    }
    
    $cmd = "lvcreate -s " . $sizeArg . " -n " . escapeshellarg($finalName) . " " . escapeshellarg($vgName . '/' . $originLvName);
    $result = execCmd($cmd, true, 120);
    
    if (strpos($result, 'successfully created') !== false || 
        strpos($result, 'Logical volume') !== false ||
        strpos($result, 'Snapshot') !== false) {
        
        sleep(1);
        return ['success' => true, 'message' => $lang2114, 'snapshot_name' => $finalName];
    }
    
    return ['success' => false, 'error' => $result];
}

function restoreSnapshot($vgName, $snapshotName, $originLvName = null) {
	global $lang2115, $lang2116, $lang2117;

    if (!$originLvName) {
        $originInfo = execCmd("lvs --noheadings -o origin " . escapeshellarg($vgName . '/' . $snapshotName) . " 2>/dev/null", true, 5);
        $originLvName = trim($originInfo);
        if (empty($originLvName)) {
            return ['success' => false, 'error' => $lang2115 . $snapshotName];
        }
    }
    
    $originPath = "/dev/{$vgName}/{$originLvName}";
    $snapshotPath = "/dev/{$vgName}/{$snapshotName}";
    

    $mountCheck = execCmd("nsenter -t 1 -m cat /proc/mounts | grep -E '^" . preg_quote($originPath, '/') . "\\s' | awk '{print $2}'", true, 5);
    if (!empty($mountCheck)) {
        $mountPoint = trim($mountCheck);
        execCmd("nsenter -t 1 -m umount -f " . escapeshellarg($mountPoint) . " 2>/dev/null", true, 10);
        sleep(1);
    }
    
    execCmd("lvchange -an " . escapeshellarg($originPath) . " 2>/dev/null", true, 10);
    sleep(1);
    
    $cmd = "lvconvert --merge " . escapeshellarg($snapshotPath) . " 2>&1";
    $result = execCmd($cmd, true, 300);
    
    if (strpos($result, 'merging') !== false || 
        strpos($result, 'Merging') !== false ||
        strpos($result, 'successfully') !== false) {
        
        execCmd("vgchange -ay " . escapeshellarg($vgName) . " 2>/dev/null", true, 10);
        
        return ['success' => true, 'message' => $lang2116];
    }
    
    if (strpos($result, 'Cannot merge') !== false) {
        $ddCmd = "dd if=" . escapeshellarg($snapshotPath) . " of=" . escapeshellarg($originPath) . " bs=4M status=progress 2>&1";
        $ddResult = execCmd($ddCmd, true, 600);
        
        if (strpos($ddResult, 'bytes') !== false || strpos($ddResult, 'records') !== false) {
            return ['success' => true, 'message' => $lang2117];
        }
        return ['success' => false, 'error' => $ddResult];
    }
    
    return ['success' => false, 'error' => $result];
}

function deleteSnapshot($vgName, $snapshotName) {
    $snapshotPath = "/dev/{$vgName}/{$snapshotName}";
    
    $mountCheck = execCmd("nsenter -t 1 -m cat /proc/mounts | grep -E '^" . preg_quote($snapshotPath, '/') . "\\s' | awk '{print $2}'", true, 5);
    if (!empty($mountCheck)) {
        $mountPoint = trim($mountCheck);
        execCmd("nsenter -t 1 -m umount -f " . escapeshellarg($mountPoint) . " 2>/dev/null", true, 10);
        sleep(1);
    }
    
    execCmd("lvchange -an " . escapeshellarg($snapshotPath) . " 2>/dev/null", true, 10);
    sleep(1);
    
    $result = execCmd("lvremove -f " . escapeshellarg($vgName . '/' . $snapshotName), true, 60);
    
    if (strpos($result, 'successfully removed') !== false || 
        strpos($result, 'Logical volume') !== false ||
        empty($result)) {
        return ['success' => true];
    }
    
    return ['success' => false, 'error' => $result];
}

function getSnapshotInfo($vgName, $snapshotName) {
    $info = [];
    
    $lvsInfo = execCmd("lvs --noheadings --units g -o lv_name,vg_name,lv_size,lv_attr,origin,data_percent,metadata_percent,copy_percent " . escapeshellarg($vgName . '/' . $snapshotName) . " 2>/dev/null", true, 10);
    
    if (!empty($lvsInfo)) {
        $parts = preg_split('/\s+/', trim($lvsInfo));
        if (count($parts) >= 7) {
            $info = [
                'name' => $parts[0],
                'vg_name' => $parts[1],
                'size' => $parts[2],
                'size_bytes' => parseSize($parts[2]),
                'attr' => $parts[3],
                'origin' => $parts[4],
                'data_percent' => floatval(trim($parts[5], '%')),
                'metadata_percent' => floatval(trim($parts[6], '%')),
                'copy_percent' => isset($parts[7]) ? floatval(trim($parts[7], '%')) : 0,
                'path' => "/dev/{$vgName}/{$snapshotName}"
            ];
            
            $mergeCheck = execCmd("lvs --noheadings -o lv_attr " . escapeshellarg($vgName . '/' . $snapshotName) . " 2>/dev/null | grep -E '[Mm]'", true, 5);
            $info['is_merging'] = !empty($mergeCheck);
            
            $mountPoint = execCmd("nsenter -t 1 -m cat /proc/mounts | grep " . escapeshellarg($info['path']) . " | awk '{print $2}'", true, 5);
            $info['mount_point'] = !empty($mountPoint) ? trim($mountPoint) : null;
        }
    }
    
    return $info;
}

// ==================== ОБРАБОТЧИК ЗАПРОСОВ ====================

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (is_array($input)) {
        $action = $input['action'] ?? $action;
    } else {
        $input = [];
    }
} else {
    $input = $_GET;
}
global $lang2118;
$response = ['success' => false, 'error' => $lang2118];

switch ($action) {
    case 'get_all_lvm':
        $response = [
            'success' => true,
            'disks' => getAllDisks(),
            'pvs' => getAllPVs(),
            'vgs' => getAllVGs(),
            'lvs' => getAllLVs()
        ];
        break;
        
    case 'get_raw_devices':
        $response = ['success' => true, 'devices' => getAllRawDevices()];
        break;
        
    case 'get_available_pvs':
        $response = ['success' => true, 'pvs' => getAvailablePVs()];
        break;
        
    case 'get_pvs_in_vg':
		global $lang2119;
        $vgName = $input['vg_name'] ?? '';
        if (empty($vgName)) {
            $response = ['success' => false, 'error' => $lang2119];
        } else {
            $response = ['success' => true, 'pvs' => getPVsInVG($vgName)];
        }
        break;
        
    case 'get_partition_info':
		global $lang2120;
        $partition = $input['partition'] ?? '';
        if (empty($partition)) {
            $response = ['success' => false, 'error' => $lang2120];
        } else {
            $response = ['success' => true, 'info' => getPartitionInfo($partition)];
        }
        break;
        
    case 'disk_info':
		global $lang2121;
        $disk = $input['disk'] ?? '';
        if (empty($disk)) {
            $response = ['success' => false, 'error' => $lang2121];
        } else {
            $response = ['success' => true, 'info' => getDiskInfo($disk)];
        }
        break;
        
    case 'lvm_create_pv':
		global $lang2122;
        $device = $input['device'] ?? '';
        if (empty($device)) {
            $response = ['success' => false, 'error' => $lang2122];
        } else {
            $response = createPV($device);
        }
        break;
        
    case 'lvm_delete_pv':
		global $lang2123;
        $pvName = $input['pv_name'] ?? '';
        if (empty($pvName)) {
            $response = ['success' => false, 'error' => $lang2123];
        } else {
            $response = deletePV($pvName);
        }
        break;
        
    case 'lvm_create_vg':
		global $lang2124;
        $vgName = $input['vg_name'] ?? '';
        $devices = $input['devices'] ?? [];
        if (empty($vgName) || empty($devices)) {
            $response = ['success' => false, 'error' => $lang2124];
        } else {
            $response = createVG($vgName, $devices);
        }
        break;
        
    case 'lvm_delete_vg':
		global $lang2125;
        $vgName = $input['vg_name'] ?? '';
        if (empty($vgName)) {
            $response = ['success' => false, 'error' => $lang2125];
        } else {
            $response = deleteVG($vgName);
        }
        break;
        
    case 'lvm_extend_vg':
		global $lang2126;
        $vgName = $input['vg_name'] ?? '';
        $devices = $input['devices'] ?? [];
        if (empty($vgName) || empty($devices)) {
            $response = ['success' => false, 'error' => $lang2126];
        } else {
            $response = extendVG($vgName, $devices);
        }
        break;
        
    case 'lvm_reduce_vg':
		global $lang2127;
        $vgName = $input['vg_name'] ?? '';
        $devices = $input['devices'] ?? [];
        if (empty($vgName) || empty($devices)) {
            $response = ['success' => false, 'error' => $lang2127];
        } else {
            $response = reduceVG($vgName, $devices);
        }
        break;
        
    case 'lvm_rename_vg':
		global $lang2128;
        $oldName = $input['old_name'] ?? '';
        $newName = $input['new_name'] ?? '';
        if (empty($oldName) || empty($newName)) {
            $response = ['success' => false, 'error' => $lang2128];
        } else {
            $response = renameVG($oldName, $newName);
        }
        break;
        
    case 'lvm_create_lv':
		global $lang2129;
        $vgName = $input['vg_name'] ?? '';
        $lvName = $input['lv_name'] ?? '';
        $size = $input['size'] ?? '';
        $format = $input['format'] ?? false;
        $fsType = $input['fs_type'] ?? 'ext4';
        if (empty($vgName) || empty($lvName) || empty($size)) {
            $response = ['success' => false, 'error' => $lang2129];
        } else {
            $response = createLV($vgName, $lvName, $size, $format, $fsType);
        }
        break;
        
    case 'lvm_format_lv':
		global $lang2130;
        $lvPath = $input['lv_path'] ?? '';
        $fsType = $input['fs_type'] ?? 'ext4';
        if (empty($lvPath)) {
            $response = ['success' => false, 'error' => $lang2130];
        } else {
            $response = formatLV($lvPath, $fsType);
        }
        break;
        
    case 'lvm_delete_lv':
		global $lang2131;
        $vgName = $input['vg_name'] ?? '';
        $lvName = $input['lv_name'] ?? '';
        if (empty($vgName) || empty($lvName)) {
            $response = ['success' => false, 'error' => $lang2131];
        } else {
            $response = deleteLV($vgName, $lvName);
        }
        break;
        
    case 'lvm_extend_lv':
		global $lang2132;
        $vgName = $input['vg_name'] ?? '';
        $lvName = $input['lv_name'] ?? '';
        $size = $input['size'] ?? '';
        if (empty($vgName) || empty($lvName) || empty($size)) {
            $response = ['success' => false, 'error' => $lang2132];
        } else {
            $response = extendLV($vgName, $lvName, $size);
        }
        break;
        
    case 'lvm_rename_lv':
		global $lang2133;
        $vgName = $input['vg_name'] ?? '';
        $oldName = $input['old_name'] ?? '';
        $newName = $input['new_name'] ?? '';
        if (empty($vgName) || empty($oldName) || empty($newName)) {
            $response = ['success' => false, 'error' => $lang2133];
        } else {
            $response = renameLV($vgName, $oldName, $newName);
        }
        break;
        
    case 'lvm_mount':
		global $lang2134;
        $device = $input['device'] ?? '';
        $mountPoint = $input['mount_point'] ?? '';
        $fs = $input['fs'] ?? 'auto';
        $fstab = $input['fstab'] ?? false;
        if (empty($device) || empty($mountPoint)) {
            $response = ['success' => false, 'error' => $lang2134];
        } else {
            $response = mountLV($device, $mountPoint, $fs, $fstab);
        }
        break;
        
    case 'lvm_umount':
		global $lang2135;
		$mountPoint = $input['mount_point'] ?? '';
		$removeFromFstab = isset($input['remove_from_fstab']) ? filter_var($input['remove_from_fstab'], FILTER_VALIDATE_BOOLEAN) : false;
		
		if (empty($mountPoint)) {
			$response = ['success' => false, 'error' => $lang2135];
		} else {
			$response = umountLV($mountPoint, $removeFromFstab);
		}
		break;
    
	case 'lv_status':
		global $lang2136;
		$lvPath = $input['lv_path'] ?? '';
		if (empty($lvPath)) {
			$response = ['success' => false, 'error' => $lang2136];
		} else {
			$exists = execCmd("lvs " . escapeshellarg($lvPath) . " 2>/dev/null", true, 5);
			$active = execCmd("lvs --noheadings -o lv_attr " . escapeshellarg($lvPath) . " 2>/dev/null", true, 5);
			$hasFs = execCmd("blkid -s TYPE -o value " . escapeshellarg($lvPath) . " 2>/dev/null", true, 5);
			
			$response = [
				'success' => true,
				'exists' => !empty($exists),
				'active' => !empty($active) && strpos($active, 'a') !== false,
				'has_filesystem' => !empty($hasFs),
				'filesystem' => $hasFs ?: null
			];
		}
		break;
    
	case 'get_lv_info':
		global $lang2137, $lang2138;
		$lvPath = $input['lv_path'] ?? '';
		if (empty($lvPath)) {
			$response = ['success' => false, 'error' => $lang2137];
		} else {
			$info = getLVInfo($lvPath);
			if ($info) {
				$response = ['success' => true, 'info' => $info];
			} else {
				$response = ['success' => false, 'error' => $lang2138];
			}
		}
		break;
	
	case 'get_snapshots':
		$response = ['success' => true, 'snapshots' => getAllSnapshots()];
		break;
		
	case 'get_snapshot_info':
		global $lang2139, $lang2140;
		$vgName = $input['vg_name'] ?? '';
		$snapshotName = $input['snapshot_name'] ?? '';
		if (empty($vgName) || empty($snapshotName)) {
			$response = ['success' => false, 'error' => $lang2139];
		} else {
			$info = getSnapshotInfo($vgName, $snapshotName);
			if (!empty($info)) {
				$response = ['success' => true, 'info' => $info];
			} else {
				$response = ['success' => false, 'error' => $lang2140];
			}
		}
		break;
		
	case 'create_snapshot':
		global $lang2141;
		$vgName = $input['vg_name'] ?? '';
		$originLv = $input['origin_lv'] ?? '';
		$snapshotName = $input['snapshot_name'] ?? '';
		$size = $input['size'] ?? '10G';
		
		if (empty($vgName) || empty($originLv) || empty($snapshotName)) {
			$response = ['success' => false, 'error' => $lang2141];
		} else {
			$response = createSnapshot($vgName, $originLv, $snapshotName, $size);
		}
		break;
		
	case 'restore_snapshot':
		global $lang2142;
		$vgName = $input['vg_name'] ?? '';
		$snapshotName = $input['snapshot_name'] ?? '';
		$originLv = $input['origin_lv'] ?? null;
		
		if (empty($vgName) || empty($snapshotName)) {
			$response = ['success' => false, 'error' => $lang2142];
		} else {
			$response = restoreSnapshot($vgName, $snapshotName, $originLv);
		}
		break;
		
	case 'delete_snapshot':
		global $lang2143;
		$vgName = $input['vg_name'] ?? '';
		$snapshotName = $input['snapshot_name'] ?? '';
		
		if (empty($vgName) || empty($snapshotName)) {
			$response = ['success' => false, 'error' => $lang2143];
		} else {
			$response = deleteSnapshot($vgName, $snapshotName);
		}
		break;
	
	case 'get_disk_usage':
		global $lang2144, $lang2145, $lang2146;
		$path = $input['path'] ?? '';
		if (empty($path)) {
			$response = ['success' => false, 'error' => $lang2144];
		} else {
			$df = execCmd("df -B1 " . escapeshellarg($path) . " 2>/dev/null | tail -1", true, 5);
			if (!empty($df)) {
				$parts = preg_split('/\s+/', $df);
				if (count($parts) >= 6) {
					$total = (int)$parts[1];
					$used = (int)$parts[2];
					$available = (int)$parts[3];
					$percent = $total > 0 ? round(($used / $total) * 100, 1) : 0;
					
					$response = [
						'success' => true,
						'usage' => [
							'total' => $total,
							'total_formatted' => formatSize($total),
							'used' => $used,
							'used_formatted' => formatSize($used),
							'available' => $available,
							'available_formatted' => formatSize($available),
							'percent' => $percent,
							'mount_point' => $parts[5]
						]
					];
				} else {
					$response = ['success' => false, 'error' => $lang2145];
				}
			} else {
				$response = ['success' => false, 'error' => $lang2146];
			}
		}
		break;
	
	case 'get_raw_devices_with_status':
		$allPVs = getAllPVs();
		$pvPaths = [];
		foreach ($allPVs as $pv) {
			$pvPaths[] = $pv['name'];
		}
		
		$rootDisk = getRootDisk();
		$devices = [];
		
		$lsblkJson = execCmd("lsblk -J -p -o NAME,TYPE,SIZE,MODEL,FSTYPE 2>/dev/null", true, 10);
		
		if (!empty($lsblkJson)) {
			$data = json_decode($lsblkJson, true);
			if ($data && isset($data['blockdevices'])) {
				foreach ($data['blockdevices'] as $device) {
					$name = $device['name'];
					$type = $device['type'];
					$size = $device['size'] ?? '0';
					$model = $device['model'] ?? null;
					$fstype = $device['fstype'] ?? null;
					
					if ($type === 'rom') continue;
					if (strpos($name, 'loop') !== false) continue;
					if (strpos($name, 'dm-') !== false) continue;
					if (strpos($name, 'mapper') !== false) continue;
					if (strpos($name, '--') !== false) continue;
					
					if (preg_match('/\/dev\/mapper\//', $name)) continue;
					if (preg_match('/-cow$/', $name)) continue;
					if (preg_match('/-real$/', $name)) continue;
					if (preg_match('/snap_/', $name)) continue;
					
					$devicePath = $name;
					$shortName = str_replace('/dev/', '', $name);
					$isPV = in_array($devicePath, $pvPaths);
					$isSystem = ($shortName === $rootDisk);
					
					$isPVDevice = $isPV || ($fstype === 'LVM2_member');
					
					$devices[] = [
						'name' => $shortName,
						'path' => $devicePath,
						'size_formatted' => $size,
						'type' => 'disk',
						'is_pv' => $isPVDevice,
						'is_system' => $isSystem,
						'fstype' => ($fstype && $fstype !== 'LVM2_member') ? $fstype : null,
						'model' => $model,
						'disabled_reason' => $isPVDevice ? 'already_pv' : ($isSystem ? 'system' : null)
					];
					
					if (isset($device['children']) && is_array($device['children'])) {
						foreach ($device['children'] as $part) {
							$partName = $part['name'];
							$partType = $part['type'];
							$partSize = $part['size'] ?? '0';
							$partFstype = $part['fstype'] ?? null;
							
							if (strpos($partName, 'mapper') !== false) continue;
							if (strpos($partName, 'dm-') !== false) continue;
							if (strpos($partName, '--') !== false) continue;
							
							if ($partType === 'extended') continue;
							
							$partPath = $partName;
							$partShortName = str_replace('/dev/', '', $partName);
							$partIsPV = in_array($partPath, $pvPaths);
							
							$isPVPart = $partIsPV || ($partFstype === 'LVM2_member');
							
							$devices[] = [
								'name' => $partShortName,
								'path' => $partPath,
								'size_formatted' => $partSize,
								'type' => 'partition',
								'is_pv' => $isPVPart,
								'is_system' => false,
								'fstype' => ($partFstype && $partFstype !== 'LVM2_member') ? $partFstype : null,
								'model' => null,
								'disabled_reason' => $isPVPart ? 'already_pv' : null,
								'parent_disk' => $shortName
							];
						}
					}
				}
			}
		}
		
		foreach ($allPVs as $pv) {
			$pvPath = $pv['name'];
			$pvName = str_replace('/dev/', '', $pvPath);
			
			$exists = false;
			foreach ($devices as $dev) {
				if ($dev['path'] === $pvPath) {
					$exists = true;
					break;
				}
			}
			
			if (!$exists) {
				$sizeInfo = execCmd("lsblk -p -n -o SIZE " . escapeshellarg($pvPath) . " 2>/dev/null | head -1", true, 5);
				$devices[] = [
					'name' => $pvName,
					'path' => $pvPath,
					'size_formatted' => trim($sizeInfo) ?: '0',
					'type' => 'disk',
					'is_pv' => true,
					'is_system' => false,
					'fstype' => null,
					'model' => null,
					'disabled_reason' => 'already_pv'
				];
			}
		}
		
		$response = ['success' => true, 'devices' => $devices];
		break;
	
    default:
		global $lang2147;
        $response = ['success' => false, 'error' => $lang2147 . $action];
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>