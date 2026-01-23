<?php
/**
 * ping.php
 * High-speed heartbeat for the Fogs POS system.
 * Designed to minimize server load.
 */

// 1. Prevent the browser from caching the response
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.

// 2. Set the content type to JSON (standard for modern apps)
header('Content-Type: application/json');

// 3. Output a simple success message
echo json_encode([
    'status' => 'success',
    'timestamp' => time(),
    'message' => 'Fogs Server is reaching out!'
]);
?>