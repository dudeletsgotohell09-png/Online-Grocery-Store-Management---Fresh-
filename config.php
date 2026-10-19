<?php
/**
 * config.php
 * Central database connection + bootstrap, shared by every page.
 * Also starts the session with hardened cookie settings and emits
 * a small set of security headers.
 *
 * Update the DB constants below to match your local MySQL setup
 * (defaults match a fresh XAMPP/WAMP install).
 */

/* ---- App-level constants ---- */
define('DB_HOST', 'localhost');
define('DB_NAME', 'fresh_grocery');
define('DB_USER', 'root');
define('DB_PASS', 'Navoda2004');

/* Free delivery threshold (Rs.) — orders at or above this get free shipping. */
define('FREE_DELIVERY_THRESHOLD', 5000);
/* Flat delivery fee used below the threshold. */
define('DELIVERY_FEE', 250);
/* Max login attempts per (email + IP) within the lockout window. */
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_MINUTES', 15);

/* ---- Pagination ---- */
define('PRODUCTS_PER_PAGE', 12);

/* ---- Image uploads ---- */
define('UPLOAD_DIR', __DIR__ . '/uploads');
define('UPLOAD_URL', 'uploads/');
define('MAX_UPLOAD_BYTES', 2 * 1024 * 1024); // 2 MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/webp', 'image/gif']);

/* ---- Mail ---- */
/* On XAMPP/WAMP, configure php.ini [mail function] to use SMTP, then set: */
define('MAIL_FROM', 'no-reply@freshgrocery.example');
define('MAIL_FROM_NAME', 'Fresh Grocery');
/* Set to true to log every email body to PHP error_log instead of / in addition to sending.
   Useful when no SMTP is configured — you can read the reset links from the log. */
define('MAIL_LOG_ONLY', true);   // true = local testing (no SMTP needed): emails are written to the PHP error log and the reset link is shown on screen. Set false on a real server.

/* ---- Base URL auto-detection (used for absolute links in emails, PWA manifest, etc.) ---- */
function base_url(): string {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (($_SERVER['SERVER_PORT'] ?? '') == 443);
    $scheme = $https ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    // Strip the script filename from PHP_SELF to get the folder path.
    $path = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    // If we're already at the project root, $path is '\' or '.' — normalise to ''.
    if ($path === '\\' || $path === '.' || $path === '/') $path = '';
    return $scheme . '://' . $host . $path;
}

/* ---- Session hardening ---- */
if (session_status() === PHP_SESSION_NONE) {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (($_SERVER['SERVER_PORT'] ?? '') == 443);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'secure'   => $https,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/* ---- Security headers (best-effort; safe if headers already sent) ---- */
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-XSS-Protection: 1; mode=block');
    // Permissive CSP — uploads/ images are served as data: or /uploads/.
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'");
}

/* ---- Database connection ---- */
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Never leak raw DB errors to end users.
    error_log('DB connection failed: ' . $e->getMessage());
    http_response_code(500);
    die('This service is temporarily unavailable. Please try again in a moment.');
}

/* ---- Helper includes ---- */
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/mail.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/cart.php';
require_once __DIR__ . '/includes/header.php';   // nav bar + head_meta() used by every page
