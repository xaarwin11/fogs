<?php
require_once __DIR__ . '/../db.php';
session_start();
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}
$role = strtolower($_SESSION['role'] ?? '');
if (!in_array($role, ['staff','admin','manager'])) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

$response = ['success' => false];
try {
    $mysqli = get_db_conn();

    $tableName = 'tables';
    $res = $mysqli->query("SHOW TABLES LIKE 'tables'");
    if (!$res || $res->num_rows === 0) {
        throw new Exception('Schema `tables` not found');
    }

    // Read JSON body
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['id'] ?? null;
    if (!$id) {
        throw new Exception('Missing table id');
    }

    $id = (int)$id;
    $requestedOccupied = isset($data['occupied']) ? (int)$data['occupied'] : null;

    $pkCol = 'id';
    $pkRes = $mysqli->query("SHOW KEYS FROM `{$tableName}` WHERE Key_name = 'PRIMARY'");
    if ($pkRes && $pkRes->num_rows > 0) {
        $rowPk = $pkRes->fetch_assoc();
        $pkCol = $rowPk['Column_name'] ?? 'id';
    }

    $col = null;
    $check = $mysqli->query("SHOW COLUMNS FROM `{$tableName}` LIKE 'occupied'");
    if ($check && $check->num_rows > 0) $col = 'occupied';
    $check = $mysqli->query("SHOW COLUMNS FROM `{$tableName}` LIKE 'is_occupied'");
    if (!$col && $check && $check->num_rows > 0) $col = 'is_occupied';
    $check = $mysqli->query("SHOW COLUMNS FROM `{$tableName}` LIKE 'status'");
    $hasStatus = $check && $check->num_rows > 0;

    if ($col) {
        if ($requestedOccupied !== null) {
            $stmt = $mysqli->prepare("UPDATE `{$tableName}` SET `{$col}` = ? WHERE `{$pkCol}` = ? LIMIT 1");
            if (!$stmt) throw new Exception($mysqli->error);
            $stmt->bind_param('ii', $requestedOccupied, $id);
            $stmt->execute();
            $stmt->close();
            $isOccupied = (bool)$requestedOccupied;
        } else {
            $stmt = $mysqli->prepare("UPDATE `{$tableName}` SET `{$col}` = 1 - `{$col}` WHERE `{$pkCol}` = ? LIMIT 1");
            if (!$stmt) throw new Exception($mysqli->error);
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();

            $stmt = $mysqli->prepare("SELECT `{$col}` FROM `{$tableName}` WHERE `{$pkCol}` = ? LIMIT 1");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->bind_result($val);
            $stmt->fetch();
            $stmt->close();
            $isOccupied = (bool)$val;
        }
        $response['success'] = true;
        $response['is_occupied'] = $isOccupied;
    } elseif ($hasStatus) {
        $stmt = $mysqli->prepare("SELECT `status` FROM `{$tableName}` WHERE `{$pkCol}` = ? LIMIT 1");
        if (!$stmt) throw new Exception($mysqli->error);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->bind_result($status);
        $stmt->fetch();
        $stmt->close();
        $status = strtolower(trim($status ?? ''));
        $new = ($status === 'occupied' || $status === 'in_use' || $status === 'busy' || $status === '1') ? 'available' : 'occupied';
        $stmt = $mysqli->prepare("UPDATE `{$tableName}` SET `status` = ? WHERE `{$pkCol}` = ? LIMIT 1");
        $stmt->bind_param('si', $new, $id);
        $stmt->execute();
        $stmt->close();
        $response['success'] = true;
        $response['is_occupied'] = ($new === 'occupied');
    } else {
        throw new Exception('No recognized status column found');
    }

    $mysqli->close();
} catch (Exception $ex) {
    error_log('toggle_table_status error: ' . $ex->getMessage());
    $response['success'] = false;
    $response['error'] = $ex->getMessage();
}

header('Content-Type: application/json');
echo json_encode($response);
?>
