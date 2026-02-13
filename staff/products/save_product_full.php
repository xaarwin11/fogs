<?php
// save_product_full.php
require_once __DIR__ . '/../../db.php';
session_start();
header('Content-Type: application/json');

// 1. Security Check (Copied from your existing files)
if (empty($_SESSION['user_id'])) { 
    http_response_code(401); 
    echo json_encode(['success'=>false, 'error'=>'Unauthorized']); 
    exit; 
}
$role = strtolower($_SESSION['role'] ?? '');
if (!in_array($role, ['staff','admin','manager'])) { 
    http_response_code(403); 
    echo json_encode(['success'=>false, 'error'=>'Forbidden']); 
    exit; 
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = isset($data['id']) ? (int)$data['id'] : 0;
    
    $mysqli = get_db_conn();
    
    // Start Transaction
    $mysqli->begin_transaction();

    // 2. Insert or Update MAIN Product
    if($id) {
        $stmt = $mysqli->prepare("UPDATE products SET name=?, category_id=?, price=?, kds=?, available=?, has_variation=? WHERE id=?");
        $stmt->bind_param("sidiiii", $data['name'], $data['category_id'], $data['price'], $data['kds'], $data['available'], $data['has_variation'], $id);
        $stmt->execute();
    } else {
        $stmt = $mysqli->prepare("INSERT INTO products (name, category_id, price, kds, available, has_variation) VALUES (?,?,?,?,?,?)");
        $stmt->bind_param("sidiii", $data['name'], $data['category_id'], $data['price'], $data['kds'], $data['available'], $data['has_variation']);
        $stmt->execute();
        $id = $mysqli->insert_id;
    }

    // 3. Handle Variations (Wipe old ones, Add new ones)
    $mysqli->query("DELETE FROM product_variations WHERE product_id = $id");
    
    if ($data['has_variation'] && !empty($data['variations'])) {
        $stmtV = $mysqli->prepare("INSERT INTO product_variations (product_id, name, price) VALUES (?, ?, ?)");
        foreach ($data['variations'] as $v) {
            // Ensure data types
            $vPrice = (float)$v['price'];
            $stmtV->bind_param("isd", $id, $v['name'], $vPrice);
            $stmtV->execute();
        }
        $stmtV->close();
    }

    // 4. Handle Product-Specific Modifiers (Wipe old ones, Add new ones)
    // NOTE: This does NOT touch 'category_modifiers', which is safer to manage elsewhere.
    $mysqli->query("DELETE FROM product_modifiers WHERE product_id = $id");
    
    if (!empty($data['modifiers'])) {
        $stmtM = $mysqli->prepare("INSERT INTO product_modifiers (product_id, name, price) VALUES (?, ?, ?)");
        foreach ($data['modifiers'] as $m) {
            $mPrice = (float)$m['price'];
            $stmtM->bind_param("isd", $id, $m['name'], $mPrice);
            $stmtM->execute();
        }
        $stmtM->close();
    }

    $mysqli->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if(isset($mysqli)) $mysqli->rollback();
    error_log('Save Product Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>