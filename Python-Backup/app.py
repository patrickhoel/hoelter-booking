from flask import Flask, jsonify, request, render_template, redirect, url_for
from flask_sqlalchemy import SQLAlchemy
from flask_login import LoginManager, UserMixin, login_user, logout_user, login_required, current_user
from werkzeug.security import generate_password_hash, check_password_hash
from datetime import datetime, timedelta

app = Flask(__name__)
app.secret_key = 'your-secret-key-change-this'

# Datenbank-Konfiguration (SQLite für einfache Entwicklung)
app.config['SQLALCHEMY_DATABASE_URI'] = 'sqlite:///bookings.db'
app.config['SQLALCHEMY_TRACK_MODIFICATIONS'] = False
db = SQLAlchemy(app)

# Flask-Login initialisieren
login_manager = LoginManager()
login_manager.init_app(app)
login_manager.login_view = 'login'

# --- DATENBANK-MODELLE ---

class User(UserMixin, db.Model):
    __tablename__ = 'users'
    id = db.Column(db.Integer, primary_key=True)
    username = db.Column(db.String(100), unique=True, nullable=False)
    password_hash = db.Column(db.String(200), nullable=False)

@login_manager.user_loader
def load_user(user_id):
    return db.session.get(User, int(user_id))

class EventType(db.Model):
    __tablename__ = 'event_types'
    id = db.Column(db.Integer, primary_key=True)
    name = db.Column(db.String(100), nullable=False) # z.B. "Einzeltraining"
    duration_minutes = db.Column(db.Integer, default=60)
    is_active = db.Column(db.Boolean, default=True)

class Booking(db.Model):
    __tablename__ = 'bookings'
    id = db.Column(db.Integer, primary_key=True)
    event_type_id = db.Column(db.Integer, db.ForeignKey('event_types.id'), nullable=False)
    
    customer_name = db.Column(db.String(100), nullable=False)
    customer_email = db.Column(db.String(120), nullable=False)
    start_time = db.Column(db.DateTime, nullable=False)

    # Beziehung für einfachere Abfragen
    event = db.relationship('EventType', backref=db.backref('bookings', lazy=True))

class Settings(db.Model):
    __tablename__ = 'settings'
    id = db.Column(db.Integer, primary_key=True)
    work_start_time = db.Column(db.String(5), default="09:00")
    work_end_time = db.Column(db.String(5), default="17:00")

# Tabellen beim App-Start automatisch erstellen
with app.app_context():
    db.create_all()
    
    # Einen Standard-Admin anlegen, falls noch keiner existiert
    if not User.query.filter_by(username='admin').first():
        hashed_pw = generate_password_hash('admin123')
        db.session.add(User(username='admin', password_hash=hashed_pw))
        db.session.commit()

    # Standard-Einstellungen (9-17 Uhr) anlegen, falls noch keine existieren
    if not Settings.query.first():
        db.session.add(Settings(work_start_time="09:00", work_end_time="17:00"))
        db.session.commit()

@app.route('/')
def home():
    return jsonify({"message": "Hölter-Digital Booking Backend läuft!"})

@app.route('/embed/<int:event_id>')
def embed_widget(event_id):
    event = db.session.get(EventType, event_id)
    if not event or not event.is_active:
        return "Dieses Training existiert nicht oder ist inaktiv.", 404
        
    return render_template('index.html', event=event)

@app.route('/api/events', methods=['GET', 'POST'])
def api_events():
    # 1. POST: Einen neuen Event-Typ anlegen (z.B. Welpentraining)
    if request.method == 'POST':
        data = request.get_json()
        
        if not data or not data.get('name'):
            return jsonify({"error": "Der Name des Events ist erforderlich"}), 400
            
        new_event = EventType(
            name=data['name'],
            duration_minutes=data.get('duration_minutes', 60),
            is_active=data.get('is_active', True)
        )
        db.session.add(new_event)
        db.session.commit()
        
        return jsonify({
            "message": "Event-Typ erfolgreich angelegt",
            "event": {
                "id": new_event.id,
                "name": new_event.name,
                "duration_minutes": new_event.duration_minutes
            }
        }), 201

    # 2. GET: Alle aktiven Events für das Frontend abrufen
    events = EventType.query.filter_by(is_active=True).all()
    return jsonify([{
        "id": e.id,
        "name": e.name,
        "duration_minutes": e.duration_minutes
    } for e in events])

@app.route('/api/availability/<int:event_id>')
def api_availability(event_id):
    date_str = request.args.get('date')
    if not date_str:
        return jsonify({"error": "Datum fehlt"}), 400
        
    try:
        target_date = datetime.strptime(date_str, '%Y-%m-%d').date()
    except ValueError:
        return jsonify({"error": "Ungültiges Datumsformat"}), 400
        
    event = db.session.get(EventType, event_id)
    if not event:
        return jsonify({"error": "Event nicht gefunden"}), 404
        
    # Arbeitszeiten dynamisch aus der Datenbank laden
    settings = Settings.query.first()
    start_time_obj = datetime.strptime(settings.work_start_time, '%H:%M').time()
    end_time_obj = datetime.strptime(settings.work_end_time, '%H:%M').time()
    
    work_start = datetime.combine(target_date, start_time_obj)
    work_end = datetime.combine(target_date, end_time_obj)
    
    # Alle Buchungen für den ausgewählten Tag holen
    day_end = work_start + timedelta(days=1)
    existing_bookings = Booking.query.filter(Booking.start_time >= work_start, Booking.start_time < day_end).all()
    
    available_slots = []
    current_time = work_start
    
    # Slots in 30-Minuten-Schritten generieren
    while current_time + timedelta(minutes=event.duration_minutes) <= work_end:
        slot_end = current_time + timedelta(minutes=event.duration_minutes)
        is_free = True
        
        # Prüfen, ob der aktuelle Slot mit einer Buchung kollidiert
        for b in existing_bookings:
            b_start = b.start_time
            b_end = b_start + timedelta(minutes=b.event.duration_minutes)
            if current_time < b_end and slot_end > b_start:
                is_free = False
                break
                
        # Vergangene Zeiten für den heutigen Tag rausfiltern
        if is_free and current_time > datetime.now():
            available_slots.append(current_time.strftime('%H:%M'))
            
        current_time += timedelta(minutes=30)
        
    return jsonify({"date": date_str, "available_slots": available_slots})

@app.route('/api/book', methods=['POST'])
def api_book():
    data = request.get_json()
    
    # 1. Prüfen, ob alle wichtigen Felder ausgefüllt sind
    required_fields = ["event_type_id", "customer_name", "customer_email", "start_time"]
    if not all(field in data for field in required_fields):
        return jsonify({"error": "Bitte alle Felder ausfüllen"}), 400
        
    try:
        # 2. Datum aus Text (ISO-Format) in ein echtes Python-Datum umwandeln
        start_time = datetime.fromisoformat(data['start_time'])
    except ValueError:
        return jsonify({"error": "Ungültiges Datumsformat"}), 400
        
    # 3. Prüfen, ob die gewählte Trainingsart überhaupt existiert
    event = db.session.get(EventType, data['event_type_id'])
    if not event:
        return jsonify({"error": "Dieses Training existiert nicht"}), 404
        
    # --- NEU: DOPPELBUCHUNGEN VERHINDERN ---
    # 3.5 Ende des gewünschten Termins berechnen
    requested_end_time = start_time + timedelta(minutes=event.duration_minutes)
    
    # Alle bestehenden Buchungen für diesen spezifischen Tag aus der Datenbank holen
    day_start = start_time.replace(hour=0, minute=0, second=0, microsecond=0)
    day_end = day_start + timedelta(days=1)
    
    existing_bookings = Booking.query.filter(
        Booking.start_time >= day_start,
        Booking.start_time < day_end
    ).all()
    
    for b in existing_bookings:
        # Für jede bestehende Buchung Start und Ende berechnen
        b_start = b.start_time
        b_end = b_start + timedelta(minutes=b.event.duration_minutes)
        
        # Die mathematische Überlappungs-Regel
        if start_time < b_end and requested_end_time > b_start:
            return jsonify({"error": "Dieser Zeitraum ist leider schon belegt oder überschneidet sich mit einem anderen Termin."}), 409
    # --------------------------------------

    # 4. Buchung in der Datenbank anlegen
    new_booking = Booking(
        event_type_id=event.id,
        customer_name=data['customer_name'],
        customer_email=data['customer_email'],
        start_time=start_time
    )
    
    db.session.add(new_booking)
    db.session.commit()
    
    return jsonify({
        "message": "Termin erfolgreich gebucht!",
        "booking_id": new_booking.id
    }), 201

@app.route('/api/bookings', methods=['GET'])
@login_required
def api_bookings():
    # Alle Buchungen abrufen, sortiert nach Datum
    bookings = Booking.query.order_by(Booking.start_time).all()
    return jsonify([{
        "id": b.id,
        "event_name": b.event.name,
        "customer_name": b.customer_name,
        "customer_email": b.customer_email,
        "start_time": b.start_time.isoformat()
    } for b in bookings])

@app.route('/login', methods=['GET', 'POST'])
def login():
    error = None
    if request.method == 'POST':
        username = request.form.get('username')
        password = request.form.get('password')
        user = User.query.filter_by(username=username).first()
        
        if user and check_password_hash(user.password_hash, password):
            login_user(user)
            return redirect(url_for('admin_dashboard'))
        else:
            error = 'Falscher Benutzername oder Passwort!'
    return render_template('login.html', error=error)

@app.route('/logout')
@login_required
def logout():
    logout_user()
    return redirect(url_for('login'))

@app.route('/admin')
@login_required
def admin_dashboard():
    return render_template('admin.html')

@app.route('/api/settings', methods=['GET', 'POST'])
@login_required
def api_settings():
    settings = Settings.query.first()
    
    if request.method == 'POST':
        data = request.get_json()
        if 'work_start_time' in data: settings.work_start_time = data['work_start_time']
        if 'work_end_time' in data: settings.work_end_time = data['work_end_time']
        db.session.commit()
        return jsonify({"message": "Arbeitszeiten erfolgreich gespeichert!"})
        
    return jsonify({
        "work_start_time": settings.work_start_time,
        "work_end_time": settings.work_end_time
    })

if __name__ == '__main__':
    # host='0.0.0.0' ist entscheidend, damit die App 
    # aus dem isolierten Docker-Container heraus erreichbar ist.
    app.run(host='0.0.0.0', port=5000, debug=True)
