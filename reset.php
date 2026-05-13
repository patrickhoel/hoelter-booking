<?php
session_start();
require_once 'config.php';

$token = $_GET['token'] ?? '';
$error = '';
$message = '';
$validToken = false;

$db = getDb();

if ($token) {
    // Fallback-Migration
    try { $db->exec("ALTER TABLE settings ADD COLUMN password_reset_token TEXT DEFAULT NULL"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE settings ADD COLUMN password_reset_expires DATETIME DEFAULT NULL"); } catch (Exception $e) {}

    $stmt = $db->prepare("SELECT id FROM settings WHERE password_reset_token = ? AND password_reset_expires > ?");
    $stmt->execute([$token, date('Y-m-d H:i:s')]);
    $settingsRow = $stmt->fetch();
    
    if ($settingsRow) {
        $validToken = true;
    } else {
        $error = "Der Link ist ungültig oder abgelaufen.";
    }
} else {
    $error = "Kein Token angegeben.";
}

if ($validToken && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword = $_POST['new_password'] ?? '';
    if (strlen($newPassword) < 12) {
        $error = "Das Passwort muss mindestens 12 Zeichen lang sein.";
    } elseif (!preg_match('/[A-Z]/', $newPassword) || !preg_match('/[a-z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword)) {
        $error = "Das Passwort muss Groß-, Kleinbuchstaben und Zahlen enthalten.";
    } else {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $db->prepare("UPDATE settings SET admin_password_hash = ?, password_reset_token = NULL, password_reset_expires = NULL WHERE password_reset_token = ?")->execute([$hash, $token]);
        $message = "Dein Passwort wurde erfolgreich geändert!";
        $validToken = false; // Formular ausblenden
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Passwort zurücksetzen - Planago</title>
    <link rel="stylesheet" href="assets/admin_style.css">
</head>
<body>
    <div class="login-box">
        <h2>Neues Passwort vergeben</h2>
        <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if ($message): ?>
            <div style="color: var(--success); background: rgba(52,199,89,0.1); padding: 12px; border-radius: 8px; margin-bottom: 15px; font-size: 14px; font-weight: 500;"><?= htmlspecialchars($message) ?></div>
            <div style="text-align: center; margin-top: 15px;"><a href="login.php" style="display: inline-block; background: var(--accent); color: white; padding: 12px 25px; border-radius: 10px; text-decoration: none; font-weight: 600;">Zum Login</a></div>
        <?php endif; ?>
        <?php if ($validToken): ?>
            <p style="color: var(--text-muted); font-size: 14px; margin-bottom: 20px;">Gib ein neues sicheres Passwort (min. 12 Zeichen, inkl. Groß-/Kleinbuchstaben & Zahlen) für deinen Admin-Zugang ein.</p>
            <form method="POST"><input type="password" name="new_password" placeholder="Neues Passwort (min. 12 Zeichen)" required autofocus><button type="submit">Passwort speichern</button></form>
        <?php endif; ?>
        <?php if (!$message && !$validToken): ?><div style="text-align: center; margin-top: 15px;"><a href="login.php" style="font-size: 13px; color: var(--text-muted); text-decoration: none; transition: color 0.2s;" onmouseover="this.style.color='var(--accent)'" onmouseout="this.style.color='var(--text-muted)'">&larr; Zurück zum Login</a></div><?php endif; ?>
    </div>
</body>
</html>