<?php
require_once __DIR__ . '/config.php';

if (isset($_SESSION['user_id'])) {
    redirect($_SESSION['role'] === 'admin' ? 'admin/dashboard.php' : 'customer/dashboard.php');
}

$token = trim($_GET['token'] ?? '');
$error = '';
$success = '';
$valid = false;

if ($token) {
    $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = ? AND used = 0 AND expires_at > NOW()");
    $stmt->execute([$token]);
    $reset = $stmt->fetch();
    if ($reset) {
        $valid = true;
    } else {
        $error = 'This reset link is invalid or has expired.';
    }
} else {
    $error = 'No reset token provided.';
}

if ($valid && is_post()) {
    csrf_verify();
    $pw = $_POST['password'] ?? '';
    $pw2 = $_POST['password_confirm'] ?? '';

    $pwErr = validate_password($pw);
    if ($pwErr) {
        $error = $pwErr;
    } elseif ($pw !== $pw2) {
        $error = 'Passwords do not match.';
    } else {
        $userStmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $userStmt->execute([$reset['user_id']]);
        $user = $userStmt->fetch();
        if (!$user) {
            $error = 'Account not found.';
        } else {
            $hash = password_hash($pw, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $user['id']]);
            $pdo->prepare("UPDATE password_resets SET used = 1 WHERE id = ?")->execute([$reset['id']]);
            $success = 'Your password has been reset. You can now log in.';
            $valid = false;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reset password — Fresh</title>
<link rel="stylesheet" href="css/style.css">
<script src="js/theme.js"></script>
<?php head_meta(''); ?>
</head>
<body>

<header class="topbar">
  <div class="topbar-inner">
    <a href="login.php" class="brand"><span class="leaf">&#127807;</span> Fresh</a>
    <button type="button" class="theme-toggle" data-theme-toggle aria-label="Switch to dark mode"></button>
  </div>
</header>

<div class="auth-wrap">
  <div class="auth-tabs">
    <a href="login.php" class="auth-tab-link">Log in</a>
    <a href="forgot_password.php" class="auth-tab-link active">Reset password</a>
  </div>
  <div class="auth-tab-body">
    <span class="role-tag">New password</span>

    <?php if ($error): ?>
      <div class="form-error"><?= h($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="form-success"><?= h($success) ?></div>
      <a href="login.php" class="btn btn-dark btn-block">Go to log in</a>
    <?php elseif ($valid): ?>
      <form method="POST" action="<?= h('?token=' . urlencode($token)) ?>">
        <?= csrf_field() ?>
        <div class="field">
          <label for="password">New password</label>
          <input type="password" id="password" name="password" minlength="8" required autocomplete="new-password">
        </div>
        <div class="field">
          <label for="password_confirm">Confirm new password</label>
          <input type="password" id="password_confirm" name="password_confirm" minlength="8" required autocomplete="new-password">
        </div>
        <button type="submit" class="btn btn-dark btn-block">Reset password</button>
      </form>
    <?php endif; ?>
    <div class="auth-switch"><a href="forgot_password.php">Request a new link</a></div>
  </div>
</div>

<script src="js/app.js" defer></script>
</body>
</html>
