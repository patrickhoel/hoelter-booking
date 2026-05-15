const eventId = document.getElementById('eventId').value;
// Wir übergeben die aktiven Tage aus PHP an JavaScript
// Diese Variable wird jetzt direkt im HTML gesetzt, siehe index.php
// const activeDays = <?= json_encode($activeDays) ?>;

// Vorlaufzeit und maximaler Zeitraum berechnen
// Diese Variablen werden jetzt direkt im HTML gesetzt, siehe index.php
// const now = new Date();
// const minAllowedDate = new Date(now.getTime() + (<?= $noticeMinHours ?> * 60 * 60 * 1000));
// const maxAllowedDate = new Date(now.getTime() + (<?= $noticeMaxDays ?> * 24 * 60 * 60 * 1000));

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
                
                if (data.error) {
                    container.innerHTML = `<p class="error-text">${data.error}</p>`;
                    return;
                }

                if (data.available_slots.length === 0) {
                    container.innerHTML = '<p class="error-text">Keine freien Termine an diesem Tag.</p>';
                    return;
                }
                
                data.available_slots.forEach(slot => {
                    const div = document.createElement('div');
                    div.className = 'slot';
                    
                    // Zusatztext "10/10 frei" anzeigen, wenn es ein Gruppentraining ist
                    if (slot.max_capacity > 1) {
                        div.innerHTML = `${slot.time}<br><span class="slot-capacity">(${slot.spots_left}/${slot.max_capacity} frei)</span>`;
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
        headers: { 
            'Content-Type': 'application/json',
            'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        const msgDiv = document.getElementById('message');
        const formDiv = document.getElementById('bookingForm');
        if(result.error) {
            msgDiv.className = 'message-box error';
            msgDiv.innerText = result.error;
        } else {
            msgDiv.className = 'message-box success';
            msgDiv.innerText = result.message;
            formDiv.style.display = 'none'; // Komplettes Formular ausblenden
        }
        msgDiv.style.display = 'block';
    });
});

// --- HINTERGRUND-AUFGABEN (Automatisierte Bewertungs-E-Mails etc.) ---
// Wird unsichtbar ausgeführt, ohne das Kundenerlebnis zu verlangsamen
setTimeout(() => fetch('cron.php').catch(() => {}), 2500);
