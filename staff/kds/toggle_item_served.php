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
$response = ['success' => false];
try {
    $mysqli = get_db_conn();
    $data = json_decode(file_get_contents('php://input'), true);
    $orderItemId = isset($data['order_item_id']) ? (int)$data['order_item_id'] : 0;
    if (!$orderItemId) throw new Exception('Missing order_item_id');
    $explicit = null;
    if (isset($data['set'])) {
        if (is_numeric($data['set'])) {
            $explicit = (int)$data['set'];
        } else {
            $explicit = ($data['set'] ? 1 : 0);
        }
    }

    $stmt = $mysqli->prepare('SELECT quantity, served FROM order_items WHERE id = ? LIMIT 1');
    if (!$stmt) throw new Exception($mysqli->error);
    $stmt->bind_param('i', $orderItemId);
    $stmt->execute();
    $stmt->bind_result($curQty, $curServed);
    $stmt->fetch();
    $stmt->close();

    if ($explicit === null) {
        if ((int)$curServed >= (int)$curQty) {
            $newServed = 0;
        } else {
            $newServed = (int)$curQty;
        }
        $stmt = $mysqli->prepare('UPDATE order_items SET served = ? WHERE id = ? LIMIT 1');
        if (!$stmt) throw new Exception($mysqli->error);
        $stmt->bind_param('ii', $newServed, $orderItemId);
        $stmt->execute();
        $stmt->close();
    } else {
        if (!is_int($explicit)) $explicit = (int)$explicit;
        if ($explicit === 1 && $explicit !== 0) {
            $setVal = (int)$curQty;
        } else if ($explicit === 0) {
            $setVal = 0;
        } else {
            $setVal = max(0, min((int)$explicit, (int)$curQty));
        }
        $stmt = $mysqli->prepare('UPDATE order_items SET served = ? WHERE id = ? LIMIT 1');
        if (!$stmt) throw new Exception($mysqli->error);
        $stmt->bind_param('ii', $setVal, $orderItemId);
        $stmt->execute();
        $stmt->close();
    }

    $stmt = $mysqli->prepare('SELECT served, quantity FROM order_items WHERE id = ? LIMIT 1');
    if (!$stmt) throw new Exception($mysqli->error);
    $stmt->bind_param('i', $orderItemId);
    $stmt->execute();
    $stmt->bind_result($served, $quantity);
    $stmt->fetch();
    $stmt->close();

    $response['success'] = true;
    $response['served'] = (int)$served;
    $response['quantity'] = (int)$quantity;
    $mysqli->close();
} catch (Exception $ex) {
    error_log('toggle_item_served error: ' . $ex->getMessage());
    $response['success'] = false;
    $response['error'] = $ex->getMessage();
}
header('Content-Type: application/json');
echo json_encode($response);
?>
