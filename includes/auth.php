<?php
// ---- Session settings (change the minutes here, one place only) ----
$idle_minutes = 15;                                   // log out after this many idle minutes
$timeout      = $idle_minutes * 60;                   // in seconds

ini_set('session.gc_maxlifetime', $timeout);          // keep PHP's own cleanup in sync
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();

// Idle timeout: no activity for too long = log the user out
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['last_active']) && time() - $_SESSION['last_active'] > $timeout) {
        session_unset();
        session_destroy();
        session_start();                              // fresh, empty session
    } else {
        $_SESSION['last_active'] = time();
    }
}

define('BASE_URL', '/inventory-sales/');

function require_login() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: " . BASE_URL . "login.php");
        exit;
    }
}

// True only when the logged-in user's role is 'admin'
function is_admin() {
    return ($_SESSION['role'] ?? '') === 'admin';
}

// Put this at the top of any page that only admins may use
function require_admin() {
    require_login();
    if (!is_admin()) {
        $_SESSION['flash'] = ['type' => 'error', 'text' => 'Only an admin can open that page.'];
        header("Location: " . BASE_URL . "index.php");
        exit;
    }
}
?>