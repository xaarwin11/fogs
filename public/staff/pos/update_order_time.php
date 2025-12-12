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
$order_id = isset($data['order_id']) ? (int)$data['order_id'] : 0;
if (!$order_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing order_id']);
    exit;
}

try {
    $mysqli = get_db_conn();
    $stmt = $mysqli->prepare('UPDATE orders SET updated_at = NOW() WHERE id = ?');
    if (!$stmt) throw new Exception('Prepare failed: ' . $mysqli->error);
    $stmt->bind_param('i', $order_id);
    $stmt->execute();
    $stmt->close();
    $mysqli->close();
    echo json_encode(['success' => true]);
} catch (Exception $ex) {
    error_log('update_order_time error: ' . $ex->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $ex->getMessage()]);
}
