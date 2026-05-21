<?php
// reset-password.php
require_once __DIR__ . '/bootstrap.php';

$token  = $_GET['token'] ?? '';
$errors = [];
$done   = false;

$stmt = db()->prepare("SELECT * FROM users WHERE reset_token=? AND reset_token_expires > NOW()");
$stmt->execute([$token]);
$user = $stmt->fetch();

if (!$token || !$user) {
    flash('error','Invalid or expired reset link. Please request a new one.');
    redirect(APP_URL . '/forgot-password.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $pw  = $_POST['password']         ?? '';
    $cpw = $_POST['confirm_password'] ?? '';

    if (strlen($pw) < 8)    $errors[] = 'Password must be at least 8 characters.';
    if ($pw !== $cpw)        $errors[] = 'Passwords do not match.';

    if (empty($errors)) {
        $hash = password_hash($pw, PASSWORD_BCRYPT, ['cost' => PASSWORD_COST]);
        db()->prepare("UPDATE users SET password_hash=?,reset_token=NULL,reset_token_expires=NULL WHERE id=?")
           ->execute([$hash, $user['id']]);
        $done = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Reset Password – <?= APP_NAME ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    :root{--brand:#1B4FD8;--accent:#00C9A7;--bg:#0A0F1E;--surface:#111827;
          --border:rgba(255,255,255,.08);--text:#E5E9F4;--muted:#8492AA;}
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:var(--text);
         min-height:100vh;display:flex;align-items:center;justify-content:center;padding:2rem}
    .card{background:var(--surface);border:1px solid var(--border);border-radius:20px;
          width:100%;max-width:420px;padding:2.5rem;}
    h2{font-size:1.5rem;font-weight:800;margin-bottom:.4rem;}
    .sub{color:var(--muted);font-size:.9rem;margin-bottom:1.8rem;}
    label{display:block;font-size:.78rem;font-weight:600;color:var(--muted);
          margin-bottom:.4rem;text-transform:uppercase;letter-spacing:.05em;}
    .input-wrap{position:relative;margin-bottom:1.1rem;}
    .input-wrap i{position:absolute;left:1rem;top:50%;transform:translateY(-50%);color:var(--muted);}
    input{width:100%;background:rgba(255,255,255,.04);border:1.5px solid var(--border);
          border-radius:10px;color:var(--text);font-family:inherit;font-size:.92rem;
          padding:.8rem 1rem .8rem 2.8rem;outline:none;transition:border-color .2s;}
    input:focus{border-color:var(--brand);}
    .btn{width:100%;background:var(--brand);color:#fff;border:none;border-radius:10px;
         font-family:inherit;font-size:1rem;font-weight:700;padding:.9rem;cursor:pointer;}
    .err{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);border-radius:10px;
         padding:.85rem;color:#fca5a5;font-size:.88rem;margin-bottom:1rem;}
    .success{text-align:center;color:#10b981}
  </style>
</head>
<body>
<div class="card">
  <h2>Set New Password</h2>
  <p class="sub">For account: <strong><?= e($user['email']) ?></strong></p>

  <?php if ($done): ?>
  <div class="success">
    <i class="bi bi-check-circle-fill" style="font-size:3rem;display:block;margin-bottom:1rem"></i>
    <strong>Password updated!</strong><br>
    <a href="login.php" style="color:var(--brand);font-weight:600;text-decoration:none;
                                display:block;margin-top:1rem">← Sign In</a>
  </div>
  <?php else: ?>
  <?php if ($errors): ?>
  <div class="err"><?= e(implode(' ', $errors)) ?></div>
  <?php endif; ?>
  <form method="POST">
    <?= csrfField() ?>
    <label>New Password</label>
    <div class="input-wrap">
      <i class="bi bi-lock"></i>
      <input type="password" name="password" placeholder="Min 8 characters" required>
    </div>
    <label>Confirm Password</label>
    <div class="input-wrap">
      <i class="bi bi-lock-fill"></i>
      <input type="password" name="confirm_password" placeholder="Repeat password" required>
    </div>
    <button type="submit" class="btn">Update Password</button>
  </form>
  <?php endif; ?>
</div>
</body>
</html>
