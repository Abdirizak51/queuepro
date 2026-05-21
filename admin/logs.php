<?php
// admin/logs.php
require_once __DIR__ . '/../bootstrap.php';
requireRole(ROLE_ADMIN);

$pdo = db();
$pageTitle = 'Activity Logs';

$search = trim($_GET['search'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));

$where  = "WHERE 1";
$params = [];
if ($search) {
    $where .= " AND (l.action LIKE :s OR l.description LIKE :s2 OR u.full_name LIKE :s3 OR l.ip_address LIKE :s4)";
    $params[':s'] = $params[':s2'] = $params[':s3'] = $params[':s4'] = "%$search%";
}

$paged = paginate(
    "SELECT l.*, COALESCE(u.full_name,'System') AS user_name, u.email AS user_email
     FROM activity_logs l
     LEFT JOIN users u ON u.id=l.user_id
     $where ORDER BY l.created_at DESC",
    $params, $page
);

$actionIcons = [
    'login'          => ['bi-box-arrow-in-right','#3b82f6'],
    'logout'         => ['bi-box-arrow-right',   '#6b7280'],
    'login_failed'   => ['bi-exclamation-triangle','#ef4444'],
    'register'       => ['bi-person-plus',        '#10b981'],
    'user_create'    => ['bi-person-plus-fill',   '#8b5cf6'],
    'user_update'    => ['bi-person-gear',         '#f59e0b'],
    'user_delete'    => ['bi-person-dash-fill',   '#ef4444'],
    'ticket_take'    => ['bi-ticket-perforated',  '#3b82f6'],
    'ticket_called'  => ['bi-megaphone-fill',     '#f97316'],
    'ticket_status_update' => ['bi-arrow-repeat', '#6b7280'],
];

require_once __DIR__ . '/../views/partials/header.php';
?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem">
  <div>
    <h1 style="font-size:1.4rem;font-weight:800">Activity Logs</h1>
    <p style="color:var(--muted);font-size:.88rem"><?= number_format($paged['total']) ?> entries</p>
  </div>
</div>

<!-- Search -->
<div class="panel" style="margin-bottom:1.25rem">
  <div class="panel-body" style="padding:.85rem 1.25rem">
    <form method="GET" style="display:flex;gap:.75rem;align-items:center">
      <div style="flex:1;position:relative">
        <i class="bi bi-search" style="position:absolute;left:.75rem;top:50%;transform:translateY(-50%);color:var(--muted)"></i>
        <input type="text" name="search" class="form-control" style="padding-left:2.3rem"
               placeholder="Search action, description, IP…" value="<?= e($search) ?>">
      </div>
      <button type="submit" class="btn-brand"><i class="bi bi-search"></i> Search</button>
      <?php if ($search): ?><a href="logs.php" class="btn-ghost"><i class="bi bi-x"></i> Clear</a><?php endif; ?>
    </form>
  </div>
</div>

<div class="panel">
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Action</th><th>Description</th><th>User</th><th>IP Address</th><th>Time</th></tr>
      </thead>
      <tbody>
        <?php foreach ($paged['data'] as $log): ?>
        <?php [$ico, $clr] = $actionIcons[$log['action']] ?? ['bi-activity','#6b7280']; ?>
        <tr>
          <td>
            <span style="display:inline-flex;align-items:center;gap:.5rem">
              <span style="width:28px;height:28px;border-radius:7px;background:<?= $clr ?>22;
                           display:grid;place-items:center;font-size:.85rem;color:<?= $clr ?>">
                <i class="bi <?= $ico ?>"></i>
              </span>
              <code style="font-size:.8rem;color:var(--muted)"><?= e($log['action']) ?></code>
            </span>
          </td>
          <td style="max-width:280px;font-size:.85rem"><?= e($log['description']) ?></td>
          <td>
            <div style="font-size:.85rem;font-weight:600"><?= e($log['user_name']) ?></div>
            <?php if ($log['user_email']): ?>
            <div style="font-size:.75rem;color:var(--muted)"><?= e($log['user_email']) ?></div>
            <?php endif; ?>
          </td>
          <td><code style="font-size:.8rem"><?= e($log['ip_address']) ?></code></td>
          <td style="font-size:.78rem;color:var(--muted);white-space:nowrap">
            <?= date('d M Y', strtotime($log['created_at'])) ?><br>
            <?= date('H:i:s', strtotime($log['created_at'])) ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($paged['data'])): ?>
        <tr><td colspan="5" style="text-align:center;color:var(--muted);padding:3rem">No logs found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($paged['last_page'] > 1): ?>
  <div style="padding:1rem 1.25rem;border-top:1px solid var(--border);display:flex;gap:.5rem;justify-content:center">
    <?php for ($i=1;$i<=$paged['last_page'];$i++): ?>
    <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>"
       style="min-width:36px;height:36px;border-radius:8px;display:grid;place-items:center;
              font-size:.85rem;font-weight:600;text-decoration:none;
              <?= $i==$page?'background:var(--brand);color:#fff':'border:1px solid var(--border);color:var(--text)' ?>">
      <?= $i ?>
    </a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../views/partials/footer.php'; ?>
