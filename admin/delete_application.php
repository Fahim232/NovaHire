<?php
require_once __DIR__ . '/../includes/bootstrap.php';

if (!isset($_SESSION['admin_username'])) {
    header('Location: admin_login.php');
    exit;
}

// Handle POST delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_application'])) {
    require_csrf();

    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) {
        header('Location: showdata.php');
        exit;
    }

    $stmt = mysqli_prepare($con, "DELETE FROM jobregistration WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    $dquery = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($dquery) {
        header('Location: showdata.php?success=deleted');
    } else {
        header('Location: showdata.php?error=delete_failed');
    }
    exit;
}

// If accessed via GET, redirect back
header('Location: showdata.php');
exit;
