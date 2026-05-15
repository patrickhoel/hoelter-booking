<?php
session_start();
require_once 'config.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!checkRateLimit('forgot_password', 3, 900)) {
        $error = 'Zu viele Anfragen! Aus Sicherheitsgründen für 15 Minuten gesperrt.';
    } else {
        $db = getDb();
        
        // Fallback-Migration für bestehende Updates: Sicherstellen, dass die Spalten existieren
        try { $db->exec("ALTER TABLE settings ADD COLUMN password_reset_token TEXT DEFAULT NULL"); } catch (Exception $e) {}
        try { $db->exec("ALTER TABLE settings ADD COLUMN password_reset_expires DATETIME DEFAULT NULL"); } catch (Exception $e) {}

        $email = $_POST['email'] ?? '';
        
        $stmt = $db->query("SELECT admin_email, company_name FROM settings LIMIT 1");
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (empty($settings['admin_email'])) {
            $error = "Es wurde keine Admin-E-Mail im System hinterlegt. Ein Zurücksetzen ist nicht möglich. Bitte kontaktiere den Support.";
        } elseif (strtolower(trim($email)) === strtolower(trim($settings['admin_email']))) {
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            $db->prepare("UPDATE settings SET password_reset_token = ?, password_reset_expires = ?")->execute([$token, $expires]);
            
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
            $basePath = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
            $baseUrl = rtrim($protocol . "://" . $_SERVER['HTTP_HOST'] . $basePath, '/');
            $resetLink = $baseUrl . "/reset.php?token=" . $token;
            
            $subject = "Passwort zurücksetzen - " . ($settings['company_name'] ?? 'Planago');
            $body = "
            <div style='font-family: -apple-system, BlinkMacSystemFont, sans-serif; max-width: 550px; margin: 40px auto; padding: 30px; background: #ffffff; border: 1px solid #d2d2d7; border-radius: 18px;'>
                <h2 style='color: #1d1d1f; text-align: center;'>Passwort zurücksetzen</h2>
                <p style='color: #1d1d1f;'>Hallo,</p>
                <p style='color: #1d1d1f;'>es wurde angefragt, das Admin-Passwort für dein Buchungssystem zurückzusetzen. Klicke auf den folgenden Button, um ein neues Passwort zu vergeben:</p>
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='$resetLink' style='background: #34c759; color: white; padding: 14px 25px; text-decoration: none; border-radius: 10px; font-weight: 600;'>Neues Passwort vergeben</a>
                </div>
                <p style='color: #86868b; font-size: 13px; text-align: center;'>Dieser Link ist für 1 Stunde gültig. Falls du diese Anfrage nicht gestellt hast, kannst du diese E-Mail ignorieren.</p>
            </div>";
            
            sendSystemMail($email, $subject, $body);
            $message = "Falls die E-Mail-Adresse übereinstimmt, wurde ein Link zum Zurücksetzen gesendet.";
        } else {
            sleep(1); // Verzögerung gegen Brute-Force und Timing-Angriffe
            // Aus Sicherheitsgründen dieselbe Meldung anzeigen (verhindert das Ausspähen von E-Mails)
            $message = "Falls die E-Mail-Adresse übereinstimmt, wurde ein Link zum Zurücksetzen gesendet.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Passwort vergessen - Planago</title>
    <link rel="stylesheet" href="assets/admin_style.css">
</head>
<body>
    <div class="login-box">
        <h2>Passwort vergessen</h2>
        <p style="color: var(--text-muted); font-size: 14px; margin-bottom: 20px;">Gib deine Admin E-Mail-Adresse ein, um einen Link zum Zurücksetzen zu erhalten.</p>
        <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if ($message): ?><div style="color: var(--success); background: rgba(52,199,89,0.1); padding: 12px; border-radius: 8px; margin-bottom: 15px; font-size: 14px; font-weight: 500;"><?= htmlspecialchars($message) ?></div><?php endif; ?>
        <form method="POST"><input type="email" name="email" placeholder="Deine Admin E-Mail..." required autofocus><button type="submit">Link senden</button></form>
        <div style="text-align: center; margin-top: 15px;"><a href="login.php" style="font-size: 13px; color: var(--text-muted); text-decoration: none; transition: color 0.2s;" onmouseover="this.style.color='var(--accent)'" onmouseout="this.style.color='var(--text-muted)'">&larr; Zurück zum Login</a></div>
    </div>
</body>
</html>