<?php
/**
 * API Endpoint: Mark All Notifications Read
 * 
 * Marks all pending unread notifications as read for the logged-in job seeker.
 */

// Initialize session if not active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Return JSON header
header('Content-Type: application/json');

// Session authentication check
if (!isset($_SESSION['id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

// Include database & helper functions
include __DIR__ . '/../admin/dbcon.php';
include __DIR__ . '/../includes/functions.php';

$user_id = intval($_SESSION['id']);
$result  = mark_all_read($con, 'user', $user_id);

echo json_encode(['success' => $result]);
?>
