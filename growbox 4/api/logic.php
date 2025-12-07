<?php
// growbox/api/logic.php
// Per-Relay-Modi, Sensor-Logik, Intervalle, Notfallpanel etc.

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/auth.php';

// Arduino **oder** Dashboard:
require_session_or_api_key();

// -------------------------------------------------
// Helper
// -------------------------------------------------
function tableExists($db, $name) {
    $nameSafe = $db->real_escape_string($name);
    $res = $db->query("SHOW TABLES LIKE '{$nameSafe}'");
    return $res && $res->num_rows > 0;
}

function getSettingValue($db, $name, $default = null) {
    $stmt = $db->prepare("SELECT value FROM settings WHERE name = ? LIMIT 1");
    if (!$stmt) return $default;
    $stmt->bind_param("s", $name);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && ($row = $res->fetch_assoc())) {
        $stmt->close();
        return $row['value'];
    }
    $stmt->close();
    return $default;
}

function setSettingValue($db, $name, $value) {
    $stmt = $db->prepare(
        "INSERT INTO settings (name, value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE value = VALUES(value)"
    );
    if (!$stmt) return;
    $stmt->bind_param("ss", $name, $value);
    $stmt->execute();
    $stmt->close();
}

function minutesToHhmm($minutes) {
    $minutes = ($minutes % 1440 + 1440) % 1440;
    $h = (int)($minutes / 60);
    $m = $minutes % 60;
    return sprintf('%02d:%02d', $h, $m);
}

function lightDurationString($onMin, $offMin) {
    $onMin  = ($onMin % 1440 + 1440) % 1440;
    $offMin = ($offMin % 1440 + 1440) % 1440;
    if ($offMin > $onMin) {
        $dur = $offMin - $onMin;
    } else {
        $dur = 1440 - ($onMin - $offMin);
    }
    $h = (int)($dur / 60);
    $m = $dur % 60;
    return sprintf('%d h %02d min', $h, $m);
}

function computeLightState($currentMin, $onMin, $offMin) {
    $currentMin = ($currentMin % 1440 + 1440) % 1440;
    $onMin      = ($onMin % 1440 + 1440) % 1440;
    $offMin     = ($offMin % 1440 + 1440) % 1440;

    if ($onMin === $offMin) {
        return 0;
    }
    if ($onMin < $offMin) {
        return ($currentMin >= $onMin && $currentMin < $offMin) ? 1 : 0;
    }
    return ($currentMin >= $onMin || $currentMin < $offMin) ? 1 : 0;
}

function soilRawToPercent($raw, $dry, $wet) {
    if ($raw === null || $dry === null || $wet === null || $dry == $wet) {
        return null;
    }
    if ($dry > $wet) {
        $clamped = max($wet, min($dry, $raw));
        $percent = 1.0 - (($clamped - $wet) / ($dry - $wet));
    } else {
        $clamped = max($dry, min($wet, $raw));
        $percent = ($clamped - $dry) / ($wet - $dry);
    }
    $percent *= 100.0;
    if ($percent < 0)   $percent = 0;
    if ($percent > 100) $percent = 100;
    return $percent;
}

function intervalState($now, $baseTs, $onSec, $offSec) {
    $period = max($onSec + $offSec, 1);
    $dt = ($now - $baseTs) % $period;
    if ($dt < 0) $dt += $period;
    return ($dt < $onSec) ? 1 : 0;
}

// -------------------------------------------------
$hasSettings = tableExists($mysqli, 'settings');
$hasRelays   = tableExists($mysqli, 'relays');

$relayKeys = ['heat', 'fan', 'exhaust', 'light'];
$allowedRelayModes = ['sensor', 'interval', 'blocked'];

// -------------------------------------------------
// Overrides / Modi aus Dashboard übernehmen
// (UI sendet: sensor, interval, blocked_off, blocked_on)
// -------------------------------------------------
if ($hasSettings && isset($_GET['override_update']) && $_GET['override_update'] === '1') {
    foreach ($relayKeys as $k) {
        $param = 'override_' . $k . '_mode';
        if (!isset($_GET[$param])) continue;
        $raw = $_GET[$param];

        $mode  = 'sensor';
        $state = '0';

        if ($raw === 'sensor' || $raw === 'interval') {
            $mode = $raw;
        } elseif ($raw === 'blocked_on') {
            $mode  = 'blocked';
            $state = '1';
        } elseif ($raw === 'blocked_off') {
            $mode  = 'blocked';
            $state = '0';
        }

        if (!in_array($mode, $allowedRelayModes, true)) {
            $mode = 'sensor';
        }

        setSettingValue($mysqli, 'override_' . $k . '_mode',  $mode);
        setSettingValue($mysqli, 'override_' . $k . '_state', $state);
    }
    $steps[] = "Relay-Modi / Blockzustände aus Dashboard aktualisiert.";
}

// -------------------------------------------------
// Overrides laden
// -------------------------------------------------
$overrides = [];
if ($hasSettings) {
    foreach ($relayKeys as $k) {
        $mode  = getSettingValue($mysqli, 'override_' . $k . '_mode', 'sensor');
        $state = getSettingValue($mysqli, 'override_' . $k . '_state', '0');
        if (!in_array($mode, $allowedRelayModes, true)) {
            $mode = 'sensor';
        }
        $overrides[$k] = [
            'mode'  => $mode,
            'state' => ($state === '1') ? 1 : 0,
        ];
    }
} else {
    foreach ($relayKeys as $k) {
        $overrides[$k] = ['mode' => 'sensor', 'state' => 0];
    }
}

// -------------------------------------------------
// Profil-Grenzwerte
// -------------------------------------------------
$tempMin = 22.0;
$tempMax = 26.0;
$humMin  = 55.0;
$humMax  = 70.0;

if ($hasSettings) {
    $v = getSettingValue($mysqli, 'active_temp_min', null);
    if ($v !== null && is_numeric($v)) $tempMin = (float)$v;
    $v = getSettingValue($mysqli, 'active_temp_max', null);
    if ($v !== null && is_numeric($v)) $tempMax = (float)$v;
    $v = getSettingValue($mysqli, 'active_hum_min', null);
    if ($v !== null && is_numeric($v)) $humMin  = (float)$v;
    $v = getSettingValue($mysqli, 'active_hum_max', null);
    if ($v !== null && is_numeric($v)) $humMax  = (float)$v;
} else {
    $steps[] = "Keine settings-Tabelle → nutze Default-Grenzwerte.";
}

// Lichtplan
$lightOnMin  = 360; // 06:00
$lightOffMin = 0;   // 00:00
if ($hasSettings) {
    $v = getSettingValue($mysqli, 'light_on_min', null);
    if ($v !== null && ctype_digit($v)) $lightOnMin = (int)$v;
    $v = getSettingValue($mysqli, 'light_off_min', null);
    if ($v !== null && ctype_digit($v)) $lightOffMin = (int)$v;
}

// Bodenfeuchte-Kalibrierung
$soilDryRaw = null;
$soilWetRaw = null;
if ($hasSettings) {
    $v = getSettingValue($mysqli, 'soil_dry_raw', null);
    if ($v !== null && is_numeric($v)) $soilDryRaw = (int)$v;
    $v = getSettingValue($mysqli, 'soil_wet_raw', null);
    if ($v !== null && is_numeric($v)) $soilWetRaw = (int)$v;
}

// Intervallgrundlagen
$now = time();
$fanOnSec  = 60;
$fanOffSec = 300;
$exOnSec   = 60;
$exOffSec  = 600;
$heatOnSec  = 60;
$heatOffSec = 300;
$intervalBase = $now;

if ($hasSettings) {
    $tmp = getSettingValue($mysqli, 'interval_fan_on_s', null);
    if ($tmp !== null && (int)$tmp > 0)  $fanOnSec  = (int)$tmp;
    $tmp = getSettingValue($mysqli, 'interval_fan_off_s', null);
    if ($tmp !== null && (int)$tmp >= 0) $fanOffSec = (int)$tmp;

    $tmp = getSettingValue($mysqli, 'interval_exhaust_on_s', null);
    if ($tmp !== null && (int)$tmp > 0)  $exOnSec   = (int)$tmp;
    $tmp = getSettingValue($mysqli, 'interval_exhaust_off_s', null);
    if ($tmp !== null && (int)$tmp >= 0) $exOffSec  = (int)$tmp;

    $tmp = getSettingValue($mysqli, 'interval_heat_on_s', null);
    if ($tmp !== null && (int)$tmp > 0)  $heatOnSec   = (int)$tmp;
    $tmp = getSettingValue($mysqli, 'interval_heat_off_s', null);
    if ($tmp !== null && (int)$tmp >= 0) $heatOffSec  = (int)$tmp;

    $baseStr = getSettingValue($mysqli, 'interval_base_ts', null);
    if ($baseStr !== null && ctype_digit($baseStr)) {
        $intervalBase = (int)$baseStr;
    } else {
        $intervalBase = $now;
        setSettingValue($mysqli, 'interval_base_ts', (string)$intervalBase);
        $steps[] = "Intervall: Basiszeitpunkt initial gesetzt.";
    }
} else {
    $steps[] = "Intervall: settings-Tabelle fehlt, nutze Default-Werte.";
}

// -------------------------------------------------
// Letzte Messung
// -------------------------------------------------
$sqlMeas = "SELECT id, temperature, humidity, soil1, soil2, soil3, created_at
            FROM measurements
            ORDER BY id DESC
            LIMIT 1";
$resMeas = $mysqli->query($sqlMeas);

if (!$resMeas) {
    echo json_encode([
        'status'  => 'error',
        'mode'    => 'per-relay',
        'steps'   => array_merge($steps, ["Fehler beim Lesen aus 'measurements': " . $mysqli->error]),
        'measurement' => null,
        'soil_avg'    => null,
        'soil_avg_percent' => null,
        'before'      => null,
        'after'       => null,
        'measurement_age_sec'   => null,
        'measurement_age_human' => null,
        'sensor_status'         => 'unknown',
        'overrides'             => $overrides,
    ]);
    exit;
}

$measurement = $resMeas->fetch_assoc();
if (!$measurement) {
    echo json_encode([
        'status'      => 'no_measurement',
        'mode'        => 'per-relay',
        'steps'       => array_merge($steps, ["Keine Messwerte vorhanden."]),
        'measurement' => null,
        'soil_avg'    => null,
        'soil_avg_percent' => null,
        'before'      => null,
        'after'       => null,
        'measurement_age_sec'   => null,
        'measurement_age_human' => null,
        'sensor_status'         => 'no_data',
        'overrides'             => $overrides,
    ]);
    exit;
}

// Bodenfeuchte
$soilVals = [];
for ($i = 1; $i <= 3; $i++) {
    $key = "soil{$i}";
    if ($measurement[$key] !== null && $measurement[$key] !== '') {
        $soilVals[] = (float)$measurement[$key];
    }
}
$soilAvg = $soilVals ? array_sum($soilVals) / count($soilVals) : null;
$soilAvgPercent = soilRawToPercent($soilAvg, $soilDryRaw, $soilWetRaw);

// Messungs-Alter & Sensorstatus
$measurementAgeSec   = null;
$measurementAgeHuman = null;
$sensorStatus        = 'ok';

if (!empty($measurement['created_at'])) {
    $ts = strtotime($measurement['created_at']);
    if ($ts !== false) {
        $diff = $now - $ts;
        if ($diff >= 0) {
            $measurementAgeSec = $diff;
            if ($measurementAgeSec < 90) {
                $measurementAgeHuman = $measurementAgeSec . ' s';
            } elseif ($measurementAgeSec < 3600) {
                $min = (int) floor($measurementAgeSec / 60);
                $measurementAgeHuman = $min . ' min';
            } else {
                $hours = (int) floor($measurementAgeSec / 3600);
                $measurementAgeHuman = $hours . ' h';
            }

            if ($measurementAgeSec > 600) {
                $steps[] = "WARNUNG: letzte Messung >10min alt ({$measurementAgeHuman}).";
            } elseif ($measurementAgeSec > 180) {
                $steps[] = "Hinweis: letzte Messung >3min alt ({$measurementAgeHuman}).";
            } else {
                $steps[] = "Letzte Messung ist frisch ({$measurementAgeHuman}).";
            }
        }
    }
}

$temp = ($measurement['temperature'] !== null && $measurement['temperature'] !== '') ? (float)$measurement['temperature'] : null;
$hum  = ($measurement['humidity']    !== null && $measurement['humidity']    !== '') ? (float)$measurement['humidity']    : null;

$missing = [];
if ($temp === null)    $missing[] = 'Temperatur';
if ($hum === null)     $missing[] = 'Luftfeuchte';
if ($soilAvg === null) $missing[] = 'Bodenfeuchte';

if ($missing) {
    $sensorStatus = 'missing: ' . implode(', ', $missing);
    $steps[] = "Achtung: keine gültigen Werte für: " . implode(', ', $missing) . ".";
} else {
    $sensorStatus = 'ok';
}

$highTemp = ($temp !== null && $temp > $tempMax);
$lowTemp  = ($temp !== null && $temp < $tempMin);
$highHum  = ($hum  !== null && $hum  > $humMax);
$lowHum   = ($hum  !== null && $hum  < $humMin);

$currentMinutes = (int)date('G', $now) * 60 + (int)date('i', $now);

// -------------------------------------------------
// Relais vorher
// -------------------------------------------------
if ($hasRelays) {
    $sqlRel = "SELECT heat, fan, exhaust, light, created_at
               FROM relays
               ORDER BY id DESC
               LIMIT 1";
    $resRel = $mysqli->query($sqlRel);
    if ($resRel && $row = $resRel->fetch_assoc()) {
        $before = [
            'heat'    => (int)$row['heat'],
            'fan'     => (int)$row['fan'],
            'exhaust' => (int)$row['exhaust'],
            'light'   => (int)$row['light'],
        ];
    } else {
        $before = ['heat'=>0,'fan'=>0,'exhaust'=>0,'light'=>0];
        $steps[] = "Keine Relais-Einträge gefunden, starte mit allen AUS.";
    }
} else {
    $before = ['heat'=>0,'fan'=>0,'exhaust'=>0,'light'=>0];
    $steps[] = "Tabelle 'relays' fehlt, Relaiszustände werden nicht gespeichert.";
}

$after = $before;

// -------------------------------------------------
// Vorberechnung Intervalle + Sensor-Wunsch
// -------------------------------------------------
$heatInterval    = intervalState($now, $intervalBase, $heatOnSec, $heatOffSec);
$fanInterval     = intervalState($now, $intervalBase, $fanOnSec,  $fanOffSec);
$exhaustInterval = intervalState($now, $intervalBase, $exOnSec,   $exOffSec);

$fanSensorWant     = null;
$exhaustSensorWant = null;
if ($temp !== null && $hum !== null) {
    if ($highTemp || $highHum) {
        $fanSensorWant     = 1;
        $exhaustSensorWant = 1;
        $steps[] = "Sensor-Logik: Temp/RH hoch → Lüftung bevorzugt EIN.";
    }
}

// -------------------------------------------------
// HEIZUNG
// -------------------------------------------------
switch ($overrides['heat']['mode']) {
    case 'blocked':
        $after['heat'] = $overrides['heat']['state'] ? 1 : 0;
        $steps[] = "Heizung: Blockiert → dauerhaft " . ($after['heat'] ? 'EIN' : 'AUS') . ".";
        break;

    case 'interval':
        $after['heat'] = $heatInterval;
        $steps[] = "Heizung: Intervall on={$heatOnSec}s/off={$heatOffSec}s → " . ($after['heat']?'EIN':'AUS') . ".";
        break;

    case 'sensor':
    default:
        if ($temp === null) {
            $steps[] = "Heizung: keine Temperaturmessung → Zustand unverändert.";
        } else {
            if ($lowTemp) {
                $after['heat'] = 1;
                $steps[] = "Heizung: Temp < {$tempMin}°C → EIN.";
            } elseif ($highTemp) {
                $after['heat'] = 0;
                $steps[] = "Heizung: Temp > {$tempMax}°C → AUS.";
            } else {
                $steps[] = "Heizung: Temp im Zielbereich → Zustand unverändert.";
            }
        }
        break;
}

// -------------------------------------------------
// UMLUFT – Sensor > Intervall
// -------------------------------------------------
switch ($overrides['fan']['mode']) {
    case 'blocked':
        $after['fan'] = $overrides['fan']['state'] ? 1 : 0;
        $steps[] = "Umluft: Blockiert → dauerhaft " . ($after['fan'] ? 'EIN' : 'AUS') . ".";
        break;

    case 'interval':
        $after['fan'] = $fanInterval;
        $steps[] = "Umluft: Intervall on={$fanOnSec}s/off={$fanOffSec}s → " . ($after['fan']?'EIN':'AUS') . ".";
        break;

    case 'sensor':
    default:
        if ($fanSensorWant === 1) {
            $after['fan'] = 1;
            $steps[] = "Umluft: Sensor will EIN → EIN (überstimmt Intervall).";
        } else {
            $after['fan'] = $fanInterval;
            $steps[] = "Umluft: Sensor ok → nutze Intervall → " . ($after['fan']?'EIN':'AUS') . ".";
        }
        break;
}

// -------------------------------------------------
// ABLUFT – Sensor > Intervall
// -------------------------------------------------
switch ($overrides['exhaust']['mode']) {
    case 'blocked':
        $after['exhaust'] = $overrides['exhaust']['state'] ? 1 : 0;
        $steps[] = "Abluft: Blockiert → dauerhaft " . ($after['exhaust'] ? 'EIN' : 'AUS') . ".";
        break;

    case 'interval':
        $after['exhaust'] = $exhaustInterval;
        $steps[] = "Abluft: Intervall on={$exOnSec}s/off={$exOffSec}s → " . ($after['exhaust']?'EIN':'AUS') . ".";
        break;

    case 'sensor':
    default:
        if ($exhaustSensorWant === 1) {
            $after['exhaust'] = 1;
            $steps[] = "Abluft: Sensor will EIN → EIN (überstimmt Intervall).";
        } else {
            $after['exhaust'] = $exhaustInterval;
            $steps[] = "Abluft: Sensor ok → nutze Intervall → " . ($after['exhaust']?'EIN':'AUS') . ".";
        }
        break;
}

// -------------------------------------------------
// LICHT
// -------------------------------------------------
switch ($overrides['light']['mode']) {
    case 'blocked':
        $after['light'] = $overrides['light']['state'] ? 1 : 0;
        $steps[] = "Licht: Blockiert → dauerhaft " . ($after['light'] ? 'EIN' : 'AUS') . ".";
        break;

    case 'interval':
    case 'sensor':
    default:
        $after['light'] = computeLightState($currentMinutes, $lightOnMin, $lightOffMin);
        $steps[] = "Licht: Photoperiode ".minutesToHhmm($lightOnMin)."–".minutesToHhmm($lightOffMin)." (".lightDurationString($lightOnMin,$lightOffMin).") → ".($after['light']?'EIN':'AUS').".";
        break;
}

// -------------------------------------------------
// Relaiszustände speichern
// -------------------------------------------------
if ($hasRelays && $after !== $before) {
    $stmtRel = $mysqli->prepare(
        "INSERT INTO relays (heat, fan, exhaust, light, created_at)
         VALUES (?, ?, ?, ?, NOW())"
    );
    if ($stmtRel) {
        $stmtRel->bind_param(
            "iiii",
            $after['heat'],
            $after['fan'],
            $after['exhaust'],
            $after['light']
        );
        $stmtRel->execute();
        if ($stmtRel->errno) {
            $steps[] = "Fehler beim Speichern der Relaiszustände: " . $stmtRel->error;
        } else {
            $steps[] = "Neue Relais-Zustände in 'relays' gespeichert.";
        }
        $stmtRel->close();
    } else {
        $steps[] = "Fehler beim Vorbereiten des Relais-Statements: " . $mysqli->error;
    }
} elseif (!$hasRelays) {
    $steps[] = "Relaiszustände werden nicht geloggt (Tabelle 'relays' fehlt).";
} else {
    $steps[] = "Relais-Zustände unverändert, keine Speicherung.";
}

// -------------------------------------------------
// JSON-Ausgabe
// -------------------------------------------------
echo json_encode([
    'status'                => 'ok',
    'mode'                  => 'per-relay',
    'steps'                 => $steps,
    'measurement'           => $measurement,
    'soil_avg'              => $soilAvg,
    'soil_avg_percent'      => $soilAvgPercent,
    'before'                => [
        'heat'    => (bool)$before['heat'],
        'fan'     => (bool)$before['fan'],
        'exhaust' => (bool)$before['exhaust'],
        'light'   => (bool)$before['light'],
    ],
    'after'                 => [
        'heat'    => (bool)$after['heat'],
        'fan'     => (bool)$after['fan'],
        'exhaust' => (bool)$after['exhaust'],
        'light'   => (bool)$after['light'],
    ],
    'measurement_age_sec'   => $measurementAgeSec,
    'measurement_age_human' => $measurementAgeHuman,
    'sensor_status'         => $sensorStatus,
    'overrides'             => $overrides,
]);
