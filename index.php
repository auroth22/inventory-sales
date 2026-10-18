<?php
$page_title = 'Dashboard';
require 'includes/header.php';

$products   = $conn->query("SELECT COUNT(*) AS c FROM products")->fetch_assoc()['c'];
$categories = $conn->query("SELECT COUNT(*) AS c FROM categories")->fetch_assoc()['c'];
$low        = $conn->query("SELECT COUNT(*) AS c FROM products WHERE stock <= reorder_level")->fetch_assoc()['c'];
$today      = $conn->query("SELECT COALESCE(SUM(total),0) AS t FROM sales WHERE DATE(created_at) = CURDATE()")->fetch_assoc()['t'];
$lowList    = $conn->query("SELECT name, stock, reorder_level FROM products WHERE stock <= reorder_level ORDER BY stock ASC LIMIT 10");
?>
<h1>Dashboard</h1>
<p>Welcome, <?= htmlspecialchars($_SESSION['username']) ?>!</p>

<div class="cards">
    <div class="card"><span class="num"><?= $products ?></span><span>Products</span></div>
    <div class="card"><span class="num"><?= $categories ?></span><span>Categories</span></div>
    <div class="card warn"><span class="num"><?= $low ?></span><span>Low stock</span></div>
    <div class="card"><span class="num">&#8369;<?= number_format($today, 2) ?></span><span>Sales today</span></div>
</div>

<h2>Low stock items</h2>
<?php if ($lowList->num_rows === 0): ?>
    <p>Nothing is running low. 👍</p>
<?php else: ?>
    <table>
        <tr><th>Product</th><th>Stock</th><th>Reorder level</th></tr>
        <?php while ($r = $lowList->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($r['name']) ?></td>
                <td><?= (int)$r['stock'] ?></td>
                <td><?= (int)$r['reorder_level'] ?></td>
            </tr>
        <?php endwhile; ?>
    </table>
<?php endif; ?>
<?php require 'includes/footer.php'; ?>
