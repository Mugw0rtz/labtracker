<?php
/**
 * Logout Page
 * Destroys user session and redirects to login page
 */

require_once 'config/config.php';
require_once 'includes/session.php';
require_once 'includes/functions.php';

// Log the logout event if user was logged in
if (isLoggedIn()) {
    logEvent("User logged out", "info", "User ID: " . $_SESSION['user_id']);
}

// Destroy the session
logout();

// Set success message
$_SESSION['alert_message'] = 'You have been successfully logged out.';
$_SESSION['alert_type'] = 'success';

// Redirect to login page
header('Location: login.php');
exit;
?>
