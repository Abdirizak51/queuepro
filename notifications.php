<?php
// notifications.php
require_once __DIR__ . '/bootstrap.php';
requireAuth();

$pdo = db();
$uid = $_SESSION['user_id'];
$pageTitle = 'Notifications';

// Mark all read
if (isset($_GET['mark_read'])) {
    $pdo->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$uid]);
    redirect(APP_URL . '/notifications.php');
}

// Delete one
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    verifyCsrf();
    $nid = (int)$_POST['nid'];
    $pdo->prepare("DELETE FROM notifications WHERE id=? AND user_id=?")->execute([$nid, $uid]);
    redirect(APP_URL . '/notifications.php');
}

$page  = max(1, (int)($_GET['page'] ?? 1));
$paged = paginate(
    "SELECT * FROM notifications WHERE user_id=:uid ORDER BY created_at DESC",
    [':uid' => $uid], $page, 20
);

require_once __DIR__ . '/views/partials/header.php';
?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem">
  <div>
    <h1 style="font-size:1.4rem;font-weight:800">Notifications</h1>
    <p style="color:var(--muted);font-size:.88rem"><?= $paged['total'] ?> total</p>
  </div>
  <?php if ($paged['total']): ?>
  <a href="?mark_read=1" class="btn-ghost">
    <i class="bi bi-check-all"></i> Mark All Read
  </a>
  <?php endif; ?>
</div>

<?php if (empty($paged['data'])): ?>
<div style="text-align:center;padding:5rem;color:var(--muted)">
  <i class="bi bi-bell-slash" style="font-size:3.5rem;display:block;margin-bottom:1rem;opacity:.4"></i>
  No notifications yet.
</div>
<?php else: ?>
<div style="display:flex;flex-direction:column;gap:.75rem">
  <?php foreach ($paged['data'] as $n): ?>
  <div style="background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);
              padding:1.1rem 1.25rem;display:flex;align-items:flex-start;gap:1rem;
              <?= $n['is_read'] ? '' : 'border-left:3px solid var(--brand)' ?>">

    <?php
    $iconMap = [
      'ticket_issued' => ['bi-ticket-perforated-fill','#3b82f6'],
      'ticket_called' => ['bi-megaphone-fill','#f97316'],
      'ticket_completed' => ['bi-check-circle-fill','#10b981'],
      'appointment_booked' => ['bi-calendar-check-fill','#8b5cf6'],
    ];
    [$ico, $clr] = $iconMap[$n['type']] ?? ['bi-bell-fill','#6b7280'];
    ?>
    <div style="width:40px;height:40px;border-radius:10px;background:<?= $clr ?>22;
                display:grid;place-items:center;font-size:1.1rem;color:<?= $clr ?>;flex-shrink:0">
      <i class="bi <?= $ico ?>"></i>
    </div>

    <div style="flex:1">
      <div style="font-weight:700;font-size:.9rem;margin-bottom:.2rem">
        <?= e($n['title']) ?>
        <?php if (!$n['is_read']): ?>
        <span style="display:inline-block;width:7px;height:7px;background:var(--brand);
                     border-radius:50%;margin-left:.4rem;vertical-align:middle"></span>
        <?php endif; ?>
      </div>
      <div style="font-size:.85rem;color:var(--muted)"><?= e($n['message']) ?></div>
      <div style="font-size:.75rem;color:var(--muted);margin-top:.3rem">
        <i class="bi bi-clock"></i> <?= timeAgo($n['created_at']) ?>
      </div>
    </div>

    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="nid" value="<?= $n['id'] ?>">
      <button type="submit" style="background:none;border:none;color:var(--muted);cursor:pointer;
                                    padding:.25rem;font-size:.9rem" title="Delete">
        <i class="bi bi-x"></i>
      </button>
    </form>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/views/partials/footer.php'; ?>
