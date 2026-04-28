<?php
// config.php
// Hier definieren wir globale Einstellungen für das gesamte System

define('DB_PATH', __DIR__ . '/data/database.db');

// Hilfsfunktion für die Datenbankverbindung
function getDb() {
    // Stellt die Verbindung zur SQLite-Datenbank her
    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $pdo;
}
?>