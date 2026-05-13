#!/usr/bin/php
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
 
function getSafeFileSize($path) {
    if (!is_file($path)) return 0;
    
    $size = @filesize($path);
    
    if ($size === false || $size < 0) {
        if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
            $size = trim(@shell_exec('stat -c %s ' . escapeshellarg($path)));
            if (is_numeric($size)) {
                return (float)$size;
            }
        }
        
        $fp = @fopen($path, 'rb');
        if ($fp) {
            fseek($fp, 0, SEEK_END);
            $size = ftell($fp);
            fclose($fp);
            return (float)$size;
        }
        
        return 0;
    }
    
    return $size;
}


$baseDir = dirname(__FILE__);
$tmpDir = $baseDir . '/tmp';

if (!is_dir($tmpDir)) {
    mkdir($tmpDir, 0777, true);
}
if (!is_writable($tmpDir)) {
    chmod($tmpDir, 0777);
}

if ($argc < 2) {
    $errorLog = $tmpDir . '/fm_worker_error.log';
    file_put_contents($errorLog, date('Y-m-d H:i:s') . " - No operation ID provided\n", FILE_APPEND);
    exit(1);
}

$operationId = $argv[1];
$progressFile = $tmpDir . '/fm_progress_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $operationId) . '.json';
$logFile = $tmpDir . '/fm_log_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $operationId) . '.txt';
$errorLog = $tmpDir . '/fm_worker_error.log';
$lockFile = $tmpDir . '/fm_lock_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $operationId) . '.lock';

$fp = fopen($lockFile, 'w');
if (!flock($fp, LOCK_EX | LOCK_NB)) {
    file_put_contents($errorLog, date('Y-m-d H:i:s') . " - Worker already running for $operationId\n", FILE_APPEND);
    exit(0);
}

function logMessage($msg) {
    global $logFile;
    @file_put_contents($logFile, date('Y-m-d H:i:s') . ' - ' . $msg . PHP_EOL, FILE_APPEND);
}

function logError($msg) {
    global $errorLog;
    @file_put_contents($errorLog, date('Y-m-d H:i:s') . ' - ' . $msg . PHP_EOL, FILE_APPEND);
}

function updateProgress($current, $total, $currentFile = '') {
    global $progressFile;
    
    if (!file_exists($progressFile)) {
        logError("Progress file does not exist: $progressFile");
        return false;
    }
    
    $content = file_get_contents($progressFile);
    if ($content === false) {
        logError("Failed to read progress file");
        return false;
    }
    
    $data = json_decode($content, true);
    if (!$data) {
        logError("Failed to decode JSON: " . json_last_error_msg());
        return false;
    }
    
    $data['current'] = (int)$current;
    $data['last_update'] = time();
    if ($currentFile) {
        $data['current_file'] = $currentFile;
    }
    $data['total'] = (int)$total;
    $overallPercent = ($total > 0) ? min(100, floor(($current / $total) * 100)) : 0;
    $data['current_percent'] = $overallPercent;
    $data['overall_percent'] = $overallPercent;
    
    $newContent = json_encode($data, JSON_UNESCAPED_UNICODE);
    
    $fp = fopen($progressFile, 'w');
    if (flock($fp, LOCK_EX)) {
        ftruncate($fp, 0);
        fwrite($fp, $newContent);
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        chmod($progressFile, 0666);
        logMessage("Progress updated: $current / $total ({$overallPercent}%) - " . substr($currentFile, 0, 50));
        return true;
    }
    
    fclose($fp);
    logError("Failed to lock progress file");
    return false;
}

function isCancelled() {
    global $progressFile;
    if (file_exists($progressFile)) {
        $data = json_decode(@file_get_contents($progressFile), true);
        return $data && isset($data['cancel']) && $data['cancel'] === true;
    }
    return false;
}

function copyFileWithProgress($src, $dst, &$processedCount, $total, $fileName, $fileSize) {
    if (isCancelled()) return false;
    
    logMessage("Copying file: $fileName (" . round($fileSize / 1024 / 1024, 2) . " MB)");
    
    $dstDir = dirname($dst);
    if (!is_dir($dstDir)) {
        if (!@mkdir($dstDir, 0777, true)) {
            logError("Failed to create destination directory: $dstDir");
            return false;
        }
    }
    
    $srcHandle = @fopen($src, 'rb');
    if (!$srcHandle) {
        logError("Failed to open source file: $src");
        return false;
    }
    
    $dstHandle = @fopen($dst, 'wb');
    if (!$dstHandle) {
        logError("Failed to open destination file: $dst");
        @fclose($srcHandle);
        return false;
    }
    
    $bufferSize = 8 * 1024 * 1024;
    stream_set_read_buffer($srcHandle, $bufferSize);
    stream_set_write_buffer($dstHandle, $bufferSize);
    
    $copiedSize = 0;
    $lastProgressUpdate = 0;
    $lastPercent = -1;
    
    while (!feof($srcHandle)) {
        if (isCancelled()) {
            logMessage("Copy cancelled: $fileName");
            @fclose($srcHandle);
            @fclose($dstHandle);
            @unlink($dst);
            return false;
        }
        
        $buffer = @fread($srcHandle, $bufferSize);
        if ($buffer === false) {
            logError("Failed to read from source: $src");
            @fclose($srcHandle);
            @fclose($dstHandle);
            @unlink($dst);
            return false;
        }
        
        if (strlen($buffer) === 0 && !feof($srcHandle)) {
            logError("Zero-byte read but not EOF: $src");
            continue;
        }
        
        $bytesToWrite = strlen($buffer);
        $written = 0;
        
        while ($written < $bytesToWrite) {
            $chunk = substr($buffer, $written);
            $result = @fwrite($dstHandle, $chunk);
            
            if ($result === false) {
                logError("Failed to write to destination: $dst");
                @fclose($srcHandle);
                @fclose($dstHandle);
                @unlink($dst);
                return false;
            }
            
            $written += $result;
        }
        
        $copiedSize += $bytesToWrite;
        
        $now = time();
        $filePercent = ($fileSize > 0) ? min(100, floor(($copiedSize / $fileSize) * 100)) : 0;
        
        if ($now - $lastProgressUpdate >= 1 || $filePercent != $lastPercent) {
            updateProgressForFile($processedCount, $total, $fileName, $filePercent);
            $lastProgressUpdate = $now;
            $lastPercent = $filePercent;
        }
    }
    
    @fclose($srcHandle);
    @fclose($dstHandle);
    
    clearstatcache(true, $dst);
    $finalSize = getSafeFileSize($dst);
    
    if ($finalSize != $fileSize && $fileSize > 0) {
        logError("Size mismatch after copy: expected $fileSize, got $finalSize for $fileName");
        @unlink($dst);
        return false;
    }
    
    $perms = @fileperms($src);
    if ($perms) {
        @chmod($dst, $perms);
    }
    
    $processedCount++;
    updateProgressForFile($processedCount, $total, $fileName, 100);
    
    logMessage("Completed copying: $fileName ($finalSize bytes)");
    return true;
}

function updateProgressForFile($current, $total, $currentFile, $filePercent) {
    global $progressFile;
    
    if (!file_exists($progressFile)) {
        logError("Progress file does not exist: $progressFile");
        return false;
    }
    
    $content = file_get_contents($progressFile);
    if ($content === false) {
        logError("Failed to read progress file");
        return false;
    }
    
    $data = json_decode($content, true);
    if (!$data) {
        logError("Failed to decode JSON: " . json_last_error_msg());
        return false;
    }
    
    $data['current'] = (int)$current;
    $data['last_update'] = time();
    $data['current_file'] = $currentFile;
    $data['total'] = (int)$total;
    $data['current_percent'] = $filePercent;
    $data['overall_percent'] = $filePercent;
    
    $newContent = json_encode($data, JSON_UNESCAPED_UNICODE);
    
    $fp = fopen($progressFile, 'w');
    if (flock($fp, LOCK_EX)) {
        ftruncate($fp, 0);
        fwrite($fp, $newContent);
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        chmod($progressFile, 0666);
        logMessage("Progress: $current / $total - File: $currentFile - {$filePercent}%");
        return true;
    }
    
    fclose($fp);
    logError("Failed to lock progress file");
    return false;
}

function copyDirectory($src, $dst, &$processedCount, $total, $relativePath = '') {
    if (isCancelled()) return false;
    
    if (!is_dir($dst)) {
        if (!@mkdir($dst, 0777, true)) {
            logError("Failed to create directory: $dst");
            return false;
        }
        $processedCount++;
        updateProgress($processedCount, $total, $relativePath . basename($src) . '/');
    }
    
    $items = @scandir($src);
    if ($items === false) {
        logError("Failed to read directory: $src");
        return false;
    }
    
    foreach ($items as $item) {
        if ($item == '.' || $item == '..') continue;
        if (isCancelled()) return false;
        
        $srcItem = $src . '/' . $item;
        $dstItem = $dst . '/' . $item;
        
        if (is_dir($srcItem)) {
            $newRelPath = $relativePath . basename($src) . '/';
            if (!copyDirectory($srcItem, $dstItem, $processedCount, $total, $newRelPath)) {
                return false;
            }
        } else {
            $fileName = $relativePath . $item;
            $fileSize = getSafeFileSize($srcItem);
            if ($fileSize === false) $fileSize = 0;
            if (!copyFileWithProgress($srcItem, $dstItem, $processedCount, $total, $fileName, $fileSize)) {
                return false;
            }
        }
    }
    return true;
}

function copyItem($src, $dst, &$processedCount, $total, $itemName) {
    if (is_dir($src)) {
        logMessage("Copying directory: $itemName");
        return copyDirectory($src, $dst, $processedCount, $total, '');
    } else {
        $fileSize = getSafeFileSize($src);
        if ($fileSize === false) $fileSize = 0;
        return copyFileWithProgress($src, $dst, $processedCount, $total, $itemName, $fileSize);
    }
}

function moveItem($src, $dst, &$processedCount, $total, $itemName) {
    if (isCancelled()) return false;
    
    logMessage("Starting move: $itemName");
    
    $canRename = false;
    
    $srcStat = @stat(dirname($src));
    $dstStat = @stat(dirname($dst));
    
    if ($srcStat && $dstStat && $srcStat['dev'] == $dstStat['dev']) {
        $canRename = true;
        logMessage("Same device detected, using fast rename for: $itemName");
    } else {
        logMessage("Different devices detected, will use copy+delete for: $itemName");
    }
    
    if ($canRename) {
        updateProgressForFile($processedCount, $total, $itemName . " (renaming...)", 0);
        
        usleep(100000);
        
        if (@rename($src, $dst)) {
            logMessage("Moved (rename): $itemName");
            $processedCount++;
            updateProgressForFile($processedCount, $total, $itemName, 100);
            return true;
        } else {
            logMessage("Rename failed despite same device: $itemName");
            $canRename = false;
        }
    }
    
    if (!$canRename) {
        logMessage("Starting copy phase for move: $itemName");
        
        if (!copyItem($src, $dst, $processedCount, $total, $itemName)) {
            logError("Copy failed during move: $itemName");
            return false;
        }
        
        logMessage("Copy completed, deleting original: $itemName");
        
        updateProgressForFile($processedCount, $total, $itemName . " (deleting original...)", 100);
        usleep(100000);
        
        $deleteSuccess = false;
        if (is_dir($src)) {
            deleteDirectory($src);
            clearstatcache(true, $src);
            $deleteSuccess = !file_exists($src);
        } else {
            $deleteSuccess = @unlink($src);
            
            if (!$deleteSuccess) {
                clearstatcache(true, $src);
                sleep(1);
                $deleteSuccess = @unlink($src);
            }
        }
        
        if (!$deleteSuccess) {
            logError("WARNING: Failed to delete original: $src");
            updateProgressForFile($processedCount, $total, $itemName . " (ОШИБКА: оригинал не удалён)", 100);
            return true;
        }
        
        logMessage("Successfully deleted original: $itemName");
        return true;
    }
    
    return false;
}

function deleteDirectory($dir) {
    if (!is_dir($dir)) return;
    $items = @scandir($dir);
    if ($items === false) return;
    
    foreach ($items as $item) {
        if ($item == '.' || $item == '..') continue;
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            deleteDirectory($path);
        } else {
            @unlink($path);
        }
    }
    @rmdir($dir);
}

function getTotalItemsRecursive($files, $sourceDir) {
    $total = 0;
    foreach ($files as $file) {
        $path = $sourceDir . '/' . $file;
        if (!file_exists($path)) continue;
        
        if (is_dir($path)) {
            try {
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::SELF_FIRST
                );
                foreach ($iterator as $item) {
                    $total++;
                }
            } catch (Exception $e) {
                logError("Error counting directory: " . $e->getMessage());
            }
            $total++;
        } else {
            $total++;
        }
    }
    return $total;
}

logError("=== WORKER STARTED ===");
logMessage("=== WORKER STARTED ===");
logMessage("Operation ID: $operationId");
logMessage("PID: " . getmypid());

$maxWait = 10;
$waited = 0;
while (!file_exists($progressFile) && $waited < $maxWait) {
    sleep(1);
    $waited++;
}

if (!file_exists($progressFile)) {
    logError("Progress file not found after waiting: $progressFile");
    flock($fp, LOCK_UN);
    fclose($fp);
    exit(1);
}

$progress = json_decode(@file_get_contents($progressFile), true);
if (!$progress) {
    logError("Invalid progress data");
    flock($fp, LOCK_UN);
    fclose($fp);
    exit(1);
}

logMessage("Operation: " . $progress['operation']);
logMessage("Source: " . $progress['source_dir']);
logMessage("Target: " . $progress['target_dir']);
logMessage("Files to process: " . json_encode($progress['files']));

if (!is_dir($progress['source_dir'])) {
    logError("Source directory does not exist: " . $progress['source_dir']);
    $progress['active'] = false;
    $progress['completed'] = true;
    $progress['error'] = 'Source directory not found';
    @file_put_contents($progressFile, json_encode($progress));
    flock($fp, LOCK_UN);
    fclose($fp);
    exit(1);
}

if (!is_dir($progress['target_dir'])) {
    if (!@mkdir($progress['target_dir'], 0777, true)) {
        logError("Failed to create target directory: " . $progress['target_dir']);
        $progress['active'] = false;
        $progress['completed'] = true;
        $progress['error'] = 'Target directory cannot be created';
        @file_put_contents($progressFile, json_encode($progress));
        flock($fp, LOCK_UN);
        fclose($fp);
        exit(1);
    }
}

$total = getTotalItemsRecursive($progress['files'], $progress['source_dir']);
logMessage("Total items (recounted): " . $total);

$progress['total'] = $total;
$progress['current'] = 0;
$progress['current_percent'] = 0;
@file_put_contents($progressFile, json_encode($progress));

$processed = 0;

updateProgress(0, $total, "Starting...");

try {
    if ($progress['operation'] == 'copy') {
        foreach ($progress['files'] as $file) {
            if (isCancelled()) {
                logMessage("Operation cancelled by user");
                break;
            }
            $src = $progress['source_dir'] . '/' . $file;
            $dst = $progress['target_dir'] . '/' . $file;
            
            if (!file_exists($src)) {
                logMessage("Source not found: $src");
                $processed++;
                updateProgress($processed, $total, "[SKIPPED] $file");
                continue;
            }
            
            logMessage("Processing: $file");
            if (copyItem($src, $dst, $processed, $total, $file)) {
                logMessage("Successfully processed: $file");
            } else {
                logMessage("FAILED to process: $file");
                $processed++;
                updateProgress($processed, $total, "[FAILED] $file");
            }
        }
    } elseif ($progress['operation'] == 'move') {
        foreach ($progress['files'] as $file) {
            if (isCancelled()) {
                logMessage("Operation cancelled by user");
                break;
            }
            $src = $progress['source_dir'] . '/' . $file;
            $dst = $progress['target_dir'] . '/' . $file;
            
            if (!file_exists($src)) {
                logMessage("Source not found: $src");
                $processed++;
                updateProgress($processed, $total, "[SKIPPED] $file");
                continue;
            }
            
            logMessage("Processing: $file");
            if (moveItem($src, $dst, $processed, $total, $file)) {
                logMessage("Successfully processed: $file");
            } else {
                logMessage("FAILED to process: $file");
                $processed++;
                updateProgress($processed, $total, "[FAILED] $file");
            }
        }
    }
} catch (Exception $e) {
    logError("Exception: " . $e->getMessage());
    logError("Stack trace: " . $e->getTraceAsString());
}

if (file_exists($progressFile)) {
    $currentProgress = json_decode(@file_get_contents($progressFile), true);
    if ($currentProgress) {
        $currentProgress['active'] = false;
        $currentProgress['current'] = $processed;
        $currentProgress['completed'] = true;
        $currentProgress['current_percent'] = ($total > 0) ? min(100, floor(($processed / $total) * 100)) : 100;
        $currentProgress['current_file'] = '';
        $currentProgress['worker_pid'] = null;
        @file_put_contents($progressFile, json_encode($currentProgress));
        logMessage("Final progress: $processed / $total (" . $currentProgress['current_percent'] . "%)");
    }
}

flock($fp, LOCK_UN);
fclose($fp);
@unlink($lockFile);

logMessage("=== WORKER COMPLETED ===");
logError("=== WORKER COMPLETED ===");