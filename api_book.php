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
    $rescheduleId = $data['reschedule_id'] ?? null;
    $rescheduleToken = $data['reschedule_token'] ?? null;

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

    if ($rescheduleId && $rescheduleToken) {
        // --- LOGIK FÜR TERMIN-VERSCHIEBUNG ---
        
        // 1. Prüfen, ob der Token und die ID zur ursprünglichen Buchung passen
        $stmt = $db->prepare("SELECT id FROM bookings WHERE id = ? AND cancel_token = ? AND status = 'reschedule_requested'");
        $stmt->execute([$rescheduleId, $rescheduleToken]);
        $originalBooking = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$originalBooking) {
            throw new Exception("Ungültige Anfrage zum Verschieben oder der Termin wurde bereits verschoben.");
        }

        // 2. Bestehende Buchung aktualisieren
        $updateStmt = $db->prepare("UPDATE bookings SET start_time = ?, status = ?, custom_data_json = ? WHERE id = ?");
        $updateStmt->execute([$startTime->format('Y-m-d H:i:s'), $status, $customData, $rescheduleId]);

        // 3. Bestätigungs-E-Mail für den *neuen* Termin senden
        $formattedDateStr = $startTime->format('d.m.Y');
        $formattedTimeStr = $startTime->format('H:i');
        $subject = "Termin erfolgreich verschoben - $companyName";
        $body = "<h2>Hallo $name,</h2><p>dein Termin wurde erfolgreich auf den <strong>$formattedDateStr um $formattedTimeStr Uhr</strong> verschoben.</p><p>Wir freuen uns auf dich.</p>";
        $msg = 'Dein Termin wurde erfolgreich verschoben!';
        $icsData = generateIcsData($eventName, $startTime, $duration);
        sendSystemMail($email, $subject, $body, $icsData);

        // 4. Admin über die erfolgreiche Verschiebung informieren
        if (!empty($adminEmail)) {
            $adminSubj = "Termin verschoben: $eventName am " . $startTime->format('d.m. H:i');
            $adminBody = "Ein Kunde hat einen Termin verschoben.<br><strong>Neuer Zeitpunkt:</strong> $formattedDateStr um $formattedTimeStr Uhr.<br><strong>Status:</strong> Der Termin wurde auf '$status' gesetzt.";
            sendSystemMail($adminEmail, $adminSubj, $adminBody);
        }

        echo json_encode(['message' => $msg]);

    } else {
        // --- LOGIK FÜR NEUE BUCHUNG (bestehender Code) ---
        
        // 4. Alles OK -> Buchung in die Datenbank schreiben
        $insertStmt = $db->prepare("INSERT INTO bookings (event_type_id, customer_name, customer_email, start_time, custom_data_json, status, cancel_token) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $insertStmt->execute([$eventId, $name, $email, $startTime->format('Y-m-d H:i:s'), $customData, $status, $cancelToken]);

        // 5. Automatische E-Mail an den Kunden versenden
        $formattedDate = $startTime->format('d.m.Y \u\m H:i') . ' Uhr';
        
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
        $baseUrl = $protocol . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']);
        $cancelLink = $baseUrl . "/cancel.php?token=" . $cancelToken;
        
        $icsData = null;
        $formattedDateStr = $startTime->format('d.m.Y');
        $formattedTimeStr = $startTime->format('H:i');

        if ($require_manual) {
            $subject = "Termin-Anfrage eingegangen - $companyName";
            $body = "
            <div style='font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif; max-width: 550px; margin: 40px auto; padding: 30px; background-color: #ffffff; border: 1px solid #d2d2d7; border-radius: 18px; box-shadow: 0 4px 20px rgba(0,0,0,0.05);'>
                <div style='text-align: center; margin-bottom: 25px;'>
                    <h1 style='color: #1d1d1f; font-size: 24px; margin-bottom: 5px;'>Anfrage eingegangen</h1>
                    <p style='color: #86868b; font-size: 16px;'>Wir melden uns in Kürze mit der finalen Bestätigung.</p>
                </div>
                <div style='background-color: #f5f5f7; padding: 20px; border-radius: 14px; margin-bottom: 25px;'>
                    <table style='width: 100%; border-collapse: collapse;'>
                        <tr><td style='color: #86868b; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; padding-bottom: 5px;'>Was</td></tr>
                        <tr><td style='color: #1d1d1f; font-weight: 600; font-size: 17px; padding-bottom: 15px;'>$eventName</td></tr>
                        <tr><td style='color: #86868b; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; padding-bottom: 5px;'>Wann</td></tr>
                        <tr><td style='color: #1d1d1f; font-weight: 600; font-size: 17px;'>$formattedDateStr um $formattedTimeStr Uhr</td></tr>
                    </table>
                </div>
                <div style='text-align: center; border-top: 1px solid #d2d2d7; padding-top: 25px;'>
                    <a href='$cancelLink' style='color: #86868b; font-size: 13px; text-decoration: underline;'>Anfrage zurückziehen</a>
                </div>
            </div>";
            $msg = 'Termin erfolgreich angefragt! Warte auf Bestätigung...';
        } else {
            $subject = "Terminbestätigung - $companyName";
            $body = "
            <div style='font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif; max-width: 550px; margin: 40px auto; padding: 30px; background-color: #ffffff; border: 1px solid #d2d2d7; border-radius: 18px; box-shadow: 0 4px 20px rgba(0,0,0,0.05);'>
                <div style='text-align: center; margin-bottom: 25px;'>
                    <h1 style='color: #1d1d1f; font-size: 24px; margin-bottom: 5px;'>Termin bestätigt!</h1>
                    <p style='color: #86868b; font-size: 16px;'>$companyName hat deinen Termin erfolgreich eingetragen.</p>
                </div>
                <div style='background-color: #f5f5f7; padding: 20px; border-radius: 14px; margin-bottom: 25px;'>
                    <table style='width: 100%; border-collapse: collapse;'>
                        <tr><td style='color: #86868b; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; padding-bottom: 5px;'>Was</td></tr>
                        <tr><td style='color: #1d1d1f; font-weight: 600; font-size: 17px; padding-bottom: 15px;'>$eventName</td></tr>
                        <tr><td style='color: #86868b; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; padding-bottom: 5px;'>Wann</td></tr>
                        <tr><td style='color: #1d1d1f; font-weight: 600; font-size: 17px;'>$formattedDateStr um $formattedTimeStr Uhr</td></tr>
                    </table>
                </div>
                <p style='color: #1d1d1f; font-size: 15px; line-height: 1.5; margin-bottom: 25px;'>Wir haben dir eine Kalender-Datei (.ics) angehängt, damit du den Termin mit einem Klick in dein Handy speichern kannst.</p>
                <div style='text-align: center; border-top: 1px solid #d2d2d7; padding-top: 25px;'>
                    <p style='color: #86868b; font-size: 13px; margin-bottom: 15px;'>Sollte etwas dazwischenkommen:</p>
                    <a href='$cancelLink' style='display: inline-block; background-color: #ff3b30; color: #ffffff; text-decoration: none; padding: 12px 25px; border-radius: 10px; font-weight: 600; font-size: 14px;'>Termin stornieren</a>
                </div>
            </div>";
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
    }

} catch (Exception $e) {
    if (http_response_code() === 200) { // Wenn kein spezifischer Fehlercode gesetzt wurde
        http_response_code(400); // Bad Request
    }
    echo json_encode(['error' => $e->getMessage()]);
}