<?php
$root_path = '../';
require_once $root_path . 'config.php';
require_role('admin', $root_path);

if (is_post() && ($_POST['action'] ?? '') === 'update_status') {
    csrf_verify();
    $orderId = $_POST['order_id'] ?? '';
    $status  = $_POST['status'] ?? '';
    $note    = trim($_POST['note'] ?? '');
    $allowed = ['Processing', 'Shipped', 'Delivered', 'Cancelled'];
    if (in_array($status, $allowed)) {
        // Fetch current.
        $stmt = $pdo->prepare("SELECT status FROM orders WHERE id = ?");
        $stmt->execute([$orderId]);
        $current = $stmt->fetchColumn();
        if ($current !== $status) {
            $pdo->beginTransaction();
            try {
                $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?")->execute([$status, $orderId]);
                log_order_status($pdo, $orderId, $status, $note ?: null, (int)$_SESSION['user_id']);

                // Restock on cancel, deduct again on un-cancel (only if items still reference products).
                if ($status === 'Cancelled' && $current !== 'Cancelled') {
                    $items = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
                    $items->execute([$orderId]);
                    $restock = $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
                    foreach ($items->fetchAll() as $it) {
                        if ($it['product_id']) {
                            $restock->execute([$it['qty'], $it['product_id']]);
                            log_stock($pdo, (int)$it['product_id'], (int)$it['qty'], 'restock', 'Order ' . $orderId . ' cancelled', (int)$_SESSION['user_id']);
                        }
                    }
                } elseif ($current === 'Cancelled' && $status !== 'Cancelled') {
                    // Re-deduct stock.
                    $items = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
                    $items->execute([$orderId]);
                    $deduct = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?");
                    foreach ($items->fetchAll() as $it) {
                        if ($it['product_id']) {
                            $deduct->execute([$it['qty'], $it['product_id'], $it['qty']]);
                            log_stock($pdo, (int)$it['product_id'], -(int)$it['qty'], 'order', 'Order ' . $orderId . ' reactivated', (int)$_SESSION['user_id']);
                        }
                    }
                }
                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
            }
        }
    }
    redirect('orders.php');
}

/* Filters */
$statusFilter = $_GET['status'] ?? 'All';
$search       = trim($_GET['q'] ?? '');
$from         = $_GET['from'] ?? '';
$to           = $_GET['to'] ?? '';

$sql = "SELECT o.*, u.email FROM orders o JOIN users u ON u.id = o.user_id WHERE 1=1";
$params = [];
if ($statusFilter !== 'All') {
    $sql .= " AND o.status = ?";
    $params[] = $statusFilter;
}
if ($search !== '') {
    $sql .= " AND (o.id LIKE ? OR o.customer_name LIKE ? OR u.email LIKE ? OR o.address LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($from) {
    $sql .= " AND DATE(o.created_at) >= ?";
    $params[] = $from;
}
if ($to) {
    $sql .= " AND DATE(o.created_at) <= ?";
    $params[] = $to;
}
$sql .= " ORDER BY o.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

$itemStmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");

$statusCounts = [
    'Processing' => 0, 'Shipped' => 0, 'Delivered' => 0, 'Cancelled' => 0,
];
foreach ($orders as $o) {
    if (isset($statusCounts[$o['status']])) $statusCounts[$o['status']]++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Orders — Fresh Admin</title>
<link rel="stylesheet" href="../css/style.css">
<script src="../js/theme.js"></script>
<?php head_meta('../'); ?>
</head>
<body>

<?php admin_header('orders'); ?>

<div class="container" style="padding-bottom:60px;">
  <div class="dash-hero"><h1 style="margin-top:26px;">Customer orders</h1></div>

  <div class="stat-grid" style="grid-template-columns: repeat(4,1fr); margin: 18px 0;">
    <div class="stat-card"><div class="stat-value"><?= $statusCounts['Processing'] ?></div><div class="stat-label">Processing</div></div>
    <div class="stat-card"><div class="stat-value"><?= $statusCounts['Shipped'] ?></div><div class="stat-label">Shipped</div></div>
    <div class="stat-card"><div class="stat-value"><?= $statusCounts['Delivered'] ?></div><div class="stat-label">Delivered</div></div>
    <div class="stat-card"><div class="stat-value"><?= $statusCounts['Cancelled'] ?></div><div class="stat-label">Cancelled</div></div>
  </div>

  <div class="filter-bar">
    <form method="GET" style="display:contents;">
      <div class="field">
        <label for="q">Search</label>
        <input type="text" id="q" name="q" value="<?= h($search) ?>" placeholder="Order ID, name, email…">
      </div>
      <div class="field">
        <label for="st">Status</label>
        <select id="st" name="status">
          <option value="All" <?= $statusFilter === 'All' ? 'selected' : '' ?>>All</option>
          <?php foreach (['Processing','Shipped','Delivered','Cancelled'] as $s): ?>
            <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= $s ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="from">From</label>
        <input type="date" id="from" name="from" value="<?= h($from) ?>">
      </div>
      <div class="field">
        <label for="to">To</label>
        <input type="date" id="to" name="to" value="<?= h($to) ?>">
      </div>
      <div class="filter-actions">
        <button type="submit" class="btn btn-ghost btn-sm">Filter</button>
        <a href="orders.php" class="btn btn-ghost btn-sm">Clear</a>
      </div>
    </form>
  </div>

  <?php if (empty($orders)): ?>
    <div class="empty-state">
      <div class="glyph">📋</div>
      <h3>No orders match your filters</h3>
      <p>Try clearing the filters above.</p>
    </div>
  <?php else: ?>
    <table class="data-table">
      <thead><tr><th>Order</th><th>Customer</th><th>Date</th><th>Items</th><th>Total</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($orders as $o):
          $itemStmt->execute([$o['id']]);
          $orderItems = $itemStmt->fetchAll();
          $itemSummary = implode(', ', array_map(fn($it) => $it['product_name'] . ' ×' . $it['qty'], $orderItems));
      ?>
        <tr>
          <td data-label="Order"><?= h($o['id']) ?></td>
          <td data-label="Customer">
            <?= h($o['customer_name']) ?><br>
            <span class="muted" style="font-size:0.75rem;"><?= h($o['email']) ?></span>
          </td>
          <td data-label="Date"><?= date('M j, Y · g:ia', strtotime($o['created_at'])) ?></td>
          <td data-label="Items" style="max-width:260px; font-size:0.8rem; color:var(--ink-soft);"><?= h($itemSummary) ?></td>
          <td data-label="Total">Rs. <?= number_format($o['total'], 0) ?></td>
          <td data-label="Status">
            <span class="badge <?= badge_class($o['status']) ?>"><?= h($o['status']) ?></span>
          </td>
          <td><a href="order_detail.php?id=<?= urlencode($o['id']) ?>" class="edit-link">Manage</a></td>
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
