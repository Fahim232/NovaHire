<?php
require_once __DIR__ . '/../includes/bootstrap.php';
if (!isset($_SESSION['id'])) { header('location: ' . BASE_URL . '/auth/login.php'); exit(); }
require_once __DIR__ . '/../admin/dbcon.php';
require_once __DIR__ . '/../ai/cover_letter.php';

$user_id = $_SESSION['id'];

require_once __DIR__ . '/../includes/premium.php';
$access = nh_check_access($con, $user_id, 'ai_cover_letter');
if (!$access['allowed']) {
    nh_render_pro_gate('ai_cover_letter');
    exit;
}

$user = mysqli_fetch_assoc(mysqli_query($con, "SELECT * FROM user_info WHERE id = '$user_id'"));
$username = htmlspecialchars($user['username'] ?? 'User');
$initial = strtoupper(substr($user['username'] ?? 'U', 0, 1));

$jobs = [];
$jq = mysqli_query($con, "SELECT cj.*, c.company_name FROM company_jobs cj JOIN companies c ON cj.company_id = c.id WHERE cj.status='active' ORDER BY cj.posted_date DESC");
if ($jq) while ($j = mysqli_fetch_assoc($jq)) $jobs[] = $j;

$saved = [];
$sq = mysqli_query($con, "SELECT cl.*, cj.job_title, c.company_name FROM ai_cover_letters cl LEFT JOIN company_jobs cj ON cl.job_id=cj.id LEFT JOIN companies c ON cj.company_id=c.id WHERE cl.user_id='$user_id' ORDER BY cl.created_at DESC LIMIT 10");
if ($sq) while ($s = mysqli_fetch_assoc($sq)) $saved[] = $s;

$letter = null;
$selected_job = null;

if (isset($_POST['generate'])) {
    $job_id  = intval($_POST['job_id']);
    $tone    = in_array($_POST['tone'] ?? '', ['professional','formal','friendly','confident']) ? $_POST['tone'] : 'professional';
    foreach ($jobs as $j) if ($j['id'] == $job_id) $selected_job = $j;
    if ($selected_job) {
        $letter = ai_generate_cover_letter($user, $selected_job, $tone);
        $stmt = mysqli_prepare($con, "INSERT INTO ai_cover_letters (user_id, job_id, title, content, mode) VALUES (?, ?, ?, ?, ?)");
        $title = $letter['title']; $content = $letter['content']; $mode = $letter['mode'];
        mysqli_stmt_bind_param($stmt, "iisss", $user_id, $job_id, $title, $content, $mode);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Cover Letter Generator | NovaHire</title>
    <?php include __DIR__ . '/../includes/links.php'; ?>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
    <style>
    :root{--cl-primary:#1a56db;--cl-secondary:#0ea5e9;--cl-grad:linear-gradient(135deg,#1a56db,#0ea5e9);--cl-bg:#f8fafc;--cl-card:#fff;--cl-text:#1e293b;--cl-muted:#64748b;--cl-border:#e2e8f0;--cl-success:#059669;--cl-warning:#d97706;--cl-danger:#dc2626;--cl-radius:16px;}
    body{background:var(--cl-bg);margin:0;font-family:'Plus Jakarta Sans',sans-serif;}

    .cl-topbar{background:linear-gradient(135deg,var(--cl-primary),var(--cl-secondary));padding:5px 0;color:#fff;font-size:.75rem;}
    .cl-topbar .container-fluid{display:flex;justify-content:flex-end;align-items:center;gap:18px;}
    .cl-topbar a{color:rgba(255,255,255,.85);text-decoration:none;}
    .cl-nav{background:rgba(255,255,255,.92);backdrop-filter:blur(20px);border-bottom:1px solid rgba(226,232,240,.5);padding:0;position:sticky;top:0;z-index:9990;}
    .cl-nav-inner{max-width:1340px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;padding:0 24px;height:60px;}
    .cl-brand{display:flex;align-items:center;gap:10px;text-decoration:none;}
    .cl-brand-icon{width:36px;height:36px;background:var(--cl-grad);border-radius:10px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:.9rem;}
    .cl-brand-text{font-family:'Plus Jakarta Sans',sans-serif;font-size:1.25rem;font-weight:800;color:var(--cl-text);}
    .cl-brand-text span{background:var(--cl-grad);-webkit-background-clip:text;-webkit-text-fill-color:transparent;}
    .cl-nav-links{display:flex;align-items:center;gap:4px;}
    .cl-nav-link{color:var(--cl-muted);font-weight:600;padding:8px 14px;border-radius:9999px;font-size:.85rem;text-decoration:none;transition:.2s;display:flex;align-items:center;gap:6px;}
    .cl-nav-link:hover{color:var(--cl-primary);background:rgba(26,86,219,.06);}
    .cl-nav-link.active{color:var(--cl-primary);background:rgba(26,86,219,.1);font-weight:700;}
    .cl-nav-right{display:flex;align-items:center;gap:10px;}
    .cl-user-pill{display:flex;align-items:center;gap:8px;padding:4px 14px 4px 4px;border-radius:9999px;border:1.5px solid var(--cl-border);background:#fff;text-decoration:none;}
    .cl-user-avatar{width:32px;height:32px;border-radius:50%;background:var(--cl-grad);display:flex;align-items:center;justify-content:center;color:#fff;font-size:.75rem;font-weight:700;}
    .cl-user-name{font-weight:700;font-size:.82rem;color:var(--cl-text);}
    .cl-back{display:inline-flex;align-items:center;gap:6px;padding:7px 16px;border-radius:9999px;font-size:.82rem;font-weight:600;color:var(--cl-primary);text-decoration:none;border:1.5px solid var(--cl-border);transition:.2s;}
    .cl-back:hover{background:var(--cl-primary);color:#fff;border-color:var(--cl-primary);}

    /* Hamburger */
    .cl-hamburger{display:none;background:none;border:none;cursor:pointer;padding:8px;position:relative;width:40px;height:40px;}
    .cl-hamburger span{display:block;width:22px;height:2px;background:var(--cl-text);border-radius:2px;position:absolute;left:9px;transition:.3s;}
    .cl-hamburger span:nth-child(1){top:12px;}
    .cl-hamburger span:nth-child(2){top:19px;}
    .cl-hamburger span:nth-child(3){top:26px;}
    .cl-hamburger.open span:nth-child(1){top:19px;transform:rotate(45deg);}
    .cl-hamburger.open span:nth-child(2){opacity:0;}
    .cl-hamburger.open span:nth-child(3){top:19px;transform:rotate(-45deg);}
    .cl-mobile-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:9998;opacity:0;transition:.3s;}
    .cl-mobile-overlay.show{opacity:1;}
    .cl-mobile-drawer{display:none;position:fixed;top:0;left:0;width:280px;height:100%;background:#fff;z-index:9999;transform:translateX(-100%);transition:.3s cubic-bezier(.4,0,.2,1);padding:24px;overflow-y:auto;}
    .cl-mobile-drawer.show{transform:translateX(0);}
    .cl-mobile-drawer .cl-brand{margin-bottom:24px;}
    .cl-mobile-drawer .cl-mob-link{display:flex;align-items:center;gap:10px;padding:12px 14px;border-radius:12px;color:var(--cl-muted);font-weight:600;font-size:.9rem;text-decoration:none;transition:.2s;}
    .cl-mobile-drawer .cl-mob-link:hover,.cl-mobile-drawer .cl-mob-link.active{color:var(--cl-primary);background:rgba(26,86,219,.08);}
    .cl-mobile-drawer .cl-mob-link i{width:20px;text-align:center;}
    .cl-mobile-divider{height:1px;background:var(--cl-border);margin:12px 0;}
    .cl-mobile-close{position:absolute;top:16px;right:16px;background:none;border:none;font-size:1.2rem;color:var(--cl-muted);cursor:pointer;}

    /* Scroll to Top */
    .cl-scroll-top{position:fixed;bottom:24px;right:24px;width:44px;height:44px;border-radius:50%;background:var(--cl-grad);color:#fff;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:1rem;box-shadow:0 4px 16px rgba(26,86,219,.3);opacity:0;transform:translateY(20px);transition:.3s;z-index:9989;}
    .cl-scroll-top.show{opacity:1;transform:translateY(0);}
    .cl-scroll-top:hover{transform:translateY(-2px);box-shadow:0 6px 24px rgba(26,86,219,.4);}

    /* Focus States */
    .cl-tone:focus-visible,.cl-generate-btn:focus-visible,.cl-history-item:focus-visible{outline:2px solid var(--cl-primary);outline-offset:2px;}

    .cl-hero{background:var(--cl-grad);color:#fff;padding:44px 0 32px;position:relative;overflow:hidden;}
    .cl-hero::before{content:'';position:absolute;top:-40%;right:-10%;width:380px;height:380px;background:radial-gradient(circle,rgba(255,255,255,.12) 0%,transparent 70%);border-radius:50%;}
    .cl-hero h1{font-weight:800;font-size:1.7rem;margin:0 0 4px;}
    .cl-hero p{opacity:.88;font-size:.95rem;margin:0;}
    .cl-badge{background:rgba(255,255,255,.2);padding:4px 14px;border-radius:20px;font-size:.75rem;font-weight:600;display:inline-flex;align-items:center;gap:6px;margin-bottom:12px;}

    .cl-layout{display:grid;grid-template-columns:360px 1fr;gap:24px;padding:28px 0 60px;}
    .cl-card{background:var(--cl-card);border:1px solid var(--cl-border);border-radius:var(--cl-radius);padding:24px;box-shadow:0 4px 18px rgba(26,86,219,.05);}
    .cl-card h4{font-weight:700;color:var(--cl-text);font-size:1rem;margin:0 0 14px;display:flex;align-items:center;gap:8px;}
    .cl-card h4 i{color:var(--cl-primary);font-size:.9rem;}

    .cl-select{width:100%;border:2px solid var(--cl-border);border-radius:12px;padding:11px 14px;font-size:.88rem;color:var(--cl-text);background:var(--cl-card);outline:none;transition:.2s;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%2364748b' viewBox='0 0 16 16'%3E%3Cpath d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 14px center;}
    .cl-select:focus{border-color:var(--cl-primary);box-shadow:0 0 0 3px rgba(26,86,219,.1);}
    .cl-label{font-size:.75rem;font-weight:700;color:var(--cl-muted);text-transform:uppercase;letter-spacing:.4px;margin-bottom:6px;display:block;}

    .cl-tones{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;}
    .cl-tone{padding:7px 16px;border-radius:9999px;border:1.5px solid var(--cl-border);background:var(--cl-card);font-size:.78rem;font-weight:600;color:var(--cl-muted);cursor:pointer;transition:.2s;}
    .cl-tone:hover{border-color:var(--cl-primary);color:var(--cl-primary);}
    .cl-tone.active{background:var(--cl-primary);color:#fff;border-color:var(--cl-primary);box-shadow:0 2px 8px rgba(26,86,219,.3);}

    .cl-job-preview{background:#f8fafc;border:1px solid var(--cl-border);border-radius:12px;padding:16px;margin-top:12px;display:none;}
    .cl-job-preview.show{display:block;animation:clFadeIn .3s ease;}
    .cl-job-preview .jp-title{font-weight:700;color:var(--cl-text);font-size:.92rem;}
    .cl-job-preview .jp-company{color:var(--cl-primary);font-size:.82rem;font-weight:600;margin-top:2px;}
    .cl-job-preview .jp-meta{display:flex;gap:8px;margin-top:10px;flex-wrap:wrap;}
    .cl-job-preview .jp-tag{font-size:.72rem;font-weight:600;padding:4px 10px;border-radius:8px;background:#eef2ff;color:#1e40af;}

    .cl-generate-btn{width:100%;padding:14px;border:none;border-radius:9999px;background:var(--cl-grad);color:#fff;font-size:.95rem;font-weight:700;cursor:pointer;transition:.25s cubic-bezier(.34,1.56,.64,1);box-shadow:0 6px 20px rgba(26,86,219,.3);display:flex;align-items:center;justify-content:center;gap:8px;margin-top:16px;}
    .cl-generate-btn:hover{transform:translateY(-2px);box-shadow:0 10px 28px rgba(26,86,219,.4);}
    .cl-generate-btn:active{transform:scale(.97);}
    .cl-generate-btn:disabled{opacity:.6;cursor:not-allowed;transform:none;box-shadow:none;}
    .cl-generate-btn .cl-spinner{display:none;width:18px;height:18px;border:2.5px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:clSpin .7s linear infinite;}
    .cl-generate-btn.loading .cl-spinner{display:inline-block;}
    .cl-generate-btn.loading .cl-btn-text{display:none;}
    @keyframes clSpin{to{transform:rotate(360deg);}}
    @keyframes clFadeIn{from{opacity:0;transform:translateY(6px);}to{opacity:1;transform:none;}}

    .cl-score-box{text-align:center;padding:20px 0;margin-top:12px;}
    .cl-score-ring{position:relative;width:110px;height:110px;margin:0 auto 10px;}
    .cl-score-ring svg{transform:rotate(-90deg);width:110px;height:110px;}
    .cl-score-ring .ring-bg{fill:none;stroke:#e2e8f0;stroke-width:8;}
    .cl-score-ring .ring-fill{fill:none;stroke:var(--cl-primary);stroke-width:8;stroke-linecap:round;transition:stroke-dashoffset 1s ease;stroke-dasharray:283;stroke-dashoffset:283;}
    .cl-score-val{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;}
    .cl-score-val .num{font-size:1.6rem;font-weight:800;color:var(--cl-text);line-height:1;}
    .cl-score-val .lbl{font-size:.6rem;font-weight:700;color:var(--cl-muted);text-transform:uppercase;letter-spacing:.5px;margin-top:2px;}
    .cl-score-label{font-size:.82rem;font-weight:700;margin-top:6px;}

    .cl-matched{display:flex;flex-wrap:wrap;gap:6px;margin-top:10px;}
    .cl-matched-chip{display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:8px;font-size:.72rem;font-weight:600;background:#d1fae5;color:#065f46;}
    .cl-matched-chip i{font-size:.6rem;}

    .cl-empty{text-align:center;padding:60px 20px;color:var(--cl-muted);}
    .cl-empty i{font-size:3rem;color:#c7d2fe;margin-bottom:14px;display:block;}
    .cl-empty h4{color:var(--cl-text);font-weight:700;font-size:1.1rem;margin:0 0 6px;}
    .cl-empty p{font-size:.88rem;margin:0;}

    .cl-result{animation:clFadeIn .4s ease;}
    .cl-result-header{display:flex;flex-wrap:wrap;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:16px;}
    .cl-result-header h4{font-weight:700;font-size:1.05rem;margin:0;}
    .cl-result-meta{font-size:.78rem;color:var(--cl-muted);margin-top:2px;}
    .cl-result-actions{display:flex;gap:8px;flex-wrap:wrap;}
    .cl-result-actions button{padding:8px 18px;border-radius:9999px;font-size:.82rem;font-weight:600;cursor:pointer;transition:.2s;border:none;display:inline-flex;align-items:center;gap:6px;}
    .cl-btn-outline{background:#fff;color:var(--cl-primary);border:1.5px solid var(--cl-primary);}
    .cl-btn-outline:hover{background:var(--cl-primary);color:#fff;}
    .cl-btn-fill{background:var(--cl-grad);color:#fff;box-shadow:0 4px 12px rgba(26,86,219,.25);}
    .cl-btn-fill:hover{transform:translateY(-1px);box-shadow:0 6px 16px rgba(26,86,219,.35);}

    .cl-letter{background:#fff;border:1px solid var(--cl-border);border-radius:var(--cl-radius);padding:32px;font-size:.9rem;line-height:1.85;color:#334155;white-space:pre-wrap;box-shadow:0 4px 18px rgba(0,0,0,.03);position:relative;}
    .cl-letter-typed::after{content:'|';animation:clBlink .8s step-end infinite;color:var(--cl-primary);font-weight:300;}
    @keyframes clBlink{0%,100%{opacity:1;}50%{opacity:0;}}

    .cl-history{margin-top:24px;}
    .cl-history h4{font-weight:700;color:var(--cl-text);font-size:1rem;margin:0 0 14px;display:flex;align-items:center;gap:8px;}
    .cl-history h4 i{color:var(--cl-primary);font-size:.85rem;}
    .cl-history-item{display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border:1px solid var(--cl-border);border-radius:12px;margin-bottom:8px;cursor:pointer;transition:.2s;}
    .cl-history-item:hover{border-color:var(--cl-primary);background:#f8fafc;}
    .cl-history-item .hi-title{font-weight:600;font-size:.85rem;color:var(--cl-text);}
    .cl-history-item .hi-meta{font-size:.72rem;color:var(--cl-muted);margin-top:2px;}
    .cl-history-item .hi-mode{font-size:.65rem;font-weight:700;padding:3px 8px;border-radius:6px;background:#eef2ff;color:#1e40af;}
    .cl-history-item .hi-mode.llm{background:#d1fae5;color:#065f46;}

    .cl-toast{position:fixed;bottom:24px;right:24px;z-index:10001;background:var(--cl-card);border-radius:12px;padding:14px 20px;box-shadow:0 12px 40px rgba(0,0,0,.12);display:flex;align-items:center;gap:10px;font-size:.88rem;font-weight:600;color:var(--cl-text);border-left:4px solid var(--cl-success);transform:translateY(20px);opacity:0;transition:.3s;}
    .cl-toast.show{transform:translateY(0);opacity:1;}
    .cl-toast i{color:var(--cl-success);font-size:1.1rem;}

    @media(max-width:991px){
        .cl-layout{grid-template-columns:1fr;padding:20px 0 50px;}
        .cl-nav-links{display:none;}
        .cl-hamburger{display:block;}
        .cl-mobile-overlay,.cl-mobile-drawer{display:block;}
        .cl-back{display:none;}
    }
    @media(max-width:575px){
        .cl-hero{padding:32px 0 24px;}
        .cl-hero h1{font-size:1.3rem;}
        .cl-card{padding:18px;}
        .cl-letter{padding:20px;font-size:.85rem;}
        .cl-result-actions{width:100%;}
        .cl-result-actions button{flex:1;justify-content:center;}
        .cl-tones{gap:6px;}
        .cl-tone{padding:6px 12px;font-size:.72rem;}
        .cl-nav-inner{padding:0 14px;}
    }
    @media print{
        .cl-topbar,.cl-nav,.cl-hero,.cl-left-panel,.cl-result-actions,.cl-history,.cl-toast{display:none !important;}
        body{background:#fff !important;margin:0;padding:0;}
        .cl-layout{grid-template-columns:1fr;padding:0;}
        .cl-letter{border:none;box-shadow:none;padding:0;font-size:11pt;line-height:1.7;color:#000;}
        .cl-card{box-shadow:none;border:none;}
    }
    </style>
</head>
<body>

<!-- Navbar -->
<div class="cl-topbar"><div class="container-fluid"><a href="seeker_dashboard.php"><i class="fas fa-home mr-1"></i> Dashboard</a></div></div>
<nav class="cl-nav">
    <div class="cl-nav-inner">
        <a class="cl-brand" href="seeker_dashboard.php">
            <div class="cl-brand-icon"><i class="fas fa-layer-group"></i></div>
            <div class="cl-brand-text">Nova<span>Hire</span></div>
        </a>
        <div class="cl-nav-links">
            <a class="cl-nav-link" href="seeker_dashboard.php"><i class="fas fa-home"></i> Home</a>
            <a class="cl-nav-link" href="browse_jobs.php"><i class="fas fa-briefcase"></i> Jobs</a>
            <a class="cl-nav-link active" href="ai_hub.php"><i class="fas fa-robot"></i> AI Center</a>
        </div>
        <div class="cl-nav-right">
            <a class="cl-back" href="ai_hub.php"><i class="fas fa-arrow-left"></i> AI Hub</a>
            <button class="cl-hamburger" id="clHamburger" aria-label="Toggle menu"><span></span><span></span><span></span></button>
            <a class="cl-user-pill" href="profile.php">
                <div class="cl-user-avatar"><?php echo $initial; ?></div>
                <span class="cl-user-name"><?php echo $username; ?></span>
            </a>
        </div>
    </div>
</nav>

<!-- Mobile Drawer -->
<div class="cl-mobile-overlay" id="clMobileOverlay"></div>
<div class="cl-mobile-drawer" id="clMobileDrawer">
    <button class="cl-mobile-close" id="clMobileClose" aria-label="Close menu"><i class="fas fa-xmark"></i></button>
    <a class="cl-brand" href="seeker_dashboard.php">
        <div class="cl-brand-icon"><i class="fas fa-layer-group"></i></div>
        <div class="cl-brand-text">Nova<span>Hire</span></div>
    </a>
    <a class="cl-mob-link" href="seeker_dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
    <a class="cl-mob-link" href="browse_jobs.php"><i class="fas fa-briefcase"></i> Browse Jobs</a>
    <div class="cl-mobile-divider"></div>
    <a class="cl-mob-link active" href="ai_hub.php"><i class="fas fa-robot"></i> AI Center</a>
    <a class="cl-mob-link" href="ai_resume_analyzer.php"><i class="fas fa-file-lines"></i> Resume Analyzer</a>
    <a class="cl-mob-link" href="ai_cover_letter_generator.php"><i class="fas fa-envelope-open-text"></i> Cover Letter</a>
    <a class="cl-mob-link" href="ai_grooming_coach.php"><i class="fas fa-graduation-cap"></i> Grooming Coach</a>
    <a class="cl-mob-link" href="ai_mock_interview.php"><i class="fas fa-clipboard-question"></i> Mock Interview</a>
    <a class="cl-mob-link" href="ai_assistant.php"><i class="fas fa-headset"></i> Career Assistant</a>
    <div class="cl-mobile-divider"></div>
    <a class="cl-mob-link" href="profile.php"><i class="fas fa-user"></i> My Profile</a>
    <a class="cl-mob-link" href="auth/logout.php"><i class="fas fa-right-from-bracket"></i> Logout</a>
</div>

<!-- Hero -->
<div class="cl-hero">
    <div class="container">
        <div class="cl-badge"><i class="fas fa-wand-magic-sparkles"></i> AI-Powered</div>
        <h1>Cover Letter Generator</h1>
        <p>Pick a job, choose your tone, and let AI craft a personalised cover letter from your real profile.</p>
    </div>
</div>

<!-- Content -->
<div class="container">
    <div class="cl-layout">
        <!-- LEFT PANEL -->
        <div class="cl-left-panel">
            <div class="cl-card">
                <h4><i class="fas fa-briefcase"></i> Choose a Job</h4>
                <?php if (empty($jobs)): ?>
                    <p style="font-size:.85rem;color:var(--cl-muted);">No active jobs available right now.</p>
                <?php else: ?>
                    <label class="cl-label">Job Position</label>
                    <select class="cl-select" id="clJobSelect">
                        <option value="">Select a job...</option>
                        <?php foreach ($jobs as $j): ?>
                            <option value="<?php echo $j['id']; ?>"
                                data-title="<?php echo htmlspecialchars($j['job_title']); ?>"
                                data-company="<?php echo htmlspecialchars($j['company_name']); ?>"
                                data-cat="<?php echo htmlspecialchars($j['job_category'] ?? ''); ?>"
                                data-skills="<?php echo htmlspecialchars($j['skills_required'] ?? ''); ?>"
                                data-location="<?php echo htmlspecialchars($j['location'] ?? ''); ?>"
                                data-salary="<?php echo htmlspecialchars($j['salary_range'] ?? ''); ?>"
                                <?php echo (isset($_POST['job_id']) && $_POST['job_id'] == $j['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($j['job_title'] . ' - ' . $j['company_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <div class="cl-job-preview" id="clJobPreview">
                        <div class="jp-title" id="jpTitle"></div>
                        <div class="jp-company" id="jpCompany"></div>
                        <div class="jp-meta" id="jpMeta"></div>
                    </div>

                    <label class="cl-label" style="margin-top:16px;">Tone</label>
                    <div class="cl-tones" id="clTones">
                        <button type="button" class="cl-tone active" data-tone="professional">Professional</button>
                        <button type="button" class="cl-tone" data-tone="formal">Formal</button>
                        <button type="button" class="cl-tone" data-tone="friendly">Friendly</button>
                        <button type="button" class="cl-tone" data-tone="confident">Confident</button>
                    </div>

                    <form method="POST" id="clForm">
                        <input type="hidden" name="job_id" id="clJobId" value="">
                        <input type="hidden" name="tone" id="clToneInput" value="professional">
                        <button type="submit" name="generate" class="cl-generate-btn" id="clGenBtn">
                            <span class="cl-btn-text"><i class="fas fa-wand-magic-sparkles"></i> Generate Letter</span>
                            <span class="cl-spinner"></span>
                        </button>
                    </form>

                    <hr style="margin:18px 0;border-color:var(--cl-border);">

                    <h4 style="font-size:.85rem;"><i class="fas fa-circle-info" style="color:var(--cl-primary);"></i> How it works</h4>
                    <p style="font-size:.78rem;color:var(--cl-muted);line-height:1.6;">The AI matches your skills against the job requirements, calculates a compatibility score, and writes a personalised letter. With an LLM key configured, OpenAI/Gemini drafts the text; otherwise the smart template engine uses your real data.</p>
                <?php endif; ?>
            </div>

            <?php if (!empty($saved)): ?>
            <div class="cl-card cl-history">
                <h4><i class="fas fa-clock-rotate-left"></i> Recent Letters</h4>
                <?php foreach ($saved as $s): ?>
                    <div class="cl-history-item" data-content="<?php echo htmlspecialchars($s['content'], ENT_QUOTES); ?>" data-title="<?php echo htmlspecialchars($s['job_title'] ?? 'Untitled', ENT_QUOTES); ?>" data-company="<?php echo htmlspecialchars($s['company_name'] ?? '', ENT_QUOTES); ?>">
                        <div>
                            <div class="hi-title"><?php echo htmlspecialchars($s['job_title'] ?? 'Untitled'); ?></div>
                            <div class="hi-meta"><?php echo htmlspecialchars($s['company_name'] ?? ''); ?> &bull; <?php echo date('M d, Y', strtotime($s['created_at'])); ?></div>
                        </div>
                        <span class="hi-mode <?php echo $s['mode'] === 'llm' ? 'llm' : ''; ?>"><?php echo $s['mode'] === 'llm' ? 'AI' : 'Template'; ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- RIGHT PANEL -->
        <div id="clRightPanel">
            <?php if ($letter): ?>
                <div class="cl-card cl-result">
                    <div class="cl-result-header">
                        <div>
                            <h4><i class="fas fa-file-lines" style="color:var(--cl-primary);margin-right:6px;"></i><?php echo htmlspecialchars($letter['title']); ?></h4>
                            <div class="cl-result-meta">
                                Generated for <?php echo htmlspecialchars($selected_job['company_name']); ?>
                                &bull; <?php echo $letter['mode'] === 'llm' ? 'AI (LLM)' : 'Smart Template'; ?>
                                &bull; <?php
                                    $g = $letter['greeting'] ?? '';
                                    $toneLabel = 'Professional';
                                    if (strpos($g, 'Hiring Team') !== false) $toneLabel = 'Confident';
                                    elseif (strpos($g, 'Hello') !== false) $toneLabel = 'Friendly';
                                    elseif (strpos($g, 'Sir or Madam') !== false) $toneLabel = 'Formal';
                                    echo $toneLabel;
                                ?>
                                &bull; <?php echo date('M d, Y'); ?>
                            </div>
                        </div>
                        <div class="cl-result-actions">
                            <button class="cl-btn-outline" id="clCopyBtn"><i class="fas fa-copy"></i> Copy</button>
                            <button class="cl-btn-outline" id="clPrintBtn"><i class="fas fa-print"></i> Print / PDF</button>
                            <button class="cl-btn-fill" id="clDownloadBtn"><i class="fas fa-download"></i> Download</button>
                        </div>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 180px;gap:20px;align-items:start;">
                        <div class="cl-letter" id="clLetter"><?php echo nl2br(htmlspecialchars($letter['content'])); ?></div>
                        <div>
                            <div class="cl-score-box">
                                <div class="cl-score-ring">
                                    <svg viewBox="0 0 100 100">
                                        <circle class="ring-bg" cx="50" cy="50" r="45"/>
                                        <circle class="ring-fill" id="clScoreRing" cx="50" cy="50" r="45"/>
                                    </svg>
                                    <div class="cl-score-val">
                                        <span class="num" id="clScoreNum">0</span>
                                        <span class="lbl">Match</span>
                                    </div>
                                </div>
                                <div class="cl-score-label" id="clScoreLabel" style="color:var(--cl-primary);"></div>
                            </div>
                            <?php if (!empty($letter['matched'])): ?>
                                <div style="margin-top:12px;">
                                    <div class="cl-label" style="text-align:center;">Matched Skills</div>
                                    <div class="cl-matched" style="justify-content:center;">
                                        <?php foreach ($letter['matched'] as $m): ?>
                                            <span class="cl-matched-chip"><i class="fas fa-check"></i> <?php echo htmlspecialchars(ucfirst($m)); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <script>
                var clLetterContent = <?php echo json_encode($letter['content']); ?>;
                var clLetterScore = <?php echo intval($letter['score']); ?>;
                (function(){
                    var el = document.getElementById('clLetter');
                    el.textContent = '';
                    var i = 0;
                    el.classList.add('cl-letter-typed');
                    function typeChar(){
                        if (i < clLetterContent.length) {
                            el.textContent += clLetterContent.charAt(i);
                            i++;
                            setTimeout(typeChar, 8 + Math.random() * 5);
                        } else {
                            el.classList.remove('cl-letter-typed');
                        }
                    }
                    typeChar();
                    setTimeout(function(){
                        var ring = document.getElementById('clScoreRing');
                        var circ = 2 * Math.PI * 45;
                        ring.style.strokeDasharray = circ;
                        ring.style.strokeDashoffset = circ;
                        setTimeout(function(){
                            ring.style.strokeDashoffset = circ - (circ * clLetterScore / 100);
                            document.getElementById('clScoreNum').textContent = clLetterScore;
                            var lbl = document.getElementById('clScoreLabel');
                            if (clLetterScore >= 85){lbl.textContent='Strong Match';lbl.style.color='#059669';}
                            else if (clLetterScore >= 70){lbl.textContent='Good Match';lbl.style.color='#2563eb';}
                            else if (clLetterScore >= 50){lbl.textContent='Fair Match';lbl.style.color='#d97706';}
                            else{lbl.textContent='Keep Improving';lbl.style.color='#dc2626';}
                        }, 200);
                    }, 300);
                    setTimeout(function(){
                        var card = document.querySelector('.cl-result');
                        if (card) card.scrollIntoView({behavior:'smooth',block:'start'});
                    }, 100);
                })();
                </script>
            <?php else: ?>
                <div class="cl-card cl-empty" id="clEmpty">
                    <i class="fas fa-envelope-open-text"></i>
                    <h4>Ready when you are</h4>
                    <p>Select a job on the left, pick your tone, and click "Generate Letter". Your personalised cover letter will appear here with a typing animation.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<div class="cl-toast" id="clToast"><i class="fas fa-check-circle"></i> <span id="clToastMsg"></span></div>

<!-- Scroll to Top -->
<button class="cl-scroll-top" id="clScrollTop" aria-label="Scroll to top"><i class="fas fa-arrow-up"></i></button>

<script>
var clLetterContent = <?php echo $letter ? json_encode($letter['content']) : "''"; ?>;
var clLetterScore = <?php echo $letter ? intval($letter['score']) : 0; ?>;

document.getElementById('clJobSelect').addEventListener('change', function(){
    var sel = this;
    var opt = sel.options[sel.selectedIndex];
    var box = document.getElementById('clJobPreview');
    if (!sel.value){ box.classList.remove('show'); document.getElementById('clJobId').value = ''; return; }
    document.getElementById('jpTitle').textContent = opt.dataset.title || '';
    document.getElementById('jpCompany').textContent = opt.dataset.company || '';
    var meta = '';
    if (opt.dataset.cat) meta += '<span class="jp-tag">' + opt.dataset.cat + '</span>';
    if (opt.dataset.location) meta += '<span class="jp-tag"><i class="fas fa-location-dot" style="margin-right:3px;"></i>' + opt.dataset.location + '</span>';
    if (opt.dataset.salary) meta += '<span class="jp-tag"><i class="fas fa-bangladeshi-taka-sign" style="margin-right:3px;"></i>' + opt.dataset.salary + '</span>';
    if (opt.dataset.skills) {
        opt.dataset.skills.split(',').slice(0,4).forEach(function(s){ meta += '<span class="jp-tag">' + s.trim() + '</span>'; });
    }
    document.getElementById('jpMeta').innerHTML = meta;
    box.classList.add('show');
    document.getElementById('clJobId').value = sel.value;
});

document.querySelectorAll('.cl-tone').forEach(function(btn){
    btn.addEventListener('click', function(){
        document.querySelectorAll('.cl-tone').forEach(function(b){ b.classList.remove('active'); });
        btn.classList.add('active');
        document.getElementById('clToneInput').value = btn.dataset.tone;
    });
});

document.getElementById('clForm').addEventListener('submit', function(e){
    if (!document.getElementById('clJobId').value) {
        e.preventDefault();
        alert('Please select a job first.');
        return;
    }
    var btn = document.getElementById('clGenBtn');
    btn.classList.add('loading');
    setTimeout(function(){ btn.disabled = true; }, 200);
});

document.querySelectorAll('.cl-history-item').forEach(function(item){
    item.addEventListener('click', function(){
        var content = this.dataset.content;
        var title = this.dataset.title;
        var company = this.dataset.company;
        var panel = document.getElementById('clRightPanel');
        panel.innerHTML = '<div class="cl-card cl-result"><div class="cl-result-header"><div><h4><i class="fas fa-file-lines" style="color:var(--cl-primary);margin-right:6px;"></i>' + title + '</h4><div class="cl-result-meta">Generated for ' + company + '</div></div><div class="cl-result-actions"><button class="cl-btn-outline" id="clCopyBtn"><i class="fas fa-copy"></i> Copy</button><button class="cl-btn-outline" id="clPrintBtn"><i class="fas fa-print"></i> Print / PDF</button><button class="cl-btn-fill" id="clDownloadBtn"><i class="fas fa-download"></i> Download</button></div></div><div class="cl-letter" id="clLetter"></div></div>';
        clLetterContent = content;
        attachActionBtns();
        var el = document.getElementById('clLetter');
        var i = 0;
        el.classList.add('cl-letter-typed');
        function typeChar(){
            if (i < content.length){ el.textContent += content.charAt(i); i++; setTimeout(typeChar, 12 + Math.random() * 8); }
            else { el.classList.remove('cl-letter-typed'); }
        }
        typeChar();
    });
});

function attachActionBtns(){
    var copyBtn = document.getElementById('clCopyBtn');
    var printBtn = document.getElementById('clPrintBtn');
    var dlBtn = document.getElementById('clDownloadBtn');
    if (copyBtn) copyBtn.addEventListener('click', function(){
        navigator.clipboard.writeText(clLetterContent).then(function(){ clToast('Cover letter copied!'); }).catch(function(){
            var ta = document.createElement('textarea'); ta.value = clLetterContent; document.body.appendChild(ta); ta.select(); document.execCommand('copy'); ta.remove(); clToast('Cover letter copied!');
        });
    });
    if (printBtn) printBtn.addEventListener('click', function(){ window.print(); });
    if (dlBtn) dlBtn.addEventListener('click', function(){
        var blob = new Blob([clLetterContent], {type:'text/plain;charset=utf-8'});
        var a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = 'cover-letter-' + Date.now() + '.txt'; a.click(); URL.revokeObjectURL(a.href);
        clToast('Download started!');
    });
}
attachActionBtns();

function clToast(msg){
    var t = document.getElementById('clToast');
    document.getElementById('clToastMsg').textContent = msg;
    t.classList.add('show');
    setTimeout(function(){ t.classList.remove('show'); }, 3000);
}

var jobSel = document.getElementById('clJobSelect');
if (jobSel && jobSel.value) {
    document.getElementById('clJobId').value = jobSel.value;
    jobSel.dispatchEvent(new Event('change'));
}

/* Hamburger */
(function(){
    var h=document.getElementById('clHamburger');
    var o=document.getElementById('clMobileOverlay');
    var d=document.getElementById('clMobileDrawer');
    var c=document.getElementById('clMobileClose');
    function open(){h.classList.add('open');o.classList.add('show');d.classList.add('show');document.body.style.overflow='hidden';}
    function close(){h.classList.remove('open');o.classList.remove('show');d.classList.remove('show');document.body.style.overflow='';}
    h.addEventListener('click',function(){d.classList.contains('show')?close():open();});
    o.addEventListener('click',close);
    c.addEventListener('click',close);
})();

/* Scroll to Top */
(function(){
    var btn=document.getElementById('clScrollTop');
    window.addEventListener('scroll',function(){
        window.scrollY>400?btn.classList.add('show'):btn.classList.remove('show');
    });
    btn.addEventListener('click',function(){window.scrollTo({top:0,behavior:'smooth'});});
})();
</script>
</body>
</html>
