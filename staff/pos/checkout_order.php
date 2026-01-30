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

    /* 1️⃣ Get order + table */
    $stmt = $mysqli->prepare(
        'SELECT table_id FROM orders WHERE id = ? AND status = "open"'
    );
    if (!$stmt) throw new Exception($mysqli->error);

    $stmt->bind_param('i', $order_id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$order) {
        throw new Exception('Order not found or already paid');
    }

    $table_id = (int)$order['table_id'];

    /* 2️⃣ Compute total */
    $stmt = $mysqli->prepare(
        'SELECT COALESCE(SUM(price * quantity),0) AS total 
         FROM order_items WHERE order_id = ?'
    );
    if (!$stmt) throw new Exception($mysqli->error);

    $stmt->bind_param('i', $order_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $total = (float)$row['total'];

    if ($total <= 0) {
        throw new Exception('Cannot checkout empty order');
    }

    if ($amount_paid === null) $amount_paid = $total;
    
    // Allow small float margin error
    if ($amount_paid + 0.001 < $total) {
        throw new Exception('Insufficient payment');
    }

    /* 3️⃣ Generate reference (YYMMXXXX) */
    // 'y' = 2 digit year, 'm' = 2 digit month. Example: 2601
    $prefix = date('ym'); 
    
    // Count existing orders with this specific YYMM prefix
    $res = $mysqli->query(
        "SELECT COUNT(*) c FROM orders WHERE reference LIKE '{$prefix}%'"
    );
    
    if (!$res) throw new Exception($mysqli->error);
    
    $seq = ((int)$res->fetch_assoc()['c']) + 1;
    
    // Construct: Prefix (YYMM) + Sequence padded to 4 digits (XXXX)
    // Result example: 26010001
    $reference = $prefix . str_pad($seq, 3, '0', STR_PAD_LEFT);

    $userId = (int)($_SESSION['user_id'] ?? 0);
    $change = round($amount_paid - $total, 2);

    /* 4️⃣ TRANSACTION */
    $mysqli->begin_transaction();

    // Payment
    $stmt = $mysqli->prepare(
        'INSERT INTO payments 
         (order_id, amount, method, change_given, processed_by) 
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('idssi', 
        $order_id, $amount_paid, $payment_method, $change, $userId
    );
    $stmt->execute();
    $stmt->close();

    // Order paid
    $stmt = $mysqli->prepare(
        'UPDATE orders 
         SET status = "paid", 
             reference = ?, 
             paid_at = NOW(), 
             checked_out_by = ? 
         WHERE id = ?'
    );
    $stmt->bind_param('sii', $reference, $userId, $order_id);
    $stmt->execute();
    $stmt->close();

    // Table available again
    if ($table_id > 0) {
        $stmt = $mysqli->prepare(
            'UPDATE tables SET status = "available" WHERE id = ?'
        );
        $stmt->bind_param('i', $table_id);
        $stmt->execute();
        $stmt->close();
    }

    $mysqli->commit();

    echo json_encode([
        'success' => true,
        'reference' => $reference,
        'change' => $change
    ]);

} catch (Exception $e) {
    if (isset($mysqli) && $mysqli->errno === 0) {
        $mysqli->rollback();
    }

    error_log('checkout_order error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

if (isset($mysqli)) {
    $mysqli->close();
}