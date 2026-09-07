<?php
require_once __DIR__ . '/../includes/bootstrap.php';

if (!isset($_SESSION['admin_username'])) {
    header('Location: admin_login.php');
    exit;
}

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: admin_dashboard.php');
    exit;
}

// Get current status
$stmt = mysqli_prepare($con, "SELECT id, status FROM companies WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$company = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$company) {
    header('Location: admin_dashboard.php?error=not_found');
    exit;
}

// Toggle status
$new_status = ($company['status'] === 'active') ? 'inactive' : 'active';

$update_stmt = mysqli_prepare($con, "UPDATE companies SET status = ? WHERE id = ?");
mysqli_stmt_bind_param($update_stmt, "si", $new_status, $id);
mysqli_stmt_execute($update_stmt);
mysqli_stmt_close($update_stmt);

header('Location: admin_dashboard.php?success=status_updated');
exit;
?>
