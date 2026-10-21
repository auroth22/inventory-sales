<?php
require '../includes/auth.php';
require '../includes/db.php';
require_admin();

// Small helper: run a prepared query and return all rows
function run($conn, $sql, $types = '', ...$params) {
    $stmt = $conn->prepare($sql);
    if ($types !== '') { $stmt->bind_param($types, ...$params); }
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$from = $_GET['from'] ?? '';
$to   = $_GET['to'] ?? '';
$min  = max(1, (int)($_GET['min'] ?? 2));
$f = $from !== '' ? $from : '2000-01-01';
$t = $to   !== '' ? $to   : '2099-12-31';

// 1. COUNT, SUM, AVG, MIN, MAX in one query
$sum = run($conn,
    "SELECT COUNT(*) AS cnt,
            COALESCE(SUM(total), 0) AS sum_total,
            COALESCE(AVG(total), 0) AS avg_total,
            COALESCE(MIN(total), 0) AS min_total,
            COALESCE(MAX(total), 0) AS max_total
     FROM sales
     WHERE DATE(created_at) BETWEEN ? AND ?", "ss", $f, $t)[0];

// 2. GROUP BY day
$daily = run($conn,
    "SELECT DATE(created_at) AS day, COUNT(*) AS transactions, SUM(total) AS revenue
     FROM sales
     WHERE DATE(created_at) BETWEEN ? AND ?
     GROUP BY day
     ORDER BY day DESC
     LIMIT 31", "ss", $f, $t);

// 3. GROUP BY month
$monthly = run($conn,
    "SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, COUNT(*) AS transactions, SUM(total) AS revenue
     FROM sales
     WHERE DATE(created_at) BETWEEN ? AND ?
     GROUP BY month
     ORDER BY month DESC", "ss", $f, $t);

// 4. Best sellers: SUM of quantity per product, biggest first, top 5
$best = run($conn,
    "SELECT p.name, SUM(si.quantity) AS units, SUM(si.quantity * si.price) AS revenue
     FROM sale_items si
     JOIN products p ON p.id = si.product_id
     JOIN sales s    ON s.id = si.sale_id
     WHERE DATE(s.created_at) BETWEEN ? AND ?
     GROUP BY p.id, p.name
     ORDER BY units DESC
     LIMIT 5", "ss", $f, $t);

// 5. Revenue per category (JOIN + GROUP BY)
$byCat = run($conn,
    "SELECT COALESCE(c.name, 'Uncategorized') AS category,
            SUM(si.quantity) AS units,
            SUM(si.quantity * si.price) AS revenue
     FROM sale_items si
     JOIN products p ON p.id = si.product_id
     JOIN sales s    ON s.id = si.sale_id
     LEFT JOIN categories c ON c.id = p.category_id
     WHERE DATE(s.created_at) BETWEEN ? AND ?
     GROUP BY category
     ORDER BY revenue DESC", "ss", $f, $t);

// 6. HAVING: only categories with at least $min products, plus stock value
$stockCats = run($conn,
    "SELECT c.name, COUNT(p.id) AS product_count,
            COALESCE(SUM(p.stock), 0) AS total_stock,
            COALESCE(SUM(p.price * p.stock), 0) AS stock_value
     FROM categories c
     JOIN products p ON p.category_id = c.id
     GROUP BY c.id, c.name
     HAVING COUNT(p.id) >= ?
     ORDER BY product_count DESC", "i", $min);

// For the little bars in the daily table
$maxDaily = 0;
foreach ($daily as $d) { $maxDaily = max($maxDaily, $d['revenue']); }

$page_title = 'Reports';
require '../includes/header.php';
?>
<h1>Reports</h1>

<form method="GET" class="inline-form">
    <label>From <input type="date" name="from" value="<?= htmlspecialchars($from) ?>"></label>
    <label>To <input type="date" name="to" value="<?= htmlspecialchars($to) ?>"></label>
    <label>Min products per category <input type="number" name="min" min="1" value="<?= (int)$min ?>" class="qty-input"></label>
    <button type="submit">Apply</button>
    <?php if ($from !== '' || $to !== '' || $min !== 2): ?><a href="reports.php" class="btn-link">Reset</a><?php endif; ?>
</form>

<div class="cards">
    <div class="card"><span class="num"><?= (int)$sum['cnt'] ?></span><span>Transactions (COUNT)</span></div>
    <div class="card"><span class="num">&#8369;<?= number_format($sum['sum_total'], 2) ?></span><span>Total sales (SUM)</span></div>
    <div class="card"><span class="num">&#8369;<?= number_format($sum['avg_total'], 2) ?></span><span>Average sale (AVG)</span></div>
    <div class="card"><span class="num">&#8369;<?= number_format($sum['min_total'], 2) ?></span><span>Smallest sale (MIN)</span></div>
    <div class="card"><span class="num">&#8369;<?= number_format($sum['max_total'], 2) ?></span><span>Biggest sale (MAX)</span></div>
</div>

<h2>Daily sales</h2>
<?php if (!$daily): ?><p>No sales in this range yet.</p><?php else: ?>
<table>
    <tr><th>Date</th><th>Transactions</th><th>Revenue</th><th></th></tr>
    <?php foreach ($daily as $d): ?>
        <tr>
            <td><?= htmlspecialchars($d['day']) ?></td>
            <td><?= (int)$d['transactions'] ?></td>
            <td>&#8369;<?= number_format($d['revenue'], 2) ?></td>
            <td class="bar-cell"><div class="bar" style="width: <?= $maxDaily > 0 ? round($d['revenue'] / $maxDaily * 100) : 0 ?>%"></div></td>
        </tr>
    <?php endforeach; ?>
</table>
<?php endif; ?>

<h2>Monthly sales</h2>
<?php if (!$monthly): ?><p>No sales in this range yet.</p><?php else: ?>
<table>
    <tr><th>Month</th><th>Transactions</th><th>Revenue</th></tr>
    <?php foreach ($monthly as $m): ?>
        <tr>
            <td><?= htmlspecialchars($m['month']) ?></td>
            <td><?= (int)$m['transactions'] ?></td>
            <td>&#8369;<?= number_format($m['revenue'], 2) ?></td>
        </tr>
    <?php endforeach; ?>
</table>
<?php endif; ?>

<h2>Top 5 best sellers</h2>
<?php if (!$best): ?><p>No sales in this range yet.</p><?php else: ?>
<table>
    <tr><th>#</th><th>Product</th><th>Units sold</th><th>Revenue</th></tr>
    <?php foreach ($best as $i => $b): ?>
        <tr>
            <td><?= $i + 1 ?></td>
            <td><?= htmlspecialchars($b['name']) ?></td>
            <td><?= (int)$b['units'] ?></td>
            <td>&#8369;<?= number_format($b['revenue'], 2) ?></td>
        </tr>
    <?php endforeach; ?>
</table>
<?php endif; ?>

<h2>Revenue by category</h2>
<?php if (!$byCat): ?><p>No sales in this range yet.</p><?php else: ?>
<table>
    <tr><th>Category</th><th>Units sold</th><th>Revenue</th></tr>
    <?php foreach ($byCat as $c): ?>
        <tr>
            <td><?= htmlspecialchars($c['category']) ?></td>
            <td><?= (int)$c['units'] ?></td>
            <td>&#8369;<?= number_format($c['revenue'], 2) ?></td>
        </tr>
    <?php endforeach; ?>
</table>
<?php endif; ?>

<h2>Stock value by category (at least <?= (int)$min ?> products)</h2>
<?php if (!$stockCats): ?><p>No category has <?= (int)$min ?> or more products yet.</p><?php else: ?>
<table>
    <tr><th>Category</th><th>Products</th><th>Total stock</th><th>Stock value</th></tr>
    <?php foreach ($stockCats as $c): ?>
        <tr>
            <td><?= htmlspecialchars($c['name']) ?></td>
            <td><?= (int)$c['product_count'] ?></td>
            <td><?= (int)$c['total_stock'] ?></td>
            <td>&#8369;<?= number_format($c['stock_value'], 2) ?></td>
        </tr>
    <?php endforeach; ?>
</table>
<?php endif; ?>
<?php require '../includes/footer.php'; ?>
