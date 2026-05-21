<?php
// api/ticket-status.php  – called by customer dashboard polling
require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json');

$uid = (int)($_GET['uid'] ?? 0);
if (!$uid) jsonResponse([]);

$pdo  = db();
$stmt = $pdo->prepare(
    "SELECT t.id, t.ticket_number, t.status,
            (SELECT COUNT(*) FROM tickets t2
             WHERE t2.service_id=t.service_id AND t2.branch_id=t.branch_id
               AND t2.status='waiting' AND t2.id < t.id AND DATE(t2.created_at)=CURDATE()) AS ahead
     FROM tickets t
     WHERE t.user_id=? AND DATE(t.created_at)=CURDATE()
       AND t.status IN ('waiting','called','in_progress')"
);
$stmt->execute([$uid]);
$tickets = $stmt->fetchAll();

// Check if any need notifications
$notifyAt = (int)$pdo->query("SELECT value FROM settings WHERE `key`='notify_at_position'")->fetchColumn();
foreach ($tickets as $t) {
    if ((int)$t['ahead'] <= $notifyAt && $t['status'] === 'waiting') {
        // Could trigger browser push here; for now just include in response
    }
}

echo json_encode($tickets);
