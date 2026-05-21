<?php
// staff/dashboard.php
require_once __DIR__ . '/../bootstrap.php';
requireRole(ROLE_STAFF);

$pdo       = db();
$pageTitle = 'Staff Dashboard';
$bid       = (int)($_SESSION['branch_id'] ?? 1);

$waiting   = (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE branch_id=$bid AND status='waiting' AND DATE(created_at)=CURDATE()")->fetchColumn();
$serving   = (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE branch_id=$bid AND status IN ('called','in_progress') AND DATE(created_at)=CURDATE()")->fetchColumn();
$completed = (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE branch_id=$bid AND status='completed' AND DATE(created_at)=CURDATE()")->fetchColumn();
$apptToday = (int)$pdo->query("SELECT COUNT(*) FROM appointments WHERE branch_id=$bid AND appointment_date=CURDATE() AND status NOT IN ('cancelled')")->fetchColumn();

$myServed  = (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE served_by={$_SESSION['user_id']} AND DATE(created_at)=CURDATE()")->fetchColumn();

// Services with waiting counts
$services = $pdo->prepare(
    "SELECT s.*,
            SUM(CASE WHEN t.status='waiting' THEN 1 ELSE 0 END) AS waiting_count,
            SUM(CASE WHEN t.status IN ('called','in_progress') THEN 1 ELSE 0 END) AS serving_count
     FROM services s
     LEFT JOIN tickets t ON t.service_id=s.id AND t.branch_id=:bid AND DATE(t.created_at)=CURDATE()
     WHERE s.branch_id=:bid2 AND s.status='active'
     GROUP BY s.id ORDER BY waiting_count DESC"
);
$services->execute([':bid' => $bid, ':bid2' => $bid]);
$allServices = $services->fetchAll();

// Today's appointments
$appointments = $pdo->prepare(
    "SELECT a.*, s.name AS service_name, u.full_name AS customer_name, u.phone AS customer_phone
     FROM appointments a
     JOIN services s ON s.id=a.service_id
     JOIN users u ON u.id=a.user_id
     WHERE a.branch_id=? AND a.appointment_date=CURDATE()
     ORDER BY a.appointment_time"
);
$appointments->execute([$bid]);
$todayAppts = $appointments->fetchAll();

require_once __DIR__ . '/../views/partials/header.php';
?>

<div style="margin-bottom:1.75rem">
  <h1 style="font-size:1.4rem;font-weight:800;margin-bottom:.2rem">
    Staff Panel – <?= date('l, d F Y') ?>
  </h1>
  <p style="color:var(--muted);font-size:.88rem">Manage your queue and appointments</p>
</div>

<!-- Stats -->
<div class="stat-grid" style="margin-bottom:2rem">
  <div class="stat-card">
    <div class="stat-icon" style="background:#fefce8;color:#ca8a04"><i class="bi bi-hourglass-split"></i></div>
    <div class="stat-text">
      <div class="value" id="statWaiting"><?= $waiting ?></div>
      <div class="label">Waiting Now</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:#eff6ff;color:var(--brand)"><i class="bi bi-person-check-fill"></i></div>
    <div class="stat-text">
      <div class="value" id="statServing"><?= $serving ?></div>
      <div class="label">Being Served</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:#f0fdf4;color:#16a34a"><i class="bi bi-check-all"></i></div>
    <div class="stat-text">
      <div class="value"><?= $completed ?></div>
      <div class="label">Completed Today</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:#fdf4ff;color:#9333ea"><i class="bi bi-award-fill"></i></div>
    <div class="stat-text">
      <div class="value"><?= $myServed ?></div>
      <div class="label">My Served Today</div>
    </div>
  </div>
</div>

<div class="row g-4">
  <!-- Call Next Panel -->
  <div class="col-lg-5">
    <div class="panel" style="height:100%">
      <div class="panel-header">
        <span class="panel-title"><i class="bi bi-megaphone-fill" style="color:var(--brand)"></i> Call Next</span>
      </div>
      <div class="panel-body">
        <div style="margin-bottom:1rem">
          <label class="form-label">Service</label>
          <select id="callSvcId" class="form-select">
            <?php foreach ($allServices as $sv): ?>
            <option value="<?= $sv['id'] ?>">
              <?= e($sv['name']) ?> (<?= (int)$sv['waiting_count'] ?> waiting)
            </option>
            <?php endforeach; ?>
          </select>
        </div>

        <button class="btn-brand" style="width:100%;padding:1rem;font-size:1.05rem;margin-bottom:1.5rem"
                onclick="callNext()">
          <i class="bi bi-megaphone-fill"></i> Call Next Ticket
        </button>

        <!-- Current ticket display -->
        <div id="currentTicketBox" style="display:none;text-align:center;
             background:linear-gradient(135deg,rgba(27,79,216,.08) 0%,rgba(0,201,167,.06) 100%);
             border:1px solid rgba(27,79,216,.2);border-radius:16px;padding:2rem">
          <div style="font-size:.75rem;font-weight:700;text-transform:uppercase;
                      letter-spacing:.15em;color:var(--muted);margin-bottom:.5rem">Now Calling</div>
          <div id="currentTicketNum"
               style="font-family:'Bebas Neue',sans-serif;font-size:5rem;line-height:1;
                      color:var(--brand);letter-spacing:.05em"></div>
          <div id="currentSvcName" style="font-weight:600;color:var(--muted);margin:.5rem 0"></div>
          <button class="btn-brand" style="margin-top:1rem" onclick="completeTicket()">
            <i class="bi bi-check-lg"></i> Mark Complete
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Service Queue overview -->
  <div class="col-lg-7">
    <div class="panel">
      <div class="panel-header">
        <span class="panel-title"><i class="bi bi-bar-chart-fill" style="color:var(--accent)"></i> Queue by Service</span>
        <a href="queue.php" class="btn-ghost" style="font-size:.8rem;padding:.35rem .8rem">Full Queue →</a>
      </div>
      <div class="panel-body" style="display:flex;flex-direction:column;gap:.75rem">
        <?php foreach ($allServices as $sv): ?>
        <div style="display:flex;align-items:center;gap:1rem">
          <div style="width:38px;height:38px;border-radius:10px;background:<?= e($sv['color']) ?>22;
                      display:grid;place-items:center;font-size:1.1rem;color:<?= e($sv['color']) ?>;flex-shrink:0">
            <i class="bi <?= e($sv['icon']) ?>"></i>
          </div>
          <div style="flex:1">
            <div style="display:flex;justify-content:space-between;margin-bottom:.3rem">
              <span style="font-weight:600;font-size:.9rem"><?= e($sv['name']) ?></span>
              <span style="font-size:.82rem;color:var(--muted)"><?= (int)$sv['waiting_count'] ?> waiting</span>
            </div>
            <div style="background:var(--bg);border-radius:20px;height:6px;overflow:hidden">
              <div style="height:100%;border-radius:20px;background:<?= e($sv['color']) ?>;
                          width:<?= min(100, (int)$sv['waiting_count'] * 5) ?>%;
                          transition:width .5s ease"></div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Today's appointments -->
    <div class="panel" style="margin-top:1.25rem">
      <div class="panel-header">
        <span class="panel-title"><i class="bi bi-calendar-check" style="color:#f97316"></i> Appointments Today</span>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr><th>Time</th><th>Customer</th><th>Service</th><th>Status</th><th>Action</th></tr>
          </thead>
          <tbody>
            <?php foreach ($todayAppts as $a): ?>
            <tr>
              <td><strong><?= date('H:i', strtotime($a['appointment_time'])) ?></strong></td>
              <td>
                <div><?= e($a['customer_name']) ?></div>
                <div style="font-size:.75rem;color:var(--muted)"><?= e($a['customer_phone'] ?? '') ?></div>
              </td>
              <td><?= e($a['service_name']) ?></td>
              <td><?= statusBadge($a['status']) ?></td>
              <td>
                <?php if ($a['status'] === 'pending' || $a['status'] === 'confirmed'): ?>
                <form method="POST" action="<?= APP_URL ?>/admin/appointments.php" style="display:inline">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="update_status">
                  <input type="hidden" name="id"     value="<?= $a['id'] ?>">
                  <input type="hidden" name="status" value="completed">
                  <button type="submit" class="btn-brand" style="font-size:.75rem;padding:.3rem .65rem">
                    <i class="bi bi-check"></i> Done
                  </button>
                </form>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($todayAppts)): ?>
            <tr><td colspan="5" style="text-align:center;color:var(--muted);padding:2rem">No appointments today</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php
$extraJs = '
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&display=swap" rel="stylesheet">
<script>
let currentTicketId = null;

async function callNext() {
  const svcId = document.getElementById("callSvcId").value;
  const res = await fetch("' . APP_URL . '/admin/tickets.php", {
    method: "POST",
    headers: {"Content-Type":"application/x-www-form-urlencoded"},
    body: new URLSearchParams({action:"call_next",service_id:svcId,_csrf:"' . csrfToken() . '"})
  }).then(r => r.json());

  if (res.success) {
    currentTicketId = res.ticket.id;
    document.getElementById("currentTicketNum").textContent = res.ticket.ticket_number;
    document.getElementById("currentSvcName").textContent   = "Service: " + document.querySelector("#callSvcId option:checked").text;
    document.getElementById("currentTicketBox").style.display = "block";
    // Update waiting count
    const waiting = parseInt(document.getElementById("statWaiting").textContent);
    document.getElementById("statWaiting").textContent = Math.max(0, waiting - 1);
  } else {
    alert(res.message ?? "No waiting tickets.");
  }
}

async function completeTicket() {
  if (!currentTicketId) return;
  await fetch("' . APP_URL . '/admin/tickets.php", {
    method: "POST",
    headers: {"Content-Type":"application/x-www-form-urlencoded"},
    body: new URLSearchParams({action:"update_status",ticket_id:currentTicketId,status:"completed",_csrf:"' . csrfToken() . '"})
  });
  document.getElementById("currentTicketBox").style.display = "none";
  currentTicketId = null;
  setTimeout(() => location.reload(), 500);
}

// Refresh waiting count every 15s
setInterval(async () => {
  const res = await fetch("' . APP_URL . '/api/queue-status.php?branch_id=' . $bid . '");
  const data = await res.json();
  document.getElementById("statWaiting").textContent = data.stats.waiting_count;
}, 15000);
</script>';
require_once __DIR__ . '/../views/partials/footer.php';
?>
