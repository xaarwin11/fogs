<?php

require_once __DIR__ . '/../../db.php';
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}
$role = strtolower($_SESSION['role'] ?? '');
if (!in_array($role, ['staff','admin','manager','kitchen'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $orderId = isset($data['order_id']) ? (int)$data['order_id'] : 0;
    if (!$orderId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing order_id']);
        exit;
    }

    $mysqli = get_db_conn();
    $colRes = $mysqli->query("SHOW COLUMNS FROM `orders` LIKE 'hidden_in_kds'");
    if ($colRes && $colRes->num_rows > 0) {
        $stmt = $mysqli->prepare('UPDATE `orders` SET `hidden_in_kds` = 0 WHERE id = ? LIMIT 1');
        if (!$stmt) throw new Exception($mysqli->error);
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        $mysqli->close();
        echo json_encode(['success' => true, 'order_id' => $orderId, 'hidden_in_kds' => 0, 'affected' => $affected]);
        exit;
    }

    // Fallback: remove from JSON file
    $file = __DIR__ . '/../kds_hidden.json';
    if (!is_readable($file)) {
        echo json_encode(['success' => true, 'hidden' => []]);
        exit;
    }
    $json = @file_get_contents($file);
    $arr = json_decode($json, true);
    if (!is_array($arr)) $arr = [];
    $hidden = array_map('intval', $arr);
    $new = array_values(array_filter($hidden, function($v) use ($orderId) { return $v !== $orderId; }));
    @file_put_contents($file, json_encode($new));
    echo json_encode(['success' => true, 'hidden' => $new]);
} catch (Exception $ex) {
    error_log('unhide_kds_order error: ' . $ex->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}

?>
