<?php
// Datenbank-Verbindung laden
require_once 'config.php';
$db = getDb();

// --- NEU: Logik für den "Umbuchen"-Modus ---
$rescheduleId = filter_input(INPUT_GET, 'reschedule', FILTER_VALIDATE_INT);
$rescheduleToken = filter_input(INPUT_GET, 'token', FILTER_SANITIZE_STRING);
$rescheduleBooking = null;
$isRescheduleMode = false;
$eventId = null;

if ($rescheduleId && $rescheduleToken) {
    // Finde die ursprüngliche Buchung, die verschoben werden soll
    $stmt = $db->prepare("SELECT * FROM bookings WHERE id = ? AND cancel_token = ? AND status = 'reschedule_requested'");
    $stmt->execute([$rescheduleId, $rescheduleToken]);
    $rescheduleBooking = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($rescheduleBooking) {
        $isRescheduleMode = true;
        // Erzwinge, dass das Widget das richtige Training lädt
        $eventId = $rescheduleBooking['event_type_id'];
    }
}

if (!$eventId) {
    // Normaler Modus: Prüfen, ob eine spezifische Event-ID über die URL übergeben wurde (?event_id=2)
    $eventId = filter_input(INPUT_GET, 'event_id', FILTER_VALIDATE_INT);
}

if ($eventId) { // Lade das spezifische Event
    $stmt = $db->prepare("SELECT * FROM event_types WHERE id = ? AND is_active = 1");
    $stmt->execute([$eventId]);
} else {
    // Fallback: Lade das erste aktive Training aus der Datenbank
    $stmt = $db->query("SELECT * FROM event_types WHERE is_active = 1 LIMIT 1");
}

$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    die("Dieses Training existiert nicht oder ist inaktiv.");
}

// JSON-Einstellungen entpacken, um die aktiven Wochentage herauszufinden
$schedule = $event['schedule_json'] ? json_decode($event['schedule_json'], true) : null;
$useGlobal = $schedule['use_global'] ?? true;
// Standard: Montag (1) bis Samstag (6)
$activeDays = $useGlobal ? [1, 2, 3, 4, 5, 6] : ($schedule['active_days'] ?? [1, 2, 3, 4, 5, 6]);

// Eigene Formularfelder entpacken
$formFields = $event['form_fields_json'] ? json_decode($event['form_fields_json'], true) : [];

// Buchungszeitraum
$noticeMinHours = $event['notice_min_hours'] ?? 24;
$noticeMaxDays = $event['notice_max_days'] ?? 60;

// --- NEU: Impressum & Datenschutz auslesen ---
$settingsStmt = $db->query("SELECT company_link_impressum, company_link_privacy FROM settings LIMIT 1");
$sysSettings = $settingsStmt->fetch(PDO::FETCH_ASSOC);
$impressumLink = $sysSettings['company_link_impressum'] ?? '';
$privacyLink = $sysSettings['company_link_privacy'] ?? '';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planago - Termin buchen</title>
    
    <!-- Flatpickr CSS & Deutsche Sprache laden -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://npmcdn.com/flatpickr/dist/l10n/de.js"></script>

    <!-- Planago "Apple Vibe" Stylesheet -->
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

    <div class="container">
        <?php if ($isRescheduleMode): ?>
            <h2 style="text-align: center;">Neuen Termin wählen</h2>
            <p style="text-align: center; margin-bottom: 20px;">Bitte wähle einen neuen Termin für <strong><?= htmlspecialchars($event['name']) ?></strong>.</p>
        <?php else: ?>
            <h2 style="text-align: center;"><?= htmlspecialchars($event['name']) ?></h2>
            <p style="text-align: center; margin-bottom: 20px;">Dauer: <?= $event['duration_minutes'] ?> Minuten</p>
        <?php endif; ?>
        
        <form id="bookingForm">
            <input type="hidden" id="eventId" value="<?= $event['id'] ?>">
            <!-- Versteckte Felder für den Umbuchungs-Modus -->
            <?php if ($isRescheduleMode): ?>
                <input type="hidden" id="rescheduleId" value="<?= $rescheduleBooking['id'] ?>">
                <input type="hidden" id="rescheduleToken" value="<?= $rescheduleBooking['cancel_token'] ?>">
            <?php endif; ?>
            
            <div class="form-group">
                <label for="datePicker">Wähle ein Datum</label>
                <input type="text" id="datePicker" placeholder="Datum auswählen..." required>
            </div>
            
            <!-- Hier laden wir die freien Uhrzeiten rein -->
            <div class="form-group">
                <label>Verfügbare Zeiten</label>
                <div id="time-slots">
                    <p style="font-size: 13px; grid-column: span 3;">Bitte wähle zuerst ein Datum aus.</p>
                </div>
            </div>
            
            <!-- Dieser Teil wird erst nach Auswahl eines Slots sichtbar -->
            <div id="userDetailsForm" class="booking-form">
                <div class="form-group">
                    <label for="name">Dein Name</label> <!-- Im Umbuchungs-Modus wird das Feld per JS befüllt und gesperrt -->
                    <input type="text" id="name" placeholder="Max Mustermann" required <?= $isRescheduleMode ? 'value="' . htmlspecialchars($rescheduleBooking['customer_name']) . '" readonly' : '' ?>>
                </div>
                
                <div class="form-group">
                    <label for="email">Deine E-Mail</label> <!-- Im Umbuchungs-Modus wird das Feld per JS befüllt und gesperrt -->
                    <input type="email" id="email" placeholder="max@beispiel.de" required <?= $isRescheduleMode ? 'value="' . htmlspecialchars($rescheduleBooking['customer_email']) . '" readonly' : '' ?>>
                </div>
                
                <!-- Dynamische Formularfelder laden -->
                <?php foreach ($formFields as $field): ?>
                    <div class="form-group">
                        <label><?= htmlspecialchars($field['label']) ?><?= $field['required'] ? ' *' : '' ?></label>
                        <?php if ($field['type'] === 'textarea'): ?>
                            <textarea class="custom-input" data-label="<?= htmlspecialchars($field['label']) ?>" rows="3" <?= $field['required'] ? 'required' : '' ?>></textarea>
                        <?php else: ?>
                            <input type="<?= $field['type'] === 'number' ? 'number' : 'text' ?>" class="custom-input" data-label="<?= htmlspecialchars($field['label']) ?>" <?= $field['required'] ? 'required' : '' ?>>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <input type="hidden" id="selectedTime" required>
                <button type="submit"><?= $isRescheduleMode ? 'Neuen Termin bestätigen' : 'Jetzt verbindlich buchen' ?></button>
            </div>
        </form>
        
        <div id="message"></div>
    </div>

    <!-- NEU: Impressum & Datenschutz Links -->
    <?php if (!empty($impressumLink) || !empty($privacyLink)): ?>
        <div style="text-align: center; margin-top: 20px; font-size: 12px;">
            <?php if (!empty($impressumLink)): ?>
                <a href="<?= htmlspecialchars($impressumLink) ?>" target="_blank" style="color: var(--text-muted); text-decoration: none; margin: 0 10px; transition: opacity 0.2s;" onmouseover="this.style.opacity='0.7'" onmouseout="this.style.opacity='1'">Impressum</a>
            <?php endif; ?>
            <?php if (!empty($privacyLink)): ?>
                <a href="<?= htmlspecialchars($privacyLink) ?>" target="_blank" style="color: var(--text-muted); text-decoration: none; margin: 0 10px; transition: opacity 0.2s;" onmouseover="this.style.opacity='0.7'" onmouseout="this.style.opacity='1'">Datenschutz</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <script>
        const eventId = document.getElementById('eventId').value;
        // Wir übergeben die aktiven Tage aus PHP an JavaScript
        const activeDays = <?= json_encode($activeDays) ?>;
        
        // Vorlaufzeit und maximaler Zeitraum berechnen
        const now = new Date();
        const minAllowedDate = new Date(now.getTime() + (<?= $noticeMinHours ?> * 60 * 60 * 1000));
        const maxAllowedDate = new Date(now.getTime() + (<?= $noticeMaxDays ?> * 24 * 60 * 60 * 1000));

        // Flatpickr initialisieren
        flatpickr("#datePicker", {
            locale: "de", // Auf Deutsch stellen
            minDate: minAllowedDate, // Man kann keine Termine in der Vergangenheit / Vorlaufzeit buchen
            maxDate: maxAllowedDate, // Maximaler Buchungszeitraum
            disable: [
                function(date) {
                    // Deaktiviere alle Tage, die NICHT in unserer activeDays Liste sind
                    return !activeDays.includes(date.getDay());
                }
            ],
            onChange: function(selectedDates, dateStr, instance) {
                // Wenn ein Datum angeklickt wird: Lade freie Termine
                fetch(`api_availability.php?event_id=${eventId}&date=${dateStr}`)
                    .then(r => r.json())
                    .then(data => {
                        const container = document.getElementById('time-slots');
                        container.innerHTML = ''; // Vorherige löschen
                        document.getElementById('selectedTime').value = ''; // Auswahl zurücksetzen
                        document.getElementById('userDetailsForm').style.display = 'none'; // Formular ausblenden
                        
                        if (data.available_slots.length === 0) {
                            container.innerHTML = '<p style="color: red; grid-column: span 3;">Keine freien Termine an diesem Tag.</p>';
                            return;
                        }
                        
                        data.available_slots.forEach(slot => {
                            const div = document.createElement('div');
                            div.className = 'slot';
                            
                            // Zusatztext "10/10 frei" anzeigen, wenn es ein Gruppentraining ist
                            if (slot.max_capacity > 1) {
                                div.innerHTML = `${slot.time}<br><span style="font-size:11px; font-weight:normal; color: var(--text-muted);">(${slot.spots_left}/${slot.max_capacity} frei)</span>`;
                            } else {
                                div.innerHTML = `${slot.time} Uhr`;
                            }
                            
                            div.onclick = function() {
                                document.querySelectorAll('.slot').forEach(el => el.classList.remove('selected'));
                                div.classList.add('selected');
                                document.getElementById('selectedTime').value = slot.time; // Uhrzeit merken
                                
                                // Formular einblenden und hinscrollen
                                const userForm = document.getElementById('userDetailsForm');
                                userForm.style.display = 'block';
                                userForm.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                            };
                            container.appendChild(div);
                        });
                    });
            }
        });

        // Wenn jemand auf "Buchen" klickt: Schicke die Daten an unser Backend
        document.getElementById('bookingForm').addEventListener('submit', function(e) {
            e.preventDefault(); // Verhindert das Neuladen der Seite
            
            const time = document.getElementById('selectedTime').value;
            if (!time) {
                alert("Bitte wähle eine Uhrzeit aus!");
                return;
            }

            // Datum und Uhrzeit zusammensetzen für unser Backend (Format: YYYY-MM-DDTHH:MM:00)
            const date = document.getElementById('datePicker').value;
            const finalDateTime = `${date}T${time}:00`;

            // Benutzerdefinierte Felder sammeln
            const customData = {};
            document.querySelectorAll('.custom-input').forEach(input => {
                customData[input.getAttribute('data-label')] = input.value;
            });

            // Wir sammeln die Eingaben aus den Feldern
            const data = {
                event_type_id: parseInt(document.getElementById('eventId').value),
                customer_name: document.getElementById('name').value,
                customer_email: document.getElementById('email').value,
                start_time: finalDateTime,
                custom_data: customData
            };

            // Füge Umbuchungs-Infos hinzu, falls vorhanden
            const rescheduleIdInput = document.getElementById('rescheduleId');
            if (rescheduleIdInput) {
                data.reschedule_id = parseInt(rescheduleIdInput.value);
                data.reschedule_token = document.getElementById('rescheduleToken').value;
            }

            // Wir senden die Daten per POST an unsere API
            fetch('api_book.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(result => {
                const msgDiv = document.getElementById('message');
                if(result.error) {
                    msgDiv.style.color = 'red';
                    msgDiv.innerText = result.error;
                } else {
                    msgDiv.style.color = 'green';
                    msgDiv.innerText = result.message;
                    document.getElementById('bookingForm').style.display = 'none'; // Komplettes Formular ausblenden
                }
            });
        });
    </script>
</body>
</html>
