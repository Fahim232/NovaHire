<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

include '../admin/dbcon.php';
include '../includes/functions.php';

$user_id = $_SESSION['id'];
$result = mark_all_read($con, 'user', $user_id);

echo json_encode(['success' => $result]);
?>
