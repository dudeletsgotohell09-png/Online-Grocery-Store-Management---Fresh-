<?php
$root_path = '../';
require_once $root_path . 'config.php';
require_role('admin', $root_path);

$categories = get_categories($pdo);
$error = '';
$success = '';

/* Add category. */
if (is_post() && ($_POST['action'] ?? '') === 'add') {
    csrf_verify();
    $name = trim($_POST['name'] ?? '');
    $icon = trim($_POST['icon'] ?? '🛒') ?: '🛒';
    $sort = (int)($_POST['sort_order'] ?? 0);
    if ($name === '') {
        $error = 'Category name cannot be empty.';
    } else {
        try {
            $pdo->prepare("INSERT INTO categories (name, icon, sort_order) VALUES (?, ?, ?)")->execute([$name, $icon, $sort]);
            $success = 'Category added.';
        } catch (PDOException $e) {
            $error = 'A category with that name already exists.';
        }
    }
}

/* Edit category. */
if (is_post() && ($_POST['action'] ?? '') === 'edit') {
    csrf_verify();
    $id   = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $icon = trim($_POST['icon'] ?? '🛒') ?: '🛒';
    $sort = (int)($_POST['sort_order'] ?? 0);
    if ($name === '') {
        $error = 'Category name cannot be empty.';
    } else {
        try {
            $pdo->prepare("UPDATE categories SET name = ?, icon = ?, sort_order = ? WHERE id = ?")->execute([$name, $icon, $sort, $id]);
            $success = 'Category updated.';
        } catch (PDOException $e) {
            $error = 'Another category already uses that name.';
        }
    }
}

/* Delete category. */
if (is_post() && ($_POST['action'] ?? '') === 'delete') {
    csrf_verify();
    $id = (int)($_POST['id'] ?? 0);
    // Check whether any products use it.
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category = (SELECT name FROM categories WHERE id = ?) AND deleted_at IS NULL");
    $stmt->execute([$id]);
    $used = (int)$stmt->fetchColumn();
    if ($used > 0) {
        $error = "Cannot delete: {$used} product(s) still use this category. Reassign them first.";
    } else {
        $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$id]);
        $success = 'Category deleted.';
    }
}

$categories = get_categories($pdo);
$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$editCat = null;
if ($editId) {
    foreach ($categories as $c) { if ($c['id'] == $editId) { $editCat = $c; break; } }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Categories — Fresh Admin</title>
<link rel="stylesheet" href="../css/style.css">
<script src="../js/theme.js"></script>
<?php if ($success): ?>
<meta name="flash-toast" content="<?= h($success) ?>|success">
<?php elseif ($error): ?>
<meta name="flash-toast" content="<?= h($error) ?>|error">
<?php endif; ?>
<?php head_meta('../'); ?>
</head>
<body>

<?php admin_header('dashboard'); ?>

<div class="container" style="padding-bottom:60px;">
  <div class="dash-hero"><h1 style="margin-top:26px;">Categories</h1></div>

  <?php if ($error && !$success): ?><div class="form-error"><?= h($error) ?></div><?php endif; ?>

  <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 24px; align-items:start;" class="cat-grid">
    <style>@media (max-width: 760px){ .cat-grid{ grid-template-columns: 1fr !important; } }</style>

    <div>
      <table class="data-table">
        <thead><tr><th>Icon</th><th>Name</th><th>Sort</th><th>Products</th><th></th></tr></thead>
        <tbody>
        <?php
        $counts = [];
        $rows = $pdo->query("SELECT category, COUNT(*) AS c FROM products WHERE deleted_at IS NULL GROUP BY category")->fetchAll();
        foreach ($rows as $r) $counts[$r['category']] = $r['c'];
        ?>
        <?php foreach ($categories as $c): ?>
          <tr>
            <td data-label="Icon"><span class="row-icon"><?= h($c['icon']) ?></span></td>
            <td data-label="Name"><?= h($c['name']) ?></td>
            <td data-label="Sort"><?= (int)$c['sort_order'] ?></td>
            <td data-label="Products"><?= (int)($counts[$c['name']] ?? 0) ?></td>
            <td>
              <div class="table-actions">
                <a class="edit-link" href="categories.php?edit=<?= $c['id'] ?>">Edit</a>
                <form method="POST" style="display:inline;">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= $c['id'] ?>">
                  <button type="submit" class="del-link" style="background:none; border:none; cursor:pointer;" data-confirm="Delete this category?">Delete</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="panel">
      <h3><?= $editCat ? 'Edit category' : 'Add category' ?></h3>
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="<?= $editCat ? 'edit' : 'add' ?>">
        <?php if ($editCat): ?>
          <input type="hidden" name="id" value="<?= (int)$editCat['id'] ?>">
        <?php endif; ?>
        <div class="field"><label>Icon (emoji)</label><input type="text" name="icon" maxlength="4" value="<?= h($editCat['icon'] ?? '🛒') ?>" required></div>
        <div class="field"><label>Name</label><input type="text" name="name" value="<?= h($editCat['name'] ?? '') ?>" required></div>
        <div class="field"><label>Sort order</label><input type="number" name="sort_order" value="<?= (int)($editCat['sort_order'] ?? 0) ?>" min="0"></div>
        <div style="display:flex; gap:10px;">
          <button type="submit" class="btn btn-primary"><?= $editCat ? 'Update' : 'Add' ?></button>
          <?php if ($editCat): ?>
            <a href="categories.php" class="btn btn-ghost">Cancel</a>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>
</div>

<footer class="site-footer"><div class="container"><p>© 2026 Fresh Grocery. All rights reserved..</p></div></footer>
<script src="../js/app.js" defer></script>
</body>
</html>
