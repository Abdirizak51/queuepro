<?php
// register.php
require_once __DIR__ . '/bootstrap.php';

if (isLoggedIn()) redirect(dashboardUrl());

$errors = [];
$data   = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $data = [
        'full_name' => trim($_POST['full_name'] ?? ''),
        'email'     => trim($_POST['email']     ?? ''),
        'phone'     => trim($_POST['phone']      ?? ''),
        'password'  => $_POST['password']       ?? '',
        'confirm'   => $_POST['confirm_password']?? '',
        'branch_id' => (int)($_POST['branch_id'] ?? 1),
    ];

    if (!$data['full_name'])                       $errors[] = 'Full name is required.';
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
    if (strlen($data['password']) < 8)             $errors[] = 'Password must be at least 8 characters.';
    if ($data['password'] !== $data['confirm'])    $errors[] = 'Passwords do not match.';

    // Check duplicate email
    if (empty($errors)) {
        $chk = db()->prepare("SELECT id FROM users WHERE email = ?");
        $chk->execute([$data['email']]);
        if ($chk->fetch()) $errors[] = 'This email address is already registered.';
    }

    if (empty($errors)) {
        $hash = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => PASSWORD_COST]);
        $stmt = db()->prepare(
            "INSERT INTO users (branch_id, role_id, full_name, email, phone, password_hash, email_verified_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())"
        );
        $stmt->execute([
            $data['branch_id'],
            ROLE_CUSTOMER,
            $data['full_name'],
            $data['email'],
            $data['phone'],
            $hash,
        ]);
        logActivity('register', 'New customer registered: ' . $data['email'], (int)db()->lastInsertId());
        flash('success', 'Account created! Please sign in.');
        redirect(APP_URL . '/login.php');
    }
}

// Load branches
$branches = db()->query("SELECT id, name FROM branches WHERE status='active'")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Create Account – <?= APP_NAME ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    :root {
      --brand:#1B4FD8; --brand-dk:#1239a3; --accent:#00C9A7;
      --bg:#0A0F1E; --surface:#111827; --border:rgba(255,255,255,.08);
      --text:#E5E9F4; --muted:#8492AA;
    }
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:var(--text);
         min-height:100vh;display:flex;align-items:center;justify-content:center;padding:2rem 1rem;}
    .card{background:var(--surface);border:1px solid var(--border);border-radius:20px;
          width:100%;max-width:520px;padding:2.5rem;}
    .logo{display:flex;align-items:center;gap:.6rem;margin-bottom:2rem;}
    .logo-icon{width:40px;height:40px;background:var(--brand);border-radius:10px;
               display:grid;place-items:center;font-size:1.2rem;}
    .logo-text{font-size:1.35rem;font-weight:800;}
    .logo-text span{color:var(--accent);}
    h2{font-size:1.6rem;font-weight:800;margin-bottom:.4rem;}
    .sub{color:var(--muted);font-size:.9rem;margin-bottom:1.8rem;}
    .grid-2{display:grid;grid-template-columns:1fr 1fr;gap:1rem;}
    .form-group{margin-bottom:1.1rem;}
    label{display:block;font-size:.78rem;font-weight:600;color:var(--muted);
          margin-bottom:.4rem;text-transform:uppercase;letter-spacing:.05em;}
    .input-wrap{position:relative;}
    .input-wrap i{position:absolute;left:1rem;top:50%;transform:translateY(-50%);color:var(--muted);}
    input,select{width:100%;background:rgba(255,255,255,.04);border:1.5px solid var(--border);
                 border-radius:10px;color:var(--text);font-family:inherit;font-size:.92rem;
                 padding:.8rem 1rem .8rem 2.8rem;outline:none;transition:border-color .2s;}
    select{padding-left:1rem;}
    input:focus,select:focus{border-color:var(--brand);box-shadow:0 0 0 3px rgba(27,79,216,.2);}
    .errors{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);
            border-radius:10px;padding:.85rem 1rem;margin-bottom:1.2rem;}
    .errors li{color:#fca5a5;font-size:.88rem;margin-left:1rem;}
    .btn{width:100%;background:var(--brand);color:#fff;border:none;border-radius:10px;
         font-family:inherit;font-size:1rem;font-weight:700;padding:.9rem;cursor:pointer;
         transition:background .2s;margin-top:.5rem;}
    .btn:hover{background:var(--brand-dk);}
    .login-link{text-align:center;margin-top:1.2rem;font-size:.9rem;color:var(--muted);}
    .login-link a{color:var(--accent);font-weight:600;text-decoration:none;}
    @media(max-width:480px){.grid-2{grid-template-columns:1fr;}}
  </style>
</head>
<body>
<div class="card">
  <div class="logo">
    <div class="logo-icon"><i class="bi bi-ticket-perforated"></i></div>
    <span class="logo-text">Queue<span>Pro</span></span>
  </div>

  <h2>Create Account</h2>
  <p class="sub">Join QueuePro and skip the waiting line</p>

  <?php if ($errors): ?>
  <ul class="errors">
    <?php foreach ($errors as $e): ?>
    <li><?= e($e) ?></li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>

  <form method="POST">
    <?= csrfField() ?>

    <div class="grid-2">
      <div class="form-group">
        <label>Full Name</label>
        <div class="input-wrap">
          <i class="bi bi-person"></i>
          <input type="text" name="full_name" value="<?= e($data['full_name'] ?? '') ?>"
                 placeholder="Ahmed Ali" required>
        </div>
      </div>
      <div class="form-group">
        <label>Phone</label>
        <div class="input-wrap">
          <i class="bi bi-phone"></i>
          <input type="tel" name="phone" value="<?= e($data['phone'] ?? '') ?>"
                 placeholder="+252 61 000 0000">
        </div>
      </div>
    </div>

    <div class="form-group">
      <label>Email Address</label>
      <div class="input-wrap">
        <i class="bi bi-envelope"></i>
        <input type="email" name="email" value="<?= e($data['email'] ?? '') ?>"
               placeholder="you@example.com" required>
      </div>
    </div>

    <div class="form-group">
      <label>Branch</label>
      <select name="branch_id">
        <?php foreach ($branches as $b): ?>
        <option value="<?= $b['id'] ?>"
          <?= ($data['branch_id'] ?? 1) == $b['id'] ? 'selected' : '' ?>>
          <?= e($b['name']) ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="grid-2">
      <div class="form-group">
        <label>Password</label>
        <div class="input-wrap">
          <i class="bi bi-lock"></i>
          <input type="password" name="password" placeholder="Min 8 chars" required>
        </div>
      </div>
      <div class="form-group">
        <label>Confirm Password</label>
        <div class="input-wrap">
          <i class="bi bi-lock-fill"></i>
          <input type="password" name="confirm_password" placeholder="Repeat password" required>
        </div>
      </div>
    </div>

    <button type="submit" class="btn">Create Account</button>
  </form>

  <div class="login-link">
    Already have an account? <a href="login.php">Sign in</a>
  </div>
</div>
</body>
</html>
