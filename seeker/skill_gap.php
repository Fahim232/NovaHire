<?php
/**
 * NovaHire — Skill Gap Analyzer (seeker)
 * ---------------------------------------------------------------------------
 * Aggregates the AI match data across every open job to show a seeker exactly
 * which missing skills are holding them back — ranked by how many jobs each
 * skill would unlock — plus their current strengths and readiness. Every gap
 * links to the ways NovaHire helps close it (grooming quizzes, certificates,
 * 1:1 mentor sessions), which is where the platform earns.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
if (!isset($_SESSION['id'])) { header('location: ' . BASE_URL . '/auth/login.php'); exit(); }
require_once __DIR__ . '/../includes/header.php';

$user_id = $_SESSION['id'];

require_once __DIR__ . '/../includes/premium.php';
$access = nh_check_access($con, $user_id, 'skill_gap');
if (!$access['allowed']) {
    nh_render_pro_gate('skill_gap');
    exit;
}

$uq = mysqli_query($con, "SELECT * FROM user_info WHERE id = " . (int)$user_id);
$user = mysqli_fetch_assoc($uq);
$has_skills = !empty(trim((string)$user['user_skills']));

$recs = $has_skills ? nh_get_recommendations($con, $user, 100) : [];
$job_count = count($recs);

$gaps = [];       // key => ['label'=>, 'count'=>, 'jobs'=>[]]
$strengths = [];  // key => ['label'=>, 'count'=>]
$score_sum = 0;
foreach ($recs as $j) {
    $score_sum += (int)$j['ai']['score'];
    foreach (($j['ai']['missing_skills'] ?? []) as $ms) {
        $k = strtolower(trim($ms)); if ($k === '') continue;
        if (!isset($gaps[$k])) $gaps[$k] = ['label' => $ms, 'count' => 0, 'jobs' => []];
        $gaps[$k]['count']++;
        if (count($gaps[$k]['jobs']) < 3) $gaps[$k]['jobs'][] = $j['job_title'];
    }
    foreach (($j['ai']['matched_skills'] ?? []) as $mk) {
        $k = strtolower(trim($mk)); if ($k === '') continue;
        if (!isset($strengths[$k])) $strengths[$k] = ['label' => $mk, 'count' => 0];
        $strengths[$k]['count']++;
    }
}
uasort($gaps, fn($a,$b) => $b['count'] <=> $a['count']);
uasort($strengths, fn($a,$b) => $b['count'] <=> $a['count']);
$top_gaps = array_slice($gaps, 0, 8, true);
$top_strengths = array_slice($strengths, 0, 8, true);
$avg = $job_count ? round($score_sum / $job_count) : 0;

// categories the user could certify now (turns a closed gap into a credential)
$eligible = nh_user_eligible_categories($con, $user_id);

function sg_ring($v){ return $v>=70?'#059669':($v>=40?'#d97706':'#dc2626'); }
$max_gap = $top_gaps ? reset($top_gaps)['count'] : 1;
?>
<style>
  .sg-wrap{max-width:1080px;margin:0 auto;padding:0 20px 60px}
  .sg-head h1{font-weight:800;color:var(--text);font-size:1.8rem;letter-spacing:-.5px}
  .sg-head p{color:var(--text-muted);margin:0}
  .sg-top{display:grid;grid-template-columns:220px 1fr;gap:22px;align-items:center;margin:24px 0 10px;background:var(--bg-card);border:1px solid var(--border-light);border-radius:var(--radius-xl);padding:26px;box-shadow:var(--shadow-xs)}
  @media(max-width:720px){.sg-top{grid-template-columns:1fr;text-align:center}}
  .sg-ring{--v:0;width:150px;height:150px;border-radius:50%;margin:0 auto;display:grid;place-items:center;background:conic-gradient(var(--rc) calc(var(--v)*1%),var(--border-light) 0)}
  .sg-ring span{width:120px;height:120px;border-radius:50%;background:var(--bg-card);display:grid;place-items:center;flex-direction:column;text-align:center}
  .sg-ring b{font-size:2.1rem;font-weight:800;color:var(--text);line-height:1}
  .sg-ring small{color:var(--text-muted);font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px}
  .sg-top h3{font-weight:800;color:var(--text);font-size:1.2rem;margin-bottom:6px}
  .sg-top p{color:var(--text-muted);font-size:.92rem;line-height:1.55}
  .cols{display:grid;grid-template-columns:1.4fr 1fr;gap:22px;margin-top:22px}
  @media(max-width:820px){.cols{grid-template-columns:1fr}}
  .panel{background:var(--bg-card);border:1px solid var(--border-light);border-radius:var(--radius-lg);padding:22px;box-shadow:var(--shadow-xs)}
  .panel h3{font-weight:700;color:var(--text);font-size:1.08rem;margin-bottom:6px;display:flex;align-items:center;gap:9px}
  .panel .hint{color:var(--text-muted);font-size:.85rem;margin-bottom:16px}
  .gap-row{margin-bottom:16px}
  .gap-row .gr-top{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:6px}
  .gap-row .name{font-weight:700;color:var(--text);text-transform:capitalize}
  .gap-row .cnt{font-size:.78rem;font-weight:700;color:#b45309;background:rgba(217,119,6,.12);padding:2px 9px;border-radius:99px;white-space:nowrap}
  .bar{height:8px;border-radius:99px;background:var(--border-light);overflow:hidden}
  .bar i{display:block;height:100%;border-radius:99px;background:linear-gradient(90deg,#d97706,#f97316)}
  .gap-row .jobs{color:var(--text-light);font-size:.76rem;margin-top:5px}
  .str{display:flex;flex-wrap:wrap;gap:8px}
  .str .s{display:inline-flex;align-items:center;gap:6px;font-size:.8rem;font-weight:600;padding:6px 12px;border-radius:99px;background:rgba(5,150,105,.12);color:#059669;text-transform:capitalize}
  .str .s b{font-weight:800}
  .act-card{border:1px solid var(--border-light);border-radius:14px;padding:16px;display:flex;gap:14px;align-items:flex-start;margin-bottom:12px;transition:.15s;text-decoration:none}
  .act-card:hover{border-color:var(--primary);background:var(--bg-hover)}
  .act-card .ai{width:42px;height:42px;border-radius:12px;display:grid;place-items:center;font-size:1.1rem;color:#fff;flex-shrink:0}
  .act-card .t{font-weight:700;color:var(--text)}
  .act-card .d{color:var(--text-muted);font-size:.83rem;margin-top:2px}
  .act-card .go{margin-left:auto;color:var(--text-light);align-self:center}
  .empty{text-align:center;padding:56px 20px;color:var(--text-muted)}
  .empty i{font-size:2.6rem;color:var(--text-light);margin-bottom:14px}
</style>

<div class="sg-wrap">
  <div class="sg-head">
    <h1><i class="fas fa-chart-simple mr-2" style="color:var(--primary)"></i>Skill Gap Analyzer</h1>
    <p>See what's between you and more job matches — and the fastest way to close the gap.</p>
  </div>

  <?php if (!$has_skills): ?>
    <div class="empty">
      <i class="fas fa-user-pen d-block"></i>
      <h5 style="color:var(--text);font-weight:700">Add your skills first</h5>
      <p>We analyze your profile against every open role. Add your skills to see your gaps.</p>
      <a href="profile.php" class="btn btn-primary rounded-pill px-4">Complete profile</a>
    </div>
  <?php elseif (!$job_count): ?>
    <div class="empty">
      <i class="fas fa-briefcase d-block"></i>
      <h5 style="color:var(--text);font-weight:700">No open jobs to analyze yet</h5>
      <p>Check back soon — new roles are posted regularly.</p>
      <a href="browse_jobs.php" class="btn btn-primary rounded-pill px-4">Browse jobs</a>
    </div>
  <?php else: ?>

    <div class="sg-top">
      <div class="sg-ring" style="--v:<?= $avg ?>;--rc:<?= sg_ring($avg) ?>">
        <span><b><?= $avg ?>%</b><small>avg match</small></span>
      </div>
      <div>
        <h3>You're an average <?= $avg ?>% match across <?= $job_count ?> open role<?= $job_count>1?'s':'' ?></h3>
        <p>
          <?php if ($avg >= 70): ?>
            Strong profile — you clear the bar for most roles. Verify your top skills with certificates to stand out even more.
          <?php elseif ($avg >= 40): ?>
            You're close. Closing the gaps below will push you into the "ready to apply" range for many more jobs.
          <?php else: ?>
            There's room to grow. Focus on the highest-impact skills below to unlock more matches quickly.
          <?php endif; ?>
        </p>
      </div>
    </div>

    <div class="cols">
      <div class="panel">
        <h3><i class="fas fa-arrow-trend-up" style="color:#d97706"></i>Highest-impact skills to learn</h3>
        <div class="hint">Ranked by how many open jobs each skill would help unlock.</div>
        <?php if (empty($top_gaps)): ?>
          <p style="color:#059669;font-weight:600"><i class="fas fa-circle-check mr-1"></i>No common gaps found — your skills already cover the open roles well.</p>
        <?php else: foreach ($top_gaps as $g): $pct = round($g['count']/$max_gap*100); ?>
          <div class="gap-row">
            <div class="gr-top">
              <span class="name"><?= htmlspecialchars($g['label']) ?></span>
              <span class="cnt"><?= $g['count'] ?> job<?= $g['count']>1?'s':'' ?> want this</span>
            </div>
            <div class="bar"><i style="width:<?= $pct ?>%"></i></div>
            <?php if (!empty($g['jobs'])): ?><div class="jobs">e.g. <?= htmlspecialchars(implode(' · ', $g['jobs'])) ?></div><?php endif; ?>
          </div>
        <?php endforeach; endif; ?>

        <?php if (!empty($top_strengths)): ?>
          <h3 style="margin-top:24px"><i class="fas fa-star" style="color:#059669"></i>Your strengths</h3>
          <div class="hint">Skills of yours that already match open roles — lead with these.</div>
          <div class="str">
            <?php foreach ($top_strengths as $s): ?>
              <span class="s"><i class="fas fa-check"></i><?= htmlspecialchars($s['label']) ?> <b><?= $s['count'] ?></b></span>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <div>
        <div class="panel" style="margin-bottom:22px">
          <h3><i class="fas fa-bolt" style="color:var(--primary)"></i>Close the gap</h3>
          <div class="hint">Turn these gaps into offers.</div>

          <a class="act-card" href="grooming.php">
            <span class="ai" style="background:linear-gradient(135deg,#3b82f6,#06b6d4)"><i class="fas fa-graduation-cap"></i></span>
            <span><span class="t">Train &amp; take a skill quiz</span><span class="d">Learn a missing skill and prove it by passing a quiz.</span></span>
            <i class="fas fa-chevron-right go"></i>
          </a>
          <a class="act-card" href="mentors.php">
            <span class="ai" style="background:linear-gradient(135deg,#d97706,#f97316)"><i class="fas fa-chalkboard-user"></i></span>
            <span><span class="t">Book a mentor session</span><span class="d">Get 1:1 coaching on the exact skills you're missing.</span></span>
            <i class="fas fa-chevron-right go"></i>
          </a>
          <a class="act-card" href="recommendations.php">
            <span class="ai" style="background:linear-gradient(135deg,#059669,#059669)"><i class="fas fa-wand-magic-sparkles"></i></span>
            <span><span class="t">See your best-match jobs</span><span class="d">Apply now to the roles where you already score highest.</span></span>
            <i class="fas fa-chevron-right go"></i>
          </a>
        </div>

        <?php if (!empty($eligible)): ?>
          <div class="panel">
            <h3><i class="fas fa-award" style="color:#d97706"></i>Ready to certify</h3>
            <div class="hint">You've already passed <?= count($eligible) ?> quiz<?= count($eligible)>1?'zes':'' ?> — claim the credential.</div>
            <a href="certificates.php" class="btn btn-primary btn-block rounded-pill"><i class="fas fa-certificate mr-1"></i>Claim your certificate<?= count($eligible)>1?'s':'' ?></a>
          </div>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
</div>
