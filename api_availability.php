<?php
// api_availability.php

require_once 'config.php';

// Wir geben JSON zurück
header('Content-Type: application/json');

try {
    $db = getDb();

    // 1. Eingabedaten aus der URL holen und validieren
    $dateStr = $_GET['date'] ?? null;
    $eventId = filter_input(INPUT_GET, 'event_id', FILTER_VALIDATE_INT);

    if (!$dateStr || !$eventId) {
        throw new Exception("Datum oder Event-ID fehlt.");
    }

    $targetDate = new DateTime($dateStr);

    // 2. Event-Details und Arbeitszeiten aus der DB holen
    $stmt = $db->prepare("SELECT duration_minutes, max_capacity, buffer_minutes, schedule_json, notice_min_hours FROM event_types WHERE id = ?");
    $stmt->execute([$eventId]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);

    $settingsStmt = $db->query("SELECT work_start_time, work_end_time FROM settings LIMIT 1");
    $settings = $settingsStmt->fetch(PDO::FETCH_ASSOC);

    if (!$event || !$settings) {
        throw new Exception("Konfiguration nicht gefunden.");
    }

    // 2.5 Individuelle Zeiten und Wochentage prüfen
    $schedule = $event['schedule_json'] ? json_decode($event['schedule_json'], true) : null;
    $useGlobal = $schedule['use_global'] ?? true;
    
    if ($useGlobal) {
        $startTimeStr = $settings['work_start_time'];
        $endTimeStr = $settings['work_end_time'];
        $activeDays = [1, 2, 3, 4, 5, 6]; // Standardmäßig Mo-Sa erlaubt
    } else {
        $startTimeStr = $schedule['start_time'] ?? $settings['work_start_time'];
        $endTimeStr = $schedule['end_time'] ?? $settings['work_end_time'];
        $activeDays = $schedule['active_days'] ?? [1, 2, 3, 4, 5, 6];
    }

    // Wenn der angefragte Wochentag nicht aktiv ist, gib leere Liste zurück
    $requestedDayOfWeek = (int)$targetDate->format('w');
    if (!in_array($requestedDayOfWeek, $activeDays)) {
        echo json_encode(['available_slots' => []]);
        exit;
    }

    $maxCapacity = $event['max_capacity'] ?? 1;
    $duration = $event['duration_minutes'];
    $buffer = $event['buffer_minutes'] ?? 0;
    $noticeMinHours = $event['notice_min_hours'] ?? 24;
    $workStart = new DateTime($targetDate->format('Y-m-d') . ' ' . $startTimeStr);
    $workEnd = new DateTime($targetDate->format('Y-m-d') . ' ' . $endTimeStr);

    // 3. Bestehende Buchungen für den Tag laden
    $bookingStmt = $db->prepare("
        SELECT b.start_time, e.duration_minutes, e.buffer_minutes 
        FROM bookings b 
        JOIN event_types e ON b.event_type_id = e.id 
        WHERE DATE(b.start_time) = ?
    ");
    $bookingStmt->execute([$targetDate->format('Y-m-d')]);
    $existingBookings = $bookingStmt->fetchAll(PDO::FETCH_ASSOC);

    $availableSlots = [];
    $currentTime = clone $workStart;
    $now = new DateTime();
    $minAllowedTime = (clone $now)->modify("+$noticeMinHours hours");

    // 4. Alle möglichen Slots durchgehen und prüfen
    while ($currentTime < $workEnd) {
        $slotEnd = (clone $currentTime)->modify("+" . ($duration + $buffer) . " minutes");
        if ($slotEnd > $workEnd) break; // Slot passt nicht mehr in den Arbeitstag

        $overlappingCount = 0;
        // Überschneidungsprüfung
        foreach ($existingBookings as $booking) {
            $b_start = new DateTime($booking['start_time']);
            $b_duration = $booking['duration_minutes'];
            $b_buffer = $booking['buffer_minutes'] ?? 0;
            $b_end = (clone $b_start)->modify("+" . ($b_duration + $b_buffer) . " minutes");

            if ($currentTime < $b_end && $slotEnd > $b_start) {
                $overlappingCount++;
            }
        }

        // Nur freie Slots in der Zukunft hinzufügen
        if ($overlappingCount < $maxCapacity && $currentTime > $minAllowedTime) {
            $availableSlots[] = [
                'time' => $currentTime->format('H:i'),
                'spots_left' => $maxCapacity - $overlappingCount,
                'max_capacity' => $maxCapacity
            ];
        }

        // Zum nächsten möglichen Slot springen (Dauer + Pufferzeit)
        $currentTime->modify("+" . ($duration + $buffer) . " minutes");
    }

    echo json_encode(['available_slots' => $availableSlots]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}