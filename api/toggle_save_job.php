<?php
/**
 * API Endpoint: Toggle Save/Bookmark Job
 * 
 * Toggles a job's bookmarked state for the current logged-in user (inserts if not saved, deletes if saved).
 * Returns the updated saved status and total saved job count.
 */

// Initialize session if not active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set JSON output content header
header('Content-Type: application/json');

// Session authentication check
if (!isset($_SESSION['id'])) {
    echo json_encode(['success' => false, 'message' => 'Login required']);
    exit();
}

// Include database connection
include __DIR__ . '/../admin/dbcon.php';

// Verify POST request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$user_id = intval($_SESSION['id']);
$job_id  = isset($_POST['job_id']) ? intval($_POST['job_id']) : 0;

if ($job_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid job ID']);
    exit();
}

// 1. Check if job is currently saved
$check_stmt = mysqli_prepare($con, "SELECT id FROM saved_jobs WHERE user_id = ? AND job_id = ?");
mysqli_stmt_bind_param($check_stmt, "ii", $user_id, $job_id);
mysqli_stmt_execute($check_stmt);
$check_res = mysqli_stmt_get_result($check_stmt);
$is_saved  = mysqli_num_rows($check_res) > 0;
mysqli_stmt_close($check_stmt);

// 2. Toggle state: Remove if saved, insert if not saved
if ($is_saved) {
    $del_stmt = mysqli_prepare($con, "DELETE FROM saved_jobs WHERE user_id = ? AND job_id = ?");
    mysqli_stmt_bind_param($del_stmt, "ii", $user_id, $job_id);
    mysqli_stmt_execute($del_stmt);
    mysqli_stmt_close($del_stmt);
    $saved = false;
} else {
    $ins_stmt = mysqli_prepare($con, "INSERT INTO saved_jobs (user_id, job_id) VALUES (?, ?)");
    mysqli_stmt_bind_param($ins_stmt, "ii", $user_id, $job_id);
    mysqli_stmt_execute($ins_stmt);
    mysqli_stmt_close($ins_stmt);
    $saved = true;
}

// 3. Fetch total saved jobs count for user
$count_stmt = mysqli_prepare($con, "SELECT COUNT(*) as total FROM saved_jobs WHERE user_id = ?");
mysqli_stmt_bind_param($count_stmt, "i", $user_id);
mysqli_stmt_execute($count_stmt);
$count_res = mysqli_stmt_get_result($count_stmt);
$count_row = mysqli_fetch_assoc($count_res);
$count     = intval($count_row['total']);
mysqli_stmt_close($count_stmt);

// Output JSON payload
echo json_encode(['success' => true, 'saved' => $saved, 'count' => $count]);
?>
