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
require_once 'lang/loader.php';

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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Cron Jobs — Mini-b</title>
    
    <link href="lib/bootstrap-5.3.8-dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="lib/bootstrap-icons-1.11.0/bootstrap-icons.css">
    <link rel="stylesheet" href="lib/fontawesome-free-6.7.2-web/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="css/loader.css">
    <link rel="shortcut icon" href="css/icon.ico" type="image/x-icon">
	<script>
	window.lang = {
		<?php
		for ($i = 1; $i <= 100; $i++) {
			$var_name = "lang$i";
			if (isset($$var_name)) {
				echo "'$i': '" . addslashes($$var_name) . "',\n";
			}
		}
		?>
	};
	function __(num) {
		return window.lang[num] || 'lang'+num;
	}
	console.log('Language loaded');
	</script>
    
    <style>
        * {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e9eef3 100%);
            min-height: 100vh;
        }
        
        .main-content {
            padding: 25px 30px;
        }
        
        @media (max-width: 768px) {
            .main-content {
                padding: 20px 15px;
            }
        }
        
        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .content-header h1 {
            font-size: 28px;
            font-weight: 600;
            letter-spacing: -0.3px;
            background: linear-gradient(135deg, #1c1c1e, #3a3a3c);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 0;
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
        
        .apple-card:hover {
            box-shadow: 0 12px 36px rgba(0, 0, 0, 0.12);
            transform: translateY(-2px);
        }
        
        .job-card {
            background: white;
            border-radius: 20px;
            border: 1px solid #e9ecef;
            transition: all 0.2s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .job-card:hover {
            border-color: #007aff40;
            box-shadow: 0 8px 20px rgba(0,122,255,0.1);
        }
        
        .job-card.enabled {
            border-left: 4px solid #34c759;
        }
        
        .job-card.disabled {
            border-left: 4px solid #8e8e93;
            opacity: 0.7;
        }
        
        .job-card-header {
            padding: 16px 20px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        
        .job-card-body {
            padding: 16px 20px;
            flex: 1;
        }
        
        .job-card-footer {
            padding: 12px 20px;
            border-top: 1px solid #e9ecef;
            background: #f8f9fa;
            font-size: 12px;
            color: #6c757d;
        }
        
        .job-name {
            font-weight: 600;
            font-size: 16px;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .job-comment {
            font-size: 13px;
            color: #6c757d;
            margin-top: 4px;
            font-style: italic;
        }
        
        .schedule-code {
            font-family: 'SF Mono', Monaco, monospace;
            font-size: 12px;
            background: #f8f9fa;
            padding: 6px 10px;
            border-radius: 10px;
            margin: 10px 0;
            word-break: break-all;
        }
        
        .command-code {
            font-family: 'SF Mono', Monaco, monospace;
            font-size: 11px;
            background: #1c1c1e;
            color: #e5e5ea;
            padding: 8px 10px;
            border-radius: 10px;
            margin: 10px 0;
            word-break: break-all;
            overflow-x: auto;
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
        }
        
        .status-success {
            background: #34c75920;
            color: #248a3d;
        }
        
        .status-failed {
            background: #ff3b3020;
            color: #d70015;
        }
        
        .status-pending {
            background: #e5e5ea;
            color: #6c757d;
        }
        
        .badge-enabled {
            background: #34c75920;
            color: #248a3d;
        }
        
        .badge-disabled {
            background: #e5e5ea;
            color: #6c757d;
        }
        
        .run-time {
            font-size: 12px;
            color: #6c757d;
            display: flex;
            align-items: center;
            gap: 6px;
            margin-top: 8px;
        }
        
        .output-preview {
            font-family: 'SF Mono', Monaco, monospace;
            font-size: 10px;
            background: #f8f9fa;
            padding: 8px;
            border-radius: 10px;
            margin-top: 10px;
            max-height: 60px;
            overflow-y: auto;
            color: #495057;
        }
        
        .card-actions-dropdown {
            position: relative;
        }
        
        .dropdown-toggle-actions {
            background: none;
            border: none;
            padding: 8px;
            border-radius: 8px;
            color: #6c757d;
            transition: all 0.2s;
            cursor: pointer;
        }
        
        .dropdown-toggle-actions:hover {
            background: #f8f9fa;
            color: #007aff;
        }
        
        .dropdown-menu-actions {
            position: absolute;
            right: 0;
            top: 100%;
            background: white;
            border-radius: 12px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
            border: 1px solid #e9ecef;
            min-width: 160px;
            z-index: 100;
            display: none;
        }
        
        .dropdown-menu-actions.show {
            display: block;
        }
        
        .dropdown-menu-actions a {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            color: #1c1c1e;
            text-decoration: none;
            font-size: 13px;
            transition: background 0.2s;
        }
        
        .dropdown-menu-actions a:hover {
            background: #f8f9fa;
        }
        
        .dropdown-menu-actions a i {
            width: 18px;
            font-size: 14px;
        }
        
        .dropdown-divider {
            height: 1px;
            background: #e9ecef;
            margin: 4px 0;
        }
        
        .btn-apple {
            background: #007aff;
            border: none;
            border-radius: 12px;
            padding: 10px 20px;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.2s ease;
            color: white;
            display: inline-flex;
            align-items: center;
            gap: 8px;
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
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-apple-outline:hover {
            background: #007aff10;
            transform: scale(0.98);
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
            padding: 20px 24px;
        }
        
        .modal-apple .modal-body {
            padding: 24px;
        }
        
        .modal-apple .modal-footer {
            border-top: 1px solid #e9ecef;
            padding: 16px 24px;
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
        
        .cron-preset {
            font-size: 11px;
            cursor: pointer;
            display: inline-block;
            margin-right: 6px;
            margin-bottom: 6px;
            padding: 4px 10px;
            border-radius: 20px;
            background: #e9ecef;
            color: #495057;
            transition: all 0.2s;
        }
        
        .cron-preset:hover {
            background: #007aff;
            color: white;
        }
        
        .alert-apple {
            border-radius: 14px;
            border: none;
            background: white;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
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
            z-index: 9999;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 20px;
        }
        
        .empty-state i {
            font-size: 48px;
            color: #c6c6c8;
            margin-bottom: 16px;
        }
        
        .help-box {
            background: linear-gradient(135deg, #f8f9fa, #ffffff);
            border-radius: 20px;
            padding: 20px;
            margin-top: 24px;
        }
        
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 44px;
            height: 24px;
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
            border-radius: 24px;
        }
        
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 20px;
            width: 20px;
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
        
        .log-viewer {
            max-height: 400px;
            overflow-y: auto;
            background: #1c1c1e;
            color: #e5e5ea;
            font-family: 'SF Mono', Monaco, monospace;
            font-size: 11px;
            padding: 16px;
            border-radius: 12px;
        }
        
        .log-line {
            font-family: monospace;
            white-space: pre-wrap;
            word-break: break-all;
            border-bottom: 1px solid #2a2a2e;
            padding: 4px 0;
        }
        
        .tabs {
            display: flex;
            gap: 8px;
            border-bottom: 1px solid #e9ecef;
            margin-bottom: 20px;
        }
        
        .tab-btn {
            padding: 10px 20px;
            background: none;
            border: none;
            font-weight: 500;
            color: #6c757d;
            cursor: pointer;
            transition: all 0.2s;
            border-radius: 8px 8px 0 0;
        }
        
        .tab-btn.active {
            color: #007aff;
            border-bottom: 2px solid #007aff;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .runner-status {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
		
        .script-select {
            font-family: 'SF Mono', Monaco, monospace;
            font-size: 13px;
        }
        
        .script-list-item {
            cursor: pointer;
            padding: 8px 12px;
            border-radius: 8px;
            transition: all 0.2s;
        }
        
        .script-list-item:hover {
            background-color: #f8f9fa;
        }
        
        .script-list-item.selected {
            background-color: #007aff20;
            border-left: 3px solid #007aff;
        }
        
        .code-editor {
            font-family: 'SF Mono', Monaco, monospace;
            font-size: 12px;
            line-height: 1.5;
            background: #1c1c1e;
            color: #e5e5ea;
            padding: 16px;
            border-radius: 12px;
            min-height: 400px;
        }
        
        .code-editor:focus {
            outline: none;
            box-shadow: 0 0 0 2px #007aff;
        }
        
        .type-selector {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 12px;
        }
        
        .type-selector label {
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .command-input-group {
            transition: all 0.3s ease;
        }
        
        .script-selector-group {
            transition: all 0.3s ease;
        }
        
        .file-badge {
            font-family: monospace;
            font-size: 11px;
        }
		
		.widget-header {
			margin-left: 15px;
			margin-top: 5px;
		}
		
		.widget-body {
			margin-left: 10px;
			margin-right: 10px;
			margin-top: 10px;
			margin-bottom: 10px;
		}
    </style>
</head>
<body>
<div id="applePreloader" class="apple-preloader">
    <div class="apple-spinner"></div>
    <div class="apple-spinner-text"><?php echo $lang12; ?></div>
</div>

<div class="top-bar">
    <div class="top-bar-left">
        <h1><i class="fas fa-bucket"></i> Mini-B</h1>
    </div>
    <div class="top-bar-right">
	<i class="bi bi-clock-history"></i> Cron Jobs
        <div class="host-selector" style="margin-right: 15px;">
            <select id="hostSelector" style="background: rgba(255,255,255,0.9); border: 1px solid #ddd; border-radius: 20px; padding: 6px 30px 6px 15px; font-size: 14px; cursor: pointer;">
                <option value=""><?php echo $lang12; ?></option>
            </select>
        </div>
		<button class="btn-apple" onclick="openCronModal()">
                <i class="bi bi-plus-lg"></i> <?php echo $lang4187; ?>
            </button>
        <i class="fas fa-sync-alt refresh-btn text-muted" onclick="refreshAllData()" title="<?php echo $lang4188; ?>"></i>
    </div>
</div>

<div class="app-container">
    <?php echo $menu; ?>
    
    <main class="main-content">
        <div id="alertContainer"></div>
        
        <div class="tabs">
            <button class="tab-btn active" data-tab="jobs"><?php echo $lang4189; ?></button>
            <button class="tab-btn" data-tab="scripts"><?php echo $lang4190; ?></button>
            <button class="tab-btn" data-tab="logs"><?php echo $lang4191; ?></button>
            <button class="tab-btn" data-tab="settings"><?php echo $lang4192; ?></button>
        </div>
        
        <div id="jobsTab" class="tab-content active">
            <div id="jobsContainer"></div>
        </div>
        
        <div id="scriptsTab" class="tab-content">
            <div class="apple-card">
                <div class="widget-header">
                    <h3><i class="bi bi-file-code"></i> <?php echo $lang4193; ?></h3>
                    <div>
                        <button class="btn btn-primary btn-sm" onclick="openScriptModal()">
                            <i class="bi bi-plus-lg"></i> <?php echo $lang4194; ?>
                        </button>
                        <button class="btn btn-outline-secondary btn-sm" onclick="refreshScripts()">
                            <i class="bi bi-arrow-repeat"></i> <?php echo $lang4195; ?>
                        </button>
                    </div>
                </div>
                <div class="widget-body">
                    <div id="scriptsContainer" class="table-responsive">
                        <div class="text-center py-3"><div class="loading-spinner-sm"></div> <?php echo $lang4196; ?></div>
                    </div>
                </div>
            </div>
        </div>
        
        <div id="logsTab" class="tab-content">
            <div class="apple-card">
                <div class="widget-header">
                    <h3><i class="bi bi-file-text"></i> <?php echo $lang4197; ?></h3>
                    <div>
                        <button class="btn btn-sm btn-outline-secondary" onclick="refreshLogs()"><i class="bi bi-arrow-repeat"></i> <?php echo $lang4198; ?></button>
                        <button class="btn btn-sm btn-outline-danger" onclick="clearLogs()"><i class="bi bi-trash"></i> <?php echo $lang4199; ?></button>
                    </div>
                </div>
                <div class="widget-body">
                    <div id="logViewer" class="log-viewer">
                        <div class="text-center text-muted"><?php echo $lang4200; ?></div>
                    </div>
                </div>
            </div>
        </div>
        
        <div id="settingsTab" class="tab-content">
            <div class="apple-card">
                <div class="widget-header">
                    <h3><i class="bi bi-gear"></i> <?php echo $lang4201; ?></h3>
                </div>
                <div class="widget-body">
                    <div class="runner-status" id="runnerStatus">
                        <div class="loading-spinner-sm"></div> <?php echo $lang4202; ?>
                    </div>
                    
                    <hr>
                    
                    <h6 class="mt-3"><?php echo $lang4203; ?></h6>
                    <p><code id="scriptsDirDisplay"></code></p>
                    
                    <hr>
                    
                    <h6 class="mt-3"><?php echo $lang4204; ?></h6>
                    <p><?php echo $lang4205; ?> (<code>crontab -e</code>):</p>
                    <pre id="runnerCommandDisplay" class="bg-dark text-light p-3 rounded" style="overflow-x: auto;"></pre>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Modal for Add/Edit Cron Job -->
<div class="modal fade modal-apple" id="cronModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-semibold"><i class="bi bi-clock me-2"></i><span id="modalTitle"><?php echo $lang4206; ?></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="cronForm">
                    <input type="hidden" id="editUniqueId" name="unique_id" value="">
                    
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold"><?php echo $lang4207; ?> <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-apple" id="job_name" name="job_name" placeholder="e.g., Database Backup" required>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label fw-semibold"><?php echo $lang4208; ?></label>
                            <textarea class="form-control form-control-apple" id="comment" name="comment" rows="2" placeholder="<?php echo $lang4209; ?>"></textarea>
                        </div>
                        
                        <!-- Type Selector -->
                        <div class="col-12 type-selector">
                            <label>
                                <input type="radio" name="job_type" value="command" checked onchange="toggleJobType()">
                                <i class="bi bi-terminal"></i> <?php echo $lang4210; ?>
                            </label>
                            <label>
                                <input type="radio" name="job_type" value="script" onchange="toggleJobType()">
                                <i class="bi bi-file-code"></i> <?php echo $lang4211; ?>
                            </label>
                        </div>
                        
                        <!-- Command Input -->
                        <div class="col-12 command-input-group" id="commandGroup">
                            <label class="form-label fw-semibold"><?php echo $lang4212; ?> <span class="text-danger">*</span></label>
                            <textarea class="form-control form-control-apple" id="command" name="command" rows="3" placeholder="/usr/bin/php /path/to/script.php"></textarea>
                        </div>
                        
                        <!-- Script Selector -->
                        <div class="col-12 script-selector-group" id="scriptGroup" style="display: none;">
                            <label class="form-label fw-semibold"><?php echo $lang4213; ?> <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <select class="form-select form-select-apple" id="script_name" name="script_name">
                                    <option value=""><?php echo $lang4214; ?></option>
                                </select>
                                <button type="button" class="btn btn-outline-primary" onclick="openScriptModalForSelect()">
                                    <i class="bi bi-plus-lg"></i> <?php echo $lang4215; ?>
                                </button>
                                <button type="button" class="btn btn-outline-secondary" onclick="editSelectedScript()">
                                    <i class="bi bi-pencil"></i> <?php echo $lang4216; ?>
                                </button>
                            </div>
                            <small class="text-muted mt-1 d-block"><?php echo $lang4217; ?> <code>/var/www/minib/cron/userscripts/</code></small>
                        </div>
                        
                        <!-- Cron Schedule -->
                        <div class="row g-2 mt-2">
                            <div class="col-md-2">
                                <label class="form-label small fw-semibold"><?php echo $lang4218; ?></label>
                                <input type="text" class="form-control form-control-apple" id="minute" name="minute" value="*" placeholder="*">
                                <small class="text-muted" style="font-size: 9px;">0-59,*</small>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-semibold"><?php echo $lang4219; ?></label>
                                <input type="text" class="form-control form-control-apple" id="hour" name="hour" value="*" placeholder="*">
                                <small class="text-muted" style="font-size: 9px;">0-23,*</small>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-semibold"><?php echo $lang4220; ?></label>
                                <input type="text" class="form-control form-control-apple" id="day" name="day" value="*" placeholder="*">
                                <small class="text-muted" style="font-size: 9px;">1-31,*</small>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-semibold"><?php echo $lang4221; ?></label>
                                <input type="text" class="form-control form-control-apple" id="month" name="month" value="*" placeholder="*">
                                <small class="text-muted" style="font-size: 9px;">1-12,*</small>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-semibold"><?php echo $lang4222; ?></label>
                                <input type="text" class="form-control form-control-apple" id="weekday" name="weekday" value="*" placeholder="*">
                                <small class="text-muted" style="font-size: 9px;">0-7,*</small>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-semibold"><?php echo $lang4223; ?></label>
                                <div class="mt-2">
                                    <label class="toggle-switch">
                                        <input type="checkbox" id="enabled" name="enabled" checked>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <hr class="my-3">
                    
                    <div>
                        <strong class="small"><?php echo $lang4224; ?></strong><br>
                        <span class="cron-preset" onclick="setCronPreset('*/5', '*', '*', '*', '*')"><?php echo $lang4225; ?></span>
                        <span class="cron-preset" onclick="setCronPreset('0', '*', '*', '*', '*')"><?php echo $lang4226; ?></span>
                        <span class="cron-preset" onclick="setCronPreset('0', '0', '*', '*', '*')"><?php echo $lang4227; ?></span>
                        <span class="cron-preset" onclick="setCronPreset('0', '2', '*', '*', '1')"><?php echo $lang4228; ?></span>
                        <span class="cron-preset" onclick="setCronPreset('0', '*/2', '*', '*', '*')"><?php echo $lang4229; ?></span>
                        <span class="cron-preset" onclick="setCronPreset('30', '9', '*', '*', '1-5')"><?php echo $lang4230; ?></span>
                    </div>
                    
                    <div class="mt-3 p-3 bg-light rounded-3">
                        <strong><?php echo $lang4231; ?></strong>
                        <code id="schedulePreview" class="d-block mt-1">* * * * *</code>
                        <span id="humanPreview" class="small text-muted"></span>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary rounded-3" data-bs-dismiss="modal"><?php echo $lang4232; ?></button>
                <button type="button" class="btn-apple" onclick="saveCronJob()">
                    <i class="bi bi-save"></i> <?php echo $lang4233; ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal for Script Editor -->
<div class="modal fade modal-apple" id="scriptModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-semibold"><i class="bi bi-file-code me-2"></i><span id="scriptModalTitle"><?php echo $lang4234; ?></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold"><?php echo $lang4235; ?> <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-apple" id="script_filename" placeholder="script.sh">
                        <small class="text-muted"><?php echo $lang4236; ?> (.sh, .php, .py, .js, .rb)</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold"><?php echo $lang4237; ?></label>
                        <select class="form-select form-select-apple" id="script_template" onchange="loadTemplate()">
                            <option value="bash">Bash (.sh)</option>
                            <option value="php">PHP (.php)</option>
                            <option value="python">Python (.py)</option>
                            <option value="node">Node.js (.js)</option>
                            <option value="ruby">Ruby (.rb)</option>
                            <option value="empty"><?php echo $lang4238; ?></option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold"><?php echo $lang4239; ?></label>
                        <textarea id="script_content" class="code-editor" rows="15" style="width: 100%; font-family: monospace;"></textarea>
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="script_executable" checked>
                            <label class="form-check-label" for="script_executable">
                                <?php echo $lang4240; ?>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" id="deleteScriptBtn" onclick="deleteCurrentScript()" style="display: none;">
                    <i class="bi bi-trash"></i> <?php echo $lang4241; ?>
                </button>
                <button type="button" class="btn btn-secondary rounded-3" data-bs-dismiss="modal"><?php echo $lang4242; ?></button>
                <button type="button" class="btn-apple" onclick="saveScript()">
                    <i class="bi bi-save"></i> <?php echo $lang4243; ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal for Full Output -->
<div class="modal fade modal-apple" id="outputModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-semibold"><i class="bi bi-code-square"></i> <?php echo $lang4244; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <pre id="fullOutput" style="white-space: pre-wrap; word-wrap: break-word; max-height: 400px; overflow-y: auto; background: #1c1c1e; color: #e5e5ea; padding: 16px; border-radius: 12px; font-family: monospace; font-size: 12px;"></pre>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary rounded-3" data-bs-dismiss="modal"><?php echo $lang4245; ?></button>
            </div>
        </div>
    </div>
</div>

<script src="lib/jquery-3.6.0-master/dist/jquery.min.js"></script>
<script src="lib/bootstrap-5.3.8-dist/js/bootstrap.bundle.min.js"></script>
<script src="js/loader.js"></script>
<script src="js/hosts_load.js"></script>

<script>
window.apiConfig = <?php echo json_encode($js_config); ?>;

let currentModal = null;
let outputModal = null;
let scriptModal = null;
let currentJobs = [];
let currentScripts = [];
let scriptSelectCallback = null;

// ========== API Calls ==========
async function apiCall(action, method = 'GET', data = null) {
    let fullUrl = `${window.apiConfig.apiBaseUrl}cron_api.php?action=${action}`;
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

// ========== UI Helpers ==========
function showLoading() {
    if (!$('.loading-overlay').length) {
        $('body').append('<div class="loading-overlay"><div class="spinner-border text-light" style="width: 3rem; height: 3rem;"></div></div>');
    }
    $('.loading-overlay').show();
}

function hideLoading() {
    $('.loading-overlay').hide();
}

function showAlert(message, type = 'success') {
    const alertHtml = `<div class="alert alert-${type} alert-dismissible fade show alert-apple mb-3" role="alert">
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'}"></i> ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>`;
    $('#alertContainer').append(alertHtml);
    setTimeout(() => $('.alert').fadeOut(500, function() { $(this).remove(); }), 5000);
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/[&<>]/g, function(m) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;' }[m];
    });
}

function updatePreview() {
    const minute = $('#minute').val() || '*';
    const hour = $('#hour').val() || '*';
    const day = $('#day').val() || '*';
    const month = $('#month').val() || '*';
    const weekday = $('#weekday').val() || '*';
    $('#schedulePreview').text(`${minute} ${hour} ${day} ${month} ${weekday}`);
    
    let desc = [];
    if (minute === '0') desc.push('at minute 0');
    else if (minute === '*') desc.push('every minute');
    else if (minute.startsWith('*/')) desc.push('every ' + minute.substring(2) + ' min');
    else desc.push('min ' + minute);
    
    if (hour === '*') desc.push('every hour');
    else if (hour.startsWith('*/')) desc.push('every ' + hour.substring(2) + ' hours');
    else if (hour.includes('-')) desc.push('hours ' + hour);
    else desc.push(hour + ':00');
    
    if (day !== '*') desc.push('day ' + day);
    if (month !== '*') desc.push('month ' + month);
    if (weekday !== '*') {
        const weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        desc.push(weekdays[parseInt(weekday)] || weekday);
    }
    $('#humanPreview').text(desc.join(', '));
}

function setCronPreset(minute, hour, day, month, weekday) {
    if (minute) $('#minute').val(minute);
    if (hour) $('#hour').val(hour);
    if (day) $('#day').val(day);
    if (month) $('#month').val(month);
    if (weekday) $('#weekday').val(weekday);
    updatePreview();
}

function toggleJobType() {
    const jobType = $('input[name="job_type"]:checked').val();
    if (jobType === 'command') {
        $('#commandGroup').show();
        $('#scriptGroup').hide();
        $('#command').prop('required', true);
        $('#script_name').prop('required', false);
    } else {
        $('#commandGroup').hide();
        $('#scriptGroup').show();
        $('#command').prop('required', false);
        $('#script_name').prop('required', true);
        loadScriptsForSelect();
    }
}

function toggleDropdown(btn) {
    $('.dropdown-menu-actions').not($(btn).next()).removeClass('show');
    $(btn).next('.dropdown-menu-actions').toggleClass('show');
}

// ========== Script Management ==========
async function loadScripts() {
    let result = await apiCall('get_scripts');
    if (result.success) {
        currentScripts = result.data;
        renderScripts();
    } else {
        $('#scriptsContainer').html(`<div class="text-center text-danger"><?php echo $lang4246; ?> ${result.error}</div>`);
    }
}

function renderScripts() {
    if (currentScripts.length === 0) {
        $('#scriptsContainer').html(`
            <div class="empty-state">
                <i class="bi bi-file-code"></i>
                <h5><?php echo $lang4247; ?></h5>
                <p class="text-muted"><?php echo $lang4248; ?></p>
                <button class="btn-apple" onclick="openScriptModal()">
                    <i class="bi bi-plus-lg"></i> <?php echo $lang4249; ?>
                </button>
            </div>
        `);
        return;
    }
    
    let html = `<table class="table table-hover">
        <thead>
            <tr><th><?php echo $lang4250; ?></th><th><?php echo $lang4251; ?></th><th><?php echo $lang4252; ?></th><th><?php echo $lang4253; ?></th><th><?php echo $lang4254; ?></th></tr>
        </thead>
        <tbody>`;
    
    currentScripts.forEach(script => {
        const extBadge = script.extension ? `<span class="badge bg-secondary">${escapeHtml(script.extension)}</span>` : '';
        const executableBadge = script.is_executable ? '<span class="badge bg-success"><?php echo $lang4255; ?></span>' : '<span class="badge bg-warning"><?php echo $lang4256; ?></span>';
        
        html += `<tr>
            <td><code>${escapeHtml(script.name)}</code> ${extBadge}</td>
            <td>${formatBytes(script.size)}</td>
            <td>${escapeHtml(script.modified)}</td>
            <td>${script.permissions} ${executableBadge}</td>
            <td>
                <button class="btn btn-sm btn-outline-primary" onclick="editScript('${escapeHtml(script.name)}')"><i class="bi bi-pencil"></i></button>
                <button class="btn btn-sm btn-outline-danger" onclick="deleteScript('${escapeHtml(script.name)}')"><i class="bi bi-trash"></i></button>
                ${script.is_executable ? `<button class="btn btn-sm btn-outline-success" onclick="testScript('${escapeHtml(script.name)}')"><i class="bi bi-play-fill"></i> <?php echo $lang4257; ?></button>` : ''}
            </td>
        </tr>`;
    });
    
    html += `</tbody></table>`;
    $('#scriptsContainer').html(html);
}

function formatBytes(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

async function loadScriptsForSelect() {
    let result = await apiCall('get_scripts');
    if (result.success) {
        const select = $('#script_name');
        const currentVal = select.val();
        select.empty();
        select.append('<option value=""><?php echo $lang4258; ?></option>');
        result.data.forEach(script => {
            if (script.is_executable) {
                select.append(`<option value="${escapeHtml(script.name)}">${escapeHtml(script.name)}</option>`);
            }
        });
        if (currentVal) select.val(currentVal);
    }
}

function openScriptModalForSelect() {
    scriptSelectCallback = true;
    openScriptModal();
}

function openScriptModal(scriptName = null) {
    $('#scriptModalTitle').text(scriptName ? '<?php echo $lang4259; ?>' : '<?php echo $lang4260; ?>');
    $('#script_filename').val(scriptName || '');
    $('#script_content').val('');
    $('#deleteScriptBtn').toggle(!!scriptName);
    $('#script_template').val('bash');
    
    if (scriptName) {
        loadScriptContent(scriptName);
    } else {
        loadTemplate();
    }
    
    scriptModal = new bootstrap.Modal(document.getElementById('scriptModal'));
    scriptModal.show();
}

async function loadScriptContent(filename) {
    showLoading();
    let result = await apiCall('get_script', 'GET', { filename: filename });
    hideLoading();
    
    if (result.success) {
        $('#script_content').val(result.content);
        const ext = filename.split('.').pop();
        if (ext === 'sh') $('#script_template').val('bash');
        else if (ext === 'php') $('#script_template').val('php');
        else if (ext === 'py') $('#script_template').val('python');
        else if (ext === 'js') $('#script_template').val('node');
        else if (ext === 'rb') $('#script_template').val('ruby');
        else $('#script_template').val('bash');
    } else {
        showAlert(result.error, 'danger');
    }
}

async function loadTemplate() {
    const type = $('#script_template').val();
    const filename = $('#script_filename').val() || 'script';
    const name = filename.split('.')[0];
    
    if (type === 'empty') {
        $('#script_content').val('');
        return;
    }
    
    let result = await apiCall('get_script_template', 'GET', { type: type, name: name });
    if (result.success) {
        $('#script_content').val(result.template);
    }
}

async function saveScript() {
    const filename = $('#script_filename').val().trim();
    const content = $('#script_content').val();
    const makeExecutable = $('#script_executable').is(':checked');
    
    if (!filename) {
        showAlert('<?php echo $lang4261; ?>', 'danger');
        return;
    }
    
    showLoading();
    let result = await apiCall('save_script', 'POST', {
        filename: filename,
        content: content,
        make_executable: makeExecutable ? '1' : '0'
    });
    hideLoading();
    
    if (result.success) {
        showAlert(result.message, 'success');
        scriptModal.hide();
        await loadScripts();
        await loadScriptsForSelect();
        
        if (scriptSelectCallback) {
            scriptSelectCallback = false;
            $('#script_name').val(filename);
        }
    } else {
        showAlert(result.error, 'danger');
    }
}

async function editSelectedScript() {
    const scriptName = $('#script_name').val();
    if (!scriptName) {
        showAlert('<?php echo $lang4262; ?>', 'warning');
        return;
    }
    openScriptModal(scriptName);
}

async function editScript(filename) {
    openScriptModal(filename);
}

async function deleteScript(filename) {
    if (!confirm(`<?php echo $lang4263; ?> "${filename}"?`)) return;
    
    showLoading();
    let result = await apiCall('delete_script', 'GET', { filename: filename });
    hideLoading();
    
    if (result.success) {
        showAlert(result.message, 'success');
        await loadScripts();
        await loadScriptsForSelect();
    } else {
        showAlert(result.error, 'danger');
    }
}

async function deleteCurrentScript() {
    const filename = $('#script_filename').val();
    if (!filename) return;
    
    if (!confirm(`<?php echo $lang4264; ?> "${filename}"?`)) return;
    
    showLoading();
    let result = await apiCall('delete_script', 'GET', { filename: filename });
    hideLoading();
    
    if (result.success) {
        showAlert(result.message, 'success');
        scriptModal.hide();
        await loadScripts();
        await loadScriptsForSelect();
    } else {
        showAlert(result.error, 'danger');
    }
}

async function testScript(filename) {
    showLoading();
    showAlert('<?php echo $lang4265; ?>', 'info');
    
    let result = await apiCall('run_script', 'GET', { filename: filename });
    hideLoading();
    
    if (result.success) {
        showAlert(result.message, result.status === 'success' ? 'success' : 'warning');
        if (result.output) {
            $('#fullOutput').text(result.output);
            outputModal = new bootstrap.Modal(document.getElementById('outputModal'));
            outputModal.show();
        }
    } else {
        showAlert(result.error || '<?php echo $lang4266; ?>', 'danger');
    }
}

function refreshScripts() {
    loadScripts();
}

// ========== Load Jobs ==========
async function loadJobs() {
    let result = await apiCall('get_jobs');
    if (result.success) {
        currentJobs = result.data;
        renderJobs();
    } else {
        $('#jobsContainer').html(`<div class="empty-state"><i class="bi bi-calendar2-x"></i><h5><?php echo $lang4267; ?></h5><p class="text-muted">${result.error || 'Unknown error'}</p></div>`);
    }
}

function renderJobs() {
    if (currentJobs.length === 0) {
        $('#jobsContainer').html(`
            <div class="empty-state">
                <i class="bi bi-calendar2-x"></i>
                <h5><?php echo $lang4268; ?></h5>
                <p class="text-muted"><?php echo $lang4269; ?></p>
                <button class="btn-apple" onclick="openCronModal()">
                    <i class="bi bi-plus-lg"></i> <?php echo $lang4270; ?>
                </button>
            </div>
        `);
        return;
    }
    
    let html = '<div class="row g-4">';
    currentJobs.forEach(job => {
        const next_run = job.next_run || (job.enabled ? 'Calculating...' : 'Disabled');
        const last_status_class = job.last_status === 'success' ? 'status-success' : (job.last_status === 'failed' ? 'status-failed' : 'status-pending');
        const last_status_icon = job.last_status === 'success' ? 'bi-check-circle' : (job.last_status === 'failed' ? 'bi-exclamation-circle' : 'bi-question-circle');
        
        // Отображаем что выполняется
        const execDisplay = job.job_type === 'script' 
            ? `<span class="badge bg-info"><i class="bi bi-file-code"></i> <?php echo $lang4271; ?> ${escapeHtml(job.script_name)}</span>`
            : `<span class="badge bg-secondary"><i class="bi bi-terminal"></i> <?php echo $lang4272; ?></span>`;
        
        html += `
            <div class="col-md-6 col-lg-4" data-job-id="${escapeHtml(job.unique_id)}">
                <div class="job-card ${job.enabled ? 'enabled' : 'disabled'}">
                    <div class="job-card-header">
                        <div>
                            <div class="job-name">
                                ${job.enabled ? '<span class="status-badge status-success"><i class="bi bi-play-fill"></i> <?php echo $lang4273; ?></span>' : '<span class="status-badge status-pending"><i class="bi bi-pause-fill"></i> <?php echo $lang4274; ?></span>'}
                                ${job.last_status !== 'pending' && job.enabled ? `<span class="status-badge ${last_status_class}"><i class="${last_status_icon}"></i> ${job.last_status === 'success' ? 'OK' : 'Failed'}</span>` : ''}
                                ${execDisplay}
                            </div>
                            <div class="fw-semibold fs-6 mt-1">${escapeHtml(job.job_name)}</div>
                            ${job.comment ? `<div class="job-comment">${escapeHtml(job.comment)}</div>` : ''}
                        </div>
                        <div class="card-actions-dropdown">
                            <button class="dropdown-toggle-actions" onclick="toggleDropdown(this)">
                                <i class="bi bi-three-dots-vertical"></i>
                            </button>
                            <div class="dropdown-menu-actions">
                                <a href="#" onclick="runJob('${escapeHtml(job.unique_id)}'); return false;">
                                    <i class="bi bi-play-fill"></i> <?php echo $lang4275; ?>
                                </a>
                                <a href="#" onclick="toggleJob('${escapeHtml(job.unique_id)}'); return false;">
                                    <i class="bi bi-${job.enabled ? 'pause-fill' : 'play-fill'}"></i> ${job.enabled ? '<?php echo $lang4276; ?>' : '<?php echo $lang4277; ?>'}
                                </a>
                                <a href="#" onclick="editJob('${escapeHtml(job.unique_id)}'); return false;">
                                    <i class="bi bi-pencil"></i> <?php echo $lang4278; ?>
                                </a>
                                <div class="dropdown-divider"></div>
                                <a href="#" onclick="deleteJob('${escapeHtml(job.unique_id)}'); return false;" style="color: #ff3b30;">
                                    <i class="bi bi-trash3"></i> <?php echo $lang4279; ?>
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="job-card-body">
                        <div class="schedule-code">
                            <i class="bi bi-calendar3 me-1"></i>
                            <code>${escapeHtml(job.minute)} ${escapeHtml(job.hour)} ${escapeHtml(job.day)} ${escapeHtml(job.month)} ${escapeHtml(job.weekday)}</code>
                        </div>
                        <div class="command-code">
                            <i class="bi bi-${job.job_type === 'script' ? 'file-code' : 'terminal'} me-1"></i>
                            <code>${escapeHtml(job.job_type === 'script' ? job.script_name : job.command)}</code>
                        </div>
                        <div class="run-time">
                            <i class="bi bi-hourglass-split"></i>
                            <span><?php echo $lang4280; ?> <strong>${escapeHtml(next_run)}</strong></span>
                        </div>
                        ${job.last_run ? `<div class="run-time"><i class="bi bi-clock-history"></i><span><?php echo $lang4281; ?> ${escapeHtml(job.last_run)}</span></div>` : ''}
                        ${job.last_output ? `
                            <div class="output-preview">
                                <i class="bi bi-code-square"></i> <?php echo $lang4282; ?><br>
                                <code>${escapeHtml(job.last_output.substring(0, 120))}</code>
                                ${job.last_output.length > 120 ? `<button class="btn btn-link btn-sm p-0 mt-1" onclick="showFullOutput('${escapeHtml(job.unique_id)}')" style="font-size: 10px;"><?php echo $lang4283; ?></button>` : ''}
                            </div>
                        ` : ''}
                    </div>
                    <div class="job-card-footer">
                        <i class="bi bi-calendar-plus"></i> <?php echo $lang4284; ?> ${escapeHtml(job.created_at)}
                    </div>
                </div>
            </div>
        `;
    });
    html += '</div>';
    $('#jobsContainer').html(html);
}

// ========== Job CRUD Operations ==========
function openCronModal() {
    $('#modalTitle').text('<?php echo $lang4285; ?>');
    $('#cronForm')[0].reset();
    $('#minute').val('*');
    $('#hour').val('*');
    $('#day').val('*');
    $('#month').val('*');
    $('#weekday').val('*');
    $('#enabled').prop('checked', true);
    $('#editUniqueId').val('');
    $('input[name="job_type"][value="command"]').prop('checked', true);
    $('#commandGroup').show();
    $('#scriptGroup').hide();
    updatePreview();
    currentModal = new bootstrap.Modal(document.getElementById('cronModal'));
    currentModal.show();
}

async function editJob(uniqueId) {
    showLoading();
    let result = await apiCall('get_job', 'GET', { unique_id: uniqueId });
    hideLoading();
    
    if (result.success) {
        const job = result.data;
        $('#modalTitle').text('Edit Cron Job');
        $('#editUniqueId').val(job.unique_id);
        $('#job_name').val(job.job_name);
        $('#comment').val(job.comment || '');
        $('#minute').val(job.minute);
        $('#hour').val(job.hour);
        $('#day').val(job.day);
        $('#month').val(job.month);
        $('#weekday').val(job.weekday);
        $('#enabled').prop('checked', job.enabled);
        
        $(`input[name="job_type"][value="${job.job_type}"]`).prop('checked', true);
        
        if (job.job_type === 'command') {
            $('#command').val(job.command);
            $('#commandGroup').show();
            $('#scriptGroup').hide();
        } else {
            $('#commandGroup').hide();
            $('#scriptGroup').show();
            await loadScriptsForSelect();
            $('#script_name').val(job.script_name);
        }
        
        updatePreview();
        currentModal = new bootstrap.Modal(document.getElementById('cronModal'));
        currentModal.show();
    } else {
        showAlert(result.error || '<?php echo $lang4286; ?>', 'danger');
    }
}

async function saveCronJob() {
    const jobName = $('#job_name').val().trim();
    const jobType = $('input[name="job_type"]:checked').val();
    
    if (!jobName) { showAlert('<?php echo $lang4287; ?>', 'danger'); return; }
    
    if (jobType === 'command') {
        const command = $('#command').val().trim();
        if (!command) { showAlert('<?php echo $lang4288; ?>', 'danger'); return; }
    } else {
        const scriptName = $('#script_name').val();
        if (!scriptName) { showAlert('<?php echo $lang4289; ?>', 'danger'); return; }
    }
    
    showLoading();
    
    let result = await apiCall('save_job', 'POST', $('#cronForm').serialize());
    hideLoading();
    
    if (result.success) {
        if (currentModal) currentModal.hide();
        showAlert(result.message, 'success');
        await loadJobs();
    } else {
        showAlert(result.error || '<?php echo $lang4290; ?>', 'danger');
    }
}

async function deleteJob(uniqueId) {
    if (!confirm('<?php echo $lang4291; ?>')) return;
    showLoading();
    let result = await apiCall('delete_job', 'GET', { unique_id: uniqueId });
    hideLoading();
    
    if (result.success) {
        showAlert(result.message, 'success');
        await loadJobs();
    } else {
        showAlert(result.error || '<?php echo $lang4292; ?>', 'danger');
    }
}

async function toggleJob(uniqueId) {
    showLoading();
    let result = await apiCall('toggle_job', 'GET', { unique_id: uniqueId });
    hideLoading();
    
    if (result.success) {
        showAlert(result.message, 'success');
        await loadJobs();
    } else {
        showAlert(result.error || '<?php echo $lang4293; ?>', 'danger');
    }
}

async function runJob(uniqueId) {
    $('.dropdown-menu-actions').removeClass('show');
    showLoading();
    showAlert('<?php echo $lang4294; ?>', 'info');
    
    let result = await apiCall('run_job', 'GET', { unique_id: uniqueId });
    hideLoading();
    
    if (result.success) {
        showAlert(result.message, result.status === 'success' ? 'success' : 'warning');
        if (result.plain_output) {
            $('#fullOutput').text(result.plain_output);
            outputModal = new bootstrap.Modal(document.getElementById('outputModal'));
            outputModal.show();
        }
        await loadJobs();
    } else {
        showAlert(result.error || '<?php echo $lang4295; ?>', 'danger');
    }
}

async function showFullOutput(uniqueId) {
    showLoading();
    let result = await apiCall('get_job', 'GET', { unique_id: uniqueId });
    hideLoading();
    
    if (result.success && result.data.last_output) {
        $('#fullOutput').text(result.data.last_output);
        outputModal = new bootstrap.Modal(document.getElementById('outputModal'));
        outputModal.show();
    } else {
        showAlert('<?php echo $lang4296; ?>', 'info');
    }
}

// ========== Logs ==========
async function loadLogs() {
    let result = await apiCall('get_logs', 'GET', { lines: 100 });
    if (result.success && result.logs) {
        if (result.logs.length === 0) {
            $('#logViewer').html('<div class="text-center text-muted"><?php echo $lang4297; ?></div>');
        } else {
            let html = '';
            result.logs.forEach(log => {
                html += `<div class="log-line">${escapeHtml(log)}</div>`;
            });
            $('#logViewer').html(html);
        }
    } else {
        $('#logViewer').html('<div class="text-center text-danger"><?php echo $lang4298; ?></div>');
    }
}

async function refreshLogs() {
    showLoading();
    await loadLogs();
    hideLoading();
    showAlert('<?php echo $lang4299; ?>', 'success');
}

async function clearLogs() {
    if (!confirm('<?php echo $lang4300; ?>')) return;
    showLoading();
    let result = await apiCall('clear_logs');
    hideLoading();
    if (result.success) {
        showAlert('<?php echo $lang4301; ?>', 'success');
        await loadLogs();
    } else {
        showAlert(result.error || '<?php echo $lang4302; ?>', 'danger');
    }
}

// ========== Runner Settings ==========
async function loadRunnerStatus() {
    let result = await apiCall('get_runner_status');
    if (result.success) {
        let statusHtml = `
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <div>
                    <strong><?php echo $lang4303; ?></strong> 
                    <span class="badge ${result.runner_exists ? 'bg-success' : 'bg-danger'}">${result.runner_exists ? '<?php echo $lang4304; ?>' : '<?php echo $lang4305; ?>'}</span>
                </div>
                <div>
                    <strong><?php echo $lang4306; ?></strong> 
                    <span class="badge ${result.runner_installed ? 'bg-success' : 'bg-warning'}">${result.runner_installed ? '<?php echo $lang4307; ?>' : '<?php echo $lang4308; ?>'}</span>
                </div>
                ${!result.runner_installed ? `<button class="btn btn-sm btn-primary" onclick="installRunner()"><i class="bi bi-download"></i> <?php echo $lang4309; ?></button>` : `<button class="btn btn-sm btn-danger" onclick="uninstallRunner()"><i class="bi bi-x-circle"></i> <?php echo $lang4310; ?></button>`}
            </div>
        `;
        $('#runnerStatus').html(statusHtml);
        $('#runnerCommandDisplay').text(result.runner_command);
        $('#scriptsDirDisplay').text(result.scripts_dir || '/var/www/minib/cron/userscripts');
    } else {
        $('#runnerStatus').html('<div class="text-danger"><?php echo $lang4311; ?></div>');
    }
}

async function installRunner() {
    showLoading();
    let result = await apiCall('install_runner');
    hideLoading();
    if (result.success) {
        showAlert(result.message, 'success');
        loadRunnerStatus();
    } else {
        showAlert(result.error || '<?php echo $lang4312; ?>', 'danger');
    }
}

async function uninstallRunner() {
    if (!confirm('<?php echo $lang4313; ?>')) return;
    showLoading();
    let result = await apiCall('uninstall_runner');
    hideLoading();
    if (result.success) {
        showAlert(result.message, 'success');
        loadRunnerStatus();
    } else {
        showAlert(result.error || '<?php echo $lang4314; ?>', 'danger');
    }
}

// ========== Refresh All ==========
async function refreshAllData() {
    showLoading();
    await Promise.all([loadJobs(), loadLogs(), loadRunnerStatus(), loadScripts()]);
    hideLoading();
    showAlert('<?php echo $lang4315; ?>', 'success');
}

// ========== Tabs ==========
$('.tab-btn').on('click', function() {
    const tab = $(this).data('tab');
    $('.tab-btn').removeClass('active');
    $(this).addClass('active');
    $('.tab-content').removeClass('active');
    
    if (tab === 'jobs') {
        $('#jobsTab').addClass('active');
    } else if (tab === 'scripts') {
        $('#scriptsTab').addClass('active');
        loadScripts();
    } else if (tab === 'logs') {
        $('#logsTab').addClass('active');
        loadLogs();
    } else if (tab === 'settings') {
        $('#settingsTab').addClass('active');
        loadRunnerStatus();
    }
});

// ========== Initialization ==========
$(document).ready(function() {
    refreshAllData();
    
    $('#minute, #hour, #day, #month, #weekday').on('input change', updatePreview);
    updatePreview();
    
    setTimeout(function() { $('#applePreloader').fadeOut(500); }, 500);
});
</script>
</body>
</html>