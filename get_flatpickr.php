<?php
// get_flatpickr.php - ROBUSTE cURL VERSION
$dir = __DIR__ . '/assets/flatpickr';
if (!is_dir($dir)) mkdir($dir, 0777, true);

$files = [
    'flatpickr.min.js' => 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.js',
    'flatpickr.min.css' => 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css',
    'de.js' => 'https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/de.js',
    'dark.css' => 'https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css'
];

foreach ($files as $name => $url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $data = curl_exec($ch);
    curl_close($ch);
    
    if ($data && strlen($data) > 100) {
        file_put_contents($dir . '/' . $name, $data);
        echo "✅ Erfolgreich geladen (cURL): <strong>$name</strong> (" . strlen($data) . " Bytes)<br>";
    } else {
        echo "❌ <strong style='color:red;'>Fehler bei: $name</strong> (Datei leer oder geblockt!)<br>";
    }
}
echo "<br><b>🎉 Fertig!</b> Wenn überall 'Erfolgreich' steht, liegen die Dateien fehlerfrei bereit.";
?>