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
    <title>SSL Manager — Mini-B</title>
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
        console.log('API Config loaded:', window.apiConfig);
    </script>
    <style>
        * { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        
        body { background: linear-gradient(135deg, #f5f7fa 0%, #e9eef3 100%); min-height: 100vh; }
        
        .app-container {
			display: flex;
			min-height: calc(100vh - 60px);
			overflow-x: visible !important;
		}

        
        .main-content {
			padding: 25px 30px;
			flex: 1;
			overflow-x: visible !important;
			overflow-y: auto;
		}
        
        @media (max-width: 768px) { .main-content { padding: 20px 15px; } }
        
        /* Dashboard Cards */
        .stat-card {
            background: white;
            border-radius: 24px;
            padding: 20px;
            transition: all 0.2s;
            border: 1px solid rgba(0,0,0,0.05);
            box-shadow: 0 2px 8px rgba(0,0,0,0.02);
        }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,0.08); }
        .stat-icon { width: 48px; height: 48px; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 24px; }
        .stat-value { font-size: 28px; font-weight: 700; margin-top: 12px; }
        .stat-label { font-size: 13px; color: #8e8e93; margin-top: 4px; }
        
        /* Tabs */
        .custom-tabs {
            background: white;
            border-radius: 16px;
            padding: 4px;
            display: inline-flex;
            gap: 4px;
            margin-bottom: 24px;
            border: 1px solid #e9ecef;
        }
        .custom-tab {
            padding: 10px 24px;
            border-radius: 12px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            background: transparent;
            border: none;
            color: #6c757d;
        }
        .custom-tab.active {
            background: #007aff;
            color: white;
            box-shadow: 0 2px 8px rgba(0,122,255,0.3);
        }
        .custom-tab:hover:not(.active) { background: #f8f9fa; color: #1c1c1e; }
        
        /* Certificate Cards */
        .cert-grid {
			display: grid;
			grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
			gap: 20px;
			overflow: visible;
		}
        .cert-card {
			background: white;
			border-radius: 20px;
			border: 1px solid #e9ecef;
			transition: all 0.2s ease;
			position: relative;
			
		}
        
		.cert-card .dropdown-menu {
			position: absolute;
			z-index: 1050;
			min-width: 200px;
		}

		.card-actions {
			position: absolute;
			top: 12px;
			right: 12px;
			z-index: 10;
		}
		
		.cert-card:hover { border-color: #007aff40; box-shadow: 0 8px 20px rgba(0,122,255,0.1); transform: translateY(-2px); }
        
        .cert-card.valid { 
			border-left: 4px solid #34c759; 
			border-radius: 20px;
		}
        .cert-card.expiring { border-left: 4px solid #ff9f0a; }
        .cert-card.expired { border-left: 4px solid #ff3b30; opacity: 0.7; }
        .cert-card.revoked { border-left: 4px solid #8e8e93; opacity: 0.6; background: #f8f9fa; }
        
        .cert-card-header { padding: 16px 20px; border-bottom: 1px solid #e9ecef; display: flex; justify-content: space-between; align-items: flex-start; }
        .cert-card-body { padding: 16px 20px; }
        .cert-card-footer {
			padding: 12px 20px;
			border-top: 1px solid #e9ecef;
			background: #f8f9fa;
			font-size: 12px;
			color: #6c757d;
			/* Убеждаемся что нижние углы скруглены */
			border-radius: 0 0 20px 20px;
		}
        
        .cert-name { font-weight: 600; font-size: 16px; margin-bottom: 4px; word-break: break-all; }
        .cert-domain { font-family: 'SF Mono', monospace; font-size: 12px; color: #007aff; margin-top: 4px; }
        
        .days-left { font-size: 28px; font-weight: 700; color: #1c1c1e; }
        .days-label { font-size: 11px; color: #8e8e93; }
        
        .status-badge { display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 500; }
        .status-valid { background: #34c75920; color: #248a3d; }
        .status-expiring { background: #ff9f0a20; color: #c46e00; }
        .status-expired { background: #ff3b3020; color: #d70015; }
        .status-revoked { background: #8e8e9320; color: #5c5c5e; }
        
        /* CA Cards */
        .ca-card {
            background: white;
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 12px;
            border: 1px solid #e9ecef;
            transition: all 0.2s;
            cursor: pointer;
        }
        .ca-card:hover { border-color: #af52de40; box-shadow: 0 2px 8px rgba(0,0,0,0.05); transform: translateX(4px); }
        .ca-card.root { border-left: 3px solid #af52de; background: linear-gradient(135deg, #fff 0%, #faf5ff 100%); }
        .ca-card.intermediate { border-left: 3px solid #5e5ce0; background: linear-gradient(135deg, #fff 0%, #f5f5ff 100%); }
        
        /* Buttons */
        .btn-primary-custom { background: #007aff; border: none; border-radius: 12px; padding: 10px 20px; font-weight: 500; font-size: 14px; color: white; transition: all 0.2s; }
        .btn-primary-custom:hover { background: #005fc1; transform: scale(0.98); }
        .btn-outline-custom { background: transparent; border: 1.5px solid #007aff; color: #007aff; border-radius: 12px; padding: 9px 19px; font-weight: 500; transition: all 0.2s; }
        .btn-outline-custom:hover { background: #007aff10; transform: scale(0.98); }
        
        .btn-icon-sm { width: 32px; height: 32px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; background: #f8f9fa; border: 1px solid #e9ecef; color: #6c757d; transition: all 0.2s; }
        .btn-icon-sm:hover { background: #007aff; color: white; border-color: #007aff; }
        
        /* Modals */
        .modal-modern .modal-content { border-radius: 28px; border: none; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
        .form-modern { border-radius: 14px; border: 1px solid #e9ecef; padding: 12px 16px; transition: all 0.2s; }
        .form-modern:focus { border-color: #007aff; box-shadow: 0 0 0 3px rgba(0,122,255,0.1); outline: none; }
        
        /* Menu Button */
        .menu-btn { background: #1c1c1e; border: none; border-radius: 30px; padding: 8px 20px; color: white; font-weight: 500; display: flex; align-items: center; gap: 8px; transition: all 0.2s; }
        .menu-btn:hover { background: #3a3a3c; transform: scale(0.98); }
        .dropdown-menu-custom { border-radius: 16px; padding: 8px; min-width: 220px; box-shadow: 0 10px 40px rgba(0,0,0,0.15); border: none; }
        .dropdown-menu-custom .dropdown-item { border-radius: 10px; padding: 10px 16px; font-size: 14px; }
        .dropdown-menu-custom .dropdown-item i { width: 24px; margin-right: 8px; }
        
        .alert-toast { position: fixed; bottom: 20px; right: 20px; z-index: 9999; min-width: 300px; }
        
        .search-input { border-radius: 30px; padding: 10px 20px; border: 1px solid #e9ecef; width: 260px; }
        .search-input:focus { border-color: #007aff; outline: none; }
        
        .filter-chip { background: #f8f9fa; border-radius: 30px; padding: 6px 16px; font-size: 13px; cursor: pointer; transition: all 0.2s; border: 1px solid transparent; }
        .filter-chip.active { background: #007aff; color: white; border-color: #007aff; }
        .filter-chip:hover:not(.active) { background: #e9ecef; border-color: #dee2e6; }
        
        .loading-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; display: flex; align-items: center; justify-content: center; }
        
        .empty-state { text-align: center; padding: 60px 20px; background: white; border-radius: 20px; }
        .code-block { background: #1e1e1e; color: #d4d4d4; padding: 16px; border-radius: 16px; font-family: 'SF Mono', monospace; font-size: 12px; overflow-x: auto; max-height: 300px; }
        
        .comment-text { font-size: 11px; color: #6c757d; background: #f8f9fa; padding: 8px 12px; border-radius: 12px; margin-top: 12px; word-break: break-word; }
        
        .badge-purple { background: #af52de; color: white; font-size: 10px; padding: 2px 8px; border-radius: 12px; margin-left: 8px; }
        .badge-blue { background: #5e5ce0; color: white; font-size: 10px; padding: 2px 8px; border-radius: 12px; margin-left: 8px; }
    </style>
</head>
<body>
<div id="applePreloader" class="apple-preloader">
    <div class="apple-spinner"></div>
    <div class="apple-spinner-text">Loading...</div>
</div>

<div class="top-bar">
    <div class="top-bar-left">
        <h1><i class="fas fa-bucket"></i> Mini-B</h1>
    </div> 
    
    <div class="top-bar-right">
	<i class="fas fa-certificate"></i>SSL Manager
	<div class="host-selector" style="margin-left: 20px;">
        <select id="hostSelector" style="background: rgba(255,255,255,0.9); border: 1px solid #ddd; border-radius: 20px; padding: 6px 30px 6px 15px; font-size: 14px; cursor: pointer;">
            <option value="">Loading...</option>
        </select>
    </div>
        <div class="dropdown">
            <button class="menu-btn dropdown-toggle" type="button" data-bs-toggle="dropdown">
                <i class="bi bi-plus-lg"></i> Create
            </button>
            <ul class="dropdown-menu dropdown-menu-end dropdown-menu-custom">
                <li><a class="dropdown-item" href="#" onclick="openCreateSelfSignedModal(); return false;"><i class="bi bi-shield-check"></i> Self-Signed Certificate</a></li>
                <li><a class="dropdown-item" href="#" onclick="openCreateSignedModal(); return false;"><i class="bi bi-file-earmark-check"></i> CA-Signed Certificate</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="#" onclick="openCreateRootCAModal(); return false;"><i class="bi bi-building"></i> Root CA</a></li>
                <li><a class="dropdown-item" href="#" onclick="openCreateIntermediateCAModal(); return false;"><i class="bi bi-diagram-3"></i> Intermediate CA</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="#" onclick="openGenerateCSRModal(); return false;"><i class="bi bi-file-text"></i> Generate CSR</a></li>
                <li><a class="dropdown-item" href="#" onclick="openImportModal(); return false;"><i class="bi bi-upload"></i> Import Certificate</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="#" onclick="refreshAllData(); return false;"><i class="bi bi-arrow-repeat"></i> Refresh</a></li>
            </ul>
        </div>
    </div>
</div>

<div class="app-container">
    <?php echo $menu; ?>
    
    <main class="main-content">
        <div id="alertContainer" class="alert-toast"></div>
        
        <!-- Stats Cards -->
        <div class="row g-4 mb-4" id="statsRow">
            <div class="col-md-3 col-sm-6">
                <div class="stat-card">
                    <div class="stat-icon" style="background: #007aff20; color: #007aff;"><i class="bi bi-shield-check"></i></div>
                    <div class="stat-value" id="statTotal">0</div>
                    <div class="stat-label">Total Certificates</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card">
                    <div class="stat-icon" style="background: #34c75920; color: #34c759;"><i class="bi bi-check-circle"></i></div>
                    <div class="stat-value" id="statValid">0</div>
                    <div class="stat-label">Valid</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card">
                    <div class="stat-icon" style="background: #ff9f0a20; color: #ff9f0a;"><i class="bi bi-exclamation-triangle"></i></div>
                    <div class="stat-value text-warning" id="statExpiring">0</div>
                    <div class="stat-label">Expiring Soon</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card">
                    <div class="stat-icon" style="background: #af52de20; color: #af52de;"><i class="bi bi-building"></i></div>
                    <div class="stat-value" id="statTotalCAs">0</div>
                    <div class="stat-label">Certificate Authorities</div>
                </div>
            </div>
        </div>
        
        <!-- Tabs -->
        <div class="custom-tabs">
            <button class="custom-tab active" data-tab="certs" onclick="switchTab('certs')">
                <i class="bi bi-shield-check me-2"></i>Certificates
            </button>
            <button class="custom-tab" data-tab="cas" onclick="switchTab('cas')">
                <i class="bi bi-building me-2"></i>Certificate Authorities
            </button>
        </div>
        
        <!-- Certificates Tab -->
        <div id="certsTab">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                <div class="d-flex gap-2 flex-wrap">
                    <span class="filter-chip active" data-filter="all" onclick="applyFilter('all')">All</span>
                    <span class="filter-chip" data-filter="valid" onclick="applyFilter('valid')">Valid</span>
                    <span class="filter-chip" data-filter="expiring" onclick="applyFilter('expiring')">Expiring (≤30d)</span>
                    <span class="filter-chip" data-filter="expired" onclick="applyFilter('expired')">Expired</span>
                    <span class="filter-chip" data-filter="revoked" onclick="applyFilter('revoked')">Revoked</span>
                </div>
                <div class="d-flex gap-2">
                    <div class="view-toggle d-flex bg-light rounded-3 p-1">
                        <button class="btn btn-sm px-3 rounded-3 active-view" data-view="grid" onclick="setView('grid')" style="background: #007aff; color: white;"><i class="bi bi-grid-3x3-gap-fill"></i></button>
                        <button class="btn btn-sm px-3 rounded-3" data-view="table" onclick="setView('table')"><i class="bi bi-table"></i></button>
                    </div>
                    <input type="text" id="searchInput" class="search-input" placeholder="Search certificates..." onkeyup="filterCerts()">
                </div>
            </div>
            <div id="certsContainer">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary"></div>
                    <p class="mt-2 text-muted">Loading certificates...</p>
                </div>
            </div>
        </div>
        
        <!-- CAs Tab -->
        <div id="casTab" style="display: none;">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                <div class="d-flex gap-2">
                    <span class="filter-chip active" data-ca-filter="all" onclick="applyCAFilter('all')">All</span>
                    <span class="filter-chip" data-ca-filter="root" onclick="applyCAFilter('root')">Root CAs</span>
                    <span class="filter-chip" data-ca-filter="intermediate" onclick="applyCAFilter('intermediate')">Intermediate CAs</span>
                </div>
                <input type="text" id="caSearchInput" class="search-input" placeholder="Search CAs..." onkeyup="filterCAs()">
            </div>
            <div id="casContainer">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary"></div>
                    <p class="mt-2 text-muted">Loading CAs...</p>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Modals -->

<!-- Self-Signed Certificate Modal -->
<div class="modal fade modal-modern" id="createSelfSignedModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-semibold"><i class="bi bi-shield-check me-2" style="color: #007aff;"></i>Create Self-Signed Certificate</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 pb-4">
                <form id="selfSignedForm">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Certificate Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-modern" id="certName" placeholder="my_cert" required>
                        <small class="text-muted">Alphanumeric, underscore, hyphen only</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Domain/CN <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-modern" id="domain" placeholder="example.com" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Subject Alternative Names (SAN)</label>
                        <input type="text" class="form-control form-modern" id="sans" placeholder="www.example.com, api.example.com">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Valid Days</label>
                            <input type="number" class="form-control form-modern" id="days" value="365">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Key Size</label>
                            <select class="form-select form-modern" id="keySize">
                                <option value="2048">2048 bits</option>
                                <option value="3072">3072 bits</option>
                                <option value="4096" selected>4096 bits</option>
                            </select>
                        </div>
						<div class="col-md-6 mb-3">
							<label class="form-label fw-semibold">Signature Algorithm</label>
							<select class="form-select form-modern" id="signatureAlgo">
								<option value="sha256" selected>SHA-256 (recommended)</option>
								<option value="sha384">SHA-384</option>
								<option value="sha512">SHA-512</option>
								<option value="sha1">SHA-1 (deprecated)</option>
							</select>
						</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Comment (optional)</label>
                        <textarea class="form-control form-modern" id="createComment" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 pb-4 px-4">
                <button type="button" class="btn btn-light rounded-3 px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn-primary-custom" onclick="createSelfSigned()">Create</button>
            </div>
        </div>
    </div>
</div>

<!-- CA-Signed Certificate Modal -->
<div class="modal fade modal-modern" id="createSignedModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-semibold"><i class="bi bi-file-earmark-check me-2" style="color: #007aff;"></i>Create CA-Signed Certificate</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 pb-4">
                <div class="alert alert-info py-2 small">Only Intermediate CAs can sign regular certificates</div>
                <form id="signedForm">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Certificate Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-modern" id="signedCertName" placeholder="my_signed_cert" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Signing CA <span class="text-danger">*</span></label>
                        <select class="form-select form-modern" id="caName" required>
                            <option value="">Select Intermediate CA...</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Domain/CN <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-modern" id="signedDomain" placeholder="example.com" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">SAN</label>
                        <input type="text" class="form-control form-modern" id="signedSans" placeholder="www.example.com, api.example.com">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Valid Days</label>
                            <input type="number" class="form-control form-modern" id="signedDays" value="365">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Key Size</label>
                            <select class="form-select form-modern" id="signedKeySize">
                                <option value="2048">2048 bits</option>
                                <option value="3072">3072 bits</option>
                                <option value="4096" selected>4096 bits</option>
                            </select>
                        </div>
						<div class="col-md-6 mb-3">
							<label class="form-label fw-semibold">Signature Algorithm</label>
							<select class="form-select form-modern" id="signedSignatureAlgo">
								<option value="sha256" selected>SHA-256 (recommended)</option>
								<option value="sha384">SHA-384</option>
								<option value="sha512">SHA-512</option>
								<option value="sha1">SHA-1 (deprecated)</option>
							</select>
						</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Comment</label>
                        <textarea class="form-control form-modern" id="signedComment" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 pb-4 px-4">
                <button type="button" class="btn btn-light rounded-3 px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn-primary-custom" onclick="createSignedCertificate()">Create</button>
            </div>
        </div>
    </div>
</div>

<!-- Root CA Modal -->
<div class="modal fade modal-modern" id="createRootCAModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pt-4 px-4" style="background: #af52de10;">
                <h5 class="modal-title fw-semibold"><i class="bi bi-building me-2" style="color: #af52de;"></i>Create Root CA</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 pb-4">
                <form id="rootCAForm">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">CA Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-modern" id="rootCaName" placeholder="my_root_ca" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Common Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-modern" id="rootCaCn" placeholder="My Root CA" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Valid Days</label>
                            <input type="number" class="form-control form-modern" id="rootCaDays" value="3650">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Key Size</label>
                            <select class="form-select form-modern" id="rootCaKeySize">
                                <option value="2048">2048 bits</option>
                                <option value="3072">3072 bits</option>
                                <option value="4096" selected>4096 bits</option>
                            </select>
                        </div>
						<div class="col-md-6 mb-3">
							<label class="form-label fw-semibold">Signature Algorithm</label>
							<select class="form-select form-modern" id="rootCaSignatureAlgo">
								<option value="sha256" selected>SHA-256 (recommended)</option>
								<option value="sha384">SHA-384</option>
								<option value="sha512">SHA-512</option>
								<option value="sha1">SHA-1 (deprecated)</option>
							</select>
						</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Comment</label>
                        <textarea class="form-control form-modern" id="rootCaComment" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 pb-4 px-4">
                <button type="button" class="btn btn-light rounded-3 px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn-primary-custom" onclick="createRootCA()" style="background: #af52de;">Create</button>
            </div>
        </div>
    </div>
</div>

<!-- Intermediate CA Modal -->
<div class="modal fade modal-modern" id="createIntermediateCAModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pt-4 px-4" style="background: #5e5ce010;">
                <h5 class="modal-title fw-semibold"><i class="bi bi-diagram-3 me-2" style="color: #5e5ce0;"></i>Create Intermediate CA</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 pb-4">
                <form id="intermediateCAForm">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">CA Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-modern" id="intCaName" placeholder="my_intermediate_ca" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Root CA <span class="text-danger">*</span></label>
                        <select class="form-select form-modern" id="rootCAName" required></select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Common Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-modern" id="intCaCn" placeholder="My Intermediate CA" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Valid Days</label>
                            <input type="number" class="form-control form-modern" id="intCaDays" value="1825">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Key Size</label>
                            <select class="form-select form-modern" id="intCaKeySize">
                                <option value="2048">2048 bits</option>
                                <option value="3072">3072 bits</option>
                                <option value="4096" selected>4096 bits</option>
                            </select>
                        </div>
						<div class="col-md-6 mb-3">
							<label class="form-label fw-semibold">Signature Algorithm</label>
							<select class="form-select form-modern" id="intCaSignatureAlgo">
								<option value="sha256" selected>SHA-256 (recommended)</option>
								<option value="sha384">SHA-384</option>
								<option value="sha512">SHA-512</option>
								<option value="sha1">SHA-1 (deprecated)</option>
							</select>
						</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Comment</label>
                        <textarea class="form-control form-modern" id="intCaComment" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 pb-4 px-4">
                <button type="button" class="btn btn-light rounded-3 px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn-primary-custom" onclick="createIntermediateCA()" style="background: #5e5ce0;">Create</button>
            </div>
        </div>
    </div>
</div>

<!-- Generate CSR Modal -->
<div class="modal fade modal-modern" id="generateCSRModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-semibold"><i class="bi bi-file-text me-2"></i>Generate CSR</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 pb-4">
                <form id="csrForm">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">CSR Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-modern" id="csrName" placeholder="my_csr" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Domain/CN <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-modern" id="csrDomain" placeholder="example.com" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">SAN</label>
                        <input type="text" class="form-control form-modern" id="csrSans" placeholder="www.example.com, api.example.com">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Key Size</label>
                        <select class="form-select form-modern" id="csrKeySize">
                            <option value="2048">2048 bits</option>
                            <option value="3072">3072 bits</option>
                            <option value="4096" selected>4096 bits</option>
                        </select>
                    </div>
                </form>
                <div id="csrResult" style="display: none;" class="mt-3">
                    <hr>
                    <label class="fw-semibold">CSR Content:</label>
                    <pre id="csrOutput" class="code-block mt-1"></pre>
                    <label class="fw-semibold mt-2">Private Key:</label>
                    <pre id="keyOutput" class="code-block mt-1"></pre>
                    <div class="d-flex gap-2 mt-3">
                        <button class="btn btn-sm btn-outline-primary" onclick="copyToClipboard('csrOutput')">Copy CSR</button>
                        <button class="btn btn-sm btn-outline-success" onclick="openSignCSRFromCSR()">Sign This CSR</button>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 pb-4 px-4">
                <button type="button" class="btn btn-light rounded-3 px-4" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn-primary-custom" onclick="generateCSR()">Generate</button>
            </div>
        </div>
    </div>
</div>

<!-- Import Modal -->
<div class="modal fade modal-modern" id="importModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-semibold"><i class="bi bi-upload me-2"></i>Import Certificate</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 pb-4">
                <form id="importForm">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Import as</label>
                        <select class="form-select form-modern" id="importType" onchange="toggleImportType()">
                            <option value="0">SSL Certificate</option>
                            <option value="1">Certificate Authority (CA)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-modern" id="importCertName" placeholder="my_cert" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Certificate (CRT/PEM) <span class="text-danger">*</span></label>
                        <textarea class="form-control form-modern" id="certContent" rows="6" placeholder="-----BEGIN CERTIFICATE-----..."></textarea>
                    </div>
                    <div class="mb-3" id="keyField">
                        <label class="form-label fw-semibold">Private Key (optional)</label>
                        <textarea class="form-control form-modern" id="keyContent" rows="4" placeholder="-----BEGIN PRIVATE KEY-----..."></textarea>
                    </div>
                    <div class="mb-3" id="chainField">
                        <label class="form-label fw-semibold">Chain/CA Bundle (optional)</label>
                        <textarea class="form-control form-modern" id="chainContent" rows="4" placeholder="-----BEGIN CERTIFICATE-----..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Comment</label>
                        <textarea class="form-control form-modern" id="importComment" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 pb-4 px-4">
                <button type="button" class="btn btn-light rounded-3 px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn-primary-custom" onclick="importCertificate()">Import</button>
            </div>
        </div>
    </div>
</div>

<!-- Sign CSR Modal -->
<div class="modal fade modal-modern" id="signCSRModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-semibold"><i class="bi bi-file-earmark-check me-2"></i>Sign CSR with CA</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 pb-4">
                <form id="signCSRForm">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Certificate Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-modern" id="signCertName" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Signing CA <span class="text-danger">*</span></label>
                        <select class="form-select form-modern" id="signCA" required></select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">CSR Content <span class="text-danger">*</span></label>
                        <textarea class="form-control form-modern" id="csrContent" rows="8" placeholder="-----BEGIN CERTIFICATE REQUEST-----..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Valid Days</label>
                        <input type="number" class="form-control form-modern" id="signDays" value="365">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Comment</label>
                        <textarea class="form-control form-modern" id="signComment" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 pb-4 px-4">
                <button type="button" class="btn btn-light rounded-3 px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn-primary-custom" onclick="signCSR()">Sign</button>
            </div>
        </div>
    </div>
</div>

<!-- Certificate Details Modal -->
<div class="modal fade modal-modern" id="detailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-semibold"><i class="bi bi-info-circle me-2"></i>Certificate Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 pb-4" id="detailsContent">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary"></div>
                </div>
            </div>
            <div class="modal-footer border-0 pb-4 px-4">
                <button type="button" class="btn btn-light rounded-3 px-4" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-danger rounded-3 px-4" id="deleteCertBtn" style="display: none;" onclick="deleteCurrentCertificate()">Delete</button>
            </div>
        </div>
    </div>
</div>

<!-- CA Details Modal -->
<div class="modal fade modal-modern" id="caDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pt-4 px-4" style="background: #af52de10;">
                <h5 class="modal-title fw-semibold"><i class="bi bi-building me-2" style="color: #af52de;"></i>CA Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 pb-4" id="caDetailsContent">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary"></div>
                </div>
            </div>
            <div class="modal-footer border-0 pb-4 px-4">
                <button type="button" class="btn btn-light rounded-3 px-4" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-danger rounded-3 px-4" id="deleteCaBtn" onclick="deleteCurrentCA()">Delete CA</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Comment Modal -->
<div class="modal fade modal-modern" id="editCommentModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-semibold"><i class="bi bi-chat-text me-2"></i>Edit Comment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 pb-4">
                <textarea class="form-control form-modern" id="commentText" rows="4" placeholder="Add a comment..."></textarea>
                <input type="hidden" id="commentName">
                <input type="hidden" id="commentType">
            </div>
            <div class="modal-footer border-0 pb-4 px-4">
                <button type="button" class="btn btn-light rounded-3 px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn-primary-custom" onclick="saveComment()">Save</button>
            </div>
        </div>
    </div>
</div>

<script src="lib/jquery-3.6.0-master/dist/jquery.min.js"></script>
<script src="lib/bootstrap-5.3.8-dist/js/bootstrap.bundle.min.js"></script>
<script src="js/loader.js"></script>

<script>
// ========== Configuration ==========
const API_BASE = window.apiConfig.apiBaseUrl || '/api/';
const API_KEY = window.apiConfig.apiKey || '';
const IS_LOCALHOST = window.apiConfig.isLocalhost || false;
let currentView = localStorage.getItem('sslView') || 'grid';
let currentFilter = 'all';
let currentCAFilter = 'all';
let currentCertificates = [];
let currentCAs = [];
let currentDetailsName = null;
let currentDetailsType = 'cert';
let generatedCSRContent = '';
let generatedPrivateKeyContent = '';

// ========== Utility Functions ==========
function showAlert(message, type = 'success') {
    const alertHtml = `<div class="alert alert-${type} alert-dismissible fade show" role="alert">
        <i class="fas fa-${type === 'success' ? 'check-circle' : (type === 'danger' ? 'exclamation-triangle' : 'info-circle')} me-2"></i>
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>`;
    $('#alertContainer').append(alertHtml);
    setTimeout(() => $('.alert').fadeOut(500, function() { $(this).remove(); }), 5000);
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

function escapeHtml(text) {
    if (!text) return '';
    return String(text).replace(/[&<>]/g, function(m) {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>') return '&gt;';
        return m;
    });
}

function copyToClipboard(elementId) {
    const text = document.getElementById(elementId).innerText;
    navigator.clipboard.writeText(text);
    showAlert('Copied to clipboard!', 'success');
}

// ========== API Calls ==========
async function apiCall(action, method = 'GET', data = null) {
    let url = `${API_BASE}ssl_api.php?action=${action}`;
    let options = { 
        method: method, 
        headers: {
            'X-API-Key': API_KEY
        }
    };
    
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
        return { success: false, error: e.message };
    }
}

//loading

async function downloadFile(name, type, certType = 'cert') {
    let url = `${API_BASE}ssl_api.php?action=download&name=${encodeURIComponent(name)}&type=${type}&certType=${certType}`;
    
    if (!IS_LOCALHOST && API_KEY) {
        url += `&api_key=${encodeURIComponent(API_KEY)}`;
    }
    
    window.open(url, '_blank');
}

async function exportCertificate(name, type) {
    let url = `${API_BASE}ssl_api.php?action=export&name=${encodeURIComponent(name)}&type=${type}`;
    
    if (!IS_LOCALHOST && API_KEY) {
        url += `&api_key=${encodeURIComponent(API_KEY)}`;
    }
    
    window.open(url, '_blank');
}

function exportCertificateHandler(name, type) {
    exportCertificate(name, type);
}


// ========== Load Data ==========
async function loadCertificates() {
    showLoading();
    let result = await apiCall('list');
    hideLoading();
    
    if (result.success) {
        currentCertificates = result.certificates || [];
        currentCAs = result.cas || [];
        renderCertificates();
        renderCAs();
        loadStats();
        populateDropdowns();
    } else {
        $('#certsContainer').html(`<div class="empty-state">
            <i class="bi bi-shield-slash" style="font-size: 48px; color: #c6c6c8;"></i>
            <h5>Error Loading Certificates</h5>
            <p class="text-muted">${escapeHtml(result.error || 'Unknown error')}</p>
        </div>`);
        showAlert(result.error || 'Error loading certificates', 'danger');
    }
}

async function loadStats() {
    let result = await apiCall('stats');
    if (result.success && result.stats) {
        $('#statTotal').text(result.stats.total);
        $('#statValid').text(result.stats.valid);
        $('#statExpiring').text(result.stats.expiring_soon);
        $('#statTotalCAs').text(result.stats.total_cas);
    }
}

function getCertStatus(cert) {
    if (cert.revoked) return 'revoked';
    if (!cert.is_valid) return 'expired';
    if (cert.days_left <= 30 && cert.days_left > 0) return 'expiring';
    return 'valid';
}

function renderCertificates() {
    let filtered = [...currentCertificates];
    
    if (currentFilter === 'valid') {
        filtered = filtered.filter(c => getCertStatus(c) === 'valid');
    } else if (currentFilter === 'expiring') {
        filtered = filtered.filter(c => getCertStatus(c) === 'expiring');
    } else if (currentFilter === 'expired') {
        filtered = filtered.filter(c => getCertStatus(c) === 'expired');
    } else if (currentFilter === 'revoked') {
        filtered = filtered.filter(c => getCertStatus(c) === 'revoked');
    }
    
    const searchTerm = $('#searchInput').val().toLowerCase();
    if (searchTerm) {
        filtered = filtered.filter(c => 
            c.name.toLowerCase().includes(searchTerm) || 
            (c.subject || '').toLowerCase().includes(searchTerm)
        );
    }
    
    if (filtered.length === 0) {
        $('#certsContainer').html(`<div class="empty-state">
            <i class="bi bi-shield-slash" style="font-size: 48px; color: #c6c6c8;"></i>
            <h5>No Certificates Found</h5>
            <p class="text-muted">Create a new certificate or import an existing one</p>
            <button class="btn-primary-custom mt-3" onclick="openCreateSelfSignedModal()">Create Certificate</button>
        </div>`);
        return;
    }
    
    if (currentView === 'grid') {
        renderCertGridView(filtered);
    } else {
        renderCertTableView(filtered);
    }
}

function renderCertGridView(certs) {
    let html = '<div class="cert-grid" style="overflow: visible;">';
    certs.forEach(cert => {
        const status = getCertStatus(cert);
        let statusText = '';
        switch(status) {
            case 'valid': statusText = 'Valid'; break;
            case 'expiring': statusText = `Expires in ${cert.days_left} days`; break;
            case 'expired': statusText = 'Expired'; break;
            case 'revoked': statusText = 'Revoked'; break;
        }
        
        let sourceBadge = '';
        if (cert.source === 'ca_signed') sourceBadge = '<span class="badge-purple">CA Signed</span>';
        else if (cert.source === 'self_signed') sourceBadge = '<span class="badge bg-secondary text-white ms-2" style="font-size: 10px;">Self-Signed</span>';
        else if (cert.source === 'imported') sourceBadge = '<span class="badge bg-info text-white ms-2" style="font-size: 10px;">Imported</span>';
        
        let commentHtml = cert.comment ? `<div class="comment-text"><i class="bi bi-chat-text me-1"></i>${escapeHtml(cert.comment.substring(0, 80))}${cert.comment.length > 80 ? '...' : ''}</div>` : '';
        
        let crtUrl = `${API_BASE}ssl_api.php?action=download&name=${encodeURIComponent(cert.name)}&type=crt`;
        let keyUrl = `${API_BASE}ssl_api.php?action=download&name=${encodeURIComponent(cert.name)}&type=key`;
        let chainUrl = `${API_BASE}ssl_api.php?action=download&name=${encodeURIComponent(cert.name)}&type=chain`;
        let fullchainUrl = `${API_BASE}ssl_api.php?action=download&name=${encodeURIComponent(cert.name)}&type=fullchain`;
        let exportUrl = `${API_BASE}ssl_api.php?action=export&name=${encodeURIComponent(cert.name)}&type=cert`;
        
        if (!IS_LOCALHOST && API_KEY) {
            crtUrl += `&api_key=${encodeURIComponent(API_KEY)}`;
            keyUrl += `&api_key=${encodeURIComponent(API_KEY)}`;
            chainUrl += `&api_key=${encodeURIComponent(API_KEY)}`;
            fullchainUrl += `&api_key=${encodeURIComponent(API_KEY)}`;
            exportUrl += `&api_key=${encodeURIComponent(API_KEY)}`;
        }
        
        const dropdownId = `dropdown_${cert.name.replace(/[^a-zA-Z0-9]/g, '_')}`;
        
        html += `<div class="cert-card ${status}" style="overflow: visible; position: relative;">
            <div class="cert-card-header">
                <div>
                    <div class="cert-name">${escapeHtml(cert.name)} ${sourceBadge}</div>
                    <div class="cert-domain"><i class="bi bi-globe2 me-1"></i>${escapeHtml(cert.subject || 'Unknown')}</div>
                </div>
                <div class="dropdown" style="position: static;">
                    <button class="btn-icon-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="true" aria-expanded="false">
                        <i class="bi bi-three-dots-vertical"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" style="position: absolute; z-index: 1060;">
                        <li><a class="dropdown-item" href="#" onclick="showDetails('${escapeHtml(cert.name)}', 'cert'); return false;"><i class="bi bi-eye"></i> View Details</a></li>
                        <li><a class="dropdown-item" href="#" onclick="editComment('${escapeHtml(cert.name)}', 'cert', '${escapeHtml(cert.comment || '')}'); return false;"><i class="bi bi-chat-text"></i> Edit Comment</a></li>
                        <li><a class="dropdown-item" href="#" onclick="exportCertificate('${escapeHtml(cert.name)}', 'cert'); return false;"><i class="bi bi-box-arrow-up-right"></i> Export Bundle</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="${crtUrl}" target="_blank"><i class="bi bi-file-lock"></i> Download CRT</a></li>
                        ${cert.has_key ? `<li><a class="dropdown-item" href="${keyUrl}" target="_blank"><i class="bi bi-key"></i> Download KEY</a></li>` : ''}
                        ${cert.has_chain ? `<li><a class="dropdown-item" href="${chainUrl}" target="_blank"><i class="bi bi-link"></i> Download Chain</a></li>` : ''}
                        ${cert.has_fullchain ? `<li><a class="dropdown-item" href="${fullchainUrl}" target="_blank"><i class="bi bi-stack"></i> Download Fullchain</a></li>` : ''}
                        ${status !== 'revoked' && cert.is_valid ? `<li><hr class="dropdown-divider"><li><a class="dropdown-item text-warning" href="#" onclick="revokeCertificate('${escapeHtml(cert.name)}'); return false;"><i class="bi bi-x-octagon"></i> Revoke</a></li>` : ''}
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="#" onclick="deleteCertificate('${escapeHtml(cert.name)}', 'cert'); return false;"><i class="bi bi-trash"></i> Delete</a></li>
                        <li><a class="dropdown-item text-danger" href="#" onclick="forceDeleteCertificate('${escapeHtml(cert.name)}', 'cert'); return false;"><i class="bi bi-trash3"></i> Force Delete</a></li>
                    </ul>
                </div>
            </div>
            <div class="cert-card-body">
                <div class="text-center mb-3">
                    <div class="days-left">${cert.days_left > 0 ? cert.days_left : 0}</div>
                    <div class="days-label">days remaining</div>
                </div>
                <div class="small text-muted">
                    <i class="bi bi-calendar me-1"></i> Expires: ${cert.valid_to?.slice(0, 10) || 'Unknown'}
                </div>
                ${commentHtml}
            </div>
            <div class="cert-card-footer">
                <i class="bi bi-clock me-1"></i> ${cert.modified || 'Unknown'}
                <span class="status-badge status-${status} float-end">
                    ${status === 'valid' ? '<i class="bi bi-check-circle-fill"></i>' : 
                      (status === 'expiring' ? '<i class="bi bi-exclamation-triangle-fill"></i>' : 
                       (status === 'revoked' ? '<i class="bi bi-slash-circle"></i>' : '<i class="bi bi-x-circle-fill"></i>'))}
                    ${statusText}
                </span>
            </div>
        </div>`;
    });
    html += '</div>';
    $('#certsContainer').html(html);
    
    $('.dropdown-toggle').dropdown();
}

function renderCertTableView(certs) {
    let html = `<div class="table-responsive"><table class="table table-hover bg-white rounded-4 overflow-hidden">
        <thead class="bg-light"><tr><th>Name</th><th>Domain</th><th>Source</th><th>Status</th><th>Days Left</th><th>Expires</th><th>Actions</th></tr></thead><tbody>`;
    certs.forEach(cert => {
        const status = getCertStatus(cert);
        let statusText = status.charAt(0).toUpperCase() + status.slice(1);
        if (status === 'expiring') statusText = `Expiring (${cert.days_left}d)`;
        let sourceText = cert.source === 'ca_signed' ? 'CA Signed' : (cert.source === 'imported' ? 'Imported' : 'Self-Signed');
        
        let crtUrl = `${API_BASE}ssl_api.php?action=download&name=${encodeURIComponent(cert.name)}&type=crt`;
        let keyUrl = `${API_BASE}ssl_api.php?action=download&name=${encodeURIComponent(cert.name)}&type=key`;
        
        if (!IS_LOCALHOST && API_KEY) {
            crtUrl += `&api_key=${encodeURIComponent(API_KEY)}`;
            keyUrl += `&api_key=${encodeURIComponent(API_KEY)}`;
        }
        
        html += `<tr>
            <td><strong>${escapeHtml(cert.name)}</strong>${cert.revoked ? ' <span class="badge bg-secondary">Revoked</span>' : ''}</td>
            <td><code>${escapeHtml(cert.subject || 'Unknown')}</code></td>
            <td><span class="badge bg-secondary">${sourceText}</span></td>
            <td><span class="status-badge status-${status}">${statusText}</span></td>
            <td>${cert.days_left > 0 ? cert.days_left : 0}</td>
            <td>${cert.valid_to?.slice(0, 10) || 'Unknown'}</td>
            <td>
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown"><i class="bi bi-three-dots"></i></button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="#" onclick="showDetails('${escapeHtml(cert.name)}', 'cert')"><i class="bi bi-eye"></i> View Details</a></li>
                        <li><a class="dropdown-item" href="#" onclick="editComment('${escapeHtml(cert.name)}', 'cert', '${escapeHtml(cert.comment || '')}')"><i class="bi bi-chat-text"></i> Edit Comment</a></li>
                        <li><a class="dropdown-item" href="${crtUrl}" target="_blank"><i class="bi bi-download"></i> Download CRT</a></li>
                        ${cert.has_key ? `<li><a class="dropdown-item" href="${keyUrl}" target="_blank"><i class="bi bi-key"></i> Download KEY</a></li>` : ''}
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="#" onclick="deleteCertificate('${escapeHtml(cert.name)}', 'cert')"><i class="bi bi-trash"></i> Delete</a></li>
                    </ul>
                </div>
            </td>
        </tr>`;
    });
    html += '</tbody></table></div>';
    $('#certsContainer').html(html);
}

function renderCAs() {
    let filtered = [...currentCAs];
    
    const searchTerm = $('#caSearchInput').val().toLowerCase();
    if (searchTerm) {
        filtered = filtered.filter(ca => 
            ca.name.toLowerCase().includes(searchTerm) || 
            (ca.subject || '').toLowerCase().includes(searchTerm)
        );
    }
    
    if (currentCAFilter === 'root') {
        filtered = filtered.filter(ca => ca.ca_type === 'root' || ca.is_root);
    } else if (currentCAFilter === 'intermediate') {
        filtered = filtered.filter(ca => ca.ca_type === 'intermediate' || (!ca.is_root && ca.ca_type !== 'root'));
    }
    
    if (filtered.length === 0) {
        $('#casContainer').html(`<div class="empty-state">
            <i class="bi bi-building" style="font-size: 48px; color: #c6c6c8;"></i>
            <h5>No CAs Found</h5>
            <p class="text-muted">Create a Root CA to start signing certificates</p>
            <button class="btn-primary-custom mt-3" onclick="openCreateRootCAModal()">Create Root CA</button>
        </div>`);
        return;
    }
    
    let html = '<div>';
    filtered.forEach(ca => {
        let typeClass = (ca.ca_type === 'root' || ca.is_root) ? 'root' : 'intermediate';
        let typeIcon = typeClass === 'root' ? 'bi-building' : 'bi-diagram-3';
        let typeColor = typeClass === 'root' ? '#af52de' : '#5e5ce0';
        let statusBadge = ca.is_valid ? '<span class="badge bg-success ms-2">Valid</span>' : '<span class="badge bg-danger ms-2">Expired</span>';
        let commentHtml = ca.comment ? `<div class="comment-text mt-2"><i class="bi bi-chat-text me-1"></i>${escapeHtml(ca.comment)}</div>` : '';
        
        let exportUrl = `${API_BASE}ssl_api.php?action=export&name=${encodeURIComponent(ca.name)}&type=ca`;
        if (!IS_LOCALHOST && API_KEY) {
            exportUrl += `&api_key=${encodeURIComponent(API_KEY)}`;
        }
        
        html += `<div class="ca-card ${typeClass}" onclick="showCADetails('${escapeHtml(ca.name)}')">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <i class="bi ${typeIcon}" style="color: ${typeColor}; font-size: 20px;"></i>
                    <strong class="ms-2">${escapeHtml(ca.name)}</strong>
                    ${statusBadge}
                    <div class="small text-muted mt-1">${escapeHtml(ca.subject || 'Unknown')}</div>
                </div>
                <div class="dropdown">
                    <button class="btn-icon-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" onclick="event.stopPropagation()">
                        <i class="bi bi-three-dots-vertical"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="#" onclick="event.stopPropagation(); showCADetails('${escapeHtml(ca.name)}')"><i class="bi bi-eye"></i> View Details</a></li>
                        <li><a class="dropdown-item" href="#" onclick="event.stopPropagation(); editComment('${escapeHtml(ca.name)}', 'ca', '${escapeHtml(ca.comment || '')}')"><i class="bi bi-chat-text"></i> Edit Comment</a></li>
                        <li><a class="dropdown-item" href="#" onclick="event.stopPropagation(); exportCertificate('${escapeHtml(ca.name)}', 'ca')"><i class="bi bi-box-arrow-up-right"></i> Export Bundle</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="#" onclick="event.stopPropagation(); deleteCertificate('${escapeHtml(ca.name)}', 'ca')"><i class="bi bi-trash"></i> Delete CA</a></li>
                    </ul>
                </div>
            </div>
            <div class="small text-muted mt-2">
                <i class="bi bi-calendar me-1"></i> Expires: ${ca.valid_to?.slice(0, 10) || 'Unknown'} (${ca.days_left > 0 ? ca.days_left + ' days left' : 'Expired'})
            </div>
            ${commentHtml}
        </div>`;
    });
    html += '</div>';
    $('#casContainer').html(html);
}

function populateDropdowns() {
    let intermediateOptions = '<option value="">Select Intermediate CA...</option>';
    let rootOptions = '<option value="">Select Root CA...</option>';
    let allOptions = '<option value="">Select CA...</option>';
    
    currentCAs.forEach(ca => {
        allOptions += `<option value="${escapeHtml(ca.name)}">${escapeHtml(ca.name)} (${escapeHtml(ca.subject)})</option>`;
        if (ca.ca_type === 'intermediate' || (!ca.is_root && ca.ca_type !== 'root')) {
            intermediateOptions += `<option value="${escapeHtml(ca.name)}">${escapeHtml(ca.name)} (${escapeHtml(ca.subject)})</option>`;
        }
        if (ca.is_root || ca.ca_type === 'root') {
            rootOptions += `<option value="${escapeHtml(ca.name)}">${escapeHtml(ca.name)} (${escapeHtml(ca.subject)})</option>`;
        }
    });
    
    $('#caName').html(intermediateOptions);
    $('#rootCAName').html(rootOptions);
    $('#signCA').html(allOptions);
}

// ========== Tab Switching ==========
function switchTab(tab) {
    $('.custom-tab').removeClass('active');
    $(`.custom-tab[data-tab="${tab}"]`).addClass('active');
    
    if (tab === 'certs') {
        $('#certsTab').show();
        $('#casTab').hide();
        renderCertificates();
    } else {
        $('#certsTab').hide();
        $('#casTab').show();
        renderCAs();
    }
}

function setView(view) {
    currentView = view;
    localStorage.setItem('sslView', view);
    $('.view-toggle button').removeClass('active-view').css({background: '', color: ''});
    $(`.view-toggle button[data-view="${view}"]`).addClass('active-view').css({background: '#007aff', color: 'white'});
    renderCertificates();
}

function applyFilter(filter) {
    currentFilter = filter;
    $('.filter-chip[data-filter]').removeClass('active');
    $(`.filter-chip[data-filter="${filter}"]`).addClass('active');
    renderCertificates();
}

function applyCAFilter(filter) {
    currentCAFilter = filter;
    $('.filter-chip[data-ca-filter]').removeClass('active');
    $(`.filter-chip[data-ca-filter="${filter}"]`).addClass('active');
    renderCAs();
}

function filterCerts() { renderCertificates(); }
function filterCAs() { renderCAs(); }
function refreshAllData() { loadCertificates(); }

// ========== Modal Handlers ==========
function openCreateSelfSignedModal() {
    $('#selfSignedForm')[0].reset();
    new bootstrap.Modal(document.getElementById('createSelfSignedModal')).show();
}

function openCreateSignedModal() {
    if (currentCAs.filter(ca => ca.ca_type === 'intermediate' || (!ca.is_root && ca.ca_type !== 'root')).length === 0) {
        showAlert('Please create an Intermediate CA first', 'warning');
        return;
    }
    $('#signedForm')[0].reset();
    new bootstrap.Modal(document.getElementById('createSignedModal')).show();
}

function openCreateRootCAModal() {
    $('#rootCAForm')[0].reset();
    new bootstrap.Modal(document.getElementById('createRootCAModal')).show();
}

function openCreateIntermediateCAModal() {
    if (currentCAs.filter(ca => ca.is_root || ca.ca_type === 'root').length === 0) {
        showAlert('Please create a Root CA first', 'warning');
        return;
    }
    $('#intermediateCAForm')[0].reset();
    new bootstrap.Modal(document.getElementById('createIntermediateCAModal')).show();
}

function openGenerateCSRModal() {
    $('#csrForm')[0].reset();
    $('#csrResult').hide();
    new bootstrap.Modal(document.getElementById('generateCSRModal')).show();
}

function openImportModal() {
    $('#importForm')[0].reset();
    toggleImportType();
    new bootstrap.Modal(document.getElementById('importModal')).show();
}

function toggleImportType() {
    const isCA = $('#importType').val() === '1';
    if (isCA) {
        $('#keyField').hide();
        $('#chainField').hide();
    } else {
        $('#keyField').show();
        $('#chainField').show();
    }
}

// ========== CRUD Operations ==========
async function createSelfSigned() {
    const certName = $('#certName').val().trim();
    const domain = $('#domain').val().trim();
    if (!certName || !domain) { showAlert('Name and domain are required', 'danger'); return; }
    if (!/^[a-zA-Z0-9_-]+$/.test(certName)) { showAlert('Use only letters, numbers, underscore and hyphen', 'danger'); return; }
    
    showLoading();
    let result = await apiCall('create', 'POST', {
        certName: certName, domain: domain, sans: $('#sans').val(),
        days: $('#days').val(), keySize: $('#keySize').val(),
        signatureAlgo: $('#signatureAlgo').val(),
        country: 'US', state: 'California', city: 'San Francisco',
        org: 'Mini-B', orgUnit: 'IT', email: 'admin@localhost',
        comment: $('#createComment').val()
    });
    hideLoading();
    
    if (result.success) {
        bootstrap.Modal.getInstance(document.getElementById('createSelfSignedModal')).hide();
        showAlert(result.message, 'success');
        loadCertificates();
    } else {
        showAlert(result.error || 'Error creating certificate', 'danger');
    }
}

async function createSignedCertificate() {
    const certName = $('#signedCertName').val().trim();
    const caName = $('#caName').val();
    const domain = $('#signedDomain').val().trim();
    
    if (!certName || !caName || !domain) {
        showAlert('Certificate name, CA and domain are required', 'danger');
        return;
    }
    
    showLoading();
    let result = await apiCall('create_signed', 'POST', {
        certName: certName, caName: caName, domain: domain,
        sans: $('#signedSans').val(), days: $('#signedDays').val(),
        keySize: $('#signedKeySize').val(), signatureAlgo: $('#signedSignatureAlgo').val(),
        comment: $('#signedComment').val(),
        country: 'US', state: 'California', city: 'San Francisco',
        org: 'Mini-B', orgUnit: 'IT', email: 'admin@localhost'
    });
    hideLoading();
    
    if (result.success) {
        bootstrap.Modal.getInstance(document.getElementById('createSignedModal')).hide();
        showAlert(result.message, 'success');
        loadCertificates();
    } else {
        showAlert(result.error || 'Error creating certificate', 'danger');
    }
}

async function createRootCA() {
    const caName = $('#rootCaName').val().trim();
    if (!caName) { showAlert('CA name is required', 'danger'); return; }
    
    showLoading();
    let result = await apiCall('create_ca', 'POST', {
        caName: caName, cn: $('#rootCaCn').val(), days: $('#rootCaDays').val(),
        keySize: $('#rootCaKeySize').val(), signatureAlgo: $('#rootCaSignatureAlgo').val(),
        comment: $('#rootCaComment').val(),
        country: 'US', state: 'California', city: 'San Francisco',
        org: 'Mini-B CA', orgUnit: 'Certificate Authority', email: 'ca@localhost'
    });
    hideLoading();
    
    if (result.success) {
        bootstrap.Modal.getInstance(document.getElementById('createRootCAModal')).hide();
        showAlert(result.message, 'success');
        loadCertificates();
    } else {
        showAlert(result.error || 'Error creating CA', 'danger');
    }
}

async function createIntermediateCA() {
    const caName = $('#intCaName').val().trim();
    const rootCAName = $('#rootCAName').val();
    if (!caName || !rootCAName) { showAlert('CA name and Root CA are required', 'danger'); return; }
    
    showLoading();
    let result = await apiCall('create_intermediate_ca', 'POST', {
        caName: caName, rootCAName: rootCAName, cn: $('#intCaCn').val(),
        days: $('#intCaDays').val(), keySize: $('#intCaKeySize').val(),
        signatureAlgo: $('#intCaSignatureAlgo').val(),
        comment: $('#intCaComment').val(),
        country: 'US', state: 'California', city: 'San Francisco',
        org: 'Mini-B Sub CA', orgUnit: 'Subordinate CA', email: 'ca@localhost'
    });
    hideLoading();
    
    if (result.success) {
        bootstrap.Modal.getInstance(document.getElementById('createIntermediateCAModal')).hide();
        showAlert(result.message, 'success');
        loadCertificates();
    } else {
        showAlert(result.error || 'Error creating intermediate CA', 'danger');
    }
}

async function generateCSR() {
    const csrName = $('#csrName').val().trim();
    if (!csrName) { showAlert('CSR name is required', 'danger'); return; }
    
    showLoading();
    let result = await apiCall('generate_csr', 'POST', {
        csrName: csrName, domain: $('#csrDomain').val(), sans: $('#csrSans').val(),
        keySize: $('#csrKeySize').val(),
        country: 'US', state: 'California', city: 'San Francisco',
        org: 'Mini-B', orgUnit: 'IT', email: 'admin@localhost'
    });
    hideLoading();
    
    if (result.success) {
        generatedCSRContent = result.csr;
        generatedPrivateKeyContent = result.private_key;
        $('#csrOutput').text(result.csr);
        $('#keyOutput').text(result.private_key);
        $('#csrResult').show();
        showAlert('CSR generated successfully!', 'success');
    } else {
        showAlert(result.error || 'Error generating CSR', 'danger');
    }
}

function openSignCSRFromCSR() {
    if (generatedCSRContent) {
        $('#signCertName').val($('#csrName').val() + '_signed');
        $('#csrContent').val(generatedCSRContent);
        bootstrap.Modal.getInstance(document.getElementById('generateCSRModal')).hide();
        new bootstrap.Modal(document.getElementById('signCSRModal')).show();
    }
}

async function signCSR() {
    const certName = $('#signCertName').val().trim();
    const caName = $('#signCA').val();
    const csrContent = $('#csrContent').val().trim();
    const days = $('#signDays').val();
    const comment = $('#signComment').val();
    
    if (!certName || !caName || !csrContent) {
        showAlert('Certificate name, CA and CSR content are required', 'danger');
        return;
    }
    
    showLoading();
    let result = await apiCall('sign_csr', 'POST', {
        certName: certName, caName: caName, csrContent: csrContent,
        days: days, comment: comment
    });
    hideLoading();
    
    if (result.success) {
        bootstrap.Modal.getInstance(document.getElementById('signCSRModal')).hide();
        showAlert(result.message, 'success');
        loadCertificates();
    } else {
        showAlert(result.error || 'Error signing CSR', 'danger');
    }
}

async function importCertificate() {
    const certName = $('#importCertName').val().trim();
    const certContent = $('#certContent').val().trim();
    if (!certName || !certContent) {
        showAlert('Name and certificate content are required', 'danger');
        return;
    }
    
    showLoading();
    let result = await apiCall('import', 'POST', {
        certName: certName, certContent: certContent,
        keyContent: $('#keyContent').val(), chainContent: $('#chainContent').val(),
        isCA: $('#importType').val(), comment: $('#importComment').val()
    });
    hideLoading();
    
    if (result.success) {
        bootstrap.Modal.getInstance(document.getElementById('importModal')).hide();
        showAlert(result.message, 'success');
        loadCertificates();
    } else {
        showAlert(result.error || 'Error importing certificate', 'danger');
    }
}

async function revokeCertificate(certName) {
    if (!confirm(`Revoke certificate "${certName}"? This cannot be undone.`)) return;
    showLoading();
    let result = await apiCall('revoke', 'POST', { name: certName });
    hideLoading();
    if (result.success) {
        showAlert(result.message, 'success');
        loadCertificates();
    } else {
        showAlert(result.error || 'Error revoking certificate', 'danger');
    }
}

function exportCertificate(certName, type) {
    let url = `${API_BASE}ssl_api.php?action=export&name=${encodeURIComponent(certName)}&type=${type}`;
    
    if (!IS_LOCALHOST && API_KEY) {
        url += `&api_key=${encodeURIComponent(API_KEY)}`;
    }
    
    window.open(url, '_blank');
}

function downloadFile(name, fileType, certType = 'cert') {
    let url = `${API_BASE}ssl_api.php?action=download&name=${encodeURIComponent(name)}&type=${fileType}&certType=${certType}`;
    
    if (!IS_LOCALHOST && API_KEY) {
        url += `&api_key=${encodeURIComponent(API_KEY)}`;
    }
    
    window.open(url, '_blank');
}

async function deleteCertificate(certName, type) {
    if (!confirm(`Delete ${type === 'ca' ? 'CA' : 'certificate'} "${certName}"? This will first revoke it (if not already revoked) and then permanently delete all files. This cannot be undone.`)) return;
    
    showLoading();
    
    try {
        if (type !== 'ca') {
            let checkResult = await apiCall('details', 'GET', { name: certName, type: 'cert' });
            
            if (checkResult.success && checkResult.certificate) {
                const cert = checkResult.certificate;
                
                if (!cert.revoked) {
                    console.log(`Revoking certificate "${certName}" before deletion...`);
                    let revokeResult = await apiCall('revoke', 'POST', { name: certName });
                    
                    if (!revokeResult.success) {
                        console.warn('Revoke failed:', revokeResult.error);
                        showAlert('Warning: Certificate could not be revoked, but will attempt deletion', 'warning');
                    } else {
                        showAlert('Certificate revoked successfully', 'success');
                    }
                }
            }
        }
        
        let result = await apiCall('delete', 'POST', { name: certName, type: type });
        
        hideLoading();
        
        if (result.success) {
            showAlert(result.message, 'success');
            loadCertificates(); // Refresh the list
        } else {
            showAlert(result.error || 'Error deleting', 'danger');
        }
    } catch (error) {
        hideLoading();
        showAlert('Error during deletion: ' + error.message, 'danger');
    }
}

async function forceDeleteCertificate(certName, type) {
    if (!confirm(`⚠️ FORCE DELETE: Are you absolutely sure you want to delete "${certName}"? This certificate may still be valid in production!`)) return;
    
    showLoading();
    let result = await apiCall('delete', 'POST', { name: certName, type: type, force: 1 });
    hideLoading();
    
    if (result.success) {
        showAlert(result.message, 'success');
        loadCertificates();
    } else {
        showAlert(result.error || 'Error deleting', 'danger');
    }
}

async function showDetails(certName, type = 'cert') {
    currentDetailsName = certName;
    currentDetailsType = type;
    showLoading();
    let result = await apiCall('details', 'GET', { name: certName, type: type });
    hideLoading();
    
    if (result.success && result.certificate) {
        const d = result.certificate;
        const statusText = d.is_valid ? 'Valid' : (d.revoked ? 'Revoked' : 'Expired');
        const statusClass = d.is_valid ? 'success' : (d.revoked ? 'secondary' : 'danger');
        
        let crtUrl = `${API_BASE}ssl_api.php?action=download&name=${encodeURIComponent(d.name)}&type=crt`;
        let keyUrl = `${API_BASE}ssl_api.php?action=download&name=${encodeURIComponent(d.name)}&type=key`;
        let chainUrl = `${API_BASE}ssl_api.php?action=download&name=${encodeURIComponent(d.name)}&type=chain`;
        let fullchainUrl = `${API_BASE}ssl_api.php?action=download&name=${encodeURIComponent(d.name)}&type=fullchain`;
        
        if (!IS_LOCALHOST && API_KEY) {
            crtUrl += `&api_key=${encodeURIComponent(API_KEY)}`;
            keyUrl += `&api_key=${encodeURIComponent(API_KEY)}`;
            chainUrl += `&api_key=${encodeURIComponent(API_KEY)}`;
            fullchainUrl += `&api_key=${encodeURIComponent(API_KEY)}`;
        }
        
        let html = `<div class="text-center mb-4">
            <div style="font-size: 48px;"><i class="bi bi-shield-check" style="color: #007aff;"></i></div>
            <h4>${escapeHtml(d.name)}</h4>
            <span class="badge bg-${statusClass}">${statusText}</span>
            ${d.source === 'ca_signed' ? '<span class="badge bg-purple ms-2">CA Signed</span>' : ''}
        </div>
        <div class="row">
            <div class="col-md-6">
                <div class="mb-3"><strong>Subject (CN):</strong><br><code>${escapeHtml(d.subject)}</code></div>
                <div class="mb-3"><strong>Issuer:</strong><br><code>${escapeHtml(d.issuer)}</code></div>
                <div class="mb-3"><strong>Serial Number:</strong><br><code>${escapeHtml(d.serial)}</code></div>
                ${d.san ? `<div class="mb-3"><strong>Subject Alternative Names:</strong><br><code>${escapeHtml(d.san.join(', '))}</code></div>` : ''}
            </div>
            <div class="col-md-6">
                <div class="mb-3"><strong>Valid From:</strong><br>${escapeHtml(d.valid_from)}</div>
                <div class="mb-3"><strong>Valid To:</strong><br>${escapeHtml(d.valid_to)}</div>
                <div class="mb-3"><strong>Days Left:</strong><br><span class="fw-bold">${d.days_left > 0 ? d.days_left : 0}</span> days</div>
                <div class="mb-3"><strong>Signature Algorithm:</strong><br>${escapeHtml(d.signature_algo)}</div>
            </div>
        </div>
        ${d.comment ? `<hr><div class="mb-3"><strong>Comment:</strong><br><div class="alert alert-light">${escapeHtml(d.comment)}</div></div>` : ''}
        <hr>
        <div class="d-flex gap-2 flex-wrap">
            <a href="${crtUrl}" class="btn btn-outline-primary rounded-3" target="_blank"><i class="bi bi-download"></i> CRT</a>
            ${d.has_key ? `<a href="${keyUrl}" class="btn btn-outline-secondary rounded-3" target="_blank"><i class="bi bi-key"></i> KEY</a>` : ''}
            ${d.has_chain ? `<a href="${chainUrl}" class="btn btn-outline-info rounded-3" target="_blank"><i class="bi bi-link"></i> Chain</a>` : ''}
            ${d.has_fullchain ? `<a href="${fullchainUrl}" class="btn btn-outline-success rounded-3" target="_blank"><i class="bi bi-stack"></i> Fullchain</a>` : ''}
            <button class="btn btn-outline-warning rounded-3" onclick="editComment('${escapeHtml(d.name)}', '${type}', '${escapeHtml(d.comment || '')}'); bootstrap.Modal.getInstance(document.getElementById('detailsModal')).hide();"><i class="bi bi-chat-text"></i> Edit Comment</button>
        </div>`;
        
        $('#detailsContent').html(html);
        $('#deleteCertBtn').show();
        new bootstrap.Modal(document.getElementById('detailsModal')).show();
    } else {
        showAlert(result.error || 'Error loading details', 'danger');
    }
}

async function showCADetails(caName) {
    currentDetailsName = caName;
    currentDetailsType = 'ca';
    showLoading();
    let result = await apiCall('details', 'GET', { name: caName, type: 'ca' });
    hideLoading();
    
    if (result.success && result.certificate) {
        const d = result.certificate;
        const isRoot = d.is_root;
        
        let crtUrl = `${API_BASE}ssl_api.php?action=download&name=${encodeURIComponent(d.name)}&type=crt&certType=ca`;
        let keyUrl = `${API_BASE}ssl_api.php?action=download&name=${encodeURIComponent(d.name)}&type=key&certType=ca`;
        
        if (!IS_LOCALHOST && API_KEY) {
            crtUrl += `&api_key=${encodeURIComponent(API_KEY)}`;
            keyUrl += `&api_key=${encodeURIComponent(API_KEY)}`;
        }
        
        let html = `<div class="text-center mb-4">
            <div style="font-size: 48px;"><i class="bi bi-building" style="color: #af52de;"></i></div>
            <h4>${escapeHtml(d.name)}</h4>
            <span class="badge ${isRoot ? 'bg-purple' : 'bg-info'}">${isRoot ? 'Root CA' : 'Intermediate CA'}</span>
            ${d.is_valid ? '<span class="badge bg-success ms-2">Valid</span>' : '<span class="badge bg-danger ms-2">Expired</span>'}
        </div>
        <div class="row">
            <div class="col-md-6">
                <div class="mb-3"><strong>Common Name (CN):</strong><br><code>${escapeHtml(d.subject)}</code></div>
                <div class="mb-3"><strong>Issuer:</strong><br><code>${escapeHtml(d.issuer)}</code></div>
                <div class="mb-3"><strong>Serial Number:</strong><br><code>${escapeHtml(d.serial)}</code></div>
            </div>
            <div class="col-md-6">
                <div class="mb-3"><strong>Valid From:</strong><br>${escapeHtml(d.valid_from)}</div>
                <div class="mb-3"><strong>Valid To:</strong><br>${escapeHtml(d.valid_to)}</div>
                <div class="mb-3"><strong>Days Left:</strong><br><span class="fw-bold">${d.days_left > 0 ? d.days_left : 0}</span> days</div>
            </div>
        </div>
        ${d.comment ? `<hr><div class="mb-3"><strong>Comment:</strong><br><div class="alert alert-light">${escapeHtml(d.comment)}</div></div>` : ''}
        <hr>
        <div class="d-flex gap-2 flex-wrap">
            <a href="${crtUrl}" class="btn btn-outline-primary rounded-3" target="_blank"><i class="bi bi-download"></i> CRT</a>
            ${d.has_key ? `<a href="${keyUrl}" class="btn btn-outline-secondary rounded-3" target="_blank"><i class="bi bi-key"></i> Private Key</a>` : ''}
            <button class="btn btn-outline-success rounded-3" onclick="openSignWithCA('${escapeHtml(d.name)}'); bootstrap.Modal.getInstance(document.getElementById('caDetailsModal')).hide();"><i class="bi bi-file-earmark-check"></i> Sign CSR</button>
        </div>`;
        
        $('#caDetailsContent').html(html);
        new bootstrap.Modal(document.getElementById('caDetailsModal')).show();
    } else {
        showAlert(result.error || 'Error loading CA details', 'danger');
    }
}

function openSignWithCA(caName) {
    $('#signCA').val(caName);
    $('#signCertName').val('');
    $('#csrContent').val('');
    new bootstrap.Modal(document.getElementById('signCSRModal')).show();
}

function deleteCurrentCertificate() {
    deleteCertificate(currentDetailsName, currentDetailsType);
    bootstrap.Modal.getInstance(document.getElementById('detailsModal')).hide();
}

function deleteCurrentCA() {
    deleteCertificate(currentDetailsName, 'ca');
    bootstrap.Modal.getInstance(document.getElementById('caDetailsModal')).hide();
}

// ========== Comment Functions ==========
function editComment(name, type, currentComment) {
    $('#commentName').val(name);
    $('#commentType').val(type);
    $('#commentText').val(currentComment || '');
    new bootstrap.Modal(document.getElementById('editCommentModal')).show();
}

async function saveComment() {
    const name = $('#commentName').val();
    const type = $('#commentType').val();
    const comment = $('#commentText').val();
    
    showLoading();
    let result = await apiCall('update_comment', 'POST', { name: name, type: type, comment: comment });
    hideLoading();
    
    if (result.success) {
        showAlert('Comment updated', 'success');
        bootstrap.Modal.getInstance(document.getElementById('editCommentModal')).hide();
        loadCertificates();
    } else {
        showAlert(result.error || 'Error updating comment', 'danger');
    }
}

// ========== Initialization ==========
$(document).ready(function() {
    setView(currentView);
    loadCertificates();
    setTimeout(() => $('#applePreloader').fadeOut(500), 500);
});
</script>
</body>
</html>