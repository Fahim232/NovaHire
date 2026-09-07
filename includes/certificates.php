<?php
/**
 * NovaHire — Verifiable Skill Certificates
 * ---------------------------------------------------------------------------
 * When a seeker passes a grooming quiz they can claim a shareable, verifiable
 * certificate. Free for NovaHire Pro members; a one-off fee otherwise.
 * Each certificate carries a unique code verifiable on a public page.
 */

if (defined('NOVAHIRE_CERTIFICATES')) return;
define('NOVAHIRE_CERTIFICATES', true);

function nh_cert_title($category) {
    return $category . ' Proficiency Certificate';
}

function nh_generate_cert_code() {
    // e.g. NH-4F9A-2C71
    return 'NH-' . strtoupper(bin2hex(random_bytes(2))) . '-' . strtoupper(bin2hex(random_bytes(2)));
}

/* ── Eligibility ───────────────────────────────────────────────────────────── */
/** Categories the user passed a quiz in but has not yet claimed a certificate for. */
function nh_user_eligible_categories($con, $user_id) {
    $passed = [];
    $stmt = mysqli_prepare($con, "SELECT DISTINCT category FROM user_quiz_status WHERE user_id = ? AND status = 'passed'");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($r = mysqli_fetch_assoc($res)) $passed[$r['category']] = true;
    mysqli_stmt_close($stmt);

    // remove categories already certified
    $stmt = mysqli_prepare($con, "SELECT DISTINCT category FROM certificates WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($r = mysqli_fetch_assoc($res)) unset($passed[$r['category']]);
    mysqli_stmt_close($stmt);

    return array_keys($passed);
}

function nh_user_certified_categories($con, $user_id) {
    $cats = [];
    $stmt = mysqli_prepare($con, "SELECT DISTINCT category FROM certificates WHERE user_id = ? AND is_paid = 1");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($r = mysqli_fetch_assoc($res)) $cats[] = $r['category'];
    mysqli_stmt_close($stmt);
    return $cats;
}

/* ── Issue / fulfil ────────────────────────────────────────────────────────── */
/**
 * Create a certificate row. If the user is Pro (or $free), it's issued paid/valid
 * immediately and returns ['status'=>'issued','id'=>..]. Otherwise it's created
 * unpaid and returns ['status'=>'payment','id'=>..] so the caller sends the user
 * to checkout with item_id = certificate id.
 */
function nh_issue_certificate($con, $user_id, $category, $score = null) {
    // guard: must have passed this category and not already have it
    if (!in_array($category, nh_user_eligible_categories($con, $user_id))) {
        return ['status' => 'ineligible', 'id' => null];
    }

    $is_pro = function_exists('is_user_pro') ? is_user_pro($con, $user_id) : false;
    $title  = nh_cert_title($category);
    $code   = nh_generate_cert_code();
    $paid   = $is_pro ? 1 : 0;

    $stmt = mysqli_prepare($con, "INSERT INTO certificates (user_id, category, title, cert_code, score, is_paid, issued_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    mysqli_stmt_bind_param($stmt, "isssii", $user_id, $category, $title, $code, $score, $paid);
    $ok = mysqli_stmt_execute($stmt);
    $cert_id = $ok ? mysqli_insert_id($con) : null;
    mysqli_stmt_close($stmt);
    if (!$ok) return ['status' => 'error', 'id' => null];

    if ($is_pro) {
        if (function_exists('create_notification')) {
            create_notification($con, 'user', $user_id, 'system', null,
                'Certificate issued 🎓', 'Your free <strong>' . htmlspecialchars($title) . '</strong> is ready (a NovaHire Pro perk).', 'system', 'certificates', $cert_id);
        }
        return ['status' => 'issued', 'id' => $cert_id];
    }
    return ['status' => 'payment', 'id' => $cert_id];
}

/** Called by fulfill_payment() when a certificate payment completes. */
function nh_mark_certificate_paid($con, $cert_id, $payment_id = null) {
    $stmt = mysqli_prepare($con, "UPDATE certificates SET is_paid = 1, payment_id = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "si", $payment_id, $cert_id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($ok) {
        $cert = nh_get_certificate($con, $cert_id);
        if ($cert && function_exists('create_notification')) {
            create_notification($con, 'user', $cert['user_id'], 'system', null,
                'Certificate issued 🎓', 'Your <strong>' . htmlspecialchars($cert['title']) . '</strong> is verified and ready to share.', 'system', 'certificates', $cert_id);
        }
    }
    return $ok;
}

/* ── Lookups ───────────────────────────────────────────────────────────────── */
function nh_get_certificate($con, $cert_id) {
    $stmt = mysqli_prepare($con, "SELECT * FROM certificates WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "i", $cert_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

function nh_get_user_certificates($con, $user_id) {
    $stmt = mysqli_prepare($con, "SELECT * FROM certificates WHERE user_id = ? ORDER BY issued_at DESC");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;
    mysqli_stmt_close($stmt);
    return $rows;
}

/** Public verification: returns the certificate + holder name, only if valid (paid). */
function nh_get_certificate_by_code($con, $code) {
    $stmt = mysqli_prepare($con, "SELECT c.*, u.username AS holder_name FROM certificates c JOIN user_info u ON u.id = c.user_id WHERE c.cert_code = ? AND c.is_paid = 1 LIMIT 1");
    mysqli_stmt_bind_param($stmt, "s", $code);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

/* ── Revenue (admin) ───────────────────────────────────────────────────────── */
function nh_certificate_revenue($con) {
    $out = ['issued' => 0, 'paid_count' => 0, 'revenue' => 0.0];
    $price = nh_pricing()['certificate_price'];
    $r = @mysqli_query($con, "SELECT COUNT(*) issued, SUM(is_paid = 1 AND payment_id IS NOT NULL) paid_count FROM certificates");
    if ($r && ($row = mysqli_fetch_assoc($r))) {
        $out['issued'] = (int)$row['issued'];
        $out['paid_count'] = (int)$row['paid_count'];
        $out['revenue'] = $out['paid_count'] * $price;
    }
    return $out;
}
?>
