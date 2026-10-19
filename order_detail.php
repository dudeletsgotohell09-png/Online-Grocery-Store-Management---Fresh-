<?php
require_once __DIR__ . '/config.php';
require_role('customer', '');

$orderId = $_GET['id'] ?? '';
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
$stmt->execute([$orderId, $_SESSION['user_id']]);
$order = $stmt->fetch();
if (!$order) redirect('order_history.php');

$itemStmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
$itemStmt->execute([$order['id']]);
$items = $itemStmt->fetchAll();

$histStmt = $pdo->prepare("SELECT * FROM order_status_history WHERE order_id = ? ORDER BY created_at ASC");
$histStmt->execute([$order['id']]);
$history = $histStmt->fetchAll();

$flash = '';
$flashKind = 'success';

/* Handle cancel order. */
if (is_post() && ($_POST['action'] ?? '') === 'cancel') {
    csrf_verify();
    if ($order['status'] === 'Processing') {
        $pdo->beginTransaction();
        try {
            $pdo->prepare("UPDATE orders SET status = 'Cancelled' WHERE id = ? AND status = 'Processing'")
                ->execute([$order['id']]);
            log_order_status($pdo, $order['id'], 'Cancelled', 'Cancelled by customer', (int)$_SESSION['user_id']);
            // Restock items.
            $restock = $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
            foreach ($items as $it) {
                if ($it['product_id']) {
                    $restock->execute([$it['qty'], $it['product_id']]);
                    log_stock($pdo, (int)$it['product_id'], (int)$it['qty'], 'restock', 'Order ' . $order['id'] . ' cancelled', (int)$_SESSION['user_id']);
                }
            }
            $pdo->commit();
            $order['status'] = 'Cancelled';
            $flash = 'Your order has been cancelled.';
            $histStmt->execute([$order['id']]);
            $history = $histStmt->fetchAll();
        } catch (Exception $e) {
            $pdo->rollBack();
            $flash = 'Could not cancel the order. Please try again.';
            $flashKind = 'error';
        }
    } else {
        $flash = 'Only Processing orders can be cancelled.';
        $flashKind = 'error';
    }
}

/* Handle reorder — add all items back to cart. */
if (is_post() && ($_POST['action'] ?? '') === 'reorder') {
    csrf_verify();
    $added = 0;
    foreach ($items as $it) {
        if (!$it['product_id']) continue;
        $new = cart_add($pdo, (int)$it['product_id'], (int)$it['qty']);
        if ($new > 0) $added++;
    }
    if ($added > 0) {
        redirect('cart.php');
    } else {
        $flash = 'These items are no longer in stock.';
        $flashKind = 'error';
    }
}

$canCancel = $order['status'] === 'Processing';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Order <?= h($order['id']) ?> — Fresh</title>
<link rel="stylesheet" href="css/style.css">
<script src="js/theme.js"></script>
<?php if ($flash): ?>
<meta name="flash-toast" content="<?= h($flash) ?>|<?= h($flashKind) ?>">
<?php endif; ?>
<?php head_meta(''); ?>
</head>
<body>

<?php customer_header('home'); ?>

<div class="container">
  <?php breadcrumbs([['Home', 'customer/dashboard.php'], ['Orders', 'order_history.php'], [$order['id'], null]]); ?>

  <div class="dash-hero" style="display:flex; justify-content:space-between; align-items:flex-start; gap:14px; flex-wrap:wrap;">
    <div>
      <h1 style="margin-top:0;">Order <?= h($order['id']) ?></h1>
      <p style="margin:0;">Placed <?= date('M j, Y · g:ia', strtotime($order['created_at'])) ?></p>
    </div>
    <span class="badge <?= badge_class($order['status']) ?>" style="font-size:0.85rem; padding: 8px 16px;"><?= h($order['status']) ?></span>
  </div>

  <div class="order-detail-wrap">
    <div>
      <div class="panel">
        <h3>Items</h3>
        <?php foreach ($items as $it): ?>
          <div class="oi-row"><span><?= h($it['product_name']) ?> &times; <?= $it['qty'] ?></span><span>Rs. <?= number_format($it['price'] * $it['qty'], 0) ?></span></div>
        <?php endforeach; ?>
        <div style="border-top: 1px solid var(--border); margin-top: 14px; padding-top: 12px;">
          <div class="summary-line"><span>Subtotal</span><span>Rs. <?= number_format($order['subtotal'], 0) ?></span></div>
          <div class="summary-line"><span>Delivery</span><span><?= $order['delivery_fee'] > 0 ? 'Rs. ' . number_format($order['delivery_fee'], 0) : 'FREE' ?></span></div>
          <?php if ($order['discount'] > 0): ?>
          <div class="summary-line" style="color:var(--green-dark);"><span>Discount</span><span>− Rs. <?= number_format($order['discount'], 0) ?></span></div>
          <?php endif; ?>
          <div class="total-line"><span>Total paid</span><span>Rs. <?= number_format($order['total'], 0) ?></span></div>
        </div>
      </div>

      <div class="panel">
        <h3>Delivery details</h3>
        <p style="margin:0; color: var(--ink);"><strong><?= h($order['customer_name']) ?></strong></p>
        <p style="margin:4px 0 0; color: var(--ink-soft);"><?= h($order['address']) ?></p>
        <?php if ($order['phone']): ?>
          <p style="margin:4px 0 0; color: var(--ink-soft);">📞 <?= h($order['phone']) ?></p>
        <?php endif; ?>
      </div>

      <?php if ($canCancel || $order['status'] !== 'Cancelled'): ?>
      <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <form method="POST" action="order_detail.php?id=<?= urlencode($order['id']) ?>">
          <?= csrf_field() ?>
          <button type="submit" name="action" value="reorder" class="btn btn-primary btn-sm">Reorder these items</button>
        </form>
        <?php if ($canCancel): ?>
        <form method="POST" action="order_detail.php?id=<?= urlencode($order['id']) ?>">
          <?= csrf_field() ?>
          <button type="submit" name="action" value="cancel" class="btn btn-danger btn-sm" data-confirm="Cancel this order? Items will be restocked.">Cancel order</button>
        </form>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>

    <aside class="side-panel">
      <div class="side-eyebrow">Timeline</div>
      <h3>Status</h3>
      <ul class="timeline">
        <?php foreach ($history as $h): ?>
          <li class="done">
            <div class="tl-status" style="<?= $h['status'] === 'Cancelled' ? 'color: var(--berry);' : '' ?>"><?= h($h['status']) ?></div>
            <div class="tl-time"><?= date('M j, Y · g:ia', strtotime($h['created_at'])) ?></div>
            <?php if (!empty($h['note'])): ?>
              <div class="tl-note"><?= h($h['note']) ?></div>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
        <?php if (empty($history)): ?>
          <li class="done"><div class="tl-status">Order placed</div></li>
        <?php endif; ?>
      </ul>
      <p style="font-size:0.78rem; margin-top:14px;" class="muted">Current status: <strong style="color: var(--ink);"><?= h($order['status']) ?></strong></p>
    </aside>
  </div>
</div>

<?php customer_footer(''); ?>
</body>
</html>
