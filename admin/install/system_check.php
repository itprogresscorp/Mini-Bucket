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
//P.S. I Love USA.

define('ROOT_PATH', dirname(dirname(__FILE__)));

if (file_exists(ROOT_PATH . '/config.php')) {
    require_once ROOT_PATH . '/config.php';
} else {
    die('Configuration file not found. Please ensure config.php exists in the parent directory.');
}

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
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Mini-B System Checker</title>
    <link href="../lib/bootstrap-5.3.8-dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../lib/bootstrap-icons-1.11.0/bootstrap-icons.css">
    <link rel="stylesheet" href="../lib/fontawesome-free-6.7.2-web/css/all.min.css">
    <!--<link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">-->
	<link rel="stylesheet" href="../style.css">
	<script>
window.apiConfig = <?php echo json_encode($js_config); ?>;
</script>

    <style>
        * {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e9eef3 100%);
            min-height: 100vh;
            padding: 2rem 1rem;
        }
        
        .apple-card {
            background: rgba(255, 255, 255, 0.96);
            backdrop-filter: blur(0px);
            border-radius: 20px;
            box-shadow: 0 8px 28px rgba(0, 0, 0, 0.08), 0 1px 2px rgba(0, 0, 0, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.5);
            transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        }
        
        .apple-card:hover {
            box-shadow: 0 12px 36px rgba(0, 0, 0, 0.12);
            transform: translateY(-1px);
        }
        
        .status-badge {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            transition: all 0.2s ease;
        }
        
        .status-success {
            background: #34c759;
            color: white;
            box-shadow: 0 2px 8px rgba(52, 199, 89, 0.3);
        }
        
        .status-failed {
            background: #ff3b30;
            color: white;
            box-shadow: 0 2px 8px rgba(255, 59, 48, 0.3);
        }
        
        .status-warning {
            background: #ff9500;
            color: white;
        }
        
        .progress-apple {
            height: 8px;
            border-radius: 12px;
            background: #e5e5ea;
            overflow: hidden;
        }
        
        .progress-apple .progress-bar {
            background: linear-gradient(90deg, #34c759, #30b0c7);
            border-radius: 12px;
            transition: width 0.4s cubic-bezier(0.65, 0, 0.35, 1);
        }
        
        .btn-apple {
            background: #007aff;
            border: none;
            border-radius: 12px;
            padding: 12px 24px;
            font-weight: 500;
            font-size: 0.95rem;
            transition: all 0.2s ease;
            color: white;
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
            padding: 10px 20px;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        
        .btn-apple-outline:hover {
            background: #007aff10;
            transform: scale(0.98);
        }
        
        .log-viewer {
            background: #1c1c1e;
            color: #e5e5ea;
            font-family: 'SF Mono', 'Menlo', monospace;
            font-size: 0.85rem;
            border-radius: 16px;
            max-height: 300px;
            overflow-y: auto;
            padding: 1rem;
        }
        
        .log-line {
            font-family: monospace;
            font-size: 0.75rem;
            white-space: pre-wrap;
            word-break: break-all;
            border-left: 2px solid #34c759;
            padding-left: 10px;
            margin-top: 4px;
        }
        
        .category-header {
            cursor: pointer;
            user-select: none;
            transition: background 0.2s;
            border-radius: 16px;
        }
        
        .category-header:hover {
            background: #f8f9fa;
        }
        
        .animate-pulse {
            animation: pulse 1.5s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.6; }
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
        }
        
        .toast-apple {
            background: #1c1c1e;
            color: white;
            border-radius: 12px;
            backdrop-filter: blur(20px);
        }
        
        .scroll-to-top {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: #007aff;
            color: white;
            border: none;
            box-shadow: 0 4px 12px rgba(0, 122, 255, 0.4);
            transition: all 0.2s;
            z-index: 1000;
        }
        
        .scroll-to-top:hover {
            transform: scale(1.05);
            background: #005fc1;
        }
        
        @media (max-width: 768px) {
            body {
                padding: 1rem 0.75rem;
            }
            
            .btn-apple, .btn-apple-outline {
                padding: 10px 16px;
                font-size: 0.85rem;
            }
        }
    </style>
</head>
<body>

<div class="container-lg">
    <!-- Header -->
    <div class="text-center mb-4">
        <div class="d-inline-flex align-items-center justify-content-center bg-white rounded-circle p-3 shadow-sm mb-3" style="width: 70px; height: 70px;">
            <i class="bi bi-shield-check" style="font-size: 2.5rem; color: #007aff;"></i>
        </div>
        <h1 class="fw-bold" style="font-size: 2.5rem; letter-spacing: -0.5px;">System Checker</h1>
        <p class="text-secondary">Server configuration diagnostics and recovery</p>
    </div>
    
    <!-- Control Panel -->
    <div class="apple-card p-3 p-md-4 mb-4">
        <div class="row align-items-center g-3">
            <div class="col-md-6">
                <div class="d-flex gap-2">
                    <button class="btn-apple" id="runCheckBtn">
                        <i class="bi bi-arrow-repeat me-2"></i>Check All
                    </button>
                    <button class="btn-apple-outline" id="fixAllBtn">
                        <i class="bi bi-hammer me-2"></i>Fix All
                    </button>
                </div>
            </div>
			
		
            <div class="col-md-6">
                <div class="d-flex gap-2 justify-content-md-end">
                    <button class="btn-apple-outline" onclick="showLogs('install')">
                        <i class="bi bi-file-text me-1"></i>Install Log
                    </button>
                    <button class="btn-apple-outline" onclick="showLogs('error')">
                        <i class="bi bi-exclamation-triangle me-1"></i>Error Log
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Общий прогресс -->
        <div class="mt-4" id="globalProgressSection" style="display: none;">
            <div class="d-flex justify-content-between mb-2">
                <span class="small fw-semibold"><i class="bi bi-hourglass-split me-1"></i>Overall Progress</span>
                <span class="small text-secondary" id="progressPercent">0%</span>
            </div>
            <div class="progress-apple">
                <div class="progress-bar" id="globalProgressBar" style="width: 0%"></div>
            </div>
            <div class="text-center mt-2">
                <span id="statusMessage" class="small"></span>
            </div>
        </div>
		
		
    </div>
    
    <!-- Results Container -->
    <div id="checksContainer"></div>
	
	<div class="apple-card p-3 p-md-4 mb-4">
        <div class="row align-items-center">
            
                <div class="d-flex gap-2 align-items-center justify-content-center">
                    <a class="btn-apple-outline" href="install.php">
                        <i class="fa fa-check"></i> Next
                  </a>
                </div>
            
    </div>
</div>

<!-- Modal для логов и модальных окон -->
<div class="modal fade modal-apple" id="logModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-semibold"><i class="bi bi-terminal me-2"></i><span id="logModalTitle">System Logs</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div class="log-viewer" id="logContent">
                    <div class="text-center text-secondary p-4">Loading logs...</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn-apple" id="refreshLogBtn"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно для проверки с любой страницы (глобальный вызов) -->
<div class="modal fade modal-apple" id="globalCheckModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-semibold">
                    <i class="bi bi-laptop me-2"></i>Server Diagnostics
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="globalModalBody">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary mb-3" role="status"></div>
                    <p>Starting system check...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn-apple" id="globalFixAllBtn">Fix All</button>
            </div>
        </div>
    </div>
</div>

<!-- Кнопка скролла -->
<button class="scroll-to-top" id="scrollToTop" style="display: none;">
    <i class="bi bi-arrow-up"></i>
</button>

<script src="../lib/jquery-3.6.0-master/dist/jquery.js"></script>
<script src="../lib/bootstrap-5.3.8-dist/js/bootstrap.bundle.min.js"></script>

<script>
const url = "<?php echo $current_host_id == 1 ? '/api/' : rtrim($host_url, '/') . '/'; ?>";
let currentCheckData = null;
let currentLogType = 'install';
let isChecking = false;

function showToast(message, type = 'success') {
    const toastHtml = `
        <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1100">
            <div class="toast align-items-center text-white bg-${type === 'success' ? 'success' : 'danger'} border-0" role="alert">
                <div class="d-flex">
                    <div class="toast-body">${message}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        </div>
    `;
    $('body').append(toastHtml);
    const toastEl = $('.toast').last();
    const toast = new bootstrap.Toast(toastEl[0], { autohide: true, delay: 3000 });
    toast.show();
    toastEl.on('hidden.bs.toast', () => toastEl.remove());
}

async function runFullCheck(showProgress = true) {
    if (isChecking) {
        showToast('Check already in progress', 'warning');
        return;
    }
    
    isChecking = true;
    $('#runCheckBtn').html('<i class="bi bi-arrow-repeat animate-pulse me-2"></i>Checking...');
    $('#runCheckBtn').prop('disabled', true);
    
    if (showProgress) {
        $('#globalProgressSection').show();
        updateGlobalProgress(0, 'Starting check...');
    }
    
    const headers = {};
    if (window.apiConfig && window.apiConfig.apiKey) {
        headers['X-API-Key'] = window.apiConfig.apiKey;
    }
    
    try {
        const response = await fetch(url + 'system_check_api.php?action=check_all', {
            method: 'GET',
            headers: headers
        });
        const data = await response.json();
        
        if (data.success) {
            currentCheckData = data.results;
            renderResults(data.results);
            updateStats();
            if (showProgress) {
                updateGlobalProgress(100, 'Check completed');
                setTimeout(() => $('#globalProgressSection').fadeOut(), 2000);
            }
            showToast('Check completed', 'success');
        } else {
            throw new Error('Error getting data');
        }
    } catch (error) {
        console.error(error);
        showToast('Error during system check', 'danger');
        $('#checksContainer').html(`
            <div class="apple-card p-5 text-center">
                <i class="bi bi-wifi-off" style="font-size: 3rem; color: #ff3b30;"></i>
                <h5 class="mt-3">Connection Error</h5>
                <p class="text-secondary">Could not connect to API. Check if system_check_api.php is available</p>
                <button class="btn-apple mt-2" onclick="location.reload()">Refresh</button>
            </div>
        `);
    } finally {
        isChecking = false;
        $('#runCheckBtn').html('<i class="bi bi-arrow-repeat me-2"></i>Check All');
        $('#runCheckBtn').prop('disabled', false);
    }
}

function renderResults(results) {
    let html = '';
    let categoryIndex = 0;
    
    for (const [catKey, category] of Object.entries(results)) {
        const itemsCount = Object.keys(category.items).length;
        const failedCount = Object.values(category.items).filter(item => !item.status).length;
        const statusIcon = failedCount === 0 ? 'bi-check-circle-fill text-success' : (failedCount === itemsCount ? 'bi-x-circle-fill text-danger' : 'bi-exclamation-triangle-fill text-warning');
        
        html += `
            <div class="apple-card mb-4 overflow-hidden">
                <div class="category-header p-3 p-md-4" onclick="toggleCategory('cat_${catKey}')">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <i class="bi ${statusIcon} me-2" style="font-size: 1.3rem;"></i>
                            <strong class="fs-5">${category.name}</strong>
                            <span class="badge bg-secondary ms-2">${failedCount}/${itemsCount}</span>
                        </div>
                        <i class="bi bi-chevron-down fs-5" id="icon_cat_${catKey}"></i>
                    </div>
                </div>
                <div id="cat_${catKey}" class="collapse ${categoryIndex === 0 ? 'show' : ''}">
                    <div class="p-3 p-md-4 pt-0 border-top">
                        <div class="row g-3 mt-2">
        `;
        
        for (const [itemKey, item] of Object.entries(category.items)) {
            const statusClass = item.status ? 'success' : 'failed';
            const statusIconSmall = item.status ? 'bi-check-lg' : 'bi-x-lg';
            
            html += `
                <div class="col-md-6 col-lg-4" data-category="${catKey}" data-item="${itemKey}">
                    <div class="d-flex align-items-center justify-content-between p-3 bg-light rounded-4 border">
                        <div class="d-flex align-items-center gap-3">
                            <div class="status-badge status-${statusClass}">
                                <i class="bi ${statusIconSmall}"></i>
                            </div>
                            <div>
                                <div class="fw-semibold small">${item.name}</div>
                                <div class="text-secondary small">${item.status ? '✓ Working' : '✗ Needs attention'}</div>
                            </div>
                        </div>
                        ${!item.status ? `
                            <button class="btn btn-sm btn-outline-primary rounded-pill fix-item-btn" data-category="${catKey}" data-item="${itemKey}">
                                <i class="bi bi-hammer me-1"></i>Fix
                            </button>
                        ` : `
                            <span class="text-success small"><i class="bi bi-check-circle"></i> OK</span>
                        `}
                    </div>
                </div>
            `;
        }
        
        html += `
                        </div>
                    </div>
                </div>
            </div>
        `;
        categoryIndex++;
    }
    
    $('#checksContainer').html(html);
    
    $('.fix-item-btn').on('click', function(e) {
        e.stopPropagation();
        const category = $(this).data('category');
        const item = $(this).data('item');
        fixSingleItem(category, item);
    });
}

async function fixSingleItem(category, item, buttonElement = null) {
    const btn = buttonElement || $(`.fix-item-btn[data-category="${category}"][data-item="${item}"]`);
    const originalHtml = btn.html();
    btn.html('<i class="bi bi-hourglass-split me-1"></i>...').prop('disabled', true);
    
    try {
        const formData = new URLSearchParams();
        formData.append('action', 'fix');
        formData.append('category', category);
        formData.append('item', item);
        
        const headers = {
            'Content-Type': 'application/x-www-form-urlencoded'
        };
        if (window.apiConfig && window.apiConfig.apiKey) {
            headers['X-API-Key'] = window.apiConfig.apiKey;
        }
        
        const response = await fetch(url + 'system_check_api.php', {
            method: 'POST',
            headers: headers,
            body: formData.toString()
        });
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const result = await response.json();
        
        if (result.success) {
            showToast(result.message || 'Fixed successfully', 'success');
            await runFullCheck(false);
        } else {
            showToast('Error: ' + (result.message || 'unknown error'), 'danger');
            btn.html(originalHtml).prop('disabled', false);
        }
    } catch (error) {
        console.error('Fix error:', error);
        showToast('Network error during fix: ' + error.message, 'danger');
        btn.html(originalHtml).prop('disabled', false);
    }
}

async function fixEverything() {
    if (!confirm('Are you sure you want to fix ALL issues? Packages will be reinstalled and settings will be changed.')) return;
    
    $('#fixAllBtn').html('<i class="bi bi-hourglass-split animate-pulse me-2"></i>Fixing...').prop('disabled', true);
    $('#globalProgressSection').show();
    updateGlobalProgress(0, 'Starting mass fix...');
    
    try {
        const formData = new URLSearchParams();
        formData.append('action', 'fix_all');
        formData.append('confirm', 'yes');
        
        const headers = {
            'Content-Type': 'application/x-www-form-urlencoded'
        };
        if (window.apiConfig && window.apiConfig.apiKey) {
            headers['X-API-Key'] = window.apiConfig.apiKey;
        }
        
        const response = await fetch(url + 'system_check_api.php', {
            method: 'POST',
            headers: headers,
            body: formData.toString()
        });
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const result = await response.json();
        
        if (result.success) {
            updateGlobalProgress(100, 'All issues fixed!');
            showToast('All components successfully fixed', 'success');
            await runFullCheck(false);
        } else {
            throw new Error(result.message || 'API returned error');
        }
    } catch (error) {
        console.error('Fix all error:', error);
        showToast('Error during mass fix: ' + error.message, 'danger');
        updateGlobalProgress(0, 'Fix failed: ' + error.message);
    } finally {
        $('#fixAllBtn').html('<i class="bi bi-hammer me-2"></i>Fix All').prop('disabled', false);
        setTimeout(() => $('#globalProgressSection').fadeOut(), 3000);
    }
}

function updateStats() {
    if (!currentCheckData) return;
    
    let total = 0, fixed = 0;
    for (const category of Object.values(currentCheckData)) {
        for (const item of Object.values(category.items)) {
            total++;
            if (item.status) fixed++;
        }
    }
    
    const percent = total > 0 ? (fixed / total * 100).toFixed(0) : 0;
    $('#globalProgressBar').css('width', percent + '%');
    $('#progressPercent').text(percent + '%');
    $('#statusMessage').html(`<i class="bi bi-check2-circle"></i> Ready: ${fixed}/${total} items`);
}

function updateGlobalProgress(percent, message) {
    $('#globalProgressBar').css('width', percent + '%');
    $('#progressPercent').text(percent + '%');
    $('#statusMessage').html(message);
}

function toggleCategory(catId) {
    $(`#${catId}`).collapse('toggle');
    const icon = $(`#icon_${catId}`);
    if ($(`#${catId}`).hasClass('show')) {
        icon.removeClass('bi-chevron-down').addClass('bi-chevron-up');
    } else {
        icon.removeClass('bi-chevron-up').addClass('bi-chevron-down');
    }
}

async function showLogs(type) {
    currentLogType = type;
    $('#logModalTitle').html(type === 'install' ? '<i class="bi bi-file-text me-2"></i>Install Log' : '<i class="bi bi-exclamation-triangle me-2"></i>Error Log');
    $('#logModal').modal('show');
    await loadLogs();
}

async function loadLogs() {
    $('#logContent').html('<div class="text-center text-secondary p-4"><div class="spinner-border text-primary"></div><p class="mt-2">Loading logs...</p></div>');
    
    try {
        const headers = {};
        if (window.apiConfig && window.apiConfig.apiKey) {
            headers['X-API-Key'] = window.apiConfig.apiKey;
        }
        
        const response = await fetch(url + `system_check_api.php?action=check_log&log=${currentLogType}`, {
            method: 'GET',
            headers: headers
        });
        const data = await response.json();
        
        if (data.success && data.content) {
            const formatted = data.content.split('\n').map(line => `<div class="log-line">${escapeHtml(line)}</div>`).join('');
            $('#logContent').html(formatted || '<div class="text-secondary p-4 text-center">Log is empty</div>');
        } else {
            $('#logContent').html('<div class="text-secondary p-4 text-center">No log data</div>');
        }
    } catch (e) {
        console.error('Load logs error:', e);
        $('#logContent').html('<div class="text-danger p-4 text-center">Error loading logs</div>');
    }
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

window.openSystemChecker = function() {
    const modal = new bootstrap.Modal(document.getElementById('globalCheckModal'));
    modal.show();
    const modalBody = $('#globalModalBody');
    modalBody.html(`
        <div class="text-center py-5">
            <div class="spinner-border text-primary mb-3" role="status"></div>
            <p>Starting diagnostics...</p>
        </div>
    `);
    
    fetch('api/system_check_api.php?action=check_all')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                renderModalResults(data.results);
            } else {
                modalBody.html('<div class="alert alert-danger m-3">Error loading data</div>');
            }
        })
        .catch(err => {
            modalBody.html('<div class="alert alert-danger m-3">Network error</div>');
        });
    
    $('#globalFixAllBtn').off('click').on('click', async function() {
        if (!confirm('Fix all issues?')) return;
        $(this).html('<i class="bi bi-hourglass-split me-2"></i>Fixing...').prop('disabled', true);
        
        const formData = new URLSearchParams();
        formData.append('action', 'fix_all');
        formData.append('confirm', 'yes');
        
        const resp = await fetch('api/system_check_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData.toString()
        });
        const result = await resp.json();
        
        if (result.success) {
            showToast('Fix completed', 'success');
            const newResp = await fetch('api/system_check_api.php?action=check_all');
            const newData = await newResp.json();
            if (newData.success) renderModalResults(newData.results);
        } else {
            showToast('Fix error', 'danger');
        }
        
        $(this).html('Fix All').prop('disabled', false);
    });
};

function renderModalResults(results) {
    let html = '<div class="p-3">';
    for (const [catKey, category] of Object.entries(results)) {
        html += `<h6 class="mt-3 fw-semibold"><i class="bi bi-folder2 me-1"></i> ${category.name}</h6><div class="row g-2 mb-3">`;
        for (const [itemKey, item] of Object.entries(category.items)) {
            const statusColor = item.status ? '#34c759' : '#ff3b30';
            const statusText = item.status ? '✓' : '✗';
            html += `
                <div class="col-md-6">
                    <div class="d-flex justify-content-between align-items-center p-2 rounded border" style="background: #f8f9fa;">
                        <span><span style="color: ${statusColor}">${statusText}</span> ${item.name}</span>
                        ${!item.status ? `<button class="btn btn-sm btn-link quick-fix-modal" data-cat="${catKey}" data-item="${itemKey}">Fix</button>` : ''}
                    </div>
                </div>
            `;
        }
        html += `</div>`;
    }
    html += `<div class="alert alert-info mt-3 small">For mass fixing use the button below</div></div>`;
    $('#globalModalBody').html(html);
    
    $('.quick-fix-modal').on('click', async function() {
        const cat = $(this).data('cat');
        const item = $(this).data('item');
        const btn = $(this);
        btn.html('...').prop('disabled', true);
        
        const formData = new URLSearchParams();
        formData.append('action', 'fix');
        formData.append('category', cat);
        formData.append('item', item);
        
        await fetch('api/system_check_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData.toString()
        });
        
        const resp = await fetch('api/system_check_api.php?action=check_all');
        const newData = await resp.json();
        if (newData.success) renderModalResults(newData.results);
        showToast('Fixed', 'success');
    });
}

$(document).ready(function() {
    runFullCheck();
    $('#runCheckBtn').on('click', () => runFullCheck(true));
    $('#fixAllBtn').on('click', fixEverything);
    $('#refreshLogBtn').on('click', loadLogs);
    
    $(window).on('scroll', function() {
        $('#scrollToTop').toggle($(this).scrollTop() > 300);
    });
    $('#scrollToTop').on('click', function() {
        $('html, body').animate({ scrollTop: 0 }, 300);
    });
});
</script>
</body>
</html>