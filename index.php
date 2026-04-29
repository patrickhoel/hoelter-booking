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
        // Erzwinge, dass das Widget die richtige Terminart lädt
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
    // Fallback: Lade die erste aktive Terminart aus der Datenbank
    $stmt = $db->query("SELECT * FROM event_types WHERE is_active = 1 LIMIT 1");
}

$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    die("Diese Terminart existiert nicht oder ist inaktiv.");
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
$settingsStmt = $db->query("SELECT company_name, company_link_impressum, company_link_privacy, company_link_agb, company_address, widget_accent_color, company_logo FROM settings LIMIT 1");
$sysSettings = $settingsStmt->fetch(PDO::FETCH_ASSOC);
$companyName = $sysSettings['company_name'] ?? 'Planago Booking';
$impressumLink = $sysSettings['company_link_impressum'] ?? '';
$privacyLink = $sysSettings['company_link_privacy'] ?? '';
$agbLink = $sysSettings['company_link_agb'] ?? '';
$companyAddress = $sysSettings['company_address'] ?? '';
$accentColor = $sysSettings['widget_accent_color'] ?? '#34c759';
$companyLogo = $sysSettings['company_logo'] ?? '';
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
    
    <style>
        /* Überschreibt die globalen Variablen mit der gewählten Admin-Farbe */
        :root {
            --accent: <?= htmlspecialchars($accentColor) ?>;
            --accent-hover: <?= htmlspecialchars($accentColor) ?>;
        }
        /* Passt den Glow-Schatten dynamisch an (Hex-Farbe + Transparenzwert) */
        .slot.selected { box-shadow: 0 4px 12px <?= htmlspecialchars($accentColor) ?>4D !important; }
        button[type="submit"]:hover { box-shadow: 0 4px 12px <?= htmlspecialchars($accentColor) ?>33 !important; filter: brightness(0.95); }
    </style>
</head>
<body>

    <div class="container">
        <?php if (!empty($companyLogo)): ?>
            <div style="text-align: center; margin-bottom: 20px;">
                <img src="<?= htmlspecialchars($companyLogo) ?>" alt="<?= htmlspecialchars($companyName) ?>" style="max-height: 80px; max-width: 100%; border-radius: 8px;">
            </div>
        <?php endif; ?>

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
                <!-- Honeypot-Feld gegen Spam-Bots (für normale Nutzer unsichtbar) -->
                <div style="position: absolute; left: -9999px;" aria-hidden="true">
                    <label for="fax_number_hp">Faxnummer</label>
                    <input type="text" id="fax_number_hp" tabindex="-1" autocomplete="off">
                </div>

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

                <div class="form-group" style="display: flex; align-items: flex-start; gap: 10px; margin-top: 15px; margin-bottom: 20px;">
                    <input type="checkbox" id="privacyConsent" name="privacyConsent" required style="width: auto; margin-top: 3px; cursor: pointer;">
                    <label for="privacyConsent" style="font-size: 0.8rem; line-height: 1.4; color: var(--text-muted); font-weight: normal; margin: 0;">
                        <?php if (!empty($agbLink)): ?>
                            Ich habe die <a href="<?= htmlspecialchars($agbLink) ?>" target="_blank" style="color: var(--accent); text-decoration: none;">AGB</a> und die <a href="<?= htmlspecialchars($privacyLink) ?>" target="_blank" style="color: var(--accent); text-decoration: none;">Datenschutzerklärung</a> zur Kenntnis genommen und stimme der Verarbeitung meiner Daten für die Terminbuchung zu.
                        <?php else: ?>
                            Ich habe die <a href="<?= htmlspecialchars($privacyLink) ?>" target="_blank" style="color: var(--accent); text-decoration: none;">Datenschutzerklärung</a> zur Kenntnis genommen und stimme der Verarbeitung meiner Daten für die Terminbuchung zu.
                        <?php endif; ?>
                    </label>
                </div>

                <input type="hidden" id="selectedTime" required>
                <button type="submit"><?= $isRescheduleMode ? 'Neuen Termin bestätigen' : 'Jetzt verbindlich buchen' ?></button>
            </div>
        </form>
        
        <div id="message"></div>
    </div>

    <!-- Eleganter Planago Corporate Footer -->
    <div style="text-align: center; margin-top: 40px; padding-top: 20px; border-top: 1px solid var(--border); color: var(--text-muted); font-size: 12px; line-height: 1.6;">
        <strong><?= htmlspecialchars($companyName) ?></strong><br>
        <?= nl2br(htmlspecialchars($companyAddress)) ?><br><br>
        
        <?php if (!empty($agbLink)): ?>
            <a href="<?= htmlspecialchars($agbLink) ?>" target="_blank" style="color: var(--text-muted); text-decoration: none; margin: 0 10px; transition: color 0.2s;" onmouseover="this.style.color='var(--text-main)'" onmouseout="this.style.color='var(--text-muted)'">AGB</a>
        <?php endif; ?>
        <?php if (!empty($impressumLink)): ?>
            <a href="<?= htmlspecialchars($impressumLink) ?>" target="_blank" style="color: var(--text-muted); text-decoration: none; margin: 0 10px; transition: color 0.2s;" onmouseover="this.style.color='var(--text-main)'" onmouseout="this.style.color='var(--text-muted)'">Impressum</a>
        <?php endif; ?>
        <?php if (!empty($privacyLink)): ?>
            <a href="<?= htmlspecialchars($privacyLink) ?>" target="_blank" style="color: var(--text-muted); text-decoration: none; margin: 0 10px; transition: color 0.2s;" onmouseover="this.style.color='var(--text-main)'" onmouseout="this.style.color='var(--text-muted)'">Datenschutz</a>
        <?php endif; ?>
        
        <br><br>
        <span style="color: #d2d2d7;">Powered by <strong>Planago</strong></span>
    </div>

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
                custom_data: customData,
                honeypot: document.getElementById('fax_number_hp').value
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
