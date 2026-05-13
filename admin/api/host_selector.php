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
 
define('ROOT_PATH', dirname(dirname(__FILE__)));

if (file_exists(ROOT_PATH . '/config.php')) {
    require_once ROOT_PATH . '/config.php';
}

session_start();
isAuthenticated();

header('Content-Type: application/json');

try {
    $db = getDB();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_hosts':
        $result = $db->query("SELECT idHost, hostName, hostIp, hostStatus, hostApiKey FROM hosts ORDER BY idHost");
        $hosts = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $hosts[] = $row;
        }
        
        $currentHostId = isset($_SESSION['current_host_id']) ? $_SESSION['current_host_id'] : 1;
        
        $currentHost = null;
        foreach ($hosts as $host) {
            if ($host['idHost'] == $currentHostId) {
                $currentHost = $host;
                break;
            }
        }
        
        echo json_encode([
            'success' => true,
            'hosts' => $hosts,
            'current_host_id' => $currentHostId,
            'current_host' => $currentHost
        ]);
        break;
        
    case 'set_current_host':
        $hostId = intval($_POST['host_id'] ?? 0);
        
        if ($hostId > 0) {
            $stmt = $db->prepare("SELECT idHost FROM hosts WHERE idHost = :id");
            $stmt->bindValue(':id', $hostId, SQLITE3_INTEGER);
            $result = $stmt->execute();
            
            if ($result->fetchArray()) {
                $_SESSION['current_host_id'] = $hostId;
                echo json_encode(['success' => true, 'host_id' => $hostId]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Host not found']);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid host ID']);
        }
        break;
        
    default:
        echo json_encode(['error' => 'Invalid action']);
}
?>