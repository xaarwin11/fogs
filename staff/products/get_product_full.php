<?php
require_once __DIR__ . '/../../db.php';
header('Content-Type: application/json');
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if(!$id) exit(json_encode(['success'=>false]));

$mysqli = get_db_conn();

// 1. Basic Info
$stmt = $mysqli->prepare("SELECT * FROM products WHERE id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$prod = $stmt->get_result()->fetch_assoc();
$stmt->close();

// 2. Variations
$vars = [];
$stmt = $mysqli->prepare("SELECT name, price FROM product_variations WHERE product_id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
while($r = $res->fetch_assoc()) $vars[] = $r;
$stmt->close();

// 3. Modifiers (Only product-specific ones)
$mods = [];
$stmt = $mysqli->prepare("SELECT name, price FROM product_modifiers WHERE product_id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
while($r = $res->fetch_assoc()) $mods[] = $r;
$stmt->close();

echo json_encode(['success'=>true, 'product'=>$prod, 'variations'=>$vars, 'modifiers'=>$mods]);
?>