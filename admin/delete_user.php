<?php
require_once __DIR__ . '/../includes/bootstrap.php';

if (!isset($_SESSION['admin_username'])) {
    header('Location: admin_login.php');
    exit;
}

    $id = intval($_GET['id'] ?? 0);
    if ($id <= 0) {
        header('location: show_users.php');
        exit;
    }

    $stmt = mysqli_prepare($con, "DELETE FROM user_info WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    $dquery = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($dquery){
        header('location: show_users.php?success=deleted');
    }else{
        header('location: show_users.php?error=delete_failed');
    }
    exit;
