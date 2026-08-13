<?php
// Core setup: session, DB, BASE_URL, helpers
require_once __DIR__ . '/../includes/bootstrap.php';
if (!isset($_SESSION['id'])) {
    header('location: ' . BASE_URL . '/auth/login.php');
    exit();
}
require_once __DIR__ . '/../admin/dbcon.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../ai/config.php';

$user_id = $_SESSION['id'];
$user_q = mysqli_query($con, "SELECT * FROM user_info WHERE id = '$user_id'");
$user = mysqli_fetch_assoc($user_q);

// Quick resume score for the hub
$fields = array('username' => 15, 'email' => 15, 'phone' => 15, 'user_degree' => 15, 'user_skills' => 20, 'profile' => 10, 'about_me' => 10);
$completion = 0;
foreach ($fields as $f => $w) if (!empty($user[$f])) $completion += $w;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>AI Career Center | NovaHire</title>
    <?php require_once __DIR__ . '/../includes/links.php'; ?>
    <?php echo ai_css_link(); ?>
    <style>
        body { background: #f8fafc; }
        .ai-feature-card {
            background: white; border: 1px solid #e2e8f0; border-radius: 20px;
            padding: 30px 26px; height: 100%; text-decoration: none; display: block;
            transition: all 0.3s cubic-bezier(0.4,0,0.2,1);
            box-shadow: 0 4px 16px rgba(0,0,0,0.04);
        }
        .ai-feature-card:hover { transform: translateY(-6px); box-shadow: 0 16px 40px rgba(79,70,229,0.15); text-decoration: none; border-color: #c7d2fe; }
        .ai-feature-icon {
            width: 58px; height: 58px; border-radius: 16px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.4rem; margin-bottom: 18px;
        }
        .ai-feature-card h5 { font-weight: 700; color: #1e293b; font-size: 1.05rem; margin-bottom: 8px; }
        .ai-feature-card p { color: #64748b; font-size: 0.85rem; line-height: 1.6; margin: 0; }
        .ai-feature-card .ai-go { color: #4f46e5; font-weight: 600; font-size: 0.82rem; margin-top: 14px; display: inline-flex; align-items: center; gap: 5px; }
        .ai-quick-stat { background: white; border: 1px solid #e2e8f0; border-radius: 16px; padding: 18px; text-align: center; }
        .ai-quick-stat .v { font-size: 1.5rem; font-weight: 800; color: #1e293b; }
        .ai-quick-stat .l { font-size: 0.75rem; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.4px; }
    </style>
</head>
<body>
<?php ai_page_header('AI Career Center', 'Your personal AI toolkit for finding, applying and preparing for your dream job.', 'brain'); ?>

<div class="container" style="padding-bottom: 60px;">

    <!-- Quick stats -->
    <div class="row mb-4" style="margin-top: 24px;">
        <div class="col-6 col-md-3 mb-3">
            <div class="ai-quick-stat"><div class="v"><?php echo $completion; ?>%</div><div class="l">Profile Ready</div></div>
        </div>
        <div class="col-6 col-md-3 mb-3">
            <div class="ai-quick-stat"><div class="v" style="color:#4f46e5;"><i class="fas fa-bolt"></i></div><div class="l">Hybrid Engine</div></div>
        </div>
        <div class="col-6 col-md-3 mb-3">
            <div class="ai-quick-stat"><div class="v" style="color:#059669;"><?php echo ai_llm_available() ? 'ON' : 'OFF'; ?></div><div class="l"><?php echo ai_provider_label(); ?></div></div>
        </div>
        <div class="col-6 col-md-3 mb-3">
            <div class="ai-quick-stat"><div class="v" style="color:#d97706;"><i class="fas fa-robot"></i></div><div class="l">Always Online</div></div>
        </div>
    </div>

    <!-- Feature cards -->
    <h5 class="font-weight-bold mb-3" style="color:#1e293b;"><i class="fas fa-wand-magic-sparkles mr-2" style="color:#4f46e5;"></i>AI Features</h5>
    <div class="row">
        <div class="col-md-6 col-lg-4 mb-4">
            <a href="ai_resume_analyzer.php" class="ai-feature-card">
                <div class="ai-feature-icon" style="background:rgba(79,70,229,0.1);color:#4f46e5;"><i class="fas fa-file-lines"></i></div>
                <h5>AI Resume Analyzer</h5>
                <p>Score your resume across skills, education, experience and completeness. Get your strengths, gaps and concrete improvements.</p>
                <span class="ai-go">Analyze my resume <i class="fas fa-arrow-right"></i></span>
            </a>
        </div>
        <div class="col-md-6 col-lg-4 mb-4">
            <a href="ai_cover_letter_generator.php" class="ai-feature-card">
                <div class="ai-feature-icon" style="background:rgba(236,72,153,0.1);color:#db2777;"><i class="fas fa-envelope-open-text"></i></div>
                <h5>AI Cover Letter Generator</h5>
                <p>Pick any active job and get a personalised, professional cover letter written from your real profile data in one click.</p>
                <span class="ai-go">Generate a letter <i class="fas fa-arrow-right"></i></span>
            </a>
        </div>
        <div class="col-md-6 col-lg-4 mb-4">
            <a href="ai_grooming_coach.php" class="ai-feature-card">
                <div class="ai-feature-icon" style="background:rgba(16,185,129,0.1);color:#059669;"><i class="fas fa-graduation-cap"></i></div>
                <h5>AI Grooming Coach</h5>
                <p>Personalised study plans for the grooming hub. See your weak topics, strong topics and exactly which videos to watch.</p>
                <span class="ai-go">Get my study plan <i class="fas fa-arrow-right"></i></span>
            </a>
        </div>
        <div class="col-md-6 col-lg-4 mb-4">
            <a href="ai_mock_interview.php" class="ai-feature-card">
                <div class="ai-feature-icon" style="background:rgba(245,158,11,0.1);color:#d97706;"><i class="fas fa-clipboard-question"></i></div>
                <h5>AI Mock Interview</h5>
                <p>Answer category-based interview questions, get instant scoring by keyword analysis plus feedback and improvement tips.</p>
                <span class="ai-go">Practice now <i class="fas fa-arrow-right"></i></span>
            </a>
        </div>
        <div class="col-md-6 col-lg-4 mb-4">
            <a href="browse_jobs.php" class="ai-feature-card">
                <div class="ai-feature-icon" style="background:rgba(59,130,246,0.1);color:#2563eb;"><i class="fas fa-magnifying-glass-chart"></i></div>
                <h5>AI Job Matching</h5>
                <p>Every job on the portal now shows an AI match percentage computed from your skills, experience, education and the job requirements.</p>
                <span class="ai-go">Find my best match <i class="fas fa-arrow-right"></i></span>
            </a>
        </div>
        <div class="col-md-6 col-lg-4 mb-4">
            <a href="ai_assistant.php" class="ai-feature-card">
                <div class="ai-feature-icon" style="background:rgba(139,92,246,0.1);color:#7c3aed;"><i class="fas fa-robot"></i></div>
                <h5>AI Career Assistant</h5>
                <p>Chat with the full-page AI assistant about jobs, applications, grooming, interviews and anything else about NovaHire.</p>
                <span class="ai-go">Start chatting <i class="fas fa-arrow-right"></i></span>
            </a>
        </div>
    </div>

    <!-- How it works -->
    <div class="ai-card mt-2">
        <h4><i class="fas fa-circle-info mr-2" style="color:#4f46e5;"></i>How NovaHire AI works</h4>
        <div class="row mt-3">
            <div class="col-md-4 mb-3">
                <div class="d-flex gap-2">
                    <div class="ai-dim-icon" style="width:44px;height:44px;border-radius:12px;background:rgba(79,70,229,0.1);color:#4f46e5;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas fa-puzzle-piece"></i></div>
                    <div><strong style="font-size:0.9rem;">Rule-Based Core</strong><p style="font-size:0.8rem;color:#64748b;margin:4px 0 0;">Matching, scoring and coaching run on proven algorithms - they work offline, instantly, every time.</p></div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="d-flex gap-2">
                    <div class="ai-dim-icon" style="width:44px;height:44px;border-radius:12px;background:rgba(236,72,153,0.1);color:#db2777;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas fa-cloud-bolt"></i></div>
                    <div><strong style="font-size:0.9rem;">Optional LLM Boost</strong><p style="font-size:0.8rem;color:#64748b;margin:4px 0 0;">Connect an OpenAI or Gemini key in the admin panel for richer cover letters, chat and summaries.</p></div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="d-flex gap-2">
                    <div class="ai-dim-icon" style="width:44px;height:44px;border-radius:12px;background:rgba(16,185,129,0.1);color:#059669;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas fa-shield-heart"></i></div>
                    <div><strong style="font-size:0.9rem;">Private by Default</strong><p style="font-size:0.8rem;color:#64748b;margin:4px 0 0;">Your data stays in your database. Only when you configure a key does any data leave your server.</p></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo BASE_URL; ?>/ai/assets/js/chat.js"></script>
<?php ai_chat_widget(); ?>
<script>aiChatInit();</script>
</body>
</html>
