<?php
// =============================================================
// forgot_password.php  –  Reset employee password
// =============================================================
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

startSession();

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email           = strtolower(trim($_POST['email'] ?? ''));
    $password        = trim($_POST['password'] ?? '');
    $confirmPassword = trim($_POST['confirm_password'] ?? '');

    if (!$email || !$password || !$confirmPassword) {
        $error = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match. Please try again.';
    } elseif (strlen($password) < 8) {
        $error = 'Password should be at least 8 characters long.';
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT id, full_name FROM employees WHERE email = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            $error = 'No active employee account was found for that email.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $update = $db->prepare("UPDATE employees SET password_hash = ? WHERE id = ?");
            $update->execute([$hash, $user['id']]);
            $success = 'Your password has been updated successfully. You can now sign in with your new password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= APP_NAME ?> – Reset Password</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="assets/css/style.css"/>
</head>
<body class="login-body">
  <div class="login-split">
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
        <h1 class="brand-tagline">Reset your password<br/>and sign in anywhere.</h1>
        <p class="brand-sub">Enter your registered email and choose a new password to update your account.</p>
      </div>
    </div>

    <div class="login-form-panel">
      <div class="login-card">
        <h2 class="login-title">Forgot Password?</h2>
        <p class="login-sub">Reset your employee account password.</p>

        <?php if ($success): ?>
          <div class="alert alert-success"><?= e($success) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
          <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="" class="login-form" novalidate>
          <div class="form-group">
            <label for="email">Registered Email</label>
            <div class="input-wrap">
              <svg class="input-icon" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
              <input type="email" id="email" name="email" placeholder="you@company.com"
                     value="<?= e($_POST['email'] ?? '') ?>" autocomplete="email" required/>
            </div>
          </div>
          <div class="form-group">
            <label for="password">New Password</label>
            <div class="input-wrap">
              <svg class="input-icon" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
              <input type="password" id="password" name="password" placeholder="New password" autocomplete="new-password" required/>
            </div>
          </div>
          <div class="form-group">
            <label for="confirm_password">Confirm New Password</label>
            <div class="input-wrap">
              <svg class="input-icon" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
              <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm password" autocomplete="new-password" required/>
            </div>
          </div>
          <button type="submit" class="btn-login">Update Password</button>
        </form>

        <div class="login-footer">
          <p>Remembered your password? <a href="index.php">Sign in</a></p>
        </div>
      </div>
    </div>
  </div><!-- /login-split -->
</body>
</html>
