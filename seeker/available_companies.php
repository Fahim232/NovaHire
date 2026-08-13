<?php
// Core setup: session, DB, BASE_URL, helpers
require_once __DIR__ . '/../includes/bootstrap.php';
 

require_once __DIR__ . '/../admin/dbcon.php';
require_once __DIR__ . '/../includes/header.php';

// Check if user is logged in
if (!isset($_SESSION['id'])) {
    header('Location: ' . BASE_URL . '/auth/login.php');
    exit();
}

$user_id = $_SESSION['id'];

// Get categories where user has passed the quiz
$passed_categories = [];
$query_passed = "SELECT DISTINCT category FROM user_quiz_status WHERE user_id = ? AND status = 'Passed'";
$stmt = mysqli_prepare($con, $query_passed);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result_passed = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result_passed)) {
    $passed_categories[] = $row['category'];
}
mysqli_stmt_close($stmt);

// Get selected category from URL or use first passed category
$selected_category = isset($_GET['category']) ? mysqli_real_escape_string($con, $_GET['category']) : (count($passed_categories) > 0 ? $passed_categories[0] : '');

// Handle application submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['apply_company'])) {
    $company_id = intval($_POST['company_id']);
    $category = mysqli_real_escape_string($con, $_POST['category']);
    $user_message = mysqli_real_escape_string($con, $_POST['user_message']);
    
    // Check if already applied
    $check_query = "SELECT id FROM category_applications WHERE user_id = ? AND company_id = ? AND category = ?";
    $stmt = mysqli_prepare($con, $check_query);
    mysqli_stmt_bind_param($stmt, "iis", $user_id, $company_id, $category);
    mysqli_stmt_execute($stmt);
    $check_result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($check_result) > 0) {
        $alert_message = "You have already applied to this company for $category position.";
        $alert_type = "warning";
    } else {
        // Insert application
        $insert_query = "INSERT INTO category_applications (user_id, company_id, category, user_message, status) VALUES (?, ?, ?, ?, 'Pending')";
        $stmt = mysqli_prepare($con, $insert_query);
        mysqli_stmt_bind_param($stmt, "iiss", $user_id, $company_id, $category, $user_message);
        
        if (mysqli_stmt_execute($stmt)) {
            $alert_message = "Application submitted successfully!";
            $alert_type = "success";
        } else {
            $alert_message = "Failed to submit application. Please try again.";
            $alert_type = "danger";
        }
    }
    mysqli_stmt_close($stmt);
}

// Category descriptions mapping
$category_info = [
    'PHP' => ['title' => 'PHP Developer', 'desc' => 'Backend Logic, MySQL, Server-side scripting', 'icon' => 'fab fa-php', 'color' => '#777BB3'],
    'Java' => ['title' => 'Java Developer', 'desc' => 'OOPs, Spring Boot, Enterprise Applications', 'icon' => 'fab fa-java', 'color' => '#007396'],
    'Python' => ['title' => 'Python Developer', 'desc' => 'Scripting, Automation, Django/Flask', 'icon' => 'fab fa-python', 'color' => '#3776AB'],
    'Frontend' => ['title' => 'Frontend Developer', 'desc' => 'HTML5, CSS3, Responsive Design', 'icon' => 'fab fa-html5', 'color' => '#E34F26'],
    'JavaScript' => ['title' => 'JavaScript Developer', 'desc' => 'ES6+, DOM Manipulation, Async Programming', 'icon' => 'fab fa-js', 'color' => '#F7DF1E'],
    'UI/UX' => ['title' => 'UI/UX Designer', 'desc' => 'User Research, Wireframing, Prototyping', 'icon' => 'fas fa-pencil-ruler', 'color' => '#FF6B6B'],
    'DataScience' => ['title' => 'Data Scientist', 'desc' => 'Data Analysis, ML, Python/Pandas', 'icon' => 'fas fa-chart-line', 'color' => '#4ECDC4'],
    'Marketing' => ['title' => 'Digital Marketing', 'desc' => 'SEO, Content Strategy, Social Media', 'icon' => 'fas fa-bullhorn', 'color' => '#FF6B6B'],
    'DB' => ['title' => 'Database Admin', 'desc' => 'SQL Optimization, Schema Design, Security', 'icon' => 'fas fa-database', 'color' => '#336791']
];

// Counts for hero stats
$total_companies = 0;
$count_res = mysqli_query($con, "SELECT COUNT(*) AS c FROM companies");
if ($count_res && $row_c = mysqli_fetch_assoc($count_res)) {
    $total_companies = (int) $row_c['c'];
}

$applied_count = 0;
$stmt = mysqli_prepare($con, "SELECT COUNT(*) AS c FROM category_applications WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$applied_res = mysqli_stmt_get_result($stmt);
if ($applied_res && $row_c = mysqli_fetch_assoc($applied_res)) {
    $applied_count = (int) $row_c['c'];
}
mysqli_stmt_close($stmt);

$status_badge = [
    'Pending'     => ['pill-warn', 'Pending'],
    'Approved'    => ['pill-ok', 'Approved'],
    'Accepted'    => ['pill-ok', 'Accepted'],
    'Shortlisted' => ['pill-info', 'Shortlisted'],
    'Reviewing'   => ['pill-info', 'Reviewing'],
    'Interview'   => ['pill-info', 'Interview'],
    'Rejected'    => ['pill-danger', 'Rejected'],
];

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <title>Available Companies | NovaHire</title>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --ac-grad: linear-gradient(135deg, #6d5efc 0%, #8b5cf6 45%, #d946ef 100%);
            --ac-grad-soft: linear-gradient(135deg, rgba(109, 94, 252, .12), rgba(217, 70, 239, .12));
        }

        body { font-family: 'Manrope', sans-serif; }
        .ac-wrap { background: var(--bg); min-height: 70vh; }

        /* ── Hero ── */
        .ac-hero {
            position: relative;
            background: var(--ac-grad);
            margin-top: -16px;
            padding: 74px 0 150px;
            overflow: hidden;
            border-radius: 0 0 38px 38px;
        }
        .ac-hero::before, .ac-hero::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            pointer-events: none;
        }
        .ac-hero::before { top: -120px; right: -80px; width: 380px; height: 380px; background: radial-gradient(circle, rgba(255,255,255,.18), transparent 70%); }
        .ac-hero::after { bottom: -170px; left: -60px; width: 360px; height: 360px; background: radial-gradient(circle, rgba(255,255,255,.12), transparent 70%); }
        .ac-hero-inner { position: relative; z-index: 2; }

        .ac-breadcrumb {
            display: inline-flex; align-items: center; gap: 8px;
            background: rgba(255,255,255,.14); border: 1px solid rgba(255,255,255,.22);
            color: #fff; font-size: .76rem; font-weight: 700; letter-spacing: .04em;
            padding: 7px 15px; border-radius: 999px; margin-bottom: 22px;
        }
        .ac-breadcrumb i { font-size: .7rem; }

        .ac-hero h1 {
            font-family: 'Sora', sans-serif; font-weight: 800; color: #fff;
            font-size: clamp(1.9rem, 4.5vw, 2.9rem); line-height: 1.15;
            margin: 0 0 14px; letter-spacing: -0.02em;
        }
        .ac-hero h1 span {
            background: linear-gradient(90deg, #fde68a, #fbbf24);
            -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent;
        }
        .ac-hero p.lead { color: rgba(255,255,255,.85); font-size: 1.02rem; font-weight: 500; max-width: 640px; margin: 0 auto; }

        .ac-stats { display: flex; justify-content: center; gap: 18px; margin-top: 30px; flex-wrap: wrap; }
        .ac-stat {
            display: flex; align-items: center; gap: 12px;
            background: rgba(255,255,255,.13); border: 1px solid rgba(255,255,255,.2);
            backdrop-filter: blur(8px); border-radius: 16px; padding: 12px 20px;
        }
        .ac-stat .num { font-family: 'Sora', sans-serif; font-weight: 800; font-size: 1.35rem; color: #fff; line-height: 1; }
        .ac-stat .lbl { font-size: .74rem; font-weight: 600; color: rgba(255,255,255,.78); }
        .ac-stat i { font-size: 1.2rem; color: #fde68a; }

        /* ── Category selector (floating card) ── */
        .ac-cats {
            position: relative; z-index: 5;
            max-width: 1080px; margin: -76px auto 0; padding: 24px 26px;
            background: var(--bg-card); border: 1px solid var(--border-light);
            border-radius: 22px; box-shadow: 0 24px 50px -22px rgba(79,70,229,.35);
        }
        .ac-cats-head { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; flex-wrap: wrap; }
        .ac-cats-title { display: inline-flex; align-items: center; gap: 10px; font-family: 'Sora', sans-serif; font-weight: 800; font-size: 1.05rem; color: var(--text); }
        .ac-cats-title .ic {
            width: 38px; height: 38px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-size: .95rem; background: var(--ac-grad);
            box-shadow: 0 6px 14px -6px rgba(139,92,246,.6);
        }
        .ac-cats-desc { margin-left: auto; font-size: .82rem; font-weight: 600; color: var(--text-light); }

        .ac-pills { display: flex; flex-wrap: wrap; gap: 10px; }
        .ac-pill {
            display: inline-flex; align-items: center; gap: 9px;
            padding: 10px 18px; border-radius: 999px;
            border: 1.5px solid var(--border-light);
            background: var(--bg-hover); color: var(--text);
            font-weight: 700; font-size: .85rem; text-decoration: none;
            transition: all .25s;
        }
        .ac-pill i { font-size: 1rem; }
        .ac-pill:hover { transform: translateY(-2px); border-color: rgba(139,92,246,.45); color: var(--primary); text-decoration: none; box-shadow: 0 10px 20px -12px rgba(109,94,252,.5); }
        .ac-pill.active {
            background: var(--ac-grad); color: #fff; border-color: transparent;
            box-shadow: 0 12px 24px -10px rgba(217,70,239,.55);
        }
        .ac-pill.active i { color: #fff !important; }

        /* ── Company cards ── */
        .ac-body { max-width: 1080px; padding-top: 40px; padding-bottom: 60px; }
        .ac-section-head { display: flex; align-items: center; gap: 12px; margin-bottom: 22px; }
        .ac-section-head .ic {
            width: 42px; height: 42px; border-radius: 13px;
            display: flex; align-items: center; justify-content: center;
            color: var(--primary); font-size: 1.05rem; background: var(--ac-grad-soft);
            border: 1px solid rgba(139,92,246,.22);
        }
        .ac-section-head h2 { font-family: 'Sora', sans-serif; font-weight: 800; font-size: 1.3rem; color: var(--text); margin: 0; letter-spacing: -.01em; }
        .ac-section-head p { color: var(--text-muted); font-size: .85rem; margin: 2px 0 0; }

        .ac-card {
            position: relative;
            height: 100%;
            background: var(--bg-card);
            border: 1px solid var(--border-light);
            border-radius: 20px;
            padding: 22px 24px;
            box-shadow: var(--shadow-sm);
            transition: transform .3s, box-shadow .3s, border-color .3s, background .3s;
            overflow: hidden;
        }
        .ac-card::before {
            content: '';
            position: absolute; left: 0; top: 0; bottom: 0; width: 4px;
            background: var(--ac-grad);
            opacity: 0; transition: opacity .3s;
        }
        .ac-card:hover { transform: translateY(-5px); box-shadow: 0 24px 48px -18px rgba(79,70,229,.35); border-color: rgba(139,92,246,.35); }
        .ac-card:hover::before { opacity: 1; }

        .ac-card-top { display: flex; align-items: flex-start; gap: 16px; }
        .ac-logo {
            flex: 0 0 72px; height: 72px;
            display: flex; align-items: center; justify-content: center;
            background: var(--bg-hover); border: 1px solid var(--border-light);
            border-radius: 16px; padding: 10px; overflow: hidden;
        }
        .ac-logo img { max-width: 100%; max-height: 100%; object-fit: contain; }
        .ac-logo .no-img {
            font-family: 'Sora', sans-serif; font-weight: 800; font-size: 1.6rem;
            color: #fff; background: var(--ac-grad); width: 100%; height: 100%;
            display: flex; align-items: center; justify-content: center; border-radius: 9px;
        }
        .ac-head { flex: 1; min-width: 0; }
        .ac-name { font-family: 'Sora', sans-serif; font-weight: 800; font-size: 1.22rem; color: var(--text); margin: 0 0 6px; letter-spacing: -.01em; }
        .ac-name i { color: #38bdf8; font-size: .85rem; vertical-align: 2px; margin-left: 6px; }
        .ac-cmeta { display: flex; flex-direction: column; gap: 4px; }
        .ac-cmeta span { font-size: .78rem; font-weight: 600; color: var(--text-light); display: inline-flex; align-items: center; gap: 7px; }
        .ac-cmeta i { width: 14px; text-align: center; color: var(--primary); font-size: .78rem; }

        .ac-tags { display: flex; flex-wrap: wrap; gap: 8px; margin: 18px 0 14px; }
        .ac-tag {
            display: inline-flex; align-items: center; gap: 6px;
            background: var(--bg-hover); border: 1px solid var(--border-light);
            color: var(--text-muted); font-size: .78rem; font-weight: 700;
            padding: 6px 12px; border-radius: 10px;
        }
        .ac-tag.cat { color: var(--primary); background: var(--ac-grad-soft); border: 1px solid rgba(139,92,246,.22); }
        .ac-tag i { font-size: .8rem; width: 14px; text-align: center; }

        .ac-desc { color: var(--text-muted); font-size: .86rem; line-height: 1.65; margin: 0 0 18px; }
        .ac-desc i { color: var(--primary); }

        .ac-foot { display: flex; align-items: center; justify-content: space-between; gap: 14px; flex-wrap: wrap; }
        .ac-hint { font-size: .74rem; font-weight: 600; color: var(--text-light); display: inline-flex; align-items: center; gap: 7px; }
        .ac-hint i { color: var(--primary); }

        .ac-apply {
            display: inline-flex; align-items: center; gap: 8px;
            font-family: 'Sora', sans-serif; font-weight: 700; font-size: .85rem;
            color: #fff; background: var(--ac-grad); background-size: 150% 150%;
            padding: 12px 22px; border-radius: 13px; border: 0; cursor: pointer;
            box-shadow: 0 10px 22px -10px rgba(139,92,246,.6);
            transition: transform .25s, box-shadow .3s, background-position .4s;
        }
        .ac-apply:hover { transform: translateY(-2px); background-position: 100% 50%; box-shadow: 0 16px 30px -12px rgba(217,70,239,.65); }

        .ac-pill-status {
            display: inline-flex; align-items: center; gap: 8px;
            font-size: .8rem; font-weight: 800; padding: 10px 18px; border-radius: 999px;
        }
        .ac-pill-status.pill-ok { color: #047857; background: rgba(16,185,129,.1); border: 1px solid rgba(16,185,129,.22); }
        .ac-pill-status.pill-warn { color: #b45309; background: rgba(245,158,11,.1); border: 1px solid rgba(245,158,11,.24); }
        .ac-pill-status.pill-info { color: #1d4ed8; background: rgba(59,130,246,.1); border: 1px solid rgba(59,130,246,.22); }
        .ac-pill-status.pill-danger { color: #b91c1c; background: rgba(239,68,68,.1); border: 1px solid rgba(239,68,68,.22); }
        [data-theme="dark"] .ac-pill-status.pill-ok { color: #34d399; }
        [data-theme="dark"] .ac-pill-status.pill-warn { color: #fbbf24; }
        [data-theme="dark"] .ac-pill-status.pill-info { color: #93c5fd; }
        [data-theme="dark"] .ac-pill-status.pill-danger { color: #fca5a5; }

        /* ── Alerts ── */
        .ac-alert {
            display: flex; align-items: flex-start; gap: 12px;
            background: var(--bg-card); border: 1px solid var(--border-light);
            border-left: 4px solid var(--primary); border-radius: 14px;
            padding: 16px 18px; margin-bottom: 24px; box-shadow: var(--shadow-sm);
        }
        .ac-alert .ic { width: 40px; height: 40px; flex-shrink: 0; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1rem; }
        .ac-alert.success { border-left-color: #10b981; }
        .ac-alert.success .ic { color: #047857; background: rgba(16,185,129,.12); }
        .ac-alert.warning { border-left-color: #f59e0b; }
        .ac-alert.warning .ic { color: #b45309; background: rgba(245,158,11,.12); }
        .ac-alert.danger { border-left-color: #ef4444; }
        .ac-alert.danger .ic { color: #b91c1c; background: rgba(239,68,68,.12); }
        .ac-alert strong { display: block; color: var(--text); font-weight: 800; font-size: .92rem; }
        .ac-alert p { margin: 0; color: var(--text-muted); font-size: .83rem; }

        /* ── Empty / locked states ── */
        .ac-empty {
            text-align: center; background: var(--bg-card);
            border: 1px dashed var(--border); border-radius: 22px; padding: 70px 30px;
        }
        .ac-empty .ic {
            width: 92px; height: 92px; margin: 0 auto 22px;
            display: flex; align-items: center; justify-content: center;
            font-size: 2.1rem; color: var(--primary); background: var(--ac-grad-soft);
            border-radius: 28px;
        }
        .ac-empty h3 { font-family: 'Sora', sans-serif; font-weight: 800; color: var(--text); }
        .ac-empty p { color: var(--text-muted); }
        .ac-empty .btn {
            display: inline-flex; align-items: center; gap: 8px;
            font-family: 'Sora', sans-serif; font-weight: 700; font-size: .88rem;
            border: 0; border-radius: 13px; padding: 13px 26px;
            color: #fff; background: var(--ac-grad); box-shadow: 0 12px 24px -12px rgba(217,70,239,.6);
            transition: transform .25s, box-shadow .3s;
        }
        .ac-empty .btn:hover { transform: translateY(-2px); box-shadow: 0 18px 30px -14px rgba(217,70,239,.7); color: #fff; text-decoration: none; }

        /* ── Modal ── */
        .ac-modal {
            display: none; position: fixed; z-index: 2000; left: 0; top: 0;
            width: 100%; height: 100%;
            background: rgba(15,23,42,.55); backdrop-filter: blur(4px);
            animation: acFade .25s ease;
            overflow-y: auto;
        }
        .ac-modal-box {
            background: var(--bg-card); margin: 6% auto;
            border-radius: 22px; width: 92%; max-width: 560px;
            box-shadow: 0 40px 80px -30px rgba(0,0,0,.5);
            animation: acSlide .3s ease; overflow: hidden;
        }
        .ac-modal-head {
            position: relative;
            padding: 26px 28px 30px;
            background: var(--ac-grad);
        }
        .ac-modal-head h3 { font-family: 'Sora', sans-serif; font-weight: 800; color: #fff; font-size: 1.2rem; margin: 0; }
        .ac-modal-head .sub { color: rgba(255,255,255,.85); font-size: .85rem; margin: 6px 0 0; }
        .ac-close {
            position: absolute; top: 16px; right: 18px;
            width: 34px; height: 34px; border-radius: 10px;
            border: 1px solid rgba(255,255,255,.3); background: rgba(255,255,255,.14);
            color: #fff; font-size: 1rem; cursor: pointer; transition: all .2s;
        }
        .ac-close:hover { background: rgba(255,255,255,.3); transform: rotate(90deg); }
        .ac-modal-body { padding: 24px 28px 28px; }
        .ac-field label { font-family: 'Sora', sans-serif; font-weight: 700; font-size: .74rem; letter-spacing: .05em; text-transform: uppercase; color: var(--text-light); margin: 0 0 8px; }
        .ac-field textarea {
            width: 100%; min-height: 118px; resize: vertical;
            border: 1.5px solid var(--border-light); border-radius: 13px;
            background: var(--bg-hover); color: var(--text);
            font-family: 'Manrope', sans-serif; font-size: .88rem;
            padding: 13px 15px; transition: all .2s;
        }
        .ac-field textarea:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 4px rgba(99,102,241,.14); background: var(--bg-card); }
        .ac-tip { display: flex; align-items: flex-start; gap: 9px; font-size: .8rem; font-weight: 600; color: var(--text-light); margin-top: 9px; }
        .ac-tip i { color: #f59e0b; margin-top: 2px; }
        .ac-share {
            display: flex; align-items: flex-start; gap: 9px;
            background: var(--ac-grad-soft); border: 1px solid rgba(139,92,246,.2);
            border-radius: 12px; padding: 12px 14px;
            color: var(--text-muted); font-size: .8rem; font-weight: 600; margin: 16px 0;
        }
        .ac-share i { color: var(--primary); margin-top: 2px; }
        .ac-submit {
            width: 100%; display: inline-flex; align-items: center; justify-content: center; gap: 9px;
            font-family: 'Sora', sans-serif; font-weight: 700; font-size: .92rem;
            color: #fff; background: var(--ac-grad); background-size: 150% 150%;
            border: 0; border-radius: 14px; padding: 15px 20px; cursor: pointer;
            box-shadow: 0 12px 24px -12px rgba(217,70,239,.6);
            transition: transform .25s, box-shadow .3s, background-position .4s;
        }
        .ac-submit:hover { transform: translateY(-2px); background-position: 100% 50%; box-shadow: 0 18px 32px -14px rgba(217,70,239,.7); }

        @keyframes acFade { from { opacity: 0; } to { opacity: 1; } }
        @keyframes acSlide { from { transform: translateY(-40px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

        .ac-fade { opacity: 0; transform: translateY(16px); animation: acUp .5s ease forwards; }
        @keyframes acUp { to { opacity: 1; transform: none; } }

        /* ── Responsive ── */
        @media (max-width: 860px) {
            .ac-hero { padding: 56px 0 136px; border-radius: 0 0 28px 28px; }
            .ac-cats { margin-top: -66px; padding: 20px 18px; border-radius: 18px; }
            .ac-cats-desc { margin-left: 0; width: 100%; }
            .ac-card-top { flex-wrap: wrap; }
            .ac-foot { flex-direction: column; align-items: flex-start; }
        }
        @media (max-width: 480px) {
            .ac-card { padding: 18px; }
            .ac-apply { width: 100%; justify-content: center; }
            .ac-modal-box { margin: 12% auto; }
        }
    </style>
</head>

<body>
<div class="ac-wrap">

    <!-- Hero -->
    <div class="ac-hero">
        <div class="container text-center ac-hero-inner">
            <div class="ac-breadcrumb"><i class="fas fa-home"></i> Dashboard <i class="fas fa-chevron-right"></i> Available Companies</div>
            <h1>Find Your <span>Next Company</span></h1>
            <p class="lead">Apply directly to companies in the categories where you've cleared the skill assessment.</p>
            <div class="ac-stats">
                <div class="ac-stat"><i class="fas fa-building"></i><div><div class="num"><?php echo $total_companies; ?></div><div class="lbl">Companies</div></div></div>
                <div class="ac-stat"><i class="fas fa-trophy"></i><div><div class="num"><?php echo count($passed_categories); ?></div><div class="lbl">Passed Quizzes</div></div></div>
                <div class="ac-stat"><i class="fas fa-paper-plane"></i><div><div class="num"><?php echo $applied_count; ?></div><div class="lbl">Applications</div></div></div>
            </div>
        </div>
    </div>

    <?php if (isset($alert_message)): ?>
    <div class="container">
        <div class="ac-alert <?php echo $alert_type; ?> ac-fade">
            <div class="ic">
                <?php if ($alert_type == 'success'): ?><i class="fas fa-check-circle"></i>
                <?php elseif ($alert_type == 'warning'): ?><i class="fas fa-exclamation-triangle"></i>
                <?php else: ?><i class="fas fa-times-circle"></i><?php endif; ?>
            </div>
            <div>
                <strong><?php echo htmlspecialchars($alert_message); ?></strong>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Category selector -->
    <?php if (count($passed_categories) == 0): ?>
        <div class="container ac-body">
            <div class="ac-empty ac-fade">
                <div class="ic"><i class="fas fa-lock"></i></div>
                <h3>No Categories Unlocked Yet</h3>
                <p>You haven't passed any skill assessment yet. Take a quiz to unlock company applications!</p>
                <a href="browse_jobs.php" class="btn"><i class="fas fa-th-large"></i> Browse by Category</a>
            </div>
        </div>
    <?php else: ?>
        <div class="container">
            <div class="ac-cats ac-fade" style="animation-delay:.08s">
                <div class="ac-cats-head">
                    <div class="ac-cats-title">
                        <div class="ic"><i class="fas fa-th-large"></i></div>
                        Choose a Category
                    </div>
                    <div class="ac-cats-desc"><i class="fas fa-mouse-pointer mr-1"></i> Pick a category to filter companies</div>
                </div>
                <div class="ac-pills">
                    <?php foreach ($passed_categories as $cat): ?>
                        <?php $info = isset($category_info[$cat]) ? $category_info[$cat] : ['title' => $cat, 'icon' => 'fas fa-code', 'color' => '#999']; ?>
                        <a href="?category=<?php echo urlencode($cat); ?>"
                           class="ac-pill <?php echo $selected_category == $cat ? 'active' : ''; ?>">
                            <i class="<?php echo $info['icon']; ?>" style="color: <?php echo $selected_category == $cat ? '#fff' : $info['color']; ?>;"></i>
                            <?php echo $info['title']; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="container ac-body">
            <div class="ac-section-head ac-fade" style="animation-delay:.16s">
                <div class="ic"><i class="fas fa-building"></i></div>
                <div>
                    <h2>
                        <?php echo $category_info[$selected_category]['title'] ?? $selected_category; ?>
                        <span class="text-muted" style="font-weight:600;font-size:.95rem;">&middot; Companies</span>
                    </h2>
                    <p><i class="fas fa-info-circle mr-1"></i><?php echo $category_info[$selected_category]['desc'] ?? ''; ?></p>
                </div>
            </div>

            <?php
            // Get companies that have posted jobs or are registered
            $companies_query = "SELECT DISTINCT c.* FROM companies c 
                               WHERE c.id IS NOT NULL 
                               ORDER BY c.company_name ASC";
            $companies_result = mysqli_query($con, $companies_query);
            $ac_idx = 0;
            if (mysqli_num_rows($companies_result) > 0):
            ?>
                <div class="row">
                    <?php while ($company = mysqli_fetch_assoc($companies_result)): $ac_idx++; ?>
                        <?php
                        // Check if already applied
                        $check_applied = "SELECT id, status FROM category_applications 
                                         WHERE user_id = ? AND company_id = ? AND category = ?";
                        $stmt = mysqli_prepare($con, $check_applied);
                        mysqli_stmt_bind_param($stmt, "iis", $user_id, $company['id'], $selected_category);
                        mysqli_stmt_execute($stmt);
                        $applied_result = mysqli_stmt_get_result($stmt);
                        $application = mysqli_fetch_assoc($applied_result);
                        mysqli_stmt_close($stmt);
                        ?>

                        <div class="col-md-6 mb-4">
                            <div class="ac-card ac-fade" style="animation-delay:<?php echo min(($ac_idx % 4) * 0.08, 0.32); ?>s">
                                <div class="ac-card-top">
                                    <div class="ac-logo" data-initial="<?php echo htmlspecialchars(substr($company['company_name'], 0, 1)); ?>">
                                        <?php if (!empty($company['logo'])): ?>
                                            <img src="<?php echo BASE_URL; ?>/uploads/company_logos/<?php echo htmlspecialchars($company['logo']); ?>"
                                                 alt="<?php echo htmlspecialchars($company['company_name']); ?>"
                                                 class="ac-logo-img" data-fallback="1">
                                        <?php else: ?>
                                            <div class="no-img"><?php echo htmlspecialchars(substr($company['company_name'], 0, 1)); ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="ac-head">
                                        <h3 class="ac-name"><?php echo htmlspecialchars($company['company_name']); ?><i class="fas fa-badge-check"></i></h3>
                                        <div class="ac-cmeta">
                                            <?php if (!empty($company['industry'])): ?>
                                                <span><i class="fas fa-industry"></i> <?php echo htmlspecialchars($company['industry']); ?></span>
                                            <?php endif; ?>
                                            <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($company['company_email'] ?? 'N/A'); ?></span>
                                        </div>
                                    </div>
                                </div>

                                <div class="ac-tags">
                                    <span class="ac-tag cat">
                                        <i class="<?php echo $category_info[$selected_category]['icon'] ?? 'fas fa-code'; ?>"></i>
                                        <?php echo $category_info[$selected_category]['title'] ?? $selected_category; ?>
                                    </span>
                                    <span class="ac-tag"><i class="fas fa-map-marker-alt"></i> Remote</span>
                                    <span class="ac-tag"><i class="fas fa-clock"></i> Full-time</span>
                                    <?php if (!empty($company['company_size'])): ?>
                                        <span class="ac-tag"><i class="fas fa-users"></i> <?php echo htmlspecialchars($company['company_size']); ?></span>
                                    <?php endif; ?>
                                </div>

                                <p class="ac-desc">
                                    <i class="fas fa-info-circle mr-1"></i> <?php echo $category_info[$selected_category]['desc'] ?? ''; ?>
                                </p>

                                <div class="ac-foot">
                                    <span class="ac-hint"><i class="fas fa-shield-alt"></i> Profile &amp; quiz score shared on apply</span>
                                    <?php if ($application): ?>
                                        <?php $bs = $status_badge[$application['status']] ?? ['pill-info', $application['status']]; ?>
                                        <span class="ac-pill-status <?php echo $bs[0]; ?>">
                                            <i class="fas fa-check-circle"></i> Applied &middot; <?php echo $bs[1]; ?>
                                        </span>
                                    <?php else: ?>
                                        <button class="ac-apply" onclick="openApplyModal(<?php echo $company['id']; ?>, '<?php echo htmlspecialchars($company['company_name']); ?>', '<?php echo $selected_category; ?>')">
                                            <i class="fas fa-paper-plane"></i> Apply Now
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="ac-empty ac-fade" style="animation-delay:.2s">
                    <div class="ic"><i class="fas fa-building"></i></div>
                    <h3>No companies available</h3>
                    <p>Check back later for new opportunities!</p>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Application Modal -->
<div id="applyModal" class="ac-modal">
    <div class="ac-modal-box">
        <div class="ac-modal-head">
            <button type="button" class="ac-close" onclick="closeApplyModal()"><i class="fas fa-times"></i></button>
            <h3><i class="fas fa-paper-plane mr-2"></i>Apply to <span id="modalCompanyName"></span></h3>
            <div class="sub">Take the next step in your career journey</div>
        </div>
        <div class="ac-modal-body">
            <form method="POST" action="">
                <input type="hidden" name="company_id" id="modalCompanyId">
                <input type="hidden" name="category" id="modalCategory">

                <div class="ac-field">
                    <label for="user_message"><i class="fas fa-comment-dots mr-1"></i> Cover Message <span style="text-transform:none;font-weight:600;">(Optional)</span></label>
                    <textarea name="user_message" id="user_message"
                              placeholder="Tell the company why you're a great fit for this position..."></textarea>
                    <div class="ac-tip">
                        <i class="fas fa-lightbulb"></i>
                        <span>Mention your experience and skills related to <?php echo htmlspecialchars($selected_category); ?>.</span>
                    </div>
                </div>

                <div class="ac-share">
                    <i class="fas fa-shield-alt"></i>
                    <span>Your profile and quiz scores will be shared with the company.</span>
                </div>

                <button type="submit" name="apply_company" class="ac-submit">
                    <i class="fas fa-check"></i> Submit Application
                </button>
            </form>
        </div>
    </div>
</div>

<script>
    document.querySelectorAll('.ac-logo-img').forEach(function(img) {
        img.addEventListener('error', function() {
            var wrap = img.closest('.ac-logo');
            var div = document.createElement('div');
            div.className = 'no-img';
            div.textContent = wrap.getAttribute('data-initial') || '?';
            img.replaceWith(div);
        });
    });

    function openApplyModal(companyId, companyName, category) {
        document.getElementById('modalCompanyId').value = companyId;
        document.getElementById('modalCompanyName').textContent = companyName;
        document.getElementById('modalCategory').value = category;
        document.getElementById('applyModal').style.display = 'block';
        document.body.style.overflow = 'hidden';
    }

    function closeApplyModal() {
        document.getElementById('applyModal').style.display = 'none';
        document.getElementById('user_message').value = '';
        document.body.style.overflow = '';
    }

    document.getElementById('applyModal').addEventListener('click', function(event) {
        if (event.target === this) {
            closeApplyModal();
        }
    });

    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeApplyModal();
        }
    });
</script>
</body>

</html>
