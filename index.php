<?php
// =============================================================
// index.php  –  Login Page
// =============================================================
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

startSession();

// Already logged in → redirect to correct dashboard
if (!empty($_SESSION['logged_in'])) {
    redirect($_SESSION['role'] === 'admin'
        ? APP_URL . '/admin/dashboard.php'
        : APP_URL . '/employee/dashboard.php');
}

$error = '';
$msg   = e($_GET['msg'] ?? '');

// ---- Handle POST login ----------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!$email || !$password) {
        $error = 'Please enter both email and password.';
    } else {
        $user = attemptLogin($email, $password);
        if ($user) {
            loginUser($user);
            redirect($user['role'] === 'admin'
                ? APP_URL . '/admin/dashboard.php'
                : APP_URL . '/employee/dashboard.php');
        } else {
            $error = 'Invalid email or password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= APP_NAME ?> – Login</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="assets/css/style.css"/>
</head>
<body class="login-body">

  <div class="login-split">

    <!-- Left brand panel -->
    <div class="login-brand">
      <div class="brand-content">
        <div class="brand-logo">
          <svg width="48" height="48" viewBox="0 0 48 48" fill="none">
            <rect width="48" height="48" rx="14" fill="#f0ff4b"/>
            <path d="M12 24C12 17.373 17.373 12 24 12s12 5.373 12 12-5.373 12-12 12S12 30.627 12 24z" fill="#0a0a0a" opacity=".15"/>
            <path d="M24 16v8l5 3" stroke="#0a0a0a" stroke-width="2.5" stroke-linecap="round"/>
            <circle cx="24" cy="24" r="9" stroke="#0a0a0a" stroke-width="2.5" fill="none"/>
          </svg>
          <span class="brand-name"><?= APP_NAME ?></span>
        </div>
        <h1 class="brand-tagline">Track time.<br/>Measure growth.<br/>Build teams.</h1>
        <p class="brand-sub">A complete attendance &amp; performance management platform for modern workplaces.</p>
        <div class="brand-stats">
          <div class="stat"><span class="stat-num">99%</span><span class="stat-label">Uptime</span></div>
          <div class="stat"><span class="stat-num">∞</span><span class="stat-label">Employees</span></div>
          <div class="stat"><span class="stat-num">Live</span><span class="stat-label">Reports</span></div>
        </div>
      </div>
      <div class="brand-bg-circles">
        <div class="bc bc1"></div>
        <div class="bc bc2"></div>
        <div class="bc bc3"></div>
      </div>
    </div>

    <!-- Right login form -->
    <div class="login-form-panel">
      <div class="login-card">
        <h2 class="login-title">Welcome back</h2>
        <p class="login-subtitle">Sign in to your workspace</p>

        <?php if ($msg): ?>
          <div class="alert alert-info"><?= $msg ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
          <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="" class="login-form" novalidate>
          <div class="form-group">
            <label for="email">Email Address</label>
            <div class="input-wrap">
              <svg class="input-icon" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
              <input type="email" id="email" name="email" placeholder="you@company.com"
                     value="<?= e($_POST['email'] ?? '') ?>" autocomplete="email" required/>
            </div>
          </div>
          <div class="form-group">
            <label for="password">Password</label>
            <div class="input-wrap">
              <svg class="input-icon" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
              <input type="password" id="password" name="password" placeholder="••••••••" autocomplete="current-password" required/>
              <button type="button" class="toggle-pw" onclick="togglePw(this)" aria-label="Toggle password">
                <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
              </button>
            </div>
          </div>
          <button type="submit" class="btn-login">Sign In →</button>
        </form>
        <div class="login-footer" style="margin-top:1rem; text-align:center;">
          <a href="forgot_password.php" class="forgot-link">Forgot password?</a>
        </div>
      </div>
    </div>

  </div><!-- /login-split -->

<script>
function togglePw(btn) {
  const input = btn.closest('.input-wrap').querySelector('input');
  input.type  = input.type === 'password' ? 'text' : 'password';
}
</script>
</body>
</html>
