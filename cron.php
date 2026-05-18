<?php
// cron.php
// This file handles all scheduled tasks for Planago.
// It should be called periodically (e.g., once every few hours) by a cron job or a "poor man's cron".

// Prevent direct browser access from showing anything sensitive
if (php_sapi_name() !== 'cli' && $_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(403);
    exit;
}

require_once 'config.php';

// --- TASK 1: LICENSE VALIDATION (PHONING HOME) ---
function checkLicense() {
    $db = getDb();
    $stmt = $db->query("SELECT license_status, license_last_check FROM settings LIMIT 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$settings) return; // Should not happen on an installed system

    $lastCheck = new DateTime($settings['license_last_check'] ?? '1970-01-01');
    $now = new DateTime();
    $diff = $now->getTimestamp() - $lastCheck->getTimestamp();

    // Check only once every 24 hours to save performance
    if ($diff < 86400 && $settings['license_status'] === 'valid') {
        return;
    }

    // --- Phoning Home ---
    $domain = $_SERVER['HTTP_HOST'] ?? 'unknown';
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $basePath = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    $cronUrl = rtrim($protocol . "://" . $domain . $basePath, '/') . '/cron.php';

    // Bei Netzwerkfehlern behalten wir den bisherigen Status, um niemanden auszusperren!
    $newStatus = $settings['license_status']; 

    if (PLANAGO_LICENSE_KEY !== 'demo-key') {
        $ch = curl_init('https://planago.de/api_license.php');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'license_key' => PLANAGO_LICENSE_KEY,
            'domain' => $domain,
            'cron_url' => $cronUrl
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        curl_close($ch);

        if ($response) {
            $licenseData = json_decode($response, true);
            // Nur bei expliziten und gültigen Antworten den Status anpassen
            if (isset($licenseData['status']) && in_array($licenseData['status'], ['valid', 'invalid', 'revoked'])) {
                $newStatus = $licenseData['status'];
            }
        }
    } else {
        $newStatus = 'valid'; // Demo key is always valid
    }

    // Update local database with the result
    $updateStmt = $db->prepare("UPDATE settings SET license_status = ?, license_last_check = CURRENT_TIMESTAMP");
    $updateStmt->execute([$newStatus]);
}

// --- TASK 2: SEND GOOGLE REVIEW E-MAILS ---
function sendReviewEmails() {
    $db = getDb();
    $stmt = $db->query("SELECT enable_review_email, google_review_link, company_name FROM settings LIMIT 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);

    if (empty($settings['enable_review_email']) || empty($settings['google_review_link'])) {
        return;
    }

    // Find all completed bookings older than 24 hours where no review mail has been sent yet
    $bookingStmt = $db->query("
        SELECT id, customer_name, customer_email 
        FROM bookings 
        WHERE status = 'confirmed' 
        AND review_email_sent = 0 
        AND start_time < datetime('now', '-24 hours')
        AND start_time > datetime('now', '-30 days') -- Don't send for very old bookings
    ");
    $bookings = $bookingStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($bookings as $booking) {
        $subject = "Wie war dein Termin bei " . htmlspecialchars($settings['company_name']) . "?";
        $body = "<div style='font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif; max-width: 550px; margin: 40px auto; padding: 30px; background-color: #ffffff; border: 1px solid #d2d2d7; border-radius: 18px;'><h1 style='font-size: 22px; margin-bottom: 20px;'>Hallo " . htmlspecialchars($booking['customer_name']) . ",</h1><p>wir hoffen, du warst mit deinem Termin bei uns zufrieden!</p><p>Wenn du einen Moment Zeit hast, würden wir uns riesig über eine kurze Bewertung auf Google freuen. Dein Feedback hilft uns und anderen Kunden sehr.</p><div style='text-align: center; margin: 30px 0;'><a href='" . htmlspecialchars($settings['google_review_link']) . "' style='background-color: #007aff; color: #ffffff; padding: 15px 30px; text-decoration: none; border-radius: 10px; font-weight: 600;'>Jetzt bewerten</a></div><p>Vielen Dank für deine Unterstützung!<br>Dein Team von " . htmlspecialchars($settings['company_name']) . "</p></div>";
        
        sendSystemMail($booking['customer_email'], $subject, $body);

        // Mark as sent to prevent double-sending
        $updateStmt = $db->prepare("UPDATE bookings SET review_email_sent = 1 WHERE id = ?");
        $updateStmt->execute([$booking['id']]);
    }
}

// --- TASK 3: SEND APPOINTMENT REMINDERS ---
function sendAppointmentReminders() {
    $db = getDb();
    $stmt = $db->query("SELECT enable_reminders, reminder_hours_before, company_name, company_address, company_link_impressum, company_link_privacy, company_link_agb, company_logo FROM settings LIMIT 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);

    if (empty($settings['enable_reminders'])) {
        return;
    }

    $hoursBefore = (int)($settings['reminder_hours_before'] ?? 24);

    // Find all confirmed bookings that are due for a reminder
    $bookingStmt = $db->prepare("
        SELECT b.id, b.customer_name, b.customer_email, b.start_time, e.name as event_name
        FROM bookings b
        JOIN event_types e ON b.event_type_id = e.id
        WHERE b.status = 'confirmed'
        AND b.reminder_sent = 0
        AND b.start_time > datetime('now')
        AND b.start_time <= datetime('now', '+' || ? || ' hours')
    ");
    $bookingStmt->execute([$hoursBefore]);
    $bookings = $bookingStmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($bookings)) {
        return;
    }

    // Prepare email footer once
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $basePath = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    $baseUrl = rtrim($protocol . "://" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $basePath, '/');
    $footerName = htmlspecialchars($settings['company_name']);
    $footerAddress = nl2br(htmlspecialchars($settings['company_address']));
    $footerImpressum = htmlspecialchars($settings['company_link_impressum'] ?: '#');
    $footerPrivacy = htmlspecialchars($settings['company_link_privacy'] ?: '#');
    $footerAgb = htmlspecialchars($settings['company_link_agb'] ?: '#');
    $agbHtml = !empty($settings['company_link_agb']) ? "<a href='$footerAgb' style='color: #86868b; text-decoration: underline; margin-right: 15px;'>AGB</a>" : "";
    $logoHtml = !empty($settings['company_logo']) ? "<img src='" . $baseUrl . "/logo.php?v=" . md5($settings['company_logo']) . "' alt='$footerName' style='max-height: 40px; margin-bottom: 10px;'><br>" : "";
    $emailFooter = "
        <div style='margin-top: 40px; padding-top: 20px; border-top: 1px solid #e5e5ea; text-align: center; color: #86868b; font-size: 11px; line-height: 1.6;'>
            $logoHtml
            <strong>$footerName</strong><br>
            $footerAddress<br><br>
            $agbHtml
            <a href='$footerImpressum' style='color: #86868b; text-decoration: underline; margin-right: 15px;'>Impressum</a>
            <a href='$footerPrivacy' style='color: #86868b; text-decoration: underline;'>Datenschutz</a>
            <br><br>
            <span style='color: #d2d2d7;'>Powered by <a href='https://planago.de' target='_blank' style='color: inherit; text-decoration: none;'><strong>Planago</strong></a></span>
        </div>
    </div>";

    foreach ($bookings as $booking) {
        $startTime = new DateTime($booking['start_time']);
        $subject = "Erinnerung: Dein Termin bei " . htmlspecialchars($settings['company_name']);
        $body = "<div style='font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif; max-width: 550px; margin: 40px auto; padding: 30px; background-color: #ffffff; border: 1px solid #d2d2d7; border-radius: 18px; box-shadow: 0 4px 20px rgba(0,0,0,0.05);'><div style='text-align: center; margin-bottom: 25px;'><h1 style='color: #1d1d1f; font-size: 24px; margin-bottom: 5px;'>Termin-Erinnerung</h1><p style='color: #86868b; font-size: 16px;'>Hallo " . htmlspecialchars($booking['customer_name']) . ", dein Termin steht bald an.</p></div><div style='background-color: #f5f5f7; padding: 20px; border-radius: 14px; margin-bottom: 25px;'><table style='width: 100%; border-collapse: collapse;'><tr><td style='color: #86868b; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; padding-bottom: 5px;'>Was</td></tr><tr><td style='color: #1d1d1f; font-weight: 600; font-size: 17px; padding-bottom: 15px;'>" . htmlspecialchars($booking['event_name']) . "</td></tr><tr><td style='color: #86868b; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; padding-bottom: 5px;'>Wann</td></tr><tr><td style='color: #1d1d1f; font-weight: 600; font-size: 17px;'>" . $startTime->format('d.m.Y') . " um " . $startTime->format('H:i') . " Uhr</td></tr></table></div><p style='color: #1d1d1f; font-size: 15px; line-height: 1.5; margin-bottom: 25px;'>Wir freuen uns auf dich!</p>" . $emailFooter;
        
        sendSystemMail($booking['customer_email'], $subject, $body);

        // Mark as sent to prevent double-sending
        $updateStmt = $db->prepare("UPDATE bookings SET reminder_sent = 1 WHERE id = ?");
        $updateStmt->execute([$booking['id']]);
    }
}

// --- RUN ALL TASKS ---
try {
    checkLicense();
    sendReviewEmails();
    sendAppointmentReminders();
    // Future tasks can be added here
} catch (Exception $e) {
    http_response_code(500);
    error_log("Cron job failed: " . $e->getMessage());
}