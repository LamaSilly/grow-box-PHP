<?php
// control.php
// GET  -> aktuellen Relais-Zustand liefern
// POST -> Relais-Zustand vom Dashboard setzen

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/auth.php';

// nur eingeloggtes Dashboard:
require_session_auth();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// ... Rest deiner vorhandenen Logik unverändert ...

if ($method === 'POST') {
    // JSON einlesen
    $raw = file_get_contents('php://input');
    if (!$raw) {
        http_response_code(400);
        echo json_encode(["error" => "No request body"]);
        exit;
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid JSON"]);
        exit;
    }

    // Werte auslesen (optional, wenn nicht mitgeschickt -> Zustand bleibt)
    $heat    = isset($data['heat'])    ? (int)!empty($data['heat'])    : null;
    $fan     = isset($data['fan'])     ? (int)!empty($data['fan'])     : null;
    $exhaust = isset($data['exhaust']) ? (int)!empty($data['exhaust']) : null;
    $light   = isset($data['light'])   ? (int)!empty($data['light'])   : null;

    // Aktuellen Zustand holen
    $result = $mysqli->query("SELECT heat, fan, exhaust, light FROM growbox_control WHERE id = 1");
    if (!$result) {
        http_response_code(500);
        echo json_encode(["error" => "Query failed"]);
        exit;
    }
    $row = $result->fetch_assoc() ?: ["heat" => 0, "fan" => 0, "exhaust" => 0, "light" => 0];

    // Nur Felder überschreiben, die im JSON wirklich mitkamen
    $newHeat    = ($heat    !== null) ? $heat    : (int)$row['heat'];
    $newFan     = ($fan     !== null) ? $fan     : (int)$row['fan'];
    $newExhaust = ($exhaust !== null) ? $exhaust : (int)$row['exhaust'];
    $newLight   = ($light   !== null) ? $light   : (int)$row['light'];

    $stmt = $mysqli->prepare(
        "REPLACE INTO growbox_control (id, heat, fan, exhaust, light)
         VALUES (1, ?, ?, ?, ?)"
    );
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(["error" => "Prepare failed"]);
        exit;
    }

    $stmt->bind_param("iiii", $newHeat, $newFan, $newExhaust, $newLight);
    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(["error" => "Update failed"]);
        exit;
    }

    echo json_encode([
        "status"  => "ok",
        "heat"    => (bool)$newHeat,
        "fan"     => (bool)$newFan,
        "exhaust" => (bool)$newExhaust,
        "light"   => (bool)$newLight
    ]);
    exit;
}

// GET: aktuellen Zustand liefern
$result = $mysqli->query("SELECT heat, fan, exhaust, light, updated_at FROM growbox_control WHERE id = 1");
if (!$result) {
    http_response_code(500);
    echo json_encode(["error" => "Query failed"]);
    exit;
}

$row = $result->fetch_assoc() ?: [
    "heat" => 0,
    "fan" => 0,
    "exhaust" => 0,
    "light" => 0,
    "updated_at" => null
];

echo json_encode([
    "heat"      => (bool)$row['heat'],
    "fan"       => (bool)$row['fan'],
    "exhaust"   => (bool)$row['exhaust'],
    "light"     => (bool)$row['light'],
    "updated_at"=> $row['updated_at']
]);
