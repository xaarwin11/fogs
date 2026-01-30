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
    
    // Check if KDS column exists
    $hasKds = false;
    $colRes = $mysqli->query("SHOW COLUMNS FROM `products` LIKE 'kds'");
    if ($colRes && $colRes->num_rows > 0) $hasKds = true;

    // Use a LEFT JOIN to get the category name from the categories table
    // If you haven't moved to the category table yet, this SQL will fall back gracefully
    $kdsField = $hasKds ? "p.kds" : "0 AS kds";
    
    // Check if category table exists to decide join logic
    $tableCheck = $mysqli->query("SHOW TABLES LIKE 'categories'");
    
    if ($tableCheck->num_rows > 0) {
        // NEW LOGIC: Joins products with categories table
        $sql = "SELECT p.id, p.name, c.name as category_name, p.price, p.available, $kdsField 
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                ORDER BY c.name ASC, p.name ASC";
    } else {
        // OLD LOGIC: Just uses the text column in products
        $sql = "SELECT id, name, category as category_name, price, available, $kdsField 
                FROM products 
                ORDER BY category ASC, name ASC";
    }

    $res = $mysqli->query($sql);
    if (!$res) throw new Exception('Query failed: ' . $mysqli->error);

    $products = [];
    while ($row = $res->fetch_assoc()) {
        $products[] = [
            'id'        => (int)$row['id'],
            'name'      => $row['name'],
            'category'  => $row['category_name'] ?? 'Uncategorized', // Use the joined name
            'price'     => (float)$row['price'],
            'available' => isset($row['available']) ? (bool)$row['available'] : true,
            'kds'       => (bool)$row['kds']
        ];
    }
    
    $mysqli->close();
    echo json_encode(['success' => true, 'products' => $products]);

} catch (Exception $ex) {
    error_log('get_products_admin error: ' . $ex->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $ex->getMessage()]);
}
?>