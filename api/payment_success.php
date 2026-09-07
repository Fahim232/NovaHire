<?php
/**
 * NovaHire — Payment Success Handler
 * Handles successful SSLCOMMERZ payment
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/payment.php';

// Get payment parameters from SSLCOMMERZ
$val_id = $_GET['val_id'] ?? $_POST['val_id'] ?? '';
$amount = $_GET['amount'] ?? $_POST['amount'] ?? '';
$currency = $_GET['currency'] ?? $_POST['currency'] ?? '';
$tran_id = $_GET['tran_id'] ?? $_POST['tran_id'] ?? '';
$payment_id = $_GET['payment_id'] ?? $_POST['payment_id'] ?? '';

if (empty($val_id)) {
    header('Location: ' . BASE_URL . '/company/subscription.php?error=invalid_payment');
    exit;
}

// Verify payment with SSLCOMMERZ
$config = get_smtp_config(); // Reusing config function
$store_id = defined('SSLC_STORE_ID') ? SSLC_STORE_ID : '';
$store_pass = defined('SSLC_STORE_PASS') ? SSLC_STORE_PASS : '';

$verification = verify_sslcommerz_payment($val_id, $store_id, $store_pass);

if (isset($verification['status']) && $verification['status'] == 'VALID') {
    // Get payment record
    $stmt = mysqli_prepare($con, "SELECT * FROM payments WHERE payment_id = ?");
    mysqli_stmt_bind_param($stmt, "s", $payment_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $payment = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if ($payment) {
        // Activate subscription
        if (activate_subscription($con, $payment['company_id'], $payment['plan_type'], $payment_id)) {
            // Log activity
            create_activity_log('company', $payment['company_id'], 'payment_completed', [
                'payment_id' => $payment_id,
                'plan' => $payment['plan_type'],
                'amount' => $payment['amount'],
            ]);
            
            header('Location: ' . BASE_URL . '/company/subscription.php?success=1&payment_id=' . urlencode($payment_id));
            exit;
        }
    }
}

// Payment verification failed
error_log("Payment verification failed: " . json_encode($verification));
header('Location: ' . BASE_URL . '/company/subscription.php?error=verification_failed');
exit;
?>
