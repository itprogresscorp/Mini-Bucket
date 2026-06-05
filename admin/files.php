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
    <title>Mini-b Commander - File Manager</title>
    <link href="lib/bootstrap-5.3.8-dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="lib/fontawesome-free-6.7.2-web/css/all.min.css">
    <link rel="stylesheet" href="style.css">
	<script src="js/hosts_load.js"></script>
	<script src="js/crt_checker.js"></script>
	<script>
	window.apiConfig = <?php echo json_encode($js_config); ?>;
	console.log('API Config loaded:', window.apiConfig);
	</script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #f5f5f7; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, sans-serif; }
        
        .app-container { display: flex; min-height: 100vh; }
        
        .content-header { margin-bottom: 32px; }
        .content-header h1 { font-size: 32px; font-weight: 600; }
        
        .dual-container { display: flex; gap: 20px; }
        .panel { flex: 1; background: white; border-radius: 20px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .panel-header { padding: 16px 20px; background: #f9f9fb; border-bottom: 1px solid #e5e5ea; }
        .drive-badge { background: rgba(0,122,255,0.1); padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 500; color: #007aff; }
        .drive-select { background: white; border: 1px solid #d1d1d6; border-radius: 8px; padding: 6px 12px; font-size: 12px; font-weight: 500; width: 100%; }
        .search-bar { padding: 12px 16px; background: #f9f9fb; border-bottom: 1px solid #e5e5ea; }
        .search-input { border-radius: 10px; border: 1px solid #d1d1d6; padding: 8px 12px; font-size: 14px; width: 100%; }
        .panel-toolbar { padding: 12px 16px; background: white; border-bottom: 1px solid #e5e5ea; display: flex; gap: 8px; flex-wrap: wrap; }
        .file-list { height: calc(100vh - 420px); overflow-y: auto; }
        .file-row { display: flex; align-items: center; padding: 8px 16px; border-bottom: 1px solid #f0f0f0; cursor: pointer; transition: background 0.15s; }
        .file-row:hover { background: #f5f5f7; }
        .file-row.selected { background: #e3f2fd; }
        .checkbox-col { width: 28px; margin-right: 8px; }
        .checkbox-col input { width: 18px; height: 18px; cursor: pointer; accent-color: #007aff; }
        .file-icon { width: 32px; text-align: center; margin-right: 12px; color: #007aff; }
        .file-name { flex: 1; font-size: 14px; word-break: break-word; }
        .file-name a { text-decoration: none; color: #1d1d1f; cursor: pointer; }
        .file-name a:hover { color: #007aff; text-decoration: underline; }
        .file-size { width: 90px; text-align: right; font-size: 12px; color: #8e8e93; margin-right: 16px; }
        .file-perms { width: 100px; font-family: monospace; font-size: 11px; color: #8e8e93; margin-right: 16px; }
        .file-owner { width: 120px; font-size: 11px; color: #8e8e93; margin-right: 16px; overflow: hidden; text-overflow: ellipsis; }
        .file-date { width: 150px; font-size: 11px; color: #8e8e93; }
        .file-actions { width: 60px; text-align: right; }
        .file-actions .btn-icon { background: none; border: none; padding: 4px 6px; border-radius: 6px; cursor: pointer; color: #8e8e93; display: inline-block; text-decoration: none; }
        .file-actions .btn-icon:hover { background: #e5e5ea; color: #007aff; }
        .parent-folder { background: #f9f9fb; font-weight: 500; }
        .search-result-info { font-size: 12px; color: #007aff; margin-top: 8px; }
        
        .btn-apple { padding: 6px 14px; border-radius: 8px; font-size: 13px; font-weight: 500; border: none; transition: all 0.2s; cursor: pointer; }
        .btn-apple-primary { background: #007aff; color: white; }
        .btn-apple-primary:hover { background: #0051d5; }
        .btn-apple-secondary { background: #e5e5ea; color: #1d1d1f; }
        .btn-apple-secondary:hover { background: #d1d1d6; }
        .btn-apple-danger { background: #ff3b30; color: white; }
        .btn-apple-danger:hover { background: #d70015; }
        .btn-apple-success { background: #34c759; color: white; }
        .btn-apple-success:hover { background: #248a3d; }
        .btn-apple-warning { background: #ff9500; color: white; }
        .btn-apple-warning:hover { background: #cc7a00; }
        
        .progress-widget {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: rgba(30,30,35,0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 20px;
            min-width: 320px;
            z-index: 10000;
            box-shadow: 0 8px 32px rgba(0,0,0,0.3);
            border: 1px solid rgba(255,255,255,0.1);
            display: none;
        }
        .progress-widget .widget-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            color: white;
            font-size: 14px;
            font-weight: 500;
        }
        .progress-widget .widget-actions button {
            background: none;
            border: none;
            color: rgba(255,255,255,0.7);
            cursor: pointer;
            font-size: 16px;
            margin-left: 10px;
        }
        .progress-widget .widget-actions button:hover { color: white; }
        .progress-ring-container {
            position: relative;
            width: 80px;
            height: 80px;
            margin: 0 auto 15px;
        }
        .progress-ring-circle-bg { stroke: rgba(255,255,255,0.2); }
        .progress-ring-circle {
            stroke: #34c759;
            transition: stroke-dashoffset 0.1s linear;
            transform: rotate(-90deg);
            transform-origin: 50% 50%;
        }
        .progress-text {
            position: absolute;
            top: 55%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
            font-size: 18px;
            font-weight: 600;
        }
        .progress-status {
            text-align: center;
            color: rgba(255,255,255,0.8);
            font-size: 12px;
            margin-top: 10px;
        }
        .progress-current-file {
            text-align: center;
            color: rgba(255,255,255,0.6);
            font-size: 11px;
            margin-top: 8px;
            word-break: break-all;
        }
        
        .modal-content { border-radius: 20px; border: none; box-shadow: 0 24px 48px rgba(0,0,0,0.2); }
        .alert-apple { border-radius: 14px; border: none; margin-bottom: 20px; }
        .clipboard-indicator {
            position: fixed;
            bottom: 24px;
            left: 280px;
            background: rgba(30,30,35,0.95);
            backdrop-filter: blur(20px);
            padding: 12px 20px;
            border-radius: 30px;
            color: white;
            font-size: 13px;
            z-index: 1000;
        }
        .clipboard-indicator a { color: white; text-decoration: none; cursor: pointer; }
        .clipboard-indicator a:hover { text-decoration: underline; }
        .drop-zone { border: 2px dashed #007aff; border-radius: 12px; padding: 30px; text-align: center; margin: 10px; background: rgba(0,122,255,0.05); transition: all 0.3s; cursor: pointer; }
        .drop-zone.drag-over { background: rgba(0,122,255,0.15); border-color: #0051d5; }
        .acl-row { display: flex; gap: 10px; margin-bottom: 10px; align-items: center; }
        
        .spinner-container { text-align: center; padding: 20px; }
        .spinner {
            width: 40px;
            height: 40px;
            border: 3px solid rgba(0,0,0,0.1);
            border-radius: 50%;
            border-top-color: #007aff;
            animation: spin 1s ease-in-out infinite;
            margin: 0 auto;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255,255,255,0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
            border-radius: 20px;
        }
        
        @media (max-width: 768px) {
            .sidebar { width: 70px; }
            .sidebar-header span, .nav-item span, .user-info span { display: none; }
            .main-content { margin-left: 70px; padding: 16px; }
            .nav-item { justify-content: center; }
            .nav-item i { margin-right: 0; }
            .dual-container { flex-direction: column; }
        }
    </style>
</head>
<body>

<div class="top-bar">
    <div class="top-bar-left">
        <h1><i class="fas fa-bucket"></i> Mini-B</h1>
    </div>
    <div class="top-bar-right">
        <i class="fas fa-folder"></i> File Manager
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
        <div id="alertContainer"></div>
        <div id="clipboardIndicator" style="display:none" class="clipboard-indicator"></div>
        
        <div class="dual-container">
            <!-- LEFT PANEL -->
            <div class="panel" id="leftPanel">
                <div class="panel-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div><i class="fas fa-folder-open"></i> <span id="leftPathLink"><a href="#" onclick="void(0)">/</a></span></div>
                        <span class="drive-badge"><i class="fas fa-hdd"></i> <span id="leftDriveLabel">/mnt/data</span></span>
                    </div>
                    <div class="mt-2">
                        <select class="drive-select" id="leftDriveSelect">
                            <option value="">Loading drives...</option>
                        </select>
                    </div>
                </div>
                <div class="search-bar">
                    <div class="d-flex gap-2 flex-wrap">
                        <input type="text" id="leftSearchInput" class="search-input flex-grow-1" placeholder="Search (supports *.ext wildcards)">
                        <label class="btn btn-sm btn-outline-secondary"><input type="checkbox" id="leftRecursiveCheckbox"> Recursive</label>
                        <button type="button" id="leftSearchBtn" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Search</button>
                        <button type="button" id="leftClearSearchBtn" class="btn btn-secondary btn-sm" style="display:none"><i class="fas fa-times"></i> Clear</button>
                    </div>
                    <div id="leftSearchInfo" class="search-result-info" style="display:none"></div>
                </div>
                <div class="panel-toolbar">
                    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                        <button type="button" class="btn-apple btn-apple-secondary" onclick="fileManager.selectAll('left')"><i class="fas fa-check-double"></i> All</button>
                        <button type="button" class="btn-apple btn-apple-primary" onclick="fileManager.copyToOther('left')"><i class="fas fa-copy"></i> Copy →</button>
                        <button type="button" class="btn-apple btn-apple-warning" onclick="fileManager.moveToOther('left')"><i class="fas fa-arrow-right"></i> Move →</button>
                        <button type="button" class="btn-apple btn-apple-secondary" onclick="fileManager.copyToClipboard('left')"><i class="fas fa-copy"></i> Copy</button>
                        <button type="button" class="btn-apple btn-apple-secondary" onclick="fileManager.cutToClipboard('left')"><i class="fas fa-cut"></i> Cut</button>
                        <button type="button" class="btn-apple btn-apple-success" onclick="fileManager.pasteFromClipboard('left')"><i class="fas fa-paste"></i> Paste</button>
                        <button type="button" class="btn-apple btn-apple-danger" onclick="fileManager.deleteSelected('left')"><i class="fas fa-trash"></i> Delete</button>
                        <button type="button" class="btn-apple btn-apple-secondary" data-bs-toggle="modal" data-bs-target="#uploadModal" onclick="fileManager.setActivePanel('left')"><i class="fas fa-upload"></i></button>
                        <button type="button" class="btn-apple btn-apple-secondary" data-bs-toggle="modal" data-bs-target="#mkdirModal" onclick="fileManager.setActivePanel('left')"><i class="fas fa-folder-plus"></i></button>
                        <button type="button" class="btn-apple btn-apple-secondary" data-bs-toggle="modal" data-bs-target="#archiveModal"><i class="fas fa-archive"></i></button>
                        <button type="button" class="btn-apple btn-apple-secondary" data-bs-toggle="modal" data-bs-target="#permissionsModal"><i class="fas fa-lock"></i></button>
                        <button type="button" class="btn-apple btn-apple-secondary" data-bs-toggle="modal" data-bs-target="#aclModal"><i class="fas fa-users"></i> ACL</button>
                    </div>
                </div>
                <div class="file-list" id="leftFileList">
                    <div class="spinner-container"><div class="spinner"></div></div>
                </div>
            </div>
            
            <!-- RIGHT PANEL -->
            <div class="panel" id="rightPanel">
                <div class="panel-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div><i class="fas fa-folder-open"></i> <span id="rightPathLink"><a href="#" onclick="void(0)">/</a></span></div>
                        <span class="drive-badge"><i class="fas fa-hdd"></i> <span id="rightDriveLabel">/mnt/data</span></span>
                    </div>
                    <div class="mt-2">
                        <select class="drive-select" id="rightDriveSelect">
                            <option value="">Loading drives...</option>
                        </select>
                    </div>
                </div>
                <div class="search-bar">
                    <div class="d-flex gap-2 flex-wrap">
                        <input type="text" id="rightSearchInput" class="search-input flex-grow-1" placeholder="Search (supports *.ext wildcards)">
                        <label class="btn btn-sm btn-outline-secondary"><input type="checkbox" id="rightRecursiveCheckbox"> Recursive</label>
                        <button type="button" id="rightSearchBtn" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Search</button>
                        <button type="button" id="rightClearSearchBtn" class="btn btn-secondary btn-sm" style="display:none"><i class="fas fa-times"></i> Clear</button>
                    </div>
                    <div id="rightSearchInfo" class="search-result-info" style="display:none"></div>
                </div>
                <div class="panel-toolbar">
                    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                        <button type="button" class="btn-apple btn-apple-secondary" onclick="fileManager.selectAll('right')"><i class="fas fa-check-double"></i> All</button>
                        <button type="button" class="btn-apple btn-apple-primary" onclick="fileManager.copyToOther('right')"><i class="fas fa-copy"></i> ← Copy</button>
                        <button type="button" class="btn-apple btn-apple-warning" onclick="fileManager.moveToOther('right')"><i class="fas fa-arrow-left"></i> ← Move</button>
                        <button type="button" class="btn-apple btn-apple-secondary" onclick="fileManager.copyToClipboard('right')"><i class="fas fa-copy"></i> Copy</button>
                        <button type="button" class="btn-apple btn-apple-secondary" onclick="fileManager.cutToClipboard('right')"><i class="fas fa-cut"></i> Cut</button>
                        <button type="button" class="btn-apple btn-apple-success" onclick="fileManager.pasteFromClipboard('right')"><i class="fas fa-paste"></i> Paste</button>
                        <button type="button" class="btn-apple btn-apple-danger" onclick="fileManager.deleteSelected('right')"><i class="fas fa-trash"></i> Delete</button>
                        <button type="button" class="btn-apple btn-apple-secondary" data-bs-toggle="modal" data-bs-target="#uploadModal" onclick="fileManager.setActivePanel('right')"><i class="fas fa-upload"></i></button>
                        <button type="button" class="btn-apple btn-apple-secondary" data-bs-toggle="modal" data-bs-target="#mkdirModal" onclick="fileManager.setActivePanel('right')"><i class="fas fa-folder-plus"></i></button>
                        <button type="button" class="btn-apple btn-apple-secondary" data-bs-toggle="modal" data-bs-target="#archiveModal"><i class="fas fa-archive"></i></button>
                        <button type="button" class="btn-apple btn-apple-secondary" data-bs-toggle="modal" data-bs-target="#permissionsModal"><i class="fas fa-lock"></i></button>
                        <button type="button" class="btn-apple btn-apple-secondary" data-bs-toggle="modal" data-bs-target="#aclModal"><i class="fas fa-users"></i> ACL</button>
                    </div>
                </div>
                <div class="file-list" id="rightFileList">
                    <div class="spinner-container"><div class="spinner"></div></div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Progress Widget -->
<div id="progressWidget" class="progress-widget" style="display: none;">
    <div class="widget-header">
        <span><i class="fas fa-exchange-alt"></i> <span id="progressType">Processing</span>...</span>
        <div class="widget-actions">
            <button id="viewLogBtn" title="View Log"><i class="fas fa-file-alt"></i></button>
            <button id="cancelProgressBtn" title="Cancel"><i class="fas fa-times"></i></button>
        </div>
    </div>
    <div class="progress-ring-container">
        <svg width="80" height="80" viewBox="0 0 80 80">
            <circle class="progress-ring-circle-bg" cx="40" cy="40" r="35" fill="none" stroke-width="4"/>
            <circle id="progressRing" class="progress-ring-circle" cx="40" cy="40" r="35" fill="none" stroke-width="4" stroke-dasharray="219.9" stroke-dashoffset="219.9"/>
        </svg>
        <div class="progress-text" id="progressPercent">0%</div>
    </div>
    <div class="progress-status" id="progressStatus">Waiting...</div>
    <div class="progress-current-file" id="progressCurrentFile"></div>
</div>

<!-- Log Modal -->
<div class="modal fade" id="logModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-file-alt"></i> Operation Log</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <pre id="logContent" style="max-height: 400px; overflow: auto; font-size: 12px;"></pre>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Upload Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-cloud-upload-alt"></i> Upload Files</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Select Files</label>
                    <input type="file" class="form-control" id="uploadFiles" multiple>
                </div>
                <div class="alert alert-primary">Uploading to: <strong id="uploadTargetPanel">Left Panel</strong></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="fileManager.uploadFiles()">Upload</button>
            </div>
        </div>
    </div>
</div>

<!-- Create Folder Modal -->
<div class="modal fade" id="mkdirModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-folder-plus"></i> New Folder</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Folder name</label>
                    <input type="text" class="form-control" id="mkdirName" placeholder="New Folder" required>
                </div>
                <div class="alert alert-primary">Creating in: <strong id="mkdirTargetPanel">Left Panel</strong></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="fileManager.createDirectory()">Create</button>
            </div>
        </div>
    </div>
</div>

<!-- Archive Modal -->
<div class="modal fade" id="archiveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-archive"></i> Archive / Extract</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Action</label>
                    <select class="form-select" id="archiveAction">
                        <option value="create">Create Archive (TAR)</option>
                        <option value="extract">Extract Archive(s)</option>
                    </select>
                </div>
                <div id="createArchiveOptions">
                    <div class="mb-3">
                        <label class="form-label">Archive Name</label>
                        <input type="text" class="form-control" id="archiveName" placeholder="my_archive">
                    </div>
                </div>
                <div id="extractArchiveOptions" style="display:none">
                    <div class="alert alert-info">Selected archive files will be extracted to folders with the same name.</div>
                </div>
                <div class="alert alert-primary">Working in: <strong id="archiveTargetPanel">Left Panel</strong></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="createArchiveBtn" onclick="fileManager.createArchive()">Create Archive</button>
                <button type="button" class="btn btn-primary" id="extractArchiveBtn" style="display:none" onclick="fileManager.extractArchive()">Extract</button>
            </div>
        </div>
    </div>
</div>

<!-- Permissions Modal -->
<div class="modal fade" id="permissionsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-lock"></i> Set Permissions</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Owner</label>
                    <select class="form-select" id="permOwner">
                        <option value="">-- Keep current --</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Group</label>
                    <select class="form-select" id="permGroup">
                        <option value="">-- Keep current --</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Permissions (Unix octal)</label>
                    <select class="form-select" id="permValue">
                        <option value="0755">755 (rwxr-xr-x) - Default for directories</option>
                        <option value="0644">644 (rw-r--r--) - Default for files</option>
                        <option value="0777">777 (rwxrwxrwx) - Full access</option>
                        <option value="0700">700 (rwx------) - Owner only</option>
                        <option value="0750">750 (rwxr-x---) - Owner and group</option>
                        <option value="0666">666 (rw-rw-rw-) - Read/write all</option>
                        <option value="0600">600 (rw-------) - Read/write owner only</option>
                    </select>
                </div>
                <div class="form-check mb-3">
                    <input type="checkbox" class="form-check-input" id="permRecursive">
                    <label class="form-check-label" for="permRecursive">Apply recursively to all subdirectories and files</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="fileManager.setPermissions()">Apply Changes</button>
            </div>
        </div>
    </div>
</div>

<!-- ACL Modal -->
<div class="modal fade" id="aclModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-users"></i> ACL Permissions</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">ACL allows fine-grained permissions for users and groups.</div>
                <div class="card mb-3">
                    <div class="card-header">User ACL Entries</div>
                    <div class="card-body" id="aclUsersContainer">
                        <div class="acl-row">
                            <select class="acl-user-select form-select">
                                <option value="">-- Select User --</option>
                            </select>
                            <select class="acl-user-perms form-select">
                                <option value="r">Read only (r--)</option>
                                <option value="rw">Read-Write (rw-)</option>
                                <option value="rx">Read-Execute (r-x)</option>
                                <option value="rwx">Full (rwx)</option>
                            </select>
                            <button type="button" class="btn btn-sm btn-danger" onclick="this.closest('.acl-row').remove()"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="fileManager.addAclUserRow()"><i class="fas fa-plus"></i> Add User</button>
                </div>
                <div class="card mb-3">
                    <div class="card-header">Group ACL Entries</div>
                    <div class="card-body" id="aclGroupsContainer">
                        <div class="acl-row">
                            <select class="acl-group-select form-select">
                                <option value="">-- Select Group --</option>
                            </select>
                            <select class="acl-group-perms form-select">
                                <option value="r">Read only (r--)</option>
                                <option value="rw">Read-Write (rw-)</option>
                                <option value="rx">Read-Execute (r-x)</option>
                                <option value="rwx">Full (rwx)</option>
                            </select>
                            <button type="button" class="btn btn-sm btn-danger" onclick="this.closest('.acl-row').remove()"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="fileManager.addAclGroupRow()"><i class="fas fa-plus"></i> Add Group</button>
                </div>
                <div class="form-check mb-3">
                    <input type="checkbox" class="form-check-input" id="aclRecursive">
                    <label class="form-check-label" for="aclRecursive">Apply recursively</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="fileManager.setAclPermissions()">Apply ACL</button>
            </div>
        </div>
    </div>
</div>

<script src="lib/bootstrap-5.3.8-dist/js/bootstrap.bundle.min.js"></script>

<script>
const API_BASE = "<?php echo $current_host_id == 1 ? 'api/files_api.php' : rtrim($host_url, '/') . '/files_api.php'; ?>";

// File Manager Application
const fileManager = {
    activePanel: 'left',
    leftState: {
        drive: '/mnt/data',
        dir: '',
        search: '',
        recursive: false,
        items: [],
        selected: new Set()
    },
    rightState: {
        drive: '/mnt/data',
        dir: '',
        search: '',
        recursive: false,
        items: [],
        selected: new Set()
    },
    currentOperationId: null,
    progressInterval: null,
    systemUsers: [],
    systemGroups: [],
    clipboard: null,
    
    init: async function() {
        await this.loadSystemUsers();
        await this.loadSystemGroups();
        await this.loadDrives();
        await this.loadClipboardStatus();
        
        await this.loadDirectory('left');
        await this.loadDirectory('right');
        
        this.setupEventListeners();
        this.setupArchiveActionListener();
        
        await this.checkOngoingOperation();
    },
    
    // API Request
    apiRequest: async function(endpoint, method = 'GET', data = null, isFormData = false) {
		const url = `${API_BASE}?${endpoint}`;
		const options = {
			method: method,
			credentials: 'same-origin',
			headers: {}
		};
		
		if (window.apiConfig && window.apiConfig.apiKey) {
			options.headers['X-API-Key'] = window.apiConfig.apiKey;
		}
		
		if (data) {
			if (isFormData) {
				options.body = data;
			} else {
				options.headers['Content-Type'] = 'application/json';
				options.body = JSON.stringify(data);
			}
		}
		
		if (Object.keys(options.headers).length === 0) {
			delete options.headers;
		}
		
		try {
			const response = await fetch(url, options);
			if (!response.ok) {
				throw new Error(`HTTP ${response.status}`);
			}
			return await response.json();
		} catch (error) {
			console.error('API Error:', error);
			this.showAlert('Network error', 'danger');
			return null;
       
		}
	},
    
    loadSystemUsers: async function() {
        const result = await this.apiRequest('action=get_system_users');
        if (result && result.success) {
            this.systemUsers = result.users;
            this.populateUserSelects();
        }
    },
    
    loadSystemGroups: async function() {
        const result = await this.apiRequest('action=get_system_groups');
        if (result && result.success) {
            this.systemGroups = result.groups;
            this.populateGroupSelects();
        }
    },
    
    populateUserSelects: function() {
        const options = this.systemUsers.map(u => `<option value="${this.escapeHtml(u)}">${this.escapeHtml(u)}</option>`).join('');
        document.querySelectorAll('.acl-user-select').forEach(select => {
            select.innerHTML = '<option value="">-- Select User --</option>' + options;
        });
        const permOwner = document.getElementById('permOwner');
        if (permOwner) {
            permOwner.innerHTML = '<option value="">-- Keep current --</option>' + options;
        }
    },
    
    populateGroupSelects: function() {
        const options = this.systemGroups.map(g => `<option value="${this.escapeHtml(g)}">${this.escapeHtml(g)}</option>`).join('');
        document.querySelectorAll('.acl-group-select').forEach(select => {
            select.innerHTML = '<option value="">-- Select Group --</option>' + options;
        });
        const permGroup = document.getElementById('permGroup');
        if (permGroup) {
            permGroup.innerHTML = '<option value="">-- Keep current --</option>' + options;
        }
    },
    
    loadDrives: async function() {
        const result = await this.apiRequest('action=get_drives');
        if (result && result.success) {
            const drives = result.drives;
            const options = drives.map(d => `<option value="${this.escapeHtml(d.mount)}">💾 ${this.escapeHtml(d.mount)}</option>`).join('');
            
            const leftSelect = document.getElementById('leftDriveSelect');
            const rightSelect = document.getElementById('rightDriveSelect');
            if (leftSelect) leftSelect.innerHTML = options;
            if (rightSelect) rightSelect.innerHTML = options;
            
            if (drives.length > 0) {
                this.leftState.drive = drives[0].mount;
                this.rightState.drive = drives[0].mount;
                if (leftSelect) leftSelect.value = this.leftState.drive;
                if (rightSelect) rightSelect.value = this.rightState.drive;
                this.updateDriveLabels();
            }
        }
    },
    
    updateDriveLabels: function() {
        document.getElementById('leftDriveLabel').innerText = this.leftState.drive;
        document.getElementById('rightDriveLabel').innerText = this.rightState.drive;
    },
    
    loadDirectory: async function(panel) {
        const state = panel === 'left' ? this.leftState : this.rightState;
        const fileList = document.getElementById(`${panel}FileList`);
        
        if (fileList) {
            fileList.innerHTML = '<div class="spinner-container"><div class="spinner"></div></div>';
        }
        
        const params = new URLSearchParams({
            action: 'list_directory',
            drive: state.drive,
            dir: state.dir,
            search: state.search,
            recursive: state.recursive ? '1' : '0'
        });
        
        const result = await this.apiRequest(params.toString());
        
        if (result && result.success) {
            state.items = result.items;
            this.renderFileList(panel);
        } else if (result && result.error) {
            this.showAlert(result.error, 'danger');
            if (fileList) fileList.innerHTML = `<div class="text-center text-danger p-4">${this.escapeHtml(result.error)}</div>`;
        }
        
        this.updatePathDisplay(panel);
    },
    
    renderFileList: function(panel) {
        const state = panel === 'left' ? this.leftState : this.rightState;
        const fileList = document.getElementById(`${panel}FileList`);
        const isRoot = state.dir === '';
        
        if (!fileList) return;
        
        if (state.items.length === 0 && !state.search) {
            fileList.innerHTML = '<div class="text-center text-muted p-4"><i class="fas fa-folder-open"></i> Empty directory</div>';
            return;
        }
        
        if (state.items.length === 0 && state.search) {
            fileList.innerHTML = `<div class="text-center text-muted p-4"><i class="fas fa-search"></i> No files matching "${this.escapeHtml(state.search)}"</div>`;
            return;
        }
        
        let html = '';
        
        if (!state.search && !isRoot) {
            html += `
                <div class="file-row parent-folder" data-path="..">
                    <div class="checkbox-col"></div>
                    <div class="file-icon"><i class="fas fa-arrow-up"></i></div>
                    <div class="file-name"><a href="#" onclick="fileManager.navigateUp('${panel}'); return false;">..</a></div>
                    <div class="file-size">-</div>
                    <div class="file-perms">-</div>
                    <div class="file-owner">-</div>
                    <div class="file-date">-</div>
                    <div class="file-actions"></div>
                </div>
            `;
        }
        
        for (const item of state.items) {
            const isSelected = state.selected.has(item.name);
            const checkboxId = `${panel}_cb_${this.escapeHtml(item.name).replace(/[^a-zA-Z0-9]/g, '_')}`;
            
            html += `
                <div class="file-row ${isSelected ? 'selected' : ''}" data-filename="${this.escapeHtml(item.name)}" data-path="${this.escapeHtml(item.path)}" data-is-dir="${item.is_dir}">
                    <div class="checkbox-col">
                        <input type="checkbox" class="${panel}-checkbox" data-name="${this.escapeHtml(item.name)}" ${isSelected ? 'checked' : ''} onclick="event.stopPropagation(); fileManager.toggleSelect('${panel}', '${this.escapeHtml(item.name)}')">
                    </div>
                    <div class="file-icon"><i class="fas fa-${item.is_dir ? 'folder' : 'file'}"></i></div>
                    <div class="file-name">
                        ${item.is_dir 
                            ? `<a href="#" onclick="fileManager.navigateTo('${panel}', '${this.escapeHtml(item.name)}'); return false;">${this.escapeHtml(item.name)}</a>`
                            : this.escapeHtml(item.name)
                        }
                        ${state.search && !item.is_dir ? `<small class="text-muted ms-2">(${this.escapeHtml(item.rel_path || '')})</small>` : ''}
                    </div>
                    <div class="file-size">${item.is_dir ? '&lt;DIR&gt;' : this.formatSize(item.size)}</div>
                    <div class="file-perms"><code>${this.escapeHtml(item.perms_text || '---------')}</code></div>
                    <div class="file-owner">${this.escapeHtml(item.owner || 'unknown')}:${this.escapeHtml(item.group || 'unknown')}</div>
                    <div class="file-date">${this.escapeHtml(item.modified || 'Unknown')}</div>
                    <div class="file-actions">
                        ${item.is_dir 
                            ? `<button class="btn-icon" onclick="event.stopPropagation(); fileManager.downloadFolder('${panel}', '${this.escapeHtml(item.path)}')" title="Download as TAR"><i class="fas fa-file-archive"></i></button>`
                            : `<button class="btn-icon" onclick="event.stopPropagation(); fileManager.downloadFile('${this.escapeHtml(item.path)}')" title="Download file"><i class="fas fa-download"></i></button>`
                        }
                    </div>
                </div>
            `;
        }
        
        fileList.innerHTML = html;
        
        this.updateSearchInfo(panel);
    },
    
    updateSearchInfo: function(panel) {
        const state = panel === 'left' ? this.leftState : this.rightState;
        const searchInfo = document.getElementById(`${panel}SearchInfo`);
        const clearBtn = document.getElementById(`${panel}ClearSearchBtn`);
        
        if (state.search) {
            if (searchInfo) {
                searchInfo.innerHTML = `<i class="fas fa-info-circle"></i> Found ${state.items.length} item(s) matching "${this.escapeHtml(state.search)}"`;
                searchInfo.style.display = 'block';
            }
            if (clearBtn) clearBtn.style.display = 'inline-block';
        } else {
            if (searchInfo) searchInfo.style.display = 'none';
            if (clearBtn) clearBtn.style.display = 'none';
        }
    },
    
    updatePathDisplay: function(panel) {
        const state = panel === 'left' ? this.leftState : this.rightState;
        const fullPath = state.dir === '' ? state.drive : state.drive + '/' + state.dir;
        const pathLink = document.getElementById(`${panel}PathLink`);
        
        if (pathLink) {
            pathLink.innerHTML = `<a href="#" onclick="fileManager.navigateTo('${panel}', ''); return false;">${this.escapeHtml(fullPath)}</a>`;
        }
    },
    
    navigateTo: async function(panel, dirName) {
        const state = panel === 'left' ? this.leftState : this.rightState;
        
        if (dirName === '') {
            state.dir = '';
        } else {
            const newDir = state.dir === '' ? dirName : state.dir + '/' + dirName;
            state.dir = newDir;
        }
        
        state.search = '';
        state.recursive = false;
        state.selected.clear();
        
        const searchInput = document.getElementById(`${panel}SearchInput`);
        const recursiveCheckbox = document.getElementById(`${panel}RecursiveCheckbox`);
        if (searchInput) searchInput.value = '';
        if (recursiveCheckbox) recursiveCheckbox.checked = false;
        
        await this.loadDirectory(panel);
    },
    
    navigateUp: async function(panel) {
        const state = panel === 'left' ? this.leftState : this.rightState;
        
        if (state.dir === '') return;
        
        const parts = state.dir.split('/');
        parts.pop();
        state.dir = parts.join('');
        
        state.search = '';
        state.recursive = false;
        state.selected.clear();
        
        const searchInput = document.getElementById(`${panel}SearchInput`);
        const recursiveCheckbox = document.getElementById(`${panel}RecursiveCheckbox`);
        if (searchInput) searchInput.value = '';
        if (recursiveCheckbox) recursiveCheckbox.checked = false;
        
        await this.loadDirectory(panel);
    },
    
    toggleSelect: function(panel, fileName) {
        const state = panel === 'left' ? this.leftState : this.rightState;
        
        if (state.selected.has(fileName)) {
            state.selected.delete(fileName);
        } else {
            state.selected.add(fileName);
        }
        
        const row = document.querySelector(`#${panel}FileList .file-row[data-filename="${fileName.replace(/"/g, '&quot;')}"]`);
        if (row) {
            if (state.selected.has(fileName)) {
                row.classList.add('selected');
            } else {
                row.classList.remove('selected');
            }
        }
    },
    
    selectAll: function(panel) {
        const state = panel === 'left' ? this.leftState : this.rightState;
        
        if (state.selected.size === state.items.length) {
            state.selected.clear();
        } else {
            state.selected.clear();
            for (const item of state.items) {
                state.selected.add(item.name);
            }
        }
        
        const rows = document.querySelectorAll(`#${panel}FileList .file-row`);
        for (const row of rows) {
            const checkbox = row.querySelector(`.${panel}-checkbox`);
            if (checkbox) {
                const fileName = checkbox.getAttribute('data-name');
                if (state.selected.has(fileName)) {
                    row.classList.add('selected');
                    checkbox.checked = true;
                } else {
                    row.classList.remove('selected');
                    checkbox.checked = false;
                }
            }
        }
    },
    
    // Get selected files
    getSelectedFiles: function(panel) {
        const state = panel === 'left' ? this.leftState : this.rightState;
        return Array.from(state.selected);
    },
    
    // Check if any files selected
    hasSelected: function(panel) {
        return this.getSelectedFiles(panel).length > 0;
    },
    
    // Copy to other panel
    copyToOther: async function(panel) {
        if (!this.hasSelected(panel)) {
            this.showAlert('Please select at least one item.', 'warning');
            return false;
        }
        
        const selectedFiles = this.getSelectedFiles(panel);
        const sourceState = panel === 'left' ? this.leftState : this.rightState;
        const targetState = panel === 'left' ? this.rightState : this.leftState;
        
        const sourceDir = sourceState.dir === '' ? sourceState.drive : sourceState.drive + '/' + sourceState.dir;
        const targetDir = targetState.dir === '' ? targetState.drive : targetState.drive + '/' + targetState.dir;
        
        const result = await this.apiRequest('action=copy_to_other', 'POST', {
            source_dir: sourceDir,
            target_dir: targetDir,
            files: selectedFiles
        });
        
        if (result && result.success) {
            this.showAlert(result.message, 'success');
            if (result.operation_id) {
                this.startProgressMonitoring(result.operation_id);
            }
            // Clear selection and refresh
            sourceState.selected.clear();
            await this.loadDirectory(panel);
        } else if (result && result.error) {
            this.showAlert(result.error, 'danger');
        }
        
        return true;
    },
    
    // Move to other panel
    moveToOther: async function(panel) {
        if (!this.hasSelected(panel)) {
            this.showAlert('Please select at least one item.', 'warning');
            return false;
        }
        
        const selectedFiles = this.getSelectedFiles(panel);
        const sourceState = panel === 'left' ? this.leftState : this.rightState;
        const targetState = panel === 'left' ? this.rightState : this.leftState;
        
        const sourceDir = sourceState.dir === '' ? sourceState.drive : sourceState.drive + '/' + sourceState.dir;
        const targetDir = targetState.dir === '' ? targetState.drive : targetState.drive + '/' + targetState.dir;
        
        const result = await this.apiRequest('action=move_to_other', 'POST', {
            source_dir: sourceDir,
            target_dir: targetDir,
            files: selectedFiles
        });
        
        if (result && result.success) {
            this.showAlert(result.message, 'success');
            if (result.operation_id) {
                this.startProgressMonitoring(result.operation_id);
            }
            sourceState.selected.clear();
            await this.loadDirectory(panel);
            await this.loadDirectory(panel === 'left' ? 'right' : 'left');
        } else if (result && result.error) {
            this.showAlert(result.error, 'danger');
        }
        
        return true;
    },
    
    // Copy to clipboard
    copyToClipboard: async function(panel) {
        if (!this.hasSelected(panel)) {
            this.showAlert('Please select at least one item.', 'warning');
            return false;
        }
        
        const selectedFiles = this.getSelectedFiles(panel);
        const state = panel === 'left' ? this.leftState : this.rightState;
        const sourceDir = state.dir === '' ? state.drive : state.drive + '/' + state.dir;
        
        const result = await this.apiRequest('action=copy_to_clipboard', 'POST', {
            source_dir: sourceDir,
            files: selectedFiles
        });
        
        if (result && result.success) {
            this.showAlert(result.message, 'success');
            this.clipboard = result.clipboard;
            this.updateClipboardIndicator();
        } else if (result && result.error) {
            this.showAlert(result.error, 'danger');
        }
        
        return true;
    },
    
    // Cut to clipboard
    cutToClipboard: async function(panel) {
        if (!this.hasSelected(panel)) {
            this.showAlert('Please select at least one item.', 'warning');
            return false;
        }
        
        const selectedFiles = this.getSelectedFiles(panel);
        const state = panel === 'left' ? this.leftState : this.rightState;
        const sourceDir = state.dir === '' ? state.drive : state.drive + '/' + state.dir;
        
        const result = await this.apiRequest('action=cut_to_clipboard', 'POST', {
            source_dir: sourceDir,
            files: selectedFiles
        });
        
        if (result && result.success) {
            this.showAlert(result.message, 'success');
            this.clipboard = result.clipboard;
            this.updateClipboardIndicator();
        } else if (result && result.error) {
            this.showAlert(result.error, 'danger');
        }
        
        return true;
    },
    
    // Paste from clipboard
    pasteFromClipboard: async function(panel) {
        if (!this.clipboard || !this.clipboard.files || this.clipboard.files.length === 0) {
            this.showAlert('Clipboard is empty.', 'warning');
            return false;
        }
        
        const state = panel === 'left' ? this.leftState : this.rightState;
        const targetDir = state.dir === '' ? state.drive : state.drive + '/' + state.dir;
        
        const result = await this.apiRequest('action=paste_from_clipboard', 'POST', {
            target_dir: targetDir,
            clipboard: this.clipboard
        });
        
        if (result && result.success) {
            this.showAlert(result.message, 'success');
            if (result.operation_id) {
                if (this.clipboard.operation === 'cut') {
                    this.clipboard = null;
                    this.updateClipboardIndicator();
                }
                this.startProgressMonitoring(result.operation_id);
            }
            await this.loadDirectory(panel);
        } else if (result && result.error) {
            this.showAlert(result.error, 'danger');
        }
        
        return true;
    },
    
    // Delete selected files
    deleteSelected: async function(panel) {
        if (!this.hasSelected(panel)) {
            this.showAlert('Please select at least one item.', 'warning');
            return false;
        }
        
        const count = this.getSelectedFiles(panel).length;
        if (!confirm(`Delete ${count} selected item(s)? This cannot be undone.`)) {
            return false;
        }
        
        const selectedFiles = this.getSelectedFiles(panel);
        const state = panel === 'left' ? this.leftState : this.rightState;
        const targetDir = state.dir === '' ? state.drive : state.drive + '/' + state.dir;
        
        const result = await this.apiRequest('action=delete', 'POST', {
            target_dir: targetDir,
            files: selectedFiles
        });
        
        if (result && result.success) {
            this.showAlert(result.message, 'success');
            state.selected.clear();
            await this.loadDirectory(panel);
        } else if (result && result.error) {
            this.showAlert(result.error, 'danger');
        }
        
        return true;
    },
    
    // Upload files
    uploadFiles: async function() {
        const fileInput = document.getElementById('uploadFiles');
        const files = fileInput.files;
        
        if (files.length === 0) {
            this.showAlert('Please select files to upload.', 'warning');
            return;
        }
        
        const state = this.activePanel === 'left' ? this.leftState : this.rightState;
        const targetDir = state.dir === '' ? state.drive : state.drive + '/' + state.dir;
        
        const formData = new FormData();
        formData.append('action', 'upload');
        formData.append('target_dir', targetDir);
        
        for (let i = 0; i < files.length; i++) {
            formData.append('files[]', files[i]);
        }
        
        try {
            const response = await fetch(`${API_BASE}`, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });
            const result = await response.json();
            
            if (result && result.success) {
                this.showAlert(result.message, 'success');
                fileInput.value = '';
                await this.loadDirectory(this.activePanel);
                const modal = bootstrap.Modal.getInstance(document.getElementById('uploadModal'));
                if (modal) modal.hide();
            } else if (result && result.error) {
                this.showAlert(result.error, 'danger');
            }
        } catch (error) {
            this.showAlert('Upload failed: ' + error.message, 'danger');
        }
    },
    
    // Create directory
    createDirectory: async function() {
        const dirName = document.getElementById('mkdirName').value.trim();
        
        if (!dirName) {
            this.showAlert('Please enter a folder name.', 'warning');
            return;
        }
        
        const state = this.activePanel === 'left' ? this.leftState : this.rightState;
        const targetDir = state.dir === '' ? state.drive : state.drive + '/' + state.dir;
        
        const result = await this.apiRequest('action=mkdir', 'POST', {
            target_dir: targetDir,
            dirname: dirName
        });
        
        if (result && result.success) {
            this.showAlert(result.message, 'success');
            document.getElementById('mkdirName').value = '';
            await this.loadDirectory(this.activePanel);
            const modal = bootstrap.Modal.getInstance(document.getElementById('mkdirModal'));
            if (modal) modal.hide();
        } else if (result && result.error) {
            this.showAlert(result.error, 'danger');
        }
    },
    
    // Create archive
    createArchive: async function() {
        const selectedFiles = this.getSelectedFiles(this.activePanel);
        
        if (selectedFiles.length === 0) {
            this.showAlert('Please select at least one item.', 'warning');
            return false;
        }
        
        const archiveName = document.getElementById('archiveName').value.trim() || 'archive';
        const state = this.activePanel === 'left' ? this.leftState : this.rightState;
        const targetDir = state.dir === '' ? state.drive : state.drive + '/' + state.dir;
        
        const result = await this.apiRequest('action=create_archive', 'POST', {
            target_dir: targetDir,
            files: selectedFiles,
            archive_name: archiveName
        });
        
        if (result && result.success) {
            this.showAlert(result.message, 'success');
            await this.loadDirectory(this.activePanel);
            const modal = bootstrap.Modal.getInstance(document.getElementById('archiveModal'));
            if (modal) modal.hide();
        } else if (result && result.error) {
            this.showAlert(result.error, 'danger');
        }
        
        return true;
    },
    
    // Extract archive
    extractArchive: async function() {
        const selectedFiles = this.getSelectedFiles(this.activePanel);
        
        if (selectedFiles.length === 0) {
            this.showAlert('Please select at least one archive.', 'warning');
            return false;
        }
        
        const state = this.activePanel === 'left' ? this.leftState : this.rightState;
        const targetDir = state.dir === '' ? state.drive : state.drive + '/' + state.dir;
        
        const result = await this.apiRequest('action=extract_archive', 'POST', {
            target_dir: targetDir,
            files: selectedFiles
        });
        
        if (result && result.success) {
            this.showAlert(result.message, 'success');
            await this.loadDirectory(this.activePanel);
            const modal = bootstrap.Modal.getInstance(document.getElementById('archiveModal'));
            if (modal) modal.hide();
        } else if (result && result.error) {
            this.showAlert(result.error, 'danger');
        }
        
        return true;
    },
    
    // Set permissions
    setPermissions: async function() {
        const selectedFiles = this.getSelectedFiles(this.activePanel);
        
        if (selectedFiles.length === 0) {
            this.showAlert('Please select at least one item.', 'warning');
            return false;
        }
        
        const owner = document.getElementById('permOwner').value;
        const group = document.getElementById('permGroup').value;
        const perms = document.getElementById('permValue').value;
        const recursive = document.getElementById('permRecursive').checked;
        
        const state = this.activePanel === 'left' ? this.leftState : this.rightState;
        const targetDir = state.dir === '' ? state.drive : state.drive + '/' + state.dir;
        
        const result = await this.apiRequest('action=set_permissions', 'POST', {
            target_dir: targetDir,
            files: selectedFiles,
            owner: owner,
            group: group,
            permissions: perms,
            recursive: recursive
        });
        
        if (result && result.success) {
            this.showAlert(result.message, 'success');
            await this.loadDirectory(this.activePanel);
            const modal = bootstrap.Modal.getInstance(document.getElementById('permissionsModal'));
            if (modal) modal.hide();
        } else if (result && result.error) {
            this.showAlert(result.error, 'danger');
        }
        
        return true;
    },
    
    // Set ACL permissions
    setAclPermissions: async function() {
        const selectedFiles = this.getSelectedFiles(this.activePanel);
        
        if (selectedFiles.length === 0) {
            this.showAlert('Please select at least one item.', 'warning');
            return false;
        }
        
        const userPerms = [];
        const userRows = document.querySelectorAll('#aclUsersContainer .acl-row');
        for (const row of userRows) {
            const userSelect = row.querySelector('.acl-user-select');
            const permsSelect = row.querySelector('.acl-user-perms');
            if (userSelect && permsSelect && userSelect.value) {
                userPerms.push({ user: userSelect.value, perms: permsSelect.value });
            }
        }
        
        const groupPerms = [];
        const groupRows = document.querySelectorAll('#aclGroupsContainer .acl-row');
        for (const row of groupRows) {
            const groupSelect = row.querySelector('.acl-group-select');
            const permsSelect = row.querySelector('.acl-group-perms');
            if (groupSelect && permsSelect && groupSelect.value) {
                groupPerms.push({ group: groupSelect.value, perms: permsSelect.value });
            }
        }
        
        const recursive = document.getElementById('aclRecursive').checked;
        const state = this.activePanel === 'left' ? this.leftState : this.rightState;
        const targetDir = state.dir === '' ? state.drive : state.drive + '/' + state.dir;
        
        const result = await this.apiRequest('action=set_acl_permissions', 'POST', {
            target_dir: targetDir,
            files: selectedFiles,
            user_perms: userPerms,
            group_perms: groupPerms,
            recursive: recursive
        });
        
        if (result && result.success) {
            this.showAlert(result.message, 'success');
            await this.loadDirectory(this.activePanel);
            const modal = bootstrap.Modal.getInstance(document.getElementById('aclModal'));
            if (modal) modal.hide();
        } else if (result && result.error) {
            this.showAlert(result.error, 'danger');
        }
        
        return true;
    },
    
    // Download file
    downloadFile: function(filePath) {
        window.location.href = `${API_BASE}?action=download&path=${encodeURIComponent(filePath)}`;
    },
    
    // Download folder as TAR
    downloadFolder: function(panel, folderPath) {
        window.location.href = `${API_BASE}?action=download_folder&path=${encodeURIComponent(folderPath)}`;
    },
    
    // Start progress monitoring
    startProgressMonitoring: function(operationId) {
        if (this.progressInterval) {
            clearInterval(this.progressInterval);
        }
        
        this.currentOperationId = operationId;
        this.progressInterval = setInterval(() => this.checkProgress(), 1000);
        
        const widget = document.getElementById('progressWidget');
        if (widget) widget.style.display = 'block';
    },
    
    // Check progress
    checkProgress: async function() {
        if (!this.currentOperationId) {
            if (this.progressInterval) {
                clearInterval(this.progressInterval);
                this.progressInterval = null;
            }
            return;
        }
        
        const result = await this.apiRequest(`action=check_progress&operation_id=${encodeURIComponent(this.currentOperationId)}`);
        
        if (result && result.success) {
            const data = result.data;
            
            if (data.active && data.total > 0) {
                const percent = data.current_percent || 0;
                const circumference = 219.9;
                const offset = circumference - (percent / 100) * circumference;
                const ring = document.getElementById('progressRing');
                if (ring) ring.style.strokeDashoffset = offset;
                
                const percentEl = document.getElementById('progressPercent');
                if (percentEl) percentEl.innerText = Math.round(percent) + '%';
                
                const statusEl = document.getElementById('progressStatus');
                if (statusEl) statusEl.innerHTML = `${data.current || 0} / ${data.total} items`;
                
                const typeEl = document.getElementById('progressType');
                if (typeEl) typeEl.innerText = data.operation === 'copy' ? 'Copying' : (data.operation === 'move' ? 'Moving' : 'Processing');
                
                const currentFileEl = document.getElementById('progressCurrentFile');
                if (currentFileEl && data.current_file) {
                    currentFileEl.innerHTML = `<i class="fas fa-file"></i> ${this.escapeHtml(data.current_file.substring(0, 50))}`;
                }
                
                if (data.completed) {
                    this.stopProgressMonitoring();
                    this.showAlert('Operation completed successfully!', 'success');
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                }
            } else if (data.completed) {
                this.stopProgressMonitoring();
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            }
        }
    },
    
    // Stop progress monitoring
    stopProgressMonitoring: function() {
        if (this.progressInterval) {
            clearInterval(this.progressInterval);
            this.progressInterval = null;
        }
        
        const widget = document.getElementById('progressWidget');
        if (widget) widget.style.display = 'none';
        
        this.currentOperationId = null;
    },
    
    // Cancel current operation
    cancelCurrentOperation: async function() {
        if (!this.currentOperationId) return;
        
        if (!confirm('Cancel current operation?')) return;
        
        const result = await this.apiRequest(`action=cancel_operation&operation_id=${encodeURIComponent(this.currentOperationId)}`);
        
        if (result && result.success) {
            this.stopProgressMonitoring();
            this.showAlert('Operation cancelled.', 'warning');
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        }
    },
    
    // View operation log
    viewOperationLog: async function() {
        if (!this.currentOperationId) {
            this.showAlert('No active operation', 'warning');
            return;
        }
        
        const result = await this.apiRequest(`action=get_operation_log&operation_id=${encodeURIComponent(this.currentOperationId)}`);
        
        if (result && result.success) {
            const logContent = document.getElementById('logContent');
            if (logContent) logContent.innerText = result.log || 'No log data available.';
            const logModal = new bootstrap.Modal(document.getElementById('logModal'));
            logModal.show();
        } else if (result && result.error) {
            this.showAlert(result.error, 'danger');
        }
    },
    
    // Check for ongoing operation
    checkOngoingOperation: async function() {
        const result = await this.apiRequest('action=get_current_operation');
        
        if (result && result.success && result.operation_id) {
            this.startProgressMonitoring(result.operation_id);
        }
    },
    
    // Load clipboard status
    loadClipboardStatus: async function() {
        const result = await this.apiRequest('action=get_clipboard');
        
        if (result && result.success && result.clipboard && result.clipboard.files && result.clipboard.files.length > 0) {
            this.clipboard = result.clipboard;
            this.updateClipboardIndicator();
        } else {
            this.clipboard = null;
            this.updateClipboardIndicator();
        }
    },
    
    // Update clipboard indicator
    updateClipboardIndicator: function() {
        const indicator = document.getElementById('clipboardIndicator');
        
        if (this.clipboard && this.clipboard.files && this.clipboard.files.length > 0) {
            indicator.innerHTML = `
                <i class="fas fa-${this.clipboard.operation === 'copy' ? 'copy' : 'cut'}"></i>
                ${this.clipboard.files.length} item(s) in clipboard
                <a href="#" onclick="fileManager.clearClipboard(); return false;" class="ms-2"><i class="fas fa-times"></i> Clear</a>
            `;
            indicator.style.display = 'block';
        } else {
            indicator.style.display = 'none';
        }
    },
    
    // Clear clipboard
    clearClipboard: async function() {
        const result = await this.apiRequest('action=clear_clipboard', 'POST');
        
        if (result && result.success) {
            this.clipboard = null;
            this.updateClipboardIndicator();
            this.showAlert('Clipboard cleared.', 'info');
        }
    },
    
    // Set active panel for modals
    setActivePanel: function(panel) {
        this.activePanel = panel;
        
        const uploadTarget = document.getElementById('uploadTargetPanel');
        const mkdirTarget = document.getElementById('mkdirTargetPanel');
        const archiveTarget = document.getElementById('archiveTargetPanel');
        
        const panelText = panel === 'left' ? 'Left Panel' : 'Right Panel';
        
        if (uploadTarget) uploadTarget.innerText = panelText;
        if (mkdirTarget) mkdirTarget.innerText = panelText;
        if (archiveTarget) archiveTarget.innerText = panelText;
    },
    
    // Add ACL user row
    addAclUserRow: function() {
        const container = document.getElementById('aclUsersContainer');
        if (!container) return;
        
        const options = this.systemUsers.map(u => `<option value="${this.escapeHtml(u)}">${this.escapeHtml(u)}</option>`).join('');
        
        const div = document.createElement('div');
        div.className = 'acl-row';
        div.innerHTML = `
            <select class="acl-user-select form-select">
                <option value="">-- Select User --</option>
                ${options}
            </select>
            <select class="acl-user-perms form-select">
                <option value="r">Read only (r--)</option>
                <option value="rw">Read-Write (rw-)</option>
                <option value="rx">Read-Execute (r-x)</option>
                <option value="rwx">Full (rwx)</option>
            </select>
            <button type="button" class="btn btn-sm btn-danger" onclick="this.closest('.acl-row').remove()"><i class="fas fa-trash"></i></button>
        `;
        container.appendChild(div);
    },
    
    // Add ACL group row
    addAclGroupRow: function() {
        const container = document.getElementById('aclGroupsContainer');
        if (!container) return;
        
        const options = this.systemGroups.map(g => `<option value="${this.escapeHtml(g)}">${this.escapeHtml(g)}</option>`).join('');
        
        const div = document.createElement('div');
        div.className = 'acl-row';
        div.innerHTML = `
            <select class="acl-group-select form-select">
                <option value="">-- Select Group --</option>
                ${options}
            </select>
            <select class="acl-group-perms form-select">
                <option value="r">Read only (r--)</option>
                <option value="rw">Read-Write (rw-)</option>
                <option value="rx">Read-Execute (r-x)</option>
                <option value="rwx">Full (rwx)</option>
            </select>
            <button type="button" class="btn btn-sm btn-danger" onclick="this.closest('.acl-row').remove()"><i class="fas fa-trash"></i></button>
        `;
        container.appendChild(div);
    },
    
    // Setup event listeners
    setupEventListeners: function() {
        // Left panel drive change
        const leftDriveSelect = document.getElementById('leftDriveSelect');
        if (leftDriveSelect) {
            leftDriveSelect.addEventListener('change', async (e) => {
                this.leftState.drive = e.target.value;
                this.leftState.dir = '';
                this.leftState.search = '';
                this.leftState.selected.clear();
                await this.loadDirectory('left');
                this.updateDriveLabels();
            });
        }
        
        // Right panel drive change
        const rightDriveSelect = document.getElementById('rightDriveSelect');
        if (rightDriveSelect) {
            rightDriveSelect.addEventListener('change', async (e) => {
                this.rightState.drive = e.target.value;
                this.rightState.dir = '';
                this.rightState.search = '';
                this.rightState.selected.clear();
                await this.loadDirectory('right');
                this.updateDriveLabels();
            });
        }
        
        // Left panel search
        const leftSearchBtn = document.getElementById('leftSearchBtn');
        if (leftSearchBtn) {
            leftSearchBtn.addEventListener('click', async () => {
                const searchInput = document.getElementById('leftSearchInput');
                const recursiveCheckbox = document.getElementById('leftRecursiveCheckbox');
                this.leftState.search = searchInput ? searchInput.value.trim() : '';
                this.leftState.recursive = recursiveCheckbox ? recursiveCheckbox.checked : false;
                this.leftState.selected.clear();
                await this.loadDirectory('left');
            });
        }
        
        // Right panel search
        const rightSearchBtn = document.getElementById('rightSearchBtn');
        if (rightSearchBtn) {
            rightSearchBtn.addEventListener('click', async () => {
                const searchInput = document.getElementById('rightSearchInput');
                const recursiveCheckbox = document.getElementById('rightRecursiveCheckbox');
                this.rightState.search = searchInput ? searchInput.value.trim() : '';
                this.rightState.recursive = recursiveCheckbox ? recursiveCheckbox.checked : false;
                this.rightState.selected.clear();
                await this.loadDirectory('right');
            });
        }
        
        // Left clear search
        const leftClearBtn = document.getElementById('leftClearSearchBtn');
        if (leftClearBtn) {
            leftClearBtn.addEventListener('click', async () => {
                const searchInput = document.getElementById('leftSearchInput');
                const recursiveCheckbox = document.getElementById('leftRecursiveCheckbox');
                if (searchInput) searchInput.value = '';
                if (recursiveCheckbox) recursiveCheckbox.checked = false;
                this.leftState.search = '';
                this.leftState.recursive = false;
                this.leftState.selected.clear();
                await this.loadDirectory('left');
            });
        }
        
        // Right clear search
        const rightClearBtn = document.getElementById('rightClearSearchBtn');
        if (rightClearBtn) {
            rightClearBtn.addEventListener('click', async () => {
                const searchInput = document.getElementById('rightSearchInput');
                const recursiveCheckbox = document.getElementById('rightRecursiveCheckbox');
                if (searchInput) searchInput.value = '';
                if (recursiveCheckbox) recursiveCheckbox.checked = false;
                this.rightState.search = '';
                this.rightState.recursive = false;
                this.rightState.selected.clear();
                await this.loadDirectory('right');
            });
        }
        
        // Search on Enter key
        const leftSearchInput = document.getElementById('leftSearchInput');
        if (leftSearchInput) {
            leftSearchInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    document.getElementById('leftSearchBtn').click();
                }
            });
        }
        
        const rightSearchInput = document.getElementById('rightSearchInput');
        if (rightSearchInput) {
            rightSearchInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    document.getElementById('rightSearchBtn').click();
                }
            });
        }
        
        // Cancel button
        const cancelBtn = document.getElementById('cancelProgressBtn');
        if (cancelBtn) {
            cancelBtn.addEventListener('click', () => this.cancelCurrentOperation());
        }
        
        // View log button
        const viewLogBtn = document.getElementById('viewLogBtn');
        if (viewLogBtn) {
            viewLogBtn.addEventListener('click', () => this.viewOperationLog());
        }
    },
    
    // Setup archive action listener
    setupArchiveActionListener: function() {
        const archiveAction = document.getElementById('archiveAction');
        if (archiveAction) {
            archiveAction.addEventListener('change', () => {
                const createOptions = document.getElementById('createArchiveOptions');
                const extractOptions = document.getElementById('extractArchiveOptions');
                const createBtn = document.getElementById('createArchiveBtn');
                const extractBtn = document.getElementById('extractArchiveBtn');
                
                if (archiveAction.value === 'create') {
                    if (createOptions) createOptions.style.display = 'block';
                    if (extractOptions) extractOptions.style.display = 'none';
                    if (createBtn) createBtn.style.display = 'inline-block';
                    if (extractBtn) extractBtn.style.display = 'none';
                } else {
                    if (createOptions) createOptions.style.display = 'none';
                    if (extractOptions) extractOptions.style.display = 'block';
                    if (createBtn) createBtn.style.display = 'none';
                    if (extractBtn) extractBtn.style.display = 'inline-block';
                }
            });
        }
    },
    
    // Format file size
    formatSize: function(bytes) {
        if (bytes === 0) return '0 B';
        const units = ['B', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(1024));
        return parseFloat((bytes / Math.pow(1024, i)).toFixed(1)) + ' ' + units[i];
    },
    
    // Escape HTML
    escapeHtml: function(str) {
        if (!str) return '';
        return str
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    },
    
    // Show alert message
    showAlert: function(message, type) {
        const alertContainer = document.getElementById('alertContainer');
        if (!alertContainer) return;
        
        const alert = document.createElement('div');
        alert.className = `alert alert-${type} alert-apple alert-dismissible fade show`;
        alert.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : (type === 'danger' ? 'exclamation-triangle' : 'info-circle')}"></i>
            ${this.escapeHtml(message)}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        alertContainer.appendChild(alert);
        
        setTimeout(() => {
            if (alert && alert.parentNode) {
                alert.classList.remove('show');
                setTimeout(() => alert.remove(), 300);
            }
        }, 5000);
    }
};

// Initialize on load
document.addEventListener('DOMContentLoaded', () => {
    fileManager.init();
});

</script>
</body>
</html>