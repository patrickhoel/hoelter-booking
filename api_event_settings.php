<?php
// api_event_settings.php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Nicht authorisiert']);
    exit;
}

require_once 'config.php';
header('Content-Type: application/json');

try {
    $db = getDb();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // CSRF Token Validierung
        $headers = getallheaders();
        $clientToken = $headers['X-CSRF-Token'] ?? '';
        if (!validateCsrfToken($clientToken)) {
            http_response_code(403);
            echo json_encode(['error' => 'Ungültiger CSRF-Token. Bitte die Seite neu laden.']);
            exit;
        }

        // Einstellungen abspeichern
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        
        $id = $data['id'] ?? null;
        $schedule_json = $data['schedule_json'] ?? null;
        $form_fields_json = $data['form_fields_json'] ?? null;
        $max_capacity = isset($data['max_capacity']) ? (int)$data['max_capacity'] : 1;
        $buffer_minutes = isset($data['buffer_minutes']) ? (int)$data['buffer_minutes'] : 0;
        $notice_min = isset($data['notice_min_hours']) ? (int)$data['notice_min_hours'] : 24;
        $notice_max = isset($data['notice_max_days']) ? (int)$data['notice_max_days'] : 60;
        $cancel_limit = isset($data['cancel_limit_hours']) ? (int)$data['cancel_limit_hours'] : 24;
        
        // Min/Max Input-Validierung zur Sicherheit
        $max_capacity = max(1, min(1000, $max_capacity));
        $buffer_minutes = max(0, min(1440, $buffer_minutes));
        $notice_min = max(0, min(8760, $notice_min));
        $notice_max = max(1, min(1825, $notice_max));
        $cancel_limit = max(0, min(8760, $cancel_limit));

        if ($id) {
            $stmt = $db->prepare("UPDATE event_types SET schedule_json = ?, form_fields_json = ?, max_capacity = ?, buffer_minutes = ?, notice_min_hours = ?, notice_max_days = ?, cancel_limit_hours = ? WHERE id = ?");
            $stmt->execute([$schedule_json, $form_fields_json, $max_capacity, $buffer_minutes, $notice_min, $notice_max, $cancel_limit, $id]);
            echo json_encode(['message' => 'Einstellungen erfolgreich gespeichert!']);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Keine ID übergeben.']);
        }
    } else {
        // GET Request: Einstellungen auslesen
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if ($id) {
            $stmt = $db->prepare("SELECT id, name, schedule_json, form_fields_json, max_capacity, buffer_minutes, notice_min_hours, notice_max_days, cancel_limit_hours FROM event_types WHERE id = ?");
            $stmt->execute([$id]);
            $event = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($event) {
                echo json_encode($event);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Terminart nicht gefunden.']);
            }
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Keine ID übergeben.']);
        }
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>