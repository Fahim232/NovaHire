<?php
/**
 * NovaHire — Unified Checkout
 * ---------------------------------------------------------------------------
 * One checkout for every paid action on the platform:
 *   pro_subscription · session · certificate · featured_job · subscription (company)
 *
 * Amounts and ownership are resolved SERVER-SIDE (never trust the client).
 * In demo mode (no live gateway keys) the payment auto-completes and fulfils,
 * so the whole business model is demonstrable offline.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

$purpose = $_GET['purpose'] ?? $_POST['purpose'] ?? '';
$error   = null;

/* ── Resolve a validated checkout context ──────────────────────────────────── */
function checkout_context($con, $purpose) {
    $p = nh_pricing();

    switch ($purpose) {
        case 'pro_subscription':
            if (!isset($_SESSION['id'])) return ['error' => 'login_user'];
            return [
                'payer_type' => 'user', 'payer_id' => (int)$_SESSION['id'],
                'amount' => $p['pro_price'], 'item_id' => null, 'plan_type' => 'pro',
                'title' => 'NovaHire Pro — Monthly',
                'desc'  => 'Unlimited AI tools, priority ranking, free certificates & Pro badge for ' . $p['pro_duration_days'] . ' days.',
                'cancel' => BASE_URL . '/seeker/pro.php',
            ];

        case 'certificate':
            if (!isset($_SESSION['id'])) return ['error' => 'login_user'];
            $cid = (int)($_GET['item_id'] ?? $_POST['item_id'] ?? 0);
            $cert = nh_get_certificate($con, $cid);
            if (!$cert || $cert['user_id'] != $_SESSION['id']) return ['error' => 'not_found'];
            if ($cert['is_paid']) return ['error' => 'already_done', 'redirect' => BASE_URL . '/seeker/certificates.php'];
            return [
                'payer_type' => 'user', 'payer_id' => (int)$_SESSION['id'],
                'amount' => $p['certificate_price'], 'item_id' => $cid, 'plan_type' => null,
                'title' => $cert['title'],
                'desc'  => 'Verified, shareable certificate with a unique verification code.',
                'cancel' => BASE_URL . '/seeker/certificates.php',
            ];

        case 'session':
            if (!isset($_SESSION['id'])) return ['error' => 'login_user'];
            $sid = (int)($_GET['item_id'] ?? $_POST['item_id'] ?? 0);
            $sess = nh_get_session($con, $sid);
            if (!$sess || $sess['user_id'] != $_SESSION['id']) return ['error' => 'not_found'];
            if ($sess['status'] !== 'pending_payment') return ['error' => 'already_done', 'redirect' => BASE_URL . '/seeker/my_sessions.php'];
            return [
                'payer_type' => 'user', 'payer_id' => (int)$_SESSION['id'],
                'amount' => $sess['price'], 'item_id' => $sid, 'plan_type' => $sess['session_type'],
                'title' => nh_session_type_label($sess['session_type']) . ' with ' . $sess['mentor_name'],
                'desc'  => date('l, M j, Y \a\t g:i A', strtotime($sess['scheduled_at'])) . ' · ' . (int)$sess['duration_min'] . ' min · live video.',
                'cancel' => BASE_URL . '/seeker/mentors.php',
            ];

        case 'featured_job':
            if (!isset($_SESSION['company_id'])) return ['error' => 'login_company'];
            $jid = (int)($_GET['item_id'] ?? $_POST['item_id'] ?? 0);
            $stmt = mysqli_prepare($con, "SELECT id, job_title, company_id FROM company_jobs WHERE id = ? LIMIT 1");
            mysqli_stmt_bind_param($stmt, "i", $jid);
            mysqli_stmt_execute($stmt);
            $job = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);
            if (!$job || $job['company_id'] != $_SESSION['company_id']) return ['error' => 'not_found'];
            return [
                'payer_type' => 'company', 'payer_id' => (int)$_SESSION['company_id'],
                'amount' => $p['featured_price'], 'item_id' => $jid, 'plan_type' => null,
                'title' => 'Feature job: ' . $job['job_title'],
                'desc'  => 'Pin this job to the top of search & recommendations for ' . $p['featured_days'] . ' days.',
                'cancel' => BASE_URL . '/company/my_jobs.php',
            ];

        case 'subscription': // company plan (reuses existing plan catalogue)
            if (!isset($_SESSION['company_id'])) return ['error' => 'login_company'];
            $plan = $_GET['plan_type'] ?? $_POST['plan_type'] ?? '';
            $plans = function_exists('get_subscription_plans') ? get_subscription_plans() : [];
            if (!isset($plans[$plan]) || $plan === 'free') return ['error' => 'not_found'];
            return [
                'payer_type' => 'company', 'payer_id' => (int)$_SESSION['company_id'],
                'amount' => $plans[$plan]['price'], 'item_id' => null, 'plan_type' => $plan,
                'title' => $plans[$plan]['name'] . ' Plan',
                'desc'  => 'Employer subscription · ' . (int)$plans[$plan]['duration'] . ' days.',
                'cancel' => BASE_URL . '/company/subscription.php',
            ];
    }
    return ['error' => 'bad_purpose'];
}

$ctx = checkout_context($con, $purpose);

if (isset($ctx['error'])) {
    switch ($ctx['error']) {
        case 'login_user':    header('Location: ' . BASE_URL . '/auth/login.php'); exit;
        case 'login_company': header('Location: ' . BASE_URL . '/auth/login.php'); exit;
        case 'already_done':  header('Location: ' . ($ctx['redirect'] ?? BASE_URL)); exit;
        default:              $error = 'This checkout link is invalid or has expired.';
    }
}

/* ── Handle payment confirmation (POST) ────────────────────────────────────── */
if (!$error && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Your session expired. Please refresh and try again.';
    } else {
        $method = $_POST['method'] ?? 'sslcommerz';
        $payment_id = create_payment($con, $ctx['payer_type'], $ctx['payer_id'], $purpose, $ctx['amount'], [
            'item_id'   => $ctx['item_id'],
            'plan_type' => $ctx['plan_type'],
            'method'    => $method,
        ]);
        if (!$payment_id) {
            $error = 'We could not start your payment. Please try again.';
        } elseif (!nh_gateway_live()) {
            // DEMO MODE: complete + fulfil immediately.
            mark_payment_completed($con, $payment_id);
            $payment = get_payment($con, $payment_id);
            $result  = fulfill_payment($con, $payment);
            header('Location: ' . $result['redirect']);
            exit;
        } else {
            // LIVE MODE: hand off to the real gateway (SSLCommerz/bKash).
            header('Location: ' . BASE_URL . '/api/initiate_payment.php?payment_id=' . urlencode($payment_id));
            exit;
        }
    }
}

$demo = !nh_gateway_live();
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Checkout · NovaHire</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
  :root{--brand:#1a56db;--brand2:#0ea5e9;--ink:#0f172a;--muted:#64748b;--line:#e2e8f0;--bg:#f1f5f9;}
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Inter',system-ui,sans-serif;background:var(--bg);color:var(--ink);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
  .card{background:#fff;border-radius:20px;box-shadow:0 20px 60px rgba(15,23,42,.12);width:100%;max-width:440px;overflow:hidden}
  .head{background:linear-gradient(135deg,var(--brand),var(--brand2));color:#fff;padding:28px 28px 22px}
  .brand{display:flex;align-items:center;gap:9px;font-weight:800;font-size:1.05rem;letter-spacing:.2px}
  .brand i{background:rgba(255,255,255,.2);width:32px;height:32px;border-radius:9px;display:grid;place-items:center}
  .head h1{font-size:1.15rem;margin-top:16px;font-weight:700}
  .head p{opacity:.9;font-size:.9rem;margin-top:6px;line-height:1.5}
  .body{padding:24px 28px 28px}
  .row{display:flex;justify-content:space-between;align-items:center;padding:12px 0;border-bottom:1px solid var(--line)}
  .row:last-of-type{border-bottom:none}
  .row .k{color:var(--muted);font-size:.9rem}
  .row .v{font-weight:600}
  .total{display:flex;justify-content:space-between;align-items:baseline;margin:18px 0 8px}
  .total .lbl{font-weight:600}
  .total .amt{font-size:1.9rem;font-weight:800;color:var(--brand)}
  .methods{display:flex;gap:8px;margin:16px 0 4px}
  .m{flex:1;border:1.5px solid var(--line);border-radius:11px;padding:10px;text-align:center;cursor:pointer;font-size:.8rem;font-weight:600;color:var(--muted);transition:.15s}
  .m.active{border-color:var(--brand);color:var(--brand);background:#eef2ff}
  .m i{display:block;font-size:1.1rem;margin-bottom:4px}
  .pay{width:100%;margin-top:18px;background:linear-gradient(135deg,var(--brand),var(--brand2));color:#fff;border:none;padding:15px;border-radius:12px;font-size:1rem;font-weight:700;cursor:pointer;font-family:inherit;transition:.15s;display:flex;align-items:center;justify-content:center;gap:9px}
  .pay:hover{transform:translateY(-1px);box-shadow:0 10px 24px rgba(26,86,219,.35)}
  .demo{margin-top:14px;font-size:.78rem;color:var(--muted);text-align:center;line-height:1.5}
  .demo b{color:#059669}
  .cancel{display:block;text-align:center;margin-top:14px;color:var(--muted);text-decoration:none;font-size:.85rem}
  .cancel:hover{color:var(--ink)}
  .err{background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;padding:12px 14px;border-radius:11px;font-size:.88rem;margin-bottom:16px}
  .secure{display:flex;align-items:center;justify-content:center;gap:6px;margin-top:16px;color:var(--muted);font-size:.76rem}
</style>
</head>
<body>
  <div class="card">
    <div class="head">
      <div class="brand"><i class="fas fa-briefcase"></i> NovaHire</div>
      <?php if (!$error): ?>
        <h1><?= htmlspecialchars($ctx['title']) ?></h1>
        <p><?= htmlspecialchars($ctx['desc']) ?></p>
      <?php else: ?>
        <h1>Checkout unavailable</h1>
      <?php endif; ?>
    </div>
    <div class="body">
      <?php if ($error): ?>
        <div class="err"><i class="fas fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
        <a class="cancel" href="<?= htmlspecialchars(BASE_URL) ?>/index.php">← Back to NovaHire</a>
      <?php else: ?>
        <div class="row"><span class="k">Item</span><span class="v"><?= htmlspecialchars($ctx['title']) ?></span></div>
        <div class="row"><span class="k">Billed to</span><span class="v"><?= $ctx['payer_type'] === 'user' ? 'Your account' : 'Your company' ?></span></div>
        <div class="total"><span class="lbl">Total due</span><span class="amt"><?= nh_price($ctx['amount']) ?></span></div>

        <form method="POST" action="<?= htmlspecialchars(BASE_URL) ?>/api/checkout.php">
          <?= csrf_field() ?>
          <input type="hidden" name="purpose" value="<?= htmlspecialchars($purpose) ?>">
          <?php if ($ctx['item_id'] !== null): ?><input type="hidden" name="item_id" value="<?= (int)$ctx['item_id'] ?>"><?php endif; ?>
          <?php if ($ctx['plan_type'] !== null): ?><input type="hidden" name="plan_type" value="<?= htmlspecialchars($ctx['plan_type']) ?>"><?php endif; ?>
          <input type="hidden" name="method" id="method" value="sslcommerz">

          <div class="methods">
            <div class="m active" data-m="sslcommerz"><i class="fas fa-credit-card"></i>Card</div>
            <div class="m" data-m="bkash"><i class="fas fa-mobile-screen"></i>bKash</div>
            <div class="m" data-m="nagad"><i class="fas fa-wallet"></i>Nagad</div>
          </div>

          <button type="submit" class="pay"><i class="fas fa-lock"></i> Pay <?= nh_price($ctx['amount']) ?></button>
        </form>

        <?php if ($demo): ?>
          <p class="demo"><b>Demo mode</b> — no real charge. This simulates a successful gateway payment so you can experience the full flow.</p>
        <?php endif; ?>
        <a class="cancel" href="<?= htmlspecialchars($ctx['cancel']) ?>">← Cancel and go back</a>
        <div class="secure"><i class="fas fa-shield-halved"></i> Secured checkout · BDT</div>
      <?php endif; ?>
    </div>
  </div>
<script>
  document.querySelectorAll('.m').forEach(function(el){
    el.addEventListener('click', function(){
      document.querySelectorAll('.m').forEach(x=>x.classList.remove('active'));
      el.classList.add('active');
      document.getElementById('method').value = el.getAttribute('data-m');
    });
  });
</script>
</body>
</html>
