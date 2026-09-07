<?php
/**
 * NovaHire — My Certificates (seeker)
 * Claim verified skill certificates for quizzes you've passed.
 * Free for Pro members; a one-off fee otherwise (routed through unified checkout).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
if (!isset($_SESSION['id'])) { header('location: ' . BASE_URL . '/auth/login.php'); exit(); }

$user_id = $_SESSION['id'];

/* ── Claim a certificate (POST → redirect, so refresh is safe) ─────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    if (isset($_POST['claim'])) {
        $cat = trim($_POST['category'] ?? '');
        $res = nh_issue_certificate($con, $user_id, $cat);
        if ($res['status'] === 'payment') {
            header('Location: ' . BASE_URL . '/api/checkout.php?purpose=certificate&item_id=' . (int)$res['id']);
            exit;
        } elseif ($res['status'] === 'issued') {
            header('Location: certificates.php?issued=1');
            exit;
        }
        header('Location: certificates.php?err=' . urlencode($res['status']));
        exit;
    }
}

require_once __DIR__ . '/../includes/header.php';

$certs    = nh_get_user_certificates($con, $user_id);
$eligible = nh_user_eligible_categories($con, $user_id);
$is_pro   = is_user_pro($con, $user_id);
$price    = nh_pricing()['certificate_price'];

$paid_certs    = array_filter($certs, fn($c) => $c['is_paid']);
$pending_certs = array_filter($certs, fn($c) => !$c['is_paid']);

$verify_base = BASE_URL . '/includes/verify_certificate.php?code=';
// Absolute origin for shareable links (works whether BASE_URL is absolute or a path).
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$origin = (strpos(BASE_URL, 'http') === 0) ? '' : $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
?>
<style>
  .cert-wrap{max-width:1080px;margin:0 auto;padding:0 20px 60px}
  .cert-head{margin-bottom:8px}
  .cert-head h1{font-weight:800;color:var(--text);font-size:1.8rem;letter-spacing:-.5px}
  .cert-head p{color:var(--text-muted);margin:0}
  .ok-banner,.warn-banner{padding:15px 20px;border-radius:var(--radius-lg);margin:18px 0;display:flex;gap:12px;align-items:center;font-weight:600}
  .ok-banner{background:rgba(5,150,105,.12);border:1px solid rgba(5,150,105,.3);color:#047857}
  .warn-banner{background:rgba(217,119,6,.1);border:1px solid rgba(217,119,6,.3);color:#b45309}
  .sec-t{font-weight:700;color:var(--text);font-size:1.12rem;margin:34px 0 14px;display:flex;align-items:center;gap:10px}
  .sec-t .n{background:var(--bg-hover);color:var(--text-muted);font-size:.75rem;font-weight:800;padding:2px 10px;border-radius:99px}

  /* claim cards */
  .claim-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px}
  .claim{background:var(--bg-card);border:1px dashed var(--primary);border-radius:var(--radius-lg);padding:20px;position:relative;overflow:hidden}
  .claim::before{content:'';position:absolute;top:-40px;right:-40px;width:120px;height:120px;border-radius:50%;background:radial-gradient(circle,rgba(26,86,219,.1),transparent 70%)}
  .claim h4{font-weight:700;color:var(--text);font-size:1.05rem;margin-bottom:4px}
  .claim .sub{color:var(--text-muted);font-size:.85rem;margin-bottom:16px}
  .claim .row2{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-top:6px}
  .price-pill{font-weight:800;color:var(--text)}
  .price-pill.free{color:#059669}
  .price-pill small{display:block;font-weight:600;color:var(--text-muted);font-size:.7rem}
  .btn-claim{background:linear-gradient(135deg,#1a56db,#0ea5e9);color:#fff;border:none;font-weight:700;border-radius:var(--radius-full);padding:10px 20px;cursor:pointer;font-size:.88rem;white-space:nowrap}
  .btn-claim:hover{opacity:.93}

  /* certificate cards */
  .cert-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:18px}
  .cert{background:var(--bg-card);border:1px solid var(--border-light);border-radius:var(--radius-lg);box-shadow:var(--shadow-xs);overflow:hidden;transition:.18s}
  .cert:hover{transform:translateY(-3px);box-shadow:var(--shadow-lg)}
  .cert-top{background:linear-gradient(135deg,#1a56db,#0ea5e9);color:#fff;padding:20px;position:relative}
  .cert-top .seal{position:absolute;top:16px;right:16px;width:40px;height:40px;border-radius:50%;background:rgba(255,255,255,.18);display:grid;place-items:center;font-size:1rem}
  .cert-top .cat{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.6px;opacity:.85}
  .cert-top h3{font-weight:800;font-size:1.18rem;margin:6px 0 0;line-height:1.25}
  .cert-body{padding:16px 20px 20px}
  .cert-meta{display:flex;gap:18px;color:var(--text-muted);font-size:.82rem;margin-bottom:14px;flex-wrap:wrap}
  .cert-meta b{color:var(--text)}
  .cert-code{display:flex;align-items:center;gap:8px;background:var(--bg-hover);border-radius:10px;padding:9px 12px;font-family:monospace;font-weight:700;color:var(--text);letter-spacing:1px;margin-bottom:14px}
  .cert-code .cp{margin-left:auto;background:none;border:none;color:var(--primary);cursor:pointer;font-size:.9rem}
  .cert-acts{display:flex;gap:8px;flex-wrap:wrap}
  .cert-acts a,.cert-acts button{flex:1;text-align:center;border-radius:var(--radius-full);padding:9px 12px;font-weight:700;font-size:.82rem;text-decoration:none;cursor:pointer;border:1px solid var(--border);background:var(--bg-card);color:var(--text)}
  .cert-acts a.primary{background:linear-gradient(135deg,#1a56db,#0ea5e9);color:#fff;border-color:transparent}
  .cert-acts a:hover,.cert-acts button:hover{border-color:var(--primary);color:var(--primary)}
  .cert-acts a.primary:hover{color:#fff;opacity:.93}
  .pending-badge{background:rgba(217,119,6,.15);color:#b45309;font-size:.72rem;font-weight:800;padding:3px 10px;border-radius:99px}

  .empty{text-align:center;padding:56px 20px;color:var(--text-muted)}
  .empty i{font-size:2.6rem;color:var(--text-light);margin-bottom:14px}
  .pro-nudge{background:linear-gradient(135deg,#fffbeb,#fef3c7);border:1px solid #fde68a;border-radius:var(--radius-lg);padding:16px 20px;margin-top:22px;display:flex;gap:14px;align-items:center}
  [data-theme=dark] .pro-nudge{background:#422006;border-color:#78350f}
  .pro-nudge i{font-size:1.5rem;color:#d97706}
  .pro-nudge .txt{flex:1;color:var(--text);font-weight:500;font-size:.9rem}
  .pro-nudge a{background:linear-gradient(135deg,#d97706,#f97316);color:#fff;font-weight:700;border-radius:var(--radius-full);padding:9px 18px;text-decoration:none;white-space:nowrap;font-size:.85rem}
</style>

<div class="cert-wrap">
  <div class="cert-head">
    <h1><i class="fas fa-award mr-2" style="color:var(--primary)"></i>My Certificates</h1>
    <p>Turn the quizzes you've passed into verified, shareable credentials employers can trust.</p>
  </div>

  <?php if (isset($_GET['issued'])): ?>
    <div class="ok-banner"><i class="fas fa-circle-check fa-lg"></i> Your certificate is issued and verified. Share your unique code with employers anytime.</div>
  <?php endif; ?>
  <?php if (isset($_GET['err'])): ?>
    <div class="warn-banner"><i class="fas fa-triangle-exclamation fa-lg"></i>
      <?= $_GET['err']==='ineligible' ? 'You need to pass that category\'s quiz first, or you\'ve already claimed it.' : 'Something went wrong issuing that certificate. Please try again.' ?>
    </div>
  <?php endif; ?>

  <!-- Claimable -->
  <?php if (!empty($eligible)): ?>
    <div class="sec-t"><i class="fas fa-unlock-keyhole" style="color:#d97706"></i>Ready to claim <span class="n"><?= count($eligible) ?></span></div>
    <div class="claim-grid">
      <?php foreach ($eligible as $cat): ?>
        <div class="claim">
          <h4><?= htmlspecialchars($cat) ?></h4>
          <div class="sub"><?= htmlspecialchars(nh_cert_title($cat)) ?></div>
          <form method="POST" class="row2">
            <?= csrf_field() ?>
            <input type="hidden" name="category" value="<?= htmlspecialchars($cat) ?>">
            <?php if ($is_pro): ?>
              <span class="price-pill free">Free<small>Pro perk</small></span>
            <?php else: ?>
              <span class="price-pill"><?= nh_price($price) ?><small>one-time</small></span>
            <?php endif; ?>
            <button class="btn-claim" name="claim" value="1">
              <i class="fas fa-<?= $is_pro ? 'download' : 'lock' ?> mr-1"></i><?= $is_pro ? 'Get certificate' : 'Unlock' ?>
            </button>
          </form>
        </div>
      <?php endforeach; ?>
    </div>
    <?php if (!$is_pro): ?>
      <div class="pro-nudge">
        <i class="fas fa-crown"></i>
        <div class="txt"><strong>Go Pro to get every certificate free.</strong> Pro members claim unlimited verified certificates at no extra cost, plus priority ranking with employers.</div>
        <a href="pro.php">Upgrade to Pro</a>
      </div>
    <?php endif; ?>
  <?php endif; ?>

  <!-- Issued / owned -->
  <?php if (!empty($paid_certs)): ?>
    <div class="sec-t"><i class="fas fa-certificate" style="color:var(--primary)"></i>Your credentials <span class="n"><?= count($paid_certs) ?></span></div>
    <div class="cert-grid">
      <?php foreach ($paid_certs as $c): $vurl = $verify_base . urlencode($c['cert_code']); $share = $origin . $vurl; ?>
        <div class="cert">
          <div class="cert-top">
            <div class="seal"><i class="fas fa-award"></i></div>
            <div class="cat"><?= htmlspecialchars($c['category']) ?></div>
            <h3><?= htmlspecialchars($c['title']) ?></h3>
          </div>
          <div class="cert-body">
            <div class="cert-meta">
              <span>Issued <b><?= date('M j, Y', strtotime($c['issued_at'])) ?></b></span>
              <?php if (!is_null($c['score'])): ?><span>Score <b><?= (int)$c['score'] ?>%</b></span><?php endif; ?>
              <span>Holder <b><?= htmlspecialchars($_SESSION['username']) ?></b></span>
            </div>
            <div class="cert-code">
              <i class="fas fa-shield-halved" style="color:#059669"></i><?= htmlspecialchars($c['cert_code']) ?>
              <button class="cp" title="Copy code" onclick="navigator.clipboard.writeText('<?= htmlspecialchars($c['cert_code']) ?>');this.innerHTML='<i class=\'fas fa-check\'></i>'"><i class="far fa-copy"></i></button>
            </div>
            <div class="cert-acts">
              <a class="primary" href="<?= htmlspecialchars($vurl) ?>" target="_blank" rel="noopener"><i class="fas fa-badge-check mr-1"></i>View / Verify</a>
              <button onclick="navigator.clipboard.writeText('<?= htmlspecialchars($share) ?>');this.innerHTML='<i class=\'fas fa-check mr-1\'></i>Link copied'"><i class="fas fa-share-nodes mr-1"></i>Share link</button>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <!-- Pending payment -->
  <?php if (!empty($pending_certs)): ?>
    <div class="sec-t"><i class="fas fa-hourglass-half" style="color:#d97706"></i>Awaiting payment <span class="n"><?= count($pending_certs) ?></span></div>
    <div class="cert-grid">
      <?php foreach ($pending_certs as $c): ?>
        <div class="cert">
          <div class="cert-top" style="background:linear-gradient(135deg,#94a3b8,#64748b)">
            <div class="seal"><i class="fas fa-hourglass-half"></i></div>
            <div class="cat"><?= htmlspecialchars($c['category']) ?> <span class="pending-badge" style="margin-left:6px">UNPAID</span></div>
            <h3><?= htmlspecialchars($c['title']) ?></h3>
          </div>
          <div class="cert-body">
            <p style="color:var(--text-muted);font-size:.86rem;margin-bottom:14px">Complete the one-time payment to activate this certificate and get a verifiable code.</p>
            <div class="cert-acts">
              <a class="primary" href="<?= BASE_URL ?>/api/checkout.php?purpose=certificate&item_id=<?= (int)$c['id'] ?>"><i class="fas fa-lock mr-1"></i>Pay <?= nh_price($price) ?> &amp; activate</a>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <!-- Empty state -->
  <?php if (empty($eligible) && empty($certs)): ?>
    <div class="empty">
      <i class="fas fa-award d-block"></i>
      <h5 style="color:var(--text);font-weight:700">No certificates yet</h5>
      <p>Pass a skill quiz in the grooming center to unlock your first verified certificate.</p>
      <a href="grooming.php" class="btn btn-primary rounded-pill px-4">Take a skill quiz</a>
    </div>
  <?php endif; ?>
</div>
