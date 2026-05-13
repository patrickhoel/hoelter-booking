<?php
// logout.php
session_start();
session_unset();
session_destroy(); // Vernichtet den Login-Cookie

// Vernichtet den Session-Cookie auch aktiv im Browser des Nutzers
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

header('Location: login.php');
exit;
?>