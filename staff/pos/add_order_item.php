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

$input = json_decode(file_get_contents('php://input'), true);
$table_id = $input['table_id'] ?? null;
$product_id = $input['product_id'] ?? null;
$quantity = (int)($input['quantity'] ?? 1);

if (!$table_id || !$product_id || $quantity < 1) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing or invalid parameters']);
    exit;
}

try {
    $mysqli = get_db_conn();
    
    $stmt = $mysqli->prepare('SELECT id FROM `orders` WHERE table_id = ? AND status = "open" LIMIT 1');
    if (!$stmt) throw new Exception('Prepare failed: ' . $mysqli->error);
    
    $stmt->bind_param('i', $table_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $order_row = $res->fetch_assoc();
    $res->free();
    $stmt->close();
    
    $order_id = null;
    if ($order_row) {
        $order_id = (int)$order_row['id'];
    } else {
        $stmt = $mysqli->prepare('INSERT INTO `orders` (table_id, status) VALUES (?, "open")');
        if (!$stmt) throw new Exception('Prepare failed: ' . $mysqli->error);
        
        $stmt->bind_param('i', $table_id);
        $stmt->execute();
        $order_id = $mysqli->insert_id;
        $stmt->close();
    }
    
    $stmt = $mysqli->prepare('SELECT id, quantity FROM `order_items` WHERE order_id = ? AND product_id = ?');
    if (!$stmt) throw new Exception('Prepare failed: ' . $mysqli->error);
    
    $stmt->bind_param('ii', $order_id, $product_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $item_row = $res->fetch_assoc();
    $res->free();
    $stmt->close();
    
    if ($item_row) {
        $new_qty = (int)$item_row['quantity'] + $quantity;
        $stmt = $mysqli->prepare('UPDATE `order_items` SET quantity = ? WHERE id = ?');
        if (!$stmt) throw new Exception('Prepare failed: ' . $mysqli->error);
        
        $stmt->bind_param('ii', $new_qty, $item_row['id']);
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $mysqli->prepare('INSERT INTO `order_items` (order_id, product_id, quantity) VALUES (?, ?, ?)');
        if (!$stmt) throw new Exception('Prepare failed: ' . $mysqli->error);
        
        $stmt->bind_param('iii', $order_id, $product_id, $quantity);
        $stmt->execute();
        $stmt->close();
    }
    
    echo json_encode(['success' => true, 'order_id' => $order_id]);
} catch (Exception $ex) {
    error_log('add_order_item error: ' . $ex->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $ex->getMessage()]);
}

$mysqli->close();
?>
