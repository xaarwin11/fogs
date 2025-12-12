<?php

require_once __DIR__ . '/../../db.php';
session_start();
header('Content-Type: application/json');
if (empty($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['success'=>false,'error'=>'Unauthorized']); exit; }
$role = strtolower($_SESSION['role'] ?? ''); if (!in_array($role, ['staff','admin','manager'])) { http_response_code(403); echo json_encode(['success'=>false,'error'=>'Forbidden']); exit; }
try{
    $data = json_decode(file_get_contents('php://input'), true);
    $name = $data['name'] ?? '';
    $cat = $data['category'] ?? 'Other';
    $price = isset($data['price']) ? (float)$data['price'] : 0.00;
    $kds = isset($data['kds']) ? (int)$data['kds'] : 1;
    $avail = isset($data['available']) ? (int)$data['available'] : 1;

    $mysqli = get_db_conn();

    $hasKds = false; $hasAvail = false;
    $r = $mysqli->query("SHOW COLUMNS FROM `products` LIKE 'kds'"); if ($r && $r->num_rows > 0) $hasKds = true;
    $r2 = $mysqli->query("SHOW COLUMNS FROM `products` LIKE 'available'"); if ($r2 && $r2->num_rows > 0) $hasAvail = true;

    if ($hasKds && $hasAvail) {
        $stmt = $mysqli->prepare('INSERT INTO products (name, category, price, available, kds) VALUES (?, ?, ?, ?, ?)');
        $stmt->bind_param('ssdii',$name,$cat,$price,$avail,$kds);
    } elseif ($hasAvail) {
        $stmt = $mysqli->prepare('INSERT INTO products (name, category, price, available) VALUES (?, ?, ?, ?)');
        $stmt->bind_param('ssdi',$name,$cat,$price,$avail);
    } else {
        $stmt = $mysqli->prepare('INSERT INTO products (name, category, price) VALUES (?, ?, ?)');
        $stmt->bind_param('ssd',$name,$cat,$price);
    }

    if (!$stmt) throw new Exception('Prepare failed: ' . $mysqli->error);
    $stmt->execute();
    $id = $stmt->insert_id;
    $stmt->close(); $mysqli->close();
    echo json_encode(['success'=>true,'id'=>$id]);
}catch(Exception $e){ error_log('create_product error: '.$e->getMessage()); http_response_code(500); echo json_encode(['success'=>false,'error'=>'Server error','detail'=>$e->getMessage()]); }
?>
