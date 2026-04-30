<?php
// ical_feed.php
// Generiert einen Live-Kalender-Feed für Apple, Google und Outlook

require_once 'config.php';

$token = $_GET['token'] ?? '';
if (empty($token) || strlen($token) < 10) {
    http_response_code(403);
    exit('Zugriff verweigert.');
}

$db = getDb();

// Fallback-Migration für bestehende Installationen
try { $db->exec("ALTER TABLE settings ADD COLUMN calendar_sync_token TEXT DEFAULT NULL"); } catch (Exception $e) {}

$stmt = $db->prepare("SELECT company_name FROM settings WHERE calendar_sync_token = ? LIMIT 1");
$stmt->execute([$token]);
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$settings) {
    http_response_code(403);
    exit('Ungültiger Token.');
}

$companyName = $settings['company_name'] ? $settings['company_name'] : 'Planago';

header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="planago_kalender.ics"');

echo "BEGIN:VCALENDAR\r\n";
echo "VERSION:2.0\r\n";
echo "PRODID:-//Planago Booking//DE\r\n";
echo "CALSCALE:GREGORIAN\r\n";
echo "X-WR-CALNAME:" . $companyName . " Termine\r\n";
echo "X-WR-TIMEZONE:Europe/Berlin\r\n";
echo "REFRESH-INTERVAL;VALUE=DURATION:PT15M\r\n"; // Kalender bitten, alle 15 Min zu aktualisieren

// Alle bestätigten oder ausstehenden Buchungen abrufen
$bookStmt = $db->query("SELECT b.*, e.name as event_name, e.duration_minutes FROM bookings b JOIN event_types e ON b.event_type_id = e.id WHERE b.status = 'confirmed' OR b.status = 'pending'");
$bookings = $bookStmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($bookings as $b) {
    $start = new DateTime($b['start_time']);
    $end = (clone $start)->modify("+" . $b['duration_minutes'] . " minutes");

    // iCal verlangt immer UTC-Zeit!
    $start->setTimezone(new DateTimeZone('UTC'));
    $end->setTimezone(new DateTimeZone('UTC'));

    echo "BEGIN:VEVENT\r\n";
    echo "UID:planago_" . $b['id'] . "_" . md5($b['start_time']) . "@local\r\n";
    echo "DTSTAMP:" . gmdate('Ymd\THis\Z') . "\r\n";
    echo "DTSTART:" . $start->format('Ymd\THis\Z') . "\r\n";
    echo "DTEND:" . $end->format('Ymd\THis\Z') . "\r\n";
    
    $prefix = ($b['status'] === 'pending') ? '[NEU] ' : '';
    echo "SUMMARY:" . $prefix . $b['customer_name'] . " - " . $b['event_name'] . "\r\n";
    echo "DESCRIPTION:Kunde: " . $b['customer_name'] . "\\nE-Mail: " . $b['customer_email'] . "\r\n";
    echo "END:VEVENT\r\n";
}
echo "END:VCALENDAR\r\n";
?>