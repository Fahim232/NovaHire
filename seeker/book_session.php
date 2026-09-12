<?php
require_once __DIR__ . '/../includes/bootstrap.php';
if (!isset($_SESSION['id'])) { header('location: ' . BASE_URL . '/auth/login.php'); exit(); }

$user_id   = $_SESSION['id'];
$mentor_id = (int)($_GET['mentor'] ?? 0);
$mentor    = nh_get_mentor($con, $mentor_id);
$error     = null;

if (!$mentor || $mentor['status'] !== 'approved') {
    require_once __DIR__ . '/../includes/header.php';
    echo '<div style="max-width:600px;margin:60px auto;text-align:center;color:var(--text-muted)"><h4>Mentor not found</h4><a href="mentors.php" class="btn btn-primary mt-2">Back to mentors</a></div>';
    exit;
}

// Handle booking submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Session expired. Please try again.';
    } else {
        $slot_id = (int)($_POST['slot_id'] ?? 0);
        $stype   = $_POST['session_type'] ?? 'mock_interview';
        if (!array_key_exists($stype, nh_session_types())) $stype = 'mock_interview';
        $sid = nh_create_session_booking($con, $user_id, $mentor_id, $slot_id, $stype);
        if ($sid) {
            header('Location: ' . BASE_URL . '/api/checkout.php?purpose=session&item_id=' . $sid);
            exit;
        }
        $error = 'That time slot is no longer available. Please pick another.';
    }
}

$slots = nh_get_mentor_slots($con, $mentor_id, true);
$types = nh_session_types();

require_once __DIR__ . '/../includes/header.php';
?>
<style>
  .bk-wrap{max-width:960px;margin:0 auto;padding:0 20px 60px}
  .bk-grid{display:grid;grid-template-columns:320px 1fr;gap:24px;align-items:start}
  @media(max-width:800px){.bk-grid{grid-template-columns:1fr}}
  .card-b{background:var(--bg-card);border:1px solid var(--border-light);border-radius:var(--radius-xl);padding:26px;box-shadow:var(--shadow-md)}
  .prof{text-align:center;position:sticky;top:96px}
  .prof-av{width:88px;height:88px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--secondary));color:#fff;display:grid;place-items:center;font-weight:800;font-size:2rem;margin:0 auto 14px}
  .prof h3{font-weight:800;color:var(--text);font-size:1.25rem}
  .prof .h{color:var(--text-muted);font-size:.9rem}
  .prof .rate{margin:14px 0;padding:14px;background:var(--bg-hover);border-radius:var(--radius-md)}
  .prof .rate .n{font-size:1.7rem;font-weight:800;color:var(--primary)}
  .stars{color:#d97706;font-weight:700}
  .sec-t{font-weight:700;color:var(--text);margin-bottom:12px;font-size:1.05rem}
  .type-opts{display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-bottom:24px}
  @media(max-width:600px){.type-opts{grid-template-columns:1fr}}
  .type-opt{border:1.5px solid var(--border);border-radius:var(--radius-md);padding:14px;cursor:pointer;text-align:center;transition:.15s}
  .type-opt i{font-size:1.3rem;color:var(--primary);margin-bottom:6px;display:block}
  .type-opt .l{font-weight:700;color:var(--text);font-size:.88rem}
  .type-opt.sel{border-color:var(--primary);background:rgba(26,86,219,.06)}
  .slot-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:10px}
  .slot{border:1.5px solid var(--border);border-radius:var(--radius-md);padding:11px;cursor:pointer;text-align:center;transition:.15s}
  .slot .d{font-weight:700;color:var(--text);font-size:.85rem}
  .slot .t{color:var(--text-muted);font-size:.8rem}
  .slot.sel{border-color:var(--primary);background:rgba(26,86,219,.06)}
  .err{background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;padding:12px 16px;border-radius:var(--radius-md);margin-bottom:18px}
  .no-slots{color:var(--text-muted);padding:20px;text-align:center;background:var(--bg-hover);border-radius:var(--radius-md)}
</style>

<div class="bk-wrap">
  <a href="mentors.php" class="btn btn-link pl-0 mb-2"><i class="fas fa-arrow-left mr-1"></i>All mentors</a>
  <div class="bk-grid">
    <div class="card-b prof">
      <div class="prof-av"><?= htmlspecialchars(strtoupper(substr($mentor['name'],0,1))) ?></div>
      <h3><?= htmlspecialchars($mentor['name']) ?></h3>
      <div class="h"><?= htmlspecialchars($mentor['headline'] ?: $mentor['category'].' Mentor') ?></div>
      <div class="rate">
        <div class="n"><?= nh_price($mentor['hourly_rate']) ?></div>
        <div style="font-size:.78rem;color:var(--text-muted);font-weight:600">PER SESSION</div>
      </div>
      <div class="stars"><i class="fas fa-star"></i> <?= number_format((float)$mentor['rating'],1) ?>
        <span style="color:var(--text-light);font-weight:500">· <?= (int)$mentor['total_sessions'] ?> sessions</span>
      </div>
      <?php if (!empty($mentor['languages'])): ?><div style="margin-top:10px;color:var(--text-muted);font-size:.82rem"><i class="fas fa-language mr-1"></i><?= htmlspecialchars($mentor['languages']) ?></div><?php endif; ?>
      <p style="margin-top:14px;color:var(--text-muted);font-size:.85rem;text-align:left;line-height:1.6"><?= nl2br(htmlspecialchars($mentor['bio'] ?: '')) ?></p>
    </div>

    <div class="card-b">
      <?php if ($error): ?><div class="err"><i class="fas fa-circle-exclamation mr-1"></i><?= htmlspecialchars($error) ?></div><?php endif; ?>
      <form method="POST" id="bookForm">
        <?= csrf_field() ?>
        <input type="hidden" name="session_type" id="session_type" value="mock_interview">
        <input type="hidden" name="slot_id" id="slot_id" value="">

        <div class="sec-t">1. What do you need help with?</div>
        <div class="type-opts">
          <?php $first=true; foreach ($types as $k=>$t): ?>
            <div class="type-opt <?= $first?'sel':'' ?>" data-type="<?= $k ?>">
              <i class="fas <?= $t['icon'] ?>"></i>
              <div class="l"><?= htmlspecialchars($t['label']) ?></div>
            </div>
          <?php $first=false; endforeach; ?>
        </div>

        <div class="sec-t">2. Pick an available time</div>
        <?php if (empty($slots)): ?>
          <div class="no-slots"><i class="fas fa-calendar-xmark mr-1"></i>This mentor has no open slots right now. Check back soon.</div>
        <?php else: ?>
          <div class="slot-grid">
            <?php foreach ($slots as $s):
              $ts = strtotime($s['slot_date'].' '.$s['slot_time']); ?>
              <div class="slot" data-slot="<?= (int)$s['id'] ?>">
                <div class="d"><?= date('D, M j', $ts) ?></div>
                <div class="t"><?= date('g:i A', $ts) ?> · <?= (int)$s['duration_min'] ?>m</div>
              </div>
            <?php endforeach; ?>
          </div>
          <button type="submit" class="btn btn-primary btn-lg btn-block mt-4 rounded-pill" id="bookBtn" disabled>
            <i class="fas fa-lock mr-2"></i>Continue to secure checkout
          </button>
          <p style="text-align:center;color:var(--text-muted);font-size:.8rem;margin-top:10px">You'll confirm payment on the next step. <?= nh_price($mentor['hourly_rate']) ?> total.</p>
        <?php endif; ?>
      </form>
    </div>
  </div>
</div>

<script>
  document.querySelectorAll('.type-opt').forEach(function(el){
    el.addEventListener('click',function(){
      document.querySelectorAll('.type-opt').forEach(x=>x.classList.remove('sel'));
      el.classList.add('sel');
      document.getElementById('session_type').value = el.dataset.type;
    });
  });
  document.querySelectorAll('.slot').forEach(function(el){
    el.addEventListener('click',function(){
      document.querySelectorAll('.slot').forEach(x=>x.classList.remove('sel'));
      el.classList.add('sel');
      document.getElementById('slot_id').value = el.dataset.slot;
      var b=document.getElementById('bookBtn'); if(b) b.disabled=false;
    });
  });
</script>
