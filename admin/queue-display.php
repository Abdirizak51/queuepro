<?php
// admin/queue-display.php
// Public-facing display for a big screen/TV showing current serving ticket
require_once __DIR__ . '/../bootstrap.php';

$branchId = (int)($_GET['branch'] ?? 1);
$pdo = db();

$branch = $pdo->prepare("SELECT * FROM branches WHERE id=?")->execute([$branchId]) ?
          $pdo->prepare("SELECT * FROM branches WHERE id=?")->execute([$branchId]) : null;
$branchRow = $pdo->prepare("SELECT name FROM branches WHERE id=?")->execute([$branchId]) ? null : null;
$stmt = $pdo->prepare("SELECT name FROM branches WHERE id=?");
$stmt->execute([$branchId]);
$branchRow = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Queue Display – <?= APP_NAME ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;700;800;900&family=Bebas+Neue&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    :root {
      --brand:#1B4FD8; --accent:#00C9A7; --bg:#050B1A; --surface:rgba(255,255,255,.04);
    }
    *{box-sizing:border-box;margin:0;padding:0}
    body {
      font-family: 'Plus Jakarta Sans', sans-serif;
      background: var(--bg);
      color: #fff;
      min-height: 100vh;
      padding: 2rem;
      display: flex;
      flex-direction: column;
    }
    /* Header */
    .header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 2rem;
      padding-bottom: 1.5rem;
      border-bottom: 1px solid rgba(255,255,255,.08);
    }
    .header-logo {
      display: flex;
      align-items: center;
      gap: .75rem;
    }
    .header-logo-icon {
      width: 50px; height: 50px;
      background: var(--brand);
      border-radius: 14px;
      display: grid;
      place-items: center;
      font-size: 1.5rem;
    }
    .header-logo-name {
      font-size: 1.5rem;
      font-weight: 800;
    }
    .header-logo-name span { color: var(--accent); }
    .header-branch {
      font-size: .9rem;
      color: rgba(255,255,255,.5);
    }
    .header-time {
      text-align: right;
      font-size: 1.1rem;
      font-weight: 700;
    }
    .header-time .date {
      font-size: .82rem;
      color: rgba(255,255,255,.5);
      font-weight: 400;
    }

    /* NOW SERVING section */
    .now-serving-section {
      flex: 0;
      margin-bottom: 2rem;
    }
    .section-label {
      font-size: .75rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .15em;
      color: rgba(255,255,255,.4);
      margin-bottom: 1rem;
    }
    .now-serving-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap: 1.25rem;
    }
    .serving-card {
      background: linear-gradient(135deg, rgba(27,79,216,.3) 0%, rgba(0,201,167,.15) 100%);
      border: 1px solid rgba(27,79,216,.5);
      border-radius: 20px;
      padding: 2rem;
      text-align: center;
      position: relative;
      overflow: hidden;
    }
    .serving-card::before {
      content: '';
      position: absolute;
      top: -50%;
      left: -50%;
      width: 200%;
      height: 200%;
      background: radial-gradient(circle at center, rgba(0,201,167,.1) 0%, transparent 60%);
      animation: pulse 3s ease-in-out infinite;
    }
    @keyframes pulse {
      0%, 100% { transform: scale(1); opacity: 1; }
      50%       { transform: scale(1.1); opacity: .7; }
    }
    .serving-number {
      font-family: 'Bebas Neue', sans-serif;
      font-size: 7rem;
      line-height: 1;
      color: var(--accent);
      letter-spacing: .05em;
      position: relative;
      z-index: 1;
    }
    .serving-service {
      font-size: 1.1rem;
      font-weight: 700;
      color: rgba(255,255,255,.8);
      margin-top: .5rem;
      position: relative;
      z-index: 1;
    }
    .serving-counter {
      font-size: .82rem;
      color: rgba(255,255,255,.4);
      margin-top: .3rem;
      position: relative;
      z-index: 1;
    }

    /* WAITING QUEUE */
    .waiting-section { flex: 1; }
    .waiting-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
      gap: .75rem;
    }
    .waiting-ticket {
      background: var(--surface);
      border: 1px solid rgba(255,255,255,.08);
      border-radius: 14px;
      padding: 1rem;
      text-align: center;
      transition: background .3s;
    }
    .waiting-ticket.called {
      background: rgba(245,158,11,.1);
      border-color: rgba(245,158,11,.4);
    }
    .waiting-number {
      font-family: 'Bebas Neue', sans-serif;
      font-size: 2.4rem;
      line-height: 1;
      color: rgba(255,255,255,.85);
    }
    .waiting-svc {
      font-size: .7rem;
      color: rgba(255,255,255,.4);
      margin-top: .25rem;
    }

    /* Marquee */
    .marquee-bar {
      margin-top: 2rem;
      background: var(--brand);
      border-radius: 12px;
      padding: .65rem 1.5rem;
      overflow: hidden;
      white-space: nowrap;
    }
    .marquee-inner {
      display: inline-block;
      animation: marquee 20s linear infinite;
      font-size: .9rem;
      font-weight: 600;
    }
    @keyframes marquee {
      0%   { transform: translateX(0); }
      100% { transform: translateX(-50%); }
    }
  </style>
</head>
<body>

<header class="header">
  <div class="header-logo">
    <div class="header-logo-icon"><i class="bi bi-ticket-perforated"></i></div>
    <div>
      <div class="header-logo-name">Queue<span>Pro</span></div>
      <div class="header-branch"><?= e($branchRow['name'] ?? 'All Branches') ?></div>
    </div>
  </div>
  <div class="header-time">
    <div id="clock">--:--:--</div>
    <div class="date" id="dateStr"></div>
  </div>
</header>

<!-- Now Serving -->
<div class="now-serving-section">
  <div class="section-label"><i class="bi bi-megaphone-fill"></i> Now Serving</div>
  <div class="now-serving-grid" id="nowServingGrid">
    <!-- Populated by JS -->
  </div>
</div>

<!-- Waiting -->
<div class="waiting-section">
  <div class="section-label"><i class="bi bi-hourglass-split"></i> Queue</div>
  <div class="waiting-grid" id="waitingGrid">
    <!-- Populated by JS -->
  </div>
</div>

<!-- Announcement bar -->
<div class="marquee-bar">
  <span class="marquee-inner">
    🎫 Welcome to <?= e(APP_NAME) ?> &nbsp;&nbsp;•&nbsp;&nbsp;
    Please have your ticket ready &nbsp;&nbsp;•&nbsp;&nbsp;
    Respect other customers &nbsp;&nbsp;•&nbsp;&nbsp;
    <?= date('l, d F Y') ?> &nbsp;&nbsp;•&nbsp;&nbsp;
    Welcome to <?= e(APP_NAME) ?> &nbsp;&nbsp;•&nbsp;&nbsp;
    Please have your ticket ready &nbsp;&nbsp;•&nbsp;&nbsp;
    Respect other customers &nbsp;&nbsp;•&nbsp;&nbsp;
    <?= date('l, d F Y') ?>
  </span>
</div>

<script>
const BRANCH_ID = <?= $branchId ?>;

function updateClock() {
  const now = new Date();
  document.getElementById('clock').textContent = now.toLocaleTimeString('en-GB');
  document.getElementById('dateStr').textContent = now.toLocaleDateString('en-GB', {weekday:'long',day:'numeric',month:'long',year:'numeric'});
}
setInterval(updateClock, 1000);
updateClock();

async function fetchQueueData() {
  try {
    const res = await fetch(`<?= APP_URL ?>/api/queue-status.php?branch_id=${BRANCH_ID}`);
    const data = await res.json();

    // Now serving
    const nsGrid = document.getElementById('nowServingGrid');
    if (data.serving.length === 0) {
      nsGrid.innerHTML = '<div style="color:rgba(255,255,255,.3);font-size:1.1rem;grid-column:1/-1;padding:2rem;text-align:center"><i class="bi bi-pause-circle" style="font-size:2rem;display:block;margin-bottom:.5rem"></i>No active tickets</div>';
    } else {
      nsGrid.innerHTML = data.serving.map(t => `
        <div class="serving-card">
          <div class="serving-number">${t.ticket_number}</div>
          <div class="serving-service">${t.service_name}</div>
          <div class="serving-counter">Counter ${t.counter ?? '1'}</div>
        </div>
      `).join('');
    }

    // Waiting
    const wGrid = document.getElementById('waitingGrid');
    if (data.waiting.length === 0) {
      wGrid.innerHTML = '<div style="color:rgba(255,255,255,.3);grid-column:1/-1;text-align:center;padding:2rem">Queue is empty</div>';
    } else {
      wGrid.innerHTML = data.waiting.map(t => `
        <div class="waiting-ticket ${t.status === 'called' ? 'called' : ''}">
          <div class="waiting-number">${t.ticket_number}</div>
          <div class="waiting-svc">${t.service_name}</div>
        </div>
      `).join('');
    }
  } catch(e) {
    console.error('Queue fetch error:', e);
  }
}

fetchQueueData();
setInterval(fetchQueueData, 5000); // Refresh every 5 seconds
</script>
</body>
</html>
