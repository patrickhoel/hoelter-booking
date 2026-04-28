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
                        <th>Link für Website</th>
                        <th>Aktion</th>
                    </tr>
                </thead>
                <tbody id="eventsTableBody">
                    <tr><td colspan="4">Lade Trainingsarten...</td></tr>
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
                        <th>Aktion</th>
                    </tr>
                </thead>
                <tbody id="bookingsTableBody">
                    <tr><td colspan="5">Lade Buchungen...</td></tr>
                </tbody>
            </table>
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
                if(events.length === 0) { tbody.innerHTML = '<tr><td colspan="4">Keine Trainingsarten gefunden.</td></tr>'; return; }
                const baseUrl = window.location.href.split('?')[0].replace('admin.php', 'index.php');
                events.forEach(e => {
                    const eventLink = `${baseUrl}?event_id=${e.id}`;
                    tbody.innerHTML += `<tr><td>${e.name}</td><td>${e.duration_minutes} Min.</td><td><input type="text" value="${eventLink}" readonly onclick="this.select()" style="width: 250px; font-size: 12px; cursor: pointer; border: 1px solid #ccc; padding: 5px; border-radius: 3px;" title="Klicken zum Kopieren"></td><td><button style="background-color: #ffc107; color: #000; padding: 5px 10px; font-size: 14px;" onclick="editEvent(${e.id})">⚙️ Einstellen</button></td></tr>`;
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
        
        // 4.5. Einstellungen-Menü öffnen
        function editEvent(id) {
            alert("Die Datenbank ist vorbereitet! \n\nWelche Funktion sollen wir nun für das Event (ID " + id + ") hier einbauen: \nDie eigenen Buchungszeiten oder den Formular-Baukasten?");
        }

        // 5. Buchungen abrufen und in die Tabelle rendern
        function loadBookings() {
            fetch('api_bookings.php').then(r => r.json()).then(bookings => {
                const tbody = document.getElementById('bookingsTableBody'); tbody.innerHTML = '';
                if(bookings.length === 0) { tbody.innerHTML = '<tr><td colspan="5">Noch keine Buchungen vorhanden.</td></tr>'; return; }
                bookings.forEach(b => {
                    const dateString = new Date(b.start_time).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
                    tbody.innerHTML += `<tr><td>${dateString} Uhr</td><td>${b.event_name}</td><td>${b.customer_name}</td><td><a href="mailto:${b.customer_email}">${b.customer_email}</a></td><td><button class="btn-danger" onclick="deleteBooking(${b.id})">Löschen</button></td></tr>`;
                });
            });
        }
        
        loadBookings(); // Beim Start sofort laden

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