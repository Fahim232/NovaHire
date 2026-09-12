<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/google_auth.php';

if (!isset($_GET['code']) || empty($_GET['code'])) {
    header('Location: ' . BASE_URL . '/auth/login.php?error=no_code');
    exit;
}

if (isset($_GET['error'])) {
    header('Location: ' . BASE_URL . '/auth/login.php?error=' . urlencode($_GET['error']));
    exit;
}

$token_data = exchange_google_code($_GET['code']);

if (!isset($token_data['access_token'])) {
    error_log("Google token exchange failed: " . json_encode($token_data));
    header('Location: ' . BASE_URL . '/auth/login.php?error=token_exchange_failed');
    exit;
}

$google_user = get_google_user_info($token_data['access_token']);

if (!isset($google_user['id']) || !isset($google_user['email'])) {
    error_log("Failed to get Google user info: " . json_encode($google_user));
    header('Location: ' . BASE_URL . '/auth/login.php?error=user_info_failed');
    exit;
}

$result = handle_google_login($con, $google_user);

if ($result['success']) {
    $location = isset($result['new_user']) && $result['new_user']
        ? BASE_URL . '/seeker/profile.php?welcome=1'
        : BASE_URL . '/seeker/seeker_dashboard.php';
    header('Location: ' . $location);
} else {
    header('Location: ' . BASE_URL . '/auth/login.php?error=' . urlencode($result['message']));
}
exit;
?>
