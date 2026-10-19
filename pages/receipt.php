<?php
require '../includes/auth.php';
require '../includes/db.php';
require_login();

$id = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare("SELECT s.id, s.total, s.created_at, u.username
                        FROM sales s LEFT JOIN users u ON u.id = s.user_id
                        WHERE s.id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$sale = $stmt->get_result()->fetch_assoc();

$items = null;
if ($sale) {
    $stmt = $conn->prepare("SELECT p.name, si.quantity, si.price
                            FROM sale_items si JOIN products p ON p.id = si.product_id
                            WHERE si.sale_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $items = $stmt->get_result();
}

$page_title = 'Receipt';
require '../includes/header.php';
?>
<?php if (!$sale): ?>
    <h1>Receipt not found</h1>
    <p><a href="sales_history.php">Back to sales history</a></p>
<?php else: ?>
    <div class="receipt">
        <h2>Inventory &amp; Sales</h2>
        <p>Receipt #<?= (int)$sale['id'] ?><br>
           <?= htmlspecialchars($sale['created_at']) ?><br>
           Cashier: <?= htmlspecialchars($sale['username'] ?? 'unknown') ?></p>
        <table>
            <tr><th>Item</th><th>Qty</th><th>Price</th><th>Subtotal</th></tr>
            <?php while ($r = $items->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($r['name']) ?></td>
                    <td><?= (int)$r['quantity'] ?></td>
                    <td>&#8369;<?= number_format($r['price'], 2) ?></td>
                    <td>&#8369;<?= number_format($r['price'] * $r['quantity'], 2) ?></td>
                </tr>
            <?php endwhile; ?>
        </table>
        <p class="cart-total">Total: &#8369;<?= number_format($sale['total'], 2) ?></p>
        <p>Thank you!</p>
    </div>
    <div class="no-print receipt-buttons">
        <button type="button" onclick="window.print()">Print receipt</button>
        <a href="sales.php" class="btn-link">New sale</a>
        <a href="sales_history.php" class="btn-link">Sales history</a>
    </div>
<?php endif; ?>
<?php require '../includes/footer.php'; ?>
