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
$message = '';
$error = '';

$current_host_id = $_SESSION['current_host_id'] ?? 1;

// Получаем API ключ и URL для текущего хоста
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
    $host_name = 'Localhost';
}

$menu = require_once 'menu.php';

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
    'currentHostName' => $host_name
];

// Переключение языка
$current_lang_display = $current_lang;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Mini-B - Mini-B Settings</title>
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
        
        .apple-card:hover {
            box-shadow: 0 12px 36px rgba(0, 0, 0, 0.12);
            transform: translateY(-2px);
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
        
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 51px;
            height: 31px;
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
            border-radius: 31px;
        }
        
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 27px;
            width: 27px;
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
        
        .btn-apple {
            background: #007aff;
            border: none;
            border-radius: 12px;
            padding: 10px 20px;
            font-weight: 500;
            font-size: 14px;
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
            padding: 9px 19px;
            font-weight: 500;
            transition: all 0.2s ease;
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
        
        .info-box {
            background: #f8f9fa;
            border-radius: 16px;
            padding: 14px 18px;
            margin-bottom: 12px;
            border: 1px solid #e9ecef;
        }
        
        .config-preview {
            background: #1e1e1e;
            color: #d4d4d4;
            border-radius: 12px;
            padding: 16px;
            font-family: 'SF Mono', 'Monaco', monospace;
            font-size: 12px;
            overflow-x: auto;
            max-height: 400px;
        }
        
        .form-control-apple, .form-select-apple {
            border-radius: 12px;
            border: 1px solid #e9ecef;
            padding: 10px 14px;
        }
        
        .form-control-apple:focus, .form-select-apple:focus {
            border-color: #007aff;
            box-shadow: 0 0 0 3px rgba(0,122,255,0.1);
            outline: none;
        }
        
        .cert-select-card {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 10px 15px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .cert-select-card:hover {
            background: #e9ecef;
        }
        
        .cert-select-card.selected {
            background: #007aff20;
            border: 1px solid #007aff;
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
        
        .status-success {
            background: #34c75920;
            color: #248a3d;
        }
        
        .status-warning {
            background: #ff950020;
            color: #c46e00;
        }
        
        .status-danger {
            background: #ff3b3020;
            color: #d70015;
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
        
        .modal-apple .modal-content {
            border-radius: 24px;
            border: none;
        }
		
		.content-with-sidebar {
			display: flex;
			gap: 20px;
			min-height: calc(100vh - 120px);
		}

		.main-panel {
			flex: 1;
			min-width: 0;
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
<div id="applePreloader" class="apple-preloader">
    <div class="apple-spinner"></div>
    <div class="apple-spinner-text"><?php echo $lang12; ?></div>
</div>

<div class="top-bar">
    <div class="top-bar-left">
        <h1><i class="fas fa-server"></i> Mini-B</h1>
    </div>
    <div class="top-bar-right">
        <div class="host-selector">
            <select id="hostSelector">
                <option value=""><?php echo $lang12; ?></option>
            </select>
        </div>
        <i class="fas fa-sync-alt refresh-btn text-muted" onclick="refreshAllData()" title="<?php echo $lang3869; ?>"></i>
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
                    <div class="tab-pane fade show active" id="apacheContent" role="tabpanel">
                        <div id="hostsContainer" class="row">
                                 <div class="col-lg-6">
									<div class="apple-card">
										<div class="card-header-apple">
											<h3><i class="bi bi-globe2"></i> <?php echo $lang3870; ?></h3>
										</div>
										<div class="card-body-apple">
											<div class="mb-4 d-flex justify-content-between align-items-center">
												<div>
													<span class="fw-semibold"><?php echo $lang3871; ?></span>
													<div class="small text-muted"><?php echo $lang3872; ?></div>
												</div>
												<label class="toggle-switch">
													<input type="checkbox" id="enableWebInterface" onchange="toggleWebInterface()">
													<span class="toggle-slider"></span>
												</label>
											</div>
											
											<div class="mb-3">
												<label class="form-label fw-semibold"><?php echo $lang3873; ?></label>
												<input type="number" class="form-control form-control-apple" id="listenPort" placeholder="1488" min="1" max="65535">
												<small class="text-muted"><?php echo $lang3874; ?></small>
											</div>
											
											<div class="mb-3">
												<label class="form-label fw-semibold"><?php echo $lang3875; ?></label>
												<input type="text" class="form-control form-control-apple" id="documentRoot" placeholder="/var/www/html/admin">
												<small class="text-muted"><?php echo $lang3876; ?></small>
											</div>
										</div>
									</div>
									
									<!-- SSL Configuration Card -->
									<div class="apple-card">
										<div class="card-header-apple">
											<h3><i class="bi bi-lock-fill"></i> <?php echo $lang3877; ?></h3>
										</div>
										<div class="card-body-apple">
											<div class="mb-4 d-flex justify-content-between align-items-center">
												<div>
													<span class="fw-semibold"><?php echo $lang3878; ?></span>
													<div class="small text-muted"><?php echo $lang3879; ?></div>
												</div>
												<label class="toggle-switch">
													<input type="checkbox" id="enableHttps" onchange="toggleHttps()">
													<span class="toggle-slider"></span>
												</label>
											</div>
											
											<div id="sslSettings" style="display: none;">
												<div class="mb-3">
													<label class="form-label fw-semibold"><?php echo $lang3880; ?></label>
													<div class="d-flex gap-2">
														<input type="text" class="form-control form-control-apple" id="sslCertFile" placeholder="/path/to/certificate.crt" readonly>
														<button type="button" class="btn-apple-outline" onclick="openCertificateSelector()">
															<i class="bi bi-search"></i> <?php echo $lang3881; ?>
														</button>
													</div>
												</div>
												<div class="mb-3">
													<label class="form-label fw-semibold"><?php echo $lang3882; ?></label>
													<input type="text" class="form-control form-control-apple" id="sslKeyFile" placeholder="/path/to/private.key" readonly>
												</div>
												<div class="mb-3">
													<label class="form-label fw-semibold"><?php echo $lang3883; ?></label>
													<input type="text" class="form-control form-control-apple" id="sslChainFile" placeholder="/path/to/chain.pem" readonly>
												</div>
												<div class="alert alert-info small rounded-3" style="background: #e3f2fd; border: none;">
													<i class="bi bi-info-circle me-2"></i><?php echo $lang3884; ?>
												</div>
											</div>
										</div>
									</div>
								</div>
								<!-- Status & Actions Card -->
								<div class="col-lg-6">
									<div class="apple-card">
										<div class="card-header-apple">
											<h3><i class="bi bi-info-circle"></i> <?php echo $lang3885; ?></h3>
										</div>
										<div class="card-body-apple">
											<div class="info-box d-flex justify-content-between align-items-center">
												<div>
													<i class="bi bi-server me-2 text-primary"></i>
													<strong><?php echo $lang3886; ?></strong>
													<span id="apacheStatus" class="ms-2">-</span>
												</div>
												<div class="btn-group">
													<button class="btn-apple-outline btn-sm" onclick="serviceAction('restart')" style="padding: 5px 12px;">
														<i class="bi bi-arrow-repeat"></i> <?php echo $lang3887; ?>
													</button>
													<button class="btn-apple-outline btn-sm" onclick="serviceAction('reload')" style="padding: 5px 12px;">
														<i class="bi bi-arrow-repeat"></i> <?php echo $lang3888; ?>
													</button>
												</div>
											</div>
											
											<div class="info-box">
												<i class="bi bi-file-text me-2 text-primary"></i>
												<strong><?php echo $lang3889; ?></strong>
												<code id="configPath">/etc/apache2/sites-available/minib.conf</code>
											</div>
											
											<div class="info-box">
												<i class="bi bi-calendar me-2 text-primary"></i>
												<strong><?php echo $lang3890; ?></strong>
												<span id="lastModified">-</span>
											</div>
										</div>
									</div>

									<!-- Actions Card -->
									<div class="apple-card">
										<div class="card-header-apple">
											<h3><i class="bi bi-tools"></i> <?php echo $lang3891; ?></h3>
										</div>
										<div class="card-body-apple">
											<div class="d-flex gap-3 flex-wrap mb-4">
												<button type="button" class="btn-apple sm" onclick="applyConfig()">
													<i class="bi bi-check-lg me-2"></i><?php echo $lang3892; ?>
												</button>
												<button type="button" class="btn-apple-outline sm" onclick="loadCurrentConfig()">
													<i class="bi bi-arrow-repeat me-2"></i><?php echo $lang3893; ?>
												</button>
												<button type="button" class="btn btn-danger sm" onclick="restoreDefaultConfig()">
													<i class="bi bi-arrow-counterclockwise me-2"></i><?php echo $lang3894; ?>
												</button>
											</div>
											
											<div class="alert alert-warning rounded-3 small">
												<i class="bi bi-exclamation-triangle me-2"></i>
												<strong><?php echo $lang3895; ?></strong> <?php echo $lang3896; ?>
											</div>
										</div>
									</div>
								</div>
								<div class="apple-card">
									<div class="card-header-apple d-flex justify-content-between align-items-center">
										<h3><i class="bi bi-file-code"></i> <?php echo $lang3897; ?></h3>
										<button class="btn-apple-outline btn-sm" onclick="copyConfigToClipboard()">
											<i class="bi bi-clipboard"></i> <?php echo $lang3898; ?>
										</button>
									</div>
									<div class="card-body-apple">
										<pre id="configPreview" class="config-preview"><?php echo $lang3899; ?></pre>
									</div>
								</div>
                        </div>
                    </div>
                    
					<!-- INTERFACE TAB -->
                    <div class="tab-pane fade" id="interfaceContent" role="tabpanel">
                        <div id="hostsContainer" class="row">
                            <div class="col-12">
                                <div id="rotationContainer">						
									<div class="row">
										<!-- Info Card -->
											<div class="apple-card">
												<div class="card-header-apple">
													<h3><i class="fas fa-sliders-h"></i> <?php echo $lang4359; ?></h3>
												</div>
												<div class="card-body-apple col-lg-5">
													<div class="apple-card">
														<div class="card-header-apple">
															<h3><i class="fa fa-language"></i> <?php echo $lang4355; ?></h3>
														</div>
														<div class="card-body-apple">
														<p><?php echo $lang4360; ?></p>
															<div class="language-selector" style="margin-left: 15px;">
																<?php echo $lang4361; ?>  <select id="languageSelector" style="background: rgba(255,255,255,0.9); border: 1px solid #ddd; border-radius: 20px; padding: 6px 15px; font-size: 14px; cursor: pointer;">
																	<?php foreach ($available_langs as $code => $lang): ?>
																		<option value="<?php echo $code; ?>" <?php echo $code === $current_lang ? 'selected' : ''; ?>>
																			<?php echo $lang['name']; ?>
																		</option>
																	<?php endforeach; ?>
																</select>
																<button id="saveLanguageBtn" class="btn-apple btn-sm" style="padding: 4px 12px; margin-left: 8px; border-radius: 20px;">
																	<i class="bi bi-save"></i> <?php echo $lang4356; ?>
																</button>
															</div>
														</div>
													</div>
												</div>
											</div>
									</div>
								</div>
								
								
								
                            </div>
                        </div>
                    </div>
					
                    <!-- INCOMING REQUESTS TAB -->
                    <div class="tab-pane fade" id="apiceycontent" role="tabpanel">
                        <div id="incomingContainer" class="row">
                            <div class="col-12 text-center py-5">
                                <div id="rotationContainer">						
									<div class="row">
										<!-- Info Card -->
										<div class="col-lg-8">
											<div class="apple-card">
												<div class="card-header-apple">
													<h3><i class="bi bi-clock-history"></i> <?php echo $lang4358; ?></h3>
												</div>
												<div class="card-body-apple">
													<div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
														<table class="table table-sm" id="rotationHistoryTable">
															<thead>
																<tr>
																	<th><?php echo $lang3901; ?></th>
																	<th><?php echo $lang3902; ?></th>
																	<th><?php echo $lang3903; ?></th>
																	<th><?php echo $lang3904; ?></th>
																	<th><?php echo $lang3905; ?></th>
																	<th><?php echo $lang3906; ?></th>
																	<th><?php echo $lang3907; ?></th>
																</tr>
															</thead>
															<tbody>
																<tr>
																	<td colspan="7" class="text-center text-muted"><?php echo $lang3908; ?></td>
																</tr>
															</tbody>
														</table>
													</div>
												</div>
											</div>
										</div>
										
										<!-- Settings Card -->
										<div class="col-lg-4">
											<div class="apple-card">
												<div class="card-header-apple">
													<h3><i class="bi bi-sliders2"></i> <?php echo $lang3909; ?></h3>
												</div>
												<div class="card-body-apple">
													<div class="mb-4 d-flex justify-content-between align-items-center">
														<div>
															<span class="fw-semibold"><?php echo $lang3910; ?></span>
															<div class="small text-muted"><?php echo $lang3911; ?></div>
														</div>
														<label class="toggle-switch">
															<input type="checkbox" id="enableRotation" onchange="toggleRotation()">
															<span class="toggle-slider"></span>
														</label>
													</div>
													
													<div id="rotationDaysSettings" style="display: none;">
														<div class="mb-3">
															<label class="form-label fw-semibold"><?php echo $lang3912; ?></label>
															<input type="number" class="form-control form-control-apple" id="rotationDays" 
																   placeholder="30" min="1" max="365">
															<small class="text-muted"><?php echo $lang3913; ?></small>
														</div>
														<div class="alert alert-info small rounded-3" style="background: #e3f2fd; border: none;">
															<i class="bi bi-info-circle me-2"></i>
															<?php echo $lang3914; ?>
															<?php echo $lang3915; ?>
														</div>
													</div>
													
													<div class="d-flex gap-3 mt-4">
														<button type="button" class="btn-apple" onclick="saveRotationSettings()">
															<i class="bi bi-save me-2"></i><?php echo $lang3916; ?>
														</button>
														<button type="button" class="btn-apple-outline" onclick="loadRotationSettings()">
															<i class="bi bi-arrow-repeat me-2"></i><?php echo $lang3917; ?>
														</button>
													</div>
												</div>
											</div>
										</div>
									</div>
								</div>
								
								
								
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Сайдбар с вкладками -->
            <div class="sidebar-tabs">
                <div class="nav flex-column nav-pills" id="v-pills-tab" role="tablist" aria-orientation="vertical">
                    <button class="nav-link active" id="hosts-tab" data-bs-toggle="pill" data-bs-target="#apacheContent" type="button" role="tab">
                        <i class="bi bi-card-checklist"></i>
                        <span class="tab-label"><?php echo $lang4357; ?></span>
                    </button>
					<button class="nav-link" id="interface-tab" data-bs-toggle="pill" data-bs-target="#interfaceContent" type="button" role="tab">
                        <i class="bi bi-sliders"></i>
                        <span class="tab-label"><?php echo $lang3918; ?></span>
                    </button>
                    <button class="nav-link" id="incoming-tab" data-bs-toggle="pill" data-bs-target="#apiceycontent" type="button" role="tab">
                        <i class="bi bi-code"></i>
                        <span class="tab-label"><?php echo $lang3919; ?></span>
                    </button>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Certificate Selector Modal -->
<div class="modal fade modal-apple" id="certSelectorModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-semibold"><i class="bi bi-shield-check"></i> <?php echo $lang3920; ?></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <input type="text" class="form-control form-control-apple" id="certSearchInput" placeholder="<?php echo $lang3921; ?>" onkeyup="filterCertificates()">
                </div>
                <div id="certListContainer" class="row g-2" style="max-height: 400px; overflow-y: auto;">
                    <div class="text-center py-5">
                        <div class="loading-spinner-sm"></div> <?php echo $lang3922; ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary rounded-3" data-bs-dismiss="modal"><?php echo $lang3923; ?></button>
            </div>
        </div>
    </div>
</div>

<script src="lib/jquery-3.6.0-master/dist/jquery.min.js"></script>
<script src="lib/bootstrap-5.3.8-dist/js/bootstrap.bundle.min.js"></script>
<script src="js/loader.js"></script>

<script>
const API_BASE = window.apiConfig.apiBaseUrl || '/api/';
let certificatesList = [];

// ========== Utility Functions ==========
$('#saveLanguageBtn').on('click', function() {
    var newLang = $('#languageSelector').val();
    
    if (!newLang) {
        showAlert('<?php echo $lang4362; ?>', 'danger');
        return;
    }
    
    showAlert('<?php echo $lang4363; ?>', 'info');
    $('#applePreloader').fadeIn(200);
    
    $.ajax({
        url: window.apiConfig.apiBaseUrl + 'minib_setting_api.php',
        method: 'POST',
        data: { 
            action: 'save_language',
            lang: newLang 
        },
        headers: {
            'X-API-Key': window.apiConfig.apiKey
        },
        dataType: 'json',
        success: function(response) {
            $('#applePreloader').fadeOut(500);
            if (response.success) {
                showAlert('<?php echo $lang4364; ?>', 'success');
                setTimeout(function() {
                    location.reload();
                }, 1000);
            } else {
                showAlert('<?php echo $lang4365; ?> ' + (response.error || 'Unknown error'), 'danger');
            }
        },
        error: function(xhr) {
            $('#applePreloader').fadeOut(500);
            showAlert('<?php echo $lang4366; ?>', 'danger');
            console.error('Language save error:', xhr.responseText);
        }
    });
});

function showAlert(message, type = 'success') {
    const alertHtml = `<div class="alert alert-${type} alert-dismissible fade show mb-3" role="alert">
        <i class="bi bi-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i> 
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>`;
    $('#alertContainer').append(alertHtml);
    setTimeout(() => $('.alert').fadeOut(500, function() { $(this).remove(); }), 5000);
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/[&<>]/g, function(m) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;' }[m];
    });
}

async function toggleWebInterface() {
    const enabled = $('#enableWebInterface').is(':checked');
    
    if (!enabled) {
        const confirmed = confirm(
            '⚠️ <?php echo $lang3924; ?>\n\n' +
            '<?php echo $lang3925; ?>\n\n' +
            '<?php echo $lang3926; ?>'
        );
        
        if (!confirmed) {
            $('#enableWebInterface').prop('checked', true);
            return;
        }
    }
    
    if (!enabled) {
        $('#enableHttps').prop('checked', false);
        $('#sslSettings').hide();
    }
}

function apiCall(action, method, data, onSuccess, onError) {
    const apiUrl = window.apiConfig.apiBaseUrl + 'minib_setting_api.php';
    
    $.ajax({
        url: apiUrl,
        method: method,
        data: $.extend({ action: action }, data),
        headers: {
            'X-API-Key': window.apiConfig.apiKey
        },
        success: function(response) {
            if (response.success) {
                if (onSuccess) onSuccess(response);
            } else {
                if (onError) onError(response.error || 'Unknown error');
                else showAlert(response.error || 'Error', 'danger');
            }
        },
        error: function(xhr) {
            const error = xhr.responseJSON?.error || '<?php echo $lang3927; ?>';
            if (onError) onError(error);
            else showAlert(error, 'danger');
        }
    });
}


// ========== Host Selector ==========
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
                    window.location.href = 'minib_setting_api.php';
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

// ========== Load Apache Status ==========
function loadApacheStatus() {
    apiCall('get_status', 'GET', null,
        function(response) {
            if (response.running) {
                $('#apacheStatus').html('<span class="status-badge status-success"><i class="bi bi-check-circle-fill"></i> <?php echo $lang3928; ?></span>');
            } else {
                $('#apacheStatus').html('<span class="status-badge status-danger"><i class="bi bi-x-circle-fill"></i> <?php echo $lang3929; ?></span>');
            }
        },
        function(error) {
            $('#apacheStatus').html('<span class="status-badge status-warning"><?php echo $lang3930; ?></span>');
        }
    );
}

// ========== Load Current Config ==========
function loadCurrentConfig() {
    $('#configPreview').html('<div class="text-center"><div class="loading-spinner-sm"></div> <?php echo $lang3931; ?></div>');
    
    apiCall('get_config', 'GET', null, 
        function(response) {
            $('#enableWebInterface').prop('checked', response.enabled === true);
            $('#listenPort').val(response.port);
            $('#documentRoot').val(response.document_root);
            $('#enableHttps').prop('checked', response.ssl_enabled === true);
            
            if (response.ssl_enabled === true) {
                $('#sslSettings').show();
                $('#sslCertFile').val(response.ssl_cert || '');
                $('#sslKeyFile').val(response.ssl_key || '');
                $('#sslChainFile').val(response.ssl_chain || '');
            } else {
                $('#sslSettings').hide();
            }
            
            $('#configPreview').text(response.config_content);
            $('#configPath').text(response.config_path);
            $('#lastModified').text(response.last_modified || 'Unknown');
        },
        function(error) {
            $('#configPreview').text('<?php echo $lang3932; ?> ' + error);
        }
    );
}


// ========== Apply Config ==========
function applyConfig() {
    const data = {
        enabled: $('#enableWebInterface').is(':checked') ? '1' : '0',
        port: $('#listenPort').val(),
        document_root: $('#documentRoot').val(),
        ssl_enabled: $('#enableHttps').is(':checked') ? '1' : '0',
        ssl_cert: $('#sslCertFile').val(),
        ssl_key: $('#sslKeyFile').val(),
        ssl_chain: $('#sslChainFile').val()
    };
    
    if (data.ssl_enabled === '1' && (!data.ssl_cert || !data.ssl_key)) {
        showAlert('<?php echo $lang3933; ?>', 'danger');
        return;
    }
    
    showAlert('<?php echo $lang3934; ?>', 'info');
    $('#applePreloader').fadeIn(200);
    
    apiCall('apply_config', 'POST', data,
        function(response) {
            $('#applePreloader').fadeOut(500);
            showAlert(response.message, 'success');
            loadCurrentConfig();
            loadApacheStatus();
        },
        function(error) {
            $('#applePreloader').fadeOut(500);
            showAlert('<?php echo $lang3935; ?> ' + error, 'danger');
        }
    );
}

// ========== Service Actions ==========
function serviceAction(action) {
    showAlert(`${action} <?php echo $lang3936; ?>`, 'info');
    
    apiCall('service_action', 'POST', { sub_action: action },
        function(response) {
            showAlert(response.message, 'success');
            loadApacheStatus();
        },
        function(error) {
            showAlert('<?php echo $lang3937; ?> ' + error, 'danger');
        }
    );
}

// ========== Restore Default ==========
function restoreDefaultConfig() {
    if (!confirm('<?php echo $lang3938; ?>')) return;
    
    showAlert('<?php echo $lang3939; ?>', 'info');
    $('#applePreloader').fadeIn(200);
    
    apiCall('restore_default', 'POST', null,
        function(response) {
            $('#applePreloader').fadeOut(500);
            showAlert(response.message, 'success');
            loadCurrentConfig();
            loadApacheStatus();
        },
        function(error) {
            $('#applePreloader').fadeOut(500);
            showAlert('<?php echo $lang3940; ?> ' + error, 'danger');
        }
    );
}

// ========== Toggle Functions ==========
function toggleWebInterface() {
    const enabled = $('#enableWebInterface').is(':checked');
    if (!enabled) {
        $('#enableHttps').prop('checked', false);
        $('#sslSettings').hide();
    }
}

function toggleHttps() {
    const enabled = $('#enableHttps').is(':checked');
    $('#sslSettings').toggle(enabled);
    
    if (!enabled) {
        $('#sslCertFile').val('');
        $('#sslKeyFile').val('');
        $('#sslChainFile').val('');
    }
}

// ========== Certificate Selector ==========
function openCertificateSelector() {
    $('#certListContainer').html('<div class="text-center py-5"><div class="loading-spinner-sm"></div> <?php echo $lang3941; ?></div>');
    new bootstrap.Modal(document.getElementById('certSelectorModal')).show();
    
    apiCall('list_certificates', 'GET', null,
        function(response) {
            certificatesList = response.certificates || [];
            renderCertificateList();
        },
        function(error) {
            $('#certListContainer').html('<div class="alert alert-danger"><?php echo $lang3942; ?> ' + error + '</div>');
        }
    );
}

function renderCertificateList() {
    let searchTerm = $('#certSearchInput').val().toLowerCase();
    let filtered = certificatesList.filter(cert => 
        cert.name.toLowerCase().includes(searchTerm) || 
        (cert.subject || '').toLowerCase().includes(searchTerm)
    );
    
    if (filtered.length === 0) {
        $('#certListContainer').html(`<div class="empty-state text-center py-5">
            <i class="bi bi-shield-slash" style="font-size: 48px; color: #c6c6c8;"></i>
            <p class="mt-2 text-muted"><?php echo $lang3943; ?></p>
            <small class="text-muted"><?php echo $lang3944; ?></small>
        </div>`);
        return;
    }
    
    let html = '';
    filtered.forEach(cert => {
        const isValid = cert.is_valid;
        const daysLeft = cert.days_left || 0;
        let statusClass = isValid ? (daysLeft <= 30 ? 'status-warning' : 'status-success') : 'status-danger';
        let statusText = isValid ? (daysLeft <= 30 ? `Expires in ${daysLeft}d` : 'Valid') : 'Expired';
        
        html += `<div class="col-12">
            <div class="cert-select-card" onclick="selectCertificate('${escapeHtml(cert.name)}', '${escapeHtml(cert.crt_path || '')}', '${escapeHtml(cert.key_path || '')}', '${escapeHtml(cert.chain_path || '')}')">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <strong><i class="bi bi-shield-check me-2"></i>${escapeHtml(cert.name)}</strong>
                        <div class="small text-muted">${escapeHtml(cert.subject || 'Unknown')}</div>
                    </div>
                    <div>
                        <span class="status-badge ${statusClass}">${statusText}</span>
                    </div>
                </div>
                <div class="small text-muted mt-2">
                    <div><i class="bi bi-file-earmark-lock"></i> <?php echo $lang3945; ?> ${escapeHtml(cert.crt_path || '<?php echo $lang3946; ?>')}</div>
                    <div><i class="bi bi-key"></i> <?php echo $lang3947; ?> ${cert.has_key ? '<?php echo $lang3948; ?>' : '<?php echo $lang3949; ?>'}</div>
                </div>
            </div>
        </div>`;
    });
    
    $('#certListContainer').html(html);
}

function filterCertificates() {
    renderCertificateList();
}

function selectCertificate(name, crtPath, keyPath, chainPath) {
    if (crtPath) {
        $('#sslCertFile').val(crtPath);
    } else {
        $('#sslCertFile').val(`/var/www/minib/certs/crt/${name}.crt`);
    }
    
    if (keyPath) {
        $('#sslKeyFile').val(keyPath);
    } else {
        $('#sslKeyFile').val(`/var/www/minib/certs/crt/${name}.key`);
    }
    
    if (chainPath && chainPath.trim() !== '') {
        $('#sslChainFile').val(chainPath);
    } else {
        $('#sslChainFile').val('');
    }
    
    bootstrap.Modal.getInstance(document.getElementById('certSelectorModal')).hide();
    showAlert(`<?php echo $lang3950; ?> ${name}`, 'success');
}

function copyConfigToClipboard() {
    const configText = $('#configPreview').text();
    navigator.clipboard.writeText(configText);
    showAlert('<?php echo $lang3951; ?>', 'success');
}

function refreshAllData() {
    loadCurrentConfig();
    loadApacheStatus();
}

// ========== Key Rotation Functions ==========

function toggleRotation() {
    const enabled = $('#enableRotation').is(':checked');
    $('#rotationDaysSettings').toggle(enabled);
    
    if (!enabled) {
        $('#rotationDays').val('0');
    } else {
        const currentVal = parseInt($('#rotationDays').val());
        if (isNaN(currentVal) || currentVal <= 0) {
            $('#rotationDays').val('30');
        }
    }
}

function loadRotationSettings() {
    $.ajax({
        url: API_BASE + 'minib_setting_api.php',
        method: 'GET',
        data: { action: 'get_rotation_settings' },
        headers: { 'X-API-Key': window.apiConfig.apiKey },
        success: function(response) {
            if (response.success) {
                const enabled = response.enabled === true;
                $('#enableRotation').prop('checked', enabled);
                $('#rotationDays').val(response.days || 0);
                $('#rotationDaysSettings').toggle(enabled);
            } else {
                console.error('Failed to load rotation settings:', response.error);
                $('#enableRotation').prop('checked', false);
                $('#rotationDays').val('0');
                $('#rotationDaysSettings').hide();
            }
        },
        error: function(xhr) {
            console.error('Error loading rotation settings:', xhr.responseText);
        }
    });
}

function saveRotationSettings() {
    const enabled = $('#enableRotation').is(':checked');
    let days = parseInt($('#rotationDays').val());
    
    if (enabled) {
        if (isNaN(days) || days < 1) {
            days = 30;
            $('#rotationDays').val(30);
        }
        if (days > 365) {
            showAlert('<?php echo $lang3952; ?>', 'danger');
            return;
        }
    } else {
        days = 0;
    }
    
    showAlert('<?php echo $lang3953; ?>', 'info');
    $('#applePreloader').fadeIn(200);
    
    $.ajax({
        url: API_BASE + 'minib_setting_api.php',
        method: 'POST',
        data: {
            action: 'save_rotation_settings',
            enabled: enabled ? '1' : '0',
            days: days
        },
        headers: { 'X-API-Key': window.apiConfig.apiKey },
        success: function(response) {
            $('#applePreloader').fadeOut(500);
            if (response.success) {
                showAlert('<?php echo $lang3954; ?>', 'success');
                loadRotationHistory();
            } else {
                showAlert('<?php echo $lang3955; ?> ' + response.error, 'danger');
            }
        },
        error: function(xhr) {
            $('#applePreloader').fadeOut(500);
            showAlert('<?php echo $lang3956; ?>', 'danger');
        }
    });
}

function loadRotationHistory() {
    $.ajax({
        url: API_BASE + 'minib_setting_api.php',
        method: 'GET',
        data: { action: 'get_rotation_history', limit: 50 },
        headers: { 'X-API-Key': window.apiConfig.apiKey },
        success: function(response) {
            if (response.success && response.history) {
                renderRotationHistory(response.history);
            } else {
                $('#rotationHistoryTable tbody').html('<tr><td colspan="7" class="text-center text-muted"><?php echo $lang3957; ?></td></tr>');
            }
        },
        error: function(xhr) {
            console.error('Error loading rotation history:', xhr.responseText);
            $('#rotationHistoryTable tbody').html('<tr><td colspan="7" class="text-center text-danger"><?php echo $lang3958; ?></td></tr>');
        }
    });
}

function renderRotationHistory(history) {
    if (!history || history.length === 0) {
        $('#rotationHistoryTable tbody').html('<tr><td colspan="7" class="text-center text-muted"><?php echo $lang3959; ?></td></tr>');
        return;
    }
    
    let html = '';
    history.forEach(task => {
        let statusClass = '';
        let statusText = task.status;
        
        switch(task.status) {
            case 'completed':
                statusClass = 'status-success';
                statusText = '✓ <?php echo $lang3960; ?>';
                break;
            case 'pending':
                statusClass = 'status-warning';
                statusText = '⏳ <?php echo $lang3961; ?>';
                break;
            case 'in_progress':
                statusClass = 'status-warning';
                statusText = '🔄 <?php echo $lang3962; ?>';
                break;
            case 'failed':
                statusClass = 'status-danger';
                statusText = '✗ <?php echo $lang3963; ?>';
                break;
            default:
                statusClass = 'status-info';
        }
        
        html += `<tr>
            <td>${task.taskId || task.id}</td>
            <td><code>${escapeHtml(task.targetSn || '-')}</code></td>
            <td><code>${escapeHtml((task.oldApiKey || '-').substring(0, 16))}...</code></td>
            <td><code>${escapeHtml((task.newApiKey || '-').substring(0, 16))}...</code></td>
            <td><small>${task.dateCreate || '-'}</small></td>
            <td><small>${task.completedDate || '-'}</small></td>
            <td><span class="status-badge ${statusClass}">${statusText}</span></td>
        </tr>`;
    });
    
    $('#rotationHistoryTable tbody').html(html);
}

// Update initialization to include rotation
$(document).ready(function() {
    initHostSelector();
    loadCurrentConfig();
    loadApacheStatus();
    loadRotationSettings();
    loadRotationHistory();
    setTimeout(() => $('#applePreloader').fadeOut(500), 500);
    
    $('button[data-bs-target="#rotationContent"]').on('shown.bs.tab', function() {
        loadRotationHistory();
    });
});


$(document).ready(function() {
    if (window.location.hash) {
        const hash = window.location.hash;
        const tabEl = document.querySelector(`.sidebar-tabs .nav-link[data-bs-target="${hash}"]`);
        if (tabEl) {
            bootstrap.Tab.getOrCreateInstance(tabEl).show();
        }
    }
    
    $('.sidebar-tabs .nav-link').on('shown.bs.tab', function(e) {
        const target = $(e.target).attr('data-bs-target');
        if (target) {
            window.location.hash = target;
        }
    });
});

// ========== Initialization ==========
$(document).ready(function() {
    initHostSelector();
    loadCurrentConfig();
    loadApacheStatus();
    setTimeout(() => $('#applePreloader').fadeOut(500), 500);
});
</script>
</body>
</html>