<?php
session_start();
header('Content-Type: application/json');
include '../admin/dbcon.php';
include '../includes/functions.php';

if (!isset($_SESSION['company_id'])) {
    echo json_encode(['ok' => false, 'msg' => 'Unauthorized']);
    exit;
}

$company_id = (int)$_SESSION['company_id'];
$company_name = $_SESSION['company_name'] ?? 'Company';
$app_id = (int)($_POST['app_id'] ?? 0);
$status = $_POST['status'] ?? '';

$allowed = ['pending', 'reviewed', 'shortlisted', 'rejected'];

if ($app_id <= 0 || !in_array($status, $allowed)) {
    echo json_encode(['ok' => false, 'msg' => 'Invalid request']);
    exit;
}

$q = mysqli_query($con, "SELECT ja.id, ja.user_id, ja.application_status, cj.job_title
                         FROM job_applications ja
                         JOIN company_jobs cj ON ja.job_id = cj.id
                         WHERE ja.id = $app_id AND ja.company_id = $company_id");

$app = mysqli_fetch_assoc($q);

if (!$app) {
    echo json_encode(['ok' => false, 'msg' => 'Application not found']);
    exit;
}

$status_esc = mysqli_real_escape_string($con, $status);
mysqli_query($con, "UPDATE job_applications SET application_status = '$status_esc' WHERE id = $app_id");

if ($status !== 'pending') {
    notify_application_status($con, $app['user_id'], $company_id, $app['job_title'], $company_name, $status);
}

echo json_encode([
    'ok' => true,
    'msg' => 'Application marked as ' . ucfirst($status),
    'status' => $status,
    'app_id' => $app_id,
]);
