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

$available_langs = [];
foreach (glob(__DIR__ . '/*.php') as $file) {
    $filename = basename($file);
    if ($filename !== 'loader.php') {
        $lang_code = pathinfo($filename, PATHINFO_FILENAME);
        $LANG_NAME = $lang_code;
        $LANG_ICON = '';
        include $file;
        $available_langs[$lang_code] = [
            'name' => $LANG_NAME ?? $lang_code,
            'icon' => $LANG_ICON ?? ''
        ];
    }
}

$set_lang = 'En';

$config_path = dirname(__DIR__) . '/config.php';
if (file_exists($config_path)) {
    $config_content = file_get_contents($config_path);
    if (preg_match('/\$set_lang\s*=\s*["\']([^"\']+)["\']/', $config_content, $matches)) {
        $set_lang = $matches[1];
    }
}

$current_lang = $set_lang;

if (!isset($available_langs[$current_lang])) {
    $current_lang = 'En';
}

$lang_file = __DIR__ . '/' . $current_lang . '.php';
if (file_exists($lang_file)) {
    include $lang_file;
    foreach (get_defined_vars() as $key => $value) {
        if (strpos($key, 'lang') === 0 && is_numeric(substr($key, 4))) {
            $GLOBALS[$key] = $value;
        }
    }
    if (isset($LANG_NAME)) $GLOBALS['LANG_NAME'] = $LANG_NAME;
    if (isset($LANG_ICON)) $GLOBALS['LANG_ICON'] = $LANG_ICON;
}

if (isset($_GET['ajax_get_lang'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'lang' => $current_lang]);
    exit;
}

?>