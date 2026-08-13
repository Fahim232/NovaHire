<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id'])) {
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

include '../admin/dbcon.php';
include '../includes/functions.php';

$user_id = $_SESSION['id'];
$count = get_unread_count($con, 'user', $user_id);

echo json_encode(['count' => $count]);
?>
