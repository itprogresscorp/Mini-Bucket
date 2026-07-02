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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Firewall — Mini-B</title>
    <link href="lib/bootstrap-5.3.8-dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="lib/bootstrap-icons-1.11.0/bootstrap-icons.css">
    <link rel="stylesheet" href="lib/fontawesome-free-6.7.2-web/css/all.min.css">
    <!--<link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">-->
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
        
        .content-header {
            margin-bottom: 24px;
        }
        
        .content-header h1 {
            font-size: 28px;
            font-weight: 600;
            letter-spacing: -0.3px;
            background: linear-gradient(135deg, #1c1c1e, #3a3a3c);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 0 0 16px 0;
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
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
        }
        
        .status-active {
            background: #34c75920;
            color: #248a3d;
        }
        
        .status-inactive {
            background: #ff3b3020;
            color: #d70015;
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
        
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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
            font-size: 28px;
            margin-bottom: 10px;
            color: #007aff;
        }
        
        .stat-number {
            font-size: 28px;
            font-weight: 700;
            color: #1c1c1e;
        }
        
        .stat-label {
            font-size: 13px;
            color: #8e8e93;
        }
        
        .rule-item {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 14px;
            padding: 12px 16px;
            margin-bottom: 10px;
            transition: all 0.2s;
        }
        
        .rule-item:hover {
            background: white;
            border-color: #007aff;
            box-shadow: 0 2px 8px rgba(0,122,255,0.1);
        }
        
        .rule-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
        }
        
        .rule-allow {
            background: #34c75920;
            color: #248a3d;
        }
        
        .rule-deny {
            background: #ff3b3020;
            color: #d70015;
        }
        
        .rule-reject {
            background: #ff3b3020;
            color: #d70015;
        }
        
        .rule-limit {
            background: #ff950020;
            color: #c47a00;
        }
        
        .rule-in {
            background: #007aff20;
            color: #005fc1;
        }
        
        .rule-out {
            background: #5856d620;
            color: #3a38a3;
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
        
        .kill-connection-btn {
            opacity: 0;
            transition: opacity 0.2s;
        }
        
        .connection-item:hover .kill-connection-btn {
            opacity: 1;
        }
        
        .log-entry {
            font-family: 'SF Mono', Monaco, monospace;
            font-size: 11px;
            padding: 10px 12px;
            border-left: 3px solid;
            background: #f8f9fa;
            margin-bottom: 6px;
            border-radius: 10px;
        }
        
        .log-allow {
            border-left-color: #34c759;
        }
        
        .log-block {
            border-left-color: #ff3b30;
        }
        
        .log-limit {
            border-left-color: #ff9500;
        }
        
        .filter-section {
            background: #f8f9fa;
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 20px;
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
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
        }
        
        .empty-state i {
            font-size: 48px;
            color: #c6c6c8;
            margin-bottom: 16px;
        }
        
        .quick-action-btn {
            transition: all 0.2s;
        }
        
        .quick-action-btn:hover {
            transform: translateY(-2px);
        }
        
        .info-box {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 14px 16px;
            margin-bottom: 12px;
        }
    </style>
</head>
<body>
<div id="applePreloader" class="apple-preloader">
    <div class="apple-spinner"></div>
    <div class="apple-spinner-text"><?php echo $lang12; ?></div>
</div>

<div id="loadingOverlay" class="loading-overlay" style="display: none;">
    <div class="loading-spinner"></div>
</div>

<div class="toast-container" id="toastContainer"></div>

<div class="top-bar">
    <div class="top-bar-left">
        <h1><i class="fas fa-bucket"></i> Mini-B</h1>
    </div>
    <div class="top-bar-right">
        <span><i class="bi bi-shield-check me-2"></i> Firewall</span>
		<div class="host-selector" style="margin-left: 20px;">
            <select id="hostSelector" style="background: rgba(255,255,255,0.9); border: 1px solid #ddd; border-radius: 20px; padding: 6px 30px 6px 15px; font-size: 14px; cursor: pointer;">
                <option value=""><?php echo $lang12; ?></option>
            </select>
        </div>
        <div id="ufwStatusWidget" class="d-flex align-items-center gap-3"></div>
		
        <div class="btn-group">
            <button class="btn btn-sm btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-bars"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="#" onclick="openAddRuleModal()"><i class="fas fa-plus me-2"></i><?php echo $lang3164; ?></a></li>
                <li><a class="dropdown-item" href="#" onclick="openAddAppRuleModal()"><i class="fas fa-tools me-2"></i><?php echo $lang3165; ?></a></li>
                <li><a class="dropdown-item" href="#" onclick="quickBlockIP()"><i class="fas fa-ban me-2"></i><?php echo $lang3166; ?></a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="#" onclick="resetAllRules()"><i class="fas fa-trash me-2"></i><?php echo $lang3167; ?></a></li>
                <li><a class="dropdown-item" href="#" onclick="refreshRules()"><i class="fas fa-repeat me-2"></i><?php echo $lang3168; ?></a></li>
            </ul>
        </div>
    </div>
</div>

<div class="app-container">
    <?php echo $menu; ?>
    
    <main class="main-content">
        
        <ul class="nav nav-tabs-apple" id="firewallTabs" role="tablist">
            <li class="nav-item">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#rulesTab" type="button" role="tab">
                    <i class="bi bi-list-ul me-2"></i><?php echo $lang3169; ?>
                    <span id="rulesCount" class="badge bg-secondary ms-1" style="background: #8e8e93 !important;">0</span>
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#connectionsTab" type="button" role="tab">
                    <i class="bi bi-diagram-3 me-2"></i><?php echo $lang3170; ?>
                    <span id="connectionsCount" class="badge bg-secondary ms-1" style="background: #8e8e93 !important;">0</span>
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#logsTab" type="button" role="tab">
                    <i class="bi bi-clock-history me-2"></i><?php echo $lang3171; ?>
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#settingsTab" type="button" role="tab">
                    <i class="bi bi-gear me-2"></i><?php echo $lang3172; ?>
                </button>
            </li>
        </ul>
        
        <div class="tab-content">
            <!-- Rules Tab -->
            <div class="tab-pane fade show active" id="rulesTab" role="tabpanel">
                <div class="apple-card">
                    <div class="card-header-apple">
                        <h3><i class="bi bi-shield-check"></i> <?php echo $lang3173; ?></h3>
                        <button class="btn-apple" onclick="openAddRuleModal()">
                            <i class="bi bi-plus-lg"></i> <?php echo $lang3174; ?>
                        </button>
                    </div>
                    <div class="card-body-apple">
                        <div class="filter-section">
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <div class="input-group">
                                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                                        <input type="text" id="ruleSearch" class="form-control border-start-0" placeholder="<?php echo $lang3175; ?>" style="border-radius: 0 12px 12px 0;">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <select id="filterDirection" class="form-select">
                                        <option value="all">📊 <?php echo $lang3176; ?></option>
                                        <option value="in">⬇️ <?php echo $lang3177; ?></option>
                                        <option value="out">⬆️ <?php echo $lang3178; ?></option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <select id="filterAction" class="form-select">
                                        <option value="all">🎯 <?php echo $lang3179; ?></option>
                                        <option value="allow">✅ <?php echo $lang3180; ?></option>
                                        <option value="deny">❌ <?php echo $lang3181; ?></option>
                                        <option value="reject">🚫 <?php echo $lang3182; ?></option>
                                        <option value="limit">⏱️ <?php echo $lang3183; ?></option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <button class="btn btn-outline-secondary w-100" style="border-radius: 10px;" onclick="clearRuleFilters()">
                                        <i class="bi bi-eraser"></i> <?php echo $lang3184; ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div id="rulesList">
                            <div class="text-center text-muted py-4">
                                <div class="spinner-border text-primary mb-2" role="status"></div>
                                <p><?php echo $lang3185; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Connections Tab -->
            <div class="tab-pane fade" id="connectionsTab" role="tabpanel">
                <div class="filter-section">
                    <div class="row g-2">
                        <div class="col-md-8">
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-funnel"></i></span>
                                <input type="text" id="connectionSearch" class="form-control" placeholder="<?php echo $lang3186; ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <button class="btn-apple w-100" onclick="refreshConnections()">
                                <i class="bi bi-arrow-repeat"></i> <?php echo $lang3187; ?>
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="stats-row">
                    <div class="stat-card">
                        <i class="bi bi-arrow-down-circle"></i>
                        <div class="stat-number" id="incomingCount">0</div>
                        <div class="stat-label"><?php echo $lang3188; ?></div>
                    </div>
                    <div class="stat-card">
                        <i class="bi bi-arrow-up-circle"></i>
                        <div class="stat-number" id="outgoingCount">0</div>
                        <div class="stat-label"><?php echo $lang3189; ?></div>
                    </div>
                    <div class="stat-card">
                        <i class="bi bi-people"></i>
                        <div class="stat-number" id="totalCount">0</div>
                        <div class="stat-label"><?php echo $lang3190; ?></div>
                    </div>
                </div>
                
                <div class="apple-card">
                    <div class="card-header-apple">
                        <h3><i class="bi bi-arrow-down-circle text-success"></i> <?php echo $lang3191; ?></h3>
                    </div>
                    <div class="card-body-apple" id="incomingConnectionsList">
                        <div class="text-center text-muted py-4"><?php echo $lang3192; ?></div>
                    </div>
                </div>
                
                <div class="apple-card">
                    <div class="card-header-apple">
                        <h3><i class="bi bi-arrow-up-circle text-warning"></i> <?php echo $lang3193; ?></h3>
                    </div>
                    <div class="card-body-apple" id="outgoingConnectionsList">
                        <div class="text-center text-muted py-4"><?php echo $lang3194; ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Logs Tab -->
            <div class="tab-pane fade" id="logsTab" role="tabpanel">
                <div class="apple-card">
                    <div class="card-header-apple">
                        <h3><i class="bi bi-file-text"></i> <?php echo $lang3195; ?></h3>
                        <div class="d-flex gap-2">
                            <select id="logLinesSelect" class="form-select form-select-sm" style="width: auto; border-radius: 10px;">
                                <option value="50">50 <?php echo $lang3196; ?></option>
                                <option value="100" selected>100 <?php echo $lang3196; ?></option>
                                <option value="200">200 <?php echo $lang3196; ?></option>
                                <option value="500">500 <?php echo $lang3196; ?></option>
                            </select>
                            <select id="logTypeFilter" class="form-select form-select-sm" style="width: auto; border-radius: 10px;">
                                <option value="all"><?php echo $lang3197; ?></option>
                                <option value="ALLOW">✅ <?php echo $lang3198; ?></option>
                                <option value="BLOCK">❌ <?php echo $lang3199; ?></option>
                                <option value="LIMIT">⚠️ <?php echo $lang3200; ?></option>
                            </select>
                            <button type="button" class="btn-apple-outline" onclick="refreshLogs()">
                                <i class="bi bi-arrow-repeat"></i> <?php echo $lang3201; ?>
                            </button>
                            <button type="button" class="btn-apple-outline" onclick="exportLogs()">
                                <i class="bi bi-download"></i> <?php echo $lang3202; ?>
                            </button>
                        </div>
                    </div>
                    <div class="card-body-apple">
                        <div class="mb-3">
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                                <input type="text" id="logFilter" class="form-control" placeholder="<?php echo $lang3203; ?>">
                                <button class="btn btn-outline-secondary" style="border-radius: 0 10px 10px 0;" onclick="clearLogFilter()">
                                    <i class="bi bi-x-lg"></i> <?php echo $lang3204; ?>
                                </button>
                            </div>
                        </div>
                        <div id="logsList" style="max-height: 500px; overflow-y: auto;">
                            <div class="text-center text-muted py-4"><?php echo $lang3205; ?></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Settings Tab -->
            <div class="tab-pane fade" id="settingsTab" role="tabpanel">
                <div class="row g-4">
                    <div class="col-lg-6">
                        <div class="apple-card">
                            <div class="card-header-apple">
                                <h3><i class="bi bi-globe"></i> <?php echo $lang3206; ?></h3>
                            </div>
                            <div class="card-body-apple">
                                <form id="defaultPoliciesForm">
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold"><?php echo $lang3207; ?></label>
                                        <select id="defaultIncoming" class="form-select form-select-apple">
                                            <option value="deny"><?php echo $lang3208; ?></option>
                                            <option value="allow"><?php echo $lang3209; ?></option>
                                        </select>
                                        <small class="text-muted"><?php echo $lang3210; ?></small>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold"><?php echo $lang3211; ?></label>
                                        <select id="defaultOutgoing" class="form-select form-select-apple">
                                            <option value="allow"><?php echo $lang3212; ?></option>
                                            <option value="deny"><?php echo $lang3213; ?></option>
                                        </select>
                                        <small class="text-muted"><?php echo $lang3214; ?></small>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold"><?php echo $lang3215; ?></label>
                                        <select id="defaultRouted" class="form-select form-select-apple">
                                            <option value="deny"><?php echo $lang3216; ?></option>
                                            <option value="allow"><?php echo $lang3217; ?></option>
                                        </select>
                                        <small class="text-muted"><?php echo $lang3218; ?></small>
                                    </div>
                                    <button type="submit" class="btn-apple"><?php echo $lang3219; ?></button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-6">
                        <div class="apple-card">
                            <div class="card-header-apple">
                                <h3><i class="bi bi-bar-chart-steps"></i> <?php echo $lang3220; ?></h3>
                            </div>
                            <div class="card-body-apple">
                                <form id="loggingForm">
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold"><?php echo $lang3221; ?></label>
                                        <select id="loggingLevel" class="form-select form-select-apple">
                                            <option value="off"><?php echo $lang3222; ?></option>
                                            <option value="low"><?php echo $lang3223; ?></option>
                                            <option value="medium"><?php echo $lang3224; ?></option>
                                            <option value="high"><?php echo $lang3225; ?></option>
                                        </select>
                                        <small class="text-muted"><?php echo $lang3226; ?></small>
                                    </div>
                                    <button type="submit" class="btn-apple"><?php echo $lang3227; ?></button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="apple-card">
                    <div class="card-header-apple">
                        <h3><i class="bi bi-info-circle"></i> <?php echo $lang3228; ?></h3>
                        <button class="btn-apple-outline" onclick="loadSettings()">
                            <i class="bi bi-arrow-repeat"></i> <?php echo $lang3229; ?>
                        </button>
                    </div>
                    <div class="card-body-apple" id="ufwInfo">
                        <div class="text-center text-muted py-4"><?php echo $lang3230; ?></div>
                    </div>
                </div>
                
                <div class="apple-card">
                    <div class="card-header-apple">
                        <h3><i class="bi bi-lightning-charge"></i> <?php echo $lang3231; ?></h3>
                    </div>
                    <div class="card-body-apple">
                        <div class="row g-2">
                            <div class="col-md-3">
                                <button class="btn-apple-outline w-100 quick-action-btn" style="background: #34c75910; border-color: #34c759; color: #248a3d;" onclick="quickAddRule('22', 'SSH')">
                                    <i class="bi bi-key"></i> <?php echo $lang3232; ?>
                                </button>
                            </div>
                            <div class="col-md-3">
                                <button class="btn-apple-outline w-100 quick-action-btn" onclick="quickAddRule('80', 'HTTP')">
                                    <i class="bi bi-globe"></i> <?php echo $lang3233; ?>
                                </button>
                            </div>
                            <div class="col-md-3">
                                <button class="btn-apple-outline w-100 quick-action-btn" onclick="quickAddRule('443', 'HTTPS')">
                                    <i class="bi bi-lock"></i> <?php echo $lang3234; ?>
                                </button>
                            </div>
                            <div class="col-md-3">
                                <button class="btn-apple-outline w-100 quick-action-btn" onclick="quickAddRule('21', 'FTP')">
                                    <i class="bi bi-folder"></i> <?php echo $lang3235; ?>
                                </button>
                            </div>
                            <div class="col-md-3">
                                <button class="btn-apple-outline w-100 quick-action-btn" onclick="quickAddRule('3306', 'MySQL')">
                                    <i class="bi bi-database"></i> <?php echo $lang3236; ?>
                                </button>
                            </div>
                            <div class="col-md-3">
                                <button class="btn-apple-outline w-100 quick-action-btn" onclick="quickAddRule('5432', 'PostgreSQL')">
                                    <i class="bi bi-database"></i> <?php echo $lang3237; ?>
                                </button>
                            </div>
                            <div class="col-md-3">
                                <button class="btn-apple-outline w-100 quick-action-btn" style="border-color: #ff9500; color: #c47a00;" onclick="quickBlockIP()">
                                    <i class="bi bi-ban"></i> <?php echo $lang3238; ?>
                                </button>
                            </div>
                            <div class="col-md-3">
                                <button class="btn btn-outline-danger w-100 quick-action-btn" onclick="resetAllRules()">
                                    <i class="bi bi-trash3"></i> <?php echo $lang3239; ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Add/Edit Rule Modal -->
<div class="modal fade modal-apple" id="ruleModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-semibold" id="ruleModalTitle"><i class="bi bi-plus-circle me-2"></i><?php echo $lang3240; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="ruleForm">
                    <input type="hidden" id="ruleId" value="">
                    <div class="mb-3">
                        <label class="form-label fw-semibold"><?php echo $lang3241; ?></label>
                        <select id="ruleDirection" class="form-select form-select-apple" required>
                            <option value="in"><?php echo $lang3242; ?></option>
                            <option value="out"><?php echo $lang3243; ?></option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold"><?php echo $lang3244; ?></label>
                        <select id="ruleAction" class="form-select form-select-apple" required>
                            <option value="allow"><?php echo $lang3245; ?></option>
                            <option value="deny"><?php echo $lang3246; ?></option>
                            <option value="reject"><?php echo $lang3247; ?></option>
                            <option value="limit"><?php echo $lang3248; ?></option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold"><?php echo $lang3249; ?></label>
                        <input type="text" id="rulePort" class="form-control form-control-apple" placeholder="e.g., 22, 80, 443, 3000:3010" required>
                        <small class="text-muted"><?php echo $lang3250; ?></small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold"><?php echo $lang3251; ?></label>
                        <select id="ruleProtocol" class="form-select form-select-apple">
                            <option value="tcp">TCP</option>
                            <option value="udp">UDP</option>
                            <option value="any">Any</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold"><?php echo $lang3252; ?></label>
                        <select id="ipVersion" class="form-select form-select-apple">
                            <option value="both">IPv4 and IPv6 (both)</option>
                            <option value="ipv4">IPv4 only</option>
                            <option value="ipv6">IPv6 only</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold"><?php echo $lang3253; ?></label>
                        <input type="text" id="ruleFrom" class="form-control form-control-apple" placeholder="e.g., 192.168.1.0/24 <?php echo $lang3254; ?>">
                        <small class="text-muted"><?php echo $lang3255; ?></small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold"><?php echo $lang3256; ?></label>
                        <input type="text" id="ruleComment" class="form-control form-control-apple" placeholder="<?php echo $lang3257; ?>">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang3258; ?></button>
                <button type="button" class="btn-apple" onclick="saveRule()"><?php echo $lang3259; ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Add Service Rule Modal -->
<div class="modal fade modal-apple" id="appRuleModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-semibold"><i class="bi bi-apple me-2"></i><?php echo $lang3260; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold"><?php echo $lang3261; ?></label>
                    <select id="appSelect" class="form-select form-select-apple">
                        <option value=""><?php echo $lang3262; ?></option>
                    </select>
                    <small class="text-muted"><?php echo $lang3263; ?></small>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold"><?php echo $lang3264; ?></label>
                    <select id="appAction" class="form-select form-select-apple">
                        <option value="allow"><?php echo $lang3265; ?></option>
                        <option value="deny"><?php echo $lang3266; ?></option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold"><?php echo $lang3267; ?></label>
                    <select id="appIpVersion" class="form-select form-select-apple">
                        <option value="both">IPv4 and IPv6 (both)</option>
                        <option value="ipv4">IPv4 only</option>
                        <option value="ipv6">IPv6 only</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang3268; ?></button>
                <button type="button" class="btn-apple" onclick="addAppRule()"><?php echo $lang3269; ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Block IP Modal -->
<div class="modal fade modal-apple" id="blockIPModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-semibold"><i class="bi bi-ban me-2"></i><?php echo $lang3270; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold"><?php echo $lang3271; ?></label>
                    <input type="text" id="blockIP" class="form-control form-control-apple" placeholder="e.g., 192.168.1.100">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold"><?php echo $lang3272; ?></label>
                    <input type="text" id="blockPort" class="form-control form-control-apple" placeholder="<?php echo $lang3273; ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold"><?php echo $lang3274; ?></label>
                    <input type="text" id="blockComment" class="form-control form-control-apple" placeholder="<?php echo $lang3275; ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold"><?php echo $lang3276; ?></label>
                    <select id="blockIpVersion" class="form-select form-select-apple">
                        <option value="both">IPv4 and IPv6 (both)</option>
                        <option value="ipv4">IPv4 only</option>
                        <option value="ipv6">IPv6 only</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang3277; ?></button>
                <button type="button" class="btn-apple-danger btn" onclick="blockIP()"><?php echo $lang3278; ?></button>
            </div>
        </div>
    </div>
</div>

<script src="lib/jquery-3.6.0-master/dist/jquery.min.js"></script>
<script src="lib/bootstrap-5.3.8-dist/js/bootstrap.bundle.min.js"></script>
<script src="js/loader.js"></script>

<script>
const url = "<?php echo $current_host_id == 1 ? '/api/' : rtrim($host_url, '/') . '/'; ?>";
let currentRules = [];
let currentConnections = { incoming: [], outgoing: [] };
let currentLogs = [];

function showToast(message, type = 'success') {
    const toast = $(`
        <div class="toast-custom toast-${type}">
            <i class="bi ${type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill'} me-2"></i>
            <span>${message}</span>
        </div>
    `);
    $('#toastContainer').append(toast);
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

// Load UFW Status
function loadUfwStatus() {
    const headers = {};
    if (window.apiConfig && window.apiConfig.apiKey) {
        headers['X-API-Key'] = window.apiConfig.apiKey;
    }
    
    $.ajax({
        url: url + 'firewall_api.php?action=get_status',
        method: 'GET',
        headers: headers,
        success: function(response) {
            if (response.success) {
                const status = response.data;
                const isActive = status.active;
                $('#ufwStatusWidget').html(`
                    <div class="d-flex align-items-center gap-3">
                        <span class="status-badge ${isActive ? 'status-active' : 'status-inactive'}">
                            <i class="bi ${isActive ? 'bi-shield-check' : 'bi-shield-x'}"></i>
                            ${isActive ? 'Active' : 'Inactive'}
                        </span>
                        <label class="toggle-switch">
                            <input type="checkbox" id="ufwToggle" ${isActive ? 'checked' : ''}>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                `);
                
                $('#ufwToggle').off('change').on('change', function() {
                    const enabled = $(this).prop('checked');
                    showLoading();
                    $.ajax({
                        url: url + 'firewall_api.php?action=set_enabled',
                        method: 'POST',
                        data: { enabled: enabled },
                        headers: headers,
                        success: function(res) {
                            hideLoading();
                            if (res.success) {
                                showToast(`UFW ${enabled ? '<?php echo $lang3280; ?>' : '<?php echo $lang3281; ?>'}`, 'success');
                                loadUfwStatus();
                                refreshRules();
                            } else {
                                showToast(res.error, 'error');
                                $('#ufwToggle').prop('checked', !enabled);
                            }
                        },
                        error: function(xhr, status, error) {
                            hideLoading();
                            showToast('<?php echo $lang3279; ?> ' + error, 'error');
                            $('#ufwToggle').prop('checked', !enabled);
                        }
                    });
                });
            }
        },
        error: function(xhr, status, error) {
            console.error('Load UFW status error:', error);
            $('#ufwStatusWidget').html('<div class="text-danger"><?php echo $lang3282; ?></div>');
        }
    });
}

// Refresh Rules
function refreshRules() {
    showLoading();
    
    const headers = {};
    if (window.apiConfig && window.apiConfig.apiKey) {
        headers['X-API-Key'] = window.apiConfig.apiKey;
    }
    
    $.ajax({
        url: url + 'firewall_api.php?action=get_rules',
        method: 'GET',
        headers: headers,
        success: function(response) {
            hideLoading();
            if (response.success) {
                currentRules = response.data;
                $('#rulesCount').text(currentRules.length);
                filterRules();
            } else {
                $('#rulesList').html('<div class="text-center text-danger py-4"><?php echo $lang3283; ?></div>');
            }
        },
        error: function(xhr, status, error) {
            hideLoading();
            console.error('Refresh rules error:', error);
            $('#rulesList').html('<div class="text-center text-danger py-4"><?php echo $lang3284; ?> ' + error + '</div>');
        }
    });
}

let ruleSearchTerm = '';
let ruleFilterDirection = 'all';
let ruleFilterAction = 'all';

function filterRules() {
    ruleSearchTerm = $('#ruleSearch').val().toLowerCase();
    ruleFilterDirection = $('#filterDirection').val();
    ruleFilterAction = $('#filterAction').val();
    
    let filtered = currentRules.filter(rule => {
        let matchSearch = ruleSearchTerm === '' || 
            rule.port.toString().includes(ruleSearchTerm) ||
            rule.protocol.toLowerCase().includes(ruleSearchTerm) ||
            (rule.comment && rule.comment.toLowerCase().includes(ruleSearchTerm)) ||
            rule.from.toLowerCase().includes(ruleSearchTerm);
        let matchDirection = ruleFilterDirection === 'all' || rule.direction === ruleFilterDirection;
        let matchAction = ruleFilterAction === 'all' || rule.action === ruleFilterAction;
        return matchSearch && matchDirection && matchAction;
    });
    
    renderRulesList(filtered);
}

function clearRuleFilters() {
    $('#ruleSearch').val('');
    $('#filterDirection').val('all');
    $('#filterAction').val('all');
    filterRules();
}

function renderRulesList(rules) {
    if (rules.length === 0) {
        $('#rulesList').html(`
            <div class="empty-state">
                <i class="bi bi-shield-slash"></i>
                <p class="text-muted"><?php echo $lang3285; ?></p>
            </div>
        `);
        return;
    }
    
    let html = '';
    rules.forEach(rule => {
        const actionClass = rule.action === 'allow' ? 'rule-allow' : (rule.action === 'deny' || rule.action === 'reject' ? 'rule-deny' : 'rule-limit');
        const directionClass = rule.direction === 'in' ? 'rule-in' : 'rule-out';
        const ipVersionText = rule.ip_version === 'ipv4' ? 'IPv4' : (rule.ip_version === 'ipv6' ? 'IPv6' : 'v4+v6');
        
        html += `
            <div class="rule-item" data-id="${rule.id}">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="d-flex align-items-center flex-wrap gap-2">
                        <span class="rule-badge ${actionClass}">${rule.action.toUpperCase()}</span>
                        <span class="rule-badge ${directionClass}">${rule.direction === 'in' ? '<?php echo $lang3286; ?>' : '<?php echo $lang3287; ?>'}</span>
                        <code class="bg-light p-1 rounded">${rule.port}/${rule.protocol}</code>
                        <span class="badge" style="background: #5856d6;">${ipVersionText}</span>
                        ${rule.from !== 'anywhere' && rule.from !== 'any' ? `<span class="text-muted"><i class="bi bi-arrow-right"></i> ${escapeHtml(rule.from)}</span>` : ''}
                        ${rule.comment ? `<span class="badge bg-secondary">${escapeHtml(rule.comment)}</span>` : ''}
                    </div>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary btn-sm" style="border-radius: 8px;" onclick="editRule(${rule.id})">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-outline-danger btn-sm" style="border-radius: 8px;" onclick="deleteRule(${rule.id})">
                            <i class="bi bi-trash3"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
    });
    $('#rulesList').html(html);
}

function openAddRuleModal() {
    $('#ruleModalTitle').html('<i class="bi bi-plus-circle me-2"></i><?php echo $lang3288; ?>');
    $('#ruleId').val('');
    $('#ruleForm')[0].reset();
    $('#ruleDirection').val('in');
    $('#ruleAction').val('allow');
    $('#ruleProtocol').val('tcp');
    $('#ruleFrom').val('');
    $('#ruleComment').val('');
    $('#rulePort').val('');
    $('#ipVersion').val('both');
    $('#ruleModal').modal('show');
}

function editRule(ruleId) {
    const rule = currentRules.find(r => r.id === ruleId);
    if (!rule) return;
    
    $('#ruleModalTitle').html('<i class="bi bi-pencil-square me-2"></i><?php echo $lang3289; ?>');
    $('#ruleId').val(rule.id);
    $('#ruleDirection').val(rule.direction);
    $('#ruleAction').val(rule.action);
    $('#rulePort').val(rule.port);
    $('#ruleProtocol').val(rule.protocol);
    $('#ruleFrom').val(rule.from !== 'anywhere' && rule.from !== 'any' ? rule.from : '');
    $('#ruleComment').val(rule.comment || '');
    $('#ipVersion').val(rule.ip_version || 'both');
    $('#ruleModal').modal('show');
}

function saveRule() {
    const ruleId = $('#ruleId').val();
    const direction = $('#ruleDirection').val();
    const action = $('#ruleAction').val();
    const port = $('#rulePort').val();
    const protocol = $('#ruleProtocol').val();
    const from = $('#ruleFrom').val() || '';
    const comment = $('#ruleComment').val();
    const ipVersion = $('#ipVersion').val();
    
    if (!port) {
        showToast('<?php echo $lang3290; ?>', 'error');
        return;
    }
    
    const data = { direction, action, port, protocol, from, comment, ip_version: ipVersion };
    const apiUrl = ruleId ? `${url}firewall_api.php?action=update_rule&rule_id=${ruleId}` : `${url}firewall_api.php?action=add_rule`;
    
    const headers = {
        'Content-Type': 'application/json'
    };
    if (window.apiConfig && window.apiConfig.apiKey) {
        headers['X-API-Key'] = window.apiConfig.apiKey;
    }
    
    showLoading();
    $.ajax({
        url: apiUrl,
        method: 'POST',
        headers: headers,
        data: JSON.stringify(data),
        success: function(response) {
            hideLoading();
            if (response.success) {
                showToast(ruleId ? '<?php echo $lang3291; ?>' : '<?php echo $lang3292; ?>', 'success');
                $('#ruleModal').modal('hide');
                refreshRules();
            } else {
                showToast(response.error, 'error');
            }
        },
        error: function(xhr, status, error) {
            hideLoading();
            console.error('Save rule error:', error);
            showToast('<?php echo $lang3294; ?> ' + error, 'error');
        }
    });
}

function deleteRule(ruleId) {
    if (confirm('<?php echo $lang3293; ?>')) {
        showLoading();
        
        const headers = {
            'Content-Type': 'application/x-www-form-urlencoded'
        };
        if (window.apiConfig && window.apiConfig.apiKey) {
            headers['X-API-Key'] = window.apiConfig.apiKey;
        }
        
        $.ajax({
            url: url + 'firewall_api.php?action=delete_rule',
            method: 'POST',
            data: { rule_id: ruleId },
            headers: headers,
            success: function(response) {
                hideLoading();
                if (response.success) {
                    showToast('<?php echo $lang3295; ?>', 'success');
                    refreshRules();
                } else {
                    showToast(response.error, 'error');
                }
            },
            error: function(xhr, status, error) {
                hideLoading();
                console.error('Delete rule error:', error);
                showToast('<?php echo $lang3296; ?> ' + error, 'error');
            }
        });
    }
}

function resetAllRules() {
    if (confirm('<?php echo $lang3297; ?>')) {
        showLoading();
        
        const headers = {};
        if (window.apiConfig && window.apiConfig.apiKey) {
            headers['X-API-Key'] = window.apiConfig.apiKey;
        }
        
        $.ajax({
            url: url + 'firewall_api.php?action=reset_rules',
            method: 'POST',
            headers: headers,
            success: function(response) {
                hideLoading();
                if (response.success) {
                    showToast('<?php echo $lang3298; ?>', 'success');
                    refreshRules();
                } else {
                    showToast(response.error, 'error');
                }
            },
            error: function(xhr, status, error) {
                hideLoading();
                console.error('Reset rules error:', error);
                showToast('<?php echo $lang3299; ?> ' + error, 'error');
            }
        });
    }
}

// Connections
function refreshConnections() {
    const headers = {};
    if (window.apiConfig && window.apiConfig.apiKey) {
        headers['X-API-Key'] = window.apiConfig.apiKey;
    }
    
    $.ajax({
        url: url + 'firewall_api.php?action=get_connections',
        method: 'GET',
        headers: headers,
        success: function(response) {
            if (response.success) {
                currentConnections = response.data;
                $('#incomingCount').text(currentConnections.incoming.length);
                $('#outgoingCount').text(currentConnections.outgoing.length);
                $('#totalCount').text(currentConnections.total);
                $('#connectionsCount').text(currentConnections.total);
                filterAndRenderConnections();
            }
        },
        error: function(xhr, status, error) {
            console.error('Refresh connections error:', error);
        }
    });
}

function filterAndRenderConnections() {
    const search = $('#connectionSearch').val().toLowerCase();
    let inc = currentConnections.incoming;
    let out = currentConnections.outgoing;
    
    if (search) {
        inc = inc.filter(c => c.local_ip.includes(search) || c.local_port.includes(search) || c.remote_ip.includes(search) || c.remote_port.includes(search) || (c.process && c.process.toLowerCase().includes(search)));
        out = out.filter(c => c.local_ip.includes(search) || c.local_port.includes(search) || c.remote_ip.includes(search) || c.remote_port.includes(search) || (c.process && c.process.toLowerCase().includes(search)));
    }
    
    renderConnections(inc, '#incomingConnectionsList', 'incoming');
    renderConnections(out, '#outgoingConnectionsList', 'outgoing');
}

function renderConnections(connections, containerId, type) {
    if (connections.length === 0) {
        $(containerId).html(`<div class="empty-state"><i class="bi bi-plug"></i><p class="text-muted"><?php echo $lang3300; ?> ${type} <?php echo $lang3301; ?></p></div>`);
        return;
    }
    
    let html = '';
    connections.forEach(conn => {
        const stateClass = conn.state === 'ESTABLISHED' ? 'bg-success' : 'bg-warning';
        html += `
            <div class="connection-item">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="flex-grow-1">
                        <span class="badge bg-secondary me-2">${conn.protocol}</span>
                        <span class="badge ${stateClass} me-2">${conn.state}</span>
                        <code class="text-primary">${conn.local_ip}:${conn.local_port}</code>
                        <i class="bi bi-arrow-right mx-2 text-muted"></i>
                        <code class="text-success">${conn.remote_ip}:${conn.remote_port}</code>
                        ${conn.process ? `<span class="badge bg-info ms-2"><i class="bi bi-cpu"></i> ${escapeHtml(conn.process)}</span>` : ''}
                    </div>
                    <button class="btn btn-danger btn-sm kill-connection-btn" style="border-radius: 8px;" onclick="killConnection('${conn.remote_ip}', '${conn.remote_port}', '${conn.protocol}')">
                        <i class="bi bi-x-lg"></i> <?php echo $lang3302; ?>
                    </button>
                </div>
            </div>
        `;
    });
    $(containerId).html(html);
}

function killConnection(ip, port, protocol) {
    if (confirm(`<?php echo $lang3303; ?> ${ip}:${port}?`)) {
        showLoading();
        
        const headers = {
            'Content-Type': 'application/x-www-form-urlencoded'
        };
        if (window.apiConfig && window.apiConfig.apiKey) {
            headers['X-API-Key'] = window.apiConfig.apiKey;
        }
        
        $.ajax({
            url: url + 'firewall_api.php?action=kill_connection',
            method: 'POST',
            data: { ip: ip, port: port, protocol: protocol },
            headers: headers,
            success: function(response) {
                hideLoading();
                if (response.success) {
                    showToast(`<?php echo $lang3304; ?> ${ip}:${port} <?php echo $lang3305; ?>`, 'success');
                    refreshConnections();
                } else {
                    showToast(response.error, 'error');
                }
            },
            error: function(xhr, status, error) {
                hideLoading();
                console.error('Kill connection error:', error);
                showToast('<?php echo $lang3306; ?> ' + error, 'error');
            }
        });
    }
}

// Logs
function refreshLogs() {
    const lines = $('#logLinesSelect').val();
    const filter = $('#logFilter').val();
    const typeFilter = $('#logTypeFilter').val();
    
    showLoading();
    
    const headers = {};
    if (window.apiConfig && window.apiConfig.apiKey) {
        headers['X-API-Key'] = window.apiConfig.apiKey;
    }
    
    $.ajax({
        url: url + `firewall_api.php?action=get_logs&lines=${lines}&filter=${encodeURIComponent(filter)}`,
        method: 'GET',
        headers: headers,
        success: function(response) {
            hideLoading();
            if (response.success) {
                if (response.data.error) {
                    $('#logsList').html(`<div class="alert alert-warning m-3">${response.data.error}</div>`);
                    return;
                }
                currentLogs = response.data;
                let filtered = typeFilter === 'all' ? currentLogs : currentLogs.filter(log => log.action === typeFilter);
                renderLogs(filtered);
            } else {
                $('#logsList').html('<div class="text-center text-danger py-4"><?php echo $lang3307; ?></div>');
            }
        },
        error: function(xhr, status, error) {
            hideLoading();
            console.error('Refresh logs error:', error);
            $('#logsList').html('<div class="text-center text-danger py-4"><?php echo $lang3308; ?> ' + error + '</div>');
        }
    });
}

function renderLogs(logs) {
    if (logs.length === 0) {
        $('#logsList').html(`<div class="empty-state"><i class="bi bi-file-text"></i><p class="text-muted"><?php echo $lang3309; ?></p></div>`);
        return;
    }
    
    let html = '';
    logs.forEach(log => {
        let logClass = 'log-entry';
        let icon = '';
        if (log.action === 'ALLOW') {
            logClass += ' log-allow';
            icon = '<i class="bi bi-check-circle-fill text-success me-2"></i>';
        } else if (log.action === 'BLOCK') {
            logClass += ' log-block';
            icon = '<i class="bi bi-ban text-danger me-2"></i>';
        } else {
            logClass += ' log-limit';
            icon = '<i class="bi bi-clock text-warning me-2"></i>';
        }
        
        html += `
            <div class="${logClass}">
                <div class="d-flex flex-wrap gap-2 align-items-start">
                    ${icon}
                    <span class="text-muted small">[${log.timestamp}]</span>
                    <span class="fw-bold">${log.action}:</span>
                    <span class="small">${escapeHtml(log.details)}</span>
                </div>
            </div>
        `;
    });
    $('#logsList').html(html);
}

function clearLogFilter() {
    $('#logFilter').val('');
    $('#logTypeFilter').val('all');
    refreshLogs();
}

function exportLogs() {
    let text = '';
    currentLogs.forEach(log => {
        text += `[${log.timestamp}] ${log.action}: ${log.details}\n`;
    });
    const blob = new Blob([text], { type: 'text/plain' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = `ufw_logs_${new Date().toISOString().slice(0,19)}.txt`;
    link.click();
    URL.revokeObjectURL(link.href);
}

// Settings
function loadSettings() {
    const headers = {};
    if (window.apiConfig && window.apiConfig.apiKey) {
        headers['X-API-Key'] = window.apiConfig.apiKey;
    }
    
    $.ajax({
        url: url + 'firewall_api.php?action=get_status',
        method: 'GET',
        headers: headers,
        success: function(response) {
            if (response.success) {
                const status = response.data;
                $('#defaultIncoming').val(status.default_incoming);
                $('#defaultOutgoing').val(status.default_outgoing);
                $('#defaultRouted').val(status.default_routed);
                $('#loggingLevel').val(status.logging);
                
                $('#ufwInfo').html(`
                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-box"><strong><?php echo $lang3310; ?></strong> ${status.active ? 'Active' : 'Inactive'}</div>
                            <div class="info-box"><strong><?php echo $lang3311; ?></strong> ${status.logging}</div>
                            <div class="info-box"><strong><?php echo $lang3312; ?></strong> ${status.default_incoming}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-box"><strong><?php echo $lang3313; ?></strong> ${status.default_outgoing}</div>
                            <div class="info-box"><strong><?php echo $lang3314; ?></strong> ${status.default_routed}</div>
                        </div>
                    </div>
                `);
            }
        },
        error: function(xhr, status, error) {
            console.error('Load settings error:', error);
            $('#ufwInfo').html('<div class="text-danger"><?php echo $lang3315; ?></div>');
        }
    });
}

// App Rules
function openAddAppRuleModal() {
    $('#appSelect').html('<option value=""><?php echo $lang3316; ?></option>');
    $('#appRuleModal').modal('show');
    
    const headers = {};
    if (window.apiConfig && window.apiConfig.apiKey) {
        headers['X-API-Key'] = window.apiConfig.apiKey;
    }
    
    $.ajax({
        url: url + 'firewall_api.php?action=get_app_profiles',
        method: 'GET',
        headers: headers,
        success: function(response) {
            if (response.success && response.data.length > 0) {
                let options = '<option value=""><?php echo $lang3317; ?></option>';
                response.data.forEach(app => {
                    options += `<option value="${escapeHtml(app)}">${escapeHtml(app)}</option>`;
                });
                $('#appSelect').html(options);
            } else {
                $('#appSelect').html('<option value=""><?php echo $lang3318; ?></option>');
            }
        },
        error: function(xhr, status, error) {
            console.error('Load app profiles error:', error);
            $('#appSelect').html('<option value=""><?php echo $lang3319; ?></option>');
        }
    });
}

function addAppRule() {
    const appName = $('#appSelect').val();
    const action = $('#appAction').val();
    const ipVersion = $('#appIpVersion').val();
    
    if (!appName) {
        showToast('<?php echo $lang3320; ?>', 'error');
        return;
    }
    
    showLoading();
    
    const headers = {
        'Content-Type': 'application/x-www-form-urlencoded'
    };
    if (window.apiConfig && window.apiConfig.apiKey) {
        headers['X-API-Key'] = window.apiConfig.apiKey;
    }
    
    $.ajax({
        url: url + 'firewall_api.php?action=add_rule_by_app',
        method: 'POST',
        data: { app_name: appName, action: action, ip_version: ipVersion },
        headers: headers,
        success: function(response) {
            hideLoading();
            if (response.success) {
                showToast(`<?php echo $lang3321; ?> ${appName} <?php echo $lang3322; ?> (${ipVersion})`, 'success');
                $('#appRuleModal').modal('hide');
                refreshRules();
            } else {
                showToast(response.error, 'error');
            }
        },
        error: function(xhr, status, error) {
            hideLoading();
            console.error('Add app rule error:', error);
            showToast('<?php echo $lang3323; ?> ' + error, 'error');
        }
    });
}

// Quick Actions
function quickAddRule(port, serviceName) {
    $('#rulePort').val(port);
    $('#ruleComment').val(serviceName);
    $('#ruleDirection').val('in');
    $('#ruleAction').val('allow');
    $('#ruleProtocol').val('tcp');
    $('#ruleFrom').val('');
    $('#ipVersion').val('both');
    $('#ruleId').val('');
    $('#ruleModalTitle').html(`<i class="bi bi-plus-circle me-2"></i><?php echo $lang3324; ?> ${serviceName} (port ${port})`);
    $('#ruleModal').modal('show');
}

function quickBlockIP() {
    $('#blockIP').val('');
    $('#blockPort').val('');
    $('#blockComment').val('');
    $('#blockIpVersion').val('both');
    $('#blockIPModal').modal('show');
}

function blockIP() {
    const ip = $('#blockIP').val();
    const port = $('#blockPort').val();
    const comment = $('#blockComment').val();
    const ipVersion = $('#blockIpVersion').val();
    
    if (!ip) {
        showToast('<?php echo $lang3325; ?>', 'error');
        return;
    }
    
    const data = {
        direction: 'in',
        action: 'deny',
        protocol: 'any',
        from: ip,
        comment: comment || `Blocked IP ${ip}`,
        ip_version: ipVersion,
        port: port || '1:65535'
    };
    if (port) data.protocol = 'tcp';
    
    showLoading();
    
    const headers = {
        'Content-Type': 'application/json'
    };
    if (window.apiConfig && window.apiConfig.apiKey) {
        headers['X-API-Key'] = window.apiConfig.apiKey;
    }
    
    $.ajax({
        url: url + 'firewall_api.php?action=add_rule',
        method: 'POST',
        headers: headers,
        data: JSON.stringify(data),
        success: function(response) {
            hideLoading();
            if (response.success) {
                showToast(`<?php echo $lang3326; ?> ${ip} <?php echo $lang3327; ?>`, 'success');
                $('#blockIPModal').modal('hide');
                refreshRules();
            } else {
                showToast(response.error, 'error');
            }
        },
        error: function(xhr, status, error) {
            hideLoading();
            console.error('Block IP error:', error);
            showToast('<?php echo $lang3328; ?> ' + error, 'error');
        }
    });
}

// Form handlers
$('#defaultPoliciesForm').on('submit', function(e) {
    e.preventDefault();
    showLoading();
    
    const headers = {
        'Content-Type': 'application/x-www-form-urlencoded'
    };
    if (window.apiConfig && window.apiConfig.apiKey) {
        headers['X-API-Key'] = window.apiConfig.apiKey;
    }
    
    $.ajax({
        url: url + 'firewall_api.php?action=set_default_policy',
        method: 'POST',
        data: { incoming: $('#defaultIncoming').val(), outgoing: $('#defaultOutgoing').val(), routed: $('#defaultRouted').val() },
        headers: headers,
        success: function(response) {
            hideLoading();
            if (response.success) {
                showToast('<?php echo $lang3329; ?>', 'success');
                loadSettings();
            } else {
                showToast(response.error, 'error');
            }
        },
        error: function(xhr, status, error) {
            hideLoading();
            console.error('Update policies error:', error);
            showToast('<?php echo $lang3330; ?> ' + error, 'error');
        }
    });
});

$('#loggingForm').on('submit', function(e) {
    e.preventDefault();
    showLoading();
    
    const headers = {
        'Content-Type': 'application/x-www-form-urlencoded'
    };
    if (window.apiConfig && window.apiConfig.apiKey) {
        headers['X-API-Key'] = window.apiConfig.apiKey;
    }
    
    $.ajax({
        url: url + 'firewall_api.php?action=set_logging',
        method: 'POST',
        data: { level: $('#loggingLevel').val() },
        headers: headers,
        success: function(response) {
            hideLoading();
            if (response.success) {
                showToast('<?php echo $lang3331; ?>', 'success');
                loadSettings();
            } else {
                showToast(response.error, 'error');
            }
        },
        error: function(xhr, status, error) {
            hideLoading();
            console.error('Update logging error:', error);
            showToast('<?php echo $lang3332; ?> ' + error, 'error');
        }
    });
});

// Event listeners
$('#logLinesSelect, #logTypeFilter').on('change', () => refreshLogs());
let logFilterTimeout;
$('#logFilter').on('keyup', () => {
    clearTimeout(logFilterTimeout);
    logFilterTimeout = setTimeout(() => refreshLogs(), 500);
});
$('#ruleSearch, #filterDirection, #filterAction').on('keyup change', () => filterRules());
$('#connectionSearch').on('keyup', () => filterAndRenderConnections());

// Tab change handlers
$('#firewallTabs button').on('shown.bs.tab', function(e) {
    const target = $(e.target).attr('data-bs-target');
    if (target === '#connectionsTab') refreshConnections();
    else if (target === '#logsTab') refreshLogs();
});

// Initial load
$(document).ready(function() {
    loadUfwStatus();
    refreshRules();
    refreshConnections();
    refreshLogs();
    loadSettings();
    
    setInterval(() => { if ($('#connectionsTab').hasClass('active')) refreshConnections(); }, 5000);
    setInterval(() => { if ($('#logsTab').hasClass('active')) refreshLogs(); }, 5000);
    setInterval(() => loadUfwStatus(), 30000);
});
</script>
</body>
</html>