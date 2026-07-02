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
 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('ROOT_PATH', __DIR__);
define('DATA_PATH', '/mnt/data');
define('SMB_CONFIG', '/etc/samba/smb.conf');
define('NFS_EXPORTS', '/etc/exports');

class SafeSQLite3 extends SQLite3 {
    private $maxRetries = 5;
    
    public function __construct($filename) {
        parent::__construct($filename);
        $this->exec('PRAGMA journal_mode = WAL');
        $this->busyTimeout(30000);
        $this->exec('PRAGMA synchronous = NORMAL');
        $this->exec('PRAGMA cache_size = 20000');
    }
    
    public function prepare($query) {
        for ($i = 0; $i < $this->maxRetries; $i++) {
            try {
                return parent::prepare($query);
            } catch (Exception $e) {
                if ($this->lastErrorCode() === 5 || strpos($e->getMessage(), 'locked') !== false) {
                    $waitTime = 100000 * pow(2, $i); // 0.1, 0.2, 0.4, 0.8, 1.6 секунд
                    usleep($waitTime);
                    continue;
                }
                throw $e;
            }
        }
        throw new Exception("Database locked after {$this->maxRetries} retries for query: " . substr($query, 0, 100));
    }
     
    public function exec($query) {
        for ($i = 0; $i < $this->maxRetries; $i++) {
            try {
                return parent::exec($query);
            } catch (Exception $e) {
                if ($this->lastErrorCode() === 5 || strpos($e->getMessage(), 'locked') !== false) {
                    $waitTime = 100000 * pow(2, $i);
                    usleep($waitTime);
                    continue;
                }
                throw $e;
            }
        }
        throw new Exception("Database locked after {$this->maxRetries} retries for exec: " . substr($query, 0, 100));
    }
    
    public function query($query) {
        for ($i = 0; $i < $this->maxRetries; $i++) {
            try {
                return parent::query($query);
            } catch (Exception $e) {
                if ($this->lastErrorCode() === 5 || strpos($e->getMessage(), 'locked') !== false) {
                    $waitTime = 100000 * pow(2, $i);
                    usleep($waitTime);
                    continue;
                }
                throw $e;
            }
        }
        throw new Exception("Database locked after {$this->maxRetries} retries for query: " . substr($query, 0, 100));
    }
}

function isAuthenticated() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: /login.php');
        exit;
    }
}

function getDB() {
    $db = new SafeSQLite3('/var/www/minib/db.sqlite');
    $db->enableExceptions(true);
    return $db;
}

function getDB2() {
    $db2 = new SafeSQLite3('/var/www/minib/db2.sqlite');
    $db2->enableExceptions(true);
    return $db2;
}

function getBaseDir() {
    $baseDir = DATA_PATH;
    if (!file_exists($baseDir)) {
        mkdir($baseDir, 0755, true);
    }
    return $baseDir;
}

$version = "3.6.6";
$status_install = "0";
$type_pro = "Master";
$set_lang = "En";

/*function getMountedDrives() {
    $drives = [];
    $mounts = file('/proc/mounts');
    foreach ($mounts as $mount) {
        $parts = preg_split('/\s+/', trim($mount));
        if (count($parts) >= 2 && strpos($parts[1], '/mnt/') === 0 || $parts[1] == '/' || strpos($parts[1], '/media/') === 0) {
            $drives[] = [
                'device' => $parts[0],
                'mount' => $parts[1],
                'type' => $parts[2]
            ];
        }
    }
    return $drives;
}*/

?>
