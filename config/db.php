<?php

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$DB_HOST = '127.0.0.1';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'parking_service';
$DB_PORT = 3306;

function db(): mysqli {
    static $conn = null;
    global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT;

    if ($conn === null) {
        $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT);
        $conn->set_charset('utf8mb4');
    }
    return $conn;
}
