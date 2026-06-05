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

$operationId = $argv[1] ?? '';
$progressFile = sys_get_temp_dir() . '/fm_progress_' . $operationId . '.json';
$logFile = sys_get_temp_dir() . '/fm_log_' . $operationId . '.txt';

function logMsg($msg) {
    global $logFile;
    file_put_contents($logFile, date('Y-m-d H:i:s') . ' - ' . $msg . PHP_EOL, FILE_APPEND);
}

function copyRecursive($src, $dst, &$count) {
    if (!file_exists($src)) return false;
    
    if (!is_dir($src)) {
        $dstDir = dirname($dst);
        if (!is_dir($dstDir)) {
            mkdir($dstDir, 0755, true);
        }
        if (copy($src, $dst)) {
            $count++;
            @chmod($dst, fileperms($src));
            return true;
        }
        return false;
    }
    
    if (!is_dir($dst)) {
        mkdir($dst, 0755, true);
    }
    $count++;
    
    $files = scandir($src);
    $success = true;
    foreach ($files as $file) {
        if ($file == '.' || $file == '..') continue;
        if (!copyRecursive($src . '/' . $file, $dst . '/' . $file, $count)) {
            $success = false;
        }
    }
    return $success;
}

function moveRecursive($src, $dst, &$count) {
    if (!file_exists($src)) return false;
    
    if (!is_dir($src)) {
        $dstDir = dirname($dst);
        if (!is_dir($dstDir)) {
            mkdir($dstDir, 0755, true);
        }
        if (rename($src, $dst)) {
            $count++;
            return true;
        }
        if (copy($src, $dst)) {
            unlink($src);
            $count++;
            return true;
        }
        return false;
    }
    
    if (!is_dir($dst)) {
        mkdir($dst, 0755, true);
    }
    $count++;
    
    $files = scandir($src);
    $success = true;
    foreach ($files as $file) {
        if ($file == '.' || $file == '..') continue;
        if (!moveRecursive($src . '/' . $file, $dst . '/' . $file, $count)) {
            $success = false;
        }
    }
    
    if ($success) {
        rmdir($src);
    }
    return $success;
}

if (!$operationId || !file_exists($progressFile)) {
    logMsg("Invalid operation ID or progress file not found");
    exit(1);
}

$progress = json_decode(file_get_contents($progressFile), true);
if (!$progress) {
    logMsg("Invalid progress data");
    exit(1);
}

logMsg("Console operation started: {$progress['operation']}");
logMsg("Source: {$progress['source_dir']}");
logMsg("Target: {$progress['target_dir']}");
logMsg("Files: " . implode(', ', $progress['files']));

$processed = 0;
$total = $progress['total'];

foreach ($progress['files'] as $file) {
    if (file_exists($progressFile)) {
        $currentProgress = json_decode(file_get_contents($progressFile), true);
        if ($currentProgress && isset($currentProgress['cancel']) && $currentProgress['cancel']) {
            logMsg("Operation cancelled by user");
            break;
        }
    }
    
    $src = $progress['source_dir'] . '/' . $file;
    $dst = $progress['target_dir'] . '/' . $file;
    
    logMsg("Processing: " . basename($file));
    
    try {
        if ($progress['operation'] === 'copy') {
            $copiedCount = 0;
            if (copyRecursive($src, $dst, $copiedCount)) {
                $processed += $copiedCount;
                logMsg("Copied: " . basename($file) . " ($copiedCount items)");
            } else {
                logMsg("FAILED to copy: " . basename($file));
            }
        } else {
            $movedCount = 0;
            if (moveRecursive($src, $dst, $movedCount)) {
                $processed += $movedCount;
                logMsg("Moved: " . basename($file) . " ($movedCount items)");
            } else {
                logMsg("FAILED to move: " . basename($file));
            }
        }
    } catch (Exception $e) {
        logMsg("Exception: " . $e->getMessage());
    }
    
    if (file_exists($progressFile)) {
        $currentProgress = json_decode(file_get_contents($progressFile), true);
        if ($currentProgress) {
            $currentProgress['current'] = $processed;
            file_put_contents($progressFile, json_encode($currentProgress));
        }
    }
}

if (file_exists($progressFile)) {
    $currentProgress = json_decode(file_get_contents($progressFile), true);
    if ($currentProgress) {
        $currentProgress['active'] = false;
        $currentProgress['current'] = $processed;
        file_put_contents($progressFile, json_encode($currentProgress));
    }
}

logMsg("Operation completed. Processed: $processed / $total items");