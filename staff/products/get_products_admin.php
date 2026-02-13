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
    
    // 1. Check if KDS column exists
    $hasKds = false;
    $colRes = $mysqli->query("SHOW COLUMNS FROM `products` LIKE 'kds'");
    if ($colRes && $colRes->num_rows > 0) $hasKds = true;
    $kdsField = $hasKds ? "p.kds" : "0 AS kds";

    // 2. Check if category table exists to decide join logic
    $tableCheck = $mysqli->query("SHOW TABLES LIKE 'categories'");
    
    if ($tableCheck->num_rows > 0) {
        // NEW LOGIC: Joins products with categories table
        $sql = "SELECT p.id, p.name, c.name as category_name, p.price, p.available, p.has_variation, $kdsField 
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                ORDER BY c.name ASC, p.name ASC";
    } else {
        // OLD LOGIC: Just uses the text column in products
        $sql = "SELECT id, name, category as category_name, price, available, has_variation, $kdsField 
                FROM products 
                ORDER BY category ASC, name ASC";
    }

    $res = $mysqli->query($sql);
    if (!$res) throw new Exception('Query failed: ' . $mysqli->error);

    $products = [];
    // Line 44 was here - now $res is guaranteed to be defined above
    while ($row = $res->fetch_assoc()) {
        $products[] = [
            'id'            => (int)$row['id'],
            'name'          => $row['name'],
            'category'      => $row['category_name'] ?? 'Uncategorized',
            'price'         => (float)$row['price'],
            'available'     => isset($row['available']) ? (bool)$row['available'] : true,
            'has_variation' => (int)($row['has_variation'] ?? 0),
            'kds'           => (bool)$row['kds']
        ];
    }
    
    $mysqli->close();
    echo json_encode(['success' => true, 'products' => $products]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>