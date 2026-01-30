<?php
require_once __DIR__ . '/../../db.php';
header('Content-Type: application/json');

try {
    $mysqli = get_db_conn();
    $result = $mysqli->query("SELECT id, name FROM categories ORDER BY name ASC");
    
    $categories = [];
    while ($row = $result->fetch_assoc()) {
        $categories[] = [
            'id' => (int)$row['id'],
            'name' => $row['name']
        ];
    }
    
    $mysqli->close();
    echo json_encode(['success' => true, 'categories' => $categories]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>