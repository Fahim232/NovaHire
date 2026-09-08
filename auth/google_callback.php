<?php
/**
 * NovaHire — Google OAuth Callback
 * Handles Google login callback
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/google_auth.php';

// Check if code is provided
if (!isset($_GET['code']) || empty($_GET['code'])) {
    header('Location: ' . BASE_URL . '/auth/login.php?error=no_code');
    exit;
}

// Check for errors
if (isset($_GET['error'])) {
    $error = $_GET['error'];
    header('Location: ' . BASE_URL . '/auth/login.php?error=' . urlencode($error));
    exit;
}

$code = $_GET['code'];

// Exchange code for tokens
$token_data = exchange_google_code($code);

if (!isset($token_data['access_token'])) {
    error_log("Google token exchange failed: " . json_encode($token_data));
    header('Location: ' . BASE_URL . '/auth/login.php?error=token_exchange_failed');
    exit;
}

// Get user info from Google
$google_user = get_google_user_info($token_data['access_token']);

if (!isset($google_user['id']) || !isset($google_user['email'])) {
    error_log("Failed to get Google user info: " . json_encode($google_user));
    header('Location: ' . BASE_URL . '/auth/login.php?error=user_info_failed');
    exit;
}

// Handle login/register
$result = handle_google_login($con, $google_user);

if ($result['success']) {
    // Check if user needs to complete profile
    if (isset($result['new_user']) && $result['new_user']) {
        header('Location: ' . BASE_URL . '/seeker/profile.php?welcome=1');
    } else {
        header('Location: ' . BASE_URL . '/seeker/seeker_dashboard.php');
    }
    exit;
} else {
    header('Location: ' . BASE_URL . '/auth/login.php?error=' . urlencode($result['message']));
    exit;
}
?>
