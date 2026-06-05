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

// Кеш-директория
define('ROOT_PATH_BACK', '/var/www/minib/');
$cacheDir = ROOT_PATH_BACK . '/tmp/';
if (!file_exists($cacheDir)) mkdir($cacheDir, 0755, true);

// Хранилище для предыдущих значений между запросами
$prevDataFile = $cacheDir . 'prev_metrics_data.json';
$diskCacheFile = $cacheDir . 'physical_disks_cache.json';

function getPrevData() {
    global $prevDataFile;
    if (file_exists($prevDataFile)) {
        $data = json_decode(file_get_contents($prevDataFile), true);
        if (is_array($data)) return $data;
    }
    return [];
}

function savePrevData($data) {
    global $prevDataFile;
    file_put_contents($prevDataFile, json_encode($data));
}

// Получение CPU процентов
function getCpuUsage() {
    $cacheFile = ROOT_PATH_BACK . '/tmp/cpu_usage_cache.json';
    $prevData = [];
    
    if (file_exists($cacheFile)) {
        $prevData = json_decode(file_get_contents($cacheFile), true);
        if (!is_array($prevData)) $prevData = [];
    }
    
    $stat = file_get_contents('/proc/stat');
    preg_match('/^cpu\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)/', $stat, $cpu);
    
    if (count($cpu) < 5) return 0;
    
    $total = intval($cpu[1]) + intval($cpu[2]) + intval($cpu[3]) + intval($cpu[4]);
    $idle = intval($cpu[4]);
    
    $current = [
        'total' => $total,
        'idle' => $idle,
        'time' => microtime(true)
    ];
    
    $usage = 0;
    
    if (isset($prevData['total']) && isset($prevData['idle']) && isset($prevData['time'])) {
        $totalDiff = $current['total'] - $prevData['total'];
        $idleDiff = $current['idle'] - $prevData['idle'];
        $timeDiff = $current['time'] - $prevData['time'];
        
        if ($totalDiff > 0 && $timeDiff > 0 && $timeDiff < 5) {
            $usage = (($totalDiff - $idleDiff) / $totalDiff) * 100;
            $usage = round(max(0, min(100, $usage)), 1);
        }
    }
    
    file_put_contents($cacheFile, json_encode($current));
    
    return $usage;
}

function getCpuCores() {
    $cacheFile = ROOT_PATH_BACK . '/tmp/cpu_cores_cache.json';
    $prevCores = [];
    
    if (file_exists($cacheFile)) {
        $prevCores = json_decode(file_get_contents($cacheFile), true);
        if (!is_array($prevCores)) $prevCores = [];
    }
    
    $result = [];
    $currentStats = [];
    
    $stat = file_get_contents('/proc/stat');
    $lines = explode("\n", $stat);
    
    foreach ($lines as $line) {
        if (preg_match('/^cpu(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)/', $line, $matches)) {
            $core = (int)$matches[1];
            $user = (int)$matches[2];
            $nice = (int)$matches[3];
            $system = (int)$matches[4];
            $idle = (int)$matches[5];
            $iowait = (int)$matches[6];
            $irq = (int)$matches[7];
            $softirq = (int)$matches[8];
            
            $total = $user + $nice + $system + $idle + $iowait + $irq + $softirq;
            $idleTotal = $idle + $iowait;
            
            $currentStats[$core] = [
                'total' => $total,
                'idle' => $idleTotal
            ];
            
            if (isset($prevCores[$core])) {
                $totalDiff = $total - $prevCores[$core]['total'];
                $idleDiff = $idleTotal - $prevCores[$core]['idle'];
                
                if ($totalDiff > 0) {
                    $usage = round((($totalDiff - $idleDiff) / $totalDiff) * 100, 1);
                    $result[$core] = max(0, min(100, $usage));
                } else {
                    $result[$core] = 0;
                }
            } else {
                $result[$core] = 0;
            }
        }
    }
    
    if (empty($result)) {
        return [getCpuUsage()];
    }
    
    file_put_contents($cacheFile, json_encode($currentStats));
    
    ksort($result);
    return array_values($result);
}

function getCpuTemp() {
    $temp = 'N/A';
    if (file_exists('/sys/class/thermal/thermal_zone0/temp')) {
        $temp = round(file_get_contents('/sys/class/thermal/thermal_zone0/temp') / 1000, 1) . '°C';
    }
    return $temp;
}

function getMemoryInfo() {
    $memInfo = file_get_contents('/proc/meminfo');
    preg_match('/MemTotal:\s+(\d+)/', $memInfo, $memTotal);
    preg_match('/MemAvailable:\s+(\d+)/', $memInfo, $memAvail);
    preg_match('/SwapTotal:\s+(\d+)/', $memInfo, $swapTotal);
    preg_match('/SwapFree:\s+(\d+)/', $memInfo, $swapFree);
    
    $totalMem = round($memTotal[1] / 1024);
    $availableMem = round($memAvail[1] / 1024);
    $usedMem = $totalMem - $availableMem;
    $memPercent = round(($usedMem / $totalMem) * 100, 1);
    
    $swapTotalMem = round($swapTotal[1] / 1024);
    $swapUsedMem = $swapTotalMem > 0 ? $swapTotalMem - round($swapFree[1] / 1024) : 0;
    
    return [
        'total' => $totalMem,
        'used' => $usedMem,
        'available' => $availableMem,
        'percent' => $memPercent,
        'swap_total' => $swapTotalMem,
        'swap_used' => $swapUsedMem,
        'swap_percent' => $swapTotalMem > 0 ? round(($swapUsedMem / $swapTotalMem) * 100, 1) : 0
    ];
}

function getLoadAverage() {
    return sys_getloadavg();
}

// Получение списка дисков с IO статистикой
function getDisksWithIO() {
    $prevData = getPrevData();
    $prevDiskStats = $prevData['disk_stats'] ?? null;
    $currentTime = microtime(true);
    
    $disks = [];
    
    $lsblk = shell_exec("lsblk -o NAME,TYPE -d -n 2>/dev/null | grep -E 'disk' | grep -vE 'mtdblock|loop|ram' | awk '{print $1}'");
    $diskNames = array_filter(explode("\n", trim($lsblk)));
    
    if (empty($diskNames)) {
        return [];
    }
    
    $currentStats = [];
    $diskStats = file_get_contents('/proc/diskstats');
    $lines = explode("\n", trim($diskStats));
    
    foreach ($lines as $line) {
        $data = preg_split('/\s+/', trim($line));
        if (count($data) >= 14) {
            $diskName = $data[2];
            if (in_array($diskName, $diskNames)) {
                $readsCompleted = intval($data[3]);    // чтения завершены
                $sectorsRead = intval($data[5]);       // сектора прочитаны
                $writesCompleted = intval($data[7]);   // записи завершены  
                $sectorsWritten = intval($data[9]);    // сектора записаны
                $ioInProgress = intval($data[11]);     // I/O в процессе
                $ioTime = intval($data[12]);           // время на I/O (мс)
                
                $currentStats[$diskName] = [
                    'read_bytes' => $sectorsRead * 512,
                    'write_bytes' => $sectorsWritten * 512,
                    'read_ops' => $readsCompleted,
                    'write_ops' => $writesCompleted,
                    'io_in_progress' => $ioInProgress,
                    'io_time' => $ioTime,
                    'time' => $currentTime
                ];
            }
        }
    }
    
    if ($prevDiskStats !== null) {
        foreach ($currentStats as $diskName => $stats) {
            if (isset($prevDiskStats[$diskName])) {
                $timeDiff = $stats['time'] - $prevDiskStats[$diskName]['time'];
                if ($timeDiff > 0 && $timeDiff < 5) {
                    $readBytesDiff = max(0, $stats['read_bytes'] - $prevDiskStats[$diskName]['read_bytes']);
                    $writeBytesDiff = max(0, $stats['write_bytes'] - $prevDiskStats[$diskName]['write_bytes']);
                    
                    $readSpeed = round(($readBytesDiff / $timeDiff) / 1024 / 1024, 2);
                    $writeSpeed = round(($writeBytesDiff / $timeDiff) / 1024 / 1024, 2);
                    
                    $queueLength = min(20, $stats['io_in_progress']);
                    
                    // Расчет IOPS
                    $readOpsDiff = max(0, $stats['read_ops'] - ($prevDiskStats[$diskName]['read_ops'] ?? 0));
                    $writeOpsDiff = max(0, $stats['write_ops'] - ($prevDiskStats[$diskName]['write_ops'] ?? 0));
                    
                    $disks[$diskName] = [
                        'read_mb_s' => $readSpeed,
                        'write_mb_s' => $writeSpeed,
                        'queue_length' => $queueLength,
                        'total_read_mb' => round($stats['read_bytes'] / 1024 / 1024, 2),
                        'total_write_mb' => round($stats['write_bytes'] / 1024 / 1024, 2),
                        'read_iops' => round($readOpsDiff / $timeDiff, 0),
                        'write_iops' => round($writeOpsDiff / $timeDiff, 0)
                    ];
                } else {
                    $disks[$diskName] = [
                        'read_mb_s' => 0,
                        'write_mb_s' => 0,
                        'queue_length' => min(20, $stats['io_in_progress']),
                        'total_read_mb' => round($stats['read_bytes'] / 1024 / 1024, 2),
                        'total_write_mb' => round($stats['write_bytes'] / 1024 / 1024, 2),
                        'read_iops' => 0,
                        'write_iops' => 0
                    ];
                }
            } else {
                $disks[$diskName] = [
                    'read_mb_s' => 0,
                    'write_mb_s' => 0,
                    'queue_length' => min(20, $stats['io_in_progress']),
                    'total_read_mb' => round($stats['read_bytes'] / 1024 / 1024, 2),
                    'total_write_mb' => round($stats['write_bytes'] / 1024 / 1024, 2),
                    'read_iops' => 0,
                    'write_iops' => 0
                ];
            }
        }
    } else {
        foreach ($currentStats as $diskName => $stats) {
            $disks[$diskName] = [
                'read_mb_s' => 0,
                'write_mb_s' => 0,
                'queue_length' => min(20, $stats['io_in_progress']),
                'total_read_mb' => round($stats['read_bytes'] / 1024 / 1024, 2),
                'total_write_mb' => round($stats['write_bytes'] / 1024 / 1024, 2),
                'read_iops' => 0,
                'write_iops' => 0
            ];
        }
    }
    
    $prevData['disk_stats'] = $currentStats;
    savePrevData($prevData);
    
    return $disks;
}

// Получение сетевой статистики с расчетом скорости
function getNetworkTraffic() {
    $prevData = getPrevData();
    $prevNetStats = $prevData['net_stats'] ?? null;
    $currentTime = microtime(true);
    
    $result = [];
    $interfaces = [];
    
    // Получаем сетевые интерфейсы
    $netDevices = scandir('/sys/class/net/');
    foreach ($netDevices as $iface) {
        if ($iface == '.' || $iface == '..' || $iface == 'lo') continue;
        $interfaces[] = $iface;
    }
    
    $currentStats = [];
    
    foreach ($interfaces as $iface) {
        $rx = @file_get_contents("/sys/class/net/" . $iface . "/statistics/rx_bytes");
        $tx = @file_get_contents("/sys/class/net/" . $iface . "/statistics/tx_bytes");
        
        if ($rx !== false && $tx !== false) {
            $currentStats[$iface] = [
                'rx_bytes' => floatval($rx),
                'tx_bytes' => floatval($tx),
                'time' => $currentTime
            ];
        }
    }
    
    // Рассчитываем скорости
    if ($prevNetStats !== null) {
        foreach ($currentStats as $iface => $stats) {
            if (isset($prevNetStats[$iface])) {
                $timeDiff = $stats['time'] - $prevNetStats[$iface]['time'];
                if ($timeDiff > 0 && $timeDiff < 5) {
                    $rxDiff = max(0, $stats['rx_bytes'] - $prevNetStats[$iface]['rx_bytes']);
                    $txDiff = max(0, $stats['tx_bytes'] - $prevNetStats[$iface]['tx_bytes']);
                    
                    $rxSpeed = round(($rxDiff / $timeDiff) / 1024 / 1024, 2);
                    $txSpeed = round(($txDiff / $timeDiff) / 1024 / 1024, 2);
                    
                    $result[$iface] = [
                        'rx_mb' => round($stats['rx_bytes'] / 1024 / 1024, 2),
                        'tx_mb' => round($stats['tx_bytes'] / 1024 / 1024, 2),
                        'rx_mb_s' => $rxSpeed,
                        'tx_mb_s' => $txSpeed
                    ];
                } else {
                    $result[$iface] = [
                        'rx_mb' => round($stats['rx_bytes'] / 1024 / 1024, 2),
                        'tx_mb' => round($stats['tx_bytes'] / 1024 / 1024, 2),
                        'rx_mb_s' => 0,
                        'tx_mb_s' => 0
                    ];
                }
            } else {
                $result[$iface] = [
                    'rx_mb' => round($stats['rx_bytes'] / 1024 / 1024, 2),
                    'tx_mb' => round($stats['tx_bytes'] / 1024 / 1024, 2),
                    'rx_mb_s' => 0,
                    'tx_mb_s' => 0
                ];
            }
        }
    } else {
        foreach ($currentStats as $iface => $stats) {
            $result[$iface] = [
                'rx_mb' => round($stats['rx_bytes'] / 1024 / 1024, 2),
                'tx_mb' => round($stats['tx_bytes'] / 1024 / 1024, 2),
                'rx_mb_s' => 0,
                'tx_mb_s' => 0
            ];
        }
    }
    
    $prevData['net_stats'] = $currentStats;
    savePrevData($prevData);
    
    return $result;
}

function getNetworkInterfaces() {
    $interfaces = [];
    $netDevices = scandir('/sys/class/net/');
    
    foreach ($netDevices as $iface) {
        if ($iface == '.' || $iface == '..' || $iface == 'lo') continue;
        
        $rx = @file_get_contents("/sys/class/net/" . $iface . "/statistics/rx_bytes");
        $tx = @file_get_contents("/sys/class/net/" . $iface . "/statistics/tx_bytes");
        $mac = @file_get_contents("/sys/class/net/" . $iface . "/address");
        
        if ($rx !== false && $tx !== false) {
            $interfaces[] = [
                'interface' => $iface,
                'rx_mb' => round(floatval($rx) / 1024 / 1024, 2),
                'tx_mb' => round(floatval($tx) / 1024 / 1024, 2),
                'mac' => trim($mac),
                'ip' => getIpAddress($iface)
            ];
        }
    }
    
    return $interfaces;
}

function getIpAddress($interface) {
    $ip = shell_exec("ip -4 addr show " . $interface . " 2>/dev/null | grep -oP '(?<=inet\\s)\\d+(\\.\\d+){3}' | head -1");
    return trim($ip) ?: 'N/A';
}

// Получение физических ддисков с кешированием (обновл раз в 30 сек)
function getPhysicalDisks() {
    global $diskCacheFile;
    
    if (file_exists($diskCacheFile)) {
        $cacheTime = filemtime($diskCacheFile);
        if (time() - $cacheTime < 30) {
            $cached = json_decode(file_get_contents($diskCacheFile), true);
            if ($cached && is_array($cached)) {
                return $cached;
            }
        }
    }
    
    $disks = [];
    
    $lsblkJson = shell_exec("lsblk -J -d -o NAME,TYPE,SIZE,MODEL 2>/dev/null | grep -v 'loop'");
    
    if ($lsblkJson) {
        $data = json_decode($lsblkJson, true);
        if ($data && isset($data['blockdevices'])) {
            foreach ($data['blockdevices'] as $disk) {
                if (strpos($disk['name'] ?? '', 'loop') === 0) continue;
                if (($disk['type'] ?? '') !== 'disk') continue;
                
                $diskName = $disk['name'];
                $diskSize = $disk['size'] ?? 'Unknown';
                $diskModel = $disk['model'] ?? $diskName;
                
                $device = '/dev/' . $diskName;
                $smart = getSmartInfo($device);
                $partitions = getDiskPartitions($diskName);
                $totalUsage = calculateDiskUsage($partitions);
                
                if (empty($partitions)) {
                    $fsCheck = shell_exec("lsblk -o FSTYPE -n /dev/" . $diskName . " 2>/dev/null | head -1");
                    if ($fsCheck && trim($fsCheck) && trim($fsCheck) !== '-') {
                        $mountPoint = trim(shell_exec("lsblk -o MOUNTPOINT -n /dev/" . $diskName . " 2>/dev/null | head -1"));
                        $partitions[] = [
                            'name' => $diskName,
                            'size' => $diskSize,
                            'mount' => ($mountPoint && $mountPoint !== '-') ? $mountPoint : '',
                            'fstype' => trim($fsCheck),
                            'is_swap' => false,
                            'used' => '0',
                            'avail' => '0',
                            'percent' => 0
                        ];
                        
                        if ($mountPoint && $mountPoint !== '-' && file_exists($mountPoint)) {
                            $df = shell_exec("df -h " . escapeshellarg($mountPoint) . " 2>/dev/null | tail -1");
                            if ($df && trim($df)) {
                                $dfParts = preg_split('/\s+/', trim($df));
                                if (count($dfParts) >= 6) {
                                    $partitions[0]['size'] = $dfParts[1];
                                    $partitions[0]['used'] = $dfParts[2];
                                    $partitions[0]['avail'] = $dfParts[3];
                                    $partitions[0]['percent'] = intval(rtrim($dfParts[4], '%'));
                                }
                            }
                        }
                        
                        $totalUsage = calculateDiskUsage($partitions);
                    }
                }
                
                $disks[] = [
                    'name' => $diskName,
                    'model' => trim($diskModel),
                    'size' => $diskSize,
                    'smart' => $smart,
                    'partitions' => $partitions,
                    'total_used' => $totalUsage['used'],
                    'total_size' => $totalUsage['size'],
                    'total_percent' => $totalUsage['percent']
                ];
            }
        }
    } else {
        $lsblk = shell_exec("lsblk -o NAME,TYPE,SIZE,MODEL -d -n 2>/dev/null | grep -E 'disk' | grep -vE 'mtdblock|loop|ram'");
        $diskLines = array_filter(explode("\n", trim($lsblk)));
        
        foreach ($diskLines as $line) {
            $parts = preg_split('/\s+/', trim($line));
            if (count($parts) < 3) continue;
            
            $diskName = $parts[0];
            $diskSize = $parts[2];
            $diskModel = count($parts) > 3 ? implode(' ', array_slice($parts, 3)) : $diskName;
            $device = '/dev/' . $diskName;
            
            $smart = getSmartInfo($device);
            $partitions = getDiskPartitions($diskName);
            $totalUsage = calculateDiskUsage($partitions);
            
            $disks[] = [
                'name' => $diskName,
                'model' => trim($diskModel),
                'size' => $diskSize,
                'smart' => $smart,
                'partitions' => $partitions,
                'total_used' => $totalUsage['used'],
                'total_size' => $totalUsage['size'],
                'total_percent' => $totalUsage['percent']
            ];
        }
    }
    
    file_put_contents($diskCacheFile, json_encode($disks));
    
    return $disks;
}

function getSmartInfo($device) {
    if (!file_exists($device)) {
        return ['status' => 'PASSED', 'temp' => 'N/A', 'health' => 'OK', 'bad_sectors' => 0];
    }
    
    $smart = [
        'status' => 'PASSED', 
        'temp' => 'N/A', 
        'health' => 'OK', 
        'bad_sectors' => 0,
        'realloc_sectors' => 0,
        'pending_sectors' => 0,
        'offline_uncorrectable' => 0
    ];
    
    if (strpos($device, '/dev/sd') === 0 || strpos($device, '/dev/nvme') === 0) {
        // Reallocated_Sector_Ct
        $realloc = shell_exec("sudo smartctl -A " . $device . " 2>/dev/null | grep -E 'Reallocated_Sector_Ct|Reallocated_Event_Count' | awk '{print $10}'");
        if ($realloc && intval($realloc) > 0) {
            $smart['bad_sectors'] = intval($realloc);
            $smart['realloc_sectors'] = intval($realloc);
            $smart['status'] = 'FAILED';
            $smart['health'] = "Realloc: " . trim($realloc);
        }
        
        // Current_Pending_Sector - ожидающие переназначения
        $pending = shell_exec("sudo smartctl -A " . $device . " 2>/dev/null | grep 'Current_Pending_Sector' | awk '{print $10}'");
        if ($pending && intval($pending) > 0) {
            $smart['bad_sectors'] += intval($pending);
            $smart['pending_sectors'] = intval($pending);
            $smart['status'] = 'FAILED';
            $smart['health'] = "Pending: " . trim($pending);
        }
        
        // Offline_Uncorrectable
        $offline = shell_exec("sudo smartctl -A " . $device . " 2>/dev/null | grep 'Offline_Uncorrectable' | awk '{print $10}'");
        if ($offline && intval($offline) > 0) {
            $smart['bad_sectors'] += intval($offline);
            $smart['offline_uncorrectable'] = intval($offline);
            $smart['status'] = 'FAILED';
        }
        
        // Если нет плохих секторов - PASSED
        if ($smart['bad_sectors'] == 0) {
            // Дополнительная проверка общей health
            $healthCheck = shell_exec("sudo smartctl -H " . $device . " 2>/dev/null | grep 'SMART overall-health'");
            if ($healthCheck && strpos($healthCheck, 'FAILED') !== false) {
                $smart['status'] = 'FAILED';
                $smart['health'] = 'SMART Health FAILED';
            } else {
                $smart['status'] = 'PASSED';
                $smart['health'] = 'OK';
            }
        }
        
        // Температура
        $temp = shell_exec("sudo smartctl -A " . $device . " 2>/dev/null | grep -E 'Temperature_Celsius|Temperature:|Current Drive Temperature' | head -1");
        if ($temp) {
            if (preg_match('/(\d+)/', $temp, $matches)) {
                $tempVal = intval($matches[1]);
                if ($tempVal > 0 && $tempVal < 100) {
                    $smart['temp'] = $tempVal . '°C';
                }
            }
        }
    }
    
    return $smart;
}

function getDiskPartitions($diskName) {
    $partitions = [];
    
    $lsblk = shell_exec("lsblk -o NAME,TYPE,MOUNTPOINT,SIZE,FSTYPE -n /dev/" . $diskName . " 2>/dev/null | grep -E 'part'");
    $partLines = array_filter(explode("\n", trim($lsblk)));
    
    if (empty($partLines)) {
        $diskInfo = shell_exec("lsblk -o NAME,TYPE,MOUNTPOINT,SIZE,FSTYPE -n /dev/" . $diskName . " 2>/dev/null | head -1");
        if ($diskInfo && trim($diskInfo)) {
            $parts = preg_split('/\s+/', trim($diskInfo));
            if (count($parts) >= 3) {
                $fsType = $parts[3] ?? '';
                $mountPoint = $parts[2] ?? '';
                
                if (!empty($fsType) && $fsType !== '-') {
                    $partition = [
                        'name' => $diskName,
                        'size' => $parts[1] ?? '0',
                        'mount' => ($mountPoint && $mountPoint !== '-') ? $mountPoint : '',
                        'fstype' => $fsType,
                        'is_swap' => ($fsType === 'swap'),
                        'used' => '0',
                        'avail' => '0',
                        'percent' => 0
                    ];
                    
                    if ($partition['mount'] && file_exists($partition['mount'])) {
                        $df = shell_exec("df -h " . escapeshellarg($partition['mount']) . " 2>/dev/null | tail -1");
                        if ($df && trim($df)) {
                            $dfParts = preg_split('/\s+/', trim($df));
                            if (count($dfParts) >= 6) {
                                $partition['size'] = $dfParts[1];
                                $partition['used'] = $dfParts[2];
                                $partition['avail'] = $dfParts[3];
                                $partition['percent'] = intval(rtrim($dfParts[4], '%'));
                            }
                        }
                    } elseif ($partition['mount'] === '' && !empty($fsType)) {
                        $df = shell_exec("df -h /dev/" . $diskName . " 2>/dev/null | tail -1");
                        if ($df && trim($df)) {
                            $dfParts = preg_split('/\s+/', trim($df));
                            if (count($dfParts) >= 6) {
                                $partition['size'] = $dfParts[1];
                                $partition['used'] = $dfParts[2];
                                $partition['avail'] = $dfParts[3];
                                $partition['percent'] = intval(rtrim($dfParts[4], '%'));
                            }
                        }
                    }
                    
                    $partitions[] = $partition;
                    return $partitions;
                }
            }
        }
        return $partitions;
    }
    
    // Обработка обычных разделов
    foreach ($partLines as $line) {
        $parts = preg_split('/\s+/', trim($line));
        if (count($parts) < 3) continue;
        
        $partName = $parts[0];
        $mountPoint = $parts[2] ?? '';
        $partSize = $parts[3] ?? '0';
        $fsType = $parts[4] ?? 'unknown';
        
        $isSwap = ($fsType === 'swap' || strpos($mountPoint, 'SWAP') !== false);
        
        $partition = [
            'name' => $partName,
            'size' => $partSize,
            'mount' => ($mountPoint && $mountPoint !== '-') ? $mountPoint : '',
            'fstype' => $fsType,
            'is_swap' => $isSwap,
            'used' => '0',
            'avail' => '0',
            'percent' => 0
        ];
        
        if (!$isSwap && $mountPoint && $mountPoint !== '-' && !empty($mountPoint) && file_exists($mountPoint)) {
            $df = shell_exec("df -h " . escapeshellarg($mountPoint) . " 2>/dev/null | tail -1");
            if ($df && trim($df)) {
                $dfParts = preg_split('/\s+/', trim($df));
                if (count($dfParts) >= 6) {
                    $partition['size'] = $dfParts[1];
                    $partition['used'] = $dfParts[2];
                    $partition['avail'] = $dfParts[3];
                    $partition['percent'] = intval(rtrim($dfParts[4], '%'));
                }
            }
        } elseif ($isSwap) {
            $partition['mount'] = '[SWAP]';
            $swapInfo = shell_exec("swapon --show=size,used 2>/dev/null | grep '/dev/" . $partName . "'");
            if ($swapInfo) {
                $swapParts = preg_split('/\s+/', trim($swapInfo));
                if (count($swapParts) >= 2) {
                    $partition['size'] = round($swapParts[0] / 1024 / 1024, 1) . 'G';
                    $partition['used'] = round($swapParts[1] / 1024 / 1024, 1) . 'G';
                    $partition['percent'] = $swapParts[0] > 0 ? round(($swapParts[1] / $swapParts[0]) * 100, 1) : 0;
                }
            }
        }
        
        $partitions[] = $partition;
    }
    
    return $partitions;
}

function calculateDiskUsage($partitions) {
    $totalSize = 0;
    $totalUsed = 0;
    
    foreach ($partitions as $part) {
        if ($part['is_swap']) continue;
        
        $sizeBytes = convertToBytes($part['size']);
        $usedBytes = convertToBytes($part['used']);
        
        if ($sizeBytes > 0) {
            $totalSize += $sizeBytes;
            $totalUsed += $usedBytes;
        }
    }
    
    $percent = $totalSize > 0 ? round(($totalUsed / $totalSize) * 100, 1) : 0;
    
    return [
        'size' => formatBytes($totalSize),
        'used' => formatBytes($totalUsed),
        'percent' => $percent
    ];
}

function convertToBytes($size) {
    if (empty($size) || $size === '0') return 0;
    
    $units = ['B' => 1, 'K' => 1024, 'M' => 1048576, 'G' => 1073741824, 'T' => 1099511627776];
    preg_match('/(\d+(?:\.\d+)?)\s*([KMGT]?B?)/i', trim($size), $matches);
    
    if (count($matches) >= 2) {
        $number = floatval($matches[1]);
        $unit = strtoupper($matches[2]);
        $unit = substr($unit, 0, 1);
        return $number * ($units[$unit] ?? 1);
    }
    return 0;
}

function formatBytes($bytes) {
    if ($bytes <= 0) return '0B';
    if ($bytes >= 1073741824) return round($bytes / 1073741824, 1) . 'G';
    if ($bytes >= 1048576) return round($bytes / 1048576, 1) . 'M';
    if ($bytes >= 1024) return round($bytes / 1024, 1) . 'K';
    return $bytes . 'B';
}


function getCurrentVersion() {
    $config_path = ROOT_PATH . '/config.php';
    $current_version = 'Unknown';
    $type_pro = 'Unknown';
    
    if (file_exists($config_path)) {
        $config_content = file_get_contents($config_path);
        if (preg_match('/\$version\s*=\s*"([^"]+)"/', $config_content, $matches)) {
            $current_version = $matches[1];
        }
        if (preg_match('/\$type_pro\s*=\s*"([^"]+)"/', $config_content, $matches)) {
            $type_pro = $matches[1];
        }
    }
    
    return [
        'version' => $current_version,
        'type_pro' => $type_pro
    ];
}

function getSystemInfo() {
	$versionInfo = getCurrentVersion();
	$uptime = shell_exec("cat /proc/uptime | awk '{printf \"%d days %02d:%02d:%02d\", int($1/86400), int(($1%86400)/3600), int(($1%3600)/60), int($1%60)}'");
    return [
        'uptime' => trim($uptime),
        'hostname' => trim(shell_exec("hostname")),
        'kernel' => trim(shell_exec("uname -r")),
        'processes' => intval(trim(shell_exec("ps aux 2>/dev/null | wc -l"))),
		'current_version' => trim($versionInfo['version'])
    ];
}

// API роутинг
$action = $_GET['action'] ?? '';

if ($action === 'metrics') {
    $data = [
        'cpu' => getCpuUsage(),
        'cpu_cores' => getCpuCores(),
        'cpu_temp' => getCpuTemp(),
        'memory' => getMemoryInfo(),
        'load' => getLoadAverage(),
        'disks_io' => getDisksWithIO(),
        'disks' => getPhysicalDisks(),
        'network_traffic' => getNetworkTraffic(),
        'network_interfaces' => getNetworkInterfaces(),
        'system' => getSystemInfo(),
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    echo json_encode($data);
    
} else {
    echo json_encode(['error' => 'Invalid action']);
}
?>