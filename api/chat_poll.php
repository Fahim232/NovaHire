<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id'])) {
    echo json_encode(['success' => false, 'message' => 'Login required']);
    exit();
}

include __DIR__ . '/../admin/dbcon.php';

$user_id = intval($_SESSION['id']);
$since = isset($_GET['since']) ? intval($_GET['since']) : 0;
$with_type = isset($_GET['with_type']) ? mysqli_real_escape_string($con, $_GET['with_type']) : '';
$with_id = isset($_GET['with_id']) ? intval($_GET['with_id']) : 0;

if (empty($with_type) || $with_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid conversation']);
    exit();
}

// Mark messages from this person as read
$mark = "UPDATE live_chats SET is_read = 1 
         WHERE sender_type = '$with_type' AND sender_id = $with_id 
         AND receiver_type = 'user' AND receiver_id = $user_id AND is_read = 0";
mysqli_query($con, $mark);

// Fetch messages
$sql = "SELECT * FROM live_chats 
        WHERE ((sender_type = 'user' AND sender_id = $user_id AND receiver_type = '$with_type' AND receiver_id = $with_id)
            OR (sender_type = '$with_type' AND sender_id = $with_id AND receiver_type = 'user' AND receiver_id = $user_id))";
if ($since > 0) {
    $sql .= " AND id > $since";
}
$sql .= " ORDER BY created_at ASC LIMIT 100";

$result = mysqli_query($con, $sql);
$messages = [];
while ($row = mysqli_fetch_assoc($result)) {
    $messages[] = [
        'id' => intval($row['id']),
        'sender_type' => $row['sender_type'],
        'sender_id' => intval($row['sender_id']),
        'message' => $row['message'],
        'is_read' => intval($row['is_read']),
        'created_at' => $row['created_at']
    ];
}

echo json_encode(['success' => true, 'messages' => $messages]);
