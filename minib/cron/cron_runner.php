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
 * https://mini-bucket.ru/
 */

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
        fclose($lock_fp);
        exit(0);
    }

    $content = file_get_contents($cron_file);
    $jobs = json_decode($content, true);
    
    if (!is_array($jobs)) {
        file_put_contents($log_file, "[" . date("Y-m-d H:i:s") . "] Invalid jobs data\n", FILE_APPEND);
        fclose($lock_fp);
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
        if (!isset($job["enabled"]) || !$job["enabled"]) {
            continue;
        }
        
        $should_run = check_cron_time($job["minute"] ?? "*", $current_minute) &&
                      check_cron_time($job["hour"] ?? "*", $current_hour) &&
                      check_cron_time($job["day"] ?? "*", $current_day) &&
                      check_cron_time($job["month"] ?? "*", $current_month) &&
                      check_cron_time($job["weekday"] ?? "*", $current_weekday);
        
        if ($should_run) {
            file_put_contents($log_file, "[" . date("Y-m-d H:i:s") . "] Running: " . ($job["job_name"] ?? "unknown") . "\n", FILE_APPEND);
            
            if (isset($job["job_type"]) && $job["job_type"] === "script" && !empty($job["script_name"])) {
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
                $cmd = $job["command"] ?? "";
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
            
            file_put_contents($log_file, "[" . date("Y-m-d H:i:s") . "] " . ($job["job_name"] ?? "unknown") . " - " . $job["last_status"] . " (return: $return_var)\n", FILE_APPEND);
            if (!empty($output_str)) {
                file_put_contents($log_file, "Output: " . substr($output_str, 0, 500) . "\n", FILE_APPEND);
            }
            
            $jobs_updated = true;
        }
    }

    if ($jobs_updated && is_array($jobs)) {
        foreach ($jobs as &$job) {
            if (isset($job["last_output"])) {
                $job["last_output"] = cleanOutput($job["last_output"]);
            }
        }
        $json_data = json_encode($jobs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($json_data !== false) {
            file_put_contents($cron_file, $json_data);
            file_put_contents($log_file, "[" . date("Y-m-d H:i:s") . "] Saved " . count($jobs) . " jobs\n", FILE_APPEND);
        }
    }
} catch (Exception $e) {
    file_put_contents($log_file, "[" . date("Y-m-d H:i:s") . "] Exception: " . $e->getMessage() . "\n", FILE_APPEND);
} finally {
    flock($lock_fp, LOCK_UN);
    fclose($lock_fp);
}
