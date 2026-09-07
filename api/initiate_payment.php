<?php
/**
 * NovaHire — Initiate Payment
 * Starts payment process with selected gateway
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/payment.php';

if (!isset($_SESSION['company_id'])) {
    header('Location: ' . BASE_URL . '/auth/login.php');
    exit;
}

$company_id = $_SESSION['company_id'];
$plan_type = $_GET['plan'] ?? '';
$payment_method = $_GET['method'] ?? 'sslcommerz';

$plans = get_subscription_plans();
if (!isset($plans[$plan_type]) || $plans[$plan_type]['price'] == 0) {
    header('Location: ' . BASE_URL . '/company/subscription.php?error=invalid_plan');
    exit;
}

$amount = $plans[$plan_type]['price'];

if ($payment_method === 'sslcommerz') {
    $payment_data = init_sslcommerz_payment($con, $company_id, $plan_type, $amount);
    
    if ($payment_data) {
        // In production, you would redirect to SSLCOMMERZ gateway
        // For now, we'll simulate successful payment
        echo '<!DOCTYPE html>
        <html>
        <head>
            <title>Processing Payment...</title>
            <style>
                body { font-family: Arial, sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; background: #f5f5f5; }
                .card { background: white; padding: 40px; border-radius: 20px; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
                .spinner { width: 50px; height: 50px; border: 5px solid #f3f3f3; border-top: 5px solid #1a56db; border-radius: 50%; animation: spin 1s linear infinite; margin: 20px auto; }
                @keyframes spin { to { transform: rotate(360deg); } }
            </style>
        </head>
        <body>
            <div class="card">
                <div class="spinner"></div>
                <h2>Processing Payment...</h2>
                <p>Amount: ৳' . number_format($amount) . '</p>
                <p>Plan: ' . ucfirst($plan_type) . '</p>
                <p>Please wait while we process your payment.</p>
                <p><small>Payment ID: ' . $payment_data['payment_id'] . '</small></p>
            </div>
        </body>
        </html>';
        
        // Simulate payment success after 3 seconds (for demo)
        // In production, this would be handled by SSLCOMMERZ callback
        echo '<script>
            setTimeout(function() {
                // Simulate successful payment verification
                window.location.href = "' . BASE_URL . '/api/payment_success.php?val_id=demo_' . time() . '&amount=' . $amount . '&currency=BDT&tran_id=TXN_' . time() . '&payment_id=' . $payment_data['payment_id'] . '";
            }, 3000);
        </script>';
        exit;
    }
} elseif ($payment_method === 'bkash') {
    $payment_data = init_bkash_payment($con, $company_id, $plan_type, $amount);
    
    if ($payment_data) {
        // Redirect to bKash
        header('Location: ' . BASE_URL . '/company/subscription.php?success=1');
        exit;
    }
}

header('Location: ' . BASE_URL . '/company/subscription.php?error=payment_init_failed');
exit;
?>
