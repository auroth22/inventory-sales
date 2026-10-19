<?php
require '../includes/auth.php';
require '../includes/db.php';
require_login();

$from = $_GET['from'] ?? '';
$to   = $_GET['to'] ?? '';
$f = $from !== '' ? $from : '2000-01-01';     // empty filter = include everything
$t = $to   !== '' ? $to   : '2099-12-31';

// Aggregate functions: COUNT, SUM, AVG, MAX
$stmt = $conn->prepare(
    "SELECT COUNT(*) AS cnt,
            COALESCE(SUM(total), 0) AS sum_total,
            COALESCE(AVG(total), 0) AS avg_total,
            COALESCE(MAX(total), 0) AS max_total
     FROM sales
     WHERE DATE(created_at) BETWEEN ? AND ?"
);
$stmt->bind_param("ss", $f, $t);
$stmt->execute();
$sum = $stmt->get_result()->fetch_assoc();

$stmt = $conn->prepare(
    "SELECT s.id, s.total, s.created_at, u.username
     FROM sales s LEFT JOIN users u ON u.id = s.user_id
     WHERE DATE(s.created_at) BETWEEN ? AND ?
     ORDER BY s.created_at DESC"
);
$stmt->bind_param("ss", $f, $t);
$stmt->execute();
$rows = $stmt->get_result();

$page_title = 'Sales History';
require '../includes/header.php';
?>
<h1>Sales History</h1>

<form method="GET" class="inline-form">
    <label>From <input type="date" name="from" value="<?= htmlspecialchars($from) ?>"></label>
    <label>To <input type="date" name="to" value="<?= htmlspecialchars($to) ?>"></label>
    <button type="submit">Filter</button>
    <?php if ($from !== '' || $to !== ''): ?><a href="sales_history.php" class="btn-link">Clear</a><?php endif; ?>
</form>

<div class="cards">
    <div class="card"><span class="num"><?= (int)$sum['cnt'] ?></span><span>Transactions</span></div>
    <div class="card"><span class="num">&#8369;<?= number_format($sum['sum_total'], 2) ?></span><span>Total sales</span></div>
    <div class="card"><span class="num">&#8369;<?= number_format($sum['avg_total'], 2) ?></span><span>Average sale</span></div>
    <div class="card"><span class="num">&#8369;<?= number_format($sum['max_total'], 2) ?></span><span>Biggest sale</span></div>
</div>

<table>
    <tr><th>Receipt #</th><th>Date</th><th>Cashier</th><th>Total</th><th></th></tr>
    <?php while ($r = $rows->fetch_assoc()): ?>
        <tr>
            <td><?= (int)$r['id'] ?></td>
            <td><?= htmlspecialchars($r['created_at']) ?></td>
            <td><?= htmlspecialchars($r['username'] ?? 'unknown') ?></td>
            <td>&#8369;<?= number_format($r['total'], 2) ?></td>
            <td><a href="receipt.php?id=<?= (int)$r['id'] ?>">View receipt</a></td>
        </tr>
    <?php endwhile; ?>
</table>
<?php require '../includes/footer.php'; ?>
