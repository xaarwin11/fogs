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

$data = json_decode(file_get_contents('php://input'), true);
$table_id = intval($data['table_id'] ?? 0);
$items = $data['items'] ?? [];

if (!$table_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing table_id']);
    exit;
}

try {
    $mysqli = get_db_conn();
    
    error_log('save_pos_cart: table_id=' . $table_id . ', items count=' . count($items));
    
    $stmt = $mysqli->prepare('SELECT id FROM `orders` WHERE table_id = ? AND status != "paid" LIMIT 1');
    if (!$stmt) throw new Exception('Prepare failed: ' . $mysqli->error);
    
    $stmt->bind_param('i', $table_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $existing_order = $res->fetch_assoc();
    $res->free();
    $stmt->close();
    
    error_log('save_pos_cart: existing_order=' . ($existing_order ? $existing_order['id'] : 'none'));
    
    $order_id = null;
    if ($existing_order) {
        $order_id = (int)$existing_order['id'];
    } else {
        $status = 'open';
        $stmt = $mysqli->prepare('INSERT INTO `orders` (table_id, status) VALUES (?, ?)');
        if (!$stmt) throw new Exception('Prepare failed: ' . $mysqli->error);
        
        $stmt->bind_param('is', $table_id, $status);
        $stmt->execute();
        $order_id = $mysqli->insert_id;
        $stmt->close();
    }
    
    if (!$order_id) {
        throw new Exception('Failed to get or create order');
    }
    
     $existingMap = [];
    $res = $mysqli->query('SELECT product_id, quantity, served, price FROM order_items WHERE order_id = ' . intval($order_id));
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $pid = intval($r['product_id']);
           
            if (!isset($existingMap[$pid])) {
                $existingMap[$pid] = ['quantity' => 0, 'served' => 0, 'price' => null];
            }
            $existingMap[$pid]['quantity'] += intval($r['quantity']);
            $existingMap[$pid]['served'] += intval($r['served']);
            if ($r['price'] !== null) $existingMap[$pid]['price'] = (float)$r['price'];
        }
        $res->free();
    }

      $stmt = $mysqli->prepare('DELETE FROM `order_items` WHERE order_id = ?');
    if (!$stmt) throw new Exception('Prepare failed: ' . $mysqli->error);
    $stmt->bind_param('i', $order_id);
    $stmt->execute();
    $stmt->close();

        $productPrices = [];
    $productIds = array_values(array_unique(array_filter(array_map(function($it){ return intval($it['product_id'] ?? 0); }, $items))));
    if (count($productIds) > 0) {
        $in = implode(',', array_map('intval', $productIds));
        $res = $mysqli->query('SELECT id, price FROM products WHERE id IN (' . $in . ')');
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $productPrices[intval($r['id'])] = (float)$r['price'];
            }
            $res->free();
        }
    }

    
    $newQuantities = [];
    foreach ($items as $item) {
        $pid = intval($item['product_id'] ?? 0);
        $qty = intval($item['quantity'] ?? 0);
        if ($pid <= 0 || $qty <= 0) continue;
        if (!isset($newQuantities[$pid])) $newQuantities[$pid] = 0;
        $newQuantities[$pid] += $qty;
    }

        $ins = $mysqli->prepare('INSERT INTO `order_items` (order_id, product_id, quantity, served, price) VALUES (?, ?, ?, ?, ?)');
    if (!$ins) throw new Exception('Prepare failed: ' . $mysqli->error);
    foreach ($newQuantities as $product_id => $quantity) {
        $prevServed = isset($existingMap[$product_id]) ? intval($existingMap[$product_id]['served']) : 0;
        $servedVal = min($prevServed, $quantity);
        $priceVal = isset($productPrices[$product_id]) ? $productPrices[$product_id] : (isset($existingMap[$product_id]['price']) ? $existingMap[$product_id]['price'] : 0.0);
        $ins->bind_param('iiiid', $order_id, $product_id, $quantity, $servedVal, $priceVal);
        $ins->execute();
    }
    $ins->close();
    
    echo json_encode([
        'success' => true,
        'order_id' => $order_id,
        'table_id' => $table_id,
        'items_saved' => count($items)
    ]);
} catch (Exception $ex) {
    error_log('save_pos_cart error: ' . $ex->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $ex->getMessage()]);
}

$mysqli->close();
?>
