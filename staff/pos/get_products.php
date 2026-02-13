<?php
require_once __DIR__ . '/../../db.php';
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) { 
    http_response_code(401); 
    echo json_encode(['success'=>false,'error'=>'Unauthorized']); 
    exit; 
}

$role = strtolower($_SESSION['role'] ?? ''); 
if (!in_array($role, ['staff','admin','manager'])) { 
    http_response_code(403); 
    echo json_encode(['success'=>false,'error'=>'Forbidden']); 
    exit; 
}

try {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $mysqli = get_db_conn();

    // 1. Fetching a Single Product (e.g., for Edit Modal)
    if ($id) {
        $sql = "SELECT p.*, c.name AS category_name 
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.id 
                WHERE p.id = ? LIMIT 1";
        
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $p = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($p) {
            // Check if this product inherits variations OR has its own
            $varCheck = $mysqli->query("SELECT 
                ((SELECT COUNT(*) FROM product_variations WHERE product_id = {$id}) + 
                 (SELECT COUNT(*) FROM category_variations WHERE category_id = {$p['category_id']})) as total");
            $p['has_variation'] = ($varCheck->fetch_assoc()['total'] > 0) ? 1 : 0;
            
            // Check if this product inherits modifiers OR has its own
            $modCheck = $mysqli->query("SELECT 
                ((SELECT COUNT(*) FROM product_modifiers WHERE product_id = {$id}) + 
                 (SELECT COUNT(*) FROM category_modifiers WHERE category_id = {$p['category_id']})) as total");
            $p['has_modifiers'] = ($modCheck->fetch_assoc()['total'] > 0) ? 1 : 0;
        }

        $mysqli->close();
        echo json_encode(['success'=>true, 'product'=>$p]);
        exit;
    }

    // 2. Fetching All Products (Grouped by Category for POS)
    $sql = "SELECT p.id, p.name, p.price, p.category_id, c.name AS category_name 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            WHERE p.available = 1 
            ORDER BY category_name ASC, p.name ASC";

    $res = $mysqli->query($sql);
    $categories = [];

    while ($row = $res->fetch_assoc()) {
        $catName = $row['category_name'] ?? 'Uncategorized';
        $pid = (int)$row['id'];
        $cid = (int)$row['category_id'];

        if (!isset($categories[$catName])) $categories[$catName] = [];

        // Check for ANY variation (Product-specific OR Category-wide)
        $vQuery = $mysqli->query("SELECT 
            (SELECT COUNT(*) FROM product_variations WHERE product_id = $pid) + 
            (SELECT COUNT(*) FROM category_variations WHERE category_id = $cid) as total");
        $hasVar = ($vQuery->fetch_assoc()['total'] > 0) ? 1 : 0;

        // Check for ANY modifier (Product-specific OR Category-wide)
        $mQuery = $mysqli->query("SELECT 
            (SELECT COUNT(*) FROM product_modifiers WHERE product_id = $pid) + 
            (SELECT COUNT(*) FROM category_modifiers WHERE category_id = $cid) as total");
        $hasMod = ($mQuery->fetch_assoc()['total'] > 0) ? 1 : 0;

        $categories[$catName][] = [
            'id' => $pid,
            'name' => $row['name'],
            'price' => (float)$row['price'],
            'has_variation' => $hasVar,
            'has_modifiers' => $hasMod
        ];
    }

    $mysqli->close();
    echo json_encode(['success'=>true, 'categories'=>$categories]);

} catch(Exception $e) { 
    echo json_encode(['success'=>false, 'error'=>$e->getMessage()]); 
}
?>