<?php
require_once __DIR__ . '/../../db.php';
session_start();

// President-level security check
if (strtolower($_SESSION['role'] ?? '') !== 'admin') {
    die("Unauthorized access.");
}

$mysqli = get_db_conn();
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$current_tab = $_POST['current_tab'] ?? 'business'; // To redirect back to the same tab

// --- 1. UPDATE GLOBAL SETTINGS (Business, System, Routing) ---
if ($action === 'update_settings') {
    // We loop through everything in POST. If it's a key in our system_settings table, we update it.
    foreach ($_POST as $key => $value) {
        if (in_array($key, ['action', 'current_tab'])) continue; 
        
        $stmt = $mysqli->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
        $stmt->bind_param("ss", $value, $key);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: settings.php?msg=success&tab=$current_tab");
    exit;
}

// --- 2. PRINTER MANAGEMENT (Add/Update) ---
if ($action === 'save_printer') {
    $pid      = $_POST['printer_id'] ?? '';
    $label    = $_POST['printer_label'];
    $conn     = $_POST['connection_type'];
    $path     = $_POST['path'];
    $port     = $_POST['port'] ?? 9100;
    $beep     = isset($_POST['beep_on_print']) ? 1 : 0;
    $cut      = isset($_POST['cut_after_print']) ? 1 : 0;

    if (empty($pid)) {
        // New Printer
        $stmt = $mysqli->prepare("INSERT INTO printers (printer_label, connection_type, path, port, beep_on_print, cut_after_print) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssiii", $label, $conn, $path, $port, $beep, $cut);
    } else {
        // Update Existing
        $stmt = $mysqli->prepare("UPDATE printers SET printer_label=?, connection_type=?, path=?, port=?, beep_on_print=?, cut_after_print=? WHERE id=?");
        $stmt->bind_param("sssiiii", $label, $conn, $path, $port, $beep, $cut, $pid);
    }
    
    $stmt->execute();
    header("Location: settings.php?msg=printer_saved&tab=hardware");
    exit;
}

// --- 3. TABLE MANAGEMENT (Add) ---
if ($action === 'add_table') {
    $num  = $_POST['table_number'];
    $type = $_POST['table_type'];
    
    $stmt = $mysqli->prepare("INSERT INTO tables (table_number, table_type) VALUES (?, ?)");
    $stmt->bind_param("ss", $num, $type);
    
    if ($stmt->execute()) {
        header("Location: settings.php?msg=table_added&tab=tables");
    } else {
        header("Location: settings.php?msg=error&tab=tables");
    }
    exit;
}

// --- 4. DELETE ACTIONS (Printers or Tables) ---
if ($action === 'delete') {
    $target = $_GET['target'] ?? '';
    $id = $_GET['id'] ?? '';

    if ($target === 'table') {
        $stmt = $mysqli->prepare("DELETE FROM tables WHERE id = ?");
    } elseif ($target === 'printer') {
        $stmt = $mysqli->prepare("DELETE FROM printers WHERE id = ?");
    }

    if (isset($stmt)) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
    }
    
    $tab = ($target === 'table') ? 'tables' : 'hardware';
    header("Location: settings.php?msg=deleted&tab=$tab");
    exit;
}

$mysqli->close();
header("Location: settings.php");
exit;
?>