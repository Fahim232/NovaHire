<?php
/**
 * NovaHire — Public Certificate Verification
 * ---------------------------------------------------------------------------
 * Anyone (e.g. an employer) can paste a certificate code and confirm it is
 * genuine. No login required. Only PAID/valid certificates resolve.
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';

$code = strtoupper(trim($_GET['code'] ?? $_POST['code'] ?? ''));
$cert = ($code !== '') ? nh_get_certificate_by_code($con, $code) : null;
$searched = ($code !== '');
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Verify Certificate · NovaHire</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
  :root{--brand:#1a56db;--brand2:#0ea5e9;--ink:#0f172a;--muted:#64748b;--line:#e2e8f0;--bg:#f1f5f9;--ok:#059669;--bad:#dc2626}
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Inter',system-ui,sans-serif;background:
       radial-gradient(circle at 15% 10%,rgba(26,86,219,.12),transparent 40%),
       radial-gradient(circle at 85% 20%,rgba(14,165,233,.10),transparent 40%),var(--bg);
       color:var(--ink);min-height:100vh;display:flex;flex-direction:column;align-items:center;padding:32px 20px}
  .brand{display:flex;align-items:center;gap:10px;font-weight:800;font-size:1.2rem;color:var(--ink);margin-bottom:26px;text-decoration:none}
  .brand .tile{width:38px;height:38px;border-radius:11px;background:linear-gradient(135deg,var(--brand),var(--brand2));color:#fff;display:grid;place-items:center;font-size:1.05rem}
  .brand span b{color:var(--brand)}
  .card{background:#fff;border-radius:22px;box-shadow:0 24px 60px rgba(15,23,42,.14);width:100%;max-width:540px;overflow:hidden}
  .search{padding:26px 28px;border-bottom:1px solid var(--line)}
  .search h1{font-size:1.15rem;font-weight:800;margin-bottom:4px}
  .search p{color:var(--muted);font-size:.88rem;margin-bottom:16px}
  .search form{display:flex;gap:10px}
  .search input{flex:1;border:1.5px solid var(--line);border-radius:12px;padding:12px 14px;font-size:.95rem;font-family:inherit;letter-spacing:1px;text-transform:uppercase}
  .search input:focus{outline:none;border-color:var(--brand);box-shadow:0 0 0 3px rgba(26,86,219,.12)}
  .search button{background:linear-gradient(135deg,var(--brand),var(--brand2));color:#fff;border:none;border-radius:12px;padding:0 22px;font-weight:700;cursor:pointer;font-family:inherit}
  .search button:hover{opacity:.93}

  .result{padding:28px}
  .valid-hd{display:flex;align-items:center;gap:14px;margin-bottom:22px}
  .valid-hd .ic{width:56px;height:56px;border-radius:50%;display:grid;place-items:center;font-size:1.5rem;color:#fff;flex-shrink:0}
  .valid-hd.ok .ic{background:linear-gradient(135deg,#059669,#059669);box-shadow:0 10px 24px rgba(5,150,105,.35)}
  .valid-hd.bad .ic{background:linear-gradient(135deg,#dc2626,#dc2626);box-shadow:0 10px 24px rgba(239,68,68,.3)}
  .valid-hd .t{font-weight:800;font-size:1.15rem}
  .valid-hd .s{color:var(--muted);font-size:.88rem}

  .cert-face{background:linear-gradient(135deg,#1a56db,#0ea5e9);border-radius:16px;color:#fff;padding:26px;position:relative;overflow:hidden}
  .cert-face::after{content:'\f559';font-family:'Font Awesome 6 Free';font-weight:900;position:absolute;right:-10px;bottom:-14px;font-size:7rem;opacity:.12}
  .cert-face .lbl{font-size:.7rem;font-weight:700;letter-spacing:.7px;text-transform:uppercase;opacity:.85}
  .cert-face h2{font-size:1.5rem;font-weight:800;margin:6px 0 18px;line-height:1.2}
  .cert-face .who{opacity:.85;font-size:.82rem}
  .cert-face .name{font-size:1.15rem;font-weight:700;margin-top:2px}
  .facts{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:22px}
  .fact{background:var(--bg);border-radius:12px;padding:14px 16px}
  .fact .k{color:var(--muted);font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.4px;margin-bottom:4px}
  .fact .v{font-weight:700;font-size:.95rem}
  .fact .v.code{font-family:monospace;letter-spacing:1px;color:var(--brand)}
  .stamp{display:flex;align-items:center;gap:8px;justify-content:center;margin-top:22px;color:#059669;font-weight:700;font-size:.85rem}

  .bad-body{text-align:center;color:var(--muted)}
  .bad-body p{margin-top:6px;font-size:.92rem}
  .foot{margin-top:22px;color:var(--muted);font-size:.8rem;text-align:center}
  .foot a{color:var(--brand);text-decoration:none;font-weight:600}
</style>
</head>
<body>
  <a class="brand" href="<?= htmlspecialchars(BASE_URL) ?>/index.php"><span class="tile"><i class="fas fa-briefcase"></i></span><span>Nova<b>Hire</b></span></a>

  <div class="card">
    <div class="search">
      <h1><i class="fas fa-shield-halved mr-1" style="color:var(--brand)"></i> Verify a certificate</h1>
      <p>Enter a NovaHire certificate code to confirm it's authentic.</p>
      <form method="GET" action="includes/verify_certificate.php">
        <input type="text" name="code" placeholder="NH-XXXX-XXXX" value="<?= htmlspecialchars($code) ?>" autocomplete="off" autofocus>
        <button type="submit">Verify</button>
      </form>
    </div>

    <?php if ($searched && $cert): ?>
      <div class="result">
        <div class="valid-hd ok">
          <div class="ic"><i class="fas fa-check"></i></div>
          <div><div class="t">Verified &amp; authentic</div><div class="s">This certificate was issued by NovaHire.</div></div>
        </div>
        <div class="cert-face">
          <div class="lbl">Certificate of Proficiency</div>
          <h2><?= htmlspecialchars($cert['category']) ?></h2>
          <div class="who">Awarded to</div>
          <div class="name"><?= htmlspecialchars($cert['holder_name']) ?></div>
        </div>
        <div class="facts">
          <div class="fact"><div class="k">Issued on</div><div class="v"><?= date('F j, Y', strtotime($cert['issued_at'])) ?></div></div>
          <div class="fact"><div class="k">Certificate code</div><div class="v code"><?= htmlspecialchars($cert['cert_code']) ?></div></div>
          <?php if (!is_null($cert['score'])): ?>
            <div class="fact"><div class="k">Assessment score</div><div class="v"><?= (int)$cert['score'] ?>%</div></div>
          <?php endif; ?>
          <div class="fact"><div class="k">Status</div><div class="v" style="color:#059669">Active</div></div>
        </div>
        <div class="stamp"><i class="fas fa-circle-check"></i> Digitally verified by NovaHire</div>
      </div>
    <?php elseif ($searched): ?>
      <div class="result bad-body">
        <div class="valid-hd bad" style="justify-content:center">
          <div class="ic"><i class="fas fa-xmark"></i></div>
          <div><div class="t">No matching certificate</div><div class="s">We couldn't verify that code.</div></div>
        </div>
        <p>The code <strong><?= htmlspecialchars($code) ?></strong> doesn't match any active NovaHire certificate. Double-check the code, or ask the holder to resend it.</p>
      </div>
    <?php endif; ?>
  </div>

  <div class="foot">
    Certificates are earned by passing skill assessments on NovaHire. <a href="<?= htmlspecialchars(BASE_URL) ?>/index.php">Learn more →</a>
  </div>
</body>
</html>
