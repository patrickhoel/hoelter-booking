<?php
// api_delete_booking.php
session_start();

// Sicherheitscheck: Nur eingeloggte Admins dürfen löschen!
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Nicht authorisiert']);
    exit;
}

require_once 'config.php';
header('Content-Type: application/json');

try {
    // CSRF Token Validierung
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $clientToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $headers['X-CSRF-Token'] ?? $headers['X-Csrf-Token'] ?? '';
    if (!validateCsrfToken($clientToken)) {
        http_response_code(403);
        echo json_encode(['error' => 'Ungültiger CSRF-Token. Bitte die Seite neu laden.']);
        exit;
    }

    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    if (isset($data['id'])) {
        $db = getDb();
        $stmt = $db->prepare("DELETE FROM bookings WHERE id = ?");
        $stmt->execute([$data['id']]);
        echo json_encode(['message' => 'Termin erfolgreich gelöscht.']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>