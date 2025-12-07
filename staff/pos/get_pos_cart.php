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

$table_id = intval($_GET['table_id'] ?? 0);
if (!$table_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing table_id']);
    exit;
}

try {
    $mysqli = get_db_conn();
    
    // Get open orders for this table. Aggregate per product so we return one row per product
    $stmt = $mysqli->prepare("SELECT MAX(o.id) as order_id, MIN(oi.id) as item_id, p.id as product_id, p.name, COALESCE(oi.price, p.price) as price, SUM(oi.quantity) as quantity, SUM(oi.served) as served
        FROM `orders` o
        JOIN `order_items` oi ON o.id = oi.order_id
        JOIN `products` p ON oi.product_id = p.id
        WHERE o.table_id = ? AND o.status != 'paid'
        GROUP BY p.id, p.name, COALESCE(oi.price, p.price)");
    if (!$stmt) throw new Exception('Prepare failed: ' . $mysqli->error);
    
    $stmt->bind_param('i', $table_id);
    $stmt->execute();
    $res = $stmt->get_result();
    
    $items = [];
    $order_id = null;
    while ($row = $res->fetch_assoc()) {
        $order_id = $row['order_id'];
        $items[] = [
            'item_id' => (int)$row['item_id'],
            'product_id' => (int)$row['product_id'],
            'name' => $row['name'],
            'price' => (float)$row['price'],
            'quantity' => (int)$row['quantity']
        ];
    }
    $res->free();
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'order_id' => $order_id ? (int)$order_id : null,
        'table_id' => (int)$table_id,
        'items' => $items
    ]);
} catch (Exception $ex) {
    error_log('get_pos_cart error: ' . $ex->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $ex->getMessage()]);
}

$mysqli->close();
?>
