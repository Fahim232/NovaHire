<?php
require_once __DIR__ . '/../includes/bootstrap.php';

// Already logged in?
if (isset($_SESSION['mentor_id'])) { header('Location: ' . BASE_URL . '/mentor/index.php'); exit; }

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Session expired. Please try again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $pass  = $_POST['password'] ?? '';
        $stmt = mysqli_prepare($con, "SELECT * FROM mentors WHERE email = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $m = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if ($m && password_verify($pass, $m['password'])) {
            $_SESSION['mentor_id']   = $m['id'];
            $_SESSION['mentor_name'] = $m['name'];
            $_SESSION['user_type']   = 'mentor';
            header('Location: ' . BASE_URL . '/mentor/index.php');
            exit;
        }
        $error = 'Invalid email or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<title>Mentor Login · NovaHire</title>
<?php include __DIR__ . '/../includes/links.php'; ?>
<style>
  body{margin:0;font-family:'Inter',sans-serif;background:#0f172a}
  .auth{min-height:100vh;display:grid;grid-template-columns:1.05fr .95fr}
  @media(max-width:860px){.auth{grid-template-columns:1fr}.auth-side{display:none}}
  .auth-side{background:linear-gradient(150deg,#1a56db,#0ea5e9 60%,#a21caf);color:#fff;padding:56px 52px;display:flex;flex-direction:column;justify-content:center;position:relative;overflow:hidden}
  .auth-side::after{content:'';position:absolute;width:420px;height:420px;border-radius:50%;background:rgba(255,255,255,.08);bottom:-160px;right:-120px}
  .auth-side .badge2{display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,.15);padding:7px 15px;border-radius:99px;font-weight:700;font-size:.8rem;width:fit-content;margin-bottom:28px}
  .auth-side h1{font-weight:800;font-size:2.3rem;line-height:1.15;letter-spacing:-.5px}
  .auth-side p{opacity:.9;font-size:1.02rem;margin-top:14px;max-width:420px;line-height:1.6}
  .auth-feats{margin-top:34px;display:flex;flex-direction:column;gap:16px;position:relative;z-index:1}
  .auth-feat{display:flex;gap:13px;align-items:center;font-weight:500}
  .auth-feat i{width:38px;height:38px;border-radius:11px;background:rgba(255,255,255,.16);display:grid;place-items:center;font-size:.95rem}
  .auth-form{display:flex;align-items:center;justify-content:center;padding:40px;background:var(--bg)}
  .auth-card{width:100%;max-width:400px}
  .auth-card h2{font-weight:800;color:var(--text);font-size:1.6rem}
  .auth-card .sub{color:var(--text-muted);margin-bottom:26px}
  .fld{margin-bottom:16px}
  .fld label{font-weight:600;color:var(--text);font-size:.86rem;margin-bottom:6px;display:block}
  .fld input{width:100%;border:1.5px solid var(--border);border-radius:12px;padding:12px 15px;background:var(--bg-card);color:var(--text);font-size:.95rem}
  .fld input:focus{outline:none;border-color:var(--primary);box-shadow:0 0 0 3px rgba(26,86,219,.12)}
  .btn-go{width:100%;background:linear-gradient(135deg,#1a56db,#0ea5e9);color:#fff;border:none;border-radius:12px;padding:13px;font-weight:700;font-size:1rem;cursor:pointer;margin-top:6px}
  .btn-go:hover{opacity:.93}
  .err{background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;padding:11px 15px;border-radius:11px;margin-bottom:18px;font-size:.9rem}
  .alt{text-align:center;margin-top:22px;color:var(--text-muted);font-size:.9rem}
  .alt a{color:var(--primary);font-weight:700;text-decoration:none}
  .back{color:var(--text-muted);text-decoration:none;font-size:.85rem;font-weight:600}
</style>
</head>
<body>
<div class="auth">
  <div class="auth-side">
    <span class="badge2"><i class="fas fa-chalkboard-user"></i> NovaHire Mentors</span>
    <h1>Share your expertise.<br>Earn on your schedule.</h1>
    <p>Run mock interviews, resume reviews and career coaching sessions with job seekers who need your guidance — and get paid for every booking.</p>
    <div class="auth-feats">
      <div class="auth-feat"><i class="fas fa-sack-dollar"></i> Keep 80% of every session you run</div>
      <div class="auth-feat"><i class="fas fa-calendar-check"></i> You control your own availability</div>
      <div class="auth-feat"><i class="fas fa-video"></i> Instant video room for each booking</div>
    </div>
  </div>
  <div class="auth-form">
    <div class="auth-card">
      <a href="<?= BASE_URL ?>/index.php" class="back"><i class="fas fa-arrow-left mr-1"></i>Back to NovaHire</a>
      <h2 class="mt-3">Mentor login</h2>
      <div class="sub">Welcome back — sign in to manage your sessions.</div>
      <?php if ($error): ?><div class="err"><i class="fas fa-circle-exclamation mr-1"></i><?= htmlspecialchars($error) ?></div><?php endif; ?>
      <form method="POST">
        <?= csrf_field() ?>
        <div class="fld"><label>Email address</label><input type="email" name="email" required autofocus placeholder="you@example.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"></div>
        <div class="fld"><label>Password</label><input type="password" name="password" required placeholder="••••••••"></div>
        <button class="btn-go" type="submit">Sign in</button>
      </form>
      <div class="alt">New mentor? <a href="register.php">Apply to join →</a></div>
    </div>
  </div>
</div>
</body>
</html>
