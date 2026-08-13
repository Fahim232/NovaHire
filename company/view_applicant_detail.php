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

    // Get application ID
    if (!isset($_GET['id'])) {
        header('Location: view_applicants.php');
        exit;
    }

    $app_id = intval($_GET['id']);

    // Fetch application details
    $app_query = "SELECT ja.*, cj.job_title, cj.job_category, cj.job_description, 
                  ui.username, ui.email, ui.phone, ui.user_degree, ui.user_skills, ui.profile,
                  jqa.total_questions, jqa.correct_answers, jqa.score_percentage, jqa.time_taken, jqa.attempt_date
                  FROM job_applications ja
                  JOIN company_jobs cj ON ja.job_id = cj.id
                  JOIN user_info ui ON ja.user_id = ui.id
                  LEFT JOIN job_quiz_attempts jqa ON ja.id = jqa.application_id
                  WHERE ja.id = $app_id AND ja.company_id = $company_id";
    $app_result = mysqli_query($con, $app_query);

    if (mysqli_num_rows($app_result) == 0) {
        header('Location: view_applicants.php');
        exit;
    }

    $app = mysqli_fetch_assoc($app_result);

    // Update application status
    if (isset($_POST['update_status'])) {
        $new_status = mysqli_real_escape_string($con, $_POST['application_status']);
        $update_query = "UPDATE job_applications SET application_status = '$new_status' WHERE id = $app_id";
        if (mysqli_query($con, $update_query)) {
            include '../includes/functions.php';
            notify_application_status($con, $app['user_id'], $company_id, $app['job_title'], $company_name, $new_status);

            send_message($con, 'company', $company_id, 'user', $app['user_id'],
                "Application Update: " . ucfirst($new_status),
                "Your application for {$app['job_title']} has been updated to: " . ucfirst($new_status),
                $app['job_id']);

            $app['application_status'] = $new_status;
            $status_updated = true;
        } else {
            $status_error = true;
        }
    }

    $avatar_gradients = [
        ['#6366f1', '#8b5cf6'],
        ['#0ea5e9', '#06b6d4'],
        ['#10b981', '#34d399'],
        ['#f59e0b', '#f97316'],
        ['#ec4899', '#f43f5e'],
        ['#14b8a6', '#0d9488'],
    ];

    $initial = strtoupper(substr(trim($app['username']), 0, 1) ?: '?');
    $g = $avatar_gradients[abs(crc32($app['username'])) % count($avatar_gradients)];

    $quiz_score = isset($app['score_percentage']) ? floatval($app['score_percentage']) : intval($app['quiz_score']);
    $score_pct = round($quiz_score);
    $score_color = $score_pct >= 60 ? '#10b981' : ($score_pct >= 30 ? '#f59e0b' : '#ef4444');

    $status_colors = [
        'pending'    => ['#94a3b8', 'Pending Review'],
        'reviewed'   => ['#06b6d4', 'Reviewed'],
        'shortlisted'=> ['#3b82f6', 'Shortlisted'],
        'rejected'   => ['#ef4444', 'Rejected'],
    ];
    $app_status = $app['application_status'] ?: 'pending';
    $app_color = isset($status_colors[$app_status]) ? $status_colors[$app_status][0] : '#94a3b8';
    $app_label = isset($status_colors[$app_status]) ? $status_colors[$app_status][1] : ucfirst($app_status);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Applicant Details | Company Dashboard</title>
    <?php include '../includes/links.php'; ?>
    <style>
        :root {
            --ad-bg: #f4f6fb;
            --ad-card: #ffffff;
            --ad-border: #e5e9f2;
            --ad-text: #1e293b;
            --ad-muted: #64748b;
            --ad-primary: #4f46e5;
            --ad-primary-2: #7c3aed;
            --ad-soft: #eef2ff;
            --ad-input: #f8fafc;
            --ad-shadow: 0 10px 30px rgba(15, 23, 42, 0.07);
        }
        [data-theme="dark"] {
            --ad-bg: #0f172a;
            --ad-card: #111827;
            --ad-border: #28334a;
            --ad-text: #e8edff;
            --ad-muted: #94a3b8;
            --ad-primary: #8b5cf6;
            --ad-primary-2: #a78bfa;
            --ad-soft: #1e293b;
            --ad-input: #0d1526;
            --ad-shadow: 0 10px 30px rgba(0, 0, 0, 0.45);
        }

        body {
            background:
                radial-gradient(circle at 8% 12%, rgba(99, 102, 241, 0.10), transparent 28%),
                radial-gradient(circle at 92% 8%, rgba(217, 70, 239, 0.08), transparent 26%),
                var(--ad-bg);
            color: var(--ad-text);
            min-height: 100vh;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }

        .ad-wrap { max-width: 1120px; margin: 0 auto; padding: 34px 24px 60px; }

        /* ── Hero ── */
        .ad-hero {
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 55%, #a855f7 100%);
            border-radius: 22px;
            padding: 30px 34px;
            color: #fff;
            box-shadow: 0 20px 40px rgba(79, 70, 229, 0.28);
        }
        .ad-hero::before {
            content: '';
            position: absolute;
            right: -80px; top: -80px;
            width: 260px; height: 260px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.10);
        }
        .ad-hero::after {
            content: '';
            position: absolute;
            right: 60px; bottom: -110px;
            width: 220px; height: 220px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.08);
        }
        .ad-hero-in { position: relative; z-index: 1; display: flex; align-items: center; gap: 22px; flex-wrap: wrap; }
        .ad-avatar {
            width: 78px; height: 78px;
            border-radius: 24px;
            color: #fff;
            font-weight: 800;
            font-size: 1.9rem;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 14px 30px rgba(0, 0, 0, 0.25);
            border: 3px solid rgba(255, 255, 255, 0.35);
        }
        .ad-hero-info h1 { font-weight: 800; font-size: 1.7rem; color: #fff; margin: 0 0 4px; }
        .ad-hero-info p { color: rgba(255, 255, 255, 0.85); margin: 0; font-size: 0.92rem; }
        .ad-hero-info p i { margin-right: 6px; }
        .ad-hero-badges { display: flex; gap: 9px; margin-top: 12px; flex-wrap: wrap; }
        .ad-hbadge {
            display: inline-flex; align-items: center; gap: 7px;
            font-size: 0.76rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: .4px;
            padding: 6px 14px; border-radius: 30px;
            background: rgba(255, 255, 255, 0.18);
            border: 1px solid rgba(255, 255, 255, 0.35);
            color: #fff;
        }
        .ad-back {
            margin-left: auto;
            background: rgba(255, 255, 255, 0.16);
            border: 1px solid rgba(255, 255, 255, 0.35);
            color: #fff;
            padding: 11px 20px;
            border-radius: 13px;
            font-size: 0.85rem; font-weight: 600;
            transition: all .2s ease;
            display: inline-flex; align-items: center; gap: 8px;
        }
        .ad-back:hover { background: #fff; color: #4f46e5; text-decoration: none; }

        /* ── Section cards ── */
        .ad-section {
            background: var(--ad-card);
            border: 1px solid var(--ad-border);
            border-radius: 18px;
            padding: 24px 26px;
            margin-top: 20px;
            box-shadow: var(--ad-shadow);
            animation: adIn .4s ease both;
        }
        @keyframes adIn {
            from { opacity: 0; transform: translateY(14px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .ad-section-head {
            display: flex; align-items: center; gap: 13px;
            margin-bottom: 18px;
            padding-bottom: 14px;
            border-bottom: 1px solid var(--ad-border);
        }
        .ad-section-head .ico {
            width: 42px; height: 42px;
            border-radius: 13px;
            background: linear-gradient(135deg, var(--ad-primary), var(--ad-primary-2));
            color: #fff;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.05rem;
            box-shadow: 0 8px 18px rgba(79, 70, 229, 0.3);
        }
        .ad-section-head h3 { font-size: 1.05rem; font-weight: 700; margin: 0; color: var(--ad-text); }
        .ad-section-head p { font-size: 0.8rem; color: var(--ad-muted); margin: 2px 0 0; }

        /* Info grid */
        .ad-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 14px; }
        .ad-item {
            background: var(--ad-input);
            border: 1px solid var(--ad-border);
            border-radius: 13px;
            padding: 15px 17px;
            transition: border-color .2s ease;
        }
        .ad-item:hover { border-color: var(--ad-primary); }
        .ad-item small {
            display: block;
            font-size: 0.68rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: .6px;
            color: var(--ad-muted);
            margin-bottom: 5px;
        }
        .ad-item span { font-size: 0.92rem; color: var(--ad-text); font-weight: 600; word-break: break-word; }
        .ad-item a { color: var(--ad-primary); text-decoration: none; }
        .ad-item a:hover { text-decoration: underline; }

        /* Skills */
        .ad-skills { display: flex; flex-wrap: wrap; gap: 8px; }
        .ad-skill {
            background: var(--ad-soft);
            border: 1px solid var(--ad-border);
            color: var(--ad-text);
            font-size: 0.8rem; font-weight: 600;
            padding: 7px 15px; border-radius: 22px;
            display: inline-flex; align-items: center; gap: 7px;
        }
        .ad-skill i { color: var(--ad-primary); font-size: 0.75rem; }

        /* Cover letter */
        .ad-cover {
            background: var(--ad-input);
            border: 1px solid var(--ad-border);
            border-left: 4px solid var(--ad-primary);
            border-radius: 13px;
            padding: 18px 20px;
            font-size: 0.9rem;
            line-height: 1.7;
            color: var(--ad-text);
            white-space: pre-line;
        }

        /* Job preview */
        .ad-job {
            background: var(--ad-input);
            border: 1px solid var(--ad-border);
            border-radius: 13px;
            padding: 18px 20px;
        }
        .ad-job h4 { font-size: 1rem; font-weight: 700; color: var(--ad-text); margin: 0 0 8px; }
        .ad-job p { font-size: 0.85rem; color: var(--ad-muted); line-height: 1.65; margin: 0; }
        .ad-job .cat {
            display: inline-flex; align-items: center; gap: 6px;
            font-size: 0.72rem; font-weight: 700;
            color: var(--ad-primary);
            background: var(--ad-soft);
            border: 1px solid var(--ad-border);
            padding: 4px 12px; border-radius: 20px;
            margin-bottom: 8px;
        }

        /* Score ring */
        .ad-score {
            display: flex; align-items: center; gap: 22px; flex-wrap: wrap;
        }
        .ad-ring {
            width: 128px; height: 128px;
            border-radius: 50%;
            position: relative;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .ad-ring .inner {
            width: 96px; height: 96px;
            border-radius: 50%;
            background: var(--ad-card);
            display: flex; flex-direction: column; align-items: center; justify-content: center;
        }
        .ad-ring b { font-size: 1.5rem; font-weight: 800; line-height: 1; }
        .ad-ring small { font-size: 0.62rem; font-weight: 600; color: var(--ad-muted); margin-top: 3px; }
        .ad-score-stats { display: flex; gap: 14px; flex-wrap: wrap; flex: 1; }
        .ad-score-stat {
            flex: 1; min-width: 120px;
            background: var(--ad-input);
            border: 1px solid var(--ad-border);
            border-radius: 13px;
            padding: 14px 16px;
            text-align: center;
        }
        .ad-score-stat b { display: block; font-size: 1.15rem; font-weight: 800; color: var(--ad-text); }
        .ad-score-stat span { font-size: 0.7rem; color: var(--ad-muted); font-weight: 600; text-transform: uppercase; letter-spacing: .4px; }
        .ad-not-taken {
            text-align: center; padding: 30px 20px;
            color: var(--ad-muted);
        }
        .ad-not-taken i { font-size: 3rem; opacity: .35; }
        .ad-not-taken h4 { font-weight: 700; color: var(--ad-text); margin-top: 12px; }

        /* Status selector */
        .ad-status-pills { display: flex; gap: 10px; flex-wrap: wrap; }
        .ad-pill {
            flex: 1; min-width: 130px;
            border: 1.5px solid var(--ad-border);
            background: var(--ad-input);
            color: var(--ad-muted);
            border-radius: 13px;
            padding: 13px 15px;
            cursor: pointer;
            text-align: left;
            transition: all .2s ease;
        }
        .ad-pill b { display: block; font-size: 0.86rem; font-weight: 700; margin-bottom: 2px; }
        .ad-pill span { font-size: 0.72rem; }
        .ad-pill input { display: none; }
        .ad-pill.sel {
            border-color: var(--ad-primary);
            background: var(--ad-soft);
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.12);
        }
        .ad-pill.sel b { color: var(--ad-primary); }

        /* Actions */
        .ad-actions { display: flex; flex-wrap: wrap; gap: 11px; }
        .ad-act {
            display: inline-flex; align-items: center; justify-content: center; gap: 9px;
            padding: 12px 22px; border-radius: 13px;
            font-size: 0.85rem; font-weight: 700;
            border: 1.5px solid var(--ad-border);
            background: var(--ad-card);
            color: var(--ad-text);
            text-decoration: none;
            transition: all .18s ease;
        }
        .ad-act:hover { transform: translateY(-2px); text-decoration: none; }
        .ad-act-interview { background: rgba(245, 158, 11, 0.12); border-color: rgba(245, 158, 11, 0.4); color: #f59e0b; }
        .ad-act-interview:hover { background: #f59e0b; color: #fff; }
        .ad-act-cv { background: rgba(16, 185, 129, 0.12); border-color: rgba(16, 185, 129, 0.4); color: #10b981; }
        .ad-act-cv:hover { background: #10b981; color: #fff; }
        .ad-act-msg { background: rgba(139, 92, 246, 0.12); border-color: rgba(139, 92, 246, 0.4); color: #8b5cf6; }
        .ad-act-msg:hover { background: #8b5cf6; color: #fff; }
        .ad-act-mail { background: rgba(59, 130, 246, 0.12); border-color: rgba(59, 130, 246, 0.4); color: #3b82f6; }
        .ad-act-mail:hover { background: #3b82f6; color: #fff; }
        .ad-act-call { background: rgba(6, 182, 212, 0.12); border-color: rgba(6, 182, 212, 0.4); color: #06b6d4; }
        .ad-act-call:hover { background: #06b6d4; color: #fff; }

        .ad-save-btn {
            display: inline-flex; align-items: center; gap: 9px;
            background: linear-gradient(135deg, var(--ad-primary), var(--ad-primary-2));
            color: #fff;
            padding: 14px 30px; border-radius: 13px;
            font-size: 0.92rem; font-weight: 700;
            border: none; cursor: pointer;
            box-shadow: 0 10px 22px rgba(79, 70, 229, 0.35);
            transition: transform .2s ease, box-shadow .2s ease;
        }
        .ad-save-btn:hover { transform: translateY(-2px); box-shadow: 0 14px 28px rgba(79, 70, 229, 0.45); }

        /* Toast */
        .ad-toast {
            position: fixed; top: 84px; right: 24px; z-index: 9999;
            background: var(--ad-card);
            border: 1px solid var(--ad-border);
            border-left: 4px solid #10b981;
            border-radius: 14px;
            padding: 15px 20px;
            display: flex; align-items: center; gap: 12px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.18);
            opacity: 0; transform: translateX(30px);
            transition: all .35s ease;
            pointer-events: none;
        }
        .ad-toast.show { opacity: 1; transform: translateX(0); }
        .ad-toast i { color: #10b981; font-size: 1.3rem; }
        .ad-toast b { color: var(--ad-text); font-size: 0.9rem; }

        @media (max-width: 768px) {
            .ad-wrap { padding: 22px 14px 60px; }
            .ad-grid { grid-template-columns: 1fr; }
            .ad-hero { padding: 24px; }
            .ad-back { margin-left: 0; }
            .ad-score { justify-content: center; text-align: center; }
            .ad-actions .ad-act { flex: 1; }
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/company_header.php'; ?>

    <div class="ad-wrap">
        <!-- Hero -->
        <div class="ad-hero">
            <div class="ad-hero-in">
                <div class="ad-avatar" style="background: linear-gradient(135deg, <?php echo $g[0]; ?>, <?php echo $g[1]; ?>);"><?php echo $initial; ?></div>
                <div class="ad-hero-info">
                    <h1><?php echo htmlspecialchars(trim($app['username'])); ?></h1>
                    <p><i class="fas fa-briefcase"></i>Applied for <strong><?php echo htmlspecialchars($app['job_title']); ?></strong></p>
                    <p><i class="fas fa-calendar"></i>Applied on <?php echo date('M d, Y h:i A', strtotime($app['applied_date'])); ?></p>
                    <div class="ad-hero-badges">
                        <?php if ($app['quiz_status'] == 'passed'): ?>
                            <span class="ad-hbadge"><i class="fas fa-circle-check"></i>Quiz Passed</span>
                        <?php elseif ($app['quiz_status'] == 'failed'): ?>
                            <span class="ad-hbadge"><i class="fas fa-circle-xmark"></i>Quiz Failed</span>
                        <?php else: ?>
                            <span class="ad-hbadge"><i class="fas fa-hourglass-half"></i>Not Taken</span>
                        <?php endif; ?>
                        <span class="ad-hbadge" style="background: <?php echo $app_color; ?>33; border-color: rgba(255,255,255,.4);"><i class="fas fa-tag"></i><?php echo $app_label; ?></span>
                    </div>
                </div>
                <a href="view_applicants.php?job_id=<?php echo $app['job_id']; ?>" class="ad-back">
                    <i class="fas fa-arrow-left"></i>Back to Applicants
                </a>
            </div>
        </div>

        <!-- Contact & Personal Info -->
        <div class="ad-section">
            <div class="ad-section-head">
                <div class="ico"><i class="fas fa-id-card"></i></div>
                <div>
                    <h3>Contact Information</h3>
                    <p>Candidate's personal and contact details.</p>
                </div>
            </div>
            <div class="ad-grid">
                <div class="ad-item">
                    <small>Full Name</small>
                    <span><i class="fas fa-user mr-1" style="color:var(--ad-primary);"></i><?php echo htmlspecialchars(trim($app['username'])); ?></span>
                </div>
                <div class="ad-item">
                    <small>Email</small>
                    <span><a href="mailto:<?php echo htmlspecialchars($app['email']); ?>"><i class="fas fa-envelope mr-1"></i><?php echo htmlspecialchars($app['email']); ?></a></span>
                </div>
                <div class="ad-item">
                    <small>Phone</small>
                    <span><a href="tel:<?php echo htmlspecialchars($app['phone']); ?>"><i class="fas fa-phone mr-1"></i><?php echo htmlspecialchars($app['phone']); ?></a></span>
                </div>
                <div class="ad-item">
                    <small>Education</small>
                    <span><i class="fas fa-graduation-cap mr-1" style="color:var(--ad-primary);"></i><?php echo htmlspecialchars($app['user_degree']); ?></span>
                </div>
            </div>
        </div>

        <!-- Quiz Performance -->
        <div class="ad-section">
            <div class="ad-section-head">
                <div class="ico"><i class="fas fa-chart-pie"></i></div>
                <div>
                    <h3>Quiz Performance</h3>
                    <p>Assessment results for this application.</p>
                </div>
            </div>

            <?php if ($app['quiz_status'] != 'not_taken' && !empty($app['total_questions'])): ?>
                <div class="ad-score">
                    <div class="ad-ring" style="background: conic-gradient(<?php echo $score_color; ?> <?php echo $score_pct; ?>%, var(--ad-border) 0);">
                        <div class="inner">
                            <b style="color: <?php echo $score_color; ?>;"><?php echo $score_pct; ?>%</b>
                            <small>Score</small>
                        </div>
                    </div>
                    <div class="ad-score-stats">
                        <div class="ad-score-stat">
                            <b><?php echo $app['correct_answers']; ?> / <?php echo $app['total_questions']; ?></b>
                            <span><i class="fas fa-check-circle mr-1" style="color:#10b981;"></i>Correct</span>
                        </div>
                        <div class="ad-score-stat">
                            <b><?php echo $app['time_taken'] ? gmdate("i:s", $app['time_taken']) : 'N/A'; ?></b>
                            <span><i class="fas fa-stopwatch mr-1" style="color:var(--ad-primary);"></i>Time Taken</span>
                        </div>
                        <div class="ad-score-stat">
                            <b><?php echo date('M d, Y', strtotime($app['attempt_date'])); ?></b>
                            <span><i class="fas fa-calendar-check mr-1" style="color:#f59e0b;"></i>Attempted</span>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="ad-not-taken">
                    <i class="fas fa-hourglass-half"></i>
                    <h4>Quiz Not Taken Yet</h4>
                    <p class="mb-0">The candidate has not attempted the quiz for this application.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Skills & Expertise -->
        <div class="ad-section">
            <div class="ad-section-head">
                <div class="ico"><i class="fas fa-code"></i></div>
                <div>
                    <h3>Skills & Expertise</h3>
                    <p>Technical and professional skills listed by the candidate.</p>
                </div>
            </div>
            <div class="ad-skills">
                <?php
                $skills = array_filter(array_map('trim', explode(',', $app['user_skills'])));
                if (count($skills) > 0):
                    foreach ($skills as $skill): ?>
                        <span class="ad-skill"><i class="fas fa-check"></i><?php echo htmlspecialchars($skill); ?></span>
                    <?php endforeach;
                else: ?>
                    <span class="ad-muted" style="color:var(--ad-muted);font-size:.9rem;">No skills listed.</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Applied Job -->
        <div class="ad-section">
            <div class="ad-section-head">
                <div class="ico"><i class="fas fa-briefcase"></i></div>
                <div>
                    <h3>Applied Job</h3>
                    <p>The position this candidate applied for.</p>
                </div>
            </div>
            <div class="ad-job">
                <span class="cat"><i class="fas fa-tag"></i><?php echo htmlspecialchars($app['job_category']); ?></span>
                <h4><?php echo htmlspecialchars($app['job_title']); ?></h4>
                <p><?php echo nl2br(htmlspecialchars($app['job_description'])); ?></p>
            </div>
        </div>

        <!-- Cover Letter -->
        <?php if (!empty($app['cover_letter'])): ?>
            <div class="ad-section">
                <div class="ad-section-head">
                    <div class="ico"><i class="fas fa-file-lines"></i></div>
                    <div>
                        <h3>Cover Letter</h3>
                        <p>Candidate's message accompanying the application.</p>
                    </div>
                </div>
                <div class="ad-cover"><?php echo htmlspecialchars($app['cover_letter']); ?></div>
            </div>
        <?php endif; ?>

        <!-- Application Status Management -->
        <div class="ad-section">
            <div class="ad-section-head">
                <div class="ico"><i class="fas fa-sliders"></i></div>
                <div>
                    <h3>Application Status</h3>
                    <p>Update how this application is progressing.</p>
                </div>
            </div>

            <form method="POST" action="" id="statusForm">
                <div class="ad-status-pills mb-4">
                    <?php foreach ($status_colors as $key => $s): ?>
                        <label class="ad-pill <?php echo $app_status == $key ? 'sel' : ''; ?>" data-key="<?php echo $key; ?>">
                            <input type="radio" name="application_status" value="<?php echo $key; ?>" <?php echo $app_status == $key ? 'checked' : ''; ?>>
                            <b><i class="fas fa-circle mr-1" style="color: <?php echo $s[0]; ?>; font-size:.55rem;"></i><?php echo $s[1]; ?></b>
                            <span><?php echo $key == 'pending' ? 'Waiting for review' : ($key == 'reviewed' ? 'Application reviewed' : ($key == 'shortlisted' ? 'Moved to shortlist' : 'Not proceeding')); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <button type="submit" name="update_status" class="ad-save-btn">
                    <i class="fas fa-save"></i>Update Status
                </button>
            </form>
        </div>

        <!-- Actions -->
        <div class="ad-section">
            <div class="ad-section-head">
                <div class="ico"><i class="fas fa-bolt"></i></div>
                <div>
                    <h3>Quick Actions</h3>
                    <p>Reach out or take the next step with this candidate.</p>
                </div>
            </div>
            <div class="ad-actions">
                <?php if ($app['quiz_status'] == 'passed' || $app['application_status'] == 'shortlisted'): ?>
                    <a href="schedule_interview.php?application_id=<?php echo $app['id']; ?>" class="ad-act ad-act-interview">
                        <i class="fas fa-calendar-check"></i>Schedule Interview
                    </a>
                <?php endif; ?>
                <?php if (!empty($app['profile'])): ?>
                    <a href="../files/<?php echo htmlspecialchars($app['profile']); ?>" target="_blank" class="ad-act ad-act-cv">
                        <i class="fas fa-file-pdf"></i>Download CV
                    </a>
                <?php endif; ?>
                <a href="message_center.php?with=user_<?php echo $app['user_id']; ?>" class="ad-act ad-act-msg">
                    <i class="fas fa-comments"></i>Send Message
                </a>
                <a href="mailto:<?php echo htmlspecialchars($app['email']); ?>?subject=Regarding Your Application for <?php echo urlencode($app['job_title']); ?>" class="ad-act ad-act-mail">
                    <i class="fas fa-envelope"></i>Send Email
                </a>
                <a href="tel:<?php echo htmlspecialchars($app['phone']); ?>" class="ad-act ad-act-call">
                    <i class="fas fa-phone"></i>Call Candidate
                </a>
            </div>
        </div>
    </div>

    <!-- Toast -->
    <div class="ad-toast" id="adToast"><i class="fas fa-circle-check"></i><b>Application status updated! The candidate has been notified.</b></div>

    <script>
        // Status pill toggle
        document.querySelectorAll('.ad-pill').forEach(pill => {
            pill.addEventListener('click', () => {
                document.querySelectorAll('.ad-pill').forEach(p => p.classList.remove('sel'));
                pill.classList.add('sel');
            });
        });

        <?php if (isset($status_updated)): ?>
            (function() {
                const t = document.getElementById('adToast');
                t.classList.add('show');
                setTimeout(() => t.classList.remove('show'), 4200);
            })();
        <?php endif; ?>
    </script>
</body>
</html>
