<?php
require_once __DIR__ . '/../includes/bootstrap.php';
if (!isset($_SESSION['id'])) { header('location: ' . BASE_URL . '/auth/login.php'); exit(); }

$user_id = $_SESSION['id'];

// Handle review submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $sid = (int)$_POST['session_id'];
        $sess = nh_get_session($con, $sid);
        if ($sess && $sess['user_id'] == $user_id && $sess['status'] === 'completed') {
            nh_add_session_review($con, $sid, $user_id, $sess['mentor_id'], (int)$_POST['rating'], trim($_POST['comment'] ?? ''));
        }
    }
    header('Location: my_sessions.php?reviewed=1');
    exit;
}

require_once __DIR__ . '/../includes/header.php';
$sessions = nh_user_sessions($con, $user_id);

$status_meta = [
  'confirmed' => ['#059669','Confirmed','fa-circle-check'],
  'completed' => ['#3b82f6','Completed','fa-flag-checkered'],
  'cancelled' => ['#dc2626','Cancelled','fa-ban'],
];
?>
<style>
  .ms-wrap{max-width:860px;margin:0 auto;padding:0 20px 60px}
  .ms-head h1{font-weight:800;color:var(--text);font-size:1.8rem;letter-spacing:-.5px}
  .ms-head p{color:var(--text-muted)}
  .ses{background:var(--bg-card);border:1px solid var(--border-light);border-radius:var(--radius-lg);padding:20px 22px;margin-bottom:14px;box-shadow:var(--shadow-xs)}
  .ses-top{display:flex;gap:14px;align-items:center}
  .ses-av{width:50px;height:50px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--secondary));color:#fff;display:grid;place-items:center;font-weight:800;font-size:1.15rem;flex-shrink:0}
  .ses-title{font-weight:700;color:var(--text)}
  .ses-sub{color:var(--text-muted);font-size:.86rem}
  .ses-status{margin-left:auto;padding:5px 13px;border-radius:var(--radius-full);font-size:.76rem;font-weight:700;color:#fff}
  .ses-when{display:flex;gap:18px;flex-wrap:wrap;margin:14px 0 0;padding-top:14px;border-top:1px solid var(--border-light);color:var(--text-muted);font-size:.87rem}
  .ses-when b{color:var(--text)}
  .join{background:linear-gradient(135deg,#1a56db,#0ea5e9);color:#fff;font-weight:700;border-radius:var(--radius-full);padding:9px 20px;text-decoration:none;display:inline-flex;align-items:center;gap:8px}
  .join:hover{color:#fff;opacity:.92}
  .rev-form{margin-top:14px;padding-top:14px;border-top:1px solid var(--border-light)}
  .stars-in{direction:rtl;display:inline-flex;gap:4px;font-size:1.4rem}
  .stars-in input{display:none}
  .stars-in label{color:var(--border);cursor:pointer;transition:.1s}
  .stars-in input:checked ~ label,.stars-in label:hover,.stars-in label:hover ~ label{color:#d97706}
  .empty{text-align:center;padding:60px 20px;color:var(--text-muted)}
  .empty i{font-size:2.6rem;color:var(--text-light);margin-bottom:14px}
  .okmsg{background:rgba(5,150,105,.12);border:1px solid rgba(5,150,105,.3);color:#047857;padding:14px 18px;border-radius:var(--radius-md);margin-bottom:20px;font-weight:600}
</style>

<div class="ms-wrap">
  <div class="ms-head"><h1><i class="fas fa-calendar-check mr-2" style="color:var(--primary)"></i>My Sessions</h1><p>Your booked grooming sessions and video links.</p></div>

  <?php if (isset($_GET['booked'])): ?><div class="okmsg"><i class="fas fa-circle-check mr-1"></i>Session booked! Your video link is ready below.</div><?php endif; ?>
  <?php if (isset($_GET['reviewed'])): ?><div class="okmsg"><i class="fas fa-star mr-1"></i>Thanks for your feedback!</div><?php endif; ?>

  <?php if (empty($sessions)): ?>
    <div class="empty">
      <i class="fas fa-calendar-plus d-block"></i>
      <h5 style="color:var(--text);font-weight:700">No sessions yet</h5>
      <p>Book a 1-on-1 with an expert mentor to sharpen your interview and career skills.</p>
      <a href="mentors.php" class="btn btn-primary rounded-pill px-4">Browse mentors</a>
    </div>
  <?php else: ?>
    <?php foreach ($sessions as $s):
      $meta = $status_meta[$s['status']] ?? ['#64748b',ucfirst($s['status']),'fa-circle'];
      $ts = strtotime($s['scheduled_at']);
      $upcoming = $ts > time();
      $reviewed = nh_session_has_review($con, $s['id']);
    ?>
    <div class="ses">
      <div class="ses-top">
        <div class="ses-av"><?= htmlspecialchars(strtoupper(substr($s['mentor_name'],0,1))) ?></div>
        <div>
          <div class="ses-title"><?= htmlspecialchars(nh_session_type_label($s['session_type'])) ?></div>
          <div class="ses-sub">with <?= htmlspecialchars($s['mentor_name']) ?> · <?= htmlspecialchars($s['mentor_headline'] ?: '') ?></div>
        </div>
        <span class="ses-status" style="background:<?= $meta[0] ?>"><i class="fas <?= $meta[2] ?> mr-1"></i><?= $meta[1] ?></span>
      </div>
      <div class="ses-when">
        <span><i class="far fa-calendar mr-1"></i><b><?= date('l, M j, Y', $ts) ?></b></span>
        <span><i class="far fa-clock mr-1"></i><b><?= date('g:i A', $ts) ?></b> · <?= (int)$s['duration_min'] ?> min</span>
        <span class="ml-auto"><?= nh_price($s['price']) ?></span>
      </div>

      <?php if ($s['status'] === 'confirmed' && $s['meeting_link']): ?>
        <div style="margin-top:14px">
          <a href="<?= htmlspecialchars($s['meeting_link']) ?>" target="_blank" rel="noopener" class="join"><i class="fas fa-video"></i><?= $upcoming ? 'Join session' : 'Open room' ?></a>
          <span style="color:var(--text-muted);font-size:.82rem;margin-left:10px"><?= $upcoming ? 'Link opens your live video room.' : 'Session time has passed.' ?></span>
        </div>
      <?php endif; ?>

      <?php if ($s['status'] === 'completed' && !$reviewed): ?>
        <form class="rev-form" method="POST">
          <?= csrf_field() ?>
          <input type="hidden" name="session_id" value="<?= (int)$s['id'] ?>">
          <div style="font-weight:600;color:var(--text);margin-bottom:8px">How was your session?</div>
          <div class="stars-in">
            <?php for ($r=5;$r>=1;$r--): ?>
              <input type="radio" name="rating" id="r<?= $s['id'] ?>_<?= $r ?>" value="<?= $r ?>" <?= $r===5?'checked':'' ?>>
              <label for="r<?= $s['id'] ?>_<?= $r ?>"><i class="fas fa-star"></i></label>
            <?php endfor; ?>
          </div>
          <textarea name="comment" class="form-control mt-2" rows="2" placeholder="Share a few words (optional)"></textarea>
          <button class="btn btn-primary btn-sm rounded-pill mt-2 px-4" name="submit_review" value="1">Submit review</button>
        </form>
      <?php elseif ($s['status'] === 'completed' && $reviewed): ?>
        <div style="margin-top:12px;color:var(--success);font-size:.85rem;font-weight:600"><i class="fas fa-check mr-1"></i>You reviewed this session</div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
