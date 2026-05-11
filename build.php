<?php
// build.php - Skript zur automatischen Erstellung von Release-ZIPs
// ACHTUNG: Nur lokal auf deinem Entwicklungsrechner aufrufen! Lade diese Datei NICHT zum Kunden hoch.

require_once 'config.php';
$version = PLANAGO_VERSION;

echo "<div style='font-family: Arial, sans-serif; padding: 20px; line-height: 1.6;'>";

if (!class_exists('ZipArchive')) {
    die("<p style='color:red;'><strong>Fehler:</strong> Die PHP ZipArchive Erweiterung ist nicht aktiviert. Bitte aktiviere <code>extension=zip</code> in deiner php.ini (XAMPP Config) und starte Apache neu.</p></div>");
}

echo "<h2>🚀 Starte Build-Prozess für Planago v{$version}...</h2>";

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
    foreach ($files as $file) {
        if (file_exists($file)) $zip->addFile($file, $file);
    }
    foreach ($directories as $dir) {
        if (is_dir($dir)) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
            foreach ($iterator as $file) {
                if (!$file->isDir()) {
                    $localPath = substr($file->getPathname(), strlen(__DIR__) + 1);
                    $zip->addFile($file->getPathname(), str_replace('\\', '/', $localPath));
                }
            }
        }
    }
}

// 1. UPDATE ZIP ERSTELLEN (ohne install.php - damit keine Kundendaten überschrieben werden)
$updateZipName = "planago_v{$version}.zip";
$updateZip = new ZipArchive();
if ($updateZip->open($updateZipName, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
    addFilesToZip($updateZip, $baseFiles, $directories);
    $updateZip->close();
    echo "<p>✅ Update-ZIP erstellt: <strong>{$updateZipName}</strong></p>";
}

// 2. VOLLSTÄNDIGE INSTALLATIONS-ZIP ERSTELLEN (inkl. install.php für Neukunden)
$installZipName = "planago_INSTALL_v{$version}.zip";
$installZip = new ZipArchive();
if ($installZip->open($installZipName, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
    addFilesToZip($installZip, array_merge($baseFiles, ['install.php', 'setup.php']), $directories);
    $installZip->close();
    echo "<p>✅ Kunden-Installations-ZIP erstellt: <strong>{$installZipName}</strong></p>";
}

// 3. VERSION.JSON AUTOMATISCH GENERIEREN
$versionJson = [ 
    "version" => $version, 
    "zip_url" => "https://planago.de/software_releases/{$updateZipName}",
    "install_zip_url" => "https://planago.de/software_releases/{$installZipName}"
];
file_put_contents('version.json', json_encode($versionJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo "<p>✅ <strong>version.json</strong> generiert.</p>";

echo "<hr><p>🎉 <strong>Fertig!</strong> Lade nun die beiden ZIPs und die <code>version.json</code> auf deinen Server in den Ordner <strong>software_releases</strong> hoch.</p>";
echo "</div>";
?>