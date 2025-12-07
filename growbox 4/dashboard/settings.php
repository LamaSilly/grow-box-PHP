<?php
// growbox/dashboard/settings.php
session_start();
require __DIR__ . '/config.php';
require __DIR__ . '/../api/db_config.php';

if (empty($_SESSION['growbox_logged_in'])) {
    header('Location: /growbox/dashboard/login.php');
    exit;
}

// Helper Settings
function getSettingValue(mysqli $mysqli, string $name, ?string $default = null)
{
    $stmt = $mysqli->prepare("SELECT value FROM settings WHERE name = ? LIMIT 1");
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

function setSettingValue(mysqli $mysqli, string $name, string $value): void
{
    $stmt = $mysqli->prepare(
        "INSERT INTO settings (name, value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE value = VALUES(value)"
    );
    if (!$stmt) return;
    $stmt->bind_param("ss", $name, $value);
    $stmt->execute();
    $stmt->close();
}

// Helper Zeit
function minutesToHhmmLocal(int $minutes): string
{
    $minutes = ($minutes % 1440 + 1440) % 1440;
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    return sprintf('%02d:%02d', $h, $m);
}

function hhmmToMinutes(string $hhmm, int $fallback): int
{
    $hhmm = trim($hhmm);
    if (!preg_match('/^(\d{1,2}):(\d{2})$/', $hhmm, $m)) {
        return $fallback;
    }
    $h = (int)$m[1];
    $mi = (int)$m[2];
    if ($h < 0 || $h > 23 || $mi < 0 || $mi > 59) {
        return $fallback;
    }
    return $h * 60 + $mi;
}

$saveMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $profile = $_POST['profile'] ?? 'custom';
    if (!in_array($profile, ['custom', 'seedling', 'veg', 'bloom'], true)) {
        $profile = 'custom';
    }

    $tempMin = isset($_POST['temp_min']) ? (float)$_POST['temp_min'] : 22.0;
    $tempMax = isset($_POST['temp_max']) ? (float)$_POST['temp_max'] : 26.0;
    $humMin  = isset($_POST['hum_min'])  ? (float)$_POST['hum_min']  : 55.0;
    $humMax  = isset($_POST['hum_max'])  ? (float)$_POST['hum_max']  : 70.0;

    $fanOnSec  = isset($_POST['fan_on_s'])     ? max(0, (int)$_POST['fan_on_s'])     : 60;
    $fanOffSec = isset($_POST['fan_off_s'])    ? max(0, (int)$_POST['fan_off_s'])    : 300;
    $exOnSec   = isset($_POST['exhaust_on_s']) ? max(0, (int)$_POST['exhaust_on_s']) : 60;
    $exOffSec  = isset($_POST['exhaust_off_s'])? max(0, (int)$_POST['exhaust_off_s']): 600;

    $lightOnStr  = $_POST['light_on_time']  ?? '06:00';
    $lightOffStr = $_POST['light_off_time'] ?? '00:00';

    $lightOnMin  = hhmmToMinutes($lightOnStr, 360);
    $lightOffMin = hhmmToMinutes($lightOffStr, 0);

    $soilDryRaw = isset($_POST['soil_dry_raw']) ? (int)$_POST['soil_dry_raw'] : 800;
    $soilWetRaw = isset($_POST['soil_wet_raw']) ? (int)$_POST['soil_wet_raw'] : 300;

    // Settings speichern
    setSettingValue($mysqli, 'active_temp_min', (string)$tempMin);
    setSettingValue($mysqli, 'active_temp_max', (string)$tempMax);
    setSettingValue($mysqli, 'active_hum_min',  (string)$humMin);
    setSettingValue($mysqli, 'active_hum_max',  (string)$humMax);

    setSettingValue($mysqli, 'interval_fan_on_s',     (string)$fanOnSec);
    setSettingValue($mysqli, 'interval_fan_off_s',    (string)$fanOffSec);
    setSettingValue($mysqli, 'interval_exhaust_on_s', (string)$exOnSec);
    setSettingValue($mysqli, 'interval_exhaust_off_s',(string)$exOffSec);

    setSettingValue($mysqli, 'light_on_min',  (string)$lightOnMin);
    setSettingValue($mysqli, 'light_off_min', (string)$lightOffMin);

    setSettingValue($mysqli, 'growbox_active_profile', $profile);

    setSettingValue($mysqli, 'soil_dry_raw', (string)$soilDryRaw);
    setSettingValue($mysqli, 'soil_wet_raw', (string)$soilWetRaw);

    $saveMessage = 'Einstellungen gespeichert.';
}

// Aktuelle Werte laden
$currentProfile = getSettingValue($mysqli, 'growbox_active_profile', 'custom');

$tempMin = (float)getSettingValue($mysqli, 'active_temp_min', '22');
$tempMax = (float)getSettingValue($mysqli, 'active_temp_max', '26');
$humMin  = (float)getSettingValue($mysqli, 'active_hum_min',  '55');
$humMax  = (float)getSettingValue($mysqli, 'active_hum_max',  '70');

$fanOnSec  = (int)getSettingValue($mysqli, 'interval_fan_on_s',     '60');
$fanOffSec = (int)getSettingValue($mysqli, 'interval_fan_off_s',    '300');
$exOnSec   = (int)getSettingValue($mysqli, 'interval_exhaust_on_s', '60');
$exOffSec  = (int)getSettingValue($mysqli, 'interval_exhaust_off_s','600');

$lightOnMin  = (int)getSettingValue($mysqli, 'light_on_min',  '360'); // 06:00
$lightOffMin = (int)getSettingValue($mysqli, 'light_off_min', '0');   // 00:00

$soilDryRaw = getSettingValue($mysqli, 'soil_dry_raw', '800');
$soilWetRaw = getSettingValue($mysqli, 'soil_wet_raw', '300');

$lightOnStr  = minutesToHhmmLocal($lightOnMin);
$lightOffStr = minutesToHhmmLocal($lightOffMin);

// Leuchtdauer
$durStr = (function(int $onMin, int $offMin): string {
    $onMin  = ($onMin % 1440 + 1440) % 1440;
    $offMin = ($offMin % 1440 + 1440) % 1440;
    if ($offMin > $onMin) {
        $dur = $offMin - $onMin;
    } else {
        $dur = 1440 - ($onMin - $offMin);
    }
    $h = intdiv($dur, 60);
    $m = $dur % 60;
    return sprintf('%d h %02d min', $h, $m);
})($lightOnMin, $lightOffMin);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Growbox Einstellungen – liamslab</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            margin: 0;
            font-family: system-ui, sans-serif;
            background: radial-gradient(circle at top, #1e293b 0, #020617 55%);
            color: #e5e7eb;
            min-height: 100vh;
        }
        .page {
            max-width: 1100px;
            margin: 0 auto;
            padding: 1.8rem 1.4rem 2.4rem;
        }
        header {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            align-items: center;
            margin-bottom: 1.6rem;
        }
        h1 {
            margin: 0;
            font-size: 1.4rem;
        }
        .sub {
            font-size: 0.85rem;
            color: #9ca3af;
        }
        .nav-links {
            display: flex;
            gap: 0.6rem;
            flex-wrap: wrap;
            font-size: 0.8rem;
        }
        .nav-links a,
        .nav-links form button {
            border-radius: 999px;
            border: 1px solid #374151;
            background: rgba(15,23,42,0.95);
            color: #e5e7eb;
            padding: 0.3rem 0.7rem;
            text-decoration: none;
            cursor: pointer;
        }
        .nav-links a:hover,
        .nav-links form button:hover {
            background: rgba(15,23,42,1);
        }
        .card {
            background: rgba(15,23,42,0.98);
            border-radius: 1.3rem;
            border: 1px solid #1f2937;
            padding: 1.1rem 1.2rem 1.2rem;
            box-shadow: 0 18px 35px rgba(15,23,42,1);
            margin-bottom: 1.4rem;
        }
        .card-title {
            font-size: 0.95rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        .card-sub {
            font-size: 0.8rem;
            color: #9ca3af;
            margin-bottom: 0.8rem;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.8rem 1rem;
        }
        @media (max-width: 800px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
        label {
            display: block;
            font-size: 0.8rem;
            margin-bottom: 0.2rem;
        }
        input[type="number"],
        input[type="text"],
        select {
            width: 100%;
            border-radius: 0.7rem;
            border: 1px solid #374151;
            background: #020617;
            color: #e5e7eb;
            padding: 0.35rem 0.6rem;
            font-size: 0.85rem;
        }
        .btn-save {
            margin-top: 1rem;
            border-radius: 999px;
            border: none;
            background: linear-gradient(135deg, #22c55e, #16a34a);
            color: #022c22;
            font-weight: 600;
            padding: 0.5rem 1.1rem;
            cursor: pointer;
            font-size: 0.85rem;
        }
        .save-msg {
            margin-top: 0.6rem;
            font-size: 0.8rem;
            color: #bbf7d0;
        }
        .mono {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
        }
        .hint {
            font-size: 0.75rem;
            color: #9ca3af;
            margin-top: 0.2rem;
        }
    </style>
</head>
<body>
<div class="page">
    <header>
        <div>
            <h1>Growbox Einstellungen</h1>
            <p class="sub">Profile, Grenzwerte, Intervalle, Lichtplan &amp; Bodenfeuchte-Kalibrierung.</p>
        </div>
        <div class="nav-links">
            <a href="/growbox/dashboard/dashboard.php">← Dashboard</a>
            <a href="/growbox/dashboard/stats.php">Statistik</a>
            <a href="/growbox/api/logic.php" target="_blank">API JSON</a>
            <form action="/growbox/dashboard/logout.php" method="post" style="margin:0;">
                <button type="submit">Logout</button>
            </form>
        </div>
    </header>

    <form method="post">
        <section class="card">
            <div class="card-title">Profil &amp; Klima</div>
            <div class="card-sub">
                Wähle ein Profil (Anzucht/Wachstum/Blüte) oder nutze <strong>Custom</strong> und passe die Werte nach Bedarf an.
            </div>

            <div style="margin-bottom:0.9rem;">
                <label for="profile">Profil</label>
                <select name="profile" id="profile">
                    <option value="custom"   <?= $currentProfile === 'custom'   ? 'selected' : '' ?>>Custom</option>
                    <option value="seedling" <?= $currentProfile === 'seedling' ? 'selected' : '' ?>>Anzucht</option>
                    <option value="veg"      <?= $currentProfile === 'veg'      ? 'selected' : '' ?>>Wachstum</option>
                    <option value="bloom"    <?= $currentProfile === 'bloom'    ? 'selected' : '' ?>>Blüte</option>
                </select>
                <p class="hint">
                    Profil-Auswahl füllt nur lokal die Felder vor. Gespeichert werden die Werte, die du unten einträgst.
                </p>
            </div>

            <div class="form-grid">
                <div>
                    <label>Temperatur min (°C)</label>
                    <input type="number" step="0.1" name="temp_min" id="temp_min" value="<?= htmlspecialchars($tempMin) ?>">
                </div>
                <div>
                    <label>Temperatur max (°C)</label>
                    <input type="number" step="0.1" name="temp_max" id="temp_max" value="<?= htmlspecialchars($tempMax) ?>">
                </div>
                <div>
                    <label>Luftfeuchte min (%)</label>
                    <input type="number" step="0.1" name="hum_min" id="hum_min" value="<?= htmlspecialchars($humMin) ?>">
                </div>
                <div>
                    <label>Luftfeuchte max (%)</label>
                    <input type="number" step="0.1" name="hum_max" id="hum_max" value="<?= htmlspecialchars($humMax) ?>">
                </div>
            </div>
        </section>

        <section class="card">
            <div class="card-title">Umluft- &amp; Abluft-Intervalle</div>
            <div class="card-sub">
                Wird im <strong>Intervallmodus</strong> verwendet. Werte in Sekunden.
            </div>
            <div class="form-grid">
                <div>
                    <label>Umluft EIN (s)</label>
                    <input type="number" min="0" name="fan_on_s" id="fan_on_s" value="<?= htmlspecialchars($fanOnSec) ?>">
                </div>
                <div>
                    <label>Umluft AUS (s)</label>
                    <input type="number" min="0" name="fan_off_s" id="fan_off_s" value="<?= htmlspecialchars($fanOffSec) ?>">
                </div>
                <div>
                    <label>Abluft EIN (s)</label>
                    <input type="number" min="0" name="exhaust_on_s" id="exhaust_on_s" value="<?= htmlspecialchars($exOnSec) ?>">
                </div>
                <div>
                    <label>Abluft AUS (s)</label>
                    <input type="number" min="0" name="exhaust_off_s" id="exhaust_off_s" value="<?= htmlspecialchars($exOffSec) ?>">
                </div>
            </div>
        </section>

        <section class="card">
            <div class="card-title">Lichtplan</div>
            <div class="card-sub">
                Uhrzeiten für Licht AN/AUS (24-h-Format). Aktuelle Leuchtdauer: <strong><?= htmlspecialchars($durStr) ?></strong>.
            </div>

            <div class="form-grid">
                <div>
                    <label>Licht AN (HH:MM)</label>
                    <input type="text" name="light_on_time" id="light_on_time" value="<?= htmlspecialchars($lightOnStr) ?>" class="mono">
                </div>
                <div>
                    <label>Licht AUS (HH:MM)</label>
                    <input type="text" name="light_off_time" id="light_off_time" value="<?= htmlspecialchars($lightOffStr) ?>" class="mono">
                </div>
            </div>

            <p class="hint">
                Beispiel: 06:00–00:00 ≈ 18h Beleuchtung (Anzucht/Wachstum), 06:00–18:00 ≈ 12h (Blüte).
            </p>
        </section>

        <section class="card">
            <div class="card-title">Bodenfeuchte-Kalibrierung</div>
            <div class="card-sub">
                Rohwerte der Bodenfeuchtesensoren in <strong>trocken</strong> und <strong>nass</strong>. Daraus wird im Dashboard die Bodenfeuchte in % berechnet.
            </div>

            <div class="form-grid">
                <div>
                    <label>„Trocken“-Rohwert</label>
                    <input type="number" name="soil_dry_raw" id="soil_dry_raw" value="<?= htmlspecialchars($soilDryRaw) ?>">
                    <p class="hint">
                        Sensor in trockene Erde/Luft legen, Rohwert ablesen (z.B. in Stats) und hier eintragen.
                    </p>
                </div>
                <div>
                    <label>„Nass“-Rohwert</label>
                    <input type="number" name="soil_wet_raw" id="soil_wet_raw" value="<?= htmlspecialchars($soilWetRaw) ?>">
                    <p class="hint">
                        Sensor in gut bewässerte Erde legen, Rohwert ablesen und hier eintragen.
                    </p>
                </div>
            </div>

            <p class="hint">
                Hinweis: Viele kapazitive Sensoren liefern bei trocken <em>höhere</em> Rohwerte als bei nass. Das wird automatisch berücksichtigt.
            </p>
        </section>

        <button type="submit" class="btn-save">Einstellungen speichern</button>
        <?php if ($saveMessage): ?>
            <div class="save-msg"><?= htmlspecialchars($saveMessage) ?></div>
        <?php endif; ?>
    </form>
</div>

<script>
    // Profil-Vorschläge lokal (nur zum Vorbefüllen der Felder)
    const profilePresets = {
        custom:   {},
        seedling: { // Anzucht
            temp_min: 23, temp_max: 26,
            hum_min:  65, hum_max: 75,
            fan_on_s: 30,  fan_off_s: 300,
            exhaust_on_s: 30, exhaust_off_s: 600,
            light_on_time: "06:00", light_off_time: "00:00"
        },
        veg: { // Wachstum
            temp_min: 22, temp_max: 26,
            hum_min:  55, hum_max: 70,
            fan_on_s: 60,  fan_off_s: 300,
            exhaust_on_s: 60, exhaust_off_s: 600,
            light_on_time: "06:00", light_off_time: "00:00"
        },
        bloom: { // Blüte
            temp_min: 21, temp_max: 25,
            hum_min:  45, hum_max: 60,
            fan_on_s: 60,  fan_off_s: 300,
            exhaust_on_s: 60, exhaust_off_s: 600,
            light_on_time: "06:00", light_off_time: "18:00"
        }
    };

    document.getElementById('profile').addEventListener('change', (e) => {
        const p = e.target.value;
        const preset = profilePresets[p] || {};
        if (!Object.keys(preset).length) return;

        if (preset.temp_min != null) document.getElementById('temp_min').value = preset.temp_min;
        if (preset.temp_max != null) document.getElementById('temp_max').value = preset.temp_max;
        if (preset.hum_min  != null) document.getElementById('hum_min').value  = preset.hum_min;
        if (preset.hum_max  != null) document.getElementById('hum_max').value  = preset.hum_max;

        if (preset.fan_on_s     != null) document.getElementById('fan_on_s').value     = preset.fan_on_s;
        if (preset.fan_off_s    != null) document.getElementById('fan_off_s').value    = preset.fan_off_s;
        if (preset.exhaust_on_s != null) document.getElementById('exhaust_on_s').value = preset.exhaust_on_s;
        if (preset.exhaust_off_s!= null) document.getElementById('exhaust_off_s').value= preset.exhaust_off_s;

        if (preset.light_on_time  != null) document.getElementById('light_on_time').value  = preset.light_on_time;
        if (preset.light_off_time != null) document.getElementById('light_off_time').value = preset.light_off_time;
    });
</script>
</body>
</html>
