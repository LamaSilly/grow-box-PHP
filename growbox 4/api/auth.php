<?php
// growbox/api/auth.php
//
// Zentrale Funktionen für API-Sicherheit:
//  - Session-Login (Dashboard)
//  - API-Key (ESP32)

require_once __DIR__ . '/../config.php'; // enthält GROWBOX_API_KEY
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Prüfen, ob ein gültiger Dashboard-Login existiert.
 */
function has_valid_session(): bool {
    return !empty($_SESSION['growbox_logged_in']);
}

/**
 * API-Key aus Request lesen:
 *  - Header: X-API-KEY
 *  - GET/POST: api_key
 */
function get_api_key_from_request(): ?string {
    if (!empty($_SERVER['HTTP_X_API_KEY'])) {
        return $_SERVER['HTTP_X_API_KEY'];
    }
    if (isset($_GET['api_key'])) {
        return $_GET['api_key'];
    }
    if (isset($_POST['api_key'])) {
        return $_POST['api_key'];
    }
    return null;
}

/**
 * Prüfen, ob der mitgeschickte API-Key gültig ist.
 */
function has_valid_api_key(): bool {
    $provided = get_api_key_from_request();
    if ($provided === null) {
        return false;
    }
    // timing-sicherer Vergleich
    return hash_equals(GROWBOX_API_KEY, $provided);
}

/**
 * Nur Dashboard-Login erlaubt.
 */
function require_session_auth(): void {
    if (has_valid_session()) {
        return;
    }
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'error' => 'Unauthorized – login required'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Nur API-Key erlaubt (für ESP-Endpunkte).
 */
function require_api_key_auth(): void {
    if (has_valid_api_key()) {
        return;
    }
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'error' => 'Forbidden – invalid or missing API key'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Entweder Dashboard-Login **oder** API-Key.
 */
function require_session_or_api_key(): void {
    if (has_valid_session() || has_valid_api_key()) {
        return;
    }
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'error' => 'Unauthorized'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
