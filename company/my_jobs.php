<?php
    session_start();
    include '../admin/dbcon.php';

    // Check if company is logged in
    if (!isset($_SESSION['company_id'])) {
        header('Location: ../company_login.php');
        exit;
    }

    $company_id = $_SESSION['company_id'];

    // Fetch jobs
    $jobs_query = "SELECT cj.*, 
                   (SELECT COUNT(*) FROM job_applications WHERE job_id = cj.id) as application_count,
                   (SELECT COUNT(*) FROM job_applications WHERE job_id = cj.id AND quiz_status = 'passed') as qualified_count,
                   (SELECT COUNT(*) FROM company_job_questions WHERE job_id = cj.id) as quiz_count
                   FROM company_jobs cj 
                   WHERE cj.company_id = $company_id 
                   ORDER BY cj.posted_date DESC";
    $jobs_result = mysqli_query($con, $jobs_query);

    // Handle job status update
    if (isset($_POST['update_status'])) {
        $job_id = intval($_POST['job_id']);
        $new_status = mysqli_real_escape_string($con, $_POST['status']);
        $update_query = "UPDATE company_jobs SET status = '$new_status' WHERE id = $job_id AND company_id = $company_id";
        if (mysqli_query($con, $update_query)) {
            header("Location: my_jobs.php?updated=1");
            exit;
        }
    }

    // Handle job deletion
    if (isset($_GET['delete_id'])) {
        $delete_id = intval($_GET['delete_id']);
        $delete_query = "DELETE FROM company_jobs WHERE id = $delete_id AND company_id = $company_id";
        if (mysqli_query($con, $delete_query)) {
            header("Location: my_jobs.php?deleted=1");
            exit;
        }
    }

    // Stats
    $stats = ['total' => 0, 'active' => 0, 'draft' => 0, 'closed' => 0, 'applications' => 0];
    $stats_q = "SELECT status, COUNT(*) as c FROM company_jobs WHERE company_id = $company_id GROUP BY status";
    $stats_r = mysqli_query($con, $stats_q);
    while ($s = mysqli_fetch_assoc($stats_r)) {
        $stats['total'] += $s['c'];
        if ($s['status'] == 'active') $stats['active'] = $s['c'];
        elseif ($s['status'] == 'draft') $stats['draft'] = $s['c'];
        elseif ($s['status'] == 'closed') $stats['closed'] = $s['c'];
    }
    $apps_q = "SELECT COUNT(*) as c FROM job_applications ja JOIN company_jobs cj ON ja.job_id = cj.id WHERE cj.company_id = $company_id";
    $apps_r = mysqli_query($con, $apps_q);
    $stats['applications'] = intval(mysqli_fetch_assoc($apps_r)['c']);

    $category_styles = [
        'Java'        => ['icon' => 'fa-brands fa-java', 'color' => '#f89820'],
        'Python'      => ['icon' => 'fa-brands fa-python', 'color' => '#3776ab'],
        'Frontend'    => ['icon' => 'fa-code', 'color' => '#7c3aed'],
        'PHP'         => ['icon' => 'fa-brands fa-php', 'color' => '#8993be'],
        'Finance'     => ['icon' => 'fa-chart-line', 'color' => '#10b981'],
        'Healthcare'  => ['icon' => 'fa-heart-pulse', 'color' => '#f43f5e'],
        'Education'   => ['icon' => 'fa-graduation-cap', 'color' => '#f59e0b'],
        'Engineering' => ['icon' => 'fa-gears', 'color' => '#0ea5e9'],
        'Sales'       => ['icon' => 'fa-bullhorn', 'color' => '#8b5cf6'],
        'HR'          => ['icon' => 'fa-users', 'color' => '#ec4899'],
        'Legal'       => ['icon' => 'fa-gavel', 'color' => '#6366f1'],
        'Media'       => ['icon' => 'fa-video', 'color' => '#ef4444'],
        'Logistics'   => ['icon' => 'fa-truck-fast', 'color' => '#14b8a6'],
        'Consulting'  => ['icon' => 'fa-comments', 'color' => '#06b6d4'],
        'Retail'      => ['icon' => 'fa-store', 'color' => '#f97316'],
        'QA'          => ['icon' => 'fa-bug', 'color' => '#22c55e'],
    ];
    $default_style = ['icon' => 'fa-briefcase', 'color' => '#4f46e5'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>My Jobs | Company Dashboard</title>
    <?php include '../includes/links.php'; ?>
    <style>
        :root {
            --mj-bg: #f4f6fb;
            --mj-card: #ffffff;
            --mj-card-hover: #ffffff;
            --mj-border: #e5e9f2;
            --mj-text: #1e293b;
            --mj-muted: #64748b;
            --mj-primary: #4f46e5;
            --mj-primary-2: #7c3aed;
            --mj-soft: #eef2ff;
            --mj-shadow: 0 10px 30px rgba(15, 23, 42, 0.07);
        }
        [data-theme="dark"] {
            --mj-bg: #0f172a;
            --mj-card: #111827;
            --mj-card-hover: #151e33;
            --mj-border: #28334a;
            --mj-text: #e8edff;
            --mj-muted: #94a3b8;
            --mj-primary: #8b5cf6;
            --mj-primary-2: #a78bfa;
            --mj-soft: #1e293b;
            --mj-shadow: 0 10px 30px rgba(0, 0, 0, 0.45);
        }

        body {
            background:
                radial-gradient(circle at 8% 12%, rgba(99, 102, 241, 0.10), transparent 28%),
                radial-gradient(circle at 92% 8%, rgba(217, 70, 239, 0.08), transparent 26%),
                var(--mj-bg);
            color: var(--mj-text);
            min-height: 100vh;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }

        .mj-wrap { max-width: 1200px; margin: 0 auto; padding: 34px 24px 60px; }

        /* ── Hero banner ── */
        .mj-hero {
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 55%, #a855f7 100%);
            border-radius: 22px;
            padding: 30px 34px;
            color: #fff;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            box-shadow: 0 20px 40px rgba(79, 70, 229, 0.28);
        }
        .mj-hero::before {
            content: '';
            position: absolute;
            right: -80px; top: -80px;
            width: 260px; height: 260px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.10);
        }
        .mj-hero::after {
            content: '';
            position: absolute;
            right: 60px; bottom: -110px;
            width: 220px; height: 220px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.08);
        }
        .mj-hero h1 { font-weight: 800; font-size: 1.75rem; color: #fff; margin: 0 0 6px; }
        .mj-hero p { color: rgba(255, 255, 255, 0.85); margin: 0; font-size: 0.95rem; }
        .mj-hero-btn {
            position: relative; z-index: 1;
            background: #fff; color: #4f46e5;
            font-weight: 700; border: none;
            padding: 12px 26px; border-radius: 14px;
            display: inline-flex; align-items: center; gap: 9px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
            transition: transform .2s ease, box-shadow .2s ease;
        }
        .mj-hero-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 30px rgba(0, 0, 0, 0.22);
            color: #4f46e5; text-decoration: none;
        }

        /* ── Stats ── */
        .mj-stats { display: grid; grid-template-columns: repeat(5, 1fr); gap: 16px; margin-top: 22px; }
        .mj-stat {
            background: var(--mj-card);
            border: 1px solid var(--mj-border);
            border-radius: 16px;
            padding: 18px 20px;
            display: flex;
            align-items: center;
            gap: 14px;
            box-shadow: var(--mj-shadow);
            transition: transform .2s ease, box-shadow .2s ease;
        }
        .mj-stat:hover { transform: translateY(-4px); box-shadow: 0 18px 38px rgba(79, 70, 229, 0.14); }
        .mj-stat-ico {
            width: 46px; height: 46px;
            border-radius: 13px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.15rem;
            flex-shrink: 0;
        }
        .mj-stat b { display: block; font-size: 1.45rem; line-height: 1.1; color: var(--mj-text); }
        .mj-stat span { font-size: 0.76rem; color: var(--mj-muted); font-weight: 600; text-transform: uppercase; letter-spacing: .4px; }

        /* ── Toolbar ── */
        .mj-toolbar {
            display: flex; justify-content: space-between; align-items: center;
            flex-wrap: wrap; gap: 14px;
            margin: 30px 0 20px;
        }
        .mj-search {
            position: relative; flex: 1; min-width: 250px; max-width: 400px;
        }
        .mj-search i {
            position: absolute; left: 16px; top: 50%; transform: translateY(-50%);
            color: var(--mj-muted); font-size: 0.9rem;
        }
        .mj-search input {
            width: 100%;
            background: var(--mj-card);
            border: 1.5px solid var(--mj-border);
            color: var(--mj-text);
            border-radius: 13px;
            padding: 12px 16px 12px 42px;
            font-size: 0.92rem;
            outline: none;
            transition: border-color .2s ease, box-shadow .2s ease;
        }
        .mj-search input:focus { border-color: var(--mj-primary); box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.15); }
        .mj-search input::placeholder { color: var(--mj-muted); }

        .mj-filters { display: flex; gap: 8px; flex-wrap: wrap; }
        .mj-fbtn {
            border: 1.5px solid var(--mj-border);
            background: var(--mj-card);
            color: var(--mj-muted);
            font-weight: 600; font-size: 0.83rem;
            padding: 9px 16px;
            border-radius: 12px;
            cursor: pointer;
            transition: all .2s ease;
        }
        .mj-fbtn:hover { border-color: var(--mj-primary); color: var(--mj-primary); }
        .mj-fbtn.active {
            background: linear-gradient(135deg, var(--mj-primary), var(--mj-primary-2));
            color: #fff; border-color: transparent;
            box-shadow: 0 8px 20px rgba(79, 70, 229, 0.3);
        }
        .mj-fbtn .cnt {
            display: inline-block; margin-left: 6px;
            background: rgba(0, 0, 0, 0.08);
            border-radius: 20px; padding: 1px 8px; font-size: 0.72rem;
        }
        .mj-fbtn.active .cnt { background: rgba(255, 255, 255, 0.22); }

        /* ── Job cards ── */
        .mj-list { display: flex; flex-direction: column; gap: 18px; }
        .job-card {
            background: var(--mj-card);
            border: 1px solid var(--mj-border);
            border-radius: 18px;
            padding: 22px 24px;
            display: flex;
            align-items: center;
            gap: 20px;
            box-shadow: var(--mj-shadow);
            transition: transform .22s ease, box-shadow .22s ease, border-color .22s ease;
            animation: mjIn .4s ease both;
        }
        .job-card:hover {
            transform: translateY(-3px);
            border-color: var(--mj-primary);
            box-shadow: 0 20px 40px rgba(79, 70, 229, 0.16);
        }
        @keyframes mjIn {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .job-cat-ico {
            width: 56px; height: 56px;
            border-radius: 16px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.35rem;
            flex-shrink: 0;
        }
        .job-main { flex: 1; min-width: 0; }
        .job-title-row { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .job-title-row h3 {
            font-size: 1.12rem; font-weight: 700; margin: 0;
            color: var(--mj-text);
        }
        .job-badge {
            font-size: 0.7rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: .5px;
            padding: 4px 11px; border-radius: 20px;
            display: inline-flex; align-items: center; gap: 6px;
        }
        .job-badge i { font-size: 0.5rem; }
        .job-badge.active  { background: rgba(16, 185, 129, 0.14); color: #10b981; }
        .job-badge.draft   { background: rgba(245, 158, 11, 0.14); color: #f59e0b; }
        .job-badge.closed  { background: rgba(239, 68, 68, 0.14); color: #ef4444; }

        .job-meta { display: flex; flex-wrap: wrap; gap: 16px; margin-top: 10px; }
        .job-meta span { font-size: 0.83rem; color: var(--mj-muted); }
        .job-meta i { margin-right: 5px; color: var(--mj-primary); opacity: .8; }

        .job-divider {
            width: 1px; height: 72px;
            background: var(--mj-border);
            flex-shrink: 0;
        }

        .job-stats { display: flex; gap: 22px; flex-shrink: 0; }
        .job-stat { text-align: center; min-width: 58px; }
        .job-stat b { display: block; font-size: 1.3rem; color: var(--mj-text); }
        .job-stat span { font-size: 0.72rem; color: var(--mj-muted); font-weight: 600; text-transform: uppercase; letter-spacing: .3px; }
        .job-stat b.apps { color: #3b82f6; }
        .job-stat b.qual { color: #10b981; }
        .job-stat b.quiz { color: #8b5cf6; }

        .job-actions {
            display: flex; align-items: center; gap: 8px;
            flex-shrink: 0;
        }
        .act-btn {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 9px 15px; border-radius: 11px;
            font-size: 0.82rem; font-weight: 600;
            border: 1.5px solid var(--mj-border);
            background: var(--mj-card);
            color: var(--mj-text);
            text-decoration: none;
            cursor: pointer;
            transition: all .18s ease;
            white-space: nowrap;
        }
        .act-btn:hover { transform: translateY(-2px); text-decoration: none; }
        .act-btn i { font-size: 0.85rem; }

        .act-edit { background: rgba(59, 130, 246, 0.10); border-color: rgba(59, 130, 246, 0.35); color: #3b82f6; }
        .act-edit:hover { background: #3b82f6; color: #fff; }
        .act-quiz { background: rgba(139, 92, 246, 0.10); border-color: rgba(139, 92, 246, 0.35); color: #8b5cf6; }
        .act-quiz:hover { background: #8b5cf6; color: #fff; }
        .act-apps { background: rgba(16, 185, 129, 0.10); border-color: rgba(16, 185, 129, 0.35); color: #10b981; }
        .act-apps:hover { background: #10b981; color: #fff; }
        .act-status { background: rgba(245, 158, 11, 0.10); border-color: rgba(245, 158, 11, 0.35); color: #f59e0b; }
        .act-status:hover { background: #f59e0b; color: #fff; }
        .act-del { background: rgba(239, 68, 68, 0.10); border-color: rgba(239, 68, 68, 0.35); color: #ef4444; }
        .act-del:hover { background: #ef4444; color: #fff; }

        .status-dd .dropdown-menu {
            background: var(--mj-card);
            border: 1px solid var(--mj-border);
            border-radius: 12px;
            box-shadow: var(--mj-shadow);
            padding: 6px;
            min-width: 160px;
        }
        .status-dd .dropdown-item {
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--mj-text);
        }
        .status-dd .dropdown-item:hover { background: var(--mj-soft); color: var(--mj-primary); }

        /* ── Empty states ── */
        .mj-empty {
            text-align: center;
            padding: 70px 24px;
            background: var(--mj-card);
            border: 1.5px dashed var(--mj-border);
            border-radius: 18px;
        }
        .mj-empty i { font-size: 3.4rem; color: var(--mj-primary); opacity: .35; }
        .mj-empty h3 { font-weight: 700; color: var(--mj-text); margin-top: 16px; }
        .mj-empty p { color: var(--mj-muted); }

        /* ── Toast ── */
        .mj-toast {
            position: fixed; top: 84px; right: 24px; z-index: 9999;
            background: var(--mj-card);
            border: 1px solid var(--mj-border);
            border-left: 4px solid #10b981;
            border-radius: 14px;
            padding: 15px 20px;
            display: flex; align-items: center; gap: 12px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.18);
            opacity: 0; transform: translateX(30px);
            transition: all .35s ease;
            pointer-events: none;
        }
        .mj-toast.show { opacity: 1; transform: translateX(0); }
        .mj-toast i { color: #10b981; font-size: 1.3rem; }
        .mj-toast b { color: var(--mj-text); font-size: 0.9rem; }

        /* ── Responsive ── */
        @media (max-width: 992px) {
            .mj-stats { grid-template-columns: repeat(3, 1fr); }
            .job-card { flex-wrap: wrap; }
            .job-divider { display: none; }
            .job-stats { width: 100%; justify-content: space-between; padding-top: 14px; border-top: 1px solid var(--mj-border); }
            .job-actions { width: 100%; justify-content: flex-end; flex-wrap: wrap; }
        }
        @media (max-width: 576px) {
            .mj-stats { grid-template-columns: repeat(2, 1fr); }
            .job-actions .act-btn { flex: 1; justify-content: center; }
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/company_header.php'; ?>

    <div class="mj-wrap">
        <!-- Hero -->
        <div class="mj-hero">
            <div>
                <h1><i class="fas fa-briefcase mr-2"></i>My Job Postings</h1>
                <p>Manage, track and organize all your job openings in one place.</p>
            </div>
            <a href="post_job.php" class="mj-hero-btn">
                <i class="fas fa-plus"></i> Post New Job
            </a>
        </div>

        <!-- Stats -->
        <div class="mj-stats">
            <div class="mj-stat">
                <div class="mj-stat-ico" style="background: rgba(99,102,241,.12); color:#6366f1;"><i class="fas fa-briefcase"></i></div>
                <div><b><?php echo $stats['total']; ?></b><span>Total Jobs</span></div>
            </div>
            <div class="mj-stat">
                <div class="mj-stat-ico" style="background: rgba(16,185,129,.12); color:#10b981;"><i class="fas fa-bullseye"></i></div>
                <div><b><?php echo $stats['active']; ?></b><span>Active</span></div>
            </div>
            <div class="mj-stat">
                <div class="mj-stat-ico" style="background: rgba(245,158,11,.12); color:#f59e0b;"><i class="fas fa-pen-ruler"></i></div>
                <div><b><?php echo $stats['draft']; ?></b><span>Drafts</span></div>
            </div>
            <div class="mj-stat">
                <div class="mj-stat-ico" style="background: rgba(239,68,68,.12); color:#ef4444;"><i class="fas fa-ban"></i></div>
                <div><b><?php echo $stats['closed']; ?></b><span>Closed</span></div>
            </div>
            <div class="mj-stat">
                <div class="mj-stat-ico" style="background: rgba(59,130,246,.12); color:#3b82f6;"><i class="fas fa-users"></i></div>
                <div><b><?php echo $stats['applications']; ?></b><span>Applications</span></div>
            </div>
        </div>

        <!-- Toolbar -->
        <div class="mj-toolbar">
            <div class="mj-search">
                <i class="fas fa-magnifying-glass"></i>
                <input type="text" id="mjSearch" placeholder="Search by title, category or location..." oninput="filterJobs()">
            </div>
            <div class="mj-filters">
                <button class="mj-fbtn active" data-filter="all" onclick="setFilter(this)">All <span class="cnt"><?php echo $stats['total']; ?></span></button>
                <button class="mj-fbtn" data-filter="active" onclick="setFilter(this)">Active <span class="cnt"><?php echo $stats['active']; ?></span></button>
                <button class="mj-fbtn" data-filter="draft" onclick="setFilter(this)">Draft <span class="cnt"><?php echo $stats['draft']; ?></span></button>
                <button class="mj-fbtn" data-filter="closed" onclick="setFilter(this)">Closed <span class="cnt"><?php echo $stats['closed']; ?></span></button>
            </div>
        </div>

        <!-- Jobs -->
        <?php if (mysqli_num_rows($jobs_result) > 0): ?>
            <div class="mj-list" id="mjList">
                <?php while ($job = mysqli_fetch_assoc($jobs_result)):
                    $cat = $job['job_category'];
                    $style = isset($category_styles[$cat]) ? $category_styles[$cat] : $default_style;
                    $icon = $style['icon']; $color = $style['color'];
                    $status = $job['status'] ?: 'draft';
                ?>
                    <div class="job-card" data-status="<?php echo $status; ?>"
                         data-search="<?php echo strtolower(htmlspecialchars($job['job_title'] . ' ' . $cat . ' ' . $job['location'] . ' ' . $job['employment_type'])); ?>">
                        <div class="job-cat-ico" style="background: <?php echo $color; ?>18; color: <?php echo $color; ?>;">
                            <i class="<?php echo $icon; ?>"></i>
                        </div>

                        <div class="job-main">
                            <div class="job-title-row">
                                <h3><?php echo htmlspecialchars($job['job_title']); ?></h3>
                                <span class="job-badge <?php echo $status; ?>">
                                    <i class="fas fa-circle"></i><?php echo strtoupper($status); ?>
                                </span>
                            </div>
                            <div class="job-meta">
                                <span><i class="fas fa-tag"></i><?php echo htmlspecialchars($cat); ?></span>
                                <span><i class="fas fa-map-marker-alt"></i><?php echo htmlspecialchars($job['location']); ?></span>
                                <span><i class="fas fa-briefcase"></i><?php echo htmlspecialchars($job['employment_type']); ?></span>
                                <?php if (!empty($job['salary_range'])): ?>
                                    <span><i class="fas fa-money-bill-wave"></i><?php echo htmlspecialchars($job['salary_range']); ?></span>
                                <?php endif; ?>
                                <span><i class="far fa-calendar-alt"></i>Deadline: <?php echo date('M d, Y', strtotime($job['deadline'])); ?></span>
                            </div>
                        </div>

                        <div class="job-divider"></div>

                        <div class="job-stats">
                            <div class="job-stat"><b class="apps"><?php echo $job['application_count']; ?></b><span>Applications</span></div>
                            <div class="job-stat"><b class="qual"><?php echo $job['qualified_count']; ?></b><span>Qualified</span></div>
                            <div class="job-stat"><b class="quiz"><?php echo $job['quiz_count']; ?></b><span>Quiz Qs</span></div>
                        </div>

                        <div class="job-actions">
                            <a class="act-btn act-edit" href="edit_job.php?id=<?php echo $job['id']; ?>">
                                <i class="fas fa-pen"></i>Edit
                            </a>
                            <a class="act-btn act-quiz" href="manage_quiz.php?job_id=<?php echo $job['id']; ?>">
                                <i class="fas fa-list-check"></i>Quiz
                            </a>
                            <a class="act-btn act-apps" href="view_applicants.php?job_id=<?php echo $job['id']; ?>">
                                <i class="fas fa-users"></i>Applicants
                            </a>

                            <div class="dropdown status-dd">
                                <button class="act-btn act-status dropdown-toggle" type="button" data-toggle="dropdown">
                                    <i class="fas fa-sliders"></i>Status
                                </button>
                                <div class="dropdown-menu dropdown-menu-right">
                                    <form method="POST" action="">
                                        <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                                        <input type="hidden" name="status" value="active">
                                        <button type="submit" name="update_status" value="active" class="dropdown-item"><i class="fas fa-circle text-success mr-2" style="font-size:.55rem;"></i>Active</button>
                                    </form>
                                    <form method="POST" action="">
                                        <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                                        <input type="hidden" name="status" value="draft">
                                        <button type="submit" name="update_status" value="draft" class="dropdown-item"><i class="fas fa-circle text-warning mr-2" style="font-size:.55rem;"></i>Draft</button>
                                    </form>
                                    <form method="POST" action="">
                                        <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                                        <input type="hidden" name="status" value="closed">
                                        <button type="submit" name="update_status" value="closed" class="dropdown-item"><i class="fas fa-circle text-danger mr-2" style="font-size:.55rem;"></i>Closed</button>
                                    </form>
                                </div>
                            </div>

                            <a class="act-btn act-del" href="?delete_id=<?php echo $job['id']; ?>"
                               onclick="return confirm('Are you sure you want to delete this job? This will also delete all applications and quiz questions.');">
                                <i class="fas fa-trash"></i>Delete
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
            <div class="mj-empty" id="mjNoMatch" style="display:none;">
                <i class="fas fa-filter"></i>
                <h3>No Jobs Found</h3>
                <p>Try a different search term or filter.</p>
            </div>
        <?php else: ?>
            <div class="mj-empty">
                <i class="fas fa-briefcase"></i>
                <h3>No Jobs Posted Yet</h3>
                <p>Create your first job posting and start receiving applications.</p>
                <a href="post_job.php" class="btn btn-primary rounded-pill px-4 py-2 mt-3"><i class="fas fa-plus mr-2"></i>Post Your First Job</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Toast -->
    <div class="mj-toast" id="mjToast"><i class="fas fa-circle-check"></i><b id="mjToastMsg"></b></div>

    <script>
        function showToast(msg) {
            const t = document.getElementById('mjToast');
            document.getElementById('mjToastMsg').textContent = msg;
            t.classList.add('show');
            clearTimeout(window._mjToastT);
            window._mjToastT = setTimeout(() => t.classList.remove('show'), 3200);
        }
        <?php if (isset($_GET['updated'])): ?>showToast('Job status updated successfully!');<?php endif; ?>
        <?php if (isset($_GET['deleted'])): ?>showToast('Job deleted successfully!');<?php endif; ?>

        // Filter + search
        let currentFilter = 'all';
        function setFilter(btn) {
            document.querySelectorAll('.mj-fbtn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            currentFilter = btn.dataset.filter;
            filterJobs();
        }
        function filterJobs() {
            const q = (document.getElementById('mjSearch').value || '').toLowerCase();
            const cards = document.querySelectorAll('.job-card');
            let visible = 0;
            cards.forEach(card => {
                const okFilter = currentFilter === 'all' || card.dataset.status === currentFilter;
                const okSearch = !q || card.dataset.search.includes(q);
                const show = okFilter && okSearch;
                card.style.display = show ? '' : 'none';
                if (show) visible++;
            });
            const noMatch = document.getElementById('mjNoMatch');
            if (noMatch) noMatch.style.display = visible === 0 ? '' : 'none';
        }
    </script>
</body>
</html>
