<?php
$root_path = '../';
require_once $root_path . 'config.php';
require_role('admin', $root_path);

$orderId = $_GET['id'] ?? '';
$stmt = $pdo->prepare("SELECT o.*, u.email FROM orders o JOIN users u ON u.id = o.user_id WHERE o.id = ?");
$stmt->execute([$orderId]);
$order = $stmt->fetch();
if (!$order) redirect('orders.php');

$itemStmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
$itemStmt->execute([$order['id']]);
$items = $itemStmt->fetchAll();

$histStmt = $pdo->prepare("
    SELECT h.*, u.name AS changed_by_name
    FROM order_status_history h
    LEFT JOIN users u ON u.id = h.changed_by
    WHERE h.order_id = ?
    ORDER BY h.created_at ASC
");
$histStmt->execute([$order['id']]);
$history = $histStmt->fetchAll();

$flash = '';
$flashKind = 'success';

if (is_post() && ($_POST['action'] ?? '') === 'update_status') {
    csrf_verify();
    $newStatus = $_POST['status'] ?? '';
    $note = trim($_POST['note'] ?? '');
    $allowed = ['Processing','Shipped','Delivered','Cancelled'];
    if (in_array($newStatus, $allowed) && $newStatus !== $order['status']) {
        $pdo->beginTransaction();
        try {
            $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?")->execute([$newStatus, $order['id']]);
            log_order_status($pdo, $order['id'], $newStatus, $note ?: null, (int)$_SESSION['user_id']);

            if ($newStatus === 'Cancelled' && $order['status'] !== 'Cancelled') {
                $restock = $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
                foreach ($items as $it) {
                    if ($it['product_id']) {
                        $restock->execute([$it['qty'], $it['product_id']]);
                        log_stock($pdo, (int)$it['product_id'], (int)$it['qty'], 'restock', 'Order ' . $order['id'] . ' cancelled', (int)$_SESSION['user_id']);
                    }
                }
            } elseif ($order['status'] === 'Cancelled' && $newStatus !== 'Cancelled') {
                $deduct = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?");
                foreach ($items as $it) {
                    if ($it['product_id']) {
                        $deduct->execute([$it['qty'], $it['product_id'], $it['qty']]);
                        log_stock($pdo, (int)$it['product_id'], -(int)$it['qty'], 'order', 'Order ' . $order['id'] . ' reactivated', (int)$_SESSION['user_id']);
                    }
                }
            }
            $pdo->commit();
            $order['status'] = $newStatus;
            $flash = "Order status updated to {$newStatus}.";
            $histStmt->execute([$order['id']]);
            $history = $histStmt->fetchAll();
        } catch (Exception $e) {
            $pdo->rollBack();
            $flash = 'Could not update status.';
            $flashKind = 'error';
        }
    }
}

if (is_post() && ($_POST['action'] ?? '') === 'save_note') {
    csrf_verify();
    $note = trim($_POST['admin_note'] ?? '');
    $pdo->prepare("UPDATE orders SET admin_note = ? WHERE id = ?")->execute([$note, $order['id']]);
    $order['admin_note'] = $note;
    $flash = 'Note saved.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Order <?= h($order['id']) ?> — Fresh Admin</title>
<link rel="stylesheet" href="../css/style.css">
<script src="../js/theme.js"></script>
<?php if ($flash): ?>
<meta name="flash-toast" content="<?= h($flash) ?>|<?= h($flashKind) ?>">
<?php endif; ?>
<?php head_meta('../'); ?>
</head>
<body>

<?php admin_header('orders'); ?>

<div class="container" style="padding-bottom:60px;">
  <?php breadcrumbs([['Dashboard', 'dashboard.php'], ['Orders', 'orders.php'], [$order['id'], null]]); ?>

  <div class="dash-hero" style="display:flex; justify-content:space-between; align-items:flex-start; gap:14px; flex-wrap:wrap;">
    <div>
      <h1 style="margin-top:0;">Order <?= h($order['id']) ?></h1>
      <p style="margin:0;">Placed <?= date('M j, Y · g:ia', strtotime($order['created_at'])) ?> · Customer: <?= h($order['customer_name']) ?> (<?= h($order['email']) ?>)</p>
    </div>
    <span class="badge <?= badge_class($order['status']) ?>" style="font-size:0.9rem; padding: 8px 16px;"><?= h($order['status']) ?></span>
  </div>

  <div class="order-detail-wrap">
    <div>
      <div class="panel">
        <h3>Items</h3>
        <table class="data-table">
          <thead><tr><th>Item</th><th>Price</th><th>Qty</th><th>Line total</th></tr></thead>
          <tbody>
          <?php foreach ($items as $it): ?>
            <tr>
              <td data-label="Item"><?= h($it['product_name']) ?></td>
              <td data-label="Price">Rs. <?= number_format($it['price'], 0) ?></td>
              <td data-label="Qty"><?= (int)$it['qty'] ?></td>
              <td data-label="Line total">Rs. <?= number_format($it['price'] * $it['qty'], 0) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <div style="border-top: 1px solid var(--border); margin-top: 14px; padding-top: 12px;">
          <div class="summary-line"><span>Subtotal</span><span>Rs. <?= number_format($order['subtotal'], 0) ?></span></div>
          <div class="summary-line"><span>Delivery</span><span><?= $order['delivery_fee'] > 0 ? 'Rs. ' . number_format($order['delivery_fee'], 0) : 'FREE' ?></span></div>
          <?php if ($order['discount'] > 0): ?>
          <div class="summary-line" style="color:var(--green-dark);"><span>Discount (<?= h($order['coupon_code']) ?>)</span><span>− Rs. <?= number_format($order['discount'], 0) ?></span></div>
          <?php endif; ?>
          <div class="total-line"><span>Total</span><span>Rs. <?= number_format($order['total'], 0) ?></span></div>
        </div>
      </div>

      <div class="panel">
        <h3>Delivery details</h3>
        <p style="margin:0; color: var(--ink);"><strong><?= h($order['customer_name']) ?></strong></p>
        <p style="margin:4px 0 0; color: var(--ink-soft);"><?= h($order['address']) ?></p>
        <?php if ($order['phone']): ?>
          <p style="margin:4px 0 0; color: var(--ink-soft);">📞 <?= h($order['phone']) ?></p>
        <?php endif; ?>
        <?php if ($order['email']): ?>
          <p style="margin:4px 0 0; color: var(--ink-soft);">✉️ <?= h($order['email']) ?></p>
        <?php endif; ?>
      </div>

      <div class="panel">
        <h3>Update status</h3>
        <form method="POST">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="update_status">
          <div class="form-row-2">
            <div class="field">
              <label for="status">New status</label>
              <select id="status" name="status">
                <?php foreach (['Processing','Shipped','Delivered','Cancelled'] as $s): ?>
                  <option value="<?= $s ?>" <?= $s === $order['status'] ? 'selected' : '' ?>><?= $s ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field">
              <label for="note">Note (optional)</label>
              <input type="text" id="note" name="note" placeholder="e.g. handed to courier">
            </div>
          </div>
          <button type="submit" class="btn btn-primary">Update status</button>
        </form>
      </div>

      <div class="panel">
        <h3>Admin note</h3>
        <form method="POST">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="save_note">
          <div class="field">
            <label for="admin_note">Internal note (visible only to admins)</label>
            <textarea id="admin_note" name="admin_note" rows="3" style="width:100%; padding:10px 12px; border-radius: var(--radius-sm); border:1.5px solid var(--border); background: var(--surface); color: var(--ink); font-family:inherit; font-size:0.9rem; resize:vertical;"><?= h($order['admin_note'] ?? '') ?></textarea>
          </div>
          <button type="submit" class="btn btn-primary btn-sm">Save note</button>
        </form>
      </div>
    </div>

    <aside class="side-panel">
      <div class="side-eyebrow">Audit trail</div>
      <h3>Status timeline</h3>
      <ul class="timeline">
        <?php foreach ($history as $i => $h): ?>
          <li class="done">
            <div class="tl-status"><?= h($h['status']) ?></div>
            <div class="tl-time"><?= date('M j, Y · g:ia', strtotime($h['created_at'])) ?></div>
            <?php if (!empty($h['changed_by_name'])): ?>
              <div class="tl-note">by <?= h($h['changed_by_name']) ?></div>
            <?php endif; ?>
            <?php if (!empty($h['note'])): ?>
              <div class="tl-note"><?= h($h['note']) ?></div>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
        <?php if (empty($history)): ?>
          <li class="done"><div class="tl-status">Order placed</div></li>
        <?php endif; ?>
      </ul>
      <p style="font-size:0.78rem; margin-top:14px;" class="muted">All status changes are logged with the responsible admin and timestamp.</p>
    </aside>
  </div>
</div>

<footer class="site-footer"><div class="container"><p>© 2026 Fresh Grocery. All rights reserved..</p></div></footer>
<script src="../js/app.js" defer></script>
</body>
</html>
