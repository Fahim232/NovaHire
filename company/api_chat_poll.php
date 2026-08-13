<?php
/**
 * API Endpoint: Poll Live Chat Messages (Company Side)
 * 
 * Fetches recent chat messages between the logged-in company and candidate users.
 * Automatically marks incoming candidate messages as read. Uses prepared statements.
 */

// Initialize session if not active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Return JSON header
header('Content-Type: application/json');

// Validate company session authentication
if (!isset($_SESSION['company_id'])) {
    echo json_encode(['success' => false, 'message' => 'Login required']);
    exit();
}

// Include database connection
include __DIR__ . '/../admin/dbcon.php';

$company_id = intval($_SESSION['company_id']);
$since      = isset($_GET['since']) ? intval($_GET['since']) : 0;
$with_type  = isset($_GET['with_type']) ? trim($_GET['with_type']) : '';
$with_id    = isset($_GET['with_id']) ? intval($_GET['with_id']) : 0;

if (empty($with_type) || $with_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid conversation target']);
    exit();
}

// 1. Mark incoming unread messages as read
$mark_stmt = mysqli_prepare($con, "UPDATE live_chats SET is_read = 1 WHERE sender_type = ? AND sender_id = ? AND receiver_type = 'company' AND receiver_id = ? AND is_read = 0");
mysqli_stmt_bind_param($mark_stmt, "sii", $with_type, $with_id, $company_id);
mysqli_stmt_execute($mark_stmt);
mysqli_stmt_close($mark_stmt);

// 2. Query messages via prepared statement
if ($since > 0) {
    $sql_stmt = mysqli_prepare($con, "SELECT id, sender_type, sender_id, message, is_read, created_at FROM live_chats WHERE ((sender_type = 'company' AND sender_id = ? AND receiver_type = ? AND receiver_id = ?) OR (sender_type = ? AND sender_id = ? AND receiver_type = 'company' AND receiver_id = ?)) AND id > ? ORDER BY created_at ASC LIMIT 100");
    mysqli_stmt_bind_param($sql_stmt, "isisiii", $company_id, $with_type, $with_id, $with_type, $with_id, $company_id, $since);
} else {
    $sql_stmt = mysqli_prepare($con, "SELECT id, sender_type, sender_id, message, is_read, created_at FROM live_chats WHERE ((sender_type = 'company' AND sender_id = ? AND receiver_type = ? AND receiver_id = ?) OR (sender_type = ? AND sender_id = ? AND receiver_type = 'company' AND receiver_id = ?)) ORDER BY created_at ASC LIMIT 100");
    mysqli_stmt_bind_param($sql_stmt, "isisii", $company_id, $with_type, $with_id, $with_type, $with_id, $company_id);
}

mysqli_stmt_execute($sql_stmt);
$result = mysqli_stmt_get_result($sql_stmt);

// Build structured message array
$messages = [];
while ($row = mysqli_fetch_assoc($result)) {
    $messages[] = [
        'id'          => intval($row['id']),
        'sender_type' => $row['sender_type'],
        'sender_id'   => intval($row['sender_id']),
        'message'     => $row['message'],
        'is_read'     => intval($row['is_read']),
        'created_at'  => $row['created_at']
    ];
}
mysqli_stmt_close($sql_stmt);

// Output JSON payload
echo json_encode(['success' => true, 'messages' => $messages]);
?>
