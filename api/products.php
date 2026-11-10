<?php
// GET /api/products.php            -> all active products
// GET /api/products.php?q=para     -> search by name
// GET /api/products.php?category_id=2
require __DIR__ . '/bootstrap.php';

try {
    $q   = trim($_GET['q'] ?? '');
    $cat = (int)($_GET['category_id'] ?? 0);
    $like = "%$q%";

    $sql = "SELECT p.id, p.name, c.name AS category, p.price, p.stock, p.reorder_level
            FROM products p
            LEFT JOIN categories c ON c.id = p.category_id
            WHERE p.is_active = 1 AND p.name LIKE ?";
    if ($cat > 0) { $sql .= " AND p.category_id = ?"; }
    $sql .= " ORDER BY p.name";

    $stmt = $conn->prepare($sql);
    if ($cat > 0) { $stmt->bind_param("si", $like, $cat); }
    else          { $stmt->bind_param("s", $like); }
    $stmt->execute();

    $items = [];
    foreach ($stmt->get_result() as $r) {
        $items[] = [
            'id'            => (int)$r['id'],
            'name'          => $r['name'],
            'category'      => $r['category'],
            'price'         => (float)$r['price'],
            'stock'         => (int)$r['stock'],
            'reorder_level' => (int)$r['reorder_level'],
            'low_stock'     => $r['stock'] <= $r['reorder_level'],
        ];
    }
    json_out(['count' => count($items), 'products' => $items]);

} catch (Throwable $e) {
    json_out(['error' => 'Server error'], 500);
}
