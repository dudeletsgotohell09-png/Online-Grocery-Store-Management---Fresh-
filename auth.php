<?php
/**
 * includes/auth.php
 * Session/role guards, plus hardened login/logout helpers.
 *
 * $root_path lets each page tell this file how many folders deep it is,
 * so the redirect back to login.php always resolves correctly.
 * Example: admin/dashboard.php sets $root_path = '../';
 */
if (!isset($root_path)) {
    $root_path = '';
}

function require_login($root_path) {
    if (!isset($_SESSION['user_id'])) {
        redirect($root_path . "login.php");
    }
}

function require_role($role, $root_path) {
    require_login($root_path);
    if (($_SESSION['role'] ?? '') !== $role) {
        // Logged in, but wrong role -> send them to their own dashboard instead of login.
        if ($_SESSION['role'] === 'admin') {
            redirect($root_path . "admin/dashboard.php");
        } else {
            redirect($root_path . "customer/dashboard.php");
        }
    }
    // Disabled accounts are kicked out.
    if (!empty($_SESSION['user_status']) && $_SESSION['user_status'] === 'disabled') {
        logout_user($root_path);
    }
}

/**
 * Log a user in: regenerate the session id (anti-fixation), then stash the basics.
 */
function login_user(array $user, string $root_path = ''): void {
    session_regenerate_id(true);
    $_SESSION['user_id']     = $user['id'];
    $_SESSION['name']        = $user['name'];
    $_SESSION['email']       = $user['email'];
    $_SESSION['role']        = $user['role'];
    $_SESSION['user_status'] = $user['status'] ?? 'active';
    $_SESSION['login_at']    = time();
}

/** Destroy the session and bounce to login. */
function logout_user(string $root_path = ''): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    redirect($root_path . "login.php");
}
