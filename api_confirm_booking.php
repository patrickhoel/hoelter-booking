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
        $stmt = $db->prepare("SELECT b.customer_email, b.customer_name, b.start_time, b.cancel_token, e.name as event_name, e.duration_minutes FROM bookings b JOIN event_types e ON b.event_type_id = e.id WHERE b.id = ?");
        $stmt->execute([$data['id']]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($booking) {
            $settingStmt = $db->query("SELECT company_name FROM settings LIMIT 1");
            $sysSettings = $settingStmt->fetch(PDO::FETCH_ASSOC);
            $companyName = $sysSettings['company_name'] ?? 'Planago Booking';

            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
            $baseUrl = $protocol . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']);
            $cancelLink = $baseUrl . "/cancel.php?token=" . $booking['cancel_token'];

            $startTimeObj = new DateTime($booking['start_time']);
            $date = $startTimeObj->format('d.m.Y \u\m H:i') . ' Uhr';
            $subject = "Termin bestätigt: " . $booking['event_name'] . " - " . $companyName;
            $body = "<h2>Hallo {$booking['customer_name']},</h2><p>gute Nachrichten: Dein Termin am <strong>$date</strong> für <em>{$booking['event_name']}</em> wurde soeben bestätigt!</p><p>Wir freuen uns auf dich.</p><hr><p style='font-size:12px; color:#666;'>Möchtest du den Termin doch noch absagen? <a href='$cancelLink'>Hier klicken</a></p>";
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