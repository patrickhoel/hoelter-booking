<?php
// api_confirm_booking.php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Nicht authorisiert']);
    exit;
}

require_once 'config.php';
header('Content-Type: application/json');

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (isset($data['id'])) {
        $db = getDb();
        $db->prepare("UPDATE bookings SET status = 'confirmed' WHERE id = ?")->execute([$data['id']]);
        
        // Kundendaten für E-Mail laden
        $stmt = $db->prepare("SELECT b.customer_email, b.customer_name, b.start_time, e.name as event_name, e.duration_minutes FROM bookings b JOIN event_types e ON b.event_type_id = e.id WHERE b.id = ?");
        $stmt->execute([$data['id']]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($booking) {
            $startTimeObj = new DateTime($booking['start_time']);
            $date = $startTimeObj->format('d.m.Y \u\m H:i') . ' Uhr';
            $subject = "Termin bestätigt: " . $booking['event_name'];
            $body = "<h2>Hallo {$booking['customer_name']},</h2><p>gute Nachrichten: Dein Termin am <strong>$date</strong> für <em>{$booking['event_name']}</em> wurde soeben bestätigt!</p><p>Wir freuen uns auf dich.</p>";
            $icsData = generateIcsData($booking['event_name'], $startTimeObj, $booking['duration_minutes']);
            sendSystemMail($booking['customer_email'], $subject, $body, $icsData);
        }
        
        echo json_encode(['message' => 'Termin bestätigt und E-Mail versendet.']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>