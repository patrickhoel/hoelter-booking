<?php
// Datenbank-Verbindung laden
require_once 'config.php';
$db = getDb();

// Session und CSRF für die öffentliche Buchungsseite aktivieren
$csrfToken = initCsrfToken();

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
$settingsStmt = $db->query("SELECT company_name, company_link_impressum, company_link_privacy, company_link_agb, company_address, widget_accent_color, company_logo, theme_mode FROM settings LIMIT 1");
$sysSettings = $settingsStmt->fetch(PDO::FETCH_ASSOC);
$companyName = $sysSettings['company_name'] ?? 'Planago Booking';
$impressumLink = $sysSettings['company_link_impressum'] ?? '';
$privacyLink = $sysSettings['company_link_privacy'] ?? '';
$agbLink = $sysSettings['company_link_agb'] ?? '';
$companyAddress = $sysSettings['company_address'] ?? '';
$accentColor = $sysSettings['widget_accent_color'] ?? '#34c759';
$companyLogo = $sysSettings['company_logo'] ?? '';
$themeMode = $sysSettings['theme_mode'] ?? 'auto';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planago - Termin buchen</title>
    
    <!-- Globaler CSRF Token für JS Fetch-Requests -->
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken) ?>">

    <!-- Flatpickr CSS & Deutsche Sprache laden -->
    <link id="flatpickr-theme" rel="stylesheet" href="assets/flatpickr/flatpickr.min.css?v=<?= PLANAGO_VERSION ?>">
    <script nonce="<?= htmlspecialchars(CSP_NONCE) ?>" src="assets/flatpickr/flatpickr.min.js?v=<?= PLANAGO_VERSION ?>"></script>
    <script nonce="<?= htmlspecialchars(CSP_NONCE) ?>" src="assets/flatpickr/de.js?v=<?= PLANAGO_VERSION ?>"></script>

    <!-- Planago "Apple Vibe" Stylesheet -->
    <link rel="stylesheet" href="assets/style.css">
    
    <script nonce="<?= htmlspecialchars(CSP_NONCE) ?>">
        // --- THEME & FLATPICKR LOGIK ---
        const themeMode = '<?= $themeMode ?>';
        const isDemoMode = <?= (defined('PLANAGO_DEMO_MODE') && PLANAGO_DEMO_MODE) ? 'true' : 'false' ?>;
        
        function updateTheme(forcedTheme = null) {
            const fpThemeLink = document.getElementById('flatpickr-theme');
            let isDark = false;
            
            if (forcedTheme) {
                isDark = (forcedTheme === 'dark');
            } else if (isDemoMode) {
                const savedTheme = localStorage.getItem('planago-theme');
                if (savedTheme === 'dark') isDark = true;
                else if (savedTheme === 'light') isDark = false;
                else isDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
            } else {
                if (themeMode === 'dark') {
                    isDark = true;
                } else if (themeMode === 'light') {
                    isDark = false;
                } else {
                    isDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
                }
            }
            
            // Flatpickr Theme wechseln
            if (fpThemeLink) {
                fpThemeLink.href = isDark 
                    ? "assets/flatpickr/dark.css?v=<?= PLANAGO_VERSION ?>" 
                    : "assets/flatpickr/flatpickr.min.css?v=<?= PLANAGO_VERSION ?>";
            }
            
            // Falls Auto-Mode: Wir setzen ein Attribut auf den Body, damit wir notfalls CSS steuern können
            document.documentElement.setAttribute('data-theme', isDark ? 'dark' : 'light');
        }

        updateTheme();
        if (window.matchMedia) {
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
                if (!isDemoMode && themeMode === 'auto') updateTheme();
                else if (isDemoMode && !localStorage.getItem('planago-theme')) updateTheme();
            });
        }
        
        // Immer auf Theme-Wechsel vom Parent (z.B. Landingpage) hören!
        window.addEventListener('message', (e) => {
            if (e.data && e.data.type === 'theme-change') {
                if (isDemoMode) localStorage.setItem('planago-theme', e.data.theme);
                updateTheme(e.data.theme);
            }
        });

        if (isDemoMode) {
            window.addEventListener('storage', (e) => {
                if (e.key === 'planago-theme') updateTheme();
            });
        }
    </script>

    <style nonce="<?= htmlspecialchars(CSP_NONCE) ?>">
        /* Überschreibt die globalen Variablen mit der gewählten Admin-Farbe */
        :root {
            --accent: <?= htmlspecialchars($accentColor) ?>;
            --accent-hover: <?= htmlspecialchars($accentColor) ?>;
        }
        
        <?php if ($themeMode === 'light'): ?>
        /* Erzwungener Light Mode */
        :root, body, html {
            color-scheme: light !important;
            --bg-body: transparent !important;
            --card-bg: #ffffff !important;
            --text-main: #1d1d1f !important;
            --text-muted: #86868b !important;
            --border: #e5e5ea !important;
            --input-bg: #f5f5f7 !important;
            --slot-bg: #ffffff !important;
            --slot-hover: #f2fbf4 !important;
        }
        <?php elseif ($themeMode === 'dark'): ?>
        /* Erzwungener Dark Mode */
        :root, body, html {
            color-scheme: dark !important;
            --bg-body: transparent !important;
            --card-bg: #1c1c1e !important;
            --text-main: #f5f5f7 !important;
            --text-muted: #a1a1a6 !important;
            --border: #38383a !important;
            --input-bg: #1c1c1e !important; /* Etwas dunkler als Surface für Inputs */
            --slot-bg: #1c1c1e !important;
            --slot-hover: #2c2c2e !important;
        }
        <?php endif; ?>
        
        <?php if (defined('PLANAGO_DEMO_MODE') && PLANAGO_DEMO_MODE): ?>
        /* --- DEMO MODE THEME OVERRIDES --- */
        :root[data-theme="light"], body[data-theme="light"], html[data-theme="light"] {
            color-scheme: light !important;
            --bg-body: transparent !important;
            --card-bg: #ffffff !important;
            --text-main: #1d1d1f !important;
            --text-muted: #86868b !important;
            --border: #e5e5ea !important;
            --input-bg: #f5f5f7 !important;
            --slot-bg: #ffffff !important;
            --slot-hover: #f2fbf4 !important;
        }
        :root[data-theme="dark"], body[data-theme="dark"], html[data-theme="dark"] {
            color-scheme: dark !important;
            --bg-body: transparent !important;
            --card-bg: #1c1c1e !important;
            --text-main: #f5f5f7 !important;
            --text-muted: #a1a1a6 !important;
            --border: #38383a !important;
            --input-bg: #1c1c1e !important;
            --slot-bg: #1c1c1e !important;
            --slot-hover: #2c2c2e !important;
        }
        <?php endif; ?>

        /* Passt den Glow-Schatten dynamisch an (Hex-Farbe + Transparenzwert) */
        .slot.selected { box-shadow: 0 4px 12px <?= htmlspecialchars($accentColor) ?>4D !important; }
        button[type="submit"]:hover { box-shadow: 0 4px 12px <?= htmlspecialchars($accentColor) ?>33 !important; filter: brightness(0.95); }
        
        /* CSP-sichere Helfer-Klassen */
        .text-center { text-align: center; }
        .mb-20 { margin-bottom: 20px; }
        .company-logo { max-height: 80px; max-width: 100%; border-radius: 8px; }
        .sr-only { position: absolute; left: -9999px; }
        .link-accent { color: var(--accent); text-decoration: none; }
        .error-text { color: red; grid-column: 1 / -1; margin: 0; text-align: center; }
    </style>
</head>
<body>

    <div class="container">
        <?php if (!empty($companyLogo)): ?>
            <div class="text-center mb-20">
                <img src="logo.php?v=<?= md5($companyLogo) ?>" alt="<?= htmlspecialchars($companyName) ?>" class="company-logo">
            </div>
        <?php endif; ?>

        <?php if ($isRescheduleMode): ?>
            <h2 class="text-center">Neuen Termin wählen</h2>
            <p class="text-center mb-20">Bitte wähle einen neuen Termin für <strong><?= htmlspecialchars($event['name']) ?></strong>.</p>
        <?php else: ?>
            <h2 class="text-center"><?= htmlspecialchars($event['name']) ?></h2>
            <p class="text-center mb-20">Dauer: <?= $event['duration_minutes'] ?> Minuten</p>
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
                <input type="text" id="datePicker" placeholder="Datum auswählen..." 
                    data-activedays="<?= htmlspecialchars(json_encode($activeDays)) ?>"
                    data-minhours="<?= $noticeMinHours ?>"
                    data-maxdays="<?= $noticeMaxDays ?>"
                    required>
            </div>
            
            <!-- Hier laden wir die freien Uhrzeiten rein -->
            <div class="form-group">
                <label>Verfügbare Zeiten</label>
                <div id="time-slots">
                    <p class="slot-placeholder">Bitte wähle zuerst ein Datum aus.</p>
                </div>
            </div>
            
            <!-- Dieser Teil wird erst nach Auswahl eines Slots sichtbar -->
            <div id="userDetailsForm" class="booking-form">
                <!-- Honeypot-Feld gegen Spam-Bots (für normale Nutzer unsichtbar) -->
                <div class="sr-only" aria-hidden="true">
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

                <div class="form-group privacy-consent-group">
                    <input type="checkbox" id="privacyConsent" name="privacyConsent" required>
                    <label for="privacyConsent" class="privacy-consent-label">
                        <?php if (!empty($agbLink)): ?>
                            Ich habe die <a href="<?= htmlspecialchars($agbLink) ?>" target="_blank" class="link-accent">AGB</a> und die <a href="<?= htmlspecialchars($privacyLink) ?>" target="_blank" class="link-accent">Datenschutzerklärung</a> zur Kenntnis genommen und stimme der Verarbeitung meiner Daten für die Terminbuchung zu.
                        <?php else: ?>
                            Ich habe die <a href="<?= htmlspecialchars($privacyLink) ?>" target="_blank" class="link-accent">Datenschutzerklärung</a> zur Kenntnis genommen und stimme der Verarbeitung meiner Daten für die Terminbuchung zu.
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
    <footer class="main-footer">
        <strong><?= htmlspecialchars($companyName) ?></strong><br>
        <?= nl2br(htmlspecialchars($companyAddress)) ?><br><br>
        
        <div class="footer-links">
            <?php if (!empty($agbLink)): ?>
                <a href="<?= htmlspecialchars($agbLink) ?>" target="_blank">AGB</a>
            <?php endif; ?>
            <?php if (!empty($impressumLink)): ?>
                <a href="<?= htmlspecialchars($impressumLink) ?>" target="_blank">Impressum</a>
            <?php endif; ?>
            <?php if (!empty($privacyLink)): ?>
                <a href="<?= htmlspecialchars($privacyLink) ?>" target="_blank">Datenschutz</a>
            <?php endif; ?>
        </div>
        
        <br><br>
        <span class="powered-by">Powered by <a href="https://planago.de" target="_blank" style="color: inherit; text-decoration: none;"><strong>Planago</strong></a></span>
    </footer>

    <!-- Cache-Buster im Script-Tag erzwingt das Neuladen der booking.js -->
    <script nonce="<?= htmlspecialchars(CSP_NONCE) ?>" src="assets/booking.js?v=<?= PLANAGO_VERSION ?>" defer></script>
</body>
</html>
