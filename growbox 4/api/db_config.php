<?php
// growbox/api/db_config.php
//
// MySQL-Verbindung (Plesk / netcup)
// Diese Datei wird von API-Skripten & Dashboard verwendet.

$DB_HOST = "10.35.233.229";       // Datenbankserver (aus Plesk)
$DB_USER = "k332574_limi";        // DB-Benutzer
$DB_PASS = "Klabautermann.2";     // NEUES Passwort
$DB_NAME = "k332574_growbox";     // Datenbankname

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

if ($mysqli->connect_errno) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        "error"   => "DB connection failed",
        "details" => $mysqli->connect_error
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$mysqli->set_charset("utf8mb4");
