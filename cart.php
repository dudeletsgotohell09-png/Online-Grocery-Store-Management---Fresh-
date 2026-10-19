<?php
require_once __DIR__ . '/config.php';
require_role('customer', '');

/* Handle cart actions — supports both regular POST and AJAX. */
if (is_post() && isset($_POST['action'])) {
    csrf_verify();
    $productId = (int)($_POST['product_id'] ?? 0);
    $ajax = !empty($_POST['ajax']);

    switch ($_POST['action']) {
        case 'remove':
            cart_remove($productId);
            break;
        case 'inc':
            cart_add($pdo, $productId, 1);
            break;
        case 'dec':
            $current = $_SESSION['cart'][$productId] ?? 0;
            cart_set_qty($pdo, $productId, $current - 1);
            break;
        case 'set':
            $qty = max(0, (int)($_POST['qty'] ?? 0));
            cart_set_qty($pdo, $productId, $qty);
            break;
    }

    if ($ajax) {
        $state = cart_state($pdo);
        // For remove/inc/dec we need updated line totals too.
        $items = cart_items($pdo);
        $state['items'] = array_map(function($it) {
            return [
                'id' => (int)$it['product']['id'],
                'qty' => $it['qty'],
                'line_total' => $it['line_total'],
                'line_total_fmt' => money($it['line_total']),
            ];
        }, $items);
        json_response(['ok' => true, 'state' => $state]);
    }
    redirect('cart.php');
}

$items = cart_items($pdo);
$subtotal = cart_subtotal($pdo);
$delivery = delivery_fee_for($subtotal);
$applied = $_SESSION['coupon'] ?? null;
$discount = ($applied && !empty($applied['ok'])) ? (float)$applied['discount'] : 0;
$total = max(0, $subtotal + $delivery - $discount);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Your Cart — Fresh</title>
<link rel="stylesheet" href="css/style.css">
<script src="js/theme.js"></script>
<?php head_meta(''); ?>
</head>
<body class="page-cart">

<?php customer_header('shop'); ?>

<div class="container">
  <?php breadcrumbs([['Home', 'customer/dashboard.php'], ['Cart', null]]); ?>
  <div class="dash-hero"><h1 style="margin-top:26px;">Your cart</h1></div>

  <?php if (empty($items)): ?>
    <div class="empty-state">
      <div class="glyph">🛒</div>
      <h3>Your cart is empty</h3>
      <p>Browse the shop and add a few items.</p>
      <a href="shop.php" class="btn btn-primary" style="margin-top:10px;">Go to shop</a>
    </div>
  <?php else: ?>
  <div class="cart-layout">
    <div class="cart-table">
      <?php foreach ($items as $item): $p = $item['product']; ?>
      <div class="cart-row" data-row-product="<?= (int)$p['id'] ?>">
        <div class="ci-icon"><?= product_visual($p, 'thumb') ?></div>
        <div class="ci-info">
          <div class="ci-name"><a href="product.php?id=<?= $p['id'] ?>"><?= h($p['name']) ?></a></div>
          <div class="ci-price">Rs. <?= number_format($p['price'], 0) ?> &times; <span data-row-qty><?= $item['qty'] ?></span></div>
        </div>
        <form method="POST" class="ajax-cart" style="display:flex; align-items:center;">
          <?= csrf_field() ?>
          <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
          <div class="qty-stepper">
            <button type="submit" name="action" value="dec" aria-label="Decrease">&minus;</button>
            <span data-row-qty><?= $item['qty'] ?></span>
            <button type="submit" name="action" value="inc" <?= $item['qty'] >= $p['stock'] ? 'disabled' : '' ?> aria-label="Increase">+</button>
          </div>
          <button type="submit" name="action" value="remove" class="remove-link" data-confirm="Remove this item from your cart?">Remove</button>
        </form>
        <div class="ci-line-total" data-row-linetotal>Rs. <?= number_format($item['line_total'], 0) ?></div>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="cart-summary">
      <h3>Order summary</h3>
      <div class="summary-line"><span>Subtotal</span><span data-cart-subtotal>Rs. <?= number_format($subtotal, 0) ?></span></div>
      <div class="summary-line"><span>Delivery</span><span data-cart-delivery><?= $delivery > 0 ? 'Rs. ' . number_format($delivery, 0) : 'FREE' ?></span></div>
      <?php if ($discount > 0): ?>
        <div class="summary-line" style="color:var(--green-dark); font-weight:700;"><span>Discount (<?= h($applied['code']) ?>)</span><span>− Rs. <?= number_format($discount, 0) ?></span></div>
      <?php endif; ?>
      <div class="total-line"><span>Total</span><span data-cart-total>Rs. <?= number_format($total, 0) ?></span></div>
      <a href="checkout.php" class="btn btn-primary btn-block">Checkout</a>
      <a href="shop.php" class="btn btn-ghost btn-block" style="margin-top:10px;">Continue shopping</a>
      <?php if ($subtotal < FREE_DELIVERY_THRESHOLD): ?>
        <p style="font-size:0.78rem; margin: 12px 0 0; text-align:center;">Add Rs. <?= number_format(FREE_DELIVERY_THRESHOLD - $subtotal, 0) ?> more for FREE delivery.</p>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php customer_footer(''); ?>
</body>
</html>
