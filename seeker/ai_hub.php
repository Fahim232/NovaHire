<?php
require_once __DIR__ . '/../includes/bootstrap.php';
if (!isset($_SESSION['id'])) { header('location: ' . BASE_URL . '/auth/login.php'); exit(); }
require_once __DIR__ . '/../admin/dbcon.php';
require_once __DIR__ . '/../ai/config.php';
require_once __DIR__ . '/../ai/helpers.php';

$user_id = $_SESSION['id'];
$user = mysqli_fetch_assoc(mysqli_query($con, "SELECT * FROM user_info WHERE id = '$user_id'"));
$username = htmlspecialchars($user['username'] ?? 'User');
$initial = strtoupper(substr($user['username'] ?? 'U', 0, 1));

$fields = ['username'=>15,'email'=>15,'phone'=>15,'user_degree'=>15,'user_skills'=>20,'profile'=>10,'about_me'=>10];
$completion = 0;
foreach ($fields as $f=>$w) if (!empty($user[$f])) $completion += $w;

$provider_label = ai_provider_label();
$llm_on = ai_llm_available();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Career Center | NovaHire</title>
    <?php include __DIR__ . '/../includes/links.php'; ?>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
    <?php echo ai_css_link(); ?>
    <style>
    :root{
        --ai-primary:#1a56db;--ai-secondary:#0ea5e9;--ai-accent:#0ea5e9;
        --ai-grad:linear-gradient(135deg,#1a56db,#0ea5e9 55%,#0ea5e9);
        --ai-bg:#f6f7fb;--ai-card:#fff;--ai-text:#1e293b;--ai-muted:#64748b;
        --ai-border:#e2e8f0;--ai-radius:18px;
    }
    body{background:var(--ai-bg);margin:0;font-family:'Plus Jakarta Sans',sans-serif;}

    /* Navbar */
    .ai-topbar{background:var(--ai-grad);padding:5px 0;color:#fff;font-size:.75rem;}
    .ai-topbar .wrap{max-width:1340px;margin:0 auto;padding:0 24px;display:flex;justify-content:flex-end;align-items:center;gap:18px;}
    .ai-topbar a{color:rgba(255,255,255,.85);text-decoration:none;}
    .ai-nav{background:rgba(255,255,255,.92);backdrop-filter:blur(20px);border-bottom:1px solid rgba(226,232,240,.5);position:sticky;top:0;z-index:9990;}
    .ai-nav-inner{max-width:1340px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;padding:0 24px;height:60px;}
    .ai-brand{display:flex;align-items:center;gap:10px;text-decoration:none;}
    .ai-brand-icon{width:36px;height:36px;background:var(--ai-grad);border-radius:10px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:.9rem;}
    .ai-brand-text{font-family:'Plus Jakarta Sans',sans-serif;font-size:1.25rem;font-weight:800;color:var(--ai-text);}
    .ai-brand-text span{background:var(--ai-grad);-webkit-background-clip:text;-webkit-text-fill-color:transparent;}
    .ai-nav-links{display:flex;align-items:center;gap:2px;}
    .ai-nav-link{color:var(--ai-muted);font-weight:600;padding:8px 14px;border-radius:9999px;font-size:.85rem;text-decoration:none;transition:.2s;display:flex;align-items:center;gap:6px;}
    .ai-nav-link:hover{color:var(--ai-primary);background:rgba(26,86,219,.06);}
    .ai-nav-link.active{color:var(--ai-primary);background:rgba(26,86,219,.1);font-weight:700;}
    .ai-nav-right{display:flex;align-items:center;gap:10px;}
    .ai-user-pill{display:flex;align-items:center;gap:8px;padding:4px 14px 4px 4px;border-radius:9999px;border:1.5px solid var(--ai-border);background:#fff;text-decoration:none;}
    .ai-user-avatar{width:32px;height:32px;border-radius:50%;background:var(--ai-grad);display:flex;align-items:center;justify-content:center;color:#fff;font-size:.75rem;font-weight:700;}
    .ai-user-name{font-weight:700;font-size:.82rem;color:var(--ai-text);}

    /* Hamburger */
    .ai-hamburger{display:none;background:none;border:none;cursor:pointer;padding:8px;position:relative;width:40px;height:40px;}
    .ai-hamburger span{display:block;width:22px;height:2px;background:var(--ai-text);border-radius:2px;position:absolute;left:9px;transition:.3s;}
    .ai-hamburger span:nth-child(1){top:12px;}
    .ai-hamburger span:nth-child(2){top:19px;}
    .ai-hamburger span:nth-child(3){top:26px;}
    .ai-hamburger.open span:nth-child(1){top:19px;transform:rotate(45deg);}
    .ai-hamburger.open span:nth-child(2){opacity:0;}
    .ai-hamburger.open span:nth-child(3){top:19px;transform:rotate(-45deg);}
    .ai-mobile-panel{display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.4);z-index:9998;opacity:0;transition:.3s;}
    .ai-mobile-panel.show{opacity:1;}
    .ai-mobile-drawer{position:fixed;top:0;left:0;width:280px;height:100%;background:#fff;z-index:9999;transform:translateX(-100%);transition:.3s cubic-bezier(.4,0,.2,1);padding:24px;overflow-y:auto;}
    .ai-mobile-drawer.show{transform:translateX(0);}
    .ai-mobile-drawer .ai-brand{margin-bottom:24px;}
    .ai-mobile-drawer .ai-mobile-link{display:flex;align-items:center;gap:10px;padding:12px 14px;border-radius:12px;color:var(--ai-muted);font-weight:600;font-size:.9rem;text-decoration:none;transition:.2s;}
    .ai-mobile-drawer .ai-mobile-link:hover,.ai-mobile-drawer .ai-mobile-link.active{color:var(--ai-primary);background:rgba(26,86,219,.08);}
    .ai-mobile-drawer .ai-mobile-link i{width:20px;text-align:center;}
    .ai-mobile-divider{height:1px;background:var(--ai-border);margin:12px 0;}
    .ai-mobile-close{position:absolute;top:16px;right:16px;background:none;border:none;font-size:1.2rem;color:var(--ai-muted);cursor:pointer;}

    /* Hero */
    .ai-hero{background:var(--ai-grad);color:#fff;padding:52px 0 44px;position:relative;overflow:hidden;text-align:center;}
    .ai-hero::before{content:'';position:absolute;top:-50%;left:-20%;width:500px;height:500px;background:radial-gradient(circle,rgba(255,255,255,.1) 0%,transparent 70%);border-radius:50%;}
    .ai-hero::after{content:'';position:absolute;bottom:-40%;right:-10%;width:400px;height:400px;background:radial-gradient(circle,rgba(255,255,255,.08) 0%,transparent 70%);border-radius:50%;}
    .ai-hero-chip{background:rgba(255,255,255,.18);backdrop-filter:blur(10px);padding:6px 18px;border-radius:9999px;font-size:.78rem;font-weight:600;display:inline-flex;align-items:center;gap:7px;margin-bottom:16px;}
    .ai-hero h1{font-weight:800;font-size:2rem;margin:0 0 8px;position:relative;}
    .ai-hero p{opacity:.88;font-size:1rem;margin:0;position:relative;}

    /* Stats Row */
    .ai-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-top:-32px;position:relative;z-index:2;padding:0 24px;max-width:900px;margin-left:auto;margin-right:auto;}
    .ai-stat{background:var(--ai-card);border:1px solid var(--ai-border);border-radius:var(--ai-radius);padding:20px 16px;text-align:center;box-shadow:0 8px 24px rgba(0,0,0,.06);transition:.3s;cursor:pointer;}
    .ai-stat:hover{transform:translateY(-3px);box-shadow:0 12px 32px rgba(26,86,219,.1);}
    .ai-stat .val{font-size:1.6rem;font-weight:800;color:var(--ai-text);line-height:1.2;}
    .ai-stat .lbl{font-size:.7rem;font-weight:700;color:var(--ai-muted);text-transform:uppercase;letter-spacing:.5px;margin-top:4px;}
    .ai-stat .ico{width:40px;height:40px;border-radius:12px;display:inline-flex;align-items:center;justify-content:center;margin-bottom:8px;font-size:1rem;}

    /* Section */
    .ai-section{max-width:1340px;margin:0 auto;padding:40px 24px 60px;}
    .ai-section-title{font-weight:800;font-size:1.2rem;color:var(--ai-text);margin:0 0 6px;display:flex;align-items:center;gap:8px;}
    .ai-section-title i{color:var(--ai-primary);font-size:1rem;}
    .ai-section-sub{font-size:.88rem;color:var(--ai-muted);margin:0 0 24px;}

    /* Feature Cards */
    .ai-features{display:grid;grid-template-columns:repeat(3,1fr);gap:20px;}
    .ai-fcard{background:var(--ai-card);border:1px solid var(--ai-border);border-radius:var(--ai-radius);padding:28px 24px;text-decoration:none;display:flex;flex-direction:column;transition:.3s cubic-bezier(.4,0,.2,1);box-shadow:0 4px 16px rgba(0,0,0,.04);position:relative;overflow:hidden;}
    .ai-fcard::before{content:'';position:absolute;top:0;left:0;right:0;height:4px;background:var(--ai-grad);opacity:0;transition:.3s;}
    .ai-fcard:hover,.ai-fcard:focus-visible{transform:translateY(-6px);box-shadow:0 16px 40px rgba(26,86,219,.12);text-decoration:none;border-color:#c7d2fe;}
    .ai-fcard:hover::before,.ai-fcard:focus-visible::before{opacity:1;}
    .ai-fcard:focus-visible{outline:2px solid var(--ai-primary);outline-offset:2px;}
    .ai-fcard-icon{width:54px;height:54px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:1.3rem;margin-bottom:16px;transition:.3s;}
    .ai-fcard:hover .ai-fcard-icon{transform:scale(1.08);}
    .ai-fcard h5{font-weight:700;color:var(--ai-text);font-size:1rem;margin:0 0 8px;}
    .ai-fcard p{color:var(--ai-muted);font-size:.83rem;line-height:1.6;margin:0;flex:1;}
    .ai-fcard .ai-go{color:var(--ai-primary);font-weight:700;font-size:.82rem;margin-top:16px;display:inline-flex;align-items:center;gap:5px;transition:.2s;}
    .ai-fcard:hover .ai-go{gap:8px;}
    .ai-fcard-tag{position:absolute;top:16px;right:16px;font-size:.62rem;font-weight:700;padding:3px 8px;border-radius:6px;text-transform:uppercase;letter-spacing:.3px;}

    /* Scroll Animation */
    .ai-fcard,.ai-stat,.ai-how-item{opacity:0;transform:translateY(20px);transition:opacity .5s ease,transform .5s ease;}
    .ai-fcard.visible,.ai-stat.visible,.ai-how-item.visible{opacity:1;transform:translateY(0);}

    /* How It Works */
    .ai-how{background:var(--ai-card);border:1px solid var(--ai-border);border-radius:var(--ai-radius);padding:32px;box-shadow:0 4px 16px rgba(0,0,0,.04);margin-top:8px;}
    .ai-how h4{font-weight:800;font-size:1.1rem;color:var(--ai-text);margin:0 0 24px;display:flex;align-items:center;gap:8px;}
    .ai-how h4 i{color:var(--ai-primary);}
    .ai-how-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:24px;}
    .ai-how-item{display:flex;gap:14px;align-items:flex-start;}
    .ai-how-icon{width:46px;height:46px;border-radius:13px;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:1rem;}
    .ai-how-item strong{font-size:.9rem;color:var(--ai-text);display:block;margin-bottom:4px;}
    .ai-how-item p{font-size:.8rem;color:var(--ai-muted);margin:0;line-height:1.5;}

    /* Scroll to Top */
    .ai-scroll-top{position:fixed;bottom:90px;right:24px;width:44px;height:44px;border-radius:50%;background:var(--ai-grad);color:#fff;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:1rem;box-shadow:0 4px 16px rgba(26,86,219,.3);opacity:0;transform:translateY(20px);transition:.3s;z-index:9989;}
    .ai-scroll-top.show{opacity:1;transform:translateY(0);}
    .ai-scroll-top:hover{transform:translateY(-2px);box-shadow:0 6px 24px rgba(26,86,219,.4);}

    /* Print */
    @media print{
        .ai-topbar,.ai-nav,.ai-scroll-top,.ai-chat-widget-toggle{display:none!important;}
        .ai-hero{background:#1a56db!important;-webkit-print-color-adjust:exact;print-color-adjust:exact;}
    }

    /* Responsive */
    @media(max-width:991px){
        .ai-features{grid-template-columns:repeat(2,1fr);}
        .ai-how-grid{grid-template-columns:1fr;}
        .ai-nav-links{display:none;}
        .ai-hamburger{display:block;}
        .ai-mobile-panel,.ai-mobile-drawer{display:block;}
    }
    @media(max-width:767px){
        .ai-stats{grid-template-columns:repeat(2,1fr);margin-top:-24px;}
        .ai-hero h1{font-size:1.5rem;}
        .ai-hero{padding:40px 0 36px;}
    }
    @media(max-width:575px){
        .ai-features{grid-template-columns:1fr;}
        .ai-nav-inner{padding:0 14px;height:56px;}
        .ai-stats{padding:0 14px;gap:10px;}
        .ai-stat{padding:14px 10px;}
        .ai-stat .val{font-size:1.3rem;}
        .ai-section{padding:28px 14px 40px;}
        .ai-how{padding:20px;}
        .ai-topbar{display:none;}
    }
    </style>
</head>
<body>

<!-- Topbar -->
<div class="ai-topbar"><div class="wrap"><a href="seeker_dashboard.php"><i class="fas fa-home mr-1"></i> Dashboard</a><a href="#"><i class="fas fa-headset mr-1"></i> Support</a></div></div>

<!-- Navbar -->
<nav class="ai-nav">
    <div class="ai-nav-inner">
        <a class="ai-brand" href="seeker_dashboard.php">
            <div class="ai-brand-icon"><i class="fas fa-layer-group"></i></div>
            <div class="ai-brand-text">Nova<span>Hire</span></div>
        </a>
        <div class="ai-nav-links">
            <a class="ai-nav-link" href="seeker_dashboard.php"><i class="fas fa-home"></i> Home</a>
            <a class="ai-nav-link" href="browse_jobs.php"><i class="fas fa-briefcase"></i> Jobs</a>
            <a class="ai-nav-link active" href="ai_hub.php"><i class="fas fa-robot"></i> AI Center</a>
        </div>
        <div class="ai-nav-right">
            <button class="ai-hamburger" id="aiHamburger" aria-label="Toggle menu"><span></span><span></span><span></span></button>
            <a class="ai-user-pill" href="profile.php">
                <div class="ai-user-avatar"><?php echo $initial; ?></div>
                <span class="ai-user-name"><?php echo $username; ?></span>
            </a>
        </div>
    </div>
</nav>

<!-- Mobile Drawer -->
<div class="ai-mobile-panel" id="aiMobilePanel"></div>
<div class="ai-mobile-drawer" id="aiMobileDrawer">
    <button class="ai-mobile-close" id="aiMobileClose" aria-label="Close menu"><i class="fas fa-xmark"></i></button>
    <a class="ai-brand" href="seeker_dashboard.php">
        <div class="ai-brand-icon"><i class="fas fa-layer-group"></i></div>
        <div class="ai-brand-text">Nova<span>Hire</span></div>
    </a>
    <a class="ai-mobile-link" href="seeker_dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
    <a class="ai-mobile-link" href="browse_jobs.php"><i class="fas fa-briefcase"></i> Browse Jobs</a>
    <a class="ai-mobile-divider"></a>
    <a class="ai-mobile-link active" href="ai_hub.php"><i class="fas fa-robot"></i> AI Center</a>
    <a class="ai-mobile-link" href="ai_resume_analyzer.php"><i class="fas fa-file-lines"></i> Resume Analyzer</a>
    <a class="ai-mobile-link" href="ai_cover_letter_generator.php"><i class="fas fa-envelope-open-text"></i> Cover Letter</a>
    <a class="ai-mobile-link" href="ai_grooming_coach.php"><i class="fas fa-graduation-cap"></i> Grooming Coach</a>
    <a class="ai-mobile-link" href="ai_mock_interview.php"><i class="fas fa-clipboard-question"></i> Mock Interview</a>
    <a class="ai-mobile-link" href="ai_assistant.php"><i class="fas fa-headset"></i> Career Assistant</a>
    <a class="ai-mobile-link" href="skill_gap.php"><i class="fas fa-chart-simple"></i> Skill Gap</a>
    <a class="ai-mobile-link" href="career_path.php"><i class="fas fa-signs-post"></i> Career Path</a>
    <a class="ai-mobile-link" href="recommendations.php"><i class="fas fa-wand-magic-sparkles"></i> Recommendations</a>
    <a class="ai-mobile-divider"></a>
    <a class="ai-mobile-link" href="profile.php"><i class="fas fa-user"></i> My Profile</a>
    <a class="ai-mobile-link" href="seeker/my_applications.php"><i class="fas fa-paper-plane"></i> Applications</a>
    <a class="ai-mobile-link" href="auth/logout.php"><i class="fas fa-right-from-bracket"></i> Logout</a>
</div>

<!-- Hero -->
<div class="ai-hero">
    <div class="container">
        <div class="ai-hero-chip"><i class="fas fa-brain"></i> <?php echo $llm_on ? 'Powered by ' . $provider_label : 'Hybrid AI Engine - Always Online'; ?></div>
        <h1>AI Career Center</h1>
        <p>Your personal AI toolkit for finding, applying and preparing for your dream job.</p>
    </div>
</div>

<!-- Stats -->
<div class="ai-stats">
    <div class="ai-stat" onclick="location.href='profile.php'" role="link" tabindex="0" aria-label="Go to profile" onkeydown="if(event.key==='Enter')location.href='profile.php'">
        <div class="ico" style="background:rgba(26,86,219,.1);color:#1a56db;"><i class="fas fa-user-check"></i></div>
        <div class="val"><?php echo $completion; ?>%</div>
        <div class="lbl">Profile Ready</div>
    </div>
    <div class="ai-stat">
        <div class="ico" style="background:rgba(14,165,233,.1);color:#0ea5e9;"><i class="fas fa-bolt"></i></div>
        <div class="val" style="font-size:1.2rem;"><i class="fas fa-bolt"></i></div>
        <div class="lbl">Hybrid Engine</div>
    </div>
    <div class="ai-stat">
        <div class="ico" style="background:<?php echo $llm_on ? 'rgba(5,150,105,.1)' : 'rgba(217,119,6,.1)'; ?>;color:<?php echo $llm_on ? '#059669' : '#d97706'; ?>;"><i class="fas fa-cloud-bolt"></i></div>
        <div class="val" style="color:<?php echo $llm_on ? '#059669' : '#d97706'; ?>;font-size:1.1rem;"><?php echo $llm_on ? 'ON' : 'OFF'; ?></div>
        <div class="lbl"><?php echo $provider_label; ?></div>
    </div>
    <div class="ai-stat">
        <div class="ico" style="background:rgba(14,165,233,.1);color:#0ea5e9;"><i class="fas fa-robot"></i></div>
        <div class="val" style="font-size:1.2rem;"><i class="fas fa-check-circle"></i></div>
        <div class="lbl">Always Online</div>
    </div>
</div>

<!-- Features -->
<div class="ai-section">
    <h2 class="ai-section-title"><i class="fas fa-wand-magic-sparkles"></i> AI Features</h2>
    <p class="ai-section-sub">Powerful tools to analyse, prepare and accelerate your job search.</p>

    <div class="ai-features">
        <!-- Resume Analyzer -->
        <a href="ai_resume_analyzer.php" class="ai-fcard">
            <span class="ai-fcard-tag" style="background:rgba(26,86,219,.08);color:#1a56db;">Popular</span>
            <div class="ai-fcard-icon" style="background:rgba(26,86,219,.1);color:#1a56db;"><i class="fas fa-file-lines"></i></div>
            <h5>AI Resume Analyzer</h5>
            <p>Score your resume across skills, education, experience and completeness. Get strengths, gaps and concrete improvements.</p>
            <span class="ai-go">Analyze my resume <i class="fas fa-arrow-right"></i></span>
        </a>

        <!-- Cover Letter -->
        <a href="ai_cover_letter_generator.php" class="ai-fcard">
            <span class="ai-fcard-tag" style="background:rgba(236,72,153,.08);color:#db2777;">New</span>
            <div class="ai-fcard-icon" style="background:rgba(236,72,153,.1);color:#db2777;"><i class="fas fa-envelope-open-text"></i></div>
            <h5>AI Cover Letter Generator</h5>
            <p>Pick any active job and get a personalised, professional cover letter written from your real profile data in one click.</p>
            <span class="ai-go">Generate a letter <i class="fas fa-arrow-right"></i></span>
        </a>

        <!-- Grooming Coach -->
        <a href="ai_grooming_coach.php" class="ai-fcard">
            <div class="ai-fcard-icon" style="background:rgba(5,150,105,.1);color:#059669;"><i class="fas fa-graduation-cap"></i></div>
            <h5>AI Grooming Coach</h5>
            <p>Personalised study plans for the grooming hub. See your weak topics, strong topics and exactly which videos to watch.</p>
            <span class="ai-go">Get my study plan <i class="fas fa-arrow-right"></i></span>
        </a>

        <!-- Mock Interview -->
        <a href="ai_mock_interview.php" class="ai-fcard">
            <div class="ai-fcard-icon" style="background:rgba(217,119,6,.1);color:#d97706;"><i class="fas fa-clipboard-question"></i></div>
            <h5>AI Mock Interview</h5>
            <p>Answer category-based interview questions, get instant scoring by keyword analysis plus feedback and improvement tips.</p>
            <span class="ai-go">Practice now <i class="fas fa-arrow-right"></i></span>
        </a>

        <!-- Job Matching -->
        <a href="browse_jobs.php" class="ai-fcard">
            <div class="ai-fcard-icon" style="background:rgba(59,130,246,.1);color:#2563eb;"><i class="fas fa-magnifying-glass-chart"></i></div>
            <h5>AI Job Matching</h5>
            <p>Every job on the portal shows an AI match percentage computed from your skills, experience, education and requirements.</p>
            <span class="ai-go">Find my best match <i class="fas fa-arrow-right"></i></span>
        </a>

        <!-- Career Assistant -->
        <a href="ai_assistant.php" class="ai-fcard">
            <span class="ai-fcard-tag" style="background:rgba(14,165,233,.08);color:#0ea5e9;">Chat</span>
            <div class="ai-fcard-icon" style="background:rgba(14,165,233,.1);color:#0ea5e9;"><i class="fas fa-robot"></i></div>
            <h5>AI Career Assistant</h5>
            <p>Chat with the full-page AI assistant about jobs, applications, grooming, interviews and anything else about NovaHire.</p>
            <span class="ai-go">Start chatting <i class="fas fa-arrow-right"></i></span>
        </a>

        <!-- Skill Gap -->
        <a href="skill_gap.php" class="ai-fcard">
            <div class="ai-fcard-icon" style="background:rgba(14,165,233,.1);color:#0ea5e9;"><i class="fas fa-chart-simple"></i></div>
            <h5>Skill Gap Analyzer</h5>
            <p>Discover exactly which skills you need to develop for your target role. Visualise gaps and get a learning roadmap.</p>
            <span class="ai-go">Analyze my skills <i class="fas fa-arrow-right"></i></span>
        </a>

        <!-- Career Path -->
        <a href="career_path.php" class="ai-fcard">
            <div class="ai-fcard-icon" style="background:rgba(249,115,22,.1);color:#f97316;"><i class="fas fa-signs-post"></i></div>
            <h5>Career Path Explorer</h5>
            <p>Visualise your career trajectory from current role to dream job. See recommended steps, skills and milestones along the way.</p>
            <span class="ai-go">Explore paths <i class="fas fa-arrow-right"></i></span>
        </a>

        <!-- Job Recommendations -->
        <a href="recommendations.php" class="ai-fcard">
            <div class="ai-fcard-icon" style="background:rgba(236,72,153,.1);color:#ec4899;"><i class="fas fa-wand-magic-sparkles"></i></div>
            <h5>Smart Job Matches</h5>
            <p>AI-powered job recommendations ranked by compatibility with your profile. See why each job matches and how to improve your odds.</p>
            <span class="ai-go">View matches <i class="fas fa-arrow-right"></i></span>
        </a>
    </div>

    <!-- How It Works -->
    <div class="ai-how" style="margin-top:32px;">
        <h4><i class="fas fa-circle-info"></i> How NovaHire AI Works</h4>
        <div class="ai-how-grid">
            <div class="ai-how-item">
                <div class="ai-how-icon" style="background:rgba(26,86,219,.1);color:#1a56db;"><i class="fas fa-puzzle-piece"></i></div>
                <div>
                    <strong>Rule-Based Core</strong>
                    <p>Matching, scoring and coaching run on proven algorithms — they work offline, instantly, every time.</p>
                </div>
            </div>
            <div class="ai-how-item">
                <div class="ai-how-icon" style="background:rgba(236,72,153,.1);color:#db2777;"><i class="fas fa-cloud-bolt"></i></div>
                <div>
                    <strong>Optional LLM Boost</strong>
                    <p>Connect an OpenAI or Gemini key in the admin panel for richer cover letters, chat and summaries.</p>
                </div>
            </div>
            <div class="ai-how-item">
                <div class="ai-how-icon" style="background:rgba(5,150,105,.1);color:#059669;"><i class="fas fa-shield-heart"></i></div>
                <div>
                    <strong>Private by Default</strong>
                    <p>Your data stays in your database. Only when you configure a key does any data leave your server.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Scroll to Top -->
<button class="ai-scroll-top" id="aiScrollTop" aria-label="Scroll to top"><i class="fas fa-arrow-up"></i></button>

<script src="<?php echo BASE_URL; ?>/ai/assets/js/chat.js"></script>
<?php ai_chat_widget(); ?>
<script>
if(typeof aiChatInit==='function') aiChatInit();

/* Hamburger */
(function(){
    var h=document.getElementById('aiHamburger');
    var p=document.getElementById('aiMobilePanel');
    var d=document.getElementById('aiMobileDrawer');
    var c=document.getElementById('aiMobileClose');
    function open(){h.classList.add('open');p.classList.add('show');d.classList.add('show');document.body.style.overflow='hidden';}
    function close(){h.classList.remove('open');p.classList.remove('show');d.classList.remove('show');document.body.style.overflow='';}
    h.addEventListener('click',function(){d.classList.contains('show')?close():open();});
    p.addEventListener('click',close);
    c.addEventListener('click',close);
})();

/* Scroll to Top */
(function(){
    var btn=document.getElementById('aiScrollTop');
    window.addEventListener('scroll',function(){
        window.scrollY>400?btn.classList.add('show'):btn.classList.remove('show');
    });
    btn.addEventListener('click',function(){window.scrollTo({top:0,behavior:'smooth'});});
})();

/* Scroll Animations */
(function(){
    var els=document.querySelectorAll('.ai-fcard,.ai-stat,.ai-how-item');
    if(!els.length) return;
    var obs=new IntersectionObserver(function(entries){
        entries.forEach(function(e,i){
            if(e.isIntersecting){
                var idx=Array.from(els).indexOf(e.target);
                setTimeout(function(){e.target.classList.add('visible');},idx*60);
                obs.unobserve(e.target);
            }
        });
    },{threshold:0.1});
    els.forEach(function(el){obs.observe(el);});
})();
</script>
</body>
</html>
