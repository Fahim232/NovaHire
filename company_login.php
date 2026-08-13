<?php
/**
 * Company Login Redirect Handler
 * 
 * Redirects legacy company login URL callers to the unified multi-role login portal.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect to primary unified login portal
header('Location: auth/login.php');
exit();
?>
