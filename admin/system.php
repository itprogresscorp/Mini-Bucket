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

require_once 'config.php';
isAuthenticated();

$db = getDB();
$message = '';
$error = '';

$current_host_id = $_SESSION['current_host_id'] ?? 1;

// Получаем API ключ и URL для текущего хоста
$stmt = $db->prepare("SELECT idHost, hostName, hostApiKey, hostProto, hostIp, hostPort, hostApiPath FROM hosts WHERE idHost = :id");
$stmt->bindValue(':id', $current_host_id, SQLITE3_INTEGER);
$result = $stmt->execute();
$host = $result->fetchArray(SQLITE3_ASSOC);

if ($host) {
    $api_key = $host['hostApiKey'];
    $host_name = $host['hostName'];
    $host_id = $host['idHost'];
    $host_proto = $host['hostProto'];
    $host_ip = $host['hostIp'];
    $host_port = $host['hostPort'];
    $host_api_path = $host['hostApiPath'];
    
    $host_url = $host_proto . "://" . $host_ip;
    if (!empty($host_port) && $host_port != 0 && $host_port != '0') {
        $host_url .= ":" . $host_port;
    }
    $host_url .= $host_api_path;
    
    if ($current_host_id == 1) {
        $api_base_url = '/api/';
    } else {
        $api_base_url = rtrim($host_url, '/') . '/';
    }
} else {
    $api_key = null;
    $api_base_url = '/api/';
    $host_name = 'Localhost';
}

session_start();
if (isset($_SESSION['system_api_message'])) {
    $message = $_SESSION['system_api_message'];
    unset($_SESSION['system_api_message']);
}
if (isset($_SESSION['system_api_error'])) {
    $error = $_SESSION['system_api_error'];
    unset($_SESSION['system_api_error']);
}

$js_config = [
    'apiBaseUrl' => $api_base_url,
    'apiKey' => $api_key,
    'isLocalhost' => ($current_host_id == 1),
    'currentHostId' => $current_host_id,
    'currentHostName' => $host_name
];

$stmt = $db->prepare("SELECT idHost, hostName FROM hosts ORDER BY idHost");
$hostsResult = $stmt->execute();
$hosts = [];
while ($row = $hostsResult->fetchArray(SQLITE3_ASSOC)) {
    $hosts[] = $row;
}

$menu = require_once 'menu.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Mini-B - System</title>
    <link href="lib/bootstrap-5.3.8-dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="lib/bootstrap-icons-1.11.0/bootstrap-icons.css">
    <!--<link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">-->
    <link rel="stylesheet" href="lib/fontawesome-free-6.7.2-web/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="css/loader.css">
    <link rel="shortcut icon" href="css/icon.ico" type="image/x-icon">
    <script src="js/hosts_load.js"></script>
    <script src="js/crt_checker.js"></script>
    <script>
	window.apiConfig = <?php echo json_encode($js_config); ?>;
	console.log('API Config loaded:', window.apiConfig);

	window.hostsList = <?php echo json_encode($hosts); ?>;
	window.currentHostId = <?php echo (int)$current_host_id; ?>;
	</script>
    
    <style>
        * {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e9eef3 100%);
            min-height: 100vh;
        }

        .host-selector select {
            background: rgba(255,255,255,0.9);
            border: 1px solid #ddd;
            border-radius: 20px;
            padding: 6px 30px 6px 15px;
            font-size: 14px;
            cursor: pointer;
        }
        
        .app-container {
            display: flex;
            min-height: 100vh;
        }
        
        .main-content {
            flex: 1;
            padding: 5px 15px;
            margin-left: 280px;
            transition: all 0.3s ease;
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px 15px;
            }
        }
        
        .content-header h1 {
            font-size: 28px;
            font-weight: 600;
            letter-spacing: -0.3px;
            background: linear-gradient(135deg, #1c1c1e, #3a3a3c);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 20px;
        }
        
        .apple-card {
            background: rgba(255, 255, 255, 0.96);
            backdrop-filter: blur(0px);
            border-radius: 20px;
            box-shadow: 0 8px 28px rgba(0, 0, 0, 0.08), 0 1px 2px rgba(0, 0, 0, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.5);
            transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            margin-bottom: 24px;
            overflow: hidden;
        }
        
        .apple-card:hover {
            box-shadow: 0 12px 36px rgba(0, 0, 0, 0.12);
            transform: translateY(-2px);
        }
        
        .card-header-apple {
            padding: 20px 24px;
            background: linear-gradient(135deg, #f8f9fa, #ffffff);
            border-bottom: 1px solid #e9ecef;
        }
        
        .card-header-apple h3 {
            font-size: 18px;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #1c1c1e;
        }
        
        .card-header-apple h3 i {
            color: #007aff;
            font-size: 1.3rem;
        }
        
        .card-body-apple {
            padding: 20px 24px;
        }
        
        /* Service Cards */
        .service-grid-card {
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid #e9ecef;
            transition: all 0.2s ease;
        }
        
        .service-grid-card:hover {
            border-color: #007aff20;
            box-shadow: 0 4px 12px rgba(0,122,255,0.1);
        }
        
        /* Toggle Switch */
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 51px;
            height: 31px;
        }
        
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #e9ecef;
            transition: 0.3s;
            border-radius: 31px;
        }
        
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 27px;
            width: 27px;
            left: 2px;
            bottom: 2px;
            background-color: white;
            transition: 0.3s;
            border-radius: 50%;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        
        input:checked + .toggle-slider {
            background-color: #34c759;
        }
        
        input:checked + .toggle-slider:before {
            transform: translateX(20px);
        }
        
        /* Progress Bar */
        .progress-apple {
            height: 8px;
            border-radius: 12px;
            background: #e5e5ea;
            overflow: hidden;
            width: 100%;
        }
        
        .progress-apple .progress-bar {
            background: linear-gradient(90deg, #34c759, #30b0c7) !important;
            height: 100% !important;
            border-radius: 12px;
            transition: width 0.4s cubic-bezier(0.65, 0, 0.35, 1);
        }
        
        /* Info Box */
        .info-box {
            background: #f8f9fa;
            border-radius: 16px;
            padding: 14px 18px;
            margin-bottom: 12px;
            border: 1px solid #e9ecef;
        }
        
        /* Tabs */
        .nav-tabs-apple {
            border-bottom: 1px solid #e9ecef;
            margin-bottom: 24px;
            gap: 8px;
        }
        
        .nav-tabs-apple .nav-link {
            border: none;
            color: #6c757d;
            font-weight: 500;
            padding: 5px 5px;
            border-radius: 5px;
            transition: all 0.2s;
            font-size: 14px;
        }
        
        .nav-tabs-apple .nav-link:hover {
            background: #f8f9fa;
            color: #007aff;
        }
        
        .nav-tabs-apple .nav-link.active {
            background: #007aff;
            color: white;
            box-shadow: 0 2px 8px rgba(0,122,255,0.3);
        }
        
        /* Buttons */
        .btn-apple {
            background: #007aff;
            border: none;
            border-radius: 12px;
            padding: 10px 20px;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.2s ease;
            color: white;
        }
        
        .btn-apple:hover {
            background: #005fc1;
            transform: scale(0.98);
            color: white;
        }
        
        .btn-apple-outline {
            background: transparent;
            border: 1.5px solid #007aff;
            color: #007aff;
            border-radius: 12px;
            padding: 9px 19px;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        
        .btn-apple-outline:hover {
            background: #007aff10;
            transform: scale(0.98);
        }
        
        .btn-apple-danger {
            background: #ff3b30;
        }
        
        .btn-apple-danger:hover {
            background: #d70015;
        }
        
        .btn-apple-warning {
            background: #ff9500;
        }
        
        .btn-apple-warning:hover {
            background: #cc7a00;
        }
        
        /* Badges */
        .badge-running {
            background: #34c75920;
            color: #248a3d;
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 500;
            font-size: 12px;
        }
        
        .badge-stopped {
            background: #ff3b3020;
            color: #d70015;
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 500;
            font-size: 12px;
        }
        
        /* Alert */
        .alert-apple {
            border-radius: 16px;
            border: none;
            background: white;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        
        /* Service Action Buttons */
        .service-action-btn {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            border: none;
            background: #f8f9fa;
            color: #6c757d;
        }
        
        .service-action-btn:hover {
            background: #007aff;
            color: white;
            transform: scale(1.05);
        }
        
        .service-action-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        /* Loading Spinner */
        .loading-spinner-sm {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #007aff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Refresh Button */
        .refresh-btn {
            cursor: pointer;
            transition: transform 0.2s;
            font-size: 16px;
        }
        
        .refresh-btn:hover {
            transform: rotate(180deg);
        }
		
		/* Diagnostic Button */
        .diagnostic-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 12px;
            padding: 12px 24px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .diagnostic-btn:hover {
            transform: scale(1.02);
            box-shadow: 0 8px 20px rgba(102,126,234,0.4);
        }
    </style>
</head>
<body>
<div id="applePreloader" class="apple-preloader">
    <div class="apple-spinner"></div>
    <div class="apple-spinner-text">Loading...</div>
</div>

<div class="top-bar">
    <div class="top-bar-left">
        <h1><i class="fas fa-bucket"></i> Mini-B</h1>
    </div>
    <div class="top-bar-right">
        
        <div class="host-selector">
            <select id="hostSelector">
                <option value="">Loading...</option>
            </select>
        </div>
        <i class="fas fa-sync-alt refresh-btn text-muted" onclick="refreshAllData()" title="Refresh"></i>
    </div>
</div>

<div class="app-container">
    <?php echo $menu; ?>
    
    <main class="main-content">
        <div id="alertContainer"></div>
        <!--<div id="alertMessages"></div>-->
        
        <!-- Tabs -->
        <ul class="nav nav-tabs-apple" id="systemTab" role="tablist">
            <li class="nav-item">
                <button class="nav-link active" id="services-tab" data-bs-toggle="tab" data-bs-target="#services" type="button" role="tab">
                    <i class="bi bi-gear-fill me-2"></i>Services
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="datetime-tab" data-bs-toggle="tab" data-bs-target="#datetime" type="button" role="tab">
                    <i class="bi bi-calendar3 me-2"></i>Date & Time
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="network-tab" data-bs-toggle="tab" data-bs-target="#network" type="button" role="tab">
                    <i class="bi bi-wifi me-2"></i>Network
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="systeminfo-tab" data-bs-toggle="tab" data-bs-target="#systeminfo" type="button" role="tab">
                    <i class="bi bi-motherboard me-2"></i>System Info
                </button>
            </li>
        </ul>
        
        <div class="tab-content">
            <!-- ==================== SERVICES TAB ==================== -->
            <div class="tab-pane fade show active" id="services" role="tabpanel">
                <!-- Power & Resources Row -->
                <div class="row g-4">
                    <div class="col-lg-5">
                        <div class="apple-card">
                            <div class="card-header-apple">
                                <h3><i class="bi bi-power"></i> Power Management</h3>
                            </div>
                            <div class="card-body-apple">
                                <div class="d-flex gap-3 mb-4">
                                    <button type="button" class="btn-apple-warning btn" onclick="powerAction('reboot')" style="background: #ff9500; color: white;">
                                        <i class="bi bi-arrow-repeat me-2"></i>Reboot
                                    </button>
                                    <button type="button" class="btn-apple-danger btn" onclick="powerAction('shutdown')">
                                        <i class="bi bi-power me-2"></i>Shutdown
                                    </button>
                                </div>
                                
                                <div class="info-box">
                                    <i class="bi bi-clock-history me-2 text-primary"></i>
                                    <strong>System Uptime</strong><br>
                                    <span class="fs-5 fw-semibold" id="systemUptime">-</span>
                                </div>
                                
                                <div class="info-box">
                                    <i class="bi bi-graph-up me-2 text-primary"></i>
                                    <strong>Load Average</strong><br>
                                    <span id="loadAverage">-</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-7">
                        <div class="apple-card">
                            <div class="card-header-apple">
                                <h3><i class="bi bi-pie-chart"></i> System Resources</h3>
                            </div>
                            <div class="card-body-apple">
                                <div class="mb-4">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="small fw-semibold"><i class="bi bi-memory"></i> Memory Usage</span>
                                        <span class="small text-secondary" id="memoryPercent">0%</span>
                                    </div>
                                    <div class="progress-apple">
                                        <div class="progress-bar" id="memoryBar" style="width: 0%"></div>
                                    </div>
                                    <div class="d-flex justify-content-between mt-2 small text-secondary">
                                        <span>Used: <span id="memoryUsed">0</span> GB</span>
                                        <span>Total: <span id="memoryTotal">0</span> GB</span>
                                    </div>
                                </div>
                                
                                <div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="small fw-semibold"><i class="bi bi-hdd-stack"></i> Disk Usage (/)</span>
                                        <span class="small text-secondary" id="diskPercent">0%</span>
                                    </div>
                                    <div class="progress-apple">
                                        <div class="progress-bar" id="diskBar" style="width: 0%"></div>
                                    </div>
                                    <div class="d-flex justify-content-between mt-2 small text-secondary">
                                        <span>Used: <span id="diskUsed">0</span> GB</span>
                                        <span>Free: <span id="diskFree">0</span> GB</span>
                                        <span>Total: <span id="diskTotal">0</span> GB</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Services Grid -->
                <div class="apple-card">
                    <div class="card-header-apple">
                        <h3><i class="bi bi-grid-3x3-gap-fill"></i> Services Management</h3>
                    </div>
                    <div class="card-body-apple">
                        <div class="row g-3" id="servicesGrid">
                            <div class="col-12 text-center py-4">
                                <div class="loading-spinner-sm"></div> Loading services...
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- ==================== DATE & TIME TAB ==================== -->
            <div class="tab-pane fade" id="datetime" role="tabpanel">
                <div class="row g-4">
                    <div class="col-lg-6">
                        <div class="apple-card">
                            <div class="card-header-apple">
                                <h3><i class="bi bi-globe2"></i> Timezone Settings</h3>
                            </div>
                            <div class="card-body-apple">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Current Timezone</label>
                                    <div class="info-box">
                                        <i class="bi bi-geo-alt-fill me-2 text-primary"></i>
                                        <span id="currentTimezone">-</span>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Change Timezone</label>
                                    <select id="timezoneSelect" class="form-select rounded-3" style="padding: 10px;">
                                        <option value="">Select timezone...</option>
                                    </select>
                                </div>
                                <button type="button" class="btn-apple" onclick="setTimezone()">
                                    <i class="bi bi-save me-2"></i>Apply Timezone
                                </button>
                            </div>
                        </div>
                        
                        <div class="apple-card">
                            <div class="card-header-apple">
                                <h3><i class="bi bi-clock"></i> NTP Settings</h3>
                            </div>
                            <div class="card-body-apple">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span class="fw-semibold">Automatic Time Sync (NTP)</span>
                                    <label class="toggle-switch">
                                        <input type="checkbox" id="ntpToggle" onchange="toggleNtp()">
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                                <div class="alert alert-info small rounded-3" style="background: #e3f2fd; border: none;">
                                    <i class="bi bi-info-circle me-2"></i>NTP automatically synchronizes your system time with network time servers.
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-6">
                        <div class="apple-card" id="manualTimeCard">
                            <div class="card-header-apple">
                                <h3><i class="bi bi-calendar-event"></i> Manual Date & Time</h3>
                            </div>
                            <div class="card-body-apple">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Current System Time</label>
                                    <div class="info-box">
                                        <i class="bi bi-clock me-2 text-primary"></i>
                                        <span id="currentDateTime">-</span>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Set Date</label>
                                    <input type="date" id="manualDate" class="form-control rounded-3" value="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Set Time</label>
                                    <input type="time" id="manualTime" class="form-control rounded-3" value="<?php echo date('H:i:s'); ?>">
                                </div>
                                <button type="button" class="btn-apple-warning btn" id="setManualTimeBtn" style="background: #ff9500; color: white;" onclick="setManualDateTime()">
                                    <i class="bi bi-save me-2"></i>Set Manual Time
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- ==================== NETWORK TAB ==================== -->
            <div class="tab-pane fade" id="network" role="tabpanel">
                <div class="row g-4">
                    <div class="col-lg-6">
                        <div class="apple-card">
                            <div class="card-header-apple">
                                <h3><i class="bi bi-server"></i> Hostname</h3>
                            </div>
                            <div class="card-body-apple">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Current Hostname</label>
                                    <div class="info-box">
                                        <i class="bi bi-desktop me-2 text-primary"></i>
                                        <span id="currentHostname">-</span>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">New Hostname</label>
                                    <input type="text" id="newHostname" class="form-control rounded-3" placeholder="Enter new hostname">
                                    <small class="text-muted mt-1">Letters, numbers, dots, and hyphens only</small>
                                </div>
                                <button type="button" class="btn-apple" onclick="setHostname()">
                                    <i class="bi bi-save me-2"></i>Change Hostname
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-6">
                        <div class="apple-card">
                            <div class="card-header-apple">
                                <h3><i class="bi bi-hdd-network"></i> Network Configuration</h3>
                            </div>
                            <div class="card-body-apple">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Interface</label>
                                    <select id="networkInterface" class="form-select rounded-3" required>
                                        <option value="">Select interface...</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">IP Method</label>
                                    <select id="ipMethod" class="form-select rounded-3" onchange="toggleStaticFields()">
                                        <option value="dhcp">DHCP (Automatic)</option>
                                        <option value="static">Static (Manual)</option>
                                    </select>
                                </div>
                                
                                <div id="staticFields" style="display: none;">
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">IP Address</label>
                                        <input type="text" id="ipAddress" class="form-control rounded-3" placeholder="192.168.1.100">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Netmask</label>
                                        <select id="netmask" class="form-select rounded-3">
											<option value="128.0.0.0">128.0.0.0 (/1)</option>
											<option value="192.0.0.0">192.0.0.0 (/2)</option>
											<option value="224.0.0.0">224.0.0.0 (/3)</option>
											<option value="240.0.0.0">240.0.0.0 (/4)</option>
											<option value="248.0.0.0">248.0.0.0 (/5)</option>
											<option value="252.0.0.0">252.0.0.0 (/6)</option>
											<option value="254.0.0.0">254.0.0.0 (/7)</option>
											<option value="255.0.0.0">255.0.0.0 (/8)</option>
											<option value="255.128.0.0">255.128.0.0 (/9)</option>
											<option value="255.192.0.0">255.192.0.0 (/10)</option>
											<option value="255.224.0.0">255.224.0.0 (/11)</option>
											<option value="255.240.0.0">255.240.0.0 (/12)</option>
											<option value="255.248.0.0">255.248.0.0 (/13)</option>
											<option value="255.252.0.0">255.252.0.0 (/14)</option>
											<option value="255.254.0.0">255.254.0.0 (/15)</option>
											<option value="255.255.0.0">255.255.0.0 (/16)</option>
											<option value="255.255.128.0">255.255.128.0 (/17)</option>
											<option value="255.255.192.0">255.255.192.0 (/18)</option>
											<option value="255.255.224.0">255.255.224.0 (/19)</option>
											<option value="255.255.240.0">255.255.240.0 (/20)</option>
											<option value="255.255.248.0">255.255.248.0 (/21)</option>
											<option value="255.255.252.0">255.255.252.0 (/22)</option>
											<option value="255.255.254.0">255.255.254.0 (/23)</option>
											<option value="255.255.255.0">255.255.255.0 (/24)</option>
											<option value="255.255.255.128">255.255.255.128 (/25)</option>
											<option value="255.255.255.192">255.255.255.192 (/26)</option>
											<option value="255.255.255.224">255.255.255.224 (/27)</option>
											<option value="255.255.255.240">255.255.255.240 (/28)</option>
											<option value="255.255.255.248">255.255.255.248 (/29)</option>
											<option value="255.255.255.252">255.255.255.252 (/30)</option>
											<option value="255.255.255.254">255.255.255.254 (/31)</option>
										</select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Gateway</label>
                                        <input type="text" id="gateway" class="form-control rounded-3" placeholder="192.168.1.1">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">DNS Servers</label>
                                        <input type="text" id="dns" class="form-control rounded-3" placeholder="8.8.8.8, 8.8.4.4">
                                    </div>
                                </div>
                                
                                <div class="alert alert-warning rounded-3 small">
                                    <i class="bi bi-exclamation-triangle me-2"></i>Changes may disconnect your session!
                                </div>
                                <button type="button" class="btn-apple" onclick="setNetworkConfig()">
                                    <i class="bi bi-save me-2"></i>Apply Settings
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="apple-card">
                    <div class="card-header-apple">
                        <h3><i class="bi bi-diagram-3"></i> Network Status</h3>
                    </div>
                    <div class="card-body-apple">
                        <div class="table-responsive">
                            <table class="table table-borderless">
                                <thead>
                                    <tr><th>Interface</th><th>IP Address</th><th>Method</th><th>Status</th></tr>
                                </thead>
                                <tbody id="networkStatusTable">
                                    <tr><td colspan="4" class="text-center"><div class="loading-spinner-sm"></div> Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- ==================== SYSTEM INFO TAB ==================== -->
            <div class="tab-pane fade" id="systeminfo" role="tabpanel">
                <div class="row g-4">
                    <div class="col-lg-6">
                        <div class="apple-card">
                            <div class="card-header-apple">
                                <h3><i class="bi bi-info-circle"></i> System Information</h3>
                            </div>
                            <div class="card-body-apple">
                                <div class="info-box" id="sysinfoHostname">
                                    <i class="bi bi-server me-2 text-primary"></i>
                                    <strong>Hostname:</strong><br>-
                                </div>
                                <div class="info-box" id="sysinfoOs">
                                    <i class="bi bi-ubuntu me-2 text-primary"></i>
                                    <strong>Operating System:</strong><br>-
                                </div>
                                <div class="info-box" id="sysinfoKernel">
                                    <i class="bi bi-box me-2 text-primary"></i>
                                    <strong>Kernel:</strong><br>-
                                </div>
                                <div class="info-box" id="sysinfoCpu">
                                    <i class="bi bi-cpu-fill me-2 text-primary"></i>
                                    <strong>CPU:</strong><br>-
                                </div>
                                <div class="info-box" id="sysinfoPhp">
                                    <i class="bi bi-code-slash me-2 text-primary"></i>
                                    <strong>PHP Version:</strong><br>-
                                </div>
                            </div>
                        </div>
						
						<div class="apple-card">
                            <div class="card-header-apple">
                                <h3><i class="bi bi-info-circle"></i> System Information</h3>
                            </div>
                            <div class="card-body-apple">
                                <div class="info-box" id="">
                                    <i class="bi bi-disc me-2 text-primary"></i>
                                    <strong>Product:</strong><br>Mini-Bucket - NAS Control Panel
                                </div>
                                <div class="info-box" id="">
                                    <i class="bi bi-archive me-2 text-primary"></i>
                                    <strong>Version:</strong><br><?php echo $version; ?>
                                </div>
                                <div class="info-box" id="">
                                    <i class="fas fa-balance-scale me-2 text-primary"></i>
                                    <strong>License:</strong><br> GNU Affero General Public License "AGPLv3+"
                                </div>
                                <div class="info-box" id="">
                                    <i class="bi bi-c-circle me-2 text-primary"></i>
                                    <strong>Copyright (C) 2026 Mamontov Roman Igorevich</strong><br>Mail: <a href="mailto:sa@itp-corp.ru">sa@itp-corp.ru</a> | WEB Site: <a href="https://mini-bucket.ru/" target="_blank" rel="noopener noreferrer">mini-bucket.ru</a>
                                </div>
                                <div class="info-box" id="">
                                    <i class="bi bi-currency-dollar me-2 text-primary"></i>
                                    <strong>Donation</strong><br> Donation methods: <a href="https://mini-bucket.ru/donation/" target="_blank" rel="noopener noreferrer">Donation</a>
                                </div>
                            </div>
                        </div>
                    </div>
					
                    
                    <div class="col-lg-6">
                        <div class="apple-card">
                            <div class="card-header-apple">
                                <h3><i class="bi bi-bar-chart-steps"></i> Resource Details</h3>
                            </div>
                            <div class="card-body-apple">
                                <div class="info-box" id="resMemory">
                                    <i class="bi bi-memory me-2 text-primary"></i>
                                    <strong>Memory Details</strong><br>-
                                </div>
                                <div class="info-box" id="resDisk">
                                    <i class="bi bi-hdd-stack me-2 text-primary"></i>
                                    <strong>Disk Details (/)</strong><br>-
                                </div>
                                <div class="info-box" id="resUptime">
                                    <i class="bi bi-clock-history me-2 text-primary"></i>
                                    <strong>System Uptime</strong><br>-
                                </div>
                                <div class="info-box" id="resTimezone">
                                    <i class="bi bi-calendar3 me-2 text-primary"></i>
                                    <strong>Timezone & Date</strong><br>-
                                </div>
                            </div>
                        </div>
						<!-- Diagnostic Button -->
                        <div class="apple-card text-center">
                            <div class="card-body-apple">
                                <button type="button" class="btn diagnostic-btn" onclick="window.openSystemChecker()">
                                    <i class="bi bi-shield-check me-2"></i> Run System Diagnostics
                                </button>
                                <p class="text-muted small mt-3 mb-0">
                                    <i class="bi bi-info-circle"></i> Check and repair system configuration, packages, and services
                                </p>
                            </div>
                        </div>
                    </div>
					
                </div>
            </div>
        </div>
		
    </main>
</div>

<script src="lib/jquery-3.6.0-master/dist/jquery.min.js"></script>
<script src="lib/bootstrap-5.3.8-dist/js/bootstrap.bundle.min.js"></script>
<script src="js/loader.js"></script>

<script>
const url = "<?php echo $current_host_id == 1 ? '/api/' : rtrim($host_url, '/') . '/'; ?>";
// ========== Утилиты ==========
function showAlert(message, type = 'success') {
    const alertHtml = `<div class="alert alert-${type} alert-dismissible fade show mb-3" role="alert">
        <i class="bi bi-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i> 
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>`;
    $('#alertContainer').append(alertHtml);
    setTimeout(() => $('.alert').fadeOut(500, function() { $(this).remove(); }), 5000);
}

// ========== API Calls ==========
async function apiCall(action, method = 'GET', data = null) {
    let fullUrl = `${window.apiConfig.apiBaseUrl}system_settings_api.php?action=${action}`;
    let options = { 
        method: method, 
        headers: {}
    };
    
    if (window.apiConfig && window.apiConfig.apiKey) {
        options.headers['X-API-Key'] = window.apiConfig.apiKey;
    }
    
    if (method === 'POST' && data) {
        options.headers['Content-Type'] = 'application/x-www-form-urlencoded';
        options.body = new URLSearchParams(data);
    } else if (method === 'GET' && data) {
        fullUrl += '&' + new URLSearchParams(data);
    }
    
    try {
        let response = await fetch(fullUrl, options);
        return await response.json();
    } catch(e) {
        console.error('API Error:', e);
        return { success: false, error: e.message };
    }
}

// ========== Host Selector ==========
function initHostSelector() {
    const selector = $('#hostSelector');
    selector.empty();
    
    if (window.hostsList && window.hostsList.length > 0) {
        window.hostsList.forEach(host => {
            const selected = (host.idHost == window.currentHostId) ? 'selected' : '';
            selector.append(`<option value="${host.idHost}" ${selected}>${escapeHtml(host.hostName)}</option>`);
        });
    } else {
        selector.append('<option value="1">Localhost</option>');
    }
    
    selector.on('change', function() {
        const newHostId = $(this).val();
        if (newHostId != window.currentHostId) {
            showAlert('Switching to ' + $(this).find('option:selected').text() + '...', 'info');
            $('#applePreloader').fadeIn(200);
            
            $.ajax({
                url: 'ajax/switch_host.php',
                method: 'POST',
                data: { host_id: newHostId },
                success: function() {
                    window.location.href = 'system.php';
                },
                error: function() {
                    showAlert('Failed to switch host', 'danger');
                    $('#applePreloader').fadeOut(500);
                    selector.val(window.currentHostId);
                }
            });
        }
    });
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/[&<>]/g, function(m) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;' }[m];
    });
}

// ========== Power Actions ==========
async function powerAction(action) {
    if (!confirm(`${action === 'reboot' ? 'Reboot' : 'Shutdown'} system?`)) return;
    
    const result = await apiCall('power_action', 'POST', { power_action: action });
    if (result.success) {
        showAlert(result.message, 'warning');
        setTimeout(() => { window.location.reload(); }, 5000);
    } else {
        showAlert(result.error || 'Action failed', 'danger');
    }
}

// ========== Resources ==========
async function loadResources() {
    const result = await apiCall('get_resources');
    if (result.success) {
        const data = result.data;
        
        $('#systemUptime').text(data.uptime);
        $('#loadAverage').text(data.loadavg.map(l => l.toFixed(2)).join(', ') + ' (1, 5, 15 min)');
        
        $('#memoryPercent').text(data.memory.usage_percent + '%');
        $('#memoryBar').css('width', data.memory.usage_percent + '%');
        $('#memoryUsed').text(data.memory.used);
        $('#memoryTotal').text(data.memory.total);
        
        $('#diskPercent').text(data.disk.usage_percent + '%');
        $('#diskBar').css('width', data.disk.usage_percent + '%');
        $('#diskUsed').text(data.disk.used);
        $('#diskFree').text(data.disk.free);
        $('#diskTotal').text(data.disk.total);
    }
}

// ========== Services ==========
async function loadServices() {
    try {
        const result = await apiCall('get_services_status');
        if (result.success) {
            const services = result.data;
            let html = '';
            
            const serviceIcons = {
                nfs: 'bi-folder-symlink',
                smb: 'bi-hdd-network',
                rsync: 'bi-arrow-repeat',
                ftp: 'bi-folder',
                ssh: 'bi-shield-lock',
                apache2: 'bi-globe',
                ufw: 'bi-shield-check',
                ntp: 'bi-clock'
            };
            
            const serviceDisplay = {
                nfs: 'NFS', smb: 'SMB/CIFS', rsync: 'RSYNC', ftp: 'FTP',
                ssh: 'SSH', apache2: 'Apache2', ufw: 'Firewall (UFW)', ntp: 'NTP'
            };
            
            for (const [key, service] of Object.entries(services)) {
                html += `
                    <div class="col-md-6 col-lg-4">
                        <div class="service-grid-card p-3">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi ${serviceIcons[key]} fs-5" style="color: #007aff;"></i>
                                    <strong>${serviceDisplay[key]}</strong>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" onchange="serviceAction('${key}', '${service.enabled ? 'disable' : 'enable'}')" ${service.enabled ? 'checked' : ''}>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                            <div class="mb-2">
                                ${service.running ? 
                                    '<span class="badge-running"><i class="bi bi-play-fill me-1"></i>Running</span>' : 
                                    '<span class="badge-stopped"><i class="bi bi-stop-fill me-1"></i>Stopped</span>'}
                            </div>
                            <div class="d-flex gap-2 mt-2">
                                <button type="button" class="service-action-btn" onclick="serviceAction('${key}', 'start')" ${service.running ? 'disabled' : ''} title="Start">
                                    <i class="bi bi-play-fill"></i>
                                </button>
                                <button type="button" class="service-action-btn" onclick="serviceAction('${key}', 'stop')" ${!service.running ? 'disabled' : ''} title="Stop">
                                    <i class="bi bi-stop-fill"></i>
                                </button>
                                <button type="button" class="service-action-btn" onclick="serviceAction('${key}', 'restart')" title="Restart">
                                    <i class="bi bi-arrow-repeat"></i>
                                </button>
                            </div>
                            <div class="mt-2">
                                <small class="text-muted">
                                    <i class="bi bi-toggle2-on"></i> Autostart: ${service.enabled ? 'Enabled' : 'Disabled'}
                                </small>
                                ${service.pid ? `<br><small class="text-muted"><i class="bi bi-cpu"></i> PID: ${service.pid}</small>` : ''}
                            </div>
                        </div>
                    </div>
                `;
            }
            
            $('#servicesGrid').html(html);
        } else {
            $('#servicesGrid').html('<div class="col-12 text-center py-4 text-danger">Failed to load services</div>');
        }
    } catch (error) {
        console.error('loadServices error:', error);
        $('#servicesGrid').html('<div class="col-12 text-center py-4 text-danger">Error loading services</div>');
    }
}

async function serviceAction(service, action) {
    const result = await apiCall('service_action', 'POST', { service: service, service_action: action });
    if (result.success) {
        showAlert(`Service ${action}ed`, 'success');
        loadServices();
        loadResources();
    } else {
        showAlert(`Error: ${result.error || 'Action failed'}`, 'danger');
    }
}

// ========== Timezone ==========
async function loadTimezones() {
    const result = await apiCall('get_timezones');
    if (result.success) {
        const timezones = result.data;
        let html = '<option value="">Select timezone...</option>';
        for (const [region, zones] of Object.entries(timezones)) {
            html += `<optgroup label="${escapeHtml(region)}">`;
            zones.forEach(tz => {
                html += `<option value="${escapeHtml(tz)}">${escapeHtml(tz.replace(/_/g, ' '))}</option>`;
            });
            html += `</optgroup>`;
        }
        $('#timezoneSelect').html(html);
    }
}

async function loadCurrentTimezone() {
    const result = await apiCall('get_timezone');
    if (result.success) {
        $('#currentTimezone').text(result.timezone.replace(/_/g, ' '));
        $('#currentDateTime').text(result.datetime);
        $('#timezoneSelect').val(result.timezone);
    }
}

async function setTimezone() {
    const timezone = $('#timezoneSelect').val();
    if (!timezone) {
        showAlert('Select timezone', 'warning');
        return;
    }
    
    const result = await apiCall('set_timezone', 'POST', { timezone: timezone });
    if (result.success) {
        showAlert(result.message, 'success');
        loadCurrentTimezone();
        loadSystemInfo();
    } else {
        showAlert(result.message, 'danger');
    }
}

// ========== NTP ==========
async function loadNtpStatus() {
    const result = await apiCall('get_ntp_status');
    if (result.success) {
        $('#ntpToggle').prop('checked', result.enabled);
        if (result.enabled) {
            $('#manualTimeCard').addClass('opacity-50');
            $('#setManualTimeBtn').prop('disabled', true);
            $('#manualDate, #manualTime').prop('disabled', true);
        } else {
            $('#manualTimeCard').removeClass('opacity-50');
            $('#setManualTimeBtn').prop('disabled', false);
            $('#manualDate, #manualTime').prop('disabled', false);
        }
    }
}

async function toggleNtp() {
    const enabled = $('#ntpToggle').is(':checked');
    const result = await apiCall('toggle_ntp', 'POST', { ntp_enabled: enabled ? '1' : '0' });
    if (result.success) {
        showAlert(result.message, 'success');
        loadNtpStatus();
        loadCurrentTimezone();
    } else {
        showAlert(result.message, 'danger');
        $('#ntpToggle').prop('checked', !enabled);
    }
}

async function setManualDateTime() {
    const date = $('#manualDate').val();
    const time = $('#manualTime').val();
    
    if (!date || !time) {
        showAlert('Enter date and time', 'warning');
        return;
    }
    
    const result = await apiCall('set_datetime', 'POST', { date: date, time: time });
    if (result.success) {
        showAlert(result.message, 'success');
        loadCurrentTimezone();
        loadSystemInfo();
    } else {
        showAlert(result.message, 'danger');
    }
}

// ========== Hostname ==========
async function loadHostname() {
    const result = await apiCall('get_hostname');
    if (result.success) {
        $('#currentHostname').text(result.hostname);
    }
}

async function setHostname() {
    const hostname = $('#newHostname').val();
    if (!hostname) {
        showAlert('Enter hostname', 'warning');
        return;
    }
    
    const result = await apiCall('set_hostname', 'POST', { hostname: hostname });
    if (result.success) {
        showAlert(result.message, 'success');
        loadHostname();
        loadSystemInfo();
        $('#newHostname').val('');
    } else {
        showAlert(result.message, 'danger');
    }
}

// ========== Network ==========
async function loadNetwork() {
    try {
        const savedInterface = $('#networkInterface').val();
        
        const result = await apiCall('get_network');
        if (result.success) {
            const interfaces = result.interfaces;
            
            let selectHtml = '<option value="">Select interface...</option>';
            let tableHtml = '';
            
            if (Object.keys(interfaces).length === 0) {
                tableHtml = '<tr><td colspan="4" class="text-center text-muted">No network interfaces found</td></tr>';
            } else {
                for (const [iface, info] of Object.entries(interfaces)) {
                    const selectedAttr = (iface === savedInterface) ? 'selected' : '';
                    selectHtml += `<option value="${escapeHtml(iface)}" ${selectedAttr}>${escapeHtml(iface)} - ${escapeHtml(info.ip)}</option>`;
                    
                    const statusBadge = info.ip !== 'No IP' ? 
                        '<span class="badge-running"><i class="bi bi-check-circle-fill me-1"></i>Active</span>' : 
                        '<span class="badge-stopped"><i class="bi bi-x-circle-fill me-1"></i>Down</span>';
                    
                    tableHtml += `<tr>
                        <td><strong>${escapeHtml(iface)}</strong></td>
                        <td>${escapeHtml(info.ip)}</td>
                        <td>${info.method === 'static' ? 'Static' : 'DHCP'}</td>
                        <td>${statusBadge}</td>
                    </tr>`;
                }
            }
            
            $('#networkInterface').html(selectHtml);
            $('#networkStatusTable').html(tableHtml);
        } else {
            $('#networkStatusTable').html('<tr><td colspan="4" class="text-center text-danger">Failed to load network info</td></tr>');
        }
    } catch (error) {
        console.error('loadNetwork error:', error);
        $('#networkStatusTable').html('<tr><td colspan="4" class="text-center text-danger">Error loading network</td></tr>');
    }
}

function toggleStaticFields() {
    const method = $('#ipMethod').val();
    $('#staticFields').toggle(method === 'static');
}

async function setNetworkConfig() {
    const interface_ = $('#networkInterface').val();
    const ipMethod = $('#ipMethod').val();
    
    if (!interface_) {
        showAlert('Select interface', 'warning');
        return;
    }
    
    const data = {
        interface: interface_,
        ip_method: ipMethod
    };
    
    if (ipMethod === 'static') {
        data.ip_address = $('#ipAddress').val();
        data.netmask = $('#netmask').val();
        data.gateway = $('#gateway').val();
        data.dns = $('#dns').val();
        
        if (!data.ip_address || !data.gateway) {
            showAlert('Fill IP address and gateway', 'warning');
            return;
        }
    }
    
    const result = await apiCall('set_network', 'POST', data);
    if (result.success) {
        showAlert(result.message, 'success');
        setTimeout(() => loadNetwork(), 3000);
    } else {
        showAlert(result.message, 'danger');
    }
}

// ========== System Info ==========
async function loadSystemInfo() {
    const result = await apiCall('get_system_info');
    if (result.success) {
        const data = result.data;
        
        $('#sysinfoHostname').html(`<i class="bi bi-server me-2 text-primary"></i><strong>Hostname:</strong><br>${escapeHtml(data.hostname)}`);
        $('#sysinfoOs').html(`<i class="bi bi-ubuntu me-2 text-primary"></i><strong>Operating System:</strong><br>${escapeHtml(data.os)}`);
        $('#sysinfoKernel').html(`<i class="bi bi-cpu me-2 text-primary"></i><strong>Kernel:</strong><br>${escapeHtml(data.kernel)}`);
        $('#sysinfoPhp').html(`<i class="bi bi-code-slash me-2 text-primary"></i><strong>PHP Version:</strong><br>${escapeHtml(data.php_version)}`);
        
        $('#resTimezone').html(`<i class="bi bi-calendar3 me-2 text-primary"></i><strong>Timezone & Date</strong><br>Timezone: ${escapeHtml(data.timezone.replace(/_/g, ' '))}<br>System Time: ${escapeHtml(data.datetime)}<br>NTP: ${data.ntp_enabled ? '<span class="badge-running">Enabled</span>' : '<span class="badge-stopped">Disabled</span>'}`);
    }
    
    const resources = await apiCall('get_resources');
    if (resources.success) {
        const cpu = resources.data.cpu;
        $('#sysinfoCpu').html(`<i class="bi bi-microchip me-2 text-primary"></i><strong>CPU:</strong><br>${escapeHtml(cpu.model)} (${cpu.cores} cores)`);
        
        $('#resMemory').html(`<i class="bi bi-memory me-2 text-primary"></i><strong>Memory Details</strong><br>Total: ${resources.data.memory.total} GB<br>Used: ${resources.data.memory.used} GB<br>Available: ${resources.data.memory.available} GB<br>Usage: ${resources.data.memory.usage_percent}%`);
        
        $('#resDisk').html(`<i class="bi bi-hdd-stack me-2 text-primary"></i><strong>Disk Details (/)</strong><br>Total: ${resources.data.disk.total} GB<br>Used: ${resources.data.disk.used} GB<br>Free: ${resources.data.disk.free} GB<br>Usage: ${resources.data.disk.usage_percent}%`);
        
        $('#resUptime').html(`<i class="bi bi-clock-history me-2 text-primary"></i><strong>System Uptime</strong><br>${escapeHtml(resources.data.uptime)}`);
    }
}

// ========== Refresh All ==========
let isRefreshing = false;

async function refreshAllData() {
    if (isRefreshing) {
        showAlert('Refresh already in progress...', 'info');
        return;
    }
    
    isRefreshing = true;
    const refreshIcon = $('.refresh-btn');
    refreshIcon.addClass('fa-spin');
    
    showLoadingIndicators();
    
    try {
        await Promise.all([
            loadResources(),
            loadServices(),
            loadCurrentTimezone(),
            loadNtpStatus(),
            loadHostname(),
            loadNetwork(),
            loadSystemInfo()
        ]);
        
        showAlert('Data updated successfully', 'success');
    } catch (error) {
        console.error('Refresh error:', error);
        showAlert('Error loading data: ' + error.message, 'danger');
    } finally {
        hideLoadingIndicators();
        refreshIcon.removeClass('fa-spin');
        isRefreshing = false;
    }
}

function showLoadingIndicators() {
    $('.apple-card').addClass('loading');
    $('#servicesGrid').html('<div class="col-12 text-center py-4"><div class="loading-spinner-sm"></div> Loading services...</div>');
    $('#networkStatusTable').html('<tr><td colspan="4" class="text-center"><div class="loading-spinner-sm"></div> Loading...</td></tr>');
    $('#currentTimezone').text('Loading...');
    $('#currentHostname').text('Loading...');
    $('#systemUptime').text('Loading...');
    $('#loadAverage').text('Loading...');
}

function hideLoadingIndicators() {
    $('.apple-card').removeClass('loading');
}

// System Checker Modal Functions
function openSystemChecker() {
    if (!$('#globalCheckModal').length) {
        $('body').append(`
            <div class="modal fade modal-apple" id="globalCheckModal" tabindex="-1" data-bs-backdrop="static">
                <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header" style="background: linear-gradient(135deg, #667eea, #764ba2); color: white;">
                            <h5 class="modal-title"><i class="bi bi-laptop me-2"></i>System Diagnostics</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body" id="globalModalBody">
                            <div class="text-center py-5">
                                <div class="spinner-border text-primary mb-3" role="status"></div>
                                <p>Running diagnostics...</p>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button class="btn" id="globalFixAllBtn2" style="background: linear-gradient(135deg, #667eea, #764ba2); color: white;">Fix All</button>
                        </div>
                    </div>
                </div>
            </div>
        `);
    }
    
    const modal = new bootstrap.Modal($('#globalCheckModal')[0]);
    modal.show();
    
    $('#globalModalBody').html('<div class="text-center py-5"><div class="spinner-border text-primary mb-3"></div><p>Checking system...</p></div>');
    
    const headers = {};
    if (window.apiConfig && window.apiConfig.apiKey) {
        headers['X-API-Key'] = window.apiConfig.apiKey;
    }
    
    fetch(url + 'system_check_api.php?action=check_all', {
        method: 'GET',
        headers: headers
    })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                renderQuickCheckResults(data.results);
            } else {
                $('#globalModalBody').html('<div class="alert alert-danger m-3">API Error</div>');
            }
        })
        .catch(() => {
            $('#globalModalBody').html('<div class="alert alert-danger m-3">Network Error</div>');
        });
    
    function renderQuickCheckResults(results) {
        let html = '<div class="p-3">';
        for (const [catKey, category] of Object.entries(results)) {
            html += `<h6 class="mt-3 fw-semibold"><i class="bi bi-folder2 me-2"></i>${category.name}</h6><div class="row g-2 mb-3">`;
            for (const [itemKey, item] of Object.entries(category.items)) {
                const color = item.status ? '#34c759' : '#ff3b30';
                html += `<div class="col-md-6">
                    <div class="d-flex justify-content-between align-items-center p-2 rounded border" style="background: #f8f9fa;">
                        <span><span style="color:${color}">${item.status ? '✓' : '✗'}</span> ${item.name}</span>
                        ${!item.status ? `<button class="btn btn-sm btn-link fix-modal-item" data-cat="${catKey}" data-item="${itemKey}">Fix</button>` : ''}
                    </div>
                </div>`;
            }
            html += '</div>';
        }
        html += '<div class="alert alert-info mt-3 small">Use "Fix All" to resolve all issues automatically</div></div>';
        $('#globalModalBody').html(html);
        
        $('.fix-modal-item').off('click').on('click', async function() {
            const cat = $(this).data('cat');
            const item = $(this).data('item');
            const btn = $(this);
            btn.text('...');
            
            const formData = new URLSearchParams();
            formData.append('action', 'fix');
            formData.append('category', cat);
            formData.append('item', item);
            
            await fetch(url + 'system_check_api.php', { 
                method: 'POST', 
                body: formData,
                headers: headers
            });
            const resp = await fetch(url + 'system_check_api.php?action=check_all', {
                method: 'GET',
                headers: headers
            });
            const newData = await resp.json();
            if (newData.success) renderQuickCheckResults(newData.results);
        });
    }
    
    $('#globalFixAllBtn2').off('click').on('click', async function() {
        if (!confirm('Fix all issues? This may take a few moments.')) return;
        $(this).text('Fixing...').prop('disabled', true);
        const formData = new URLSearchParams();
        formData.append('action', 'fix_all');
        formData.append('confirm', 'yes');
        await fetch(url + 'system_check_api.php', { 
            method: 'POST', 
            body: formData,
            headers: headers
        });
        const resp = await fetch(url + 'system_check_api.php?action=check_all', {
            method: 'GET',
            headers: headers
        });
        const newData = await resp.json();
        if (newData.success) renderQuickCheckResults(newData.results);
        $(this).text('Fix All').prop('disabled', false);
    });
}

// ========== INITIALIZATION ==========
$(document).ready(function() {
    initHostSelector();
    
    const savedTab = localStorage.getItem('activeSystemTab');
    if (savedTab && $(savedTab).length) {
        const tabButton = document.querySelector(`.nav-tabs-apple .nav-link[data-bs-target="${savedTab}"]`);
        if (tabButton) {
            const tab = new bootstrap.Tab(tabButton);
            tab.show();
        }
    }
    
    $('.nav-tabs-apple .nav-link').on('shown.bs.tab', function(e) {
        const target = $(e.target).attr('data-bs-target');
        if (target) {
            localStorage.setItem('activeSystemTab', target);
        }
    });
    
    refreshAllData();
    loadTimezones();
    
    $('#systeminfo-tab').on('shown.bs.tab', function() {
        loadSystemInfo();
    });
    
    $('#network-tab').on('shown.bs.tab', function() {
        loadNetwork();
    });
    
    $('#datetime-tab').on('shown.bs.tab', function() {
        loadCurrentTimezone();
        loadNtpStatus();
    });
    
    $('#services-tab').on('shown.bs.tab', function() {
        loadServices();
        loadResources();
    });
    
    setInterval(() => {
        if (document.hidden) return;
        
        const activeTab = document.querySelector('.nav-tabs-apple .nav-link.active');
        if (activeTab) {
            const tabId = activeTab.getAttribute('data-bs-target');
            if (tabId === '#services') {
                loadResources();
                loadServices();
            } else if (tabId === '#network') {
                loadNetwork();
            } else if (tabId === '#datetime') {
                loadCurrentTimezone();
            }
        }
    }, 30000);
    
    setTimeout(function() { $('#applePreloader').fadeOut(500); }, 500);
});
</script>
<style>
.modal-apple .modal-content {
    border-radius: 24px;
    overflow: hidden;
}
.fix-modal-item {
    color: #007aff;
    text-decoration: none;
}
.fix-modal-item:hover {
    text-decoration: underline;
}
</style>
</body>
</html>