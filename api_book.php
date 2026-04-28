<?php
// api_book.php

require_once 'config.php';
header('Content-Type: application/json');

try {
    $db = getDb();

    // 1. Daten aus dem Frontend (als JSON gesendet) empfangen
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    // 2. Validierung
    $eventId = $data['event_type_id'] ?? null;
    $name = $data['customer_name'] ?? null;
    $email = $data['customer_email'] ?? null;
    $startTimeStr = $data['start_time'] ?? null;

    if (!$eventId || !$name || !$email || !$startTimeStr) {
        throw new Exception("Bitte alle Felder ausfüllen.");
    }

    $startTime = new DateTime($startTimeStr);

    // 3. Doppelbuchungs-Check (wie in der Availability-API)
    $stmt = $db->prepare("SELECT duration_minutes FROM event_types WHERE id = ?");
    $stmt->execute([$eventId]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    $duration = $event['duration_minutes'];
    $requestedEnd = (clone $startTime)->modify("+$duration minutes");

    $bookingStmt = $db->prepare("SELECT start_time FROM bookings WHERE DATE(start_time) = ?");
    $bookingStmt->execute([$startTime->format('Y-m-d')]);
    $existingBookings = $bookingStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($existingBookings as $booking) {
        $b_start = new DateTime($booking['start_time']);
        $b_end = (clone $b_start)->modify("+$duration minutes");
        if ($startTime < $b_end && $requestedEnd > $b_start) {
            http_response_code(409); // 409 Conflict
            throw new Exception("Dieser Zeitraum ist leider schon belegt.");
        }
    }

    // 4. Alles OK -> Buchung in die Datenbank schreiben
    $insertStmt = $db->prepare("INSERT INTO bookings (event_type_id, customer_name, customer_email, start_time) VALUES (?, ?, ?, ?)");
    $insertStmt->execute([$eventId, $name, $email, $startTime->format('Y-m-d H:i:s')]);

    echo json_encode(['message' => 'Termin erfolgreich gebucht!']);

} catch (Exception $e) {
    if (http_response_code() === 200) { // Wenn kein spezifischer Fehlercode gesetzt wurde
        http_response_code(400); // Bad Request
    }
    echo json_encode(['error' => $e->getMessage()]);
}