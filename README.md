# 🛒 Fresh — Online Grocery Store Management System

Fresh is a web-based online grocery store. Customers browse products, fill a cart and place orders. Store administrators manage inventory, orders, customers and messages from a separate admin area.

Built with **HTML, CSS, JavaScript, PHP and MySQL only**. No frameworks or external libraries.

---

## ✨ Features

### Customer
- Register, log in, log out, and reset a forgotten password by email link
- Browse the shop by category, search with live autocomplete, and page through results
- Product detail pages with images, price, unit and stock status
- AJAX shopping cart (add, change quantity and remove without reloading)
- Checkout with saved delivery addresses and promo codes
- Order confirmation, order history and order detail with status tracking
- Profile page and contact form
- Light and dark mode, and a layout that fits phones, tablets and desktops
- Installable as an app (PWA) with offline caching

### Admin
- Dashboard with sales, order and stock summaries, low-stock alerts and top sellers
- Inventory: add, edit and soft-delete products, upload product photos, adjust stock
- Category management
- Order management with the stages *Processing → Shipped → Delivered / Cancelled*, internal notes and a full status history
- Customer list and enable/disable accounts
- Customer messages: read and reply
- Export data

### Security
- Passwords hashed with `password_hash()`
- Role-based access control (customer / admin)
- CSRF tokens on every form
- Login throttling against brute-force attempts
- Prepared statements (PDO) against SQL injection
- Image uploads validated by MIME type, with PHP execution blocked in `uploads/`
- Audit trails for order status changes and stock changes

---

## 🧰 Tech Stack

| Layer | Technology |
|---|---|
| Front end | HTML5, CSS3 (custom design tokens, responsive, dark mode), vanilla JavaScript |
| Back end | PHP 8 (PDO, sessions) |
| Database | MySQL / MariaDB (12 tables, InnoDB, utf8mb4) |
| Server | Apache via XAMPP / WAMP |

---

## 🚀 Getting Started

### Requirements
- XAMPP or WAMP (Apache, PHP 8+, MySQL/MariaDB)
- A web browser

### Installation
1. **Copy the project** folder `fresh-grocery` into `C:\xampp\htdocs\`.
2. **Start Apache and MySQL** in the XAMPP Control Panel.
3. **Import the database:** open `http://localhost/phpmyadmin`, go to **Import**, choose `schema.sql` and click **Go**. This creates the `fresh_grocery` database with all tables and demo data. (It drops any existing `fresh_grocery` database first.)
   From the command line, use:
   ```bash
   mysql --default-character-set=utf8mb4 -u root < schema.sql
   ```
4. **Check the connection** settings in `config.php` if your MySQL has a password or a different port.
5. **Make `uploads/` writable** (Linux/Mac only): `chmod -R 775 uploads/`
6. **Open the app:** `http://localhost/fresh-grocery/login.php`
   (If Apache runs on port 8080, use `http://localhost:8080/fresh-grocery/login.php`.)

### Demo accounts

| Role | Email | Password |
|---|---|---|
| Customer | `customer@demo.com` | `demo123` |
| Admin | `admin@demo.com` | `admin123` |

### Demo coupons
- `FRESH100` — Rs. 100 off orders over Rs. 1,000
- `WELCOME10` — 10% off orders over Rs. 500

> **Email in local testing:** `MAIL_LOG_ONLY` is `true` in `config.php`, so no mail server is needed. Password-reset links appear on screen. Set it to `false` on a real server with SMTP configured.

---

## 📁 Project Structure

```
fresh-grocery/
├── admin/                  Admin pages (dashboard, inventory, orders, customers, categories, messages, export)
├── api/                    JSON endpoints (cart.php, search.php)
├── customer/               Customer dashboard and profile
├── includes/               Shared code (auth, csrf, security, header/footer, helpers, mail, cart)
├── css/style.css           Design tokens, light/dark themes, responsive rules
├── js/                     app.js (cart, search, UI) and theme.js (dark mode)
├── uploads/                Product images
├── config.php              Database connection and settings
├── schema.sql              Database schema and demo data
├── login.php, register.php, logout.php, forgot_password.php, reset_password.php
├── shop.php, product.php, cart.php, checkout.php, order_confirmation.php
├── order_history.php, order_detail.php, contact.php
└── manifest.json, sw.js    PWA manifest and service worker
```

---

## 🗄️ Database

Twelve tables in the `fresh_grocery` database:

`users` · `categories` · `products` · `addresses` · `orders` · `order_items` · `order_status_history` · `stock_movements` · `coupons` · `messages` · `password_resets` · `login_attempts`

Main relationships: a user has many orders, addresses, messages and reset tokens. An order has many order items and status-history records. A product appears in many order items and has many stock movements. The full ER diagram is in the project report.

---


---

## 🔮 Future Enhancements
- Real online payment gateway integration
- Email and SMS order notifications on a production SMTP service
- Delivery tracking with a map and delivery-slot selection
- Product reviews and ratings, wishlists and recommendations
- Sales reports with charts and downloadable PDF invoices
- Multi-language support (English, Sinhala, Tamil)

---

## 📄 License
Developed for educational purposes as part of the CS12012 Web Development module at KDU.
