<?php
require_once __DIR__ . '/../../db.php';
session_start();
header('Content-Type: application/json');

if (strtolower($_SESSION['role'] ?? '') !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$mysqli = get_db_conn();
$action = $_GET['action'] ?? 'save';

// --- DELETE LOGIC ---
if ($action === 'delete') {
    $id = (int)$_GET['id'];
    $stmt = $mysqli->prepare("DELETE FROM discounts WHERE id = ?");
    $stmt->bind_param("i", $id);
    if($stmt->execute()) echo json_encode(['success' => true]);
    else echo json_encode(['success' => false, 'error' => $mysqli->error]);
    exit;
}

// --- SAVE/UPDATE LOGIC ---
$input = json_decode(file_get_contents('php://input'), true);
$id = !empty($input['id']) ? (int)$input['id'] : null;
$name = $input['name'];
$type = $input['type'];
$value = (float)$input['value'];
$target = $input['target_type']; // This is a STRING (e.g. 'all', 'food')
$active = (int)$input['is_active'];

if ($id) {
    // Update
    // FIXED TYPES: "ssdsii" -> String, String, Double, String, Int, Int
    $stmt = $mysqli->prepare("UPDATE discounts SET name=?, type=?, value=?, target_type=?, is_active=? WHERE id=?");
    $stmt->bind_param("ssdsii", $name, $type, $value, $target, $active, $id);
} else {
    // Insert
    // FIXED TYPES: "ssdsi" -> String, String, Double, String, Int
    $stmt = $mysqli->prepare("INSERT INTO discounts (name, type, value, target_type, is_active) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssdsi", $name, $type, $value, $target, $active);
}

if($stmt->execute()) echo json_encode(['success' => true]);
else echo json_encode(['success' => false, 'error' => $mysqli->error]);
?>