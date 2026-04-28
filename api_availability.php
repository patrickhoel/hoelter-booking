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
    $stmt = $db->prepare("SELECT duration_minutes FROM event_types WHERE id = ?");
    $stmt->execute([$eventId]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);

    $settingsStmt = $db->query("SELECT work_start_time, work_end_time FROM settings LIMIT 1");
    $settings = $settingsStmt->fetch(PDO::FETCH_ASSOC);

    if (!$event || !$settings) {
        throw new Exception("Konfiguration nicht gefunden.");
    }

    $duration = $event['duration_minutes'];
    $workStart = new DateTime($targetDate->format('Y-m-d') . ' ' . $settings['work_start_time']);
    $workEnd = new DateTime($targetDate->format('Y-m-d') . ' ' . $settings['work_end_time']);

    // 3. Bestehende Buchungen für den Tag laden
    $bookingStmt = $db->prepare("SELECT start_time FROM bookings WHERE DATE(start_time) = ?");
    $bookingStmt->execute([$targetDate->format('Y-m-d')]);
    $existingBookings = $bookingStmt->fetchAll(PDO::FETCH_ASSOC);

    $availableSlots = [];
    $currentTime = clone $workStart;
    $now = new DateTime();

    // 4. Alle möglichen Slots durchgehen und prüfen
    while ($currentTime < $workEnd) {
        $slotEnd = (clone $currentTime)->modify("+$duration minutes");
        if ($slotEnd > $workEnd) break; // Slot passt nicht mehr in den Arbeitstag

        $isFree = true;
        // Überschneidungsprüfung
        foreach ($existingBookings as $booking) {
            $b_start = new DateTime($booking['start_time']);
            $b_end = (clone $b_start)->modify("+$duration minutes");

            if ($currentTime < $b_end && $slotEnd > $b_start) {
                $isFree = false;
                break;
            }
        }

        // Nur freie Slots in der Zukunft hinzufügen
        if ($isFree && $currentTime > $now) {
            $availableSlots[] = $currentTime->format('H:i');
        }

        // Zum nächsten möglichen Slot springen (z.B. alle 30 Min)
        $currentTime->modify('+30 minutes');
    }

    echo json_encode(['available_slots' => $availableSlots]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}