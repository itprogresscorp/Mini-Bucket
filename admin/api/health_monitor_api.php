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

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(dirname(__FILE__)));
}

if (file_exists(ROOT_PATH . '/config.php')) {
    require_once ROOT_PATH . '/config.php';
}

if (php_sapi_name() !== 'cli') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, X-API-Key, Authorization, X-Requested-With");
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Max-Age: 86400");
    header('Content-Type: application/json');
    
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }
}

// Проверка API ключа
function validateApiKey() {
    if (php_sapi_name() === 'cli') {
        return true;
    }
    
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

if (php_sapi_name() !== 'cli') {
    validateApiKey();
}

$action = $_GET['action'] ?? '';

// ========== ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ ==========

global $db2;
       
if (!$db2) {
   try {
      $db2 = getDB2();
    } catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
	}
}

function addNotification($type, $severity, $title, $message, $details = null) {
    addNotificationSimple($type, $severity, $title, $message, $details);

}

function getSettings() {
    global $db2;
	
	$settings = [];
    $result = $db2->query("SELECT setting_key, setting_value FROM notification_settings");
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    return $settings;
}

function sendWebhook($data) {
    $settings = getSettings();
    if ($settings['webhook_enabled'] != 1 || empty($settings['webhook_url'])) {
        return false;
    }
    
    $ch = curl_init($settings['webhook_url']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $result = curl_exec($ch);
    curl_close($ch);
    
    return $result !== false;
}

function sendEmail($subject, $message) {
    $settings = getSettings();
    
    if ($settings['email_enabled'] != 1 || empty($settings['email_recipient'])) {
        error_log("Email disabled or no recipient");
        return false;
    }
    
    $phpmailerPath = ROOT_PATH . '/lib/PHPMailer/PHPMailer/src/PHPMailer.php';
    if (!file_exists($phpmailerPath)) {
        error_log("PHPMailer not found");
        return false;
    }
    
    try {
        require_once $phpmailerPath;
        require_once ROOT_PATH . '/lib/PHPMailer/PHPMailer/src/SMTP.php';
        require_once ROOT_PATH . '/lib/PHPMailer/PHPMailer/src/Exception.php';
        
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = $settings['smtp_host'] ?? '';
        $mail->SMTPAuth   = true;
        
        $mail->Username = $settings['smtp_username'] ?? '';
        $mail->Password = $settings['smtp_password'] ?? '';
        
        $mail->SMTPSecure = $settings['smtp_encryption'] ?? '';
        $mail->Port       = intval($settings['smtp_port'] ?? 25);
        
        $fromEmail = $settings['smtp_from_email'] ?? '';
        if (empty($fromEmail) && !empty($settings['smtp_username']) && !empty($settings['smtp_domain'])) {
            $fromEmail = $settings['smtp_username'] . '@' . $settings['smtp_domain'];
        }
        
        $fromName = $settings['smtp_from_name'] ?? 'Mini-B Health Monitor';
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($settings['email_recipient']);
        
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $message;
        $mail->AltBody = strip_tags($message);
        
        $mail->send();
        error_log("Email sent successfully to {$settings['email_recipient']}");
        return true;
        
    } catch (Exception $e) {
        error_log("Email sending failed: " . ($mail->ErrorInfo ?? $e->getMessage()));
        return false;
    }
}

// ========== ПРОВЕРКА ДИСКОВ ==========

function checkDisks() {
    global $db2;
    
    $result = ['disks' => [], 'db_disks' => [], 'stats' => ['total' => 0, 'ok' => 0, 'warning' => 0, 'critical' => 0]];
    
    $lsblk = shell_exec("lsblk -o NAME,TYPE,SIZE,MODEL -d -n 2>/dev/null | grep -E 'disk' | grep -vE 'mtdblock|loop|ram'");
    $diskLines = array_filter(explode("\n", trim($lsblk)));
    
    $currentDisks = [];
    
    foreach ($diskLines as $line) {
        $parts = preg_split('/\s+/', trim($line));
        if (count($parts) < 3) continue;
        
        $diskName = $parts[0];
        $diskSize = $parts[2];
        $diskModel = count($parts) > 3 ? implode(' ', array_slice($parts, 3)) : $diskName;
        $device = '/dev/' . $diskName;
        
        $smart = getSmartInfoForDisk($device);
        
        $currentDisks[$diskName] = [
            'name' => $diskName,
            'model' => $diskModel,
            'size' => $diskSize,
            'smart_status' => $smart['status'],
            'smart_temp' => $smart['temp'],
            'smart_bad_sectors' => $smart['bad_sectors'],
            'smart_realloc' => $smart['realloc_sectors']
        ];
    }
    
    $dbDisks = [];
    $stmt = $db2->prepare("SELECT * FROM monitored_disks ORDER BY id");
    $res = $stmt->execute();
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $dbDisks[$row['disk_name']] = $row;
    }
    
    $settings = getSettings();
    
    foreach ($currentDisks as $diskName => $disk) {
        if (!isset($dbDisks[$diskName])) {
            $stmt = $db2->prepare("INSERT INTO monitored_disks (disk_name, disk_model, disk_size, disk_path, is_new, is_active) 
                                  VALUES (:name, :model, :size, :path, 1, 1)");
            $stmt->bindValue(':name', $diskName, SQLITE3_TEXT);
            $stmt->bindValue(':model', $disk['model'], SQLITE3_TEXT);
            $stmt->bindValue(':size', $disk['size'], SQLITE3_TEXT);
            $stmt->bindValue(':path', '/dev/' . $diskName, SQLITE3_TEXT);
            $stmt->execute();
            
            addNotification('disk', 'info', 'New Disk Detected', 
                          "New disk {$diskName} ({$disk['model']}) has been detected",
                          json_encode($disk));
        } else {
            $stmt = $db2->prepare("UPDATE monitored_disks SET last_seen = CURRENT_TIMESTAMP, is_active = 1 WHERE disk_name = :name");
            $stmt->bindValue(':name', $diskName, SQLITE3_TEXT);
            $stmt->execute();
        }
        
        if ($disk['smart_bad_sectors'] > 0 && $settings['notify_smart_failed'] == 1) {
            addNotification('smart', 'critical', 'SMART Failure Detected',
                          "Disk {$diskName} has {$disk['smart_bad_sectors']} bad sectors",
                          json_encode($disk));
            $result['stats']['critical']++;
        } else {
            $result['stats']['ok']++;
        }
        
        $result['disks'][] = $disk;
        $result['stats']['total']++;
    }
    
    foreach ($dbDisks as $diskName => $dbDisk) {
        if (!isset($currentDisks[$diskName]) && $dbDisk['is_active'] == 1) {
            $stmt = $db2->prepare("UPDATE monitored_disks SET is_active = 0 WHERE disk_name = :name");
            $stmt->bindValue(':name', $diskName, SQLITE3_TEXT);
            $stmt->execute();
            
            if ($settings['notify_disk_missing'] == 1) {
                addNotification('disk', 'critical', 'Disk Missing',
                              "Disk {$diskName} is no longer present in the system",
                              json_encode($dbDisk));
            }
            $result['stats']['critical']++;
        }
    }
    
    $finalDbDisks = [];
    $stmt = $db2->prepare("SELECT * FROM monitored_disks ORDER BY id");
    $res = $stmt->execute();
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $finalDbDisks[] = $row;
    }
    $result['db_disks'] = $finalDbDisks;
    
    return $result;
}

function getSmartInfoForDisk($device) {
    $smart = ['status' => 'UNKNOWN', 'temp' => 'N/A', 'bad_sectors' => 0, 'realloc_sectors' => 0];
    
    if (!file_exists($device)) return $smart;
    
    $health = shell_exec("sudo smartctl -H " . $device . " 2>/dev/null | grep 'SMART overall-health'");
    if ($health && strpos($health, 'PASSED') !== false) {
        $smart['status'] = 'PASSED';
    } elseif ($health && strpos($health, 'FAILED') !== false) {
        $smart['status'] = 'FAILED';
    }
    
    $realloc = shell_exec("sudo smartctl -A " . $device . " 2>/dev/null | grep -E 'Reallocated_Sector_Ct' | awk '{print $10}'");
    if ($realloc && intval($realloc) > 0) {
        $smart['bad_sectors'] = intval($realloc);
        $smart['realloc_sectors'] = intval($realloc);
        $smart['status'] = 'FAILED';
    }
    
    $pending = shell_exec("sudo smartctl -A " . $device . " 2>/dev/null | grep 'Current_Pending_Sector' | awk '{print $10}'");
    if ($pending && intval($pending) > 0) {
        $smart['bad_sectors'] += intval($pending);
        $smart['status'] = 'FAILED';
    }
    
    $temp = shell_exec("sudo smartctl -A " . $device . " 2>/dev/null | grep -E 'Temperature_Celsius|Temperature:|Current Drive Temperature' | head -1");
    if ($temp && preg_match('/(\d+)/', $temp, $matches)) {
        $tempVal = intval($matches[1]);
        if ($tempVal > 0 && $tempVal < 100) {
            $smart['temp'] = $tempVal . '°C';
        }
    }
    
    return $smart;
}

// ========== ПРОВЕРКА RAID ==========

function checkRaid() {
    global $db2;
    
    $result = ['raid' => [], 'db_raids' => []];
    
    $mdStats = shell_exec("cat /proc/mdstat 2>/dev/null");
    $mdLines = explode("\n", $mdStats);
    
    $currentRaids = [];
    $currentRaidName = null;
    
    foreach ($mdLines as $line) {
        if (preg_match('/^(md\d+)\s+:/', $line, $matches)) {
            $currentRaidName = $matches[1];
            $currentRaids[$currentRaidName] = [
                'name' => $currentRaidName,
                'level' => 'unknown',
                'status' => 'unknown',
                'size' => 'N/A',
                'degraded' => false,
                'failed_disks' => 0,
                'working_disks' => 0,
                'total_disks' => 0,
                'devices' => []
            ];
            
            if (strpos($line, '[UU]') !== false || strpos($line, '[U_]') === false) {
                $currentRaids[$currentRaidName]['status'] = 'active';
            }
            if (strpos($line, '[_U]') !== false || strpos($line, '[U_]') !== false) {
                $currentRaids[$currentRaidName]['degraded'] = true;
                $currentRaids[$currentRaidName]['status'] = 'degraded';
            }
            if (preg_match('/\[(\d+)\/(\d+)\]/', $line, $diskMatches)) {
                $currentRaids[$currentRaidName]['working_disks'] = intval($diskMatches[1]);
                $currentRaids[$currentRaidName]['total_disks'] = intval($diskMatches[2]);
                $currentRaids[$currentRaidName]['failed_disks'] = $currentRaids[$currentRaidName]['total_disks'] - $currentRaids[$currentRaidName]['working_disks'];
            }
        } elseif ($currentRaidName && preg_match('/^\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)/', $line, $syncMatches)) {
            $currentRaids[$currentRaidName]['sync_percent'] = round(($syncMatches[4] / $syncMatches[3]) * 100, 1);
        }
    }
    
    foreach (array_keys($currentRaids) as $raidName) {
        $detail = shell_exec("sudo mdadm --detail /dev/{$raidName} 2>/dev/null");
        if ($detail) {
            if (preg_match('/Raid Level\s+:\s+(.+)/', $detail, $matches)) {
                $currentRaids[$raidName]['level'] = trim($matches[1]);
            }
            if (preg_match('/Array Size\s+:\s+(.+)/', $detail, $matches)) {
                $currentRaids[$raidName]['size'] = trim($matches[1]);
            }
            if (preg_match('/State\s+:\s+(.+)/', $detail, $matches)) {
                $currentRaids[$raidName]['status'] = trim($matches[1]);
            }
            if (preg_match('/Failed Devices\s+:\s+(\d+)/', $detail, $matches)) {
                $currentRaids[$raidName]['failed_disks'] = intval($matches[1]);
            }
            if (preg_match('/Working Devices\s+:\s+(\d+)/', $detail, $matches)) {
                $currentRaids[$raidName]['working_disks'] = intval($matches[1]);
            }
            if (preg_match('/Total Devices\s+:\s+(\d+)/', $detail, $matches)) {
                $currentRaids[$raidName]['total_disks'] = intval($matches[1]);
            }
            
            preg_match_all('/\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\S+)\s+(\d+)\s+(\d+)\s+(\S+)/', $detail, $devMatches, PREG_SET_ORDER);
            foreach ($devMatches as $dev) {
                $currentRaids[$raidName]['devices'][] = $dev[5];
            }
        }
    }
    
    $settings = getSettings();
    $dbRaids = [];
    $stmt = $db2->prepare("SELECT * FROM monitored_raids");
    $res = $stmt->execute();
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $dbRaids[$row['raid_name']] = $row;
    }
    
    foreach ($currentRaids as $raidName => $raid) {
        if (!isset($dbRaids[$raidName])) {
            $stmt = $db2->prepare("INSERT INTO monitored_raids (raid_name, raid_level, raid_size, devices, is_new, is_active) 
                                  VALUES (:name, :level, :size, :devices, 1, 1)");
            $stmt->bindValue(':name', $raidName, SQLITE3_TEXT);
            $stmt->bindValue(':level', $raid['level'], SQLITE3_TEXT);
            $stmt->bindValue(':size', $raid['size'], SQLITE3_TEXT);
            $stmt->bindValue(':devices', json_encode($raid['devices']), SQLITE3_TEXT);
            $stmt->execute();
            
            addNotification('raid', 'info', 'New RAID Array Detected',
                          "New RAID array {$raidName} ({$raid['level']}) has been detected",
                          json_encode($raid));
        } else {
            $stmt = $db2->prepare("UPDATE monitored_raids SET last_seen = CURRENT_TIMESTAMP, is_active = 1 WHERE raid_name = :name");
            $stmt->bindValue(':name', $raidName, SQLITE3_TEXT);
            $stmt->execute();
        }
        
        if ($raid['degraded'] && $settings['notify_raid_degraded'] == 1) {
            addNotification('raid', 'critical', 'RAID Degraded',
                          "RAID array {$raidName} is degraded. Working: {$raid['working_disks']}/{$raid['total_disks']}",
                          json_encode($raid));
        }
        
        $result['raid'][] = $raid;
    }
    
    foreach ($dbRaids as $raidName => $dbRaid) {
        if (!isset($currentRaids[$raidName]) && $dbRaid['is_active'] == 1) {
            $stmt = $db2->prepare("UPDATE monitored_raids SET is_active = 0 WHERE raid_name = :name");
            $stmt->bindValue(':name', $raidName, SQLITE3_TEXT);
            $stmt->execute();
            addNotification('raid', 'warning', 'RAID Array Missing',
                          "RAID array {$raidName} is no longer present",
                          json_encode($dbRaid));
        }
    }
    
    $finalDbRaids = [];
    $stmt = $db2->prepare("SELECT * FROM monitored_raids ORDER BY id");
    $res = $stmt->execute();
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $finalDbRaids[] = $row;
    }
    $result['db_raids'] = $finalDbRaids;
    
    return $result;
}

// ========== ПРОВЕРКА LVM ==========

function checkLvm() {
    $result = ['vgs' => [], 'lvs' => []];
    
    $vgdisplay = shell_exec("sudo vgdisplay 2>/dev/null");
    if ($vgdisplay) {
        $currentVg = [];
        $lines = explode("\n", $vgdisplay);
        foreach ($lines as $line) {
            if (preg_match('/VG Name\s+(.+)/', $line, $matches)) {
                if (!empty($currentVg)) {
                    $result['vgs'][] = $currentVg;
                }
                $currentVg = ['name' => trim($matches[1])];
            } elseif (preg_match('/VG Size\s+(.+)/', $line, $matches) && isset($currentVg)) {
                $currentVg['size'] = trim($matches[1]);
            } elseif (preg_match('/Free PE \/ Size\s+(.+)/', $line, $matches) && isset($currentVg)) {
                $currentVg['free'] = trim($matches[1]);
            } elseif (preg_match('/Total PE\s+(\d+)/', $line, $matches) && isset($currentVg)) {
                $currentVg['total_pe'] = intval($matches[1]);
            } elseif (preg_match('/Allocated PE\s+(\d+)/', $line, $matches) && isset($currentVg)) {
                $currentVg['allocated_pe'] = intval($matches[1]);
                $currentVg['used_percent'] = $currentVg['total_pe'] > 0 ? round(($currentVg['allocated_pe'] / $currentVg['total_pe']) * 100, 1) : 0;
            } elseif (preg_match('/PV Count\s+(\d+)/', $line, $matches) && isset($currentVg)) {
                $currentVg['pv_count'] = intval($matches[1]);
            } elseif (preg_match('/LV Count\s+(\d+)/', $line, $matches) && isset($currentVg)) {
                $currentVg['lv_count'] = intval($matches[1]);
                $currentVg['size_formatted'] = $currentVg['size'];
                $currentVg['free_formatted'] = $currentVg['free'];
            }
        }
        if (!empty($currentVg)) {
            $result['vgs'][] = $currentVg;
        }
    }
    
    $lvdisplay = shell_exec("sudo lvdisplay 2>/dev/null");
    if ($lvdisplay) {
        $currentLv = [];
        $lines = explode("\n", $lvdisplay);
        foreach ($lines as $line) {
            if (preg_match('/LV Name\s+(.+)/', $line, $matches)) {
                if (!empty($currentLv)) {
                    $result['lvs'][] = $currentLv;
                }
                $currentLv = ['name' => trim($matches[1])];
            } elseif (preg_match('/VG Name\s+(.+)/', $line, $matches) && isset($currentLv)) {
                $currentLv['vg_name'] = trim($matches[1]);
            } elseif (preg_match('/LV Size\s+(.+)/', $line, $matches) && isset($currentLv)) {
                $currentLv['size'] = trim($matches[1]);
            } elseif (preg_match('/LV Status\s+(.+)/', $line, $matches) && isset($currentLv)) {
                $currentLv['active'] = trim($matches[1]) === 'available';
                $currentLv['health'] = $currentLv['active'] ? 'active' : 'inactive';
            } elseif (preg_match('/LV Path\s+(.+)/', $line, $matches) && isset($currentLv)) {
                $currentLv['path'] = trim($matches[1]);
                $currentLv['size_formatted'] = $currentLv['size'];
                
                $mountCheck = shell_exec("findmnt -n -o TARGET " . escapeshellarg($currentLv['path']) . " 2>/dev/null");
                if ($mountCheck && trim($mountCheck)) {
                    $currentLv['mount_point'] = trim($mountCheck);
                }
            }
        }
        if (!empty($currentLv)) {
            $result['lvs'][] = $currentLv;
        }
    }
    
    return $result;
}

// ========== ПРОВЕРКА ТЕМПЕРАТУР ==========

function checkTemperatures() {
    $settings = getSettings();
    $cpuThreshold = intval($settings['cpu_temp_threshold'] ?? 85);
    $diskThreshold = intval($settings['disk_temp_threshold'] ?? 55);
    
    $result = [
        'cpu_temp' => null,
        'disk_temps' => [],
        'thresholds' => ['cpu' => $cpuThreshold, 'disk' => $diskThreshold]
    ];
    
    if (file_exists('/sys/class/thermal/thermal_zone0/temp')) {
        $tempRaw = file_get_contents('/sys/class/thermal/thermal_zone0/temp');
        $tempCelsius = round(intval($tempRaw) / 1000, 1);
        $result['cpu_temp'] = $tempCelsius . '°C';
        
        global $db2;
        $stmt = $db2->prepare("INSERT INTO temperature_history (sensor_type, sensor_name, temperature) VALUES ('cpu', 'cpu0', :temp)");
        $stmt->bindValue(':temp', $tempCelsius, SQLITE3_FLOAT);
        $stmt->execute();
        
        if ($tempCelsius > $cpuThreshold && $settings['notify_temp_critical'] == 1) {
            addNotification('temperature', 'critical', 'High CPU Temperature',
                          "CPU temperature is {$tempCelsius}°C (threshold: {$cpuThreshold}°C)",
                          "CPU温度: {$tempCelsius}°C");
        }
    }
    
    $lsblk = shell_exec("lsblk -o NAME,TYPE -d -n 2>/dev/null | grep -E 'disk' | grep -vE 'mtdblock|loop|ram' | awk '{print $1}'");
    $diskNames = array_filter(explode("\n", trim($lsblk)));
    
    foreach ($diskNames as $diskName) {
        $device = '/dev/' . $diskName;
        
        $tempOutput = shell_exec("sudo smartctl -A " . $device . " 2>/dev/null | grep -E 'Temperature_Celsius|Temperature:|Current Drive Temperature' | head -1");
        if ($tempOutput && preg_match('/(\d+)/', $tempOutput, $matches)) {
            $tempVal = intval($matches[1]);
            if ($tempVal > 0 && $tempVal < 100) {
                $temp = $tempVal . '°C';
                $result['disk_temps'][] = ['name' => $diskName, 'temp' => $temp];
                
                global $db2;
                $stmt = $db2->prepare("INSERT INTO temperature_history (sensor_type, sensor_name, temperature) VALUES ('disk', :name, :temp)");
                $stmt->bindValue(':name', $diskName, SQLITE3_TEXT);
                $stmt->bindValue(':temp', $tempVal, SQLITE3_FLOAT);
                $stmt->execute();
                
                if ($tempVal > $diskThreshold && $settings['notify_temp_critical'] == 1) {
                    addNotification('temperature', 'warning', 'High Disk Temperature',
                                  "Disk {$diskName} temperature is {$temp} (threshold: {$diskThreshold}°C)",
                                  "Disk temperature: {$temp}");
                }
            }
        }
    }
    
    return $result;
}

// ========== ПРОВЕРКА СЕТЕВЫХ ШАР ==========

function checkShares() {
    global $db2;
    
    $result = ['shares' => []];
    
    scanAndAddShares();
    
    $stmt = $db2->prepare("SELECT * FROM monitored_shares WHERE is_active = 1");
    $res = $stmt->execute();
    
    while ($share = $res->fetchArray(SQLITE3_ASSOC)) {
        $isAvailable = false;
        $error = null;
        $diskStatus = 'ok';
        
        if (!empty($share['share_path']) && file_exists($share['share_path'])) {
            $isAvailable = true;
        } elseif (!empty($share['share_path'])) {
            $isAvailable = false;
            $error = "Path does not exist: {$share['share_path']}";
            
            $pathParts = explode('/', $share['share_path']);
            if (count($pathParts) >= 3) {
                $deviceCheck = shell_exec("mount | grep " . escapeshellarg($pathParts[1] . '/' . $pathParts[2]) . " 2>/dev/null");
                if (empty($deviceCheck)) {
                    $diskStatus = 'missing';
                }
            }
        }
        
        $stmt2 = $db2->prepare("UPDATE monitored_shares SET last_check = CURRENT_TIMESTAMP, last_status = :status, error_message = :error WHERE id = :id");
        $stmt2->bindValue(':status', $isAvailable ? 'available' : 'down', SQLITE3_TEXT);
        $stmt2->bindValue(':error', $error, SQLITE3_TEXT);
        $stmt2->bindValue(':id', $share['id'], SQLITE3_INTEGER);
        $stmt2->execute();
        
        if (!$isAvailable) {
            $settings = getSettings();
            if ($settings['notify_share_down'] == 1) {
                addNotification('share', 'critical', 'Share Unavailable',
                              "Share {$share['share_name']} ({$share['share_type']}) is not accessible",
                              json_encode($share));
            }
        }
        
        $result['shares'][] = [
            'id' => $share['id'],
            'type' => $share['share_type'],
            'name' => $share['share_name'],
            'path' => $share['share_path'],
            'is_available' => $isAvailable,
            'error' => $error,
            'disk_status' => $diskStatus,
            'last_check' => $share['last_check']
        ];
    }
    
    return $result;
}

function scanAndAddShares() {
    global $db2;
    
    $shares = [];
    
    if (file_exists('/etc/samba/smb.conf')) {
        $content = file_get_contents('/etc/samba/smb.conf');
        preg_match_all('/^\s*\[([^\]]+)\]\s*$/m', $content, $matches);
        foreach ($matches[1] as $shareName) {
            if ($shareName === 'global') continue;
            
            preg_match('/\[' . preg_quote($shareName, '/') . '\](.*?)(?=\n\s*\[|$)/s', $content, $section);
            if (isset($section[1]) && preg_match('/path\s*=\s*(.+)/', $section[1], $pathMatch)) {
                $path = trim($pathMatch[1]);
                $shares['smb_' . $shareName] = ['type' => 'smb', 'name' => $shareName, 'path' => $path];
            }
        }
    }
    
    if (file_exists('/etc/exports')) {
        $content = file_get_contents('/etc/exports');
        $lines = explode("\n", $content);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || $line[0] === '#') continue;
            
            $parts = preg_split('/\s+/', $line);
            if (count($parts) >= 1) {
                $path = $parts[0];
                $shareName = 'nfs_' . basename($path);
                $shares[$shareName] = ['type' => 'nfs', 'name' => basename($path), 'path' => $path];
            }
        }
    }
    
    if (file_exists('/etc/vsftpd.conf')) {
        $content = file_get_contents('/etc/vsftpd.conf');
        if (preg_match('/local_root=(.+)/', $content, $matches)) {
            $path = trim($matches[1]);
            $shares['ftp_root'] = ['type' => 'ftp', 'name' => 'ftp_root', 'path' => $path];
        }
        if (preg_match('/anon_root=(.+)/', $content, $matches)) {
            $path = trim($matches[1]);
            $shares['ftp_anon'] = ['type' => 'ftp', 'name' => 'ftp_anon', 'path' => $path];
        }
    }
    
    foreach ($shares as $key => $share) {
        $stmt = $db2->prepare("SELECT id FROM monitored_shares WHERE share_type = :type AND share_name = :name");
        $stmt->bindValue(':type', $share['type'], SQLITE3_TEXT);
        $stmt->bindValue(':name', $share['name'], SQLITE3_TEXT);
        $res = $stmt->execute();
        
        if (!$res->fetchArray()) {
            $stmt2 = $db2->prepare("INSERT INTO monitored_shares (share_type, share_name, share_path, is_active) 
                                   VALUES (:type, :name, :path, 1)");
            $stmt2->bindValue(':type', $share['type'], SQLITE3_TEXT);
            $stmt2->bindValue(':name', $share['name'], SQLITE3_TEXT);
            $stmt2->bindValue(':path', $share['path'], SQLITE3_TEXT);
            $stmt2->execute();
        }
    }
}

// ========== SMTP ФУНКЦИИ ==========

function testSmtpConnection($host, $port, $username, $password, $encryption, $toEmail, $fromEmail = null, $domain = null) {
    $phpmailerPath = ROOT_PATH . '/lib/PHPMailer/PHPMailer/src/PHPMailer.php';
    if (!file_exists($phpmailerPath)) {
        return ['success' => false, 'error' => 'PHPMailer not installed'];
    }
    
    require_once $phpmailerPath;
    require_once ROOT_PATH . '/lib/PHPMailer/PHPMailer/src/SMTP.php';
    require_once ROOT_PATH . '/lib/PHPMailer/PHPMailer/src/Exception.php';
    
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        $mail->isSMTP();
        $mail->Host       = $host;
        $mail->SMTPAuth   = true;
        
        $mail->Username = $username;
        $mail->Password = $password;
        
        $mail->SMTPSecure = $encryption ?: '';
        $mail->Port       = intval($port) ?: 25;
        
        $fromEmailAddr = $fromEmail;
        if (empty($fromEmailAddr) && !empty($username) && !empty($domain)) {
            $fromEmailAddr = $username . '@' . $domain;
        }
        
        $mail->setFrom($fromEmailAddr, 'SMTP Test');
        $mail->addAddress($toEmail);
        $mail->Subject = 'SMTP Test from Mini-B Health Monitor';
        $mail->Body    = 'This is a test email. Your SMTP settings are working!';
        
        $mail->send();
        return ['success' => true];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $mail->ErrorInfo];
    }
}

function getAllStats() {
    global $db2;
    
    $stats = [
        'total' => 0,
        'ok' => 0,
        'warning' => 0,
        'critical' => 0
    ];
    
    // 1. Диски
    try {
        $disks = checkDisks();
        $stats['total'] += $disks['stats']['total'];
        $stats['ok'] += $disks['stats']['ok'];
        $stats['warning'] += $disks['stats']['warning'];
        $stats['critical'] += $disks['stats']['critical'];
    } catch (Exception $e) {}
    
    // 2. RAID
    try {
        $raid = checkRaid();
        if (isset($raid['raid'])) {
            foreach ($raid['raid'] as $r) {
                $stats['total']++;
                if (isset($r['degraded']) && $r['degraded']) {
                    $stats['critical']++;
                } else {
                    $stats['ok']++;
                }
            }
        }
    } catch (Exception $e) {}
    
    // 3. LVM
    try {
        $lvm = checkLvm();
        if (isset($lvm['lvs'])) {
            foreach ($lvm['lvs'] as $lv) {
                $stats['total']++;
                $isActive = isset($lv['active']) ? $lv['active'] : (isset($lv['health']) && $lv['health'] === 'active');
                if (!$isActive) {
                    $stats['warning']++;
                } else {
                    $stats['ok']++;
                }
            }
        }
    } catch (Exception $e) {}
    
    // 4. Температуры
    try {
        $temp = checkTemperatures();
        $settings = getSettings();
        $cpuThreshold = intval($settings['cpu_temp_threshold'] ?? 85);
        $diskThreshold = intval($settings['disk_temp_threshold'] ?? 55);
        
        if (isset($temp['cpu_temp']) && $temp['cpu_temp']) {
            $stats['total']++;
            $cpuVal = floatval(str_replace('°C', '', $temp['cpu_temp']));
            if ($cpuVal > $cpuThreshold) {
                $stats['warning']++;
            } else {
                $stats['ok']++;
            }
        }
        if (isset($temp['disk_temps'])) {
            foreach ($temp['disk_temps'] as $disk) {
                $stats['total']++;
                $tempVal = floatval(str_replace('°C', '', $disk['temp']));
                if ($tempVal > $diskThreshold) {
                    $stats['warning']++;
                } else {
                    $stats['ok']++;
                }
            }
        }
    } catch (Exception $e) {}
    
    // 5. Шары
    try {
        $shares = checkShares();
        if (isset($shares['shares'])) {
            foreach ($shares['shares'] as $share) {
                $stats['total']++;
                if (isset($share['is_available']) && $share['is_available']) {
                    $stats['ok']++;
                } else {
                    $stats['critical']++;
                }
            }
        }
    } catch (Exception $e) {}
    
    return $stats;
}

// Функция для сохранения уведомления
function addNotificationSimple($type, $severity, $title, $message, $details = null) {
    global $db2;
    
    $plainMessage = strip_tags($message);
    $plainTitle = strip_tags($title);
    
    $stmt = $db2->prepare("INSERT INTO notifications (notification_type, severity, title, message, details) 
                          VALUES (:type, :severity, :title, :message, :details)");
    $stmt->bindValue(':type', $type, SQLITE3_TEXT);
    $stmt->bindValue(':severity', $severity, SQLITE3_TEXT);
    $stmt->bindValue(':title', $plainTitle, SQLITE3_TEXT);
    $stmt->bindValue(':message', $plainMessage, SQLITE3_TEXT);
    $stmt->bindValue(':details', $details, SQLITE3_TEXT);
    $stmt->execute();
}



// ========== API РОУТИНГ ==========

if (php_sapi_name() !== 'cli') {

switch ($action) {
    case 'get_settings':
        echo json_encode(['success' => true, 'settings' => getSettings()]);
        break;
        
    case 'save_settings':
        $input = json_decode(file_get_contents('php://input'), true);
        if ($input) {
            foreach ($input as $key => $value) {
                $stmt = $db2->prepare("INSERT OR REPLACE INTO notification_settings (setting_key, setting_value, updated_at) 
                                      VALUES (:key, :value, CURRENT_TIMESTAMP)");
                $stmt->bindValue(':key', $key, SQLITE3_TEXT);
                $stmt->bindValue(':value', $value, SQLITE3_TEXT);
                $stmt->execute();
            }
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid input']);
        }
        break;
        
    case 'get_notifications':
        $stmt = $db2->prepare("SELECT * FROM notifications ORDER BY created_at DESC LIMIT 100");
        $res = $stmt->execute();
        $notifications = [];
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $notifications[] = $row;
        }
        echo json_encode(['success' => true, 'notifications' => $notifications]);
        break;
        
    case 'mark_read':
        $input = json_decode(file_get_contents('php://input'), true);
        if (isset($input['id'])) {
            $stmt = $db2->prepare("UPDATE notifications SET is_read = 1 WHERE id = :id");
            $stmt->bindValue(':id', $input['id'], SQLITE3_INTEGER);
            $stmt->execute();
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false]);
        }
        break;
        
    case 'mark_all_read':
        $db2->exec("UPDATE notifications SET is_read = 1");
        echo json_encode(['success' => true]);
        break;
        
    case 'clear_notifications':
        $db2->exec("DELETE FROM notifications");
        echo json_encode(['success' => true]);
        break;
        
    case 'delete_notification':
        $input = json_decode(file_get_contents('php://input'), true);
        if (isset($input['id'])) {
            $stmt = $db2->prepare("DELETE FROM notifications WHERE id = :id");
            $stmt->bindValue(':id', $input['id'], SQLITE3_INTEGER);
            $stmt->execute();
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false]);
        }
        break;
        
    case 'check_all':
        $disks = checkDisks();
        $raid = checkRaid();
        $lvm = checkLvm();
        $temps = checkTemperatures();
        $shares = checkShares();
        
        echo json_encode([
            'success' => true,
            'disks' => $disks,
            'raid' => $raid,
            'lvm' => $lvm,
            'temperatures' => $temps,
            'shares' => $shares
        ]);
        break;
        
    case 'check_disks':
        echo json_encode(['success' => true, 'data' => checkDisks()]);
        break;
        
    case 'check_raid':
        echo json_encode(['success' => true, 'data' => checkRaid()]);
        break;
        
    case 'check_lvm':
        echo json_encode(['success' => true, 'data' => checkLvm()]);
        break;
        
    case 'check_temperature':
        echo json_encode(['success' => true, 'data' => checkTemperatures()]);
        break;
        
    case 'check_shares':
        echo json_encode(['success' => true, 'data' => checkShares()]);
        break;
        
    case 'get_status_disks':
        echo json_encode(['success' => true] + checkDisks());
        break;
        
    case 'get_status_raid':
        echo json_encode(['success' => true] + checkRaid());
        break;
        
    case 'get_status_lvm':
        echo json_encode(['success' => true] + checkLvm());
        break;
        
    case 'get_status_temperature':
        echo json_encode(['success' => true] + checkTemperatures());
        break;
        
    case 'get_status_shares':
        echo json_encode(['success' => true] + checkShares());
        break;
        
    case 'acknowledge_disk':
        $input = json_decode(file_get_contents('php://input'), true);
        if (isset($input['disk_name'])) {
            $stmt = $db2->prepare("UPDATE monitored_disks SET is_new = 0, notes = 'Acknowledged at ' || CURRENT_TIMESTAMP WHERE disk_name = :name");
            $stmt->bindValue(':name', $input['disk_name'], SQLITE3_TEXT);
            $stmt->execute();
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false]);
        }
        break;
        
    case 'remove_missing_disk':
        $input = json_decode(file_get_contents('php://input'), true);
        if (isset($input['disk_name'])) {
            $stmt = $db2->prepare("DELETE FROM monitored_disks WHERE disk_name = :name AND is_active = 0");
            $stmt->bindValue(':name', $input['disk_name'], SQLITE3_TEXT);
            $stmt->execute();
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false]);
        }
        break;
    
    case 'get_schedules':
        $stmt = $db2->prepare("SELECT * FROM check_schedules ORDER BY check_type");
        $res = $stmt->execute();
        $schedules = [];
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $schedules[] = $row;
        }
        echo json_encode(['success' => true, 'schedules' => $schedules]);
        break;
        
    case 'update_schedule':
        $input = json_decode(file_get_contents('php://input'), true);
        if (isset($input['check_type'])) {
            $stmt = $db2->prepare("
                UPDATE check_schedules 
                SET enabled = :enabled, 
                    interval_seconds = :interval,
                    updated_at = CURRENT_TIMESTAMP
                WHERE check_type = :type
            ");
            $stmt->bindValue(':enabled', $input['enabled'] ?? 1, SQLITE3_INTEGER);
            $stmt->bindValue(':interval', $input['interval_seconds'] ?? 300, SQLITE3_INTEGER);
            $stmt->bindValue(':type', $input['check_type'], SQLITE3_TEXT);
            $stmt->execute();
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Missing check_type']);
        }
        break;
    
    case 'run_check_now':
        $input = json_decode(file_get_contents('php://input'), true);
        if (isset($input['check_type'])) {
            $result = null;
            switch ($input['check_type']) {
                case 'disks':
                    $result = checkDisks();
                    break;
                case 'raid':
                    $result = checkRaid();
                    break;
                case 'lvm':
                    $result = checkLvm();
                    break;
                case 'temperature':
                    $result = checkTemperatures();
                    break;
                case 'shares':
                    $result = checkShares();
                    break;
                case 'all':
                    $result = [
                        'disks' => checkDisks(),
                        'raid' => checkRaid(),
                        'lvm' => checkLvm(),
                        'temperature' => checkTemperatures(),
                        'shares' => checkShares()
                    ];
                    break;
                default:
                    echo json_encode(['success' => false, 'error' => 'Unknown check type']);
                    exit;
            }
            echo json_encode(['success' => true, 'result' => $result]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Missing check_type']);
        }
        break;
        
    case 'get_check_history':
        $limit = $_GET['limit'] ?? 50;
        $checkType = $_GET['check_type'] ?? null;
        
        if ($checkType) {
            $stmt = $db2->prepare("SELECT * FROM check_history WHERE check_type = :type ORDER BY created_at DESC LIMIT :limit");
            $stmt->bindValue(':type', $checkType, SQLITE3_TEXT);
        } else {
            $stmt = $db2->prepare("SELECT * FROM check_history ORDER BY created_at DESC LIMIT :limit");
        }
        $stmt->bindValue(':limit', $limit, SQLITE3_INTEGER);
        $res = $stmt->execute();
        
        $history = [];
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $history[] = $row;
        }
        echo json_encode(['success' => true, 'history' => $history]);
        break;
        
    case 'update_notification_rules':
        $input = json_decode(file_get_contents('php://input'), true);
        $rules = ['notify_on_auto_check', 'notify_only_on_error', 'notification_cooldown_minutes'];
        foreach ($rules as $key) {
            if (isset($input[$key])) {
                $stmt = $db2->prepare("INSERT OR REPLACE INTO notification_settings (setting_key, setting_value, updated_at) 
                                      VALUES (:key, :value, CURRENT_TIMESTAMP)");
                $stmt->bindValue(':key', $key, SQLITE3_TEXT);
                $stmt->bindValue(':value', $input[$key], SQLITE3_TEXT);
                $stmt->execute();
            }
        }
        echo json_encode(['success' => true]);
        break;
        
    case 'test_smtp':
    $input = json_decode(file_get_contents('php://input'), true);
    $result = testSmtpConnection(
        $input['host'] ?? '',
        $input['port'] ?? 25,
        $input['username'] ?? '',
        $input['password'] ?? '',
        $input['encryption'] ?? '',
        $input['test_email'] ?? '',
        $input['from_email'] ?? '',
        $input['domain'] ?? ''
    );
    echo json_encode($result);
    break;
        
    case 'save_smtp_settings':
    $input = json_decode(file_get_contents('php://input'), true);
    $smtpKeys = ['smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 
                 'smtp_encryption', 'smtp_from_email', 'smtp_from_name', 'smtp_domain'];  
    
    foreach ($smtpKeys as $key) {
        if (isset($input[$key])) {
            $stmt = $db2->prepare("INSERT OR REPLACE INTO notification_settings (setting_key, setting_value, updated_at) 
                                  VALUES (:key, :value, CURRENT_TIMESTAMP)");
            $stmt->bindValue(':key', $key, SQLITE3_TEXT);
            $stmt->bindValue(':value', trim($input[$key]), SQLITE3_TEXT);
            $stmt->execute();
        }
    }
    echo json_encode(['success' => true]);
    break;
    
	case 'get_global_stats':
    echo json_encode(['success' => true, 'stats' => getAllStats()]);
    break;
	
    default:
        echo json_encode(['error' => 'Invalid action']);
}

}
?>