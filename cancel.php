<?php
require_once 'config.php';
$db = getDb();

$token = $_GET['token'] ?? '';
$message = '';
$success = false;

if (empty($token)) {
    $message = "Ungültiger Link.";
} else {
    // Prüfen, ob der Token existiert
    $stmt = $db->prepare("SELECT b.id, b.start_time, e.name as event_name, e.cancel_limit_hours FROM bookings b JOIN event_types e ON b.event_type_id = e.id WHERE b.cancel_token = ?");
    $stmt->execute([$token]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        $message = "Dieser Termin existiert nicht mehr oder wurde bereits abgesagt.";
    } else {
        $bookingTime = new DateTime($booking['start_time']);
        $date = $bookingTime->format('d.m.Y \u\m H:i') . ' Uhr';
        
        $now = new DateTime();
        $limitHours = $booking['cancel_limit_hours'] ?? 24;
        $deadline = (clone $bookingTime)->modify("-{$limitHours} hours");
        
        if ($now > $deadline) {
            $message = "Eine Stornierung ist leider nur bis zu {$limitHours} Stunden vor dem Termin möglich. Bitte kontaktiere uns direkt.";
            $booking = null; // Blendet den "Ja, absagen" Button aus
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Kunde hat die Stornierung bestätigt -> Buchung löschen
            $db->prepare("DELETE FROM bookings WHERE id = ?")->execute([$booking['id']]);
            $message = "Dein Termin wurde erfolgreich storniert.";
            $success = true;
            
            // Optional: Admin über Storno informieren
            $setStmt = $db->query("SELECT admin_email FROM settings LIMIT 1");
            $sys = $setStmt->fetch(PDO::FETCH_ASSOC);
            if (!empty($sys['admin_email'])) {
                sendSystemMail($sys['admin_email'], "Termin storniert vom Kunden", "Ein Kunde hat seinen Termin ({$booking['event_name']} am $date) soeben über den Link in der E-Mail abgesagt.");
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Termin absagen - Planago</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="container" style="text-align: center; margin-top: 50px;">
        <?php if ($success): ?>
            <h2 style="color: var(--success);">Erfolgreich abgesagt</h2>
            <p><?= htmlspecialchars($message) ?></p>
        <?php elseif (!empty($message) && !$booking): ?>
            <h2 style="color: var(--danger);">Fehler</h2>
            <p><?= htmlspecialchars($message) ?></p>
        <?php else: ?>
            <h2>Termin absagen?</h2>
            <p>Möchtest du deinen Termin für <strong><?= htmlspecialchars($booking['event_name']) ?></strong> am <strong><?= $date ?></strong> wirklich absagen?</p>
            <p style="color: var(--danger); font-size: 13px; margin-bottom: 25px;">Dieser Schritt kann nicht rückgängig gemacht werden. Der Termin wird wieder für andere freigegeben.</p>
            
            <form method="POST">
                <button type="submit" style="background: var(--danger); box-shadow: 0 4px 15px rgba(255, 59, 48, 0.3);">Ja, Termin verbindlich absagen</button>
                <a href="javascript:window.close();" style="display: block; margin-top: 20px; color: var(--text-muted); text-decoration: none; font-size: 14px;">Abbrechen und Fenster schließen</a>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>