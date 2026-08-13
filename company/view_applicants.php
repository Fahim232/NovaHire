<?php
    session_start();
    include '../admin/dbcon.php';

    // Check if company is logged in
    if (!isset($_SESSION['company_id'])) {
        header('Location: ../company_login.php');
        exit;
    }

    $company_id = $_SESSION['company_id'];
    $company_name = $_SESSION['company_name'];

    // Filter by job_id if provided
    $job_filter = isset($_GET['job_id']) ? intval($_GET['job_id']) : 0;
    $status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

    // Build query
    $where_clause = "cj.company_id = $company_id";

    if ($job_filter > 0) {
        $where_clause .= " AND ja.job_id = $job_filter";
    }

    if ($status_filter != 'all') {
        $where_clause .= " AND ja.quiz_status = '$status_filter'";
    }

    // Fetch applications
    $applications_query = "SELECT ja.*, cj.job_title, cj.job_category, ui.username, ui.email, ui.phone, ui.user_degree, ui.user_skills, ui.profile 
                          FROM job_applications ja
                          JOIN company_jobs cj ON ja.job_id = cj.id
                          JOIN user_info ui ON ja.user_id = ui.id
                          WHERE $where_clause
                          ORDER BY ja.applied_date DESC";
    $applications_result = mysqli_query($con, $applications_query);

    // Fetch jobs for filter dropdown
    $jobs_query = "SELECT id, job_title FROM company_jobs WHERE company_id = $company_id ORDER BY job_title";
    $jobs_result = mysqli_query($con, $jobs_query);

    // Count statistics
    $stats_query = "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN ja.quiz_status = 'passed' THEN 1 ELSE 0 END) as passed,
        SUM(CASE WHEN ja.quiz_status = 'failed' THEN 1 ELSE 0 END) as failed,
        SUM(CASE WHEN ja.quiz_status = 'not_taken' THEN 1 ELSE 0 END) as not_taken
        FROM job_applications ja
        JOIN company_jobs cj ON ja.job_id = cj.id
        WHERE $where_clause";
    $stats_result = mysqli_query($con, $stats_query);
    $stats = mysqli_fetch_assoc($stats_result);

    $avatar_gradients = [
        ['#6366f1', '#8b5cf6'],
        ['#0ea5e9', '#06b6d4'],
        ['#10b981', '#34d399'],
        ['#f59e0b', '#f97316'],
        ['#ec4899', '#f43f5e'],
        ['#14b8a6', '#0d9488'],
    ];

    function applicant_avatar($username, $gradients) {
        $initial = strtoupper(substr(trim($username), 0, 1) ?: '?');
        $g = $gradients[abs(crc32($username)) % count($gradients)];
        return '<div class="va-avatar" style="background: linear-gradient(135deg, ' . $g[0] . ', ' . $g[1] . ');">' . $initial . '</div>';
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Job Applicants | Company Dashboard</title>
    <?php include '../includes/links.php'; ?>
    <style>
        :root {
            --va-bg: #f4f6fb;
            --va-card: #ffffff;
            --va-border: #e5e9f2;
            --va-text: #1e293b;
            --va-muted: #64748b;
            --va-primary: #4f46e5;
            --va-primary-2: #7c3aed;
            --va-soft: #eef2ff;
            --va-input: #f8fafc;
            --va-shadow: 0 10px 30px rgba(15, 23, 42, 0.07);
        }
        [data-theme="dark"] {
            --va-bg: #0f172a;
            --va-card: #111827;
            --va-border: #28334a;
            --va-text: #e8edff;
            --va-muted: #94a3b8;
            --va-primary: #8b5cf6;
            --va-primary-2: #a78bfa;
            --va-soft: #1e293b;
            --va-input: #0d1526;
            --va-shadow: 0 10px 30px rgba(0, 0, 0, 0.45);
        }

        body {
            background:
                radial-gradient(circle at 8% 12%, rgba(99, 102, 241, 0.10), transparent 28%),
                radial-gradient(circle at 92% 8%, rgba(217, 70, 239, 0.08), transparent 26%),
                var(--va-bg);
            color: var(--va-text);
            min-height: 100vh;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }

        .va-wrap { max-width: 1200px; margin: 0 auto; padding: 34px 24px 60px; }

        /* ── Hero ── */
        .va-hero {
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 55%, #a855f7 100%);
            border-radius: 22px;
            padding: 30px 34px;
            color: #fff;
            box-shadow: 0 20px 40px rgba(79, 70, 229, 0.28);
        }
        .va-hero::before {
            content: '';
            position: absolute;
            right: -80px; top: -80px;
            width: 260px; height: 260px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.10);
        }
        .va-hero::after {
            content: '';
            position: absolute;
            right: 60px; bottom: -110px;
            width: 220px; height: 220px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.08);
        }
        .va-hero h1 { font-weight: 800; font-size: 1.75rem; color: #fff; margin: 0 0 6px; }
        .va-hero p { color: rgba(255, 255, 255, 0.85); margin: 0; font-size: 0.95rem; }

        /* ── Stats ── */
        .va-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-top: 22px; }
        .va-stat {
            background: var(--va-card);
            border: 1px solid var(--va-border);
            border-radius: 16px;
            padding: 18px 20px;
            display: flex;
            align-items: center;
            gap: 14px;
            box-shadow: var(--va-shadow);
            transition: transform .2s ease, box-shadow .2s ease;
            cursor: pointer;
            text-decoration: none;
        }
        .va-stat:hover { transform: translateY(-4px); box-shadow: 0 18px 38px rgba(79, 70, 229, 0.14); text-decoration: none; }
        .va-stat-ico {
            width: 46px; height: 46px;
            border-radius: 13px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.15rem;
            flex-shrink: 0;
        }
        .va-stat b { display: block; font-size: 1.45rem; line-height: 1.1; color: var(--va-text); }
        .va-stat span { font-size: 0.76rem; color: var(--va-muted); font-weight: 600; text-transform: uppercase; letter-spacing: .4px; }
        .va-stat.on {
            border-color: var(--va-primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15), var(--va-shadow);
        }

        /* ── Toolbar ── */
        .va-toolbar {
            display: flex; justify-content: space-between; align-items: center;
            flex-wrap: wrap; gap: 14px;
            margin: 30px 0 20px;
        }
        .va-filters { display: flex; gap: 8px; flex-wrap: wrap; }
        .va-fbtn {
            border: 1.5px solid var(--va-border);
            background: var(--va-card);
            color: var(--va-muted);
            font-weight: 600; font-size: 0.83rem;
            padding: 9px 16px;
            border-radius: 12px;
            cursor: pointer;
            transition: all .2s ease;
            text-decoration: none;
        }
        .va-fbtn:hover { border-color: var(--va-primary); color: var(--va-primary); text-decoration: none; }
        .va-fbtn.active {
            background: linear-gradient(135deg, var(--va-primary), var(--va-primary-2));
            color: #fff; border-color: transparent;
            box-shadow: 0 8px 20px rgba(79, 70, 229, 0.3);
        }
        .va-fbtn .cnt {
            display: inline-block; margin-left: 6px;
            background: rgba(0, 0, 0, 0.08);
            border-radius: 20px; padding: 1px 8px; font-size: 0.72rem;
        }
        .va-fbtn.active .cnt { background: rgba(255, 255, 255, 0.22); }

        .va-search { position: relative; flex: 1; min-width: 240px; max-width: 380px; }
        .va-search i { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--va-muted); font-size: 0.9rem; }
        .va-search input {
            width: 100%;
            background: var(--va-card);
            border: 1.5px solid var(--va-border);
            color: var(--va-text);
            border-radius: 13px;
            padding: 12px 16px 12px 42px;
            font-size: 0.92rem;
            outline: none;
            transition: border-color .2s ease, box-shadow .2s ease;
        }
        .va-search input:focus { border-color: var(--va-primary); box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.15); }
        .va-search input::placeholder { color: var(--va-muted); }

        .va-jobsel {
            background: var(--va-card);
            border: 1.5px solid var(--va-border);
            color: var(--va-text);
            border-radius: 13px;
            padding: 12px 16px;
            font-size: 0.9rem;
            outline: none;
            min-width: 220px;
        }
        .va-jobsel:focus { border-color: var(--va-primary); }

        /* ── Applicant cards ── */
        .va-list { display: flex; flex-direction: column; gap: 16px; }
        .va-card {
            background: var(--va-card);
            border: 1px solid var(--va-border);
            border-radius: 18px;
            padding: 22px 24px;
            display: flex;
            gap: 20px;
            box-shadow: var(--va-shadow);
            transition: transform .22s ease, box-shadow .22s ease, border-color .22s ease;
            animation: vaIn .4s ease both;
        }
        .va-card:hover { transform: translateY(-3px); border-color: var(--va-primary); box-shadow: 0 20px 40px rgba(79, 70, 229, 0.16); }
        @keyframes vaIn {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .va-avatar {
            width: 56px; height: 56px;
            border-radius: 18px;
            color: #fff;
            font-weight: 800;
            font-size: 1.3rem;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 8px 18px rgba(0, 0, 0, 0.15);
        }

        .va-main { flex: 1; min-width: 0; }
        .va-name-row { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .va-name-row h3 { font-size: 1.08rem; font-weight: 700; margin: 0; color: var(--va-text); }
        .va-badge {
            font-size: 0.7rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: .5px;
            padding: 4px 11px; border-radius: 20px;
            display: inline-flex; align-items: center; gap: 6px;
        }
        .va-badge i { font-size: 0.5rem; }
        .va-badge.passed { background: rgba(16, 185, 129, 0.14); color: #10b981; }
        .va-badge.failed { background: rgba(239, 68, 68, 0.14); color: #ef4444; }
        .va-badge.not_taken { background: rgba(245, 158, 11, 0.14); color: #f59e0b; }
        .va-badge.shortlisted { background: rgba(59, 130, 246, 0.14); color: #3b82f6; }
        .va-badge.pending { background: rgba(148, 163, 184, 0.18); color: var(--va-muted); }

        .va-meta { display: flex; flex-wrap: wrap; gap: 14px; margin-top: 8px; }
        .va-meta span { font-size: 0.82rem; color: var(--va-muted); }
        .va-meta i { margin-right: 5px; color: var(--va-primary); opacity: .8; }
        .va-meta b { color: var(--va-text); font-weight: 600; }

        .va-cover {
            margin-top: 12px;
            padding: 12px 16px;
            background: var(--va-soft);
            border: 1px solid var(--va-border);
            border-radius: 12px;
            font-size: 0.84rem;
            color: var(--va-text);
            line-height: 1.55;
            display: flex; gap: 10px; align-items: flex-start;
        }
        .va-cover i { color: var(--va-primary); margin-top: 3px; }

        .va-education { margin-top: 12px; font-size: 0.84rem; color: var(--va-muted); }
        .va-education i { margin-right: 7px; color: var(--va-primary); }

        .va-skills { display: flex; flex-wrap: wrap; gap: 7px; margin-top: 12px; }
        .va-skill {
            background: var(--va-soft);
            border: 1px solid var(--va-border);
            color: var(--va-text);
            font-size: 0.76rem; font-weight: 600;
            padding: 5px 12px; border-radius: 20px;
        }

        .va-actions {
            display: flex; flex-direction: column; gap: 9px;
            flex-shrink: 0;
            justify-content: center;
            min-width: 150px;
        }
        .va-act {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            padding: 10px 16px; border-radius: 11px;
            font-size: 0.82rem; font-weight: 600;
            border: 1.5px solid var(--va-border);
            background: var(--va-card);
            color: var(--va-text);
            text-decoration: none;
            transition: all .18s ease;
            white-space: nowrap;
        }
        .va-act:hover { transform: translateY(-2px); text-decoration: none; }
        .va-act-detail { background: rgba(79, 70, 229, 0.10); border-color: rgba(79, 70, 229, 0.35); color: var(--va-primary); }
        .va-act-detail:hover { background: var(--va-primary); color: #fff; }
        .va-act-cv { background: rgba(16, 185, 129, 0.10); border-color: rgba(16, 185, 129, 0.35); color: #10b981; }
        .va-act-cv:hover { background: #10b981; color: #fff; }
        .va-act-contact { background: rgba(59, 130, 246, 0.10); border-color: rgba(59, 130, 246, 0.35); color: #3b82f6; }
        .va-act-contact:hover { background: #3b82f6; color: #fff; }
        .va-act.disabled { opacity: .5; cursor: not-allowed; }
        .va-act.disabled:hover { transform: none; background: var(--va-card); color: var(--va-muted); }

        /* Score ring */
        .va-score {
            text-align: center; flex-shrink: 0;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            padding-left: 20px;
            border-left: 1px solid var(--va-border);
            min-width: 86px;
        }
        .va-score-ring {
            width: 58px; height: 58px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-weight: 800; font-size: 0.95rem;
            position: relative;
        }
        .va-score-ring small { font-size: 0.62rem; font-weight: 600; opacity: .8; }
        .va-score-cap { font-size: 0.66rem; color: var(--va-muted); font-weight: 600; margin-top: 6px; text-transform: uppercase; letter-spacing: .4px; }

        /* Empty */
        .va-empty {
            text-align: center;
            padding: 70px 24px;
            background: var(--va-card);
            border: 1.5px dashed var(--va-border);
            border-radius: 18px;
        }
        .va-empty i { font-size: 3.4rem; color: var(--va-primary); opacity: .35; }
        .va-empty h3 { font-weight: 700; color: var(--va-text); margin-top: 16px; }
        .va-empty p { color: var(--va-muted); }

        @media (max-width: 992px) {
            .va-stats { grid-template-columns: repeat(2, 1fr); }
            .va-card { flex-wrap: wrap; }
            .va-score { border-left: none; padding-left: 0; border-top: 1px solid var(--va-border); padding-top: 14px; width: 100%; flex-direction: row; gap: 12px; }
            .va-actions { width: 100%; flex-direction: row; flex-wrap: wrap; }
            .va-actions .va-act { flex: 1; }
        }
        @media (max-width: 576px) {
            .va-stats { grid-template-columns: repeat(2, 1fr); }
            .va-actions { flex-direction: column; }
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/company_header.php'; ?>

    <div class="va-wrap">
        <!-- Hero -->
        <div class="va-hero">
            <h1><i class="fas fa-users mr-2"></i>Job Applicants</h1>
            <p>Review candidates, scores, and application details for your job openings.</p>
        </div>

        <!-- Stats (clickable filters) -->
        <div class="va-stats">
            <a class="va-stat <?php echo $status_filter == 'all' ? 'on' : ''; ?>" href="?status=all<?php echo $job_filter ? '&job_id=' . $job_filter : ''; ?>">
                <div class="va-stat-ico" style="background: rgba(99,102,241,.12); color:#6366f1;"><i class="fas fa-file-signature"></i></div>
                <div><b><?php echo $stats['total']; ?></b><span>Applications</span></div>
            </a>
            <a class="va-stat <?php echo $status_filter == 'passed' ? 'on' : ''; ?>" href="?status=passed<?php echo $job_filter ? '&job_id=' . $job_filter : ''; ?>">
                <div class="va-stat-ico" style="background: rgba(16,185,129,.12); color:#10b981;"><i class="fas fa-circle-check"></i></div>
                <div><b><?php echo $stats['passed']; ?></b><span>Passed Quiz</span></div>
            </a>
            <a class="va-stat <?php echo $status_filter == 'failed' ? 'on' : ''; ?>" href="?status=failed<?php echo $job_filter ? '&job_id=' . $job_filter : ''; ?>">
                <div class="va-stat-ico" style="background: rgba(239,68,68,.12); color:#ef4444;"><i class="fas fa-circle-xmark"></i></div>
                <div><b><?php echo $stats['failed']; ?></b><span>Failed Quiz</span></div>
            </a>
            <a class="va-stat <?php echo $status_filter == 'not_taken' ? 'on' : ''; ?>" href="?status=not_taken<?php echo $job_filter ? '&job_id=' . $job_filter : ''; ?>">
                <div class="va-stat-ico" style="background: rgba(245,158,11,.12); color:#f59e0b;"><i class="fas fa-hourglass-half"></i></div>
                <div><b><?php echo $stats['not_taken']; ?></b><span>Not Taken</span></div>
            </a>
        </div>

        <!-- Toolbar -->
        <div class="va-toolbar">
            <div class="va-search">
                <i class="fas fa-magnifying-glass"></i>
                <input type="text" id="vaSearch" placeholder="Search applicants..." oninput="filterApplicants()">
            </div>
            <div class="va-filters">
                <a class="va-fbtn <?php echo $status_filter == 'all' ? 'active' : ''; ?>" href="?status=all<?php echo $job_filter ? '&job_id=' . $job_filter : ''; ?>">All</a>
                <a class="va-fbtn <?php echo $status_filter == 'passed' ? 'active' : ''; ?>" href="?status=passed<?php echo $job_filter ? '&job_id=' . $job_filter : ''; ?>">Passed</a>
                <a class="va-fbtn <?php echo $status_filter == 'failed' ? 'active' : ''; ?>" href="?status=failed<?php echo $job_filter ? '&job_id=' . $job_filter : ''; ?>">Failed</a>
                <a class="va-fbtn <?php echo $status_filter == 'not_taken' ? 'active' : ''; ?>" href="?status=not_taken<?php echo $job_filter ? '&job_id=' . $job_filter : ''; ?>">Not Taken</a>
            </div>
            <select class="va-jobsel" onchange="location.href='?status=<?php echo $status_filter; ?>&job_id=' + this.value;">
                <option value="0">All Jobs</option>
                <?php
                mysqli_data_seek($jobs_result, 0);
                while ($job = mysqli_fetch_assoc($jobs_result)): ?>
                    <option value="<?php echo $job['id']; ?>" <?php echo ($job_filter == $job['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($job['job_title']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <!-- Applicant list -->
        <?php if (mysqli_num_rows($applications_result) > 0): ?>
            <div class="va-list" id="vaList">
                <?php while ($app = mysqli_fetch_assoc($applications_result)):
                    $status = $app['quiz_status'] ?: 'not_taken';
                    $app_status = $app['application_status'] ?: 'pending';
                    $score = intval($app['quiz_score']);
                    $score_color = $score >= 60 ? '#10b981' : ($score >= 30 ? '#f59e0b' : '#ef4444');
                    $data_search = strtolower(htmlspecialchars($app['username'] . ' ' . $app['email'] . ' ' . $app['job_title'] . ' ' . $app['user_degree']));
                ?>
                    <div class="va-card" data-status="<?php echo $status; ?>" data-appstatus="<?php echo $app_status; ?>" data-search="<?php echo $data_search; ?>">
                        <?php echo applicant_avatar($app['username'], $avatar_gradients); ?>

                        <div class="va-main">
                            <div class="va-name-row">
                                <h3><?php echo htmlspecialchars(trim($app['username'])); ?></h3>
                                <span class="va-badge <?php echo $status; ?>">
                                    <i class="fas fa-circle"></i><?php echo $status == 'passed' ? 'Quiz Passed' : ($status == 'failed' ? 'Quiz Failed' : 'Not Taken'); ?>
                                </span>
                                <?php if ($app_status != 'pending'): ?>
                                    <span class="va-badge <?php echo $app_status; ?>">
                                        <i class="fas fa-circle"></i><?php echo ucfirst($app_status); ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="va-meta">
                                <span><i class="fas fa-briefcase"></i>Applied for: <b><?php echo htmlspecialchars($app['job_title']); ?></b></span>
                                <span><i class="far fa-calendar-alt"></i>Applied: <?php echo date('M d, Y', strtotime($app['applied_date'])); ?></span>
                                <span><i class="fas fa-envelope"></i><?php echo htmlspecialchars($app['email']); ?></span>
                                <?php if (!empty($app['phone'])): ?>
                                    <span><i class="fas fa-phone"></i><?php echo htmlspecialchars($app['phone']); ?></span>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($app['cover_letter'])): ?>
                                <div class="va-cover">
                                    <i class="fas fa-quote-left"></i>
                                    <span><?php echo nl2br(htmlspecialchars(mb_substr($app['cover_letter'], 0, 220))); ?><?php echo mb_strlen($app['cover_letter']) > 220 ? '...' : ''; ?></span>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($app['user_degree'])): ?>
                                <div class="va-education"><i class="fas fa-graduation-cap"></i><?php echo htmlspecialchars($app['user_degree']); ?></div>
                            <?php endif; ?>

                            <?php
                            $skills = array_filter(array_map('trim', explode(',', $app['user_skills'])));
                            if (count($skills) > 0): ?>
                                <div class="va-skills">
                                    <?php foreach (array_slice($skills, 0, 8) as $skill): ?>
                                        <span class="va-skill"><?php echo htmlspecialchars($skill); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($app['quiz_score'] !== null): ?>
                            <div class="va-score">
                                <div class="va-score-ring" style="background: <?php echo $score_color; ?>1a; color: <?php echo $score_color; ?>; border: 3px solid <?php echo $score_color; ?>55;">
                                    <?php echo $score; ?><small>%</small>
                                </div>
                                <div class="va-score-cap">Score</div>
                            </div>
                        <?php endif; ?>

                        <div class="va-actions">
                            <a class="va-act va-act-detail" href="view_applicant_detail.php?id=<?php echo $app['id']; ?>">
                                <i class="fas fa-eye"></i>View Details
                            </a>
                            <?php if (!empty($app['profile'])): ?>
                                <a class="va-act va-act-cv" href="../files/<?php echo htmlspecialchars($app['profile']); ?>" target="_blank">
                                    <i class="fas fa-file-pdf"></i>View CV
                                </a>
                            <?php else: ?>
                                <span class="va-act va-act-cv disabled"><i class="fas fa-file-pdf"></i>No CV</span>
                            <?php endif; ?>
                            <a class="va-act va-act-contact" href="mailto:<?php echo htmlspecialchars($app['email']); ?>">
                                <i class="fas fa-envelope"></i>Contact
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
            <div class="va-empty" id="vaNoMatch" style="display:none;">
                <i class="fas fa-user-magnifying-glass"></i>
                <h3>No Applicants Found</h3>
                <p>Try a different search term.</p>
            </div>
        <?php else: ?>
            <div class="va-empty">
                <i class="fas fa-inbox"></i>
                <h3>No Applicants Yet</h3>
                <p>When candidates apply for your jobs, they'll appear here.</p>
                <a href="my_jobs.php" class="btn btn-primary rounded-pill px-4 py-2 mt-3"><i class="fas fa-briefcase mr-2"></i>View Your Jobs</a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function filterApplicants() {
            const q = (document.getElementById('vaSearch').value || '').toLowerCase();
            const cards = document.querySelectorAll('.va-card');
            let visible = 0;
            cards.forEach(card => {
                const ok = !q || card.dataset.search.includes(q);
                card.style.display = ok ? '' : 'none';
                if (ok) visible++;
            });
            const noMatch = document.getElementById('vaNoMatch');
            if (noMatch) noMatch.style.display = visible === 0 ? '' : 'none';
        }
    </script>
</body>
</html>
