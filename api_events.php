<?php
// api_events.php
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
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $clientToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $headers['X-CSRF-Token'] ?? $headers['X-Csrf-Token'] ?? '';
        if (!validateCsrfToken($clientToken)) {
            http_response_code(403);
            echo json_encode(['error' => 'Ungültiger CSRF-Token. Bitte die Seite neu laden.']);
            exit;
        }

        if (defined('PLANAGO_DEMO_MODE') && PLANAGO_DEMO_MODE) {
            echo json_encode(['error' => 'In der Demo-Version können keine Änderungen vorgenommen werden.']);
            exit;
        }

        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        
        $name = $data['name'] ?? null;
        $duration = isset($data['duration_minutes']) ? (int)$data['duration_minutes'] : 60;
        
        if ($name) {
            $stmt = $db->prepare("INSERT INTO event_types (name, duration_minutes, is_active) VALUES (?, ?, 1)");
            $stmt->execute([$name, $duration]);
            echo json_encode(['message' => 'Terminart erfolgreich angelegt!']);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Der Name darf nicht leer sein.']);
        }
    } else {
        $stmt = $db->query("SELECT id, name, duration_minutes, max_capacity, buffer_minutes, is_active FROM event_types ORDER BY name ASC");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>