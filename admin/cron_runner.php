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
 
$cron_file = __DIR__ . "/cron_jobs.json";
$log_file = __DIR__ . "/cron.log";

if (!file_exists($cron_file)) {
    exit(0);
}

$jobs = json_decode(file_get_contents($cron_file), true);
if (!is_array($jobs)) {
    exit(0);
}

$now = time();
$current_minute = date("i", $now);
$current_hour = date("H", $now);
$current_day = date("d", $now);
$current_month = date("m", $now);
$current_weekday = date("w", $now);

function check_cron_time($cron_value, $current_value, $type) {
    if ($cron_value === "*") {
        return true;
    }
    
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

foreach ($jobs as $job) {
    if (!$job["enabled"]) {
        continue;
    }
    
    $should_run = true;
    $should_run = $should_run && check_cron_time($job["minute"], (int)$current_minute, "minute");
    $should_run = $should_run && check_cron_time($job["hour"], (int)$current_hour, "hour");
    $should_run = $should_run && check_cron_time($job["day"], (int)$current_day, "day");
    $should_run = $should_run && check_cron_time($job["month"], (int)$current_month, "month");
    $should_run = $should_run && check_cron_time($job["weekday"], (int)$current_weekday, "weekday");
    
    if ($should_run) {
        $command = $job["command"];
        $log_entry = "[" . date("Y-m-d H:i:s") . "] Running: " . $command . "\n";
        file_put_contents($log_file, $log_entry, FILE_APPEND);
        
        exec($command . " >> " . $log_file . " 2>&1 &");
    }
}