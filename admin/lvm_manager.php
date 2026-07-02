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
//P.S. "Joe" Biden - LOH!

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
    <title>LVM Manager - Mini-B</title>
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
            --apple-cyan: #64d2ff;
            --apple-teal: #5ac8fa;
            --disk-color: #4A90D9;
            --partition-color: #F5A623;
            --pv-color: #9013FE;
            --vg-color: #00BCD4;
            --lv-color: #4CAF50;
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
        
        .lvm-layout { display: flex; gap: 24px; margin-top: 5px; }
        .disks-sidebar { width: 320px; flex-shrink: 0; background: white; border-radius: 20px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); height: fit-content; overflow-y: auto; }
        .lvm-main { flex: 1; }
        
        .disk-item { padding: 12px; border-radius: 12px; margin-bottom: 8px; cursor: pointer; transition: all 0.2s; background: var(--apple-light-gray); border: 1px solid #e5e5ea; }
        .disk-item:hover { background: #e5e5ea; transform: translateX(2px); }
        .disk-item.active { background: var(--apple-blue); color: white; border-color: var(--apple-blue); }
        .disk-item .disk-name { font-weight: 600; font-size: 14px; }
        .disk-item .disk-size { font-size: 12px; opacity: 0.7; }
        
        .partition-item { padding: 8px 12px; margin: 4px 0 4px 20px; border-radius: 8px; background: #f0f0f0; font-size: 13px; cursor: pointer; transition: all 0.2s; }
        .partition-item:hover { background: #e0e0e0; transform: translateX(2px); }
        
        .lvm-card { background: white; border-radius: 20px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); transition: transform 0.2s; }
        .lvm-card:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.1); }
        .lvm-header { border-bottom: 2px solid #f0f0f0; padding-bottom: 10px; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        
        .vg-progress { height: 8px; background: #e5e5ea; border-radius: 4px; overflow: hidden; margin: 10px 0; }
        .vg-progress-fill { background: var(--apple-green); height: 100%; transition: width 0.3s; }
        
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
            gap: 20px;
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
        
        .lvm-layer {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            justify-content: center;
            padding: 20px;
            background: rgba(0,188,212,0.1);
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
        
        .partition-list { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; justify-content: center; }
        
        .partition-badge {
            background: linear-gradient(135deg, var(--partition-color), #8B6914);
            border-radius: 8px;
            padding: 5px 10px;
            font-size: 11px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .partition-badge:hover { transform: scale(1.05); }
        .partition-badge.pv-marked { border: 2px solid var(--pv-color); }
        
        .vg-card {
            background: rgba(0,188,212,0.15);
            border-radius: 16px;
            padding: 15px;
            min-width: 280px;
            border: 1px solid var(--vg-color);
            cursor: pointer;
        }
        
        .vg-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.2); }
        .vg-header {
            text-align: center;
            color: var(--vg-color);
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .pv-in-vg {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: center;
            margin: 10px 0;
            padding: 8px;
            background: rgba(144,19,254,0.1);
            border-radius: 10px;
        }
        
        .pv-badge {
            background: linear-gradient(135deg, var(--pv-color), #6B0F8E);
            border-radius: 8px;
            padding: 5px 10px;
            font-size: 11px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .lv-badge {
            background: linear-gradient(135deg, var(--lv-color), #2E7D32);
            border-radius: 8px;
            padding: 5px 10px;
            font-size: 11px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .lv-badge.mounted { border: 2px solid var(--apple-red); }
        
        .partition-details { font-size: 14px; }
        .partition-details-item { padding: 8px; border-bottom: 1px solid #eee; }
        
        @media (max-width: 768px) {
            .lvm-layout { flex-direction: column; }
            .disks-sidebar { width: 100%; max-height: 300px; }
            .physical-device { min-width: 100px; }
        }
		
		#pvDevicesList .card {
    transition: all 0.2s ease;
    cursor: pointer;
}
#pvDevicesList .card:not(.opacity-50):hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
#pvDevicesList .form-check-input:disabled + label {
    cursor: not-allowed;
}
#pvDevicesList .form-check-input:not(:disabled) {
    cursor: pointer;
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
        <i class="fas fa-layer-group"></i> LVM Manager
		<!--<div class="legend" onclick="showLegendModal()">
		<div class="legend-item"><div class="legend-color" style="background: #4A90D9;"></div><span>Disk</span></div>
            <div class="legend-item"><div class="legend-color" style="background: #F5A623;"></div><span>Partition</span></div>
            <div class="legend-item"><div class="legend-color" style="background: #9013FE;"></div><span>PV (Physical Volume)</span></div>
            <div class="legend-item"><div class="legend-color" style="background: #00BCD4;"></div><span>VG (Volume Group)</span></div>
            <div class="legend-item"><div class="legend-color" style="background: #4CAF50;"></div><span>LV (Logical Volume)</span></div>
            <div class="legend-item"><div class="legend-color" style="background: #ff3b30;"></div><span>Mounted</span></div>
            <div class="legend-item"><i class="fas fa-chevron-right"></i><span>Click for details</span></div>	
    </div>-->
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
                    <li><a class="dropdown-item" href="#" onclick="showBlockDiagram()"><i class="fas fa-project-diagram me-2"></i> <?php echo $lang1700; ?></a></li>
                    <li><a class="dropdown-item" href="#" onclick="showSnapshotsList()"><i class="fas fa-camera-retro me-2"></i> <?php echo $lang1701; ?></a></li>
                    <li><a class="dropdown-item" href="#" onclick="showLegendModal()"><i class="fas fa-info me-2"></i> <?php echo $lang1702; ?></a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="#" onclick="refreshAll(); return false;"><i class="fas fa-sync-alt me-2"></i> <?php echo $lang1703; ?></a></li>
                </ul>
            </div>	
                <!-- <button class="btn btn-warning btn-sm" onclick="refreshAll()"><i class="fas fa-sync-alt"></i> Обновить</button>-->
	</div>
</div>

<div class="app-container">
    <?php echo $menu; ?>
    <main class="main-content">
   
        <div class="lvm-layout">
    <div class="disks-sidebar">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0"><i class="fas fa-hdd"></i> <?php echo $lang1704; ?></h5>
            <div class="btn-group">
                <button class="btn btn-sm btn-outline-apple dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-plus"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="#" onclick="showCreatePv(); return false;"><i class="fas fa-database me-2"></i> <?php echo $lang1705; ?></a></li>
                    <li><a class="dropdown-item" href="#" onclick="showCreateVg(); return false;"><i class="fas fa-layer-group me-2"></i> <?php echo $lang1706; ?></a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="#" onclick="refreshAll(); return false;"><i class="fas fa-sync-alt me-2"></i> <?php echo $lang1707; ?></a></li>
                </ul>
            </div>
        </div>
        <hr class="my-2">
        <div id="disksList"></div>
    </div>
    
    <div class="lvm-main">
        <div id="lvmContainer"></div>
    </div>
</div>
    </main>
</div>

<!-- Модальное окно легенды -->
<div class="modal fade" id="legendModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-info-circle"></i> <?php echo $lang1708; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="legend-item mb-2"><div class="legend-color" style="background: #4A90D9;"></div><span><?php echo $lang1709; ?></span></div>
                <div class="legend-item mb-2"><div class="legend-color" style="background: #F5A623;"></div><span><?php echo $lang1710; ?></span></div>
                <div class="legend-item mb-2"><div class="legend-color" style="background: #9013FE;"></div><span><?php echo $lang1711; ?></span></div>
                <div class="legend-item mb-2"><div class="legend-color" style="background: #00BCD4;"></div><span><?php echo $lang1712; ?></span></div>
                <div class="legend-item mb-2"><div class="legend-color" style="background: #4CAF50;"></div><span><?php echo $lang1713; ?></span></div>
                <div class="legend-item mb-2"><div class="legend-color" style="background: #ff3b30;"></div><span><?php echo $lang1714; ?></span></div>
                <hr>
                <p class="small text-muted"><?php echo $lang1715; ?></p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-apple" data-bs-dismiss="modal"><?php echo $lang1716; ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно блок-схемы -->
<div class="modal fade" id="blockDiagramModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-project-diagram"></i> <?php echo $lang1717; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="max-height: 80vh; overflow-y: auto; background: #0f0f1a;">
                <div id="blockDiagramContent" class="diagram-container">
                    <div class="text-center p-4 text-white"><?php echo $lang1718; ?></div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang1719; ?></button>
                <button class="btn btn-apple" onclick="refreshBlockDiagram()"><i class="fas fa-sync-alt"></i> <?php echo $lang1720; ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно информации о разделе -->
<div class="modal fade" id="partitionInfoModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-microchip"></i> <span id="partitionInfoTitle"><?php echo $lang1721; ?></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="partitionInfoContent">
                <div class="text-center p-4"><?php echo $lang1722; ?></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang1723; ?></button>
                <button class="btn btn-apple-success" id="createPvFromPartitionBtn" style="display: none;" onclick="createPvFromPartition()"><i class="fas fa-database"></i> <?php echo $lang1724; ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно информации о VG -->
<div class="modal fade" id="vgInfoModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-layer-group"></i> <span id="vgInfoTitle"><?php echo $lang1725; ?></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="vgInfoContent"></div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang1726; ?></button>
                <button class="btn btn-apple" onclick="showExtendVgFromModal()"><?php echo $lang1727; ?></button>
                <button class="btn btn-apple-warning" id="reduceVgBtn" onclick="showReduceVgFromModal()" style="display: none;"><?php echo $lang1728; ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно информации о LV -->
<div class="modal fade" id="lvInfoModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-chart-line"></i> <span id="lvInfoTitle"><?php echo $lang1729; ?></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="lvInfoContent"></div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang1730; ?></button>
                <button class="btn btn-apple" id="extendLvFromModalBtn" onclick="extendLvFromModal()"><?php echo $lang1731; ?></button>
                <button class="btn btn-apple-success" id="mountLvFromModalBtn" onclick="mountLvFromModal()"><?php echo $lang1732; ?></button>
                <button class="btn btn-apple-secondary" id="umountLvFromModalBtn" onclick="umountLvFromModal()" style="display: none;"><?php echo $lang1733; ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно информации о диске -->
<div class="modal fade" id="diskInfoModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-hdd"></i> <span id="diskInfoTitle"><?php echo $lang1734; ?></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="diskInfoContent">
                <div class="text-center p-4"><?php echo $lang1735; ?></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang1736; ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Модальные окна LVM -->
<div class="modal fade" id="createPvModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-database"></i> <?php echo $lang1737; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning mb-3">
                    <i class="fas fa-exclamation-triangle"></i> 
                    <strong><?php echo $lang1738; ?></strong> <?php echo $lang1739; ?>
                </div>
                <div id="pvDevicesList" class="row g-2" style="max-height: 500px; overflow-y: auto;">
                    <div class="col-12 text-center p-4"><?php echo $lang1740; ?></div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang1741; ?></button>
                <button class="btn btn-apple-danger" onclick="createPvFromSelected()"><?php echo $lang1742; ?></button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="createVgModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-layer-group"></i> <?php echo $lang1743; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="text" class="form-control mb-2" id="vgName" placeholder="<?php echo $lang1744; ?>">
                <label class="form-label"><?php echo $lang1745; ?></label>
                <div id="pvList" class="border rounded p-2" style="max-height:200px;overflow:auto"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang1746; ?></button>
                <button class="btn btn-apple" onclick="createVg()"><?php echo $lang1747; ?></button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="createLvModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-chart-line"></i> <?php echo $lang1748; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="lvVgName">
                <input type="text" class="form-control mb-2" id="lvName" placeholder="<?php echo $lang1749; ?>">
                <input type="text" class="form-control mb-2" id="lvSize" placeholder="<?php echo $lang1750; ?>">
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="lvFormat">
                    <label class="form-check-label"><?php echo $lang1751; ?></label>
                </div>
                <div class="mb-2" id="lvFsTypeDiv" style="display: none;">
                    <label class="form-label"><?php echo $lang1752; ?></label>
                    <select id="lvFsType" class="form-select">
                        <option value="ext4">ext4</option>
                        <option value="xfs">xfs</option>
                        <option value="ext3">ext3</option>
                        <option value="ntfs">ntfs</option>
                        <option value="vfat">vfat</option>
                    </select>
                </div>
                <div class="text-muted small"><?php echo $lang1753; ?></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang1754; ?></button>
                <button class="btn btn-apple" onclick="createLv()"><?php echo $lang1755; ?></button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="extendVgModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-expand"></i> <?php echo $lang1756; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="extendVgName">
                <label><?php echo $lang1757; ?></label>
                <div id="extendPvList"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang1758; ?></button>
                <button class="btn btn-apple" onclick="extendVg()"><?php echo $lang1759; ?></button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="reduceVgModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-compress"></i> <?php echo $lang1760; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="reduceVgName">
                <div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> <?php echo $lang1761; ?></div>
                <label><?php echo $lang1762; ?></label>
                <div id="reducePvList"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang1763; ?></button>
                <button class="btn btn-apple-warning" onclick="reduceVg()"><?php echo $lang1764; ?></button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="extendLvModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-expand"></i> <?php echo $lang1765; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="extendLvVg">
                <input type="hidden" id="extendLvName">
                <div class="mb-3"><strong><?php echo $lang1766; ?> <span id="extendLvCurrentSize"></span></strong></div>
                <input type="text" class="form-control" id="extendLvSize" placeholder="+10G, 20G, +50%FREE">
                <div class="text-muted small mt-2"><?php echo $lang1767; ?></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang1768; ?></button>
                <button class="btn btn-apple" onclick="extendLv()"><?php echo $lang1769; ?></button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="renameVgModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo $lang1770; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="renameVgOldName">
                <input type="text" class="form-control" id="renameVgNewName" placeholder="<?php echo $lang1772; ?>">
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang1771; ?></button>
                <button class="btn btn-apple" onclick="renameVg()"><?php echo $lang1773; ?></button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="renameLvModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo $lang1774; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="renameLvVg">
                <input type="hidden" id="renameLvOldName">
                <input type="text" class="form-control" id="renameLvNewName" placeholder="<?php echo $lang1775; ?>">
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang1776; ?></button>
                <button class="btn btn-apple" onclick="renameLv()"><?php echo $lang1777; ?></button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="mountModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-eject"></i> <?php echo $lang1778; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="mountDevice">
                <input type="text" class="form-control mb-2" id="mountPoint" placeholder="/mnt/data">
                <select id="mountFs" class="form-select mb-2">
                    <option value="auto"><?php echo $lang1779; ?></option>
                    <option value="ext4">ext4</option>
                    <option value="xfs">xfs</option>
                    <option value="ext3">ext3</option>
                    <option value="ntfs-3g">ntfs-3g</option>
                    <option value="vfat">vfat</option>
                </select>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="mountFstab">
                    <label class="form-check-label"><?php echo $lang1780; ?></label>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang1781; ?></button>
                <button class="btn btn-apple" onclick="mountLv()"><i class="fas fa-eject"></i> <?php echo $lang1782; ?></button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="formatLvModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-eraser"></i> <?php echo $lang1783; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="formatLvPath">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> 
                    <?php echo $lang1784; ?>
                </div>
                <label class="form-label"><?php echo $lang1785; ?></label>
                <select id="formatFsType" class="form-select">
                    <optgroup label="Linux">
                        <option value="ext4">ext4 <?php echo $lang1786; ?></option>
                        <option value="ext3">ext3</option>
                        <option value="ext2">ext2</option>
                        <option value="xfs">XFS</option>
                        <option value="btrfs">Btrfs</option>
                    </optgroup>
                    <optgroup label="Windows">
                        <option value="ntfs">NTFS</option>
                        <option value="vfat">FAT32</option>
                    </optgroup>
                </select>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang1787; ?></button>
                <button class="btn btn-apple-danger" onclick="confirmFormatLv()"><?php echo $lang1788; ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно создания снапшота -->
<div class="modal fade" id="createSnapshotModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-camera"></i> <?php echo $lang1789; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="snapshotVgName">
                <input type="hidden" id="snapshotOriginLv">
                <div class="mb-3">
                    <label class="form-label"><?php echo $lang1790; ?></label>
                    <input type="text" class="form-control" id="snapshotName" placeholder="snap_2024_01_01">
                    <div class="form-text"><?php echo $lang1791; ?></div>
                </div>
                <div class="mb-3">
                    <label class="form-label"><?php echo $lang1792; ?></label>
                    <input type="text" class="form-control" id="snapshotSize" value="10G">
                    <div class="form-text"><?php echo $lang1793; ?></div>
                </div>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> 
                    <?php echo $lang1794; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang1795; ?></button>
                <button class="btn btn-apple" onclick="createSnapshot()"><?php echo $lang1796; ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно информации о снапшоте -->
<div class="modal fade" id="snapshotInfoModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-camera"></i> <span id="snapshotInfoTitle"><?php echo $lang1797; ?></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="snapshotInfoContent">
                <div class="text-center p-4"><?php echo $lang1798; ?></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang1799; ?></button>
                <button class="btn btn-apple-warning" id="restoreSnapshotBtn" onclick="restoreSnapshotFromModal()"><?php echo $lang1800; ?></button>
                <button class="btn btn-apple-danger" id="deleteSnapshotBtn" onclick="deleteSnapshotFromModal()"><?php echo $lang1801; ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно списка снапшотов -->
<div class="modal fade" id="snapshotsListModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-camera-retro"></i> <?php echo $lang1802; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="snapshotsListContent">
                <div class="text-center p-4"><?php echo $lang1803; ?></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang1804; ?></button>
                <button class="btn btn-apple" onclick="refreshSnapshotsList()"><?php echo $lang1805; ?></button>
            </div>
        </div>
    </div>
</div>

<script src="lib/jquery-3.6.0-master/dist/jquery.min.js"></script>
<script>
const url = "<?php echo $current_host_id == 1 ? '/api/' : rtrim($host_url, '/') . '/'; ?>";
let currentData = {};
let currentPartitionInfo = null;
let currentVgInfo = null;
let currentLvInfo = null;
let currentFormatLvPath = null;
let currentFormatVgName = null;
let currentFormatLvName = null;
let currentSnapshotInfo = null;

function showLoader() {
    document.getElementById('globalLoader').style.display = 'flex';
}

function hideLoader() {
    document.getElementById('globalLoader').style.display = 'none';
}

function closeAllModals() {
    const modals = document.querySelectorAll('.modal.show');
    modals.forEach(modal => {
        const bsModal = bootstrap.Modal.getInstance(modal);
        if (bsModal) bsModal.hide();
    });
}

async function apiCall(action, data = {}) {
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
    } catch (e) {
        return { success: false, error: e.message };
    }
}

function showToast(msg, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `alert alert-${type === 'error' ? 'danger' : type === 'warning' ? 'warning' : 'success'} position-fixed bottom-0 end-0 m-3`;
    toast.style.zIndex = 9999;
    toast.style.minWidth = '250px';
    toast.innerHTML = `<i class="fas fa-${type === 'error' ? 'exclamation-circle' : type === 'warning' ? 'exclamation-triangle' : 'check-circle'} me-2"></i> ${msg}`;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 4000);
}

function showLegendModal() {
    new bootstrap.Modal(document.getElementById('legendModal')).show();
}

function showFormatLvModal(vgName, lvName) {
    currentFormatVgName = vgName;
    currentFormatLvName = lvName;
    currentFormatLvPath = `/dev/${vgName}/${lvName}`;
    
    document.getElementById('formatLvPath').value = currentFormatLvPath;
    document.getElementById('formatFsType').value = 'ext4';
    
    new bootstrap.Modal(document.getElementById('formatLvModal')).show();
}

async function confirmFormatLv() {
    const lvPath = document.getElementById('formatLvPath').value;
    const fsType = document.getElementById('formatFsType').value;
    
    if (!lvPath || !fsType) {
        showToast('<?php echo $lang1806; ?>', 'error');
        return;
    }
    
    bootstrap.Modal.getInstance(document.getElementById('formatLvModal')).hide();
    
    showLoader();
    const res = await apiCall('lvm_format_lv', { lv_path: lvPath, fs_type: fsType });
    hideLoader();
    
    if (res.success) {
        await refreshAll();
        showToast(`<?php echo $lang1807; ?> ${fsType}`);
    } else {
        showToast(res.error, 'error');
    }
}

async function refreshAll() {
    const preloader = document.getElementById('applePreloader');
    if (preloader) preloader.style.display = 'flex';
    
    try {
        const cacheBuster = Date.now();
        
        const headers = {};
        if (window.apiConfig && window.apiConfig.apiKey) {
            headers['X-API-Key'] = window.apiConfig.apiKey;
        }
        
        const res = await fetch(`${url}lvm_api.php?action=get_all_lvm&_=${cacheBuster}`, {
            method: 'GET',
            headers: headers
        });
        const data = await res.json();
        
        if (data.success) {
            currentData = data;
            renderDisks(data.disks);
            renderLvm(data);
        } else {
            showToast(data.error || '<?php echo $lang1808; ?>', 'error');
        }
    } catch (e) {
        showToast('<?php echo $lang1809; ?>' + e.message, 'error');
    }
    
    if (preloader) preloader.style.display = 'none';
}

function renderDisks(disks) {
    let html = '';
    if (!disks || disks.length === 0) {
        html = '<div class="alert alert-warning"><?php echo $lang1810; ?></div>';
    } else {
        for (const disk of disks) {
            const isPV = currentData.pvs?.some(pv => pv.name === disk.path);
            html += `<div class="disk-item mb-2" onclick="showDiskInfo('${disk.name}')">
                <div class="disk-name"><i class="fas fa-hdd"></i> ${disk.name}</div>
                <div class="disk-size">${disk.size_formatted}</div>
                <div class="small">${disk.model || 'Unknown'}</div>
                ${disk.is_system ? '<span class="badge bg-secondary mt-1"><?php echo $lang1811; ?></span>' : ''}
                ${disk.partition_table_type ? `<span class="badge bg-info mt-1">${disk.partition_table_type.toUpperCase()}</span>` : ''}
                ${isPV ? '<span class="badge bg-info mt-1"><?php echo $lang1812; ?></span>' : ''}
                <div class="small text-muted mt-1">${disk.partitions?.length || 0} <?php echo $lang1813; ?></div>
            </div>`;
            
            if (disk.partitions && disk.partitions.length) {
                for (const part of disk.partitions) {
                    const isPV = part.is_pv === true;
                    const isLV = part.is_lv === true;
                    
                    // Для PV находим VG
                    let vgName = null;
                    if (isPV) {
                        const pv = currentData.pvs?.find(p => p.name === part.path);
                        if (pv && pv.vg_name) {
                            vgName = pv.vg_name;
                        }
                    }
                    
                    // Для LV находим информацию
                    let lvInfo = null;
                    if (isLV) {
                        lvInfo = currentData.lvs?.find(l => l.path === part.path || l.mapper_path === part.path);
                    }
                    
                    if (isLV && lvInfo) {
                        // LV - показываем как логический том
                        html += `<div class="partition-item" style="border-left: 3px solid #4CAF50;" onclick="event.stopPropagation(); showLVInfoModal('${part.path}', '${lvInfo.vg_name}', '${lvInfo.name}')">
                            <i class="fas fa-chart-line" style="color: #4CAF50;"></i> ${part.name}
                            <span class="float-end">${part.size_formatted}</span>
                            <div class="small"><?php echo $lang1814; ?> | ${lvInfo.filesystem || part.fstype || 'no fs'} | ${lvInfo.mount_point || part.mount_point || 'not mounted'}</div>
                            ${lvInfo.mount_point ? '<span class="badge bg-danger mt-1">mounted</span>' : ''}
                            ${!lvInfo.has_filesystem ? '<span class="badge bg-warning mt-1"><?php echo $lang1816; ?></span>' : ''}
                        </div>`;
                    } else if (isPV) {
                        // PV - показываем как physical volume
                        html += `<div class="partition-item" style="border-left: 3px solid #9013FE;" onclick="event.stopPropagation(); showPartitionInfo('${part.name}')">
                            <i class="fas fa-database" style="color: #9013FE;"></i> ${part.name}
                            <span class="float-end">${part.size_formatted}</span>
                            <div class="small"><?php echo $lang1817; ?> | ${vgName ? 'VG: ' + vgName : 'free'} | ${part.fstype || 'LVM2_member'}</div>
                            <span class="badge bg-info mt-1"><?php echo $lang1817; ?></span>
                        </div>`;
                    } else {
                        // Обычный раздел
                        html += `<div class="partition-item" onclick="event.stopPropagation(); showPartitionInfo('${part.name}')">
                            <i class="fas fa-microchip"></i> ${part.name}
                            <span class="float-end">${part.size_formatted}</span>
                            <div class="small">${part.fstype || 'no fs'} | ${part.mount_point || 'not mounted'}</div>
                        </div>`;
                    }
                }
            }
        }
    }
    document.getElementById('disksList').innerHTML = html;
}


function renderLvm(data) {
    let html = '';
    
    // PV секция
    if (data.pvs && data.pvs.length) {
        html += `<div class="lvm-card mb-4">
            <div class="lvm-header d-flex justify-content-between align-items-center">
                <strong><i class="fas fa-database"></i> <?php echo $lang1818; ?> (${data.pvs.length})</strong>
                <div class="btn-group">
                <button class="btn btn-sm btn-outline-apple dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-plus"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="#" onclick="showCreatePv(); return false;"><i class="fas fa-database me-2"></i><?php echo $lang1819; ?></a></li>
                    <li><a class="dropdown-item" href="#" onclick="showCreateVg(); return false;"><i class="fas fa-layer-group me-2"></i><?php echo $lang1820; ?></a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="#" onclick="refreshAll(); return false;"><i class="fas fa-sync-alt me-2"></i><?php echo $lang1821; ?></a></li>
                </ul>
            </div>
            </div>
            <div class="row g-2">`;
        for (const pv of data.pvs) {
            const usedPercent = pv.size ? (pv.used / pv.size) * 100 : 0;
            html += `<div class="col-md-6 col-lg-4">
                <div class="border rounded p-2 h-100">
                    <div class="d-flex justify-content-between align-items-start">
                        <strong><i class="fas fa-database"></i> ${escapeHtml(pv.name.split('/').pop())}</strong>
                        <button class="btn btn-sm btn-outline-danger" onclick="deletePv('${escapeAttr(pv.name)}')" title="<?php echo $lang1822; ?>"><i class="fas fa-trash"></i></button>
                    </div>
                    <div class="small mt-1"><?php echo $lang1823; ?> ${pv.size_formatted}</div>
                    <div class="small"><?php echo $lang1824; ?> ${pv.used_formatted}</div>
                    <div class="small"><?php echo $lang1825; ?> ${pv.free_formatted}</div>
                    <div class="small"><?php echo $lang1826; ?> ${pv.vg_name ? escapeHtml(pv.vg_name) : '<span class="text-muted"><?php echo $lang1827; ?></span>'}</div>
                    <div class="progress mt-1" style="height: 3px;"><div class="progress-bar bg-info" style="width: ${usedPercent}%"></div></div>
                </div>
            </div>`;
        }
        html += `</div></div>`;
    } else {
        html += `<div class="lvm-card mb-4">
            <div class="lvm-header d-flex justify-content-between align-items-center">
                <strong><i class="fas fa-database"></i> <?php echo $lang1828; ?></strong>
                <button class="btn btn-sm btn-outline-apple" onclick="showCreatePv()"><i class="fas fa-plus"></i></button>
            </div>
            <div class="alert alert-info mb-0 small"><?php echo $lang1829; ?></div>
        </div>`;
    }
    
    // VG секция
    if (data.vgs && data.vgs.length) {
        for (const vg of data.vgs) {
            const vgLvs = data.lvs ? data.lvs.filter(lvItem => lvItem.vg_name === vg.name) : [];
            
            html += `<div class="vg-card mb-4">
                <div class="vg-header p-3" style="background: linear-gradient(135deg, #00BCD4, #0097a7); border-radius: 12px 12px 0 0;">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-1 text-white"><i class="fas fa-layer-group"></i> ${escapeHtml(vg.name)}</h5>
                            <div class="small text-white-50">${vg.size_formatted} | ${vg.pv_count} <?php echo $lang1830; ?> | ${vg.lv_count} <?php echo $lang1831; ?></div>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-success" onclick="showCreateLv('${escapeAttr(vg.name)}')">
                                <i class="fas fa-plus"></i> <?php echo $lang1832; ?>
                            </button>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-cog"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="#" onclick="showExtendVg('${escapeAttr(vg.name)}'); return false;"><i class="fas fa-expand me-2"></i><?php echo $lang1833; ?></a></li>
                                    <li><a class="dropdown-item" href="#" onclick="showReduceVg('${escapeAttr(vg.name)}'); return false;"><i class="fas fa-compress me-2"></i><?php echo $lang1834; ?></a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="#" onclick="showRenameVg('${escapeAttr(vg.name)}'); return false;"><i class="fas fa-pen me-2"></i><?php echo $lang1835; ?></a></li>
                                    <li><a class="dropdown-item text-danger" href="#" onclick="deleteVg('${escapeAttr(vg.name)}'); return false;"><i class="fas fa-trash me-2"></i><?php echo $lang1836; ?></a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="mt-2">
                        <div class="d-flex justify-content-between small text-white-50 mb-1">
                            <span><?php echo $lang1837; ?> ${vg.used_percent}%</span>
                            <span><?php echo $lang1838; ?> ${vg.free_formatted}</span>
                        </div>
                        <div class="progress" style="height: 6px;"><div class="progress-bar bg-success" style="width: ${vg.used_percent}%"></div></div>
                    </div>
                </div>
                <div class="p-3 bg-white" style="border-radius: 0 0 12px 12px;">`;
            
            if (vgLvs.length) {
                html += `<div class="row g-2">
                    <div class="col-12"><strong><i class="fas fa-chart-line"></i> <?php echo $lang1839; ?></strong><hr class="my-2"></div>`;
                
                for (const lv of vgLvs) {
                    const isMounted = lv.mount_point !== null;
                    const hasFs = lv.has_filesystem;
                    const mountStatus = isMounted ? 'mount in ' + lv.mount_point : (hasFs ? 'formated' : 'no formated');
                    const uniqueId = 'lv_' + Date.now() + '_' + Math.random().toString(36).substr(2, 8);
                    
                    html += `<div class="col-md-6 col-lg-4">
                        <div class="border rounded p-2 h-100" style="border-left: 3px solid ${isMounted ? '#ff3b30' : (hasFs ? '#34c759' : '#ff9f0a')}">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <strong><i class="fas fa-chart-line"></i> ${escapeHtml(lv.name)}</strong>
                                    <span class="badge ${isMounted ? 'bg-danger' : (hasFs ? 'bg-success' : 'bg-warning')} ms-1" style="font-size: 9px;">${isMounted ? 'Mounted' : (hasFs ? 'Formatted' : 'No FS')}</span>
                                </div>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li><a class="dropdown-item" href="#" onclick="showCreateSnapshot('${escapeAttr(lv.vg_name)}', '${escapeAttr(lv.name)}'); return false;"><i class="fas fa-camera me-2"></i><?php echo $lang1840; ?></a></li>
                                        ${!isMounted ? `<li><a class="dropdown-item" href="#" onclick="formatLv('${escapeAttr(lv.vg_name)}', '${escapeAttr(lv.name)}', false); return false;"><i class="fas fa-eraser me-2"></i><?php echo $lang1841; ?></a></li>` : ''}
                                        <li><a class="dropdown-item" href="#" onclick="showExtendLv('${escapeAttr(lv.vg_name)}', '${escapeAttr(lv.name)}', '${lv.size_formatted}'); return false;"><i class="fas fa-expand me-2"></i><?php echo $lang1842; ?></a></li>
                                        <li><a class="dropdown-item" href="#" onclick="showRenameLv('${escapeAttr(lv.vg_name)}', '${escapeAttr(lv.name)}'); return false;"><i class="fas fa-pen me-2"></i><?php echo $lang1843; ?></a></li>
                                        ${!isMounted && hasFs ? `<li><a class="dropdown-item" href="#" onclick="showMountModal('${escapeAttr(lv.path)}'); return false;"><i class="fas fa-eject me-2"></i><?php echo $lang1844; ?></a></li>` : ''}
                                        ${isMounted ? `<li><a class="dropdown-item" href="#" onclick="umountLv('${escapeAttr(lv.mount_point)}'); return false;"><i class="fas fa-eject me-2"></i><?php echo $lang1845; ?></a></li>` : ''}
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item text-danger" href="#" onclick="deleteLv('${escapeAttr(lv.vg_name)}', '${escapeAttr(lv.name)}'); return false;"><i class="fas fa-trash me-2"></i><?php echo $lang1846; ?></a></li>
                                    </ul>
                                </div>
                            </div>
                            <div class="small mt-2">
                                <div><?php echo $lang1847; ?> <strong>${lv.size_formatted}</strong></div>
                                <div class="text-truncate" title="${escapeAttr(lv.path)}"><?php echo $lang1848; ?> <code class="small">${escapeHtml(lv.path)}</code></div>
                                <div><?php echo $lang1849; ?> ${escapeHtml(mountStatus)}</div>
                                ${lv.filesystem ? `<div><?php echo $lang1850; ?> ${escapeHtml(lv.filesystem)}</div>` : ''}`;
                    
                    // Прогресс-бар для смонтированных LV
                    if (isMounted) {
						html += `<div class="mt-2" data-lv-mount="${escapeAttr(lv.mount_point)}">
									<div class="d-flex justify-content-between small mb-1">
										<span><?php echo $lang1851; ?></span>
										<span class="usage-text"><?php echo $lang1852; ?></span>
									</div>
									<div class="progress" style="height: 4px;">
										<div class="progress-bar bg-primary" style="width: 0%"></div>
									</div>
								</div>`;
					} else if (hasFs) {
                        html += `<div class="alert alert-info mt-2 mb-0 py-1 px-2 small">
                                    <i class="fas fa-info-circle"></i> <?php echo $lang1853; ?>
                                </div>`;
                    } else {
                        html += `<div class="alert alert-warning mt-2 mb-0 py-1 px-2 small">
                                    <i class="fas fa-exclamation-triangle"></i> <?php echo $lang1854; ?>
                                </div>`;
                    }
                    
                    html += `</div>
                        </div>
                    </div>`;
                }
                html += `</div>`;
            } else {
                html += `<div class="text-center text-muted py-3 small"><?php echo $lang1855; ?> 
					<br><button class="btn btn-sm btn-success" onclick="showCreateLv('${escapeAttr(vg.name)}')">
                                <i class="fas fa-plus"></i> <?php echo $lang1856; ?>
                            </button>
				</div>`;
            }
            
            html += `</div></div>`;
        }
    } else {
        html += `<div class="lvm-card">
            <div class="lvm-header d-flex justify-content-between align-items-center">
                <strong><i class="fas fa-layer-group"></i> <?php echo $lang1857; ?></strong>
                <button class="btn btn-sm btn-outline-apple" onclick="showCreateVg()"><i class="fas fa-plus"></i></button>
            </div>
            <div class="alert alert-info mb-0 small"><?php echo $lang1858; ?></div>
        </div>`;
    }
    
    document.getElementById('lvmContainer').innerHTML = html;
    
    // Загружаем данные использования для смонтированных LV
    setTimeout(() => {
    document.querySelectorAll('[data-lv-mount]').forEach(container => {
    const mountPoint = container.getAttribute('data-lv-mount');
    const progressBar = container.querySelector('.progress-bar');
    const usageText = container.querySelector('.usage-text');
    
    const headers = { 'Content-Type': 'application/json' };
    if (window.apiConfig && window.apiConfig.apiKey) {
        headers['X-API-Key'] = window.apiConfig.apiKey;
    }
    
    fetch(url + 'lvm_api.php', {
        method: 'POST',
        headers: headers,
        body: JSON.stringify({ action: 'get_disk_usage', path: mountPoint })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.usage) {
            const percent = data.usage.percent;
            const used = data.usage.used_formatted;
            const total = data.usage.total_formatted;
            if (progressBar) {
                progressBar.style.width = percent + '%';
                if (percent > 85) progressBar.classList.add('bg-danger');
                else if (percent > 70) progressBar.classList.add('bg-warning');
            }
            if (usageText) usageText.innerText = used + ' / ' + total + ' (' + percent + '%)';
        } else {
            if (usageText) usageText.innerText = 'error';
        }
    })
    .catch(err => {
        if (usageText) usageText.innerText = 'error';
    });
});
}, 500);
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

function escapeAttr(str) {
    if (!str) return '';
    return str.replace(/'/g, "\\'").replace(/"/g, '&quot;');
}

// Показывать/скрывать выбор ФС при форматировании
document.addEventListener('DOMContentLoaded', function() {
    const formatCheckbox = document.getElementById('lvFormat');
    const fsDiv = document.getElementById('lvFsTypeDiv');
    if (formatCheckbox && fsDiv) {
        formatCheckbox.addEventListener('change', function() {
            fsDiv.style.display = this.checked ? 'block' : 'none';
        });
    }
});

async function showLVInfoModal(lvPath, vgName, lvName) {
    const modal = new bootstrap.Modal(document.getElementById('lvInfoModal'));
    const content = document.getElementById('lvInfoContent');
    const title = document.getElementById('lvInfoTitle');
    
    title.innerHTML = `<i class="fas fa-chart-line"></i> ${vgName}/${lvName}`;
    content.innerHTML = '<div class="text-center p-4"><div class="apple-spinner mx-auto"></div><p class="mt-2"><?php echo $lang1859; ?></p></div>';
    modal.show();
    
    try {
        const res = await apiCall('get_lv_info', { lv_path: lvPath });
        if (res.success && res.info) {
            const lv = res.info;
            const mountStatus = lv.mount_point ? 
                `<span class="badge bg-danger"><?php echo $lang1860; ?> ${lv.mount_point}</span>` : 
                '<span class="badge bg-secondary"><?php echo $lang1861; ?></span>';
            const fsStatus = lv.has_filesystem ? 
                `<span class="badge bg-success">${lv.filesystem}</span>` : 
                '<span class="badge bg-warning"><?php echo $lang1862; ?></span>';
            
            content.innerHTML = `
                <div class="partition-details">
                    <div class="partition-details-item"><strong><?php echo $lang1863; ?></strong> ${lv.name}</div>
                    <div class="partition-details-item"><strong><?php echo $lang1864; ?></strong> ${lv.vg_name}</div>
                    <div class="partition-details-item"><strong><?php echo $lang1865; ?></strong> ${lv.size_formatted}</div>
                    <div class="partition-details-item"><strong><?php echo $lang1866; ?></strong> <code>${lv.path}</code></div>
                    <div class="partition-details-item"><strong><?php echo $lang1867; ?></strong> ${fsStatus}</div>
                    <div class="partition-details-item"><strong><?php echo $lang1868; ?></strong> ${mountStatus}</div>
                    <div class="partition-details-item"><strong><?php echo $lang1869; ?></strong> ${lv.is_active ? 'Yes' : 'No'}</div>
                </div>
                ${!lv.has_filesystem ? `
                <div class="alert alert-warning mt-3">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo $lang1871; ?> 
                    <?php echo $lang1872; ?>
                </div>
                ` : ''}
            `;
            
            const mountBtn = document.getElementById('mountLvFromModalBtn');
            const umountBtn = document.getElementById('umountLvFromModalBtn');
            const formatBtn = document.getElementById('formatLvFromModalBtn');
            
            if (mountBtn && umountBtn) {
                if (lv.mount_point) {
                    mountBtn.style.display = 'none';
                    umountBtn.style.display = 'inline-block';
                    if (formatBtn) formatBtn.style.display = 'none';
                } else {
                    mountBtn.style.display = lv.has_filesystem ? 'inline-block' : 'none';
                    umountBtn.style.display = 'none';
                    if (formatBtn) formatBtn.style.display = 'inline-block';
                }
            }
            
            currentLvInfo = lv;
            currentLvInfo.vg_name = lv.vg_name;
            currentLvInfo.name = lv.name;
        } else {
            content.innerHTML = `<div class="alert alert-danger"><?php echo $lang1873; ?> ${res.error || 'Unknown error'}</div>`;
        }
    } catch (e) {
        content.innerHTML = `<div class="alert alert-danger"><?php echo $lang1874; ?> ${e.message}</div>`;
    }
}

// ==================== БЛОК-СХЕМА ====================

async function showBlockDiagram() {
    const modal = new bootstrap.Modal(document.getElementById('blockDiagramModal'));
    const container = document.getElementById('blockDiagramContent');
    container.innerHTML = '<div class="text-center p-4 text-white"><div class="apple-spinner mx-auto"></div><p class="mt-2"><?php echo $lang1875; ?></p></div>';
    modal.show();
    
    try {
        const headers = {};
        if (window.apiConfig && window.apiConfig.apiKey) {
            headers['X-API-Key'] = window.apiConfig.apiKey;
        }
        
        const res = await fetch(url + 'lvm_api.php?action=get_all_lvm', {
            method: 'GET',
            headers: headers
        });
        const data = await res.json();
        if (data.success) {
            renderConnectionDiagram(data);
        } else {
            container.innerHTML = '<div class="alert alert-danger"><?php echo $lang1876; ?></div>';
        }
    } catch (e) {
        container.innerHTML = `<div class="alert alert-danger"><?php echo $lang1877; ?> ${e.message}</div>`;
    }
}

function renderConnectionDiagram(data) {
    const deviceToVg = {};
    const deviceToPvData = {};
    
    // Собираем информацию о PV и их VG
    if (data.pvs) {
        for (const pv of data.pvs) {
            deviceToPvData[pv.name] = pv;
            if (pv.vg_name) {
                deviceToVg[pv.name] = pv.vg_name;
            }
        }
    }
    
    const vgToPvs = {};
    const vgToLvs = {};
    
    if (data.vgs) {
        for (const vg of data.vgs) {
            vgToPvs[vg.name] = []; 
			vgToLvs[vg.name] = [];
        }
    }
    
    if (data.pvs) {
        for (const pv of data.pvs) {
            if (pv.vg_name && vgToPvs[pv.vg_name]) {
                vgToPvs[pv.vg_name].push(pv);
            }
        }
    }
    
    if (data.lvs) {
        for (const lv of data.lvs) {
            if (vgToLvs[lv.vg_name]) {
                vgToLvs[lv.vg_name].push(lv);
            }
        }
    }
    
    const elementIds = {};
    let idCounter = 0;
    
    function getElementId(prefix, name) {
        const key = `${prefix}_${name}`;
        if (!elementIds[key]) {
            elementIds[key] = `${prefix}_${idCounter++}`;
        }
        return elementIds[key];
    }
    
    let html = `
        <div class="connection-diagram" id="connectionDiagram" style="position: relative;">
            <svg id="connectionLines" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: 10;"></svg>
            <div class="physical-layer" id="physicalLayer">
                <div class="level-label" style="position: absolute; top: -10px; left: 10px;"><?php echo $lang1878; ?></div>
    `;
    
    // Рендер диски и разделы
    for (const disk of data.disks) {
        const diskId = getElementId('disk', disk.name);
        html += `<div class="physical-device" id="${diskId}" data-device="${disk.name}" data-type="disk" onclick="showDiskInfo('${disk.name}')">
            <div class="device-name"><i class="fas fa-hdd"></i> ${disk.name}</div>
            <div class="device-size">${disk.size_formatted}</div>
            <div class="partition-list">`;
        
        if (disk.partitions && disk.partitions.length) {
            for (const part of disk.partitions) {
                const isPv = deviceToVg[part.path] !== undefined;
                const vgName = deviceToVg[part.path];
                const partId = getElementId('part', part.path);
                html += `<div class="partition-badge ${isPv ? 'pv-marked' : ''}" 
                            id="${partId}"
                            data-device="${part.path}" 
                            data-type="partition"
                            data-vg="${vgName || ''}"
                            onclick="event.stopPropagation(); showPartitionInfo('${part.name}')" 
                            title="${isPv ? 'PV in ' + (vgName || '?') : 'Not a PV'}">
                    <i class="fas fa-microchip"></i> ${part.name}<br>
                    <small>${part.size_formatted}</small>
                    ${isPv ? `<span class="badge bg-info" style="font-size: 8px;"><?php echo $lang1879; ?></span>` : ''}
                </div>`;
            }
        } else {
            html += `<div class="text-muted small"><?php echo $lang1880; ?></div>`;
        }
        
        html += `</div></div>`;
    }
    
    html += `</div><div class="lvm-layer" id="lvmLayer"><div class="level-label" style="position: absolute; top: -10px; left: 10px;"><?php echo $lang1881; ?></div>`;
    
    // Рендер VG и их содержимое
    if (data.vgs && data.vgs.length) {
        for (const vg of data.vgs) {
            const pvsInVg = vgToPvs[vg.name] || [];
            const lvsInVg = vgToLvs[vg.name] || [];
            const vgId = getElementId('vg', vg.name);
            
            html += `<div class="vg-card" id="${vgId}" data-vg="${vg.name}" onclick="showVgInfo('${vg.name}')">
                <div class="vg-header">
                    <i class="fas fa-layer-group"></i> ${vg.name}
                    <span style="font-size: 11px;">(${vg.size_formatted})</span>
                </div>`;
            
            if (pvsInVg.length) {
                html += `<div class="pv-in-vg" id="pvInVg_${vg.name}">
                    <div style="width:100%; text-align:center; font-size:10px; color:#aaa;"><?php echo $lang1882; ?></div>`;
                for (const pv of pvsInVg) {
                    const pvId = getElementId('pv', pv.name);
                    html += `<div class="pv-badge" id="${pvId}" data-pv="${pv.name}" data-vg="${vg.name}" onclick="event.stopPropagation(); showPvInfo('${pv.name}')">
                        <i class="fas fa-database"></i> ${pv.name.split('/').pop()}
                        <small>${pv.size_formatted}</small>
                    </div>`;
                }
                html += `</div>`;
            }
            
            if (lvsInVg.length) {
                html += `<div class="pv-in-vg" style="background: rgba(76,175,80,0.1);">
                    <div style="width:100%; text-align:center; font-size:10px; color:#aaa;"><?php echo $lang1883; ?></div>`;
                for (const lv of lvsInVg) {
                    const isMounted = lv.mount_point !== null;
                    const lvId = getElementId('lv', `${lv.vg_name}_${lv.name}`);
                    html += `<div class="lv-badge ${isMounted ? 'mounted' : ''}" id="${lvId}" data-lv="${lv.name}" data-vg="${lv.vg_name}" onclick="event.stopPropagation(); showLvInfo('${vg.name}', '${lv.name}')">
                        <i class="fas fa-chart-line"></i> ${lv.name}
                        <small>${lv.size_formatted}</small>
                        ${isMounted ? '<i class="fas fa-mount"></i>' : ''}
                    </div>`;
                }
                html += `</div>`;
            }
            
            if (pvsInVg.length === 0 && lvsInVg.length === 0) {
                html += `<div class="text-center text-muted small"><?php echo $lang1884; ?></div>`;
            }
            
            html += `</div>`;
        }
    } else {
        html += `<div class="text-center text-muted"><?php echo $lang1885; ?></div>`;
    }
    
    html += `</div></div>`;
    
    document.getElementById('blockDiagramContent').innerHTML = html;
    
    setTimeout(() => drawConnectionLines(), 100);
}

function drawConnectionLines() {
    const svg = document.getElementById('connectionLines');
    if (!svg) return;
    
    svg.innerHTML = '';
    
    const container = document.getElementById('connectionDiagram');
    if (!container) return;
    
    const containerRect = container.getBoundingClientRect();
    
    const pvBadges = document.querySelectorAll('.pv-badge');
    
    for (const pvBadge of pvBadges) {
        const pvName = pvBadge.getAttribute('data-pv');
        const vgName = pvBadge.getAttribute('data-vg');
        
        if (!pvName || !vgName) continue;
        
        let sourceElement = null;
        
        sourceElement = document.querySelector(`.partition-badge[data-device="${pvName}"]`);
        
        if (!sourceElement) {
            sourceElement = document.querySelector(`.physical-device[data-device="${pvName}"]`);
        }
        
        if (!sourceElement) {
            const diskName = pvName.split('/').pop();
            sourceElement = document.querySelector(`.physical-device[data-device="${diskName}"]`);
        }
        
        if (!sourceElement) {
            const allDisks = document.querySelectorAll('.physical-device');
            for (const disk of allDisks) {
                const diskName = disk.getAttribute('data-device');
                if (diskName && pvName.includes(diskName)) {
                    sourceElement = disk;
                    break;
                }
            }
        }
        
        if (!sourceElement) {
            console.warn(`<?php echo $lang1886; ?> ${pvName}`);
            continue;
        }
        
        const targetElement = document.querySelector(`.vg-card[data-vg="${vgName}"]`);
        if (!targetElement) {
            console.warn(`<?php echo $lang1887; ?> ${vgName}`);
            continue;
        }
        
        const sourceRect = sourceElement.getBoundingClientRect();
        const targetRect = targetElement.getBoundingClientRect();
        
        if (sourceRect.width === 0 || targetRect.width === 0) continue;
        
        const startX = sourceRect.left + sourceRect.width / 2 - containerRect.left;
        const startY = sourceRect.bottom - containerRect.top;
        const endX = targetRect.left + targetRect.width / 2 - containerRect.left;
        const endY = targetRect.top - containerRect.top;
        
        const midY = (startY + endY) / 2;
        const path = `M ${startX} ${startY} C ${startX} ${midY}, ${endX} ${midY}, ${endX} ${endY}`;
        
        const line = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        line.setAttribute('d', path);
        line.setAttribute('stroke', '#9013FE');
        line.setAttribute('stroke-width', '2');
        line.setAttribute('fill', 'none');
        line.setAttribute('stroke-dasharray', '5,3');
        line.setAttribute('opacity', '0.7');
        
        svg.appendChild(line);
        
        const circle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
        circle.setAttribute('cx', startX);
        circle.setAttribute('cy', startY);
        circle.setAttribute('r', '4');
        circle.setAttribute('fill', '#9013FE');
        circle.setAttribute('opacity', '0.8');
        svg.appendChild(circle);
        
        const arrowSize = 6;
        const angle = Math.atan2(endY - startY, endX - startX);
        const arrowX = endX - arrowSize * Math.cos(angle);
        const arrowY = endY - arrowSize * Math.sin(angle);
        
        const arrowLine1 = document.createElementNS('http://www.w3.org/2000/svg', 'line');
        arrowLine1.setAttribute('x1', endX);
        arrowLine1.setAttribute('y1', endY);
        arrowLine1.setAttribute('x2', arrowX - arrowSize * Math.sin(angle));
        arrowLine1.setAttribute('y2', arrowY + arrowSize * Math.cos(angle));
        arrowLine1.setAttribute('stroke', '#9013FE');
        arrowLine1.setAttribute('stroke-width', '1.5');
        
        const arrowLine2 = document.createElementNS('http://www.w3.org/2000/svg', 'line');
        arrowLine2.setAttribute('x1', endX);
        arrowLine2.setAttribute('y1', endY);
        arrowLine2.setAttribute('x2', arrowX + arrowSize * Math.sin(angle));
        arrowLine2.setAttribute('y2', arrowY - arrowSize * Math.cos(angle));
        arrowLine2.setAttribute('stroke', '#9013FE');
        arrowLine2.setAttribute('stroke-width', '1.5');
        
        svg.appendChild(arrowLine1);
        svg.appendChild(arrowLine2);
    }
    
    const lvBadges = document.querySelectorAll('.lv-badge');
    
    for (const lvBadge of lvBadges) {
        const vgName = lvBadge.getAttribute('data-vg');
        
        if (!vgName) continue;
        
        const targetElement = document.querySelector(`.vg-card[data-vg="${vgName}"]`);
        if (!targetElement) continue;
        
        const sourceRect = lvBadge.getBoundingClientRect();
        const targetRect = targetElement.getBoundingClientRect();
        
        if (sourceRect.width === 0 || targetRect.width === 0) continue;
        
        const startX = sourceRect.left + sourceRect.width / 2 - containerRect.left;
        const startY = sourceRect.bottom - containerRect.top;
        const endX = targetRect.left + targetRect.width / 2 - containerRect.left;
        const endY = targetRect.top - containerRect.top;
        
        const midY = (startY + endY) / 2;
        const path = `M ${startX} ${startY} C ${startX} ${midY}, ${endX} ${midY}, ${endX} ${endY}`;
        
        const line = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        line.setAttribute('d', path);
        line.setAttribute('stroke', '#4CAF50');
        line.setAttribute('stroke-width', '1.5');
        line.setAttribute('fill', 'none');
        line.setAttribute('stroke-dasharray', '4,4');
        line.setAttribute('opacity', '0.5');
        
        svg.appendChild(line);
    }
}

async function refreshBlockDiagram() {
    const container = document.getElementById('blockDiagramContent');
    container.innerHTML = '<div class="text-center p-4 text-white"><div class="apple-spinner mx-auto"></div><p class="mt-2"><?php echo $lang1888; ?></p></div>';
    
    try {
        const headers = {};
        if (window.apiConfig && window.apiConfig.apiKey) {
            headers['X-API-Key'] = window.apiConfig.apiKey;
        }
        
        const res = await fetch(url + 'lvm_api.php?action=get_all_lvm', {
            method: 'GET',
            headers: headers
        });
        const data = await res.json();
        if (data.success) {
            renderConnectionDiagram(data);
        } else {
            container.innerHTML = '<div class="alert alert-danger"><?php echo $lang1889; ?></div>';
        }
    } catch (e) {
        container.innerHTML = `<div class="alert alert-danger"><?php echo $lang1890; ?> ${e.message}</div>`;
    }
}

window.addEventListener('resize', () => {
    const modal = document.getElementById('blockDiagramModal');
    if (modal && modal.classList.contains('show')) {
        setTimeout(() => drawConnectionLines(), 50);
    }
});

// ==================== ИНФОРМАЦИОННЫЕ МОДАЛЬНЫЕ ОКНА ====================

function showVgInfo(vgName) {
    const vg = currentData.vgs?.find(v => v.name === vgName);
    if (!vg) return;
    
    currentVgInfo = vg;
    const modal = new bootstrap.Modal(document.getElementById('vgInfoModal'));
    document.getElementById('vgInfoTitle').innerHTML = `<i class="fas fa-layer-group"></i> ${vg.name}`;
    
    const pvsInVg = currentData.pvs?.filter(p => p.vg_name === vgName) || [];
    const reduceBtn = document.getElementById('reduceVgBtn');
    if (reduceBtn) {
        reduceBtn.style.display = pvsInVg.length > 0 ? 'inline-block' : 'none';
    }
    
    document.getElementById('vgInfoContent').innerHTML = `
        <div class="partition-details">
            <div class="partition-details-item"><strong><?php echo $lang1891; ?></strong> ${vg.name}</div>
            <div class="partition-details-item"><strong><?php echo $lang1892; ?></strong> ${vg.size_formatted}</div>
            <div class="partition-details-item"><strong><?php echo $lang1893; ?></strong> ${vg.used_formatted} (${vg.used_percent}%)</div>
            <div class="partition-details-item"><strong><?php echo $lang1894; ?></strong> ${vg.free_formatted}</div>
            <div class="partition-details-item"><strong><?php echo $lang1895; ?></strong> ${vg.pv_count}</div>
            <div class="partition-details-item"><strong><?php echo $lang1896; ?></strong> ${vg.lv_count}</div>
            <div class="partition-details-item"><strong><?php echo $lang1897; ?></strong> ${vg.pe_size}</div>
        </div>
        <hr>
        <strong><?php echo $lang1898; ?></strong>
        <ul class="list-group mt-2">
            ${pvsInVg.map(pv => `<li class="list-group-item d-flex justify-content-between align-items-center">
                ${pv.name}
                <span class="badge bg-primary rounded-pill">${pv.size_formatted}</span>
            </li>`).join('')}
            ${pvsInVg.length === 0 ? '<li class="list-group-item text-muted"><?php echo $lang1899; ?></li>' : ''}
        </ul>
    `;
    modal.show();
}

function showReduceVgFromModal() {
    if (currentVgInfo) {
        bootstrap.Modal.getInstance(document.getElementById('vgInfoModal')).hide();
        showReduceVg(currentVgInfo.name);
    }
}

function showExtendVgFromModal() {
    if (currentVgInfo) {
        bootstrap.Modal.getInstance(document.getElementById('vgInfoModal')).hide();
        showExtendVg(currentVgInfo.name);
    }
}

function showPvInfo(pvName) {
    const pv = currentData.pvs?.find(p => p.name === pvName);
    if (!pv) return;
    showToast(`<?php echo $lang1900; ?> ${pv.name}\n<?php echo $lang1901; ?> ${pv.size_formatted}\n<?php echo $lang1902; ?> ${pv.used_formatted}\n<?php echo $lang1903; ?> ${pv.vg_name || 'none'}`, 'info');
}

function showLvInfo(vgName, lvName) {
    const lv = currentData.lvs?.find(l => l.vg_name === vgName && l.name === lvName);
    if (!lv) return;
    
    currentLvInfo = lv;
    const modal = new bootstrap.Modal(document.getElementById('lvInfoModal'));
    document.getElementById('lvInfoTitle').innerHTML = `<i class="fas fa-chart-line"></i> ${vgName}/${lvName}`;
    
    const mountBtn = document.getElementById('mountLvFromModalBtn');
    const umountBtn = document.getElementById('umountLvFromModalBtn');
    const formatBtn = document.getElementById('formatLvFromModalBtn');
    
    if (mountBtn && umountBtn && formatBtn) {
        if (lv.mount_point) {
            mountBtn.style.display = 'none';
            umountBtn.style.display = 'inline-block';
            formatBtn.style.display = 'none';
        } else {
            mountBtn.style.display = lv.has_filesystem ? 'inline-block' : 'none';
            umountBtn.style.display = 'none';
            formatBtn.style.display = 'inline-block';
        }
    }
    
    document.getElementById('lvInfoContent').innerHTML = `
        <div class="partition-details">
            <div class="partition-details-item"><strong><?php echo $lang1904; ?></strong> ${lv.name}</div>
            <div class="partition-details-item"><strong><?php echo $lang1905; ?></strong> ${lv.vg_name}</div>
            <div class="partition-details-item"><strong><?php echo $lang1906; ?></strong> ${lv.size_formatted}</div>
            <div class="partition-details-item"><strong><?php echo $lang1907; ?></strong> <code>${lv.path}</code></div>
            <div class="partition-details-item"><strong><?php echo $lang1908; ?></strong> ${lv.filesystem || 'Not formating'}</div>
            <div class="partition-details-item"><strong><?php echo $lang1909; ?></strong> ${lv.mount_point ? `<?php echo $lang1910; ?> <code>${lv.mount_point}</code>` : 'Not mounted'}</div>
            <div class="partition-details-item"><strong><?php echo $lang1911; ?></strong> ${lv.is_active ? 'Yes' : 'No'}</div>
        </div>
        ${!lv.has_filesystem ? `<div class="alert alert-warning mt-3">
            <i class="fas fa-exclamation-triangle"></i> <?php echo $lang1912; ?> 
            <?php echo $lang1913; ?>
        </div>` : ''}
        ${lv.mount_point ? `<div class="alert alert-info mt-3">
            <i class="fas fa-info-circle"></i> <?php echo $lang1914; ?>
        </div>` : ''}
    `;
    modal.show();
}

function extendLvFromModal() {
    if (currentLvInfo) {
        bootstrap.Modal.getInstance(document.getElementById('lvInfoModal')).hide();
        showExtendLv(currentLvInfo.vg_name, currentLvInfo.name, currentLvInfo.size_formatted);
    }
}

function mountLvFromModal() {
    if (currentLvInfo && currentLvInfo.has_filesystem) {
        bootstrap.Modal.getInstance(document.getElementById('lvInfoModal')).hide();
        showMountModal(currentLvInfo.path);
    }
}

async function umountLvFromModal() {
    if (currentLvInfo && currentLvInfo.mount_point) {
        await umountLv(currentLvInfo.mount_point);
        bootstrap.Modal.getInstance(document.getElementById('lvInfoModal')).hide();
    }
}

// ==================== ИНФОРМАЦИЯ О РАЗДЕЛЕ ====================

async function showPartitionInfo(partitionName) {
    const modal = new bootstrap.Modal(document.getElementById('partitionInfoModal'));
    const content = document.getElementById('partitionInfoContent');
    const title = document.getElementById('partitionInfoTitle');
    const createBtn = document.getElementById('createPvFromPartitionBtn');
    
    title.innerHTML = `<i class="fas fa-microchip"></i> ${partitionName}`;
    content.innerHTML = '<div class="text-center p-4"><div class="apple-spinner mx-auto"></div><p class="mt-2"><?php echo $lang1915; ?></p></div>';
    createBtn.style.display = 'none';
    modal.show();
    
    try {
        const res = await apiCall('get_partition_info', { partition: partitionName });
        if (res.success && res.info) {
            currentPartitionInfo = res.info;
            renderPartitionInfo(res.info);
            if (!res.info.is_pv) {
                createBtn.style.display = 'inline-block';
            }
        } else {
            content.innerHTML = `<div class="alert alert-danger"><?php echo $lang1916; ?> ${res.error || 'Unknown error'}</div>`;
        }
    } catch (e) {
        content.innerHTML = `<div class="alert alert-danger"><?php echo $lang1917; ?> ${e.message}</div>`;
    }
}

function renderPartitionInfo(info) {
    const mountStatus = info.mount_point ? `<span class="badge bg-danger"><?php echo $lang1918; ?> ${info.mount_point}</span>` : '<span class="badge bg-secondary"><?php echo $lang1919; ?></span>';
    const pvStatus = info.is_pv ? `<span class="badge bg-info"><?php echo $lang1920; ?> ${info.vg_name ? '(<?php echo $lang1921; ?> ' + info.vg_name + ')' : '(unused)'}</span>` : '<span class="badge bg-warning"><?php echo $lang1922; ?></span>';
    
    const html = `
        <div class="partition-details">
            <div class="partition-details-item"><strong><?php echo $lang1923; ?></strong> <code>${info.path}</code></div>
            <div class="partition-details-item"><strong><?php echo $lang1924; ?></strong> ${info.size}</div>
            <div class="partition-details-item"><strong><?php echo $lang1925; ?></strong> ${info.fstype || 'Not formated'}</div>
            <div class="partition-details-item"><strong><?php echo $lang1926; ?></strong> ${info.label || 'No'}</div>
            <div class="partition-details-item"><strong><?php echo $lang1927; ?></strong> <code>${info.uuid || 'No'}</code></div>
            <div class="partition-details-item"><strong><?php echo $lang1928; ?></strong> ${mountStatus}</div>
            <div class="partition-details-item"><strong><?php echo $lang1929; ?></strong> ${pvStatus}</div>
        </div>
        ${!info.is_pv ? `
        <div class="alert alert-info mt-3">
            <i class="fas fa-info-circle"></i> <?php echo $lang1930; ?> 
            <?php echo $lang1931; ?>
        </div>
        ` : ''}
    `;
    document.getElementById('partitionInfoContent').innerHTML = html;
}

async function createPvFromPartition() {
    if (!currentPartitionInfo) return;
    
    if (!confirm(`<?php echo $lang1932; ?> ${currentPartitionInfo.path}?\n<?php echo $lang1933; ?>`)) return;
    
    showLoader();
    const res = await apiCall('lvm_create_pv', { device: currentPartitionInfo.path });
    hideLoader();
    
    if (res.success) {
        showToast(`<?php echo $lang1934; ?> ${currentPartitionInfo.path}`);
        bootstrap.Modal.getInstance(document.getElementById('partitionInfoModal')).hide();
        refreshAll();
    } else {
        showToast(res.error, 'error');
    }
}

async function showDiskInfo(diskName) {
    const modal = new bootstrap.Modal(document.getElementById('diskInfoModal'));
    const content = document.getElementById('diskInfoContent');
    const title = document.getElementById('diskInfoTitle');
    
    title.innerHTML = `<i class="fas fa-hdd"></i> ${diskName}`;
    content.innerHTML = '<div class="text-center p-4"><div class="apple-spinner mx-auto"></div><p class="mt-2"><?php echo $lang1935; ?></p></div>';
    modal.show();
    
    try {
        const res = await apiCall('disk_info', { disk: diskName });
        if (res.success && res.info) {
            renderDiskInfo(res.info);
        } else {
            content.innerHTML = `<div class="alert alert-danger"><?php echo $lang1936; ?> ${res.error || 'Unknown error'}</div>`;
        }
    } catch (e) {
        content.innerHTML = `<div class="alert alert-danger"><?php echo $lang1937; ?> ${e.message}</div>`;
    }
}

function renderDiskInfo(disk) {
    const html = `
        <div class="row">
            <div class="col-md-6">
                <div class="card mb-3">
                    <div class="card-header bg-primary text-white"><?php echo $lang1938; ?></div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <tr><td><strong><?php echo $lang1939; ?></strong></td><td>${disk.name}</td></tr>
                            <tr><td><strong><?php echo $lang1940; ?></strong></td><td><code>${disk.path}</code></td></tr>
                            <tr><td><strong><?php echo $lang1941; ?></strong></td><td>${disk.size_formatted}</td></tr>
                            <tr><td><strong><?php echo $lang1942; ?></strong></td><td>${disk.model || 'N/A'}</td></tr>
                            <tr><td><strong><?php echo $lang1943; ?></strong></td><td>${disk.partition_table_type ? disk.partition_table_type.toUpperCase() : 'No'}</td></tr>
                            <tr><td><strong><?php echo $lang1944; ?></strong></td><td>${disk.is_system ? 'System disk' : (disk.removable ? 'Removable' : 'Interior')}</td></tr>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card mb-3">
                    <div class="card-header bg-info text-white"><?php echo $lang1945; ?></div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <thead><tr><th><?php echo $lang1946; ?></th><th><?php echo $lang1947; ?></th><th><?php echo $lang1948; ?></th><th><?php echo $lang1949; ?></th></tr></thead>
                            <tbody>
                                ${disk.partitions.map(part => `
                                    <tr>
                                        <td><code>${part.name}</code></td>
                                        <td>${part.size_formatted}</td>
                                        <td>${part.fstype || '-'}</td>
                                        <td>${part.mount_point || '-'}</td>
                                    </tr>
                                `).join('')}
                                ${disk.partitions.length === 0 ? '<tr><td colspan="4" class="text-center"><?php echo $lang1950; ?></td></tr>' : ''}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    `;
    document.getElementById('diskInfoContent').innerHTML = html;
}

// ==================== PV ОПЕРАЦИИ ====================

async function showCreatePv() {
    showLoader();
    try {
        const headers = {};
        if (window.apiConfig && window.apiConfig.apiKey) {
            headers['X-API-Key'] = window.apiConfig.apiKey;
        }
        
        const res = await fetch(url + 'lvm_api.php?action=get_raw_devices_with_status', {
            method: 'GET',
            headers: headers
        });
        const data = await res.json();
        hideLoader();
        
        if (data.success && data.devices && data.devices.length) {
            const container = document.getElementById('pvDevicesList');
            let html = '';
            
            const physicalDevices = data.devices.filter(dev => dev.type === 'disk' || dev.type === 'partition');
            
            if (physicalDevices.length === 0) {
                html = '<div class="col-12 alert alert-warning"><?php echo $lang1951; ?></div>';
            } else {
                for (const dev of physicalDevices) {
                    const isUsed = dev.is_pv === true;
                    const isSystem = dev.is_system === true;
                    const disabled = isUsed || isSystem;
                    let disabledReason = '';
                    if (isUsed) disabledReason = '<?php echo $lang1952; ?>';
                    if (isSystem) disabledReason = '<?php echo $lang1953; ?>';
                    
                    html += `<div class="col-md-6 col-lg-4">
                        <div class="card ${disabled ? 'bg-light opacity-50' : 'border-primary'} h-100">
                            <div class="card-body p-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" 
                                        value="${dev.path}" 
                                        id="dev_${dev.name.replace(/[\/-]/g, '_')}"
                                        ${disabled ? 'disabled' : ''}>
                                    <label class="form-check-label ${disabled ? 'text-muted' : 'fw-bold'}" 
                                        for="dev_${dev.name.replace(/[\/-]/g, '_')}">
                                        <i class="fas ${dev.type === 'disk' ? 'fa-hdd' : 'fa-microchip'} me-2"></i>
                                        ${dev.name}
                                    </label>
                                </div>
                                <div class="small mt-2">
                                    <div><i class="fas fa-chart-pie me-1"></i> <?php echo $lang1954; ?> ${dev.size_formatted}</div>
                                    <div><i class="fas fa-tag me-1"></i> <?php echo $lang1955; ?> ${dev.type === 'disk' ? 'Disk' : 'Partition'}</div>
                                    ${dev.fstype ? `<div><i class="fas fa-file me-1"></i> <?php echo $lang1956; ?> ${dev.fstype}</div>` : ''}
                                    ${dev.model ? `<div><i class="fas fa-microchip me-1"></i> <?php echo $lang1957; ?> ${dev.model}</div>` : ''}
                                    ${dev.parent_disk ? `<div><i class="fas fa-hdd me-1"></i> <?php echo $lang1958; ?> ${dev.parent_disk}</div>` : ''}
                                </div>
                                ${disabledReason ? `<div class="badge bg-secondary mt-2">${disabledReason}</div>` : ''}
                            </div>
                        </div>
                    </div>`;
                }
            }
            
            container.innerHTML = html;
            new bootstrap.Modal(document.getElementById('createPvModal')).show();
        } else {
            showToast('<?php echo $lang1959; ?>', 'error');
        }
    } catch (e) {
        hideLoader();
        showToast('<?php echo $lang1960; ?>' + e.message, 'error');
    }
}

async function createPvFromSelected() {
    const selected = Array.from(document.querySelectorAll('#pvDevicesList input:checked:not(:disabled)')).map(cb => cb.value);
    
    if (selected.length === 0) {
        showToast('<?php echo $lang1961; ?>', 'warning');
        return;
    }
    
    if (!confirm(`<?php echo $lang1962; ?> ${selected.length} <?php echo $lang1963; ?>\n\n<?php echo $lang1964; ?>`)) {
        return;
    }
    
    showLoader();
    let successCount = 0;
    let errorCount = 0;
    
    for (const device of selected) {
        const res = await apiCall('lvm_create_pv', { device });
        if (res.success) {
            successCount++;
        } else {
            errorCount++;
            console.error(`Error in ${device}:`, res.error);
        }
    }
    
    hideLoader();
    
    if (successCount > 0) {
        bootstrap.Modal.getInstance(document.getElementById('createPvModal')).hide();
        await refreshAll();
        showToast(`<?php echo $lang1965; ?> ${successCount}, <?php echo $lang1966; ?> ${errorCount}`);
    } else {
        showToast('<?php echo $lang1967; ?>', 'error');
    }
}

async function createPv() {
    const device = document.getElementById('pvDevice').value;
    if (!device) return showToast('<?php echo $lang1968; ?>', 'error');
    if (!confirm(`<?php echo $lang1969; ?> ${device}? <?php echo $lang1970; ?>`)) return;
    
    showLoader();
    const res = await apiCall('lvm_create_pv', { device });
    hideLoader();
    
    if (res.success) {
        bootstrap.Modal.getInstance(document.getElementById('createPvModal')).hide();
        refreshAll();
        showToast('<?php echo $lang1971; ?>');
    } else {
        showToast(res.error, 'error');
    }
}

async function deletePv(pvName) {
    if (!confirm(`<?php echo $lang1972; ?> ${pvName}?\n<?php echo $lang1973; ?>`)) return;
    
    showLoader();
    const res = await apiCall('lvm_delete_pv', { pv_name: pvName });
    hideLoader();
    
    if (res.success) {
        refreshAll();
        showToast('<?php echo $lang1974; ?>');
    } else {
        showToast(res.error, 'error');
    }
}

// ==================== VG ОПЕРАЦИИ ====================

async function showCreateVg() {
    showLoader();
    try {
        const headers = {};
        if (window.apiConfig && window.apiConfig.apiKey) {
            headers['X-API-Key'] = window.apiConfig.apiKey;
        }
        
        const res = await fetch(url + 'lvm_api.php?action=get_available_pvs', {
            method: 'GET',
            headers: headers
        });
        const data = await res.json();
        hideLoader();
        
        if (data.success && data.pvs && data.pvs.length) {
            document.getElementById('pvList').innerHTML = data.pvs.map(pv => `<div class="form-check"><input class="form-check-input" type="checkbox" value="${pv.name}" id="pv_${pv.name.replace(/[\/-]/g, '_')}"><label class="form-check-label">${pv.name} (${pv.size_formatted})</label></div>`).join('');
            document.getElementById('vgName').value = '';
            new bootstrap.Modal(document.getElementById('createVgModal')).show();
        } else {
            showToast('<?php echo $lang1975; ?>', 'warning');
        }
    } catch (e) {
        hideLoader();
        showToast('<?php echo $lang1976; ?> ' + e.message, 'error');
    }
}

async function createVg() {
    const vgName = document.getElementById('vgName').value.trim();
    const devices = Array.from(document.querySelectorAll('#pvList input:checked')).map(cb => cb.value);
    if (!vgName) return showToast('<?php echo $lang1977; ?>', 'error');
    if (!devices.length) return showToast('<?php echo $lang1978; ?>', 'error');
    
    showLoader();
    const res = await apiCall('lvm_create_vg', { vg_name: vgName, devices });
    hideLoader();
    
    if (res.success) {
        bootstrap.Modal.getInstance(document.getElementById('createVgModal')).hide();
        refreshAll();
        showToast(`<?php echo $lang1979; ?> ${vgName} <?php echo $lang1980; ?>`);
    } else {
        showToast(res.error, 'error');
    }
}

async function deleteVg(vgName) {
    if (!confirm(`<?php echo $lang1981; ?> ${vgName}?\n<?php echo $lang1982; ?>`)) return;
    
    showLoader();
    const res = await apiCall('lvm_delete_vg', { vg_name: vgName });
    hideLoader();
    
    if (res.success) {
        refreshAll();
        showToast('<?php echo $lang1983; ?>');
    } else {
        showToast(res.error, 'error');
    }
}

async function showExtendVg(vgName) {
    showLoader();
    try {
        const headers = {};
        if (window.apiConfig && window.apiConfig.apiKey) {
            headers['X-API-Key'] = window.apiConfig.apiKey;
        }
        
        const res = await fetch(url + 'lvm_api.php?action=get_available_pvs', {
            method: 'GET',
            headers: headers
        });
        const data = await res.json();
        hideLoader();
        
        if (data.success && data.pvs && data.pvs.length) {
            document.getElementById('extendVgName').value = vgName;
            document.getElementById('extendPvList').innerHTML = data.pvs.map(pv => `<div class="form-check"><input class="form-check-input" type="checkbox" value="${pv.name}" id="extend_${pv.name.replace(/[\/-]/g, '_')}"><label class="form-check-label">${pv.name} (${pv.size_formatted})</label></div>`).join('');
            new bootstrap.Modal(document.getElementById('extendVgModal')).show();
        } else {
            showToast('<?php echo $lang1984; ?>', 'warning');
        }
    } catch (e) {
        hideLoader();
        showToast('<?php echo $lang1985; ?> ' + e.message, 'error');
    }
}

async function extendVg() {
    const vgName = document.getElementById('extendVgName').value;
    const devices = Array.from(document.querySelectorAll('#extendPvList input:checked')).map(cb => cb.value);
    if (!devices.length) return showToast('<?php echo $lang1986; ?>', 'error');
    
    showLoader();
    const res = await apiCall('lvm_extend_vg', { vg_name: vgName, devices });
    hideLoader();
    
    if (res.success) {
        bootstrap.Modal.getInstance(document.getElementById('extendVgModal')).hide();
        refreshAll();
        showToast('<?php echo $lang1987; ?>');
    } else {
        showToast(res.error, 'error');
    }
}

async function showReduceVg(vgName) {
    showLoader();
    try {
        const headers = {};
        if (window.apiConfig && window.apiConfig.apiKey) {
            headers['X-API-Key'] = window.apiConfig.apiKey;
        }
        
        const res = await fetch(`${url}lvm_api.php?action=get_pvs_in_vg&vg_name=${encodeURIComponent(vgName)}`, {
            method: 'GET',
            headers: headers
        });
        const data = await res.json();
        hideLoader();
        
        if (data.success && data.pvs && data.pvs.length) {
            document.getElementById('reduceVgName').value = vgName;
            document.getElementById('reducePvList').innerHTML = data.pvs.map(pv => `<div class="form-check"><input class="form-check-input" type="checkbox" value="${pv.name}" id="reduce_${pv.name.replace(/[\/-]/g, '_')}"><label class="form-check-label">${pv.name} (${pv.size_formatted}) - Used: ${pv.used_formatted}</label></div>`).join('');
            new bootstrap.Modal(document.getElementById('reduceVgModal')).show();
        } else {
            showToast('<?php echo $lang1988; ?>', 'warning');
        }
    } catch (e) {
        hideLoader();
        showToast('<?php echo $lang1989; ?> ' + e.message, 'error');
    }
}

async function reduceVg() {
    const vgName = document.getElementById('reduceVgName').value;
    const devices = Array.from(document.querySelectorAll('#reducePvList input:checked')).map(cb => cb.value);
    if (!devices.length) return showToast('<?php echo $lang1990; ?>', 'error');
    
    if (!confirm(`<?php echo $lang1991; ?> ${vgName}?\n\n<?php echo $lang1992; ?>`)) return;
    
    showLoader();
    const res = await apiCall('lvm_reduce_vg', { vg_name: vgName, devices });
    hideLoader();
    
    if (res.success) {
        bootstrap.Modal.getInstance(document.getElementById('reduceVgModal')).hide();
        refreshAll();
        if (res.removed) {
            showToast(`<?php echo $lang1993; ?> ${res.removed.join(', ')}`);
        }
        if (res.errors && res.errors.length) {
            showToast(`<?php echo $lang1994; ?> ${res.errors.join('; ')}`, 'warning');
        } else {
            showToast('<?php echo $lang1995; ?>');
        }
    } else {
        showToast(res.error, 'error');
    }
}

async function showRenameVg(vgName) {
    document.getElementById('renameVgOldName').value = vgName;
    document.getElementById('renameVgNewName').value = vgName;
    new bootstrap.Modal(document.getElementById('renameVgModal')).show();
}

async function renameVg() {
    const oldName = document.getElementById('renameVgOldName').value;
    const newName = document.getElementById('renameVgNewName').value.trim();
    if (!newName) return showToast('<?php echo $lang1997; ?>', 'error');
    if (oldName === newName) return showToast('<?php echo $lang1996; ?>', 'warning');
    
    showLoader();
    const res = await apiCall('lvm_rename_vg', { old_name: oldName, new_name: newName });
    hideLoader();
    
    if (res.success) {
        bootstrap.Modal.getInstance(document.getElementById('renameVgModal')).hide();
        refreshAll();
        showToast('<?php echo $lang1998; ?>');
    } else {
        showToast(res.error, 'error');
    }
}

// ==================== LV ОПЕРАЦИИ ====================

function showCreateLv(vgName) {
    document.getElementById('lvVgName').value = vgName;
    document.getElementById('lvName').value = '';
    document.getElementById('lvSize').value = '';
    document.getElementById('lvFormat').checked = false;
    document.getElementById('lvFsTypeDiv').style.display = 'none';
    new bootstrap.Modal(document.getElementById('createLvModal')).show();
}

async function createLv() {
    const vgName = document.getElementById('lvVgName').value;
    const lvName = document.getElementById('lvName').value.trim();
    const size = document.getElementById('lvSize').value.trim();
    const format = document.getElementById('lvFormat')?.checked || false;
    const fsType = document.getElementById('lvFsType')?.value || 'ext4';
    
    if (!lvName) return showToast('<?php echo $lang1999; ?>', 'error');
    if (!size) return showToast('<?php echo $lang2000; ?>', 'error');
    
    showLoader();
    const res = await apiCall('lvm_create_lv', { 
        vg_name: vgName, 
        lv_name: lvName, 
        size, 
        format, 
        fs_type: fsType 
    });
    hideLoader();
    
    if (res.success) {
        closeAllModals();
        await refreshAll();
        if (format) {
            showToast(`<?php echo $lang2001; ?> ${lvName} <?php echo $lang2002; ?> ${fsType}`);
        } else {
            showToast(`<?php echo $lang2001; ?> ${lvName} <?php echo $lang2003; ?>`);
        }
    } else {
        showToast(res.error, 'error');
    }
}

async function checkLvStatus(lvPath) {
    try {
        const res = await apiCall('lv_status', { lv_path: lvPath });
        return res;
    } catch (e) {
        return { success: false, error: e.message };
    }
}

async function formatLv(vgName, lvName, checkMount = true) {
    const lvPath = `/dev/${vgName}/${lvName}`;
    
    // Проверяем статус LV перед форматированием
    showLoader();
    const statusRes = await apiCall('lv_status', { lv_path: lvPath });
    
    if (statusRes.success && statusRes.exists) {
        if (statusRes.active === false) {
            showToast('<?php echo $lang2004; ?>', 'warning');
            hideLoader();
            return;
        }
        
        if (statusRes.mounted === true) {
            showToast('<?php echo $lang2005; ?>', 'error');
            hideLoader();
            return;
        }
    }
    hideLoader();
    
    showFormatLvModal(vgName, lvName);
}

async function deleteLv(vgName, lvName) {
    if (!confirm(`<?php echo $lang2006; ?> ${lvName}?`)) return;
    
    showLoader();
    const res = await apiCall('lvm_delete_lv', { vg_name: vgName, lv_name: lvName });
    hideLoader();
    
    if (res.success) {
        refreshAll();
        showToast('<?php echo $lang2007; ?>');
    } else {
        showToast(res.error, 'error');
    }
}

function showExtendLv(vgName, lvName, currentSize) {
    document.getElementById('extendLvVg').value = vgName;
    document.getElementById('extendLvName').value = lvName;
    document.getElementById('extendLvCurrentSize').innerText = currentSize;
    document.getElementById('extendLvSize').value = '';
    new bootstrap.Modal(document.getElementById('extendLvModal')).show();
}

async function extendLv() {
    const vgName = document.getElementById('extendLvVg').value;
    const lvName = document.getElementById('extendLvName').value;
    const size = document.getElementById('extendLvSize').value.trim();
    
    if (!size) return showToast('<?php echo $lang2008; ?>', 'error');
    
    showLoader();
    const res = await apiCall('lvm_extend_lv', { vg_name: vgName, lv_name: lvName, size });
    hideLoader();
    
    if (res.success) {
        bootstrap.Modal.getInstance(document.getElementById('extendLvModal')).hide();
        refreshAll();
        showToast('<?php echo $lang2009; ?>');
    } else {
        showToast(res.error, 'error');
    }
}

function showRenameLv(vgName, lvName) {
    document.getElementById('renameLvVg').value = vgName;
    document.getElementById('renameLvOldName').value = lvName;
    document.getElementById('renameLvNewName').value = lvName;
    new bootstrap.Modal(document.getElementById('renameLvModal')).show();
}

async function renameLv() {
    const vgName = document.getElementById('renameLvVg').value;
    const oldName = document.getElementById('renameLvOldName').value;
    const newName = document.getElementById('renameLvNewName').value.trim();
    if (!newName) return showToast('<?php echo $lang2010; ?>', 'error');
    if (oldName === newName) return showToast('<?php echo $lang2011; ?>', 'warning');
    
    showLoader();
    const res = await apiCall('lvm_rename_lv', { vg_name: vgName, old_name: oldName, new_name: newName });
    hideLoader();
    
    if (res.success) {
        bootstrap.Modal.getInstance(document.getElementById('renameLvModal')).hide();
        refreshAll();
        showToast('<?php echo $lang2012; ?>');
    } else {
        showToast(res.error, 'error');
    }
}

// ==================== МОНТИРОВАНИЕ ====================

function showMountModal(device) {
    document.getElementById('mountDevice').value = device;
    const defaultMountPoint = '/mnt/' + device.split('/').pop();
    document.getElementById('mountPoint').value = defaultMountPoint;
    document.getElementById('mountFstab').checked = false;
    document.getElementById('mountFs').value = 'auto';
    new bootstrap.Modal(document.getElementById('mountModal')).show();
}

async function mountLv() {
    // ====== ОТЛАДКА ======
    const device = document.getElementById('mountDevice').value;
    const mountPoint = document.getElementById('mountPoint').value.trim();
    const fs = document.getElementById('mountFs').value;
    const fstab = document.getElementById('mountFstab').checked;
    
    console.log('=== MOUNT LV DEBUG ===');
    console.log('device:', device);
    console.log('mountPoint:', mountPoint);
    console.log('fs:', fs);
    console.log('fstab (checkbox checked):', fstab);
    console.log('fstab (type):', typeof fstab);
    // =====================
    
    if (!mountPoint) return showToast('<?php echo $lang2013; ?>', 'error');
    
    if (!mountPoint.startsWith('/mnt/') && !mountPoint.startsWith('/media/')) {
        if (!confirm('<?php echo $lang2014; ?>')) {
            return;
        }
    }
    
    showLoader();
    
    // ====== ОТЛАДКА ПЕРЕД ОТПРАВКОЙ ======
    const payload = { device, mount_point: mountPoint, fs, fstab };
    console.log('Sending payload:', payload);
    console.log('Payload JSON:', JSON.stringify(payload));
    // ====================================
    
    const res = await apiCall('lvm_mount', payload);
    
    console.log('Response:', res);
    // ====================================
    
    if (res.success) {
        closeAllModals();
        await new Promise(resolve => setTimeout(resolve, 1000));
        await refreshAll();
        showToast(`<?php echo $lang2015; ?> ${res.mount_point || mountPoint}`);
    } else {
        showToast(res.error, 'error');
    }
    
    hideLoader();
}

async function umountLv(mountPoint) {
    if (!confirm(`<?php echo $lang2016; ?> ${mountPoint}?`)) return;
    
    const result = await showDialog(
        '<?php echo $lang4776; ?>',
        `<?php echo $lang4777; ?> ${mountPoint}?`,
        [
            { text: '<?php echo $lang4778; ?>', value: true, class: 'btn-danger' },
            { text: '<?php echo $lang4779; ?>', value: false, class: 'btn-secondary' }
        ]
    );
    
    if (result === undefined) return;
    
    showLoader();
    const res = await apiCall('lvm_umount', { 
        mount_point: mountPoint,
        remove_from_fstab: result 
    });
    
    if (res.success) {
        await new Promise(resolve => setTimeout(resolve, 500));
        await refreshAll();
        let message = res.message || '<?php echo $lang2017; ?>';
        if (result) {
            message += ' <?php echo $lang4780; ?>';
        }
        showToast(message);
    } else {
        showToast(res.error, 'error');
    }
    
    hideLoader();
}

function showDialog(title, message, buttons) {
    return new Promise((resolve) => {
        const overlay = document.createElement('div');
        overlay.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        `;
        
        const dialog = document.createElement('div');
        dialog.style.cssText = `
            background: #fff;
            padding: 30px;
            border-radius: 8px;
            max-width: 500px;
            min-width: 300px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        `;
        
        const titleEl = document.createElement('h3');
        titleEl.textContent = title;
        titleEl.style.cssText = `
            margin: 0 0 15px 0;
            font-size: 18px;
            color: #333;
        `;
        dialog.appendChild(titleEl);
        
        const messageEl = document.createElement('p');
        messageEl.textContent = message;
        messageEl.style.cssText = `
            margin: 0 0 25px 0;
            font-size: 14px;
            color: #666;
            line-height: 1.5;
        `;
        dialog.appendChild(messageEl);
        
        const buttonContainer = document.createElement('div');
        buttonContainer.style.cssText = `
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        `;
        
        buttons.forEach((btn) => {
            const button = document.createElement('button');
            button.textContent = btn.text;
            button.style.cssText = `
                padding: 8px 20px;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                font-size: 14px;
                ${btn.class === 'btn-danger' ? 'background: #dc3545; color: #fff;' : ''}
                ${btn.class === 'btn-secondary' ? 'background: #6c757d; color: #fff;' : ''}
                ${!btn.class ? 'background: #007bff; color: #fff;' : ''}
            `;
            
            button.onmouseenter = () => {
                button.style.opacity = '0.8';
            };
            button.onmouseleave = () => {
                button.style.opacity = '1';
            };
            
            button.onclick = () => {
                document.body.removeChild(overlay);
                resolve(btn.value);
            };
            
            buttonContainer.appendChild(button);
        });
        
        dialog.appendChild(buttonContainer);
        overlay.appendChild(dialog);
        document.body.appendChild(overlay);
        
        overlay.onclick = (e) => {
            if (e.target === overlay) {
                document.body.removeChild(overlay);
                resolve(undefined);
            }
        };
    });
}

async function showCreateSnapshot(vgName, originLv) {
    document.getElementById('snapshotVgName').value = vgName;
    document.getElementById('snapshotOriginLv').value = originLv;
    
    const now = new Date();
    const timestamp = now.toISOString().slice(0,16).replace(/[:-]/g, '').replace('T', '_');
    let baseName = `snap_${originLv}_${timestamp}`;
    
    try {
        const res = await apiCall('get_snapshots');
        if (res.success && res.snapshots) {
            let counter = 1;
            let snapshotName = baseName;
            const existingNames = res.snapshots.map(s => s.name);
            
            while (existingNames.includes(snapshotName)) {
                snapshotName = `${baseName}_v${counter}`;
                counter++;
            }
            baseName = snapshotName;
        }
    } catch(e) {
        console.log('Error checking snapshots:', e);
    }
    
    document.getElementById('snapshotName').value = baseName;
    document.getElementById('snapshotSize').value = '10G';
    new bootstrap.Modal(document.getElementById('createSnapshotModal')).show();
}

async function deleteSnapshotByName(vgName, snapshotName) {
    if (!confirm(`<?php echo $lang2018; ?> ${snapshotName}?`)) return;
    
    showLoader();
    const res = await apiCall('delete_snapshot', {
        vg_name: vgName,
        snapshot_name: snapshotName
    });
    hideLoader();
    
    if (res.success) {
        await refreshAll();
        showToast(`<?php echo $lang2019; ?> ${snapshotName} <?php echo $lang2020; ?>!`);
        
        const snapshotsModal = document.getElementById('snapshotsListModal');
        if (snapshotsModal && snapshotsModal.classList.contains('show')) {
            await refreshSnapshotsList();
        }
    } else {
        showToast(res.error, 'error');
    }
}

async function createSnapshot() {
    const vgName = document.getElementById('snapshotVgName').value;
    const originLv = document.getElementById('snapshotOriginLv').value;
    let snapshotName = document.getElementById('snapshotName').value.trim();
    const size = document.getElementById('snapshotSize').value.trim();
    
    if (!snapshotName) {
        showToast('<?php echo $lang2021; ?>', 'error');
        return;
    }
    if (!size) {
        showToast('<?php echo $lang2022; ?>', 'error');
        return;
    }
    
    showLoader();
    const res = await apiCall('create_snapshot', {
        vg_name: vgName,
        origin_lv: originLv,
        snapshot_name: snapshotName,
        size: size
    });
    hideLoader();
    
    if (res.success) {
        bootstrap.Modal.getInstance(document.getElementById('createSnapshotModal')).hide();
        await refreshAll();
        const createdName = res.snapshot_name || snapshotName;
        showToast(`<?php echo $lang2023; ?> ${createdName} <?php echo $lang2024; ?>`);
    } else {
        showToast(res.error, 'error');
    }
}

async function showSnapshotsList() {
    const modal = new bootstrap.Modal(document.getElementById('snapshotsListModal'));
    const content = document.getElementById('snapshotsListContent');
    content.innerHTML = '<div class="text-center p-4"><div class="apple-spinner mx-auto"></div><p><?php echo $lang2025; ?></p></div>';
    modal.show();
    
    await refreshSnapshotsList();
}

async function refreshSnapshotsList() {
    try {
        const res = await apiCall('get_snapshots');
        const content = document.getElementById('snapshotsListContent');
        
        if (res.success && res.snapshots && res.snapshots.length > 0) {
            let html = `<div class="row">
                <div class="col-12 mb-3">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> 
                        <?php echo $lang2026; ?>
                    </div>
                </div>`;
            
            for (const snap of res.snapshots) {
                const usedPercent = snap.data_percent;
                const isMerging = snap.is_merging;
                const progressColor = usedPercent > 80 ? 'bg-danger' : (usedPercent > 50 ? 'bg-warning' : 'bg-success');
                
                html += `
                    <div class="col-md-6 mb-3">
                        <div class="card ${isMerging ? 'border-warning' : ''}" onclick="showSnapshotInfo('${snap.vg_name}', '${snap.name}')" style="cursor: pointer;">
                            <div class="card-header ${isMerging ? 'bg-warning' : 'bg-info'} text-white">
                                <i class="fas fa-camera"></i> ${snap.name}
                                ${isMerging ? '<span class="badge bg-dark ms-2"><?php echo $lang2027; ?></span>' : ''}
                            </div>
                            <div class="card-body">
                                <table class="table table-sm mb-0">
                                    <tr><td width="40%"><?php echo $lang2028; ?></td><td><strong>${snap.vg_name}</strong></td></tr>
                                    <tr><td><?php echo $lang2029; ?></td><td><strong>${snap.origin}</strong></td></tr>
                                    <tr><td><?php echo $lang2030; ?></td><td>${snap.size}</td></tr>
                                    <tr><td><?php echo $lang2031; ?></td><td>${snap.data_percent}%</td></tr>
                                    <tr><td><?php echo $lang2032; ?></td><td>${snap.metadata_percent}%</td></tr>
                                    <tr><td><?php echo $lang2033; ?></td><td>${snap.is_active ? '<span class="badge bg-success"><?php echo $lang2034; ?></span>' : '<span class="badge bg-secondary"><?php echo $lang2035; ?></span>'}</td></tr>
                                    ${snap.mount_point ? `<tr><td><?php echo $lang2036; ?></td><td><code>${snap.mount_point}</code></td></tr>` : ''}
                                </table>
                                <div class="progress mt-2" style="height: 8px;">
                                    <div class="progress-bar ${progressColor}" style="width: ${usedPercent}%"></div>
                                </div>
                                <div class="small text-muted mt-1"><?php echo $lang2037; ?> ${usedPercent}%</div>
                            </div>
                            <div class="card-footer">
                                <button class="btn btn-sm btn-outline-warning" onclick="event.stopPropagation(); restoreSnapshot('${snap.vg_name}', '${snap.name}', '${snap.origin}')">
                                    <i class="fas fa-undo"></i> <?php echo $lang2038; ?>
                                </button>
                                <button class="btn btn-sm btn-outline-danger" onclick="event.stopPropagation(); deleteSnapshot('${snap.vg_name}', '${snap.name}')">
                                    <i class="fas fa-trash"></i> <?php echo $lang2039; ?>
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
                    <i class="fas fa-info-circle"></i> <?php echo $lang2040; ?>
                    <hr>
                    <?php echo $lang2041; ?>
                </div>
                <div class="text-center">
                    <button class="btn btn-apple" onclick="closeSnapshotsListAndCreate()"><?php echo $lang2042; ?></button>
                </div>
            `;
        }
    } catch (e) {
        document.getElementById('snapshotsListContent').innerHTML = `<div class="alert alert-danger"><?php echo $lang2043; ?> ${e.message}</div>`;
    }
}

function closeSnapshotsListAndCreate() {
    bootstrap.Modal.getInstance(document.getElementById('snapshotsListModal')).hide();
    showToast('<?php echo $lang2044; ?>', 'info');
}

async function showSnapshotInfo(vgName, snapshotName) {
    const modal = new bootstrap.Modal(document.getElementById('snapshotInfoModal'));
    const content = document.getElementById('snapshotInfoContent');
    const title = document.getElementById('snapshotInfoTitle');
    
    title.innerHTML = `<i class="fas fa-camera"></i> ${vgName}/${snapshotName}`;
    content.innerHTML = '<div class="text-center p-4"><div class="apple-spinner mx-auto"></div><p><?php echo $lang2045; ?></p></div>';
    modal.show();
    
    try {
        const res = await apiCall('get_snapshot_info', { vg_name: vgName, snapshot_name: snapshotName });
        
        if (res.success && res.info) {
            currentSnapshotInfo = res.info;
            const snap = res.info;
            const usedPercent = snap.data_percent;
            const progressColor = usedPercent > 80 ? 'danger' : (usedPercent > 50 ? 'warning' : 'success');
            
            content.innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <div class="card mb-3">
                            <div class="card-header bg-info text-white"><?php echo $lang2046; ?></div>
                            <div class="card-body">
                                <table class="table table-sm">
                                    <tr><td width="40%"><?php echo $lang2047; ?></td><td><strong>${snap.name}</strong></td></tr>
                                    <tr><td><?php echo $lang2048; ?></td><td><strong>${snap.vg_name}</strong></td></tr>
                                    <tr><td><?php echo $lang2049; ?></td><td><strong>${snap.origin}</strong></td></tr>
                                    <tr><td><?php echo $lang2050; ?></td><td>${snap.size}</td></tr>
                                    <tr><td><?php echo $lang2051; ?></td><td><code>${snap.path}</code></td></tr>
                                    <tr><td><?php echo $lang2052; ?></td><td>${snap.is_active ? '<span class="badge bg-success"><?php echo $lang2053; ?></span>' : '<span class="badge bg-secondary"><?php echo $lang2054; ?></span>'}</td></tr>
                                    <tr><td><?php echo $lang2055; ?></td><td>${snap.is_merging ? '<span class="badge bg-warning"><?php echo $lang2056; ?></span>' : '<span class="badge bg-secondary"><?php echo $lang2057; ?></span>'}</td></tr>
                                    ${snap.mount_point ? `<tr><td><?php echo $lang2058; ?></td><td><code>${snap.mount_point}</code></td></tr>` : ''}
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card mb-3">
                            <div class="card-header bg-primary text-white"><?php echo $lang2059; ?></div>
                            <div class="card-body">
                                <label><?php echo $lang2060; ?></label>
                                <div class="progress mb-3" style="height: 20px;">
                                    <div class="progress-bar bg-${progressColor}" style="width: ${usedPercent}%">
                                        ${usedPercent}%
                                    </div>
                                </div>
                                <label><?php echo $lang2061; ?></label>
                                <div class="progress mb-3" style="height: 20px;">
                                    <div class="progress-bar bg-info" style="width: ${snap.metadata_percent}%">
                                        ${snap.metadata_percent}%
                                    </div>
                                </div>
                                ${snap.copy_percent > 0 ? `
                                <label><?php echo $lang2062; ?></label>
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
                    <strong><?php echo $lang2063; ?></strong> <?php echo $lang2064; ?> 
                    <?php echo $lang2065; ?>
                </div>
            `;
        } else {
            content.innerHTML = `<div class="alert alert-danger">${res.error || '<?php echo $lang2066; ?>'}</div>`;
        }
    } catch (e) {
        content.innerHTML = `<div class="alert alert-danger"><?php echo $lang2067; ?> ${e.message}</div>`;
    }
}

async function restoreSnapshot(vgName, snapshotName, originLv = null) {
    const confirmMsg = originLv 
        ? `<?php echo $lang2068; ?> ${originLv} <?php echo $lang2069; ?> ${snapshotName}?\n\n<?php echo $lang2070; ?> ${originLv} <?php echo $lang2071; ?>`
        : `<?php echo $lang2072; ?> ${snapshotName}?\n\n<?php echo $lang2073; ?>`;
    
    if (!confirm(confirmMsg)) return;
    
    showLoader();
    const res = await apiCall('restore_snapshot', {
        vg_name: vgName,
        snapshot_name: snapshotName,
        origin_lv: originLv
    });
    hideLoader();
    
    if (res.success) {
        const snapshotModal = bootstrap.Modal.getInstance(document.getElementById('snapshotInfoModal'));
        if (snapshotModal) snapshotModal.hide();
        
        await refreshAll();
        showToast(res.message || '<?php echo $lang2074; ?>');
        
        const snapshotsModal = document.getElementById('snapshotsListModal');
        if (snapshotsModal && snapshotsModal.classList.contains('show')) {
            await refreshSnapshotsList();
        }
    } else {
        showToast(res.error, 'error');
    }
}

async function deleteSnapshot(vgName, snapshotName) {
    if (!confirm(`<?php echo $lang2075; ?> ${snapshotName}?\n\n<?php echo $lang2076; ?> ${snapshotName} <?php echo $lang2077; ?>`)) return;
    
    showLoader();
    const res = await apiCall('delete_snapshot', {
        vg_name: vgName,
        snapshot_name: snapshotName
    });
    hideLoader();
    
    if (res.success) {
        const snapshotModal = bootstrap.Modal.getInstance(document.getElementById('snapshotInfoModal'));
        if (snapshotModal) snapshotModal.hide();
        
        await refreshAll();
        showToast(`<?php echo $lang2078; ?> ${snapshotName} <?php echo $lang2079; ?>`);
        
        const snapshotsModal = document.getElementById('snapshotsListModal');
        if (snapshotsModal && snapshotsModal.classList.contains('show')) {
            await refreshSnapshotsList();
        }
    } else {
        showToast(res.error, 'error');
    }
}

function restoreSnapshotFromModal() {
    if (currentSnapshotInfo) {
        restoreSnapshot(currentSnapshotInfo.vg_name, currentSnapshotInfo.name, currentSnapshotInfo.origin);
    }
}

function deleteSnapshotFromModal() {
    if (currentSnapshotInfo) {
        bootstrap.Modal.getInstance(document.getElementById('snapshotInfoModal')).hide();
        deleteSnapshot(currentSnapshotInfo.vg_name, currentSnapshotInfo.name);
    }
}

// Инициализация
refreshAll();
</script>
</body>
</html>
