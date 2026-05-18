<?php
// install.php
require_once 'config.php';

// --- AUFRÄUMEN & BEENDEN ---
if (isset($_POST['finish_installation'])) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['temp_admin_password'] = $_POST['temp_pwd'] ?? '';
    @unlink(__DIR__ . '/setup.php');
    @unlink(__DIR__ . '/temp_install.zip'); // Sicherheitshalber Reste löschen
    @unlink(__FILE__); // Löscht diese install.php selbst!
    header("Location: anleitung.php"); // Zur neuen Anleitung weiterleiten
    exit;
}

echo "<div style='font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif; max-width: 650px; margin: 40px auto; padding: 40px; border: 1px solid #d2d2d7; border-radius: 18px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); background: #ffffff;'>";
echo "<h1 style='color: #1d1d1f; margin-top: 0; text-align: center;'>Planago Setup 🚀</h1>";

// --- NEU: LIZENZ-CHECK (Die Hochzeit) ---
$domain = $_SERVER['HTTP_HOST'] ?? 'unknown';
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$basePath = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
$cronUrl = rtrim($protocol . "://" . $domain . $basePath, '/') . '/cron.php';

// Phoning Home zu deinem Server
if (PLANAGO_LICENSE_KEY !== 'demo-key') {
    $ch = curl_init('https://planago.de/api_license.php');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'license_key' => PLANAGO_LICENSE_KEY,
        'domain' => $domain,
        'cron_url' => $cronUrl
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    curl_close($ch);

    $licenseValid = false;
    $licenseError = 'Der Lizenz-Server ist aktuell nicht erreichbar. Bitte versuche es in ein paar Minuten erneut.';

    if ($response) {
        $licenseData = json_decode($response, true);
        if (isset($licenseData['status']) && $licenseData['status'] === 'valid') {
            $licenseValid = true;
        } else {
            $licenseError = $licenseData['message'] ?? 'Ungültige Lizenz.';
        }
    }

    if (!$licenseValid) {
        echo "<h2 style='color: #ff3b30; text-align: center;'>Setup blockiert</h2>";
        echo "<p style='text-align: center; color: #1d1d1f;'>$licenseError</p>";
        echo "</div>";
        exit;
    }
}

// 1. Prüfen, ob der /data/ Ordner existiert, falls nicht, automatisch anlegen
if (!file_exists(__DIR__ . '/data')) {
    mkdir(__DIR__ . '/data', 0755, true);
    @chmod(__DIR__ . '/data', 0755); // Sichere Schreibrechte
    echo "<p>✅ Ordner <strong>/data/</strong> wurde erstellt.</p>";
}

try {
    // 2. Datenbankverbindung herstellen (erstellt die .db Datei automatisch, falls sie fehlt)
    $db = getDb();
    echo "<p style='color: #86868b; font-size: 14px;'>✅ Datenbankverbindung hergestellt.</p>";
    
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
        
        CREATE TABLE IF NOT EXISTS rate_limits (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ip TEXT NOT NULL,
            action TEXT NOT NULL,
            timestamp DATETIME NOT NULL
        );
    ");
    echo "<p style='color: #86868b; font-size: 14px;'>✅ Datenbank-Tabellen wurden eingerichtet.</p>";

    // --- NEU: Datenbank-Updates (Migrationen) für bestehende Installationen ---
    // Fügt die JSON-Spalten sicher hinzu, falls sie noch nicht existieren
    $migrations = [
        "ALTER TABLE event_types ADD COLUMN schedule_json TEXT DEFAULT NULL",
        "ALTER TABLE event_types ADD COLUMN form_fields_json TEXT DEFAULT NULL",
        "ALTER TABLE bookings ADD COLUMN custom_data_json TEXT DEFAULT NULL",
        "ALTER TABLE event_types ADD COLUMN max_capacity INTEGER DEFAULT 1",
        "ALTER TABLE event_types ADD COLUMN buffer_minutes INTEGER DEFAULT 0",
        "ALTER TABLE event_types ADD COLUMN notice_min_hours INTEGER DEFAULT 24",
        "ALTER TABLE event_types ADD COLUMN notice_max_days INTEGER DEFAULT 60",
        "ALTER TABLE settings ADD COLUMN smtp_host TEXT DEFAULT ''",
        "ALTER TABLE settings ADD COLUMN smtp_port TEXT DEFAULT '587'",
        "ALTER TABLE settings ADD COLUMN smtp_user TEXT DEFAULT ''",
        "ALTER TABLE settings ADD COLUMN smtp_pass TEXT DEFAULT ''",
        "ALTER TABLE settings ADD COLUMN smtp_from TEXT DEFAULT ''",
        "ALTER TABLE settings ADD COLUMN require_manual_confirmation INTEGER DEFAULT 0",
        "ALTER TABLE bookings ADD COLUMN status TEXT DEFAULT 'confirmed'",
        "ALTER TABLE settings ADD COLUMN company_name TEXT DEFAULT 'Planago Booking'",
        "ALTER TABLE settings ADD COLUMN admin_email TEXT DEFAULT ''",
        "ALTER TABLE bookings ADD COLUMN cancel_token TEXT DEFAULT ''",
        "ALTER TABLE event_types ADD COLUMN cancel_limit_hours INTEGER DEFAULT 24",
        "ALTER TABLE settings ADD COLUMN company_address TEXT DEFAULT ''",
        "ALTER TABLE settings ADD COLUMN company_phone TEXT DEFAULT ''",
        "ALTER TABLE settings ADD COLUMN company_link_impressum TEXT DEFAULT ''",
        "ALTER TABLE settings ADD COLUMN company_link_privacy TEXT DEFAULT ''",
        "ALTER TABLE settings ADD COLUMN smtp_from_name TEXT DEFAULT ''",
        "ALTER TABLE settings ADD COLUMN company_link_agb TEXT DEFAULT ''",
        "ALTER TABLE settings ADD COLUMN admin_username TEXT DEFAULT 'admin'",
        "ALTER TABLE settings ADD COLUMN admin_password_hash TEXT DEFAULT ''",
        "ALTER TABLE settings ADD COLUMN widget_accent_color TEXT DEFAULT '#34c759'",
        "ALTER TABLE settings ADD COLUMN company_logo TEXT DEFAULT ''",
        "ALTER TABLE settings ADD COLUMN google_review_link TEXT DEFAULT ''",
        "ALTER TABLE settings ADD COLUMN enable_review_email INTEGER DEFAULT 0",
        "ALTER TABLE bookings ADD COLUMN review_email_sent INTEGER DEFAULT 0",
        "ALTER TABLE settings ADD COLUMN password_reset_token TEXT DEFAULT NULL",
        "ALTER TABLE settings ADD COLUMN password_reset_expires DATETIME DEFAULT NULL",
        "ALTER TABLE settings ADD COLUMN calendar_sync_token TEXT DEFAULT NULL",
        "ALTER TABLE settings ADD COLUMN theme_mode TEXT DEFAULT 'auto'",
        "ALTER TABLE settings ADD COLUMN force_password_change INTEGER DEFAULT 1",
        "ALTER TABLE settings ADD COLUMN license_status TEXT DEFAULT 'valid'",
        "ALTER TABLE settings ADD COLUMN license_last_check DATETIME DEFAULT '1970-01-01 00:00:00'",
        "ALTER TABLE bookings ADD COLUMN reminder_sent INTEGER DEFAULT 0",
        "ALTER TABLE settings ADD COLUMN enable_reminders INTEGER DEFAULT 0",
        "ALTER TABLE settings ADD COLUMN reminder_hours_before INTEGER DEFAULT 24",
        "ALTER TABLE settings ADD COLUMN updates_enabled INTEGER DEFAULT 1"
    ];
    foreach ($migrations as $sql) {
        try { $db->exec($sql); } catch (PDOException $e) { /* Ignorieren, falls Spalte schon existiert */ }
    }
    echo "<p style='color: #86868b; font-size: 14px;'>✅ Datenbank-Struktur erfolgreich aktualisiert.</p>";

    // 1. ZUERST: Standard-Werte einfügen (erschafft die Zeile)
    $db->exec("INSERT INTO event_types (name, duration_minutes) SELECT 'Einzeltraining', 60 WHERE NOT EXISTS (SELECT 1 FROM event_types)");
    $db->exec("INSERT INTO settings (work_start_time, work_end_time) SELECT '09:00', '17:00' WHERE NOT EXISTS (SELECT 1 FROM settings)");

    // 2. DANACH: Sicheres, temporäres Zufallspasswort generieren (Live) oder 'admin123' (Lokal)
    $isLocalhost = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1', '::1']);
    $tempPassword = $isLocalhost ? 'admin123' : bin2hex(random_bytes(6));
    $stmt = $db->prepare("UPDATE settings SET admin_password_hash = ?, force_password_change = 1 WHERE admin_password_hash = '' OR admin_password_hash IS NULL");
    $stmt->execute([password_hash($tempPassword, PASSWORD_DEFAULT)]);

    echo "<hr style='border: none; border-top: 1px solid #e5e5ea; margin: 30px 0;'>";
    echo "<h2 style='color: #34c759; margin-bottom: 15px; text-align: center;'>Installation erfolgreich! 🎉</h2>";
    
    // --- DIE ANLEITUNG FÜR DEN KUNDEN ---
    echo "
    <div style='background: #f5f5f7; padding: 25px; border-radius: 14px; margin-bottom: 30px;'>
        <h3 style='margin-top: 0; color: #1d1d1f; font-size: 18px;'>Wie geht es jetzt weiter?</h3>
        <ol style='line-height: 1.6; color: #1d1d1f; padding-left: 20px; margin-bottom: 0;'>
            <li style='margin-bottom: 12px;'><strong>Admin-Panel:</strong> Du kannst dein System über die <code>/admin.php</code> verwalten.<br>
            <em>Standard-Login:</em> Benutzer: <strong>admin</strong> | Passwort: <strong style='background: #fff; padding: 2px 6px; border-radius: 4px; border: 1px solid #ccc;'>" . htmlspecialchars($tempPassword) . "</strong><br>
            <span style='color: #d9534f; font-size: 13px;'>⚠️ Bitte ändere dieses temporäre Passwort sofort nach dem ersten Login!</span></li>
            <li style='margin-bottom: 12px;'><strong>Profil einrichten:</strong> Ändere nach dem ersten Login unbedingt dein Passwort im Dashboard und trage deine Unternehmensdaten (E-Mail, Adresse etc.) ein.</li>
            <li><strong>WICHTIG (DSGVO):</strong> Planago verarbeitet Namen und E-Mail-Adressen deiner Kunden. Bitte ergänze deine Datenschutzerklärung auf deiner Website um einen entsprechenden Passus, dass du für die Terminbuchung die Software \"Planago\" nutzt.</li>
        </ol>
    </div>
    ";
    
    echo "<form method='POST'><input type='hidden' name='temp_pwd' value='" . htmlspecialchars($tempPassword) . "'><p style='font-size: 12px; color: #86868b; text-align: center;'>Mit Klick auf den Button werden alle sensiblen Installations-Dateien aus Sicherheitsgründen restlos von deinem Server gelöscht.</p><button type='submit' name='finish_installation' style='background: #34c759; color: white; border: none; padding: 16px 30px; border-radius: 12px; font-size: 16px; font-weight: 600; cursor: pointer; width: 100%; box-shadow: 0 4px 12px rgba(52,199,89,0.3); transition: 0.2s;'>Installation beenden & Tool starten</button></form>";

} catch (PDOException $e) {
    echo "<p style='color:red;'><strong>Fehler bei der Installation:</strong> " . $e->getMessage() . "</p>";
}
echo "</div>";
?>
