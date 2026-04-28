<?php
// api_bookings.php
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
    
    // Buchungen mit den Namen des Trainings zusammenführen (JOIN)
    $stmt = $db->query("
        SELECT b.id, b.customer_name, b.customer_email, b.start_time, b.custom_data_json, e.name as event_name 
        FROM bookings b 
        JOIN event_types e ON b.event_type_id = e.id 
        ORDER BY b.start_time ASC
    ");
    
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>