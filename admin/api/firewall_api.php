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

require_once '../lang/loader.php';

class FirewallAPI {
    
    private function jsonResponse($data, $success = true) {
        echo json_encode(['success' => $success, 'data' => $data]);
        exit;
    }
    
    private function jsonError($message) {
        echo json_encode(['success' => false, 'error' => $message]);
        exit;
    }
    
    private function executeCommand($command) {
        $fullCommand = "sudo " . $command . " 2>&1";
        $output = shell_exec($fullCommand);
        return ['success' => true, 'output' => $output];
    }
    
    public function getStatus() {
        $output = shell_exec('sudo ufw status verbose 2>&1 | grep -v "WARN:"');
        $status = [
            'active' => false,
            'logging' => 'off',
            'default_incoming' => 'deny',
            'default_outgoing' => 'allow',
            'default_routed' => 'disabled'
        ];
        
        if (strpos($output, 'Status: active') !== false) {
            $status['active'] = true;
        }
        
        if (preg_match('/Logging:\s*(\w+)/i', $output, $matches)) {
            $status['logging'] = strtolower($matches[1]);
        }
        
        if (preg_match('/Default:\s*(\w+)\s*\(incoming\)/i', $output, $matches)) {
            $status['default_incoming'] = strtolower($matches[1]);
        }
        
        if (preg_match('/Default:\s*(\w+)\s*\(outgoing\)/i', $output, $matches)) {
            $status['default_outgoing'] = strtolower($matches[1]);
        }
        
        if (preg_match('/Default:\s*(\w+)\s*\(routed\)/i', $output, $matches)) {
            $status['default_routed'] = strtolower($matches[1]);
        }
        
        $this->jsonResponse($status);
    }
    
    public function setEnabled($enabled) {
        $command = $enabled ? 'ufw --force enable' : 'ufw disable';
        $this->executeCommand($command);
        $this->jsonResponse(['enabled' => $enabled]);
    }
    
    public function getRules() {
        $output = shell_exec('sudo ufw status numbered 2>&1 | grep -E "^\[\s*[0-9]+\]" | grep -v "WARN:"');
        $lines = explode("\n", trim($output));
        $rules = [];
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            $isIPv6 = (strpos($line, '(v6)') !== false);
            $ipVersion = $isIPv6 ? 'ipv6' : 'ipv4';
            
            $cleanLine = str_replace(' (v6)', '', $line);
            
            if (preg_match('/^\s*\[\s*(\d+)\]\s+(\S+)\s+(\S+)\s+(\S+)\s+(.+?)(?:\s+#\s*(.*))?$/', $cleanLine, $matches)) {
                $ruleId = $matches[1];
                $portProto = $matches[2];
                $action = $matches[3];
                $direction = $matches[4];
                $from = trim($matches[5]);
                $comment = $matches[6] ?? '';
                
                $port = $portProto;
                $protocol = 'tcp';
                if (strpos($portProto, '/') !== false) {
                    list($port, $protocol) = explode('/', $portProto);
                }
                
                $rules[] = [
                    'id' => (int)$ruleId,
                    'port' => $port,
                    'protocol' => strtolower($protocol),
                    'action' => strtolower($action),
                    'direction' => strtolower($direction === 'IN' ? 'in' : 'out'),
                    'from' => $from,
                    'to' => 'anywhere',
                    'comment' => trim($comment),
                    'ip_version' => $ipVersion
                ];
            }
            elseif (preg_match('/^\s*\[\s*(\d+)\]\s+(\S+)\s+(\S+)\s+(\S+)\s+(.+?)(?:\s+#\s*(.*))?$/', $cleanLine, $matches)) {
                $ruleId = $matches[1];
                $appName = $matches[2];
                $action = $matches[3];
                $direction = $matches[4];
                $from = trim($matches[5]);
                $comment = $matches[6] ?? '';
                
                $rules[] = [
                    'id' => (int)$ruleId,
                    'port' => $appName,
                    'protocol' => 'app',
                    'action' => strtolower($action),
                    'direction' => strtolower($direction === 'IN' ? 'in' : 'out'),
                    'from' => $from,
                    'to' => 'anywhere',
                    'comment' => trim($comment),
                    'ip_version' => $ipVersion
                ];
            }
        }
        
        $this->jsonResponse($rules);
    }
    
    public function addRule($data) {
		global $lang3333, $lang3334, $lang3335, $lang3336;
        $direction = $data['direction'] ?? 'in';
        $action = $data['action'] ?? 'allow';
        $port = $data['port'] ?? '';
        $protocol = $data['protocol'] ?? 'tcp';
        $from = $data['from'] ?? '';
        $comment = $data['comment'] ?? '';
        $ipVersion = $data['ip_version'] ?? 'both';
        
        if (empty($port)) {
            $this->jsonError($lang3333);
        }
        
        if (empty($from) || $from === 'any' || $from === 'anywhere') {
            $from = '';
        }
        
        $commands = [];
        
        if ($ipVersion === 'ipv4') {
            $cmd = $this->buildCommand($direction, $action, $port, $protocol, $from, $comment);
            if ($cmd) $commands[] = $cmd;
        }

        elseif ($ipVersion === 'ipv6') {
            $cmd = $this->buildCommand($direction, $action, $port, $protocol, $from, $comment);
            if ($cmd) $commands[] = $cmd;
        }

        else {
            $cmd = $this->buildCommand($direction, $action, $port, $protocol, $from, $comment);
            if ($cmd) $commands[] = $cmd;
        }
        
        if (empty($commands)) {
            $this->jsonError($lang3334);
        }
        
        foreach ($commands as $cmd) {
            $result = $this->executeCommand("ufw $cmd");
            if (strpos($result['output'], 'ERROR') !== false || strpos($result['output'], 'Problem running') !== false) {
                $this->jsonError($lang3335 . $result['output']);
            }
        }
        
        $this->executeCommand('ufw reload');
        $this->jsonResponse(['message' => $lang3336 . $ipVersion]);
    }
    
    private function buildCommand($direction, $action, $port, $protocol, $from, $comment) {
        $cmd = "{$action}";
        
        if (!empty($from)) {
            $cmd .= " from {$from}";
        }
        
        $cmd .= " to any port {$port}";
        
        if ($protocol !== 'any') {
            $cmd .= " proto {$protocol}";
        }
        
        if ($direction === 'out') {
            $cmd .= " out";
        }
        
        if (!empty($comment)) {
            $cmd .= " comment '{$comment}'";
        }
        
        return $cmd;
    }
    
    public function deleteRule($ruleId) {
		global $lang3337, $lang3338;
        $result = $this->executeCommand("ufw --force delete {$ruleId}");
        
        if (strpos($result['output'], 'Rule deleted') !== false || strpos($result['output'], 'Rules have been deleted') !== false) {
            $this->executeCommand('ufw reload');
            $this->jsonResponse(['message' => $lang3337]);
        } else {
            $this->jsonError($lang3338 . $result['output']);
        }
    }
    
    public function updateRule($ruleId, $data) {
        $this->executeCommand("ufw --force delete {$ruleId}");
        $this->addRule($data);
    }
    
    public function getConnections() {
        $connections = ['incoming' => [], 'outgoing' => [], 'total' => 0];
        $output = shell_exec('sudo ss -tunap 2>/dev/null | grep -E "tcp|udp" | grep -v "LISTEN"');
        $lines = explode("\n", trim($output));
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            $parts = preg_split('/\s+/', $line);
            if (count($parts) < 6) continue;
            
            $protocol = $parts[0];
            $state = isset($parts[1]) ? $parts[1] : '';
            if ($state !== 'ESTAB' && $state !== 'ESTABLISHED') continue;
            
            $localAddr = $parts[4] ?? '';
            $peerAddr = $parts[5] ?? '';
            $process = isset($parts[6]) ? implode(' ', array_slice($parts, 6)) : '';
            
            if (preg_match('/(\d+\.\d+\.\d+\.\d+|\:\:[\w:]+|[\da-f:]+):(\d+)$/', $localAddr, $lm)) {
                $localIp = $lm[1];
                $localPort = $lm[2];
            } elseif (preg_match('/\[([\da-f:]+)\]:(\d+)/', $localAddr, $lm)) {
                $localIp = $lm[1];
                $localPort = $lm[2];
            } else { continue; }
            
            if (preg_match('/(\d+\.\d+\.\d+\.\d+|\:\:[\w:]+|[\da-f:]+):(\d+)$/', $peerAddr, $rm)) {
                $remoteIp = $rm[1];
                $remotePort = $rm[2];
            } elseif (preg_match('/\[([\da-f:]+)\]:(\d+)/', $peerAddr, $rm)) {
                $remoteIp = $rm[1];
                $remotePort = $rm[2];
            } else { continue; }
            
            $processName = '';
            if (preg_match('/"([^"]+)"/', $process, $procMatches)) {
                $processName = $procMatches[1];
            } elseif (preg_match('/users:\(\("([^"]+)"', $process, $procMatches)) {
                $processName = $procMatches[1];
            }
            
            $serverPorts = ['22', '80', '443', '21', '25', '3306', '5432', '139', '445', '8080', '8443', '1488'];
            $direction = in_array($localPort, $serverPorts) ? 'incoming' : 'outgoing';
            
            $connection = [
                'id' => uniqid(),
                'protocol' => strtoupper($protocol),
                'state' => $state,
                'local_ip' => $localIp,
                'local_port' => $localPort,
                'remote_ip' => $remoteIp,
                'remote_port' => $remotePort,
                'process' => $processName,
                'direction' => $direction
            ];
            
            if ($direction === 'incoming') {
                $connections['incoming'][] = $connection;
            } else {
                $connections['outgoing'][] = $connection;
            }
        }
        
        $connections['total'] = count($connections['incoming']) + count($connections['outgoing']);
        $this->jsonResponse($connections);
    }
    
    public function killConnection($ip, $port, $protocol = 'tcp') {
		global $lang3339, $lang3340;
        shell_exec("sudo ss -K dst {$ip} dport = {$port} 2>&1");
        $this->jsonResponse(['message' => $lang3339 . $ip . ":" . $port . $lang3340]);
    }
    
    public function getLogs($lines = 100, $filter = '') {
        $logFiles = ['/var/log/ufw.log', '/var/log/syslog'];
        $logContent = '';
        
        foreach ($logFiles as $logFile) {
            if (file_exists($logFile)) {
                if ($logFile == '/var/log/syslog') {
                    $output = shell_exec("sudo grep 'UFW' {$logFile} | tail -n {$lines} 2>/dev/null");
                } else {
                    $output = shell_exec("sudo tail -n {$lines} {$logFile} 2>/dev/null");
                }
                if (!empty(trim($output))) {
                    $logContent = $output;
                    break;
                }
            }
        }
        
        if (empty($logContent)) {
            $output = shell_exec("sudo journalctl -k -n {$lines} 2>/dev/null | grep -i ufw");
            if (!empty(trim($output))) {
                $logContent = $output;
            }
        }
        
        if (empty($logContent)) {
			global $lang3341;
            $this->jsonResponse(['error' => $lang3341, 'logging_off' => true]);
            return;
        }
        
        $logs = [];
        $logLines = explode("\n", trim($logContent));
        
        foreach ($logLines as $line) {
            if (empty(trim($line))) continue;
            
            if (preg_match('/(\w+\s+\d+\s+\d+:\d+:\d+).*\[UFW\s+(\w+)\]\s+(.+)/', $line, $matches)) {
                $timestamp = $matches[1];
                $action = $matches[2];
                $details = $matches[3];
                
                $formattedDetails = $this->formatLogDetails($details);
                
                if (!empty($filter) && stripos($action, $filter) === false && stripos($formattedDetails, $filter) === false) {
                    continue;
                }
                
                $logs[] = [
                    'timestamp' => $timestamp,
                    'action' => $action,
                    'details' => $formattedDetails,
                    'raw' => $line
                ];
            }
        }
        
        $this->jsonResponse($logs);
    }
    
    private function formatLogDetails($details) {
        $formatted = [];
        
        if (preg_match('/SRC=([\d\.]+)/', $details, $matches)) {
            $formatted[] = "IP: {$matches[1]}";
        }
        if (preg_match('/DST=([\d\.]+)/', $details, $matches)) {
            $formatted[] = "→ {$matches[1]}";
        }
        if (preg_match('/DPT=(\d+)/', $details, $matches)) {
            $formatted[] = "Port: {$matches[1]}";
        }
        if (preg_match('/PROTO=(\w+)/', $details, $matches)) {
            $formatted[] = "Proto: {$matches[1]}";
        }
        
        if (!empty($formatted)) {
            return implode(' | ', $formatted);
        }
        
        return $details;
    }
    
    public function setDefaultPolicy($incoming, $outgoing, $routed) {
		global $lang3342;
        $this->executeCommand($incoming === 'allow' ? "ufw default allow incoming" : "ufw default deny incoming");
        $this->executeCommand($outgoing === 'deny' ? "ufw default deny outgoing" : "ufw default allow outgoing");
        $this->executeCommand($routed === 'allow' ? "ufw default allow routed" : "ufw default deny routed");
        $this->executeCommand('ufw reload');
        $this->jsonResponse(['message' => $lang3342]);
    }
    
    public function setLogging($level) {
		global $lang3343, $lang3344;
        $validLevels = ['off', 'low', 'medium', 'high'];
        if (!in_array($level, $validLevels)) {
            $this->jsonError($lang3343);
        }
        $this->executeCommand("ufw logging {$level}");
        $this->jsonResponse(['message' => $lang3344 . $level]);
    }
    
    public function resetRules() {
		global $lang3345;
        $this->executeCommand("ufw --force reset");
        $this->jsonResponse(['message' => $lang3345]);
    }
    
    public function getAppProfiles() {
        $output = shell_exec('sudo ufw app list 2>&1 | grep -v "WARN:"');
        $apps = [];
        $lines = explode("\n", $output);
        $inApps = false;
        
        foreach ($lines as $line) {
            if (strpos($line, 'Available applications:') !== false) {
                $inApps = true;
                continue;
            }
            if ($inApps && preg_match('/^\s+(\S+)/', $line, $matches)) {
                $apps[] = $matches[1];
            }
        }
        
        $this->jsonResponse($apps);
    }
    
    public function addRuleByApp($appName, $action = 'allow', $ipVersion = 'both') {
		global $lang3346, $lang3347, $lang3348, $lang3349;
        if (empty($appName)) {
            $this->jsonError($lang3346);
        }
        
        $cmd = "ufw {$action} '{$appName}'";
        $result = $this->executeCommand($cmd);
        
        if (strpos($result['output'], 'ERROR') !== false) {
            $this->jsonError($lang3347 . $result['output']);
        }
        
        $this->executeCommand('ufw reload');
        $this->jsonResponse(['message' => $lang3348 . $appName . $lang3349 . $ipVersion]);
    }
}

$api = new FirewallAPI();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'get_status': $api->getStatus(); break;
        case 'set_enabled': $api->setEnabled(filter_var($_POST['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN)); break;
        case 'get_rules': $api->getRules(); break;
        case 'add_rule': $data = json_decode(file_get_contents('php://input'), true); $api->addRule($data); break;
        case 'delete_rule': $api->deleteRule($_POST['rule_id'] ?? 0); break;
        case 'update_rule': $data = json_decode(file_get_contents('php://input'), true); $api->updateRule($_GET['rule_id'] ?? 0, $data); break;
        case 'get_connections': $api->getConnections(); break;
        case 'kill_connection': $api->killConnection($_POST['ip'] ?? '', $_POST['port'] ?? '', $_POST['protocol'] ?? 'tcp'); break;
        case 'get_logs': $api->getLogs($_GET['lines'] ?? 100, $_GET['filter'] ?? ''); break;
        case 'set_default_policy': $api->setDefaultPolicy($_POST['incoming'] ?? 'deny', $_POST['outgoing'] ?? 'allow', $_POST['routed'] ?? 'deny'); break;
        case 'set_logging': $api->setLogging($_POST['level'] ?? 'low'); break;
        case 'reset_rules': $api->resetRules(); break;
        case 'get_app_profiles': $api->getAppProfiles(); break;
        case 'add_rule_by_app': $api->addRuleByApp($_POST['app_name'] ?? '', $_POST['action'] ?? 'allow', $_POST['ip_version'] ?? 'both'); break;
        default: $api->jsonError('Unknown action');
    }
} catch (Exception $e) {
    $api->jsonError($e->getMessage());
}
?>