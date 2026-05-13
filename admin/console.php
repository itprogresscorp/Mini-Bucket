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
 
require_once 'config.php';
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

$menu = require_once 'menu.php';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mini-b - Console</title>
    <link href="lib/bootstrap-5.3.8-dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="lib/fontawesome-free-6.7.2-web/css/all.min.css">
    <link rel="stylesheet" href="style.css">
	<script src="js/hosts_load.js"></script>
	<script src="js/crt_checker.js"></script>
	<script>
	window.apiConfig = <?php echo json_encode($js_config); ?>;
	//console.log('API Config loaded:', window.apiConfig);
	</script>
    <style>
	
		.main-content {
			flex: 1;
			margin-left: 260px;
			padding: 0px 10px;
		}
		
        .console-container {
            background: #1e1e1e;
            border-radius: 12px;
            overflow: hidden;
            font-family: 'Courier New', monospace;
        }
        .console-output {
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
            min-height: 800px;
            max-height: 800px;
            overflow-y: auto;
            font-family: monospace;
            font-size: 13px;
            line-height: 1.5;
        }
        .console-output::-webkit-scrollbar {
            width: 8px;
        }
        .console-output::-webkit-scrollbar-track {
            background: #2d2d2d;
        }
        .console-output::-webkit-scrollbar-thumb {
            background: #555;
            border-radius: 4px;
        }
        .console-input-line {
            background: #2d2d2d;
            border-top: 1px solid #3d3d3d;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .console-prompt {
            color: #4ec9b0;
            font-weight: bold;
            font-family: monospace;
            font-size: 14px;
        }
        .console-input {
            flex: 1;
            background: transparent;
            border: none;
            color: #d4d4d4;
            font-family: monospace;
            font-size: 14px;
            outline: none;
        }
        .console-input:focus {
            outline: none;
        }
        .console-line {
            margin-bottom: 4px;
            white-space: pre-wrap;
            word-break: break-all;
            font-family: monospace;
            font-size: 13px;
            line-height: 1.4;
        }
        .console-line.command {
            color: #4ec9b0;
        }
        .console-line.error {
            color: #f48771;
        }
        .console-line.warning {
            color: #dcdcaa;
        }
        .console-line.success {
            color: #6a9955;
        }
        .console-line.info {
            color: #9cdcfe;
        }
        .console-line.output {
            color: #d4d4d4;
        }
        .btn-console {
            background: #0d6efd;
            border: none;
            padding: 8px 20px;
            border-radius: 8px;
            color: white;
            font-weight: 500;
        }
        .btn-console:hover {
            background: #0b5ed7;
        }
        .btn-console-exit {
            background: #dc3545;
        }
        .btn-console-exit:hover {
            background: #bb2d3b;
        }
        .connection-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
        }
        .status-connected {
            background: #d1fae5;
            color: #065f46;
        }
        .status-disconnected {
            background: #fee2e2;
            color: #991b1b;
        }
        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
        }
        .status-dot.connected {
            background: #10b981;
            animation: pulse 2s infinite;
        }
        .status-dot.disconnected {
            background: #ef4444;
        }
        @keyframes pulse {
            0% { opacity: 0.5; transform: scale(1); }
            50% { opacity: 1; transform: scale(1.2); }
            100% { opacity: 0.5; transform: scale(1); }
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
        .toast-container {
            z-index: 1100;
        }
        .command-hint {
            font-size: 11px;
            color: #6c757d;
            margin-top: 8px;
            padding-left: 12px;
        }
    </style>
</head>
<body>

<div class="top-bar">
    <div class="top-bar-left">
        <h1><i class="fas fa-bucket"></i> Mini-B</h1>
    </div>
	
    <div class="top-bar-right">
            <text><i class="fas fa-terminal"></i> Console</text>
			<div class="host-selector" style="margin-left: 20px;">
            <select id="hostSelector" style="background: rgba(255,255,255,0.9); border: 1px solid #ddd; border-radius: 20px; padding: 6px 30px 6px 15px; font-size: 14px; cursor: pointer;">
                <option value="">Loading...</option>
            </select>
        </div>
			<span id="connectionStatus" class="connection-status status-disconnected">
                    <span class="status-dot disconnected"></span> Disconnected
                </span>
				<button class="btn btn-outline-secondary btn-sm ms-2" onclick="clearConsole()">
                    <i class="fas fa-eraser"></i> Clear
                </button>
	</div>
</div>

<div class="app-container">
    <?php echo $menu; ?>
    <main class="main-content">

        <div class="console-container mt-3">
            <div class="console-output" id="consoleOutput">
                <div class="console-line info">═══════════════════════════════════════════════════════════</div>
                <div class="console-line success"><i class="fas fa-check-circle"></i> SSH Console v2.0 (stable version)</div>
                <div class="console-line info">═══════════════════════════════════════════════════════════</div>
                <div class="console-line info"><i class="fas fa-hand-pointer"></i> Click the "Connect" button to start</div>
                <div class="console-line info"><i class="fas fa-terminal"></i> After connecting, you can enter any commands</div>
                <div class="console-line info"><i class="fas fa-sign-out-alt"></i> To exit, type 'exit' or click the "Exit" button</div>
                <div class="console-line info">═══════════════════════════════════════════════════════════</div>
            </div>
            <div class="console-input-line">
                <span class="console-prompt" id="consolePrompt">$</span>
                <input type="text" class="console-input" id="consoleInput" placeholder="Enter command..." disabled>
                <button class="btn btn-sm btn-console" id="connectBtn" onclick="connectConsole()">
                    <i class="fas fa-plug"></i> Connect
                </button>
                <button class="btn btn-sm btn-console-exit" id="exitBtn" onclick="closeConsole()" style="display: none;">
                    <i class="fas fa-power-off"></i> Exit
                </button>
            </div>
            <div class="command-hint">
                <i class="fas fa-info-circle"></i> Available commands: ls, cd, pwd, cat, grep, ps, df, du, free, top, htop, systemctl, journalctl, apt, wget, curl, and any others
            </div>
        </div>
    </main>
</div>

<div class="loader-overlay" id="loader">
    <div class="loader-spinner"></div>
</div>

<div class="toast-container position-fixed bottom-0 end-0 p-3"></div>

<script>
const url = "<?php echo $current_host_id == 1 ? '/api/' : rtrim($host_url, '/') . '/'; ?>";
let currentSession = null;
let consoleOutput = null;
let consoleInput = null;
let commandHistory = [];
let historyIndex = 0;

function showLoader() {
    document.getElementById('loader').style.display = 'flex';
}

function hideLoader() {
    document.getElementById('loader').style.display = 'none';
}

function showToast(message, type = 'success') {
    const container = document.querySelector('.toast-container');
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-white bg-${type === 'success' ? 'success' : (type === 'info' ? 'info' : 'danger')} border-0 show`;
    toast.setAttribute('role', 'alert');
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">${message}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    container.appendChild(toast);
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 5000);
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

async function apiCall(action, data = {}) {
    try {
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 60000);
        
        const headers = { 'Content-Type': 'application/json' };
        if (window.apiConfig && window.apiConfig.apiKey) {
            headers['X-API-Key'] = window.apiConfig.apiKey;
        }
        
        const res = await fetch(url + 'console_api.php', {
            method: 'POST',
            headers: headers,
            body: JSON.stringify({ action, ...data }),
            signal: controller.signal
        });
        
        clearTimeout(timeoutId);
        
        if (!res.ok) {
            throw new Error(`HTTP ${res.status}: ${res.statusText}`);
        }
        
        return await res.json();
    } catch(e) {
        if (e.name === 'AbortError') {
            showToast('Timeout: Operation took too long', 'danger');
        } else {
            showToast(e.message || 'Connection error', 'danger');
        }
        return { success: false, error: e.message };
    }
}

function appendToConsole(text, type = 'output') {
    if (!consoleOutput) {
        consoleOutput = document.getElementById('consoleOutput');
    }
    
    const lines = text.split('\n');
    for (const line of lines) {
        if (line === '' && type !== 'command') continue;
        const lineDiv = document.createElement('div');
        lineDiv.className = `console-line ${type}`;
        lineDiv.style.whiteSpace = 'pre-wrap';
        lineDiv.style.wordBreak = 'break-all';
        lineDiv.innerHTML = escapeHtml(line);
        consoleOutput.appendChild(lineDiv);
    }
    
    consoleOutput.scrollTop = consoleOutput.scrollHeight;
}

async function connectConsole() {
    showLoader();
    
    const res = await apiCall('init');
    
    hideLoader();
    
    if (res.success) {
        currentSession = res.session_id;
        
        document.getElementById('connectBtn').style.display = 'none';
        document.getElementById('exitBtn').style.display = 'inline-block';
        document.getElementById('consoleInput').disabled = false;
        document.getElementById('consoleInput').focus();
        
        document.getElementById('connectionStatus').className = 'connection-status status-connected';
        document.getElementById('connectionStatus').innerHTML = '<span class="status-dot connected"></span> Connected';
        
        appendToConsole('═══════════════════════════════════════════════════════════', 'info');
        appendToConsole('<i class="fas fa-check-circle"></i> Connection established', 'success');
        appendToConsole(`<i class="fas fa-id-card"></i> Session ID: ${currentSession.substring(0, 20)}...`, 'info');
        
        if (res.output) {
            appendToConsole(res.output, 'output');
        }
        
        appendToConsole('═══════════════════════════════════════════════════════════', 'info');
        appendToConsole('<i class="fas fa-rocket"></i> Ready to work. Enter commands:', 'success');
        
        document.getElementById('consolePrompt').innerHTML = '<span style="color:#4ec9b0">root@localhost</span>:<span style="color:#9cdcfe">~</span>#';
        
    } else {
        appendToConsole(`<i class="fas fa-times-circle"></i> Error: ${res.error}`, 'error');
        showToast(res.error, 'danger');
    }
}

async function sendCommand() {
    if (!currentSession) {
        appendToConsole('<i class="fas fa-times-circle"></i> No active session. Click "Connect"', 'error');
        return;
    }
    
    const command = consoleInput.value.trim();
    if (!command) return;
    
    commandHistory.push(command);
    historyIndex = commandHistory.length;
    
    appendToConsole(`$ ${command}`, 'command');
    
    consoleInput.value = '';
    
    if (command.toLowerCase() === 'exit' || command.toLowerCase() === 'logout') {
        appendToConsole('Ending session...', 'warning');
        await closeConsole();
        return;
    }
    
    showLoader();
    
    const res = await apiCall('command', {
        session_id: currentSession,
        command: command
    });
    
    hideLoader();
    
    if (res.success) {
        if (res.output && res.output.trim()) {
            appendToConsole(res.output, 'output');
        }
        
        if (res.is_exited) {
            appendToConsole('Session ended. Closing console...', 'warning');
            await closeConsole();
        }
    } else {
        appendToConsole(`<i class="fas fa-times-circle"></i> Error: ${res.error}`, 'error');
    }
    
    consoleInput.focus();
}

async function closeConsole() {
    if (currentSession) {
        showLoader();
        await apiCall('close', { session_id: currentSession });
        hideLoader();
        currentSession = null;
    }
    
    document.getElementById('connectBtn').style.display = 'inline-block';
    document.getElementById('exitBtn').style.display = 'none';
    document.getElementById('consoleInput').disabled = true;
    document.getElementById('consoleInput').value = '';
    
    document.getElementById('connectionStatus').className = 'connection-status status-disconnected';
    document.getElementById('connectionStatus').innerHTML = '<span class="status-dot disconnected"></span> Disconnected';
    document.getElementById('consolePrompt').innerHTML = '$';
    
    appendToConsole('═══════════════════════════════════════════════════════════', 'info');
    appendToConsole('<i class="fas fa-plug"></i> Session closed', 'warning');
    appendToConsole('<i class="fas fa-hand-pointer"></i> Click "Connect" for a new session', 'info');
    appendToConsole('═══════════════════════════════════════════════════════════', 'info');
}

function clearConsole() {
    if (confirm('Clear console output?')) {
        consoleOutput.innerHTML = '';
        appendToConsole('═══════════════════════════════════════════════════════════', 'info');
        appendToConsole('<i class="fas fa-broom"></i> Output cleared', 'success');
        if (currentSession) {
            appendToConsole('<i class="fas fa-check-circle"></i> Session active. Continue working.', 'info');
        } else {
            appendToConsole('<i class="fas fa-hand-pointer"></i> Click "Connect" to start working', 'info');
        }
        appendToConsole('═══════════════════════════════════════════════════════════', 'info');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    consoleOutput = document.getElementById('consoleOutput');
    consoleInput = document.getElementById('consoleInput');
    
    consoleInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            sendCommand();
        }
    });
    
    consoleInput.addEventListener('keydown', function(e) {
        if (e.key === 'ArrowUp') {
            e.preventDefault();
            if (commandHistory.length > 0 && historyIndex > 0) {
                historyIndex--;
                consoleInput.value = commandHistory[historyIndex];
                setTimeout(() => {
                    consoleInput.selectionStart = consoleInput.selectionEnd = consoleInput.value.length;
                }, 0);
            }
        } else if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (historyIndex < commandHistory.length - 1) {
                historyIndex++;
                consoleInput.value = commandHistory[historyIndex];
                setTimeout(() => {
                    consoleInput.selectionStart = consoleInput.selectionEnd = consoleInput.value.length;
                }, 0);
            } else if (historyIndex === commandHistory.length - 1) {
                historyIndex = commandHistory.length;
                consoleInput.value = '';
            }
        }
    });
});
</script>

</body>
</html>