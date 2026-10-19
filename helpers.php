<?php
/**
 * includes/helpers.php
 * Small shared utilities used by multiple pages.
 */

/** Pull all categories (id, name, icon) ordered by sort_order. */
function get_categories(PDO $pdo): array {
    return $pdo->query("SELECT * FROM categories ORDER BY sort_order, name")->fetchAll();
}

/** Pull all category names as a flat list (used by select boxes). */
function category_names(PDO $pdo): array {
    return array_column(get_categories($pdo), 'name');
}

/** Look up a single product by id (ignores soft-deleted rows). */
function get_product(PDO $pdo, int $id): ?array {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** Validation badge class for an order status. */
function badge_class(string $status): string {
    switch ($status) {
        case 'Delivered': return 'badge-delivered';
        case 'Shipped':   return 'badge-shipped';
        case 'Cancelled': return 'badge-cancelled';
        default:          return 'badge-processing';
    }
}

/** Append a status entry to order_status_history. */
function log_order_status(PDO $pdo, string $orderId, string $status, ?string $note = null, ?int $changedBy = null): void {
    $stmt = $pdo->prepare(
        "INSERT INTO order_status_history (order_id, status, note, changed_by) VALUES (?, ?, ?, ?)"
    );
    $stmt->execute([$orderId, $status, $note, $changedBy]);
}

/** Record a stock movement. */
function log_stock(PDO $pdo, int $productId, int $delta, string $reason = 'manual', ?string $note = null, ?int $changedBy = null): void {
    $stmt = $pdo->prepare(
        "INSERT INTO stock_movements (product_id, delta, reason, note, changed_by) VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->execute([$productId, $delta, $reason, $note, $changedBy]);
}

/**
 * Apply a coupon code against a subtotal.
 * Returns ['ok' => bool, 'discount' => float, 'message' => string].
 */
function apply_coupon(PDO $pdo, string $code, float $subtotal): array {
    $code = strtoupper(trim($code));
    if ($code === '') return ['ok' => false, 'discount' => 0, 'message' => 'Enter a code.'];

    $stmt = $pdo->prepare("SELECT * FROM coupons WHERE code = ? AND active = 1");
    $stmt->execute([$code]);
    $c = $stmt->fetch();
    if (!$c)                              return ['ok' => false, 'discount' => 0, 'message' => 'Invalid code.'];
    if ($c['expires_at'] && strtotime($c['expires_at']) < time())
                                          return ['ok' => false, 'discount' => 0, 'message' => 'This code has expired.'];
    if ($c['max_uses'] && $c['uses'] >= $c['max_uses'])
                                          return ['ok' => false, 'discount' => 0, 'message' => 'This code has been fully redeemed.'];
    if ($subtotal < $c['min_subtotal'])   return ['ok' => false, 'discount' => 0, 'message' => 'Order does not meet the minimum for this code.'];

    $discount = $c['type'] === 'percent'
        ? round($subtotal * ($c['value'] / 100), 2)
        : (float)$c['value'];
    $discount = min($discount, $subtotal);

    return ['ok' => true, 'discount' => $discount, 'code' => $code, 'message' => 'Coupon applied.'];
}

/** Mark a coupon as used (one redemption per successful order). */
function consume_coupon(PDO $pdo, string $code): void {
    $pdo->prepare("UPDATE coupons SET uses = uses + 1 WHERE code = ?")->execute([$code]);
}

/**
 * Compute delivery fee based on subtotal + free-delivery threshold.
 * Free if subtotal >= FREE_DELIVERY_THRESHOLD.
 */
function delivery_fee_for(float $subtotal): float {
    return $subtotal >= FREE_DELIVERY_THRESHOLD ? 0 : DELIVERY_FEE;
}

/** Send a 404 page and stop. */
function not_found(): void {
    http_response_code(404);
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Not found</title>'
       . '<style>body{font-family:system-ui;background:#F5F6F2;color:#1B211C;text-align:center;padding:80px 20px;}'
       . 'a{color:#23553A;font-weight:700;}</style></head><body>'
       . '<h1>404 — Page not found</h1>'
       . '<p>The page you were looking for doesn’t exist.</p>'
       . '<p><a href="javascript:history.back()">Go back</a> · <a href="/">Home</a></p>'
       . '</body></html>';
    exit;
}

/** Emit a JSON response and stop. */
function json_response(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Build a serializable cart state used by AJAX handlers. */
function cart_state(PDO $pdo): array {
    $items = cart_items($pdo);
    $subtotal = cart_subtotal($pdo);
    $delivery = delivery_fee_for($subtotal);
    $applied = $_SESSION['coupon'] ?? null;
    $discount = 0;
    if ($applied && !empty($applied['ok'])) {
        $discount = (float)$applied['discount'];
    }
    $total = max(0, $subtotal + $delivery - $discount);

    $stateItems = [];
    foreach ($items as $it) {
        $stateItems[] = [
            'id'              => (int)$it['product']['id'],
            'qty'             => $it['qty'],
            'line_total'      => $it['line_total'],
            'line_total_fmt'  => money($it['line_total']),
        ];
    }

    return [
        'items'          => $stateItems,
        'count'          => cart_count(),
        'subtotal'       => $subtotal,
        'subtotal_fmt'   => money($subtotal),
        'delivery'       => $delivery,
        'delivery_fmt'   => $delivery > 0 ? money($delivery) : 'FREE',
        'discount'       => $discount,
        'discount_fmt'   => money($discount),
        'total'          => $total,
        'total_fmt'      => money($total),
        'threshold'      => FREE_DELIVERY_THRESHOLD,
        'coupon'         => $applied['code'] ?? null,
    ];
}

/**
 * Render a product's visual: image if uploaded, otherwise the emoji icon
 * inside a colored tile. Returns HTML safe for direct echo.
 *
 * @param array $product Product row.
 * @param string $size   One of: 'thumb' | 'card' | 'detail'.
 */
function product_visual(array $product, string $size = 'card'): string {
    $img = $product['image'] ?? null;
    if ($img && file_exists(UPLOAD_DIR . '/' . basename($img))) {
        $url  = h(UPLOAD_URL . basename($img));
        $alt  = h($product['name'] ?? '');
        $style = '';
        if ($size === 'thumb') {
            $style = 'width:48px;height:48px;object-fit:cover;border-radius:12px;';
        } elseif ($size === 'detail') {
            $style = 'width:100%;height:100%;object-fit:cover;border-radius:var(--radius-lg);';
        } else {
            $style = 'width:52px;height:52px;object-fit:cover;border-radius:14px;';
        }
        return '<img src="' . $url . '" alt="' . $alt . '" style="' . $style . '" loading="lazy">';
    }
    // Fallback to emoji.
    $icon  = h($product['icon'] ?? '🛒');
    if ($size === 'thumb') {
        return '<span class="row-icon">' . $icon . '</span>';
    } elseif ($size === 'detail') {
        return '<span style="font-size:8rem;">' . $icon . '</span>';
    }
    return '<span class="p-icon">' . $icon . '</span>';
}

/**
 * Validate and save an uploaded product image. Returns ['ok'=>bool, 'path'=>string|null, 'error'=>string|null].
 */
function save_product_image(array $file): array {
    if (!isset($file['tmp_name']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'path' => null, 'error' => null];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'path' => null, 'error' => 'Upload failed (code ' . $file['error'] . ').'];
    }
    if ($file['size'] > MAX_UPLOAD_BYTES) {
        return ['ok' => false, 'path' => null, 'error' => 'Image is too large. Max ' . (MAX_UPLOAD_BYTES / 1024 / 1024) . ' MB.'];
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, ALLOWED_IMAGE_TYPES, true)) {
        return ['ok' => false, 'path' => null, 'error' => 'Unsupported image type. Use JPEG, PNG, WebP, or GIF.'];
    }
    if (!is_dir(UPLOAD_DIR)) {
        @mkdir(UPLOAD_DIR, 0775, true);
    }
    switch ($mime) {
        case 'image/jpeg': $ext = 'jpg';  break;
        case 'image/png':  $ext = 'png';  break;
        case 'image/webp': $ext = 'webp'; break;
        case 'image/gif':  $ext = 'gif';  break;
        default:           $ext = 'bin';
    }
    $name = 'product_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest = UPLOAD_DIR . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return ['ok' => false, 'path' => null, 'error' => 'Could not save the uploaded file. Check folder permissions on uploads/.'];
    }
    return ['ok' => true, 'path' => $name, 'error' => null];
}

/** Delete a previously-uploaded product image file (best-effort). */
function delete_product_image(?string $path): void {
    if (!$path) return;
    $full = UPLOAD_DIR . '/' . basename($path);
    if (is_file($full)) @unlink($full);
}

/**
 * Build a SQL fragment + params for "IN" filter, given an array of values.
 * Returns [placeholders, params].
 */
function in_clause(array $values): array {
    if (empty($values)) return ['(NULL)', []];
    $placeholders = implode(',', array_fill(0, count($values), '?'));
    return ["($placeholders)", array_values($values)];
}
