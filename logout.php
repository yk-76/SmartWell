<?php
require_once 'auth_helper.php';
start_secure_session();

// Remove the remembered username cookie
setcookie('remembered_username', '', time() - 3600, '/');   // <--- Add this line here

// Unset all session variables
$_SESSION = array();

// If it's desired to kill the session, also delete the session cookie.
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 42000, '/');
}

// Finally, destroy the session
session_destroy();

// Redirect to homepage
header("Location: index.php");
exit;
?>
