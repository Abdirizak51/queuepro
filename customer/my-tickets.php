<?php
// customer/my-tickets.php
require_once __DIR__ . '/../bootstrap.php';
requireRole(ROLE_CUSTOMER);

$pdo       = db();
$pageTitle = 'My Tickets';
$uid       = $_SESSION['user_id'];

$page   = max(1, (int)($_GET['page'] ?? 1));
$status = $_GET['status'] ?? '';

$where  = "WHERE t.user_id = :uid";
$params = [':uid' => $uid];
if ($status) {
    $where .= " AND t.status = :st";
    $params[':st'] = $status;
}

$paged = paginate(
    "SELECT t.*, s.name AS service_name, s.color, s.icon, b.name AS branch_name
     FROM tickets t
     JOIN services s ON s.id = t.service_id
     JOIN branches b ON b.id = t.branch_id
     $where ORDER BY t.created_at DESC",
    $params, $page
);

require_once __DIR__ . '/../views/partials/header.php';
?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem">
  <div>
    <h1 style="font-size:1.4rem;font-weight:800">My Tickets</h1>
    <p style="color:var(--muted);font-size:.88rem"><?= $paged['total'] ?> total tickets</p>
  </div>
  <a href="take-ticket.php" class="btn-brand">
    <i class="bi bi-ticket-perforated"></i> New Ticket
  </a>
</div>

<!-- Status filter pills -->
<div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1.5rem">
  <?php
  $statuses = ['' => 'All', 'waiting' => 'Waiting', 'in_progress' => 'In Progress',
               'completed' => 'Completed', 'cancelled' => 'Cancelled'];
  foreach ($statuses as $v => $label):
  ?>
  <a href="?status=<?= $v ?>"
     style="padding:.4rem 1rem;border-radius:20px;font-size:.82rem;font-weight:600;text-decoration:none;
            <?= $status===$v ? 'background:var(--brand);color:#fff' : 'background:var(--surface);border:1px solid var(--border);color:var(--muted)' ?>">
    <?= $label ?>
  </a>
  <?php endforeach; ?>
</div>

<?php if (empty($paged['data'])): ?>
<div style="text-align:center;padding:5rem;color:var(--muted)">
  <i class="bi bi-inbox" style="font-size:3.5rem;display:block;margin-bottom:1rem;opacity:.4"></i>
  No tickets found.
</div>
<?php else: ?>
<div style="display:flex;flex-direction:column;gap:.85rem">
  <?php foreach ($paged['data'] as $t): ?>
  <div style="background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);
              padding:1.1rem 1.25rem;display:flex;align-items:center;gap:1rem">

    <!-- Service icon -->
    <div style="width:44px;height:44px;border-radius:10px;background:<?= e($t['color']) ?>22;
                display:grid;place-items:center;font-size:1.2rem;color:<?= e($t['color']) ?>;flex-shrink:0">
      <i class="bi <?= e($t['icon']) ?>"></i>
    </div>

    <!-- Ticket num + service -->
    <div style="flex:1">
      <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:.25rem">
        <span style="font-family:'Bebas Neue',monospace;font-size:1.4rem;color:var(--brand);letter-spacing:.05em">
          <?= e($t['ticket_number']) ?>
        </span>
        <?= statusBadge($t['status']) ?>
      </div>
      <div style="font-size:.8rem;color:var(--muted)">
        <?= e($t['service_name']) ?> &bull; <?= e($t['branch_name']) ?>
      </div>
    </div>

    <!-- Times -->
    <div style="text-align:right;font-size:.78rem;color:var(--muted)">
      <div><i class="bi bi-clock-history"></i> <?= date('d M Y H:i', strtotime($t['created_at'])) ?></div>
      <?php if ($t['completed_at']): ?>
      <div><i class="bi bi-check-circle"></i> Done: <?= date('H:i', strtotime($t['completed_at'])) ?></div>
      <?php endif; ?>
    </div>

    <!-- QR for active -->
    <?php if (in_array($t['status'], ['waiting','called','in_progress'])): ?>
    <div>
      <img src="<?= qrCodeUrl($t['ticket_number']) ?>" width="44" height="44"
           style="border-radius:8px;cursor:pointer;border:1px solid var(--border)"
           onclick="showQr('<?= e($t['ticket_number']) ?>','<?= qrCodeUrl($t['ticket_number']) ?>')"
           alt="QR">
    </div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>

<!-- Pagination -->
<?php if ($paged['last_page'] > 1): ?>
<div style="margin-top:1.5rem;display:flex;gap:.5rem;justify-content:center">
  <?php for ($i=1;$i<=$paged['last_page'];$i++): ?>
  <a href="?page=<?= $i ?>&status=<?= $status ?>"
     style="min-width:36px;height:36px;border-radius:8px;display:grid;place-items:center;
            font-size:.85rem;font-weight:600;text-decoration:none;
            <?= $i==$page?'background:var(--brand);color:#fff':'border:1px solid var(--border);color:var(--text)' ?>">
    <?= $i ?>
  </a>
  <?php endfor; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<!-- QR Modal -->
<div class="modal fade" id="qrModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content" style="text-align:center">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">Ticket QR Code</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="qrNum" style="font-family:'Bebas Neue',sans-serif;font-size:3.5rem;color:var(--brand);line-height:1"></div>
        <img id="qrImg" src="" alt="QR" style="width:180px;border-radius:12px;margin:1rem auto;display:block">
        <p style="font-size:.82rem;color:var(--muted)">Show this at the counter</p>
      </div>
    </div>
  </div>
</div>

<?php
$extraJs = '
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&display=swap" rel="stylesheet">
<script>
function showQr(num, url) {
  document.getElementById("qrNum").textContent = num;
  document.getElementById("qrImg").src = url;
  new bootstrap.Modal(document.getElementById("qrModal")).show();
}
</script>';
require_once __DIR__ . '/../views/partials/footer.php';
?>
