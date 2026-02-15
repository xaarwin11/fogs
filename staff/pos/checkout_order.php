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

$input = json_decode(file_get_contents('php://input'), true);
$order_id = isset($input['order_id']) ? (int)$input['order_id'] : 0;
$payment_method = trim($input['payment_method'] ?? '');
$amount_paid = isset($input['amount_paid']) ? (float)$input['amount_paid'] : null;

if ($order_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing order_id']);
    exit;
}

try {
    $mysqli = get_db_conn();

    // 1. Get order details, including the GRAND TOTAL which includes global discounts
    $stmt = $mysqli->prepare('SELECT table_id, grand_total FROM orders WHERE id = ? AND status = "open"');
    $stmt->bind_param('i', $order_id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$order) {
        throw new Exception('Order not found or already paid');
    }

    $table_id = (int)$order['table_id'];
    $total = (float)$order['grand_total']; // USE THE SAVED TOTAL

    if ($total <= 0) {
        // Fallback safety: Check if it's truly empty or just 100% discounted
        // If 100% discounted, total is 0, which is allowed.
        // We verify if there are items.
        $check = $mysqli->query("SELECT COUNT(*) c FROM order_items WHERE order_id = $order_id");
        if($check->fetch_assoc()['c'] == 0) throw new Exception('Cannot checkout empty order');
    }

    if ($amount_paid === null) $amount_paid = $total;
    
    if ($amount_paid + 0.001 < $total) {
        throw new Exception('Insufficient payment');
    }

    $prefix = date('ym'); 
    $res = $mysqli->query("SELECT COUNT(*) c FROM orders WHERE reference LIKE '{$prefix}%'");
    $seq = ((int)$res->fetch_assoc()['c']) + 1;
    $reference = $prefix . str_pad($seq, 3, '0', STR_PAD_LEFT);

    $userId = (int)($_SESSION['user_id'] ?? 0);
    $change = round($amount_paid - $total, 2);

    $mysqli->begin_transaction();

    $stmt = $mysqli->prepare('INSERT INTO payments (order_id, amount, method, change_given, processed_by) VALUES (?, ?, ?, ?, ?)');
    $stmt->bind_param('idssi', $order_id, $amount_paid, $payment_method, $change, $userId);
    $stmt->execute();

    $stmt = $mysqli->prepare('UPDATE orders SET status = "paid", reference = ?, paid_at = NOW(), checked_out_by = ? WHERE id = ?');
    $stmt->bind_param('sii', $reference, $userId, $order_id);
    $stmt->execute();

    if ($table_id > 0) {
        $stmt = $mysqli->prepare('UPDATE tables SET status = "available" WHERE id = ?');
        $stmt->bind_param('i', $table_id);
        $stmt->execute();
    }

    $mysqli->commit();

    echo json_encode(['success' => true, 'reference' => $reference, 'change' => $change]);

} catch (Exception $e) {
    if (isset($mysqli)) $mysqli->rollback();
    error_log('checkout_order error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>