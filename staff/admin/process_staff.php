<?php
require_once __DIR__ . '/../../db.php';
session_start();

if (strtolower($_SESSION['role'] ?? '') !== 'admin') {
    die("Unauthorized");
}

$mysqli = get_db_conn();

$id = $_POST['user_id'] ?? '';
$fname = $_POST['first_name'];
$lname = $_POST['last_name'];
$user = $_POST['username'];
$rate = $_POST['hourly_rate'];
$pass = $_POST['password'];

if (empty($id)) {
    // --- ADD NEW STAFF ---
    // Password is required for new entries
    $hashed_pass = password_hash($pass, PASSWORD_BCRYPT);
    
    $stmt = $mysqli->prepare("INSERT INTO credentials (first_name, last_name, username, password, hourly_rate, role) VALUES (?, ?, ?, ?, ?, 'staff')");
    $stmt->bind_param("ssssd", $fname, $lname, $user, $hashed_pass, $rate);
} else {
    // --- EDIT EXISTING STAFF ---
    if (!empty($pass)) {
        // If password was provided, hash it and update everything
        $hashed_pass = password_hash($pass, PASSWORD_BCRYPT);
        $stmt = $mysqli->prepare("UPDATE credentials SET first_name=?, last_name=?, username=?, password=?, hourly_rate=? WHERE id=?");
        $stmt->bind_param("ssssdi", $fname, $lname, $user, $hashed_pass, $rate, $id);
    } else {
        // If password was blank, update only the other fields
        $stmt = $mysqli->prepare("UPDATE credentials SET first_name=?, last_name=?, username=?, hourly_rate=? WHERE id=?");
        $stmt->bind_param("sssdi", $fname, $lname, $user, $rate, $id);
    }
}

if ($stmt->execute()) {
    header("Location: staff.php?msg=success");
} else {
    echo "Error: " . $mysqli->error;
}