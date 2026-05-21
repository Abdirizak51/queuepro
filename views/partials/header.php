<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= isset($pageTitle) ? e($pageTitle) . ' – ' : '' ?><?= APP_NAME ?></title>

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">

  <!-- Icons & Bootstrap -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>

  <style>
    /* ==================== DESIGN TOKENS ==================== */
    :root {
      --brand:      #1B4FD8;
      --brand-dk:   #1239a3;
      --brand-lt:   #dbeafe;
      --accent:     #00C9A7;
      --danger:     #ef4444;
      --warning:    #f59e0b;
      --success:    #10b981;
      --bg:         #F0F4FD;
      --surface:    #FFFFFF;
      --sidebar-bg: #0A0F1E;
      --sidebar-w:  260px;
      --text:       #1e293b;
      --muted:      #64748b;
      --border:     #e2e8f0;
      --radius:     14px;
      --shadow:     0 1px 3px rgba(0,0,0,.08), 0 4px 16px rgba(0,0,0,.04);
    }
    [data-theme="dark"] {
      --bg: #0D1117; --surface:#161B22; --text:#e6edf3;
      --muted:#8b949e; --border:#30363d;
    }

    /* ==================== RESET ==================== */
    *, *::before, *::after { box-sizing: border-box; }
    body {
      font-family: 'Plus Jakarta Sans', sans-serif;
      background: var(--bg);
      color: var(--text);
      margin: 0;
      display: flex;
    }

    /* ==================== SIDEBAR ==================== */
    .sidebar {
      position: fixed;
      top: 0; left: 0;
      width: var(--sidebar-w);
      height: 100vh;
      background: var(--sidebar-bg);
      display: flex;
      flex-direction: column;
      overflow-y: auto;
      z-index: 1000;
      transition: transform .3s ease;
    }
    .sidebar-brand {
      padding: 1.5rem 1.25rem 1rem;
      border-bottom: 1px solid rgba(255,255,255,.06);
      display: flex;
      align-items: center;
      gap: .7rem;
      text-decoration: none;
    }
    .brand-icon {
      width: 38px; height: 38px;
      background: var(--brand);
      border-radius: 10px;
      display: grid;
      place-items: center;
      color: #fff;
      font-size: 1.2rem;
      flex-shrink: 0;
    }
    .brand-name {
      font-size: 1.2rem;
      font-weight: 800;
      color: #fff;
      letter-spacing: -.01em;
    }
    .brand-name span { color: var(--accent); }

    .sidebar-nav { flex: 1; padding: 1rem 0; }
    .nav-section {
      padding: .5rem 1.25rem .25rem;
      font-size: .7rem;
      font-weight: 700;
      color: rgba(255,255,255,.3);
      text-transform: uppercase;
      letter-spacing: .1em;
    }
    .nav-link {
      display: flex;
      align-items: center;
      gap: .75rem;
      padding: .65rem 1.25rem;
      color: rgba(255,255,255,.55);
      text-decoration: none;
      font-size: .875rem;
      font-weight: 500;
      border-radius: 0;
      transition: color .15s, background .15s;
      margin: .05rem .65rem;
      border-radius: 10px;
    }
    .nav-link i { font-size: 1.05rem; width: 20px; text-align: center; }
    .nav-link:hover {
      color: #fff;
      background: rgba(255,255,255,.07);
    }
    .nav-link.active {
      color: #fff;
      background: var(--brand);
    }
    .nav-badge {
      margin-left: auto;
      background: var(--accent);
      color: #000;
      font-size: .65rem;
      font-weight: 800;
      border-radius: 20px;
      padding: .1rem .45rem;
    }
    .sidebar-footer {
      padding: 1rem 1.25rem;
      border-top: 1px solid rgba(255,255,255,.06);
    }
    .sidebar-user {
      display: flex;
      align-items: center;
      gap: .7rem;
      text-decoration: none;
    }
    .user-avatar {
      width: 36px; height: 36px;
      border-radius: 50%;
      background: var(--brand);
      display: grid;
      place-items: center;
      color: #fff;
      font-weight: 700;
      font-size: .85rem;
      flex-shrink: 0;
    }
    .user-info .name {
      font-size: .85rem;
      font-weight: 600;
      color: #fff;
    }
    .user-info .role {
      font-size: .72rem;
      color: rgba(255,255,255,.4);
    }

    /* ==================== MAIN CONTENT ==================== */
    .main-wrap {
      margin-left: var(--sidebar-w);
      flex: 1;
      display: flex;
      flex-direction: column;
      min-height: 100vh;
    }

    /* ==================== TOPBAR ==================== */
    .topbar {
      position: sticky;
      top: 0;
      background: var(--surface);
      border-bottom: 1px solid var(--border);
      padding: .75rem 2rem;
      display: flex;
      align-items: center;
      gap: 1rem;
      z-index: 900;
    }
    .topbar .page-title {
      font-size: 1.05rem;
      font-weight: 700;
      flex: 1;
    }
    .topbar-actions {
      display: flex;
      align-items: center;
      gap: .75rem;
    }
    .icon-btn {
      width: 38px; height: 38px;
      border-radius: 10px;
      border: 1px solid var(--border);
      background: none;
      color: var(--muted);
      display: grid;
      place-items: center;
      font-size: 1rem;
      cursor: pointer;
      position: relative;
      transition: border-color .15s, color .15s;
      text-decoration: none;
    }
    .icon-btn:hover { border-color: var(--brand); color: var(--brand); }
    .badge-dot {
      position: absolute;
      top: 6px; right: 6px;
      width: 8px; height: 8px;
      background: var(--danger);
      border-radius: 50%;
      border: 2px solid var(--surface);
    }
    .hamburger {
      display: none;
      width: 38px; height: 38px;
      border: 1px solid var(--border);
      border-radius: 10px;
      background: none;
      cursor: pointer;
      color: var(--text);
      font-size: 1.1rem;
      place-items: center;
    }

    /* ==================== CONTENT AREA ==================== */
    .content {
      flex: 1;
      padding: 1.75rem 2rem;
    }

    /* ==================== STAT CARDS ==================== */
    .stat-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
      gap: 1.25rem;
      margin-bottom: 1.75rem;
    }
    .stat-card {
      background: var(--surface);
      border-radius: var(--radius);
      padding: 1.4rem 1.5rem;
      box-shadow: var(--shadow);
      display: flex;
      align-items: center;
      gap: 1rem;
      border: 1px solid var(--border);
    }
    .stat-icon {
      width: 52px; height: 52px;
      border-radius: 14px;
      display: grid;
      place-items: center;
      font-size: 1.4rem;
      flex-shrink: 0;
    }
    .stat-text .value {
      font-size: 1.75rem;
      font-weight: 800;
      line-height: 1;
    }
    .stat-text .label {
      font-size: .8rem;
      color: var(--muted);
      margin-top: .25rem;
    }
    .stat-text .change {
      font-size: .75rem;
      font-weight: 600;
      margin-top: .2rem;
    }
    .change.up   { color: var(--success); }
    .change.down { color: var(--danger); }

    /* ==================== PANEL / CARD ==================== */
    .panel {
      background: var(--surface);
      border-radius: var(--radius);
      border: 1px solid var(--border);
      box-shadow: var(--shadow);
      margin-bottom: 1.5rem;
    }
    .panel-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 1.1rem 1.5rem;
      border-bottom: 1px solid var(--border);
    }
    .panel-title {
      font-size: .95rem;
      font-weight: 700;
    }
    .panel-body { padding: 1.5rem; }

    /* ==================== TABLES ==================== */
    .table-wrap { overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; font-size: .875rem; }
    th {
      background: var(--bg);
      font-size: .73rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .05em;
      color: var(--muted);
      padding: .75rem 1rem;
      text-align: left;
      white-space: nowrap;
    }
    td {
      padding: .85rem 1rem;
      border-top: 1px solid var(--border);
      vertical-align: middle;
    }
    tr:hover td { background: rgba(27,79,216,.03); }

    /* ==================== FORMS ==================== */
    .form-label {
      font-size: .78rem;
      font-weight: 600;
      color: var(--muted);
      text-transform: uppercase;
      letter-spacing: .05em;
      margin-bottom: .4rem;
    }
    .form-control, .form-select {
      font-family: inherit;
      font-size: .9rem;
      border: 1.5px solid var(--border);
      border-radius: 10px;
      padding: .65rem .9rem;
      transition: border-color .2s, box-shadow .2s;
    }
    .form-control:focus, .form-select:focus {
      border-color: var(--brand);
      box-shadow: 0 0 0 3px rgba(27,79,216,.12);
    }

    /* ==================== BUTTONS ==================== */
    .btn-brand {
      background: var(--brand);
      color: #fff;
      border: none;
      border-radius: 10px;
      font-family: inherit;
      font-weight: 600;
      font-size: .875rem;
      padding: .55rem 1.1rem;
      cursor: pointer;
      transition: background .2s;
      display: inline-flex;
      align-items: center;
      gap: .4rem;
      text-decoration: none;
    }
    .btn-brand:hover { background: var(--brand-dk); color: #fff; }
    .btn-ghost {
      background: none;
      border: 1.5px solid var(--border);
      border-radius: 10px;
      font-family: inherit;
      font-weight: 600;
      font-size: .875rem;
      padding: .5rem 1rem;
      cursor: pointer;
      color: var(--text);
      transition: border-color .2s;
      display: inline-flex;
      align-items: center;
      gap: .4rem;
      text-decoration: none;
    }
    .btn-ghost:hover { border-color: var(--brand); color: var(--brand); }
    .btn-danger { background: var(--danger); color: #fff; border: none; border-radius: 10px;
                  font-family:inherit;font-weight:600;font-size:.875rem;padding:.5rem 1rem;
                  cursor:pointer; }

    /* ==================== BADGES ==================== */
    .badge { font-size: .72rem; font-weight: 600; padding: .3rem .65rem;
             border-radius: 20px; white-space: nowrap; }

    /* ==================== FLASH ALERTS ==================== */
    .flash-alert {
      display: flex;
      align-items: center;
      gap: .6rem;
      padding: .85rem 1.1rem;
      border-radius: 10px;
      font-size: .9rem;
      margin-bottom: 1.25rem;
    }
    .flash-success { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; }
    .flash-error   { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
    .flash-info    { background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; }

    /* ==================== MODAL ==================== */
    .modal-content {
      border: 1px solid var(--border);
      border-radius: var(--radius);
      font-family: inherit;
    }
    .modal-header { border-color: var(--border); }
    .modal-footer { border-color: var(--border); }

    /* ==================== RESPONSIVE ==================== */
    @media (max-width: 768px) {
      .sidebar { transform: translateX(-100%); }
      .sidebar.open { transform: translateX(0); }
      .main-wrap { margin-left: 0; }
      .hamburger { display: grid; }
      .content { padding: 1rem; }
    }
  </style>
</head>
<body>

<!-- ============ SIDEBAR ============ -->
<aside class="sidebar" id="sidebar">
  <a href="<?= APP_URL ?>/admin/dashboard.php" class="sidebar-brand">
    <div class="brand-icon"><i class="bi bi-ticket-perforated"></i></div>
    <span class="brand-name">Ballan<span>HUB</span></span>
  </a>

  <nav class="sidebar-nav">
    <?php $role = $_SESSION['role_id'] ?? 3; ?>

    <?php if ($role == ROLE_ADMIN): ?>
    <div class="nav-section">Main</div>
    <a href="<?= APP_URL ?>/admin/dashboard.php"   class="nav-link <?= isActive('dashboard') ?>">
      <i class="bi bi-grid-1x2-fill"></i> Dashboard
    </a>

    <div class="nav-section">People</div>
    <a href="<?= APP_URL ?>/admin/users.php"        class="nav-link <?= isActive('users') ?>">
      <i class="bi bi-people-fill"></i> Users
    </a>
    <a href="<?= APP_URL ?>/admin/branches.php"     class="nav-link <?= isActive('branches') ?>">
      <i class="bi bi-building"></i> Branches
    </a>

    <div class="nav-section">Operations</div>
    <a href="<?= APP_URL ?>/admin/services.php"     class="nav-link <?= isActive('services') ?>">
      <i class="bi bi-box-seam"></i> Services
    </a>
    <a href="<?= APP_URL ?>/admin/tickets.php"      class="nav-link <?= isActive('tickets') ?>">
      <i class="bi bi-ticket-detailed"></i> Tickets
      <?php $wt = db()->query("SELECT COUNT(*) FROM tickets WHERE status='waiting' AND DATE(created_at)=CURDATE()")->fetchColumn(); ?>
      <?php if ($wt > 0): ?><span class="nav-badge"><?= $wt ?></span><?php endif; ?>
    </a>
    <a href="<?= APP_URL ?>/admin/appointments.php" class="nav-link <?= isActive('appointments') ?>">
      <i class="bi bi-calendar-check"></i> Appointments
    </a>
    <a href="<?= APP_URL ?>/admin/queue-display.php"class="nav-link <?= isActive('queue-display') ?>">
      <i class="bi bi-display"></i> Queue Display
    </a>

    <div class="nav-section">System</div>
    <a href="<?= APP_URL ?>/admin/reports.php"      class="nav-link <?= isActive('reports') ?>">
      <i class="bi bi-bar-chart-fill"></i> Reports
    </a>
    <a href="<?= APP_URL ?>/admin/settings.php"     class="nav-link <?= isActive('settings') ?>">
      <i class="bi bi-gear-fill"></i> Settings
    </a>
    <a href="<?= APP_URL ?>/admin/logs.php"         class="nav-link <?= isActive('logs') ?>">
      <i class="bi bi-journal-text"></i> Activity Logs
    </a>

    <?php elseif ($role == ROLE_STAFF): ?>
    <div class="nav-section">Main</div>
    <a href="<?= APP_URL ?>/staff/dashboard.php"     class="nav-link <?= isActive('dashboard') ?>">
      <i class="bi bi-grid-1x2-fill"></i> Dashboard
    </a>
    <a href="<?= APP_URL ?>/staff/queue.php"          class="nav-link <?= isActive('queue') ?>">
      <i class="bi bi-ticket-detailed"></i> Queue Management
    </a>
    <a href="<?= APP_URL ?>/staff/appointments.php"   class="nav-link <?= isActive('appointments') ?>">
      <i class="bi bi-calendar-check"></i> Appointments
    </a>

    <?php else: ?>
    <div class="nav-section">Main</div>
    <a href="<?= APP_URL ?>/customer/dashboard.php"   class="nav-link <?= isActive('dashboard') ?>">
      <i class="bi bi-grid-1x2-fill"></i> My Dashboard
    </a>
    <a href="<?= APP_URL ?>/customer/take-ticket.php" class="nav-link <?= isActive('take-ticket') ?>">
      <i class="bi bi-ticket-perforated"></i> Take Ticket
    </a>
    <a href="<?= APP_URL ?>/customer/appointments.php"class="nav-link <?= isActive('appointments') ?>">
      <i class="bi bi-calendar-plus"></i> Book Appointment
    </a>
    <a href="<?= APP_URL ?>/customer/my-tickets.php"  class="nav-link <?= isActive('my-tickets') ?>">
      <i class="bi bi-collection"></i> My Tickets
    </a>
    <?php endif; ?>

    <!-- common -->
    <div class="nav-section">Account</div>
    <a href="<?= APP_URL ?>/profile.php"             class="nav-link <?= isActive('profile') ?>">
      <i class="bi bi-person-circle"></i> Profile
    </a>
    <a href="<?= APP_URL ?>/notifications.php"       class="nav-link <?= isActive('notifications') ?>">
      <i class="bi bi-bell"></i> Notifications
      <?php $nc = unreadNotificationsCount(); if ($nc): ?>
      <span class="nav-badge"><?= $nc ?></span>
      <?php endif; ?>
    </a>
  </nav>

  <div class="sidebar-footer">
    <div class="sidebar-user">
      <div class="user-avatar"><?= strtoupper(substr($_SESSION['full_name'] ?? 'U', 0, 1)) ?></div>
      <div class="user-info">
        <div class="name"><?= e($_SESSION['full_name'] ?? '') ?></div>
        <div class="role">
          <?= match((int)$_SESSION['role_id']) { 1=>'Administrator', 2=>'Staff', default=>'Customer' } ?>
        </div>
      </div>
    </div>
  </div>
</aside>

<!-- ============ MAIN ============ -->
<div class="main-wrap">

  <!-- TOPBAR -->
  <header class="topbar">
    <button class="hamburger" onclick="toggleSidebar()"><i class="bi bi-list"></i></button>
    <span class="page-title"><?= isset($pageTitle) ? e($pageTitle) : APP_NAME ?></span>
    <div class="topbar-actions">
      <a href="<?= APP_URL ?>/notifications.php" class="icon-btn">
        <i class="bi bi-bell"></i>
        <?php if (unreadNotificationsCount()): ?><span class="badge-dot"></span><?php endif; ?>
      </a>
      <a href="<?= APP_URL ?>/logout.php" class="icon-btn" title="Sign out">
        <i class="bi bi-box-arrow-right"></i>
      </a>
    </div>
  </header>

  <!-- CONTENT -->
  <main class="content">

  <?php
  // Flash message
  $flash = getFlash();
  if ($flash):
    $cls = match($flash['type']) { 'success'=>'flash-success','error'=>'flash-error', default=>'flash-info' };
    $ico = match($flash['type']) { 'success'=>'check-circle', 'error'=>'x-circle', default=>'info-circle' };
  ?>
  <div class="flash-alert <?= $cls ?>">
    <i class="bi bi-<?= $ico ?>"></i>
    <?= e($flash['message']) ?>
  </div>
  <?php endif; ?>

<?php
// Helper: detect active nav item
function isActive(string $page): string {
    return str_contains($_SERVER['PHP_SELF'], $page) ? 'active' : '';
}
?>
