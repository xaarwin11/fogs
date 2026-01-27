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

// HARD WALL: If no items, kill the script immediately. 
// No Order ID will be created.
if (empty($items)) {
    echo json_encode(['success' => true, 'order_id' => 0, 'message' => 'No items to save']);
    exit;
}

// ... rest of your code (try/catch, etc)

try {
    $mysqli = get_db_conn();
    $mysqli->begin_transaction();

    // NEW: If there are no items and no existing order, just stop here successfully.
    if (empty($items)) {
        // If an order exists but we are clearing it (items is empty), 
        // we handle the deletion of unserved items.
        $stmt = $mysqli->prepare('SELECT id FROM `orders` WHERE table_id = ? AND status != "paid" LIMIT 1');
        $stmt->bind_param('i', $table_id);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($existing) {
            $order_id = $existing['id'];
            // Remove only items not yet served
            $mysqli->query("DELETE FROM `order_items` WHERE order_id = $order_id AND served = 0");
            
            // OPTIONAL: If order is now totally empty (no served items left), delete the order too
            $checkEmpty = $mysqli->query("SELECT COUNT(*) as count FROM order_items WHERE order_id = $order_id");
            if ($checkEmpty->fetch_assoc()['count'] == 0) {
                $mysqli->query("DELETE FROM `orders` WHERE id = $order_id");
                $order_id = null;
            }
        }
        
        $mysqli->commit();
        echo json_encode(['success' => true, 'order_id' => $order_id ?? 0]);
        exit;
    }

    // --- LOGIC FOR WHEN ITEMS EXIST ---

    // 1. Get or Create Order ONLY if we have items
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

    // 2. UPSERT Logic remains the same...
    // ... rest of your foreach loop ...

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
