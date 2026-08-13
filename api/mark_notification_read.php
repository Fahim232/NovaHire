<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

include '../admin/dbcon.php';
include '../includes/functions.php';

$notif_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($notif_id > 0) {
    $uid = intval($_SESSION['id']);
    $check = mysqli_prepare($con, "SELECT id FROM notifications WHERE id = ? AND recipient_type = 'user' AND recipient_id = ?");
    mysqli_stmt_bind_param($check, "ii", $notif_id, $uid);
    mysqli_stmt_execute($check);
    $owned = mysqli_num_rows(mysqli_stmt_get_result($check)) > 0;
    mysqli_stmt_close($check);

    if ($owned) {
        $result = mark_read($con, $notif_id);
    } else {
        $result = false;
    }
    echo json_encode(['success' => $result]);
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid notification ID']);
}
?>
