<?php
// api_reschedule_invite.php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401); exit;
}
require_once 'config.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$bookingId = $data['id'] ?? null;

if ($bookingId) {
    $db = getDb();
    $stmt = $db->prepare("SELECT b.*, e.name as event_name FROM bookings b JOIN event_types e ON b.event_type_id = e.id WHERE b.id = ?");
    $stmt->execute([$bookingId]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($booking) {
        // NEU: Status der Buchung auf "Verschiebung angefragt" setzen
        $db->prepare("UPDATE bookings SET status = 'reschedule_requested' WHERE id = ?")->execute([$bookingId]);

        $settingStmt = $db->query("SELECT company_name, company_link_impressum, company_link_privacy FROM settings LIMIT 1");
        $sys = $settingStmt->fetch(PDO::FETCH_ASSOC);
        $companyName = $sys['company_name'] ?? 'Planago Booking';
        $impressumLink = $sys['company_link_impressum'] ?? '';
        $privacyLink = $sys['company_link_privacy'] ?? '';

        $legalLinks = [];
        if (!empty($impressumLink)) $legalLinks[] = "<a href='$impressumLink' style='color: #86868b; text-decoration: none;'>Impressum</a>";
        if (!empty($privacyLink)) $legalLinks[] = "<a href='$privacyLink' style='color: #86868b; text-decoration: none;'>Datenschutz</a>";
        $legalHtml = !empty($legalLinks) ? "<p style='color: #86868b; font-size: 12px; margin-bottom: 10px;'>" . implode(" &nbsp;|&nbsp; ", $legalLinks) . "</p>" : "";
        $footerHtml = "<div style='margin-top: 30px; text-align: center; border-top: 1px solid #d2d2d7; padding-top: 20px;'>" . $legalHtml . "<p style='color: #d2d2d7; font-size: 11px; margin: 0;'>Smarte Buchungen mit <strong style='color:#d2d2d7;'>Planago</strong></p></div></div>";

        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
        $baseUrl = $protocol . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']);
        // Den Token übergeben wir, damit der Kunde autorisiert auf die Website kommt
        $rescheduleUrl = $baseUrl . "/index.php?reschedule=" . $booking['id'] . "&token=" . $booking['cancel_token'];

        $subject = "Termin verschieben: " . $booking['event_name'] . " - " . $companyName;
        
        $slotTime = new DateTime($booking['start_time']);
        $formattedDate = $slotTime->format('d.m.Y');
        
        $body = "
        <div style='font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif; max-width: 550px; margin: auto; padding: 30px; background-color: #ffffff; border: 1px solid #d2d2d7; border-radius: 18px; box-shadow: 0 4px 20px rgba(0,0,0,0.05);'>
            <h2 style='color: #1d1d1f; text-align:center;'>Termin verschieben</h2>
            <p style='color: #1d1d1f;'>Hallo " . htmlspecialchars($booking['customer_name']) . ",</p>
            <p style='color: #1d1d1f;'>leider müssen wir den Termin für <strong>" . htmlspecialchars($booking['event_name']) . "</strong> verschieben.</p>
            <p style='color: #1d1d1f;'>Keine Sorge, du kannst dir ganz einfach einen neuen passenden Zeitpunkt aussuchen:</p>
            <div style='text-align: center; margin: 30px 0;'>
                <a href='$rescheduleUrl' style='display: inline-block; background-color: #34c759; color: white; padding: 14px 25px; text-decoration: none; border-radius: 10px; font-weight: 600;'>Neuen Termin wählen</a>
            </div>
            <p style='color: #86868b; font-size: 13px; text-align:center;'>Wir bitten um Entschuldigung für die Umstände. Dein ursprünglicher Termin am $formattedDate verfällt hiermit.</p>
            " . $footerHtml;

        sendSystemMail($booking['customer_email'], $subject, $body);
        echo json_encode(['success' => true, 'message' => 'Einladung zum Verschieben wurde gesendet!']);
    }
}
?>