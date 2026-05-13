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

$menu = require_once 'menu.php';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Mini-B - NFS</title>
    <link href="lib/bootstrap-5.3.8-dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="lib/fontawesome-free-6.7.2-web/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="css/loader.css">
    <link rel="shortcut icon" href="css/icon.ico" type="image/x-icon">
	<script src="js/hosts_load.js"></script>
	<script src="js/crt_checker.js"></script>
	<script>
window.apiConfig = <?php echo json_encode($js_config); ?>;
//console.log('API Config loaded:', window.apiConfig);
</script>
    <style>

        /* Status Badges */
        .status-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-running {
            background: #d4edda;
            color: #155724;
        }

        .status-stopped {
            background: #f8d7da;
            color: #721c24;
        }

        .status-enabled {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status-disabled {
            background: #f8d7da;
            color: #721c24;
        }

        /* Layout */
        .app-container {
            display: flex;
        }

        .main-content {
            margin-left: 260px;
            padding: 24px 32px;
            flex: 1;
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s;
                z-index: 999;
            }
            .sidebar.open {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
        }

        /* Widgets */
        .widget {
            background: white;
            border-radius: 16px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            overflow: hidden;
            border: 1px solid #e9ecef;
        }

        .widget-header {
            padding: 14px 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .widget-header h3 {
            font-size: 15px;
            font-weight: 600;
            margin: 0;
            color: #1c1c1e;
        }

        .widget-header h3 i {
            color: #007aff;
            margin-right: 8px;
        }

        .widget-body {
            padding: 16px;
        }

        .widget-body.p-0 {
            padding: 0;
        }

        /* Storage Cards */
        .storage-card {
            margin: 0 0 12px 0;
            background: #f8f9fa;
            border-radius: 12px;
            padding: 12px 16px;
            transition: all 0.2s;
            border: 1px solid #e9ecef;
        }

        .storage-card:last-child {
            margin-bottom: 0;
        }

        .storage-card:hover {
            background: #fff;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        }

        .storage-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .storage-name {
            font-size: 13px;
            font-weight: 600;
            color: #495057;
        }

        .storage-name i {
            margin-right: 6px;
            color: #6c757d;
        }

        .storage-type {
            font-size: 10px;
            padding: 2px 8px;
            border-radius: 20px;
            font-weight: 500;
        }

        .progress-custom {
            height: 6px;
            border-radius: 3px;
            background-color: #e9ecef;
            overflow: hidden;
            margin: 8px 0;
        }

        .progress-fill {
            height: 100%;
            border-radius: 3px;
            transition: width 0.3s ease;
        }

        .storage-stats {
            font-size: 11px;
            color: #6c757d;
            display: flex;
            justify-content: space-between;
        }

        .storage-stats i {
            margin-right: 4px;
        }

        /* Export Badges */
        .export-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #e9ecef;
            padding: 5px 12px;
            border-radius: 30px;
            font-size: 13px;
            margin: 3px;
        }

        .export-badge i {
            color: #6c757d;
        }

        .export-badge-actions {
            display: inline-flex;
            gap: 5px;
            margin-left: 5px;
        }

        .export-badge-actions .btn-link {
            text-decoration: none;
        }

        /* Tables */
        .table-export th {
            background: #f8f9fa;
            border-bottom: 2px solid #e9ecef;
            font-size: 13px;
            font-weight: 600;
        }

        .table-export td {
            font-size: 13px;
            vertical-align: middle;
        }

        .table-export-path {
            font-family: monospace;
            font-size: 12px;
            max-width: 280px;
            word-break: break-all;
            background: #f8f9fa;
            padding: 4px 8px;
            border-radius: 6px;
            display: inline-block;
        }

        /* Buttons */
        .btn {
            border-radius: 10px;
            font-weight: 500;
            transition: all 0.2s;
        }

        .btn-sm {
            border-radius: 8px;
            padding: 4px 10px;
            font-size: 12px;
        }

        .btn-outline-primary:hover,
        .btn-outline-danger:hover,
        .btn-outline-warning:hover {
            transform: translateY(-1px);
        }

        /* Folder Browser */
        .folder-browser {
            min-height: 350px;
            max-height: 450px;
            overflow-y: auto;
        }

        .folder-item {
            cursor: pointer;
            padding: 10px 15px;
            border-radius: 8px;
            transition: all 0.2s;
        }

        .folder-item:hover {
            background-color: #e9ecef;
        }

        .folder-item i {
            margin-right: 8px;
            color: #ffc107;
        }

        .breadcrumb-item {
            cursor: pointer;
        }

        .breadcrumb-item a {
            text-decoration: none;
            color: #007aff;
        }

        .breadcrumb-item a:hover {
            text-decoration: underline;
        }

        /* Client Lists */
        .client-list {
            max-height: 200px;
            overflow-y: auto;
        }

        .client-item {
            padding: 8px 12px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .client-item:last-child {
            border-bottom: none;
        }

        .client-ip {
            font-family: monospace;
            font-weight: 600;
        }

        .client-path {
            font-family: monospace;
            font-size: 11px;
            color: #6c757d;
        }

        /* Alerts */
        .alert {
            padding: 2px;
            border-radius: 4px;
            animation: slideInDown 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 0px;
        }

        .alert .btn-close {
            width: 12px;
            height: 12px;
            padding: 2px;
            margin: 0;
            background-size: 12px 12px;
            opacity: 0.6;
            transition: opacity 0.2s;
            flex-shrink: 0;
            position: relative;
            top: 0;
            transform: none;
        }

        @keyframes slideInDown {
            from {
                transform: translateY(-100%);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
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

        /* Modal Customization */
        .modal-header.bg-primary,
        .modal-header.bg-success,
        .modal-header.bg-warning {
            border-bottom: none;
        }

        .modal-header .btn-close-white {
            filter: brightness(0) invert(1);
        }

        /* Options Help */
        .options-help {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 12px;
            margin-top: 16px;
            font-size: 12px;
        }

        .options-help ul {
            margin-top: 8px;
            margin-bottom: 0;
            padding-left: 20px;
        }

        .options-help code {
            background: #e9ecef;
            padding: 2px 6px;
            border-radius: 4px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .table-responsive {
                font-size: 12px;
            }
            
            .table-export-path {
                max-width: 150px;
                font-size: 10px;
            }
            
            .widget-header {
                flex-wrap: wrap;
                gap: 10px;
            }
            
            .storage-card {
                padding: 10px;
            }
        }

        /* Utility Classes */
        .cursor-pointer {
            cursor: pointer;
        }

        .gap-2 {
            gap: 8px;
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
        
        <div class="status-group" id="statusGroup">
            <span class="status-badge" id="nfsRunningBadge">
                <i class="fas fa-play-circle"></i> NFS: Loading...
            </span>
            <span class="status-badge" id="nfsEnabledBadge">
                <i class="fas fa-check-circle"></i> Auto: Loading...
            </span>
            <span class="status-badge" id="nfsPidBadge" style="background:#e9ecef; color:#495057; display:none;">
                <i class="fas fa-microchip"></i> PID: <span id="nfsPid">-</span>
            </span>
            <span class="status-badge" id="nfsVersionBadge" style="background:#e9ecef; color:#495057;">
                <i class="fas fa-code-branch"></i> v<span id="nfsVersion">-</span>
            </span>
        </div>
        <div class="host-selector" style="margin-left: 20px;">
            <select id="hostSelector" style="background: rgba(255,255,255,0.9); border: 1px solid #ddd; border-radius: 20px; padding: 6px 30px 6px 15px; font-size: 14px; cursor: pointer;">
                <option value="">Loading...</option>
            </select>
        </div>
        <div class="dropdown">
            <button class="btn btn-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                <i class="fas fa-power-off"></i> Management
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><button class="dropdown-item" onclick="serviceAction('start')"><i class="fas fa-play text-success"></i> Start</button></li>
                <li><button class="dropdown-item" onclick="serviceAction('stop')"><i class="fas fa-stop text-danger"></i> Stop</button></li>
                <li><button class="dropdown-item" onclick="serviceAction('restart')"><i class="fas fa-sync-alt text-warning"></i> Restart</button></li>
                <li><hr class="dropdown-divider"></li>
                <li><button class="dropdown-item" id="toggleAutostartBtn" onclick="toggleAutostart()"><i class="fas fa-play text-info"></i> Enable autostart</button></li>
            </ul>
        </div>
        
        <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#settingsModal">
            <i class="fas fa-cog"></i>
        </button>
        <i class="fas fa-sync-alt refresh-btn text-muted" onclick="refreshAllData()" title="Refresh"></i>
    </div>
</div>

<div class="app-container">
    <?php echo $menu; ?>
    
    <main class="main-content">
	<div id="alertContainer"></div>
        <div class="row">
            <div class="col-md-8">
                <div class="widget">
                    <div class="widget-header">
                        <h3><i class="fas fa-share-alt text-primary"></i> NFS Exports</h3>
                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addExportModal">
                            <i class="fas fa-plus"></i> Create Export
                        </button>
                    </div>
                    <div class="widget-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover table-export mb-0">
                                <thead>
                                    <tr><th>Path</th><th>Client</th><th>Options</th><th>Actions</th></tr>
                                </thead>
                                <tbody id="exportsTableBody">
                                    <tr><td colspan="4" class="text-center text-muted py-4"><div class="loading-spinner-sm"></div> Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="options-help">
                            <strong><i class="fas fa-info-circle"></i> NFS Options:</strong>
                            <ul>
                                <li><code>rw</code> - read and write, <code>ro</code> - read only</li>
                                <li><code>sync</code> - synchronous write, <code>async</code> - asynchronous (faster)</li>
                                <li><code>no_subtree_check</code> - disable subtree checking</li>
                                <li><code>root_squash</code> - map root to nobody, <code>no_root_squash</code> - allow root</li>
                                <li><code>insecure</code> - allow connections from ports > 1024</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="widget">
                    <div class="widget-header">
                        <h3><i class="fas fa-hdd text-primary"></i> Disk Partitions</h3>
                        <a class="btn btn-primary btn-sm" href="disk_manager.php"><i class="fas fa-tools"></i></a>
                    </div>
                    <div class="widget-body" id="storagesContainer">
                        <div class="text-center py-3"><div class="loading-spinner-sm"></div> Loading...</div>
                    </div>
                </div>
                
                <div class="widget">
                    <div class="widget-header">
                        <h3><i class="fas fa-chart-line text-primary"></i> NFS Statistics</h3>
                        <i class="fas fa-sync-alt refresh-btn text-muted" onclick="loadStats()" style="font-size:12px"></i>
                    </div>
                    <div class="widget-body">
                        <div class="row mb-3">
                            <div class="col-6">
                                <div class="storage-card text-center" style="margin:0">
                                    <i class="fas fa-folder-open fa-2x text-primary mb-2 d-block"></i>
                                    <div class="storage-name" style="font-size:24px; font-weight:700" id="statExports">-</div>
                                    <div class="storage-stats justify-content-center">Exports</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="storage-card text-center" style="margin:0">
                                    <i class="fas fa-network-wired fa-2x text-success mb-2 d-block"></i>
                                    <div class="storage-name" style="font-size:24px; font-weight:700" id="statClients">-</div>
                                    <div class="storage-stats justify-content-center">Clients</div>
                                </div>
                            </div>
                        </div>
                        
                        <h6><i class="fas fa-users"></i> Connected Clients</h6>
                        <div class="client-list border rounded" id="clientList">
                            <div class="text-muted text-center p-3"><div class="loading-spinner-sm"></div> Loading...</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<div class="modal fade" id="settingsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-sliders-h"></i> Global NFS Settings</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="globalConfigForm">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Number of NFSD Threads</label>
                            <input type="number" name="threads" id="config_threads" class="form-control" min="1" max="128">
                            <small class="text-muted">Number of NFS server cores</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">NFSv4 Lease Time (sec)</label>
                            <input type="number" name="nfsv4_lease_time" id="config_lease_time" class="form-control" min="30" max="360">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">NFSv4 Grace Period (sec)</label>
                            <input type="number" name="nfsv4_grace_period" id="config_grace_period" class="form-control" min="30" max="360">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">mountd Port</label>
                            <input type="number" name="mountd_port" id="config_mountd_port" class="form-control" placeholder="Auto">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">statd Port</label>
                            <input type="number" name="statd_port" id="config_statd_port" class="form-control" placeholder="Auto">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">lockd Port</label>
                            <input type="number" name="lockd_port" id="config_lockd_port" class="form-control" placeholder="Auto">
                        </div>
                        <div class="col-md-12">
                            <div class="form-check form-check-inline">
                                <input type="checkbox" name="need_statd" class="form-check-input" id="config_need_statd">
                                <label class="form-check-label" for="config_need_statd">Enable statd</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input type="checkbox" name="need_idmapd" class="form-check-input" id="config_need_idmapd">
                                <label class="form-check-label" for="config_need_idmapd">Enable idmapd</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input type="checkbox" name="need_gssd" class="form-check-input" id="config_need_gssd">
                                <label class="form-check-label" for="config_need_gssd">Enable GSS (Kerberos)</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="addExportModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-plus-circle"></i> Create NFS Export</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="addExportForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Path *</label>
                        <div class="input-group">
                            <input type="text" name="export_path" id="addExportPath" class="form-control" readonly required>
                            <button type="button" class="btn btn-secondary" onclick="openFolderBrowser('addExportPath')">
                                <i class="fas fa-folder-open"></i> Browse
                            </button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Client *</label>
                        <input type="text" name="client" class="form-control" value="*" required>
                        <small class="text-muted">* - all, or IP: 192.168.1.0/24, or IP: 192.168.1.100</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Options *</label>
                        <input type="text" name="options" class="form-control" value="rw,sync,no_subtree_check" required>
                        <small class="text-muted">rw, ro, sync, async, no_subtree_check, root_squash, no_root_squash</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-success">Create Export</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editExportModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-edit"></i> Edit NFS Export</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="editExportForm">
                <div class="modal-body">
                    <input type="hidden" name="old_path" id="edit_old_path">
                    <input type="hidden" name="old_client" id="edit_old_client">
                    <div class="mb-3">
                        <label class="form-label">Path *</label>
                        <div class="input-group">
                            <input type="text" name="export_path" id="editExportPath" class="form-control" readonly required>
                            <button type="button" class="btn btn-secondary" onclick="openFolderBrowserEdit()">
                                <i class="fas fa-folder-open"></i> Browse
                            </button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Client *</label>
                        <input type="text" name="client" id="editClient" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Options *</label>
                        <input type="text" name="options" id="editOptions" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="browseFolderModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-folder-open"></i> Select Folder</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <nav aria-label="breadcrumb"><ol class="breadcrumb" id="folderBreadcrumb"></ol></nav>
                <div class="input-group mb-3">
                    <input type="text" id="currentPath" class="form-control" readonly>
                    <button type="button" class="btn btn-success" onclick="showCreateFolderDialog()"><i class="fas fa-folder-plus"></i> Create Folder</button>
                </div>
                <div class="folder-browser" id="folderBrowser"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="selectCurrentFolder()">Select</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="browseFolderModalEdit" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-folder-open"></i> Select Folder</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <nav aria-label="breadcrumb"><ol class="breadcrumb" id="folderBreadcrumbEdit"></ol></nav>
                <div class="input-group mb-3">
                    <input type="text" id="currentPathEdit" class="form-control" readonly>
                    <button type="button" class="btn btn-success" onclick="showCreateFolderDialogEdit()"><i class="fas fa-folder-plus"></i> Create Folder</button>
                </div>
                <div class="folder-browser" id="folderBrowserEdit"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="selectCurrentFolderEdit()">Select</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="createFolderDialog" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content"><div class="modal-header bg-success text-white"><h5 class="modal-title"><i class="fas fa-folder-plus"></i> Create Folder</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="mb-3"><label class="form-label">Path:</label> <code id="createFolderPath"></code></div><div class="mb-3"><label class="form-label">Folder Name</label><input type="text" id="newFolderName" class="form-control" placeholder="New Folder"></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-success" onclick="createNewFolder()">Create</button></div></div></div>
</div>

<div class="modal fade" id="createFolderDialogEdit" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content"><div class="modal-header bg-success text-white"><h5 class="modal-title"><i class="fas fa-folder-plus"></i> Create Folder</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="mb-3"><label class="form-label">Path:</label> <code id="createFolderPathEdit"></code></div><div class="mb-3"><label class="form-label">Folder Name</label><input type="text" id="newFolderNameEdit" class="form-control" placeholder="New Folder"></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-success" onclick="createNewFolderEdit()">Create</button></div></div></div>
</div>

<script src="lib/jquery-3.6.0-master/dist/jquery.min.js"></script>
<script src="lib/bootstrap-5.3.8-dist/js/bootstrap.bundle.min.js"></script>
<script>
const url = "<?php echo $current_host_id == 1 ? '/api/' : rtrim($host_url, '/') . '/'; ?>";
let currentTargetInput = null;
let currentBrowsePath = '/';
let currentBrowsePathEdit = '/';

function showAlert(message, type = 'success') {
    const alertHtml = `<div class="alert alert-${type} alert-dismissible fade show" role="alert"><i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'}"></i> ${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
    $('#alertContainer').append(alertHtml);
    setTimeout(() => $('.alert').fadeOut(500, function() { $(this).remove(); }), 5000);
}

function escapeHtml(str) { if (!str) return ''; return str.replace(/[&<>]/g, function(m) { return { '&':'&amp;', '<':'&lt;', '>':'&gt;' }[m]; }); }

async function apiCall(action, method = 'GET', data = null) {
    let fullUrl = `${url}shares_nfs_api.php?action=${action}`;
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

async function loadStatus() {
    let result = await apiCall('get_status');
    if (result.success) {
        let status = result.data;
        $('#nfsRunningBadge').html(`<i class="fas fa-${status.running ? 'play-circle' : 'stop-circle'}"></i> NFS: ${status.running ? 'Running' : 'Stopped'}`);
        $('#nfsRunningBadge').removeClass('status-running status-stopped').addClass(status.running ? 'status-running' : 'status-stopped');
        $('#nfsEnabledBadge').html(`<i class="fas fa-${status.enabled ? 'check-circle' : 'times-circle'}"></i> Auto: ${status.enabled ? 'On' : 'Off'}`);
        $('#nfsEnabledBadge').removeClass('status-enabled status-disabled').addClass(status.enabled ? 'status-enabled' : 'status-disabled');
        if (status.pid) { $('#nfsPidBadge').show(); $('#nfsPid').text(status.pid); } else { $('#nfsPidBadge').hide(); }
        $('#nfsVersion').text(status.version || 'unknown');
        
        let toggleBtn = $('#toggleAutostartBtn');
        toggleBtn.html(`<i class="fas fa-${status.enabled ? 'pause' : 'play'} text-info"></i> ${status.enabled ? 'Disable autostart' : 'Enable autostart'}`);
    }
}

async function serviceAction(action) {
    let result = await apiCall('service_action', 'POST', { service_action: action });
    if (result.success) { showAlert(`Service ${action}ed`, 'success'); loadStatus(); loadStats(); }
    else showAlert(`Error during ${action}`, 'danger');
}

async function toggleAutostart() {
    let result = await apiCall('get_status');
    if (result.success) {
        let action = result.data.enabled ? 'disable' : 'enable';
        await serviceAction(action);
    }
}

async function loadConfig() {
    let result = await apiCall('get_config');
    if (result.success) {
        let cfg = result.data;
        $('#config_threads').val(cfg.threads);
        $('#config_lease_time').val(cfg.nfsv4_lease_time);
        $('#config_grace_period').val(cfg.nfsv4_grace_period);
        $('#config_mountd_port').val(cfg.mountd_port || '');
        $('#config_statd_port').val(cfg.statd_port || '');
        $('#config_lockd_port').val(cfg.lockd_port || '');
        $('#config_need_statd').prop('checked', cfg.need_statd);
        $('#config_need_idmapd').prop('checked', cfg.need_idmapd);
        $('#config_need_gssd').prop('checked', cfg.need_gssd);
    }
}

$('#globalConfigForm').on('submit', async function(e) {
    e.preventDefault();
    let data = $(this).serialize();
    let result = await apiCall('save_config', 'POST', data);
    if (result.success) { showAlert('Configuration saved', 'success'); $('#settingsModal').modal('hide'); loadStatus(); }
    else showAlert('Save error', 'danger');
});

async function loadExports() {
    let result = await apiCall('get_exports');
    if (result.success) {
        let exports = result.data;
        if (exports.length === 0) {
            $('#exportsTableBody').html('<tr><td colspan="4" class="text-center text-muted py-4"><i class="fas fa-folder-open fa-2x mb-2 d-block"></i>No NFS exports created</td></tr>');
        } else {
            let html = '';
            exports.forEach((exp, idx) => {
                html += `<tr>
                    <td><code class="table-export-path">${escapeHtml(exp.path)}</code></td>
                    <td><code>${escapeHtml(exp.client)}</code></td>
                    <td><span class="badge bg-info">${escapeHtml(exp.options)}</span></td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-primary" onclick="editExport(${idx}, '${escapeHtml(exp.path)}', '${escapeHtml(exp.client)}', '${escapeHtml(exp.options)}')"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-outline-danger" onclick="deleteExport(${idx})"><i class="fas fa-trash"></i></button>
                        </div>
                     </td>
                 </tr>`;
            });
            $('#exportsTableBody').html(html);
        }
    }
}

$('#addExportForm').on('submit', async function(e) {
    e.preventDefault();
    let data = $(this).serialize();
    let result = await apiCall('create_export', 'POST', data);
    if (result.success) { showAlert(result.message, 'success'); $('#addExportModal').modal('hide'); loadExports(); loadStats(); loadStatus(); }
    else showAlert(result.error || 'Error', 'danger');
});

function editExport(index, path, client, options) {
    $('#edit_old_path').val(path);
    $('#edit_old_client').val(client);
    $('#editExportPath').val(path);
    $('#editClient').val(client);
    $('#editOptions').val(options);
    $('#editExportModal').modal('show');
}

$('#editExportForm').on('submit', async function(e) {
    e.preventDefault();
    let data = $(this).serialize();
    let result = await apiCall('update_export', 'POST', data);
    if (result.success) { showAlert(result.message, 'success'); $('#editExportModal').modal('hide'); loadExports(); loadStats(); loadStatus(); }
    else showAlert(result.error || 'Error', 'danger');
});

async function deleteExport(index) {
    if (!confirm(`Delete NFS export?`)) return;
    let result = await apiCall('delete_export', 'POST', { index: index });
    if (result.success) { showAlert(result.message, 'success'); loadExports(); loadStats(); loadStatus(); }
    else showAlert(result.error || 'Error', 'danger');
}

async function loadStorages() {
    let result = await apiCall('get_storages');
    if (result.success) {
        let storages = result.storages;
        if (storages.length === 0) {
            $('#storagesContainer').html('<div class="alert alert-info">No available partitions. Connect disks.</div>');
        } else {
            let html = '';
            storages.forEach(s => {
                let fillColor = s.used_percent > 90 ? '#dc3545' : (s.used_percent > 70 ? '#ffc107' : '#28a745');
                html += `<div class="storage-card"><div class="storage-header"><span class="storage-name"><i class="fas fa-hdd"></i> ${escapeHtml(s.name)}</span><span class="storage-type type-${s.type}">${s.type.toUpperCase()}</span></div><div class="storage-mount"><i class="fas fa-folder-open"></i> ${escapeHtml(s.mount)}</div><div class="storage-stats mb-1"><span><i class="fas fa-chart-pie"></i> Used: ${s.used_percent}%</span><span><i class="fas fa-database"></i> ${s.size_gb}</span></div><div class="progress-custom"><div class="progress-fill" style="width: ${s.used_percent}%; background: ${fillColor};"></div></div><div class="storage-stats"><span><i class="fas fa-chart-line"></i> Used: ${s.used || '—'}</span><span><i class="fas fa-check-circle text-success"></i> Free: ${s.available || '—'}</span></div></div>`;
            });
            $('#storagesContainer').html(html);
        }
    }
}

async function loadStats() {
    let result = await apiCall('get_stats');
    if (result.success) {
        $('#statExports').text(result.data.total_exports);
        $('#statClients').text(result.data.clients_connected);
    }
    
    let clientsResult = await apiCall('get_clients');
    if (clientsResult.success) {
        let clients = clientsResult.data;
        let html = '';
        if (clients.length === 0) {
            html = '<div class="text-muted text-center p-3">No connected clients</div>';
        } else {
            clients.forEach(client => {
                html += `<div class="client-item">
                    <span><i class="fas fa-laptop"></i> <strong class="client-ip">${escapeHtml(client.client)}</strong></span>
                    <span class="client-path"><code>${escapeHtml(client.path)}</code></span>
                </div>`;
            });
        }
        $('#clientList').html(html);
    }
}

async function loadFolder(path, container, breadcrumbContainer, currentPathInput) {
    let result = await apiCall('browse', 'GET', { path: path });
    if (result.success) {
        if (container === '#folderBrowser') {
            currentBrowsePath = result.path;
        } else {
            currentBrowsePathEdit = result.path;
        }
        $(currentPathInput).val(result.path);
        
        let parts = result.path.split('/').filter(p => p);
        let breadcrumbHtml = '<li class="breadcrumb-item"><a onclick="loadFolder(\'/\', \'' + container + '\', \'' + breadcrumbContainer + '\', \'' + currentPathInput + '\')">/</a></li>';
        let cp = '';
        parts.forEach(p => { cp += '/' + p; breadcrumbHtml += `<li class="breadcrumb-item"><a onclick="loadFolder('${cp}', '${container}', '${breadcrumbContainer}', '${currentPathInput}')">${escapeHtml(p)}</a></li>`; });
        $(breadcrumbContainer).html(breadcrumbHtml);
        
        let listHtml = '<div class="list-group">';
        if (result.path !== '/') listHtml += `<div class="list-group-item list-group-item-action folder-item" onclick="loadFolder('${result.parent}', '${container}', '${breadcrumbContainer}', '${currentPathInput}')"><i class="fas fa-level-up-alt"></i> ..</div>`;
        result.items.forEach(item => {
            listHtml += `<div class="list-group-item list-group-item-action folder-item" onclick="selectDirectory('${item.path}', '${container}', '${currentPathInput}')"><i class="fas fa-folder"></i> ${escapeHtml(item.name)}</div>`;
        });
        if (result.items.length === 0 && result.path !== '/') listHtml += '<div class="list-group-item text-muted">Empty folder</div>';
        listHtml += '</div>';
        $(container).html(listHtml);
    } else {
        showAlert('Error loading folders', 'danger');
    }
}

function selectDirectory(path, container, currentPathInput) {
    $(currentPathInput).val(path);
    if (container === '#folderBrowser') {
        currentBrowsePath = path;
        loadFolder(path, container, '#folderBreadcrumb', currentPathInput);
    } else {
        currentBrowsePathEdit = path;
        loadFolder(path, container, '#folderBreadcrumbEdit', currentPathInput);
    }
}

function selectCurrentFolder() {
    if (currentTargetInput) {
        $(`#${currentTargetInput}`).val(currentBrowsePath);
        $('#browseFolderModal').modal('hide');
        showAlert('Папка выбрана: ' + currentBrowsePath, 'success');
    }
}

function selectCurrentFolderEdit() {
    $('#editExportPath').val(currentBrowsePathEdit);
    $('#browseFolderModalEdit').modal('hide');
}

function openFolderBrowser(targetInputId) {
    currentTargetInput = targetInputId;
    let startPath = $(`#${targetInputId}`).val() || '/';
    loadFolder(startPath, '#folderBrowser', '#folderBreadcrumb', '#currentPath');
    $('#browseFolderModal').modal('show');
}

function openFolderBrowserEdit() {
    let startPath = $('#editExportPath').val() || '/';
    loadFolder(startPath, '#folderBrowserEdit', '#folderBreadcrumbEdit', '#currentPathEdit');
    $('#browseFolderModalEdit').modal('show');
}

function showCreateFolderDialog() {
    $('#createFolderPath').text(currentBrowsePath);
    $('#newFolderName').val('');
    $('#createFolderDialog').modal('show');
}

function showCreateFolderDialogEdit() {
    $('#createFolderPathEdit').text(currentBrowsePathEdit);
    $('#newFolderNameEdit').val('');
    $('#createFolderDialogEdit').modal('show');
}

async function createNewFolder() {
    let path = $('#createFolderPath').text();
    let name = $('#newFolderName').val();
    if (!name) { showAlert('Enter folder name', 'danger'); return; }
    let result = await apiCall('create_folder', 'POST', { path: path, name: name });
    if (result.success) {
        $('#createFolderDialog').modal('hide');
        loadFolder(currentBrowsePath, '#folderBrowser', '#folderBreadcrumb', '#currentPath');
        showAlert('Folder "' + name + '" created', 'success');
    } else {
        showAlert(result.error, 'danger');
    }
}

async function createNewFolderEdit() {
    let path = $('#createFolderPathEdit').text();
    let name = $('#newFolderNameEdit').val();
    if (!name) { showAlert('Enter folder name', 'danger'); return; }
    let result = await apiCall('create_folder', 'POST', { path: path, name: name });
    if (result.success) {
        $('#createFolderDialogEdit').modal('hide');
        loadFolder(currentBrowsePathEdit, '#folderBrowserEdit', '#folderBreadcrumbEdit', '#currentPathEdit');
        showAlert('Folder "' + name + '" created', 'success');
    } else {
        showAlert(result.error, 'danger');
    }
}

async function refreshAllData() {
    await Promise.all([loadStatus(), loadConfig(), loadExports(), loadStorages(), loadStats()]);
    showAlert('Data updated', 'success');
}

setInterval(() => {
    loadStats();
}, 10000);

$(document).ready(function() {
    refreshAllData();
    setTimeout(function() { $('#applePreloader').fadeOut(500); }, 500);
});
</script>
<script src="js/loader.js"></script>
</body>
</html>