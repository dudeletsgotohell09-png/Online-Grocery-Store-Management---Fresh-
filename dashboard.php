<?php
$root_path = '../';
require_once $root_path . 'config.php';
require_role('customer', $root_path);

$firstName = explode(' ', $_SESSION['name'])[0];

/* ---- account stats ---- */
$stmt = $pdo->prepare("SELECT COUNT(*) AS order_count, COALESCE(SUM(total),0) AS total_spent FROM orders WHERE user_id = ? AND status != 'Cancelled'");
$stmt->execute([$_SESSION['user_id']]);
$stats = $stmt->fetch();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND status IN ('Processing','Shipped')");
$stmt->execute([$_SESSION['user_id']]);
$pendingCount = $stmt->fetchColumn();

/* ---- categories with live counts ---- */
$categories = $pdo->query("SELECT category, COUNT(*) AS cnt FROM products WHERE deleted_at IS NULL GROUP BY category ORDER BY cnt DESC")->fetchAll();
$totalProducts = array_sum(array_column($categories, 'cnt'));

$categoryStyle = [
    'Fruits & Veg'  => ['icon' => '🥕', 'tint' => 'var(--green-pale)'],
    'Dairy & Eggs'  => ['icon' => '🥛', 'tint' => 'var(--blue-pale)'],
    'Bakery'        => ['icon' => '🍞', 'tint' => 'var(--orange-pale)'],
    'Pantry'        => ['icon' => '🍚', 'tint' => 'var(--purple-pale)'],
    'Beverages'     => ['icon' => '🧃', 'tint' => 'var(--yellow-pale)'],
    'Snacks'        => ['icon' => '🥜', 'tint' => 'var(--pink-pale)'],
];

/* ---- featured products (deterministic — first 2 is_featured, or rand fallback) ---- */
$featured = $pdo->query("SELECT * FROM products WHERE stock > 0 AND deleted_at IS NULL AND is_featured = 1 LIMIT 2")->fetchAll();
if (count($featured) < 2) {
    $stmt = $pdo->query("SELECT * FROM products WHERE stock > 0 AND deleted_at IS NULL ORDER BY id LIMIT 2");
    $featured = $stmt->fetchAll();
}

/* ---- recent orders (3 most recent) ---- */
$stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 3");
$stmt->execute([$_SESSION['user_id']]);
$recentOrders = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard — Fresh</title>
<link rel="stylesheet" href="../css/style.css">
<script src="../js/theme.js"></script>
<?php head_meta('../'); ?>
</head>
<body>

<?php customer_header('home', '../'); ?>

<div class="container">

  <div class="hero-banner">
    <div>
      <span class="hero-tag">&#127807; Welcome back, <?= h($firstName) ?></span>
      <h1>Make healthy life with <span class="accent">fresh</span> grocery</h1>
      <p>Everyday produce, pantry staples and household essentials.</p><p> Order online and skip the queue.</p>
      <a href="../shop.php" class="btn btn-primary">Shop Now</a>
    </div>
    <div class="hero-visual">
      <div class="hero-badge badge-top">&#127811; 100% Fresh</div>
      <div class="emoji-tile">🍅</div>
      <div class="emoji-tile">🥑</div>
      <div class="emoji-tile">🥦</div>
      <div class="emoji-tile">🍞</div>
      <div class="emoji-tile">🥛</div>
      <div class="emoji-tile">🍌</div>
      <div class="hero-badge badge-bottom">&#128666; Fast Delivery</div>
    </div>
  </div>

  <div class="dash-layout">
    <div>
      <h2 class="section-heading">Top Categories</h2>
      <div class="category-grid">
        <a class="category-card" href="../shop.php">
          <div class="category-icon" style="background:var(--orange-pale);">&#128717;</div>
          <div class="cat-name">Browse All</div>
          <div class="cat-count"><?= (int)$totalProducts ?> items</div>
        </a>
        <?php foreach ($categories as $c):
            $style = $categoryStyle[$c['category']] ?? ['icon' => '🛒', 'tint' => 'var(--bg)'];
        ?>
        <a class="category-card" href="../shop.php?category=<?= urlencode($c['category']) ?>">
          <div class="category-icon" style="background: <?= $style['tint'] ?>;"><?= $style['icon'] ?></div>
          <div class="cat-name"><?= h($c['category']) ?></div>
          <div class="cat-count"><?= (int)$c['cnt'] ?> items</div>
        </a>
        <?php endforeach; ?>
      </div>

      <h2 class="section-heading">Quick actions</h2>
      <div class="action-grid">
        <div class="action-card">
          <div class="action-icon">&#128722;</div>
          <h3>Browse products</h3>
          <p>Search our shop and add items to your cart.</p>
          <a href="../shop.php" class="btn btn-ghost btn-sm">Go to shop</a>
        </div>
        <div class="action-card">
          <div class="action-icon">&#128717;</div>
          <h3>My cart</h3>
          <p>Review items and continue to checkout.</p>
          <a href="../cart.php" class="btn btn-ghost btn-sm">View cart</a>
        </div>
        <div class="action-card">
          <div class="action-icon">&#128230;</div>
          <h3>Order history</h3>
          <p>Track the status of your past orders.</p>
          <a href="../order_history.php" class="btn btn-ghost btn-sm">View orders</a>
        </div>
        <div class="action-card">
          <div class="action-icon">&#128100;</div>
          <h3>My profile</h3>
          <p>Update your name, email, password, and addresses.</p>
          <a href="profile.php" class="btn btn-ghost btn-sm">Edit profile</a>
        </div>
      </div>

      <?php if ($recentOrders): ?>
      <h2 class="section-heading" style="margin-top:30px;">Recent orders</h2>
      <?php foreach ($recentOrders as $o): ?>
        <div class="order-card">
          <div class="order-card-head">
            <div>
              <div class="order-id">Order <?= h($o['id']) ?></div>
              <div class="order-meta"><?= date('M j, Y', strtotime($o['created_at'])) ?> &middot; Rs. <?= number_format($o['total'], 0) ?></div>
            </div>
            <span class="badge <?= badge_class($o['status']) ?>"><?= h($o['status']) ?></span>
          </div>
          <div style="margin-top:8px;">
            <a href="../order_detail.php?id=<?= urlencode($o['id']) ?>" class="btn btn-ghost btn-sm">View details</a>
          </div>
        </div>
      <?php endforeach; ?>
      <p style="margin-top:14px;"><a href="../order_history.php" class="btn btn-ghost btn-sm">See all orders →</a></p>
      <?php endif; ?>
    </div>

    <aside>
      <div class="side-panel">
        <div class="side-eyebrow">Account</div>
        <h3>Your activity</h3>
        <div class="feature-row"><div class="f-icon">&#128230;</div><span class="f-text"><?= (int)$stats['order_count'] ?> orders placed</span></div>
        <div class="feature-row"><div class="f-icon">&#9203;</div><span class="f-text"><?= (int)$pendingCount ?> in progress</span></div>
        <div class="feature-row"><div class="f-icon">&#128176;</div><span class="f-text">Rs. <?= number_format($stats['total_spent'], 0) ?> spent</span></div>
      </div>

      <div class="side-panel">
        <div class="side-eyebrow">Feature</div>
        <h3>Why shop with Fresh?</h3>
        <p>We always provide the best and fastest service to all our customers.</p>
        <div class="feature-row"><div class="f-icon">&#128666;</div><span class="f-text">Free delivery over Rs. <?= number_format(FREE_DELIVERY_THRESHOLD, 0) ?></span></div>
        <div class="feature-row"><div class="f-icon">&#128683;</div><span class="f-text">No additional fees for orders</span></div>
        <div class="feature-row"><div class="f-icon">&#9989;</div><span class="f-text">Fast and reliable delivery</span></div>
      </div>

      <?php if ($featured): ?>
      <div class="side-panel">
        <div class="side-eyebrow">Our items</div>
        <h3>Popular picks this week</h3>
        <?php foreach ($featured as $p): ?>
        <a class="mini-product" href="../product.php?id=<?= $p['id'] ?>">
          <div class="mp-icon" style="background: var(--green-pale); overflow:hidden;"><?= product_visual($p, 'thumb') ?></div>
          <div>
            <div class="mp-name"><?= h($p['name']) ?></div>
            <div class="mp-meta"><?= h($p['unit']) ?></div>
          </div>
          <div class="mp-price">Rs. <?= number_format($p['price'], 0) ?></div>
        </a>
        <?php endforeach; ?>
        <a href="../shop.php" class="btn btn-ghost btn-sm btn-block" style="margin-top:14px;">View all products</a>
      </div>
      <?php endif; ?>
    </aside>
  </div>
</div>

<?php customer_footer('../'); ?>
</body>
</html>
