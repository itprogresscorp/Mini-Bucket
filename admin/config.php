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
 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('ROOT_PATH', __DIR__);
define('DATA_PATH', '/mnt/data');
define('SMB_CONFIG', '/etc/samba/smb.conf');
define('NFS_EXPORTS', '/etc/exports');

function isAuthenticated() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

function getDB() {
    $db = new SQLite3('/var/www/minib/db.sqlite');
    $db->enableExceptions(true);
    return $db;
}

function getBaseDir() {
    $baseDir = DATA_PATH;
    if (!file_exists($baseDir)) {
        mkdir($baseDir, 0755, true);
    }
    return $baseDir;
}

$version = "3.5.8 Beta";
$status_install = "0";
$type_pro = "Master";

?>
