<?php
session_start();

// Login-Schutz: Wer nicht eingeloggt ist, fliegt raus!
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hölter-Digital - Admin Panel</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #e9ecef; padding: 20px; color: #333; }
        .admin-container { max-width: 900px; margin: 0 auto; }
        .card { background: white; padding: 25px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); margin-bottom: 20px; }
        h1 { margin-top: 0; color: #0056b3; }
        h2 { margin-top: 0; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="time"], input[type="text"], input[type="number"] { padding: 10px; border: 1px solid #ccc; border-radius: 5px; font-size: 16px; }
        input[type="time"] { width: 150px; }
        button { padding: 10px 20px; background-color: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; transition: 0.2s;}
        button:hover { background-color: #0056b3; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f8f9fa; }
        .btn-danger { background-color: #dc3545; padding: 5px 10px; font-size: 14px; }
        .btn-danger:hover { background-color: #c82333; }
        
        /* Modal Styles für das Einstellungs-Fenster */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); overflow-y: auto; }
        .modal-content { background-color: #fff; margin: 5% auto; padding: 25px; border-radius: 8px; width: 90%; max-width: 600px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); max-height: 85vh; overflow-y: auto; }
        .close { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; margin-top: -10px; }
        .close:hover { color: #000; }
        .custom-field-row { display: flex; gap: 10px; margin-bottom: 10px; align-items: center; background: #f9f9f9; padding: 10px; border: 1px solid #eee; border-radius: 4px; }
        .custom-field-row input[type="text"], .custom-field-row select { flex: 1; padding: 8px; border: 1px solid #ccc; border-radius: 4px; margin: 0; font-size: 14px;}
        .custom-field-row input[type="checkbox"] { width: auto; margin-right: 5px; }
    </style>
</head>
<body>
    <div class="admin-container">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h1>Admin Dashboard</h1>
                <p style="margin-top: -15px; color: #666;">Eingeloggt als: <strong><?= htmlspecialchars($_SESSION['username']) ?></strong></p>
            </div>
            <a href="logout.php" style="padding: 10px 15px; background-color: #dc3545; color: white; text-decoration: none; border-radius: 5px; font-weight: bold;">Logout</a>
        </div>
        
        <div class="card">
            <h2>⏰ Öffnungszeiten einstellen</h2>
            <form id="settingsForm">
                <div style="display: flex; gap: 20px; align-items: flex-end;">
                    <div class="form-group">
                        <label for="startTime">Startzeit</label>
                        <input type="time" id="startTime" required>
                    </div>
                    <div class="form-group">
                        <label for="endTime">Endzeit</label>
                        <input type="time" id="endTime" required>
                    </div>
                    <div class="form-group">
                        <button type="submit">Speichern</button>
                    </div>
                </div>
                <div id="settingsMessage" style="font-weight: bold; color: green; margin-top: 10px;"></div>
            </form>
        </div>

        <div class="card">
            <h2>🏷️ Trainingsarten verwalten</h2>
            <form id="eventForm">
                <div style="display: flex; gap: 20px; align-items: flex-end;">
                    <div class="form-group">
                        <label for="eventName">Name (z.B. Welpentraining)</label>
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
                    <tr><td colspan="6">Lade Trainingsarten...</td></tr>
                </tbody>
            </table>
        </div>

        <div class="card">
            <h2>📅 Alle Buchungen</h2>
            <table>
                <thead>
                    <tr>
                        <th>Datum & Uhrzeit</th>
                        <th>Training</th>
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
    
    <!-- EINSTELLUNGEN MODAL (Unsichtbar bis man auf "Einstellen" klickt) -->
    <div id="eventSettingsModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2 id="modalEventName" style="margin-top: 0; color: #0056b3;">Einstellungen</h2>

            <div class="card" style="box-shadow: none; border: 1px solid #eee; margin-bottom: 15px;">
                <h3 style="margin-top:0;">👥 Kapazität & Puffer</h3>
                <div style="display: flex; gap: 15px;">
                    <label style="font-weight:normal;">Plätze (Max): <input type="number" id="modalCapacity" style="width: 80px; padding: 5px;"></label>
                    <label style="font-weight:normal;">Pufferzeit (Min): <input type="number" id="modalBuffer" style="width: 80px; padding: 5px;"></label>
                </div>
            </div>

            <div class="card" style="box-shadow: none; border: 1px solid #eee; margin-bottom: 15px;">
                <h3 style="margin-top:0;">⏳ Buchungszeitraum</h3>
                <div style="display: flex; gap: 15px;">
                    <label style="font-weight:normal;">Vorlaufzeit (Stunden): <input type="number" id="modalMinNotice" style="width: 80px; padding: 5px;" title="Wie viele Stunden im Voraus muss mindestens gebucht werden?"></label>
                    <label style="font-weight:normal;">Max. im Voraus (Tage): <input type="number" id="modalMaxNotice" style="width: 80px; padding: 5px;" title="Wie viele Tage in die Zukunft können Termine maximal gebucht werden?"></label>
                </div>
            </div>

            <div class="card" style="box-shadow: none; border: 1px solid #eee; margin-bottom: 15px;">
                <h3 style="margin-top:0;">⏰ Buchungszeiten</h3>
                <label style="font-weight: normal; cursor: pointer; display: flex; align-items: center; gap: 10px;">
                    <input type="checkbox" id="useGlobalSchedule" onchange="toggleScheduleOptions()" style="width: auto;">
                    <strong>Globale Öffnungszeiten für dieses Training übernehmen</strong>
                </label>
                <div id="customScheduleOptions" style="display:none; margin-top: 15px; padding-top: 15px; border-top: 1px dashed #ccc;">
                    <p style="margin-top:0; font-size: 14px; color: #666;">An welchen Tagen findet dieses Training statt?</p>
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
                <h3 style="margin-top:0;">📋 Kunden-Daten abfragen</h3>
                <p style="font-size: 13px; color: #666;"><strong>Name</strong> und <strong>E-Mail</strong> sind globale Pflichtfelder und werden immer abgefragt. Hier kannst du optionale oder verpflichtende Zusatzfelder für dieses Training anlegen (z.B. Telefonnummer, Alter des Hundes).</p>
                <div id="customFieldsContainer"></div>
                <button type="button" onclick="addCustomField()" style="background-color: #6c757d; font-size: 14px; margin-top: 10px;">+ Weiteres Feld hinzufügen</button>
            </div>

            <button type="button" onclick="saveEventSettings()" style="background-color: #28a745; width: 100%; margin-top: 10px; font-size: 18px;">💾 Einstellungen speichern</button>
        </div>
    </div>

    <script>
        // 1. Einstellungen beim Laden abrufen
        fetch('api_settings.php')
            .then(r => r.json())
            .then(data => {
                document.getElementById('startTime').value = data.work_start_time;
                document.getElementById('endTime').value = data.work_end_time;
            });

        // 2. Einstellungen absenden und speichern
        document.getElementById('settingsForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const data = { work_start_time: document.getElementById('startTime').value, work_end_time: document.getElementById('endTime').value };
            fetch('api_settings.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) })
            .then(r => r.json())
            .then(result => {
                const msg = document.getElementById('settingsMessage');
                msg.innerText = result.message;
                setTimeout(() => msg.innerText = '', 3000);
            });
        });

        // 3. Trainingsarten abrufen und rendern
        function loadEvents() {
            fetch('api_events.php').then(r => r.json()).then(events => {
                const tbody = document.getElementById('eventsTableBody'); tbody.innerHTML = '';
                if(events.length === 0) { tbody.innerHTML = '<tr><td colspan="6">Keine Trainingsarten gefunden.</td></tr>'; return; }
                const baseUrl = window.location.href.split('?')[0].replace('admin.php', 'index.php');
                events.forEach(e => {
                    const eventLink = `${baseUrl}?event_id=${e.id}`;
                    tbody.innerHTML += `<tr><td>${e.name}</td><td>${e.duration_minutes} Min.</td><td>${e.max_capacity}</td><td>${e.buffer_minutes} Min.</td><td><input type="text" value="${eventLink}" readonly onclick="this.select()" style="width: 250px; font-size: 12px; cursor: pointer; border: 1px solid #ccc; padding: 5px; border-radius: 3px;" title="Klicken zum Kopieren"></td><td><button style="background-color: #ffc107; color: #000; padding: 5px 10px; font-size: 14px; margin-right: 5px;" onclick="editEvent(${e.id})">⚙️ Einstellen</button><button class="btn-danger" onclick="deleteEvent(${e.id})">🗑️ Löschen</button></td></tr>`;
                });
            });
        }
        loadEvents();

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
                        <button type="button" class="btn-danger" onclick="removeField(${index})">X</button>
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
                notice_max_days: document.getElementById('modalMaxNotice').value
            };
            
            fetch('api_event_settings.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) })
                .then(r => r.json()).then(res => { if(res.error) alert(res.error); else { alert("Erfolgreich gespeichert!"); closeModal(); } });
        }

        // 5. Buchungen abrufen und in die Tabelle rendern
        function loadBookings() {
            fetch('api_bookings.php').then(r => r.json()).then(bookings => {
                const tbody = document.getElementById('bookingsTableBody'); tbody.innerHTML = '';
                if(bookings.length === 0) { tbody.innerHTML = '<tr><td colspan="6">Noch keine Buchungen vorhanden.</td></tr>'; return; }
                bookings.forEach(b => {
                    const dateString = new Date(b.start_time).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
                    
                    // Zusatzinfos auslesen und formatieren
                    let customDataHtml = '-';
                    if (b.custom_data_json) {
                        try {
                            const parsed = JSON.parse(b.custom_data_json);
                            customDataHtml = Object.entries(parsed).map(([key, val]) => `<div style="margin-bottom:3px;"><strong>${key}:</strong> ${val}</div>`).join('');
                        } catch(e) {}
                    }
                    
                    tbody.innerHTML += `<tr><td>${dateString} Uhr</td><td>${b.event_name}</td><td>${b.customer_name}</td><td><a href="mailto:${b.customer_email}">${b.customer_email}</a></td><td style="font-size: 13px;">${customDataHtml}</td><td><button class="btn-danger" onclick="deleteBooking(${b.id})">Löschen</button></td></tr>`;
                });
            });
        }
        
        loadBookings(); // Beim Start sofort laden

        // 5.5 Event (Trainingsart) löschen
        function deleteEvent(id) {
            if(confirm("Möchtest du dieses Training wirklich löschen? ACHTUNG: Alle bestehenden Buchungen für dieses Training werden ebenfalls gelöscht!")) {
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