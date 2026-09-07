<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_mentor_login();

$mentor_id = $_SESSION['mentor_id'];
$mentor    = nh_get_mentor($con, $mentor_id);
$earnings  = nh_mentor_earnings_summary($con, $mentor_id);
$sessions  = nh_mentor_sessions($con, $mentor_id);
$slots     = nh_get_mentor_slots($con, $mentor_id, true);

$upcoming = array_filter($sessions, fn($s) => $s['status'] === 'confirmed' && strtotime($s['scheduled_at']) > time());
usort($upcoming, fn($a,$b) => strtotime($a['scheduled_at']) <=> strtotime($b['scheduled_at']));
$completed_count = count(array_filter($sessions, fn($s) => $s['status'] === 'completed'));

$page_title = 'Dashboard';
$nav_active = 'dashboard';
require __DIR__ . '/mentor_header.php';
?>
<style>
  .dash-hi{font-weight:800;color:var(--text);font-size:1.7rem;letter-spacing:-.5px}
  .dash-sub{color:var(--text-muted);margin-bottom:24px}
  .stat-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:26px}
  @media(max-width:820px){.stat-grid{grid-template-columns:1fr 1fr}}
  .stat{background:var(--bg-card);border:1px solid var(--border-light);border-radius:16px;padding:20px;box-shadow:var(--shadow-xs)}
  .stat .ic{width:42px;height:42px;border-radius:12px;display:grid;place-items:center;font-size:1.1rem;margin-bottom:12px}
  .stat .v{font-weight:800;color:var(--text);font-size:1.55rem;letter-spacing:-.5px}
  .stat .l{color:var(--text-muted);font-size:.82rem;font-weight:600}
  .cols{display:grid;grid-template-columns:1.6fr 1fr;gap:20px}
  @media(max-width:820px){.cols{grid-template-columns:1fr}}
  .panel{background:var(--bg-card);border:1px solid var(--border-light);border-radius:16px;padding:22px;box-shadow:var(--shadow-xs)}
  .panel h3{font-weight:700;color:var(--text);font-size:1.05rem;margin-bottom:16px;display:flex;align-items:center;gap:9px}
  .up{display:flex;gap:13px;align-items:center;padding:13px 0;border-bottom:1px solid var(--border-light)}
  .up:last-child{border-bottom:none}
  .up-av{width:44px;height:44px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--secondary));color:#fff;display:grid;place-items:center;font-weight:700;flex-shrink:0}
  .up-t{font-weight:700;color:var(--text);font-size:.92rem}
  .up-s{color:var(--text-muted);font-size:.82rem}
  .up-join{margin-left:auto;background:linear-gradient(135deg,#1a56db,#0ea5e9);color:#fff!important;font-weight:700;border-radius:99px;padding:8px 16px;text-decoration:none;font-size:.82rem;white-space:nowrap}
  .empty{text-align:center;padding:34px 16px;color:var(--text-muted)}
  .empty i{font-size:2.2rem;color:var(--text-light);margin-bottom:10px}
  .qa{display:flex;flex-direction:column;gap:10px}
  .qa a{display:flex;align-items:center;gap:12px;padding:14px 16px;border:1px solid var(--border-light);border-radius:12px;text-decoration:none;color:var(--text);font-weight:600;transition:.15s}
  .qa a:hover{border-color:var(--primary);background:var(--bg-hover);color:var(--primary)}
  .qa a i{width:20px;text-align:center;color:var(--primary)}
  .payout{background:linear-gradient(135deg,#ecfdf5,#d1fae5);border:1px solid #a7f3d0;border-radius:14px;padding:18px;text-align:center;margin-bottom:14px}
  [data-theme=dark] .payout{background:#064e3b;border-color:#065f46}
  .payout .n{font-weight:800;font-size:1.9rem;color:#047857}
  [data-theme=dark] .payout .n{color:#6ee7b7}
  .payout .l{color:#059669;font-weight:600;font-size:.82rem}
  [data-theme=dark] .payout .l{color:#34d399}
</style>

<div class="dash-hi">Welcome back, <?= htmlspecialchars(explode(' ', $mentor['name'])[0]) ?> 👋</div>
<div class="dash-sub"><?= $mentor['status']==='approved' ? 'Your profile is live. Here\'s how your mentoring is going.' : 'Set up your availability so you\'re ready the moment you\'re approved.' ?></div>

<div class="stat-grid">
  <div class="stat">
    <div class="ic" style="background:rgba(5,150,105,.12);color:#059669"><i class="fas fa-wallet"></i></div>
    <div class="v"><?= nh_price($earnings['available']) ?></div>
    <div class="l">Available to withdraw</div>
  </div>
  <div class="stat">
    <div class="ic" style="background:rgba(26,86,219,.12);color:#1a56db"><i class="fas fa-sack-dollar"></i></div>
    <div class="v"><?= nh_price($earnings['total']) ?></div>
    <div class="l">Total earned</div>
  </div>
  <div class="stat">
    <div class="ic" style="background:rgba(217,119,6,.12);color:#d97706"><i class="fas fa-star"></i></div>
    <div class="v"><?= number_format((float)$mentor['rating'],1) ?> <small style="font-size:.9rem;color:var(--text-muted);font-weight:600">/ 5</small></div>
    <div class="l"><?= (int)$mentor['total_reviews'] ?> reviews</div>
  </div>
  <div class="stat">
    <div class="ic" style="background:rgba(6,182,212,.12);color:#06b6d4"><i class="fas fa-video"></i></div>
    <div class="v"><?= (int)$mentor['total_sessions'] ?></div>
    <div class="l">Sessions delivered</div>
  </div>
</div>

<div class="cols">
  <div class="panel">
    <h3><i class="fas fa-calendar-day" style="color:var(--primary)"></i>Upcoming sessions</h3>
    <?php if (empty($upcoming)): ?>
      <div class="empty">
        <i class="fas fa-calendar-xmark d-block"></i>
        <p>No upcoming sessions yet.<br><?= empty($slots) ? 'Add availability so seekers can book you.' : 'Your open slots are live — bookings will appear here.' ?></p>
        <?php if (empty($slots)): ?><a href="availability.php" class="btn btn-primary btn-sm rounded-pill px-4">Add availability</a><?php endif; ?>
      </div>
    <?php else: ?>
      <?php foreach (array_slice($upcoming, 0, 5) as $s): $ts = strtotime($s['scheduled_at']); ?>
      <div class="up">
        <div class="up-av"><?= htmlspecialchars(strtoupper(substr($s['user_name'],0,1))) ?></div>
        <div>
          <div class="up-t"><?= htmlspecialchars(nh_session_type_label($s['session_type'])) ?> · <?= htmlspecialchars($s['user_name']) ?></div>
          <div class="up-s"><i class="far fa-clock mr-1"></i><?= date('D, M j · g:i A', $ts) ?> (<?= (int)$s['duration_min'] ?>m)</div>
        </div>
        <?php if ($s['meeting_link']): ?><a class="up-join" href="<?= htmlspecialchars($s['meeting_link']) ?>" target="_blank" rel="noopener"><i class="fas fa-video mr-1"></i>Join</a><?php endif; ?>
      </div>
      <?php endforeach; ?>
      <div class="text-center mt-3"><a href="sessions.php" class="btn btn-link btn-sm">View all sessions →</a></div>
    <?php endif; ?>
  </div>

  <div>
    <div class="payout">
      <div class="n"><?= nh_price($earnings['available']) ?></div>
      <div class="l">READY FOR PAYOUT</div>
    </div>
    <div class="panel">
      <h3><i class="fas fa-bolt" style="color:var(--primary)"></i>Quick actions</h3>
      <div class="qa">
        <a href="availability.php"><i class="fas fa-calendar-plus"></i>Manage availability <span style="margin-left:auto;color:var(--text-muted);font-weight:700;font-size:.82rem"><?= count($slots) ?> open</span></a>
        <a href="sessions.php"><i class="fas fa-list-check"></i>All sessions <span style="margin-left:auto;color:var(--text-muted);font-weight:700;font-size:.82rem"><?= $completed_count ?> done</span></a>
        <a href="../seeker/mentors.php" target="_blank"><i class="fas fa-eye"></i>View public directory</a>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/mentor_footer.php'; ?>
