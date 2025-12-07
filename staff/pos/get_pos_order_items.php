<?php

require_once __DIR__ . '/../../db.php';
session_start();

header('Content-Type: application/json');

$role = strtolower($_SESSION['role'] ?? '');
if (!in_array($role, ['staff','admin','manager','kitchen'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

$table_id = $_GET['table_id'] ?? null;

if (!$table_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing table_id']);
    exit;
}

try {
    $mysqli = get_db_conn();
    
    // Get open order for table
    $stmt = $mysqli->prepare('SELECT id FROM `orders` WHERE table_id = ? AND status = "open" LIMIT 1');
    if (!$stmt) throw new Exception('Prepare failed: ' . $mysqli->error);
    
    $stmt->bind_param('i', $table_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $order = $res->fetch_assoc();
    $res->free();
    $stmt->close();
    
    if (!$order) {
        echo json_encode(['success' => true, 'items' => [], 'total' => 0, 'subtotal' => 0]);
        exit;
    }
    
    $order_id = (int)$order['id'];
    
    // Get order items with product details
    $stmt = $mysqli->prepare('\n        SELECT oi.id, oi.quantity, p.name, p.price \n        FROM `order_items` oi \n        JOIN `products` p ON oi.product_id = p.id \n        WHERE oi.order_id = ? \n        ORDER BY oi.created_at ASC\n    ');
    if (!$stmt) throw new Exception('Prepare failed: ' . $mysqli->error);
    
    $stmt->bind_param('i', $order_id);
    $stmt->execute();
    $res = $stmt->get_result();
    
    $items = [];
    $subtotal = 0;
    
    while ($row = $res->fetch_assoc()) {
        $line_total = (float)$row['price'] * (int)$row['quantity'];
        $subtotal += $line_total;
        
        $items[] = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'price' => (float)$row['price'],
            'quantity' => (int)$row['quantity'],
            'line_total' => $line_total
        ];
    }
    $res->free();
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'order_id' => $order_id,
        'items' => $items,
        'subtotal' => $subtotal,
        'total' => $subtotal
    ]);
} catch (Exception $ex) {
    error_log('get_pos_order_items error: ' . $ex->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $ex->getMessage()]);
}

$mysqli->close();
?>
