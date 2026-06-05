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

define('ROOT_PATH', dirname(dirname(__FILE__)));

if (file_exists(ROOT_PATH . '/config.php')) {
    require_once ROOT_PATH . '/config.php';
}

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-API-Key, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Max-Age: 86400");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

header('Content-Type: application/json');

// ========== ПРОВЕРКА API КЛЮЧА ==========
function validateApiKey() {
    global $db;
    
    if (!$db) {
        try {
            $db = getDB();
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database connection failed']);
            exit;
        }
    }
    
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $apiKey = $headers['X-API-Key'] ?? $_GET['api_key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '';
    
    if (empty($apiKey)) {
        if (isset($_SESSION['user_id'])) {
            return true;
        }
        http_response_code(401);
        echo json_encode(['error' => 'API key required']);
        exit;
    }
    
    $stmt = $db->prepare("SELECT idHost, hostName FROM hosts WHERE hostApiKey = :key");
    $stmt->bindValue(':key', $apiKey, SQLITE3_TEXT);
    $result = $stmt->execute();
    $host = $result->fetchArray(SQLITE3_ASSOC);
    
    if (!$host) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid API key']);
        exit;
    }
    
    return true;
}

validateApiKey();

$db = getDB();

// ========== ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ ==========
function escapeForShell($arg) {
    return escapeshellarg($arg);
}

function getSystemUsers() {
    $users = [];
    $output = shell_exec("getent passwd 2>/dev/null");
    if (!$output) return $users;
    
    $lines = explode("\n", trim($output));
    foreach ($lines as $line) {
        if (empty($line)) continue;
        $parts = explode(':', $line);
        if ($parts[2] >= 1000 && $parts[2] < 65534) {
            $groupsOutput = shell_exec("groups " . escapeshellarg($parts[0]) . " 2>/dev/null");
            $userGroups = [];
            if ($groupsOutput) {
                $groupsStr = trim(str_replace($parts[0] . ' : ', '', $groupsOutput));
                $userGroups = !empty($groupsStr) ? explode(' ', $groupsStr) : [];
            }
            
            $lastLogin = shell_exec("lastlog -u " . escapeshellarg($parts[0]) . " 2>/dev/null | tail -1");
            $lastLoginInfo = 'Never';
            if ($lastLogin && strpos($lastLogin, '**Never logged in**') === false && !strpos($lastLogin, 'Username')) {
                $lastLoginParts = preg_split('/\s+/', trim($lastLogin));
                if (count($lastLoginParts) >= 4) {
                    $lastLoginInfo = $lastLoginParts[0] . ' ' . $lastLoginParts[1] . ' ' . $lastLoginParts[2];
                }
            }
            
            $processCount = shell_exec("ps -u " . escapeshellarg($parts[0]) . " 2>/dev/null | wc -l");
            $isActive = intval($processCount) > 1;
            
            $smbCheck = shell_exec("sudo pdbedit -L 2>/dev/null | grep '^" . $parts[0] . ":'");
            $hasSmb = !empty(trim($smbCheck));
            
            $users[] = [
                'username' => $parts[0],
                'uid' => (int)$parts[2],
                'gid' => (int)$parts[3],
                'home' => $parts[5],
                'shell' => $parts[6],
                'groups' => $userGroups,
                'groups_str' => implode(', ', $userGroups),
                'last_login' => $lastLoginInfo,
                'is_active' => $isActive,
                'has_smb' => $hasSmb
            ];
        }
    }
    usort($users, function($a, $b) { return strcmp($a['username'], $b['username']); });
    return $users;
}

function getAllSystemGroups() {
    $groups = [];
    $output = shell_exec("getent group 2>/dev/null");
    if ($output) {
        $lines = explode("\n", trim($output));
        foreach ($lines as $line) {
            if (empty($line)) continue;
            $parts = explode(':', $line);
            if (($parts[2] >= 1000 && $parts[2] < 65534) || 
                in_array($parts[0], ['sudo', 'adm', 'users', 'www-data', 'ssh', 'dialout', 'cdrom', 'floppy', 'audio', 'video', 'plugdev', 'netdev', 'docker', 'lxd'])) {
                $members = !empty($parts[3]) ? explode(',', $parts[3]) : [];
                $groups[] = [
                    'name' => $parts[0],
                    'gid' => (int)$parts[2],
                    'members' => $members,
                    'members_count' => count($members)
                ];
            }
        }
    }
    usort($groups, function($a, $b) { return strcmp($a['name'], $b['name']); });
    return $groups;
}

function getPanelUsers() {
    global $db;
    $users = [];
    $res = $db->query("SELECT id, username, email, role, created_at FROM users ORDER BY id");
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $users[] = $row;
    }
    return $users;
}

function getStats() {
    global $db;
    return [
        'panel_users' => $db->querySingle("SELECT COUNT(*) FROM users"),
        'system_users' => count(getSystemUsers()),
        'system_groups' => count(getAllSystemGroups())
    ];
}

// ========== PANEL USER MANAGEMENT ==========
function addPanelUser($username, $password, $email, $role) {
    global $db;
    
    $check = $db->querySingle("SELECT id FROM users WHERE username = '$username'");
    if ($check) {
        return ['success' => false, 'error' => 'Username already exists'];
    }
    
    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT INTO users (username, password, email, role, created_at) VALUES (:username, :password, :email, :role, datetime('now'))");
    $stmt->bindValue(':username', $username, SQLITE3_TEXT);
    $stmt->bindValue(':password', $hashed, SQLITE3_TEXT);
    $stmt->bindValue(':email', $email, SQLITE3_TEXT);
    $stmt->bindValue(':role', $role, SQLITE3_TEXT);
    $stmt->execute();
    
    return ['success' => true, 'message' => 'Panel user added successfully'];
}

function updatePanelUser($id, $username, $email, $role) {
    global $db;
    
    $stmt = $db->prepare("UPDATE users SET username = :username, email = :email, role = :role WHERE id = :id");
    $stmt->bindValue(':username', $username, SQLITE3_TEXT);
    $stmt->bindValue(':email', $email, SQLITE3_TEXT);
    $stmt->bindValue(':role', $role, SQLITE3_TEXT);
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $stmt->execute();
    
    return ['success' => true, 'message' => 'Panel user updated'];
}

function changePanelPassword($id, $newPassword) {
    global $db;
    
    $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
    $stmt = $db->prepare("UPDATE users SET password = :password WHERE id = :id");
    $stmt->bindValue(':password', $hashed, SQLITE3_TEXT);
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $stmt->execute();
    
    return ['success' => true, 'message' => 'Password changed successfully'];
}

function deletePanelUser($id) {
    global $db;
    
    $user = $db->querySingle("SELECT username FROM users WHERE id = $id", true);
    if ($user && $user['username'] == 'admin') {
        return ['success' => false, 'error' => 'Cannot delete admin user'];
    }
    
    $db->exec("DELETE FROM users WHERE id = $id");
    return ['success' => true, 'message' => 'Panel user deleted'];
}

// ========== SYSTEM USER MANAGEMENT ==========
function addSystemUser($username, $password, $groups = []) {
    $check = shell_exec("id $username 2>/dev/null");
    if (!empty(trim($check))) {
        return ['success' => false, 'error' => 'System user already exists'];
    }
    
    if (!preg_match('/^[a-z_][a-z0-9_-]*$/', $username)) {
        return ['success' => false, 'error' => 'Invalid username format'];
    }
    
    $output = shell_exec("sudo useradd -m -s /bin/bash $username 2>&1");
    $output .= shell_exec("echo '$username:$password' | sudo chpasswd 2>&1");
    
    if (!empty($groups)) {
        $groupsStr = implode(',', $groups);
        shell_exec("sudo usermod -aG $groupsStr $username 2>&1");
    }
    
    shell_exec("(echo '$password'; echo '$password') | sudo smbpasswd -a $username -s 2>/dev/null");
    
    return ['success' => true, 'message' => 'System user created successfully'];
}

function updateSystemUserGroups($username, $groups = []) {
    $userInfo = shell_exec("id $username 2>/dev/null");
    preg_match('/gid=\d+\(([^)]+)\)/', $userInfo, $primaryGroup);
    $primary = $primaryGroup[1] ?? '';
    
    $allGroups = shell_exec("groups $username 2>/dev/null");
    $allGroups = str_replace($username . ' : ', '', trim($allGroups));
    $currentGroups = !empty($allGroups) ? explode(' ', $allGroups) : [];
    
    foreach ($currentGroups as $grp) {
        if ($grp != $primary && $grp != $username) {
            shell_exec("sudo deluser $username $grp 2>/dev/null");
        }
    }
    
    foreach ($groups as $grp) {
        shell_exec("sudo usermod -aG $grp $username 2>&1");
    }
    
    return ['success' => true, 'message' => 'Groups updated successfully'];
}

function changeSystemPassword($username, $newPassword) {
    $output = shell_exec("echo '$username:$newPassword' | sudo chpasswd 2>&1");
    
    shell_exec("(echo '$newPassword'; echo '$newPassword') | sudo smbpasswd -s -a $username 2>/dev/null");
    
    return ['success' => empty($output), 'message' => empty($output) ? 'Password changed' : 'Error changing password'];
}

function setSmbPassword($username, $password) {
    $output = shell_exec("(echo '$password'; echo '$password') | sudo smbpasswd -s -a $username 2>&1");
    $success = strpos($output, 'Added user') !== false || strpos($output, 'Password changed') !== false;
    
    return ['success' => $success, 'message' => $success ? 'SMB password set' : 'Error setting SMB password'];
}

function deleteSystemUser($username) {
    if ($username == 'root' || $username == $_SESSION['username'] ?? '') {
        return ['success' => false, 'error' => 'Cannot delete this user'];
    }
    
    $output = shell_exec("sudo userdel -r $username 2>&1");
    shell_exec("sudo smbpasswd -x $username 2>/dev/null");
    
    return ['success' => empty($output), 'message' => empty($output) ? 'System user deleted' : 'Error deleting user'];
}

// ========== GROUP MANAGEMENT ==========
function addSystemGroup($groupname) {
    $check = shell_exec("getent group $groupname 2>/dev/null");
    if (!empty(trim($check))) {
        return ['success' => false, 'error' => 'Group already exists'];
    }
    
    $output = shell_exec("sudo groupadd $groupname 2>&1");
    return ['success' => empty($output), 'message' => empty($output) ? 'Group created' : 'Error creating group'];
}

function renameSystemGroup($oldname, $newname) {
    if ($oldname == 'sudo' || $oldname == 'root') {
        return ['success' => false, 'error' => 'Cannot rename this group'];
    }
    
    $output = shell_exec("sudo groupmod -n $newname $oldname 2>&1");
    return ['success' => empty($output), 'message' => empty($output) ? 'Group renamed' : 'Error renaming group'];
}

function deleteSystemGroup($groupname) {
    if ($groupname == 'sudo' || $groupname == 'root') {
        return ['success' => false, 'error' => 'Cannot delete this group'];
    }
    
    $output = shell_exec("sudo groupdel $groupname 2>&1");
    return ['success' => empty($output), 'message' => empty($output) ? 'Group deleted' : 'Error deleting group'];
}

// ========== API ОБРАБОТЧИК ==========
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    // Get data
    case 'get_panel_users':
        echo json_encode(['success' => true, 'data' => getPanelUsers()]);
        break;
        
    case 'get_system_users':
        echo json_encode(['success' => true, 'data' => getSystemUsers()]);
        break;
        
    case 'get_all_groups':
        echo json_encode(['success' => true, 'data' => getAllSystemGroups()]);
        break;
        
    case 'get_stats':
        echo json_encode(['success' => true, 'data' => getStats()]);
        break;
        
    case 'get_all_data':
        echo json_encode([
            'success' => true,
            'data' => [
                'panel_users' => getPanelUsers(),
                'system_users' => getSystemUsers(),
                'groups' => getAllSystemGroups(),
                'stats' => getStats()
            ]
        ]);
        break;
    
    // Panel User actions
    case 'add_panel_user':
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $email = trim($_POST['email'] ?? '');
        $role = $_POST['role'] ?? 'user';
        
        if (empty($username) || empty($password)) {
            echo json_encode(['success' => false, 'error' => 'Username and password required']);
        } elseif (strlen($password) < 4) {
            echo json_encode(['success' => false, 'error' => 'Password must be at least 4 characters']);
        } else {
            echo json_encode(addPanelUser($username, $password, $email, $role));
        }
        break;
        
    case 'update_panel_user':
        $id = intval($_POST['user_id'] ?? 0);
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = $_POST['role'] ?? 'user';
        
        if ($id <= 0 || empty($username)) {
            echo json_encode(['success' => false, 'error' => 'Invalid data']);
        } else {
            echo json_encode(updatePanelUser($id, $username, $email, $role));
        }
        break;
        
    case 'change_panel_password':
        $id = intval($_POST['user_id'] ?? 0);
        $newPassword = $_POST['new_password'] ?? '';
        
        if ($id <= 0 || empty($newPassword)) {
            echo json_encode(['success' => false, 'error' => 'Invalid data']);
        } elseif (strlen($newPassword) < 4) {
            echo json_encode(['success' => false, 'error' => 'Password must be at least 4 characters']);
        } else {
            echo json_encode(changePanelPassword($id, $newPassword));
        }
        break;
        
    case 'delete_panel_user':
        $id = intval($_GET['id'] ?? 0);
        echo json_encode(deletePanelUser($id));
        break;
    
    // System User actions
    case 'add_system_user':
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $groups = $_POST['groups'] ?? [];
        
        if (empty($username) || empty($password)) {
            echo json_encode(['success' => false, 'error' => 'Username and password required']);
        } elseif (!preg_match('/^[a-z_][a-z0-9_-]*$/', $username)) {
            echo json_encode(['success' => false, 'error' => 'Invalid username format']);
        } elseif (strlen($password) < 4) {
            echo json_encode(['success' => false, 'error' => 'Password must be at least 4 characters']);
        } else {
            echo json_encode(addSystemUser($username, $password, $groups));
        }
        break;
        
    case 'update_system_groups':
        $username = trim($_POST['username'] ?? '');
        $groups = $_POST['groups'] ?? [];
        
        if (empty($username)) {
            echo json_encode(['success' => false, 'error' => 'Username required']);
        } else {
            echo json_encode(updateSystemUserGroups($username, $groups));
        }
        break;
        
    case 'change_system_password':
        $username = trim($_POST['username'] ?? '');
        $newPassword = $_POST['password'] ?? '';
        
        if (empty($username) || empty($newPassword)) {
            echo json_encode(['success' => false, 'error' => 'Username and password required']);
        } elseif (strlen($newPassword) < 4) {
            echo json_encode(['success' => false, 'error' => 'Password must be at least 4 characters']);
        } else {
            echo json_encode(changeSystemPassword($username, $newPassword));
        }
        break;
        
    case 'set_smb_password':
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            echo json_encode(['success' => false, 'error' => 'Username and password required']);
        } else {
            echo json_encode(setSmbPassword($username, $password));
        }
        break;
        
    case 'delete_system_user':
        $username = trim($_POST['username'] ?? '');
        echo json_encode(deleteSystemUser($username));
        break;
    
    // Group actions
    case 'add_system_group':
        $groupname = trim($_POST['groupname'] ?? '');
        
        if (empty($groupname)) {
            echo json_encode(['success' => false, 'error' => 'Group name required']);
        } elseif (!preg_match('/^[a-z_][a-z0-9_-]*$/', $groupname)) {
            echo json_encode(['success' => false, 'error' => 'Invalid group name format']);
        } else {
            echo json_encode(addSystemGroup($groupname));
        }
        break;
        
    case 'rename_system_group':
        $oldname = trim($_POST['oldname'] ?? '');
        $newname = trim($_POST['newname'] ?? '');
        
        if (empty($oldname) || empty($newname)) {
            echo json_encode(['success' => false, 'error' => 'Group names required']);
        } else {
            echo json_encode(renameSystemGroup($oldname, $newname));
        }
        break;
        
    case 'delete_system_group':
        $groupname = trim($_POST['groupname'] ?? '');
        echo json_encode(deleteSystemGroup($groupname));
        break;
    
    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
}
?>