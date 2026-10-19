# Fresh — Online Grocery Store Management System (CS12012, polished + extended edition)

HTML + CSS + JavaScript + PHP + MySQL only. No frameworks or external libraries.

This is the **polished + extended** version of the original project. The visual
design and layout are unchanged; the changes focus on **security, missing features,
bug fixes, and now five additional feature areas**:

1. **Product image uploads** (replaces emoji-only products)
2. **Email sending** (password resets + order confirmations)
3. **Search autocomplete** (live product suggestions in the search box)
4. **Pagination on the shop page** (12 products per page)
5. **PWA / offline support** (installable app + service worker caching)

## Run it (XAMPP / WAMP)
1. Copy the `fresh-grocery` folder into `C:\xampp\htdocs\`.
2. Start **Apache** and **MySQL** in the XAMPP control panel.
3. Open http://localhost/phpmyadmin → **Import** → choose `schema.sql` → Go.
   (drops the old DB and recreates `fresh_grocery` with all new tables and demo data)
4. Make the `uploads/` folder writable by PHP:
   - On Windows: usually no action needed.
   - On Linux/Mac: `chmod -R 775 uploads/`
5. Open http://localhost/fresh-grocery/login.php
6. If your MySQL has a password, edit `config.php`.

Importing from the command line instead of phpMyAdmin? Use
`mysql --default-character-set=utf8mb4 -u root < schema.sql`
(without that flag the emoji icons are stored garbled).

Local testing: `MAIL_LOG_ONLY` is `true` in `config.php`, so no mail server is needed.
Password-reset links are shown on screen; set it to `false` only on a real server.

Demo logins (also listed in schema.sql):
- Customer: `customer@demo.com` / `demo123`
- Admin:    `admin@demo.com` / `admin123`

Coupon codes to try at checkout:
- `FRESH100`  → Rs. 100 off orders over Rs. 1,000
- `WELCOME10` → 10% off orders over Rs. 500

## What's new in this extended edition (5 features)

### 1. Product image uploads
- New `image` column on the `products` table.
- Admin can upload a JPEG / PNG / WebP / GIF (max 2 MB) per product.
- Image is validated server-side via `finfo_file()` (MIME sniffing, not the
  client-supplied `$_FILES['type']`).
- Saved to `uploads/` with a randomised name; the previous file is deleted on
  replacement. There's a "Remove current image" checkbox too.
- The `uploads/` directory has an `.htaccess` that blocks direct execution of
  any PHP files (so even if someone uploads a `.php` disguised as a JPEG, it
  can't run).
- Everywhere a product is shown — shop, product detail, cart, customer
  dashboard, admin inventory table, admin dashboard low-stock & top-sellers —
  the image is rendered if present, otherwise the emoji icon is used. The
  fallback is automatic via the shared `product_visual()` helper.

### 2. Email sending
- New `includes/mail.php` wraps PHP's built-in `mail()` with a branded HTML
  template.
- `forgot_password.php` now sends an actual password-reset email with a
  clickable button. Order confirmation emails are sent from `checkout.php`.
- For local development without SMTP, set `MAIL_LOG_ONLY = true` in `config.php`
  (default). Every email body is written to PHP's `error_log` so you can read
  the reset links and order confirmations during testing. The forgot-password
  page also surfaces the reset link on screen in this mode.
- For production, configure `php.ini`'s `[mail function]` (SMTP host, port,
  `sendmail_from`), set `MAIL_LOG_ONLY = false`, and emails will actually be sent.
- `base_url()` is auto-detected so absolute links in emails work regardless of
  where you host the app.

### 3. Search autocomplete
- New `api/search.php` returns up to 8 matching products as JSON (with image
  URL, price, category, in-stock status).
- The header search box on every customer page now shows a live dropdown as you
  type (debounced 200 ms).
- Keyboard navigation: ↑/↓ to move, Enter to go to the highlighted product,
  Esc to close, Enter on empty dropdown to submit the full search to shop.php.
- The dropdown shows product image thumbnails, name, category, price, and an
  "Out of stock" badge when applicable.

### 4. Pagination on the shop page
- 12 products per page (`PRODUCTS_PER_PAGE` in `config.php`).
- A clean pagination control with ellipses (… for skipped page numbers) appears
  below the product grid when there's more than one page.
- All current filters (search term, category, sort, in-stock toggle) are
  preserved when paging.
- The result count shows "12 products · page 2 of 5" style messaging.

### 5. PWA / offline support
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

## Notes for graders
- No external CSS or JS libraries are used (no jQuery, Bootstrap, Chart.js,
  PHPMailer, etc.). The sales chart is a pure CSS bar chart; toasts, AJAX,
  and autocomplete use plain `XMLHttpRequest`; email uses PHP's built-in
  `mail()`; PWA icons are PNGs generated with Python+PIL.
- All database writes use **prepared statements** with bound parameters.
- Output is escaped with `htmlspecialchars()` (via the `h()` helper) to
  prevent XSS.
- File uploads are validated by MIME type via `finfo_file()`, restricted to
  JPEG/PNG/WebP/GIF, capped at 2 MB, and stored in a directory whose
  `.htaccess` denies execution of any script files.
- The mock payment at checkout is intentionally simple — no real card data is
  stored.
- For the PWA to be installable, the app must be served over HTTPS (or from
  `localhost`). On XAMPP, `localhost` works for testing.

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
