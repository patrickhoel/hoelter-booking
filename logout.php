<?php
// logout.php
session_start();
session_unset();
session_destroy(); // Vernichtet den Login-Cookie
header('Location: login.php');
exit;
?>