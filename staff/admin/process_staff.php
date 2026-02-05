<?php
require_once __DIR__ . '/../../db.php';
session_start();

// President-level security check
if (strtolower($_SESSION['role'] ?? '') !== 'admin') {
    die("Unauthorized access.");
}

$mysqli = get_db_conn();

$id       = $_POST['user_id'] ?? '';
$fname    = $_POST['first_name'];
$lname    = $_POST['last_name'];
$user     = $_POST['username'];
$rate     = $_POST['hourly_rate'];
$role_id  = $_POST['role_id']; 
$passcode = $_POST['passcode']; 

// --- 1. THE DUPLICATE PASSCODE PRE-CHECK ---
if (!empty($passcode)) {
    // We fetch all current passcodes to verify them against the new input
    $check_query = "SELECT id, passcode FROM credentials";
    $check_res = $mysqli->query($check_query);
    
    while ($row = $check_res->fetch_assoc()) {
        // Use password_verify to check if the new PIN matches an existing hash
        // We only trigger an error if the match belongs to a DIFFERENT user
        if (password_verify($passcode, $row['passcode']) && $row['id'] != $id) {
            header("Location: settings.php?msg=conflict_error");
            exit;
        }
    }
}

// --- 2. PREPARE DATA ---
$hashed_pin = !empty($passcode) ? password_hash($passcode, PASSWORD_BCRYPT) : null;

if (empty($id)) {
    // --- ADD NEW STAFF ---
    $stmt = $mysqli->prepare("INSERT INTO credentials (first_name, last_name, username, passcode, hourly_rate, role_id) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssdi", $fname, $lname, $user, $hashed_pin, $rate, $role_id);
} else {
    // --- EDIT EXISTING STAFF ---
    if (!empty($passcode)) {
        // Update everything including new passcode
        $stmt = $mysqli->prepare("UPDATE credentials SET first_name=?, last_name=?, username=?, passcode=?, hourly_rate=?, role_id=? WHERE id=?");
        $stmt->bind_param("ssssdii", $fname, $lname, $user, $hashed_pin, $rate, $role_id, $id);
    } else {
        // Update only profile info, keep old passcode
        $stmt = $mysqli->prepare("UPDATE credentials SET first_name=?, last_name=?, username=?, hourly_rate=?, role_id=? WHERE id=?");
        $stmt->bind_param("sssdii", $fname, $lname, $user, $rate, $role_id, $id);
    }
}

// --- 3. EXECUTION & ERROR HANDLING ---
if ($stmt->execute()) {
    header("Location: settings.php?msg=success&tab=staff");
    exit;
} else {
    // Check for duplicate Username (DB will still catch this as it's plain text)
    if ($mysqli->errno === 1062) {
        header("Location: settings.php?msg=conflict_error&tab=staff");
    } else {
        echo "Database Error: " . $mysqli->error;
    }
}

$stmt->close();
$mysqli->close();