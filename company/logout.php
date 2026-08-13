<?php
    session_start();
    session_destroy();
    header('Location: ../company_login.php');
    exit;
?>
