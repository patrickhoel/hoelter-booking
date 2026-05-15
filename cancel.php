<?php
require_once 'config.php';

$token = $_GET['token'] ?? '';
$message = '';
$booking = null;

$db = getDb();

// Settings für Links auslesen
$sysStmt = $db->query("SELECT company_name, company_link_impressum, company_link_privacy, company_link_agb, company_address, widget_accent_color, company_logo, theme_mode FROM settings LIMIT 1");
$sysSettings = $sysStmt->fetch(PDO::FETCH_ASSOC);
$companyName = $sysSettings['company_name'] ?? 'Planago Booking';
$impressumLink = $sysSettings['company_link_impressum'] ?? '';
$privacyLink = $sysSettings['company_link_privacy'] ?? '';
$agbLink = $sysSettings['company_link_agb'] ?? '';
$companyAddress = $sysSettings['company_address'] ?? '';
$accentColor = $sysSettings['widget_accent_color'] ?? '#34c759';
$companyLogo = $sysSettings['company_logo'] ?? '';
$themeMode = $sysSettings['theme_mode'] ?? 'auto';

if ($token) {
    // Buchung anhand des Tokens suchen
    $stmt = $db->prepare("SELECT b.*, e.name as event_title, e.cancel_limit_hours FROM bookings b JOIN event_types e ON b.event_type_id = e.id WHERE b.cancel_token = ?");
    $stmt->execute([$token]);
    $booking = $stmt->fetch();

    if (!$booking) {
        $message = "<div class='alert alert-error'>Ungültiger oder bereits verwendeter Link.</div>";
    } else {
        $limitHours = $booking['cancel_limit_hours'] ?? 24;
        $bookingTime = strtotime($booking['start_time']);
        $deadline = $bookingTime - ($limitHours * 3600);
        
        if (time() > $deadline) {
            $message = "<div class='alert alert-error'><strong>Zu kurzfristig!</strong><br>Eine Online-Stornierung ist leider nur bis zu {$limitHours} Stunden vor dem Termin möglich. Bitte kontaktiere uns direkt, um eine Lösung zu finden.</div>";
            $booking = null; // Buchung & Button ausblenden
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Stornierung durchführen (Löschen)
            $delStmt = $db->prepare("DELETE FROM bookings WHERE id = ?");
            $delStmt->execute([$booking['id']]);
            
            $message = "<div class='alert alert-success'>Dein Termin wurde erfolgreich storniert.</div>";
            $booking = null; // Buchung ausblenden, da storniert
        }
    }
} else {
    $message = "<div class='alert alert-error'>Kein Token übergeben.</div>";
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Termin stornieren - Planago</title>
    <link rel="stylesheet" href="assets/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --accent: <?= htmlspecialchars($accentColor) ?>;
            --accent-hover: <?= htmlspecialchars($accentColor) ?>;
        }
        
        <?php if ($themeMode === 'light'): ?>
        :root, body, html {
            color-scheme: light !important;
            --bg-color: #f5f5f7 !important;
            --surface-color: #ffffff !important;
            --text-main: #1d1d1f !important;
            --text-muted: #86868b !important;
            --border-color: #e5e5ea !important;
            --input-bg: #ffffff !important;
        }
        <?php elseif ($themeMode === 'dark'): ?>
        :root, body, html {
            color-scheme: dark !important;
            --bg-color: #000000 !important;
            --surface-color: #1c1c1e !important;
            --text-main: #f5f5f7 !important;
            --text-muted: #a1a1a6 !important;
            --border-color: #38383a !important;
            --input-bg: #2c2c2e !important;
        }
        <?php endif; ?>

        /* Spezifische Anpassungen für die Storno-Seite */
        body {
            background-color: var(--bg-color, #f5f5f7); 
            color: var(--text-main, #1d1d1f);
        }
        .cancel-container {
            text-align: center;
        }
        .event-details {
            background: var(--input-bg, #ffffff);
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
            text-align: left;
        }
        .event-details p {
            margin: 5px 0 !important;
        }
        .btn-danger {
            background-color: #ff3b30;
            color: white;
            border: none;
            padding: 14px 20px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 1.05rem;
            cursor: pointer;
            width: 100%;
            transition: all 0.2s;
        }
        .btn-danger:hover {
            background-color: #e03026;
            transform: translateY(-1px);
        }
        .alert {
            padding: 15px;
            border-radius: 10px;
            font-weight: 500;
            margin-bottom: 20px;
        }
        .alert-success {
            background-color: #e8f8f0;
            color: #10b981;
            border: 1px solid #a7f3d0;
        }
        .alert-error {
            background-color: #fee2e2;
            color: #ef4444;
            border: 1px solid #fecaca;
        }
    </style>
</head>
<body>

<div class="container cancel-container">
    <?php if (!empty($companyLogo)): ?>
        <div style="text-align: center; margin-bottom: 20px;">
            <img src="logo.php?v=<?= md5($companyLogo) ?>" alt="<?= htmlspecialchars($companyName) ?>" style="max-height: 80px; max-width: 100%; border-radius: 8px;">
        </div>
    <?php endif; ?>

    <?php if ($message): ?>
        <?= $message ?>
    <?php endif; ?>

    <?php if ($booking): ?>
        <h2>Termin stornieren?</h2>
        <p>Bist du sicher, dass du folgenden Termin absagen möchtest?</p>
        
        <div class="event-details">
    <p><strong>Was:</strong> <?= htmlspecialchars($booking['event_title']) ?></p>
    <p><strong>Wann:</strong> <?= date('d.m.Y', strtotime($booking['start_time'])) ?> um <?= date('H:i', strtotime($booking['start_time'])) ?> Uhr</p>
</div>

        <form method="post">
            <button type="submit" class="btn-danger">Ja, Termin endgültig stornieren</button>
        </form>
    <?php endif; ?>
</div>

<!-- Eleganter Planago Corporate Footer -->
<div style="text-align: center; margin-top: 40px; padding-top: 20px; border-top: 1px solid var(--border); color: var(--text-muted); font-size: 12px; line-height: 1.6;">
    <strong><?= htmlspecialchars($companyName) ?></strong><br>
    <?= nl2br(htmlspecialchars($companyAddress)) ?><br><br>
    
    <?php if (!empty($agbLink)): ?>
        <a href="<?= htmlspecialchars($agbLink) ?>" target="_blank" style="color: var(--text-muted); text-decoration: none; margin: 0 10px; transition: color 0.2s;" onmouseover="this.style.color='var(--text-main)'" onmouseout="this.style.color='var(--text-muted)'">AGB</a>
    <?php endif; ?>
    <?php if (!empty($impressumLink)): ?>
        <a href="<?= htmlspecialchars($impressumLink) ?>" target="_blank" style="color: var(--text-muted); text-decoration: none; margin: 0 10px; transition: color 0.2s;" onmouseover="this.style.color='var(--text-main)'" onmouseout="this.style.color='var(--text-muted)'">Impressum</a>
    <?php endif; ?>
    <?php if (!empty($privacyLink)): ?>
        <a href="<?= htmlspecialchars($privacyLink) ?>" target="_blank" style="color: var(--text-muted); text-decoration: none; margin: 0 10px; transition: color 0.2s;" onmouseover="this.style.color='var(--text-main)'" onmouseout="this.style.color='var(--text-muted)'">Datenschutz</a>
    <?php endif; ?>
    
    <br><br>
    <span style="color: #d2d2d7;">Powered by <strong>Planago</strong></span>
</div>

</body>
</html>