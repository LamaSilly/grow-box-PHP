<?php
// latest.php – letzte Messung für Dashboard

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/auth.php';

// nur Dashboard:
require_session_auth();

$sql = "SELECT
            device_id,
            temperature,
            humidity,
            soil1,
            soil2,
            soil3,
            heat,
            fan,
            exhaust,
            light,
            created_at
        FROM growbox_measurements
        ORDER BY id DESC
        LIMIT 1";

$result = $mysqli->query($sql);

if (!$result) {
    http_response_code(500);
    echo json_encode(["error" => "Query failed"]);
    exit;
}

$row = $result->fetch_assoc();

if (!$row) {
    echo json_encode(["error" => "No data yet"]);
    exit;
}

echo json_encode($row);
