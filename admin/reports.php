<?php
// admin/reports.php
require_once __DIR__ . '/../bootstrap.php';
requireRole(ROLE_ADMIN);

$pdo       = db();
$pageTitle = 'Reports';

$from = $_GET['from'] ?? date('Y-m-01');  // First of month
$to   = $_GET['to']   ?? date('Y-m-d');
$type = $_GET['type'] ?? 'tickets';

// ---- Export CSV ----
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="queuepro-report-' . date('Ymd') . '.csv"');
    $fp = fopen('php://output', 'w');
    fprintf($fp, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM

    if ($type === 'tickets') {
        fputcsv($fp, ['Ticket #','Customer','Service','Branch','Status','Priority','Created','Completed','Staff']);
        $stmt = $pdo->prepare(
            "SELECT t.ticket_number,COALESCE(u.full_name,'Walk-in'),s.name,b.name,t.status,t.priority,
                    t.created_at,t.completed_at,COALESCE(st.full_name,'–')
             FROM tickets t
             JOIN services s ON s.id=t.service_id
             JOIN branches b ON b.id=t.branch_id
             LEFT JOIN users u ON u.id=t.user_id
             LEFT JOIN users st ON st.id=t.served_by
             WHERE DATE(t.created_at) BETWEEN ? AND ?
             ORDER BY t.created_at"
        );
        $stmt->execute([$from, $to]);
    } else {
        fputcsv($fp, ['ID','Customer','Phone','Service','Date','Time','Status','Notes']);
        $stmt = $pdo->prepare(
            "SELECT a.id,u.full_name,u.phone,s.name,a.appointment_date,a.appointment_time,a.status,a.notes
             FROM appointments a JOIN services s ON s.id=a.service_id JOIN users u ON u.id=a.user_id
             WHERE a.appointment_date BETWEEN ? AND ?
             ORDER BY a.appointment_date,a.appointment_time"
        );
        $stmt->execute([$from, $to]);
    }
    foreach ($stmt->fetchAll(PDO::FETCH_NUM) as $row) fputcsv($fp, $row);
    fclose($fp);
    exit;
}

// ---- Summary stats ----
$summary = $pdo->prepare(
    "SELECT
       COUNT(*) AS total,
       SUM(status='completed') AS completed,
       SUM(status='cancelled') AS cancelled,
       SUM(status='no_show')   AS no_show,
       SUM(status='waiting')   AS waiting,
       AVG(TIMESTAMPDIFF(MINUTE,created_at,completed_at)) AS avg_minutes
     FROM tickets
     WHERE DATE(created_at) BETWEEN :f AND :t"
);
$summary->execute([':f' => $from, ':t' => $to]);
$stats = $summary->fetch();

// ---- Daily chart data ----
$dailyStmt = $pdo->prepare(
    "SELECT DATE(created_at) AS day,
            COUNT(*) AS total,
            SUM(status='completed') AS completed
     FROM tickets
     WHERE DATE(created_at) BETWEEN ? AND ?
     GROUP BY DATE(created_at) ORDER BY day"
);
$dailyStmt->execute([$from, $to]);
$dailyData = $dailyStmt->fetchAll();

// ---- Service breakdown ----
$svcStmt = $pdo->prepare(
    "SELECT s.name, s.color, COUNT(t.id) AS total, SUM(t.status='completed') AS completed
     FROM services s
     LEFT JOIN tickets t ON t.service_id=s.id AND DATE(t.created_at) BETWEEN ? AND ?
     GROUP BY s.id ORDER BY total DESC"
);
$svcStmt->execute([$from, $to]);
$svcBreak = $svcStmt->fetchAll();

// ---- Staff performance ----
$staffStmt = $pdo->prepare(
    "SELECT u.full_name, COUNT(t.id) AS served,
            AVG(TIMESTAMPDIFF(MINUTE,t.started_at,t.completed_at)) AS avg_time
     FROM users u
     JOIN tickets t ON t.served_by=u.id AND DATE(t.created_at) BETWEEN ? AND ?
     WHERE u.role_id = ?
     GROUP BY u.id ORDER BY served DESC"
);
$staffStmt->execute([$from, $to, ROLE_STAFF]);
$staffPerf = $staffStmt->fetchAll();

require_once __DIR__ . '/../views/partials/header.php';
?>

<!-- Header -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem">
  <div>
    <h1 style="font-size:1.4rem;font-weight:800">Reports</h1>
    <p style="color:var(--muted);font-size:.88rem">Analytics from <?= date('d M', strtotime($from)) ?> to <?= date('d M Y', strtotime($to)) ?></p>
  </div>
  <div style="display:flex;gap:.75rem">
    <a href="?from=<?= $from ?>&to=<?= $to ?>&type=tickets&export=csv" class="btn-ghost">
      <i class="bi bi-filetype-csv"></i> Export Tickets
    </a>
    <a href="?from=<?= $from ?>&to=<?= $to ?>&type=appointments&export=csv" class="btn-ghost">
      <i class="bi bi-calendar-check"></i> Export Appointments
    </a>
  </div>
</div>

<!-- Date filter -->
<div class="panel" style="margin-bottom:1.5rem">
  <div class="panel-body" style="padding:.85rem 1.25rem">
    <form method="GET" style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap">
      <div>
        <label class="form-label" style="margin-bottom:.25rem">From</label>
        <input type="date" name="from" class="form-control" value="<?= $from ?>">
      </div>
      <div>
        <label class="form-label" style="margin-bottom:.25rem">To</label>
        <input type="date" name="to" class="form-control" value="<?= $to ?>">
      </div>
      <div style="align-self:flex-end">
        <button type="submit" class="btn-brand"><i class="bi bi-bar-chart"></i> Apply</button>
      </div>
      <!-- Quick range presets -->
      <div style="align-self:flex-end;display:flex;gap:.5rem">
        <?php
        $presets = [
            ['Today', date('Y-m-d'), date('Y-m-d')],
            ['This Week', date('Y-m-d', strtotime('monday this week')), date('Y-m-d')],
            ['This Month', date('Y-m-01'), date('Y-m-d')],
            ['Last Month', date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('last month'))],
        ];
        foreach ($presets as [$label, $f, $t]): ?>
        <a href="?from=<?= $f ?>&to=<?= $t ?>"
           class="btn-ghost" style="font-size:.78rem;padding:.4rem .75rem"><?= $label ?></a>
        <?php endforeach; ?>
      </div>
    </form>
  </div>
</div>

<!-- Summary cards -->
<div class="stat-grid" style="margin-bottom:1.75rem">
  <div class="stat-card">
    <div class="stat-icon" style="background:#eff6ff;color:var(--brand)"><i class="bi bi-ticket-fill"></i></div>
    <div class="stat-text">
      <div class="value"><?= number_format($stats['total']) ?></div>
      <div class="label">Total Tickets</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:#f0fdf4;color:#16a34a"><i class="bi bi-check-circle-fill"></i></div>
    <div class="stat-text">
      <div class="value"><?= number_format($stats['completed']) ?></div>
      <div class="label">Completed</div>
      <div class="change up"><?= $stats['total'] > 0 ? round($stats['completed']/$stats['total']*100) : 0 ?>% rate</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:#fef2f2;color:#dc2626"><i class="bi bi-x-circle-fill"></i></div>
    <div class="stat-text">
      <div class="value"><?= number_format($stats['cancelled'] + $stats['no_show']) ?></div>
      <div class="label">Cancelled / No-Show</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:#fff7ed;color:#f97316"><i class="bi bi-stopwatch-fill"></i></div>
    <div class="stat-text">
      <div class="value"><?= $stats['avg_minutes'] ? round($stats['avg_minutes']) . 'm' : 'N/A' ?></div>
      <div class="label">Avg Service Time</div>
    </div>
  </div>
</div>

<div class="row g-4 mb-4">
  <!-- Daily trend -->
  <div class="col-lg-8">
    <div class="panel h-100">
      <div class="panel-header"><span class="panel-title">Daily Ticket Trend</span></div>
      <div class="panel-body"><canvas id="dailyChart" height="240"></canvas></div>
    </div>
  </div>
  <!-- Service breakdown -->
  <div class="col-lg-4">
    <div class="panel h-100">
      <div class="panel-header"><span class="panel-title">By Service</span></div>
      <div class="panel-body"><canvas id="svcBreakChart" height="240"></canvas></div>
    </div>
  </div>
</div>

<!-- Staff performance table -->
<div class="panel">
  <div class="panel-header"><span class="panel-title">Staff Performance</span></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Staff Member</th><th>Tickets Served</th><th>Avg Service Time</th><th>Performance</th></tr></thead>
      <tbody>
        <?php foreach ($staffPerf as $sp): ?>
        <tr>
          <td><strong><?= e($sp['full_name']) ?></strong></td>
          <td><?= number_format($sp['served']) ?></td>
          <td><?= $sp['avg_time'] ? round($sp['avg_time']) . ' min' : 'N/A' ?></td>
          <td>
            <div style="background:var(--bg);border-radius:20px;height:8px;overflow:hidden;width:150px">
              <?php $pct = $stats['total'] > 0 ? min(100, $sp['served']/$stats['total']*100) : 0; ?>
              <div style="height:100%;background:var(--brand);width:<?= round($pct) ?>%;border-radius:20px"></div>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($staffPerf)): ?>
        <tr><td colspan="4" style="text-align:center;color:var(--muted);padding:2rem">No staff data for this period</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php
$days  = array_column($dailyData, 'day');
$totals = array_column($dailyData, 'total');
$dones  = array_column($dailyData, 'completed');

$extraJs = '
<script>
new Chart(document.getElementById("dailyChart"), {
  type: "line",
  data: {
    labels: ' . json_encode(array_map(fn($d) => date('d M', strtotime($d)), $days)) . ',
    datasets: [
      {label:"Total",   data:' . json_encode($totals) . ',borderColor:"#1B4FD8",backgroundColor:"rgba(27,79,216,.08)",tension:.4,fill:true,borderWidth:2,pointRadius:4},
      {label:"Completed",data:' . json_encode($dones) . ',borderColor:"#00C9A7",backgroundColor:"transparent",tension:.4,borderWidth:2,pointRadius:4}
    ]
  },
  options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:"top"}},
           scales:{y:{beginAtZero:true,grid:{color:"rgba(0,0,0,.05)"}},x:{grid:{display:false}}}}
});

new Chart(document.getElementById("svcBreakChart"), {
  type: "bar",
  data: {
    labels: ' . json_encode(array_column($svcBreak, 'name')) . ',
    datasets: [{label:"Tickets",data:' . json_encode(array_column($svcBreak, 'total')) . ',
      backgroundColor: ' . json_encode(array_map(fn($s) => $s['color'].'bb', $svcBreak)) . ',
      borderRadius:8}]
  },
  options:{responsive:true,maintainAspectRatio:false,indexAxis:"y",
           plugins:{legend:{display:false}},
           scales:{x:{beginAtZero:true,grid:{display:false}},y:{grid:{display:false}}}}
});
</script>';
require_once __DIR__ . '/../views/partials/footer.php';
?>
