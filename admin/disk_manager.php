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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Disk Manager - Mini-b</title>
    <link href="lib/bootstrap-5.3.8-dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="lib/fontawesome-free-6.7.2-web/css/all.min.css">
    <link rel="stylesheet" href="style.css">
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
        .disk-manager {
            display: flex;
            gap: 20px;
            height: calc(100vh - 100px);
            background: #f8f9fa;
            border-radius: 16px;
            overflow: hidden;
        }
        .disk-sidebar {
            width: 380px;
            background: white;
            border-radius: 16px;
            overflow-y: auto;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            display: flex;
            flex-direction: column;
        }
        .filter-bar {
            padding: 12px;
            border-bottom: 1px solid #e9ecef;
            background: #f8f9fa;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .filter-btn {
            font-size: 12px;
            padding: 4px 8px;
            margin: 2px;
            border-radius: 20px;
        }
        .filter-btn.active {
            background: #0d6efd;
            color: white;
            border-color: #0d6efd;
        }
        .disk-category {
            padding: 12px 16px;
            font-weight: 600;
            color: #6c757d;
            font-size: 12px;
            text-transform: uppercase;
            border-bottom: 1px solid #e9ecef;
            background: #f8f9fa;
            position: sticky;
            top: 50px;
            z-index: 5;
        }
        .disk-item {
            padding: 12px 16px;
            cursor: pointer;
            transition: all 0.2s;
            border-left: 3px solid transparent;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid #f0f0f0;
        }
        .disk-item:hover { background: #f8f9fa; }
        .disk-item.active {
            background: #e7f1ff;
            border-left-color: #0d6efd;
        }
        .disk-item.uninitialized {
            background: #fff8e7;
            border-left-color: #fd7e14;
        }
        .disk-item.managed-lvm {
            background: #fef3c7;
            border-left-color: #8b5cf6;
        }
        .disk-item.managed-raid {
            background: #fee2e2;
            border-left-color: #ef4444;
        }
        .disk-item.virtual-lvm {
            background: linear-gradient(135deg, #f0f4ff 0%, #e8eeff 100%);
            border-left-color: #8b5cf6;
        }
        .disk-item.virtual-raid {
            background: linear-gradient(135deg, #fff0f0 0%, #ffe8e8 100%);
            border-left-color: #ef4444;
        }
        .disk-icon {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            background: #e9ecef;
            font-size: 18px;
        }
        .disk-icon.virtual-icon { background: rgba(139, 92, 246, 0.15); }
        .disk-icon.raid-icon { background: rgba(239, 68, 68, 0.15); }
        .disk-icon.lvm-icon { background: rgba(139, 92, 246, 0.15); }
        .disk-icon.raid-member-icon { background: rgba(239, 68, 68, 0.15); }
        .disk-info { flex: 1; min-width: 0; }
        .disk-title { font-weight: 600; font-size: 14px; display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
        .disk-subtitle { font-size: 11px; color: #6c757d; margin-top: 2px; }
        .disk-badge {
            font-size: 9px;
            padding: 2px 6px;
            border-radius: 20px;
        }
        .disk-badge.system { background: #fd7e14; color: white; }
        .disk-badge.usb { background: #10b981; color: white; }
        .disk-badge.uninit { background: #dc2626; color: white; }
        .disk-badge.gpt { background: #3b82f6; color: white; }
        .disk-badge.mbr { background: #6b7280; color: white; }
        .disk-badge.lvm-pv { background: #8b5cf6; color: white; }
        .disk-badge.raid-member { background: #ef4444; color: white; }
        .disk-badge.lvm-vg { background: #8b5cf6; color: white; }
        .disk-badge.raid-array { background: #ef4444; color: white; }
        .disk-content {
            flex: 1;
            background: white;
            border-radius: 16px;
            overflow-y: auto;
            padding: 24px;
        }
        .disk-header {
            display: flex;
            align-items: center;
            gap: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e9ecef;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .init-card {
			background: linear-gradient(135deg, #4a6fa5 0%, #6c8cbd 100%);
			border-radius: 16px;
			padding: 24px;
			margin-bottom: 20px;
			color: white;
		}
        .disk-map {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        .partition-visual {
            display: flex;
            height: 60px;
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 15px;
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);
        }
        .part-visual {
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 500;
            color: white;
            transition: all 0.2s;
            cursor: pointer;
            text-shadow: 0 1px 1px rgba(0,0,0,0.2);
        }
        .part-visual:hover {
            filter: brightness(1.05);
            transform: scaleY(1.02);
        }
        .part-visual.free {
            background: repeating-linear-gradient(45deg, #dee2e6, #dee2e6 10px, #ced4da 10px, #ced4da 20px);
            color: #495057;
            text-shadow: none;
        }
        .part-visual.ext2 { background: linear-gradient(135deg, #a6f7dc, #78e0c0); }
        .part-visual.ext4 { background: linear-gradient(135deg, #10b981, #059669); }
        .part-visual.ntfs { background: linear-gradient(135deg, #3b82f6, #2563eb); }
        .part-visual.fat32 { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .part-visual.exfat { background: linear-gradient(135deg, #f97316, #ea580c); }
        .part-visual.xfs { background: linear-gradient(135deg, #06b6d4, #0891b2); }
        .part-visual.btrfs { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
        .part-visual.swap { background: linear-gradient(135deg, #ef4444, #dc2626); }
        .part-visual.no-fs { background: linear-gradient(135deg, #6b7280, #4b5563); }
        .free-space-info {
            font-size: 12px;
            color: #6c757d;
            margin-top: 8px;
            text-align: center;
        }
        .disk-toolbar {
            display: flex;
            gap: 8px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .partition-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .partition-table th {
            text-align: left;
            padding: 12px 12px;
            background: #f8f9fa;
            border-bottom: 2px solid #e9ecef;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #6c757d;
        }
        .partition-table td {
            padding: 12px 12px;
            border-bottom: 1px solid #f0f0f0;
            vertical-align: middle;
        }
        .partition-table tr:last-child td { border-bottom: none; }
        .partition-table tr:hover { background: #f8f9fa; }
        .partition-table tr.no-fs { background: #fffbeb; }
        .fs-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
        }
        .fs-ext4 { background: #d1fae5; color: #065f46; }
        .fs-ext3 { background: #d1fae5; color: #065f46; }
        .fs-ext2 { background: #d1fae5; color: #065f46; }
        .fs-ntfs { background: #dbeafe; color: #1e40af; }
        .fs-fat32 { background: #fed7aa; color: #92400e; }
        .fs-exfat { background: #fed7aa; color: #92400e; }
        .fs-xfs { background: #cffafe; color: #0e7490; }
        .fs-btrfs { background: #ede9fe; color: #6d28d9; }
        .fs-swap { background: #fee2e2; color: #991b1b; }
        .fs-none { background: #f3f4f6; color: #4b5563; }
        .fstab-badge {
            background: #e0e7ff;
            color: #4338ca;
            font-size: 10px;
            padding: 3px 8px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .mounted-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #10b981;
            margin-left: 8px;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { opacity: 0.5; transform: scale(1); }
            50% { opacity: 1; transform: scale(1.2); }
            100% { opacity: 0.5; transform: scale(1); }
        }
        .no-fs-warning {
            color: #dc2626;
            font-size: 11px;
            margin-left: 6px;
            display: inline-flex;
            align-items: center;
            gap: 3px;
        }
        .alert-info {
            background: linear-gradient(135deg, #e7f1ff, #dbeafe);
            border: none;
            border-radius: 12px;
        }
        .loader-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
        }
        .loader-spinner {
            width: 48px;
            height: 48px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #0d6efd;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            background: white;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .context-menu {
            position: fixed;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            min-width: 220px;
            z-index: 10000;
            overflow: hidden;
        }
        .context-menu-item {
            padding: 10px 16px;
            cursor: pointer;
            transition: background 0.2s;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 13px;
        }
        .context-menu-item:hover { background: #f8f9fa; }
        .context-menu-item.danger { color: #dc2626; }
        .context-menu-item.danger:hover { background: #fee2e2; }
        .context-menu-divider {
            height: 1px;
            background: #e9ecef;
            margin: 4px 0;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }
        .toast-container { z-index: 1100; }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 16px;
            margin-top: 20px;
        }
        .info-card {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 16px;
        }
        .info-label {
            font-size: 11px;
            text-transform: uppercase;
            color: #6c757d;
            margin-bottom: 4px;
        }
        .info-value {
            font-size: 14px;
            font-weight: 500;
            word-break: break-all;
        }
        .smart-health-passed { color: #10b981; font-weight: 600; }
        .smart-health-failed { color: #dc2626; font-weight: 600; }
        .temperature-normal { color: #10b981; }
        .temperature-warning { color: #f59e0b; }
        .temperature-critical { color: #dc2626; }
        .logs-container {
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 15px;
            border-radius: 8px;
            font-family: monospace;
            font-size: 12px;
            max-height: 500px;
            overflow-y: auto;
        }
        .log-entry {
            border-bottom: 1px solid #333;
            padding: 4px 0;
            font-family: monospace;
        }
        .log-error { color: #f48771; }
        .log-success { color: #6a9955; }
        .log-warning { color: #dcdcaa; }
        .log-info { color: #d4d4d4; }
        .smart-attributes-table {
            width: 100%;
            font-size: 12px;
            margin-top: 16px;
            border-collapse: collapse;
        }
        .smart-attributes-table th, .smart-attributes-table td {
            padding: 8px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }
        .btn-group-sm .btn {
            padding: 4px 8px;
            font-size: 11px;
            border-radius: 6px;
        }
        .resize-hint { font-size: 12px; color: #6c757d; margin-top: 5px; }
        
        .disk-item.hidden-category {
            display: none;
        }
        .disk-category.hidden-category {
            display: none;
        }
		
		.lv-card {
            background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
            border-left: 4px solid #4CAF50;
        }
        .lv-info {
            font-size: 12px;
            color: #2e7d32;
        }
        .lv-badge-mounted {
            background: #ff3b30;
            color: white;
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 12px;
        }
        .lv-badge-formatted {
            background: #34c759;
            color: white;
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 12px;
        }
        .lv-badge-unformatted {
            background: #ff9f0a;
            color: white;
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 12px;
        }
        .modal-lg-custom {
            max-width: 800px;
        }
        .snapshot-card {
            background: #f0f4ff;
            border-left: 4px solid #5856d6;
        }
        .progress-sm {
            height: 6px;
        }

    </style>
</head>
<body>

<div class="top-bar">
    <div class="top-bar-left">
        <h1><i class="fas fa-bucket"></i> Mini-B</h1>
    </div>
    <div class="top-bar-right">
        <i class="fas fa-hdd"></i>Disk Manager
		<div class="host-selector" style="margin-left: 20px;">
            <select id="hostSelector" style="background: rgba(255,255,255,0.9); border: 1px solid #ddd; border-radius: 20px; padding: 6px 30px 6px 15px; font-size: 14px; cursor: pointer;">
                <option value=""><?php echo $lang12; ?></option>
            </select>
        </div>
		<div class="btn-group">
                <button class="btn btn-sm btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-bars"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="#" onclick="openPartedConsole()"><i class="fas fa-terminal me-2"></i> <?php echo $lang573; ?></a></li>
                    <li><a class="dropdown-item" href="#" onclick="showLogs()"><i class="fas fa-history me-2"></i> <?php echo $lang574; ?></a></li>
					<li><hr class="dropdown-divider"></li>
					<li><a class="dropdown-item" href="#" onclick="refreshAll(true)"><i class="fas fa-sync-alt me-2"></i> <?php echo $lang575; ?></a></li>
                </ul>
            </div>	
    </div>
</div>

<div class="app-container">
    <?php echo $menu; ?>
    <main class="main-content">

        <div class="disk-manager">
            <div class="disk-sidebar">
                <div class="filter-bar">
                    <div class="btn-group w-100" role="group">
                        <button class="btn btn-sm btn-outline-secondary filter-btn active" data-filter="all"><?php echo $lang576; ?></button>
                        <button class="btn btn-sm btn-outline-secondary filter-btn" data-filter="physical"><?php echo $lang577; ?></button>
                        <button class="btn btn-sm btn-outline-secondary filter-btn" data-filter="usb"><?php echo $lang578; ?></button>
                        <button class="btn btn-sm btn-outline-secondary filter-btn" data-filter="lvm"><?php echo $lang579; ?></button>
                        <button class="btn btn-sm btn-outline-secondary filter-btn" data-filter="raid"><?php echo $lang580; ?></button>
                    </div>
                </div>
                <div id="sidebarContent">
                    <div class="disk-category" data-category="usb"><i class="fas fa-eject"></i> <?php echo $lang581; ?></div>
                    <div id="externalDisksList"><div class="text-muted p-3 text-center">—</div></div>
					<div class="disk-category" data-category="system"><i class="fas fa-desktop"></i> <?php echo $lang582; ?></div>
                    <div id="systemDisksList"><div class="text-muted p-3 text-center"><?php echo $lang583; ?></div></div>
					<div class="disk-category" data-category="internal"><i class="fas fa-hdd"></i> <?php echo $lang584; ?></div>
                    <div id="internalDisksList"><div class="text-muted p-3 text-center">—</div></div>
					<div class="disk-category" data-category="lvm-vg"><i class="fas fa-cubes"></i> <?php echo $lang585; ?></div>
                    <div id="lvmVgsList"><div class="text-muted p-3 text-center"><?php echo $lang586; ?></div></div>
                    <div class="disk-category" data-category="raid-array"><i class="fas fa-server"></i> <?php echo $lang587; ?></div>
                    <div id="raidArraysList"><div class="text-muted p-3 text-center"><?php echo $lang588; ?></div></div>
                    <div class="disk-category" data-category="lvm-pv"><i class="fas fa-cubes"></i> <?php echo $lang589; ?></div>
                    <div id="lvmPvList"><div class="text-muted p-3 text-center"><?php echo $lang590; ?></div></div>
                    <div class="disk-category" data-category="raid-member"><i class="fas fa-server"></i> <?php echo $lang591; ?></div>
                    <div id="raidMemberList"><div class="text-muted p-3 text-center"><?php echo $lang592; ?></div></div>
                </div>
            </div>

            <div class="disk-content">
                <div id="diskDetailsPanel">
                    <div class="empty-state">
                        <i class="fas fa-hdd fa-4x mb-3"></i>
                        <p><?php echo $lang593; ?></p>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<div class="modal fade" id="createLvModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-chart-line"></i> <?php echo $lang594; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="createLvVgName">
                <div class="mb-3">
                    <label class="form-label"><?php echo $lang595; ?></label>
                    <input type="text" class="form-control" id="createLvName" placeholder="Example: data, home, backups">
                    <small class="text-muted"><?php echo $lang596; ?></small>
                </div>
                <div class="mb-3">
                    <label class="form-label"><?php echo $lang597; ?></label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="createLvSize" placeholder="10G, 512M, 50%FREE, 100%FREE">
                        <button class="btn btn-outline-secondary" type="button" id="maxLvSizeBtn" title="Use all available space">
                            <i class="fas fa-arrow-up"></i> <?php echo $lang598; ?>
                        </button>
                    </div>
                    <small class="text-muted" id="vgFreeSpaceHint"><?php echo $lang599; ?></small>
                </div>
                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="createLvFormat">
                        <label class="form-check-label" for="createLvFormat">
                            <i class="fa fa-eraser"></i> <?php echo $lang600; ?>
                        </label>
                    </div>
                </div>
                <div class="mb-3" id="createLvFsDiv" style="display: none;">
                    <label class="form-label"><?php echo $lang601; ?></label>
                    <select class="form-select" id="createLvFs">
                        <optgroup label="Linux">
                            <option value="ext4" selected>ext4 <?php echo $lang602; ?></option>
                            <option value="ext3">ext3</option>
                            <option value="ext2">ext2</option>
                            <option value="xfs">XFS</option>
                            <option value="btrfs">Btrfs</option>
                        </optgroup>
                        <optgroup label="Windows">
                            <option value="ntfs">NTFS</option>
                            <option value="vfat">FAT32</option>
                            <option value="exfat">exFAT</option>
                        </optgroup>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label"><?php echo $lang603; ?></label>
                    <input type="text" class="form-control" id="createLvLabel" placeholder="Volume label">
                </div>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <strong><?php echo $lang604; ?></strong><br>
                    • <code>10G</code> - 10 Ggb<br>
                    • <code>512M</code> - 512 Mbt<br>
                    • <code>50%FREE</code> - 50% <?php echo $lang605; ?><br>
                    • <code>100%FREE</code> - all <?php echo $lang606; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang607; ?></button>
                <button class="btn btn-primary" onclick="executeCreateLv()"><?php echo $lang608; ?></button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="extendLvModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-expand-alt"></i> <?php echo $lang609; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="extendLvVgName">
                <input type="hidden" id="extendLvName">
                <div class="mb-3">
                    <label class="form-label"><?php echo $lang610; ?></label>
                    <div class="form-control bg-light" id="extendLvCurrentSize">-</div>
                </div>
                <div class="mb-3">
                    <label class="form-label"><?php echo $lang611; ?></label>
                    <div class="form-control bg-light" id="extendVgFreeSpace">-</div>
                </div>
                <div class="mb-3">
                    <label class="form-label"><?php echo $lang612; ?></label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="extendLvNewSize" placeholder="+5G, 15G, +50%FREE">
                        <button class="btn btn-outline-secondary" type="button" id="extendMaxSizeBtn" title="Use all available space">
                            <i class="fas fa-arrow-up"></i> <?php echo $lang613; ?>
                        </button>
                    </div>
                    <small class="text-muted">
                        <strong><?php echo $lang614; ?></strong><br>
                        • <code>+5G</code> - <?php echo $lang615; ?><br>
                        • <code>15G</code> - <?php echo $lang616; ?><br>
                        • <code>+50%FREE</code> - <?php echo $lang617; ?>
                    </small>
                </div>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong><?php echo $lang618; ?></strong> <?php echo $lang619; ?>
                    <ul class="mb-0 mt-1">
                        <li><strong>ext4:</strong> <code><?php echo $lang620; ?></code> <?php echo $lang621; ?></li>
                        <li><strong>xfs:</strong> <code><?php echo $lang622; ?></code></li>
                        <li><strong>btrfs:</strong> <code><?php echo $lang623; ?></code></li>
                    </ul>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang624; ?></button>
                <button class="btn btn-primary" onclick="executeExtendLv()"><?php echo $lang625; ?></button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="renameLvModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-pen"></i> <?php echo $lang626; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="renameLvVgName">
                <input type="hidden" id="renameLvOldName">
                <div class="mb-3">
                    <label class="form-label"><?php echo $lang627; ?></label>
                    <div class="form-control bg-light" id="renameLvCurrentName">-</div>
                </div>
                <div class="mb-3">
                    <label class="form-label"><?php echo $lang628; ?></label>
                    <input type="text" class="form-control" id="renameLvNewName" placeholder="new_name">
                    <small class="text-muted"><?php echo $lang629; ?></small>
                </div>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <?php echo $lang630; ?><code><?php echo $lang631; ?></code>.<br>
                    <?php echo $lang632; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang633; ?></button>
                <button class="btn btn-primary" onclick="executeRenameLv()"><?php echo $lang634; ?></button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteLvConfirmModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle"></i> <?php echo $lang635; ?></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="deleteLvVgName">
                <input type="hidden" id="deleteLvName">
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle fa-2x float-start me-3"></i>
                    <strong><?php echo $lang636; ?></strong><br>
                    <?php echo $lang637; ?><strong id="deleteLvDisplayName">-</strong>.
                    <br><br>
                    <strong><?php echo $lang638; ?></strong>
                </div>
                <div class="form-check mt-3">
                    <input class="form-check-input" type="checkbox" id="deleteLvConfirmCheck">
                    <label class="form-check-label text-danger" for="deleteLvConfirmCheck">
					<?php echo $lang639; ?>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang640; ?></button>
                <button class="btn btn-danger" onclick="executeDeleteLv()" id="deleteLvConfirmBtn" disabled><?php echo $lang641; ?></button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="snapshotInfoModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-camera"></i> <span id="snapshotInfoTitle"><?php echo $lang642; ?></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="snapshotInfoContent">
                <div class="text-center p-4"><?php echo $lang643; ?></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang644; ?></button>
                <button class="btn btn-warning" id="restoreSnapshotBtn" onclick="executeRestoreSnapshot()"><?php echo $lang645; ?></button>
                <button class="btn btn-danger" id="deleteSnapshotBtn" onclick="executeDeleteSnapshot()"><?php echo $lang646; ?></button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="createSnapshotModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-camera"></i> <?php echo $lang647; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="snapshotVgName">
                <input type="hidden" id="snapshotOriginLvName">
                <div class="mb-3">
                    <label class="form-label"><?php echo $lang648; ?></label>
                    <div class="form-control bg-light" id="snapshotOriginDisplay">-</div>
                </div>
                <div class="mb-3">
                    <label class="form-label"><?php echo $lang649; ?></label>
                    <input type="text" class="form-control" id="snapshotName" placeholder="snap_2024_01_01">
                    <small class="text-muted"><?php echo $lang650; ?></small>
                </div>
                <div class="mb-3">
                    <label class="form-label"><?php echo $lang651; ?></label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="snapshotSize" value="10G" placeholder="5G, 10G, 20%">
                        <button class="btn btn-outline-secondary" type="button" id="snapshotSizeHintBtn" title="<?php echo $lang652; ?>">
                            <i class="fas fa-question"></i>
                        </button>
                    </div>
                    <small class="text-muted">
                        <strong><?php echo $lang653; ?>:</strong> 5G, 10G, 20% <?php echo $lang654; ?>, 50%FREE<br>
                        <strong><?php echo $lang655; ?>:</strong> 10-30% <?php echo $lang656; ?>
                    </small>
                </div>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <?php echo $lang657; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang658; ?></button>
                <button class="btn btn-primary" onclick="executeCreateSnapshot()"><?php echo $lang659; ?></button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="snapshotsListModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-camera-retro"></i> <?php echo $lang660; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="snapshotsListContent">
                <div class="text-center p-4"><?php echo $lang661; ?></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang662; ?></button>
                <button class="btn btn-primary" onclick="refreshSnapshotsList()"><?php echo $lang663; ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Parted Console -->
<div class="modal fade" id="partedModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title"><i class="fas fa-terminal"></i> <?php echo $lang664; ?></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div id="partedConnectPanel" class="p-4 bg-dark text-white">
                    <div class="text-center mb-4">
                        <i class="fas fa-terminal fa-3x mb-3"></i>
                        <h5><?php echo $lang665; ?></h5>
                        <p class="text-muted"><?php echo $lang666; ?></p>
                    </div>
                    <div class="row justify-content-center">
                        <div class="col-md-6">
                            <div class="d-grid gap-2">
                                <button class="btn btn-primary" onclick="connectPartedConsole()">
                                    <i class="fas fa-plug"></i> <?php echo $lang667; ?>
                                </button>
                                <button class="btn btn-secondary" data-bs-dismiss="modal">
                                    <i class="fas fa-times"></i> <?php echo $lang668; ?>
                                </button>
                            </div>
                            <div id="partedConnectError" class="alert alert-danger mt-3" style="display: none;"></div>
                        </div>
                    </div>
                </div>
                <div id="partedConsolePanel" style="display: none;">
                    <div class="bg-dark text-white p-3" style="font-family: monospace; min-height: 500px; max-height: 500px; overflow-y: auto;" id="partedConsole">
                        <div class="text-center text-muted p-5"><?php echo $lang669; ?></div>
                    </div>
                    <div class="bg-secondary p-2">
                        <form id="partedCommandForm" onsubmit="sendShellCommand(); return false;">
                            <div class="input-group">
                                <span class="input-group-text bg-dark text-white" id="partedPrompt">#</span>
                                <input type="text" class="form-control bg-dark text-white" id="partedCommand" 
                                       name="command" placeholder="parted /dev/sda, lsblk, fdisk -l, or any other command" 
                                       style="font-family: monospace;" disabled>
                                <button type="submit" class="btn btn-primary" disabled id="partedSendBtn">
                                    <i class="fas fa-paper-plane"></i> <?php echo $lang670; ?>
                                </button>
                                <button type="button" class="btn btn-danger" onclick="closeShellConsole()">
                                    <i class="fas fa-power-off"></i> <?php echo $lang671; ?>
                                </button>
                            </div>
                        </form>
                        <small class="text-muted mt-2 d-block">
                            <i class="fas fa-info-circle"></i> 
                            <?php echo $lang672; ?>: <strong>parted /dev/sda</strong>, <strong>lsblk</strong>, <strong>fdisk -l</strong>, <strong>exit</strong>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Initialize Disk -->
<div class="modal fade" id="initModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo $lang673; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="initDiskName">
                <p><?php echo $lang674; ?> <strong id="initDiskNameDisplay"></strong> <?php echo $lang675; ?></p>
                <p><?php echo $lang676; ?>:</p>
                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="tableType" id="tableTypeGpt" value="gpt" checked>
                        <label class="form-check-label" for="tableTypeGpt"><?php echo $lang677; ?></label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="tableType" id="tableTypeMbr" value="msdos">
                        <label class="form-check-label" for="tableTypeMbr"><?php echo $lang678; ?></label>
                    </div>
                </div>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo $lang679; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang680; ?></button>
                <button class="btn btn-primary" onclick="executeInit()"><?php echo $lang681; ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Create Partition -->
<div class="modal fade" id="createPartitionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo $lang682; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="createDisk">
                <div class="mb-3">
                    <label class="form-label"><?php echo $lang683; ?></label>
                    <div class="input-group">
                        <input type="number" class="form-control" id="partSize" step="0.1" placeholder="<?php echo $lang683; ?>">
                        <select class="form-select" id="partUnit" style="width: 80px;">
                            <option value="MB">MB</option>
                            <option value="GB" selected>GB</option>
                            <option value="TB">TB</option>
                        </select>
                    </div>
                    <small class="text-muted" id="sizeHint"></small>
                </div>
                <div class="mb-3">
                    <label class="form-label"><?php echo $lang684; ?></label>
                    <select class="form-select" id="createFs">
                        <option value="ext4">ext4 (Linux)</option>
                        <option value="ext3">ext3</option>
                        <option value="ext2">ext2</option>
                        <option value="ntfs">NTFS (Windows)</option>
                        <option value="fat32">FAT32</option>
                        <option value="exfat">exFAT</option>
                        <option value="xfs">XFS</option>
                        <option value="btrfs">Btrfs</option>
                        <option value="swap">Swap</option>
                        <option value="none"><?php echo $lang685; ?></option>
                    </select>
                </div>
                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="formatAfterCreate">
                        <label class="form-check-label" for="formatAfterCreate"><?php echo $lang686; ?></label>
                    </div>
                </div>
                <div id="formatOptionsCreate" style="display: none;">
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="formatType" id="quickFormatCreate" value="quick" checked>
                            <label class="form-check-label" for="quickFormatCreate"><?php echo $lang687; ?></label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="formatType" id="fullFormatCreate" value="full">
                            <label class="form-check-label" for="fullFormatCreate"><?php echo $lang688; ?></label>
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label"><?php echo $lang689; ?></label>
                    <input type="text" class="form-control" id="createLabel" placeholder="<?php echo $lang690; ?>">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang691; ?></button>
                <button class="btn btn-primary" onclick="executeCreatePartition()"><?php echo $lang692; ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Format Partition -->
<div class="modal fade" id="formatModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="formatModalTitle"><?php echo $lang693; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="formatPartitionName">
                <div class="mb-3">
                    <label class="form-label"><?php echo $lang694; ?></label>
                    <select class="form-select" id="formatFs">
                        <option value="ext4">ext4 (Linux)</option>
                        <option value="ext3">ext3</option>
                        <option value="ext2">ext2</option>
                        <option value="ntfs">NTFS (Windows)</option>
                        <option value="fat32">FAT32</option>
                        <option value="exfat">exFAT</option>
                        <option value="xfs">XFS</option>
                        <option value="btrfs">Btrfs</option>
                        <option value="swap">Swap</option>
                    </select>
                </div>
                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="formatTypeRadio" id="quickFormatRadio" value="quick" checked>
                        <label class="form-check-label" for="quickFormatRadio"><?php echo $lang695; ?></label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="formatTypeRadio" id="fullFormatRadio" value="full">
                        <label class="form-check-label" for="fullFormatRadio"><?php echo $lang696; ?></label>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label"><?php echo $lang697; ?></label>
                    <input type="text" class="form-control" id="formatLabel" placeholder="<?php echo $lang698; ?>">
                </div>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo $lang699; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang700; ?></button>
                <button class="btn btn-primary" onclick="executeFormat()"><?php echo $lang701; ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Resize Partition -->
<div class="modal fade" id="resizeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-expand-alt"></i> <?php echo $lang702; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="resizePartitionName">
                <input type="hidden" id="resizePartitionFs">
                <div class="alert alert-info mb-3" id="resizeInfoAlert">
                    <i class="fas fa-info-circle"></i> <span id="resizeInfoText"><?php echo $lang703; ?></span>
                </div>
                <div class="mb-3">
                    <label class="form-label"><?php echo $lang704; ?>: <strong id="currentSizeLabel">0</strong> GB</label>
                    <div class="progress mb-2" style="height: 8px;">
                        <div id="sizeProgressBar" class="progress-bar bg-primary" role="progressbar" style="width: 0%"></div>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="useAllSpace">
                        <label class="form-check-label" for="useAllSpace">
                            <i class="fas fa-expand"></i> <?php echo $lang705; ?> (<span id="freeSpaceSpan">0</span> GB)
                        </label>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label"><?php echo $lang706; ?> (GB):</label>
                    <div class="input-group">
                        <input type="number" class="form-control" id="resizeSize" step="0.1" placeholder="<?php echo $lang707; ?>">
                        <button class="btn btn-outline-secondary" type="button" id="maxSizeBtn" title="Maximum size">
                            <i class="fas fa-arrow-up"></i> <?php echo $lang708; ?>
                        </button>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label"><?php echo $lang709; ?></label>
                    <input type="range" class="form-range" id="sizeSlider" min="0" max="100" step="0.1" value="0">
                    <div class="d-flex justify-content-between mt-1">
                        <small class="text-muted" id="minSizeLabel">0 GB</small>
                        <small class="text-muted" id="maxSizeLabel">0 GB</small>
                    </div>
                </div>
                <div id="resizeWarning" class="alert alert-warning mt-3" style="display: none;">
                    <i class="fas fa-exclamation-triangle"></i> <span id="resizeWarningText"></span>
                </div>
                <div class="alert alert-warning mt-2">
                    <i class="fas fa-exclamation-triangle"></i> <strong><?php echo $lang710; ?></strong> <?php echo $lang711; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang712; ?></button>
                <button class="btn btn-primary" onclick="executeResize()"><?php echo $lang713; ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Mount -->
<div class="modal fade" id="mountModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo $lang714; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="mountDevice">
                <div class="mb-3">
                    <label class="form-label"><?php echo $lang715; ?></label>
                    <input type="text" class="form-control" id="mountPoint" placeholder="/mnt/device">
                    <small class="text-muted"><?php echo $lang716; ?></small>
                </div>
                <div class="mb-3">
                    <label class="form-label"><?php echo $lang717; ?></label>
                    <select class="form-select" id="mountFs">
                        <option value="auto"><?php echo $lang718; ?></option>
                        <option value="ext4">ext4</option>
                        <option value="ntfs">ntfs</option>
                        <option value="vfat">fat32</option>
                        <option value="exfat">exfat</option>
                    </select>
                </div>
                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="addToFstab">
                        <label class="form-check-label" for="addToFstab"><?php echo $lang719; ?></label>
                    </div>
                </div>
                <div id="fstabOptionsDiv" style="display: none;">
                    <div class="mb-3">
                        <label class="form-label"><?php echo $lang720; ?></label>
                        <input type="text" class="form-control" id="fstabOptions" value="defaults">
                        <small class="text-muted"><?php echo $lang721; ?></small>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang722; ?></button>
                <button class="btn btn-primary" onclick="executeMount()"><?php echo $lang723; ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Smart Umount -->
<div class="modal fade" id="smartUmountModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-eject"></i> Smart Unmount</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="umountDeviceName">
                <input type="hidden" id="umountMountPoint">  
                <input type="hidden" id="umountIsMounted">
                <div id="umountInfoContent"><div class="text-center p-3">Loading...</div></div>
                <div id="umountFstabWarning" class="alert alert-danger mt-3" style="display: none;">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Warning!</strong> This partition is in fstab.
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" id="removeFromFstabCheckbox" checked>
                        <label class="form-check-label" for="removeFromFstabCheckbox">
                            <strong>Also remove from fstab</strong> (recommended)
                        </label>
                    </div>
                </div>
                <div id="umountNormalWarning" class="alert alert-info mt-3">
                    <i class="fas fa-info-circle"></i> 
                    The partition will be unmounted. The fstab entry will not be changed.
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-warning" onclick="executeSmartUmount(false)" id="umountOnlyBtn">
                    <i class="fas fa-eject"></i> Unmount
                </button>
                <button class="btn btn-danger" onclick="executeSmartUmount(true)" id="umountAndRemoveBtn">
                    <i class="fas fa-trash-alt"></i> Unmount & Remove
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Disk Info -->
<div class="modal fade" id="diskInfoModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-info-circle"></i> <?php echo $lang734; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="diskInfoContent"><div class="text-center p-4"><?php echo $lang735; ?></div></div>
            <div class="modal-footer">
                <button class="btn btn-primary" onclick="showSmartInfo(currentDiskName)"><?php echo $lang736; ?></button>
                <button class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang737; ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: SMART Info -->
<div class="modal fade" id="smartModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-chart-line"></i> <?php echo $lang738; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="smartContent"><div class="text-center p-4"><?php echo $lang739; ?></div></div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang740; ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Logs -->
<div class="modal fade" id="logsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-history"></i> <?php echo $lang741; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <button class="btn btn-sm btn-primary" onclick="refreshLogs()"><i class="fas fa-sync-alt"></i> <?php echo $lang742; ?></button>
                    <button class="btn btn-sm btn-danger" onclick="clearLogs()"><i class="fas fa-trash"></i> <?php echo $lang743; ?></button>
                </div>
                <div id="logsContent" class="logs-container"><div class="text-center"><?php echo $lang744; ?></div></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang745; ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Progress -->
<div class="modal fade" id="progressModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-spinner fa-spin"></i> <?php echo $lang746; ?></h5>
            </div>
            <div class="modal-body">
                <div id="progressMessage" class="text-center"><?php echo $lang747; ?></div>
                <div class="progress mt-3">
                    <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
                </div>
                <div id="progressDetails" style="font-size: 12px; color: #6c757d; margin-top: 10px; max-height: 200px; overflow-y: auto;"></div>
            </div>
        </div>
    </div>
</div>

<div class="loader-overlay" id="loader">
    <div class="loader-spinner"></div>
</div>

<div class="toast-container position-fixed bottom-0 end-0 p-3"></div>

<script src="lib/bootstrap-5.3.8-dist/js/bootstrap.bundle.min.js"></script>
<script src="lib/jquery-3.6.0-master/dist/jquery.min.js"></script>
<script>
// ==================== ГЛОБАЛЬНЫЕ ПЕРЕМЕННЫЕ ====================
const url = "<?php echo $current_host_id == 1 ? '/api/' : rtrim($host_url, '/') . '/'; ?>";
let currentDisk = null;
let currentDiskName = null;
let allDisks = [];
let allLvmData = { pvs: [], vgs: [], lvs: [] };
let fstabEntries = [];
let contextMenu = null;
let refreshInterval = null;
let isRefreshing = false;
let lastUsbCount = 0;
let progressModal = null;
let progressCheckInterval = null;
let shellOutputElement = null;
let shellCommandInput = null;
let currentFilter = 'all';
let currentLvInfo = null;
let currentSnapshotInfo = null;

// ==================== ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ ====================
function showLoader() { 
    const loader = document.getElementById('loader'); 
    if (loader) loader.style.display = 'flex'; 
    else console.warn('Loader element not found');
}

function getDeviceType(device) {
    const clean = device.replace(/^\/dev\//, '');
    
    if (device.includes('/dev/mapper/') || 
        (device.startsWith('/dev/') && 
         !device.startsWith('/dev/sd') && 
         !device.startsWith('/dev/nvme') && 
         !device.startsWith('/dev/md') &&
         !device.startsWith('/dev/loop') &&
         !device.startsWith('/dev/sr'))) {
        return 'lvm';
    }
    
    if (device.startsWith('/dev/md')) {
        return 'raid';
    }
    
    return 'disk';
}

async function unmountByMountPoint(mountPoint) {
    if (!mountPoint) {
        showToast('<?php echo $lang1265; ?>', 'danger');
        return;
    }
    
    if (!confirm(`Unmount ${mountPoint}?`)) return;
    
    showProgress(`Unmounting ${mountPoint}...`, true);
    const res = await apiCall('umount', { 
        device: mountPoint, 
        force: false, 
        remove_from_fstab: false 
    });
    hideProgress();
    
    if (res.success) {
        showToast(`Unmounted ${mountPoint}`, 'success');
        await refreshAll(true);
        if (currentDisk) renderDiskDetails(currentDisk);
    } else {
        showToast(res.error || '<?php echo $lang4720; ?>', 'danger');
    }
}

function hideLoader() { 
    const loader = document.getElementById('loader'); 
    if (loader) loader.style.display = 'none'; 
}

function showToast(message, type = 'success') {
    const container = document.querySelector('.toast-container');
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-white bg-${type === 'success' ? 'success' : (type === 'info' ? 'info' : 'danger')} border-0 show`;
    toast.setAttribute('role', 'alert');
    toast.innerHTML = `<div class="d-flex"><div class="toast-body">${message}</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>`;
    container.appendChild(toast);
    const bsToast = new bootstrap.Toast(toast, { autohide: true, delay: 5000 });
    bsToast.show();
    setTimeout(() => toast.remove(), 5500);
}

function showProgress(message, showBar = true) {
    const modalElement = document.getElementById('progressModal');
    if (!modalElement) {
        console.warn('progressModal element not found, creating fallback');
        showToast(message, 'info');
        return;
    }
    
    if (!progressModal) {
        progressModal = new bootstrap.Modal(modalElement, { backdrop: 'static', keyboard: false });
    }
    
    const messageEl = document.getElementById('progressMessage');
    const progressBarEl = document.getElementById('progressBar');
    const detailsEl = document.getElementById('progressDetails');
    
    if (messageEl) messageEl.innerHTML = message;
    if (progressBarEl) {
        if (showBar) {
            progressBarEl.style.width = '100%';
            progressBarEl.className = 'progress-bar progress-bar-striped progress-bar-animated';
        } else {
            progressBarEl.style.width = '0%';
        }
    }
    if (detailsEl) detailsEl.innerHTML = '';
    
    progressModal.show();
    
    if (progressCheckInterval) clearInterval(progressCheckInterval);
    progressCheckInterval = setInterval(checkOperationStatus, 2000);
}

function updateProgressDetails(details) {
    const container = document.getElementById('progressDetails');
    container.innerHTML += `<div>${new Date().toLocaleTimeString()}: ${details}</div>`;
    container.scrollTop = container.scrollHeight;
}

async function checkOperationStatus() {
    try {
        const res = await apiCall('get_operation_status');
        if (res.success && res.status) {
            if (res.status.progress) document.getElementById('progressBar').style.width = res.status.progress + '%';
            if (res.status.message) document.getElementById('progressMessage').innerHTML = res.status.message;
            if (res.status.progress >= 100) setTimeout(() => hideProgress(), 1000);
        }
    } catch(e) { console.error('Status check error:', e); }
}

async function hideProgress() {
    if (progressCheckInterval) { 
        clearInterval(progressCheckInterval); 
        progressCheckInterval = null; 
    }
    
    if (progressModal) {
        try {
            progressModal.hide();
        } catch(e) {
            console.warn('Error hiding progress modal:', e);
        }
    }
    
    await clearOperationStatus();
    
    setTimeout(() => {
        try {
            document.querySelectorAll('.modal-backdrop').forEach(backdrop => backdrop.remove());
            document.body.classList.remove('modal-open');
            document.body.style.overflow = '';
            document.body.style.paddingRight = '';
        } catch(e) {
            console.warn('Error cleaning up modal backdrop:', e);
        }
    }, 100);
}

function escapeHtml(text) { if (!text) return ''; const div = document.createElement('div'); div.textContent = text; return div.innerHTML; }

function formatSize(bytes) {
    if (!bytes || bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
}

function getFsClass(fstype) {
    if (!fstype) return 'none';
    const type = fstype.toLowerCase();
    if (type === 'ext2') return 'ext2';
    if (type === 'ext4') return 'ext4';
    if (type === 'ntfs') return 'ntfs';
    if (type === 'fat32') return 'fat32';
    if (type === 'exfat') return 'exfat';
    if (type === 'xfs') return 'xfs';
    if (type === 'btrfs') return 'btrfs';
    if (type === 'swap') return 'swap';
    return 'none';
}

// ==================== API ВЫЗОВЫ ====================
async function apiCall(action, data = {}) {
    try {
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 120000);
        
        const headers = { 'Content-Type': 'application/json' };
        if (window.apiConfig && window.apiConfig.apiKey) {
            headers['X-API-Key'] = window.apiConfig.apiKey;
        }
        
        const fullUrl = url + 'disk_api.php';
        console.log('API URL:', fullUrl);
        
        const res = await fetch(fullUrl, {
            method: 'POST',
            headers: headers,
            body: JSON.stringify({ action, ...data }),
            signal: controller.signal
        });
        
        clearTimeout(timeoutId);
        
        if (!res.ok) {
            throw new Error(`HTTP ${res.status}`);
        }
        
        return await res.json();
    } catch(e) {
        console.error('API Error:', e);
        if (e.name === 'AbortError') showToast('<?php echo $lang4721; ?>', 'danger');
        else showToast(e.message || '<?php echo $lang373; ?>', 'danger');
        return { success: false, error: e.message };
    }
}

async function lvmApiCall(action, data = {}) {
    try {
        const headers = { 'Content-Type': 'application/json' };
        
        if (window.apiConfig && window.apiConfig.apiKey) {
            headers['X-API-Key'] = window.apiConfig.apiKey;
        }
        
        const res = await fetch(url + 'lvm_api.php', {
            method: 'POST',
            headers: headers,
            body: JSON.stringify({ action, ...data })
        });
        return await res.json();
    } catch(e) {
        return { success: false, error: e.message };
    }
}

function clearOperationStatus() {
    const statusDiv = document.getElementById('operationStatus');
    if (statusDiv) {
        statusDiv.style.display = 'none';
        statusDiv.innerHTML = '';
    }
}

function showProgress(message) {
    const statusDiv = document.getElementById('operationStatus');
    if (statusDiv) {
        statusDiv.style.display = 'flex';
        statusDiv.innerHTML = `<div class="spinner-border spinner-border-sm me-2"></div> ${message}`;
    }
}


// ==================== ЗАГРУЗКА ДАННЫХ ====================
async function refreshAll(showLoading = false) {
    if (isRefreshing) return;
    isRefreshing = true;
    if (showLoading) showLoader();
    
    try {
        const res = await apiCall('get_all');
        if (res.success) {
            allDisks = res.disks || [];
            fstabEntries = res.fstab_entries || [];
        }
        
        const lvmRes = await lvmApiCall('get_all_lvm');
        if (lvmRes.success) {
            allLvmData = lvmRes;
        }
        
        renderSidebar();
        
        if (currentDisk) {
            const diskExists = allDisks.some(d => d.name === currentDisk);
            if (diskExists) renderDiskDetails(currentDisk);
            else { currentDisk = null; showEmptyState(); }
        }
        
        const usbCount = allDisks.filter(d => d.removable && !d.is_managed_by_lvm && !d.is_managed_by_raid).length;
        if (lastUsbCount !== 0 && lastUsbCount !== usbCount) {
            showToast(usbCount > lastUsbCount ? '🔌 <?php echo $lang748; ?>' : '⏏️ <?php echo $lang749; ?>', 'info');
        }
        lastUsbCount = usbCount;
        
        const connectionStatusEl = document.getElementById('connectionStatus');
        if (connectionStatusEl) {
            connectionStatusEl.className = 'badge bg-success me-2';
            connectionStatusEl.innerHTML = '● On-Line';
        }
    } catch(e) { 
        console.error('Refresh error:', e); 
        if (e.message) console.error('Error details:', e.message);
    }
    
    isRefreshing = false;
    if (showLoading) hideLoader();
}

function showEmptyState() {
    document.getElementById('diskDetailsPanel').innerHTML = `<div class="empty-state"><i class="fas fa-hdd fa-4x mb-3"></i><p><?php echo $lang750; ?></p></div>`;
}

// ==================== ФИЛЬТР ====================
function setupFilters() {
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            currentFilter = btn.getAttribute('data-filter');
            applyFilter();
        });
    });
}

function applyFilter() {
    const categories = document.querySelectorAll('.disk-category');
    const items = document.querySelectorAll('.disk-item');
    
    if (currentFilter === 'all') {
        categories.forEach(c => c.classList.remove('hidden-category'));
        items.forEach(i => i.classList.remove('hidden-category'));
        return;
    }
    
    categories.forEach(c => c.classList.add('hidden-category'));
    items.forEach(i => i.classList.add('hidden-category'));
    
    if (currentFilter === 'physical') {
        const visibleCategories = ['system', 'internal', 'usb'];
        categories.forEach(c => {
            const cat = c.getAttribute('data-category');
            if (visibleCategories.includes(cat)) c.classList.remove('hidden-category');
        });
        items.forEach(i => {
            if (i.classList.contains('physical-disk') && !i.classList.contains('managed-lvm') && !i.classList.contains('managed-raid')) {
                i.classList.remove('hidden-category');
            }
        });
    } else if (currentFilter === 'usb') {
        const visibleCategories = ['usb'];
        categories.forEach(c => {
            const cat = c.getAttribute('data-category');
            if (visibleCategories.includes(cat)) c.classList.remove('hidden-category');
        });
        items.forEach(i => {
            if (i.classList.contains('usb-disk')) i.classList.remove('hidden-category');
        });
    } else if (currentFilter === 'lvm') {
        const visibleCategories = ['lvm-vg', 'lvm-pv'];
        categories.forEach(c => {
            const cat = c.getAttribute('data-category');
            if (visibleCategories.includes(cat)) c.classList.remove('hidden-category');
        });
        items.forEach(i => {
            if (i.classList.contains('virtual-lvm') || i.classList.contains('managed-lvm')) {
                i.classList.remove('hidden-category');
            }
        });
    } else if (currentFilter === 'raid') {
        const visibleCategories = ['raid-array', 'raid-member'];
        categories.forEach(c => {
            const cat = c.getAttribute('data-category');
            if (visibleCategories.includes(cat)) c.classList.remove('hidden-category');
        });
        items.forEach(i => {
            if (i.classList.contains('virtual-raid') || i.classList.contains('managed-raid')) {
                i.classList.remove('hidden-category');
            }
        });
    }
}

// ==================== РЕНДЕР САЙДБАРА ====================
function renderSidebar() {
    const physicalDisks = allDisks.filter(d => !d.is_virtual);
    
    const lvmVgs = allDisks.filter(d => d.is_virtual && d.virtual_type === 'lvm_vg');
    const raidArrays = allDisks.filter(d => d.is_virtual && d.virtual_type === 'raid_array');
    const lvmPvDisks = physicalDisks.filter(d => d.is_managed_by_lvm === true);
    const raidMemberDisks = physicalDisks.filter(d => d.is_managed_by_raid === true);
    const systemDisks = physicalDisks.filter(d => d.is_system && !d.is_managed_by_lvm && !d.is_managed_by_raid);
    const internalDisks = physicalDisks.filter(d => !d.removable && !d.is_system && !d.is_managed_by_lvm && !d.is_managed_by_raid);
    const externalDisks = physicalDisks.filter(d => d.removable && !d.is_managed_by_lvm && !d.is_managed_by_raid);
    
    const renderDisk = (disk, category) => {
        const isUninit = !disk.has_partition_table && !disk.is_system && !disk.is_virtual;
        const isVirtualLvm = disk.is_virtual && disk.virtual_type === 'lvm_vg';
        const isVirtualRaid = disk.is_virtual && disk.virtual_type === 'raid_array';
        const isManagedByLvm = disk.is_managed_by_lvm === true;
        const isManagedByRaid = disk.is_managed_by_raid === true;
        
        let virtualClass = '';
        let diskClass = 'physical-disk';
        if (isVirtualLvm) { virtualClass = 'virtual-lvm'; diskClass = 'virtual-lvm'; }
        else if (isVirtualRaid) { virtualClass = 'virtual-raid'; diskClass = 'virtual-raid'; }
        else if (isManagedByLvm) { virtualClass = 'managed-lvm'; diskClass = 'managed-lvm'; }
        else if (isManagedByRaid) { virtualClass = 'managed-raid'; diskClass = 'managed-raid'; }
        
        let usbClass = '';
        if (disk.removable && !isManagedByLvm && !isManagedByRaid) usbClass = 'usb-disk';
        
        const tempClass = disk.temperature ? (disk.temperature > 60 ? 'text-danger' : (disk.temperature > 50 ? 'text-warning' : 'text-success')) : '';
        
        let icon = '<i class="fas fa-hdd"></i>';
        let iconClass = '';
        if (isVirtualLvm) { icon = '<i class="fas fa-cubes"></i>'; iconClass = 'virtual-icon'; }
        else if (isVirtualRaid) { icon = '<i class="fas fa-server"></i>'; iconClass = 'raid-icon'; }
        else if (isManagedByLvm) { icon = '<i class="fas fa-cubes"></i>'; iconClass = 'lvm-icon'; }
        else if (isManagedByRaid) { icon = '<i class="fas fa-shield-alt"></i>'; iconClass = 'raid-member-icon'; }
        
        let sizeDisplay = formatSize(disk.size_bytes);
        if (isVirtualLvm && disk.free_bytes) {
            const usedPercent = ((disk.size_bytes - disk.free_bytes) / disk.size_bytes * 100).toFixed(1);
            sizeDisplay = `${formatSize(disk.size_bytes)} (исп. ${usedPercent}%)`;
        }
        
        let badges = '';
        if (disk.is_system && !isManagedByLvm && !isManagedByRaid) badges += '<span class="disk-badge system"><?php echo $lang751; ?></span>';
        if (disk.removable && !isManagedByLvm && !isManagedByRaid) badges += '<span class="disk-badge usb"><?php echo $lang752; ?></span>';
        if (isUninit && !isManagedByLvm && !isManagedByRaid) badges += '<span class="disk-badge uninit"><?php echo $lang753; ?></span>';
        if (isVirtualLvm) badges += '<span class="disk-badge lvm-vg"><?php echo $lang754; ?></span>';
        if (isVirtualRaid) badges += `<span class="disk-badge raid-array">RAID ${disk.raid_level || ''}</span>`;
        if (isManagedByLvm) badges += '<span class="disk-badge lvm-pv"><?php echo $lang755; ?></span>';
        if (isManagedByRaid) badges += '<span class="disk-badge raid-member"><?php echo $lang756; ?></span>';
        if (disk.partition_table_type && !disk.is_virtual && !isManagedByLvm && !isManagedByRaid) badges += `<span class="disk-badge ${disk.partition_table_type}">${disk.partition_table_type.toUpperCase()}</span>`;
        if (disk.temperature) badges += `<span class="${tempClass}" style="font-size: 10px;">🌡️ ${disk.temperature}°C</span>`;
        
        let title = '';
        if (isManagedByLvm) title = '<?php echo $lang757; ?>';
        else if (isManagedByRaid) title = '<?php echo $lang758; ?>';
        else if (isVirtualLvm) title = '<?php echo $lang759; ?>';
        else if (isVirtualRaid) title = '<?php echo $lang760; ?>';
        
        let onClickHandler = `selectDisk('${disk.name}', event)`;
        
        return `
            <div class="disk-item ${isUninit ? 'uninitialized' : ''} ${virtualClass} ${usbClass} ${diskClass}" 
                 data-disk="${disk.name}" 
                 data-category="${category}"
                 onclick="${onClickHandler}" 
                 title="${title}">
                <div class="disk-icon ${iconClass}">${icon}</div>
                <div class="disk-info">
                    <div class="disk-title">${disk.name} ${badges}</div>
                    <div class="disk-subtitle">${sizeDisplay} ${disk.model ? '• ' + disk.model : ''}</div>
                    ${isVirtualLvm && disk.lv_count ? `<div class="disk-subtitle"><i class="fas fa-database"></i> LV: ${disk.lv_count}, PV: ${disk.pv_count}</div>` : ''}
                    ${isVirtualRaid && disk.component_count ? `<div class="disk-subtitle"><i class="fas fa-hdd"></i> <?php echo $lang761; ?>: ${disk.component_count}${disk.is_degraded ? ' (DEGRADED!)' : ''}</div>` : ''}
                    ${isManagedByLvm && disk.lvm_info ? `<div class="disk-subtitle"><i class="fas fa-cubes"></i> VG: ${disk.lvm_info.vg_name}</div>` : ''}
                    ${isManagedByRaid && disk.raid_info ? `<div class="disk-subtitle"><i class="fas fa-server"></i> RAID: ${disk.raid_info.raid_name}</div>` : ''}
                </div>
            </div>
        `;
    };
    
    document.getElementById('lvmVgsList').innerHTML = lvmVgs.length ? lvmVgs.map(vg => renderDisk(vg, 'lvm-vg')).join('') : '<div class="text-muted p-3 text-center">—</div>';
    document.getElementById('raidArraysList').innerHTML = raidArrays.length ? raidArrays.map(raid => renderDisk(raid, 'raid-array')).join('') : '<div class="text-muted p-3 text-center">—</div>';
    document.getElementById('lvmPvList').innerHTML = lvmPvDisks.length ? lvmPvDisks.map(d => renderDisk(d, 'lvm-pv')).join('') : '<div class="text-muted p-3 text-center">—</div>';
    document.getElementById('raidMemberList').innerHTML = raidMemberDisks.length ? raidMemberDisks.map(d => renderDisk(d, 'raid-member')).join('') : '<div class="text-muted p-3 text-center">—</div>';
    document.getElementById('systemDisksList').innerHTML = systemDisks.length ? systemDisks.map(d => renderDisk(d, 'system')).join('') : '<div class="text-muted p-3 text-center">—</div>';
    document.getElementById('internalDisksList').innerHTML = internalDisks.length ? internalDisks.map(d => renderDisk(d, 'internal')).join('') : '<div class="text-muted p-3 text-center">—</div>';
    document.getElementById('externalDisksList').innerHTML = externalDisks.length ? externalDisks.map(d => renderDisk(d, 'usb')).join('') : '<div class="text-muted p-3 text-center">—</div>';
    
    applyFilter();
    
    if (currentDisk) {
        document.querySelectorAll('.disk-item').forEach(el => {
            if (el.getAttribute('data-disk') === currentDisk) el.classList.add('active');
        });
    }
}

// ==================== ВЫБОР ДИСКА ====================
function selectDisk(diskName, event) {
    const disk = allDisks.find(d => d.name === diskName);
    
    if (disk && disk.is_managed_by_lvm === true) {
        showManagedDiskMessage(disk, 'lvm');
        return;
    }
    
    if (disk && disk.is_managed_by_raid === true) {
        showManagedDiskMessage(disk, 'raid');
        return;
    }
    
    currentDisk = diskName;
    currentDiskName = diskName;
    renderDiskDetails(diskName);
    document.querySelectorAll('.disk-item').forEach(el => el.classList.remove('active'));
    if (event && event.currentTarget) event.currentTarget.classList.add('active');
}

function showManagedDiskMessage(disk, type) {
    const isLvm = type === 'lvm';
    const managerUrl = isLvm ? 'lvm_manager.php' : 'raid_manager.php';
    const managerName = isLvm ? 'LVM Manager' : 'RAID Manager';
    const vgName = disk.lvm_info?.vg_name || '';
    const raidName = disk.raid_info?.raid_name || '';
    
    const html = `
        <div class="disk-header">
            <div class="disk-icon" style="width: 48px; height: 48px; font-size: 24px; background: ${isLvm ? '#f0f4ff' : '#fff0f0'}">
                <i class="fas ${isLvm ? 'fa-cubes' : 'fa-shield-alt'}"></i>
            </div>
            <div>
                <h3 class="mb-1">${escapeHtml(disk.name)}</h3>
                <p class="text-muted mb-0">${formatSize(disk.size_bytes)} • ${escapeHtml(disk.model || 'Disk')}</p>
            </div>
            <div class="ms-auto">
                <button class="btn btn-outline-info btn-sm" onclick="window.location.href='${managerUrl}'">
                    <i class="fas fa-external-link-alt"></i> <?php echo $lang762; ?> ${managerName}
                </button>
            </div>
        </div>
        <div class="alert alert-warning mt-3">
            <i class="fas fa-exclamation-triangle"></i>
            <strong><?php echo $lang763; ?></strong> <?php echo $lang764; ?> ${isLvm ? '<?php echo $lang765; ?>' : '<?php echo $lang766; ?>'}.
            <br><?php echo $lang767; ?> 
            <a href="${managerUrl}" class="alert-link">${managerName}</a>.
            ${isLvm && vgName ? `<br><br><strong><?php echo $lang768; ?>:</strong> ${vgName}` : ''}
            ${!isLvm && raidName ? `<br><br><strong><?php echo $lang769; ?>:</strong> ${raidName}` : ''}
        </div>
        <div class="empty-state">
            <i class="fas fa-${isLvm ? 'cubes' : 'server'} fa-4x mb-3 text-muted"></i>
            <p>This disk is controlled via ${managerName}</p>
            <button class="btn btn-primary" onclick="window.location.href='${managerUrl}'">
                <i class="fas fa-external-link-alt"></i> <?php echo $lang770; ?>
            </button>
        </div>
    `;
    
    document.getElementById('diskDetailsPanel').innerHTML = html;
    currentDisk = null;
    currentDiskName = null;
}

// ==================== РЕНДЕР ДЕТАЛЕЙ ДИСКА ====================
function renderDiskDetails(diskName) {
    const disk = allDisks.find(d => d.name === diskName);
    if (!disk) return;
    
    if (disk.is_virtual && disk.virtual_type === 'lvm_vg') {
        renderLvmVgDetails(disk);
        return;
    }
    
    if (disk.is_virtual && disk.virtual_type === 'raid_array') {
        renderRaidArrayDetails(disk);
        return;
    }
    
    renderPhysicalDiskDetails(disk);
}

function renderPhysicalDiskDetails(disk) {
    if (!disk.has_partition_table && !disk.is_system) {
        const initHtml = `
            <div class="disk-header">
                <div class="disk-icon" style="width: 48px; height: 48px; font-size: 24px;"><i class="fas fa-hdd"></i></div>
                <div><h3 class="mb-1">${escapeHtml(disk.name)}</h3><p class="text-muted mb-0">${formatSize(disk.size_bytes)} • ${escapeHtml(disk.model || 'Disk')}</p></div>
                <div class="ms-auto">
                    <button class="btn btn-outline-info btn-sm me-2" onclick="showDiskInfo('${disk.name}')"><i class="fas fa-info-circle"></i> <?php echo $lang771; ?></button>
                    <button class="btn btn-outline-primary btn-sm" onclick="showSmartInfo('${disk.name}')"><i class="fas fa-chart-line"></i> SMART</button>
                </div>
            </div>
            <div class="init-card"><h4><i class="fas fa-exclamation-triangle"></i> <?php echo $lang772; ?></h4><p><?php echo $lang773; ?></p><button class="btn btn-light" onclick="showInitModal('${disk.name}')"><i class="fas fa-magic"></i> <?php echo $lang774; ?></button></div>
        `;
        document.getElementById('diskDetailsPanel').innerHTML = initHtml;
        return;
    }
    
    // ========== ДЕДУПЛИКАЦИЯ РАЗДЕЛОВ ПО ИМЕНИ ==========
    const rawPartitions = disk.partitions || [];
    const uniquePartitions = [];
    const seenNames = new Set();
    
    for (const part of rawPartitions) {
        if (!seenNames.has(part.name)) {
            seenNames.add(part.name);
            uniquePartitions.push(part);
        }
    }
    const partitions = uniquePartitions;
    
    const validPartitions = partitions.filter(part => part.size_bytes > 1024 * 1024);
    
    const totalSize = disk.size_bytes;
    
    let usedBytes = 0;
    for (const part of validPartitions) { 
        usedBytes += part.size_bytes; 
    }
    let freeBytes = totalSize - usedBytes;
    
    if (freeBytes < 0) {
        console.warn('Negative free space detected, recalculating...');
        freeBytes = 0;
    }
    
    const usedPercent = totalSize > 0 ? (usedBytes / totalSize) * 100 : 0;
    const freePercent = totalSize > 0 ? 100 - usedPercent : 0;
    const freeSpaceGb = (freeBytes / 1024 / 1024 / 1024).toFixed(1);
    const usedSpaceGb = (usedBytes / 1024 / 1024 / 1024).toFixed(1);
    const totalSizeGb = (totalSize / 1024 / 1024 / 1024).toFixed(1);
    
    // ========== ВИЗУАЛИЗАЦИЯ РАЗДЕЛОВ ==========
    let visualHtml = '';
    const processedInVisual = new Set();
    
    for (const part of validPartitions) {
        if (processedInVisual.has(part.name)) continue;
        processedInVisual.add(part.name);
        
        const percent = totalSize > 0 ? (part.size_bytes / totalSize) * 100 : 0;
        if (percent < 0.1) continue;
        
        const fsClass = part.has_filesystem ? getFsClass(part.fstype) : 'no-fs';
        const title = `${part.name} | ${part.has_filesystem ? (part.fstype || 'unknown') : 'NOT FORMATTED'} | ${formatSize(part.size_bytes)}`;
        visualHtml += `<div class="part-visual ${fsClass}" style="width: ${percent}%;" title="${escapeHtml(title)}">${percent > 8 ? part.name : ''}</div>`;
    }
    
    if (freePercent > 0.5 && validPartitions.length > 0) {
        visualHtml += `<div class="part-visual free" style="width: ${freePercent}%;" title="Free space: ${formatSize(freeBytes)} (${freePercent.toFixed(1)}%)">${freePercent > 8 ? 'Free' : ''}</div>`;
    }
    
    if (validPartitions.length === 0 && freePercent > 0) {
        visualHtml = `<div class="part-visual free" style="width: 100%;" title="Free space: ${formatSize(freeBytes)}"><?php echo $lang775; ?> (${formatSize(freeBytes)})</div>`;
    }
    
    // ========== ТАБЛИЦА РАЗДЕЛОВ ==========
    let tableHtml = '';
    const processedInTable = new Set();
    
    for (const part of validPartitions) {
        if (processedInTable.has(part.name)) continue;
        processedInTable.add(part.name);
        
        const fsClass = part.has_filesystem ? getFsClass(part.fstype) : 'none';
        const hasFstab = part.fstab_entry !== null;
        const hasFilesystem = part.has_filesystem === true;
        const noFsWarning = !hasFilesystem ? '<span class="no-fs-warning"><i class="fas fa-exclamation-triangle"></i> <?php echo $lang776; ?></span>' : '';
        const isMounted = part.mount_point !== null && part.mount_point !== '';
        
        let actionBtns = `
            <div class="btn-group btn-group-sm">
                ${!isMounted && hasFilesystem ? `<button class="btn btn-outline-success" onclick="showMountModal('${part.name}')" title="<?php echo $lang777; ?>"><i class="fas fa-play"></i> <?php echo $lang777; ?></button>` : ''}
                <button class="btn btn-outline-primary" onclick="showFormatModal('${part.name}')" title="<?php echo $lang778; ?>"><i class="fa fa-eraser"></i> <?php echo $lang778; ?></button>
                ${isMounted ? `<button class="btn btn-outline-warning" onclick="umountPartition('${part.name}')" title="<?php echo $lang779; ?>"><i class="fas fa-eject"></i> <?php echo $lang779; ?></button>` : ''}
                ${hasFilesystem && part.fstype !== 'swap' && part.fstype !== 'unknown' ? `<button class="btn btn-outline-info" onclick="showResizeModal('${part.name}', '${part.fstype}')" title="<?php echo $lang780; ?>"><i class="fas fa-expand-alt"></i> <?php echo $lang780; ?></button>` : ''}
                <button class="btn btn-outline-danger" onclick="deletePartition('${part.name}')" title="<?php echo $lang781; ?>"><i class="fas fa-trash"></i> <?php echo $lang781; ?></button>
            </div>
        `;
        
        const safePartName = part.name.replace(/'/g, "\\'");
        const safeFsType = (part.fstype || '').replace(/'/g, "\\'");
        
        tableHtml += `
            <tr data-partition="${part.name}" class="${!hasFilesystem ? 'no-fs' : ''}" oncontextmenu="showPartitionContextMenu(event, '${disk.name}', '${safePartName}', '${safeFsType}', ${isMounted}, ${hasFilesystem})">
                <td><code>${escapeHtml(part.name)}</code> ${noFsWarning}${isMounted ? '<span class="mounted-indicator" title="Mounted"></span>' : ''}</td>
                <td>${formatSize(part.size_bytes)}</td>
                <td><span class="fs-badge fs-${fsClass}">${part.has_filesystem ? (part.fstype || '—') : 'No FS'}</span></td>
                <td>${part.mount_point && part.mount_point !== '' ? escapeHtml(part.mount_point) : '<span class="text-muted">—</span>'}</td>
                <td>${hasFstab ? '<span class="fstab-badge"><i class="fas fa-bookmark"></i> fstab</span>' : '—'}</td>
                <td class="text-end">${actionBtns}</td>
            </tr>
        `;
    }
    
    // ========== ИНФОРМАЦИЯ О СВОБОДНОМ МЕСТЕ ==========
    const freeSpaceHtml = freePercent > 0 ? `
        <div class="alert alert-info mt-3" style="font-size: 13px; background: #e7f1ff; border: none;">
            <i class="fas fa-chart-pie"></i> <strong><?php echo $lang782; ?></strong><br>
            <div class="row mt-2">
                <div class="col-md-4"><small><?php echo $lang783; ?> <strong>${totalSizeGb} GB</strong></small></div>
                <div class="col-md-4"><small><?php echo $lang784; ?> <strong>${usedSpaceGb} GB</strong> (${usedPercent.toFixed(1)}%)</small></div>
                <div class="col-md-4"><small><?php echo $lang785; ?> <strong>${freeSpaceGb} GB</strong> (${freePercent.toFixed(1)}%)</small></div>
            </div>
            ${freePercent > 1 ? `<hr class="my-2"><small class="text-success"><i class="fas fa-plus-circle"></i> <?php echo $lang786; ?> <strong>${freeSpaceGb} GB</strong></small>` : '<small class="text-danger"><i class="fas fa-exclamation-triangle"></i> <?php echo $lang787; ?></small>'}
        </div>
    ` : '';
    
    // ========== ПРЕДУПРЕЖДЕНИЕ ДЛЯ MBR > 2TB ==========
    const mbrWarningHtml = (disk.partition_table_type === 'mbr' && totalSize > 2 * 1024 * 1024 * 1024 * 1024) ? `
        <div class="alert alert-warning mt-2"><i class="fas fa-exclamation-triangle"></i> <strong><?php echo $lang788; ?></strong> <?php echo $lang789; ?></div>
    ` : '';
    
    // ========== ОЧИСТКА И РЕНДЕР ==========
    const panel = document.getElementById('diskDetailsPanel');
    panel.innerHTML = '';
    
    const html = `
        <div class="disk-header">
            <div class="disk-icon" style="width: 48px; height: 48px; font-size: 24px;"><i class="fas ${disk.type === 'nvme' ? 'fa-microchip' : (disk.removable ? 'fa-eject' : 'fa-hdd')}"></i></div>
            <div><h3 class="mb-1">${escapeHtml(disk.name)}</h3><p class="text-muted mb-0">${formatSize(disk.size_bytes)} • ${escapeHtml(disk.model || 'Disk')} • Table: ${disk.partition_table_type?.toUpperCase() || '?'} • Type: ${disk.is_rotational ? 'HDD' : 'SSD'}${disk.temperature ? ` • 🌡️ ${disk.temperature}°C` : ''}</p></div>
            <div class="ms-auto">
                ${disk.removable ? `<button class="btn btn-outline-danger btn-sm me-2" onclick="safeRemoveDisk('${disk.name}')" title="<?php echo $lang790; ?>"><i class="fas fa-eject"></i> <?php echo $lang790; ?></button>` : ''}
                <button class="btn btn-outline-info btn-sm me-2" onclick="showDiskInfo('${disk.name}')"><i class="fas fa-info-circle"></i> <?php echo $lang791; ?></button>
                <button class="btn btn-outline-primary btn-sm" onclick="showSmartInfo('${disk.name}')"><i class="fas fa-chart-line"></i> SMART</button>
            </div>
        </div>
        ${validPartitions.length > 0 ? `
        <div class="disk-map">
            <div class="partition-visual" style="display: flex; flex-wrap: nowrap; width: 100%; overflow: hidden; border-radius: 6px;">${visualHtml || '<div class="text-muted text-center p-3"><?php echo $lang792; ?></div>'}</div>
            <div class="free-space-info mt-2"><small class="text-muted"><i class="fas fa-mouse-pointer"></i> <?php echo $lang794; ?> | <i class="fas fa-arrows-alt"></i> <?php echo $lang793; ?></small></div>
        </div>
        ` : ''}
        <div class="disk-toolbar">
            <button class="btn btn-sm btn-primary" onclick="showCreatePartitionModal('${disk.name}')"><i class="fas fa-plus"></i> <?php echo $lang795; ?></button>
            <button class="btn btn-sm btn-outline-danger" onclick="showReinitModal('${disk.name}')"><i class="fas fa-table"></i> <?php echo $lang796; ?></button>
            <button class="btn btn-sm btn-outline-info" onclick="viewFstab()"><i class="fas fa-bookmark"></i> <?php echo $lang797; ?></button>
            <button class="btn btn-sm btn-outline-secondary" onclick="refreshAll(true)"><i class="fas fa-sync-alt"></i> <?php echo $lang798; ?></button>
        </div>
        ${freeSpaceHtml}
        ${mbrWarningHtml}
        ${tableHtml ? `<table class="partition-table mt-3"><thead><tr><th><?php echo $lang799; ?></th><th><?php echo $lang800; ?></th><th><?php echo $lang801; ?></th><th><?php echo $lang802; ?></th><th><?php echo $lang803; ?></th><th class="text-end"><?php echo $lang804; ?></th></tr></thead><tbody>${tableHtml}</tbody></table>` : `<div class="empty-state mt-4"><i class="fas fa-hdd fa-3x mb-3 text-muted"></i><p class="text-muted"><?php echo $lang805; ?></p><button class="btn btn-sm btn-primary" onclick="showCreatePartitionModal('${disk.name}')"><i class="fas fa-plus"></i> <?php echo $lang806; ?></button></div>`}
    `;
    
    panel.innerHTML = html;
}

function renderLvmVgDetails(vg) {
    const partitions = vg.partitions || [];
    const totalSize = vg.size_bytes;
    const freeBytes = vg.free_bytes || 0;
    const usedBytes = totalSize - freeBytes;
    const usedPercent = totalSize > 0 ? (usedBytes / totalSize) * 100 : 0;
    const freePercent = totalSize > 0 ? (freeBytes / totalSize) * 100 : 0;
    
    let visualHtml = '';
    
    if (partitions.length > 0) {
        for (const part of partitions) {
            const percent = (part.size_bytes / totalSize) * 100;
            const fsClass = part.has_filesystem ? getFsClass(part.fstype) : 'no-fs';
            const title = `${part.name} | ${part.has_filesystem ? (part.fstype || 'unknown') : 'NOT FORMATTED'} | ${formatSize(part.size_bytes)}`;
            
            const minWidthForText = 6;
            const showLabel = percent >= minWidthForText;
            
            visualHtml += `<div class="part-visual ${fsClass}" style="width: ${percent}%; min-width: ${percent > 0 ? '2px' : '0'};" title="${escapeHtml(title)}">`;
            if (showLabel) {
                visualHtml += `<span class="part-label">${escapeHtml(part.name)}</span>`;
            }
            visualHtml += `</div>`;
        }
    }
    
    let freeSpaceVisual = '';
    if (freePercent > 0.5 && partitions.length > 0) {
        const minWidthForFreeText = 8;
        const showFreeLabel = freePercent >= minWidthForFreeText;
        freeSpaceVisual = `<div class="part-visual free" style="width: ${freePercent}%; min-width: 2px;" title="<?php echo $lang807; ?> ${formatSize(freeBytes)} (${freePercent.toFixed(1)}%)">`;
        if (showFreeLabel) {
            freeSpaceVisual += `<span class="part-label"><?php echo $lang808; ?></span>`;
        }
        freeSpaceVisual += `</div>`;
    }
    
    if (partitions.length === 0 && freePercent > 0) {
        freeSpaceVisual = `<div class="part-visual free" style="width: 100%;" title="<?php echo $lang809; ?> ${formatSize(freeBytes)}">`;
        freeSpaceVisual += `<span class="part-label"><?php echo $lang810; ?> (${formatSize(freeBytes)})</span>`;
        freeSpaceVisual += `</div>`;
    }
    
    let tableHtml = '';
    for (const part of partitions) {
        const fsClass = part.has_filesystem ? getFsClass(part.fstype) : 'none';
        const hasFstab = part.fstab_entry !== null;
        const hasFilesystem = part.has_filesystem === true;
        const noFsWarning = !hasFilesystem ? '<span class="no-fs-warning"><i class="fas fa-exclamation-triangle"></i> <?php echo $lang811; ?></span>' : '';
        const isMounted = part.mount_point !== null;
		const mountDeviceName = part.name;
        
        const isSnapshot = part.is_snapshot === true;
        const isActive = part.is_active !== false;
        
        let extraBadges = '';
        if (!isActive) extraBadges += '<span class="badge bg-secondary ms-1"><?php echo $lang812; ?></span>';
        if (isSnapshot) extraBadges += '<span class="badge bg-info ms-1"><?php echo $lang813; ?></span>';
        
        let actionBtns = `
            <div class="btn-group btn-group-sm">
                ${!isMounted && hasFilesystem ? `<button class="btn btn-outline-success" onclick="showMountModal('${part.path || part.name}')" title="<?php echo $lang814; ?>"><i class="fa fa-play"></i> <?php echo $lang814; ?></button>` : ''}
                <button class="btn btn-outline-primary" onclick="showFormatLvModal('${vg.name}', '${part.name}')" title="<?php echo $lang815; ?>"><i class="fa fa-eraser"></i> <?php echo $lang815; ?></button>
                ${isMounted ? `<button class="btn btn-outline-warning" onclick="umountPartition('${part.path || part.name}')" title="<?php echo $lang816; ?>"><i class="fas fa-eject"></i> <?php echo $lang816; ?></button>` : ''}
                <button class="btn btn-outline-info" onclick="showExtendLvModal('${vg.name}', '${part.name}', '${formatSize(part.size_bytes)}')" title="<?php echo $lang817; ?>"><i class="fas fa-expand-alt"></i> <?php echo $lang817; ?></button>
                <button class="btn btn-outline-secondary" onclick="showRenameLvModal('${vg.name}', '${part.name}')" title="<?php echo $lang818; ?>"><i class="fas fa-pen"></i> <?php echo $lang818; ?></button>
                ${!isSnapshot ? `<button class="btn btn-outline-info" onclick="showCreateSnapshotModal('${vg.name}', '${part.name}')" title="<?php echo $lang820; ?>"><i class="fas fa-camera"></i> <?php echo $lang819; ?></button>` : ''}
                <button class="btn btn-outline-danger" onclick="showDeleteLvConfirm('${vg.name}', '${part.name}')" title="<?php echo $lang822; ?>"><i class="fas fa-trash"></i> <?php echo $lang821; ?></button>
            </div>
        `;
        
        if (isSnapshot) {
            actionBtns = `
                <div class="btn-group btn-group-sm">
                    ${!isMounted && hasFilesystem ? `<button class="btn btn-outline-success" onclick="showMountModal('${part.path || part.name}')" title="<?php echo $lang823; ?>"><i class="fa fa-play"></i> <?php echo $lang823; ?></button>` : ''}
                    ${isMounted ? `<button class="btn btn-outline-warning" onclick="umountPartition('${part.path || part.name}')" title="<?php echo $lang824; ?>"><i class="fas fa-eject"></i> <?php echo $lang824; ?></button>` : ''}
                    <button class="btn btn-outline-warning" onclick="restoreSnapshot('${vg.name}', '${part.name}', '${part.parent_lv || ''}')" title="<?php echo $lang826; ?>"><i class="fas fa-undo"></i> <?php echo $lang825; ?></button>
                    <button class="btn btn-outline-danger" onclick="deleteSnapshot('${vg.name}', '${part.name}')" title="<?php echo $lang828; ?>"><i class="fas fa-trash"></i> <?php echo $lang827; ?></button>
                </div>
            `;
        }
        
        tableHtml += `
            <tr data-partition="${part.name}" class="${!hasFilesystem ? 'no-fs' : ''} ${isSnapshot ? 'snapshot-row' : ''}">
                <td><code>${escapeHtml(part.name)}</code> ${noFsWarning}${extraBadges}${isMounted ? '<span class="mounted-indicator" title="<?php echo $lang829; ?>"></span>' : ''}</td>
                <td>${formatSize(part.size_bytes)}</td>
                <td><span class="fs-badge fs-${fsClass}">${part.has_filesystem ? (part.fstype || '—') : 'No FS'}</span></td>
                <td>${part.mount_point || '<span class="text-muted">—</span>'}</td>
                <td>${hasFstab ? '<span class="fstab-badge"><i class="fas fa-bookmark"></i> fstab</span>' : '—'}</td>
                <td class="text-end">${actionBtns}</td>
            </tr>
        `;
    }
    
    const freeSpaceHtml = freeBytes > 0 ? `
        <div class="alert alert-info mt-3" style="font-size: 13px; background: #e7f1ff; border: none;">
            <i class="fas fa-chart-pie"></i> <strong><?php echo $lang830; ?></strong><br>
            <div class="row mt-2">
                <div class="col-md-4"><small><?php echo $lang831; ?> <strong>${formatSize(totalSize)}</strong></small></div>
                <div class="col-md-4"><small><?php echo $lang832; ?> <strong>${formatSize(usedBytes)}</strong> (${usedPercent.toFixed(1)}%)</small></div>
                <div class="col-md-4"><small><?php echo $lang833; ?> <strong>${formatSize(freeBytes)}</strong> (${freePercent.toFixed(1)}%)</small></div>
            </div>
            ${freePercent > 1 ? `<hr class="my-2"><small class="text-success"><i class="fas fa-plus-circle"></i> <?php echo $lang834; ?> <strong>${formatSize(freeBytes)}</strong></small>` : ''}
        </div>
    ` : '';
    
    const createButtons = freeBytes > 0 ? `
        <button class="btn btn-sm btn-primary" onclick="showCreateLvModal('${vg.name}', ${freeBytes})">
            <i class="fas fa-plus"></i> <?php echo $lang835; ?>
        </button>
        <button class="btn btn-sm btn-outline-info" onclick="showSnapshotsList()">
            <i class="fas fa-camera-retro"></i> <?php echo $lang836; ?>
        </button>
    ` : `<button class="btn btn-sm btn-secondary" disabled title="No place in VG">
        <i class="fas fa-plus"></i> <?php echo $lang837; ?>
    </button>`;
    
    const combinedVisual = visualHtml + freeSpaceVisual;
    
    const html = `
        <div class="disk-header">
            <div class="disk-icon" style="width: 48px; height: 48px; font-size: 24px; background: #f0f4ff">
                <i class="fas fa-cubes"></i>
            </div>
            <div>
                <h3 class="mb-1">${escapeHtml(vg.name)}</h3>
                <p class="text-muted mb-0"><?php echo $lang838; ?> • ${formatSize(totalSize)} • LV: ${vg.lv_count || 0}, PV: ${vg.pv_count || 0}${vg.is_active === false ? ' • INACTIVE' : ''}</p>
            </div>
            <div class="ms-auto">
                <button class="btn btn-outline-info btn-sm me-2" onclick="window.location.href='lvm_manager.php'">
                    <i class="fas fa-external-link-alt"></i> <?php echo $lang839; ?>
                </button>
                <button class="btn btn-outline-secondary btn-sm" onclick="refreshAll(true)">
                    <i class="fas fa-sync-alt"></i> <?php echo $lang840; ?>
                </button>
            </div>
        </div>
        <div class="disk-map">
            <div class="partition-visual" style="display: flex; flex-wrap: nowrap; width: 100%; overflow: hidden; border-radius: 6px;">
                ${combinedVisual || '<div class="text-muted text-center p-3"><?php echo $lang841; ?></div>'}
            </div>
            <div class="free-space-info mt-2">
                <small class="text-muted">
                    <i class="fas fa-mouse-pointer"></i> <?php echo $lang842; ?> | 
                    <i class="fas fa-arrows-alt"></i> <?php echo $lang843; ?>
                </small>
            </div>
        </div>
        <div class="disk-toolbar mt-3">
            ${createButtons}
            <button class="btn btn-sm btn-outline-info" onclick="viewFstab()">
                <i class="fas fa-bookmark"></i> <?php echo $lang844; ?>
            </button>
        </div>
        ${freeSpaceHtml}
        ${tableHtml ? `
            <table class="partition-table mt-3">
                <thead>
                    <tr>
                        <th><?php echo $lang845; ?></th>
                        <th><?php echo $lang846; ?></th>
                        <th><?php echo $lang847; ?></th>
                        <th><?php echo $lang848; ?></th>
                        <th><?php echo $lang849; ?></th>
                        <th class="text-end"><?php echo $lang850; ?></th>
                    </tr>
                </thead>
                <tbody>${tableHtml}</tbody>
            </table>
        ` : `
            <div class="empty-state mt-4">
                <i class="fas fa-cubes fa-3x mb-3 text-muted"></i>
                <p class="text-muted"><?php echo $lang851; ?></p>
                ${freeBytes > 0 ? createButtons : ''}
            </div>
        `}
    `;
    
    document.getElementById('diskDetailsPanel').innerHTML = html;
}

function escapeAttr(str) {
    if (!str) return '';
    return str.replace(/'/g, "\\'").replace(/"/g, '&quot;');
}

function renderRaidArrayDetails(raid) {
    const partitions = raid.partitions || [];
    const totalSize = raid.size_bytes;
    
    let usedBytes = 0;
    for (const part of partitions) usedBytes += part.size_bytes;
    const freeBytes = totalSize - usedBytes;
    const maxNewPartitionSize = freeBytes;
	const maxNewPartitionGb = (maxNewPartitionSize / 1024 / 1024 / 1024).toFixed(1);
	const usedPercent = totalSize > 0 ? (usedBytes / totalSize) * 100 : 0;
    const freePercent = totalSize > 0 ? (freeBytes / totalSize) * 100 : 0;
    
    let visualHtml = '';
    if (partitions.length > 0) {
        for (const part of partitions) {
            const percent = (part.size_bytes / totalSize) * 100;
            const fsClass = part.has_filesystem ? getFsClass(part.fstype) : 'no-fs';
            const title = `${part.name} | ${part.has_filesystem ? (part.fstype || 'unknown') : 'NOT FORMATTED'} | ${formatSize(part.size_bytes)}`;
            visualHtml += `<div class="part-visual ${fsClass}" style="width: ${percent}%;" title="${escapeHtml(title)}">${percent > 8 ? part.name : ''}</div>`;
        }
    }
    if (freePercent > 0.5 && partitions.length > 0) {
        visualHtml += `<div class="part-visual free" style="width: ${freePercent}%;" title="<?php echo $lang852; ?> ${formatSize(freeBytes)} (${freePercent.toFixed(1)}%)">${freePercent > 8 ? 'Free' : ''}</div>`;
    }
    
    let tableHtml = '';
    for (const part of partitions) {
        const fsClass = part.has_filesystem ? getFsClass(part.fstype) : 'none';
        const hasFstab = part.fstab_entry !== null;
        const hasFilesystem = part.has_filesystem === true;
        const noFsWarning = !hasFilesystem ? '<span class="no-fs-warning"><i class="fas fa-exclamation-triangle"></i> <?php echo $lang853; ?></span>' : '';
        const isMounted = part.mount_point !== null;
        
        let actionBtns = `
            <div class="btn-group btn-group-sm">
                ${!isMounted && hasFilesystem ? `<button class="btn btn-outline-success" onclick="showMountModal('${part.name}')" title="<?php echo $lang854; ?>"><i class="fa fa-play"></i> <?php echo $lang854; ?></button>` : ''}
                <button class="btn btn-outline-primary" onclick="showFormatModal('${part.name}')" title="<?php echo $lang855; ?>"><i class="fa fa-eraser"></i> <?php echo $lang855; ?></button>
                ${isMounted ? `<button class="btn btn-outline-warning" onclick="umountPartition('${part.name}')" title="<?php echo $lang856; ?>"><i class="fas fa-eject"></i> <?php echo $lang856; ?></button>` : ''}
                ${hasFilesystem && part.fstype !== 'swap' ? `<button class="btn btn-outline-info" onclick="showResizeModal('${part.name}', '${part.fstype}')" title="<?php echo $lang857; ?>"><i class="fas fa-expand-alt"></i> <?php echo $lang857; ?></button>` : ''}
                <button class="btn btn-outline-danger" onclick="deletePartition('${part.name}')" title="<?php echo $lang858; ?>"><i class="fas fa-trash"></i> <?php echo $lang858; ?></button>
            </div>
        `;
        
        tableHtml += `
            <tr data-partition="${part.name}" class="${!hasFilesystem ? 'no-fs' : ''}">
                <td><code>${escapeHtml(part.name)}</code> ${noFsWarning}${isMounted ? '<span class="mounted-indicator" title="<?php echo $lang859; ?>"></span>' : ''}</td>
                <td>${formatSize(part.size_bytes)}</td>
                <td><span class="fs-badge fs-${fsClass}">${part.has_filesystem ? (part.fstype || '—') : 'No FS'}</span></td>
                <td>${part.mount_point || '<span class="text-muted">—</span>'}</td>
                <td>${hasFstab ? '<span class="fstab-badge"><i class="fas fa-bookmark"></i> fstab</span>' : '—'}</td>
                <td class="text-end">${actionBtns}</td>
            </tr>
        `;
    }
    
    const raidStatusHtml = raid.is_degraded ? `<div class="alert alert-danger mt-2"><i class="fas fa-exclamation-triangle"></i> <strong><?php echo $lang860; ?></strong><br><?php echo $lang861; ?></div>` : (raid.state ? `<div class="alert alert-success mt-2"><i class="fas fa-check-circle"></i> <strong><?php echo $lang862; ?></strong> ${raid.state}${raid.active_devices ? `<br><?php echo $lang863; ?> ${raid.active_devices} / ${raid.component_count}` : ''}</div>` : '');
    
    const raidComponentsHtml = (raid.components && raid.components.length > 0) ? `
        <div class="card mt-3"><div class="card-header bg-secondary text-white"><i class="fas fa-hdd"></i> <?php echo $lang864; ?></div><div class="card-body p-0"><table class="table table-sm mb-0"><thead><tr><th><?php echo $lang865; ?></th><th><?php echo $lang866; ?></th></tr></thead><tbody>${raid.components.map(comp => `<tr><td><code>${escapeHtml(comp.device)}</code></td><td><span class="badge ${comp.state === 'active' ? 'bg-success' : (comp.state === 'spare' ? 'bg-warning' : 'bg-danger')}">${comp.state}</span></td></tr>`).join('')}</tbody></table></div></div>
    ` : '';
    
    const freeSpaceHtml = freePercent > 0 ? `
        <div class="alert alert-info mt-3" style="font-size: 13px; background: #e7f1ff; border: none;">
            <i class="fas fa-chart-pie"></i> <strong><?php echo $lang867; ?></strong><br>
            <div class="row mt-2">
                <div class="col-md-4"><small><?php echo $lang868; ?> <strong>${formatSize(totalSize)}</strong></small></div>
                <div class="col-md-4"><small><?php echo $lang869; ?> <strong>${formatSize(usedBytes)}</strong> (${usedPercent.toFixed(1)}%)</small></div>
                <div class="col-md-4"><small><?php echo $lang870; ?> <strong>${formatSize(freeBytes)}</strong> (${freePercent.toFixed(1)}%)</small></div>
            </div>
            ${freePercent > 1 ? `<hr class="my-2"><small class="text-success"><i class="fas fa-plus-circle"></i> <?php echo $lang871; ?> <strong>${formatSize(freeBytes)}</strong></small>` : ''}
        </div>
    ` : '';
    
    const createButtons = freeBytes > 0 ? 
    `<button class="btn btn-sm btn-primary" onclick="showCreateRaidPartitionModal('${raid.name}', ${totalSize})">
        <i class="fas fa-plus"></i> <?php echo $lang872; ?> (<?php echo $lang873; ?> ${maxNewPartitionGb} GB)
     </button>` : 
    `<button class="btn btn-sm btn-secondary" disabled title="<?php echo $lang874; ?>">
        <i class="fas fa-plus"></i> <?php echo $lang875; ?>
     </button>`;
    
    const html = `
        <div class="disk-header">
            <div class="disk-icon" style="width: 48px; height: 48px; font-size: 24px; background: #fff0f0"><i class="fas fa-server"></i></div>
            <div><h3 class="mb-1">${escapeHtml(raid.name)}</h3><p class="text-muted mb-0">RAID ${raid.raid_level || 'Array'} • ${formatSize(totalSize)} • Disks: ${raid.component_count || 0}${raid.state ? ` • State: ${raid.state}` : ''}${raid.is_degraded ? ' • ⚠️ DEGRADED!' : ''}</p></div>
            <div class="ms-auto"><button class="btn btn-outline-info btn-sm" onclick="window.location.href='raid_manager.php'"><i class="fas fa-external-link-alt"></i> <?php echo $lang876; ?></button></div>
        </div>
        <div class="disk-map"><div class="partition-visual">${visualHtml}</div><div class="free-space-info mt-2"><small class="text-muted"><i class="fas fa-mouse-pointer"></i> <?php echo $lang877; ?> | <i class="fas fa-arrows-alt"></i> <?php echo $lang878; ?></small></div></div>
        <div class="disk-toolbar">${createButtons}<button class="btn btn-sm btn-outline-info" onclick="viewFstab()"><i class="fas fa-bookmark"></i> <?php echo $lang879; ?></button><button class="btn btn-sm btn-outline-secondary" onclick="refreshAll(true)"><i class="fas fa-sync-alt"></i> <?php echo $lang880; ?></button></div>
        ${raidStatusHtml}
        ${freeSpaceHtml}
        ${raidComponentsHtml}
        ${tableHtml ? `<table class="partition-table mt-3"><thead><tr><th><?php echo $lang881; ?></th><th><?php echo $lang882; ?></th><th><?php echo $lang883; ?></th><th><?php echo $lang884; ?></th><th><?php echo $lang885; ?></th><th class="text-end"><?php echo $lang886; ?></th></tr></thead><tbody>${tableHtml}</tbody></table>` : `<div class="empty-state mt-4"><i class="fas fa-server fa-3x mb-3 text-muted"></i><p class="text-muted"><?php echo $lang887; ?></p>${createButtons}</div>`}
    `;
    
    document.getElementById('diskDetailsPanel').innerHTML = html;
}

// ==================== ОПЕРАЦИИ С РАЗДЕЛАМИ ====================
function showInitModal(diskName) {
    document.getElementById('initDiskName').value = diskName;
    document.getElementById('initDiskNameDisplay').innerText = diskName;
    new bootstrap.Modal(document.getElementById('initModal')).show();
}

function showReinitModal(diskName) {
    if (confirm(`<?php echo $lang888; ?> ${diskName} <?php echo $lang889; ?>\n\n<?php echo $lang890; ?>`)) showInitModal(diskName);
}

async function executeInit() {
    const disk = document.getElementById('initDiskName').value;
    const tableType = document.querySelector('input[name="tableType"]:checked').value;
    const modal = bootstrap.Modal.getInstance(document.getElementById('initModal'));
    modal.hide();
    
    showProgress(`Initializing the disk ${disk} with table ${tableType.toUpperCase()}...`, true);
    updateProgressDetails('Clearing the partition table...');
    
    const res = await apiCall('init_disk', { disk: disk, table_type: tableType });
    hideProgress();
    
    if (res.success) {
        showToast(`<?php echo $lang891; ?> ${disk} <?php echo $lang892; ?> ${tableType.toUpperCase()}`);
        await refreshAll(true);
    } else {
        showToast(res.error || '<?php echo $lang893; ?>', 'danger');
    }
}

function showCreatePartitionModal(diskName) {
    const disk = allDisks.find(d => d.name === diskName);
    let usedBytes = 0;
    if (disk.partitions) for (const part of disk.partitions) usedBytes += part.size_bytes;
    const freeBytes = disk.size_bytes - usedBytes;
    const freeGb = (freeBytes / 1024 / 1024 / 1024).toFixed(1);
    const freeMb = (freeBytes / 1024 / 1024).toFixed(0);
    
    document.getElementById('createDisk').value = diskName;
    document.getElementById('partSize').value = '';
    document.getElementById('partUnit').value = 'GB';
    document.getElementById('createFs').value = 'ext4';
    document.getElementById('formatAfterCreate').checked = false;
    document.getElementById('formatOptionsCreate').style.display = 'none';
    document.getElementById('createLabel').value = '';
    
    let hintSpan = document.getElementById('sizeHint');
    if (!hintSpan) {
        hintSpan = document.createElement('small');
        hintSpan.id = 'sizeHint';
        hintSpan.className = 'text-muted d-block mt-2';
        document.getElementById('partSize').parentNode.appendChild(hintSpan);
    }
    
    let hintText = `📊 <?php echo $lang894; ?> ${freeGb} GB (${freeMb} MB)`;
    if (freeGb > 0) hintText += `<br>💡 <strong><?php echo $lang895; ?></strong> <?php echo $lang896; ?> (${freeGb} GB)`;
    else hintText += `<br>⚠️ <strong><?php echo $lang897; ?></strong> <?php echo $lang898; ?>`;
    hintSpan.innerHTML = hintText;
    
    const createBtn = document.querySelector('#createPartitionModal .btn-primary');
    if (freeBytes <= 0 && createBtn) { createBtn.disabled = true; createBtn.title = '<?php echo $lang899; ?>'; }
    else if (createBtn) { createBtn.disabled = false; createBtn.title = ''; }
    
    new bootstrap.Modal(document.getElementById('createPartitionModal')).show();
}

document.getElementById('formatAfterCreate')?.addEventListener('change', function(e) {
    document.getElementById('formatOptionsCreate').style.display = e.target.checked ? 'block' : 'none';
});

async function executeCreatePartition() {
    const disk = document.getElementById('createDisk').value;
    let sizeNum = document.getElementById('partSize').value;
    const unit = document.getElementById('partUnit').value;
    
    let size = '0';
    if (sizeNum && parseFloat(sizeNum) > 0) {
        let sizeValue = parseFloat(sizeNum);
        
        if (unit === 'MB') {
            sizeValue = sizeValue / 1024; // MB -> GB
        } else if (unit === 'TB') {
            sizeValue = sizeValue * 1024; // TB -> GB
        }
        
        size = sizeValue.toString();
    }
    
    const fs = document.getElementById('createFs').value;
    const format = document.getElementById('formatAfterCreate').checked;
    const quickFormat = document.querySelector('input[name="formatType"]:checked')?.value === 'quick';
    const label = document.getElementById('createLabel').value;
    
    const modal = bootstrap.Modal.getInstance(document.getElementById('createPartitionModal'));
    if (modal) modal.hide();
    
    showLoader();
    showProgress(`<?php echo $lang900; ?> ${disk}...`, true);
    
    let fsTypeToSend = null;
    if (format && fs !== 'none') {
        fsTypeToSend = fs;
        updateProgressDetails(`Size: ${size === '0' ? 'all space' : size + ' GB'}, FS: ${fs}, formatting: ${quickFormat ? 'quick' : 'full'}`);
    } else {
        updateProgressDetails(`Size: ${size === '0' ? 'all space' : size + ' GB'}, no formatting`);
    }
    
    try {
        const res = await apiCall('partition_create', { 
            disk: disk, 
            size: size,
            fs_type: fsTypeToSend, 
            label: label, 
            format: format, 
            quick_format: quickFormat 
        });
        
        hideProgress();
        hideLoader();
        
        if (res.success) {
            let msg = `Partition created ${res.partition}`;
            if (format && fs !== 'none') msg += ` and formatted in ${fs}`;
            else msg += ` (not formatted)`;
            if (res.format_warning) msg += `. ${res.format_warning}`;
            showToast(msg, res.format_warning ? 'warning' : 'success');
            await refreshAll(true);
            if (res.format_warning) showLogs();
        } else {
            showToast(res.error || '<?php echo $lang901; ?>', 'danger');
            showLogs();
        }
    } catch (error) {
        hideProgress();
        hideLoader();
        showToast('<?php echo $lang902; ?> ' + error.message, 'danger');
        console.error('<?php echo $lang903; ?>', error);
    }
}

async function deletePartition(partition) {
    if (!confirm(`<?php echo $lang904; ?> ${partition}? <?php echo $lang905; ?>`)) return;
    showProgress(`<?php echo $lang906; ?> ${partition}...`, true);
    const res = await apiCall('partition_delete', { partition: partition });
    hideProgress();
    if (res.success) { showToast(`<?php echo $lang907; ?> ${partition}`); await refreshAll(true); hideProgress();}
    else showToast(res.error || '<?php echo $lang908; ?>', 'danger');
}

function showFormatModal(partitionName) {
    document.getElementById('formatPartitionName').value = partitionName;
    document.getElementById('formatModalTitle').innerHTML = `<?php echo $lang909; ?> ${partitionName}`;
    document.getElementById('formatFs').value = 'ext4';
    document.getElementById('quickFormatRadio').checked = true;
    document.getElementById('formatLabel').value = '';
    new bootstrap.Modal(document.getElementById('formatModal')).show();
}

async function executeFormat() {
    const partition = document.getElementById('formatPartitionName').value;
    const fs = document.getElementById('formatFs').value;
    const quickFormat = document.querySelector('input[name="formatTypeRadio"]:checked').value === 'quick';
    const label = document.getElementById('formatLabel').value;
    const modal = bootstrap.Modal.getInstance(document.getElementById('formatModal'));
    modal.hide();
    
    showProgress(`<?php echo $lang910; ?> ${partition} <?php echo $lang911; ?> ${fs}...`, true);
    updateProgressDetails(quickFormat ? '<?php echo $lang912; ?>' : '<?php echo $lang913; ?>');
    const res = await apiCall('partition_format', { partition: partition, fs_type: fs, label: label, quick_format: quickFormat });
    hideProgress();
    
    if (res.success) { showToast(`<?php echo $lang914; ?> ${partition} <?php echo $lang911; ?> ${fs}${quickFormat ? ' (quick)' : ' (full)'}`); await refreshAll(true); }
    else { showToast(res.error || '<?php echo $lang915; ?>', 'danger'); showLogs(); }
}

async function showResizeModal(partitionName, fsType) {
    document.getElementById('resizePartitionName').value = partitionName;
    document.getElementById('resizePartitionFs').value = fsType;
    showLoader();
    const maxInfo = await apiCall('get_partition_max_size', { partition: partitionName });
    hideLoader();
    if (!maxInfo.success) { showToast(maxInfo.error || '<?php echo $lang916; ?>', 'danger'); return; }
    
    const currentGb = maxInfo.current_size_gb, maxGb = maxInfo.max_size_gb, freeGb = maxInfo.free_space_gb, diskTotalGb = maxInfo.disk_total_gb;
    
    document.getElementById('currentSizeLabel').innerText = currentGb.toFixed(2);
    document.getElementById('freeSpaceSpan').innerText = freeGb.toFixed(2);
    document.getElementById('minSizeLabel').innerHTML = `<strong>${currentGb.toFixed(2)} GB</strong> (current)`;
    document.getElementById('maxSizeLabel').innerHTML = `<strong>${maxGb.toFixed(2)} GB</strong> (maximum)`;
    document.getElementById('resizeSize').value = currentGb.toFixed(2);
    document.getElementById('resizeSize').max = maxGb;
    document.getElementById('resizeSize').min = currentGb;
    
    const slider = document.getElementById('sizeSlider');
    slider.min = currentGb; slider.max = maxGb; slider.step = 0.1; slider.value = currentGb;
    document.getElementById('sizeProgressBar').style.width = `${(currentGb / maxGb) * 100}%`;
    
    document.getElementById('resizeInfoText').innerHTML = `<strong>${partitionName}</strong><br>📊 <?php echo $lang917; ?> ${fsType}<br>💾 <?php echo $lang918; ?> <strong>${currentGb.toFixed(2)} GB</strong><br>🆓 <?php echo $lang919; ?> <strong>${freeGb.toFixed(2)} GB</strong><br>📀 <?php echo $lang920; ?> <strong>${diskTotalGb.toFixed(2)} GB</strong><br>📈 <?php echo $lang921; ?> <strong>${maxGb.toFixed(2)} GB</strong>`;
    
    const useAllCheckbox = document.getElementById('useAllSpace');
    useAllCheckbox.checked = false;
    useAllCheckbox.onchange = function() { if (this.checked) { document.getElementById('resizeSize').value = maxGb.toFixed(2); slider.value = maxGb; updateSliderAndInput(maxGb); } };
    
    document.getElementById('maxSizeBtn').onclick = function() { document.getElementById('resizeSize').value = maxGb.toFixed(2); slider.value = maxGb; updateSliderAndInput(maxGb); document.getElementById('useAllSpace').checked = true; };
    
    const sizeInput = document.getElementById('resizeSize');
    sizeInput.oninput = function() { let val = parseFloat(this.value); if (isNaN(val)) val = currentGb; val = Math.max(currentGb, Math.min(maxGb, val)); slider.value = val; updateSliderAndInput(val); document.getElementById('useAllSpace').checked = (val >= maxGb - 0.1); };
    slider.oninput = function() { const val = parseFloat(this.value); sizeInput.value = val.toFixed(2); updateSliderAndInput(val); document.getElementById('useAllSpace').checked = (val >= maxGb - 0.1); };
    
    const warningDiv = document.getElementById('resizeWarning'), warningText = document.getElementById('resizeWarningText');
    if (fsType === 'ntfs') { warningDiv.style.display = 'block'; warningText.innerHTML = '<?php echo $lang922; ?>'; }
    else if (fsType === 'fat32') { warningDiv.style.display = 'block'; warningText.innerHTML = '<?php echo $lang923; ?>'; }
    else if (freeGb < 1) { warningDiv.style.display = 'block'; warningText.innerHTML = '<?php echo $lang924; ?>'; }
    else warningDiv.style.display = 'none';
    
    function updateSliderAndInput(val) { document.getElementById('sizeProgressBar').style.width = `${(val / maxGb) * 100}%`; document.getElementById('sizeProgressBar').className = `progress-bar ${val > currentGb ? 'bg-success' : 'bg-primary'}`; }
    
    new bootstrap.Modal(document.getElementById('resizeModal')).show();
}

async function executeResize() {
    const partition = document.getElementById('resizePartitionName').value;
    let newSize = parseFloat(document.getElementById('resizeSize').value);
    const useAllSpace = document.getElementById('useAllSpace').checked;
    const maxSize = parseFloat(document.getElementById('resizeSize').max);
    if (useAllSpace) newSize = maxSize;
    if (isNaN(newSize) || newSize <= 0) { showToast('<?php echo $lang925; ?>', 'danger'); return; }
    if (newSize > maxSize) { showToast(`<?php echo $lang926; ?> ${maxSize.toFixed(2)} GB`, 'danger'); return; }
    
    const modal = bootstrap.Modal.getInstance(document.getElementById('resizeModal'));
    modal.hide();
    
    showProgress(`<?php echo $lang927; ?> ${partition} <?php echo $lang928; ?> ${newSize.toFixed(2)} GB...`, true);
    updateProgressDetails('<?php echo $lang929; ?>');
    updateProgressDetails('⚠️ <?php echo $lang930; ?>');
    
    const res = await apiCall('resize_partition', { partition: partition, new_size_gb: newSize });
    hideProgress();
    
    if (res.success) { showToast(`✅ <?php echo $lang931; ?> ${partition} <?php echo $lang932; ?> ${res.old_size_gb} GB → ${res.new_size_gb} GB`, 'success'); await refreshAll(true); if (currentDisk) renderDiskDetails(currentDisk); }
    else { showToast(res.error || '<?php echo $lang933; ?>', 'danger'); showLogs(); }
}

// ==================== МОНТИРОВАНИЕ ====================
function showMountModal(partitionName) {
    let device = partitionName;
    let mountPoint = '/mnt/' + partitionName;
    let isLvm = false;
    let lvName = '';
    let vgName = '';
    
    if (partitionName.includes('-') && !partitionName.includes('/')) {
        const parts = partitionName.split('-');
        if (parts.length >= 2) {
            vgName = parts[0];
            lvName = parts.slice(1).join('-');
            isLvm = true;
            
            lvmApiCall('get_all_lvm').then(res => {
                if (res.success && res.lvs) {
                    const lv = res.lvs.find(l => l.name === lvName && l.vg_name === vgName);
                    if (lv) {
                        device = lv.path || `/dev/${vgName}/${lvName}`;
                        mountPoint = '/mnt/' + lvName;
                        document.getElementById('mountDevice').value = device;
                        document.getElementById('mountPoint').value = mountPoint;
                        document.getElementById('mountFs').value = 'auto';
                    }
                }
            });
        }
    }
    
    if (partitionName.startsWith('/dev/') && partitionName.includes('/') && 
        !partitionName.startsWith('/dev/sd') && !partitionName.startsWith('/dev/nvme') && 
        !partitionName.startsWith('/dev/md') && !partitionName.startsWith('/dev/loop')) {
        isLvm = true;
        device = partitionName;
        const pathParts = partitionName.split('/');
        lvName = pathParts[pathParts.length - 1];
        mountPoint = '/mnt/' + lvName;
    }
    
    document.getElementById('mountDevice').value = device;
    document.getElementById('mountPoint').value = mountPoint;
    document.getElementById('mountFs').value = 'auto';
    document.getElementById('addToFstab').checked = false;
    document.getElementById('fstabOptionsDiv').style.display = 'none';
    
    document.getElementById('mountDevice').dataset.isLvm = isLvm ? 'true' : 'false';
    document.getElementById('mountDevice').dataset.vgName = vgName;
    document.getElementById('mountDevice').dataset.lvName = lvName;
    
    new bootstrap.Modal(document.getElementById('mountModal')).show();
}

document.getElementById('addToFstab')?.addEventListener('change', function(e) { document.getElementById('fstabOptionsDiv').style.display = e.target.checked ? 'block' : 'none'; });

function executeMount() {
    const device = document.getElementById('mountDevice').value;
    const mountPoint = document.getElementById('mountPoint').value;
    const fsType = document.getElementById('mountFs').value;
    const addToFstab = document.getElementById('addToFstab').checked;
    const fstabOptions = document.getElementById('fstabOptions').value;
    const modal = bootstrap.Modal.getInstance(document.getElementById('mountModal'));
    modal.hide();
    if (!mountPoint) { showToast('<?php echo $lang934; ?>', 'danger'); return; }
    mountPartition(device, mountPoint, fsType, addToFstab, fstabOptions);
}

async function mountPartition(device, mountPoint, fsType, addToFstab, fstabOptions) {
    showProgress(`<?php echo $lang4722; ?> ${device} <?php echo $lang4723; ?> ${mountPoint}...`, true);
    updateProgressDetails('<?php echo $lang4722; ?>...');
    
    const deviceType = getDeviceType(device);
    let res;
    
    if (deviceType === 'lvm') {
        res = await lvmApiCall('lvm_mount', {
            device: device,
            mount_point: mountPoint,
            fs: fsType,
            fstab: addToFstab
        });
    } else {
        const cleanDevice = device.replace(/^\/dev\//, '');
        res = await apiCall('mount', {
            device: cleanDevice,
            mount_point: mountPoint,
            fs_type: fsType,
            add_to_fstab: addToFstab,
            fstab_options: fstabOptions || 'defaults'
        });
    }
    
    hideProgress();
    
    if (res.success) {
        showToast(`<?php echo $lang4724; ?> ${device} <?php echo $lang4725; ?> ${res.mount_point || mountPoint}${addToFstab ? ' <?php echo $lang4726; ?>' : ''}`, 'success');
        await refreshAll(true);
        if (currentDisk) renderDiskDetails(currentDisk);
    } else {
        showToast(res.error || '<?php echo $lang4727; ?>', 'danger');
    }
}

async function umountPartition(partition) {
    console.log('=== umountPartition START ===');
    console.log('Input partition:', partition);
    
    if (partition.startsWith('/dev/')) {
        console.log('Using full path:', partition);
        await showSmartUmountModal(partition);
        return;
    }
    
    if (partition.includes('-') && !partition.includes('/')) {
        console.log('Detected LVM format: vg_name-lv_name');
        const parts = partition.split('-');
        if (parts.length >= 2) {
            const vgName = parts[0];
            const lvName = parts.slice(1).join('-');
            
            try {
                showProgress(`<?php echo $lang4728; ?> ${partition}...`, true);
                
                const lvmRes = await lvmApiCall('get_all_lvm');
                console.log('LVM API response:', lvmRes);
                
                if (lvmRes.success && lvmRes.lvs) {
                    const lv = lvmRes.lvs.find(l => 
                        l.name === lvName && l.vg_name === vgName
                    );
                    
                    if (lv) {
                        console.log('Found LV:', lv);
                        const lvPath = lv.path || `/dev/${vgName}/${lvName}`;
                        console.log('LV path:', lvPath);
                        
                        if (lv.mount_point) {
                            console.log('LV is mounted at:', lv.mount_point);
                            hideProgress();
                            
                            showProgress(`<?php echo $lang4729; ?> ${lvName}...`, true);
                            const umountRes = await lvmApiCall('lvm_umount', {
                                mount_point: lv.mount_point,
                                remove_from_fstab: false
                            });
                            
                            hideProgress();
                            
                            if (umountRes.success) {
                                showToast(`<?php echo $lang4730; ?> ${lvName} <?php echo $lang4731; ?> ${lv.mount_point}`, 'success');
                                await refreshAll(true);
                                if (currentDisk) renderDiskDetails(currentDisk);
                                return;
                            } else {
                                showToast(umountRes.error || '<?php echo $lang4732; ?>', 'danger');
                                return;
                            }
                        } else {
                            hideProgress();
                            showToast(`LV ${lvName} <?php echo $lang4733; ?>`, 'warning');
                            return;
                        }
                    } else {
                        hideProgress();
                        console.log('LV not found in LVM data');
                    }
                } else {
                    hideProgress();
                    console.log('LVM API returned no data or error');
                }
            } catch(e) {
                hideProgress();
                console.error('Error with LVM API:', e);
                showToast('<?php echo $lang4734; ?> ' + e.message, 'danger');
                return;
            }
        }
    }
    
    if (!partition.includes('/') && !partition.includes('-')) {
        console.log('<?php echo $lang4735; ?>', partition);
        try {
            const lvmRes = await lvmApiCall('get_all_lvm');
            if (lvmRes.success && lvmRes.lvs) {
                const lv = lvmRes.lvs.find(l => l.name === partition);
                if (lv) {
                    console.log('Found LV:', lv);
                    if (lv.mount_point) {
                        showProgress(`<?php echo $lang4736; ?> ${partition}...`, true);
                        const umountRes = await lvmApiCall('lvm_umount', {
                            mount_point: lv.mount_point,
                            remove_from_fstab: false
                        });
                        hideProgress();
                        if (umountRes.success) {
                            showToast(`<?php echo $lang4737; ?> ${partition}`, 'success');
                            await refreshAll(true);
                            if (currentDisk) renderDiskDetails(currentDisk);
                            return;
                        } else {
                            showToast(umountRes.error || '<?php echo $lang4738; ?>', 'danger');
                            return;
                        }
                    } else {
                        showToast(`LV ${partition} <?php echo $lang4739; ?>`, 'warning');
                        return;
                    }
                }
            }
        } catch(e) {
            console.error('Error searching LVM:', e);
        }
    }
    
    console.log('No LVM detected, trying as regular disk');
    await showSmartUmountModal(partition);
}

async function showSmartUmountModal(partition) {
    showLoader();
    
    const deviceType = getDeviceType(partition);
    let mountStatus, fstabCheck;
    
    if (deviceType === 'lvm') {
        mountStatus = await lvmApiCall('get_lv_info', { lv_path: partition });
        const cleanDevice = partition.replace(/^\/dev\//, '');
        fstabCheck = await apiCall('check_fstab', { partition: cleanDevice });
        
        if (mountStatus.success && mountStatus.info) {
            mountStatus = {
                success: true,
                status: {
                    mounted: mountStatus.info.mount_point !== null,
                    mount_point: mountStatus.info.mount_point
                }
            };
        } else {
            mountStatus = { success: false };
        }
    } else {
        const cleanPartition = partition.replace(/^\/dev\//, '');
        mountStatus = await apiCall('mount_status', { device: cleanPartition });
        fstabCheck = await apiCall('check_fstab', { partition: cleanPartition });
    }
    
    hideLoader();
    
    if (!mountStatus.success) {
        showToast('<?php echo $lang4740; ?>', 'danger');
        return;
    }
    
    const isMounted = mountStatus.status?.mounted || false;
    const mountPoint = mountStatus.status?.mount_point || 'unknown';
    const inFstab = fstabCheck.in_fstab || false;
    const fstabEntry = fstabCheck.fstab_entry || '';
    
    if (!isMounted) {
        showToast(`<?php echo $lang4741; ?> ${partition} <?php echo $lang4742; ?>`, 'warning');
        return;
    }
    
    document.getElementById('umountDeviceName').value = partition;
    document.getElementById('umountIsMounted').value = isMounted;
    
    let infoHtml = `<div class="alert alert-secondary"><i class="fas fa-hdd"></i> <strong><?php echo $lang4743; ?></strong> ${partition}<br><i class="fas fa-folder-open"></i> <strong><?php echo $lang4744; ?></strong> ${mountPoint}<br><i class="fas fa-circle-info"></i> <strong><?php echo $lang4745; ?></strong> <span class="text-success"><?php echo $lang4746; ?></span>`;
    if (inFstab) infoHtml += `<br><i class="fas fa-bookmark"></i> <strong>fstab:</strong> <span class="text-warning"><?php echo $lang4747; ?></span><br><code class="small">${escapeHtml(fstabEntry)}</code>`;
    else infoHtml += `<br><i class="fas fa-bookmark"></i> <strong>fstab:</strong> <span class="text-muted"><?php echo $lang4748; ?></span>`;
    infoHtml += `</div>`;
    document.getElementById('umountInfoContent').innerHTML = infoHtml;
    
    const fstabWarning = document.getElementById('umountFstabWarning');
    const normalWarning = document.getElementById('umountNormalWarning');
    const umountOnlyBtn = document.getElementById('umountOnlyBtn');
    const umountAndRemoveBtn = document.getElementById('umountAndRemoveBtn');
    const removeCheckbox = document.getElementById('removeFromFstabCheckbox');
    
    if (inFstab) {
        fstabWarning.style.display = 'block';
        normalWarning.style.display = 'none';
        removeCheckbox.checked = true;
        umountOnlyBtn.innerHTML = '<i class="fas fa-eject"></i> <?php echo $lang4749; ?>';
        umountOnlyBtn.className = 'btn btn-warning';
        umountAndRemoveBtn.innerHTML = '<i class="fas fa-trash-alt"></i> <?php echo $lang4750; ?>';
        umountAndRemoveBtn.className = 'btn btn-danger';
        umountAndRemoveBtn.disabled = false;
    } else {
        fstabWarning.style.display = 'none';
        normalWarning.style.display = 'block';
        umountOnlyBtn.innerHTML = '<i class="fas fa-eject"></i> <?php echo $lang4751; ?>';
        umountOnlyBtn.className = 'btn btn-primary';
        umountAndRemoveBtn.innerHTML = '<i class="fas fa-trash-alt"></i> <?php echo $lang4752; ?>';
        umountAndRemoveBtn.className = 'btn btn-outline-danger';
        umountAndRemoveBtn.disabled = true;
        umountAndRemoveBtn.title = 'Not in fstab';
    }
    new bootstrap.Modal(document.getElementById('smartUmountModal')).show();
}


async function executeSmartUmount(removeFromFstab) {
    const partition = document.getElementById('umountDeviceName').value;
    const removeCheckbox = document.getElementById('removeFromFstabCheckbox');
    const shouldRemove = removeFromFstab && removeCheckbox.checked;
    const modal = bootstrap.Modal.getInstance(document.getElementById('smartUmountModal'));
    modal.hide();
    
    showProgress(`<?php echo $lang4753; ?> ${partition}...`, true);
    
    const deviceType = getDeviceType(partition);
    let res;
    
    if (deviceType === 'lvm') {
    const lvInfo = await lvmApiCall('get_lv_info', { lv_path: partition });
    if (lvInfo.success && lvInfo.info && lvInfo.info.mount_point) {
        const mountPoint = lvInfo.info.mount_point;
        
        res = await lvmApiCall('lvm_umount', {
            mount_point: mountPoint,
            remove_from_fstab: shouldRemove
        });

    } else {
        res = { success: false, error: '<?php echo $lang4754; ?>' };
    }
} else {
    const cleanPartition = partition.replace(/^\/dev\//, '');
    res = await apiCall('smart_umount', {
        device: cleanPartition,
        auto_remove_from_fstab: shouldRemove
    });
}
    
    hideProgress();
    
    if (res.success) {
        showToast(res.message || `<?php echo $lang4755; ?> ${partition}`, 'success');
        await refreshAll(true);
        if (currentDisk) renderDiskDetails(currentDisk);
    } else {
        showToast(res.error || '<?php echo $lang4756; ?>', 'danger');
        if (confirm('<?php echo $lang4757; ?>')) {
            showProgress(`<?php echo $lang4758; ?> ${partition}...`, true);
            
            let forceRes;
            if (deviceType === 'lvm') {
                const lvInfo = await lvmApiCall('get_lv_info', { lv_path: partition });
                if (lvInfo.success && lvInfo.info && lvInfo.info.mount_point) {
                    forceRes = await lvmApiCall('lvm_umount', {
                        mount_point: lvInfo.info.mount_point
                    });
                } else {
                    forceRes = { success: false, error: '<?php echo $lang4759; ?>' };
                }
            } else {
                const cleanPartition = partition.replace(/^\/dev\//, '');
                forceRes = await apiCall('umount', {
                    device: cleanPartition,
                    force: true,
                    remove_from_fstab: shouldRemove
                });
            }
            
            hideProgress();
            if (forceRes.success) {
                showToast(`<?php echo $lang4760; ?> ${partition}`, 'success');
                await refreshAll(true);
            } else {
                showToast(forceRes.error || '<?php echo $lang4761; ?>', 'danger');
            }
        }
    }
}

async function executeMount() {
    const device = document.getElementById('mountDevice').value;
    const mountPoint = document.getElementById('mountPoint').value;
    const fsType = document.getElementById('mountFs').value;
    const addToFstab = document.getElementById('addToFstab').checked;
    const fstabOptions = document.getElementById('fstabOptions').value;
    const isLvm = document.getElementById('mountDevice').dataset.isLvm === 'true';
    const vgName = document.getElementById('mountDevice').dataset.vgName;
    const lvName = document.getElementById('mountDevice').dataset.lvName;
    
    const modal = bootstrap.Modal.getInstance(document.getElementById('mountModal'));
    modal.hide();
    
    if (!mountPoint) {
        showToast('<?php echo $lang4762; ?>', 'danger');
        return;
    }
    
    if (isLvm) {
        showProgress(`<?php echo $lang4763; ?> ${lvName || device} <?php echo $lang4764; ?> ${mountPoint}...`, true);
        
        try {
            const res = await lvmApiCall('lvm_mount', {
                device: device,
                mount_point: mountPoint,
                fs: fsType,
                fstab: addToFstab
            });
            
            hideProgress();
            
            if (res.success) {
                showToast(`<?php echo $lang4765; ?> ${lvName || device} <?php echo $lang4766; ?> ${res.mount_point || mountPoint}${addToFstab ? ' <?php echo $lang4767; ?>' : ''}`, 'success');
                await refreshAll(true);
                if (currentDisk) renderDiskDetails(currentDisk);
            } else {
                showToast(res.error || '<?php echo $lang4768; ?>', 'danger');
            }
        } catch(e) {
            hideProgress();
            showToast('Error: ' + e.message, 'danger');
        }
    } else {
        await mountPartition(device, mountPoint, fsType, addToFstab, fstabOptions);
    }
}

function getDeviceType(device) {
    const clean = device.replace(/^\/dev\//, '');
    
    if (device.includes('/dev/mapper/') || 
        (device.startsWith('/dev/') && 
         !device.startsWith('/dev/sd') && 
         !device.startsWith('/dev/nvme') && 
         !device.startsWith('/dev/md') &&
         !device.startsWith('/dev/loop') &&
         !device.startsWith('/dev/sr'))) {
        return 'lvm';
    }
    
    if (device.startsWith('/dev/md')) {
        return 'raid';
    }
    
    if (device.includes('-') && !device.includes('/')) {
        return 'lvm';
    }
    
    return 'disk';
}

// ==================== LVM И RAID ОПЕРАЦИИ ====================
function showCreateLvModal(vgName, freeBytes) {
    const freeGb = (freeBytes / 1024 / 1024 / 1024).toFixed(1);
    
    document.getElementById('createLvVgName').value = vgName;
    document.getElementById('createLvName').value = '';
    document.getElementById('createLvSize').value = '';
    document.getElementById('createLvFormat').checked = false;
    document.getElementById('createLvFsDiv').style.display = 'none';
    document.getElementById('createLvLabel').value = '';
    document.getElementById('vgFreeSpaceHint').innerHTML = `📊 <?php echo $lang963; ?> <strong>${freeGb} GB</strong><br>💡 <?php echo $lang964; ?>`;
    
    const maxBtn = document.getElementById('maxLvSizeBtn');
    if (maxBtn) {
        maxBtn.onclick = () => {
            document.getElementById('createLvSize').value = '100%FREE';
            document.getElementById('vgFreeSpaceHint').innerHTML = `📊 <?php echo $lang965; ?> <strong>${freeGb} GB</strong>`;
        };
    }
    
    new bootstrap.Modal(document.getElementById('createLvModal')).show();
}

document.getElementById('createLvFormat')?.addEventListener('change', function(e) {
    document.getElementById('createLvFsDiv').style.display = e.target.checked ? 'block' : 'none';
});

async function executeCreateLv() {
    const vgName = document.getElementById('createLvVgName')?.value;
    const lvName = document.getElementById('createLvName')?.value.trim();
    let size = document.getElementById('createLvSize')?.value.trim();
    const format = document.getElementById('createLvFormat')?.checked || false;
    const fsType = document.getElementById('createLvFs')?.value || 'ext4';
    const label = document.getElementById('createLvLabel')?.value || '';
    
    if (!vgName) {
        showToast('<?php echo $lang966; ?>', 'danger');
        return;
    }
    
    if (!lvName) {
        showToast('<?php echo $lang967; ?>', 'danger');
        return;
    }
    
    if (!/^[a-zA-Z0-9_-]+$/.test(lvName)) {
        showToast('<?php echo $lang968; ?>', 'danger');
        return;
    }
    
    if (!size) {
        size = '100%FREE';
    }
    
    const modalElement = document.getElementById('createLvModal');
    if (modalElement) {
        const modal = bootstrap.Modal.getInstance(modalElement);
        if (modal) modal.hide();
    }
    
    showProgress(`<?php echo $lang969; ?> ${lvName} <?php echo $lang970; ?> ${vgName}...`, true);
    updateProgressDetails(`<?php echo $lang971; ?> ${size}, <?php echo $lang972; ?> ${format ? fsType : 'no'}`);
    
    const res = await lvmApiCall('lvm_create_lv', {
        vg_name: vgName,
        lv_name: lvName,
        size: size,
        format: format,
        fs_type: fsType,
        label: label
    });
    
    hideProgress();
    
    if (res.success) {
        let msg = `<?php echo $lang973; ?> ${lvName} <?php echo $lang974; ?> ${size}`;
        if (format) msg += `, <?php echo $lang975; ?> ${fsType}`;
        else msg += ` <?php echo $lang976; ?>`;
        showToast(msg, 'success');
        await refreshAll(true);
        if (currentDisk === vgName) renderDiskDetails(vgName);
    } else {
        showToast(res.error || '<?php echo $lang977; ?>', 'danger');
    }
}

async function showExtendLvModal(vgName, lvName, currentSizeFormatted) {
    showLoader();
    
    const vgInfo = await lvmApiCall('get_all_lvm');
    let freeSpace = 0;
    let freeSpaceFormatted = '0 GB';
    
    if (vgInfo.success && vgInfo.vgs) {
        const vg = vgInfo.vgs.find(v => v.name === vgName);
        if (vg) {
            freeSpace = vg.free;
            freeSpaceFormatted = vg.free_formatted;
        }
    }
    
    hideLoader();
    
    document.getElementById('extendLvVgName').value = vgName;
    document.getElementById('extendLvName').value = lvName;
    document.getElementById('extendLvCurrentSize').innerHTML = `<strong>${currentSizeFormatted}</strong>`;
    document.getElementById('extendVgFreeSpace').innerHTML = `<strong>${freeSpaceFormatted}</strong>`;
    document.getElementById('extendLvNewSize').value = '';
    
    const maxBtn = document.getElementById('extendMaxSizeBtn');
    if (maxBtn) {
        maxBtn.onclick = () => {
            document.getElementById('extendLvNewSize').value = `+${freeSpaceFormatted}`;
        };
    }
    
    new bootstrap.Modal(document.getElementById('extendLvModal')).show();
}

async function executeExtendLv() {
    const vgName = document.getElementById('extendLvVgName').value;
    const lvName = document.getElementById('extendLvName').value;
    const newSize = document.getElementById('extendLvNewSize').value.trim();
    
    if (!newSize) {
        showToast('<?php echo $lang978; ?>', 'danger');
        return;
    }
    
    const modal = bootstrap.Modal.getInstance(document.getElementById('extendLvModal'));
    modal.hide();
    
    showProgress(`<?php echo $lang979; ?> ${lvName}...`, true);
    updateProgressDetails(`<?php echo $lang980; ?> ${newSize}`);
    
    const res = await lvmApiCall('lvm_extend_lv', {
        vg_name: vgName,
        lv_name: lvName,
        size: newSize
    });
    
    hideProgress();
    
    if (res.success) {
        showToast(`LV ${lvName} <?php echo $lang981; ?> ${newSize}`, 'success');
        await refreshAll(true);
        if (currentDisk === vgName) renderDiskDetails(vgName);
    } else {
        showToast(res.error || '<?php echo $lang982; ?>', 'danger');
    }
}

function showRenameLvModal(vgName, lvName) {
    document.getElementById('renameLvVgName').value = vgName;
    document.getElementById('renameLvOldName').value = lvName;
    document.getElementById('renameLvCurrentName').innerHTML = `<strong>${lvName}</strong>`;
    document.getElementById('renameLvNewName').value = '';
    
    new bootstrap.Modal(document.getElementById('renameLvModal')).show();
}

async function executeRenameLv() {
    const vgName = document.getElementById('renameLvVgName').value;
    const oldName = document.getElementById('renameLvOldName').value;
    const newName = document.getElementById('renameLvNewName').value.trim();
    
    if (!newName) {
        showToast('<?php echo $lang983; ?>', 'danger');
        return;
    }
    
    if (oldName === newName) {
        showToast('<?php echo $lang984; ?>', 'warning');
        return;
    }
    
    if (!/^[a-zA-Z0-9_-]+$/.test(newName)) {
        showToast('<?php echo $lang985; ?>', 'danger');
        return;
    }
    
    const modal = bootstrap.Modal.getInstance(document.getElementById('renameLvModal'));
    modal.hide();
    
    showProgress(`<?php echo $lang986; ?> ${oldName} <?php echo $lang987; ?> ${newName}...`, true);
    
    const res = await lvmApiCall('lvm_rename_lv', {
        vg_name: vgName,
        old_name: oldName,
        new_name: newName
    });
    
    hideProgress();
    
    if (res.success) {
        showToast(`<?php echo $lang988; ?> ${oldName} → ${newName}`, 'success');
        await refreshAll(true);
        if (currentDisk === vgName) renderDiskDetails(vgName);
    } else {
        showToast(res.error || '<?php echo $lang989; ?>', 'danger');
    }
}

function showDeleteLvConfirm(vgName, lvName) {
    document.getElementById('deleteLvVgName').value = vgName;
    document.getElementById('deleteLvName').value = lvName;
    document.getElementById('deleteLvDisplayName').innerHTML = `${vgName}/${lvName}`;
    document.getElementById('deleteLvConfirmCheck').checked = false;
    document.getElementById('deleteLvConfirmBtn').disabled = true;
    
    new bootstrap.Modal(document.getElementById('deleteLvConfirmModal')).show();
}

document.getElementById('deleteLvConfirmCheck')?.addEventListener('change', function(e) {
    document.getElementById('deleteLvConfirmBtn').disabled = !e.target.checked;
});

async function executeDeleteLv() {
    const vgName = document.getElementById('deleteLvVgName').value;
    const lvName = document.getElementById('deleteLvName').value;
    
    const modal = bootstrap.Modal.getInstance(document.getElementById('deleteLvConfirmModal'));
    modal.hide();
    
    showProgress(`<?php echo $lang990; ?> ${lvName}...`, true);
    updateProgressDetails('<?php echo $lang991; ?>');
    
    const res = await lvmApiCall('lvm_delete_lv', {
        vg_name: vgName,
        lv_name: lvName
    });
    
    hideProgress();
    
    if (res.success) {
        showToast(`LV ${lvName} <?php echo $lang992; ?>`, 'success');
        await refreshAll(true);
        if (currentDisk === vgName) renderDiskDetails(vgName);
    } else {
        showToast(res.error || '<?php echo $lang993; ?>', 'danger');
    }
}

async function formatLv(vgName, lvName) {
    const lvPath = `/dev/${vgName}/${lvName}`;
    
    showLoader();
    const statusRes = await lvmApiCall('lv_status', { lv_path: lvPath });
    hideLoader();
    
    if (statusRes.success && statusRes.exists) {
        if (statusRes.mounted === true) {
            showToast('<?php echo $lang994; ?>', 'error');
            return;
        }
    }
    
    showFormatLvModal(vgName, lvName);
}

function showFormatLvModal(vgName, lvName) {
    const modalHtml = `
        <div class="modal fade" id="formatLvModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-eraser"></i> <?php echo $lang995; ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="formatLvVgName" value="${escapeAttr(vgName)}">
                        <input type="hidden" id="formatLvName" value="${escapeAttr(lvName)}">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i> 
                            <strong><?php echo $lang996; ?></strong> <?php echo $lang997; ?> ${vgName}/${lvName} <?php echo $lang998; ?>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><?php echo $lang999; ?></label>
                            <select class="form-select" id="formatLvFs">
                                <optgroup label="Linux">
                                    <option value="ext4" selected>ext4 <?php echo $lang1000; ?></option>
                                    <option value="ext3">ext3</option>
                                    <option value="ext2">ext2</option>
                                    <option value="xfs">XFS</option>
                                    <option value="btrfs">Btrfs</option>
                                </optgroup>
                                <optgroup label="Windows">
                                    <option value="ntfs">NTFS</option>
                                    <option value="vfat">FAT32</option>
                                    <option value="exfat">exFAT</option>
                                </optgroup>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><?php echo $lang1001; ?></label>
                            <input type="text" class="form-control" id="formatLvLabel" placeholder="<?php echo $lang1002; ?>">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang1003; ?></button>
                        <button class="btn btn-danger" onclick="executeFormatLv()"><?php echo $lang1004; ?></button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    const oldModal = document.getElementById('formatLvModal');
    if (oldModal) oldModal.remove();
    
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    new bootstrap.Modal(document.getElementById('formatLvModal')).show();
}

async function executeFormatLv() {
    const vgName = document.getElementById('formatLvVgName').value;
    const lvName = document.getElementById('formatLvName').value;
    const fsType = document.getElementById('formatLvFs').value;
    const label = document.getElementById('formatLvLabel').value;
    
    const lvPath = `/dev/${vgName}/${lvName}`;
    const modal = bootstrap.Modal.getInstance(document.getElementById('formatLvModal'));
    if (modal) modal.hide();
    
    showProgress(`<?php echo $lang1005; ?> ${vgName}/${lvName} <?php echo $lang1006; ?> ${fsType}...`, true);
    
    const res = await lvmApiCall('lvm_format_lv', {
        lv_path: lvPath,
        fs_type: fsType,
        label: label
    });
    
    hideProgress();
    
    if (res.success) {
        showToast(`LV ${lvName} <?php echo $lang1007; ?> ${fsType}`, 'success');
        await refreshAll(true);
        if (currentDisk === vgName) renderDiskDetails(vgName);
    } else {
        showToast(res.error || '<?php echo $lang1008; ?>', 'danger');
    }
}

async function showCreateSnapshotModal(vgName, lvName) {
    document.getElementById('snapshotVgName').value = vgName;
    document.getElementById('snapshotOriginLvName').value = lvName;
    document.getElementById('snapshotOriginDisplay').innerHTML = `<strong>${vgName}/${lvName}</strong>`;
    
    const now = new Date();
    const timestamp = now.toISOString().slice(0,16).replace(/[:-]/g, '').replace('T', '_');
    const defaultName = `snap_${lvName}_${timestamp}`;
    document.getElementById('snapshotName').value = defaultName;
    document.getElementById('snapshotSize').value = '10G';
    
    const hintBtn = document.getElementById('snapshotSizeHintBtn');
    if (hintBtn) {
        hintBtn.onclick = () => {
            showToast('<?php echo $lang1009; ?>', 'info');
        };
    }
    
    new bootstrap.Modal(document.getElementById('createSnapshotModal')).show();
}

async function executeCreateSnapshot() {
    const vgName = document.getElementById('snapshotVgName').value;
    const originLv = document.getElementById('snapshotOriginLvName').value;
    let snapshotName = document.getElementById('snapshotName').value.trim();
    const size = document.getElementById('snapshotSize').value.trim();
    
    if (!snapshotName) {
        showToast('<?php echo $lang1010; ?>', 'danger');
        return;
    }
    
    if (!size) {
        showToast('<?php echo $lang1011; ?>', 'danger');
        return;
    }
    
    const modal = bootstrap.Modal.getInstance(document.getElementById('createSnapshotModal'));
    modal.hide();
    
    showProgress(`<?php echo $lang1012; ?> ${snapshotName}...`, true);
    updateProgressDetails(`<?php echo $lang1013; ?> ${vgName}/${originLv}, <?php echo $lang1014; ?> ${size}`);
    
    const res = await lvmApiCall('create_snapshot', {
        vg_name: vgName,
        origin_lv: originLv,
        snapshot_name: snapshotName,
        size: size
    });
    
    hideProgress();
    
    if (res.success) {
        showToast(`<?php echo $lang1015; ?> ${res.snapshot_name || snapshotName} <?php echo $lang1016; ?>`, 'success');
        await refreshAll(true);
    } else {
        showToast(res.error || '<?php echo $lang1017; ?>', 'danger');
    }
}

async function showSnapshotsList() {
    const modal = new bootstrap.Modal(document.getElementById('snapshotsListModal'));
    const content = document.getElementById('snapshotsListContent');
    content.innerHTML = '<div class="text-center p-4"><div class="loader-spinner mx-auto"></div><p><?php echo $lang1018; ?></p></div>';
    modal.show();
    
    await refreshSnapshotsList();
}

async function refreshSnapshotsList() {
    try {
        const res = await lvmApiCall('get_snapshots');
        const content = document.getElementById('snapshotsListContent');
        
        if (res.success && res.snapshots && res.snapshots.length > 0) {
            let html = `<div class="alert alert-info mb-3">
                <i class="fas fa-info-circle"></i> 
                <?php echo $lang1019; ?>
            </div><div class="row">`;
            
            for (const snap of res.snapshots) {
                const usedPercent = snap.data_percent || 0;
                const isMerging = snap.is_merging;
                const progressColor = usedPercent > 80 ? 'danger' : (usedPercent > 50 ? 'warning' : 'success');
                
                html += `
                    <div class="col-md-6 mb-3">
                        <div class="card snapshot-card ${isMerging ? 'border-warning' : ''}" onclick="showSnapshotInfo('${snap.vg_name}', '${snap.name}')" style="cursor: pointer;">
                            <div class="card-header ${isMerging ? 'bg-warning' : 'bg-primary'} text-white">
                                <i class="fas fa-camera"></i> ${escapeHtml(snap.name)}
                                ${isMerging ? '<span class="badge bg-dark ms-2"><?php echo $lang1020; ?></span>' : ''}
                            </div>
                            <div class="card-body">
                                <table class="table table-sm mb-0">
                                    <tr><td width="35%">VG:</td><td><strong>${escapeHtml(snap.vg_name)}</strong></td></tr>
                                    <tr><td><?php echo $lang1021; ?></td><td><strong>${escapeHtml(snap.origin)}</strong></td></tr>
                                    <tr><td><?php echo $lang1022; ?></td><td>${escapeHtml(snap.size)}</td></tr>
                                    <tr><td><?php echo $lang1023; ?></td><td>${usedPercent}%</td></tr>
                                    <tr><td><?php echo $lang1024; ?></td><td>${snap.is_active ? '<span class="badge bg-success"><?php echo $lang1025; ?></span>' : '<span class="badge bg-secondary"><?php echo $lang1026; ?></span>'}</td></tr>
                                    ${snap.mount_point ? `<tr><td><?php echo $lang1027; ?></td><td><code>${escapeHtml(snap.mount_point)}</code></td></tr>` : ''}
                                </table>
                                <div class="progress mt-2" style="height: 6px;">
                                    <div class="progress-bar bg-${progressColor}" style="width: ${usedPercent}%"></div>
                                </div>
                            </div>
                            <div class="card-footer">
                                <button class="btn btn-sm btn-outline-warning" onclick="event.stopPropagation(); restoreSnapshot('${snap.vg_name}', '${snap.name}', '${snap.origin}')">
                                    <i class="fas fa-undo"></i> <?php echo $lang1028; ?>
                                </button>
                                <button class="btn btn-sm btn-outline-danger" onclick="event.stopPropagation(); deleteSnapshot('${snap.vg_name}', '${snap.name}')">
                                    <i class="fas fa-trash"></i> <?php echo $lang1029; ?>
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            }
            html += `</div>`;
            content.innerHTML = html;
        } else {
            content.innerHTML = `
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> <?php echo $lang1030; ?>
                    <hr>
                    <?php echo $lang1031; ?>
                </div>
                <div class="text-center">
                    <button class="btn btn-primary" onclick="closeSnapshotsListAndCreate()"><?php echo $lang1032; ?></button>
                </div>
            `;
        }
    } catch (e) {
        document.getElementById('snapshotsListContent').innerHTML = `<div class="alert alert-danger"><?php echo $lang1033; ?> ${e.message}</div>`;
    }
}

function closeSnapshotsListAndCreate() {
    bootstrap.Modal.getInstance(document.getElementById('snapshotsListModal')).hide();
    showToast('<?php echo $lang1034; ?>', 'info');
}

async function showSnapshotInfo(vgName, snapshotName) {
    const modal = new bootstrap.Modal(document.getElementById('snapshotInfoModal'));
    const content = document.getElementById('snapshotInfoContent');
    const title = document.getElementById('snapshotInfoTitle');
    
    title.innerHTML = `<i class="fas fa-camera"></i> ${vgName}/${snapshotName}`;
    content.innerHTML = '<div class="text-center p-4"><div class="loader-spinner mx-auto"></div><p><?php echo $lang1035; ?></p></div>';
    modal.show();
    
    try {
        const res = await lvmApiCall('get_snapshot_info', { vg_name: vgName, snapshot_name: snapshotName });
        
        if (res.success && res.info) {
            currentSnapshotInfo = res.info;
            const snap = res.info;
            const usedPercent = snap.data_percent || 0;
            const progressColor = usedPercent > 80 ? 'danger' : (usedPercent > 50 ? 'warning' : 'success');
            
            content.innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <div class="card mb-3">
                            <div class="card-header bg-info text-white"><?php echo $lang1036; ?></div>
                            <div class="card-body">
                                <table class="table table-sm">
                                    <tr><td width="40%"><?php echo $lang1037; ?></td><td><strong>${escapeHtml(snap.name)}</strong></td></tr>
                                    <tr><td>VG:</td><td><strong>${escapeHtml(snap.vg_name)}</strong></td></tr>
                                    <tr><td><?php echo $lang1038; ?></td><td><strong>${escapeHtml(snap.origin)}</strong></td></tr>
                                    <tr><td><?php echo $lang1039; ?></td><td>${escapeHtml(snap.size)}</td></tr>
                                    <tr><td><?php echo $lang1040; ?></td><td><code>${escapeHtml(snap.path)}</code></td></tr>
                                    <tr><td><?php echo $lang1041; ?></td><td>${snap.is_active ? '<span class="badge bg-success"><?php echo $lang1043; ?></span>' : '<span class="badge bg-secondary"><?php echo $lang1044; ?></span>'}</td></tr>
                                    <tr><td><?php echo $lang1042; ?></td><td>${snap.is_merging ? '<span class="badge bg-warning"><?php echo $lang1045; ?></span>' : '<span class="badge bg-secondary"><?php echo $lang1046; ?></span>'}</td></tr>
                                    ${snap.mount_point ? `<tr><td><?php echo $lang1047; ?></td><td><code>${escapeHtml(snap.mount_point)}</code></td></tr>` : ''}
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card mb-3">
                            <div class="card-header bg-primary text-white"><?php echo $lang1048; ?></div>
                            <div class="card-body">
                                <label><?php echo $lang1049; ?></label>
                                <div class="progress mb-3" style="height: 20px;">
                                    <div class="progress-bar bg-${progressColor}" style="width: ${usedPercent}%">
                                        ${usedPercent}%
                                    </div>
                                </div>
                                <label><?php echo $lang1050; ?></label>
                                <div class="progress mb-3" style="height: 20px;">
                                    <div class="progress-bar bg-info" style="width: ${snap.metadata_percent}%">
                                        ${snap.metadata_percent}%
                                    </div>
                                </div>
                                ${snap.copy_percent > 0 ? `
                                <label><?php echo $lang1051; ?></label>
                                <div class="progress mb-3" style="height: 20px;">
                                    <div class="progress-bar bg-warning" style="width: ${snap.copy_percent}%">
                                        ${snap.copy_percent}%
                                    </div>
                                </div>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                </div>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong><?php echo $lang1052; ?></strong> <?php echo $lang1053; ?> 
                    <?php echo $lang1054; ?>
                </div>
            `;
        } else {
            content.innerHTML = `<div class="alert alert-danger">${res.error || '<?php echo $lang1055; ?>'}</div>`;
        }
    } catch (e) {
        content.innerHTML = `<div class="alert alert-danger"><?php echo $lang1056; ?> ${e.message}</div>`;
    }
}

async function restoreSnapshot(vgName, snapshotName, originLv) {
    const confirmMsg = `<?php echo $lang4769; ?> ${originLv} <?php echo $lang4770; ?> ${snapshotName}?\n\n<?php echo $lang1057; ?> ${originLv} <?php echo $lang1058; ?>`;
    
    if (!confirm(confirmMsg)) return;
    
    showProgress(`<?php echo $lang1059; ?> ${snapshotName}...`, true);
    
    const res = await lvmApiCall('restore_snapshot', {
        vg_name: vgName,
        snapshot_name: snapshotName,
        origin_lv: originLv
    });
    
    hideProgress();
    
    if (res.success) {
        const snapshotModal = bootstrap.Modal.getInstance(document.getElementById('snapshotInfoModal'));
        if (snapshotModal) snapshotModal.hide();
        
        await refreshAll(true);
        showToast(res.message || '<?php echo $lang1060; ?>', 'success');
        
        const snapshotsModal = document.getElementById('snapshotsListModal');
        if (snapshotsModal && snapshotsModal.classList.contains('show')) {
            await refreshSnapshotsList();
        }
    } else {
        showToast(res.error || '<?php echo $lang1061; ?>', 'danger');
    }
}

async function deleteSnapshot(vgName, snapshotName) {
    if (!confirm(`<?php echo $lang1062; ?> ${snapshotName}?\n\n<?php echo $lang1063; ?>`)) return;
    
    showProgress(`<?php echo $lang1064; ?> ${snapshotName}...`, true);
    
    const res = await lvmApiCall('delete_snapshot', {
        vg_name: vgName,
        snapshot_name: snapshotName
    });
    
    hideProgress();
    
    if (res.success) {
        const snapshotModal = bootstrap.Modal.getInstance(document.getElementById('snapshotInfoModal'));
        if (snapshotModal) snapshotModal.hide();
        
        await refreshAll(true);
        showToast(`<?php echo $lang1065; ?> ${snapshotName} <?php echo $lang1066; ?>`, 'success');
        
        const snapshotsModal = document.getElementById('snapshotsListModal');
        if (snapshotsModal && snapshotsModal.classList.contains('show')) {
            await refreshSnapshotsList();
        }
    } else {
        showToast(res.error || '<?php echo $lang1067; ?>', 'danger');
    }
}

function executeRestoreSnapshot() {
    if (currentSnapshotInfo) {
        restoreSnapshot(currentSnapshotInfo.vg_name, currentSnapshotInfo.name, currentSnapshotInfo.origin);
    }
}

function executeDeleteSnapshot() {
    if (currentSnapshotInfo) {
        bootstrap.Modal.getInstance(document.getElementById('snapshotInfoModal')).hide();
        deleteSnapshot(currentSnapshotInfo.vg_name, currentSnapshotInfo.name);
    }
}

async function deleteLogicalVolume(vgName, lvName) {
    if (!confirm(`<?php echo $lang1068; ?> ${vgName}/${lvName}?\n\n<?php echo $lang1069; ?>`)) return;
    showProgress(`<?php echo $lang1070; ?> ${lvName}...`, true);
    const res = await apiCall('lvm_delete_lv', { vg_name: vgName, lv_name: lvName });
    hideProgress();
    if (res.success) { showToast(`<?php echo $lang1071; ?> ${lvName}`, 'success'); await refreshAll(true); }
    else showToast(res.error || '<?php echo $lang1072; ?>', 'danger');
}

async function showCreateRaidPartitionModal(raidName, totalSize) {
    showLoader();
    
    const freeSpaceRes = await apiCall('get_raid_free_space', { raid_name: raidName });
    hideLoader();
    
    let freeGb = 0;
    let existingPartsCount = 0;
    let freeSpaceHtml = '';
    
    if (freeSpaceRes.success) {
        freeGb = freeSpaceRes.free_gb;
        existingPartsCount = freeSpaceRes.existing_partitions_count;
        
        if (freeGb <= 0) {
            showToast('<?php echo $lang1073; ?>', 'danger');
            return;
        }
        
        freeSpaceHtml = `
            <div class="alert alert-info mb-3">
                <i class="fas fa-info-circle"></i> 
                <strong><?php echo $lang1074; ?></strong> ${freeGb} GB
                ${existingPartsCount > 0 ? `<br><strong><?php echo $lang1075; ?></strong> ${existingPartsCount}` : ''}
                <br><small><?php echo $lang1076; ?></small>
            </div>
        `;
    } else {
        freeSpaceHtml = `<div class="alert alert-warning mb-3"><i class="fas fa-exclamation-triangle"></i> <?php echo $lang1077; ?></div>`;
    }
    
    const maxSizeGb = freeGb > 0 ? freeGb : (totalSize / 1024 / 1024 / 1024).toFixed(1);
    
    const modalHtml = `
        <div class="modal fade" id="createRaidPartitionModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-plus-circle"></i> <?php echo $lang1078; ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p><strong><?php echo $lang1079; ?></strong> ${raidName}</p>
                        <p><strong><?php echo $lang1080; ?></strong> ${maxSizeGb} GB</p>
                        ${freeSpaceHtml}
                        <div class="mb-3">
                            <label class="form-label"><?php echo $lang1081; ?></label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="raidPartSize" step="0.1" placeholder="<?php echo $lang1082; ?>">
                                <select class="form-select" id="raidPartUnit" style="width: 80px;">
                                    <option value="G" selected>GB</option>
                                    <option value="M">MB</option>
                                    <option value="T">TB</option>
                                </select>
                            </div>
                            <small class="text-muted">
                                💡 <strong><?php echo $lang1083; ?></strong> <?php echo $lang1084; ?> (${freeGb} GB)
                            </small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><?php echo $lang1085; ?></label>
                            <select class="form-select" id="raidPartFs">
                                <optgroup label="Linux">
                                    <option value="ext4" selected>ext4 <?php echo $lang1086; ?></option>
                                    <option value="ext3">ext3</option>
                                    <option value="ext2">ext2</option>
                                    <option value="xfs">XFS</option>
                                    <option value="btrfs">Btrfs</option>
                                </optgroup>
                                <optgroup label="Windows">
                                    <option value="ntfs">NTFS</option>
                                    <option value="fat32">FAT32</option>
                                    <option value="exfat">exFAT</option>
                                </optgroup>
                                <option value="none"><?php echo $lang1087; ?></option>
                            </select>
                        </div>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i> 
                            <?php echo $lang1088; ?> <code>/dev/${raidName}p[number]</code>
                            ${existingPartsCount > 0 ? `<br>⚠️ <?php echo $lang1089; ?>` : ''}
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang1090; ?></button>
                        <button class="btn btn-primary" onclick="executeCreateRaidPartition('${raidName}')"><?php echo $lang1091; ?></button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    const oldModal = document.getElementById('createRaidPartitionModal');
    if (oldModal) oldModal.remove();
    
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    new bootstrap.Modal(document.getElementById('createRaidPartitionModal')).show();
}

async function executeCreateRaidPartition(raidName) {
    let size = document.getElementById('raidPartSize').value;
    const unit = document.getElementById('raidPartUnit').value;
    const fsType = document.getElementById('raidPartFs').value;
    const format = fsType !== 'none';
    
    let fullSize = '';
    if (size && parseFloat(size) > 0) {
        fullSize = size + unit;
    }
    
    const modal = bootstrap.Modal.getInstance(document.getElementById('createRaidPartitionModal'));
    modal.hide();
    
    showProgress(`<?php echo $lang1092; ?> ${raidName}...`, true);
    updateProgressDetails(fullSize ? `<?php echo $lang1093; ?> ${fullSize}` : '<?php echo $lang1094; ?>');
    updateProgressDetails(`<?php echo $lang1095; ?> ${format ? fsType : 'no format'}`);
    
    const res = await apiCall('create_partition_on_raid', { 
        raid_name: raidName, 
        size: fullSize, 
        fs_type: fsType === 'none' ? 'ext4' : fsType, 
        format: format 
    });
    
    hideProgress();
    
    if (res.success) {
        let msg = `<?php echo $lang1096; ?> ${res.partition}`;
        if (format) {
            msg += `, <?php echo $lang1097; ?> ${fsType}`;
        } else {
            msg += ` <?php echo $lang1098; ?>`;
        }
        if (res.used_all_space) {
            msg += ` <?php echo $lang1099; ?>`;
        }
        showToast(msg, 'success');
        await refreshAll(true);
        if (currentDisk === raidName) renderDiskDetails(raidName);
    } else {
        showToast(res.error || '<?php echo $lang1100; ?>', 'danger');
        showLogs();
    }
}

// ==================== БЕЗОПАСНОЕ ИЗВЛЕЧЕНИЕ ====================
async function safeRemoveDisk(diskName) {
    if (!confirm(`<?php echo $lang1101; ?> ${diskName}\n\n<?php echo $lang1102; ?>\n\n<?php echo $lang1103; ?>`)) return;
    showProgress(`<?php echo $lang1104; ?> ${diskName}...`, true);
    updateProgressDetails('<?php echo $lang1105; ?>');
    const res = await apiCall('safe_remove', { disk: diskName });
    hideProgress();
    if (res.success) { showToast(`<?php echo $lang1106; ?> ${diskName} <?php echo $lang1107; ?>`, 'success'); await refreshAll(true); if (currentDisk === diskName) { currentDisk = null; showEmptyState(); } }
    else { const errors = res.errors ? res.errors.join(', ') : '<?php echo $lang1108; ?>'; showToast(errors, 'danger'); }
}

// ==================== ИНФОРМАЦИЯ SMART И ДИСКОВ ====================
async function showDiskInfo(diskName) {
    showLoader();
    const res = await apiCall('disk_info', { disk: diskName });
    hideLoader();
    if (res.success && res.info) {
        const info = res.info;
        const tempClass = info.temperature ? (info.temperature > 60 ? 'temperature-critical' : (info.temperature > 50 ? 'temperature-warning' : 'temperature-normal')) : '';
        const html = `<div class="info-grid"><div class="info-card"><div class="info-label"><i class="fas fa-microchip"></i> <?php echo $lang1109; ?></div><div class="info-value">${info.model || '—'}</div></div><div class="info-card"><div class="info-label"><i class="fas fa-tag"></i> <?php echo $lang1110; ?></div><div class="info-value">${info.serial || '—'}</div></div><div class="info-card"><div class="info-label"><i class="fas fa-building"></i> <?php echo $lang1111; ?></div><div class="info-value">${info.vendor || '—'}</div></div><div class="info-card"><div class="info-label"><i class="fas fa-code-branch"></i> <?php echo $lang1112; ?></div><div class="info-value">${info.revision || '—'}</div></div><div class="info-card"><div class="info-label"><i class="fas fa-hdd"></i> <?php echo $lang1113; ?></div><div class="info-value">${info.type || '—'}</div></div><div class="info-card"><div class="info-label"><i class="fas fa-ruler"></i> <?php echo $lang1114; ?></div><div class="info-value">${info.size_gb ? info.size_gb + ' GB' : '—'}</div></div><div class="info-card"><div class="info-label"><i class="fas fa-thermometer-half"></i> <?php echo $lang1115; ?></div><div class="info-value ${tempClass}">${info.temperature ? info.temperature + '°C' : '—'}</div></div><div class="info-card"><div class="info-label"><i class="fas fa-power-off"></i> <?php echo $lang1116; ?></div><div class="info-value">${info.state || '—'}</div></div></div>`;
        document.getElementById('diskInfoContent').innerHTML = html;
        new bootstrap.Modal(document.getElementById('diskInfoModal')).show();
    } else showToast('<?php echo $lang1117; ?>', 'danger');
}

async function showSmartInfo(diskName) {
    showLoader();
    const res = await apiCall('get_smart', { disk: diskName });
    hideLoader();
    if (res.success && res.smart) {
        const smart = res.smart;
        let healthHtml = '';
        if (smart.health === 'PASSED') healthHtml = '<span class="smart-health-passed"><i class="fas fa-check-circle"></i> <?php echo $lang1118; ?></span>';
        else if (smart.health === 'FAILED') healthHtml = '<span class="smart-health-failed"><i class="fas fa-exclamation-circle"></i> <?php echo $lang1119; ?></span>';
        else healthHtml = '<span class="text-warning"><i class="fas fa-question-circle"></i> ' + (smart.health_text || 'Unknown') + '</span>';
        
        let attributesHtml = '';
        if (smart.attributes && smart.attributes.length > 0) {
            attributesHtml = `<h6 class="mt-4"><?php echo $lang1120; ?></h6><table class="smart-attributes-table"><thead><tr><th>ID</th><th><?php echo $lang1121; ?></th><th><?php echo $lang1122; ?></th><th><?php echo $lang1123; ?></th><th><?php echo $lang1124; ?></th><th><?php echo $lang1125; ?></th></tr></thead><tbody>${smart.attributes.map(attr => `<tr><td>${attr.id}</td><td>${attr.name}</td><td>${attr.value}</td><td>${attr.worst}</td><td>${attr.threshold}</td><td>${attr.raw}</td></tr>`).join('')}</tbody></table>`;
        }
        
        const tempClass = smart.temperature ? (smart.temperature > 60 ? 'temperature-critical' : (smart.temperature > 50 ? 'temperature-warning' : 'temperature-normal')) : '';
        const html = `<div class="info-grid"><div class="info-card"><div class="info-label"><i class="fas fa-heartbeat"></i> <?php echo $lang1126; ?></div><div class="info-value">${healthHtml}</div></div><div class="info-card"><div class="info-label"><i class="fas fa-thermometer-half"></i> <?php echo $lang1127; ?></div><div class="info-value ${tempClass}">${smart.temperature ? smart.temperature + '°C' : '—'}</div></div>${smart.percentage_used !== null ? `<div class="info-card"><div class="info-label"><i class="fas fa-percent"></i> <?php echo $lang1128; ?></div><div class="info-value">${smart.percentage_used}%</div></div>` : ''}${smart.power_on_days !== null ? `<div class="info-card"><div class="info-label"><i class="fas fa-clock"></i> <?php echo $lang1129; ?></div><div class="info-value">${smart.power_on_days} <?php echo $lang1130; ?> (${smart.power_on_hours} <?php echo $lang1131; ?>)</div></div>` : ''}${smart.reallocated_sectors !== null ? `<div class="info-card"><div class="info-label"><i class="fas fa-exclamation-triangle"></i> <?php echo $lang1132; ?></div><div class="info-value ${smart.reallocated_sectors > 0 ? 'text-danger' : ''}">${smart.reallocated_sectors}</div></div>` : ''}${smart.current_pending_sector !== null ? `<div class="info-card"><div class="info-label"><i class="fas fa-hourglass-half"></i> <?php echo $lang1133; ?></div><div class="info-value ${smart.current_pending_sector > 0 ? 'text-warning' : ''}">${smart.current_pending_sector}</div></div>` : ''}${smart.offline_uncorrectable !== null ? `<div class="info-card"><div class="info-label"><i class="fas fa-skull-crossbones"></i> <?php echo $lang1134; ?></div><div class="info-value ${smart.offline_uncorrectable > 0 ? 'text-danger' : ''}">${smart.offline_uncorrectable}</div></div>` : ''}${smart.udma_crc_errors !== null ? `<div class="info-card"><div class="info-label"><i class="fas fa-plug"></i> <?php echo $lang1135; ?></div><div class="info-value">${smart.udma_crc_errors}</div></div>` : ''}</div>${attributesHtml}${!smart.available ? '<div class="alert alert-warning mt-3"><?php echo $lang1136; ?></div>' : ''}`;
        document.getElementById('smartContent').innerHTML = html;
        new bootstrap.Modal(document.getElementById('smartModal')).show();
    } else showToast('<?php echo $lang1137; ?>', 'danger');
}

// ==================== ЛОГИ ====================
async function showLogs() { const modal = new bootstrap.Modal(document.getElementById('logsModal')); modal.show(); await refreshLogs(); }
async function refreshLogs() {
    const container = document.getElementById('logsContent');
    container.innerHTML = '<div class="text-center"><?php echo $lang1138; ?></div>';
    const res = await apiCall('get_logs', { lines: 100 });
    if (res.success && res.logs) container.innerHTML = res.logs.map(log => { let logClass = 'log-info'; if (log.includes('| error |')) logClass = 'log-error'; else if (log.includes('| success |')) logClass = 'log-success'; else if (log.includes('| warning |')) logClass = 'log-warning'; return `<div class="log-entry ${logClass}">${escapeHtml(log.trim())}</div>`; }).join('');
    else container.innerHTML = '<div class="text-danger"><?php echo $lang1139; ?></div>';
}
async function clearLogs() { if (!confirm('<?php echo $lang1140; ?>')) return; const res = await apiCall('clear_logs'); if (res.success) { showToast('<?php echo $lang1141; ?>', 'success'); refreshLogs(); } }

// ==================== FSTAB ====================
async function viewFstab() {
    showLoader();
    const res = await apiCall('get_fstab');
    hideLoader();
    if (res.success && res.entries) {
        if (res.entries.length === 0) { showToast('<?php echo $lang1142; ?>', 'info'); return; }
        let html = '<div class="list-group">';
        for (const entry of res.entries) {
            let icon = '<i class="fas fa-hdd"></i>';
            if (entry.is_uuid) icon = '<i class="fas fa-key"></i>';
            html += `<div class="list-group-item list-group-item-action"><div class="d-flex justify-content-between align-items-start"><div style="flex: 1;"><div class="mb-1">${icon} <code>${escapeHtml(entry.device)}</code>${entry.device_name && entry.device_name !== entry.device ? `<br><small class="text-muted">→ ${escapeHtml(entry.device_name)}</small>` : ''}</div><div><small>📁 <strong>${escapeHtml(entry.mount_point)}</strong></small><br><small>💾 ${escapeHtml(entry.fstype)} | ⚙️ ${escapeHtml(entry.options)}</small>${entry.uuid ? `<br><small class="text-muted">🔑 UUID: ${escapeHtml(entry.uuid)}</small>` : ''}</div></div><div class="btn-group-vertical"><button class="btn btn-sm btn-outline-danger mb-1" onclick="removeFstabEntry('${entry.uuid || ''}', '${escapeHtml(entry.mount_point)}', '${escapeHtml(entry.device)}')" title="Remove from fstab"><i class="fas fa-trash"></i></button><button class="btn btn-sm btn-outline-success" onclick="mountFstabEntry('${escapeHtml(entry.mount_point)}')" title="<?php echo $lang1143; ?>"><i class="fa fa-play"></i></button></div></div></div>`;
        }
        html += '</div><div class="mt-3"><button class="btn btn-sm btn-secondary" onclick="refreshFstab()"><i class="fas fa-sync-alt"></i> <?php echo $lang1144; ?></button><button class="btn btn-sm btn-danger" onclick="mountAllFstab()"><i class="fa fa-play"></i> <?php echo $lang1145; ?></button></div>';
        const modalHtml = `<div class="modal fade" id="fstabModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-header"><h5 class="modal-title"><i class="fas fa-bookmark"></i> /etc/fstab</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body" style="max-height: 500px; overflow-y: auto;">${html}</div><div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang1146; ?></button></div></div></div></div>`;
        const oldModal = document.getElementById('fstabModal'); if (oldModal) oldModal.remove();
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        new bootstrap.Modal(document.getElementById('fstabModal')).show();
    } else showToast(res.error || '<?php echo $lang1147; ?>', 'danger');
}

async function removeFstabEntry(uuid, mountPoint, device) {
    let confirmMsg = `<?php echo $lang1148; ?>\n\n` + (mountPoint ? `<?php echo $lang1149; ?> ${mountPoint}\n` : '') + (device ? `<?php echo $lang1150; ?> ${device}\n` : '') + (uuid ? `UUID: ${uuid}\n` : '') + `\n<?php echo $lang1151; ?>`;
    if (!confirm(confirmMsg)) return;
    showProgress("Deleting in fstab...", true);
    let res = uuid ? await apiCall('remove_fstab_entry', { uuid: uuid }) : await apiCall('remove_fstab_entry', { mount_point: mountPoint });
    hideProgress();
    if (res.success) { showToast('<?php echo $lang1152; ?>', 'success'); const modal = bootstrap.Modal.getInstance(document.getElementById('fstabModal')); if (modal) modal.hide(); refreshAll(true); }
    else showToast(res.error || '<?php echo $lang1153; ?>', 'danger');
}

async function mountFstabEntry(mountPoint) {
    showProgress(`<?php echo $lang1154; ?> ${mountPoint}...`, true);
    const res = await apiCall('mount_fstab_entry', { mount_point: mountPoint });
    hideProgress();
    if (res.success) { showToast(`<?php echo $lang1155; ?> ${mountPoint}`, 'success'); refreshAll(true); }
    else showToast(res.error || '<?php echo $lang1156; ?>', 'danger');
}

async function mountAllFstab() {
    if (!confirm('<?php echo $lang1157; ?>')) return;
    showProgress("<?php echo $lang1158; ?>", true);
    const res = await apiCall('mount_all_fstab');
    hideProgress();
    if (res.success) showToast(`<?php echo $lang1159; ?> ${res.mounted || 0}, <?php echo $lang1160; ?> ${res.errors || 0}`, res.errors > 0 ? 'warning' : 'success');
    else showToast(res.error || 'Error', 'danger');
    refreshAll(true);
}

async function refreshFstab() { const modal = bootstrap.Modal.getInstance(document.getElementById('fstabModal')); if (modal) { modal.hide(); setTimeout(() => viewFstab(), 200); } else viewFstab(); }

// ==================== PARTED CONSOLE ====================
function openPartedConsole() { const modal = new bootstrap.Modal(document.getElementById('partedModal')); modal.show(); document.getElementById('partedConnectPanel').style.display = 'block'; document.getElementById('partedConsolePanel').style.display = 'none'; document.getElementById('partedConnectError').style.display = 'none'; }
async function connectPartedConsole() {
    showLoader();
    try {
        const res = await apiCall('exec_command', { command: 'echo "Shell ready"' });
        if (res.success) {
            document.getElementById('partedConnectPanel').style.display = 'none';
            document.getElementById('partedConsolePanel').style.display = 'block';
            shellOutputElement = document.getElementById('partedConsole');
            shellCommandInput = document.getElementById('partedCommand');
            shellOutputElement.innerHTML = '';
            appendToShell('========================================', 'info');
            appendToShell('Local Shell (Direct Execution)', 'success');
            appendToShell('========================================', 'info');
            appendToShell('<?php echo $lang1161; ?>', 'info');
            appendToShell('  • parted /dev/sda print - disk information', 'info');
            appendToShell('  • lsblk - list of all disks', 'info');
            appendToShell('  • fdisk -l /dev/sda - information about partition', 'info');
            appendToShell('  • blkid - UUID partition', 'info');
            appendToShell('  • exit - close console', 'info');
            appendToShell('========================================', 'info');
            appendToShell('<?php echo $lang1162; ?>', 'success');
            shellCommandInput.disabled = false;
            shellCommandInput.focus();
            document.getElementById('partedSendBtn').disabled = false;
        } else {
            const errorDiv = document.getElementById('partedConnectError');
            errorDiv.innerHTML = '<i class="fas fa-exclamation-triangle"></i> ' + (res.error || '<?php echo $lang1163; ?>');
            errorDiv.style.display = 'block';
        }
    } catch (e) { const errorDiv = document.getElementById('partedConnectError'); errorDiv.innerHTML = '<i class="fas fa-exclamation-triangle"></i> <?php echo $lang1164; ?> ' + e.message; errorDiv.style.display = 'block'; }
    hideLoader();
}
async function sendShellCommand() {
    event.preventDefault();
    const command = shellCommandInput.value.trim();
    if (!command) return;
    shellCommandInput.value = '';
    appendToShell(`$ ${command}`, 'command');
    if (command.toLowerCase() === 'exit' || command.toLowerCase() === 'logout') { appendToShell('Closing session...', 'warning'); setTimeout(() => closeShellConsole(), 1000); return; }
    showLoader();
    try {
        const res = await apiCall('exec_command', { command: command });
        if (res.success) { if (res.output && res.output.trim()) appendToShell(res.output, 'output'); }
        else appendToShell(`Error: ${res.error}`, 'error');
    } catch (e) { appendToShell(`Connection error: ${e.message}`, 'error'); }
    hideLoader();
}
async function closeShellConsole() { const modal = bootstrap.Modal.getInstance(document.getElementById('partedModal')); if (modal) modal.hide(); }
function appendToShell(text, type) {
    if (!shellOutputElement) return;
    if (shellOutputElement.innerHTML.includes('Connecting')) shellOutputElement.innerHTML = '';
    let color = '#d4d4d4';
    switch(type) { case 'command': color = '#4ec9b0'; break; case 'error': color = '#f48771'; break; case 'warning': color = '#dcdcaa'; break; case 'info': color = '#6a9955'; break; case 'success': color = '#6a9955'; break; }
    const lines = text.split('\n');
    for (const l of lines) { if (l === '') continue; const lineDiv = document.createElement('div'); lineDiv.style.color = color; lineDiv.style.fontFamily = 'monospace'; lineDiv.style.fontSize = '13px'; lineDiv.style.whiteSpace = 'pre-wrap'; lineDiv.style.wordBreak = 'break-all'; lineDiv.innerHTML = escapeHtml(l); shellOutputElement.appendChild(lineDiv); }
    shellOutputElement.scrollTop = shellOutputElement.scrollHeight;
}

// ==================== КОНТЕКСТНОЕ МЕНЮ ====================
function showPartitionContextMenu(event, diskName, partitionName, fsType, isMounted, hasFilesystem) {
    event.preventDefault(); event.stopPropagation();
    if (contextMenu) contextMenu.remove();
    const menu = document.createElement('div');
    menu.className = 'context-menu';
    menu.style.left = event.pageX + 'px';
    menu.style.top = event.pageY + 'px';
    let items = '';
    if (!isMounted && hasFilesystem) items += `<div class="context-menu-item" onclick="showMountModal('${partitionName}'); closeContextMenu();"><i class="fa fa-play"></i> <?php echo $lang1165; ?></div>`;
    items += `<div class="context-menu-item" onclick="showFormatModal('${partitionName}'); closeContextMenu();"><i class="fa fa-eraser"></i> ${hasFilesystem ? '<?php echo $lang1166; ?>' : '<?php echo $lang1167; ?>'}</div>`;
    if (isMounted) items += `<div class="context-menu-item" onclick="umountPartition('${partitionName}'); closeContextMenu();"><i class="fas fa-eject"></i> <?php echo $lang1168; ?></div>`;
    if (hasFilesystem && fsType !== 'swap' && fsType !== 'unknown') { items += `<div class="context-menu-divider"></div><div class="context-menu-item" onclick="showResizeModal('${partitionName}', '${fsType}'); closeContextMenu();"><i class="fas fa-expand-alt"></i> <?php echo $lang1169; ?></div>`; }
    items += `<div class="context-menu-divider"></div><div class="context-menu-item danger" onclick="deletePartition('${partitionName}'); closeContextMenu();"><i class="fas fa-trash"></i> <?php echo $lang1170; ?></div>`;
    menu.innerHTML = items;
    document.body.appendChild(menu);
    contextMenu = menu;
    function closeHandler(e) { if (!menu.contains(e.target)) { closeContextMenu(); document.removeEventListener('click', closeHandler); } }
    setTimeout(() => document.addEventListener('click', closeHandler), 10);
}
function closeContextMenu() { if (contextMenu) { contextMenu.remove(); contextMenu = null; } }

// ==================== АВТООБНОВЛЕНИЕ ====================
function startAutoRefresh() { if (refreshInterval) clearInterval(refreshInterval); refreshInterval = setInterval(() => refreshAll(false), 5000); }
refreshAll(true);
startAutoRefresh();
setupFilters();
document.addEventListener('click', () => { if (contextMenu) closeContextMenu(); });
</script>

</body>
</html>