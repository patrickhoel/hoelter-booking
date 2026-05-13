<?php
session_start();

// Nur wenn gerade eingeloggt und Passwort-Erzwingung aktiv
if (!isset($_SESSION['force_password_change']) || $_SESSION['force_password_change'] !== true) {
    header('Location: login.php');
    exit;
}

require_once 'config.php';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Validierung
    if (strlen($newPassword) < 12) {
        $error = 'Passwort muss mindestens 12 Zeichen lang sein.';
    } elseif (!preg_match('/[A-Z]/', $newPassword)) {
        $error = 'Passwort muss mindestens einen Großbuchstaben enthalten.';
    } elseif (!preg_match('/[a-z]/', $newPassword)) {
        $error = 'Passwort muss mindestens einen Kleinbuchstaben enthalten.';
    } elseif (!preg_match('/[0-9]/', $newPassword)) {
        $error = 'Passwort muss mindestens eine Ziffer enthalten.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Passwörter stimmen nicht überein.';
    } else {
        $db = getDb();
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $db->prepare("UPDATE settings SET admin_password_hash = ?, force_password_change = 0 WHERE 1")->execute([$hash]);

        $_SESSION['force_password_change'] = false;
        header('Location: admin.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Passwort ändern - Planago</title>
    <link rel="stylesheet" href="assets/admin_style.css">
</head>
<body>
    <div class="login-box">
        <h2>🔐 Passwort ändern (erforderlich)</h2>
        <p style="color: #666; font-size: 14px; margin-bottom: 20px;">Dies ist dein erstes Login. Bitte setze ein starkes Passwort.</p>

        <?php if ($error): ?>
            <div class="error" style="margin-bottom: 15px;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500;">Neues Passwort</label>
                <input type="password" name="new_password" required
                    style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                <small style="color: #999; display: block; margin-top: 5px;">Mindestens 12 Zeichen, mit Groß-, Kleinbuchstaben und Ziffern</small>
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500;">Passwort bestätigen</label>
                <input type="password" name="confirm_password" required
                    style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
            </div>

            <button type="submit" style="width: 100%; padding: 12px; background: #34c759; color: white; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 16px;">Passwort speichern & weiter</button>
        </form>
    </div>
</body>
</html>
