<?php
// growbox/dashboard/stats.php
// Zeigt Messwerte + Relaiszustände, gespeist aus der API /growbox/api/data.php (Tabelle "measurements")

session_start();
require __DIR__ . '/config.php';
require __DIR__ . '/../api/db_config.php';

if (empty($_SESSION['growbox_logged_in'])) {
    header("Location: /growbox/dashboard/login.php");
    exit;
}

// kleine Helper-Funktion zum Laden der letzten Datensätze
function tableLast(mysqli $db, string $tableName, int $limit = 1000): array {
    $safe = $db->real_escape_string($tableName);

    // Prüfen, ob Tabelle existiert
    $res = $db->query("SHOW TABLES LIKE '{$safe}'");
    if (!$res || $res->num_rows === 0) {
        return [];
    }

    // Neueste Einträge zuerst holen
    $sql  = "SELECT * FROM `{$safe}` ORDER BY id DESC LIMIT " . (int)$limit;
    $res2 = $db->query($sql);
    if (!$res2) {
        return [];
    }

    return $res2->fetch_all(MYSQLI_ASSOC);
}

// Messungen + Relais laden (ID DESC), danach für Diagramme in zeitliche Reihenfolge drehen
$measureDesc = tableLast($mysqli, "measurements");
$relDesc     = tableLast($mysqli, "relays");

// Für Diagramme brauchen wir "älteste → neueste"
$measure = array_reverse($measureDesc);
$rel     = array_reverse($relDesc);
?>

<!DOCTYPE html>

<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Growbox Statistik – liamslab</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">

```
<style>
    :root {
        --bg: #020617;
        --bg-2: #020617;
        --card: #0b1120;
        --card-soft: #0f172a;
        --border: #1f2937;
        --text: #e5e7eb;
        --text-muted: #9ca3af;
        --accent: #22c55e;
        --accent-soft: rgba(34,197,94,0.15);
    }

    * { box-sizing: border-box; }

    body {
        margin: 0;
        font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        background: radial-gradient(circle at top, #1e293b 0, #020617 55%);
        color: var(--text);
        min-height: 100vh;
    }

    .page {
        max-width: 1200px;
        margin: 0 auto;
        padding: 1.8rem 1.4rem 2.4rem;
    }

    header {
        display: flex;
        justify-content: space-between;
        gap: 1rem;
        align-items: center;
        margin-bottom: 1.2rem;
    }

    h1 {
        margin: 0;
        font-size: 1.4rem;
        font-weight: 600;
    }

    .sub {
        margin: 0.15rem 0 0;
        font-size: 0.85rem;
        color: var(--text-muted);
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
        color: var(--text);
        padding: 0.3rem 0.75rem;
        text-decoration: none;
        cursor: pointer;
    }

    .nav-links a:hover,
    .nav-links form button:hover {
        background: rgba(15,23,42,1);
    }

    .nav-links a.active {
        border-color: var(--accent);
        box-shadow: 0 0 0 1px rgba(34,197,94,0.35);
    }

    .card {
        background: rgba(15,23,42,0.98);
        border-radius: 1.2rem;
        border: 1px solid var(--border);
        padding: 1.1rem 1.2rem 1.2rem;
        box-shadow: 0 18px 35px rgba(15,23,42,1);
        margin-top: 1rem;
    }

    .card-title {
        font-size: 0.95rem;
        font-weight: 600;
        margin-bottom: 0.25rem;
    }

    .card-sub {
        font-size: 0.8rem;
        color: var(--text-muted);
        margin-bottom: 0.8rem;
    }

    /* Scrollbarer Diagramm-Bereich */
    .scrollwrap {
        width: 100%;
        overflow-x: auto;
        background: #020617;
        border-radius: 1rem;
        border: 1px solid #1f2937;
        padding: 0.75rem 0.75rem 0.9rem;
    }

    .scrollwrap::-webkit-scrollbar {
        height: 8px;
    }
    .scrollwrap::-webkit-scrollbar-track {
        background: #020617;
    }
    .scrollwrap::-webkit-scrollbar-thumb {
        background: #111827;
        border-radius: 999px;
    }
    .scrollwrap::-webkit-scrollbar-thumb:hover {
        background: #1f2937;
    }

    .sync {
        width: 2000px; /* wird per JS angepasst */
    }

    #sensorChart,
    #relayCanvas {
        display: block;
        width: 100%;
    }
    #sensorChart {
        margin-bottom: 0.4rem;
    }
    #relayCanvas {
        background: #020617;
        border-radius: 0.6rem;
    }

    .legend-row {
        display: flex;
        flex-wrap: wrap;
        gap: 0.6rem;
        align-items: center;
        font-size: 0.78rem;
        color: var(--text-muted);
        margin-top: 0.6rem;
    }

    .legend-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        border-radius: 999px;
        padding: 0.15rem 0.55rem;
        background: rgba(15,23,42,0.9);
        border: 1px solid #1f2937;
    }

    .legend-color {
        width: 10px;
        height: 10px;
        border-radius: 3px;
    }

    /* Tabelle */
    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 0.5rem;
        font-size: 0.8rem;
    }

    th, td {
        padding: 0.4rem 0.5rem;
        border-bottom: 1px solid #1f2937;
        white-space: nowrap;
    }

    th {
        color: var(--text-muted);
        font-weight: 600;
        text-align: left;
    }

    tr:nth-child(even) td {
        background: rgba(15,23,42,0.9);
    }

    .badge {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        border-radius: 999px;
        padding: 0.1rem 0.5rem;
        font-size: 0.75rem;
        background: rgba(15,23,42,0.9);
        border: 1px solid #1f2937;
        color: var(--text-muted);
    }

    .badge-dot {
        width: 6px;
        height: 6px;
        border-radius: 999px;
        background: var(--accent);
    }

    .empty-note {
        font-size: 0.8rem;
        color: var(--text-muted);
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns"></script>
```

</head>
<body>
<div class="page">
    <header>
        <div>
            <h1>Growbox Statistik</h1>
            <p class="sub">
                Zeitverlauf der Messwerte &amp; Relais-Zustände.<br>
                Daten kommen aus der Tabelle <code>measurements</code>, die vom ESP über
                <code>/growbox/api/data.php</code> beschrieben wird.
            </p>
        </div>
        <div class="nav-links">
            <a href="/">← Startseite</a>
            <a href="/growbox/dashboard/dashboard.php">Dashboard</a>
            <a href="/growbox/dashboard/settings.php">Einstellungen</a>
            <a href="/growbox/dashboard/stats.php" class="active">Statistik</a>
            <a href="/growbox/api/data.php?latest=1" target="_blank">API&nbsp;Latest</a>
            <a href="/growbox/api/logic.php" target="_blank">API&nbsp;Logic</a>
            <form action="/growbox/dashboard/logout.php" method="post" style="margin:0;">
                <button type="submit">Logout</button>
            </form>
        </div>
    </header>

```
<section class="card">
    <div class="card-title">Diagramme</div>
    <div class="card-sub">
        Oben: Verlauf von Temperatur &amp; Luftfeuchte (Linien).<br>
        Unten: Schaltzustände der Relais (farbige Balken), zeitlich synchron.
    </div>

    <?php if (!$measure): ?>
        <p class="empty-note">
            Keine Messwerte in der Datenbank. Sobald der ESP32 Messdaten an
            <code>/growbox/api/data.php</code> sendet, erscheinen hier die Verläufe.
        </p>
    <?php else: ?>
        <div class="scrollwrap">
            <div class="sync">
                <canvas id="sensorChart" height="260"></canvas>
                <canvas id="relayCanvas" height="120"></canvas>
            </div>
        </div>

        <div class="legend-row">
            <span class="badge">
                <span class="badge-dot"></span>
                <span><?=count($measure)?> Messpunkte (ältester links, neuester rechts)</span>
            </span>
            <div class="legend-pill">
                <span class="legend-color" style="background:#f97316;"></span>
                <span>Heizung (Relais)</span>
            </div>
            <div class="legend-pill">
                <span class="legend-color" style="background:#22c55e;"></span>
                <span>Umluft</span>
            </div>
            <div class="legend-pill">
                <span class="legend-color" style="background:#a855f7;"></span>
                <span>Abluft</span>
            </div>
            <div class="legend-pill">
                <span class="legend-color" style="background:#e5e7eb;"></span>
                <span>Licht</span>
            </div>
        </div>
    <?php endif; ?>
</section>

<section class="card">
    <div class="card-title">Messwerte (Rohdaten)</div>
    <div class="card-sub">
        Die letzten gespeicherten Messwerte aus der Tabelle <code>measurements</code>.<br>
        Hinweis: ID&nbsp;1 ist der älteste Wert – hier werden die <strong>neuesten zuerst</strong> angezeigt.
    </div>

    <?php if (!$measureDesc): ?>
        <p class="empty-note">Keine Werte vorhanden.</p>
    <?php else: ?>
        <div style="overflow-x:auto;">
            <table>
                <tr>
                    <th>ID</th>
                    <th>Zeit</th>
                    <th>Temp&nbsp;[°C]</th>
                    <th>RH&nbsp;[%]</th>
                    <th>B1 (roh)</th>
                    <th>B2 (roh)</th>
                    <th>B3 (roh)</th>
                </tr>
                <?php foreach ($measureDesc as $m): // DESC: neuester zuerst ?>
                    <tr>
                        <td><?=$m['id']?></td>
                        <td><?=$m['created_at']?></td>
                        <td><?=$m['temperature']?></td>
                        <td><?=$m['humidity']?></td>
                        <td><?=$m['soil1']?></td>
                        <td><?=$m['soil2']?></td>
                        <td><?=$m['soil3']?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    <?php endif; ?>
</section>
```

</div>

<?php if ($measure): ?>

<script>
const meas = <?=json_encode($measure, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP)?>;
const rel  = <?=json_encode($rel,    JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP)?>;

// *********************
// Sensor-Linien-Chart
// *********************
if (meas.length) {
    // Zeitachse als echte Date-Objekte aus created_at (Format "YYYY-MM-DD HH:MM:SS")
    const times = meas.map(m => new Date(String(m.created_at).replace(' ', 'T')));
    const temp  = meas.map(m => m.temperature !== null ? Number(m.temperature) : null);
    const hum   = meas.map(m => m.humidity    !== null ? Number(m.humidity)    : null);

    const ctx = document.getElementById("sensorChart");
    const sensorChart = new Chart(ctx, {
        type: "line",
        data: {
            labels: times,
            datasets: [
                {
                    label: "Temperatur [°C]",
                    data: temp,
                    borderColor: "#f97373",
                    backgroundColor: "rgba(248,113,113,0.12)",
                    pointRadius: 0,
                    borderWidth: 1.7,
                    tension: 0.3
                },
                {
                    label: "Luftfeuchte [%]",
                    data: hum,
                    borderColor: "#38bdf8",
                    backgroundColor: "rgba(56,189,248,0.12)",
                    pointRadius: 0,
                    borderWidth: 1.7,
                    tension: 0.3
                }
            ]
        },
        options: {
            responsive: false,
            animation: false,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    labels: { color: "#e5e7eb", font: { size: 11 } }
                }
            },
            scales: {
                x: {
                    type: "time",
                    time: { unit: "hour" },
                    grid: { color: "rgba(148,163,184,0.25)" },
                    ticks: { color: "#9ca3af", maxRotation: 0, autoSkip: true }
                },
                y: {
                    grid: { color: "rgba(148,163,184,0.18)" },
                    ticks: { color: "#9ca3af" }
                }
            }
        }
    });

    // *********************
    // Horizontales Scrolling
    // *********************
    const pxPerPoint = 22;           // Skalierung: 1 Messpunkt = 22px
    const totalPoints = Math.max(meas.length, rel.length || 0);
    const totalW = Math.max(totalPoints * pxPerPoint, 600);
    document.querySelector(".sync").style.width = totalW + "px";

    // *********************
    // Relais-Balken (Canvas)
    // *********************
    const relayKeys = [
        { key: "heat",    color: "#f97316", label: "Heizung" },
        { key: "fan",     color: "#22c55e", label: "Umluft"  },
        { key: "exhaust", color: "#a855f7", label: "Abluft"  },
        { key: "light",   color: "#e5e7eb", label: "Licht"   },
    ];

    const cv  = document.getElementById("relayCanvas");
    const ctxR = cv.getContext("2d");

    function drawRelay() {
        cv.width  = totalW;
        // Höhe bleibt wie im HTML gesetzt
        ctxR.clearRect(0, 0, cv.width, cv.height);

        const rowH = 26;
        cons
