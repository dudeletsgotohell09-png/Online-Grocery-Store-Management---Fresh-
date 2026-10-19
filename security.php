<?php
/**
 * includes/security.php
 * Small helpers used across the app: escaping, client IP, redirects,
 * rate limiting for login.
 */

/** HTML-escape a value for output. */
function h($v): string {
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}

/** Get the client IP behind common proxy headers (best effort). */
function client_ip(): string {
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $k) {
        if (!empty($_SERVER[$k])) {
            // X-Forwarded-For can be a comma list — take the first.
            $ip = trim(explode(',', $_SERVER[$k])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
    }
    return '0.0.0.0';
}

/** Safe redirect — always exits. */
function redirect(string $url): void {
    header("Location: " . $url);
    exit;
}

/**
 * Validate a password against a basic strength policy.
 * Returns null on success or an error message.
 */
function validate_password(string $pw): ?string {
    if (strlen($pw) < 8)            return 'Password must be at least 8 characters.';
    if (!preg_match('/[A-Za-z]/', $pw)) return 'Password must contain at least one letter.';
    if (!preg_match('/[0-9]/',   $pw)) return 'Password must contain at least one number.';
    return null;
}

/**
 * Record a login attempt and decide whether the (email, ip) pair is locked out.
 * Uses the `login_attempts` table — keeps state across browser sessions.
 */
function check_login_throttle(PDO $pdo, string $email): bool {
    $ip = client_ip();
    $window = (new DateTime('-' . LOGIN_LOCKOUT_MINUTES . ' minutes'))->format('Y-m-d H:i:s');

    // Count recent failures for this email OR this IP.
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM login_attempts
         WHERE success = 0 AND created_at > ?
           AND (email = ? OR ip = ?)"
    );
    $stmt->execute([$window, strtolower($email), $ip]);
    $fails = (int)$stmt->fetchColumn();

    if ($fails >= LOGIN_MAX_ATTEMPTS) return false; // locked out
    return true;
}

function record_login_attempt(PDO $pdo, string $email, bool $success): void {
    $stmt = $pdo->prepare(
        "INSERT INTO login_attempts (email, ip, success) VALUES (?, ?, ?)"
    );
    $stmt->execute([strtolower($email), client_ip(), $success ? 1 : 0]);

    // Light garbage collection — keep the table from growing forever.
    if (rand(1, 50) === 1) {
        $pdo->prepare("DELETE FROM login_attempts WHERE created_at < ?")
            ->execute([(new DateTime('-7 days'))->format('Y-m-d H:i:s')]);
    }
}

/** Generate a hard-to-guess order ID — checks for collisions. */
function generate_order_id(PDO $pdo): string {
    for ($i = 0; $i < 5; $i++) {
        $id = 'FR' . date('ymd') . strtoupper(bin2hex(random_bytes(3)));
        $stmt = $pdo->prepare("SELECT 1 FROM orders WHERE id = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) return $id;
    }
    // Extremely unlikely fallback.
    return 'FR' . date('ymd') . strtoupper(uniqid());
}

/** Format money consistently. */
function money($n): string {
    return 'Rs. ' . number_format((float)$n, 0);
}
