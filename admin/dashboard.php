<?php
// admin/dashboard.php
require_once __DIR__ . '/../bootstrap.php';
requireRole(ROLE_ADMIN);

$pageTitle = 'Dashboard';
$pdo = db();

// ---- Stats ----
$totalUsers     = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$activeUsers    = $pdo->query("SELECT COUNT(*) FROM users WHERE status='active'")->fetchColumn();
$ticketsToday   = $pdo->query("SELECT COUNT(*) FROM tickets WHERE DATE(created_at)=CURDATE()")->fetchColumn();
$completedToday = $pdo->query("SELECT COUNT(*) FROM tickets WHERE DATE(created_at)=CURDATE() AND status='completed'")->fetchColumn();
$waitingNow     = $pdo->query("SELECT COUNT(*) FROM tickets WHERE status='waiting' AND DATE(created_at)=CURDATE()")->fetchColumn();
$apptToday      = $pdo->query("SELECT COUNT(*) FROM appointments WHERE appointment_date=CURDATE()")->fetchColumn();

// ---- Weekly ticket chart ----
$weeklyStmt = $pdo->query(
    "SELECT DATE(created_at) AS day, COUNT(*) AS cnt
     FROM tickets
     WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
     GROUP BY DATE(created_at)
     ORDER BY day"
);
$weeklyRaw = $weeklyStmt->fetchAll();
$weekDays  = [];
$weekCnts  = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $weekDays[] = date('D', strtotime($d));
    $found = false;
    foreach ($weeklyRaw as $r) {
        if ($r['day'] === $d) { $weekCnts[] = (int)$r['cnt']; $found = true; break; }
    }
    if (!$found) $weekCnts[] = 0;
}

// ---- Service popularity ----
$svcStmt = $pdo->query(
    "SELECT s.name, COUNT(t.id) AS cnt
     FROM services s
     LEFT JOIN tickets t ON t.service_id=s.id AND DATE(t.created_at)=CURDATE()
     GROUP BY s.id ORDER BY cnt DESC LIMIT 6"
);
$svcData = $svcStmt->fetchAll();

// ---- Recent tickets ----
$recentTickets = $pdo->query(
    "SELECT t.*, s.name AS service_name, s.color AS svc_color,
            u.full_name AS customer_name
     FROM tickets t
     JOIN services s ON s.id=t.service_id
     LEFT JOIN users u ON u.id=t.user_id
     ORDER BY t.created_at DESC LIMIT 10"
)->fetchAll();

// ---- Today's appointments ----
$todayAppts = $pdo->query(
    "SELECT a.*, s.name AS service_name, u.full_name AS customer_name
     FROM appointments a
     JOIN services s ON s.id=a.service_id
     JOIN users u ON u.id=a.user_id
     WHERE a.appointment_date=CURDATE()
     ORDER BY a.appointment_time"
)->fetchAll();

require_once __DIR__ . '/../views/partials/header.php';
?>

<!-- ============ STAT CARDS ============ -->
<div class="stat-grid">
  <div class="stat-card">
    <div class="stat-icon" style="background:#eff6ff;color:var(--brand)">
      <i class="bi bi-people-fill"></i>
    </div>
    <div class="stat-text">
      <div class="value"><?= number_format($totalUsers) ?></div>
      <div class="label">Total Users</div>
      <div class="change up"><i class="bi bi-arrow-up"></i> <?= $activeUsers ?> active</div>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon" style="background:#fff7ed;color:#f97316">
      <i class="bi bi-ticket-detailed-fill"></i>
    </div>
    <div class="stat-text">
      <div class="value"><?= number_format($ticketsToday) ?></div>
      <div class="label">Tickets Today</div>
      <div class="change up"><i class="bi bi-check-circle"></i> <?= $completedToday ?> completed</div>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon" style="background:#fefce8;color:#ca8a04">
      <i class="bi bi-hourglass-split"></i>
    </div>
    <div class="stat-text">
      <div class="value"><?= number_format($waitingNow) ?></div>
      <div class="label">Waiting Now</div>
      <div class="change <?= $waitingNow > 20 ? 'down' : 'up' ?>">
        <?= $waitingNow > 20 ? '<i class="bi bi-exclamation-triangle"></i> High load' : 'Normal load' ?>
      </div>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon" style="background:#f0fdf4;color:#16a34a">
      <i class="bi bi-calendar-check-fill"></i>
    </div>
    <div class="stat-text">
      <div class="value"><?= number_format($apptToday) ?></div>
      <div class="label">Appointments Today</div>
      <div class="change up"><i class="bi bi-calendar3"></i> Scheduled</div>
    </div>
  </div>
</div>

<!-- ============ CHARTS ROW ============ -->
<div class="row g-4 mb-4">
  <div class="col-lg-8">
    <div class="panel h-100">
      <div class="panel-header">
        <span class="panel-title">Ticket Trend – Last 7 Days</span>
        <a href="reports.php" class="btn-ghost" style="font-size:.8rem;padding:.35rem .8rem">
          <i class="bi bi-arrow-right"></i> Full Report
        </a>
      </div>
      <div class="panel-body">
        <canvas id="trendChart" height="220"></canvas>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="panel h-100">
      <div class="panel-header">
        <span class="panel-title">Services Today</span>
      </div>
      <div class="panel-body">
        <canvas id="svcChart" height="220"></canvas>
      </div>
    </div>
  </div>
</div>

<!-- ============ TABLES ROW ============ -->
<div class="row g-4">
  <!-- Recent Tickets -->
  <div class="col-xl-7">
    <div class="panel">
      <div class="panel-header">
        <span class="panel-title">Recent Tickets</span>
        <a href="tickets.php" class="btn-brand" style="font-size:.8rem;padding:.35rem .8rem">
          <i class="bi bi-list-ul"></i> All Tickets
        </a>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>#</th><th>Customer</th><th>Service</th>
              <th>Status</th><th>Time</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recentTickets as $t): ?>
            <tr>
              <td><strong style="color:var(--brand)"><?= e($t['ticket_number']) ?></strong></td>
              <td><?= e($t['customer_name'] ?? 'Walk-in') ?></td>
              <td>
                <span style="display:inline-flex;align-items:center;gap:.4rem">
                  <span style="width:8px;height:8px;border-radius:50%;background:<?= e($t['svc_color']) ?>"></span>
                  <?= e($t['service_name']) ?>
                </span>
              </td>
              <td><?= statusBadge($t['status']) ?></td>
              <td style="color:var(--muted);font-size:.8rem"><?= timeAgo($t['created_at']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($recentTickets)): ?>
            <tr><td colspan="5" style="text-align:center;color:var(--muted);padding:2rem">No tickets today</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Today's Appointments -->
  <div class="col-xl-5">
    <div class="panel">
      <div class="panel-header">
        <span class="panel-title">Today's Appointments</span>
        <a href="appointments.php" class="btn-ghost" style="font-size:.8rem;padding:.35rem .8rem">View All</a>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr><th>Time</th><th>Customer</th><th>Service</th><th>Status</th></tr>
          </thead>
          <tbody>
            <?php foreach ($todayAppts as $a): ?>
            <tr>
              <td><strong><?= date('H:i', strtotime($a['appointment_time'])) ?></strong></td>
              <td><?= e($a['customer_name']) ?></td>
              <td><?= e($a['service_name']) ?></td>
              <td><?= statusBadge($a['status']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($todayAppts)): ?>
            <tr><td colspan="4" style="text-align:center;color:var(--muted);padding:2rem">No appointments</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php
$extraJs = '
<script>
// Trend Chart
new Chart(document.getElementById("trendChart"), {
  type: "bar",
  data: {
    labels: ' . json_encode($weekDays) . ',
    datasets: [{
      label: "Tickets",
      data: ' . json_encode($weekCnts) . ',
      backgroundColor: "rgba(27,79,216,.15)",
      borderColor: "#1B4FD8",
      borderWidth: 2,
      borderRadius: 8,
    }]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
      y: { beginAtZero: true, grid: { color: "rgba(0,0,0,.05)" }, ticks: { stepSize: 1 } },
      x: { grid: { display: false } }
    }
  }
});

// Service doughnut
new Chart(document.getElementById("svcChart"), {
  type: "doughnut",
  data: {
    labels: ' . json_encode(array_column($svcData, 'name')) . ',
    datasets: [{
      data: ' . json_encode(array_column($svcData, 'cnt')) . ',
      backgroundColor: ["#1B4FD8","#00C9A7","#f97316","#8b5cf6","#ef4444","#10b981"],
      borderWidth: 0,
    }]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: {
      legend: { position: "bottom", labels: { font: { size: 11 } } }
    },
    cutout: "65%"
  }
});
</script>';
require_once __DIR__ . '/../views/partials/footer.php';
?>
