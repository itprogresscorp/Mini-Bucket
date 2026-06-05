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

// ========== ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ ==========
function getServiceStatus($serviceName) {
    $status = shell_exec("systemctl is-active " . $serviceName . " 2>/dev/null");
    return trim($status) === 'active' ? 'active' : 'inactive';
}

function getServiceEnabled($serviceName) {
    $enabled = shell_exec("systemctl is-enabled " . $serviceName . " 2>/dev/null");
    $enabledStatus = trim($enabled);
    return ($enabledStatus === 'enabled' || $enabledStatus === 'static');
}

function getServicePid($serviceName) {
    $pid = shell_exec("pidof " . $serviceName . " 2>/dev/null");
    return trim($pid) ?: '';
}

function getServiceVersion($serviceName) {
    $versionMap = [
        'nfs-kernel-server' => 'nfsstat -s 2>/dev/null | head -1',
        'smbd' => 'smbd -V 2>/dev/null',
        'vsftpd' => 'vsftpd -v 2>&1',
        'apache2' => 'apache2 -v 2>/dev/null',
        'ssh' => 'sshd -V 2>&1',
        'ntp' => 'ntpd --version 2>&1'
    ];
    
    $cmd = $versionMap[$serviceName] ?? "systemctl show $serviceName --property=FragmentPath 2>/dev/null";
    $output = shell_exec($cmd);
    
    if ($serviceName === 'smbd' && preg_match('/Version ([0-9.]+)/', $output, $matches)) {
        return $matches[1];
    }
    if ($serviceName === 'apache2' && preg_match('/Apache\/([0-9.]+)/', $output, $matches)) {
        return $matches[1];
    }
    if ($serviceName === 'vsftpd' && preg_match('/vsftpd: version ([0-9.]+)/', $output, $matches)) {
        return $matches[1];
    }
    
    return 'unknown';
}

function startService($serviceName) {
    shell_exec("sudo systemctl start $serviceName 2>&1");
    sleep(1);
    return getServiceStatus($serviceName) === 'active';
}

function stopService($serviceName) {
    shell_exec("sudo systemctl stop $serviceName 2>&1");
    sleep(1);
    return getServiceStatus($serviceName) !== 'active';
}

function restartService($serviceName) {
    shell_exec("sudo systemctl restart $serviceName 2>&1");
    sleep(2);
    return getServiceStatus($serviceName) === 'active';
}

function enableService($serviceName) {
    shell_exec("sudo systemctl enable $serviceName 2>&1");
    return getServiceEnabled($serviceName);
}

function disableService($serviceName) {
    shell_exec("sudo systemctl disable $serviceName 2>&1");
    return !getServiceEnabled($serviceName);
}

function getAllServicesStatus() {
    $services = [
        'nfs' => 'nfs-kernel-server',
        'smb' => 'smbd',
        'rsync' => 'rsync',
        'ftp' => 'vsftpd',
        'ssh' => 'ssh',
        'apache2' => 'apache2',
        'ufw' => 'ufw',
        'ntp' => 'ntp'
    ];
    
    $result = [];
    foreach ($services as $key => $service) {
        $result[$key] = [
            'running' => getServiceStatus($service) === 'active',
            'enabled' => getServiceEnabled($service),
            'pid' => getServicePid($service),
            'version' => getServiceVersion($service)
        ];
    }
    
    return $result;
}

// ========== POWER MANAGEMENT ==========
function systemReboot() {
    shell_exec('sudo /sbin/reboot > /dev/null 2>&1 &');
    return true;
}

function systemShutdown() {
    shell_exec('sudo /sbin/poweroff > /dev/null 2>&1 &');
    return true;
}

function getSystemUptime() {
    if (!file_exists('/proc/uptime')) return 'Unknown';
    $uptimeSeconds = floatval(file_get_contents('/proc/uptime'));
    $days = floor($uptimeSeconds / 86400);
    $hours = floor(($uptimeSeconds % 86400) / 3600);
    $minutes = floor(($uptimeSeconds % 3600) / 60);
    return sprintf("%d days, %d hours, %d minutes", $days, $hours, $minutes);
}

function getLoadAverage() {
    return sys_getloadavg();
}

function getMemoryInfo() {
    $info = ['total' => 0, 'available' => 0, 'used' => 0, 'usage_percent' => 0];
    if (file_exists('/proc/meminfo')) {
        $memContent = file_get_contents('/proc/meminfo');
        preg_match('/MemTotal:\s+(\d+)/', $memContent, $memTotal);
        preg_match('/MemAvailable:\s+(\d+)/', $memContent, $memAvailable);
        $info['total'] = round($memTotal[1] / 1024 / 1024, 2);
        $info['available'] = round($memAvailable[1] / 1024 / 1024, 2);
        $info['used'] = round($info['total'] - $info['available'], 2);
        $info['usage_percent'] = round(($info['used'] / $info['total']) * 100);
    }
    return $info;
}

function getDiskInfo() {
    $info = ['total' => 0, 'free' => 0, 'used' => 0, 'usage_percent' => 0];
    $diskTotal = disk_total_space('/');
    $diskFree = disk_free_space('/');
    if ($diskTotal !== false) {
        $info['total'] = round($diskTotal / 1024 / 1024 / 1024, 2);
        $info['free'] = round($diskFree / 1024 / 1024 / 1024, 2);
        $info['used'] = round($info['total'] - $info['free'], 2);
        $info['usage_percent'] = round(($info['used'] / $info['total']) * 100);
    }
    return $info;
}

function getCpuInfo() {
    $cpuInfo = '';
    $cpuCores = 1;
    if (file_exists('/proc/cpuinfo')) {
        $cpuInfoContent = file_get_contents('/proc/cpuinfo');
        if (preg_match('/model name\s+:\s+(.+)/', $cpuInfoContent, $matches)) {
            $cpuInfo = $matches[1];
        } elseif (preg_match('/Processor\s+:\s+(.+)/', $cpuInfoContent, $matches)) {
            $cpuInfo = $matches[1];
        }
        $cpuCores = substr_count($cpuInfoContent, 'processor');
        if ($cpuCores === 0) $cpuCores = 1;
    }
    return ['model' => $cpuInfo, 'cores' => $cpuCores];
}

function getOsDetails() {
    $osDetails = php_uname('s') . ' ' . php_uname('r');
    if (file_exists('/etc/os-release')) {
        $osRelease = parse_ini_file('/etc/os-release');
        if (isset($osRelease['PRETTY_NAME'])) {
            $osDetails = $osRelease['PRETTY_NAME'];
        }
    }
    return $osDetails;
}

// ========== TIMEZONE MANAGEMENT ==========
function getCurrentTimezone() {
    if (file_exists('/etc/timezone')) {
        return trim(file_get_contents('/etc/timezone'));
    }
    return 'UTC';
}

function setTimezone($timezone) {
    $validTimezones = timezone_identifiers_list();
    if (!in_array($timezone, $validTimezones)) {
        return false;
    }
    
    file_put_contents('/tmp/timezone', $timezone . "\n");
    exec("sudo cp /tmp/timezone /etc/timezone 2>&1");
    exec("sudo ln -sf /usr/share/zoneinfo/" . escapeshellarg($timezone) . " /etc/localtime 2>&1", $output, $return);
    return $return === 0;
}

function getNtpStatus() {
    return getServiceStatus('ntp') === 'active';
}

function setNtpEnabled($enabled) {
    if ($enabled) {
        shell_exec("sudo systemctl enable ntp 2>&1");
        shell_exec("sudo systemctl start ntp 2>&1");
    } else {
        shell_exec("sudo systemctl stop ntp 2>&1");
        shell_exec("sudo systemctl disable ntp 2>&1");
    }
    return getNtpStatus() === $enabled;
}

function setManualDateTime($date, $time) {
    $datetime = $date . ' ' . $time;
    $cmd = "sudo date -s '" . escapeshellcmd($datetime) . "' 2>&1";
    exec($cmd, $output, $return);
    exec("sudo hwclock --systohc 2>&1");
    return $return === 0;
}

function getCurrentDateTime() {
    return date('Y-m-d H:i:s');
}

function getAllTimezones() {
    $timezones = [];
    foreach (timezone_identifiers_list() as $tz) {
        $parts = explode('/', $tz, 2);
        if (count($parts) == 2) {
            if (!isset($timezones[$parts[0]])) $timezones[$parts[0]] = [];
            $timezones[$parts[0]][] = $tz;
        }
    }
    ksort($timezones);
    return $timezones;
}

// ========== HOSTNAME MANAGEMENT ==========
function getCurrentHostname() {
    return trim(file_get_contents('/etc/hostname'));
}

function setHostname($hostname) {
    $hostname = preg_replace('/[^a-zA-Z0-9\.\-]/', '', $hostname);
    if (empty($hostname)) return false;
    
    exec('sudo hostnamectl set-hostname ' . escapeshellarg($hostname));
    
    $newHosts = "127.0.0.1\tlocalhost\n";
    $newHosts .= "127.0.1.1\t" . $hostname . "\n";
    $newHosts .= "\n";
    $newHosts .= "# The following lines are desirable for IPv6 capable hosts\n";
    $newHosts .= "::1\tlocalhost ip6-localhost ip6-loopback\n";
    $newHosts .= "fe00::0\tip6-localnet\n";
    $newHosts .= "ff00::0\tip6-mcastprefix\n";
    $newHosts .= "ff02::1\tip6-allnodes\n";
    $newHosts .= "ff02::2\tip6-allrouters\n";
    
    $tempFile = '/tmp/hosts_' . uniqid();
    file_put_contents($tempFile, $newHosts);
    exec('sudo cp ' . $tempFile . ' /etc/hosts');
    unlink($tempFile);
    
    return true;
}

// ========== NETWORK MANAGEMENT ==========
function getNetworkInterfaces() {
    $interfaces = [];
    $output = shell_exec("ip -br link show 2>/dev/null | grep -v LOOPBACK | awk '{print $1}'");
    if ($output) {
        $ifaces = explode("\n", trim($output));
        foreach ($ifaces as $iface) {
            if (!empty($iface) && $iface !== 'lo') {
                $ip = shell_exec("ip -4 addr show $iface 2>/dev/null | grep -oP '(?<=inet\s)\d+(\.\d+){3}' | head -1");
                $interfaces[$iface] = [
                    'ip' => trim($ip) ?: 'No IP',
                    'method' => 'dhcp'
                ];
                
                if (file_exists('/etc/network/interfaces')) {
                    $interfacesContent = file_get_contents('/etc/network/interfaces');
                    if (preg_match('/iface ' . $iface . ' inet static/', $interfacesContent)) {
                        $interfaces[$iface]['method'] = 'static';
                    }
                }
            }
        }
    }
    return $interfaces;
}

function setNetworkConfig($interface, $ipMethod, $ipAddress = '', $netmask = '', $gateway = '', $dns = '') {
    $interfacesFile = '/etc/network/interfaces';
    
    if (!file_exists($interfacesFile)) {
        return false;
    }
    
    $content = file_get_contents($interfacesFile);
    
    $pattern = '/\n?auto\s+' . preg_quote($interface, '/') . '\s*\niface\s+' . preg_quote($interface, '/') . '\s+[^\n]*\n(\s+[^\n]*\n)*/';
    $content = preg_replace($pattern, "\n", $content);
    
    $pattern2 = '/\n?auto\s+' . preg_quote($interface, '/') . '\s*\n/';
    $content = preg_replace($pattern2, "\n", $content);
    
    $newConfig = "";
    
    if ($ipMethod === 'dhcp') {
        $newConfig = "auto " . $interface . "\n";
        $newConfig .= "iface " . $interface . " inet dhcp\n";
    } else {
        $newConfig = "auto " . $interface . "\n";
        $newConfig .= "iface " . $interface . " inet static\n";
        $newConfig .= "    address " . $ipAddress . "\n";
        $newConfig .= "    netmask " . $netmask . "\n";
        $newConfig .= "    gateway " . $gateway . "\n";
        
        if (!empty($dns)) {
            $dnsServers = explode(',', $dns);
            $firstDns = trim($dnsServers[0]);
            if (!empty($firstDns)) {
                $newConfig .= "    dns-nameservers " . $firstDns . "\n";
            }
        }
    }
    
    $content = rtrim($content) . "\n\n" . $newConfig;
    
    $content = preg_replace("/\n{3,}/", "\n\n", $content);
    
    $tempFile = '/tmp/interfaces_' . uniqid();
    file_put_contents($tempFile, $content);
    
    exec("sudo cp " . $tempFile . " " . $interfacesFile . " 2>&1", $output, $return);
    unlink($tempFile);
    
    if ($return !== 0) {
        return false;
    }
    
    exec("sudo systemctl restart networking 2>&1", $output, $return);
    
    return $return === 0;
}

function getSystemResources() {
    return [
        'memory' => getMemoryInfo(),
        'disk' => getDiskInfo(),
        'cpu' => getCpuInfo(),
        'uptime' => getSystemUptime(),
        'loadavg' => getLoadAverage()
    ];
}

function getSystemInfo() {
    return [
        'hostname' => getCurrentHostname(),
        'os' => getOsDetails(),
        'kernel' => php_uname('r'),
        'php_version' => phpversion(),
        'timezone' => getCurrentTimezone(),
        'datetime' => getCurrentDateTime(),
        'ntp_enabled' => getNtpStatus()
    ];
}

// ========== API ОБРАБОТЧИК ==========
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_services_status':
        echo json_encode(['success' => true, 'data' => getAllServicesStatus()]);
        break;
        
    case 'service_action':
        $service = $_POST['service'] ?? '';
        $action_type = $_POST['service_action'] ?? '';
        
        $serviceMap = [
            'nfs' => 'nfs-kernel-server',
            'smb' => 'smbd',
            'rsync' => 'rsync',
            'ftp' => 'vsftpd',
            'ssh' => 'ssh',
            'apache2' => 'apache2',
            'ufw' => 'ufw',
            'ntp' => 'ntp'
        ];
        
        if (!isset($serviceMap[$service])) {
            echo json_encode(['success' => false, 'error' => 'Invalid service']);
            break;
        }
        
        $systemService = $serviceMap[$service];
        $result = false;
        
        switch ($action_type) {
            case 'start': $result = startService($systemService); break;
            case 'stop': $result = stopService($systemService); break;
            case 'restart': $result = restartService($systemService); break;
            case 'enable': $result = enableService($systemService); break;
            case 'disable': $result = disableService($systemService); break;
        }
        
        echo json_encode([
            'success' => $result, 
            'data' => [
                'running' => getServiceStatus($systemService) === 'active',
                'enabled' => getServiceEnabled($systemService),
                'pid' => getServicePid($systemService)
            ]
        ]);
        break;
    
    // Power Management
    case 'power_action':
        $powerAction = $_POST['power_action'] ?? '';
        if ($powerAction === 'reboot') {
            systemReboot();
            echo json_encode(['success' => true, 'message' => 'System rebooting...']);
        } elseif ($powerAction === 'shutdown') {
            systemShutdown();
            echo json_encode(['success' => true, 'message' => 'System shutting down...']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid power action']);
        }
        break;
    
    // Resources
    case 'get_resources':
        echo json_encode(['success' => true, 'data' => getSystemResources()]);
        break;
    
    // Timezone
    case 'get_timezone':
        echo json_encode(['success' => true, 'timezone' => getCurrentTimezone(), 'datetime' => getCurrentDateTime()]);
        break;
        
    case 'set_timezone':
        $timezone = $_POST['timezone'] ?? '';
        $result = setTimezone($timezone);
        echo json_encode([
            'success' => $result, 
            'message' => $result ? 'Timezone updated' : 'Failed to update timezone',
            'timezone' => getCurrentTimezone()
        ]);
        break;
        
    case 'get_timezones':
        echo json_encode(['success' => true, 'data' => getAllTimezones()]);
        break;
    
    // NTP
    case 'get_ntp_status':
        echo json_encode(['success' => true, 'enabled' => getNtpStatus()]);
        break;
        
    case 'toggle_ntp':
        $enabled = isset($_POST['ntp_enabled']) && $_POST['ntp_enabled'] == '1';
        $result = setNtpEnabled($enabled);
        echo json_encode([
            'success' => $result,
            'enabled' => getNtpStatus(),
            'message' => $result ? 'NTP ' . ($enabled ? 'enabled' : 'disabled') : 'Failed to change NTP status'
        ]);
        break;
    
    // Manual Date/Time
    case 'set_datetime':
        $date = $_POST['date'] ?? '';
        $time = $_POST['time'] ?? '';
        $result = setManualDateTime($date, $time);
        echo json_encode([
            'success' => $result,
            'message' => $result ? 'System time updated' : 'Failed to set system time',
            'datetime' => getCurrentDateTime()
        ]);
        break;
    
    // Hostname
    case 'get_hostname':
		echo json_encode(['success' => true, 'hostname' => getCurrentHostname()]);
		break;
    
    case 'set_hostname':
		$hostname = $_POST['hostname'] ?? '';
		$result = setHostname($hostname);
		echo json_encode([
			'success' => $result,
			'message' => $result ? 'Hostname changed' : 'Failed to change hostname',
			'hostname' => getCurrentHostname()
		]);
		break;
    
    // Network
    case 'get_network':
        echo json_encode(['success' => true, 'interfaces' => getNetworkInterfaces()]);
        break;
        
    case 'set_network':
        $interface = $_POST['interface'] ?? '';
        $ipMethod = $_POST['ip_method'] ?? 'dhcp';
        $ipAddress = $_POST['ip_address'] ?? '';
        $netmask = $_POST['netmask'] ?? '';
        $gateway = $_POST['gateway'] ?? '';
        $dns = $_POST['dns'] ?? '';
        
        $result = setNetworkConfig($interface, $ipMethod, $ipAddress, $netmask, $gateway, $dns);
        echo json_encode([
            'success' => $result,
            'message' => $result ? 'Network configuration updated' : 'Failed to update network'
        ]);
        break;
    
    // System Info
    case 'get_system_info':
        echo json_encode(['success' => true, 'data' => getSystemInfo()]);
        break;
    
    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
}
?>