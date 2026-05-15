<?php
session_start();

// Wenn schon eingeloggt, direkt zum Dashboard
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: admin.php');
    exit;
}

require_once 'config.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!checkRateLimit('login', 5, 300)) {
        $error = 'Zu viele Fehlversuche! Aus Sicherheitsgründen für 5 Minuten gesperrt.';
    } else {
        $db = getDb();
        
        $stmt = $db->query("SELECT admin_username, admin_password_hash FROM settings LIMIT 1");
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        $adminUser = $settings['admin_username'] ?? 'admin';
        $adminHash = $settings['admin_password_hash'] ?? '';
        
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        
        if ($username === $adminUser && password_verify($password, $adminHash)) {
            // --- SCHUTZ VOR SESSION-FIXATION ---
            session_regenerate_id(true); // Generiert nach erfolgreichem Login eine neue sichere Session-ID
            
            $_SESSION['logged_in'] = true;
            $_SESSION['username'] = $adminUser;
            header('Location: admin.php');
            exit;
        } else {
            // Künstliche Verzögerung gegen Brute-Force-Attacken (Sicherheit)
            sleep(1);
            $error = 'Falscher Benutzername oder Passwort!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planago - Login</title>
    <link rel="stylesheet" href="assets/admin_style.css">
</head>
<body>
    <div class="login-box">
        <h2>Admin Login</h2>
        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST">
            <?php $isDemo = defined('PLANAGO_DEMO_MODE') && PLANAGO_DEMO_MODE; ?>
            <input type="text" name="username" placeholder="Benutzername..." required <?= $isDemo ? 'value="admin" readonly' : 'autofocus' ?>>
            <input type="password" name="password" placeholder="Passwort..." required <?= $isDemo ? 'value="Admin1234567" readonly' : '' ?>>
            <button type="submit">Einloggen</button>
        </form>
        <div class="text-center mt-15">
            <a href="forgot.php" class="fs-13 text-muted" style="text-decoration: none;">Passwort vergessen?</a>
        </div>
        <div class="powered-by">
            Powered by <a href="https://planago.de" target="_blank">Planago</a>
        </div>
    </div>
</body>
</html>