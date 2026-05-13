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
$headers = getallheaders();
$clientToken = $headers['X-CSRF-Token'] ?? '';
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
    $zip->extractTo(__DIR__);
    $zip->close();
    unlink($tempZipPath);
    echo json_encode(['success' => true, 'message' => 'Planago wurde erfolgreich aktualisiert! Das System lädt jetzt neu.']);
} else {
    unlink($tempZipPath);
    http_response_code(500);
    echo json_encode(['error' => 'Fehler beim Entpacken der ZIP-Datei.']);
}
?>