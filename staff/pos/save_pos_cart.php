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
    $mysqli->begin_transaction();

    // 1. Get or Create Order
    $stmt = $mysqli->prepare('SELECT id FROM `orders` WHERE table_id = ? AND status != "paid" LIMIT 1');
    $stmt->bind_param('i', $table_id);
    $stmt->execute();
    $existing_order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $order_id = $existing_order ? (int)$existing_order['id'] : null;
    if (!$order_id) {
        $stmt = $mysqli->prepare('INSERT INTO `orders` (table_id, status) VALUES (?, "open")');
        $stmt->bind_param('i', $table_id);
        $stmt->execute();
        $order_id = $mysqli->insert_id;
        $stmt->close();
    }

    // 2. SMART SAVE: Use UPSERT
    // This updates the quantity if the item exists, but LEAVES the 'served' count alone.
    $upsertStmt = $mysqli->prepare("
        INSERT INTO `order_items` (order_id, product_id, quantity, price, served) 
        VALUES (?, ?, ?, (SELECT price FROM products WHERE id = ?), 0)
        ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)
    ");

    $active_pids = [];
    foreach ($items as $item) {
        $pid = intval($item['product_id']);
        $qty = intval($item['quantity']);
        if ($pid <= 0 || $qty <= 0) continue;

        // NEW: SAFETY CHECK
        $check = $mysqli->prepare("SELECT served, quantity FROM order_items WHERE order_id = ? AND product_id = ?");
        $check->bind_param('ii', $order_id, $pid);
        $check->execute();
        $res = $check->get_result()->fetch_assoc();
        $check->close();

        if ($res && $qty < (int)$res['served']) {
            // Throw an error if they try to save a quantity less than what was served
            throw new Exception("Cannot reduce quantity for product ID $pid. " . $res['served'] . " items already served.");
        }

        $active_pids[] = $pid;
        $upsertStmt->bind_param('iiii', $order_id, $pid, $qty, $pid);
        $upsertStmt->execute();
    }
    
    $upsertStmt->close();

    // 3. REMOVE items deleted from cart ONLY if served = 0
    if (!empty($active_pids)) {
        $pid_list = implode(',', $active_pids);
        $mysqli->query("DELETE FROM `order_items` 
                        WHERE order_id = $order_id 
                        AND product_id NOT IN ($pid_list) 
                        AND served = 0");
    }

    $mysqli->commit();
    echo json_encode(['success' => true, 'order_id' => $order_id]);

} catch (Exception $ex) {
    $mysqli->rollback();
    error_log('save_pos_cart error: ' . $ex->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $ex->getMessage()]);
}

$mysqli->close();
?>
