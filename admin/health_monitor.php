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

$menu = require_once 'menu.php';

try {
    $db2 = getDB2();
} catch (Exception $e) {
    error_log("Health Monitor init error: " . $e->getMessage());
}

try {
    $db = getDB();
    initMonitorTables($db);
} catch (Exception $e) {
    error_log("Health Monitor init error: " . $e->getMessage());
}

function initMonitorTables($db2) {
    $queries = [
        "CREATE TABLE IF NOT EXISTS monitored_disks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            disk_name TEXT NOT NULL,
            disk_model TEXT,
            disk_size TEXT,
            disk_path TEXT,
            first_seen DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_seen DATETIME DEFAULT CURRENT_TIMESTAMP,
            is_new INTEGER DEFAULT 1,
            is_active INTEGER DEFAULT 1,
            is_ignored INTEGER DEFAULT 0,
            notes TEXT
        )",
        "CREATE TABLE IF NOT EXISTS monitored_raids (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            raid_name TEXT NOT NULL,
            raid_level TEXT,
            raid_size TEXT,
            devices TEXT,
            first_seen DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_seen DATETIME DEFAULT CURRENT_TIMESTAMP,
            is_new INTEGER DEFAULT 1,
            is_active INTEGER DEFAULT 1,
            is_ignored INTEGER DEFAULT 0,
            notes TEXT
        )",
        "CREATE TABLE IF NOT EXISTS monitored_lvm (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            lvm_type TEXT NOT NULL,
            lvm_name TEXT NOT NULL,
            vg_name TEXT,
            size TEXT,
            first_seen DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_seen DATETIME DEFAULT CURRENT_TIMESTAMP,
            is_new INTEGER DEFAULT 1,
            is_active INTEGER DEFAULT 1,
            is_ignored INTEGER DEFAULT 0,
            notes TEXT
        )",
        "CREATE TABLE IF NOT EXISTS monitored_shares (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            share_type TEXT NOT NULL,
            share_name TEXT NOT NULL,
            share_path TEXT,
            share_config_file TEXT,
            is_active INTEGER DEFAULT 1,
            last_check DATETIME,
            last_status TEXT,
            error_message TEXT,
            notes TEXT
        )",
        "CREATE TABLE IF NOT EXISTS notifications (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            notification_type TEXT NOT NULL,
            severity TEXT NOT NULL,
            title TEXT NOT NULL,
            message TEXT NOT NULL,
            details TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            is_read INTEGER DEFAULT 0,
            is_acknowledged INTEGER DEFAULT 0
        )",
        "CREATE TABLE IF NOT EXISTS notification_settings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            setting_key TEXT UNIQUE NOT NULL,
            setting_value TEXT,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS check_schedules (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            check_type TEXT UNIQUE NOT NULL,
            enabled INTEGER DEFAULT 1,
            interval_seconds INTEGER DEFAULT 300,
            last_run DATETIME,
            next_run DATETIME,
            last_status TEXT,
            last_error TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS check_history (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            check_type TEXT NOT NULL,
            status TEXT NOT NULL,
            message TEXT,
            duration_ms INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS temperature_history (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sensor_type TEXT NOT NULL,
            sensor_name TEXT,
            temperature REAL,
            recorded_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE INDEX IF NOT EXISTS idx_check_history_type ON check_history(check_type)",
        "CREATE INDEX IF NOT EXISTS idx_check_history_created ON check_history(created_at)",
        "CREATE INDEX IF NOT EXISTS idx_schedules_next_run ON check_schedules(next_run)",
        "CREATE INDEX IF NOT EXISTS idx_disks_name ON monitored_disks(disk_name)",
        "CREATE INDEX IF NOT EXISTS idx_notifications_created ON notifications(created_at)",
        "CREATE INDEX IF NOT EXISTS idx_notifications_read ON notifications(is_read)"
    ];
    
    foreach ($queries as $query) {
        try {
            $db2->exec($query);
        } catch (Exception $e) {
        }
    }
    
    $defaultSettings = [
        'cpu_temp_threshold' => '85',
        'disk_temp_threshold' => '55',
        'check_interval' => '300',
        'email_enabled' => '0',
        'email_recipient' => '',
        'webhook_enabled' => '0',
        'webhook_url' => '',
        'notify_disk_missing' => '1',
        'notify_raid_degraded' => '1',
        'notify_lvm_error' => '1',
        'notify_share_down' => '1',
        'notify_temp_critical' => '1',
        'notify_smart_failed' => '1',
        'smtp_host' => '',
        'smtp_port' => '587',
        'smtp_username' => '',
		'smtp_domain' => '',
        'smtp_password' => '',
        'smtp_encryption' => 'tls',
        'smtp_from_email' => '',
        'smtp_from_name' => 'Mini-B Health Monitor',
        'notify_only_on_error' => '0',
        'notification_cooldown_minutes' => '60'
    ];
    
    foreach ($defaultSettings as $key => $value) {
        $stmt = $db2->prepare("INSERT OR IGNORE INTO notification_settings (setting_key, setting_value) VALUES (:key, :value)");
        $stmt->bindValue(':key', $key, SQLITE3_TEXT);
        $stmt->bindValue(':value', $value, SQLITE3_TEXT);
        $stmt->execute();
    }
}

$current_host_id = $_SESSION['current_host_id'] ?? 1;

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

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Health Monitor - Mini-B</title>
    <link href="lib/bootstrap-5.3.8-dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="lib/fontawesome-free-6.7.2-web/css/all.min.css">
    <script src="lib/bootstrap-5.3.8-dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="../style.css">
    <link rel="shortcut icon" href="../css/icon.ico" type="image/x-icon">
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
        :root {
            --primary: #007aff;
            --success: #34c759;
            --warning: #ff9500;
            --danger: #ff3b30;
            --info: #5856d6;
            --dark: #1c1c1e;
            --light: #f5f5f7;
            --border: #efeff4;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background: var(--light);
            color: var(--dark);
        }
        
        .app-container {
            display: flex;
            min-height: calc(100vh - 60px);
        }
        
        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: 24px 32px;
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 70px;
                padding: 16px;
            }
        }
        
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            border: 1px solid var(--border);
            transition: all 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
        }
        
        .stat-value {
            font-size: 36px;
            font-weight: 700;
        }
        
        .stat-label {
            font-size: 13px;
            color: #8e8e93;
            margin-top: 8px;
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
        
        .status-ok {
            background: rgba(52,199,89,0.1);
            color: var(--success);
        }
        
        .status-warning {
            background: rgba(255,149,0,0.1);
            color: var(--warning);
        }
        
        .status-critical {
            background: rgba(255,59,48,0.1);
            color: var(--danger);
            animation: pulse 1s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        
        .check-card {
            background: white;
            border-radius: 20px;
            border: 1px solid var(--border);
            overflow: hidden;
            margin-bottom: 20px;
        }
        
        .check-header {
            padding: 18px 24px;
            background: white;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
        }
        
        .check-header h3 {
            margin: 0;
            font-size: 17px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .check-header h3 i {
            width: 24px;
            color: var(--primary);
        }
        
        .check-body {
            padding: 0 24px 24px 24px;
            display: none;
        }
        
        .check-body.open {
            display: block;
        }
        
        .items-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 16px;
            margin-top: 16px;
        }
        
        .item-card {
            background: var(--light);
            border-radius: 16px;
            padding: 16px;
            border: 1px solid var(--border);
            transition: all 0.2s;
        }
        
        .item-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
        }
        
        .item-card.problem {
            border-color: var(--danger);
            background: rgba(255,59,48,0.02);
            animation: problemPulse 1s ease-in-out infinite;
        }
        
        @keyframes problemPulse {
            0%, 100% { border-color: var(--danger); }
            50% { border-color: var(--danger); box-shadow: 0 0 0 3px rgba(255,59,48,0.1); }
        }
        
        .item-title {
            font-weight: 600;
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .settings-group {
            background: white;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid var(--border);
        }
        
        .settings-group h4 {
            font-size: 16px;
            margin-bottom: 16px;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--primary);
            display: inline-block;
        }
        
        .progress-check {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: var(--dark);
            color: white;
            padding: 12px 20px;
            border-radius: 40px;
            display: none;
            align-items: center;
            gap: 12px;
            z-index: 1000;
            font-size: 14px;
        }
        
        .spinner-small {
            display: inline-block;
            width: 18px;
            height: 18px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .notification-item {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border);
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .notification-item:hover {
            background: var(--light);
        }
        
        .notification-item.unread {
            background: rgba(0,122,255,0.05);
            border-left: 3px solid var(--primary);
        }
        
        .host-selector select {
            background: rgba(255,255,255,0.9);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 6px 30px 6px 15px;
            font-size: 14px;
            cursor: pointer;
        }
        
        .dropdown-menu {
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            border: 1px solid var(--border);
        }
        
        .dropdown-item i {
            width: 20px;
            margin-right: 8px;
        }
        
        .table-responsive {
            border-radius: 12px;
        }
        
        .btn-sm-icon {
            padding: 4px 8px;
            font-size: 12px;
        }
    </style>
</head>
<body>

<div class="top-bar">
    <div class="top-bar-left">
        <h1><i class="fas fa-bucket"></i> Mini-B</h1>
    </div>
    <div class="top-bar-right">
	<i class="fas fa-heartbeat"></i> Health Monitor
        <div class="host-selector" style="margin-left: 20px;">
            <select id="hostSelector" style="background: rgba(255,255,255,0.9); border: 1px solid #ddd; border-radius: 20px; padding: 6px 30px 6px 15px; font-size: 14px; cursor: pointer;">
                <option value=""><?php echo $lang12; ?></option>
            </select>
        </div>
        <button class="btn btn-primary btn-sm" onclick="runAllChecks()">
            <i class="fas fa-play"></i> <?php echo $lang3493; ?>
        </button>
        <div class="btn-group">
            <button class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                <i class="fas fa-cog"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" data-bs-toggle="modal" data-bs-target="#notificationsModal">
                    <i class="fas fa-bell me-2"></i><?php echo $lang3494; ?></a>
                </li>
                <li><a class="dropdown-item" data-bs-toggle="modal" data-bs-target="#emailModal">
                    <i class="fas fa-envelope me-2"></i><?php echo $lang3495; ?></a>
                </li>
                <li><a class="dropdown-item" data-bs-toggle="modal" data-bs-target="#webhookModal">
                    <i class="fas fa-globe me-2"></i><?php echo $lang3496; ?></a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" data-bs-toggle="modal" data-bs-target="#scheduleModal">
                    <i class="fas fa-calendar-alt me-2"></i><?php echo $lang3497; ?></a>
                </li>
            </ul>
        </div>
    </div>
</div>

<div class="app-container">
    <?php echo $menu; ?>
    <main class="main-content">
        <!-- Stats -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="stat-value" id="statTotalChecks">0</div>
                    <div class="stat-label"><?php echo $lang3498; ?></div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="stat-value text-danger" id="statProblems">0</div>
                    <div class="stat-label"><?php echo $lang3499; ?></div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="stat-value" id="statWarnings">0</div>
                    <div class="stat-label"><?php echo $lang3500; ?></div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="stat-value" id="statOk">0</div>
                    <div class="stat-label"><?php echo $lang3501; ?></div>
                </div>
            </div>
        </div>

        <!-- Notifications -->
        <div class="check-card">
            <div class="check-header" onclick="toggleSection(this)">
                <h3><i class="fas fa-bell"></i> <?php echo $lang3502; ?></h3>
                <i class="fas fa-chevron-down toggle-icon"></i>
            </div>
            <div class="check-body">
                <div id="notificationsList" style="max-height: 300px; overflow-y: auto;">
                    <div class="text-center text-muted py-4"><?php echo $lang3503; ?></div>
                </div>
                <div class="mt-3 text-end">
                    <button class="btn btn-sm btn-link" onclick="markAllRead()"><?php echo $lang3504; ?></button>
                    <button class="btn btn-sm btn-link text-danger" onclick="clearAllNotifications()"><?php echo $lang3505; ?></button>
                </div>
            </div>
        </div>

        <!-- Disks & SMART -->
        <div class="check-card">
            <div class="check-header" onclick="toggleSection(this)">
                <h3><i class="fas fa-hdd"></i> <?php echo $lang3506; ?></h3>
                <div class="header-buttons">
                    <button class="btn btn-sm btn-primary" onclick="runCheck('disks')" style="margin-right: 12px;">
                        <i class="fas fa-sync-alt"></i> <?php echo $lang3507; ?>
                    </button>
                    <i class="fas fa-chevron-down toggle-icon"></i>
                </div>
            </div>
            <div class="check-body">
                <div id="disksStatus" class="items-grid"></div>
            </div>
        </div>

        <!-- RAID Arrays -->
        <div class="check-card">
            <div class="check-header" onclick="toggleSection(this)">
                <h3><i class="fas fa-shield-alt"></i> <?php echo $lang3508; ?></h3>
                <div class="header-buttons">
                    <button class="btn btn-sm btn-primary" onclick="runCheck('raid')" style="margin-right: 12px;">
                        <i class="fas fa-sync-alt"></i> <?php echo $lang3509; ?>
                    </button>
                    <i class="fas fa-chevron-down toggle-icon"></i>
                </div>
            </div>
            <div class="check-body">
                <div id="raidStatus" class="items-grid"></div>
            </div>
        </div>

        <!-- LVM -->
        <div class="check-card">
            <div class="check-header" onclick="toggleSection(this)">
                <h3><i class="fas fa-cubes"></i> <?php echo $lang3510; ?></h3>
                <div class="header-buttons">
                    <button class="btn btn-sm btn-primary" onclick="runCheck('lvm')" style="margin-right: 12px;">
                        <i class="fas fa-sync-alt"></i> <?php echo $lang3511; ?>
                    </button>
                    <i class="fas fa-chevron-down toggle-icon"></i>
                </div>
            </div>
            <div class="check-body">
                <div id="lvmStatus" class="items-grid"></div>
            </div>
        </div>

        <!-- Temperatures -->
        <div class="check-card">
            <div class="check-header" onclick="toggleSection(this)">
                <h3><i class="fas fa-thermometer-half"></i> <?php echo $lang3512; ?></h3>
                <div class="header-buttons">
                    <button class="btn btn-sm btn-primary" onclick="runCheck('temperature')" style="margin-right: 12px;">
                        <i class="fas fa-sync-alt"></i> <?php echo $lang3513; ?>
                    </button>
                    <i class="fas fa-chevron-down toggle-icon"></i>
                </div>
            </div>
            <div class="check-body">
                <div id="temperatureStatus" class="items-grid"></div>
            </div>
        </div>

        <!-- Network Shares -->
        <div class="check-card">
            <div class="check-header" onclick="toggleSection(this)">
                <h3><i class="fas fa-share-alt"></i> <?php echo $lang3514; ?></h3>
                <div class="header-buttons">
                    <button class="btn btn-sm btn-primary" onclick="runCheck('shares')" style="margin-right: 12px;">
                        <i class="fas fa-sync-alt"></i> <?php echo $lang3515; ?>
                    </button>
                    <i class="fas fa-chevron-down toggle-icon"></i>
                </div>
            </div>
            <div class="check-body">
                <div id="sharesStatus" class="items-grid"></div>
            </div>
        </div>
    </main>
</div>

<!-- ========== MODAL: NOTIFICATION RULES ========== -->
<div class="modal fade" id="notificationsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-bell"></i> <?php echo $lang3516; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- General Settings -->
                <div class="settings-group">
                    <h4><i class="fas fa-sliders-h"></i> <?php echo $lang3517; ?></h4>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><?php echo $lang3518; ?></label>
                            <input type="number" class="form-control" id="setting_notification_cooldown_minutes" value="60" min="5" max="1440">
                            <small class="text-muted"><?php echo $lang3519; ?></small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" id="setting_notify_only_on_error">
                                <label class="form-check-label"><?php echo $lang3520; ?></label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Temperature Thresholds -->
                <div class="settings-group">
                    <h4><i class="fas fa-thermometer-half"></i> <?php echo $lang3521; ?></h4>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><?php echo $lang3522; ?> (°C)</label>
                            <input type="number" class="form-control" id="setting_cpu_temp_threshold" value="85">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><?php echo $lang3523; ?> (°C)</label>
                            <input type="number" class="form-control" id="setting_disk_temp_threshold" value="55">
                        </div>
                    </div>
                </div>
                
                <!-- Events to Notify -->
                <div class="settings-group">
                    <h4><i class="fas fa-exclamation-triangle"></i> <?php echo $lang3524; ?></h4>
                    <div class="row">
                        <div class="col-md-6 mb-2">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="setting_notify_disk_missing">
                                <label class="form-check-label"><?php echo $lang3525; ?></label>
                            </div>
                        </div>
                        <div class="col-md-6 mb-2">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="setting_notify_raid_degraded">
                                <label class="form-check-label"><?php echo $lang3526; ?></label>
                            </div>
                        </div>
                        <div class="col-md-6 mb-2">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="setting_notify_lvm_error">
                                <label class="form-check-label"><?php echo $lang3527; ?></label>
                            </div>
                        </div>
                        <div class="col-md-6 mb-2">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="setting_notify_share_down">
                                <label class="form-check-label"><?php echo $lang3528; ?></label>
                            </div>
                        </div>
                        <div class="col-md-6 mb-2">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="setting_notify_temp_critical">
                                <label class="form-check-label"><?php echo $lang3529; ?></label>
                            </div>
                        </div>
                        <div class="col-md-6 mb-2">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="setting_notify_smart_failed">
                                <label class="form-check-label"><?php echo $lang3530; ?></label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang3531; ?></button>
                <button type="button" class="btn btn-primary" onclick="saveNotificationRules()"><?php echo $lang3532; ?></button>
            </div>
        </div>
    </div>
</div>

<!-- ========== MODAL: EMAIL / SMTP ========== -->
<div class="modal fade" id="emailModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-envelope"></i> <?php echo $lang3533; ?> &amp; <?php echo $lang3534; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Enable Email -->
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="setting_email_enabled">
                    <label class="form-check-label"><?php echo $lang3535; ?></label>
                </div>
                
                <!-- Recipient -->
                <div class="mb-3">
                    <label class="form-label"><?php echo $lang3536; ?></label>
                    <input type="email" class="form-control" id="setting_email_recipient" placeholder="admin@example.com">
                </div>
                
                <hr>
                <h5><i class="fas fa-server"></i> <?php echo $lang3537; ?></h5>
                
                <div class="mb-3">
                    <label class="form-label"><?php echo $lang3538; ?></label>
                    <input type="text" class="form-control" id="setting_smtp_host" placeholder="smtp.gmail.com">
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><?php echo $lang3539; ?></label>
                        <input type="number" class="form-control" id="setting_smtp_port" value="587">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><?php echo $lang3540; ?></label>
                        <select class="form-select" id="setting_smtp_encryption">
                            <option value="tls">TLS</option>
                            <option value="ssl">SSL</option>
                            <option value=""><?php echo $lang3541; ?></option>
                        </select>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label"><?php echo $lang3542; ?></label>
                    <input type="text" class="form-control" id="setting_smtp_username" placeholder="user@example.com">
                </div>
                
				<div class="mb-3">
					<label class="form-label"><?php echo $lang3543; ?></label>
					<input type="text" class="form-control" id="setting_smtp_domain" placeholder="example.com">
					<small class="text-muted"><?php echo $lang3544; ?></small>
				</div>
				
                <div class="mb-3">
                    <label class="form-label"><?php echo $lang3545; ?></label>
                    <input type="password" class="form-control" id="setting_smtp_password">
                </div>
                
                <div class="mb-3">
                    <label class="form-label"><?php echo $lang3546; ?></label>
                    <input type="email" class="form-control" id="setting_smtp_from_email" placeholder="noreply@example.com">
                    <small class="text-muted"><?php echo $lang3547; ?></small>
                </div>
                
                <div class="mb-3">
                    <label class="form-label"><?php echo $lang3548; ?></label>
                    <input type="text" class="form-control" id="setting_smtp_from_name" value="Mini-B Health Monitor">
                </div>
                
                <div class="mb-3">
                    <button class="btn btn-outline-secondary btn-sm" onclick="testSmtp()">
                        <i class="fas fa-vial"></i> <?php echo $lang3549; ?>
                    </button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang3550; ?></button>
                <button type="button" class="btn btn-primary" onclick="saveEmailSettings()"><?php echo $lang3551; ?></button>
            </div>
        </div>
    </div>
</div>

<!-- ========== MODAL: WEBHOOK ========== -->
<div class="modal fade" id="webhookModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-globe"></i> <?php echo $lang3552; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="setting_webhook_enabled">
                    <label class="form-check-label"><?php echo $lang3553; ?></label>
                </div>
                
                <div class="mb-3">
                    <label class="form-label"><?php echo $lang3554; ?></label>
                    <input type="url" class="form-control" id="setting_webhook_url" placeholder="https://your-server.com/webhook">
                    <small class="text-muted"><?php echo $lang3555; ?></small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang3556; ?></button>
                <button type="button" class="btn btn-primary" onclick="saveWebhookSettings()"><?php echo $lang3557; ?></button>
            </div>
        </div>
    </div>
</div>

<!-- ========== MODAL: SCHEDULES & HISTORY ========== -->
<div class="modal fade" id="scheduleModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-calendar-alt"></i> <?php echo $lang3558; ?> &amp; <?php echo $lang3559; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Schedules -->
                <div class="settings-group">
                    <h4><i class="fas fa-clock"></i> <?php echo $lang3560; ?></h4>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th><?php echo $lang3561; ?></th>
                                    <th><?php echo $lang3562; ?></th>
                                    <th><?php echo $lang3563; ?></th>
                                    <th><?php echo $lang3564; ?></th>
                                    <th><?php echo $lang3565; ?></th>
                                    <th><?php echo $lang3566; ?></th>
                                    <th><?php echo $lang3567; ?></th>
                                </tr>
                            </thead>
                            <tbody id="schedulesTableBody">
                                <tr><td colspan="7" class="text-center"><?php echo $lang3568; ?></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- History -->
                <div class="settings-group">
                    <h4><i class="fas fa-history"></i> <?php echo $lang3569; ?></h4>
                    <div class="table-responsive" style="max-height: 400px;">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th><?php echo $lang3570; ?></th>
                                    <th><?php echo $lang3571; ?></th>
                                    <th><?php echo $lang3572; ?></th>
                                    <th><?php echo $lang3573; ?></th>
                                    <th><?php echo $lang3574; ?></th>
                                </tr>
                            </thead>
                            <tbody id="historyTableBody">
                                <tr><td colspan="5" class="text-center"><?php echo $lang3575; ?></td></tr>
                            </tbody>
                        </table>
                    </div>
                    <button class="btn btn-sm btn-link mt-2" onclick="loadHistory()">
                        <i class="fas fa-sync-alt"></i> <?php echo $lang3576; ?>
                    </button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang3577; ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Progress indicator -->
<div id="checkProgress" class="progress-check">
    <div class="spinner-small"></div>
    <span id="progressText"><?php echo $lang3578; ?></span>
</div>

<script src="lib/jquery-3.6.0-master/dist/jquery.min.js"></script>
<script>
window.apiConfig = <?php echo json_encode($js_config); ?>;

let currentChecks = {};

// ========== UI Helpers ==========

function toggleSection(header) {
    const body = header.nextElementSibling;
    const icon = header.querySelector('.toggle-icon');
    body.classList.toggle('open');
    icon.style.transform = body.classList.contains('open') ? 'rotate(180deg)' : 'rotate(0deg)';
}

function showProgress(show, text = '<?php echo $lang3579; ?>') {
    const el = document.getElementById('checkProgress');
    if (show) {
        el.style.display = 'flex';
        document.getElementById('progressText').innerText = text;
    } else {
        el.style.display = 'none';
    }
}

async function loadGlobalStats() {
    try {
        const data = await apiCall('get_global_stats');
        if (data.success && data.stats) {
            document.getElementById('statTotalChecks').innerText = data.stats.total || 0;
            document.getElementById('statProblems').innerText = data.stats.critical || 0;
            document.getElementById('statWarnings').innerText = data.stats.warning || 0;
            document.getElementById('statOk').innerText = data.stats.ok || 0;
        }
    } catch (e) {
        console.error('Load stats error:', e);
    }
}

function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `alert alert-${type} position-fixed bottom-0 end-0 m-3`;
    toast.style.zIndex = '1100';
    toast.style.animation = 'fadeInUp 0.3s ease';
    toast.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : (type === 'danger' ? 'fa-exclamation-circle' : 'fa-info-circle')} me-2"></i> ${message}`;
    document.body.appendChild(toast);
    setTimeout(() => {
        toast.style.animation = 'fadeOutDown 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// ========== API Calls ==========
async function apiCall(endpoint, options = {}) {
    const baseUrl = window.apiConfig.apiBaseUrl;
    const headers = {
        'Content-Type': 'application/json',
        ...options.headers
    };
    
    if (window.apiConfig.apiKey && !window.apiConfig.isLocalhost) {
        headers['X-API-Key'] = window.apiConfig.apiKey;
    }
    
    const response = await fetch(baseUrl + 'health_monitor_api.php?action=' + endpoint, {
        ...options,
        headers
    });
    
    if (!response.ok) {
        throw new Error(`API error: ${response.status}`);
    }
    
    return response.json();
}

// ========== Settings ==========
async function loadSettings() {
    try {
        const data = await apiCall('get_settings');
        if (data.success) {
            // Notification rules
            document.getElementById('setting_cpu_temp_threshold').value = data.settings.cpu_temp_threshold || 85;
            document.getElementById('setting_disk_temp_threshold').value = data.settings.disk_temp_threshold || 55;
            document.getElementById('setting_notify_disk_missing').checked = data.settings.notify_disk_missing == 1;
            document.getElementById('setting_notify_raid_degraded').checked = data.settings.notify_raid_degraded == 1;
            document.getElementById('setting_notify_lvm_error').checked = data.settings.notify_lvm_error == 1;
            document.getElementById('setting_notify_share_down').checked = data.settings.notify_share_down == 1;
            document.getElementById('setting_notify_temp_critical').checked = data.settings.notify_temp_critical == 1;
            document.getElementById('setting_notify_smart_failed').checked = data.settings.notify_smart_failed == 1;
            document.getElementById('setting_notify_only_on_error').checked = data.settings.notify_only_on_error == 1;
            document.getElementById('setting_notification_cooldown_minutes').value = data.settings.notification_cooldown_minutes || 60;
            
            // Email settings
            document.getElementById('setting_email_enabled').checked = data.settings.email_enabled == 1;
            document.getElementById('setting_email_recipient').value = data.settings.email_recipient || '';
            document.getElementById('setting_smtp_host').value = data.settings.smtp_host || '';
            document.getElementById('setting_smtp_port').value = data.settings.smtp_port || '587';
            document.getElementById('setting_smtp_username').value = data.settings.smtp_username || '';
            document.getElementById('setting_smtp_encryption').value = data.settings.smtp_encryption || 'tls';
            document.getElementById('setting_smtp_from_email').value = data.settings.smtp_from_email || '';
            document.getElementById('setting_smtp_from_name').value = data.settings.smtp_from_name || 'Mini-B Health Monitor';
			document.getElementById('setting_smtp_domain').value = data.settings.smtp_domain || '';
            
            // Webhook settings
            document.getElementById('setting_webhook_enabled').checked = data.settings.webhook_enabled == 1;
            document.getElementById('setting_webhook_url').value = data.settings.webhook_url || '';
        }
    } catch (e) {
        console.error('Load settings error:', e);
    }
}

async function saveNotificationRules() {
    const settings = {
        cpu_temp_threshold: document.getElementById('setting_cpu_temp_threshold').value,
        disk_temp_threshold: document.getElementById('setting_disk_temp_threshold').value,
        notify_disk_missing: document.getElementById('setting_notify_disk_missing').checked ? 1 : 0,
        notify_raid_degraded: document.getElementById('setting_notify_raid_degraded').checked ? 1 : 0,
        notify_lvm_error: document.getElementById('setting_notify_lvm_error').checked ? 1 : 0,
        notify_share_down: document.getElementById('setting_notify_share_down').checked ? 1 : 0,
        notify_temp_critical: document.getElementById('setting_notify_temp_critical').checked ? 1 : 0,
        notify_smart_failed: document.getElementById('setting_notify_smart_failed').checked ? 1 : 0,
        notify_only_on_error: document.getElementById('setting_notify_only_on_error').checked ? 1 : 0,
        notification_cooldown_minutes: document.getElementById('setting_notification_cooldown_minutes').value
    };
    
    try {
        await apiCall('save_settings', {
            method: 'POST',
            body: JSON.stringify(settings)
        });
        bootstrap.Modal.getInstance(document.getElementById('notificationsModal')).hide();
        showToast('<?php echo $lang3580; ?>', 'success');
    } catch (e) {
        console.error('Save error:', e);
        showToast('<?php echo $lang3581; ?>', 'danger');
    }
}

async function saveEmailSettings() {
    const smtpSettings = {
        smtp_host: document.getElementById('setting_smtp_host').value,
        smtp_port: document.getElementById('setting_smtp_port').value,
        smtp_username: document.getElementById('setting_smtp_username').value,
        smtp_password: document.getElementById('setting_smtp_password').value,
        smtp_encryption: document.getElementById('setting_smtp_encryption').value,
        smtp_from_email: document.getElementById('setting_smtp_from_email').value,
        smtp_from_name: document.getElementById('setting_smtp_from_name').value,
        smtp_domain: document.getElementById('setting_smtp_domain').value,
        email_enabled: document.getElementById('setting_email_enabled').checked ? 1 : 0,
        email_recipient: document.getElementById('setting_email_recipient').value
    };
    
    try {
        await apiCall('save_smtp_settings', {
            method: 'POST',
            body: JSON.stringify(smtpSettings)
        });
        await apiCall('save_settings', {
            method: 'POST',
            body: JSON.stringify({
                email_enabled: smtpSettings.email_enabled,
                email_recipient: smtpSettings.email_recipient
            })
        });
        bootstrap.Modal.getInstance(document.getElementById('emailModal')).hide();
        showToast('<?php echo $lang3582; ?>', 'success');
    } catch (e) {
        console.error('Save error:', e);
        showToast('<?php echo $lang3583; ?>', 'danger');
    }
}

async function saveWebhookSettings() {
    const settings = {
        webhook_enabled: document.getElementById('setting_webhook_enabled').checked ? 1 : 0,
        webhook_url: document.getElementById('setting_webhook_url').value
    };
    
    try {
        await apiCall('save_settings', {
            method: 'POST',
            body: JSON.stringify(settings)
        });
        bootstrap.Modal.getInstance(document.getElementById('webhookModal')).hide();
        showToast('<?php echo $lang3584; ?>', 'success');
    } catch (e) {
        console.error('Save error:', e);
        showToast('<?php echo $lang3585; ?>', 'danger');
    }
}

async function testSmtp() {
    const testEmail = document.getElementById('setting_email_recipient').value;
    if (!testEmail) {
        showToast('<?php echo $lang3586; ?>', 'warning');
        return;
    }
    
    const smtpSettings = {
        host: document.getElementById('setting_smtp_host').value,
        port: document.getElementById('setting_smtp_port').value,
        username: document.getElementById('setting_smtp_username').value,
        password: document.getElementById('setting_smtp_password').value,
        encryption: document.getElementById('setting_smtp_encryption').value,
        test_email: testEmail,
        from_email: document.getElementById('setting_smtp_from_email').value,
        domain: document.getElementById('setting_smtp_domain').value
    };
    
    showProgress(true, '<?php echo $lang3587; ?>');
    try {
        const data = await apiCall('test_smtp', {
            method: 'POST',
            body: JSON.stringify(smtpSettings)
        });
        
        if (data.success) {
            showToast('<?php echo $lang3588; ?>', 'success');
        } else {
            showToast('<?php echo $lang3589; ?> ' + (data.error || 'Unknown error'), 'danger');
        }
    } catch (e) {
        showToast('<?php echo $lang3590; ?>', 'danger');
    } finally {
        showProgress(false);
    }
}

// ========== Notifications ==========
async function loadNotifications() {
    try {
        const data = await apiCall('get_notifications');
        if (data.success) {
            const container = document.getElementById('notificationsList');
            if (data.notifications.length === 0) {
                container.innerHTML = '<div class="text-center text-muted py-4"><?php echo $lang3591; ?></div>';
                return;
            }
            
            container.innerHTML = data.notifications.map(n => `
                <div class="notification-item ${n.is_read ? '' : 'unread'}" onclick="markAsRead(${n.id})">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="status-badge ${n.severity === 'critical' ? 'status-critical' : (n.severity === 'warning' ? 'status-warning' : 'status-ok')} mb-1">
                                <i class="fas ${n.severity === 'critical' ? 'fa-exclamation-triangle' : (n.severity === 'warning' ? 'fa-exclamation-circle' : 'fa-info-circle')}"></i>
                                ${n.severity.toUpperCase()}
                            </span>
                            <div class="mt-1"><strong>${escapeHtml(n.title)}</strong></div>
                            <div class="text-muted small">${escapeHtml(n.message)}</div>
                            <div class="text-muted small mt-1">${n.created_at}</div>
                        </div>
                        <button class="btn btn-sm btn-link text-danger" onclick="event.stopPropagation(); deleteNotification(${n.id})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            `).join('');
            
            const unreadCount = data.notifications.filter(n => !n.is_read).length;
            document.title = unreadCount > 0 ? `(${unreadCount}) <?php echo $lang3592; ?>` : '<?php echo $lang3593; ?>';
        }
    } catch (e) {
        console.error('Load notifications error:', e);
    }
}

async function markAsRead(id) {
    try {
        await apiCall('mark_read', {
            method: 'POST',
            body: JSON.stringify({ id })
        });
        loadNotifications();
    } catch (e) {
        console.error('Mark read error:', e);
    }
}

async function markAllRead() {
    try {
        await apiCall('mark_all_read', { method: 'POST' });
        loadNotifications();
    } catch (e) {
        console.error('Mark all read error:', e);
    }
}

async function clearAllNotifications() {
    if (!confirm('<?php echo $lang3594; ?>')) return;
    try {
        await apiCall('clear_notifications', { method: 'POST' });
        loadNotifications();
    } catch (e) {
        console.error('Clear notifications error:', e);
    }
}

async function deleteNotification(id) {
    try {
        await apiCall('delete_notification', {
            method: 'POST',
            body: JSON.stringify({ id })
        });
        loadNotifications();
    } catch (e) {
        console.error('Delete notification error:', e);
    }
}

// ========== Checks ==========
async function runCheck(checkType) {
    showProgress(true, `<?php echo $lang3595; ?> ${checkType}...`);
    try {
        const data = await apiCall(`check_${checkType}`, { method: 'POST' });
        if (data.success) {
            loadAllStatuses();
            loadNotifications();
            showToast(`${checkType} <?php echo $lang3596; ?>`, 'success');
        }
    } catch (e) {
        console.error(`Check ${checkType} error:`, e);
        showToast(`<?php echo $lang3597; ?> ${checkType} <?php echo $lang3598; ?>`, 'danger');
    } finally {
        showProgress(false);
    }
}

async function runAllChecks() {
    showProgress(true, '<?php echo $lang3599; ?>');
    try {
        const data = await apiCall('check_all', { method: 'POST' });
        if (data.success) {
            loadAllStatuses();
            loadNotifications();
            showToast('<?php echo $lang3600; ?>', 'success');
        }
    } catch (e) {
        console.error('All checks error:', e);
        showToast('<?php echo $lang3601; ?>', 'danger');
    } finally {
        showProgress(false);
    }
}

async function loadAllStatuses() {
    await Promise.all([
        loadStatus('disks', 'disksStatus', renderDisksStatus),
        loadStatus('raid', 'raidStatus', renderRaidStatus),
        loadStatus('lvm', 'lvmStatus', renderLvmStatus),
        loadStatus('temperature', 'temperatureStatus', renderTemperatureStatus),
        loadStatus('shares', 'sharesStatus', renderSharesStatus)
    ]);
}

async function loadStatus(checkType, containerId, renderFn) {
    try {
        const data = await apiCall(`get_status_${checkType}`);
        if (data.success) {
            const container = document.getElementById(containerId);
            if (renderFn) {
                container.innerHTML = renderFn(data);
            }
            if (data.stats) {
                updateStatistics(data.stats);
            }
        }
    } catch (e) {
        console.error(`Load ${checkType} status error:`, e);
    }
}

function updateStatistics(stats) {
    loadGlobalStats();
}

// ========== Render Functions ==========
function renderDisksStatus(data) {
    const disks = data.disks || [];
    const dbDisks = data.db_disks || [];
    const dbDiskMap = {};
    dbDisks.forEach(d => { dbDiskMap[d.disk_name] = d; });
    
    if (disks.length === 0 && dbDisks.length === 0) {
        return '<div class="text-center text-muted py-4"><?php echo $lang3602; ?></div>';
    }
    
    let html = '';
    
    for (const disk of disks) {
        const dbDisk = dbDiskMap[disk.name];
        const isNew = dbDisk && dbDisk.is_new == 1;
        const hasProblem = disk.smart_bad_sectors > 0 || disk.smart_status === 'FAILED';
        const problemClass = hasProblem ? 'problem' : '';
        
        html += `
            <div class="item-card ${problemClass}">
                <div class="item-title">
                    <span><i class="fas fa-hdd"></i> <strong>${escapeHtml(disk.name)}</strong></span>
                    <span class="badge ${disk.smart_status === 'PASSED' ? 'bg-success' : 'bg-danger'}">${disk.smart_status || 'UNKNOWN'}</span>
                </div>
                <div class="small text-muted mb-2"><?php echo $lang3603; ?> ${escapeHtml(disk.model)} | <?php echo $lang3604; ?> ${disk.size}</div>
                <div class="small mb-2">
                    ${disk.smart_temp ? `<span>🌡️ <?php echo $lang3605; ?> ${disk.smart_temp}</span>` : ''}
                    ${disk.smart_bad_sectors > 0 ? `<span class="text-danger">⚠️ <?php echo $lang3606; ?> ${disk.smart_bad_sectors}</span>` : ''}
                </div>
                ${isNew ? `<div class="badge bg-info mb-2"><i class="fas fa-star"></i> <?php echo $lang3607; ?></div>
                    <button class="btn btn-sm btn-success w-100 mt-2" onclick="acknowledgeDisk('${disk.name}')">
                        <i class="fas fa-check"></i> <?php echo $lang3608; ?>
                    </button>` : ''}
            </div>
        `;
    }
    
    for (const dbDisk of dbDisks) {
        if (dbDisk.is_active == 1 && !disks.find(d => d.name === dbDisk.disk_name)) {
            html += `
                <div class="item-card problem">
                    <div class="item-title">
                        <span><i class="fas fa-hdd text-danger"></i> <strong>${escapeHtml(dbDisk.disk_name)}</strong></span>
                        <span class="badge bg-danger"><?php echo $lang3609; ?></span>
                    </div>
                    <div class="small text-muted mb-2"><?php echo $lang3610; ?> ${escapeHtml(dbDisk.disk_model || 'Unknown')} | <?php echo $lang3611; ?> ${dbDisk.first_seen}</div>
                    <div class="small text-danger mb-2">⚠️ <?php echo $lang3612; ?></div>
                    <button class="btn btn-sm btn-danger w-100" onclick="removeMissingDisk('${dbDisk.disk_name}')">
                        <i class="fas fa-trash"></i> <?php echo $lang3613; ?>
                    </button>
                </div>
            `;
        }
    }
    
    return html;
}

function renderRaidStatus(data) {
    const raids = data.raid || [];
    if (raids.length === 0) {
        return '<div class="text-center text-muted py-4"><?php echo $lang3614; ?></div>';
    }
    
    let html = '';
    for (const raid of raids) {
        const isDegraded = raid.degraded || raid.status === 'auto-read-only';
        const problemClass = isDegraded ? 'problem' : '';
        
        html += `
            <div class="item-card ${problemClass}">
                <div class="item-title">
                    <span><i class="fas fa-shield-alt"></i> <strong>${escapeHtml(raid.name)}</strong></span>
                    <span class="badge ${isDegraded ? 'bg-warning' : 'bg-success'}">${raid.level || 'RAID'}</span>
                </div>
                <div class="small mb-2"><?php echo $lang3615; ?> ${raid.size || 'Unknown'} | <?php echo $lang3616; ?> ${raid.status || 'unknown'}</div>
                ${raid.degraded ? `<div class="small text-warning">⚠️ <?php echo $lang3617; ?></div>` : ''}
                ${raid.failed_disks > 0 ? `<div class="small text-danger">❌ <?php echo $lang3618; ?> ${raid.failed_disks}</div>` : ''}
                ${raid.sync_percent ? `<div class="progress mt-2" style="height: 4px;"><div class="progress-bar" style="width: ${raid.sync_percent}%"></div></div>` : ''}
            </div>
        `;
    }
    return html;
}

function renderLvmStatus(data) {
    const vgs = data.vgs || [];
    const lvs = data.lvs || [];
    
    if (vgs.length === 0 && lvs.length === 0) {
        return '<div class="text-center text-muted py-4"><?php echo $lang3619; ?></div>';
    }
    
    let html = '';
    
    if (vgs.length > 0) {
        html += '<h6 class="mt-2 mb-2"><i class="fas fa-database"></i> <?php echo $lang3620; ?></h6>';
        for (const vg of vgs) {
            html += `
                <div class="item-card mb-2">
                    <div class="item-title">
                        <span><strong>${escapeHtml(vg.name)}</strong></span>
                        <span>${vg.size_formatted || vg.size}</span>
                    </div>
                    <div class="small"><?php echo $lang3621; ?> ${vg.pv_count} | <?php echo $lang3622; ?> ${vg.lv_count} | <?php echo $lang3623; ?> ${vg.free_formatted || vg.free}</div>
                </div>
            `;
        }
    }
    
    if (lvs.length > 0) {
        html += '<h6 class="mt-3 mb-2"><i class="fas fa-chart-simple"></i> <?php echo $lang3624; ?></h6>';
        for (const lv of lvs) {
            const hasProblem = !lv.active;
            const problemClass = hasProblem ? 'problem' : '';
            
            html += `
                <div class="item-card mb-2 ${problemClass}">
                    <div class="item-title">
                        <span><strong>${escapeHtml(lv.name)}</strong> <span class="small text-muted">(${escapeHtml(lv.vg_name)})</span></span>
                        <span>${lv.size_formatted || lv.size}</span>
                    </div>
                    <div class="small"><?php echo $lang3625; ?> ${lv.active ? 'Active' : 'Inactive'} | <?php echo $lang3626; ?> ${lv.path || 'N/A'}</div>
                    ${lv.mount_point ? `<div class="small"><?php echo $lang3627; ?> ${lv.mount_point}</div>` : ''}
                    ${!lv.active ? `<div class="small text-danger">⚠️ <?php echo $lang3628; ?></div>` : ''}
                </div>
            `;
        }
    }
    
    return html;
}

function renderTemperatureStatus(data) {
    const cpuTemp = data.cpu_temp;
    const diskTemps = data.disk_temps || [];
    const thresholds = data.thresholds || { cpu: 85, disk: 55 };
    
    let html = '';
    
    if (cpuTemp) {
        const tempValue = parseFloat(cpuTemp);
        const isWarning = tempValue > thresholds.cpu;
        html += `
            <div class="item-card ${isWarning ? 'problem' : ''}">
                <div class="item-title">
                    <span><i class="fas fa-microchip"></i> <strong><?php echo $lang3629; ?></strong></span>
                    <span class="badge ${isWarning ? 'bg-danger' : 'bg-success'}">${cpuTemp}</span>
                </div>
                <div class="small"><?php echo $lang3630; ?> ${thresholds.cpu}°C</div>
                ${isWarning ? `<div class="small text-danger mt-2">⚠️ <?php echo $lang3631; ?></div>` : ''}
            </div>
        `;
    }
    
    for (const disk of diskTemps) {
        const tempValue = parseFloat(disk.temp);
        const isWarning = tempValue > thresholds.disk;
        html += `
            <div class="item-card ${isWarning ? 'problem' : ''}">
                <div class="item-title">
                    <span><i class="fas fa-hdd"></i> <strong>${escapeHtml(disk.name)}</strong></span>
                    <span class="badge ${isWarning ? 'bg-danger' : 'bg-success'}">${disk.temp}</span>
                </div>
                <div class="small"><?php echo $lang3632; ?> ${thresholds.disk}°C</div>
                ${isWarning ? `<div class="small text-danger mt-2">⚠️ <?php echo $lang3633; ?></div>` : ''}
            </div>
        `;
    }
    
    if (diskTemps.length === 0 && !cpuTemp) {
        html = '<div class="text-center text-muted py-4"><?php echo $lang3634; ?></div>';
    }
    
    return html;
}

function renderSharesStatus(data) {
    const shares = data.shares || [];
    
    if (shares.length === 0) {
        return '<div class="text-center text-muted py-4"><?php echo $lang3635; ?></div>';
    }
    
    let html = '';
    for (const share of shares) {
        const isAvailable = share.is_available;
        const problemClass = !isAvailable ? 'problem' : '';
        
        html += `
            <div class="item-card ${problemClass}">
                <div class="item-title">
                    <span><i class="fas ${share.type === 'smb' ? 'fa-network-wired' : (share.type === 'nfs' ? 'fa-server' : 'fa-exchange-alt')}"></i>
                    <strong>${escapeHtml(share.name)}</strong> <span class="badge bg-secondary">${share.type.toUpperCase()}</span></span>
                    <span class="badge ${isAvailable ? 'bg-success' : 'bg-danger'}">${isAvailable ? 'Available' : 'Down'}</span>
                </div>
                <div class="small"><?php echo $lang3636; ?> ${escapeHtml(share.path || 'N/A')}</div>
                ${!isAvailable ? `<div class="small text-danger mt-2">⚠️ <?php echo $lang3637; ?> ${share.error || ''}</div>` : ''}
            </div>
        `;
    }
    return html;
}

// ========== Disk Actions ==========
async function acknowledgeDisk(diskName) {
    try {
        await apiCall('acknowledge_disk', {
            method: 'POST',
            body: JSON.stringify({ disk_name: diskName })
        });
        loadAllStatuses();
        showToast(`<?php echo $lang3638; ?> ${diskName} <?php echo $lang3639; ?>`, 'success');
    } catch (e) {
        console.error('Acknowledge disk error:', e);
    }
}

async function removeMissingDisk(diskName) {
    if (!confirm(`<?php echo $lang3640; ?> ${diskName} <?php echo $lang3641; ?>`)) return;
    try {
        await apiCall('remove_missing_disk', {
            method: 'POST',
            body: JSON.stringify({ disk_name: diskName })
        });
        loadAllStatuses();
        showToast(`<?php echo $lang3642; ?> ${diskName} <?php echo $lang3643; ?>`, 'success');
    } catch (e) {
        console.error('Remove disk error:', e);
    }
}

// ========== Schedules ==========
async function loadSchedules() {
    try {
        const data = await apiCall('get_schedules');
        if (data.success) {
            const tbody = document.getElementById('schedulesTableBody');
            tbody.innerHTML = data.schedules.map(s => `
                <tr>
                    <td><strong>${s.check_type}</strong></td>
                    <td>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" 
                                   onchange="toggleSchedule('${s.check_type}', this.checked)"
                                   ${s.enabled ? 'checked' : ''}>
                        </div>
                    </td>
                    <td>
                        <select class="form-select form-select-sm" 
                                onchange="updateScheduleInterval('${s.check_type}', this.value)"
                                style="width: auto;">
                            ${generateIntervalOptions(s.interval_seconds)}
                        </select>
                    </td>
                    <td>${s.last_run || '<?php echo $lang3644; ?>'}</td>
                    <td>${s.next_run || '<?php echo $lang3645; ?>'}</td>
                    <td>
                        <span class="badge ${s.last_status === 'success' ? 'bg-success' : (s.last_status === 'failed' ? 'bg-danger' : 'bg-secondary')}">
                            ${s.last_status || 'pending'}
                        </span>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-primary" onclick="runCheckNow('${s.check_type}')">
                            <i class="fas fa-play"></i> <?php echo $lang3646; ?>
                        </button>
                    </td>
                </tr>
            `).join('');
        }
    } catch (e) {
        console.error('Load schedules error:', e);
    }
}

function generateIntervalOptions(currentInterval) {
    const intervals = [
        { seconds: 60, label: '<?php echo $lang3647; ?>' },
        { seconds: 120, label: '<?php echo $lang3648; ?>' },
        { seconds: 300, label: '<?php echo $lang3649; ?>' },
        { seconds: 600, label: '<?php echo $lang3650; ?>' },
        { seconds: 900, label: '<?php echo $lang3651; ?>' },
        { seconds: 1800, label: '<?php echo $lang3652; ?>' },
        { seconds: 3600, label: '<?php echo $lang3653; ?>' },
        { seconds: 7200, label: '<?php echo $lang3654; ?>' },
        { seconds: 14400, label: '<?php echo $lang3655; ?>' },
        { seconds: 21600, label: '<?php echo $lang3656; ?>' },
        { seconds: 43200, label: '<?php echo $lang3657; ?>' },
        { seconds: 86400, label: '<?php echo $lang3658; ?>' },
        { seconds: 172800, label: '<?php echo $lang3659; ?>' },
        { seconds: 604800, label: '<?php echo $lang3660; ?>' }
    ];
    
    return intervals.map(i => 
        `<option value="${i.seconds}" ${currentInterval == i.seconds ? 'selected' : ''}>${i.label}</option>`
    ).join('');
}

async function toggleSchedule(checkType, enabled) {
    try {
        await apiCall('update_schedule', {
            method: 'POST',
            body: JSON.stringify({
                check_type: checkType,
                enabled: enabled ? 1 : 0
            })
        });
        showToast(`${checkType} <?php echo $lang3661; ?> ${enabled ? '<?php echo $lang3662; ?>' : '<?php echo $lang3663; ?>'}`, 'success');
        loadSchedules();
    } catch (e) {
        console.error('Toggle schedule error:', e);
    }
}

async function updateScheduleInterval(checkType, interval) {
    try {
        await apiCall('update_schedule', {
            method: 'POST',
            body: JSON.stringify({
                check_type: checkType,
                enabled: 1,
                interval_seconds: parseInt(interval)
            })
        });
        showToast(`${checkType} <?php echo $lang3664; ?>`, 'success');
        loadSchedules();
    } catch (e) {
        console.error('Update interval error:', e);
    }
}

async function runCheckNow(checkType) {
    showProgress(true, `<?php echo $lang3665; ?> ${checkType} <?php echo $lang3666; ?>`);
    try {
        const data = await apiCall('run_check_now', {
            method: 'POST',
            body: JSON.stringify({ check_type: checkType })
        });
        if (data.success) {
            showToast(`${checkType} <?php echo $lang3667; ?>`, 'success');
            loadSchedules();
            loadHistory();
        }
    } catch (e) {
        console.error('Run check error:', e);
        showToast(`<?php echo $lang3668; ?> ${checkType} <?php echo $lang3669; ?>`, 'danger');
    } finally {
        showProgress(false);
    }
}

// ========== History ==========
async function loadHistory() {
    try {
        const data = await apiCall('get_check_history&limit=30');
        if (data.success) {
            const tbody = document.getElementById('historyTableBody');
            if (data.history.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center"><?php echo $lang3670; ?></td></tr>';
                return;
            }
            
            tbody.innerHTML = data.history.map(h => `
                <tr>
                    <td>${h.created_at}</td>
                    <td><strong>${h.check_type}</strong></td>
                    <td>
                        <span class="badge ${h.status === 'success' ? 'bg-success' : (h.status === 'failed' ? 'bg-danger' : 'bg-info')}">
                            ${h.status}
                        </span>
                    </td>
                    <td>${escapeHtml(h.message || '-')}</td>
                    <td>${h.duration_ms ? h.duration_ms + 'ms' : '-'}</td>
                </tr>
            `).join('');
        }
    } catch (e) {
        console.error('Load history error:', e);
    }
}

// ========== Host Selector ==========
async function loadHosts() {
    try {
        const response = await fetch('/api/hosts_api.php?action=get_hosts');
        const data = await response.json();
        if (data.success && data.hosts) {
            const selector = document.getElementById('hostSelector');
            const currentHostId = <?php echo $current_host_id; ?>;
            
            selector.innerHTML = data.hosts.map(h => 
                `<option value="${h.idHost}" ${h.idHost == currentHostId ? 'selected' : ''}>
                    ${h.hostName} ${h.idHost == 1 ? '(Local)' : ''}
                </option>`
            ).join('');
            
            selector.addEventListener('change', function() {
                const hostId = this.value;
                window.location.href = `?set_host=${hostId}`;
            });
        }
    } catch (e) {
        console.error('Load hosts error:', e);
    }
}

// ========== Init ==========
async function init() {
    loadSettings();
    loadNotifications();
    loadAllStatuses();
    loadSchedules();
    loadHistory();
    //loadHosts();
	loadGlobalStats();
    
    // Auto-refresh every 60 seconds
    setInterval(() => {
        loadNotifications();
        loadAllStatuses();
        loadSchedules();
    }, 60000);
}

init();

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    @keyframes fadeOutDown {
        from {
            opacity: 1;
            transform: translateY(0);
        }
        to {
            opacity: 0;
            transform: translateY(20px);
        }
    }
`;
document.head.appendChild(style);
</script>
</body>
</html>