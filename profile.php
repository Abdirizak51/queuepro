<?php
// profile.php
require_once __DIR__ . '/bootstrap.php';
requireAuth();

$pdo = db();
$uid = $_SESSION['user_id'];
$pageTitle = 'My Profile';

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $fullName = trim($_POST['full_name'] ?? '');
        $phone    = trim($_POST['phone']     ?? '');
        $email    = trim($_POST['email']     ?? '');

        if (!$fullName) $errors[] = 'Full name is required.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email required.';

        if (empty($errors)) {
            // Check email unique (excluding self)
            $chk = $pdo->prepare("SELECT id FROM users WHERE email=? AND id != ?");
            $chk->execute([$email, $uid]);
            if ($chk->fetch()) $errors[] = 'Email already in use.';
        }

        if (empty($errors)) {
            $pdo->prepare("UPDATE users SET full_name=?,phone=?,email=? WHERE id=?")
                ->execute([$fullName, $phone, $email, $uid]);
            $_SESSION['full_name'] = $fullName;
            $_SESSION['email']     = $email;
            flash('success','Profile updated.');
            redirect(APP_URL . '/profile.php');
        }
    }

    if ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password']     ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        $user = $pdo->prepare("SELECT password_hash FROM users WHERE id=?")->execute([$uid]) ?
                $pdo->query("SELECT password_hash FROM users WHERE id=$uid")->fetch() : null;
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id=?");
        $stmt->execute([$uid]);
        $user = $stmt->fetch();

        if (!password_verify($current, $user['password_hash'])) $errors[] = 'Current password is incorrect.';
        if (strlen($new) < 8)  $errors[] = 'New password must be at least 8 characters.';
        if ($new !== $confirm) $errors[] = 'Passwords do not match.';

        if (empty($errors)) {
            $hash = password_hash($new, PASSWORD_BCRYPT, ['cost' => PASSWORD_COST]);
            $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([$hash, $uid]);
            flash('success', 'Password changed successfully.');
            redirect(APP_URL . '/profile.php');
        }
    }
}

$user = currentUser();
require_once __DIR__ . '/views/partials/header.php';
?>

<div style="max-width:640px">
  <h1 style="font-size:1.4rem;font-weight:800;margin-bottom:1.75rem">Profile Settings</h1>

  <?php if ($errors): ?>
  <div class="flash-alert flash-error">
    <i class="bi bi-exclamation-circle"></i> <?= e(implode(' ', $errors)) ?>
  </div>
  <?php endif; ?>

  <!-- Avatar + info -->
  <div class="panel" style="margin-bottom:1.5rem">
    <div class="panel-body" style="display:flex;align-items:center;gap:1.5rem">
      <div style="width:72px;height:72px;background:var(--brand);border-radius:50%;
                  display:grid;place-items:center;color:#fff;font-size:2rem;font-weight:700;flex-shrink:0">
        <?= strtoupper(substr($user['full_name'],0,1)) ?>
      </div>
      <div>
        <div style="font-size:1.2rem;font-weight:800"><?= e($user['full_name']) ?></div>
        <div style="color:var(--muted);font-size:.88rem"><?= e($user['email']) ?></div>
        <div style="margin-top:.3rem">
          <span class="badge bg-secondary"><?= e($user['role_display']) ?></span>
          <?= statusBadge($user['status']) ?>
        </div>
      </div>
      <div style="margin-left:auto;text-align:right;font-size:.8rem;color:var(--muted)">
        <div><i class="bi bi-building"></i> <?= e($user['branch_name'] ?? 'N/A') ?></div>
        <div style="margin-top:.25rem"><i class="bi bi-calendar3"></i> Joined <?= date('d M Y', strtotime($user['created_at'])) ?></div>
        <?php if ($user['last_login']): ?>
        <div style="margin-top:.25rem"><i class="bi bi-clock-history"></i> Last login <?= timeAgo($user['last_login']) ?></div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Update Profile -->
  <div class="panel" style="margin-bottom:1.5rem">
    <div class="panel-header"><span class="panel-title">Personal Information</span></div>
    <div class="panel-body">
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="update_profile">
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label">Full Name</label>
            <input type="text" name="full_name" class="form-control" value="<?= e($user['full_name']) ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" value="<?= e($user['email']) ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Phone</label>
            <input type="tel" name="phone" class="form-control" value="<?= e($user['phone'] ?? '') ?>">
          </div>
          <div class="col-12">
            <button type="submit" class="btn-brand"><i class="bi bi-check-lg"></i> Save Changes</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- Change Password -->
  <div class="panel">
    <div class="panel-header"><span class="panel-title">Change Password</span></div>
    <div class="panel-body">
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="change_password">
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label">Current Password</label>
            <input type="password" name="current_password" class="form-control" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">New Password</label>
            <input type="password" name="new_password" class="form-control" placeholder="Min 8 chars" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Confirm Password</label>
            <input type="password" name="confirm_password" class="form-control" required>
          </div>
          <div class="col-12">
            <button type="submit" class="btn-brand"><i class="bi bi-key"></i> Change Password</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/views/partials/footer.php'; ?>
