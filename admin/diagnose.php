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

$current_host_id = $_SESSION['current_host_id'] ?? 1;

try {
    $db = getDB();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}

// Получаем API ключ по ID хоста
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

$menu = require_once 'menu.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Diagnostic Tools — Mini-B</title>
    <link href="lib/bootstrap-5.3.8-dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="lib/bootstrap-icons-1.11.0/bootstrap-icons.css">
    <link rel="stylesheet" href="lib/fontawesome-free-6.7.2-web/css/all.min.css">
    <!--<link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">-->
    <script src="lib/chart.js-4.5.1/package/dist/chart.umd.js"></script>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="css/loader.css">
    <link rel="shortcut icon" href="css/icon.ico" type="image/x-icon">
    <script src="js/hosts_load.js"></script>
	<script src="js/crt_checker.js"></script>
	<script>
	window.apiConfig = <?php echo json_encode($js_config); ?>;
	console.log('API Config loaded:', window.apiConfig);
	</script>

    <style>
        * {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e9eef3 100%);
            min-height: 100vh;
        }
        
        .app-container {
            display: flex;
            min-height: 100vh;
        }
        
        .main-content {
            flex: 1;
            padding: 25px 30px;
            margin-left: 280px;
            transition: all 0.3s ease;
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px 15px;
            }
        }

        .apple-card {
            background: rgba(255, 255, 255, 0.96);
            border-radius: 20px;
            box-shadow: 0 8px 28px rgba(0, 0, 0, 0.08), 0 1px 2px rgba(0, 0, 0, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.5);
            transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            margin-bottom: 24px;
            overflow: hidden;
        }
        
        .card-header-apple {
            padding: 16px 20px;
            background: linear-gradient(135deg, #f8f9fa, #ffffff);
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        
        .card-header-apple h3 {
            font-size: 16px;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
            color: #1c1c1e;
        }
        
        .card-header-apple h3 i {
            color: #007aff;
            font-size: 1.2rem;
        }
        
        .card-body-apple {
            padding: 20px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            border: 1px solid #e9ecef;
            transition: all 0.2s;
        }
        
        .stat-card:hover {
            border-color: #007aff40;
            transform: translateY(-2px);
        }
        
        .stat-card i {
            font-size: 32px;
            margin-bottom: 10px;
            color: #007aff;
        }
        
        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: #1c1c1e;
        }
        
        .stat-label {
            font-size: 13px;
            color: #8e8e93;
        }
        
        .tool-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 12px;
            margin-bottom: 24px;
        }
        
        .tool-btn {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 14px;
            padding: 16px 12px;
            text-align: center;
            transition: all 0.2s;
            cursor: pointer;
        }
        
        .tool-btn:hover {
            border-color: #007aff;
            background: #007aff08;
            transform: translateY(-2px);
        }
        
        .tool-btn i {
            font-size: 28px;
            color: #007aff;
            margin-bottom: 8px;
            display: block;
        }
        
        .tool-btn span {
            font-size: 13px;
            font-weight: 500;
            color: #1c1c1e;
        }
        
        .modal-apple .modal-content {
            border-radius: 24px;
            border: none;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        
        .modal-apple .modal-header {
            border-bottom: 1px solid #e9ecef;
            background: linear-gradient(135deg, #f8f9fa, #ffffff);
            border-radius: 24px 24px 0 0;
            padding: 18px 24px;
        }
        
        .modal-apple .modal-body {
            padding: 24px;
        }
        
        .modal-apple .modal-footer {
            border-top: 1px solid #e9ecef;
            padding: 16px 24px;
        }
        
        .console-output {
            background: #1e1e1e;
            color: #d4d4d4;
            font-family: 'SF Mono', 'Courier New', monospace;
            font-size: 12px;
            padding: 16px;
            border-radius: 12px;
            max-height: 400px;
            overflow-y: auto;
            white-space: pre-wrap;
            word-break: break-all;
        }
        
        .console-output::-webkit-scrollbar {
            width: 8px;
        }
        
        .console-output::-webkit-scrollbar-track {
            background: #2d2d2d;
            border-radius: 4px;
        }
        
        .console-output::-webkit-scrollbar-thumb {
            background: #007aff;
            border-radius: 4px;
        }
        
        .process-table {
            font-size: 13px;
        }
        
        .process-table th {
            background: #f8f9fa;
            position: sticky;
            top: 0;
        }
        
        .process-item {
            transition: all 0.2s;
        }
        
        .process-item:hover {
            background: #f0f0f0;
        }
        
        .cpu-bar {
            height: 6px;
            background: #e9ecef;
            border-radius: 3px;
            overflow: hidden;
            width: 100px;
        }
        
        .cpu-bar-fill {
            height: 100%;
            background: #007aff;
            border-radius: 3px;
            transition: width 0.3s;
        }
        
        .cpu-bar-fill.high {
            background: #ff3b30;
        }
        
        .cpu-bar-fill.medium {
            background: #ff9500;
        }
        
        .connection-item {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 12px;
            padding: 12px 16px;
            margin-bottom: 8px;
            transition: all 0.2s;
        }
        
        .connection-item:hover {
            background: white;
            border-color: #007aff40;
        }
        
        .network-graph-container {
            background: white;
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 20px;
        }
        
        canvas.network-chart {
            max-height: 200px;
            width: 100%;
        }
        
        .btn-apple {
            background: #007aff;
            border: none;
            border-radius: 10px;
            padding: 8px 16px;
            font-weight: 500;
            font-size: 13px;
            transition: all 0.2s ease;
            color: white;
            display: inline-flex;
            align-items: center;
            gap: 6px;
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
            border-radius: 10px;
            padding: 7px 15px;
            font-weight: 500;
            font-size: 13px;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
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
        
        .btn-sm-apple {
            padding: 4px 10px;
            font-size: 11px;
        }
        
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        }
        
        .toast-custom {
            background: white;
            border-radius: 12px;
            padding: 12px 20px;
            margin-bottom: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            border-left: 4px solid;
            animation: slideIn 0.3s ease;
        }
        
        .toast-success {
            border-left-color: #34c759;
        }
        
        .toast-error {
            border-left-color: #ff3b30;
        }
        
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9998;
        }
        
        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #007aff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .form-control-apple, .form-select-apple {
            border-radius: 12px;
            border: 1px solid #e9ecef;
            padding: 10px 14px;
            transition: all 0.2s;
        }
        
        .form-control-apple:focus, .form-select-apple:focus {
            border-color: #007aff;
            box-shadow: 0 0 0 3px rgba(0,122,255,0.1);
            outline: none;
        }
        
        .nav-tabs-apple {
            border-bottom: 1px solid #e9ecef;
            margin-bottom: 24px;
            gap: 4px;
        }
        
        .nav-tabs-apple .nav-link {
            border: none;
            color: #8e8e93;
            font-weight: 500;
            padding: 10px 20px;
            border-radius: 12px;
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
        
        .badge-process {
            font-size: 10px;
            padding: 3px 8px;
            border-radius: 10px;
        }
        
        .kill-process-btn, .kill-connection-btn {
            opacity: 0.6;
            transition: opacity 0.2s;
        }
        
        tr:hover .kill-process-btn,
        .connection-item:hover .kill-connection-btn {
            opacity: 1;
        }
    </style>
</head>
<body>

<div id="loadingOverlay" class="loading-overlay" style="display: none;">
    <div class="loading-spinner"></div>
</div>

<!--<div class="toast-container" id="toastContainer"></div>-->
        <div id="alertContainer"></div>

<div class="top-bar">
    <div class="top-bar-left">
        <h1><i class="fas fa-bucket"></i> Mini-B</h1>
    </div>
    <div class="top-bar-right">
        <span><i class="bi bi-stethoscope me-2"></i> Diagnostic Tools</span>
		<div class="host-selector" style="margin-left: 20px;">
            <select id="hostSelector" style="background: rgba(255,255,255,0.9); border: 1px solid #ddd; border-radius: 20px; padding: 6px 30px 6px 15px; font-size: 14px; cursor: pointer;">
                <option value="">Loading...</option>
            </select>
        </div>
    </div>
</div>

<div class="app-container">
    <?php echo $menu; ?>
    
    <main class="main-content">
        
        <!-- System Stats Overview -->
        <div class="stats-grid">
            <div class="stat-card">
                <i class="bi bi-cpu"></i>
                <div class="stat-value" id="cpuUsage">0%</div>
                <div class="stat-label">CPU Usage</div>
            </div>
            <div class="stat-card">
                <i class="bi bi-memory"></i>
                <div class="stat-value" id="ramUsage">0%</div>
                <div class="stat-label">RAM Usage</div>
            </div>
            <div class="stat-card">
                <i class="bi bi-hdd-stack"></i>
                <div class="stat-value" id="diskUsage">0%</div>
                <div class="stat-label">Disk Usage</div>
            </div>
            <div class="stat-card">
                <i class="bi bi-arrow-up-short"></i>
                <div class="stat-value" id="uptime">0</div>
                <div class="stat-label">Uptime (days)</div>
            </div>
            <div class="stat-card">
                <i class="bi bi-activity"></i>
                <div class="stat-value" id="loadAvg">0</div>
                <div class="stat-label">Load Average</div>
            </div>
            <div class="stat-card">
                <i class="bi bi-hdd-network"></i>
                <div class="stat-value" id="connectionsCount">0</div>
                <div class="stat-label">Active Connections</div>
            </div>
        </div>
        
        <!-- Network Traffic Graph -->
        <div class="apple-card">
            <div class="card-header-apple">
                <h3><i class="bi bi-graph-up"></i> Network Traffic (Real-time)</h3>
                <div class="d-flex gap-2">
                    <button class="btn-apple-outline btn-sm-apple" onclick="resetNetworkGraph()">
                        <i class="bi bi-arrow-repeat"></i> Reset
                    </button>
                </div>
            </div>
            <div class="card-body-apple">
                <div class="network-graph-container">
                    <canvas id="networkChart" class="network-chart" style="height: 200px;"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Quick Tools Grid -->
        <div class="tool-grid">
            <div class="tool-btn" onclick="openPingModal()">
                <i class="bi bi-wifi"></i>
                <span>Ping</span>
            </div>
            <div class="tool-btn" onclick="openTracerouteModal()">
                <i class="bi bi-diagram-3"></i>
                <span>Traceroute</span>
            </div>
            <div class="tool-btn" onclick="openNetstatModal()">
                <i class="bi bi-diagram-2"></i>
                <span>Netstat</span>
            </div>
            <div class="tool-btn" onclick="openNmapModal()">
                <i class="bi bi-binoculars"></i>
                <span>Port Scanner</span>
            </div>
            <div class="tool-btn" onclick="openDnsModal()">
                <i class="bi bi-question-diamond"></i>
                <span>DNS Lookup</span>
            </div>
            <div class="tool-btn" onclick="openBandwidthModal()">
                <i class="bi bi-speedometer2"></i>
                <span>Bandwidth Test</span>
            </div>
        </div>
        
        <!-- Tabs for Processes, Connections, Services -->
        <ul class="nav nav-tabs-apple" id="diagTabs" role="tablist">
            <li class="nav-item">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#processesTab" type="button" role="tab">
                    <i class="bi bi-cpu me-2"></i>Processes
                    <span id="processCount" class="badge bg-secondary ms-1" style="background: #8e8e93 !important;">0</span>
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#connectionsTab" type="button" role="tab">
                    <i class="bi bi-hdd-network me-2"></i>Network Connections
                    <span id="connCount" class="badge bg-secondary ms-1" style="background: #8e8e93 !important;">0</span>
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#servicesTab" type="button" role="tab">
                    <i class="bi bi-gear me-2"></i>Services
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#logsTab" type="button" role="tab">
                    <i class="bi bi-file-text me-2"></i>System Logs
                </button>
            </li>
        </ul>
        
        <div class="tab-content">
            <!-- Processes Tab -->
            <div class="tab-pane fade show active" id="processesTab" role="tabpanel">
                <div class="apple-card">
                    <div class="card-header-apple">
                        <h3><i class="bi bi-cpu"></i> Running Processes</h3>
                        <div class="d-flex gap-2">
                            <select id="processSort" class="form-select form-select-sm" style="width: auto; border-radius: 10px;">
                                <option value="cpu">Sort by CPU</option>
                                <option value="mem">Sort by Memory</option>
                                <option value="pid">Sort by PID</option>
                            </select>
                            <button class="btn-apple-outline btn-sm-apple" onclick="refreshProcesses()">
                                <i class="bi bi-arrow-repeat"></i> Refresh
                            </button>
                            <button class="btn-apple-outline btn-sm-apple" onclick="killProcessModal()">
                                <i class="bi bi-x-circle"></i> Kill Process
                            </button>
                        </div>
                    </div>
                    <div class="card-body-apple">
                        <div class="table-responsive">
                            <table class="table table-sm process-table" id="processTable">
                                <thead>
                                    <tr>
                                        <th>PID</th>
                                        <th>User</th>
                                        <th>CPU%</th>
                                        <th>MEM%</th>
                                        <th>Command</th>
                                        <th style="width: 60px">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="processList">
                                    <tr><td colspan="6" class="text-center text-muted">Loading processes...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Network Connections Tab -->
            <div class="tab-pane fade" id="connectionsTab" role="tabpanel">
                <div class="apple-card">
                    <div class="card-header-apple">
                        <h3><i class="bi bi-hdd-network"></i> Active Network Connections</h3>
                        <div class="d-flex gap-2">
                            <input type="text" id="connFilter" class="form-control form-control-sm" placeholder="Filter by IP/Port" style="width: 200px; border-radius: 10px;">
                            <button class="btn-apple-outline btn-sm-apple" onclick="refreshConnections()">
                                <i class="bi bi-arrow-repeat"></i> Refresh
                            </button>
                        </div>
                    </div>
                    <div class="card-body-apple" id="connectionsList">
                        <div class="text-center text-muted py-4">Loading connections...</div>
                    </div>
                </div>
            </div>
            
            <!-- Services Tab -->
            <div class="tab-pane fade" id="servicesTab" role="tabpanel">
                <div class="apple-card">
                    <div class="card-header-apple">
                        <h3><i class="bi bi-gear"></i> System Services</h3>
                        <div class="d-flex gap-2">
                            <select id="serviceFilter" class="form-select form-select-sm" style="width: auto; border-radius: 10px;">
                                <option value="all">All Services</option>
                                <option value="running">Running</option>
                                <option value="stopped">Stopped</option>
                            </select>
                            <button class="btn-apple-outline btn-sm-apple" onclick="refreshServices()">
                                <i class="bi bi-arrow-repeat"></i> Refresh
                            </button>
                        </div>
                    </div>
                    <div class="card-body-apple">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Service</th>
                                        <th>Status</th>
                                        <th>Load State</th>
                                        <th>Description</th>
                                        <th style="width: 100px">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="servicesList">
                                    <tr><td colspan="5" class="text-center text-muted">Loading services...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- System Logs Tab -->
            <div class="tab-pane fade" id="logsTab" role="tabpanel">
                <div class="apple-card">
                    <div class="card-header-apple">
                        <h3><i class="bi bi-file-text"></i> System Logs</h3>
                        <div class="d-flex gap-2">
                            <select id="logLines" class="form-select form-select-sm" style="width: auto; border-radius: 10px;">
                                <option value="50">50 lines</option>
                                <option value="100" selected>100 lines</option>
                                <option value="200">200 lines</option>
                                <option value="500">500 lines</option>
                            </select>
                            <select id="logType" class="form-select form-select-sm" style="width: auto; border-radius: 10px;">
                                <option value="syslog">System Log</option>
                                <option value="auth">Auth Log</option>
                                <option value="kernel">Kernel Log</option>
                            </select>
                            <button class="btn-apple-outline btn-sm-apple" onclick="refreshLogs()">
                                <i class="bi bi-arrow-repeat"></i> Refresh
                            </button>
                            <button class="btn-apple-outline btn-sm-apple" onclick="exportLogs()">
                                <i class="bi bi-download"></i> Export
                            </button>
                        </div>
                    </div>
                    <div class="card-body-apple">
                        <div class="mb-3">
                            <input type="text" id="logFilter" class="form-control" placeholder="Filter logs..." style="border-radius: 10px;">
                        </div>
                        <div id="logsList" class="console-output" style="max-height: 400px;">
                            <div class="text-center text-muted">Loading logs...</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Ping Modal -->
<div class="modal fade modal-apple" id="pingModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-semibold"><i class="bi bi-wifi me-2"></i>Ping Tool</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="input-group mb-3">
                    <input type="text" id="pingTarget" class="form-control form-control-apple" placeholder="IP address or hostname (e.g., google.com, 8.8.8.8)">
                    <select id="pingCount" class="form-select" style="width: auto;">
                        <option value="4">4 pings</option>
                        <option value="10">10 pings</option>
                        <option value="20">20 pings</option>
                        <option value="0">Continuous</option>
                    </select>
                    <button class="btn-apple" onclick="startPing()">Start Ping</button>
                    <button class="btn-apple-outline" onclick="stopPing()">Stop</button>
                    <button class="btn-apple-outline" onclick="clearPingOutput()">Clear</button>
                </div>
                <div id="pingOutput" class="console-output" style="height: 400px; font-family: monospace;"></div>
            </div>
        </div>
    </div>
</div>

<!-- Traceroute Modal -->
<div class="modal fade modal-apple" id="traceModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-semibold"><i class="bi bi-diagram-3 me-2"></i>Traceroute</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="input-group mb-3">
                    <input type="text" id="traceTarget" class="form-control form-control-apple" placeholder="IP address or hostname">
                    <button class="btn-apple" onclick="startTraceroute()">Start Traceroute</button>
                    <button class="btn-apple-outline" onclick="stopTraceroute()">Stop</button>
                    <button class="btn-apple-outline" onclick="clearTraceOutput()">Clear</button>
                </div>
                <div id="traceOutput" class="console-output" style="height: 400px;"></div>
            </div>
        </div>
    </div>
</div>

<!-- Netstat Modal -->
<div class="modal fade modal-apple" id="netstatModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-semibold"><i class="bi bi-diagram-2 me-2"></i>Network Statistics (netstat)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="btn-group mb-3" role="group">
                    <button class="btn btn-sm btn-outline-secondary" onclick="refreshNetstat('all')">All</button>
                    <button class="btn btn-sm btn-outline-secondary" onclick="refreshNetstat('tcp')">TCP</button>
                    <button class="btn btn-sm btn-outline-secondary" onclick="refreshNetstat('udp')">UDP</button>
                    <button class="btn btn-sm btn-outline-secondary" onclick="refreshNetstat('listening')">Listening</button>
                </div>
                <div id="netstatOutput" class="console-output" style="height: 400px; font-size: 11px;"></div>
            </div>
        </div>
    </div>
</div>

<!-- Port Scanner Modal -->
<div class="modal fade modal-apple" id="nmapModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-semibold"><i class="bi bi-binoculars me-2"></i>Port Scanner</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="input-group mb-3">
                    <input type="text" id="scanTarget" class="form-control form-control-apple" placeholder="IP address or hostname">
                    <input type="text" id="scanPorts" class="form-control form-control-apple" placeholder="Ports (e.g., 22,80,443 or 1-1000)" value="22,80,443,3306,5432,8080">
                    <button class="btn-apple" onclick="startPortScan()">Scan</button>
                    <button class="btn-apple-outline" onclick="clearScanOutput()">Clear</button>
                </div>
                <div id="scanOutput" class="console-output" style="height: 400px;"></div>
            </div>
        </div>
    </div>
</div>

<!-- DNS Lookup Modal -->
<div class="modal fade modal-apple" id="dnsModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-semibold"><i class="bi bi-question-diamond me-2"></i>DNS Lookup</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="input-group mb-3">
                    <input type="text" id="dnsTarget" class="form-control form-control-apple" placeholder="Domain name (e.g., google.com)">
                    <select id="dnsType" class="form-select" style="width: auto;">
                        <option value="A">A (IPv4)</option>
                        <option value="AAAA">AAAA (IPv6)</option>
                        <option value="MX">MX (Mail)</option>
                        <option value="NS">NS (Nameserver)</option>
                        <option value="TXT">TXT</option>
                        <option value="CNAME">CNAME</option>
                    </select>
                    <button class="btn-apple" onclick="startDnsLookup()">Lookup</button>
                </div>
                <div id="dnsOutput" class="console-output" style="height: 300px;"></div>
            </div>
        </div>
    </div>
</div>

<!-- Bandwidth Test Modal -->
<div class="modal fade modal-apple" id="bandwidthModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-semibold"><i class="bi bi-speedometer2 me-2"></i>Bandwidth Test</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3">
                    <button class="btn-apple" onclick="startBandwidthTest()">Start Speed Test</button>
                    <div id="bandwidthResult" class="mt-3"></div>
                </div>
                <canvas id="speedChart" style="height: 200px;"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Kill Process Modal -->
<div class="modal fade modal-apple" id="killProcessModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-semibold"><i class="bi bi-x-circle me-2"></i>Kill Process</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Process ID (PID)</label>
                    <input type="number" id="killPid" class="form-control form-control-apple" placeholder="Enter PID">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Signal</label>
                    <select id="killSignal" class="form-select form-select-apple">
                        <option value="15">SIGTERM (15) - Graceful</option>
                        <option value="9">SIGKILL (9) - Force</option>
                        <option value="1">SIGHUP (1) - Reload</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn-apple-danger" onclick="killProcess()">Kill Process</button>
            </div>
        </div>
    </div>
</div>

<script src="lib/jquery-3.6.0-master/dist/jquery.min.js"></script>
<script src="lib/bootstrap-5.3.8-dist/js/bootstrap.bundle.min.js"></script>

<script>
const url = "<?php echo $current_host_id == 1 ? '/api/' : rtrim($host_url, '/') . '/'; ?>";
let pingInterval = null;
let traceInterval = null;
let networkChart = null;
let speedData = [];

function showToast(message, type = 'success') {
    const toast = $(`
        <div class="toast-custom toast-${type}">
            <i class="bi ${type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill'} me-2"></i>
            <span>${message}</span>
        </div>
    `);
    $('#alertContainer').append(toast);
    setTimeout(() => toast.fadeOut(300, () => toast.remove()), 3000);
}

function showLoading() {
    $('#loadingOverlay').show();
}

function hideLoading() {
    $('#loadingOverlay').hide();
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/[&<>]/g, function(m) {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>') return '&gt;';
        return m;
    });
}

// ==================== System Stats ====================
function loadSystemStats() {
    const headers = {};
    if (window.apiConfig && window.apiConfig.apiKey) {
        headers['X-API-Key'] = window.apiConfig.apiKey;
    }
    
    $.ajax({
        url: url + 'diagnostic_api.php?action=system_stats',
        method: 'GET',
        headers: headers,
        success: function(response) {
            if (response.success) {
                const stats = response.data;
                $('#cpuUsage').text(stats.cpu + '%');
                $('#ramUsage').text(stats.ram + '%');
                $('#diskUsage').text(stats.disk + '%');
                $('#uptime').text(stats.uptime_days);
                $('#loadAvg').text(stats.load_avg);
                
                const cpu = parseInt(stats.cpu);
                if (cpu > 80) {
                    $('#cpuUsage').css('color', '#ff3b30');
                } else if (cpu > 50) {
                    $('#cpuUsage').css('color', '#ff9500');
                } else {
                    $('#cpuUsage').css('color', '#1c1c1e');
                }
            }
        },
        error: function(xhr, status, error) {
            console.error('System stats error:', error);
            $('#cpuUsage').text('Error');
            $('#ramUsage').text('Error');
            $('#diskUsage').text('Error');
            $('#uptime').text('Error');
            $('#loadAvg').text('Error');
        }
    });
}

// ==================== Network Traffic Graph ====================
function initNetworkChart() {
    const ctx = document.getElementById('networkChart').getContext('2d');
    networkChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: [],
            datasets: [
                {
                    label: 'Download (KB/s)',
                    data: [],
                    borderColor: '#007aff',
                    backgroundColor: 'rgba(0, 122, 255, 0.1)',
                    fill: true,
                    tension: 0.4
                },
                {
                    label: 'Upload (KB/s)',
                    data: [],
                    borderColor: '#34c759',
                    backgroundColor: 'rgba(52, 199, 89, 0.1)',
                    fill: true,
                    tension: 0.4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { position: 'top' },
                tooltip: { mode: 'index', intersect: false }
            },
            scales: {
                y: { beginAtZero: true, title: { display: true, text: 'KB/s' } },
                x: { title: { display: true, text: 'Time' } }
            }
        }
    });
}

let networkDataPoints = [];
function updateNetworkStats() {
    const headers = {};
    if (window.apiConfig && window.apiConfig.apiKey) {
        headers['X-API-Key'] = window.apiConfig.apiKey;
    }
    
    $.ajax({
        url: url + 'diagnostic_api.php?action=network_stats',
        method: 'GET',
        headers: headers,
        success: function(response) {
            if (response.success && networkChart) {
                const stats = response.data;
                const now = new Date().toLocaleTimeString();
                
                networkDataPoints.push({ time: now, download: stats.download_kbps, upload: stats.upload_kbps });
                if (networkDataPoints.length > 30) networkDataPoints.shift();
                
                networkChart.data.labels = networkDataPoints.map(p => p.time);
                networkChart.data.datasets[0].data = networkDataPoints.map(p => p.download);
                networkChart.data.datasets[1].data = networkDataPoints.map(p => p.upload);
                networkChart.update();
                
                $('#connectionsCount').text(stats.connections);
            }
        },
        error: function(xhr, status, error) {
            console.error('Network stats error:', error);
        }
    });
}

function resetNetworkGraph() {
    networkDataPoints = [];
    networkChart.data.labels = [];
    networkChart.data.datasets[0].data = [];
    networkChart.data.datasets[1].data = [];
    networkChart.update();
    showToast('Graph reset', 'success');
}

// ==================== Processes ====================
let currentProcesses = [];

function refreshProcesses() {
    const sortBy = $('#processSort').val();
    
    const headers = {};
    if (window.apiConfig && window.apiConfig.apiKey) {
        headers['X-API-Key'] = window.apiConfig.apiKey;
    }
    
    $.ajax({
        url: url + 'diagnostic_api.php?action=processes',
        method: 'GET',
        data: { sort: sortBy },
        headers: headers,
        success: function(response) {
            if (response.success) {
                currentProcesses = response.data;
                $('#processCount').text(currentProcesses.length);
                renderProcesses(currentProcesses);
            }
        },
        error: function(xhr, status, error) {
            console.error('Processes refresh error:', error);
            $('#processCount').text('Error');
        }
    });
}

function renderProcesses(processes) {
    if (processes.length === 0) {
        $('#processList').html('<tr><td colspan="6" class="text-center text-muted">No processes found</td></tr>');
        return;
    }
    
    let html = '';
    processes.forEach(proc => {
        const cpuClass = proc.cpu > 50 ? 'high' : (proc.cpu > 20 ? 'medium' : '');
        html += `
            <tr class="process-item">
                <td><code>${proc.pid}</code></td>
                <td>${escapeHtml(proc.user)}</td>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <span>${proc.cpu}%</span>
                        <div class="cpu-bar"><div class="cpu-bar-fill ${cpuClass}" style="width: ${Math.min(proc.cpu, 100)}%"></div></div>
                    </div>
                </td>
                <td>${proc.mem}%</td>
                <td><small>${escapeHtml(proc.command.substring(0, 60))}</small></td>
                <td>
                    <button class="btn btn-sm btn-outline-danger kill-process-btn" onclick="killProcessById(${proc.pid})" title="Kill process">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </td>
            </tr>
        `;
    });
    $('#processList').html(html);
}

function killProcessById(pid) {
    if (confirm(`Terminate process PID ${pid}?`)) {
        showLoading();
        
        const headers = {};
        if (window.apiConfig && window.apiConfig.apiKey) {
            headers['X-API-Key'] = window.apiConfig.apiKey;
        }
        
        $.ajax({
            url: url + 'diagnostic_api.php?action=kill_process',
            method: 'POST',
            data: { pid: pid, signal: 15 },
            headers: headers,
            success: function(response) {
                hideLoading();
                if (response.success) {
                    showToast(`Process ${pid} terminated`, 'success');
                    refreshProcesses();
                } else {
                    showToast(response.error, 'error');
                }
            },
            error: function(xhr, status, error) {
                hideLoading();
                console.error('Kill process error:', error);
                showToast('Error killing process: ' + error, 'error');
            }
        });
    }
}

function killProcessModal() {
    $('#killPid').val('');
    $('#killProcessModal').modal('show');
}

function killProcess() {
    const pid = $('#killPid').val();
    const signal = $('#killSignal').val();
    
    if (!pid) {
        showToast('Enter PID', 'error');
        return;
    }
    
    showLoading();
    
    const headers = {};
    if (window.apiConfig && window.apiConfig.apiKey) {
        headers['X-API-Key'] = window.apiConfig.apiKey;
    }
    
    $.ajax({
        url: url + 'diagnostic_api.php?action=kill_process',
        method: 'POST',
        data: { pid: pid, signal: signal },
        headers: headers,
        success: function(response) {
            hideLoading();
            if (response.success) {
                showToast(`Process ${pid} terminated`, 'success');
                $('#killProcessModal').modal('hide');
                refreshProcesses();
            } else {
                showToast(response.error, 'error');
            }
        },
        error: function(xhr, status, error) {
            hideLoading();
            console.error('Kill process error:', error);
            showToast('Error killing process: ' + error, 'error');
        }
    });
}

// ==================== Network Connections ====================
function refreshConnections() {
    const filter = $('#connFilter').val().toLowerCase();
    
    const headers = {};
    if (window.apiConfig && window.apiConfig.apiKey) {
        headers['X-API-Key'] = window.apiConfig.apiKey;
    }
    
    $.ajax({
        url: url + 'diagnostic_api.php?action=connections',
        method: 'GET',
        headers: headers,
        success: function(response) {
            if (response.success) {
                let connections = response.data;
                $('#connCount').text(connections.length);
                
                if (filter) {
                    connections = connections.filter(c => 
                        (c.local_addr || '').toLowerCase().includes(filter) || 
                        (c.remote_addr || '').toLowerCase().includes(filter) ||
                        (c.pid || '').toLowerCase().includes(filter)
                    );
                }
                
                renderConnections(connections);
            }
        },
        error: function(xhr, status, error) {
            console.error('Connections refresh error:', error);
            $('#connCount').text('Error');
            renderConnections([]);
        }
    });
}

function renderConnections(connections) {
    if (connections.length === 0) {
        $('#connectionsList').html('<div class="text-center text-muted py-4">No connections found</div>');
        return;
    }
    
    let html = '';
    connections.forEach(conn => {
        const stateClass = conn.state === 'ESTABLISHED' ? 'bg-success' : (conn.state === 'LISTEN' ? 'bg-info' : 'bg-secondary');
        html += `
            <div class="connection-item">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="flex-grow-1">
                        <span class="badge bg-secondary me-2">${conn.protocol}</span>
                        <span class="badge ${stateClass} me-2">${conn.state}</span>
                        <code class="text-primary">${escapeHtml(conn.local_addr)}</code>
                        <i class="bi bi-arrow-right mx-2 text-muted"></i>
                        <code class="text-success">${escapeHtml(conn.remote_addr)}</code>
                        ${conn.pid !== '-' ? `<span class="badge bg-info ms-2"><i class="bi bi-cpu"></i> PID: ${conn.pid}</span>` : ''}
                        ${conn.process ? `<span class="badge bg-secondary ms-2">${escapeHtml(conn.process)}</span>` : ''}
                    </div>
                    ${conn.state === 'ESTABLISHED' ? `
                        <button class="btn btn-danger btn-sm kill-connection-btn" onclick="killConnectionById('${conn.remote_addr.split(':')[0]}', '${conn.remote_addr.split(':')[1]}', '${conn.protocol}')">
                            <i class="bi bi-x-lg"></i> Kill
                        </button>
                    ` : ''}
                </div>
            </div>
        `;
    });
    $('#connectionsList').html(html);
}

function killConnectionById(ip, port, protocol) {
    if (confirm(`Terminate connection ${ip}:${port}?`)) {
        showLoading();
        
        const headers = {};
        if (window.apiConfig && window.apiConfig.apiKey) {
            headers['X-API-Key'] = window.apiConfig.apiKey;
        }
        
        $.ajax({
            url: url + 'diagnostic_api.php?action=kill_connection',
            method: 'POST',
            data: { ip: ip, port: port, protocol: protocol },
            headers: headers,
            success: function(response) {
                hideLoading();
                if (response.success) {
                    showToast(`Connection ${ip}:${port} terminated`, 'success');
                    refreshConnections();
                } else {
                    showToast(response.error, 'error');
                }
            },
            error: function(xhr, status, error) {
                hideLoading();
                console.error('Kill connection error:', error);
                showToast('Error terminating connection: ' + error, 'error');
            }
        });
    }
}

// ==================== Services ====================
function refreshServices() {
    const filter = $('#serviceFilter').val();
    
    const headers = {};
    if (window.apiConfig && window.apiConfig.apiKey) {
        headers['X-API-Key'] = window.apiConfig.apiKey;
    }
    
    $.ajax({
        url: url + 'diagnostic_api.php?action=services',
        method: 'GET',
        headers: headers,
        success: function(response) {
            if (response.success) {
                let services = response.data;
                if (filter !== 'all') {
                    services = services.filter(s => s.active === filter);
                }
                renderServices(services);
            }
        },
        error: function(xhr, status, error) {
            console.error('Services refresh error:', error);
            showToast('Failed to load services', 'error');
            renderServices([]);
        }
    });
}

function renderServices(services) {
    if (services.length === 0) {
        $('#servicesList').html('<tr><td colspan="5" class="text-center text-muted">No services found</td></tr>');
        return;
    }
    
    let html = '';
    services.forEach(service => {
        const statusClass = service.active === 'running' ? 'bg-success' : 'bg-secondary';
        html += `
            <tr>
                <td><code>${escapeHtml(service.name)}</code></td>
                <td><span class="badge ${statusClass}">${service.active}</span></td>
                <td>${service.sub}</td>
                <td><small>${escapeHtml(service.description.substring(0, 60))}</small></td>
                <td>
                    <button class="btn btn-sm ${service.active === 'running' ? 'btn-outline-danger' : 'btn-outline-success'}" onclick="serviceAction('${service.name}', '${service.active === 'running' ? 'stop' : 'start'}')">
                        ${service.active === 'running' ? '<i class="bi bi-stop-circle"></i> Stop' : '<i class="bi bi-play-circle"></i> Start'}
                    </button>
                </td>
            </tr>
        `;
    });
    $('#servicesList').html(html);
}

function serviceAction(service, action) {
    showLoading();
    
    const headers = {
        'Content-Type': 'application/x-www-form-urlencoded'
    };
    if (window.apiConfig && window.apiConfig.apiKey) {
        headers['X-API-Key'] = window.apiConfig.apiKey;
    }
    
    $.ajax({
        url: url + 'diagnostic_api.php?action=service_action',
        method: 'POST',
        data: { service: service, action: action },
        headers: headers,
        success: function(response) {
            hideLoading();
            if (response.success) {
                const actionNames = {
                    start: 'started',
                    stop: 'stopped',
                    restart: 'restarted',
                    enable: 'enabled',
                    disable: 'disabled'
                };
                const message = `${service} ${actionNames[action] || action + 'ed'}`;
                showToast(message, 'success');
                refreshServices();
            } else {
                showToast(response.error || 'Action failed', 'error');
            }
        },
        error: function(xhr, status, error) {
            hideLoading();
            console.error('Service action error:', error);
            showToast(`Error ${action}ing ${service}: ${error}`, 'error');
        }
    });
}

// ==================== System Logs ====================
function refreshLogs() {
    const lines = $('#logLines').val();
    const type = $('#logType').val();
    const filter = $('#logFilter').val();
    
    const headers = {};
    if (window.apiConfig && window.apiConfig.apiKey) {
        headers['X-API-Key'] = window.apiConfig.apiKey;
    }
    
    $.ajax({
        url: url + `diagnostic_api.php?action=logs&lines=${lines}&type=${type}&filter=${encodeURIComponent(filter)}`,
        method: 'GET',
        headers: headers,
        success: function(response) {
            if (response.success) {
                renderLogs(response.data);
            } else {
                $('#logsList').html('<div class="text-center text-danger">Error loading logs</div>');
            }
        },
        error: function(xhr, status, error) {
            console.error('Logs refresh error:', error);
            $('#logsList').html('<div class="text-center text-danger">Failed to load logs: ' + error + '</div>');
        }
    });
}

function renderLogs(logs) {
    if (logs.length === 0) {
        $('#logsList').html('<div class="text-center text-muted">No logs found</div>');
        return;
    }
    
    let html = '';
    logs.forEach(log => {
        let color = '#d4d4d4';
        if (log.includes('ERROR') || log.includes('error') || log.includes('fail')) color = '#ff6b6b';
        else if (log.includes('WARN') || log.includes('warning')) color = '#ffcc00';
        else if (log.includes('INFO')) color = '#6bff6b';
        
        html += `<div style="color: ${color}; border-bottom: 1px solid #333; padding: 4px 0;">${escapeHtml(log)}</div>`;
    });
    $('#logsList').html(html);
}

function exportLogs() {
    const logs = $('#logsList').text();
    const blob = new Blob([logs], { type: 'text/plain' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = `system_logs_${new Date().toISOString().slice(0,19)}.txt`;
    link.click();
    URL.revokeObjectURL(link.href);
    showToast('Logs exported', 'success');
}

// ==================== Ping Tool ====================
let pingActive = false;

function openPingModal() {
    $('#pingTarget').val('');
    $('#pingOutput').html('');
    $('#pingModal').modal('show');
}

function startPing() {
    const target = $('#pingTarget').val();
    const count = $('#pingCount').val();
    
    if (!target) {
        showToast('Enter target address', 'error');
        return;
    }
    
    if (pingInterval) clearInterval(pingInterval);
    
    $('#pingOutput').html('');
    appendPingOutput(`Pinging ${target}...\n`);
    
    const headers = {
        'Content-Type': 'application/x-www-form-urlencoded'
    };
    if (window.apiConfig && window.apiConfig.apiKey) {
        headers['X-API-Key'] = window.apiConfig.apiKey;
    }
    
    if (count === '0') {
        pingActive = true;
        function doPing() {
            if (!pingActive) return;
            $.ajax({
                url: url + 'diagnostic_api.php?action=ping',
                method: 'POST',
                data: { target: target, count: 1 },
                headers: headers,
                success: function(response) {
                    if (response.success) {
                        appendPingOutput(response.data);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Ping error:', error);
                    appendPingOutput(`Error: ${error}`);
                }
            });
        }
        doPing();
        pingInterval = setInterval(doPing, 2000);
    } else {
        $.ajax({
            url: url + 'diagnostic_api.php?action=ping',
            method: 'POST',
            data: { target: target, count: count },
            headers: headers,
            success: function(response) {
                if (response.success) {
                    appendPingOutput(response.data);
                } else {
                    appendPingOutput('Error: ' + response.error);
                }
            },
            error: function(xhr, status, error) {
                appendPingOutput(`Error: ${error}`);
            }
        });
    }
}

function appendPingOutput(text) {
    const output = $('#pingOutput');
    output.append(text + '\n');
    output.scrollTop(output[0].scrollHeight);
}

function stopPing() {
    pingActive = false;
    if (pingInterval) {
        clearInterval(pingInterval);
        pingInterval = null;
    }
    appendPingOutput('\n[Ping stopped]');
}

function clearPingOutput() {
    $('#pingOutput').html('');
}

// ==================== Traceroute ====================
function openTracerouteModal() {
    $('#traceTarget').val('');
    $('#traceOutput').html('');
    $('#traceModal').modal('show');
}

function startTraceroute() {
    const target = $('#traceTarget').val();
    if (!target) {
        showToast('Enter target address', 'error');
        return;
    }
    
    $('#traceOutput').html('');
    appendTraceOutput(`Tracing route to ${target}...\n\n`);
    
    const headers = {
        'Content-Type': 'application/x-www-form-urlencoded'
    };
    if (window.apiConfig && window.apiConfig.apiKey) {
        headers['X-API-Key'] = window.apiConfig.apiKey;
    }
    
    $.ajax({
        url: url + 'diagnostic_api.php?action=traceroute',
        method: 'POST',
        data: { target: target },
        headers: headers,
        success: function(response) {
            if (response.success) {
                appendTraceOutput(response.data);
            } else {
                appendTraceOutput('Error: ' + response.error);
            }
        },
        error: function(xhr, status, error) {
            console.error('Traceroute error:', error);
            appendTraceOutput(`Error: ${error}`);
        }
    });
}

function appendTraceOutput(text) {
    const output = $('#traceOutput');
    output.append(text + '\n');
    output.scrollTop(output[0].scrollHeight);
}

function stopTraceroute() {
    appendTraceOutput('\n[Traceroute stopped]');
}

function clearTraceOutput() {
    $('#traceOutput').html('');
}

// ==================== Netstat ====================
function openNetstatModal() {
    $('#netstatOutput').html('Loading...');
    $('#netstatModal').modal('show');
    refreshNetstat('all');
}

function refreshNetstat(type) {
    const headers = {};
    if (window.apiConfig && window.apiConfig.apiKey) {
        headers['X-API-Key'] = window.apiConfig.apiKey;
    }
    
    $.ajax({
        url: url + `diagnostic_api.php?action=netstat&type=${type}`,
        method: 'GET',
        headers: headers,
        success: function(response) {
            if (response.success) {
                $('#netstatOutput').html(response.data);
            } else {
                $('#netstatOutput').html('<div class="alert alert-danger">Error: ' + response.error + '</div>');
            }
        },
        error: function(xhr, status, error) {
            console.error('Netstat error:', error);
            $('#netstatOutput').html('<div class="alert alert-danger">Failed to load netstat: ' + error + '</div>');
        }
    });
}

// ==================== Port Scanner ====================
function openNmapModal() {
    $('#scanTarget').val('');
    $('#scanPorts').val('22,80,443,3306,5432,8080');
    $('#scanOutput').html('');
    $('#nmapModal').modal('show');
}

function startPortScan() {
    const target = $('#scanTarget').val();
    const ports = $('#scanPorts').val();
    
    if (!target) {
        showToast('Enter target address', 'error');
        return;
    }
    
    $('#scanOutput').html('Scanning...\n');
    
    const headers = {
        'Content-Type': 'application/x-www-form-urlencoded'
    };
    if (window.apiConfig && window.apiConfig.apiKey) {
        headers['X-API-Key'] = window.apiConfig.apiKey;
    }
    
    $.ajax({
        url: url + 'diagnostic_api.php?action=port_scan',
        method: 'POST',
        data: { target: target, ports: ports },
        headers: headers,
        success: function(response) {
            if (response.success) {
                $('#scanOutput').html(response.data);
            } else {
                $('#scanOutput').html('Error: ' + response.error);
            }
        },
        error: function(xhr, status, error) {
            $('#scanOutput').html('Error: ' + error);
        }
    });
}

function clearScanOutput() {
    $('#scanOutput').html('');
}

// ==================== DNS Lookup ====================
function openDnsModal() {
    $('#dnsTarget').val('');
    $('#dnsOutput').html('');
    $('#dnsModal').modal('show');
}

function startDnsLookup() {
    const target = $('#dnsTarget').val();
    const type = $('#dnsType').val();
    
    if (!target) {
        showToast('Enter domain name', 'error');
        return;
    }
    
    $('#dnsOutput').html('Looking up...\n');
    
    const headers = {
        'Content-Type': 'application/x-www-form-urlencoded'
    };
    if (window.apiConfig && window.apiConfig.apiKey) {
        headers['X-API-Key'] = window.apiConfig.apiKey;
    }
    
    $.ajax({
        url: url + 'diagnostic_api.php?action=dns_lookup',
        method: 'POST',
        data: { target: target, type: type },
        headers: headers,
        success: function(response) {
            if (response.success) {
                $('#dnsOutput').html(response.data);
            } else {
                $('#dnsOutput').html('Error: ' + response.error);
            }
        },
        error: function(xhr, status, error) {
            $('#dnsOutput').html('Error: ' + error);
        }
    });
}

// ==================== Bandwidth Test ====================
let speedChart = null;

function openBandwidthModal() {
    $('#bandwidthResult').html('');
    $('#bandwidthModal').modal('show');
    initSpeedChart();
}

function initSpeedChart() {
    const ctx = document.getElementById('speedChart').getContext('2d');
    if (speedChart) speedChart.destroy();
    speedChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: [],
            datasets: [{
                label: 'Speed (Mbps)',
                data: [],
                borderColor: '#007aff',
                backgroundColor: 'rgba(0, 122, 255, 0.1)',
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            scales: { y: { beginAtZero: true, title: { display: true, text: 'Mbps' } } }
        }
    });
}

function startBandwidthTest() {
    $('#bandwidthResult').html('<div class="spinner-border text-primary"></div> Testing bandwidth...');
    speedData = [];
    if (speedChart) {
        speedChart.data.labels = [];
        speedChart.data.datasets[0].data = [];
        speedChart.update();
    }
    
    const headers = {};
    if (window.apiConfig && window.apiConfig.apiKey) {
        headers['X-API-Key'] = window.apiConfig.apiKey;
    }
    
    $.ajax({
        url: url + 'diagnostic_api.php?action=bandwidth_test',
        method: 'POST',
        headers: headers,
        success: function(response) {
            if (response.success) {
                const data = response.data;
                let html = `
                    <div class="row text-center">
                        <div class="col-6">
                            <div class="stat-card" style="padding: 15px;">
                                <i class="bi bi-arrow-down-circle text-primary"></i>
                                <div class="stat-value" style="font-size: 24px;">${data.download_mbps} Mbps</div>
                                <div class="stat-label">Download</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="stat-card" style="padding: 15px;">
                                <i class="bi bi-arrow-up-circle text-success"></i>
                                <div class="stat-value" style="font-size: 24px;">${data.upload_mbps} Mbps</div>
                                <div class="stat-label">Upload</div>
                            </div>
                        </div>
                    </div>
                    <div class="text-center mt-2">
                        <small class="text-muted">Ping: ${data.ping_ms} ms | Jitter: ${data.jitter_ms} ms</small>
                    </div>
                `;
                $('#bandwidthResult').html(html);
                
                if (speedChart && data.speed_samples) {
                    const samples = data.speed_samples;
                    speedChart.data.labels = samples.map((_, i) => i + 1);
                    speedChart.data.datasets[0].data = samples;
                    speedChart.update();
                }
            } else {
                $('#bandwidthResult').html('<div class="alert alert-danger">Error: ' + response.error + '</div>');
            }
        },
        error: function(xhr, status, error) {
            $('#bandwidthResult').html('<div class="alert alert-danger">Error performing bandwidth test: ' + error + '</div>');
        }
    });
}

// ==================== Auto-refresh ====================
let autoRefreshInterval;

function startAutoRefresh() {
    loadSystemStats();
    refreshProcesses();
    refreshConnections();
    refreshServices();
    refreshLogs();
    updateNetworkStats();
    
    setInterval(() => loadSystemStats(), 5000);
    setInterval(() => refreshProcesses(), 5000);
    setInterval(() => refreshConnections(), 5000);
    setInterval(() => updateNetworkStats(), 2000);
    setInterval(() => refreshServices(), 10000);
}

// Event handlers
$(document).ready(function() {
    initNetworkChart();
    startAutoRefresh();
    
    $('#processSort').on('change', () => refreshProcesses());
    $('#connFilter').on('keyup', () => refreshConnections());
    $('#serviceFilter').on('change', () => refreshServices());
    $('#logLines, #logType').on('change', () => refreshLogs());
    $('#logFilter').on('keyup', () => refreshLogs());
    
    $('#pingModal').on('hidden.bs.modal', function() {
        stopPing();
    });
});
</script>
</body>
</html>