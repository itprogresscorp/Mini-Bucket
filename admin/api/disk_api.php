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

error_reporting(E_ERROR);
ini_set('display_errors', 0);
set_time_limit(0);

// ==================== ЛОГИРОВАНИЕ ====================
define('LOG_FILE', '/var/www/minib/logs/disk_manager.log');
define('ERROR_LOG_FILE', '/var/www/minib/logs/disk_manager_error.log');

function writeLog($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
    file_put_contents(LOG_FILE, $logMessage, FILE_APPEND);
}

function writeError($message, $context = []) {
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? ' | Context: ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
    $logMessage = "[{$timestamp}] [ERROR] {$message}{$contextStr}" . PHP_EOL;
    file_put_contents(ERROR_LOG_FILE, $logMessage, FILE_APPEND);
    file_put_contents(LOG_FILE, $logMessage, FILE_APPEND);
}

// ==================== ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ ====================
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

writeLog("Action called: {$action}", 'DEBUG');


// Функция получения логов
function getLogs($lines = 100) {
    $logFile = LOG_FILE;
    if (!file_exists($logFile)) {
        return [];
    }
    
    $logs = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($logs === false) {
        return [];
    }
    
    $logs = array_reverse($logs);
    $logs = array_slice($logs, 0, $lines);
    return array_reverse($logs);
}

// Функция очистки логов
function clearLogs() {
    $result = true;
    if (file_exists(LOG_FILE)) {
        $result = $result && (file_put_contents(LOG_FILE, '') !== false);
    }
    if (file_exists(ERROR_LOG_FILE)) {
        $result = $result && (file_put_contents(ERROR_LOG_FILE, '') !== false);
    }
    writeLog("User permission logs");
    return $result;
}

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
        writeError("proc_open failed for command: {$fullCmd}");
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
            writeError("Command timeout after {$timeout}s: {$fullCmd}");
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

function isMounted($device) {
    $device = preg_replace('/^\/dev\//', '', $device);
    $mountCheck = execCmd("mount | grep -E \"^/dev/${device} \"", true, 5);
    return !empty($mountCheck);
}

function getMountPoint($device) {
    $device = preg_replace('/^\/dev\//', '', $device);
    $mountPoint = execCmd("mount | grep -E \"^/dev/${device} \" | awk '{print $3}'", true, 5);
    return trim($mountPoint);
}

function mountDevice($device, $mountPoint, $fsType = 'auto', $addToFstab = false, $fstabOptions = 'defaults') {
    writeLog("Mounting device: {$device}, mount point: {$mountPoint}, fsType: {$fsType}");
    
    $device = preg_replace('/^\/dev\//', '', $device);
    $devicePath = "/dev/{$device}";
    
    if (!file_exists($devicePath)) {
        $error = "Device {$devicePath} does not exist";
        writeError($error);
        return ['success' => false, 'error' => $error];
    }
    
    $detectedFs = $fsType;
    if ($fsType === 'auto') {
        $detectedFs = trim(execCmd("blkid -s TYPE -o value {$devicePath} 2>/dev/null", true, 5));
        if (empty($detectedFs)) {
            $detectedFs = trim(execCmd("lsblk -n -o FSTYPE {$devicePath} 2>/dev/null", true, 5));
        }
        if (empty($detectedFs)) {
            $error = "Unable to determine file system on {$device}. The partition may not be formatted.";
            writeError($error, ['device' => $device]);
            return ['success' => false, 'error' => $error];
        }
        writeLog("Detected filesystem: {$detectedFs}");
    }
    
    $globalMount = execCmd("nsenter -t 1 -m mount | grep '{$devicePath} '", true, 5);
    if (!empty($globalMount)) {
        $existingMount = execCmd("nsenter -t 1 -m mount | grep '{$devicePath} ' | awk '{print $3}'", true, 5);
        $error = "The device is already mounted in {$existingMount}";
        writeError($error);
        return ['success' => false, 'error' => $error];
    }
    
    $uid = execCmd("id -u www-data 2>/dev/null", false, 3);
    $gid = execCmd("id -g www-data 2>/dev/null", false, 3);
    if (empty($uid)) $uid = 33;
    if (empty($gid)) $gid = 33;
    
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
        
        execCmd("chown {$uid}:{$gid} \"{$mountPoint}\"", true, 3);
        execCmd("chmod 755 \"{$mountPoint}\"", true, 3);
        $mountPointCreated = true;
        execCmd("touch \"{$mountPoint}/.mount_created_by_disk_manager\"", true, 3);
        writeLog("Created mount point: {$mountPoint}");
    }
    
    $fsHelper = '';
    switch ($detectedFs) {
        case 'ntfs':
        case 'ntfs-3g':
            $fsHelper = execCmd("which ntfs-3g 2>/dev/null", true, 3);
            if (empty($fsHelper)) {
                $error = "NTFS-3G not installed. Install it: sudo apt install ntfs-3g";
                writeError($error);
                if ($mountPointCreated) {
                    execCmd("rmdir \"{$mountPoint}\" 2>/dev/null", true, 3);
                }
                return ['success' => false, 'error' => $error];
            }
            $detectedFs = 'ntfs-3g';
            break;
        case 'exfat':
            $fsHelper = execCmd("which mount.exfat 2>/dev/null", true, 3);
            if (empty($fsHelper)) {
                $error = "exFAT not supported. Install it: sudo apt install exfat-fuse exfatprogs";
                writeError($error);
                if ($mountPointCreated) {
                    execCmd("rmdir \"{$mountPoint}\" 2>/dev/null", true, 3);
                }
                return ['success' => false, 'error' => $error];
            }
            break;
        case 'vfat':
        case 'fat32':
            $detectedFs = 'vfat';
            break;
    }
    
    $mountOptions = "";
    
    switch ($detectedFs) {
        case 'ntfs-3g':
            $mountOptions = "uid={$uid},gid={$gid},umask=000,fmask=000,dmask=000,big_writes";
            break;
        case 'exfat':
            $mountOptions = "uid={$uid},gid={$gid},umask=000,fmask=000,dmask=000";
            break;
        case 'vfat':
            $mountOptions = "uid={$uid},gid={$gid},umask=000,fmask=000,dmask=000,shortname=mixed,utf8";
            break;
        case 'ext4':
        case 'ext3':
        case 'ext2':
            $mountOptions = "uid={$uid},gid={$gid},umask=000";
            break;
        case 'xfs':
            $mountOptions = "uid={$uid},gid={$gid},umask=000";
            break;
        case 'btrfs':
            $mountOptions = "uid={$uid},gid={$gid},umask=000";
            break;
        default:
            $mountOptions = "uid={$uid},gid={$gid},umask=000";
    }
    
    $mountSuccess = false;
    $lastError = "";
    $mountCommands = [];
    
    $mountCommands[] = "nsenter -t 1 -m mount -t {$detectedFs} -o {$mountOptions} {$devicePath} \"{$mountPoint}\" 2>&1";
    
    $mountCommands[] = "nsenter -t 1 -m mount -t {$detectedFs} {$devicePath} \"{$mountPoint}\" 2>&1";
    
    $mountCommands[] = "nsenter -t 1 -m mount -t {$detectedFs} -o uid={$uid},gid={$gid} {$devicePath} \"{$mountPoint}\" 2>&1";
    
    if ($detectedFs === 'ntfs-3g') {
        $mountCommands[] = "nsenter -t 1 -m ntfs-3g -o uid={$uid},gid={$gid},umask=000 {$devicePath} \"{$mountPoint}\" 2>&1";
    }
    
    $mountCommands[] = "nsenter -t 1 -m mount {$devicePath} \"{$mountPoint}\" 2>&1";
    
    foreach ($mountCommands as $cmd) {
        writeLog("Trying mount command: {$cmd}");
        $output = execCmd($cmd, true, 15);
        
        $checkMount = execCmd("nsenter -t 1 -m mount | grep '{$devicePath} '", true, 3);
        if (!empty($checkMount)) {
            $mountSuccess = true;
            writeLog("Mount successful with command: {$cmd}");
            break;
        }
        
        $lastError = $output;
        writeLog("Mount failed: {$output}");
    }
    
    if (!$mountSuccess) {
        if ($mountPointCreated && is_dir($mountPoint)) {
            execCmd("rm -f \"{$mountPoint}/.mount_created_by_disk_manager\"", true, 3);
            execCmd("rmdir \"{$mountPoint}\" 2>/dev/null", true, 3);
        }
        
        $errorMsg = "Mount error: ";
        if (strpos($lastError, 'unknown filesystem type') !== false) {
            $errorMsg .= "Unknown file system type '{$detectedFs}'. Install the required packages.";
        } elseif (strpos($lastError, 'bad option') !== false) {
            $errorMsg .= "Incorrect mount options for FS '{$detectedFs}'.";
        } elseif (strpos($lastError, 'bad superblock') !== false) {
            $errorMsg .= "Corrupted file system on {$device}. Run the scan: fsck {$devicePath}";
        } elseif (strpos($lastError, 'helper program') !== false) {
            $errorMsg .= "There is no driver for FS '{$detectedFs}'. Install: ";
            if ($detectedFs === 'ntfs-3g') $errorMsg .= "sudo apt install ntfs-3g";
            elseif ($detectedFs === 'exfat') $errorMsg .= "sudo apt install exfat-fuse exfatprogs";
            elseif ($detectedFs === 'vfat') $errorMsg .= "sudo apt install dosfstools";
            else $errorMsg .= "corresponding package for {$detectedFs}";
        } else {
            $errorMsg .= $lastError;
        }
        
        writeError($errorMsg);
        return ['success' => false, 'error' => $errorMsg];
    }
    
    execCmd("nsenter -t 1 -m chmod 777 \"{$mountPoint}\" 2>/dev/null", true, 5);
    execCmd("nsenter -t 1 -m chown {$uid}:{$gid} \"{$mountPoint}\" 2>/dev/null", true, 5);
    
    if ($addToFstab) {
        $uuid = execCmd("blkid -s UUID -o value /dev/{$device} 2>/dev/null", true, 5);
        if (!empty($uuid)) {
            $fstabOptions = 'defaults,noatime';
            
            if ($detectedFs === 'ntfs' || $detectedFs === 'ntfs-3g') {
                $fstabOptions = 'defaults,noatime,uid=0,gid=0,umask=000,fmask=000,dmask=000';
            } elseif ($detectedFs === 'exfat') {
                $fstabOptions = 'defaults,noatime,uid=0,gid=0,umask=000,fmask=000,dmask=000';
            } elseif ($detectedFs === 'vfat' || $detectedFs === 'fat32') {
                $fstabOptions = 'defaults,noatime,uid=0,gid=0,umask=000,fmask=000,dmask=000,shortname=mixed,utf8';
            }
            
            $fstabLine = "UUID={$uuid} {$mountPoint} {$detectedFs} {$fstabOptions} 0 2\n";
            
            $currentFstab = file_get_contents('/etc/fstab');
            if ($currentFstab !== false && strpos($currentFstab, $uuid) === false) {
                file_put_contents('/etc/fstab', $fstabLine, FILE_APPEND);
                writeLog("Added to fstab: {$fstabLine}");
            }
        }
    }
    
    $realMountPoint = execCmd("nsenter -t 1 -m mount | grep '{$devicePath} ' | awk '{print $3}'", true, 5);
    writeLog("Device {$device} mounted successfully at {$realMountPoint}");
    
    return ['success' => true, 'mount_point' => $realMountPoint, 'mount_point_created' => $mountPointCreated];
}

function umountDevice($device, $force = false, $removeFromFstab = false) {
    writeLog("Unmounting device: {$device}, force: " . ($force ? 'true' : 'false') . ", removeFromFstab: " . ($removeFromFstab ? 'true' : 'false'));
    
    $device = preg_replace('/^\/dev\//', '', $device);
    $devicePath = "/dev/{$device}";
    
    $globalMount = execCmd("nsenter -t 1 -m mount | grep '/dev/{$device} '", true, 5);
    
    if (empty($globalMount)) {
        writeError("Device not mounted: {$device}");
        return ['success' => false, 'error' => 'Устройство не смонтировано в системе'];
    }
    
    $mountPoint = execCmd("nsenter -t 1 -m mount | grep '/dev/{$device} ' | awk '{print $3}'", true, 5);
    $mountPoint = trim($mountPoint);
    
    writeLog("Mount point: {$mountPoint}");
    
    $wasCreatedByMount = false;
    if ($mountPoint && is_dir($mountPoint)) {
        $markerFile = $mountPoint . '/.mount_created_by_disk_manager';
        $wasCreatedByMount = file_exists($markerFile);
        writeLog("Mount point was " . ($wasCreatedByMount ? "created by disk manager" : "not created by disk manager"));
    }
    
    $uuid = '';
    if ($removeFromFstab) {
        $uuid = execCmd("blkid -s UUID -o value {$devicePath} 2>/dev/null", true, 5);
        $uuid = trim($uuid);
        writeLog("Device UUID: " . ($uuid ?: 'not found'));
    }
    
    if ($removeFromFstab) {
        writeLog("Removing from fstab...");
        
        if (!empty($uuid)) {
            $result = removeFromFstabByUuid($uuid);
            writeLog("Remove by UUID: " . ($result ? "success" : "failed"));
        }
        
        $result2 = removeFromFstab($device);
        writeLog("Remove by device path: " . ($result2 ? "success" : "failed"));
        
        if (!empty($mountPoint)) {
            $result3 = removeFromFstabByMountPoint($mountPoint);
            writeLog("Remove by mount point: " . ($result3 ? "success" : "failed"));
        }
    }
    
    $unmountSuccess = false;
    $lastError = '';
    
    if ($force) {
        writeLog("Force unmounting...");
        $output = execCmd("nsenter -t 1 -m umount -l {$devicePath}", true, 10);
    } else {
        writeLog("Normal unmounting...");
        $output = execCmd("nsenter -t 1 -m umount {$devicePath}", true, 10);
    }
    
    sleep(1);
    $stillMounted = execCmd("nsenter -t 1 -m mount | grep '{$devicePath} '", true, 5);
    
    if (empty($stillMounted)) {
        $unmountSuccess = true;
        writeLog("Successfully unmounted {$devicePath}");
    } else {
        if (!$force) {
            writeLog("Normal unmount failed, trying force unmount...");
            $output = execCmd("nsenter -t 1 -m umount -l {$devicePath}", true, 10);
            sleep(1);
            $stillMounted = execCmd("nsenter -t 1 -m mount | grep '{$devicePath} '", true, 5);
            
            if (empty($stillMounted)) {
                $unmountSuccess = true;
                writeLog("Force unmount successful");
            } else {
                $lastError = "Failed to unmount: " . ($output ?: 'Unknown error');
            }
        } else {
            $lastError = "Force unmount failed: " . ($output ?: 'Unknown error');
        }
    }
    
    if (!$unmountSuccess) {
        writeError($lastError, ['device' => $device]);
        return ['success' => false, 'error' => $lastError];
    }
    
    if ($wasCreatedByMount && $mountPoint && is_dir($mountPoint)) {
        writeLog("Removing mount point {$mountPoint} (was created by disk manager)");
        
        execCmd("rm -f \"{$mountPoint}/.mount_created_by_disk_manager\"", true, 3);
        
        $isEmpty = true;
        $dirContent = scandir($mountPoint);
        if ($dirContent !== false) {
            $content = array_diff($dirContent, ['.', '..']);
            if (!empty($content)) {
                $isEmpty = false;
                writeLog("Directory not empty, keeping it. Contents: " . implode(', ', $content));
            }
        }
        
        if ($isEmpty) {
            $rmdirResult = execCmd("rmdir \"{$mountPoint}\" 2>&1", true, 3);
            if (empty($rmdirResult) || strpos($rmdirResult, 'success') !== false) {
                writeLog("Removed directory: {$mountPoint}");
            } else {
                writeLog("Could not remove directory: {$rmdirResult}");
            }
        }
    } else {
        writeLog("Keeping mount point {$mountPoint} (was not created by disk manager)");
    }
    
    execCmd("partprobe 2>/dev/null", true, 3);
    execCmd("udevadm settle 2>/dev/null", true, 3);
    
    writeLog("Device {$device} unmounted successfully");
    return ['success' => true, 'message' => 'Устройство размонтировано', 'mount_point' => $mountPoint];
}

function removeFromFstabByMountPoint($mountPoint) {
    if (empty($mountPoint)) return false;
    
    writeLog("Removing from fstab by mount point: {$mountPoint}");
    
    $fstab = file_get_contents('/etc/fstab');
    if ($fstab === false) {
        writeError("Cannot read /etc/fstab");
        return false;
    }
    
    $lines = explode("\n", $fstab);
    $newLines = [];
    $found = false;
    
    foreach ($lines as $line) {
        $lineTrimmed = trim($line);
        
        if (empty($lineTrimmed) || $lineTrimmed[0] === '#') {
            $newLines[] = $line;
            continue;
        }
        
        $parts = preg_split('/\s+/', $lineTrimmed);
        if (count($parts) >= 2) {
            if ($parts[1] === $mountPoint) {
                $found = true;
                writeLog("Removing fstab entry by mount point: {$line}");
                continue;
            }
        }
        
        $newLines[] = $line;
    }
    
    if ($found) {
        $newContent = implode("\n", $newLines);
        $newContent = preg_replace('/\n{3,}/', "\n\n", $newContent);
        $result = file_put_contents('/etc/fstab', $newContent);
        writeLog($result !== false ? "Successfully removed fstab entry for {$mountPoint}" : "Failed to write fstab");
        return $result !== false;
    }
    
    writeLog("No fstab entry found for mount point {$mountPoint}");
    return true;
}

function getMountStatus($device) {
    $device = preg_replace('/^\/dev\//', '', $device);
    $devicePath = "/dev/{$device}";
    
    $mountInfo = execCmd("mount | grep '{$devicePath} '", true, 5);
    if (empty($mountInfo)) {
        $mountInfo = execCmd("nsenter -t 1 -m mount | grep '{$devicePath} '", true, 5);
    }
    
    if (empty($mountInfo)) {
        return ['mounted' => false];
    }
    
    $mountPoint = '';
    if (preg_match('/on\s+(\S+)\s+type/', $mountInfo, $matches)) {
        $mountPoint = $matches[1];
    } else {
        $mountPoint = execCmd("echo '{$mountInfo}' | awk '{print $3}'", false, 3);
        $mountPoint = trim($mountPoint);
    }
    
    return [
        'mounted' => true,
        'mount_point' => $mountPoint,
        'mount_info' => $mountInfo
    ];
}

function cleanupOrphanedMountPoints() {
    writeLog("Starting cleanup of orphaned mount points");
    $cleaned = 0;
    $errors = [];
    
    $mountedDevices = getMountedDevices();
    $mountedPaths = [];
    foreach ($mountedDevices as $dev) {
        $mountedPaths[] = $dev['mount_point'];
    }
    
    $searchPaths = ['/mnt', '/media', '/tmp/mnt'];
    $foundMarkers = [];
    
    foreach ($searchPaths as $searchPath) {
        if (!is_dir($searchPath)) continue;
        
        $cmd = "find {$searchPath} -name '.mount_created_by_disk_manager' -type f 2>/dev/null";
        $output = execCmd($cmd, true, 10);
        
        if (!empty($output)) {
            $markers = explode("\n", trim($output));
            foreach ($markers as $marker) {
                $marker = trim($marker);
                if (!empty($marker)) {
                    $mountPoint = dirname($marker);
                    $foundMarkers[] = $mountPoint;
                }
            }
        }
    }
    
    foreach ($foundMarkers as $mountPoint) {
        $isMounted = in_array($mountPoint, $mountedPaths);
        
        if (!$isMounted) {
            writeLog("Found orphaned mount point: {$mountPoint}");
            
            $dirContent = scandir($mountPoint);
            if ($dirContent !== false) {
                $content = array_diff($dirContent, ['.', '..', '.mount_created_by_disk_manager']);
                
                if (empty($content)) {
                    execCmd("rm -f \"{$mountPoint}/.mount_created_by_disk_manager\"", true, 3);
                    $result = execCmd("rmdir \"{$mountPoint}\" 2>&1", true, 3);
                    
                    if (strpos($result, 'success') !== false || empty($result)) {
                        $cleaned++;
                        writeLog("Removed orphaned mount point: {$mountPoint}");
                    } else {
                        $errors[] = "Could not remove {$mountPoint}: {$result}";
                    }
                } else {
                    writeLog("Mount point {$mountPoint} has content but no device mounted, keeping it (user data?)");
                }
            }
        }
    }
    
    writeLog("Cleanup completed. Removed {$cleaned} orphaned mount points");
    if (!empty($errors)) {
        writeError("Cleanup errors: " . implode(', ', $errors));
    }
    
    return ['cleaned' => $cleaned, 'errors' => $errors];
}

function verifyMountPoints() {
    writeLog("Verifying mount points consistency");
    
    $mountedDevices = getMountedDevices();
    $issues = [];
    
    foreach ($mountedDevices as $dev) {
        $mountPoint = $dev['mount_point'];
        
        if (!is_dir($mountPoint)) {
            $issues[] = "Mount point {$mountPoint} for device {$dev['device']} does not exist";
            writeLog("Issue: Mount point missing for {$dev['device']}");
        }
        
        $perms = fileperms($mountPoint);
        if ($perms !== false) {
            $permStr = substr(sprintf('%o', $perms), -4);
            if ($permStr !== '0777' && $permStr !== '777') {
                writeLog("Fixing permissions for {$mountPoint}: current {$permStr}, setting to 777");
                execCmd("chmod 777 \"{$mountPoint}\"", true, 3);
            }
        }
    }
    
    return ['issues' => $issues];
}

function removeFromFstab($device) {
    $device = preg_replace('/^\/dev\//', '', $device);
    $devicePath = "/dev/{$device}";
    
    writeLog("Removing from fstab by device: {$devicePath}");
    
    $fstab = file_get_contents('/etc/fstab');
    if ($fstab === false) {
        writeError("Cannot read /etc/fstab");
        return false;
    }
    
    $lines = explode("\n", $fstab);
    $newLines = [];
    $found = false;
    
    foreach ($lines as $line) {
        $lineTrimmed = trim($line);
        
        if (empty($lineTrimmed) || $lineTrimmed[0] === '#') {
            $newLines[] = $line;
            continue;
        }
        
        if (strpos($line, $devicePath) !== false) {
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

function removeFromFstabByUuid($uuid) {
    if (empty($uuid)) {
        writeLog("Empty UUID, skipping fstab removal");
        return true;
    }
    
    writeLog("Removing from fstab by UUID: {$uuid}");
    
    $fstab = file_get_contents('/etc/fstab');
    if ($fstab === false) {
        writeError("Cannot read /etc/fstab");
        return false;
    }
    
    $lines = explode("\n", $fstab);
    $newLines = [];
    $found = false;
    
    foreach ($lines as $line) {
        $lineTrimmed = trim($line);
        
        if (empty($lineTrimmed) || $lineTrimmed[0] === '#') {
            $newLines[] = $line;
            continue;
        }
        
        if (strpos($line, $uuid) !== false) {
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
        writeLog($result !== false ? "Successfully removed UUID {$uuid} from fstab" : "Failed to write fstab");
        return $result !== false;
    }
    
    writeLog("No fstab entry found for UUID {$uuid}");
    return true;
}

function addToFstab($device, $mountPoint, $fsType, $options = 'defaults') {
    writeLog("Adding to fstab: {$device} -> {$mountPoint}");
    
    $device = preg_replace('/^\/dev\//', '', $device);
    $devicePath = "/dev/{$device}";
    
    $uuid = execCmd("blkid -s UUID -o value {$devicePath} 2>/dev/null", true, 5);
    $uuid = trim($uuid);
    
    if (empty($uuid)) {
        $error = "Cannot get UUID for device {$device}";
        writeError($error);
        return ['success' => false, 'error' => $error];
    }
    
    $safeOptions = 'defaults,noatime';
    
    switch ($fsType) {
        case 'ntfs':
        case 'ntfs-3g':
            $safeOptions = 'defaults,noatime,uid=0,gid=0,umask=000,fmask=000,dmask=000';
            break;
        case 'exfat':
            $safeOptions = 'defaults,noatime,uid=0,gid=0,umask=000,fmask=000,dmask=000';
            break;
        case 'vfat':
        case 'fat32':
            $safeOptions = 'defaults,noatime,uid=0,gid=0,umask=000,fmask=000,dmask=000,shortname=mixed,utf8';
            break;
    }
    
    $fstabLine = "UUID={$uuid} {$mountPoint} {$fsType} {$safeOptions} 0 2\n";
    
    $currentFstab = file_get_contents('/etc/fstab');
    if ($currentFstab === false) {
        writeError("Cannot read /etc/fstab");
        return ['success' => false, 'error' => 'Cannot read /etc/fstab'];
    }
    
    if (strpos($currentFstab, $uuid) !== false) {
        writeLog("UUID {$uuid} already in fstab, skipping");
        return ['success' => true, 'message' => 'Entry already exists'];
    }
    
    if (strpos($currentFstab, $mountPoint) !== false) {
        writeLog("Mount point {$mountPoint} already in fstab, skipping");
        return ['success' => true, 'message' => 'Mount point already in fstab'];
    }
    
    $result = file_put_contents('/etc/fstab', $fstabLine, FILE_APPEND);
    if ($result !== false) {
        writeLog("Added to fstab: {$fstabLine}");
        return ['success' => true];
    } else {
        writeError("Failed to write to /etc/fstab");
        return ['success' => false, 'error' => 'Failed to write to /etc/fstab'];
    }
}

function getLvmVgAsVirtualDisks() {
    $virtualDisks = [];
    
    $vgsOutput = execCmd("vgs --noheadings -o vg_name,vg_size,vg_free,vg_attr 2>/dev/null", true, 10);
    if (empty($vgsOutput)) {
        return $virtualDisks;
    }
    
    $lines = explode("\n", trim($vgsOutput));
    foreach ($lines as $line) {
        if (empty(trim($line))) continue;
        $parts = preg_split('/\s+/', trim($line));
        if (count($parts) >= 4) {
            $vgName = $parts[0];
            $sizeBytes = parseSizeToBytes($parts[1]);
            $freeBytes = parseSizeToBytes($parts[2]);
            $attr = $parts[3];
            
            $logicalVolumes = getLvmLvsAsPartitions($vgName);
            
            $virtualDisks[] = [
                'name' => $vgName,
                'is_virtual' => true,
                'virtual_type' => 'lvm_vg',
                'size_bytes' => $sizeBytes,
                'free_bytes' => $freeBytes,
                'used_bytes' => $sizeBytes - $freeBytes,
                'attributes' => $attr,
                'is_active' => strpos($attr, 'a') !== false,
                'model' => 'LVM Volume Group',
                'type' => 'lvm',
                'removable' => false,
                'is_system' => false,
                'has_partition_table' => true,
                'partition_table_type' => 'lvm',
                'partitions' => $logicalVolumes,
                'pv_count' => getPvCountForVg($vgName),
                'lv_count' => count($logicalVolumes),
                'redirect_url' => 'lvm_manager.php'
            ];
        }
    }
    
    return $virtualDisks;
}

function getLvmLvsAsPartitions($vgName) {
    $partitions = [];
    
    $lvsOutput = execCmd("lvs --noheadings -o lv_name,lv_size,lv_attr,lv_path " . escapeshellarg($vgName) . " 2>/dev/null", true, 10);
    if (empty($lvsOutput)) {
        return $partitions;
    }
    
    $lines = explode("\n", trim($lvsOutput));
    foreach ($lines as $line) {
        if (empty(trim($line))) continue;
        $parts = preg_split('/\s+/', trim($line));
        if (count($parts) >= 4) {
            $lvName = $parts[0];
            $sizeBytes = parseSizeToBytes($parts[1]);
            $attr = $parts[2];
            $lvPath = $parts[3];
            //$sizeFormatted = formatSize($sizeBytes);
            
            $mountPoint = null;
            $mountsOutput = execCmd("mount | grep '{$lvPath} ' | awk '{print $3}'", true, 5);
            if (!empty($mountsOutput)) {
                $mountPoint = trim($mountsOutput);
            }
            
            $fsType = trim(execCmd("blkid -s TYPE -o value {$lvPath} 2>/dev/null", true, 5));
            $hasFilesystem = !empty($fsType);
            
            $fstabEntry = null;
            $uuid = trim(execCmd("blkid -s UUID -o value {$lvPath} 2>/dev/null", true, 5));
            if (!empty($uuid)) {
                $fstabCheck = execCmd("grep -E \"UUID={$uuid}|{$lvPath}\" /etc/fstab 2>/dev/null", true, 3);
                if (!empty($fstabCheck)) {
                    $fstabEntry = $fstabCheck;
                }
            }
            
            $isActive = strpos($attr, 'a') !== false;
            $isSnapshot = strpos($attr, 's') !== false || strpos($attr, 'S') !== false;
            
            $partitions[] = [
                'name' => $lvName,
                'full_name' => "{$vgName}/{$lvName}",
                'path' => $lvPath,
                'size_bytes' => $sizeBytes,
                'size_formatted' => $sizeFormatted,
                'fstype' => $fsType ?: null,
                'mount_point' => $mountPoint,
                'uuid' => $uuid ?: null,
                'fstab_entry' => $fstabEntry,
                'has_filesystem' => $hasFilesystem,
                'is_active' => $isActive,
                'is_snapshot' => $isSnapshot,
                'attributes' => $attr,
                'is_virtual_partition' => true,
                'parent_vg' => $vgName
            ];
        }
    }
    
    return $partitions;
}


function getRaidArraysAsVirtualDisks() {
    $virtualDisks = [];
    
    $mdstat = @file_get_contents('/proc/mdstat');
    if (empty($mdstat)) {
        return $virtualDisks;
    }
    
    $lines = explode("\n", $mdstat);
    $currentArray = null;
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        if (strpos($line, 'Personalities') !== false) continue;
        if (strpos($line, 'unused devices') !== false) continue;
        
        if (preg_match('/^(md\d+)\s+:\s+active\s+(?:\(auto-read-only\)\s+)?raid(\d+)/', $line, $matches)) {
            $mdName = $matches[1];
            $raidLevel = 'raid' . $matches[2];
            
            $isAutoReadOnly = (strpos($line, '(auto-read-only)') !== false);
            
            $sizeBytes = 0;
            $sizeFromProc = execCmd("cat /proc/partitions | grep ' " . $mdName . "$' | awk '{print $3}'", true, 5);
            if (!empty($sizeFromProc)) {
                $sizeBytes = intval($sizeFromProc) * 1024;
            }
            
            $state = execCmd("cat /sys/block/" . $mdName . "/md/array_state 2>/dev/null", true, 5);
            $state = trim($state) ?: 'active';
            
            if ($isAutoReadOnly) {
                $displayState = 'auto-read-only';
            } else {
                $displayState = $state;
            }
            
            $components = getRaidComponents($mdName);
            
            $partitions = getRaidPartitions($mdName);
            
            $virtualDisks[] = [
                'name' => $mdName,
                'is_virtual' => true,
                'virtual_type' => 'raid_array',
                'raid_level' => $raidLevel,
                'size_bytes' => $sizeBytes,
                'components' => $components,
                'component_count' => count($components),
                'state' => $state,
                'display_state' => $displayState,
                'is_auto_read_only' => $isAutoReadOnly,
                'is_degraded' => ($state === 'degraded'),
                'model' => "RAID {$raidLevel} Array" . ($isAutoReadOnly ? " (Auto-Read-Only)" : ""),
                'type' => 'raid',
                'removable' => false,
                'is_system' => false,
                'has_partition_table' => true,
                'partition_table_type' => getRaidPartitionTableType($mdName),
                'partitions' => $partitions,
                'redirect_url' => 'raid_manager.php'
            ];
        }
    }
    
    return $virtualDisks;
}

function getRaidComponents($mdName) {
    $components = [];
    
    $detail = execCmd("mdadm --detail /dev/" . escapeshellarg($mdName) . " 2>/dev/null", true, 10);
    if (!empty($detail)) {
        preg_match_all('/\/(dev\/[a-z0-9]+)\s+:\s+\d+\s+\d+\s+\d+\s+\d+\s+(\w+)/', $detail, $matches);
        if (!empty($matches[1])) {
            foreach ($matches[1] as $idx => $device) {
                $components[] = [
                    'device' => basename($device),
                    'path' => $device,
                    'state' => $matches[2][$idx] ?? 'unknown'
                ];
            }
        }
    }
    
    return $components;
}

function getRaidPartitions($mdName) {
    $partitions = [];
    $mdPath = "/dev/{$mdName}";
    
    $lsblkJson = execCmd("lsblk -J -o NAME,SIZE,FSTYPE,MOUNTPOINT,LABEL,UUID {$mdPath} 2>/dev/null", true, 10);
    
    if (!empty($lsblkJson)) {
        $data = json_decode($lsblkJson, true);
        if ($data && isset($data['blockdevices'][0]['children'])) {
            foreach ($data['blockdevices'][0]['children'] as $part) {
                $partName = $part['name'];
                $size = $part['size'] ?? '0';
                $fstype = $part['fstype'] ?? '';
                $mountPoint = $part['mountpoint'] ?? '';
                $label = $part['label'] ?? '';
                $uuid = $part['uuid'] ?? '';
                
                $sizeBytes = parseSizeToBytes($size);
                $hasFilesystem = !empty($fstype);
                
                $fstabEntry = null;
                if (!empty($uuid)) {
                    $fstabCheck = execCmd("grep -E \"UUID={$uuid}|/dev/{$partName}\" /etc/fstab 2>/dev/null", true, 3);
                    if (!empty($fstabCheck)) {
                        $fstabEntry = $fstabCheck;
                    }
                }
                
                $partitions[] = [
                    'name' => $partName,
                    'path' => "/dev/{$partName}",
                    'size_bytes' => $sizeBytes,
                    'size_formatted' => $size,
                    'fstype' => $fstype ?: null,
                    'mount_point' => $mountPoint ?: null,
                    'label' => $label ?: null,
                    'uuid' => $uuid ?: null,
                    'fstab_entry' => $fstabEntry,
                    'has_filesystem' => $hasFilesystem,
                    'is_raid_partition' => true,
                    'parent_raid' => $mdName
                ];
            }
        }
    }
    
    if (empty($partitions)) {
        $partedOutput = execCmd("parted {$mdPath} print 2>/dev/null", true, 10);
        if (!empty($partedOutput)) {
            preg_match_all('/\s+(\d+)\s+([0-9.]+[A-Z]?B)\s+([0-9.]+[A-Z]?B)\s+([0-9.]+[A-Z]?B)\s+(\S+)/', $partedOutput, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $partNum = $match[1];
                $partName = "{$mdName}p{$partNum}";
                $size = $match[4];
                $fstype = $match[5] ?? null;
                
                $partitions[] = [
                    'name' => $partName,
                    'path' => "/dev/{$partName}",
                    'size_bytes' => parseSizeToBytes($size),
                    'size_formatted' => $size,
                    'fstype' => ($fstype && $fstype !== 'unknown') ? $fstype : null,
                    'mount_point' => null,
                    'has_filesystem' => false,
                    'is_raid_partition' => true,
                    'parent_raid' => $mdName
                ];
            }
        }
    }
    
    return $partitions;
}

function getRaidPartitionTableType($mdName) {
    $output = execCmd("parted /dev/{$mdName} print 2>/dev/null | grep 'Partition Table' | awk '{print $3}'", true, 5);
    $output = strtolower(trim($output));
    if (strpos($output, 'gpt') !== false) return 'gpt';
    if (strpos($output, 'msdos') !== false || strpos($output, 'mbr') !== false) return 'mbr';
    return null;
}

function getPvCountForVg($vgName) {
    $output = execCmd("vgs --noheadings -o pv_count " . escapeshellarg($vgName) . " 2>/dev/null", true, 5);
    return intval(trim($output));
}

function getAllDisksWithVirtual() {
  $physicalDisks = getAllDisks();
    $lvmVgs = getLvmVgAsVirtualDisks();
    $raidArrays = getRaidArraysAsVirtualDisks();
    
    return array_merge($physicalDisks, $lvmVgs, $raidArrays);
}


function getMountedDevices() {
    $output = execCmd("mount | grep '^/dev/' | grep -v 'loop'", true, 5);
    $devices = [];
    
    if (!empty($output)) {
        $lines = explode("\n", $output);
        foreach ($lines as $line) {
            if (preg_match('/^\/dev\/(\S+)\s+on\s+(\S+)\s+type\s+(\S+)/', $line, $matches)) {
                $devices[] = [
                    'device' => $matches[1],
                    'mount_point' => $matches[2],
                    'fstype' => $matches[3]
                ];
            }
        }
    }
    
    return $devices;
}

function getRootDisk() {
    $rootDev = execCmd("df / | tail -1 | awk '{print $1}'", true, 5);
    $rootDev = preg_replace('/[0-9]+$/', '', $rootDev);
    $rootDev = preg_replace('/p\d+$/', '', $rootDev);
    $rootDev = str_replace('/dev/', '', $rootDev);
    return trim($rootDev);
}

function parseSizeToBytes($size) {
    if (empty($size)) return 0;
    $size = trim($size);
    if (is_numeric($size)) return (float)$size;
    
    $unit = strtoupper(substr($size, -1));
    $num = (float)substr($size, 0, -1);
    
    switch ($unit) {
        case 'K': return $num * 1024;
        case 'M': return $num * 1024 * 1024;
        case 'G': return $num * 1024 * 1024 * 1024;
        case 'T': return $num * 1024 * 1024 * 1024 * 1024;
        default: return (float)$size;
    }
}

function isMtdDevice($deviceName) {
    return strpos($deviceName, 'mtdblock') !== false || 
           strpos($deviceName, 'mtd') !== false || 
           $deviceName === 'loop0' || 
           strpos($deviceName, 'loop') === 0;
}

function getAllDisks() {
    $rootDisk = getRootDisk();
    $disks = [];
    
    $lsblkJson = execCmd("lsblk -J -d -o NAME,TYPE,SIZE,MODEL,RM,RO,ROTA,SERIAL,STATE 2>/dev/null", true, 10);
    
    if (!empty($lsblkJson)) {
        $data = json_decode($lsblkJson, true);
        if ($data && isset($data['blockdevices'])) {
            foreach ($data['blockdevices'] as $device) {
                if ($device['type'] !== 'disk') continue;
                
                $diskName = $device['name'];
                
                if (isMtdDevice($diskName)) continue;
                
                $size = $device['size'] ?? '0';
                $model = $device['model'] ?? '';
                $removable = ($device['rm'] ?? '0') == '1';
                $readonly = ($device['ro'] ?? '0') == '1';
                $isRotational = ($device['rota'] ?? '1') == '1';
                $serial = $device['serial'] ?? '';
                $state = $device['state'] ?? 'running';
                $isSystem = ($diskName === $rootDisk);
                
                $sizeBytes = parseSizeToBytes($size);
                
                $hasPartitionTable = checkPartitionTable($diskName);
				$hasWholeDiskFs = false;
				$diskInfo = execCmd("lsblk -J -o NAME,FSTYPE /dev/{$diskName} 2>/dev/null", true, 5);
				$diskData = json_decode($diskInfo, true);
				if ($diskData && isset($diskData['blockdevices'][0]['fstype']) && !empty($diskData['blockdevices'][0]['fstype'])) {
					$hasWholeDiskFs = true;
				}
                $smartData = getFullSmartData($diskName);
                $temperature = getDiskTemperature($diskName);
				$lvmInfo = detectLvmOnDisk($diskName);
				$raidInfo = detectRaidOnDisk($diskName);
                
                $disks[] = [
                    'name' => $diskName,
                    'size_bytes' => $sizeBytes,
                    'model' => $model ?: ($diskName === $rootDisk ? 'System Disk' : 'Unknown'),
                    'type' => strpos($diskName, 'nvme') !== false ? 'nvme' : (strpos($diskName, 'sd') !== false ? 'sata' : 'other'),
                    'removable' => $removable,
                    'readonly' => $readonly,
                    'is_system' => $isSystem,
                    'has_partition_table' => $hasPartitionTable,
                    'partition_table_type' => $hasPartitionTable ? getPartitionTableType($diskName) : null,
                    'partitions' => getPartitions($diskName),
                    'serial' => $serial,
                    'state' => $state,
                    'is_rotational' => $isRotational,
                    'smart' => $smartData,
                    'temperature' => $temperature,
					'lvm_info' => $lvmInfo,
					'raid_info' => $raidInfo,
					'is_managed_by_lvm' => $lvmInfo !== null,
					'is_managed_by_raid' => $raidInfo !== null,
					'has_whole_disk_fs' => $hasWholeDiskFs

                ];
            }
        }
    }
    
    return $disks;
}

// ==================== LVM И RAID ФУНКЦИИ ====================

function detectLvmOnDisk($diskName) {
    $pvsOutput = execCmd("pvs --noheadings -o pv_name,vg_name 2>/dev/null", true, 10);
    if (empty($pvsOutput)) {
        return null;
    }
    
    $diskPath = "/dev/{$diskName}";
    $lines = explode("\n", trim($pvsOutput));
    
    foreach ($lines as $line) {
        if (strpos($line, $diskPath) !== false || strpos($line, $diskName) !== false) {
            $parts = preg_split('/\s+/', trim($line));
            if (count($parts) >= 2) {
                return [
                    'in_lvm' => true,
                    'pv_name' => $parts[0],
                    'vg_name' => $parts[1],
                    'type' => 'lvm_physical_volume'
                ];
            }
        }
    }
    
    return null;
}

function detectLvmOnPartition($partitionName) {
    $pvsOutput = execCmd("pvs --noheadings -o pv_name,vg_name 2>/dev/null", true, 10);
    if (empty($pvsOutput)) {
        return null;
    }
    
    $partPath = "/dev/{$partitionName}";
    $lines = explode("\n", trim($pvsOutput));
    
    foreach ($lines as $line) {
        if (strpos($line, $partPath) !== false || strpos($line, $partitionName) !== false) {
            $parts = preg_split('/\s+/', trim($line));
            if (count($parts) >= 2) {
                return [
                    'in_lvm' => true,
                    'pv_name' => $parts[0],
                    'vg_name' => $parts[1],
                    'type' => 'lvm_physical_volume'
                ];
            }
        }
    }
    
    return null;
}

function detectRaidOnDisk($diskName) {
    $disk = basename($diskName);
    
    $output = shell_exec("sudo mdadm --examine /dev/{$disk} 2>/dev/null");
    if ($output && strpos($output, 'md') !== false) {
        if (preg_match('/md(\d+)/', $output, $matches)) {
            return [
                'in_raid' => true,
                'raid_name' => 'md' . $matches[1],
                'type' => 'mdadm_raid'
            ];
        }
    }
    
    $mdstat = @file_get_contents('/proc/mdstat');
    if ($mdstat) {
        $pattern = '/' . preg_quote($disk, '/') . '\[\d+\]/';
        if (preg_match($pattern, $mdstat)) {
            if (preg_match('/(md\d+)\s+:\s+active/', $mdstat, $raidMatch)) {
                return [
                    'in_raid' => true,
                    'raid_name' => $raidMatch[1],
                    'type' => 'mdadm_raid'
                ];
            }
        }
    }
    
    $output = shell_exec("lsblk -o NAME,TYPE,MOUNTPOINT /dev/{$disk} 2>/dev/null | grep -c 'md'");
    if (trim($output) > 0) {
        return [
            'in_raid' => true,
            'type' => 'mdadm_raid'
        ];
    }
    
    return null;
}

function getLvmVolumeGroups() {
    $vgs = [];
    
    $vgsOutput = execCmd("vgs --noheadings -o vg_name,vg_size,vg_free,vg_attr 2>/dev/null", true, 10);
    if (empty($vgsOutput)) {
        return $vgs;
    }
    
    $lines = explode("\n", trim($vgsOutput));
    foreach ($lines as $line) {
        if (empty(trim($line))) continue;
        $parts = preg_split('/\s+/', trim($line));
        if (count($parts) >= 4) {
            $sizeBytes = parseSizeToBytes($parts[1]);
            $freeBytes = parseSizeToBytes($parts[2]);
            
            $vgs[] = [
                'name' => $parts[0],
                'size_bytes' => $sizeBytes,
                'size_gb' => round($sizeBytes / 1024 / 1024 / 1024, 2),
                'free_bytes' => $freeBytes,
                'free_gb' => round($freeBytes / 1024 / 1024 / 1024, 2),
                'attributes' => $parts[3],
                'is_active' => strpos($parts[3], 'a') !== false,
                'logical_volumes' => getLogicalVolumes($parts[0])
            ];
        }
    }
    
    return $vgs;
}

function getLogicalVolumes($vgName) {
    $lvs = [];
    
    $lvsOutput = execCmd("lvs --noheadings -o lv_name,lv_size,lv_attr,pool_lv,origin,data_percent,metadata_percent,path {$vgName} 2>/dev/null", true, 10);
    if (empty($lvsOutput)) {
        return $lvs;
    }
    
    $lines = explode("\n", trim($lvsOutput));
    foreach ($lines as $line) {
        if (empty(trim($line))) continue;
        $parts = preg_split('/\s+/', trim($line));
        if (count($parts) >= 3) {
            $sizeBytes = parseSizeToBytes($parts[1]);
            
            $lvs[] = [
                'name' => $parts[0],
                'size_bytes' => $sizeBytes,
                'size_gb' => round($sizeBytes / 1024 / 1024 / 1024, 2),
                'attributes' => $parts[2],
                'is_active' => strpos($parts[2], 'a') !== false,
                'path' => "/dev/{$vgName}/{$parts[0]}",
                'mount_point' => getMountPointForDevice("/dev/{$vgName}/{$parts[0]}")
            ];
        }
    }
    
    return $lvs;
}

function getMountPointForDevice($devicePath) {
    $mountOutput = execCmd("mount | grep '{$devicePath} ' | awk '{print $3}'", true, 5);
    return trim($mountOutput) ?: null;
}

function getRaidArrays() {
    $raids = [];
    
    $mdstat = @file_get_contents('/proc/mdstat');
    if (empty($mdstat)) {
        return $raids;
    }
    
    $lines = explode("\n", $mdstat);
    $currentRaid = null;
    
    foreach ($lines as $line) {
        if (preg_match('/^(md\d+)\s+:\s+active\s+raid(\d+)/', $line, $matches)) {
            $currentRaid = $matches[1];
            $raids[$currentRaid] = [
                'name' => $currentRaid,
                'raid_level' => 'raid' . $matches[2],
                'disks' => [],
                'size' => null,
                'status' => 'active'
            ];
        }
        
        if ($currentRaid && preg_match_all('/(sd[a-z]+|nvme\d+n\d+)/', $line, $diskMatches)) {
            foreach ($diskMatches[0] as $disk) {
                if (!in_array($disk, $raids[$currentRaid]['disks'])) {
                    $raids[$currentRaid]['disks'][] = $disk;
                }
            }
        }
        
        if ($currentRaid && preg_match('/(\d+)\s+blocks/', $line, $sizeMatch)) {
            $raids[$currentRaid]['size_bytes'] = (int)$sizeMatch[1] * 1024;
            $raids[$currentRaid]['size_gb'] = round($raids[$currentRaid]['size_bytes'] / 1024 / 1024 / 1024, 2);
        }
        
        if (empty(trim($line))) {
            $currentRaid = null;
        }
    }
    
    foreach ($raids as &$raid) {
        $detail = execCmd("mdadm --detail /dev/{$raid['name']} 2>/dev/null", true, 10);
        if (!empty($detail)) {
            if (preg_match('/State\s+:\s+(\S+)/', $detail, $stateMatch)) {
                $raid['state'] = $stateMatch[1];
            }
            if (preg_match('/Active\s+Devices\s+:\s+(\d+)/', $detail, $activeMatch)) {
                $raid['active_devices'] = (int)$activeMatch[1];
            }
            if (preg_match('/Working\s+Devices\s+:\s+(\d+)/', $detail, $workingMatch)) {
                $raid['working_devices'] = (int)$workingMatch[1];
            }
            if (preg_match('/Failed\s+Devices\s+:\s+(\d+)/', $detail, $failedMatch)) {
                $raid['failed_devices'] = (int)$failedMatch[1];
            }
        }
    }
    
    return array_values($raids);
}

function getRaidFreeSpaceStart($raidName) {
    $raidPath = "/dev/{$raidName}";
    
    $existingParts = getRaidPartitions($raidName);
    
    if (empty($existingParts)) {
        return '0%';
    }
    
    $lastPart = end($existingParts);
    
    $partedOutput = execCmd("parted {$raidPath} unit B print 2>/dev/null | grep '{$lastPart['name']}'", true, 10);
    
    if (preg_match('/(\d+)B\s+(\d+)B/', $partedOutput, $matches)) {
        $endBytes = (int)$matches[2];
        return ($endBytes + 1) . 'B';
    }
    
    return '0%';
}

function getRaidMaxNewPartitionSize($raidName) {
    $raidPath = "/dev/{$raidName}";
    $totalSize = 0;
    
    $sizeOutput = execCmd("blockdev --getsize64 {$raidPath} 2>/dev/null", true, 5);
    if (!empty($sizeOutput)) {
        $totalSize = (int)trim($sizeOutput);
    }
    
    if ($totalSize <= 0) {
        $partedOutput = execCmd("parted {$raidPath} unit B print 2>/dev/null | grep 'Disk {$raidPath}'", true, 5);
        if (preg_match('/(\d+)B/', $partedOutput, $matches)) {
            $totalSize = (int)$matches[1];
        }
    }
    
    $existingParts = getRaidPartitions($raidName);
    $usedSize = 0;
    foreach ($existingParts as $part) {
        $usedSize += $part['size_bytes'];
    }
    
    $freeSize = $totalSize - $usedSize;
    return max(0, $freeSize);
}

function createPartitionOnRaid($raidName, $size, $fsType = 'ext4', $format = true) {
    writeLog("=== CREATE PARTITION ON RAID START ===");
    writeLog("RAID: {$raidName}, size: " . ($size === '' ? 'ALL FREE SPACE' : $size) . ", fsType: {$fsType}, format: " . ($format ? 'true' : 'false'));
    
    $raidPath = "/dev/{$raidName}";
    
    if (!file_exists($raidPath)) {
        $error = "RAID device {$raidPath} not found";
        writeError($error);
        return ['success' => false, 'error' => $error];
    }
    
    $totalSize = (int)trim(execCmd("blockdev --getsize64 {$raidPath} 2>/dev/null", true, 5));
    if ($totalSize <= 0) {
        $totalSize = (int)trim(execCmd("parted {$raidPath} unit B print 2>/dev/null | grep 'Disk {$raidPath}' | awk '{print $3}' | tr -d 'B'", true, 5));
    }
    writeLog("RAID total size: " . round($totalSize/1024/1024/1024, 2) . " GB ({$totalSize} bytes)");
    
    $tableType = getRaidPartitionTableType($raidName);
    writeLog("Current partition table type: " . ($tableType ?: 'none'));
    
    if (!$tableType) {
        writeLog("No partition table found, creating GPT");
        $initResult = execCmd("parted -s {$raidPath} mklabel gpt 2>&1", true, 20);
        if (strpos($initResult, 'Error') !== false) {
            return ['success' => false, 'error' => "Failed to create partition table: {$initResult}"];
        }
        $tableType = 'gpt';
        writeLog("Created GPT partition table");
        sleep(1);
    }
    
    $existingParts = getRaidPartitions($raidName);
    writeLog("Existing partitions count: " . count($existingParts));
    
    $partedOutput = execCmd("parted {$raidPath} unit B print free 2>/dev/null", true, 10);
    writeLog("Parted output with free:\n{$partedOutput}");
    
    $freeSpaceStart = 0;
    $freeSpaceEnd = 0;
    $lastEndByte = 0;
    
    $lines = explode("\n", $partedOutput);
    $inTable = false;
    
    foreach ($lines as $line) {
        $line = trim($line);
        
        if (strpos($line, 'Number') !== false && strpos($line, 'Start') !== false) {
            $inTable = true;
            continue;
        }
        if (!$inTable || empty($line)) continue;
        if (strpos($line, 'Disk') === 0) continue;
        if (strpos($line, 'Partition Table') !== false) continue;
        
        if (preg_match('/^\s*(\d+)\s+(\d+)B\s+(\d+)B/', $line, $matches)) {
            $endByte = (int)$matches[3];
            if ($endByte > $lastEndByte) {
                $lastEndByte = $endByte;
            }
        }
        
        if (strpos($line, 'Free Space') !== false) {
            if (preg_match('/(\d+)B\s+(\d+)B\s+\d+B\s+Free Space/', $line, $matches)) {
                $freeSpaceStart = (int)$matches[1];
                $freeSpaceEnd = (int)$matches[2];
                writeLog("Found free space: {$freeSpaceStart}B - {$freeSpaceEnd}B, size: " . round(($freeSpaceEnd - $freeSpaceStart)/1024/1024/1024, 2) . " GB");
            }
        }
    }
    
    $startByte = 0;
    if ($freeSpaceStart > 0) {
        $startByte = $freeSpaceStart;
    } elseif ($lastEndByte > 0) {
        $startByte = $lastEndByte + 1048576;
    } else {
        $startByte = 1048576;
    }
    
    if (empty($existingParts)) {
        $startByte = 1048576;
    }
    
    writeLog("Start position: {$startByte} bytes (" . round($startByte/1024/1024, 2) . " MB)");
    
    $useAllSpace = false;
    $endByte = 0;
    
    $isEmptySize = (empty($size) || $size === '' || $size === '0' || trim($size) === '' || 
                    strtolower($size) === 'all' || strtolower($size) === '100%');
    
    if ($isEmptySize) {
        if ($freeSpaceEnd > 0) {
            $endByte = $freeSpaceEnd;
        } else {
            $endByte = $totalSize;
        }
        $useAllSpace = true;
        writeLog("EMPTY SIZE - Using all free space, end at: {$endByte} bytes");
    } elseif (strpos($size, '%') !== false) {
        $percent = (float)$size / 100;
        $freeBytes = $freeSpaceEnd - $freeSpaceStart;
        $sizeBytes = (int)($freeBytes * $percent);
        $endByte = $startByte + $sizeBytes;
        writeLog("Using {$size} of free space: " . round($sizeBytes/1024/1024/1024, 2) . " GB");
    } elseif (is_numeric($size)) {
        $sizeGb = (float)$size;
        $sizeBytes = (int)($sizeGb * 1024 * 1024 * 1024);
        $endByte = $startByte + $sizeBytes;
        writeLog("Requested size: {$sizeGb} GB, end at: {$endByte} bytes");
    } else {
        $sizeBytes = parseSizeToBytes($size);
        if ($sizeBytes > 0) {
            $endByte = $startByte + $sizeBytes;
            writeLog("Parsed size: {$size} -> {$sizeBytes} bytes");
        } else {
            $endByte = $totalSize;
            $useAllSpace = true;
            writeLog("Could not parse size, using all space");
        }
    }
    
    if ($endByte > $totalSize) {
        $endByte = $totalSize;
        writeLog("Adjusted end to disk size: {$endByte}");
    }
    
    if ($endByte <= $startByte) {
        $error = "Invalid partition size: end ({$endByte}) <= start ({$startByte})";
        writeError($error);
        return ['success' => false, 'error' => $error];
    }
    
    $partitionSizeGb = round(($endByte - $startByte) / 1024 / 1024 / 1024, 2);
    writeLog("Creating partition: start={$startByte}B, end={$endByte}B, size={$partitionSizeGb} GB");
    
    $startPercent = round(($startByte / $totalSize) * 100, 2);
    $endPercent = round(($endByte / $totalSize) * 100, 2);
    
    writeLog("Start percent: {$startPercent}%, End percent: {$endPercent}%");
    
    $cmd = "parted -s {$raidPath} mkpart primary {$startPercent}% {$endPercent}% 2>&1";
    writeLog("Executing: {$cmd}");
    $output = execCmd($cmd, true, 30);
    
    if (strpos($output, 'Error') !== false || strpos($output, 'error') !== false) {
        $cmd2 = "parted -s {$raidPath} unit B mkpart primary {$startByte}B {$endByte}B 2>&1";
        writeLog("Retry with bytes: {$cmd2}");
        $output = execCmd($cmd2, true, 30);
        
        if (strpos($output, 'Error') !== false || strpos($output, 'error') !== false) {
            $startMiB = ceil($startByte / 1024 / 1024);
            $endMiB = floor($endByte / 1024 / 1024);
            $cmd3 = "parted -s {$raidPath} unit MiB mkpart primary {$startMiB}MiB {$endMiB}MiB 2>&1";
            writeLog("Retry with MiB: {$cmd3}");
            $output = execCmd($cmd3, true, 30);
            
            if (strpos($output, 'Error') !== false || strpos($output, 'error') !== false) {
                writeError("Partition creation failed: {$output}");
                return ['success' => false, 'error' => "Не удалось создать раздел. Ошибка: " . $output];
            }
        }
    }
    
    execCmd("partprobe {$raidPath}", true, 5);
    execCmd("udevadm settle", true, 5);
    sleep(2);
    
    $nextNum = count($existingParts) + 1;
    $partitionName = "{$raidName}p{$nextNum}";
    
    $maxAttempts = 15;
    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        if (file_exists("/dev/{$partitionName}")) {
            writeLog("Partition device found: /dev/{$partitionName}");
            break;
        }
        
        $altName = "{$raidName}{$nextNum}";
        if (file_exists("/dev/{$altName}")) {
            $partitionName = $altName;
            writeLog("Found partition with alt name: /dev/{$partitionName}");
            break;
        }
        
        sleep(1);
    }
    
    if (!file_exists("/dev/{$partitionName}")) {
        return [
            'success' => false,
            'error' => "Раздел создан, но устройство /dev/{$partitionName} не найдено. Попробуйте: partprobe {$raidPath}",
            'partition' => $partitionName
        ];
    }
    
    if ($format && $fsType !== 'none') {
        writeLog("Formatting partition {$partitionName} with {$fsType}");
        $formatResult = formatPartition($partitionName, $fsType, '', true);
        if (!$formatResult['success']) {
            return [
                'success' => false,
                'error' => "Раздел создан, но форматирование не удалось: " . $formatResult['error'],
                'partition' => $partitionName
            ];
        }
    }
    
    writeLog("=== CREATE PARTITION ON RAID END: success, partition={$partitionName} ===");
    
    return [
        'success' => true,
        'partition' => $partitionName,
        'used_all_space' => $useAllSpace,
        'size_gb' => $partitionSizeGb
    ];
}

function getRaidFreeSpaceInfo($raidName) {
    $raidPath = "/dev/{$raidName}";
    $result = [
        'free_bytes' => 0,
        'free_gb' => 0,
        'total_bytes' => 0,
        'total_gb' => 0,
        'used_bytes' => 0,
        'used_gb' => 0,
        'has_partition_table' => false,
        'existing_partitions' => [],
        'next_free_position' => null,
        'next_free_position_mib' => null,
        'start_sector' => null
    ];
    
    if (!file_exists($raidPath)) {
        return $result;
    }
    
    $totalSize = execCmd("blockdev --getsize64 {$raidPath} 2>/dev/null", true, 5);
    if (!empty($totalSize)) {
        $result['total_bytes'] = (int)trim($totalSize);
        $result['total_gb'] = round($result['total_bytes'] / 1024 / 1024 / 1024, 2);
    }
    
    $existingParts = getRaidPartitions($raidName);
    $result['existing_partitions'] = $existingParts;
    $result['has_partition_table'] = !empty($existingParts) || getRaidPartitionTableType($raidName) !== null;
    
    $usedBytes = 0;
    foreach ($existingParts as $part) {
        $usedBytes += $part['size_bytes'];
    }
    $result['used_bytes'] = $usedBytes;
    $result['used_gb'] = round($usedBytes / 1024 / 1024 / 1024, 2);
    
    $result['free_bytes'] = max(0, $result['total_bytes'] - $usedBytes);
    $result['free_gb'] = round($result['free_bytes'] / 1024 / 1024 / 1024, 2);
    
    $nextPos = getRaidNextFreePosition($raidName);
    $result['next_free_position'] = $nextPos;
    
    if (preg_match('/(\d+(?:\.\d+)?)MiB/', $nextPos, $matches)) {
        $result['next_free_position_mib'] = (float)$matches[1];
    }
    
    $ptType = getRaidPartitionTableType($raidName);
    if ($ptType === 'gpt') {
        $result['start_sector'] = 34;
    } else {
        $result['start_sector'] = 1;
    }
    
    return $result;
}

function resizeRaidPartition($partition, $newSizeGb) {
    writeLog("=== RESIZE RAID PARTITION START ===");
    writeLog("Partition: {$partition}, new size: {$newSizeGb} GB");
    
    if ($newSizeGb <= 0) {
        return ['success' => false, 'error' => 'Invalid size'];
    }
    
    $cleanPartition = preg_replace('#^/dev/#', '', $partition);
    $partitionPath = "/dev/{$cleanPartition}";
    
    if (!file_exists($partitionPath)) {
        return ['success' => false, 'error' => "Partition {$partitionPath} not found"];
    }
    
    $raidName = null;
    $partNum = null;
    
    if (preg_match('/^md(\d+)p(\d+)$/i', $cleanPartition, $matches)) {
        $raidName = 'md' . $matches[1];
        $partNum = (int)$matches[2];
        writeLog("Parsed format mdXpY: raid={$raidName}, num={$partNum}");
    }
    elseif (preg_match('/^md(\d+)_(\d+)$/i', $cleanPartition, $matches)) {
        $raidName = 'md' . $matches[1];
        $partNum = (int)$matches[2];
        writeLog("Parsed format mdX_Y: raid={$raidName}, num={$partNum}");
    }
    elseif (preg_match('/^md(\d+)-(\d+)$/i', $cleanPartition, $matches)) {
        $raidName = 'md' . $matches[1];
        $partNum = (int)$matches[2];
        writeLog("Parsed format mdX-Y: raid={$raidName}, num={$partNum}");
    }
    elseif (preg_match('/^md(\d+)(\d+)$/i', $cleanPartition, $matches)) {
        $raidName = 'md' . $matches[1];
        $partNum = (int)$matches[2];
        writeLog("Parsed format mdXnum: raid={$raidName}, num={$partNum}");
    }
    else {
        $error = "Cannot parse RAID partition name: {$cleanPartition}";
        writeError($error);
        return ['success' => false, 'error' => $error];
    }
    
    $raidPath = "/dev/{$raidName}";
    writeLog("RAID path: {$raidPath}, partition number: {$partNum}");
    
    if (!file_exists($raidPath)) {
        return ['success' => false, 'error' => "RAID device {$raidPath} not found"];
    }
    
    $currentSizeBytes = (int)trim(execCmd("blockdev --getsize64 {$partitionPath} 2>/dev/null", true, 5));
    $currentSizeGb = round($currentSizeBytes / 1024 / 1024 / 1024, 2);
    writeLog("Current size: {$currentSizeGb} GB ({$currentSizeBytes} bytes)");
    
    $totalSize = (int)trim(execCmd("blockdev --getsize64 {$raidPath} 2>/dev/null", true, 5));
    $totalSizeGb = round($totalSize / 1024 / 1024 / 1024, 2);
    writeLog("RAID total size: {$totalSizeGb} GB");
    
    if ($newSizeGb > $totalSizeGb) {
        return ['success' => false, 'error' => "New size {$newSizeGb}GB exceeds RAID total size {$totalSizeGb}GB"];
    }
    
    if ($newSizeGb < $currentSizeGb - 0.1) {
        return ['success' => false, 'error' => "Уменьшение размера раздела не поддерживается. Новый размер должен быть больше текущего ({$currentSizeGb}GB)"];
    }
    
    if (abs($newSizeGb - $currentSizeGb) < 0.1) {
        return ['success' => false, 'error' => "Новый размер совпадает с текущим"];
    }
    
    $mountCheck = execCmd("mount | grep '{$partitionPath} '", true, 3);
    $wasMounted = !empty($mountCheck);
    $mountPoint = null;
    
    if ($wasMounted) {
        $mountPoint = execCmd("mount | grep '{$partitionPath} ' | awk '{print $3}'", true, 3);
        $mountPoint = trim($mountPoint);
        writeLog("Unmounting {$cleanPartition} from {$mountPoint}");
        execCmd("umount {$partitionPath} 2>/dev/null", true, 10);
        sleep(2);
    }
    
    $fsType = trim(execCmd("blkid -s TYPE -o value {$partitionPath} 2>/dev/null", true, 5));
    writeLog("Filesystem type: {$fsType}");
    
    if ($fsType === 'ext4' || $fsType === 'ext3' || $fsType === 'ext2') {
        writeLog("Checking filesystem before resize");
        execCmd("e2fsck -f -y {$partitionPath} 2>/dev/null", true, 120);
    }
    
    $newSizePercent = round(($newSizeGb / $totalSizeGb) * 100, 2);
    $cmd = "parted -s {$raidPath} resizepart {$partNum} {$newSizePercent}% 2>&1";
    writeLog("Executing: {$cmd}");
    $output = execCmd($cmd, true, 30);
    
    if (strpos($output, 'Error') !== false || strpos($output, 'error') !== false) {
        $cmd2 = "parted -s {$raidPath} resizepart {$partNum} {$newSizeGb}GB 2>&1";
        writeLog("Retry with GB: {$cmd2}");
        $output = execCmd($cmd2, true, 30);
        
        if (strpos($output, 'Error') !== false && strpos($output, 'error') !== false) {
            $cmd3 = "parted -s {$raidPath} resizepart {$partNum} 100% 2>&1";
            writeLog("Retry with 100%: {$cmd3}");
            $output = execCmd($cmd3, true, 30);
            
            if (strpos($output, 'Error') !== false && strpos($output, 'error') !== false) {
                if ($wasMounted && $mountPoint) {
                    execCmd("mount {$partitionPath} \"{$mountPoint}\" 2>/dev/null", true, 10);
                }
                writeError("Resize failed: {$output}");
                return ['success' => false, 'error' => "Failed to resize partition: " . $output];
            }
        }
    }
    
    execCmd("partprobe {$raidPath}", true, 5);
    execCmd("blockdev --rereadpt {$raidPath} 2>/dev/null", true, 5);
    execCmd("udevadm settle", true, 5);
    sleep(2);
    
    if (!empty($fsType) && $fsType !== 'unknown') {
        writeLog("Resizing filesystem of type {$fsType}");
        
        switch ($fsType) {
            case 'ext4':
            case 'ext3':
            case 'ext2':
                $resizeCmd = "resize2fs {$partitionPath} 2>/dev/null";
                writeLog("Executing: {$resizeCmd}");
                execCmd($resizeCmd, true, 120);
                break;
            case 'xfs':
                if ($wasMounted && $mountPoint) {
                    execCmd("xfs_growfs {$mountPoint} 2>/dev/null", true, 30);
                }
                break;
            case 'ntfs':
                execCmd("ntfsresize -f {$partitionPath} 2>/dev/null", true, 120);
                break;
            case 'btrfs':
                if ($wasMounted && $mountPoint) {
                    execCmd("btrfs filesystem resize max {$mountPoint} 2>/dev/null", true, 60);
                }
                break;
        }
    }
    
    if ($wasMounted && $mountPoint) {
        writeLog("Remounting {$cleanPartition} to {$mountPoint}");
        execCmd("mount {$partitionPath} \"{$mountPoint}\" 2>/dev/null", true, 10);
    }
    
    $newActualSizeBytes = (int)trim(execCmd("blockdev --getsize64 {$partitionPath} 2>/dev/null", true, 5));
    $newActualSizeGb = round($newActualSizeBytes / 1024 / 1024 / 1024, 2);
    
    writeLog("=== RESIZE RAID PARTITION END: success, new size={$newActualSizeGb}GB ===");
    
    return [
        'success' => true,
        'old_size_gb' => $currentSizeGb,
        'new_size_gb' => $newActualSizeGb,
        'fs_resized' => !empty($fsType)
    ];
}

function getLvmOnRaidInfo() {
    $result = [];
    
    $pvsOutput = execCmd("pvs --noheadings -o pv_name,vg_name 2>/dev/null", true, 10);
    if (empty($pvsOutput)) {
        return $result;
    }
    
    $lines = explode("\n", trim($pvsOutput));
    foreach ($lines as $line) {
        if (empty(trim($line))) continue;
        $parts = preg_split('/\s+/', trim($line));
        if (count($parts) >= 2) {
            $pvName = $parts[0];
            
            if (strpos($pvName, '/dev/md') !== false) {
                $result[] = [
                    'pv_name' => $pvName,
                    'vg_name' => $parts[1],
                    'type' => 'lvm_on_raid'
                ];
            }
        }
    }
    
    return $result;
}

function getFullSmartData($disk) {
    $smart = [
        'available' => false,
        'health' => 'Unknown',
        'health_text' => 'Неизвестно',
        'reallocated_sectors' => null,
        'power_on_hours' => null,
        'power_on_days' => null,
        'temperature' => null,
        'temperature_airflow' => null,
        'raw_read_error_rate' => null,
        'seek_error_rate' => null,
        'spin_retry_count' => null,
        'command_timeout' => null,
        'current_pending_sector' => null,
        'offline_uncorrectable' => null,
        'udma_crc_errors' => null,
        'percentage_used' => null,
        'start_stop_count' => null,
        'power_cycle_count' => null,
        'load_cycle_count' => null,
        'high_fly_writes' => null,
        'g_sense_error_rate' => null,
        'hardware_ecc_recovered' => null,
        'attributes' => []
    ];
    
    $smartSupport = execCmd("smartctl -i /dev/{$disk} 2>/dev/null | grep 'SMART support is:'", true, 5);
    if (empty($smartSupport)) {
        return $smart;
    }
    
    $smart['available'] = true;
    
    $healthOutput = execCmd("smartctl -H /dev/{$disk} 2>/dev/null", true, 5);
    if (preg_match('/SMART overall-health self-assessment test result: (\w+)/i', $healthOutput, $matches)) {
        $health = $matches[1];
        if (strtoupper($health) === 'PASSED') {
            $smart['health'] = 'PASSED';
            $smart['health_text'] = 'Хорошее';
        } else {
            $smart['health'] = 'FAILED';
            $smart['health_text'] = 'КРИТИЧЕСКОЕ!';
        }
    }
    
    $attributesOutput = execCmd("smartctl -A /dev/{$disk} 2>/dev/null", true, 10);
    
    if (!empty($attributesOutput)) {
        $lines = explode("\n", $attributesOutput);
        $inDataSection = false;
        
        foreach ($lines as $line) {
            if (strpos($line, 'ID#') !== false || strpos($line, 'ATTRIBUTE_NAME') !== false) {
                $inDataSection = true;
                continue;
            }
            
            if (!$inDataSection) continue;
            
            if (empty(trim($line)) || strpos($line, '-----') !== false) continue;
            
            if (preg_match('/^\s*(\d+)\s+([^\s]+)\s+0x[0-9a-f]+\s+(\d+)\s+(\d+)\s+(\d+)\s+([^\s]+)\s+([^\s]+)\s+([^\s]*)\s+(.+?)$/', $line, $matches)) {
                $id = (int)$matches[1];
                $name = $matches[2];
                $value = (int)$matches[3];
                $worst = (int)$matches[4];
                $threshold = (int)$matches[5];
                $type = $matches[6];
                $updated = $matches[7];
                $whenFailed = trim($matches[8]);
                $rawValue = trim($matches[9]);
                
                $rawValueClean = preg_replace('/\s+/', ' ', $rawValue);
                
                $rawNumber = (int)preg_replace('/[^0-9].*$/', '', $rawValue);
                
                $smart['attributes'][] = [
                    'id' => $id,
                    'name' => $name,
                    'value' => $value,
                    'worst' => $worst,
                    'threshold' => $threshold,
                    'type' => $type,
                    'updated' => $updated,
                    'when_failed' => $whenFailed,
                    'raw' => $rawValueClean
                ];
                
                switch ($id) {
                    case 1: // Raw Read Error Rate
                        $smart['raw_read_error_rate'] = $rawNumber;
                        break;
                    case 4: // Start/Stop Count
                        $smart['start_stop_count'] = $rawNumber;
                        break;
                    case 5: // Reallocated Sectors Count
                        if (preg_match('/(\d+)/', $rawValue, $reallocMatch)) {
                            $smart['reallocated_sectors'] = (int)$reallocMatch[1];
                        } else {
                            $smart['reallocated_sectors'] = $rawNumber;
                        }
                        break;
                    case 7: // Seek Error Rate
                        $smart['seek_error_rate'] = $rawNumber;
                        break;
                    case 9: // Power-On Hours
                        $smart['power_on_hours'] = $rawNumber;
                        $smart['power_on_days'] = round($rawNumber / 24, 1);
                        break;
                    case 10: // Spin Retry Count
                        $smart['spin_retry_count'] = $rawNumber;
                        break;
                    case 12: // Power Cycle Count
                        $smart['power_cycle_count'] = $rawNumber;
                        break;
                    case 187: // Reported Uncorrectable Errors
                        $smart['reported_uncorrect'] = $rawNumber;
                        break;
                    case 188: // Command Timeout
                        $smart['command_timeout'] = $rawNumber;
                        break;
                    case 189: // High Fly Writes
                        $smart['high_fly_writes'] = $rawNumber;
                        break;
                    case 190: // Airflow Temperature
                        if (preg_match('/(\d+)/', $rawValue, $tempMatch)) {
                            $smart['temperature_airflow'] = (int)$tempMatch[1];
                        }
                        if ($smart['temperature'] === null) {
                            $smart['temperature'] = $smart['temperature_airflow'];
                        }
                        break;
                    case 191: // G-Sense Error Rate
                        $smart['g_sense_error_rate'] = $rawNumber;
                        break;
                    case 192: // Power-Off Retract Count
                        $smart['power_off_retract_count'] = $rawNumber;
                        break;
                    case 193: // Load/Unload Cycle Count
                        $smart['load_cycle_count'] = $rawNumber;
                        break;
                    case 194: // Temperature Celsius
                        if (preg_match('/(\d+)/', $rawValue, $tempMatch)) {
                            $smart['temperature'] = (int)$tempMatch[1];
                        }
                        break;
                    case 195: // Hardware ECC Recovered
                        $smart['hardware_ecc_recovered'] = $rawNumber;
                        break;
                    case 197: // Current Pending Sector
                        $smart['current_pending_sector'] = $rawNumber;
                        break;
                    case 198: // Offline Uncorrectable
                        $smart['offline_uncorrectable'] = $rawNumber;
                        break;
                    case 199: // UDMA CRC Error Count
                        $smart['udma_crc_errors'] = $rawNumber;
                        break;
                }
            }
        }
    }
    
    writeLog("SMART parsed for {$disk}: Temp={$smart['temperature']}°C, Hours={$smart['power_on_hours']}, Reallocated={$smart['reallocated_sectors']}");
    
    return $smart;
}

function getSmartAttributeName($id) {
    $names = [
        1 => 'Raw Read Error Rate',
        2 => 'Throughput Performance',
        3 => 'Spin Up Time',
        4 => 'Start/Stop Count',
        5 => 'Reallocated Sectors Count',
        7 => 'Seek Error Rate',
        8 => 'Seek Time Performance',
        9 => 'Power-On Hours',
        10 => 'Spin Retry Count',
        12 => 'Power Cycle Count',
        13 => 'Read Soft Error Rate',
        100 => 'Total LBAs Written',
        101 => 'Total LBAs Read',
        187 => 'Reported Uncorrectable Errors',
        188 => 'Command Timeout',
        190 => 'Airflow Temperature',
        191 => 'G-Sense Error Rate',
        192 => 'Power-Off Retract Count',
        193 => 'Load/Unload Cycle Count',
        194 => 'Temperature',
        195 => 'Hardware ECC Recovered',
        196 => 'Reallocation Event Count',
        197 => 'Current Pending Sector Count',
        198 => 'Offline Uncorrectable Sector Count',
        199 => 'UltraDMA CRC Error Count',
        200 => 'Write Error Rate',
        241 => 'Total LBAs Written',
        242 => 'Total LBAs Read'
    ];
    return $names[$id] ?? "Attribute $id";
}

function getDiskTemperature($disk) {
    $smart = getFullSmartData($disk);
    
    if ($smart['temperature'] !== null && $smart['temperature'] > 0 && $smart['temperature'] < 100) {
        return $smart['temperature'];
    }
    
    if (isset($smart['temperature_airflow']) && $smart['temperature_airflow'] > 0 && $smart['temperature_airflow'] < 100) {
        return $smart['temperature_airflow'];
    }
    
    $tempOutput = execCmd("smartctl -A /dev/{$disk} 2>/dev/null | grep -E '^\\s+194\\s+'", true, 5);
    if (preg_match('/\s+194\s+Temperature_Celsius\s+0x[0-9a-f]+\s+\d+\s+\d+\s+\d+\s+[^\s]+\s+[^\s]+\s+[^\s]*\s+(\d+)/', $tempOutput, $matches)) {
        $temp = (int)$matches[1];
        if ($temp > 0 && $temp < 100) {
            return $temp;
        }
    }
    
    $tempOutput = execCmd("smartctl -A /dev/{$disk} 2>/dev/null | grep -E 'Temperature|Temp' | head -1", true, 5);
    if (preg_match('/(\d+)[^\d]*$/', $tempOutput, $matches)) {
        $temp = (int)$matches[1];
        if ($temp > 0 && $temp < 100) {
            return $temp;
        }
    }
    
    return null;
}

function getRawSmartOutput($disk) {
    return [
        'info' => execCmd("smartctl -i /dev/{$disk} 2>/dev/null", true, 10),
        'health' => execCmd("smartctl -H /dev/{$disk} 2>/dev/null", true, 10),
        'attributes' => execCmd("smartctl -A /dev/{$disk} 2>/dev/null", true, 10),
        'full' => execCmd("smartctl -a /dev/{$disk} 2>/dev/null", true, 10)
    ];
}

function checkPartitionTable($disk) {
    $output = execCmd("parted /dev/{$disk} print 2>/dev/null | grep -E 'Partition Table|partition table'", true, 5);
    return !empty($output) && strpos($output, 'unknown') === false;
}

function getPartitionTableType($disk) {
    $output = execCmd("parted /dev/{$disk} print 2>/dev/null | grep 'Partition Table' | awk '{print $3}'", true, 5);
    $output = strtolower(trim($output));
    if (strpos($output, 'gpt') !== false) return 'gpt';
    if (strpos($output, 'msdos') !== false || strpos($output, 'mbr') !== false) return 'mbr';
    return null;
}

function getPartitions($diskName) {
    $partitions = [];
    
    $tableType = trim(execCmd("parted /dev/{$diskName} print 2>/dev/null | grep 'Partition Table' | awk '{print $3}'", true, 5));
    
    $lsblkJson = execCmd("lsblk -J -o NAME,SIZE,FSTYPE,MOUNTPOINT,LABEL,UUID /dev/{$diskName} 2>/dev/null", true, 10);
    
    if (!empty($lsblkJson)) {
        $data = json_decode($lsblkJson, true);
        if ($data && isset($data['blockdevices'][0]['children'])) {
            foreach ($data['blockdevices'][0]['children'] as $part) {
                $size = $part['size'] ?? '0';
                $fstype = $part['fstype'] ?? '';
                $mountPoint = $part['mountpoint'] ?? '';
                $label = $part['label'] ?? '';
                $uuid = $part['uuid'] ?? '';
                
                if (empty($mountPoint)) $mountPoint = null;
                if (empty($fstype)) $fstype = null;
                if (empty($label)) $label = null;
                
                $sizeBytes = parseSizeToBytes($size);
                $hasFilesystem = ($fstype !== null && $fstype !== '');
                
                $wasCreatedByMount = false;
                $lvmPartInfo = detectLvmOnPartition($part['name']);
                
                if ($mountPoint && is_dir($mountPoint)) {
                    $markerFile = $mountPoint . '/.mount_created_by_disk_manager';
                    $wasCreatedByMount = file_exists($markerFile);
                }
                
                $fstabEntry = null;
                if ($uuid) {
                    $fstabCheck = execCmd("grep -E \"UUID={$uuid}|/dev/{$part['name']}\" /etc/fstab 2>/dev/null", true, 3);
                    if (!empty($fstabCheck)) {
                        $fstabEntry = parseFstabEntry($fstabCheck);
                    }
                }
                
                $partitions[] = [
                    'name' => $part['name'],
                    'size_bytes' => $sizeBytes,
                    'fstype' => $fstype,
                    'mount_point' => $mountPoint,
                    'label' => $label,
                    'uuid' => $uuid,
                    'fstab_entry' => $fstabEntry,
                    'was_created_by_mount' => $wasCreatedByMount,
                    'has_filesystem' => $hasFilesystem,
                    'lvm_info' => $lvmPartInfo,
                    'is_lvm_physical_volume' => $lvmPartInfo !== null,
                ];
            }
        } elseif ($data && isset($data['blockdevices'][0])) {
            $disk = $data['blockdevices'][0];
            $fstype = $disk['fstype'] ?? '';
            $mountPoint = $disk['mountpoint'] ?? '';
            $label = $disk['label'] ?? '';
            $uuid = $disk['uuid'] ?? '';
            
            if (!empty($fstype)) {
                if (empty($mountPoint)) $mountPoint = null;
                if (empty($label)) $label = null;
                
                $sizeBytes = parseSizeToBytes($disk['size'] ?? '0');
                $hasFilesystem = true;
                
                $wasCreatedByMount = false;
                if ($mountPoint && is_dir($mountPoint)) {
                    $markerFile = $mountPoint . '/.mount_created_by_disk_manager';
                    $wasCreatedByMount = file_exists($markerFile);
                }
                
                $fstabEntry = null;
                if ($uuid) {
                    $fstabCheck = execCmd("grep -E \"UUID={$uuid}|/dev/{$diskName}\" /etc/fstab 2>/dev/null", true, 3);
                    if (!empty($fstabCheck)) {
                        $fstabEntry = parseFstabEntry($fstabCheck);
                    }
                }
                
                $partitions[] = [
                    'name' => $diskName,
                    'size_bytes' => $sizeBytes,
                    'fstype' => $fstype,
                    'mount_point' => $mountPoint,
                    'label' => $label,
                    'uuid' => $uuid,
                    'fstab_entry' => $fstabEntry,
                    'was_created_by_mount' => $wasCreatedByMount,
                    'has_filesystem' => $hasFilesystem,
                    'is_whole_disk_fs' => true,
                    'lvm_info' => null,
                    'is_lvm_physical_volume' => false,
                ];
                
                writeLog("Detected whole-disk filesystem on {$diskName} with fstype {$fstype}, table type: {$tableType}");
            }
        }
    }
    
    return $partitions;
}

function getRaidNextFreePosition($raidName) {
    $raidPath = "/dev/{$raidName}";
    
    $existingParts = getRaidPartitions($raidName);
    
    if (empty($existingParts)) {
        return '1MiB';
    }
    
    $output = execCmd("parted {$raidPath} unit MiB print free 2>/dev/null", true, 10);
    
    if (!empty($output)) {
        $lines = explode("\n", $output);
        $lastFreeStart = null;
        $lastFreeEnd = null;
        
        foreach ($lines as $line) {
            if (strpos($line, 'Free Space') !== false) {
                if (preg_match('/(\d+(?:\.\d+)?)MiB\s+(\d+(?:\.\d+)?)MiB\s+Free Space/', $line, $matches)) {
                    $start = (float)$matches[1];
                    $end = (float)$matches[2];
                    if ($end > $start) {
                        $lastFreeStart = $start;
                        $lastFreeEnd = $end;
                    }
                }
            }
        }
        
        if ($lastFreeStart !== null && $lastFreeStart > 0) {
            return $lastFreeStart . 'MiB';
        }
    }
    
    if (!empty($existingParts)) {
        $lastPart = end($existingParts);
        
        $partInfo = execCmd("parted {$raidPath} unit MiB print 2>/dev/null | grep '{$lastPart['name']}\s'", true, 10);
        
        if (preg_match('/(\d+(?:\.\d+)?)MiB\s+(\d+(?:\.\d+)?)MiB/', $partInfo, $matches)) {
            $endPos = (float)$matches[2];
            $newStart = ceil($endPos) + 1;
            return $newStart . 'MiB';
        }
    }
    
    return '1MiB';
}

function deleteRaidPartition($partition) {
    writeLog("Deleting RAID partition: {$partition}");
    
    $parsed = parseRaidPartitionName($partition);
    if (!$parsed['is_raid'] || $parsed['is_array']) {
        $error = "Cannot parse RAID partition name: {$partition}";
        writeError($error);
        return ['success' => false, 'error' => $error];
    }
    
    $raidName = $parsed['raid_name'];
    $partNum = $parsed['partition_num'];
    $raidPath = "/dev/{$raidName}";
    
    if (!file_exists($raidPath)) {
        $error = "RAID device {$raidPath} not found";
        writeError($error);
        return ['success' => false, 'error' => $error];
    }
    
    removeFromFstab($partition);
    
    $mountCheck = execCmd("mount | grep '/dev/{$partition} '", true, 3);
    if (!empty($mountCheck)) {
        $mountPoint = execCmd("mount | grep '/dev/{$partition} ' | awk '{print $3}'", true, 3);
        writeLog("Unmounting {$partition} from {$mountPoint}");
        execCmd("umount /dev/{$partition} 2>/dev/null", true, 10);
        sleep(1);
    }
    
    $cmd = "parted -s {$raidPath} rm {$partNum} 2>&1";
    writeLog("Executing: {$cmd}");
    $output = execCmd($cmd, true, 20);
    
    execCmd("partprobe {$raidPath}", true, 5);
    execCmd("udevadm settle", true, 5);
    
    $success = (strpos($output, 'Error') === false && strpos($output, 'error') === false);
    
    if ($success) {
        writeLog("RAID partition {$partition} deleted successfully");
    } else {
        writeError("Delete RAID partition failed: {$output}", ['partition' => $partition]);
    }
    
    return ['success' => $success, 'error' => $success ? '' : $output];
}

function parseFstabEntry($line) {
    $parts = preg_split('/\s+/', trim($line));
    if (count($parts) >= 3) {
        return [
            'device' => $parts[0],
            'mount_point' => $parts[1],
            'fstype' => $parts[2],
            'options' => $parts[3] ?? 'defaults',
            'dump' => $parts[4] ?? '0',
            'pass' => $parts[5] ?? '0'
        ];
    }
    return null;
}

function initDisk($disk, $tableType = 'gpt') {
    writeLog("Initializing disk: {$disk}, table type: {$tableType}");
    
    execCmd("wipefs -a /dev/{$disk} 2>/dev/null", true, 10);
    
    $cmd = "parted -s /dev/{$disk} mklabel {$tableType} 2>&1";
    $output = execCmd($cmd, true, 20);
    
    if (strpos($output, 'Error') !== false || strpos($output, 'error') !== false) {
        writeError("Init disk failed: {$output}", ['disk' => $disk]);
        return ['success' => false, 'error' => $output];
    }
    
    execCmd("partprobe /dev/{$disk}", true, 5);
    
    writeLog("Disk {$disk} initialized with {$tableType}");
    return ['success' => true, 'table_type' => $tableType];
}

function createLvmLogicalVolume($vgName, $lvName, $size, $fsType = 'ext4', $format = true) {
    writeLog("Creating LV: {$vgName}/{$lvName}, size: {$size}, fs: {$fsType}");
    
    $vgCheck = execCmd("vgs " . escapeshellarg($vgName) . " 2>/dev/null", true, 5);
    if (empty($vgCheck)) {
        return ['success' => false, 'error' => "Volume Group {$vgName} not found"];
    }
    
    $freeSpace = execCmd("vgs --noheadings -o vg_free --units g " . escapeshellarg($vgName) . " 2>/dev/null | tr -d ' '", true, 5);
    $freeSpace = floatval($freeSpace);
    
    $sizeNum = floatval($size);
    $sizeUnit = strtoupper(preg_replace('/[0-9.]/', '', $size));
    if (empty($sizeUnit)) $sizeUnit = 'G';
    
    $sizeInG = $sizeNum;
    if ($sizeUnit === 'M') $sizeInG = $sizeNum / 1024;
    if ($sizeUnit === 'T') $sizeInG = $sizeNum * 1024;
    
    if ($sizeInG > $freeSpace) {
        return ['success' => false, 'error' => "Not enough free space in VG. Available: {$freeSpace}G, Requested: {$sizeInG}G"];
    }
    
    $sizeArg = is_numeric($size) ? $size . 'G' : $size;
    $cmd = "lvcreate -n " . escapeshellarg($lvName) . " -L " . escapeshellarg($sizeArg) . " " . escapeshellarg($vgName);
    $result = execCmd($cmd, true, 60);
    
    if (strpos($result, 'successfully created') !== false || strpos($result, 'Logical volume') !== false) {
        sleep(2);
        $lvPath = "/dev/{$vgName}/{$lvName}";
        
        if ($format && $fsType !== 'none') {
            $formatResult = formatPartition(basename($lvPath), $fsType, '', true);
            if (!$formatResult['success']) {
                return ['success' => false, 'error' => "LV created but format failed: " . $formatResult['error']];
            }
        }
        
        return ['success' => true, 'lv_path' => $lvPath, 'lv_name' => $lvName];
    }
    
    return ['success' => false, 'error' => $result];
}


function getLvmVgInfo($vgName) {
    $info = [];
    
    $vgInfo = execCmd("vgs --noheadings -o vg_name,vg_size,vg_free,vg_attr,pv_count,lv_count " . escapeshellarg($vgName) . " 2>/dev/null", true, 10);
    if (!empty($vgInfo)) {
        $parts = preg_split('/\s+/', trim($vgInfo));
        if (count($parts) >= 6) {
            $info = [
                'name' => $parts[0],
                'size_bytes' => parseSizeToBytes($parts[1]),
                'free_bytes' => parseSizeToBytes($parts[2]),
                'attributes' => $parts[3],
                'pv_count' => intval($parts[4]),
                'lv_count' => intval($parts[5]),
                'logical_volumes' => getLvmLvsAsPartitions($vgName)
            ];
        }
    }
    
    return $info;
}

function getRaidArrayInfo($raidName) {
    $info = [];
    
    $detail = execCmd("mdadm --detail /dev/" . escapeshellarg($raidName) . " 2>/dev/null", true, 10);
    if (!empty($detail)) {
        if (preg_match('/Raid Level\s+:\s+(.+)/', $detail, $m)) $info['raid_level'] = trim($m[1]);
        if (preg_match('/Array Size\s+:\s+(.+)/', $detail, $m)) $info['array_size'] = trim($m[1]);
        if (preg_match('/State\s+:\s+(.+)/', $detail, $m)) $info['state'] = trim($m[1]);
        if (preg_match('/Active Devices\s+:\s+(\d+)/', $detail, $m)) $info['active_devices'] = intval($m[1]);
        if (preg_match('/Working Devices\s+:\s+(\d+)/', $detail, $m)) $info['working_devices'] = intval($m[1]);
        if (preg_match('/Failed Devices\s+:\s+(\d+)/', $detail, $m)) $info['failed_devices'] = intval($m[1]);
        if (preg_match('/Spare Devices\s+:\s+(\d+)/', $detail, $m)) $info['spare_devices'] = intval($m[1]);
    }
    
    $info['components'] = getRaidComponents($raidName);
    $info['partitions'] = getRaidPartitions($raidName);
    
    return $info;
}

// ==================== ФУНКЦИЯ СОЗДАНИЯ РАЗДЕЛОВ ====================
function getPartitionTableTypeForDisk($disk) {
    $output = execCmd("parted /dev/{$disk} print 2>/dev/null | grep 'Partition Table' | awk '{print $3}'", true, 5);
    return strtolower(trim($output));
}

function getNextPartitionNumber($disk) {
    $output = execCmd("lsblk -n -o NAME /dev/{$disk} 2>/dev/null | grep -v '^'{$disk}'$'", true, 5);
    $numbers = [];
    
    if (!empty($output)) {
        $lines = explode("\n", trim($output));
        foreach ($lines as $line) {
            $line = trim($line);
            if (!empty($line) && $line !== $disk) {
                if (preg_match('/p(\d+)$/', $line, $matches)) {
                    $numbers[] = (int)$matches[1];
                } elseif (preg_match('/(\d+)$/', $line, $matches)) {
                    $numbers[] = (int)$matches[0];
                }
            }
        }
    }
    
    if (empty($numbers)) {
        return 1;
    }
    
    sort($numbers);
    return end($numbers) + 1;
}

function getFreeSpaceStart($disk) {
    $output = execCmd("parted /dev/{$disk} unit B print free 2>/dev/null | grep 'Free Space' | tail -1", true, 10);
    
    if (empty($output)) {
        return '0%';
    }
    
    if (preg_match('/(\d+)B\s+(\d+)B/', $output, $matches)) {
        $start = (int)$matches[1];
        $end = (int)$matches[2];
        if ($end > $start) {
            return $start . 'B';
        }
    }
    
    return '0%';
}

function createPartition($disk, $size, $fsType = null, $label = '', $format = false, $quickFormat = true, $partitionType = 'primary') {
    writeLog("=== CREATE PARTITION START ===");
    writeLog("Parameters: disk={$disk}, size={" . ($size === '' || $size === null ? 'EMPTY' : $size) . "}, fsType={$fsType}, format=" . ($format ? 'true' : 'false'));
    
    // ==================== ПРОВЕРКА СУЩЕСТВОВАНИЯ ДИСКА ====================
    $diskPath = "/dev/{$disk}";
    if (!file_exists($diskPath)) {
        $error = "Disk {$diskPath} does not exist";
        writeError($error);
        return ['success' => false, 'error' => $error];
    }
    
    // ==================== ОПРЕДЕЛЕНИЕ ТИПА ТАБЛИЦЫ РАЗДЕЛОВ ====================
    $tableType = getPartitionTableTypeForDisk($disk);
    writeLog("Current partition table type: " . ($tableType ?: 'none'));
    
    if (!$tableType) {
        writeLog("No partition table found, creating GPT");
        $initResult = execCmd("parted -s {$diskPath} mklabel gpt 2>&1", true, 20);
        if (strpos($initResult, 'Error') !== false) {
            $initResult = execCmd("parted -s {$diskPath} mklabel msdos 2>&1", true, 20);
            if (strpos($initResult, 'Error') !== false) {
                return ['success' => false, 'error' => "Failed to create partition table: {$initResult}"];
            }
            $tableType = 'msdos';
            writeLog("Created MBR (msdos) partition table");
        } else {
            $tableType = 'gpt';
            writeLog("Created GPT partition table");
        }
        sleep(1);
    }
    
    $isGpt = ($tableType === 'gpt');
    $isMbr = ($tableType === 'msdos');
    
    // ==================== ПОЛУЧЕНИЕ ИНФОРМАЦИИ О ДИСКЕ ====================
    $totalBytes = (int)trim(execCmd("blockdev --getsize64 {$diskPath} 2>/dev/null", true, 5));
    if ($totalBytes <= 0) {
        $totalBytes = (int)trim(execCmd("parted {$diskPath} unit B print 2>/dev/null | grep 'Disk {$diskPath}' | awk '{print $3}' | tr -d 'B'", true, 5));
    }
    writeLog("Total disk size: {$totalBytes} bytes (" . round($totalBytes/1024/1024/1024, 2) . " GB)");
    
    $existingPartsRaw = getPartitions($disk);
    $existingParts = [];
    $seenNames = [];
    foreach ($existingPartsRaw as $part) {
        if (!in_array($part['name'], $seenNames)) {
            $seenNames[] = $part['name'];
            $existingParts[] = $part;
        }
    }
    writeLog("Existing partitions count: " . count($existingParts));
    
    // ==================== ВЫЧИСЛЕНИЕ СВОБОДНОГО МЕСТА ====================
    $usedBytes = 0;
    foreach ($existingParts as $part) {
        $usedBytes += $part['size_bytes'];
    }
    $freeBytes = $totalBytes - $usedBytes;
    $freeGb = round($freeBytes / 1024 / 1024 / 1024, 2);
    
    writeLog("Calculated free space: {$freeGb} GB ({$freeBytes} bytes)");
    
    if ($freeBytes <= 0) {
        return ['success' => false, 'error' => 'Нет свободного места на диске для создания нового раздела'];
    }
    
    // ==================== ОПРЕДЕЛЕНИЕ НАЧАЛЬНОЙ ПОЗИЦИИ ====================
    $partedOutput = execCmd("parted {$diskPath} unit B print free 2>/dev/null", true, 10);
    writeLog("Parted output for free space:\n{$partedOutput}");
    
    $freeSpaceStart = 0;
    $freeSpaceEnd = 0;
    $lastEndByte = 0;
    
    $lines = explode("\n", $partedOutput);
    $inTable = false;
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (strpos($line, 'Number') !== false && strpos($line, 'Start') !== false) {
            $inTable = true;
            continue;
        }
        if (!$inTable || empty($line)) continue;
        if (strpos($line, 'Disk') === 0) continue;
        if (strpos($line, 'Partition Table') !== false) continue;
        
        if (preg_match('/^\s*(\d+)\s+(\d+)B\s+(\d+)B/', $line, $matches) && !strpos($line, 'Free Space')) {
            $endByte = (int)$matches[3];
            if ($endByte > $lastEndByte) {
                $lastEndByte = $endByte;
            }
        }
        
        if (strpos($line, 'Free Space') !== false) {
            if (preg_match('/(\d+)B\s+(\d+)B/', $line, $matches)) {
                $freeSpaceStart = (int)$matches[1];
                $freeSpaceEnd = (int)$matches[2];
                writeLog("Found free space: {$freeSpaceStart}B - {$freeSpaceEnd}B, size: " . round(($freeSpaceEnd - $freeSpaceStart)/1024/1024/1024, 2) . " GB");
            }
        }
    }
    
    $startByte = 1048576;
    
    if ($freeSpaceStart > 0) {
        $startByte = $freeSpaceStart;
        writeLog("Using free space start from parted: {$startByte} bytes");
    } elseif ($lastEndByte > 0) {
        $startByte = $lastEndByte + 1048576;
        writeLog("Calculated start from last partition end: {$startByte} bytes");
    } else {
        writeLog("Using default start: {$startByte} bytes (1MB)");
    }
    
    $startPos = $startByte . 'B';
    
    // ==================== ОПРЕДЕЛЕНИЕ КОНЕЧНОЙ ПОЗИЦИИ ====================
    $useAllSpace = false;
    $endByte = 0;
    
    $isEmptySize = false;
    
    if ($size === null || $size === '' || trim($size) === '' || 
        $size === '0' || $size === 0 || $size === '0.0' ||
        strtolower($size) === 'all' || strtolower($size) === '100%') {
        $isEmptySize = true;
        writeLog("EMPTY SIZE DETECTED - will use all free space");
    }
    
    if ($isEmptySize) {
        if ($freeSpaceEnd > 0 && $freeSpaceEnd > $startByte) {
            $endByte = $freeSpaceEnd;
        } else {
            $endByte = $totalBytes;
        }
        $useAllSpace = true;
        $partitionSizeBytes = $endByte - $startByte;
        $partitionSizeGb = round($partitionSizeBytes / 1024 / 1024 / 1024, 2);
        writeLog("Using ALL free space: {$partitionSizeGb} GB (start={$startByte}, end={$endByte})");
    } elseif (strpos($size, '%') !== false) {
        $percent = (float)$size / 100;
        $sizeBytes = (int)($freeBytes * $percent);
        $endByte = $startByte + $sizeBytes;
        $partitionSizeGb = round($sizeBytes / 1024 / 1024 / 1024, 2);
        writeLog("Using {$size} of free space: {$partitionSizeGb} GB");
    } elseif (is_numeric($size)) {
        $sizeGb = (float)$size;
        $sizeBytes = (int)($sizeGb * 1024 * 1024 * 1024);
        
        if ($sizeBytes > $freeBytes) {
            return ['success' => false, 'error' => "Запрошенный размер {$sizeGb} GB превышает доступное свободное место {$freeGb} GB"];
        }
        
        $endByte = $startByte + $sizeBytes;
        $partitionSizeGb = $sizeGb;
        writeLog("Requested size: {$sizeGb} GB ({$sizeBytes} bytes)");
    } else {
        $sizeBytes = parseSizeToBytes($size);
        if ($sizeBytes > 0) {
            if ($sizeBytes > $freeBytes) {
                return ['success' => false, 'error' => "Запрошенный размер превышает доступное свободное место"];
            }
            $endByte = $startByte + $sizeBytes;
            $partitionSizeGb = round($sizeBytes / 1024 / 1024 / 1024, 2);
            writeLog("Parsed size: {$size} -> {$sizeBytes} bytes ({$partitionSizeGb} GB)");
        } else {
            if ($freeSpaceEnd > 0 && $freeSpaceEnd > $startByte) {
                $endByte = $freeSpaceEnd;
            } else {
                $endByte = $totalBytes;
            }
            $useAllSpace = true;
            $partitionSizeBytes = $endByte - $startByte;
            $partitionSizeGb = round($partitionSizeBytes / 1024 / 1024 / 1024, 2);
            writeLog("Could not parse size, using all free space: {$partitionSizeGb} GB");
        }
    }
    
    if ($endByte > $totalBytes) {
        $endByte = $totalBytes;
        writeLog("End position exceeds disk size, adjusting to {$endByte}");
    }
    
    if ($endByte <= $startByte) {
        $error = "Invalid partition size: end ({$endByte}) <= start ({$startByte})";
        writeError($error);
        return ['success' => false, 'error' => $error];
    }
    
    $endPos = $endByte . 'B';
    $partitionSizeBytes = $endByte - $startByte;
    $partitionSizeGb = round($partitionSizeBytes / 1024 / 1024 / 1024, 2);
    
    writeLog("Creating partition: start={$startPos}, end={$endPos}, size={$partitionSizeGb} GB");
    
    // ==================== ОПРЕДЕЛЕНИЕ ТИПА РАЗДЕЛА ДЛЯ PARTED ====================
    $partedPartType = $partitionType;
    if ($isGpt) {
        $partedPartType = 'primary';
    } elseif ($isMbr) {
        $primaryCount = 0;
        foreach ($existingParts as $part) {
            if (strpos($part['name'], 'extended') === false && !preg_match('/^.*(logical|extended).*/', $part['name'])) {
                $primaryCount++;
            }
        }
        if ($primaryCount >= 4) {
            $partedPartType = 'logical';
            writeLog("MBR with 4 primary partitions, using logical");
        }
    }
    
    writeLog("Parted partition type: {$partedPartType}");
    
    // ==================== ВЫПОЛНЕНИЕ КОМАНДЫ PARTED ====================
    $cmd = "parted -s {$diskPath} unit B mkpart {$partedPartType} {$startPos} {$endPos} 2>&1";
    writeLog("Executing: {$cmd}");
    $output = execCmd($cmd, true, 30);
    
    if (strpos($output, 'Error') !== false || strpos($output, 'error') !== false) {
        $startPercent = round(($startByte / $totalBytes) * 100, 2);
        $endPercent = round(($endByte / $totalBytes) * 100, 2);
        $percentCmd = "parted -s {$diskPath} mkpart {$partedPartType} {$startPercent}% {$endPercent}% 2>&1";
        writeLog("Retry with percentages: {$percentCmd}");
        $output = execCmd($percentCmd, true, 30);
        
        if (strpos($output, 'Error') !== false && strpos($output, 'error') !== false) {
            writeError("Partition creation failed: {$output}");
            return ['success' => false, 'error' => $output];
        }
    }
    
    // ==================== ОБНОВЛЕНИЕ ТАБЛИЦЫ РАЗДЕЛОВ ====================
    execCmd("partprobe {$diskPath}", true, 5);
    execCmd("blockdev --rereadpt {$diskPath} 2>/dev/null", true, 5);
    execCmd("udevadm settle", true, 5);
    sleep(2);
    
    // ==================== ОПРЕДЕЛЕНИЕ ИМЕНИ НОВОГО РАЗДЕЛА ====================
    $newPartitionName = '';
    $existingNames = array_map(function($p) { return $p['name']; }, $existingParts);
    
    for ($attempt = 1; $attempt <= 20; $attempt++) {
        $currentParts = getPartitions($disk);
        foreach ($currentParts as $part) {
            if (!in_array($part['name'], $existingNames)) {
                $newPartitionName = $part['name'];
                writeLog("Found new partition at attempt {$attempt}: {$newPartitionName}");
                break 2;
            }
        }
        
        $expectedNum = count($existingParts) + 1;
        if (strpos($disk, 'nvme') !== false) {
            $expectedName = $disk . 'p' . $expectedNum;
        } else {
            $expectedName = $disk . $expectedNum;
        }
        
        if (file_exists("/dev/{$expectedName}") && !in_array($expectedName, $existingNames)) {
            $newPartitionName = $expectedName;
            writeLog("Found by expected name: {$newPartitionName}");
            break;
        }
        
        if ($attempt % 5 == 0) {
            execCmd("partprobe {$diskPath}", true, 5);
            execCmd("udevadm settle", true, 5);
        }
        sleep(1);
    }
    
    if (empty($newPartitionName)) {
        $expectedName = (strpos($disk, 'nvme') !== false) ? $disk . 'p' . (count($existingParts) + 1) : $disk . (count($existingParts) + 1);
        writeError("Could not find new partition, expected: {$expectedName}");
        return [
            'success' => true,
            'partition' => $expectedName,
            'warning' => "Раздел создан, но устройство не обнаружено. Выполните: partprobe {$diskPath}",
            'size_gb' => $partitionSizeGb
        ];
    }
    
    for ($i = 0; $i < 10; $i++) {
        if (file_exists("/dev/{$newPartitionName}")) {
            break;
        }
        sleep(1);
    }
    
    if (!file_exists("/dev/{$newPartitionName}")) {
        return [
            'success' => false,
            'error' => "Раздел создан, но устройство /dev/{$newPartitionName} не найдено"
        ];
    }
    
    writeLog("Partition successfully created: /dev/{$newPartitionName}");
    
    // ==================== ФОРМАТИРОВАНИЕ ====================
    if ($format === true && $fsType !== null && $fsType !== 'none' && $fsType !== '') {
        writeLog("Formatting partition {$newPartitionName} with {$fsType}");
        
        $formatResult = formatPartition($newPartitionName, $fsType, $label, $quickFormat);
        if (!$formatResult['success']) {
            return [
                'success' => false,
                'error' => 'Раздел создан, но форматирование не удалось: ' . $formatResult['error'],
                'partition' => $newPartitionName
            ];
        }
    }
    
    $actualSizeBytes = (int)trim(execCmd("blockdev --getsize64 /dev/{$newPartitionName} 2>/dev/null", true, 5));
    $actualSizeGb = round($actualSizeBytes / 1024 / 1024 / 1024, 2);
    
    writeLog("=== CREATE PARTITION END: success, partition={$newPartitionName}, size={$actualSizeGb}GB ===");
    
    return [
        'success' => true,
        'partition' => $newPartitionName,
        'used_all_space' => $useAllSpace,
        'size_gb' => $actualSizeGb
    ];
}

function getFreeSpaceStartForDisk($disk, $existingParts) {
    $diskPath = "/dev/{$disk}";
    
    if (empty($existingParts)) {
        return '1MiB';
    }
    
    $output = execCmd("parted {$diskPath} unit B print free 2>/dev/null", true, 10);
    writeLog("Parted free output for {$disk}:\n{$output}");
    
    $lines = explode("\n", $output);
    $lastFreeStart = null;
    $lastFreeEnd = null;
    
    foreach ($lines as $line) {
        $line = trim($line);
        
        if (strpos($line, 'Free Space') !== false) {
            if (preg_match('/(\d+)B\s+(\d+)B\s+\d+B\s+Free Space/', $line, $matches)) {
                $start = (int)$matches[1];
                $end = (int)$matches[2];
                
                writeLog("Found free space: start={$start}B, end={$end}B");
                
                $lastFreeStart = $start;
                $lastFreeEnd = $end;
            }
        }
    }
    
    if ($lastFreeStart !== null && $lastFreeStart > 0) {
        $startMiB = ceil($lastFreeStart / 1024 / 1024);
        $result = max(1, $startMiB) . 'MiB';
        writeLog("Using free space start: {$result} (from parted free output)");
        return $result;
    }
    
    $lastPart = end($existingParts);
    $partInfo = execCmd("parted {$diskPath} unit B print 2>/dev/null | grep '{$lastPart['name']}\\s'", true, 10);
    
    if (preg_match('/(\d+)B\s+(\d+)B/', $partInfo, $matches)) {
        $endBytes = (int)$matches[2];
        $startBytes = $endBytes + 1;
        $startMiB = ceil($startBytes / 1024 / 1024);
        $result = max(1, $startMiB) . 'MiB';
        writeLog("Calculated from last partition end: {$result} (end was {$endBytes}B)");
        return $result;
    }
    
    writeLog("Using default start: 1MiB");
    return '1MiB';
}


function getNewPartitionName($disk, $expectedNumber) {
    $maxAttempts = 10;
    $attempt = 0;
    
    while ($attempt < $maxAttempts) {
        $afterParts = getPartitionNumbers($disk);
        
        if (in_array($expectedNumber, $afterParts)) {
            if (strpos($disk, 'nvme') !== false) {
                return $disk . 'p' . $expectedNumber;
            } else {
                return $disk . $expectedNumber;
            }
        }
        
        foreach ($afterParts as $num) {
            if (strpos($disk, 'nvme') !== false) {
                $candidate = $disk . 'p' . $num;
            } else {
                $candidate = $disk . $num;
            }
            
            $checkExists = execCmd("lsblk /dev/{$candidate} 2>/dev/null", true, 3);
            if (!empty($checkExists)) {
                writeLog("Found partition {$candidate} after {$attempt} attempts");
                return $candidate;
            }
        }
        
        $attempt++;
        sleep(1);
    }
    
    if (strpos($disk, 'nvme') !== false) {
        return $disk . 'p' . $expectedNumber;
    } else {
        return $disk . $expectedNumber;
    }
}

function getPartitionNumbers($disk) {
    $output = execCmd("lsblk -n -o NAME /dev/{$disk} 2>/dev/null | grep -v '^'{$disk}'$'", true, 5);
    $numbers = [];
    
    if (!empty($output)) {
        $lines = explode("\n", trim($output));
        foreach ($lines as $line) {
            $line = trim($line);
            if (!empty($line) && $line !== $disk) {
                if (preg_match('/p(\d+)$/', $line, $matches)) {
                    $numbers[] = (int)$matches[1];
                } elseif (preg_match('/(\d+)$/', $line, $matches)) {
                    $numbers[] = (int)$matches[0];
                }
            }
        }
    }
    
    sort($numbers);
    return $numbers;
}

function formatPartition($partition, $fsType, $label = '', $quickFormat = true) {
    writeLog("Formatting partition: {$partition}, fsType: {$fsType}, label: {$label}, quickFormat: " . ($quickFormat ? 'true' : 'false'));
    
    $partCheck = execCmd("lsblk /dev/{$partition} 2>/dev/null", true, 3);
    if (empty($partCheck)) {
        $error = "Partition {$partition} does not exist";
        writeError($error);
        return ['success' => false, 'error' => $error];
    }
    
    $mountCheck = execCmd("mount | grep '/dev/{$partition} '", true, 3);
    if (!empty($mountCheck)) {
        writeLog("Unmounting {$partition} before format");
        execCmd("umount /dev/{$partition} 2>/dev/null", true, 5);
        sleep(1);
    }
    
    if ($fsType === 'ntfs') {
        execCmd("ntfsfix /dev/{$partition} 2>/dev/null", true, 10);
    }
    
    if (!$quickFormat && $fsType !== 'swap') {
        writeLog("Performing full format - wiping first 10MB of partition");
        execCmd("dd if=/dev/zero of=/dev/{$partition} bs=1M count=10 2>/dev/null", true, 30);
        sleep(1);
    }
    
    $cmd = '';
    switch ($fsType) {
        case 'ext4': 
            $cmd = "mkfs.ext4 -F";
            break;
        case 'ext3': 
            $cmd = "mkfs.ext3 -F";
            break;
        case 'ext2': 
            $cmd = "mkfs.ext2 -F";
            break;
        case 'ntfs': 
            $cmd = $quickFormat ? "mkfs.ntfs -Q -F" : "mkfs.ntfs -F";
            break;
        case 'fat32': 
            $cmd = "mkfs.vfat -F32";
            break;
        case 'exfat': 
            $cmd = "mkfs.exfat";
            break;
        case 'xfs': 
            $cmd = "mkfs.xfs -f";
            break;
        case 'btrfs': 
            $cmd = "mkfs.btrfs -f";
            break;
        case 'swap': 
            $cmd = "mkswap";
            break;
        default: 
            writeError("Unknown filesystem type: {$fsType}, using ext4");
            $cmd = "mkfs.ext4 -F";
            $fsType = 'ext4';
    }
    
    if (!empty($label) && $fsType !== 'swap') {
        $cleanLabel = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $label);
        $cleanLabel = substr($cleanLabel, 0, 16);
        if (!empty($cleanLabel)) {
            $cmd .= " -L \"" . addslashes($cleanLabel) . "\"";
            writeLog("Adding label: {$cleanLabel}");
        }
    }
    
    $cmd .= " /dev/{$partition} 2>&1";
    writeLog("Executing format command: {$cmd}");
    
    $timeout = ($fsType === 'ntfs' && !$quickFormat) ? 300 : 120;
    $output = execCmd($cmd, true, $timeout);
    
    $success = true;
    $errorMsg = '';
    
    if (strpos($output, 'failed') !== false || 
        strpos($output, 'error') !== false ||
        strpos($output, 'ERROR') !== false ||
        strpos($output, 'Could not stat') !== false ||
        strpos($output, 'not found') !== false ||
        strpos($output, 'No such file') !== false) {
        $success = false;
        $errorMsg = $output;
        writeError("Format failed: {$output}");
    }
    
    if ($success && $fsType === 'ntfs') {
        $check = execCmd("ntfsinfo /dev/{$partition} 2>&1 | head -1", true, 10);
        if (strpos($check, 'ERROR') !== false) {
            $success = false;
            $errorMsg = "NTFS creation verification failed: {$check}";
            writeError($errorMsg);
        }
    }
    
    if ($success && ($fsType === 'ext4' || $fsType === 'ext3' || $fsType === 'ext2')) {
        $check = execCmd("dumpe2fs /dev/{$partition} 2>&1 | head -1", true, 10);
        if (strpos($check, 'Bad magic number') !== false) {
            $success = false;
            $errorMsg = "EXT filesystem creation verification failed";
            writeError($errorMsg);
        }
    }
    
    if ($success) {
        execCmd("partprobe /dev/" . preg_replace('/[0-9]+$/', '', $partition) . " 2>/dev/null", true, 5);
        execCmd("blockdev --rereadpt /dev/" . preg_replace('/[0-9]+$/', '', $partition) . " 2>/dev/null", true, 5);
        
        sleep(1);
        $actualFs = trim(execCmd("blkid -s TYPE -o value /dev/{$partition} 2>/dev/null", true, 5));
        writeLog("Format completed. Requested: {$fsType}, Actual: " . ($actualFs ?: 'unknown'));
        
        if (empty($actualFs)) {
            writeError("Warning: Filesystem type could not be detected after formatting");
        } elseif ($actualFs !== $fsType && $fsType !== 'swap') {
            writeError("Warning: Filesystem mismatch! Requested {$fsType}, but got {$actualFs}");
        }
        
        return ['success' => true, 'actual_fs' => $actualFs];
    } else {
        return ['success' => false, 'error' => $errorMsg];
    }
}

function deletePartition($partition) {
    writeLog("Deleting partition: {$partition}");
    
    if (preg_match('/(nvme\d+n\d+)p(\d+)/', $partition, $matches)) {
        $disk = $matches[1];
        $num = $matches[2];
    } elseif (preg_match('/(sd[a-z]+)(\d+)/', $partition, $matches)) {
        $disk = $matches[1];
        $num = $matches[2];
    } else {
        $error = "Cannot parse partition name: {$partition}";
        writeError($error);
        return ['success' => false, 'error' => $error];
    }
    
    removeFromFstab($partition);
    
    $mountCheck = execCmd("mount | grep '/dev/{$partition} '", true, 3);
    if (!empty($mountCheck)) {
        execCmd("umount /dev/{$partition} 2>/dev/null", true, 5);
        sleep(1);
    }
    
    $cmd = "parted -s /dev/{$disk} rm {$num} 2>&1";
    $output = execCmd($cmd, true, 20);
    execCmd("partprobe /dev/{$disk}", true, 5);
    
    $success = (strpos($output, 'Error') === false && strpos($output, 'error') === false);
    
    if ($success) {
        writeLog("Partition {$partition} deleted successfully");
    } else {
        writeError("Delete partition failed: {$output}", ['partition' => $partition]);
    }
    
    return ['success' => $success, 'error' => $success ? '' : $output];
}

function safeRemoveDisk($disk) {
    writeLog("Safe removing disk: {$disk}");
    
    $errors = [];
    $success = true;
    
    $partitions = getPartitions($disk);
    
    foreach ($partitions as $part) {
        if ($part['mount_point']) {
            $result = umountDevice($part['name'], false, false);
            if (!$result['success']) {
                $result = umountDevice($part['name'], true, false);
                if (!$result['success']) {
                    $errors[] = "Не удалось размонтировать {$part['name']}";
                    $success = false;
                }
            }
        }
    }
    
    if ($success) {
        execCmd("udisksctl power-off -b /dev/{$disk} 2>/dev/null", true, 10);
        execCmd("echo 1 > /sys/block/{$disk}/device/delete 2>/dev/null", false, 5);
        writeLog("Disk {$disk} powered off");
    } else {
        writeError("Safe remove failed for {$disk}", ['errors' => $errors]);
    }
    
    return ['success' => $success, 'errors' => $errors];
}

function mountFstabEntry($mountPoint) {
    $entries = getFstabEntries();
    $entry = null;
    foreach ($entries as $e) {
        if ($e['mount_point'] === $mountPoint) {
            $entry = $e;
            break;
        }
    }
    
    if (!$entry) {
        return ['success' => false, 'error' => "Entry not found for {$mountPoint}"];
    }
    
    $mountCheck = execCmd("mount | grep '{$mountPoint} '", true, 3);
    if (!empty($mountCheck)) {
        return ['success' => false, 'error' => "Already mounted at {$mountPoint}"];
    }
    
    $cmd = "mount {$mountPoint} 2>&1";
    $output = execCmd($cmd, true, 10);
    
    $mountCheck = execCmd("mount | grep '{$mountPoint} '", true, 3);
    if (empty($mountCheck)) {
        return ['success' => false, 'error' => "Mount failed: " . ($output ?: 'Unknown error')];
    }
    
    return ['success' => true, 'message' => "Mounted {$mountPoint}"];
}

function mountAllFstab() {
    $entries = getFstabEntries();
    $mounted = 0;
    $errors = 0;
    $errorMessages = [];
    
    foreach ($entries as $entry) {
        $mountPoint = $entry['mount_point'];
        
        if ($entry['fstype'] === 'swap') continue;
        
        $mountCheck = execCmd("mount | grep '{$mountPoint} '", true, 3);
        if (!empty($mountCheck)) continue;
        
        $cmd = "mount {$mountPoint} 2>&1";
        $output = execCmd($cmd, true, 10);
        
        $mountCheck = execCmd("mount | grep '{$mountPoint} '", true, 3);
        if (empty($mountCheck)) {
            $errors++;
            $errorMessages[] = "{$mountPoint}: " . ($output ?: 'Unknown error');
        } else {
            $mounted++;
        }
    }
    
    return [
        'success' => true,
        'mounted' => $mounted,
        'errors' => $errors,
        'error_messages' => $errorMessages
    ];
}

function getFstabEntries() {
    $entries = [];
    $fstab = file_get_contents('/etc/fstab');
    if ($fstab === false) return $entries;
    
    $lines = explode("\n", $fstab);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) continue;
        
        $parts = preg_split('/\s+/', $line);
        if (count($parts) >= 3) {
            $device = $parts[0];
            $mountPoint = $parts[1];
            $fstype = $parts[2];
            $options = $parts[3] ?? 'defaults';
            
            $isUuid = strpos($device, 'UUID=') === 0;
            $uuid = null;
            $devName = null;
            
            if ($isUuid) {
                $uuid = str_replace('UUID=', '', $device);
                $devName = execCmd("blkid -U {$uuid} 2>/dev/null", true, 3);
                $devName = trim($devName);
            } else {
                $devName = $device;
                $uuid = execCmd("blkid -s UUID -o value {$device} 2>/dev/null", true, 3);
                $uuid = trim($uuid);
            }
            
            $entries[] = [
                'device' => $device,
                'device_name' => $devName,
                'uuid' => $uuid,
                'is_uuid' => $isUuid,
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

function checkPartitionInFstab($partition) {
    $devicePath = "/dev/{$partition}";
    $uuid = trim(execCmd("blkid -s UUID -o value {$devicePath} 2>/dev/null", true, 5));
    
    $fstab = file_get_contents('/etc/fstab');
    if ($fstab === false) {
        return ['in_fstab' => false];
    }
    
    $inFstab = false;
    $fstabEntry = null;
    $lines = explode("\n", $fstab);
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || $line[0] === '#') continue;
        
        if (strpos($line, $uuid) !== false || strpos($line, $devicePath) !== false) {
            $inFstab = true;
            $fstabEntry = $line;
            break;
        }
    }
    
    return [
        'in_fstab' => $inFstab,
        'fstab_entry' => $fstabEntry,
        'uuid' => $uuid,
        'device_path' => $devicePath
    ];
}

function smartUmount($device, $autoRemoveFromFstab = true) {
    writeLog("Smart unmounting device: {$device}, autoRemoveFromFstab: " . ($autoRemoveFromFstab ? 'true' : 'false'));
    
    $device = preg_replace('/^\/dev\//', '', $device);
    $devicePath = "/dev/{$device}";
    
    $mountStatus = getMountStatus($device);
    if (!$mountStatus['mounted']) {
        return ['success' => false, 'error' => 'Устройство не смонтировано'];
    }
    
    $mountPoint = $mountStatus['mount_point'];
    
    $fstabCheck = checkPartitionInFstab($device);
    $wasInFstab = $fstabCheck['in_fstab'];
    
    if ($wasInFstab && $autoRemoveFromFstab) {
        writeLog("Removing {$device} from fstab before unmount");
        if (!empty($fstabCheck['uuid'])) {
            removeFromFstabByUuid($fstabCheck['uuid']);
        } else {
            removeFromFstab($device);
        }
        removeFromFstabByMountPoint($mountPoint);
    }
    
    $result = umountDevice($device, false, false);
    
    if ($result['success']) {
        $message = "Устройство {$device} размонтировано";
        if ($wasInFstab && $autoRemoveFromFstab) {
            $message .= " и удалено из /etc/fstab";
        } elseif ($wasInFstab && !$autoRemoveFromFstab) {
            $message .= " (оставлено в fstab)";
        }
        $result['message'] = $message;
        $result['was_in_fstab'] = $wasInFstab;
        $result['removed_from_fstab'] = ($wasInFstab && $autoRemoveFromFstab);
    }
    
    return $result;
}

function getPartitionMaxSize($partition) {
    
    $disk = '';
    $num = '';
    
    if (preg_match('/^(md\d+)p(\d+)$/i', $partition, $matches)) {
        $disk = $matches[1];
        $num = $matches[2];
        writeLog("getPartitionMaxSize: detected RAID partition - disk={$disk}, num={$num}");
    }
    elseif (preg_match('/(nvme\d+n\d+)p(\d+)/', $partition, $matches)) {
        $disk = $matches[1];
        $num = $matches[2];
    }
    elseif (preg_match('/(sd[a-z]+)(\d+)/', $partition, $matches)) {
        $disk = $matches[1];
        $num = $matches[2];
    }
    else {
        writeError("getPartitionMaxSize: Cannot parse partition name: {$partition}");
        return ['success' => false, 'error' => 'Cannot parse partition name: ' . $partition];
    }
    
    $diskInfo = getAllDisksWithVirtual();
    $diskData = null;
    foreach ($diskInfo as $d) {
        if ($d['name'] === $disk) {
            $diskData = $d;
            break;
        }
    }
    
    if (!$diskData) {
        return ['success' => false, 'error' => 'Disk/RAID not found: ' . $disk];
    }
    
    $currentPartition = null;
    $usedByOthers = 0;
    foreach ($diskData['partitions'] as $part) {
        if ($part['name'] === $partition) {
            $currentPartition = $part;
        } else {
            $usedByOthers += $part['size_bytes'];
        }
    }
    
    if (!$currentPartition) {
        return ['success' => false, 'error' => 'Partition not found: ' . $partition];
    }
    
    $maxSizeBytes = $diskData['size_bytes'] - $usedByOthers;
    $currentSizeBytes = $currentPartition['size_bytes'];
    $freeSpaceBytes = $maxSizeBytes - $currentSizeBytes;
    
    return [
        'success' => true,
        'current_size_gb' => round($currentSizeBytes / 1024 / 1024 / 1024, 2),
        'max_size_gb' => round($maxSizeBytes / 1024 / 1024 / 1024, 2),
        'free_space_gb' => round($freeSpaceBytes / 1024 / 1024 / 1024, 2),
        'current_size_bytes' => $currentSizeBytes,
        'max_size_bytes' => $maxSizeBytes,
        'free_space_bytes' => $freeSpaceBytes,
        'disk_total_gb' => round($diskData['size_bytes'] / 1024 / 1024 / 1024, 2)
    ];
}

function parseRaidPartitionName($partition) {
    $result = [
        'raid_name' => null, 
        'partition_num' => null, 
        'is_raid' => false,
        'is_array' => false,
        'full_name' => $partition
    ];
    
    $clean = preg_replace('#^/dev/#', '', $partition);
    
    if (preg_match('/^(md\d+)p(\d+)$/i', $clean, $matches)) {
        $result['raid_name'] = $matches[1];
        $result['partition_num'] = (int)$matches[2];
        $result['is_raid'] = true;
        $result['is_array'] = false;
    }
    elseif (preg_match('/^(md\d+)$/i', $clean, $matches)) {
        $result['raid_name'] = $matches[1];
        $result['partition_num'] = null;
        $result['is_raid'] = true;
        $result['is_array'] = true;
    }
    elseif (preg_match('/^(md\d+)(\d+)$/i', $clean, $matches)) {
        $result['raid_name'] = $matches[1];
        $result['partition_num'] = (int)$matches[2];
        $result['is_raid'] = true;
        $result['is_array'] = false;
    }
    
    writeLog("parseRaidPartitionName('{$partition}') -> " . json_encode($result));
    
    return $result;
}

function resizePartition($partition, $newSizeGb) {
    writeLog("Resizing partition: {$partition} to {$newSizeGb}GB");
    
    if ($newSizeGb <= 0) {
        return ['success' => false, 'error' => 'Invalid size'];
    }
    
    if (preg_match('/(nvme\d+n\d+)p(\d+)/', $partition, $matches)) {
        $disk = $matches[1];
        $num = $matches[2];
    } elseif (preg_match('/(sd[a-z]+)(\d+)/', $partition, $matches)) {
        $disk = $matches[1];
        $num = $matches[2];
    } else {
        return ['success' => false, 'error' => 'Cannot parse partition name'];
    }
    
    $currentSize = execCmd("lsblk -b -o SIZE /dev/{$partition} 2>/dev/null | tail -1", true, 5);
    $currentSizeGb = round((float)$currentSize / 1024 / 1024 / 1024, 1);
    
    $mountCheck = execCmd("mount | grep '/dev/{$partition} '", true, 3);
    $wasMounted = !empty($mountCheck);
    $mountPoint = null;
    
    if ($wasMounted) {
        $mountPoint = execCmd("mount | grep '/dev/{$partition} ' | awk '{print $3}'", true, 3);
        execCmd("umount /dev/{$partition} 2>/dev/null", true, 5);
        sleep(1);
    }
    
    $fs = trim(execCmd("blkid -s TYPE -o value /dev/{$partition} 2>/dev/null", true, 5));
    
    if ($fs === 'ext4' || $fs === 'ext3' || $fs === 'ext2') {
        execCmd("e2fsck -f -y /dev/{$partition} 2>/dev/null", true, 120);
    }
    
    $cmd = "parted -s /dev/{$disk} resizepart {$num} {$newSizeGb}GB 2>&1";
    $output = execCmd($cmd, true, 20);
    
    if (strpos($output, 'Error') !== false || strpos($output, 'error') !== false) {
        if ($wasMounted && $mountPoint) {
            execCmd("mount /dev/{$partition} \"{$mountPoint}\" 2>/dev/null", true, 10);
        }
        writeError("Resize partition failed: {$output}", ['partition' => $partition]);
        return ['success' => false, 'error' => $output];
    }
    
    if ($fs === 'ext4' || $fs === 'ext3' || $fs === 'ext2') {
        execCmd("resize2fs /dev/{$partition} 2>/dev/null", true, 120);
    } elseif ($fs === 'xfs') {
        execCmd("xfs_growfs /dev/{$partition} 2>/dev/null", true, 30);
    } elseif ($fs === 'ntfs') {
        execCmd("ntfsresize -f /dev/{$partition} 2>/dev/null", true, 120);
    }
    
    execCmd("partprobe /dev/{$disk}", true, 5);
    
    if ($wasMounted && $mountPoint) {
        execCmd("mount /dev/{$partition} \"{$mountPoint}\" 2>/dev/null", true, 10);
    }
    
    writeLog("Partition {$partition} resized from {$currentSizeGb}GB to {$newSizeGb}GB");
    return ['success' => true, 'old_size_gb' => $currentSizeGb, 'new_size_gb' => $newSizeGb];
}

function getDiskInfo($disk) {
    $info = [];
    
    $info['name'] = $disk;
    $info['model'] = execCmd("cat /sys/block/{$disk}/device/model 2>/dev/null | tr -d '\n'", false, 3);
    $info['vendor'] = execCmd("cat /sys/block/{$disk}/device/vendor 2>/dev/null | tr -d '\n'", false, 3);
    $info['serial'] = execCmd("cat /sys/block/{$disk}/device/serial 2>/dev/null | tr -d '\n'", false, 3);
    $info['revision'] = execCmd("cat /sys/block/{$disk}/device/rev 2>/dev/null | tr -d '\n'", false, 3);
    
    $size = execCmd("cat /sys/block/{$disk}/size 2>/dev/null", false, 3);
    if (!empty($size)) {
        $info['size_bytes'] = (float)$size * 512;
        $info['size_gb'] = round($info['size_bytes'] / 1024 / 1024 / 1024, 2);
    }
    
    $rotational = execCmd("cat /sys/block/{$disk}/queue/rotational 2>/dev/null", false, 3);
    $info['type'] = trim($rotational) == '0' ? 'SSD' : 'HDD';
    
    $info['state'] = execCmd("cat /sys/block/{$disk}/device/state 2>/dev/null | tr -d '\n'", false, 3);
    $info['smart'] = getFullSmartData($disk);
    $info['temperature'] = getDiskTemperature($disk);
    
    return $info;
}

// ==================== CONSOLE ====================

function executeLocalCommand($command, $timeout = 60) {
    $command = trim($command);
    
    $allowedCommands = [
        'lsblk', 'fdisk', 'parted', 'blkid', 'df', 'mount', 
        'umount', 'ls', 'cat', 'echo', 'smartctl', 'hdparm'
    ];
    
    $cmdBase = explode(' ', $command)[0];
    $isAllowed = false;
    foreach ($allowedCommands as $allowed) {
        if (strpos($cmdBase, $allowed) === 0) {
            $isAllowed = true;
            break;
        }
    }
    
    if (!$isAllowed && strpos($command, 'sudo') !== 0) {
        return ['success' => false, 'error' => 'Command not allowed: ' . $cmdBase];
    }
    
    if (strpos($command, 'sudo') !== 0) {
        $command = 'sudo ' . $command;
    }
    
    $command .= " 2>&1";
    
    $descriptorspec = [
        0 => ["pipe", "r"],
        1 => ["pipe", "w"],
        2 => ["pipe", "w"]
    ];
    
    $process = @proc_open($command, $descriptorspec, $pipes);
    
    if (!is_resource($process)) {
        return ['success' => false, 'error' => 'Failed to execute command'];
    }
    
    $output = '';
    $startTime = time();
    
    while (time() - $startTime < $timeout) {
        $read = [$pipes[1]];
        $write = null;
        $except = null;
        
        if (stream_select($read, $write, $except, 0, 100000) > 0) {
            $chunk = fread($pipes[1], 8192);
            if ($chunk !== false && $chunk !== '') {
                $output .= $chunk;
            }
        }
        
        $status = proc_get_status($process);
        if (!$status['running']) {
            while (!feof($pipes[1])) {
                $chunk = fread($pipes[1], 8192);
                if ($chunk === false) break;
                $output .= $chunk;
            }
            break;
        }
        
        usleep(50000);
    }
    
    fclose($pipes[0]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);
    
    if (strlen($output) > 500000) {
        $output = substr($output, 0, 500000) . "\n... (output truncated)";
    }
    
    return [
        'success' => true,
        'output' => $output ?: "(no output)\n",
        'command' => $command
    ];
}

// ==================== ОБРАБОТЧИК ЗАПРОСОВ ====================
$response = ['success' => false, 'error' => 'Unknown action'];

try {
    switch ($action) {
        case 'get_all':
			cleanupOrphanedMountPoints();
			verifyMountPoints();
			
			$disks = getAllDisksWithVirtual();
			$response = [
				'success' => true, 
				'disks' => $disks,
				'fstab_entries' => getFstabEntries(),
				'mounted_devices' => getMountedDevices()
			];
			break;
			
		 case 'get_logs':
            $lines = (int)($input['lines'] ?? 100);
            $logs = getLogs($lines);
            $response = ['success' => true, 'logs' => $logs];
            break;
            
        case 'clear_logs':
            $response = ['success' => clearLogs()];
            break;
            
        case 'get_operation_status':
            $response = ['success' => true, 'status' => ['progress' => 100, 'message' => 'Готово']];
            break;
            
        case 'clear_operation_status':
            $response = ['success' => true];
            break;
            
        case 'init_disk':
            $disk = $input['disk'] ?? '';
            $tableType = $input['table_type'] ?? 'gpt';
            if (empty($disk)) {
                $response = ['success' => false, 'error' => 'Disk name required'];
                break;
            }
            $response = initDisk($disk, $tableType);
            break;
            
        case 'mount':
            $device = $input['device'] ?? '';
            if (empty($device)) {
                $response = ['success' => false, 'error' => 'Device name required'];
                break;
            }
            $response = mountDevice(
                $device,
                $input['mount_point'] ?? '',
                $input['fs_type'] ?? 'auto',
                $input['add_to_fstab'] ?? false,
                $input['fstab_options'] ?? 'defaults'
            );
            break;
            
        case 'umount':
            $device = $input['device'] ?? '';
            if (empty($device)) {
                $response = ['success' => false, 'error' => 'Device name required'];
                break;
            }
            $response = umountDevice(
                $device,
                ($input['force'] ?? false) === true || $input['force'] === 'true',
                $input['remove_from_fstab'] ?? false
            );
            break;
            
        case 'partition_create':
			$disk = $input['disk'] ?? '';
			if (empty($disk)) {
				$response = ['success' => false, 'error' => 'Disk name required'];
				break;
			}
			$response = createPartition(
				$disk,
				$input['size'] ?? '0',
				$input['fs_type'] ?? null,
				$input['label'] ?? '',
				$input['format'] ?? false,
				$input['quick_format'] ?? true,
				$input['partition_type'] ?? 'primary'
			);
			break;
            
        case 'partition_delete':
			$partition = $input['partition'] ?? '';
			if (empty($partition)) {
				$response = ['success' => false, 'error' => 'Partition name required'];
				break;
			}
			if (preg_match('/^md\d+(?:p\d+|\d+)$/', $partition)) {
				$response = deleteRaidPartition($partition);
			} else {
				$response = deletePartition($partition);
			}
			break;
            
        case 'partition_format':
            $partition = $input['partition'] ?? '';
            if (empty($partition)) {
                $response = ['success' => false, 'error' => 'Partition name required'];
                break;
            }
            $response = formatPartition(
                $partition,
                $input['fs_type'] ?? 'ext4',
                $input['label'] ?? '',
                $input['quick_format'] ?? true
            );
            break;
            
        case 'add_to_fstab':
            $device = $input['device'] ?? '';
            $mountPoint = $input['mount_point'] ?? '';
            $fsType = $input['fs_type'] ?? 'ext4';
            $options = $input['options'] ?? 'defaults';
            if (empty($device) || empty($mountPoint)) {
                $response = ['success' => false, 'error' => 'Device and mount point required'];
                break;
            }
            $response = addToFstab($device, $mountPoint, $fsType, $options);
            break;
            
        case 'remove_from_fstab':
            $uuid = $input['uuid'] ?? '';
            if (empty($uuid)) {
                $response = ['success' => false, 'error' => 'UUID required'];
                break;
            }
            $response = ['success' => removeFromFstabByUuid($uuid)];
            break;
            
        case 'safe_remove':
            $disk = $input['disk'] ?? '';
            if (empty($disk)) {
                $response = ['success' => false, 'error' => 'Disk name required'];
                break;
            }
            $response = safeRemoveDisk($disk);
            break;
            
        case 'disk_info':
            $disk = $input['disk'] ?? '';
            if (empty($disk)) {
                $response = ['success' => false, 'error' => 'Disk name required'];
                break;
            }
            $response = ['success' => true, 'info' => getDiskInfo($disk)];
            break;
            
        case 'get_smart':
            $disk = $input['disk'] ?? '';
            if (empty($disk)) {
                $response = ['success' => false, 'error' => 'Disk name required'];
                break;
            }
            $response = ['success' => true, 'smart' => getFullSmartData($disk)];
            break;
			
		case 'get_raw_smart':
			$disk = $input['disk'] ?? '';
			if (empty($disk)) {
				$response = ['success' => false, 'error' => 'Disk name required'];
				break;
			}
			$response = ['success' => true, 'output' => getRawSmartOutput($disk)];
			break;
			
		case 'mount_status':
			$device = $input['device'] ?? '';
			if (empty($device)) {
				$response = ['success' => false, 'error' => 'Device required'];
				break;
			}
			$response = ['success' => true, 'status' => getMountStatus($device)];
			break;
        
		case 'get_fstab':
			$response = ['success' => true, 'entries' => getFstabEntries()];
			break;
			
		case 'remove_fstab_entry':
			$uuid = $input['uuid'] ?? '';
			$mountPoint = $input['mount_point'] ?? '';
			
			if (empty($uuid) && empty($mountPoint)) {
				$response = ['success' => false, 'error' => 'UUID or mount point required'];
				break;
			}
			
			if (!empty($uuid)) {
				$response = ['success' => removeFromFstabByUuid($uuid)];
			} else {
				$entries = getFstabEntries();
				$foundUuid = null;
				foreach ($entries as $entry) {
					if ($entry['mount_point'] === $mountPoint && $entry['uuid']) {
						$foundUuid = $entry['uuid'];
						break;
					}
				}
				if ($foundUuid) {
					$response = ['success' => removeFromFstabByUuid($foundUuid)];
				} else {
					$response = ['success' => false, 'error' => 'Entry not found'];
				}
			}
			break;
		
		case 'mount_fstab_entry':
			$mountPoint = $input['mount_point'] ?? '';
			if (empty($mountPoint)) {
				$response = ['success' => false, 'error' => 'Mount point required'];
				break;
			}
			$response = mountFstabEntry($mountPoint);
			break;

		case 'get_partition_max_size':
			$partition = $input['partition'] ?? '';
			if (empty($partition)) {
				$response = ['success' => false, 'error' => 'Partition name required'];
				break;
			}
			$response = getPartitionMaxSize($partition);
			break;
			
		case 'check_fstab':
			$partition = $input['partition'] ?? '';
			if (empty($partition)) {
				$response = ['success' => false, 'error' => 'Partition name required'];
				break;
			}
			$response = checkPartitionInFstab($partition);
			break;
			
		case 'smart_umount':
			$device = $input['device'] ?? '';
			$autoRemove = $input['auto_remove_from_fstab'] ?? true;
			if (empty($device)) {
				$response = ['success' => false, 'error' => 'Device name required'];
				break;
			}
			$response = smartUmount($device, $autoRemove);
			break;

		case 'mount_all_fstab':
			$response = mountAllFstab();
			break;
		
		case 'exec_command':
			$command = $input['command'] ?? '';
			if (empty($command)) {
				$response = ['success' => false, 'error' => 'Command required'];
				break;
			}
			$response = executeLocalCommand($command);
			break;
		
		case 'get_lvm_info':
			$response = [
				'success' => true,
				'volume_groups' => getLvmVolumeGroups(),
				'lvm_on_raid' => getLvmOnRaidInfo()
			];
			break;
			
		case 'get_raid_info':
			$response = [
				'success' => true,
				'raid_arrays' => getRaidArrays()
			];
			break;
		
		case 'get_lvm_vg_info':
			$vgName = $input['vg_name'] ?? '';
			if (empty($vgName)) {
				$response = ['success' => false, 'error' => 'VG name required'];
			} else {
				$response = ['success' => true, 'info' => getLvmVgInfo($vgName)];
			}
			break;

		case 'get_raid_array_info':
			$raidName = $input['raid_name'] ?? '';
			if (empty($raidName)) {
				$response = ['success' => false, 'error' => 'RAID name required'];
			} else {
				$response = ['success' => true, 'info' => getRaidArrayInfo($raidName)];
			}
			break;

		case 'create_partition_on_vg':
			$vgName = $input['vg_name'] ?? '';
			$lvName = $input['lv_name'] ?? '';
			$size = $input['size'] ?? '';
			$fsType = $input['fs_type'] ?? 'ext4';
			$format = $input['format'] ?? false;
			
			if (empty($vgName) || empty($lvName) || empty($size)) {
				$response = ['success' => false, 'error' => 'VG name, LV name and size required'];
			} else {
				$response = createLvmLogicalVolume($vgName, $lvName, $size, $fsType, $format);
			}
			break;

		case 'create_partition_on_raid':
			$raidName = $input['raid_name'] ?? '';
			$size = $input['size'] ?? '';
			$fsType = $input['fs_type'] ?? 'ext4';
			$format = $input['format'] ?? false;
			
			if (empty($raidName)) {
				$response = ['success' => false, 'error' => 'RAID name required'];
				break;
			}
			$response = createPartitionOnRaid($raidName, $size, $fsType, $format);
			break;
		
		case 'resize_partition':
			$partition = $input['partition'] ?? '';
			$newSize = (float)($input['new_size_gb'] ?? 0);
			
			if (empty($partition) || $newSize <= 0) {
				$response = ['success' => false, 'error' => 'Partition name and valid size required'];
				break;
			}
			
			$cleanPartition = preg_replace('#^/dev/#', '', $partition);
			
			$isRaidPartition = preg_match('/^md\d+p\d+$/i', $cleanPartition);
			
			writeLog("resize_partition: partition='{$partition}', clean='{$cleanPartition}', isRaid=" . ($isRaidPartition ? 'true' : 'false'));
			
			if ($isRaidPartition) {
				$response = resizeRaidPartition($cleanPartition, $newSize);
			} else {
				$response = resizePartition($cleanPartition, $newSize);
			}
			break;
		
		case 'get_raid_free_space':
			$raidName = $input['raid_name'] ?? '';
			if (empty($raidName)) {
				$response = ['success' => false, 'error' => 'RAID name required'];
				break;
			}
			$freeInfo = getRaidFreeSpaceInfo($raidName);
			$response = [
				'success' => true,
				'free_bytes' => $freeInfo['free_bytes'],
				'free_gb' => $freeInfo['free_gb'],
				'total_bytes' => $freeInfo['total_bytes'],
				'total_gb' => $freeInfo['total_gb'],
				'used_bytes' => $freeInfo['used_bytes'],
				'used_gb' => $freeInfo['used_gb'],
				'has_partition_table' => $freeInfo['has_partition_table'],
				'existing_partitions_count' => count($freeInfo['existing_partitions']),
				'existing_partitions' => $freeInfo['existing_partitions'],
				'next_free_position' => $freeInfo['next_free_position']
			];
			break;
		
        default:
            $response = ['success' => false, 'error' => 'Unknown action: ' . $action];
    }
} catch (Exception $e) {
    writeError("Exception: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
    $response = ['success' => false, 'error' => $e->getMessage()];
}

writeLog("Response for action {$action}: " . json_encode(['success' => $response['success'] ?? false]));
echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>