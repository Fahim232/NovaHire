<?php
require_once __DIR__ . '/../includes/bootstrap.php';

if (!isset($_SESSION['admin_username'])) {
    header('location: admin_login.php');
    exit();
}

// Redirect to main dashboard
header('location: admin_dashboard.php');
exit();
