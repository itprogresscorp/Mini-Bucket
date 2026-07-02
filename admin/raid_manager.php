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
    <title>RAID Manager - Mini-B</title>
    <link href="lib/bootstrap-5.3.8-dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="lib/fontawesome-free-6.7.2-web/css/all.min.css">
    <script src="lib/bootstrap-5.3.8-dist/js/bootstrap.bundle.min.js"></script>
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
        :root {
            --apple-blue: #007aff;
            --apple-green: #34c759;
            --apple-red: #ff3b30;
            --apple-gray: #8e8e93;
            --apple-dark: #1c1c1e;
            --apple-light-gray: #f5f5f7;
            --apple-orange: #ff9f0a;
            --apple-purple: #5856d6;
            --raid-color: #5856d6;
            --disk-color: #4A90D9;
            --lvm-color: #9013FE;
        }
        
        * { box-sizing: border-box; }
        
        body { background: var(--apple-light-gray); font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; }
        
        .apple-preloader { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255,255,255,0.95); z-index: 9999; display: flex; flex-direction: column; justify-content: center; align-items: center; }
        .apple-spinner { width: 40px; height: 40px; border: 3px solid #e5e5e5; border-top-color: var(--apple-blue); border-radius: 50%; animation: spin 0.8s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        
        .loader-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            z-index: 10000;
            display: flex;
            justify-content: center;
            align-items: center;
            display: none;
        }
        .loader-content {
            background: white;
            padding: 30px 40px;
            border-radius: 20px;
            text-align: center;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        
        .raid-layout { display: flex; gap: 24px;}
        .devices-sidebar { width: 320px; flex-shrink: 0; background: white; border-radius: 20px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); height: fit-content; max-height: 90vh; overflow-y: auto; }
        .raid-main { flex: 1; }
        
        .device-item { padding: 12px; border-radius: 12px; margin-bottom: 8px; cursor: pointer; transition: all 0.2s; background: var(--apple-light-gray); border: 1px solid #e5e5ea; }
        .device-item:hover { background: #e5e5ea; transform: translateX(2px); }
        .device-item .device-name { font-weight: 600; font-size: 14px; }
        .device-item .device-size { font-size: 12px; opacity: 0.7; }
        .device-item .device-status { font-size: 11px; margin-top: 4px; }
        .device-item.used-in-lvm { border-left: 3px solid var(--lvm-color); }
        .device-item.used-in-raid { border-left: 3px solid var(--raid-color); }
        .device-item.available { border-left: 3px solid var(--apple-green); }
        
        .raid-card { background: white; border-radius: 20px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); transition: transform 0.2s; cursor: pointer; }
        .raid-card:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.1); }
        .raid-header { border-bottom: 2px solid #f0f0f0; padding-bottom: 10px; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        
        .raid-progress { height: 8px; background: #e5e5ea; border-radius: 4px; overflow: hidden; margin: 10px 0; }
        .raid-progress-fill { background: var(--apple-green); height: 100%; transition: width 0.3s; }
        .raid-progress-fill.checking { background: var(--apple-orange); }
        .raid-progress-fill.repair { background: var(--apple-red); }
        .raid-progress-fill.recovery { background: var(--apple-blue); }
        .raid-progress-fill.reshape { background: var(--apple-purple); }
        
        .disk-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 6px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
        }
		
		.raid-grid {
			display: grid;
			grid-template-columns: repeat(auto-fill, minmax(390px, 1fr));
			gap: 20px;
			margin-top: 5px;
		}

		.raid-card {
			background: white;
			border-radius: 20px;
			padding: 20px;
			box-shadow: 0 2px 10px rgba(0,0,0,0.05);
			transition: transform 0.2s, box-shadow 0.2s;
			cursor: pointer;
			margin-bottom: 0;
			height: 100%;
			display: flex;
			flex-direction: column;
		}
        .disk-status-active { background: #e8f5e9; color: #2e7d32; }
        .disk-status-failed { background: #ffebee; color: #c62828; }
        .disk-status-spare { background: #fff3e0; color: #e65100; }
        
        .legend { display: flex; gap: 10px; flex-wrap: wrap; padding: 5px; background: white; border-radius: 12px; cursor: pointer; transition: all 0.2s; }
        .legend-item { display: flex; align-items: center; gap: 8px; font-size: 12px; }
        .legend-color { width: 20px; height: 20px; border-radius: 4px; }
        
        .btn-apple { background: var(--apple-blue); color: white; border: none; border-radius: 10px; padding: 8px 16px; font-weight: 500; transition: all 0.2s; }
        .btn-apple:hover { background: #0051d5; transform: scale(1.02); color: white; }
        .btn-apple-danger { background: var(--apple-red); }
        .btn-apple-danger:hover { background: #cc2f27; }
        .btn-apple-success { background: var(--apple-green); }
        .btn-apple-success:hover { background: #2aa84a; }
        .btn-apple-warning { background: var(--apple-orange); color: white; }
        .btn-apple-warning:hover { background: #e68a00; color: white; }
        
        .modal-content { border-radius: 20px; border: none; }
        .modal-header { border-bottom: 1px solid #e5e5ea; background: var(--apple-light-gray); border-radius: 20px 20px 0 0; }
        
        .device-badge { display: inline-block; background: #e5e5ea; border-radius: 6px; padding: 4px 8px; margin: 2px; font-size: 12px; }
        .device-badge.spare { background: var(--apple-orange); color: white; }
        .device-badge.failed { background: var(--apple-red); color: white; }
        .device-badge.lvm { background: var(--lvm-color); color: white; }
        
        .raid-level-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .raid-level-0 { background: #007aff; color: white; }
        .raid-level-1 { background: #34c759; color: white; }
        .raid-level-5 { background: #ff9f0a; color: white; }
        .raid-level-6 { background: #5856d6; color: white; }
        .raid-level-10 { background: #ff2d55; color: white; }
        
        .diagram-container {
            background: #1a1a2e;
            min-height: 500px;
            border-radius: 20px;
            padding: 30px;
            position: relative;
            overflow-x: auto;
        }
        .connection-diagram {
            position: relative;
            display: flex;
            flex-direction: column;
            gap: 40px;
        }
        .physical-layer {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            justify-content: center;
            padding: 20px;
            background: rgba(74,144,217,0.1);
            border-radius: 16px;
            position: relative;
        }
        .raid-layer {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            justify-content: center;
            padding: 20px;
            background: rgba(88,86,214,0.1);
            border-radius: 16px;
            position: relative;
        }
        .level-label {
            background: rgba(255,255,255,0.1);
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            color: #aaa;
            font-weight: 600;
            letter-spacing: 1px;
            display: inline-block;
            margin-bottom: 15px;
        }
        .physical-device {
            background: linear-gradient(135deg, var(--disk-color), #2c3e6e);
            border-radius: 12px;
            padding: 10px 15px;
            min-width: 120px;
            text-align: center;
            position: relative;
            cursor: pointer;
            transition: all 0.2s;
        }
        .physical-device:hover { transform: translateY(-3px); box-shadow: 0 4px 15px rgba(0,0,0,0.3); }
        .physical-device .device-name { font-weight: bold; color: white; font-size: 14px; }
        .physical-device .device-size { font-size: 10px; color: rgba(255,255,255,0.6); }
        .physical-device.lvm-used { border: 2px solid var(--lvm-color); }
        .physical-device.raid-used { border: 2px solid var(--raid-color); }
        
        .partition-badge {
            background: rgba(255,255,255,0.2);
            border-radius: 6px;
            padding: 4px 8px;
            margin: 4px;
            font-size: 10px;
            cursor: pointer;
            display: inline-block;
        }
        .partition-badge.lvm-used { background: rgba(144,19,254,0.5); }
        .partition-badge.raid-used { background: rgba(88,86,214,0.5); }
        
        .raid-card-diagram {
            background: rgba(88,86,214,0.15);
            border-radius: 16px;
            padding: 15px;
            min-width: 280px;
            border: 1px solid var(--raid-color);
            cursor: pointer;
            position: relative;
        }
        .raid-card-diagram:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.2); }
        .raid-header-diagram {
            text-align: center;
            color: var(--raid-color);
            font-weight: bold;
            margin-bottom: 10px;
        }
        .device-in-raid {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: center;
            margin: 10px 0;
            padding: 8px;
            background: rgba(74,144,217,0.1);
            border-radius: 10px;
        }
        .device-badge-diagram {
            background: linear-gradient(135deg, var(--disk-color), #2c3e6e);
            border-radius: 8px;
            padding: 5px 10px;
            font-size: 11px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .device-badge-diagram.spare { background: var(--apple-orange); }
        .device-badge-diagram.failed { background: var(--apple-red); }
        
        .connection-arrow {
            position: relative;
            text-align: center;
            padding: 10px 0;
            color: #888;
            font-size: 20px;
        }
        .connection-arrow::before {
            content: "↓";
            font-size: 24px;
            color: var(--raid-color);
        }
        
        .sync-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
            margin-left: 8px;
        }
        .sync-badge.checking { background: var(--apple-orange); color: white; }
        .sync-badge.repair { background: var(--apple-red); color: white; }
        .sync-badge.recovery { background: var(--apple-blue); color: white; }
        .sync-badge.reshape { background: var(--apple-purple); color: white; }
        
        .alert-lvm {
            background: #f3e5f5;
            border-left: 4px solid var(--lvm-color);
            padding: 10px 15px;
            border-radius: 10px;
            margin: 10px 0;
        }
        
        .toast-notification {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 9999;
            background: white;
            border-radius: 12px;
            padding: 12px 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.3s ease;
        }
        .toast-notification.success { border-left: 4px solid var(--apple-green); }
        .toast-notification.error { border-left: 4px solid var(--apple-red); }
        .toast-notification.warning { border-left: 4px solid var(--apple-orange); }
        
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        @media (max-width: 768px) {
            .raid-layout { flex-direction: column; }
            .devices-sidebar { width: 100%; max-height: 300px; }
        }
        
        .broken-section {
            margin-top: 20px;
            margin-bottom: 24px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            overflow: hidden;
            transition: all 0.2s ease;
        }
        .broken-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 20px;
            background: #fffaf5;
            cursor: pointer;
            border-left: 4px solid var(--apple-red);
            transition: background 0.2s;
        }
        .broken-header:hover {
            background: #fff2ef;
        }
        .broken-title {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .broken-title i {
            font-size: 20px;
            color: var(--apple-red);
        }
        .broken-title h5 {
            margin: 0;
            font-weight: 600;
            font-size: 16px;
            color: #1c1c1e;
        }
        .broken-badge {
            background: var(--apple-red);
            color: white;
            border-radius: 30px;
            padding: 2px 10px;
            font-size: 13px;
            font-weight: 600;
        }
        .broken-chevron {
            color: var(--apple-gray);
            transition: transform 0.2s;
        }
        .broken-chevron.rotated {
            transform: rotate(180deg);
        }
        .broken-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            background: #fffaf5;
            border-top: 1px solid #ffe0d6;
        }
        .broken-content.expanded {
            max-height: 2000px;
            padding: 20px;
        }
        .broken-raid-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 16px;
        }
        .broken-raid-card {
            background: white;
            border-radius: 16px;
            padding: 16px;
            border: 1px solid #ffe0d6;
            transition: all 0.2s;
            box-shadow: 0 1px 2px rgba(0,0,0,0.03);
        }
        .broken-raid-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(255,59,48,0.08);
            border-color: #ffcdd2;
        }
        .broken-disk-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin: 12px 0;
        }
        .broken-disk-item {
            background: #fff0ed;
            border-radius: 12px;
            padding: 8px 12px;
            font-size: 13px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: all 0.2s;
            border: 1px solid #ffded6;
        }
        .broken-disk-item:hover {
            background: #ffe0da;
            transform: scale(1.02);
        }
        .broken-disk-item i {
            color: var(--apple-red);
            font-size: 14px;
        }
        .empty-broken-box {
            text-align: center;
            padding: 24px;
            background: #f8f9fa;
            border-radius: 16px;
            color: #2e7d32;
        }
        .empty-broken-box i {
            font-size: 32px;
            margin-bottom: 8px;
        }
        .broken-action-btn {
            background: white;
            border: 1px solid #ffcdd2;
            color: #c62828;
            border-radius: 10px;
            padding: 6px 12px;
            font-size: 12px;
            transition: all 0.2s;
        }
        .broken-action-btn:hover {
            background: #ffebee;
            border-color: var(--apple-red);
        }
		
		@media (max-width: 768px) {
			.raid-grid {
				grid-template-columns: 1fr;
				gap: 16px;
			}
		}
		
		.raid-level-badge {
			display: inline-flex;
			align-items: center;
			gap: 6px;
			padding: 6px 14px;
			border-radius: 30px;
			font-size: 13px;
			font-weight: 600;
			box-shadow: 0 1px 2px rgba(0,0,0,0.1);
		}
		
.raid-card.critical {
    animation: criticalPulse 1.5s ease-in-out infinite;
    border: 2px solid var(--apple-red);
    background: linear-gradient(145deg, #fff5f5, #ffffff);
    box-shadow: 0 0 15px rgba(255, 59, 48, 0.3);
}

.raid-card.warning {
    animation: warningPulse 2s ease-in-out infinite;
    border: 2px solid var(--apple-orange);
    background: linear-gradient(145deg, #fffaf0, #ffffff);
}

@keyframes criticalPulse {
    0% {
        border-color: var(--apple-red);
        box-shadow: 0 0 0 0 rgba(255, 59, 48, 0.4);
        background: #fff5f5;
    }
    50% {
        border-color: #ff6b6b;
        box-shadow: 0 0 0 8px rgba(255, 59, 48, 0);
        background: #ffe0e0;
    }
    100% {
        border-color: var(--apple-red);
        box-shadow: 0 0 0 0 rgba(255, 59, 48, 0);
        background: #fff5f5;
    }
}

@keyframes warningPulse {
    0% {
        border-color: var(--apple-orange);
        box-shadow: 0 0 0 0 rgba(255, 159, 10, 0.3);
    }
    50% {
        border-color: #ffb347;
        box-shadow: 0 0 0 6px rgba(255, 159, 10, 0);
    }
    100% {
        border-color: var(--apple-orange);
        box-shadow: 0 0 0 0 rgba(255, 159, 10, 0);
    }
}

.raid-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
}

.raid-status-badge.active {
    background: #e8f5e9;
    color: #2e7d32;
}

.raid-status-badge.degraded {
    background: #fff3e0;
    color: #e65100;
}

.raid-status-badge.failed, .raid-status-badge.critical {
    background: #ffebee;
    color: #c62828;
    animation: statusBadgePulse 1s ease-in-out infinite;
}

@keyframes statusBadgePulse {
    0% { opacity: 1; background: #ffebee; }
    50% { opacity: 0.8; background: #ffcdd2; }
    100% { opacity: 1; background: #ffebee; }
}

.raid-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
}

.raid-status-badge.active {
    background: #e8f5e9;
    color: #2e7d32;
}

.raid-status-badge.degraded {
    background: #fff3e0;
    color: #e65100;
}

.raid-status-badge.critical {
    background: #ffebee;
    color: #c62828;
    animation: statusBadgePulse 1s ease-in-out infinite;
}

@keyframes statusBadgePulse {
    0% { opacity: 1; background: #ffebee; }
    50% { opacity: 0.8; background: #ffcdd2; }
    100% { opacity: 1; background: #ffebee; }
}

.legend-item-card {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px 12px;
    background: var(--apple-light-gray);
    border-radius: 12px;
    transition: transform 0.2s;
}
.legend-item-card:hover {
    transform: translateX(3px);
}
.legend-color-box {
    width: 32px;
    height: 32px;
    border-radius: 8px;
}

.raid-legend-card {
    background: var(--apple-light-gray);
    border-radius: 16px;
    padding: 15px;
    transition: all 0.2s;
    height: 100%;
}
.raid-legend-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.raid-legend-icon {
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    color: white;
    font-size: 18px;
}

.raid-schema {
    background: white;
    border-radius: 12px;
    padding: 12px;
    text-align: center;
    margin: 10px 0;
}

.disk-schema {
    display: inline-block;
    background: #4A90D9;
    color: white;
    padding: 4px 10px;
    border-radius: 8px;
    font-size: 12px;
    margin: 2px;
}

.disk-schema-small {
    display: inline-block;
    background: #4A90D9;
    color: white;
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 11px;
    margin: 2px;
}

.disk-schema-small.parity {
    background: #ff9f0a;
}

.schema-plus, .schema-equal {
    display: inline-block;
    font-size: 16px;
    font-weight: bold;
    margin: 0 4px;
    color: var(--apple-gray);
}

.schema-arrow {
    font-size: 20px;
    color: var(--apple-gray);
    margin: 4px 0;
}

.raid-result {
    background: #e8f5e9;
    border-radius: 8px;
    padding: 6px;
    font-size: 12px;
    font-weight: 600;
    color: #2e7d32;
}

.raid10-schema {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    flex-wrap: wrap;
}

.mirror-group {
    background: #e3f2fd;
    border-radius: 8px;
    padding: 6px 10px;
    font-size: 12px;
}

.status-legend {
    display: flex;
    align-items: center;
    padding: 6px 12px;
    background: white;
    border-radius: 10px;
}

.blinking {
    animation: statusBadgePulse 1s ease-in-out infinite;
}
    </style>
</head>
<body>
<div id="applePreloader" class="apple-preloader"><div class="apple-spinner"></div><div class="mt-2"><?php echo $lang12; ?></div></div>
<div id="globalLoader" class="loader-overlay"><div class="loader-content"><div class="apple-spinner mx-auto"></div><p class="mt-2"><?php echo $lang1274; ?></p></div></div>

<div class="top-bar">
    <div class="top-bar-left">
        <h1><i class="fas fa-bucket"></i> Mini-B</h1>
    </div>
    <div class="top-bar-right">
        <i class="fas fa-server"></i> RAID Manager
		<!--<div class="legend" onclick="showLegendModal()">
                <div class="legend-item"><div class="legend-color" style="background: #5856d6;"></div><span>RAID array</span></div>
                <div class="legend-item"><div class="legend-color" style="background: #4A90D9;"></div><span>Disk/partition</span></div>
                <div class="legend-item"><div class="legend-color" style="background: #9013FE;"></div><span>Used in LVM</span></div>
                <div class="legend-item"><div class="legend-color" style="background: #ff9f0a;"></div><span>Spare disk</span></div>
                <div class="legend-item"><div class="legend-color" style="background: #ff3b30;"></div><span>Failed disk</span></div>
                <div class="legend-item"><i class="fas fa-chevron-right"></i><span>Click for details</span></div>
            </div>-->
			<div class="host-selector" style="margin-left: 20px;">
            <select id="hostSelector" style="background: rgba(255,255,255,0.9); border: 1px solid #ddd; border-radius: 20px; padding: 6px 30px 6px 15px; font-size: 14px; cursor: pointer;">
                <option value=""><?php echo $lang12; ?></option>
            </select>
        </div>
			<button class="btn btn-sm btn-apple-success" onclick="raidManager.showCreateRaid()"><i class="fas fa-plus"></i> RAID</button>
	<div class="btn-group">
                <button class="btn btn-sm btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-bars"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="#" onclick="raidManager.showBlockDiagram()"><i class="fas fa-project-diagram ms-2"></i> <?php echo $lang1275; ?></a></li>
                    <li><a class="dropdown-item" href="#" onclick="showLegendModal()"><i class="fas fa-info ms-2"></i> <?php echo $lang1276; ?></a></li>
                    
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="#" onclick="raidManager.refreshAll(true)"><i class="fas fa-sync-alt ms-2"></i> <?php echo $lang1277; ?></a></li>
                </ul>
            </div>	
	</div>
</div>

<div class="app-container">
    <?php echo $menu; ?>
    <main class="main-content">

        <div class="raid-layout">
            <div class="devices-sidebar">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0"><i class="fas fa-hdd"></i> <?php echo $lang1278; ?></h5>
                </div>
                <hr class="my-2">
                <div id="devicesList"></div>
            </div>
            
            <div class="raid-main">
                <div id="brokenRaidsWrapper" class="broken-section" style="display: none;">
                    <div class="broken-header" onclick="raidManager.toggleBrokenAccordion()">
                        <div class="broken-title">
                            <i class="fas fa-exclamation-triangle"></i>
                            <h5><?php echo $lang1279; ?></h5>
                            <span class="broken-badge" id="brokenCount">0</span>
                        </div>
                        <div class="btn-group" onclick="event.stopPropagation()">
                            <button class="btn broken-action-btn" onclick="raidManager.cleanupAllBrokenRaids()">
                                <i class="fas fa-broom"></i> <?php echo $lang1280; ?>
                            </button>
                        </div>
                        <i class="fas fa-chevron-down broken-chevron" id="brokenChevron"></i>
                    </div>
                    <div class="broken-content" id="brokenAccordionContent">
                        <div id="brokenRaidsContainer"></div>
                    </div>
                </div>
                
                <div id="raidContainer" class="raid-grid"></div>
            </div>
        </div>
    </main>
</div>

<!-- Модальные окна -->
<div class="modal fade" id="legendModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-info-circle"></i> <?php echo $lang1281; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Color legend -->
                <div class="legend-section mb-4">
                    <h6 class="fw-bold mb-3"><i class="fas fa-palette"></i> <?php echo $lang1282; ?></h6>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="legend-item-card">
                                <div class="legend-color-box" style="background: #5856d6;"></div>
                                <div><strong><?php echo $lang1283; ?></strong><br><small class="text-muted"><?php echo $lang1284; ?></small></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="legend-item-card">
                                <div class="legend-color-box" style="background: #4A90D9;"></div>
                                <div><strong><?php echo $lang1285; ?></strong><br><small class="text-muted"><?php echo $lang1286; ?></small></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="legend-item-card">
                                <div class="legend-color-box" style="background: #9013FE;"></div>
                                <div><strong><?php echo $lang1287; ?></strong><br><small class="text-muted"><?php echo $lang1288; ?></small></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="legend-item-card">
                                <div class="legend-color-box" style="background: #ff9f0a;"></div>
                                <div><strong><?php echo $lang1289; ?></strong><br><small class="text-muted"><?php echo $lang1290; ?></small></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="legend-item-card">
                                <div class="legend-color-box" style="background: #ff3b30;"></div>
                                <div><strong><?php echo $lang1291; ?></strong><br><small class="text-muted"><?php echo $lang1292; ?></small></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="legend-item-card">
                                <div class="legend-color-box" style="background: #34c759;"></div>
                                <div><strong><?php echo $lang1293; ?></strong><br><small class="text-muted"><?php echo $lang1294; ?></small></div>
                            </div>
                        </div>
                    </div>
                </div>

                <hr>

                <!-- RAID levels schema -->
                <div class="legend-section">
                    <h6 class="fw-bold mb-3"><i class="fas fa-chart-simple"></i> <?php echo $lang1295; ?></h6>
                    <div class="row g-3">
                        <!-- RAID 0 -->
                        <div class="col-md-6 col-lg-4">
                            <div class="raid-legend-card">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <span class="raid-legend-icon raid-level-0"><i class="fas fa-grip-lines"></i></span>
                                    <span class="fw-bold">RAID 0</span>
                                    <span class="badge bg-secondary ms-auto"><?php echo $lang1296; ?></span>
                                </div>
                                <div class="raid-schema">
                                    <div class="disk-schema"><i class="fas fa-hdd"></i> <?php echo $lang1297; ?></div>
                                    <div class="schema-plus">+</div>
                                    <div class="disk-schema"><i class="fas fa-hdd"></i> <?php echo $lang1298; ?></div>
                                    <div class="schema-arrow">↓</div>
                                    <div class="raid-result"><i class="fas fa-bolt"></i> <?php echo $lang1299; ?></div>
                                </div>
                                <div class="small text-muted mt-2">
                                    <i class="fas fa-rocket text-success"></i> <?php echo $lang1300; ?><br>
                                    <i class="fas fa-skull text-danger"></i> <?php echo $lang1301; ?><br>
                                    <i class="fas fa-hdd"></i> <?php echo $lang1302; ?>
                                </div>
                            </div>
                        </div>

                        <!-- RAID 1 -->
                        <div class="col-md-6 col-lg-4">
                            <div class="raid-legend-card">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <span class="raid-legend-icon raid-level-1"><i class="fas fa-copy"></i></span>
                                    <span class="fw-bold">RAID 1</span>
                                    <span class="badge bg-secondary ms-auto"><?php echo $lang1303; ?></span>
                                </div>
                                <div class="raid-schema">
                                    <div class="disk-schema"><i class="fas fa-hdd"></i> <?php echo $lang1297; ?></div>
                                    <div class="schema-equal">⇄</div>
                                    <div class="disk-schema"><i class="fas fa-hdd"></i> <?php echo $lang1298; ?></div>
                                    <div class="schema-arrow">↓</div>
                                    <div class="raid-result"><i class="fas fa-clone"></i> <?php echo $lang1303; ?></div>
                                </div>
                                <div class="small text-muted mt-2">
                                    <i class="fas fa-shield-alt text-success"></i> <?php echo $lang1304; ?><br>
                                    <i class="fas fa-balance-scale"></i> <?php echo $lang1305; ?><br>
                                    <i class="fas fa-hdd"></i> <?php echo $lang1306; ?>
                                </div>
                            </div>
                        </div>

                        <!-- RAID 5 -->
                        <div class="col-md-6 col-lg-4">
                            <div class="raid-legend-card">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <span class="raid-legend-icon raid-level-5"><i class="fas fa-chart-line"></i></span>
                                    <span class="fw-bold">RAID 5</span>
                                    <span class="badge bg-secondary ms-auto"><?php echo $lang1307; ?></span>
                                </div>
                                <div class="raid-schema">
                                    <div class="d-flex gap-1 justify-content-center">
                                        <div class="disk-schema-small"><i class="fas fa-hdd"></i>1</div>
                                        <div class="disk-schema-small"><i class="fas fa-hdd"></i>2</div>
                                        <div class="disk-schema-small"><i class="fas fa-hdd"></i>3</div>
                                        <div class="disk-schema-small parity"><i class="fas fa-calculator"></i>P</div>
                                    </div>
                                    <div class="schema-arrow">↓</div>
                                    <div class="raid-result"><i class="fas fa-table"></i> <?php echo $lang1308; ?></div>
                                </div>
                                <div class="small text-muted mt-2">
                                    <i class="fas fa-chart-line text-success"></i> <?php echo $lang1309; ?><br>
                                    <i class="fas fa-database"></i> <?php echo $lang1310; ?><br>
                                    <i class="fas fa-hdd"></i> <?php echo $lang1311; ?>
                                </div>
                            </div>
                        </div>

                        <!-- RAID 6 -->
                        <div class="col-md-6 col-lg-4">
                            <div class="raid-legend-card">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <span class="raid-legend-icon raid-level-6"><i class="fas fa-chart-simple"></i></span>
                                    <span class="fw-bold">RAID 6</span>
                                    <span class="badge bg-secondary ms-auto"><?php echo $lang1312; ?></span>
                                </div>
                                <div class="raid-schema">
                                    <div class="d-flex gap-1 justify-content-center">
                                        <div class="disk-schema-small"><i class="fas fa-hdd"></i>1</div>
                                        <div class="disk-schema-small"><i class="fas fa-hdd"></i>2</div>
                                        <div class="disk-schema-small parity"><i class="fas fa-calculator"></i>P</div>
                                        <div class="disk-schema-small parity"><i class="fas fa-calculator"></i>Q</div>
                                    </div>
                                    <div class="schema-arrow">↓</div>
                                    <div class="raid-result"><i class="fas fa-table"></i> <?php echo $lang1313; ?></div>
                                </div>
                                <div class="small text-muted mt-2">
                                    <i class="fas fa-shield-alt text-success"></i> <?php echo $lang1314; ?><br>
                                    <i class="fas fa-microchip"></i> <?php echo $lang1315; ?><br>
                                    <i class="fas fa-hdd"></i> <?php echo $lang1316; ?>
                                </div>
                            </div>
                        </div>

                        <!-- RAID 10 -->
                        <div class="col-md-6 col-lg-4">
                            <div class="raid-legend-card">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <span class="raid-legend-icon raid-level-10"><i class="fas fa-layer-group"></i></span>
                                    <span class="fw-bold">RAID 10</span>
                                    <span class="badge bg-secondary ms-auto"><?php echo $lang1317; ?></span>
                                </div>
                                <div class="raid-schema">
                                    <div class="raid10-schema">
                                        <div class="mirror-group">[<i class="fas fa-hdd"></i>1 ⇄ <i class="fas fa-hdd"></i>2]</div>
                                        <div class="schema-plus">+</div>
                                        <div class="mirror-group">[<i class="fas fa-hdd"></i>3 ⇄ <i class="fas fa-hdd"></i>4]</div>
                                    </div>
                                    <div class="schema-arrow">↓</div>
                                    <div class="raid-result"><i class="fas fa-bolt"></i> <?php echo $lang1318; ?></div>
                                </div>
                                <div class="small text-muted mt-2">
                                    <i class="fas fa-tachometer-alt text-success"></i> <?php echo $lang1319; ?><br>
                                    <i class="fas fa-chart-pie"></i> <?php echo $lang1320; ?><br>
                                    <i class="fas fa-hdd"></i> <?php echo $lang1321; ?>
                                </div>
                            </div>
                        </div>

                        <!-- LINEAR (JBOD) -->
                        <div class="col-md-6 col-lg-4">
                            <div class="raid-legend-card">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <span class="raid-legend-icon" style="background: #8e8e93;"><i class="fas fa-grip-vertical"></i></span>
                                    <span class="fw-bold">LINEAR / JBOD</span>
                                    <span class="badge bg-secondary ms-auto"><?php echo $lang1322; ?></span>
                                </div>
                                <div class="raid-schema">
                                    <div class="d-flex gap-1 justify-content-center">
                                        <div class="disk-schema-small"><i class="fas fa-hdd"></i>1</div>
                                        <div class="schema-plus">+</div>
                                        <div class="disk-schema-small"><i class="fas fa-hdd"></i>2</div>
                                        <div class="schema-plus">+</div>
                                        <div class="disk-schema-small"><i class="fas fa-hdd"></i>3</div>
                                    </div>
                                    <div class="schema-arrow">↓</div>
                                    <div class="raid-result"><i class="fas fa-database"></i> <?php echo $lang1323; ?></div>
                                </div>
                                <div class="small text-muted mt-2">
                                    <i class="fas fa-expand-alt"></i> <?php echo $lang1324; ?><br>
                                    <i class="fas fa-skull text-danger"></i> <?php echo $lang1325; ?><br>
                                    <i class="fas fa-hdd"></i> <?php echo $lang1326; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <hr class="my-3">

                <!-- RAID statuses -->
                <div class="legend-section">
                    <h6 class="fw-bold mb-2"><i class="fas fa-heartbeat"></i> <?php echo $lang1327; ?></h6>
                    <div class="row g-3">
                        <div class="col-md-3">
                            <div class="status-legend">
                                <span class="badge bg-success"><i class="fas fa-check-circle"></i> Clean</span>
                                <span class="small ms-2"><?php echo $lang1329; ?></span>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="status-legend">
                                <span class="badge bg-warning"><i class="fas fa-exclamation-triangle"></i> AUTO-READ-ONLY</span>
                                <span class="small ms-2"><?php echo $lang1331; ?></span>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="status-legend">
                                <span class="badge bg-danger blinking"><i class="fas fa-fire"></i> DEGRADED</span>
                                <span class="small ms-2"><?php echo $lang1328; ?></span>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="status-legend">
                                <span class="badge bg-secondary"><i class="fas fa-pause-circle"></i> Stopped</span>
                                <span class="small ms-2"><?php echo $lang1330; ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-apple" data-bs-dismiss="modal"><?php echo $lang1332; ?></button>
            </div>
        </div>
    </div>
</div>


<div class="modal fade" id="blockDiagramModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-project-diagram"></i> <?php echo $lang1333; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="max-height: 80vh; overflow-y: auto; background: #0f0f1a;">
                <div id="blockDiagramContent" class="diagram-container">
                    <div class="text-center p-4 text-white"><?php echo $lang1334; ?></div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang1335; ?></button>
                <button class="btn btn-apple" onclick="raidManager.refreshBlockDiagram()"><i class="fas fa-sync-alt"></i> <?php echo $lang1336; ?></button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="raidInfoModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><span id="raidInfoTitle"><?php echo $lang1337; ?></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="raidInfoContent">
                <div class="text-center p-4"><?php echo $lang1338; ?></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang1339; ?></button>
                <button class="btn btn-apple-warning" id="checkRaidBtn"><?php echo $lang1340; ?></button>
                <button class="btn btn-apple-warning" id="repairRaidBtn"><?php echo $lang1341; ?></button>
                <button class="btn btn-apple-danger" id="stopRaidBtn"><?php echo $lang1342; ?></button>
                <button class="btn btn-apple" id="startRaidBtn"><?php echo $lang1343; ?></button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="deviceInfoModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-hdd"></i> <span id="deviceInfoTitle"><?php echo $lang1344; ?></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="deviceInfoContent"></div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang1345; ?></button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="createRaidModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-server"></i> <?php echo $lang1346; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning mb-3">
                    <i class="fas fa-exclamation-triangle"></i> 
                    <strong><?php echo $lang1347; ?></strong> <?php echo $lang1348; ?>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <label class="form-label"><?php echo $lang1349; ?></label>
                        <input type="text" class="form-control mb-3" id="raidName" placeholder="md0">
                        <div class="text-muted small"><?php echo $lang1350; ?> md0, md1, md2, etc.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><?php echo $lang1351; ?></label>
                        <select class="form-select mb-3" id="raidLevel">
                            <option value="0">RAID 0 <?php echo $lang1352; ?></option>
                            <option value="1" selected>RAID 1 <?php echo $lang1353; ?></option>
                            <option value="5">RAID 5 - <?php echo $lang1354; ?></option>
                            <option value="6">RAID 6 - <?php echo $lang1355; ?></option>
                            <option value="10">RAID 10 - <?php echo $lang1356; ?></option>
                            <option value="linear">Linear (JBOD) - <?php echo $lang1357; ?></option>
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <label class="form-label"><?php echo $lang1358; ?></label>
                        <select class="form-select mb-3" id="raidChunk">
                            <option value=""><?php echo $lang1359; ?> (512K)</option>
                            <option value="64">64K</option>
                            <option value="128">128K</option>
                            <option value="256">256K</option>
                            <option value="512">512K</option>
                            <option value="1024">1024K</option>
                        </select>
                    </div>
                </div>
                <label class="form-label"><?php echo $lang1360; ?></label>
                <div id="raidDevicesList" class="border rounded p-2 mb-3" style="max-height: 250px; overflow-y: auto;"></div>
                <div id="raidSpareBlock">
                    <label class="form-label"><?php echo $lang1361; ?></label>
                    <div id="raidSpareList" class="border rounded p-2" style="max-height: 150px; overflow-y: auto;"></div>
                    <div class="text-muted small mt-1"><?php echo $lang1362; ?></div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang1363; ?></button>
                <button class="btn btn-apple-danger" onclick="raidManager.createRaid()"><?php echo $lang1364; ?></button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addDeviceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus"></i> <?php echo $lang1365; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="addDeviceRaidName">
                <label class="form-label"><?php echo $lang1366; ?></label>
                <select class="form-select" id="addDeviceSelect"></select>
                <div class="alert alert-info mt-3 small">
                    <i class="fas fa-info-circle"></i> <?php echo $lang1367; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang1368; ?></button>
                <button class="btn btn-apple" onclick="raidManager.addDeviceToRaid()"><?php echo $lang1369; ?></button>
            </div>
        </div>
    </div>
</div>

<script src="lib/jquery-3.6.0-master/dist/jquery.min.js"></script>
<script>
const url = "<?php echo $current_host_id == 1 ? '/api/' : rtrim($host_url, '/') . '/'; ?>";

// ==================== RAID Manager Class ====================
class RAIDManager {
    constructor() {
        this.data = { raid: [], available_devices: [] };
        this.currentRaidInfo = null;
        this.updateInterval = null;
        this.healthInterval = null;
        this.isRefreshing = false;
        this.raidElements = new Map();
        this.deviceElements = new Map();
        this.pendingRequest = null;
        
        this.init();
    }
    
    async init() {
        await this.refreshAll(true);
        this.startLiveUpdates();
        this.startHealthMonitoring();
    }
    
    showLoader() {
        document.getElementById('globalLoader').style.display = 'flex';
    }
    
    hideLoader() {
        document.getElementById('globalLoader').style.display = 'none';
    }
    
    showToast(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `toast-notification ${type}`;
        toast.innerHTML = `
            <i class="fas fa-${type === 'error' ? 'exclamation-circle' : type === 'warning' ? 'exclamation-triangle' : 'check-circle'}"></i>
            <span>${message}</span>
        `;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 4000);
    }
    
    async apiCall(action, data = {}, timeout = 15000) {
		try {
			const controller = new AbortController();
			const timeoutId = setTimeout(() => controller.abort(), timeout);
			
			const headers = { 'Content-Type': 'application/json' };
			if (window.apiConfig && window.apiConfig.apiKey) {
				headers['X-API-Key'] = window.apiConfig.apiKey;
			}
			
			const res = await fetch(url + 'raid_api.php', {
				method: 'POST',
				headers: headers,
				body: JSON.stringify({ action, ...data }),
				signal: controller.signal
			});
			
			clearTimeout(timeoutId);
			return await res.json();
		} catch (e) {
			if (e.name === 'AbortError') {
				return { success: false, error: '<?php echo $lang1370; ?>' };
			}
			return { success: false, error: e.message };
		}
	}
    
    toggleBrokenAccordion() {
        const content = document.getElementById('brokenAccordionContent');
        const chevron = document.getElementById('brokenChevron');
        if (!content || !chevron) return;
        if (content.classList.contains('expanded')) {
            content.classList.remove('expanded');
            chevron.classList.remove('rotated');
        } else {
            content.classList.add('expanded');
            chevron.classList.add('rotated');
        }
    }
    
    openBrokenAccordion() {
        const content = document.getElementById('brokenAccordionContent');
        const chevron = document.getElementById('brokenChevron');
        if (!content || !chevron) return;
        if (!content.classList.contains('expanded')) {
            content.classList.add('expanded');
            chevron.classList.add('rotated');
        }
    }
    
    closeBrokenAccordion() {
        const content = document.getElementById('brokenAccordionContent');
        const chevron = document.getElementById('brokenChevron');
        if (!content || !chevron) return;
        if (content.classList.contains('expanded')) {
            content.classList.remove('expanded');
            chevron.classList.remove('rotated');
        }
    }
    
    async refreshAll(showPreloader = false) {
        if (this.isRefreshing) return;
        this.isRefreshing = true;
        if (showPreloader) {
            document.getElementById('applePreloader').style.display = 'flex';
        }
        try {
            const res = await this.apiCall('get_all_raid', {}, 10000);
            if (res.success) {
                this.data = res;
                this.updateDevicesIncremental(res.available_devices);
                this.updateRaidIncremental(res.raid, []);
                await this.getBrokenRaids();
            } else {
                this.showToast(res.error || '<?php echo $lang1371; ?>', 'error');
            }
        } catch (e) {
            this.showToast('<?php echo $lang1372; ?>' + e.message, 'error');
        } finally {
            this.isRefreshing = false;
            if (showPreloader) {
                document.getElementById('applePreloader').style.display = 'none';
            }
        }
    }
    
    hasRaidChanged(oldRaid, newRaid) {
        return oldRaid.sync_percent !== newRaid.sync_percent ||
               oldRaid.sync_action !== newRaid.sync_action ||
               oldRaid.status !== newRaid.status ||
               oldRaid.working_disks !== newRaid.working_disks ||
               oldRaid.failed_disks !== newRaid.failed_disks ||
               oldRaid.degraded !== newRaid.degraded ||
               oldRaid.size !== newRaid.size ||
               oldRaid.in_lvm !== newRaid.in_lvm;
    }
    
    updateRaidIncremental(changedRaids, deletedRaids) {
		const container = document.getElementById('raidContainer');
		
		for (const name of deletedRaids) {
			const element = this.raidElements.get(name);
			if (element) {
				element.remove();
				this.raidElements.delete(name);
			}
		}
		
		for (const raid of changedRaids) {
			const existingElement = this.raidElements.get(raid.name);
			if (existingElement) {
				this.updateRaidCard(existingElement, raid);
			} else {
				const newCard = this.createRaidCard(raid);
				container.appendChild(newCard);
				this.raidElements.set(raid.name, newCard);
			}
		}
		
		if (this.data.raid.length === 0 && changedRaids.length === 0 && deletedRaids.length === 0) {
			container.innerHTML = `
				<div class="raid-card text-center" style="grid-column: 1/-1;">
					<i class="fas fa-server fa-3x text-muted mb-3"></i>
					<h5><?php echo $lang1373; ?></h5>
					<p class="text-muted"><?php echo $lang1374; ?></p>
					<button class="btn btn-apple" onclick="raidManager.showCreateRaid()"><i class="fas fa-plus"></i> <?php echo $lang1375; ?></button>
				</div>
			`;
			this.raidElements.clear();
		}
	}
    
    createRaidCard(raid) {
		const div = document.createElement('div');
		div.className = 'raid-card';
		div.setAttribute('data-raid', raid.name);
		div.onclick = (e) => {
			if (!e.target.closest('.btn-group') && !e.target.closest('button')) {
				this.showRaidInfo(raid.name);
			}
		};
		this.updateRaidCard(div, raid);
		return div;
	}
    
    getDiskStateBadge(state) {
        if (state === 'active' || state === 'in_sync') {
            return '<span class="disk-status-badge disk-status-active"><i class="fas fa-check-circle"></i> <?php echo $lang1376; ?></span>';
        } else if (state === 'spare' || state === 'spare rebuilding') {
            return '<span class="disk-status-badge disk-status-spare"><i class="fas fa-clock"></i> <?php echo $lang1377; ?></span>';
        } else if (state === 'faulty' || state === 'failed') {
            return '<span class="disk-status-badge disk-status-failed"><i class="fas fa-exclamation-triangle"></i> <?php echo $lang1378; ?></span>';
        } else if (state === 'removed') {
            return '<span class="disk-status-badge disk-status-failed"><i class="fas fa-minus-circle"></i> <?php echo $lang1379; ?></span>';
        }
        return `<span class="disk-status-badge">${state}</span>`;
    }
    
    updateRaidCard(element, raid) {
    let criticalClass = '';
    let customStatusBadge = '';
    
    const healthState = (raid.health_state || raid.status || '').toLowerCase();
    const isAutoReadOnly = raid.read_only || healthState.includes('auto-read-only') || healthState.includes('auto') || healthState.includes('readonly') || healthState === 'read-only';
    const isInactive = healthState === 'inactive';
    const isFaulty = healthState === 'faulty';
    const isDegraded = raid.degraded || (raid.failed_disks > 0) || (raid.working_disks < raid.total_disks);
    
    if (isFaulty) {
        criticalClass = 'critical';
        customStatusBadge = '<span class="raid-status-badge critical"><i class="fas fa-skull-crosswalk"></i> <?php echo $lang1380; ?></span>';
    } 
    else if (isDegraded) {
        criticalClass = 'critical';
        if (isAutoReadOnly) {
            customStatusBadge = '<span class="raid-status-badge critical"><i class="fas fa-skull-crosswalk"></i> <?php echo $lang1381; ?></span>';
        } else {
            customStatusBadge = '<span class="raid-status-badge critical"><i class="fas fa-exclamation-triangle"></i> <?php echo $lang1382; ?></span>';
        }
    }
    else if (isAutoReadOnly) {
        criticalClass = 'warning';
        customStatusBadge = '<span class="raid-status-badge degraded"><i class="fas fa-lock"></i> <?php echo $lang1383; ?></span>';
    }
    else if (isInactive) {
        criticalClass = 'warning';
        customStatusBadge = '<span class="raid-status-badge degraded"><i class="fas fa-stop-circle"></i> <?php echo $lang1384; ?></span>';
    }
    else {
        criticalClass = '';
        customStatusBadge = '<span class="raid-status-badge active"><i class="fas fa-check-circle"></i> <?php echo $lang1385; ?></span>';
    }
    
    element.classList.remove('critical', 'warning');
    if (criticalClass) {
        element.classList.add(criticalClass);
    }
    
    if (criticalClass) {
        element.classList.add(criticalClass);
    } else {
        element.classList.remove('critical', 'warning');
    }
    
    const levelClass = this.getRaidLevelClass(raid.level);
    const syncPercent = raid.sync_percent ? parseInt(raid.sync_percent) : 0;
    const syncAction = raid.sync_action;
    const isRaid0 = raid.level === 'RAID 0' || raid.level === 'raid0' || raid.level === '0';
    const isLinear = raid.level === 'LINEAR' || raid.level === 'linear';
    const supportsSync = !(isRaid0 || isLinear);
    const hasSync = supportsSync && syncAction && syncAction !== 'idle' && syncPercent > 0;
    
    const oldStatusBadge = (isDegraded && !customStatusBadge) ? '<span class="badge bg-danger ms-2">⚠️ DEGRADED</span>' : 
                            (raid.health_state === 'clean' ? '<span class="badge bg-success ms-2">✓ Clean</span>' : '');
    
    const finalStatusBadge = customStatusBadge || oldStatusBadge;
    
    let progressClass = '';
    if (syncAction === 'check' || syncAction === 'checking') progressClass = 'checking';
    else if (syncAction === 'repair') progressClass = 'repair';
    else if (syncAction === 'recover' || syncAction === 'recovery') progressClass = 'recovery';
    else if (syncAction === 'reshape') progressClass = 'reshape';
    
    const lvmWarning = raid.in_lvm ? 
        `<div class="alert-lvm mt-2 mb-2">
            <i class="fas fa-cubes"></i> <strong><?php echo $lang1386; ?></strong> <?php echo $lang1387; ?> (VG: ${raid.lvm_vg || 'unknown'})
            <br><small><?php echo $lang1388; ?> <a href="lvm_manager.php" target="_blank"><?php echo $lang1389; ?></a></small>
        </div>` : '';
    const partitionWarning = raid.has_partitions ? 
        `<div class="alert alert-warning mt-2 mb-2">
            <i class="fas fa-layer-group"></i> <strong><?php echo $lang1390; ?></strong> <?php echo $lang1391; ?>
            <br><small><?php echo $lang1392; ?></small>
        </div>` : '';
    
    let devicesHtml = '';
    if (raid.devices && raid.devices.length) {
        for (const dev of raid.devices) {
            const spareClass = dev.spare ? 'spare' : '';
            let diskState = '';
            if (raid.disk_states) {
                const diskInfo = raid.disk_states.find(d => d.device === '/dev/' + dev.name);
                if (diskInfo) {
                    diskState = this.getDiskStateBadge(diskInfo.state);
                }
            }
            devicesHtml += `<span class="device-badge ${spareClass}">${dev.name} ${diskState}${dev.spare ? ' (spare)' : ''}</span>`;
        }
    } else {
        devicesHtml = '<span class="text-muted"><?php echo $lang1393; ?></span>';
    }
    
    let activeDisksText = raid.working_disks || raid.devices?.length || '?';
    let totalDisksText = (raid.working_disks + (raid.failed_disks || 0)) || raid.devices?.length || '?';
    const isActive = raid.status !== 'inactive';
    
    element.innerHTML = `
        <div class="raid-header">
            <div>
                <strong><i class="fas fa-server"></i> ${raid.name}</strong>
                <span class="raid-level-badge ${levelClass} ms-2">${raid.level}</span>
                ${finalStatusBadge}
                ${hasSync ? this.getSyncBadge(syncAction, syncPercent) : ''}
                ${!isActive ? '<span class="badge bg-secondary ms-2"><i class="fas fa-pause-circle"></i> <?php echo $lang1394; ?></span>' : ''}
            </div>
            <div class="btn-group" onclick="event.stopPropagation()">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-cog"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="#" onclick="raidManager.showRaidInfo('${raid.name}'); return false;"><i class="fas fa-info-circle me-2"></i><?php echo $lang1395; ?></a></li>
                    <li><a class="dropdown-item" href="#" onclick="raidManager.showAddDevice('${raid.name}'); return false;"><i class="fas fa-plus me-2"></i><?php echo $lang1396; ?></a></li>
                    ${isAutoReadOnly && !isDegraded ? 
                        `<li><a class="dropdown-item" href="#" onclick="raidManager.activateRaid('${raid.name}'); return false;"><i class="fas fa-play-circle me-2"></i><?php echo $lang1397; ?></a></li>` : ''
                    }
                    <li><hr class="dropdown-divider"></li>
                    ${isActive ? 
                        `<li><a class="dropdown-item" href="#" onclick="raidManager.stopRaid('${raid.name}'); return false;"><i class="fas fa-stop me-2"></i><?php echo $lang1398; ?></a></li>` :
                        `<li><a class="dropdown-item" href="#" onclick="raidManager.startRaid('${raid.name}'); return false;"><i class="fas fa-play me-2"></i><?php echo $lang1399; ?></a></li>`
                    }
                    ${supportsSync ? `
                        <li><a class="dropdown-item" href="#" onclick="raidManager.checkRaid('${raid.name}')"><i class="fas fa-stethoscope me-2"></i><?php echo $lang1400; ?></a></li>
                        <li><a class="dropdown-item" href="#" onclick="raidManager.repairRaid('${raid.name}')"><i class="fas fa-wrench me-2"></i><?php echo $lang1401; ?></a></li>
                    ` : ''}
                    ${!raid.in_lvm ? 
                        `<li><a class="dropdown-item text-danger" href="#" onclick="raidManager.deleteRaid('${raid.name}'); return false;"><i class="fas fa-trash me-2"></i><?php echo $lang1402; ?></a></li>` :
                        `<li><a class="dropdown-item text-muted disabled" href="#"><i class="fas fa-trash me-2"></i><?php echo $lang1403; ?></a></li>`
                    }
                </ul>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="mb-2">
                    <div class="small text-muted"><?php echo $lang1404; ?></div>
                    <strong>${raid.size || 'N/A'}</strong>
                </div>
                <div class="mb-2">
                    <div class="small text-muted"><?php echo $lang1405; ?></div>
                    <div>${devicesHtml}</div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-2">
                    <div class="small text-muted"><?php echo $lang1406; ?></div>
                    <div>${raid.health_state || raid.status || 'active'}${isAutoReadOnly ? ' (read-only)' : ''}</div>
                </div>
                <div class="mb-2">
                    <div class="small text-muted"><?php echo $lang1407; ?></div>
                    <div><?php echo $lang1408; ?> ${activeDisksText} / <?php echo $lang1409; ?> ${totalDisksText}</div>
                    ${raid.failed_disks > 0 ? `<div class="text-danger"><?php echo $lang1410; ?> ${raid.failed_disks}</div>` : ''}
                </div>
            </div>
        </div>
        ${hasSync ? `
        <div class="mt-2">
            <div class="d-flex justify-content-between small mb-1">
                <span>${syncAction === 'check' ? 'Check' : syncAction === 'repair' ? 'Repair' : syncAction === 'recovery' ? 'Recovery' : syncAction === 'reshape' ? 'Reshape' : 'Sync'}</span>
                <span>${syncPercent}%</span>
            </div>
            <div class="raid-progress">
                <div class="raid-progress-fill ${progressClass}" style="width: ${syncPercent}%"></div>
            </div>
            ${raid.sync_time_left ? `<div class="small text-muted"><?php echo $lang1411; ?> ${raid.sync_time_left}</div>` : ''}
            ${raid.sync_speed ? `<div class="small text-muted"><?php echo $lang1412; ?> ${raid.sync_speed}</div>` : ''}
        </div>
        ` : ''}
        <div class="mt-3" onclick="event.stopPropagation()">
            ${lvmWarning}
            ${partitionWarning}
        </div>
    `;
}
    
    updateDevicesIncremental(devices) {
        const container = document.getElementById('devicesList');
        const newDeviceMap = new Map(devices.map(d => [d.name, d]));
        for (const [name, element] of this.deviceElements) {
            if (!newDeviceMap.has(name)) {
                element.remove();
                this.deviceElements.delete(name);
            }
        }
        for (const device of devices) {
            const existingElement = this.deviceElements.get(device.name);
            if (existingElement) {
                this.updateDeviceItem(existingElement, device);
            } else {
                const newItem = this.createDeviceItem(device);
                container.appendChild(newItem);
                this.deviceElements.set(device.name, newItem);
            }
        }
        const sorted = Array.from(container.children).sort((a, b) => {
            const aAvailable = a.classList.contains('available');
            const bAvailable = b.classList.contains('available');
            if (aAvailable !== bAvailable) return aAvailable ? -1 : 1;
            return (a.getAttribute('data-name') || '').localeCompare(b.getAttribute('data-name') || '');
        });
        sorted.forEach(child => container.appendChild(child));
    }
    
    createDeviceItem(device) {
        const div = document.createElement('div');
        div.className = 'device-item';
        div.setAttribute('data-name', device.name);
        div.onclick = () => this.showDeviceInfo(device.name);
        this.updateDeviceItem(div, device);
        return div;
    }
    
    updateDeviceItem(element, device) {
        let statusText = '';
        let statusColor = '';
        let extraClass = '';
        if (device.is_lvm) {
            extraClass = 'used-in-lvm';
            statusText = '<i class="fas fa-cubes"></i> <?php echo $lang1413; ?>';
            statusColor = 'text-info';
        } else if (device.is_raid) {
            extraClass = 'used-in-raid';
            statusText = '<i class="fas fa-server"></i> <?php echo $lang1414; ?>';
            statusColor = 'text-primary';
        } else if (device.is_raid_array) {
            extraClass = 'used-in-raid';
            statusText = '<i class="fas fa-server"></i> <?php echo $lang1415; ?>';
            statusColor = 'text-primary';
        } else if (device.available) {
            extraClass = 'available';
            statusText = '<i class="fas fa-check-circle"></i> <?php echo $lang1416; ?>';
            statusColor = 'text-success';
        } else if (device.is_mounted) {
            statusText = '<i class="fas fa-mount"></i> <?php echo $lang1417; ?>';
            statusColor = 'text-danger';
        } else if (device.has_filesystem) {
            statusText = '<i class="fas fa-folder"></i> <?php echo $lang1418; ?>';
            statusColor = 'text-warning';
        } else {
            statusText = '<i class="fas fa-times-circle"></i> <?php echo $lang1419; ?>';
            statusColor = 'text-danger';
        }
        element.className = `device-item ${extraClass}`;
        element.setAttribute('data-name', device.name);
        element.innerHTML = `
            <div class="device-name"><i class="fas fa-hdd"></i> ${device.name}</div>
            <div class="device-size">${device.size}</div>
            <div class="small"><?php echo $lang1420; ?> ${device.type === 'disk' ? 'Disk' : (device.type === 'partition' ? 'Partition' : device.type)}</div>
            <div class="device-status ${statusColor}">${statusText}</div>
            ${device.parent_disk ? `<div class="small text-muted"><?php echo $lang1421; ?> ${device.parent_disk}</div>` : ''}
        `;
    }
    
    async getBrokenRaids() {
        try {
            const res = await this.apiCall('get_broken_raids', {}, 5000);
            if (res.success && res.broken_raids) {
                this.renderBrokenRaids(res.broken_raids);
            }
        } catch (e) {
            console.error('Error getting broken raids:', e);
        }
    }
    
    renderBrokenRaids(brokenRaids) {
        const wrapper = document.getElementById('brokenRaidsWrapper');
        const container = document.getElementById('brokenRaidsContainer');
        const countSpan = document.getElementById('brokenCount');
        if (!wrapper || !container) return;
        
        if (!brokenRaids || brokenRaids.length === 0) {
            wrapper.style.display = 'none';
            return;
        }
        
        wrapper.style.display = 'block';
        if (countSpan) countSpan.textContent = brokenRaids.length;
        this.openBrokenAccordion();
        
        let html = `<div class="broken-raid-grid">`;
        for (const raid of brokenRaids) {
            let devicesHtml = '';
            for (const disk of raid.devices) {
                devicesHtml += `
                    <div class="broken-disk-item" onclick="event.stopPropagation(); raidManager.cleanDiskFromBroken('${disk.name}', '${raid.name}')">
                        <i class="fas fa-hdd"></i>
                        <strong>${disk.name}</strong>
                        ${disk.spare ? '<span class="badge bg-warning ms-1" style="font-size: 10px;"><?php echo $lang1422; ?></span>' : ''}
                        <small class="text-muted ms-1"><?php echo $lang1423; ?> ${disk.slot}</small>
                    </div>
                `;
            }
            html += `
                <div class="broken-raid-card">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <i class="fas fa-exclamation-triangle text-danger fa-lg"></i>
                            <strong class="text-danger ms-2">${raid.name}</strong>
                            <span class="badge bg-danger ms-2"><?php echo $lang1424; ?></span>
                        </div>
                        <button class="btn broken-action-btn" onclick="event.stopPropagation(); raidManager.cleanBrokenRaid('${raid.name}')" title="Clean entire RAID">
                            <i class="fas fa-trash-alt"></i> <?php echo $lang1425; ?>
                        </button>
                    </div>
                    <div class="mb-2">
                        <small class="text-muted"><?php echo $lang1426; ?> ${raid.size}</small>
                    </div>
                    <div class="mb-2">
                        <strong><?php echo $lang1427; ?> (${raid.devices.length}):</strong>
                        <div class="broken-disk-list mt-2">${devicesHtml}</div>
                    </div>
                    <div class="alert alert-warning small mt-2 mb-0">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo $lang1428; ?>
                    </div>
                </div>
            `;
        }
        html += `</div>`;
        container.innerHTML = html;
    }
    
    async cleanDiskFromBroken(diskName, raidName) {
        if (!confirm(`<i class="fas fa-exclamation-triangle"></i> <?php echo $lang1429; ?> /dev/${diskName}\n\n<?php echo $lang1430; ?> ${raidName}\n\n3 <?php echo $lang1431; ?>\n1. <?php echo $lang1432; ?>\n2. <?php echo $lang1433; ?>\n3. <?php echo $lang1434; ?> (50MB)\n\n<?php echo $lang1435; ?>`)) return;
        this.showLoader();
        this.showToast(`<?php echo $lang1442; ?> ${diskName}...`, 'info');
        const res = await this.apiCall('force_clean_disk', { disk: diskName }, 120000);
        this.hideLoader();
        if (res.success) {
            this.showToast(`<i class="fas fa-check-circle"></i> <?php echo $lang1436; ?> ${diskName} <?php echo $lang1437; ?>`, 'success');
            await this.refreshAll(true);
            await this.getBrokenRaids();
        } else {
            this.showToast(`<i class="fas fa-times-circle"></i> <?php echo $lang1438; ?> ${diskName}: ${res.error}`, 'error');
        }
    }
    
    async cleanBrokenRaid(raidName) {
        if (!confirm(`<i class="fas fa-exclamation-triangle"></i> <?php echo $lang1439; ?> ${raidName}\n\n<?php echo $lang1440; ?>\n\n<?php echo $lang1441; ?>`)) return;
        this.showLoader();
        this.showToast(`<?php echo $lang1443; ?> ${raidName}...`, 'info');
        const res = await this.apiCall('get_broken_raids', {}, 5000);
        if (res.success && res.broken_raids) {
            const raid = res.broken_raids.find(r => r.name === raidName);
            if (raid && raid.devices.length > 0) {
                let cleaned = 0, failed = 0;
                for (const disk of raid.devices) {
                    this.showToast(`<?php echo $lang1444; ?> ${disk.name}...`, 'info');
                    const cleanRes = await this.apiCall('force_clean_disk', { disk: disk.name }, 120000);
                    if (cleanRes.success) cleaned++; else failed++;
                    await new Promise(r => setTimeout(r, 1000));
                }
                await this.apiCall('clear_md_array', { name: raidName }, 30000);
                this.hideLoader();
                this.showToast(`<i class="fas fa-check-circle"></i> <?php echo $lang1445; ?> ${cleaned}, errors: ${failed}`, cleaned > 0 ? 'success' : 'error');
                await this.refreshAll(true);
                await this.getBrokenRaids();
            } else {
                this.hideLoader();
                this.showToast('<?php echo $lang1446; ?>', 'error');
            }
        } else {
            this.hideLoader();
            this.showToast('<?php echo $lang1447; ?>', 'error');
        }
    }
    
    async cleanupAllBrokenRaids() {
        if (!confirm(`<i class="fas fa-exclamation-triangle"></i> <?php echo $lang1448; ?>\n\n<?php echo $lang1449; ?>\n\n<?php echo $lang1450; ?>`)) return;
        this.showLoader();
        this.showToast('<?php echo $lang1451; ?>', 'info');
        const res = await this.apiCall('get_broken_raids', {}, 5000);
        if (res.success && res.broken_raids && res.broken_raids.length > 0) {
            let totalCleaned = 0;
            let totalRaids = res.broken_raids.length;
            for (const raid of res.broken_raids) {
                this.showToast(`<?php echo $lang1452; ?> ${raid.name} (${raid.devices.length} <?php echo $lang1453; ?>)...`, 'info');
                for (const disk of raid.devices) {
                    const cleanRes = await this.apiCall('force_clean_disk', { disk: disk.name }, 120000);
                    if (cleanRes.success) totalCleaned++;
                    await new Promise(r => setTimeout(r, 500));
                }
                await this.apiCall('clear_md_array', { name: raid.name }, 30000);
            }
            this.hideLoader();
            this.showToast(`<i class="fas fa-check-circle"></i> <?php echo $lang1454; ?> ${totalCleaned} <?php echo $lang1455; ?> ${totalRaids} <?php echo $lang1456; ?>`, 'success');
            await this.refreshAll(true);
            await this.getBrokenRaids();
        } else {
            this.hideLoader();
            this.showToast('<?php echo $lang1457; ?>', 'info');
        }
    }
    
    async startHealthMonitoring() {
        if (this.healthInterval) clearInterval(this.healthInterval);
        this.healthInterval = setInterval(async () => {
            if (document.visibilityState === 'visible' && this.data.raid.length > 0 && !this.isRefreshing) {
                for (const raid of this.data.raid) {
                    const healthRes = await this.apiCall('get_raid_health', { name: raid.name }, 5000);
                    if (healthRes.success && healthRes.health) {
                        const needsUpdate = raid.degraded !== healthRes.health.degraded ||
                                           raid.failed_disks !== healthRes.health.failed_disks ||
                                           raid.sync_percent !== healthRes.health.sync_progress;
                        if (needsUpdate) {
                            raid.degraded = healthRes.health.degraded;
                            raid.failed_disks = healthRes.health.failed_disks;
                            raid.working_disks = healthRes.health.working_disks || raid.working_disks;
                            raid.sync_percent = healthRes.health.sync_progress;
                            raid.sync_action = healthRes.health.sync_action;
                            raid.health_state = healthRes.health.state;
                            const element = this.raidElements.get(raid.name);
                            if (element) this.updateRaidCard(element, raid);
                            if (raid.degraded && !healthRes.health.degraded) {
                                this.showToast(`<i class="fas fa-exclamation-triangle"></i> <?php echo $lang1458; ?> ${raid.name} <?php echo $lang1459; ?>`, 'warning');
                            }
                            if (raid.failed_disks > 0 && healthRes.health.failed_disks > raid.failed_disks) {
                                this.showToast(`<i class="fas fa-times-circle"></i> <?php echo $lang1461; ?> ${raid.name}: <?php echo $lang1460; ?>`, 'error');
                            }
                        }
                    }
                }
            }
        }, 8000);
    }
    
    getRaidLevelClass(level) {
        level = level.toLowerCase();
        if (level.includes('raid0') || level === '0') return 'raid-level-0';
        if (level.includes('raid1') || level === '1') return 'raid-level-1';
        if (level.includes('raid5') || level === '5') return 'raid-level-5';
        if (level.includes('raid6') || level === '6') return 'raid-level-6';
        if (level.includes('raid10') || level === '10') return 'raid-level-10';
        return 'raid-level-0';
    }
    
    getSyncBadge(action, percent) {
        if (!action || action === 'idle') return '';
        let label = '';
        switch(action) {
            case 'check': case 'checking': label = 'Check'; break;
            case 'repair': label = 'Repair'; break;
            case 'recover': case 'recovery': label = 'Recovery'; break;
            case 'reshape': label = 'Reshape'; break;
            default: label = action;
        }
        return `<span class="sync-badge ${action}">${label} ${percent || 0}%</span>`;
    }
    
    async showRaidInfo(raidName) {
        const modal = new bootstrap.Modal(document.getElementById('raidInfoModal'));
        const content = document.getElementById('raidInfoContent');
        const title = document.getElementById('raidInfoTitle');
        title.innerHTML = `<i class="fas fa-server"></i> ${raidName}`;
        content.innerHTML = '<div class="text-center p-4"><div class="apple-spinner mx-auto"></div><p class="mt-2"><?php echo $lang1462; ?></p></div>';
        modal.show();
        const res = await this.apiCall('get_raid_info', { name: raidName }, 10000);
        if (res.success && res.info) {
            this.currentRaidInfo = res.info;
            this.renderRaidInfoModal(res.info);
        } else {
            content.innerHTML = `<div class="alert alert-danger"><?php echo $lang1463; ?> ${res.error || 'Unknown error'}</div>`;
        }
    }
    
    renderRaidInfoModal(info) {
        const content = document.getElementById('raidInfoContent');
        let devicesHtml = '';
        const diskStatuses = info.disk_status || [];
        for (const dev of (info.devices || [])) {
            const diskInfo = diskStatuses.find(d => d.device === '/dev/' + dev.name);
            const stateBadge = diskInfo ? this.getDiskStateBadge(diskInfo.state) : '';
            devicesHtml += `<tr><td><code>${dev.name || 'removed'}</code></td><td>${dev.slot || '-'}</td><td>${stateBadge}</td><td><span class="badge ${dev.spare ? 'bg-warning' : 'bg-success'}">${dev.spare ? 'spare' : 'active'}</span></td></tr>`;
        }
        const hasSync = info.sync_action && info.sync_action !== 'idle';
        const syncStatus = hasSync ? `
            <div class="alert alert-info mt-3">
                <strong>${info.sync_action === 'check' || info.sync_action === 'checking' ? 'Check' : (info.sync_action === 'repair' ? 'Repair' : info.sync_action)} <?php echo $lang1464; ?></strong>
                <div class="progress mt-2" style="height: 20px;">
                    <div class="progress-bar progress-bar-striped progress-bar-animated" style="width: ${info.sync_percent || 0}%">
                        ${info.sync_percent || 0}%
                    </div>
                </div>
                ${info.sync_speed ? `<div class="small mt-1"><?php echo $lang1465; ?> ${info.sync_speed}</div>` : ''}
            </div>
        ` : '';
        const isRaid0 = info.level === 'RAID 0' || info.level === 'raid0' || info.level === '0';
        const healthBadge = info.degraded ? 
            '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <strong><?php echo $lang1466; ?></strong> <?php echo $lang1467; ?></div>' :
            (info.failed_disks > 0 ? `<div class="alert alert-warning"><i class="fas fa-microchip"></i> ${info.failed_disks} <?php echo $lang1468; ?></div>` : '');
        const lvmBlock = info.in_lvm ? `
            <div class="alert alert-warning mt-3">
                <i class="fas fa-cubes"></i> <strong><?php echo $lang1469; ?></strong>
                <p class="mb-0 mt-2"><?php echo $lang1470; ?> <code>${info.lvm_vg || 'unknown'}</code>.</p>
                <p class="mb-0"><?php echo $lang1471; ?> <a href="lvm_manager.php" target="_blank" class="alert-link"><?php echo $lang1472; ?></a>.</p>
                <button class="btn btn-sm btn-outline-warning mt-2" onclick="window.open('lvm_manager.php', '_blank')">
                    <i class="fas fa-external-link-alt"></i> <?php echo $lang1473; ?>
                </button>
            </div>
        ` : '';
        const partitionsBlock = info.has_partitions ? `
            <div class="alert alert-warning mt-3">
                <i class="fas fa-layer-group"></i> <strong><?php echo $lang1474; ?></strong>
                <p class="mb-0 mt-2"><?php echo $lang1475; ?></p>
            </div>
        ` : '';
        const isActive = info.status !== 'inactive';
        content.innerHTML = `
            ${healthBadge}
            ${lvmBlock}
            ${partitionsBlock}
            <div class="row">
                <div class="col-md-6">
                    <div class="card mb-3">
                        <div class="card-header bg-primary text-white"><?php echo $lang1476; ?></div>
                        <div class="card-body">
                            <table class="table table-sm">
                                <tr><td width="40%"><strong><?php echo $lang1477; ?></strong></td><td>${info.name}</td></tr>
                                <tr><td><strong><?php echo $lang1478; ?></strong></td><td><span class="raid-level-badge ${this.getRaidLevelClass(info.level)}">${info.level}</span></td></tr>
                                <tr><td><strong><?php echo $lang1479; ?></strong></td><td>${info.size || 'N/A'}</td></tr>
                                <tr><td><strong><?php echo $lang1480; ?></strong></td><td>${info.health_state || info.status || 'active'} ${isActive ? '<span class="badge bg-success"><?php echo $lang1481; ?></span>' : '<span class="badge bg-secondary"><?php echo $lang1482; ?></span>'}</td></tr>
                                <tr><td><strong><?php echo $lang1483; ?></strong></td><td>${info.degraded ? '<span class="badge bg-danger"><?php echo $lang1484; ?></span>' : '<span class="badge bg-success"><?php echo $lang1485; ?></span>'}</td></tr>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card mb-3">
                        <div class="card-header bg-info text-white"><?php echo $lang1486; ?></div>
                        <div class="card-body">
                            <table class="table table-sm">
                                <tr><td><strong><?php echo $lang1487; ?></strong></td><td>${info.total_devices || info.devices?.length || 0}</td></tr>
                                <tr><td><strong><?php echo $lang1488; ?></strong></td><td>${info.working_disks || 0}</td></tr>
                                <tr><td><strong><?php echo $lang1489; ?></strong></td><td class="text-danger">${info.failed_disks || 0}</td></tr>
                                <tr><td><strong><?php echo $lang1490; ?></strong></td><td>${info.spare_disks || 0}</td></tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            ${syncStatus}
            <div class="card">
                <div class="card-header bg-secondary text-white"><?php echo $lang1491; ?></div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead><tr><th><?php echo $lang1492; ?></th><th><?php echo $lang1493; ?></th><th><?php echo $lang1494; ?></th><th><?php echo $lang1495; ?></th></tr></thead>
                        <tbody>${devicesHtml || '<tr><td colspan="4" class="text-center"><?php echo $lang1496; ?></td></tr>'}</tbody>
                    </table>
                </div>
            </div>
        `;
        const checkBtn = document.getElementById('checkRaidBtn');
        const repairBtn = document.getElementById('repairRaidBtn');
        const stopBtn = document.getElementById('stopRaidBtn');
        const startBtn = document.getElementById('startRaidBtn');
        if (checkBtn) checkBtn.onclick = () => this.checkRaid(info.name);
        if (repairBtn) repairBtn.onclick = () => this.repairRaid(info.name);
        if (stopBtn) stopBtn.onclick = () => this.stopRaid(info.name);
        if (startBtn) startBtn.onclick = () => this.startRaid(info.name);
        if (checkBtn) checkBtn.style.display = isRaid0 ? 'none' : 'inline-block';
        if (repairBtn) repairBtn.style.display = isRaid0 ? 'none' : 'inline-block';
        if (stopBtn) stopBtn.style.display = isActive ? 'inline-block' : 'none';
        if (startBtn) startBtn.style.display = isActive ? 'none' : 'inline-block';
    }
    
    async showDeviceInfo(deviceName) {
        const modal = new bootstrap.Modal(document.getElementById('deviceInfoModal'));
        const content = document.getElementById('deviceInfoContent');
        const title = document.getElementById('deviceInfoTitle');
        title.innerHTML = `<i class="fas fa-hdd"></i> ${deviceName}`;
        content.innerHTML = '<div class="text-center p-4"><div class="apple-spinner mx-auto"></div><p class="mt-2"><?php echo $lang1497; ?></p></div>';
        modal.show();
        const device = this.data.available_devices?.find(d => d.name === deviceName);
        if (device) {
            let statusHtml = '';
            let needsCleaning = false;
            if (device.is_lvm) {
                statusHtml = '<span class="badge bg-info"><?php echo $lang1498; ?></span>';
                needsCleaning = true;
            }
            if (device.is_raid) {
                statusHtml = '<span class="badge bg-primary"><?php echo $lang1499; ?></span>';
                needsCleaning = true;
            }
            if (device.is_raid_array) {
                statusHtml = '<span class="badge bg-primary"><?php echo $lang1500; ?></span>';
            }
            if (device.available) {
                statusHtml = '<span class="badge bg-success"><?php echo $lang1501; ?></span>';
            }
            if (device.is_mounted) {
                statusHtml = '<span class="badge bg-danger"><?php echo $lang1502; ?></span>';
            }
            if (device.has_filesystem && !device.is_mounted) {
                statusHtml = '<span class="badge bg-warning"><?php echo $lang1503; ?></span>';
                needsCleaning = true;
            }
            const showCleanBtn = !device.is_mounted && !device.is_raid_array && (device.is_raid || device.has_filesystem || device.is_lvm);
            content.innerHTML = `
                <div>
                    <div class="mb-2"><strong><?php echo $lang1504; ?></strong> <code>/dev/${device.name}</code></div>
                    <div class="mb-2"><strong><?php echo $lang1505; ?></strong> ${device.size}</div>
                    <div class="mb-2"><strong><?php echo $lang1506; ?></strong> ${device.type === 'disk' ? 'Disk' : (device.type === 'partition' ? 'Partition' : device.type)}</div>
                    <div class="mb-2"><strong><?php echo $lang1507; ?></strong> ${statusHtml}</div>
                    ${device.fstype ? `<div class="mb-2"><strong><?php echo $lang1508; ?></strong> ${device.fstype}</div>` : ''}
                    ${device.parent_disk ? `<div class="mb-2"><strong><?php echo $lang1509; ?></strong> ${device.parent_disk}</div>` : ''}
                    ${device.model ? `<div class="mb-2"><strong><?php echo $lang1510; ?></strong> ${device.model}</div>` : ''}
                    <div class="mb-2"><strong><?php echo $lang1511; ?></strong> ${device.rota ? 'HDD' : 'SSD'}</div>
                    ${showCleanBtn ? `
                    <hr>
                    <div class="alert alert-warning mt-3">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong><?php echo $lang1512; ?></strong> <?php echo $lang1513; ?> 
                        ${device.is_raid ? 'RAID metadata' : ''}
                        ${device.has_filesystem ? ' filesystem' : ''}
                        ${device.is_lvm ? ' LVM labels' : ''}
                    </div>
                    <button class="btn btn-apple-danger w-100" onclick="raidManager.forceCleanDisk('${device.name}')">
                        <i class="fas fa-trash-alt"></i> <?php echo $lang1514; ?>
                    </button>
                    <div class="small text-muted mt-2 text-center"><?php echo $lang1515; ?></div>
                    ` : ''}
                </div>
            `;
        } else {
            content.innerHTML = `<div class="alert alert-danger"><?php echo $lang1516; ?></div>`;
        }
    }
    
    async forceCleanDisk(diskName) {
        if (!confirm(`<i class="fas fa-exclamation-triangle"></i> <?php echo $lang1517; ?> /dev/${diskName}\n\n<?php echo $lang1518; ?>\n\n<?php echo $lang1519; ?>\n1. <?php echo $lang1520; ?>\n2. <?php echo $lang1521; ?>\n3. <?php echo $lang1522; ?>\n\n<?php echo $lang1523; ?>`)) return;
        this.showLoader();
        const res = await this.apiCall('force_clean_disk', { disk: diskName }, 120000);
        if (res.success) {
            this.showToast(`<i class="fas fa-check-circle"></i> <?php echo $lang1524; ?> ${diskName} <?php echo $lang1525; ?>`, 'success');
            bootstrap.Modal.getInstance(document.getElementById('deviceInfoModal'))?.hide();
            setTimeout(() => this.refreshAll(true), 2000);
        } else {
            this.showToast(`<i class="fas fa-times-circle"></i> <?php echo $lang1526; ?> ${res.error}`, 'error');
        }
        this.hideLoader();
    }
    
    async showBlockDiagram() {
        const modal = new bootstrap.Modal(document.getElementById('blockDiagramModal'));
        const container = document.getElementById('blockDiagramContent');
        container.innerHTML = '<div class="text-center p-4 text-white"><div class="apple-spinner mx-auto"></div><p class="mt-2"><?php echo $lang1527; ?></p></div>';
        modal.show();
        await this.renderConnectionDiagram();
    }
    
    async renderConnectionDiagram() {
        const devices = this.data.available_devices || [];
        const raidArrays = this.data.raid || [];
        const deviceToRaid = new Map();
        const raidToDevices = new Map();
        for (const raid of raidArrays) {
            const devList = [];
            for (const dev of raid.devices) {
                deviceToRaid.set(dev.name, raid.name);
                devList.push({ name: dev.name, spare: dev.spare, failed: raid.disk_states?.some(d => d.device === '/dev/' + dev.name && d.state === 'faulty') });
            }
            raidToDevices.set(raid.name, devList);
        }
        let html = `<div class="connection-diagram"><div class="connection-arrow"></div><div class="raid-layer"><div class="level-label" style="position: absolute; top: -10px; left: 10px;"><i class="fas fa-server"></i> <?php echo $lang1528; ?></div>`;
        if (raidArrays && raidArrays.length) {
            for (const raid of raidArrays) {
                const levelClass = this.getRaidLevelClass(raid.level);
                const syncPercent = raid.sync_percent ? parseInt(raid.sync_percent) : 0;
                const hasSync = raid.sync_action && raid.sync_action !== 'idle' && syncPercent > 0;
                const isDegraded = raid.degraded;
                html += `<div class="raid-card-diagram" style="${isDegraded ? 'border-color: #ff3b30;' : ''}" onclick="raidManager.showRaidInfo('${raid.name}')">
                    <div class="raid-header-diagram">
                        <div class="d-flex align-items-center justify-content-between mb-2">
							<strong><i class="fas fa-server"></i> ${raid.name}</strong>
							<span class="raid-level-badge ${levelClass}">
								<i class="fas ${this.getRaidIcon(raid.level)} me-1"></i> ${raid.level}
							</span>
						</div>
                        <span style="font-size: 11px;">(${raid.size || 'N/A'})</span>
                        ${isDegraded ? '<span style="color:#ff3b30; margin-left:8px;"><i class="fas fa-exclamation-triangle"></i></span>' : ''}
                    </div>
                    <div class="device-in-raid">
                        <div style="width:100%; text-align:center; font-size:10px; color:#aaa;"><i class="fas fa-hdd"></i> <?php echo $lang1529; ?></div>`;
                const raidDevices = raidToDevices.get(raid.name) || [];
                for (const dev of raidDevices) {
                    const spareClass = dev.spare ? 'spare' : '';
                    const failedClass = dev.failed ? 'failed' : '';
                    html += `<div class="device-badge-diagram ${spareClass} ${failedClass}" onclick="event.stopPropagation(); raidManager.showDeviceInfo('${dev.name}')">
                        <i class="fas fa-hdd"></i> ${dev.name}
                        ${dev.spare ? '<small><?php echo $lang1530; ?></small>' : ''}
                        ${dev.failed ? '<small><?php echo $lang1531; ?></small>' : ''}
                    </div>`;
                }
                html += `</div>`;
                if (hasSync) {
                    let syncLabel = 'Sync';
                    if (raid.sync_action === 'check' || raid.sync_action === 'checking') syncLabel = 'Check';
                    else if (raid.sync_action === 'repair') syncLabel = 'Repair';
                    else if (raid.sync_action === 'recover' || raid.sync_action === 'recovery') syncLabel = 'Recovery';
                    else if (raid.sync_action === 'reshape') syncLabel = 'Reshape';
                    html += `<div class="mt-2">
                        <div class="progress" style="height: 4px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" style="width: ${syncPercent}%"></div>
                        </div>
                        <div class="small text-center">${syncLabel}: ${syncPercent}%</div>
                    </div>`;
                }
                html += `</div>`;
            }
        } else {
            html += `<div class="text-center text-muted"><?php echo $lang1532; ?></div>`;
        }
        html += `</div><div class="connection-arrow"></div><div class="physical-layer"><div class="level-label" style="position: absolute; top: -10px; left: 10px;"><i class="fas fa-hdd"></i> <?php echo $lang1533; ?></div>`;
        const disksMap = new Map();
        for (const dev of devices) {
            if (dev.type === 'disk') {
                if (!disksMap.has(dev.name)) {
                    disksMap.set(dev.name, { ...dev, partitions: [], usedInRaid: deviceToRaid.has(dev.name) });
                }
            }
        }
        for (const dev of devices) {
            if (dev.type === 'partition' && dev.parent_disk) {
                if (disksMap.has(dev.parent_disk)) {
                    const partInfo = { ...dev, usedInRaid: deviceToRaid.has(dev.name) };
                    disksMap.get(dev.parent_disk).partitions.push(partInfo);
                    if (partInfo.usedInRaid) {
                        disksMap.get(dev.parent_disk).usedInRaid = true;
                    }
                }
            }
        }
        for (const [diskName, disk] of disksMap) {
            const lvmClass = disk.is_lvm ? 'lvm-used' : '';
            const raidClass = disk.usedInRaid ? 'raid-used' : '';
            html += `<div class="physical-device ${lvmClass} ${raidClass}" onclick="raidManager.showDeviceInfo('${diskName}')">
                <div class="device-name"><i class="fas fa-hdd"></i> ${diskName}</div>
                <div class="device-size">${disk.size}</div>
                <div class="partition-list">`;
            if (disk.partitions && disk.partitions.length) {
                for (const part of disk.partitions) {
                    const partLvmClass = part.is_lvm ? 'lvm-used' : '';
                    const partRaidClass = part.usedInRaid ? 'raid-used' : '';
                    html += `<div class="partition-badge ${partLvmClass} ${partRaidClass}" onclick="event.stopPropagation(); raidManager.showDeviceInfo('${part.name}')">
                                ${part.name}<br><small>${part.size}</small>
                            </div>`;
                }
            } else {
                html += `<div class="text-muted small"><?php echo $lang1534; ?></div>`;
            }
            html += `</div></div>`;
        }
        html += `</div></div>`;
        document.getElementById('blockDiagramContent').innerHTML = html;
    }
    
	getRaidIcon(level) {
    level = level.toLowerCase();
    if (level.includes('raid0') || level === '0') return 'fa-grip-lines';
    if (level.includes('raid1') || level === '1') return 'fa-copy';
    if (level.includes('raid5') || level === '5') return 'fa-chart-line';
    if (level.includes('raid6') || level === '6') return 'fa-chart-simple';
    if (level.includes('raid10') || level === '10') return 'fa-layer-group';
    return 'fa-server';
}
	
    async refreshBlockDiagram() {
        await this.refreshAll(false);
        await this.renderConnectionDiagram();
    }
    
    async checkRaidBeforeDelete(raidName) {
    this.showLoader();
    try {
        const res = await this.apiCall('check_raid_before_delete', { name: raidName }, 10000);
        this.hideLoader();
        
        if (res.success && res.check) {
            const check = res.check;
            if (!check.can_delete) {
                let errorMsg = check.errors.join('\n');
                if (check.lvm_vg) {
                    errorMsg += `\n\n<?php echo $lang1535; ?> "${check.lvm_vg}"`;
                    if (confirm(errorMsg + '\n\n<?php echo $lang1536; ?>')) {
                        window.open('lvm_manager.php', '_blank');
                    }
                } else {
                    this.showToast(errorMsg, 'error');
                }
                return false;
            }
            
            let warningMsg = '';
            if (check.warnings.length > 0) warningMsg = check.warnings.join('\n') + '\n\n';
            warningMsg += `<?php echo $lang1537; ?> ${raidName}?\n\n<?php echo $lang1538; ?>`;
            if (check.devices && check.devices.length) warningMsg += `\n\n<?php echo $lang1539; ?> ${check.devices.join(', ')}`;
            return confirm(warningMsg);
        }
        
        this.showToast('<?php echo $lang1540; ?>', 'error');
        return false;
    } catch (e) {
        this.hideLoader();
        this.showToast('<?php echo $lang1541; ?> ' + e.message, 'error');
        return false;
    }
}
    
    async showCreateRaid() {
        this.showLoader();
        const res = await this.apiCall('get_raid_candidates', {}, 10000);
        this.hideLoader();
        if (res.success && res.devices && res.devices.length) {
            const devicesList = document.getElementById('raidDevicesList');
            const spareList = document.getElementById('raidSpareList');
            const allDisks = this.data.available_devices || [];
            const disksWithInfo = res.devices.map(dev => {
                const fullInfo = allDisks.find(d => d.name === dev.name);
                return { ...dev, needsCleaning: fullInfo && (fullInfo.is_raid || fullInfo.has_filesystem || fullInfo.is_lvm), is_raid: fullInfo?.is_raid || false, has_filesystem: fullInfo?.has_filesystem || false, is_lvm: fullInfo?.is_lvm || false };
            });
            disksWithInfo.sort((a, b) => (a.needsCleaning ? 1 : 0) - (b.needsCleaning ? 1 : 0));
            let devicesHtml = '<div class="row">';
            let spareHtml = '<div class="row">';
            for (const dev of disksWithInfo) {
                const warningIcon = dev.needsCleaning ? '<span class="text-warning ms-1" title="<?php echo $lang1542; ?>"><i class="fas fa-exclamation-triangle"></i></span>' : '';
                const cardClass = dev.needsCleaning ? 'border-warning' : 'border-success';
                devicesHtml += `
                    <div class="col-md-6 col-lg-4 mb-2">
                        <div class="card ${cardClass}">
                            <div class="card-body p-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="${dev.name}" id="raid_dev_${dev.name.replace(/[\/-]/g, '_')}">
                                    <label class="form-check-label" for="raid_dev_${dev.name.replace(/[\/-]/g, '_')}">
                                        <strong>${dev.name}</strong> ${warningIcon}
                                        <br><small class="text-muted">${dev.size} | ${dev.type === 'partition' ? 'Partition' : 'Disk'}</small>
                                    </label>
                                    ${dev.needsCleaning ? `
                                    <div class="small text-warning mt-1">
                                        <i class="fas fa-exclamation-circle"></i> 
                                        ${dev.is_raid ? 'RAID metadata' : ''}
                                        ${dev.has_filesystem ? ' filesystem' : ''}
                                        ${dev.is_lvm ? ' LVM' : ''}
                                        <button class="btn btn-sm btn-outline-warning float-end" onclick="event.stopPropagation(); raidManager.forceCleanDiskFromModal('${dev.name}')">
                                            <?php echo $lang1543; ?>
                                        </button>
                                    </div>
                                    ` : ''}
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                spareHtml += `
                    <div class="col-md-6 col-lg-4 mb-2">
                        <div class="card">
                            <div class="card-body p-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="${dev.name}" id="raid_spare_${dev.name.replace(/[\/-]/g, '_')}">
                                    <label class="form-check-label" for="raid_spare_${dev.name.replace(/[\/-]/g, '_')}">
                                        <strong>${dev.name}</strong>
                                        <br><small class="text-muted">${dev.size}</small>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }
            devicesHtml += '</div>';
            spareHtml += '</div>';
            devicesList.innerHTML = devicesHtml || '<div class="alert alert-warning"><?php echo $lang1544; ?></div>';
            spareList.innerHTML = spareHtml || '<div class="text-muted"><?php echo $lang1545; ?></div>';
            let mdNumber = 0;
            const existingNames = (this.data.raid || []).map(r => r.name);
            while (existingNames.includes('md' + mdNumber)) mdNumber++;
            document.getElementById('raidName').value = 'md' + mdNumber;
            document.getElementById('raidLevel').value = '1';
            document.getElementById('raidChunk').value = '';
            this.toggleSpareBlock();
            document.getElementById('raidLevel').onchange = () => this.toggleSpareBlock();
            new bootstrap.Modal(document.getElementById('createRaidModal')).show();
        } else {
            this.showToast('<?php echo $lang1546; ?>', 'warning');
        }
    }
    
    async forceCleanDiskFromModal(diskName) {
        if (!confirm(`<i class="fas fa-exclamation-triangle"></i> <?php echo $lang1547; ?> /dev/${diskName}?\n\n<?php echo $lang1548; ?>`)) return;
        this.showLoader();
        const res = await this.apiCall('force_clean_disk', { disk: diskName }, 120000);
        if (res.success) {
            this.showToast(`<i class="fas fa-check-circle"></i> <?php echo $lang1549; ?> ${diskName} <?php echo $lang1550; ?>`, 'success');
            await this.refreshAll(false);
            this.showCreateRaid();
        } else {
            this.showToast(`<i class="fas fa-times-circle"></i> <?php echo $lang1551; ?> ${res.error}`, 'error');
        }
        this.hideLoader();
    }
    
    toggleSpareBlock() {
        const level = document.getElementById('raidLevel').value;
        const spareBlock = document.getElementById('raidSpareBlock');
        const showSpare = ['1', '5', '6', '10'].includes(level);
        if (spareBlock) spareBlock.style.display = showSpare ? 'block' : 'none';
    }
    
    async createRaid() {
        const name = document.getElementById('raidName').value.trim();
        const level = document.getElementById('raidLevel').value;
        const chunk = document.getElementById('raidChunk').value;
        const devices = Array.from(document.querySelectorAll('#raidDevicesList input:checked')).map(cb => cb.value);
        const spare = Array.from(document.querySelectorAll('#raidSpareList input:checked')).map(cb => cb.value);
        if (!name || !/^md\d+$/.test(name)) {
            this.showToast('<?php echo $lang1552; ?>', 'error');
            return;
        }
        if (devices.length < 2) {
            this.showToast('<?php echo $lang1553; ?>', 'error');
            return;
        }
        let warningMsg = `<?php echo $lang1554; ?> ${level} <?php echo $lang1555; ?> ${devices.length} <?php echo $lang1556; ?>`;
        if (spare.length > 0) warningMsg += `\n<?php echo $lang1557; ?> ${spare.join(', ')}`;
        warningMsg += `\n\n<?php echo $lang1558; ?>`;
        if (!confirm(warningMsg)) return;
        this.showLoader();
        const res = await this.apiCall('raid_create', { name, level, devices, spare, chunk }, 60000);
        this.hideLoader();
        if (res.success) {
            bootstrap.Modal.getInstance(document.getElementById('createRaidModal')).hide();
            this.showToast(`<?php echo $lang1559; ?> ${name} <?php echo $lang1560; ?>`, 'success');
            setTimeout(() => this.refreshAll(true), 3000);
        } else {
            this.showToast(res.error, 'error');
        }
    }
    
    async stopRaid(name) {
        if (!confirm(`<?php echo $lang1561; ?> ${name}?`)) return;
        this.showLoader();
        const res = await this.apiCall('raid_stop', { name }, 15000);
        this.hideLoader();
        if (res.success) {
            await this.refreshAll(true);
            this.showToast(`<?php echo $lang1562; ?> ${name} <?php echo $lang1563; ?>`);
        } else {
            this.showToast(res.error, 'error');
        }
    }
    
    async startRaid(name) {
        if (!confirm(`<?php echo $lang1564; ?> ${name}?`)) return;
        this.showLoader();
        const res = await this.apiCall('raid_start', { name }, 15000);
        this.hideLoader();
        if (res.success) {
            await this.refreshAll(true);
            this.showToast(`<?php echo $lang1565; ?> ${name} <?php echo $lang1566; ?>`);
        } else {
            this.showToast(res.error, 'error');
        }
    }
    
    async deleteRaid(name) {
        this.showLoader();
        const checkRes = await this.apiCall('check_raid_before_delete', { name: name }, 10000);
        this.hideLoader();
        if (checkRes.success && checkRes.check) {
            const check = checkRes.check;
            if (!check.can_delete && check.in_lvm) {
                let lvsHtml = '';
                if (check.lvs && check.lvs.length) {
                    lvsHtml = '<div class="mt-3"><strong><?php echo $lang1567; ?></strong><ul class="mb-0">';
                    for (const lv of check.lvs) lvsHtml += `<li><code>${lv.name}</code> (${lv.size})</li>`;
                    lvsHtml += '</ul></div>';
                }
                const modalHtml = `
                    <div class="alert alert-danger"><h5><i class="fas fa-cubes"></i> <?php echo $lang1568; ?></h5><p><?php echo $lang1569; ?> <strong>${name}</strong> <?php echo $lang1570; ?></p></div>
                    <div class="card mb-3"><div class="card-header bg-info text-white"><?php echo $lang1571; ?></div><div class="card-body"><table class="table table-sm"><tr><td width="40%"><strong><?php echo $lang1572; ?></strong></td><td><code>${check.vg_name || 'unknown'}</code></td></tr><tr><td><strong><?php echo $lang1573; ?></strong></td><td>${check.vg_size || 'N/A'}</td></tr><tr><td><strong><?php echo $lang1574; ?></strong></td><td><code>${check.pv_uuid || 'N/A'}</code></td></tr></table>${lvsHtml}</div></div>
                    <div class="alert alert-warning"><strong><?php echo $lang1575; ?></strong><ol class="mb-0 mt-2"><li><?php echo $lang1576; ?> <code>${check.vg_name}</code></li><li><?php echo $lang1577; ?> <code>${check.vg_name}</code></li><li><?php echo $lang1578; ?></li><li><?php echo $lang1579; ?></li></ol></div>
                    <div class="text-center mt-3"><a href="lvm_manager.php" target="_blank" class="btn btn-apple btn-lg"><i class="fas fa-external-link-alt"></i> <?php echo $lang1580; ?></a></div>
                `;
                this.showLVMWarningModal(modalHtml);
                return;
            }
            if (!check.can_delete && check.errors.length > 0) {
                this.showToast(check.errors[0], 'error');
                return;
            }
            let warningMsg = '';
            if (check.warnings && check.warnings.length > 0) warningMsg = check.warnings.join('\n') + '\n\n';
            warningMsg += `<?php echo $lang1581; ?> ${name}?\n\n<?php echo $lang1582; ?>`;
            if (check.devices && check.devices.length) warningMsg += `\n\n<?php echo $lang1583; ?> ${check.devices.join(', ')}`;
            if (!confirm(warningMsg)) return;
        }
        this.showLoader();
        const res = await this.apiCall('raid_delete', { name: name }, 60000);
        this.hideLoader();
        if (res.success) {
            await this.refreshAll(true);
            this.showToast(`<?php echo $lang1584; ?> ${name} <?php echo $lang1585; ?>`, 'success');
            if (res.cleaned_devices && res.cleaned_devices.length) {
                this.showToast(`<?php echo $lang1586; ?> ${res.cleaned_devices.join(', ')}`, 'info');
            }
        } else {
            if (res.in_lvm) {
                this.showLVMWarningModal(`
                    <div class="alert alert-danger"><h5><i class="fas fa-cubes"></i> <?php echo $lang1587; ?></h5><p>${res.error}</p></div>
                    <div class="text-center"><a href="lvm_manager.php" target="_blank" class="btn btn-apple"><i class="fas fa-external-link-alt"></i> <?php echo $lang1588; ?></a>
                    <button class="btn btn-apple-danger" onclick="raidManager.forceDeleteRaid('${name}')"><i class="fas fa-skull-crosswalk"></i> <?php echo $lang1589; ?></button></div>
                `);
            } else {
                this.showToast(res.error, 'error');
            }
        }
    }
    
    showLVMWarningModal(htmlContent) {
        let modal = document.getElementById('lvmWarningModal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'lvmWarningModal';
            modal.className = 'modal fade';
            modal.setAttribute('tabindex', '-1');
            modal.innerHTML = `
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header bg-danger text-white"><h5 class="modal-title"><i class="fas fa-exclamation-triangle"></i> <?php echo $lang1590; ?></h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
                        <div class="modal-body" id="lvmWarningModalBody"></div>
                        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang1591; ?></button></div>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        }
        document.getElementById('lvmWarningModalBody').innerHTML = htmlContent;
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();
    }
    
    async checkRaidLVMStatus(raidName) {
        const res = await this.apiCall('is_raid_in_lvm', { name: raidName }, 10000);
        if (res.success) return res;
        return null;
    }
    
    async forceDeleteRaid(name) {
        if (!confirm(`<?php echo $lang1592; ?> ${name} <?php echo $lang1593; ?>\n\n<?php echo $lang1594; ?>`)) return;
        this.showLoader();
        const res = await this.apiCall('raid_delete', { name: name, force: true }, 60000);
        this.hideLoader();
        if (res.success) {
            bootstrap.Modal.getInstance(document.getElementById('lvmWarningModal'))?.hide();
            await this.refreshAll(true);
            this.showToast(`<?php echo $lang1595; ?> ${name} <?php echo $lang1596; ?>`, 'warning');
        } else {
            this.showToast(res.error, 'error');
        }
    }
    
    async checkRaid(name) {
        const raid = this.data.raid.find(r => r.name === name);
        if (raid && (raid.level === 'RAID 0' || raid.level === 'raid0' || raid.level === '0')) {
            this.showToast('<?php echo $lang1597; ?>', 'warning');
            return;
        }
        this.showLoader();
        const res = await this.apiCall('raid_check', { name }, 10000);
        this.hideLoader();
        if (res.success) {
            this.showToast(`<?php echo $lang1598; ?> ${name} <?php echo $lang1599; ?>`);
            setTimeout(() => this.refreshAll(true), 2000);
        } else {
            this.showToast(res.error, 'error');
        }
    }
    
    async repairRaid(name) {
        const raid = this.data.raid.find(r => r.name === name);
        if (raid && (raid.level === 'RAID 0' || raid.level === 'raid0' || raid.level === '0')) {
            this.showToast('<?php echo $lang1600; ?>', 'warning');
            return;
        }
        if (!confirm(`<?php echo $lang1601; ?> ${name}?`)) return;
        this.showLoader();
        const res = await this.apiCall('raid_repair', { name }, 10000);
        this.hideLoader();
        if (res.success) {
            this.showToast(`<?php echo $lang1602; ?> ${name} <?php echo $lang1603; ?>`);
            setTimeout(() => this.refreshAll(true), 2000);
        } else {
            this.showToast(res.error, 'error');
        }
    }
    
    async cleanupStaleRaids() {
        if (!confirm('<?php echo $lang1604; ?>\n\n<?php echo $lang1605; ?>')) return;
        this.showLoader();
        const res = await this.apiCall('cleanup_all_stale_raid', {}, 30000);
        this.hideLoader();
        if (res.success) {
            if (res.cleaned && res.cleaned.length > 0) {
                this.showToast(`<?php echo $lang1606; ?> ${res.cleaned.length} <?php echo $lang1607; ?> ${res.cleaned.join(', ')}`, 'success');
            } else {
                this.showToast('<?php echo $lang1608; ?>', 'info');
            }
            await this.refreshAll(true);
        } else {
            this.showToast(res.error || '<?php echo $lang1609; ?>', 'error');
        }
    }
    
    async showAddDevice(raidName) {
        this.showLoader();
        const res = await this.apiCall('get_raid_candidates', {}, 10000);
        this.hideLoader();
        if (res.success && res.devices && res.devices.length) {
            const select = document.getElementById('addDeviceSelect');
            select.innerHTML = res.devices.map(d => `<option value="${d.name}">${d.name} (${d.size})</option>`).join('');
            document.getElementById('addDeviceRaidName').value = raidName;
            new bootstrap.Modal(document.getElementById('addDeviceModal')).show();
        } else {
            this.showToast('<?php echo $lang1610; ?>', 'warning');
        }
    }
    
    async addDeviceToRaid() {
        const raidName = document.getElementById('addDeviceRaidName').value;
        const device = document.getElementById('addDeviceSelect').value;
        if (!device) {
            this.showToast('<?php echo $lang1611; ?>', 'error');
            return;
        }
        if (!confirm(`<?php echo $lang1612; ?> ${device} <?php echo $lang1613; ?> ${raidName}?`)) return;
        this.showLoader();
        const res = await this.apiCall('raid_add_device', { name: raidName, device }, 20000);
        this.hideLoader();
        if (res.success) {
            bootstrap.Modal.getInstance(document.getElementById('addDeviceModal')).hide();
            await this.refreshAll(true);
            this.showToast(`<?php echo $lang1614; ?> ${device} <?php echo $lang1615; ?> ${raidName}`);
        } else {
            this.showToast(res.error, 'error');
        }
    }
    
    closeAllModals() {
        const modals = document.querySelectorAll('.modal.show');
        modals.forEach(modal => {
            const bsModal = bootstrap.Modal.getInstance(modal);
            if (bsModal) bsModal.hide();
        });
    }
    
    startLiveUpdates() {
        if (this.updateInterval) clearInterval(this.updateInterval);
        this.updateInterval = setInterval(() => {
            if (document.visibilityState === 'visible' && !this.isRefreshing) {
                this.refreshAll(false);
            }
        }, 15000);
    }
    
    stopLiveUpdates() {
        if (this.updateInterval) {
            clearInterval(this.updateInterval);
            this.updateInterval = null;
        }
        if (this.healthInterval) {
            clearInterval(this.healthInterval);
            this.healthInterval = null;
        }
    }
}

const raidManager = new RAIDManager();

function showLegendModal() {
    new bootstrap.Modal(document.getElementById('legendModal')).show();
}
</script>
</body>
</html>
