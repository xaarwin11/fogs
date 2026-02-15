<?php
require_once __DIR__ . '/../../db.php';
session_start();
header('Content-Type: application/json');

// Only Admins or Managers should be allowed to void paid orders
if (!in_array(strtolower($_SESSION['role'] ?? ''), ['admin', 'manager'])) {
    echo json_encode(['success' => false, 'error' => 'Permission denied. Presidential or Managerial override required.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$order_id = (int)($input['order_id'] ?? 0);
$reason = trim($input['reason'] ?? '');

if ($order_id <= 0 || empty($reason)) {
    echo json_encode(['success' => false, 'error' => 'Order ID and reason are required.']);
    exit;
}

try {
    $mysqli = get_db_conn();
    $mysqli->begin_transaction();

    // 1. Check if the order is already voided
    $check = $mysqli->prepare("SELECT status FROM orders WHERE id = ?");
    $check->bind_param("i", $order_id);
    $check->execute();
    if ($check->get_result()->fetch_assoc()['status'] === 'voided') {
        throw new Exception("Order is already voided.");
    }

    // 2. Update Order Status
    $stmt = $mysqli->prepare("UPDATE orders SET status = 'voided', void_reason = ?, voided_by = ? WHERE id = ?");
    $userId = $_SESSION['user_id'];
    $stmt->bind_param("sii", $reason, $userId, $order_id);
    $stmt->execute();

    // 3. Optional: Mark payment as refunded in the payments table
    $stmt = $mysqli->prepare("UPDATE payments SET method = CONCAT(method, ' (VOIDED)') WHERE order_id = ?");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();

    $mysqli->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $mysqli->rollback();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}