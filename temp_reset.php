<?php
require_once 'config.php';
$db = getDb();

// Spalte ergänzen, damit der Login-Fehler verschwindet
try { $db->exec("ALTER TABLE settings ADD COLUMN force_password_change INTEGER DEFAULT 0"); } catch (Exception $e) {}

$newPassword = 'TestAdmin!2024';
$hash = password_hash($newPassword, PASSWORD_DEFAULT);

$stmt = $db->prepare("UPDATE settings SET admin_password_hash = ?, force_password_change = 0");
$stmt->execute([$hash]);

echo "Passwort wurde erfolgreich auf <strong>TestAdmin!2024</strong> gesetzt. Bitte diese Datei nach dem Testen wieder löschen!";