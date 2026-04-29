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
        
        // Aktuelles Logo auslesen
        $stmtLogo = $db->query("SELECT company_logo FROM settings LIMIT 1");
        $currentSettings = $stmtLogo->fetch(PDO::FETCH_ASSOC);
        $company_logo = $currentSettings['company_logo'] ?? '';

        // Wenn ein neues Logo hochgeladen wurde (Base64)
        if (!empty($data['company_logo_base64'])) {
            if (!file_exists(__DIR__ . '/assets/uploads')) {
                mkdir(__DIR__ . '/assets/uploads', 0777, true);
            }
            $base64Parts = explode(';', $data['company_logo_base64']);
            if (count($base64Parts) == 2) {
                $type = $base64Parts[0];
                $imgData = base64_decode(explode(',', $base64Parts[1])[1]);
                
                $ext = 'jpg';
                if (strpos($type, 'png') !== false) $ext = 'png';
                elseif (strpos($type, 'gif') !== false) $ext = 'gif';
                elseif (strpos($type, 'svg') !== false) $ext = 'svg';
                elseif (strpos($type, 'webp') !== false) $ext = 'webp';
                
                $filename = 'assets/uploads/logo_' . time() . '.' . $ext;
                file_put_contents(__DIR__ . '/' . $filename, $imgData);
                $company_logo = $filename;
            }
        } elseif (isset($data['remove_logo']) && $data['remove_logo'] === true) {
            $company_logo = '';
        }

        if ($start && $end) {
            $sql = "UPDATE settings SET work_start_time = ?, work_end_time = ?, require_manual_confirmation = ?, smtp_from = ?, smtp_host = ?, smtp_port = ?, smtp_user = ?, smtp_pass = ?, company_name = ?, admin_email = ?, smtp_from_name = ?, company_phone = ?, company_address = ?, company_link_impressum = ?, company_link_privacy = ?, company_link_agb = ?, admin_username = ?, widget_accent_color = ?, company_logo = ?";
            $params = [$start, $end, $manual, $smtp_from, $smtp_host, $smtp_port, $smtp_user, $smtp_pass, $company_name, $admin_email, $smtp_from_name, $company_phone, $company_address, $company_link_impressum, $company_link_privacy, $company_link_agb, $admin_username, $widget_accent_color, $company_logo];
            
            if (!empty($admin_new_password)) {
                $sql .= ", admin_password_hash = ?";
                $params[] = password_hash($admin_new_password, PASSWORD_DEFAULT);
            }
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            echo json_encode(['message' => 'Arbeitszeiten erfolgreich gespeichert!']);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Fehlende Daten']);
        }
    } else {
        // GET: Einstellungen abrufen
        $stmt = $db->query("SELECT * FROM settings LIMIT 1");
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode($settings);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>