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
$order_id = isset($input['order_id']) ? (int)$input['order_id'] : null;
$payment_method = isset($input['payment_method']) ? trim($input['payment_method']) : null;
$amount_paid = isset($input['amount_paid']) ? (float)$input['amount_paid'] : null;

if (!$order_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing order_id']);
    exit;
}

try {
    $mysqli = get_db_conn();
    
    // Get table_id from order
    $stmt = $mysqli->prepare('SELECT table_id FROM `orders` WHERE id = ?');
    if (!$stmt) throw new Exception('Prepare failed: ' . $mysqli->error);
    
    $stmt->bind_param('i', $order_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $order = $res->fetch_assoc();
    $res->free();
    $stmt->close();
    
    if (!$order) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Order not found']);
        exit;
    }
    
    $table_id = (int)$order['table_id'];
    // Compute order total on server to avoid client tampering
    // Use order_items.price when present, otherwise fall back to products.price
    $stmt = $mysqli->prepare('SELECT COALESCE(SUM((CASE WHEN oi.price IS NOT NULL THEN oi.price ELSE p.price END) * oi.quantity),0) as total FROM order_items oi LEFT JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ?');
    if (!$stmt) throw new Exception('Prepare failed: ' . $mysqli->error);
    $stmt->bind_param('i', $order_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();
    $total = isset($row['total']) ? (float)$row['total'] : 0.0;

    // Validate payment amount if provided
    if ($amount_paid === null) $amount_paid = $total;
    if ($amount_paid < $total - 0.001) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Insufficient payment amount', 'total' => $total]);
        exit;
    }

    // Generate month-based reference: RYYYYMM-XXXX (sequence per month)
    $month = date('Ym');
    $like = $mysqli->real_escape_string('R' . $month . '-%');
    $countRes = $mysqli->query("SELECT COUNT(*) as c FROM `orders` WHERE `reference` LIKE '{$like}'");
    $seq = 1;
    if ($countRes) {
        $crow = $countRes->fetch_assoc();
        $seq = isset($crow['c']) ? ((int)$crow['c'] + 1) : 1;
    }
    $reference = 'R' . $month . '-' . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);

    // Record payment in payments table (if present) and update order summary
    $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    $change = round($amount_paid - $total, 2);

    $paymentsTableRes = $mysqli->query("SHOW TABLES LIKE 'payments'");
    $usePayments = ($paymentsTableRes && $paymentsTableRes->num_rows > 0);

    $mysqli->begin_transaction();
    try {
        if ($usePayments) {
            $ins = $mysqli->prepare('INSERT INTO `payments` (`order_id`, `amount`, `method`, `change_given`, `processed_by`, `created_at`) VALUES (?, ?, ?, ?, ?, NOW())');
            if ($ins) {
                $ins->bind_param('idssi', $order_id, $amount_paid, $payment_method, $change, $userId);
                $ins->execute();
                $ins->close();
            }

            // Update orders summary (no granular payment fields stored here)
            $up = $mysqli->prepare('UPDATE `orders` SET `status` = "paid", `reference` = ?, `paid_at` = NOW(), `checked_out_by` = ? WHERE id = ?');
            if ($up) {
                $up->bind_param('sii', $reference, $userId, $order_id);
                $up->execute();
                $up->close();
            }
        } else {
            // No payments table — keep previous behaviour but avoid storing unnecessary details if possible
            $up = $mysqli->prepare('UPDATE `orders` SET `status` = "paid", `reference` = ?, `paid_at` = NOW(), `checked_out_by` = ? WHERE id = ?');
            if ($up) {
                $up->bind_param('sii', $reference, $userId, $order_id);
                $up->execute();
                $up->close();
            }
        }

        // Mark table as available
        $stmt = $mysqli->prepare('UPDATE `tables` SET occupied = 0 WHERE id = ?');
        if (!$stmt) throw new Exception('Prepare failed: ' . $mysqli->error);
        $stmt->bind_param('i', $table_id);
        $stmt->execute();
        $stmt->close();

        $mysqli->commit();
    } catch (Exception $e) {
        $mysqli->rollback();
        throw $e;
    }

    // Save the user who checked out (if column exists)
    $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    $colChecked = $mysqli->query("SHOW COLUMNS FROM `orders` LIKE 'checked_out_by'");
    if ($colChecked && $colChecked->num_rows > 0 && $userId) {
        $up2 = $mysqli->prepare('UPDATE `orders` SET `checked_out_by` = ? WHERE id = ?');
        if ($up2) {
            $up2->bind_param('ii', $userId, $order_id);
            $up2->execute();
            $up2->close();
        }
    }
    
    // Set table as available after checkout
    $stmt = $mysqli->prepare('UPDATE `tables` SET occupied = 0 WHERE id = ?');
    if (!$stmt) throw new Exception('Prepare failed: ' . $mysqli->error);
    
    $stmt->bind_param('i', $table_id);
    $stmt->execute();
    $stmt->close();
    
    echo json_encode(['success' => true, 'table_id' => $table_id, 'reference' => $reference]);
} catch (Exception $ex) {
    error_log('checkout_order error: ' . $ex->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $ex->getMessage()]);
}

$mysqli->close();
?>
