<?php
// api/settings.php
// GET  -> aktuelle Einstellungen lesen
// POST -> Einstellungen (Modus + Grenzwerte) speichern

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/auth.php';

// nur Dashboard:
require_session_auth();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Hilfsfunktion: gültigen Modus prüfen
function normalize_mode($mode) {
    $allowed = [
        'manual',
        'manual_advanced',
        'auto_simple',
        'auto_phases',
        'auto_curve'
    ];
    if (!in_array($mode, $allowed, true)) {
        return 'manual';
    }
    return $mode;
}

if ($method === 'POST') {
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

    $mode = isset($data['mode']) ? normalize_mode($data['mode']) : 'manual';

    // Numeric-Felder ggf. in float/int konvertieren, NaN -> null
    $temp_min = isset($data['temp_min']) ? floatval($data['temp_min']) : null;
    $temp_max = isset($data['temp_max']) ? floatval($data['temp_max']) : null;
    $hum_min  = isset($data['hum_min'])  ? floatval($data['hum_min'])  : null;
    $hum_max  = isset($data['hum_max'])  ? floatval($data['hum_max'])  : null;
    $soil_min = isset($data['soil_min']) ? intval($data['soil_min'])   : null;
    $soil_max = isset($data['soil_max']) ? intval($data['soil_max'])   : null;

    // NaN -> null
    foreach (['temp_min','temp_max','hum_min','hum_max','soil_min','soil_max'] as $k) {
        if (isset($$k) && is_numeric($$k) === false) {
            $$k = null;
        }
    }

    $stmt = $mysqli->prepare(
        "REPLACE INTO growbox_settings
         (id, mode, temp_min, temp_max, hum_min, hum_max, soil_min, soil_max)
         VALUES (1, ?, ?, ?, ?, ?, ?, ?)"
    );

    if (!$stmt) {
        http_response_code(500);
        echo json_encode(["error" => "Prepare failed"]);
        exit;
    }

    $stmt->bind_param(
        "sddddii",
        $mode,
        $temp_min,
        $temp_max,
        $hum_min,
        $hum_max,
        $soil_min,
        $soil_max
    );

    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(["error" => "Update failed"]);
        exit;
    }

    echo json_encode([
        "status"    => "ok",
        "mode"      => $mode,
        "temp_min"  => $temp_min,
        "temp_max"  => $temp_max,
        "hum_min"   => $hum_min,
        "hum_max"   => $hum_max,
        "soil_min"  => $soil_min,
        "soil_max"  => $soil_max
    ]);
    exit;
}

// ---------------------- GET --------------------------

$result = $mysqli->query(
    "SELECT mode, temp_min, temp_max, hum_min, hum_max, soil_min, soil_max, updated_at
     FROM growbox_settings
     WHERE id = 1"
);

if (!$result) {
    http_response_code(500);
    echo json_encode(["error" => "Query failed"]);
    exit;
}

$row = $result->fetch_assoc();

if (!$row) {
    // Fallback: Default anlegen
    $defaultMode = 'manual';
    $mysqli->query(
        "INSERT INTO growbox_settings (id, mode)
         VALUES (1, '{$defaultMode}')
         ON DUPLICATE KEY UPDATE id = id"
    );

    $row = [
        "mode"      => $defaultMode,
        "temp_min"  => null,
        "temp_max"  => null,
        "hum_min"   => null,
        "hum_max"   => null,
        "soil_min"  => null,
        "soil_max"  => null,
        "updated_at"=> null
    ];
} else {
    $row['mode'] = normalize_mode($row['mode']);
}

echo json_encode($row);
