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
    
    // Find the latest open shift (clock_out IS NULL) for this user
    $stmt = $mysqli->prepare('SELECT id, clock_in FROM `time_tracking` WHERE user_id = ? AND clock_out IS NULL ORDER BY clock_in DESC LIMIT 1');
    if (!$stmt) throw new Exception('Prepare failed: ' . $mysqli->error);

    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $record = $res->fetch_assoc();
    $res->free();
    $stmt->close();

    if (!$record) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'No open shift to clock out from']);
        $mysqli->close();
        exit;
    }

    // Calculate hours worked for this shift
    $clock_in = new DateTime($record['clock_in']);
    $clock_out = new DateTime('now');
    $interval = $clock_in->diff($clock_out);
    $hours = $interval->h + ($interval->i / 60) + ($interval->s / 3600);

    // Update record with clock_out and hours_worked
    $now = date('Y-m-d H:i:s');
    $stmt = $mysqli->prepare('UPDATE `time_tracking` SET clock_out = ?, hours_worked = ? WHERE id = ?');
    if (!$stmt) throw new Exception('Prepare failed: ' . $mysqli->error);

    $stmt->bind_param('sdi', $now, $hours, $record['id']);
    $stmt->execute();
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'message' => 'Clocked out',
        'hours_worked' => round($hours, 2),
        'clock_out_time' => date('H:i:s')
    ]);
} catch (Exception $ex) {
    error_log('clock_out error: ' . $ex->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $ex->getMessage()]);
}

$mysqli->close();
?>
