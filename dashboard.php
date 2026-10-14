<?php
$root_path = '../';
require_once $root_path . 'config.php';
require_role('admin', $root_path);

// Store-wide stats
$productCount   = $pdo->query("SELECT COUNT(*) FROM products WHERE deleted_at IS NULL")->fetchColumn();
$lowStockCount  = $pdo->query("SELECT COUNT(*) FROM products WHERE deleted_at IS NULL AND stock > 0 AND stock <= 10")->fetchColumn();
$outOfStock     = $pdo->query("SELECT COUNT(*) FROM products WHERE deleted_at IS NULL AND stock = 0")->fetchColumn();
$orderCount     = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$pendingOrders  = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'Processing'")->fetchColumn();
$cancelledOrders= $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'Cancelled'")->fetchColumn();
$revenue        = $pdo->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE status != 'Cancelled'")->fetchColumn();
$customerCount  = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn();

// Sales last 7 days (excludes cancelled)
$salesRows = $pdo->query("
    SELECT DATE(created_at) AS d, COALESCE(SUM(total),0) AS s, COUNT(*) AS c
    FROM orders
    WHERE status != 'Cancelled' AND created_at >= (CURDATE() - INTERVAL 6 DAY)
    GROUP BY DATE(created_at)
    ORDER BY d
")->fetchAll();

// Build a 7-day map (fill missing days with 0)
$days = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i day"));
    $days[$d] = ['label' => date('D', strtotime($d)), 'sales' => 0, 'orders' => 0];
}
foreach ($salesRows as $r) {
    if (isset($days[$r['d']])) {
        $days[$r['d']]['sales'] = (float)$r['s'];
        $days[$r['d']]['orders'] = (int)$r['c'];
    }
}
$maxSales = max(1, max(array_column($days, 'sales')));

// Low-stock list (top 5)
$lowStockItems = $pdo->query("SELECT * FROM products WHERE deleted_at IS NULL AND stock <= 10 ORDER BY stock ASC LIMIT 5")->fetchAll();

// Top products (top 5 by qty sold)
$topProducts = $pdo->query("
    SELECT p.id, p.name, p.icon, p.image, p.stock, p.price, COALESCE(SUM(oi.qty),0) AS sold
    FROM products p
    LEFT JOIN order_items oi ON oi.product_id = p.id
    LEFT JOIN orders o ON o.id = oi.order_id AND o.status != 'Cancelled'
    WHERE p.deleted_at IS NULL
    GROUP BY p.id
    ORDER BY sold DESC
    LIMIT 5
")->fetchAll();
$maxSold = max(1, max(array_map(fn($r) => $r['sold'], $topProducts) ?: [1]));

// Recent orders
$recentOrders = $pdo->query("
    SELECT o.*, u.email FROM orders o JOIN users u ON u.id = o.user_id
    ORDER BY o.created_at DESC LIMIT 5
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard — Fresh</title>
<link rel="stylesheet" href="../css/style.css">
<script src="../js/theme.js"></script>
<?php head_meta('../'); ?>
</head>
<body>

<?php admin_header('dashboard'); ?>

<div class="container">
  <div class="dash-hero">
    <div class="eyebrow">Admin dashboard</div>
    <h1>Welcome, <?= h($_SESSION['name']) ?></h1>
    <p>Here's how the store is doing today.</p>
  </div>

  <div class="stat-grid">
    <div class="stat-card">
      <div class="stat-value"><?= (int)$productCount ?></div>
      <div class="stat-label">Products listed</div>
    </div>
    <div class="stat-card <?= $lowStockCount > 0 ? 'warn' : '' ?>">
      <div class="stat-value"><?= (int)$lowStockCount ?></div>
      <div class="stat-label">Low stock items</div>
    </div>
    <div class="stat-card <?= $outOfStock > 0 ? 'warn' : '' ?>">
      <div class="stat-value"><?= (int)$outOfStock ?></div>
      <div class="stat-label">Out of stock</div>
    </div>
    <div class="stat-card <?= $pendingOrders > 0 ? 'warn' : '' ?>">
      <div class="stat-value"><?= (int)$pendingOrders ?></div>
      <div class="stat-label">Orders to process</div>
    </div>
    <div class="stat-card">
      <div class="stat-value"><?= (int)$orderCount ?></div>
      <div class="stat-label">Total orders</div>
    </div>
    <div class="stat-card">
      <div class="stat-value">Rs. <?= number_format($revenue, 0) ?></div>
      <div class="stat-label">Total revenue</div>
    </div>
    <div class="stat-card">
      <div class="stat-value"><?= (int)$customerCount ?></div>
      <div class="stat-label">Customers</div>
    </div>
    <div class="stat-card">
      <div class="stat-value"><?= (int)$cancelledOrders ?></div>
      <div class="stat-label">Cancelled orders</div>
    </div>
  </div>

  <div class="chart-card">
    <div style="display:flex; justify-content:space-between; align-items:flex-end; flex-wrap:wrap; gap:10px; margin-bottom: 10px;">
      <h3 style="margin:0;">Sales — last 7 days</h3>
      <div class="chart-legend">
        <span>Bars: revenue (Rs.)</span>
        <span>·</span>
        <span>Hover bars for order count</span>
      </div>
    </div>
    <div class="bar-chart">
      <?php foreach ($days as $d): ?>
        <?php $pct = $d['sales'] > 0 ? max(2, round($d['sales'] / $maxSales * 100)) : 1; ?>
        <div class="bar" style="height: <?= $pct ?>%;" title="<?= h($d['label']) ?>">
          <span class="bar-tip"><?= number_format($d['sales'], 0) ?> · <?= (int)$d['orders'] ?> ord</span>
          <span class="bar-label"><?= h($d['label']) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px;" class="admin-grid-2">
    <style>@media (max-width: 760px){ .admin-grid-2{ grid-template-columns: 1fr !important; } }</style>

    <div class="chart-card">
      <h3>Low-stock alerts</h3>
      <?php if (empty($lowStockItems)): ?>
        <p class="muted">All products have healthy stock levels. 🎉</p>
      <?php else: ?>
        <table class="data-table">
          <thead><tr><th></th><th>Product</th><th>Stock</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($lowStockItems as $p): ?>
            <tr>
              <td><?= product_visual($p, 'thumb') ?></td>
              <td data-label="Product"><?= h($p['name']) ?></td>
              <td data-label="Stock">
                <?php if ($p['stock'] == 0): ?>
                  <span class="stock-out">Out of stock</span>
                <?php else: ?>
                  <span class="stock-low"><?= (int)$p['stock'] ?></span>
                <?php endif; ?>
              </td>
              <td><a href="inventory.php?edit=<?= $p['id'] ?>" class="edit-link">Restock</a></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <div class="chart-card">
      <h3>Top sellers</h3>
      <?php if (empty($topProducts) || $topProducts[0]['sold'] == 0): ?>
        <p class="muted">No sales yet — top sellers will appear here.</p>
      <?php else: ?>
        <?php foreach ($topProducts as $tp): ?>
          <div style="margin-bottom: 10px;">
            <div style="display:flex; justify-content:space-between; align-items:center; font-size:0.85rem; margin-bottom: 4px; gap:10px;">
              <span style="display:flex; align-items:center; gap:8px; min-width:0;">
                <span style="width:28px; height:28px; border-radius:8px; overflow:hidden; background:var(--green-pale); display:inline-flex; align-items:center; justify-content:center; flex-shrink:0;">
                  <?= product_visual($tp, 'thumb') ?>
                </span>
                <span style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?= h($tp['name']) ?></span>
              </span>
              <strong><?= (int)$tp['sold'] ?> sold</strong>
            </div>
            <div style="background: var(--bg); border-radius: 999px; height:6px; overflow:hidden;">
              <div style="background: var(--green); height: 100%; width: <?= max(2, round($tp['sold'] / $maxSold * 100)) ?>%;"></div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <div class="chart-card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 10px;">
      <h3 style="margin:0;">Recent orders</h3>
      <a href="orders.php" class="btn btn-ghost btn-sm">View all →</a>
    </div>
    <?php if (empty($recentOrders)): ?>
      <p class="muted">No orders placed yet.</p>
    <?php else: ?>
    <table class="data-table">
      <thead><tr><th>Order</th><th>Customer</th><th>Date</th><th>Total</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($recentOrders as $o): ?>
        <tr>
          <td data-label="Order"><?= h($o['id']) ?></td>
          <td data-label="Customer"><?= h($o['customer_name']) ?><br><span class="muted" style="font-size:0.75rem;"><?= h($o['email']) ?></span></td>
          <td data-label="Date"><?= date('M j, Y · g:ia', strtotime($o['created_at'])) ?></td>
          <td data-label="Total">Rs. <?= number_format($o['total'], 0) ?></td>
          <td data-label="Status"><span class="badge <?= badge_class($o['status']) ?>"><?= h($o['status']) ?></span></td>
          <td><a href="order_detail.php?id=<?= urlencode($o['id']) ?>" class="edit-link">View</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

  <div class="action-grid">
    <div class="action-card">
      <div class="action-icon">&#128230;</div>
      <h3>Manage inventory</h3>
      <p>Add, edit, or remove products and update stock levels.</p>
      <a href="inventory.php" class="btn btn-ghost btn-sm">Open inventory</a>
    </div>
    <div class="action-card">
      <div class="action-icon">&#128203;</div>
      <h3>Manage orders</h3>
      <p>View incoming orders and update their delivery status.</p>
      <a href="orders.php" class="btn btn-ghost btn-sm">Open orders</a>
    </div>
    <div class="action-card">
      <div class="action-icon">&#128100;</div>
      <h3>Customers</h3>
      <p>View customer accounts, order history, and total spend.</p>
      <a href="customers.php" class="btn btn-ghost btn-sm">Open customers</a>
    </div>
    <div class="action-card">
      <div class="action-icon">&#128236;</div>
      <h3>Customer messages</h3>
      <p>Read and reply to messages sent from the Contact page.</p>
      <a href="messages.php" class="btn btn-ghost btn-sm">Open messages</a>
    </div>
    <div class="action-card">
      <div class="action-icon">&#128202;</div>
      <h3>Export data</h3>
      <p>Download orders or products as a CSV file.</p>
      <div style="display:flex; gap:8px; flex-wrap:wrap;">
        <a href="export.php?type=orders" class="btn btn-ghost btn-sm">Orders CSV</a>
        <a href="export.php?type=products" class="btn btn-ghost btn-sm">Products CSV</a>
      </div>
    </div>
    <div class="action-card">
      <div class="action-icon">&#127879;</div>
      <h3>Categories</h3>
      <p>Add, rename, or remove product categories.</p>
      <a href="categories.php" class="btn btn-ghost btn-sm">Manage categories</a>
    </div>
  </div>
</div>

<footer class="site-footer"><div class="container"><p>© 2026 Fresh Grocery. All rights reserved..</p></div></footer>
<script src="../js/app.js" defer></script>
</body>
</html>
