<?php
require '../includes/auth.php';
require '../includes/db.php';
require_login();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);   // make DB errors throw exceptions

function flash($type, $text) { $_SESSION['flash'] = ['type' => $type, 'text' => $text]; }

if (!isset($_SESSION['cart'])) { $_SESSION['cart'] = []; }   // cart = [product_id => quantity]

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $pid = (int)($_POST['product_id'] ?? 0);
        $qty = max(1, (int)($_POST['qty'] ?? 1));

        $stmt = $conn->prepare("SELECT name, stock FROM products WHERE id = ?");
        $stmt->bind_param("i", $pid);
        $stmt->execute();
        $p = $stmt->get_result()->fetch_assoc();

        if (!$p) {
            flash('error', 'Product not found.');
        } else {
            $newQty = ($_SESSION['cart'][$pid] ?? 0) + $qty;
            if ($newQty > $p['stock']) {
                flash('error', "Only {$p['stock']} of {$p['name']} in stock.");
            } else {
                $_SESSION['cart'][$pid] = $newQty;
            }
        }

    } elseif ($action === 'remove') {
        unset($_SESSION['cart'][(int)($_POST['product_id'] ?? 0)]);

    } elseif ($action === 'clear') {
        $_SESSION['cart'] = [];

    } elseif ($action === 'checkout') {
        $cart = $_SESSION['cart'];
        if (!$cart) {
            flash('error', 'The cart is empty.');
        } else {
            try {
                $conn->begin_transaction();      // all steps succeed together, or none do

                // 1. Lock each product row, check stock, read the REAL price from the database
                $lines = [];
                $total = 0;
                $sel = $conn->prepare("SELECT name, price, stock FROM products WHERE id = ? FOR UPDATE");
                foreach ($cart as $pid => $qty) {
                    $sel->bind_param("i", $pid);
                    $sel->execute();
                    $p = $sel->get_result()->fetch_assoc();
                    if (!$p) { throw new Exception("A product in your cart no longer exists."); }
                    if ($p['stock'] < $qty) { throw new Exception("Not enough stock for {$p['name']} (only {$p['stock']} left)."); }
                    $lines[] = ['id' => $pid, 'qty' => $qty, 'price' => $p['price']];
                    $total += $p['price'] * $qty;
                }
                $total = round($total, 2);

                // 2. Create the sale
                $uid = $_SESSION['user_id'];
                $ins = $conn->prepare("INSERT INTO sales (user_id, total) VALUES (?, ?)");
                $ins->bind_param("id", $uid, $total);
                $ins->execute();
                $sale_id = $conn->insert_id;

                // 3. Save each item and reduce the stock
                $item  = $conn->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
                $stock = $conn->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
                foreach ($lines as $l) {
                    $item->bind_param("iiid", $sale_id, $l['id'], $l['qty'], $l['price']);
                    $item->execute();
                    $stock->bind_param("ii", $l['qty'], $l['id']);
                    $stock->execute();
                }

                $conn->commit();                 // save everything
                $_SESSION['cart'] = [];
                header("Location: receipt.php?id=" . $sale_id);
                exit;

            } catch (Exception $e) {
                $conn->rollback();               // undo everything if anything failed
                flash('error', $e->getMessage());
            }
        }
    }
    header("Location: sales.php");
    exit;
}

// ----- Data for the page -----
$q    = trim($_GET['q'] ?? '');
$like = "%$q%";
$stmt = $conn->prepare("SELECT id, name, price, stock FROM products WHERE stock > 0 AND name LIKE ? ORDER BY name");
$stmt->bind_param("s", $like);
$stmt->execute();
$products = $stmt->get_result();

$cartLines = [];
$cartTotal = 0;
$get = $conn->prepare("SELECT id, name, price, stock FROM products WHERE id = ?");
foreach ($_SESSION['cart'] as $pid => $qty) {
    $get->bind_param("i", $pid);
    $get->execute();
    $p = $get->get_result()->fetch_assoc();
    if ($p) {
        $p['qty'] = $qty;
        $p['subtotal'] = $p['price'] * $qty;
        $cartTotal += $p['subtotal'];
        $cartLines[] = $p;
    }
}

$page_title = 'New Sale';
require '../includes/header.php';
?>
<h1>New Sale</h1>

<div class="pos-grid">
    <section>
        <h2>Products</h2>
        <form method="GET" class="inline-form">
            <input type="text" name="q" placeholder="Search products..." value="<?= htmlspecialchars($q) ?>">
            <button type="submit">Search</button>
            <?php if ($q !== ''): ?><a href="sales.php" class="btn-link">Clear</a><?php endif; ?>
        </form>

        <table>
            <tr><th>Product</th><th>Price</th><th>Stock</th><th>Add to cart</th></tr>
            <?php while ($r = $products->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($r['name']) ?></td>
                    <td>&#8369;<?= number_format($r['price'], 2) ?></td>
                    <td><?= (int)$r['stock'] ?></td>
                    <td>
                        <form method="POST" class="add-form">
                            <input type="hidden" name="action" value="add">
                            <input type="hidden" name="product_id" value="<?= (int)$r['id'] ?>">
                            <input type="number" name="qty" value="1" min="1" max="<?= (int)$r['stock'] ?>" class="qty-input">
                            <button type="submit">Add</button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
        </table>
    </section>

    <section>
        <h2>Cart</h2>
        <?php if (!$cartLines): ?>
            <p>The cart is empty.</p>
        <?php else: ?>
            <table>
                <tr><th>Item</th><th>Qty</th><th>Subtotal</th><th></th></tr>
                <?php foreach ($cartLines as $l): ?>
                    <tr>
                        <td><?= htmlspecialchars($l['name']) ?></td>
                        <td><?= (int)$l['qty'] ?></td>
                        <td>&#8369;<?= number_format($l['subtotal'], 2) ?></td>
                        <td>
                            <form method="POST">
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="product_id" value="<?= (int)$l['id'] ?>">
                                <button type="submit" class="danger">&times;</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
            <p class="cart-total">Total: &#8369;<?= number_format($cartTotal, 2) ?></p>
            <div class="cart-buttons">
                <form method="POST">
                    <input type="hidden" name="action" value="checkout">
                    <button type="submit">Checkout</button>
                </form>
                <form method="POST">
                    <input type="hidden" name="action" value="clear">
                    <button type="submit" class="danger">Clear cart</button>
                </form>
            </div>
        <?php endif; ?>
    </section>
</div>
<?php require '../includes/footer.php'; ?>
