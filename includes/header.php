<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_login();
$page_title = $page_title ?? 'Inventory and Sales';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> - Inventory and Sales</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>style.css">
</head>
<body>
<nav class="topbar">
    <span class="brand">Inventory &amp; Sales</span>
    <a href="<?= BASE_URL ?>index.php">Dashboard</a>
    <a href="<?= BASE_URL ?>pages/categories.php">Categories</a>
    <a href="<?= BASE_URL ?>pages/products.php">Products</a>
    <a href="<?= BASE_URL ?>pages/sales.php">New Sale</a>
    <a href="<?= BASE_URL ?>pages/sales_history.php">Sales History</a>
    <a href="<?= BASE_URL ?>pages/reports.php">Reports</a>
    <span class="spacer"></span>
    <span class="user"><?= htmlspecialchars($_SESSION['username']) ?> (<?= htmlspecialchars($_SESSION['role']) ?>)</span>
    <a href="<?= BASE_URL ?>logout.php">Log out</a>
</nav>
<main class="container">
<?php if (isset($_SESSION['flash'])): ?>
    <div class="flash <?= htmlspecialchars($_SESSION['flash']['type']) ?>">
        <?= htmlspecialchars($_SESSION['flash']['text']) ?>
    </div>
    <?php unset($_SESSION['flash']); ?>
<?php endif; ?>
