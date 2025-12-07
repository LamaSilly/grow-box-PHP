<?php
require_once __DIR__ . '/config.php';
require_login();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Growbox Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <style>
        body {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: #05060a;
            color: #f3f3f3;
            margin: 0;
            padding: 20px;
        }
        h1 { margin-bottom: 1rem; }

        .grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            gap: 1rem;
            max-width: 900px;
            margin-top: 10px;
        }
        @media (min-width: 800px) {
            .grid { grid-template-columns: 2fr 1fr; }
        }

        .card {
            background: #14141e;
            border-radius: 14px;
            padding: 16px 18px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.35);
            border: 1px solid #29293a;
        }
        .label {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #9a9ab0;
        }
        .value {
            font-size: 1.8rem;
            margin-top: 4px;
        }
        .value-small { font-size: 0.9rem; }
        .badge {
            display: inline-block;
            padding: 0.25rem 0.7rem;
            border-radius: 999px;
            font-size: 0.75rem;
            background: #26263a;
            color: #d0d0ff;
            margin-top: 6px;
        }
        .soil-list {
            margin-top: 4px;
            line-height: 1.4;
        }
        .status {
            margin-top: 1rem;
            font-size: 0.9rem;
            color: #a0a0c0;
        }
        .relay-on {
            color: #9fff9f;
        }
        .relay-off {
            color: #ffb0b0;
        }
        .value-small {
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/nav.php'; ?>

<h1>Dashboard</h1>

<div class="grid">
    <div>
        <div class="card">
            <div class="label">Gerät</div>
            <div class="value" id="device_id">—</div>
            <div class="badge" id="last_update">Kein Datensatz</div>
        </div>

        <div class="card">
            <div class="label">Temperatur</div>
            <div class="value" id="temperature">— °C</div>

            <div class="label" style="margin-top: 12px;">Luftfeuchte</div>
            <div class="value" id="humidity">— %</div>
        </div>

        <div class="card">
            <div class="label">Bodenfeuchte</div>
            <div class="soil-list value-small" id="soil_values">
                Soil1: — %<br>
                Soil2: — %<br>
                Soil3: — %
            </div>
        </div>
    </div>

    <div>
        <div class="card">
            <div class="label">Relais-Zustand (aktuell)</div>
            <div class="value-small">
                Heizung: <span id="rel_heat" class="relay-off">AUS</span><br>
                Umluft:  <span id="rel_fan" class="relay-off">AUS</span><br>
                Abluft:  <span id="rel_exhaust" class="relay-off">AUS</span><br>
                Licht:   <span id="rel_light" class="relay-off">AUS</span>
            </div>
        </div>

        <div class="card">
            <div class="label">Hinweis</div>
            <div class="value-small" id="mode_info">
                Einstellungen für Automatik findest du unter <strong>Einstellungen</strong> im Menü.
            </div>
        </div>
    </div>
</div>

<div class="status" id="status">
    Warte auf Daten…
</div>

<script>
    function setRelaySpan(id, on) {
        const el = document.getElementById(id);
        if (!el) return;
        if (on) {
            el.textContent = 'AN';
            el.classList.add('relay-on');
            el.classList.remove('relay-off');
        } else {
            el.textContent = 'AUS';
            el.classList.add('relay-off');
            el.classList.remove('relay-on');
        }
    }

    async function loadLatestMeasurement() {
        const statusEl = document.getElementById('status');
        try {
            const res = await fetch('../api/latest.php?_=' + Date.now());
            const data = await res.json();

            if (data.error) {
                statusEl.textContent = 'Keine Messdaten: ' + data.error;
                return;
            }

            document.getElementById('device_id').textContent =
                data.device_id || '—';

            const t = (data.temperature !== null && data.temperature !== undefined)
                ? Number(data.temperature).toFixed(1) + ' °C'
                : '— °C';
            document.getElementById('temperature').textContent = t;

            const h = (data.humidity !== null && data.humidity !== undefined)
                ? Number(data.humidity).toFixed(1) + ' %'
                : '— %';
            document.getElementById('humidity').textContent = h;

            const s1 = (data.soil1 !== null && data.soil1 !== undefined)
                ? data.soil1 + ' %'
                : '— %';
            const s2 = (data.soil2 !== null && data.soil2 !== undefined)
                ? data.soil2 + ' %'
                : '— %';
            const s3 = (data.soil3 !== null && data.soil3 !== undefined)
                ? data.soil3 + ' %'
                : '— %';

            document.getElementById('soil_values').innerHTML =
                'Soil1: ' + s1 + '<br>' +
                'Soil2: ' + s2 + '<br>' +
                'Soil3: ' + s3;

            const lu = document.getElementById('last_update');
            if (data.created_at) {
                lu.textContent = 'Letztes Update: ' + data.created_at;
            } else {
                lu.textContent = 'Zeitstempel unbekannt';
            }

            statusEl.textContent = 'Live-Daten werden alle 5 Sekunden aktualisiert.';
        } catch (e) {
            statusEl.textContent = 'Fehler beim Laden der Messdaten: ' + e;
        }
    }

    async function loadRelayState() {
        try {
            const res = await fetch('../api/control.php?_=' + Date.now());
            const state = await res.json();

            setRelaySpan('rel_heat', !!state.heat);
            setRelaySpan('rel_fan', !!state.fan);
            setRelaySpan('rel_exhaust', !!state.exhaust);
            setRelaySpan('rel_light', !!state.light);
        } catch (e) {
            console.error('Fehler beim Laden des Relaiszustands:', e);
        }
    }

    async function loadModeInfo() {
        try {
            const res = await fetch('../api/settings.php?_=' + Date.now());
            const s = await res.json();
            const el = document.getElementById('mode_info');
            if (s.error) {
                el.textContent = 'Einstellungen konnten nicht geladen werden: ' + s.error;
                return;
            }
            el.innerHTML = 'Modus: <strong>' + (s.mode || 'unbekannt') +
                '</strong><br>Grenzwerte kannst du unter <strong>Einstellungen</strong> bearbeiten.';
        } catch (e) {
            console.error('Fehler beim Laden der Einstellungen:', e);
        }
    }

    loadLatestMeasurement();
    loadRelayState();
    loadModeInfo();
    setInterval(loadLatestMeasurement, 5000);
    setInterval(loadRelayState, 7000);
</script>
</body>
</html>
