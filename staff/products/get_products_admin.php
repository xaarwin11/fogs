<?php
require_once __DIR__ . '/../../db.php';
session_start();
header('Content-Type: application/json');
$role = strtolower($_SESSION['role'] ?? '');
if (!in_array($role, ['staff','admin','manager'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}
try {
    $mysqli = get_db_conn();
    // Some installations may not have the `kds` column yet (migration not run).
    // Check for the column and fall back gracefully.
    $hasKds = false;
    $colRes = $mysqli->query("SHOW COLUMNS FROM `products` LIKE 'kds'");
    if ($colRes && $colRes->num_rows > 0) $hasKds = true;

    if ($hasKds) {
        $sql = 'SELECT id, name, category, price, available, kds FROM products ORDER BY category ASC, name ASC';
    } else {
        $sql = 'SELECT id, name, category, price, available FROM products ORDER BY category ASC, name ASC';
    }

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) throw new Exception('Prepare failed: ' . $mysqli->error . ' SQL: ' . $sql);
    $stmt->execute();
    $res = $stmt->get_result();
    $products = [];
    while ($row = $res->fetch_assoc()) {
        $products[] = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'category' => $row['category'],
            'price' => (float)$row['price'],
            'available' => isset($row['available']) ? (bool)$row['available'] : false,
            'kds' => isset($row['kds']) ? (bool)$row['kds'] : false
        ];
    }
    $stmt->close(); $mysqli->close();
    echo json_encode(['success'=>true,'products'=>$products]);
} catch (Exception $ex) {
    error_log('get_products_admin error: '.$ex->getMessage());
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'Server error', 'detail' => $ex->getMessage()]);
}
?>
