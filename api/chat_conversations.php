<?php
/**
 * API Endpoint: Fetch Chat Conversations List (User Side)
 * 
 * Retrieves all company contacts the current user has chated with, along with
 * company metadata, unread message counts, and timestamps.
 */

// Initialize session if not active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Return JSON content
header('Content-Type: application/json');

// Session authentication check
if (!isset($_SESSION['id'])) {
    echo json_encode(['success' => false, 'message' => 'Login required']);
    exit();
}

// Include database connection handle
include __DIR__ . '/../admin/dbcon.php';

$user_id = intval($_SESSION['id']);

// Prepared statement to fetch distinct company partners and unread message stats
$sql = "SELECT 
    CASE 
        WHEN sender_type = 'company' THEN sender_id
        ELSE receiver_id
    END as company_id,
    MAX(created_at) as last_time,
    (SELECT COUNT(*) FROM live_chats lc2 
     WHERE lc2.sender_type = 'company' 
     AND lc2.sender_id = CASE WHEN lc.sender_type = 'company' THEN lc.sender_id ELSE lc.receiver_id END
     AND lc2.receiver_type = 'user' AND lc2.receiver_id = ? 
     AND lc2.is_read = 0) as unread
FROM live_chats lc
WHERE (sender_type = 'user' AND sender_id = ?)
   OR (receiver_type = 'user' AND receiver_id = ?)
GROUP BY company_id
ORDER BY last_time DESC";

$stmt = mysqli_prepare($con, $sql);
mysqli_stmt_bind_param($stmt, "iii", $user_id, $user_id, $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Prepare statement for fetching company details efficiently
$comp_stmt = mysqli_prepare($con, "SELECT id, company_name, logo FROM companies WHERE id = ?");

$conversations = [];
while ($row = mysqli_fetch_assoc($result)) {
    $cid = intval($row['company_id']);
    if ($cid <= 0) continue;
    
    // Fetch company name & logo
    mysqli_stmt_bind_param($comp_stmt, "i", $cid);
    mysqli_stmt_execute($comp_stmt);
    $comp_res = mysqli_stmt_get_result($comp_stmt);
    $comp = mysqli_fetch_assoc($comp_res);
    if (!$comp) continue;
    
    $conversations[] = [
        'company_id'   => $cid,
        'company_name' => $comp['company_name'],
        'logo'         => $comp['logo'] ?? '',
        'last_time'    => $row['last_time'],
        'unread'       => intval($row['unread'])
    ];
}
mysqli_stmt_close($comp_stmt);
mysqli_stmt_close($stmt);

// Render structured response
echo json_encode(['success' => true, 'conversations' => $conversations]);
?>
