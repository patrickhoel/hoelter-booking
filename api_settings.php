<?php
// api_settings.php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401); // Unauthorized
    echo json_encode(['error' => 'Nicht authorisiert']);
    exit;
}

require_once 'config.php';
header('Content-Type: application/json');

try {
    $db = getDb();
    
    // Fallback-Migrationen für Updates: Sicherstellen, dass die neuen Spalten existieren
    try { $db->exec("ALTER TABLE settings ADD COLUMN zapier_webhook_url TEXT DEFAULT ''"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE settings ADD COLUMN calendar_sync_token TEXT DEFAULT NULL"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE settings ADD COLUMN theme_mode TEXT DEFAULT 'auto'"); } catch (Exception $e) {}

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        
        $start = $data['work_start_time'] ?? null;
        $end = $data['work_end_time'] ?? null;
        $manual = isset($data['require_manual_confirmation']) ? (int)$data['require_manual_confirmation'] : 0;
        $smtp_from = $data['smtp_from'] ?? '';
        $smtp_host = $data['smtp_host'] ?? '';
        $smtp_port = $data['smtp_port'] ?? '587';
        $smtp_user = $data['smtp_user'] ?? '';
        $smtp_pass = $data['smtp_pass'] ?? '';
        $company_name = $data['company_name'] ?? 'Planago Booking';
        $admin_email = $data['admin_email'] ?? '';
        $smtp_from_name = $data['smtp_from_name'] ?? '';
        $company_phone = $data['company_phone'] ?? '';
        $company_address = $data['company_address'] ?? '';
        $company_link_impressum = $data['company_link_impressum'] ?? '';
        $company_link_privacy = $data['company_link_privacy'] ?? '';
        $company_link_agb = $data['company_link_agb'] ?? '';
        $admin_username = $data['admin_username'] ?? 'admin';
        $admin_new_password = $data['admin_new_password'] ?? '';
        $widget_accent_color = $data['widget_accent_color'] ?? '#34c759';
        $google_review_link = $data['google_review_link'] ?? '';
        $enable_review_email = isset($data['enable_review_email']) ? (int)$data['enable_review_email'] : 0;
        $zapier_webhook_url = $data['zapier_webhook_url'] ?? '';
        $theme_mode = $data['theme_mode'] ?? 'auto';
        
        // Aktuelles Logo auslesen
        $stmtLogo = $db->query("SELECT company_logo FROM settings LIMIT 1");
        $currentSettings = $stmtLogo->fetch(PDO::FETCH_ASSOC);
        $company_logo = $currentSettings['company_logo'] ?? '';

        // Wenn ein neues Logo hochgeladen wurde (Base64)
        if (!empty($data['company_logo_base64'])) {
            // Wir speichern den Base64-String nun DIREKT und absolut sicher in der Datenbank!
            $company_logo = $data['company_logo_base64'];
        } elseif (isset($data['remove_logo']) && $data['remove_logo'] === true) {
            $company_logo = '';
        }

        if ($start && $end) {
            $sql = "UPDATE settings SET work_start_time = ?, work_end_time = ?, require_manual_confirmation = ?, smtp_from = ?, smtp_host = ?, smtp_port = ?, smtp_user = ?, smtp_pass = ?, company_name = ?, admin_email = ?, smtp_from_name = ?, company_phone = ?, company_address = ?, company_link_impressum = ?, company_link_privacy = ?, company_link_agb = ?, admin_username = ?, widget_accent_color = ?, company_logo = ?, google_review_link = ?, enable_review_email = ?, zapier_webhook_url = ?, theme_mode = ?";
            $params = [$start, $end, $manual, $smtp_from, $smtp_host, $smtp_port, $smtp_user, $smtp_pass, $company_name, $admin_email, $smtp_from_name, $company_phone, $company_address, $company_link_impressum, $company_link_privacy, $company_link_agb, $admin_username, $widget_accent_color, $company_logo, $google_review_link, $enable_review_email, $zapier_webhook_url, $theme_mode];
            
            if (!empty($admin_new_password)) {
                $sql .= ", admin_password_hash = ?";
                $params[] = password_hash($admin_new_password, PASSWORD_DEFAULT);
            }
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            echo json_encode(['message' => 'Einstellungen erfolgreich gespeichert!']);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Fehlende Daten']);
        }
    } else {
        // GET: Einstellungen abrufen
        $stmt = $db->query("SELECT * FROM settings LIMIT 1");
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Den Base64-String nicht jedes Mal ans Admin-Panel senden (spart enorm Ladezeit & Bandbreite)
        if (isset($settings['company_logo'])) {
            $settings['company_logo'] = !empty($settings['company_logo']) ? true : false;
        }

        // Kalender-Token generieren, falls dieser noch fehlt (z.B. nach einem Update)
        if (empty($settings['calendar_sync_token'])) {
            $token = bin2hex(random_bytes(16));
            $db->prepare("UPDATE settings SET calendar_sync_token = ?")->execute([$token]);
            $settings['calendar_sync_token'] = $token;
        }
        echo json_encode($settings);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>