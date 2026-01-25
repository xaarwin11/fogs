<?php

require_once  '../db.php';
session_start();
header('Content-Type: application/json');
if (empty($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['success'=>false,'error'=>'Unauthorized']); exit; }
$role = strtolower($_SESSION['role'] ?? ''); if (!in_array($role, ['staff','admin','manager'])) { http_response_code(403); echo json_encode(['success'=>false,'error'=>'Forbidden']); exit; }
try {
    $mysqli = get_db_conn();
    $tables = [];
    $stmt = $mysqli->prepare('SELECT id, table_number, occupied FROM `tables` ORDER BY table_number ASC');
    if ($stmt) {
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $tables[] = ['id' => (int)$row['id'], 'table_number' => (int)($row['table_number'] ?? $row['id']), 'is_occupied' => isset($row['occupied']) ? ((int)$row['occupied'] === 1) : false];
        }
        $res->free();
        $stmt->close();
    }
    $mysqli->close();
    echo json_encode(['success'=>true,'tables'=>$tables]);
} catch (Exception $ex) {
    error_log('get_table_statuses error: ' . $ex->getMessage());
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'Server error']);
}
?>
