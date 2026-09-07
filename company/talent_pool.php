<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_company_login();

$company_id = $_SESSION['company_id'];

// Handle pipeline stage update
$flash = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['advance_stage'])) {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $app_id = (int)$_POST['application_id'];
        $stage  = $_POST['stage'] ?? '';
        // ownership check: application must belong to this company
        $chk = mysqli_prepare($con, "SELECT id FROM job_applications WHERE id = ? AND company_id = ? LIMIT 1");
        mysqli_stmt_bind_param($chk, "ii", $app_id, $company_id);
        mysqli_stmt_execute($chk);
        $owned = mysqli_fetch_assoc(mysqli_stmt_get_result($chk));
        mysqli_stmt_close($chk);
        if ($owned && nh_set_pipeline_stage($con, $app_id, $stage)) {
            $flash = $stage === 'hired' ? 'Candidate marked as hired — a placement was recorded.' : 'Pipeline stage updated.';
        }
    }
    header('Location: talent_pool.php?ok=' . urlencode($flash ?? '1'));
    exit;
}
if (isset($_GET['ok'])) $flash = $_GET['ok'] === '1' ? 'Updated.' : $_GET['ok'];

$pool  = nh_company_talent_pool($con, $company_id);
$stats = nh_placement_stats($con, $company_id);

// filter by job
$job_filter = isset($_GET['job']) ? (int)$_GET['job'] : 0;
$jobs_seen = [];
foreach ($pool as $p) { $jobs_seen[$p['job_id']] = $p['job_title']; }
if ($job_filter) $pool = array_values(array_filter($pool, fn($p) => (int)$p['job_id'] === $job_filter));

function score_color($s){ return $s>=70?'#059669':($s>=40?'#d97706':'#dc2626'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <title>Talent Pool | Company Dashboard</title>
  <?php include '../includes/links.php'; ?>
  <style>
    body{background:var(--bg)}
    .tp-wrap{max-width:1140px;margin:28px auto 60px;padding:0 20px}
    .tp-head h1{font-weight:800;color:var(--text);font-size:1.7rem;letter-spacing:-.5px}
    .tp-head p{color:var(--text-muted)}
    .tp-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin:22px 0}
    @media(max-width:720px){.tp-stats{grid-template-columns:repeat(2,1fr)}}
    .tp-stat{background:var(--bg-card);border:1px solid var(--border-light);border-radius:var(--radius-lg);padding:18px 20px;box-shadow:var(--shadow-xs)}
    .tp-stat .n{font-size:1.6rem;font-weight:800;color:var(--text)}
    .tp-stat .l{color:var(--text-muted);font-size:.82rem;font-weight:600;text-transform:uppercase;letter-spacing:.4px}
    .tp-toolbar{display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:16px}
    .tp-toolbar select{max-width:280px}
    .cand{background:var(--bg-card);border:1px solid var(--border-light);border-radius:var(--radius-lg);padding:18px 20px;margin-bottom:14px;box-shadow:var(--shadow-xs);display:grid;grid-template-columns:52px 1fr auto;gap:16px;align-items:center}
    .cand-av{width:52px;height:52px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--secondary));color:#fff;display:grid;place-items:center;font-weight:800;font-size:1.2rem}
    .cand-name{font-weight:700;color:var(--text);font-size:1.05rem;display:flex;align-items:center;gap:8px;flex-wrap:wrap}
    .cand-sub{color:var(--text-muted);font-size:.86rem;margin-top:2px}
    .chips{margin-top:8px;display:flex;gap:6px;flex-wrap:wrap}
    .chip{background:var(--bg-hover);color:var(--text-muted);font-size:.72rem;font-weight:600;padding:3px 9px;border-radius:var(--radius-full)}
    .ring{--v:0;width:54px;height:54px;border-radius:50%;display:grid;place-items:center;font-weight:800;font-size:.9rem;color:var(--text);background:conic-gradient(var(--rc) calc(var(--v)*1%),var(--border-light) 0)}
    .ring span{width:42px;height:42px;border-radius:50%;background:var(--bg-card);display:grid;place-items:center}
    .cand-right{display:flex;align-items:center;gap:16px}
    .stage-pill{padding:4px 12px;border-radius:var(--radius-full);font-size:.75rem;font-weight:700;color:#fff}
    .rankn{position:absolute;margin-top:-34px;margin-left:-8px;background:var(--text);color:#fff;font-size:.68rem;font-weight:700;width:22px;height:22px;border-radius:50%;display:grid;place-items:center}
    .stage-form{display:flex;gap:8px;align-items:center;margin-top:10px}
    .stage-form select{font-size:.82rem;padding:5px 8px;border-radius:8px;border:1px solid var(--border);background:var(--bg-card);color:var(--text)}
    .flash{background:rgba(5,150,105,.12);border:1px solid rgba(5,150,105,.3);color:#047857;padding:12px 16px;border-radius:var(--radius-md);margin-bottom:18px;font-weight:600}
    .empty{text-align:center;padding:60px 20px;color:var(--text-muted)}
    .empty i{font-size:2.4rem;margin-bottom:12px;color:var(--text-light)}
    .pro-pill{display:inline-flex;align-items:center;gap:4px;background:linear-gradient(135deg,#d97706,#f97316);color:#fff;font-size:.66rem;font-weight:800;padding:2px 8px;border-radius:20px}
  </style>
</head>
<body>
  <?php require_once __DIR__ . '/company_header.php'; ?>

  <div class="tp-wrap">
    <div class="tp-head">
      <h1><i class="fas fa-users-viewfinder mr-2" style="color:var(--primary)"></i>Talent Pool</h1>
      <p>Applicants across your jobs, ranked by AI match, verified skills and Pro status.</p>
    </div>

    <?php if ($flash): ?><div class="flash"><i class="fas fa-circle-check mr-1"></i><?= htmlspecialchars($flash) ?></div><?php endif; ?>

    <div class="tp-stats">
      <div class="tp-stat"><div class="n"><?= count($pool) ?></div><div class="l">Candidates</div></div>
      <div class="tp-stat"><div class="n"><?= (int)$stats['total'] ?></div><div class="l">Hires made</div></div>
      <div class="tp-stat"><div class="n"><?= (int)$stats['fee_pending'] ?></div><div class="l">Fees pending</div></div>
      <div class="tp-stat"><div class="n"><?= nh_price($stats['revenue']) ?></div><div class="l">Fees settled</div></div>
    </div>

    <div class="tp-toolbar">
      <form method="GET" class="d-flex" style="gap:8px">
        <select name="job" class="form-control" onchange="this.form.submit()">
          <option value="0">All jobs (<?= array_sum(array_map(fn($t)=>1,$jobs_seen)) ?>)</option>
          <?php foreach ($jobs_seen as $jid => $jt): ?>
            <option value="<?= (int)$jid ?>" <?= $job_filter===(int)$jid?'selected':'' ?>><?= htmlspecialchars($jt) ?></option>
          <?php endforeach; ?>
        </select>
      </form>
    </div>

    <?php if (empty($pool)): ?>
      <div class="empty"><i class="fas fa-user-slash d-block"></i><p>No applicants yet. Once candidates apply to your jobs, they'll be ranked here.</p></div>
    <?php else: ?>
      <?php foreach ($pool as $i => $c):
        $ms = (int)$c['match_score'];
        $skills = array_filter(array_map('trim', explode(',', (string)$c['user_skills'])));
        $stage = $c['pipeline_stage'] ?: 'applied';
      ?>
      <div class="cand" style="position:relative">
        <span class="rankn">#<?= $i+1 ?></span>
        <div class="cand-av"><?= htmlspecialchars(strtoupper(substr($c['username'],0,1))) ?></div>
        <div>
          <div class="cand-name">
            <?= htmlspecialchars($c['username']) ?>
            <?php if ($c['is_pro']): ?><span class="pro-pill"><i class="fas fa-crown"></i>PRO</span><?php endif; ?>
          </div>
          <div class="cand-sub">Applied for <strong><?= htmlspecialchars($c['job_title']) ?></strong> · <?= date('M j', strtotime($c['applied_date'])) ?></div>
          <div class="chips">
            <?php if ($c['quiz_score'] !== null): ?><span class="chip"><i class="fas fa-clipboard-check mr-1"></i>Quiz <?= (int)$c['quiz_score'] ?>%</span><?php endif; ?>
            <span class="chip"><i class="fas fa-award mr-1"></i><?= (int)$c['grooming_passed'] ?> skills verified</span>
            <?php foreach (array_slice($skills,0,4) as $s): ?><span class="chip"><?= htmlspecialchars($s) ?></span><?php endforeach; ?>
          </div>
          <form class="stage-form" method="POST" action="talent_pool.php">
            <?= csrf_field() ?>
            <input type="hidden" name="application_id" value="<?= (int)$c['application_id'] ?>">
            <select name="stage">
              <?php foreach (nh_pipeline_stages() as $st): ?>
                <option value="<?= $st ?>" <?= $st===$stage?'selected':'' ?>><?= nh_stage_label($st) ?></option>
              <?php endforeach; ?>
            </select>
            <button class="btn btn-sm btn-primary" name="advance_stage" value="1" type="submit">Update</button>
            <a class="btn btn-sm btn-outline-secondary" href="view_applicant_detail.php?id=<?= (int)$c['application_id'] ?>">View</a>
          </form>
        </div>
        <div class="cand-right">
          <div class="text-center">
            <div class="ring" style="--v:<?= $ms ?>;--rc:<?= score_color($ms) ?>"><span><?= $ms ?>%</span></div>
            <div style="font-size:.68rem;color:var(--text-muted);margin-top:4px;font-weight:600">MATCH</div>
          </div>
          <span class="stage-pill" style="background:<?= nh_stage_color($stage) ?>"><?= nh_stage_label($stage) ?></span>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</body>
</html>
