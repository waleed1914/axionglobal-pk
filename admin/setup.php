<?php
/* =========================================================
   One-time admin password setup.

   Runs only while no password exists. Once one is set this
   page locks itself, so it cannot be used to take the panel
   over later. To reset, clear the admin_password_hash row
   from the settings table (see DEPLOY.md).
   ========================================================= */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';

if (admin_password_is_set()) {
    header('Location: login.php');
    exit;
}

$error = '';
$MIN = 10;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {

    if (!csrf_check()) {
        $error = 'Your session expired. Please try again.';
    } else {
        $p1 = (string) ($_POST['password'] ?? '');
        $p2 = (string) ($_POST['password2'] ?? '');

        if (mb_strlen($p1) < $MIN) {
            $error = 'Use at least ' . $MIN . ' characters.';
        } elseif ($p1 !== $p2) {
            $error = 'The two passwords do not match.';
        } else {
            admin_password_set($p1);
            admin_log_in();
            header('Location: index.php');
            exit;
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Set admin password — AXION Global</title>
<meta name="robots" content="noindex, nofollow">
<link rel="stylesheet" href="admin.css">
</head>
<body>

<div class="auth-wrap">
  <div class="auth-card">

    <div class="auth-brand">AXION <span>GLOBAL</span></div>
    <div class="auth-sub">First-time setup</div>

    <?php if ($error !== ''): ?>
      <div class="alert alert--bad"><?= h($error) ?></div>
    <?php endif; ?>

    <p style="font-size:13px;color:#5C6270;line-height:1.65;margin-bottom:20px;">
      No admin password has been set yet. Choose one now — it is stored
      only as a hash, so it cannot be read back out of the database.
    </p>

    <form method="post" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">

      <label class="field">
        <span>New password</span>
        <input type="password" name="password" required minlength="<?= $MIN ?>" autofocus>
      </label>

      <label class="field">
        <span>Confirm password</span>
        <input type="password" name="password2" required minlength="<?= $MIN ?>">
      </label>

      <button type="submit" class="btn btn--full">Set password &amp; sign in</button>
    </form>

    <p class="note">
      Minimum <?= $MIN ?> characters. Do not reuse your email password.
    </p>

  </div>
</div>

</body>
</html>
