<?php
// customer/take-ticket.php
require_once __DIR__ . '/../bootstrap.php';
requireRole(ROLE_CUSTOMER);

$pdo       = db();
$pageTitle = 'Take a Ticket';
$uid       = $_SESSION['user_id'];
$branchId  = (int)($_SESSION['branch_id'] ?? 1);

$newTicket = null;
$errors    = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $svcId = (int)($_POST['service_id'] ?? 0);

    if (!$svcId) {
        $errors[] = 'Please select a service.';
    } else {
        // Check service exists and is active
        $svcStmt = $pdo->prepare("SELECT * FROM services WHERE id=? AND branch_id=? AND status='active'");
        $svcStmt->execute([$svcId, $branchId]);
        $service = $svcStmt->fetch();

        if (!$service) {
            $errors[] = 'Service not available.';
        } else {
            // Daily limit
            $maxPerDay  = (int)$pdo->query("SELECT value FROM settings WHERE `key`='max_tickets_per_user'")->fetchColumn();
            $todayCount = (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE user_id=$uid AND DATE(created_at)=CURDATE()")->fetchColumn();

            if ($todayCount >= $maxPerDay) {
                $errors[] = "You have reached your daily limit of $maxPerDay tickets.";
            } else {
                // Check capacity
                $waiting = (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE service_id=$svcId AND branch_id=$branchId AND status='waiting' AND DATE(created_at)=CURDATE()")->fetchColumn();
                if ($waiting >= $service['max_capacity']) {
                    $errors[] = 'This service has reached maximum capacity for today.';
                } else {
                    $ticketNum = generateTicketNumber($service['prefix'], $branchId, $svcId);
                    $waitMins  = estimateWait($svcId, $branchId);
                    $qrUrl     = qrCodeUrl($ticketNum);

                    $pdo->prepare(
                        "INSERT INTO tickets (branch_id,service_id,user_id,ticket_number,status,estimated_wait_minutes,qr_code)
                         VALUES (?,?,?,?,'waiting',?,?)"
                    )->execute([$branchId, $svcId, $uid, $ticketNum, $waitMins, $qrUrl]);

                    $tid       = (int)$pdo->lastInsertId();
                    $newTicket = array_merge($service, [
                        'id'            => $tid,
                        'ticket_number' => $ticketNum,
                        'wait_minutes'  => $waitMins,
                        'qr_url'        => $qrUrl,
                        'waiting_count' => $waiting,
                    ]);

                    notify($uid, 'ticket_issued', 'Ticket Issued',
                        "Your ticket {$ticketNum} for {$service['name']} has been issued. Estimated wait: {$waitMins} minutes.");

                    logActivity('ticket_take', "Took ticket $ticketNum for service {$service['name']}");
                }
            }
        }
    }
}

// Load services for this branch
$services = $pdo->prepare(
    "SELECT s.*,
            (SELECT COUNT(*) FROM tickets t WHERE t.service_id=s.id AND t.branch_id=:bid AND t.status='waiting' AND DATE(t.created_at)=CURDATE()) AS waiting_count,
            (SELECT COUNT(*) FROM tickets t WHERE t.service_id=s.id AND t.branch_id=:bid2 AND t.status IN ('called','in_progress') AND DATE(t.created_at)=CURDATE()) AS serving_count
     FROM services s
     WHERE s.branch_id=:bid3 AND s.status='active'
     ORDER BY s.name"
);
$services->execute([':bid' => $branchId, ':bid2' => $branchId, ':bid3' => $branchId]);
$allServices = $services->fetchAll();

require_once __DIR__ . '/../views/partials/header.php';
?>

<!-- Ticket Issued Success -->
<?php if ($newTicket): ?>
<div style="max-width:460px;margin:0 auto 2rem">
  <div style="background:linear-gradient(135deg,var(--brand) 0%,#0d47a1 100%);
              border-radius:20px;padding:2.5rem;text-align:center;color:#fff;position:relative;overflow:hidden">
    <div style="position:absolute;inset:0;background:radial-gradient(circle at 70% 30%, rgba(0,201,167,.2) 0%, transparent 60%)"></div>
    <div style="position:relative;z-index:1">
      <div style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.15em;
                  color:rgba(255,255,255,.6);margin-bottom:.5rem">Your Ticket Number</div>
      <div style="font-family:'Bebas Neue',sans-serif;font-size:6rem;line-height:1;color:#fff;
                  text-shadow:0 4px 20px rgba(0,0,0,.3)">
        <?= e($newTicket['ticket_number']) ?>
      </div>
      <div style="font-size:1rem;font-weight:600;margin:.5rem 0"><?= e($newTicket['name']) ?></div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin:1.5rem 0">
        <div style="background:rgba(255,255,255,.1);border-radius:12px;padding:.85rem">
          <div style="font-size:1.3rem;font-weight:800"><?= $newTicket['waiting_count'] ?></div>
          <div style="font-size:.72rem;opacity:.7">People Ahead</div>
        </div>
        <div style="background:rgba(255,255,255,.1);border-radius:12px;padding:.85rem">
          <div style="font-size:1.3rem;font-weight:800">~<?= $newTicket['wait_minutes'] ?> min</div>
          <div style="font-size:.72rem;opacity:.7">Est. Wait</div>
        </div>
      </div>

      <img src="<?= e($newTicket['qr_url']) ?>" alt="QR" width="130" height="130"
           style="border-radius:12px;margin-bottom:1rem;background:#fff;padding:4px">

      <div style="font-size:.78rem;opacity:.7;margin-bottom:1.5rem">Scan QR at the counter</div>

      <a href="dashboard.php" style="display:inline-flex;align-items:center;gap:.5rem;
                                      background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.3);
                                      border-radius:10px;padding:.65rem 1.25rem;color:#fff;
                                      text-decoration:none;font-weight:600;font-size:.9rem">
        <i class="bi bi-house"></i> Back to Dashboard
      </a>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Error messages -->
<?php if ($errors): ?>
<div class="flash-alert flash-error" style="margin-bottom:1.5rem">
  <i class="bi bi-exclamation-circle"></i> <?= e($errors[0]) ?>
</div>
<?php endif; ?>

<!-- Service selection -->
<?php if (!$newTicket): ?>
<div style="margin-bottom:1.5rem">
  <h1 style="font-size:1.4rem;font-weight:800;margin-bottom:.25rem">Take a Queue Ticket</h1>
  <p style="color:var(--muted);font-size:.9rem">Select a service to join its queue</p>
</div>

<form method="POST" id="ticketForm">
  <?= csrfField() ?>
  <input type="hidden" name="service_id" id="selectedService">

  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:1.25rem;margin-bottom:2rem">
    <?php foreach ($allServices as $svc): ?>
    <div class="service-card"
         onclick="selectService(<?= $svc['id'] ?>, this)"
         style="background:var(--surface);border:2px solid var(--border);border-radius:var(--radius);
                padding:1.5rem;cursor:pointer;transition:all .2s;position:relative;overflow:hidden">

      <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1rem">
        <div style="width:48px;height:48px;border-radius:12px;background:<?= e($svc['color']) ?>22;
                    display:grid;place-items:center;font-size:1.4rem;color:<?= e($svc['color']) ?>">
          <i class="bi <?= e($svc['icon']) ?>"></i>
        </div>
        <div>
          <div style="font-weight:700;font-size:.95rem"><?= e($svc['name']) ?></div>
          <div style="font-size:.75rem;color:var(--muted)">Prefix: <?= e($svc['prefix']) ?>###</div>
        </div>
      </div>

      <p style="font-size:.8rem;color:var(--muted);margin-bottom:1rem;line-height:1.6;min-height:36px">
        <?= e($svc['description'] ?? '') ?>
      </p>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:.5rem">
        <div style="text-align:center;background:var(--bg);border-radius:8px;padding:.5rem">
          <div style="font-size:1.2rem;font-weight:800;color:var(--warning)"><?= $svc['waiting_count'] ?></div>
          <div style="font-size:.68rem;color:var(--muted)">Waiting</div>
        </div>
        <div style="text-align:center;background:var(--bg);border-radius:8px;padding:.5rem">
          <div style="font-size:1.2rem;font-weight:800">~<?= $svc['avg_duration_minutes'] * max(1,$svc['waiting_count']) ?>m</div>
          <div style="font-size:.68rem;color:var(--muted)">Est. Wait</div>
        </div>
      </div>

      <!-- Selected checkmark -->
      <div class="selected-mark" style="display:none;position:absolute;top:.75rem;right:.75rem;
                                         width:24px;height:24px;background:var(--accent);border-radius:50%;
                                         display:none;align-items:center;justify-content:center;color:#000;font-size:.8rem">
        <i class="bi bi-check"></i>
      </div>
    </div>
    <?php endforeach; ?>

    <?php if (empty($allServices)): ?>
    <div style="grid-column:1/-1;text-align:center;padding:4rem;color:var(--muted)">
      <i class="bi bi-exclamation-circle" style="font-size:3rem;display:block;margin-bottom:.75rem;opacity:.4"></i>
      No services available at your branch.
    </div>
    <?php endif; ?>
  </div>

  <div style="max-width:400px;margin:0 auto;text-align:center" id="submitArea" style="display:none">
    <div id="selectedInfo" style="margin-bottom:1rem;padding:1rem;background:var(--surface);
                                   border-radius:12px;border:1px solid var(--border);display:none">
      <strong>Selected:</strong> <span id="selectedName"></span>
    </div>
    <button type="submit" id="submitBtn" class="btn-brand" style="width:100%;padding:1rem;font-size:1rem;display:none">
      <i class="bi bi-ticket-perforated-fill"></i> Take This Ticket
    </button>
  </div>
</form>
<?php endif; ?>

<?php
$extraJs = '
<script>
function selectService(id, el) {
  // Clear all selections
  document.querySelectorAll(".service-card").forEach(c => {
    c.style.borderColor = "var(--border)";
    c.style.background = "var(--surface)";
    c.querySelector(".selected-mark").style.display = "none";
  });

  // Highlight selected
  el.style.borderColor = "var(--brand)";
  el.style.background  = "rgba(27,79,216,.04)";
  el.querySelector(".selected-mark").style.display = "flex";

  const name = el.querySelector("[style*=\"font-weight:700\"]").textContent;
  document.getElementById("selectedService").value = id;
  document.getElementById("selectedName").textContent  = name;
  document.getElementById("selectedInfo").style.display = "block";
  document.getElementById("submitBtn").style.display    = "inline-flex";
}
</script>';
require_once __DIR__ . '/../views/partials/footer.php';
?>
