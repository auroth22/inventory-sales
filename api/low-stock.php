<?php
// GET /api/low-stock.php -> active products at or below their reorder level, lowest stock first
require __DIR__ . '/bootstrap.php';

try {
    $res = $conn->query(
        "SELECT id, name, stock, reorder_level
         FROM products
         WHERE is_active = 1 AND stock <= reorder_level
         ORDER BY stock ASC, name"
    );
    $items = [];
    foreach ($res as $r) {
        $items[] = [
            'id'            => (int)$r['id'],
            'name'          => $r['name'],
            'stock'         => (int)$r['stock'],
            'reorder_level' => (int)$r['reorder_level'],
        ];
    }
    json_out(['count' => count($items), 'low_stock' => $items]);

} catch (Throwable $e) {
    json_out(['error' => 'Server error'], 500);
}
