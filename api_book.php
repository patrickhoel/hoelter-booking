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
    $stmt = $db->prepare("SELECT name, duration_minutes, max_capacity, buffer_minutes, notice_min_hours, notice_max_days FROM event_types WHERE id = ?");
    $stmt->execute([$eventId]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    $eventName = $event['name'] ?? 'Training';
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
    
    // 3.5 Prüfen, ob Zwei-Wege-Bestätigung aktiv ist
    $settingStmt = $db->query("SELECT require_manual_confirmation, company_name, admin_email FROM settings LIMIT 1");
    $sysSettings = $settingStmt->fetch(PDO::FETCH_ASSOC);
    $require_manual = $sysSettings['require_manual_confirmation'] ?? 0;
    $companyName = $sysSettings['company_name'] ?? 'Planago Booking';
    $adminEmail = $sysSettings['admin_email'] ?? '';
    $status = $require_manual ? 'pending' : 'confirmed';
    
    // Geheimen Token für Stornierung generieren
    $cancelToken = bin2hex(random_bytes(16));

    // 4. Alles OK -> Buchung in die Datenbank schreiben
    $insertStmt = $db->prepare("INSERT INTO bookings (event_type_id, customer_name, customer_email, start_time, custom_data_json, status, cancel_token) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $insertStmt->execute([$eventId, $name, $email, $startTime->format('Y-m-d H:i:s'), $customData, $status, $cancelToken]);

    // 5. Automatische E-Mail an den Kunden versenden
    $formattedDate = $startTime->format('d.m.Y \u\m H:i') . ' Uhr';
    
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $baseUrl = $protocol . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']);
    $cancelLink = $baseUrl . "/cancel.php?token=" . $cancelToken;
    
    $icsData = null;
    if ($require_manual) {
        $subject = "Termin-Anfrage eingegangen - $companyName";
        $body = "<h2>Hallo $name,</h2><p>wir haben deine Anfrage für den Termin am <strong>$formattedDate</strong> erhalten.</p><p>Wir prüfen dies nun und melden uns in Kürze mit der finalen Bestätigung bei dir!</p><hr><p style='font-size:12px; color:#666;'>Möchtest du die Anfrage zurückziehen? <a href='$cancelLink'>Hier klicken zum Absagen</a></p>";
        $msg = 'Termin erfolgreich angefragt! Warte auf Bestätigung...';
    } else {
        $subject = "Terminbestätigung - $companyName";
        $body = "<h2>Hallo $name,</h2><p>dein Termin am <strong>$formattedDate</strong> ist hiermit verbindlich gebucht!</p><p>Wir freuen uns auf dich.</p><hr><p style='font-size:12px; color:#666;'>Termin absagen? <a href='$cancelLink'>Hier klicken</a></p>";
        $msg = 'Termin erfolgreich gebucht!';
        $icsData = generateIcsData($eventName, $startTime, $duration);
    }
    sendSystemMail($email, $subject, $body, $icsData);

    // 6. Benachrichtigung an Admin
    if (!empty($adminEmail)) {
        $adminSubj = "Neue Buchung: $eventName am " . $startTime->format('d.m. H:i');
        $adminBody = "<h2>Neue Termin-Aktivität</h2><p><strong>Kunde:</strong> $name ($email)</p><p><strong>Training:</strong> $eventName</p><p><strong>Zeitpunkt:</strong> $formattedDate</p><p><strong>Status:</strong> " . ($require_manual ? 'Ausstehend (Muss im Dashboard bestätigt werden)' : 'Automatisch bestätigt') . "</p>";
        sendSystemMail($adminEmail, $adminSubj, $adminBody);
    }

    echo json_encode(['message' => $msg]);

} catch (Exception $e) {
    if (http_response_code() === 200) { // Wenn kein spezifischer Fehlercode gesetzt wurde
        http_response_code(400); // Bad Request
    }
    echo json_encode(['error' => $e->getMessage()]);
}