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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Mini-B - Update Manager</title>
    <link href="lib/bootstrap-5.3.8-dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="lib/bootstrap-icons-1.11.0/bootstrap-icons.css">
    <!--<link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">-->
    <link rel="stylesheet" href="lib/fontawesome-free-6.7.2-web/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="css/loader.css">
    <link rel="shortcut icon" href="css/icon.ico" type="image/x-icon">
    <script src="js/hosts_load.js"></script>
    <script src="js/crt_checker.js"></script>
    <script>
	window.apiConfig = <?php echo json_encode($js_config); ?>;
	window.hostsList = <?php echo json_encode($hosts); ?>;
	window.currentHostId = <?php echo (int)$current_host_id; ?>;
	</script>
    
    <style>
        * { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        
        body { background: linear-gradient(135deg, #f5f7fa 0%, #e9eef3 100%); min-height: 100vh; }

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
        
        .content-header h1 {
            font-size: 28px;
            font-weight: 600;
            letter-spacing: -0.3px;
            background: linear-gradient(135deg, #1c1c1e, #3a3a3c);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 20px;
        }
        
        .apple-card {
            background: rgba(255, 255, 255, 0.96);
            backdrop-filter: blur(0px);
            border-radius: 20px;
            box-shadow: 0 8px 28px rgba(0, 0, 0, 0.08), 0 1px 2px rgba(0, 0, 0, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.5);
            transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            margin-bottom: 24px;
            overflow: hidden;
        }
        
        .card-header-apple {
            padding: 20px 24px;
            background: linear-gradient(135deg, #f8f9fa, #ffffff);
            border-bottom: 1px solid #e9ecef;
        }
        
        .card-header-apple h3 {
            font-size: 18px;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #1c1c1e;
        }
        
        .card-header-apple h3 i {
            color: #007aff;
            font-size: 1.3rem;
        }
        
        .card-body-apple {
            padding: 20px 24px;
        }
        
        .btn-apple {
            background: #007aff;
            border: none;
            border-radius: 12px;
            padding: 12px 20px;
            font-weight: 500;
            font-size: 15px;
            transition: all 0.2s ease;
            color: white;
        }
        
        .btn-apple:hover:not(:disabled) {
            background: #005fc1;
            transform: scale(0.98);
            color: white;
        }
        
        .btn-apple:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .btn-apple-outline {
            background: transparent;
            border: 1.5px solid #007aff;
            color: #007aff;
            border-radius: 12px;
            padding: 11px 20px;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        
        .btn-apple-outline:hover:not(:disabled) {
            background: #007aff10;
            transform: scale(0.98);
        }
        
        .btn-apple-success {
            background: #34c759;
        }
        
        .btn-apple-success:hover:not(:disabled) {
            background: #248a3d;
        }
        
        .info-box {
            background: #f8f9fa;
            border-radius: 16px;
            padding: 14px 18px;
            margin-bottom: 12px;
            border: 1px solid #e9ecef;
        }
        
        .status-box {
            background: #f8f9fa;
            border-radius: 16px;
            padding: 12px 18px;
            margin-bottom: 12px;
            border-left: 4px solid;
        }
        
        .status-box.success {
            border-left-color: #34c759;
        }
        
        .status-box.error {
            border-left-color: #ff3b30;
        }
        
        .status-box.warning {
            border-left-color: #ff9500;
        }
        
        .status-box i {
            margin-right: 8px;
        }
        
        .log-console {
            background: #1e1e1e;
            color: #d4d4d4;
            border-radius: 12px;
            padding: 16px;
            font-family: 'SF Mono', 'Monaco', monospace;
            font-size: 12px;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .log-line {
            padding: 4px 0;
            border-bottom: 1px solid #333;
            font-family: monospace;
            white-space: pre-wrap;
        }
        
        .log-line.error {
            color: #ff6b6b;
        }
        
        .log-line.success {
            color: #51cf66;
        }
        
        .log-line.info {
            color: #74c0fc;
        }
        
        .version-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }
        
        .version-current {
            background: #007aff20;
            color: #007aff;
        }
        
        .version-new {
            background: #34c75920;
            color: #248a3d;
        }
        
        .progress {
            height: 8px;
            border-radius: 10px;
            overflow: hidden;
        }
        
        .progress-bar {
            transition: width 0.3s ease;
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
        
        .refresh-btn {
            cursor: pointer;
            transition: transform 0.2s;
            font-size: 16px;
        }
        
        .refresh-btn:hover {
            transform: rotate(180deg);
        }
        
        .update-step {
            display: none;
        }
        
        .update-step.active {
            display: block;
        }
        
        .last-check-badge {
            font-size: 12px;
            color: #6c757d;
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px solid #e9ecef;
        }
        
        .status-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        
        .status-item {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
        }
        
        .status-badge {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
        }
        
        .status-badge.green { background: #34c759; }
        .status-badge.red { background: #ff3b30; }
        .status-badge.orange { background: #ff9500; }
        
        @media (max-width: 768px) {
            .status-grid {
                grid-template-columns: 1fr;
            }
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
        <h1><i class="fas fa-server"></i> Mini-B</h1>
    </div>
    <div class="top-bar-right">
	<i class="bi bi-cloud-upload"></i> Update Manager
        <div class="host-selector">
            <select id="hostSelector">
                <option value="">Loading...</option>
            </select>
        </div>
        <i class="fas fa-sync-alt refresh-btn text-muted" onclick="refreshAllData()" title="Refresh"></i>
    </div>
</div>

<div class="app-container">
    <?php echo $menu; ?>
    
    <main class="main-content">
        <div id="alertContainer"></div>
        
        <!-- Основная таблица результатов (Update Info Card) -->
        <div class="row">
		
            <div class="col-md-8">
			<div class="info-box mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div>
                                    <i class="bi bi-tag me-2 text-primary"></i>
                                    <strong>Current Version:</strong>
                                    <span id="currentVersion" class="ms-2">Loading...</span>
                                </div>
                                <div>
                                    <i class="bi bi-package me-2 text-primary"></i>
                                    <strong>Type:</strong>
                                    <span id="typePro" class="ms-2">Loading...</span>
                                </div>
                            </div>
                            <div class="mt-2">
                                <i class="bi bi-hdd-stack me-2 text-primary"></i>
                                <strong>System:</strong>
                                <span id="systemInfo" class="ms-2">Loading...</span>
                            </div>
                        </div>
                <!-- Update Info Card -->
                <div class="apple-card" id="updateInfoCard" style="display: none;">
                    <div class="card-header-apple">
                        <h3><i class="bi bi-gift-fill"></i> Update Available</h3>
                    </div>
                    <div class="card-body-apple">
                        <div class="info-box">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <strong>New Version:</strong>
                                <span id="newVersion" class="version-badge version-new"></span>
                            </div>
                            <div class="mb-2">
                                <strong>Release Date:</strong>
                                <span id="releaseDate"></span>
                            </div>
                            <div>
                                <strong>What's New:</strong>
                                <div id="versionInfo" class="mt-2 small"></div>
                            </div>
                        </div>
                        
                        <button type="button" class="btn btn-success w-100" id="updateNowBtn" onclick="startUpdate()">
                            <i class="bi bi-cloud-download me-2"></i>Update Now
                        </button>
                    </div>
                </div>
                
                <!-- Update Process Card -->
                <div class="apple-card" id="updateProcessCard" style="display: none;">
                    <div class="card-header-apple">
                        <h3><i class="bi bi-arrow-repeat spin"></i> Update in Progress</h3>
                    </div>
                    <div class="card-body-apple">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span>Update Progress</span>
                                <span id="progressPercent">0%</span>
                            </div>
                            <div class="progress">
                                <div id="updateProgress" class="progress-bar bg-success" style="width: 0%"></div>
                            </div>
                        </div>
                        
                        <div id="updateLog" class="log-console">
                            <div class="log-line info">Waiting to start...</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Виджет проверки -->
            <div class="col-md-4">
                <div class="apple-card">
                    <div class="card-header-apple">
                        <h3><i class="bi bi-cloud-check"></i> Check for Updates</h3>
                    </div>
                    <div class="card-body-apple">
                        <!-- System Status -->
                        
                        
                        <!-- Update Server Status -->
                        <div id="serverStatusBox" class="status-box mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <strong><i class="bi bi-globe2"></i> Update Server Status</strong>
                                <i class="bi bi-arrow-repeat" id="refreshStatusIcon" style="cursor:pointer;" onclick="loadServerStatus()"></i>
                            </div>
                            <div class="status-grid">
                                <div class="status-item">
                                    <span class="status-badge" id="urlStatusBadge"></span>
                                    <span id="urlStatusText">Checking...</span>
                                </div>
                                <div class="status-item">
                                    <span class="status-badge" id="sslStatusBadge"></span>
                                    <span id="sslStatusText">Checking...</span>
                                </div>
                            </div>
                            <div class="small text-muted mt-2" id="serverUrlDisplay"></div>
                        </div>
                        
                        <!-- Last Check Info -->
                        <div class="last-check-badge mb-3" id="lastCheckBox">
                            <i class="bi bi-clock-history me-1"></i>
                            Last check: <span id="lastCheckDate">Never</span>
                        </div>
                        
                        <!-- Check Button -->
                        <button type="button" class="btn-apple w-100" id="checkUpdateBtn" onclick="checkForUpdates()">
                            <i class="bi bi-search me-2"></i>Check for Updates
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script src="lib/jquery-3.6.0-master/dist/jquery.min.js"></script>
<script src="lib/bootstrap-5.3.8-dist/js/bootstrap.bundle.min.js"></script>
<script src="js/loader.js"></script>

<script>
const API_BASE = window.apiConfig.apiBaseUrl || '/api/';
const UPDATE_API_KEY = window.apiConfig.apiKey || '';
let updateData = null;
let currentStep = 0;

function showAlert(message, type = 'success') {
    const alertHtml = `<div class="alert alert-${type} alert-dismissible fade show mb-3" role="alert">
        <i class="bi bi-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i> 
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>`;
    $('#alertContainer').append(alertHtml);
    setTimeout(() => $('.alert').fadeOut(500, function() { $(this).remove(); }), 5000);
}

function addLog(message, type = 'info') {
    const logDiv = $('#updateLog');
    const logLine = `<div class="log-line ${type}">[${new Date().toLocaleTimeString()}] ${message}</div>`;
    logDiv.append(logLine);
    logDiv.scrollTop(logDiv[0].scrollHeight);
}

function updateProgress(percent) {
    $('#updateProgress').css('width', percent + '%');
    $('#progressPercent').text(percent + '%');
}

function apiCall(action, method, data = null, onSuccess = null, onError = null, useUpdateApiKey = true) {
    const apiUrl = API_BASE + 'update_api.php';
    
    let requestData = { action: action };
    if (data) {
        requestData = { ...requestData, ...data };
    }
    
    const headers = {
        'X-API-Key': UPDATE_API_KEY  // Ключ всегда в заголовке, не в URL
    };
    
    let url = apiUrl;
    let requestBody = null;
    
    if (method === 'GET') {
        url += '?' + new URLSearchParams(requestData).toString();
    } else {
        requestBody = requestData;
    }
    
    $.ajax({
        url: url,
        method: method,
        data: requestBody,
        headers: headers,
        dataType: 'json',
        timeout: 30000,
        success: function(response) {
            if (response.success) {
                if (onSuccess) onSuccess(response);
            } else {
                if (onError) onError(response.error || 'Unknown error');
                else showAlert(response.error || 'Error', 'danger');
            }
        },
        error: function(xhr) {
            const error = xhr.responseJSON?.error || 'Network error';
            if (onError) onError(error);
            else showAlert(error, 'danger');
        }
    });
}

function loadServerStatus() {
    apiCall('get_server_status', 'GET', null,
        function(response) {
            const status = response.status;
            
            if (status.available) {
                $('#urlStatusBadge').removeClass().addClass('status-badge green');
                $('#urlStatusText').html('<i class="bi bi-check-circle-fill text-success me-1"></i> Available');
            } else {
                $('#urlStatusBadge').removeClass().addClass('status-badge red');
                $('#urlStatusText').html('<i class="bi bi-x-circle-fill text-danger me-1"></i> Unavailable');
            }
            
            if (status.ssl_verified) {
                $('#sslStatusBadge').removeClass().addClass('status-badge green');
                $('#sslStatusText').html('<i class="bi bi-shield-check text-success me-1"></i> Verified');
            } else {
                $('#sslStatusBadge').removeClass().addClass('status-badge orange');
                $('#sslStatusText').html('<i class="bi bi-shield-exclamation text-warning me-1"></i> Not Verified');
            }
            
            $('#serverUrlDisplay').html('<i class="bi bi-link me-1"></i>' + escapeHtml(status.url));
            
            const serverBox = $('#serverStatusBox');
            if (status.available && status.ssl_verified) {
                serverBox.removeClass('error warning').addClass('success');
            } else if (status.available) {
                serverBox.removeClass('error success').addClass('warning');
            } else {
                serverBox.removeClass('success warning').addClass('error');
            }
        },
        function(error) {
            $('#urlStatusText').html('<i class="bi bi-question-circle text-secondary me-1"></i> Check failed');
            $('#sslStatusText').html('<i class="bi bi-question-circle text-secondary me-1"></i> Check failed');
            $('#serverStatusBox').removeClass('success warning').addClass('error');
        }
    );
}

function loadLastCheckDate() {
    apiCall('get_last_check', 'GET', null,
        function(response) {
            $('#lastCheckDate').html(response.last_check_date || 'Never');
        },
        function() {
            $('#lastCheckDate').html('Error loading');
        }
    );
}

function loadLastCheckDate() {
    apiCall('get_last_check', 'GET', null,
        function(response) {
            if (response.last_check_date) {
                $('#lastCheckDate').html(response.last_check_date);
            } else {
                $('#lastCheckDate').html('Never');
            }
        },
        function(error) {
            $('#lastCheckDate').html('Error loading');
        }
    );
}

function checkForUpdates() {
    const $btn = $('#checkUpdateBtn');
    $btn.prop('disabled', true).html('<span class="loading-spinner-sm"></span> Checking...');
    
    $('#updateInfoCard').hide();
    updateData = null;
    
    apiCall('check_update', 'GET', null,
        function(response) {
            $('#currentVersion').html(`<span class="version-badge version-current">${escapeHtml(response.current_version)}</span>`);
            $('#typePro').html(`<span class="version-badge version-current">${escapeHtml(response.type_pro)}</span>`);
            $('#systemInfo').html(`${escapeHtml(response.system_info.os_type)} | PHP: ${escapeHtml(response.system_info.php_version)}`);
            
            if (response.last_check_date) {
                $('#lastCheckDate').html(response.last_check_date);
            }
            
            if (response.update_available && response.new_version) {
                updateData = response;
                $('#newVersion').html(escapeHtml(response.new_version));
                $('#releaseDate').html(escapeHtml(response.release_date || 'Unknown'));
                $('#versionInfo').html(response.version_info || '<em>No description provided</em>');
                $('#updateInfoCard').fadeIn();
                showAlert(`New version ${response.new_version} is available!`, 'success');
            } else {
                showAlert('You have the latest version! No updates available.', 'success');
            }
            
            $btn.prop('disabled', false).html('<i class="bi bi-search me-2"></i>Check for Updates');
        },
        function(error) {
            $btn.prop('disabled', false).html('<i class="bi bi-search me-2"></i>Check for Updates');
            showAlert('Failed to check updates: ' + error, 'danger');
        }
    );
}

function startUpdate() {
    if (!updateData || !updateData.download_url) {
        showAlert('Update information not available. Please check for updates first.', 'danger');
        return;
    }
    
    if (!confirm(`Are you sure you want to update from ${updateData.current_version} to ${updateData.new_version}?\n\nThis will restart services.`)) {
        return;
    }
    
    $('#updateProcessCard').show();
    $('#updateNowBtn').prop('disabled', true).html('<span class="loading-spinner-sm"></span> Updating...');
    updateProgress(0);
    addLog('Starting update process...', 'info');
    
    addLog(`Downloading version ${updateData.new_version}...`, 'info');
    updateProgress(10);
    
    apiCall('download_update', 'POST', {
        download_url: updateData.download_url,
        version: updateData.new_version
    },
    function(response) {
        addLog('Download completed successfully!', 'success');
        updateProgress(30);
        
        addLog('Extracting archive...', 'info');
        
        apiCall('extract_update', 'POST', { archive_path: response.path },
        function(extractResponse) {
            addLog('Extraction completed!', 'success');
            updateProgress(50);
            
            addLog('Running update script...', 'info');
            updateProgress(60);
            
            apiCall('run_update_background', 'POST', { 
                update_script: extractResponse.update_script,
                extract_dir: extractResponse.extract_dir 
            },
            function(runResponse) {
                if (runResponse.success) {
                    addLog('Update script started in background', 'success');
                    
                    let checkInterval;
                    let attempts = 0;
                    const maxAttempts = 300;
                    
                    function checkUpdateStatus() {
                        attempts++;
                        
                        apiCall('get_update_status', 'GET', null,
                            function(statusResponse) {
                                if (statusResponse.output) {
                                    addLog(statusResponse.output.trim(), 'info');
                                    
                                    if (statusResponse.output.toLowerCase().includes('complete') || 
                                        statusResponse.output.toLowerCase().includes('done')) {
                                        let currentWidth = parseInt($('#updateProgress').css('width'));
                                        if (currentWidth < 90) {
                                            updateProgress(currentWidth + 5);
                                        }
                                    }
                                }
                                
                                if (statusResponse.complete) {
                                    clearInterval(checkInterval);
                                    updateProgress(100);
                                    addLog('========================================', 'success');
                                    addLog('UPDATE COMPLETED SUCCESSFULLY!', 'success');
                                    addLog('========================================', 'success');
                                    addLog('Redirecting to system check page in 5 seconds...', 'info');
                                    
                                    // Cleanup
                                    apiCall('cleanup', 'POST', { extract_dir: extractResponse.extract_dir }, function() {});
                                    
                                    setTimeout(() => {
                                        window.location.href = 'system_check.php';
                                    }, 5000);
                                }
                                
                                if (statusResponse.error) {
                                    clearInterval(checkInterval);
                                    addLog('ERROR: ' + statusResponse.error, 'error');
                                    updateProgress(0);
                                    $('#updateNowBtn').prop('disabled', false).html('<i class="bi bi-cloud-download me-2"></i>Update Now');
                                    showAlert('Update failed: ' + statusResponse.error, 'danger');
                                }
                            },
                            function(error) {
                                if (attempts >= maxAttempts) {
                                    clearInterval(checkInterval);
                                    addLog('Update status check timeout', 'error');
                                    $('#updateNowBtn').prop('disabled', false).html('<i class="bi bi-cloud-download me-2"></i>Update Now');
                                }
                            }
                        );
                    }
                    
                    checkInterval = setInterval(checkUpdateStatus, 2000);
                    
                    setTimeout(() => {
                        if (checkInterval) {
                            clearInterval(checkInterval);
                            addLog('Update process timeout (5 minutes)', 'error');
                            $('#updateNowBtn').prop('disabled', false).html('<i class="bi bi-cloud-download me-2"></i>Update Now');
                        }
                    }, 300000);
                    
                } else {
                    addLog('Failed to start update script: ' + (runResponse.error || 'Unknown error'), 'error');
                    $('#updateNowBtn').prop('disabled', false).html('<i class="bi bi-cloud-download me-2"></i>Update Now');
                }
            },
            function(error) {
                addLog('Failed to start update script: ' + error, 'error');
                $('#updateNowBtn').prop('disabled', false).html('<i class="bi bi-cloud-download me-2"></i>Update Now');
            });
        },
        function(error) {
            addLog('Extraction failed: ' + error, 'error');
            $('#updateNowBtn').prop('disabled', false).html('<i class="bi bi-cloud-download me-2"></i>Update Now');
            showAlert('Extraction failed: ' + error, 'danger');
        });
    },
    function(error) {
        addLog('Download failed: ' + error, 'error');
        $('#updateNowBtn').prop('disabled', false).html('<i class="bi bi-cloud-download me-2"></i>Update Now');
        showAlert('Download failed: ' + error, 'danger');
    });
}

function refreshAllData() {
    $('#updateInfoCard').hide();
    $('#updateProcessCard').hide();
    updateData = null;
    loadServerStatus();
    loadLastCheckDate();
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/[&<>]/g, function(m) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;' }[m];
    });
}

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
            showAlert('Switching to ' + $(this).find('option:selected').text() + '...', 'info');
            $('#applePreloader').fadeIn(200);
            
            $.ajax({
                url: 'ajax/switch_host.php',
                method: 'POST',
                data: { host_id: newHostId },
                success: function() {
                    window.location.href = 'update_manager.php';
                },
                error: function() {
                    showAlert('Failed to switch host', 'danger');
                    $('#applePreloader').fadeOut(500);
                    selector.val(window.currentHostId);
                }
            });
        }
    });
}

$(document).ready(function() {
    initHostSelector();
    loadServerStatus();
    loadLastCheckDate();
    
    apiCall('check_versions', 'GET', null,
        function(response) {
            $('#currentVersion').html(`<span class="version-badge version-current">${escapeHtml(response.current_version)}</span>`);
            $('#typePro').html(`<span class="version-badge version-current">${escapeHtml(response.type_pro)}</span>`);
            $('#systemInfo').html(`${escapeHtml(response.system_info.os_type)} | PHP: ${escapeHtml(response.system_info.php_version)}`);
        },
        function(error) {
            $('#currentVersion').html('Error');
            $('#systemInfo').html('Failed to load');
        }
    );
    
    setTimeout(() => $('#applePreloader').fadeOut(500), 500);
});
</script>
</body>
</html>