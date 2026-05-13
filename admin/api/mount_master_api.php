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

error_reporting(E_ERROR);
ini_set('display_errors', 0);
set_time_limit(0);

define('LOG_FILE', ROOT_PATH . '/tmp/mount_master.log');
define('ERROR_LOG_FILE', ROOT_PATH . '/tmp/mount_master_error.log');

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

function execCmd($cmd, $sudo = true, $timeout = 60) {
    $fullCmd = $sudo ? "sudo " . $cmd : $cmd;
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
                    if (isset($disk['children'])) {
                        foreach ($disk['children'] as $part) {
                            if ($part['type'] === 'part' && empty($part['mountpoint'])) {
                                $devices[] = [
                                    'name' => $part['name'],
                                    'path' => '/dev/' . $part['name'],
                                    'size' => $part['size'] ?? 'Unknown',
                                    'fstype' => $part['fstype'] ?? null,
                                    'label' => $part['label'] ?? null,
                                    'uuid' => $part['uuid'] ?? null,
                                    'type' => 'partition',
                                    'source_type' => 'partition'
                                ];
                            }
                        }
                    }
                }
            }
        }
    }
    
    $mdOutput = execCmd("lsblk -J -o NAME,TYPE,SIZE,FSTYPE,LABEL,UUID,MOUNTPOINT /dev/md* 2>/dev/null", true, 10);
    if (!empty($mdOutput)) {
        $mdData = json_decode($mdOutput, true);
        if ($mdData && isset($mdData['blockdevices'])) {
            foreach ($mdData['blockdevices'] as $raid) {
                if (empty($raid['mountpoint'])) {
                    $devices[] = [
                        'name' => $raid['name'],
                        'path' => '/dev/' . $raid['name'],
                        'size' => $raid['size'] ?? 'Unknown',
                        'fstype' => $raid['fstype'] ?? null,
                        'label' => $raid['label'] ?? null,
                        'uuid' => $raid['uuid'] ?? null,
                        'type' => 'raid',
                        'source_type' => 'raid'
                    ];
                }
                
                if (isset($raid['children'])) {
                    foreach ($raid['children'] as $part) {
                        if (empty($part['mountpoint'])) {
                            $devices[] = [
                                'name' => $part['name'],
                                'path' => '/dev/' . $part['name'],
                                'size' => $part['size'] ?? 'Unknown',
                                'fstype' => $part['fstype'] ?? null,
                                'label' => $part['label'] ?? null,
                                'uuid' => $part['uuid'] ?? null,
                                'type' => 'raid_partition',
                                'source_type' => 'raid_partition'
                            ];
                        }
                    }
                }
            }
        }
    }
    
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
                        'source_type' => 'lvm'
                    ];
                }
            }
        }
    }
    
    return $devices;
}

function getMountEntries() {
    $entries = [];
    
    $mountOutput = execCmd("mount | grep '^/dev/' | grep -v 'loop'", true, 10);
    if (!empty($mountOutput)) {
        $lines = explode("\n", trim($mountOutput));
        foreach ($lines as $line) {
            if (preg_match('/^\/dev\/(\S+)\s+on\s+(\S+)\s+type\s+(\S+)\s+\((.+)\)/', $line, $matches)) {
                $device = $matches[1];
                $mountPoint = $matches[2];
                $fstype = $matches[3];
                $options = $matches[4];
                
                $isManaged = false;
                $markerFile = $mountPoint . '/.mount_created_by_mount_master';
                if (file_exists($markerFile)) {
                    $isManaged = true;
                }
                
                $inFstab = false;
                $fstabEntry = null;
                $fstab = file_get_contents('/etc/fstab');
                if ($fstab && strpos($fstab, $mountPoint) !== false) {
                    $inFstab = true;
                    $lines2 = explode("\n", $fstab);
                    foreach ($lines2 as $l) {
                        if (strpos($l, $mountPoint) !== false && strpos($l, '#') !== 0) {
                            $fstabEntry = trim($l);
                            break;
                        }
                    }
                }
                
                $entries[] = [
                    'device' => $device,
                    'device_path' => '/dev/' . $device,
                    'mount_point' => $mountPoint,
                    'fstype' => $fstype,
                    'options' => $options,
                    'in_fstab' => $inFstab,
                    'fstab_entry' => $fstabEntry,
                    'is_managed' => $isManaged
                ];
            }
        }
    }
    
    $networkOutput = execCmd("mount -t nfs,nfs4,cifs 2>/dev/null", true, 10);
    if (!empty($networkOutput)) {
        $lines = explode("\n", trim($networkOutput));
        foreach ($lines as $line) {
            if (empty(trim($line))) continue;
            
            if (preg_match('/^\/\/(\S+)\/(\S+)\s+on\s+(\S+)\s+type\s+cifs/', $line, $matches)) {
                $server = $matches[1];
                $share = $matches[2];
                $mountPoint = $matches[3];
                
                $entries[] = [
                    'device' => "//{$server}/{$share}",
                    'device_path' => "//{$server}/{$share}",
                    'mount_point' => $mountPoint,
                    'fstype' => 'cifs',
                    'options' => '',
                    'in_fstab' => false,
                    'is_managed' => false,
                    'is_network' => true,
                    'network_type' => 'cifs'
                ];
            } elseif (preg_match('/^(\S+):(\S+)\s+on\s+(\S+)\s+type\s+nfs/', $line, $matches)) {
                $server = $matches[1];
                $export = $matches[2];
                $mountPoint = $matches[3];
                
                $entries[] = [
                    'device' => "{$server}:{$export}",
                    'device_path' => "{$server}:{$export}",
                    'mount_point' => $mountPoint,
                    'fstype' => 'nfs',
                    'options' => '',
                    'in_fstab' => false,
                    'is_managed' => false,
                    'is_network' => true,
                    'network_type' => 'nfs'
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
    writeLog("Adding to fstab: {$device} -> {$mountPoint}");
    
    $escapedMountPoint = str_replace(' ', '\\040', $mountPoint);
    
    $fstabLine = "{$device} {$escapedMountPoint} {$fstype} {$options} {$dump} {$pass}\n";
    
    $currentFstab = @file_get_contents('/etc/fstab');
    if ($currentFstab !== false) {
        if (strpos($currentFstab, $mountPoint) !== false || strpos($currentFstab, $device) !== false) {
            writeLog("Entry for {$mountPoint} already exists in fstab");
            return ['success' => true, 'message' => 'Entry already exists'];
        }
    }
    
    $result = file_put_contents('/etc/fstab', $fstabLine, FILE_APPEND);
    if ($result !== false) {
        writeLog("Added to fstab: {$fstabLine}");
        return ['success' => true];
    } else {
        $error = "Failed to write to /etc/fstab";
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
        return file_put_contents('/etc/fstab', $newContent) !== false;
    }
    
    return true;
}

function mountLocalDevice($device, $mountPoint, $options = [], $addToFstab = false) {
    writeLog("Mounting local device: {$device} -> {$mountPoint}");
    
    $devicePath = $device;
    if (strpos($device, '/dev/') !== 0) {
        $devicePath = '/dev/' . $device;
    }
    
    if (!file_exists($devicePath)) {
        return ['success' => false, 'error' => "Device {$devicePath} does not exist"];
    }
    
    $mountCheck = execCmd("mount | grep '{$devicePath} '", true, 5);
    if (!empty($mountCheck)) {
        return ['success' => false, 'error' => "Device already mounted"];
    }
    
    $fstype = $options['fstype'] ?? 'auto';
    if ($fstype === 'auto') {
        $fstype = trim(execCmd("blkid -s TYPE -o value {$devicePath} 2>/dev/null", true, 5));
        if (empty($fstype)) {
            $fstype = trim(execCmd("lsblk -n -o FSTYPE {$devicePath} 2>/dev/null", true, 5));
        }
        if (empty($fstype)) {
            $fstype = 'auto';
        }
    }
    
    $mountPointCreated = false;
    if (!is_dir($mountPoint)) {
        $parentDir = dirname($mountPoint);
        if (!is_dir($parentDir) && $parentDir !== '/') {
            execCmd("mkdir -p \"{$parentDir}\"", true, 5);
        }
        
        $result = execCmd("mkdir -p \"{$mountPoint}\"", true, 5);
        if (!empty($result) && strpos($result, 'cannot create') !== false) {
            return ['success' => false, 'error' => "Failed to create mount point: {$result}"];
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
    
    $mountOptions = $options['mount_options'] ?? 'defaults';
    
    if ($fstype === 'ntfs' || $fstype === 'ntfs-3g') {
        $mountOptions = "uid={$uidNum},gid={$gidNum},umask=000,fmask=000,dmask=000,big_writes";
    } elseif ($fstype === 'exfat') {
        $mountOptions = "uid={$uidNum},gid={$gidNum},umask=000,fmask=000,dmask=000";
    } elseif ($fstype === 'vfat' || $fstype === 'fat32') {
        $mountOptions = "uid={$uidNum},gid={$gidNum},umask=000,fmask=000,dmask=000,shortname=mixed,utf8";
    } elseif ($fstype === 'ext4' || $fstype === 'ext3' || $fstype === 'ext2') {
        $mountOptions = "uid={$uidNum},gid={$gidNum},umask=000";
    } else {
        $mountOptions = $options['mount_options'] ?? "uid={$uidNum},gid={$gidNum},umask=000";
    }
    
    $cmd = "mount -t {$fstype} -o {$mountOptions} {$devicePath} \"{$mountPoint}\" 2>&1";
    $output = execCmd($cmd, true, 30);
    
    sleep(1);
    $checkMount = execCmd("mount | grep '{$devicePath} '", true, 5);
    
    if (empty($checkMount)) {
        if ($mountPointCreated) {
            execCmd("rmdir \"{$mountPoint}\" 2>/dev/null", true, 3);
        }
        return ['success' => false, 'error' => "Mount failed: " . ($output ?: 'Unknown error')];
    }
    
    execCmd("touch \"{$mountPoint}/.mount_created_by_mount_master\"", true, 3);
    execCmd("chmod 755 \"{$mountPoint}\"", true, 3);
    
    if ($addToFstab) {
        $uuid = trim(execCmd("blkid -s UUID -o value {$devicePath} 2>/dev/null", true, 5));
        if (!empty($uuid)) {
            $fstabDevice = "UUID={$uuid}";
        } else {
            $fstabDevice = $devicePath;
        }
        addToFstab($fstabDevice, $mountPoint, $fstype, $mountOptions);
    }
    
    writeLog("Device {$device} mounted successfully at {$mountPoint}");
    
    return [
        'success' => true,
        'mount_point' => $mountPoint,
        'mount_point_created' => $mountPointCreated,
        'fstype' => $fstype
    ];
}

function mountCifsShare($server, $share, $mountPoint, $options = [], $addToFstab = false) {
    writeLog("Mounting CIFS share: //{$server}/{$share} -> {$mountPoint}");
    
    $source = "//{$server}/{$share}";
    
    $mountCheck = execCmd("mount | grep '{$source} '", true, 5);
    if (!empty($mountCheck)) {
        return ['success' => false, 'error' => "Share already mounted"];
    }
    
    $mountPointCreated = false;
    if (!is_dir($mountPoint)) {
        $parentDir = dirname($mountPoint);
        if (!is_dir($parentDir) && $parentDir !== '/') {
            execCmd("mkdir -p \"{$parentDir}\"", true, 5);
        }
        
        $result = execCmd("mkdir -p \"{$mountPoint}\"", true, 5);
        if (!empty($result) && strpos($result, 'cannot create') !== false) {
            return ['success' => false, 'error' => "Failed to create mount point: {$result}"];
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
    
    $mountOptions = "uid={$uidNum},gid={$gidNum},file_mode=0777,dir_mode=0777,noperm";
    
    if (!empty($options['username']) && !empty($options['password'])) {
        $mountOptions .= ",username={$options['username']},password={$options['password']}";
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
    
    $cmd = "mount -t cifs -o {$mountOptions} \"{$source}\" \"{$mountPoint}\" 2>&1";
    $output = execCmd($cmd, true, 30);
    
    sleep(1);
    $checkMount = execCmd("mount | grep '{$source} '", true, 5);
    
    if (empty($checkMount)) {
        if ($mountPointCreated) {
            execCmd("rmdir \"{$mountPoint}\" 2>/dev/null", true, 3);
        }
        return ['success' => false, 'error' => "Mount failed: " . ($output ?: 'Unknown error')];
    }
    
    execCmd("touch \"{$mountPoint}/.mount_created_by_mount_master\"", true, 3);
    
    if ($addToFstab) {
        $fstabOptions = "_netdev,uid={$uidNum},gid={$gidNum},file_mode=0777,dir_mode=0777";
        if (!empty($options['username'])) {
            $fstabOptions .= ",username={$options['username']}";
        }
        addToFstab($source, $mountPoint, 'cifs', $fstabOptions);
    }
    
    writeLog("CIFS share mounted successfully at {$mountPoint}");
    
    return [
        'success' => true,
        'mount_point' => $mountPoint,
        'mount_point_created' => $mountPointCreated
    ];
}

function mountNfsShare($server, $export, $mountPoint, $options = [], $addToFstab = false) {
    writeLog("Mounting NFS share: {$server}:{$export} -> {$mountPoint}");
    
    $source = "{$server}:{$export}";
    
    $mountCheck = execCmd("mount | grep '{$source} '", true, 5);
    if (!empty($mountCheck)) {
        return ['success' => false, 'error' => "Share already mounted"];
    }
    
    $mountPointCreated = false;
    if (!is_dir($mountPoint)) {
        $parentDir = dirname($mountPoint);
        if (!is_dir($parentDir) && $parentDir !== '/') {
            execCmd("mkdir -p \"{$parentDir}\"", true, 5);
        }
        
        $result = execCmd("mkdir -p \"{$mountPoint}\"", true, 5);
        if (!empty($result) && strpos($result, 'cannot create') !== false) {
            return ['success' => false, 'error' => "Failed to create mount point: {$result}"];
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
        return ['success' => false, 'error' => "Mount failed: " . ($output ?: 'Unknown error')];
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
    writeLog("Unmounting: {$mountPoint}, force: " . ($force ? 'true' : 'false'));
    
    $mountCheck = execCmd("mount | grep ' {$mountPoint} '", true, 5);
    if (empty($mountCheck)) {
        return ['success' => false, 'error' => "Nothing mounted at {$mountPoint}"];
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
        $output = execCmd("umount \"{$mountPoint}\" 2>&1", true, 10);
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
                return ['success' => false, 'error' => "Failed to unmount: " . ($output ?: 'Unknown error')];
            }
        } else {
            return ['success' => false, 'error' => "Failed to unmount: " . ($output ?: 'Unknown error')];
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
    
    return ['success' => true, 'message' => "Successfully unmounted", 'was_managed' => $wasManaged];
}

function browseDirectory($path) {
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
        return ['success' => false, 'error' => 'Not a directory: ' . $realPath];
    }
    
    if (!is_readable($realPath)) {
        return ['success' => false, 'error' => 'Directory not readable: ' . $realPath];
    }
    
    $items = [];
    
    try {
        $dirs = scandir($realPath);
        if ($dirs === false) {
            return ['success' => false, 'error' => 'Cannot read directory: ' . $realPath];
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
        return ['success' => false, 'error' => 'Error reading directory: ' . $e->getMessage()];
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
    $newPath = rtrim($path, '/') . '/' . $name;
    
    if (file_exists($newPath)) {
        return ['success' => false, 'error' => 'Directory already exists'];
    }
    
    $result = mkdir($newPath, 0777, true);
    if ($result) {
        execCmd("chmod 777 \"{$newPath}\"", true, 3);
        return ['success' => true, 'path' => $newPath];
    } else {
        return ['success' => false, 'error' => 'Failed to create directory'];
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
            $device = $input['device'] ?? '';
            $mountPoint = $input['mount_point'] ?? '';
            $addToFstab = filter_var($input['add_to_fstab'] ?? false, FILTER_VALIDATE_BOOLEAN);
            
            if (empty($device) || empty($mountPoint)) {
                $response = ['success' => false, 'error' => 'Device and mount point required'];
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
            $server = $input['server'] ?? '';
            $share = $input['share'] ?? '';
            $mountPoint = $input['mount_point'] ?? '';
            $addToFstab = filter_var($input['add_to_fstab'] ?? false, FILTER_VALIDATE_BOOLEAN);
            
            if (empty($server) || empty($share) || empty($mountPoint)) {
                $response = ['success' => false, 'error' => 'Server, share and mount point required'];
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
            $server = $input['server'] ?? '';
            $export = $input['export'] ?? '';
            $mountPoint = $input['mount_point'] ?? '';
            $addToFstab = filter_var($input['add_to_fstab'] ?? false, FILTER_VALIDATE_BOOLEAN);
            
            if (empty($server) || empty($export) || empty($mountPoint)) {
                $response = ['success' => false, 'error' => 'Server, export and mount point required'];
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
            $mountPoint = $input['mount_point'] ?? '';
            $force = filter_var($input['force'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $removeFromFstab = filter_var($input['remove_from_fstab'] ?? false, FILTER_VALIDATE_BOOLEAN);
            
            if (empty($mountPoint)) {
                $response = ['success' => false, 'error' => 'Mount point required'];
                break;
            }
            
            $response = unmountPoint($mountPoint, $force, $removeFromFstab);
            break;
            
        case 'add_to_fstab':
            $device = $input['device'] ?? '';
            $mountPoint = $input['mount_point'] ?? '';
            $fstype = $input['fstype'] ?? 'ext4';
            $options = $input['options'] ?? 'defaults';
            
            if (empty($device) || empty($mountPoint)) {
                $response = ['success' => false, 'error' => 'Device and mount point required'];
                break;
            }
            
            $response = addToFstab($device, $mountPoint, $fstype, $options);
            break;
            
        case 'remove_from_fstab':
            $mountPoint = $input['mount_point'] ?? '';
            
            if (empty($mountPoint)) {
                $response = ['success' => false, 'error' => 'Mount point required'];
                break;
            }
            
            $response = ['success' => removeFromFstab($mountPoint)];
            break;
            
        case 'change_mount_point':
            $oldMountPoint = $input['old_mount_point'] ?? '';
            $newMountPoint = $input['new_mount_point'] ?? '';
            $updateFstab = filter_var($input['update_fstab'] ?? false, FILTER_VALIDATE_BOOLEAN);
            
            if (empty($oldMountPoint) || empty($newMountPoint)) {
                $response = ['success' => false, 'error' => 'Old and new mount point required'];
                break;
            }
            
            $mountCheck = execCmd("mount | grep ' {$oldMountPoint} '", true, 5);
            if (empty($mountCheck)) {
                $response = ['success' => false, 'error' => "Nothing mounted at {$oldMountPoint}"];
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
                $response = ['success' => false, 'error' => "Failed to remount: {$output}"];
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
            
            $response = ['success' => true, 'message' => 'Mount point changed successfully'];
            break;
            
        case 'browse':
    $path = $input['path'] ?? $_GET['path'] ?? '/';
    $path = urldecode($path);
    $path = '/' . ltrim($path, '/');
    $response = browseDirectory($path);
    break;
            
        case 'create_folder':
            $path = $input['path'] ?? '';
            $name = $input['name'] ?? '';
            
            if (empty($path) || empty($name)) {
                $response = ['success' => false, 'error' => 'Path and name required'];
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
?>