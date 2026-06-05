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

define('ROOT_PATH', dirname(dirname(__FILE__)));

if (file_exists(ROOT_PATH . '/config.php')) {
    require_once ROOT_PATH . '/config.php';
} else {
    die('Configuration file not found. Please ensure config.php exists in the parent directory.');
}

// Если установка уже выполнена
if ($status_install == "1") {
    if (isset($_GET['reconfigure']) && $_GET['reconfigure'] == '1') {
        isAuthenticated();
    } else {
        header('Location: ../index.php');
        exit;
    }
}

// Обработка выхода
if (isset($_POST['cancel_install'])) {
    $configPath = ROOT_PATH . '/config.php';
    if (file_exists($configPath) && is_writable($configPath)) {
        $configContent = file_get_contents($configPath);
        $configContent = preg_replace('/\$status_install\s*=\s*"0";/', '$status_install = "1";', $configContent);
        file_put_contents($configPath, $configContent);
    }
    
    header('Location: ' . ROOT_PATH . '/index.php');
    exit;
}

// Шаги мастера
$steps = [
    1 => 'Welcome',
    2 => 'System Check',
    3 => 'Host Configuration',
    4 => 'API Security',
    5 => 'Admin Account',
    6 => 'Ready to Launch'
];

$currentStep = isset($_GET['step']) ? (int)$_GET['step'] : 1;
if ($currentStep < 1 || $currentStep > 6) $currentStep = 1;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['install_data'])) {
    $_SESSION['install_data'] = [];
}

// Обработка POST запросов
$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Шаг 1: Проверка согласий
    if ($action === 'accept_terms' && $currentStep == 1) {
        $license_accepted = isset($_POST['license_accepted']) && $_POST['license_accepted'] == '1';
        $privacy_accepted = isset($_POST['privacy_accepted']) && $_POST['privacy_accepted'] == '1';
        
        if ($license_accepted && $privacy_accepted) {
            $_SESSION['install_data']['license_accepted'] = true;
            $_SESSION['install_data']['privacy_accepted'] = true;
            header('Location: ?step=2');
            exit;
        } else {
            $error = 'You must accept both the License Agreement and the Privacy Policy to continue.';
        }
    }
    
    // Шаг 3: Сохранение имени хоста
    if ($action === 'save_hostname' && $currentStep == 3) {
		$hostname = trim($_POST['hostname'] ?? '');
		if (!empty($hostname)) {
			$_SESSION['install_data']['hostname'] = $hostname;
			
			$result = setSystemHostname($hostname);
			
			if ($result) {
				header('Location: ?step=4');
				exit;
			} else {
				$error = 'Failed to set hostname. Please check write permissions for /etc/hosts and /etc/hostname';
			}
		} else {
			$error = 'Please enter a valid hostname';
		}
	}
    
    // Шаг 4: Генерация API ключа и серийного номера
    if ($action === 'generate_api' && $currentStep == 4) {
        $apiKey = bin2hex(random_bytes(32));
        $serialNumber = generateSerialNumber(32);
        
        $_SESSION['install_data']['api_key'] = $apiKey;
        $_SESSION['install_data']['serial_number'] = $serialNumber;
        
        header('Location: ?step=5');
        exit;
    }
    
    // Шаг 5: Создание администратора
    if ($action === 'create_admin' && $currentStep == 5) {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        $email = trim($_POST['email'] ?? '');
        
        if (empty($username)) {
            $error = 'Username is required';
        } elseif (strlen($password) < 4) {
            $error = 'Password must be at least 4 characters';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match';
        } else {
            $_SESSION['install_data']['admin_username'] = $username;
            $_SESSION['install_data']['admin_password'] = $password;
            $_SESSION['install_data']['admin_email'] = $email;
            
            header('Location: ?step=6');
            exit;
        }
    }
    
    // Шаг 6: Завершение установки
    if ($action === 'finalize' && $currentStep == 6) {
        $installSuccess = finalizeInstallation();
        
        if ($installSuccess) {
            $configPath = ROOT_PATH . '/config.php';
            if (file_exists($configPath) && is_writable($configPath)) {
                $configContent = file_get_contents($configPath);
                $configContent = preg_replace('/\$status_install\s*=\s*"0";/', '$status_install = "1";', $configContent);
                file_put_contents($configPath, $configContent);
            }
            
            unset($_SESSION['install_data']);
            header('Location: ../index.php');
            exit;
        } else {
            $error = 'Failed to finalize installation. Please check logs.';
        }
    }
}

// Функция для получения системного hostname
function getSystemHostname() {
    $hostname = php_uname('n');
    if (empty($hostname)) {
        $hostname = gethostname();
    }
    return $hostname ?: 'localhost';
}

// Функция для установки системного hostname
function setSystemHostname($hostname) {
    if (empty($hostname)) return false;
    
    $hostname = preg_replace('/[^a-zA-Z0-9\.\-]/', '', $hostname);
    if (empty($hostname)) return false;
    
    exec('sudo hostnamectl set-hostname ' . escapeshellarg($hostname));
    
    $newHosts = "127.0.0.1\tlocalhost\n";
    $newHosts .= "127.0.1.1\t" . $hostname . "\n";
    $newHosts .= "\n";
    $newHosts .= "# The following lines are desirable for IPv6 capable hosts\n";
    $newHosts .= "::1\tlocalhost ip6-localhost ip6-loopback\n";
    $newHosts .= "fe00::0\tip6-localnet\n";
    $newHosts .= "ff00::0\tip6-mcastprefix\n";
    $newHosts .= "ff02::1\tip6-allnodes\n";
    $newHosts .= "ff02::2\tip6-allrouters\n";
    
    $tempFile = '/tmp/hosts_' . uniqid();
    file_put_contents($tempFile, $newHosts);
    exec('sudo cp ' . $tempFile . ' /etc/hosts');
    unlink($tempFile);
    
    return true;
}

function generateSerialNumber($length = 32) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $serial = '';
    
    for ($i = 0; $i < $length; $i++) {
        $serial .= $characters[random_int(0, $charactersLength - 1)];
    }
    
    return $serial;
}

// Функция завершения установки
function finalizeInstallation() {
    try {
        $db = getDB();
        
        $db->exec('BEGIN TRANSACTION');
        
        $data = $_SESSION['install_data'];
        
        // 1. Устанавливаем системный hostname
        $newHostname = $data['hostname'] ?? getSystemHostname();
        setSystemHostname($newHostname);
        
        // 2. Обновляем host в таблице hosts
        $hostname = $newHostname . ' (LocalHost)';
        $apiKey = $data['api_key'] ?? bin2hex(random_bytes(32));
        $serialNumber = $data['serial_number'] ?? generateSerialNumber(32); // Получаем серийный номер
        $currentDate = date('Y-m-d H:i:s');
        
        // Проверяем, существует ли запись
        $check = $db->querySingle("SELECT COUNT(*) FROM hosts WHERE idHost = 1");
        
        if ($check == 0) {
            // Вставляем новую запись с HostSn
            $stmt = $db->prepare("INSERT INTO hosts (idHost, hostName, hostApiKey, HostSn, hostProto, hostIp, hostPort, hostApiPath, hostStatus, hostLive, hostDateApiUpdtae) 
                                  VALUES (1, :name, :key, :sn, 'http', 'localhost', '80', '/api/', 'active', '1', :date)");
            $stmt->bindValue(':name', $hostname, SQLITE3_TEXT);
            $stmt->bindValue(':key', $apiKey, SQLITE3_TEXT);
            $stmt->bindValue(':sn', $serialNumber, SQLITE3_TEXT);
            $stmt->bindValue(':date', $currentDate, SQLITE3_TEXT);
            $stmt->execute();
        } else {
            // Обновляем существующую запись, включая поле HostSn
            $stmt = $db->prepare("UPDATE hosts SET 
                                  hostName = :name, 
                                  hostApiKey = :key, 
                                  HostSn = :sn,
                                  hostDateApiUpdtae = :date 
                                  WHERE idHost = 1");
            $stmt->bindValue(':name', $hostname, SQLITE3_TEXT);
            $stmt->bindValue(':key', $apiKey, SQLITE3_TEXT);
            $stmt->bindValue(':sn', $serialNumber, SQLITE3_TEXT);
            $stmt->bindValue(':date', $currentDate, SQLITE3_TEXT);
            $stmt->execute();
        }
        
        // 3. Обновляем или создаем администратора в таблице users
        $hashedPassword = password_hash($data['admin_password'], PASSWORD_DEFAULT);
        $adminUser = $data['admin_username'] ?? 'admin';
        $adminEmail = $data['admin_email'] ?? '';
        
        // Проверяем существует ли пользователь
        $checkAdmin = $db->querySingle("SELECT COUNT(*) FROM users WHERE id = 1");
        
        if ($checkAdmin == 0) {
            // Вставляем нового пользователя
            $stmt = $db->prepare("INSERT INTO users (id, username, password, email, role, created_at) 
                                  VALUES (1, :username, :pass, :email, 'admin', datetime('now'))");
            $stmt->bindValue(':username', $adminUser, SQLITE3_TEXT);
            $stmt->bindValue(':pass', $hashedPassword, SQLITE3_TEXT);
            $stmt->bindValue(':email', $adminEmail, SQLITE3_TEXT);
            $stmt->execute();
        } else {
            // Обновляем существующего пользователя
            $stmt = $db->prepare("UPDATE users SET 
                                  username = :username, 
                                  password = :pass, 
                                  email = :email, 
                                  role = 'admin' 
                                  WHERE id = 1");
            $stmt->bindValue(':username', $adminUser, SQLITE3_TEXT);
            $stmt->bindValue(':pass', $hashedPassword, SQLITE3_TEXT);
            $stmt->bindValue(':email', $adminEmail, SQLITE3_TEXT);
            $stmt->execute();
        }
        
		//$version = getCurrentConfigVersion()['version'];
		//$type_pro = getCurrentConfigVersion()['type_pro'];

		//@file_get_contents("https://update.itp-corp.ru/minib/download.php?action=record_install&version=" . urlencode($version) . "&type_pro=" . urlencode($type_pro));
		@file_get_contents("https://update.mini-bucket.ru/minib/download.php?action=record_install");	
        $db->exec('COMMIT');
        $db->close();
        
        return true;
    } catch (Exception $e) {
        if (isset($db)) {
            $db->exec('ROLLBACK');
            $db->close();
        }
        error_log('Installation finalize error: ' . $e->getMessage());
        return false;
    }
}

function getLicenseContent() {
    $licensePath = ROOT_PATH . '/LICENSE';
    if (file_exists($licensePath)) {
        return file_get_contents($licensePath);
    }
    return "License file not found.";
}

function getPrivacyContent() {
    $privacyPath = ROOT_PATH . '/PRIVACY';
    if (file_exists($privacyPath)) {
        return file_get_contents($privacyPath);
    }
    return "Privacy policy file not found.";
}

// Проверки для шага 2 (диагностика)
$dbOk = false;
$tempWritable = false;
$dbError = null;

try {
    $db = getDB();
    $dbOk = true;
    $db->close();
} catch (Exception $e) {
    $dbError = $e->getMessage();
}

$tempPath = ROOT_PATH . '/tmp';
if (!is_dir($tempPath)) {
    mkdir($tempPath, 0755, true);
}
$tempWritable = is_writable($tempPath);

$configWritable = is_writable(ROOT_PATH . '/config.php');

$allChecksPassed = $dbOk && $tempWritable && $configWritable;

$licenseContent = getLicenseContent();
$privacyContent = getPrivacyContent();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Installation Wizard — Mini-B</title>
    <link href="../lib/bootstrap-5.3.8-dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../lib/fontawesome-free-6.7.2-web/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Text', 'SF Pro Display', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e9edf2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .install-container {
            width: 100%;
            max-width: 700px;
            margin: 0 auto;
        }
        
        .install-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            border-radius: 32px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25), 0 0 0 1px rgba(0, 0, 0, 0.02);
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .progress-step {
            padding: 24px 32px 0;
        }
        
        .step-indicators {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        
        .step-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 1;
            flex: 1;
        }
        
        .step-circle {
            width: 32px;
            height: 32px;
            background: #e5e5ea;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 600;
            color: #8e8e93;
            transition: all 0.3s ease;
        }
        
        .step-item.active .step-circle {
            background: #007aff;
            color: white;
            box-shadow: 0 0 0 4px rgba(0, 122, 255, 0.2);
        }
        
        .step-item.completed .step-circle {
            background: #34c759;
            color: white;
        }
        
        .step-item.completed .step-circle i {
            font-size: 14px;
        }
        
        .step-label {
            font-size: 11px;
            font-weight: 500;
            margin-top: 8px;
            color: #8e8e93;
            text-align: center;
        }
        
        .step-item.active .step-label {
            color: #007aff;
            font-weight: 600;
        }
        
        .step-line {
            flex: 1;
            height: 2px;
            background: #e5e5ea;
            margin: 0 8px;
            position: relative;
            top: -16px;
        }
        
        .step-line.filled {
            background: #34c759;
        }
        
        .install-header {
            padding: 32px 32px 0;
            text-align: center;
        }
        
        .install-header .logo-icon {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, #007aff, #5856d6);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            box-shadow: 0 8px 20px rgba(0, 122, 255, 0.3);
        }
        
        .install-header .logo-icon i {
            font-size: 32px;
            color: white;
        }
        
        .install-header h2 {
            font-size: 28px;
            font-weight: 700;
            letter-spacing: -0.5px;
            background: linear-gradient(135deg, #1c1c1e, #3a3a3e);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .install-header p {
            color: #8e8e93;
            font-size: 14px;
            margin-top: 8px;
        }
        
        .install-content {
            padding: 32px;
        }
        
        .form-label {
            font-weight: 500;
            font-size: 14px;
            color: #1c1c1e;
            margin-bottom: 8px;
        }
        
        .form-control, .input-group-text {
            border-radius: 12px;
            border: 1px solid #e5e5ea;
            padding: 12px 16px;
            font-size: 15px;
            transition: all 0.2s;
            background: #ffffff;
        }
        
        .form-control:focus {
            border-color: #007aff;
            box-shadow: 0 0 0 4px rgba(0, 122, 255, 0.1);
            outline: none;
        }
        
        .btn-apple {
            border-radius: 12px;
            padding: 12px 24px;
            font-weight: 500;
            font-size: 15px;
            transition: all 0.2s;
        }
        
        .btn-apple-primary {
            background: #007aff;
            color: white;
            border: none;
        }
        
        .btn-apple-primary:hover {
            background: #0051d5;
            transform: translateY(-1px);
        }
        
        .btn-apple-secondary {
            background: #f5f5f7;
            color: #007aff;
            border: none;
        }
        
        .btn-apple-secondary:hover {
            background: #e5e5ea;
        }
        
        .btn-apple-danger {
            background: #ff3b30;
            color: white;
            border: none;
        }
        
        .btn-apple-danger:hover {
            background: #d70015;
        }
        
        .check-card {
            background: #f9f9fb;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 16px;
            border: 1px solid #e5e5ea;
        }
        
        .check-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid #e5e5ea;
        }
        
        .check-item:last-child {
            border-bottom: none;
        }
        
        .check-icon {
            width: 28px;
            font-size: 18px;
        }
        
        .check-icon.success { color: #34c759; }
        .check-icon.error { color: #ff3b30; }
        .check-icon.warning { color: #ff9500; }
        
        .alert-custom {
            border-radius: 16px;
            border: none;
            padding: 16px 20px;
        }
        
        .api-key-display {
            background: #1c1c1e;
            border-radius: 16px;
            padding: 20px;
            font-family: 'SF Mono', Monaco, monospace;
            font-size: 14px;
            color: #34c759;
            word-break: break-all;
            text-align: center;
            letter-spacing: 0.5px;
        }
        
        .install-footer {
            padding: 20px 32px 32px;
            display: flex;
            justify-content: space-between;
            border-top: 1px solid #f0f0f0;
            background: #fafafc;
        }
        
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
        
        .fade-in-up {
            animation: fadeInUp 0.5s ease-out;
        }
        
        .password-strength {
            margin-top: 8px;
            font-size: 12px;
        }
        
        .strength-weak { color: #ff3b30; }
        .strength-medium { color: #ff9500; }
        .strength-strong { color: #34c759; }
        
        .password-wrapper {
            position: relative;
        }
        
        .password-wrapper input {
            padding-right: 80px;
        }
        
        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #8e8e93;
            cursor: pointer;
        }
        
        /* Стили для чекбоксов */
        .agreement-checkbox {
            margin-bottom: 16px;
            padding: 12px;
            background: #f9f9fb;
            border-radius: 12px;
            border: 1px solid #e5e5ea;
            transition: all 0.2s;
        }
        
        .agreement-checkbox:hover {
            background: #f5f5f7;
            border-color: #007aff;
        }
        
        .agreement-checkbox .form-check {
            margin: 0;
        }
        
        .agreement-checkbox .form-check-label {
            font-weight: 500;
            color: #1c1c1e;
        }
        
        .agreement-checkbox .link-text {
            color: #007aff;
            text-decoration: none;
            cursor: pointer;
            margin-left: 8px;
        }
        
        .agreement-checkbox .link-text:hover {
            text-decoration: underline;
        }
        
        /* Стили для модального окна */
        .modal-content {
            border-radius: 20px;
            border: none;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }
        
        .modal-header {
            border-bottom: 1px solid #e5e5ea;
            padding: 20px 24px;
            background: #fafafc;
            border-radius: 20px 20px 0 0;
        }
        
        .modal-body {
            padding: 24px;
            max-height: 60vh;
            overflow-y: auto;
        }
        
        .modal-footer {
            border-top: 1px solid #e5e5ea;
            padding: 16px 24px;
            background: #fafafc;
            border-radius: 0 0 20px 20px;
        }
        
        .license-content, .privacy-content {
            font-family: 'SF Mono', Monaco, monospace;
            font-size: 12px;
            white-space: pre-wrap;
            word-wrap: break-word;
            background: #f9f9fb;
            padding: 16px;
            border-radius: 12px;
            margin: 0;
        }
    </style>
</head>
<body>
<div class="install-container">
    <div class="install-card fade-in-up">
        <div class="progress-step">
            <div class="step-indicators">
                <?php for ($i = 1; $i <= 6; $i++): 
                    $isActive = ($i == $currentStep);
                    $isCompleted = ($i < $currentStep);
                ?>
                    <div class="step-item <?= $isActive ? 'active' : '' ?> <?= $isCompleted ? 'completed' : '' ?>">
                        <div class="step-circle">
                            <?php if ($isCompleted): ?>
                                <i class="fas fa-check"></i>
                            <?php else: ?>
                                <?= $i ?>
                            <?php endif; ?>
                        </div>
                        <span class="step-label"><?= $steps[$i] ?></span>
                    </div>
                    <?php if ($i < 6): ?>
                        <div class="step-line <?= ($i < $currentStep - 1) ? 'filled' : '' ?>"></div>
                    <?php endif; ?>
                <?php endfor; ?>
            </div>
        </div>
        
        <div class="install-header">
            <div class="logo-icon">
                <i class="fas fa-bucket"></i>
            </div>
            <h2>Mini-B Setup</h2>
            <p>Configure your Mini Bucket Storage Panel</p>
        </div>
        
        <div class="install-content">
            <?php if ($error): ?>
                <div class="alert alert-danger alert-custom mb-4">
                    <i class="fas fa-exclamation-circle me-2"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <!-- STEP 1: Welcome with Agreements -->
            <?php if ($currentStep == 1): ?>
                <div class="text-center">
                    <i class="fas fa-hand-wave" style="font-size: 48px; color: #007aff; margin-bottom: 20px;"></i>
                    <h3 style="font-weight: 600; margin-bottom: 16px;">Welcome to Mini-B</h3>
                    <p style="color: #6c757d; margin-bottom: 24px; line-height: 1.5;">
                        This wizard will guide you through the initial setup of your 
                        Mini Bucket Storage Panel. You'll configure system settings, 
                        create an administrator account, and secure your installation.
                    </p>
                    <div class="check-card text-start" style="background: #f9f9fb;">
                        <p class="mb-2"><i class="fas fa-clock me-2" style="color: #007aff;"></i> Estimated time: 2-3 minutes</p>
                        <p class="mb-2"><i class="fas fa-shield-alt me-2" style="color: #34c759;"></i> Secure API key generation</p>
                        <p class="mb-0"><i class="fas fa-users me-2" style="color: #5856d6;"></i> Admin account creation</p>
                    </div>
                    
                    <hr class="my-4">
                    
                    <form method="POST" id="agreementForm">
                        <input type="hidden" name="action" value="accept_terms">
                        
                        <!-- Лицензионное соглашение -->
                        <div class="agreement-checkbox">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="license_accepted" id="license_accepted" value="1" required>
                                <label class="form-check-label" for="license_accepted">
                                    I have read and agree to the 
                                    <a href="#" class="link-text" data-bs-toggle="modal" data-bs-target="#licenseModal">
                                        License Agreement
                                    </a>
                                </label>
                            </div>
                        </div>
                        
                        <!-- Политика сбора данных -->
                        <div class="agreement-checkbox">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="privacy_accepted" id="privacy_accepted" value="1" required>
                                <label class="form-check-label" for="privacy_accepted">
                                    I have read and agree to the 
                                    <a href="#" class="link-text" data-bs-toggle="modal" data-bs-target="#privacyModal">
                                        Privacy Policy & Data Collection Terms
                                    </a>
                                </label>
                            </div>
                        </div>
                        
                        <div class="alert alert-info alert-custom mt-3" style="background: #e3f2fd;">
                            <i class="fas fa-info-circle me-2"></i>
                            You must accept both agreements to continue with the installation.
                        </div>
                        
                        <button type="submit" class="btn btn-apple-primary btn-apple mt-3 w-100" id="continueBtn" disabled>
                            Accept & Continue <i class="fas fa-arrow-right ms-2"></i>
                        </button>
                    </form>
                </div>
            <?php endif; ?>
            
            <!-- STEP 2: System Check -->
            <?php if ($currentStep == 2): ?>
                <div>
                    <h3 style="font-weight: 600; margin-bottom: 20px;">System Diagnostics</h3>
                    <p style="color: #6c757d; margin-bottom: 24px;">Checking your system requirements...</p>
                    
                    <div class="check-card">
                        <div class="check-item">
                            <div class="check-icon <?= $dbOk ? 'success' : 'error' ?>">
                                <i class="fas <?= $dbOk ? 'fa-check-circle' : 'fa-times-circle' ?>"></i>
                            </div>
                            <div style="flex: 1;">
                                <strong>Database Connection</strong>
                                <div class="text-muted small">SQLite3 database access</div>
                            </div>
                            <?php if (!$dbOk && $dbError): ?>
                                <span class="text-danger small"><?= htmlspecialchars($dbError) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="check-item">
                            <div class="check-icon <?= $tempWritable ? 'success' : 'error' ?>">
                                <i class="fas <?= $tempWritable ? 'fa-check-circle' : 'fa-times-circle' ?>"></i>
                            </div>
                            <div style="flex: 1;">
                                <strong>Temporary Directory</strong>
                                <div class="text-muted small">Write permissions for /tmp</div>
                            </div>
                        </div>
                        <div class="check-item">
                            <div class="check-icon <?= $configWritable ? 'success' : 'error' ?>">
                                <i class="fas <?= $configWritable ? 'fa-check-circle' : 'fa-times-circle' ?>"></i>
                            </div>
                            <div style="flex: 1;">
                                <strong>Configuration File</strong>
                                <div class="text-muted small">Write permissions for config.php</div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!$allChecksPassed): ?>
                        <div class="alert alert-warning alert-custom mt-3">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Please fix the issues above before continuing.
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <!-- STEP 3: Host Configuration -->
            <?php if ($currentStep == 3): ?>
				<div>
					<h3 style="font-weight: 600; margin-bottom: 8px;">Host Configuration</h3>
					<p style="color: #6c757d; margin-bottom: 24px;">Set a name for your Mini-B instance</p>
					
					<div class="alert alert-info alert-custom mb-4">
						<i class="fas fa-info-circle me-2"></i>
						<strong>Note:</strong> The hostname will be automatically added to <code>/etc/hosts</code> 
						to prevent sudo warnings and ensure proper system operation.
					</div>
					
					<form method="POST">
						<input type="hidden" name="action" value="save_hostname">
						<div class="mb-4">
							<label class="form-label">System Hostname</label>
							<div class="input-group">
								<span class="input-group-text"><i class="fas fa-server"></i></span>
								<input type="text" name="hostname" class="form-control" 
									   value="<?= htmlspecialchars($_SESSION['install_data']['hostname'] ?? getSystemHostname()) ?>" 
									   placeholder="Enter hostname" required autofocus>
							</div>
							<div class="form-text text-muted mt-2">
								<i class="fas fa-info-circle"></i> 
								This name will appear in the panel header and system hostname<br>
								<i class="fas fa-check-circle text-success"></i> 
								Will be added to <code>/etc/hosts</code> as <code>127.0.1.1 <?= htmlspecialchars($_SESSION['install_data']['hostname'] ?? getSystemHostname()) ?></code>
							</div>
						</div>
						<div class="mt-4">
							<button type="submit" class="btn btn-apple-primary btn-apple w-100">
								Continue <i class="fas fa-arrow-right ms-2"></i>
							</button>
						</div>
					</form>
				</div>
			<?php endif; ?>
            
            <!-- STEP 4: API Security -->
            <?php if ($currentStep == 4): ?>
				<div class="text-center">
					<i class="fas fa-key" style="font-size: 48px; color: #ff9500; margin-bottom: 20px;"></i>
					<h3 style="font-weight: 600; margin-bottom: 16px;">Security Credentials</h3>
					<p style="color: #6c757d; margin-bottom: 24px;">
						Unique API key and serial number will be generated for your installation.
					</p>
					
					<?php if (isset($_SESSION['install_data']['api_key'])): ?>
						<div class="api-key-display mb-3">
							<i class="fas fa-shield-alt me-2"></i> 
							<strong>API Key:</strong><br>
							<code style="font-size: 12px; word-break: break-all;"><?= htmlspecialchars($_SESSION['install_data']['api_key']) ?></code>
						</div>
						<div class="api-key-display mb-4" style="background: #2c2c2e;">
							<i class="fas fa-barcode me-2"></i> 
							<strong>Serial Number (HostSn):</strong><br>
							<code style="font-size: 14px; letter-spacing: 1px;"><?= htmlspecialchars($_SESSION['install_data']['serial_number']) ?></code>
						</div>
						<div class="alert alert-info alert-custom mb-4" style="background: #e3f2fd;">
							<i class="fas fa-save me-2"></i> 
							<strong>Important!</strong> Please save both credentials securely. 
							You won't be able to see them again after this step!
						</div>
						<a href="?step=5" class="btn btn-apple-primary btn-apple">
							Continue <i class="fas fa-arrow-right ms-2"></i>
						</a>
					<?php else: ?>
						<form method="POST">
							<input type="hidden" name="action" value="generate_api">
							<button type="submit" class="btn btn-apple-primary btn-apple">
								<i class="fas fa-sync-alt me-2"></i> Generate Security Credentials
							</button>
						</form>
					<?php endif; ?>
				</div>
			<?php endif; ?>
            
            <!-- STEP 5: Admin Account -->
            <?php if ($currentStep == 5): ?>
                <div>
                    <h3 style="font-weight: 600; margin-bottom: 8px;">Administrator Account</h3>
                    <p style="color: #6c757d; margin-bottom: 24px;">Create your admin credentials</p>
                    
                    <form method="POST" id="adminForm">
                        <input type="hidden" name="action" value="create_admin">
                        <div class="mb-3">
                            <label class="form-label">Username *</label>
                            <input type="text" name="username" id="username" class="form-control" 
                                   value="<?= htmlspecialchars($_SESSION['install_data']['admin_username'] ?? 'admin') ?>" 
                                   required autofocus>
                            <div class="invalid-feedback" id="usernameError">Username is required</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email (optional)</label>
                            <input type="email" name="email" class="form-control" 
                                   value="<?= htmlspecialchars($_SESSION['install_data']['admin_email'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password *</label>
                            <div class="password-wrapper">
                                <input type="password" name="password" id="admin_password" class="form-control" required>
                                <button type="button" class="password-toggle" onclick="togglePassword('admin_password')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="password-strength" id="passwordStrength"></div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Confirm Password *</label>
                            <div class="password-wrapper">
                                <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
                                <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="invalid-feedback" id="passwordError"></div>
                        </div>
                        <button type="submit" class="btn btn-apple-primary btn-apple w-100">
                            Create Account <i class="fas fa-user-check ms-2"></i>
                        </button>
                    </form>
                </div>
            <?php endif; ?>
            
            <!-- STEP 6: Ready to Launch -->
            <?php if ($currentStep == 6): ?>
                <div class="text-center">
                    <i class="fas fa-check-circle" style="font-size: 56px; color: #34c759; margin-bottom: 20px;"></i>
                    <h3 style="font-weight: 600; margin-bottom: 16px;">Installation Complete!</h3>
                    <p style="color: #6c757d; margin-bottom: 24px; line-height: 1.5;">
                        Mini-B has been successfully configured. You're now ready to 
                        manage your storage buckets.
                    </p>
                    
                    <div class="check-card text-start mb-4">
                        <p class="mb-2"><i class="fas fa-check-circle text-success me-2"></i> System configured</p>
                        <p class="mb-2"><i class="fas fa-key text-warning me-2"></i> API key generated and saved</p>
                        <p class="mb-0"><i class="fas fa-user-shield text-primary me-2"></i> Admin account created</p>
                    </div>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="finalize">
                        <button type="submit" class="btn btn-apple-primary btn-apple">
                            <i class="fas fa-rocket me-2"></i> Launch Mini-B
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="install-footer">
            <?php if ($currentStep > 1 && $currentStep < 6 && $currentStep != 4): ?>
                <a href="?step=<?= $currentStep - 1 ?>" class="btn btn-apple-secondary btn-apple">
                    <i class="fas fa-arrow-left me-2"></i> Back
                </a>
            <?php elseif ($currentStep == 4 && isset($_SESSION['install_data']['api_key'])): ?>
                <a href="?step=3" class="btn btn-apple-secondary btn-apple">
                    <i class="fas fa-arrow-left me-2"></i> Back
                </a>
            <?php else: ?>
                <div></div>
            <?php endif; ?>
            
            <?php if ($currentStep < 6 && $currentStep != 4 && $currentStep != 3 && $currentStep != 5): ?>
                <?php if ($currentStep == 1): ?>
                <?php elseif ($currentStep == 2): ?>
                    <?php if ($allChecksPassed): ?>
                        <a href="?step=3" class="btn btn-apple-primary btn-apple">
                            Continue <i class="fas fa-arrow-right ms-2"></i>
                        </a>
                    <?php else: ?>
                        <a href="?step=2" class="btn btn-apple-primary btn-apple" onclick="location.reload()">
                            <i class="fas fa-sync-alt me-2"></i> Check Again
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
            Version: <?php echo $version; ?>
        </div>
    </div>
</div>

<!-- Модальное окно для лицензионного соглашения -->
<div class="modal fade" id="licenseModal" tabindex="-1" aria-labelledby="licenseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="licenseModalLabel">
                    <i class="fas fa-file-contract me-2" style="color: #007aff;"></i>
                    License Agreement
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <pre class="license-content"><?= htmlspecialchars($licenseContent) ?></pre>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-apple-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-apple-primary" id="acceptLicenseBtn">
                    <i class="fas fa-check me-2"></i>I Accept
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно для политики конфиденциальности -->
<div class="modal fade" id="privacyModal" tabindex="-1" aria-labelledby="privacyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="privacyModalLabel">
                    <i class="fas fa-shield-alt me-2" style="color: #34c759;"></i>
                    Privacy Policy & Data Collection
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <pre class="privacy-content"><?= htmlspecialchars($privacyContent) ?></pre>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-apple-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-apple-primary" id="acceptPrivacyBtn">
                    <i class="fas fa-check me-2"></i>I Accept
                </button>
            </div>
        </div>
    </div>
</div>

<script src="../lib/bootstrap-5.3.8-dist/js/bootstrap.bundle.min.js"></script>
<script>
// Управление кнопкой Continue на шаге 1
const licenseCheckbox = document.getElementById('license_accepted');
const privacyCheckbox = document.getElementById('privacy_accepted');
const continueBtn = document.getElementById('continueBtn');

function updateContinueButton() {
    if (continueBtn) {
        const licenseChecked = licenseCheckbox ? licenseCheckbox.checked : false;
        const privacyChecked = privacyCheckbox ? privacyCheckbox.checked : false;
        continueBtn.disabled = !(licenseChecked && privacyChecked);
    }
}

if (licenseCheckbox && privacyCheckbox) {
    licenseCheckbox.addEventListener('change', updateContinueButton);
    privacyCheckbox.addEventListener('change', updateContinueButton);
    updateContinueButton();
}

// Функции для принятия соглашений из модальных окон
function acceptLicense() {
    if (licenseCheckbox) {
        licenseCheckbox.checked = true;
        updateContinueButton();
        const modal = bootstrap.Modal.getInstance(document.getElementById('licenseModal'));
        if (modal) modal.hide();
    }
}

function acceptPrivacy() {
    if (privacyCheckbox) {
        privacyCheckbox.checked = true;
        updateContinueButton();
        const modal = bootstrap.Modal.getInstance(document.getElementById('privacyModal'));
        if (modal) modal.hide();
    }
}

// Назначаем обработчики для кнопок в модальных окнах
const acceptLicenseBtn = document.getElementById('acceptLicenseBtn');
if (acceptLicenseBtn) {
    acceptLicenseBtn.addEventListener('click', acceptLicense);
}

const acceptPrivacyBtn = document.getElementById('acceptPrivacyBtn');
if (acceptPrivacyBtn) {
    acceptPrivacyBtn.addEventListener('click', acceptPrivacy);
}

function checkPasswordStrength(password) {
    let strength = 0;
    if (password.length >= 8) strength++;
    if (password.match(/[a-z]/)) strength++;
    if (password.match(/[A-Z]/)) strength++;
    if (password.match(/[0-9]/)) strength++;
    if (password.match(/[^a-zA-Z0-9]/)) strength++;
    
    if (strength <= 2) return { text: 'Weak', class: 'strength-weak' };
    if (strength <= 4) return { text: 'Medium', class: 'strength-medium' };
    return { text: 'Strong', class: 'strength-strong' };
}

function updatePasswordStrength() {
    const password = document.getElementById('admin_password');
    if (password) {
        const strength = checkPasswordStrength(password.value);
        const strengthDiv = document.getElementById('passwordStrength');
        if (strengthDiv) {
            strengthDiv.innerHTML = `<i class="fas fa-shield-alt"></i> Password strength: <span class="${strength.class}">${strength.text}</span>`;
        }
    }
}

function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    if (field) {
        field.type = field.type === 'password' ? 'text' : 'password';
    }
}

function validateForm(username, password, confirm) {
    let isValid = true;
    
    if (!username || username.trim() === '') {
        const usernameField = document.getElementById('username');
        usernameField.classList.add('is-invalid');
        isValid = false;
    } else {
        const usernameField = document.getElementById('username');
        usernameField.classList.remove('is-invalid');
    }
    
    if (password.length < 4) {
        const passwordError = document.getElementById('passwordError');
        passwordError.textContent = 'Password must be at least 4 characters long!';
        document.getElementById('admin_password').classList.add('is-invalid');
        isValid = false;
    } else {
        document.getElementById('admin_password').classList.remove('is-invalid');
    }
    
    if (password !== confirm) {
        const passwordError = document.getElementById('passwordError');
        passwordError.textContent = 'Passwords do not match!';
        document.getElementById('confirm_password').classList.add('is-invalid');
        isValid = false;
    } else if (password.length >= 4) {
        document.getElementById('confirm_password').classList.remove('is-invalid');
        document.getElementById('passwordError').textContent = '';
    }
    
    return isValid;
}

document.addEventListener('DOMContentLoaded', function() {
    const passwordField = document.getElementById('admin_password');
    if (passwordField) {
        passwordField.addEventListener('input', updatePasswordStrength);
        updatePasswordStrength();
    }
    
    const adminForm = document.getElementById('adminForm');
    if (adminForm) {
        adminForm.addEventListener('submit', function(e) {
            const username = document.getElementById('username').value;
            const password = document.getElementById('admin_password').value;
            const confirm = document.getElementById('confirm_password').value;
            
            if (!validateForm(username, password, confirm)) {
                e.preventDefault();
            }
        });
        
        const usernameField = document.getElementById('username');
        if (usernameField) {
            usernameField.addEventListener('input', function() {
                if (this.value.trim() !== '') {
                    this.classList.remove('is-invalid');
                }
            });
        }
        
        const confirmField = document.getElementById('confirm_password');
        if (confirmField) {
            confirmField.addEventListener('input', function() {
                const password = document.getElementById('admin_password').value;
                if (this.value === password && password.length >= 4) {
                    this.classList.remove('is-invalid');
                    document.getElementById('passwordError').textContent = '';
                }
            });
        }
        
        const passwordMainField = document.getElementById('admin_password');
        if (passwordMainField) {
            passwordMainField.addEventListener('input', function() {
                if (this.value.length >= 4) {
                    this.classList.remove('is-invalid');
                }
                const confirmVal = document.getElementById('confirm_password').value;
                if (confirmVal !== '' && confirmVal !== this.value) {
                    document.getElementById('passwordError').textContent = 'Passwords do not match!';
                    document.getElementById('confirm_password').classList.add('is-invalid');
                } else if (confirmVal !== '' && confirmVal === this.value && this.value.length >= 4) {
                    document.getElementById('confirm_password').classList.remove('is-invalid');
                    document.getElementById('passwordError').textContent = '';
                }
            });
        }
    }
});
</script>
</body>
</html>