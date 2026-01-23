<?php


require_once  '../db.php';
session_start();
header('Content-Type: application/json');
if (empty($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['success'=>false,'error'=>'Unauthorized']); exit; }
$role = strtolower($_SESSION['role'] ?? ''); if (!in_array($role, ['staff','admin','manager'])) { http_response_code(403); echo json_encode(['success'=>false,'error'=>'Forbidden']); exit; }
try {
    $table_id = isset($_GET['table_id']) ? (int)$_GET['table_id'] : 0;
    if (!$table_id) { echo json_encode(['success'=>true,'total_items'=>0]); exit; }
    $mysqli = get_db_conn();
    $stmt = $mysqli->prepare('SELECT SUM(oi.quantity) as total_items FROM orders o JOIN order_items oi ON oi.order_id = o.id WHERE o.table_id = ? AND o.status != "paid"');
    if ($stmt) {
        $stmt->bind_param('i', $table_id);
        $stmt->execute();
        $stmt->bind_result($total_items);
        $stmt->fetch();
        $stmt->close();
    }
    $mysqli->close();
    echo json_encode(['success'=>true,'total_items' => (int)($total_items ?? 0)]);
} catch (Exception $ex) {
    error_log('get_table_orders error: ' . $ex->getMessage());
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'Server error']);
}
?>
