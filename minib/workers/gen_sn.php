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


require_once  '/var/www/html/admin/config.php';


function generateSerialNumber($length = 32) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $serial = '';
    
    for ($i = 0; $i < $length; $i++) {
        $serial .= $characters[random_int(0, $charactersLength - 1)];
    }
    
    return $serial;
}

try {
    $db = getDB();
    
    $check = $db->querySingle("SELECT COUNT(*) FROM hosts WHERE idHost = 1");
    
    if ($check == 0) {
        $serialNumber = generateSerialNumber(32);
        $stmt = $db->prepare("INSERT INTO hosts (idHost, HostSn) VALUES (1, :sn)");
        $stmt->bindValue(':sn', $serialNumber, SQLITE3_TEXT);
        $stmt->execute();
        echo "Created new host with SN: " . $serialNumber . "\n";
    } else {
        $existingSn = $db->querySingle("SELECT HostSn FROM hosts WHERE idHost = 1");
        
        if (empty($existingSn)) {
            $serialNumber = generateSerialNumber(32);
            $stmt = $db->prepare("UPDATE hosts SET HostSn = :sn WHERE idHost = 1");
            $stmt->bindValue(':sn', $serialNumber, SQLITE3_TEXT);
            $stmt->execute();
            echo "Generated and inserted SN: " . $serialNumber . "\n";
        } else {
            echo "SN already exists: " . $existingSn . "\n";
        }
    }
    
    $db->close();
    echo "Done!\n";
    
} catch (Exception $e) {
    error_log('Generate SN error: ' . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>