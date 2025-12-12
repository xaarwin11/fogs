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
    
    // Determine if requesting own timesheet or all staff timesheets
    $view_all = in_array($role, ['admin', 'manager']);
    $request_user_id = $_GET['user_id'] ?? null;
    
    // Non-managers can only view their own
    if (!$view_all || !$request_user_id) {
        $target_user_id = $user_id;
    } else {
        $target_user_id = intval($request_user_id);
    }
    
    $date_param = $_GET['date'] ?? date('Y-m-d');
    
    if ($target_user_id === $user_id || $view_all) {
        // Get all records for the date and compute totals; support multiple shifts
        $stmt = $mysqli->prepare(
            'SELECT tt.id, tt.clock_in, tt.clock_out, tt.hours_worked, tt.date, c.username, c.first_name, c.last_name
             FROM `time_tracking` tt
             JOIN `credentials` c ON tt.user_id = c.id
             WHERE tt.user_id = ? AND tt.date = ?
             ORDER BY tt.clock_in ASC'
        );
        if (!$stmt) throw new Exception('Prepare failed: ' . $mysqli->error);

        $stmt->bind_param('is', $target_user_id, $date_param);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
        $res->free();
        $stmt->close();

        // Build response
        $total_hours = 0.0;
        $open_shift = false;
        $open_shift_clock_in = null;
        $records = [];
        $username = null;
        $name = null;

        foreach ($rows as $r) {
            $records[] = [
                'id' => (int)$r['id'],
                'clock_in' => $r['clock_in'],
                'clock_out' => $r['clock_out'],
                'hours_worked' => $r['hours_worked'] !== null ? (float)$r['hours_worked'] : null
            ];
            if ($r['hours_worked'] !== null) {
                $total_hours += (float)$r['hours_worked'];
            }
            if ($r['clock_out'] === null && $r['clock_in'] !== null) {
                $open_shift = true;
                $open_shift_clock_in = $r['clock_in'];
            }
            $username = $r['username'];
            $name = trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')) ?: $username;
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'total_hours' => round($total_hours, 2),
                'open_shift' => $open_shift,
                'open_shift_clock_in' => $open_shift_clock_in,
                'records' => $records,
                'date' => $date_param,
                'user_id' => $target_user_id,
                'username' => $username,
                'name' => $name
            ]
        ]);
    } else {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Cannot view other timesheets']);
    }
} catch (Exception $ex) {
    error_log('get_timesheet error: ' . $ex->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $ex->getMessage()]);
}

$mysqli->close();
?>
