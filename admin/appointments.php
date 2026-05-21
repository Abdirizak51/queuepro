<?php
// admin/appointments.php
require_once __DIR__ . '/../bootstrap.php';
requireRole([ROLE_ADMIN, ROLE_STAFF]);

$pdo       = db();
$pageTitle = 'Appointments';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);

    if ($action === 'update_status' && $id) {
        $newSt = $_POST['status'] ?? '';
        $valid = ['pending','confirmed','cancelled','completed','rescheduled','no_show'];
        if (!in_array($newSt, $valid)) { flash('error','Invalid status.'); redirect('appointments.php'); }
        $pdo->prepare("UPDATE appointments SET status=? WHERE id=?")->execute([$newSt, $id]);
        flash('success','Appointment updated.');
        redirect('appointments.php');
    }

    if ($action === 'delete' && $id) {
        $pdo->prepare("DELETE FROM appointments WHERE id=?")->execute([$id]);
        flash('success','Appointment deleted.');
        redirect('appointments.php');
    }
}

$date   = $_GET['date']    ?? date('Y-m-d');
$search = trim($_GET['search'] ?? '');
$status = $_GET['status']  ?? '';
$page   = max(1, (int)($_GET['page'] ?? 1));

$where  = "WHERE 1";
$params = [];
if ($date)   { $where .= " AND a.appointment_date = :d";  $params[':d']  = $date; }
if ($status) { $where .= " AND a.status = :st";            $params[':st'] = $status; }
if ($search) {
    $where .= " AND (u.full_name LIKE :s OR s.name LIKE :s2)";
    $params[':s'] = $params[':s2'] = "%$search%";
}

$paged = paginate(
    "SELECT a.*, s.name AS service_name, u.full_name AS customer_name,
            st.full_name AS staff_name
     FROM appointments a
     JOIN services s ON s.id=a.service_id
     JOIN users u ON u.id=a.user_id
     LEFT JOIN users st ON st.id=a.staff_id
     $where ORDER BY a.appointment_date, a.appointment_time",
    $params, $page
);

require_once __DIR__ . '/../views/partials/header.php';
?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem">
  <div>
    <h1 style="font-size:1.4rem;font-weight:800">Appointments</h1>
    <p style="color:var(--muted);font-size:.88rem"><?= $paged['total'] ?> total</p>
  </div>
</div>

<!-- Filters -->
<div class="panel" style="margin-bottom:1.25rem">
  <div class="panel-body" style="padding:.85rem 1.25rem">
    <form method="GET" style="display:flex;gap:.7rem;flex-wrap:wrap;align-items:center">
      <input type="date" name="date" class="form-control" style="width:160px" value="<?= e($date) ?>">
      <div style="flex:1;min-width:180px;position:relative">
        <i class="bi bi-search" style="position:absolute;left:.75rem;top:50%;transform:translateY(-50%);color:var(--muted)"></i>
        <input type="text" name="search" class="form-control" style="padding-left:2.3rem"
               placeholder="Customer or service" value="<?= e($search) ?>">
      </div>
      <select name="status" class="form-select" style="width:160px">
        <option value="">All Statuses</option>
        <?php foreach (['pending','confirmed','cancelled','completed','rescheduled','no_show'] as $st): ?>
        <option value="<?= $st ?>" <?= $status===$st?'selected':'' ?>><?= ucfirst($st) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn-brand"><i class="bi bi-filter"></i> Filter</button>
      <a href="appointments.php" class="btn-ghost"><i class="bi bi-x"></i> Reset</a>
    </form>
  </div>
</div>

<div class="panel">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>#</th><th>Customer</th><th>Service</th>
          <th>Date & Time</th><th>Duration</th>
          <th>Status</th><th>Staff</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($paged['data'] as $a): ?>
        <tr>
          <td style="font-weight:700;color:var(--brand)">#<?= $a['id'] ?></td>
          <td><?= e($a['customer_name']) ?></td>
          <td><?= e($a['service_name']) ?></td>
          <td>
            <div style="font-weight:600"><?= date('d M Y', strtotime($a['appointment_date'])) ?></div>
            <div style="font-size:.8rem;color:var(--muted)"><?= date('H:i', strtotime($a['appointment_time'])) ?></div>
          </td>
          <td><?= $a['duration_minutes'] ?> min</td>
          <td><?= statusBadge($a['status']) ?></td>
          <td style="font-size:.82rem"><?= e($a['staff_name'] ?? '–') ?></td>
          <td>
            <div style="display:flex;gap:.4rem">
              <button class="btn-ghost" style="padding:.3rem .6rem;font-size:.8rem"
                      onclick="openStatus(<?= $a['id'] ?>,'<?= $a['status'] ?>')">
                <i class="bi bi-pencil-square"></i>
              </button>
              <form method="POST" onsubmit="return confirm('Delete appointment?')">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $a['id'] ?>">
                <button type="submit" class="btn-danger" style="padding:.3rem .6rem;font-size:.8rem">
                  <i class="bi bi-trash"></i>
                </button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($paged['data'])): ?>
        <tr><td colspan="8" style="text-align:center;color:var(--muted);padding:3rem">No appointments found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($paged['last_page'] > 1): ?>
  <div style="padding:1rem 1.25rem;border-top:1px solid var(--border);display:flex;gap:.5rem;justify-content:center">
    <?php for ($i=1;$i<=$paged['last_page'];$i++): ?>
    <a href="?page=<?= $i ?>&date=<?= $date ?>&search=<?= urlencode($search) ?>&status=<?= $status ?>"
       style="min-width:36px;height:36px;border-radius:8px;display:grid;place-items:center;
              font-size:.85rem;font-weight:600;text-decoration:none;
              <?= $i==$page?'background:var(--brand);color:#fff':'border:1px solid var(--border);color:var(--text)' ?>">
      <?= $i ?>
    </a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>

<!-- Status update modal -->
<div class="modal fade" id="statusModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">Update Status</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="update_status">
        <input type="hidden" name="id" id="stApptId">
        <div class="modal-body">
          <select name="status" id="stSelect" class="form-select">
            <?php foreach (['pending','confirmed','cancelled','completed','rescheduled','no_show'] as $st): ?>
            <option value="<?= $st ?>"><?= ucfirst($st) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn-brand">Update</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php
$extraJs = '
<script>
function openStatus(id, cur) {
  document.getElementById("stApptId").value = id;
  document.getElementById("stSelect").value = cur;
  new bootstrap.Modal(document.getElementById("statusModal")).show();
}
</script>';
require_once __DIR__ . '/../views/partials/footer.php';
?>
