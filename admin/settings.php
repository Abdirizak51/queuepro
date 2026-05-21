<?php
// admin/settings.php
require_once __DIR__ . '/../bootstrap.php';
requireRole(ROLE_ADMIN);

$pdo = db();
$pageTitle = 'Settings';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $keys = ['app_name','timezone','queue_reset_time','max_tickets_per_user',
             'notify_at_position','working_hours_start','working_hours_end'];
    foreach ($keys as $k) {
        $val = trim($_POST[$k] ?? '');
        $pdo->prepare("UPDATE settings SET value=? WHERE `key`=?")->execute([$val, $k]);
    }
    flash('success','Settings saved.');
    redirect('settings.php');
}

$settings = [];
foreach ($pdo->query("SELECT `key`,value FROM settings") as $row) {
    $settings[$row['key']] = $row['value'];
}

require_once __DIR__ . '/../views/partials/header.php';
?>

<div style="max-width:600px">
  <h1 style="font-size:1.4rem;font-weight:800;margin-bottom:1.75rem">System Settings</h1>

  <form method="POST">
    <?= csrfField() ?>

    <div class="panel" style="margin-bottom:1.5rem">
      <div class="panel-header"><span class="panel-title">General</span></div>
      <div class="panel-body row g-3">
        <div class="col-12">
          <label class="form-label">Application Name</label>
          <input type="text" name="app_name" class="form-control" value="<?= e($settings['app_name'] ?? 'QueuePro') ?>" required>
        </div>
        <div class="col-12">
          <label class="form-label">Timezone</label>
          <select name="timezone" class="form-select">
            <?php
            $tzs = ['Africa/Nairobi' => 'Nairobi / Mogadishu (UTC+3)', 'Africa/Harare' => 'Harare (UTC+2)', 'UTC' => 'UTC'];
            foreach ($tzs as $k => $label):
            ?>
            <option value="<?= $k ?>" <?= ($settings['timezone'] ?? '') === $k ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">Working Hours Start</label>
          <input type="time" name="working_hours_start" class="form-control"
                 value="<?= e($settings['working_hours_start'] ?? '08:00') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Working Hours End</label>
          <input type="time" name="working_hours_end" class="form-control"
                 value="<?= e($settings['working_hours_end'] ?? '17:00') ?>">
        </div>
      </div>
    </div>

    <div class="panel" style="margin-bottom:1.5rem">
      <div class="panel-header"><span class="panel-title">Queue Rules</span></div>
      <div class="panel-body row g-3">
        <div class="col-md-6">
          <label class="form-label">Daily Queue Reset Time</label>
          <input type="time" name="queue_reset_time" class="form-control"
                 value="<?= e($settings['queue_reset_time'] ?? '00:00') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Max Tickets Per User Per Day</label>
          <input type="number" name="max_tickets_per_user" class="form-control" min="1" max="20"
                 value="<?= e($settings['max_tickets_per_user'] ?? '3') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Notify When N Tickets Ahead</label>
          <input type="number" name="notify_at_position" class="form-control" min="1" max="10"
                 value="<?= e($settings['notify_at_position'] ?? '3') ?>">
          <small style="color:var(--muted);font-size:.78rem">Users notified when this many tickets before them</small>
        </div>
      </div>
    </div>

    <button type="submit" class="btn-brand" style="padding:.8rem 2rem">
      <i class="bi bi-check-lg"></i> Save Settings
    </button>
  </form>
</div>

<?php require_once __DIR__ . '/../views/partials/footer.php'; ?>
