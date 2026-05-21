<?php
// helpers/functions.php

/**
 * Sanitize output to prevent XSS
 */
function e(mixed $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Redirect to a URL
 */
function redirect(string $url): never {
    header('Location: ' . $url);
    exit;
}

/**
 * Flash message into session
 */
function flash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Get and clear flash message
 */
function getFlash(): ?array {
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * JSON response helper (for AJAX / API)
 */
function jsonResponse(mixed $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Paginate a query
 */
function paginate(string $query, array $params, int $page = 1, int $perPage = PER_PAGE): array {
    $pdo = db();

    // Count total
    $countQuery = preg_replace('/SELECT .* FROM /is', 'SELECT COUNT(*) as total FROM ', $query, 1);
    $countQuery = preg_replace('/ORDER BY .*/is', '', $countQuery);
    $countStmt  = $pdo->prepare($countQuery);
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $offset    = ($page - 1) * $perPage;
    $lastPage  = (int)ceil($total / $perPage);

    $stmt = $pdo->prepare($query . " LIMIT :limit OFFSET :offset");
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
    $stmt->execute();

    return [
        'data'      => $stmt->fetchAll(),
        'total'     => $total,
        'per_page'  => $perPage,
        'page'      => $page,
        'last_page' => $lastPage,
    ];
}

/**
 * Generate a unique ticket number like A001, B023
 */
function generateTicketNumber(string $prefix, int $branchId, int $serviceId): string {
    $pdo   = db();
    $today = date('Y-m-d');
    $stmt  = $pdo->prepare(
        "SELECT COUNT(*) FROM tickets
         WHERE branch_id = ? AND service_id = ? AND DATE(created_at) = ?"
    );
    $stmt->execute([$branchId, $serviceId, $today]);
    $count = (int)$stmt->fetchColumn() + 1;
    return strtoupper($prefix) . str_pad($count, 3, '0', STR_PAD_LEFT);
}

/**
 * Calculate estimated wait time in minutes
 */
function estimateWait(int $serviceId, int $branchId): int {
    $pdo  = db();
    $stmt = $pdo->prepare(
        "SELECT s.avg_duration_minutes,
                COUNT(t.id) AS waiting_count
         FROM services s
         LEFT JOIN tickets t ON t.service_id = s.id
           AND t.branch_id = :bid
           AND t.status IN ('waiting','in_progress')
           AND DATE(t.created_at) = CURDATE()
         WHERE s.id = :sid
         GROUP BY s.id"
    );
    $stmt->execute([':sid' => $serviceId, ':bid' => $branchId]);
    $row = $stmt->fetch();
    if (!$row) return 0;
    return (int)$row['avg_duration_minutes'] * max(0, (int)$row['waiting_count']);
}

/**
 * Log an activity
 */
function logActivity(string $action, string $description = '', ?int $userId = null): void {
    $pdo = db();
    $uid = $userId ?? ($_SESSION['user_id'] ?? null);
    $ip  = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $ua  = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $pdo->prepare(
        "INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent)
         VALUES (?, ?, ?, ?, ?)"
    )->execute([$uid, $action, $description, $ip, $ua]);
}

/**
 * Format datetime nicely
 */
function timeAgo(string $datetime): string {
    $now  = new DateTime();
    $then = new DateTime($datetime);
    $diff = $now->diff($then);
    if ($diff->y > 0) return $diff->y . 'y ago';
    if ($diff->m > 0) return $diff->m . 'mo ago';
    if ($diff->d > 0) return $diff->d . 'd ago';
    if ($diff->h > 0) return $diff->h . 'h ago';
    if ($diff->i > 0) return $diff->i . 'min ago';
    return 'just now';
}

/**
 * Get status badge HTML
 */
function statusBadge(string $status): string {
    $map = [
        'waiting'     => ['bg-warning text-dark', 'Waiting'],
        'called'      => ['bg-info text-dark',    'Called'],
        'in_progress' => ['bg-primary',           'In Progress'],
        'completed'   => ['bg-success',           'Completed'],
        'cancelled'   => ['bg-danger',            'Cancelled'],
        'no_show'     => ['bg-secondary',         'No Show'],
        'pending'     => ['bg-warning text-dark', 'Pending'],
        'confirmed'   => ['bg-success',           'Confirmed'],
        'rescheduled' => ['bg-info text-dark',    'Rescheduled'],
        'active'      => ['bg-success',           'Active'],
        'inactive'    => ['bg-secondary',         'Inactive'],
        'blocked'     => ['bg-danger',            'Blocked'],
    ];
    [$cls, $label] = $map[$status] ?? ['bg-secondary', ucfirst($status)];
    return '<span class="badge ' . $cls . '">' . $label . '</span>';
}

/**
 * Send in-app notification
 */
function notify(int $userId, string $type, string $title, string $message, ?array $data = null): void {
    db()->prepare(
        "INSERT INTO notifications (user_id, type, title, message, data) VALUES (?,?,?,?,?)"
    )->execute([$userId, $type, $title, $message, $data ? json_encode($data) : null]);
}

/**
 * Get unread notification count
 */
function unreadNotificationsCount(?int $userId = null): int {
    $uid = $userId ?? ($_SESSION['user_id'] ?? 0);
    if (!$uid) return 0;
    $stmt = db()->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
    $stmt->execute([$uid]);
    return (int)$stmt->fetchColumn();
}

/**
 * Simple QR code URL generator (using Google Charts as fallback)
 */
function qrCodeUrl(string $data): string {
    return 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($data);
}
