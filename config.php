<?php
// config.php
// Hier definieren wir globale Einstellungen für das gesamte System

define('DB_PATH', __DIR__ . '/data/database.db');

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
    $ics .= "PRODID:-//Hölter Digital//Planago Booking//DE\r\n";
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

// Globale E-Mail Funktion für das System
function sendSystemMail($to, $subject, $body, $icsData = null) {
    $db = getDb();
    $stmt = $db->query("SELECT smtp_host, smtp_port, smtp_user, smtp_pass, smtp_from FROM settings LIMIT 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $from = !empty($settings['smtp_from']) ? $settings['smtp_from'] : 'noreply@' . $_SERVER['HTTP_HOST'];
    $host = $settings['smtp_host'] ?? '';
    $port = $settings['smtp_port'] ?? '587';
    $user = $settings['smtp_user'] ?? '';
    $pass = $settings['smtp_pass'] ?? '';
    
    $crlf = "\r\n";
    $boundary = md5(time());
    
    // MIME-Header für Multipart E-Mail (HTML + Anhang)
    $mimeHeaders = "MIME-Version: 1.0" . $crlf;
    $mimeHeaders .= "From: Planago <{$from}>" . $crlf;
    $mimeHeaders .= "Reply-To: {$from}" . $crlf;
    $mimeHeaders .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"" . $crlf;

    // E-Mail Body zusammenbauen (HTML Text)
    $message = "--{$boundary}" . $crlf;
    $message .= "Content-Type: text/html; charset=UTF-8" . $crlf;
    $message .= "Content-Transfer-Encoding: 8bit" . $crlf . $crlf;
    $message .= $body . $crlf . $crlf;

    // Falls vorhanden: .ics Datei als Anhang hinzufügen (Base64 kodiert)
    if ($icsData) {
        $message .= "--{$boundary}" . $crlf;
        $message .= "Content-Type: text/calendar; charset=utf-8; method=REQUEST; name=\"termin.ics\"" . $crlf;
        $message .= "Content-Disposition: attachment; filename=\"termin.ics\"" . $crlf;
        $message .= "Content-Transfer-Encoding: base64" . $crlf . $crlf;
        $message .= chunk_split(base64_encode($icsData)) . $crlf;
    }
    $message .= "--{$boundary}--" . $crlf;
    
    // Fallback: Wenn noch gar keine SMTP-Daten eingetragen wurden, nutze die alte PHP Funktion
    if (empty($host) || empty($user) || empty($pass)) {
        @mail($to, $subject, $message, $mimeHeaders);
        return;
    }

    // --- ECHTE SMTP ENGINE (Direkte Verbindung zum Mailserver) ---
    
    $smtpHeaders = $mimeHeaders;
    $smtpHeaders .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=" . $crlf;

    // Bei Port 465 (SSL) direkt verschlüsselt verbinden
    $protocol = ($port == 465) ? 'ssl://' : '';
    $smtp = @fsockopen($protocol . $host, $port, $errno, $errstr, 10);
    if (!$smtp) return; // Verbindung fehlgeschlagen

    // Kleine Hilfsfunktion, um die Server-Antworten abzuwarten
    $readRes = function($sock) { $res = ''; while ($str = fgets($sock, 515)) { $res .= $str; if (substr($str, 3, 1) == ' ') break; } return $res; };

    $readRes($smtp); // Server-Begrüßung lesen
    fputs($smtp, "EHLO localhost" . $crlf); $readRes($smtp);

    // STARTTLS Verschlüsselung für Port 587 (Standard bei Ionos, Strato, etc.) aktivieren
    if ($port == 587 || $port == 25) {
        fputs($smtp, "STARTTLS" . $crlf); $readRes($smtp);
        @stream_socket_enable_crypto($smtp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        fputs($smtp, "EHLO localhost" . $crlf); $readRes($smtp);
    }

    // Sicherer SMTP Login
    fputs($smtp, "AUTH LOGIN" . $crlf); $readRes($smtp);
    fputs($smtp, base64_encode($user) . $crlf); $readRes($smtp);
    fputs($smtp, base64_encode($pass) . $crlf); $authRes = $readRes($smtp);
    
    // Nur senden, wenn Login erfolgreich war (Server meldet Code 235)
    if (substr($authRes, 0, 3) === '235') {
        fputs($smtp, "MAIL FROM: <{$from}>" . $crlf); $readRes($smtp);
        fputs($smtp, "RCPT TO: <{$to}>" . $crlf); $readRes($smtp);
        fputs($smtp, "DATA" . $crlf); $readRes($smtp);
        fputs($smtp, $smtpHeaders . $crlf . $message . $crlf . "." . $crlf); $readRes($smtp);
    }
    fputs($smtp, "QUIT" . $crlf); fclose($smtp);
}
?>