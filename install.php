<?php
// install.php
require_once 'config.php';

echo "<div style='font-family: Arial, sans-serif; max-width: 600px; margin: 40px auto; padding: 20px; border: 1px solid #ccc; border-radius: 10px;'>";
echo "<h1 style='color: #0056b3;'>Hölter-Digital Setup 🚀</h1>";

// 1. Prüfen, ob der /data/ Ordner existiert, falls nicht, automatisch anlegen
if (!file_exists(__DIR__ . '/data')) {
    mkdir(__DIR__ . '/data', 0777, true);
    echo "<p>✅ Ordner <strong>/data/</strong> wurde erstellt.</p>";
}

try {
    // 2. Datenbankverbindung herstellen (erstellt die .db Datei automatisch, falls sie fehlt)
    $db = getDb();
    echo "<p>✅ Datenbankverbindung hergestellt.</p>";
    
    // 3. Tabellen erstellen
    $db->exec("
        CREATE TABLE IF NOT EXISTS event_types (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            duration_minutes INTEGER DEFAULT 60,
            is_active INTEGER DEFAULT 1
        );
        
        CREATE TABLE IF NOT EXISTS bookings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            event_type_id INTEGER,
            customer_name TEXT NOT NULL,
            customer_email TEXT NOT NULL,
            start_time DATETIME NOT NULL,
            FOREIGN KEY (event_type_id) REFERENCES event_types(id)
        );
        
        CREATE TABLE IF NOT EXISTS settings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            work_start_time TEXT DEFAULT '09:00',
            work_end_time TEXT DEFAULT '17:00'
        );
    ");
    echo "<p>✅ Datenbank-Tabellen wurden eingerichtet.</p>";

    // --- NEU: Datenbank-Updates (Migrationen) für bestehende Installationen ---
    // Fügt die JSON-Spalten sicher hinzu, falls sie noch nicht existieren
    $migrations = [
        "ALTER TABLE event_types ADD COLUMN schedule_json TEXT DEFAULT NULL",
        "ALTER TABLE event_types ADD COLUMN form_fields_json TEXT DEFAULT NULL",
        "ALTER TABLE bookings ADD COLUMN custom_data_json TEXT DEFAULT NULL"
    ];
    foreach ($migrations as $sql) {
        try { $db->exec($sql); } catch (PDOException $e) { /* Ignorieren, falls Spalte schon existiert */ }
    }
    echo "<p>✅ Datenbank-Struktur wurde für individuelle Zeiten und Formulare aktualisiert.</p>";

    // 4. Standard-Werte einfügen (wie in der Python Version)
    $db->exec("INSERT INTO event_types (name, duration_minutes) SELECT 'Einzeltraining', 60 WHERE NOT EXISTS (SELECT 1 FROM event_types)");
    $db->exec("INSERT INTO settings (work_start_time, work_end_time) SELECT '09:00', '17:00' WHERE NOT EXISTS (SELECT 1 FROM settings)");

    echo "<h3 style='color: green;'>Installation erfolgreich abgeschlossen! 🎉</h3>";
    echo "<p>Du kannst diese Datei nun löschen und zur <a href='index.php'>Startseite</a> gehen.</p>";

} catch (PDOException $e) {
    echo "<p style='color:red;'><strong>Fehler bei der Installation:</strong> " . $e->getMessage() . "</p>";
}
echo "</div>";
?>
