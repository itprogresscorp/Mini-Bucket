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

require_once 'config.php';
isAuthenticated();

if ($status_install == "0") {
    header('Location: install/system_check.php');
    exit;
}


$menu = require_once 'menu.php';
//$licru = '';
//$licenseRuFile = $licenseDir . 'LICENSE.ru.txt';
//if (file_exists($licenseRuFile)) {
//    $licru = file_get_contents($licenseRuFile);
//    if ($licru === false) {
//        $licru = 'Ошибка: Не удалось прочитать файл LICENSE.ru.txt';
//    }
//} else {
//    $licru = 'Файл LICENSE.ru.txt не найден';
//}

$licen = '';
$licenseEnFile = $licenseDir . 'LICENSE';
if (file_exists($licenseEnFile)) {
    $licen = file_get_contents($licenseEnFile);
    if ($licen === false) {
        $licen = 'Error: Unable to read LICENSE file';
    }
} else {
    $licen = 'LICENSE file not found';
}

$current_host_id = $_SESSION['current_host_id'] ?? 1;

try {
    $db = getDB();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}

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
}

$js_config = [
    'apiBaseUrl' => $api_base_url,
    'apiKey' => $api_key,
    'isLocalhost' => ($current_host_id == 1)
];

?>
<?php //echo $current_host_id; ?> 
<?php //echo $api_key; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mini-B Dashboard</title>
    <link href="lib/bootstrap-5.3.8-dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="lib/fontawesome-free-6.7.2-web/css/all.min.css">
    <script src="lib/chart.js-4.5.1/package/dist/chart.umd.js"></script>
    <script src="lib/bootstrap-5.3.8-dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="css/loader.css">
    <link rel="shortcut icon" href="css/icon.ico" type="image/x-icon">
	<!-- PWA Support -->
	<link rel="apple-touch-icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Crect width='100' height='100' fill='%23007aff'/%3E%3Ctext x='50' y='67' font-size='50' text-anchor='middle' fill='white'%3E📦%3C/text%3E%3C/svg%3E">
	<link rel="manifest" href="manifest.json">
	<meta name="theme-color" content="#007aff">
	<meta name="apple-mobile-web-app-capable" content="yes">
	<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
	<meta name="apple-mobile-web-app-title" content="Mini-B">
	<link rel="icon" href="/css/icon.ico" type="image/x-icon">
	<link rel="apple-touch-icon" href="/css/icon.ico">
	<meta name="msapplication-TileColor" content="#007aff">
	<meta name="msapplication-TileImage" content="icons/icon-144.png">
	<meta name="application-name" content="Mini-Bucket NAS">
	<script src="js/hosts_load.js"></script>
	<script src="js/crt_checker.js"></script>
	<script>
window.apiConfig = <?php echo json_encode($js_config); ?>;
//console.log('API Config loaded:', window.apiConfig);
</script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background: #f5f5f7;
            color: #1c1c1e;
        }

        .live-badge {
            background: #34c759;
            color: white;
            padding: 6px 14px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .live-badge i {
            font-size: 10px;
            animation: pulse 1.5s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
        }
        
        .timestamp {
            color: #8e8e93;
            font-size: 13px;
            font-weight: 500;
        }
        
        .app-container {
            display: flex;
            min-height: 100vh;
        }
       
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 32px;
        }
        
        @media (max-width: 1200px) {
            .metrics-grid { grid-template-columns: repeat(2, 1fr); }
        }
        
        @media (max-width: 768px) {
            .metrics-grid { grid-template-columns: 1fr; }
            .main-content { margin-left: 70px; padding: 16px; }
            .top-bar { padding: 10px 16px; }
            .top-bar-left h1 { font-size: 18px; }
            .timestamp { display: none; }
        }
        
        .metric-card {
            background: white;
            border-radius: 20px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            transition: all 0.2s;
            border: 1px solid #efeff4;
        }
        
        .metric-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
        }
        
        .metric-icon {
            width: 52px;
            height: 52px;
            background: linear-gradient(135deg, #007aff15, #5856d615);
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .metric-icon i {
            font-size: 26px;
            color: #007aff;
        }
        
        .metric-info {
            flex: 1;
        }
        
        .metric-label {
            font-size: 13px;
            color: #8e8e93;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        
        .metric-value {
            font-size: 32px;
            font-weight: 700;
            color: #1c1c1e;
        }
        
        .metric-value small {
            font-size: 14px;
            font-weight: 500;
            color: #8e8e93;
        }
        
        .progress-bar-custom {
            margin-top: 10px;
            height: 6px;
            background: #e5e5ea;
            border-radius: 6px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #007aff, #5856d6);
            border-radius: 6px;
            transition: width 0.3s;
        }
        
        .charts-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 32px;
        }
        
        @media (max-width: 1400px) {
            .charts-row { grid-template-columns: repeat(2, 1fr); }
        }
        
        @media (max-width: 768px) {
            .charts-row { grid-template-columns: 1fr; }
        }
        
        .chart-card {
            background: white;
            border-radius: 20px;
            padding: 20px;
            border: 1px solid #efeff4;
            transition: all 0.2s;
        }
        
        .chart-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        
        .chart-header h3 {
            font-size: 15px;
            font-weight: 600;
            margin-bottom: 16px;
            color: #1c1c1e;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .chart-header h3 i {
            color: #007aff;
        }
        
        .chart-card canvas {
            max-height: 180px;
            width: 100%;
        }
        
        .accordion-section {
            background: white;
            border-radius: 20px;
            margin-bottom: 20px;
            border: 1px solid #efeff4;
            overflow: hidden;
            transition: all 0.2s;
        }
        
        .accordion-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 18px 24px;
            cursor: pointer;
            transition: background 0.2s;
            background: white;
        }
        
        .accordion-header:hover {
            background: #f9f9fb;
        }
        
        .accordion-header h3 {
            margin: 0;
            font-size: 17px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .accordion-header h3 i {
            color: #007aff;
            width: 24px;
        }
        
        .header-buttons {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .manager-btn {
            font-size: 12px;
            padding: 4px 10px;
            border-radius: 20px;
            background: #007aff;
            color: white;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }
        
        .manager-btn:hover {
            background: #005fc1;
            color: white;
        }
        
        .health-badge {
            margin-left: 12px;
            font-size: 13px;
            font-weight: 500;
            padding: 4px 10px;
            border-radius: 20px;
            background: #f9f9fb;
        }
        
        .health-green { color: #34c759; }
        .health-green i { color: #34c759; }
        .health-warning { color: #ff9500; }
        .health-warning i { color: #ff9500; }
        .health-danger {
            color: #ff3b30;
            animation: blinkRed 1s ease-in-out infinite;
        }
        .health-danger i { color: #ff3b30; }
        
        @keyframes blinkRed {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; text-shadow: 0 0 4px rgba(255,59,48,0.5); }
        }
         
        .toggle-icon {
            color: #8e8e93;
            transition: transform 0.2s;
        }
        
        .accordion-header.open .toggle-icon {
            transform: rotate(180deg);
        }
        
        .accordion-body {
            padding: 0 24px 24px 24px;
            border-top: 1px solid #e9e9ef;
            display: none;
        }
        
        .accordion-body.open {
            display: block;
        }
        
        .disks-grid, .raid-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 16px;
            margin-top: 8px;
        }
        
		.lvm-grid {
            
            
            gap: 16px;
            margin-top: 8px;
        }
        
		.disk-io-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(420px, 1fr));
            gap: 20px;
            margin-top: 8px;
        }
        
        .disk-card, .raid-card, .lvm-card, .disk-io-card {
            background: #f9f9fb;
            border-radius: 16px;
            padding: 16px;
            transition: all 0.2s;
            border: 1px solid #efeff4;
        }
        
        .disk-card:hover, .raid-card:hover, .lvm-card:hover, .disk-io-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
        }
        
        .disk-card.problem, .raid-card.problem, .lvm-card.problem {
            animation: problemPulse 1s ease-in-out infinite;
            border-color: #ff3b30;
            background: rgba(255,59,48,0.02);
        }
        
        @keyframes problemPulse {
            0%, 100% { border-color: #ff3b30; box-shadow: 0 0 0 0 rgba(255,59,48,0.2); }
            50% { border-color: #ff3b30; box-shadow: 0 0 0 4px rgba(255,59,48,0.1); }
        }
        
        .disk-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            font-weight: 600;
        }
        
        .progress-bar-sm {
            height: 4px;
            background: #e5e5ea;
            border-radius: 4px;
            overflow: hidden;
            margin: 8px 0;
        }
        
        .progress-bar-fill {
            background: linear-gradient(90deg, #007aff, #5856d6);
            height: 100%;
            transition: width 0.3s;
        }
        
        .smart-passed { color: #34c759; }
        .smart-failed { 
            color: #ff3b30; 
            font-weight: 600;
            background: rgba(255,59,48,0.1);
            padding: 2px 8px;
            border-radius: 12px;
            display: inline-block;
        }
        
        .badge-raid {
            background: #5856d6;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
        }
        
        .badge-lvm {
            background: #32ade6;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
        }
        
        .disk-chart-container {
            min-height: 120px;
            margin: 12px 0;
        }
        
        .spinner-small {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid #e5e5ea;
            border-top-color: #007aff;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .disk-card-loading, .disk-io-card-loading {
            background: #f9f9fb;
            border-radius: 16px;
            padding: 40px;
            text-align: center;
            color: #8e8e93;
        }
        
        .system-info {
            background: white;
            border-radius: 20px;
            padding: 16px 24px;
            display: flex;
            gap: 24px;
            flex-wrap: wrap;
            border: 1px solid #efeff4;
            margin-top: 20px;
        }
        
        .info-card {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
        }
        
        .info-card i {
            color: #007aff;
            width: 20px;
        }
        
        .disk-info-sm {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            margin-bottom: 12px;
        }
        
        .raid-devices-list {
            font-size: 11px;
            color: #666;
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px solid #e5e5ea;
        }
        
        .lvm-section-header {
            margin-bottom: 16px;
            font-weight: 600;
            font-size: 14px;
            color: #1c1c1e;
            padding-bottom: 8px;
            border-bottom: 2px solid #007aff;
            display: inline-block;
        }
		
		.license-warnings {
			margin-top: 20px;
			padding-top: 15px;
			border-top: 1px solid rgba(255,255,255,0.1);
		}

		.warning-box {
			background: rgba(0,0,0,0.2);
			border-left: 3px solid;
			padding: 10px 15px;
			margin-bottom: 10px;
			border-radius: 4px;
		}

		.warning-box .red { color: #e74c3c; }
		.warning-box .orange { color: #e67e22; }
		.warning-box .yellow { color: #f1c40f; }

		.warning-box i {
			margin-right: 10px;
			font-size: 16px;
		}

		.warning-box strong {
			display: inline-block;
			margin-bottom: 5px;
		}

		.warning-box p {
			margin: 0;
			font-size: 12px;
			opacity: 0.8;
		}

		.summary-item .red { color: #e74c3c; }
		
    </style>
</head>
<body>

<div class="top-bar">
    <div class="top-bar-left">
        <h1><i class="fas fa-bucket"></i> Mini-B</h1>
    </div>
	
    <div class="top-bar-right">
        <div class="timestamp" id="timestamp">--:--:--</div>
		<div class="host-selector" style="margin-left: 20px;">
            <select id="hostSelector" style="background: rgba(255,255,255,0.9); border: 1px solid #ddd; border-radius: 20px; padding: 6px 30px 6px 15px; font-size: 14px; cursor: pointer;">
                <option value="">Loading...</option>
            </select>
        </div>
        <div class="btn-group">
            <button class="btn btn-sm btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-bars"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="diagnose.php"><i class="fa fa-heartbeat"></i> Diag Utils</a></li>
                <li><a class="dropdown-item" href="system_check.php" target="_blank"><i class="fa fa-bar-chart"></i> System Checker</a></li>
				<li><a class="dropdown-item" href="#" onclick="showLicenseModal()"><i class="fa fa-balance-scale"></i> License</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="#" onclick="refreshAll(); return false;"><i class="fas fa-sync-alt me-2"></i>Refresh</a></li>
            </ul>
        </div>
    </div>
</div>

<div class="app-container">
    <?php echo $menu; ?>
    <main class="main-content">
        <div class="metrics-grid">
            <div class="metric-card">
                <div class="metric-icon"><i class="fas fa-microchip"></i></div>
                <div class="metric-info">
                    <div class="metric-label">CPU Usage</div>
                    <div class="metric-value" id="cpuValue"><span class="spinner-small"></span></div>
				</div>
            </div>
            <div class="metric-card">
                <div class="metric-icon"><i class="fas fa-memory"></i></div>
                <div class="metric-info">
                    <div class="metric-label">Memory</div>
                    <div class="metric-value" id="memValue"><span class="spinner-small"></span></div>
                    <div class="progress-bar-custom"><div class="progress-fill" id="memProgress" style="width:0%"></div></div>
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-icon"><i class="fas fa-chart-line"></i></div>
                <div class="metric-info">
                    <div class="metric-label">Load Average</div>
                    <div class="metric-value" id="loadValue"><span class="spinner-small"></span></div>
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-icon"><i class="fas fa-thermometer-half"></i></div>
                <div class="metric-info">
                    <div class="metric-label">CPU Temp</div>
                    <div class="metric-value" id="tempValue"><span class="spinner-small"></span></div>
                </div>
            </div>
        </div>

        <div class="charts-row">
            <div class="chart-card">
                <div class="chart-header"><h3><i class="fas fa-microchip"></i> CPU Cores Usage</h3></div>
                <canvas id="cpuCoresChart" height="150"></canvas>
                <div id="cpuCoresLoading" class="disk-card-loading" style="display:none;"><div class="spinner-small"></div><span style="margin-left:10px;">Loading cores...</span></div>
            </div>
            <div class="chart-card">
                <div class="chart-header"><h3><i class="fas fa-chart-line"></i> CPU History (20 samples)</h3></div>
                <canvas id="cpuHistoryChart" height="150"></canvas>
            </div>
            <div class="chart-card">
                <div class="chart-header"><h3><i class="fas fa-chart-pie"></i> Memory Usage</h3></div>
                <canvas id="memoryChart" height="150"></canvas>
            </div>
            <div class="chart-card">
                <div class="chart-header"><h3><i class="fas fa-network-wired"></i> Network Traffic (MB/s)</h3></div>
                <canvas id="networkChart" height="150"></canvas>
            </div>
        </div>

        <div class="accordion-section" data-accordion="diskio">
            <div class="accordion-header">
                <h3><i class="fas fa-tachometer-alt"></i> Disk I/O Performance <span id="diskioHealthIcon" class="health-badge"></span></h3>
                <i class="fas fa-chevron-down toggle-icon"></i>
            </div>
            <div class="accordion-body">
                <div id="disksIOGrid" class="disk-io-grid"></div>
            </div>
        </div>

        <div class="accordion-section" data-accordion="physical">
            <div class="accordion-header">
                <h3><i class="fas fa-hdd"></i> Physical Disks & Partitions <span id="smartHealthIcon" class="health-badge"></span></h3>
                <div class="header-buttons">
                    <a href="disk_manager.php" class="manager-btn" onclick="event.stopPropagation();"><i class="fas fa-cog"></i> Disk Manager</a>
                    <i class="fas fa-chevron-down toggle-icon"></i>
                </div>
            </div>
            <div class="accordion-body">
                <div id="disksList" class="disks-grid"><div class="disk-card-loading"><div class="spinner-small"></div><span>Loading disk information...</span></div></div>
            </div>
        </div>

        <div id="raidSectionWrapper" class="accordion-section" data-accordion="raid" data-has-data="pending" style="display:none;">
            <div class="accordion-header">
                <h3><i class="fas fa-shield-alt"></i> RAID Arrays <span id="raidHealthIcon" class="health-badge"></span></h3>
                <div class="header-buttons">
                    <a href="raid_manager.php" class="manager-btn" onclick="event.stopPropagation();"><i class="fas fa-cog"></i> RAID Manager</a>
                    <i class="fas fa-chevron-down toggle-icon"></i>
                </div>
            </div>
            <div class="accordion-body">
                <div id="raidList" class="raid-grid"></div>
            </div>
        </div>

        <div id="lvmSectionWrapper" class="accordion-section" data-accordion="lvm" data-has-data="pending" style="display:none;">
            <div class="accordion-header">
                <h3><i class="fas fa-cubes"></i> LVM Volumes <span id="lvmHealthIcon" class="health-badge"></span></h3>
                <div class="header-buttons">
                    <a href="lvm_manager.php" class="manager-btn" onclick="event.stopPropagation();"><i class="fas fa-cog"></i> LVM Manager</a>
                    <i class="fas fa-chevron-down toggle-icon"></i>
                </div>
            </div>
            <div class="accordion-body">
                <div id="lvmList" class="lvm-grid"></div>
            </div>
        </div>

        <div class="accordion-section" data-accordion="network">
            <div class="accordion-header">
                <h3><i class="fas fa-network-wired"></i> Network Interfaces</h3>
                <i class="fas fa-chevron-down toggle-icon"></i>
            </div>
            <div class="accordion-body">
                <div id="networkInterfaces" class="disks-grid"><div class="disk-card-loading"><div class="spinner-small"></div><span>Loading network interfaces...</span></div></div>
            </div>
        </div>

        <div class="system-info">
            <div class="info-card"><i class="fas fa-clock"></i> Uptime: <strong id="uptime"><span class="spinner-small"></span></strong></div>
            <div class="info-card"><i class="fas fa-server"></i> Hostname: <strong id="hostname"><span class="spinner-small"></span></strong></div>
            <div class="info-card"><i class="fab fa-linux"></i> Kernel: <strong id="kernel"><span class="spinner-small"></span></strong></div>
            <div class="info-card"><i class="fas fa-tasks"></i> Processes: <strong id="processes"><span class="spinner-small"></span></strong></div>
			<div class="info-card"><i class="fa fa-cube"></i> Version: <strong id="current_version"><span class="spinner-small"></span></strong></div>
        </div>
    </main>
</div>

<div id="licenseModal" class="license-modal">
    <div class="license-modal-content">
        <div class="license-modal-header">
            <div class="license-icon">
                <i class="fas fa-balance-scale"></i>
            </div>
            <h2>GNU Affero General Public License</h2>
            <p class="license-version">Version 3, 19 November 2007</p>
            <button class="license-close-btn" onclick="closeLicenseModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="license-modal-body">
            <div class="license-info">
                <div class="license-badge">
                    <i class="fas fa-code-branch"></i>
                    <span>Mini-Bucket - NAS Control Panel</span>
                </div>
                <div class="license-badge">
                    <i class="far fa-copyright"></i>
                    <span>Copyright (C) 2026 Mamontov Roman Igorevich</span>
                </div>
            </div>
            
            <div class="license-summary">
                <h3><i class="fas fa-info-circle"></i> License Summary</h3>
                <div class="summary-grid">
                    <div class="summary-item">
                        <i class="fas fa-check-circle green"></i>
                        <div>
                            <strong>You can</strong>
                            <p>Use, modify, and distribute this software</p>
                        </div>
                    </div>
                    <div class="summary-item">
                        <i class="fas fa-exclamation-triangle orange"></i>
                        <div>
                            <strong>You must</strong>
                            <p>Disclose source code when distributing</p>
                        </div>
                    </div>
                    <div class="summary-item">
                        <i class="fas fa-share-alt blue"></i>
                        <div>
                            <strong>Same license</strong>
                            <p>Distribute under the same AGPL-3.0 license</p>
                        </div>
                    </div>
                    <div class="summary-item">
                        <i class="fas fa-server purple"></i>
                        <div>
                            <strong>Network use</strong>
                            <p>Using over a network requires source disclosure</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="license-full-text">
                <h3><i class="fas fa-file-alt"></i> Full License Text</h3>
                <div class="license-text-scroll">
                    <pre>
                    <?php echo $licen; ?>
					<hr>
					<?php //echo $licru; ?>
                    </pre>
                </div>
            </div>
        </div>
        
        <div class="license-modal-footer">
            <a href="https://www.gnu.org/licenses/agpl-3.0.html" target="_blank" class="license-link">
                <i class="fas fa-external-link-alt"></i> Read online at gnu.org
            </a>
            <button class="btn-license-close" onclick="closeLicenseModal()">
                <i class="fas fa-check"></i> I understand
            </button>
        </div>
    </div>
</div>



<script>
 const url = "<?php echo $current_host_id == 1 ? '/api/' : rtrim($host_url, '/') . '/'; ?>";
 
    let cpuHistoryChart, networkChart, memoryChart, cpuCoresChart;
    let diskCharts = {};
    let cpuHistory = Array(20).fill(0);
    let rxHistory = Array(20).fill(0);
    let txHistory = Array(20).fill(0);
    let diskReadHistory = {}, diskWriteHistory = {}, diskQueueHistory = {};
    let isFirstLoad = true;
    
    let raidChecked = false;
    let lvmChecked = false;
    let hasRaid = false;
    let hasLvm = false;
    
    let raidLoadPromise = null;
    let lvmLoadPromise = null;
    
    function initCharts() {
        const cpuCtx = document.getElementById('cpuHistoryChart').getContext('2d');
        cpuHistoryChart = new Chart(cpuCtx, {
            type: 'line',
            data: { labels: Array(20).fill(''), datasets: [{ label: 'CPU %', data: cpuHistory, borderColor: '#007aff', backgroundColor: 'rgba(0,122,255,0.05)', borderWidth: 2, pointRadius: 0, fill: true, tension: 0.3 }] },
            options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { display: false } }, scales: { y: { min: 0, max: 100, grid: { color: '#e5e5ea' } } } }
        });
        
        const netCtx = document.getElementById('networkChart').getContext('2d');
        networkChart = new Chart(netCtx, {
            type: 'line',
            data: { labels: Array(20).fill(''), datasets: [{ label: 'RX MB/s', data: rxHistory, borderColor: '#34c759', borderWidth: 2, pointRadius: 0, fill: false }, { label: 'TX MB/s', data: txHistory, borderColor: '#ff9500', borderWidth: 2, pointRadius: 0, fill: false }] },
            options: { responsive: true, maintainAspectRatio: true, scales: { y: { title: { display: true, text: 'MB/s' } } } }
        });
        
          const memCtx = document.getElementById('memoryChart').getContext('2d');
			memoryChart = new Chart(memCtx, {
				type: 'doughnut',
				data: { 
					labels: ['Used RAM', 'Free RAM', 'Swap Used'], 
					datasets: [{ 
						data: [0,0,0], 
						backgroundColor: ['#007aff','#34c759','#ff9500'], 
						borderWidth: 0 
					}] 
				},
				options: { 
					responsive: true, 
					maintainAspectRatio: true,
					plugins: { 
						legend: { 
							position: 'left',
							align: 'center',
							labels: { 
								boxWidth: 12,
								boxHeight: 12,
								padding: 10,
								font: { size: 11 }
							} 
						},
						tooltip: { enabled: true }
					},
					layout: {
						padding: {
							left: 5,
							right: 5,
							top: 5,
							bottom: 5
						}
					}
				}
			});
        
        const coresCtx = document.getElementById('cpuCoresChart').getContext('2d');
        cpuCoresChart = new Chart(coresCtx, {
            type: 'bar',
            data: { labels: [], datasets: [{ label: 'Usage %', data: [], backgroundColor: '#007aff', borderRadius: 4 }] },
            options: { responsive: true, scales: { y: { min: 0, max: 100 } }, plugins: { legend: { display: false } } }
        });
    }
    

    function createDiskChart(diskName, container) {
        if (!container) return;
        container.innerHTML = '';
        const canvas = document.createElement('canvas');
        canvas.id = `disk-chart-${diskName}`;
        canvas.style.width = '100%';
        canvas.style.height = '120px';
        container.appendChild(canvas);
        
        if (!diskReadHistory[diskName]) diskReadHistory[diskName] = Array(20).fill(0);
        if (!diskWriteHistory[diskName]) diskWriteHistory[diskName] = Array(20).fill(0);
        if (!diskQueueHistory[diskName]) diskQueueHistory[diskName] = Array(20).fill(0);
        
        const ctx = canvas.getContext('2d');
        diskCharts[diskName] = new Chart(ctx, {
            type: 'line',
            data: {
                labels: Array(20).fill(''),
                datasets: [
                    { label: 'Read MB/s', data: diskReadHistory[diskName], borderColor: '#34c759', backgroundColor: 'rgba(52,199,89,0.1)', borderWidth: 2, pointRadius: 0, fill: true, yAxisID: 'y' },
                    { label: 'Write MB/s', data: diskWriteHistory[diskName], borderColor: '#ff9500', backgroundColor: 'rgba(255,149,0,0.1)', borderWidth: 2, pointRadius: 0, fill: true, yAxisID: 'y' },
                    { label: 'Queue', data: diskQueueHistory[diskName], borderColor: '#ff3b30', borderWidth: 1.5, pointRadius: 0, fill: false, yAxisID: 'y1', borderDash: [5,5] }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                scales: {
                    y: { position: 'left', title: { display: true, text: 'MB/s', font: { size: 9 } }, min: 0 },
                    y1: { position: 'right', title: { display: true, text: 'Queue', font: { size: 9 } }, min: 0, max: 20, grid: { drawOnChartArea: false } }
                },
                plugins: { legend: { position: 'top', labels: { boxWidth: 10, font: { size: 9 } } }, tooltip: { mode: 'index', intersect: false } }
            }
        });
    }
    
    function updateDiskCards(disksIO) {
        const grid = document.getElementById('disksIOGrid');
        if (!grid) return;
        
        if (!disksIO || Object.keys(disksIO).length === 0) {
            if (grid.innerHTML !== '<div class="disk-card-loading">No disk I/O data available</div>') {
                grid.innerHTML = '<div class="disk-card-loading">No disk I/O data available</div>';
                document.getElementById('diskioHealthIcon').innerHTML = '<i class="fas fa-info-circle"></i> No disks';
            }
            return;
        }
        
        const currentDisks = new Set(Object.keys(diskCharts));
        const newDisks = new Set(Object.keys(disksIO));
        
        for (const disk of currentDisks) {
            if (!newDisks.has(disk)) {
                if (diskCharts[disk]) { diskCharts[disk].destroy(); delete diskCharts[disk]; }
                delete diskReadHistory[disk];
                delete diskWriteHistory[disk];
                delete diskQueueHistory[disk];
                const card = document.getElementById(`disk-card-${disk}`);
                if (card) card.remove();
            }
        }
        
        for (const [diskName, stats] of Object.entries(disksIO)) {
            let card = document.getElementById(`disk-card-${diskName}`);
            if (!card) {
                card = document.createElement('div');
                card.className = 'disk-io-card';
                card.id = `disk-card-${diskName}`;
                card.innerHTML = `
                    <h4 style="margin:0 0 12px 0; font-size:16px;"><i class="fas fa-hdd"></i> ${diskName}</h4>
                    <div class="disk-info-sm">
                        <span>📖 Read: <strong id="read-${diskName}">${stats.read_mb_s}</strong> MB/s</span>
                        <span>✍️ Write: <strong id="write-${diskName}">${stats.write_mb_s}</strong> MB/s</span>
                        <span>⏳ Queue: <strong id="queue-${diskName}">${stats.queue_length}</strong></span>
                    </div>
                    <div id="disk-io-chart-${diskName}" class="disk-chart-container"></div>
                    <div style="font-size:11px; display:flex; justify-content:space-between; color:#8e8e93; margin-top:8px;">
                        <span>Total Read: <span id="total-read-${diskName}">${stats.total_read_mb}</span> MB</span>
                        <span>Total Write: <span id="total-write-${diskName}">${stats.total_write_mb}</span> MB</span>
                    </div>
                `;
                grid.appendChild(card);
                const container = document.getElementById(`disk-io-chart-${diskName}`);
                if (container) createDiskChart(diskName, container);
            } else {
                const readSpan = document.getElementById(`read-${diskName}`);
                const writeSpan = document.getElementById(`write-${diskName}`);
                const queueSpan = document.getElementById(`queue-${diskName}`);
                const totalReadSpan = document.getElementById(`total-read-${diskName}`);
                const totalWriteSpan = document.getElementById(`total-write-${diskName}`);
                if (readSpan) readSpan.textContent = stats.read_mb_s;
                if (writeSpan) writeSpan.textContent = stats.write_mb_s;
                if (queueSpan) queueSpan.textContent = stats.queue_length;
                if (totalReadSpan) totalReadSpan.textContent = stats.total_read_mb;
                if (totalWriteSpan) totalWriteSpan.textContent = stats.total_write_mb;
            }
            
            if (diskCharts[diskName]) {
                if (!diskReadHistory[diskName]) diskReadHistory[diskName] = Array(20).fill(0);
                diskReadHistory[diskName].push(parseFloat(stats.read_mb_s) || 0);
                if (diskReadHistory[diskName].length > 20) diskReadHistory[diskName].shift();
                
                diskWriteHistory[diskName].push(parseFloat(stats.write_mb_s) || 0);
                if (diskWriteHistory[diskName].length > 20) diskWriteHistory[diskName].shift();
                
                diskQueueHistory[diskName].push(parseFloat(stats.queue_length) || 0);
                if (diskQueueHistory[diskName].length > 20) diskQueueHistory[diskName].shift();
                
                diskCharts[diskName].data.datasets[0].data = [...diskReadHistory[diskName]];
                diskCharts[diskName].data.datasets[1].data = [...diskWriteHistory[diskName]];
                diskCharts[diskName].data.datasets[2].data = [...diskQueueHistory[diskName]];
                diskCharts[diskName].update('none');
            }
        }
        
        document.getElementById('diskioHealthIcon').innerHTML = '<i class="fas fa-check-circle health-green"></i> Active';
    }
    
    function getSmartHealthStatus(disks) {
        if (!disks || disks.length === 0) return { class: '', text: '', icon: '' };
        let hasBadSectors = false;
        let totalBadSectors = 0;
        for (let d of disks) {
            if (d.smart && d.smart.bad_sectors > 0) {
                hasBadSectors = true;
                totalBadSectors += d.smart.bad_sectors;
            }
        }
        if (hasBadSectors) {
            return { class: 'health-danger', text: `⚠️ ${totalBadSectors} bad sectors`, icon: 'fa-exclamation-triangle' };
        }
        return { class: 'health-green', text: '✓ SMART OK', icon: 'fa-check-circle' };
    }
    
    function getRaidHealthStatus(raids) {
        if (!raids || raids.length === 0) return { class: '', text: '', icon: '' };
        let hasDegraded = false, hasFailed = false, hasReadOnly = false;
        for (let r of raids) {
            if (r.degraded) hasDegraded = true;
            if (r.status === 'auto-read-only' || r.status === 'readonly') hasReadOnly = true;
            if (r.failed_disks > 0 || r.status === 'inactive' || r.status === 'failed' || (r.health_state && r.health_state !== 'active' && r.health_state !== 'clean')) hasFailed = true;
        }
        if (hasFailed) return { class: 'health-danger', text: '⚠️ RAID Failed', icon: 'fa-exclamation-triangle' };
        if (hasReadOnly) return { class: 'health-warning', text: '⚠️ Read-only', icon: 'fa-exclamation-triangle' };
        if (hasDegraded) return { class: 'health-warning', text: '⚠️ Degraded', icon: 'fa-exclamation-triangle' };
        return { class: 'health-green', text: '✓ Healthy', icon: 'fa-check-circle' };
    }
    
    function getLvmHealthStatus(lvs, vgs) {
        if ((!lvs || lvs.length === 0) && (!vgs || vgs.length === 0)) return { class: '', text: '', icon: '' };
        return { class: 'health-green', text: '✓ Active', icon: 'fa-check-circle' };
    }
    
    function initAccordion() {
        const sections = document.querySelectorAll('.accordion-section');
        sections.forEach(section => {
            const header = section.querySelector('.accordion-header');
            const body = section.querySelector('.accordion-body');
            const toggleIcon = section.querySelector('.toggle-icon');
            const key = `accordion_${section.dataset.accordion}`;
            const isOpen = sessionStorage.getItem(key) !== 'closed';
            if (isOpen) { 
                body.classList.add('open'); 
                header.classList.add('open');
            }
            
            header.addEventListener('click', (e) => {
                if (e.target.closest('.manager-btn')) {
                    return;
                }
                body.classList.toggle('open');
                header.classList.toggle('open');
                sessionStorage.setItem(key, body.classList.contains('open') ? 'open' : 'closed');
            });
        });
    }
    
    function isSectionOpen(accordionName) {
        const section = document.querySelector(`[data-accordion="${accordionName}"]`);
        if (!section) return false;
        const body = section.querySelector('.accordion-body');
        return body ? body.classList.contains('open') : false;
    }
    
    async function updateLightMetrics() {
        try {
            const metricsRes = await fetch(url + 'dashboard_api.php?action=metrics', {
				headers: {
					'X-API-Key': window.apiConfig.apiKey
				}
			});
            const data = await metricsRes.json();
            
            if (isFirstLoad) {
                const preloader = document.getElementById('applePreloader');
                if (preloader) preloader.style.display = 'none';
                isFirstLoad = false;
            }
            
            document.getElementById('cpuValue').innerHTML = (data.cpu || 0) + '<small>%</small>';
            document.getElementById('loadValue').innerHTML = (data.load?.[0] || 0).toFixed(2);
            document.getElementById('tempValue').innerHTML = data.cpu_temp || 'N/A';
            document.getElementById('uptime').innerHTML = data.system?.uptime || '-';
            document.getElementById('hostname').innerHTML = data.system?.hostname || '-';
            document.getElementById('kernel').innerHTML = data.system?.kernel || '-';
            document.getElementById('processes').innerHTML = data.system?.processes || '-';
			document.getElementById('current_version').innerHTML = data.system?.current_version || '-';
           // document.getElementById('timestamp').innerHTML = data.timestamp || '-';
            
            if (data.memory) {
                document.getElementById('memValue').innerHTML = data.memory.used + '<small> / ' + data.memory.total + ' MB</small>';
                document.getElementById('memProgress').style.width = data.memory.percent + '%';
                memoryChart.data.datasets[0].data = [data.memory.used, data.memory.available, data.memory.swap_used];
                memoryChart.update();
            }
            
            cpuHistory.push(data.cpu || 0);
            if (cpuHistory.length > 20) cpuHistory.shift();
            cpuHistoryChart.data.datasets[0].data = [...cpuHistory];
            cpuHistoryChart.update();
            
            if (data.cpu_cores && data.cpu_cores.length) {
                document.getElementById('cpuCoresLoading').style.display = 'none';
                document.getElementById('cpuCoresChart').style.display = 'block';
                cpuCoresChart.data.labels = data.cpu_cores.map((_, i) => `Core ${i}`);
                cpuCoresChart.data.datasets[0].data = data.cpu_cores;
                cpuCoresChart.update();
            } else {
                document.getElementById('cpuCoresLoading').style.display = 'flex';
                document.getElementById('cpuCoresChart').style.display = 'none';
            }
            
            if (data.network_traffic) {
                const mainIface = Object.keys(data.network_traffic)[0];
                if (mainIface) {
                    rxHistory.push(data.network_traffic[mainIface].rx_mb_s);
                    txHistory.push(data.network_traffic[mainIface].tx_mb_s);
                    if (rxHistory.length > 20) rxHistory.shift();
                    if (txHistory.length > 20) txHistory.shift();
                    networkChart.data.datasets[0].data = [...rxHistory];
                    networkChart.data.datasets[1].data = [...txHistory];
                    networkChart.update();
                }
            }
            
            if (isSectionOpen('diskio') && data.disks_io && Object.keys(data.disks_io).length > 0) {
                updateDiskCards(data.disks_io);
            } else if (isSectionOpen('diskio')) {
                const grid = document.getElementById('disksIOGrid');
                if (grid && grid.innerHTML !== '<div class="disk-card-loading">No disk I/O data available</div>') {
                    grid.innerHTML = '<div class="disk-card-loading">No disk I/O data available</div>';
                    document.getElementById('diskioHealthIcon').innerHTML = '<i class="fas fa-info-circle"></i> No disks';
                }
            }
            
            if (isSectionOpen('physical') && data.disks && data.disks.length) {
				let disksHtml = '';
				for (let disk of data.disks) {
					const smartStatus = disk.smart.status;
					const badSectors = disk.smart.bad_sectors || 0;
					const isDiskProblem = (badSectors > 0 || smartStatus === 'FAILED');
					const smartClass = isDiskProblem ? 'smart-failed' : 'smart-passed';
					const smartText = isDiskProblem ? `⚠️ ${badSectors} bad sectors` : `✓ SMART: ${smartStatus}`;
					const percentClass = disk.total_percent > 90 ? 'text-danger' : (disk.total_percent > 75 ? 'text-warning' : '');
					disksHtml += `
						<div class="disk-card ${isDiskProblem ? 'problem' : ''}">
                            <div class="disk-info">
                                <span><i class="fas fa-hdd"></i> <strong>${disk.name}</strong> - ${disk.model}</span>
                                <span>${disk.size}</span>
                            </div>
                            <div style="font-size:12px; margin-bottom:8px;">
                                <span class="${smartClass}">🔍 ${smartText}</span>
                                ${disk.smart.temp !== 'N/A' ? ` | 🌡️ ${disk.smart.temp}` : ''}
                                ${badSectors > 0 ? `<span class="smart-failed ml-2"> | Realloc: ${disk.smart.realloc_sectors || 0}, Pending: ${disk.smart.pending_sectors || 0}</span>` : ''}
                            </div>
                            <div style="margin-bottom:12px;">
                                <span>Total Usage: ${disk.total_used} / ${disk.total_size}</span>
                                <span class="${percentClass}"> (${disk.total_percent}%)</span>
                                <div class="progress-bar-sm"><div class="progress-bar-fill" style="width: ${disk.total_percent}%"></div></div>
                            </div>
                            <div>
                                ${disk.partitions.map(part => `
                                    <div style="padding:6px 0; border-bottom:1px solid #e5e5ea; font-size:12px;">
                                        <div><i class="fas ${part.mount ? 'fa-folder-open' : (part.is_swap ? 'fa-exchange-alt' : 'fa-database')}"></i> ${part.name} (${part.fstype || 'unknown'})${part.mount ? ` - ${part.mount}` : ''}</div>
                                        ${!part.is_swap && part.mount ? `<div style="font-size:11px; color:#8e8e93;">Used: ${part.used} / ${part.size} (${part.percent}%)</div><div class="progress-bar-sm"><div class="progress-bar-fill" style="width: ${part.percent}%"></div></div>` : (part.is_swap ? `<div style="font-size:11px;">Swap: ${part.used} / ${part.size} (${part.percent}%)</div>` : '')}
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                    `;
                }
                document.getElementById('disksList').innerHTML = disksHtml;
                const smartHealth = getSmartHealthStatus(data.disks);
                document.getElementById('smartHealthIcon').innerHTML = `<i class="fas ${smartHealth.icon} ${smartHealth.class}"></i> ${smartHealth.text}`;
            }
            
            if (isSectionOpen('network') && data.network_interfaces && data.network_interfaces.length) {
                document.getElementById('networkInterfaces').innerHTML = data.network_interfaces.map(net => `
                    <div class="disk-card">
                        <div class="disk-info">
                            <span><i class="fas fa-plug"></i> <strong>${net.interface}</strong></span>
                            <span>${net.ip}</span>
                        </div>
                        <div style="font-size:13px; margin-top:8px;">
                            📥 RX: ${net.rx_mb} MB | 📤 TX: ${net.tx_mb} MB<br>
                            🔌 MAC: ${net.mac}
                        </div>
                    </div>
                `).join('');
            }
            
        } catch (err) {
        console.error('Light metrics error:', err);
        
        
    }
}
    
    async function loadRaidData() {
        if (raidChecked && !hasRaid) return;
        
        if (raidLoadPromise) return raidLoadPromise;
        
        raidLoadPromise = (async () => {
            try {
                const raidRes = await fetch(url + 'raid_api.php?action=get_all_raid', {
                headers: {
                    'X-API-Key': window.apiConfig.apiKey
                }
            });
                const raidData = await raidRes.json();
                const raidSection = document.getElementById('raidSectionWrapper');
                
                if (raidData.success && raidData.raid && raidData.raid.length) {
                    hasRaid = true;
                    raidSection.style.display = 'block';
                    raidSection.dataset.hasData = 'true';
                    
                    let raidHtml = '';
                    let raidHasProblem = false;
                    
                    for (let r of raidData.raid) {
                        const isProblemStatus = r.status === 'inactive' || r.status === 'auto-read-only' || r.status === 'readonly' || r.status === 'failed';
                        const hasProblem = r.degraded || r.failed_disks > 0 || isProblemStatus;
                        if (hasProblem) raidHasProblem = true;
                        
                        let statusClass = '';
                        let statusText = r.status || 'unknown';
                        if (r.status === 'active' || r.status === 'clean') {
                            statusClass = 'health-green';
                        } else if (r.status === 'auto-read-only' || r.status === 'readonly') {
                            statusClass = 'health-warning';
                            statusText = r.status + ' ⚠️';
                        } else if (hasProblem) {
                            statusClass = 'text-danger';
                        }
                        
                        let devicesHtml = '';
                        let deviceNames = [];
                        
                        if (r.devices && Array.isArray(r.devices)) {
                            deviceNames = r.devices.map(dev => {
                                if (typeof dev === 'string') return dev;
                                if (dev && typeof dev === 'object') {
                                    return dev.name || dev.device || dev.path || JSON.stringify(dev);
                                }
                                return String(dev);
                            });
                        } else if (r.devices && typeof r.devices === 'string') {
                            deviceNames = r.devices.split(',').map(d => d.trim());
                        }
                        
                        const workingCount = r.working_disks !== undefined ? r.working_disks : 
                                            (r.active_devices !== undefined ? r.active_devices : 
                                            (deviceNames.filter(d => d && d !== 'removed' && d !== 'failed').length));
                        const totalCount = r.total_disks !== undefined ? r.total_disks : deviceNames.length;
                        
                        devicesHtml = `<div class="raid-devices-list"><strong>Devices:</strong> ${workingCount || 0}/${totalCount || 0} active<br>`;
                        if (deviceNames.length > 0) {
                            devicesHtml += deviceNames.map(d => `<span style="font-family:monospace; display:inline-block; background:#e5e5ea; padding:2px 6px; border-radius:4px; margin:2px;">${d}</span>`).join(' ');
                        }
                        devicesHtml += `</div>`;
                        
                        let raidSize = r.size || r.size_formatted || 'N/A';
                        if (raidSize === 'N/A' && r.total_size) {
                            raidSize = r.total_size;
                        }
                        
                        raidHtml += `
                            <div class="raid-card ${hasProblem ? 'problem' : ''}">
                                <div class="disk-info">
                                    <span><i class="fas fa-shield-alt"></i> <strong>${r.name}</strong> <span class="badge-raid">${r.level || 'RAID'}</span></span>
                                    <span>${raidSize}</span>
                                </div>
                                <div style="margin:8px 0;">
                                    Status: <span class="${statusClass}">${statusText}${r.degraded ? ' (DEGRADED)' : ''}</span>
                                    ${r.failed_disks > 0 ? `<span class="text-danger"> | Failed disks: ${r.failed_disks}</span>` : ''}
                                </div>
                                ${devicesHtml}
                                ${r.sync_percent ? `<div class="progress-bar-sm mt-2"><div class="progress-bar-fill" style="width:${r.sync_percent}%"></div><small>Sync ${r.sync_percent}%</small></div>` : ''}
                        `;
                        
                        if (r.mount_point && r.mount_point !== '' && r.mount_point !== 'N/A' && r.mount_point !== '-') {
                            let usagePercent = 0;
                            let usedSize = '?';
                            let totalSize = '?';
                            
                            if (r.used_percent !== undefined) {
                                usagePercent = r.used_percent;
                                usedSize = r.used_formatted || '?';
                                totalSize = r.size_formatted || raidSize;
                            } else {
                                try {
                                    const dfRes = await fetch(url + `disk_usage_api.php?path=${encodeURIComponent(r.mount_point)}`, {
										headers: {
											'X-API-Key': window.apiConfig.apiKey
										}
									});
                                    const dfData = await dfRes.json();
                                    if (dfData.success) {
                                        usagePercent = dfData.percent || 0;
                                        usedSize = dfData.used || '?';
                                        totalSize = dfData.size || '?';
                                    }
                                } catch(e) { console.error('DF error for RAID:', e); }
                            }
                            
                            raidHtml += `
                                <div class="mt-2" style="margin-top:12px;">
                                    <div><i class="fas fa-folder-open"></i> Mount: ${r.mount_point}</div>
                                    <div style="font-size:12px; margin-top:6px;">Usage: ${usedSize} / ${totalSize}</div>
                                    <div class="progress-bar-sm"><div class="progress-bar-fill" style="width: ${usagePercent}%; background: linear-gradient(90deg, #34c759, #28a745);"></div></div>
                                </div>
                            `;
                        }
                        
                        raidHtml += `</div>`;
                    }
                    document.getElementById('raidList').innerHTML = raidHtml;
                    
                    const raidHealth = getRaidHealthStatus(raidData.raid);
                    document.getElementById('raidHealthIcon').innerHTML = `<i class="fas ${raidHealth.icon} ${raidHealth.class}"></i> ${raidHealth.text}`;
                } else {
                    hasRaid = false;
                    raidSection.style.display = 'none';
                    raidSection.dataset.hasData = 'false';
                }
                raidChecked = true;
                raidLoadPromise = null;
            } catch (e) {
                console.error('RAID error:', e);
                const raidSection = document.getElementById('raidSectionWrapper');
                raidSection.style.display = 'none';
                raidChecked = true;
                raidLoadPromise = null;
            }
        })();
        
        return raidLoadPromise;
    }
    
function showLicenseModal() {
    const modal = document.getElementById('licenseModal');
    if (modal) {
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
    }
}

function closeLicenseModal() {
    const modal = document.getElementById('licenseModal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }
}

window.onclick = function(event) {
    const modal = document.getElementById('licenseModal');
    if (event.target === modal) {
        closeLicenseModal();
    }
}

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const modal = document.getElementById('licenseModal');
        if (modal && modal.style.display === 'block') {
            closeLicenseModal();
        }
    }
});
	
    async function loadLvmData() {
        if (lvmChecked && !hasLvm) return;
        
        if (lvmLoadPromise) return lvmLoadPromise;
        
        lvmLoadPromise = (async () => {
            try {
                const lvmRes = await fetch(url + 'lvm_api.php?action=get_all_lvm', {
				headers: {
					'X-API-Key': window.apiConfig.apiKey
				}
			});
                const lvmData = await lvmRes.json();
                const lvmSection = document.getElementById('lvmSectionWrapper');
                
                if (lvmData.success && ((lvmData.lvs && lvmData.lvs.length) || (lvmData.vgs && lvmData.vgs.length))) {
                    hasLvm = true;
                    lvmSection.style.display = 'block';
                    lvmSection.dataset.hasData = 'true';
                    
                    let lvmHtml = '';
                    
                    if (lvmData.vgs && lvmData.vgs.length) {
                        const sortedVgs = [...lvmData.vgs].sort((a, b) => (a.name || '').localeCompare(b.name || ''));
                        lvmHtml += `<div class="lvm-section-header"><i class="fas fa-database"></i> Volume Groups (${sortedVgs.length})</div>`;
                        lvmHtml += `<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 16px; margin-bottom: 24px;">`;
                        lvmHtml += sortedVgs.map(vg => `
                            <div class="lvm-card">
                                <div class="disk-info">
                                    <span><i class="fas fa-layer-group"></i> <strong>${vg.name}</strong></span>
                                    <span>${vg.size_formatted}</span>
                                </div>
                                <div style="margin: 8px 0;">
                                    <div>Used: ${vg.used_formatted} / ${vg.size_formatted}</div>
                                    <div class="progress-bar-sm"><div class="progress-bar-fill" style="width:${vg.used_percent}%"></div></div>
                                </div>
                                <div style="font-size: 11px; color: #8e8e93; display: flex; gap: 16px; margin-top: 8px;">
                                    <span><i class="fas fa-hdd"></i> PV: ${vg.pv_count}</span>
                                    <span><i class="fas fa-chart-line"></i> LV: ${vg.lv_count}</span>
                                    <span><i class="fas fa-chart-pie"></i> ${vg.used_percent}% used</span>
                                </div>
                            </div>
                        `).join('');
                        lvmHtml += `</div>`;
                    }
                    
                    if (lvmData.lvs && lvmData.lvs.length) {
                        const sortedLvs = [...lvmData.lvs].sort((a, b) => {
                            const vgCompare = (a.vg_name || '').localeCompare(b.vg_name || '');
                            if (vgCompare !== 0) return vgCompare;
                            return (a.name || '').localeCompare(b.name || '');
                        });
                        
                        lvmHtml += `<div class="lvm-section-header" style="margin-top: 8px;"><i class="fas fa-chart-simple"></i> Logical Volumes (${sortedLvs.length})</div>`;
                        lvmHtml += `<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 16px;">`;
                        
                        let currentVg = '';
                        for (let lv of sortedLvs) {
                            if (currentVg !== lv.vg_name && currentVg !== '') {
                                lvmHtml += `</div><div style="margin-top: 8px;"></div><div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 16px;">`;
                            }
                            currentVg = lv.vg_name;
                            
                            let usagePercent = 0;
                            let usedSize = '?';
                            let totalSize = lv.size_formatted;
                            
                            if (lv.used_percent !== undefined && lv.used_percent > 0) {
                                usagePercent = lv.used_percent;
                                usedSize = lv.used_formatted || '?';
                            } else if (lv.mount_point && lv.mount_point !== '' && lv.mount_point !== 'N/A' && lv.mount_point !== 'none') {
                                try {
                                    const dfRes = await fetch(url + `disk_usage_api.php?path=${encodeURIComponent(lv.mount_point)}`, {
				headers: {
					'X-API-Key': window.apiConfig.apiKey
				}
			});
                                    const dfData = await dfRes.json();
                                    if (dfData.success) {
                                        usagePercent = dfData.percent || 0;
                                        usedSize = dfData.used || '?';
                                        totalSize = dfData.size || lv.size_formatted;
                                    }
                                } catch(e) { console.error('DF error for LV:', e); }
                            }
                            
                            lvmHtml += `
                                <div class="lvm-card">
                                    <div class="disk-info">
                                        <span><i class="fas fa-chart-simple"></i> <strong>${lv.name}</strong> <span class="badge-lvm">${lv.vg_name}</span></span>
                                        <span>${lv.size_formatted}</span>
                                    </div>
                                    ${lv.mount_point && lv.mount_point !== '' && lv.mount_point !== 'N/A' && lv.mount_point !== 'none' ? `
                                        <div style="margin: 8px 0;">
                                            <div><i class="fas fa-folder-open"></i> ${lv.mount_point}</div>
                                            <div style="font-size: 12px; margin-top: 6px;">Used: ${usedSize} / ${totalSize}</div>
                                            <div class="progress-bar-sm"><div class="progress-bar-fill" style="width: ${usagePercent}%; background: linear-gradient(90deg, #34c759, #28a745);"></div></div>
                                        </div>
                                    ` : ''}
                                    ${lv.filesystem && lv.filesystem !== '' && lv.filesystem !== 'unknown' ? `
                                        <div style="font-size: 11px; color: #8e8e93; margin-top: 8px;">
                                            <i class="fas fa-file-alt"></i> FS: ${lv.filesystem}
                                        </div>
                                    ` : ''}
                                    ${!lv.mount_point || lv.mount_point === '' || lv.mount_point === 'none' ? `
                                        <div style="font-size: 11px; color: #ff9500; margin-top: 8px;">
                                            <i class="fas fa-info-circle"></i> Not mounted
                                        </div>
                                    ` : ''}
                                </div>
                            `;
                        }
                        lvmHtml += `</div>`;
                    }
                    
                    document.getElementById('lvmList').innerHTML = lvmHtml || '<div class="disk-card-loading">No LVM data available</div>';
                    const lvmHealth = getLvmHealthStatus(lvmData.lvs, lvmData.vgs);
                    document.getElementById('lvmHealthIcon').innerHTML = `<i class="fas ${lvmHealth.icon} ${lvmHealth.class}"></i> ${lvmHealth.text}`;
                } else {
                    hasLvm = false;
                    lvmSection.style.display = 'none';
                    lvmSection.dataset.hasData = 'false';
                }
                lvmChecked = true;
                lvmLoadPromise = null;
            } catch (e) {
                console.error('LVM error:', e);
                const lvmSection = document.getElementById('lvmSectionWrapper');
                lvmSection.style.display = 'none';
                lvmChecked = true;
                lvmLoadPromise = null;
            }
        })();
        
        return lvmLoadPromise;
    }
    
    function init() {
        initCharts();
        initAccordion();
        updateLightMetrics();
        
        loadRaidData();
        loadLvmData();
        
        setInterval(updateLightMetrics, 2000);
        
        setInterval(() => {
            if (hasRaid && isSectionOpen('raid')) loadRaidData();
            if (hasLvm && isSectionOpen('lvm')) loadLvmData();
        }, 30000);
    }
    
    init();
	
</script>

<!-- PWA Registration -->
<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', async () => {
        try {
            const registration = await navigator.serviceWorker.register('/sw.js', {
                scope: '/'
            });
            console.log('Service Worker registered successfully:', registration);
            
            registration.onupdatefound = () => {
                const installingWorker = registration.installing;
                installingWorker.onstatechange = () => {
                    if (installingWorker.state === 'installed') {
                        if (navigator.serviceWorker.controller) {
                            console.log('New content available, refresh to update');
                        } else {
                            console.log('Content cached for offline use');
                        }
                    }
                };
            };
        } catch (error) {
            console.error('Service Worker registration failed:', error);
            showServiceWorkerError(error);
        }
    });
}

async function requestPushPermission(registration) {
    if (!('PushManager' in window)) {
        console.log('Push messaging not supported');
        return;
    }
    
    const permission = await Notification.requestPermission();
    if (permission !== 'granted') {
        console.log('Notification permission denied');
        return;
    }
    
    try {
        const subscription = await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array('YOUR_VAPID_PUBLIC_KEY')
        });
        
        await fetch('/api/push_subscribe.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(subscription)
        });
        
        console.log('Push subscription successful');
    } catch (error) {
        console.error('Push subscription failed:', error);
    }
}

function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - base64String.length % 4) % 4);
    const base64 = (base64String + padding)
        .replace(/\-/g, '+')
        .replace(/_/g, '/');
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; ++i) {
        outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
}

function showUpdateNotification() {
    const updateToast = document.createElement('div');
    updateToast.className = 'update-notification';
    updateToast.innerHTML = `
        <div style="position:fixed; bottom:20px; right:20px; background:#1c1c1e; color:white; padding:12px 20px; border-radius:12px; z-index:10000; display:flex; align-items:center; gap:12px; box-shadow:0 4px 12px rgba(0,0,0,0.15);">
            <i class="fas fa-download"></i>
            <span>Доступно обновление приложения</span>
            <button onclick="updateApp()" style="background:#007aff; border:none; color:white; padding:6px 12px; border-radius:8px; cursor:pointer;">Обновить</button>
            <button onclick="this.parentElement.remove()" style="background:transparent; border:none; color:#8e8e93; cursor:pointer;">✕</button>
        </div>
    `;
    document.body.appendChild(updateToast);
}

async function updateApp() {
    const registration = await navigator.serviceWorker.ready;
    registration.waiting?.postMessage({ type: 'SKIP_WAITING' });
    window.location.reload();
}

function saveOfflineState(key, data) {
    localStorage.setItem(`offline_${key}`, JSON.stringify(data));
}

function getOfflineState(key) {
    const data = localStorage.getItem(`offline_${key}`);
    return data ? JSON.parse(data) : null;
}

window.addEventListener('online', () => {
    console.log('Online');
    document.body.classList.remove('offline-mode');
    const indicator = document.querySelector('.offline-indicator');
    if (indicator) indicator.remove();
    refreshAll();
});

window.addEventListener('offline', () => {
    console.log('Offline');
    document.body.classList.add('offline-mode');
    showOfflineIndicator();
});

function showOfflineIndicator() {
    let indicator = document.querySelector('.offline-indicator');
    if (!indicator) {
        indicator = document.createElement('div');
        indicator.className = 'offline-indicator';
        indicator.innerHTML = '<i class="fas fa-wifi"></i> Офлайн режим - данные могут быть устаревшими';
        indicator.style.cssText = 'position:fixed; top:0; left:0; right:0; background:#ff9500; color:white; text-align:center; padding:8px; z-index:10000; font-size:12px;';
        document.body.appendChild(indicator);
    }
}
</script>

<style>
/* Стили для PWA */
.offline-mode .metric-card,
.offline-mode .chart-card,
.offline-mode .accordion-section {
    opacity: 0.85;
}

/* Стили для установки на iOS */
@media (display-mode: standalone) {
    body {
        padding-top: env(safe-area-inset-top);
        padding-bottom: env(safe-area-inset-bottom);
    }
    
    .top-bar {
        padding-top: calc(12px + env(safe-area-inset-top));
    }
}

/* Анимация установки */
@keyframes installPrompt {
    from { transform: translateY(100%); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

.install-prompt {
    position: fixed;
    bottom: 20px;
    left: 20px;
    right: 20px;
    background: white;
    border-radius: 20px;
    padding: 16px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.2);
    z-index: 10001;
    animation: installPrompt 0.3s ease;
    border: 1px solid #efeff4;
}
</style>
<script src="js/loader.js"></script>
</body>
</html>