<?php
require_once __DIR__ . '/../includes/bootstrap.php';

if (isset($_SESSION['mentor_id'])) { header('Location: ' . BASE_URL . '/mentor/index.php'); exit; }

$categories = ['Finance','Engineering','Sales','HR','Marketing','IT / Software','Design','Data Science','Healthcare','Education','Legal','Media','Consulting','Business','Other'];
$error = null;
$old = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = $_POST;
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Session expired. Please try again.';
    } else {
        $name     = trim($_POST['name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $phone    = trim($_POST['phone'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $headline = trim($_POST['headline'] ?? '');
        $bio      = trim($_POST['bio'] ?? '');
        $rate     = (float)($_POST['hourly_rate'] ?? 0);
        $langs    = trim($_POST['languages'] ?? '');
        $pass     = $_POST['password'] ?? '';

        if ($name === '' || $email === '' || $pass === '' || $category === '') {
            $error = 'Please fill in all required fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (strlen($pass) < 6) {
            $error = 'Password must be at least 6 characters.';
        } elseif ($rate < 100 || $rate > 100000) {
            $error = 'Please set a session price between ৳100 and ৳100,000.';
        } else {
            // unique email?
            $chk = mysqli_prepare($con, "SELECT id FROM mentors WHERE email = ? LIMIT 1");
            mysqli_stmt_bind_param($chk, "s", $email);
            mysqli_stmt_execute($chk);
            $exists = mysqli_fetch_assoc(mysqli_stmt_get_result($chk));
            mysqli_stmt_close($chk);
            if ($exists) {
                $error = 'An account with this email already exists. Try logging in.';
            } else {
                $hash = password_hash($pass, PASSWORD_BCRYPT);
                $stmt = mysqli_prepare($con, "INSERT INTO mentors (name, email, phone, password, category, headline, bio, hourly_rate, languages, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
                mysqli_stmt_bind_param($stmt, "sssssssds", $name, $email, $phone, $hash, $category, $headline, $bio, $rate, $langs);
                if (mysqli_stmt_execute($stmt)) {
                    $mid = mysqli_insert_id($con);
                    mysqli_stmt_close($stmt);
                    // notify admin
                    if (function_exists('create_notification')) {
                        create_notification($con, 'admin', 0, 'system', null, 'New mentor application', htmlspecialchars($name) . ' applied to become a ' . htmlspecialchars($category) . ' mentor.', 'system', 'mentors', $mid);
                    }
                    // Send mentor application alert to admin
                    require_once __DIR__ . '/../includes/mail.php';
                    $admin_email = null;
                    $admin_email_r = @mysqli_query($con, "SELECT setting_value FROM site_settings WHERE setting_key='smtp_from_email' LIMIT 1");
                    if ($admin_email_r && $ar = mysqli_fetch_assoc($admin_email_r)) $admin_email = $ar['setting_value'];
                    if ($admin_email) send_mentor_application_alert($admin_email, $name, $email, $category);
                    $_SESSION['mentor_id']   = $mid;
                    $_SESSION['mentor_name'] = $name;
                    $_SESSION['user_type']   = 'mentor';
                    header('Location: ' . BASE_URL . '/mentor/index.php?welcome=1');
                    exit;
                }
                mysqli_stmt_close($stmt);
                $error = 'Something went wrong. Please try again.';
            }
        }
    }
}
$pricing = nh_pricing();
$commission = (int)$pricing['session_commission_pct'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<title>Become a Mentor · NovaHire</title>
<?php include __DIR__ . '/../includes/links.php'; ?>
<style>
  body{margin:0;font-family:'Inter',sans-serif;background:var(--bg)}
  .reg-top{background:linear-gradient(135deg,#1a56db,#0ea5e9 60%,#a21caf);color:#fff;padding:44px 20px 90px;text-align:center}
  .reg-top .badge2{display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,.15);padding:7px 15px;border-radius:99px;font-weight:700;font-size:.8rem;margin-bottom:16px}
  .reg-top h1{font-weight:800;font-size:2.1rem;letter-spacing:-.5px}
  .reg-top p{opacity:.92;max-width:560px;margin:10px auto 0;line-height:1.6}
  .reg-perks{display:flex;gap:24px;justify-content:center;flex-wrap:wrap;margin-top:22px}
  .reg-perk{display:flex;gap:9px;align-items:center;font-weight:600;font-size:.9rem}
  .reg-card{max-width:720px;margin:-64px auto 60px;background:var(--bg-card);border:1px solid var(--border-light);border-radius:20px;box-shadow:0 30px 60px -30px rgba(15,23,42,.3);padding:34px}
  .reg-card h2{font-weight:800;color:var(--text);font-size:1.35rem;margin-bottom:4px}
  .reg-card .sub{color:var(--text-muted);margin-bottom:24px;font-size:.92rem}
  .grid2{display:grid;grid-template-columns:1fr 1fr;gap:16px}
  @media(max-width:600px){.grid2{grid-template-columns:1fr}}
  .fld{margin-bottom:16px}
  .fld label{font-weight:600;color:var(--text);font-size:.85rem;margin-bottom:6px;display:block}
  .fld .req{color:#dc2626}
  .fld input,.fld select,.fld textarea{width:100%;border:1.5px solid var(--border);border-radius:11px;padding:11px 14px;background:var(--bg);color:var(--text);font-size:.93rem;font-family:inherit}
  .fld input:focus,.fld select:focus,.fld textarea:focus{outline:none;border-color:var(--primary);box-shadow:0 0 0 3px rgba(26,86,219,.12)}
  .fld small{color:var(--text-muted);font-size:.78rem}
  .rate-wrap{position:relative}
  .rate-wrap span{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-weight:700}
  .rate-wrap input{padding-left:30px}
  .btn-go{width:100%;background:linear-gradient(135deg,#1a56db,#0ea5e9);color:#fff;border:none;border-radius:12px;padding:14px;font-weight:700;font-size:1rem;cursor:pointer;margin-top:8px}
  .btn-go:hover{opacity:.93}
  .err{background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;padding:12px 16px;border-radius:11px;margin-bottom:18px;font-size:.9rem}
  .alt{text-align:center;margin-top:20px;color:var(--text-muted);font-size:.9rem}
  .alt a{color:var(--primary);font-weight:700;text-decoration:none}
  .payout-note{background:rgba(5,150,105,.1);border:1px solid rgba(5,150,105,.25);color:#047857;border-radius:12px;padding:12px 16px;font-size:.85rem;margin-bottom:22px;font-weight:600}
  [data-theme=dark] .payout-note{background:rgba(5,150,105,.12);color:#6ee7b7}
</style>
</head>
<body>
<div class="reg-top">
  <span class="badge2"><i class="fas fa-chalkboard-user"></i> Become a NovaHire Mentor</span>
  <h1>Turn your experience into income</h1>
  <p>Help job seekers ace interviews, sharpen their CVs and plan their careers — and earn for every session you run.</p>
  <div class="reg-perks">
    <span class="reg-perk"><i class="fas fa-sack-dollar"></i> Keep <?= 100 - $commission ?>% per session</span>
    <span class="reg-perk"><i class="fas fa-clock"></i> Flexible hours</span>
    <span class="reg-perk"><i class="fas fa-globe"></i> Work from anywhere</span>
  </div>
</div>

<div class="reg-card">
  <h2>Create your mentor profile</h2>
  <div class="sub">It takes 2 minutes. Your profile goes live once our team approves it.</div>
  <div class="payout-note"><i class="fas fa-circle-info mr-1"></i>You set your own price per session and keep <?= 100 - $commission ?>% — NovaHire takes a <?= $commission ?>% platform fee.</div>

  <?php if ($error): ?><div class="err"><i class="fas fa-circle-exclamation mr-1"></i><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <form method="POST">
    <?= csrf_field() ?>
    <div class="grid2">
      <div class="fld"><label>Full name <span class="req">*</span></label><input type="text" name="name" required value="<?= htmlspecialchars($old['name'] ?? '') ?>"></div>
      <div class="fld"><label>Email <span class="req">*</span></label><input type="email" name="email" required value="<?= htmlspecialchars($old['email'] ?? '') ?>"></div>
    </div>
    <div class="grid2">
      <div class="fld"><label>Phone</label><input type="text" name="phone" value="<?= htmlspecialchars($old['phone'] ?? '') ?>"></div>
      <div class="fld"><label>Password <span class="req">*</span></label><input type="password" name="password" required minlength="6" placeholder="At least 6 characters"></div>
    </div>
    <div class="grid2">
      <div class="fld">
        <label>Area of expertise <span class="req">*</span></label>
        <select name="category" required>
          <option value="">Select a category…</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?= htmlspecialchars($c) ?>" <?= ($old['category'] ?? '')===$c?'selected':'' ?>><?= htmlspecialchars($c) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="fld">
        <label>Price per session <span class="req">*</span></label>
        <div class="rate-wrap"><span>৳</span><input type="number" name="hourly_rate" required min="100" max="100000" step="50" placeholder="1500" value="<?= htmlspecialchars($old['hourly_rate'] ?? '') ?>"></div>
        <small>Typical range: ৳800 – ৳3,000</small>
      </div>
    </div>
    <div class="fld"><label>Professional headline</label><input type="text" name="headline" placeholder="e.g. Senior Software Engineer at a top tech firm" value="<?= htmlspecialchars($old['headline'] ?? '') ?>"></div>
    <div class="fld"><label>Languages</label><input type="text" name="languages" placeholder="e.g. English, Bangla" value="<?= htmlspecialchars($old['languages'] ?? '') ?>"></div>
    <div class="fld"><label>About you</label><textarea name="bio" rows="4" placeholder="Tell seekers about your experience and how you can help them…"><?= htmlspecialchars($old['bio'] ?? '') ?></textarea></div>
    <button class="btn-go" type="submit">Submit application</button>
  </form>
  <div class="alt">Already a mentor? <a href="login.php">Log in →</a></div>
</div>
</body>
</html>
