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
    <title>Mini-B - FTP</title>
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

        .user-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #e9ecef;
            padding: 5px 12px;
            border-radius: 30px;
            font-size: 13px;
            margin: 3px;
        }

        .user-badge i {
            color: #6c757d;
        }

        .user-badge-actions {
            display: inline-flex;
            gap: 5px;
            margin-left: 5px;
        }

        .user-badge-actions .btn-link {
            text-decoration: none;
        }

        .table-ftp th {
            background: #f8f9fa;
            border-bottom: 2px solid #e9ecef;
            font-size: 13px;
            font-weight: 600;
        }

        .table-ftp td {
            font-size: 13px;
            vertical-align: middle;
        }

        .table-ftp-path {
            font-family: monospace;
            font-size: 12px;
            max-width: 280px;
            word-break: break-all;
            background: #f8f9fa;
            padding: 4px 8px;
            border-radius: 6px;
            display: inline-block;
        }

        .share-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: #e9ecef;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            margin: 2px;
        }

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

        .user-select-list {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 10px;
            background: white;
        }

        .log-viewer {
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 12px;
            border-radius: 12px;
            font-family: 'Courier New', monospace;
            font-size: 11px;
            max-height: 250px;
            overflow-y: auto;
        }

        .log-line {
            padding: 4px 0;
            border-bottom: 1px solid #333;
            font-family: monospace;
            font-size: 11px;
        }

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

        .refresh-btn {
            cursor: pointer;
            transition: transform 0.2s;
            font-size: 16px;
        }

        .refresh-btn:hover {
            transform: rotate(180deg);
        }

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

        .modal-header.bg-primary,
        .modal-header.bg-success,
        .modal-header.bg-warning {
            border-bottom: none;
        }

        .modal-header .btn-close-white {
            filter: brightness(0) invert(1);
        }

        @media (max-width: 768px) {
            .table-responsive {
                font-size: 12px;
            }
            
            .table-ftp-path {
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
            <span class="status-badge" id="ftpRunningBadge">
                <i class="fas fa-play-circle"></i> FTP: Loading...
            </span>
            <span class="status-badge" id="ftpEnabledBadge">
                <i class="fas fa-check-circle"></i> Auto: Loading...
            </span>
            <span class="status-badge" id="ftpPidBadge" style="background:#e9ecef; color:#495057; display:none;">
                <i class="fas fa-microchip"></i> PID: <span id="ftpPid">-</span>
            </span>
            <span class="status-badge" id="ftpVersionBadge" style="background:#e9ecef; color:#495057;">
                <i class="fas fa-code-branch"></i> v<span id="ftpVersion">-</span>
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
                        <h3><i class="fas fa-folder-open text-primary"></i> FTP Directories</h3>
                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addFtpShareModal">
                            <i class="fas fa-plus"></i> Add Directory
                        </button>
                    </div>
                    <div class="widget-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover table-ftp mb-0">
                                <thead>
                                    <tr><th>Name</th><th>Path</th><th>Access</th><th>Actions</th></tr>
                                </thead>
                                <tbody id="sharesTableBody">
                                    <tr><td colspan="4" class="text-center text-muted py-4"><div class="loading-spinner-sm"></div> Loading...</td></tr>
                                </tbody>
                            </table>
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
                        <h3><i class="fas fa-users text-primary"></i> FTP Users</h3>
                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addFtpUserModal">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                    <div class="widget-body" id="usersContainer">
                        <div class="text-center py-3"><div class="loading-spinner-sm"></div> Loading...</div>
                    </div>
                </div>
                
                <div class="widget">
                    <div class="widget-header">
                        <h3><i class="fas fa-history text-primary"></i> FTP Log</h3>
                        <i class="fas fa-sync-alt refresh-btn text-muted" onclick="loadLogs()" style="font-size:12px"></i>
                    </div>
                    <div class="widget-body p-0">
                        <div class="log-viewer" id="logsContainer">
                            <div class="text-muted text-center py-3"><div class="loading-spinner-sm"></div> Loading...</div>
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
                <h5 class="modal-title"><i class="fas fa-sliders-h"></i> Global FTP Settings</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="globalConfigForm">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input type="checkbox" name="anonymous_enable" class="form-check-input" id="config_anonymous">
                                <label class="form-check-label" for="config_anonymous">Anonymous Access</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input type="checkbox" name="local_enable" class="form-check-input" id="config_local">
                                <label class="form-check-label" for="config_local">Local Users</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input type="checkbox" name="write_enable" class="form-check-input" id="config_write">
                                <label class="form-check-label" for="config_write">Allow Write</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input type="checkbox" name="chroot_local_user" class="form-check-input" id="config_chroot">
                                <label class="form-check-label" for="config_chroot">Chroot Users</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input type="checkbox" name="ssl_enable" class="form-check-input" id="config_ssl">
                                <label class="form-check-label" for="config_ssl">SSL/TLS Support</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input type="checkbox" name="pasv_enable" class="form-check-input" id="config_pasv">
                                <label class="form-check-label" for="config_pasv">Passive Mode</label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Local umask</label>
                            <input type="text" name="local_umask" id="config_umask" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Passive Ports (min)</label>
                            <input type="number" name="pasv_min_port" id="config_pasv_min" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Passive Ports (max)</label>
                            <input type="number" name="pasv_max_port" id="config_pasv_max" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Max Clients</label>
                            <input type="number" name="max_clients" id="config_max_clients" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Max per IP</label>
                            <input type="number" name="max_per_ip" id="config_max_per_ip" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Idle Timeout (sec)</label>
                            <input type="number" name="idle_session_timeout" id="config_idle_timeout" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Data Timeout (sec)</label>
                            <input type="number" name="data_connection_timeout" id="config_data_timeout" class="form-control">
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

<div class="modal fade" id="addFtpUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-user-plus"></i> Add FTP User</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="addUserForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Select system user</label>
                        <select name="username" id="ftpUserSelect" class="form-select" required>
                            <option value="">Select user...</option>
                        </select>
                        <small class="text-muted">Only existing system users can get FTP access</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Add</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="addFtpShareModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-plus-circle"></i> Add FTP Directory</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="addShareForm">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Directory Name *</label>
                            <input type="text" name="name" class="form-control" required placeholder="e.g.: documents">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Folder Path *</label>
                            <div class="input-group">
                                <input type="text" name="path" id="addFtpPath" class="form-control" readonly required>
                                <button type="button" class="btn btn-secondary" onclick="openFolderBrowser('addFtpPath')">
                                    <i class="fas fa-folder-open"></i> Browse
                                </button>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">User Access</label>
                            <div class="user-select-list" id="shareUsersList"></div>
                            <small class="text-muted">If no user is selected, the directory will be available to everyone</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-success">Add Directory</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editFtpShareModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-edit"></i> Edit FTP Directory</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="editShareForm">
                <div class="modal-body">
                    <input type="hidden" name="old_id" id="edit_old_id">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Directory Name *</label>
                            <input type="text" name="name" id="edit_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Folder Path *</label>
                            <div class="input-group">
                                <input type="text" name="path" id="edit_path" class="form-control" readonly required>
                                <button type="button" class="btn btn-secondary" onclick="openFolderBrowserEdit()">
                                    <i class="fas fa-folder-open"></i> Browse
                                </button>
                            </div>
                        </div>
                        <div class="col-md-12" id="editUsersContainer"></div>
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
let ftpUsersList = [];
let systemUsersList = [];

function showAlert(message, type = 'success') {
    const alertHtml = `<div class="alert alert-${type} alert-dismissible fade show" role="alert"><i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'}"></i> ${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
    $('#alertContainer').append(alertHtml);
    setTimeout(() => $('.alert').fadeOut(500, function() { $(this).remove(); }), 5000);
}

function escapeHtml(str) { if (!str) return ''; return str.replace(/[&<>]/g, function(m) { return { '&':'&amp;', '<':'&lt;', '>':'&gt;' }[m]; }); }

async function apiCall(action, method = 'GET', data = null) {
    let fullUrl = `${url}shares_ftp_api.php?action=${action}`;
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
        $('#ftpRunningBadge').html(`<i class="fas fa-${status.running ? 'play-circle' : 'stop-circle'}"></i> FTP: ${status.running ? 'Running' : 'Stopped'}`);
        $('#ftpRunningBadge').removeClass('status-running status-stopped').addClass(status.running ? 'status-running' : 'status-stopped');
        $('#ftpEnabledBadge').html(`<i class="fas fa-${status.enabled ? 'check-circle' : 'times-circle'}"></i> Auto: ${status.enabled ? 'On' : 'Off'}`);
        $('#ftpEnabledBadge').removeClass('status-enabled status-disabled').addClass(status.enabled ? 'status-enabled' : 'status-disabled');
        if (status.pid) { $('#ftpPidBadge').show(); $('#ftpPid').text(status.pid); } else { $('#ftpPidBadge').hide(); }
        $('#ftpVersion').text(status.version || 'unknown');
        
        let toggleBtn = $('#toggleAutostartBtn');
        toggleBtn.html(`<i class="fas fa-${status.enabled ? 'pause' : 'play'} text-info"></i> ${status.enabled ? 'Disable autostart' : 'Enable autostart'}`);
    }
}

async function serviceAction(action) {
    let result = await apiCall('service_action', 'POST', { service_action: action });
    if (result.success) { showAlert(`Service ${action}ed`, 'success'); loadStatus(); }
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
        $('#config_anonymous').prop('checked', cfg.anonymous_enable === 'YES');
        $('#config_local').prop('checked', cfg.local_enable === 'YES');
        $('#config_write').prop('checked', cfg.write_enable === 'YES');
        $('#config_chroot').prop('checked', cfg.chroot_local_user === 'YES');
        $('#config_ssl').prop('checked', cfg.ssl_enable === 'YES');
        $('#config_pasv').prop('checked', cfg.pasv_enable === 'YES');
        $('#config_umask').val(cfg.local_umask);
        $('#config_pasv_min').val(cfg.pasv_min_port);
        $('#config_pasv_max').val(cfg.pasv_max_port);
        $('#config_max_clients').val(cfg.max_clients);
        $('#config_max_per_ip').val(cfg.max_per_ip);
        $('#config_idle_timeout').val(cfg.idle_session_timeout);
        $('#config_data_timeout').val(cfg.data_connection_timeout);
    }
}

$('#globalConfigForm').on('submit', async function(e) {
    e.preventDefault();
    let data = $(this).serialize();
    let result = await apiCall('save_config', 'POST', data);
    if (result.success) { showAlert('Configuration saved', 'success'); $('#settingsModal').modal('hide'); loadStatus(); }
    else showAlert('Save error', 'danger');
});

async function loadUsers() {
    let result = await apiCall('get_users');
    if (result.success) {
        ftpUsersList = result.data;
        if (ftpUsersList.length === 0) {
            $('#usersContainer').html('<div class="alert alert-info mb-0">No users. Add the first one.</div>');
        } else {
            let html = '<div class="d-flex flex-wrap">';
            ftpUsersList.forEach(u => {
                html += `<div class="user-badge" id="user-${escapeHtml(u)}"><i class="fas fa-user-circle fa-lg"></i><strong>${escapeHtml(u)}</strong><span class="user-badge-actions"><button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="deleteUser('${escapeHtml(u)}')"><i class="fas fa-trash-alt"></i></button></span></div>`;
            });
            html += '</div>';
            $('#usersContainer').html(html);
        }
        updateUserCheckboxes();
    }
}


async function loadSystemUsers() {
    let result = await apiCall('get_system_users');
    if (result.success) {
        systemUsersList = result.data;
        let html = '<option value="">Select user...</option>';
        systemUsersList.forEach(u => {
            html += `<option value="${escapeHtml(u)}">${escapeHtml(u)}</option>`;
        });
        $('#ftpUserSelect').html(html);
    }
}

function updateUserCheckboxes() {
    let html = '<div class="form-check mb-2"><input type="checkbox" id="select_all_users" class="form-check-input"><label class="form-check-label" for="select_all_users">Select all</label></div><hr>';
    ftpUsersList.forEach(u => {
        html += `<div class="form-check"><input type="checkbox" name="users[]" value="${escapeHtml(u)}" class="form-check-input user-checkbox" id="share_user_${escapeHtml(u)}"><label class="form-check-label" for="share_user_${escapeHtml(u)}">${escapeHtml(u)}</label></div>`;
    });
    if (ftpUsersList.length === 0) {
        html = '<span class="text-muted">No users with FTP access. Add users first.</span>';
    }
    $('#shareUsersList').html(html);
    
    $('#select_all_users').off('change').on('change', function() {
        $('.user-checkbox').prop('checked', $(this).prop('checked'));
    });
}

async function deleteUser(username) {
    if (!confirm(`Remove user ${username} from FTP access?`)) return;
    let result = await apiCall('delete_user', 'POST', { username: username });
    if (result.success) { showAlert(result.message, 'success'); loadUsers(); loadShares(); }
    else showAlert(result.message, 'danger');
}

$('#addUserForm').on('submit', async function(e) {
    e.preventDefault();
    let data = $(this).serialize();
    let result = await apiCall('create_user', 'POST', data);
    if (result.success) { showAlert(result.message, 'success'); $('#addFtpUserModal').modal('hide'); loadUsers(); loadShares(); loadSystemUsers(); }
    else showAlert(result.error || 'Error', 'danger');
});

async function loadShares() {
    let result = await apiCall('get_shares');
    if (result.success) {
        let shares = result.data;
        if (shares.length === 0) {
			$('#sharesTableBody').html('<tr><td colspan="4" class="text-center text-muted py-4"><i class="fas fa-folder-open fa-2x mb-2 d-block"></i>No FTP directories created</td></tr>');
        } else {
            let html = '';
            shares.forEach(s => {
                let usersHtml = '';
                if (s.users && s.users.length > 0) {
                    s.users.forEach(u => {
                        usersHtml += `<span class="share-badge"><i class="fas fa-user"></i> ${escapeHtml(u)}</span>`;
                    });
                } else {
                    usersHtml = '<span class="badge bg-info">All users</span>';
                }
                html += `<tr>
                    <td><strong>${escapeHtml(s.name)}</strong></td>
                    <td><code class="table-ftp-path">${escapeHtml(s.path)}</code></td>
                    <td>${usersHtml}</td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-primary" onclick="editShare('${escapeHtml(s.id)}', '${escapeHtml(s.name)}', '${escapeHtml(s.path)}', ${JSON.stringify(s.users).replace(/"/g, '&quot;')})"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-outline-danger" onclick="deleteShare('${escapeHtml(s.id)}')"><i class="fas fa-trash"></i></button>
                        </div>
                     </td>
                 </tr>`;
            });
            $('#sharesTableBody').html(html);
        }
    }
}

$('#addShareForm').on('submit', async function(e) {
    e.preventDefault();
    let data = $(this).serialize();
    let result = await apiCall('create_share', 'POST', data);
    if (result.success) { showAlert(result.message, 'success'); $('#addFtpShareModal').modal('hide'); loadShares(); loadStatus(); }
    else showAlert(result.error || 'Error', 'danger');
});

function editShare(id, name, path, users) {
    $('#edit_old_id').val(id);
    $('#edit_name').val(name);
    $('#edit_path').val(path);
    
    let html = '<label class="form-label">User Access</label><div class="user-select-list">';
    html += '<div class="form-check mb-2"><input type="checkbox" id="edit_select_all_users" class="form-check-input"><label class="form-check-label" for="edit_select_all_users">Select all</label></div><hr>';
    
    ftpUsersList.forEach(u => {
        let checked = users && users.includes(u) ? 'checked' : '';
        html += `<div class="form-check"><input type="checkbox" name="users[]" value="${escapeHtml(u)}" class="form-check-input edit-user-checkbox" id="edit_user_${escapeHtml(u)}" ${checked}><label class="form-check-label" for="edit_user_${escapeHtml(u)}">${escapeHtml(u)}</label></div>`;
    });
    
    if (ftpUsersList.length === 0) {
        html += '<span class="text-muted">No users with FTP access.</span>';
    }
    
    html += '</div>';
    $('#editUsersContainer').html(html);
    
    $('#edit_select_all_users').off('change').on('change', function() {
        $('.edit-user-checkbox').prop('checked', $(this).prop('checked'));
    });
    
    $('#editFtpShareModal').modal('show');
}

$('#editShareForm').on('submit', async function(e) {
    e.preventDefault();
    let data = $(this).serialize();
    let result = await apiCall('update_share', 'POST', data);
    if (result.success) { showAlert(result.message, 'success'); $('#editFtpShareModal').modal('hide'); loadShares(); loadStatus(); }
    else showAlert(result.error || 'Error', 'danger');
});

async function deleteShare(id) {
    if (!confirm('Удалить FTP каталог?')) return;
    let result = await apiCall('delete_share', 'POST', { id: id });
    if (result.success) { showAlert(result.message, 'success'); loadShares(); loadStatus(); }
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

async function loadLogs() {
    let result = await apiCall('get_logs');
    if (result.success) {
        let logs = result.data;
        let html = '';
        if (logs.length === 0) {
            html = '<div class="text-muted text-center py-3">No record in Log file</div>';
        } else {
            logs.forEach(log => {
                html += `<div class="log-line">${escapeHtml(log)}</div>`;
            });
        }
        $('#logsContainer').html(html);
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
        showAlert('Selected folder: ' + currentBrowsePath, 'success');
    }
}

function selectCurrentFolderEdit() {
    $('#edit_path').val(currentBrowsePathEdit);
    $('#browseFolderModalEdit').modal('hide');
}

function openFolderBrowser(targetInputId) {
    currentTargetInput = targetInputId;
    let startPath = $(`#${targetInputId}`).val() || '/';
    loadFolder(startPath, '#folderBrowser', '#folderBreadcrumb', '#currentPath');
    $('#browseFolderModal').modal('show');
}

function openFolderBrowserEdit() {
    let startPath = $('#edit_path').val() || '/';
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
    await Promise.all([loadStatus(), loadConfig(), loadUsers(), loadShares(), loadStorages(), loadLogs()]);
    showAlert('Data updated...', 'success');
}

setInterval(() => {
    loadLogs();
}, 30000);

$(document).ready(function() {
    refreshAllData();
    loadSystemUsers();
    setTimeout(function() { $('#applePreloader').fadeOut(500); }, 500);
});
</script>
<script src="js/loader.js"></script>
</body>
</html>