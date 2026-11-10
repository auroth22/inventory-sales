<?php
require '../includes/auth.php';
require '../includes/db.php';
require_login();
$admin = is_admin();   // true only for admins

function flash($type, $text) { $_SESSION['flash'] = ['type' => $type, 'text' => $text]; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Server-side check: hiding buttons is not security, this is
    if (!$admin) {
        flash('error', 'Only an admin can change products.');
        header("Location: products.php");
        exit;
    }
    $action   = $_POST['action'] ?? '';
    $id       = (int)($_POST['id'] ?? 0);
    $name     = trim($_POST['name'] ?? '');
    $cat      = (int)($_POST['category_id'] ?? 0);
    $category = $cat > 0 ? $cat : null;
    $price    = (float)($_POST['price'] ?? 0);
    $stock    = (int)($_POST['stock'] ?? 0);
    $reorder  = (int)($_POST['reorder_level'] ?? 5);

    try {
        if (($action === 'add' || $action === 'update') && ($name === '' || $price < 0 || $stock < 0 || $reorder < 0)) {
            flash('error', 'Please fill in a name and use zero or positive numbers.');
        } elseif ($action === 'add') {
            $stmt = $conn->prepare("INSERT INTO products (category_id, name, price, stock, reorder_level) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("isdii", $category, $name, $price, $stock, $reorder);
            $stmt->execute();
            flash('success', 'Product added.');
        } elseif ($action === 'update' && $id) {
            $stmt = $conn->prepare("UPDATE products SET category_id = ?, name = ?, price = ?, stock = ?, reorder_level = ? WHERE id = ?");
            $stmt->bind_param("isdiii", $category, $name, $price, $stock, $reorder, $id);
            $stmt->execute();
            flash('success', 'Product updated.');
        } elseif ($action === 'delete' && $id) {
            try {
                $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                flash('success', 'Product deleted.');
            } catch (mysqli_sql_exception $e) {
                if ($e->getCode() == 1451) {   // part of a past sale, so hide it instead
                    $stmt = $conn->prepare("UPDATE products SET is_active = 0 WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                    flash('success', 'Product removed (archived, because it is part of past sales).');
                } else {
                    throw $e;   // the outer catch will show it
                }
            }
        }
    } catch (mysqli_sql_exception $e) {
        flash('error', "Can't complete that. The product may already be part of a sale. (" . $e->getMessage() . ")");
    }
    header("Location: products.php");
    exit;
}

$cats = $conn->query("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name")->fetch_all(MYSQLI_ASSOC);

$q    = trim($_GET['q'] ?? '');
$like = "%$q%";
$stmt = $conn->prepare(
    "SELECT p.*, c.name AS category FROM products p
     LEFT JOIN categories c ON c.id = p.category_id
     WHERE p.is_active = 1 AND p.name LIKE ?
     ORDER BY (p.stock <= p.reorder_level) DESC,
              CASE WHEN p.stock <= p.reorder_level THEN p.stock END ASC,
              p.name"
);
$stmt->bind_param("s", $like);
$stmt->execute();
$rows = $stmt->get_result();

$page_title = 'Products';
require '../includes/header.php';
?>
<h1>Products</h1>

<?php if ($admin && count($cats) === 0): ?>
    <div class="flash error">Add at least one <a href="categories.php">category</a> first.</div>
<?php endif; ?>

<?php if ($admin): ?>
<!-- Add product (editing happens in the popup further down) -->
<form method="POST" class="grid-form">
    <input type="hidden" name="action" value="add">

    <label>Name
        <input type="text" name="name" required>
    </label>
    <label>Category
        <select name="category_id">
            <option value="0">-- none --</option>
            <?php foreach ($cats as $c): ?>
                <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>Price
        <input type="number" name="price" step="0.01" min="0" required>
    </label>
    <label>Stock
        <input type="number" name="stock" min="0" required value="0">
    </label>
    <label>Reorder level
        <input type="number" name="reorder_level" min="0" required value="5">
    </label>
    <div class="form-buttons">
        <button type="submit">Add product</button>
    </div>
</form>
<?php endif; ?>

<form method="GET" class="inline-form">
    <input type="text" name="q" placeholder="Search products..." value="<?= htmlspecialchars($q) ?>">
    <button type="submit">Search</button>
    <?php if ($q !== ''): ?><a href="products.php" class="btn-link">Clear</a><?php endif; ?>
</form>

<table>
    <tr><th>#</th><th>Name</th><th>Category</th><th>Price</th><th>Stock</th><?php if ($admin): ?><th>Actions</th><?php endif; ?></tr>
    <?php $section = null; while ($r = $rows->fetch_assoc()):
        $isLow = $r['stock'] <= $r['reorder_level'];
        if ($section !== $isLow): $section = $isLow; ?>
        <tr class="section-row"><td colspan="<?= $admin ? 6 : 5 ?>"><?= $isLow ? 'Low stock' : 'Well stocked' ?></td></tr>
    <?php endif; ?>
        <tr class="<?= $isLow ? 'low' : '' ?>">
            <td><?= (int)$r['id'] ?></td>
            <td><?= htmlspecialchars($r['name']) ?></td>
            <td><?= htmlspecialchars($r['category'] ?? '-') ?></td>
            <td>&#8369;<?= number_format($r['price'], 2) ?></td>
            <td><?= (int)$r['stock'] ?></td>
            <?php if ($admin): ?>
            <td class="actions">
                <button type="button" class="link-button edit-btn"
                        data-id="<?= (int)$r['id'] ?>"
                        data-name="<?= htmlspecialchars($r['name']) ?>"
                        data-category-id="<?= (int)($r['category_id'] ?? 0) ?>"
                        data-price="<?= htmlspecialchars($r['price']) ?>"
                        data-stock="<?= (int)$r['stock'] ?>"
                        data-reorder="<?= (int)$r['reorder_level'] ?>">Edit</button>
                <form method="POST" onsubmit="return confirm('Delete this product?');">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <button type="submit" class="danger">Delete</button>
                </form>
            </td>
            <?php endif; ?>
        </tr>
    <?php endwhile; ?>
</table>

<?php if ($admin): ?>
<!-- Edit popup -->
<dialog id="edit-modal" class="auth-modal">
    <div class="auth-inner">
        <button type="button" class="close-x" id="edit-close" aria-label="Close">&times;</button>
        <h2 class="modal-title">Edit product</h2>
        <p class="modal-sub" id="edit-title"></p>

        <form method="POST" class="modal-form">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit-id">

            <label>Name
                <input type="text" name="name" id="edit-name" required>
            </label>
            <label>Category
                <select name="category_id" id="edit-category">
                    <option value="0">-- none --</option>
                    <?php foreach ($cats as $c): ?>
                        <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Price
                <input type="number" name="price" id="edit-price" step="0.01" min="0" required>
            </label>
            <label>Stock
                <input type="number" name="stock" id="edit-stock" min="0" required>
            </label>
            <label>Reorder level
                <input type="number" name="reorder_level" id="edit-reorder" min="0" required>
            </label>

            <div class="modal-buttons">
                <button type="button" class="secondary" id="edit-cancel">Cancel</button>
                <button type="submit">Save changes</button>
            </div>
        </form>
    </div>
</dialog>

<script>
    const editModal = document.getElementById('edit-modal');

    document.querySelectorAll('.edit-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const d = btn.dataset;
            document.getElementById('edit-id').value      = d.id;
            document.getElementById('edit-name').value    = d.name;
            document.getElementById('edit-price').value   = d.price;
            document.getElementById('edit-stock').value   = d.stock;
            document.getElementById('edit-reorder').value = d.reorder;
            document.getElementById('edit-title').textContent = d.name;

            // Pick the product's category (fall back to "none" if it was archived)
            const sel = document.getElementById('edit-category');
            sel.value = d.categoryId;
            if (sel.value !== d.categoryId) { sel.value = '0'; }

            editModal.showModal();
        });
    });

    document.getElementById('edit-close').addEventListener('click',  () => editModal.close());
    document.getElementById('edit-cancel').addEventListener('click', () => editModal.close());
    editModal.addEventListener('click', e => { if (e.target === editModal) editModal.close(); });
</script>
<?php endif; ?>
<?php require '../includes/footer.php'; ?>