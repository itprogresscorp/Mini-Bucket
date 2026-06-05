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
$menu = require_once 'menu.php';

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

// Статистика
$stats = [
    'mounted' => 0,
    'fstab' => 0
];

if ($response) {
    $data = json_decode($response, true);
    if ($data && $data['success']) {
        $stats['mounted'] = count($data['mounted']);
        $stats['fstab'] = count($data['fstab_entries']);
    }
}

$viewMode = $_COOKIE['mount_view_mode'] ?? 'table';
if (isset($_GET['view_mode'])) {
    $viewMode = $_GET['view_mode'];
    setcookie('mount_view_mode', $viewMode, time() + 86400 * 30, '/');
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Mount Manager — Mini-b</title>
    <link href="lib/bootstrap-5.3.8-dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="lib/fontawesome-free-6.7.2-web/css/all.min.css">
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
        * { box-sizing: border-box; }
        body { background: #f5f5f7; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 12px;
        }
        .page-title {
            font-size: 28px;
            font-weight: 600;
            margin: 0;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .stats-group {
            display: flex;
            gap: 16px;
            background: white;
            padding: 6px 16px;
            border-radius: 40px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .stat-badge {
            display: flex;
            align-items: baseline;
            gap: 6px;
            font-size: 13px;
        }
        .stat-badge .number {
            font-weight: 700;
            font-size: 18px;
            color: #007aff;
        }
        .stat-badge .label {
            color: #6c757d;
        }
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        .btn-icon {
            padding: 6px 12px;
            border-radius: 10px;
            font-size: 13px;
        }
        
        .filters-bar {
            background: white;
            border-radius: 16px;
            padding: 12px 20px;
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            align-items: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .search-box {
            position: relative;
            flex: 1;
            min-width: 200px;
            max-width: 280px;
        }
        .search-box input {
            padding-left: 34px;
            border-radius: 10px;
            border: 1px solid #e5e5ea;
            font-size: 14px;
        }
        .search-box i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #8e8e93;
            font-size: 14px;
        }
        .filter-tabs {
            display: flex;
            gap: 6px;
        }
        .filter-tab {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 13px;
            cursor: pointer;
            background: #f0f0f5;
            color: #555;
            transition: all 0.2s;
        }
        .filter-tab:hover { background: #e5e5ea; }
        .filter-tab.active {
            background: #007aff;
            color: white;
        }
        .view-toggle {
            display: flex;
            gap: 4px;
            background: #f0f0f5;
            padding: 3px;
            border-radius: 12px;
        }
        .view-toggle button {
            padding: 5px 12px;
            border-radius: 9px;
            border: none;
            background: transparent;
            font-size: 14px;
        }
        .view-toggle button.active {
            background: white;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        
        .card {
            background: white;
            border-radius: 20px;
            border: none;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            overflow: hidden;
        }
        .card-header {
            padding: 16px 20px;
            background: white;
            border-bottom: 1px solid #f0f0f0;
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .table { margin-bottom: 0; }
        .table th { 
            background: #fafafc; 
            font-weight: 500; 
            font-size: 13px;
            border-bottom: 1px solid #f0f0f0;
        }
        .table td { font-size: 14px; vertical-align: middle; }
        .btn-sm { padding: 4px 8px; font-size: 12px; border-radius: 8px; margin: 0 1px; }
        .badge { font-weight: 500; padding: 4px 8px; border-radius: 8px; }
        
        .mount-card {
            border: 1px solid #f0f0f0;
            border-radius: 16px;
            padding: 14px;
            transition: all 0.2s;
            background: white;
            height: 100%;
            position: relative;
        }
        .mount-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.05); transform: translateY(-2px); }
        .mount-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: #f0f0f5;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            margin-bottom: 10px;
        }
        .mount-device { font-weight: 600; font-size: 15px; margin-bottom: 4px; font-family: monospace; word-break: break-all; }
        .mount-info { font-size: 11px; color: #6c757d; margin-bottom: 2px; }
        .fstab-badge {
            position: absolute;
            top: 10px;
            right: 10px;
        }
        .alert {
            border-radius: 14px;
            font-size: 14px;
            margin-bottom: 16px;
        }
        
        .folder-browser {
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #e5e5ea;
            border-radius: 12px;
            background: #f9f9fb;
        }
        .folder-item {
            cursor: pointer;
            padding: 10px 15px;
            border-radius: 8px;
            transition: all 0.2s;
        }
        .folder-item:hover { background-color: #e9ecef; }
        .folder-item i { margin-right: 8px; color: #ffc107; }
        .breadcrumb-item { cursor: pointer; }
        .breadcrumb-item a { text-decoration: none; color: #007aff; cursor: pointer; }
        .breadcrumb-item a:hover { text-decoration: underline; }
        
        .device-select-list {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #e5e5ea;
            border-radius: 12px;
            background: #f9f9fb;
        }
        .device-item {
            cursor: pointer;
            padding: 10px 15px;
            border-bottom: 1px solid #e5e5ea;
            transition: background 0.2s;
        }
        .device-item:last-child { border-bottom: none; }
        .device-item:hover { background: #e9ecef; }
        .device-item.selected { background: #d1e7ff; }
        
        .config-section {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .config-section-title {
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 12px;
            color: #495057;
        }
   
  
        .app-container { display: flex; }
        .main-content {
            margin-left: 260px;
            padding: 24px 32px;
            flex: 1;
        }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); transition: transform 0.3s; z-index: 999; position: fixed; background: white; height: 100%; top: 0; left: 0; width: 260px; }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 20px; }
        }
        
        .refresh-btn {
            cursor: pointer;
            transition: transform 0.2s;
        }
        .refresh-btn:hover { transform: rotate(180deg); }
        
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
        
        .cursor-pointer { cursor: pointer; }
        .log-viewer { font-family: monospace; font-size: 11px; }
    </style>
</head>
<body>
<div id="applePreloader" class="apple-preloader">
    <div class="apple-spinner"></div>
    <div class="apple-spinner-text">Загрузка...</div>
</div>

<div class="top-bar">
    <div class="top-bar-left">
        <h1><i class="fas fa-bucket"></i> Mini-B</h1>
    </div>
    <div class="top-bar-right">
        <i class="fas fa-hdd"></i> Mount Manager
		<div class="host-selector" style="margin-left: 20px;">
            <select id="hostSelector" style="background: rgba(255,255,255,0.9); border: 1px solid #ddd; border-radius: 20px; padding: 6px 30px 6px 15px; font-size: 14px; cursor: pointer;">
                <option value="">Loading...</option>
            </select>
        </div>
        <div class="stat-badge"><span class="number" id="statMounted"><?= $stats['mounted'] ?></span><span class="label">Mounted</span></div>
        <div class="stat-badge"><span class="number" id="statFstab"><?= $stats['fstab'] ?></span><span class="label">fstab</span></div>
        <div class="action-buttons">
            <div class="dropdown">
                <button class="btn btn-primary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-plus"></i> Mount
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#mountLocalModal"><i class="fas fa-hdd"></i> Local Device</a></li>
                    <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#mountRaidModal"><i class="fas fa-server"></i> RAID Device</a></li>
                    <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#mountLvmModal"><i class="fas fa-chart-pie"></i> LVM Volume</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#mountCifsModal"><i class="fab fa-windows"></i> SMB/CIFS Share</a></li>
                    <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#mountNfsModal"><i class="fab fa-linux"></i> NFS Share</a></li>
                </ul>
            </div>
        </div>
        <i class="fas fa-sync-alt refresh-btn text-muted" onclick="refreshAllData()" title="Обновить"></i>
    </div>
</div>

<div class="app-container">
    <?php echo $menu; ?>
    
    <main class="main-content">
	<div id="alertContainer"></div>
        <div id="globalAlert"></div>
        
        <!-- ФИЛЬТРЫ -->
        <div class="filters-bar">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" class="form-control" placeholder="Search..." onkeyup="filterMounts()">
            </div>
            <div class="filter-tabs">
                <span class="filter-tab active" data-type="all" onclick="setFilter('all')">All</span>
                <span class="filter-tab" data-type="local" onclick="setFilter('local')"><i class="fas fa-hdd"></i> Local</span>
                <span class="filter-tab" data-type="network" onclick="setFilter('network')"><i class="fas fa-network-wired"></i> Network</span>
            </div>
            <div class="form-check form-switch ms-auto">
                <input class="form-check-input" type="checkbox" id="showFstabOnly" onchange="filterMounts()">
                <label class="form-check-label switch-label" for="showFstabOnly">In fstab only</label>
            </div>
            <div class="view-toggle">
                <button id="viewTableBtn" class="<?= $viewMode == 'table' ? 'active' : '' ?>" onclick="setViewMode('table')"><i class="fas fa-table"></i></button>
                <button id="viewGridBtn" class="<?= $viewMode == 'grid' ? 'active' : '' ?>" onclick="setViewMode('grid')"><i class="fas fa-th-large"></i></button>
            </div>
        </div>
        
        <!-- ТАБЛИЦА -->
        <div class="card">
            <div class="card-header">
                <span><i class="fas fa-hdd me-2"></i>Mounted Filesystems</span>
            </div>
            
            <div id="tableView" class="table-responsive" style="display: <?= $viewMode == 'table' ? 'block' : 'none' ?>;">
                <table class="table table-hover align-middle" id="mountsTable">
                    <thead>
                        <tr><th>Type</th><th>Device/Source</th><th>Mount Point</th><th>Filesystem</th><th>Options</th><th>fstab</th><th>Actions</th></tr>
                    </thead>
                    <tbody id="tableMountsContainer"></tbody>
                </table>
            </div>
            
            <div id="gridView" class="p-3" style="display: <?= $viewMode == 'grid' ? 'block' : 'none' ?>;">
                <div class="row" id="gridMountsContainer"></div>
            </div>
        </div>
        
        <!-- ЛОГИ (свёрнутые) -->
        <div class="card" id="logsCard" style="display: none;">
            <div class="card-header collapse-header cursor-pointer" onclick="toggleLogs()">
                <span><i class="fas fa-history me-2"></i>Operation Logs</span>
                <i class="fas fa-chevron-down" id="logsToggleIcon"></i>
            </div>
            <div id="logsContent" style="display: none;">
                <div class="p-3">
                    <div class="mb-2 text-end">
                        <button class="btn btn-sm btn-outline-danger" onclick="clearLogs()"><i class="fas fa-trash"></i> Clear</button>
                    </div>
                    <div class="log-viewer" id="logsContainer" style="background: #1e1e1e; color: #d4d4d4; padding: 12px; border-radius: 12px; max-height: 300px; overflow-y: auto;"></div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- ==================== МОДАЛЬНЫЕ ОКНА ==================== -->

<!-- Модалка: Local Device -->
<div class="modal fade" id="mountLocalModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-hdd"></i> Mount Local Device</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="mountLocalForm">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label">Device / Partition</label>
                            <select name="device" id="localDeviceSelect" class="form-select" required>
                                <option value="">Select device...</option>
                            </select>
                            <small class="text-muted">Available unmounted partitions, RAID devices and LVM volumes</small>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Mount Point</label>
                            <div class="input-group">
                                <input type="text" name="mount_point" id="localMountPoint" class="form-control" placeholder="/mnt/name" required>
                                <button type="button" class="btn btn-secondary" onclick="openFolderBrowser('localMountPoint')">
                                    <i class="fas fa-folder-open"></i> Browse
                                </button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Filesystem Type</label>
                            <select name="fstype" class="form-select">
                                <option value="auto">Auto-detect</option>
                                <option value="ext4">ext4</option>
                                <option value="ext3">ext3</option>
                                <option value="ext2">ext2</option>
                                <option value="ntfs-3g">NTFS</option>
                                <option value="exfat">exFAT</option>
                                <option value="vfat">FAT32</option>
                                <option value="xfs">XFS</option>
                                <option value="btrfs">Btrfs</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Owner UID</label>
                            <select name="uid" id="localUid" class="form-select"></select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Owner GID</label>
                            <select name="gid" id="localGid" class="form-select"></select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Mount Options</label>
                            <input type="text" name="mount_options" class="form-control" placeholder="defaults, noatime, etc." value="defaults">
                        </div>
                        <div class="col-md-12">
                            <div class="form-check">
                                <input type="checkbox" name="add_to_fstab" class="form-check-input" id="localAddToFstab">
                                <label class="form-check-label" for="localAddToFstab">Add to /etc/fstab (auto-mount on boot)</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Mount</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Модалка: RAID Device -->
<div class="modal fade" id="mountRaidModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-server"></i> Mount RAID Device</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="mountRaidForm">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label">RAID Device</label>
                            <select name="device" id="raidDeviceSelect" class="form-select" required>
                                <option value="">Select RAID device...</option>
                            </select>
                            <small class="text-muted">Available RAID arrays (md devices)</small>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Mount Point</label>
                            <div class="input-group">
                                <input type="text" name="mount_point" id="raidMountPoint" class="form-control" placeholder="/mnt/raid_name" required>
                                <button type="button" class="btn btn-secondary" onclick="openFolderBrowser('raidMountPoint')">
                                    <i class="fas fa-folder-open"></i> Browse
                                </button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Filesystem Type</label>
                            <select name="fstype" class="form-select">
                                <option value="auto">Auto-detect</option>
                                <option value="ext4">ext4</option>
                                <option value="ext3">ext3</option>
                                <option value="xfs">XFS</option>
                                <option value="btrfs">Btrfs</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Owner UID</label>
                            <select name="uid" id="raidUid" class="form-select"></select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Owner GID</label>
                            <select name="gid" id="raidGid" class="form-select"></select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Mount Options</label>
                            <input type="text" name="mount_options" class="form-control" placeholder="defaults, noatime, etc." value="defaults">
                        </div>
                        <div class="col-md-12">
                            <div class="form-check">
                                <input type="checkbox" name="add_to_fstab" class="form-check-input" id="raidAddToFstab">
                                <label class="form-check-label" for="raidAddToFstab">Add to /etc/fstab (auto-mount on boot)</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Mount</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Модалка: LVM Volume -->
<div class="modal fade" id="mountLvmModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-chart-pie"></i> Mount LVM Logical Volume</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="mountLvmForm">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label">Logical Volume</label>
                            <select name="device" id="lvmDeviceSelect" class="form-select" required>
                                <option value="">Select LVM volume...</option>
                            </select>
                            <small class="text-muted">Available unmounted LVM logical volumes</small>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Mount Point</label>
                            <div class="input-group">
                                <input type="text" name="mount_point" id="lvmMountPoint" class="form-control" placeholder="/mnt/lvm_volume" required>
                                <button type="button" class="btn btn-secondary" onclick="openFolderBrowser('lvmMountPoint')">
                                    <i class="fas fa-folder-open"></i> Browse
                                </button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Filesystem Type</label>
                            <select name="fstype" class="form-select">
                                <option value="auto">Auto-detect</option>
                                <option value="ext4">ext4</option>
                                <option value="ext3">ext3</option>
                                <option value="xfs">XFS</option>
                                <option value="btrfs">Btrfs</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Owner UID</label>
                            <select name="uid" id="lvmUid" class="form-select"></select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Owner GID</label>
                            <select name="gid" id="lvmGid" class="form-select"></select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Mount Options</label>
                            <input type="text" name="mount_options" class="form-control" placeholder="defaults, noatime, etc." value="defaults">
                        </div>
                        <div class="col-md-12">
                            <div class="form-check">
                                <input type="checkbox" name="add_to_fstab" class="form-check-input" id="lvmAddToFstab">
                                <label class="form-check-label" for="lvmAddToFstab">Add to /etc/fstab (auto-mount on boot)</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Mount</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Модалка: SMB/CIFS Share -->
<div class="modal fade" id="mountCifsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fab fa-windows"></i> Mount SMB/CIFS Share</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="mountCifsForm">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Server Address</label>
                            <input type="text" name="server" class="form-control" placeholder="192.168.1.100 or server-name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Share Name</label>
                            <input type="text" name="share" class="form-control" placeholder="shared_folder" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Mount Point</label>
                            <div class="input-group">
                                <input type="text" name="mount_point" id="cifsMountPoint" class="form-control" placeholder="/mnt/smb_share" required>
                                <button type="button" class="btn btn-secondary" onclick="openFolderBrowser('cifsMountPoint')">
                                    <i class="fas fa-folder-open"></i> Browse
                                </button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Username (optional)</label>
                            <input type="text" name="username" class="form-control" placeholder="guest">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Password (optional)</label>
                            <input type="password" name="password" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Domain/Workgroup</label>
                            <input type="text" name="domain" class="form-control" placeholder="WORKGROUP">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">SMB Version</label>
                            <select name="vers" class="form-select">
                                <option value="3.0">3.0 (recommended)</option>
                                <option value="2.1">2.1</option>
                                <option value="2.0">2.0</option>
                                <option value="1.0">1.0 (legacy)</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Owner UID</label>
                            <select name="uid" id="cifsUid" class="form-select"></select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Owner GID</label>
                            <select name="gid" id="cifsGid" class="form-select"></select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Charset</label>
                            <select name="iocharset" class="form-select">
                                <option value="utf8">UTF-8</option>
                                <option value="cp866">CP866</option>
                                <option value="cp1251">CP1251</option>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <div class="form-check">
                                <input type="checkbox" name="add_to_fstab" class="form-check-input" id="cifsAddToFstab">
                                <label class="form-check-label" for="cifsAddToFstab">Add to /etc/fstab (auto-mount on boot)</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Mount</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Модалка: NFS Share -->
<div class="modal fade" id="mountNfsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fab fa-linux"></i> Mount NFS Share</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="mountNfsForm">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Server Address</label>
                            <input type="text" name="server" class="form-control" placeholder="192.168.1.100 or server-name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Export Path</label>
                            <input type="text" name="export" class="form-control" placeholder="/exported/path" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Mount Point</label>
                            <div class="input-group">
                                <input type="text" name="mount_point" id="nfsMountPoint" class="form-control" placeholder="/mnt/nfs_share" required>
                                <button type="button" class="btn btn-secondary" onclick="openFolderBrowser('nfsMountPoint')">
                                    <i class="fas fa-folder-open"></i> Browse
                                </button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">NFS Version</label>
                            <select name="nfs_version" class="form-select">
                                <option value="4.2">4.2</option>
                                <option value="4.1">4.1</option>
                                <option value="4.0">4.0</option>
                                <option value="3">3</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Mount Options</label>
                            <input type="text" name="mount_options" class="form-control" placeholder="defaults, noatime, etc." value="defaults">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">rsize</label>
                            <input type="text" name="rsize" class="form-control" placeholder="1048576">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">wsize</label>
                            <input type="text" name="wsize" class="form-control" placeholder="1048576">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Protocol</label>
                            <select name="soft_hard" class="form-select">
                                <option value="hard">hard</option>
                                <option value="soft">soft</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input type="checkbox" name="intr" class="form-check-input" id="nfsIntr">
                                <label class="form-check-label" for="nfsIntr">Allow interrupts (intr)</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input type="checkbox" name="noatime" class="form-check-input" id="nfsNoatime">
                                <label class="form-check-label" for="nfsNoatime">No atime update (noatime)</label>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-check">
                                <input type="checkbox" name="add_to_fstab" class="form-check-input" id="nfsAddToFstab">
                                <label class="form-check-label" for="nfsAddToFstab">Add to /etc/fstab (auto-mount on boot)</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Mount</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Модалка: Browse Folder -->
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

<!-- Модалка: Create Folder -->
<div class="modal fade" id="createFolderDialog" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-folder-plus"></i> Create Folder</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Path:</label> <code id="createFolderPath"></code></div>
                <div class="mb-3"><label class="form-label">Folder Name</label><input type="text" id="newFolderName" class="form-control" placeholder="New folder"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" onclick="createNewFolder()">Create</button>
            </div>
        </div>
    </div>
</div>

<!-- Модалка: Edit Mount Point -->
<div class="modal fade" id="editMountPointModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i class="fas fa-edit"></i> Change Mount Point</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editMountPointForm">
                <div class="modal-body">
                    <input type="hidden" name="old_mount_point" id="editOldMountPoint">
                    <div class="mb-3">
                        <label class="form-label">Current Mount Point</label>
                        <input type="text" id="editCurrentMountPoint" class="form-control" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Mount Point</label>
                        <div class="input-group">
                            <input type="text" name="new_mount_point" id="editNewMountPoint" class="form-control" required>
                            <button type="button" class="btn btn-secondary" onclick="openFolderBrowserEdit()">
                                <i class="fas fa-folder-open"></i> Browse
                            </button>
                        </div>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" name="update_fstab" class="form-check-input" id="editUpdateFstab">
                        <label class="form-check-label" for="editUpdateFstab">Update /etc/fstab entry</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Change</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="lib/jquery-3.6.0-master/dist/jquery.min.js"></script>
<script src="lib/bootstrap-5.3.8-dist/js/bootstrap.bundle.min.js"></script>
<script>
// ==================== ГЛОБАЛЬНЫЕ ПЕРЕМЕННЫЕ ====================
const url = "<?php echo $current_host_id == 1 ? '/api/' : rtrim($host_url, '/') . '/'; ?>";
let mountedData = [];
let currentFilter = 'all';
let searchTerm = '';
let fstabOnly = false;
let currentBrowseTarget = null;
let currentBrowsePath = '/';
let systemUsers = [];

// ==================== ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ ====================
function showAlert(message, type = 'success') {
    const alertHtml = `<div class="alert alert-${type} alert-dismissible fade show" role="alert">
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

async function apiCall(action, method = 'GET', data = null, customApiKey = null) {
    let fullUrl = `${url}mount_master_api.php?action=${action}`;
    
    let fetchOptions = { 
        method: method, 
        headers: {
            'Content-Type': 'application/json'
        }
    };
    
    const apiKey = customApiKey || (window.apiConfig && window.apiConfig.apiKey);
    if (apiKey) {
        fetchOptions.headers['X-API-Key'] = apiKey;
    }
    
    if (method === 'POST' && data) {
        fetchOptions.body = JSON.stringify(data);
    } else if (method === 'GET' && data) {
        const params = new URLSearchParams();
        for (let [key, value] of Object.entries(data)) {
            params.append(key, value);
        }
        fullUrl += '&' + params.toString();
    }
    
    try {
        let response = await fetch(fullUrl, fetchOptions);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return await response.json();
    } catch(e) {
        
		console.error('API Error:', e);
        return { success: false, error: e.message };
		
    }
}

// ==================== ЗАГРУЗКА ДАННЫХ ====================
async function loadAllData() {
    let result = await apiCall('get_all');
    if (result.success) {
        mountedData = result.mounted || [];
        renderMounts();
        $('#statMounted').text(mountedData.length);
        $('#statFstab').text((result.fstab_entries || []).length);
    }
    return result;
}

async function loadSystemUsers() {
    let result = await apiCall('get_all');
    if (result.success && result.system_users) {
        systemUsers = result.system_users;
        populateUserSelects();
    }
}

function populateUserSelects() {
    let options = '<option value="www-data">www-data (web server)</option>';
    options += '<option value="root">root</option>';
    options += '<option value="nobody">nobody</option>';
    
    if (systemUsers && systemUsers.length) {
        systemUsers.forEach(user => {
            if (user.username !== 'www-data' && user.username !== 'root' && user.username !== 'nobody') {
                options += `<option value="${escapeHtml(user.username)}">${escapeHtml(user.username)} (uid:${user.uid})</option>`;
            }
        });
    }
    
    $('#localUid').html(options);
    $('#localGid').html(options);
    $('#raidUid').html(options);
    $('#raidGid').html(options);
    $('#lvmUid').html(options);
    $('#lvmGid').html(options);
    $('#cifsUid').html(options);
    $('#cifsGid').html(options);
}

async function loadAvailableDevices() {
    let result = await apiCall('get_available_devices');
    if (result.success && result.devices) {
        let devices = result.devices;
        let localOpts = '<option value="">Select device...</option>';
        let raidOpts = '<option value="">Select RAID device...</option>';
        let lvmOpts = '<option value="">Select LVM volume...</option>';
        
        devices.forEach(dev => {
            let sizeStr = dev.size ? ` (${dev.size})` : '';
            let fsStr = dev.fstype ? ` [${dev.fstype}]` : '';
            let labelStr = dev.label ? ` ${dev.label}` : '';
            let optionText = `${dev.path}${labelStr}${sizeStr}${fsStr}`;
            
            if (dev.type === 'raid' || dev.type === 'raid_partition') {
                raidOpts += `<option value="${dev.path}">${optionText}</option>`;
            } else if (dev.type === 'lvm') {
                lvmOpts += `<option value="${dev.path}">${dev.name} (${dev.vg_name})${sizeStr}${fsStr}</option>`;
            } else {
                localOpts += `<option value="${dev.path}">${optionText}</option>`;
            }
        });
        
        $('#localDeviceSelect').html(localOpts);
        $('#raidDeviceSelect').html(raidOpts);
        $('#lvmDeviceSelect').html(lvmOpts);
    }
}

// ==================== ОТОБРАЖЕНИЕ ====================
function filterMounts() {
    searchTerm = $('#searchInput').val().toLowerCase();
    fstabOnly = $('#showFstabOnly').prop('checked');
    renderMounts();
}

function setFilter(type) {
    currentFilter = type;
    $('.filter-tab').removeClass('active');
    $(`.filter-tab[data-type="${type}"]`).addClass('active');
    renderMounts();
}

function setViewMode(mode) {
    document.cookie = `mount_view_mode=${mode}; path=/; max-age=2592000`;
    $('#tableView').css('display', mode === 'table' ? 'block' : 'none');
    $('#gridView').css('display', mode === 'grid' ? 'block' : 'none');
    if (mode === 'table') {
        $('#viewTableBtn').addClass('active');
        $('#viewGridBtn').removeClass('active');
    } else {
        $('#viewGridBtn').addClass('active');
        $('#viewTableBtn').removeClass('active');
    }
}

function renderMounts() {
    let filtered = mountedData.filter(mount => {
        if (currentFilter === 'local' && mount.is_network) return false;
        if (currentFilter === 'network' && !mount.is_network) return false;
        
        if (searchTerm) {
            let searchStr = `${mount.device} ${mount.mount_point} ${mount.fstype}`.toLowerCase();
            if (!searchStr.includes(searchTerm)) return false;
        }
        
        if (fstabOnly && !mount.in_fstab) return false;
        
        return true;
    });
    
    // Таблица
    let tableHtml = '';
    filtered.forEach(m => {
        let typeIcon = m.is_network ? (m.network_type === 'cifs' ? '<i class="fab fa-windows"></i>' : '<i class="fab fa-linux"></i>') : '<i class="fas fa-hdd"></i>';
        let typeText = m.is_network ? (m.network_type === 'cifs' ? 'CIFS' : 'NFS') : 'Local';
        let fstabBadge = m.in_fstab ? '<span class="badge bg-success">in fstab</span>' : '<span class="badge bg-secondary">manual</span>';
        
        tableHtml += `<tr>
            <td><span class="badge bg-info">${typeIcon} ${typeText}</span></td>
            <td><code class="small">${escapeHtml(m.device)}</code></td>
            <td><strong>${escapeHtml(m.mount_point)}</strong></td>
            <td><span class="badge bg-light text-dark">${escapeHtml(m.fstype)}</span></td>
            <td><small>${escapeHtml(m.options.substring(0, 40))}${m.options.length > 40 ? '...' : ''}</small></td>
            <td>${fstabBadge}</td>
            <td>
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-warning" onclick="editMountPoint('${escapeHtml(m.mount_point)}')" title="Change mount point"><i class="fas fa-edit"></i></button>
                    ${!m.in_fstab ? `<button class="btn btn-outline-success" onclick="addToFstab('${escapeHtml(m.mount_point)}')" title="Add to fstab"><i class="fas fa-bookmark"></i></button>` : `<button class="btn btn-outline-danger" onclick="removeFromFstab('${escapeHtml(m.mount_point)}')" title="Remove from fstab"><i class="fas fa-trash-alt"></i></button>`}
                    <button class="btn btn-outline-danger" onclick="unmount('${escapeHtml(m.mount_point)}')" title="Unmount"><i class="fas fa-eject"></i></button>
                </div>
            </td>
        </tr>`;
    });
    
    if (filtered.length === 0) {
        tableHtml = `<tr><td colspan="7" class="text-center text-muted py-4"><i class="fas fa-info-circle"></i> No mounts found</td></tr>`;
    }
    $('#tableMountsContainer').html(tableHtml);
    
    // Сетка
    let gridHtml = '';
    filtered.forEach(m => {
        let typeIcon = m.is_network ? (m.network_type === 'cifs' ? '<i class="fab fa-windows"></i>' : '<i class="fab fa-linux"></i>') : '<i class="fas fa-hdd"></i>';
        let fstabBadge = m.in_fstab ? '<span class="badge bg-success fstab-badge">fstab</span>' : '';
        
        gridHtml += `<div class="col-md-4 col-lg-3 mb-3">
            <div class="mount-card position-relative">
                ${fstabBadge}
                <div class="mount-icon">${typeIcon}</div>
                <div class="mount-device"><code>${escapeHtml(m.device)}</code></div>
                <div class="mount-info"><i class="fas fa-folder-open"></i> ${escapeHtml(m.mount_point)}</div>
                <div class="mount-info"><i class="fas fa-file-code"></i> ${escapeHtml(m.fstype)}</div>
                <div class="mount-info"><i class="fas fa-cog"></i> <small>${escapeHtml(m.options.substring(0, 30))}${m.options.length > 30 ? '...' : ''}</small></div>
                <div class="mt-2">
                    <div class="btn-group btn-group-sm w-100">
                        <button class="btn btn-outline-warning" onclick="editMountPoint('${escapeHtml(m.mount_point)}')"><i class="fas fa-edit"></i></button>
                        ${!m.in_fstab ? `<button class="btn btn-outline-success" onclick="addToFstab('${escapeHtml(m.mount_point)}')"><i class="fas fa-bookmark"></i></button>` : `<button class="btn btn-outline-danger" onclick="removeFromFstab('${escapeHtml(m.mount_point)}')"><i class="fas fa-trash-alt"></i></button>`}
                        <button class="btn btn-outline-danger" onclick="unmount('${escapeHtml(m.mount_point)}')"><i class="fas fa-eject"></i></button>
                    </div>
                </div>
            </div>
        </div>`;
    });
    
    if (filtered.length === 0) {
        gridHtml = `<div class="col-12 text-center text-muted py-4"><i class="fas fa-info-circle"></i> No mounts found</div>`;
    }
    $('#gridMountsContainer').html(gridHtml);
}

// ==================== ДЕЙСТВИЯ С МОНТИРОВАНИЕМ ====================
async function unmount(mountPoint) {
    if (!confirm(`Unmount ${mountPoint}?`)) return;
    
    let result = await apiCall('unmount', 'POST', {
        mount_point: mountPoint,
        force: false,
        remove_from_fstab: false
    });
    
    if (result.success) {
        showAlert(`Unmounted ${mountPoint}`, 'success');
        await loadAllData();
    } else {
        if (confirm(`Normal unmount failed. Force unmount? Error: ${result.error}`)) {
            let forceResult = await apiCall('unmount', 'POST', {
                mount_point: mountPoint,
                force: true,
                remove_from_fstab: false
            });
            if (forceResult.success) {
                showAlert(`Force unmounted ${mountPoint}`, 'success');
                await loadAllData();
            } else {
                showAlert(`Failed to unmount: ${forceResult.error}`, 'danger');
            }
        } else {
            showAlert(`Failed to unmount: ${result.error}`, 'danger');
        }
    }
}

async function addToFstab(mountPoint) {
    let mount = mountedData.find(m => m.mount_point === mountPoint);
    if (!mount) {
        showAlert('Mount not found', 'danger');
        return;
    }
    
    let result = await apiCall('add_to_fstab', 'POST', {
        device: mount.device_path || mount.device,
        mount_point: mount.mount_point,
        fstype: mount.fstype,
        options: mount.options
    });
    
    if (result.success) {
        showAlert(`Added to /etc/fstab`, 'success');
        await loadAllData();
    } else {
        showAlert(`Failed: ${result.error}`, 'danger');
    }
}

async function removeFromFstab(mountPoint) {
    if (!confirm(`Remove ${mountPoint} from /etc/fstab?`)) return;
    
    let result = await apiCall('remove_from_fstab', 'POST', {
        mount_point: mountPoint
    });
    
    if (result.success) {
        showAlert(`Removed from /etc/fstab`, 'success');
        await loadAllData();
    } else {
        showAlert(`Failed: ${result.error}`, 'danger');
    }
}

async function editMountPoint(oldMountPoint) {
    $('#editOldMountPoint').val(oldMountPoint);
    $('#editCurrentMountPoint').val(oldMountPoint);
    $('#editNewMountPoint').val(oldMountPoint);
    $('#editUpdateFstab').prop('checked', true);
    $('#editMountPointModal').modal('show');
}

$('#editMountPointForm').on('submit', async function(e) {
    e.preventDefault();
    let data = {
        old_mount_point: $('#editOldMountPoint').val(),
        new_mount_point: $('#editNewMountPoint').val(),
        update_fstab: $('#editUpdateFstab').prop('checked')
    };
    
    let result = await apiCall('change_mount_point', 'POST', data);
    if (result.success) {
        showAlert(result.message, 'success');
        $('#editMountPointModal').modal('hide');
        await loadAllData();
    } else {
        showAlert(`Failed: ${result.error}`, 'danger');
    }
});

// ==================== ФОРМЫ МОНТИРОВАНИЯ ====================
$('#mountLocalForm').on('submit', async function(e) {
    e.preventDefault();
    let data = $(this).serializeArray();
    let postData = {};
    data.forEach(item => { postData[item.name] = item.value; });
    postData.action = 'mount_local';
    if (postData.add_to_fstab === 'on') postData.add_to_fstab = true;
    
    let result = await apiCall('mount_local', 'POST', postData);
    if (result.success) {
        showAlert(`Mounted to ${result.mount_point}`, 'success');
        $('#mountLocalModal').modal('hide');
        $('#mountLocalForm')[0].reset();
        await loadAllData();
        await loadAvailableDevices();
    } else {
        showAlert(`Mount failed: ${result.error}`, 'danger');
    }
});

$('#mountRaidForm').on('submit', async function(e) {
    e.preventDefault();
    let data = $(this).serializeArray();
    let postData = {};
    data.forEach(item => { postData[item.name] = item.value; });
    postData.action = 'mount_local';
    if (postData.add_to_fstab === 'on') postData.add_to_fstab = true;
    
    let result = await apiCall('mount_local', 'POST', postData);
    if (result.success) {
        showAlert(`RAID device mounted to ${result.mount_point}`, 'success');
        $('#mountRaidModal').modal('hide');
        $('#mountRaidForm')[0].reset();
        await loadAllData();
        await loadAvailableDevices();
    } else {
        showAlert(`Mount failed: ${result.error}`, 'danger');
    }
});

$('#mountLvmForm').on('submit', async function(e) {
    e.preventDefault();
    let data = $(this).serializeArray();
    let postData = {};
    data.forEach(item => { postData[item.name] = item.value; });
    postData.action = 'mount_local';
    if (postData.add_to_fstab === 'on') postData.add_to_fstab = true;
    
    let result = await apiCall('mount_local', 'POST', postData);
    if (result.success) {
        showAlert(`LVM volume mounted to ${result.mount_point}`, 'success');
        $('#mountLvmModal').modal('hide');
        $('#mountLvmForm')[0].reset();
        await loadAllData();
        await loadAvailableDevices();
    } else {
        showAlert(`Mount failed: ${result.error}`, 'danger');
    }
});

$('#mountCifsForm').on('submit', async function(e) {
    e.preventDefault();
    let data = $(this).serializeArray();
    let postData = {};
    data.forEach(item => { postData[item.name] = item.value; });
    postData.action = 'mount_cifs';
    if (postData.add_to_fstab === 'on') postData.add_to_fstab = true;
    
    let result = await apiCall('mount_cifs', 'POST', postData);
    if (result.success) {
        showAlert(`CIFS share mounted to ${result.mount_point}`, 'success');
        $('#mountCifsModal').modal('hide');
        $('#mountCifsForm')[0].reset();
        await loadAllData();
    } else {
        showAlert(`Mount failed: ${result.error}`, 'danger');
    }
});

$('#mountNfsForm').on('submit', async function(e) {
    e.preventDefault();
    let data = $(this).serializeArray();
    let postData = {};
    data.forEach(item => { 
        if (item.name === 'intr' || item.name === 'noatime') {
            postData[item.name] = true;
        } else {
            postData[item.name] = item.value;
        }
    });
    postData.action = 'mount_nfs';
    if (postData.add_to_fstab === 'on') postData.add_to_fstab = true;
    
    let result = await apiCall('mount_nfs', 'POST', postData);
    if (result.success) {
        showAlert(`NFS share mounted to ${result.mount_point}`, 'success');
        $('#mountNfsModal').modal('hide');
        $('#mountNfsForm')[0].reset();
        await loadAllData();
    } else {
        showAlert(`Mount failed: ${result.error}`, 'danger');
    }
});

// ==================== БРАУЗЕР ПАПОК ====================
async function loadFolder(path, containerId, breadcrumbId, currentPathInputId) {
    $(`#${containerId}`).html('<div class="text-center p-4"><div class="spinner-border text-primary"></div><br>Loading...</div>');
    
    if (!path || path === '') path = '/';
    
    let result = await apiCall('browse', 'GET', { path: path });
    
    if (result.success) {
        currentBrowsePath = result.path;
        $(`#${currentPathInputId}`).val(result.path);
        
        let parts = result.path.split('/').filter(p => p && p !== '');
        let breadcrumbHtml = `<li class="breadcrumb-item"><a href="#" onclick="loadFolder('/', '${containerId}', '${breadcrumbId}', '${currentPathInputId}'); return false;"><i class="fas fa-home"></i> /</a></li>`;
        let cp = '';
        for (let p of parts) {
            cp += '/' + p;
            breadcrumbHtml += `<li class="breadcrumb-item"><a href="#" onclick="loadFolder('${cp.replace(/'/g, "\\'")}', '${containerId}', '${breadcrumbId}', '${currentPathInputId}'); return false;">${escapeHtml(p)}</a></li>`;
        }
        $(`#${breadcrumbId}`).html(breadcrumbHtml);
        
        let listHtml = '<div class="list-group">';
        if (result.path !== '/') {
            let parentPath = result.parent;
            listHtml += `<div class="list-group-item list-group-item-action folder-item" style="cursor: pointer;" onclick="loadFolder('${parentPath.replace(/'/g, "\\'")}', '${containerId}', '${breadcrumbId}', '${currentPathInputId}')">
                <i class="fas fa-level-up-alt"></i> .. (Parent directory)
            </div>`;
        }
        
        if (result.items && result.items.length > 0) {
            for (let item of result.items) {
                listHtml += `<div class="list-group-item list-group-item-action folder-item" style="cursor: pointer;" onclick="loadFolder('${item.path.replace(/'/g, "\\'")}', '${containerId}', '${breadcrumbId}', '${currentPathInputId}')">
                    <i class="fas fa-folder ${item.readable ? 'text-warning' : 'text-muted'}"></i> ${escapeHtml(item.name)} ${!item.readable ? '<span class="badge bg-secondary">no access</span>' : ''}
                </div>`;
            }
        } else {
            listHtml += '<div class="list-group-item text-muted"><i class="fas fa-folder-open"></i> No subfolders found</div>';
        }
        
        listHtml += '</div>';
        $(`#${containerId}`).html(listHtml);
    } else {
        $(`#${containerId}`).html(`<div class="alert alert-danger">Error: ${escapeHtml(result.error)}</div>`);
        showAlert('Error loading folders: ' + result.error, 'danger');
    }
}

function selectDirectory(path, containerId, breadcrumbId, currentPathInputId) {
    loadFolder(path, containerId, breadcrumbId, currentPathInputId);
}

function selectCurrentFolder() {
    if (currentBrowseTarget) {
        let targetInput = document.getElementById(currentBrowseTarget);
        if (targetInput) {
            targetInput.value = currentBrowsePath;
        } else {
            $(`#${currentBrowseTarget}`).val(currentBrowsePath);
        }
        $('#browseFolderModal').modal('hide');
        showAlert('Folder selected: ' + currentBrowsePath, 'success');
    }
}

function openFolderBrowser(targetInputId) {
    currentBrowseTarget = targetInputId;
    let startPath = $(`#${targetInputId}`).val() || '/';
    if (!startPath || startPath === '') startPath = '/';
    
    $('#folderBreadcrumb').empty();
    $('#currentPath').val('');
    $('#folderBrowser').html('<div class="text-center p-4"><div class="spinner-border text-primary"></div><br>Loading...</div>');
    
    $('#browseFolderModal').modal('show');
    
    setTimeout(() => {
        loadFolder(startPath, 'folderBrowser', 'folderBreadcrumb', 'currentPath');
    }, 200);
}

function openFolderBrowserEdit() {
    let startPath = $('#editNewMountPoint').val() || '/';
    if (!startPath || startPath === '') startPath = '/';
    
    $('#folderBreadcrumb').html('');
    $('#currentPath').val('');
    $('#folderBrowser').html('<div class="text-center p-4"><div class="spinner-border text-primary"></div><br>Loading...</div>');
    
    $('#browseFolderModal').modal('show');
    
    setTimeout(() => {
        loadFolder(startPath, 'folderBrowser', 'folderBreadcrumb', 'currentPath');
    }, 100);
    
    currentBrowseTarget = 'editNewMountPoint';
}

function showCreateFolderDialog() {
    $('#createFolderPath').text(currentBrowsePath);
    $('#newFolderName').val('');
    $('#createFolderDialog').modal('show');
}

async function createNewFolder() {
    let path = $('#createFolderPath').text();
    let name = $('#newFolderName').val();
    if (!name) { 
        showAlert('Enter folder name', 'danger'); 
        return; 
    }
    
    let result = await apiCall('create_folder', 'POST', { path: path, name: name });
    if (result.success) {
        $('#createFolderDialog').modal('hide');
        loadFolder(currentBrowsePath, 'folderBrowser', 'folderBreadcrumb', 'currentPath');
        showAlert('Folder "' + name + '" created', 'success');
    } else {
        showAlert(result.error, 'danger');
    }
}

// ==================== ЛОГИ ====================
async function loadLogs() {
    let result = await apiCall('get_logs', 'GET', { lines: 100 });
    if (result.success && result.logs) {
        let html = '';
        result.logs.forEach(log => {
            html += `<div class="log-line" style="padding: 2px 0; border-bottom: 1px solid #333;">${escapeHtml(log)}</div>`;
        });
        $('#logsContainer').html(html || '<div class="text-muted text-center py-3">No logs</div>');
    }
}

async function clearLogs() {
    let result = await apiCall('clear_logs');
    if (result.success) {
        showAlert('Logs cleared', 'success');
        await loadLogs();
    }
}

function toggleLogs() {
    $('#logsContent').slideToggle();
    $('#logsToggleIcon').toggleClass('fa-chevron-down fa-chevron-up');
    $('#logsCard').slideDown();
}

// ==================== ОБНОВЛЕНИЕ ====================
async function refreshAllData() {
    await loadAllData();
    await loadAvailableDevices();
    await loadLogs();
    showAlert('Data refreshed', 'success');
}

// ==================== ИНИЦИАЛИЗАЦИЯ ====================
$(document).ready(function() {
    refreshAllData();
    loadSystemUsers();
    setTimeout(function() { $('#applePreloader').fadeOut(500); }, 500);
});
</script>
<script src="js/loader.js"></script>
</body>
</html>