<?php
// api_confirm_booking.php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Nicht authorisiert']);
    exit;
}

require_once 'config.php';
header('Content-Type: application/json');

try {
    // CSRF Token Validierung
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $clientToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $headers['X-CSRF-Token'] ?? $headers['X-Csrf-Token'] ?? '';
    if (!validateCsrfToken($clientToken)) {
        http_response_code(403);
        echo json_encode(['error' => 'Ungültiger CSRF-Token. Bitte die Seite neu laden.']);
        exit;
    }

    if (defined('PLANAGO_DEMO_MODE') && PLANAGO_DEMO_MODE) {
        echo json_encode(['error' => 'In der Demo-Version können keine Änderungen vorgenommen werden.']);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    
    if (isset($data['id'])) {
        $db = getDb();
        $db->prepare("UPDATE bookings SET status = 'confirmed' WHERE id = ?")->execute([$data['id']]);
        
        // Kundendaten für E-Mail laden
        $stmt = $db->prepare("SELECT b.customer_email, b.customer_name, b.start_time, b.cancel_token, e.name as event_name, e.duration_minutes FROM bookings b JOIN event_types e ON b.event_type_id = e.id WHERE b.id = ?");
        $stmt->execute([$data['id']]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($booking) {
            $settingStmt = $db->query("SELECT company_name, company_link_impressum, company_link_privacy, company_link_agb, company_address, company_logo FROM settings LIMIT 1");
            $sysSettings = $settingStmt->fetch(PDO::FETCH_ASSOC);
            $companyName = $sysSettings['company_name'] ?? 'Planago Booking';
            $impressumLink = $sysSettings['company_link_impressum'] ?? '';
            $privacyLink = $sysSettings['company_link_privacy'] ?? '';
            $agbLink = $sysSettings['company_link_agb'] ?? '';
            $companyAddress = $sysSettings['company_address'] ?? '';
            $companyLogo = $sysSettings['company_logo'] ?? '';

            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
            $basePath = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
            $baseUrl = rtrim($protocol . "://" . $_SERVER['HTTP_HOST'] . $basePath, '/');

            $footerName = htmlspecialchars($companyName);
            $footerAddress = nl2br(htmlspecialchars($companyAddress));
            $footerImpressum = htmlspecialchars($impressumLink ?: '#');
            $footerPrivacy = htmlspecialchars($privacyLink ?: '#');
            $footerAgb = htmlspecialchars($agbLink ?: '#');
            
            $agbHtml = !empty($agbLink) ? "<a href='$footerAgb' style='color: #86868b; text-decoration: underline; margin-right: 15px;'>AGB</a>" : "";
            $logoHtml = !empty($companyLogo) ? "<img src='" . $baseUrl . "/logo.php?v=" . md5($companyLogo) . "' alt='$footerName' style='max-height: 40px; margin-bottom: 10px;'><br>" : "";

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

            $cancelLink = $baseUrl . "/cancel.php?token=" . $booking['cancel_token'];

            $startTimeObj = new DateTime($booking['start_time']);
            $formattedDateStr = $startTimeObj->format('d.m.Y');
            $formattedTimeStr = $startTimeObj->format('H:i');
            
            $subject = "Termin bestätigt: " . $booking['event_name'] . " - " . $companyName;
            $body = "
            <div style='font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif; max-width: 550px; margin: 40px auto; padding: 30px; background-color: #ffffff; border: 1px solid #d2d2d7; border-radius: 18px; box-shadow: 0 4px 20px rgba(0,0,0,0.05);'>
                <div style='text-align: center; margin-bottom: 25px;'>
                    <h1 style='color: #1d1d1f; font-size: 24px; margin-bottom: 5px;'>Termin bestätigt!</h1>
                    <p style='color: #86868b; font-size: 16px;'>$companyName hat deinen Termin erfolgreich eingetragen.</p>
                </div>
                <div style='background-color: #f5f5f7; padding: 20px; border-radius: 14px; margin-bottom: 25px;'>
                    <table style='width: 100%; border-collapse: collapse;'>
                        <tr><td style='color: #86868b; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; padding-bottom: 5px;'>Was</td></tr>
                        <tr><td style='color: #1d1d1f; font-weight: 600; font-size: 17px; padding-bottom: 15px;'>{$booking['event_name']}</td></tr>
                        <tr><td style='color: #86868b; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; padding-bottom: 5px;'>Wann</td></tr>
                        <tr><td style='color: #1d1d1f; font-weight: 600; font-size: 17px;'>$formattedDateStr um $formattedTimeStr Uhr</td></tr>
                    </table>
                </div>
                <p style='color: #1d1d1f; font-size: 15px; line-height: 1.5; margin-bottom: 25px;'>Wir haben dir eine Kalender-Datei (.ics) angehängt, damit du den Termin mit einem Klick in dein Handy speichern kannst.</p>
                <div style='text-align: center; border-top: 1px solid #d2d2d7; padding-top: 25px;'>
                    <p style='color: #86868b; font-size: 13px; margin-bottom: 15px;'>Sollte etwas dazwischenkommen:</p>
                    <a href='$cancelLink' style='display: inline-block; background-color: #ff3b30; color: #ffffff; text-decoration: none; padding: 12px 25px; border-radius: 10px; font-weight: 600; font-size: 14px;'>Termin stornieren</a>
                </div>
                " . $emailFooter;
            $icsData = generateIcsData($booking['event_name'], $startTimeObj, $booking['duration_minutes']);
            sendSystemMail($booking['customer_email'], $subject, $body, $icsData);
        }
        
        echo json_encode(['message' => 'Termin bestätigt und E-Mail versendet.']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>