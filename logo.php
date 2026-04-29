<?php
// logo.php
// Lädt das Logo als reines Bild aus der Datenbank und stellt es dem Browser/E-Mail-Programm zur Verfügung.
require_once 'config.php';

try {
    $db = getDb();
    $stmt = $db->query("SELECT company_logo FROM settings LIMIT 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!empty($settings['company_logo'])) {
        $base64 = $settings['company_logo'];
        $commaPos = strpos($base64, ',');
        if ($commaPos !== false) {
            $mimeInfo = substr($base64, 5, $commaPos - 5); // extrahiert z.B. "image/png;base64"
            $mime = str_replace(';base64', '', $mimeInfo);
            $imgData = base64_decode(substr($base64, $commaPos + 1));
            
            header("Content-Type: " . $mime);
            header("Cache-Control: public, max-age=86400"); // 1 Tag im Browser-Cache behalten
            echo $imgData;
            exit;
        }
    }
} catch (Exception $e) {}

// Fallback, falls kein Logo existiert: Ein unsichtbarer 1x1 Pixel
header('Content-Type: image/gif');
echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
?>