<?php
/**
 * Logout - Secure session destruction
 */
session_start();

// Unset all session variables
$_SESSION = array();

// Delete session cookie
if(isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Delete remember token cookie
if(isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
}

// Destroy session
session_destroy();

// Redirect to login
header("Location: login.php?logout=success");
exit();
?>