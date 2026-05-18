<?php
// build.php - Skript zur automatischen Erstellung von Release-ZIPs
// ACHTUNG: Nur lokal auf deinem Entwicklungsrechner aufrufen! Lade diese Datei NICHT zum Kunden hoch.

require_once 'config.php';
$version = PLANAGO_VERSION;

// --- RELEASE NOTES (WAS IST NEU?) ---
// Trage hier vor dem Ausführen der Datei kurz ein, was sich geändert hat.
$releaseNotes = "
<ul style='margin: 0; padding-left: 20px;'>
    <li><b>Fix:</b> Ungewollte Scrollbalken bei Tabellen in der Desktop-Ansicht entfernt.</li>
</ul>";

echo "<div style='font-family: Arial, sans-serif; padding: 20px; line-height: 1.6;'>";

if (!class_exists('ZipArchive')) {
    die("<p style='color:red;'><strong>Fehler:</strong> Die PHP ZipArchive Erweiterung ist nicht aktiviert. Bitte aktiviere <code>extension=zip</code> in deiner php.ini (XAMPP Config) und starte Apache neu.</p></div>");
}

// NEU: Wir definieren den Zielordner (Einen Ordner hoch, dann in 3_software_releases)
$releaseDir = __DIR__ . '/../3_software_releases/';
if (!is_dir($releaseDir)) {
    die("<p style='color:red;'><strong>Fehler:</strong> Der Zielordner <code>3_software_releases</code> wurde nicht gefunden. Bitte prüfe deine Ordnerstruktur.</p></div>");
}

echo "<h2>🚀 Starte Build-Prozess für Planago v{$version}...</h2>";

// --- NEU: Release Notes zur visuellen Kontrolle anzeigen ---
echo "<div style='background: #f8f9fa; border: 1px solid #dee2e6; padding: 15px; border-radius: 8px; margin-bottom: 20px;'>";
echo "<h3 style='margin-top: 0; margin-bottom: 10px; color: #495057; font-size: 16px;'>📝 Eingetragene Release Notes für v{$version}:</h3>";
echo "<div style='font-size: 14px;'>" . $releaseNotes . "</div>";
echo "</div>";

// Welche Dateien sollen zwingend gepackt werden?
$baseFiles = [
    'admin.php', 'anleitung.php', 'api_availability.php', 'api_book.php', 
    'api_bookings.php', 'api_confirm_booking.php', 'api_delete_booking.php', 
    'api_delete_event.php', 'api_events.php', 'api_event_settings.php', 
    'api_reschedule_invite.php', 'api_settings.php', 'api_test_email.php', 
    'cancel.php', 'config.php', 'cron.php', 'forgot.php', 'ical_feed.php', 
    'index.php', 'login.php', 'logo.php', 'logout.php', 'reset.php', 'update.php'
];

$directories = ['assets', 'PHPMailer'];

function addFilesToZip($zip, $files, $directories) {
    // 1. Basis-Dateien im Hauptordner
    foreach ($files as $file) {
        if (file_exists($file)) {
            // Sichert auch hier ab, dass immer Vorwärts-Slashes genutzt werden
            $zipName = ltrim(str_replace(['\\', '/'], '/', $file), '/');
            $zip->addFile($file, $zipName);
        } else {
            die("<p style='color:red; font-size: 16px;'><strong>🛑 Kritischer Fehler beim Build:</strong> Die Datei <code>{$file}</code> fehlt in deinem Entwicklungsordner!<br><br>Hast du die Installation lokal getestet und die Dateien haben sich am Ende selbst gelöscht? Bitte stelle sie wieder her, bevor du ein Release baust.</p></div>");
        }
    }
    
    // 2. Ganze Ordner (wie PHPMailer und assets) rekursiv durchlaufen
    foreach ($directories as $dir) {
        if (is_dir($dir)) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
            foreach ($iterator as $file) {
                if (!$file->isDir()) {
                    // Der absolute Dateipfad auf deinem Laptop (z.B. C:\xampp\...)
                    $absolutePath = $file->getPathname();
                    
                    // Schneidet den lokalen Projektordner-Pfad vorne komplett ab
                    $localPath = str_replace(__DIR__, '', $absolutePath);
                    
                    // DER IDIOTENSICHERE FIX: 
                    // Wandelt z.B. "\PHPMailer\src\SMTP.php" streng um in "PHPMailer/src/SMTP.php"
                    $zipPath = ltrim(str_replace(['\\', '/'], '/', $localPath), '/');
                    
                    $zip->addFile($absolutePath, $zipPath);
                }
            }
        }
    }
}

// Dateinamen für diese Version
$updateFileName = "planago_v{$version}.zip";
$installFileName = "planago_INSTALL_v{$version}.zip";

// 1. UPDATE ZIP ERSTELLEN (ohne install.php - damit keine Kundendaten überschrieben werden)
$updateZipPath = $releaseDir . $updateFileName;
$updateZip = new ZipArchive();
if ($updateZip->open($updateZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
    addFilesToZip($updateZip, $baseFiles, $directories);
    $updateZip->close();
    echo "<p>✅ Update-ZIP erstellt: <strong>{$updateFileName}</strong> (Gespeichert in 3_software_releases)</p>";
}

// 2. VOLLSTÄNDIGE INSTALLATIONS-ZIP ERSTELLEN (inkl. install.php für Neukunden)
$installZipPath = $releaseDir . $installFileName;
$installZip = new ZipArchive();
if ($installZip->open($installZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
    addFilesToZip($installZip, array_merge($baseFiles, ['install.php']), $directories);
    $installZip->close();
    echo "<p>✅ Kunden-Installations-ZIP erstellt: <strong>{$installFileName}</strong> (Gespeichert in 3_software_releases)</p>";
}

// 3. VERSION.PHP (mit CORS Header) AUTOMATISCH GENERIEREN
$versionJson = [ 
    "version" => $version, 
    "release_notes" => trim($releaseNotes),
    "zip_url" => "https://planago.de/software_releases/{$updateFileName}",
    "install_zip_url" => "https://planago.de/software_releases/{$installFileName}"
];
$phpContent = "<?php\nheader('Access-Control-Allow-Origin: *');\nheader('Content-Type: application/json');\n?>\n" . json_encode($versionJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
file_put_contents($releaseDir . 'version.php', $phpContent);
echo "<p>✅ <strong>version.php</strong> (mit CORS-Support) aktualisiert.</p>";

echo "<hr><p>🎉 <strong>Build erfolgreich!</strong><br><br>👉 Gehe jetzt in VS Code, klicke mit der <b>rechten Maustaste auf den Ordner '3_software_releases'</b> und wähle <b>'Upload'</b>. Die neue Version ist dann sofort online!</p>";
echo "</div>";
?>