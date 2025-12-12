<?php

date_default_timezone_set('Asia/Manila');

$dbHost = 'localhost';
$dbName = 'fogssystem';
$dbUser = 'root';
$dbPass = '';

function get_db_conn(): mysqli {
    global $dbHost, $dbUser, $dbPass, $dbName, $dbPort;

    $mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName, $dbPort);

    if ($mysqli->connect_errno) {
        error_log('MySQL connect error: ' . $mysqli->connect_error);
        throw new Exception('DB connection failed');
    }

    $mysqli->set_charset('utf8mb4');
    return $mysqli;
}


?>
