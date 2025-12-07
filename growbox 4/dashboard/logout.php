<?php
// growbox/dashboard/logout.php
session_start();

// Session leeren & zerstören
$_SESSION = [];
session_destroy();

// Zurück zur Login-Seite des Dashboards
header('Location: /growbox/dashboard/login.php');
exit;
