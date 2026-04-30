<?php
// config.php
// Hier definieren wir globale Einstellungen für das gesamte System

// --- SYSTEM VERSION ---
define('PLANAGO_VERSION', '1.0.1');

// --- LIZENZSCHLÜSSEL ---
define('PLANAGO_LICENSE_KEY', 'DEIN_GEHEIMER_LIZENZSCHLUESSEL_12345');

// --- ZEITZONEN-SICHERHEIT ---
date_default_timezone_set('Europe/Berlin');

// PHPMailer einbinden (ohne Composer)
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

define('DB_PATH', __DIR__ . '/data/database.db');

// --- DATENBANK SCHUTZ (CRITICAL) ---
// Verhindert, dass jemand die Datenbank-Datei direkt über den Browser herunterlädt
if (is_dir(__DIR__ . '/data') && !file_exists(__DIR__ . '/data/.htaccess')) {
    @file_put_contents(__DIR__ . '/data/.htaccess', "<Files \"*.db\">\nOrder allow,deny\nDeny from all\nRequire all denied\n</Files>");
    @file_put_contents(__DIR__ . '/data/index.php', "<?php http_response_code(403); exit; ?>");
}

// Hilfsfunktion für die Datenbankverbindung
function getDb() {
    // Stellt die Verbindung zur SQLite-Datenbank her
    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $pdo;
}

// --- NEU: Hilfsfunktion zur Erstellung von iCal-Dateien (.ics) ---
function generateIcsData($eventName, $startTimeObj, $durationMinutes) {
    $startUtc = clone $startTimeObj;
    $startUtc->setTimezone(new DateTimeZone('UTC'));
    $endUtc = clone $startUtc;
    $endUtc->modify("+" . (int)$durationMinutes . " minutes");

    // iCal Datumsformat (UTC)
    $dtStart = $startUtc->format('Ymd\THis\Z');
    $dtEnd = $endUtc->format('Ymd\THis\Z');
    $now = gmdate('Ymd\THis\Z');
    $uid = md5(uniqid(mt_rand(), true)) . "@planago.local";

    $ics = "BEGIN:VCALENDAR\r\n";
    $ics .= "VERSION:2.0\r\n";
    $ics .= "PRODID:-//Planago//Planago Booking//DE\r\n";
    $ics .= "CALSCALE:GREGORIAN\r\n";
    $ics .= "METHOD:REQUEST\r\n"; // Wichtig, damit Mail-Programme es als Einladung erkennen
    $ics .= "BEGIN:VEVENT\r\n";
    $ics .= "DTSTAMP:{$now}\r\n";
    $ics .= "DTSTART:{$dtStart}\r\n";
    $ics .= "DTEND:{$dtEnd}\r\n";
    $ics .= "UID:{$uid}\r\n";
    $ics .= "SUMMARY:" . $eventName . "\r\n";
    $ics .= "DESCRIPTION:Dein gebuchter Termin: " . $eventName . "\r\n";
    $ics .= "STATUS:CONFIRMED\r\n";
    $ics .= "SEQUENCE:0\r\n";
    $ics .= "END:VEVENT\r\n";
    $ics .= "END:VCALENDAR\r\n";
    return $ics;
}

// --- NEU: Webhook-Funktion für Zapier & Co. ---
function sendToWebhook($payload) {
    $db = getDb();
    $stmt = $db->query("SELECT zapier_webhook_url FROM settings LIMIT 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!empty($settings['zapier_webhook_url']) && filter_var($settings['zapier_webhook_url'], FILTER_VALIDATE_URL)) {
        $ch = curl_init($settings['zapier_webhook_url']);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // Wichtig: Kurzer Timeout, damit der Kunde nicht warten muss!
        curl_setopt($ch, CURLOPT_TIMEOUT, 2); 
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Für Kompatibilität
        
        // Asynchroner Aufruf, damit der Kunde nicht wartet ("fire and forget")
        curl_setopt($ch, CURLOPT_NOSIGNAL, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, 500); // Sehr kurzer Timeout in Millisekunden

        curl_exec($ch);
        curl_close($ch);
    }
}

// Globale E-Mail Funktion für das System
function sendSystemMail($to, $subject, $body, $icsData = null) {
    $db = getDb();
    $stmt = $db->query("SELECT smtp_host, smtp_port, smtp_user, smtp_pass, smtp_from, smtp_from_name, company_name FROM settings LIMIT 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $from = !empty($settings['smtp_from']) ? $settings['smtp_from'] : 'noreply@' . $_SERVER['HTTP_HOST'];
    $company = !empty($settings['company_name']) ? $settings['company_name'] : 'Planago Booking';
    $fromName = !empty($settings['smtp_from_name']) ? $settings['smtp_from_name'] : $company;
    $host = $settings['smtp_host'] ?? '';
    $port = $settings['smtp_port'] ?? '587';
    $user = $settings['smtp_user'] ?? '';
    $pass = $settings['smtp_pass'] ?? '';
    
    $mail = new PHPMailer(true);
    
    try {
        // Server-Einstellungen
        if (!empty($host) && !empty($user) && !empty($pass)) {
            $mail->isSMTP();
            $mail->Host       = $host;
            $mail->SMTPAuth   = true;
            $mail->Username   = $user;
            $mail->Password   = $pass;
            $mail->SMTPSecure = ($port == 465) ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $port;
            $mail->Timeout    = 3; // Timeout auf 3 Sekunden reduzieren (verhindert Absturz bei Buchung)
        } else {
            // Fallback auf normale PHP mail() Funktion, falls keine SMTP-Daten hinterlegt sind
            $mail->isMail();
        }
        
        // Absender und Empfänger
        $mail->setFrom($from, $fromName);
        $mail->addAddress($to);
        
        // Inhalt
        $mail->CharSet = 'UTF-8';
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        
        // iCal Datei anhängen
        if ($icsData) {
            $mail->addStringAttachment($icsData, 'termin.ics', 'base64', 'text/calendar');
        }
        
        $mail->send();
    } catch (Exception $e) {
        // Fehler stumm ignorieren oder ins Log schreiben
        error_log("PHPMailer Fehler: {$mail->ErrorInfo}");
    }
}
?>