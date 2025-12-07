<?php
// growbox/api/measure.php
// Endpoint für ESP32, um Messwerte zu speichern (mit einfachem API-Key-Schutz)

require __DIR__ . '/../config.php';
require __DIR__ . '/db_config.php';
require __DIR__ . '/auth.php';

header('Content-Type: application/json; charset=utf-8');

// Nur ESP mit gültigem API-Key
require_api_key_auth();

// >>> ab hier dein bisheriger Mess-Insert-Code wie gehabt <<<
// Temperatur / Feuchte / Bodenfeuchte aus $_GET oder JSON lesen
// in measurements eintragen, JSON "status: ok" zurückgeben
// --- API-Key prüfen ---


$providedKey = null;

// Variante 1: Header "X-API-KEY: <key>"
if (isset($_SERVER['HTTP_X_API_KEY'])) {
    $providedKey = $_SERVER['HTTP_X_API_KEY'];
}

// Variante 2: ?api_key=... in URL/POST
if ($providedKey === null && isset($_REQUEST['api_key'])) {
    $providedKey = $_REQUEST['api_key'];
}

if (!defined('GROWBOX_API_KEY') || GROWBOX_API_KEY === '') {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'error'  => 'API key not configured on server'
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

if ($providedKey !== GROWBOX_API_KEY) {
    http_response_code(403);
    echo json_encode([
        'status' => 'error',
        'error'  => 'Forbidden: invalid API key'
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// --- Messwerte einlesen (GET oder POST) ---
$temperature = isset($_REQUEST['temperature']) ? trim($_REQUEST['temperature']) : null;
$humidity    = isset($_REQUEST['humidity'])    ? trim($_REQUEST['humidity'])    : null;
$soil1       = isset($_REQUEST['soil1'])       ? trim($_REQUEST['soil1'])       : null;
$soil2       = isset($_REQUEST['soil2'])       ? trim($_REQUEST['soil2'])       : null;
$soil3       = isset($_REQUEST['soil3'])       ? trim($_REQUEST['soil3'])       : null;

if ($temperature === null && $humidity === null && $soil1 === null && $soil2 === null && $soil3 === null) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'error'  => 'No measurement data provided'
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// in passende Typen casten (NULL bleibt NULL)
$temperature = ($temperature !== null && $temperature !== '') ? (float)$temperature : null;
$humidity    = ($humidity    !== null && $humidity    !== '') ? (float)$humidity   : null;
$soil1       = ($soil1       !== null && $soil1       !== '') ? (int)$soil1        : null;
$soil2       = ($soil2       !== null && $soil2       !== '') ? (int)$soil2        : null;
$soil3       = ($soil3       !== null && $soil3       !== '') ? (int)$soil3        : null;

// Zeitstempel (Serverzeit)
$createdAt = date('Y-m-d H:i:s');

// Prepared Statement
$stmt = $mysqli->prepare("
    INSERT INTO measurements (temperature, humidity, soil1, soil2, soil3, created_at)
    VALUES (?, ?, ?, ?, ?, ?)
");

if (!$stmt) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'error'  => 'DB prepare failed',
        'details'=> $mysqli->error
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// Datentypen: d = double (float), i = integer, s = string
$stmt->bind_param(
    'ddiiss',
    $temperature,
    $humidity,
    $soil1,
    $soil2,
    $soil3,
    $createdAt
);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'error'  => 'DB insert failed',
        'details'=> $stmt->error
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    $stmt->close();
    exit;
}

$insertId = $stmt->insert_id;
$stmt->close();

echo json_encode([
    'status' => 'ok',
    'id'     => $insertId,
    'saved'  => [
        'temperature' => $temperature,
        'humidity'    => $humidity,
        'soil1'       => $soil1,
        'soil2'       => $soil2,
        'soil3'       => $soil3,
        'created_at'  => $createdAt,
    ]
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
