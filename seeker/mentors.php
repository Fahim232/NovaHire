<?php
require_once __DIR__ . '/../includes/bootstrap.php';
if (!isset($_SESSION['id'])) { header('location: ' . BASE_URL . '/auth/login.php'); exit(); }
require_once __DIR__ . '/../includes/header.php';

$filters = [];
if (!empty($_GET['category'])) $filters['category'] = $_GET['category'];
if (!empty($_GET['q']))        $filters['search']   = trim($_GET['q']);

$mentors = nh_get_mentors($con, $filters);
$cats    = nh_mentor_categories($con);
$types   = nh_session_types();
?>
<style>
  .mn-wrap{max-width:1100px;margin:0 auto;padding:0 20px 60px}
  .mn-hero{background:linear-gradient(135deg,#1a56db,#0ea5e9);border-radius:var(--radius-xl);padding:34px 32px;color:#fff;margin-bottom:24px}
  .mn-hero h1{font-weight:800;font-size:1.9rem;letter-spacing:-.5px}
  .mn-hero p{opacity:.92;max-width:620px;margin-top:8px}
  .mn-types{display:flex;gap:12px;flex-wrap:wrap;margin-top:18px}
  .mn-type{background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.2);border-radius:12px;padding:9px 14px;font-size:.85rem;font-weight:600;display:flex;gap:8px;align-items:center}
  .mn-tools{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:22px}
  .mn-tools input,.mn-tools select{border:1px solid var(--border);border-radius:var(--radius-md);padding:10px 14px;background:var(--bg-card);color:var(--text)}
  .mn-tools input{flex:1;min-width:200px}
  .mn-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:18px}
  .mc{background:var(--bg-card);border:1px solid var(--border-light);border-radius:var(--radius-lg);padding:22px;box-shadow:var(--shadow-xs);transition:.18s;display:flex;flex-direction:column}
  .mc:hover{transform:translateY(-3px);box-shadow:var(--shadow-lg)}
  .mc-top{display:flex;gap:14px;align-items:center}
  .mc-av{width:60px;height:60px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--secondary));color:#fff;display:grid;place-items:center;font-weight:800;font-size:1.4rem;flex-shrink:0}
  .mc-name{font-weight:700;color:var(--text);font-size:1.1rem}
  .mc-head{color:var(--text-muted);font-size:.85rem}
  .mc-rate{display:flex;align-items:center;gap:5px;color:#d97706;font-weight:700;font-size:.85rem;margin-top:3px}
  .mc-bio{color:var(--text-muted);font-size:.88rem;margin:14px 0;line-height:1.55;flex:1}
  .mc-cat{display:inline-block;background:var(--bg-hover);color:var(--text-muted);font-size:.72rem;font-weight:600;padding:3px 10px;border-radius:var(--radius-full);margin-bottom:6px}
  .mc-foot{display:flex;justify-content:space-between;align-items:center;border-top:1px solid var(--border-light);padding-top:14px}
  .mc-price{font-weight:800;color:var(--text);font-size:1.2rem}
  .mc-price small{color:var(--text-muted);font-weight:600;font-size:.72rem}
  .empty{text-align:center;padding:60px 20px;color:var(--text-muted)}
  .empty i{font-size:2.6rem;color:var(--text-light);margin-bottom:14px}
</style>

<div class="mn-wrap">
  <div class="mn-hero">
    <h1><i class="fas fa-chalkboard-user mr-2"></i>Live Grooming Sessions</h1>
    <p>Book a 1-on-1 video session with an industry mentor. Practice interviews, polish your CV, or map your next career move.</p>
    <div class="mn-types">
      <?php foreach ($types as $t): ?>
        <span class="mn-type"><i class="fas <?= $t['icon'] ?>"></i><?= htmlspecialchars($t['label']) ?></span>
      <?php endforeach; ?>
    </div>
  </div>

  <form class="mn-tools" method="GET">
    <input type="text" name="q" placeholder="Search mentors by name or expertise…" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
    <select name="category" onchange="this.form.submit()">
      <option value="">All categories</option>
      <?php foreach ($cats as $c): ?>
        <option value="<?= htmlspecialchars($c) ?>" <?= ($_GET['category']??'')===$c?'selected':'' ?>><?= htmlspecialchars($c) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-primary px-4" type="submit"><i class="fas fa-search"></i></button>
  </form>

  <?php if (empty($mentors)): ?>
    <div class="empty">
      <i class="fas fa-user-clock d-block"></i>
      <h5 style="color:var(--text);font-weight:700">No mentors available yet</h5>
      <p>Mentors are being onboarded. Please check back shortly.</p>
    </div>
  <?php else: ?>
    <div class="mn-grid">
      <?php foreach ($mentors as $m): ?>
      <div class="mc">
        <div class="mc-top">
          <div class="mc-av"><?= htmlspecialchars(strtoupper(substr($m['name'],0,1))) ?></div>
          <div>
            <div class="mc-name"><?= htmlspecialchars($m['name']) ?></div>
            <div class="mc-head"><?= htmlspecialchars($m['headline'] ?: $m['category'].' Mentor') ?></div>
            <div class="mc-rate">
              <i class="fas fa-star"></i><?= number_format((float)$m['rating'],1) ?>
              <span style="color:var(--text-light);font-weight:500">(<?= (int)$m['total_reviews'] ?> reviews · <?= (int)$m['total_sessions'] ?> sessions)</span>
            </div>
          </div>
        </div>
        <div class="mc-bio">
          <span class="mc-cat"><?= htmlspecialchars($m['category']) ?></span><br>
          <?= htmlspecialchars($m['bio'] ? (mb_strlen($m['bio'])>130?mb_substr($m['bio'],0,130).'…':$m['bio']) : 'Experienced '.$m['category'].' professional ready to help you grow.') ?>
        </div>
        <div class="mc-foot">
          <div class="mc-price"><?= nh_price($m['hourly_rate']) ?><small>/session</small></div>
          <a href="book_session.php?mentor=<?= (int)$m['id'] ?>" class="btn btn-primary rounded-pill px-4">Book</a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
