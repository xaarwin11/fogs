<?php
require_once __DIR__ . '/../../db.php';
session_start();

if (strtolower($_SESSION['role'] ?? '') !== 'admin') { die("Unauthorized."); }
$mysqli = get_db_conn();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// --- 1. NEW: SAVE SYSTEM SETTINGS (Routing, Store Info, etc) ---
// This handles the printer ID routing from your Hardware Tab
if ($action === 'update_settings') {
    foreach ($_POST as $key => $value) {
        // Skip metadata fields so they don't get saved as settings
        if (in_array($key, ['action', 'current_tab'])) continue;
        
        // We use 'hardware' as default category if not specified
        $category = $_POST['current_tab'] ?? 'hardware';
        
        // UPSERT: Update the setting if it exists, or insert it if it's new
        $stmt = $mysqli->prepare("
            INSERT INTO system_settings (setting_key, setting_value, category) 
            VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");
        $stmt->bind_param("sss", $key, $value, $category);
        $stmt->execute();
    }
    
    $tab = $_POST['current_tab'] ?? 'hardware';
    header("Location: settings.php?tab=$tab&msg=settings_updated");
    exit;
}

// --- 2. ADD NEW CATEGORY ---
if ($action === 'add_category') {
    $name = $_POST['cat_name'];
    $type = $_POST['cat_type'] ?? 'food'; // Make sure your modal sends this!
    
    $stmt = $mysqli->prepare("INSERT INTO categories (name, cat_type) VALUES (?, ?)");
    $stmt->bind_param("ss", $name, $type);
    $stmt->execute();
    header("Location: settings.php?tab=categories&msg=cat_added");
    exit;
}

// --- 3. ADD GLOBAL MODIFIER ---
if ($action === 'add_master_modifier') {
    $name = $_POST['name'];
    $price = $_POST['price'];
    $stmt = $mysqli->prepare("INSERT INTO product_modifiers (name, price, product_id) VALUES (?, ?, NULL)");
    $stmt->bind_param("sd", $name, $price);
    $stmt->execute();
    header("Location: settings.php?tab=master-library&msg=mod_added");
    exit;
}
// --- SAVE NEW PRINTER DEVICE ---
if ($action === 'save_printer') {
    $label      = $_POST['printer_label'];
    $conn_type  = $_POST['connection_type'];
    $path       = $_POST['path'];
    $port       = !empty($_POST['port']) ? (int)$_POST['port'] : 9100;
    
    // Convert checkboxes to 1 or 0
    $cut  = isset($_POST['cut_after_print']) ? 1 : 0;
    $beep = isset($_POST['beep_on_print']) ? 1 : 0;

    $stmt = $mysqli->prepare("
        INSERT INTO printers (printer_label, connection_type, path, port, cut_after_print, beep_on_print, is_active) 
        VALUES (?, ?, ?, ?, ?, ?, 1)
    ");
    
    $stmt->bind_param("ssssii", $label, $conn_type, $path, $port, $cut, $beep);

    if ($stmt->execute()) {
        header("Location: settings.php?tab=hardware&msg=printer_saved");
    } else {
        // Handle error (e.g., table doesn't have 'port' column)
        die("Error saving printer: " . $mysqli->error);
    }
    exit;
}

// --- 4. DELETE HANDLER ---
if ($action === 'delete') {
    $target = $_GET['target'] ?? '';
    $id = (int)($_GET['id'] ?? 0);
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
        $tab = 'hardware';}

    if (isset($stmt)) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
    }
    header("Location: settings.php?tab=$tab&msg=deleted");
    exit;
}