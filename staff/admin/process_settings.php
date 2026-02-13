<?php
require_once __DIR__ . '/../../db.php';
session_start();

if (strtolower($_SESSION['role'] ?? '') !== 'admin') { die("Unauthorized."); }
$mysqli = get_db_conn();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// --- 1. ADD NEW CATEGORY ---
if ($action === 'add_category') {
    $name = $_POST['cat_name'];
    $stmt = $mysqli->prepare("INSERT INTO categories (name) VALUES (?)");
    $stmt->bind_param("s", $name);
    $stmt->execute();
    header("Location: settings.php?tab=categories&msg=cat_added");
    exit;
}

// --- 2. ADD GLOBAL MODIFIER (Master Add-on) ---
if ($action === 'add_master_modifier') {
    $name = $_POST['name'];
    $price = $_POST['price'];
    // product_id is NULL for global modifiers
    $stmt = $mysqli->prepare("INSERT INTO product_modifiers (name, price, product_id) VALUES (?, ?, NULL)");
    $stmt->bind_param("sd", $name, $price);
    $stmt->execute();
    header("Location: settings.php?tab=master-library&msg=mod_added");
    exit;
}

// --- 3. UPDATED DELETE HANDLER ---
if ($action === 'delete') {
    $target = $_GET['target'] ?? '';
    $id = $_GET['id'] ?? '';
    $tab = 'business';

    if ($target === 'category') {
        $stmt = $mysqli->prepare("DELETE FROM categories WHERE id = ?");
        $tab = 'categories';
    } elseif ($target === 'master_mod') {
        $stmt = $mysqli->prepare("DELETE FROM product_modifiers WHERE id = ? AND product_id IS NULL");
        $tab = 'master-library';
    } elseif ($target === 'table') {
        $stmt = $mysqli->prepare("DELETE FROM tables WHERE id = ?");
        $tab = 'tables';
    } elseif ($target === 'printer') {
        $stmt = $mysqli->prepare("DELETE FROM printers WHERE id = ?");
        $tab = 'hardware';
    }

    if (isset($stmt)) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
    }
    header("Location: settings.php?tab=$tab&msg=deleted");
    exit;
}