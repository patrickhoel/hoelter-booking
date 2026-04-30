<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Nicht authorisiert']);
    exit;
}

require_once 'config.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

$host = $data['smtp_host'] ?? '';
$port = $data['smtp_port'] ?? '587';
$user = $data['smtp_user'] ?? '';
$pass = $data['smtp_pass'] ?? '';
$from = !empty($data['smtp_from']) ? $data['smtp_from'] : 'noreply@' . $_SERVER['HTTP_HOST'];
$fromName = !empty($data['smtp_from_name']) ? $data['smtp_from_name'] : 'Planago Test';
$to = !empty($data['admin_email']) ? $data['admin_email'] : $from;

if (empty($to)) {
    echo json_encode(['error' => 'Bitte trage unten eine Admin E-Mail ein, an die die Test-Mail gesendet werden soll.']);
    exit;
}

$mail = new PHPMailer(true);

try {
    if (!empty($host) && !empty($user) && !empty($pass)) {
        $mail->isSMTP();
        $mail->Host       = $host;
        $mail->SMTPAuth   = true;
        $mail->Username   = $user;
        $mail->Password   = $pass;
        $mail->SMTPSecure = ($port == 465) ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $port;
        $mail->Timeout    = 5; // Für den dedizierten Test geben wir ihm etwas mehr Zeit (5 Sek)
    } else {
        $mail->isMail();
    }
    
    $mail->setFrom($from, $fromName);
    $mail->addAddress($to);
    $mail->CharSet = 'UTF-8';
    $mail->isHTML(true);
    $mail->Subject = "Planago E-Mail Test";
    $mail->Body    = "<h3>Erfolg! 🎉</h3><p>Wenn du diese E-Mail liest, sind deine SMTP-Einstellungen in Planago korrekt eingerichtet.</p>";
    
    $mail->send();
    echo json_encode(['message' => 'Test-E-Mail wurde erfolgreich an ' . htmlspecialchars($to) . ' gesendet!']);
} catch (Exception $e) {
    echo json_encode(['error' => 'Fehler beim Senden: ' . $mail->ErrorInfo]);
}
?>