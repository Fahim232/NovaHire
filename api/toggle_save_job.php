<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id'])) {
    echo json_encode(['success' => false, 'message' => 'Login required']);
    exit();
}

include __DIR__ . '/../admin/dbcon.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$user_id = intval($_SESSION['id']);
$job_id = isset($_POST['job_id']) ? intval($_POST['job_id']) : 0;

if ($job_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid job ID']);
    exit();
}

$check = mysqli_query($con, "SELECT id FROM saved_jobs WHERE user_id = $user_id AND job_id = $job_id");
$is_saved = mysqli_num_rows($check) > 0;

if ($is_saved) {
    mysqli_query($con, "DELETE FROM saved_jobs WHERE user_id = $user_id AND job_id = $job_id");
    $saved = false;
} else {
    mysqli_query($con, "INSERT INTO saved_jobs (user_id, job_id) VALUES ($user_id, $job_id)");
    $saved = true;
}

$count_result = mysqli_query($con, "SELECT COUNT(*) as total FROM saved_jobs WHERE user_id = $user_id");
$count_row = mysqli_fetch_assoc($count_result);
$count = intval($count_row['total']);

echo json_encode(['success' => true, 'saved' => $saved, 'count' => $count]);
