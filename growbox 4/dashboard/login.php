<?php
// growbox/login.php
session_start();
require __DIR__ . '/config.php';

// Wenn bereits eingeloggt, direkt ins Dashboard
if (!empty($_SESSION['growbox_logged_in'])) {
    header('Location: dashboard.php'); // gleiches Verzeichnis
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['username'] ?? '');
    $pass = trim($_POST['password'] ?? '');

    if ($user === LAB_LOGIN_USER && $pass === LAB_LOGIN_PASS) {
        $_SESSION['growbox_logged_in'] = true;
        header('Location: dashboard.php'); // nach erfolgreichem Login
        exit;
    } else {
        $error = 'Login fehlgeschlagen.';
    }
}
?>

<!DOCTYPE html>

<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Growbox Login – liamslab</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            margin: 0;
            font-family: system-ui, sans-serif;
            background: radial-gradient(circle at top, #1e293b 0, #020617 55%);
            color: #e5e7eb;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .card {
            background: rgba(15,23,42,0.98);
            border-radius: 1.4rem;
            border: 1px solid #1f2937;
            padding: 1.8rem 1.9rem 2rem;
            width: 100%;
            max-width: 380px;
            box-shadow: 0 20px 40px rgba(15,23,42,1);
        }
        h1 {
            margin: 0 0 0.4rem;
            font-size: 1.3rem;
        }
        p.sub {
            margin: 0 0 1.3rem;
            font-size: 0.9rem;
            color: #9ca3af;
        }
        label {
            display: block;
            font-size: 0.82rem;
            margin-bottom: 0.25rem;
        }
        input[type="text"],
        input[type="password"] {
            width: 100%;
            border-radius: 0.7rem;
            border: 1px solid #374151;
            background: #020617;
            color: #e5e7eb;
            padding: 0.5rem 0.7rem;
            font-size: 0.9rem;
            margin-bottom: 0.8rem;
        }
        button {
            width: 100%;
            border-radius: 999px;
            border: none;
            background: linear-gradient(135deg, #22c55e, #16a34a);
            color: #022c22;
            font-weight: 600;
            padding: 0.55rem 0.9rem;
            cursor: pointer;
            font-size: 0.9rem;
            box-shadow: 0 16px 32px rgba(34,197,94,0.5);
        }
        button:hover {
            background: linear-gradient(135deg, #4ade80, #16a34a);
        }
        .error {
            color: #fecaca;
            font-size: 0.8rem;
            margin-bottom: 0.7rem;
        }
        .links {
            margin-top: 1rem;
            display: flex;
            justify-content: space-between;
            font-size: 0.8rem;
            color: #9ca3af;
        }
        .links a {
            color: #9ca3af;
            text-decoration: none;
        }
        .links a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
<div class="card">
    <h1>Growbox Login</h1>
    <p class="sub">Zugriff auf das interne Dashboard deiner ESP32-Steuerung.</p>

```
<?php if ($error): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="post" autocomplete="off">
    <label for="username">Benutzername</label>
    <input id="username" name="username" type="text" required>

    <label for="password">Passwort</label>
    <input id="password" name="password" type="password" required>

    <button type="submit">Anmelden</button>
</form>

<div class="links">
    <a href="../index.html">← Zur Startseite</a>
    <a href="logic.php" target="_blank">API testen</a>
</div>
```

</div>
</body>
</html>
