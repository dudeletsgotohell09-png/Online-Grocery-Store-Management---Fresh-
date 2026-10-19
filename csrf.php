<?php
/**
 * includes/csrf.php
 * Single-purpose CSRF helpers.
 * Token stored in session, validated on every POST/DELETE-style request.
 */

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Render a hidden input for any <form method="POST">. */
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

/**
 * Verify the token on POST. Call this at the top of any page that
 * accepts POST. Bails out with 403 + a friendly message on mismatch.
 */
function csrf_verify(): void {
    $sent = $_POST['csrf_token'] ?? '';
    if (!is_string($sent) || !hash_equals($_SESSION['csrf_token'] ?? '', $sent)) {
        http_response_code(403);
        die('Invalid or expired form submission. Please go back and try again.');
    }
}

/** True if the current request is a POST. */
function is_post(): bool {
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}
