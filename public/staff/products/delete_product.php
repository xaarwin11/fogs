<?php

require_once __DIR__ . '/../../db.php';
session_start();
header('Content-Type: application/json');
if (empty($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['success'=>false,'error'=>'Unauthorized']); exit; }
$role = strtolower($_SESSION['role'] ?? ''); if (!in_array($role, ['staff','admin','manager'])) { http_response_code(403); echo json_encode(['success'=>false,'error'=>'Forbidden']); exit; }
try{
    $data = json_decode(file_get_contents('php://input'), true);
    $id = isset($data['id']) ? (int)$data['id'] : 0; if (!$id) throw new Exception('Missing id');
    $mysqli = get_db_conn();
    $stmt = $mysqli->prepare('DELETE FROM products WHERE id = ?');
    if (!$stmt) throw new Exception('Prepare failed: ' . $mysqli->error);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close(); $mysqli->close();
    echo json_encode(['success'=>true]);
}catch(Exception $e){ error_log('delete_product error: '.$e->getMessage()); http_response_code(500); echo json_encode(['success'=>false,'error'=>'Server error','detail'=>$e->getMessage()]); }
?>
