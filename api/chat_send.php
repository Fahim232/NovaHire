<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id'])) {
    echo json_encode(['success' => false, 'message' => 'Login required']);
    exit();
}

include __DIR__ . '/../admin/dbcon.php';
include __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

$user_id = intval($_SESSION['id']);
$receiver_type = mysqli_real_escape_string($con, $_POST['receiver_type'] ?? '');
$receiver_id = intval($_POST['receiver_id'] ?? 0);
$message = trim($_POST['message'] ?? '');

if (empty($receiver_type) || $receiver_id <= 0 || empty($message)) {
    echo json_encode(['success' => false, 'message' => 'Invalid message data']);
    exit();
}

$stmt = mysqli_prepare($con, "INSERT INTO live_chats (sender_type, sender_id, receiver_type, receiver_id, message) VALUES ('user', ?, ?, ?, ?)");
mysqli_stmt_bind_param($stmt, "isis", $user_id, $receiver_type, $receiver_id, $message);
$result = mysqli_stmt_execute($stmt);
$msg_id = mysqli_insert_id($con);
mysqli_stmt_close($stmt);

if ($result) {
    echo json_encode(['success' => true, 'id' => $msg_id]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to send message']);
}
