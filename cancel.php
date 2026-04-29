<?php
require_once 'config.php';

$token = $_GET['token'] ?? '';
$message = '';
$booking = null;

$db = getDb();

// Settings für Links auslesen
$sysStmt = $db->query("SELECT company_link_impressum, company_link_privacy FROM settings LIMIT 1");
$sysSettings = $sysStmt->fetch(PDO::FETCH_ASSOC);
$impressumLink = $sysSettings['company_link_impressum'] ?? '';
$privacyLink = $sysSettings['company_link_privacy'] ?? '';

if ($token) {
    // Buchung anhand des Tokens suchen
    $stmt = $db->prepare("SELECT b.*, e.name as event_title FROM bookings b JOIN event_types e ON b.event_type_id = e.id WHERE b.cancel_token = ?");
    $stmt->execute([$token]);
    $booking = $stmt->fetch();

    if (!$booking) {
        $message = "<div class='alert alert-error'>Ungültiger oder bereits verwendeter Link.</div>";
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Stornierung durchführen (Status auf 'cancelled' setzen oder löschen)
        $delStmt = $db->prepare("DELETE FROM bookings WHERE id = ?");
        $delStmt->execute([$booking['id']]);
        
        $message = "<div class='alert alert-success'>Dein Termin wurde erfolgreich storniert.</div>";
        $booking = null; // Buchung ausblenden, da storniert
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
        /* Spezifische Anpassungen für die Storno-Seite */
        body {
            background-color: #f5f5f7; /* Leichtes Apple-Grau für die ganze Seite */
        }
        .cancel-container {
            text-align: center;
        }
        .event-details {
            background: var(--input-bg);
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

<!-- NEU: Impressum & Datenschutz Links -->
<?php if (!empty($impressumLink) || !empty($privacyLink)): ?>
    <div style="text-align: center; margin-top: 20px; font-size: 12px;">
        <?php if (!empty($impressumLink)): ?>
            <a href="<?= htmlspecialchars($impressumLink) ?>" target="_blank" style="color: var(--text-muted); text-decoration: none; margin: 0 10px; transition: opacity 0.2s;" onmouseover="this.style.opacity='0.7'" onmouseout="this.style.opacity='1'">Impressum</a>
        <?php endif; ?>
        <?php if (!empty($privacyLink)): ?>
            <a href="<?= htmlspecialchars($privacyLink) ?>" target="_blank" style="color: var(--text-muted); text-decoration: none; margin: 0 10px; transition: opacity 0.2s;" onmouseover="this.style.opacity='0.7'" onmouseout="this.style.opacity='1'">Datenschutz</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

</body>
</html>