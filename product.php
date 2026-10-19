<?php
require_once __DIR__ . '/config.php';
require_role('customer', '');

$id = (int)($_GET['id'] ?? 0);
$product = get_product($pdo, $id);
if (!$product) not_found();

/* Handle add-to-cart */
if (is_post() && ($_POST['action'] ?? '') === 'add') {
    csrf_verify();
    $qty = max(1, min((int)($_POST['qty'] ?? 1), 99));
    $added = cart_add($pdo, $id, $qty);
    if (!empty($_POST['ajax'])) {
        json_response(['ok' => $added > 0, 'message' => $added > 0 ? 'Added to cart.' : 'Out of stock.', 'state' => cart_state($pdo)]);
    }
    redirect('cart.php');
}

$inCart = $_SESSION['cart'][$product['id']] ?? 0;
$outOfStock = $product['stock'] <= 0;

/* Related products — same category, different id, limit 4 */
$stmt = $pdo->prepare("SELECT * FROM products WHERE category = ? AND id != ? AND deleted_at IS NULL AND stock > 0 ORDER BY RAND() LIMIT 4");
$stmt->execute([$product['category'], $id]);
$related = $stmt->fetchAll();

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
<title><?= h($product['name']) ?> — Fresh</title>
<link rel="stylesheet" href="css/style.css">
<script src="js/theme.js"></script>
<?php head_meta(''); ?>
</head>
<body class="page-product">

<?php customer_header('shop'); ?>

<div class="container">
  <?php breadcrumbs([['Home', 'customer/dashboard.php'], ['Shop', 'shop.php'], [$product['name'], null]]); ?>

  <div class="product-detail">
    <div class="pd-visual"><?= product_visual($product, 'detail') ?></div>
    <div>
      <div class="pd-cat"><?= h($product['category']) ?></div>
      <h1><?= h($product['name']) ?></h1>
      <p class="pd-desc"><?= h($product['description']) ?></p>
      <div class="pd-price">Rs. <?= number_format($product['price'], 0) ?> <small>/ <?= h($product['unit']) ?></small></div>

      <div class="pd-meta">
        <div><strong>Stock</strong><?= $outOfStock
            ? '<span style="color:var(--berry); font-weight:700;">Out of stock</span>'
            : ($product['stock'] <= 10
                ? '<span style="color:var(--orange-dark); font-weight:700;">Only ' . (int)$product['stock'] . ' left</span>'
                : '<span style="color:var(--green-dark); font-weight:700;">In stock</span>') ?></div>
        <div><strong>Category</strong><?= h($product['category']) ?></div>
        <div><strong>Unit</strong><?= h($product['unit']) ?></div>
      </div>

      <?php if ($subtotal > 0 && $subtotal < FREE_DELIVERY_THRESHOLD): ?>
        <div class="delivery-progress" style="margin: 0 0 18px;">
          <div class="dp-text">🚚 Add <strong>Rs. <?= number_format($dpRemaining, 0) ?></strong> more for FREE delivery.</div>
          <div class="dp-track"><div class="dp-fill" data-dp-fill style="width: <?= $dpPct ?>%;"></div></div>
        </div>
      <?php endif; ?>

      <div class="pd-actions">
        <?php if ($outOfStock): ?>
          <span class="stock-out" style="font-size:1rem;">Out of stock</span>
        <?php else: ?>
          <form method="POST" class="ajax-cart" action="product.php?id=<?= $id ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="product_id" value="<?= $id ?>">
            <input type="hidden" name="action" value="add">
            <div class="qty-stepper" style="font-size:1.1rem;">
              <button type="button" data-qty-dec aria-label="Decrease">&minus;</button>
              <span data-qty-display>1</span>
              <button type="button" data-qty-inc aria-label="Increase">+</button>
            </div>
            <input type="hidden" name="qty" value="1" data-qty-input>
            <button type="submit" class="btn btn-primary">Add to cart — Rs. <span data-qty-total><?= number_format($product['price'], 0) ?></span></button>
          </form>
          <?php if ($inCart > 0): ?>
            <a href="cart.php" class="btn btn-ghost btn-sm">View cart (<?= $inCart ?>)</a>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php if ($related): ?>
    <h2 class="section-heading" style="margin-top:30px;">You might also like</h2>
    <div class="product-grid">
      <?php foreach ($related as $p): ?>
      <div class="product-card">
        <a href="product.php?id=<?= $p['id'] ?>" style="display:block;">
          <div class="p-icon"><?= product_visual($p, 'card') ?></div>
          <div class="p-cat"><?= h($p['category']) ?></div>
          <h4 class="p-name"><?= h($p['name']) ?></h4>
          <p class="p-desc"><?= h($p['description']) ?></p>
        </a>
        <div class="p-footer">
          <div class="p-price">Rs. <?= number_format($p['price'], 0) ?><small><?= h($p['unit']) ?></small></div>
          <a href="product.php?id=<?= $p['id'] ?>" class="btn btn-ghost btn-sm">View</a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php customer_footer(''); ?>
<script>
(function(){
  var inc = document.querySelector('[data-qty-inc]');
  var dec = document.querySelector('[data-qty-dec]');
  var display = document.querySelector('[data-qty-display]');
  var input  = document.querySelector('[data-qty-input]');
  var total  = document.querySelector('[data-qty-total]');
  var price  = <?= (float)$product['price'] ?>;
  var max    = <?= (int)$product['stock'] ?>;
  function set(v){
    v = Math.max(1, Math.min(max, v));
    if (display) display.textContent = v;
    if (input)  input.value = v;
    if (total)  total.textContent = (price * v).toLocaleString('en-US', {maximumFractionDigits:0});
  }
  if (inc) inc.addEventListener('click', function(){ set(parseInt(input.value,10)+1); });
  if (dec) dec.addEventListener('click', function(){ set(parseInt(input.value,10)-1); });
})();
</script>
</body>
</html>
