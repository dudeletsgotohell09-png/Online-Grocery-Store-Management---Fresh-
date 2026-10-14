<?php
$root_path = '../';
require_once $root_path . 'config.php';
require_role('admin', $root_path);

$flash = '';
$flashKind = 'success';

/* Reply + close. */
if (is_post() && ($_POST['action'] ?? '') === 'reply') {
    csrf_verify();
    $mid = (int)($_POST['message_id'] ?? 0);
    $reply = trim($_POST['admin_reply'] ?? '');
    if ($reply) {
        $pdo->prepare("UPDATE messages SET admin_reply = ?, status = 'replied', replied_at = NOW() WHERE id = ?")
            ->execute([$reply, $mid]);
        $flash = 'Reply sent.';
    } else {
        $flash = 'Reply cannot be empty.';
        $flashKind = 'error';
    }
}

/* Mark closed. */
if (is_post() && ($_POST['action'] ?? '') === 'close') {
    csrf_verify();
    $mid = (int)($_POST['message_id'] ?? 0);
    $pdo->prepare("UPDATE messages SET status = 'closed' WHERE id = ?")->execute([$mid]);
    $flash = 'Message closed.';
}

/* Delete (POST only). */
if (is_post() && ($_POST['action'] ?? '') === 'delete') {
    csrf_verify();
    $mid = (int)($_POST['message_id'] ?? 0);
    $pdo->prepare("DELETE FROM messages WHERE id = ?")->execute([$mid]);
    $flash = 'Message deleted.';
}

$statusFilter = $_GET['status'] ?? 'All';
$sql = "SELECT * FROM messages WHERE 1=1";
$params = [];
if (in_array($statusFilter, ['open','replied','closed'])) {
    $sql .= " AND status = ?";
    $params[] = $statusFilter;
}
$sql .= " ORDER BY created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$messages = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Customer Messages — Fresh</title>
<link rel="stylesheet" href="../css/style.css">
<script src="../js/theme.js"></script>
<?php if ($flash): ?>
<meta name="flash-toast" content="<?= h($flash) ?>|<?= h($flashKind) ?>">
<?php endif; ?>
<?php head_meta('../'); ?>
</head>
<body>

<?php admin_header('messages'); ?>

<div class="container" style="padding-bottom:60px;">
  <div class="dash-hero">
    <div class="eyebrow">Admin</div>
    <h1>Customer messages</h1>
  </div>

  <div class="filter-bar">
    <form method="GET" style="display:contents;">
      <div class="field">
        <label for="status">Status</label>
        <select id="status" name="status">
          <option value="All" <?= $statusFilter === 'All' ? 'selected' : '' ?>>All</option>
          <option value="open" <?= $statusFilter === 'open' ? 'selected' : '' ?>>Open</option>
          <option value="replied" <?= $statusFilter === 'replied' ? 'selected' : '' ?>>Replied</option>
          <option value="closed" <?= $statusFilter === 'closed' ? 'selected' : '' ?>>Closed</option>
        </select>
      </div>
      <div class="filter-actions">
        <button type="submit" class="btn btn-ghost btn-sm">Filter</button>
        <a href="messages.php" class="btn btn-ghost btn-sm">Clear</a>
      </div>
    </form>
  </div>

  <?php if (!$messages): ?>
    <div class="empty-state"><div class="glyph">&#128236;</div><h3>No messages</h3><p>Messages sent from the customer Contact page appear here.</p></div>
  <?php else: foreach ($messages as $m): ?>
    <div class="message-card">
      <div class="mc-head">
        <div>
          <div class="mc-subject"><?= h($m['subject'] ?: '(no subject)') ?></div>
          <div class="mc-meta"><?= h($m['name']) ?> &middot; <?= h($m['email']) ?> &middot; <?= date('M j, Y · g:ia', strtotime($m['created_at'])) ?></div>
        </div>
        <span class="message-status <?= h($m['status']) ?>"><?= h($m['status']) ?></span>
      </div>
      <div class="mc-body"><?= nl2br(h($m['body'])) ?></div>

      <?php if (!empty($m['admin_reply'])): ?>
        <div class="mc-reply">
          <strong>Admin reply (<?= $m['replied_at'] ? date('M j, Y · g:ia', strtotime($m['replied_at'])) : '' ?>):</strong>
          <p style="margin: 6px 0 0; color: var(--ink);"><?= nl2br(h($m['admin_reply'])) ?></p>
        </div>
      <?php elseif ($m['status'] !== 'closed'): ?>
        <div class="mc-reply">
          <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="message_id" value="<?= (int)$m['id'] ?>">
            <textarea name="admin_reply" placeholder="Type your reply…" required></textarea>
            <div class="mc-reply-actions">
              <button type="submit" name="action" value="reply" class="btn btn-primary btn-sm">Send reply</button>
              <button type="submit" name="action" value="close" class="btn btn-ghost btn-sm" formnovalidate data-confirm="Close this message without replying?">Close without reply</button>
            </div>
          </form>
        </div>
      <?php endif; ?>

      <div style="margin-top: 12px;">
        <form method="POST" style="display:inline;">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="message_id" value="<?= (int)$m['id'] ?>">
          <button type="submit" class="del-link" style="background:none; border:none; cursor:pointer; font-weight:700; font-size:0.8rem;" data-confirm="Delete this message?">Delete</button>
        </form>
      </div>
    </div>
  <?php endforeach; endif; ?>
</div>

<footer class="site-footer"><div class="container"><p>© 2026 Fresh Grocery. All rights reserved..</p></div></footer>
<script src="../js/app.js" defer></script>
</body>
</html>
