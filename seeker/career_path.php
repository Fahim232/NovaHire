<?php
/**
 * NovaHire — Career Path (seeker)
 * ---------------------------------------------------------------------------
 * Turns the AI match scores into a personal career ladder: roles you're ready
 * for now, roles you're growing into, and stretch goals — plus the strongest
 * career tracks for your profile and a concrete "next target" with the exact
 * skills to get there. Growth is routed through grooming, mentors and Pro.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
if (!isset($_SESSION['id'])) { header('location: ' . BASE_URL . '/auth/login.php'); exit(); }
require_once __DIR__ . '/../includes/header.php';

$user_id = $_SESSION['id'];

require_once __DIR__ . '/../includes/premium.php';
$access = nh_check_access($con, $user_id, 'career_path');
if (!$access['allowed']) {
    nh_render_pro_gate('career_path');
    exit;
}

$uq = mysqli_query($con, "SELECT * FROM user_info WHERE id = " . (int)$user_id);
$user = mysqli_fetch_assoc($uq);
$has_skills = !empty(trim((string)$user['user_skills']));

$recs = $has_skills ? nh_get_recommendations($con, $user, 100) : [];

$ready = []; $growing = []; $stretch = [];
$by_cat = []; // category => ['count','sum']
foreach ($recs as $j) {
    $s = (int)$j['ai']['score'];
    if ($s >= 70) $ready[] = $j;
    elseif ($s >= 40) $growing[] = $j;
    else $stretch[] = $j;

    $cat = trim((string)($j['job_category'] ?? '')); if ($cat === '') $cat = 'General';
    if (!isset($by_cat[$cat])) $by_cat[$cat] = ['count' => 0, 'sum' => 0];
    $by_cat[$cat]['count']++; $by_cat[$cat]['sum'] += $s;
}
foreach ($by_cat as $c => &$d) $d['avg'] = round($d['sum'] / max(1,$d['count']));
unset($d);
uasort($by_cat, fn($a,$b) => $b['avg'] <=> $a['avg']);
$tracks = array_slice($by_cat, 0, 5, true);

// The "next target": the strongest role the seeker is still growing into.
$next = $growing[0] ?? ($stretch[0] ?? null);

function cp_ring($v){ return $v>=70?'#059669':($v>=40?'#d97706':'#94a3b8'); }
?>
<style>
  .cp-wrap{max-width:1080px;margin:0 auto;padding:0 20px 60px}
  .cp-head h1{font-weight:800;color:var(--text);font-size:1.8rem;letter-spacing:-.5px}
  .cp-head p{color:var(--text-muted);margin:0}

  /* next target */
  .target{background:linear-gradient(135deg,#1a56db,#0ea5e9);border-radius:var(--radius-xl);color:#fff;padding:26px;margin:24px 0;position:relative;overflow:hidden}
  .target::after{content:'\f091';font-family:'Font Awesome 6 Free';font-weight:900;position:absolute;right:-12px;bottom:-20px;font-size:8rem;opacity:.1}
  .target .lbl{font-size:.72rem;font-weight:800;letter-spacing:.6px;text-transform:uppercase;opacity:.85}
  .target h2{font-size:1.5rem;font-weight:800;margin:6px 0 2px}
  .target .co{opacity:.9}
  .target .miss{margin-top:16px;display:flex;flex-wrap:wrap;gap:7px}
  .target .miss .m{background:rgba(255,255,255,.16);border-radius:99px;padding:4px 12px;font-size:.78rem;font-weight:600;text-transform:capitalize}
  .target .cta{margin-top:20px;display:flex;gap:10px;flex-wrap:wrap}
  .target .cta a{border-radius:var(--radius-full);padding:10px 20px;font-weight:700;font-size:.86rem;text-decoration:none}
  .target .cta a.w{background:#fff;color:#1a56db}
  .target .cta a.g{background:rgba(255,255,255,.16);color:#fff}
  .target .cta a:hover{opacity:.93}

  .ladder{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-top:8px}
  @media(max-width:820px){.ladder{grid-template-columns:1fr}}
  .rung{background:var(--bg-card);border:1px solid var(--border-light);border-radius:var(--radius-lg);padding:20px;box-shadow:var(--shadow-xs)}
  .rung .rh{display:flex;align-items:center;gap:10px;margin-bottom:4px}
  .rung .rh .dot{width:11px;height:11px;border-radius:50%}
  .rung .rh h4{font-weight:800;color:var(--text);font-size:1.02rem;margin:0}
  .rung .rc{font-size:.8rem;color:var(--text-muted);margin-bottom:14px}
  .rung .cnt{font-weight:800;font-size:1.6rem;color:var(--text)}
  .role{display:flex;align-items:center;gap:10px;padding:9px 0;border-top:1px solid var(--border-light);text-decoration:none}
  .role:first-of-type{border-top:none}
  .role .rt{font-weight:600;color:var(--text);font-size:.88rem;line-height:1.2}
  .role .rco{color:var(--text-muted);font-size:.76rem}
  .role .rs{margin-left:auto;font-weight:800;font-size:.82rem}
  .role:hover .rt{color:var(--primary)}
  .rung .none{color:var(--text-light);font-size:.85rem;padding:8px 0}

  .tracks{background:var(--bg-card);border:1px solid var(--border-light);border-radius:var(--radius-lg);padding:22px;box-shadow:var(--shadow-xs);margin-top:22px}
  .tracks h3{font-weight:700;color:var(--text);font-size:1.08rem;margin-bottom:4px;display:flex;align-items:center;gap:9px}
  .tracks .hint{color:var(--text-muted);font-size:.85rem;margin-bottom:16px}
  .track{margin-bottom:14px}
  .track .tt{display:flex;justify-content:space-between;margin-bottom:6px}
  .track .tt .nm{font-weight:700;color:var(--text)}
  .track .tt .vv{font-weight:800;color:var(--text)}
  .track .bar{height:9px;border-radius:99px;background:var(--border-light);overflow:hidden}
  .track .bar i{display:block;height:100%;border-radius:99px}
  .track .cn{color:var(--text-light);font-size:.76rem;margin-top:4px}

  .empty{text-align:center;padding:56px 20px;color:var(--text-muted)}
  .empty i{font-size:2.6rem;color:var(--text-light);margin-bottom:14px}
</style>

<div class="cp-wrap">
  <div class="cp-head">
    <h1><i class="fas fa-signs-post mr-2" style="color:var(--primary)"></i>Your Career Path</h1>
    <p>Where you can go next, based on how your profile matches every open role.</p>
  </div>

  <?php if (!$has_skills): ?>
    <div class="empty">
      <i class="fas fa-user-pen d-block"></i>
      <h5 style="color:var(--text);font-weight:700">Add your skills to map your path</h5>
      <p>We build your career ladder from how you match open roles. Add your skills to begin.</p>
      <a href="profile.php" class="btn btn-primary rounded-pill px-4">Complete profile</a>
    </div>
  <?php elseif (empty($recs)): ?>
    <div class="empty">
      <i class="fas fa-briefcase d-block"></i>
      <h5 style="color:var(--text);font-weight:700">No open jobs to map yet</h5>
      <p>Check back soon — new roles are posted regularly.</p>
      <a href="browse_jobs.php" class="btn btn-primary rounded-pill px-4">Browse jobs</a>
    </div>
  <?php else: ?>

    <?php if ($next): $miss = array_slice($next['ai']['missing_skills'] ?? [], 0, 5); ?>
      <div class="target">
        <div class="lbl"><i class="fas fa-bullseye mr-1"></i>Your next target</div>
        <h2><?= htmlspecialchars($next['job_title']) ?></h2>
        <div class="co"><?= htmlspecialchars($next['company_name']) ?> · <?= (int)$next['ai']['score'] ?>% match today</div>
        <?php if ($miss): ?>
          <div class="miss">
            <span style="opacity:.85;font-size:.8rem;align-self:center">Close these to qualify:</span>
            <?php foreach ($miss as $m): ?><span class="m"><?= htmlspecialchars($m) ?></span><?php endforeach; ?>
          </div>
        <?php endif; ?>
        <div class="cta">
          <a class="w" href="grooming.php"><i class="fas fa-graduation-cap mr-1"></i>Build these skills</a>
          <a class="g" href="mentors.php"><i class="fas fa-chalkboard-user mr-1"></i>Get a mentor</a>
          <a class="g" href="job_details.php?id=<?= (int)$next['id'] ?>"><i class="fas fa-arrow-right mr-1"></i>View role</a>
        </div>
      </div>
    <?php endif; ?>

    <div class="ladder">
      <?php
      $rungs = [
        ['Ready now', 'Roles you match 70%+ — apply today.', '#059669', $ready],
        ['Growing into', 'A few skills away (40–69%).', '#d97706', $growing],
        ['Stretch goals', 'Aspirational roles to build toward.', '#94a3b8', $stretch],
      ];
      foreach ($rungs as $r): [$title,$desc,$color,$list] = $r; ?>
        <div class="rung">
          <div class="rh"><span class="dot" style="background:<?= $color ?>"></span><h4><?= $title ?></h4></div>
          <div class="rc"><?= $desc ?></div>
          <div class="cnt" style="color:<?= $color ?>"><?= count($list) ?></div>
          <div style="margin-top:10px">
            <?php if (empty($list)): ?>
              <div class="none"><?= $title==='Ready now' ? 'Close a few gaps to unlock these.' : 'Nothing here right now.' ?></div>
            <?php else: foreach (array_slice($list, 0, 4) as $j): $s=(int)$j['ai']['score']; ?>
              <a class="role" href="job_details.php?id=<?= (int)$j['id'] ?>">
                <span><span class="rt"><?= htmlspecialchars($j['job_title']) ?></span><br><span class="rco"><?= htmlspecialchars($j['company_name']) ?></span></span>
                <span class="rs" style="color:<?= cp_ring($s) ?>"><?= $s ?>%</span>
              </a>
            <?php endforeach; endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="tracks">
      <h3><i class="fas fa-layer-group" style="color:var(--primary)"></i>Your strongest career tracks</h3>
      <div class="hint">Job categories ranked by how well your profile fits — your natural direction and where to specialize.</div>
      <?php foreach ($tracks as $cat => $d): ?>
        <div class="track">
          <div class="tt"><span class="nm"><?= htmlspecialchars($cat) ?></span><span class="vv" style="color:<?= cp_ring($d['avg']) ?>"><?= $d['avg'] ?>%</span></div>
          <div class="bar"><i style="width:<?= $d['avg'] ?>%;background:linear-gradient(90deg,<?= cp_ring($d['avg']) ?>,<?= cp_ring($d['avg']) ?>bb)"></i></div>
          <div class="cn"><?= $d['count'] ?> open role<?= $d['count']>1?'s':'' ?> in this track</div>
        </div>
      <?php endforeach; ?>
    </div>

  <?php endif; ?>
</div>
