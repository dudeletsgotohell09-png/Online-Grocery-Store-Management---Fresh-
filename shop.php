<?php
require_once __DIR__ . '/config.php';
require_role('customer', '');

/* Handle add-to-cart POST (works with or without ajax flag). */
if (is_post() && isset($_POST['action'])) {
    csrf_verify();
    $productId = (int)($_POST['product_id'] ?? 0);
    $ajax = !empty($_POST['ajax']);

    if ($_POST['action'] === 'add') {
        $new = cart_add($pdo, $productId, 1);
        if ($ajax) {
            json_response([
                'ok' => $new > 0,
                'message' => $new > 0 ? 'Added to cart.' : 'Out of stock.',
                'state' => cart_state($pdo),
            ]);
        }
    } elseif ($_POST['action'] === 'inc') {
        cart_add($pdo, $productId, 1);
        if ($ajax) json_response(['ok' => true, 'state' => cart_state($pdo)]);
    } elseif ($_POST['action'] === 'dec') {
        $current = $_SESSION['cart'][$productId] ?? 0;
        cart_set_qty($pdo, $productId, $current - 1);
        if ($ajax) json_response(['ok' => true, 'state' => cart_state($pdo)]);
    }
    if (!$ajax) {
        redirect("shop.php?" . http_build_query($_GET));
    }
}

$search = trim($_GET['search'] ?? '');
$category = trim($_GET['category'] ?? 'All');
$sort = $_GET['sort'] ?? 'cat';
$inStockOnly = ($_GET['instock'] ?? '0') === '1';

$where = "deleted_at IS NULL";
$params = [];
if ($search !== '') {
    $where .= " AND (name LIKE ? OR category LIKE ? OR description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($category !== 'All' && $category !== '') {
    $where .= " AND category = ?";
    $params[] = $category;
}
if ($inStockOnly) {
    $where .= " AND stock > 0";
}
switch ($sort) {
    case 'price_asc':  $orderBy = "price ASC, name"; break;
    case 'price_desc': $orderBy = "price DESC, name"; break;
    case 'name':       $orderBy = "name"; break;
    case 'newest':     $orderBy = "created_at DESC, name"; break;
    default:           $orderBy = "category, name"; break;
}

/* ----- Pagination ----- */
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = PRODUCTS_PER_PAGE;
$offset  = ($page - 1) * $perPage;

// Count total matches.
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE " . $where);
$countStmt->execute($params);
$totalProducts = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalProducts / $perPage));
if ($page > $totalPages) { $page = $totalPages; $offset = ($page - 1) * $perPage; }

// Fetch the current page.
$sql = "SELECT * FROM products WHERE " . $where . " ORDER BY " . $orderBy . " LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$categories = get_categories($pdo);
$catIcon = [];
foreach ($categories as $c) $catIcon[$c['name']] = $c['icon'];

/* Build a query-string helper that preserves current filters but lets us change `page`. */
$baseQs = http_build_query(array_filter([
    'search' => $search,
    'category' => $category !== 'All' ? $category : null,
    'sort' => $sort !== 'cat' ? $sort : null,
    'instock' => $inStockOnly ? '1' : null,
]));

/* Free-delivery progress */
$subtotal = cart_subtotal($pdo);
$dpPct = min(100, $subtotal > 0 ? round($subtotal / FREE_DELIVERY_THRESHOLD * 100) : 0);
$dpRemaining = max(0, FREE_DELIVERY_THRESHOLD - $subtotal);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Shop — Fresh</title>
<link rel="stylesheet" href="css/style.css">
<script src="js/theme.js"></script>
<?php head_meta(''); ?>
</head>
<body class="page-shop">

<?php customer_header('shop', '', $search); ?>

<div class="container">
  <div class="dash-hero">
    <h1 style="margin-top:26px;">Shop groceries</h1>
    <p>Fresh produce, pantry staples, and everyday essentials.</p>
  </div>

  <?php if ($subtotal > 0 && $subtotal < FREE_DELIVERY_THRESHOLD): ?>
    <div class="delivery-progress">
      <div class="dp-text">🚚 Add <strong>Rs. <?= number_format($dpRemaining, 0) ?></strong> more to unlock <strong>FREE delivery</strong>.</div>
      <div class="dp-track"><div class="dp-fill" data-dp-fill style="width: <?= $dpPct ?>%;"></div></div>
    </div>
  <?php elseif ($subtotal >= FREE_DELIVERY_THRESHOLD): ?>
    <div class="delivery-progress">
      <div class="dp-text">🎉 You've unlocked <strong>FREE delivery</strong> on this order.</div>
      <div class="dp-track"><div class="dp-fill" data-dp-fill style="width: 100%;"></div></div>
    </div>
  <?php endif; ?>

  <div class="category-grid" style="grid-template-columns: repeat(auto-fit, minmax(120px,1fr));">
    <a class="category-card <?= $category === 'All' ? 'active' : '' ?>" href="shop.php?<?= http_build_query(array_filter(['search' => $search])) ?>">
      <div class="category-icon" style="background:var(--orange-pale);">&#128717;</div>
      <div class="cat-name">All</div>
    </a>
    <?php foreach ($categories as $c): ?>
    <a class="category-card <?= $category === $c['name'] ? 'active' : '' ?>" href="shop.php?<?= http_build_query(array_filter(['search' => $search, 'category' => $c['name'], 'sort' => $sort !== 'cat' ? $sort : null, 'instock' => $inStockOnly ? '1' : null])) ?>">
      <div class="category-icon" style="background:var(--green-pale);"><?= $c['icon'] ?></div>
      <div class="cat-name"><?= h($c['name']) ?></div>
    </a>
    <?php endforeach; ?>
  </div>

  <div class="shop-toolbar">
    <div class="left-group">
      <span class="result-count"><?= $totalProducts ?> product<?= $totalProducts === 1 ? '' : 's' ?><?= $totalPages > 1 ? ' · page ' . $page . ' of ' . $totalPages : '' ?></span>
      <?php if ($search || $category !== 'All' || $inStockOnly): ?>
        <a class="clear-filters" href="shop.php">Clear filters</a>
      <?php endif; ?>
    </div>
    <div class="sort-group">
      <label for="sort">Sort:</label>
      <select id="sort" onchange="applySort(this.value)">
        <option value="cat"         <?= $sort === 'cat' ? 'selected' : '' ?>>Default</option>
        <option value="price_asc"   <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price: Low → High</option>
        <option value="price_desc"  <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: High → Low</option>
        <option value="name"        <?= $sort === 'name' ? 'selected' : '' ?>>Name (A–Z)</option>
        <option value="newest"      <?= $sort === 'newest' ? 'selected' : '' ?>>Newest</option>
      </select>
      <label style="display:inline-flex; align-items:center; gap:6px; margin-left:8px;">
        <input type="checkbox" id="instock" <?= $inStockOnly ? 'checked' : '' ?> onchange="applyInStock(this.checked)">
        <span>In stock only</span>
      </label>
    </div>
  </div>

  <?php if (empty($products)): ?>
    <div class="empty-state">
      <div class="glyph">🔍</div>
      <h3>No products found</h3>
      <p>Try a different search term or category.</p>
      <a href="shop.php" class="btn btn-dark btn-sm">Show all products</a>
    </div>
  <?php else: ?>
  <div class="product-grid">
    <?php foreach ($products as $p):
        $inCart = $_SESSION['cart'][$p['id']] ?? 0;
        $outOfStock = $p['stock'] <= 0;
    ?>
    <div class="product-card">
      <a href="product.php?id=<?= $p['id'] ?>" style="display:block;">
        <div class="p-icon"><?= product_visual($p, 'card') ?></div>
        <div class="p-cat"><?= h($p['category']) ?></div>
        <h4 class="p-name"><?= h($p['name']) ?></h4>
        <p class="p-desc"><?= h($p['description']) ?></p>
      </a>
      <?php if ($p['stock'] > 0 && $p['stock'] <= 10): ?><span class="stock-low">Only <?= $p['stock'] ?> left</span><?php endif; ?>
      <div class="p-footer">
        <div class="p-price">Rs. <?= number_format($p['price'], 0) ?><small><?= h($p['unit']) ?></small></div>
        <?php if ($outOfStock): ?>
          <span class="stock-out">Out of stock</span>
        <?php elseif ($inCart > 0): ?>
          <form method="POST" class="ajax-cart qty-form" action="shop.php?<?= http_build_query($_GET) ?>" style="display:inline;">
            <?= csrf_field() ?>
            <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
            <div class="qty-stepper">
              <button type="submit" name="action" value="dec" aria-label="Decrease">&minus;</button>
              <span><?= $inCart ?></span>
              <button type="submit" name="action" value="inc" <?= $inCart >= $p['stock'] ? 'disabled' : '' ?> aria-label="Increase">+</button>
            </div>
          </form>
        <?php else: ?>
          <form method="POST" class="ajax-cart" action="shop.php?<?= http_build_query($_GET) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
            <button type="submit" name="action" value="add" class="btn btn-primary btn-sm">Add to cart</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <?php if ($totalPages > 1): ?>
  <nav class="pagination" aria-label="Pagination">
    <?php
    // Render pagination with ellipses for big ranges.
    $window = 2; // pages either side of current
    $pages = [];
    for ($i = 1; $i <= $totalPages; $i++) {
        if ($i === 1 || $i === $totalPages || abs($i - $page) <= $window) {
            $pages[] = $i;
        } elseif (end($pages) !== '…') {
            $pages[] = '…';
        }
    }
    foreach ($pages as $p):
        if ($p === '…'): ?>
          <span class="dots">…</span>
        <?php elseif ($p == $page): ?>
          <span class="current" aria-current="page"><?= $p ?></span>
        <?php else: ?>
          <a href="shop.php?<?= $baseQs ? $baseQs . '&page=' . $p : 'page=' . $p ?>"><?= $p ?></a>
        <?php endif;
    endforeach;
    ?>
  </nav>
  <?php endif; ?>
  <?php endif; ?>
</div>

<?php customer_footer(''); ?>
<script>
function applySort(v) {
  var p = new URLSearchParams(location.search);
  if (v && v !== 'cat') p.set('sort', v); else p.delete('sort');
  location.search = p.toString();
}
function applyInStock(v) {
  var p = new URLSearchParams(location.search);
  if (v) p.set('instock', '1'); else p.delete('instock');
  location.search = p.toString();
}
</script>
</body>
</html>
