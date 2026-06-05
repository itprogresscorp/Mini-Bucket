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

// ========== CONSTANTS ==========
if (!defined('CERT_DIR')) {
    define('CERT_DIR', '/var/www/minib/certs/crt');
}
if (!defined('CA_DIR')) {
    define('CA_DIR', '/var/www/minib/certs/ca');
}

if (!is_dir(CERT_DIR)) mkdir(CERT_DIR, 0755, true);
if (!is_dir(CA_DIR)) mkdir(CA_DIR, 0755, true);
if (!is_dir(CERT_DIR . '/revoked')) mkdir(CERT_DIR . '/revoked', 0755, true);

// ========== HELPER FUNCTION FOR DIGEST ALGORITHM ==========
function getDigestAlgo($signatureAlgo) {
    switch ($signatureAlgo) {
        case 'sha1':
            return 'sha1';
        case 'sha384':
            return 'sha384';
        case 'sha512':
            return 'sha512';
        case 'sha256':
        default:
            return 'sha256';
    }
}

// ========== DATABASE CONNECTION ==========
try {
    $db = getDB();
    
    $db->exec("CREATE TABLE IF NOT EXISTS ssl_certs_meta (
        idCert INTEGER PRIMARY KEY AUTOINCREMENT,
        certName TEXT NOT NULL UNIQUE,
        certDomain TEXT NOT NULL,
        certType TEXT DEFAULT 'self_signed',
        source TEXT DEFAULT 'self_signed',
        issuer TEXT,
        issuerCA TEXT,
        validFrom TEXT,
        validTo TEXT,
        san TEXT,
        status TEXT DEFAULT 'active',
        autoRenew INTEGER DEFAULT 0,
        lastRenew TEXT,
        keySize INTEGER DEFAULT 2048,
        signatureAlgo TEXT,
        serialNumber TEXT,
        comment TEXT DEFAULT '',
        createdAt TEXT DEFAULT CURRENT_TIMESTAMP,
        updatedAt TEXT DEFAULT CURRENT_TIMESTAMP
    )");
    
    $db->exec("CREATE TABLE IF NOT EXISTS ssl_ca_meta (
        idCA INTEGER PRIMARY KEY AUTOINCREMENT,
        caName TEXT NOT NULL UNIQUE,
        caSubject TEXT NOT NULL,
        caType TEXT DEFAULT 'root',
        parentCA TEXT,
        validFrom TEXT,
        validTo TEXT,
        status TEXT DEFAULT 'active',
        keySize INTEGER DEFAULT 4096,
        signatureAlgo TEXT,
        serialNumber TEXT,
        comment TEXT DEFAULT '',
        createdAt TEXT DEFAULT CURRENT_TIMESTAMP
    )");
    
    $db->exec("CREATE TABLE IF NOT EXISTS ssl_csrs (
        idCSR INTEGER PRIMARY KEY AUTOINCREMENT,
        csrName TEXT NOT NULL UNIQUE,
        csrContent TEXT,
        privateKey TEXT,
        subject TEXT,
        san TEXT,
        status TEXT DEFAULT 'pending',
        createdAt TEXT DEFAULT CURRENT_TIMESTAMP
    )");
    
    try {
        $db->exec("ALTER TABLE ssl_certs_meta ADD COLUMN comment TEXT DEFAULT ''");
    } catch (Exception $e) {}
    try {
        $db->exec("ALTER TABLE ssl_certs_meta ADD COLUMN signatureAlgo TEXT DEFAULT 'sha256'");
    } catch (Exception $e) {}
    try {
        $db->exec("ALTER TABLE ssl_ca_meta ADD COLUMN comment TEXT DEFAULT ''");
    } catch (Exception $e) {}
    try {
        $db->exec("ALTER TABLE ssl_ca_meta ADD COLUMN signatureAlgo TEXT DEFAULT 'sha256'");
    } catch (Exception $e) {}
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    exit;
}

// ========== HELPER FUNCTIONS ==========
function scanCertificates() {
    $certs = [];
    if (!is_dir(CERT_DIR)) return $certs;
    
    $files = glob(CERT_DIR . '/*.crt');
    $revokedDir = CERT_DIR . '/revoked';
    $revokedFiles = is_dir($revokedDir) ? glob($revokedDir . '/*.crt') : [];
    
    global $db;
    $comments = [];
    $result = $db->query("SELECT certName, comment FROM ssl_certs_meta");
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $comments[$row['certName']] = $row['comment'];
    }
    
    foreach ($files as $crtFile) {
        $baseName = pathinfo($crtFile, PATHINFO_FILENAME);
        $keyFile = CERT_DIR . '/' . $baseName . '.key';
        $chainFile = CERT_DIR . '/' . $baseName . '.chain.pem';
        $fullchainFile = CERT_DIR . '/' . $baseName . '.fullchain.pem';
        
        $certInfo = [
            'name' => $baseName,
            'has_key' => file_exists($keyFile),
            'has_chain' => file_exists($chainFile),
            'has_fullchain' => file_exists($fullchainFile),
            'crt_size' => file_exists($crtFile) ? filesize($crtFile) : 0,
            'crt_size_kb' => file_exists($crtFile) ? round(filesize($crtFile) / 1024, 2) : 0,
            'key_size' => file_exists($keyFile) ? filesize($keyFile) : 0,
            'key_size_kb' => file_exists($keyFile) ? round(filesize($keyFile) / 1024, 2) : 0,
            'modified' => file_exists($crtFile) ? date('Y-m-d H:i:s', filemtime($crtFile)) : null,
            'source' => 'self_signed',
            'revoked' => false,
            'comment' => $comments[$baseName] ?? ''
        ];
        
        if (file_exists($crtFile)) {
            $certContent = file_get_contents($crtFile);
            $parsed = openssl_x509_parse($certContent);
            if ($parsed) {
                $certInfo['subject'] = $parsed['subject']['CN'] ?? 'Unknown';
                $certInfo['issuer'] = $parsed['issuer']['CN'] ?? 'Unknown';
                $certInfo['valid_from'] = date('Y-m-d H:i:s', $parsed['validFrom_time_t']);
                $certInfo['valid_to'] = date('Y-m-d H:i:s', $parsed['validTo_time_t']);
                $certInfo['days_left'] = floor(($parsed['validTo_time_t'] - time()) / 86400);
                $certInfo['is_valid'] = $parsed['validTo_time_t'] > time();
                $certInfo['serial'] = $parsed['serialNumber'] ?? 'Unknown';
                $certInfo['signature_algo'] = $parsed['signatureTypeSN'] ?? 'Unknown';
                
                if (isset($parsed['extensions']['subjectAltName'])) {
                    preg_match_all('/DNS:([^,\s]+)/', $parsed['extensions']['subjectAltName'], $matches);
                    $certInfo['san'] = $matches[1] ?? [];
                }
                
                if ($parsed['issuer']['CN'] !== $parsed['subject']['CN']) {
                    $certInfo['source'] = 'ca_signed';
                }
            }
        }
        
        $certs[] = $certInfo;
    }
    
    foreach ($revokedFiles as $crtFile) {
        $filename = pathinfo($crtFile, PATHINFO_FILENAME);
        if (preg_match('/^(.+?)_revoked_\d+_\d+$/', $filename, $matches)) {
            $baseName = $matches[1];
        } else {
            $baseName = $filename;
        }
        
        $certInfo = [
            'name' => $baseName,
            'has_key' => false,
            'has_chain' => false,
            'has_fullchain' => false,
            'crt_size' => filesize($crtFile),
            'crt_size_kb' => round(filesize($crtFile) / 1024, 2),
            'key_size' => 0,
            'key_size_kb' => 0,
            'modified' => date('Y-m-d H:i:s', filemtime($crtFile)),
            'source' => 'unknown',
            'revoked' => true,
            'subject' => 'Revoked Certificate',
            'issuer' => 'Unknown',
            'days_left' => 0,
            'is_valid' => false,
            'valid_to' => 'Revoked',
            'comment' => $comments[$baseName] ?? ''
        ];
        
        $certContent = file_get_contents($crtFile);
        $parsed = openssl_x509_parse($certContent);
        if ($parsed) {
            $certInfo['subject'] = $parsed['subject']['CN'] ?? 'Revoked';
            $certInfo['issuer'] = $parsed['issuer']['CN'] ?? 'Unknown';
            $certInfo['valid_from'] = date('Y-m-d H:i:s', $parsed['validFrom_time_t']);
            $certInfo['valid_to'] = date('Y-m-d H:i:s', $parsed['validTo_time_t']);
        }
        
        $certs[] = $certInfo;
    }
    
    usort($certs, function($a, $b) {
        return strtotime($b['modified']) - strtotime($a['modified']);
    });
    
    return $certs;
}

function scanCA() {
    $cas = [];
    if (!is_dir(CA_DIR)) return $cas;
    
    $files = glob(CA_DIR . '/*.crt');
    
    global $db;
    $comments = [];
    $result = $db->query("SELECT caName, comment FROM ssl_ca_meta");
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $comments[$row['caName']] = $row['comment'];
    }
    
    foreach ($files as $crtFile) {
        $baseName = pathinfo($crtFile, PATHINFO_FILENAME);
        $keyFile = CA_DIR . '/' . $baseName . '.key';
        
        $caInfo = [
            'name' => $baseName,
            'has_key' => file_exists($keyFile),
            'is_ca' => true,
            'crt_size' => file_exists($crtFile) ? filesize($crtFile) : 0,
            'modified' => file_exists($crtFile) ? date('Y-m-d H:i:s', filemtime($crtFile)) : null,
            'ca_type' => 'root',
            'comment' => $comments[$baseName] ?? ''
        ];
        
        if (file_exists($crtFile)) {
            $certContent = file_get_contents($crtFile);
            $parsed = openssl_x509_parse($certContent);
            if ($parsed) {
                $caInfo['subject'] = $parsed['subject']['CN'] ?? 'Unknown';
                $caInfo['issuer'] = $parsed['issuer']['CN'] ?? 'Unknown';
                $caInfo['valid_from'] = date('Y-m-d H:i:s', $parsed['validFrom_time_t']);
                $caInfo['valid_to'] = date('Y-m-d H:i:s', $parsed['validTo_time_t']);
                $caInfo['days_left'] = floor(($parsed['validTo_time_t'] - time()) / 86400);
                $caInfo['is_valid'] = $parsed['validTo_time_t'] > time();
                $caInfo['is_root'] = ($parsed['subject']['CN'] == $parsed['issuer']['CN']);
                $caInfo['ca_type'] = $caInfo['is_root'] ? 'root' : 'intermediate';
            }
        }
        
        $cas[] = $caInfo;
    }
    
    $result = $db->query("SELECT caName, caType, parentCA, comment FROM ssl_ca_meta");
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        foreach ($cas as &$ca) {
            if ($ca['name'] === $row['caName']) {
                $ca['ca_type'] = $row['caType'];
                $ca['parentCA'] = $row['parentCA'];
                $ca['comment'] = $row['comment'] ?? $ca['comment'];
                break;
            }
        }
    }
    
    return $cas;
}

function updateMetadata($db, $certInfo) {
    $stmt = $db->prepare("INSERT OR REPLACE INTO ssl_certs_meta 
        (certName, certDomain, source, issuer, validFrom, validTo, san, status, keySize, signatureAlgo, serialNumber, comment, updatedAt)
        VALUES (:name, :domain, :source, :issuer, :validFrom, :validTo, :san, :status, :keySize, :sigAlgo, :serial, :comment, CURRENT_TIMESTAMP)");
    $stmt->bindValue(':name', $certInfo['name'], SQLITE3_TEXT);
    $stmt->bindValue(':domain', $certInfo['subject'] ?? $certInfo['name'], SQLITE3_TEXT);
    $stmt->bindValue(':source', $certInfo['source'] ?? 'self_signed', SQLITE3_TEXT);
    $stmt->bindValue(':issuer', $certInfo['issuer'] ?? 'Unknown', SQLITE3_TEXT);
    $stmt->bindValue(':validFrom', $certInfo['valid_from'] ?? '', SQLITE3_TEXT);
    $stmt->bindValue(':validTo', $certInfo['valid_to'] ?? '', SQLITE3_TEXT);
    $stmt->bindValue(':san', isset($certInfo['san']) ? implode(',', $certInfo['san']) : '', SQLITE3_TEXT);
    $stmt->bindValue(':status', ($certInfo['is_valid'] ?? false) ? 'active' : 'expired', SQLITE3_TEXT);
    $stmt->bindValue(':keySize', $certInfo['keySize'] ?? 2048, SQLITE3_INTEGER);
    $stmt->bindValue(':sigAlgo', $certInfo['signature_algo'] ?? 'sha256', SQLITE3_TEXT);
    $stmt->bindValue(':serial', $certInfo['serial'] ?? '', SQLITE3_TEXT);
    $stmt->bindValue(':comment', $certInfo['comment'] ?? '', SQLITE3_TEXT);
    $stmt->execute();
}

// ========== API HANDLERS ==========
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Update comment
if ($action === 'update_comment') {
    $name = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['name'] ?? '');
    $type = $_POST['type'] ?? 'cert';
    $comment = trim($_POST['comment'] ?? '');
    
    if (empty($name)) {
        echo json_encode(['success' => false, 'error' => 'Name required']);
        exit;
    }
    
    if ($type === 'ca') {
        $stmt = $db->prepare("UPDATE ssl_ca_meta SET comment = :comment, updatedAt = CURRENT_TIMESTAMP WHERE caName = :name");
        $stmt->bindValue(':comment', $comment, SQLITE3_TEXT);
        $stmt->bindValue(':name', $name, SQLITE3_TEXT);
        $stmt->execute();
    } else {
        $stmt = $db->prepare("UPDATE ssl_certs_meta SET comment = :comment, updatedAt = CURRENT_TIMESTAMP WHERE certName = :name");
        $stmt->bindValue(':comment', $comment, SQLITE3_TEXT);
        $stmt->bindValue(':name', $name, SQLITE3_TEXT);
        $stmt->execute();
    }
    
    echo json_encode(['success' => true, 'message' => 'Comment updated']);
    exit;
}

// Get CA hierarchy
if ($action === 'get_ca_hierarchy') {
    $cas = scanCA();
    $hierarchy = [
        'root_cas' => [],
        'intermediate_cas' => []
    ];
    
    foreach ($cas as $ca) {
        if ($ca['ca_type'] === 'root' || $ca['is_root']) {
            $hierarchy['root_cas'][] = $ca;
        } else {
            $hierarchy['intermediate_cas'][] = $ca;
        }
    }
    
    echo json_encode(['success' => true, 'hierarchy' => $hierarchy, 'all_cas' => $cas]);
    exit;
}

// List certificates
if ($action === 'list') {
    $certs = scanCertificates();
    $cas = scanCA();
    echo json_encode(['success' => true, 'certificates' => $certs, 'cas' => $cas]);
    exit;
}

// List CAs only
if ($action === 'list_cas') {
    $cas = scanCA();
    echo json_encode(['success' => true, 'cas' => $cas]);
    exit;
}

// Get certificate details
if ($action === 'details') {
    $name = preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['name'] ?? '');
    $type = $_GET['type'] ?? 'cert';
    
    if (empty($name)) {
        echo json_encode(['success' => false, 'error' => 'Certificate name required']);
        exit;
    }
    
    $dir = ($type === 'ca') ? CA_DIR : CERT_DIR;
    $crtFile = $dir . '/' . $name . '.crt';
    
    if (!file_exists($crtFile) && $type !== 'ca') {
        $revokedFile = CERT_DIR . '/revoked/' . $name . '_revoked_*.crt';
        $revokedFiles = glob($revokedFile);
        if (!empty($revokedFiles)) {
            $crtFile = $revokedFiles[0];
        }
    }
    
    if (!file_exists($crtFile)) {
        echo json_encode(['success' => false, 'error' => 'Certificate not found']);
        exit;
    }
    
    $certContent = file_get_contents($crtFile);
    $parsed = openssl_x509_parse($certContent);
    
    $comment = '';
    if ($type === 'ca') {
        $result = $db->querySingle("SELECT comment FROM ssl_ca_meta WHERE caName = '$name'", true);
        $comment = $result['comment'] ?? '';
    } else {
        $result = $db->querySingle("SELECT comment FROM ssl_certs_meta WHERE certName = '$name'", true);
        $comment = $result['comment'] ?? '';
    }
    
    $details = [
        'name' => $name,
        'type' => $type,
        'subject' => $parsed['subject']['CN'] ?? 'Unknown',
        'issuer' => $parsed['issuer']['CN'] ?? 'Unknown',
        'valid_from' => date('Y-m-d H:i:s', $parsed['validFrom_time_t']),
        'valid_to' => date('Y-m-d H:i:s', $parsed['validTo_time_t']),
        'days_left' => floor(($parsed['validTo_time_t'] - time()) / 86400),
        'is_valid' => $parsed['validTo_time_t'] > time(),
        'serial' => $parsed['serialNumber'] ?? 'Unknown',
        'signature_algo' => $parsed['signatureTypeSN'] ?? 'Unknown',
        'crt_size' => filesize($crtFile),
        'crt_size_kb' => round(filesize($crtFile) / 1024, 2),
        'has_key' => file_exists($dir . '/' . $name . '.key'),
        'has_chain' => file_exists($dir . '/' . $name . '.chain.pem'),
        'has_fullchain' => file_exists($dir . '/' . $name . '.fullchain.pem'),
        'is_ca' => ($type === 'ca') || (isset($parsed['extensions']['basicConstraints']) && strpos($parsed['extensions']['basicConstraints'], 'CA:TRUE') !== false),
        'cert_pem' => $certContent,
        'source' => 'self_signed',
        'revoked' => strpos($crtFile, '/revoked/') !== false,
        'comment' => $comment
    ];
    
    if ($details['issuer'] !== $details['subject']) {
        $details['source'] = 'ca_signed';
    }
    
    if (file_exists($dir . '/' . $name . '.key')) {
        $details['key_size'] = filesize($dir . '/' . $name . '.key');
        $details['key_size_kb'] = round(filesize($dir . '/' . $name . '.key') / 1024, 2);
    }
    
    if (isset($parsed['extensions']['subjectAltName'])) {
        preg_match_all('/DNS:([^,\s]+)/', $parsed['extensions']['subjectAltName'], $matches);
        $details['san'] = $matches[1] ?? [];
    }
    
    echo json_encode(['success' => true, 'certificate' => $details]);
    exit;
}

// Get statistics
if ($action === 'stats') {
    $certs = scanCertificates();
    $cas = scanCA();
    
    $revokedDir = CERT_DIR . '/revoked';
    $revokedCount = 0;
    if (is_dir($revokedDir)) {
        $revokedCount = count(glob($revokedDir . '/*.crt'));
    }
    
    $total = count($certs);
    $valid = 0;
    $expiringSoon = 0;
    $expired = 0;
    $hasKey = 0;
    $totalSize = 0;
    
    foreach ($certs as $cert) {
        if ($cert['revoked'] ?? false) {
            continue;
        }
        if ($cert['is_valid'] ?? false) {
            $valid++;
            if (($cert['days_left'] ?? 999) <= 30 && ($cert['days_left'] ?? 999) > 0) {
                $expiringSoon++;
            }
        } else if (!($cert['revoked'] ?? false)) {
            $expired++;
        }
        if ($cert['has_key']) $hasKey++;
        $totalSize += $cert['crt_size'];
    }
    
    echo json_encode([
        'success' => true,
        'stats' => [
            'total' => $total,
            'valid' => $valid,
            'expiring_soon' => $expiringSoon,
            'expired' => $expired,
            'revoked' => $revokedCount,
            'has_key' => $hasKey,
            'total_size' => round($totalSize / 1024, 2),
            'total_cas' => count($cas)
        ]
    ]);
    exit;
}

// Create Root CA
if ($action === 'create_ca') {
    $caName = preg_replace('/[^a-zA-Z0-9_-]/', '', trim($_POST['caName'] ?? ''));
    $country = trim($_POST['country'] ?? 'US');
    $state = trim($_POST['state'] ?? 'California');
    $city = trim($_POST['city'] ?? 'San Francisco');
    $org = trim($_POST['org'] ?? 'Mini-B CA');
    $orgUnit = trim($_POST['orgUnit'] ?? 'Certificate Authority');
    $cn = trim($_POST['cn'] ?? $caName . ' Root CA');
    $email = trim($_POST['email'] ?? 'ca@localhost');
    $days = (int)($_POST['days'] ?? 3650);
    $keySize = (int)($_POST['keySize'] ?? 4096);
    $signatureAlgo = trim($_POST['signatureAlgo'] ?? 'sha256');
    $comment = trim($_POST['comment'] ?? '');
    $digestAlgo = getDigestAlgo($signatureAlgo);
    
    if (empty($caName)) {
        echo json_encode(['success' => false, 'error' => 'CA name required']);
        exit;
    }
    
    $crtFile = CA_DIR . '/' . $caName . '.crt';
    $keyFile = CA_DIR . '/' . $caName . '.key';
    
    if (file_exists($crtFile)) {
        echo json_encode(['success' => false, 'error' => 'CA already exists']);
        exit;
    }
    
    $dn = [
        'countryName' => $country,
        'stateOrProvinceName' => $state,
        'localityName' => $city,
        'organizationName' => $org,
        'organizationalUnitName' => $orgUnit,
        'commonName' => $cn,
        'emailAddress' => $email
    ];
    
    $privateKey = openssl_pkey_new([
        'private_key_bits' => $keySize,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ]);
    
    $csr = openssl_csr_new($dn, $privateKey, ['digest_alg' => $digestAlgo]);
    
    $caExtensions = [
        'basicConstraints' => 'CA:TRUE',
        'keyUsage' => 'cRLSign, keyCertSign',
        'subjectKeyIdentifier' => 'hash',
        'authorityKeyIdentifier' => 'keyid:always,issuer:always',
        'digest_alg' => $digestAlgo
    ];
    
    $x509 = openssl_csr_sign($csr, null, $privateKey, $days, $caExtensions);
    
    if (!openssl_x509_export_to_file($x509, $crtFile)) {
        echo json_encode(['success' => false, 'error' => 'Failed to export certificate']);
        exit;
    }
    if (!openssl_pkey_export_to_file($privateKey, $keyFile)) {
        echo json_encode(['success' => false, 'error' => 'Failed to export private key']);
        exit;
    }
    chmod($keyFile, 0600);
    copy($crtFile, CA_DIR . '/' . $caName . '.pem');
    
    $parsed = openssl_x509_parse(file_get_contents($crtFile));
    $stmt = $db->prepare("INSERT OR REPLACE INTO ssl_ca_meta 
        (caName, caSubject, caType, validFrom, validTo, keySize, signatureAlgo, serialNumber, comment, status)
        VALUES (:name, :subject, 'root', :validFrom, :validTo, :keySize, :sigAlgo, :serial, :comment, 'active')");
    $stmt->bindValue(':name', $caName, SQLITE3_TEXT);
    $stmt->bindValue(':subject', $cn, SQLITE3_TEXT);
    $stmt->bindValue(':validFrom', date('Y-m-d H:i:s', $parsed['validFrom_time_t']), SQLITE3_TEXT);
    $stmt->bindValue(':validTo', date('Y-m-d H:i:s', $parsed['validTo_time_t']), SQLITE3_TEXT);
    $stmt->bindValue(':keySize', $keySize, SQLITE3_INTEGER);
    $stmt->bindValue(':sigAlgo', $signatureAlgo, SQLITE3_TEXT);
    $stmt->bindValue(':serial', $parsed['serialNumber'] ?? '', SQLITE3_TEXT);
    $stmt->bindValue(':comment', $comment, SQLITE3_TEXT);
    $stmt->execute();
    
    echo json_encode(['success' => true, 'message' => 'Root CA created successfully']);
    exit;
}

// Create Intermediate CA
if ($action === 'create_intermediate_ca') {
    $caName = preg_replace('/[^a-zA-Z0-9_-]/', '', trim($_POST['caName'] ?? ''));
    $rootCAName = preg_replace('/[^a-zA-Z0-9_-]/', '', trim($_POST['rootCAName'] ?? ''));
    $country = trim($_POST['country'] ?? 'US');
    $state = trim($_POST['state'] ?? 'California');
    $city = trim($_POST['city'] ?? 'San Francisco');
    $org = trim($_POST['org'] ?? 'Mini-B Sub CA');
    $orgUnit = trim($_POST['orgUnit'] ?? 'Subordinate CA');
    $cn = trim($_POST['cn'] ?? $caName . ' Intermediate CA');
    $email = trim($_POST['email'] ?? 'ca@localhost');
    $days = (int)($_POST['days'] ?? 3650);
    $keySize = (int)($_POST['keySize'] ?? 4096);
    $signatureAlgo = trim($_POST['signatureAlgo'] ?? 'sha256');
    $comment = trim($_POST['comment'] ?? '');
    $digestAlgo = getDigestAlgo($signatureAlgo);
    
    if (empty($caName) || empty($rootCAName)) {
        echo json_encode(['success' => false, 'error' => 'CA name and Root CA name required']);
        exit;
    }
    
    $rootCrtFile = CA_DIR . '/' . $rootCAName . '.crt';
    $rootKeyFile = CA_DIR . '/' . $rootCAName . '.key';
    $crtFile = CA_DIR . '/' . $caName . '.crt';
    $keyFile = CA_DIR . '/' . $caName . '.key';
    
    if (!file_exists($rootCrtFile) || !file_exists($rootKeyFile)) {
        echo json_encode(['success' => false, 'error' => 'Root CA not found']);
        exit;
    }
    
    if (file_exists($crtFile)) {
        echo json_encode(['success' => false, 'error' => 'Intermediate CA already exists']);
        exit;
    }
    
    $rootCert = file_get_contents($rootCrtFile);
    $rootKeyContent = file_get_contents($rootKeyFile);
    $rootKey = openssl_pkey_get_private($rootKeyContent);
    
    if (!$rootKey) {
        echo json_encode(['success' => false, 'error' => 'Failed to load root CA private key']);
        exit;
    }
    
    $dn = [
        'countryName' => $country,
        'stateOrProvinceName' => $state,
        'localityName' => $city,
        'organizationName' => $org,
        'organizationalUnitName' => $orgUnit,
        'commonName' => $cn,
        'emailAddress' => $email
    ];
    
    $privateKey = openssl_pkey_new([
        'private_key_bits' => $keySize,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ]);
    
    $csr = openssl_csr_new($dn, $privateKey, ['digest_alg' => $digestAlgo]);
    
    $caExtensions = [
        'basicConstraints' => 'CA:TRUE, pathlen:0',
        'keyUsage' => 'cRLSign, keyCertSign',
        'subjectKeyIdentifier' => 'hash',
        'authorityKeyIdentifier' => 'keyid:always,issuer:always',
        'digest_alg' => $digestAlgo
    ];
    
    $x509 = openssl_csr_sign($csr, $rootCert, $rootKey, $days, $caExtensions);
    
    openssl_x509_export_to_file($x509, $crtFile);
    openssl_pkey_export_to_file($privateKey, $keyFile);
    chmod($keyFile, 0600);
    
    $chainContent = file_get_contents($crtFile) . "\n" . file_get_contents($rootCrtFile);
    file_put_contents(CA_DIR . '/' . $caName . '.chain.pem', $chainContent);
    
    $parsed = openssl_x509_parse(file_get_contents($crtFile));
    $stmt = $db->prepare("INSERT OR REPLACE INTO ssl_ca_meta 
        (caName, caSubject, caType, parentCA, validFrom, validTo, keySize, signatureAlgo, serialNumber, comment, status)
        VALUES (:name, :subject, 'intermediate', :parent, :validFrom, :validTo, :keySize, :sigAlgo, :serial, :comment, 'active')");
    $stmt->bindValue(':name', $caName, SQLITE3_TEXT);
    $stmt->bindValue(':subject', $cn, SQLITE3_TEXT);
    $stmt->bindValue(':parent', $rootCAName, SQLITE3_TEXT);
    $stmt->bindValue(':validFrom', date('Y-m-d H:i:s', $parsed['validFrom_time_t']), SQLITE3_TEXT);
    $stmt->bindValue(':validTo', date('Y-m-d H:i:s', $parsed['validTo_time_t']), SQLITE3_TEXT);
    $stmt->bindValue(':keySize', $keySize, SQLITE3_INTEGER);
    $stmt->bindValue(':sigAlgo', $signatureAlgo, SQLITE3_TEXT);
    $stmt->bindValue(':serial', $parsed['serialNumber'] ?? '', SQLITE3_TEXT);
    $stmt->bindValue(':comment', $comment, SQLITE3_TEXT);
    $stmt->execute();
    
    echo json_encode(['success' => true, 'message' => 'Intermediate CA created successfully']);
    exit;
}

// Create certificate signed by CA
if ($action === 'create_signed') {
    $certName = preg_replace('/[^a-zA-Z0-9_-]/', '', trim($_POST['certName'] ?? ''));
    $caName = preg_replace('/[^a-zA-Z0-9_-]/', '', trim($_POST['caName'] ?? ''));
    $domain = trim($_POST['domain'] ?? '');
    $sans = trim($_POST['sans'] ?? '');
    $days = (int)($_POST['days'] ?? 365);
    $country = trim($_POST['country'] ?? 'US');
    $state = trim($_POST['state'] ?? 'California');
    $city = trim($_POST['city'] ?? 'San Francisco');
    $org = trim($_POST['org'] ?? 'Mini-B');
    $orgUnit = trim($_POST['orgUnit'] ?? 'IT');
    $email = trim($_POST['email'] ?? 'admin@localhost');
    $keySize = (int)($_POST['keySize'] ?? 2048);
    $signatureAlgo = trim($_POST['signatureAlgo'] ?? 'sha256');
    $comment = trim($_POST['comment'] ?? '');
    $digestAlgo = getDigestAlgo($signatureAlgo);
    
    if (empty($certName) || empty($caName) || empty($domain)) {
        echo json_encode(['success' => false, 'error' => 'Certificate name, CA name and domain required']);
        exit;
    }
    
    $caCrtFile = CA_DIR . '/' . $caName . '.crt';
    $caKeyFile = CA_DIR . '/' . $caName . '.key';
    
    if (!file_exists($caCrtFile) || !file_exists($caKeyFile)) {
        echo json_encode(['success' => false, 'error' => 'CA not found']);
        exit;
    }
    
    $crtFile = CERT_DIR . '/' . $certName . '.crt';
    $keyFile = CERT_DIR . '/' . $certName . '.key';
    
    if (file_exists($crtFile)) {
        echo json_encode(['success' => false, 'error' => 'Certificate already exists']);
        exit;
    }
    
    $caCert = file_get_contents($caCrtFile);
    $caKeyContent = file_get_contents($caKeyFile);
    $caKey = openssl_pkey_get_private($caKeyContent);
    
    if (!$caKey) {
        echo json_encode(['success' => false, 'error' => 'Failed to load CA private key']);
        exit;
    }
    
    $dn = [
        'countryName' => $country,
        'stateOrProvinceName' => $state,
        'localityName' => $city,
        'organizationName' => $org,
        'organizationalUnitName' => $orgUnit,
        'commonName' => $domain,
        'emailAddress' => $email
    ];
    
    $sanList = [$domain];
    if (!empty($sans)) {
        $extraSans = array_map('trim', explode(',', $sans));
        $sanList = array_merge($sanList, $extraSans);
    }
    $sanList = array_unique($sanList);
    
    $sanExtension = 'DNS:' . implode(',DNS:', $sanList);
    
    $extensions = [
        'subjectAltName' => $sanExtension,
        'keyUsage' => 'digitalSignature, keyEncipherment',
        'extendedKeyUsage' => 'serverAuth, clientAuth',
        'digest_alg' => $digestAlgo
    ];
    
    $privateKey = openssl_pkey_new([
        'private_key_bits' => $keySize,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ]);
    
    $csr = openssl_csr_new($dn, $privateKey, ['digest_alg' => $digestAlgo]);
    $x509 = openssl_csr_sign($csr, $caCert, $caKey, $days, $extensions);
    
    if (!$x509) {
        echo json_encode(['success' => false, 'error' => 'Failed to sign certificate']);
        exit;
    }
    
    openssl_x509_export_to_file($x509, $crtFile);
    openssl_pkey_export_to_file($privateKey, $keyFile);
    chmod($keyFile, 0600);
    
    $fullchain = file_get_contents($crtFile) . "\n" . file_get_contents($caCrtFile);
    file_put_contents(CERT_DIR . '/' . $certName . '.fullchain.pem', $fullchain);
    
    $parsed = openssl_x509_parse(file_get_contents($crtFile));
    $certInfo = [
        'name' => $certName,
        'subject' => $domain,
        'issuer' => $parsed['issuer']['CN'] ?? $caName,
        'valid_from' => date('Y-m-d H:i:s', $parsed['validFrom_time_t']),
        'valid_to' => date('Y-m-d H:i:s', $parsed['validTo_time_t']),
        'is_valid' => true,
        'san' => $sanList,
        'keySize' => $keySize,
        'signature_algo' => $signatureAlgo,
        'serial' => $parsed['serialNumber'] ?? '',
        'source' => 'ca_signed',
        'comment' => $comment
    ];
    updateMetadata($db, $certInfo);
    
    echo json_encode(['success' => true, 'message' => 'Certificate created and signed by CA successfully']);
    exit;
}

// Sign existing CSR
if ($action === 'sign_csr') {
    $certName = preg_replace('/[^a-zA-Z0-9_-]/', '', trim($_POST['certName'] ?? ''));
    $caName = preg_replace('/[^a-zA-Z0-9_-]/', '', trim($_POST['caName'] ?? ''));
    $csrContent = trim($_POST['csrContent'] ?? '');
    $days = (int)($_POST['days'] ?? 365);
    $signatureAlgo = trim($_POST['signatureAlgo'] ?? 'sha256');
    $comment = trim($_POST['comment'] ?? '');
    $digestAlgo = getDigestAlgo($signatureAlgo);
    
    if (empty($certName) || empty($caName) || empty($csrContent)) {
        echo json_encode(['success' => false, 'error' => 'Certificate name, CA name and CSR content required']);
        exit;
    }
    
    $caCrtFile = CA_DIR . '/' . $caName . '.crt';
    $caKeyFile = CA_DIR . '/' . $caName . '.key';
    
    if (!file_exists($caCrtFile) || !file_exists($caKeyFile)) {
        echo json_encode(['success' => false, 'error' => 'CA not found']);
        exit;
    }
    
    $caCert = file_get_contents($caCrtFile);
    $caKeyContent = file_get_contents($caKeyFile);
    $caKey = openssl_pkey_get_private($caKeyContent);
    
    if (!$caKey) {
        echo json_encode(['success' => false, 'error' => 'Failed to load CA private key']);
        exit;
    }
    
    $signExtensions = ['digest_alg' => $digestAlgo];
    $x509 = openssl_csr_sign($csrContent, $caCert, $caKey, $days, $signExtensions);
    
    if ($x509) {
        $crtFile = CERT_DIR . '/' . $certName . '.crt';
        openssl_x509_export_to_file($x509, $crtFile);
        
        $fullchain = file_get_contents($crtFile) . "\n" . file_get_contents($caCrtFile);
        file_put_contents(CERT_DIR . '/' . $certName . '.fullchain.pem', $fullchain);
        
        $parsed = openssl_x509_parse(file_get_contents($crtFile));
        $certInfo = [
            'name' => $certName,
            'subject' => $parsed['subject']['CN'] ?? $certName,
            'issuer' => $parsed['issuer']['CN'] ?? $caName,
            'valid_from' => date('Y-m-d H:i:s', $parsed['validFrom_time_t']),
            'valid_to' => date('Y-m-d H:i:s', $parsed['validTo_time_t']),
            'is_valid' => true,
            'san' => [],
            'keySize' => 2048,
            'signature_algo' => $signatureAlgo,
            'serial' => $parsed['serialNumber'] ?? '',
            'source' => 'ca_signed',
            'comment' => $comment
        ];
        
        if (isset($parsed['extensions']['subjectAltName'])) {
            preg_match_all('/DNS:([^,\s]+)/', $parsed['extensions']['subjectAltName'], $matches);
            $certInfo['san'] = $matches[1] ?? [];
        }
        
        updateMetadata($db, $certInfo);
        
        echo json_encode(['success' => true, 'message' => 'CSR signed successfully']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to sign CSR']);
    }
    exit;
}

// Revoke certificate
if ($action === 'revoke') {
    $name = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['name'] ?? '');
    
    if (empty($name)) {
        echo json_encode(['success' => false, 'error' => 'Certificate name required']);
        exit;
    }
    
    $crtFile = CERT_DIR . '/' . $name . '.crt';
    $revokedDir = CERT_DIR . '/revoked';
    
    if (!is_dir($revokedDir)) {
        mkdir($revokedDir, 0755, true);
    }
    
    if (file_exists($crtFile)) {
        $revokedCrtFile = $revokedDir . '/' . $name . '_revoked_' . date('Ymd_His') . '.crt';
        rename($crtFile, $revokedCrtFile);
        
        $keyFile = CERT_DIR . '/' . $name . '.key';
        if (file_exists($keyFile)) {
            rename($keyFile, $revokedDir . '/' . $name . '_revoked_' . date('Ymd_His') . '.key');
        }
        
        $chainFile = CERT_DIR . '/' . $name . '.chain.pem';
        if (file_exists($chainFile)) {
            rename($chainFile, $revokedDir . '/' . $name . '_revoked_' . date('Ymd_His') . '.chain.pem');
        }
        
        $fullchainFile = CERT_DIR . '/' . $name . '.fullchain.pem';
        if (file_exists($fullchainFile)) {
            rename($fullchainFile, $revokedDir . '/' . $name . '_revoked_' . date('Ymd_His') . '.fullchain.pem');
        }
        
        $stmt = $db->prepare("UPDATE ssl_certs_meta SET status = 'revoked', updatedAt = CURRENT_TIMESTAMP WHERE certName = :name");
        $stmt->bindValue(':name', $name, SQLITE3_TEXT);
        $stmt->execute();
        
        echo json_encode(['success' => true, 'message' => 'Certificate revoked successfully']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Certificate not found']);
    }
    exit;
}

// Export certificate
if ($action === 'export') {
    $name = preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['name'] ?? '');
    $type = $_GET['type'] ?? 'cert';
    
    if (empty($name)) {
        http_response_code(400);
        echo json_encode(['error' => 'Certificate name required']);
        exit;
    }
    
    $dir = ($type === 'ca') ? CA_DIR : CERT_DIR;
    $filesToZip = [];
    
    $extensions = ($type === 'ca') ? ['.crt', '.key', '.pem', '.chain.pem'] : ['.crt', '.key', '.chain.pem', '.fullchain.pem'];
    
    foreach ($extensions as $ext) {
        $file = $dir . '/' . $name . $ext;
        if (file_exists($file)) {
            $filesToZip[] = $file;
        }
    }
    
    if (empty($filesToZip)) {
        http_response_code(404);
        echo json_encode(['error' => 'No files found to export']);
        exit;
    }
    
    $zipName = $name . '_certificate_bundle.zip';
    $zipPath = sys_get_temp_dir() . '/' . $zipName;
    
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
        foreach ($filesToZip as $file) {
            $zip->addFile($file, basename($file));
        }
        $zip->close();
        
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $zipName . '"');
        header('Content-Length: ' . filesize($zipPath));
        readfile($zipPath);
        unlink($zipPath);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create ZIP archive']);
    }
    exit;
}

// Create self-signed certificate
if ($action === 'create') {
    $certName = preg_replace('/[^a-zA-Z0-9_-]/', '', trim($_POST['certName'] ?? ''));
    $domain = trim($_POST['domain'] ?? '');
    $sans = trim($_POST['sans'] ?? '');
    $days = (int)($_POST['days'] ?? 365);
    $country = trim($_POST['country'] ?? 'US');
    $state = trim($_POST['state'] ?? 'California');
    $city = trim($_POST['city'] ?? 'San Francisco');
    $org = trim($_POST['org'] ?? 'Mini-B');
    $orgUnit = trim($_POST['orgUnit'] ?? 'IT');
    $email = trim($_POST['email'] ?? 'admin@localhost');
    $keySize = (int)($_POST['keySize'] ?? 2048);
    $signatureAlgo = trim($_POST['signatureAlgo'] ?? 'sha256');
    $comment = trim($_POST['comment'] ?? '');
    $digestAlgo = getDigestAlgo($signatureAlgo);
    
    if (empty($certName) || empty($domain)) {
        echo json_encode(['success' => false, 'error' => 'Certificate name and domain required']);
        exit;
    }
    
    $crtFile = CERT_DIR . '/' . $certName . '.crt';
    $keyFile = CERT_DIR . '/' . $certName . '.key';
    
    if (file_exists($crtFile)) {
        echo json_encode(['success' => false, 'error' => 'Certificate already exists']);
        exit;
    }
    
    $sanList = [$domain];
    if (!empty($sans)) {
        $extraSans = array_map('trim', explode(',', $sans));
        $sanList = array_merge($sanList, $extraSans);
    }
    $sanList = array_unique($sanList);
    
    $sanExtension = 'DNS:' . implode(',DNS:', $sanList);
    
    $extensions = [
        'subjectAltName' => $sanExtension,
        'keyUsage' => 'digitalSignature, keyEncipherment',
        'extendedKeyUsage' => 'serverAuth, clientAuth',
        'digest_alg' => $digestAlgo
    ];
    
    $privateKey = openssl_pkey_new([
        'private_key_bits' => $keySize,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ]);
    
    $dn = [
        'countryName' => $country,
        'stateOrProvinceName' => $state,
        'localityName' => $city,
        'organizationName' => $org,
        'organizationalUnitName' => $orgUnit,
        'commonName' => $domain,
        'emailAddress' => $email
    ];
    
    $csr = openssl_csr_new($dn, $privateKey, ['digest_alg' => $digestAlgo]);
    $x509 = openssl_csr_sign($csr, null, $privateKey, $days, $extensions);
    
    if (!$x509) {
        echo json_encode(['success' => false, 'error' => 'Failed to create certificate']);
        exit;
    }
    
    openssl_x509_export_to_file($x509, $crtFile);
    openssl_pkey_export_to_file($privateKey, $keyFile);
    chmod($keyFile, 0600);
    
    $parsed = openssl_x509_parse(file_get_contents($crtFile));
    $certInfo = [
        'name' => $certName,
        'subject' => $domain,
        'issuer' => $parsed['issuer']['CN'] ?? 'Self-Signed',
        'valid_from' => date('Y-m-d H:i:s', $parsed['validFrom_time_t']),
        'valid_to' => date('Y-m-d H:i:s', $parsed['validTo_time_t']),
        'is_valid' => true,
        'san' => $sanList,
        'keySize' => $keySize,
        'signature_algo' => $signatureAlgo,
        'serial' => $parsed['serialNumber'] ?? '',
        'source' => 'self_signed',
        'comment' => $comment
    ];
    updateMetadata($db, $certInfo);
    
    echo json_encode(['success' => true, 'message' => 'Self-signed certificate created successfully']);
    exit;
}

// Import certificate
if ($action === 'import') {
    $certName = preg_replace('/[^a-zA-Z0-9_-]/', '', trim($_POST['certName'] ?? ''));
    $certContent = trim($_POST['certContent'] ?? '');
    $keyContent = trim($_POST['keyContent'] ?? '');
    $chainContent = trim($_POST['chainContent'] ?? '');
    $isCA = isset($_POST['isCA']) ? (int)$_POST['isCA'] : 0;
    $comment = trim($_POST['comment'] ?? '');
    
    if (empty($certName) || empty($certContent)) {
        echo json_encode(['success' => false, 'error' => 'Certificate name and content required']);
        exit;
    }
    
    $testCert = @openssl_x509_read($certContent);
    if (!$testCert) {
        echo json_encode(['success' => false, 'error' => 'Invalid certificate format']);
        exit;
    }
    
    $targetDir = ($isCA) ? CA_DIR : CERT_DIR;
    
    file_put_contents($targetDir . '/' . $certName . '.crt', $certContent);
    
    if (!empty($keyContent)) {
        file_put_contents($targetDir . '/' . $certName . '.key', $keyContent);
        chmod($targetDir . '/' . $certName . '.key', 0600);
    }
    
    if (!empty($chainContent)) {
        file_put_contents($targetDir . '/' . $certName . '.chain.pem', $chainContent);
        if (!$isCA) {
            $fullchain = $certContent . "\n" . $chainContent;
            file_put_contents(CERT_DIR . '/' . $certName . '.fullchain.pem', $fullchain);
        }
    }
    
    $parsed = openssl_x509_parse($certContent);
    
    if (!$isCA) {
        $certInfo = [
            'name' => $certName,
            'subject' => $parsed['subject']['CN'] ?? $certName,
            'issuer' => $parsed['issuer']['CN'] ?? 'Unknown',
            'valid_from' => date('Y-m-d H:i:s', $parsed['validFrom_time_t']),
            'valid_to' => date('Y-m-d H:i:s', $parsed['validTo_time_t']),
            'is_valid' => $parsed['validTo_time_t'] > time(),
            'san' => [],
            'signature_algo' => $parsed['signatureTypeSN'] ?? '',
            'serial' => $parsed['serialNumber'] ?? '',
            'source' => 'imported',
            'comment' => $comment
        ];
        
        if (isset($parsed['extensions']['subjectAltName'])) {
            preg_match_all('/DNS:([^,\s]+)/', $parsed['extensions']['subjectAltName'], $matches);
            $certInfo['san'] = $matches[1] ?? [];
        }
        
        updateMetadata($db, $certInfo);
    } else {
        $stmt = $db->prepare("INSERT OR REPLACE INTO ssl_ca_meta 
            (caName, caSubject, caType, validFrom, validTo, signatureAlgo, serialNumber, comment, status)
            VALUES (:name, :subject, 'imported', :validFrom, :validTo, :sigAlgo, :serial, :comment, 'active')");
        $stmt->bindValue(':name', $certName, SQLITE3_TEXT);
        $stmt->bindValue(':subject', $parsed['subject']['CN'] ?? $certName, SQLITE3_TEXT);
        $stmt->bindValue(':validFrom', date('Y-m-d H:i:s', $parsed['validFrom_time_t']), SQLITE3_TEXT);
        $stmt->bindValue(':validTo', date('Y-m-d H:i:s', $parsed['validTo_time_t']), SQLITE3_TEXT);
        $stmt->bindValue(':sigAlgo', $parsed['signatureTypeSN'] ?? 'sha256RSA', SQLITE3_TEXT);
        $stmt->bindValue(':serial', $parsed['serialNumber'] ?? '', SQLITE3_TEXT);
        $stmt->bindValue(':comment', $comment, SQLITE3_TEXT);
        $stmt->execute();
    }
    
    echo json_encode(['success' => true, 'message' => 'Certificate imported successfully']);
    exit;
}

// Delete certificate
if ($action === 'delete') {
    $name = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['name'] ?? $_GET['name'] ?? '');
    $type = $_POST['type'] ?? $_GET['type'] ?? 'cert';
    $force = isset($_POST['force']) ? (int)$_POST['force'] : 0;
    
    if (empty($name)) {
        echo json_encode(['success' => false, 'error' => 'Certificate name required']);
        exit;
    }
    
    $targetDir = ($type === 'ca') ? CA_DIR : CERT_DIR;
    
    if ($type !== 'ca' && !$force) {
        $crtFile = $targetDir . '/' . $name . '.crt';
        $isRevoked = false;
        
        if (!file_exists($crtFile)) {
            $revokedFiles = glob(CERT_DIR . '/revoked/' . $name . '_revoked_*');
            if (!empty($revokedFiles)) {
                $isRevoked = true;
            }
        }
        
        $stmt = $db->prepare("SELECT status FROM ssl_certs_meta WHERE certName = :name");
        $stmt->bindValue(':name', $name, SQLITE3_TEXT);
        $resultCheck = $stmt->execute();
        $row = $resultCheck->fetchArray(SQLITE3_ASSOC);
        
        if ($row && $row['status'] !== 'revoked' && !$isRevoked) {
            echo json_encode([
                'success' => false, 
                'error' => 'Certificate is still active! Please revoke it before deletion, or use force_delete=true to override.',
                'requires_revocation' => true
            ]);
            exit;
        }
    }
    
    $deleted = [];
    
    $extensions = ($type === 'ca') ? ['.crt', '.key', '.pem', '.chain.pem'] : ['.crt', '.key', '.chain.pem', '.fullchain.pem'];
    
    foreach ($extensions as $ext) {
        $file = $targetDir . '/' . $name . $ext;
        if (file_exists($file) && unlink($file)) {
            $deleted[] = $ext;
        }
    }
    
    if ($type !== 'ca') {
        $revokedFiles = glob(CERT_DIR . '/revoked/' . $name . '_revoked_*');
        foreach ($revokedFiles as $file) {
            if (unlink($file)) {
                $deleted[] = basename($file);
            }
        }
    }
    
    if ($type === 'ca') {
        $stmt = $db->prepare("DELETE FROM ssl_ca_meta WHERE caName = :name");
        $stmt->bindValue(':name', $name, SQLITE3_TEXT);
        $stmt->execute();
    } else {
        $stmt = $db->prepare("DELETE FROM ssl_certs_meta WHERE certName = :name");
        $stmt->bindValue(':name', $name, SQLITE3_TEXT);
        $stmt->execute();
    }
    
    echo json_encode(['success' => true, 'message' => count($deleted) . ' file(s) deleted']);
    exit;
}

// Download certificate
if ($action === 'download') {
    $name = preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['name'] ?? '');
    $type = $_GET['type'] ?? 'crt';
    $certType = $_GET['certType'] ?? 'cert';
    
    $dir = ($certType === 'ca') ? CA_DIR : CERT_DIR;
    
    $fileMap = [
        'crt' => '.crt',
        'key' => '.key',
        'chain' => '.chain.pem',
        'fullchain' => '.fullchain.pem',
        'pem' => '.pem'
    ];
    
    $file = $dir . '/' . $name . ($fileMap[$type] ?? '.crt');
    
    if (!file_exists($file)) {
        http_response_code(404);
        echo json_encode(['error' => 'File not found']);
        exit;
    }
    
    header('Content-Type: application/x-pem-file');
    header('Content-Disposition: attachment; filename="' . basename($file) . '"');
    header('Content-Length: ' . filesize($file));
    readfile($file);
    exit;
}

// View certificate content
if ($action === 'view') {
    $name = preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['name'] ?? '');
    $type = $_GET['type'] ?? 'crt';
    $certType = $_GET['certType'] ?? 'cert';
    
    $dir = ($certType === 'ca') ? CA_DIR : CERT_DIR;
    
    $fileMap = [
        'crt' => '.crt',
        'key' => '.key',
        'chain' => '.chain.pem',
        'fullchain' => '.fullchain.pem'
    ];
    
    $file = $dir . '/' . $name . ($fileMap[$type] ?? '.crt');
    
    if (!file_exists($file)) {
        http_response_code(404);
        echo "File not found";
        exit;
    }
    
    header('Content-Type: text/plain');
    echo file_get_contents($file);
    exit;
}

// Generate CSR
if ($action === 'generate_csr') {
    $csrName = preg_replace('/[^a-zA-Z0-9_-]/', '', trim($_POST['csrName'] ?? ''));
    $domain = trim($_POST['domain'] ?? '');
    $sans = trim($_POST['sans'] ?? '');
    $country = trim($_POST['country'] ?? 'US');
    $state = trim($_POST['state'] ?? 'California');
    $city = trim($_POST['city'] ?? 'San Francisco');
    $org = trim($_POST['org'] ?? 'Mini-B');
    $orgUnit = trim($_POST['orgUnit'] ?? 'IT');
    $email = trim($_POST['email'] ?? 'admin@localhost');
    $keySize = (int)($_POST['keySize'] ?? 2048);
    $signatureAlgo = trim($_POST['signatureAlgo'] ?? 'sha256');
    $digestAlgo = getDigestAlgo($signatureAlgo);
    
    if (empty($csrName) || empty($domain)) {
        echo json_encode(['success' => false, 'error' => 'CSR name and domain required']);
        exit;
    }
    
    $dn = [
        'countryName' => $country,
        'stateOrProvinceName' => $state,
        'localityName' => $city,
        'organizationName' => $org,
        'organizationalUnitName' => $orgUnit,
        'commonName' => $domain,
        'emailAddress' => $email
    ];
    
    $privateKey = openssl_pkey_new([
        'private_key_bits' => $keySize,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ]);
    
    $sanList = [$domain];
    if (!empty($sans)) {
        $extraSans = array_map('trim', explode(',', $sans));
        $sanList = array_merge($sanList, $extraSans);
    }
    $sanList = array_unique($sanList);
    
    $tmpConfig = tempnam(sys_get_temp_dir(), 'openssl_');
    $sanConfig = "[req]\nreq_extensions = v3_req\ndistinguished_name = req_distinguished_name\n[req_distinguished_name]\n[v3_req]\nsubjectAltName = " . 'DNS:' . implode(',DNS:', $sanList);
    file_put_contents($tmpConfig, $sanConfig);
    
    $csr = openssl_csr_new($dn, $privateKey, ['config' => $tmpConfig, 'digest_alg' => $digestAlgo]);
    
    if (!$csr) {
        unlink($tmpConfig);
        echo json_encode(['success' => false, 'error' => 'Failed to generate CSR']);
        exit;
    }
    
    openssl_csr_export($csr, $csrContent);
    openssl_pkey_export($privateKey, $keyContent);
    
    unlink($tmpConfig);
    
    $csrFile = CERT_DIR . '/' . $csrName . '.csr';
    $keyFile = CERT_DIR . '/' . $csrName . '.key';
    
    file_put_contents($csrFile, $csrContent);
    file_put_contents($keyFile, $keyContent);
    chmod($keyFile, 0600);
    
    $stmt = $db->prepare("INSERT OR REPLACE INTO ssl_csrs 
        (csrName, csrContent, privateKey, subject, san, status)
        VALUES (:name, :csr, :key, :subject, :san, 'generated')");
    $stmt->bindValue(':name', $csrName, SQLITE3_TEXT);
    $stmt->bindValue(':csr', $csrContent, SQLITE3_TEXT);
    $stmt->bindValue(':key', $keyContent, SQLITE3_TEXT);
    $stmt->bindValue(':subject', $domain, SQLITE3_TEXT);
    $stmt->bindValue(':san', implode(',', $sanList), SQLITE3_TEXT);
    $stmt->execute();
    
    echo json_encode([
        'success' => true,
        'message' => 'CSR generated successfully',
        'csr' => $csrContent,
        'private_key' => $keyContent,
        'csr_file' => $csrFile,
        'key_file' => $keyFile
    ]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Unknown action']);
?>