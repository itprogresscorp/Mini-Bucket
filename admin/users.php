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

$db = getDB();

$current_host_id = $_SESSION['current_host_id'] ?? 1;

// Получаем API ключ и URL для текущего хоста
$stmt = $db->prepare("SELECT idHost, hostName, hostApiKey, hostProto, hostIp, hostPort, hostApiPath FROM hosts WHERE idHost = :id");
$stmt->bindValue(':id', $current_host_id, SQLITE3_INTEGER);
$result = $stmt->execute();
$host = $result->fetchArray(SQLITE3_ASSOC);

if ($host) {
    $api_key = $host['hostApiKey'];
    $host_name = $host['hostName'];
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
    $host_name = 'Localhost';
}

$stmt = $db->prepare("SELECT idHost, hostName FROM hosts ORDER BY idHost");
$hostsResult = $stmt->execute();
$hosts = [];
while ($row = $hostsResult->fetchArray(SQLITE3_ASSOC)) {
    $hosts[] = $row;
}

$js_config = [
    'apiBaseUrl' => $api_base_url,
    'apiKey' => $api_key,
    'isLocalhost' => ($current_host_id == 1),
    'currentHostId' => $current_host_id,
    'currentHostName' => $host_name,
    'currentUser' => $_SESSION['username'] ?? 'admin'
];

$viewMode = $_COOKIE['user_view_mode'] ?? 'table';

if (!isset($_SESSION['groups_collapsed'])) {
    $_SESSION['groups_collapsed'] = true;
}

$menu = require_once 'menu.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Users — Mini-B</title>
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
	window.hostsList = <?php echo json_encode($hosts); ?>;
	window.currentHostId = <?php echo (int)$current_host_id; ?>;
	</script>
    
    <style>
        * { box-sizing: border-box; }
        body { background: #f5f5f7; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; }
        
        
        .host-selector select {
            background: rgba(255,255,255,0.9);
            border: 1px solid #ddd;
            border-radius: 20px;
            padding: 6px 30px 6px 15px;
            font-size: 14px;
            cursor: pointer;
        }
        
        .app-container {
            display: flex;
            min-height: 100vh;
        }
        
        .main-content {
            flex: 1;
            padding: 5px 15px;
            margin-left: 280px;
            transition: all 0.3s ease;
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px 15px;
            }
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
            overflow: visible;
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
        
        .collapse-header {
            cursor: pointer;
            user-select: none;
        }
        
        .collapse-header:hover { opacity: 0.8; }
        
        .table th { 
            background: #fafafc; 
            font-weight: 500; 
            font-size: 13px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .table td { font-size: 14px; vertical-align: middle; }
        
        .user-card {
            border: 1px solid #f0f0f0;
            border-radius: 16px;
            padding: 14px;
            transition: all 0.2s;
            background: white;
            height: 100%;
        }
        
        .user-card:hover { 
            box-shadow: 0 4px 12px rgba(0,0,0,0.05); 
            transform: translateY(-2px); 
        }
        
        .user-card.active-user { 
            border-left: 3px solid #34c759; 
        }
        
        .user-avatar {
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
        
        .user-name { font-weight: 600; font-size: 15px; margin-bottom: 4px; }
        .user-info { font-size: 11px; color: #6c757d; margin-bottom: 2px; }
        
        .groups-checkboxes {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #e5e5ea;
            border-radius: 12px;
            padding: 12px;
            background: #f9f9fb;
        }
        
        .password-wrapper {
            position: relative;
        }
        
        .password-wrapper input {
            padding-right: 80px;
        }
        
        .password-toggle, .generate-password {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            z-index: 10;
        }
        
        .password-toggle {
            right: 10px;
            color: #6c757d;
        }
        
        .generate-password {
            right: 40px;
            color: #007aff;
            font-size: 14px;
        }
        
        .password-strength {
            margin-top: 8px;
            font-size: 12px;
        }
        
        .strength-weak { color: #ff3b30; }
        .strength-medium { color: #ff9500; }
        .strength-strong { color: #34c759; }
        
        .alert {
            border-radius: 14px;
            font-size: 14px;
            margin-bottom: 16px;
        }
        
        .modal-content {
            border-radius: 24px;
            border: none;
            overflow: hidden;
        }
        
        .modal-header {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            color: white;
            border-bottom: none;
            padding: 20px 24px;
        }
        
        .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }
        
        .refresh-btn {
            cursor: pointer;
            transition: transform 0.2s;
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
        <div class="stats-group" id="statsGroup">
            <div class="stat-badge"><span class="number" id="statPanelUsers">-</span><span class="label"><?php echo $lang2726; ?></span></div>
            <div class="stat-badge"><span class="number" id="statSystemUsers">-</span><span class="label"><?php echo $lang2727; ?></span></div>
            <div class="stat-badge"><span class="number" id="statGroups">-</span><span class="label"><?php echo $lang2728; ?></span></div>
        </div>
        <div class="host-selector">
            <select id="hostSelector">
                <option value=""><?php echo $lang12; ?></option>
            </select>
        </div>
        <div class="dropdown">
            <button class="btn btn-primary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                <i class="fas fa-plus"></i> <?php echo $lang2729; ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#addPanelUserModal"><i class="fas fa-database me-2"></i> <?php echo $lang2730; ?></a></li>
                <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#addSystemUserModal"><i class="fab fa-linux me-2"></i> <?php echo $lang2731; ?></a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#addGroupModal"><i class="fas fa-users me-2"></i> <?php echo $lang2732; ?></a></li>
            </ul>
        </div>
        <i class="fas fa-sync-alt refresh-btn text-muted" onclick="refreshAllData()" title="<?php echo $lang2733; ?>"></i>
    </div>
</div>

<div class="app-container">
    <?php echo $menu; ?>
    
    <main class="main-content">
        <div id="alertContainer"></div>

        <!-- FILTERS -->
        <div class="filters-bar">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" class="form-control" placeholder="Search..." onkeyup="filterUsers()">
            </div>
            <div class="filter-tabs">
                <span class="filter-tab active" data-type="all" onclick="setFilter('all')"><?php echo $lang2734; ?></span>
                <span class="filter-tab" data-type="panel" onclick="setFilter('panel')"><i class="fas fa-database"></i> <?php echo $lang2735; ?></span>
                <span class="filter-tab" data-type="system" onclick="setFilter('system')"><i class="fab fa-linux"></i> <?php echo $lang2736; ?></span>
            </div>
            <div class="form-check form-switch ms-auto">
                <input class="form-check-input" type="checkbox" id="showActiveOnly" onchange="filterUsers()">
                <label class="form-check-label" for="showActiveOnly"><?php echo $lang2737; ?></label>
            </div>
            <div class="view-toggle">
                <button class="<?= $viewMode == 'table' ? 'active' : '' ?>" onclick="setViewMode('table')"><i class="fas fa-table"></i></button>
                <button class="<?= $viewMode == 'grid' ? 'active' : '' ?>" onclick="setViewMode('grid')"><i class="fas fa-th-large"></i></button>
            </div>
        </div>

        <!-- USERS CARD -->
        <div class="card">
            <div class="card-header">
                <span><i class="fas fa-user me-2"></i><?php echo $lang2738; ?></span>
            </div>
            
            <div id="tableView" class="table-responsive" style="display: <?= $viewMode == 'table' ? 'block' : 'none' ?>;">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr><th><?php echo $lang2739; ?></th><th><?php echo $lang2740; ?></th><th><?php echo $lang2741; ?></th><th><?php echo $lang2742; ?></th><th><?php echo $lang2743; ?></th><th><?php echo $lang2744; ?></th><th><?php echo $lang2745; ?></th></tr>
                    </thead>
                    <tbody id="tableUsersContainer">
                        <tr><td colspan="7" class="text-center py-4"><div class="loading-spinner-sm"></div> <?php echo $lang2746; ?></td></tr>
                    </tbody>
                </table>
            </div>
            
            <div id="gridView" class="p-3" style="display: <?= $viewMode == 'grid' ? 'block' : 'none' ?>;">
                <div class="row" id="gridUsersContainer">
                    <div class="col-12 text-center py-4"><div class="loading-spinner-sm"></div> <?php echo $lang2747; ?></div>
                </div>
            </div>
        </div>

        <!-- GROUPS CARD -->
        <div class="card">
            <div class="card-header collapse-header" onclick="toggleGroups()">
                <span><i class="fas fa-users me-2"></i><?php echo $lang2748; ?> <span class="badge bg-secondary ms-2" id="groupsCount">0</span></span>
                <i class="fas fa-chevron-down" id="groupsToggleIcon"></i>
            </div>
            <div id="groupsContent" style="display: none;">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr><th><?php echo $lang2749; ?></th><th><?php echo $lang2750; ?></th><th><?php echo $lang2751; ?></th><th><?php echo $lang2752; ?></th></tr>
                        </thead>
                        <tbody id="groupsTableBody">
                            <tr><td colspan="4" class="text-center py-4"><div class="loading-spinner-sm"></div> <?php echo $lang2753; ?></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- MODALS -->
<!-- Add Panel User Modal -->
<div class="modal fade" id="addPanelUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="addPanelUserForm">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i><?php echo $lang2754; ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label><?php echo $lang2755; ?> *</label>
                        <input type="text" name="username" id="add_panel_username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label><?php echo $lang2756; ?> *</label>
                        <div class="password-wrapper">
                            <input type="password" name="password" id="add_panel_password" class="form-control" required>
                            <button type="button" class="generate-password" onclick="generatePassword('add_panel_password')"><i class="fas fa-dice-d6"></i></button>
                            <button type="button" class="password-toggle" onclick="togglePassword('add_panel_password')"><i class="fas fa-eye"></i></button>
                        </div>
                        <div class="password-strength" id="addPanelStrength"></div>
                    </div>
                    <div class="mb-3">
                        <label><?php echo $lang2757; ?> *</label>
                        <div class="password-wrapper">
                            <input type="password" id="add_panel_confirm" class="form-control" required>
                            <button type="button" class="password-toggle" onclick="togglePassword('add_panel_confirm')"><i class="fas fa-eye"></i></button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label><?php echo $lang2758; ?></label>
                        <input type="email" name="email" id="add_panel_email" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label><?php echo $lang2759; ?></label>
                        <select name="role" class="form-select">
                            <option value="user"><?php echo $lang2760; ?></option>
                            <option value="admin"><?php echo $lang2761; ?></option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang2762; ?></button>
                    <button type="submit" class="btn btn-primary"><?php echo $lang2763; ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Panel User Modal -->
<div class="modal fade" id="editPanelUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="editPanelUserForm">
                <input type="hidden" name="user_id" id="edit_panel_id">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i><?php echo $lang2764; ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label><?php echo $lang2765; ?></label>
                        <input type="text" name="username" id="edit_panel_username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label><?php echo $lang2766; ?></label>
                        <input type="email" name="email" id="edit_panel_email" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label><?php echo $lang2767; ?></label>
                        <select name="role" id="edit_panel_role" class="form-select">
                            <option value="user"><?php echo $lang2768; ?></option>
                            <option value="admin"><?php echo $lang2769; ?></option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang2770; ?></button>
                    <button type="submit" class="btn btn-primary"><?php echo $lang2771; ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Change Panel Password Modal -->
<div class="modal fade" id="changePanelPassModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="changePanelPassForm">
                <input type="hidden" name="user_id" id="pass_panel_id">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-key me-2"></i><?php echo $lang2772; ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p><?php echo $lang2773; ?> <strong id="pass_panel_username"></strong></p>
                    <div class="mb-3">
                        <label><?php echo $lang2774; ?></label>
                        <div class="password-wrapper">
                            <input type="password" name="new_password" id="change_panel_password" class="form-control" required>
                            <button type="button" class="generate-password" onclick="generatePassword('change_panel_password')"><i class="fas fa-dice-d6"></i></button>
                            <button type="button" class="password-toggle" onclick="togglePassword('change_panel_password')"><i class="fas fa-eye"></i></button>
                        </div>
                        <div class="password-strength" id="changePanelStrength"></div>
                    </div>
                    <div class="mb-3">
                        <label><?php echo $lang2775; ?></label>
                        <div class="password-wrapper">
                            <input type="password" id="change_panel_confirm" class="form-control" required>
                            <button type="button" class="password-toggle" onclick="togglePassword('change_panel_confirm')"><i class="fas fa-eye"></i></button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang2776; ?></button>
                    <button type="submit" class="btn btn-warning"><?php echo $lang2777; ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add System User Modal -->
<div class="modal fade" id="addSystemUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="addSystemUserForm">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fab fa-linux me-2"></i><?php echo $lang2778; ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label><?php echo $lang2779; ?> *</label>
                        <input type="text" name="username" id="add_sys_username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label><?php echo $lang2780; ?> *</label>
                        <div class="password-wrapper">
                            <input type="password" name="password" id="add_sys_password" class="form-control" required>
                            <button type="button" class="generate-password" onclick="generatePassword('add_sys_password')"><i class="fas fa-dice-d6"></i></button>
                            <button type="button" class="password-toggle" onclick="togglePassword('add_sys_password')"><i class="fas fa-eye"></i></button>
                        </div>
                        <div class="password-strength" id="addSysStrength"></div>
                    </div>
                    <div class="mb-3">
                        <label><?php echo $lang2781; ?> *</label>
                        <div class="password-wrapper">
                            <input type="password" id="add_sys_confirm" class="form-control" required>
                            <button type="button" class="password-toggle" onclick="togglePassword('add_sys_confirm')"><i class="fas fa-eye"></i></button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label><?php echo $lang2782; ?></label>
                        <div class="groups-checkboxes" id="addSysGroupsContainer"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang2783; ?></button>
                    <button type="submit" class="btn btn-primary"><?php echo $lang2784; ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit System User Groups Modal -->
<div class="modal fade" id="editSystemUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="editSystemUserForm">
                <input type="hidden" name="username" id="edit_sys_username">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-users-cog me-2"></i><?php echo $lang2785; ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p><?php echo $lang2786; ?> <strong id="edit_sys_username_label"></strong></p>
                    <div class="mb-3">
                        <div class="groups-checkboxes" id="edit_groups_container"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang2787; ?></button>
                    <button type="submit" class="btn btn-primary"><?php echo $lang2788; ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Change System Password Modal -->
<div class="modal fade" id="changeSystemPassModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="changeSystemPassForm">
                <input type="hidden" name="username" id="change_sys_username">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-key me-2"></i><?php echo $lang2789; ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p><?php echo $lang2790; ?> <strong id="change_sys_username_label"></strong></p>
                    <div class="mb-3">
                        <label><?php echo $lang2791; ?></label>
                        <div class="password-wrapper">
                            <input type="password" name="password" id="change_sys_password" class="form-control" required>
                            <button type="button" class="generate-password" onclick="generatePassword('change_sys_password')"><i class="fas fa-dice-d6"></i></button>
                            <button type="button" class="password-toggle" onclick="togglePassword('change_sys_password')"><i class="fas fa-eye"></i></button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label><?php echo $lang2792; ?></label>
                        <div class="password-wrapper">
                            <input type="password" id="change_sys_confirm" class="form-control" required>
                            <button type="button" class="password-toggle" onclick="togglePassword('change_sys_confirm')"><i class="fas fa-eye"></i></button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang2793; ?></button>
                    <button type="submit" class="btn btn-warning"><?php echo $lang2794; ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Set SMB Password Modal -->
<div class="modal fade" id="setSmbPassModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="setSmbPassForm">
                <input type="hidden" name="username" id="smb_username">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-network-wired me-2"></i><?php echo $lang2795; ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p><?php echo $lang2796; ?> <strong id="smb_username_label"></strong></p>
                    <div class="mb-3">
                        <label><?php echo $lang2797; ?></label>
                        <div class="password-wrapper">
                            <input type="password" name="password" id="smb_password" class="form-control" required>
                            <button type="button" class="password-toggle" onclick="togglePassword('smb_password')"><i class="fas fa-eye"></i></button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang2798; ?></button>
                    <button type="submit" class="btn btn-info"><?php echo $lang2799; ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Group Modal -->
<div class="modal fade" id="addGroupModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="addGroupForm">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus me-2"></i><?php echo $lang2800; ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label><?php echo $lang2801; ?></label>
                        <input type="text" name="groupname" id="add_group_name" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang2802; ?></button>
                    <button type="submit" class="btn btn-primary"><?php echo $lang2803; ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Rename Group Modal -->
<div class="modal fade" id="renameGroupModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="renameGroupForm">
                <input type="hidden" name="oldname" id="rename_old_groupname">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i><?php echo $lang2804; ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p><?php echo $lang2805; ?> <strong id="rename_current_name"></strong></p>
                    <div class="mb-3">
                        <label><?php echo $lang2806; ?></label>
                        <input type="text" name="newname" id="rename_newname" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang2807; ?></button>
                    <button type="submit" class="btn btn-primary"><?php echo $lang2808; ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="lib/bootstrap-5.3.8-dist/js/bootstrap.bundle.min.js"></script>
<script src="lib/jquery-3.6.0-master/dist/jquery.min.js"></script>
<script src="js/loader.js"></script>

<script>
// ========== ГЛОБАЛЬНЫЕ ПЕРЕМЕННЫЕ ==========
let panelUsers = [];
let systemUsers = [];
let allGroups = [];
let currentFilter = 'all';
let searchTerm = '';
let activeOnly = false;
let groupsCollapsed = true;

// ========== УТИЛИТЫ ==========
function showAlert(message, type = 'success') {
    const alertHtml = `<div class="alert alert-${type} alert-dismissible fade show" role="alert">
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i> 
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>`;
    $('#alertContainer').append(alertHtml);
    setTimeout(() => $('.alert').fadeOut(500, function() { $(this).remove(); }), 5000);
}

function escapeHtml(str) { 
    if (!str) return ''; 
    return str.replace(/[&<>]/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;'})[m]); 
}

// ========== API CALLS ==========
async function apiCall(action, method = 'GET', data = null) {
    let fullUrl = `${window.apiConfig.apiBaseUrl}users_api.php?action=${action}`;
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

// ========== HOST SELECTOR ==========
function initHostSelector() {
    const selector = $('#hostSelector');
    selector.empty();
    
    if (window.hostsList && window.hostsList.length > 0) {
        window.hostsList.forEach(host => {
            const selected = (host.idHost == window.currentHostId) ? 'selected' : '';
            selector.append(`<option value="${host.idHost}" ${selected}>${escapeHtml(host.hostName)}</option>`);
        });
    } else {
        selector.append('<option value="1">Localhost</option>');
    }
    
    selector.on('change', function() {
        const newHostId = $(this).val();
        if (newHostId != window.currentHostId) {
            showAlert('Switching host...', 'info');
            $('#applePreloader').fadeIn(200);
            
            $.ajax({
                url: 'ajax/switch_host.php',
                method: 'POST',
                data: { host_id: newHostId },
                success: function() {
                    window.location.href = 'users.php';
                },
                error: function() {
                    showAlert('<?php echo $lang2809; ?>', 'danger');
                    $('#applePreloader').fadeOut(500);
                    selector.val(window.currentHostId);
                }
            });
        }
    });
}

// ========== ЗАГРУЗКА ДАННЫХ ==========
async function loadAllData() {
    try {
        const result = await apiCall('get_all_data');
        if (result.success) {
            panelUsers = result.data.panel_users;
            systemUsers = result.data.system_users;
            allGroups = result.data.groups;
            
            updateStats(result.data.stats);
            renderUsers();
            renderGroups();
            
            updateGroupCheckboxes();
        } else {
            showAlert('<?php echo $lang2810; ?>', 'danger');
        }
    } catch (error) {
        console.error('Error loading data:', error);
        showAlert('<?php echo $lang2811; ?>', 'danger');
    }
}

async function loadStats() {
    const result = await apiCall('get_stats');
    if (result.success) {
        updateStats(result.data);
    }
}

function updateStats(stats) {
    $('#statPanelUsers').text(stats.panel_users);
    $('#statSystemUsers').text(stats.system_users);
    $('#statGroups').text(stats.system_groups);
    $('#groupsCount').text(stats.system_groups);
}

function updateGroupCheckboxes() {
    let html = '';
    allGroups.forEach(group => {
        html += `<div class="form-check">
            <input class="form-check-input" type="checkbox" name="groups[]" value="${escapeHtml(group.name)}" id="group_${escapeHtml(group.name)}">
            <label for="group_${escapeHtml(group.name)}">${escapeHtml(group.name)}</label>
        </div>`;
    });
    $('#addSysGroupsContainer').html(html || '<div class="text-muted"><?php echo $lang2812; ?></div>');
}

// ========== РЕНДЕРИНГ ПОЛЬЗОВАТЕЛЕЙ ==========
function renderUsers() {
    let filtered = [];
    
    if (currentFilter === 'all' || currentFilter === 'panel') {
        panelUsers.forEach(user => filtered.push({ type: 'panel', data: user }));
    }
    if (currentFilter === 'all' || currentFilter === 'system') {
        systemUsers.forEach(user => filtered.push({ type: 'system', data: user }));
    }
    
    if (searchTerm) {
        filtered = filtered.filter(item => 
            item.data.username.toLowerCase().includes(searchTerm.toLowerCase()) ||
            (item.type === 'panel' && item.data.email && item.data.email.toLowerCase().includes(searchTerm.toLowerCase())) ||
            (item.type === 'system' && item.data.home && item.data.home.toLowerCase().includes(searchTerm.toLowerCase()))
        );
    }
    
    if (activeOnly) {
        filtered = filtered.filter(item => item.type !== 'system' || item.data.is_active === true);
    }
    
    renderTableView(filtered);
    renderGridView(filtered);
}

function renderTableView(filtered) {
    const container = $('#tableUsersContainer');
    if (filtered.length === 0) {
        container.html('<tr><td colspan="7" class="text-center text-muted py-4"><?php echo $lang2813; ?></td></tr>');
        return;
    }
    
    let html = '';
    filtered.forEach(item => {
        if (item.type === 'panel') {
            const u = item.data;
            html += `<tr>
                <td><span class="badge bg-info"><i class="fas fa-database me-2"></i><?php echo $lang2814; ?></span></td>
                <td><strong>${escapeHtml(u.username)}</strong></td>
                <td><span class="badge ${u.role === 'admin' ? 'bg-danger' : 'bg-secondary'}">${u.role}</span></td>
                <td>${escapeHtml(u.email || '-')}</td>
                <td>-</td>
                <td>${escapeHtml(u.created_at || '-')}</td>
                <td>${getPanelUserActions(u)}</td>
            </tr>`;
        } else {
            const u = item.data;
            html += `<tr>
                <td><span class="badge bg-success"><i class="fab fa-linux me-2"></i><?php echo $lang2815; ?></span></td>
                <td><strong>${escapeHtml(u.username)}</strong> ${u.is_active ? '<i class="fas fa-circle text-success ms-1" style="font-size: 8px;"></i>' : ''}</td>
                <td>UID: ${u.uid}</td>
                <td><small>${escapeHtml(u.home)}</small></td>
                <td><small>${escapeHtml(u.groups_str.substring(0, 40))}${u.groups_str.length > 40 ? '...' : ''}</small></td>
                <td><small>${escapeHtml(u.last_login)}</small></td>
                <td>${getSystemUserActions(u)}</td>
            </tr>`;
        }
    });
    container.html(html);
}

function renderGridView(filtered) {
    const container = $('#gridUsersContainer');
    if (filtered.length === 0) {
        container.html('<div class="col-12 text-center text-muted py-4"><?php echo $lang2816; ?></div>');
        return;
    }
    
    let html = '';
    filtered.forEach(item => {
        if (item.type === 'panel') {
            const u = item.data;
            html += `<div class="col-md-4 col-lg-3 mb-3">
                <div class="user-card">
                    <div class="user-avatar"><i class="fas fa-user-circle"></i></div>
                    <div class="user-name">${escapeHtml(u.username)}</div>
                    <div class="user-info"><i class="fas fa-envelope"></i> ${escapeHtml(u.email || '-')}</div>
                    <div class="user-info"><i class="fas fa-tag"></i> ${u.role}</div>
                    <div class="mt-2">${getPanelUserActions(u)}</div>
                </div>
            </div>`;
        } else {
            const u = item.data;
            html += `<div class="col-md-4 col-lg-3 mb-3">
                <div class="user-card ${u.is_active ? 'active-user' : ''}">
                    <div class="user-avatar"><i class="fab fa-linux"></i></div>
                    <div class="user-name">${escapeHtml(u.username)} ${u.is_active ? '<i class="fas fa-circle text-success" style="font-size: 10px;"></i>' : ''}</div>
                    <div class="user-info"><i class="fas fa-home"></i> ${escapeHtml(u.home)}</div>
                    <div class="user-info"><i class="fas fa-users"></i> ${escapeHtml(u.groups_str.substring(0, 30))}${u.groups_str.length > 30 ? '...' : ''}</div>
                    <div class="user-info"><i class="fas fa-clock"></i> ${escapeHtml(u.last_login)}</div>
                    <div class="mt-2">${getSystemUserActions(u)}</div>
                </div>
            </div>`;
        }
    });
    container.html(html);
}

function getPanelUserActions(u) {
    return `<div class="dropdown">
        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
            <i class="fas fa-bars"></i>
        </button>
        <ul class="dropdown-menu">
            <li><button class="dropdown-item" onclick='editPanelUser(${JSON.stringify(u).replace(/'/g, "\\'")})'><i class="fas fa-edit me-2"></i><?php echo $lang2817; ?></button></li>
            <li><button class="dropdown-item" onclick='changePanelPass(${JSON.stringify(u).replace(/'/g, "\\'")})'><i class="fas fa-key me-2"></i><?php echo $lang2818; ?></button></li>
            ${u.username !== 'admin' ? `<li><hr class="dropdown-divider"></li><li><button class="dropdown-item text-danger" onclick='deletePanelUser(${u.id})'><i class="fas fa-trash me-2"></i><?php echo $lang2819; ?></button></li>` : ''}
        </ul>
    </div>`;
}

function getSystemUserActions(u) {
    return `<div class="dropdown">
        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
            <i class="fas fa-bars"></i>
        </button>
        <ul class="dropdown-menu">
            <li><button class="dropdown-item" onclick='editSystemUser(${JSON.stringify(u).replace(/'/g, "\\'")})'><i class="fas fa-users-cog me-2"></i><?php echo $lang2820; ?></button></li>
            <li><button class="dropdown-item" onclick='changeSystemPass(${JSON.stringify(u).replace(/'/g, "\\'")})'><i class="fas fa-key me-2"></i><?php echo $lang2821; ?></button></li>
            <li><button class="dropdown-item" onclick='setSmbPass(${JSON.stringify(u).replace(/'/g, "\\'")})'><i class="fas fa-network-wired me-2"></i><?php echo $lang2822; ?></button></li>
            ${u.username !== 'root' && u.username !== window.apiConfig.currentUser ? `<li><hr class="dropdown-divider"></li><li><button class="dropdown-item text-danger" onclick='deleteSystemUser("${escapeHtml(u.username)}")'><i class="fas fa-trash me-2"></i><?php echo $lang2823; ?></button></li>` : ''}
        </ul>
    </div>`;
}

// ========== РЕНДЕРИНГ ГРУПП ==========
function renderGroups() {
    const container = $('#groupsTableBody');
    if (allGroups.length === 0) {
        container.html('<tr><td colspan="4" class="text-center text-muted py-4"><?php echo $lang2824; ?></td></tr>');
        return;
    }
    
    let html = '';
    allGroups.forEach(group => {
        html += `<tr>
            <td><strong>${escapeHtml(group.name)}</strong></td>
            <td>${group.gid}</td>
            <td><small>${escapeHtml(group.members.slice(0, 5).join(', '))}${group.members_count > 5 ? '...' : ''}</small></td>
            <td>
                <button class="btn btn-sm btn-outline-primary" onclick='renameGroup("${escapeHtml(group.name)}")'><i class="fas fa-edit"></i></button>
                ${group.name !== 'sudo' && group.name !== 'root' ? 
                    `<button class="btn btn-sm btn-outline-danger" onclick='deleteGroup("${escapeHtml(group.name)}")'><i class="fas fa-trash"></i></button>` : ''}
            </td>
        </tr>`;
    });
    container.html(html);
}

// ========== PANEL USER ACTIONS ==========
async function addPanelUser(e) {
    e.preventDefault();
    const username = $('#add_panel_username').val();
    const password = $('#add_panel_password').val();
    const confirm = $('#add_panel_confirm').val();
    const email = $('#add_panel_email').val();
    const role = $('select[name="role"]').val();
    
    if (password !== confirm) {
        showAlert('<?php echo $lang2825; ?>', 'danger');
        return;
    }
    
    if (password.length < 4) {
        showAlert('<?php echo $lang2826; ?>', 'danger');
        return;
    }
    
    const result = await apiCall('add_panel_user', 'POST', { username, password, email, role });
    if (result.success) {
        showAlert(result.message, 'success');
        $('#addPanelUserModal').modal('hide');
        $('#addPanelUserForm')[0].reset();
        await loadAllData();
    } else {
        showAlert(result.error || '<?php echo $lang2827; ?>', 'danger');
    }
}

function editPanelUser(u) {
    $('#edit_panel_id').val(u.id);
    $('#edit_panel_username').val(u.username);
    $('#edit_panel_email').val(u.email || '');
    $('#edit_panel_role').val(u.role);
    new bootstrap.Modal(document.getElementById('editPanelUserModal')).show();
}

async function updatePanelUser(e) {
    e.preventDefault();
    const result = await apiCall('update_panel_user', 'POST', {
        user_id: $('#edit_panel_id').val(),
        username: $('#edit_panel_username').val(),
        email: $('#edit_panel_email').val(),
        role: $('#edit_panel_role').val()
    });
    if (result.success) {
        showAlert(result.message, 'success');
        $('#editPanelUserModal').modal('hide');
        await loadAllData();
    } else {
        showAlert(result.error || '<?php echo $lang2828; ?>', 'danger');
    }
}

function changePanelPass(u) {
    $('#pass_panel_id').val(u.id);
    $('#pass_panel_username').text(u.username);
    $('#change_panel_password').val('');
    $('#change_panel_confirm').val('');
    new bootstrap.Modal(document.getElementById('changePanelPassModal')).show();
}

async function changePanelPasswordSubmit(e) {
    e.preventDefault();
    const password = $('#change_panel_password').val();
    const confirm = $('#change_panel_confirm').val();
    
    if (password !== confirm) {
        showAlert('<?php echo $lang2829; ?>', 'danger');
        return;
    }
    
    if (password.length < 4) {
        showAlert('<?php echo $lang2830; ?>', 'danger');
        return;
    }
    
    const result = await apiCall('change_panel_password', 'POST', {
        user_id: $('#pass_panel_id').val(),
        new_password: password
    });
    if (result.success) {
        showAlert(result.message, 'success');
        $('#changePanelPassModal').modal('hide');
    } else {
        showAlert(result.error || '<?php echo $lang2831; ?>', 'danger');
    }
}

async function deletePanelUser(id) {
    if (!confirm('<?php echo $lang2832; ?>')) return;
    const result = await apiCall('delete_panel_user', 'GET', { id: id });
    if (result.success) {
        showAlert(result.message, 'success');
        await loadAllData();
    } else {
        showAlert(result.error || '<?php echo $lang2833; ?>', 'danger');
    }
}

// ========== SYSTEM USER ACTIONS ==========
async function addSystemUser(e) {
    e.preventDefault();
    const username = $('#add_sys_username').val();
    const password = $('#add_sys_password').val();
    const confirm = $('#add_sys_confirm').val();
    const groups = $('input[name="groups[]"]:checked').map(function() { return $(this).val(); }).get();
    
    if (password !== confirm) {
        showAlert('<?php echo $lang2834; ?>', 'danger');
        return;
    }
    
    if (password.length < 4) {
        showAlert('<?php echo $lang2835; ?>', 'danger');
        return;
    }
    
    const result = await apiCall('add_system_user', 'POST', { username, password, groups });
    if (result.success) {
        showAlert(result.message, 'success');
        $('#addSystemUserModal').modal('hide');
        $('#addSystemUserForm')[0].reset();
        await loadAllData();
    } else {
        showAlert(result.error || '<?php echo $lang2836; ?>', 'danger');
    }
}

function editSystemUser(u) {
    $('#edit_sys_username').val(u.username);
    $('#edit_sys_username_label').text(u.username);
    
    let html = '';
    allGroups.forEach(group => {
        const checked = u.groups.includes(group.name);
        html += `<div class="form-check">
            <input class="form-check-input" type="checkbox" name="groups[]" value="${escapeHtml(group.name)}" id="edit_group_${escapeHtml(group.name)}" ${checked ? 'checked' : ''}>
            <label for="edit_group_${escapeHtml(group.name)}">${escapeHtml(group.name)}</label>
        </div>`;
    });
    $('#edit_groups_container').html(html);
    new bootstrap.Modal(document.getElementById('editSystemUserModal')).show();
}

async function updateSystemUserGroups(e) {
    e.preventDefault();
    const groups = $('input[name="groups[]"]:checked').map(function() { return $(this).val(); }).get();
    const result = await apiCall('update_system_groups', 'POST', {
        username: $('#edit_sys_username').val(),
        groups: groups
    });
    if (result.success) {
        showAlert(result.message, 'success');
        $('#editSystemUserModal').modal('hide');
        await loadAllData();
    } else {
        showAlert(result.error || '<?php echo $lang2837; ?>', 'danger');
    }
}

function changeSystemPass(u) {
    $('#change_sys_username').val(u.username);
    $('#change_sys_username_label').text(u.username);
    $('#change_sys_password').val('');
    $('#change_sys_confirm').val('');
    new bootstrap.Modal(document.getElementById('changeSystemPassModal')).show();
}

async function changeSystemPasswordSubmit(e) {
    e.preventDefault();
    const password = $('#change_sys_password').val();
    const confirm = $('#change_sys_confirm').val();
    
    if (password !== confirm) {
        showAlert('<?php echo $lang2838; ?>', 'danger');
        return;
    }
    
    if (password.length < 4) {
        showAlert('<?php echo $lang2839; ?>', 'danger');
        return;
    }
    
    const result = await apiCall('change_system_password', 'POST', {
        username: $('#change_sys_username').val(),
        password: password
    });
    if (result.success) {
        showAlert(result.message, 'success');
        $('#changeSystemPassModal').modal('hide');
    } else {
        showAlert(result.error || '<?php echo $lang2840; ?>', 'danger');
    }
}

function setSmbPass(u) {
    $('#smb_username').val(u.username);
    $('#smb_username_label').text(u.username);
    $('#smb_password').val('');
    new bootstrap.Modal(document.getElementById('setSmbPassModal')).show();
}

async function setSmbPasswordSubmit(e) {
    e.preventDefault();
    const result = await apiCall('set_smb_password', 'POST', {
        username: $('#smb_username').val(),
        password: $('#smb_password').val()
    });
    if (result.success) {
        showAlert(result.message, 'success');
        $('#setSmbPassModal').modal('hide');
    } else {
        showAlert(result.error || '<?php echo $lang2841; ?>', 'danger');
    }
}

async function deleteSystemUser(username) {
    if (!confirm(`<?php echo $lang2842; ?> "${username}" <?php echo $lang2843; ?>`)) return;
    const result = await apiCall('delete_system_user', 'POST', { username: username });
    if (result.success) {
        showAlert(result.message, 'success');
        await loadAllData();
    } else {
        showAlert(result.error || '<?php echo $lang2844; ?>', 'danger');
    }
}

// ========== GROUP ACTIONS ==========
async function addGroup(e) {
    e.preventDefault();
    const groupname = $('#add_group_name').val();
    const result = await apiCall('add_system_group', 'POST', { groupname });
    if (result.success) {
        showAlert(result.message, 'success');
        $('#addGroupModal').modal('hide');
        $('#addGroupForm')[0].reset();
        await loadAllData();
    } else {
        showAlert(result.error || '<?php echo $lang2845; ?>', 'danger');
    }
}

function renameGroup(name) {
    $('#rename_old_groupname').val(name);
    $('#rename_current_name').text(name);
    $('#rename_newname').val('');
    new bootstrap.Modal(document.getElementById('renameGroupModal')).show();
}

async function renameGroupSubmit(e) {
    e.preventDefault();
    const result = await apiCall('rename_system_group', 'POST', {
        oldname: $('#rename_old_groupname').val(),
        newname: $('#rename_newname').val()
    });
    if (result.success) {
        showAlert(result.message, 'success');
        $('#renameGroupModal').modal('hide');
        await loadAllData();
    } else {
        showAlert(result.error || '<?php echo $lang2846; ?>', 'danger');
    }
}

async function deleteGroup(name) {
    if (!confirm(`<?php echo $lang2847; ?> "${name}"?`)) return;
    const result = await apiCall('delete_system_group', 'POST', { groupname: name });
    if (result.success) {
        showAlert(result.message, 'success');
        await loadAllData();
    } else {
        showAlert(result.error || '<?php echo $lang2848; ?>', 'danger');
    }
}

// ========== UI CONTROLS ==========
function filterUsers() {
    searchTerm = $('#searchInput').val();
    activeOnly = $('#showActiveOnly').is(':checked');
    renderUsers();
}

function setFilter(type) {
    currentFilter = type;
    $('.filter-tab').removeClass('active');
    $(`.filter-tab[data-type="${type}"]`).addClass('active');
    filterUsers();
}

function setViewMode(mode) {
    document.cookie = `user_view_mode=${mode}; path=/; max-age=2592000`;
    $('#tableView').toggle(mode === 'table');
    $('#gridView').toggle(mode === 'grid');
    $('.view-toggle button').removeClass('active');
    $(event.target).addClass('active');
}

function toggleGroups() {
    groupsCollapsed = !groupsCollapsed;
    $('#groupsContent').slideToggle();
    $('#groupsToggleIcon').toggleClass('fa-chevron-down fa-chevron-up');
    
    localStorage.setItem('groups_collapsed', groupsCollapsed ? '1' : '0');
}

function initGroupsState() {
    const savedState = localStorage.getItem('groups_collapsed');
    if (savedState !== null) {
        groupsCollapsed = savedState === '1';
    } else {
        groupsCollapsed = true;
    }
    
    if (!groupsCollapsed) {
        $('#groupsContent').show();
        $('#groupsToggleIcon').removeClass('fa-chevron-down').addClass('fa-chevron-up');
    } else {
        $('#groupsContent').hide();
        $('#groupsToggleIcon').removeClass('fa-chevron-up').addClass('fa-chevron-down');
    }
}

async function refreshAllData() {
    showAlert('<?php echo $lang2849; ?>', 'info');
    await loadAllData();
    showAlert('<?php echo $lang2850; ?>', 'success');
}

// ========== PASSWORD UTILITIES ==========
function checkPasswordStrength(password) {
    let strength = 0;
    if (password.length >= 8) strength++;
    if (password.match(/[a-z]/)) strength++;
    if (password.match(/[A-Z]/)) strength++;
    if (password.match(/[0-9]/)) strength++;
    if (password.match(/[^a-zA-Z0-9]/)) strength++;
    
    if (strength <= 2) return { text: 'Weak', class: 'strength-weak' };
    if (strength <= 4) return { text: 'Medium', class: 'strength-medium' };
    return { text: 'Strong', class: 'strength-strong' };
}

function generatePassword(fieldId) {
    const charset = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()";
    let password = "";
    for (let i = 0; i < 12; i++) {
        password += charset.charAt(Math.floor(Math.random() * charset.length));
    }
    document.getElementById(fieldId).value = password;
    updatePasswordStrength(fieldId);
}

function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    field.type = field.type === "password" ? "text" : "password";
}

function updatePasswordStrength(fieldId) {
    const password = document.getElementById(fieldId).value;
    const strength = checkPasswordStrength(password);
    let strengthId = null;
    if (fieldId === 'add_panel_password') strengthId = 'addPanelStrength';
    if (fieldId === 'change_panel_password') strengthId = 'changePanelStrength';
    if (fieldId === 'add_sys_password') strengthId = 'addSysStrength';
    if (strengthId) {
        $(`#${strengthId}`).html(`<i class="fas fa-shield-alt"></i> <?php echo $lang2851; ?> <span class="${strength.class}">${strength.text}</span>`);
    }
}

// ========== INITIALIZATION ==========
$(document).ready(function() {
    initHostSelector();
    loadAllData();
    
    // Form submissions
    $('#addPanelUserForm').on('submit', addPanelUser);
    $('#editPanelUserForm').on('submit', updatePanelUser);
    $('#changePanelPassForm').on('submit', changePanelPasswordSubmit);
    $('#addSystemUserForm').on('submit', addSystemUser);
    $('#editSystemUserForm').on('submit', updateSystemUserGroups);
    $('#changeSystemPassForm').on('submit', changeSystemPasswordSubmit);
    $('#setSmbPassForm').on('submit', setSmbPasswordSubmit);
    $('#addGroupForm').on('submit', addGroup);
    $('#renameGroupForm').on('submit', renameGroupSubmit);
    
    // Password strength listeners
    $('#add_panel_password, #change_panel_password, #add_sys_password').on('input', function() {
        updatePasswordStrength(this.id);
    });
    
    // Auto-refresh every 30 seconds
    setInterval(() => {
        if (!document.hidden) {
            loadStats();
        }
    }, 30000);
    
    setTimeout(function() { $('#applePreloader').fadeOut(500); }, 500);
});
</script>
</body>
</html>