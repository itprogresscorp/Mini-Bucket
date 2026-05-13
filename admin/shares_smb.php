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
    <title>Mini-B - SMB</title>
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

.type-partition {
    background: #e7f1ff;
    color: #084298;
}

.type-lvm2 {
    background: #cfe2ff;
    color: #084298;
}

.type-raid {
    background: #f8d7da;
    color: #721c24;
}

.storage-mount {
    font-size: 11px;
    color: #6c757d;
    font-family: monospace;
    margin-bottom: 8px;
}

.storage-mount i {
    margin-right: 4px;
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

/* User Badges */
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

/* Tables */
.table-share th {
    background: #f8f9fa;
    border-bottom: 2px solid #e9ecef;
    font-size: 13px;
    font-weight: 600;
}

.table-share td {
    font-size: 13px;
    vertical-align: middle;
}

.table-share-path {
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

/* User Select Lists */
.user-select-list {
    max-height: 180px;
    overflow-y: auto;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 10px;
    background: white;
}

.user-select-list .form-check {
    margin-bottom: 8px;
}

.user-select-list .form-check:last-child {
    margin-bottom: 0;
}

/* Session & File Lists */
.session-list,
.file-list {
    max-height: 300px;
    overflow-y: auto;
}

.session-item,
.file-item {
    padding: 10px 12px;
    border-bottom: 1px solid #f0f0f0;
    font-size: 13px;
    transition: background 0.2s;
}

.session-item:hover,
.file-item:hover {
    background-color: #f8f9fa;
}

.session-item:last-child,
.file-item:last-child {
    border-bottom: none;
}

.session-user,
.file-user {
    font-weight: 600;
    min-width: 90px;
}

.session-ip {
    font-family: monospace;
    color: #6c757d;
    font-size: 12px;
}

.file-path {
    font-family: monospace;
    font-size: 11px;
    word-break: break-all;
    flex: 1;
    color: #495057;
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

/* Responsive Tables */
@media (max-width: 768px) {
    .table-responsive {
        font-size: 12px;
    }
    
    .table-share-path {
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
.text-truncate-custom {
    max-width: 200px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.cursor-pointer {
    cursor: pointer;
}

.gap-1 {
    gap: 4px;
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
            <span class="status-badge" id="smbRunningBadge">
                <i class="fas fa-play-circle"></i> SMB: Loading...
            </span>
            <span class="status-badge" id="smbEnabledBadge">
                <i class="fas fa-check-circle"></i> Auto: Loading...
            </span>
            <span class="status-badge" id="smbPidBadge" style="background:#e9ecef; color:#495057; display:none;">
                <i class="fas fa-microchip"></i> PID: <span id="smbPid">-</span>
            </span>
            <span class="status-badge" id="smbVersionBadge" style="background:#e9ecef; color:#495057;">
                <i class="fas fa-code-branch"></i> v<span id="smbVersion">-</span>
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
                        <h3><i class="fas fa-share-alt text-primary"></i> Shared Folders (SMB Shares)</h3>
                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addSmbModal">
                            <i class="fas fa-plus"></i> Create Share
                        </button>
                    </div>
                    <div class="widget-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover table-share mb-0">
                                <thead>
                                    <tr><th>Name</th><th>Path</th><th>Access</th><th>Permissions</th><th>Actions</th></tr>
                                </thead>
                                <tbody id="sharesTableBody">
                                    <tr><td colspan="5" class="text-center text-muted py-4"><div class="loading-spinner-sm"></div> Loading......</td></tr>
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
                        <h3><i class="fas fa-users text-primary"></i> SMB Users</h3>
                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addUserModal">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                    <div class="widget-body" id="usersContainer">
                        <div class="text-center py-3"><div class="loading-spinner-sm"></div> Loading...</div>
                    </div>
                </div>
                
                <div class="widget">
                    <div class="widget-header">
                        <h3><i class="fas fa-users-viewfinder text-primary"></i> Active Sessions</h3>
                        <i class="fas fa-sync-alt refresh-btn text-muted" onclick="loadSessions()" style="font-size:12px"></i>
                    </div>
                    <div class="widget-body p-0" id="sessionsContainer">
                        <div class="p-3 text-muted text-center"><div class="loading-spinner-sm"></div> Loading...</div>
                    </div>
                </div>
                
                <div class="widget">
                    <div class="widget-header">
                        <h3><i class="fas fa-file-alt text-primary"></i> Open Files</h3>
                        <i class="fas fa-sync-alt refresh-btn text-muted" onclick="loadSessions()" style="font-size:12px"></i>
                    </div>
                    <div class="widget-body p-0" id="filesContainer">
                        <div class="p-3 text-muted text-center"><div class="loading-spinner-sm"></div> Loading...</div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Модальные окна -->
<div class="modal fade" id="settingsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-sliders-h"></i> Global SMB Settings</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="globalConfigForm">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Workgroup</label><input type="text" name="workgroup" id="config_workgroup" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label">Server Name</label><input type="text" name="server_string" id="config_server_string" class="form-control"></div>
                        <div class="col-md-4"><label class="form-label">Security</label><select name="security" id="config_security" class="form-select"><option value="user">user</option><option value="share">share</option><option value="domain">domain</option></select></div>
                        <div class="col-md-4"><label class="form-label">Map to guest</label><select name="map_to_guest" id="config_map_to_guest" class="form-select"><option value="Bad User">Bad User</option><option value="Bad Password">Bad Password</option><option value="Never">Never</option></select></div>
                        <div class="col-md-4"><label class="form-label">Guest Account</label><input type="text" name="guest_account" id="config_guest_account" class="form-control"></div>
                        <div class="col-md-3"><label class="form-label">Log Level</label><input type="number" name="log_level" id="config_log_level" class="form-control" min="0" max="10"></div>
                        <div class="col-md-3"><label class="form-label">Max Log Size (KB)</label><input type="number" name="max_log_size" id="config_max_log_size" class="form-control"></div>
                        <div class="col-md-3"><label class="form-label">Deadtime (minutes)</label><input type="number" name="deadtime" id="config_deadtime" class="form-control"></div>
                        <div class="col-md-3"><label class="form-label">Use sendfile</label><select name="use_sendfile" id="config_use_sendfile" class="form-select"><option value="yes">Yes</option><option value="no">No</option></select></div>
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

<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-user-plus"></i> Add SMB User</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="addUserForm">
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Username</label><input type="text" name="username" class="form-control" pattern="[a-z_][a-z0-9_-]*" required><small class="text-muted">Only Latin letters, numbers, _ and -</small></div>
                    <div class="mb-3"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required minlength="4"></div>
                    <div class="mb-3"><label class="form-label">Repeat password</label><input type="password" name="password2" class="form-control" required></div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Create</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="changePasswordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning"><h5 class="modal-title"><i class="fas fa-key"></i> Change Password</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form id="changePasswordForm">
                <div class="modal-body">
                    <input type="hidden" name="username" id="changePasswordUsername">
                    <div class="mb-3"><label class="form-label">New Password</label><input type="password" name="password" class="form-control" required minlength="4"></div>
                    <div class="mb-3"><label class="form-label">Repeat Password</label><input type="password" name="password2" class="form-control" required></div>
                </div>
                <div class="modal-footer"><button type="submit" class="btn btn-warning">Change</button></div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="addSmbModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white"><h5 class="modal-title"><i class="fas fa-plus-circle"></i> Create SMB Share</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <form id="addShareForm">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-4"><label class="form-label">Share Name *</label><input type="text" name="share_name" class="form-control" required placeholder="documents"><small class="text-muted">Latin letters, numbers, _</small></div>
                        <div class="col-md-8"><label class="form-label">Folder Path *</label><div class="input-group"><input type="text" name="path" id="addSmbPath" class="form-control" readonly required><button type="button" class="btn btn-secondary" onclick="openFolderBrowser('addSmbPath')"><i class="fas fa-folder-open"></i> Browse</button></div></div>
                        <div class="col-md-12"><label class="form-label">Description</label><input type="text" name="comment" class="form-control" placeholder="Optional description"></div>
                        <div class="col-md-12">
                            <div class="form-check form-check-inline"><input type="checkbox" name="writable" class="form-check-input" id="smb_writable" checked><label class="form-check-label" for="smb_writable"><i class="fas fa-pen"></i> Allow write</label></div>
                            <div class="form-check form-check-inline"><input type="checkbox" name="public" class="form-check-input" id="smb_public"><label class="form-check-label" for="smb_public"><i class="fas fa-globe"></i> Public</label></div>
                        </div>
                        <div class="col-md-12" id="smbPermissionsModal">
                            <div class="border rounded p-3 bg-light mt-2">
                                <div class="row">
                                    <div class="col-md-6"><strong><i class="fas fa-eye"></i> Read Only:</strong><div class="user-select-list mt-2" id="modalReadList"></div></div>
                                    <div class="col-md-6"><strong><i class="fas fa-pen"></i> Read & Write:</strong><div class="user-select-list mt-2" id="modalWriteList"></div></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer"><button type="submit" class="btn btn-success">Create Share</button></div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editSmbModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white"><h5 class="modal-title"><i class="fas fa-edit"></i> Edit Share</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <form id="editShareForm">
                <div class="modal-body">
                    <input type="hidden" name="old_name" id="edit_old_name">
                    <div class="row g-3">
                        <div class="col-md-4"><label class="form-label">Share Name</label><input type="text" name="share_name" id="edit_share_name" class="form-control" required></div>
                        <div class="col-md-8"><label class="form-label">Path</label><div class="input-group"><input type="text" name="path" id="edit_path" class="form-control" readonly required><button type="button" class="btn btn-secondary" onclick="openFolderBrowser('edit_path')"><i class="fas fa-folder-open"></i> Browse</button></div></div>
                        <div class="col-md-12"><label class="form-label">Description</label><input type="text" name="comment" id="edit_comment" class="form-control"></div>
                        <div class="col-md-12">
                            <div class="form-check form-check-inline"><input type="checkbox" name="writable" class="form-check-input" id="edit_writable"><label class="form-check-label" for="edit_writable"><i class="fas fa-pen"></i> Allow Write</label></div>
                            <div class="form-check form-check-inline"><input type="checkbox" name="public" class="form-check-input" id="edit_public"><label class="form-check-label" for="edit_public"><i class="fas fa-globe"></i> Public</label></div>
                        </div>
                        <div class="col-md-12" id="editPermissionsContainer"></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="submit" class="btn btn-primary">Save</button></div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="browseFolderModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white"><h5 class="modal-title"><i class="fas fa-folder-open"></i> Select Folder</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <nav aria-label="breadcrumb"><ol class="breadcrumb" id="folderBreadcrumb"></ol></nav>
                <div class="input-group mb-3"><input type="text" id="currentPath" class="form-control" readonly><button type="button" class="btn btn-success" onclick="showCreateFolderDialog()"><i class="fas fa-folder-plus"></i> Create Folder</button></div>
                <div class="folder-browser" id="folderBrowser"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-primary" onclick="selectCurrentFolder()">Select</button></div>
        </div>
    </div>
</div>

<div class="modal fade" id="createFolderDialog" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content"><div class="modal-header bg-success text-white"><h5 class="modal-title"><i class="fas fa-folder-plus"></i> Создать папку</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="mb-3"><label class="form-label">Путь:</label> <code id="createFolderPath"></code></div><div class="mb-3"><label class="form-label">Имя папки</label><input type="text" id="newFolderName" class="form-control" placeholder="Новая папка"></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button><button type="button" class="btn btn-success" onclick="createNewFolder()">Создать</button></div></div></div>
</div>

<script src="lib/jquery-3.6.0-master/dist/jquery.min.js"></script>
<script src="lib/bootstrap-5.3.8-dist/js/bootstrap.bundle.min.js"></script>
<script>
const url = "<?php echo $current_host_id == 1 ? '/api/' : rtrim($host_url, '/') . '/'; ?>";
let currentTargetInput = null;
let currentBrowsePath = '/';
let smbUsersList = [];

function showAlert(message, type = 'success') {
    const alertHtml = `<div class="alert alert-${type} alert-dismissible fade show" role="alert"><i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'}"></i> ${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
    $('#alertContainer').append(alertHtml);
    setTimeout(() => $('.alert').fadeOut(500, function() { $(this).remove(); }), 5000);
}

function escapeHtml(str) { if (!str) return ''; return str.replace(/[&<>]/g, function(m) { return { '&':'&amp;', '<':'&lt;', '>':'&gt;' }[m]; }); }

async function apiCall(action, method = 'GET', data = null) {
    let fullUrl = `${url}shares_smb_api.php?action=${action}`;
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
        $('#smbRunningBadge').html(`<i class="fas fa-${status.running ? 'play-circle' : 'stop-circle'}"></i> SMB: ${status.running ? 'Running' : 'Stopped'}`);
        $('#smbRunningBadge').removeClass('status-running status-stopped').addClass(status.running ? 'status-running' : 'status-stopped');
        $('#smbEnabledBadge').html(`<i class="fas fa-${status.enabled ? 'check-circle' : 'times-circle'}"></i> Auto: ${status.enabled ? 'On' : 'Off'}`);
        $('#smbEnabledBadge').removeClass('status-enabled status-disabled').addClass(status.enabled ? 'status-enabled' : 'status-disabled');
        if (status.pid) { $('#smbPidBadge').show(); $('#smbPid').text(status.pid); } else { $('#smbPidBadge').hide(); }
        $('#smbVersion').text(status.version || 'unknown');
        
        let toggleBtn = $('#toggleAutostartBtn');
        toggleBtn.html(`<i class="fas fa-${status.enabled ? 'pause' : 'play'} text-info"></i> ${status.enabled ? 'Disable autostart' : 'Enable autostart'}`);
    }
}

async function serviceAction(action) {
    let result = await apiCall('service_action', 'POST', { service_action: action });
    if (result.success) { showAlert(`Служба ${action}ed`, 'success'); loadStatus(); loadSessions(); }
    else showAlert(`Ошибка при ${action}`, 'danger');
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
        $('#config_workgroup').val(cfg.workgroup);
        $('#config_server_string').val(cfg.server_string);
        $('#config_security').val(cfg.security);
        $('#config_map_to_guest').val(cfg.map_to_guest);
        $('#config_guest_account').val(cfg.guest_account);
        $('#config_log_level').val(cfg.log_level);
        $('#config_max_log_size').val(cfg.max_log_size);
        $('#config_deadtime').val(cfg.deadtime);
        $('#config_use_sendfile').val(cfg.use_sendfile);
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
        smbUsersList = result.data;
        if (smbUsersList.length === 0) {
            $('#usersContainer').html('<div class="alert alert-info mb-0">No users. Create the first one.</div>');
        } else {
            let html = '<div class="d-flex flex-wrap">';
            smbUsersList.forEach(u => {
                html += `<div class="user-badge" id="user-${escapeHtml(u)}"><i class="fas fa-user-circle fa-lg"></i><strong>${escapeHtml(u)}</strong><span class="user-badge-actions"><button type="button" class="btn btn-sm btn-link text-primary p-0" onclick="openChangePassword('${escapeHtml(u)}')"><i class="fas fa-key"></i></button><button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="deleteUser('${escapeHtml(u)}')"><i class="fas fa-trash-alt"></i></button></span></div>`;
            });
            html += '</div>';
            $('#usersContainer').html(html);
        }
        updateUserCheckboxes();
    }
}

function updateUserCheckboxes() {
    let readHtml = '', writeHtml = '';
    smbUsersList.forEach(u => {
        readHtml += `<div class="form-check"><input type="checkbox" name="read_list[]" value="${escapeHtml(u)}" class="form-check-input" id="modal_read_${escapeHtml(u)}"><label class="form-check-label" for="modal_read_${escapeHtml(u)}">${escapeHtml(u)}</label></div>`;
        writeHtml += `<div class="form-check"><input type="checkbox" name="write_list[]" value="${escapeHtml(u)}" class="form-check-input" id="modal_write_${escapeHtml(u)}"><label class="form-check-label" for="modal_write_${escapeHtml(u)}">${escapeHtml(u)}</label></div>`;
    });
    if (smbUsersList.length === 0) {
        readHtml = '<span class="text-muted">No users. Create users first.</span>';
        writeHtml = '<span class="text-muted">No users. Create users first.</span>';
    }
    $('#modalReadList').html(readHtml);
    $('#modalWriteList').html(writeHtml);
}

$('#addUserForm').on('submit', async function(e) {
    e.preventDefault();
    let data = $(this).serialize();
    let result = await apiCall('create_user', 'POST', data);
    if (result.success) { showAlert(result.message, 'success'); $('#addUserModal').modal('hide'); loadUsers(); }
    else showAlert(result.error || 'Error', 'danger');
});

function openChangePassword(username) {
    $('#changePasswordUsername').val(username);
    $('#changePasswordModal').modal('show');
}

$('#changePasswordForm').on('submit', async function(e) {
    e.preventDefault();
    let data = $(this).serialize();
    let result = await apiCall('change_password', 'POST', data);
    if (result.success) { showAlert(result.message, 'success'); $('#changePasswordModal').modal('hide'); }
    else showAlert(result.error || 'Error', 'danger');
});

async function deleteUser(username) {
    if (!confirm(`Delete user ${username}?`)) return;
    let result = await apiCall('delete_user', 'POST', { username: username });
    if (result.success) { showAlert(result.message, 'success'); loadUsers(); loadShares(); }
    else showAlert(result.message, 'danger');
}

async function loadShares() {
    let result = await apiCall('get_shares');
    if (result.success) {
        let shares = result.data;
        if (shares.length === 0) {
            $('#sharesTableBody').html('<tr><td colspan="5" class="text-center text-muted py-4"><i class="fas fa-folder-open fa-2x mb-2 d-block"></i>No shares created</td></tr>');
        } else {
            let html = '';
            shares.forEach(s => {
                let accessBadge = s.public ? '<span class="badge bg-info"><i class="fas fa-globe"></i> Public</span>' : (s.writable ? '<span class="badge bg-success"><i class="fas fa-pen"></i> Read/Write</span>' : '<span class="badge bg-warning"><i class="fas fa-eye"></i> Read Only</span>');
                let rightsBadge = '';
                if (s.read_list && s.read_list.length) rightsBadge += `<span class="badge bg-secondary me-1" title="Read only: ${s.read_list.join(', ')}"><i class="fas fa-eye"></i> ${s.read_list.length}</span>`;
                if (s.write_list && s.write_list.length) rightsBadge += `<span class="badge bg-secondary" title="Write: ${s.write_list.join(', ')}"><i class="fas fa-pen"></i> ${s.write_list.length}</span>`;
                html += `<tr><td><strong>${escapeHtml(s.name)}</strong></td><td><code class="table-share-path">${escapeHtml(s.path)}</code></td><td>${accessBadge}</td><td>${rightsBadge || '-'}</td><td><div class="btn-group btn-group-sm"><button class="btn btn-outline-primary" onclick="editShare('${escapeHtml(s.name)}')"><i class="fas fa-edit"></i></button><button class="btn btn-outline-danger" onclick="deleteShare('${escapeHtml(s.name)}')"><i class="fas fa-trash"></i></button></div></td></tr>`;
            });
            $('#sharesTableBody').html(html);
        }
    }
}

$('#addShareForm').on('submit', async function(e) {
    e.preventDefault();
    let data = $(this).serialize();
    let result = await apiCall('create_share', 'POST', data);
    if (result.success) { showAlert(result.message, 'success'); $('#addSmbModal').modal('hide'); loadShares(); loadStatus(); }
    else showAlert(result.error || 'Error', 'danger');
});

function editShare(name) {
    apiCall('get_shares').then(result => {
        if (result.success) {
            let share = result.data.find(s => s.name === name);
            if (share) {
                $('#edit_old_name').val(share.name);
                $('#edit_share_name').val(share.name);
                $('#edit_path').val(share.path);
                $('#edit_comment').val(share.comment || '');
                $('#edit_writable').prop('checked', share.writable);
                $('#edit_public').prop('checked', share.public);
                
                let html = '<div class="border rounded p-3 bg-light mt-2"><div class="row"><div class="col-md-6"><strong><i class="fas fa-eye"></i> Read Only:</strong><div class="user-select-list mt-2">';
                smbUsersList.forEach(u => {
                    html += `<div class="form-check"><input type="checkbox" name="read_list[]" value="${escapeHtml(u)}" class="form-check-input" id="edit_read_${escapeHtml(u)}" ${share.read_list && share.read_list.includes(u) ? 'checked' : ''}><label class="form-check-label" for="edit_read_${escapeHtml(u)}">${escapeHtml(u)}</label></div>`;
                });
                if (smbUsersList.length === 0) html += '<span class="text-muted">No users</span>';
                html += '</div></div><div class="col-md-6"><strong><i class="fas fa-pen"></i> Read & Write:</strong><div class="user-select-list mt-2">';
                smbUsersList.forEach(u => {
                    html += `<div class="form-check"><input type="checkbox" name="write_list[]" value="${escapeHtml(u)}" class="form-check-input" id="edit_write_${escapeHtml(u)}" ${share.write_list && share.write_list.includes(u) ? 'checked' : ''}><label class="form-check-label" for="edit_write_${escapeHtml(u)}">${escapeHtml(u)}</label></div>`;
                });
                if (smbUsersList.length === 0) html += '<span class="text-muted">No users</span>';
                html += '</div></div></div></div>';
                $('#editPermissionsContainer').html(html);
                $('#editSmbModal').modal('show');
            }
        }
    });
}

$('#editShareForm').on('submit', async function(e) {
    e.preventDefault();
    let data = $(this).serialize();
    let result = await apiCall('update_share', 'POST', data);
    if (result.success) { showAlert(result.message, 'success'); $('#editSmbModal').modal('hide'); loadShares(); loadStatus(); }
    else showAlert(result.error || 'Error', 'danger');
});

async function deleteShare(name) {
    if (!confirm(`Удалить шару "${name}"?`)) return;
    let result = await apiCall('delete_share', 'GET', { name: name });
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

async function loadSessions() {
    let result = await apiCall('get_sessions');
    if (result.success) {
        let sessionsHtml = '<div class="session-list">';
        if (result.sessions.length === 0) {
            sessionsHtml += '<div class="p-3 text-muted text-center">No active sessions</div>';
        } else {
            let usersWithSessions = [...new Set(result.sessions.map(s => s.user))];
            
            usersWithSessions.forEach(user => {
                let userSessions = result.sessions.filter(s => s.user === user);
                sessionsHtml += `<div class="session-item border-bottom mb-2 pb-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <div><i class="fas fa-user-circle"></i> <strong>${escapeHtml(user)}</strong> (${userSessions.length} sessions)</div>
                        <button class="btn btn-sm btn-danger" onclick="killUserSessions('${escapeHtml(user)}')" title="Close all user sessions">
                            <i class="fas fa-power-off"></i> Close all
                        </button>
                    </div>`;
                
                userSessions.forEach(s => {
                    sessionsHtml += `<div class="ps-3 pt-1 d-flex justify-content-between align-items-center">
                        <div><i class="fas fa-laptop"></i> <span class="session-ip">${escapeHtml(s.ip)}</span> <small class="text-muted">PID: ${s.pid}</small></div>
                        <button class="btn btn-sm btn-outline-danger" onclick="killSession('${s.pid}')" title="End session">
                            <i class="fas fa-times-circle"></i>
                        </button>
                    </div>`;
                });
                sessionsHtml += `</div>`;
            });
        }
        sessionsHtml += '</div>';
        $('#sessionsContainer').html(sessionsHtml);
        
        let filesHtml = '<div class="file-list">';
        if (result.files.length === 0) {
            filesHtml += '<div class="p-3 text-muted text-center">No open files</div>';
        } else {
            let usersWithFiles = [...new Set(result.files.map(f => f.user))];
            
            usersWithFiles.forEach(user => {
                let userFiles = result.files.filter(f => f.user === user);
                filesHtml += `<div class="file-item border-bottom mb-2 pb-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <div><i class="fas fa-user-circle"></i> <strong>${escapeHtml(user)}</strong> (${userFiles.length} files)</div>
                        <button class="btn btn-sm btn-warning" onclick="closeUserFiles('${escapeHtml(user)}')" title="Close all user files">
                            <i class="fas fa-file-alt"></i> Close all
                        </button>
                    </div>`;
                
                userFiles.forEach(f => {
                    filesHtml += `<div class="ps-3 pt-1 d-flex justify-content-between align-items-center">
                        <div><i class="fas fa-file"></i> <span class="file-path" title="${escapeHtml(f.full_path)}">${escapeHtml(f.name)}</span> <small class="text-muted">PID: ${f.pid}</small></div>
                        <button class="btn btn-sm btn-outline-warning" onclick="closeFile('${f.pid}', '${escapeHtml(f.full_path).replace(/'/g, "\\'")}')" title="Close file">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>`;
                });
                filesHtml += `</div>`;
            });
        }
        filesHtml += '</div>';
        $('#filesContainer').html(filesHtml);
    }
}

async function killSession(pid) {
    if (!confirm(`End session with PID ${pid}? User will be disconnected.`)) return;
    
    let result = await apiCall('kill_session', 'POST', { pid: pid });
    if (result.success) {
        showAlert(result.message, 'success');
        loadSessions();
    } else {
        showAlert(result.message || 'Error ending session', 'danger');
    }
}

async function killUserSessions(username) {
    if (!confirm(`Close ALL sessions for user ${username}? They will be disconnected from all shares.`)) return;
    
    let result = await apiCall('kill_user_sessions', 'POST', { username: username });
    if (result.success) {
        showAlert(result.message, 'success');
        loadSessions();
    } else {
        showAlert(result.message || 'Error closing sessions', 'danger');
    }
}

async function closeFile(pid, filePath) {
    if (!confirm(`Close file?\n${filePath}`)) return;
    
    let result = await apiCall('close_file', 'POST', { pid: pid, file_path: filePath });
    if (result.success) {
        showAlert(result.message, 'success');
        loadSessions();
    } else {
        showAlert(result.message || 'Error closing file', 'danger');
    }
}

async function closeUserFiles(username) {
    if (!confirm(`Close ALL open files for user ${username}?`)) return;
    
    let result = await apiCall('close_user_files', 'POST', { username: username });
    if (result.success) {
        showAlert(result.message, 'success');
        loadSessions();
    } else {
        showAlert(result.message || 'Error closing files', 'danger');
    }
}

async function loadFolder(path) {
    let result = await apiCall('browse', 'GET', { path: path });
    if (result.success) {
        currentBrowsePath = result.path;
        $('#currentPath').val(result.path);
        
        let parts = result.path.split('/').filter(p => p);
        let html = '<li class="breadcrumb-item"><a onclick="loadFolder(\'/\')">/</a></li>';
        let cp = '';
        parts.forEach(p => { cp += '/' + p; html += `<li class="breadcrumb-item"><a onclick="loadFolder('${cp}')">${escapeHtml(p)}</a></li>`; });
        $('#folderBreadcrumb').html(html);
        
        let listHtml = '<div class="list-group">';
        if (result.path !== '/') listHtml += `<div class="list-group-item list-group-item-action folder-item" onclick="loadFolder('${result.parent}')"><i class="fas fa-level-up-alt"></i> ..</div>`;
        result.items.forEach(item => {
            listHtml += `<div class="list-group-item list-group-item-action folder-item" onclick="selectDirectory('${item.path}')"><i class="fas fa-folder"></i> ${escapeHtml(item.name)}</div>`;
        });
        if (result.items.length === 0 && result.path !== '/') listHtml += '<div class="list-group-item text-muted">Empty folder</div>';
        listHtml += '</div>';
        $('#folderBrowser').html(listHtml);
    } else {
        showAlert('Error loading folders', 'danger');
    }
}

function selectDirectory(path) {
    $('#currentPath').val(path);
    loadFolder(path);
}

function selectCurrentFolder() {
    if (currentTargetInput) {
        $(`#${currentTargetInput}`).val(currentBrowsePath);
        $('#browseFolderModal').modal('hide');
        showAlert('Папка выбрана: ' + currentBrowsePath, 'success');
    }
}

function openFolderBrowser(targetInputId) {
    currentTargetInput = targetInputId;
    let startPath = $(`#${targetInputId}`).val() || '/';
    loadFolder(startPath);
    $('#browseFolderModal').modal('show');
}

function showCreateFolderDialog() {
    $('#createFolderPath').text(currentBrowsePath);
    $('#newFolderName').val('');
    $('#createFolderDialog').modal('show');
}

async function createNewFolder() {
    let path = $('#createFolderPath').text();
    let name = $('#newFolderName').val();
    if (!name) { showAlert('Enter folder name', 'danger'); return; }
    let result = await apiCall('create_folder', 'POST', { path: path, name: name });
    if (result.success) {
        $('#createFolderDialog').modal('hide');
        loadFolder(currentBrowsePath);
        showAlert('Folder "' + name + '" created', 'success');
    } else {
        showAlert(result.error, 'danger');
    }
}

$('#smb_public, #edit_public').change(function() {
    let isPublic = $(this).prop('checked');
    let isEdit = this.id === 'edit_public';
    if (isEdit) {
        $('#edit_writable').prop('disabled', isPublic);
        if (isPublic) $('#edit_writable').prop('checked', false);
        $('#editPermissionsContainer').toggle(!isPublic);
    } else {
        $('#smb_writable').prop('disabled', isPublic);
        if (isPublic) $('#smb_writable').prop('checked', false);
        $('#smbPermissionsModal').toggle(!isPublic);
    }
});

$('#addSmbModal').on('show.bs.modal', function() {
    $('#addSmbPath').val('');
    $('#smb_writable').prop('checked', true).prop('disabled', false);
    $('#smb_public').prop('checked', false);
    $('#smbPermissionsModal').show();
    updateUserCheckboxes();
});

async function refreshAllData() {
    await Promise.all([loadStatus(), loadConfig(), loadUsers(), loadShares(), loadStorages(), loadSessions()]);
    showAlert('Data updated', 'success');
}

setInterval(() => {
    loadSessions();
    loadStorages();
}, 10000);

$(document).ready(function() {
    refreshAllData();
    setTimeout(function() { $('#applePreloader').fadeOut(500); }, 500);
});
</script>
<script src="js/loader.js"></script>
</body>
</html>