<?php
// admin/queue-live.php  – Enhanced AJAX live queue board for TV/big screens
require_once __DIR__ . '/../bootstrap.php';

$branchId  = (int)($_GET['branch'] ?? 1);
$pdo       = db();

$branchStmt = $pdo->prepare("SELECT * FROM branches WHERE id=?");
$branchStmt->execute([$branchId]);
$branch = $branchStmt->fetch();

$branches = $pdo->query("SELECT id, name FROM branches WHERE status='active'")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Live Queue – <?= e(APP_NAME) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Plus+Jakarta+Sans:wght@400;600;700;800;900&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    :root {
      --brand: #1B4FD8;
      --accent: #00C9A7;
      --bg: #060C1C;
      --card: rgba(255,255,255,.04);
      --border: rgba(255,255,255,.07);
    }
    *, *::before, *::after { box-sizing: border-box; margin:0; padding:0; }
    html, body {
      background: var(--bg);
      color: #fff;
      font-family: 'Plus Jakarta Sans', sans-serif;
      height: 100%;
      overflow: hidden;
    }
    body {
      display: grid;
      grid-template-rows: auto 1fr auto;
      min-height: 100vh;
      padding: 1.5rem;
      gap: 1.25rem;
    }

    /* ===== TOPBAR ===== */
    .topbar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      background: rgba(255,255,255,.04);
      border: 1px solid var(--border);
      border-radius: 16px;
      padding: .85rem 1.5rem;
    }
    .logo { display:flex; align-items:center; gap:.7rem; }
    .logo-icon {
      width: 38px; height: 38px;
      background: var(--brand);
      border-radius: 10px;
      display: grid;
      place-items: center;
      font-size: 1.2rem;
    }
    .logo-name { font-size: 1.1rem; font-weight: 800; }
    .logo-name span { color: var(--accent); }
    .branch-name {
      font-size: .82rem;
      color: rgba(255,255,255,.45);
      background: rgba(255,255,255,.06);
      border-radius: 20px;
      padding: .3rem .9rem;
    }
    .clock-box { text-align: right; }
    .clock { font-family: 'Bebas Neue', sans-serif; font-size: 2.2rem; line-height:1; letter-spacing: .05em; }
    .clock-date { font-size: .72rem; color: rgba(255,255,255,.4); }

    /* ===== MAIN GRID ===== */
    .main-grid {
      display: grid;
      grid-template-columns: 1fr 320px;
      gap: 1.25rem;
      overflow: hidden;
    }

    /* ===== NOW SERVING ===== */
    .serving-section {
      display: flex;
      flex-direction: column;
      gap: 1.25rem;
    }
    .section-title {
      font-size: .7rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .15em;
      color: rgba(255,255,255,.35);
      display: flex;
      align-items: center;
      gap: .5rem;
    }
    .section-title::after {
      content: '';
      flex: 1;
      height: 1px;
      background: var(--border);
    }
    .serving-cards {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
      gap: 1rem;
      flex: 1;
    }
    .serving-card {
      background: linear-gradient(135deg, rgba(27,79,216,.25) 0%, rgba(0,201,167,.12) 100%);
      border: 1px solid rgba(27,79,216,.35);
      border-radius: 20px;
      padding: 1.75rem 1.5rem;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      text-align: center;
      position: relative;
      overflow: hidden;
      min-height: 200px;
      animation: cardIn .4s ease both;
    }
    @keyframes cardIn {
      from { opacity:0; transform: scale(.95); }
      to   { opacity:1; transform: scale(1); }
    }
    .serving-card::before {
      content: '';
      position: absolute;
      inset: 0;
      background: radial-gradient(circle at 60% 20%, rgba(0,201,167,.15) 0%, transparent 65%);
    }
    .glow-ring {
      position: absolute;
      width: 160px; height: 160px;
      border-radius: 50%;
      border: 2px solid rgba(0,201,167,.15);
      animation: ring 3s ease-in-out infinite;
    }
    @keyframes ring {
      0%,100% { transform: scale(1); opacity:.6; }
      50%      { transform: scale(1.1); opacity:.2; }
    }
    .serving-svc {
      font-size: .72rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .12em;
      color: var(--accent);
      position: relative;
      z-index: 1;
      margin-bottom: .5rem;
    }
    .serving-num {
      font-family: 'Bebas Neue', sans-serif;
      font-size: 5.5rem;
      line-height: 1;
      color: #fff;
      letter-spacing: .05em;
      position: relative;
      z-index: 1;
      text-shadow: 0 0 40px rgba(0,201,167,.4);
    }
    .serving-label {
      font-size: .75rem;
      color: rgba(255,255,255,.45);
      position: relative;
      z-index: 1;
      margin-top: .4rem;
    }
    .empty-serving {
      grid-column: 1 / -1;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 3rem;
      color: rgba(255,255,255,.2);
      gap: 1rem;
    }
    .empty-serving i { font-size: 3rem; }

    /* ===== WAITING SIDEBAR ===== */
    .queue-sidebar {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 20px;
      padding: 1.25rem;
      display: flex;
      flex-direction: column;
      gap: 1rem;
      overflow: hidden;
    }
    .queue-list {
      flex: 1;
      overflow-y: auto;
      display: flex;
      flex-direction: column;
      gap: .5rem;
    }
    .queue-list::-webkit-scrollbar { width: 4px; }
    .queue-list::-webkit-scrollbar-track { background: transparent; }
    .queue-list::-webkit-scrollbar-thumb { background: var(--border); border-radius: 2px; }
    .queue-item {
      display: flex;
      align-items: center;
      gap: .75rem;
      padding: .65rem .85rem;
      background: rgba(255,255,255,.04);
      border-radius: 10px;
      border: 1px solid transparent;
      animation: itemIn .3s ease both;
    }
    @keyframes itemIn { from { opacity:0; transform:translateX(10px); } to { opacity:1; transform:translateX(0); } }
    .queue-item.called {
      background: rgba(245,158,11,.1);
      border-color: rgba(245,158,11,.3);
    }
    .queue-num {
      font-family: 'Bebas Neue', sans-serif;
      font-size: 1.4rem;
      color: rgba(255,255,255,.8);
      min-width: 54px;
    }
    .queue-svc {
      font-size: .72rem;
      color: rgba(255,255,255,.4);
      flex: 1;
    }
    .queue-badge {
      font-size: .62rem;
      font-weight: 700;
      padding: .15rem .45rem;
      border-radius: 20px;
      background: rgba(245,158,11,.2);
      color: #fbbf24;
    }

    /* ===== STATS BAR ===== */
    .stats-bar {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: .75rem;
    }
    .stat-pill {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: .7rem 1rem;
      display: flex;
      align-items: center;
      gap: .6rem;
    }
    .stat-pill i { font-size: 1rem; }
    .stat-pill .val {
      font-size: 1.1rem;
      font-weight: 800;
      line-height: 1;
    }
    .stat-pill .lbl {
      font-size: .65rem;
      color: rgba(255,255,255,.4);
      text-transform: uppercase;
      letter-spacing: .05em;
    }

    /* ===== MARQUEE ===== */
    .marquee-bar {
      background: var(--brand);
      border-radius: 10px;
      padding: .5rem 1rem;
      overflow: hidden;
      white-space: nowrap;
    }
    .marquee-inner {
      display: inline-block;
      animation: marquee 25s linear infinite;
      font-size: .82rem;
      font-weight: 600;
    }
    @keyframes marquee {
      0%   { transform: translateX(0); }
      100% { transform: translateX(-50%); }
    }

    /* ===== BRANCH SWITCHER ===== */
    .branch-switcher {
      position: fixed;
      bottom: 1rem;
      right: 1rem;
      display: flex;
      gap: .5rem;
      z-index: 999;
    }
    .branch-btn {
      background: rgba(0,0,0,.6);
      backdrop-filter: blur(8px);
      border: 1px solid var(--border);
      border-radius: 8px;
      color: rgba(255,255,255,.6);
      font-size: .75rem;
      font-weight: 600;
      padding: .4rem .75rem;
      cursor: pointer;
      font-family: inherit;
      transition: all .15s;
      text-decoration: none;
    }
    .branch-btn:hover, .branch-btn.active { background: var(--brand); color: #fff; border-color: var(--brand); }

    /* Pulse animation for called tickets */
    @keyframes pulse-border {
      0%, 100% { box-shadow: 0 0 0 0 rgba(245,158,11,.4); }
      50%       { box-shadow: 0 0 0 8px rgba(245,158,11,0); }
    }
    .queue-item.called { animation: pulse-border 2s ease-in-out infinite; }
  </style>
</head>
<body>

<!-- TOPBAR -->
<header class="topbar">
  <div class="logo">
    <div class="logo-icon"><i class="bi bi-ticket-perforated"></i></div>
    <div class="logo-name">Queue<span>Pro</span></div>
  </div>
  <div class="branch-name"><?= e($branch['name'] ?? 'All Branches') ?></div>
  <div class="clock-box">
    <div class="clock" id="clock">--:--:--</div>
    <div class="clock-date" id="dateStr"></div>
  </div>
</header>

<!-- MAIN -->
<div class="main-grid">

  <!-- Left: Now Serving -->
  <div class="serving-section">
    <div class="section-title"><i class="bi bi-megaphone-fill" style="color:var(--accent)"></i> Now Serving</div>
    <div class="serving-cards" id="servingCards">
      <div class="empty-serving">
        <i class="bi bi-pause-circle"></i>
        <span>No active tickets</span>
      </div>
    </div>

    <!-- Stats strip -->
    <div class="stats-bar">
      <div class="stat-pill"><i class="bi bi-ticket-detailed" style="color:var(--brand)"></i>
        <div><div class="val" id="statTotal">0</div><div class="lbl">Total Today</div></div>
      </div>
      <div class="stat-pill"><i class="bi bi-check-circle" style="color:var(--accent)"></i>
        <div><div class="val" id="statDone">0</div><div class="lbl">Completed</div></div>
      </div>
      <div class="stat-pill"><i class="bi bi-hourglass-split" style="color:#f59e0b"></i>
        <div><div class="val" id="statWaiting">0</div><div class="lbl">Waiting</div></div>
      </div>
      <div class="stat-pill"><i class="bi bi-clock" style="color:#f97316"></i>
        <div><div class="val" id="statTime">–</div><div class="lbl">Avg Wait</div></div>
      </div>
    </div>
  </div>

  <!-- Right: Waiting queue -->
  <div class="queue-sidebar">
    <div class="section-title"><i class="bi bi-list-ol" style="color:#f59e0b"></i> Queue</div>
    <div class="queue-list" id="queueList">
      <div style="text-align:center;color:rgba(255,255,255,.25);padding:2rem">Empty</div>
    </div>
  </div>
</div>

<!-- Marquee announcement -->
<div class="marquee-bar">
  <span class="marquee-inner" id="marqueeText">
    🎫 Welcome to <?= e(APP_NAME) ?> &nbsp;•&nbsp;
    Please have your ticket ready when called &nbsp;•&nbsp;
    <?= date('l, d F Y') ?> &nbsp;•&nbsp;
    مرحباً بكم في <?= e(APP_NAME) ?> &nbsp;•&nbsp;
    Ku soo dhowow <?= e(APP_NAME) ?> &nbsp;•&nbsp;
    🎫 Welcome to <?= e(APP_NAME) ?> &nbsp;•&nbsp;
    Please have your ticket ready when called &nbsp;•&nbsp;
    <?= date('l, d F Y') ?> &nbsp;•&nbsp;
    مرحباً بكم في <?= e(APP_NAME) ?> &nbsp;•&nbsp;
    Ku soo dhowow <?= e(APP_NAME) ?>
  </span>
</div>

<!-- Branch switcher (bottom-right) -->
<div class="branch-switcher">
  <?php foreach ($branches as $b): ?>
  <a href="?branch=<?= $b['id'] ?>"
     class="branch-btn <?= $b['id'] == $branchId ? 'active' : '' ?>">
    <?= e($b['name']) ?>
  </a>
  <?php endforeach; ?>
  <a href="<?= APP_URL ?>/admin/dashboard.php" class="branch-btn">
    <i class="bi bi-arrow-left"></i> Admin
  </a>
</div>

<!-- Notification sound (optional) -->
<audio id="callSound" preload="auto">
  <source src="data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAA" type="audio/wav">
</audio>

<script>
const BRANCH_ID = <?= $branchId ?>;
let prevServing = [];

// Clock
function updateClock() {
  const now = new Date();
  document.getElementById('clock').textContent = now.toLocaleTimeString('en-GB');
  document.getElementById('dateStr').textContent = now.toLocaleDateString('en-GB', {
    weekday:'long', day:'numeric', month:'long', year:'numeric'
  });
}
setInterval(updateClock, 1000);
updateClock();

// Fetch and render queue data
async function fetchQueue() {
  try {
    const res  = await fetch(`<?= APP_URL ?>/api/queue-status.php?branch_id=${BRANCH_ID}`);
    const data = await res.json();

    renderServing(data.serving);
    renderWaiting(data.waiting);
    renderStats(data.stats);
  } catch(e) {
    console.warn('Queue fetch error:', e);
  }
}

function renderServing(tickets) {
  const container = document.getElementById('servingCards');

  // Check if a new ticket was called (play sound)
  const newIds = tickets.map(t => t.ticket_number);
  const oldIds = prevServing.map(t => t.ticket_number);
  const hasNew = newIds.some(id => !oldIds.includes(id));
  if (hasNew && prevServing.length > 0) {
    playCallSound();
  }
  prevServing = [...tickets];

  if (!tickets.length) {
    container.innerHTML = `
      <div class="empty-serving">
        <i class="bi bi-pause-circle"></i>
        <span>No active tickets right now</span>
      </div>`;
    return;
  }

  container.innerHTML = tickets.map(t => `
    <div class="serving-card">
      <div class="glow-ring"></div>
      <div class="serving-svc">${escHtml(t.service_name)}</div>
      <div class="serving-num">${escHtml(t.ticket_number)}</div>
      <div class="serving-label">
        <i class="bi bi-arrow-right-circle"></i>
        ${t.status === 'in_progress' ? 'Being Served' : 'Please Come Forward'}
      </div>
    </div>
  `).join('');
}

function renderWaiting(tickets) {
  const list = document.getElementById('queueList');
  if (!tickets.length) {
    list.innerHTML = '<div style="text-align:center;color:rgba(255,255,255,.25);padding:2rem;font-size:.85rem">Queue is empty</div>';
    return;
  }
  list.innerHTML = tickets.slice(0, 25).map((t, i) => `
    <div class="queue-item ${t.status === 'called' ? 'called' : ''}" style="animation-delay:${i*0.03}s">
      <div class="queue-num">${escHtml(t.ticket_number)}</div>
      <div class="queue-svc">${escHtml(t.service_name)}</div>
      ${t.status === 'called' ? '<span class="queue-badge">CALLED</span>' : ''}
    </div>
  `).join('');
}

function renderStats(stats) {
  document.getElementById('statTotal').textContent   = stats.total_today   ?? 0;
  document.getElementById('statDone').textContent    = stats.completed_today ?? 0;
  document.getElementById('statWaiting').textContent = stats.waiting_count  ?? 0;
}

function escHtml(str) {
  return String(str)
    .replace(/&/g,'&amp;').replace(/</g,'&lt;')
    .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function playCallSound() {
  // Create a simple beep via Web Audio API
  try {
    const ctx = new (window.AudioContext || window.webkitAudioContext)();
    const osc = ctx.createOscillator();
    const gain = ctx.createGain();
    osc.connect(gain);
    gain.connect(ctx.destination);
    osc.frequency.value = 880;
    osc.type = 'sine';
    gain.gain.setValueAtTime(0.3, ctx.currentTime);
    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.4);
    osc.start(ctx.currentTime);
    osc.stop(ctx.currentTime + 0.4);
  } catch(e) {}
}

// Initial fetch + polling
fetchQueue();
setInterval(fetchQueue, 5000);
</script>
</body>
</html>
