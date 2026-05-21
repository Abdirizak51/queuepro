<?php
// customer/appointments.php
require_once __DIR__ . '/../bootstrap.php';
requireRole(ROLE_CUSTOMER);

$pdo       = db();
$pageTitle = 'My Appointments';
$uid       = $_SESSION['user_id'];
$branchId  = (int)($_SESSION['branch_id'] ?? 1);

// ---- POST actions ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'book') {
        $svcId = (int)($_POST['service_id']       ?? 0);
        $date  = $_POST['appointment_date']         ?? '';
        $time  = $_POST['appointment_time']         ?? '';
        $notes = trim($_POST['notes']               ?? '');

        $errors = [];
        if (!$svcId)                              $errors[] = 'Select a service.';
        if (!$date || $date < date('Y-m-d'))      $errors[] = 'Pick a future date.';
        if (!$time)                               $errors[] = 'Pick a time.';

        if (empty($errors)) {
            // Check slot availability
            $conflict = $pdo->prepare(
                "SELECT COUNT(*) FROM appointments
                 WHERE service_id=? AND appointment_date=? AND appointment_time=?
                   AND status NOT IN ('cancelled','no_show')"
            );
            $conflict->execute([$svcId, $date, $time]);
            if ((int)$conflict->fetchColumn() > 0) {
                $errors[] = 'This time slot is already booked. Please choose another.';
            }
        }

        if (empty($errors)) {
            $pdo->prepare(
                "INSERT INTO appointments (branch_id,service_id,user_id,appointment_date,appointment_time,notes,status)
                 VALUES (?,?,?,?,?,?,'pending')"
            )->execute([$branchId, $svcId, $uid, $date, $time, $notes]);
            notify($uid, 'appointment_booked', 'Appointment Booked',
                "Your appointment on " . date('d M Y', strtotime($date)) . " at $time has been booked.");
            flash('success', 'Appointment booked successfully!');
        } else {
            flash('error', implode(' ', $errors));
        }
        redirect('appointments.php');
    }

    if ($action === 'cancel') {
        $id     = (int)($_POST['id']     ?? 0);
        $reason = trim($_POST['reason']  ?? '');
        $check  = $pdo->prepare("SELECT id FROM appointments WHERE id=? AND user_id=?");
        $check->execute([$id, $uid]);
        if ($check->fetch()) {
            $pdo->prepare("UPDATE appointments SET status='cancelled',cancel_reason=? WHERE id=?")->execute([$reason, $id]);
            flash('success', 'Appointment cancelled.');
        }
        redirect('appointments.php');
    }

    if ($action === 'reschedule') {
        $id      = (int)($_POST['id']               ?? 0);
        $newDate = $_POST['new_date']                ?? '';
        $newTime = $_POST['new_time']                ?? '';
        $check   = $pdo->prepare("SELECT id FROM appointments WHERE id=? AND user_id=?");
        $check->execute([$id, $uid]);
        if ($check->fetch() && $newDate && $newTime) {
            $pdo->prepare(
                "UPDATE appointments SET appointment_date=?,appointment_time=?,status='rescheduled' WHERE id=?"
            )->execute([$newDate, $newTime, $id]);
            flash('success', 'Appointment rescheduled.');
        }
        redirect('appointments.php');
    }
}

// ---- Fetch appointments ----
$tab   = $_GET['tab']  ?? 'upcoming';
$page  = max(1, (int)($_GET['page'] ?? 1));

$where = match($tab) {
    'past'     => "WHERE a.user_id=:uid AND (a.appointment_date < CURDATE() OR a.status IN ('completed','cancelled'))",
    'all'      => "WHERE a.user_id=:uid",
    default    => "WHERE a.user_id=:uid AND a.appointment_date >= CURDATE() AND a.status NOT IN ('completed','cancelled')",
};
$paged = paginate(
    "SELECT a.*, s.name AS service_name, s.color, s.icon
     FROM appointments a JOIN services s ON s.id=a.service_id
     $where ORDER BY a.appointment_date, a.appointment_time",
    [':uid' => $uid], $page
);

$services = $pdo->prepare(
    "SELECT * FROM services WHERE branch_id=? AND status='active' ORDER BY name"
)->execute([$branchId]) ? null : null;
$svcStmt  = $pdo->prepare("SELECT * FROM services WHERE branch_id=? AND status='active' ORDER BY name");
$svcStmt->execute([$branchId]);
$services = $svcStmt->fetchAll();

require_once __DIR__ . '/../views/partials/header.php';
?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem">
  <div>
    <h1 style="font-size:1.4rem;font-weight:800">My Appointments</h1>
    <p style="color:var(--muted);font-size:.88rem">Manage your scheduled appointments</p>
  </div>
  <button class="btn-brand" data-bs-toggle="modal" data-bs-target="#bookModal">
    <i class="bi bi-calendar-plus"></i> Book Appointment
  </button>
</div>

<!-- Tabs -->
<div style="display:flex;gap:.5rem;margin-bottom:1.5rem;border-bottom:1px solid var(--border);padding-bottom:0">
  <?php foreach (['upcoming'=>'Upcoming','past'=>'Past','all'=>'All'] as $k=>$label): ?>
  <a href="?tab=<?= $k ?>" style="padding:.6rem 1.1rem;font-weight:600;font-size:.9rem;text-decoration:none;
                                   border-radius:8px 8px 0 0;
                                   <?= $tab===$k ? 'background:var(--surface);color:var(--brand);border:1px solid var(--border);border-bottom:1px solid var(--surface);margin-bottom:-1px' : 'color:var(--muted)' ?>">
    <?= $label ?>
  </a>
  <?php endforeach; ?>
</div>

<?php if (empty($paged['data'])): ?>
<div style="text-align:center;padding:4rem;color:var(--muted)">
  <i class="bi bi-calendar-x" style="font-size:3.5rem;display:block;margin-bottom:1rem;opacity:.4"></i>
  No <?= $tab ?> appointments found.<br>
  <button class="btn-brand" style="margin-top:1rem" data-bs-toggle="modal" data-bs-target="#bookModal">
    <i class="bi bi-calendar-plus"></i> Book one now
  </button>
</div>
<?php else: ?>
<div style="display:flex;flex-direction:column;gap:1rem">
  <?php foreach ($paged['data'] as $a): ?>
  <div style="background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);
              padding:1.25rem;display:flex;align-items:center;gap:1.25rem">

    <!-- Date block -->
    <div style="width:60px;text-align:center;flex-shrink:0;background:var(--bg);
                border-radius:12px;padding:.75rem .5rem">
      <div style="font-size:1.7rem;font-weight:900;line-height:1;color:var(--brand)">
        <?= date('d', strtotime($a['appointment_date'])) ?>
      </div>
      <div style="font-size:.7rem;text-transform:uppercase;color:var(--muted)">
        <?= date('M', strtotime($a['appointment_date'])) ?>
      </div>
    </div>

    <!-- Icon -->
    <div style="width:46px;height:46px;border-radius:12px;background:<?= e($a['color']) ?>22;
                display:grid;place-items:center;font-size:1.3rem;color:<?= e($a['color']) ?>;flex-shrink:0">
      <i class="bi <?= e($a['icon']) ?>"></i>
    </div>

    <!-- Info -->
    <div style="flex:1">
      <div style="font-weight:700;font-size:1rem;margin-bottom:.2rem"><?= e($a['service_name']) ?></div>
      <div style="font-size:.82rem;color:var(--muted)">
        <i class="bi bi-clock"></i> <?= date('H:i', strtotime($a['appointment_time'])) ?>
        &nbsp;•&nbsp;
        <i class="bi bi-calendar3"></i> <?= date('l', strtotime($a['appointment_date'])) ?>
      </div>
      <?php if ($a['notes']): ?>
      <div style="font-size:.78rem;color:var(--muted);margin-top:.2rem">
        <i class="bi bi-chat-square-text"></i> <?= e($a['notes']) ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- Status -->
    <div style="text-align:right">
      <?= statusBadge($a['status']) ?>
    </div>

    <!-- Actions -->
    <?php if (in_array($a['status'], ['pending','confirmed'])): ?>
    <div style="display:flex;gap:.4rem">
      <button class="btn-ghost" style="font-size:.8rem;padding:.4rem .65rem"
              onclick="openReschedule(<?= $a['id'] ?>)">
        <i class="bi bi-calendar-check"></i>
      </button>
      <button class="btn-danger" style="font-size:.8rem;padding:.4rem .65rem"
              onclick="openCancel(<?= $a['id'] ?>)">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- BOOK MODAL -->
<div class="modal fade" id="bookModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold"><i class="bi bi-calendar-plus"></i> Book Appointment</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="book">
        <div class="modal-body row g-3">
          <div class="col-12">
            <label class="form-label">Service *</label>
            <select name="service_id" class="form-select" required>
              <option value="">Choose a service…</option>
              <?php foreach ($services as $sv): ?>
              <option value="<?= $sv['id'] ?>"><?= e($sv['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Date *</label>
            <input type="date" name="appointment_date" class="form-control"
                   min="<?= date('Y-m-d') ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Time *</label>
            <input type="time" name="appointment_time" class="form-control"
                   min="08:00" max="17:00" required>
          </div>
          <div class="col-12">
            <label class="form-label">Notes (optional)</label>
            <textarea name="notes" class="form-control" rows="2" placeholder="Any special requests…"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn-brand"><i class="bi bi-check-lg"></i> Confirm Booking</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- CANCEL MODAL -->
<div class="modal fade" id="cancelModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">Cancel Appointment</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="cancel">
        <input type="hidden" name="id" id="cancelId">
        <div class="modal-body">
          <label class="form-label">Reason for cancellation</label>
          <textarea name="reason" class="form-control" rows="3" placeholder="Optional…"></textarea>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-ghost" data-bs-dismiss="modal">Keep Appointment</button>
          <button type="submit" class="btn-danger">Yes, Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- RESCHEDULE MODAL -->
<div class="modal fade" id="rescheduleModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">Reschedule</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="reschedule">
        <input type="hidden" name="id" id="rescheduleId">
        <div class="modal-body row g-2">
          <div class="col-12">
            <label class="form-label">New Date *</label>
            <input type="date" name="new_date" class="form-control" min="<?= date('Y-m-d') ?>" required>
          </div>
          <div class="col-12">
            <label class="form-label">New Time *</label>
            <input type="time" name="new_time" class="form-control" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn-brand">Reschedule</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php
$extraJs = '
<script>
function openCancel(id) {
  document.getElementById("cancelId").value = id;
  new bootstrap.Modal(document.getElementById("cancelModal")).show();
}
function openReschedule(id) {
  document.getElementById("rescheduleId").value = id;
  new bootstrap.Modal(document.getElementById("rescheduleModal")).show();
}
</script>';
require_once __DIR__ . '/../views/partials/footer.php';
?>
