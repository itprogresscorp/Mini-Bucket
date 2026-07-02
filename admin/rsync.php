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
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Mini-B - Rsync</title>
    <link href="lib/bootstrap-5.3.8-dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="lib/fontawesome-free-6.7.2-web/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="css/loader.css">
    <link rel="shortcut icon" href="css/icon.ico" type="image/x-icon">
	<script src="js/hosts_load.js"></script>
	<script src="js/crt_checker.js"></script>
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

        .table-module th {
            background: #f8f9fa;
            border-bottom: 2px solid #e9ecef;
            font-size: 13px;
            font-weight: 600;
        }

        .table-module td {
            font-size: 13px;
            vertical-align: middle;
        }

        .table-module-path {
            font-family: monospace;
            font-size: 12px;
            max-width: 280px;
            word-break: break-all;
            background: #f8f9fa;
            padding: 4px 8px;
            border-radius: 6px;
            display: inline-block;
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

        @media (max-width: 768px) {
            .table-responsive {
                font-size: 12px;
            }
            
            .table-module-path {
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
    <div class="apple-spinner-text"><?php echo $lang12; ?></div>
</div>

<div class="top-bar">
    <div class="top-bar-left">
        <h1><i class="fas fa-bucket"></i> Mini-B</h1>
    </div>
    <div class="top-bar-right">
        
        <div class="status-group" id="statusGroup">
            <span class="status-badge" id="rsyncRunningBadge">
                <i class="fas fa-play-circle"></i> <?php echo $lang2575; ?>
            </span>
            <span class="status-badge" id="rsyncEnabledBadge">
                <i class="fas fa-check-circle"></i> <?php echo $lang2576; ?>
            </span>
            <span class="status-badge" id="rsyncPidBadge" style="background:#e9ecef; color:#495057; display:none;">
                <i class="fas fa-microchip"></i> <?php echo $lang2577; ?> <span id="rsyncPid">-</span>
            </span>
            <span class="status-badge" id="rsyncVersionBadge" style="background:#e9ecef; color:#495057;">
                <i class="fas fa-code-branch"></i> <?php echo $lang2578; ?><span id="rsyncVersion">-</span>
            </span>
        </div>
        <div class="host-selector" style="margin-left: 20px;">
            <select id="hostSelector" style="background: rgba(255,255,255,0.9); border: 1px solid #ddd; border-radius: 20px; padding: 6px 30px 6px 15px; font-size: 14px; cursor: pointer;">
                <option value=""><?php echo $lang12; ?></option>
            </select>
        </div>
        <div class="dropdown">
            <button class="btn btn-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                <i class="fas fa-power-off"></i> <?php echo $lang2579; ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><button class="dropdown-item" onclick="serviceAction('start')"><i class="fas fa-play text-success me-2"></i> <?php echo $lang2580; ?></button></li>
                <li><button class="dropdown-item" onclick="serviceAction('stop')"><i class="fas fa-stop text-danger me-2"></i> <?php echo $lang2581; ?></button></li>
                <li><button class="dropdown-item" onclick="serviceAction('restart')"><i class="fas fa-sync-alt text-warning me-2"></i> <?php echo $lang2582; ?></button></li>
                <li><hr class="dropdown-divider"></li>
                <li><button class="dropdown-item" id="toggleAutostartBtn" onclick="toggleAutostart()"><i class="fas fa-play text-info me-2"></i> <?php echo $lang2583; ?></button></li>
            </ul>
        </div>
        
        <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#settingsModal">
            <i class="fas fa-cog"></i>
        </button>
        <i class="fas fa-sync-alt refresh-btn text-muted" onclick="refreshAllData()" title="<?php echo $lang2584; ?>"></i>
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
                        <h3><i class="fas fa-cubes text-primary"></i> <?php echo $lang2585; ?></h3>
                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addModuleModal">
                            <i class="fas fa-plus"></i> <?php echo $lang2586; ?>
                        </button>
                    </div>
                    <div class="widget-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover table-module mb-0">
                                <thead>
                                    <tr><th><?php echo $lang2587; ?></th><th><?php echo $lang2588; ?></th><th><?php echo $lang2589; ?></th><th><?php echo $lang2590; ?></th><th><?php echo $lang2591; ?></th></tr>
                                </thead>
                                <tbody id="modulesTableBody">
                                    <tr><td colspan="5" class="text-center text-muted py-4"><div class="loading-spinner-sm"></div> <?php echo $lang2592; ?></td></tr>
                                </tbody>
                             </table>
                        </div>
                        <div class="options-help">
                            <strong><i class="fas fa-info-circle"></i> <?php echo $lang2593; ?></strong>
                            <ul>
                                <li><code>rsync -avz user@server::module_name /local/path/</code> - <?php echo $lang2594; ?></li>
                                <li><code>rsync -avz /local/path/ user@server::module_name/</code> - <?php echo $lang2595; ?></li>
                                <li><code>rsync -avz --delete user@server::module_name /local/path/</code> - <?php echo $lang2596; ?></li>
                                <li><code>rsync -avz --list-only user@server::</code> - <?php echo $lang2597; ?></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="widget">
                    <div class="widget-header">
                        <h3><i class="fas fa-hdd text-primary"></i> <?php echo $lang2598; ?></h3>
                        <a class="btn btn-primary btn-sm" href="disk_manager.php"><i class="fas fa-tools"></i></a>
                    </div>
                    <div class="widget-body" id="storagesContainer">
                        <div class="text-center py-3"><div class="loading-spinner-sm"></div> <?php echo $lang2599; ?></div>
                    </div>
                </div>
                
                <div class="widget">
                    <div class="widget-header">
                        <h3><i class="fas fa-users text-primary"></i> <?php echo $lang2600; ?></h3>
                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addUserModal">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                    <div class="widget-body" id="usersContainer">
                        <div class="text-center py-3"><div class="loading-spinner-sm"></div> <?php echo $lang2601; ?></div>
                    </div>
                </div>
                
                <div class="widget">
                    <div class="widget-header">
                        <h3><i class="fas fa-history text-primary"></i> <?php echo $lang2602; ?></h3>
                        <i class="fas fa-sync-alt refresh-btn text-muted" onclick="loadLogs()" style="font-size:12px"></i>
                    </div>
                    <div class="widget-body p-0">
                        <div class="log-viewer" id="logsContainer">
                            <div class="text-muted text-center py-3"><div class="loading-spinner-sm"></div> <?php echo $lang2603; ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Модальные окна -->
<div class="modal fade" id="settingsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-sliders-h"></i> <?php echo $lang2604; ?></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="globalConfigForm">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label"><?php echo $lang2605; ?></label>
                            <input type="text" name="uid" id="config_uid" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?php echo $lang2606; ?></label>
                            <input type="text" name="gid" id="config_gid" class="form-control">
                        </div>
                        <div class="col-md-12">
                            <div class="form-check form-switch">
                                <input type="checkbox" name="use_chroot" class="form-check-input" id="config_chroot">
                                <label class="form-check-label" for="config_chroot"><?php echo $lang2607; ?></label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?php echo $lang2608; ?></label>
                            <input type="number" name="max_connections" id="config_max_connections" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?php echo $lang2609; ?></label>
                            <input type="number" name="timeout" id="config_timeout" class="form-control">
                        </div>
                        <div class="col-md-12">
                            <div class="form-check form-switch">
                                <input type="checkbox" name="transfer_logging" class="form-check-input" id="config_transfer_logging">
                                <label class="form-check-label" for="config_transfer_logging"><?php echo $lang2610; ?></label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang2611; ?></button>
                    <button type="submit" class="btn btn-primary"><?php echo $lang2612; ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-user-plus"></i> <?php echo $lang2613; ?></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="addUserForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label"><?php echo $lang2614; ?></label>
                        <input type="text" name="username" class="form-control" pattern="[a-z_][a-z0-9_-]*" required>
                        <small class="text-muted"><?php echo $lang2615; ?></small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?php echo $lang2616; ?></label>
                        <input type="password" name="password" class="form-control" required minlength="4">
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?php echo $lang2617; ?></label>
                        <input type="password" name="password2" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary"><?php echo $lang2618; ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="changePasswordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title"><i class="fas fa-key"></i> <?php echo $lang2619; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="changePasswordForm">
                <div class="modal-body">
                    <input type="hidden" name="username" id="changePasswordUsername">
                    <div class="mb-3">
                        <label class="form-label"><?php echo $lang2620; ?></label>
                        <input type="password" name="password" class="form-control" required minlength="4">
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?php echo $lang2621; ?></label>
                        <input type="password" name="password2" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-warning"><?php echo $lang2622; ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="addModuleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-plus-circle"></i> <?php echo $lang2623; ?></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="addModuleForm">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label"><?php echo $lang2624; ?> *</label>
                            <input type="text" name="name" class="form-control" required placeholder="e.g.: backup">
                            <small class="text-muted"><?php echo $lang2625; ?></small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?php echo $lang2626; ?> *</label>
                            <div class="input-group">
                                <input type="text" name="path" id="addModulePath" class="form-control" readonly required>
                                <button type="button" class="btn btn-secondary" onclick="openFolderBrowser('addModulePath')">
                                    <i class="fas fa-folder-open"></i> <?php echo $lang2627; ?>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label"><?php echo $lang2628; ?></label>
                            <input type="text" name="comment" class="form-control" placeholder="<?php echo $lang2629; ?>">
                        </div>
                        <div class="col-md-12">
                            <div class="form-check form-check-inline">
                                <input type="checkbox" name="read_only" class="form-check-input" id="module_read_only" checked>
                                <label class="form-check-label" for="module_read_only"><i class="fas fa-eye"></i> <?php echo $lang2630; ?></label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input type="checkbox" name="list" class="form-check-input" id="module_list" checked>
                                <label class="form-check-label" for="module_list"><i class="fas fa-list"></i> <?php echo $lang2631; ?></label>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label"><?php echo $lang2632; ?></label>
                            <div class="user-select-list" id="moduleUsersList"></div>
                            <small class="text-muted"><?php echo $lang2633; ?></small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-success"><?php echo $lang2634; ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editModuleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-edit"></i> <?php echo $lang2635; ?></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="editModuleForm">
                <div class="modal-body">
                    <input type="hidden" name="old_name" id="edit_old_name">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label"><?php echo $lang2636; ?> *</label>
                            <input type="text" name="name" id="edit_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?php echo $lang2637; ?> *</label>
                            <div class="input-group">
                                <input type="text" name="path" id="edit_path" class="form-control" readonly required>
                                <button type="button" class="btn btn-secondary" onclick="openFolderBrowserEdit()">
                                    <i class="fas fa-folder-open"></i> <?php echo $lang2638; ?>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label"><?php echo $lang2639; ?></label>
                            <input type="text" name="comment" id="edit_comment" class="form-control">
                        </div>
                        <div class="col-md-12">
                            <div class="form-check form-check-inline">
                                <input type="checkbox" name="read_only" class="form-check-input" id="edit_read_only">
                                <label class="form-check-label" for="edit_read_only"><i class="fas fa-eye"></i> <?php echo $lang2640; ?></label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input type="checkbox" name="list" class="form-check-input" id="edit_list">
                                <label class="form-check-label" for="edit_list"><i class="fas fa-list"></i> <?php echo $lang2641; ?></label>
                            </div>
                        </div>
                        <div class="col-md-12" id="editUsersContainer"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary"><?php echo $lang2642; ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="browseFolderModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-folder-open"></i> <?php echo $lang2643; ?></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <nav aria-label="breadcrumb"><ol class="breadcrumb" id="folderBreadcrumb"></ol></nav>
                <div class="input-group mb-3">
                    <input type="text" id="currentPath" class="form-control" readonly>
                    <button type="button" class="btn btn-success" onclick="showCreateFolderDialog()"><i class="fas fa-folder-plus"></i> <?php echo $lang2644; ?></button>
                </div>
                <div class="folder-browser" id="folderBrowser"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang2645; ?></button>
                <button type="button" class="btn btn-primary" onclick="selectCurrentFolder()"><?php echo $lang2646; ?></button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="browseFolderModalEdit" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-folder-open"></i> <?php echo $lang2647; ?></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <nav aria-label="breadcrumb"><ol class="breadcrumb" id="folderBreadcrumbEdit"></ol></nav>
                <div class="input-group mb-3">
                    <input type="text" id="currentPathEdit" class="form-control" readonly>
                    <button type="button" class="btn btn-success" onclick="showCreateFolderDialogEdit()"><i class="fas fa-folder-plus"></i> <?php echo $lang2648; ?></button>
                </div>
                <div class="folder-browser" id="folderBrowserEdit"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang2649; ?></button>
                <button type="button" class="btn btn-primary" onclick="selectCurrentFolderEdit()"><?php echo $lang2650; ?></button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="createFolderDialog" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content"><div class="modal-header bg-success text-white"><h5 class="modal-title"><i class="fas fa-folder-plus"></i> <?php echo $lang2651; ?></h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="mb-3"><label class="form-label"><?php echo $lang2652; ?></label> <code id="createFolderPath"></code></div><div class="mb-3"><label class="form-label"><?php echo $lang2653; ?></label><input type="text" id="newFolderName" class="form-control" placeholder="New Folder"></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang2654; ?></button><button type="button" class="btn btn-success" onclick="createNewFolder()"><?php echo $lang2655; ?></button></div></div></div>
</div>

<div class="modal fade" id="createFolderDialogEdit" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content"><div class="modal-header bg-success text-white"><h5 class="modal-title"><i class="fas fa-folder-plus"></i> <?php echo $lang2656; ?></h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="mb-3"><label class="form-label"><?php echo $lang2657; ?></label> <code id="createFolderPathEdit"></code></div><div class="mb-3"><label class="form-label"><?php echo $lang2658; ?></label><input type="text" id="newFolderNameEdit" class="form-control" placeholder="New Folder"></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang2659; ?></button><button type="button" class="btn btn-success" onclick="createNewFolderEdit()"><?php echo $lang2660; ?></button></div></div></div>
</div>

<script src="lib/jquery-3.6.0-master/dist/jquery.min.js"></script>
<script src="lib/bootstrap-5.3.8-dist/js/bootstrap.bundle.min.js"></script>
<script>
const url = "<?php echo $current_host_id == 1 ? '/api/' : rtrim($host_url, '/') . '/'; ?>";
let currentTargetInput = null;
let currentBrowsePath = '/';
let currentBrowsePathEdit = '/';
let rsyncUsersList = [];

function showAlert(message, type = 'success') {
    const alertHtml = `<div class="alert alert-${type} alert-dismissible fade show" role="alert"><i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'}"></i> ${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
    $('#alertContainer').append(alertHtml);
    setTimeout(() => $('.alert').fadeOut(500, function() { $(this).remove(); }), 5000);
}

function escapeHtml(str) { if (!str) return ''; return str.replace(/[&<>]/g, function(m) { return { '&':'&amp;', '<':'&lt;', '>':'&gt;' }[m]; }); }

async function apiCall(action, method = 'GET', data = null) {
    let fullUrl = `${url}shares_rsync_api.php?action=${action}`;
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
        $('#rsyncRunningBadge').html(`<i class="fas fa-${status.running ? 'play-circle' : 'stop-circle'}"></i> <?php echo $lang2661; ?> ${status.running ? 'Running' : 'Stopped'}`);
        $('#rsyncRunningBadge').removeClass('status-running status-stopped').addClass(status.running ? 'status-running' : 'status-stopped');
        $('#rsyncEnabledBadge').html(`<i class="fas fa-${status.enabled ? 'check-circle' : 'times-circle'}"></i> <?php echo $lang2662; ?> ${status.enabled ? 'On' : 'Off'}`);
        $('#rsyncEnabledBadge').removeClass('status-enabled status-disabled').addClass(status.enabled ? 'status-enabled' : 'status-disabled');
        if (status.pid) { $('#rsyncPidBadge').show(); $('#rsyncPid').text(status.pid); } else { $('#rsyncPidBadge').hide(); }
        $('#rsyncVersion').text(status.version || 'unknown');
        
        let toggleBtn = $('#toggleAutostartBtn');
        toggleBtn.html(`<i class="fas fa-${status.enabled ? 'pause' : 'play'} text-info"></i> ${status.enabled ? 'Disable autostart' : 'Enable autostart'}`);
    }
}

async function serviceAction(action) {
    let result = await apiCall('service_action', 'POST', { service_action: action });
    if (result.success) { showAlert(`<?php echo $lang2663; ?> ${action} <?php echo $lang2664; ?>`, 'success'); loadStatus(); }
    else showAlert(`<?php echo $lang2665; ?> ${action}`, 'danger');
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
        $('#config_uid').val(cfg.uid);
        $('#config_gid').val(cfg.gid);
        $('#config_chroot').prop('checked', cfg.use_chroot === 'yes');
        $('#config_max_connections').val(cfg.max_connections);
        $('#config_timeout').val(cfg.timeout);
        $('#config_transfer_logging').prop('checked', cfg.transfer_logging === 'yes');
    }
}

$('#globalConfigForm').on('submit', async function(e) {
    e.preventDefault();
    let data = $(this).serialize();
    let result = await apiCall('save_config', 'POST', data);
    if (result.success) { showAlert('<?php echo $lang2666; ?>', 'success'); $('#settingsModal').modal('hide'); loadStatus(); }
    else showAlert('<?php echo $lang2667; ?>', 'danger');
});

async function loadUsers() {
    let result = await apiCall('get_users');
    if (result.success) {
        rsyncUsersList = result.data;
        if (rsyncUsersList.length === 0) {
            $('#usersContainer').html('<div class="alert alert-info mb-0"><?php echo $lang2668; ?></div>');
        } else {
            let html = '<div class="d-flex flex-wrap">';
            rsyncUsersList.forEach(u => {
                html += `<div class="user-badge" id="user-${escapeHtml(u.username)}"><i class="fas fa-user-circle fa-lg"></i><strong>${escapeHtml(u.username)}</strong><span class="user-badge-actions"><button type="button" class="btn btn-sm btn-link text-primary p-0" onclick="openChangePassword('${escapeHtml(u.username)}')"><i class="fas fa-key"></i></button><button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="deleteUser('${escapeHtml(u.username)}')"><i class="fas fa-trash-alt"></i></button></span></div>`;
            });
            html += '</div>';
            $('#usersContainer').html(html);
        }
        updateUserCheckboxes();
    }
}

function updateUserCheckboxes() {
    let html = '<div class="form-check mb-2"><input type="checkbox" id="select_all_users" class="form-check-input"><label class="form-check-label" for="select_all_users"><?php echo $lang2669; ?></label></div><hr>';
    rsyncUsersList.forEach(u => {
        html += `<div class="form-check"><input type="checkbox" name="users[]" value="${escapeHtml(u.username)}" class="form-check-input user-checkbox" id="module_user_${escapeHtml(u.username)}"><label class="form-check-label" for="module_user_${escapeHtml(u.username)}">${escapeHtml(u.username)}</label></div>`;
    });
    if (rsyncUsersList.length === 0) {
        html = '<span class="text-muted"><?php echo $lang2670; ?></span>';
    }
    $('#moduleUsersList').html(html);
    
    $('#select_all_users').off('change').on('change', function() {
        $('.user-checkbox').prop('checked', $(this).prop('checked'));
    });
}

async function deleteUser(username) {
    if (!confirm(`<?php echo $lang2671; ?> ${username}?`)) return;
    let result = await apiCall('delete_user', 'POST', { username: username });
    if (result.success) { showAlert(result.message, 'success'); loadUsers(); loadModules(); }
    else showAlert(result.message, 'danger');
}

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

$('#addUserForm').on('submit', async function(e) {
    e.preventDefault();
    let data = $(this).serialize();
    let result = await apiCall('create_user', 'POST', data);
    if (result.success) { showAlert(result.message, 'success'); $('#addUserModal').modal('hide'); loadUsers(); loadModules(); }
    else showAlert(result.error || 'Error', 'danger');
});

async function loadModules() {
    let result = await apiCall('get_modules');
    if (result.success) {
        let modules = result.data;
        if (modules.length === 0) {
            $('#modulesTableBody').html('<tr><td colspan="5" class="text-center text-muted py-4"><i class="fas fa-cubes fa-2x mb-2 d-block"></i><?php echo $lang2672; ?></td></tr>');
        } else {
            let html = '';
            modules.forEach(m => {
                let rightsBadge = m.read_only ? '<span class="badge bg-warning"><i class="fas fa-eye"></i> <?php echo $lang2673; ?></span>' : '<span class="badge bg-success"><i class="fas fa-pen"></i> <?php echo $lang2674; ?></span>';
                let listBadge = m.list ? '<span class="badge bg-info"><i class="fas fa-list"></i> <?php echo $lang2675; ?></span>' : '<span class="badge bg-secondary"><i class="fas fa-lock"></i> <?php echo $lang2676; ?></span>';
                let usersBadge = '';
                if (m.auth_users && m.auth_users.length > 0) {
                    usersBadge = `<span class="badge bg-secondary" title="Users: ${m.auth_users.join(', ')}"><i class="fas fa-lock"></i> ${m.auth_users.length}</span>`;
                }
                html += `<tr>
                    <td><strong>${escapeHtml(m.name)}</strong></td>
                    <td><code class="table-module-path">${escapeHtml(m.path)}</code></td>
                    <td>${rightsBadge}</td>
                    <td>${listBadge} ${usersBadge}</td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-primary" onclick="editModule('${escapeHtml(m.name)}', '${escapeHtml(m.path)}', '${escapeHtml(m.comment || '')}', ${m.read_only ? 'true' : 'false'}, ${m.list ? 'true' : 'false'}, ${JSON.stringify(m.auth_users || []).replace(/"/g, '&quot;')})"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-outline-danger" onclick="deleteModule('${escapeHtml(m.name)}')"><i class="fas fa-trash"></i></button>
                        </div>
                    </td>
                </tr>`;
            });
            $('#modulesTableBody').html(html);
        }
    }
}

$('#addModuleForm').on('submit', async function(e) {
    e.preventDefault();
    let data = $(this).serialize();
    let result = await apiCall('create_module', 'POST', data);
    if (result.success) { showAlert(result.message, 'success'); $('#addModuleModal').modal('hide'); loadModules(); loadStatus(); }
    else showAlert(result.error || 'Error', 'danger');
});

function editModule(name, path, comment, readOnly, list, users) {
    $('#edit_old_name').val(name);
    $('#edit_name').val(name);
    $('#edit_path').val(path);
    $('#edit_comment').val(comment);
    $('#edit_read_only').prop('checked', readOnly);
    $('#edit_list').prop('checked', list);
    
    let html = '<label class="form-label"><?php echo $lang2677; ?></label><div class="user-select-list">';
    html += '<div class="form-check mb-2"><input type="checkbox" id="edit_select_all_users" class="form-check-input"><label class="form-check-label" for="edit_select_all_users"><?php echo $lang2678; ?></label></div><hr>';
    
    rsyncUsersList.forEach(u => {
        let checked = users && users.includes(u.username) ? 'checked' : '';
        html += `<div class="form-check"><input type="checkbox" name="users[]" value="${escapeHtml(u.username)}" class="form-check-input edit-user-checkbox" id="edit_user_${escapeHtml(u.username)}" ${checked}><label class="form-check-label" for="edit_user_${escapeHtml(u.username)}">${escapeHtml(u.username)}</label></div>`;
    });
    
    if (rsyncUsersList.length === 0) {
        html += '<span class="text-muted"><?php echo $lang2679; ?></span>';
    }
    
    html += '</div>';
    $('#editUsersContainer').html(html);
    
    $('#edit_select_all_users').off('change').on('change', function() {
        $('.edit-user-checkbox').prop('checked', $(this).prop('checked'));
    });
    
    $('#editModuleModal').modal('show');
}

$('#editModuleForm').on('submit', async function(e) {
    e.preventDefault();
    let data = $(this).serialize();
    let result = await apiCall('update_module', 'POST', data);
    if (result.success) { showAlert(result.message, 'success'); $('#editModuleModal').modal('hide'); loadModules(); loadStatus(); }
    else showAlert(result.error || 'Error', 'danger');
});

async function deleteModule(name) {
    if (!confirm(`<?php echo $lang2680; ?> "${name}"?`)) return;
    let result = await apiCall('delete_module', 'POST', { name: name });
    if (result.success) { showAlert(result.message, 'success'); loadModules(); loadStatus(); }
    else showAlert(result.error || 'Error', 'danger');
}

async function loadStorages() {
    let result = await apiCall('get_storages');
    if (result.success) {
        let storages = result.storages;
        if (storages.length === 0) {
            $('#storagesContainer').html('<div class="alert alert-info"><?php echo $lang2681; ?></div>');
        } else {
            let html = '';
            storages.forEach(s => {
                let fillColor = s.used_percent > 90 ? '#dc3545' : (s.used_percent > 70 ? '#ffc107' : '#28a745');
                html += `<div class="storage-card"><div class="storage-header"><span class="storage-name"><i class="fas fa-hdd"></i> ${escapeHtml(s.name)}</span><span class="storage-type type-${s.type}">${s.type.toUpperCase()}</span></div><div class="storage-mount"><i class="fas fa-folder-open"></i> ${escapeHtml(s.mount)}</div><div class="storage-stats mb-1"><span><i class="fas fa-chart-pie"></i> <?php echo $lang2682; ?> ${s.used_percent}%</span><span><i class="fas fa-database"></i> ${s.size_gb}</span></div><div class="progress-custom"><div class="progress-fill" style="width: ${s.used_percent}%; background: ${fillColor};"></div></div><div class="storage-stats"><span><i class="fas fa-chart-line"></i> <?php echo $lang2683; ?> ${s.used || '—'}</span><span><i class="fas fa-check-circle text-success"></i> <?php echo $lang2684; ?> ${s.available || '—'}</span></div></div>`;
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
            html = '<div class="text-muted text-center py-3"><?php echo $lang2685; ?></div>';
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
        let breadcrumbHtml = '<li class="breadcrumb-item"><a onclick="loadFolder(\'/\', \'' + container + '\', \'' + breadcrumbContainer + '\', \'' + currentPathInput + '\')"></a></li>';
        let cp = '';
        parts.forEach(p => { cp += '/' + p; breadcrumbHtml += `<li class="breadcrumb-item"><a onclick="loadFolder('${cp}', '${container}', '${breadcrumbContainer}', '${currentPathInput}')">${escapeHtml(p)}</a></li>`; });
        $(breadcrumbContainer).html(breadcrumbHtml);
        
        let listHtml = '<div class="list-group">';
        if (result.path !== '/') listHtml += `<div class="list-group-item list-group-item-action folder-item" onclick="loadFolder('${result.parent}', '${container}', '${breadcrumbContainer}', '${currentPathInput}')"><i class="fas fa-level-up-alt"></i> ..</div>`;
        result.items.forEach(item => {
            listHtml += `<div class="list-group-item list-group-item-action folder-item" onclick="selectDirectory('${item.path}', '${container}', '${currentPathInput}')"><i class="fas fa-folder"></i> ${escapeHtml(item.name)}</div>`;
        });
        if (result.items.length === 0 && result.path !== '/') listHtml += '<div class="list-group-item text-muted"><?php echo $lang2686; ?></div>';
        listHtml += '</div>';
        $(container).html(listHtml);
    } else {
        showAlert('<?php echo $lang2687; ?>', 'danger');
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
        showAlert('<?php echo $lang2688; ?> ' + currentBrowsePath, 'success');
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
    if (!name) { showAlert('<?php echo $lang2689; ?>', 'danger'); return; }
    let result = await apiCall('create_folder', 'POST', { path: path, name: name });
    if (result.success) {
        $('#createFolderDialog').modal('hide');
        loadFolder(currentBrowsePath, '#folderBrowser', '#folderBreadcrumb', '#currentPath');
        showAlert('<?php echo $lang2690; ?>"' + name + '"<?php echo $lang2691; ?>', 'success');
    } else {
        showAlert(result.error, 'danger');
    }
}

async function createNewFolderEdit() {
    let path = $('#createFolderPathEdit').text();
    let name = $('#newFolderNameEdit').val();
    if (!name) { showAlert('<?php echo $lang2692; ?>', 'danger'); return; }
    let result = await apiCall('create_folder', 'POST', { path: path, name: name });
    if (result.success) {
        $('#createFolderDialogEdit').modal('hide');
        loadFolder(currentBrowsePathEdit, '#folderBrowserEdit', '#folderBreadcrumbEdit', '#currentPathEdit');
        showAlert('<?php echo $lang2693; ?>"' + name + '"<?php echo $lang2694; ?>', 'success');
    } else {
        showAlert(result.error, 'danger');
    }
}

async function refreshAllData() {
    await Promise.all([loadStatus(), loadConfig(), loadUsers(), loadModules(), loadStorages(), loadLogs()]);
    showAlert('<?php echo $lang2695; ?>', 'success');
}

setInterval(() => {
    loadLogs();
}, 30000);

$(document).ready(function() {
    refreshAllData();
    setTimeout(function() { $('#applePreloader').fadeOut(500); }, 500);
});
</script>
<script src="js/loader.js"></script>
</body>
</html>