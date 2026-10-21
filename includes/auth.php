<?php
session_start();

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
