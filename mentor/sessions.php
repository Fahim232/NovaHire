<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_mentor_login();

$mentor_id = $_SESSION['mentor_id'];
$msg = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    if (isset($_POST['complete'])) {
        $sid = (int)$_POST['session_id'];
        // optional notes
        $notes = trim($_POST['mentor_notes'] ?? '');
        if ($notes !== '') {
            $stmt = mysqli_prepare($con, "UPDATE grooming_sessions SET mentor_notes=? WHERE id=? AND mentor_id=?");
            mysqli_stmt_bind_param($stmt, "sii", $notes, $sid, $mentor_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        if (nh_complete_session($con, $sid, $mentor_id)) {
            $msg = 'Session marked complete. Your earnings have been updated.';
        }
    }
    header('Location: sessions.php' . ($msg ? '?done=1' : ''));
    exit;
}
if (isset($_GET['done'])) $msg = 'Session marked complete. Your earnings have been updated.';

$sessions = nh_mentor_sessions($con, $mentor_id);

// reviews lookup
$reviews = [];
$rv = @mysqli_query($con, "SELECT session_id, rating, comment FROM session_reviews WHERE mentor_id=" . (int)$mentor_id);
if ($rv) while ($r = mysqli_fetch_assoc($rv)) $reviews[$r['session_id']] = $r;

$now = time();
$upcoming = []; $to_complete = []; $done = []; $cancelled = [];
foreach ($sessions as $s) {
    $ts = strtotime($s['scheduled_at']);
    if ($s['status'] === 'confirmed' && $ts > $now)      $upcoming[] = $s;
    elseif ($s['status'] === 'confirmed' && $ts <= $now) $to_complete[] = $s;
    elseif ($s['status'] === 'completed')                $done[] = $s;
    elseif ($s['status'] === 'cancelled')                $cancelled[] = $s;
}
usort($upcoming, fn($a,$b)=>strtotime($a['scheduled_at'])<=>strtotime($b['scheduled_at']));

$page_title = 'Sessions';
$nav_active = 'sessions';
require __DIR__ . '/mentor_header.php';

function mp_card($s, $reviews, $mode) {
    $ts = strtotime($s['scheduled_at']);
    ob_start(); ?>
    <div class="ses">
      <div class="ses-top">
        <div class="ses-av"><?= htmlspecialchars(strtoupper(substr($s['user_name'],0,1))) ?></div>
        <div>
          <div class="ses-title"><?= htmlspecialchars(nh_session_type_label($s['session_type'])) ?></div>
          <div class="ses-sub">with <?= htmlspecialchars($s['user_name']) ?></div>
        </div>
        <div class="ses-when">
          <div><i class="far fa-calendar mr-1"></i><?= date('D, M j, Y', $ts) ?></div>
          <div><i class="far fa-clock mr-1"></i><?= date('g:i A', $ts) ?> · <?= (int)$s['duration_min'] ?>m</div>
        </div>
        <div class="ses-earn"><?= nh_price($s['mentor_earning']) ?><small>your cut</small></div>
      </div>

      <?php if ($mode === 'upcoming' && $s['meeting_link']): ?>
        <div class="ses-act">
          <a class="join" href="<?= htmlspecialchars($s['meeting_link']) ?>" target="_blank" rel="noopener"><i class="fas fa-video mr-1"></i>Join video room</a>
          <span class="hint">Opens your live session with <?= htmlspecialchars($s['user_name']) ?>.</span>
        </div>
      <?php elseif ($mode === 'complete'): ?>
        <form method="POST" class="ses-act" onsubmit="return confirm('Mark this session complete? This releases your earning.')">
          <?= csrf_field() ?>
          <input type="hidden" name="session_id" value="<?= (int)$s['id'] ?>">
          <a class="join ghost" href="<?= htmlspecialchars($s['meeting_link']) ?>" target="_blank" rel="noopener"><i class="fas fa-video mr-1"></i>Reopen room</a>
          <textarea name="mentor_notes" class="notes" rows="1" placeholder="Private notes (optional)"><?= htmlspecialchars($s['mentor_notes'] ?? '') ?></textarea>
          <button class="btn-complete" name="complete" value="1"><i class="fas fa-check mr-1"></i>Mark complete</button>
        </form>
      <?php elseif ($mode === 'done'):
        $rev = $reviews[$s['id']] ?? null; ?>
        <div class="ses-act done-row">
          <?php if ($rev): ?>
            <div class="rev">
              <span class="stars"><?php for($i=1;$i<=5;$i++) echo '<i class="fas fa-star'.($i<=$rev['rating']?'':'-o').'"></i>';?></span>
              <?php if (!empty($rev['comment'])): ?><span class="rev-c">"<?= htmlspecialchars($rev['comment']) ?>"</span><?php endif; ?>
            </div>
          <?php else: ?>
            <span class="hint"><i class="fas fa-hourglass-half mr-1"></i>Awaiting seeker review</span>
          <?php endif; ?>
          <span class="paid"><i class="fas fa-circle-check mr-1"></i><?= nh_price($s['mentor_earning']) ?> earned</span>
        </div>
      <?php endif; ?>
    </div>
    <?php return ob_get_clean();
}
?>
<style>
  .ss-hi{font-weight:800;color:var(--text);font-size:1.6rem;letter-spacing:-.5px}
  .ss-sub{color:var(--text-muted);margin-bottom:22px}
  .sec-h{font-weight:700;color:var(--text);font-size:1.05rem;margin:26px 0 14px;display:flex;align-items:center;gap:10px}
  .sec-h .n{background:var(--bg-hover);color:var(--text-muted);font-size:.75rem;font-weight:700;padding:2px 9px;border-radius:99px}
  .sec-h.hot .n{background:rgba(217,119,6,.15);color:#b45309}
  .ses{background:var(--bg-card);border:1px solid var(--border-light);border-radius:14px;padding:16px 20px;margin-bottom:12px;box-shadow:var(--shadow-xs)}
  .ses-top{display:flex;gap:14px;align-items:center}
  .ses-av{width:46px;height:46px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--secondary));color:#fff;display:grid;place-items:center;font-weight:700;flex-shrink:0}
  .ses-title{font-weight:700;color:var(--text)}
  .ses-sub{color:var(--text-muted);font-size:.84rem;margin:0}
  .ses-when{margin-left:auto;text-align:right;color:var(--text-muted);font-size:.82rem;line-height:1.5}
  .ses-earn{font-weight:800;color:var(--text);font-size:1.05rem;text-align:right;min-width:90px}
  .ses-earn small{display:block;color:var(--text-muted);font-weight:600;font-size:.68rem}
  .ses-act{display:flex;gap:12px;align-items:center;margin-top:14px;padding-top:14px;border-top:1px solid var(--border-light);flex-wrap:wrap}
  .join{background:linear-gradient(135deg,#1a56db,#0ea5e9);color:#fff!important;font-weight:700;border-radius:99px;padding:9px 18px;text-decoration:none;font-size:.85rem}
  .join.ghost{background:var(--bg-hover);color:var(--primary)!important}
  .join:hover{opacity:.93}
  .hint{color:var(--text-muted);font-size:.82rem}
  .notes{flex:1;min-width:160px;border:1.5px solid var(--border);border-radius:10px;padding:8px 12px;background:var(--bg);color:var(--text);font-size:.85rem;font-family:inherit;resize:vertical}
  .btn-complete{background:#059669;color:#fff;border:none;border-radius:99px;padding:9px 18px;font-weight:700;font-size:.85rem;cursor:pointer;white-space:nowrap}
  .btn-complete:hover{background:#059669}
  .done-row{justify-content:space-between}
  .rev{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
  .stars{color:#d97706;font-size:.85rem}
  .rev-c{color:var(--text-muted);font-size:.85rem;font-style:italic}
  .paid{color:#059669;font-weight:700;font-size:.85rem}
  .msg{background:rgba(5,150,105,.12);border:1px solid rgba(5,150,105,.3);color:#047857;padding:12px 16px;border-radius:11px;margin-bottom:18px;font-weight:600;font-size:.9rem}
  .empty{text-align:center;padding:50px 16px;color:var(--text-muted)}
  .empty i{font-size:2.6rem;color:var(--text-light);margin-bottom:12px}
</style>

<div class="ss-hi">Your sessions</div>
<div class="ss-sub">Join upcoming calls, mark finished sessions complete to release earnings, and see your reviews.</div>

<?php if ($msg): ?><div class="msg"><i class="fas fa-check-circle mr-1"></i><?= htmlspecialchars($msg) ?></div><?php endif; ?>

<?php if (empty($sessions)): ?>
  <div class="empty">
    <i class="fas fa-video-slash d-block"></i>
    <h5 style="color:var(--text);font-weight:700">No sessions yet</h5>
    <p>Once seekers book your open slots, their sessions show up here.</p>
    <a href="availability.php" class="btn btn-primary rounded-pill px-4">Add availability</a>
  </div>
<?php else: ?>

  <?php if ($to_complete): ?>
    <div class="sec-h hot"><i class="fas fa-hourglass-end" style="color:#d97706"></i>Ready to complete <span class="n"><?= count($to_complete) ?></span></div>
    <?php foreach ($to_complete as $s) echo mp_card($s, $reviews, 'complete'); ?>
  <?php endif; ?>

  <?php if ($upcoming): ?>
    <div class="sec-h"><i class="fas fa-calendar-day" style="color:var(--primary)"></i>Upcoming <span class="n"><?= count($upcoming) ?></span></div>
    <?php foreach ($upcoming as $s) echo mp_card($s, $reviews, 'upcoming'); ?>
  <?php endif; ?>

  <?php if ($done): ?>
    <div class="sec-h"><i class="fas fa-flag-checkered" style="color:#059669"></i>Completed <span class="n"><?= count($done) ?></span></div>
    <?php foreach ($done as $s) echo mp_card($s, $reviews, 'done'); ?>
  <?php endif; ?>

  <?php if ($cancelled): ?>
    <div class="sec-h"><i class="fas fa-ban" style="color:#dc2626"></i>Cancelled <span class="n"><?= count($cancelled) ?></span></div>
    <?php foreach ($cancelled as $s) echo mp_card($s, $reviews, 'cancelled'); ?>
  <?php endif; ?>

<?php endif; ?>

<?php require __DIR__ . '/mentor_footer.php'; ?>
