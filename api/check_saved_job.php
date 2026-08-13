<?php
/**
 * API Endpoint: Check Saved Job Bookmark State
 * 
 * Determines whether a given job ID is bookmarked/saved by the logged-in job seeker.
 */

// Initialize session if not active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Return JSON header
header('Content-Type: application/json');

// Session check
if (!isset($_SESSION['id'])) {
    echo json_encode(['success' => false, 'saved' => false]);
    exit();
}

// Include database connection
include __DIR__ . '/../admin/dbcon.php';

$user_id = intval($_SESSION['id']);
$job_id  = isset($_GET['job_id']) ? intval($_GET['job_id']) : 0;

if ($job_id <= 0) {
    echo json_encode(['success' => false, 'saved' => false]);
    exit();
}

// Check saved status via prepared query
$stmt = mysqli_prepare($con, "SELECT id FROM saved_jobs WHERE user_id = ? AND job_id = ?");
mysqli_stmt_bind_param($stmt, "ii", $user_id, $job_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$saved = mysqli_num_rows($res) > 0;
mysqli_stmt_close($stmt);

echo json_encode(['success' => true, 'saved' => $saved]);
?>
