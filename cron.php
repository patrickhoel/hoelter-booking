<?php
// cron.php
// Dieser "Poor Man's Cron" verschickt die Google-Bewertungs-E-Mails 24h nach dem Termin.
// Er wird im Hintergrund passiv aufgerufen, sobald jemand die Website besucht.

require_once 'config.php';

try {
    $db = getDb();
    
    // 1. Settings checken
    $settingsStmt = $db->query("SELECT company_name, company_address, company_link_impressum, company_link_privacy, company_link_agb, company_logo, enable_review_email, google_review_link FROM settings LIMIT 1");
    $sysSettings = $settingsStmt->fetch(PDO::FETCH_ASSOC);
    
    // Wenn deaktiviert oder kein Link hinterlegt, abbrechen (still)
    if (empty($sysSettings['enable_review_email']) || empty($sysSettings['google_review_link'])) {
        exit;
    }
    
    // 2. 24 Stunden in die Vergangenheit rechnen (zeitzonensicher)
    $yesterday = (new DateTime('-24 hours'))->format('Y-m-d H:i:s');
    
    // 3. Finde bestätigte Termine, die älter als 24h sind und noch keine Mail haben
    $stmt = $db->prepare("
        SELECT b.id, b.customer_name, b.customer_email, e.name as event_name
        FROM bookings b
        JOIN event_types e ON b.event_type_id = e.id
        WHERE b.status = 'confirmed' 
        AND b.review_email_sent = 0 
        AND b.start_time <= ?
    ");
    $stmt->execute([$yesterday]);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($bookings) > 0) {
        // E-Mail Footer vorbereiten
        $companyName = $sysSettings['company_name'] ?? 'Planago Booking';
        $reviewLink = htmlspecialchars($sysSettings['google_review_link']);
        
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
        $basePath = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
        $baseUrl = rtrim($protocol . "://" . $_SERVER['HTTP_HOST'] . $basePath, '/');

        $footerName = htmlspecialchars($companyName);
        $footerAddress = nl2br(htmlspecialchars($sysSettings['company_address']));
        $footerImpressum = htmlspecialchars($sysSettings['company_link_impressum'] ?: '#');
        $footerPrivacy = htmlspecialchars($sysSettings['company_link_privacy'] ?: '#');
        $footerAgb = htmlspecialchars($sysSettings['company_link_agb'] ?: '#');
        
        $agbHtml = !empty($sysSettings['company_link_agb']) ? "<a href='$footerAgb' style='color: #86868b; text-decoration: underline; margin-right: 15px;'>AGB</a>" : "";
        $logoHtml = !empty($sysSettings['company_logo']) ? "<img src='" . $baseUrl . "/logo.php' alt='$footerName' style='max-height: 40px; margin-bottom: 10px;'><br>" : "";

        $emailFooter = "
            <div style='margin-top: 40px; padding-top: 20px; border-top: 1px solid #e5e5ea; text-align: center; color: #86868b; font-size: 11px; line-height: 1.6;'>
                $logoHtml <strong>$footerName</strong><br> $footerAddress<br><br> $agbHtml
                <a href='$footerImpressum' style='color: #86868b; text-decoration: underline; margin-right: 15px;'>Impressum</a>
                <a href='$footerPrivacy' style='color: #86868b; text-decoration: underline;'>Datenschutz</a><br><br>
                <span style='color: #d2d2d7;'>Powered by <strong>Planago</strong></span>
            </div></div>";
        
        foreach ($bookings as $booking) {
            $subject = "Wie war dein Termin? - $companyName";
            $body = "
            <div style='font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif; max-width: 550px; margin: 40px auto; padding: 30px; background-color: #ffffff; border: 1px solid #d2d2d7; border-radius: 18px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); text-align: center;'>
                <h1 style='color: #1d1d1f; font-size: 24px; margin-bottom: 15px;'>Wir hoffen, du warst zufrieden!</h1>
                <p style='color: #1d1d1f; font-size: 16px; line-height: 1.5;'>Hallo " . htmlspecialchars($booking['customer_name']) . ",</p>
                <p style='color: #1d1d1f; font-size: 16px; line-height: 1.5; margin-bottom: 30px;'>dein Termin (<strong>" . htmlspecialchars($booking['event_name']) . "</strong>) liegt nun etwas zurück. Wenn du mit unserem Service zufrieden warst, würdest du uns mit einer kurzen Google-Bewertung riesig helfen!</p>
                <a href='$reviewLink' target='_blank' style='display: inline-block; background-color: #34c759; color: white; padding: 15px 30px; text-decoration: none; border-radius: 12px; font-weight: 600; font-size: 16px;'>🌟 Jetzt kurz auf Google bewerten</a>
                <p style='color: #86868b; font-size: 13px; margin-top: 25px;'>Dauert weniger als 1 Minute. Vielen herzlichen Dank!</p>
                " . $emailFooter;
                
            sendSystemMail($booking['customer_email'], $subject, $body);
            $db->prepare("UPDATE bookings SET review_email_sent = 1 WHERE id = ?")->execute([$booking['id']]);
        }
    }
} catch (Exception $e) { /* Still scheitern */ }
?>