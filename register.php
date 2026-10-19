<?php
require_once __DIR__ . '/config.php';

if (isset($_SESSION['user_id'])) {
    redirect($_SESSION['role'] === 'admin' ? 'admin/dashboard.php' : 'customer/dashboard.php');
}

$error = '';
$name = $email = '';

if (is_post()) {
    csrf_verify();
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($name === '' || $email === '') {
        $error = 'Please fill in name and email.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $pwErr = validate_password($password);
        if ($pwErr) {
            $error = $pwErr;
        } else {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'An account with this email already exists.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'customer')");
                $stmt->execute([$name, $email, $hash]);

                $user = [
                    'id'     => (int)$pdo->lastInsertId(),
                    'name'   => $name,
                    'email'  => $email,
                    'role'   => 'customer',
                    'status' => 'active',
                ];
                login_user($user);
                redirect('customer/dashboard.php');
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register — Fresh</title>
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
    <a href="register.php" class="auth-tab-link active">Register</a>
  </div>

  <div class="auth-tab-body">
    <span class="role-tag">New customer account</span>

    <?php if ($error): ?>
      <div class="form-error"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="register.php">
      <?= csrf_field() ?>
      <div class="field">
        <label for="name">Full name</label>
        <input type="text" id="name" name="name" value="<?= h($name) ?>" required autocomplete="name">
      </div>
      <div class="field">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="<?= h($email) ?>" required autocomplete="email">
      </div>
      <div class="field">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" minlength="8" required autocomplete="new-password">
        <div style="font-size:0.75rem; color:var(--ink-faint); margin-top:4px;">
          At least 8 characters with one letter and one number.
        </div>
      </div>
      <button type="submit" class="btn btn-dark btn-block">Create account</button>
    </form>

    <div class="auth-switch">Already have an account? <a href="login.php">Log in</a></div>
  </div>
</div>

<script src="js/app.js" defer></script>
</body>
</html>
