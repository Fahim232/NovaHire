<?php
require_once __DIR__ . '/../includes/bootstrap.php';
if (!isset($_SESSION['id'])) { header('location: ' . BASE_URL . '/auth/login.php'); exit(); }
require_once __DIR__ . '/../includes/header.php';

$user_id = $_SESSION['id'];

require_once __DIR__ . '/../includes/premium.php';
$access = nh_check_access($con, $user_id, 'recommendations');
if (!$access['allowed']) {
    nh_render_pro_gate('recommendations');
    exit;
}

$uq = mysqli_query($con, "SELECT * FROM user_info WHERE id = " . (int)$user_id);
$user = mysqli_fetch_assoc($uq);

$has_skills = !empty(trim((string)$user['user_skills']));
$recs = $has_skills ? nh_get_recommendations($con, $user, 12) : [];

function rc_color($s){ return $s>=70?'#059669':($s>=40?'#d97706':'#dc2626'); }
?>
<style>
  .rec-wrap{max-width:1100px;margin:0 auto;padding:0 20px 60px}
  .rec-head{display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:14px;margin-bottom:8px}
  .rec-head h1{font-weight:800;color:var(--text);font-size:1.8rem;letter-spacing:-.5px}
  .rec-head p{color:var(--text-muted);margin:0}
  .rec-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(330px,1fr));gap:18px;margin-top:22px}
  .jc{background:var(--bg-card);border:1px solid var(--border-light);border-radius:var(--radius-lg);padding:20px;box-shadow:var(--shadow-xs);position:relative;transition:.18s;display:flex;flex-direction:column}
  .jc:hover{transform:translateY(-3px);box-shadow:var(--shadow-lg);border-color:var(--primary)}
  .jc.feat{border-color:#d97706;box-shadow:0 0 0 1px #d9770622}
  .feat-tag{position:absolute;top:-10px;right:16px;background:linear-gradient(135deg,#d97706,#f97316);color:#fff;font-size:.68rem;font-weight:800;padding:4px 11px;border-radius:20px;letter-spacing:.4px}
  .jc-top{display:flex;gap:13px;align-items:flex-start}
  .jc-logo{width:46px;height:46px;border-radius:12px;background:linear-gradient(135deg,var(--primary),var(--secondary));color:#fff;display:grid;place-items:center;font-weight:800;font-size:1.1rem;flex-shrink:0}
  .jc-title{font-weight:700;color:var(--text);font-size:1.05rem;line-height:1.3}
  .jc-co{color:var(--text-muted);font-size:.85rem}
  .jc-ring{margin-left:auto;--v:0;width:52px;height:52px;border-radius:50%;display:grid;place-items:center;font-weight:800;font-size:.82rem;color:var(--text);background:conic-gradient(var(--rc) calc(var(--v)*1%),var(--border-light) 0);flex-shrink:0}
  .jc-ring span{width:40px;height:40px;border-radius:50%;background:var(--bg-card);display:grid;place-items:center}
  .jc-label{font-size:.76rem;font-weight:700;margin:14px 0 6px}
  .sk{display:flex;flex-wrap:wrap;gap:5px}
  .sk .s{font-size:.72rem;font-weight:600;padding:3px 9px;border-radius:var(--radius-full)}
  .s.match{background:rgba(5,150,105,.12);color:#059669}
  .s.miss{background:rgba(217,119,6,.12);color:#b45309}
  .jc-foot{margin-top:auto;padding-top:16px;display:flex;gap:8px;align-items:center}
  .banner{background:linear-gradient(135deg,#eef2ff,#f5f3ff);border:1px solid #e0e7ff;border-radius:var(--radius-lg);padding:16px 20px;margin-top:18px;display:flex;gap:12px;align-items:center;color:#1e40af;font-weight:500}
  [data-theme=dark] .banner{background:#1e1b4b;border-color:#0c1222;color:#c7d2fe}
  .empty{text-align:center;padding:60px 20px;color:var(--text-muted)}
  .empty i{font-size:2.6rem;color:var(--text-light);margin-bottom:14px}
</style>

<div class="rec-wrap">
  <div class="rec-head">
    <div>
      <h1><i class="fas fa-wand-magic-sparkles mr-2" style="color:var(--primary)"></i>Recommended for you</h1>
      <p>Jobs ranked by how well they match your skills, experience and profile.</p>
    </div>
  </div>

  <?php if (!$has_skills): ?>
    <div class="empty">
      <i class="fas fa-user-pen d-block"></i>
      <h5 style="color:var(--text);font-weight:700">Add your skills to unlock recommendations</h5>
      <p>Update your profile with your skills and we'll match you to the best jobs.</p>
      <a href="profile.php" class="btn btn-primary rounded-pill px-4">Complete profile</a>
    </div>
  <?php elseif (empty($recs)): ?>
    <div class="empty">
      <i class="fas fa-briefcase d-block"></i>
      <h5 style="color:var(--text);font-weight:700">No open jobs to match right now</h5>
      <p>Check back soon — new roles are posted regularly.</p>
      <a href="browse_jobs.php" class="btn btn-primary rounded-pill px-4">Browse all jobs</a>
    </div>
  <?php else: ?>
    <div class="banner"><i class="fas fa-circle-info fa-lg"></i> Your top match is <strong><?= (int)$recs[0]['ai']['score'] ?>%</strong> — apply early to stand out. Missing skills? Verify them in the <a href="grooming.php" style="text-decoration:underline;color:inherit">grooming center</a>.</div>

    <div class="rec-grid">
      <?php foreach ($recs as $j):
        $ai = $j['ai']; $score = (int)$ai['score'];
        $matched = array_slice($ai['matched_skills'] ?? [], 0, 4);
        $missing = array_slice($ai['missing_skills'] ?? [], 0, 3);
        $feat = nh_is_job_featured($j);
      ?>
      <div class="jc <?= $feat?'feat':'' ?>">
        <?php if ($feat): ?><span class="feat-tag"><i class="fas fa-bolt"></i> FEATURED</span><?php endif; ?>
        <div class="jc-top">
          <div class="jc-logo"><?= htmlspecialchars(strtoupper(substr($j['company_name'],0,1))) ?></div>
          <div>
            <div class="jc-title"><?= htmlspecialchars($j['job_title']) ?></div>
            <div class="jc-co"><?= htmlspecialchars($j['company_name']) ?><?= !empty($j['job_location'])?' · '.htmlspecialchars($j['job_location']):'' ?></div>
          </div>
          <div class="jc-ring" style="--v:<?= $score ?>;--rc:<?= rc_color($score) ?>"><span><?= $score ?>%</span></div>
        </div>

        <div class="jc-label" style="color:<?= htmlspecialchars($ai['label_color'] ?? 'var(--primary)') ?>"><?= htmlspecialchars($ai['label'] ?? '') ?></div>
        <div class="sk">
          <?php foreach ($matched as $s): ?><span class="s match"><i class="fas fa-check mr-1"></i><?= htmlspecialchars($s) ?></span><?php endforeach; ?>
          <?php foreach ($missing as $s): ?><span class="s miss"><i class="fas fa-plus mr-1"></i><?= htmlspecialchars($s) ?></span><?php endforeach; ?>
        </div>

        <div class="jc-foot">
          <?php if (!empty($j['already_applied'])): ?>
            <span class="btn btn-sm btn-outline-success rounded-pill px-3" style="pointer-events:none"><i class="fas fa-check mr-1"></i>Applied</span>
            <a href="job_details.php?id=<?= (int)$j['id'] ?>" class="btn btn-sm btn-link ml-auto">View</a>
          <?php else: ?>
            <a href="job_details.php?id=<?= (int)$j['id'] ?>" class="btn btn-sm btn-primary rounded-pill px-4">View &amp; Apply</a>
            <span class="ml-auto" style="font-size:.78rem;color:var(--text-muted)"><?= count($matched) ?> skills match</span>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
