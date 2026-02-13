<?php
require_once __DIR__ . '/../../db.php';
header('Content-Type: application/json');

try {
    $mysqli = get_db_conn();
    // 1. Get Categories
    $cats = [];
    $res = $mysqli->query("SELECT id, name FROM categories ORDER BY name ASC");
    while($r = $res->fetch_assoc()) {
        $cats[$r['id']] = ['id'=>$r['id'], 'name'=>$r['name'], 'modifiers'=>[]];
    }

    // 2. Get Global Modifiers for each Category
    // Only fetch modifiers that are LINKED to a category
    $sql = "SELECT cm.category_id, m.name, m.price 
            FROM category_modifiers cm
            JOIN product_modifiers m ON cm.modifier_id = m.id";
    $res2 = $mysqli->query($sql);
    while($r = $res2->fetch_assoc()) {
        if(isset($cats[$r['category_id']])) {
            $cats[$r['category_id']]['modifiers'][] = ['name'=>$r['name'], 'price'=>$r['price']];
        }
    }

    echo json_encode(['success'=>true, 'categories'=>array_values($cats)]);
} catch(Exception $e) { echo json_encode(['success'=>false]); }
?>