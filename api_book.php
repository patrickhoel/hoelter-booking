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
    $customData = isset($data['custom_data']) ? json_encode($data['custom_data']) : null;

    if (!$eventId || !$name || !$email || !$startTimeStr) {
        throw new Exception("Bitte alle Felder ausfüllen.");
    }

    $startTime = new DateTime($startTimeStr);

    // 3. Doppelbuchungs-Check (wie in der Availability-API)
    $stmt = $db->prepare("SELECT duration_minutes, max_capacity, buffer_minutes, notice_min_hours, notice_max_days FROM event_types WHERE id = ?");
    $stmt->execute([$eventId]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    $duration = $event['duration_minutes'];
    $maxCapacity = $event['max_capacity'] ?? 1;
    $buffer = $event['buffer_minutes'] ?? 0;
    $noticeMinHours = $event['notice_min_hours'] ?? 24;
    $noticeMaxDays = $event['notice_max_days'] ?? 60;
    $requestedEnd = (clone $startTime)->modify("+" . ($duration + $buffer) . " minutes");

    // Sicherheits-Check: Liegt der Termin im erlaubten Zeitfenster?
    $now = new DateTime();
    $minAllowed = (clone $now)->modify("+$noticeMinHours hours");
    $maxAllowed = (clone $now)->setTime(23, 59, 59)->modify("+$noticeMaxDays days");

    if ($startTime < $minAllowed) {
        http_response_code(400);
        throw new Exception("Dieser Termin liegt zu kurz in der Zukunft (Mindestvorlauf: $noticeMinHours Stunden).");
    }
    if ($startTime > $maxAllowed) {
        http_response_code(400);
        throw new Exception("Dieser Termin liegt zu weit in der Zukunft (Maximal: $noticeMaxDays Tage).");
    }

    $bookingStmt = $db->prepare("
        SELECT b.start_time, e.duration_minutes, e.buffer_minutes 
        FROM bookings b 
        JOIN event_types e ON b.event_type_id = e.id 
        WHERE DATE(b.start_time) = ?
    ");
    $bookingStmt->execute([$startTime->format('Y-m-d')]);
    $existingBookings = $bookingStmt->fetchAll(PDO::FETCH_ASSOC);

    $overlappingCount = 0;
    foreach ($existingBookings as $booking) {
        $b_start = new DateTime($booking['start_time']);
        $b_duration = $booking['duration_minutes'];
        $b_buffer = $booking['buffer_minutes'] ?? 0;
        $b_end = (clone $b_start)->modify("+" . ($b_duration + $b_buffer) . " minutes");
        if ($startTime < $b_end && $requestedEnd > $b_start) {
            $overlappingCount++;
        }
    }
    
    if ($overlappingCount >= $maxCapacity) {
        http_response_code(409); // 409 Conflict
        throw new Exception("Dieser Zeitraum ist leider schon restlos ausgebucht.");
    }

    // 4. Alles OK -> Buchung in die Datenbank schreiben
    $insertStmt = $db->prepare("INSERT INTO bookings (event_type_id, customer_name, customer_email, start_time, custom_data_json) VALUES (?, ?, ?, ?, ?)");
    $insertStmt->execute([$eventId, $name, $email, $startTime->format('Y-m-d H:i:s'), $customData]);

    echo json_encode(['message' => 'Termin erfolgreich gebucht!']);

} catch (Exception $e) {
    if (http_response_code() === 200) { // Wenn kein spezifischer Fehlercode gesetzt wurde
        http_response_code(400); // Bad Request
    }
    echo json_encode(['error' => $e->getMessage()]);
}