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

require_once '../lang/loader.php';

// ========== КОНСТАНТЫ ==========
$minib_back_dir = '/var/www/minib';
define('CRON_JOBS_FILE', $minib_back_dir . '/cron/cron_jobs.json');
define('CRON_LOG_FILE', $minib_back_dir . '/cron/cron.log');
define('CRON_RUNNER_FILE', $minib_back_dir . '/cron/cron_runner.php');
define('USER_SCRIPTS_DIR', $minib_back_dir . '/cron/userscripts');

if (!is_dir(USER_SCRIPTS_DIR)) {
    mkdir(USER_SCRIPTS_DIR, 0755, true);
}

if (!file_exists(CRON_LOG_FILE)) {
    file_put_contents(CRON_LOG_FILE, "# Cron log created at " . date('Y-m-d H:i:s') . "\n");
    chmod(CRON_LOG_FILE, 0644);
}

// ========== ФУНКЦИЯ ДЛЯ ОЧИСТКИ ВЫВОДА ОТ НЕДОПУСТИМЫХ СИМВОЛОВ ==========
function cleanOutput($str) {
    if (empty($str)) return '';
    
    $str = mb_convert_encoding($str, 'UTF-8', 'UTF-8');
    
    $str = preg_replace('/[^\x{0009}\x{000A}\x{000D}\x{0020}-\x{007F}\x{0400}-\x{04FF}\x{0500}-\x{052F}]/u', '?', $str);
    
    $str = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $str);
    
    return $str;
}

// ========== ФУНКЦИИ ДЛЯ РАБОТЫ С ПОЛЬЗОВАТЕЛЬСКИМИ СКРИПТАМИ ==========
function getUserScripts() {
    $scripts = [];
    if (!is_dir(USER_SCRIPTS_DIR)) {
        return $scripts;
    }
    
    $files = scandir(USER_SCRIPTS_DIR);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        $fullPath = USER_SCRIPTS_DIR . '/' . $file;
        if (is_file($fullPath)) {
            $ext = pathinfo($file, PATHINFO_EXTENSION);
            $scripts[] = [
                'name' => $file,
                'path' => $fullPath,
                'extension' => $ext,
                'size' => filesize($fullPath),
                'modified' => date('Y-m-d H:i:s', filemtime($fullPath)),
                'permissions' => substr(sprintf('%o', fileperms($fullPath)), -4),
                'is_executable' => is_executable($fullPath)
            ];
        }
    }
    
    usort($scripts, function($a, $b) {
        return strcasecmp($a['name'], $b['name']);
    });
    
    return $scripts;
}

function getScriptContent($filename) {
    $filePath = USER_SCRIPTS_DIR . '/' . $filename;
    if (!file_exists($filePath)) {
        return false;
    }
    return file_get_contents($filePath);
}

function saveScript($filename, $content, $makeExecutable = true) {
	global $lang4316, $lang4317, $lang4318;
    if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $filename)) {
        return ['success' => false, 'error' => 'Invalid filename. Use only letters, numbers, underscores, dots and hyphens'];
    }
    
    if (strpos($filename, '..') !== false) {
        return ['success' => false, 'error' => $lang4316];
    }
    
    $filePath = USER_SCRIPTS_DIR . '/' . $filename;
    
    if (file_exists($filePath)) {
        $backup = $filePath . '.' . date('Y-m-d_H-i-s') . '.bak';
        copy($filePath, $backup);
    }
    
    $result = file_put_contents($filePath, $content);
    if ($result === false) {
        return ['success' => false, 'error' => $lang4317];
    }
    
    chmod($filePath, $makeExecutable ? 0755 : 0644);
    
    return ['success' => true, 'message' => $lang4318, 'path' => $filePath];
}

function deleteScript($filename) {
	global $lang4319, $lang4320, $lang4321;
    $filePath = USER_SCRIPTS_DIR . '/' . $filename;
    if (!file_exists($filePath)) {
        return ['success' => false, 'error' => $lang4319];
    }
    
    if (unlink($filePath)) {
        return ['success' => true, 'message' => $lang4320];
    }
    
    return ['success' => false, 'error' => $lang4321];
}

function getScriptTemplate($type) {
    $templates = [
        'bash' => '#!/bin/bash
#
# Script name: {name}
# Created: {date}
# Description: 

echo "Script started at $(date)"

# Your code here

echo "Script completed at $(date)"',
        
        'php' => '<?php
/**
 * Script name: {name}
 * Created: {date}
 * Description: 
 */

echo "Script started at " . date("Y-m-d H:i:s") . "\n";

// Your code here

echo "Script completed at " . date("Y-m-d H:i:s") . "\n";
?>',
        
        'python' => '#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Script name: {name}
Created: {date}
Description: 
"""

import os
import sys
from datetime import datetime

def main():
    print(f"Script started at {datetime.now()}")
    
    # Your code here
    
    print(f"Script completed at {datetime.now()}")

if __name__ == "__main__":
    main()',
        
        'node' => '#!/usr/bin/env node
/**
 * Script name: {name}
 * Created: {date}
 * Description: 
 */

console.log(`Script started at ${new Date().toISOString()}`);

// Your code here

console.log(`Script completed at ${new Date().toISOString()}`);',
        
        'ruby' => '#!/usr/bin/env ruby
#
# Script name: {name}
# Created: {date}
# Description:
#

puts "Script started at #{Time.now}"

# Your code here

puts "Script completed at #{Time.now}"'
    ];
    
    return $templates[$type] ?? $templates['bash'];
}

// ========== ФУНКЦИИ ДЛЯ РАБОТЫ С CRON ЗАДАНИЯМИ ==========
function getCronJobs() {
    if (!file_exists(CRON_JOBS_FILE)) {
        file_put_contents(CRON_JOBS_FILE, json_encode([], JSON_PRETTY_PRINT));
        return [];
    }
    
    $content = file_get_contents(CRON_JOBS_FILE);
    if ($content === false) {
        return [];
    }
    
    $entries = json_decode($content, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        $backup_files = glob(CRON_JOBS_FILE . '.backup_*');
        if (!empty($backup_files)) {
            rsort($backup_files);
            copy($backup_files[0], CRON_JOBS_FILE);
            $content = file_get_contents(CRON_JOBS_FILE);
            $entries = json_decode($content, true);
        }
    }
    
    if (!is_array($entries)) {
        $entries = [];
    }
    
    foreach ($entries as $idx => &$entry) {
        if (!isset($entry['unique_id'])) {
            $entry['unique_id'] = uniqid('job_', true);
        }
        if (!isset($entry['enabled'])) {
            $entry['enabled'] = true;
        }
        if (!isset($entry['created_at'])) {
            $entry['created_at'] = date('Y-m-d H:i:s');
        }
        if (!isset($entry['job_name'])) {
            $entry['job_name'] = 'Untitled Job';
        }
        if (!isset($entry['comment'])) {
            $entry['comment'] = '';
        }
        if (!isset($entry['last_run'])) {
            $entry['last_run'] = null;
        }
        if (!isset($entry['last_status'])) {
            $entry['last_status'] = 'pending';
        }
        if (!isset($entry['last_output'])) {
            $entry['last_output'] = '';
        }
        if (!isset($entry['job_type'])) {
            $entry['job_type'] = 'command';
        }
        if (!isset($entry['script_name'])) {
            $entry['script_name'] = '';
        }
        $entry['index'] = $idx;
    }
    
    return $entries;
}

function saveCronJobs($entries) {
    if (!is_array($entries)) {
        return false;
    }
    
    $to_save = [];
    foreach ($entries as $entry) {
        if (is_array($entry)) {
            if (isset($entry['last_output'])) {
                $entry['last_output'] = cleanOutput($entry['last_output']);
            }
            unset($entry['index']);
            $to_save[] = $entry;
        }
    }
    
    $dir = dirname(CRON_JOBS_FILE);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    
    if (file_exists(CRON_JOBS_FILE)) {
        copy(CRON_JOBS_FILE, CRON_JOBS_FILE . '.backup_' . date('Y-m-d_H-i-s'));
    }
    
    $json = json_encode($to_save, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return false;
    }
    
    $temp_file = $dir . '/cron_jobs.tmp';
    if (file_put_contents($temp_file, $json, LOCK_EX) === false) {
        return false;
    }
    
    return rename($temp_file, CRON_JOBS_FILE);
}

function checkCronTime($cron_value, $current_value) {
    if ($cron_value === "*") return true;
    
    if (preg_match("/^\*\/(\d+)$/", $cron_value, $matches)) {
        $interval = (int)$matches[1];
        return ($current_value % $interval === 0);
    }
    
    if (strpos($cron_value, "-") !== false) {
        list($start, $end) = explode("-", $cron_value);
        return ($current_value >= (int)$start && $current_value <= (int)$end);
    }
    
    if (strpos($cron_value, ",") !== false) {
        $values = explode(",", $cron_value);
        return in_array($current_value, array_map("intval", $values));
    }
    
    return (int)$cron_value === (int)$current_value;
}

function getNextRunTime($cron) {
    $now = time();
    $max_attempts = 1000;
    
    for ($i = 0; $i < $max_attempts; $i++) {
        $check_time = $now + ($i * 60);
        $minute = (int)date('i', $check_time);
        $hour = (int)date('H', $check_time);
        $day = (int)date('d', $check_time);
        $month = (int)date('m', $check_time);
        $weekday = (int)date('w', $check_time);
        
        if (checkCronTime($cron['minute'], $minute) &&
            checkCronTime($cron['hour'], $hour) &&
            checkCronTime($cron['day'], $day) &&
            checkCronTime($cron['month'], $month) &&
            checkCronTime($cron['weekday'], $weekday)) {
            return date('Y-m-d H:i:s', $check_time);
        }
    }
    
    return 'N/A';
}

function executeCronJob($job) {
	global $lang4322, $lang4323;
    if ($job['job_type'] === 'script' && !empty($job['script_name'])) {
        $script_path = USER_SCRIPTS_DIR . '/' . $job['script_name'];
        
        if (!file_exists($script_path)) {
            return [
                'status' => 'failed',
                'output' => $lang4322 . $script_path,
                'full_output' => $lang4323 . $script_path,
                'last_run' => date('Y-m-d H:i:s')
            ];
        }
        
        if (!is_executable($script_path)) {
            chmod($script_path, 0755);
        }
        
        $ext = pathinfo($script_path, PATHINFO_EXTENSION);
        if ($ext === 'php') {
            $cmd = '/usr/bin/php ' . escapeshellarg($script_path);
        } elseif ($ext === 'sh' || $ext === 'bash') {
            $cmd = '/bin/bash ' . escapeshellarg($script_path);
        } elseif ($ext === 'py') {
            $cmd = '/usr/bin/python3 ' . escapeshellarg($script_path);
        } else {
            $cmd = escapeshellarg($script_path);
        }
    } else {
        $cmd = $job['command'];
    }
    
    $output = [];
    $return_var = 0;
    
    $full_cmd = "export LC_ALL=en_US.UTF-8 && export LANG=en_US.UTF-8 && export PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin && " . $cmd;
    
    exec($full_cmd . " 2>&1", $output, $return_var);
    
    $output_str = implode("\n", $output);
    
    $output_str = cleanOutput($output_str);
    
    $status = ($return_var === 0) ? 'success' : 'failed';
    
    return [
        'status' => $status,
        'output' => substr($output_str, 0, 500),
        'full_output' => $output_str,
        'last_run' => date('Y-m-d H:i:s')
    ];
}

function ensureRunnerScript() {
    if (!file_exists(CRON_RUNNER_FILE)) {
        $runner_content = '#!/usr/bin/env php
<?php
chdir("/var/www/minib");

$minib_back_dir = "/var/www/minib";
define("USER_SCRIPTS_DIR", $minib_back_dir . "/cron/userscripts");

$cron_file = $minib_back_dir . "/cron/cron_jobs.json";
$log_file = $minib_back_dir . "/cron/cron.log";
$lock_file = $minib_back_dir . "/cron/runner.lock";

function cleanOutput($str) {
    if (empty($str)) return "";
    $str = mb_convert_encoding($str, "UTF-8", "UTF-8");
    $str = preg_replace("/[^\x{0009}\x{000A}\x{000D}\x{0020}-\x{007F}\x{0400}-\x{04FF}\x{0500}-\x{052F}]/u", "?", $str);
    $str = preg_replace("/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/", "", $str);
    return $str;
}

$lock_fp = fopen($lock_file, "w");
if (!flock($lock_fp, LOCK_EX | LOCK_NB)) {
    fclose($lock_fp);
    exit(0);
}

try {
    if (!file_exists($cron_file)) {
        exit(0);
    }

    $content = file_get_contents($cron_file);
    $jobs = json_decode($content, true);
    
    if (!is_array($jobs)) {
        file_put_contents($log_file, "[" . date("Y-m-d H:i:s") . "] Invalid jobs data\n", FILE_APPEND);
        exit(0);
    }

    $now = time();
    $current_minute = (int)date("i", $now);
    $current_hour = (int)date("H", $now);
    $current_day = (int)date("d", $now);
    $current_month = (int)date("m", $now);
    $current_weekday = (int)date("w", $now);

    function check_cron_time($cron_value, $current_value) {
        if ($cron_value === "*") return true;
        if (preg_match("/^\*\/(\d+)$/", $cron_value, $matches)) {
            return ($current_value % (int)$matches[1] === 0);
        }
        if (strpos($cron_value, "-") !== false) {
            list($start, $end) = explode("-", $cron_value);
            return ($current_value >= (int)$start && $current_value <= (int)$end);
        }
        if (strpos($cron_value, ",") !== false) {
            $values = explode(",", $cron_value);
            return in_array($current_value, array_map("intval", $values));
        }
        return (int)$cron_value === $current_value;
    }

    $jobs_updated = false;
    
    foreach ($jobs as &$job) {
        if (!$job["enabled"]) continue;
        
        if (check_cron_time($job["minute"], $current_minute) &&
            check_cron_time($job["hour"], $current_hour) &&
            check_cron_time($job["day"], $current_day) &&
            check_cron_time($job["month"], $current_month) &&
            check_cron_time($job["weekday"], $current_weekday)) {
            
            file_put_contents($log_file, "[" . date("Y-m-d H:i:s") . "] Running: " . $job["job_name"] . "\n", FILE_APPEND);
            
            if ($job["job_type"] === "script" && !empty($job["script_name"])) {
                $script_path = USER_SCRIPTS_DIR . "/" . $job["script_name"];
                if (!file_exists($script_path)) {
                    $output_str = "Script not found: " . $script_path;
                    $return_var = 1;
                } else {
                    chmod($script_path, 0755);
                    $ext = pathinfo($script_path, PATHINFO_EXTENSION);
                    if ($ext === "php") {
                        $cmd = "/usr/bin/php " . escapeshellarg($script_path);
                    } elseif ($ext === "sh" || $ext === "bash") {
                        $cmd = "/bin/bash " . escapeshellarg($script_path);
                    } elseif ($ext === "py") {
                        $cmd = "/usr/bin/python3 " . escapeshellarg($script_path);
                    } else {
                        $cmd = escapeshellarg($script_path);
                    }
                }
            } else {
                $cmd = $job["command"];
            }
            
            if (!isset($output_str)) {
                $full_cmd = "export LC_ALL=en_US.UTF-8 && export LANG=en_US.UTF-8 && export PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin && " . $cmd;
                $output = [];
                $return_var = 0;
                exec($full_cmd . " 2>&1", $output, $return_var);
                $output_str = implode("\n", $output);
                $output_str = cleanOutput($output_str);
            }
            
            $job["last_run"] = date("Y-m-d H:i:s");
            $job["last_status"] = ($return_var === 0) ? "success" : "failed";
            $job["last_output"] = substr($output_str, 0, 500);
            
            // ========== Записываем ВЫВОД скрипта в лог ==========
            file_put_contents($log_file, "[" . date("Y-m-d H:i:s") . "] " . $job["job_name"] . " - " . $job["last_status"] . " (return: $return_var)\n", FILE_APPEND);
            
            if (!empty($output_str)) {
                file_put_contents($log_file, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n", FILE_APPEND);
                file_put_contents($log_file, $output_str . "\n", FILE_APPEND);
                file_put_contents($log_file, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n", FILE_APPEND);
            } else {
                file_put_contents($log_file, "Output: (no output from script)\n", FILE_APPEND);
            }
            
            $jobs_updated = true;
        }
    }

    if ($jobs_updated) {
        foreach ($jobs as &$job) {
            if (isset($job["last_output"])) {
                $job["last_output"] = cleanOutput($job["last_output"]);
            }
        }
        file_put_contents($cron_file, json_encode($jobs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        file_put_contents($log_file, "[" . date("Y-m-d H:i:s") . "] Saved " . count($jobs) . " jobs\n", FILE_APPEND);
    }
} finally {
    flock($lock_fp, LOCK_UN);
    fclose($lock_fp);
}
';
        
        file_put_contents(CRON_RUNNER_FILE, $runner_content);
        chmod(CRON_RUNNER_FILE, 0755);
        
        file_put_contents(CRON_LOG_FILE, "[" . date("Y-m-d H:i:s") . "] Runner script created\n", FILE_APPEND);
    }
}

function getCronLogs($lines = 100) {
    if (!file_exists(CRON_LOG_FILE)) {
        return [];
    }
    
    $content = file_get_contents(CRON_LOG_FILE);
    if ($content === false) {
        return [];
    }
    
    $logs = explode("\n", $content);
    $logs = array_filter($logs, function($line) {
        return trim($line) !== '' && substr(trim($line), 0, 1) !== '#';
    });
    
    return array_reverse(array_slice($logs, -$lines));
}

function clearCronLogs() {
    if (file_exists(CRON_LOG_FILE)) {
        return file_put_contents(CRON_LOG_FILE, "# Logs cleared at " . date('Y-m-d H:i:s') . "\n") !== false;
    }
    return true;
}

function getSystemCrontab() {
    $output = shell_exec('crontab -l 2>/dev/null');
    return $output ? explode("\n", trim($output)) : [];
}

function addSystemCronEntry($command) {
    $current = getSystemCrontab();
    $current[] = $command;
    $tempFile = tempnam(sys_get_temp_dir(), 'cron_');
    file_put_contents($tempFile, implode("\n", array_filter($current)) . "\n");
    $result = shell_exec("crontab $tempFile 2>&1");
    unlink($tempFile);
    return $result === null;
}

function removeSystemCronEntry($command) {
    $current = getSystemCrontab();
    $new = array_filter($current, function($line) use ($command) {
        return trim($line) !== trim($command);
    });
    $tempFile = tempnam(sys_get_temp_dir(), 'cron_');
    file_put_contents($tempFile, implode("\n", array_filter($new)) . "\n");
    $result = shell_exec("crontab $tempFile 2>&1");
    unlink($tempFile);
    return $result === null;
}

function getRunnerCommand() {
    return '* * * * * php ' . CRON_RUNNER_FILE . ' > /dev/null 2>&1';
}

function isRunnerInSystemCron() {
    $runnerCmd = getRunnerCommand();
    $crontab = getSystemCrontab();
    foreach ($crontab as $line) {
        if (trim($line) === trim($runnerCmd)) {
            return true;
        }
    }
    return false;
}

// ========== API ОБРАБОТЧИК ==========
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    // ========== ЗАДАНИЯ ==========
    case 'get_jobs':
        $jobs = getCronJobs();
        foreach ($jobs as &$job) {
            $job['next_run'] = $job['enabled'] ? getNextRunTime($job) : 'Disabled';
        }
        echo json_encode(['success' => true, 'data' => $jobs]);
        break;
        
    case 'get_job':
		global $lang4324;
        $unique_id = $_GET['unique_id'] ?? '';
        $jobs = getCronJobs();
        
        foreach ($jobs as $job) {
            if ($job['unique_id'] === $unique_id) {
                echo json_encode(['success' => true, 'data' => $job]);
                exit;
            }
        }
        
        echo json_encode(['success' => false, 'error' => $lang4324]);
        break;
        
    case 'save_job':
		global $lang4325, $lang4326, $lang4327, $lang4328, $lang4329, $lang4330;
        $unique_id = $_POST['unique_id'] ?? '';
        $job_name = trim($_POST['job_name'] ?? '');
        $comment = trim($_POST['comment'] ?? '');
        $minute = trim($_POST['minute'] ?? '*');
        $hour = trim($_POST['hour'] ?? '*');
        $day = trim($_POST['day'] ?? '*');
        $month = trim($_POST['month'] ?? '*');
        $weekday = trim($_POST['weekday'] ?? '*');
        $job_type = $_POST['job_type'] ?? 'command';
        $command = trim($_POST['command'] ?? '');
        $script_name = trim($_POST['script_name'] ?? '');
        $enabled = isset($_POST['enabled']);
        
        if (empty($job_name)) {
            echo json_encode(['success' => false, 'error' => $lang4325]);
            break;
        }
        
        if ($job_type === 'command' && empty($command)) {
            echo json_encode(['success' => false, 'error' => $lang4326]);
            break;
        }
        
        if ($job_type === 'script' && empty($script_name)) {
            echo json_encode(['success' => false, 'error' => $lang4327]);
            break;
        }
        
        $jobs = getCronJobs();
        
        $new_job = [
            'unique_id' => empty($unique_id) ? uniqid('job_', true) : $unique_id,
            'job_name' => $job_name,
            'comment' => $comment,
            'minute' => $minute ?: '*',
            'hour' => $hour ?: '*',
            'day' => $day ?: '*',
            'month' => $month ?: '*',
            'weekday' => $weekday ?: '*',
            'job_type' => $job_type,
            'command' => $command,
            'script_name' => $script_name,
            'enabled' => $enabled,
            'created_at' => date('Y-m-d H:i:s'),
            'last_run' => null,
            'last_status' => 'pending',
            'last_output' => ''
        ];
        
        $found = false;
        foreach ($jobs as $idx => $job) {
            if (!empty($unique_id) && isset($job['unique_id']) && $job['unique_id'] === $unique_id) {
                $new_job['created_at'] = $job['created_at'];
                $new_job['last_run'] = $job['last_run'] ?? null;
                $new_job['last_status'] = $job['last_status'] ?? 'pending';
                $new_job['last_output'] = $job['last_output'] ?? '';
                $jobs[$idx] = $new_job;
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            $jobs[] = $new_job;
        }
        
        if (saveCronJobs($jobs)) {
            echo json_encode(['success' => true, 'message' => empty($unique_id) ? $lang4328 : $lang4329, 'data' => $new_job]);
        } else {
            echo json_encode(['success' => false, 'error' => $lang4330]);
        }
        break;
        
    case 'delete_job':
	global $lang4331, $lang4332, $lang4333;
        $unique_id = $_GET['unique_id'] ?? $_POST['unique_id'] ?? '';
        $jobs = getCronJobs();
        
        foreach ($jobs as $idx => $job) {
            if (isset($job['unique_id']) && $job['unique_id'] === $unique_id) {
                array_splice($jobs, $idx, 1);
                if (saveCronJobs($jobs)) {
                    echo json_encode(['success' => true, 'message' => $lang4331]);
                } else {
                    echo json_encode(['success' => false, 'error' => $lang4332]);
                }
                exit;
            }
        }
        
        echo json_encode(['success' => false, 'error' => $lang4333]);
        break;
        
    case 'toggle_job':
		global $lang4334, $lang4335, $lang4336, $lang4337;
        $unique_id = $_GET['unique_id'] ?? $_POST['unique_id'] ?? '';
        $jobs = getCronJobs();
        
        foreach ($jobs as $idx => &$job) {
            if (isset($job['unique_id']) && $job['unique_id'] === $unique_id) {
                $job['enabled'] = !$job['enabled'];
                if (saveCronJobs($jobs)) {
                    echo json_encode([
                        'success' => true, 
                        'message' => 'Job ' . ($job['enabled'] ? $lang4334 : $lang4335),
                        'enabled' => $job['enabled']
                    ]);
                } else {
                    echo json_encode(['success' => false, 'error' => $lang4336]);
                }
                exit;
            }
        }
        
        echo json_encode(['success' => false, 'error' => $lang4337]);
        break;
        
    case 'run_job':
		global $lang4338, $lang4339, $lang4340;
		$unique_id = $_GET['unique_id'] ?? $_POST['unique_id'] ?? '';
		$jobs = getCronJobs();
		
		foreach ($jobs as $idx => &$job) {
			if (isset($job['unique_id']) && $job['unique_id'] === $unique_id) {
				if (!$job['enabled']) {
					echo json_encode(['success' => false, 'error' => $lang4338]);
					exit;
				}
				
				$result = executeCronJob($job);
				
				$job['last_run'] = $result['last_run'];
				$job['last_status'] = $result['status'];
				$job['last_output'] = $result['output'];
				
				$log_entry = "[" . date("Y-m-d H:i:s") . "] MANUAL: " . $job['job_name'] . " - " . $result['status'] . "\n";
				if (!empty($result['full_output'])) {
					$log_entry .= "Output: " . $result['full_output'] . "\n";
				}
				file_put_contents(CRON_LOG_FILE, $log_entry, FILE_APPEND);
				
				if (saveCronJobs($jobs)) {
					echo json_encode([
						'success' => true,
						'message' => 'Job executed. Status: ' . $result['status'],
						'status' => $result['status'],
						'output' => nl2br(htmlspecialchars($result['full_output'])),
						'plain_output' => $result['full_output']
					]);
				} else {
					echo json_encode(['success' => false, 'error' => $lang4339]);
				}
				exit;
			}
		}
		
		echo json_encode(['success' => false, 'error' => $lang4340]);
		break;
        
    // ========== СКРИПТЫ ==========
    case 'get_scripts':
        echo json_encode(['success' => true, 'data' => getUserScripts()]);
        break;
        
    case 'get_script':
		global $lang4341, $lang4342;
        $filename = $_GET['filename'] ?? '';
        if (empty($filename)) {
            echo json_encode(['success' => false, 'error' => $lang4341]);
            break;
        }
        
        $content = getScriptContent($filename);
        if ($content === false) {
            echo json_encode(['success' => false, 'error' => $lang4342]);
        } else {
            echo json_encode(['success' => true, 'content' => $content, 'filename' => $filename]);
        }
        break;
        
    case 'save_script':
		global $lang4343;
        $filename = $_POST['filename'] ?? '';
        $content = $_POST['content'] ?? '';
        $make_executable = isset($_POST['make_executable']);
        
        if (empty($filename)) {
            echo json_encode(['success' => false, 'error' => $lang4343]);
            break;
        }
        
        $result = saveScript($filename, $content, $make_executable);
        echo json_encode($result);
        break;
        
    case 'delete_script':
		global $lang4344;
        $filename = $_GET['filename'] ?? $_POST['filename'] ?? '';
        if (empty($filename)) {
            echo json_encode(['success' => false, 'error' => $lang4344]);
            break;
        }
        
        $result = deleteScript($filename);
        echo json_encode($result);
        break;
        
    case 'get_script_template':
        $type = $_GET['type'] ?? 'bash';
        $name = $_GET['name'] ?? 'script';
        $date = date('Y-m-d H:i:s');
        $template = getScriptTemplate($type);
        $template = str_replace(['{name}', '{date}'], [$name, $date], $template);
        echo json_encode(['success' => true, 'template' => $template]);
        break;
        
    case 'run_script':
		global $lang4345, $lang4346, $lang4347, $lang4348;
        $filename = $_GET['filename'] ?? '';
        if (empty($filename)) {
            echo json_encode(['success' => false, 'error' => $lang4345]);
            break;
        }
        $fullPath = USER_SCRIPTS_DIR . '/' . $filename;
        if (!file_exists($fullPath)) {
            echo json_encode(['success' => false, 'error' => $lang4346]);
            break;
        }
        if (!is_executable($fullPath)) {
            chmod($fullPath, 0755);
        }
        $output = [];
        $return_var = 0;
        $full_cmd = "export LC_ALL=en_US.UTF-8 && export LANG=en_US.UTF-8 && export PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin && " . $fullPath;
        exec($full_cmd . " 2>&1", $output, $return_var);
        
        $output_str = implode("\n", $output);
        $output_str = cleanOutput($output_str);
        
        echo json_encode([
            'success' => true,
            'status' => $return_var === 0 ? 'success' : 'failed',
            'output' => $output_str,
            'message' => $return_var === 0 ? $lang4347 : $lang4348 . $return_var
        ]);
        break;
        
    // ========== ЛОГИ ==========
    case 'get_logs':
        $lines = isset($_GET['lines']) ? (int)$_GET['lines'] : 100;
        $logs = getCronLogs($lines);
        echo json_encode(['success' => true, 'logs' => $logs]);
        break;
        
    case 'clear_logs':
		global $lang4349, $lang4350;
        if (clearCronLogs()) {
            echo json_encode(['success' => true, 'message' => $lang4349]);
        } else {
            echo json_encode(['success' => false, 'error' => $lang4350]);
        }
        break;
        
    // ========== НАСТРОЙКИ RUNNER ==========
    case 'get_runner_status':
        ensureRunnerScript();
        echo json_encode([
            'success' => true,
            'runner_exists' => file_exists(CRON_RUNNER_FILE),
            'runner_installed' => isRunnerInSystemCron(),
            'runner_command' => getRunnerCommand(),
            'cron_jobs_file' => CRON_JOBS_FILE,
            'cron_log_file' => CRON_LOG_FILE,
            'scripts_dir' => USER_SCRIPTS_DIR
        ]);
        break;
        
    case 'install_runner':
		global $lang4351, $lang4352;
        ensureRunnerScript();
        $command = getRunnerCommand();
        if (addSystemCronEntry($command)) {
            echo json_encode(['success' => true, 'message' => $lang4351]);
        } else {
            echo json_encode(['success' => false, 'error' => $lang4352]);
        }
        break;
        
    case 'uninstall_runner':
		global $lang4353, $lang4354;
        $command = getRunnerCommand();
        if (removeSystemCronEntry($command)) {
            echo json_encode(['success' => true, 'message' => $lang4353]);
        } else {
            echo json_encode(['success' => false, 'error' => $lang4354]);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
}
?>