<?php
require_once __DIR__ . '/../../db.php';
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;

if (!$product_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid Product ID']);
    exit;
}

try {
    $mysqli = get_db_conn();
    
    // 1. Fetch Base Price (Just in case there are no variations)
    $base_price = 0;
    $stmt = $mysqli->prepare("SELECT price FROM products WHERE id = ?");
    $stmt->bind_param('i', $product_id);
    $stmt->execute();
    $stmt->bind_result($base_price);
    $stmt->fetch();
    $stmt->close();

    // 2. Fetch Sizes (Variations)
    $sizes = [];
    // PRO TIP: Make sure your table is exactly 'product_variations'
    $stmt = $mysqli->prepare("SELECT id, name, price FROM product_variations WHERE product_id = ? ORDER BY price ASC");
    $stmt->bind_param('i', $product_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $sizes[] = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'price' => (float)$row['price']
        ];
    }
    $stmt->close();

    // 3. Fetch Modifiers (Add-ons)
    $modifiers = [];
    $stmt = $mysqli->prepare("SELECT id, name, price FROM product_modifiers WHERE product_id = ? ORDER BY name ASC");
    $stmt->bind_param('i', $product_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $modifiers[] = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'price' => (float)$row['price']
        ];
    }
    $stmt->close();

    $mysqli->close();

    echo json_encode([
        'success' => true,
        'base_price' => (float)$base_price,
        'sizes' => $sizes,
        'modifiers' => $modifiers
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>