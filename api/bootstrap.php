<?php
// Shared setup for every API file. Include it first: require __DIR__ . '/bootstrap.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);   // DB errors throw exceptions
header('Content-Type: application/json; charset=utf-8');

// Send JSON and stop
function json_out($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

try {
    require __DIR__ . '/../includes/auth.php';   // starts the session
    require __DIR__ . '/../includes/db.php';     // creates $conn
} catch (Throwable $e) {
    json_out(['error' => 'Server error'], 500);
}

// Read-only API: GET only
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    json_out(['error' => 'Method not allowed. Use GET.'], 405);
}

// Same login as the pages, but answer with 401 JSON instead of redirecting to login.php
if (!isset($_SESSION['user_id'])) {
    json_out(['error' => 'Not logged in'], 401);
}
