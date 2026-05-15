<?php
require_once 'config.php';

$token = $_GET['token'] ?? '';
$message = '';
$booking = null;

$db = getDb();

// Settings für Links auslesen
$sysStmt = $db->query("SELECT company_name, company_link_impressum, company_link_privacy, company_link_agb, company_address, widget_accent_color, company_logo, theme_mode, admin_email FROM settings LIMIT 1");
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
            // Stornierung durchführen (Status auf storniert setzen, damit es im Admin-Panel sichtbar bleibt)
            $delStmt = $db->prepare("UPDATE bookings SET status = 'cancelled_by_customer' WHERE id = ?");
            $delStmt->execute([$booking['id']]);
            
            // --- E-MAIL AN ADMIN SENDEN ---
            $adminEmail = $sysSettings['admin_email'] ?? '';
            if (!empty($adminEmail)) {
                $startObj = new DateTime($booking['start_time']);
                $formattedDateStr = $startObj->format('d.m.Y');
                $formattedTimeStr = $startObj->format('H:i');
                
                $adminSubj = "Termin storniert: {$booking['event_title']} am {$formattedDateStr}";
                
                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
                $basePath = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
                $baseUrl = rtrim($protocol . "://" . $_SERVER['HTTP_HOST'] . $basePath, '/');
                
                $emailFooter = "
                <div style='margin-top: 40px; padding-top: 20px; border-top: 1px solid #e5e5ea; text-align: center; color: #86868b; font-size: 11px; line-height: 1.6;'>
                    " . (!empty($companyLogo) ? "<img src='{$baseUrl}/logo.php?v=" . md5($companyLogo) . "' alt='" . htmlspecialchars($companyName) . "' style='max-height: 40px; margin-bottom: 10px;'><br>" : "") . "
                    <strong>" . htmlspecialchars($companyName) . "</strong><br>
                    " . nl2br(htmlspecialchars($companyAddress)) . "<br><br>
                    <span style='color: #d2d2d7;'>Powered by <a href='https://planago.de' target='_blank' style='color: inherit; text-decoration: none;'><strong>Planago</strong></a></span>
                </div></div>";
                
                $adminBody = "
                <div style='font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif; max-width: 550px; margin: 40px auto; padding: 30px; background-color: #ffffff; border: 1px solid #d2d2d7; border-radius: 18px; box-shadow: 0 4px 20px rgba(0,0,0,0.05);'>
                    <div style='text-align: center; margin-bottom: 25px;'>
                        <h1 style='color: #ff3b30; font-size: 24px; margin-bottom: 5px;'>Termin storniert</h1>
                        <p style='color: #86868b; font-size: 16px;'>Ein Kunde hat seinen Termin online abgesagt.</p>
                    </div>
                    <div style='background-color: #f5f5f7; padding: 20px; border-radius: 14px; margin-bottom: 25px;'>
                        <table style='width: 100%; border-collapse: collapse;'>
                            <tr><td style='color: #86868b; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; padding-bottom: 5px;'>Kunde</td></tr>
                            <tr><td style='color: #1d1d1f; font-weight: 600; font-size: 17px; padding-bottom: 15px;'>{$booking['customer_name']} <br><a href='mailto:{$booking['customer_email']}' style='color: #34c759; font-size: 14px; text-decoration: none;'>{$booking['customer_email']}</a></td></tr>
                            <tr><td style='color: #86868b; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; padding-bottom: 5px;'>Was</td></tr>
                            <tr><td style='color: #1d1d1f; font-weight: 600; font-size: 17px; padding-bottom: 15px;'>{$booking['event_title']}</td></tr>
                            <tr><td style='color: #86868b; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; padding-bottom: 5px;'>Wann</td></tr>
                            <tr><td style='color: #1d1d1f; font-weight: 600; font-size: 17px;'>{$formattedDateStr} um {$formattedTimeStr} Uhr</td></tr>
                        </table>
                    </div>
                    <div style='text-align: center; border-top: 1px solid #d2d2d7; padding-top: 25px;'>
                        <a href='{$baseUrl}/admin.php' style='display: inline-block; background-color: #007aff; color: #ffffff; text-decoration: none; padding: 12px 25px; border-radius: 10px; font-weight: 600; font-size: 14px;'>Zum Admin-Dashboard</a>
                    </div>
                " . $emailFooter;
                
                sendSystemMail($adminEmail, $adminSubj, $adminBody);
            }

            // --- ZAPIER WEBHOOK SENDEN ---
            $webhookPayload = [
                'event_name' => $booking['event_title'],
                'customer_name' => $booking['customer_name'],
                'customer_email' => $booking['customer_email'],
                'start_time' => (new DateTime($booking['start_time']))->format(DateTime::ATOM),
                'status' => 'cancelled_by_customer',
                'type' => 'cancelled'
            ];
            sendToWebhook($webhookPayload);
            
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
    <span style="color: #d2d2d7;">Powered by <a href="https://planago.de" target="_blank" style="color: inherit; text-decoration: none;"><strong>Planago</strong></a></span>
</div>

</body>
</html>