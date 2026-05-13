<?php
session_start();

// Wenn schon eingeloggt, direkt zum Dashboard
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: admin.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'config.php';
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
            <input type="text" name="username" placeholder="Benutzername..." required autofocus>
            <input type="password" name="password" placeholder="Passwort..." required>
            <button type="submit">Einloggen</button>
        </form>
        <div style="text-align: center; margin-top: 15px;">
            <a href="forgot.php" style="font-size: 13px; color: var(--text-muted); text-decoration: none; transition: color 0.2s;" onmouseover="this.style.color='var(--accent)'" onmouseout="this.style.color='var(--text-muted)'">Passwort vergessen?</a>
        </div>
        <div style="text-align: center; margin-top: 25px; padding-top: 15px; border-top: 1px solid var(--border); font-size: 12px; color: var(--text-muted);">
            Powered by <a href="https://planago.de" target="_blank" style="color: var(--accent); text-decoration: none; font-weight: 600; transition: opacity 0.2s;" onmouseover="this.style.opacity='0.8'" onmouseout="this.style.opacity='1'">Planago</a>
        </div>
    </div>
</body>
</html>