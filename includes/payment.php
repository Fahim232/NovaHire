<?php
/**
 * NovaHire — Payment System
 * SSLCOMMERZ & bKash integration for subscription and job posting fees
 */

if (defined('NOVAHIRE_PAYMENT')) return;
define('NOVAHIRE_PAYMENT', true);

/* ── Subscription Plans ──────────────────────────────────────────────────── */
function get_subscription_plans() {
    return [
        'free' => [
            'name'        => 'Free',
            'price'       => 0,
            'duration'    => 30, // days
            'job_posts'   => 2,
            'features'    => ['2 Job Posts', 'Basic Analytics', 'Email Support'],
            'popular'     => false,
        ],
        'basic' => [
            'name'        => 'Basic',
            'price'       => 2999, // BDT
            'duration'    => 30,
            'job_posts'   => 10,
            'features'    => ['10 Job Posts', 'Advanced Analytics', 'Priority Support', 'Featured Badge'],
            'popular'     => true,
        ],
        'pro' => [
            'name'        => 'Professional',
            'price'       => 7999,
            'duration'    => 30,
            'job_posts'   => 50,
            'features'    => ['50 Job Posts', 'Premium Analytics', '24/7 Support', 'Featured Badge', 'Resume Database Access'],
            'popular'     => false,
        ],
        'enterprise' => [
            'name'        => 'Enterprise',
            'price'       => 19999,
            'duration'    => 365,
            'job_posts'   => -1, // unlimited
            'features'    => ['Unlimited Job Posts', 'Enterprise Analytics', 'Dedicated Support', 'Premium Badge', 'Full Resume Database', 'API Access'],
            'popular'     => false,
        ],
    ];
}

/* ── Get Company Subscription ────────────────────────────────────────────── */
function get_company_subscription($con, $company_id) {
    // Check if table exists
    $check = @mysqli_query($con, "SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'company_subscriptions'");
    if (!$check || mysqli_num_rows($check) == 0) return null;
    
    $stmt = mysqli_prepare($con, "SELECT * FROM company_subscriptions WHERE company_id = ? AND status = 'active' ORDER BY expires_at DESC LIMIT 1");
    mysqli_stmt_bind_param($stmt, "i", $company_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $sub = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if ($sub && strtotime($sub['expires_at']) > time()) {
        return $sub;
    }
    
    return null;
}

function get_company_plan($con, $company_id) {
    $sub = get_company_subscription($con, $company_id);
    if ($sub) {
        $plans = get_subscription_plans();
        return $plans[$sub['plan_type']] ?? $plans['free'];
    }
    return get_subscription_plans()['free'];
}

function can_post_job($con, $company_id) {
    $plan = get_company_plan($con, $company_id);
    
    if ($plan['job_posts'] == -1) return true; // unlimited
    
    $stmt = mysqli_prepare($con, "SELECT COUNT(*) as cnt FROM company_jobs WHERE company_id = ? AND status IN ('active', 'draft')");
    mysqli_stmt_bind_param($stmt, "i", $company_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    return $row['cnt'] < $plan['job_posts'];
}

function get_remaining_job_posts($con, $company_id) {
    $plan = get_company_plan($con, $company_id);
    
    if ($plan['job_posts'] == -1) return 'Unlimited';
    
    $stmt = mysqli_prepare($con, "SELECT COUNT(*) as cnt FROM company_jobs WHERE company_id = ? AND status IN ('active', 'draft')");
    mysqli_stmt_bind_param($stmt, "i", $company_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    return max(0, $plan['job_posts'] - $row['cnt']);
}

/* ── SSLCOMMERZ Integration ──────────────────────────────────────────────── */
function init_sslcommerz_payment($con, $company_id, $plan_type, $amount, $currency = 'BDT') {
    $store_id = defined('SSLC_STORE_ID') ? SSLC_STORE_ID : '';
    $store_pass = defined('SSLC_STORE_PASS') ? SSLC_STORE_PASS : '';
    $sandbox = defined('SSLC_SANDBOX') ? SSLC_SANDBOX : true;
    
    // Create payment record
    $payment_id = 'PAY-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
    
    $stmt = mysqli_prepare($con, "INSERT INTO payments (payment_id, company_id, plan_type, amount, currency, payment_method, status, created_at) VALUES (?, ?, ?, ?, ?, 'sslcommerz', 'pending', NOW())");
    mysqli_stmt_bind_param($stmt, "siss", $payment_id, $company_id, $plan_type, $amount, $currency);
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    if (!$result) return false;
    
    $base_url = defined('SSLC_SANDBOX_URL') ? SSLC_SANDBOX_URL : 'https://sandbox.sslcommerz.com';
    if (!$sandbox) {
        $base_url = 'https://securepay.sslcommerz.com';
    }
    
    return [
        'payment_id' => $payment_id,
        'store_id'   => $store_id,
        'amount'     => $amount,
        'currency'   => $currency,
        'success_url' => BASE_URL . '/api/payment_success.php',
        'cancel_url'  => BASE_URL . '/api/payment_cancel.php',
        'fail_url'    => BASE_URL . '/api/payment_fail.php',
        'sandbox'     => $sandbox,
    ];
}

function verify_sslcommerz_payment($val_id, $store_id, $store_pass) {
    $verify_url = "https://sandbox.sslcommerz.com/validator/api/validationserverAPI.php";
    
    $post_data = [
        'val_id'     => $val_id,
        'store_id'   => $store_id,
        'store_pass' => $store_pass,
        'v'          => '1',
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $verify_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}

/* ── bKash Integration ───────────────────────────────────────────────────── */
function init_bkash_payment($con, $company_id, $plan_type, $amount) {
    $app_key = defined('BKASH_APP_KEY') ? BKASH_APP_KEY : '';
    $app_secret = defined('BKASH_APP_SECRET') ? BKASH_APP_SECRET : '';
    $username = defined('BKASH_USERNAME') ? BKASH_USERNAME : '';
    $password = defined('BKASH_PASSWORD') ? BKASH_PASSWORD : '';
    $sandbox = defined('BKASH_SANDBOX') ? BKASH_SANDBOX : true;
    
    // Create payment record
    $payment_id = 'BK-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
    
    $stmt = mysqli_prepare($con, "INSERT INTO payments (payment_id, company_id, plan_type, amount, currency, payment_method, status, created_at) VALUES (?, ?, ?, ?, 'BDT', 'bkash', 'pending', NOW())");
    mysqli_stmt_bind_param($stmt, "siss", $payment_id, $company_id, $plan_type, $amount);
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    if (!$result) return false;
    
    return [
        'payment_id' => $payment_id,
        'amount'     => $amount,
        'sandbox'    => $sandbox,
    ];
}

/* ── Activate Subscription ───────────────────────────────────────────────── */
function activate_subscription($con, $company_id, $plan_type, $payment_id) {
    $plans = get_subscription_plans();
    $plan = $plans[$plan_type] ?? null;
    
    if (!$plan) return false;
    
    // Deactivate old subscription
    $stmt = mysqli_prepare($con, "UPDATE company_subscriptions SET status = 'expired' WHERE company_id = ? AND status = 'active'");
    mysqli_stmt_bind_param($stmt, "i", $company_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    // Create new subscription
    $expires_at = date('Y-m-d H:i:s', time() + ($plan['duration'] * 86400));
    
    $stmt = mysqli_prepare($con, "INSERT INTO company_subscriptions (company_id, plan_type, payment_id, starts_at, expires_at, status, created_at) VALUES (?, ?, ?, NOW(), ?, 'active', NOW())");
    mysqli_stmt_bind_param($stmt, "isis", $company_id, $plan_type, $payment_id, $expires_at);
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    // Update payment status
    if ($payment_id) {
        $stmt = mysqli_prepare($con, "UPDATE payments SET status = 'completed' WHERE payment_id = ?");
        mysqli_stmt_bind_param($stmt, "s", $payment_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    
    return $result;
}

/* ── Payment History ─────────────────────────────────────────────────────── */
function get_payment_history($con, $company_id, $limit = 20) {
    // Check if table exists
    $check = @mysqli_query($con, "SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'payments'");
    if (!$check || mysqli_num_rows($check) == 0) return [];
    
    $stmt = mysqli_prepare($con, "SELECT * FROM payments WHERE company_id = ? ORDER BY created_at DESC LIMIT ?");
    mysqli_stmt_bind_param($stmt, "ii", $company_id, $limit);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $payments = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $payments[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $payments;
}

/* ── Format Price ────────────────────────────────────────────────────────── */
function format_price($amount, $currency = 'BDT') {
    if ($currency === 'BDT') {
        return '৳' . number_format($amount);
    }
    return '$' . number_format($amount, 2);
}

/* ── Render Pricing Cards ────────────────────────────────────────────────── */
function render_pricing_cards($current_plan = 'free') {
    $plans = get_subscription_plans();
    $html = '<div class="pricing-cards">';
    
    foreach ($plans as $key => $plan) {
        $is_current = ($key === $current_plan);
        $popular = $plan['popular'] ? ' popular' : '';
        
        $html .= '<div class="pricing-card' . $popular . '">';
        if ($plan['popular']) {
            $html .= '<div class="popular-badge">Most Popular</div>';
        }
        $html .= '<h3>' . htmlspecialchars($plan['name']) . '</h3>';
        $html .= '<div class="price">' . format_price($plan['price']) . '<span>/month</span></div>';
        $html .= '<ul>';
        foreach ($plan['features'] as $feature) {
            $html .= '<li><i class="fas fa-check"></i> ' . htmlspecialchars($feature) . '</li>';
        }
        $html .= '</ul>';
        
        if ($is_current) {
            $html .= '<button class="btn btn-secondary" disabled>Current Plan</button>';
        } else {
            $html .= '<button class="btn btn-primary" onclick="selectPlan(\'' . $key . '\')">';
            $html .= $plan['price'] == 0 ? 'Downgrade' : 'Upgrade';
            $html .= '</button>';
        }
        
        $html .= '</div>';
    }
    
    $html .= '</div>';
    return $html;
}
?>
