<?php
// api/v1.php  – REST API for future mobile app
require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Content-Type, X-API-Key');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// ---- Simple API Key auth ----
function apiAuth(): array {
    $key  = $_SERVER['HTTP_X_API_KEY'] ?? '';
    if (!$key) jsonResponse(['error' => 'API key required'], 401);
    $stmt = db()->prepare("SELECT u.* FROM users u WHERE u.remember_token = ? AND u.status = 'active'");
    $stmt->execute([$key]);
    $user = $stmt->fetch();
    if (!$user) jsonResponse(['error' => 'Invalid or expired API key'], 401);
    return $user;
}

$method   = $_SERVER['REQUEST_METHOD'];
$path     = trim($_GET['endpoint'] ?? '', '/');
$segments = explode('/', $path);
$resource = $segments[0] ?? '';
$id       = isset($segments[1]) ? (int)$segments[1] : null;

$pdo = db();

// ---- Route dispatcher ----
switch ("$method:$resource") {

    // POST /api/v1.php?endpoint=auth/login
    case 'POST:auth':
        $sub  = $segments[1] ?? '';
        if ($sub !== 'login') jsonResponse(['error' => 'Not found'], 404);
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $email = $body['email'] ?? '';
        $pass  = $body['password'] ?? '';
        $stmt  = $pdo->prepare("SELECT * FROM users WHERE email=? AND status='active'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($pass, $user['password_hash'])) {
            jsonResponse(['error' => 'Invalid credentials'], 401);
        }
        $token = bin2hex(random_bytes(32));
        $pdo->prepare("UPDATE users SET remember_token=? WHERE id=?")->execute([$token, $user['id']]);
        unset($user['password_hash'], $user['remember_token'], $user['reset_token']);
        jsonResponse(['token' => $token, 'user' => $user]);

    // GET /api/v1.php?endpoint=services
    case 'GET:services':
        $branchId = (int)($_GET['branch_id'] ?? 1);
        $stmt = $pdo->prepare(
            "SELECT s.*, b.name AS branch_name,
                    (SELECT COUNT(*) FROM tickets t WHERE t.service_id=s.id AND t.status='waiting' AND DATE(t.created_at)=CURDATE()) AS waiting_count,
                    " . $branchId . " AS req_branch
             FROM services s JOIN branches b ON b.id=s.branch_id
             WHERE s.status='active' AND s.branch_id=:bid"
        );
        $stmt->execute([':bid' => $branchId]);
        jsonResponse(['data' => $stmt->fetchAll()]);

    // GET /api/v1.php?endpoint=tickets  (user's own)
    case 'GET:tickets':
        $user = apiAuth();
        $stmt = $pdo->prepare(
            "SELECT t.*, s.name AS service_name, s.color
             FROM tickets t JOIN services s ON s.id=t.service_id
             WHERE t.user_id=? ORDER BY t.created_at DESC LIMIT 20"
        );
        $stmt->execute([$user['id']]);
        jsonResponse(['data' => $stmt->fetchAll()]);

    // POST /api/v1.php?endpoint=tickets  (take a ticket)
    case 'POST:tickets':
        $user  = apiAuth();
        $body  = json_decode(file_get_contents('php://input'), true) ?? [];
        $svcId = (int)($body['service_id'] ?? 0);
        $bid   = (int)($user['branch_id'] ?? 1);
        if (!$svcId) jsonResponse(['error' => 'service_id required'], 422);

        // Check service exists
        $svc = $pdo->prepare("SELECT * FROM services WHERE id=? AND status='active'")->execute([$svcId]) ?
               $pdo->prepare("SELECT * FROM services WHERE id=? AND status='active'") : null;
        $svcStmt = $pdo->prepare("SELECT * FROM services WHERE id=? AND status='active' AND branch_id=?");
        $svcStmt->execute([$svcId, $bid]);
        $service = $svcStmt->fetch();
        if (!$service) jsonResponse(['error' => 'Service not found'], 404);

        // Daily limit check
        $limit = (int)$pdo->query("SELECT value FROM settings WHERE `key`='max_tickets_per_user'")->fetchColumn();
        $todayCount = (int)$pdo->prepare(
            "SELECT COUNT(*) FROM tickets WHERE user_id=? AND DATE(created_at)=CURDATE()"
        )->execute([$user['id']]) ? $pdo->query("SELECT COUNT(*) FROM tickets WHERE user_id={$user['id']} AND DATE(created_at)=CURDATE()")->fetchColumn() : 0;
        if ($todayCount >= $limit) jsonResponse(['error' => "Daily limit of $limit tickets reached"], 429);

        $num  = generateTicketNumber($service['prefix'], $bid, $svcId);
        $wait = estimateWait($svcId, $bid);
        $pdo->prepare(
            "INSERT INTO tickets (branch_id,service_id,user_id,ticket_number,status,priority,estimated_wait_minutes)
             VALUES (?,?,?,?,?,?,?)"
        )->execute([$bid, $svcId, $user['id'], $num, 'waiting', 'normal', $wait]);
        $tid = (int)$pdo->lastInsertId();

        $ticket = $pdo->query("SELECT t.*,s.name AS service_name FROM tickets t JOIN services s ON s.id=t.service_id WHERE t.id=$tid")->fetch();
        jsonResponse(['data' => $ticket, 'estimated_wait_minutes' => $wait], 201);

    // GET /api/v1.php?endpoint=appointments
    case 'GET:appointments':
        $user = apiAuth();
        $stmt = $pdo->prepare(
            "SELECT a.*, s.name AS service_name FROM appointments a
             JOIN services s ON s.id=a.service_id
             WHERE a.user_id=? ORDER BY a.appointment_date, a.appointment_time"
        );
        $stmt->execute([$user['id']]);
        jsonResponse(['data' => $stmt->fetchAll()]);

    // POST /api/v1.php?endpoint=appointments
    case 'POST:appointments':
        $user = apiAuth();
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $fields = [
            'branch_id'        => (int)($body['branch_id']        ?? $user['branch_id']),
            'service_id'       => (int)($body['service_id']       ?? 0),
            'appointment_date' => $body['appointment_date']        ?? '',
            'appointment_time' => $body['appointment_time']        ?? '',
            'notes'            => $body['notes']                   ?? '',
        ];
        if (!$fields['service_id'] || !$fields['appointment_date'] || !$fields['appointment_time']) {
            jsonResponse(['error' => 'service_id, appointment_date, and appointment_time are required'], 422);
        }
        $pdo->prepare(
            "INSERT INTO appointments (branch_id,service_id,user_id,appointment_date,appointment_time,notes,status)
             VALUES (:branch_id,:service_id,:uid,:appointment_date,:appointment_time,:notes,'pending')"
        )->execute(array_merge($fields, [':uid' => $user['id']]));
        $aid = (int)$pdo->lastInsertId();
        $appt = $pdo->query("SELECT a.*,s.name AS service_name FROM appointments a JOIN services s ON s.id=a.service_id WHERE a.id=$aid")->fetch();
        jsonResponse(['data' => $appt], 201);

    // GET /api/v1.php?endpoint=branches
    case 'GET:branches':
        $rows = $pdo->query("SELECT id,name,address,phone,email,city,country FROM branches WHERE status='active'")->fetchAll();
        jsonResponse(['data' => $rows]);

    // GET /api/v1.php?endpoint=notifications
    case 'GET:notifications':
        $user  = apiAuth();
        $limit = min(50, (int)($_GET['limit'] ?? 20));
        $stmt  = $pdo->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT $limit");
        $stmt->execute([$user['id']]);
        jsonResponse(['data' => $stmt->fetchAll()]);

    // PUT /api/v1.php?endpoint=notifications/read
    case 'PUT:notifications':
        $user = apiAuth();
        $pdo->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$user['id']]);
        jsonResponse(['message' => 'All notifications marked as read']);

    default:
        jsonResponse(['error' => 'Endpoint not found', 'path' => $path], 404);
}
