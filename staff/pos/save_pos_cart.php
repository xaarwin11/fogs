<?php
require_once __DIR__ . '/../../db.php';
session_start();
header('Content-Type: application/json');

// 1. Security Check
$role = strtolower($_SESSION['role'] ?? '');
if (!in_array($role, ['staff', 'admin', 'manager'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$table_id = $input['table_id'] ?? null;
$items = $input['items'] ?? []; 

if (!$table_id) {
    echo json_encode(['success' => false, 'error' => 'No table selected']);
    exit;
}

try {
    $mysqli = get_db_conn();
    $mysqli->begin_transaction();

    // 2. Find or create the open order
    $stmt = $mysqli->prepare("SELECT id FROM orders WHERE table_id = ? AND status = 'open' LIMIT 1");
    $stmt->bind_param('i', $table_id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $order_id = $order ? (int)$order['id'] : null;

    if (!$order_id) {
        $stmt = $mysqli->prepare("INSERT INTO orders (table_id, status, created_at) VALUES (?, 'open', NOW())");
        $stmt->bind_param('i', $table_id);
        $stmt->execute();
        $order_id = $mysqli->insert_id;
    }

    $processed_item_ids = [];
    $sync_map = [];

    // 3. Process each item in the cart
    foreach ($items as $item) {
        $db_item_id = !empty($item['order_item_id']) ? (int)$item['order_item_id'] : null;
        $u_key   = $item['unique_key'] ?? null;
        $prod_id = (int)$item['product_id'];
        $var_id  = !empty($item['variation_id']) ? (int)$item['variation_id'] : null;
        $qty     = (int)$item['quantity'];
        $notes   = $item['notes'] ?? '';
        
        // Base financial data
        $base_price = (float)($item['base_price'] ?? 0);
        $discount_amt = (float)($item['discount_amount'] ?? 0);

        // RE-CALCULATE MODIFIER TOTALS (Unit Price vs Line Total)
        $single_item_mod_sum = 0;
        if (!empty($item['modifiers'])) {
            foreach ($item['modifiers'] as $mod) {
                $single_item_mod_sum += (float)$mod['price'];
            }
        }

        // Logic: (Base + Unit Modifiers) * Quantity - Discount
        $modifier_total_for_line = $single_item_mod_sum * $qty;
        $line_total = (($base_price + $single_item_mod_sum) * $qty) - $discount_amt;

        if ($db_item_id) {
            // UPDATE EXISTING ITEM
            $stmt = $mysqli->prepare("UPDATE order_items SET 
                quantity = ?, base_price = ?, modifier_total = ?, 
                discount_amount = ?, line_total = ?, notes = ?, variation_id = ? 
                WHERE id = ? AND order_id = ?");
            $stmt->bind_param('idddssiii', $qty, $base_price, $modifier_total_for_line, $discount_amt, $line_total, $notes, $var_id, $db_item_id, $order_id);
            $stmt->execute();
            $processed_item_ids[] = $db_item_id;
        } else {
            // INSERT NEW ITEM
            $stmt = $mysqli->prepare("INSERT INTO order_items 
                (unique_key, order_id, product_id, variation_id, quantity, base_price, modifier_total, discount_amount, line_total, notes) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('siiiidddds', $u_key, $order_id, $prod_id, $var_id, $qty, $base_price, $modifier_total_for_line, $discount_amt, $line_total, $notes);
            $stmt->execute();
            $db_item_id = $mysqli->insert_id;
            $processed_item_ids[] = $db_item_id;
        }
        
        // Map the unique_key to the database ID so JS can stay synced
        if ($u_key) $sync_map[$u_key] = $db_item_id;

        // 4. REFRESH MODIFIERS (The unit-price storage)
        $m_del = $mysqli->prepare("DELETE FROM order_item_modifiers WHERE order_item_id = ?");
        $m_del->bind_param('i', $db_item_id);
        $m_del->execute();

        if (!empty($item['modifiers'])) {
            $mi_stmt = $mysqli->prepare("INSERT INTO order_item_modifiers (order_item_id, modifier_id, name, price) VALUES (?, ?, ?, ?)");
            foreach ($item['modifiers'] as $mod) {
                $mod_id = (int)$mod['id'];
                $mod_name = $mod['name'];
                $mod_price = (float)$mod['price'];
                $mi_stmt->bind_param('iisd', $db_item_id, $mod_id, $mod_name, $mod_price);
                $mi_stmt->execute();
            }
        }
    }

    // 5. DELETE removed items (Items that were in the DB but are no longer in the JS cart)
    if (!empty($processed_item_ids)) {
        $placeholders = implode(',', array_fill(0, count($processed_item_ids), '?'));
        $del_sql = "DELETE FROM order_items WHERE order_id = ? AND served = 0 AND id NOT IN ($placeholders)";
        $stmt = $mysqli->prepare($del_sql);
        $stmt->bind_param('i' . str_repeat('i', count($processed_item_ids)), $order_id, ...$processed_item_ids);
        $stmt->execute();
    } else {
        // If the cart sent is empty, delete all unserved items
        $mysqli->query("DELETE FROM order_items WHERE order_id = $order_id AND served = 0");
    }

    // 6. UPDATE MAIN ORDER TOTALS
    // We sum the line_totals (which already have discounts subtracted)
    $stmt = $mysqli->prepare("UPDATE orders SET 
        subtotal = (SELECT COALESCE(SUM(line_total + discount_amount), 0) FROM order_items WHERE order_id = ?),
        grand_total = (SELECT COALESCE(SUM(line_total), 0) FROM order_items WHERE order_id = ?),
        updated_at = NOW()
        WHERE id = ?");
    $stmt->bind_param('iii', $order_id, $order_id, $order_id);
    $stmt->execute();

    $mysqli->commit();
    echo json_encode([
        'success' => true, 
        'order_id' => $order_id, 
        'sync_map' => $sync_map,
        'message' => 'Order synchronized successfully'
    ]);

} catch (Exception $e) {
    if (isset($mysqli)) $mysqli->rollback();
    error_log("Save Order Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}