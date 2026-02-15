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
$order_discount = isset($input['order_discount']) ? (float)$input['order_discount'] : 0;
$order_discount_note = isset($input['order_discount_note']) ? trim($input['order_discount_note']) : '';

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
        $var_id  = !empty($item['variation_id']) ? (int)$item['variation_id'] : (!empty($item['size_id']) ? (int)$item['size_id'] : null);
        $qty     = (int)$item['quantity'];
        
        // MAPPING: Take 'notes' from JS and treat it as 'discount_note' for the DB
        $final_note = !empty($item['notes']) ? trim($item['notes']) : ($item['discount_note'] ?? '');
        
        $base_price = (float)($item['base_price'] ?? 0);
        $discount_amt = (float)($item['discount_amount'] ?? 0);

        // Calculate Modifier Total
        $single_item_mod_sum = 0;
        if (!empty($item['modifiers'])) {
            foreach ($item['modifiers'] as $mod) {
                $single_item_mod_sum += (float)$mod['price'];
            }
        }
        $modifier_total_for_line = $single_item_mod_sum * $qty;
        $line_total = (($base_price + $single_item_mod_sum) * $qty) - $discount_amt;

        if ($db_item_id) {
            // FIX: Removed 'notes' column, now binding $final_note to 'discount_note'
            $stmt = $mysqli->prepare("UPDATE order_items SET 
                quantity = ?, base_price = ?, modifier_total = ?, 
                discount_amount = ?, discount_note = ?, line_total = ?, variation_id = ? 
                WHERE id = ? AND order_id = ?");
            // types: idddsdiii (9 params)
            $stmt->bind_param('idddsdiii', $qty, $base_price, $modifier_total_for_line, $discount_amt, $final_note, $line_total, $var_id, $db_item_id, $order_id);
            $stmt->execute();
            $processed_item_ids[] = $db_item_id;
        } else {
            // FIX: Removed 'notes' column, kept 'discount_note'
            $stmt = $mysqli->prepare("INSERT INTO order_items 
                (unique_key, order_id, product_id, variation_id, quantity, base_price, modifier_total, discount_amount, discount_note, line_total) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            // types: siiiidddsd (10 params)
            $stmt->bind_param('siiiidddsd', $u_key, $order_id, $prod_id, $var_id, $qty, $base_price, $modifier_total_for_line, $discount_amt, $final_note, $line_total);
            $stmt->execute();
            $db_item_id = $mysqli->insert_id;
            $processed_item_ids[] = $db_item_id;
        }
        
        if ($u_key) $sync_map[$u_key] = $db_item_id;

        // Refresh Modifiers
        $m_del = $mysqli->prepare("DELETE FROM order_item_modifiers WHERE order_item_id = ?");
        $m_del->bind_param('i', $db_item_id);
        $m_del->execute();

        if (!empty($item['modifiers'])) {
            $mi_stmt = $mysqli->prepare("INSERT INTO order_item_modifiers (order_item_id, modifier_id, name, price) VALUES (?, ?, ?, ?)");
            foreach ($item['modifiers'] as $mod) {
                $mi_stmt->bind_param('iisd', $db_item_id, $mod['id'], $mod['name'], $mod['price']);
                $mi_stmt->execute();
            }
        }
    }

    // 5. DELETE removed items
    // Only delete items that are NOT in the processed list AND have not been served yet.
    if (!empty($processed_item_ids)) {
        // Create a string of "?,?,?" based on count
        $placeholders = implode(',', array_fill(0, count($processed_item_ids), '?'));
        
        $del_sql = "DELETE FROM order_items WHERE order_id = ? AND served = 0 AND id NOT IN ($placeholders)";
        
        $stmt = $mysqli->prepare($del_sql);
        // Correctly unpack the array for binding
        $types = 'i' . str_repeat('i', count($processed_item_ids));
        $stmt->bind_param($types, $order_id, ...$processed_item_ids);
        $stmt->execute();
    } else {
        // If cart is empty, delete all unserved items for this order
        $del_sql = "DELETE FROM order_items WHERE order_id = ? AND served = 0";
        $stmt = $mysqli->prepare($del_sql);
        $stmt->bind_param('i', $order_id);
        $stmt->execute();
    }

    // 6. UPDATE MAIN ORDER TOTALS
    $stmt = $mysqli->prepare("UPDATE orders SET 
        subtotal = (SELECT COALESCE(SUM(line_total + discount_amount), 0) FROM order_items WHERE order_id = ?),
        discount_total = ?,
        discount_note = ?,
        grand_total = ((SELECT COALESCE(SUM(line_total), 0) FROM order_items WHERE order_id = ?) - ?),
        updated_at = NOW()
        WHERE id = ?");
    
    $stmt->bind_param('idsidi', $order_id, $order_discount, $order_discount_note, $order_id, $order_discount, $order_id);
    $stmt->execute();

    $mysqli->commit();
    echo json_encode(['success' => true, 'order_id' => $order_id, 'sync_map' => $sync_map]);

} catch (Exception $e) {
    if (isset($mysqli)) $mysqli->rollback();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>