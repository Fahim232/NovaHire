<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['company_id'])) {
    echo json_encode(['success' => false, 'message' => 'Login required']);
    exit();
}

include __DIR__ . '/../admin/dbcon.php';

$company_id = intval($_SESSION['company_id']);

$sql = "SELECT 
    CASE 
        WHEN sender_type = 'user' THEN sender_id
        ELSE receiver_id
    END as user_id,
    MAX(created_at) as last_time,
    (SELECT COUNT(*) FROM live_chats lc2 
     WHERE lc2.sender_type = 'user' 
     AND lc2.sender_id = CASE WHEN lc.sender_type = 'user' THEN lc.sender_id ELSE lc.receiver_id END
     AND lc2.receiver_type = 'company' AND lc2.receiver_id = $company_id 
     AND lc2.is_read = 0) as unread
FROM live_chats lc
WHERE (sender_type = 'company' AND sender_id = $company_id)
   OR (receiver_type = 'company' AND receiver_id = $company_id)
GROUP BY user_id
ORDER BY last_time DESC";

$result = mysqli_query($con, $sql);
$conversations = [];
while ($row = mysqli_fetch_assoc($result)) {
    $uid = intval($row['user_id']);
    if ($uid <= 0) continue;
    
    $user_q = mysqli_query($con, "SELECT id, username, profile FROM user_info WHERE id = $uid");
    $user = mysqli_fetch_assoc($user_q);
    if (!$user) continue;
    
    $conversations[] = [
        'user_id' => $uid,
        'username' => $user['username'],
        'profile' => $user['profile'] ?? '',
        'last_time' => $row['last_time'],
        'unread' => intval($row['unread'])
    ];
}

echo json_encode(['success' => true, 'conversations' => $conversations]);
