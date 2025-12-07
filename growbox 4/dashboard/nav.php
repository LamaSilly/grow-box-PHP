<?php
// nav.php – wird oben in index.php und settings.php eingebunden
?>
<header class="topbar">
    <div class="topbar-left">
        <span class="logo-dot"></span>
        <span class="topbar-title">Growbox Cloud</span>
    </div>
    <nav class="topbar-nav">
        <a href="index.php">Dashboard</a>
        <a href="settings.php">Einstellungen</a>
        <a href="logout.php">Logout</a>
    </nav>
</header>

<style>
    .topbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 18px;
        margin: -20px -20px 20px -20px;
        background: #0d0d14;
        border-bottom: 1px solid #26263a;
    }
    .topbar-left {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .logo-dot {
        width: 10px;
        height: 10px;
        border-radius: 999px;
        background: #4a7dff;
        box-shadow: 0 0 10px rgba(74,125,255,0.7);
    }
    .topbar-title {
        font-size: 1rem;
        font-weight: 600;
    }
    .topbar-nav a {
        margin-left: 14px;
        font-size: 0.9rem;
        color: #b0b0d0;
        text-decoration: none;
    }
    .topbar-nav a:hover {
        color: #ffffff;
    }
</style>
