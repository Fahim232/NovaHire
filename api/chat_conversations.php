<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id'])) {
    echo json_encode(['success' => false, 'message' => 'Login required']);
    exit();
}

include __DIR__ . '/../admin/dbcon.php';

$user_id = intval($_SESSION['id']);

// Get all companies the user has chatted with
$sql = "SELECT 
    CASE 
        WHEN sender_type = 'company' THEN sender_id
        ELSE receiver_id
    END as company_id,
    MAX(created_at) as last_time,
    (SELECT COUNT(*) FROM live_chats lc2 
     WHERE lc2.sender_type = 'company' 
     AND lc2.sender_id = CASE WHEN lc.sender_type = 'company' THEN lc.sender_id ELSE lc.receiver_id END
     AND lc2.receiver_type = 'user' AND lc2.receiver_id = $user_id 
     AND lc2.is_read = 0) as unread
FROM live_chats lc
WHERE (sender_type = 'user' AND sender_id = $user_id)
   OR (receiver_type = 'user' AND receiver_id = $user_id)
GROUP BY company_id
ORDER BY last_time DESC";

$result = mysqli_query($con, $sql);
$conversations = [];
while ($row = mysqli_fetch_assoc($result)) {
    $cid = intval($row['company_id']);
    if ($cid <= 0) continue;
    
    $comp_q = mysqli_query($con, "SELECT id, company_name, logo FROM companies WHERE id = $cid");
    $comp = mysqli_fetch_assoc($comp_q);
    if (!$comp) continue;
    
    $conversations[] = [
        'company_id' => $cid,
        'company_name' => $comp['company_name'],
        'logo' => $comp['logo'] ?? '',
        'last_time' => $row['last_time'],
        'unread' => intval($row['unread'])
    ];
}

echo json_encode(['success' => true, 'conversations' => $conversations]);
