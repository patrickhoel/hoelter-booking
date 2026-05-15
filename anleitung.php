<?php
// anleitung.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';

$tempPassword = $_SESSION['temp_admin_password'] ?? 'Dein generiertes Passwort';
unset($_SESSION['temp_admin_password']); // Nur einmalig für diese Ansicht speichern
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planago - Erste Schritte</title>
    <link rel="stylesheet" href="assets/admin_style.css">
    <style nonce="<?= htmlspecialchars(CSP_NONCE) ?>">
        .guide-container { max-width: 700px; margin: 5vh auto; padding: 40px; background: var(--surface-color); border-radius: var(--radius); box-shadow: var(--shadow); border: 1px solid var(--border-color); }
        .step { display: flex; gap: 20px; margin-bottom: 30px; }
        .step-number { background: var(--accent); color: white; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 20px; font-weight: bold; flex-shrink: 0; box-shadow: 0 4px 10px var(--accent-glow); }
        .step-content h3 { margin-top: 0; margin-bottom: 8px; color: var(--text-main); font-size: 1.2rem; }
        .step-content p { margin-top: 0; color: var(--text-muted); line-height: 1.6; font-size: 0.95rem; }
        .guide-btn { display: inline-block; background: var(--accent); color: white; padding: 16px 35px; border-radius: 12px; text-decoration: none; font-weight: 600; font-size: 16px; box-shadow: 0 4px 12px var(--accent-glow); transition: 0.2s; }
        .guide-btn:hover { background: var(--accent-bright); transform: translateY(-1px); }
    </style>
</head>
<body>
    <div class="guide-container">
        <h1 class="text-center text-main mb-10 mt-0">Willkommen bei Planago! 🎉</h1>
        <p class="text-center text-muted mb-20 fs-15">Deine Installation war erfolgreich. Hier sind die wichtigsten ersten Schritte, um dein neues Buchungssystem startklar zu machen.</p>

        <div class="step">
            <div class="step-number">1</div>
            <div class="step-content">
                <h3>Einloggen & Profil einrichten</h3>
                <p>Klicke unten auf den Button, um zum Dashboard zu gelangen. Logge dich mit dem Benutzernamen <strong>admin</strong> und dem automatisch generierten Passwort <strong style="background: #fff; padding: 2px 6px; border-radius: 4px; border: 1px solid #ccc;"><?= htmlspecialchars($tempPassword) ?></strong> ein.<br><br>
                <span style="color: #d9534f; font-weight: 600;">⚠️ Wichtig:</span> Das System wird dich beim ersten Login zwingen, dieses Passwort zu ändern. Gehe danach direkt in den Tab <strong>Unternehmensprofil</strong> und lade dein Logo hoch!</p>
            </div>
        </div>

        <div class="step">
            <div class="step-number">2</div>
            <div class="step-content">
                <h3>Terminarten anlegen</h3>
                <p>Im Tab <strong>Terminarten</strong> kannst du definieren, was deine Kunden buchen können (z.B. "Erstgespräch" oder "Einzeltraining"). Klicke bei einer Terminart auf "Einstellen", um spezifische Zeiten, Puffer oder eigene Formularfelder festzulegen.</p>
            </div>
        </div>

        <div class="step">
            <div class="step-number">3</div>
            <div class="step-content">
                <h3>E-Mail-Versand (SMTP) einrichten</h3>
                <p>Damit Planago automatische Bestätigungen und Google-Bewertungsanfragen verschicken kann, hinterlege im Tab <strong>E-Mail & SMTP</strong> die Zugangsdaten deines E-Mail-Postfachs.</p>
            </div>
        </div>

        <div class="step">
            <div class="step-number">4</div>
            <div class="step-content">
                <h3>Datenschutz (DSGVO) anpassen</h3>
                <p>Planago verarbeitet Namen und E-Mail-Adressen. Bitte ergänze die Datenschutzerklärung auf deiner Website um einen kurzen Hinweis, dass du für Terminbuchungen die Software "Planago" verwendest.</p>
            </div>
        </div>

        <div class="step">
            <div class="step-number">5</div>
            <div class="step-content">
                <h3>Widget auf deiner Website einbinden</h3>
                <p>Unter dem neuen Menüpunkt <strong>Widget & Link</strong> im Admin-Panel findest du einen fertigen HTML-Code. Diesen kannst du ganz einfach bei WordPress, Wix oder Squarespace in einen "HTML-Block" einfügen. Alternativ kannst du auch den direkten Link für Instagram und Co. verwenden.</p>
            </div>
        </div>

        <div class="text-center mt-20 pt-15 border-top">
            <a href="login.php" class="guide-btn">Alles klar, zum Admin-Login!</a>
        </div>
    </div>
</body>
</html>