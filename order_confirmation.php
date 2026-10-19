<?php
require_once __DIR__ . '/config.php';
require_role('customer', '');

$orderId = $_GET['order'] ?? '';
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
$stmt->execute([$orderId, $_SESSION['user_id']]);
$order = $stmt->fetch();

if (!$order) {
    redirect('shop.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Order confirmed — Fresh</title>
<link rel="stylesheet" href="css/style.css">
<script src="js/theme.js"></script>
<?php head_meta(''); ?>
</head>
<body>

<?php customer_header('shop'); ?>

<div class="container">
  <?php breadcrumbs([['Home', 'customer/dashboard.php'], ['Orders', 'order_history.php'], ['Confirmed', null]]); ?>

  <div class="confirm-wrap">
    <div class="confirm-badge">&#10003;</div>
    <h2>Order confirmed</h2>
    <p>Order <strong><?= h($order['id']) ?></strong> has been placed successfully — total Rs. <?= number_format($order['total'], 0) ?>. We'll notify you as it ships.</p>
    <div style="display:flex; gap:10px; justify-content:center; margin-top:20px; flex-wrap:wrap;">
      <a href="order_detail.php?id=<?= urlencode($order['id']) ?>" class="btn btn-dark">View order details</a>
      <a href="order_history.php" class="btn btn-ghost">Order history</a>
      <a href="shop.php" class="btn btn-primary">Continue shopping</a>
    </div>
  </div>
</div>

<?php customer_footer(''); ?>
</body>
</html>
