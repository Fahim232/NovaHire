<?php
/**
 * NovaHire — Payment Cancel Handler
 */

require_once __DIR__ . '/../includes/bootstrap.php';

$payment_id = $_GET['payment_id'] ?? $_POST['payment_id'] ?? '';

if ($payment_id) {
    // Update payment status
    $stmt = mysqli_prepare($con, "UPDATE payments SET status = 'cancelled' WHERE payment_id = ? AND status = 'pending'");
    mysqli_stmt_bind_param($stmt, "s", $payment_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

header('Location: ' . BASE_URL . '/company/subscription.php?cancelled=1');
exit;
?>
