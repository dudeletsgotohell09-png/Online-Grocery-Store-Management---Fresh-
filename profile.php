<?php
$root_path = '../';
require_once $root_path . 'config.php';
require_role('customer', $root_path);

$error = '';
$success = '';

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

/* ---- Update profile fields ---- */
if (is_post() && ($_POST['form'] ?? '') === 'profile') {
    csrf_verify();
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $newPassword = $_POST['new_password'] ?? '';
    $currentPassword = $_POST['current_password'] ?? '';

    if ($name === '' || $email === '') {
        $error = 'Name and email cannot be empty.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $user['id']]);
        if ($stmt->fetch()) {
            $error = 'Another account already uses that email.';
        } else {
            // If changing password, require current password.
            if ($newPassword !== '') {
                if (!password_verify($currentPassword, $user['password'])) {
                    $error = 'Current password is incorrect.';
                } else {
                    $pwErr = validate_password($newPassword);
                    if ($pwErr) $error = $pwErr;
                }
            }
            if ($error === '') {
                if ($newPassword !== '') {
                    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ?, password = ? WHERE id = ?");
                    $stmt->execute([$name, $email, $phone, $hash, $user['id']]);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?");
                    $stmt->execute([$name, $email, $phone, $user['id']]);
                }
                $_SESSION['name'] = $name;
                $_SESSION['email'] = $email;
                $user['name'] = $name;
                $user['email'] = $email;
                $user['phone'] = $phone;
                $success = 'Profile updated successfully.';
            }
        }
    }
}

/* ---- Address add ---- */
if (is_post() && ($_POST['form'] ?? '') === 'address_add') {
    csrf_verify();
    $label   = trim($_POST['label'] ?? 'Home');
    $aname   = trim($_POST['aname'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city    = trim($_POST['city'] ?? '');
    $postal  = trim($_POST['postal'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');
    $isDefault = !empty($_POST['is_default']);

    if ($aname === '' || $address === '' || $city === '' || $postal === '' || $phone === '') {
        $error = 'Please fill in every address field.';
    } else {
        if ($isDefault) {
            $pdo->prepare("UPDATE addresses SET is_default = 0 WHERE user_id = ?")->execute([$_SESSION['user_id']]);
        }
        $stmt = $pdo->prepare("INSERT INTO addresses (user_id, label, name, address, city, postal, phone, is_default) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $label, $aname, $address, $city, $postal, $phone, $isDefault ? 1 : 0]);
        $success = 'Address added.';
    }
}

/* ---- Address delete ---- */
if (is_post() && ($_POST['form'] ?? '') === 'address_delete') {
    csrf_verify();
    $aid = (int)($_POST['address_id'] ?? 0);
    $pdo->prepare("DELETE FROM addresses WHERE id = ? AND user_id = ?")->execute([$aid, $_SESSION['user_id']]);
    $success = 'Address removed.';
}

/* ---- Address set default ---- */
if (is_post() && ($_POST['form'] ?? '') === 'address_default') {
    csrf_verify();
    $aid = (int)($_POST['address_id'] ?? 0);
    $pdo->prepare("UPDATE addresses SET is_default = 0 WHERE user_id = ?")->execute([$_SESSION['user_id']]);
    $pdo->prepare("UPDATE addresses SET is_default = 1 WHERE id = ? AND user_id = ?")->execute([$aid, $_SESSION['user_id']]);
    $success = 'Default address updated.';
}

$stmt = $pdo->prepare("SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC");
$stmt->execute([$_SESSION['user_id']]);
$addresses = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Profile — Fresh</title>
<link rel="stylesheet" href="../css/style.css">
<script src="../js/theme.js"></script>
<?php head_meta('../'); ?>
</head>
<body>

<?php customer_header('home', '../'); ?>

<div class="container">
  <?php breadcrumbs([['Home', 'dashboard.php'], ['Profile', null]]); ?>
  <div class="dash-hero"><h1 style="margin-top:26px;">My profile</h1></div>

  <?php if ($error): ?><div class="form-error" style="max-width:760px;"><?= h($error) ?></div><?php endif; ?>
  <?php if ($success): ?>
    <div class="form-success" style="max-width:760px;"><?= h($success) ?></div>
    <meta name="flash-toast" content="<?= h($success) ?>|success">
  <?php endif; ?>

  <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 24px; align-items:start; padding-bottom: 60px;" class="profile-grid">
    <style>@media (max-width: 760px){ .profile-grid{ grid-template-columns: 1fr !important; } }</style>
    <div>
      <div class="panel">
        <h3>Account details</h3>
        <form method="POST">
          <?= csrf_field() ?>
          <input type="hidden" name="form" value="profile">
          <div class="field"><label>Full name</label><input type="text" name="name" value="<?= h($user['name']) ?>" required></div>
          <div class="field"><label>Email</label><input type="email" name="email" value="<?= h($user['email']) ?>" required></div>
          <div class="field"><label>Phone</label><input type="tel" name="phone" value="<?= h($user['phone'] ?? '') ?>" placeholder="077 123 4567"></div>
          <hr style="border:none; border-top:1px solid var(--border); margin: 18px 0;">
          <div class="field"><label>Current password (only needed to change password)</label><input type="password" name="current_password" autocomplete="current-password"></div>
          <div class="field"><label>New password (leave blank to keep current)</label><input type="password" name="new_password" autocomplete="new-password"></div>
          <button type="submit" class="btn btn-primary btn-block">Save changes</button>
        </form>
      </div>
    </div>

    <div>
      <div class="panel">
        <h3>Saved addresses</h3>
        <?php if (empty($addresses)): ?>
          <p class="muted">No saved addresses yet. Add one to speed up checkout.</p>
        <?php else: ?>
          <div class="address-grid">
            <?php foreach ($addresses as $a): ?>
              <div class="address-card <?= $a['is_default'] ? 'default' : '' ?>">
                <div class="ac-label"><?= h($a['label']) ?> <?= $a['is_default'] ? '· Default' : '' ?></div>
                <div class="ac-name"><?= h($a['name']) ?></div>
                <div class="ac-body">
                  <?= h($a['address']) ?><br>
                  <?= h($a['city']) ?> <?= h($a['postal']) ?><br>
                  📞 <?= h($a['phone']) ?>
                </div>
                <div class="ac-actions">
                  <?php if (!$a['is_default']): ?>
                  <form method="POST" style="display:inline;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="form" value="address_default">
                    <input type="hidden" name="address_id" value="<?= (int)$a['id'] ?>">
                    <button class="btn btn-ghost btn-sm" type="submit">Set default</button>
                  </form>
                  <?php endif; ?>
                  <form method="POST" style="display:inline;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="form" value="address_delete">
                    <input type="hidden" name="address_id" value="<?= (int)$a['id'] ?>">
                    <button class="btn btn-ghost btn-sm" style="color:var(--berry);" type="submit" data-confirm="Delete this address?">Delete</button>
                  </form>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="panel">
        <h3>Add new address</h3>
        <form method="POST">
          <?= csrf_field() ?>
          <input type="hidden" name="form" value="address_add">
          <div class="field"><label>Label</label><input type="text" name="label" placeholder="Home, Work, …" value="Home"></div>
          <div class="field"><label>Full name</label><input type="text" name="aname" value="<?= h($user['name']) ?>" required></div>
          <div class="field"><label>Address</label><input type="text" name="address" required></div>
          <div class="form-row-2">
            <div class="field"><label>City</label><input type="text" name="city" required></div>
            <div class="field"><label>Postal code</label><input type="text" name="postal" required></div>
          </div>
          <div class="field"><label>Phone</label><input type="tel" name="phone" value="<?= h($user['phone'] ?? '') ?>" required></div>
          <div class="field" style="display:flex; align-items:center; gap:8px;">
            <input type="checkbox" name="is_default" id="is_default" value="1">
            <label for="is_default" style="margin:0;">Make this my default address</label>
          </div>
          <button type="submit" class="btn btn-primary btn-block">Save address</button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php customer_footer('../'); ?>
</body>
</html>
