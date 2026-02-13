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
    
    // 1. Fetch Base Price & Category ID (Important for Global Modifiers)
    $base_price = 0;
    $category_id = 0;
    $stmt = $mysqli->prepare("SELECT price, category_id FROM products WHERE id = ?");
    $stmt->bind_param('i', $product_id);
    $stmt->execute();
    $stmt->bind_result($base_price, $category_id);
    $stmt->fetch();
    $stmt->close();

    // 2. Fetch Sizes (Variations)
    // 2. Fetch Sizes (Product-specific Variations + Category-wide Variations)
    $sizes = [];
    $query_vars = "
        SELECT id, name, price, 'product' as source FROM product_variations WHERE product_id = ?
        UNION
        SELECT NULL as id, variation_name as name, price, 'category' as source 
        FROM category_variations 
        WHERE category_id = ?
        ORDER BY price ASC
    ";
    
    $stmt = $mysqli->prepare($query_vars);
    $stmt->bind_param('ii', $product_id, $category_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $sizes[] = [
            // Use name as a fallback ID string so JS has a unique value to reference
            'id' => $row['id'] !== null ? (int)$row['id'] : $row['name'], 
            'name' => $row['name'],
            'price' => (float)$row['price']
        ];
    }
    $stmt->close();

    // 3. Fetch Modifiers (Direct Product Modifiers + Category-Wide Modifiers)
    // We use DISTINCT to ensure we don't show the same modifier twice if it's linked both ways
    // 3. Fetch Modifiers (Matching your fogs schema)
        $modifiers = [];
        $query = "
            /* Get modifiers linked directly to this product */
            SELECT id, name, price 
            FROM product_modifiers 
            WHERE product_id = ? 
            
            UNION
            
            /* Get modifiers linked to this category via the bridge table */
            SELECT m.id, m.name, m.price 
            FROM category_modifiers cm
            JOIN product_modifiers m ON cm.modifier_id = m.id
            WHERE cm.category_id = ?
            
            ORDER BY name ASC
        ";

        $stmt = $mysqli->prepare($query);
        $stmt->bind_param('ii', $product_id, $category_id);
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