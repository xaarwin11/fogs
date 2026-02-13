<?php
require_once __DIR__ . '/../../db.php';
header('Content-Type: application/json');
$mysqli = get_db_conn();

$data = json_decode(file_get_contents('php://input'), true);
$action = isset($_GET['action']) ? $_GET['action'] : 'add';

try {
    if ($action === 'add') {
        if ($data['type'] === 'variation') {
            $stmt = $mysqli->prepare("INSERT INTO category_variations (category_id, variation_name, price) VALUES (?, ?, ?)");
            $stmt->bind_param("isd", $data['category_id'], $data['name'], $data['price']);
        } else {
            $stmt = $mysqli->prepare("INSERT INTO category_modifiers (category_id, modifier_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $data['category_id'], $data['modifier_id']);
        }
        $stmt->execute();
    } 
    elseif ($action === 'delete') {
        if ($data['type'] === 'variation') {
            $stmt = $mysqli->prepare("DELETE FROM category_variations WHERE category_id = ? AND variation_name = ?");
            $stmt->bind_param("is", $data['category_id'], $data['target']);
        } else {
            $stmt = $mysqli->prepare("DELETE FROM category_modifiers WHERE category_id = ? AND modifier_id = ?");
            $stmt->bind_param("ii", $data['category_id'], $data['target']);
        }
        $stmt->execute();
    }
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}