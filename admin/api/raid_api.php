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

$cacheFile = '/tmp/raid_api_cache.json';
$cacheTTL = 3;

function getCached($key, $ttl = 3) {
    global $cacheFile;
    if (!file_exists($cacheFile)) return null;
    
    $data = @json_decode(file_get_contents($cacheFile), true);
    if (!$data || !isset($data[$key])) return null;
    
    if (time() - $data[$key]['time'] > $ttl) return null;
    
    return $data[$key]['value'];
}

function setCached($key, $value) {
    global $cacheFile;
    $data = [];
    if (file_exists($cacheFile)) {
        $data = json_decode(file_get_contents($cacheFile), true) ?: [];
    }
    $data[$key] = ['time' => time(), 'value' => $value];
    file_put_contents($cacheFile, json_encode($data));
}

function clearCache() {
    global $cacheFile;
    @unlink($cacheFile);
}

function execCmd($cmd, $sudo = true, $timeout = 30) {
    $fullCmd = $sudo ? "sudo " . $cmd : $cmd;
    $fullCmd .= " 2>&1";
    
    $descriptorspec = [
        0 => ["pipe", "r"],
        1 => ["pipe", "w"],
        2 => ["pipe", "w"]
    ];
    
    $process = @proc_open($fullCmd, $descriptorspec, $pipes);
    
    if (!is_resource($process)) {
        return '';
    }
    
    @stream_set_blocking($pipes[1], 0);
    @stream_set_blocking($pipes[2], 0);
    
    $start = time();
    $output = '';
    $stderr = '';
    
    while (true) {
        $read = [$pipes[1], $pipes[2]];
        $write = null;
        $except = null;
        
        @stream_select($read, $write, $except, 0, 200000);
        
        if (!empty($read)) {
            foreach ($read as $stream) {
                $chunk = @fread($stream, 8192);
                if ($chunk !== false && $chunk !== '') {
                    if ($stream === $pipes[1]) {
                        $output .= $chunk;
                    } else {
                        $stderr .= $chunk;
                    }
                }
            }
        }
        
        $status = @proc_get_status($process);
        
        if (!$status['running']) {
            while (($chunk = @fread($pipes[1], 8192)) !== false && $chunk !== '') {
                $output .= $chunk;
            }
            while (($chunk = @fread($pipes[2], 8192)) !== false && $chunk !== '') {
                $stderr .= $chunk;
            }
            break;
        }
        
        if (time() - $start > $timeout) {
            @proc_terminate($process, 9);
            usleep(100000);
            break;
        }
        
        usleep(50000);
    }
    
    @fclose($pipes[0]);
    @fclose($pipes[1]);
    @fclose($pipes[2]);
    @proc_close($process);
    
    $result = trim($output);
    if (empty($result) && !empty($stderr)) {
        $result = trim($stderr);
    }
    
    return $result;
}

function execLight($cmd, $timeout = 5) {
    return execCmd($cmd, false, $timeout);
}

function formatSize($bytes) {
    if ($bytes >= 1099511627776) return round($bytes / 1099511627776, 2) . ' TB';
    if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return round($bytes / 1024, 2) . ' KB';
    return $bytes . ' B';
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

function getMountPoints() {
    $mounts = [];
    $output = execLight("cat /proc/mounts | grep '/dev/md' 2>/dev/null", 2);
    if (!empty($output)) {
        $lines = explode("\n", $output);
        foreach ($lines as $line) {
            if (preg_match('/^(\/dev\/md\d+)\s+(\/[^\s]+)\s+(\S+)/', $line, $matches)) {
                $mounts[$matches[1]] = [
                    'device' => $matches[1],
                    'mount_point' => $matches[2],
                    'fstype' => $matches[3]
                ];
            }
        }
    }
    return $mounts;
}

function raidExists($mdName) {
    $mdstat = execLight("cat /proc/mdstat 2>/dev/null | grep '^" . preg_quote($mdName, '/') . " '", 2);
    return !empty($mdstat);
}

function getRaidHealth($mdName) {
    $health = [
        'state' => 'unknown',
        'degraded' => false,
        'read_only' => false,
        'failed_disks' => 0,
        'working_disks' => 0,
        'sync_progress' => null,
        'sync_action' => null,
        'disk_states' => []
    ];
    
    $mdstat = execLight("cat /proc/mdstat 2>/dev/null | grep -A1 '^" . $mdName . " '", 2);
    
	$detail = execCmd("mdadm --detail /dev/" . escapeshellarg($mdName) . " 2>/dev/null", true, 10);
    if (!empty($detail)) {
        if (preg_match('/State\s+:\s+(\S+)/', $detail, $matches)) {
            $state = $matches[1];
            if ($state === 'clean' || $state === 'active') {
                $health['read_only'] = false;
                $health['state'] = $state;
            }
        }
    }
	
    if (!empty($mdstat)) {
        if (strpos($mdstat, 'auto-read-only') !== false) {
            $health['read_only'] = true;
            $health['state'] = 'auto-read-only';
        }
        
        if (preg_match('/\[(\d+)\/(\d+)\]/', $mdstat, $matches)) {
            $present = (int)$matches[1];
            $expected = (int)$matches[2];
            $health['degraded'] = ($present < $expected);
            $health['working_disks'] = $present;
        }
        
        if (preg_match('/\[([U_]+)\]/', $mdstat, $diskMatches)) {
            $states = str_split($diskMatches[1]);
            $health['failed_disks'] = count(array_filter($states, function($s) { 
                return $s === '_'; 
            }));
        }
    }
    
    $detail = execCmd("mdadm --detail /dev/" . escapeshellarg($mdName) . " 2>/dev/null", true, 10);
    if (!empty($detail)) {
        preg_match_all('/\/(dev\/[a-z0-9]+)\s+:\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\w+)/', $detail, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $health['disk_states'][] = [
                'device' => $match[1],
                'number' => $match[2],
                'major' => $match[3],
                'minor' => $match[4],
                'raid_disk' => $match[5],
                'state' => $match[6]
            ];
        }
        
        if ($health['failed_disks'] === 0 && !empty($health['disk_states'])) {
            $failedCount = 0;
            foreach ($health['disk_states'] as $disk) {
                if (strpos($disk['state'], 'F') !== false || strpos($disk['state'], 'f') !== false) {
                    $failedCount++;
                }
            }
            $health['failed_disks'] = $failedCount;
            $health['working_disks'] = count($health['disk_states']) - $failedCount;
            $health['degraded'] = ($failedCount > 0);
        }
        
        $arrayState = execLight("cat /sys/block/" . $mdName . "/md/array_state 2>/dev/null", 2);
        if (!empty($arrayState) && $health['state'] === 'unknown') {
            $health['state'] = trim($arrayState);
        }
    }
    
    $syncAction = execLight("cat /sys/block/" . $mdName . "/md/sync_action 2>/dev/null", 2);
    if (!empty($syncAction) && trim($syncAction) != 'idle') {
        $health['sync_action'] = trim($syncAction);
        $syncCompleted = execLight("cat /sys/block/" . $mdName . "/md/sync_completed 2>/dev/null", 2);
        if (!empty($syncCompleted) && strpos($syncCompleted, '/') !== false) {
            list($completed, $total) = explode('/', $syncCompleted);
            if ($total > 0) {
                $health['sync_progress'] = round(($completed / $total) * 100, 1);
            }
        }
    }
    
    return $health;
}

function getLVGInfoForRaid($mdName) {
    $devicePath = '/dev/' . $mdName;
    $result = [
        'is_pv' => false,
        'vg_name' => null,
        'vg_size' => null,
        'lvs' => [],
        'pv_uuid' => null,
        'error' => null
    ];
    
    $pvCheck = execCmd("pvs --noheadings -o pv_name,pv_uuid,vg_name,vg_size " . escapeshellarg($devicePath) . " 2>/dev/null | grep -v 'WARNING'", true, 10);
    
    if (!empty(trim($pvCheck))) {
        $parts = preg_split('/\s+/', trim($pvCheck));
        if (count($parts) >= 4) {
            $result['is_pv'] = true;
            $result['pv_uuid'] = $parts[1];
            $result['vg_name'] = $parts[2];
            $result['vg_size'] = $parts[3];
            
            if (!empty($result['vg_name']) && $result['vg_name'] !== '' && $result['vg_name'] !== ' ') {
                $lvsOutput = execCmd("lvs --noheadings -o lv_name,lv_size " . escapeshellarg($result['vg_name']) . " 2>/dev/null | grep -v 'WARNING'", true, 10);
                if (!empty($lvsOutput)) {
                    $lines = explode("\n", trim($lvsOutput));
                    foreach ($lines as $line) {
                        $line = trim($line);
                        if (empty($line)) continue;
                        $lvParts = preg_split('/\s+/', $line);
                        if (count($lvParts) >= 2) {
                            $result['lvs'][] = [
                                'name' => $lvParts[0],
                                'size' => $lvParts[1]
                            ];
                        }
                    }
                }
            }
        }
    }
    
    return $result;
}

function isRaidUsedInLVM($mdName) {
    $devicePath = '/dev/' . $mdName;
    $pvCheck = execCmd("pvs --noheadings -o pv_name " . escapeshellarg($devicePath) . " 2>/dev/null | grep -v 'WARNING'", true, 5);
    return !empty(trim($pvCheck));
}

function hasPartitions($mdName) {
    $partitions = execLight("ls /dev/" . escapeshellarg($mdName) . "p* 2>/dev/null | wc -l", 2);
    return intval($partitions) > 0;
}

function getAllRaidArrays() {
    $cached = getCached('raid_arrays', 3);
    if ($cached !== null) {
        return $cached;
    }
    
    $arrays = [];
    $mounts = getMountPoints();
    
    $mdstat = execLight("cat /proc/mdstat 2>/dev/null", 2);
    if (empty($mdstat)) {
        return [];
    }
    
    $lines = explode("\n", $mdstat);
    $currentArray = null;
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        
        if (strpos($line, 'Personalities') !== false) continue;
        if (strpos($line, 'unused devices') !== false) continue;
        
        if (preg_match('/^(md\d+)\s+:\s+(.+)$/', $line, $matches)) {
            if ($currentArray !== null) {
                $arrays[] = $currentArray;
            }
            
            $name = $matches[1];
            $rest = $matches[2];
            
            $status = 'active';
            $raidType = '';
            $devicesStr = $rest;
            $readOnly = false;
            $degraded = false;
            $workingDisks = 0;
            $totalDisks = 0;
            $failedDisks = 0;
            $goodDisks = 0;
            
            if (strpos($rest, 'auto-read-only') !== false) {
                $readOnly = true;
                $status = 'auto-read-only';
            }
            
            if (preg_match('/\b(raid[016]|raid10|linear|multipath)\b/', $rest, $typeMatches)) {
                $raidType = $typeMatches[1];
            }
            
            if (preg_match('/\[(\d+)\/(\d+)\]/', $rest, $diskCountMatches)) {
                $workingDisks = (int)$diskCountMatches[1];
                $totalDisks = (int)$diskCountMatches[2];
                $degraded = ($workingDisks < $totalDisks);
            }
            
            if (preg_match('/\[([U_]+)\]/', $rest, $diskStateMatches)) {
                $states = str_split($diskStateMatches[1]);
                $failedDisks = count(array_filter($states, function($s) { return $s === '_'; }));
                $goodDisks = count(array_filter($states, function($s) { return $s === 'U'; }));
            }
            
            $devices = [];
            if (preg_match_all('/([a-z]+[0-9]+[p]?[0-9]*|sd[a-z]+|hd[a-z]+|vd[a-z]+|nvme\d+n\d+p?\d*)\[(\d+)\](?:\(S\))?/', $devicesStr, $deviceMatches)) {
                foreach ($deviceMatches[1] as $idx => $devName) {
                    $devices[] = [
                        'name' => $devName,
                        'slot' => $deviceMatches[2][$idx] ?? $idx,
                        'spare' => (strpos($devicesStr, $devName . '[' . $deviceMatches[2][$idx] . '](S)') !== false)
                    ];
                }
            }
            
            $level = '';
            switch ($raidType) {
                case 'raid0': $level = 'RAID 0'; break;
                case 'raid1': $level = 'RAID 1'; break;
                case 'raid4': $level = 'RAID 4'; break;
                case 'raid5': $level = 'RAID 5'; break;
                case 'raid6': $level = 'RAID 6'; break;
                case 'raid10': $level = 'RAID 10'; break;
                case 'linear': $level = 'LINEAR'; break;
                case 'multipath': $level = 'MULTIPATH'; break;
                default:
                    if (preg_match('/^raid(\d+)$/', $raidType, $rMatches)) {
                        $level = 'RAID ' . $rMatches[1];
                    } else {
                        $level = strtoupper($raidType ?: 'UNKNOWN');
                    }
            }
            
            $currentArray = [
                'name' => $name,
                'path' => '/dev/' . $name,
                'level' => $level,
                'raid_type' => $raidType,
                'status' => $status,
                'read_only' => $readOnly,
                'degraded' => $degraded,
                'devices' => $devices,
                'working_disks' => $workingDisks ?: count($devices),
                'total_disks' => $totalDisks,
                'failed_disks' => $failedDisks,
                'good_disks' => $goodDisks,
                'spare_disks' => 0,
                'sync_percent' => null,
                'sync_speed' => null,
                'sync_action' => null,
                'sync_time_left' => null,
                'size' => null,
                'size_bytes' => 0,
                'mount_point' => isset($mounts['/dev/' . $name]) ? $mounts['/dev/' . $name]['mount_point'] : null,
                'is_mounted' => isset($mounts['/dev/' . $name]),
                'health_state' => $status,
                'in_lvm' => false,
                'lvm_vg' => null,
                'has_partitions' => false,
                'disk_states' => []
            ];
        }
		
        elseif ($currentArray !== null && preg_match('/\[[=>\s]+\]\s+(\d+\.?\d*)%/', $line, $syncMatches)) {
            $currentArray['sync_percent'] = floatval($syncMatches[1]);
        }
        elseif ($currentArray !== null && preg_match('/finish=([\d\.]+)min/', $line, $timeMatches)) {
            $currentArray['sync_time_left'] = $timeMatches[1] . ' min';
        }
        elseif ($currentArray !== null && preg_match('/speed=(\d+)K\/sec/', $line, $speedMatches)) {
            $currentArray['sync_speed'] = $speedMatches[1] . ' KB/s';
        }
        elseif ($currentArray !== null && preg_match('/(checking|recovery|reshape|repair)/', $line, $actionMatches)) {
            $action = $actionMatches[1];
            switch($action) {
                case 'checking': $currentArray['sync_action'] = 'check'; break;
                case 'recovery': $currentArray['sync_action'] = 'recovery'; break;
                case 'reshape': $currentArray['sync_action'] = 'reshape'; break;
                case 'repair': $currentArray['sync_action'] = 'repair'; break;
                default: $currentArray['sync_action'] = $action;
            }
        }
    }
    
    if ($currentArray !== null) {
        $arrays[] = $currentArray;
    }
    
    $validArrays = [];
    foreach ($arrays as $array) {
        $detail = execCmd("mdadm --detail /dev/" . escapeshellarg($array['name']) . " 2>/dev/null", true, 5);
        if (!empty($detail) && strpos($detail, 'State :') !== false) {
            if (strpos($detail, 'State : inactive') === false && 
                strpos($detail, 'State : faulty') === false) {
                $validArrays[] = $array;
            }
        } else {
            $mdstatCheck = execLight("cat /proc/mdstat | grep '^" . $array['name'] . " :'", 2);
            if (!empty($mdstatCheck)) {
                $validArrays[] = $array;
            }
        }
    }
    
    foreach ($validArrays as &$array) {

        $sizeFromProc = execLight("cat /proc/partitions | grep ' " . $array['name'] . "$' | awk '{print $3}'", 2);
        if (!empty($sizeFromProc)) {
            $sizeBytes = intval($sizeFromProc) * 1024;
            $array['size_bytes'] = $sizeBytes;
            $array['size'] = formatSize($sizeBytes);
        } else {
            $array['size'] = 'N/A';
        }
        

        $health = getRaidHealth($array['name']);
        $array['degraded'] = $health['degraded'] || $array['degraded'];
        $array['failed_disks'] = $health['failed_disks'] ?: $array['failed_disks'];
        $array['health_state'] = $health['state'];
        $array['disk_states'] = $health['disk_states'];
        $array['read_only'] = $health['read_only'] || $array['read_only'];
        
        if ($health['sync_progress']) {
            $array['sync_percent'] = $health['sync_progress'];
        }
        if ($health['sync_action']) {
            $array['sync_action'] = $health['sync_action'];
        }
        

        $array['mount_point'] = isset($mounts['/dev/' . $array['name']]) ? $mounts['/dev/' . $array['name']]['mount_point'] : null;
        $array['is_mounted'] = isset($mounts['/dev/' . $array['name']]);
        

        $array['in_lvm'] = isRaidUsedInLVM($array['name']);
        if ($array['in_lvm']) {
            $lvmInfo = getLVGInfoForRaid($array['name']);
            $array['lvm_vg'] = $lvmInfo['vg_name'];
            $array['lvm_lvs'] = $lvmInfo['lvs'];
        }
        
        $array['has_partitions'] = hasPartitions($array['name']);
    }
    
    setCached('raid_arrays', $validArrays);
    
    return $validArrays;
}

function getAvailableDisksForRaid() {
    $cached = getCached('available_disks', 5);
    if ($cached !== null) {
        return $cached;
    }
    
    $disks = [];
    
    $lsblkJson = execLight("lsblk -J -p -o NAME,TYPE,SIZE,MOUNTPOINT,FSTYPE,ROTA,MODEL 2>/dev/null", 5);
    
    if (empty($lsblkJson)) {
        return [];
    }
    
    $data = json_decode($lsblkJson, true);
    if (!$data || !isset($data['blockdevices'])) {
        return [];
    }
    
    $pvs = execCmd("pvs --noheadings -o pv_name 2>/dev/null | grep -v 'WARNING' | head -20", true, 3);
    $lvmDevices = [];
    if (!empty($pvs)) {
        $lines = explode("\n", trim($pvs));
        foreach ($lines as $line) {
            $device = trim($line);
            if (!empty($device)) {
                $lvmDevices[] = basename($device);
            }
        }
    }
    
    $mdstatForDevices = execLight("cat /proc/mdstat 2>/dev/null", 2);
    $raidMembers = [];
    if (!empty($mdstatForDevices)) {
        preg_match_all('/(sd[a-z]+[0-9]*|hd[a-z]+[0-9]*|vd[a-z]+[0-9]*|nvme[0-9]+n[0-9]+p?[0-9]*)\[(\d+)\]/', $mdstatForDevices, $matches);
        if (!empty($matches[1])) {
            $raidMembers = array_unique($matches[1]);
        }
    }
    
    $mountOutput = execLight("mount | grep -E '^/dev/' | awk '{print $1}' | head -30", 3);
    $mountedDevices = [];
    if (!empty($mountOutput)) {
        $lines = explode("\n", trim($mountOutput));
        foreach ($lines as $line) {
            $dev = str_replace('/dev/', '', trim($line));
            if (!empty($dev)) {
                $mountedDevices[] = $dev;
            }
        }
    }
    
    function processDevice($device, &$disks, $raidMembers, $lvmDevices, $mountedDevices, $parentDisk = null) {
        $name = $device['name'];
        $type = $device['type'];
        $size = $device['size'] ?? '0';
        $mountPoint = $device['mountpoint'] ?? null;
        $fstype = $device['fstype'] ?? null;
        $rota = $device['rota'] ?? 1;
        $model = $device['model'] ?? '';
        
        $shortName = str_replace('/dev/', '', $name);
        
        if ($type === 'rom') return;
        if (strpos($shortName, 'loop') !== false) return;
        if (strpos($shortName, 'dm-') !== false) return;
        
        $isRaid = in_array($shortName, $raidMembers);
        $isRaidArray = preg_match('/^md\d+$/', $shortName);
        $isLvm = in_array($shortName, $lvmDevices);
        $isMounted = !empty($mountPoint) || in_array($shortName, $mountedDevices);
        $hasFilesystem = !empty($fstype) && $fstype !== 'LVM2_member' && $fstype !== 'linux_raid_member';
        
        if ($isRaidArray) {
            $available = true;
        } else {
            $available = !$isRaid && !$isLvm && !$isMounted && !$hasFilesystem && $type !== 'rom';
        }
        
        $disks[] = [
            'name' => $shortName,
            'path' => $name,
            'size' => $size,
            'size_bytes' => parseSize($size),
            'type' => $type === 'disk' ? 'disk' : ($type === 'part' ? 'partition' : $type),
            'available' => $available,
            'is_raid' => $isRaid,
            'is_raid_array' => $isRaidArray,
            'is_lvm' => $isLvm,
            'is_mounted' => $isMounted,
            'has_filesystem' => $hasFilesystem,
            'fstype' => $fstype,
            'parent_disk' => $parentDisk,
            'rota' => $rota == 1,
            'model' => $model
        ];
    }
    
    foreach ($data['blockdevices'] as $device) {
        processDevice($device, $disks, $raidMembers, $lvmDevices, $mountedDevices);
        
        if (isset($device['children']) && is_array($device['children'])) {
            foreach ($device['children'] as $part) {
                processDevice($part, $disks, $raidMembers, $lvmDevices, $mountedDevices, str_replace('/dev/', '', $device['name']));
            }
        }
    }
    
    usort($disks, function($a, $b) {
        if ($a['available'] !== $b['available']) {
            return $a['available'] ? -1 : 1;
        }
        if ($a['type'] !== $b['type']) {
            return $a['type'] === 'disk' ? -1 : 1;
        }
        return strcmp($a['name'], $b['name']);
    });
    
    setCached('available_disks', $disks);
    
    return $disks;
}

function getRaidCandidates() {
    $cached = getCached('raid_candidates', 5);
    if ($cached !== null) {
        return $cached;
    }
    
    $candidates = [];
    $disks = getAvailableDisksForRaid();
    
    foreach ($disks as $disk) {
        if ($disk['available'] && !$disk['is_raid_array']) {
            $candidates[] = [
                'name' => $disk['name'],
                'size' => $disk['size'],
                'type' => $disk['type']
            ];
        }
    }
    
    setCached('raid_candidates', $candidates);
    
    return $candidates;
}

function getBrokenRaids() {
    $broken = [];
    $mdstat = execLight("cat /proc/mdstat 2>/dev/null", 5);
    
    if (empty($mdstat)) {
        return $broken;
    }
    
    $lines = explode("\n", $mdstat);
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        if (strpos($line, 'Personalities') !== false) continue;
        if (strpos($line, 'unused devices') !== false) continue;
        
        if (strpos($line, 'inactive') !== false) {
            preg_match('/^(md\d+)\s+:\s+inactive\s+(.+)$/', $line, $matches);
            if (!empty($matches[1])) {
                $mdName = $matches[1];
                $devicesStr = $matches[2] ?? '';
                
                $disks = [];
                preg_match_all('/(sd[a-z]+|hd[a-z]+|vd[a-z]+|nvme[0-9]+n[0-9]+p?[0-9]*)\[(\d+)\](?:\(S\))?/', $devicesStr, $deviceMatches);
                if (!empty($deviceMatches[1])) {
                    foreach ($deviceMatches[1] as $idx => $diskName) {
                        $disks[] = [
                            'name' => $diskName,
                            'slot' => $deviceMatches[2][$idx] ?? $idx,
                            'spare' => (strpos($devicesStr, $diskName . '[' . $deviceMatches[2][$idx] . '](S)') !== false)
                        ];
                    }
                }
                
                preg_match('/(\d+)\s+blocks/', $devicesStr, $sizeMatch);
                $size = $sizeMatch[1] ?? 0;
                
                $broken[] = [
                    'name' => $mdName,
                    'status' => 'inactive',
                    'devices' => $disks,
                    'size' => formatSize($size * 1024),
                    'size_bytes' => $size * 1024
                ];
            }
        }
    }
    
    return $broken;
}

function createRaid($name, $level, $devices, $spare = [], $chunk = null) {
    if (!preg_match('/^md\d+$/', $name)) {
        return ['success' => false, 'error' => 'Invalid RAID name. Use md0, md1, etc.'];
    }
    
    $mdstat = execCmd("cat /proc/mdstat 2>/dev/null | grep '^" . preg_quote($name, '/') . " '", true, 5);
    if (!empty($mdstat)) {
        return ['success' => false, 'error' => 'RAID array ' . $name . ' already exists'];
    }
    
    $minDisks = ['0' => 2, '1' => 2, '4' => 3, '5' => 3, '6' => 4, '10' => 4, 'linear' => 2];
    if (count($devices) < ($minDisks[$level] ?? 2)) {
        return ['success' => false, 'error' => 'RAID ' . $level . ' requires at least ' . ($minDisks[$level] ?? 2) . ' devices'];
    }
    
    $allDevices = array_merge($devices, $spare);
    
    foreach ($allDevices as $device) {
        $devicePath = "/dev/" . $device;
        
        if (!file_exists($devicePath)) {
            return ['success' => false, 'error' => "Device {$devicePath} not found"];
        }
        
        execCmd("fuser -km " . escapeshellarg($devicePath) . " 2>/dev/null", true, 5);
        
        $isMounted = false;
        $mountPoint = '';
        $procMounts = execCmd("cat /proc/mounts 2>/dev/null", false, 5);
        if (!empty($procMounts)) {
            $lines = explode("\n", $procMounts);
            foreach ($lines as $line) {
                if (strpos($line, $devicePath) === 0 || strpos($line, $device . ' ') === 0) {
                    $parts = preg_split('/\s+/', trim($line));
                    if (count($parts) >= 2) {
                        $isMounted = true;
                        $mountPoint = $parts[1];
                        break;
                    }
                }
            }
        }
        
        if ($isMounted) {
            execCmd("umount -l " . escapeshellarg($devicePath) . " 2>/dev/null", true, 10);
            execCmd("umount -f " . escapeshellarg($devicePath) . " 2>/dev/null", true, 10);
            sleep(1);
            
            $stillMounted = execCmd("cat /proc/mounts | grep -E '^" . preg_quote($devicePath, '/') . "\\s'", false, 5);
            if (!empty($stillMounted)) {
                return ['success' => false, 'error' => "Device {$device} is still mounted at {$mountPoint} and cannot be unmounted"];
            }
        }
        
        $pvCheck = execCmd("pvs --noheadings -o pv_name 2>/dev/null | grep '" . preg_quote($devicePath, '/') . "'", true, 5);
        if (!empty($pvCheck)) {
            execCmd("pvremove -ff " . escapeshellarg($devicePath) . " 2>/dev/null", true, 10);
            sleep(1);
            
            $pvCheckAgain = execCmd("pvs --noheadings -o pv_name 2>/dev/null | grep '" . preg_quote($devicePath, '/') . "'", true, 5);
            if (!empty($pvCheckAgain)) {
                return ['success' => false, 'error' => "Device {$device} is still used as Physical Volume. Please remove it from LVM first via LVM Manager."];
            }
        }
        
        $raidCheck = execCmd("mdadm --examine " . escapeshellarg($devicePath) . " 2>/dev/null | grep -i 'raid'", true, 5);
        if (!empty($raidCheck)) {
            execCmd("mdadm --stop " . escapeshellarg($devicePath) . " 2>/dev/null", true, 5);
            execCmd("mdadm --zero-superblock --force " . escapeshellarg($devicePath) . " 2>/dev/null", true, 10);
            execCmd("mdadm --zero-superblock --force " . escapeshellarg($devicePath) . " 2>/dev/null", true, 10);
        }
        
        execCmd("wipefs --all --force " . escapeshellarg($devicePath) . " 2>/dev/null", true, 10);
        execCmd("wipefs --all --force " . escapeshellarg($devicePath) . " 2>/dev/null", true, 10);
        
        execCmd("dd if=/dev/zero of=" . escapeshellarg($devicePath) . " bs=1M count=20 2>/dev/null", true, 30);
        
        execCmd("blockdev --rereadpt " . escapeshellarg($devicePath) . " 2>/dev/null", true, 5);
        
        execCmd("udevadm settle 2>/dev/null", true, 5);
        sleep(1);
    }
    
    foreach ($allDevices as $device) {
        $devicePath = "/dev/" . $device;
        $writeTest = execCmd("dd if=/dev/zero of=" . escapeshellarg($devicePath) . " bs=1M count=1 2>&1", true, 10);
        if (strpos($writeTest, 'error') !== false || strpos($writeTest, 'cannot') !== false) {
            return ['success' => false, 'error' => "Device {$device} is not writable: " . $writeTest];
        }
    }
    
    $cmd = "mdadm --create /dev/" . escapeshellarg($name) . 
           " --level=" . escapeshellarg($level) . 
           " --raid-devices=" . count($devices) . 
           " --metadata=1.2";
    
    if ($chunk && in_array($level, ['0', '5', '6', '10'])) {
        $cmd .= " --chunk=" . intval($chunk);
    }
    
    foreach ($devices as $device) {
        $cmd .= " /dev/" . escapeshellarg($device);
    }
    
    foreach ($spare as $device) {
        $cmd .= " spare /dev/" . escapeshellarg($device);
    }
    
    $cmd .= " --force --run";
    
    error_log("RAID create cmd: " . $cmd);
    $result = execCmd($cmd, true, 120);
    error_log("RAID create result: " . $result);
    
    $successPatterns = ['Created', 'has been started', 'Started', 'array', 'successful'];
    $success = false;
    foreach ($successPatterns as $pattern) {
        if (strpos($result, $pattern) !== false) {
            $success = true;
            break;
        }
    }
    
    if (strpos($result, 'Continue creating array?') !== false) {
        $cmd2 = "echo y | " . $cmd;
        $result2 = execCmd($cmd2, true, 120);
        error_log("RAID create retry result: " . $result2);
        
        foreach ($successPatterns as $pattern) {
            if (strpos($result2, $pattern) !== false) {
                $success = true;
                $result = $result2;
                break;
            }
        }
    }
    
    if ($success) {
        $maxWait = 15;
        $arrayFound = false;
        for ($i = 0; $i < $maxWait; $i++) {
            if (file_exists("/dev/" . $name)) {
                $arrayFound = true;
                break;
            }
            sleep(1);
        }
        
        if (!$arrayFound) {
            return ['success' => false, 'error' => 'RAID array created but device node not found'];
        }
        
        sleep(2);
        
        $confFile = '/etc/mdadm/mdadm.conf';
        
        if (!file_exists($confFile)) {
            $configContent = "# mdadm.conf\n";
            $configContent .= "DEVICE partitions\n";
            $configContent .= "AUTO -all\n";
            $configContent .= "MAILADDR root\n\n";
            file_put_contents($confFile, $configContent);
        } else {
            $content = file_get_contents($confFile);
            if (strpos($content, 'DEVICE') === false) {
                $newContent = "DEVICE partitions\n\n" . $content;
                file_put_contents($confFile, $newContent);
            }
        }
        
        $arrayConfig = execCmd("mdadm --detail --scan | grep '/dev/" . $name . "'", true, 10);
        
        if (!empty($arrayConfig)) {
            $uuid = execCmd("mdadm --detail /dev/" . $name . " 2>/dev/null | grep 'UUID' | awk '{print $3}'", true, 10);
            $uuid = trim($uuid);
            
            if (!empty($uuid)) {
                execCmd("sed -i '/UUID=" . $uuid . "/d' " . $confFile, true, 5);
            }
            execCmd("sed -i '/\\/dev\\/" . $name . "/d' " . $confFile, true, 5);
            
            execCmd("bash -c 'echo \"" . addslashes($arrayConfig) . "\" >> " . $confFile . "'", true, 5);
            error_log("Added to config: " . $arrayConfig);
        } else {
            $uuid = execCmd("mdadm --detail /dev/" . $name . " 2>/dev/null | grep 'UUID' | awk '{print $3}'", true, 10);
            $metadata = "1.2";
            
            if (!empty($uuid)) {
                $line = "ARRAY /dev/" . $name . " metadata=" . $metadata . " UUID=" . $uuid . " auto=yes";
                execCmd("bash -c 'echo \"" . addslashes($line) . "\" >> " . $confFile . "'", true, 5);
                error_log("Added to config (fallback): " . $line);
            }
        }
        
        execCmd("update-initramfs -u", true, 60);
        
        execCmd("systemctl restart mdmonitor", true, 10);
        
        clearCache();
        
        return ['success' => true, 'message' => 'RAID array created successfully'];
    }
    
    return ['success' => false, 'error' => $result];
}

function getSpareDevicesInRaid($mdName) {
    $spares = [];
    
    $mdstat = execLight("cat /proc/mdstat 2>/dev/null | grep '^" . preg_quote($mdName, '/') . " '", 5);
    if (!empty($mdstat)) {
        preg_match_all('/(sd[a-z]+|hd[a-z]+|vd[a-z]+|nvme[0-9]+n[0-9]+p?[0-9]*)\[(\d+)\]\(S\)/', $mdstat, $matches);
        if (!empty($matches[1])) {
            $spares = array_unique($matches[1]);
        }
    }
    
    if (empty($spares)) {
        $detail = execCmd("mdadm --detail /dev/" . escapeshellarg($mdName) . " 2>/dev/null", true, 10);
        if (!empty($detail)) {
            preg_match_all('/\/(dev\/[a-z0-9]+)\s+:\s+\d+\s+\d+\s+\d+\s+\d+\s+spare/', $detail, $matches);
            if (!empty($matches[1])) {
                foreach ($matches[1] as $device) {
                    $spares[] = basename($device);
                }
            }
        }
    }
    
    return $spares;
}

function stopRaid($mdName) {
    execCmd("echo 'clear' > /sys/block/" . escapeshellarg($mdName) . "/md/array_state 2>/dev/null", true, 3);
    execCmd("echo 'remove' > /sys/block/" . escapeshellarg($mdName) . "/md/array_state 2>/dev/null", true, 3);
    
    $result = execCmd("mdadm --stop /dev/" . escapeshellarg($mdName) . " 2>&1", true, 10);
    
    sleep(1);
    $stillExists = raidExists($mdName);
    
    clearCache();
    
    if (!$stillExists) {
        return ['success' => true, 'message' => 'RAID array stopped'];
    }
    
    return ['success' => false, 'error' => 'Cannot stop RAID array: ' . $result];
}

function startRaid($mdName) {
    if (raidExists($mdName)) {
        return ['success' => true, 'message' => 'RAID array already active'];
    }
    
    $result = execCmd("mdadm --assemble /dev/" . escapeshellarg($mdName) . " 2>&1", true, 15);
    
    clearCache();
    
    if (strpos($result, 'has been started') !== false || strpos($result, 'assembled') !== false) {
        return ['success' => true, 'message' => 'RAID array started'];
    }
    
    return ['success' => false, 'error' => $result];
}

function deleteRaid($mdName, $force = false) {
    $lvmInfo = getLVGInfoForRaid($mdName);
    
    if ($lvmInfo['is_pv'] && !$force) {
        $vgName = $lvmInfo['vg_name'] ?: 'unknown';
        return [
            'success' => false,
            'error' => 'RAID массив используется в LVM как Physical Volume',
            'in_lvm' => true,
            'vg_name' => $vgName,
            'vg_size' => $lvmInfo['vg_size'],
            'lvs' => $lvmInfo['lvs'],
            'pv_uuid' => $lvmInfo['pv_uuid']
        ];
    }
    
    if ($lvmInfo['is_pv'] && $force) {
        $devicePath = '/dev/' . $mdName;
        execCmd("pvremove -ff " . escapeshellarg($devicePath) . " 2>/dev/null", true, 10);
        sleep(1);
    }
    
    $log = [];
    $log[] = "=== DELETING RAID: $mdName ===";
    $log[] = "Force mode: " . ($force ? "YES" : "NO");
    
    $mountPoint = execCmd("mount | grep '/dev/" . $mdName . "' | awk '{print $3}'", true, 5);
    if (!empty($mountPoint) && !$force) {
        return [
            'success' => false,
            'error' => 'RAID массив смонтирован',
            'is_mounted' => true,
            'mount_point' => trim($mountPoint)
        ];
    }
    
    if (!empty($mountPoint)) {
        $log[] = "Unmounting: " . trim($mountPoint);
        execCmd("umount -l /dev/" . escapeshellarg($mdName) . " 2>/dev/null", true, 10);
        execCmd("umount -f /dev/" . escapeshellarg($mdName) . " 2>/dev/null", true, 10);
        sleep(2);
    }
    
    $log[] = "=== Getting disks from RAID ===";
    $allDevices = [];
    
    $mdstatInfo = execLight("cat /proc/mdstat 2>/dev/null | grep '^" . preg_quote($mdName, '/') . " '", 5);
    if (!empty($mdstatInfo)) {
        preg_match_all('/(sd[a-z]+|hd[a-z]+|vd[a-z]+|nvme[0-9]+n[0-9]+p?[0-9]*)\[\d+\](?:\([S]\))?/', $mdstatInfo, $matches);
        if (!empty($matches[1])) {
            $allDevices = array_unique($matches[1]);
        }
        $log[] = "Devices from mdstat: " . implode(', ', $allDevices);
    }
    
    if (empty($allDevices)) {
        $detail = execCmd("mdadm --detail /dev/" . escapeshellarg($mdName) . " 2>/dev/null", true, 10);
        if (!empty($detail)) {
            preg_match_all('/\/dev\/(sd[a-z]+|hd[a-z]+|vd[a-z]+|nvme\d+n\d+)/', $detail, $matches);
            if (!empty($matches[1])) {
                $allDevices = array_unique($matches[1]);
            }
        }
        $log[] = "Devices from mdadm --detail: " . implode(', ', $allDevices);
    }
    
    $spareDevices = getSpareDevicesInRaid($mdName);
    if (!empty($spareDevices)) {
        $allDevices = array_unique(array_merge($allDevices, $spareDevices));
        $log[] = "Spare devices added: " . implode(', ', $spareDevices);
    }
    
    if (empty($allDevices)) {
        $log[] = "WARNING: No devices found for RAID $mdName";
    }
    
    $log[] = "=== Stopping RAID via sysfs ===";
    
    execCmd("sudo sh -c 'echo \"clear\" > /sys/block/" . $mdName . "/md/array_state' 2>/dev/null", true, 5);
    $log[] = "  echo clear > /sys/block/$mdName/md/array_state";
    sleep(2);
    
    execCmd("sudo sh -c 'echo \"inactive\" > /sys/block/" . $mdName . "/md/array_state' 2>/dev/null", true, 5);
    $log[] = "  echo inactive > /sys/block/$mdName/md/array_state";
    sleep(1);
    
    execCmd("sudo sh -c 'echo \"remove\" > /sys/block/" . $mdName . "/md/array_state' 2>/dev/null", true, 5);
    $log[] = "  echo remove > /sys/block/$mdName/md/array_state";
    sleep(1);
    
    $log[] = "=== Stopping via mdadm ===";
    execCmd("mdadm --stop /dev/" . escapeshellarg($mdName) . " 2>/dev/null", true, 10);
    execCmd("mdadm --stop --force /dev/" . escapeshellarg($mdName) . " 2>/dev/null", true, 10);
    sleep(2);
    
    $log[] = "=== Removing device node ===";
    execCmd("rm -f /dev/" . escapeshellarg($mdName) . " 2>/dev/null", true, 5);
    execCmd("rm -f /dev/" . escapeshellarg($mdName) . "p* 2>/dev/null", true, 5);
    
    if (!empty($allDevices)) {
        $log[] = "=== Cleaning " . count($allDevices) . " disks ===";
        
        foreach ($allDevices as $device) {
            $devicePath = "/dev/" . $device;
            $log[] = "--- Cleaning: $device ---";
            
            if (!file_exists($devicePath)) {
                $log[] = "  Device not found, skipping";
                continue;
            }
            
            execCmd("mdadm --stop " . escapeshellarg($devicePath) . " 2>/dev/null", true, 5);
            sleep(1);
            
            for ($i = 1; $i <= 3; $i++) {
                $log[] = "  Pass $i/3: zeroing superblock";
                execCmd("mdadm --zero-superblock --force " . escapeshellarg($devicePath) . " 2>/dev/null", true, 10);
                usleep(300000);
            }
            sleep(1);
            
            $log[] = "  Wiping filesystem signatures";
            execCmd("wipefs --all --force " . escapeshellarg($devicePath) . " 2>/dev/null", true, 10);
            execCmd("wipefs --all --force " . escapeshellarg($devicePath) . " 2>/dev/null", true, 10);
            sleep(1);
            
            $log[] = "  Writing zeros (50MB)";
            execCmd("dd if=/dev/zero of=" . escapeshellarg($devicePath) . " bs=1M count=50 2>/dev/null", true, 60);
            sleep(2);
            
            $log[] = "  Writing zeros (small blocks)";
            execCmd("dd if=/dev/zero of=" . escapeshellarg($devicePath) . " bs=512 count=20000 2>/dev/null", true, 60);
            sleep(1);
            
            execCmd("blockdev --rereadpt " . escapeshellarg($devicePath) . " 2>/dev/null", true, 5);
            
            $check = execCmd("mdadm --examine " . escapeshellarg($devicePath) . " 2>/dev/null | grep -i 'magic'", true, 5);
            if (empty($check)) {
                $log[] = "  SUCCESS: $device is clean";
            } else {
                $log[] = "  WARNING: $device may still have superblock!";
            }
        }
    } else {
        $log[] = "=== No disks to clean, skipping ===";
    }
    
    $log[] = "=== Scanning all disks for remaining superblocks ===";
    $allDisks = getAllDiskDevices();
    $foundExtra = [];
    
    foreach ($allDisks as $disk) {
        if (empty($disk)) continue;
        $diskName = basename($disk);
        if (in_array($diskName, $allDevices)) continue;
        
        $check = execCmd("mdadm --examine " . escapeshellarg($disk) . " 2>/dev/null | grep -E 'name.*" . $mdName . "'", true, 5);
        if (!empty($check)) {
            $log[] = "  Found superblock from $mdName on: $disk";
            $foundExtra[] = $diskName;
            
            for ($i = 1; $i <= 3; $i++) {
                execCmd("mdadm --zero-superblock --force " . escapeshellarg($disk) . " 2>/dev/null", true, 10);
                usleep(200000);
            }
            execCmd("wipefs --all --force " . escapeshellarg($disk) . " 2>/dev/null", true, 10);
            execCmd("dd if=/dev/zero of=" . escapeshellarg($disk) . " bs=1M count=50 2>/dev/null", true, 60);
            $log[] = "    Cleaned: $disk";
        }
    }
    
    $log[] = "=== Cleaning configuration ===";
    execCmd("sed -i '/" . preg_quote($mdName, '/') . "/d' /etc/mdadm/mdadm.conf", true, 5);
    execCmd("sed -i '/name=.*:" . $mdName . "/d' /etc/mdadm/mdadm.conf", true, 5);
    
    execCmd("mdadm --detail --scan 2>/dev/null > /etc/mdadm/mdadm.conf.new", true, 10);
    if (file_exists('/etc/mdadm/mdadm.conf.new')) {
        $newConf = file_get_contents('/etc/mdadm/mdadm.conf.new');
        if ($newConf !== false && strpos($newConf, $mdName) === false) {
            rename('/etc/mdadm/mdadm.conf.new', '/etc/mdadm/mdadm.conf');
        } else {
            unlink('/etc/mdadm/mdadm.conf.new');
        }
    }
    
    $log[] = "=== Updating system ===";
    execCmd("udevadm settle 2>/dev/null", true, 5);
    execCmd("udevadm control --reload 2>/dev/null", true, 5);
    execCmd("udevadm trigger 2>/dev/null", true, 5);
    execCmd("update-initramfs -u", true, 60);
    execCmd("systemctl restart mdmonitor", true, 10);
    execCmd("mdadm --assemble --scan 2>/dev/null", true, 10);
    
    sleep(3);
    $finalCheck = execLight("cat /proc/mdstat 2>/dev/null | grep '^" . preg_quote($mdName, '/') . " '", 5);
    $deviceNodeExists = file_exists("/dev/" . $mdName);
    
    $log[] = "=== FINAL CHECK ===";
    $log[] = "mdstat: " . (empty($finalCheck) ? "CLEAN" : "STILL PRESENT");
    $log[] = "device node: " . ($deviceNodeExists ? "STILL EXISTS" : "CLEAN");
    
    clearCache();
    
    $logFile = '/tmp/raid_delete_' . $mdName . '_' . date('Ymd_His') . '.log';
    file_put_contents($logFile, implode("\n", $log));
    
    if (empty($finalCheck) && !$deviceNodeExists) {
        return [
            'success' => true,
            'message' => "RAID $mdName полностью удален",
            'cleaned_devices' => $allDevices,
            'extra_cleaned' => $foundExtra,
            'log_file' => $logFile
        ];
    } else {
        return [
            'success' => false,
            'error' => "RAID $mdName не удален",
            'mdstat' => $finalCheck,
            'device_node_exists' => $deviceNodeExists,
            'log_file' => $logFile
        ];
    }
}

function checkRaidBeforeDelete($mdName) {
    $result = [
        'can_delete' => true,
        'errors' => [],
        'warnings' => [],
        'devices' => [],
        'in_lvm' => false,
        'vg_name' => null,
        'vg_size' => null,
        'lvs' => [],
        'pv_uuid' => null
    ];
    
    $lvmInfo = getLVGInfoForRaid($mdName);
    if ($lvmInfo['is_pv']) {
        $result['can_delete'] = false;
        $result['errors'][] = "RAID массив используется в LVM как Physical Volume (VG: {$lvmInfo['vg_name']})";
        $result['in_lvm'] = true;
        $result['vg_name'] = $lvmInfo['vg_name'];
        $result['vg_size'] = $lvmInfo['vg_size'];
        $result['lvs'] = $lvmInfo['lvs'];
        $result['pv_uuid'] = $lvmInfo['pv_uuid'];
        return $result;
    }
    
    $mountPoint = execCmd("mount | grep '/dev/" . $mdName . "' | awk '{print $3}'", true, 5);
    if (!empty($mountPoint)) {
        $result['can_delete'] = false;
        $result['errors'][] = "RAID массив смонтирован в {$mountPoint}";
        return $result;
    }
    
    $devices = getDevicesInRaid($mdName);
    $spareDevices = getSpareDevicesInRaid($mdName);
    $allDevices = array_unique(array_merge($devices, $spareDevices));
    $result['devices'] = $allDevices;
    
    foreach ($allDevices as $device) {
        $devicePath = "/dev/" . $device;
        
        $otherRaid = execLight("mdadm --examine " . escapeshellarg($devicePath) . " 2>/dev/null | grep -E 'Array UUID'", 5);
        if (!empty($otherRaid) && strpos($otherRaid, $mdName) === false) {
            $result['warnings'][] = "Устройство {$device} принадлежит другому RAID массиву";
        }
        
        $fsType = execLight("blkid -o value -s TYPE " . escapeshellarg($devicePath) . " 2>/dev/null", 5);
        if (!empty($fsType) && $fsType !== 'linux_raid_member') {
            $result['warnings'][] = "На устройстве {$device} обнаружена файловая система {$fsType}";
        }
    }
    
    $health = getRaidHealth($mdName);
    if ($health['degraded']) {
        $result['warnings'][] = "RAID массив находится в деградированном состоянии";
    }
    
    if ($health['failed_disks'] > 0) {
        $result['warnings'][] = "Обнаружено {$health['failed_disks']} failed дисков";
    }
    
    return $result;
}

function getDevicesInRaid($mdName) {
    $devices = [];
    
    $detail = execCmd("mdadm --detail /dev/" . escapeshellarg($mdName) . " 2>/dev/null", true, 10);
    if (!empty($detail)) {
        preg_match_all('/\/(dev\/[a-z0-9]+)\s+:\s+\d+\s+\d+\s+\d+\s+\d+\s+(\w+)/', $detail, $matches);
        if (!empty($matches[1])) {
            foreach ($matches[1] as $device) {
                $devices[] = basename($device);
            }
        }
    }
    
    if (empty($devices)) {
        $mdstat = execLight("cat /proc/mdstat 2>/dev/null | grep '^" . preg_quote($mdName, '/') . " '", 5);
        if (!empty($mdstat)) {
            preg_match_all('/(sd[a-z]+|hd[a-z]+|vd[a-z]+|nvme[0-9]+n[0-9]+p?[0-9]*)\[\d+\](?:\([SF]\))?/', $mdstat, $matches);
            if (!empty($matches[1])) {
                $devices = array_unique($matches[1]);
            }
        }
    }
    
    if (empty($devices)) {
        $scan = execCmd("mdadm --examine --scan 2>/dev/null", true, 10);
        if (!empty($scan)) {
            $lines = explode("\n", $scan);
            foreach ($lines as $line) {
                if (strpos($line, $mdName) !== false) {
                    preg_match_all('/\/dev\/(sd[a-z]+|hd[a-z]+|vd[a-z]+|nvme\d+n\d+)/', $line, $matches);
                    if (!empty($matches[1])) {
                        foreach ($matches[1] as $device) {
                            $devices[] = $device;
                        }
                    }
                }
            }
        }
    }
    
    if (empty($devices)) {
        $allDisks = getAllDiskDevices();
        foreach ($allDisks as $disk) {
            if (empty($disk)) continue;
            $check = execCmd("mdadm --examine " . escapeshellarg($disk) . " 2>/dev/null | grep -E 'name.*" . $mdName . "'", true, 5);
            if (!empty($check)) {
                $devices[] = basename($disk);
            }
        }
    }
    
    return array_unique($devices);
}

function getDevicesFromSuperblock($mdName) {
    $devices = [];
    $scan = execCmd("mdadm --examine --scan 2>/dev/null", true, 10);
    if (!empty($scan)) {
        preg_match_all('/\/dev\/(sd[a-z]+|hd[a-z]+|vd[a-z]+|nvme\d+n\d+)/', $scan, $matches);
        if (!empty($matches[1])) {
            $devices = array_unique($matches[1]);
        }
    }
    return $devices;
}

function getAllDiskDevices() {
    $disks = [];
    $lsblk = execLight("lsblk -p -n -o NAME,TYPE 2>/dev/null | grep -E 'disk|part' | awk '{print $1}'", 5);
    if (!empty($lsblk)) {
        $disks = explode("\n", trim($lsblk));
    }
    return $disks;
}

function addDeviceToRaid($mdName, $device) {
    if (!raidExists($mdName)) {
        return ['success' => false, 'error' => 'RAID array ' . $mdName . ' does not exist'];
    }
    
    $checkRaid = execLight("mdadm --examine /dev/" . escapeshellarg($device) . " 2>/dev/null | grep -i 'raid'", 3);
    if (!empty($checkRaid)) {
        execCmd("mdadm --zero-superblock --force /dev/" . escapeshellarg($device) . " 2>/dev/null", true, 5);
    }
    
    $result = execCmd("mdadm --add /dev/" . escapeshellarg($mdName) . " /dev/" . escapeshellarg($device) . " 2>&1", true, 15);
    
    clearCache();
    
    if (strpos($result, 'added') !== false || strpos($result, 're-added') !== false) {
        return ['success' => true, 'message' => 'Device added to RAID'];
    }
    
    return ['success' => false, 'error' => $result];
}

function checkRaid($mdName) {
    if (!raidExists($mdName)) {
        return ['success' => false, 'error' => 'RAID array ' . $mdName . ' does not exist'];
    }
    execCmd("chmod -R 777 /sys/block/" . escapeshellarg($mdName) . "/md/sync_action 2>&1", true, 3);
    $result = execCmd("echo 'check' > /sys/block/" . escapeshellarg($mdName) . "/md/sync_action 2>&1", true, 3);
    
    if (empty($result)) {
        clearCache();
        return ['success' => true, 'message' => 'Check started'];
    }
    
    return ['success' => false, 'error' => $result];
}

function repairRaid($mdName) {
    if (!raidExists($mdName)) {
        return ['success' => false, 'error' => 'RAID array ' . $mdName . ' does not exist'];
    }
    execCmd("chmod -R 777 /sys/block/" . escapeshellarg($mdName) . "/md/sync_action 2>&1", true, 3);
    $result = execCmd("sudo echo 'repair' > /sys/block/" . escapeshellarg($mdName) . "/md/sync_action 2>&1", true, 3);
    
    if (empty($result)) {
        clearCache();
        return ['success' => true, 'message' => 'Repair started'];
    }
    
    return ['success' => false, 'error' => $result];
}

function getRaidSyncStatus($mdName) {
    $syncAction = execLight("cat /sys/block/" . escapeshellarg($mdName) . "/md/sync_action 2>/dev/null", 2);
    $syncCompleted = execLight("cat /sys/block/" . escapeshellarg($mdName) . "/md/sync_completed 2>/dev/null", 2);
    
    $status = ['action' => trim($syncAction) ?: 'idle', 'percent' => 0, 'supported' => true];
    
    if (!empty($syncCompleted) && strpos($syncCompleted, '/') !== false) {
        list($completed, $total) = explode('/', $syncCompleted);
        if ($total > 0) {
            $status['percent'] = round(($completed / $total) * 100, 1);
        }
    }
    
    return $status;
}

function getRaidDiskStatus($mdName) {
    $detail = execCmd("mdadm --detail /dev/" . escapeshellarg($mdName) . " 2>/dev/null", true, 10);
    $disks = [];
    
    if (!empty($detail)) {
        $lines = explode("\n", $detail);
        $inDevicesSection = false;
        
        foreach ($lines as $line) {
            if (strpos($line, 'Number   Major   Minor   RaidDevice State') !== false) {
                $inDevicesSection = true;
                continue;
            }
            if ($inDevicesSection && preg_match('/^\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\S+)\s+(.+)$/', $line, $matches)) {
                $disks[] = [
                    'number' => $matches[1],
                    'major' => $matches[2],
                    'minor' => $matches[3],
                    'raid_device' => $matches[4],
                    'device' => $matches[5],
                    'state' => trim($matches[6])
                ];
            }
        }
    }
    
    return $disks;
}

function cleanupStaleRaid($mdName) {
    $result = [
        'success' => false,
        'cleaned' => false,
        'message' => '',
        'devices' => []
    ];
    
    $mdstat = execLight("cat /proc/mdstat 2>/dev/null | grep '^" . preg_quote($mdName, '/') . " '", 5);
    if (empty($mdstat)) {
        $result['message'] = "RAID array $mdName not found";
        return $result;
    }
    
    if (strpos($mdstat, 'inactive') === false && strpos($mdstat, 'active') !== false) {
        $result['message'] = "RAID array $mdName is active, cannot cleanup stale";
        return $result;
    }
    
    $devices = [];
    
    preg_match_all('/(sd[a-z]+|hd[a-z]+|vd[a-z]+|nvme[0-9]+n[0-9]+p?[0-9]*)\[(\d+)\](?:\(S\))?/', $mdstat, $matches);
    if (!empty($matches[1])) {
        $devices = array_unique($matches[1]);
    }
    
    if (empty($devices)) {
        $detail = execCmd("mdadm --detail /dev/" . escapeshellarg($mdName) . " 2>/dev/null", true, 10);
        if (!empty($detail)) {
            preg_match_all('/\/dev\/(sd[a-z]+|hd[a-z]+|vd[a-z]+|nvme\d+n\d+)/', $detail, $matches);
            if (!empty($matches[1])) {
                $devices = array_unique($matches[1]);
            }
        }
    }
    
    $result['devices'] = $devices;
    
    $log = [];
    $log[] = "=== Cleaning stale RAID: $mdName ===";
    $log[] = "Devices found: " . implode(', ', $devices);
    
    $log[] = "Stopping RAID via sysfs...";
    execCmd("sudo sh -c 'echo \"clear\" > /sys/block/" . $mdName . "/md/array_state' 2>/dev/null", true, 5);
    sleep(1);
    
    $log[] = "Stopping via mdadm...";
    execCmd("mdadm --stop /dev/" . escapeshellarg($mdName) . " 2>/dev/null", true, 10);
    execCmd("mdadm --stop --force /dev/" . escapeshellarg($mdName) . " 2>/dev/null", true, 10);
    sleep(1);
    
    $log[] = "Removing device node...";
    execCmd("rm -f /dev/" . escapeshellarg($mdName) . " 2>/dev/null", true, 5);
    execCmd("rm -f /dev/" . escapeshellarg($mdName) . "p* 2>/dev/null", true, 5);
    
    $log[] = "Cleaning superblocks on devices...";
    foreach ($devices as $device) {
        $devicePath = "/dev/" . $device;
        if (file_exists($devicePath)) {
            $log[] = "  Cleaning: $devicePath";
            execCmd("mdadm --zero-superblock --force " . escapeshellarg($devicePath) . " 2>/dev/null", true, 10);
            execCmd("wipefs --all --force " . escapeshellarg($devicePath) . " 2>/dev/null", true, 10);
            execCmd("dd if=/dev/zero of=" . escapeshellarg($devicePath) . " bs=1M count=10 2>/dev/null", true, 30);
            execCmd("blockdev --rereadpt " . escapeshellarg($devicePath) . " 2>/dev/null", true, 5);
        }
    }
    
    $log[] = "Removing from mdadm.conf...";
    execCmd("sed -i '/" . preg_quote($mdName, '/') . "/d' /etc/mdadm/mdadm.conf", true, 5);
    
    $log[] = "Updating initramfs...";
    execCmd("update-initramfs -u", true, 60);
    
    $log[] = "Restarting mdmonitor...";
    execCmd("systemctl restart mdmonitor", true, 10);
    
    $logFile = '/tmp/raid_cleanup_' . $mdName . '_' . date('Ymd_His') . '.log';
    file_put_contents($logFile, implode("\n", $log));
    
    sleep(2);
    $finalCheck = execLight("cat /proc/mdstat 2>/dev/null | grep '^" . preg_quote($mdName, '/') . " '", 5);
    
    if (empty($finalCheck)) {
        $result['success'] = true;
        $result['cleaned'] = true;
        $result['message'] = "Stale RAID array $mdName cleaned successfully";
        $result['log_file'] = $logFile;
    } else {
        $result['message'] = "Failed to clean stale RAID array $mdName";
        $result['log_file'] = $logFile;
    }
    
    return $result;
}

function cleanupAllStaleRaids() {
    $mdstat = execLight("cat /proc/mdstat 2>/dev/null", 5);
    $cleaned = [];
    
    if (!empty($mdstat)) {
        preg_match_all('/^(md\d+)\s+:\s+inactive/', $mdstat, $matches);
        
        foreach ($matches[1] as $mdName) {
            $result = cleanupStaleRaid($mdName);
            if ($result['success'] && $result['cleaned']) {
                $cleaned[] = $mdName;
            }
        }
        
        $devMd = execLight("ls /dev/md* 2>/dev/null | grep -E '/dev/md[0-9]+$'", 5);
        if (!empty($devMd)) {
            $devices = explode("\n", trim($devMd));
            foreach ($devices as $device) {
                $mdName = str_replace('/dev/', '', $device);
                if (!in_array($mdName, $matches[1]) && !raidExists($mdName)) {
                    $result = cleanupStaleRaid($mdName);
                    if ($result['success'] && $result['cleaned']) {
                        $cleaned[] = $mdName;
                    }
                }
            }
        }
    }
    
    clearCache();
    return ['success' => true, 'cleaned' => $cleaned];
}

function forceCleanDisk($diskName) {
    $diskPath = "/dev/" . $diskName;
    $log = [];
    $log[] = "=== FORCE CLEANING DISK: $diskName ===";
    
    if (!file_exists($diskPath)) {
        return ['success' => false, 'error' => "Disk $diskPath not found"];
    }
    
    $log[] = "Finding and stopping RAIDs using this disk...";
    
    $mdstat = execLight("cat /proc/mdstat 2>/dev/null", 5);
    if (!empty($mdstat)) {
        preg_match_all('/^(md\d+)\s+:/m', $mdstat, $mdMatches);
        foreach ($mdMatches[1] as $mdName) {
            $mdDetail = execLight("cat /proc/mdstat 2>/dev/null | grep '^" . preg_quote($mdName, '/') . " '", 5);
            if (strpos($mdDetail, $diskName) !== false) {
                $log[] = "  Disk found in RAID: $mdName";
                
                execCmd("sudo sh -c 'echo \"clear\" > /sys/block/" . $mdName . "/md/array_state' 2>/dev/null", true, 5);
                sleep(1);
                execCmd("sudo sh -c 'echo \"remove\" > /sys/block/" . $mdName . "/md/array_state' 2>/dev/null", true, 5);
                sleep(1);
                
                execCmd("mdadm --stop /dev/" . $mdName . " 2>/dev/null", true, 10);
                execCmd("mdadm --stop --force /dev/" . $mdName . " 2>/dev/null", true, 10);
                
                execCmd("rm -f /dev/" . $mdName . " 2>/dev/null", true, 5);
                
                $log[] = "    Stopped RAID: $mdName";
            }
        }
    }
    
    $log[] = "Stopping processes using disk...";
    execCmd("mdadm --stop " . escapeshellarg($diskPath) . " 2>/dev/null", true, 5);
    execCmd("fuser -km " . escapeshellarg($diskPath) . " 2>/dev/null", true, 5);
    sleep(1);
    
    $log[] = "Cleaning RAID superblock...";
    
    $possibleMd = execLight("ls /dev/md* 2>/dev/null | grep -E 'md[0-9]+$'", 5);
    foreach (explode("\n", $possibleMd) as $md) {
        if (empty($md)) continue;
        $mdName = str_replace('/dev/', '', $md);
        $mdDetail = execLight("cat /proc/mdstat 2>/dev/null | grep '^" . preg_quote($mdName, '/') . " '", 5);
        if (strpos($mdDetail, $diskName) !== false) {
            $log[] = "  Clearing via sysfs for $mdName...";
            execCmd("sudo sh -c 'echo \"clear\" > /sys/block/" . $mdName . "/md/array_state' 2>/dev/null", true, 5);
            sleep(1);
        }
    }
    
    for ($i = 1; $i <= 3; $i++) {
        $log[] = "  Pass $i/3: zeroing superblock...";
        execCmd("mdadm --zero-superblock --force " . escapeshellarg($diskPath) . " 2>/dev/null", true, 10);
        usleep(300000);
    }
    sleep(1);
    
    $log[] = "Wiping filesystem signatures...";
    execCmd("wipefs --all --force " . escapeshellarg($diskPath) . " 2>/dev/null", true, 10);
    execCmd("wipefs --all --force " . escapeshellarg($diskPath) . " 2>/dev/null", true, 10);
    sleep(1);
    
    $log[] = "Writing zeros to disk (50MB)...";
    execCmd("dd if=/dev/zero of=" . escapeshellarg($diskPath) . " bs=1M count=50 2>/dev/null", true, 60);
    sleep(2);
    
    $log[] = "Writing zeros with small blocks...";
    execCmd("dd if=/dev/zero of=" . escapeshellarg($diskPath) . " bs=512 count=20000 2>/dev/null", true, 60);
    sleep(1);
    
    $log[] = "Updating system...";
    execCmd("blockdev --rereadpt " . escapeshellarg($diskPath) . " 2>/dev/null", true, 5);
    execCmd("udevadm settle 2>/dev/null", true, 5);
    execCmd("udevadm control --reload 2>/dev/null", true, 5);
    execCmd("udevadm trigger 2>/dev/null", true, 5);
    
    $check = execCmd("mdadm --examine " . escapeshellarg($diskPath) . " 2>/dev/null | grep -i 'magic'", true, 5);
    
    if (empty($check)) {
        $log[] = "SUCCESS: Disk $diskName is clean";
        clearCache();
        
        return [
            'success' => true,
            'message' => "Disk $diskName successfully cleaned",
            'disk' => $diskName,
            'log' => $log
        ];
    } else {
        $log[] = "FAILED: Disk $diskName still has superblock!";
        return [
            'success' => false,
            'error' => "Failed to clean disk $diskName",
            'disk' => $diskName,
            'check' => $check,
            'log' => $log
        ];
    }
}

function clearMdArray($mdName) {
    $log = [];
    $log[] = "=== Clearing MD array: $mdName ===";
    
    execCmd("sudo sh -c 'echo \"clear\" > /sys/block/" . $mdName . "/md/array_state' 2>/dev/null", true, 5);
    sleep(1);
    
    execCmd("sudo sh -c 'echo \"inactive\" > /sys/block/" . $mdName . "/md/array_state' 2>/dev/null", true, 5);
    sleep(1);
    
    execCmd("sudo sh -c 'echo \"remove\" > /sys/block/" . $mdName . "/md/array_state' 2>/dev/null", true, 5);
    sleep(1);
    
    execCmd("mdadm --stop /dev/" . $mdName . " 2>/dev/null", true, 10);
    execCmd("mdadm --stop --force /dev/" . $mdName . " 2>/dev/null", true, 10);
    
    execCmd("rm -f /dev/" . $mdName . " 2>/dev/null", true, 5);
    
    execCmd("sed -i '/" . preg_quote($mdName, '/') . "/d' /etc/mdadm/mdadm.conf", true, 5);
    
    execCmd("update-initramfs -u", true, 60);
    execCmd("systemctl restart mdmonitor", true, 10);
    
    sleep(2);
    
    $stillExists = execLight("cat /proc/mdstat 2>/dev/null | grep '^" . preg_quote($mdName, '/') . " '", 5);
    
    if (empty($stillExists)) {
        return ['success' => true, 'message' => "MD array $mdName cleared", 'log' => $log];
    } else {
        return ['success' => false, 'error' => "Failed to clear $mdName", 'mdstat' => $stillExists];
    }
}

function fullSystemCleanup() {
    $result = [
        'success' => true,
        'cleaned_raids' => [],
        'cleaned_disks' => [],
        'errors' => []
    ];
    
    $staleResult = cleanupAllStaleRaids();
    $result['cleaned_raids'] = $staleResult['cleaned'] ?? [];
    
    $allDisks = getAllDiskDevices();
    foreach ($allDisks as $disk) {
        if (empty($disk)) continue;
        
        $examine = execCmd("mdadm --examine " . escapeshellarg($disk) . " 2>/dev/null | grep -i 'magic'", true, 5);
        if (!empty($examine)) {
            $inUse = false;
            $mdstat = execLight("cat /proc/mdstat 2>/dev/null", 5);
            if (strpos($mdstat, basename($disk)) !== false) {
                $inUse = true;
            }
            
            if (!$inUse) {
                $cleanResult = forceCleanDisk(basename($disk));
                if ($cleanResult['success']) {
                    $result['cleaned_disks'][] = basename($disk);
                } else {
                    $result['errors'][] = $cleanResult['message'];
                }
            }
        }
    }
    
    execCmd("systemctl restart mdmonitor", true, 10);
    execCmd("update-initramfs -u", true, 60);
    
    clearCache();
    
    return $result;
}

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

$response = ['success' => false, 'error' => 'Unknown action'];

switch ($action) {
    case 'get_all_raid':
        $response = [
            'success' => true,
            'raid' => getAllRaidArrays(),
            'available_devices' => getAvailableDisksForRaid(),
            'mounts' => getMountPoints(),
            'timestamp' => time()
        ];
        break;
        
    case 'get_raid_health':
        $name = $input['name'] ?? '';
        if (empty($name)) {
            $response = ['success' => false, 'error' => 'RAID name required'];
        } else {
            $response = ['success' => true, 'health' => getRaidHealth($name)];
        }
        break;
        
    case 'get_raid_info':
        $name = $input['name'] ?? '';
        if (empty($name)) {
            $response = ['success' => false, 'error' => 'RAID name required'];
        } else {
            $allRaid = getAllRaidArrays();
            $info = null;
            foreach ($allRaid as $raid) {
                if ($raid['name'] === $name) {
                    $info = $raid;
                    break;
                }
            }
            if ($info) {
                $info['disk_status'] = getRaidDiskStatus($name);
                $info['lvm_info'] = getLVGInfoForRaid($name);
                $response = ['success' => true, 'info' => $info];
            } else {
                $response = ['success' => false, 'error' => 'RAID array not found'];
            }
        }
        break;
        
    case 'get_raid_candidates':
        $response = ['success' => true, 'devices' => getRaidCandidates()];
        break;
        
    case 'get_available_disks':
        $response = ['success' => true, 'devices' => getAvailableDisksForRaid()];
        break;
        
    case 'raid_create':
        $name = $input['name'] ?? '';
        $level = $input['level'] ?? '';
        $devices = $input['devices'] ?? [];
        $spare = $input['spare'] ?? [];
        $chunk = $input['chunk'] ?? null;
        
        if (empty($name)) {
            $response = ['success' => false, 'error' => 'Name required'];
        } elseif ($level === '' || $level === null) {
            $response = ['success' => false, 'error' => 'Level required'];
        } elseif (empty($devices)) {
            $response = ['success' => false, 'error' => 'Devices required'];
        } else {
            $response = createRaid($name, $level, $devices, $spare, $chunk);
        }
        break;
        
    case 'raid_stop':
        $name = $input['name'] ?? '';
        if (empty($name)) {
            $response = ['success' => false, 'error' => 'RAID name required'];
        } else {
            $response = stopRaid($name);
        }
        break;
        
    case 'raid_start':
        $name = $input['name'] ?? '';
        if (empty($name)) {
            $response = ['success' => false, 'error' => 'RAID name required'];
        } else {
            $response = startRaid($name);
        }
        break;
        
    case 'raid_delete':
        $name = $input['name'] ?? '';
        $force = $input['force'] ?? false;
        if (empty($name)) {
            $response = ['success' => false, 'error' => 'RAID name required'];
        } else {
            $response = deleteRaid($name, $force);
        }
        break;
        
    case 'raid_add_device':
        $name = $input['name'] ?? '';
        $device = $input['device'] ?? '';
        if (empty($name) || empty($device)) {
            $response = ['success' => false, 'error' => 'RAID name and device required'];
        } else {
            $response = addDeviceToRaid($name, $device);
        }
        break;
        
    case 'raid_check':
        $name = $input['name'] ?? '';
        if (empty($name)) {
            $response = ['success' => false, 'error' => 'RAID name required'];
        } else {
            $response = checkRaid($name);
        }
        break;
        
    case 'raid_repair':
        $name = $input['name'] ?? '';
        if (empty($name)) {
            $response = ['success' => false, 'error' => 'RAID name required'];
        } else {
            $response = repairRaid($name);
        }
        break;
        
    case 'raid_sync_status':
        $name = $input['name'] ?? '';
        if (empty($name)) {
            $response = ['success' => false, 'error' => 'RAID name required'];
        } else {
            $response = ['success' => true, 'status' => getRaidSyncStatus($name)];
        }
        break;
        
    case 'get_disk_status':
        $name = $input['name'] ?? '';
        if (empty($name)) {
            $response = ['success' => false, 'error' => 'RAID name required'];
        } else {
            $response = ['success' => true, 'disks' => getRaidDiskStatus($name)];
        }
        break;
    
    case 'cleanup_stale_raid':
        $name = $input['name'] ?? '';
        if (empty($name)) {
            $response = ['success' => false, 'error' => 'RAID name required'];
        } else {
            $result = cleanupStaleRaid($name);
            if ($result) {
                clearCache();
                $response = ['success' => true, 'message' => 'Stale RAID array cleaned up'];
            } else {
                $response = ['success' => false, 'error' => 'Not a stale RAID array or cleanup failed'];
            }
        }
        break;

    case 'cleanup_all_stale_raid':
        $response = cleanupAllStaleRaids();
        break;
    
    case 'check_lvm_raid':
        $name = $input['name'] ?? '';
        if (empty($name)) {
            $response = ['success' => false, 'error' => 'RAID name required'];
        } else {
            $lvmInfo = getLVGInfoForRaid($name);
            $response = ['success' => true, 'lvm_info' => $lvmInfo];
        }
        break;
    
	case 'check_raid_before_delete':
		$name = $input['name'] ?? '';
		if (empty($name)) {
			$response = ['success' => false, 'error' => 'RAID name required'];
		} else {
			$response = ['success' => true, 'check' => checkRaidBeforeDelete($name)];
		}
		break;
	
	case 'cleanup_stale_raid_detailed':
		$name = $input['name'] ?? '';
		if (empty($name)) {
			$response = ['success' => false, 'error' => 'RAID name required'];
		} else {
			$response = cleanupStaleRaid($name);
		}
		break;
	
	case 'full_system_cleanup':
		$response = fullSystemCleanup();
		break;
	
	case 'force_clean_disk':
		$disk = $input['disk'] ?? '';
		if (empty($disk)) {
			$response = ['success' => false, 'error' => 'Disk name required'];
		} else {
			$response = forceCleanDisk($disk);
		}
		break;
	
	case 'clear_md_array':
    $mdName = $input['name'] ?? '';
    if (empty($mdName)) {
        $response = ['success' => false, 'error' => 'MD name required'];
    } else {
        $response = clearMdArray($mdName);
    }
    break;
	
	case 'get_broken_raids':
    $response = ['success' => true, 'broken_raids' => getBrokenRaids()];
    break;
	
    default:
        $response = ['success' => false, 'error' => 'Unknown action: ' . $action];
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>