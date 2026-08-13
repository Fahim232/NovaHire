<?php
/**
 * API Endpoint: Fetch Company Chat Conversations List
 * 
 * Retrieves candidate contacts who have messaged the authenticated employer/company,
 * including user profile metadata, unread counts, and last active timestamp.
 */

// Initialize session if not active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Return JSON output header
header('Content-Type: application/json');

// Validate company authentication
if (!isset($_SESSION['company_id'])) {
    echo json_encode(['success' => false, 'message' => 'Login required']);
    exit();
}

// Include database connection
include __DIR__ . '/../admin/dbcon.php';

$company_id = intval($_SESSION['company_id']);

// Prepared SQL to aggregate user conversation partners and count unread messages
$sql = "SELECT 
    CASE 
        WHEN sender_type = 'user' THEN sender_id
        ELSE receiver_id
    END as user_id,
    MAX(created_at) as last_time,
    (SELECT COUNT(*) FROM live_chats lc2 
     WHERE lc2.sender_type = 'user' 
     AND lc2.sender_id = CASE WHEN lc.sender_type = 'user' THEN lc.sender_id ELSE lc.receiver_id END
     AND lc2.receiver_type = 'company' AND lc2.receiver_id = ? 
     AND lc2.is_read = 0) as unread,
    (SELECT lc3.message FROM live_chats lc3 
     WHERE ((lc3.sender_type = 'user' AND lc3.sender_id = CASE WHEN lc.sender_type = 'user' THEN lc.sender_id ELSE lc.receiver_id END AND lc3.receiver_type = 'company' AND lc3.receiver_id = ?)
         OR (lc3.sender_type = 'company' AND lc3.sender_id = ? AND lc3.receiver_type = 'user' AND lc3.receiver_id = CASE WHEN lc.sender_type = 'user' THEN lc.sender_id ELSE lc.receiver_id END))
     ORDER BY lc3.id DESC LIMIT 1) as last_message,
    (SELECT lc4.sender_type FROM live_chats lc4 
     WHERE ((lc4.sender_type = 'user' AND lc4.sender_id = CASE WHEN lc.sender_type = 'user' THEN lc.sender_id ELSE lc.receiver_id END AND lc4.receiver_type = 'company' AND lc4.receiver_id = ?)
         OR (lc4.sender_type = 'company' AND lc4.sender_id = ? AND lc4.receiver_type = 'user' AND lc4.receiver_id = CASE WHEN lc.sender_type = 'user' THEN lc.sender_id ELSE lc.receiver_id END))
     ORDER BY lc4.id DESC LIMIT 1) as last_sender
FROM live_chats lc
WHERE (sender_type = 'company' AND sender_id = ?)
   OR (receiver_type = 'company' AND receiver_id = ?)
GROUP BY user_id
ORDER BY last_time DESC";

$stmt = mysqli_prepare($con, $sql);
mysqli_stmt_bind_param($stmt, "iiiiiii", $company_id, $company_id, $company_id, $company_id, $company_id, $company_id, $company_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Prepared statement to fetch candidate user details
$user_stmt = mysqli_prepare($con, "SELECT id, username, profile FROM user_info WHERE id = ?");

$conversations = [];
while ($row = mysqli_fetch_assoc($result)) {
    $uid = intval($row['user_id']);
    if ($uid <= 0) continue;
    
    mysqli_stmt_bind_param($user_stmt, "i", $uid);
    mysqli_stmt_execute($user_stmt);
    $user_res = mysqli_stmt_get_result($user_stmt);
    $user = mysqli_fetch_assoc($user_res);
    if (!$user) continue;
    
    $conversations[] = [
        'user_id'       => $uid,
        'username'      => $user['username'],
        'profile'       => $user['profile'] ?? '',
        'last_time'     => $row['last_time'],
        'unread'        => intval($row['unread']),
        'last_message'  => $row['last_message'] ?? '',
        'last_sender'   => $row['last_sender'] ?? ''
    ];
}
mysqli_stmt_close($user_stmt);
mysqli_stmt_close($stmt);

// Render response payload
echo json_encode(['success' => true, 'conversations' => $conversations]);
?>
