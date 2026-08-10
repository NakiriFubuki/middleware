<?php
/**
 * Activity Logging Functions
 */

function logActivity(?int $userId, string $action, ?string $description = null): void
{
    try {
        $stmt = db()->prepare(
            'INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent)
             VALUES (?, ?, ?, ?, ?)'
        );
        $ip = getClientIp();

        $stmt->execute([
            $userId,
            $action,
            $description,
            $ip !== '' ? $ip : null,
            getUserAgent()
        ]);
    } catch (Exception $e) {
        error_log('Activity log failed: ' . $e->getMessage());
    }
}

function getActivityLogs(int $page = 1, int $perPage = 20, ?string $action = null, ?int $userId = null): array
{
    $pdo = db();
    $where = [];
    $params = [];

    if ($action) {
        $where[] = 'al.action = ?';
        $params[] = $action;
    }
    if ($userId) {
        $where[] = 'al.user_id = ?';
        $params[] = $userId;
    }

    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM activity_logs al $whereClause");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $pagination = paginate($total, $page, $perPage);

    $stmt = $pdo->prepare(
        "SELECT al.*, u.full_name, u.username, u.role
         FROM activity_logs al
         LEFT JOIN users u ON u.id = al.user_id
         $whereClause
         ORDER BY al.created_at DESC
         LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}"
    );
    $stmt->execute($params);

    return [
        'logs' => $stmt->fetchAll(),
        'pagination' => $pagination
    ];
}

function getRiderActivityLogs(int $riderUserId, int $limit = 50): array
{
    $stmt = db()->prepare(
        'SELECT * FROM activity_logs
         WHERE user_id = ?
         ORDER BY created_at DESC
         LIMIT ?'
    );
    $stmt->execute([$riderUserId, $limit]);
    return $stmt->fetchAll();
}

/**
 * Record a page visit with client IP (throttled per URI per session).
 */
function logPageAccessIfNeeded(): void
{
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        return;
    }

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
        return;
    }

    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    if (strpos($script, '/api/') !== false) {
        return;
    }

    $uri = $_SERVER['REQUEST_URI'] ?? $script;
    if (strlen($uri) > 500) {
        $uri = substr($uri, 0, 500);
    }

    $throttleKey = 'page_access_' . md5($uri);
    $now = time();

    if (!empty($_SESSION[$throttleKey]) && ($now - (int) $_SESSION[$throttleKey]) < 900) {
        return;
    }

    $_SESSION[$throttleKey] = $now;
    logActivity(currentUserId(), 'page_access', $uri);
}
