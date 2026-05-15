<?php
session_start();

// 1. Sicherheits-Check: Nur eingeloggte Admins dürfen das Update anstoßen!
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Nicht authorisiert. Bitte im Admin-Panel einloggen.']);
    exit;
}

// Lade die Konfiguration, um den Lizenzschlüssel zu erhalten
require_once 'config.php';

header('Content-Type: application/json');

// CSRF Token Validierung
$headers = function_exists('getallheaders') ? getallheaders() : [];
$clientToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $headers['X-CSRF-Token'] ?? $headers['X-Csrf-Token'] ?? '';
if (!validateCsrfToken($clientToken)) {
    http_response_code(403);
    echo json_encode(['error' => 'Ungültiger CSRF-Token. Bitte die Seite neu laden.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

// 2. Check: Wurde eine URL zur neuen ZIP-Datei übergeben?
if (empty($data['zip_url'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Keine ZIP-URL angegeben.']);
    exit;
}

$zipUrl = $data['zip_url'];
$tempZipPath = __DIR__ . '/temp_update.zip';

// 3. ZIP-Datei sicher vom Agentur-Server herunterladen (mit Lizenzschlüssel)
$options = [
    'http' => [
        'header' => "X-Planago-License: " . PLANAGO_LICENSE_KEY . "\r\n"
    ]
];
$context = stream_context_create($options);

$zipContent = @file_get_contents($zipUrl, false, $context);
if ($zipContent === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Fehler beim Herunterladen der Update-Datei von den Planago-Servern.']);
    exit;
}

file_put_contents($tempZipPath, $zipContent);

// 4. ZIP-Datei entpacken und Dateien überschreiben
$zip = new ZipArchive;
if ($zip->open($tempZipPath) === TRUE) {

    // --- DER ULTIMATIVE WINDOWS / BERECHTIGUNGS FIX ---
    // extractTo() bricht bei der ersten gesperrten Datei (wie der update.php selbst!) komplett ab.
    // Daher entpacken wir die Dateien in einer Schleife einzeln und umgehen Sperren intelligent.
    foreach (glob(__DIR__ . '/*.old.php') as $old) { @unlink($old); } // Alte Reste aufräumen

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $filename = $zip->getNameIndex($i);
        $targetPath = __DIR__ . '/' . $filename;

        if (substr($filename, -1) === '/') {
            if (!file_exists($targetPath)) @mkdir($targetPath, 0755, true);
            continue;
        }

        $dir = dirname($targetPath);
        if (!file_exists($dir)) @mkdir($dir, 0755, true);

        // Sperre umgehen: Wenn Datei existiert und nicht überschreibbar ist, umbenennen!
        if (file_exists($targetPath)) {
            if (!@unlink($targetPath)) @rename($targetPath, $targetPath . '.old.php');
        }

        // Neue Datei aus dem ZIP schreiben
        $content = $zip->getFromIndex($i);
        if ($content !== false) {
            @file_put_contents($targetPath, $content);
        }
    }

    $zip->close();
    unlink($tempZipPath);

    // Server-Caches leeren, damit die neue config.php (mit der neuen Versionsnummer) sofort aktiv wird!
    if (function_exists('opcache_reset')) { opcache_reset(); }
    if (function_exists('apcu_clear_cache')) { apcu_clear_cache(); }

    echo json_encode(['success' => true, 'message' => 'Planago wurde erfolgreich aktualisiert! Das System lädt jetzt neu.']);
} else {
    unlink($tempZipPath);
    http_response_code(500);
    echo json_encode(['error' => 'Fehler beim Entpacken der ZIP-Datei.']);
}
?>