<?php
// login.php
require_once __DIR__ . '/bootstrap.php';

if (isLoggedIn()) {
    redirect(dashboardUrl());
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = !empty($_POST['remember']);

    if ($email && $password) {
        $stmt = db()->prepare(
            "SELECT u.*, r.name AS role_name FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE u.email = ? AND u.status = 'active'
             LIMIT 1"
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            loginUser($user);

            if ($remember) {
                $token = bin2hex(random_bytes(32));
                setcookie('remember_token', $token, time() + 86400 * 30, '/', '', false, true);
                db()->prepare("UPDATE users SET remember_token=? WHERE id=?")->execute([$token, $user['id']]);
            }

            redirect(dashboardUrl());
        } else {
            $error = 'Invalid email address or password.';
            logActivity('login_failed', 'Failed login attempt for: ' . $email);
        }
    } else {
        $error = 'Please fill in all fields.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign In – <?= APP_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    :root {
      --brand:    #1B4FD8;
      --brand-dk: #1239a3;
      --accent:   #00C9A7;
      --bg:       #0A0F1E;
      --surface:  #111827;
      --border:   rgba(255,255,255,.08);
      --text:     #E5E9F4;
      --muted:    #8492AA;
    }
    *, *::before, *::after { box-sizing: border-box; margin:0; padding:0; }
    body {
      font-family: 'Plus Jakarta Sans', sans-serif;
      background: var(--bg);
      color: var(--text);
      min-height: 100vh;
      display: grid;
      grid-template-columns: 1fr 480px;
    }
    /* ---- LEFT PANEL ---- */
    .panel-left {
      position: relative;
      overflow: hidden;
      display: flex;
      flex-direction: column;
      justify-content: flex-end;
      padding: 3rem;
      background: linear-gradient(135deg,#0D1B5E 0%,#0A0F1E 60%);
    }
    .panel-left::before {
      content:'';
      position:absolute; inset:0;
      background:
        radial-gradient(ellipse 60% 50% at 30% 20%, rgba(27,79,216,.4) 0%, transparent 70%),
        radial-gradient(ellipse 40% 40% at 75% 70%, rgba(0,201,167,.25) 0%, transparent 70%);
    }
    .hero-stat {
      position: relative;
      z-index:1;
      display: grid;
      grid-template-columns: repeat(3,1fr);
      gap: 1.5rem;
      margin-bottom: 2.5rem;
    }
    .stat-card {
      background: rgba(255,255,255,.06);
      border: 1px solid var(--border);
      border-radius: 16px;
      padding: 1.25rem;
      backdrop-filter: blur(12px);
    }
    .stat-card .num {
      font-size: 2rem;
      font-weight: 800;
      color: var(--accent);
      line-height: 1;
    }
    .stat-card .lbl {
      font-size: .75rem;
      color: var(--muted);
      margin-top: .3rem;
      text-transform: uppercase;
      letter-spacing:.05em;
    }
    .hero-text { position:relative; z-index:1; }
    .hero-text h1 {
      font-size: 2.4rem;
      font-weight: 800;
      line-height: 1.2;
      margin-bottom: .75rem;
    }
    .hero-text h1 span { color: var(--accent); }
    .hero-text p { color: var(--muted); font-size: 1rem; max-width: 380px; line-height: 1.7; }

    /* ---- RIGHT PANEL ---- */
    .panel-right {
      background: var(--surface);
      display: flex;
      flex-direction: column;
      justify-content: center;
      padding: 3rem 2.5rem;
      border-left: 1px solid var(--border);
    }
    .logo {
      display: flex;
      align-items: center;
      gap: .65rem;
      margin-bottom: 2.5rem;
    }
    .logo-icon {
      width: 42px; height: 42px;
      background: var(--brand);
      border-radius: 12px;
      display: grid;
      place-items: center;
      font-size: 1.3rem;
    }
    .logo-text {
      font-size: 1.4rem;
      font-weight: 800;
      letter-spacing: -.02em;
    }
    .logo-text span { color: var(--accent); }
    h2 { font-size: 1.65rem; font-weight: 800; margin-bottom: .5rem; }
    .subtitle { color: var(--muted); margin-bottom: 2rem; font-size: .95rem; }

    .form-group { margin-bottom: 1.2rem; }
    label {
      display: block;
      font-size: .82rem;
      font-weight: 600;
      color: var(--muted);
      margin-bottom: .45rem;
      text-transform: uppercase;
      letter-spacing: .05em;
    }
    .input-wrap {
      position: relative;
    }
    .input-wrap i {
      position: absolute;
      left: 1rem;
      top: 50%;
      transform: translateY(-50%);
      color: var(--muted);
      font-size: 1.1rem;
    }
    input[type="email"],
    input[type="password"] {
      width: 100%;
      background: rgba(255,255,255,.04);
      border: 1.5px solid var(--border);
      border-radius: 10px;
      color: var(--text);
      font-family: inherit;
      font-size: .95rem;
      padding: .85rem 1rem .85rem 2.8rem;
      transition: border-color .2s, box-shadow .2s;
      outline: none;
    }
    input:focus {
      border-color: var(--brand);
      box-shadow: 0 0 0 3px rgba(27,79,216,.2);
    }
    .toggle-pw {
      position: absolute;
      right: 1rem;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      color: var(--muted);
      cursor: pointer;
      font-size: 1rem;
      padding: 0;
    }
    .row-between {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 1.5rem;
    }
    .check-label {
      display: flex;
      align-items: center;
      gap: .5rem;
      font-size: .88rem;
      color: var(--muted);
      cursor: pointer;
      text-transform: none;
      letter-spacing: normal;
      font-weight: 400;
    }
    input[type="checkbox"] { accent-color: var(--brand); width:15px; height:15px; }
    .forgot { font-size: .88rem; color: var(--brand); text-decoration: none; }
    .forgot:hover { text-decoration: underline; }

    .btn-primary {
      width: 100%;
      background: var(--brand);
      color: #fff;
      border: none;
      border-radius: 10px;
      font-family: inherit;
      font-size: 1rem;
      font-weight: 700;
      padding: .9rem;
      cursor: pointer;
      transition: background .2s, transform .1s;
    }
    .btn-primary:hover { background: var(--brand-dk); }
    .btn-primary:active { transform: scale(.98); }

    .divider {
      display: flex;
      align-items: center;
      gap: 1rem;
      color: var(--muted);
      font-size: .8rem;
      margin: 1.5rem 0;
    }
    .divider::before, .divider::after {
      content:''; flex:1;
      height:1px; background: var(--border);
    }
    .register-link {
      text-align: center;
      font-size: .9rem;
      color: var(--muted);
    }
    .register-link a { color: var(--accent); font-weight: 600; text-decoration: none; }
    .register-link a:hover { text-decoration: underline; }

    .alert-error {
      background: rgba(239,68,68,.1);
      border: 1px solid rgba(239,68,68,.3);
      border-radius: 10px;
      padding: .85rem 1rem;
      color: #fca5a5;
      font-size: .9rem;
      display: flex;
      align-items: center;
      gap: .6rem;
      margin-bottom: 1.2rem;
    }

    @media (max-width: 768px) {
      body { grid-template-columns: 1fr; }
      .panel-left { display: none; }
      .panel-right { padding: 2rem 1.5rem; }
    }
  </style>
</head>
<body>

<!-- LEFT HERO PANEL -->
<div class="panel-left">
  <div class="hero-stat">
    <div class="stat-card">
      <div class="num">1.2K</div>
      <div class="lbl">Tickets / Day</div>
    </div>
    <div class="stat-card">
      <div class="num">98%</div>
      <div class="lbl">Satisfaction</div>
    </div>
    <div class="stat-card">
      <div class="num">4 min</div>
      <div class="lbl">Avg Wait</div>
    </div>
  </div>
  <div class="hero-text">
    <h1>Smart Queuing<br>for <span>Modern Somalia</span></h1>
    <p>QueuePro eliminates long waiting lines for hospitals, banks, government offices, and salons — all in one platform.</p>
  </div>
</div>

<!-- RIGHT LOGIN PANEL -->
<div class="panel-right">
  <div class="logo">
    <div class="logo-icon"><i class="bi bi-ticket-perforated"></i></div>
    <span class="logo-text">Ballan<span>HUB</span></span>
  </div>

  <h2>Welcome back</h2>
  <p class="subtitle">Sign in to your account to continue</p>

  <?php if ($error): ?>
  <div class="alert-error"><i class="bi bi-exclamation-circle"></i><?= e($error) ?></div>
  <?php endif; ?>

  <?php $flash = getFlash(); if ($flash && $flash['type'] === 'success'): ?>
  <div class="alert-error" style="background:rgba(16,185,129,.1);border-color:rgba(16,185,129,.3);color:#6ee7b7;">
    <i class="bi bi-check-circle"></i><?= e($flash['message']) ?>
  </div>
  <?php endif; ?>

  <form method="POST" action="">
    <?= csrfField() ?>

    <div class="form-group">
      <label>Email Address</label>
      <div class="input-wrap">
        <i class="bi bi-envelope"></i>
        <input type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>"
               placeholder="you@example.com" required autofocus>
      </div>
    </div>

    <div class="form-group">
      <label>Password</label>
      <div class="input-wrap">
        <i class="bi bi-lock"></i>
        <input type="password" name="password" id="pw" placeholder="••••••••" required>
        <button type="button" class="toggle-pw" onclick="togglePw()">
          <i class="bi bi-eye" id="pw-icon"></i>
        </button>
      </div>
    </div>

    <div class="row-between">
      <label class="check-label">
        <input type="checkbox" name="remember"> Remember me
      </label>
      <a href="forgot-password.php" class="forgot">Forgot password?</a>
    </div>

    <button type="submit" class="btn-primary">Sign In</button>
  </form>

  <div class="divider">or</div>

  <div class="register-link">
    Don't have an account? <a href="register.php">Create one free</a>
  </div>
</div>

<script>
function togglePw() {
  const pw   = document.getElementById('pw');
  const icon = document.getElementById('pw-icon');
  if (pw.type === 'password') {
    pw.type = 'text';
    icon.className = 'bi bi-eye-slash';
  } else {
    pw.type = 'password';
    icon.className = 'bi bi-eye';
  }
}
</script>
</body>
</html>
