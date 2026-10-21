<?php
require '../includes/auth.php';
require '../includes/db.php';
require_admin();

function flash($type, $text) { $_SESSION['flash'] = ['type' => $type, 'text' => $text]; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $name   = trim($_POST['name'] ?? '');
    $id     = (int)($_POST['id'] ?? 0);

    try {
        if ($action === 'add' && $name !== '') {
            $stmt = $conn->prepare("INSERT INTO categories (name) VALUES (?)");
            $stmt->bind_param("s", $name);
            $stmt->execute();
            flash('success', 'Category added.');
        } elseif ($action === 'update' && $name !== '' && $id) {
            $stmt = $conn->prepare("UPDATE categories SET name = ? WHERE id = ?");
            $stmt->bind_param("si", $name, $id);
            $stmt->execute();
            flash('success', 'Category updated.');
        } elseif ($action === 'delete' && $id) {
            $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM products WHERE category_id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $count = $stmt->get_result()->fetch_assoc()['c'];
            if ($count > 0) {
                flash('error', "Can't delete: $count product(s) still use this category.");
            } else {
                $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                flash('success', 'Category deleted.');
            }
        } else {
            flash('error', 'Please enter a category name.');
        }
    } catch (mysqli_sql_exception $e) {
        flash('error', 'Database error: ' . $e->getMessage());
    }
    header("Location: categories.php");
    exit;
}

$editing = null;
if (isset($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT id, name FROM categories WHERE id = ?");
    $stmt->bind_param("i", $eid);
    $stmt->execute();
    $editing = $stmt->get_result()->fetch_assoc();
}

$rows = $conn->query(
    "SELECT c.id, c.name, COUNT(p.id) AS product_count
     FROM categories c LEFT JOIN products p ON p.category_id = c.id
     GROUP BY c.id, c.name ORDER BY c.name"
);

$page_title = 'Categories';
require '../includes/header.php';
?>
<h1>Categories</h1>

<form method="POST" class="inline-form">
    <input type="hidden" name="action" value="<?= $editing ? 'update' : 'add' ?>">
    <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int)$editing['id'] ?>"><?php endif; ?>
    <input type="text" name="name" placeholder="Category name" required
           value="<?= $editing ? htmlspecialchars($editing['name']) : '' ?>">
    <button type="submit"><?= $editing ? 'Save changes' : 'Add category' ?></button>
    <?php if ($editing): ?><a href="categories.php" class="btn-link">Cancel</a><?php endif; ?>
</form>

<table>
    <tr><th>#</th><th>Name</th><th>Products</th><th>Actions</th></tr>
    <?php while ($r = $rows->fetch_assoc()): ?>
        <tr>
            <td><?= (int)$r['id'] ?></td>
            <td><?= htmlspecialchars($r['name']) ?></td>
            <td><?= (int)$r['product_count'] ?></td>
            <td class="actions">
                <a href="categories.php?edit=<?= (int)$r['id'] ?>">Edit</a>
                <form method="POST" onsubmit="return confirm('Delete this category?');">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <button type="submit" class="danger">Delete</button>
                </form>
            </td>
        </tr>
    <?php endwhile; ?>
</table>
<?php require '../includes/footer.php'; ?>
