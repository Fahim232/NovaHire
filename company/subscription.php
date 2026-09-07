<?php
require_once __DIR__ . '/../includes/bootstrap.php';

if (!isset($_SESSION['company_id'])) {
    header('Location: ' . BASE_URL . '/auth/login.php');
    exit;
}

$company_id = $_SESSION['company_id'];
$current_sub = get_company_subscription($con, $company_id);
$current_plan = $current_sub ? $current_sub['plan_type'] : 'free';
$plans = get_subscription_plans();
$payment_history = get_payment_history($con, $company_id, 10);

$success = isset($_GET['success']);
$cancelled = isset($_GET['cancelled']);
$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Subscription Plans | NovaHire</title>
    <?php include '../includes/links.php'; ?>
    <style>
        .subscription-page {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        .page-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .page-header h1 {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--text);
            margin-bottom: 10px;
        }
        
        .page-header p {
            color: var(--text-muted);
            font-size: 1.1rem;
        }
        
        .current-plan {
            background: linear-gradient(135deg, #1a56db, #0ea5e9);
            color: white;
            padding: 30px;
            border-radius: 20px;
            margin-bottom: 40px;
            text-align: center;
        }
        
        .current-plan h3 {
            font-size: 1.2rem;
            margin-bottom: 10px;
            opacity: 0.9;
        }
        
        .current-plan .plan-name {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 5px;
        }
        
        .current-plan .plan-expires {
            opacity: 0.8;
            font-size: 0.9rem;
        }
        
        .plans-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
            margin-bottom: 50px;
        }
        
        .plan-card {
            background: var(--bg-card);
            border: 2px solid var(--border-light);
            border-radius: 20px;
            padding: 30px;
            position: relative;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .plan-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }
        
        .plan-card.popular {
            border-color: #0ea5e9;
        }
        
        .plan-card.popular::before {
            content: 'Most Popular';
            position: absolute;
            top: -12px;
            left: 50%;
            transform: translateX(-50%);
            background: linear-gradient(135deg, #1a56db, #0ea5e9);
            color: white;
            padding: 5px 20px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 700;
        }
        
        .plan-card.current {
            border-color: #059669;
        }
        
        .plan-card h3 {
            font-size: 1.3rem;
            margin-bottom: 10px;
        }
        
        .plan-card .price {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 5px;
        }
        
        .plan-card .price span {
            font-size: 1rem;
            font-weight: 500;
            color: var(--text-muted);
        }
        
        .plan-card .features {
            list-style: none;
            padding: 0;
            margin: 20px 0;
        }
        
        .plan-card .features li {
            padding: 10px 0;
            border-bottom: 1px solid var(--border-light);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .plan-card .features li i {
            color: #059669;
        }
        
        .plan-card .btn {
            width: 100%;
            padding: 14px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 1rem;
        }
        
        .payment-methods {
            background: var(--bg-card);
            border: 1px solid var(--border-light);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 40px;
        }
        
        .payment-methods h3 {
            margin-bottom: 20px;
        }
        
        .payment-method-options {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .payment-method {
            flex: 1;
            min-width: 150px;
            border: 2px solid var(--border-light);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .payment-method:hover,
        .payment-method.selected {
            border-color: #0ea5e9;
            background: rgba(139, 92, 246, 0.1);
        }
        
        .payment-method i {
            font-size: 2rem;
            margin-bottom: 10px;
            color: var(--text-muted);
        }
        
        .payment-history {
            background: var(--bg-card);
            border: 1px solid var(--border-light);
            border-radius: 20px;
            padding: 30px;
        }
        
        .payment-history h3 {
            margin-bottom: 20px;
        }
        
        .payment-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .payment-table th,
        .payment-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid var(--border-light);
        }
        
        .payment-table th {
            font-weight: 700;
            color: var(--text-muted);
        }
        
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .status-badge.completed {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-badge.pending {
            background: #fef3c7;
            color: #92400e;
        }
        
        .status-badge.failed {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #059669;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #dc2626;
        }
    </style>
</head>
<body>
    <?php include '../company/company_header.php'; ?>
    
    <div class="subscription-page">
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                Payment successful! Your subscription has been activated.
            </div>
        <?php endif; ?>
        
        <?php if ($cancelled): ?>
            <div class="alert alert-error">
                <i class="fas fa-times-circle"></i>
                Payment was cancelled.
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <div class="current-plan">
            <h3>Current Plan</h3>
            <div class="plan-name"><?php echo ucfirst($current_plan); ?></div>
            <?php if ($current_sub): ?>
                <div class="plan-expires">Expires: <?php echo date('M d, Y', strtotime($current_sub['expires_at'])); ?></div>
            <?php else: ?>
                <div class="plan-expires">Free plan - No expiration</div>
            <?php endif; ?>
        </div>
        
        <div class="page-header">
            <h1>Choose Your Plan</h1>
            <p>Scale your hiring with the right plan for your business</p>
        </div>
        
        <div class="plans-grid">
            <?php foreach ($plans as $key => $plan): ?>
                <div class="plan-card <?php echo $plan['popular'] ? 'popular' : ''; ?> <?php echo $key === $current_plan ? 'current' : ''; ?>">
                    <h3><?php echo $plan['name']; ?></h3>
                    <div class="price">
                        <?php echo format_price($plan['price']); ?>
                        <span>/month</span>
                    </div>
                    <ul class="features">
                        <?php foreach ($plan['features'] as $feature): ?>
                            <li>
                                <i class="fas fa-check"></i>
                                <?php echo $feature; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    
                    <?php if ($key === $current_plan): ?>
                        <button class="btn btn-secondary" disabled>Current Plan</button>
                    <?php elseif ($plan['price'] == 0): ?>
                        <button class="btn btn-outline" onclick="downgradePlan('<?php echo $key; ?>')">Downgrade</button>
                    <?php else: ?>
                        <button class="btn btn-primary" onclick="selectPlan('<?php echo $key; ?>', <?php echo $plan['price']; ?>)">
                            Upgrade Now
                        </button>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="payment-methods">
            <h3>Payment Method</h3>
            <div class="payment-method-options">
                <div class="payment-method selected" onclick="selectPaymentMethod('sslcommerz')">
                    <i class="fas fa-credit-card"></i>
                    <div>Credit/Debit Card</div>
                </div>
                <div class="payment-method" onclick="selectPaymentMethod('bkash')">
                    <i class="fas fa-mobile-alt"></i>
                    <div>bKash</div>
                </div>
                <div class="payment-method" onclick="selectPaymentMethod('nagad')">
                    <i class="fas fa-wallet"></i>
                    <div>Nagad</div>
                </div>
            </div>
        </div>
        
        <?php if (!empty($payment_history)): ?>
            <div class="payment-history">
                <h3>Payment History</h3>
                <table class="payment-table">
                    <thead>
                        <tr>
                            <th>Payment ID</th>
                            <th>Plan</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payment_history as $payment): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($payment['payment_id']); ?></td>
                                <td><?php echo ucfirst($payment['plan_type']); ?></td>
                                <td><?php echo format_price($payment['amount']); ?></td>
                                <td><?php echo ucfirst($payment['payment_method']); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $payment['status']; ?>">
                                        <?php echo ucfirst($payment['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($payment['created_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Payment Modal -->
    <div id="paymentModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999;">
        <div style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); background:white; padding:40px; border-radius:20px; max-width:500px; width:90%;">
            <h3>Confirm Payment</h3>
            <p id="paymentDetails"></p>
            <div style="display:flex; gap:10px; margin-top:20px;">
                <button onclick="closeModal()" class="btn btn-outline" style="flex:1;">Cancel</button>
                <button onclick="confirmPayment()" class="btn btn-primary" style="flex:1;">Confirm & Pay</button>
            </div>
        </div>
    </div>
    
    <script>
        let selectedPlan = '';
        let selectedAmount = 0;
        let paymentMethod = 'sslcommerz';
        
        function selectPlan(plan, amount) {
            selectedPlan = plan;
            selectedAmount = amount;
            document.getElementById('paymentDetails').innerHTML = 
                '<strong>Plan:</strong> ' + plan.charAt(0).toUpperCase() + plan.slice(1) + '<br>' +
                '<strong>Amount:</strong> ৳' + amount.toLocaleString();
            document.getElementById('paymentModal').style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('paymentModal').style.display = 'none';
        }
        
        function selectPaymentMethod(method) {
            paymentMethod = method;
            document.querySelectorAll('.payment-method').forEach(el => el.classList.remove('selected'));
            event.currentTarget.classList.add('selected');
        }
        
        function confirmPayment() {
            // Redirect to payment gateway
            window.location.href = '<?php echo BASE_URL; ?>/api/initiate_payment.php?plan=' + selectedPlan + '&method=' + paymentMethod;
        }
        
        function downgradePlan(plan) {
            if (confirm('Are you sure you want to downgrade? You will lose access to premium features.')) {
                window.location.href = '<?php echo BASE_URL; ?>/api/downgrade_plan.php?plan=' + plan;
            }
        }
    </script>
</body>
</html>
