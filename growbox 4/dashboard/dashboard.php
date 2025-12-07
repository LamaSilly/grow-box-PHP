<?php
// growbox/dashboard/dashboard.php
session_start();
require __DIR__ . '/config.php';

if (empty($_SESSION['growbox_logged_in'])) {
    header('Location: /growbox/dashboard/login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Growbox Dashboard – liamslab</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body{margin:0;font-family:system-ui,sans-serif;background:radial-gradient(circle at top,#1e293b 0,#020617 55%);color:#e5e7eb;min-height:100vh;}
        .page{max-width:1100px;margin:0 auto;padding:1.8rem 1.4rem 2.4rem;}
        header{display:flex;justify-content:space-between;gap:1rem;align-items:center;margin-bottom:0.8rem;}
        h1{margin:0;font-size:1.4rem;}
        .sub{font-size:0.85rem;color:#9ca3af;}
        .nav-links{display:flex;gap:0.6rem;flex-wrap:wrap;font-size:0.8rem;}
        .nav-links a,.nav-links form button{border-radius:999px;border:1px solid #374151;background:rgba(15,23,42,0.95);color:#e5e7eb;padding:0.3rem 0.7rem;text-decoration:none;cursor:pointer;}
        .nav-links a:hover,.nav-links form button:hover{background:rgba(15,23,42,1);}
        .health-banner{margin-bottom:1.2rem;padding:0.55rem 0.9rem;border-radius:0.9rem;font-size:0.82rem;display:flex;align-items:center;gap:0.5rem;border:1px solid transparent;}
        .health-dot{width:8px;height:8px;border-radius:999px;}
        .health-ok{background:rgba(22,163,74,0.12);border-color:rgba(34,197,94,0.5);}
        .health-ok .health-dot{background:#22c55e;}
        .health-warn{background:rgba(234,179,8,0.12);border-color:rgba(234,179,8,0.6);}
        .health-warn .health-dot{background:#eab308;}
        .health-crit{background:rgba(220,38,38,0.14);border-color:rgba(239,68,68,0.8);}
        .health-crit .health-dot{background:#ef4444;}
        .health-label{font-weight:600;font-size:0.8rem;}
        .health-text{font-size:0.8rem;}
        .grid{display:grid;grid-template-columns:1.5fr 1.5fr 1fr;gap:1.4rem;}
        @media(max-width:900px){.grid{grid-template-columns:1fr;}}
        .card{background:rgba(15,23,42,0.98);border-radius:1.3rem;border:1px solid #1f2937;padding:1.1rem 1.2rem 1.2rem;box-shadow:0 18px 35px rgba(15,23,42,1);}
        .card-title{font-size:0.95rem;font-weight:600;margin-bottom:0.25rem;}
        .card-sub{font-size:0.8rem;color:#9ca3af;margin-bottom:0.8rem;}
        .metric-row{display:flex;justify-content:space-between;font-size:0.9rem;margin-bottom:0.2rem;}
        .metric-label{color:#9ca3af;}
        .metric-value{font-weight:500;}
        .metric-note{margin-top:0.4rem;font-size:0.78rem;color:#9ca3af;}
        .steps{font-size:0.8rem;color:#9ca3af;list-style:disc;padding-left:1.2rem;margin:0;}
        .pill{display:inline-flex;align-items:center;gap:0.3rem;padding:0.15rem 0.6rem;border-radius:999px;border:1px solid #374151;font-size:0.78rem;margin-top:0.2rem;}
        .pill-dot{width:6px;height:6px;border-radius:999px;background:#22c55e;}
        .relays-list{list-style:none;padding:0;margin:0.1rem 0 0;font-size:0.85rem;}
        .relays-list li{display:flex;justify-content:space-between;margin-bottom:0.18rem;}
        .relay-on{color:#bbf7d0;}
        .relay-off{color:#9ca3af;}
        .override-grid{margin-top:0.4rem;font-size:0.8rem;}
        .override-row{display:grid;grid-template-columns:1.1fr 1.4fr;gap:0.4rem;align-items:center;margin-bottom:0.25rem;}
        .override-select{width:100%;border-radius:0.7rem;border:1px solid #374151;background:#020617;color:#e5e7eb;padding:0.2rem 0.5rem;font-size:0.8rem;}
        .btn-small{margin-top:0.7rem;border-radius:999px;border:none;background:linear-gradient(135deg,#22c55e,#16a34a);color:#022c22;font-weight:600;padding:0.35rem 0.7rem;cursor:pointer;font-size:0.8rem;width:100%;}
        .status-text{font-size:0.8rem;color:#9ca3af;margin-top:0.4rem;}
        .danger-note{margin-top:0.3rem;font-size:0.75rem;color:#fca5a5;}
        .danger-outline{border-color:rgba(248,113,113,0.7);}
    </style>
</head>
<body>
<div class="page">
    <header>
        <div>
            <h1>Growbox Dashboard</h1>
            <p class="sub">Live-Daten &amp; Relay-Modi (Automatik, Intervall, Notfall).</p>
        </div>
        <div class="nav-links">
            <a href="/">← Startseite</a>
            <a href="/growbox/dashboard/settings.php">Einstellungen</a>
            <a href="/growbox/dashboard/stats.php">Statistik</a>
            <a href="/growbox/api/logic.php" target="_blank">API JSON</a>
            <form action="/growbox/dashboard/logout.php" method="post" style="margin:0;">
                <button type="submit">Logout</button>
            </form>
        </div>
    </header>

    <div id="health-banner" class="health-banner health-ok">
        <span class="health-dot"></span>
        <span class="health-label">Systemstatus</span>
        <span id="health-text" class="health-text">Wird geladen…</span>
    </div>

    <div class="grid">
        <!-- Messwerte -->
        <section class="card">
            <div class="card-title">Messwerte</div>
            <div class="card-sub">Letzter Datensatz vom ESP32 (Serverzeit).</div>

            <div class="metric-row">
                <span class="metric-label">Temperatur:</span>
                <span class="metric-value" id="temp">–</span>
            </div>
            <div class="metric-row">
                <span class="metric-label">Luftfeuchte:</span>
                <span class="metric-value" id="hum">–</span>
            </div>
            <div class="metric-row">
                <span class="metric-label">Bodenfeuchte (Ø):</span>
                <span class="metric-value" id="soil">–</span>
            </div>
            <div class="metric-row">
                <span class="metric-label">Zuletzt aktualisiert:</span>
                <span class="metric-value" id="created">–</span>
            </div>
            <div class="metric-row">
                <span class="metric-label">Alter der Messung:</span>
                <span class="metric-value" id="age">–</span>
            </div>

            <div class="metric-note" id="sensor-note">Sensorstatus: –</div>

            <div class="pill" style="margin-top: 0.8rem;">
                <span class="pill-dot"></span>
                <span id="status-text">Status: –</span>
            </div>
        </section>

        <!-- Relaiszustände -->
        <section class="card">
            <div class="card-title">Relaiszustände</div>
            <div class="card-sub">Vorher / Nachher laut Steuerlogik und Relay-Modi.</div>

            <ul class="relays-list" id="relays-before">
                <li><span>Heizung</span><span>–</span></li>
                <li><span>Umluft</span><span>–</span></li>
                <li><span>Abluft</span><span>–</span></li>
                <li><span>Licht</span><span>–</span></li>
            </ul>

            <hr style="border:0;border-top:1px solid #1f2937;margin:0.7rem 0;">

            <ul class="relays-list" id="relays-after">
                <li><span>Heizung</span><span>–</span></li>
                <li><span>Umluft</span><span>–</span></li>
                <li><span>Abluft</span><span>–</span></li>
                <li><span>Licht</span><span>–</span></li>
            </ul>

            <p class="status-text" id="steps-info">Entscheidungs-Logik wird geladen…</p>
        </section>

        <!-- Relay-Modi -->
        <section class="card danger-outline">
            <div class="card-title">Relay-Modi (pro Ausgang)</div>
            <div class="card-sub">
                <strong>Automatik</strong>: Sensor-Logik + Intervalle/Photoperiode<br>
                <strong>Intervall</strong>: nur Intervall/Zeitplan, Sensorwerte werden ignoriert<br>
                <strong>Notfall AUS/EIN</strong>: Relais dauerhaft AUS oder EIN, alles andere ignoriert<br>
                <em>Hinweis:</em> Änderungen an den Modis werden sofort automatisch gespeichert.
            </div>

            <form id="override-form">
                <div class="override-grid">
                    <div style="font-size:0.78rem;color:#9ca3af;margin-bottom:0.25rem;">
                        <span style="display:inline-block;width:40%;">Relais</span>
                        <span style="display:inline-block;width:60%;">Modus</span>
                    </div>

                    <div class="override-row">
                        <span>Heizung</span>
                        <select name="override_heat_mode" class="override-select" id="ov-heat-mode">
                            <option value="sensor">Automatik</option>
                            <option value="interval">Intervall</option>
                            <option value="blocked_off">Notfall: AUS (blockiert)</option>
                            <option value="blocked_on">Notfall: EIN (blockiert)</option>
                        </select>
                    </div>

                    <div class="override-row">
                        <span>Umluft</span>
                        <select name="override_fan_mode" class="override-select" id="ov-fan-mode">
                            <option value="sensor">Automatik (Sensor &gt; Intervall)</option>
                            <option value="interval">Intervall (Sensor ignoriert)</option>
                            <option value="blocked_off">Notfall: AUS (blockiert)</option>
                            <option value="blocked_on">Notfall: EIN (blockiert)</option>
                        </select>
                    </div>

                    <div class="override-row">
                        <span>Abluft</span>
                        <select name="override_exhaust_mode" class="override-select" id="ov-exhaust-mode">
                            <option value="sensor">Automatik (Sensor &gt; Intervall)</option>
                            <option value="interval">Intervall (Sensor ignoriert)</option>
                            <option value="blocked_off">Notfall: AUS (blockiert)</option>
                            <option value="blocked_on">Notfall: EIN (blockiert)</option>
                        </select>
                    </div>

                    <div class="override-row">
                        <span>Licht</span>
                        <select name="override_light_mode" class="override-select" id="ov-light-mode">
                            <option value="sensor">Automatik (Photoperiode)</option>
                            <option value="interval">Intervall / Zeitplan</option>
                            <option value="blocked_off">Notfall: AUS (blockiert)</option>
                            <option value="blocked_on">Notfall: EIN (blockiert)</option>
                        </select>
                    </div>
                </div>

                <button type="submit" class="btn-small">Manuell speichern</button>
            </form>

            <div class="status-text" id="mode-status">Noch keine Änderungen gesendet.</div>
            <div class="danger-note">
                Notfall-Einstellungen umgehen die normale Logik komplett.
            </div>
        </section>
    </div>
</div>

<script>
function relayLabel(val){
    if(val===true||val===1||val==='1')return'<span class="relay-on">AN</span>';
    if(val===false||val===0||val==='0')return'<span class="relay-off">AUS</span>';
    return'<span class="relay-off">–</span>';
}
function setHealth(status,text){
    const banner=document.getElementById('health-banner');
    const label=banner.querySelector('.health-label');
    const textEl=document.getElementById('health-text');
    banner.classList.remove('health-ok','health-warn','health-crit');
    if(status==='ok'){banner.classList.add('health-ok');label.textContent='System OK';}
    else if(status==='warn'){banner.classList.add('health-warn');label.textContent='Hinweis';}
    else if(status==='crit'){banner.classList.add('health-crit');label.textContent='Warnung';}
    else{label.textContent='Systemstatus';}
    textEl.textContent=text;
}
async function fetchData(){
    try{
        const res=await fetch('/growbox/api/logic.php');
        const data=await res.json();

        document.getElementById('status-text').textContent='Status: '+(data.status||'–');

        if(data.measurement){
            const t=data.measurement.temperature;
            const h=data.measurement.humidity;
            document.getElementById('temp').textContent=(t??'–')+(t!=null?' °C':'');
            document.getElementById('hum').textContent=(h??'–')+(h!=null?' %':'');
            let soilText='–';
            if(data.soil_avg!=null){
                const raw=Number(data.soil_avg);
                const rawStr=raw.toFixed(1);
                if(data.soil_avg_percent!=null){
                    const p=Number(data.soil_avg_percent);
                    soilText=p.toFixed(1)+' % (Roh: '+rawStr+')';
                }else{
                    soilText=rawStr+' (Rohwert)';
                }
            }
            document.getElementById('soil').textContent=soilText;
            document.getElementById('created').textContent=data.measurement.created_at??'–';
        }else{
            document.getElementById('temp').textContent='–';
            document.getElementById('hum').textContent='–';
            document.getElementById('soil').textContent='–';
            document.getElementById('created').textContent='–';
        }

        const ageSec=data.measurement_age_sec;
        const ageHuman=data.measurement_age_human;
        const sensorStatus=data.sensor_status||'unknown';
        document.getElementById('age').textContent=ageHuman||'–';
        document.getElementById('sensor-note').textContent='Sensorstatus: '+sensorStatus;

        let healthState='ok';
        let healthText='Letzte Messung: '+(ageHuman||'unbekannt');
        if(ageSec==null){
            healthState='crit';
            healthText='Keine Messung vorhanden. Prüfe ESP32 oder API.';
        }else if(ageSec>600){
            healthState='crit';
            healthText='Letzte Messung älter als 10 min ('+ageHuman+').';
        }else if(ageSec>180){
            healthState='warn';
            healthText='Letzte Messung älter als 3 min ('+ageHuman+').';
        }
        if(sensorStatus&&sensorStatus.startsWith('missing')){
            if(healthState==='ok')healthState='warn';
            healthText+=' | Fehlende Werte: '+sensorStatus.replace('missing: ','');
        }
        setHealth(healthState,healthText);

        const before=data.before||{};
        const after=data.after||{};
        document.getElementById('relays-before').innerHTML=`
            <li><span>Heizung</span><span>${relayLabel(before.heat)}</span></li>
            <li><span>Umluft</span><span>${relayLabel(before.fan)}</span></li>
            <li><span>Abluft</span><span>${relayLabel(before.exhaust)}</span></li>
            <li><span>Licht</span><span>${relayLabel(before.light)}</span></li>
        `;
        document.getElementById('relays-after').innerHTML=`
            <li><span>Heizung</span><span>${relayLabel(after.heat)}</span></li>
            <li><span>Umluft</span><span>${relayLabel(after.fan)}</span></li>
            <li><span>Abluft</span><span>${relayLabel(after.exhaust)}</span></li>
            <li><span>Licht</span><span>${relayLabel(after.light)}</span></li>
        `;

        const steps=data.steps||[];
        document.getElementById('steps-info').innerHTML=
            steps.length
                ? 'Logik-Steps:<br><ul class="steps">'+steps.map(s=>'<li>'+s+'</li>').join('')+'</ul>'
                : 'Keine Logik-Details übermittelt.';

        // Relay-Modi aus JSON in die Selects mappen
        const ov=data.overrides||{};
        function applyMode(relay,selectId){
            const o=ov[relay];
            if(!o)return;
            const sel=document.getElementById(selectId);
            if(!sel)return;
            let v='sensor';
            if(o.mode==='sensor' || o.mode==='interval'){
                v=o.mode;
            }else if(o.mode==='blocked'){
                v=o.state? 'blocked_on':'blocked_off';
            }
            sel.value=v;
        }
        applyMode('heat','ov-heat-mode');
        applyMode('fan','ov-fan-mode');
        applyMode('exhaust','ov-exhaust-mode');
        applyMode('light','ov-light-mode');

    }catch(e){
        document.getElementById('status-text').textContent='Status: Fehler beim Laden';
        setHealth('crit','Fehler beim Laden der Logik-API.');
    }
}

// gemeinsame Funktion: Modi an API schicken
async function sendOverride(){
    const f=document.getElementById('override-form');
    const params=new URLSearchParams();
    params.set('override_update','1');
    params.set('override_heat_mode',f['override_heat_mode'].value);
    params.set('override_fan_mode',f['override_fan_mode'].value);
    params.set('override_exhaust_mode',f['override_exhaust_mode'].value);
    params.set('override_light_mode',f['override_light_mode'].value);

    try{
        const res=await fetch('/growbox/api/logic.php?'+params.toString());
        const data=await res.json();
        document.getElementById('mode-status').textContent='Modi gespeichert.';
        fetchData();
    }catch(err){
        document.getElementById('mode-status').textContent='Fehler beim Speichern.';
    }
}

// Button: manuell speichern (optional)
document.getElementById('override-form').addEventListener('submit',function(e){
    e.preventDefault();
    document.getElementById('mode-status').textContent='Speichere Änderungen…';
    sendOverride();
});

// Auto-Save: bei jeder Änderung eines Selects
document.querySelectorAll('.override-select').forEach(function(sel){
    sel.addEventListener('change',function(){
        document.getElementById('mode-status').textContent='Änderung wird gespeichert…';
        sendOverride();
    });
});

fetchData();
setInterval(fetchData,5000);
</script>
</body>
</html>
