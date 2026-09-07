<?php
/**
 * NovaHire — Mentor Portal shared header
 * Emits the full page <head>, top nav and opens <main class="mp-wrap">.
 * A page sets $page_title and $nav_active before including this, then
 * includes mentor_footer.php at the end.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_mentor_login();

$mentor = nh_get_mentor($con, $_SESSION['mentor_id']);
if (!$mentor) { session_destroy(); header('Location: ' . BASE_URL . '/mentor/login.php'); exit; }

$page_title = $page_title ?? 'Mentor Portal';
$nav_active = $nav_active ?? '';
$mp_initial = mb_strtoupper(mb_substr(trim($mentor['name']), 0, 1));
$unread = function_exists('get_unread_count') ? get_unread_count($con, 'mentor', $mentor['id']) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<title><?= htmlspecialchars($page_title) ?> · NovaHire Mentor</title>
<?php include __DIR__ . '/../includes/links.php'; ?>
<style>
  body{background:var(--bg);color:var(--text);font-family:'Inter',sans-serif}
  .mp-nav{position:sticky;top:0;z-index:1030;background:var(--bg-card);border-bottom:1px solid var(--border-light)}
  .mp-nav-in{display:flex;align-items:center;gap:18px;height:64px;max-width:1200px;margin:0 auto;padding:0 24px}
  .mp-brand{display:flex;align-items:center;gap:11px;text-decoration:none!important;flex-shrink:0}
  .mp-brand-tile{width:38px;height:38px;border-radius:11px;background:linear-gradient(140deg,#1a56db,#0ea5e9);color:#fff;display:grid;place-items:center;font-weight:800}
  .mp-brand b{color:var(--text);font-weight:800;font-size:.98rem;letter-spacing:-.3px;line-height:1}
  .mp-brand small{color:var(--text-muted);font-size:.62rem;font-weight:700;text-transform:uppercase;letter-spacing:.12em}
  .mp-links{display:flex;gap:4px;list-style:none;margin:0 0 0 20px;padding:0}
  .mp-link{display:flex;align-items:center;gap:8px;padding:8px 14px;border-radius:10px;color:var(--text)!important;font-size:.86rem;font-weight:600;text-decoration:none!important;transition:.2s}
  .mp-link i{color:var(--text-muted);font-size:.9rem}
  .mp-link:hover{background:var(--bg-hover);color:var(--primary)!important}
  .mp-link:hover i{color:var(--primary)}
  .mp-link.on{background:rgba(26,86,219,.1);color:var(--primary)!important}
  .mp-link.on i{color:var(--primary)}
  .mp-right{margin-left:auto;display:flex;align-items:center;gap:10px}
  .mp-ghost{width:38px;height:38px;border:none;border-radius:11px;background:var(--bg-hover);color:var(--text);display:grid;place-items:center;cursor:pointer;text-decoration:none!important;position:relative}
  .mp-ghost:hover{background:rgba(26,86,219,.12);color:var(--primary)}
  .mp-bdg{position:absolute;top:-4px;right:-4px;min-width:16px;height:16px;padding:0 4px;border-radius:99px;background:linear-gradient(140deg,#dc2626,#f97316);color:#fff;font-size:.56rem;font-weight:800;display:grid;place-items:center}
  .mp-user{display:flex;align-items:center;gap:9px;padding:5px 12px 5px 5px;border:1px solid var(--border-light);border-radius:99px}
  .mp-av{width:30px;height:30px;border-radius:50%;background:linear-gradient(140deg,#1a56db,#0ea5e9);color:#fff;display:grid;place-items:center;font-weight:700;font-size:.8rem}
  .mp-user span{font-size:.8rem;font-weight:700;color:var(--text);max-width:130px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .mp-wrap{max-width:1200px;margin:0 auto;padding:28px 24px 60px}
  .mp-pend{background:linear-gradient(135deg,#fffbeb,#fef3c7);border:1px solid #fde68a;color:#92400e;padding:14px 20px;border-radius:14px;margin-bottom:24px;font-weight:600;display:flex;gap:12px;align-items:center}
  [data-theme=dark] .mp-pend{background:#422006;border-color:#78350f;color:#fcd34d}
  .mp-susp{background:#fef2f2;border-color:#fecaca;color:#b91c1c}
  [data-theme=dark] .mp-susp{background:#450a0a;border-color:#7f1d1d;color:#fca5a5}
  @media(max-width:760px){.mp-links{display:none}.mp-user span{display:none}}
</style>
</head>
<body>
<nav class="mp-nav">
  <div class="mp-nav-in">
    <a class="mp-brand" href="index.php">
      <span class="mp-brand-tile"><i class="fas fa-chalkboard-user"></i></span>
      <span><b>NovaHire</b><br><small>Mentor Portal</small></span>
    </a>
    <ul class="mp-links">
      <li><a class="mp-link <?= $nav_active==='dashboard'?'on':'' ?>" href="index.php"><i class="fas fa-grip"></i>Dashboard</a></li>
      <li><a class="mp-link <?= $nav_active==='sessions'?'on':'' ?>" href="sessions.php"><i class="fas fa-video"></i>Sessions</a></li>
      <li><a class="mp-link <?= $nav_active==='availability'?'on':'' ?>" href="availability.php"><i class="fas fa-calendar-plus"></i>Availability</a></li>
    </ul>
    <div class="mp-right">
      <button class="mp-ghost" type="button" onclick="mpTheme()" title="Toggle theme"><i class="fas fa-moon" id="mpThemeIcon"></i></button>
      <div class="mp-user">
        <span class="mp-av"><?= htmlspecialchars($mp_initial) ?></span>
        <span><?= htmlspecialchars($mentor['name']) ?></span>
      </div>
      <a class="mp-ghost" href="logout.php" title="Log out"><i class="fas fa-sign-out-alt"></i></a>
    </div>
  </div>
</nav>
<main class="mp-wrap">
<?php if ($mentor['status'] === 'pending'): ?>
  <div class="mp-pend"><i class="fas fa-clock fa-lg"></i><div>Your mentor profile is <strong>pending admin approval</strong>. You can set up your availability now — you'll appear to job seekers and start receiving bookings once approved.</div></div>
<?php elseif ($mentor['status'] === 'suspended' || $mentor['status'] === 'rejected'): ?>
  <div class="mp-pend mp-susp"><i class="fas fa-circle-exclamation fa-lg"></i><div>Your mentor account is currently <strong><?= htmlspecialchars($mentor['status']) ?></strong> and not visible to job seekers. Please contact the NovaHire team.</div></div>
<?php endif; ?>
<script>
  function mpTheme(){
    var cur=localStorage.getItem('company-theme')==='dark'?'dark':'light';
    var next=cur==='dark'?'light':'dark';
    localStorage.setItem('company-theme',next);
    document.documentElement.setAttribute('data-theme',next);
    var i=document.getElementById('mpThemeIcon');
    if(i){i.classList.toggle('fa-sun',next==='dark');i.classList.toggle('fa-moon',next!=='dark');}
  }
  (function(){var t=localStorage.getItem('company-theme')==='dark'?'dark':'light';var i=document.getElementById('mpThemeIcon');if(i){i.classList.toggle('fa-sun',t==='dark');i.classList.toggle('fa-moon',t!=='dark');}})();
</script>
