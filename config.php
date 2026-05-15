<?php
// config.php
// Hier definieren wir globale Einstellungen für das gesamte System

// --- SYSTEM VERSION ---
define('PLANAGO_VERSION', '1.0.8'); // WICHTIG: Bei jedem Update anpassen!

// --- SESSION SECURITY ---
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', // Dynamisch: Erlaubt lokales HTTP-Testing, erzwingt SSL live
        'httponly' => true,     // Nicht für JavaScript zugänglich
        'samesite' => 'Strict'  // CSRF-Schutz
    ]);
}

// --- ENFORCE HTTPS & HSTS ---
if ((empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === "off") && isset($_SERVER['HTTP_HOST'])) {
    $redirect = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    header('HTTP/1.1 301 Moved Permanently');
    header('Location: ' . $redirect);
    exit;
}
header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');

// --- NONCE FÜR CSP ---
// Erzeugt eine einzigartige, zufällige Zeichenkette für jeden Request.
define('CSP_NONCE', bin2hex(random_bytes(16)));

// --- CONTENT SECURITY POLICY (CSP) ---
$currentScript = basename($_SERVER['SCRIPT_NAME']);
$strictPages = ['index.php', 'admin.php', 'login.php', 'anleitung.php'];

if (in_array($currentScript, $strictPages)) {
    // Strikte CSP (Maximaler XSS-Schutz - verbietet Inline-Scripts und Inline-Styles komplett)
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-" . CSP_NONCE . "' https://cdn.jsdelivr.net https://npmcdn.com; style-src 'self' 'nonce-" . CSP_NONCE . "' https://fonts.googleapis.com https://cdn.jsdelivr.net https://npmcdn.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:; connect-src 'self' https://planago.de; frame-src 'self';");
} else {
    // Fallback für Install-Bereich: Erlaubt Inlines vorübergehend, damit das Setup-Design funktioniert
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://npmcdn.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net https://npmcdn.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:; connect-src 'self' https://planago.de; frame-src 'self';");
}

// --- LIZENZSCHLÜSSEL (aus Umgebungsvariablen/Datei) ---
$licenseKey = getenv('PLANAGO_LICENSE_KEY');
if (empty($licenseKey) && file_exists(__DIR__ . '/.env')) {
    $envContent = file_get_contents(__DIR__ . '/.env');
    if (preg_match('/PLANAGO_LICENSE_KEY\s*=\s*([^\n\r]+)/', $envContent, $matches)) {
        $licenseKey = trim($matches[1]);
    }
}
define('PLANAGO_LICENSE_KEY', $licenseKey ?: 'demo-key');

// --- SECRET ENCRYPTION ---
function encryptSecret($plainText) {
    if (empty($plainText)) return '';
    $key = hash('sha256', PLANAGO_LICENSE_KEY, true);
    $iv = random_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    $encrypted = openssl_encrypt($plainText, 'aes-256-cbc', $key, 0, $iv);
    return base64_encode($iv . '::' . $encrypted);
}

function decryptSecret($encodedText) {
    if (empty($encodedText)) return '';
    $parts = explode('::', base64_decode($encodedText), 2);
    if (count($parts) !== 2) return $encodedText; // Fallback für alte Klartext-Passwörter
    $key = hash('sha256', PLANAGO_LICENSE_KEY, true);
    $decrypted = openssl_decrypt($parts[1], 'aes-256-cbc', $key, 0, $parts[0]);
    return $decrypted !== false ? $decrypted : $encodedText;
}

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
    @chmod(__DIR__ . '/data/.htaccess', 0644);
    @file_put_contents(__DIR__ . '/data/index.php', "<?php http_response_code(403); exit; ?>");
    @chmod(__DIR__ . '/data/index.php', 0644);
}

function getDb() {
    $dbDir = dirname(DB_PATH);

    // Erstelle Verzeichnis mit sicheren Berechtigungen
    if (!file_exists($dbDir)) { @mkdir($dbDir, 0755, true); }
    @chmod($dbDir, 0755);
    if (file_exists(DB_PATH)) { @chmod(DB_PATH, 0644); }

    // Stellt die Verbindung zur SQLite-Datenbank her
    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // SQLite Tuning für Shared Hosting (Verhindert "Database is locked" Fehler extrem effektiv)
    $pdo->exec('PRAGMA busy_timeout = 5000');
    $pdo->exec('PRAGMA journal_mode = WAL');

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
    $uid = bin2hex(random_bytes(16)) . "@planago.local";

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

    $webhookUrl = $settings['zapier_webhook_url'] ?? '';

    if (!empty($webhookUrl) && filter_var($webhookUrl, FILTER_VALIDATE_URL)) {
        // --- SICHERHEITS-FIX: SSRF Schutz für Webhooks ---
        $parsed = parse_url($webhookUrl);
        
        if (!isset($parsed['scheme']) || strtolower($parsed['scheme']) !== 'https') {
            error_log("Webhook blockiert: Nur HTTPS URLs sind erlaubt.");
            return;
        }
        
        $host = $parsed['host'] ?? '';
        $ip = gethostbyname($host);
        
        // Blockiert interne IPs (127.x, 192.168.x, 10.x, AWS Metadata 169.254.x)
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            error_log("Webhook blockiert: Interne oder reservierte IPs (SSRF) sind nicht erlaubt.");
            return;
        }
        // ------------------------------------------------

        $ch = curl_init($webhookUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 2);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($ch, CURLOPT_NOSIGNAL, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, 500);

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
    $pass = decryptSecret($settings['smtp_pass'] ?? '');

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
            $mail->Timeout    = 3;
        } else {
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
        error_log("PHPMailer Fehler: {$mail->ErrorInfo}");
    }
}

// --- CSRF TOKEN FUNCTIONS ---
function initCsrfToken() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function getCsrfToken() {
    return $_SESSION['csrf_token'] ?? '';
}

function validateCsrfToken($token) {
    return hash_equals($_SESSION['csrf_token'] ?? '', $token ?? '');
}
?>