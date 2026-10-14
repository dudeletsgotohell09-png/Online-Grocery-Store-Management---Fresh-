<?php
$root_path = '../';
require_once $root_path . 'config.php';
require_role('admin', $root_path);

$type = $_GET['type'] ?? 'orders';
$statusFilter = $_GET['status'] ?? 'All';

header('Content-Type: text/csv; charset=utf-8');
$filename = $type . '-' . date('Y-m-d') . '.csv';
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');

if ($type === 'products') {
    fputcsv($out, ['ID', 'Name', 'Category', 'Price', 'Unit', 'Stock', 'Featured', 'Status']);
    $sql = "SELECT * FROM products WHERE 1=1";
    if ($statusFilter === 'active')   $sql .= " AND deleted_at IS NULL";
    if ($statusFilter === 'deleted')  $sql .= " AND deleted_at IS NOT NULL";
    $sql .= " ORDER BY category, name";
    foreach ($pdo->query($sql) as $p) {
        fputcsv($out, [
            $p['id'], $p['name'], $p['category'], $p['price'], $p['unit'], $p['stock'],
            $p['is_featured'] ? 'Yes' : 'No',
            $p['deleted_at'] ? 'Deleted' : 'Active',
        ]);
    }
} else {
    // Orders by default.
    fputcsv($out, ['Order ID', 'Customer', 'Email', 'Phone', 'Address', 'Subtotal', 'Delivery', 'Discount', 'Total', 'Status', 'Coupon', 'Created']);
    $sql = "SELECT o.*, u.email FROM orders o JOIN users u ON u.id = o.user_id WHERE 1=1";
    $params = [];
    if (in_array($statusFilter, ['Processing','Shipped','Delivered','Cancelled'])) {
        $sql .= " AND o.status = ?";
        $params[] = $statusFilter;
    }
    $sql .= " ORDER BY o.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    foreach ($stmt as $o) {
        fputcsv($out, [
            $o['id'], $o['customer_name'], $o['email'], $o['phone'], $o['address'],
            $o['subtotal'], $o['delivery_fee'], $o['discount'], $o['total'],
            $o['status'], $o['coupon_code'], $o['created_at'],
        ]);
    }
}

fclose($out);
exit;
