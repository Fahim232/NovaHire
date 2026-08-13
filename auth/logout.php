<?php
// Core setup: session, DB, BASE_URL, helpers
require_once __DIR__ . '/../includes/bootstrap.php';
/**
 * User Logout & Session Destruction Handler
 * 
 * Clears all active session data and redirects the candidate to the login page.
 */

// Initialize session if not active
if (session_status() === PHP_SESSION_NONE) {

}

// Unset all session variables
session_unset();

// Destroy current session instance
session_destroy();

// Redirect to login portal
header('Location: login.php');
exit();
?>