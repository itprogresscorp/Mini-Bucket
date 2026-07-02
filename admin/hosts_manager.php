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
require_once 'lang/loader.php';
isAuthenticated();

$db = getDB();
require_once 'menu.php';

$stmt = $db->prepare("SELECT hostPin FROM hosts WHERE idHost = 1");
$result = $stmt->execute();
$row = $result->fetchArray(SQLITE3_ASSOC);
$pin = $row['hostPin'] ?? '';
$pinDisplay = !empty($pin) ? str_repeat('•', strlen($pin)) : '••••';

$slaveIp = $_SERVER['SERVER_ADDR'];
if ($slaveIp === '127.0.0.1' || $slaveIp === '::1') {
    $slaveIp = gethostbyname(gethostname());
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Host Manager — Mini-B</title>
    <link href="lib/bootstrap-5.3.8-dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="lib/bootstrap-icons-1.11.0/bootstrap-icons.css">
    <!--<link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">-->
    <link rel="stylesheet" href="lib/fontawesome-free-6.7.2-web/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="css/loader.css">
    <link rel="shortcut icon" href="css/icon.ico" type="image/x-icon">
	<script src="lib/chart.js-4.5.1/package/dist/chart.umd.min.js"></script>
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
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e9eef3 100%);
            min-height: 100vh;
        }
        
        .main-content { padding: 25px 30px; }
        
        @media (max-width: 768px) { .main-content { padding: 20px 15px; } }
        
        .app-container {
            display: flex;
            min-height: calc(100vh - 60px);
        }
        
        .nav-tabs-custom {
            border-bottom: 1px solid #e9ecef;
            
            background: white;
            border-radius: 16px 16px 0 0;
            padding: 0 20px;
        }
        
        .nav-tabs-custom .nav-link {
            border: none;
            font-weight: 500;
            padding: 14px 24px;
            color: #6c757d;
            transition: all 0.2s;
            position: relative;
        }
        
        .nav-tabs-custom .nav-link:hover {
            color: #007aff;
            background: transparent;
        }
        
        .nav-tabs-custom .nav-link.active {
            color: #007aff;
            background: transparent;
        }
        
        .nav-tabs-custom .nav-link.active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: #007aff;
            border-radius: 2px;
        }
        
        .tab-content {
            background: transparent;
        }
        
        .host-card, .request-card {
            background: white;
            border-radius: 20px;
            border: 1px solid #e9ecef;
            transition: all 0.2s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .host-card:hover, .request-card:hover {
            border-color: #007aff40;
            box-shadow: 0 8px 20px rgba(0,122,255,0.1);
            transform: translateY(-2px);
        }
        
        .host-card.online { border-left: 4px solid #34c759; }
        .host-card.offline { border-left: 4px solid #ff3b30; }
        .host-card.pending { border-left: 4px solid #ff9f0a; }
        .request-card.incoming { border-left: 4px solid #ff9f0a; background: #fffef7; }
        .request-card.outgoing { border-left: 4px solid #0dcaf0; background: #f0f9ff; }
        
        .card-header-custom {
            padding: 16px 20px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        
        .card-body-custom { padding: 16px 20px; flex: 1; }
        .card-footer-custom {
			/* border-radius: 20px; */
			padding: 12px 20px;
			border-top: 1px solid #e9ecef;
			/* background: #f8f9fa; */
			font-size: 12px;
			color: #6c757d;
		}
        
        .host-name { font-weight: 600; font-size: 16px; margin-bottom: 4px; }
        .host-ip { font-family: monospace; font-size: 12px; color: #007aff; }
        .host-url {
            font-family: monospace;
            font-size: 11px;
            color: #6c757d;
            background: #f8f9fa;
            padding: 4px 8px;
            border-radius: 8px;
            word-break: break-all;
            margin-top: 8px;
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
        }
        
        .status-online { background: #34c75920; color: #248a3d; }
        .status-offline { background: #ff3b3020; color: #d70015; }
        .status-pending { background: #ff9f0a20; color: #c46e00; }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 20px;
        }
        
        .empty-state i { font-size: 48px; color: #c6c6c8; margin-bottom: 16px; }
        
        .btn-apple {
            background: #007aff;
            border: none;
            border-radius: 12px;
            padding: 10px 20px;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.2s ease;
            color: white;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-apple:hover { background: #005fc1; transform: scale(0.98); color: white; }
        .btn-apple-outline {
            background: transparent;
            border: 1.5px solid #007aff;
            color: #007aff;
            border-radius: 12px;
            padding: 9px 19px;
            font-weight: 500;
        }
        
        .modal-apple .modal-content { border-radius: 24px; border: none; }
        .form-control-apple, .form-select-apple {
            border-radius: 12px;
            border: 1px solid #e9ecef;
            padding: 10px 14px;
        }
        
        .form-control-apple:focus, .form-select-apple:focus {
            border-color: #007aff;
            box-shadow: 0 0 0 3px rgba(0,122,255,0.1);
        }
        
        .api-url-preview {
            background: #e8f0fe;
            padding: 12px;
            border-radius: 12px;
            margin-top: 16px;
        }
        
        .pin-container {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(0,0,0,0.05);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
        }
        
        .pin-value {
            font-family: monospace;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .pin-value.blurred { filter: blur(4px); }
        .pin-value.blurred:hover { filter: blur(0); }
        
        .btn-pin-refresh {
            background: none;
            border: none;
            color: #007aff;
            cursor: pointer;
            padding: 4px 6px;
            border-radius: 8px;
        }
        
        .btn-pin-refresh:hover { background: rgba(0,122,255,0.1); transform: rotate(180deg); }
        
        .dropdown-menu-actions {
            position: absolute;
            right: 0;
            top: 100%;
            background: white;
            border-radius: 12px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
            border: 1px solid #e9ecef;
            min-width: 160px;
            z-index: 100;
            display: none;
        }
        
        .dropdown-menu-actions.show { display: block; }
        .dropdown-menu-actions a {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            color: #1c1c1e;
            text-decoration: none;
            font-size: 13px;
            cursor: pointer;
        }
        
        .dropdown-menu-actions a:hover { background: #f8f9fa; }
        .card-actions-dropdown { position: relative; }
        
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
            z-index: 9999;
        }
		
		.host-type-badge {
			display: inline-flex;
			align-items: center;
			gap: 4px;
			padding: 2px 8px;
			border-radius: 12px;
			font-size: 10px;
			font-weight: 500;
			margin-left: 8px;
		}

		.host-type-master {
			background: #ffc10720;
			color: #b45f00;
		}

		.host-type-slave {
			background: #6c757d20;
			color: #495057;
		}
		
.master-host-card {
    border: 1px solid #ffc107;
    background: linear-gradient(135deg, #ffffff 0%, #fffef7 100%);
}

.master-host-card:hover {
    border-color: #ffc107;
    box-shadow: 0 8px 20px rgba(255, 193, 7, 0.15);
}

.host-card:not(.master-host-card):hover {
    border-color: #007aff40;
    box-shadow: 0 8px 20px rgba(0,122,255,0.1);
}

@media (max-width: 768px) {
    .section-header h3 {
        font-size: 1.2rem;
    }
}

.trap-toggle-container {
	display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(0, 0, 0, 0.05);
    padding: 6px 10px;
    border-radius: 20px;
}

.form-check-label {
	display: inline-flex;
    align-items: center;
	gap: 8px;
	padding: 6px 10px;
	font-size: 13px;
}

#hostInfoModal .modal-xl {
    max-width: 1200px;
}

#hostInfoModal .card {
    border-radius: 12px;
    border: 1px solid #e9ecef;
}

#hostInfoModal .card-header {
    background: #f8f9fa;
    border-bottom: 1px solid #e9ecef;
    padding: 12px 16px;
}

#hostInfoModal .table-sm td, 
#hostInfoModal .table-sm th {
    padding: 8px 0;
}

.user-select-all {
    user-select: all;
    -webkit-user-select: all;
}

.content-with-sidebar {
    display: flex;
    gap: 20px;
    min-height: calc(100vh - 120px);
}

.main-panel {
    flex: 1;
    min-width: 0; /* Prevents overflow */
}

.sidebar-tabs {
    width: 90px;
    flex-shrink: 0;
    position: sticky;
    top: 80px;
    height: fit-content;
}

.sidebar-tabs .nav-pills {
    background: white;
    border-radius: 20px;
    padding: 12px 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    border: 1px solid #e9ecef;
}

.sidebar-tabs .nav-link {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    padding: 12px 8px;
    margin-bottom: 8px;
    border-radius: 14px;
    color: #6c757d;
    transition: all 0.2s;
    gap: 6px;
}

.sidebar-tabs .nav-link i {
    font-size: 1.5rem;
}

.sidebar-tabs .nav-link .tab-label {
    font-size: 0.7rem;
    font-weight: 500;
}

.sidebar-tabs .nav-link .badge {
    font-size: 0.65rem;
    padding: 2px 6px;
    background: #e9ecef;
    color: #495057;
    margin-top: 2px;
}

.sidebar-tabs .nav-link:hover {
    background: #f8f9fa;
    color: #007aff;
}

.sidebar-tabs .nav-link.active {
    background: linear-gradient(135deg, #007aff, #005fc1);
    color: white;
}

.sidebar-tabs .nav-link.active .badge {
    background: rgba(255,255,255,0.3);
    color: white;
}

.sidebar-tabs .nav-link.active i {
    color: white;
}

@media (max-width: 768px) {
    .sidebar-tabs {
        width: 60px;
    }
    
    .sidebar-tabs .nav-link i {
        font-size: 1.2rem;
    }
    
    .sidebar-tabs .nav-link .tab-label {
        font-size: 0.6rem;
    }
}
    </style>
</head>
<body>

<div class="top-bar">
    <div class="top-bar-left">
        <h1><i class="fas fa-bucket"></i> Mini-B</h1>
    </div>
    <div class="top-bar-right">
	<text><i class="bi bi-server"></i> Host Manager</text>
	<div class="language-selector" style="margin-left: 15px;">
		</div>
		<div class="trap-toggle-container me-3">
			<div class="form-check form-switch">
				<input class="form-check-input" type="checkbox" id="trapToggle" style="cursor: pointer; width: 48px; height: 24px;">
				<label class="form-check-label ms-2" for="trapToggle" style="cursor: pointer;">
					<i class="bi bi-shield-check"></i>
					<span id="trapLabel"><?php echo $lang12; ?></span>
				</label>
			</div>
		</div>
		
		<div class="pin-container">
			<span class="pin-label"><i class="bi bi-shield-lock"></i> PIN:</span>
			<span class="pin-value blurred" data-pin="<?= htmlspecialchars($pin) ?>" id="pinValue"><?= $pinDisplay ?></span>
			<button class="btn-pin-refresh" onclick="regeneratePin()" title="Generate new PIN">
				<i class="bi bi-arrow-repeat"></i>
			</button>
		</div>
	<div class="btn-group">
            <button class="btn btn-sm btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-bars"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item text-warning" href="#" onclick="openAddServerModal()"><i class="bi bi-cloud-plus me-2"></i> <?php echo $lang288; ?></a></li>
                <li><a class="dropdown-item text-success" href="#" onclick="openHostModal()"><i class="bi bi-plus-lg me-2"></i> <?php echo $lang289; ?></a></li>
                <li><a class="dropdown-item text-success" href="#" onclick="showHostInfo('1')"><i class="bi bi-info-circle me-2"></i> <?php echo $lang290; ?></a></li>
				<li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-info" href="#" onclick="openSpeedTestModal()"><i class="bi bi-speedometer2 me-2"></i> <?php echo $lang291; ?></a></li>
				<li><a class="dropdown-item text-danger" href="minib_settings.php#apiceycontent" onclick=""><i class="bi bi-code me-2"></i> <?php echo $lang292; ?></a></li>
            </ul>
        </div>
	</div>
</div>

<div class="app-container">
    <?php echo $menu; ?>
    
    <main class="main-content">
        <div id="alertContainer"></div>
        
        <div class="content-with-sidebar">
            <!-- Основной контент -->
            <div class="main-panel">
                <div class="tab-content">
                    <!-- HOSTS TAB -->
                    <div class="tab-pane fade show active" id="hostsContent" role="tabpanel">
                        <div id="hostsContainer" class="row">
                            <div class="col-12 text-center py-5">
                                <div class="spinner-border text-primary"></div>
                                <p class="mt-2 text-muted"><?php echo $lang293; ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- INCOMING REQUESTS TAB -->
                    <div class="tab-pane fade" id="incomingContent" role="tabpanel">
                        <div id="incomingContainer" class="row">
                            <div class="col-12 text-center py-5">
                                <div class="spinner-border text-primary"></div>
                                <p class="mt-2 text-muted"><?php echo $lang294; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Сайдбар с вкладками справа -->
            <div class="sidebar-tabs">
                <div class="nav flex-column nav-pills" id="v-pills-tab" role="tablist" aria-orientation="vertical">
                    <button class="nav-link active" id="hosts-tab" data-bs-toggle="pill" data-bs-target="#hostsContent" type="button" role="tab">
                        <i class="bi bi-hdd-stack-fill"></i>
                        <span class="tab-label"><?php echo $lang295; ?></span>
                        <span id="hostsCount" class="badge">0</span>
                    </button>
                    <button class="nav-link" id="incoming-tab" data-bs-toggle="pill" data-bs-target="#incomingContent" type="button" role="tab">
                        <i class="bi bi-arrow-down-circle-fill"></i>
                        <span class="tab-label"><?php echo $lang296; ?></span>
                        <span id="incomingCount" class="badge">0</span>
                    </button>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Modal: Add/Edit Host -->
<div class="modal fade modal-apple" id="hostModal" tabindex="-1">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-semibold"><i class="bi bi-server me-2"></i><span id="modalTitle"><?php echo $lang297; ?></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="hostForm">
                    <input type="hidden" id="idHost" name="idHost">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold"><?php echo $lang298; ?> <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-apple" id="hostName" name="hostName" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold"><?php echo $lang299; ?> <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-apple" id="hostIp" name="hostIp" placeholder="192.168.1.100 or domain.com" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold"><?php echo $lang300; ?></label>
                            <select class="form-select form-select-apple" id="hostProto" name="hostProto">
								<option value="http">HTTP</option>
								<option value="https">HTTPS</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold"><?php echo $lang301; ?></label>
                            <input type="number" class="form-control form-control-apple" id="hostPort" name="hostPort" value="1488" placeholder="Optional" min="1" max="65535">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold"><?php echo $lang302; ?></label>
                            <input type="text" class="form-control form-control-apple" id="hostApiPath" name="hostApiPath" value="/api">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold"><?php echo $lang303; ?> <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-apple" id="hostApiKey" name="hostApiKey" required>
                        </div>
                        <div class="col-12">
							<label class="form-label fw-semibold"><?php echo $lang304; ?></label>
							<input type="text" class="form-control form-control-apple" id="hostSn" name="hostSn" readonly disabled style="background: #e9ecef;" placeholder="Auto-detected from agent request">
							<small class="text-muted"><?php echo $lang305; ?></small>
						</div>
                        <div class="col-12">
                            <label class="form-label fw-semibold"><?php echo $lang306; ?></label>
                            <textarea class="form-control form-control-apple" id="hostComment" name="hostComment" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="api-url-preview mt-3">
                        <small class="text-muted"><i class="bi bi-eye"></i> <?php echo $lang307; ?></small>
                        <code id="apiUrlPreview" class="small">http://example.com/api</code>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang95; ?></button>
                <button type="button" class="btn-apple" onclick="saveHost()"><i class="bi bi-save"></i> <?php echo $lang308; ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Test Result -->
<div class="modal fade modal-apple" id="testModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-wifi"></i> <?php echo $lang309; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="testResult"><?php echo $lang310; ?></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang90; ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Add Server (Outgoing Request) -->
<div class="modal fade modal-apple" id="addServerModal" tabindex="-1">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-cloud-plus me-2"></i><?php echo $lang311; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addServerForm">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold"><?php echo $lang312; ?> <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-apple" id="serverIp" placeholder="192.168.1.100" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold"><?php echo $lang313; ?> <span class="text-danger">*</span></label>
                            <input type="password" class="form-control form-control-apple" id="serverPin" maxlength="4" pattern="[0-9]{4}" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold"><?php echo $lang314; ?></label>
                            <select class="form-select form-select-apple" id="serverProto">
								<option value="http">HTTP</option>
								<option value="https">HTTPS</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold"><?php echo $lang315; ?></label>
                            <input type="number" class="form-control form-control-apple" id="serverPort" value="1488" placeholder="Optional">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold"><?php echo $lang316; ?></label>
                            <input type="text" class="form-control form-control-apple" id="serverApiPath" value="/api">
                        </div>
                    </div>
					<input type="hidden" id="slaveRealIp" value="<?php echo htmlspecialchars($slaveIp); ?>">
                </form>
				
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang95; ?></button>
                <button type="button" class="btn-apple" onclick="addRemoteServer()">
                    <i class="bi bi-cloud-arrow-up"></i> <?php echo $lang317; ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Speed Test -->
<div class="modal fade modal-apple" id="speedTestModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-speedometer2 me-2"></i><?php echo $lang318; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="speedTestSelection">
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold"><?php echo $lang319; ?></label>
                            <select class="form-select" id="speedSourceHost">
                                <option value=""><?php echo $lang320; ?></option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold"><?php echo $lang321; ?></label>
                            <select class="form-select" id="speedTargetHost">
                                <option value=""><?php echo $lang322; ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold"><?php echo $lang323; ?></label>
                            <select class="form-select" id="speedTestSize">
                                <option value="512">512 KB</option>
                                <option value="1024" selected>1 MB</option>
                                <option value="5120">5 MB</option>
                                <option value="10240">10 MB</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <div class="mt-4">
                                <button class="btn-apple w-100" onclick="startSpeedTest()">
                                    <i class="bi bi-play-fill"></i> <?php echo $lang324; ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div id="speedTestProgress" style="display: none;">
                    <div class="text-center mb-3">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-2"><?php echo $lang325; ?></p>
                    </div>
                    <div class="progress mb-3">
                        <div id="speedProgressBar" class="progress-bar progress-bar-striped progress-bar-animated" style="width: 0%">0%</div>
                    </div>
                </div>
                
                <div id="speedTestResults" style="display: none;">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <i class="bi bi-arrow-down-circle fs-1 text-success"></i>
                                    <h6 class="mt-2"><?php echo $lang326; ?></h6>
                                    <h3 id="downloadSpeed" class="mb-0">-- Mbps</h3>
                                    <small id="downloadSpeedMB" class="text-muted">-- MB/s</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <i class="bi bi-arrow-up-circle fs-1 text-primary"></i>
                                    <h6 class="mt-2"><?php echo $lang327; ?></h6>
                                    <h3 id="uploadSpeed" class="mb-0">-- Mbps</h3>
                                    <small id="uploadSpeedMB" class="text-muted">-- MB/s</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <i class="bi bi-clock-history fs-2 text-info"></i>
                                    <h6><?php echo $lang328; ?></h6>
                                    <h4 id="pingLatency" class="mb-0">-- ms</h4>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <i class="bi bi-graph-up fs-2 text-warning"></i>
                                    <h6><?php echo $lang329; ?></h6>
                                    <h4 id="jitterValue" class="mb-0">-- ms</h4>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <i class="bi bi-exclamation-triangle fs-2 text-danger"></i>
                                    <h6><?php echo $lang330; ?></h6>
                                    <h4 id="packetLoss" class="mb-0">-- %</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <canvas id="speedChart" style="max-height: 300px; width: 100%;"></canvas>
                    
                    <div class="mt-3 text-muted small text-center" id="testDetails"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang90; ?></button>
                <button type="button" class="btn-apple" onclick="resetSpeedTestModal()">
					<i class="bi bi-arrow-repeat"></i> <?php echo $lang331; ?>
				</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade modal-apple" id="hostInfoModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-semibold">
                    <i class="bi bi-info-circle-fill me-2"></i><?php echo $lang332; ?>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
                <div id="hostInfoContent">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary"></div>
                        <p class="mt-2"><?php echo $lang333; ?></p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle"></i> <?php echo $lang90; ?>
                </button>
                <button type="button" class="btn-apple" onclick="copyHostInfoToClipboard()">
                    <i class="bi bi-clipboard"></i> <?php echo $lang334; ?>
                </button>
            </div>
        </div>
    </div>
</div>

<script src="lib/jquery-3.6.0-master/dist/jquery.min.js"></script>
<script src="lib/bootstrap-5.3.8-dist/js/bootstrap.bundle.min.js"></script>
<script src="js/loader.js"></script>

<script>
const API_URL = '../api/host_manager_api.php';
let currentModal = null;

// ========== UTILITIES ==========

function showAlert(message, type = 'success') {
    const icons = { success: 'check-circle', danger: 'exclamation-triangle', warning: 'info-circle', info: 'info-circle' };
    const alertHtml = `<div class="alert alert-${type} alert-dismissible fade show mb-2" role="alert">
        <i class="bi bi-${icons[type]} me-2"></i> ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>`;
    $('#alertContainer').append(alertHtml);
    setTimeout(() => $('.alert').fadeOut(500, function() { $(this).remove(); }), 5000);
}

function escapeHtml(text) {
    if (text === null || text === undefined) return '';
    if (typeof text !== 'string') text = String(text);
    return text.replace(/[&<>]/g, function(m) {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>') return '&gt;';
        return m;
    });
}

function showLoading() {
    if (!$('.loading-overlay').length) {
        $('body').append('<div class="loading-overlay"><div class="spinner-border text-light" style="width: 3rem; height: 3rem;"></div></div>');
    }
    $('.loading-overlay').show();
}

function hideLoading() {
    $('.loading-overlay').hide();
}

function updateApiUrlPreview() {
    let proto = $('#hostProto').val();
    let ip = $('#hostIp').val();
    let port = $('#hostPort').val();
    let apiPath = $('#hostApiPath').val();
    if (!ip) { $('#apiUrlPreview').text('Enter IP first'); return; }
    let url = proto + '://' + ip;
    if (port) url += ':' + port;
    if (apiPath) url += (apiPath.startsWith('/') ? '' : '/') + apiPath;
    else url += '/api';
    $('#apiUrlPreview').text(url);
}

// ========== API CALLS ==========
async function apiCall(action, method = 'GET', data = null) {
    let url = `${API_URL}?action=${action}`;
    let options = { method: method, headers: {} };
    
    if (method === 'POST' && data) {
        options.headers['Content-Type'] = 'application/x-www-form-urlencoded';
        options.body = new URLSearchParams(data);
    } else if (method === 'GET' && data) {
        url += '&' + new URLSearchParams(data);
    }
    
    try {
        let response = await fetch(url, options);
        return await response.json();
    } catch(e) {
        console.error('API Error:', e);
        return { success: false, message: e.message };
    }
}

// ========== HOSTS ==========
async function loadHosts() {
    let result = await apiCall('get_hosts');
    if (result.success) {
        let hosts = result.data;
        
        let masterHosts = hosts.filter(h => h.hostType === 'master');
        let slaveHosts = hosts.filter(h => h.hostType !== 'master');
        
        $('#hostsCount').text(hosts.length);
        
        if (hosts.length === 0) {
            $('#hostsContainer').html(`<div class="col-12"><div class="empty-state"><i class="bi bi-server"></i><h5><?php echo $lang335; ?></h5><p class="text-muted"><?php echo $lang336; ?></p><button class="btn-apple" onclick="openHostModal()"><i class="bi bi-plus-lg"></i> <?php echo $lang337; ?></button></div></div>`);
        } else {
            let html = '';
            
            // MASTER HOSTS SECTION
            if (masterHosts.length > 0) {
                html += `<div class="col-12 mb-3 mt-2">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-star-fill text-warning fs-4 me-2"></i>
                        <h3 class="mb-0 fw-semibold" style="font-size: 1.5rem;"><?php echo $lang338; ?></h3>
                        <span class="badge bg-warning ms-3">${masterHosts.length}</span>
                    </div>
                    <hr class="mt-2 mb-4" style="border-top: 2px solid #ffc107;">
                </div>
                <div class="row g-4 mb-5">`;
                
                for (let host of masterHosts) {
                    html += renderHostCard(host);
                }
                
                html += `</div>`;
            }
            
            // SLAVE HOSTS SECTION
            if (slaveHosts.length > 0) {
                html += `<div class="col-12 mb-3 mt-4">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-hdd-stack-fill text-secondary fs-4 me-2"></i>
                        <h3 class="mb-0 fw-semibold" style="font-size: 1.5rem;"><?php echo $lang339; ?></h3>
                        <span class="badge bg-secondary ms-3">${slaveHosts.length}</span>
                    </div>
                    <hr class="mt-2 mb-4" style="border-top: 2px solid #6c757d;">
                </div>
                <div class="row g-4 mb-5">`;
                
                for (let host of slaveHosts) {
                    html += renderHostCard(host);
                }
                
                html += `</div>`;
            }
            
            $('#hostsContainer').html(html);
        }
    }
}


function renderHostCard(host) {
    let statusClass = host.hostLive === 'online' ? 'online' : (host.hostLive === 'offline' ? 'offline' : 'pending');
    let proto = host.hostProto || 'http';
    let port = host.hostPort ? ':' + host.hostPort : '';
    let apiPath = host.hostApiPath || '/api';
    let fullApiUrl = `${proto}://${host.hostIp}${port}${apiPath}`;
    
    let statusBadge = '';
    if (host.hostLive === 'online') {
        statusBadge = '<span class="status-badge status-online"><i class="bi bi-check-circle-fill"></i> <?php echo $lang340; ?></span>';
    } else if (host.hostLive === 'offline') {
        statusBadge = '<span class="status-badge status-offline"><i class="bi bi-x-circle-fill"></i> <?php echo $lang341; ?></span>';
    } else {
        statusBadge = '<span class="status-badge status-pending"><i class="bi bi-hourglass-split"></i> <?php echo $lang342; ?></span>';
    }
    
    let isCurrentHost = (host.idHost == 1);
    let isMasterHost = (host.hostType === 'master' && !isCurrentHost);
    
    let cardBorderClass = host.hostType === 'master' ? 'master-host-card' : '';
    
    let hostNameSafe = escapeHtml(host.hostName);
    let hostIpSafe = escapeHtml(host.hostIp);
    let hostCommentSafe = host.hostComment ? `<div class="host-comment mt-2 small text-muted">${escapeHtml(host.hostComment)}</div>` : '';
    let apiKeySafe = host.hostApiKey ? escapeHtml(host.hostApiKey.substring(0, 20)) : '';
    let hostSnSafe = host.hostSn ? `<div class="api-key-preview bg-light p-2 rounded small mb-2"><i class="bi bi-upc-scan"></i> <?php echo $lang343; ?>: <code>${escapeHtml(host.hostSn)}</code></div>` : '';
    let hostVersionSafe = (host.hostVersion && host.hostVersion !== 'unknown') ? `<div class="small text-muted"><i class="bi bi-tag"></i> <?php echo $lang344; ?>: ${escapeHtml(host.hostVersion)}</div>` : '';
    let hostDateSafe = host.hostDateApiUpdtae ? `<div class="small text-muted"><i class="bi bi-clock"></i> <?php echo $lang345; ?>: ${escapeHtml(host.hostDateApiUpdtae)}</div>` : '';
    
    let dropdownItems = `
        <a onclick="testConnection('${host.idHost}')"><i class="bi bi-wifi"></i> <?php echo $lang346; ?></a>
        <a onclick="testHostApiOnly('${host.idHost}')"><i class="bi bi-cloud-check"></i> <?php echo $lang347; ?></a>
        <a onclick="quickSpeedTest('${host.idHost}')"><i class="bi bi-lightning-charge"></i> <?php echo $lang348; ?></a>
        <a onclick="getSnForHost('${host.idHost}')"><i class="bi bi-upc-scan"></i> <?php echo $lang349; ?></a>
    `;
    
    if (host.hostType !== 'master' || isCurrentHost) {
        dropdownItems += `<a onclick="rotateHostKey('${host.idHost}')" style="color:#ff9f0a;"><i class="bi bi-arrow-repeat"></i> <?php echo $lang350; ?></a>`;
    }
    
    dropdownItems += `<a onclick="showHostInfo('${host.idHost}')"><i class="bi bi-info-circle"></i> <?php echo $lang351; ?></a>
        <a onclick="editHost('${host.idHost}')"><i class="bi bi-pencil"></i> <?php echo $lang352; ?></a>`;
    
    if (!isCurrentHost) {
        dropdownItems += `<div class="dropdown-divider"></div>
        <a onclick="deleteHost('${host.idHost}')" style="color: #ff3b30;"><i class="bi bi-trash3"></i> <?php echo $lang353; ?></a>`;
    }
    
    let thisHostBadge = isCurrentHost ? '<span class="badge bg-primary ms-2"><i class="bi bi-check-circle-fill"></i> <?php echo $lang354; ?></span>' : '';
    
    return `<div class="col-md-6 col-lg-4" data-host-id="${host.idHost}">
        <div class="host-card ${statusClass} ${cardBorderClass}">
            <div class="card-header-custom">
                <div>
                    <div class="host-name">${statusBadge}</div>
                    <div class="fw-semibold fs-6 mt-1">
                        ${host.hostType === 'master' ? 
                            '<i class="bi bi-star-fill text-warning me-1" title="Master"></i>' : 
                            '<i class="bi bi-hdd-stack me-1 text-secondary" title="Slave"></i>'}
                        ${hostNameSafe}${thisHostBadge}
                    </div>
                    <div class="host-ip"><i class="bi bi-hdd-stack"></i> ${hostIpSafe}${host.hostPort ? ':' + host.hostPort : ''}</div>
                    <div class="host-url"><i class="bi bi-link-45deg"></i> ${escapeHtml(fullApiUrl)}</div>
                    ${hostCommentSafe}
                </div>
                <div class="card-actions-dropdown">
                    <button class="btn btn-sm btn-link text-secondary" onclick="toggleDropdown(this)"><i class="bi bi-three-dots-vertical"></i></button>
                    <div class="dropdown-menu-actions">
                        ${dropdownItems}
                    </div>
                </div>
            </div>
            <div class="card-body-custom">
                <div class="api-key-preview bg-light p-2 rounded small mb-2"><i class="bi bi-key"></i> <?php echo $lang355; ?>: <code>${apiKeySafe}…</code></div>
                ${hostSnSafe}
                ${hostVersionSafe}
                ${hostDateSafe}
            </div>
            <div class="card-footer-custom">
                <i class="bi bi-calendar-plus"></i> <?php echo $lang356; ?>: ${host.idHost} | 
                <span class="${host.hostType === 'master' ? 'text-warning' : 'text-secondary'}">
                    <i class="bi ${host.hostType === 'master' ? 'bi-star-fill' : 'bi-hdd-stack'}"></i>
                    ${host.hostType === 'master' ? 'Master' : 'Slave'}
                </span>
            </div>
        </div>
    </div>`;
}

async function editHost(idHost) {
    showLoading();
    let result = await apiCall('get_host', 'GET', { idHost: idHost });
    hideLoading();
    if (result.success && result.data) {
        let h = result.data;
        $('#modalTitle').text('Edit Host');
        $('#idHost').val(h.idHost);
        $('#hostName').val(h.hostName);
        $('#hostIp').val(h.hostIp);
        $('#hostProto').val(h.hostProto || 'http');
        $('#hostPort').val(h.hostPort || '');
        $('#hostApiPath').val(h.hostApiPath || '/api');
        $('#hostApiKey').val(h.hostApiKey);
        $('#hostSn').val(h.hostSn || '').prop('disabled', true);
        $('#hostComment').val(h.hostComment || '');
        updateApiUrlPreview();
        currentModal = new bootstrap.Modal(document.getElementById('hostModal'));
        currentModal.show();
    } else {
        showAlert('<?php echo $lang357; ?>', 'danger');
    }
}

async function saveHost(forceSave = false) {
    let idHost = $('#idHost').val();
    let formData = $('#hostForm').serialize();
    
    if (idHost && idHost !== '') {
        formData = formData.replace(/&hostSn=[^&]*/g, '');
        formData = formData.replace(/^hostSn=[^&]*&?/, '');
    }
    
    if (forceSave) {
        formData += '&skip_api_test=1';
    }
    
    showLoading();
    let result = await apiCall('save_host', 'POST', formData);
    hideLoading();
    
    if (result.success) {
        if (currentModal) currentModal.hide();
        showAlert(result.message, result.warning ? 'warning' : 'success');
        
        await loadHosts();
        
        let newIdHost = result.idHost || idHost;
        if (newIdHost && !result.warning && !idHost) {
            setTimeout(async () => {
                if (confirm('<?php echo $lang358; ?>')) {
                    await testConnection(newIdHost);
                }
            }, 500);
        }
        
        $('#hostForm')[0].reset();
        $('#idHost').val('');
        $('#hostSn').val('').prop('disabled', true);
    } else if (result.api_test && !result.success && result.can_force) {
        let forceSaveConfirm = confirm(
            `⚠️ <?php echo $lang359; ?>\n\n` +
            `${result.message}\n\n` +
            `<?php echo $lang360; ?>`
        );
        if (forceSaveConfirm) {
            await saveHost(true);
        }
    } else {
        showAlert(result.message, 'danger');
    }
}

async function deleteHost(idHost) {
    if (!confirm('<?php echo $lang361; ?>')) return;
    showLoading();
    let result = await apiCall('delete_host', 'GET', { idHost: idHost });
    hideLoading();
    if (result.success) {
        showAlert(result.message, 'success');
        loadHosts();
    } else {
        showAlert(result.message, 'danger');
    }
}

async function testConnection(idHost) {
    showLoading();
    $('#testResult').html('<div class="text-center"><div class="spinner-border text-primary"></div><br><?php echo $lang362; ?></div>');
    let testModal = new bootstrap.Modal(document.getElementById('testModal'));
    testModal.show();
    
    let result = await apiCall('test_host', 'GET', { idHost: idHost });
    hideLoading();
    
    let statusIcon = '';
    let statusColor = '';
    let statusTitle = '';
    
    if (result.success) {
        statusIcon = 'check-circle-fill';
        statusColor = '#34c759';
        statusTitle = '<?php echo $lang363; ?>';
    } else if (result.details && result.details.ping && result.details.ping.success) {
        statusIcon = 'exclamation-triangle-fill';
        statusColor = '#ff9f0a';
        statusTitle = '<?php echo $lang364; ?>';
    } else {
        statusIcon = 'x-circle-fill';
        statusColor = '#ff3b30';
        statusTitle = '<?php echo $lang365; ?>';
    }
    
    let pingHtml = '';
    if (result.details && result.details.ping) {
        pingHtml = `<div class="mt-3 p-3 ${result.details.ping.success ? 'bg-success bg-opacity-10' : 'bg-danger bg-opacity-10'} rounded">
            <strong><i class="bi bi-wifi"></i> <?php echo $lang366; ?>:</strong><br>
            <small>${escapeHtml(result.details.ping.message)}</small>
        </div>`;
    }
    
    let apiHtml = '';
    if (result.details && result.details.api) {
        apiHtml = `<div class="mt-3 p-3 ${result.details.api.success ? 'bg-success bg-opacity-10' : 'bg-warning bg-opacity-10'} rounded">
            <strong><i class="bi bi-cloud-check"></i> <?php echo $lang367; ?>:</strong><br>
            <small>${escapeHtml(result.details.api.message)}</small>
            ${result.details.api.endpoint ? `<br><small><strong><?php echo $lang368; ?>:</strong> ${escapeHtml(result.details.api.endpoint)}</small>` : ''}
        </div>`;
    }
    
    $('#testResult').html(`<div class="text-center">
        <i class="bi bi-${statusIcon}" style="font-size: 48px; color: ${statusColor};"></i>
        <h4 class="mt-3">${statusTitle}</h4>
        <p class="text-muted">${escapeHtml(result.message)}</p>
        ${pingHtml}
        ${apiHtml}
    </div>`);
    
    loadHosts();
}

async function getRemoteHostSn(idHost) {
    let hostResult = await apiCall('get_host', 'GET', { idHost: idHost });
    if (!hostResult.success || !hostResult.data) {
        return { success: false, message: '<?php echo $lang369; ?>' };
    }
    
    let host = hostResult.data;
    let proto = host.hostProto || 'http';
    let ip = host.hostIp;
    let port = host.hostPort ? ':' + host.hostPort : '';
    let apiPath = host.hostApiPath || '/api';
    let apiKey = host.hostApiKey;
    
    let url = `${proto}://${ip}${port}${apiPath}/who.php?action=who`;
    
    try {
        let response = await fetch(url, {
            method: 'GET',
            headers: {
                'X-API-Key': apiKey,
                'Content-Type': 'application/json'
            }
        });
        
        let data = await response.json();
        
        if (data.success && data.serial_number) {
            let formData = new FormData();
            formData.append('idHost', idHost);
            formData.append('sn', data.serial_number);
            formData.append('name', data.name);
            formData.append('version', data.version);
            
            let saveResult = await apiCall('save_host_sn', 'POST', formData);
            
            if (saveResult.success) {
                return { 
                    success: true, 
                    sn: data.serial_number, 
                    name: data.name,
                    version: data.version,
                    message: '<?php echo $lang370; ?>' 
                };
            } else {
                return { success: false, message: '<?php echo $lang371; ?>: ' + (saveResult.message || 'Unknown error') };
            }
        } else {
            return { success: false, message: data.message || '<?php echo $lang372; ?>' };
        }
    } catch(e) {
        console.error('Error getting remote host info:', e);
        return { success: false, message: '<?php echo $lang373; ?>: ' + e.message };
    }
}

function openHostModal() {
    $('#hostForm')[0].reset();
    $('#idHost').val('');
    $('#modalTitle').text('<?php echo $lang374; ?>');
    $('#hostProto').val('http');
    $('#hostApiPath').val('/api');
    $('#hostSn').val('');
    updateApiUrlPreview();
    currentModal = new bootstrap.Modal(document.getElementById('hostModal'));
    currentModal.show();
}


let refreshInterval = null;

// Функция для проверки статуса исходящих запросов
async function checkOutgoingRequestStatus(arId) {
    let result = await apiCall('check_outgoing_request', 'GET', { arId: arId });
    if (result.success && !result.exists) {
        showAlert('<?php echo $lang375; ?>', 'info');
        await loadOutgoingRequests();
        await loadHosts();
        return false;
    }
    return true;
}

// ========== INCOMING REQUESTS ==========
async function loadIncomingRequests() {
    let result = await apiCall('get_incoming_requests');
    if (result.success) {
        let requests = result.data;
        $('#incomingCount').text(requests.length);
        if (requests.length === 0) {
            $('#incomingContainer').html(`<div class="col-12"><div class="empty-state"><i class="bi bi-inbox"></i><h5><?php echo $lang376; ?></h5><p class="text-muted"><?php echo $lang377; ?></p></div></div>`);
        } else {
            let html = '';
            requests.forEach(req => {
                html += `<div class="col-md-6 col-lg-4">
                    <div class="request-card incoming">
                        <div class="card-header-custom">
                            <div>
                                <div class="host-name"><span class="status-badge" style="background:#ff9f0a20;color:#c46e00;"><i class="bi bi-arrow-down-circle"></i> <?php echo $lang378; ?></span></div>
                                <div class="fw-semibold fs-6 mt-1"><i class="bi bi-display"></i> ${escapeHtml(req.arName || 'Unknown')}</div>
                                <div class="host-ip mt-2"><i class="bi bi-hdd-stack"></i> IP: ${escapeHtml(req.arIp)}${req.arPort ? ':' + req.arPort : ''}</div>
                                ${req.arPath ? `<div class="host-url mt-1"><i class="bi bi-link-45deg"></i> <?php echo $lang379; ?>: ${escapeHtml(req.arPath)}</div>` : ''}
                            </div>
                            <div class="card-actions-dropdown">
                                <button class="btn btn-sm btn-link text-secondary" onclick="toggleDropdown(this)"><i class="bi bi-three-dots-vertical"></i></button>
								<div class="dropdown-menu-actions">
									<a onclick="acceptIncomingRequest('${req.arId}')" style="color:#28a745;"><i class="bi bi-check-circle"></i> <?php echo $lang380; ?></a>
									<a onclick="rejectIncomingRequest('${req.arId}')" style="color:#ff3b30;"><i class="bi bi-x-circle"></i> <?php echo $lang381; ?></a>
									<div class="dropdown-divider"></div>
									<a onclick="deleteIncomingRequest('${req.arId}')" style="color:#6c757d;"><i class="bi bi-trash3"></i> <?php echo $lang382; ?></a>
								</div>
                            </div>
                        </div>
                        <div class="card-body-custom">
                            <div class="api-key-preview bg-light p-2 rounded small mb-2"><i class="bi bi-key"></i> <?php echo $lang383; ?>: <code>${escapeHtml(req.arApiKey?.substring(0, 20))}…</code></div>
                            ${req.arHostSn ? `<div class="api-key-preview bg-light p-2 rounded small mb-2"><i class="bi bi-upc-scan"></i> <?php echo $lang384; ?>: <code>${escapeHtml(req.arHostSn)}</code></div>` : ''}
                            ${req.arVersion ? `<div class="small text-muted"><i class="bi bi-tag"></i> <?php echo $lang385; ?>: ${escapeHtml(req.arVersion)}</div>` : ''}
                            ${req.arType ? `<div class="small text-muted"><i class="bi bi-diagram-3"></i> <?php echo $lang386; ?>: ${escapeHtml(req.arType)}</div>` : ''}
                            <div class="small text-muted"><i class="bi bi-calendar3"></i> <?php echo $lang387; ?>: ${escapeHtml(req.arDate)}</div>
                        </div>
                        <div class="card-footer-custom"><i class="bi bi-hash"></i> <?php echo $lang388; ?>: ${req.arId}</div>
                    </div>
                </div>`;
            });
            $('#incomingContainer').html(html);
        }
    }
}

async function joinRequest(arId) {
    if (!confirm('<?php echo $lang389; ?>')) return;
    showLoading();
    let result = await apiCall('join_request', 'GET', { arId: arId });
    hideLoading();
    if (result.success) {
        showAlert(result.message, 'success');
        loadIncomingRequests();
        loadHosts();
    } else {
        showAlert(result.message, 'danger');
    }
}

async function deleteIncomingRequest(arId) {
    if (!confirm('<?php echo $lang390; ?>')) return;
    showLoading();
    let result = await apiCall('delete_incoming_request', 'GET', { arId: arId });
    hideLoading();
    if (result.success) {
        showAlert(result.message, 'success');
        loadIncomingRequests();
    } else {
        showAlert(result.message, 'danger');
    }
}

function toggleSection(sectionId) {
    let section = document.getElementById(sectionId);
    let icon = document.getElementById(sectionId + 'Icon');
    
    if (section.style.display === 'none') {
        section.style.display = 'flex';
        icon.classList.remove('bi-chevron-right');
        icon.classList.add('bi-chevron-down');
    } else {
        section.style.display = 'none';
        icon.classList.remove('bi-chevron-down');
        icon.classList.add('bi-chevron-right');
    }
}

// ========== ADD REMOTE SERVER ==========
function openAddServerModal() {
    $('#addServerForm')[0].reset();
    $('#serverProto').val('http');
    $('#serverApiPath').val('/api');
    $('#addServerModal').modal('show');
}

let isAddingServer = false;

async function addRemoteServer() {
    if (isAddingServer) {
        showAlert('<?php echo $lang391; ?>', 'warning');
        return;
    }
    
    let serverIp = $('#serverIp').val().trim();
    let serverPin = $('#serverPin').val().trim();
    let serverProto = $('#serverProto').val();
    let serverPort = $('#serverPort').val().trim();
    let serverApiPath = $('#serverApiPath').val().trim();
	let localIp = $('#slaveRealIp').val();
    
    if (!serverIp) { showAlert('<?php echo $lang392; ?>', 'danger'); return; }
    if (!serverPin || serverPin.length !== 4) { showAlert('<?php echo $lang393; ?>', 'danger'); return; }
    if (!serverApiPath) serverApiPath = '/api';
    if (!serverApiPath.startsWith('/')) serverApiPath = '/' + serverApiPath;
    
    isAddingServer = true;
    showLoading();
    
    let localHostname = "<?php echo gethostname(); ?>";
    let localVersion = "<?php echo isset($version) ? $version : 'None'; ?>";
    
    let apiKeyResult = await apiCall('get_host_api_key');
    let localApiKey = '';
    if (apiKeyResult.success && apiKeyResult.api_key) {
        localApiKey = apiKeyResult.api_key;
    } else {
        hideLoading();
        isAddingServer = false;
        showAlert('<?php echo $lang394; ?>', 'danger');
        return;
    }
    
    let hostsResult = await apiCall('get_hosts');
    let localSn = '';
    if (hostsResult.success && hostsResult.data.length > 0) {
        let localHost = hostsResult.data.find(h => h.idHost == 1);
        if (localHost && localHost.hostSn) {
            localSn = localHost.hostSn;
        }
    }
    
    if (!localSn) {
        localSn = localHostname + '_' + Date.now();
    }
    
    let outgoingResult = await apiCall('get_outgoing_requests');
    if (outgoingResult.success) {
        let existingRequest = outgoingResult.data.find(req => req.arIp === serverIp);
        if (existingRequest) {
            hideLoading();
            isAddingServer = false;
            showAlert(`<?php echo $lang395; ?> ${serverIp} <?php echo $lang396; ?>`, 'warning');
            return;
        }
    }
    
    let createResult = await apiCall('create_outgoing_request', 'GET', {
        server_ip: serverIp,
        server_pin: serverPin,
        server_proto: serverProto,
        server_port: serverPort,
        server_api_path: serverApiPath,
        target_hostname: localHostname,
        target_version: localVersion,
        target_api_key: localApiKey,
        target_host_sn: localSn
    });
    
    if (!createResult.success) {
        hideLoading();
        isAddingServer = false;
        showAlert('<?php echo $lang397; ?>: ' + (createResult.message || 'Unknown error'), 'danger');
        return;
    }
    
    let url = serverProto + '://' + serverIp;
    if (serverPort) url += ':' + serverPort;
    url += serverApiPath + '/connector_trap.php';
    
    let postData = {
        version: localVersion,
        name: localHostname,
        type: 'slave',
        api_key: localApiKey,
        path: serverApiPath,
        host_sn: localSn,
		my_real_ip: localIp
    };
    
    try {
        let response = await fetch(url, {
            method: 'POST',
            headers: {
                'X-PIN': serverPin,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(postData)
        });
        
        let data = await response.json();
        hideLoading();
        isAddingServer = false;
        
        if (data.success) {
            showAlert('✅ <?php echo $lang398; ?>', 'success');
            $('#addServerModal').modal('hide');
            $('#addServerForm')[0].reset();
            await loadOutgoingRequests();
            
            let requestId = createResult.request_id;
            startStatusCheck(requestId);
        } else {
            showAlert('❌ <?php echo $lang399; ?>: ' + (data.message || 'Unknown error'), 'danger');
            await loadOutgoingRequests();
        }
    } catch(e) {
        hideLoading();
        isAddingServer = false;
        console.error('Connection error:', e);
        showAlert('⚠️ <?php echo $lang400; ?>', 'warning');
        $('#addServerModal').modal('hide');
        $('#addServerForm')[0].reset();
        await loadOutgoingRequests();
    }
}
 
async function showHostInfo(idHost) {
    showLoading();
    let result = await apiCall('get_host_info', 'GET', { idHost: idHost });
    hideLoading();
    
    if (result.success && result.data) {
        let host = result.data;
        
        let infoHtml = `
            <div class="row">
                <div class="col-md-6">
                    <div class="card mb-3">
                        <div class="card-header bg-light fw-semibold">
                            <i class="bi bi-hdd-stack-fill me-2"></i><?php echo $lang401; ?>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <th style="width: 35%;"><?php echo $lang402; ?>:</th>
                                    <td><code>${escapeHtml(host.idHost ?? 'N/A')}</code></td>
                                </tr>
                                <tr>
                                    <th><?php echo $lang403; ?>:</th>
                                    <td><strong>${escapeHtml(host.hostName ?? 'N/A')}</strong></td>
                                </tr>
                                <tr>
                                    <th><?php echo $lang404; ?>:</th>
                                    <td>
                                        <span class="badge ${(host.hostType ?? 'slave') === 'master' ? 'bg-warning' : 'bg-secondary'} fs-6 px-3 py-2">
                                            <i class="bi ${(host.hostType ?? 'slave') === 'master' ? 'bi-star-fill' : 'bi-hdd-stack'} me-1"></i>
                                            ${escapeHtml(host.hostType ?? 'slave').toUpperCase()}
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php echo $lang405; ?>:</th>
                                    <td>
                                        <span class="badge ${(host.hostLive ?? 'unknown') === 'online' ? 'bg-success' : ((host.hostLive ?? 'unknown') === 'offline' ? 'bg-danger' : 'bg-secondary')} fs-6 px-3 py-2">
                                            <i class="bi ${(host.hostLive ?? 'unknown') === 'online' ? 'bi-check-circle-fill' : ((host.hostLive ?? 'unknown') === 'offline' ? 'bi-x-circle-fill' : 'bi-hourglass-split')} me-1"></i>
                                            ${escapeHtml(host.hostLive ?? 'unknown').toUpperCase()}
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php echo $lang406; ?>:</th>
                                    <td><code>${escapeHtml(host.hostVersion ?? 'unknown')}</code></td>
                                </tr>
                                <tr>
                                    <th><?php echo $lang407; ?>:</th>
                                    <td><code class="user-select-all">${escapeHtml(host.hostSn ?? 'Not set')}</code></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card mb-3">
                        <div class="card-header bg-light fw-semibold">
                            <i class="bi bi-network-chart me-2"></i><?php echo $lang408; ?>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <th style="width: 35%;"><?php echo $lang409; ?>:</th>
                                    <td><code class="user-select-all">${escapeHtml(host.hostIp ?? 'N/A')}</code></td>
                                </tr>
                                <tr>
                                    <th><?php echo $lang410; ?>:</th>
                                    <td><span class="badge bg-info">${escapeHtml(host.hostProto ?? 'http')}</span></td>
                                </tr>
                                <tr>
                                    <th><?php echo $lang411; ?>:</th>
                                    <td>${escapeHtml(host.hostPort ?? 'Default (80/443)')}</td>
                                </tr>
                                <tr>
                                    <th><?php echo $lang412; ?>:</th>
                                    <td><code class="user-select-all">${escapeHtml(host.hostApiPath ?? '/api')}</code></td>
                                </tr>
                                <tr>
                                    <th><?php echo $lang413; ?>:</th>
                                    <td>
                                        <code class="user-select-all small" style="word-break: break-all;">
                                            ${escapeHtml(host.hostProto ?? 'http')}://${escapeHtml(host.hostIp ?? '')}${host.hostPort ? ':' + host.hostPort : ''}${escapeHtml(host.hostApiPath ?? '/api')}
                                        </code>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-12">
                    <div class="card mb-3">
                        <div class="card-header bg-light fw-semibold">
                            <i class="bi bi-key-fill me-2"></i><?php echo $lang414; ?>
                        </div>
                        <div class="card-body">
                            <div class="input-group">
                                <input type="password" class="form-control font-monospace small" id="apiKeyInput" value="${escapeHtml(host.hostApiKey ?? 'Not set')}" readonly style="background: #f8f9fa;">
                                <button class="btn btn-outline-secondary" type="button" onclick="toggleApiKeyVisibility()">
                                    <i class="bi bi-eye" id="apiKeyEyeIcon"></i>
                                </button>
                                <button class="btn btn-outline-primary" type="button" onclick="copyToClipboard('${escapeHtml(host.hostApiKey ?? '')}')">
                                    <i class="bi bi-clipboard"></i>
                                </button>
                            </div>
                            <small class="text-muted mt-2 d-block"><?php echo $lang415; ?>: ${(host.hostApiKey ?? '').length} characters</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="card mb-3">
                        <div class="card-header bg-light fw-semibold">
                            <i class="bi bi-calendar3 me-2"></i><?php echo $lang416; ?>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <th style="width: 40%;"><?php echo $lang417; ?>:</th>
                                    <td><code>${escapeHtml(host.hostDateApiUpdtae ?? 'Never')}</code></td>
                                </tr>
                                <tr>
                                    <th><?php echo $lang418; ?>:</th>
                                    <td><code>${escapeHtml(host.hostAddedData ?? '-')}</code></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card mb-3">
                        <div class="card-header bg-light fw-semibold">
                            <i class="bi bi-chat-text me-2"></i><?php echo $lang419; ?>
                        </div>
                        <div class="card-body">
                            <div class="p-2 bg-light rounded" style="min-height: 80px;">
                                ${escapeHtml(host.hostComment ?? 'No comment')}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        $('#hostInfoContent').html(infoHtml);
        let infoModal = new bootstrap.Modal(document.getElementById('hostInfoModal'));
        infoModal.show();
    } else {
        showAlert(result.message || '<?php echo $lang420; ?>', 'danger');
    }
}

function copyToClipboard(text) {
    if (!text) {
        showAlert('<?php echo $lang421; ?>', 'warning');
        return;
    }
    
    navigator.clipboard.writeText(text).then(() => {
        showAlert('<?php echo $lang422; ?>', 'success');
    }).catch(() => {
        let textarea = document.createElement('textarea');
        textarea.value = text;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        showAlert('<?php echo $lang423; ?>', 'success');
    });
}

// Функция для копирования всей информации о хосте
function copyHostInfoToClipboard() {
    let content = document.getElementById('hostInfoContent');
    let text = content.innerText;
    copyToClipboard(text);
}

// Функция для показа/скрытия API ключа
function toggleApiKeyVisibility() {
    let input = document.getElementById('apiKeyInput');
    let icon = document.getElementById('apiKeyEyeIcon');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('bi-eye');
        icon.classList.add('bi-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('bi-eye-slash');
        icon.classList.add('bi-eye');
    }
}

async function testHostApiOnly(idHost) {
    showLoading();
    let result = await apiCall('test_host_api', 'GET', { idHost: idHost });
    hideLoading();
    
    let modalHtml = '';
    if (result.success) {
        modalHtml = `<div class="text-center">
            <i class="bi bi-check-circle-fill" style="font-size: 48px; color: #34c759;"></i>
            <h4 class="mt-3"><?php echo $lang424; ?></h4>
            <p class="text-muted">${escapeHtml(result.message)}</p>
            <div class="mt-3 p-3 bg-light rounded text-start">
                <small><strong><?php echo $lang425; ?>:</strong> ${escapeHtml(result.endpoint)}</small><br>
                <small><strong><?php echo $lang426; ?>:</strong> ${result.http_code}</small>
                ${result.response ? `<br><small><strong><?php echo $lang427; ?>:</strong> <pre class="small mt-2">${JSON.stringify(result.response, null, 2)}</pre></small>` : ''}
            </div>
        </div>`;
    } else {
        modalHtml = `<div class="text-center">
            <i class="bi bi-x-circle-fill" style="font-size: 48px; color: #ff3b30;"></i>
            <h4 class="mt-3"><?php echo $lang428; ?></h4>
            <p class="text-muted">${escapeHtml(result.message)}</p>
            ${result.details ? `<div class="mt-3 p-3 bg-light rounded text-start">
                <small><?php echo $lang429; ?>:</small>
                <pre class="small mt-2">${JSON.stringify(result.details, null, 2)}</pre>
            </div>` : ''}
        </div>`;
    }
    
    $('#testResult').html(modalHtml);
    let testModal = new bootstrap.Modal(document.getElementById('testModal'));
    testModal.show();
}

// Глобальные переменные для графика
let speedChart = null;
let chartData = {
    download: [],
    upload: [],
    timestamps: []
};
let currentSpeedTestInterval = null;

// Загрузка списка хостов для выбора
async function loadHostsForSpeedTest() {
    let result = await apiCall('get_hosts');
    if (result.success) {
        let hosts = result.data;
        let sourceSelect = $('#speedSourceHost');
        let targetSelect = $('#speedTargetHost');
        
        sourceSelect.empty().append('<option value=""><?php echo $lang430; ?></option>');
        targetSelect.empty().append('<option value=""><?php echo $lang431; ?></option>');
        
        hosts.forEach(host => {
            let option = `<option value="${host.idHost}">${escapeHtml(host.hostName)} (${host.hostIp}) - ${host.hostType === 'master' ? 'Master' : 'Slave'}</option>`;
            sourceSelect.append(option);
            targetSelect.append(option);
        });
    }
}

// Открытие модального окна теста скорости
function openSpeedTestModal() {
    loadHostsForSpeedTest();
    $('#speedTestSelection').show();
    $('#speedTestProgress').hide();
    $('#speedTestResults').hide();
    let modal = new bootstrap.Modal(document.getElementById('speedTestModal'));
    modal.show();
}

async function quickSpeedTest(hostId) {
    openSpeedTestModal();
    
    let checkCount = 0;
    let checkInterval = setInterval(async () => {
        let sourceSelect = $('#speedSourceHost');
        let targetSelect = $('#speedTargetHost');
        
        if (sourceSelect.find('option').length > 1 && targetSelect.find('option').length > 1) {
            clearInterval(checkInterval);
            
            let hostsResult = await apiCall('get_hosts');
            if (hostsResult.success) {
                let masterHost = hostsResult.data.find(h => h.idHost == 1);
                if (masterHost) {
                    $('#speedSourceHost').val(masterHost.idHost);
                } else {
                    $('#speedSourceHost').val('1');
                }
            } else {
                $('#speedSourceHost').val('1');
            }
            
            $('#speedTargetHost').val(hostId);
            $('#speedTestSize').val('1024');
            
            startSpeedTest();
        }
        
        checkCount++;
        if (checkCount > 30) { // 30 попыток * 200ms = 6 секунд максимум
            clearInterval(checkInterval);
            showAlert('<?php echo $lang432; ?>', 'warning');
        }
    }, 200);
}

function resetSpeedTestModal() {
    resetSpeedChart();
    
    $('#downloadSpeed').text('--');
    $('#uploadSpeed').text('--');
    $('#downloadSpeedMB').text('-- MB/s');
    $('#uploadSpeedMB').text('-- MB/s');
    $('#pingLatency').text('--');
    $('#jitterValue').text('--');
    $('#packetLoss').text('--');
    $('#testDetails').html('');
    
    $('#speedTestSelection').show();
    $('#speedTestProgress').hide();
    $('#speedTestResults').hide();
    
    $('#speedSourceHost').val('');
    $('#speedTargetHost').val('');
    $('#speedTestSize').val('1024');
    
    loadHostsForSpeedTest();
    
    if (currentSpeedTestInterval) {
        clearInterval(currentSpeedTestInterval);
        currentSpeedTestInterval = null;
    }
}

async function startSpeedTest() {
    let sourceId = $('#speedSourceHost').val();
    let targetId = $('#speedTargetHost').val();
    let testSize = $('#speedTestSize').val();
    
    if (!sourceId || !targetId) {
        showAlert('<?php echo $lang433; ?>', 'danger');
        return;    }
    
    if (sourceId === targetId) {
        showAlert('<?php echo $lang434; ?>', 'danger');
        return;
    }
    
    $('#speedTestSelection').hide();
    $('#speedTestProgress').show();
    $('#speedTestResults').hide();
    
    initSpeedChart();
    
    let progress = 0;
    let progressInterval = setInterval(() => {
        progress += 2;
        if (progress <= 90) {
            $('#speedProgressBar').css('width', progress + '%').text(progress + '%');
        }
    }, 100);
    
    if (currentSpeedTestInterval) {
        clearInterval(currentSpeedTestInterval);
    }
    
    let testStartTime = Date.now();
    let lastUpdateTime = testStartTime;
    
    currentSpeedTestInterval = setInterval(() => {
        let currentTime = Date.now();
        let elapsed = (currentTime - testStartTime) / 1000;
        
        let simulatedDownload = Math.random() * 50 + 10; // 10-60 Mbps
        let simulatedUpload = Math.random() * 30 + 5; // 5-35 Mbps
        
        updateSpeedChart(simulatedDownload, simulatedUpload, elapsed);
    }, 500);
    
    let result = await apiCall('speed_test', 'GET', {
        source_id: sourceId,
        target_id: targetId,
        test_size: testSize
    });
    
    if (currentSpeedTestInterval) {
        clearInterval(currentSpeedTestInterval);
        currentSpeedTestInterval = null;
    }
    
    clearInterval(progressInterval);
    $('#speedProgressBar').css('width', '100%').text('100%');
    
    if (result.success) {
        setTimeout(() => {
            $('#speedTestProgress').hide();
            displaySpeedResults(result);
            $('#speedTestResults').show();
        }, 500);
    } else {
        clearInterval(progressInterval);
        $('#speedTestProgress').hide();
        $('#speedTestSelection').show();
        showAlert('<?php echo $lang435; ?>: ' + (result.message || 'Unknown error'), 'danger');
    }
}

function initSpeedChart() {
    let ctx = document.getElementById('speedChart').getContext('2d');
    
    resetSpeedChart();
    
    if (speedChart) {
        speedChart.destroy();
    }
    
    speedChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: [],
            datasets: [
                {
                    label: '<?php echo $lang436; ?> (Mbps)',
                    data: [],
                    borderColor: '#34c759',
                    backgroundColor: 'rgba(52, 199, 89, 0.05)',
                    borderWidth: 3,
                    pointRadius: 2,
                    pointHoverRadius: 5,
                    pointBackgroundColor: '#34c759',
                    tension: 0.3,
                    fill: true,
                    spanGaps: true
                },
                {
                    label: '<?php echo $lang437; ?> (Mbps)',
                    data: [],
                    borderColor: '#007aff',
                    backgroundColor: 'rgba(0, 122, 255, 0.05)',
                    borderWidth: 3,
                    pointRadius: 2,
                    pointHoverRadius: 5,
                    pointBackgroundColor: '#007aff',
                    tension: 0.3,
                    fill: true,
                    spanGaps: true
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            animation: {
                duration: 0
            },
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': ' + context.parsed.y.toFixed(1) + ' Mbps';
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: '<?php echo $lang438; ?>',
                        font: {
                            weight: 'bold'
                        }
                    },
                    grid: {
                        color: 'rgba(0,0,0,0.05)'
                    },
                    ticks: {
                        callback: function(value) {
                            return value.toFixed(0) + ' Mbps';
                        }
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: '<?php echo $lang439; ?>',
                        font: {
                            weight: 'bold'
                        }
                    },
                    grid: {
                        display: false
                    },
                    ticks: {
                        callback: function(value, index, values) {
                            return parseFloat(value).toFixed(1) + 's';
                        }
                    }
                }
            },
            elements: {
                line: {
                    tension: 0.3
                }
            }
        }
    });
}

let lastDownloadSpeed = 0;
let lastUploadSpeed = 0;
let lastTimestamp = 0;

function updateSpeedChart(downloadSpeed, uploadSpeed, elapsedTime = null) {
    if (!speedChart) return;
    
    let timestamp;
    if (elapsedTime !== null) {
        timestamp = parseFloat(elapsedTime.toFixed(1));
    } else {
        timestamp = chartData.timestamps.length + 1;
    }
    
    if (lastTimestamp > 0 && lastDownloadSpeed > 0 && lastUploadSpeed > 0) {
        let timeDiff = timestamp - lastTimestamp;
        
        if (timeDiff > 0.3 && timeDiff < 2.0) {
            let steps = Math.min(Math.floor(timeDiff / 0.1), 10);
            
            for (let step = 1; step <= steps; step++) {
                let interpTimestamp = lastTimestamp + (timeDiff * (step / steps));
                let interpDownload = lastDownloadSpeed - ((lastDownloadSpeed - downloadSpeed) * (step / steps));
                let interpUpload = lastUploadSpeed - ((lastUploadSpeed - uploadSpeed) * (step / steps));
                
                interpTimestamp = parseFloat(interpTimestamp.toFixed(1));
                
                chartData.timestamps.push(interpTimestamp);
                chartData.download.push(parseFloat(interpDownload.toFixed(1)));
                chartData.upload.push(parseFloat(interpUpload.toFixed(1)));
            }
        }
    }
    
    chartData.timestamps.push(timestamp);
    chartData.download.push(parseFloat(downloadSpeed.toFixed(1)));
    chartData.upload.push(parseFloat(uploadSpeed.toFixed(1)));
    
    lastDownloadSpeed = downloadSpeed;
    lastUploadSpeed = uploadSpeed;
    lastTimestamp = timestamp;
    
    while (chartData.download.length > 40) {
        chartData.download.shift();
        chartData.upload.shift();
        chartData.timestamps.shift();
    }
    
    speedChart.data.labels = [...chartData.timestamps];
    speedChart.data.datasets[0].data = [...chartData.download];
    speedChart.data.datasets[1].data = [...chartData.upload];
    speedChart.update('none');
}

function resetSpeedChart() {
    lastDownloadSpeed = 0;
    lastUploadSpeed = 0;
    lastTimestamp = 0;
    chartData = {
        download: [],
        upload: [],
        timestamps: []
    };
    if (speedChart) {
        speedChart.data.labels = [];
        speedChart.data.datasets[0].data = [];
        speedChart.data.datasets[1].data = [];
        speedChart.update();
    }
}

// Анимация счетчика скорости
function animateValue(element, start, end, duration, suffix = '') {
    if (!element) return;
    let startTime = null;
    
    function update(currentTime) {
        if (!startTime) startTime = currentTime;
        let progress = Math.min((currentTime - startTime) / duration, 1);
        let value = Math.floor(progress * (end - start) + start);
        element.textContent = value + suffix;
        
        if (progress < 1) {
            requestAnimationFrame(update);
        } else {
            element.textContent = end + suffix;
        }
    }
    
    requestAnimationFrame(update);
}


// Отображение результатов теста
function displaySpeedResults(result) {
    let results = result.results;
    
    chartData = {
        download: [],
        upload: [],
        timestamps: []
    };
    
    if (results.speed_samples && results.speed_samples.length > 0) {
        results.speed_samples.sort((a, b) => a.time_sec - b.time_sec);
        
        results.speed_samples.forEach(sample => {
            chartData.timestamps.push(sample.time_sec.toFixed(1));
            chartData.download.push(sample.download_mbps || 0);
            chartData.upload.push(sample.upload_mbps || 0);
            
            if (chartData.download.length > 30) {
                chartData.download.shift();
                chartData.upload.shift();
                chartData.timestamps.shift();
            }
        });
        
        if (speedChart) {
            speedChart.data.labels = [...chartData.timestamps];
            speedChart.data.datasets[0].data = [...chartData.download];
            speedChart.data.datasets[1].data = [...chartData.upload];
            speedChart.update();
        }
    }
    
    if (results.download_speed) {
        animateValue(document.getElementById('downloadSpeed'), 0, results.download_speed.speed_mbps, 1000, ' Mbps');
        let downloadMB = document.getElementById('downloadSpeedMB');
        if (downloadMB) {
            downloadMB.textContent = results.download_speed.speed_mb_s + ' MB/s';
        }
    }
    
    if (results.upload_speed) {
        animateValue(document.getElementById('uploadSpeed'), 0, results.upload_speed.speed_mbps, 1000, ' Mbps');
        let uploadMB = document.getElementById('uploadSpeedMB');
        if (uploadMB) {
            uploadMB.textContent = results.upload_speed.speed_mb_s + ' MB/s';
        }
    }
    
    if (results.ping) {
        animateValue(document.getElementById('pingLatency'), 0, results.ping, 500, ' ms');
    }
    
    if (results.jitter) {
        animateValue(document.getElementById('jitterValue'), 0, results.jitter, 500, ' ms');
    }
    
    if (results.packet_loss !== undefined && results.packet_loss !== null) {
        animateValue(document.getElementById('packetLoss'), 0, results.packet_loss, 500, ' %');
        
        let packetLossElem = document.getElementById('packetLoss');
        if (results.packet_loss === 0) {
            packetLossElem.style.color = '#34c759';
        } else if (results.packet_loss < 5) {
            packetLossElem.style.color = '#ff9f0a';
        } else {
            packetLossElem.style.color = '#ff3b30';
        }
    }
    
    let detailsHtml = `
        <strong><?php echo $lang440; ?>:</strong><br>
        Source: ${escapeHtml(result.from_host.hostName)} (${result.from_host.hostIp})<br>
        Target: ${escapeHtml(result.to_host.hostName)} (${result.to_host.hostIp})<br>
        Test Size: ${results.test_size_kb || 1024} KB
    `;
    
    if (results.download_speed) {
        detailsHtml += `<br><?php echo $lang441; ?>: ${results.download_speed.time_seconds} seconds`;
        detailsHtml += `<br><?php echo $lang442; ?>: ${results.download_speed.data_size_kb} KB`;
        detailsHtml += `<br><?php echo $lang443; ?>: ${results.download_speed.speed_mbps} Mbps (${results.download_speed.speed_mb_s} MB/s)`;
    }
    
    if (results.upload_speed) {
        detailsHtml += `<br><?php echo $lang444; ?>: ${results.upload_speed.time_seconds} seconds`;
        detailsHtml += `<br><?php echo $lang445; ?>: ${results.upload_speed.data_size_kb} KB`;
        detailsHtml += `<br><?php echo $lang446; ?>: ${results.upload_speed.speed_mbps} Mbps (${results.upload_speed.speed_mb_s} MB/s)`;
    }
    
    if (results.speed_samples && results.speed_samples.length > 0) {
        detailsHtml += `<br><span class="text-info"><i class="bi bi-graph-up"></i> <?php echo $lang447; ?> ${results.speed_samples.length} <?php echo $lang448; ?></span>`;
    }
    
    if (result.proxy_mode) {
        detailsHtml += `<br><span class="text-warning"><i class="bi bi-info-circle"></i> <?php echo $lang449; ?></span>`;
    }
    
    $('#testDetails').html(detailsHtml);
}


function startStatusCheck(requestId) {
    if (refreshInterval) {
        clearInterval(refreshInterval);
    }
    
    let checkCount = 0;
    refreshInterval = setInterval(async () => {
        checkCount++;
        let result = await apiCall('check_outgoing_request', 'GET', { arId: requestId });
        
        if (result.success && !result.exists) {
            clearInterval(refreshInterval);
            refreshInterval = null;
            showAlert('✨ <?php echo $lang450; ?>', 'success');
            await loadOutgoingRequests();
            await loadHosts();
        } else if (checkCount >= 60) {
            clearInterval(refreshInterval);
            refreshInterval = null;
            showAlert('<?php echo $lang451; ?>', 'info');
        }
    }, 5000);
}

async function acceptIncomingRequest(arId) {
    if (!confirm('<?php echo $lang452; ?>')) return;
    showLoading();
    let result = await apiCall('join_request', 'GET', { arId: arId });
    hideLoading();
    if (result.success) {
        showAlert('✅ <?php echo $lang453; ?>', 'success');
        loadIncomingRequests();
        loadHosts();
    } else {
        showAlert(result.message, 'danger');
    }
}

async function rejectIncomingRequest(arId) {
    if (!confirm('<?php echo $lang454; ?>')) return;
    showLoading();
    let result = await apiCall('delete_incoming_request', 'GET', { arId: arId });
    hideLoading();
    if (result.success) {
        showAlert('<?php echo $lang455; ?>', 'info');
        loadIncomingRequests();
    } else {
        showAlert(result.message, 'danger');
    }
}

async function getSnForHost(idHost) {
    showLoading();
    let result = await getRemoteHostSn(idHost);
    hideLoading();
    
    if (result.success) {
        showAlert(`✅ <?php echo $lang456; ?>: ${result.sn}`, 'success');
        loadHosts();
    } else {
        showAlert(`❌ <?php echo $lang457; ?>: ${result.message}`, 'danger');
    }
}

async function regeneratePin() {
    if (!confirm('<?php echo $lang458; ?>')) return;
    showLoading();
    let result = await apiCall('regenerate_pin');
    hideLoading();
    if (result.success) {
        let newPin = result.pin;
        let displayPin = '•'.repeat(newPin.length);
        $('#pinValue').attr('data-pin', newPin).text(displayPin);
        showAlert('<?php echo $lang459; ?>', 'success');
		setTimeout(() => {
			location.reload();
		}, 3000);
    } else {
        showAlert(result.message || '<?php echo $lang460; ?>', 'danger');
    }
}


// ========== TRAP STATUS ==========
async function loadTrapStatus() {
    let result = await apiCall('get_trap_status');
    if (result.success) {
        let toggle = $('#trapToggle');
        let label = $('#trapLabel');
        let icon = $('#trapToggle').closest('.form-check').find('i');
        
        toggle.prop('checked', result.enabled);
        
        if (result.enabled) {
            label.text('<?php echo $lang461; ?>');
            icon.removeClass('bi-shield-exclamation').addClass('bi-shield-check');
            icon.css('color', '#34c759');
        } else {
            label.text('<?php echo $lang462; ?>');
            icon.removeClass('bi-shield-check').addClass('bi-shield-exclamation');
            icon.css('color', '#ff3b30');
        }
    }
}

async function setTrapStatus(enabled) {
    showLoading();
    let result = await apiCall('set_trap_status', 'POST', { enabled: enabled ? '1' : '0' });
    hideLoading();
    
    if (result.success) {
        showAlert(result.message, 'success');
        await loadTrapStatus();
    } else {
        showAlert(result.message, 'danger');
        $('#trapToggle').prop('checked', !enabled);
    }
}

async function rotateHostKey(idHost) {
    if (!confirm(`⚠️ <?php echo $lang463; ?>`)) {
        return;
    }
    
    showLoading();
    
    let hostResult = await apiCall('get_host', 'GET', { idHost: idHost });
    
    if (!hostResult.success || !hostResult.data) {
        hideLoading();
        showAlert('<?php echo $lang464; ?>', 'danger');
        return;
    }
    
    let host = hostResult.data;
    
    if (!host.hostSn) {
        hideLoading();
        showAlert('<?php echo $lang465; ?>', 'warning');
        return;
    }
    
    if (!host.hostApiKey) {
        hideLoading();
        showAlert('<?php echo $lang466; ?>', 'danger');
        return;
    }
    
    let action = (host.hostType === 'master') ? 'generate_new_key' : 'slave_initiate_key_rotation';
    
    const proto = host.hostProto || 'http';
    const port = host.hostPort || (proto === 'https' ? 443 : 80);
    const apiPath = host.hostApiPath || '/api';
    const url = `${proto}://${host.hostIp}:${port}${apiPath}/api_inspector.php?action=${action}`;
    
    let body = {};
    if (action === 'generate_new_key') {
        body = { target_sn: host.hostSn };
    }
    
    console.log(`Calling ${url} with SN: ${host.hostSn}`);
    
    try {
        let response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-API-SN': host.hostSn,
                'X-API-KEY': host.hostApiKey
            },
            body: JSON.stringify(body)
        });
        
        let result = await response.json();
        hideLoading();
        
        if (result.status === 'success') {
            let message = `✅ <?php echo $lang467; ?>: ${result.task_id}<?php echo $lang468; ?>: ${result.new_api_key}\n`;
            
            if (host.hostType !== 'master') {
                message += `\n📡 <?php echo $lang469; ?> ${result.masters_count || 'all'} <?php echo $lang470; ?>`;
            } else {
                message += `\n📡 <?php echo $lang471; ?>`;
            }
            
            showAlert(message, 'success');
            
            if (confirm('<?php echo $lang472; ?>')) {
                navigator.clipboard.writeText(result.new_api_key);
                showAlert('<?php echo $lang473; ?>', 'info');
            }
            
            if (confirm('View task status?')) {
                checkRotationStatus(result.task_id, idHost);
            }
        } else {
            showAlert('❌ <?php echo $lang474; ?>: ' + (result.error || 'Unknown error'), 'danger');
        }
    } catch(e) {
        hideLoading();
        console.error('Rotation error:', e);
        showAlert(`❌ <?php echo $lang475; ?> ${host.hostName} (${host.hostIp}): ${e.message}`, 'danger');
    }
}


// Функция для проверки статуса задачи
async function checkRotationStatus(taskId, hostId) {
    showLoading();
    
    let apiKeyResult = await apiCall('get_host_api_key');
    if (!apiKeyResult.success || !apiKeyResult.api_key) {
        hideLoading();
        showAlert('<?php echo $lang476; ?>', 'danger');
        return;
    }
    
    let hostResult = await apiCall('get_host', 'GET', { idHost: hostId });
    if (!hostResult.success || !hostResult.data) {
        hideLoading();
        showAlert('<?php echo $lang477; ?>', 'danger');
        return;
    }
    
    let host = hostResult.data;
    
    let url = `../api/api_inspector.php?action=status&task_id=${taskId}`;
    
    try {
        let response = await fetch(url, {
            method: 'GET',
            headers: {
                'X-API-SN': host.hostSn,
                'X-API-KEY': apiKeyResult.api_key
            }
        });
        
        let result = await response.json();
        hideLoading();
        
        if (result.tasks && result.tasks.length > 0) {
            let task = result.tasks[0];
            let statusText = '';
            let statusClass = '';
            let statusIcon = '';
            
            switch(task.status) {
                case 'pending':
                    statusText = '<?php echo $lang478; ?>';
                    statusClass = 'text-warning';
                    statusIcon = '⏳';
                    break;
                case 'in_progress':
                    statusText = '<?php echo $lang479; ?>';
                    statusClass = 'text-info';
                    statusIcon = '🔄';
                    break;
                case 'awaiting_confirmation':
                    statusText = '<?php echo $lang480; ?>';
                    statusClass = 'text-primary';
                    statusIcon = '⏰';
                    break;
                case 'completed':
                    statusText = '<?php echo $lang481; ?>';
                    statusClass = 'text-success';
                    statusIcon = '✅';
                    break;
                case 'failed':
                    statusText = '<?php echo $lang482; ?> ' + (task.lastError || 'Unknown error');
                    statusClass = 'text-danger';
                    statusIcon = '❌';
                    break;
            }
            
            let modalHtml = `
                <div class="text-center">
                    <div style="font-size: 48px;">${statusIcon}</div>
                    <h5 class="mt-2"><?php echo $lang483; ?> #${task.taskId}</h5>
                    <div class="mt-3 p-3 bg-light rounded">
                        <p><strong><?php echo $lang484; ?>:</strong> <code>${escapeHtml(task.targetSn)}</code></p>
                        <p><strong><?php echo $lang485; ?>:</strong> <span class="${statusClass} fw-bold">${statusText}</span></p>
                        <p><strong><?php echo $lang486; ?>:</strong> ${escapeHtml(task.initiationDate)}</p>
                        ${task.completedDate ? `<p><strong><?php echo $lang487; ?>:</strong> ${escapeHtml(task.completedDate)}</p>` : ''}
                        ${task.lastError ? `<p><strong><?php echo $lang488; ?>:</strong> <span class="text-danger">${escapeHtml(task.lastError)}</span></p>` : ''}
                    </div>
                    ${task.newApiKey && task.status !== 'completed' ? `
                        <div class="alert alert-secondary mt-3">
                            <strong><?php echo $lang489; ?>:</strong><br>
                            <code class="user-select-all small">${escapeHtml(task.newApiKey)}</code>
                            <button class="btn btn-sm btn-outline-primary mt-2 d-block w-100" onclick="copyToClipboard('${escapeHtml(task.newApiKey)}')">
                                <i class="bi bi-clipboard"></i> <?php echo $lang490; ?>
                            </button>
                        </div>
                    ` : ''}
                    <button class="btn btn-secondary mt-3" onclick="$('#testModal').modal('hide')"><?php echo $lang491; ?></button>
                </div>
            `;
            
            $('#testResult').html(modalHtml);
            let statusModal = new bootstrap.Modal(document.getElementById('testModal'));
            statusModal.show();
            
            if (task.status === 'completed' || task.status === 'failed') {
                setTimeout(() => loadHosts(), 2000);
            }
        } else {
            showAlert('<?php echo $lang492; ?>', 'warning');
        }
    } catch(e) {
        hideLoading();
        console.error('Status check error:', e);
        showAlert('<?php echo $lang493; ?>: ' + e.message, 'danger');
    }
	
}



// ========== DROPDOWN & PIN HOVER ==========
function toggleDropdown(btn) {
    $('.dropdown-menu-actions').not($(btn).next()).removeClass('show');
    $(btn).next('.dropdown-menu-actions').toggleClass('show');
}

$(document).on('click', function(e) {
    if (!$(e.target).closest('.card-actions-dropdown').length) {
        $('.dropdown-menu-actions').removeClass('show');
    }
});

$(document).ready(function() {
    let pinElement = document.getElementById('pinValue');
    if (pinElement) {
        let actualPin = pinElement.getAttribute('data-pin');
        pinElement.addEventListener('mouseenter', function() {
            this.textContent = actualPin;
            this.classList.remove('blurred');
        });
        pinElement.addEventListener('mouseleave', function() {
            this.textContent = '•'.repeat(actualPin.length);
            this.classList.add('blurred');
        });
    }
    
    $('#hostProto, #hostIp, #hostPort, #hostApiPath').on('input change', updateApiUrlPreview);
    
    // Load all data
    loadHosts();
    loadIncomingRequests();
	loadTrapStatus();
    
	 $('#trapToggle').on('change', function() {
        let enabled = $(this).is(':checked');
        setTrapStatus(enabled);
    });
	
    setTimeout(() => $('#applePreloader').fadeOut(500), 500);
});
</script>
</body>
</html>