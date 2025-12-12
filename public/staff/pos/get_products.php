<?php

require_once __DIR__ . '/../../db.php';
session_start();
header('Content-Type: application/json');
if (empty($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['success'=>false,'error'=>'Unauthorized']); exit; }
$role = strtolower($_SESSION['role'] ?? ''); if (!in_array($role, ['staff','admin','manager'])) { http_response_code(403); echo json_encode(['success'=>false,'error'=>'Forbidden']); exit; }
try{
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $mysqli = get_db_conn();

    if ($id) {
        // Return a single product (existing behavior)
        $stmt = $mysqli->prepare('SELECT id, name, category, price, available, kds FROM products WHERE id = ? LIMIT 1');
        if (!$stmt) throw new Exception('Prepare failed: ' . $mysqli->error);
        $stmt->bind_param('i',$id);
        $stmt->execute();
        $res = $stmt->get_result();
        $p = $res->fetch_assoc();
        $stmt->close();

        if ($p) {
            // normalize fields
            $p['price'] = isset($p['price']) ? (float)$p['price'] : 0.0;
            $p['available'] = isset($p['available']) ? (bool)$p['available'] : false;
            $p['kds'] = (bool)($p['kds'] ?? false);
        }

        $mysqli->close();
        echo json_encode(['success'=>true,'product'=>$p]);
        exit;
    }

    // No id => return all products grouped by category for POS UI
    $stmt = $mysqli->prepare('SELECT id, name, category, price, available, kds FROM products WHERE available = 1 ORDER BY category ASC, name ASC');
    if (!$stmt) throw new Exception('Prepare failed: ' . $mysqli->error);
    $stmt->execute();
    $res = $stmt->get_result();

    $categories = [];
    while ($row = $res->fetch_assoc()) {
        $cat = $row['category'] ?? 'Uncategorized';
        if (!isset($categories[$cat])) $categories[$cat] = [];
        $categories[$cat][] = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'price' => isset($row['price']) ? (float)$row['price'] : 0.0,
            'available' => isset($row['available']) ? (bool)$row['available'] : false,
            'kds' => (bool)($row['kds'] ?? false)
        ];
    }
    $res->free();
    $stmt->close();
    $mysqli->close();

    echo json_encode(['success'=>true,'categories'=>$categories]);
}catch(Exception $e){ error_log('get_product error: '.$e->getMessage()); echo json_encode(['success'=>false,'error'=>'Server error']); }
?>
