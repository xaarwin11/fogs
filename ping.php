<?php
/**
 * ping.php - Heartbeat check for Fogs POS
 */

header("Cache-Control: no-cache, no-store, must-revalidate");
header("Content-Type: application/json");

// 1. Include the DB definitions
require_once __DIR__ . '/db.php'; 

try {
    // 2. Try to get the connection using your function
    // We use @ to suppress the automatic PHP warning so we can handle the error
    $conn = @get_db_conn();

    if ($conn && $conn->ping()) {
        echo json_encode([
            'status' => 'success',
            'database' => 'online'
        ]);
        $conn->close(); // Clean up
    } else {
        throw new Exception("Ping failed");
    }

} catch (Throwable $e) {
    // 3. This catches the 'Exception' thrown by your get_db_conn() function
    http_response_code(500); 
    echo json_encode([
        'status' => 'error',
        'message' => 'Database is offline'
    ]);
}
?>