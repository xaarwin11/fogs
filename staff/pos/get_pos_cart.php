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
    
    // Step 1: Find if there is an open order for this table
    $stmt = $mysqli->prepare("SELECT id FROM `orders` WHERE table_id = ? AND status != 'paid' LIMIT 1");
    $stmt->bind_param('i', $table_id);
    $stmt->execute();
    $order_res = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $order_id = $order_res ? (int)$order_res['id'] : null;
    $items = [];

    // Step 2: If an order exists, get the items (if any)
    if ($order_id) {
        $stmt = $mysqli->prepare("
            SELECT oi.id as item_id, p.id as product_id, p.name, 
                   COALESCE(oi.price, p.price) as price, 
                   oi.quantity, oi.served
            FROM `order_items` oi
            JOIN `products` p ON oi.product_id = p.id
            WHERE oi.order_id = ?
        ");
        $stmt->bind_param('i', $order_id);
        $stmt->execute();
        $res = $stmt->get_result();
        
        while ($row = $res->fetch_assoc()) {
            $items[] = [
                'item_id'    => (int)$row['item_id'],
                'product_id' => (int)$row['product_id'],
                'name'       => $row['name'],
                'price'      => (float)$row['price'],
                'quantity'   => (int)$row['quantity'],
                'served'     => (int)$row['served']
            ];
        }
        $stmt->close();
    }
    
    echo json_encode([
        'success'  => true,
        'order_id' => $order_id,
        'table_id' => (int)$table_id,
        'items'    => $items
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
    exit;
}

$mysqli->close();
?>
