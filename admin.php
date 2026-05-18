<?php
session_start();

// --- UPDATE-CLEANUP ---
// Löscht veraltete Dateileichen (.old.php), die beim Update-Vorgang auf Windows/XAMPP-Servern entstehen.
foreach (glob(__DIR__ . '/*.old.php') as $oldFile) {
    @unlink($oldFile);
}

// Login-Schutz: Wer nicht eingeloggt ist, fliegt raus!
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Lade Config für die Versionsnummer
require_once 'config.php';

// Basis-URL für das Widget ermitteln
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$basePath = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
$baseUrl = rtrim($protocol . "://" . $_SERVER['HTTP_HOST'] . $basePath, '/');

$csrfToken = initCsrfToken();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Planago - Admin Panel</title>
    <link rel="stylesheet" href="assets/admin_style.css">
    <style nonce="<?= htmlspecialchars(CSP_NONCE) ?>">
        /* Verhindert den horizontalen Scrollbalken bei schmalen iFrames/Handys */
        .tabs-container {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            overflow-x: hidden !important;
        }
        .tabs-container .tab-btn {
            flex: 1 1 auto;
        }
    </style>
    <?php if (defined('PLANAGO_DEMO_MODE') && PLANAGO_DEMO_MODE): ?>
    <script nonce="<?= htmlspecialchars(CSP_NONCE) ?>">
        function syncDemoTheme() {
            const theme = localStorage.getItem('planago-theme');
            if (theme === 'dark' || theme === 'light') {
                document.documentElement.setAttribute('data-theme', theme);
            }
        }
        syncDemoTheme();
        window.addEventListener('storage', (e) => { if (e.key === 'planago-theme') syncDemoTheme(); });
        window.addEventListener('message', (e) => {
            if (e.data && e.data.type === 'theme-change') {
                localStorage.setItem('planago-theme', e.data.theme); syncDemoTheme();
            }
        });
    </script>
    <?php endif; ?>
</head>
<body>
    <div class="admin-container">
        <!-- Globaler CSRF Token für JS Fetch-Requests -->
        <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken) ?>">

        <div class="flex-between-center">
            <div>
                <!-- Update Banner (wird per JS eingeblendet, wenn Update verfügbar) -->
                <div id="updateBanner" class="update-banner d-none" style="display: flex; flex-direction: column; align-items: stretch;">
                    <div class="flex-between-center w-100">
                        <div id="updateBannerText">
                            <strong id="updateBannerTitle">🚀 Neues Planago-Update verfügbar!</strong> 
                            <span id="updateBannerSub">Version <span id="newVersionNumber"></span> ist da.</span>
                            <a href="#" id="showReleaseNotesBtn" class="d-none" style="color: rgba(255,255,255,0.8); text-decoration: underline; font-size: 13px; margin-left: 10px;">Was ist neu?</a>
                        </div>
                        <button id="updateBtn" class="btn-update w-auto">Jetzt updaten</button>
                        <a href="https://planago.de" target="_blank" id="renewLicenseBtn" class="btn-update d-none w-auto" style="color: #d97706; text-decoration: none;">Lizenz verlängern</a>
                    </div>
                    <div id="releaseNotesContent" class="d-none mt-10 fs-14" style="background: rgba(0,0,0,0.15); padding: 12px 16px; border-radius: 8px; line-height: 1.5;"></div>
                </div>

                <h1>Admin Dashboard</h1>
                <p class="admin-subtitle">Eingeloggt als: <strong><?= htmlspecialchars($_SESSION['username']) ?></strong></p>
            </div>
            <a href="logout.php" class="btn-logout">Logout</a>
        </div>
        
        <?php if (defined('PLANAGO_DEMO_MODE') && PLANAGO_DEMO_MODE): ?>
            <div class="warning-box mb-20 mt-0 text-center">
                <strong class="warning-text fs-15">⚠️ Demo-Ansicht: Änderungen (Speichern, Löschen etc.) sind in dieser Version deaktiviert.</strong>
            </div>
        <?php endif; ?>

        <div class="tabs tabs-container">
            <button class="tab-btn active" id="btn-tab-bookings">Buchungen</button>
            <button class="tab-btn" id="btn-tab-events">Terminarten</button>
            <button class="tab-btn" id="btn-tab-process">Zeiten & Prozess</button>
            <button class="tab-btn" id="btn-tab-company">Profil & Design</button>
            <button class="tab-btn" id="btn-tab-integration">Integrationen & Automatisierung</button>
            <button class="tab-btn" id="btn-tab-email">E-Mail & SMTP</button>
        </div>
        
        <!-- Ein gemeinsames Formular umfasst nun alle Einstellungs-Tabs -->
        <form id="settingsForm" autocomplete="off">
            
            <!-- ZEITEN & PROZESS TAB -->
            <div id="tab-process" class="tab-content d-none">
                <div class="card">
                    <h2>Reguläre Arbeitszeiten</h2>
                    <p class="text-muted mt-0">Wann bist du grundsätzlich für Termine verfügbar?</p>
                    <div class="settings-group">
                        <div class="form-group">
                            <label for="startTime">Startzeit</label>
                            <input type="time" id="startTime" required>
                        </div>
                        <div class="form-group">
                            <label for="endTime">Endzeit</label>
                            <input type="time" id="endTime" required>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <h2>Buchungs-Ablauf</h2>
                    <label class="checkbox-label mb-0">
                        <input type="checkbox" id="requireManualConf" class="w-auto">
                        <strong>Zwei-Wege-Bestätigung (Manuelle Bestätigung durch Admin erforderlich)</strong>
                    </label>
                </div>
                
                <button type="submit" class="btn-success mt-20 w-auto">Zeiten & Prozess speichern</button>
                <div class="settingsMessage settings-msg"></div>
            </div>

            <!-- PROFIL & DESIGN TAB -->
            <div id="tab-company" class="tab-content d-none">
                <div class="card">
                    <h2>Unternehmensdaten</h2>
                    <div class="settings-group">
                        <div class="form-group">
                            <label>Unternehmensname</label>
                            <input type="text" id="companyName" placeholder="Z.B. Hundeschule Mustermann" required>
                        </div>
                        <div class="form-group">
                            <label>Telefon</label>
                            <input type="text" id="companyPhone" placeholder="+49 123 45678">
                        </div>
                        <div class="form-group full-width">
                            <label>Adresse / Anschrift</label>
                            <textarea id="companyAddress" rows="2" placeholder="Musterstraße 1..."></textarea>
                        </div>
                        <div class="form-group">
                            <label>Link zum Impressum</label>
                            <input type="text" id="companyLinkImpressum" placeholder="https://...">
                        </div>
                        <div class="form-group">
                            <label>Link zum Datenschutz</label>
                            <input type="text" id="companyLinkPrivacy" placeholder="https://...">
                        </div>
                        <div class="form-group">
                            <label>Link zu den AGB (Optional)</label>
                            <input type="text" id="companyLinkAgb" placeholder="https://...">
                        </div>
                    </div>
                </div>

                <div class="card">
                    <h2>Design & Branding</h2>
                    <div class="settings-group">
                        <div class="form-group full-width">
                            <label>Unternehmens-Logo</label>
                            <div class="gap-15 align-center">
                                <img id="logoPreview" src="" class="logo-preview">
                                <input type="file" id="companyLogoInput" accept="image/*" class="w-auto">
                                <button type="button" class="btn-danger btn-icon d-none w-auto" id="removeLogoBtn" style="height: 38px; padding: 0 15px;">Löschen</button>
                            </div>
                            <p class="fs-12 text-muted mt-5">Wird im Kunden-Widget und in den E-Mails angezeigt.</p>
                        </div>
                        <div class="form-group">
                            <label>Akzentfarbe (Kunden-Widget)</label>
                            <div class="gap-10 align-center">
                                <input type="color" id="widgetAccentColor" class="color-picker">
                                <button type="button" id="btn-reset-color" class="btn-color-reset">Standard</button>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Widget Design (Hell / Dunkel)</label>
                            <select id="themeMode" class="cursor-pointer">
                                <option value="auto">Auto (Passt sich dem Kunden-Gerät an)</option>
                                <option value="light">Immer Hell (Empfohlen für helle Websites)</option>
                                <option value="dark">Immer Dunkel (Empfohlen für dunkle Websites)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <h2>Admin Zugang</h2>
                    <div class="settings-group">
                        <div class="form-group">
                            <label>Benutzername</label>
                            <input type="text" id="adminUsername" required>
                        </div>
                        <div class="form-group">
                            <label>Neues Passwort (leer lassen, um es beizubehalten)</label>
                            <input type="password" id="adminNewPassword" placeholder="***" autocomplete="new-password">
                            <p class="fs-12 text-muted mt-5">Mindestens 12 Zeichen, inkl. Groß-, Kleinbuchstaben und Zahlen.</p>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-success mt-20 w-auto">Profil & Design speichern</button>
                <div class="settingsMessage settings-msg"></div>
            </div>

            <!-- INTEGRATIONEN & AUTOMATISIERUNG TAB -->
            <div id="tab-integration" class="tab-content d-none">
                <div class="card">
                    <h2>Automatisierung (Vor dem Termin)</h2>
                    <div class="settings-group">
                        <div class="form-group full-width">
                            <label class="checkbox-label">
                                <input type="checkbox" id="enableReminders" class="w-auto">
                                <strong>Automatische Termin-Erinnerungen per E-Mail aktivieren</strong>
                            </label>
                            <p class="fs-12 text-muted mt-0 mb-15">Sendet deinen Kunden automatisch eine Erinnerung vor ihrem Termin.</p>
                            
                            <div id="reminderOptionsContainer" class="d-none mb-20">
                                <label for="reminderHours">Wann soll die Erinnerung gesendet werden?</label>
                                <select id="reminderHours" class="w-auto cursor-pointer">
                                    <option value="24">24 Stunden vorher</option>
                                    <option value="12">12 Stunden vorher</option>
                                    <option value="6">6 Stunden vorher</option>
                                    <option value="1">1 Stunde vorher</option>
                                </select>
                            </div>
                             <div class="info-box">
                                <strong class="info-text">💡 Wie funktioniert das?</strong>
                                <p class="info-subtext">Um sicherzustellen, dass Erinnerungen auch dann versendet werden, wenn deine Website selten besucht wird, wird diese Funktion durch einen zentralen Server von Planago alle 15-30 Minuten angestoßen ("gepingt"). Dieser Service ist für dich kostenlos.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card">
                    <h2>Automatisierung (Nach der Buchung)</h2>
                    <div class="settings-group">
                        <div class="form-group full-width">
                            <label class="checkbox-label">
                                <input type="checkbox" id="enableReviewEmail" class="w-auto">
                                <strong>Automatische Google-Bewertungs-E-Mail aktivieren</strong>
                            </label>
                            <p class="fs-12 text-muted mt-0 mb-15">Sendet deinen Kunden 24 Stunden nach ihrem abgeschlossenen Termin automatisch eine freundliche E-Mail mit der Bitte um eine Bewertung.</p>
                            
                            <div id="reviewLinkContainer" class="d-none mb-20">
                                <label>Dein Google Bewertungs-Link (Kurz-URL)</label>
                                <input type="text" id="googleReviewLink" placeholder="https://g.page/r/...">
                            </div>
                        </div>
                        
                        <div class="form-group full-width border-top-dashed pt-15">
                            <label for="zapierWebhookUrl">Webhook URL (Zapier / Make.com) - Optional</label>
                            <input type="text" id="zapierWebhookUrl" placeholder="https://hooks.zapier.com/hooks/catch/...">
                            <p class="fs-13 text-muted mt-5" style="line-height: 1.5;">Verbinde Planago mit über 5.000 Apps. Erstelle einen Webhook-Trigger in Zapier/Make und füge die URL hier ein. Bei jeder Buchung werden die Daten dorthin gesendet.</p>
                            <div class="warning-box mt-10">
                                <strong class="warning-text">⚠️ Wichtiger Datenschutz-Hinweis (DSGVO):</strong>
                                <p class="warning-subtext">Sobald du hier eine URL einträgst, werden Kundendaten an externe Anbieter gesendet. Du musst dies zwingend in deiner Datenschutzerklärung angeben!</p>
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn-success mt-20 w-auto">Automatisierungen speichern</button>
                    <div class="settingsMessage settings-msg"></div>
                </div>

                <div class="card">
                    <h2>Links & Widget auf der Website einbinden</h2>
                    <p class="mt-0 text-muted">Wähle hier eine Terminart aus, um den passenden direkten Link oder den HTML-Code für deine Website (z. B. WordPress, Wix) zu generieren.</p>

                    <div class="form-group mt-25 pb-15 border-bottom-dashed">
                        <label for="widgetEventSelect" class="fs-15 text-main">Für welche Terminart möchtest du den Link/Code erstellen?</label>
                        <select id="widgetEventSelect" class="max-w-400 font-bold cursor-pointer">
                            <option value="">Lade Terminarten...</option>
                        </select>
                    </div>

                    <h3 class="mt-25">1. Direkter Link</h3>
                    <p class="text-muted mt-0 fs-14">Teile diesen Link auf Instagram, WhatsApp oder verlinke ihn auf einem Button deiner Website.</p>
                    <div class="gap-10">
                        <input type="text" id="directLink" readonly class="copy-input">
                        <button type="button" class="btn-secondary w-auto" id="btn-copy-direct">Kopieren</button>
                    </div>

                    <h3 class="mt-25">2. Widget einbinden (iFrame)</h3>
                    <p class="text-muted mt-0 fs-14">Kopiere diesen HTML-Code und füge ihn auf deiner Website in einen "HTML" oder "Code" Block ein.</p>
                    <div class="gap-10 align-start">
                        <textarea id="iframeCode" readonly rows="4" class="iframe-textarea"></textarea>
                        <button type="button" class="btn-secondary w-auto" id="btn-copy-iframe">Kopieren</button>
                    </div>
                </div>

                <div class="card">
                    <h2>Kalender-Synchronisation (Abo-Link)</h2>
                    <p class="text-muted mt-0 fs-14">Kopiere diesen Link und füge ihn bei <strong>Apple Kalender</strong>, <strong>Google Kalender</strong> oder <strong>Outlook</strong> unter "Kalender abonnieren / Aus URL hinzufügen" ein. Danach erscheinen alle Planago-Buchungen automatisch in deinem Kalender!</p>
                    <div class="gap-10 mt-15">
                        <input type="text" id="icalLink" readonly class="copy-input">
                        <button type="button" class="btn-secondary w-auto" id="btn-copy-ical">Kopieren</button>
                    </div>
                </div>
            </div>

            <!-- E-MAIL & SMTP TAB -->
            <div id="tab-email" class="tab-content d-none">
                <div class="card">
                    <h2>E-Mail & SMTP Server</h2>
                    <p class="text-muted mt-0 mb-20">Hinterlege hier deine E-Mail-Adresse und optional deine SMTP-Zugangsdaten, damit Planago Bestätigungen an Kunden versenden kann.</p>
                    <div class="settings-group">
                        <div class="form-group full-width mb-15">
                            <label>Admin E-Mail <span style="font-weight: normal; color: #888;">(Hierhin gehen Benachrichtigungen bei neuen Buchungen)</span></label>
                            <input type="email" id="adminEmail" placeholder="admin@deinedomain.de">
                        </div>
                        <div class="form-group">
                            <label>Absender Name</label>
                            <input type="text" id="smtpFromName" placeholder="Z.B. Planago Booking">
                        </div>
                        <div class="form-group">
                            <label>Absender E-Mail <span style="font-weight: normal; color: #888;">(Zwingend erforderlich)</span></label>
                            <input type="email" id="smtpFromEmail" placeholder="info@deinedomain.de">
                        </div>
                        
                        <div class="full-width border-top-dashed mt-10 pt-15 mb-10">
                            <strong style="color: var(--text-main);">Eigener SMTP-Server (Optional)</strong>
                            <p class="text-muted fs-13 mt-5 mb-0">Wenn du die Felder unten leer lässt, verschickt der Server E-Mails über seine interne Funktion (Empfohlen für IONOS/Strato, um Firewall-Probleme zu umgehen).</p>
                        </div>
                        <div class="form-group">
                            <label>SMTP Host</label>
                            <input type="text" id="smtpHost" placeholder="smtp.deinedomain.de">
                        </div>
                        <div class="form-group">
                            <label>SMTP Port</label>
                            <input type="text" id="smtpPort" placeholder="465 oder 587">
                        </div>
                        <div class="form-group">
                            <label>Benutzername</label>
                            <input type="text" id="smtpUser">
                        </div>
                        <div class="form-group">
                            <label>Passwort</label>
                            <input type="password" id="smtpPass" autocomplete="new-password">
                        </div>
                    </div>
                    <div class="gap-10 mt-20">
                        <button type="submit" class="btn-success mt-0 w-auto">E-Mail-Einstellungen speichern</button>
                        <button type="button" class="btn-secondary mt-0 w-auto" id="btn-test-email">Test-E-Mail senden</button>
                    </div>
                    <div class="settingsMessage settings-msg"></div>
                </div>
            </div>
        </form>

        <div class="card tab-content d-none" id="tab-events">
            <h2>Terminarten</h2>
            <form id="eventForm">
                <div class="gap-20 align-end">
                    <div class="form-group">
                        <label for="eventName">Name (z.B. Erstgespräch)</label>
                        <input type="text" id="eventName" required>
                    </div>
                    <div class="form-group">
                        <label for="eventDuration">Dauer (Minuten)</label>
                        <input type="number" id="eventDuration" value="60" required class="w-100px">
                    </div>
                    <div class="form-group">
                        <button type="submit">Hinzufügen</button>
                    </div>
                </div>
                <div id="eventMessage" class="font-bold text-success mt-10"></div>
            </form>
            
            <div class="table-responsive">
                <table class="mt-0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Dauer</th>
                            <th>Plätze</th>
                            <th>Puffer</th>
                            <th>Link für Website</th>
                            <th>Aktion</th>
                        </tr>
                    </thead>
                    <tbody id="eventsTableBody">
                        <tr><td colspan="6">Lade Terminarten...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card tab-content" id="tab-bookings">
            <div class="flex-between-center border-bottom pb-12 mb-24">
                <h2 class="m-0 border-none p-0">Buchungen</h2>
                <select id="bookingFilter" class="w-auto cursor-pointer" style="padding: 8px 15px; border-radius: 8px; font-weight: 600;">
                    <option value="upcoming">Zukünftige Termine</option>
                    <option value="past">Vergangene Termine</option>
                    <option value="all">Alle Termine</option>
                </select>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Datum & Uhrzeit</th>
                            <th>Terminart</th>
                            <th>Kunde</th>
                            <th>E-Mail</th>
                            <th>Zusatzinfos</th>
                            <th>Aktion</th>
                        </tr>
                    </thead>
                    <tbody id="bookingsTableBody">
                        <tr><td colspan="6">Lade Buchungen...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="powered-by">
            Powered by <a href="https://planago.de" target="_blank">Planago</a>
        </div>
    </div>
    
    <!-- EINSTELLUNGEN MODAL (Unsichtbar bis man auf "Einstellen" klickt) -->
    <div id="eventSettingsModal" class="modal">
        <div class="modal-content">
            <span class="close" id="btnCloseModal">&times;</span>
            <h2 id="modalEventName" class="mt-0" style="color: var(--accent);">Einstellungen</h2>

            <div class="card card-no-shadow">
                <h3 class="mt-0">Kapazität & Puffer</h3>
                <div class="gap-15">
                    <label class="font-normal">Plätze (Max): <input type="number" id="modalCapacity" class="w-80 p-5"></label>
                    <label class="font-normal">Pufferzeit (Min): <input type="number" id="modalBuffer" class="w-80 p-5"></label>
                </div>
            </div>

            <div class="card card-no-shadow">
                <h3 class="mt-0">Buchungszeitraum</h3>
                <div class="gap-15">
                    <label class="font-normal">Vorlaufzeit (Stunden): <input type="number" id="modalMinNotice" class="w-80 p-5" title="Wie viele Stunden im Voraus muss mindestens gebucht werden?"></label>
                    <label class="font-normal">Max. im Voraus (Tage): <input type="number" id="modalMaxNotice" class="w-80 p-5" title="Wie viele Tage in die Zukunft können Termine maximal gebucht werden?"></label>
                </div>
            </div>

            <div class="card card-no-shadow">
                <h3 class="mt-0">Stornierungsbedingungen</h3>
                <div class="gap-15">
                    <label class="font-normal">Bis wie viele Stunden vor dem Termin darf der Kunde absagen?<br> 
                    <input type="number" id="modalCancelLimit" class="w-80 p-5 mt-5" title="Stunden"> Stunden</label>
                </div>
            </div>

            <div class="card card-no-shadow">
                <h3 class="mt-0">Buchungszeiten</h3>
                <label class="checkbox-label mb-0">
                    <input type="checkbox" id="useGlobalSchedule" class="w-auto">
                    <strong>Globale Öffnungszeiten für diese Terminart übernehmen</strong>
                </label>
                <div id="customScheduleOptions" class="d-none mt-15 pt-15 border-top-dashed">
                    <p class="mt-0 fs-14 text-muted">An welchen Tagen findet dieser Termin statt?</p>
                    <div class="gap-10 mb-15 flex-wrap">
                        <label class="font-normal cursor-pointer"><input type="checkbox" class="day-checkbox" value="1"> Mo</label>
                        <label class="font-normal cursor-pointer"><input type="checkbox" class="day-checkbox" value="2"> Di</label>
                        <label class="font-normal cursor-pointer"><input type="checkbox" class="day-checkbox" value="3"> Mi</label>
                        <label class="font-normal cursor-pointer"><input type="checkbox" class="day-checkbox" value="4"> Do</label>
                        <label class="font-normal cursor-pointer"><input type="checkbox" class="day-checkbox" value="5"> Fr</label>
                        <label class="font-normal cursor-pointer"><input type="checkbox" class="day-checkbox" value="6"> Sa</label>
                        <label class="font-normal cursor-pointer"><input type="checkbox" class="day-checkbox" value="0"> So</label>
                    </div>
                    <p class="mt-0 fs-14 text-muted">Uhrzeit für diese Tage:</p>
                    <div class="gap-15">
                        <label class="font-normal">Startzeit: <input type="time" id="customStartTime"></label>
                        <label class="font-normal">Endzeit: <input type="time" id="customEndTime"></label>
                    </div>
                </div>
            </div>

            <div class="card card-no-shadow">
                <h3 class="mt-0">Kunden-Daten abfragen</h3>
                <p class="fs-13 text-muted"><strong>Name</strong> und <strong>E-Mail</strong> sind globale Pflichtfelder und werden immer abgefragt. Hier kannst du optionale oder verpflichtende Zusatzfelder für diese Terminart anlegen (z.B. Telefonnummer, Geburtsdatum).</p>
                <div id="customFieldsContainer"></div>
                <button type="button" class="btn-secondary mt-10" id="btnAddCustomField">+ Weiteres Feld hinzufügen</button>
            </div>

            <button type="button" class="btn-success" id="btnSaveEventSettings">Einstellungen speichern</button>
        </div>
    </div>

    <script nonce="<?= htmlspecialchars(CSP_NONCE) ?>">
        // --- CSP EVENT LISTENERS (Keine Inline-Events mehr!) ---
        document.addEventListener('DOMContentLoaded', () => {
            document.getElementById('updateBtn')?.addEventListener('click', startUpdate);
            document.getElementById('btn-test-email')?.addEventListener('click', testEmail);
            document.getElementById('removeLogoBtn')?.addEventListener('click', removeLogo);
            document.getElementById('btn-reset-color')?.addEventListener('click', () => { document.getElementById('widgetAccentColor').value='#34c759'; });
            document.getElementById('widgetEventSelect')?.addEventListener('change', updateWidgetLinks);
            document.getElementById('btn-copy-direct')?.addEventListener('click', () => copyText('directLink'));
            document.getElementById('btn-copy-iframe')?.addEventListener('click', () => copyText('iframeCode'));
            document.getElementById('btn-copy-ical')?.addEventListener('click', () => copyText('icalLink'));
            document.getElementById('btnCloseModal')?.addEventListener('click', closeModal);
            document.getElementById('useGlobalSchedule')?.addEventListener('change', toggleScheduleOptions);
            document.getElementById('btnAddCustomField')?.addEventListener('click', addCustomField);
            document.getElementById('btnSaveEventSettings')?.addEventListener('click', saveEventSettings);
            document.getElementById('enableReminders')?.addEventListener('change', toggleReminderOptions);
            document.getElementById('bookingFilter')?.addEventListener('change', loadBookings);
            
            document.getElementById('showReleaseNotesBtn')?.addEventListener('click', (e) => {
                e.preventDefault(); document.getElementById('releaseNotesContent').classList.toggle('d-none');
            });
            
            document.querySelectorAll('.tab-btn').forEach(btn => btn.addEventListener('click', (e) => openTab(e.target.id.replace('btn-', ''))));
            
            // Event-Delegation für dynamisch geladene Buttons in den Tabellen
            document.addEventListener('click', (e) => {
                if (e.target.matches('[data-action="edit-event"]')) editEvent(e.target.getAttribute('data-id'));
                if (e.target.matches('[data-action="delete-event"]')) deleteEvent(e.target.getAttribute('data-id'));
                if (e.target.matches('[data-action="delete-booking"]')) deleteBooking(e.target.getAttribute('data-id'));
                if (e.target.matches('[data-action="confirm-booking"]')) confirmBooking(e.target.getAttribute('data-id'));
                if (e.target.matches('[data-action="offer-alternative"]')) offerAlternative(e.target.getAttribute('data-id'));
                if (e.target.matches('[data-action="select-text"]')) e.target.select();
                if (e.target.matches('[data-action="remove-field"]')) removeField(e.target.getAttribute('data-index'));
            });
            document.addEventListener('change', (e) => {
                if (e.target.matches('[data-action="update-field"]')) updateField(e.target.getAttribute('data-index'), e.target.getAttribute('data-key'), e.target.type === 'checkbox' ? e.target.checked : e.target.value);
            });
        });

        // Copy to Clipboard Funktion
        function copyText(elementId) {
            const el = document.getElementById(elementId);
            el.select();
            document.execCommand('copy');
            alert("Erfolgreich kopiert!");
            window.getSelection().removeAllRanges();
        }

        function toggleReminderOptions() {
            if (document.getElementById('enableReminders').checked) {
                document.getElementById('reminderOptionsContainer').classList.remove('d-none');
            } else {
                document.getElementById('reminderOptionsContainer').classList.add('d-none');
            }
        }

        function getAuthHeaders() {
            const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            return { 'Content-Type': 'application/json', 'X-CSRF-Token': token };
        }

        // Tab-Steuerung
        function openTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.add('d-none'));
            document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
            document.getElementById(tabId).classList.remove('d-none');
            document.getElementById('btn-' + tabId).classList.add('active');
        }
        
        // --- SICHERHEIT: XSS-Schutz ---
        // Verhindert, dass bösartiger JavaScript-Code, den Kunden bei der Buchung 
        // ins Namensfeld eintragen, im Admin-Panel ausgeführt wird.
        function escapeHtml(unsafe) {
            return (unsafe || '').toString()
                 .replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;")
                 .replace(/"/g, "&quot;").replace(/'/g, "&#039;");
        }

        // --- UPDATE CHECK LOGIK ---
        const CURRENT_VERSION = "<?= PLANAGO_VERSION ?>";
        let latestUpdateUrl = "";
        let updatesEnabled = 1;

        function isNewerVersion(oldVer, newVer) {
            const oldParts = oldVer.split('.').map(Number);
            const newParts = newVer.split('.').map(Number);
            for (let i = 0; i < Math.max(oldParts.length, newParts.length); i++) {
                const o = oldParts[i] || 0;
                const n = newParts[i] || 0;
                if (n > o) return true;
                if (n < o) return false;
            }
            return false;
        }

        function checkForUpdates() {
            // Hier fragt die Software deinen zentralen Planago-Server ab!
            fetch('https://planago.de/software_releases/version.php?t=' + new Date().getTime())
                .then(r => r.json())
                .then(data => {
                    console.log("🚀 Update-Check Antwort vom Server:", data);
                    console.log("💻 Installierte Version:", CURRENT_VERSION);

                    if (data.version && isNewerVersion(CURRENT_VERSION, data.version)) {
                        document.getElementById('newVersionNumber').innerText = data.version;
                        latestUpdateUrl = data.update_zip_url || data.zip_url || data.install_zip_url;
                        
                        const banner = document.getElementById('updateBanner');
                        const notesBtn = document.getElementById('showReleaseNotesBtn');
                        const notesContent = document.getElementById('releaseNotesContent');
                        const updateBtn = document.getElementById('updateBtn');
                        const renewBtn = document.getElementById('renewLicenseBtn');
                        const bannerTitle = document.getElementById('updateBannerTitle');
                        const bannerSub = document.getElementById('updateBannerSub');

                        if (data.release_notes) {
                            notesContent.innerHTML = data.release_notes;
                            notesBtn.classList.remove('d-none');
                        }
                        
                        if (updatesEnabled === 1) {
                            banner.classList.remove('d-none');
                        } else {
                            banner.classList.remove('d-none');
                            banner.style.background = 'linear-gradient(135deg, #f59e0b, #d97706)';
                            banner.style.boxShadow = '0 4px 12px rgba(245,158,11,0.3)';
                            bannerTitle.innerText = '⚠️ Neues Update verfügbar!';
                            bannerSub.innerText = `Version ${data.version} ist da, aber deine Update-Lizenz ist abgelaufen.`;
                            updateBtn.classList.add('d-none');
                            renewBtn.classList.remove('d-none');
                        }
                    }
                }).catch(e => console.error('Fehler beim Update-Check (Falsche URL oder Datei nicht erreichbar):', e));
        }
        
        function startUpdate() {
            if (!confirm("Möchtest du Planago jetzt aktualisieren? Der Vorgang dauert nur wenige Sekunden.")) return;
            
            const btn = document.getElementById('updateBtn');
            btn.innerText = "Wird installiert...";
            btn.style.opacity = "0.7";
            btn.disabled = true;

            fetch('update.php', {
                method: 'POST',
                headers: getAuthHeaders(),
                body: JSON.stringify({ zip_url: latestUpdateUrl })
            })
            .then(r => r.json())
            .then(res => {
                if(res.error) {
                    alert("Fehler beim Update: " + res.error);
                    btn.innerText = "Erneut versuchen";
                    btn.style.opacity = "1";
                    btn.disabled = false;
                } else {
                    alert(res.message);
                    // Cache-Buster: Zwingt den Browser, das HTML frisch zu laden
                    window.location.href = window.location.pathname + '?updated=' + new Date().getTime();
                }
            }).catch(() => alert("Kritischer Fehler bei der Verbindung."));
        }
        
        // --- LOGO UPLOAD LOGIK ---
        let logoBase64 = '';
        let removeLogoFlag = false;

        document.getElementById('companyLogoInput').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(evt) {
                    const img = new Image();
                    img.onload = function() {
                        const canvas = document.createElement('canvas');
                        const MAX_WIDTH = 600;
                        const MAX_HEIGHT = 600;
                        let width = img.width;
                        let height = img.height;

                        if (width > MAX_WIDTH || height > MAX_HEIGHT) {
                            const ratio = Math.min(MAX_WIDTH / width, MAX_HEIGHT / height);
                            width = width * ratio;
                            height = height * ratio;
                        }
                        canvas.width = width;
                        canvas.height = height;
                        const ctx = canvas.getContext('2d');
                        ctx.drawImage(img, 0, 0, width, height);
                        
                        logoBase64 = canvas.toDataURL('image/png'); // Konvertiert ins PNG Format inkl. Transparenz
                        document.getElementById('logoPreview').src = logoBase64;
                        document.getElementById('logoPreview').classList.remove('d-none');
                        document.getElementById('removeLogoBtn').classList.remove('d-none');
                        removeLogoFlag = false;
                    };
                    img.src = evt.target.result;
                };
                reader.readAsDataURL(file);
            }
        });

        function removeLogo() {
            logoBase64 = '';
            removeLogoFlag = true;
            document.getElementById('companyLogoInput').value = '';
            document.getElementById('logoPreview').src = '';
            document.getElementById('logoPreview').classList.add('d-none');
            document.getElementById('removeLogoBtn').classList.add('d-none');
        }

        // 1. Einstellungen beim Laden abrufen
        fetch('api_settings.php')
            .then(r => r.json())
            .then(data => {
                document.getElementById('startTime').value = data.work_start_time;
                document.getElementById('endTime').value = data.work_end_time;
                document.getElementById('requireManualConf').checked = data.require_manual_confirmation == 1;
                document.getElementById('smtpFromEmail').value = data.smtp_from || '';
                document.getElementById('smtpFromName').value = data.smtp_from_name || '';
                document.getElementById('smtpHost').value = data.smtp_host || '';
                document.getElementById('smtpPort').value = data.smtp_port || '587';
                document.getElementById('smtpUser').value = data.smtp_user || '';
                document.getElementById('smtpPass').value = data.smtp_pass || '';
                document.getElementById('companyName').value = data.company_name || 'Planago Booking';
                document.getElementById('companyPhone').value = data.company_phone || '';
                document.getElementById('companyAddress').value = data.company_address || '';
                document.getElementById('companyLinkImpressum').value = data.company_link_impressum || '';
                document.getElementById('companyLinkPrivacy').value = data.company_link_privacy || '';
                document.getElementById('companyLinkAgb').value = data.company_link_agb || '';
                document.getElementById('widgetAccentColor').value = data.widget_accent_color || '#34c759';
                document.getElementById('adminUsername').value = data.admin_username || 'admin';
                document.getElementById('adminNewPassword').value = '';
                document.getElementById('adminEmail').value = data.admin_email || '';
                if (data.company_logo) {
                    document.getElementById('logoPreview').src = 'logo.php?t=' + new Date().getTime(); // Lädt direkt aus der Datenbank
                    document.getElementById('logoPreview').classList.remove('d-none');
                    document.getElementById('removeLogoBtn').classList.remove('d-none');
                }
                
                document.getElementById('enableReviewEmail').checked = data.enable_review_email == 1;
                document.getElementById('googleReviewLink').value = data.google_review_link || '';
                
                if (data.enable_review_email == 1) document.getElementById('reviewLinkContainer').classList.remove('d-none');
                else document.getElementById('reviewLinkContainer').classList.add('d-none');

                document.getElementById('enableReminders').checked = data.enable_reminders == 1;
                document.getElementById('reminderHours').value = data.reminder_hours_before || 24;
                toggleReminderOptions();
                document.getElementById('zapierWebhookUrl').value = data.zapier_webhook_url || '';
                document.getElementById('themeMode').value = data.theme_mode || 'auto';
                
                if (data.calendar_sync_token) {
                    const baseUrl = window.location.href.split('?')[0].replace('admin.php', '');
                    document.getElementById('icalLink').value = baseUrl + "ical_feed.php?token=" + data.calendar_sync_token;
                }
                
                updatesEnabled = data.updates_enabled !== undefined ? parseInt(data.updates_enabled) : 1;
                checkForUpdates();
            });

        document.getElementById('enableReviewEmail').addEventListener('change', function() {
            if (this.checked) document.getElementById('reviewLinkContainer').classList.remove('d-none');
            else document.getElementById('reviewLinkContainer').classList.add('d-none');
        });

        // 2. Einstellungen absenden und speichern
        document.getElementById('settingsForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const data = { 
                work_start_time: document.getElementById('startTime').value, 
                work_end_time: document.getElementById('endTime').value,
                require_manual_confirmation: document.getElementById('requireManualConf').checked ? 1 : 0,
                smtp_from: document.getElementById('smtpFromEmail').value,
                smtp_from_name: document.getElementById('smtpFromName').value,
                smtp_host: document.getElementById('smtpHost').value,
                smtp_port: document.getElementById('smtpPort').value,
                smtp_user: document.getElementById('smtpUser').value,
                smtp_pass: document.getElementById('smtpPass').value,
                company_name: document.getElementById('companyName').value,
                company_phone: document.getElementById('companyPhone').value,
                company_address: document.getElementById('companyAddress').value,
                company_link_impressum: document.getElementById('companyLinkImpressum').value,
                company_link_privacy: document.getElementById('companyLinkPrivacy').value,
                company_link_agb: document.getElementById('companyLinkAgb').value,
                widget_accent_color: document.getElementById('widgetAccentColor').value,
                company_logo_base64: logoBase64,
                remove_logo: removeLogoFlag,
                enable_review_email: document.getElementById('enableReviewEmail').checked ? 1 : 0,
                google_review_link: document.getElementById('googleReviewLink').value,
                admin_username: document.getElementById('adminUsername').value,
                admin_new_password: document.getElementById('adminNewPassword').value,
                admin_email: document.getElementById('adminEmail').value,
                zapier_webhook_url: document.getElementById('zapierWebhookUrl').value,
                enable_reminders: document.getElementById('enableReminders').checked ? 1 : 0,
                reminder_hours_before: document.getElementById('reminderHours').value,
                theme_mode: document.getElementById('themeMode').value
            };
            fetch('api_settings.php', { method: 'POST', headers: getAuthHeaders(), body: JSON.stringify(data) })
            .then(r => r.json())
            .then(result => {
                document.querySelectorAll('.settingsMessage').forEach(msg => {
                    msg.innerText = result.message || result.error;
                    msg.style.color = result.error ? 'var(--danger)' : 'var(--success)';
                    setTimeout(() => msg.innerText = '', 3000);
                });
                // Nach dem Speichern den Zwischenspeicher leeren, um Datei-Dopplungen zu vermeiden
                logoBase64 = '';
                document.getElementById('companyLogoInput').value = '';
            });
        });

        function testEmail() {
            const btn = document.getElementById('btn-test-email');
            btn.innerText = "Wird gesendet...";
            btn.disabled = true;

            // Lese die Felder direkt aus dem Formular aus (ohne vorher speichern zu müssen)
            const data = {
                smtp_host: document.getElementById('smtpHost').value,
                smtp_port: document.getElementById('smtpPort').value,
                smtp_user: document.getElementById('smtpUser').value,
                smtp_pass: document.getElementById('smtpPass').value,
                smtp_from: document.getElementById('smtpFromEmail').value,
                smtp_from_name: document.getElementById('smtpFromName').value,
                admin_email: document.getElementById('adminEmail').value
            };

            fetch('api_test_email.php', {
                method: 'POST',
                headers: getAuthHeaders(),
                body: JSON.stringify(data)
            })
            .then(r => r.json())
            .then(res => {
                alert(res.message || res.error);
                btn.innerText = "Test-E-Mail senden";
                btn.disabled = false;
            }).catch(() => {
                alert("Netzwerkfehler beim Testen der E-Mail.");
                btn.innerText = "Test-E-Mail senden";
                btn.disabled = false;
            });
        }

        // 3. Trainingsarten abrufen und rendern
        function loadEvents() {
            fetch('api_events.php').then(r => r.json()).then(events => {
                const tbody = document.getElementById('eventsTableBody'); tbody.innerHTML = '';
                const widgetSelect = document.getElementById('widgetEventSelect');
                if (widgetSelect) widgetSelect.innerHTML = ''; // Dropdown leeren

                if(events.length === 0) { 
                    tbody.innerHTML = '<tr><td colspan="6">Keine Terminarten gefunden.</td></tr>'; 
                    if (widgetSelect) widgetSelect.innerHTML = '<option value="">Keine Terminarten verfügbar</option>';
                    return; 
                }
                
                const baseUrl = window.location.href.split('?')[0].replace('admin.php', 'index.php');
                
                events.forEach(e => {
                    const safeName = escapeHtml(e.name);
                    const eventLink = `${baseUrl}?event_id=${e.id}`;
                    tbody.innerHTML += `<tr><td>${safeName}</td><td>${e.duration_minutes} Min.</td><td>${e.max_capacity}</td><td>${e.buffer_minutes} Min.</td><td><input type="text" value="${eventLink}" readonly data-action="select-text" class="readonly-link" title="Klicken zum Kopieren"></td><td><div class="action-cell"><button class="btn-edit mr-5" data-action="edit-event" data-id="${e.id}">Einstellen</button><button class="btn-danger" data-action="delete-event" data-id="${e.id}">Löschen</button></div></td></tr>`;
                    
                    // Dropdown befüllen
                    if (widgetSelect) {
                        const option = document.createElement('option');
                        option.value = eventLink;
                        option.text = e.name; // .text wird automatisch sicher als Text verarbeitet, safeName würde hier zu doppeltem Escaping führen
                        widgetSelect.appendChild(option);
                    }
                });
                
                // Setzt die Links beim ersten Laden sofort auf die erste Terminart in der Liste
                if (widgetSelect) updateWidgetLinks();
            });
        }
        loadEvents();
        
        function updateWidgetLinks() {
            const select = document.getElementById('widgetEventSelect');
            if (!select || !select.value) return;
            const link = select.value;
            document.getElementById('directLink').value = link;
            document.getElementById('iframeCode').value = `<iframe src="${link}" width="100%" height="850" style="border:none; border-radius:12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05);" allowfullscreen></iframe>`;
        }

        // 4. Neue Trainingsart anlegen
        document.getElementById('eventForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const data = { name: document.getElementById('eventName').value, duration_minutes: document.getElementById('eventDuration').value };
            fetch('api_events.php', { method: 'POST', headers: getAuthHeaders(), body: JSON.stringify(data) })
            .then(r => r.json())
            .then(result => {
                const msg = document.getElementById('eventMessage');
                msg.innerText = result.message || result.error;
                msg.style.color = result.error ? 'red' : 'green';
                if(!result.error) document.getElementById('eventName').value = ''; // Feld leeren
                setTimeout(() => msg.innerText = '', 3000);
                loadEvents(); // Liste aktualisieren
            });
        });
        
        // --- NEU: LOGIK FÜR DAS EINSTELLUNGS-POPUP ---
        let currentEditingEventId = null;
        let currentCustomFields = [];

        function editEvent(id) {
            currentEditingEventId = id;
            fetch(`api_event_settings.php?id=${id}`)
                .then(r => r.json())
                .then(data => {
                    document.getElementById('modalEventName').innerText = "Einstellungen: " + data.name;
                    
                    // 0. Kapazität und Puffer setzen
                    document.getElementById('modalCapacity').value = data.max_capacity || 1;
                    document.getElementById('modalBuffer').value = data.buffer_minutes || 0;
                    
                    // 0.5 Buchungszeitraum (Vorlauf & Max)
                    document.getElementById('modalMinNotice').value = data.notice_min_hours !== undefined ? data.notice_min_hours : 24;
                    document.getElementById('modalMaxNotice').value = data.notice_max_days !== undefined ? data.notice_max_days : 60;
                    document.getElementById('modalCancelLimit').value = data.cancel_limit_hours !== undefined ? data.cancel_limit_hours : 24;

                    // 1. Buchungszeiten setzen
                    let schedule = { use_global: true, start_time: "09:00", end_time: "17:00", active_days: [1,2,3,4,5] };
                    if (data.schedule_json) {
                        try { schedule = JSON.parse(data.schedule_json); } catch(e){}
                    }
                    if (!schedule.active_days) schedule.active_days = [1,2,3,4,5]; // Fallback für ältere Einträge

                    document.getElementById('useGlobalSchedule').checked = schedule.use_global;
                    document.getElementById('customStartTime').value = schedule.start_time;
                    document.getElementById('customEndTime').value = schedule.end_time;
                    
                    document.querySelectorAll('.day-checkbox').forEach(cb => {
                        cb.checked = schedule.active_days.includes(parseInt(cb.value));
                    });
                    toggleScheduleOptions();

                    // 2. Formularfelder setzen
                    currentCustomFields = [];
                    if (data.form_fields_json) {
                        try { currentCustomFields = JSON.parse(data.form_fields_json); } catch(e){}
                    }
                    renderCustomFields();

                    document.getElementById('eventSettingsModal').style.display = 'block';
                });
        }

        function closeModal() { document.getElementById('eventSettingsModal').style.display = 'none'; } // Inline style override is fine here because it overrides the css .modal class
        function toggleScheduleOptions() { 
            if (document.getElementById('useGlobalSchedule').checked) {
                document.getElementById('customScheduleOptions').classList.add('d-none');
            } else {
                document.getElementById('customScheduleOptions').classList.remove('d-none');
            }
        }

        function renderCustomFields() {
            const container = document.getElementById('customFieldsContainer');
            container.innerHTML = '';
            currentCustomFields.forEach((field, index) => {
                container.innerHTML += `
                    <div class="custom-field-row">
                        <input type="text" placeholder="Feld-Name (z.B. Telefonnummer)" value="${escapeHtml(field.label)}" data-action="update-field" data-index="${index}" data-key="label" required>
                        <select data-action="update-field" data-index="${index}" data-key="type">
                            <option value="text" ${field.type === 'text' ? 'selected' : ''}>Text (Kurz)</option>
                            <option value="textarea" ${field.type === 'textarea' ? 'selected' : ''}>Text (Lang)</option>
                            <option value="number" ${field.type === 'number' ? 'selected' : ''}>Zahl</option>
                        </select>
                        <label class="checkbox-label mb-0 fs-13">
                            <input type="checkbox" ${field.required ? 'checked' : ''} data-action="update-field" data-index="${index}" data-key="required"> Pflichtfeld
                        </label>
                        <button type="button" class="btn-danger btn-icon" data-action="remove-field" data-index="${index}">X</button>
                    </div>
                `;
            });
        }
        function addCustomField() { currentCustomFields.push({ label: '', type: 'text', required: false }); renderCustomFields(); }
        function updateField(index, key, value) { currentCustomFields[index][key] = value; }
        function removeField(index) { currentCustomFields.splice(index, 1); renderCustomFields(); }

        function saveEventSettings() {
            const activeDays = Array.from(document.querySelectorAll('.day-checkbox:checked')).map(cb => parseInt(cb.value));
            const schedule = { use_global: document.getElementById('useGlobalSchedule').checked, start_time: document.getElementById('customStartTime').value, end_time: document.getElementById('customEndTime').value, active_days: activeDays };
            
            const data = { 
                id: currentEditingEventId, 
                schedule_json: JSON.stringify(schedule), 
                form_fields_json: JSON.stringify(currentCustomFields),
                max_capacity: document.getElementById('modalCapacity').value,
                buffer_minutes: document.getElementById('modalBuffer').value,
                notice_min_hours: document.getElementById('modalMinNotice').value,
                notice_max_days: document.getElementById('modalMaxNotice').value,
                cancel_limit_hours: document.getElementById('modalCancelLimit').value
            };
            
            fetch('api_event_settings.php', { method: 'POST', headers: getAuthHeaders(), body: JSON.stringify(data) })
                .then(r => r.json()).then(res => { if(res.error) alert(res.error); else { alert("Erfolgreich gespeichert!"); closeModal(); } });
        }

        // 5. Buchungen abrufen und in die Tabelle rendern
        function loadBookings() {
            const filter = document.getElementById('bookingFilter') ? document.getElementById('bookingFilter').value : 'upcoming';
            fetch('api_bookings.php?filter=' + filter).then(r => r.json()).then(bookings => {
                const tbody = document.getElementById('bookingsTableBody'); tbody.innerHTML = '';
                if(bookings.length === 0) { tbody.innerHTML = '<tr><td colspan="6">Keine Termine für diese Auswahl gefunden.</td></tr>'; return; }
                bookings.forEach(b => {
                    const dateString = new Date(b.start_time).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
                    const isPast = new Date(b.start_time) < new Date();
                    
                    const safeCustomer = escapeHtml(b.customer_name);
                    const safeEmail = escapeHtml(b.customer_email);
                    const safeEvent = escapeHtml(b.event_name);

                    // Zusatzinfos auslesen und formatieren
                    let customDataHtml = '-';
                    if (b.custom_data_json) {
                        try {
                            const parsed = JSON.parse(b.custom_data_json);
                            customDataHtml = Object.entries(parsed).map(([key, val]) => `<div class="mb-5"><strong>${escapeHtml(key)}:</strong> ${escapeHtml(val)}</div>`).join('');
                        } catch(e) {}
                    }
                    
                    let baseStatus = b.status === 'pending' ? '<span class="text-warning font-bold">Ausstehend</span>' : '<span class="text-success font-bold">Bestätigt</span>';
                    let statusText = '';
                    let actionButtons = '';

                    if (b.status === 'reschedule_requested') {
                        baseStatus = '<span class="text-purple font-bold">Verschiebung</span>';
                        statusText = '<br><span class="status-badge status-pending">⏳ Alternativtermin angefragt</span>';
                        actionButtons = `<div class="action-cell"><button class="btn-danger btn-icon" data-action="delete-booking" data-id="${b.id}">Absagen</button></div>`;
                    } 
                    else if (b.status === 'rescheduled_by_customer') {
                        baseStatus = '<span class="text-warning font-bold">Angefragt</span>';
                        statusText = '<br><span class="status-badge status-new-proposal">✉️ Neuer Terminvorschlag!</span>';
                        actionButtons = `
                            <div class="action-cell">
                                <button class="btn-success btn-icon" data-action="confirm-booking" data-id="${b.id}">Bestätigen</button>
                                <button class="btn-danger btn-icon" data-action="delete-booking" data-id="${b.id}">Ablehnen</button>
                            </div>`;
                    } 
                    else if (b.status === 'cancelled_by_customer') {
                        baseStatus = '<span style="color: #ff3b30;" class="font-bold">Storniert</span>';
                        statusText = '<br><span class="status-badge" style="background: rgba(255,59,48,0.1); color: #ff3b30; padding: 4px 8px; border-radius: 4px; font-size: 11px; margin-top: 5px; display: inline-block;">❌ Vom Kunden abgesagt</span>';
                        actionButtons = `<div class="action-cell"><button class="btn-danger btn-icon" data-action="delete-booking" data-id="${b.id}">Endgültig löschen</button></div>`;
                    }
                    else {
                        let confirmBtn = b.status === 'pending' ? `<button class="btn-success btn-icon" data-action="confirm-booking" data-id="${b.id}">Bestätigen</button>` : '';
                        if (isPast) {
                            actionButtons = `<div class="action-cell"><button class="btn-danger btn-icon" data-action="delete-booking" data-id="${b.id}">Löschen</button></div>`;
                            baseStatus = '<span class="text-muted font-bold">Abgeschlossen</span>';
                        } else {
                            actionButtons = `
                                <div class="action-cell">
                                    ${confirmBtn}
                                    <button class="btn-edit btn-icon" data-action="offer-alternative" data-id="${b.id}">Verschieben</button>
                                    <button class="btn-danger btn-icon" data-action="delete-booking" data-id="${b.id}">Stornieren</button>
                                </div>`;
                        }
                    }

                    tbody.innerHTML += `<tr><td>${dateString} Uhr</td><td>${safeEvent}</td><td>${safeCustomer}<br><a href="mailto:${safeEmail}" class="text-accent fs-12">${safeEmail}</a></td><td>${baseStatus}${statusText}</td><td class="fs-13">${customDataHtml}</td><td>${actionButtons}</td></tr>`;
                });
            });
        }
        
        loadBookings(); // Beim Start sofort laden

        // 5.1 Termin manuell bestätigen
        function confirmBooking(id) {
            if(confirm("Möchtest du diesen Termin bestätigen? Der Kunde erhält nun eine E-Mail.")) {
                fetch('api_confirm_booking.php', {
                    method: 'POST',
                    headers: getAuthHeaders(),
                    body: JSON.stringify({ id: id })
                }).then(() => loadBookings()); // Tabelle nach dem Bestätigen neu laden
            }
        }

        // 5.2 Termin verschieben (Gegenvorschlag)
        function offerAlternative(bookingId) {
            if(confirm("Möchtest du dem Kunden eine E-Mail schicken und ihn bitten, einen neuen Termin zu wählen?")) {
                fetch('api_reschedule_invite.php', {
                    method: 'POST',
                    headers: getAuthHeaders(),
                    body: JSON.stringify({ id: bookingId })
                })
                .then(res => res.json())
                .then(data => { alert(data.message); });
                loadBookings(); // Lade die Tabelle neu, um den geänderten Status sofort zu sehen
            }
        }

        // 5.5 Event (Terminart) löschen
        function deleteEvent(id) {
            if(confirm("Möchtest du diese Terminart wirklich löschen? ACHTUNG: Alle bestehenden Buchungen für diese Terminart werden ebenfalls gelöscht!")) {
                fetch('api_delete_event.php', {
                    method: 'POST',
                    headers: getAuthHeaders(),
                    body: JSON.stringify({ id: id })
                }).then(() => { loadEvents(); loadBookings(); }); // Tabellen aktualisieren
            }
        }

        // 6. Buchung löschen
        function deleteBooking(id) {
            if(confirm("Möchtest du diesen Termin wirklich absagen und löschen?")) {
                fetch('api_delete_booking.php', {
                    method: 'POST',
                    headers: getAuthHeaders(),
                    body: JSON.stringify({ id: id })
                }).then(() => loadBookings()); // Tabelle nach dem Löschen sofort neu laden
            }
        }
    </script>
</body>
</html>