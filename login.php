<?php
require_once __DIR__ . '/config.php';

if (isset($_SESSION['user_id'])) {
    redirect($_SESSION['role'] === 'admin' ? 'admin/dashboard.php' : 'customer/dashboard.php');
}

$activeTab = ($_GET['role'] ?? 'customer') === 'admin' ? 'admin' : 'customer';
$error = '';
$flash = '';

if (is_post()) {
    csrf_verify();
    $role     = ($_POST['role'] ?? 'customer') === 'admin' ? 'admin' : 'customer';
    $activeTab = $role;
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Please enter both email and password.';
    } elseif (!check_login_throttle($pdo, $email)) {
        $error = 'Too many failed attempts. Please try again in ' . LOGIN_LOCKOUT_MINUTES . ' minutes.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = ?");
        $stmt->execute([$email, $role]);
        $user = $stmt->fetch();

        if ($user && $user['status'] === 'disabled') {
            $error = 'This account has been disabled. Please contact support.';
            record_login_attempt($pdo, $email, false);
        } elseif ($user && password_verify($password, $user['password'])) {
            record_login_attempt($pdo, $email, true);
            login_user($user);
            redirect($user['role'] === 'admin' ? 'admin/dashboard.php' : 'customer/dashboard.php');
        } else {
            record_login_attempt($pdo, $email, false);
            $error = ucfirst($role) . ' email or password is incorrect.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Log in — Fresh</title>
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
    <a href="login.php?role=customer" class="auth-tab-link <?= $activeTab === 'customer' ? 'active' : '' ?>">Customer Login</a>
    <a href="login.php?role=admin" class="auth-tab-link <?= $activeTab === 'admin' ? 'active' : '' ?>">Admin Login</a>
  </div>

  <div class="auth-tab-body">
    <span class="role-tag"><?= $activeTab === 'admin' ? 'Store admin access' : 'Customer access' ?></span>

    <?php if ($error): ?>
      <div class="form-error"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="login.php">
      <?= csrf_field() ?>
      <input type="hidden" name="role" value="<?= $activeTab ?>">
      <div class="field">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" placeholder="you@example.com" required autofocus autocomplete="email">
      </div>
      <div class="field">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required autocomplete="current-password">
      </div>
      <?php if ($activeTab === 'customer'): ?>
        <div style="text-align:right; margin:-6px 0 14px;">
          <a href="forgot_password.php" style="font-size:0.8rem; color:var(--green-dark); font-weight:700;">Forgot password?</a>
        </div>
      <?php endif; ?>
      <button type="submit" class="btn btn-dark btn-block"><?= $activeTab === 'admin' ? 'Log in as Admin' : 'Log in' ?></button>
    </form>

    <?php if ($activeTab === 'customer'): ?>
      <div class="auth-switch">New here? <a href="register.php">Create a customer account</a></div>
    <?php else: ?>
      <div class="auth-switch">Admin accounts are created by the system owner, not self-registered.</div>
    <?php endif; ?>
  </div>
</div>

<script src="js/app.js" defer></script>
</body>
</html>
