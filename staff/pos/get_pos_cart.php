<?php
require_once __DIR__ . '/../../db.php';
session_start();
header('Content-Type: application/json');

$table_id = $_GET['table_id'] ?? null;

if (!$table_id) {
    echo json_encode(['success' => false, 'error' => 'No table selected']);
    exit;
}

try {
    $mysqli = get_db_conn();
    
    // 1. Find the open order
    $stmt = $mysqli->prepare("SELECT id FROM orders WHERE table_id = ? AND status = 'open' LIMIT 1");
    $stmt->bind_param('i', $table_id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    
    if (!$order) {
        echo json_encode(['success' => true, 'items' => [], 'order_id' => null]);
        exit;
    }

    $order_id = $order['id'];

    // 2. Fetch items with Variation and Financial details
    $sql = "SELECT 
                oi.id as order_item_id,
                oi.product_id,
                oi.variation_id,
                oi.quantity,
                oi.base_price,
                oi.modifier_total,
                oi.discount_amount,
                oi.notes,
                oi.served,
                p.name as product_name,
                pv.name as variation_name
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            LEFT JOIN product_variations pv ON oi.variation_id = pv.id
            WHERE oi.order_id = ?";
            
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('i', $order_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $items = [];
    while ($row = $result->fetch_assoc()) {
        $order_item_id = (int)$row['order_item_id'];
        
        // 3. PRO LOGIC: Fetch Modifiers for this specific order item
        $mods = [];
        $mod_ids = [];
        $m_stmt = $mysqli->prepare("SELECT modifier_id, name, price FROM order_item_modifiers WHERE order_item_id = ?");
        $m_stmt->bind_param('i', $order_item_id);
        $m_stmt->execute();
        $m_res = $m_stmt->get_result();
        while($m_row = $m_res->fetch_assoc()){
            $mods[] = [
                'id' => $m_row['modifier_id'],
                'name' => $m_row['name'],
                'price' => (float)$m_row['price']
            ];
            $mod_ids[] = $m_row['modifier_id'];
        }
        $m_stmt->close();

        // 4. GENERATE THE PRO KEY (Must match JS: p[ID]_v[VAR]_m[MODS])
        sort($mod_ids);
        $mod_string = count($mod_ids) > 0 ? implode('-', $mod_ids) : '0';
        $v_id = $row['variation_id'] ?? 0;
        $u_key = "p" . $row['product_id'] . "_v" . $v_id . "_m" . $mod_string;

        $items[] = [
            'order_item_id' => $order_item_id,
            'unique_key'    => $u_key,
            'product_id'    => (int)$row['product_id'],
            'size_id'       => $row['variation_id'],
            'name'          => $row['product_name'],
            'variation_name'=> $row['variation_name'],
            'quantity'      => (int)$row['quantity'],
            'base_price'    => (float)$row['base_price'],
            'modifier_total'=> (float)$row['modifier_total'],
            'discount_amount' => (float)$row['discount_amount'],
            'modifiers'     => $mods,
            'notes'         => $row['notes'],
            'served'        => (int)$row['served']
        ];
    }

    echo json_encode(['success' => true, 'order_id' => $order_id, 'items' => $items]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>