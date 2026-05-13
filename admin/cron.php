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

$message = '';
$error = '';

$cron_file = __DIR__ . '/cron_jobs.json';
$log_file = __DIR__ . '/cron.log';
$status_file = __DIR__ . '/cron_status.json';

function getCrontabEntries() {
    global $cron_file;
    
    if (!file_exists($cron_file)) {
        return [];
    }
    
    $content = file_get_contents($cron_file);
    $entries = json_decode($content, true);
    
    if (!is_array($entries)) {
        return [];
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
        $entry['index'] = $idx;
    }
    
    return $entries;
}

function saveCrontabEntries($entries) {
    global $cron_file;
    
    $to_save = [];
    foreach ($entries as $entry) {
        unset($entry['index']);
        $to_save[] = $entry;
    }
    
    return file_put_contents($cron_file, json_encode($to_save, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false;
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    $response = ['success' => false, 'message' => ''];
    
    if ($action === 'save') {
        $job_name = trim($_POST['job_name'] ?? '');
        $comment = trim($_POST['comment'] ?? '');
        $minute = trim($_POST['minute'] ?? '*');
        $hour = trim($_POST['hour'] ?? '*');
        $day = trim($_POST['day'] ?? '*');
        $month = trim($_POST['month'] ?? '*');
        $weekday = trim($_POST['weekday'] ?? '*');
        $command = trim($_POST['command'] ?? '');
        $enabled = isset($_POST['enabled']) ? true : false;
        $edit_unique_id = $_POST['edit_unique_id'] ?? '';
        
        if (empty($job_name) || empty($command)) {
            $response['message'] = 'Job name and command are required';
            echo json_encode($response);
            exit;
        }
        
        $entries = getCrontabEntries();
        
        $new_entry = [
            'unique_id' => empty($edit_unique_id) ? uniqid('job_', true) : $edit_unique_id,
            'job_name' => $job_name,
            'comment' => $comment,
            'minute' => $minute ?: '*',
            'hour' => $hour ?: '*',
            'day' => $day ?: '*',
            'month' => $month ?: '*',
            'weekday' => $weekday ?: '*',
            'command' => $command,
            'enabled' => $enabled,
            'created_at' => date('Y-m-d H:i:s'),
            'last_run' => null,
            'last_status' => 'pending',
            'last_output' => ''
        ];
        
        $found = false;
        foreach ($entries as $idx => $job) {
            if (isset($job['unique_id']) && $job['unique_id'] === $edit_unique_id && !empty($edit_unique_id)) {
                $new_entry['created_at'] = $job['created_at'];
                $new_entry['last_run'] = $job['last_run'] ?? null;
                $new_entry['last_status'] = $job['last_status'] ?? 'pending';
                $new_entry['last_output'] = $job['last_output'] ?? '';
                $entries[$idx] = $new_entry;
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            $entries[] = $new_entry;
        }
        
        if (saveCrontabEntries($entries)) {
            $response['success'] = true;
            $response['message'] = empty($edit_unique_id) ? 'Cron job added successfully' : 'Cron job updated successfully';
        } else {
            $response['message'] = 'Failed to save cron job';
        }
    }
    
    echo json_encode($response);
    exit;
}

if (isset($_GET['ajax_delete'])) {
    header('Content-Type: application/json');
    $unique_id = $_GET['ajax_delete'];
    $entries = getCrontabEntries();
    $response = ['success' => false, 'message' => ''];
    
    foreach ($entries as $idx => $job) {
        if (isset($job['unique_id']) && $job['unique_id'] === $unique_id) {
            array_splice($entries, $idx, 1);
            if (saveCrontabEntries($entries)) {
                $response['success'] = true;
                $response['message'] = 'Cron job deleted';
            }
            break;
        }
    }
    
    echo json_encode($response);
    exit;
}

if (isset($_GET['ajax_toggle'])) {
    header('Content-Type: application/json');
    $unique_id = $_GET['ajax_toggle'];
    $entries = getCrontabEntries();
    $response = ['success' => false, 'message' => ''];
    
    foreach ($entries as $idx => &$job) {
        if (isset($job['unique_id']) && $job['unique_id'] === $unique_id) {
            $job['enabled'] = !$job['enabled'];
            if (saveCrontabEntries($entries)) {
                $response['success'] = true;
                $response['message'] = 'Cron job ' . ($job['enabled'] ? 'enabled' : 'disabled');
                $response['enabled'] = $job['enabled'];
            }
            break;
        }
    }
    
    echo json_encode($response);
    exit;
}

if (isset($_GET['ajax_run'])) {
    header('Content-Type: application/json');
    $unique_id = $_GET['ajax_run'];
    $entries = getCrontabEntries();
    $response = ['success' => false, 'message' => ''];
    
    foreach ($entries as $idx => &$job) {
        if (isset($job['unique_id']) && $job['unique_id'] === $unique_id) {
            if (!$job['enabled']) {
                $response['message'] = 'Job is disabled';
                echo json_encode($response);
                exit;
            }
            
            $cmd = $job['command'];
            $output = [];
            $return_var = 0;
            exec($cmd . " 2>&1", $output, $return_var);
            
            $output_str = implode("\n", $output);
            $status = ($return_var === 0) ? 'success' : 'failed';
            
            $job['last_run'] = date('Y-m-d H:i:s');
            $job['last_status'] = $status;
            $job['last_output'] = substr($output_str, 0, 500);
            saveCrontabEntries($entries);
            
            $response['success'] = true;
            $response['message'] = 'Job executed. Status: ' . $status;
            $response['status'] = $status;
            $response['output'] = nl2br(htmlspecialchars(substr($output_str, 0, 1000)));
            break;
        }
    }
    
    echo json_encode($response);
    exit;
}

if (isset($_GET['ajax_get_job'])) {
    header('Content-Type: application/json');
    $unique_id = $_GET['ajax_get_job'];
    $entries = getCrontabEntries();
    
    foreach ($entries as $job) {
        if (isset($job['unique_id']) && $job['unique_id'] === $unique_id) {
            echo json_encode(['success' => true, 'job' => $job]);
            exit;
        }
    }
    
    echo json_encode(['success' => false, 'message' => 'Job not found']);
    exit;
}

if (isset($_GET['ajax_get_status'])) {
    header('Content-Type: application/json');
    $unique_id = $_GET['ajax_get_status'];
    $entries = getCrontabEntries();
    
    foreach ($entries as $job) {
        if (isset($job['unique_id']) && $job['unique_id'] === $unique_id) {
            echo json_encode([
                'success' => true,
                'last_run' => $job['last_run'] ?? null,
                'last_status' => $job['last_status'] ?? 'pending',
                'last_output' => $job['last_output'] ?? ''
            ]);
            exit;
        }
    }
    
    echo json_encode(['success' => false, 'message' => 'Job not found']);
    exit;
}

$crons = getCrontabEntries();

$runner_script = __DIR__ . '/cron_runner.php';
if (!file_exists($runner_script)) {
    $runner_content = '<?php
$cron_file = __DIR__ . "/cron_jobs.json";
$log_file = __DIR__ . "/cron.log";

if (!file_exists($cron_file)) exit(0);

$jobs = json_decode(file_get_contents($cron_file), true);
if (!is_array($jobs)) exit(0);

$now = time();
$current_minute = date("i", $now);
$current_hour = date("H", $now);
$current_day = date("d", $now);
$current_month = date("m", $now);
$current_weekday = date("w", $now);

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
    return (int)$cron_value === (int)$current_value;
}

foreach ($jobs as &$job) {
    if (!$job["enabled"]) continue;
    
    if (check_cron_time($job["minute"], (int)$current_minute) &&
        check_cron_time($job["hour"], (int)$current_hour) &&
        check_cron_time($job["day"], (int)$current_day) &&
        check_cron_time($job["month"], (int)$current_month) &&
        check_cron_time($job["weekday"], (int)$current_weekday)) {
        
        $output = [];
        $return_var = 0;
        exec($job["command"] . " 2>&1", $output, $return_var);
        
        $job["last_run"] = date("Y-m-d H:i:s");
        $job["last_status"] = ($return_var === 0) ? "success" : "failed";
        $job["last_output"] = substr(implode("\n", $output), 0, 500);
        
        file_put_contents($log_file, "[" . date("Y-m-d H:i:s") . "] " . $job["job_name"] . " - " . $job["last_status"] . "\n", FILE_APPEND);
    }
}

file_put_contents($cron_file, json_encode($jobs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
';
    file_put_contents($runner_script, $runner_content);
}

$menu = require_once 'menu.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Cron Jobs — Mini-b</title>
    
    <link href="lib/bootstrap-5.3.8-dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="lib/bootstrap-icons-1.11.0/bootstrap-icons.css">
    <!--<link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">-->
    <link rel="stylesheet" href="lib/fontawesome-free-6.7.2-web/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="css/loader.css">
    <link rel="shortcut icon" href="css/icon.ico" type="image/x-icon">
    
    <style>
        * {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e9eef3 100%);
            min-height: 100vh;
        }
        
        .main-content {
            padding: 25px 30px;
        }
        
        @media (max-width: 768px) {
            .main-content {
                padding: 20px 15px;
            }
        }
        
        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .content-header h1 {
            font-size: 28px;
            font-weight: 600;
            letter-spacing: -0.3px;
            background: linear-gradient(135deg, #1c1c1e, #3a3a3c);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 0;
        }
        
        .apple-card {
            background: rgba(255, 255, 255, 0.96);
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
        
        .job-card {
            background: white;
            border-radius: 20px;
            border: 1px solid #e9ecef;
            transition: all 0.2s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .job-card:hover {
            border-color: #007aff40;
            box-shadow: 0 8px 20px rgba(0,122,255,0.1);
        }
        
        .job-card.enabled {
            border-left: 4px solid #34c759;
        }
        
        .job-card.disabled {
            border-left: 4px solid #8e8e93;
            opacity: 0.7;
        }
        
        .job-card-header {
            padding: 16px 20px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        
        .job-card-body {
            padding: 16px 20px;
            flex: 1;
        }
        
        .job-card-footer {
            padding: 12px 20px;
            border-top: 1px solid #e9ecef;
            background: #f8f9fa;
            font-size: 12px;
            color: #6c757d;
        }
        
        .job-name {
            font-weight: 600;
            font-size: 16px;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .job-comment {
            font-size: 13px;
            color: #6c757d;
            margin-top: 4px;
            font-style: italic;
        }
        
        .schedule-code {
            font-family: 'SF Mono', Monaco, monospace;
            font-size: 12px;
            background: #f8f9fa;
            padding: 6px 10px;
            border-radius: 10px;
            margin: 10px 0;
            word-break: break-all;
        }
        
        .command-code {
            font-family: 'SF Mono', Monaco, monospace;
            font-size: 11px;
            background: #1c1c1e;
            color: #e5e5ea;
            padding: 8px 10px;
            border-radius: 10px;
            margin: 10px 0;
            word-break: break-all;
            overflow-x: auto;
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
        
        .status-success {
            background: #34c75920;
            color: #248a3d;
        }
        
        .status-failed {
            background: #ff3b3020;
            color: #d70015;
        }
        
        .status-pending {
            background: #e5e5ea;
            color: #6c757d;
        }
        
        .badge-enabled {
            background: #34c75920;
            color: #248a3d;
        }
        
        .badge-disabled {
            background: #e5e5ea;
            color: #6c757d;
        }
        
        .run-time {
            font-size: 12px;
            color: #6c757d;
            display: flex;
            align-items: center;
            gap: 6px;
            margin-top: 8px;
        }
        
        .output-preview {
            font-family: 'SF Mono', Monaco, monospace;
            font-size: 10px;
            background: #f8f9fa;
            padding: 8px;
            border-radius: 10px;
            margin-top: 10px;
            max-height: 60px;
            overflow-y: auto;
            color: #495057;
        }
        
        .card-actions-dropdown {
            position: relative;
        }
        
        .dropdown-toggle-actions {
            background: none;
            border: none;
            padding: 8px;
            border-radius: 8px;
            color: #6c757d;
            transition: all 0.2s;
            cursor: pointer;
        }
        
        .dropdown-toggle-actions:hover {
            background: #f8f9fa;
            color: #007aff;
        }
        
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
        
        .dropdown-menu-actions.show {
            display: block;
        }
        
        .dropdown-menu-actions a {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            color: #1c1c1e;
            text-decoration: none;
            font-size: 13px;
            transition: background 0.2s;
        }
        
        .dropdown-menu-actions a:hover {
            background: #f8f9fa;
        }
        
        .dropdown-menu-actions a i {
            width: 18px;
            font-size: 14px;
        }
        
        .dropdown-divider {
            height: 1px;
            background: #e9ecef;
            margin: 4px 0;
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
            display: inline-flex;
            align-items: center;
            gap: 8px;
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
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-apple-outline:hover {
            background: #007aff10;
            transform: scale(0.98);
        }
        
        .modal-apple .modal-content {
            border-radius: 24px;
            border: none;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        
        .modal-apple .modal-header {
            border-bottom: 1px solid #e9ecef;
            background: linear-gradient(135deg, #f8f9fa, #ffffff);
            border-radius: 24px 24px 0 0;
            padding: 20px 24px;
        }
        
        .modal-apple .modal-body {
            padding: 24px;
        }
        
        .modal-apple .modal-footer {
            border-top: 1px solid #e9ecef;
            padding: 16px 24px;
        }
        
        .form-control-apple, .form-select-apple {
            border-radius: 12px;
            border: 1px solid #e9ecef;
            padding: 10px 14px;
            transition: all 0.2s;
        }
        
        .form-control-apple:focus, .form-select-apple:focus {
            border-color: #007aff;
            box-shadow: 0 0 0 3px rgba(0,122,255,0.1);
            outline: none;
        }
        
        .cron-preset {
            font-size: 11px;
            cursor: pointer;
            display: inline-block;
            margin-right: 6px;
            margin-bottom: 6px;
            padding: 4px 10px;
            border-radius: 20px;
            background: #e9ecef;
            color: #495057;
            transition: all 0.2s;
        }
        
        .cron-preset:hover {
            background: #007aff;
            color: white;
        }
        
        .alert-apple {
            border-radius: 14px;
            border: none;
            background: white;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        
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
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 20px;
        }
        
        .empty-state i {
            font-size: 48px;
            color: #c6c6c8;
            margin-bottom: 16px;
        }
        
        .help-box {
            background: linear-gradient(135deg, #f8f9fa, #ffffff);
            border-radius: 20px;
            padding: 20px;
            margin-top: 24px;
        }
        
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 44px;
            height: 24px;
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
            border-radius: 24px;
        }
        
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 20px;
            width: 20px;
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
	<text><i class="bi bi-clock-history"></i> Cron Jobs</text>
    <button class="btn btn-success btn-sm" onclick="openCronModal()">
                <i class="bi bi-plus-lg"></i> Add Cron Job
            </button>
	</div>
</div>

<div class="app-container">
    <?php echo $menu; ?>
    
    <main class="main-content">
        
        <div id="alertContainer"></div>
        
        <?php if(empty($crons)): ?>
            <div class="empty-state">
                <i class="bi bi-calendar2-x"></i>
                <h5>No Cron Jobs</h5>
                <p class="text-muted">Click "Add Cron Job" to create your first scheduled task</p>
                <button class="btn-apple" onclick="openCronModal()">
                    <i class="bi bi-plus-lg"></i> Create Cron Job
                </button>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach($crons as $cron): 
                    $next_run = $cron['enabled'] ? getNextRunTime($cron) : 'Disabled';
                ?>
                    <div class="col-md-6 col-lg-4" data-job-id="<?= htmlspecialchars($cron['unique_id']) ?>">
                        <div class="job-card <?= $cron['enabled'] ? 'enabled' : 'disabled' ?>">
                            <div class="job-card-header">
                                <div>
                                    <div class="job-name">
                                        <?php if($cron['enabled']): ?>
                                            <span class="status-badge status-success"><i class="bi bi-play-fill"></i> Active</span>
                                        <?php else: ?>
                                            <span class="status-badge status-pending"><i class="bi bi-pause-fill"></i> Paused</span>
                                        <?php endif; ?>
                                        
                                        <?php if($cron['last_status'] === 'success' && $cron['enabled']): ?>
                                            <span class="status-badge status-success"><i class="bi bi-check-circle"></i> OK</span>
                                        <?php elseif($cron['last_status'] === 'failed' && $cron['enabled']): ?>
                                            <span class="status-badge status-failed"><i class="bi bi-exclamation-circle"></i> Failed</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="fw-semibold fs-6 mt-1"><?= htmlspecialchars($cron['job_name']) ?></div>
                                    <?php if(!empty($cron['comment'])): ?>
                                        <div class="job-comment"><?= htmlspecialchars($cron['comment']) ?></div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="card-actions-dropdown">
                                    <button class="dropdown-toggle-actions" onclick="toggleDropdown(this)">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <div class="dropdown-menu-actions">
                                        <a href="#" onclick="runJob('<?= htmlspecialchars($cron['unique_id']) ?>'); return false;">
                                            <i class="bi bi-play-fill"></i> Run Now
                                        </a>
                                        <a href="#" onclick="toggleJob('<?= htmlspecialchars($cron['unique_id']) ?>'); return false;">
                                            <i class="bi bi-<?= $cron['enabled'] ? 'pause-fill' : 'play-fill' ?>"></i> <?= $cron['enabled'] ? 'Disable' : 'Enable' ?>
                                        </a>
                                        <a href="#" onclick="editJob('<?= htmlspecialchars($cron['unique_id']) ?>'); return false;">
                                            <i class="bi bi-pencil"></i> Edit
                                        </a>
                                        <div class="dropdown-divider"></div>
                                        <a href="#" onclick="deleteJob('<?= htmlspecialchars($cron['unique_id']) ?>'); return false;" style="color: #ff3b30;">
                                            <i class="bi bi-trash3"></i> Delete
                                        </a>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="job-card-body">
                                <div class="schedule-code">
                                    <i class="bi bi-calendar3 me-1"></i>
                                    <code><?= htmlspecialchars($cron['minute'] . ' ' . $cron['hour'] . ' ' . $cron['day'] . ' ' . $cron['month'] . ' ' . $cron['weekday']) ?></code>
                                </div>
                                
                                <div class="command-code">
                                    <i class="bi bi-terminal me-1"></i>
                                    <code><?= htmlspecialchars($cron['command']) ?></code>
                                </div>
                                
                                <div class="run-time">
                                    <i class="bi bi-hourglass-split"></i>
                                    <span>Next: <strong><?= htmlspecialchars($next_run) ?></strong></span>
                                </div>
                                
                                <?php if($cron['last_run']): ?>
                                    <div class="run-time">
                                        <i class="bi bi-clock-history"></i>
                                        <span>Last: <?= htmlspecialchars($cron['last_run']) ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if(!empty($cron['last_output'])): ?>
                                    <div class="output-preview">
                                        <i class="bi bi-code-square"></i> Last output:<br>
                                        <code><?= nl2br(htmlspecialchars(substr($cron['last_output'], 0, 120))) ?></code>
                                        <?php if(strlen($cron['last_output']) > 120): ?>
                                            <button class="btn btn-link btn-sm p-0 mt-1" onclick="showFullOutput('<?= htmlspecialchars($cron['unique_id']) ?>')" style="font-size: 10px;">Show more...</button>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="job-card-footer">
                                <i class="bi bi-calendar-plus"></i> Created: <?= htmlspecialchars($cron['created_at'] ?? '-') ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <div class="help-box">
            <div class="d-flex align-items-start gap-3">
                <i class="bi bi-info-circle-fill fs-4" style="color: #007aff;"></i>
                <div>
                    <strong>How to enable automatic execution:</strong><br>
                    <code class="bg-dark text-light p-1 px-2 rounded d-inline-block mt-1">* * * * * php <?= $runner_script ?> > /dev/null 2>&1</code>
                    <div class="small text-muted mt-2">
                        Add this line to your system crontab (<code>crontab -e</code>). Jobs run every minute based on schedule.
                        Logs stored in <code><?= __DIR__ ?>/cron.log</code>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Modal for Add/Edit Cron Job -->
<div class="modal fade modal-apple" id="cronModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-semibold"><i class="bi bi-clock me-2"></i><span id="modalTitle">Add Cron Job</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="cronForm">
                    <input type="hidden" id="editUniqueId" name="edit_unique_id" value="">
                    
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold">Job Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-apple" id="job_name" name="job_name" placeholder="e.g., Database Backup" required>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label fw-semibold">Description (Optional)</label>
                            <textarea class="form-control form-control-apple" id="comment" name="comment" rows="2" placeholder="What does this job do?"></textarea>
                        </div>
                        
                        <div class="row g-2">
                            <div class="col-md-2">
                                <label class="form-label small fw-semibold">Minute</label>
                                <input type="text" class="form-control form-control-apple" id="minute" name="minute" value="*" placeholder="*">
                                <small class="text-muted" style="font-size: 9px;">0-59,*</small>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-semibold">Hour</label>
                                <input type="text" class="form-control form-control-apple" id="hour" name="hour" value="*" placeholder="*">
                                <small class="text-muted" style="font-size: 9px;">0-23,*</small>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-semibold">Day</label>
                                <input type="text" class="form-control form-control-apple" id="day" name="day" value="*" placeholder="*">
                                <small class="text-muted" style="font-size: 9px;">1-31,*</small>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-semibold">Month</label>
                                <input type="text" class="form-control form-control-apple" id="month" name="month" value="*" placeholder="*">
                                <small class="text-muted" style="font-size: 9px;">1-12,*</small>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-semibold">Weekday</label>
                                <input type="text" class="form-control form-control-apple" id="weekday" name="weekday" value="*" placeholder="*">
                                <small class="text-muted" style="font-size: 9px;">0-7,*</small>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-semibold">Status</label>
                                <div class="mt-2">
                                    <label class="toggle-switch">
                                        <input type="checkbox" id="enabled" name="enabled" checked>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label fw-semibold">Command <span class="text-danger">*</span></label>
                            <textarea class="form-control form-control-apple" id="command" name="command" rows="3" placeholder="/usr/bin/php /path/to/script.php"></textarea>
                        </div>
                    </div>
                    
                    <hr class="my-3">
                    
                    <div>
                        <strong class="small">Quick Presets:</strong><br>
                        <span class="cron-preset" onclick="setCronPreset('*/5', '*', '*', '*', '*')">Every 5 min</span>
                        <span class="cron-preset" onclick="setCronPreset('0', '*', '*', '*', '*')">Every hour</span>
                        <span class="cron-preset" onclick="setCronPreset('0', '0', '*', '*', '*')">Daily at midnight</span>
                        <span class="cron-preset" onclick="setCronPreset('0', '2', '*', '*', '1')">Monday 2 AM</span>
                        <span class="cron-preset" onclick="setCronPreset('0', '*/2', '*', '*', '*')">Every 2 hours</span>
                        <span class="cron-preset" onclick="setCronPreset('30', '9', '*', '*', '1-5')">Weekdays 9:30 AM</span>
                    </div>
                    
                    <div class="mt-3 p-3 bg-light rounded-3">
                        <strong>Preview:</strong>
                        <code id="schedulePreview" class="d-block mt-1">* * * * *</code>
                        <span id="humanPreview" class="small text-muted"></span>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary rounded-3" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn-apple" onclick="saveCronJob()">
                    <i class="bi bi-save"></i> Save Job
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal for Full Output -->
<div class="modal fade modal-apple" id="outputModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-semibold"><i class="bi bi-code-square"></i> Command Output</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <pre id="fullOutput" style="white-space: pre-wrap; word-wrap: break-word; max-height: 400px; overflow-y: auto; background: #1c1c1e; color: #e5e5ea; padding: 16px; border-radius: 12px; font-family: monospace; font-size: 12px;"></pre>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary rounded-3" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="lib/jquery-3.6.0-master/dist/jquery.min.js"></script>
<script src="lib/bootstrap-5.3.8-dist/js/bootstrap.bundle.min.js"></script>
<script src="js/loader.js"></script>

<script>
let currentModal = null;
let outputModal = null;

function showLoading() {
    if (!$('.loading-overlay').length) {
        $('body').append('<div class="loading-overlay"><div class="spinner-border text-light" style="width: 3rem; height: 3rem;"></div></div>');
    }
    $('.loading-overlay').show();
}

function hideLoading() {
    $('.loading-overlay').hide();
}

function toggleDropdown(btn) {
    $('.dropdown-menu-actions').not($(btn).next()).removeClass('show');
    $(btn).next('.dropdown-menu-actions').toggleClass('show');
}

$(document).on('click', function(e) {
    if (!$(e.target).closest('.card-actions-dropdown').length) {
        $('.dropdown-menu-actions').removeClass('show');
    }
});

function updatePreview() {
    const minute = $('#minute').val() || '*';
    const hour = $('#hour').val() || '*';
    const day = $('#day').val() || '*';
    const month = $('#month').val() || '*';
    const weekday = $('#weekday').val() || '*';
    $('#schedulePreview').text(`${minute} ${hour} ${day} ${month} ${weekday}`);
    
    let desc = [];
    if (minute === '0') desc.push('at minute 0');
    else if (minute === '*') desc.push('every minute');
    else if (minute.startsWith('*/')) desc.push('every ' + minute.substring(2) + ' min');
    else desc.push('min ' + minute);
    
    if (hour === '*') desc.push('every hour');
    else if (hour.startsWith('*/')) desc.push('every ' + hour.substring(2) + ' hours');
    else if (hour.includes('-')) desc.push('hours ' + hour);
    else desc.push(hour + ':00');
    
    if (day !== '*') desc.push('day ' + day);
    if (month !== '*') desc.push('month ' + month);
    if (weekday !== '*') {
        const weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        desc.push(weekdays[parseInt(weekday)] || weekday);
    }
    $('#humanPreview').text(desc.join(', '));
}

function setCronPreset(minute, hour, day, month, weekday) {
    if (minute) $('#minute').val(minute);
    if (hour) $('#hour').val(hour);
    if (day) $('#day').val(day);
    if (month) $('#month').val(month);
    if (weekday) $('#weekday').val(weekday);
    updatePreview();
}

function openCronModal() {
    $('#modalTitle').text('Add Cron Job');
    $('#cronForm')[0].reset();
    $('#minute').val('*');
    $('#hour').val('*');
    $('#day').val('*');
    $('#month').val('*');
    $('#weekday').val('*');
    $('#enabled').prop('checked', true);
    $('#editUniqueId').val('');
    updatePreview();
    currentModal = new bootstrap.Modal(document.getElementById('cronModal'));
    currentModal.show();
}

function editJob(uniqueId) {
    showLoading();
    $.ajax({
        url: `?ajax_get_job=${encodeURIComponent(uniqueId)}`,
        method: 'GET',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    }).done(function(data) {
        hideLoading();
        if (data.success) {
            $('#modalTitle').text('Edit Cron Job');
            $('#editUniqueId').val(data.job.unique_id);
            $('#job_name').val(data.job.job_name);
            $('#comment').val(data.job.comment || '');
            $('#minute').val(data.job.minute);
            $('#hour').val(data.job.hour);
            $('#day').val(data.job.day);
            $('#month').val(data.job.month);
            $('#weekday').val(data.job.weekday);
            $('#command').val(data.job.command);
            $('#enabled').prop('checked', data.job.enabled);
            updatePreview();
            currentModal = new bootstrap.Modal(document.getElementById('cronModal'));
            currentModal.show();
        } else {
            showAlert('danger', data.message || 'Error loading job');
        }
    }).fail(function() {
        hideLoading();
        showAlert('danger', 'Network error');
    });
}

function saveCronJob() {
    const jobName = $('#job_name').val().trim();
    const command = $('#command').val().trim();
    
    if (!jobName) { showAlert('danger', 'Job name is required'); return; }
    if (!command) { showAlert('danger', 'Command is required'); return; }
    
    showLoading();
    
    $.ajax({
        url: '',
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        data: $('#cronForm').serialize() + '&action=save'
    }).done(function(data) {
        hideLoading();
        if (data.success) {
            if (currentModal) currentModal.hide();
            showAlert('success', data.message);
            setTimeout(() => location.reload(), 1000);
        } else {
            showAlert('danger', data.message);
        }
    }).fail(function() {
        hideLoading();
        showAlert('danger', 'Error saving cron job');
    });
}

function deleteJob(uniqueId) {
    if (confirm('Delete this cron job?')) {
        showLoading();
        $.ajax({
            url: `?ajax_delete=${encodeURIComponent(uniqueId)}`,
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).done(function(data) {
            hideLoading();
            if (data.success) {
                showAlert('success', data.message);
                location.reload();
            } else {
                showAlert('danger', data.message);
            }
        }).fail(function() {
            hideLoading();
            showAlert('danger', 'Error deleting job');
        });
    }
}

function toggleJob(uniqueId) {
    showLoading();
    $.ajax({
        url: `?ajax_toggle=${encodeURIComponent(uniqueId)}`,
        method: 'GET',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    }).done(function(data) {
        hideLoading();
        if (data.success) {
            showAlert('success', data.message);
            location.reload();
        } else {
            showAlert('danger', data.message);
        }
    }).fail(function() {
        hideLoading();
        showAlert('danger', 'Error toggling job');
    });
}

function runJob(uniqueId) {
    $('.dropdown-menu-actions').removeClass('show');
    showLoading();
    showAlert('info', 'Executing job...');
    
    $.ajax({
        url: `?ajax_run=${encodeURIComponent(uniqueId)}`,
        method: 'GET',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    }).done(function(data) {
        hideLoading();
        if (data.success) {
            showAlert(data.status === 'success' ? 'success' : 'warning', data.message);
            if (data.output) {
                const plainOutput = data.output.replace(/<br\s*\/?>/gi, '\n');
                $('#fullOutput').text(plainOutput);
                outputModal = new bootstrap.Modal(document.getElementById('outputModal'));
                outputModal.show();
            }
            setTimeout(() => location.reload(), 2000);
        } else {
            showAlert('danger', data.message);
        }
    }).fail(function() {
        hideLoading();
        showAlert('danger', 'Error running job');
    });
}

function showFullOutput(uniqueId) {
    showLoading();
    $.ajax({
        url: `?ajax_get_status=${encodeURIComponent(uniqueId)}`,
        method: 'GET',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    }).done(function(data) {
        hideLoading();
        if (data.success && data.last_output) {
            $('#fullOutput').text(data.last_output);
            outputModal = new bootstrap.Modal(document.getElementById('outputModal'));
            outputModal.show();
        } else {
            showAlert('info', 'No output available');
        }
    }).fail(function() {
        hideLoading();
        showAlert('danger', 'Error loading output');
    });
}

function showAlert(type, message) {
    const alertDiv = $(`
        <div class="alert alert-${type} alert-dismissible fade show alert-apple mb-3">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `);
    $('#alertContainer').append(alertDiv);
    setTimeout(() => alertDiv.fadeOut(() => alertDiv.remove()), 4000);
}

$('#minute, #hour, #day, #month, #weekday').on('input change', updatePreview);
updatePreview();
</script>
</body>
</html>