<?php
/**
 * NovaHire — Monetization Layer
 * ---------------------------------------------------------------------------
 * Central money logic for the whole platform:
 *   • Seeker "NovaHire Pro" premium plan  (recurring revenue from job seekers)
 *   • Generalized payments  (companies AND seekers pay through one pipeline)
 *   • Fulfilment dispatcher  (what to unlock once a payment completes)
 *   • Pricing config  (single source of truth for every price on the platform)
 *   • Featured jobs, mentor commission split, gating helpers
 *
 * Currency is BDT (৳) to match the existing company subscription system.
 * Reuses format_price() from includes/payment.php.
 */

if (defined('NOVAHIRE_MONETIZATION')) return;
define('NOVAHIRE_MONETIZATION', true);

/* ── Central pricing (edit these numbers to tune the business model) ───────── */
function nh_pricing() {
    return [
        'pro_price'             => 499,   // BDT / month — NovaHire Pro (seeker)
        'pro_duration_days'     => 30,
        'certificate_price'     => 299,   // BDT per verified certificate (free for Pro)
        'featured_price'        => 1499,  // BDT per job boost
        'featured_days'         => 14,
        'session_commission_pct'=> 20,    // platform keeps 20% of each grooming session
        'placement_fee_flat'    => 9999,  // BDT flat success fee per hire (fallback)
        'placement_fee_pct'     => 8,     // % of annual/first-period salary if salary known
    ];
}

/* ── NovaHire Pro plan definition ──────────────────────────────────────────── */
function nh_pro_plan() {
    $p = nh_pricing();
    return [
        'key'      => 'pro',
        'name'     => 'NovaHire Pro',
        'price'    => $p['pro_price'],
        'duration' => $p['pro_duration_days'],
        'features' => [
            'Unlimited AI tools (resume, cover letter, mock interview)',
            'Priority ranking in employer talent pools',
            'Free verified skill certificates',
            'Verified "Pro" badge on your profile',
            'Early access to newly posted matching jobs',
        ],
    ];
}

/* ── Seeker subscription state ─────────────────────────────────────────────── */
function get_user_subscription($con, $user_id) {
    if (!nh_table_exists($con, 'user_subscriptions')) return null;
    $stmt = mysqli_prepare($con, "SELECT * FROM user_subscriptions WHERE user_id = ? AND status = 'active' ORDER BY expires_at DESC LIMIT 1");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $sub = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);
    if ($sub && strtotime($sub['expires_at']) > time()) return $sub;
    return null;
}

function is_user_pro($con, $user_id) {
    return get_user_subscription($con, $user_id) !== null;
}

function activate_user_subscription($con, $user_id, $payment_id = null, $plan = 'pro', $days = null) {
    if ($days === null) $days = nh_pricing()['pro_duration_days'];

    // expire any current active sub
    $stmt = mysqli_prepare($con, "UPDATE user_subscriptions SET status = 'expired' WHERE user_id = ? AND status = 'active'");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    $expires_at = date('Y-m-d H:i:s', time() + ($days * 86400));
    $stmt = mysqli_prepare($con, "INSERT INTO user_subscriptions (user_id, plan_type, payment_id, starts_at, expires_at, status, created_at) VALUES (?, ?, ?, NOW(), ?, 'active', NOW())");
    mysqli_stmt_bind_param($stmt, "isss", $user_id, $plan, $payment_id, $expires_at);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

/* ── Generalized payment pipeline ──────────────────────────────────────────── */
/**
 * Create a pending payment row and return its payment_id.
 * $opts: item_id, plan_type, method, currency, company_id
 */
function create_payment($con, $payer_type, $payer_id, $purpose, $amount, $opts = []) {
    $prefix = $payer_type === 'user' ? 'USR' : 'CMP';
    $payment_id = $prefix . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(3));
    $item_id    = $opts['item_id']    ?? null;
    $plan_type  = $opts['plan_type']  ?? null;
    $method     = $opts['method']     ?? 'sslcommerz';
    $currency   = $opts['currency']   ?? 'BDT';
    $company_id = $opts['company_id'] ?? ($payer_type === 'company' ? $payer_id : null);

    $stmt = mysqli_prepare($con, "INSERT INTO payments (payment_id, payer_type, payer_id, company_id, purpose, item_id, plan_type, amount, currency, payment_method, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
    mysqli_stmt_bind_param($stmt, "ssiisisdss", $payment_id, $payer_type, $payer_id, $company_id, $purpose, $item_id, $plan_type, $amount, $currency, $method);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok ? $payment_id : false;
}

function get_payment($con, $payment_id) {
    $stmt = mysqli_prepare($con, "SELECT * FROM payments WHERE payment_id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "s", $payment_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

function mark_payment_completed($con, $payment_id) {
    $stmt = mysqli_prepare($con, "UPDATE payments SET status = 'completed', completed_at = NOW() WHERE payment_id = ? AND status <> 'completed'");
    mysqli_stmt_bind_param($stmt, "s", $payment_id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

/**
 * Fulfil a completed payment based on its purpose.
 * Returns ['ok'=>bool, 'redirect'=>relative-url].
 */
function fulfill_payment($con, $payment) {
    $purpose = $payment['purpose'] ?? '';
    $redirect = BASE_URL . '/seeker/ai_hub.php';

    switch ($purpose) {
        case 'pro_subscription':
            activate_user_subscription($con, $payment['payer_id'], $payment['payment_id']);
            create_notification($con, 'user', $payment['payer_id'], 'system', null,
                'Welcome to NovaHire Pro 🎉', 'Your Pro membership is active. Enjoy unlimited AI tools, priority ranking and free certificates.', 'system');
            $redirect = BASE_URL . '/seeker/pro.php?success=1';
            break;

        case 'session':
            nh_confirm_session($con, (int)$payment['item_id'], $payment['payment_id']);
            $redirect = BASE_URL . '/seeker/my_sessions.php?booked=1';
            break;

        case 'certificate':
            nh_mark_certificate_paid($con, (int)$payment['item_id'], $payment['payment_id']);
            $redirect = BASE_URL . '/seeker/certificates.php?issued=1';
            break;

        case 'featured_job':
            nh_activate_featured_job($con, (int)$payment['item_id']);
            $redirect = BASE_URL . '/company/my_jobs.php?featured=1';
            break;

        case 'subscription': // company subscription (existing behaviour)
            if (function_exists('activate_subscription') && !empty($payment['company_id'])) {
                activate_subscription($con, $payment['company_id'], $payment['plan_type'], $payment['payment_id']);
            }
            $redirect = BASE_URL . '/company/subscription.php?success=1';
            break;
    }
    return ['ok' => true, 'redirect' => $redirect];
}

/**
 * Is a real payment gateway configured? If not, we run in demo mode
 * (payments auto-complete) so the platform is fully demonstrable offline.
 */
function nh_gateway_live() {
    $store = defined('SSLC_STORE_ID') ? SSLC_STORE_ID : '';
    $sandbox = defined('SSLC_SANDBOX') ? SSLC_SANDBOX : true;
    return (!empty($store) && !$sandbox);
}

/* ── Featured jobs ─────────────────────────────────────────────────────────── */
function nh_activate_featured_job($con, $job_id) {
    $until = date('Y-m-d H:i:s', time() + (nh_pricing()['featured_days'] * 86400));
    $stmt = mysqli_prepare($con, "UPDATE company_jobs SET is_featured = 1, featured_until = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "si", $until, $job_id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

function nh_is_job_featured($job) {
    if (empty($job['is_featured'])) return false;
    if (empty($job['featured_until'])) return true;
    return strtotime($job['featured_until']) > time();
}

/* ── Mentor commission split ───────────────────────────────────────────────── */
function nh_session_split($price) {
    $pct = nh_pricing()['session_commission_pct'];
    $commission = round($price * $pct / 100, 2);
    $mentor_earning = round($price - $commission, 2);
    return ['commission' => $commission, 'mentor_earning' => $mentor_earning];
}

/* ── Small utilities ───────────────────────────────────────────────────────── */
function nh_table_exists($con, $table) {
    $table = mysqli_real_escape_string($con, $table);
    $r = @mysqli_query($con, "SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '$table'");
    return $r && mysqli_num_rows($r) > 0;
}

/** Format BDT price (falls back if payment.php not loaded). */
function nh_price($amount) {
    if (function_exists('format_price')) return format_price($amount);
    return '৳' . number_format((float)$amount);
}

/** A small "PRO" pill for use next to a seeker's name. */
function nh_pro_badge() {
    return '<span style="display:inline-flex;align-items:center;gap:4px;background:linear-gradient(135deg,#d97706,#f97316);color:#fff;font-size:0.68rem;font-weight:800;padding:2px 8px;border-radius:20px;letter-spacing:0.4px;"><i class="fas fa-crown"></i>PRO</span>';
}
?>
