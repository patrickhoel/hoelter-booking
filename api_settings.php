<?php
// api_settings.php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401); // Unauthorized
    echo json_encode(['error' => 'Nicht authorisiert']);
    exit;
}

require_once 'config.php';
header('Content-Type: application/json');

try {
    $db = getDb();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        
        $start = $data['work_start_time'] ?? null;
        $end = $data['work_end_time'] ?? null;
        
        if ($start && $end) {
            $stmt = $db->prepare("UPDATE settings SET work_start_time = ?, work_end_time = ?");
            $stmt->execute([$start, $end]);
            echo json_encode(['message' => 'Arbeitszeiten erfolgreich gespeichert!']);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Fehlende Daten']);
        }
    } else {
        // GET: Einstellungen abrufen
        $stmt = $db->query("SELECT work_start_time, work_end_time FROM settings LIMIT 1");
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode($settings);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>