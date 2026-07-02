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
    <title>Mini-B - Plugins Manager</title>
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
            flex-wrap: wrap;
            gap: 12px;
        }

        .widget-header-left {
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
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

        .search-box {
            position: relative;
            min-width: 250px;
        }

        .search-box input {
            padding: 8px 12px 8px 36px;
            border-radius: 10px;
            border: 1px solid #dee2e6;
            font-size: 14px;
            width: 100%;
            transition: all 0.2s;
        }

        .search-box input:focus {
            outline: none;
            border-color: #007aff;
            box-shadow: 0 0 0 2px rgba(0,122,255,0.1);
        }

        .search-box i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
            font-size: 14px;
        }

        .widget-body {
            padding: 16px;
        }

        .nav-tabs-custom {
            border-bottom: 2px solid #e9ecef;
            margin-bottom: 20px;
        }

        .nav-tabs-custom .nav-link {
            border: none;
            color: #6c757d;
            font-weight: 500;
            padding: 10px 20px;
            transition: all 0.2s;
        }

        .nav-tabs-custom .nav-link:hover {
            color: #007aff;
            border: none;
        }

        .nav-tabs-custom .nav-link.active {
            color: #007aff;
            border-bottom: 2px solid #007aff;
            background: transparent;
        }

        .plugin-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            transition: all 0.3s;
            border: 1px solid #e9ecef;
            height: 100%;
            position: relative;
        }

        .plugin-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.1);
        }

        .plugin-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            margin-bottom: 16px;
        }

        .plugin-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 8px;
            color: #1c1c1e;
        }

        .plugin-version {
            font-size: 12px;
            color: #6c757d;
            margin-bottom: 8px;
        }
		
		.plugin-multinet {
            font-size: 12px;
            color: #6a757d;
            margin-bottom: 8px;
        }

        .plugin-description {
            font-size: 13px;
            color: #6c757d;
            line-height: 1.5;
            margin-bottom: 16px;
            max-height: 60px;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
        }

        .plugin-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 16px;
            font-size: 11px;
        }

        .plugin-meta-item {
            background: #f8f9fa;
            padding: 4px 8px;
            border-radius: 6px;
            color: #495057;
        }

        .plugin-meta-item i {
            margin-right: 4px;
            font-size: 10px;
        }

        .plugin-actions {
            display: flex;
            gap: 8px;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid #e9ecef;
            flex-wrap: wrap;
            align-items: center;
        }

        .plugin-status {
            position: absolute;
            top: 16px;
            right: 16px;
        }

        .status-badge {
            font-size: 10px;
            padding: 4px 8px;
            border-radius: 20px;
            font-weight: 500;
        }

        .status-enabled {
            background: #d4edda;
            color: #155724;
        }

        .status-disabled {
            background: #f8d7da;
            color: #721c24;
        }

        /* Toggle Switch */
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 50px;
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
            background-color: #ccc;
            transition: 0.3s;
            border-radius: 24px;
        }

        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: 0.3s;
            border-radius: 50%;
        }

        input:checked + .toggle-slider {
            background-color: #28a745;
        }

        input:checked + .toggle-slider:before {
            transform: translateX(26px);
        }

        .toggle-loading {
            opacity: 0.6;
            pointer-events: none;
        }

        .toggle-loading .toggle-slider {
            cursor: not-allowed;
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

        .dropzone {
            border: 2px dashed #dee2e6;
            border-radius: 12px;
            padding: 40px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: #f8f9fa;
        }

        .dropzone:hover, .dropzone.dragover {
            border-color: #007aff;
            background: #e3f2fd;
        }

        .dropzone i {
            font-size: 48px;
            color: #6c757d;
            margin-bottom: 16px;
        }

        .grid-view {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 24px;
        }

        .repository-item {
            background: white;
            border-radius: 12px;
            padding: 16px;
            border: 1px solid #e9ecef;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
        }

        .repository-item:hover {
            background: #f8f9fa;
            border-color: #007aff;
        }

        .repository-info {
            flex: 1;
        }

        .repository-name {
            font-weight: 600;
            font-size: 16px;
            margin-bottom: 4px;
        }

        .repository-meta {
            font-size: 12px;
            color: #6c757d;
        }

        .repository-meta i {
            margin-right: 4px;
        }

        .repository-description {
            font-size: 12px;
            color: #6c757d;
            margin-top: 8px;
            max-height: 36px;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }

        .no-results {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }

        .no-results i {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        /* Loading overlay for cards */
        .plugin-card.toggling {
            opacity: 0.6;
            pointer-events: none;
        }

        @media (max-width: 768px) {
            .grid-view {
                grid-template-columns: 1fr;
            }
            
            .main-content {
                padding: 20px;
            }
            
            .repository-item {
                flex-direction: column;
                gap: 12px;
            }
            
            .repository-actions {
                width: 100%;
                display: flex;
                justify-content: center;
            }

            .widget-header {
                flex-direction: column;
                align-items: stretch;
            }

            .widget-header-left {
                justify-content: space-between;
            }

            .search-box {
                width: 100%;
            }
        }

        /* Repository details modal */
        .repo-details-icon {
            width: 80px;
            height: 80px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            margin: 0 auto 16px;
        }

        .repo-details-full-description {
            white-space: pre-wrap;
            word-wrap: break-word;
            line-height: 1.6;
        }
		
		.repository-name {
			font-weight: 600;
			font-size: 16px;
			margin-bottom: 4px;
			display: flex;
			align-items: center;
			flex-wrap: wrap;
			gap: 8px;
		}

		.installed-badge .badge {
			font-size: 10px;
			padding: 4px 8px;
			font-weight: 500;
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
	<i class="fas fa-puzzle-piece"></i> Plugins Manager
        <div class="host-selector" style="margin-left: 20px;">
            <select id="hostSelector" style="background: rgba(255,255,255,0.9); border: 1px solid #ddd; border-radius: 20px; padding: 6px 30px 6px 15px; font-size: 14px; cursor: pointer;">
                <option value=""><?php echo $lang12; ?></option>
            </select>
        </div>
        <i class="fas fa-sync-alt refresh-btn text-muted" onclick="refreshAllData()" title="<?php echo $lang3985; ?>"></i>
    </div>
</div>

<div class="app-container">
    <?php echo $menu; ?>
    
    <main class="main-content">
        <div id="alertContainer"></div>
        
        <div class="widget">
            <div class="widget-header">
                <div class="widget-header-left">
                    <h3><i class="fas fa-puzzle-piece"></i> <?php echo $lang3986; ?></h3>
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="searchInput" placeholder="<?php echo $lang3987; ?>" autocomplete="off">
                    </div>
                </div>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#uploadPluginModal">
                    <i class="fas fa-upload"></i> <?php echo $lang3988; ?>
                </button>
            </div>
            <div class="widget-body">
                <ul class="nav nav-tabs-custom" id="pluginTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="installed-tab" data-bs-toggle="tab" data-bs-target="#installed" type="button" role="tab">
                            <i class="fas fa-check-circle"></i> <?php echo $lang3989; ?>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="repository-tab" data-bs-toggle="tab" data-bs-target="#repository" type="button" role="tab">
                            <i class="fas fa-database"></i> <?php echo $lang3990; ?>
                        </button>
                    </li>
                </ul>
                
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="installed" role="tabpanel">
                        <div id="pluginsContainer" class="grid-view">
                            <div class="text-center py-5 col-12">
                                <div class="loading-spinner-sm"></div> <?php echo $lang3991; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="tab-pane fade" id="repository" role="tabpanel">
                        <div id="repositoryContainer">
                            <div class="text-center py-5 col-12">
                                <div class="loading-spinner-sm"></div> <?php echo $lang3992; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Modal: Upload Plugin -->
<div class="modal fade" id="uploadPluginModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-upload"></i> <?php echo $lang3994; ?></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
				<form id="uploadPluginForm" enctype="multipart/form-data">
					<div class="mb-3">
						<label for="pluginFileInput" class="form-label"><?php echo $lang3995; ?></label>
						<input type="file" class="form-control" id="pluginFileInput" name="plugin_file" accept=".zip">
						<small class="text-muted"><?php echo $lang3996; ?></small>
					</div>
					<div id="uploadProgress" class="mt-3" style="display: none;">
						<div class="progress">
							<div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 0%">0%</div>
						</div>
					</div>
				</form>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang3997; ?></button>
				<button type="button" class="btn btn-primary" id="uploadPluginBtn"><?php echo $lang3998; ?></button>
			</div>
        </div>
    </div>
</div>

<!-- Modal: Install Confirmation -->
<div class="modal fade" id="installConfirmModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-download"></i> <?php echo $lang3999; ?></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p><?php echo $lang4000; ?> <strong id="installPluginName"></strong>?</p>
                <div class="form-check mt-3">
                    <input class="form-check-input" type="checkbox" id="deleteAfterInstall">
                    <label class="form-check-label" for="deleteAfterInstall">
                        <?php echo $lang4001; ?>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang4002; ?></button>
                <button type="button" class="btn btn-success" id="confirmInstallBtn"><?php echo $lang4003; ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Plugin Details (Installed) -->
<div class="modal fade" id="pluginDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" id="pluginModalHeader">
                <h5 class="modal-title"><i class="fas fa-info-circle"></i> <?php echo $lang4004; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="pluginDetailsContent">
                <!-- Dynamic content -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang4005; ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Repository Plugin Details -->
<div class="modal fade" id="repoDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-info-circle"></i> <?php echo $lang4006; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="repoDetailsContent">
                <!-- Dynamic content -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang4007; ?></button>
                <button type="button" class="btn btn-success" id="installFromDetailsBtn"><?php echo $lang4008; ?></button>
            </div>
        </div>
    </div>
</div>

<script src="lib/jquery-3.6.0-master/dist/jquery.min.js"></script>
<script src="lib/bootstrap-5.3.8-dist/js/bootstrap.bundle.min.js"></script>
<script>
const url = "<?php echo $current_host_id == 1 ? '/api/' : rtrim($host_url, '/') . '/'; ?>";
let currentInstallFilename = null;
let currentRepoPluginData = null;
let allInstalledPlugins = [];
let allRepoPlugins = [];
let activeToggling = new Map();

function showAlert(message, type = 'success') {
    const alertHtml = `<div class="alert alert-${type} alert-dismissible fade show" role="alert">
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'}"></i> 
        ${escapeHtml(message)}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>`;
    $('#alertContainer').append(alertHtml);
    setTimeout(() => $('.alert').fadeOut(500, function() { $(this).remove(); }), 5000);
}

function escapeHtml(str) { 
    if (!str) return ''; 
    return String(str).replace(/[&<>]/g, function(m) { 
        return { '&':'&amp;', '<':'&lt;', '>':'&gt;' }[m]; 
    }); 
}

function truncateText(text, maxLength = 100) {
    if (!text) return '';
    if (text.length <= maxLength) return text;
    return text.substring(0, maxLength) + '...';
}

function stripHtml(html) {
    if (!html) return '';
    const tmp = document.createElement('div');
    tmp.innerHTML = html;
    return tmp.textContent || tmp.innerText || '';
}

async function apiCall(action, method = 'GET', data = null, isFormData = false) {
    let fullUrl = `${url}plugins_api.php?action=${action}`;
    let options = { 
        method: method, 
        headers: {}
    };
    
    if (window.apiConfig && window.apiConfig.apiKey) {
        options.headers['X-API-Key'] = window.apiConfig.apiKey;
    }
    
    if (method === 'POST' && data) {
        if (isFormData) {
            options.body = data;
        } else {
            options.headers['Content-Type'] = 'application/x-www-form-urlencoded';
            options.body = new URLSearchParams(data);
        }
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

function filterPlugins(plugins, searchTerm) {
    if (!searchTerm) return plugins;
    const term = searchTerm.toLowerCase();
    return plugins.filter(plugin => 
        (plugin.name && plugin.name.toLowerCase().includes(term)) ||
        (plugin.description && stripHtml(plugin.description).toLowerCase().includes(term)) ||
        (plugin.author && plugin.author.toLowerCase().includes(term)) ||
        (plugin.version && plugin.version.toLowerCase().includes(term))
    );
}

function renderInstalledPlugins() {
    const searchTerm = $('#searchInput').val();
    const filtered = filterPlugins(allInstalledPlugins, searchTerm);
    
    if (filtered.length === 0) {
        $('#pluginsContainer').html(`
            <div class="no-results col-12">
                <i class="fas fa-search"></i>
                <p><?php echo $lang4009; ?> "${escapeHtml(searchTerm)}"</p>
            </div>
        `);
        return;
    }
    
    let html = '';
    filtered.forEach(plugin => {
        let statusClass = plugin.enabled ? 'status-enabled' : 'status-disabled';
        let statusText = plugin.enabled ? '<?php echo $lang4010; ?>' : '<?php echo $lang4011; ?>';
        let description = stripHtml(plugin.description || '<?php echo $lang4012; ?>');
        let truncatedDesc = truncateText(description, 100);
        let isTogglingThis = activeToggling.has(plugin.folder);
        
        html += `
            <div class="plugin-card" data-plugin-card="${escapeHtml(plugin.folder)}">
                <div class="plugin-status">
                    <span class="status-badge ${statusClass}">
                        <i class="fas fa-${plugin.enabled ? 'check' : 'times'}"></i> ${statusText}
                    </span>
                </div>
                <div class="plugin-icon" style="background: ${plugin.icon_bg || 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)'}">
                    <i class="${plugin.icon || 'fas fa-puzzle-piece'}"></i>
                </div>
                <div class="plugin-title">${escapeHtml(plugin.name)}</div>
                <div class="plugin-version">
                    <i class="fas fa-code-branch"></i> v${escapeHtml(plugin.version || '1.0.0')}
                </div>
                <div class="plugin-description">
                    ${escapeHtml(truncatedDesc)}
                </div>
                <div class="plugin-meta">
                    <span class="plugin-meta-item"><i class="fas fa-user"></i> ${escapeHtml(plugin.author || 'Unknown')}</span>
                    <span class="plugin-meta-item"><i class="fas fa-signal"></i> Multinet: ${escapeHtml(plugin.multinet || 'Unknown')}</span>
                    <span class="plugin-meta-item"><i class="fas fa-calendar"></i> ${escapeHtml(plugin.release_date || 'Unknown')}</span>
                </div>
                <div class="plugin-actions">
                    <label class="toggle-switch" data-plugin="${escapeHtml(plugin.folder)}">
                        <input type="checkbox" class="plugin-toggle" ${plugin.enabled ? 'checked' : ''} ${isTogglingThis ? 'disabled' : ''}>
                        <span class="toggle-slider"></span>
                    </label>
                    <button class="btn btn-info btn-sm" onclick="showPluginDetails('${escapeHtml(plugin.folder)}')" ${isTogglingThis ? 'disabled' : ''}>
                        <i class="fas fa-info-circle"></i> <?php echo $lang4013; ?>
                    </button>
                    <button class="btn btn-danger btn-sm" onclick="deletePlugin('${escapeHtml(plugin.folder)}')" ${isTogglingThis ? 'disabled' : ''}>
                        <i class="fas fa-trash"></i> <?php echo $lang4014; ?>
                    </button>
                </div>
            </div>
        `;
    });
    $('#pluginsContainer').html(html);
    
    // Attach toggle event listeners
    $('.plugin-toggle').off('change').on('change', function() {
        const $toggle = $(this);
        const $label = $toggle.closest('.toggle-switch');
        const pluginName = $label.data('plugin');
        
        // Prevent if already toggling
        if (activeToggling.has(pluginName)) {
            // Revert the toggle state
            $toggle.prop('checked', !$toggle.is(':checked'));
            return;
        }
        
        const isChecked = $toggle.is(':checked');
        togglePluginStatus(pluginName, isChecked, $toggle);
    });
}

async function togglePluginStatus(pluginName, enable, $toggle) {
    // Mark as toggling
    if (activeToggling.has(pluginName)) {
        $toggle.prop('checked', !enable);
        return;
    }
    
    activeToggling.set(pluginName, true);
    
    const $card = $(`.plugin-card[data-plugin-card="${pluginName}"]`);
    $card.addClass('toggling');
    $toggle.prop('disabled', true);
    
    const $label = $toggle.closest('.toggle-switch');
    $label.addClass('toggle-loading');
    
    const action = enable ? 'enable_plugin' : 'disable_plugin';
    
    try {
        let result = await apiCall(action, 'POST', { plugin: pluginName });
        
        if (result.success) {
            showAlert(result.message, 'success');
            
            await new Promise(resolve => setTimeout(resolve, 300));
            
            await loadPlugins(true);
            
            await refreshMenu();
            
            activeToggling.delete(pluginName);
            
            renderInstalledPlugins();
            
        } else {
            showAlert(result.message || `<?php echo $lang4015; ?> ${enable ? '<?php echo $lang4016; ?>' : '<?php echo $lang4017; ?>'} <?php echo $lang4018; ?>`, 'danger');
            $toggle.prop('checked', !enable);
            $card.removeClass('toggling');
            $toggle.prop('disabled', false);
            $label.removeClass('toggle-loading');
            activeToggling.delete(pluginName);
        }
    } catch (error) {
        console.error('Toggle error:', error);
        showAlert(`<?php echo $lang4019; ?> ${error.message}`, 'danger');
        $toggle.prop('checked', !enable);
        $card.removeClass('toggling');
        $toggle.prop('disabled', false);
        $label.removeClass('toggle-loading');
        activeToggling.delete(pluginName);
    }
}


async function refreshMenu() {
    try {
        let result = await apiCall('get_menu');
        if (result.success && result.data) {
            const $newMenu = $(result.data);
            const newNavHtml = $newMenu.find('.sidebar-nav').html();
            $('.sidebar-nav').html(newNavHtml);
            
            window.toggleMenuGroup = function(header) {
                var group = header.parentElement;
                var content = group.querySelector(".menu-group-content");
                var arrow = header.querySelector(".group-arrow");
                var groupId = group.dataset.groupId;
                
                if (content.style.display === "none") {
                    content.style.display = "block";
                    arrow.classList.remove("fa-chevron-right");
                    arrow.classList.add("fa-chevron-down");
                    saveGroupState(groupId, true);
                } else {
                    content.style.display = "none";
                    arrow.classList.remove("fa-chevron-down");
                    arrow.classList.add("fa-chevron-right");
                    saveGroupState(groupId, false);
                }
            };
            
            document.querySelectorAll('.menu-group-header').forEach(function(header) {
                header.onclick = function() {
                    window.toggleMenuGroup(this);
                };
            });
        }
    } catch(e) {
        console.error('Failed to refresh menu:', e);
        //location.reload();
    }
}

async function loadPlugins(skipRender = false) {
    let result = await apiCall('get_plugins');
    if (result.success && result.data) {
        allInstalledPlugins = result.data;
        if (!skipRender) {
            renderInstalledPlugins();
        }
    } else {
        if (!skipRender) {
            $('#pluginsContainer').html(`
                <div class="text-center py-5 col-12">
                    <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
                    <p class="text-danger"><?php echo $lang4020; ?></p>
                </div>
            `);
        }
    }
}

function renderRepositoryPlugins() {
    const searchTerm = $('#searchInput').val();
    const filtered = filterPlugins(allRepoPlugins, searchTerm);
    
    if (filtered.length === 0) {
        $('#repositoryContainer').html(`
            <div class="no-results">
                <i class="fas fa-search"></i>
                <p><?php echo $lang4021; ?> "${escapeHtml(searchTerm)}"</p>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#uploadPluginModal">
                    <i class="fas fa-upload"></i> Upload plugin ZIP
                </button>
            </div>
        `);
        return;
    }
    
    let html = '<div class="repository-list">';
    filtered.forEach(plugin => {
        let description = stripHtml(plugin.description || 'No description');
        let truncatedDesc = truncateText(description, 80);
        
        let installStatusHtml = '';
        let installButtonDisabled = false;
        
        if (plugin.installed) {
            let statusColor = plugin.installed_enabled ? '#28a745' : '#dc3545';
            let statusText = plugin.installed_enabled ? '<?php echo $lang4023; ?>' : '<?php echo $lang4024; ?>';
            let versionMatch = !plugin.installed_version || plugin.installed_version === plugin.version;
            let versionWarning = versionMatch ? '' : `<span class="text-warning ms-1" title="<?php echo $lang4025; ?> ${plugin.installed_version}, <?php echo $lang4026; ?> ${plugin.version}"><i class="fas fa-exclamation-triangle"></i></span>`;
            
            installStatusHtml = `
                <div class="installed-badge ms-2" style="display: inline-block;">
                    <span class="badge" style="background: ${statusColor}; color: white;">
                        <i class="fas ${plugin.installed_enabled ? 'fa-check-circle' : 'fa-ban'}"></i> <?php echo $lang4027; ?> (${statusText})
                        ${versionWarning}
                    </span>
                </div>
            `;
            installButtonDisabled = true;
        }
        
        html += `
            <div class="repository-item" onclick="showRepoDetails('${escapeHtml(plugin.filename)}', event)">
                <div class="repository-info">
                    <div class="repository-name">
                        <i class="${plugin.icon || 'fas fa-puzzle-piece'}"></i> ${escapeHtml(plugin.name)}
                        ${installStatusHtml}
                    </div>
                    <div class="repository-meta">
                        <i class="fas fa-code-branch"></i> v${escapeHtml(plugin.version)} &nbsp;
                        <i class="fas fa-user"></i> ${escapeHtml(plugin.author)} &nbsp;
                        <i class="fas fa-file-archive"></i> ${escapeHtml(plugin.size)} &nbsp;
                        <i class="fas fa-calendar"></i> ${escapeHtml(plugin.modified)}
                    </div>
                    <div class="repository-description">
                        ${escapeHtml(truncatedDesc)}
                    </div>
                </div>
                <div class="repository-actions" onclick="event.stopPropagation()">
                    ${!plugin.installed ? 
                        `<button class="btn btn-success btn-sm me-2" onclick="confirmInstall('${escapeHtml(plugin.filename)}', '${escapeHtml(plugin.name)}')">
                            <i class="fas fa-download"></i> <?php echo $lang4028; ?>
                        </button>` :
                        `<button class="btn btn-secondary btn-sm me-2" disabled title="Plugin already installed">
                            <i class="fas fa-check"></i> <?php echo $lang4029; ?>
                        </button>
                        <a href="plugins.php" class="btn btn-info btn-sm me-2">
                            <i class="fas fa-cog"></i> <?php echo $lang4030; ?>
                        </a>`
                    }
                    <button class="btn btn-danger btn-sm" onclick="deleteRepositoryZip('${escapeHtml(plugin.filename)}', ${plugin.installed})" ${plugin.installed ? 'data-installed="true"' : ''}>
                        <i class="fas fa-trash"></i> <?php echo $lang4031; ?>
                    </button>
                </div>
            </div>
        `;
    });
    html += '</div>';
    $('#repositoryContainer').html(html);
}

async function loadRepository() {
    let result = await apiCall('get_repository');
    if (result.success && result.data) {
        allRepoPlugins = result.data;
        renderRepositoryPlugins();
    } else {
        $('#repositoryContainer').html(`
            <div class="text-center py-5 col-12">
                <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
                <p class="text-danger"><?php echo $lang4032; ?></p>
            </div>
        `);
    }
}

function showRepoDetails(filename, event) {
    event.stopPropagation();
    const plugin = allRepoPlugins.find(p => p.filename === filename);
    if (!plugin) return;
    
    currentRepoPluginData = plugin;
    
    let fullDescription = stripHtml(plugin.description || 'No description');
    if (plugin.info && plugin.info.description) {
        fullDescription = stripHtml(plugin.info.description);
    }
    
    let installStatusHtml = '';
    let installButtonDisabled = false;
    
    if (plugin.installed) {
        let statusColor = plugin.installed_enabled ? 'success' : 'danger';
        let statusText = plugin.installed_enabled ? '<?php echo $lang4033; ?>' : '<?php echo $lang4034; ?>';
        installStatusHtml = `
            <div class="alert alert-${statusColor} mt-3">
                <i class="fas ${plugin.installed_enabled ? 'fa-check-circle' : 'fa-ban'}"></i>
                <?php echo $lang4035; ?> (${statusText})
                ${plugin.installed_version !== plugin.version ? `<br><small class="text-warning"><?php echo $lang4036; ?> ${plugin.installed_version} <?php echo $lang4037; ?> ${plugin.version}</small>` : ''}
            </div>
        `;
        installButtonDisabled = true;
    }
    
    let html = `
        <div class="text-center">
            <div class="repo-details-icon" style="background: ${plugin.icon_bg || 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)'}">
                <i class="${plugin.icon || 'fas fa-puzzle-piece'}"></i>
            </div>
            <h3>${escapeHtml(plugin.name)}</h3>
            <p class="text-muted">v${escapeHtml(plugin.version)}</p>
        </div>
        ${installStatusHtml}
        <hr>
        <div class="row">
            <div class="col-md-6">
                <p><strong><i class="fas fa-user"></i> <?php echo $lang4038; ?></strong> ${escapeHtml(plugin.author)}</p>
                <p><strong><i class="fas fa-file-archive"></i> <?php echo $lang4039; ?></strong> ${escapeHtml(plugin.size)}</p>
                <p><strong><i class="fas fa-calendar"></i> <?php echo $lang4040; ?></strong> ${escapeHtml(plugin.modified)}</p>
            </div>
            <div class="col-md-6">
                <p><strong><i class="fas fa-signal"></i> <?php echo $lang4041; ?></strong> ${escapeHtml(plugin.info?.multinet || plugin.multinet || 'Unknown')}</p>
                <p><strong><i class="fab fa-php"></i> <?php echo $lang4042; ?></strong> ${escapeHtml(plugin.info?.php_version || 'Any')}</p>
                <p><strong><i class="fab fa-linux"></i> <?php echo $lang4043; ?></strong> ${escapeHtml(plugin.info?.os || 'Any')}</p>
            </div>
        </div>
        <hr>
        <div class="repo-details-full-description">
            <strong><i class="fas fa-align-left"></i> <?php echo $lang4044; ?></strong>
            <p class="mt-2">${escapeHtml(fullDescription)}</p>
        </div>
    `;
    
    $('#repoDetailsContent').html(html);
    $('#repoDetailsModal').modal('show');
    
    if (installButtonDisabled) {
        $('#installFromDetailsBtn').prop('disabled', true).text('<?php echo $lang4045; ?>');
    } else {
        $('#installFromDetailsBtn').prop('disabled', false).text('<?php echo $lang4046; ?>');
    }
}

async function deletePlugin(pluginName) {
    if (!confirm(`<?php echo $lang4047; ?> "${pluginName}"? <?php echo $lang4048; ?>`)) {
        return;
    }
    
    let result = await apiCall('delete_plugin', 'POST', { plugin: pluginName });
    if (result.success) {
        showAlert(result.message, 'success');
        await new Promise(resolve => setTimeout(resolve, 300));
        await loadPlugins();
        await refreshMenu();
        renderInstalledPlugins();
    } else {
        showAlert(result.message || '<?php echo $lang4049; ?>', 'danger');
    }
}

async function deleteRepositoryZip(filename, isInstalled = false) {
    let confirmMessage = `<?php echo $lang4050; ?> "${filename}" <?php echo $lang4051; ?>`;
    
    if (isInstalled) {
        confirmMessage = `<?php echo $lang4052; ?>\n\n<?php echo $lang4053; ?>\n\n${confirmMessage}`;
    }
    
    if (!confirm(confirmMessage)) {
        return;
    }
    
    let result = await apiCall('delete_repository_zip', 'POST', { filename: filename });
    if (result.success) {
        showAlert(result.message, 'success');
        await loadRepository();
    } else {
        showAlert(result.error || '<?php echo $lang4054; ?>', 'danger');
    }
}

function confirmInstall(filename, pluginName) {
    currentInstallFilename = filename;
    $('#installPluginName').text(pluginName);
    $('#installConfirmModal').modal('show');
}

async function installFromRepository(deleteAfter) {
    if (!currentInstallFilename) return;
    
    $('#installConfirmModal').modal('hide');
    showAlert('<?php echo $lang4055; ?>', 'info');
    
    let result = await apiCall('install_from_repository', 'POST', { 
        filename: currentInstallFilename,
        delete_after: deleteAfter ? 1 : 0
    });
    
    if (result.success) {
        showAlert(result.message, 'success');
        await new Promise(resolve => setTimeout(resolve, 500));
        await loadPlugins();
        await loadRepository();
        await refreshMenu();
        renderInstalledPlugins();
    } else {
        showAlert(result.error || '<?php echo $lang4056; ?>', 'danger');
    }
    
    currentInstallFilename = null;
}

async function showPluginDetails(pluginName) {
    let result = await apiCall('get_plugin', 'GET', { plugin: pluginName });
    if (result.success && result.data) {
        let p = result.data;
        let statusColor = p.enabled ? 'success' : 'danger';
        let statusText = p.enabled ? '<?php echo $lang4057; ?>' : '<?php echo $lang4058; ?>';
        let description = stripHtml(p.description || 'No description');
        
        let html = `
            <div class="row">
                <div class="col-md-4 text-center">
                    <div class="plugin-icon mx-auto mb-3" style="background: ${p.icon_bg || 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)'}; width: 80px; height: 80px; font-size: 40px;">
                        <i class="${p.icon || 'fas fa-puzzle-piece'}"></i>
                    </div>
                    <h4>${escapeHtml(p.name)}</h4>
                    <span class="badge bg-${statusColor}">${statusText}</span>
                </div>
                <div class="col-md-8">
                    <table class="table table-sm">
                        <tr><th width="35%"><?php echo $lang4059; ?></th><td>${escapeHtml(p.version || '1.0.0')}</td>
                        <tr><th><?php echo $lang4060; ?></th><td>${escapeHtml(p.author || 'Unknown')}</td>
                        <tr><th><?php echo $lang4061; ?></th><td>${escapeHtml(p.release_date || 'Unknown')}</td>
                        <tr><th><?php echo $lang4062; ?></th><td>${escapeHtml(p.php_version || 'Any')}</td>
                        <tr><th><?php echo $lang4063; ?></th><td>${escapeHtml(p.os || 'Any')}</td>
                        <tr><th><?php echo $lang4064; ?></th><td>${escapeHtml(p.multinet || 'Unknown')}</td>
                        <tr><th><?php echo $lang4065; ?></th><td>${escapeHtml(description)}</td>
                    </table>
                </div>
            </div>
        `;
        
        $('#pluginDetailsContent').html(html);
        $('#pluginDetailsModal').modal('show');
    } else {
        showAlert('<?php echo $lang4066; ?>', 'danger');
    }
}

async function uploadPlugin(file) {
    let formData = new FormData();
    formData.append('plugin_file', file);
    
    let result = await apiCall('upload_plugin', 'POST', formData, true);
    if (result.success) {
        showAlert(result.message, 'success');
        $('#uploadPluginModal').modal('hide');
        await loadRepository();
        $('#pluginFileInput').val('');
    } else {
        showAlert(result.error || 'Failed to upload plugin', 'danger');
    }
}

async function refreshAllData() {
    await loadPlugins();
    await loadRepository();
    showAlert('<?php echo $lang4068; ?>', 'success');
}

// Search handler with debounce
let searchTimeout;
$('#searchInput').on('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        const activeTab = document.querySelector('#pluginTabs .nav-link.active').getAttribute('data-bs-target');
        if (activeTab === '#installed') {
            renderInstalledPlugins();
        } else if (activeTab === '#repository') {
            renderRepositoryPlugins();
        }
    }, 300);
});

$(document).ready(function() {
    refreshAllData();
    setTimeout(function() { $('#applePreloader').fadeOut(500); }, 500);
    
    $('#uploadPluginBtn').on('click', function() {
        const fileInput = document.getElementById('pluginFileInput');
        if (fileInput && fileInput.files.length > 0) {
            uploadPlugin(fileInput.files[0]);
        } else {
            showAlert('<?php echo $lang4069; ?>', 'danger');
        }
    });
    
    $('#confirmInstallBtn').on('click', function() {
        const deleteAfter = $('#deleteAfterInstall').is(':checked');
        installFromRepository(deleteAfter);
    });
    
    $('#installFromDetailsBtn').on('click', function() {
        if (currentRepoPluginData) {
            $('#repoDetailsModal').modal('hide');
            confirmInstall(currentRepoPluginData.filename, currentRepoPluginData.name);
        }
    });
    
    $('#installed-tab').on('shown.bs.tab', function() {
        renderInstalledPlugins();
    });
    
    $('#repository-tab').on('shown.bs.tab', function() {
        renderRepositoryPlugins();
    });
});

// Save group state function for menu
function saveGroupState(groupId, isOpen) {
    var openGroups = getCookie("open_groups");
    var groups = openGroups ? openGroups.split(",") : [];
    
    if (isOpen) {
        if (!groups.includes(groupId)) {
            groups.push(groupId);
        }
    } else {
        groups = groups.filter(function(g) { return g !== groupId; });
    }
    
    document.cookie = "open_groups=" + groups.join(",") + "; path=/; max-age=31536000";
}

function getCookie(name) {
    var value = "; " + document.cookie;
    var parts = value.split("; " + name + "=");
    if (parts.length === 2) return parts.pop().split(";").shift();
    return "";
}
</script>
<script src="js/loader.js"></script>
</body>
</html>