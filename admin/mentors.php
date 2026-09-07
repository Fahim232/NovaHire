<?php
require_once __DIR__ . '/../includes/bootstrap.php';

if (!isset($_SESSION['admin_username'])) {
    header('Location: admin_login.php');
    exit();
}

$msg = null;
// status change actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $mid = (int)($_POST['mentor_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $map = ['approve' => 'approved', 'reject' => 'rejected', 'suspend' => 'suspended', 'reinstate' => 'approved'];
    if ($mid && isset($map[$action])) {
        $new_status = $map[$action];
        $stmt = mysqli_prepare($con, "UPDATE mentors SET status = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "si", $new_status, $mid);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        // notify mentor
        if (function_exists('create_notification')) {
            $titles = [
                'approved' => '🎉 You\'re approved!',
                'rejected' => 'Mentor application update',
                'suspended' => 'Your mentor account was suspended',
            ];
            $msgs = [
                'approved' => 'Congratulations — your mentor profile is now live on NovaHire. Add availability to start receiving bookings.',
                'rejected' => 'Thank you for applying. Unfortunately your mentor application was not approved at this time.',
                'suspended' => 'Your mentor account has been suspended and is not visible to seekers. Contact the NovaHire team for details.',
            ];
            if (isset($titles[$new_status])) {
                create_notification($con, 'mentor', $mid, 'admin', $_SESSION['admin_id'] ?? 0, $titles[$new_status], $msgs[$new_status], 'system', 'mentors', $mid);
            }
        }
        $msg = 'Mentor ' . htmlspecialchars($action) . 'd successfully.';
    }
}

// filter
$filter = $_GET['status'] ?? 'all';
$valid = ['all','pending','approved','rejected','suspended'];
if (!in_array($filter, $valid)) $filter = 'all';
$where = $filter === 'all' ? '' : " WHERE status = '" . mysqli_real_escape_string($con, $filter) . "'";
$mentors = [];
$mr = @mysqli_query($con, "SELECT * FROM mentors" . $where . " ORDER BY FIELD(status,'pending','approved','suspended','rejected'), created_at DESC");
if ($mr) while ($r = mysqli_fetch_assoc($mr)) $mentors[] = $r;

// counts
$counts = ['all'=>0,'pending'=>0,'approved'=>0,'rejected'=>0,'suspended'=>0];
$cr = @mysqli_query($con, "SELECT status, COUNT(*) c FROM mentors GROUP BY status");
if ($cr) while ($row = mysqli_fetch_assoc($cr)) { $counts[$row['status']] = (int)$row['c']; $counts['all'] += (int)$row['c']; }

$status_badge = [
    'pending'   => ['#d97706','rgba(217,119,6,.14)','Pending'],
    'approved'  => ['#059669','rgba(5,150,105,.12)','Approved'],
    'rejected'  => ['#dc2626','rgba(239,68,68,.12)','Rejected'],
    'suspended' => ['#0ea5e9','rgba(14,165,233,.12)','Suspended'],
];

include 'header.php';
?>
<style>
  .mn-wrap{padding:0 0 50px}
  .mn-hero{margin-top:-72px;padding:96px 0 80px;background:linear-gradient(120deg,#1a56db,#0ea5e9 55%,#a21caf 120%);position:relative;overflow:hidden}
  .mn-hero::before{content:'';position:absolute;top:-120px;right:-60px;width:360px;height:360px;border-radius:50%;background:radial-gradient(circle,rgba(255,255,255,.14),transparent 70%)}
  .mn-hero h1{color:#fff;font-size:2rem;font-weight:800;letter-spacing:-.5px;margin:0 0 6px}
  .mn-hero p{color:rgba(255,255,255,.85);margin:0}
  .mn-tabs{display:flex;gap:8px;flex-wrap:wrap;margin:0 0 20px}
  .mn-tab{padding:9px 16px;border-radius:11px;font-size:.84rem;font-weight:700;text-decoration:none;background:var(--bg-card);border:1px solid var(--border-light);color:var(--text)}
  .mn-tab:hover{border-color:var(--primary);color:var(--primary);text-decoration:none}
  .mn-tab.on{background:linear-gradient(135deg,#3b82f6,#06b6d4);color:#fff;border-color:transparent}
  .mn-tab .c{opacity:.7;font-weight:800;margin-left:5px}
  .mcard{background:var(--bg-card);border:1px solid var(--border-light);border-radius:16px;padding:20px;box-shadow:var(--shadow-sm);margin-bottom:14px}
  .mcard-top{display:flex;gap:16px;align-items:flex-start}
  .mcard-av{width:56px;height:56px;border-radius:14px;background:linear-gradient(135deg,#3b82f6,#06b6d4);color:#fff;display:grid;place-items:center;font-weight:800;font-size:1.4rem;flex-shrink:0}
  .mcard-name{font-weight:800;color:var(--text);font-size:1.1rem;display:flex;align-items:center;gap:10px;flex-wrap:wrap}
  .mcard-head{color:var(--text-muted);font-size:.86rem;margin-top:2px}
  .st-badge{font-size:.72rem;font-weight:800;padding:3px 11px;border-radius:99px}
  .mcard-meta{display:flex;gap:20px;flex-wrap:wrap;margin-top:14px;padding-top:14px;border-top:1px solid var(--border-light);color:var(--text-muted);font-size:.85rem}
  .mcard-meta b{color:var(--text);font-weight:700}
  .mcard-bio{color:var(--text-muted);font-size:.88rem;line-height:1.55;margin-top:12px}
  .mcard-acts{display:flex;gap:8px;flex-wrap:wrap;margin-top:16px}
  .ma{border:none;border-radius:10px;padding:9px 16px;font-size:.82rem;font-weight:700;cursor:pointer;color:#fff}
  .ma-approve{background:#059669}.ma-approve:hover{background:#059669}
  .ma-reject{background:#dc2626}.ma-reject:hover{background:#dc2626}
  .ma-suspend{background:#d97706}.ma-suspend:hover{background:#d97706}
  .ma-reinstate{background:#3b82f6}.ma-reinstate:hover{background:#1a56db}
  .ma-form{display:inline}
  .msg{background:rgba(5,150,105,.12);border:1px solid rgba(5,150,105,.3);color:#047857;padding:12px 18px;border-radius:12px;margin-bottom:20px;font-weight:600}
  .empty{text-align:center;padding:60px 20px;color:var(--text-muted)}
  .empty i{font-size:2.6rem;opacity:.4;margin-bottom:12px}
</style>

<div class="mn-wrap">
  <div class="mn-hero">
    <div class="container"><h1><i class="fas fa-chalkboard-user mr-2"></i>Mentors</h1><p>Review applications and manage the live grooming session marketplace.</p></div>
  </div>

  <div class="container" style="margin-top:-46px">
    <?php if ($msg): ?><div class="msg"><i class="fas fa-check-circle mr-1"></i><?= $msg ?></div><?php endif; ?>

    <div class="mn-tabs">
      <?php foreach (['all'=>'All','pending'=>'Pending','approved'=>'Approved','suspended'=>'Suspended','rejected'=>'Rejected'] as $k=>$lbl): ?>
        <a class="mn-tab <?= $filter===$k?'on':'' ?>" href="?status=<?= $k ?>"><?= $lbl ?><span class="c"><?= $counts[$k] ?></span></a>
      <?php endforeach; ?>
    </div>

    <?php if (empty($mentors)): ?>
      <div class="empty"><i class="fas fa-user-slash d-block"></i><h5 style="color:var(--text);font-weight:700">No <?= $filter==='all'?'':$filter ?> mentors</h5><p>Mentor applications will appear here for review.</p></div>
    <?php else: ?>
      <?php foreach ($mentors as $m): $sb = $status_badge[$m['status']] ?? ['#64748b','var(--bg-hover)',ucfirst($m['status'])]; ?>
      <div class="mcard">
        <div class="mcard-top">
          <div class="mcard-av"><?= htmlspecialchars(strtoupper(substr($m['name'],0,1))) ?></div>
          <div style="flex:1;min-width:0">
            <div class="mcard-name">
              <?= htmlspecialchars($m['name']) ?>
              <span class="st-badge" style="color:<?= $sb[0] ?>;background:<?= $sb[1] ?>"><?= $sb[2] ?></span>
            </div>
            <div class="mcard-head"><?= htmlspecialchars($m['headline'] ?: $m['category'].' Mentor') ?> · <?= htmlspecialchars($m['email']) ?><?= $m['phone']?' · '.htmlspecialchars($m['phone']):'' ?></div>
            <?php if (!empty($m['bio'])): ?><div class="mcard-bio"><?= nl2br(htmlspecialchars($m['bio'])) ?></div><?php endif; ?>
            <div class="mcard-meta">
              <span><i class="fas fa-tag mr-1"></i><b><?= htmlspecialchars($m['category']) ?></b></span>
              <span><i class="fas fa-coins mr-1"></i><b><?= nh_price($m['hourly_rate']) ?></b>/session</span>
              <span><i class="fas fa-star mr-1"></i><b><?= number_format((float)$m['rating'],1) ?></b> (<?= (int)$m['total_reviews'] ?>)</span>
              <span><i class="fas fa-video mr-1"></i><b><?= (int)$m['total_sessions'] ?></b> sessions</span>
              <?php if (!empty($m['languages'])): ?><span><i class="fas fa-language mr-1"></i><?= htmlspecialchars($m['languages']) ?></span><?php endif; ?>
              <span><i class="far fa-calendar mr-1"></i>Applied <?= date('M j, Y', strtotime($m['created_at'])) ?></span>
            </div>
            <div class="mcard-acts">
              <?php if ($m['status'] === 'pending'): ?>
                <form class="ma-form" method="POST"><?= csrf_field() ?><input type="hidden" name="mentor_id" value="<?= $m['id'] ?>"><button class="ma ma-approve" name="action" value="approve"><i class="fas fa-check mr-1"></i>Approve</button></form>
                <form class="ma-form" method="POST" onsubmit="return confirm('Reject this application?')"><?= csrf_field() ?><input type="hidden" name="mentor_id" value="<?= $m['id'] ?>"><button class="ma ma-reject" name="action" value="reject"><i class="fas fa-xmark mr-1"></i>Reject</button></form>
              <?php elseif ($m['status'] === 'approved'): ?>
                <form class="ma-form" method="POST" onsubmit="return confirm('Suspend this mentor? They will be hidden from seekers.')"><?= csrf_field() ?><input type="hidden" name="mentor_id" value="<?= $m['id'] ?>"><button class="ma ma-suspend" name="action" value="suspend"><i class="fas fa-pause mr-1"></i>Suspend</button></form>
              <?php elseif ($m['status'] === 'suspended' || $m['status'] === 'rejected'): ?>
                <form class="ma-form" method="POST"><?= csrf_field() ?><input type="hidden" name="mentor_id" value="<?= $m['id'] ?>"><button class="ma ma-reinstate" name="action" value="reinstate"><i class="fas fa-rotate-left mr-1"></i>Reinstate</button></form>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

</body>
</html>
