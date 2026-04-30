<?php
// anleitung.php
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planago - Erste Schritte</title>
    <link rel="stylesheet" href="assets/admin_style.css">
    <style>
        .guide-container { max-width: 700px; margin: 5vh auto; padding: 40px; background: var(--surface-color); border-radius: var(--radius); box-shadow: var(--shadow); border: 1px solid var(--border-color); }
        .step { display: flex; gap: 20px; margin-bottom: 30px; }
        .step-number { background: var(--accent); color: white; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 20px; font-weight: bold; flex-shrink: 0; box-shadow: 0 4px 10px var(--accent-glow); }
        .step-content h3 { margin-top: 0; margin-bottom: 8px; color: var(--text-main); font-size: 1.2rem; }
        .step-content p { margin-top: 0; color: var(--text-muted); line-height: 1.6; font-size: 0.95rem; }
    </style>
</head>
<body>
    <div class="guide-container">
        <h1 style="text-align: center; color: var(--text-main); margin-bottom: 10px;">Willkommen bei Planago! 🎉</h1>
        <p style="text-align: center; color: var(--text-muted); margin-bottom: 40px; font-size: 16px;">Deine Installation war erfolgreich. Hier sind die wichtigsten ersten Schritte, um dein neues Buchungssystem startklar zu machen.</p>

        <div class="step">
            <div class="step-number">1</div>
            <div class="step-content">
                <h3>Einloggen & Profil einrichten</h3>
                <p>Klicke unten auf den Button, um zum Dashboard zu gelangen. Logge dich mit dem Benutzernamen <strong>admin</strong> und dem Passwort <strong>admin123</strong> ein.<br><br>
                Gehe danach direkt in den Tab <strong>Unternehmensprofil</strong>, ändere aus Sicherheitsgründen sofort dein Passwort und lade dein Logo hoch!</p>
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

        <div style="text-align: center; margin-top: 40px; padding-top: 30px; border-top: 1px solid var(--border-color);">
            <a href="login.php" style="display: inline-block; background: var(--accent); color: white; padding: 16px 35px; border-radius: 12px; text-decoration: none; font-weight: 600; font-size: 16px; box-shadow: 0 4px 12px var(--accent-glow); transition: 0.2s;">Alles klar, zum Admin-Login!</a>
        </div>
    </div>
</body>
</html>