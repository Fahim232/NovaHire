<?php
/**
 * API Endpoint: Send Live Chat Message (Company Side)
 * 
 * Handles incoming POST requests from authenticated employers to send live chat messages
 * to job seeker candidates. Uses prepared statements for efficiency and security.
 */

// Initialize PHP session if not already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set JSON output content header
header('Content-Type: application/json');

// Validate company session authentication
if (!isset($_SESSION['company_id'])) {
    echo json_encode(['success' => false, 'message' => 'Login required']);
    exit();
}

// Include database connection module
include __DIR__ . '/../admin/dbcon.php';
include __DIR__ . '/../includes/functions.php';

// Verify POST request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Extract and sanitize input parameters
$company_id    = intval($_SESSION['company_id']);
$receiver_type = trim($_POST['receiver_type'] ?? '');
$receiver_id   = intval($_POST['receiver_id'] ?? 0);
$message       = trim($_POST['message'] ?? '');

// Validate required fields
if (empty($receiver_type) || $receiver_id <= 0 || empty($message)) {
    echo json_encode(['success' => false, 'message' => 'Invalid message data']);
    exit();
}

// Insert chat message using prepared statement
$stmt = mysqli_prepare($con, "INSERT INTO live_chats (sender_type, sender_id, receiver_type, receiver_id, message) VALUES ('company', ?, ?, ?, ?)");
mysqli_stmt_bind_param($stmt, "isis", $company_id, $receiver_type, $receiver_id, $message);
$result = mysqli_stmt_execute($stmt);
$msg_id = mysqli_insert_id($con);
mysqli_stmt_close($stmt);

// Render response payload
if ($result) {
    // Notify the recipient seeker so they don't miss the live chat message
    $sender_name = trim($_SESSION['company_name'] ?? '');
    if ($sender_name === '') {
        $cn = mysqli_prepare($con, "SELECT company_name FROM companies WHERE id = ?");
        mysqli_stmt_bind_param($cn, "i", $company_id);
        mysqli_stmt_execute($cn);
        $cr = mysqli_stmt_get_result($cn);
        $crow = mysqli_fetch_assoc($cr);
        mysqli_stmt_close($cn);
        $sender_name = $crow['company_name'] ?? 'Company';
    }
    $excerpt = mb_substr($message, 0, 150) . (mb_strlen($message) > 150 ? '...' : '');
    create_notification($con, 'user', $receiver_id, 'company', $company_id,
        'New live chat message from ' . $sender_name, $excerpt, 'message', 'live_chats', $msg_id);

    echo json_encode(['success' => true, 'id' => $msg_id]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to send message']);
}
?>
