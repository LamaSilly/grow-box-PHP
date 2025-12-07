<?php
// growbox/config.php
//
// Zentrale Konfiguration für das Growbox-Projekt.
// - Basis-URL der Web-App
// - Login-Daten für das Dashboard
// - API-Key für den ESP32 (Mess-Endpoint)

//
// Basis-URL deiner Seite (für Links / Redirects)
// Wenn Growbox direkt im Webroot liegt → '/'.
// Wenn z.B. unter /growbox/ als eigene Sub-URL, kannst du das anpassen.
//
define('APP_BASE_URL', '/');

//
// Login für das Web-Dashboard
// ACHTUNG: Nur für internen Gebrauch gedacht! Fürs offene Internet -> zusätzliche Absicherung.
//
define('LAB_LOGIN_USER', 'admin');
define('LAB_LOGIN_PASS', '4882');

//
// API-Key für den ESP32
// Diesen Key musst du im ESP-Code mitgeben (Header "X-API-KEY" oder ?api_key=...).
// Hier benutze ich dein angegebenes „ESP-Passwort“.
// Du kannst ihn später jederzeit ändern, solltest ihn dann aber auch im ESP anpassen.
//
define('GROWBOX_API_KEY', 'Klabautermann.2');
