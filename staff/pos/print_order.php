<?php
require __DIR__ . '/../../library/printerService.php';
require __DIR__ . '/../../db.php';
session_start();

header('Content-Type: application/json');

try {
    $mysqli = get_db_conn();

    $order_id = $_GET['order_id'] ?? null;
    $type     = $_GET['type'] ?? 'bill'; 

    if (!$order_id) throw new Exception("Missing order ID");

    // --- STEP A: FETCH PRINTER CONFIG BASED ON ROUTING ---
    $setting_key = ($type === 'kitchen') ? 'route_kitchen' : 'route_receipt';

    $p_query_string = "
        SELECT p.* FROM printers p 
        JOIN system_settings s ON p.id = s.setting_value 
        WHERE s.setting_key = ? AND p.is_active = 1 
        LIMIT 1
    ";

    $p_stmt = $mysqli->prepare($p_query_string); 
    $p_stmt->bind_param("s", $setting_key);
    $p_stmt->execute();
    $p_conf = $p_stmt->get_result()->fetch_assoc();

    if (!$p_conf) {
        $backup = $mysqli->query("SELECT * FROM printers WHERE is_active = 1 LIMIT 1");
        $p_conf = $backup->fetch_assoc();
    }

    if (!$p_conf) throw new Exception("No active printer found for $type");

    // --- STEP B: FETCH ORDER ITEMS ---
    if ($type === 'kitchen') {
        // KITCHEN: Only fetch items that haven't been printed yet
        $stmt = $mysqli->prepare("
            SELECT (oi.quantity - oi.kitchen_printed) as quantity, p.name, oi.price
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ? AND (oi.quantity - oi.kitchen_printed) > 0
        ");
    } else {
        // BILL: Fetch everything
        $stmt = $mysqli->prepare("
            SELECT oi.quantity, p.name, oi.price
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ?
        ");
    }
    
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // If kitchen call but no new items to cook, exit gracefully
    if ($type === 'kitchen' && empty($rows)) {
        echo json_encode(['success' => true, 'message' => 'No new items for kitchen']);
        exit;
    }
    
    if (empty($rows)) throw new Exception("No items found");

    $items = [];
    foreach ($rows as $r) {
        $items[] = [
            'quantity' => (int)$r['quantity'],
            'name'     => $r['name'],
            'price'    => (float)$r['price']
        ];
    }

    // --- STEP C: INITIALIZE SERVICE ---
    $printer = new PrinterService($p_conf['connection_type'], $p_conf['path'], (int)$p_conf['character_limit']);

    if (!$printer->isValid()) throw new Exception("Printer " . $p_conf['path'] . " not reachable");

    // --- STEP D: DATA PREP (HARDWARE & META) ---
    // --- STEP D: DATA PREP (HARDWARE & META) ---
    $options = [
        // Convert to int first so "0" becomes 0 (false) and "1" becomes 1 (true)
        'beep' => (int)$p_conf['beep_on_print'] === 1,
        'cut'  => (int)$p_conf['cut_after_print'] === 1
    ];

    $business_query = $mysqli->query("SELECT setting_key, setting_value FROM system_settings WHERE category = 'business'");
    $biz = [];
    while ($row = $business_query->fetch_assoc()) {
        $biz[$row['setting_key']] = $row['setting_value'];
    }

    $t_stmt = $mysqli->prepare("SELECT t.table_number FROM orders o JOIN tables t ON o.table_id = t.id WHERE o.id = ?");
    $t_stmt->bind_param("i", $order_id);
    $t_stmt->execute();
    $t_res = $t_stmt->get_result()->fetch_assoc();

    $meta = [
        'Store'   => $biz['store_name'] ?? 'FOGS RESTAURANT',
        'Address' => $biz['store_address'] ?? '',
        'Phone'   => $biz['store_phone'] ?? '',
        'Table'   => $t_res['table_number'] ?? '-',
        'Staff'   => $_SESSION['username'] ?? 'Staff',
        'Date'    => date('M d, Y h:i A')
    ];

    // --- STEP E: EXECUTE PRINT & DB UPDATE ---
    if ($type === 'kitchen') {
        $printer->printTicket("KITCHEN ORDER", $items, $meta, false, $options);
        
        // 🟢 ONLY update DB if printing didn't crash
        $update_stmt = $mysqli->prepare("UPDATE order_items SET kitchen_printed = quantity WHERE order_id = ?");
        $update_stmt->bind_param("i", $order_id);
        $update_stmt->execute();
    } else {
        $printer->printTicket("BILL STATEMENT", $items, $meta, true, $options);
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}