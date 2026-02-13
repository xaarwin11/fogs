<?php
date_default_timezone_set('Asia/Manila');


$base_url = "/fogs-1";

$dbHost = 'localhost';
$dbName = 'fogs';
$dbUser = 'root';
$dbPass = '290505Slol';

function get_db_conn(): mysqli {
    global $dbHost, $dbUser, $dbPass, $dbName;

    $mysqli = mysqli_init(); // Initialize the object first
    $mysqli->options(MYSQLI_OPT_CONNECT_TIMEOUT, 2); // ONLY wait 2 seconds

    // Now try to connect
    $mysqli->real_connect($dbHost, $dbUser, $dbPass, $dbName);

    if ($mysqli->connect_errno) {
        error_log('MySQL connect error: ' . $mysqli->connect_error);
        throw new Exception('DB connection failed');
    }

    $mysqli->set_charset('utf8mb4');
    return $mysqli;
}