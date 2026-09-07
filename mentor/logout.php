<?php
require_once __DIR__ . '/../includes/bootstrap.php';
// Clear only mentor-related keys so a shared session (rare) isn't fully wiped.
unset($_SESSION['mentor_id'], $_SESSION['mentor_name']);
if (($_SESSION['user_type'] ?? '') === 'mentor') unset($_SESSION['user_type']);
header('Location: ' . BASE_URL . '/mentor/login.php');
exit;
