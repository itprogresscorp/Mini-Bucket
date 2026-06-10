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
}

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-API-Key, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Max-Age: 86400");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

header('Content-Type: application/json');

// ========== ПРОВЕРКА API КЛЮЧА ==========
function validateApiKey() {
    global $db;
    
    if (!$db) {
        try {
            $db = getDB();
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database connection failed']);
            exit;
        }
    }
    
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $apiKey = $headers['X-API-Key'] ?? $_GET['api_key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '';
    
    if (empty($apiKey)) {
        if (isset($_SESSION['user_id'])) {
            return true;
        }
        http_response_code(401);
        echo json_encode(['error' => 'API key required']);
        exit;
    }
    
    $stmt = $db->prepare("SELECT idHost, hostName FROM hosts WHERE hostApiKey = :key");
    $stmt->bindValue(':key', $apiKey, SQLITE3_TEXT);
    $result = $stmt->execute();
    $host = $result->fetchArray(SQLITE3_ASSOC);
    
    if (!$host) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid API key']);
        exit;
    }
    
    return true;
}

validateApiKey();

// ========== КОНСТАНТЫ ==========
define('PLUGINS_DIR', ROOT_PATH . '/plugins');
define('BACKEND_PLUGINS_DIR', '/var/www/minib/plugins');
define('PLUGINS_MENU_DIR', PLUGINS_DIR . '/menu');
define('PLUGINS_REPOS_DIR', BACKEND_PLUGINS_DIR . '/repositories');
define('PLUGINS_INSTALLED_DIR', BACKEND_PLUGINS_DIR . '/installed');
define('PLUGINS_EXTRACT_DIR', BACKEND_PLUGINS_DIR . '/extract');

// Функция для инвалидации opcache
function invalidateOpCache($filePath) {
    if (function_exists('opcache_invalidate')) {
        opcache_invalidate($filePath, true);
    }
    if (function_exists('apc_delete_file')) {
        apc_delete_file($filePath);
    }
}

// Функция для создания директории через exec
function createDirectoryWithOwner($path, $permissions = 0755, $owner = 'www-data') {
    if (file_exists($path)) {
        return true;
    }
    
    $parent = dirname($path);
    if (!file_exists($parent)) {
        createDirectoryWithOwner($parent, $permissions, $owner);
    }
    
    $cmd = "mkdir -p " . escapeshellarg($path) . " && chmod " . decoct($permissions) . " " . escapeshellarg($path) . " && chown " . escapeshellarg($owner) . ":" . escapeshellarg($owner) . " " . escapeshellarg($path);
    
    exec($cmd, $output, $returnCode);
    
    if ($returnCode !== 0) {
        error_log("Failed to create directory: " . $path . " - " . implode("\n", $output));
    }
    
    return $returnCode === 0;
}

$directories = [
    PLUGINS_MENU_DIR,
    PLUGINS_REPOS_DIR,
    PLUGINS_INSTALLED_DIR,
    PLUGINS_EXTRACT_DIR
];

foreach ($directories as $dir) {
    if (!createDirectoryWithOwner($dir, 0755, 'www-data')) {
        error_log("Failed to create directory: " . $dir);
    }
}

// ========== ФУНКЦИИ ДЛЯ РАБОТЫ С ПЛАГИНАМИ ==========

/**
 * Получить список всех плагинов
 */
function getPlugins() {
    $plugins = [];
    
    if (!file_exists(PLUGINS_INSTALLED_DIR)) {
        return $plugins;
    }
    
    $items = scandir(PLUGINS_INSTALLED_DIR);
    foreach ($items as $item) {
        if ($item == '.' || $item == '..' || $item == 'menu') {
            continue;
        }
        
        $pluginPath = PLUGINS_INSTALLED_DIR . '/' . $item;
        $infoFile = $pluginPath . '/info.json';
        
        if (is_dir($pluginPath) && file_exists($infoFile)) {
            $info = json_decode(file_get_contents($infoFile), true);
            if ($info) {
                $info['folder'] = $item;
                $info['path'] = $pluginPath;
                $info['enabled'] = isPluginEnabled($item);
                $plugins[] = $info;
            }
        }
    }
    
    usort($plugins, function($a, $b) {
        return strcmp($a['name'], $b['name']);
    });
    
    return $plugins;
}

/**
 * Получить список ZIP файлов в репозитории
 */
function getRepositoryPlugins() {
    $plugins = [];
    
    // Получаем список установленных плагинов
    $installedPlugins = getInstalledPluginFolders();
    
    if (!file_exists(PLUGINS_REPOS_DIR)) {
        return $plugins;
    }
    
    $files = scandir(PLUGINS_REPOS_DIR);
    foreach ($files as $file) {
        if ($file == '.' || $file == '..') {
            continue;
        }
        
        $filePath = PLUGINS_REPOS_DIR . '/' . $file;
        if (is_file($filePath) && pathinfo($file, PATHINFO_EXTENSION) === 'zip') {
            $fileInfo = pathinfo($file);
            $pluginNameFromZip = $fileInfo['filename'];
            
            // Пытаемся прочитать info.json из ZIP без распаковки
            $zipInfo = getZipInfo($filePath);
            
            // Определяем имя плагина и папку
            $pluginFolder = null;
            if ($zipInfo && isset($zipInfo['name'])) {
                $pluginFolder = preg_replace('/[^a-zA-Z0-9_-]/', '', str_replace(' ', '_', strtolower($zipInfo['name'])));
            } else {
                $pluginFolder = preg_replace('/[^a-zA-Z0-9_-]/', '', str_replace(' ', '_', strtolower($pluginNameFromZip)));
            }
            
            // Проверяем установлен ли плагин
            $isInstalled = in_array($pluginFolder, $installedPlugins);
            $installedVersion = null;
            $installedEnabled = false;
            
            if ($isInstalled) {
                // Получаем информацию об установленном плагине
                $installedInfo = getInstalledPluginInfo($pluginFolder);
                if ($installedInfo) {
                    $installedVersion = $installedInfo['version'] ?? null;
                    $installedEnabled = $installedInfo['enabled'] ?? false;
                }
            }
            
            $plugins[] = [
                'filename' => $file,
                'name' => $zipInfo ? ($zipInfo['name'] ?? $pluginNameFromZip) : $pluginNameFromZip,
                'size' => formatBytes(filesize($filePath)),
                'modified' => date('Y-m-d H:i:s', filemtime($filePath)),
                'version' => $zipInfo['version'] ?? 'Unknown',
                'author' => $zipInfo['author'] ?? 'Unknown',
                'description' => $zipInfo['description'] ?? 'No description',
                'icon' => $zipInfo['icon'] ?? 'fas fa-puzzle-piece',
                'icon_bg' => $zipInfo['icon_bg'] ?? null,
                'multinet' => $zipInfo['multinet'] ?? 'Unknown',
                'php_version' => $zipInfo['php_version'] ?? 'Any',
                'os' => $zipInfo['os'] ?? 'Any',
                'release_date' => $zipInfo['release_date'] ?? null,
                'info' => $zipInfo,
                'installed' => $isInstalled,
                'installed_version' => $installedVersion,
                'installed_enabled' => $installedEnabled,
                'plugin_folder' => $pluginFolder
            ];
        }
    }
    
    usort($plugins, function($a, $b) {
        return strcmp($a['name'], $b['name']);
    });
    
    return $plugins;
}

/**
 * Получить список папок установленных плагинов
 */
function getInstalledPluginFolders() {
    $installed = [];
    
    if (!file_exists(PLUGINS_INSTALLED_DIR)) {
        return $installed;
    }
    
    $items = scandir(PLUGINS_INSTALLED_DIR);
    foreach ($items as $item) {
        if ($item == '.' || $item == '..' || $item == 'menu') {
            continue;
        }
        
        $pluginPath = PLUGINS_INSTALLED_DIR . '/' . $item;
        $infoFile = $pluginPath . '/info.json';
        
        if (is_dir($pluginPath) && file_exists($infoFile)) {
            $installed[] = $item;
        }
    }
    
    return $installed;
}

/**
 * Получить информацию об установленном плагине по имени папки
 */
function getInstalledPluginInfo($pluginFolder) {
    $infoFile = PLUGINS_INSTALLED_DIR . '/' . $pluginFolder . '/info.json';
    if (file_exists($infoFile)) {
        $info = json_decode(file_get_contents($infoFile), true);
        $info['enabled'] = isPluginEnabled($pluginFolder);
        $info['folder'] = $pluginFolder;
        return $info;
    }
    return null;
}

/**
 * Получить информацию из info.json внутри ZIP архива
 */
function getZipInfo($zipPath) {
    if (!extension_loaded('zip')) {
        return null;
    }
    
    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        return null;
    }
    
    // Ищем info.json в корне или в первой папке
    $infoContent = null;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $filename = $zip->getNameIndex($i);
        $basename = basename($filename);
        
        if ($basename === 'info.json') {
            $infoContent = $zip->getFromIndex($i);
            break;
        }
    }
    
    $zip->close();
    
    if ($infoContent) {
        return json_decode($infoContent, true);
    }
    
    return null;
}

/**
 * Проверить включен ли плагин (с принудительным сбросом кэша)
 */
function isPluginEnabled($pluginName) {
    $menuFile = PLUGINS_MENU_DIR . '/' . $pluginName . '.php';
    if (file_exists($menuFile)) {
        // Инвалидируем opcache перед включением
        invalidateOpCache($menuFile);
        clearstatcache(true, $menuFile);
        
        $menuData = include($menuFile);
        return isset($menuData['enabled']) ? (bool)$menuData['enabled'] : false;
    }
    return false;
}

/**
 * Записать файл меню с инвалидацией кэша
 */
function writeMenuFile($menuFile, $data) {
    $content = '<?php return ' . var_export($data, true) . ';';
    $result = file_put_contents($menuFile, $content);
    if ($result !== false) {
        clearstatcache(true, $menuFile);
        invalidateOpCache($menuFile);
        usleep(200000);
        return true;
    }
    return false;
}

/**
 * Включить плагин и обновить меню
 */
function enablePlugin($pluginName) {
    $menuFile = PLUGINS_MENU_DIR . '/' . $pluginName . '.php';
    
    if (file_exists($menuFile)) {
        $data = include($menuFile);
        $data['enabled'] = true;
        if (!writeMenuFile($menuFile, $data)) {
            return false;
        }
        // Проверяем что записалось
        invalidateOpCache($menuFile);
        clearstatcache(true, $menuFile);
        $checkData = include($menuFile);
        return isset($checkData['enabled']) && $checkData['enabled'] === true;
    }
    
    $pluginPath = PLUGINS_INSTALLED_DIR . '/' . $pluginName;
    $infoFile = $pluginPath . '/info.json';
    
    if (file_exists($infoFile)) {
        $info = json_decode(file_get_contents($infoFile), true);
        $menuData = [
            'enabled' => true,
            'group' => $info['group'] ?? 'other',
            'group_title' => $info['group_title'] ?? 'Other',
            'title' => $info['name'],
            'url' => 'plugins/' . $pluginName . '/index.php',
            'icon' => $info['icon'] ?? 'fas fa-puzzle-piece'
        ];
        
        if (!writeMenuFile($menuFile, $menuData)) {
            return false;
        }
        
        invalidateOpCache($menuFile);
        clearstatcache(true, $menuFile);
        $checkData = include($menuFile);
        return isset($checkData['enabled']) && $checkData['enabled'] === true;
    }
    
    return false;
}

/**
 * Выключить плагин и обновить меню
 */
function disablePlugin($pluginName) {
    $menuFile = PLUGINS_MENU_DIR . '/' . $pluginName . '.php';
    if (file_exists($menuFile)) {
        $data = include($menuFile);
        $data['enabled'] = false;
        if (!writeMenuFile($menuFile, $data)) {
            return false;
        }
        invalidateOpCache($menuFile);
        clearstatcache(true, $menuFile);
        $checkData = include($menuFile);
        return isset($checkData['enabled']) && $checkData['enabled'] === false;
    }
    return false;
}

/**
 * Удалить плагин
 */
function deletePlugin($pluginName) {
    $pluginPath = PLUGINS_INSTALLED_DIR . '/' . $pluginName;
    
    $uninstallScript = $pluginPath . '/uninstall.sh';
    if (file_exists($uninstallScript)) {
        shell_exec("sudo bash " . escapeshellarg($uninstallScript) . " 2>&1");
    }  
    return true;
}

/**
 * Удалить ZIP файл из репозитория
 */
function deleteRepositoryZip($filename) {
    $filePath = PLUGINS_REPOS_DIR . '/' . $filename;
    
    $realPath = realpath($filePath);
    $realRepoDir = realpath(PLUGINS_REPOS_DIR);
    
    if (!$realPath || strpos($realPath, $realRepoDir) !== 0) {
        return ['success' => false, 'error' => 'Invalid file path'];
    }
    
    if (file_exists($filePath) && is_file($filePath)) {
        if (unlink($filePath)) {
            clearstatcache(true, $filePath);
            return ['success' => true, 'message' => 'ZIP file deleted successfully'];
        } else {
            return ['success' => false, 'error' => 'Cannot delete file'];
        }
    }
    
    return ['success' => false, 'error' => 'File not found'];
}

/**
 * Установить плагин из ZIP архива из репозитория
 */
function installPluginFromRepository($filename, $deleteAfterInstall = false) {
    $zipPath = PLUGINS_REPOS_DIR . '/' . $filename;
    
    if (!file_exists($zipPath)) {
        return ['success' => false, 'error' => 'ZIP file not found'];
    }
    
    $result = installPlugin($zipPath);
    
    if ($result['success'] && $deleteAfterInstall) {
        deleteRepositoryZip($filename);
    }
    
    return $result;
}

/**
 * Установить плагин из ZIP архива
 */
function installPlugin($zipFile) {
    if (!extension_loaded('zip')) {
        return ['success' => false, 'error' => 'ZIP extension not loaded'];
    }
    
    $zip = new ZipArchive();
    if ($zip->open($zipFile) !== true) {
        return ['success' => false, 'error' => 'Cannot open ZIP archive'];
    }
    
    // Имя папки = имя ZIP файла
    $pluginFolder = pathinfo($zipFile, PATHINFO_FILENAME);
    $extractPath = PLUGINS_EXTRACT_DIR . '/' . $pluginFolder;
    
    // Распаковываем
    $zip->extractTo($extractPath);
    $zip->close();
    
    // Права
    exec("chown -R www-data:www-data " . escapeshellarg($extractPath));
    exec("chmod -R 755 " . escapeshellarg($extractPath));
    
    // Запускаем install.sh
    chmod($extractPath . '/install.sh', 0755);
    exec("cd " . escapeshellarg($extractPath) . " && sudo bash install.sh 2>&1", $output, $returnCode);
    
    if ($returnCode !== 0) {
        return ['success' => false, 'error' => 'Install script failed', 'output' => $output];
    }
    
    return ['success' => true, 'message' => 'Plugin installed'];
}

/**
 * Рекурсивное копирование директории с установкой прав
 */
function copyDirectoryWithOwner($source, $destination, $owner = 'www-data') {
    if (!is_dir($destination)) {
        mkdir($destination, 0755, true);
    }
    
    $dir = opendir($source);
    while (($file = readdir($dir)) !== false) {
        if ($file == '.' || $file == '..') {
            continue;
        }
        
        $srcFile = $source . '/' . $file;
        $destFile = $destination . '/' . $file;
        
        if (is_dir($srcFile)) {
            if (!copyDirectoryWithOwner($srcFile, $destFile, $owner)) {
                return false;
            }
        } else {
            if (!copy($srcFile, $destFile)) {
                return false;
            }
            chmod($destFile, 0644);
        }
    }
    closedir($dir);
    
    // Устанавливаем владельца через exec
    exec("chown -R " . escapeshellarg($owner) . ":" . escapeshellarg($owner) . " " . escapeshellarg($destination));
    
    return true;
}

/**
 * Рекурсивное удаление директории
 */
function deleteDirectory($dir) {
    if (!file_exists($dir)) {
        return true;
    }
    
    if (!is_dir($dir)) {
        return unlink($dir);
    }
    
    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..') {
            continue;
        }
        
        if (!deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) {
            return false;
        }
    }
    
    return rmdir($dir);
}

/**
 * Получить информацию о плагине
 */
function getPluginInfo($pluginName) {
    $infoFile = PLUGINS_INSTALLED_DIR . '/' . $pluginName . '/info.json';
    if (file_exists($infoFile)) {
        $info = json_decode(file_get_contents($infoFile), true);
        $info['enabled'] = isPluginEnabled($pluginName);
        $info['folder'] = $pluginName;
        return $info;
    }
    return null;
}

/**
 * Загрузить ZIP файл плагина напрямую в PLUGINS_REPOS_DIR
 */
function uploadPluginZip($file) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'File too large (server limit)',
            UPLOAD_ERR_FORM_SIZE => 'File too large (form limit)',
            UPLOAD_ERR_PARTIAL => 'File only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        ];
        $errorMsg = $errors[$file['error']] ?? 'Unknown upload error';
        return ['success' => false, 'error' => 'Upload error: ' . $errorMsg];
    }
    
    // Проверяем MIME тип и расширение
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    $allowedMimes = ['application/zip', 'application/x-zip', 'application/x-zip-compressed'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($mimeType, $allowedMimes) && $ext !== 'zip') {
        return ['success' => false, 'error' => 'Only ZIP files are allowed. MIME: ' . $mimeType];
    }
    
    // Очищаем имя файла
    $originalName = pathinfo($file['name'], PATHINFO_FILENAME);
    $cleanName = preg_replace('/[^a-zA-Z0-9_-]/', '', str_replace(' ', '_', $originalName));
    $targetFile = PLUGINS_REPOS_DIR . '/' . $cleanName . '.zip';
    
    // Если файл с таким именем существует, добавляем суффикс
    $counter = 1;
    while (file_exists($targetFile)) {
        $targetFile = PLUGINS_REPOS_DIR . '/' . $cleanName . '_' . $counter . '.zip';
        $counter++;
    }
    
    if (!move_uploaded_file($file['tmp_name'], $targetFile)) {
        return ['success' => false, 'error' => 'Cannot save uploaded file'];
    }
    
    // Устанавливаем права
    chmod($targetFile, 0644);
    exec("chown www-data:www-data " . escapeshellarg($targetFile));
    
    clearstatcache(true, $targetFile);
    
    return ['success' => true, 'message' => 'Plugin ZIP uploaded successfully', 'filename' => basename($targetFile)];
}

/**
 * Форматирование размера файла
 */
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

// ========== API ОБРАБОТЧИК ==========
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_plugins':
        echo json_encode(['success' => true, 'data' => getPlugins()]);
        break;
        
    case 'get_repository':
        echo json_encode(['success' => true, 'data' => getRepositoryPlugins()]);
        break;
        
    case 'get_plugin':
        $pluginName = $_GET['plugin'] ?? '';
        if (empty($pluginName)) {
            echo json_encode(['success' => false, 'error' => 'Plugin name required']);
        } else {
            $plugin = getPluginInfo($pluginName);
            echo json_encode(['success' => true, 'data' => $plugin]);
        }
        break;
        
    case 'enable_plugin':
        $pluginName = $_POST['plugin'] ?? '';
        if (empty($pluginName)) {
            echo json_encode(['success' => false, 'error' => 'Plugin name required']);
        } else {
            $result = enablePlugin($pluginName);
            echo json_encode(['success' => $result, 'message' => $result ? 'Plugin enabled' : 'Failed to enable plugin']);
        }
        break;
        
    case 'disable_plugin':
        $pluginName = $_POST['plugin'] ?? '';
        if (empty($pluginName)) {
            echo json_encode(['success' => false, 'error' => 'Plugin name required']);
        } else {
            $result = disablePlugin($pluginName);
            echo json_encode(['success' => $result, 'message' => $result ? 'Plugin disabled' : 'Failed to disable plugin']);
        }
        break;
        
    case 'delete_plugin':
        $pluginName = $_POST['plugin'] ?? '';
        if (empty($pluginName)) {
            echo json_encode(['success' => false, 'error' => 'Plugin name required']);
        } else {
            $result = deletePlugin($pluginName);
            clearstatcache(true);
            echo json_encode(['success' => $result, 'message' => $result ? 'Plugin deleted' : 'Failed to delete plugin']);
        }
        break;
        
    case 'delete_repository_zip':
        $filename = $_POST['filename'] ?? '';
        if (empty($filename)) {
            echo json_encode(['success' => false, 'error' => 'Filename required']);
        } else {
            $result = deleteRepositoryZip($filename);
            echo json_encode($result);
        }
        break;
        
    case 'install_from_repository':
        $filename = $_POST['filename'] ?? '';
        $deleteAfterInstall = isset($_POST['delete_after']) ? (bool)$_POST['delete_after'] : false;
        if (empty($filename)) {
            echo json_encode(['success' => false, 'error' => 'Filename required']);
        } else {
            $result = installPluginFromRepository($filename, $deleteAfterInstall);
            clearstatcache(true);
            echo json_encode($result);
        }
        break;
        
    case 'upload_plugin':
        if (!isset($_FILES['plugin_file'])) {
            echo json_encode(['success' => false, 'error' => 'No file uploaded']);
        } else {
            $result = uploadPluginZip($_FILES['plugin_file']);
            echo json_encode($result);
        }
        break;
    
	case 'get_menu':
		try {
			$menuFile = ROOT_PATH . '/menu.php';
			if (function_exists('opcache_invalidate')) {
				opcache_invalidate($menuFile, true);
			}
			clearstatcache(true, $menuFile);
			
			$pluginsMenuDir = PLUGINS_MENU_DIR;
			if (is_dir($pluginsMenuDir)) {
				$files = scandir($pluginsMenuDir);
				foreach ($files as $file) {
					if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
						$pluginFile = $pluginsMenuDir . '/' . $file;
						if (function_exists('opcache_invalidate')) {
							opcache_invalidate($pluginFile, true);
						}
						clearstatcache(true, $pluginFile);
					}
				}
			}
			
			$menuHtml = require_once $menuFile;
			
			echo json_encode(['success' => true, 'data' => $menuHtml]);
		} catch (Exception $e) {
			echo json_encode(['success' => false, 'error' => $e->getMessage()]);
		}
		break;
    
	case 'download_plugin_zip':
		$filename = $_GET['filename'] ?? '';
		if (empty($filename)) {
			http_response_code(400);
			echo json_encode(['error' => 'Filename required']);
			break;
		}
		
		$filePath = PLUGINS_REPOS_DIR . '/' . $filename;
		
		if (!file_exists($filePath)) {
			http_response_code(404);
			echo json_encode(['error' => 'File not found']);
			break;
		}
		
		header('Content-Type: application/zip');
		header('Content-Disposition: attachment; filename="' . $filename . '"');
		header('Content-Length: ' . filesize($filePath));
		readfile($filePath);
		break;
	
    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
}
?>