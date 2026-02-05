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
    // Instead of just picking any active printer, we check the "Route" setting
    $setting_key = ($type === 'kitchen') ? 'route_kitchen' : 'route_receipt';

// Step 1: Define the query string
$p_query_string = "
    SELECT p.* FROM printers p 
    JOIN system_settings s ON p.id = s.setting_value 
    WHERE s.setting_key = ? AND p.is_active = 1 
    LIMIT 1
";

// Step 2: Prepare it ONCE
$p_stmt = $mysqli->prepare($p_query_string); 

// Step 3: Bind and Execute
$p_stmt->bind_param("s", $setting_key);
$p_stmt->execute();
$p_conf = $p_stmt->get_result()->fetch_assoc();

    // Fallback logic if routing isn't set up yet
    if (!$p_conf) {
        $backup = $mysqli->query("SELECT * FROM printers WHERE is_active = 1 LIMIT 1");
        $p_conf = $backup->fetch_assoc();
    }

    if (!$p_conf) throw new Exception("No active printer found for $type");

    // --- STEP B: FETCH ORDER ITEMS ---
    $stmt = $mysqli->prepare("
        SELECT oi.quantity, p.name, oi.price
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
    ");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    if (!$rows) throw new Exception("No items found");

    $items = [];
    foreach ($rows as $r) {
        $items[] = [
            'quantity' => (int)$r['quantity'],
            'name'     => $r['name'],
            'price'    => (float)$r['price']
        ];
    }

    // --- STEP C: INITIALIZE SERVICE ---
    // Passing connection_type, path, and the character_limit
    $printer = new PrinterService($p_conf['connection_type'], $p_conf['path'], $p_conf['character_limit']);

    if (!$printer->isValid()) throw new Exception("Printer " . $p_conf['path'] . " not reachable");

    // --- STEP D: HARDWARE OPTIONS (BEEP & CUT) ---
    $options = [
        'beep' => (bool)$p_conf['beep_on_print'],
        'cut'  => (bool)$p_conf['cut_after_print']
    ];

    // Fetch Table Number for the receipt meta
    $t_stmt = $mysqli->prepare("SELECT t.table_number FROM orders o JOIN tables t ON o.table_id = t.id WHERE o.id = ?");
    $t_stmt->bind_param("i", $order_id);
    $t_stmt->execute();
    $t_res = $t_stmt->get_result()->fetch_assoc();

    $meta = [
        'TABLE' => $t_res['table_number'] ?? '-',
        'STAFF' => $_SESSION['first_name'] ?? 'Staff',
        'TIME'  => date('M d, Y h:i A')
    ];

    // --- STEP E: EXECUTE PRINT ---
    if ($type === 'kitchen') {
        // Kitchen: Large text, no prices, uses hardware options
        $printer->printTicket("KITCHEN ORDER", $items, $meta, false, $options);
    } else {
        // Bill: Normal text, show prices, uses hardware options
        $printer->printTicket("BILL STATEMENT", $items, $meta, true, $options);
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}