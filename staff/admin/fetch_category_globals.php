<?php
require_once __DIR__ . '/../../db.php';
$mysqli = get_db_conn();
$catId = $_GET['category_id'] ?? 0;

// 1. Get current variations for this category
$vars = $mysqli->query("SELECT * FROM category_variations WHERE category_id = $catId")->fetch_all(MYSQLI_ASSOC);

// 2. Get modifiers already linked to this category
$linked = $mysqli->query("SELECT pm.* FROM product_modifiers pm 
    JOIN category_modifiers cm ON pm.id = cm.modifier_id 
    WHERE cm.category_id = $catId")->fetch_all(MYSQLI_ASSOC);

// 3. Get ALL available global modifiers (to show in the "Link" dropdown)
$available = $mysqli->query("SELECT * FROM product_modifiers WHERE product_id IS NULL ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);

echo json_encode([
    'variations' => $vars,
    'modifiers' => $linked,
    'all_available_modifiers' => $available
]);