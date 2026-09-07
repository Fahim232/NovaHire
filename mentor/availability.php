<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_mentor_login();

$mentor_id = $_SESSION['mentor_id'];
$msg = null; $err = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $err = 'Session expired. Please try again.';
    } elseif (isset($_POST['add_slot'])) {
        $date = $_POST['slot_date'] ?? '';
        $time = $_POST['slot_time'] ?? '';
        $dur  = (int)($_POST['duration_min'] ?? 45);
        $dur  = in_array($dur, [30,45,60,90]) ? $dur : 45;
        $when = strtotime($date . ' ' . $time);
        if (!$when) {
            $err = 'Please pick a valid date and time.';
        } elseif ($when < time()) {
            $err = 'That time is in the past — choose a future slot.';
        } else {
            // avoid exact duplicates
            $dupe = @mysqli_query($con, "SELECT id FROM mentor_slots WHERE mentor_id=" . (int)$mentor_id . " AND slot_date='" . mysqli_real_escape_string($con,$date) . "' AND slot_time='" . mysqli_real_escape_string($con,$time) . "' LIMIT 1");
            if ($dupe && mysqli_num_rows($dupe) > 0) {
                $err = 'You already have a slot at that time.';
            } elseif (nh_add_mentor_slot($con, $mentor_id, $date, $time, $dur)) {
                $msg = 'Slot added — seekers can now book it.';
            } else {
                $err = 'Could not add slot. Please try again.';
            }
        }
    } elseif (isset($_POST['delete_slot'])) {
        $sid = (int)$_POST['slot_id'];
        // only delete own, unbooked slots
        @mysqli_query($con, "DELETE FROM mentor_slots WHERE id=" . $sid . " AND mentor_id=" . (int)$mentor_id . " AND is_booked=0");
        $msg = 'Slot removed.';
    }
}

// upcoming slots (open + booked) for display
$sql = "SELECT * FROM mentor_slots WHERE mentor_id=? AND CONCAT(slot_date,' ',slot_time) >= NOW() ORDER BY slot_date, slot_time";
$stmt = mysqli_prepare($con, $sql);
mysqli_stmt_bind_param($stmt, "i", $mentor_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$slots = [];
while ($r = mysqli_fetch_assoc($res)) $slots[] = $r;
mysqli_stmt_close($stmt);

// group by date
$by_date = [];
foreach ($slots as $s) $by_date[$s['slot_date']][] = $s;

$page_title = 'Availability';
$nav_active = 'availability';
require __DIR__ . '/mentor_header.php';
?>
<style>
  .av-hi{font-weight:800;color:var(--text);font-size:1.6rem;letter-spacing:-.5px}
  .av-sub{color:var(--text-muted);margin-bottom:24px}
  .av-cols{display:grid;grid-template-columns:340px 1fr;gap:22px;align-items:start}
  @media(max-width:820px){.av-cols{grid-template-columns:1fr}}
  .panel{background:var(--bg-card);border:1px solid var(--border-light);border-radius:16px;padding:22px;box-shadow:var(--shadow-xs)}
  .panel h3{font-weight:700;color:var(--text);font-size:1.05rem;margin-bottom:16px;display:flex;align-items:center;gap:9px}
  .add-panel{position:sticky;top:88px}
  .fld{margin-bottom:14px}
  .fld label{font-weight:600;color:var(--text);font-size:.84rem;margin-bottom:6px;display:block}
  .fld input,.fld select{width:100%;border:1.5px solid var(--border);border-radius:11px;padding:11px 13px;background:var(--bg);color:var(--text);font-size:.92rem;font-family:inherit}
  .fld input:focus,.fld select:focus{outline:none;border-color:var(--primary);box-shadow:0 0 0 3px rgba(26,86,219,.12)}
  .btn-add{width:100%;background:linear-gradient(135deg,#1a56db,#0ea5e9);color:#fff;border:none;border-radius:11px;padding:12px;font-weight:700;cursor:pointer}
  .btn-add:hover{opacity:.93}
  .daygrp{margin-bottom:22px}
  .day-h{font-weight:700;color:var(--text);font-size:.9rem;margin-bottom:10px;display:flex;align-items:center;gap:8px}
  .day-h span{color:var(--text-muted);font-weight:600;font-size:.8rem}
  .slot-row{display:flex;flex-wrap:wrap;gap:10px}
  .slot-chip{display:flex;align-items:center;gap:10px;border:1.5px solid var(--border);border-radius:12px;padding:9px 12px;background:var(--bg)}
  .slot-chip.booked{border-color:#059669;background:rgba(5,150,105,.08)}
  .slot-chip .t{font-weight:700;color:var(--text);font-size:.88rem}
  .slot-chip .d{color:var(--text-muted);font-size:.75rem}
  .slot-chip .bk{color:#059669;font-size:.72rem;font-weight:700}
  .slot-chip form{margin:0}
  .slot-del{background:none;border:none;color:var(--text-light);cursor:pointer;font-size:.85rem;padding:2px 4px}
  .slot-del:hover{color:#dc2626}
  .msg{padding:12px 16px;border-radius:11px;margin-bottom:18px;font-weight:600;font-size:.9rem}
  .msg.ok{background:rgba(5,150,105,.12);border:1px solid rgba(5,150,105,.3);color:#047857}
  .msg.no{background:#fef2f2;border:1px solid #fecaca;color:#b91c1c}
  .empty{text-align:center;padding:44px 16px;color:var(--text-muted)}
  .empty i{font-size:2.4rem;color:var(--text-light);margin-bottom:12px}
  .tip{font-size:.8rem;color:var(--text-muted);margin-top:12px;line-height:1.5}
</style>

<div class="av-hi">Your availability</div>
<div class="av-sub">Add the time slots you're free. Seekers book directly into these — you'll get a video link automatically.</div>

<?php if ($msg): ?><div class="msg ok"><i class="fas fa-check-circle mr-1"></i><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="msg no"><i class="fas fa-circle-exclamation mr-1"></i><?= htmlspecialchars($err) ?></div><?php endif; ?>

<div class="av-cols">
  <div class="panel add-panel">
    <h3><i class="fas fa-calendar-plus" style="color:var(--primary)"></i>Add a slot</h3>
    <form method="POST">
      <?= csrf_field() ?>
      <div class="fld"><label>Date</label><input type="date" name="slot_date" required min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>"></div>
      <div class="fld"><label>Start time</label><input type="time" name="slot_time" required value="10:00"></div>
      <div class="fld">
        <label>Duration</label>
        <select name="duration_min">
          <option value="30">30 minutes</option>
          <option value="45" selected>45 minutes</option>
          <option value="60">60 minutes</option>
          <option value="90">90 minutes</option>
        </select>
      </div>
      <button class="btn-add" type="submit" name="add_slot" value="1"><i class="fas fa-plus mr-1"></i>Add slot</button>
    </form>
    <div class="tip"><i class="fas fa-lightbulb mr-1"></i>Tip: add several slots across different days to get more bookings. Booked slots can't be deleted.</div>
  </div>

  <div class="panel">
    <h3><i class="fas fa-calendar-week" style="color:var(--primary)"></i>Upcoming slots</h3>
    <?php if (empty($slots)): ?>
      <div class="empty"><i class="fas fa-calendar-plus d-block"></i><p>No slots yet. Add your first one on the left to start receiving bookings.</p></div>
    <?php else: ?>
      <?php foreach ($by_date as $date => $daySlots): ?>
        <div class="daygrp">
          <div class="day-h"><i class="far fa-calendar" style="color:var(--primary)"></i><?= date('l, F j', strtotime($date)) ?> <span>· <?= count($daySlots) ?> slot<?= count($daySlots)>1?'s':'' ?></span></div>
          <div class="slot-row">
            <?php foreach ($daySlots as $s): ?>
              <div class="slot-chip <?= $s['is_booked']?'booked':'' ?>">
                <div>
                  <div class="t"><?= date('g:i A', strtotime($s['slot_time'])) ?></div>
                  <div class="d"><?= (int)$s['duration_min'] ?> min<?= $s['is_booked']?' · <span class="bk">Booked</span>':'' ?></div>
                </div>
                <?php if (!$s['is_booked']): ?>
                  <form method="POST" onsubmit="return confirm('Remove this slot?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="slot_id" value="<?= (int)$s['id'] ?>">
                    <button class="slot-del" name="delete_slot" value="1" title="Remove"><i class="fas fa-trash-can"></i></button>
                  </form>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/mentor_footer.php'; ?>
