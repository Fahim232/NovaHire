<?php
/**
 * API Endpoint: Live Chat Alerts (Unified — Seeker & Company)
 *
 * Returns unread live-chat notifications for the logged-in party (job seeker
 * OR company) so the page can alert the user in real time. Each returned alert
 * is marked as "delivered" (is_read = 1) so it only fires once.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

$is_company = isset($_SESSION['company_id']);
$is_user    = !$is_company && isset($_SESSION['id']);

if (!$is_company && !$is_user) {
    echo json_encode(['success' => false, 'message' => 'Login required']);
    exit();
}

include __DIR__ . '/../admin/dbcon.php';

$recipient_type = $is_company ? 'company' : 'user';
$recipient_id   = intval($is_company ? $_SESSION['company_id'] : $_SESSION['id']);

// Unread live-chat notifications (created by chat_send endpoints), oldest first
$sql = "SELECT id, sender_type, sender_id, title, message, created_at
        FROM notifications
        WHERE recipient_type = ?
          AND recipient_id = ?
          AND notification_type = 'message'
          AND related_type = 'live_chats'
          AND is_read = 0
        ORDER BY created_at ASC
        LIMIT 30";
$stmt = mysqli_prepare($con, $sql);
mysqli_stmt_bind_param($stmt, "si", $recipient_type, $recipient_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$alerts = [];
$notif_ids = [];

while ($row = mysqli_fetch_assoc($result)) {
    $notif_ids[] = intval($row['id']);

    $sender_name = '';
    $sender_logo = '';
    $sender_id   = intval($row['sender_id']);

    if ($row['sender_type'] === 'company') {
        $s = mysqli_prepare($con, "SELECT company_name, logo FROM companies WHERE id = ?");
        mysqli_stmt_bind_param($s, "i", $sender_id);
        mysqli_stmt_execute($s);
        $sr = mysqli_stmt_get_result($s);
        $comp = mysqli_fetch_assoc($sr);
        mysqli_stmt_close($s);
        if ($comp) {
            $sender_name = $comp['company_name'];
            $sender_logo = $comp['logo'] ?? '';
        }
    } elseif ($row['sender_type'] === 'user') {
        $s = mysqli_prepare($con, "SELECT username FROM user_info WHERE id = ?");
        mysqli_stmt_bind_param($s, "i", $sender_id);
        mysqli_stmt_execute($s);
        $sr = mysqli_stmt_get_result($s);
        $u = mysqli_fetch_assoc($sr);
        mysqli_stmt_close($s);
        if ($u) $sender_name = $u['username'];
    }

    $alerts[] = [
        'sender_id'    => $sender_id,
        'sender_type'  => $row['sender_type'],
        'sender_name'  => $sender_name !== '' ? $sender_name : ($row['title'] ?? 'Live chat'),
        'sender_logo'  => $sender_logo,
        'message'      => $row['message'],
        'created_at'   => $row['created_at'],
    ];
}

// Mark delivered so alerts fire exactly once
if (!empty($notif_ids)) {
    $ids = implode(',', $notif_ids);
    mysqli_query($con, "UPDATE notifications SET is_read = 1 WHERE id IN ($ids)");
}

echo json_encode(['success' => true, 'alerts' => $alerts]);
