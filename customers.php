<?php
$root_path = '../';
require_once $root_path . 'config.php';
require_role('admin', $root_path);

/* Toggle status (active/disabled). */
if (is_post() && ($_POST['action'] ?? '') === 'toggle') {
    csrf_verify();
    $cid = (int)($_POST['customer_id'] ?? 0);
    $pdo->prepare("UPDATE users SET status = IF(status='active','disabled','active') WHERE id = ? AND role = 'customer'")
        ->execute([$cid]);
    redirect('customers.php');
}

$search = trim($_GET['q'] ?? '');
$sql = "SELECT u.*,
        (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id AND o.status != 'Cancelled') AS order_count,
        (SELECT COALESCE(SUM(total),0) FROM orders o WHERE o.user_id = u.id AND o.status != 'Cancelled') AS total_spent,
        (SELECT MAX(created_at) FROM orders WHERE user_id = u.id) AS last_order
        FROM users u WHERE u.role = 'customer'";
$params = [];
if ($search !== '') {
    $sql .= " AND (u.name LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$sql .= " ORDER BY u.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Customers — Fresh Admin</title>
<link rel="stylesheet" href="../css/style.css">
<script src="../js/theme.js"></script>
<?php head_meta('../'); ?>
</head>
<body>

<?php admin_header('customers'); ?>

<div class="container" style="padding-bottom:60px;">
  <div class="dash-hero"><h1 style="margin-top:26px;">Customers</h1></div>

  <div class="filter-bar">
    <form method="GET" style="display:contents;">
      <div class="field">
        <label for="q">Search</label>
        <input type="text" id="q" name="q" value="<?= h($search) ?>" placeholder="Name or email…">
      </div>
      <div class="filter-actions">
        <button type="submit" class="btn btn-ghost btn-sm">Filter</button>
        <a href="customers.php" class="btn btn-ghost btn-sm">Clear</a>
      </div>
    </form>
  </div>

  <?php if (empty($customers)): ?>
    <div class="empty-state">
      <div class="glyph">👥</div>
      <h3>No customers yet</h3>
      <p>Registered customers will appear here.</p>
    </div>
  <?php else: ?>
    <table class="data-table">
      <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Joined</th><th>Orders</th><th>Total spent</th><th>Last order</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($customers as $c): ?>
        <tr>
          <td data-label="Name"><?= h($c['name']) ?></td>
          <td data-label="Email"><?= h($c['email']) ?></td>
          <td data-label="Phone"><?= h($c['phone'] ?: '—') ?></td>
          <td data-label="Joined"><?= date('M j, Y', strtotime($c['created_at'])) ?></td>
          <td data-label="Orders"><?= (int)$c['order_count'] ?></td>
          <td data-label="Total spent">Rs. <?= number_format($c['total_spent'], 0) ?></td>
          <td data-label="Last order"><?= $c['last_order'] ? date('M j, Y', strtotime($c['last_order'])) : '—' ?></td>
          <td data-label="Status">
            <span class="badge <?= $c['status'] === 'active' ? 'badge-delivered' : 'badge-cancelled' ?>"><?= h($c['status']) ?></span>
          </td>
          <td>
            <form method="POST" style="display:inline;">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="customer_id" value="<?= (int)$c['id'] ?>">
              <button type="submit" class="<?= $c['status'] === 'active' ? 'del-link' : 'edit-link' ?>" style="background:none; border:none; cursor:pointer; font-weight:700; font-size:0.8rem;" data-confirm="<?= $c['status'] === 'active' ? 'Disable this customer account?' : 'Re-enable this customer?' ?>">
                <?= $c['status'] === 'active' ? 'Disable' : 'Enable' ?>
              </button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<footer class="site-footer"><div class="container"><p>© 2026 Fresh Grocery. All rights reserved..</p></div></footer>
<script src="../js/app.js" defer></script>
</body>
</html>
