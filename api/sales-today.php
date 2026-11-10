<?php
// GET /api/sales-today.php -> number of sales and total pesos for today
require __DIR__ . '/bootstrap.php';

try {
    $r = $conn->query(
        "SELECT COUNT(*) AS sales_count, COALESCE(SUM(total), 0) AS total
         FROM sales WHERE DATE(created_at) = CURDATE()"
    )->fetch_assoc();

    json_out([
        'date'        => date('Y-m-d'),
        'sales_count' => (int)$r['sales_count'],
        'total'       => (float)$r['total'],
    ]);

} catch (Throwable $e) {
    json_out(['error' => 'Server error'], 500);
}
