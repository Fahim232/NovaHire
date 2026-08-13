<?php
/**
 * API Endpoint: Fetch Unread Notification Count
 * 
 * Returns the unread notification count for the currently logged-in job seeker.
 */

// Initialize session if not active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Return JSON header
header('Content-Type: application/json');

// Session authentication check
if (!isset($_SESSION['id'])) {
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

// Include required modules
include __DIR__ . '/../admin/dbcon.php';
include __DIR__ . '/../includes/functions.php';

$user_id = intval($_SESSION['id']);
$count   = get_unread_count($con, 'user', $user_id);

echo json_encode(['count' => $count]);
?>
