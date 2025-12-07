<?php
// growbox/api/data.php
// Zweck:
//  - Messwerte vom ESP32 entgegennehmen und in "measurements" speichern
//  - optional: letzte Messung zurückgeben (?latest=1)

require __DIR__ . '/db_config.php';
require __DIR__ . '/auth.php';
header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];

// GET /growbox/api/data.php?latest=1
if ($method === 'GET' && isset($_GET['latest'])) {
    // hier reicht Dashboard-Login **oder** API-Key:
    require_session_or_api_key();
    // ... deine bisherige SELECT-Logik ...
} else {
    // Schreiben von Messwerten nur mit API-Key:
    require_api_key_auth();
    // ... dein bisheriger Insert-Code ...
}
// --- Modus 1: letzte Messung abfragen ---
// GET /growbox/api/data.php?latest=1
if ($method === 'GET' && isset($_GET['latest'])) {
    $result = $mysqli->query(
        "SELECT id, temperature, humidity, soil1, soil2, soil3, created_at
         FROM measurements
         ORDER BY id DESC
         LIMIT 1"
    );

    $row = $result ? $result->fetch_assoc() : null;

    echo json_encode([
        'status'      => $row ? 'ok' : 'no_measurement',
        'measurement' => $row ?: null,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// --- Modus 2: Messwerte speichern ---
// ESP32 kann GET oder POST schicken
$data = $_REQUEST;

// Temperatur / Feuchte, alternativ temp/hum
$temperature = null;
if (isset($data['temperature'])) {
    $temperature = (float)$data['temperature'];
} elseif (isset($data['temp'])) {
    $temperature = (float)$data['temp'];
}

$humidity = null;
if (isset($data['humidity'])) {
    $humidity = (float)$data['humidity'];
} elseif (isset($data['hum'])) {
    $humidity = (float)$data['hum'];
}

$soil1 = isset($data['soil1']) ? (float)$data['soil1'] : null;
$soil2 = isset($data['soil2']) ? (float)$data['soil2'] : null;
$soil3 = isset($data['soil3']) ? (float)$data['soil3'] : null;

// einfache Plausibilitätsprüfung
if ($temperature === null && $humidity === null && $soil1 === null && $soil2 === null && $soil3 === null) {
    http_response_code(400);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Keine Messwerte übergeben. Erwartet: temperature/humidity/soil1/soil2/soil3.'
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// Insert vorbereiten
$stmt = $mysqli->prepare(
    "INSERT INTO measurements (temperature, humidity, soil1, soil2, soil3)
     VALUES (?, ?, ?, ?, ?)"
);

$stmt->bind_param(
    "ddddd",
    $temperature,
    $humidity,
    $soil1,
    $soil2,
    $soil3
);

$stmt->execute();

if ($stmt->errno) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Fehler beim Speichern der Messwerte.',
        'details' => $stmt->error
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$insertId = $stmt->insert_id;

echo json_encode([
    'status' => 'ok',
    'id'     => $insertId,
    'saved'  => [
        'temperature' => $temperature,
        'humidity'    => $humidity,
        'soil1'       => $soil1,
        'soil2'       => $soil2,
        'soil3'       => $soil3,
    ]
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
