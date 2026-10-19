<?php
require_once __DIR__ . '/config.php';

if (isset($_SESSION['user_id'])) {
    redirect($_SESSION['role'] === 'admin' ? 'admin/dashboard.php' : 'customer/dashboard.php');
}

$error = '';
$success = '';
$email = '';

if (is_post()) {
    csrf_verify();
    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Always show the same generic success message — don't leak which emails exist.
        $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ? AND role = 'customer'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user) {
            $token = bin2hex(random_bytes(32));
            $expires = (new DateTime('+1 hour'))->format('Y-m-d H:i:s');
            $pdo->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)")
                ->execute([$user['id'], $token, $expires]);

            $link = base_url() . '/reset_password.php?token=' . $token;

            // Send the email via the mail helper. If MAIL_LOG_ONLY is true (default
            // for dev environments without SMTP), the body is written to PHP's
            // error_log instead of actually being sent.
            $body = '<p>Hi ' . h($user['name']) . ',</p>'
                  . '<p>We received a request to reset the password for your Fresh Grocery account.</p>'
                  . '<p>Click the button below to choose a new password. This link will expire in 1 hour.</p>'
                  . '<p style="text-align:center; margin: 24px 0;">'
                  . '<a href="' . $link . '" style="background:#EE8A22;color:#fff;padding:12px 28px;border-radius:999px;font-weight:700;text-decoration:none;display:inline-block;">Reset password</a>'
                  . '</p>'
                  . '<p style="font-size:0.85rem;color:#5C6660;">If the button doesn\'t work, copy this link into your browser:</p>'
                  . '<p style="font-size:0.8rem;word-break:break-all;background:#F5F6F2;padding:10px;border-radius:8px;">' . $link . '</p>'
                  . '<p style="font-size:0.85rem;color:#5C6660;">If you didn\'t request a password reset, you can safely ignore this email.</p>';
            send_mail($email, 'Reset your Fresh Grocery password', mail_template('Password reset', $body));

            $success = "If that email matches an account, a reset link has been sent to it.";
            // In dev / log-only mode, surface the link on screen so the flow can be tested.
            if (MAIL_LOG_ONLY) {
                $success .= '<br><br><strong style="color:var(--orange-dark);">Dev mode:</strong> SMTP not configured. '
                          . '<a href="reset_password.php?token=' . h($token) . '">Open the reset link directly</a>.';
            }
            $email = '';
        } else {
            $success = "If that email matches an account, a reset link has been sent to it.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Forgot password — Fresh</title>
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
    <a href="forgot_password.php" class="auth-tab-link active">Forgot password</a>
  </div>
  <div class="auth-tab-body">
    <span class="role-tag">Reset password</span>

    <?php if ($error): ?>
      <div class="form-error"><?= h($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="form-success"><?= $success ?></div>
    <?php endif; ?>

    <form method="POST" action="forgot_password.php">
      <?= csrf_field() ?>
      <div class="field">
        <label for="email">Account email</label>
        <input type="email" id="email" name="email" value="<?= h($email) ?>" required autofocus autocomplete="email">
      </div>
      <button type="submit" class="btn btn-dark btn-block">Send reset link</button>
    </form>
    <div class="auth-switch"><a href="login.php">← Back to log in</a></div>
  </div>
</div>

<script src="js/app.js" defer></script>
</body>
</html>
