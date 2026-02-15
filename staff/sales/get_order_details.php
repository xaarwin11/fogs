<?php
require_once __DIR__ . '/../../db.php';
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

try {
    $mysqli = get_db_conn();
    
    // 1. Fetch Items (same as before)
    $stmt = $mysqli->prepare("
        SELECT oi.quantity, oi.base_price, oi.modifier_total, oi.discount_amount as item_discount,
               p.name as product_name, v.name as variation_name
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        LEFT JOIN product_variations v ON oi.variation_id = v.id
        WHERE oi.order_id = ?
    ");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // 2. Updated Meta: Fetch status, reason, and the name of the person who voided it
    $stmt = $mysqli->prepare("
        SELECT o.id, o.reference, o.grand_total, o.discount_total, o.status, o.void_reason,
               c.username as voided_by_name
        FROM orders o
        LEFT JOIN credentials c ON o.voided_by = c.id
        WHERE o.id = ?
    ");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $meta = $stmt->get_result()->fetch_assoc();

    echo json_encode(['success' => true, 'items' => $items, 'meta' => $meta]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}