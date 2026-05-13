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
 
declare(strict_types=1);

stream_context_set_default([
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false,
        'allow_self_signed' => true
    ]
]);

define('ROOT_PATH', dirname(dirname(__FILE__)));

if (file_exists(ROOT_PATH . '/config.php')) {
    require_once ROOT_PATH . '/config.php';
}

isAuthenticated();

define('AUTH_ENABLED', false);
define('AUTH_USERNAME', 'admin');
define('AUTH_PASSWORD_HASH', password_hash('your_password_here', PASSWORD_DEFAULT));

if (AUTH_ENABLED) {
    if (!isset($_SERVER['PHP_AUTH_USER']) || 
        $_SERVER['PHP_AUTH_USER'] !== AUTH_USERNAME || 
        !password_verify($_SERVER['PHP_AUTH_PW'], AUTH_PASSWORD_HASH)) {
        header('WWW-Authenticate: Basic realm="Key Rotation Administration"');
        header('HTTP/1.0 401 Unauthorized');
        exit('Access Denied');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    
    try {
        $db = getDB();
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'delete_tasks':
                $taskIds = json_decode($_POST['task_ids'] ?? '[]', true);
                if (!empty($taskIds)) {
                    $placeholders = implode(',', array_fill(0, count($taskIds), '?'));
                    $stmt = $db->prepare("DELETE FROM key_rotation_tasks WHERE taskId IN ({$placeholders})");
                    foreach ($taskIds as $idx => $id) {
                        $stmt->bindValue($idx + 1, $id, SQLITE3_INTEGER);
                    }
                    $stmt->execute();
                    echo json_encode(['success' => true, 'deleted' => count($taskIds)]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'No task identifiers provided']);
                }
                break;
                
            case 'retry_tasks':
                $taskIds = json_decode($_POST['task_ids'] ?? '[]', true);
                if (!empty($taskIds)) {
                    $placeholders = implode(',', array_fill(0, count($taskIds), '?'));
                    $stmt = $db->prepare("
                        UPDATE key_rotation_tasks 
                        SET status = 'pending', attempts = 0, lastError = NULL, dateUpdateStatus = datetime('now') 
                        WHERE taskId IN ({$placeholders})
                    ");
                    foreach ($taskIds as $idx => $id) {
                        $stmt->bindValue($idx + 1, $id, SQLITE3_INTEGER);
                    }
                    $stmt->execute();
                    echo json_encode(['success' => true, 'retried' => count($taskIds)]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'No task identifiers provided']);
                }
                break;
                
            case 'delete_all_completed':
                $stmt = $db->prepare("DELETE FROM key_rotation_tasks WHERE status IN ('completed', 'failed')");
                $stmt->execute();
                echo json_encode(['success' => true, 'deleted' => $db->changes()]);
                break;
                
            case 'get_stats':
                $stats = [];
                $statuses = ['pending', 'in_progress', 'awaiting_confirmation', 'completed', 'failed'];
                foreach ($statuses as $status) {
                    $stmt = $db->prepare("SELECT COUNT(*) as count FROM key_rotation_tasks WHERE status = :status");
                    $stmt->bindValue(':status', $status, SQLITE3_TEXT);
                    $result = $stmt->execute();
                    $row = $result->fetchArray(SQLITE3_ASSOC);
                    $stats[$status] = $row['count'];
                }
                echo json_encode(['success' => true, 'stats' => $stats]);
                break;
                
            default:
                echo json_encode(['success' => false, 'error' => 'Unrecognized action']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    
    exit;
}

try {
    $db = getDB();
    
    $statusFilter = $_GET['status'] ?? '';
    $searchQuery = $_GET['search'] ?? '';
    $sortBy = $_GET['sort_by'] ?? 'taskId';
    $sortOrder = $_GET['sort_order'] ?? 'DESC';
    $page = max(1, intval($_GET['page'] ?? 1));
    $itemsPerPage = 50;
    $offset = ($page - 1) * $itemsPerPage;
    
    $allowedSortFields = ['taskId', 'targetSn', 'status', 'attempts', 'maxAttempts', 'dateCreate', 'dateUpdateStatus', 'completedDate'];
    $sortBy = in_array($sortBy, $allowedSortFields) ? $sortBy : 'taskId';
    $sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';
    
    $whereConditions = [];
    $params = [];
    
    if ($statusFilter && in_array($statusFilter, ['pending', 'in_progress', 'awaiting_confirmation', 'completed', 'failed'])) {
        $whereConditions[] = "status = :status";
        $params[':status'] = $statusFilter;
    }
    
    if ($searchQuery) {
        $whereConditions[] = "(targetSn LIKE :search OR lastError LIKE :search)";
        $params[':search'] = "%{$searchQuery}%";
    }
    
    $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";
    
    $countSql = "SELECT COUNT(*) as total FROM key_rotation_tasks {$whereClause}";
    $countStmt = $db->prepare($countSql);
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value, SQLITE3_TEXT);
    }
    $countResult = $countStmt->execute();
    $totalTasks = $countResult->fetchArray(SQLITE3_ASSOC)['total'];
    $totalPages = ceil($totalTasks / $itemsPerPage);
    
    $sql = "SELECT * FROM key_rotation_tasks {$whereClause} ORDER BY {$sortBy} {$sortOrder} LIMIT :limit OFFSET :offset";
    $stmt = $db->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, SQLITE3_TEXT);
    }
    $stmt->bindValue(':limit', $itemsPerPage, SQLITE3_INTEGER);
    $stmt->bindValue(':offset', $offset, SQLITE3_INTEGER);
    $result = $stmt->execute();
    
    $tasks = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $tasks[] = $row;
    }
    
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Key Rotation Manager</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Text', 'SF Pro UI', 'Helvetica Neue', 'Segoe UI', system-ui, sans-serif;
            background: #f5f5f7;
            color: #1d1c1f;
            line-height: 1.4286;
            letter-spacing: -0.016em;
        }
        
        .container {
            max-width: 1440px;
            margin: 0 auto;
            padding: 32px 24px;
        }
        
        /* Header Section */
        .header {
            margin-bottom: 32px;
        }
        
        .header h1 {
            font-size: 34px;
            font-weight: 600;
            letter-spacing: -0.003em;
            background: linear-gradient(135deg, #1d1c1f 0%, #3a3a3e 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 8px;
        }
        
        .header p {
            font-size: 17px;
            color: #6c6c70;
            font-weight: 400;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 16px;
            margin-bottom: 32px;
        }
        
        .stat-card {
            background: #ffffff;
            border-radius: 14px;
            padding: 20px 16px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.25, 0.1, 0.25, 1);
            border: 1px solid #e9e9ef;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
            border-color: #007aff;
        }
        
        .stat-card.active {
            border-color: #007aff;
            background: #f5f9ff;
        }
        
        .stat-number {
            font-size: 40px;
            font-weight: 600;
            color: #1d1c1f;
            letter-spacing: -0.002em;
            margin-bottom: 4px;
        }
        
        .stat-label {
            font-size: 13px;
            font-weight: 500;
            color: #8e8e93;
            text-transform: uppercase;
            letter-spacing: 0.016em;
        }
        
        /* Controls Bar */
        .controls-bar {
            background: #ffffff;
            border-radius: 14px;
            padding: 16px 20px;
            margin-bottom: 24px;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
            border: 1px solid #e9e9ef;
        }
        
        .search-field {
            flex: 1;
            min-width: 240px;
            padding: 10px 16px;
            border: 1px solid #d9d9df;
            border-radius: 10px;
            font-size: 15px;
            font-family: inherit;
            background: #ffffff;
            transition: border-color 0.2s;
            outline: none;
        }
        
        .search-field:focus {
            border-color: #007aff;
            box-shadow: 0 0 0 3px rgba(0, 122, 255, 0.1);
        }
        
        /* Button Styles */
        .btn {
            padding: 10px 20px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 500;
            font-family: inherit;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            background: #f5f5f7;
            color: #1d1c1f;
        }
        
        .btn-primary {
            background: #007aff;
            color: white;
        }
        
        .btn-primary:hover {
            background: #005fc9;
        }
        
        .btn-danger {
            background: #ff3b30;
            color: white;
        }
        
        .btn-danger:hover {
            background: #db2a1f;
        }
        
        .btn-warning {
            background: #ff9500;
            color: white;
        }
        
        .btn-warning:hover {
            background: #e08600;
        }
        
        .btn-secondary {
            background: #f5f5f7;
            color: #007aff;
        }
        
        .btn-secondary:hover {
            background: #e9e9ef;
        }
        
        /* Table Section */
        .table-container {
            background: #ffffff;
            border-radius: 14px;
            border: 1px solid #e9e9ef;
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        
        th {
            text-align: left;
            padding: 16px 12px;
            background: #fbfbfd;
            font-weight: 600;
            color: #6c6c70;
            border-bottom: 1px solid #e9e9ef;
            cursor: pointer;
            user-select: none;
            font-size: 13px;
            letter-spacing: 0.016em;
        }
        
        th:hover {
            background: #f5f5f7;
        }
        
        td {
            padding: 14px 12px;
            border-bottom: 1px solid #f0f0f4;
            color: #1d1c1f;
        }
        
        tr:hover {
            background: #fbfbfd;
        }
        
        /* Status Badges */
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            letter-spacing: 0.01em;
        }
        
        .badge-pending {
            background: #fff9e6;
            color: #b8860b;
        }
        
        .badge-in-progress {
            background: #e6f3ff;
            color: #005fc9;
        }
        
        .badge-awaiting-confirmation {
            background: #e6f9f0;
            color: #1e7b4e;
        }
        
        .badge-completed {
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        .badge-failed {
            background: #ffebee;
            color: #c62828;
        }
        
        code {
            font-family: 'SF Mono', Monaco, 'Cascadia Code', monospace;
            font-size: 12px;
            background: #f5f5f7;
            padding: 2px 6px;
            border-radius: 6px;
        }
        
        .checkbox-col {
            width: 36px;
            text-align: center;
        }
        
        input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: #007aff;
        }
        
        .action-group {
            display: flex;
            gap: 8px;
        }
        
        .icon-btn {
            padding: 6px 12px;
            font-size: 14px;
            border-radius: 8px;
        }
        
        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            padding: 24px;
            background: #ffffff;
            border-top: 1px solid #e9e9ef;
        }
        
        .pagination .btn {
            min-width: 44px;
            padding: 8px 16px;
        }
        
        .pagination .btn.active {
            background: #007aff;
            color: white;
        }
        
        .pagination .btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }
        
        /* Loading Overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(8px);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        
        .loading-spinner {
            width: 48px;
            height: 48px;
            border: 3px solid #e9e9ef;
            border-top-color: #007aff;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Toast Notification */
        .toast {
            position: fixed;
            bottom: 24px;
            right: 24px;
            background: #1d1c1f;
            color: white;
            padding: 12px 20px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 500;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
            animation: slideUp 0.3s cubic-bezier(0.25, 0.1, 0.25, 1);
            z-index: 1100;
        }
        
        .toast-error {
            background: #ff3b30;
        }
        
        @keyframes slideUp {
            from {
                transform: translateY(100%);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #8e8e93;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 20px 16px;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            th, td {
                font-size: 12px;
                padding: 10px 8px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Key Rotation Manager</h1>
            <p>Monitor and manage API key rotation tasks across distributed hosts</p>
        </div>
        
        <div class="stats-grid" id="statsGrid">
            <div class="stat-card" data-status="">
                <div class="stat-number" id="statTotal">—</div>
                <div class="stat-label">Total</div>
            </div>
            <div class="stat-card" data-status="pending">
                <div class="stat-number" id="statPending">—</div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card" data-status="in_progress">
                <div class="stat-number" id="statInProgress">—</div>
                <div class="stat-label">In Progress</div>
            </div>
            <div class="stat-card" data-status="awaiting_confirmation">
                <div class="stat-number" id="statAwaiting">—</div>
                <div class="stat-label">Awaiting</div>
            </div>
            <div class="stat-card" data-status="completed">
                <div class="stat-number" id="statCompleted">—</div>
                <div class="stat-label">Completed</div>
            </div>
            <div class="stat-card" data-status="failed">
                <div class="stat-number" id="statFailed">—</div>
                <div class="stat-label">Failed</div>
            </div>
        </div>
        
        <div class="controls-bar">
            <input type="text" class="search-field" placeholder="Search by SN or error message" id="searchInput" value="<?= htmlspecialchars($searchQuery) ?>">
            <button class="btn btn-secondary" id="deleteSelectedBtn">Delete Selected</button>
            <button class="btn btn-warning" id="retrySelectedBtn">Retry Selected</button>
            <button class="btn btn-danger" id="deleteAllCompletedBtn">Clear Completed</button>
            <button class="btn btn-primary" id="refreshBtn">Refresh</button>
        </div>
        
        <div class="table-container">
            <?php if (isset($error)): ?>
                <div class="empty-state">
                    <p>⚠️ Error: <?= htmlspecialchars($error) ?></p>
                </div>
            <?php elseif (empty($tasks)): ?>
                <div class="empty-state">
                    <p>No tasks available</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th class="checkbox-col"><input type="checkbox" id="selectAll"></th>
                            <th onclick="sortTable('taskId')">ID <?= $sortBy === 'taskId' ? ($sortOrder === 'ASC' ? '↑' : '↓') : '' ?></th>
                            <th onclick="sortTable('targetSn')">Target SN <?= $sortBy === 'targetSn' ? ($sortOrder === 'ASC' ? '↑' : '↓') : '' ?></th>
                            <th onclick="sortTable('status')">Status <?= $sortBy === 'status' ? ($sortOrder === 'ASC' ? '↑' : '↓') : '' ?></th>
                            <th>New Key</th>
                            <th>Old Key</th>
                            <th onclick="sortTable('attempts')">Attempts <?= $sortBy === 'attempts' ? ($sortOrder === 'ASC' ? '↑' : '↓') : '' ?></th>
                            <th onclick="sortTable('maxAttempts')">Max <?= $sortBy === 'maxAttempts' ? ($sortOrder === 'ASC' ? '↑' : '↓') : '' ?></th>
                            <th onclick="sortTable('dateCreate')">Created <?= $sortBy === 'dateCreate' ? ($sortOrder === 'ASC' ? '↑' : '↓') : '' ?></th>
                            <th onclick="sortTable('dateUpdateStatus')">Updated <?= $sortBy === 'dateUpdateStatus' ? ($sortOrder === 'ASC' ? '↑' : '↓') : '' ?></th>
                            <th>Error</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tasks as $task): ?>
                        <tr>
                            <td class="checkbox-col">
                                <input type="checkbox" class="task-checkbox" value="<?= $task['taskId'] ?>">
                            </td>
                            <td><?= $task['taskId'] ?></td>
                            <td><code><?= htmlspecialchars($task['targetSn']) ?></code></td>
                            <td>
                                <span class="badge badge-<?= str_replace('_', '-', $task['status']) ?>">
                                    <?= ucfirst(str_replace('_', ' ', $task['status'])) ?>
                                </span>
                            </td>
                            <td><code><?= substr(htmlspecialchars($task['newApiKey']), 0, 24) ?>…</code></td>
                            <td><code><?= substr(htmlspecialchars($task['oldApiKey']), 0, 24) ?>…</code></td>
                            <td><?= $task['attempts'] ?> / <?= $task['maxAttempts'] ?></td>
                            <td><?= $task['maxAttempts'] ?></td>
                            <td><?= $task['dateCreate'] ?></td>
                            <td><?= $task['dateUpdateStatus'] ?></td>
                            <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                <?= htmlspecialchars($task['lastError'] ?? '—') ?>
                            </td>
                            <td>
                                <div class="action-group">
                                    <?php if ($task['status'] === 'failed' || $task['status'] === 'pending'): ?>
                                    <button class="btn btn-secondary icon-btn retry-single" data-id="<?= $task['taskId'] ?>">↻</button>
                                    <?php endif; ?>
                                    <button class="btn btn-danger icon-btn delete-single" data-id="<?= $task['taskId'] ?>">⌫</button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <button class="btn" onclick="goToPage(<?= $page - 1 ?>)" <?= $page <= 1 ? 'disabled' : '' ?>>← Previous</button>
                    
                    <?php
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    
                    if ($startPage > 1): ?>
                        <button class="btn" onclick="goToPage(1)">1</button>
                        <?php if ($startPage > 2): ?><span style="padding: 8px;">…</span><?php endif; ?>
                    <?php endif; ?>
                    
                    <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                        <button class="btn <?= $i === $page ? 'active' : '' ?>" onclick="goToPage(<?= $i ?>)"><?= $i ?></button>
                    <?php endfor; ?>
                    
                    <?php if ($endPage < $totalPages): ?>
                        <?php if ($endPage < $totalPages - 1): ?><span style="padding: 8px;">…</span><?php endif; ?>
                        <button class="btn" onclick="goToPage(<?= $totalPages ?>)"><?= $totalPages ?></button>
                    <?php endif; ?>
                    
                    <button class="btn" onclick="goToPage(<?= $page + 1 ?>)" <?= $page >= $totalPages ? 'disabled' : '' ?>>Next →</button>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
    </div>
    
    <script>
        let currentStatus = '<?= $statusFilter ?>';
        let currentSearch = '<?= htmlspecialchars($searchQuery, ENT_QUOTES) ?>';
        let currentSortBy = '<?= $sortBy ?>';
        let currentSortOrder = '<?= $sortOrder ?>';
        let currentPage = <?= $page ?>;
        
        function showLoading() {
            document.getElementById('loadingOverlay').style.display = 'flex';
        }
        
        function hideLoading() {
            document.getElementById('loadingOverlay').style.display = 'none';
        }
        
        function showToast(message, isError = false) {
            const toast = document.createElement('div');
            toast.className = 'toast' + (isError ? ' toast-error' : '');
            toast.textContent = message;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
        }
        
        async function loadStats() {
            try {
                const formData = new FormData();
                formData.append('action', 'get_stats');
                const response = await fetch('', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData
                });
                const data = await response.json();
                if (data.success) {
                    const total = Object.values(data.stats).reduce((a, b) => a + b, 0);
                    document.getElementById('statTotal').textContent = total;
                    document.getElementById('statPending').textContent = data.stats.pending || 0;
                    document.getElementById('statInProgress').textContent = data.stats.in_progress || 0;
                    document.getElementById('statAwaiting').textContent = data.stats.awaiting_confirmation || 0;
                    document.getElementById('statCompleted').textContent = data.stats.completed || 0;
                    document.getElementById('statFailed').textContent = data.stats.failed || 0;
                }
            } catch (error) {
                console.error('Failed to load statistics:', error);
            }
        }
        
        async function deleteTasks(taskIds) {
            if (!confirm(`Delete ${taskIds.length} task(s)?`)) return;
            
            showLoading();
            try {
                const formData = new FormData();
                formData.append('action', 'delete_tasks');
                formData.append('task_ids', JSON.stringify(taskIds));
                const response = await fetch('', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData
                });
                const data = await response.json();
                if (data.success) {
                    showToast(`Deleted ${data.deleted} task(s)`);
                    location.reload();
                } else {
                    showToast(data.error, true);
                }
            } catch (error) {
                showToast(error.message, true);
            } finally {
                hideLoading();
            }
        }
        
        async function retryTasks(taskIds) {
            if (!confirm(`Retry ${taskIds.length} task(s)?`)) return;
            
            showLoading();
            try {
                const formData = new FormData();
                formData.append('action', 'retry_tasks');
                formData.append('task_ids', JSON.stringify(taskIds));
                const response = await fetch('', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData
                });
                const data = await response.json();
                if (data.success) {
                    showToast(`Retrying ${data.retried} task(s)`);
                    location.reload();
                } else {
                    showToast(data.error, true);
                }
            } catch (error) {
                showToast(error.message, true);
            } finally {
                hideLoading();
            }
        }
        
        function getSelectedTaskIds() {
            const checkboxes = document.querySelectorAll('.task-checkbox:checked');
            return Array.from(checkboxes).map(cb => parseInt(cb.value));
        }
        
        function sortTable(column) {
            if (currentSortBy === column) {
                currentSortOrder = currentSortOrder === 'ASC' ? 'DESC' : 'ASC';
            } else {
                currentSortBy = column;
                currentSortOrder = 'ASC';
            }
            
            const url = new URL(window.location.href);
            url.searchParams.set('sort_by', currentSortBy);
            url.searchParams.set('sort_order', currentSortOrder);
            window.location.href = url.toString();
        }
        
        function goToPage(page) {
            if (page < 1) return;
            const url = new URL(window.location.href);
            url.searchParams.set('page', page);
            window.location.href = url.toString();
        }
        
        document.addEventListener('DOMContentLoaded', () => {
            loadStats();
            
            document.querySelectorAll('.stat-card').forEach(card => {
                card.addEventListener('click', () => {
                    const status = card.dataset.status;
                    const url = new URL(window.location.href);
                    if (status) {
                        url.searchParams.set('status', status);
                    } else {
                        url.searchParams.delete('status');
                    }
                    url.searchParams.delete('page');
                    window.location.href = url.toString();
                });
            });
            
            const selectAll = document.getElementById('selectAll');
            if (selectAll) {
                selectAll.addEventListener('change', (e) => {
                    document.querySelectorAll('.task-checkbox').forEach(cb => {
                        cb.checked = e.target.checked;
                    });
                });
            }
            
            document.getElementById('deleteSelectedBtn')?.addEventListener('click', () => {
                const ids = getSelectedTaskIds();
                if (ids.length) deleteTasks(ids);
                else showToast('Select tasks first', true);
            });
            
            document.getElementById('retrySelectedBtn')?.addEventListener('click', () => {
                const ids = getSelectedTaskIds();
                if (ids.length) retryTasks(ids);
                else showToast('Select tasks first', true);
            });
            
            document.getElementById('deleteAllCompletedBtn')?.addEventListener('click', async () => {
                if (!confirm('Delete all completed and failed tasks?')) return;
                
                showLoading();
                try {
                    const formData = new FormData();
                    formData.append('action', 'delete_all_completed');
                    const response = await fetch('', {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        body: formData
                    });
                    const data = await response.json();
                    if (data.success) {
                        showToast(`Deleted ${data.deleted} task(s)`);
                        location.reload();
                    } else {
                        showToast(data.error, true);
                    }
                } catch (error) {
                    showToast(error.message, true);
                } finally {
                    hideLoading();
                }
            });
            
            document.getElementById('refreshBtn')?.addEventListener('click', () => {
                location.reload();
            });
            
            const searchInput = document.getElementById('searchInput');
            let searchTimeout;
            searchInput?.addEventListener('input', (e) => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    const url = new URL(window.location.href);
                    if (e.target.value) {
                        url.searchParams.set('search', e.target.value);
                    } else {
                        url.searchParams.delete('search');
                    }
                    url.searchParams.delete('page');
                    window.location.href = url.toString();
                }, 500);
            });
            
            document.querySelectorAll('.retry-single').forEach(btn => {
                btn.addEventListener('click', () => retryTasks([parseInt(btn.dataset.id)]));
            });
            
            document.querySelectorAll('.delete-single').forEach(btn => {
                btn.addEventListener('click', () => deleteTasks([parseInt(btn.dataset.id)]));
            });
        });
    </script>
</body>
</html>