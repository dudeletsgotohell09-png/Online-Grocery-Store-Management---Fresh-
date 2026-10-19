<?php
require_once __DIR__ . '/config.php';
require_role('customer', '');

$error = $success = '';
$name  = $_SESSION['name'];
$email = $_SESSION['email'] ?? '';
$subject = '';

if (is_post()) {
    csrf_verify();
    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $body    = trim($_POST['body'] ?? '');

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $body === '') {
        $error = 'Please fill in your name, a valid email and a message.';
    } else {
        $stmt = $pdo->prepare("INSERT INTO messages (user_id, name, email, subject, body) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $name, $email, $subject, $body]);
        $success = 'Thanks! Your message has been sent to the Fresh team.';
        $subject = '';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Contact — Fresh</title>
<link rel="stylesheet" href="css/style.css">
<script src="js/theme.js"></script>
<style>
.contact-grid{ display:grid; grid-template-columns: 1fr 320px; gap:30px; align-items:start; padding-bottom:60px; }
@media (max-width: 860px){ .contact-grid{ grid-template-columns: 1fr; } }
.field textarea{ width:100%; padding:11px 14px; border-radius: var(--radius-sm); border:1.5px solid var(--border); background: var(--surface); color: var(--ink); font-family:inherit; font-size:0.95rem; min-height:120px; resize:vertical; }
.field textarea:focus{ border-color: var(--green); outline:none; }
</style>
<?php head_meta(''); ?>
</head>
<body>

<?php customer_header('contact'); ?>

<div class="container">
  <?php breadcrumbs([['Home', 'customer/dashboard.php'], ['Contact', null]]); ?>
  <div class="dash-hero">
    <h1 style="margin-top:26px;">Contact us</h1>
    <p>Questions about an order or our products? Send us a message.</p>
  </div>

  <div class="contact-grid">
    <div class="panel">
      <?php if ($error): ?><div class="form-error"><?= h($error) ?></div><?php endif; ?>
      <?php if ($success): ?><div class="form-success"><?= h($success) ?></div><?php endif; ?>
      <form method="POST" action="contact.php">
        <?= csrf_field() ?>
        <div class="field"><label for="name">Your name</label><input type="text" id="name" name="name" value="<?= h($name) ?>" required></div>
        <div class="field"><label for="email">Email</label><input type="email" id="email" name="email" value="<?= h($email) ?>" required></div>
        <div class="field"><label for="subject">Subject</label><input type="text" id="subject" name="subject" value="<?= h($subject) ?>"></div>
        <div class="field"><label for="body">Message</label><textarea id="body" name="body" required></textarea></div>
        <button type="submit" class="btn btn-primary">Send message</button>
      </form>
    </div>
    <aside class="side-panel">
      <div class="side-eyebrow">Get in touch</div>
      <h3>Fresh Grocery</h3>
      <p>&#128231; support@freshgrocery.example</p>
      <p>&#128222; 011 234 5678</p>
      <p>&#128338; Daily, 8:00 AM – 8:00 PM</p>
      <a href="shop.php" class="btn btn-ghost btn-sm btn-block">Back to shop</a>
    </aside>
  </div>
</div>

<?php customer_footer(''); ?>
</body>
</html>
