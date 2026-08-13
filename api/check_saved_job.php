<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id'])) {
    echo json_encode(['success' => false, 'saved' => false]);
    exit();
}

include __DIR__ . '/../admin/dbcon.php';

$user_id = intval($_SESSION['id']);
$job_id = isset($_GET['job_id']) ? intval($_GET['job_id']) : 0;

if ($job_id <= 0) {
    echo json_encode(['success' => false, 'saved' => false]);
    exit();
}

$check = mysqli_query($con, "SELECT id FROM saved_jobs WHERE user_id = $user_id AND job_id = $job_id");
$saved = mysqli_num_rows($check) > 0;

echo json_encode(['success' => true, 'saved' => $saved]);
