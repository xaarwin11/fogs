<?php

require_once __DIR__ . '/../../db.php';
session_start();
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}
$role = strtolower($_SESSION['role'] ?? '');
if (!in_array($role, ['staff','admin','manager','kitchen'])) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

$response = ['success' => false, 'orders' => []];
try {
    $mysqli = get_db_conn();

    // Fetch open orders and associated aggregated items, including served flag
    // We'll fetch order-level and item-level details and structure them in PHP
    // Prefer DB-backed hidden flag if available; otherwise use legacy JSON file
    $useDbHidden = false;
    $colRes = $mysqli->query("SHOW COLUMNS FROM `orders` LIKE 'hidden_in_kds'");
    if ($colRes && $colRes->num_rows > 0) {
        $useDbHidden = true;
    }

    // Determine whether the caller wants hidden orders or active ones
    $wantHidden = isset($_GET['hidden']) && ($_GET['hidden'] === '1' || $_GET['hidden'] === 'true');

    // KDS should show orders that still have pending KDS items (quantity > served).
    // Include paid orders as well (customer may pay first). Orders are
    // considered complete for KDS only when all KDS items are served.
    $baseCondition = 'p.kds = 1 AND (oi.quantity - oi.served) > 0';

    // Read legacy JSON hidden list if needed
    $hiddenOrders = [];
    $hiddenFile = __DIR__ . '/../kds_hidden.json';
    if (!$useDbHidden && is_readable($hiddenFile)) {
        $json = @file_get_contents($hiddenFile);
        $arr = json_decode($json, true);
        if (is_array($arr)) $hiddenOrders = array_map('intval', $arr);
    }

    // Only include items that are flagged for KDS and not yet served
    // Return quantity and served amounts so KDS can show pending = quantity - served
    $sql = 'SELECT o.id as order_id, o.table_id, o.created_at, o.updated_at, p.id as product_id, p.name as product_name, oi.quantity, oi.id as order_item_id, oi.served FROM orders o JOIN order_items oi ON oi.order_id = o.id JOIN products p ON p.id = oi.product_id WHERE ' . $baseCondition;

    if ($wantHidden) {
        // Return only hidden orders
        if ($useDbHidden) {
            $sql .= ' AND o.hidden_in_kds = 1';
        } else {
            if (empty($hiddenOrders)) {
                // Nothing hidden, return empty result early
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'orders' => []]);
                exit;
            }
            $placeholders = implode(',', array_fill(0, count($hiddenOrders), '?'));
            $sql .= ' AND o.id IN (' . $placeholders . ')';
        }
    } else {
        // Active view: exclude hidden orders when DB flag exists or via JSON
        if ($useDbHidden) {
            $sql .= ' AND (o.hidden_in_kds IS NULL OR o.hidden_in_kds = 0)';
        } elseif (!empty($hiddenOrders)) {
            $placeholders = implode(',', array_fill(0, count($hiddenOrders), '?'));
            $sql .= ' AND o.id NOT IN (' . $placeholders . ')';
        }
    }

    $sql .= ' ORDER BY o.table_id, o.id';

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) throw new Exception($mysqli->error);

    if (!$useDbHidden && !empty($hiddenOrders)) {
        $types = str_repeat('i', count($hiddenOrders));
        $bindNames = [];
        $bindNames[] = & $types;
        for ($i = 0; $i < count($hiddenOrders); $i++) {
            $bindNames[] = & $hiddenOrders[$i];
        }
        call_user_func_array([$stmt, 'bind_param'], $bindNames);
    }
    $stmt->execute();
    $res = $stmt->get_result();

    $orders = [];
    while ($row = $res->fetch_assoc()) {
        $oid = (int)$row['order_id'];
        if (!isset($orders[$oid])) {
            $orders[$oid] = ['order_id' => $oid, 'table_id' => (int)$row['table_id'], 'created_at' => $row['created_at'], 'updated_at' => $row['updated_at'] ?? null, 'items' => []];
        }
        $orders[$oid]['items'][] = ['order_item_id' => (int)$row['order_item_id'], 'product_id' => (int)$row['product_id'], 'name' => $row['product_name'], 'quantity' => (int)$row['quantity'], 'served' => (int)$row['served']];
    }
    $stmt->close();

    $response['orders'] = array_values($orders);
    $response['success'] = true;
    $mysqli->close();
} catch (Exception $ex) {
    error_log('get_kitchen_orders error: ' . $ex->getMessage());
    $response['success'] = false;
    $response['error'] = 'Database error';
}

header('Content-Type: application/json');
echo json_encode($response);
?>
