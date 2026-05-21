<?php
// customer/dashboard.php
require_once __DIR__ . '/../bootstrap.php';
requireRole(ROLE_CUSTOMER);

$pdo       = db();
$pageTitle = 'My Dashboard';
$uid       = $_SESSION['user_id'];

// Today's tickets
$myTickets = $pdo->prepare(
    "SELECT t.*, s.name AS service_name, s.color, s.icon,
            (SELECT COUNT(*) FROM tickets t2
             WHERE t2.service_id=t.service_id AND t2.branch_id=t.branch_id
               AND t2.status='waiting' AND t2.id < t.id AND DATE(t2.created_at)=CURDATE()) AS ahead
     FROM tickets t
     JOIN services s ON s.id=t.service_id
     WHERE t.user_id=? AND DATE(t.created_at)=CURDATE()
     ORDER BY t.created_at DESC"
);
$myTickets->execute([$uid]);
$tickets = $myTickets->fetchAll();

// Upcoming appointments
$appointments = $pdo->prepare(
    "SELECT a.*, s.name AS service_name, s.color, s.icon
     FROM appointments a
     JOIN services s ON s.id=a.service_id
     WHERE a.user_id=? AND a.appointment_date >= CURDATE()
       AND a.status NOT IN ('cancelled','completed')
     ORDER BY a.appointment_date, a.appointment_time
     LIMIT 5"
);
$appointments->execute([$uid]);
$upcomingAppts = $appointments->fetchAll();

// Recent history
$history = $pdo->prepare(
    "SELECT t.*, s.name AS service_name, s.color
     FROM tickets t JOIN services s ON s.id=t.service_id
     WHERE t.user_id=? ORDER BY t.created_at DESC LIMIT 10"
);
$history->execute([$uid]);
$recentHistory = $history->fetchAll();

// Stats
$totalTickets     = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE user_id=?")->execute([$uid]) ?
                    (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE user_id=$uid")->fetchColumn() : 0;
$completedTickets = (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE user_id=$uid AND status='completed'")->fetchColumn();
$totalAppts       = (int)$pdo->query("SELECT COUNT(*) FROM appointments WHERE user_id=$uid")->fetchColumn();

require_once __DIR__ . '/../views/partials/header.php';
?>

<!-- Greeting -->
<div style="margin-bottom:2rem">
  <h1 style="font-size:1.6rem;font-weight:800;margin-bottom:.3rem">
    Good <?= date('H') < 12 ? 'morning' : (date('H') < 18 ? 'afternoon' : 'evening') ?>,
    <?= e(explode(' ', $_SESSION['full_name'])[0]) ?> 👋
  </h1>
  <p style="color:var(--muted);font-size:.9rem"><?= date('l, d F Y') ?> • <?= e($_SESSION['full_name']) ?></p>
</div>

<!-- Quick Stats -->
<div class="stat-grid" style="margin-bottom:2rem">
  <div class="stat-card">
    <div class="stat-icon" style="background:#eff6ff;color:var(--brand)"><i class="bi bi-ticket-detailed-fill"></i></div>
    <div class="stat-text">
      <div class="value"><?= $totalTickets ?></div>
      <div class="label">Total Tickets</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:#f0fdf4;color:#16a34a"><i class="bi bi-check-circle-fill"></i></div>
    <div class="stat-text">
      <div class="value"><?= $completedTickets ?></div>
      <div class="label">Completed</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:#fefce8;color:#ca8a04"><i class="bi bi-calendar-check-fill"></i></div>
    <div class="stat-text">
      <div class="value"><?= $totalAppts ?></div>
      <div class="label">Appointments</div>
    </div>
  </div>
</div>

<!-- Quick Actions -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:1rem;margin-bottom:2rem">
  <a href="take-ticket.php" style="text-decoration:none">
    <div style="background:var(--brand);border-radius:var(--radius);padding:1.5rem;
                display:flex;flex-direction:column;gap:.75rem;transition:transform .15s;cursor:pointer"
         onmouseenter="this.style.transform='scale(1.02)'" onmouseleave="this.style.transform='scale(1)'">
      <i class="bi bi-ticket-perforated-fill" style="font-size:2rem;color:rgba(255,255,255,.8)"></i>
      <div>
        <div style="color:#fff;font-weight:700;font-size:1rem">Take a Ticket</div>
        <div style="color:rgba(255,255,255,.6);font-size:.8rem">Join a service queue</div>
      </div>
    </div>
  </a>
  <a href="appointments.php" style="text-decoration:none">
    <div style="background:#111827;border:1px solid var(--border);border-radius:var(--radius);padding:1.5rem;
                display:flex;flex-direction:column;gap:.75rem;transition:transform .15s;cursor:pointer"
         onmouseenter="this.style.transform='scale(1.02)'" onmouseleave="this.style.transform='scale(1)'">
      <i class="bi bi-calendar-plus-fill" style="font-size:2rem;color:var(--accent)"></i>
      <div>
        <div style="font-weight:700;font-size:1rem">Book Appointment</div>
        <div style="color:var(--muted);font-size:.8rem">Schedule for later</div>
      </div>
    </div>
  </a>
  <a href="my-tickets.php" style="text-decoration:none">
    <div style="background:#111827;border:1px solid var(--border);border-radius:var(--radius);padding:1.5rem;
                display:flex;flex-direction:column;gap:.75rem;transition:transform .15s;cursor:pointer"
         onmouseenter="this.style.transform='scale(1.02)'" onmouseleave="this.style.transform='scale(1)'">
      <i class="bi bi-collection-fill" style="font-size:2rem;color:#f97316"></i>
      <div>
        <div style="font-weight:700;font-size:1rem">My Tickets</div>
        <div style="color:var(--muted);font-size:.8rem">View ticket history</div>
      </div>
    </div>
  </a>
</div>

<div class="row g-4">
  <!-- Today's Tickets -->
  <div class="col-lg-7">
    <div class="panel">
      <div class="panel-header">
        <span class="panel-title"><i class="bi bi-ticket-detailed" style="color:var(--brand)"></i> Today's Tickets</span>
        <a href="take-ticket.php" class="btn-brand" style="font-size:.8rem;padding:.35rem .8rem">
          <i class="bi bi-plus-lg"></i> New
        </a>
      </div>
      <?php if (empty($tickets)): ?>
      <div style="text-align:center;padding:3rem;color:var(--muted)">
        <i class="bi bi-inbox" style="font-size:3rem;display:block;margin-bottom:.75rem;opacity:.4"></i>
        No tickets yet today.<br>
        <a href="take-ticket.php" style="color:var(--brand);font-weight:600;text-decoration:none">Take a ticket →</a>
      </div>
      <?php else: ?>
      <div style="padding:1rem;display:flex;flex-direction:column;gap:.75rem" id="ticketList">
        <?php foreach ($tickets as $t): ?>
        <div class="ticket-row" id="ticket-<?= $t['id'] ?>"
             style="display:flex;align-items:center;gap:1rem;background:var(--bg);
                    border-radius:12px;padding:1rem;border:1px solid var(--border)">
          <div style="width:50px;height:50px;border-radius:12px;background:<?= e($t['color']) ?>22;
                      display:grid;place-items:center;font-size:1.3rem;color:<?= e($t['color']) ?>;flex-shrink:0">
            <i class="bi <?= e($t['icon']) ?>"></i>
          </div>
          <div style="flex:1">
            <div style="font-weight:800;font-size:1.2rem;color:var(--brand)"><?= e($t['ticket_number']) ?></div>
            <div style="font-size:.82rem;color:var(--muted)"><?= e($t['service_name']) ?></div>
          </div>
          <div style="text-align:right">
            <div id="status-txt-<?= $t['id'] ?>"><?= statusBadge($t['status']) ?></div>
            <?php if ($t['status'] === 'waiting'): ?>
            <div style="font-size:.75rem;color:var(--muted);margin-top:.25rem">
              <span id="ahead-<?= $t['id'] ?>"><?= $t['ahead'] ?></span> ahead
            </div>
            <?php endif; ?>
          </div>
          <?php if (in_array($t['status'], ['waiting','called'])): ?>
          <div>
            <img src="<?= qrCodeUrl($t['ticket_number']) ?>" alt="QR" width="50" height="50"
                 style="border-radius:8px;cursor:pointer"
                 onclick="showQr('<?= e($t['ticket_number']) ?>','<?= qrCodeUrl($t['ticket_number']) ?>')">
          </div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Upcoming Appointments -->
  <div class="col-lg-5">
    <div class="panel">
      <div class="panel-header">
        <span class="panel-title"><i class="bi bi-calendar-event" style="color:var(--accent)"></i> Upcoming</span>
        <a href="appointments.php" class="btn-ghost" style="font-size:.8rem;padding:.35rem .8rem">All</a>
      </div>
      <?php if (empty($upcomingAppts)): ?>
      <div style="text-align:center;padding:3rem;color:var(--muted)">
        <i class="bi bi-calendar-x" style="font-size:3rem;display:block;margin-bottom:.75rem;opacity:.4"></i>
        No upcoming appointments.<br>
        <a href="appointments.php" style="color:var(--accent);font-weight:600;text-decoration:none">Book one →</a>
      </div>
      <?php else: ?>
      <div style="padding:1rem;display:flex;flex-direction:column;gap:.75rem">
        <?php foreach ($upcomingAppts as $a): ?>
        <div style="display:flex;align-items:center;gap:1rem;padding:.85rem 1rem;
                    background:var(--bg);border-radius:12px;border:1px solid var(--border)">
          <div style="width:46px;text-align:center;flex-shrink:0">
            <div style="font-size:1.3rem;font-weight:800;color:var(--brand);line-height:1">
              <?= date('d', strtotime($a['appointment_date'])) ?>
            </div>
            <div style="font-size:.7rem;color:var(--muted);text-transform:uppercase">
              <?= date('M', strtotime($a['appointment_date'])) ?>
            </div>
          </div>
          <div style="flex:1">
            <div style="font-weight:700;font-size:.9rem"><?= e($a['service_name']) ?></div>
            <div style="font-size:.78rem;color:var(--muted)">
              <i class="bi bi-clock"></i> <?= date('H:i', strtotime($a['appointment_time'])) ?>
            </div>
          </div>
          <?= statusBadge($a['status']) ?>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- QR Modal -->
<div class="modal fade" id="qrModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content" style="text-align:center">
      <div class="modal-header"><h5 class="modal-title fw-bold">Your Ticket QR</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div id="qrNum" style="font-family:'Bebas Neue',sans-serif;font-size:3.5rem;color:var(--brand);line-height:1"></div>
        <img id="qrImg" src="" alt="QR" style="width:180px;height:180px;border-radius:12px;margin:1rem auto;display:block">
        <p style="font-size:.82rem;color:var(--muted)">Show this at the counter</p>
      </div>
    </div>
  </div>
</div>

<?php
$extraJs = '
<script>
function showQr(num, url) {
  document.getElementById("qrNum").textContent = num;
  document.getElementById("qrImg").src = url;
  new bootstrap.Modal(document.getElementById("qrModal")).show();
}

// Live polling for ticket status updates
async function pollTickets() {
  try {
    const res  = await fetch("' . APP_URL . '/api/ticket-status.php?uid=' . $uid . '");
    const data = await res.json();
    data.forEach(t => {
      const aheadEl = document.getElementById("ahead-" + t.id);
      if (aheadEl) aheadEl.textContent = t.ahead;
      const stEl = document.getElementById("status-txt-" + t.id);
      // Only reload if status changed (simple approach)
    });
  } catch(e) {}
}
setInterval(pollTickets, 10000);
</script>';
require_once __DIR__ . '/../views/partials/footer.php';
?>
