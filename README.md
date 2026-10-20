# Fresh — Online Grocery Store Management System

HTML + CSS + JavaScript + PHP + MySQL only. No frameworks or external libraries.

### Pagination on the shop page
- 12 products per page (`PRODUCTS_PER_PAGE` in `config.php`).
- A clean pagination control with ellipses (… for skipped page numbers) appears
  below the product grid when there's more than one page.
- All current filters (search term, category, sort, in-stock toggle) are
  preserved when paging.
- The result count shows "12 products · page 2 of 5" style messaging.

### PWA / offline support
- `manifest.json` declares the app as installable (name, icons, theme color,
  start_url, shortcuts to Shop / Cart / Orders).
- Four PWA icons generated as PNGs at 192px and 512px (regular + maskable).
- `sw.js` (service worker) precaches the app shell on install and uses:
  - **Network-first** for HTML navigations (falls back to cached `login.php`
    when offline, so users can at least see the entry point).
  - **Cache-first** for static assets (CSS, JS, images, icons).
  - POST / API / admin / logout requests always hit the network (never cached).
- A `beforeinstallprompt` handler shows a small "Install Fresh" banner with
  Install + Later buttons. Dismissals are remembered for 24 hours.
- Service worker registration happens automatically from `js/app.js` on every
  page (silent fail if unsupported).

## What was already in the polished edition (still here)

### Security
- CSRF tokens on every POST form · POST-only deletes · session regeneration on
  login · hardened session cookies · brute-force throttling · strong password
  policy · security headers (CSP, X-Frame-Options, etc.) · atomic stock
  decrement · collision-free order IDs · forgot/reset password flow · disabled
  accounts · generic error messages.

### Customer features
- AJAX cart with toasts · product detail page · order detail with status
  timeline · reorder · cancel order (auto-restocks) · sort & filter · free
  delivery over Rs. 5,000 · coupons · saved addresses · breadcrumbs · password
  strength meter · recent orders on dashboard.

### Admin features
- Customers page (enable/disable accounts) · order filters & search · order
  detail with status timeline + admin notes · restock on cancel · status
  history · stock movements audit · 7-day sales chart · top sellers · low-stock
  alerts · CSV export (orders + products) · category CRUD · message replies ·
  soft-delete products with restore · search & filter on inventory · featured
  products.

### Schema additions (cumulative)
- Tables: `categories`, `addresses`, `order_status_history`, `stock_movements`,
  `coupons`, `password_resets`, `login_attempts`.
- `products.image` (NEW), `products.deleted_at`, `products.updated_at`,
  `products.is_featured`.
- Indexes on all FK / filter columns.

## File structure (extended)
```
fresh-grocery/
├── login.php              Customer / Admin login (CSRF + throttled)
├── register.php           Customer sign-up (CSRF + strong password)
├── forgot_password.php    Request password reset — now sends real email
├── reset_password.php     Set a new password with a token
├── logout.php             Ends the session -> login.php
├── shop.php               Shop (NEW: pagination + product images)
├── product.php            Product detail page (NEW: shows uploaded image)
├── cart.php               Cart (AJAX updates + image thumbnails)
├── checkout.php           NEW: sends order confirmation email after commit
├── order_confirmation.php
├── order_history.php      Customer's past orders + reorder
├── order_detail.php       Single order with timeline + cancel + reorder
├── contact.php            Contact form -> saved in `messages` table
├── config.php             DB + session + headers + NEW constants (uploads, mail, pagination)
├── schema.sql             Database + demo data + new tables + indexes + image column
├── manifest.json          NEW — PWA manifest
├── sw.js                  NEW — service worker (offline cache)
├── icon-192.png           NEW — PWA icon
├── icon-512.png           NEW — PWA icon
├── icon-192-maskable.png  NEW — maskable PWA icon
├── icon-512-maskable.png  NEW — maskable PWA icon
├── css/style.css          Original tokens + new components (toasts, charts, autocomplete, pagination, image preview, PWA banner)
├── js/
│   ├── theme.js           Light / dark mode switch (unchanged)
│   └── app.js             NEW: toasts + AJAX cart + autocomplete + SW registration + PWA install prompt
├── api/
│   ├── cart.php           JSON cart endpoint (AJAX)
│   └── search.php         NEW — autocomplete JSON endpoint
├── uploads/               NEW — product image uploads
│   ├── .htaccess          Blocks PHP execution inside this folder
│   └── index.html         Empty — prevents directory listing
├── includes/
│   ├── header.php         Navigation + breadcrumbs + toast container + autocomplete markup + head_meta()
│   ├── auth.php           Login/role guards + session regen
│   ├── cart.php           Cart helpers (stock-aware)
│   ├── csrf.php           CSRF token helpers
│   ├── helpers.php        Categories, coupons, status log, cart_state, json_response + NEW product_visual(), save_product_image(), delete_product_image()
│   ├── mail.php           NEW — send_mail() + mail_template() wrappers
│   └── security.php       Escaping, IP, redirects, password policy, login throttle
├── customer/
│   ├── dashboard.php      Customer home (now with product images in "Popular picks")
│   └── profile.php        Edit name / email / phone / password + saved addresses
└── admin/
    ├── dashboard.php      NEW: product images in low-stock + top-sellers lists
    ├── inventory.php      NEW: image upload field + image preview + remove image option
    ├── categories.php     Manage product categories
    ├── orders.php         View orders with filters/search
    ├── order_detail.php   Manage a single order + status timeline + note
    ├── customers.php      List customers, disable/enable accounts
    ├── messages.php       Read + reply + close + delete messages
    └── export.php         CSV export of orders or products
```

## How pages link
- Customer nav (`includes/header.php`): Home → `customer/dashboard.php`,
  Shop → `shop.php`, Contact → `contact.php`;
  plus Cart and Log out.
- Admin nav: Dashboard, Inventory, Orders, Customers, Messages; plus Log out.
- Pages in a sub-folder pass `'../'` to the header so links always resolve.
- A customer opening an admin page (or vice versa) is redirected to their own
  dashboard; logged-out visitors go to `login.php`.

## Dark mode and mobile
- The moon/sun button in the top bar (every page, including login) switches
  light and dark mode. The first visit follows the device setting; after that
  the choice is remembered (localStorage).
- All colours are CSS variables in `css/style.css` (`:root` = light,
  `:root[data-theme="dark"]` = dark).
- Layout is responsive: phone (≤720px), tablet (≤900px) and desktop. On phones
  the top bar becomes brand + buttons, nav links and search; admin tables turn
  into stacked cards.

## Configurable constants (in config.php)
| Constant | Default | Purpose |
|----------|---------|---------|
| `DB_HOST` / `DB_NAME` / `DB_USER` / `DB_PASS` | localhost / fresh_grocery / root / "" | DB connection |
| `FREE_DELIVERY_THRESHOLD` | 5000 | Subtotal that unlocks free delivery |
| `DELIVERY_FEE` | 250 | Flat delivery fee below threshold |
| `LOGIN_MAX_ATTEMPTS` | 5 | Failed logins before lockout |
| `LOGIN_LOCKOUT_MINUTES` | 15 | Lockout window |
| `PRODUCTS_PER_PAGE` | 12 | Shop page page size |
| `UPLOAD_DIR` / `UPLOAD_URL` | __DIR__/uploads / uploads/ | Image upload location |
| `MAX_UPLOAD_BYTES` | 2097152 | 2 MB max image size |
| `ALLOWED_IMAGE_TYPES` | jpeg/png/webp/gif | Allowed MIME types |
| `MAIL_FROM` / `MAIL_FROM_NAME` | no-reply@freshgrocery.example / Fresh Grocery | Sender address |
| `MAIL_LOG_ONLY` | true | Set false on production to actually send email |
