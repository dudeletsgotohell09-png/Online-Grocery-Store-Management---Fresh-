<?php
/**
 * includes/header.php
 * ONE place that holds the navigation bar for every page.
 * Change a link here and it changes on every page.
 *
 *   customer_header('home', '');      // page in the project root  (shop.php, cart.php ...)
 *   customer_header('home', '../');   // page inside customer/
 *   admin_header('inventory');        // any page inside admin/
 *
 * $active = which nav link is highlighted: home | shop | contact
 *
 * head_meta($base)  — call inside each page's <head> to emit PWA manifest link,
 *                     theme-color, and icon links (base = '' or '../').
 */

/** Output PWA + theme meta tags. Call inside <head>. */
function head_meta(string $base = ''): void {
    echo '<meta name="theme-color" content="#23553A">' . "\n";
    echo '  <link rel="manifest" href="' . $base . 'manifest.json">' . "\n";
    echo '  <link rel="apple-touch-icon" href="' . $base . 'icon-192.png">' . "\n";
    echo '  <link rel="icon" type="image/png" sizes="192x192" href="' . $base . 'icon-192.png">' . "\n";
    echo '  <link rel="icon" type="image/png" sizes="512x512" href="' . $base . 'icon-512.png">' . "\n";
    // Expose the project root path so app.js can register the service worker
    // with the correct scope from any subfolder page.
    echo '  <script>window.FRESH_BASE=' . json_encode($base) . ';</script>' . "\n";
}

function customer_header(string $active, string $base = '', string $search = ''): void {
    $first = htmlspecialchars(explode(' ', $_SESSION['name'] ?? 'Guest')[0]);
    $links = [
        'home'    => ['Home',    $base . 'customer/dashboard.php'],
        'shop'    => ['Shop',    $base . 'shop.php'],
        'contact' => ['Contact', $base . 'contact.php'],
    ];
    ?>
<header class="topbar">
  <div class="topbar-inner">
    <a href="<?= $base ?>customer/dashboard.php" class="brand"><span class="leaf">&#127807;</span> Fresh</a>
    <nav class="nav-links">
<?php foreach ($links as $key => [$label, $href]): ?>
      <a href="<?= $href ?>" class="nav-link<?= $active === $key ? ' active' : '' ?>"><?= $label ?></a>
<?php endforeach; ?>
    </nav>
    <div class="topbar-right">
      <form class="search-box" method="GET" action="<?= $base ?>shop.php" role="search" autocomplete="off">
        <span>&#128269;</span>
        <input type="text" name="search" placeholder="Search groceries…" value="<?= htmlspecialchars($search) ?>" aria-label="Search groceries" autocomplete="off" data-autocomplete>
        <div class="search-suggest" data-suggest hidden></div>
      </form>
      <button type="button" class="theme-toggle" data-theme-toggle aria-label="Switch to dark mode"></button>
      <a href="<?= $base ?>cart.php" class="btn btn-ghost btn-sm cart-pill">
        &#128717; Cart <?php if (cart_count() > 0): ?><span class="cart-count-badge" data-cart-count><?= cart_count() ?></span><?php else: ?><span class="cart-count-badge" data-cart-count style="display:none;">0</span><?php endif; ?>
      </a>
      <a href="<?= $base ?>logout.php" class="btn btn-dark btn-sm">Log out<span class="logout-name"> (<?= $first ?>)</span></a>
    </div>
  </div>
</header>
<div class="toast-stack" data-toast-stack aria-live="polite" aria-atomic="true"></div>
<?php
}

function customer_footer(string $base = ''): void { ?>
<footer class="site-footer">
  <div class="container">
    <p>© 2026 Fresh Grocery. All rights reserved.. &middot; <a href="<?= $base ?>contact.php" style="color:var(--green-dark);font-weight:700;">Contact us</a></p>
  </div>
</footer>
<script src="<?= $base ?>js/app.js" defer></script>
<?php
}

function admin_header(string $active): void {
    $first = htmlspecialchars(explode(' ', $_SESSION['name'] ?? 'Admin')[0]);
    $links = [
        'dashboard' => ['Dashboard',  'dashboard.php'],
        'inventory' => ['Inventory',  'inventory.php'],
        'orders'    => ['Orders',     'orders.php'],
        'customers' => ['Customers',  'customers.php'],
        'messages'  => ['Messages',   'messages.php'],
    ];
    ?>
<header class="topbar">
  <div class="topbar-inner">
    <a href="dashboard.php" class="brand"><span class="leaf">&#127807;</span> Fresh <span style="font-size:0.7rem; font-weight:700; color:var(--green-dark); background:var(--green-pale); padding:3px 10px; border-radius:999px; margin-left:6px;">Admin</span></a>
    <nav class="nav-links">
<?php foreach ($links as $key => [$label, $href]): ?>
      <a href="<?= $href ?>" class="nav-link<?= $active === $key ? ' active' : '' ?>"><?= $label ?></a>
<?php endforeach; ?>
      <a href="../logout.php" class="nav-link">Log out<span class="logout-name"> (<?= $first ?>)</span></a>
    </nav>
    <button type="button" class="theme-toggle" data-theme-toggle aria-label="Switch to dark mode"></button>
  </div>
</header>
<div class="toast-stack" data-toast-stack aria-live="polite" aria-atomic="true"></div>
<?php
}

/** Simple breadcrumb renderer. Pass an array of [label, href|null]. */
function breadcrumbs(array $crumbs): void {
    echo '<nav class="breadcrumbs" aria-label="Breadcrumb"><ol>';
    $last = array_key_last($crumbs);
    foreach ($crumbs as $i => [$label, $href]) {
        echo '<li>';
        if ($i === $last || $href === null) {
            echo '<span>' . htmlspecialchars($label) . '</span>';
        } else {
            echo '<a href="' . htmlspecialchars($href) . '">' . htmlspecialchars($label) . '</a>';
        }
        echo '</li>';
    }
    echo '</ol></nav>';
}
