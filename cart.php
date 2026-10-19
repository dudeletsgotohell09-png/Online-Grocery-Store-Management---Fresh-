<?php
/**
 * includes/cart.php
 * Cart lives in $_SESSION['cart'] as [product_id => qty].
 * All write operations also re-validate against current stock.
 */

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

function cart_count(): int {
    return (int)array_sum($_SESSION['cart']);
}

/** Add qty to a product. Caps at the live stock level. Returns the new qty. */
function cart_add(PDO $pdo, int $productId, int $qty = 1): int {
    $p = get_product($pdo, $productId);
    if (!$p || $p['stock'] <= 0) return 0;

    $current = $_SESSION['cart'][$productId] ?? 0;
    $new = min($current + $qty, (int)$p['stock']);
    $_SESSION['cart'][$productId] = $new;
    return $new;
}

/** Set an explicit qty (clamped to stock). Removes if qty <= 0. */
function cart_set_qty(PDO $pdo, int $productId, int $qty): int {
    if ($qty <= 0) {
        unset($_SESSION['cart'][$productId]);
        return 0;
    }
    $p = get_product($pdo, $productId);
    if (!$p) {
        unset($_SESSION['cart'][$productId]);
        return 0;
    }
    $qty = min($qty, (int)$p['stock']);
    $_SESSION['cart'][$productId] = $qty;
    return $qty;
}

function cart_remove(int $productId): void {
    unset($_SESSION['cart'][$productId]);
}

function cart_clear(): void {
    $_SESSION['cart'] = [];
}

/**
 * Returns the cart as a list of ['product' => row, 'qty' => n, 'line_total' => n]
 * clamped to whatever stock actually exists right now.
 */
function cart_items($pdo): array {
    if (empty($_SESSION['cart'])) return [];
    $ids = array_keys($_SESSION['cart']);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id IN ($placeholders) AND deleted_at IS NULL");
    $stmt->execute($ids);
    $rows = $stmt->fetchAll();

    $items = [];
    foreach ($rows as $row) {
        $qty = min((int)($_SESSION['cart'][$row['id']] ?? 0), max((int)$row['stock'], 0));
        if ($qty <= 0) {
            // Stale cart line — clean up.
            unset($_SESSION['cart'][$row['id']]);
            continue;
        }
        $items[] = [
            'product'    => $row,
            'qty'        => $qty,
            'line_total' => $qty * (float)$row['price'],
        ];
    }
    return $items;
}

function cart_subtotal($pdo): float {
    $total = 0;
    foreach (cart_items($pdo) as $item) {
        $total += $item['line_total'];
    }
    return (float)$total;
}

/** Total item count across the cart (1 milk + 2 breads = 3). */
function cart_unit_count(): int {
    return (int)array_sum($_SESSION['cart']);
}
