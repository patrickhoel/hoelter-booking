<?php
// reset_demo.php
// Setzt die Datenbank für öffentliche Demo-Systeme zurück.

require_once 'config.php';

// 1. Absolute Sicherheit: Nur ausführen, wenn der Demo-Modus aktiv ist!
if (!defined('PLANAGO_DEMO_MODE') || !PLANAGO_DEMO_MODE) {
    http_response_code(403);
    die("❌ Abbruch: Dieser Befehl ist aus Sicherheitsgründen nur im aktivierten Demo-Modus erlaubt.");
}

try {
    $db = getDb();
    
    // 2. Alle Buchungen und Spamschutz-Limits restlos löschen
    $db->exec("DELETE FROM bookings");
    $db->exec("DELETE FROM rate_limits");
    
    // 3. Autoincrement-IDs zurücksetzen (damit neue Buchungen wieder sauber bei ID 1 starten)
    $db->exec("DELETE FROM sqlite_sequence WHERE name='bookings' OR name='rate_limits'");
    
    // 4. Datenbank komprimieren und Speicherplatz freigeben
    $db->exec("VACUUM");

    echo "✅ Demo-Datenbank erfolgreich zurückgesetzt! Alle Termine und IP-Sperren wurden gelöscht.";
} catch (Exception $e) {
    echo "❌ Fehler beim Zurücksetzen: " . $e->getMessage();
}
?>