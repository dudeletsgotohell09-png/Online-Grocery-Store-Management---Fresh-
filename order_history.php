<?php
require_once __DIR__ . '/config.php';
require_role('customer', '');

$stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$orders = $stmt->fetchAll();

$itemStmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Orders — Fresh</title>
<link rel="stylesheet" href="css/style.css">
<script src="js/theme.js"></script>
<?php head_meta(''); ?>
</head>
<body>

<?php customer_header('home'); ?>

<div class="container">
  <?php breadcrumbs([['Home', 'customer/dashboard.php'], ['Orders', null]]); ?>
  <div class="dash-hero"><h1 style="margin-top:26px;">Your orders</h1></div>

  <?php if (empty($orders)): ?>
    <div class="empty-state">
      <div class="glyph">📦</div>
      <h3>No orders yet</h3>
      <p>Items you order will show up here.</p>
      <a href="shop.php" class="btn btn-primary" style="margin-top:10px;">Go to shop</a>
    </div>
  <?php else: ?>
    <div style="padding-bottom:60px;">
    <?php foreach ($orders as $o):
        $itemStmt->execute([$o['id']]);
        $orderItems = $itemStmt->fetchAll();
        $canReorder = $o['status'] !== 'Cancelled';
    ?>
      <div class="order-card">
        <div class="order-card-head">
          <div>
            <div class="order-id">Order <?= h($o['id']) ?></div>
            <div class="order-meta"><?= date('M j, Y · g:ia', strtotime($o['created_at'])) ?> &middot; Rs. <?= number_format($o['total'], 0) ?></div>
          </div>
          <span class="badge <?= badge_class($o['status']) ?>"><?= h($o['status']) ?></span>
        </div>
        <details class="order-items">
          <summary>View <?= count($orderItems) ?> item<?= count($orderItems) > 1 ? 's' : '' ?></summary>
          <?php foreach ($orderItems as $it): ?>
            <div class="oi-row"><span><?= h($it['product_name']) ?> &times; <?= $it['qty'] ?></span><span>Rs. <?= number_format($it['price'] * $it['qty'], 0) ?></span></div>
          <?php endforeach; ?>
        </details>
        <div style="display:flex; gap:8px; margin-top:12px; flex-wrap:wrap;">
          <a href="order_detail.php?id=<?= urlencode($o['id']) ?>" class="btn btn-ghost btn-sm">View details</a>
          <?php if ($canReorder): ?>
          <form method="POST" action="order_detail.php?id=<?= urlencode($o['id']) ?>" style="display:inline;">
            <?= csrf_field() ?>
            <button type="submit" name="action" value="reorder" class="btn btn-primary btn-sm">Reorder</button>
          </form>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php customer_footer(''); ?>
</body>
</html>
