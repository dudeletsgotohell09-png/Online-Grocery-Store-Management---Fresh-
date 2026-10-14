<?php
$root_path = '../';
require_once $root_path . 'config.php';
require_role('admin', $root_path);

$categories = category_names($pdo);
$error = '';
$success = '';

/* Handle delete (POST only). */
if (is_post() && ($_POST['action'] ?? '') === 'delete') {
    csrf_verify();
    $id = (int)($_POST['id'] ?? 0);
    // Soft delete so existing order_items still resolve.
    $pdo->prepare("UPDATE products SET deleted_at = NOW() WHERE id = ?")->execute([$id]);
    log_stock($pdo, $id, 0, 'deleted', 'Product removed', (int)$_SESSION['user_id']);
    $success = 'Product removed.';
}

/* Handle restore. */
if (is_post() && ($_POST['action'] ?? '') === 'restore') {
    csrf_verify();
    $id = (int)($_POST['id'] ?? 0);
    $pdo->prepare("UPDATE products SET deleted_at = NULL WHERE id = ?")->execute([$id]);
    $success = 'Product restored.';
}

/* Handle add/edit submit. */
if (is_post() && ($_POST['action'] ?? '') === 'save') {
    csrf_verify();
    $id = (int)($_POST['id'] ?? 0);
    $icon = trim($_POST['icon'] ?? '🛒') ?: '🛒';
    $name = trim($_POST['name'] ?? '');
    $category = $_POST['category'] ?? $categories[0];
    $description = trim($_POST['description'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $unit = trim($_POST['unit'] ?? 'each') ?: 'each';
    $stock = (int)($_POST['stock'] ?? 0);
    $isFeatured = !empty($_POST['is_featured']) ? 1 : 0;
    $removeImage = !empty($_POST['remove_image']);

    if ($name === '' || $price <= 0) {
        $error = 'Please provide a valid name and price.';
    } else {
        // Handle image upload (validation + storage).
        $imgResult = save_product_image($_FILES['image'] ?? []);
        if (!$imgResult['ok']) {
            $error = $imgResult['error'];
        } else {
            $newImagePath = $imgResult['path'];  // null if no file uploaded

            if ($id > 0) {
                $old = get_product($pdo, $id);
                $oldStock = $old ? (int)$old['stock'] : 0;
                $delta = $stock - $oldStock;

                // Determine the final image path:
                // - If a new image was uploaded, replace the old one.
                // - If "remove image" was ticked, delete and clear.
                // - Otherwise keep the existing path.
                $finalImage = $old['image'] ?? null;
                if ($newImagePath) {
                    if (!empty($old['image'])) delete_product_image($old['image']);
                    $finalImage = $newImagePath;
                } elseif ($removeImage) {
                    if (!empty($old['image'])) delete_product_image($old['image']);
                    $finalImage = null;
                }

                $stmt = $pdo->prepare("UPDATE products SET icon=?, image=?, name=?, category=?, description=?, price=?, unit=?, stock=?, is_featured=? WHERE id=?");
                $stmt->execute([$icon, $finalImage, $name, $category, $description, $price, $unit, $stock, $isFeatured, $id]);
                if ($delta !== 0) {
                    log_stock($pdo, $id, $delta, 'edit', 'Stock updated via inventory', (int)$_SESSION['user_id']);
                }
                $success = 'Product updated.';
            } else {
                $stmt = $pdo->prepare("INSERT INTO products (icon, image, name, category, description, price, unit, stock, is_featured) VALUES (?,?,?,?,?,?,?,?,?)");
                $stmt->execute([$icon, $newImagePath, $name, $category, $description, $price, $unit, $stock, $isFeatured]);
                $newId = (int)$pdo->lastInsertId();
                if ($stock > 0) log_stock($pdo, $newId, $stock, 'initial', 'Initial stock', (int)$_SESSION['user_id']);
                $success = 'Product added.';
            }
        }
    }
}

$editProduct = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editProduct = $stmt->fetch();
}
$showForm = isset($_GET['add']) || $editProduct;

/* Search + filter */
$search = trim($_GET['q'] ?? '');
$catFilter = $_GET['category'] ?? 'All';
$showDeleted = ($_GET['deleted'] ?? '0') === '1';

$sql = "SELECT * FROM products WHERE 1=1";
$params = [];
if (!$showDeleted) {
    $sql .= " AND deleted_at IS NULL";
}
if ($search !== '') {
    $sql .= " AND (name LIKE ? OR category LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($catFilter !== 'All') {
    $sql .= " AND category = ?";
    $params[] = $catFilter;
}
$sql .= " ORDER BY deleted_at IS NULL DESC, category, name";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Inventory — Fresh Admin</title>
<link rel="stylesheet" href="../css/style.css">
<script src="../js/theme.js"></script>
<?php if ($success): ?>
<meta name="flash-toast" content="<?= h($success) ?>|success">
<?php endif; ?>
<?php head_meta('../'); ?>
</head>
<body>

<?php admin_header('inventory'); ?>

<div class="container" style="padding-bottom:60px;">
  <div class="dash-hero"><h1 style="margin-top:26px;">Inventory</h1></div>

  <?php if ($showForm): ?>
    <div class="panel" style="max-width:520px;">
      <h3><?= $editProduct ? 'Edit product' : 'Add product' ?></h3>
      <?php if ($error): ?><div class="form-error"><?= h($error) ?></div><?php endif; ?>
      <form method="POST" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= $editProduct['id'] ?? '' ?>">
        <div class="field"><label>Icon (emoji — used when no image is uploaded)</label><input type="text" name="icon" maxlength="4" value="<?= h($editProduct['icon'] ?? '🛒') ?>" required></div>
        <div class="field">
          <label>Product image (optional)</label>
          <input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif" onchange="previewImage(this)">
          <div class="image-preview">
            <div class="ip-box" id="ip-box">
              <?php
              $hasImage = !empty($editProduct['image']) && file_exists(__DIR__ . '/../uploads/' . basename($editProduct['image']));
              if ($hasImage):
              ?>
                <img src="../uploads/<?= h(basename($editProduct['image'])) ?>" alt="">
              <?php else: ?>
                <?= h($editProduct['icon'] ?? '🛒') ?>
              <?php endif; ?>
            </div>
            <div class="ip-actions">
              <span class="ip-hint">JPEG, PNG, WebP, or GIF · max <?= (int)(MAX_UPLOAD_BYTES / 1024 / 1024) ?> MB</span>
              <?php if ($hasImage): ?>
                <label style="font-size:0.78rem; color: var(--berry); font-weight:700; display:flex; align-items:center; gap:6px;">
                  <input type="checkbox" name="remove_image" value="1"> Remove current image
                </label>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <div class="field"><label>Name</label><input type="text" name="name" value="<?= h($editProduct['name'] ?? '') ?>" required></div>
        <div class="field">
          <label>Category</label>
          <select name="category" required>
            <?php foreach ($categories as $c): ?>
              <option value="<?= h($c) ?>" <?= (($editProduct['category'] ?? '') === $c) ? 'selected' : '' ?>><?= h($c) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field"><label>Description</label><input type="text" name="description" value="<?= h($editProduct['description'] ?? '') ?>"></div>
        <div class="form-row-2">
          <div class="field"><label>Price (Rs.)</label><input type="number" step="0.01" min="0" name="price" value="<?= $editProduct['price'] ?? '' ?>" required></div>
          <div class="field"><label>Unit</label><input type="text" name="unit" value="<?= h($editProduct['unit'] ?? 'each') ?>"></div>
        </div>
        <div class="field"><label>Stock quantity</label><input type="number" min="0" name="stock" value="<?= $editProduct['stock'] ?? 0 ?>" required></div>
        <div class="field" style="display:flex; align-items:center; gap:8px;">
          <input type="checkbox" name="is_featured" id="is_featured" value="1" <?= !empty($editProduct['is_featured']) ? 'checked' : '' ?>>
          <label for="is_featured" style="margin:0;">Feature on customer dashboard</label>
        </div>
        <div style="display:flex; gap:10px;">
          <button type="submit" class="btn btn-primary">Save product</button>
          <a href="inventory.php" class="btn btn-ghost">Cancel</a>
        </div>
      </form>
      <script>
      function previewImage(input) {
        var box = document.getElementById('ip-box');
        if (!box || !input.files || !input.files[0]) return;
        var reader = new FileReader();
        reader.onload = function (e) {
          box.innerHTML = '<img src="' + e.target.result + '" alt="">';
        };
        reader.readAsDataURL(input.files[0]);
      }
      </script>
    </div>
  <?php else: ?>
    <div class="filter-bar">
      <form method="GET" style="display:contents;">
        <div class="field">
          <label for="q">Search</label>
          <input type="text" id="q" name="q" value="<?= h($search) ?>" placeholder="Name or category…">
        </div>
        <div class="field">
          <label for="cf">Category</label>
          <select id="cf" name="category">
            <option value="All">All</option>
            <?php foreach ($categories as $c): ?>
              <option value="<?= h($c) ?>" <?= $catFilter === $c ? 'selected' : '' ?>><?= h($c) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="d">Show</label>
          <select id="d" name="deleted">
            <option value="0" <?= !$showDeleted ? 'selected' : '' ?>>Active only</option>
            <option value="1" <?= $showDeleted ? 'selected' : '' ?>>Include deleted</option>
          </select>
        </div>
        <div class="filter-actions">
          <button type="submit" class="btn btn-ghost btn-sm">Filter</button>
          <a href="inventory.php" class="btn btn-ghost btn-sm">Clear</a>
        </div>
      </form>
    </div>

    <div class="admin-head-row">
      <div></div>
      <a href="inventory.php?add=1" class="btn btn-primary btn-sm">+ Add product</a>
    </div>
    <table class="data-table">
      <thead><tr><th></th><th>Name</th><th>Category</th><th>Price</th><th>Stock</th><th>Status</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($products as $p): ?>
        <tr <?= $p['deleted_at'] ? 'style="opacity:0.5;"' : '' ?>>
          <td><?= product_visual($p, 'thumb') ?></td>
          <td data-label="Name"><?= h($p['name']) ?> <?= $p['is_featured'] ? '⭐' : '' ?></td>
          <td data-label="Category"><?= h($p['category']) ?></td>
          <td data-label="Price">Rs. <?= number_format($p['price'], 0) ?> <span class="muted">/ <?= h($p['unit']) ?></span></td>
          <td data-label="Stock"><?= $p['stock'] == 0 ? '<span class="stock-out">Out of stock</span>' : ($p['stock'] <= 10 ? '<span class="stock-low">' . (int)$p['stock'] . '</span>' : (int)$p['stock']) ?></td>
          <td data-label="Status"><?= $p['deleted_at'] ? '<span class="muted">Deleted</span>' : '<span style="color:var(--green-dark); font-weight:700;">Active</span>' ?></td>
          <td>
            <div class="table-actions">
              <?php if ($p['deleted_at']): ?>
                <form method="POST" style="display:inline;">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="restore">
                  <input type="hidden" name="id" value="<?= $p['id'] ?>">
                  <button type="submit" class="edit-link" style="background:none; border:none; cursor:pointer;">Restore</button>
                </form>
              <?php else: ?>
                <a class="edit-link" href="inventory.php?edit=<?= $p['id'] ?>">Edit</a>
                <form method="POST" style="display:inline;">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= $p['id'] ?>">
                  <button type="submit" class="del-link" style="background:none; border:none; cursor:pointer;" data-confirm="Remove this product? It can be restored later.">Delete</button>
                </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <p style="margin-top:14px;" class="muted">Tip: deleting a product hides it from the shop but keeps past orders intact. Use the filter to show deleted products and restore them.</p>
  <?php endif; ?>
</div>

<footer class="site-footer"><div class="container"><p>© 2026 Fresh Grocery. All rights reserved..</p></div></footer>
<script src="../js/app.js" defer></script>
</body>
</html>
