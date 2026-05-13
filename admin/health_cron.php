#!/usr/bin/env php
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

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', '/var/www');
}

$configPath = ROOT_PATH . '/html/admin/config.php';
if (file_exists($configPath)) {
    require_once $configPath;
} else {
    error_log("Health Cron: config.php not found at {$configPath}");
    exit(1);
}

$tmpDir = ROOT_PATH . '/minib/tmp';
if (!is_dir($tmpDir)) {
    @mkdir($tmpDir, 0755, true);
}

set_time_limit(0);
ini_set('memory_limit', '512M');

$lockFile = $tmpDir . '/cron_executor.lock';
$fp = @fopen($lockFile, 'w');
if (!$fp) {
    error_log("Health Cron: Cannot create lock file");
    exit(1);
}

if (!flock($fp, LOCK_EX | LOCK_NB)) {
    error_log("Health Cron: Another instance is already running");
    fclose($fp);
    exit(1);
}

try {
    $db = getDB();
    initScheduleTables($db);
    
    $currentTime = date('Y-m-d H:i:s');
    $stmt = $db->prepare("
        SELECT * FROM check_schedules 
        WHERE enabled = 1 
          AND (next_run IS NULL OR next_run <= :now)
        ORDER BY CASE WHEN next_run IS NULL THEN 0 ELSE 1 END, next_run ASC
    ");
    $stmt->bindValue(':now', $currentTime, SQLITE3_TEXT);
    $result = $stmt->execute();
    
    $executed = 0;
    $allResults = [];
    
    while ($schedule = $result->fetchArray(SQLITE3_ASSOC)) {
        $executed++;
        $checkResult = executeCheckAndGetResult($db, $schedule);
        if ($checkResult) {
            $allResults[] = $checkResult;
        }
        
        if ($executed < 5) {
            usleep(500000);
        }
    }
    
    sendConsolidatedReport($db, $allResults, $executed);
    
    $db->exec("DELETE FROM check_history WHERE created_at < datetime('now', '-30 days')");
    
    if ($executed > 0) {
        error_log("Health Cron: Executed $executed checks");
    }
    
} catch (Exception $e) {
    error_log("Health Cron Error: " . $e->getMessage());
} finally {
    if ($fp) {
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}

function initScheduleTables($db) {
    $queries = [
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
        "CREATE INDEX IF NOT EXISTS idx_check_history_type ON check_history(check_type)",
        "CREATE INDEX IF NOT EXISTS idx_check_history_created ON check_history(created_at)",
        "CREATE INDEX IF NOT EXISTS idx_schedules_next_run ON check_schedules(next_run)"
    ];
    
    foreach ($queries as $query) {
        try {
            $db->exec($query);
        } catch (Exception $e) {}
    }
    
    $defaultSchedules = [
        ['disks', 3600],
        ['raid', 300],
        ['lvm', 600],
        ['temperature', 120],
        ['shares', 900],
        ['all', 86400]
    ];
    
    foreach ($defaultSchedules as $schedule) {
        $stmt = $db->prepare("INSERT OR IGNORE INTO check_schedules (check_type, interval_seconds, enabled) VALUES (:type, :interval, 1)");
        $stmt->bindValue(':type', $schedule[0], SQLITE3_TEXT);
        $stmt->bindValue(':interval', $schedule[1], SQLITE3_INTEGER);
        $stmt->execute();
    }
}

function executeCheckAndGetResult($db, $schedule) {
    $checkType = $schedule['check_type'];
    $startTime = microtime(true);
    
    error_log("Health Cron: Starting check {$checkType}");
    
    updateScheduleRun($db, $schedule['id'], 'running', null);
    
    try {
        $result = callLocalApiCheck($checkType);
        $duration = round((microtime(true) - $startTime) * 1000);
        $success = $result['success'] ?? false;
        $message = $result['message'] ?? 'Check completed';
        $hasProblems = $result['has_problems'] ?? false;
        
        $status = $success ? 'success' : 'failed';
        updateScheduleRun($db, $schedule['id'], $status, $success ? null : $message);
        logHistory($db, $checkType, $status, $message, $duration);
        
        error_log("Health Cron: Completed check {$checkType} in {$duration}ms - Status: {$status}");
        
        return [
            'type' => $checkType,
            'success' => $success,
            'has_problems' => $hasProblems,
            'message' => $message,
            'duration' => $duration,
            'data' => $result['data'] ?? null,
            'error' => null
        ];
        
    } catch (Exception $e) {
        $duration = round((microtime(true) - $startTime) * 1000);
        $errorMsg = $e->getMessage();
        
        updateScheduleRun($db, $schedule['id'], 'failed', $errorMsg);
        logHistory($db, $checkType, 'failed', $errorMsg, $duration);
        
        error_log("Health Cron: Error in check {$checkType}: " . $errorMsg);
        
        return [
            'type' => $checkType,
            'success' => false,
            'has_problems' => true,
            'message' => $errorMsg,
            'duration' => $duration,
            'data' => null,
            'error' => $errorMsg
        ];
    }
}

function callLocalApiCheck($checkType) {
    $apiFile = '/var/www/html/admin/api/health_monitor_api.php';
    
    if (!file_exists($apiFile)) {
        return ['success' => false, 'has_problems' => false, 'message' => "API file not found: {$apiFile}"];
    }
    
    ob_start();
    
    if (!defined('ROOT_PATH')) {
        define('ROOT_PATH', '/var/www');
    }
    
    global $db;
    require_once $apiFile;
    ob_end_clean();
    
    try {
        switch ($checkType) {
            case 'disks':
                $data = checkDisks();
                $criticalCount = $data['stats']['critical'] ?? 0;
                $warningCount = $data['stats']['warning'] ?? 0;
                return [
                    'success' => true,
                    'has_problems' => ($criticalCount + $warningCount) > 0,
                    'message' => "Disks: {$data['stats']['total']} found, {$criticalCount} critical, {$warningCount} warnings",
                    'data' => $data
                ];
                
            case 'raid':
                $data = checkRaid();
                $hasProblems = false;
                $problemDetails = [];
                foreach ($data['raid'] as $raid) {
                    if (isset($raid['degraded']) && $raid['degraded']) {
                        $hasProblems = true;
                        $problemDetails[] = $raid['name'] . ': ' . ($raid['status'] ?? 'degraded');
                    }
                }
                return [
                    'success' => true,
                    'has_problems' => $hasProblems,
                    'message' => "RAID: " . count($data['raid']) . " arrays, " . ($hasProblems ? "degraded: " . implode(', ', $problemDetails) : "all healthy"),
                    'data' => $data
                ];
                
            case 'lvm':
                $data = checkLvm();
                $hasProblems = false;
                $problemDetails = [];
                foreach ($data['lvs'] as $lv) {
                    if (!$lv['active']) {
                        $hasProblems = true;
                        $problemDetails[] = $lv['name'] . ' (inactive)';
                    }
                }
                return [
                    'success' => true,
                    'has_problems' => $hasProblems,
                    'message' => "LVM: " . count($data['vgs']) . " VGs, " . count($data['lvs']) . " LVs" . ($hasProblems ? " - issues: " . implode(', ', $problemDetails) : ""),
                    'data' => $data
                ];
                
            case 'temperature':
                $data = checkTemperatures();
                $settings = getSettings();
                $cpuTemp = 0;
                $cpuThreshold = intval($settings['cpu_temp_threshold'] ?? 85);
                $diskThreshold = intval($settings['disk_temp_threshold'] ?? 55);
                $hasProblems = false;
                $problemDetails = [];
                
                if ($data['cpu_temp']) {
                    $cpuTemp = floatval(str_replace('°C', '', $data['cpu_temp']));
                    if ($cpuTemp > $cpuThreshold) {
                        $hasProblems = true;
                        $problemDetails[] = "CPU: {$data['cpu_temp']} (threshold: {$cpuThreshold}°C)";
                    }
                }
                foreach ($data['disk_temps'] as $disk) {
                    $tempVal = floatval(str_replace('°C', '', $disk['temp']));
                    if ($tempVal > $diskThreshold) {
                        $hasProblems = true;
                        $problemDetails[] = "{$disk['name']}: {$disk['temp']} (threshold: {$diskThreshold}°C)";
                    }
                }
                return [
                    'success' => true,
                    'has_problems' => $hasProblems,
                    'message' => "Temperature: CPU {$data['cpu_temp']}, " . count($data['disk_temps']) . " disks" . ($hasProblems ? " - issues: " . implode(', ', $problemDetails) : ""),
                    'data' => $data
                ];
                
            case 'shares':
                $data = checkShares();
                $hasProblems = false;
                $problemDetails = [];
                foreach ($data['shares'] as $share) {
                    if (!$share['is_available']) {
                        $hasProblems = true;
                        $problemDetails[] = $share['name'] . " ({$share['type']}) - {$share['error']}";
                    }
                }
                return [
                    'success' => true,
                    'has_problems' => $hasProblems,
                    'message' => "Shares: " . count($data['shares']) . " total, " . ($hasProblems ? "unavailable: " . implode(', ', $problemDetails) : "all available"),
                    'data' => $data
                ];
                
            case 'all':
                $disks = checkDisks();
                $raid = checkRaid();
                $lvm = checkLvm();
                $temp = checkTemperatures();
                $shares = checkShares();
                
                $hasProblems = (
                    ($disks['stats']['critical'] ?? 0) > 0 ||
                    ($raid['raid'] && array_filter($raid['raid'], function($r) { return $r['degraded']; })) ||
                    ($temp['cpu_temp'] && floatval(str_replace('°C', '', $temp['cpu_temp'])) > 85) ||
                    ($shares['shares'] && array_filter($shares['shares'], function($s) { return !$s['is_available']; }))
                );
                
                return [
                    'success' => true,
                    'has_problems' => $hasProblems,
                    'message' => "Complete system check finished" . ($hasProblems ? " with issues" : " - all healthy"),
                    'data' => compact('disks', 'raid', 'lvm', 'temp', 'shares')
                ];
                
            default:
                return ['success' => false, 'has_problems' => false, 'message' => "Unknown check type: {$checkType}"];
        }
    } catch (Exception $e) {
        return ['success' => false, 'has_problems' => false, 'message' => "Error: " . $e->getMessage()];
    }
}

function sendConsolidatedReport($db, $allResults, $executedCount) {
    if (empty($allResults)) {
        return;
    }
    
    $settings = getSettingsFromDb($db);
    
    if (empty($settings['email_enabled']) || $settings['email_enabled'] != 1 || empty($settings['email_recipient'])) {
        error_log("Health Cron: Email disabled, skipping report");
        return;
    }
    
    $cooldownMinutes = intval($settings['notification_cooldown_minutes'] ?? 60);
    $lastReportFile = $tmpDir . '/last_report_time.lock';
    if ($cooldownMinutes > 0 && file_exists($lastReportFile)) {
        $lastTime = (int)file_get_contents($lastReportFile);
        if (time() - $lastTime < $cooldownMinutes * 60) {
            error_log("Health Cron: Report cooldown active ($cooldownMinutes min)");
            return;
        }
    }
    
    $hasProblems = false;
    foreach ($allResults as $res) {
        if ($res['has_problems'] || !$res['success']) {
            $hasProblems = true;
            break;
        }
    }
    
    $notifyOnlyOnError = $settings['notify_only_on_error'] ?? 0;
    if ($notifyOnlyOnError == 1 && !$hasProblems) {
        error_log("Health Cron: No problems found, skipping email (notify_only_on_error enabled)");
        return;
    }
    
    $subject = $hasProblems 
        ? "[Mini-B] ⚠️ Health Alert - Problems Detected"
        : "[Mini-B] ✅ Health Report - All Systems Operational";
    
    $htmlMessage = buildConsolidatedReportHtml($allResults, $hasProblems);
    
    try {
        $stmt = $db->prepare("
            INSERT INTO notifications (notification_type, severity, title, message, details) 
            VALUES (:type, :severity, :title, :message, :details)
        ");
        $stmt->bindValue(':type', 'consolidated', SQLITE3_TEXT);
        $stmt->bindValue(':severity', $hasProblems ? 'warning' : 'info', SQLITE3_TEXT);
        $stmt->bindValue(':title', $subject, SQLITE3_TEXT);
        $stmt->bindValue(':message', strip_tags($htmlMessage), SQLITE3_TEXT);
        $stmt->bindValue(':details', json_encode($allResults), SQLITE3_TEXT);
        $stmt->execute();
    } catch (Exception $e) {
        error_log("Health Cron: Failed to save notification: " . $e->getMessage());
    }
    
    sendEmailWithUtf8Subject($subject, $htmlMessage, $settings);
    
    file_put_contents($lastReportFile, time());
}

function buildConsolidatedReportHtml($results, $hasProblems) {
    $colors = [
        'bg' => $hasProblems ? '#FFF5F5' : '#F5F5F7',
        'border' => $hasProblems ? '#FF3B30' : '#34C759',
        'header' => $hasProblems ? '#FF3B30' : '#34C759',
        'text' => '#1C1C1E',
        'secondary' => '#8E8E93'
    ];
    
    $html = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Health Monitor Report</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: -apple-system, BlinkMacSystemFont, "SF Pro Text", "Helvetica Neue", Helvetica, Arial, sans-serif; background-color: #F5F5F7; padding: 20px; }
            .container { max-width: 560px; margin: 0 auto; }
            .card { background: #FFFFFF; border-radius: 14px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.04), 0 8px 24px rgba(0,0,0,0.08); }
            .card-header { padding: 24px 20px; text-align: center; border-bottom: 1px solid #E5E5EA; background: ' . $colors['bg'] . '; }
            .card-header h1 { font-size: 24px; font-weight: 600; color: ' . $colors['header'] . '; margin-bottom: 8px; }
            .card-header .badge { display: inline-block; padding: 4px 12px; background: ' . $colors['bg'] . '; border-radius: 20px; font-size: 12px; font-weight: 500; color: ' . $colors['border'] . '; letter-spacing: 0.5px; border: 1px solid ' . $colors['border'] . '; }
            .card-body { padding: 20px; }
            .info-row { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #E5E5EA; }
            .info-label { font-size: 14px; color: #8E8E93; font-weight: 500; }
            .info-value { font-size: 14px; color: #1C1C1E; font-weight: 500; }
            .check-item { margin-bottom: 20px; border: 1px solid #E5E5EA; border-radius: 12px; overflow: hidden; }
            .check-header { padding: 14px 16px; background: #F5F5F7; display: flex; justify-content: space-between; align-items: center; cursor: pointer; }
            .check-title { font-weight: 600; font-size: 16px; display: flex; align-items: center; gap: 8px; }
            .check-status { padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 500; }
            .status-ok { background: rgba(52,199,89,0.15); color: #34C759; }
            .status-problem { background: rgba(255,59,48,0.15); color: #FF3B30; }
            .status-warning { background: rgba(255,149,0,0.15); color: #FF9500; }
            .check-message { padding: 12px 16px; font-size: 13px; color: #6c6c70; background: white; border-top: 1px solid #E5E5EA; }
            .section { margin-top: 16px; }
            .section-title { font-size: 13px; font-weight: 600; color: #8E8E93; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px; }
            .item-list { background: #F5F5F7; border-radius: 10px; overflow: hidden; }
            .item { padding: 10px 14px; border-bottom: 1px solid #E5E5EA; display: flex; justify-content: space-between; align-items: center; }
            .item:last-child { border-bottom: none; }
            .item-name { font-size: 14px; font-weight: 500; }
            .item-badge { padding: 2px 8px; border-radius: 16px; font-size: 11px; font-weight: 500; }
            .badge-good { background: rgba(52,199,89,0.15); color: #34C759; }
            .badge-bad { background: rgba(255,59,48,0.15); color: #FF3B30; }
            .badge-warning { background: rgba(255,149,0,0.15); color: #FF9500; }
            .footer { background: #F5F5F7; padding: 16px 20px; text-align: center; border-top: 1px solid #E5E5EA; }
            .footer-text { font-size: 11px; color: #8E8E93; }
            .separator { height: 1px; background: #E5E5EA; margin: 16px 0; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="card">
                <div class="card-header">
                    <h1>' . ($hasProblems ? '⚠️ Health Check Alert' : '✅ Health Check Report') . '</h1>
                    <div class="badge">' . date('Y-m-d H:i:s') . '</div>
                </div>
                <div class="card-body">';
    
    foreach ($results as $res) {
        $checkType = $res['type'];
        $hasProblem = $res['has_problems'] || !$res['success'];
        $statusClass = $hasProblem ? 'status-problem' : 'status-ok';
        $statusText = $hasProblem ? 'Issues Found' : 'OK';
        
        $html .= '<div class="check-item">
            <div class="check-header">
                <div class="check-title">
                    <i class="fas ' . getIconForType($checkType) . '"></i>
                    ' . strtoupper($checkType) . '
                </div>
                <span class="check-status ' . $statusClass . '">' . $statusText . '</span>
            </div>';
        
        if ($res['data'] && ($hasProblem || true)) {
            $html .= '<div class="check-message">' . htmlspecialchars($res['message']) . '</div>';
            $html .= buildDetailsForType($checkType, $res['data']);
        } else {
            $html .= '<div class="check-message">' . htmlspecialchars($res['message']) . '</div>';
        }
        
        $html .= '</div>';
    }
    
    $html .= '<div class="separator"></div>
              <div class="info-row">
                  <span class="info-label">Total Checks</span>
                  <span class="info-value">' . count($results) . '</span>
              </div>
              <div class="info-row">
                  <span class="info-label">Status</span>
                  <span class="info-value" style="color: ' . $colors['border'] . ';">' . ($hasProblems ? 'Issues Detected' : 'All Healthy') . '</span>
              </div>
            </div>
            <div class="footer">
                <p class="footer-text">Mini-B Health Monitor · ' . date('Y-m-d H:i:s') . '</p>
            </div>
        </div>
    </div>
</body>
</html>';
    
    return $html;
}

function getIconForType($type) {
    $icons = [
        'disks' => '💾',
        'raid' => '🛡️',
        'lvm' => '📦',
        'temperature' => '🌡️',
        'shares' => '📁',
        'all' => '🔍'
    ];
    return $icons[$type] ?? '📊';
}

function buildDetailsForType($type, $data) {
    $html = '<div class="section"><div class="section-title">Details</div><div class="item-list">';
    
    switch ($type) {
        case 'disks':
            if (isset($data['disks'])) {
                foreach ($data['disks'] as $disk) {
                    $hasIssue = ($disk['smart_bad_sectors'] ?? 0) > 0 || $disk['smart_status'] === 'FAILED';
                    $badgeClass = $hasIssue ? 'badge-bad' : 'badge-good';
                    $badgeText = $hasIssue ? ($disk['smart_bad_sectors'] > 0 ? "Bad: {$disk['smart_bad_sectors']}" : 'Failed') : 'OK';
                    $html .= '<div class="item">
                        <span class="item-name">' . htmlspecialchars($disk['name']) . ' <span class="item-detail">' . ($disk['smart_temp'] ?? '') . '</span></span>
                        <span class="item-badge ' . $badgeClass . '">' . $badgeText . '</span>
                    </div>';
                }
            }
            break;
            
        case 'raid':
            if (isset($data['raid'])) {
                foreach ($data['raid'] as $raid) {
                    $isDegraded = !empty($raid['degraded']);
                    $badgeClass = $isDegraded ? 'badge-bad' : 'badge-good';
                    $badgeText = $isDegraded ? ($raid['status'] ?? 'Degraded') : 'Healthy';
                    $info = '';
                    if (!empty($raid['working_disks']) && !empty($raid['total_disks'])) {
                        $info = " {$raid['working_disks']}/{$raid['total_disks']} disks";
                    }
                    $html .= '<div class="item">
                        <span class="item-name">' . htmlspecialchars($raid['name']) . ' <span class="item-detail">' . ($raid['level'] ?? '') . $info . '</span></span>
                        <span class="item-badge ' . $badgeClass . '">' . $badgeText . '</span>
                    </div>';
                }
            }
            break;
            
        case 'lvm':
            if (isset($data['lvs'])) {
                foreach ($data['lvs'] as $lv) {
                    $isInactive = empty($lv['active']);
                    $badgeClass = $isInactive ? 'badge-warning' : 'badge-good';
                    $badgeText = $isInactive ? 'Inactive' : 'Active';
                    $html .= '<div class="item">
                        <span class="item-name">' . htmlspecialchars($lv['name']) . ' <span class="item-detail">(' . htmlspecialchars($lv['vg_name']) . ')</span></span>
                        <span class="item-badge ' . $badgeClass . '">' . $badgeText . '</span>
                    </div>';
                }
            }
            break;
            
        case 'temperature':
            if (!empty($data['cpu_temp'])) {
                $tempVal = floatval(str_replace('°C', '', $data['cpu_temp']));
                $isHigh = $tempVal > ($data['thresholds']['cpu'] ?? 85);
                $badgeClass = $isHigh ? 'badge-warning' : 'badge-good';
                $html .= '<div class="item">
                    <span class="item-name">CPU</span>
                    <span class="item-badge ' . $badgeClass . '">' . htmlspecialchars($data['cpu_temp']) . '</span>
                </div>';
            }
            if (!empty($data['disk_temps'])) {
                foreach ($data['disk_temps'] as $disk) {
                    $tempVal = floatval(str_replace('°C', '', $disk['temp']));
                    $isHigh = $tempVal > ($data['thresholds']['disk'] ?? 55);
                    $badgeClass = $isHigh ? 'badge-warning' : 'badge-good';
                    $html .= '<div class="item">
                        <span class="item-name">' . htmlspecialchars($disk['name']) . '</span>
                        <span class="item-badge ' . $badgeClass . '">' . htmlspecialchars($disk['temp']) . '</span>
                    </div>';
                }
            }
            break;
            
        case 'shares':
            if (isset($data['shares'])) {
                foreach ($data['shares'] as $share) {
                    $isAvailable = !empty($share['is_available']);
                    $badgeClass = $isAvailable ? 'badge-good' : 'badge-bad';
                    $badgeText = $isAvailable ? 'Available' : 'Offline';
                    $typeIcon = $share['type'] === 'smb' ? '📁' : ($share['type'] === 'nfs' ? '🌐' : '📡');
                    $html .= '<div class="item">
                        <span class="item-name">' . $typeIcon . ' ' . htmlspecialchars($share['name']) . '</span>
                        <span class="item-badge ' . $badgeClass . '">' . $badgeText . '</span>
                    </div>';
                }
            }
            break;
    }
    
    $html .= '</div></div>';
    return $html;
}

function sendEmailWithUtf8Subject($subject, $htmlMessage, $settings) {
    $phpmailerPath = '/var/www/html/admin/lib/PHPMailer/PHPMailer/src/PHPMailer.php';
    
    $encodedSubject = mb_encode_mimeheader($subject, 'UTF-8', 'B');
    
    if (!file_exists($phpmailerPath)) {
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: Mini-B Health Monitor <noreply@mini-b>\r\n";
        return mail($settings['email_recipient'], $encodedSubject, $htmlMessage, $headers);
    }
    
    try {
        require_once $phpmailerPath;
        require_once '/var/www/html/admin/lib/PHPMailer/PHPMailer/src/SMTP.php';
        require_once '/var/www/html/admin/lib/PHPMailer/PHPMailer/src/Exception.php';
        
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = $settings['smtp_host'] ?? '';
        $mail->SMTPAuth   = !empty($settings['smtp_username']);
        $mail->Username   = $settings['smtp_username'] ?? '';
        $mail->Password   = $settings['smtp_password'] ?? '';
        $mail->SMTPSecure = $settings['smtp_encryption'] ?? '';
        $mail->Port       = intval($settings['smtp_port'] ?? 25);
        $mail->SMTPDebug = 0;
        $mail->CharSet = 'UTF-8';
        
        $fromEmail = $settings['smtp_from_email'] ?? '';
        if (empty($fromEmail) && !empty($settings['smtp_username']) && !empty($settings['smtp_domain'])) {
            $fromEmail = $settings['smtp_username'] . '@' . $settings['smtp_domain'];
        }
        if (empty($fromEmail)) {
            $fromEmail = 'noreply@mini-b.local';
        }
        
        $fromName = $settings['smtp_from_name'] ?? 'Mini-B Health Monitor';
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($settings['email_recipient']);
        
        $mail->isHTML(true);
        $mail->Subject = $encodedSubject;
        $mail->Body    = $htmlMessage;
        $mail->AltBody = strip_tags($htmlMessage);
        
        $mail->send();
        error_log("Health Cron: Email sent successfully to {$settings['email_recipient']}");
        return true;
        
    } catch (Exception $e) {
        error_log("Health Cron: Email failed: " . ($mail->ErrorInfo ?? $e->getMessage()));
        return false;
    }
}

function updateScheduleRun($db, $scheduleId, $status, $error = null) {
    $now = date('Y-m-d H:i:s');
    
    $stmt = $db->prepare("SELECT interval_seconds FROM check_schedules WHERE id = :id");
    $stmt->bindValue(':id', $scheduleId, SQLITE3_INTEGER);
    $res = $stmt->execute();
    $row = $res->fetchArray(SQLITE3_ASSOC);
    $interval = $row ? $row['interval_seconds'] : 300;
    
    $nextRun = date('Y-m-d H:i:s', strtotime("+{$interval} seconds"));
    
    $stmt = $db->prepare("
        UPDATE check_schedules 
        SET last_run = :now, 
            next_run = :next,
            last_status = :status, 
            last_error = :error,
            updated_at = :now
        WHERE id = :id
    ");
    $stmt->bindValue(':now', $now, SQLITE3_TEXT);
    $stmt->bindValue(':next', $nextRun, SQLITE3_TEXT);
    $stmt->bindValue(':status', $status, SQLITE3_TEXT);
    $stmt->bindValue(':error', $error, SQLITE3_TEXT);
    $stmt->bindValue(':id', $scheduleId, SQLITE3_INTEGER);
    $stmt->execute();
}

function logHistory($db, $checkType, $status, $message, $duration) {
    $stmt = $db->prepare("
        INSERT INTO check_history (check_type, status, message, duration_ms) 
        VALUES (:type, :status, :message, :duration)
    ");
    $stmt->bindValue(':type', $checkType, SQLITE3_TEXT);
    $stmt->bindValue(':status', $status, SQLITE3_TEXT);
    $stmt->bindValue(':message', $message, SQLITE3_TEXT);
    $stmt->bindValue(':duration', $duration, SQLITE3_INTEGER);
    $stmt->execute();
}

function getSettingsFromDb($db) {
    $settings = [];
    $result = $db->query("SELECT setting_key, setting_value FROM notification_settings");
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    return $settings;
}