<?php
// growbox/tools/generate_test_data.php
// Testdaten-Generator: 60 Messpunkte im Minutentakt + Relaiszustände

require __DIR__ . '/../api/db_config.php'; // nutzt deine bestehende DB-Verbindung

header('Content-Type: text/plain; charset=utf-8');

// Anzahl Minuten / Punkte (Standard 60)
$minutes = isset($_GET['minutes']) ? max(1, (int)$_GET['minutes']) : 60;

// Optional: Startzeit in Sekunden relativ zu "jetzt" (Standard: letzte Stunde)
$now = time();
$startTs = $now - ($minutes * 60); // vor X Minuten

echo "Erzeuge {$minutes} Messungen + Relais-Einträge im 1-Minuten-Abstand...\n\n";

// Prepared Statements vorbereiten
$stmMeas = $mysqli->prepare("
    INSERT INTO measurements (temperature, humidity, soil1, soil2, soil3, created_at)
    VALUES (?, ?, ?, ?, ?, ?)
");
if (!$stmMeas) {
    echo "Fehler beim Vorbereiten des Measurements-Statements: " . $mysqli->error . "\n";
    exit;
}

$stmRel = $mysqli->prepare("
    INSERT INTO relays (heat, fan, exhaust, light, created_at)
    VALUES (?, ?, ?, ?, ?)
");
if (!$stmRel) {
    echo "Fehler beim Vorbereiten des Relays-Statements: " . $mysqli->error . "\n";
    exit;
}

for ($i = 0; $i < $minutes; $i++) {
    $ts = $startTs + $i * 60;                 // jede Minute
    $createdAt = date('Y-m-d H:i:s', $ts);

    // *** Messwerte leicht variieren ***
    // Basiswerte
    $baseTemp = 24.0;
    $baseHum  = 60.0;
    $baseSoil = 420;

    // kleine Wellenbewegung rein (sieht im Chart schöner aus)
    $temp = $baseTemp + sin($i / 10) * 1.5;  // +-1.5°C
    $hum  = $baseHum  + cos($i / 15) * 5;    // +-5%
    $soil1 = $baseSoil + mt_rand(-20, 20);
    $soil2 = $baseSoil + mt_rand(-20, 20);
    $soil3 = $baseSoil + mt_rand(-20, 20);

    // *** Relaislogik grob nachstellen ***
    // Heizung: an wenn < 23°C
    $heat = $temp < 23.0 ? 1 : 0;

    // Umluft: an wenn > 25.5°C oder hohe Luftfeuchte
    $fan = ($temp > 25.5 || $hum > 68) ? 1 : 0;

    // Abluft: an wenn Luftfeuchte > 65%
    $exhaust = $hum > 65 ? 1 : 0;

    // Licht: einfach nach Uhrzeit – an zwischen 06:00 und 18:00
    $hour = (int)date('G', $ts); // 0-23
    $light = ($hour >= 6 && $hour < 18) ? 1 : 0;

    // Measurements einfügen
    $t = (float)$temp;
    $h = (float)$hum;
    $s1 = (int)$soil1;
    $s2 = (int)$soil2;
    $s3 = (int)$soil3;
    $stmMeas->bind_param('ddiiss', $t, $h, $s1, $s2, $s3, $createdAt);
    if (!$stmMeas->execute()) {
        echo "Fehler beim Einfügen in measurements bei Minute {$i}: " . $stmMeas->error . "\n";
        break;
    }

    // Relays einfügen
    $stmRel->bind_param('iiiis', $heat, $fan, $exhaust, $light, $createdAt);
    if (!$stmRel->execute()) {
        echo "Fehler beim Einfügen in relays bei Minute {$i}: " . $stmRel->error . "\n";
        break;
    }

    echo "OK: {$createdAt} – Temp=" . round($t,1) . "°C, RH=" . round($h,1) . "%, Light=" . ($light ? 'AN' : 'AUS') . "\n";
}

$stmMeas->close();
$stmRel->close();

echo "\nFertig.\nTipp: stats.php öffnen, um die Diagramme zu prüfen.\n";
