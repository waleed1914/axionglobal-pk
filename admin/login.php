<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';

if (!admin_password_is_set()) {
    header('Location: setup.php');
    exit;
}

if (admin_is_logged_in()) {
    header('Location: index.php');
    exit;
}

$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {

    if (!csrf_check()) {
        $error = 'Your session expired. Please try again.';
    } elseif (login_is_locked()) {
        $error = 'Too many failed attempts. Try again in ' . LOGIN_LOCKOUT_MINUTES . ' minutes.';
    } elseif (admin_password_verify((string) ($_POST['password'] ?? ''))) {
        login_attempts_clear();
        admin_log_in();
        header('Location: index.php');
        exit;
    } else {
        login_attempt_record();
        $left = max(0, LOGIN_MAX_ATTEMPTS - login_attempts_recent());
        $error = $left > 0
            ? 'Incorrect password. ' . $left . ' attempt' . ($left === 1 ? '' : 's') . ' left.'
            : 'Too many failed attempts. Try again in ' . LOGIN_LOCKOUT_MINUTES . ' minutes.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin sign in — AXION Global</title>
<meta name="robots" content="noindex, nofollow">
<link rel="stylesheet" href="admin.css">
</head>
<body>

<div class="auth-wrap">
  <div class="auth-card">

    <div class="auth-brand">AXION <span>GLOBAL</span></div>
    <div class="auth-sub">Admin Panel</div>

    <?php if ($error !== ''): ?>
      <div class="alert alert--bad"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="post" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">

      <label class="field">
        <span>Password</span>
        <input type="password" name="password" required autofocus>
      </label>

      <button type="submit" class="btn btn--full">Sign in</button>
    </form>

    <p class="note">
      <a href="../index.html" style="color:#8A8F99;">&larr; Back to website</a>
    </p>

  </div>
</div>

</body>
</html>
