<?php
session_start();

// Unset all of the session variables
$_SESSION = array();

session_destroy(); // Destroy the session
header("Location: /projectweb/LoginPage.html"); // Redirect to login page
exit;
?>
