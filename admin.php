<?php
session_start();

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
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planago - Admin Panel</title>
    <link rel="stylesheet" href="assets/admin_style.css">
</head>
<body>
    <div class="admin-container">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <!-- Update Banner (wird per JS eingeblendet, wenn Update verfügbar) -->
                <div id="updateBanner" style="display: none; background: linear-gradient(135deg, #007aff, #0056b3); color: white; padding: 12px 20px; border-radius: 8px; margin-bottom: 20px; align-items: center; justify-content: space-between; box-shadow: 0 4px 12px rgba(0,122,255,0.3);">
                    <div><strong>🚀 Neues Planago-Update verfügbar!</strong> Version <span id="newVersionNumber"></span> ist da.</div>
                    <button onclick="startUpdate()" id="updateBtn" style="background: white; color: #007aff; border: none; padding: 8px 15px; border-radius: 6px; font-weight: bold; cursor: pointer; transition: 0.2s;">Jetzt updaten</button>
                </div>

                <h1>Admin Dashboard</h1>
                <p style="margin-top: -15px; color: #666;">Eingeloggt als: <strong><?= htmlspecialchars($_SESSION['username']) ?></strong></p>
            </div>
            <a href="logout.php" style="padding: 8px 16px; border: 1px solid var(--danger); color: var(--danger); text-decoration: none; border-radius: 6px; font-weight: 500; font-size: 14px; transition: 0.2s;" onmouseover="this.style.backgroundColor='rgba(220, 53, 69, 0.1)'" onmouseout="this.style.backgroundColor='transparent'">Logout</a>
        </div>
        
        <div class="tabs" style="display: flex; gap: 10px; border-bottom: 1px solid var(--border-color); margin-bottom: 20px; overflow-x: auto;">
            <button class="tab-btn active" onclick="openTab('tab-bookings')" id="btn-tab-bookings">Buchungen</button>
            <button class="tab-btn" onclick="openTab('tab-events')" id="btn-tab-events">Terminarten</button>
            <button class="tab-btn" onclick="openTab('tab-process')" id="btn-tab-process">Buchungsprozess</button>
            <button class="tab-btn" onclick="openTab('tab-email')" id="btn-tab-email">E-Mail & SMTP</button>
            <button class="tab-btn" onclick="openTab('tab-company')" id="btn-tab-company">Unternehmensprofil</button>
            <button class="tab-btn" onclick="openTab('tab-integration')" id="btn-tab-integration">Widget & Kalender</button>
        </div>
        
        <!-- Ein gemeinsames Formular umfasst nun beide Einstellungs-Tabs -->
        <form id="settingsForm">
            
            <!-- E-MAIL TAB -->
            <div id="tab-email" class="tab-content" style="display: none;">
                <div class="card">
                    <h2>E-Mail & SMTP</h2>
                    <div class="settings-group">
                        <div class="form-group">
                            <label>SMTP Host</label>
                            <input type="text" id="smtpHost" placeholder="smtp.deinedomain.de">
                        </div>
                        <div class="form-group">
                            <label>SMTP Port</label>
                            <input type="text" id="smtpPort" placeholder="587">
                        </div>
                        <div class="form-group">
                            <label>Benutzername</label>
                            <input type="text" id="smtpUser">
                        </div>
                        <div class="form-group">
                            <label>Passwort</label>
                            <input type="password" id="smtpPass">
                        </div>
                        <div class="form-group">
                            <label>Absender Name</label>
                            <input type="text" id="smtpFromName" placeholder="Z.B. Planago Booking">
                        </div>
                        <div class="form-group">
                            <label>Absender E-Mail</label>
                            <input type="email" id="smtpFromEmail" placeholder="info@deinedomain.de">
                        </div>
                        <div class="form-group full-width">
                            <label>Admin E-Mail (Benachrichtigung bei neuen Buchungen)</label>
                            <input type="email" id="adminEmail" placeholder="admin@deinedomain.de">
                        </div>
                    </div>
                    <button type="submit" class="btn-success" style="margin-top:20px; width:auto;">Einstellungen speichern</button>
                    <div class="settingsMessage" style="font-weight: bold; margin-top: 10px;"></div>
                </div>
            </div>

            <!-- UNTERNEHMENS-TAB -->
            <div id="tab-company" class="tab-content" style="display: none;">
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
                        <div class="form-group full-width">
                            <label>Unternehmens-Logo</label>
                            <div style="display: flex; align-items: center; gap: 15px;">
                                <img id="logoPreview" src="" style="max-height: 60px; display: none; border-radius: 8px; border: 1px solid var(--border);">
                                <input type="file" id="companyLogoInput" accept="image/*" style="width: auto;">
                                <button type="button" class="btn-danger btn-icon" id="removeLogoBtn" style="display: none; height: 38px; padding: 0 15px; width: auto;" onclick="removeLogo()">Löschen</button>
                            </div>
                            <p style="font-size: 12px; color: var(--text-muted); margin-top: 5px;">Wird im Widget und in den E-Mails angezeigt.</p>
                        </div>
                        <div class="form-group">
                            <label>Akzentfarbe (Kunden-Widget)</label>
                            <div style="display: flex; gap: 10px; align-items: center;">
                                <input type="color" id="widgetAccentColor" style="height: 44px; width: 60px; padding: 2px; cursor: pointer; border-radius: 8px;">
                                <button type="button" style="height: 44px; padding: 0 15px; width: auto; background: var(--input-bg); color: var(--text-main); border: 1px solid var(--border); border-radius: 8px; cursor: pointer; font-weight: 500;" onclick="document.getElementById('widgetAccentColor').value='#34c759';">Standard</button>
                            </div>
                        </div>
                        <div class="form-group full-width" style="margin-top: 15px; padding-top: 15px; border-top: 1px solid var(--border);">
                            <label style="display:flex; align-items:center; gap: 10px; cursor: pointer; font-weight: normal; color: var(--text-main); margin-bottom: 10px;">
                                <input type="checkbox" id="enableReviewEmail" style="width: auto;">
                                <strong>Automatische Google-Bewertungs-E-Mail aktivieren</strong>
                            </label>
                            <p style="font-size: 12px; color: var(--text-muted); margin-top: 0; margin-bottom: 15px;">Sendet deinen Kunden 24 Stunden nach ihrem abgeschlossenen Termin automatisch eine freundliche E-Mail mit der Bitte um eine Bewertung.</p>
                            
                            <div id="reviewLinkContainer" style="display: none;">
                                <label>Dein Google Bewertungs-Link (Kurz-URL)</label>
                                <input type="text" id="googleReviewLink" placeholder="https://g.page/r/...">
                            </div>
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
                            <input type="password" id="adminNewPassword" placeholder="***">
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-success" style="margin-top:20px; width:auto;">Unternehmensprofil speichern</button>
                <div class="settingsMessage" style="font-weight: bold; margin-top: 10px;"></div>
            </div>

            <!-- BUCHUNGSPROZESS TAB -->
            <div id="tab-process" class="tab-content" style="display: none;">
                <div class="card">
                    <h2>Buchungs-Ablauf</h2>
                    <label style="display:flex; align-items:center; gap: 10px; cursor: pointer; font-weight: normal; color: var(--text-main);">
                        <input type="checkbox" id="requireManualConf" style="width: auto;">
                        <strong>Zwei-Wege-Bestätigung (Manuelle Bestätigung durch Admin erforderlich)</strong>
                    </label>
                </div>

                <div class="card">
                    <h2>Integrationen & Webhooks</h2>
                     <div class="form-group full-width">
                        <label for="zapierWebhookUrl">Webhook URL (Zapier / Make.com) - Optional</label>
                        <input type="text" id="zapierWebhookUrl" placeholder="https://hooks.zapier.com/hooks/catch/...">
                        <p style="font-size: 13px; color: var(--text-muted); margin-top: 5px; line-height: 1.5;">Verbinde Planago mit über 5.000 Apps (z.B. über Zapier oder die kostenlose Alternative Make.com). Erstelle dort einen Webhook-Trigger und füge die URL hier ein. Bei jeder Buchung werden die Daten automatisch dorthin gesendet.</p>
                        <div style="background: rgba(255, 159, 10, 0.1); border: 1px solid rgba(255, 159, 10, 0.3); padding: 10px 15px; border-radius: 8px; margin-top: 10px;">
                            <strong style="color: #d97706; font-size: 13px;">⚠️ Wichtiger Datenschutz-Hinweis (DSGVO):</strong>
                            <p style="color: #b45309; font-size: 12px; margin: 5px 0 0 0;">Planago speichert alle Daten zu 100% lokal. <strong>Sobald du hier eine URL einträgst</strong>, werden Kundendaten (Name, E-Mail) bei einer Buchung an externe Anbieter gesendet. Du musst dies zwingend in der Datenschutzerklärung deiner Website angeben!</p>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <h2>Reguläre Arbeitszeiten</h2>
                    <p class="text-muted" style="margin-top:0;">Wann bist du grundsätzlich für Termine verfügbar?</p>
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
                <button type="submit" class="btn-success" style="margin-top:20px; width:auto;">Prozess & Zeiten speichern</button>
                <div class="settingsMessage" style="font-weight: bold; margin-top: 10px;"></div>
            </div>
        </form>

        <!-- WIDGET & LINK TAB -->
        <div id="tab-integration" class="tab-content" style="display: none;">
            <div class="card">
                <h2>Links, Widget & Kalender-Sync</h2>
                <p style="margin-top: 0; color: var(--text-muted);">Wähle hier eine Terminart aus, um den passenden direkten Link oder den HTML-Code für deine Website (z. B. WordPress, Wix) zu generieren. So kannst du Kunden auf deiner Website gezielt zur Buchung dieser spezifischen Leistung leiten.</p>

                <div class="form-group" style="margin-top: 25px; padding-bottom: 15px; border-bottom: 1px dashed var(--border-color);">
                    <label for="widgetEventSelect" style="font-size: 15px; color: var(--text-main);">Für welche Terminart möchtest du den Link/Code erstellen?</label>
                    <select id="widgetEventSelect" onchange="updateWidgetLinks()" style="max-width: 400px; font-weight: bold; cursor: pointer;">
                        <option value="">Lade Terminarten...</option>
                    </select>
                </div>

                <h3 style="margin-top: 25px;">1. Direkter Link</h3>
                <p class="text-muted" style="margin-top: 0; font-size: 14px;">Teile diesen Link auf Instagram, WhatsApp oder verlinke ihn auf einem Button deiner Website.</p>
                <div style="display: flex; gap: 10px;">
                    <input type="text" id="directLink" readonly style="flex-grow: 1; font-size: 14px; cursor: text;">
                    <button type="button" class="btn-secondary" onclick="copyText('directLink')" style="width: auto;">Kopieren</button>
                </div>

                <h3 style="margin-top: 25px;">2. Widget auf der Website einbinden (iFrame)</h3>
                <p class="text-muted" style="margin-top: 0; font-size: 14px;">Kopiere diesen HTML-Code und füge ihn auf deiner Website direkt unter der Beschreibung für diese Terminart in einen "HTML" oder "Code" Block ein.</p>
                <div style="display: flex; gap: 10px; align-items: flex-start;">
                    <textarea id="iframeCode" readonly rows="4" style="flex-grow: 1; font-family: monospace; font-size: 13px; resize: none; cursor: text;"></textarea>
                    <button type="button" class="btn-secondary" onclick="copyText('iframeCode')" style="width: auto;">Kopieren</button>
                </div>

                <h3 style="margin-top: 25px;">3. Kalender-Synchronisation (Abo-Link)</h3>
                <p class="text-muted" style="margin-top: 0; font-size: 14px;">Kopiere diesen Link und füge ihn bei <strong>Apple Kalender</strong>, <strong>Google Kalender</strong> oder <strong>Outlook</strong> unter "Kalender abonnieren / Aus URL hinzufügen" ein. Danach erscheinen alle Planago-Buchungen automatisch in deinem Kalender!</p>
                <div style="display: flex; gap: 10px;">
                    <input type="text" id="icalLink" readonly style="flex-grow: 1; font-size: 14px; cursor: text;">
                    <button type="button" class="btn-secondary" onclick="copyText('icalLink')" style="width: auto;">Kopieren</button>
                </div>
            </div>
        </div>

        <div class="card tab-content" id="tab-events" style="display: none;">
            <h2>Terminarten</h2>
            <form id="eventForm">
                <div style="display: flex; gap: 20px; align-items: flex-end;">
                    <div class="form-group">
                        <label for="eventName">Name (z.B. Erstgespräch)</label>
                        <input type="text" id="eventName" required>
                    </div>
                    <div class="form-group">
                        <label for="eventDuration">Dauer (Minuten)</label>
                        <input type="number" id="eventDuration" value="60" required style="width: 100px;">
                    </div>
                    <div class="form-group">
                        <button type="submit">Hinzufügen</button>
                    </div>
                </div>
                <div id="eventMessage" style="font-weight: bold; color: green; margin-top: 10px;"></div>
            </form>
            
            <table style="margin-top: 20px;">
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

        <div class="card tab-content" id="tab-bookings">
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 12px; margin-bottom: 24px;">
                <h2 style="margin: 0; border: none; padding: 0;">Buchungen</h2>
                <select id="bookingFilter" onchange="loadBookings()" style="width: auto; padding: 8px 15px; border-radius: 8px; border: 1px solid var(--border-color); background-color: var(--bg-color); color: var(--text-main); font-weight: 600; cursor: pointer; outline: none;">
                    <option value="upcoming">Zukünftige Termine</option>
                    <option value="past">Vergangene Termine</option>
                    <option value="all">Alle Termine</option>
                </select>
            </div>
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
        <div style="text-align: center; margin-top: 25px; padding-top: 15px; border-top: 1px solid var(--border); font-size: 12px; color: var(--text-muted);">
            Powered by <a href="https://planago.de" target="_blank" style="color: var(--accent); text-decoration: none; font-weight: 600; transition: opacity 0.2s;" onmouseover="this.style.opacity='0.8'" onmouseout="this.style.opacity='1'">Planago</a>
        </div>
    </div>
    
    <!-- EINSTELLUNGEN MODAL (Unsichtbar bis man auf "Einstellen" klickt) -->
    <div id="eventSettingsModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2 id="modalEventName" style="margin-top: 0; color: var(--accent);">Einstellungen</h2>

            <div class="card" style="box-shadow: none; border: 1px solid #eee; margin-bottom: 15px;">
                <h3 style="margin-top:0;">Kapazität & Puffer</h3>
                <div style="display: flex; gap: 15px;">
                    <label style="font-weight:normal;">Plätze (Max): <input type="number" id="modalCapacity" style="width: 80px; padding: 5px;"></label>
                    <label style="font-weight:normal;">Pufferzeit (Min): <input type="number" id="modalBuffer" style="width: 80px; padding: 5px;"></label>
                </div>
            </div>

            <div class="card" style="box-shadow: none; border: 1px solid #eee; margin-bottom: 15px;">
                <h3 style="margin-top:0;">Buchungszeitraum</h3>
                <div style="display: flex; gap: 15px;">
                    <label style="font-weight:normal;">Vorlaufzeit (Stunden): <input type="number" id="modalMinNotice" style="width: 80px; padding: 5px;" title="Wie viele Stunden im Voraus muss mindestens gebucht werden?"></label>
                    <label style="font-weight:normal;">Max. im Voraus (Tage): <input type="number" id="modalMaxNotice" style="width: 80px; padding: 5px;" title="Wie viele Tage in die Zukunft können Termine maximal gebucht werden?"></label>
                </div>
            </div>

            <div class="card" style="box-shadow: none; border: 1px solid #eee; margin-bottom: 15px;">
                <h3 style="margin-top:0;">Stornierungsbedingungen</h3>
                <div style="display: flex; gap: 15px;">
                    <label style="font-weight:normal;">Bis wie viele Stunden vor dem Termin darf der Kunde absagen?<br> 
                    <input type="number" id="modalCancelLimit" style="width: 80px; padding: 5px; margin-top: 5px;" title="Stunden"> Stunden</label>
                </div>
            </div>

            <div class="card" style="box-shadow: none; border: 1px solid #eee; margin-bottom: 15px;">
                <h3 style="margin-top:0;">Buchungszeiten</h3>
                <label style="font-weight: normal; cursor: pointer; display: flex; align-items: center; gap: 10px;">
                    <input type="checkbox" id="useGlobalSchedule" onchange="toggleScheduleOptions()" style="width: auto;">
                    <strong>Globale Öffnungszeiten für diese Terminart übernehmen</strong>
                </label>
                <div id="customScheduleOptions" style="display:none; margin-top: 15px; padding-top: 15px; border-top: 1px dashed #ccc;">
                    <p style="margin-top:0; font-size: 14px; color: #666;">An welchen Tagen findet dieser Termin statt?</p>
                    <div style="display: flex; gap: 10px; margin-bottom: 15px; flex-wrap: wrap;">
                        <label style="font-weight:normal; cursor:pointer;"><input type="checkbox" class="day-checkbox" value="1"> Mo</label>
                        <label style="font-weight:normal; cursor:pointer;"><input type="checkbox" class="day-checkbox" value="2"> Di</label>
                        <label style="font-weight:normal; cursor:pointer;"><input type="checkbox" class="day-checkbox" value="3"> Mi</label>
                        <label style="font-weight:normal; cursor:pointer;"><input type="checkbox" class="day-checkbox" value="4"> Do</label>
                        <label style="font-weight:normal; cursor:pointer;"><input type="checkbox" class="day-checkbox" value="5"> Fr</label>
                        <label style="font-weight:normal; cursor:pointer;"><input type="checkbox" class="day-checkbox" value="6"> Sa</label>
                        <label style="font-weight:normal; cursor:pointer;"><input type="checkbox" class="day-checkbox" value="0"> So</label>
                    </div>
                    <p style="margin-top:0; font-size: 14px; color: #666;">Uhrzeit für diese Tage:</p>
                    <div style="display: flex; gap: 15px;">
                        <label style="font-weight:normal;">Startzeit: <input type="time" id="customStartTime"></label>
                        <label style="font-weight:normal;">Endzeit: <input type="time" id="customEndTime"></label>
                    </div>
                </div>
            </div>

            <div class="card" style="box-shadow: none; border: 1px solid #eee;">
                <h3 style="margin-top:0;">Kunden-Daten abfragen</h3>
                <p style="font-size: 13px; color: #666;"><strong>Name</strong> und <strong>E-Mail</strong> sind globale Pflichtfelder und werden immer abgefragt. Hier kannst du optionale oder verpflichtende Zusatzfelder für diese Terminart anlegen (z.B. Telefonnummer, Geburtsdatum).</p>
                <div id="customFieldsContainer"></div>
                <button type="button" class="btn-secondary" onclick="addCustomField()" style="margin-top: 10px;">+ Weiteres Feld hinzufügen</button>
            </div>

            <button type="button" class="btn-success" onclick="saveEventSettings()">Einstellungen speichern</button>
        </div>
    </div>

    <script>
        // Copy to Clipboard Funktion
        function copyText(elementId) {
            const el = document.getElementById(elementId);
            el.select();
            document.execCommand('copy');
            alert("Erfolgreich kopiert!");
            window.getSelection().removeAllRanges();
        }

        // Tab-Steuerung
        function openTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
            document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
            document.getElementById(tabId).style.display = 'block';
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
            fetch('https://planago.de/update/version.json?t=' + new Date().getTime())
                .then(r => r.json())
                .then(data => {
                    if (data.version && isNewerVersion(CURRENT_VERSION, data.version)) {
                        document.getElementById('newVersionNumber').innerText = data.version;
                        latestUpdateUrl = data.zip_url;
                        document.getElementById('updateBanner').style.display = 'flex';
                    }
                }).catch(e => console.log('Keine Verbindung zum Update-Server.'));
        }
        
        function startUpdate() {
            if (!confirm("Möchtest du Planago jetzt aktualisieren? Der Vorgang dauert nur wenige Sekunden.")) return;
            
            const btn = document.getElementById('updateBtn');
            btn.innerText = "Wird installiert...";
            btn.style.opacity = "0.7";
            btn.disabled = true;

            fetch('update.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
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
                    location.reload();
                }
            }).catch(() => alert("Kritischer Fehler bei der Verbindung."));
        }
        
        checkForUpdates(); // Direkt beim Laden im Hintergrund prüfen!

        // --- LOGO UPLOAD LOGIK ---
        let logoBase64 = '';
        let removeLogoFlag = false;

        document.getElementById('companyLogoInput').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                if (file.size > 2.5 * 1024 * 1024) { // 2.5 MB Limit
                    alert("Das Bild ist zu groß! Bitte verwende eine Datei unter 2.5 MB.");
                    this.value = '';
                    return;
                }
                const reader = new FileReader();
                reader.onload = function(evt) {
                    logoBase64 = evt.target.result;
                    document.getElementById('logoPreview').src = logoBase64;
                    document.getElementById('logoPreview').style.display = 'block';
                    document.getElementById('removeLogoBtn').style.display = 'block';
                    removeLogoFlag = false;
                };
                reader.readAsDataURL(file);
            }
        });

        function removeLogo() {
            logoBase64 = '';
            removeLogoFlag = true;
            document.getElementById('companyLogoInput').value = '';
            document.getElementById('logoPreview').src = '';
            document.getElementById('logoPreview').style.display = 'none';
            document.getElementById('removeLogoBtn').style.display = 'none';
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
                    document.getElementById('logoPreview').style.display = 'block';
                    document.getElementById('removeLogoBtn').style.display = 'block';
                }
                
                document.getElementById('enableReviewEmail').checked = data.enable_review_email == 1;
                document.getElementById('googleReviewLink').value = data.google_review_link || '';
                document.getElementById('reviewLinkContainer').style.display = data.enable_review_email == 1 ? 'block' : 'none';
                document.getElementById('zapierWebhookUrl').value = data.zapier_webhook_url || '';
                
                if (data.calendar_sync_token) {
                    const baseUrl = window.location.href.split('?')[0].replace('admin.php', '');
                    document.getElementById('icalLink').value = baseUrl + "ical_feed.php?token=" + data.calendar_sync_token;
                }
            });

        document.getElementById('enableReviewEmail').addEventListener('change', function() {
            document.getElementById('reviewLinkContainer').style.display = this.checked ? 'block' : 'none';
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
                zapier_webhook_url: document.getElementById('zapierWebhookUrl').value
            };
            fetch('api_settings.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) })
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
                    tbody.innerHTML += `<tr><td>${safeName}</td><td>${e.duration_minutes} Min.</td><td>${e.max_capacity}</td><td>${e.buffer_minutes} Min.</td><td><input type="text" value="${eventLink}" readonly onclick="this.select()" style="width: 250px; font-size: 12px; cursor: pointer; border: 1px solid #ccc; padding: 5px; border-radius: 3px;" title="Klicken zum Kopieren"></td><td><button class="btn-edit" style="margin-right: 5px;" onclick="editEvent(${e.id})">Einstellen</button><button class="btn-danger" onclick="deleteEvent(${e.id})">Löschen</button></td></tr>`;
                    
                    // Dropdown befüllen
                    if (widgetSelect) {
                        const option = document.createElement('option');
                        option.value = eventLink;
                        option.text = safeName;
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
            fetch('api_events.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) })
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

        function closeModal() { document.getElementById('eventSettingsModal').style.display = 'none'; }
        function toggleScheduleOptions() { document.getElementById('customScheduleOptions').style.display = document.getElementById('useGlobalSchedule').checked ? 'none' : 'block'; }

        function renderCustomFields() {
            const container = document.getElementById('customFieldsContainer');
            container.innerHTML = '';
            currentCustomFields.forEach((field, index) => {
                container.innerHTML += `
                    <div class="custom-field-row">
                        <input type="text" placeholder="Feld-Name (z.B. Telefonnummer)" value="${field.label}" onchange="updateField(${index}, 'label', this.value)" required>
                        <select onchange="updateField(${index}, 'type', this.value)">
                            <option value="text" ${field.type === 'text' ? 'selected' : ''}>Text (Kurz)</option>
                            <option value="textarea" ${field.type === 'textarea' ? 'selected' : ''}>Text (Lang)</option>
                            <option value="number" ${field.type === 'number' ? 'selected' : ''}>Zahl</option>
                        </select>
                        <label style="display:flex; align-items:center; margin:0; font-size:13px; font-weight:normal; cursor:pointer;">
                            <input type="checkbox" ${field.required ? 'checked' : ''} onchange="updateField(${index}, 'required', this.checked)"> Pflichtfeld
                        </label>
                        <button type="button" class="btn-danger btn-icon" onclick="removeField(${index})">X</button>
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
            
            fetch('api_event_settings.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) })
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
                            customDataHtml = Object.entries(parsed).map(([key, val]) => `<div style="margin-bottom:3px;"><strong>${escapeHtml(key)}:</strong> ${escapeHtml(val)}</div>`).join('');
                        } catch(e) {}
                    }
                    
                    let baseStatus = b.status === 'pending' ? '<span style="color: #f59e0b; font-weight: 600;">Ausstehend</span>' : '<span style="color: var(--success); font-weight: 600;">Bestätigt</span>';
                    let statusText = '';
                    let actionButtons = '';

                    if (b.status === 'reschedule_requested') {
                        baseStatus = '<span style="color: #a855f7; font-weight: 600;">Verschiebung</span>';
                        statusText = '<br><span class="status-badge status-pending">⏳ Alternativtermin angefragt</span>';
                        actionButtons = `<div class="action-cell"><button class="btn-danger btn-icon" onclick="deleteBooking(${b.id})">Absagen</button></div>`;
                    } 
                    else if (b.status === 'rescheduled_by_customer') {
                        baseStatus = '<span style="color: #f59e0b; font-weight: 600;">Angefragt</span>';
                        statusText = '<br><span class="status-badge status-new-proposal">✉️ Neuer Terminvorschlag!</span>';
                        actionButtons = `
                            <div class="action-cell">
                                <button class="btn-success btn-icon" onclick="confirmBooking(${b.id})">Bestätigen</button>
                                <button class="btn-danger btn-icon" onclick="deleteBooking(${b.id})">Ablehnen</button>
                            </div>`;
                    } 
                    else {
                        let confirmBtn = b.status === 'pending' ? `<button class="btn-success btn-icon" onclick="confirmBooking(${b.id})">Bestätigen</button>` : '';
                        if (isPast) {
                            actionButtons = `<div class="action-cell"><button class="btn-danger btn-icon" onclick="deleteBooking(${b.id})">Löschen</button></div>`;
                            baseStatus = '<span style="color: var(--text-muted); font-weight: 600;">Abgeschlossen</span>';
                        } else {
                            actionButtons = `
                                <div class="action-cell">
                                    ${confirmBtn}
                                    <button class="btn-edit btn-icon" onclick="offerAlternative(${b.id})">Verschieben</button>
                                    <button class="btn-danger btn-icon" onclick="deleteBooking(${b.id})">Stornieren</button>
                                </div>`;
                        }
                    }

                    tbody.innerHTML += `<tr><td>${dateString} Uhr</td><td>${safeEvent}</td><td>${safeCustomer}<br><a href="mailto:${safeEmail}" style="font-size: 12px; color: var(--accent);">${safeEmail}</a></td><td>${baseStatus}${statusText}</td><td style="font-size: 13px;">${customDataHtml}</td><td>${actionButtons}</td></tr>`;
                });
            });
        }
        
        loadBookings(); // Beim Start sofort laden

        // 5.1 Termin manuell bestätigen
        function confirmBooking(id) {
            if(confirm("Möchtest du diesen Termin bestätigen? Der Kunde erhält nun eine E-Mail.")) {
                fetch('api_confirm_booking.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id })
                }).then(() => loadBookings()); // Tabelle nach dem Bestätigen neu laden
            }
        }

        // 5.2 Termin verschieben (Gegenvorschlag)
        function offerAlternative(bookingId) {
            if(confirm("Möchtest du dem Kunden eine E-Mail schicken und ihn bitten, einen neuen Termin zu wählen?")) {
                fetch('api_reschedule_invite.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
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
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id })
                }).then(() => { loadEvents(); loadBookings(); }); // Tabellen aktualisieren
            }
        }

        // 6. Buchung löschen
        function deleteBooking(id) {
            if(confirm("Möchtest du diesen Termin wirklich absagen und löschen?")) {
                fetch('api_delete_booking.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id })
                }).then(() => loadBookings()); // Tabelle nach dem Löschen sofort neu laden
            }
        }
    </script>
</body>
</html>