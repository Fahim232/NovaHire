<?php
/**
 * API Endpoint: Send Live Chat Message (User Side)
 * 
 * Handles incoming POST requests from authenticated job seekers to send live chat messages
 * to employers/companies. Utilizes prepared statements for security and efficiency.
 */

// Initialize PHP session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Enforce JSON response header
header('Content-Type: application/json');

// Validate user session authentication
if (!isset($_SESSION['id'])) {
    echo json_encode(['success' => false, 'message' => 'Login required']);
    exit();
}

// Include database connection and helper functions
include __DIR__ . '/../admin/dbcon.php';
include __DIR__ . '/../includes/functions.php';

// Ensure request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Extract and sanitize input parameters
$user_id       = intval($_SESSION['id']);
$receiver_type = trim($_POST['receiver_type'] ?? '');
$receiver_id   = intval($_POST['receiver_id'] ?? 0);
$message       = trim($_POST['message'] ?? '');

// Validate required fields
if (empty($receiver_type) || $receiver_id <= 0 || empty($message)) {
    echo json_encode(['success' => false, 'message' => 'Invalid message data']);
    exit();
}

// Prepare SQL statement to insert new chat message
$stmt = mysqli_prepare($con, "INSERT INTO live_chats (sender_type, sender_id, receiver_type, receiver_id, message) VALUES ('user', ?, ?, ?, ?)");
mysqli_stmt_bind_param($stmt, "isis", $user_id, $receiver_type, $receiver_id, $message);

// Execute prepared insert statement
$result = mysqli_stmt_execute($stmt);
$msg_id = mysqli_insert_id($con);
mysqli_stmt_close($stmt);

// Return JSON response payload
if ($result) {
    // Notify the recipient company so they don't miss the live chat message
    $sender_name = trim($_SESSION['username'] ?? '');
    if ($sender_name === '') {
        $un = mysqli_prepare($con, "SELECT username FROM user_info WHERE id = ?");
        mysqli_stmt_bind_param($un, "i", $user_id);
        mysqli_stmt_execute($un);
        $ur = mysqli_stmt_get_result($un);
        $urow = mysqli_fetch_assoc($ur);
        mysqli_stmt_close($un);
        $sender_name = $urow['username'] ?? 'Job Seeker';
    }
    $excerpt = mb_substr($message, 0, 150) . (mb_strlen($message) > 150 ? '...' : '');
    create_notification($con, 'company', $receiver_id, 'user', $user_id,
        'New live chat message from ' . $sender_name, $excerpt, 'message', 'live_chats', $msg_id);

    echo json_encode(['success' => true, 'id' => $msg_id]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to send message']);
}
?>
