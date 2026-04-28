<?php
// Datenbank-Verbindung laden
require_once 'config.php';
$db = getDb();

// Prüfen, ob eine spezifische Event-ID über die URL übergeben wurde (?event_id=2)
$eventId = filter_input(INPUT_GET, 'event_id', FILTER_VALIDATE_INT);

if ($eventId) {
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
// Hier können wir später z.B. freie Termine aus der Datenbank laden
// $freie_termine = ...
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Termin buchen</title>
    
    <!-- Flatpickr CSS & Deutsche Sprache laden -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://npmcdn.com/flatpickr/dist/l10n/de.js"></script>

    <style>
        body { 
            font-family: Arial, sans-serif; 
            background-color: #f4f7f6;
            padding: 20px; 
        }
        .widget-container {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            max-width: 400px;
            margin: 0 auto; /* Zentriert das Widget */
        }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; font-size: 14px;}
        input, select { 
            width: 100%; 
            padding: 10px; 
            border: 1px solid #ccc; 
            border-radius: 5px; 
            box-sizing: border-box; 
        }
        button { 
            width: 100%; 
            padding: 12px; 
            background-color: #28a745; 
            color: white; 
            border: none; 
            border-radius: 5px; 
            font-size: 16px; 
            cursor: pointer; 
            margin-top: 10px;
        }
        button:hover { background-color: #218838; }
        #message { margin-top: 15px; text-align: center; font-weight: bold; }
        
        /* Schickes Design für unsere Termin-Buttons */
        .slots-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-top: 10px;
        }
        .time-slot {
            padding: 10px; text-align: center; background: #e9ecef;
            border: 1px solid #ccc; border-radius: 5px; cursor: pointer;
            transition: all 0.2s; font-weight: bold; color: #333;
        }
        .time-slot:hover { background: #d6d8db; }
        .time-slot.selected { background: #28a745; color: white; border-color: #28a745; }
    </style>
</head>
<body>

    <div class="widget-container">
        <h2 style="text-align: center; margin-top: 0; color: #333;"><?= htmlspecialchars($event['name']) ?></h2>
<p style="text-align: center; color: #666; margin-bottom: 20px;">Dauer: <?= $event['duration_minutes'] ?> Minuten</p>
        
<form id="bookingForm">
    <input type="hidden" id="eventId" value="<?= $event['id'] ?>">
            
            <div class="form-group">
                <label for="name">Dein Name</label>
                <input type="text" id="name" placeholder="Max Mustermann" required>
            </div>
            
            <div class="form-group">
                <label for="email">Deine E-Mail</label>
                <input type="email" id="email" placeholder="max@beispiel.de" required>
            </div>
            
            <div class="form-group">
                <label for="datePicker">Wähle ein Datum</label>
                <input type="text" id="datePicker" placeholder="Datum auswählen..." required>
            </div>
            
            <!-- Hier laden wir die freien Uhrzeiten rein -->
            <div class="form-group">
                <label>Verfügbare Zeiten</label>
                <div id="timeSlotsContainer" class="slots-grid">
                    <p style="font-size: 13px; color: #666; grid-column: span 3;">Bitte wähle zuerst ein Datum aus.</p>
                </div>
                <!-- Unsichtbares Feld, in das wir die ausgewählte Uhrzeit schreiben -->
                <input type="hidden" id="selectedTime" required>
            </div>
            
            <button type="submit">Jetzt verbindlich buchen</button>
        </form>
        
        <div id="message"></div>
    </div>

    <script>
        const eventId = document.getElementById('eventId').value;

        // Flatpickr initialisieren
        flatpickr("#datePicker", {
            locale: "de", // Auf Deutsch stellen
            minDate: "today", // Man kann keine Termine in der Vergangenheit buchen
            disable: [
                function(date) {
                    // Optionale Funktion: Verhindere z.B. Buchungen an Sonntagen (0 = Sonntag)
                    return (date.getDay() === 0);
                }
            ],
            onChange: function(selectedDates, dateStr, instance) {
                // Wenn ein Datum angeklickt wird: Lade freie Termine
                fetch(`api_availability.php?event_id=${eventId}&date=${dateStr}`)
                    .then(r => r.json())
                    .then(data => {
                        const container = document.getElementById('timeSlotsContainer');
                        container.innerHTML = ''; // Vorherige löschen
                        document.getElementById('selectedTime').value = ''; // Auswahl zurücksetzen
                        
                        if (data.available_slots.length === 0) {
                            container.innerHTML = '<p style="color: red; grid-column: span 3;">Keine freien Termine an diesem Tag.</p>';
                            return;
                        }
                        
                        data.available_slots.forEach(time => {
                            const div = document.createElement('div');
                            div.className = 'time-slot';
                            div.innerText = time;
                            div.onclick = function() {
                                document.querySelectorAll('.time-slot').forEach(el => el.classList.remove('selected'));
                                div.classList.add('selected');
                                document.getElementById('selectedTime').value = time; // Uhrzeit merken
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

            // Wir sammeln die Eingaben aus den Feldern
            const data = {
                event_type_id: parseInt(document.getElementById('eventId').value),
                customer_name: document.getElementById('name').value,
                customer_email: document.getElementById('email').value,
                start_time: finalDateTime
            };

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
                    document.getElementById('bookingForm').reset(); // Formular leeren
                }
            });
        });
    </script>
</body>
</html>
