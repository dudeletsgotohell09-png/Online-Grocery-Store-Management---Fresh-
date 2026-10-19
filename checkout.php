<?php
require_once __DIR__ . '/config.php';
require_role('customer', '');

$items = cart_items($pdo);
if (empty($items)) {
    redirect('cart.php');
}
$subtotal = cart_subtotal($pdo);
$delivery = delivery_fee_for($subtotal);
$applied = $_SESSION['coupon'] ?? null;
$discount = ($applied && !empty($applied['ok'])) ? (float)$applied['discount'] : 0;
$total = max(0, $subtotal + $delivery - $discount);
$error = '';

/* Coupon apply (AJAX-aware). */
if (is_post() && ($_POST['action'] ?? '') === 'apply_coupon') {
    csrf_verify();
    $code = trim($_POST['coupon_code'] ?? '');
    $r = apply_coupon($pdo, $code, $subtotal);
    if ($r['ok']) {
        $_SESSION['coupon'] = $r;
    } else {
        unset($_SESSION['coupon']);
    }
    if (!empty($_POST['ajax'])) {
        $state = cart_state($pdo);
        json_response(['ok' => $r['ok'], 'message' => $r['message'], 'state' => $state]);
    }
    $error = $r['message'];
    if ($r['ok']) $error = '';
}

/* Coupon remove. */
if (is_post() && ($_POST['action'] ?? '') === 'remove_coupon') {
    csrf_verify();
    unset($_SESSION['coupon']);
    if (!empty($_POST['ajax'])) {
        json_response(['ok' => true, 'message' => 'Coupon removed.', 'state' => cart_state($pdo)]);
    }
    redirect('checkout.php');
}

/* Place order. */
if (is_post() && ($_POST['action'] ?? '') === 'place_order') {
    csrf_verify();
    $name = trim($_POST['name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $postal = trim($_POST['postal'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $card = trim($_POST['card'] ?? '');
    $note = trim($_POST['note'] ?? '');

    if ($name === '' || $address === '' || $city === '' || $postal === '' || $phone === '' || $card === '') {
        $error = 'Please fill in every required field.';
    } elseif (!preg_match('/^[0-9 +()\-]{7,20}$/', $phone)) {
        $error = 'Please enter a valid phone number.';
    } elseif (!preg_match('/^[0-9 ]{12,19}$/', $card)) {
        $error = 'Please enter a valid card number (digits only).';
    } else {
        // Re-check stock right before committing.
        $items = cart_items($pdo);
        if (empty($items)) {
            redirect('cart.php');
        }
        $subtotal = cart_subtotal($pdo);
        $delivery = delivery_fee_for($subtotal);
        $applied  = $_SESSION['coupon'] ?? null;
        $discount = ($applied && !empty($applied['ok'])) ? (float)$applied['discount'] : 0;
        $total = max(0, $subtotal + $delivery - $discount);

        $orderId = generate_order_id($pdo);
        $fullAddress = "$address, $city $postal";

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO orders
                 (id, user_id, customer_name, address, phone, subtotal, delivery_fee, discount, coupon_code, total, status)
                 VALUES (?,?,?,?,?,?,?,?,?,?,'Processing')"
            );
            $stmt->execute([
                $orderId, $_SESSION['user_id'], $name, $fullAddress, $phone,
                $subtotal, $delivery, $discount,
                $applied['code'] ?? null, $total,
            ]);

            $itemStmt  = $pdo->prepare("INSERT INTO order_items (order_id, product_id, product_name, price, qty) VALUES (?,?,?,?,?)");
            // Atomic stock decrement: only succeeds if enough stock remains.
            $stockStmt = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ? AND deleted_at IS NULL");

            foreach ($items as $item) {
                $p = $item['product'];
                $itemStmt->execute([$orderId, $p['id'], $p['name'], $p['price'], $item['qty']]);
                $stockStmt->execute([$item['qty'], $p['id'], $item['qty']]);
                if ($stockStmt->rowCount() === 0) {
                    throw new RuntimeException("Insufficient stock for: " . $p['name']);
                }
                log_stock($pdo, (int)$p['id'], -$item['qty'], 'order', $orderId, (int)$_SESSION['user_id']);
            }

            log_order_status($pdo, $orderId, 'Processing', 'Order placed by customer', (int)$_SESSION['user_id']);

            if (!empty($applied['code'])) {
                consume_coupon($pdo, $applied['code']);
            }

            $pdo->commit();
            cart_clear();
            unset($_SESSION['coupon']);

            // Send an order confirmation email (logged in dev mode).
            $itemRows = '';
            foreach ($items as $it) {
                $itemRows .= '<tr>'
                           . '<td style="padding:8px 0;border-bottom:1px solid #E4E7E0;">' . h($it['product']['name']) . ' × ' . (int)$it['qty'] . '</td>'
                           . '<td style="padding:8px 0;border-bottom:1px solid #E4E7E0;text-align:right;">Rs. ' . number_format($it['line_total'], 0) . '</td>'
                           . '</tr>';
            }
            $emailBody = '<p>Hi ' . h($name) . ',</p>'
                       . '<p>Thanks for your order! We\'ve received it and are getting your groceries ready.</p>'
                       . '<p style="font-size:1.1rem;"><strong>Order ID:</strong> ' . h($orderId) . '<br>'
                       . '<strong>Total:</strong> Rs. ' . number_format($total, 0) . '<br>'
                       . '<strong>Delivery to:</strong> ' . h($fullAddress) . '</p>'
                       . '<table style="width:100%;border-collapse:collapse;font-size:0.9rem;">'
                       . '<thead><tr><th style="text-align:left;padding:8px 0;border-bottom:2px solid #E4E7E0;">Item</th>'
                       . '<th style="text-align:right;padding:8px 0;border-bottom:2px solid #E4E7E0;">Price</th></tr></thead>'
                       . '<tbody>' . $itemRows . '</tbody></table>'
                       . '<p style="margin-top:16px;font-size:0.85rem;color:#5C6660;">You can track your order status anytime from your account.</p>';
            send_mail($_SESSION['email'], 'Your Fresh Grocery order ' . $orderId, mail_template('Order confirmed', $emailBody));

            redirect('order_confirmation.php?order=' . urlencode($orderId));
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log('Order failed: ' . $e->getMessage());
            $error = 'Could not place your order — stock may have changed. Please review your cart and try again.';
        }
    }
}

/* Saved addresses (pre-fill checkout form) */
$stmt = $pdo->prepare("SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC");
$stmt->execute([$_SESSION['user_id']]);
$savedAddresses = $stmt->fetchAll();
$defaultAddress = $savedAddresses[0] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Checkout — Fresh</title>
<link rel="stylesheet" href="css/style.css">
<script src="js/theme.js"></script>
<?php head_meta(''); ?>
</head>
<body class="page-checkout">

<?php customer_header('shop'); ?>

<div class="container">
  <?php breadcrumbs([['Home', 'customer/dashboard.php'], ['Cart', 'cart.php'], ['Checkout', null]]); ?>
  <div class="dash-hero"><h1 style="margin-top:26px;">Checkout</h1></div>

  <?php if ($error): ?><div class="form-error" style="max-width:760px;"><?= h($error) ?></div><?php endif; ?>

  <!-- The real order form is kept EMPTY and the inputs below point at it with form="checkout-form".
       (HTML does not allow a <form> inside another <form>, and the coupon forms live in the summary.) -->
  <form method="POST" action="checkout.php" id="checkout-form">
    <input type="hidden" name="action" value="place_order">
    <?= csrf_field() ?>
  </form>
  <div>
    <div class="cart-layout">
      <div>
        <div class="panel">
          <h3>Delivery address</h3>
          <?php if ($savedAddresses): ?>
          <div class="field">
            <label for="addr_pick">Saved address</label>
            <select id="addr_pick" onchange="fillAddress(this.value)">
              <option value="">— Enter a new address —</option>
              <?php foreach ($savedAddresses as $a): ?>
                <option value="<?= (int)$a['id'] ?>" data-name="<?= h($a['name']) ?>" data-address="<?= h($a['address']) ?>" data-city="<?= h($a['city']) ?>" data-postal="<?= h($a['postal']) ?>" data-phone="<?= h($a['phone']) ?>">
                  <?= h($a['label']) ?> — <?= h($a['address']) ?>, <?= h($a['city']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php endif; ?>
          <div class="field"><label for="name">Full name</label><input type="text" id="name" form="checkout-form" name="name" value="<?= h($_SESSION['name']) ?>" required autocomplete="name"></div>
          <div class="field"><label for="address">Address</label><input type="text" id="address" form="checkout-form" name="address" required autocomplete="street-address"></div>
          <div class="form-row-2">
            <div class="field"><label for="city">City</label><input type="text" id="city" form="checkout-form" name="city" required autocomplete="address-level2"></div>
            <div class="field"><label for="postal">Postal code</label><input type="text" id="postal" form="checkout-form" name="postal" required autocomplete="postal-code"></div>
          </div>
          <div class="field"><label for="phone">Phone</label><input type="tel" id="phone" form="checkout-form" name="phone" required autocomplete="tel"></div>
          <div class="field"><label for="note">Delivery note (optional)</label><input type="text" id="note" form="checkout-form" name="note" placeholder="e.g. leave at the gate"></div>
        </div>

        <div class="panel">
          <h3>Payment (mock)</h3>
          <div class="field"><label for="card">Card number</label><input type="text" id="card" form="checkout-form" name="card" placeholder="4242 4242 4242 4242" required inputmode="numeric" autocomplete="cc-number"></div>
          <div class="form-row-2">
            <div class="field"><label for="exp">Expiry</label><input type="text" id="exp" form="checkout-form" name="exp" placeholder="MM/YY" required autocomplete="cc-exp"></div>
            <div class="field"><label for="cvc">CVC</label><input type="text" id="cvc" form="checkout-form" name="cvc" placeholder="123" required inputmode="numeric" autocomplete="cc-csc"></div>
          </div>
          <p style="font-size:0.78rem; margin:0;">No real payment is processed — this simulates a successful transaction for demo purposes.</p>
        </div>
      </div>

      <div class="cart-summary">
        <h3>Order summary</h3>
        <?php foreach ($items as $item): ?>
          <div class="summary-item-row"><span><?= h($item['product']['name']) ?> &times; <?= $item['qty'] ?></span><span>Rs. <?= number_format($item['line_total'], 0) ?></span></div>
        <?php endforeach; ?>
        <div class="summary-line" style="margin-top:10px;"><span>Subtotal</span><span data-cart-subtotal>Rs. <?= number_format($subtotal, 0) ?></span></div>
        <div class="summary-line"><span>Delivery</span><span data-cart-delivery><?= $delivery > 0 ? 'Rs. ' . number_format($delivery, 0) : 'FREE' ?></span></div>

        <?php if ($applied && !empty($applied['ok'])): ?>
          <div class="coupon-applied">
            <span>Coupon <?= h($applied['code']) ?> — Rs. <?= number_format($discount, 0) ?> off</span>
            <form method="POST" data-no-loading="1" style="display:inline;">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="remove_coupon">
              <button type="submit" class="t-close" aria-label="Remove coupon" style="background:none; border:none; font-size:1rem; cursor:pointer; color:var(--green-dark);">×</button>
            </form>
          </div>
        <?php else: ?>
          <form method="POST" class="coupon-form" action="checkout.php" data-no-loading="1">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="apply_coupon">
            <div class="coupon-box">
              <input type="text" name="coupon_code" placeholder="Coupon code" autocomplete="off">
              <button type="submit" class="btn btn-ghost btn-sm">Apply</button>
            </div>
          </form>
          <p style="font-size:0.72rem; color:var(--ink-faint); margin:6px 0 0;">Try <strong>FRESH100</strong> or <strong>WELCOME10</strong>.</p>
        <?php endif; ?>

        <div class="total-line"><span>Total</span><span data-cart-total>Rs. <?= number_format($total, 0) ?></span></div>
        <button type="submit" form="checkout-form" class="btn btn-primary btn-block">Place order</button>
        <?php if ($subtotal >= FREE_DELIVERY_THRESHOLD): ?>
          <p style="font-size:0.78rem; margin: 10px 0 0; text-align:center; color:var(--green-dark); font-weight:700;">🎉 Free delivery applied!</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php customer_footer(''); ?>
<script>
function fillAddress(id) {
  var sel = document.getElementById('addr_pick');
  if (!id) return;
  var opt = sel.options[sel.selectedIndex];
  if (!opt) return;
  document.getElementById('name').value    = opt.getAttribute('data-name') || '';
  document.getElementById('address').value = opt.getAttribute('data-address') || '';
  document.getElementById('city').value    = opt.getAttribute('data-city') || '';
  document.getElementById('postal').value  = opt.getAttribute('data-postal') || '';
  document.getElementById('phone').value   = opt.getAttribute('data-phone') || '';
}

// Card number: format as groups of 4
document.getElementById('card').addEventListener('input', function(e){
  var v = e.target.value.replace(/\D/g,'').slice(0,16);
  e.target.value = v.replace(/(.{4})/g,'$1 ').trim();
});
// CVC: digits only
document.getElementById('cvc').addEventListener('input', function(e){
  e.target.value = e.target.value.replace(/\D/g,'').slice(0,4);
});
// Expiry: MM/YY
document.getElementById('exp').addEventListener('input', function(e){
  var v = e.target.value.replace(/\D/g,'').slice(0,4);
  if (v.length >= 3) v = v.slice(0,2) + '/' + v.slice(2);
  e.target.value = v;
});
</script>
</body>
</html>
