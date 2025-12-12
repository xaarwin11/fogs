<?php


require_once __DIR__ . '/../../db.php';
session_start();

header('Content-Type: application/json');

$role = strtolower($_SESSION['role'] ?? '');
if (!in_array($role, ['staff','admin','manager','kitchen'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'User not logged in']);
    exit;
}

try {
    $mysqli = get_db_conn();
    
    $today = date('Y-m-d');

    $stmt = $mysqli->prepare('SELECT id FROM `time_tracking` WHERE user_id = ? AND clock_out IS NULL ORDER BY id DESC LIMIT 1');
    if (!$stmt) throw new Exception('Prepare failed: ' . $mysqli->error);

    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $open = $res->fetch_assoc();
    $res->free();
    $stmt->close();

    if ($open) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Already clocked in (open shift). Please clock out first.']);
        $mysqli->close();
        exit;
    }

    $now = date('Y-m-d H:i:s');
    $stmt = $mysqli->prepare('INSERT INTO `time_tracking` (user_id, clock_in, date) VALUES (?, ?, ?)');
    if (!$stmt) throw new Exception('Prepare failed: ' . $mysqli->error);

    $stmt->bind_param('iss', $user_id, $now, $today);
    $stmt->execute();
    $stmt->close();
    
    echo json_encode(['success' => true, 'message' => 'Clocked in at ' . date('H:i:s')]);
} catch (Exception $ex) {
    error_log('clock_in error: ' . $ex->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $ex->getMessage()]);
}

$mysqli->close();
?>
