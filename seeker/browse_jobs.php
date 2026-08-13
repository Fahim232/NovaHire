<?php
// Core setup: session, DB, BASE_URL, helpers
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../admin/dbcon.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../ai/matching.php';

// Pull logged-in user's profile for recommendations
$user_profile = null;
if (isset($_SESSION['id'])) {
    $uid = intval($_SESSION['id']);
    $user_res = mysqli_query($con, "SELECT * FROM user_info WHERE id = $uid");
    if ($user_res && mysqli_num_rows($user_res) === 1) {
        $user_profile = mysqli_fetch_assoc($user_res);
    }
}
$user_skills = [];
if ($user_profile) {
    $raw_skills = strtolower($user_profile['user_skills']);
    $user_skills = array_filter(array_map('trim', explode(',', $raw_skills)));
}
$has_user_skills = !empty($user_skills);

// Get filter parameters
$category_filter = isset($_GET['category']) ? $_GET['category'] : 'all';
$location_filter = isset($_GET['location']) ? $_GET['location'] : '';
$type_filter = isset($_GET['type']) ? $_GET['type'] : 'all';

// Build query
$where_clause = "cj.status = 'active' AND cj.deadline >= CURDATE()";

if ($category_filter != 'all') {
    $where_clause .= " AND cj.job_category = '" . mysqli_real_escape_string($con, $category_filter) . "'";
}

if (!empty($location_filter)) {
    $where_clause .= " AND cj.location LIKE '%" . mysqli_real_escape_string($con, $location_filter) . "%'";
}

if ($type_filter != 'all') {
    $where_clause .= " AND cj.employment_type = '" . mysqli_real_escape_string($con, $type_filter) . "'";
}

// Fetch jobs
$jobs_query = "SELECT cj.*, c.company_name, c.industry, c.logo,
                   (SELECT COUNT(*) FROM company_job_questions WHERE job_id = cj.id) as quiz_count,
                   (SELECT COUNT(*) FROM job_applications WHERE job_id = cj.id) as applicant_count
                   FROM company_jobs cj
                   JOIN companies c ON cj.company_id = c.id
                   WHERE $where_clause";
$jobs_result = mysqli_query($con, $jobs_query);

// Build jobs array with AI recommendation score
$jobs = [];
if ($jobs_result) {
    while ($row = mysqli_fetch_assoc($jobs_result)) {
        if ($has_user_skills && $user_profile) {
            $ai = ai_match_profile_job($user_profile, $row);
            $row['ai'] = $ai;
        }
        $jobs[] = $row;
    }

    // Recommended jobs first (by AI score), then newest
    usort($jobs, function ($a, $b) {
        $scoreA = isset($a['ai']) ? $a['ai']['score'] : 0;
        $scoreB = isset($b['ai']) ? $b['ai']['score'] : 0;
        if ($scoreA === $scoreB) {
            return strcmp($b['posted_date'], $a['posted_date']);
        }
        return $scoreB <=> $scoreA;
    });
}

// Get categories for filter
$categories_query = "SELECT DISTINCT job_category FROM company_jobs WHERE status = 'active'";
$categories_result = mysqli_query($con, $categories_query);

// Count saved jobs for the hero stat
$saved_count = 0;
if (isset($_SESSION['id'])) {
    $sc = mysqli_query($con, "SELECT COUNT(*) AS cnt FROM saved_jobs WHERE user_id=" . intval($_SESSION['id']));
    if ($sc) { $saved_count = intval(mysqli_fetch_assoc($sc)['cnt']); }
}

// Total live jobs
$live_count_q = mysqli_query($con, "SELECT COUNT(*) AS cnt FROM company_jobs WHERE status='active' AND deadline >= CURDATE()");
$live_count = $live_count_q ? intval(mysqli_fetch_assoc($live_count_q)['cnt']) : 0;

$type_color = [
    'Full-Time'   => ['#10b981', 'fa-clock'],
    'Part-Time'   => ['#f59e0b', 'fa-hourglass-half'],
    'Contract'    => ['#3b82f6', 'fa-file-signature'],
    'Internship'  => ['#8b5cf6', 'fa-graduation-cap'],
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>Browse Jobs | NovaHire</title>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bj-grad: linear-gradient(135deg, #6d5efc 0%, #8b5cf6 45%, #d946ef 100%);
            --bj-grad-soft: linear-gradient(135deg, rgba(109,94,252,.12), rgba(217,70,239,.12));
        }

        .bj-wrap { background: var(--bg); min-height: 60vh; }

        /* ── Hero ── */
        .bj-hero {
            position: relative;
            background: var(--bj-grad);
            margin-top: -16px;
            padding: 74px 0 130px;
            overflow: hidden;
            border-radius: 0 0 38px 38px;
        }
        .bj-hero::before, .bj-hero::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            pointer-events: none;
        }
        .bj-hero::before { top: -120px; right: -80px; width: 360px; height: 360px; background: radial-gradient(circle, rgba(255,255,255,.18), transparent 70%); }
        .bj-hero::after { bottom: -160px; left: -60px; width: 340px; height: 340px; background: radial-gradient(circle, rgba(255,255,255,.12), transparent 70%); }
        .bj-hero-inner { position: relative; z-index: 2; }

        .bj-breadcrumb { display: inline-flex; align-items: center; gap: 8px; background: rgba(255,255,255,.14); border: 1px solid rgba(255,255,255,.22); color: #fff; font-size: .76rem; font-weight: 700; letter-spacing: .04em; padding: 7px 15px; border-radius: 999px; margin-bottom: 22px; }
        .bj-breadcrumb i { font-size: .7rem; }

        .bj-hero h1 { font-family: 'Sora', sans-serif; font-weight: 800; color: #fff; font-size: clamp(1.9rem, 4.5vw, 2.9rem); line-height: 1.15; margin: 0 0 14px; letter-spacing: -0.02em; }
        .bj-hero h1 span { background: linear-gradient(90deg, #fde68a, #fbbf24); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent; }
        .bj-hero p.lead { color: rgba(255,255,255,.85); font-size: 1.02rem; font-weight: 500; max-width: 620px; margin: 0 auto; }

        .bj-stats { display: flex; justify-content: center; gap: 18px; margin-top: 30px; flex-wrap: wrap; }
        .bj-stat {
            display: flex; align-items: center; gap: 12px;
            background: rgba(255,255,255,.13); border: 1px solid rgba(255,255,255,.2);
            backdrop-filter: blur(8px); border-radius: 16px; padding: 12px 20px;
        }
        .bj-stat .num { font-family: 'Sora', sans-serif; font-weight: 800; font-size: 1.35rem; color: #fff; line-height: 1; }
        .bj-stat .lbl { font-size: .74rem; font-weight: 600; color: rgba(255,255,255,.78); }
        .bj-stat i { font-size: 1.2rem; color: #fde68a; }

        /* ── Filter bar ── */
        .bj-filters {
            position: relative;
            z-index: 5;
            max-width: 1080px;
            margin: -78px auto 34px;
            padding: 24px 26px;
            background: var(--bg-card);
            border: 1px solid var(--border-light);
            border-radius: 22px;
            box-shadow: 0 20px 45px -18px rgba(79,70,229,.28);
            transition: background .3s, border-color .3s, box-shadow .3s;
        }
        .bj-filters label { font-family: 'Sora', sans-serif; font-weight: 700; font-size: .74rem; letter-spacing: .05em; text-transform: uppercase; color: var(--text-light); margin: 0 0 8px; }
        .bj-select, .bj-input {
            width: 100%;
            border: 1.5px solid var(--border);
            border-radius: 13px;
            padding: 12px 14px;
            font-family: 'Manrope', sans-serif;
            font-size: .9rem;
            font-weight: 600;
            color: var(--text);
            background: var(--bg);
            transition: border-color .25s, box-shadow .25s, background .3s;
            appearance: none;
        }
        .bj-select {
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat; background-position: right 14px center; background-size: 15px;
            padding-right: 40px;
            cursor: pointer;
        }
        .bj-select:focus, .bj-input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 4px rgba(99,102,241,.14); }
        .bj-search-btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 9px;
            width: 100%;
            border: 0; border-radius: 13px;
            padding: 13px 18px;
            font-family: 'Sora', sans-serif; font-weight: 700; font-size: .9rem;
            color: #fff;
            background: var(--bj-grad);
            background-size: 150% 150%;
            box-shadow: 0 10px 22px -10px rgba(139,92,246,.6);
            cursor: pointer;
            transition: transform .25s, box-shadow .3s, background-position .4s;
        }
        .bj-search-btn:hover { transform: translateY(-2px); background-position: 100% 50%; box-shadow: 0 16px 30px -12px rgba(217,70,239,.65); }
        .bj-clear {
            display: inline-flex; align-items: center; gap: 6px;
            color: var(--text-muted); font-weight: 700; font-size: .82rem; text-decoration: none;
        }
        .bj-clear:hover { color: var(--danger); text-decoration: none; }

        /* ── Results toolbar ── */
        .bj-toolbar { max-width: 1080px; margin: 0 auto 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; }
        .bj-count { font-family: 'Sora', sans-serif; font-weight: 800; color: var(--text); font-size: 1.15rem; }
        .bj-count span { color: var(--primary); }
        .bj-chip {
            display: inline-flex; align-items: center; gap: 7px;
            background: var(--bj-grad-soft); border: 1px solid rgba(139,92,246,.25);
            color: var(--primary); font-weight: 800; font-size: .76rem;
            padding: 7px 14px; border-radius: 999px;
        }

        /* ── Job cards ── */
        .bj-list { max-width: 1080px; margin: 0 auto; }
        .bj-card {
            position: relative;
            display: flex;
            background: var(--bg-card);
            border: 1px solid var(--border-light);
            border-radius: 20px;
            padding: 22px 24px;
            margin-bottom: 18px;
            box-shadow: var(--shadow-sm);
            transition: transform .3s, box-shadow .3s, border-color .3s, background .3s;
            overflow: hidden;
        }
        .bj-card::before {
            content: '';
            position: absolute; left: 0; top: 0; bottom: 0; width: 4px;
            background: var(--bj-grad);
            opacity: 0; transition: opacity .3s;
        }
        .bj-card:hover { transform: translateY(-5px); box-shadow: 0 24px 48px -18px rgba(79,70,229,.35); border-color: rgba(139,92,246,.35); }
        .bj-card:hover::before { opacity: 1; }

        .bj-logo {
            flex: 0 0 76px; height: 76px;
            display: flex; align-items: center; justify-content: center;
            background: var(--bg-hover);
            border: 1px solid var(--border-light);
            border-radius: 16px;
            padding: 10px; margin-right: 20px;
            overflow: hidden;
        }
        .bj-logo img { max-width: 100%; max-height: 100%; object-fit: contain; }
        .bj-logo .no-img { font-family: 'Sora', sans-serif; font-weight: 800; font-size: 1.3rem; color: var(--primary); }

        .bj-body { flex: 1; min-width: 0; }
        .bj-top { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 6px; }
        .bj-company { display: inline-flex; align-items: center; gap: 7px; font-size: .82rem; font-weight: 800; color: var(--primary); letter-spacing: .01em; }
        .bj-company i { font-size: .9rem; }
        .bj-title { font-family: 'Sora', sans-serif; font-weight: 800; font-size: 1.22rem; color: var(--text); margin: 0 0 10px; letter-spacing: -0.01em; transition: color .2s; }
        a.bj-title { text-decoration: none; }
        a.bj-title:hover { color: var(--primary); }

        .bj-meta { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; }
        .bj-meta-item {
            display: inline-flex; align-items: center; gap: 6px;
            background: var(--bg-hover);
            border: 1px solid var(--border-light);
            color: var(--text-muted);
            font-size: .78rem; font-weight: 700;
            padding: 6px 12px; border-radius: 10px;
        }
        .bj-meta-item i { color: var(--primary); font-size: .8rem; width: 14px; text-align: center; }
        .bj-meta-item.salary { color: #059669; background: rgba(16,185,129,.08); border-color: rgba(16,185,129,.18); }
        .bj-meta-item.salary i { color: #10b981; }

        .bj-desc { color: var(--text-muted); font-size: .86rem; line-height: 1.65; margin: 0 0 13px; }
        .bj-tags { display: flex; flex-wrap: wrap; gap: 7px; margin-bottom: 14px; }
        .bj-tag {
            font-size: .74rem; font-weight: 700;
            color: #6d5efc;
            background: rgba(109,94,252,.09);
            border: 1px solid rgba(109,94,252,.16);
            padding: 5px 12px; border-radius: 999px;
            transition: all .2s;
        }
        .bj-tag:hover { background: rgba(109,94,252,.18); }
        [data-theme="dark"] .bj-tag { color: #c4b5fd; }
        .bj-tag.more { color: var(--text-muted); background: var(--bg-hover); border-color: var(--border-light); }

        .bj-bottom { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .bj-badge {
            display: inline-flex; align-items: center; gap: 6px;
            font-size: .72rem; font-weight: 800;
            padding: 6px 12px; border-radius: 999px;
        }
        .bj-badge.cat { color: var(--primary); background: var(--bj-grad-soft); border: 1px solid rgba(139,92,246,.25); }
        .bj-badge.quiz-ok { color: #047857; background: rgba(16,185,129,.1); border: 1px solid rgba(16,185,129,.22); }
        .bj-badge.quiz-req { color: #b45309; background: rgba(245,158,11,.1); border: 1px solid rgba(245,158,11,.24); }
        .bj-badge.noquiz { color: var(--text-muted); background: var(--bg-hover); border: 1px solid var(--border-light); }
        .bj-badge.ai {
            color: #7c3aed; background: rgba(139,92,246,.1);
            border: 1px solid rgba(139,92,246,.28);
        }
        .bj-applicants { margin-left: auto; font-size: .78rem; font-weight: 700; color: var(--text-light); }

        /* ── Right column / actions ── */
        .bj-side { display: flex; flex-direction: column; align-items: flex-end; gap: 12px; margin-left: 20px; }
        .bj-ai-ring { position: relative; width: 64px; height: 64px; }
        .bj-ai-ring svg { transform: rotate(-90deg); }
        .bj-ai-ring .ring-bg { stroke: var(--border-light); }
        .bj-ai-ring .ring-fg { stroke: url(#bjGrad); stroke-linecap: round; transition: stroke-dashoffset 1s ease; }
        .bj-ai-val {
            position: absolute; inset: 0;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            font-family: 'Sora', sans-serif; font-weight: 800; font-size: .95rem; color: #7c3aed;
            line-height: 1;
        }
        [data-theme="dark"] .bj-ai-val { color: #c4b5fd; }
        .bj-ai-cap { font-size: .5rem; font-weight: 700; color: var(--text-light); letter-spacing: .04em; margin-top: 2px; }

        .bj-save {
            width: 44px; height: 44px; border-radius: 14px;
            display: inline-flex; align-items: center; justify-content: center;
            border: 1.5px solid var(--border);
            background: var(--bg);
            color: var(--text-light);
            font-size: 1rem;
            cursor: pointer;
            transition: all .25s;
        }
        .bj-save:hover { border-color: #f87171; color: #ef4444; transform: translateY(-2px); }
        .bj-save.saved { background: #fee2e2; border-color: #fca5a5; color: #ef4444; }
        .bj-save i { pointer-events: none; transition: transform .2s; }
        .bj-save.saved i { animation: bj-pop .35s ease; }
        @keyframes bj-pop { 0% { transform: scale(.4); } 60% { transform: scale(1.3); } 100% { transform: scale(1); } }

        .bj-apply {
            display: inline-flex; align-items: center; gap: 8px;
            font-family: 'Sora', sans-serif; font-weight: 700; font-size: .85rem;
            color: #fff;
            background: var(--bj-grad); background-size: 150% 150%;
            padding: 12px 22px; border-radius: 13px;
            box-shadow: 0 10px 22px -10px rgba(139,92,246,.6);
            text-decoration: none;
            transition: transform .25s, box-shadow .3s, background-position .4s;
        }
        .bj-apply:hover { transform: translateY(-2px); background-position: 100% 50%; color: #fff; text-decoration: none; box-shadow: 0 16px 30px -12px rgba(217,70,239,.65); }
        .bj-apply.quiz { background: linear-gradient(135deg, #f6ad55, #ed8936); background-size: 150% 150%; box-shadow: 0 10px 22px -10px rgba(237,137,54,.6); }
        .bj-apply.quiz:hover { box-shadow: 0 16px 30px -12px rgba(245,158,11,.65); }

        /* ── Empty state ── */
        .bj-empty {
            max-width: 1080px; margin: 0 auto;
            text-align: center;
            background: var(--bg-card);
            border: 1px dashed var(--border);
            border-radius: 22px;
            padding: 70px 30px;
        }
        .bj-empty .ic {
            width: 92px; height: 92px; margin: 0 auto 22px;
            display: flex; align-items: center; justify-content: center;
            font-size: 2.1rem; color: var(--primary);
            background: var(--bj-grad-soft);
            border-radius: 28px;
        }
        .bj-empty h3 { font-family: 'Sora', sans-serif; font-weight: 800; color: var(--text); }
        .bj-empty p { color: var(--text-muted); }
        .bj-empty .btn { display: inline-flex; align-items: center; gap: 8px; font-family: 'Sora', sans-serif; font-weight: 700; border: 0; border-radius: 13px; padding: 13px 26px; color: #fff; background: var(--bj-grad); }

        .bj-footer { text-align: center; padding: 34px 0 40px; color: var(--text-light); font-size: .82rem; font-weight: 600; }

        .bj-fade { opacity: 0; transform: translateY(16px); animation: bj-up .5s ease forwards; }
        @keyframes bj-up { to { opacity: 1; transform: none; } }

        @media (max-width: 860px) {
            .bj-hero { padding: 56px 0 118px; border-radius: 0 0 28px 28px; }
            .bj-filters { margin-top: -66px; padding: 20px 18px; border-radius: 18px; }
            .bj-card { flex-direction: column; padding: 18px; }
            .bj-logo { flex: 0 0 64px; height: 64px; margin-right: 0; margin-bottom: 14px; width: 64px; }
            .bj-side { flex-direction: row; align-items: center; justify-content: flex-end; margin-left: 0; margin-top: 4px; width: 100%; }
            .bj-applicants { margin-left: 0; }
        }
        @media (max-width: 480px) {
            .bj-toolbar { justify-content: center; }
            .bj-card { padding: 16px; }
            .bj-apply { width: 100%; justify-content: center; }
        }
    </style>
</head>

<body>
<div class="bj-wrap">

    <!-- Hero -->
    <div class="bj-hero">
        <div class="container text-center bj-hero-inner">
            <div class="bj-breadcrumb"><i class="fas fa-home"></i> Dashboard <i class="fas fa-chevron-right"></i> Browse Jobs</div>
            <h1>Explore <span>Opportunities</span></h1>
            <p class="lead">Hand-picked career openings from trusted companies — matched to your profile.</p>
            <div class="bj-stats">
                <div class="bj-stat"><i class="fas fa-briefcase"></i><div><div class="num"><?php echo $live_count; ?></div><div class="lbl">Live Jobs</div></div></div>
                <div class="bj-stat"><i class="fas fa-heart"></i><div><div class="num"><?php echo $saved_count; ?></div><div class="lbl">Saved Jobs</div></div></div>
                <div class="bj-stat"><i class="fas fa-robot"></i><div><div class="num"><?php echo $has_user_skills ? 'AI' : '—'; ?></div><div class="lbl">Smart Match</div></div></div>
            </div>
        </div>
    </div>

    <!-- Filter bar -->
    <div class="bj-filters">
        <form method="GET" action="">
            <div class="row align-items-end">
                <div class="col-lg-4 col-md-6 mb-3 mb-lg-0">
                    <label><i class="fas fa-tag mr-1"></i> Category</label>
                    <select name="category" class="bj-select">
                        <option value="all">All Categories</option>
                        <?php while ($cat = mysqli_fetch_assoc($categories_result)): ?>
                            <option value="<?php echo htmlspecialchars($cat['job_category']); ?>" <?php echo ($category_filter == $cat['job_category']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['job_category']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-lg-4 col-md-6 mb-3 mb-lg-0">
                    <label><i class="fas fa-map-marker-alt mr-1"></i> Location</label>
                    <input type="text" name="location" class="bj-input" placeholder="City, country, or remote..." value="<?php echo htmlspecialchars($location_filter); ?>">
                </div>
                <div class="col-lg-2 col-md-6 mb-3 mb-lg-0">
                    <label><i class="fas fa-briefcase mr-1"></i> Type</label>
                    <select name="type" class="bj-select">
                        <option value="all">All Types</option>
                        <option value="Full-Time" <?php echo ($type_filter == 'Full-Time') ? 'selected' : ''; ?>>Full-Time</option>
                        <option value="Part-Time" <?php echo ($type_filter == 'Part-Time') ? 'selected' : ''; ?>>Part-Time</option>
                        <option value="Contract" <?php echo ($type_filter == 'Contract') ? 'selected' : ''; ?>>Contract</option>
                        <option value="Internship" <?php echo ($type_filter == 'Internship') ? 'selected' : ''; ?>>Internship</option>
                    </select>
                </div>
                <div class="col-lg-2 col-md-6">
                    <button type="submit" class="bj-search-btn">
                        <i class="fas fa-search"></i>Search
                    </button>
                    <?php if ($category_filter != 'all' || $location_filter != '' || $type_filter != 'all'): ?>
                        <div class="text-center mt-2">
                            <a href="browse_jobs.php" class="bj-clear"><i class="fas fa-undo-alt"></i>Clear filters</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <!-- Toolbar -->
    <div class="bj-toolbar">
        <div class="bj-count"><span><?php echo count($jobs); ?></span> <?php echo count($jobs) === 1 ? 'Job' : 'Jobs'; ?> Found</div>
        <?php if ($has_user_skills && count($jobs) > 0): ?>
            <div class="bj-chip"><i class="fas fa-wand-magic-sparkles"></i>Sorted by best AI match</div>
        <?php endif; ?>
    </div>

    <!-- Jobs List -->
    <div class="bj-list mb-5">
        <?php if (count($jobs) > 0): ?>
            <?php foreach ($jobs as $idx => $job): ?>
                <?php $ai_match = isset($job['ai']) ? $job['ai'] : null; ?>
                <?php $tc = $type_color[$job['employment_type']] ?? ['#8b5cf6', 'fa-briefcase']; ?>
                <div class="bj-card bj-fade" style="animation-delay:<?php echo min($idx * 0.04, 0.4); ?>s;">
                    <div class="bj-logo">
                        <?php if (!empty($job['logo'])): ?>
                            <img src="<?php echo BASE_URL; ?>/uploads/company_logos/<?php echo htmlspecialchars($job['logo']); ?>" alt="<?php echo htmlspecialchars($job['company_name']); ?>">
                        <?php else: ?>
                            <span class="no-img"><?php echo strtoupper(substr($job['company_name'], 0, 1)); ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="bj-body">
                        <div class="bj-top">
                            <span class="bj-company"><i class="fas fa-building"></i><?php echo htmlspecialchars($job['company_name']); ?></span>
                            <span style="font-size:.72rem;color:var(--text-light);font-weight:700;"><i class="fas fa-industry mr-1"></i><?php echo htmlspecialchars($job['industry']); ?></span>
                        </div>
                        <a href="job_details.php?id=<?php echo $job['id']; ?>" class="bj-title"><?php echo htmlspecialchars($job['job_title']); ?></a>

                        <div class="bj-meta">
                            <span class="bj-meta-item"><i class="fas fa-map-marker-alt"></i><?php echo htmlspecialchars($job['location']); ?></span>
                            <span class="bj-meta-item"><i class="fas <?php echo $tc[1]; ?>" style="color:<?php echo $tc[0]; ?>;"></i><?php echo htmlspecialchars($job['employment_type']); ?></span>
                            <span class="bj-meta-item"><i class="fas fa-briefcase"></i><?php echo htmlspecialchars($job['experience_required']); ?></span>
                            <?php if (!empty($job['salary_range'])): ?>
                                <span class="bj-meta-item salary"><i class="fas fa-circle-dollar"></i><?php echo htmlspecialchars($job['salary_range']); ?></span>
                            <?php endif; ?>
                        </div>

                        <p class="bj-desc"><?php echo htmlspecialchars(substr($job['job_description'], 0, 180)); ?><?php echo strlen($job['job_description']) > 180 ? '…' : ''; ?></p>

                        <div class="bj-tags">
                            <?php
                            $skills = explode(',', $job['skills_required']);
                            $display_skills = array_slice($skills, 0, 5);
                            foreach ($display_skills as $skill) {
                                echo '<span class="bj-tag">' . htmlspecialchars(trim($skill)) . '</span>';
                            }
                            if (count($skills) > 5) {
                                echo '<span class="bj-tag more">+' . (count($skills) - 5) . ' more</span>';
                            }
                            ?>
                        </div>

                        <div class="bj-bottom">
                            <span class="bj-badge cat"><i class="fas fa-tag"></i><?php echo htmlspecialchars($job['job_category']); ?></span>
                            <?php if ($job['quiz_count'] > 0): ?>
                                <?php
                                $quiz_passed = false;
                                if (isset($_SESSION['id'])) {
                                    $chk_quiz = mysqli_query($con, "SELECT * FROM job_applications WHERE user_id=".$_SESSION['id']." AND job_id=".$job['id']." AND quiz_status='passed'");
                                    $quiz_passed = mysqli_num_rows($chk_quiz) > 0;
                                }
                                ?>
                                <?php if ($quiz_passed): ?>
                                    <span class="bj-badge quiz-ok"><i class="fas fa-check-circle"></i>Quiz Passed</span>
                                <?php else: ?>
                                    <span class="bj-badge quiz-req"><i class="fas fa-clipboard-check"></i>Quiz Required</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="bj-badge noquiz"><i class="fas fa-check-circle"></i>No Quiz</span>
                            <?php endif; ?>
                            <?php if ($ai_match): ?>
                                <span class="bj-badge ai"><i class="fas fa-robot"></i><?php echo $ai_match['score']; ?>% AI Match</span>
                            <?php endif; ?>
                            <span class="bj-applicants"><i class="fas fa-users mr-1"></i><?php echo $job['applicant_count']; ?> applicants · <?php echo $job['vacancy_count']; ?> positions</span>
                        </div>
                    </div>

                    <div class="bj-side">
                        <?php
                        $is_saved = false;
                        if (isset($_SESSION['id'])) {
                            $chk = mysqli_query($con, "SELECT id FROM saved_jobs WHERE user_id=".$_SESSION['id']." AND job_id=".$job['id']);
                            $is_saved = mysqli_num_rows($chk) > 0;
                        }
                        ?>
                        <?php if ($ai_match): ?>
                            <div class="bj-ai-ring" title="<?php echo $ai_match['score']; ?>% match with your profile">
                                <svg width="64" height="64" viewBox="0 0 64 64">
                                    <defs>
                                        <linearGradient id="bjGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                                            <stop offset="0%" stop-color="#6d5efc"/>
                                            <stop offset="100%" stop-color="#d946ef"/>
                                        </linearGradient>
                                    </defs>
                                    <circle class="ring-bg" cx="32" cy="32" r="26" fill="none" stroke-width="6"/>
                                    <circle class="ring-fg" cx="32" cy="32" r="26" fill="none" stroke-width="6"
                                            stroke-dasharray="<?php echo 2 * M_PI * 26; ?>"
                                            stroke-dashoffset="<?php echo 2 * M_PI * 26 * (1 - $ai_match['score'] / 100); ?>"/>
                                </svg>
                                <div class="bj-ai-val"><?php echo $ai_match['score']; ?>%<span class="bj-ai-cap">match</span></div>
                            </div>
                        <?php endif; ?>

                        <button class="bj-save <?php echo $is_saved ? 'saved' : ''; ?>" onclick="toggleSave(this, <?php echo $job['id']; ?>)" title="<?php echo $is_saved ? 'Unsave Job' : 'Save Job'; ?>">
                            <i class="fa<?php echo $is_saved ? 's' : 'r'; ?> fa-heart"></i>
                        </button>

                        <?php if ($job['quiz_count'] > 0): ?>
                            <a href="job_details.php?id=<?php echo $job['id']; ?>" class="bj-apply quiz">
                                <i class="fas fa-clipboard-check"></i>Quiz &amp; Apply
                            </a>
                        <?php else: ?>
                            <a href="job_details.php?id=<?php echo $job['id']; ?>" class="bj-apply">
                                <i class="fas fa-arrow-right"></i>Apply Now
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="bj-empty">
                <div class="ic"><i class="fas fa-magnifying-glass"></i></div>
                <h3>No Jobs Found</h3>
                <p>Try adjusting your filters to discover more opportunities.</p>
                <a href="browse_jobs.php" class="btn"><i class="fas fa-rotate-right mr-2"></i>Clear All Filters</a>
            </div>
        <?php endif; ?>
    </div>

    <div class="bj-footer">
        &copy; <?php echo date('Y'); ?> NovaHire · Browse Available Jobs
    </div>
</div>

<script>
function toggleSave(btn, jobId) {
    fetch('api/toggle_save_job.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'job_id=' + jobId
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            if (data.saved) {
                btn.classList.add('saved');
                btn.querySelector('i').className = 'fas fa-heart';
                btn.title = 'Unsave Job';
            } else {
                btn.classList.remove('saved');
                btn.querySelector('i').className = 'far fa-heart';
                btn.title = 'Save Job';
            }
        }
    });
}
</script>
</body>

</html>
