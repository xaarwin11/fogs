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

    if ($id) {
        // Updated to JOIN with categories table
        $sql = "SELECT p.id, p.name, p.price, p.available, p.kds, c.name AS category_name, p.category_id 
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.id 
                WHERE p.id = ? LIMIT 1";
        
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $p = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($p) {
            $p['price'] = (float)$p['price'];
            $p['available'] = (bool)$p['available'];
            $p['kds'] = (bool)$p['kds'];
            // For the frontend grouping logic, we keep the label consistent
            $p['category'] = $p['category_name'] ?? 'Uncategorized'; 
        }

        $mysqli->close();
        echo json_encode(['success'=>true, 'product'=>$p]);
        exit;
    }

    // Fetch all available products grouped by category name
    $sql = "SELECT p.id, p.name, p.price, p.available, p.kds, c.name AS category_name 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            WHERE p.available = 1 
            ORDER BY category_name ASC, p.name ASC";

    $res = $mysqli->query($sql);
    $categories = [];

    while ($row = $res->fetch_assoc()) {
        $cat = $row['category_name'] ?? 'Uncategorized';
        if (!isset($categories[$cat])) $categories[$cat] = [];
        
        $categories[$cat][] = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'price' => (float)$row['price'],
            'available' => (bool)$row['available'],
            'kds' => (bool)$row['kds']
        ];
    }

    $mysqli->close();
    echo json_encode(['success'=>true, 'categories'=>$categories]);

} catch(Exception $e) { 
    error_log('get_product error: '.$e->getMessage()); 
    echo json_encode(['success'=>false, 'error'=>'Server error']); 
}
?>