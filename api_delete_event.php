<?php
// api_delete_event.php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Nicht authorisiert']);
    exit;
}

require_once 'config.php';
header('Content-Type: application/json');

try {
    // CSRF Token Validierung
    $headers = getallheaders();
    $clientToken = $headers['X-CSRF-Token'] ?? '';
    if (!validateCsrfToken($clientToken)) {
        http_response_code(403);
        echo json_encode(['error' => 'Ungültiger CSRF-Token. Bitte die Seite neu laden.']);
        exit;
    }

    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    if (isset($data['id'])) {
        $db = getDb();
        // 1. Zuerst alle Buchungen löschen, die an diesem Event hängen
        $db->prepare("DELETE FROM bookings WHERE event_type_id = ?")->execute([$data['id']]);
        
        // 2. Dann das Event selbst löschen
        $db->prepare("DELETE FROM event_types WHERE id = ?")->execute([$data['id']]);
        echo json_encode(['message' => 'Event und zugehörige Buchungen erfolgreich gelöscht.']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>