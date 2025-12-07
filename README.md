# grow-box-PHP

Growbox Steuerung – liamslab

Webbasierte Steuerung und Visualisierung einer Growbox mit ESP32, 4 Relais (Heizung, Umluft, Abluft, Licht), DHT22 und Bodenfeuchtesensoren.
Das Projekt besteht aus:

einem ESP32-Sketch (läuft auf dem Mikrocontroller),

einem PHP-Backend mit REST-ähnlicher API,

einer MySQL-Datenbank,

einem Dashboard zur Steuerung und Statistik.

1. Projektüberblick
Ziel

Klimadaten (Temperatur, Luftfeuchte, Bodenfeuchte) kontinuierlich erfassen,

4 Relais (Heizung, Umluft, Abluft, Licht) über Weboberfläche & Logik steuern,

alle Messdaten und Schaltzustände in einer Datenbank speichern,

Verläufe in Diagrammen und Tabellen übersichtlich anzeigen.

Komponenten

ESP32

DHT22 am definierten Pin (z.B. GPIO 14) für Temperatur & Luftfeuchte

Bodenfeuchtesensor(en) an ADC-Pins (soil1–3)

HTTP-Client: sendet Daten zyklisch an <DOMAIN>/growbox/api/data.php

optional: fragt Steuerbefehle ab (/growbox/api/control.php / /logic.php)

Webserver (z.B. netcup Webhosting 2000)

PHP 8.x

MySQL/MariaDB

Projektordner: public_html/growbox/ (oder ähnlich)

Datenbank

Tabelle measurements: Messwerte

Tabelle relays: Zustände der Relais

optional: weitere Tabellen (z.B. settings, users), je nach Ausbau

2. Dateistruktur (Stand jetzt)

Dashboard / Frontend

growbox/dashboard/index.php
Einstieg bzw. Redirect auf dashboard.php (je nachdem, wie du es eingerichtet hast)

growbox/dashboard/dashboard.php
Hauptansicht (aktueller Zustand, Buttons, Live-Infos)

growbox/dashboard/stats.php
Statistikseite für Verläufe und Tabellen (aktuell ausführlich überarbeitet):

Sensorverlauf (Chart.js)

Relais-Timeline (Canvas mit farbigen Balken)

Messwerte-Tabelle (neueste Werte oben)

eventuell weitere Infos

growbox/dashboard/settings.php
Einstellungen / Grenzwerte (abhängig von deiner aktuellen Logik)

growbox/dashboard/login.php
Login-Formular (setzt Session growbox_logged_in)

growbox/dashboard/logout.php
Session löschen & Logout

growbox/dashboard/config.php
Konfiguration für Dashboard:

Session-Settings

ggf. feste Admin-Credentials o.ä.

growbox/dashboard/nav.php
Navigationsleiste (falls ausgelagert)

API / Backend

growbox/api/db_config.php
Aufbau der Datenbankverbindung, z.B.:

$mysqli = new mysqli($host, $user, $pass, $db);

growbox/api/data.php
Sensor-Daten Endpoint (vom ESP32 aufgerufen):
Erwartet per GET/POST u.a.:

temperature

humidity

soil1, soil2, soil3

und schreibt diese in die Tabelle measurements.
Gibt JSON-Response zurück (z.B. { "status": "ok" }).

growbox/api/latest.php (oder measure.php)
Gibt den letzten Messwert als JSON aus:
z.B. /growbox/api/latest.php → { "id": 123, "temperature": 21.3, ... }
Wird vom Dashboard genutzt, um Live-Infos darzustellen.

growbox/api/control.php
Endpoint zum Setzen oder Abfragen der Relaiszustände.
Typisch:

ESP fragt: „Was soll ich schalten?“

Backend gibt JSON mit heat, fan, exhaust, light zurück.

growbox/api/logic.php
Berechnet aus:

aktuellem Modus (z.B. manual, später auto/eco)

letzten Messwerten (Temp, RH, Boden)

ggf. Settings (Grenzwerte)

die Ziel-Relaiszustände und speichert diese in relays.
Gibt zur Debug-Hilfe JSON zurück, z.B.:

{
  "status": "ok",
  "mode": "manual",
  "steps": [
    "Aktueller Modus: manual",
    "Modus 'manual': keine Änderungen, Relais bleiben wie sie sind."
  ],
  "measurement": { ... },
  "before": { "heat": true, "fan": true, "exhaust": true, "light": true },
  "after":  { "heat": true, "fan": true, "exhaust": true, "light": true }
}

growbox/api/auth.php
Ggf. Hilfsfunktionen oder API-Auth (je nach Inhalt).

3. Datenbankstruktur (relevant für Stats)
Tabelle: measurements

Wird von data.php beschrieben und von stats.php und anderen Seiten gelesen.

Verwendete Spalten (mindestens):

id (INT, AUTO_INCREMENT, PRIMARY KEY)

temperature (FLOAT/DOUBLE, °C)

humidity (FLOAT/DOUBLE, %)

soil1 (FLOAT/INT, Bodenfeuchte Sensor 1, Roh- oder %-Werte)

soil2 (FLOAT/INT)

soil3 (FLOAT/INT)

created_at (DATETIME / TIMESTAMP)

Wichtig: ID 1 ist der älteste Eintrag.
Für Diagramme werden die Daten in stats.php so sortiert, dass der älteste links, der neueste rechts ist.
In der Tabelle werden die neueren Einträge zuerst angezeigt (absteigend nach id).

Tabelle: relays

Wird von logic.php / control.php beschrieben.

Verwendete Spalten:

id (INT, AUTO_INCREMENT, PRIMARY KEY)

heat (TINYINT(1) / BOOL; 0 = AUS, 1 = AN)

fan (TINYINT(1); Umluft)

exhaust (TINYINT(1); Abluft)

light (TINYINT(1); Licht)

created_at (DATETIME / TIMESTAMP)

4. Statistikseite (stats.php)

Die aktuelle stats.php macht im Wesentlichen:

4.1 Laden der Daten
$measureDesc = tableLast($mysqli, "measurements"); // neueste zuerst
$relDesc     = tableLast($mysqli, "relays");

$measure = array_reverse($measureDesc); // für Diagramme: älteste → neueste
$rel     = array_reverse($relDesc);

tableLast():

prüft zuerst mit SHOW TABLES LIKE 'name', ob die Tabelle existiert

holt dann per SELECT * FROM name ORDER BY id DESC LIMIT ... die letzten n Einträge

4.2 Sensor-Diagramm

oben auf der Seite im Bereich „Diagramme“:

Chart.js Line-Chart

X-Achse: Zeit (created_at, in Date-Objekte geparst)

Y-Achse: Temperatur und Luftfeuchte

const times = meas.map(m => new Date(String(m.created_at).replace(' ', 'T')));
const temp  = meas.map(m => m.temperature !== null ? Number(m.temperature) : null);
const hum   = meas.map(m => m.humidity    !== null ? Number(m.humidity)    : null);

X-Achse als type: "time" mit chartjs-adapter-date-fns

4.3 Relais-Timeline (4 Zeilen, Balken)

Direkt unter dem Sensor-Chart:

Canvas #relayCanvas

Jede der 4 Relais-Arten (heat, fan, exhaust, light) ist eine eigene Zeile.

Es wird pro Datenpunkt ein Segment gezeichnet:

Wenn Relais = 1: farbiger Balken (z.B. orange für Heizung)

Wenn Relais = 0: kein Balken (Segment bleibt transparent)

Die Zuordnung:

const relayKeys = [
    { key: "heat",    color: "#f97316", label: "Heizung" },
    { key: "fan",     color: "#22c55e", label: "Umluft"  },
    { key: "exhaust", color: "#a855f7", label: "Abluft"  },
    { key: "light",   color: "#e5e7eb", label: "Licht"   },
];

Auf diese Weise ergibt sich eine sehr gut lesbare zeitliche Balkenansicht:

horizontal: Zeit (synchron zum Sensor-Chart oben)

vertikal: die 4 Relaiskanäle übereinander

4.4 Messwerte-Tabelle

Im unteren Card-Block „Messwerte (Rohdaten)“:

Daten aus $measureDesc – also ID DESC → neueste zuerst

gezeigt werden:

ID

Zeit (created_at)

Temperatur [°C]

Luftfeuchte [%]

Boden1–3 (aktuell als Rohwerte, z.B. ADC)

Damit siehst du direkt oben in der Tabelle den aktuellen Zustand.

5. API / ESP32-Anbindung (Konzept)
5.1 data.php – Messwerte senden

Der ESP32 sendet in einem festgelegten Intervall (z.B. alle 60 s) ein HTTP-Request an:

POST https://DEINE-DOMAIN/growbox/api/data.php

Mögliche Payload (Beispiel):

temperature=21.3
humidity=63.5
soil1=512
soil2=480
soil3=495

oder JSON (abhängig davon, wie du data.php implementiert hast).
data.php schreibt die Werte in measurements und gibt eine JSON-Antwort:

{ "status": "ok" }
5.2 latest.php – letzten Datensatz abfragen

Dashboard oder Test-Skripte können per:

GET /growbox/api/latest.php

den letzten Eintrag aus measurements holen. Typische Ausgabe (Beispiel):

{
  "status": "ok",
  "measurement": {
    "id": 123,
    "temperature": 21.3,
    "humidity": 63.5,
    "soil1": 512,
    "soil2": 480,
    "soil3": 495,
    "created_at": "2025-12-07 02:31:00"
  }
}
5.3 logic.php / control.php – Relais steuern

Nach dem Messen kann der ESP32 optional:

GET /growbox/api/logic.php

oder:

GET /growbox/api/control.php

aufrufen, um neue Schaltbefehle zu holen.

Aktuell ist sicher implementiert:

Modus manual:

logic.php ändert nichts an den Relais

der JSON-Response zeigt die aktuell gespeicherten Zustände (before = after)

Für weitere Modi (z.B. auto, night, day) ist Platz in der logic.php vorgesehen.

6. Installation & Setup
6.1 Voraussetzungen

PHP 8.x

MySQL / MariaDB

Zugriff (FTP / SSH / Plesk) auf deinen Webspace

6.2 Dateien hochladen

gesamten Ordner growbox/ auf den Webserver in z.B.
htdocs/growbox/ oder public_html/growbox/ kopieren.

6.3 Datenbank anlegen

Neue Datenbank + Benutzer in Plesk / phpMyAdmin erstellen.

Tabellen anlegen (Schema entsprechend der oben genannten Felder für measurements und relays).

db_config.php anpassen:

<?php
$db_host = 'localhost';
$db_user = 'DB_USER';
$db_pass = 'DB_PASS';
$db_name = 'DB_NAME';

$mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($mysqli->connect_error) {
    die('DB-Verbindung fehlgeschlagen: ' . $mysqli->connect_error);
}
6.4 Login/Config einstellen

dashboard/config.php öffnen und dort ggf. Admin-User/Passwort anpassen
(je nach deiner aktuellen Lösung).

6.5 ESP32 konfigurieren

Im Arduino-Sketch:

SSID / Passwort für WLAN

BASE_URL auf deinen Webserver setzen, z.B.:

const char* BASE_URL = "https://deine-domain.de/growbox/api";

Pfade für data.php, logic.php bzw. control.php passend setzen.

Danach Sketch flashen → der ESP sollte beginnen, Werte zu senden.

7. Aktueller Stand (kurz)

✅ Funktioniert:

Login-System per PHP-Session (growbox_logged_in)

Speicherung von Sensorwerten in measurements über data.php

Speicherung von Relaiszuständen in relays

Statistikseite stats.php mit:

Sensor-Linechart (Temp / Luftfeuchte) via Chart.js

Relais-Timeline (4 Zeilen, farbige Balken nur bei „AN“)

Messwerte-Tabelle mit neuesten Werten oben

Navigationslinks zu:

Startseite /

Dashboard

Einstellungen

Statistik

API Latest (/growbox/api/data.php?latest=1 oder latest.php)

API Logic (/growbox/api/logic.php)

🛠 Geplant / TODO (Ideen):

Auto-Modi in logic.php (z.B. Temperatur-geregelte Heizung, feuchteabhängige Bewässerung)

Bodenfeuchte als % statt Rohwert (Kalibrierung im ESP oder Backend)

Zeitraum-Filter auf stats.php (letzte 24h / 7 Tage / 30 Tage)

Export-Funktion (CSV / JSON) aus Stats

Benutzerverwaltung mit DB-Usern (statt statischer Credentials in config.php)

Alert/Notifications (z.B. Mail oder Push bei zu hoher/zu niedriger Temperatur)

8. Weiterentwicklung

Wenn du etwas ändern oder erweitern willst, kannst du in diesem Setup gut iterieren:

Eine Datei (z.B. stats.php, logic.php, control.php) in den Editor laden

Änderungsideen hier im Chat beschreiben

Ich gebe dir eine fertige Version der Datei zurück

Du ersetzt sie auf dem Server

So wächst das Projekt Stück für Stück in eine robuste, komfortable Growbox-Steuerung hinein.
