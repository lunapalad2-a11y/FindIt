
<?php
session_start();

// Clear all session data
$_SESSION = [];

// Destroy the current session
session_destroy();

// Return user to login page
header("Location: login.php");
exit;
?>