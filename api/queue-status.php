<?php
// api/queue-status.php
require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$branchId = (int)($_GET['branch_id'] ?? 1);
$pdo = db();

// Currently serving (called or in_progress)
$servingStmt = $pdo->prepare(
    "SELECT t.ticket_number, t.status, s.name AS service_name
     FROM tickets t
     JOIN services s ON s.id = t.service_id
     WHERE t.branch_id = ?
       AND t.status IN ('called','in_progress')
       AND DATE(t.created_at) = CURDATE()
     ORDER BY t.called_at DESC
     LIMIT 10"
);
$servingStmt->execute([$branchId]);
$serving = $servingStmt->fetchAll();

// Waiting queue
$waitingStmt = $pdo->prepare(
    "SELECT t.ticket_number, t.status, t.priority, s.name AS service_name, s.color
     FROM tickets t
     JOIN services s ON s.id = t.service_id
     WHERE t.branch_id = ?
       AND t.status = 'waiting'
       AND DATE(t.created_at) = CURDATE()
     ORDER BY t.priority DESC, t.created_at ASC
     LIMIT 30"
);
$waitingStmt->execute([$branchId]);
$waiting = $waitingStmt->fetchAll();

// Stats
$stats = [
    'total_today'     => (int)$pdo->prepare("SELECT COUNT(*) FROM tickets WHERE branch_id=? AND DATE(created_at)=CURDATE()")->execute([$branchId]) ?
                         $pdo->query("SELECT COUNT(*) FROM tickets WHERE branch_id=$branchId AND DATE(created_at)=CURDATE()")->fetchColumn() : 0,
    'completed_today' => (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE branch_id=$branchId AND status='completed' AND DATE(created_at)=CURDATE()")->fetchColumn(),
    'waiting_count'   => count($waiting),
];

echo json_encode([
    'serving' => $serving,
    'waiting' => $waiting,
    'stats'   => $stats,
    'ts'      => time(),
]);
