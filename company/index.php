<?php
    session_start();
    require_once '../admin/dbcon.php';
    require_once '../includes/functions.php';

    if (!isset($_SESSION['company_id'])) {
        header('Location: ../company_login.php');
        exit;
    }

    $company_id = (int)$_SESSION['company_id'];
    $company_name = $_SESSION['company_name'] ?? 'Company';

    /* ── KPI stats ─────────────────────────────────────────── */
    $stats = [
        'total_jobs' => 0, 'active_jobs' => 0,
        'total_applications' => 0, 'qualified' => 0, 'shortlisted' => 0,
        'new_month_apps' => 0, 'new_week_apps' => 0,
    ];
    $stats_q = "SELECT
        (SELECT COUNT(*) FROM company_jobs WHERE company_id = $company_id) AS total_jobs,
        (SELECT COUNT(*) FROM company_jobs WHERE company_id = $company_id AND status = 'active') AS active_jobs,
        (SELECT COUNT(*) FROM job_applications WHERE company_id = $company_id) AS total_applications,
        (SELECT COUNT(*) FROM job_applications WHERE company_id = $company_id AND quiz_status = 'passed') AS qualified,
        (SELECT COUNT(*) FROM job_applications WHERE company_id = $company_id AND application_status = 'shortlisted') AS shortlisted,
        (SELECT COUNT(*) FROM job_applications WHERE company_id = $company_id AND applied_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS new_month_apps,
        (SELECT COUNT(*) FROM job_applications WHERE company_id = $company_id AND applied_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)) AS new_week_apps";
    if ($r = mysqli_query($con, $stats_q)) {
        $stats = array_merge($stats, mysqli_fetch_assoc($r));
    }
    foreach ($stats as $k => $v) { $stats[$k] = (int)$v; }

    /* ── Monthly series (for charts + sparklines) ──────────── */
    $months = [];
    for ($i = 5; $i >= 0; $i--) {
        $months[] = date('Y-m', strtotime("-$i month"));
    }
    $apps_by_month  = array_fill_keys($months, 0);
    $pass_by_month  = array_fill_keys($months, 0);
    $jobs_by_month  = array_fill_keys($months, 0);

    if ($r = mysqli_query($con, "SELECT DATE_FORMAT(applied_date, '%Y-%m') m,
                                 COUNT(*) c, SUM(quiz_status = 'passed') p
                                 FROM job_applications WHERE company_id = $company_id GROUP BY m")) {
        while ($row = mysqli_fetch_assoc($r)) {
            if (isset($apps_by_month[$row['m']])) {
                $apps_by_month[$row['m']] = (int)$row['c'];
                $pass_by_month[$row['m']] = (int)$row['p'];
            }
        }
    }
    if ($r = mysqli_query($con, "SELECT DATE_FORMAT(posted_date, '%Y-%m') m, COUNT(*) c
                                 FROM company_jobs WHERE company_id = $company_id GROUP BY m")) {
        while ($row = mysqli_fetch_assoc($r)) {
            if (isset($jobs_by_month[$row['m']])) $jobs_by_month[$row['m']] = (int)$row['c'];
        }
    }

    /* ── Status breakdown ──────────────────────────────────── */
    $status_counts = ['pending' => 0, 'reviewed' => 0, 'shortlisted' => 0, 'rejected' => 0];
    if ($r = mysqli_query($con, "SELECT application_status, COUNT(*) c FROM job_applications
                                 WHERE company_id = $company_id GROUP BY application_status")) {
        while ($row = mysqli_fetch_assoc($r)) {
            if (isset($status_counts[$row['application_status']])) {
                $status_counts[$row['application_status']] = (int)$row['c'];
            }
        }
    }

    /* ── Top jobs by applications ──────────────────────────── */
    $top_jobs = ['labels' => [], 'values' => []];
    if ($r = mysqli_query($con, "SELECT cj.job_title, COUNT(ja.id) c
                                 FROM job_applications ja
                                 JOIN company_jobs cj ON ja.job_id = cj.id
                                 WHERE ja.company_id = $company_id
                                 GROUP BY cj.id, cj.job_title ORDER BY c DESC LIMIT 6")) {
        while ($row = mysqli_fetch_assoc($r)) {
            $top_jobs['labels'][] = html_entity_decode($row['job_title'], ENT_QUOTES);
            $top_jobs['values'][] = (int)$row['c'];
        }
    }

    /* ── Upcoming interviews ───────────────────────────────── */
    $interviews = [];
    $upcoming_interviews = 0;
    if ($r = mysqli_query($con, "SELECT i.*, cj.job_title, ui.username, ui.email, ui.profile
                                 FROM interviews i
                                 JOIN company_jobs cj ON i.job_id = cj.id
                                 JOIN user_info ui ON i.user_id = ui.id
                                 WHERE i.company_id = $company_id AND i.status = 'scheduled'
                                   AND i.interview_date >= CURDATE()
                                 ORDER BY i.interview_date ASC, i.interview_time ASC LIMIT 6")) {
        while ($row = mysqli_fetch_assoc($r)) {
            $interviews[] = $row;
        }
    }
    $upcoming_interviews = count($interviews);
    if ($r = mysqli_query($con, "SELECT COUNT(*) c FROM interviews
                                 WHERE company_id = $company_id AND status = 'scheduled'
                                   AND interview_date >= CURDATE()")) {
        $upcoming_interviews = (int)mysqli_fetch_assoc($r)['c'];
    }
    $intv_by_month = array_fill_keys($months, 0);
    if ($r = mysqli_query($con, "SELECT DATE_FORMAT(interview_date, '%Y-%m') m, COUNT(*) c
                                 FROM interviews WHERE company_id = $company_id
                                 AND status = 'scheduled' GROUP BY m")) {
        while ($row = mysqli_fetch_assoc($r)) {
            if (isset($intv_by_month[$row['m']])) $intv_by_month[$row['m']] = (int)$row['c'];
        }
    }

    /* ── Recent applications ───────────────────────────────── */
    $recent_apps = [];
    $recent_apps_result = mysqli_query($con, "SELECT ja.*, cj.job_title, ui.username, ui.email, ui.profile
                                   FROM job_applications ja
                                   JOIN company_jobs cj ON ja.job_id = cj.id
                                   JOIN user_info ui ON ja.user_id = ui.id
                                   WHERE ja.company_id = $company_id
                                   ORDER BY ja.applied_date DESC LIMIT 8");
    if ($recent_apps_result) {
        while ($row = mysqli_fetch_assoc($recent_apps_result)) {
            $recent_apps[] = $row;
        }
    }

    /* ── Activity feed (company notifications) ─────────────── */
    $activity = [];
    if ($r = mysqli_query($con, "SELECT * FROM notifications
                                 WHERE recipient_type = 'company' AND recipient_id = $company_id
                                 ORDER BY created_at DESC LIMIT 7")) {
        while ($row = mysqli_fetch_assoc($r)) {
            $activity[] = $row;
        }
    }

    /* ── Jobs expiring soon ────────────────────────────────── */
    $jobs_expiring = 0;
    if ($r = mysqli_query($con, "SELECT COUNT(*) c FROM company_jobs
                                 WHERE company_id = $company_id AND status = 'active'
                                   AND deadline IS NOT NULL
                                   AND deadline BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)")) {
        $jobs_expiring = (int)mysqli_fetch_assoc($r)['c'];
    }

    /* ── Pipeline funnel ───────────────────────────────────── */
    $pipeline = [
        ['label' => 'Applied', 'value' => $stats['total_applications'], 'color' => '#3b82f6'],
        ['label' => 'Passed Quiz', 'value' => $stats['qualified'], 'color' => '#8b5cf6'],
        ['label' => 'Shortlisted', 'value' => $stats['shortlisted'], 'color' => '#10b981'],
        ['label' => 'Interviews', 'value' => $upcoming_interviews, 'color' => '#f59e0b'],
    ];
    $pipeline_max = max(1, $pipeline[0]['value']);

    /* ── Sparkline helper ──────────────────────────────────── */
    function nd_sparkline($data, $color = '#4f46e5', $w = 130, $h = 38) {
        $pad = 3;
        $max = max(1, max($data));
        $min = min($data);
        $range = max(1, $max - $min);
        $n = count($data);
        if ($n < 2) { $data = [$data[0], $data[0]]; $n = 2; }
        $step = ($w - $pad * 2) / ($n - 1);
        $pts = [];
        foreach ($data as $i => $v) {
            $x = $pad + $i * $step;
            $y = $h - $pad - (($v - $min) / $range) * ($h - $pad * 2);
            $pts[] = [round($x, 1), round($y, 1)];
        }
        $line = '';
        $area = "M{$pts[0][0]},{$pts[0][1]}";
        foreach ($pts as $k => $p) {
            $line .= ($k === 0 ? 'M' : 'L') . "{$p[0]},{$p[1]} ";
            $area .= " L{$p[0]},{$p[1]}";
        }
        $area .= " L{$pts[$n-1][0]},$h L{$pts[0][0]},$h Z";
        $last = $pts[$n - 1];
        $gid = 'sp' . substr(md5($color . implode('', $data)), 0, 8);
        return '<svg class="nd-spark" viewBox="0 0 ' . $w . ' ' . $h . '" preserveAspectRatio="none" width="100%" height="' . $h . '">'
            . '<defs><linearGradient id="' . $gid . '" x1="0" y1="0" x2="0" y2="1">'
            . '<stop offset="0%" stop-color="' . $color . '" stop-opacity="0.35"/>'
            . '<stop offset="100%" stop-color="' . $color . '" stop-opacity="0.02"/>'
            . '</linearGradient></defs>'
            . '<path d="' . $area . '" fill="url(#' . $gid . ')"/>'
            . '<path d="' . $line . '" fill="none" stroke="' . $color . '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="nd-spark-line"/>'
            . '<circle cx="' . $last[0] . '" cy="' . $last[1] . '" r="2.8" fill="' . $color . '"/>'
            . '</svg>';
    }

    $month_labels = array_map(function ($m) { return date('M', strtotime($m . '-01')); }, $months);
    $chart_data = [
        'months'  => array_values($apps_by_month),
        'passes'  => array_values($pass_by_month),
        'labels'  => $month_labels,
        'top'     => $top_jobs,
        'status'  => [
            'labels' => ['Pending', 'Reviewed', 'Shortlisted', 'Rejected'],
            'values' => array_values($status_counts),
            'colors' => ['#f59e0b', '#0ea5e9', '#10b981', '#ef4444'],
        ],
        'totalApps' => $stats['total_applications'],
    ];

    $now = new DateTime();
    $hour = (int)$now->format('G');
    if ($hour < 12) $greeting = 'Good morning';
    elseif ($hour < 17) $greeting = 'Good afternoon';
    else $greeting = 'Good evening';
    $today_label = $now->format('l, F j, Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Company Dashboard | NovaHire</title>
    <?php include '../includes/links.php'; ?>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Sora:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* ══════════════ MODERN FONT ══════════════ */
        body, .company-navbar, .company-navbar * {
            font-family: 'Manrope', 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        h1, h2, h3, h4, h5, h6 {
            font-family: 'Sora', 'Manrope', 'Inter', sans-serif;
        }
        .nd-stat-num, .nd-hero-sub .nd-clock {
            font-variant-numeric: tabular-nums;
            letter-spacing: -0.02em;
        }
        /* ══════════════ LAYOUT ══════════════ */
        .nd-wrap { padding: 0 0 40px; }

        /* Hero banner */
        .nd-hero {
            position: relative;
            margin-top: -72px;
            padding: 96px 0 84px;
            background: linear-gradient(120deg, #4f46e5 0%, #7c3aed 55%, #0ea5e9 120%);
            overflow: hidden;
        }
        .nd-hero::before, .nd-hero::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            filter: blur(2px);
        }
        .nd-hero::before {
            top: -120px; right: -60px;
            width: 360px; height: 360px;
            background: radial-gradient(circle, rgba(255,255,255,0.14) 0%, transparent 70%);
        }
        .nd-hero::after {
            bottom: -140px; left: 12%;
            width: 320px; height: 320px;
            background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%);
        }
        .nd-hero-inner {
            position: relative; z-index: 2;
            display: flex; align-items: flex-end; justify-content: space-between;
            flex-wrap: wrap; gap: 18px;
        }
        .nd-hero h1 {
            color: #fff; font-size: 2rem; font-weight: 800;
            letter-spacing: -0.5px; margin: 0 0 6px;
        }
        .nd-hero h1 i { font-size: 1.4rem; margin-right: 6px; opacity: .9; }
        .nd-hero .nd-hero-sub { color: rgba(255,255,255,0.82); margin: 0; font-size: .98rem; }
        .nd-hero .nd-hero-sub .nd-clock { display: inline-block; min-width: 104px; font-variant-numeric: tabular-nums; font-weight: 600; color: #fff; }
        .nd-pills { display: flex; gap: 10px; flex-wrap: wrap; }
        .nd-pill {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 9px 16px; border-radius: 999px;
            background: rgba(255,255,255,0.16); border: 1px solid rgba(255,255,255,0.24);
            color: #fff; font-size: .82rem; font-weight: 600;
            backdrop-filter: blur(8px);
            transition: transform .25s ease, background .25s ease;
            text-decoration: none;
        }
        .nd-pill:hover { background: rgba(255,255,255,0.26); color: #fff; transform: translateY(-2px); }
        .nd-pill i { font-size: .8rem; }
        .nd-pill.nd-pill-warn { background: rgba(251,191,36,.22); border-color: rgba(251,191,36,.4); }

        /* Generic card */
        .nd-card {
            background: var(--bg-card);
            border: 1px solid var(--border-light);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            transition: box-shadow .3s ease, transform .3s ease;
        }
        .nd-card:hover { box-shadow: var(--shadow-md); }
        .nd-card-head {
            display: flex; align-items: center; justify-content: space-between;
            gap: 12px; flex-wrap: wrap;
            padding: 18px 22px;
            border-bottom: 1px solid var(--border-light);
        }
        .nd-card-head h5 {
            margin: 0; font-size: 1rem; font-weight: 700;
            display: flex; align-items: center; gap: 10px;
        }
        .nd-card-head h5 .nd-ico {
            width: 34px; height: 34px; border-radius: 10px;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: .82rem; flex-shrink: 0;
        }
        .nd-card-body { padding: 22px; }
        .nd-link-more {
            color: var(--primary); font-weight: 600; font-size: .82rem;
            display: inline-flex; align-items: center; gap: 5px; text-decoration: none;
            white-space: nowrap;
        }
        .nd-link-more:hover { color: var(--primary-dark); text-decoration: none; }

        /* ══════════════ KPI CARDS ══════════════ */
        .nd-stat {
            position: relative; overflow: hidden;
            background: var(--bg-card);
            border: 1px solid var(--border-light);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            padding: 20px 20px 14px;
            transition: transform .3s cubic-bezier(.34,1.56,.64,1), box-shadow .3s ease;
            height: 100%;
            display: flex; flex-direction: column;
        }
        .nd-stat:hover { transform: translateY(-5px); box-shadow: var(--shadow-lg); }
        .nd-stat::after {
            content: '';
            position: absolute; top: -46px; right: -46px;
            width: 120px; height: 120px; border-radius: 50%;
            background: var(--nd-accent);
            opacity: .08;
            transition: transform .4s ease;
        }
        .nd-stat:hover::after { transform: scale(1.35); }
        .nd-stat-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; }
        .nd-stat-ico {
            width: 48px; height: 48px; border-radius: 13px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.15rem; color: #fff;
            background: linear-gradient(135deg, var(--nd-accent), var(--nd-accent-2));
            box-shadow: 0 6px 16px var(--nd-glow);
        }
        .nd-stat-badge {
            font-size: .7rem; font-weight: 700; letter-spacing: .3px;
            padding: 4px 10px; border-radius: 999px;
            display: inline-flex; align-items: center; gap: 4px;
        }
        .nd-stat-badge.up { background: #dcfce7; color: #15803d; }
        .nd-stat-badge.flat { background: #f1f5f9; color: #64748b; }
        .nd-stat-badge i { font-size: .62rem; }
        .nd-stat-num {
            font-size: 2rem; font-weight: 800; line-height: 1;
            color: var(--text); letter-spacing: -.5px;
        }
        .nd-stat-label { color: var(--text-muted); font-size: .82rem; font-weight: 500; margin-top: 4px; }
        .nd-stat-spark { margin-top: 12px; width: 100%; }
        .nd-spark-line {
            stroke-dasharray: 600; stroke-dashoffset: 600;
            animation: ndDraw 1.4s cubic-bezier(.4,0,.2,1) forwards;
        }
        @keyframes ndDraw { to { stroke-dashoffset: 0; } }

        /* ══════════════ CHARTS ══════════════ */
        .nd-tabs { display: inline-flex; gap: 4px; background: var(--bg-hover); padding: 4px; border-radius: 10px; }
        .nd-tab {
            border: none; background: transparent; cursor: pointer;
            padding: 6px 14px; border-radius: 8px;
            font-size: .78rem; font-weight: 600; color: var(--text-muted);
            transition: all .25s ease;
        }
        .nd-tab:hover { color: var(--text); }
        .nd-tab.active { background: var(--bg-card); color: var(--primary); box-shadow: var(--shadow-xs); }
        .nd-chart-box { position: relative; height: 300px; }
        .nd-chart-empty {
            position: absolute; inset: 0;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            color: var(--text-light); gap: 10px;
        }
        .nd-chart-empty i { font-size: 2rem; opacity: .6; }

        .nd-donut-wrap { position: relative; height: 220px; }
        .nd-donut-center {
            position: absolute; inset: 0;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            pointer-events: none;
        }
        .nd-donut-center strong { font-size: 1.7rem; font-weight: 800; color: var(--text); line-height: 1; }
        .nd-donut-center span { font-size: .72rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: .4px; }
        .nd-legend { display: flex; flex-direction: column; gap: 10px; margin-top: 14px; }
        .nd-legend-row {
            display: flex; align-items: center; gap: 10px;
            padding: 8px 12px; border-radius: 10px;
            transition: background .2s ease; cursor: default;
        }
        .nd-legend-row:hover { background: var(--bg-hover); }
        .nd-legend-dot { width: 10px; height: 10px; border-radius: 3px; flex-shrink: 0; }
        .nd-legend-name { flex: 1; font-size: .85rem; font-weight: 600; color: var(--text); }
        .nd-legend-val { font-size: .85rem; font-weight: 700; color: var(--text); }
        .nd-legend-pct { font-size: .78rem; color: var(--text-muted); font-weight: 600; width: 42px; text-align: right; }

        /* ══════════════ PIPELINE FUNNEL ══════════════ */
        .nd-funnel { display: flex; flex-direction: column; gap: 16px; }
        .nd-funnel-step { }
        .nd-funnel-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px; }
        .nd-funnel-head .l { display: flex; align-items: center; gap: 8px; font-size: .85rem; font-weight: 600; color: var(--text); }
        .nd-funnel-head .l i { width: 26px; height: 26px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; font-size: .7rem; color: #fff; }
        .nd-funnel-head .l i i { width: auto; height: auto; background: none; }
        .nd-funnel-head .r { font-size: .82rem; font-weight: 700; color: var(--text); }
        .nd-funnel-head .r small { color: var(--text-muted); font-weight: 600; margin-left: 4px; }
        .nd-funnel-track { height: 12px; background: var(--bg-hover); border-radius: 999px; overflow: hidden; }
        .nd-funnel-fill {
            height: 100%; border-radius: 999px;
            width: 0;
            transition: width 1.1s cubic-bezier(.22,1,.36,1);
        }
        .nd-funnel-note { margin-top: 4px; font-size: .72rem; color: var(--text-light); }

        /* ══════════════ INTERVIEWS ══════════════ */
        .nd-intv {
            display: flex; align-items: center; gap: 14px;
            padding: 14px 16px; border-radius: var(--radius-md);
            background: var(--bg); border: 1px solid var(--border-light);
            margin-bottom: 10px; transition: all .25s ease;
        }
        .nd-intv:last-child { margin-bottom: 0; }
        .nd-intv:hover { border-color: var(--primary); box-shadow: var(--shadow-sm); transform: translateX(3px); }
        .nd-intv-date {
            width: 54px; height: 54px; border-radius: 12px; flex-shrink: 0;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: #fff; display: flex; flex-direction: column; align-items: center; justify-content: center;
        }
        .nd-intv-date b { font-size: 1.15rem; line-height: 1; }
        .nd-intv-date span { font-size: .6rem; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; opacity: .85; }
        .nd-intv-info { flex: 1; min-width: 0; }
        .nd-intv-info h6 { margin: 0 0 2px; font-size: .9rem; font-weight: 700; }
        .nd-intv-info small { color: var(--text-muted); font-size: .78rem; display: flex; align-items: center; gap: 5px; }
        .nd-intv-info small i { font-size: .7rem; }
        .nd-intv-badge { font-size: .68rem; font-weight: 700; padding: 4px 10px; border-radius: 999px; background: rgba(16,185,129,.12); color: #059669; white-space: nowrap; }
        .nd-empty {
            text-align: center; padding: 30px 16px; color: var(--text-light);
        }
        .nd-empty i { font-size: 2rem; opacity: .5; display: block; margin-bottom: 10px; }
        .nd-empty p { margin: 0 0 10px; font-size: .9rem; color: var(--text-muted); font-weight: 500; }

        /* ══════════════ QUICK ACTIONS ══════════════ */
        .nd-actions { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
        .nd-action {
            display: flex; flex-direction: column; align-items: center; text-align: center; gap: 9px;
            padding: 20px 8px; border-radius: var(--radius-md);
            border: 1px solid var(--border-light); background: var(--bg);
            text-decoration: none; color: var(--text);
            transition: all .28s cubic-bezier(.34,1.56,.64,1);
        }
        .nd-action:hover {
            transform: translateY(-5px); box-shadow: var(--shadow-md);
            border-color: var(--nd-accent); text-decoration: none;
        }
        .nd-action .nd-action-ico {
            width: 46px; height: 46px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.05rem; color: #fff;
            background: linear-gradient(135deg, var(--nd-accent), var(--nd-accent-2));
            transition: transform .3s cubic-bezier(.34,1.56,.64,1);
        }
        .nd-action:hover .nd-action-ico { transform: scale(1.12) rotate(-4deg); }
        .nd-action h6 { margin: 0; font-size: .8rem; font-weight: 700; }
        .nd-action small { color: var(--text-muted); font-size: .68rem; }

        /* ══════════════ ACTIVITY FEED ══════════════ */
        .nd-activity { position: relative; padding-left: 26px; }
        .nd-activity::before {
            content: ''; position: absolute; left: 9px; top: 8px; bottom: 8px;
            width: 2px; background: linear-gradient(180deg, var(--primary), var(--border-light));
            border-radius: 2px;
        }
        .nd-activity-item { position: relative; padding: 0 0 18px; }
        .nd-activity-item:last-child { padding-bottom: 0; }
        .nd-activity-dot {
            position: absolute; left: -23px; top: 2px;
            width: 12px; height: 12px; border-radius: 50%;
            border: 2.5px solid var(--bg-card);
            box-shadow: 0 0 0 3px var(--nd-dot);
        }
        .nd-activity-item h6 { margin: 0 0 2px; font-size: .85rem; font-weight: 600; }
        .nd-activity-item p { margin: 0; font-size: .78rem; color: var(--text-muted); line-height: 1.45; }
        .nd-activity-item time { font-size: .68rem; color: var(--text-light); font-weight: 500; }

        /* ══════════════ RECENT APPLICATIONS ══════════════ */
        .nd-search {
            border: 1.5px solid var(--border); border-radius: 10px;
            padding: 8px 14px 8px 36px; font-size: .85rem; background: var(--bg-card);
            color: var(--text); transition: all .25s ease; width: 100%; max-width: 260px;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2.5' stroke-linecap='round'%3E%3Ccircle cx='11' cy='11' r='7'/%3E%3Cline x1='21' y1='21' x2='16.5' y2='16.5'/%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: 12px center;
        }
        .nd-search:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(79,70,229,.12); }
        .nd-app-row {
            display: flex; align-items: center; gap: 14px;
            padding: 14px 0; border-bottom: 1px solid var(--border-light);
            transition: background .2s ease, opacity .2s ease;
        }
        .nd-app-row:first-child { padding-top: 4px; }
        .nd-app-row:last-child { border-bottom: none; }
        .nd-avatar {
            width: 44px; height: 44px; border-radius: 50%; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-weight: 700; font-size: .95rem;
            background: linear-gradient(135deg, var(--nd-accent), var(--nd-accent-2));
        }
        .nd-app-info { flex: 1; min-width: 0; }
        .nd-app-info h6 { margin: 0 0 1px; font-size: .9rem; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .nd-app-info small { color: var(--text-muted); font-size: .76rem; display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .nd-app-job { color: var(--primary); font-weight: 600; font-size: .82rem; flex-shrink: 0; max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .nd-app-date { color: var(--text-light); font-size: .76rem; flex-shrink: 0; white-space: nowrap; }
        .nd-quiz-badge { padding: 4px 10px; border-radius: 999px; font-size: .68rem; font-weight: 700; flex-shrink: 0; }
        .nd-q-passed { background: #dcfce7; color: #166534; }
        .nd-q-failed { background: #fee2e2; color: #991b1b; }
        .nd-q-none { background: #f1f5f9; color: #64748b; }
        .nd-status {
            border: 1.5px solid var(--border); border-radius: 999px;
            padding: 5px 10px; font-size: .74rem; font-weight: 600; color: var(--text);
            background: var(--bg-card); cursor: pointer; transition: all .25s ease;
            appearance: none; -webkit-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='3' stroke-linecap='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 8px center;
            padding-right: 26px;
        }
        .nd-status:focus { outline: none; border-color: var(--primary); }
        .nd-status.st-pending { background-color: #fffbeb; color: #92400e; border-color: #fcd34d; }
        .nd-status.st-reviewed { background-color: #eff6ff; color: #1d4ed8; border-color: #93c5fd; }
        .nd-status.st-shortlisted { background-color: #ecfdf5; color: #047857; border-color: #6ee7b7; }
        .nd-status.st-rejected { background-color: #fef2f2; color: #b91c1c; border-color: #fca5a5; }
        .nd-view-btn {
            padding: 7px 12px; border-radius: 9px; font-size: .78rem; font-weight: 600;
            display: inline-flex; align-items: center; gap: 5px; text-decoration: none;
            background: rgba(59,130,246,.1); color: #2563eb; flex-shrink: 0;
            transition: all .25s ease; border: none;
        }
        .nd-view-btn:hover { background: #3b82f6; color: #fff; text-decoration: none; }

        /* ══════════════ TOASTS ══════════════ */
        .nd-toasts {
            position: fixed; top: 84px; right: 20px; z-index: 10001;
            display: flex; flex-direction: column; gap: 10px;
        }
        .nd-toast {
            background: var(--bg-card); border-radius: 12px;
            padding: 13px 16px; box-shadow: var(--shadow-xl);
            display: flex; align-items: center; gap: 11px; min-width: 280px; max-width: 360px;
            border-left: 4px solid var(--success);
            animation: ndToastIn .3s cubic-bezier(.21,1.02,.73,1) both;
        }
        .nd-toast.out { animation: ndToastOut .3s ease forwards; }
        .nd-toast.err { border-left-color: var(--danger); }
        .nd-toast i {
            width: 30px; height: 30px; border-radius: 9px; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center; font-size: .75rem;
            background: #dcfce7; color: #15803d;
        }
        .nd-toast.err i { background: #fee2e2; color: #b91c1c; }
        .nd-toast span { font-size: .82rem; font-weight: 600; color: var(--text); }
        @keyframes ndToastIn { from { opacity: 0; transform: translateX(120%); } to { opacity: 1; transform: translateX(0); } }
        @keyframes ndToastOut { to { opacity: 0; transform: translateX(120%); } }

        /* ══════════════ REVEAL ANIMATIONS ══════════════ */
        .nd-reveal { opacity: 0; transform: translateY(22px); transition: opacity .6s ease, transform .6s ease; }
        .nd-reveal.nd-in { opacity: 1; transform: translateY(0); }
        .nd-reveal.nd-d1 { transition-delay: .05s; }
        .nd-reveal.nd-d2 { transition-delay: .1s; }
        .nd-reveal.nd-d3 { transition-delay: .15s; }
        .nd-reveal.nd-d4 { transition-delay: .2s; }

        /* Footer */
        .nd-footer {
            background: var(--dark); color: #94a3b8;
            padding: 30px 0 20px; margin-top: 36px; text-align: center;
        }
        .nd-footer p { color: #64748b; font-size: .85rem; margin: 0; }

        /* ══════════════ RESPONSIVE ══════════════ */
        @media (max-width: 991px) {
            .nd-hero { margin-top: -62px; padding: 88px 0 72px; }
            .nd-hero h1 { font-size: 1.65rem; }
            .nd-search { max-width: 100%; }
        }
        @media (max-width: 767px) {
            .nd-hero { padding: 82px 0 64px; margin-top: -52px; }
            .nd-hero h1 { font-size: 1.4rem; }
            .nd-hero-inner { align-items: center; text-align: center; }
            .nd-pills { justify-content: center; }
            .nd-chart-box { height: 260px; }
            .nd-actions { grid-template-columns: repeat(3, 1fr); gap: 8px; }
            .nd-app-row { flex-wrap: wrap; gap: 8px; }
            .nd-app-job { max-width: 100%; order: 3; flex-basis: 100%; }
        }
        @media (max-width: 575px) {
            .nd-hero { padding: 76px 0 56px; }
            .nd-pill { padding: 7px 12px; font-size: .76rem; }
            .nd-stat { padding: 16px 14px 12px; }
            .nd-stat-num { font-size: 1.6rem; }
            .nd-stat-ico { width: 42px; height: 42px; font-size: 1rem; }
            .nd-card-head { padding: 14px 16px; }
            .nd-card-body { padding: 16px; }
            .nd-actions { grid-template-columns: repeat(2, 1fr); }
            .nd-intv-date { width: 48px; height: 48px; }
            .nd-status { font-size: .7rem; }
            .nd-toasts { left: 16px; right: 16px; }
            .nd-toast { min-width: 0; width: 100%; }
        }
    </style>
</head>
<body>

<?php include 'company_header.php'; ?>

<div class="nd-wrap">

    <!-- ═══════════ HERO BANNER ═══════════ -->
    <div class="nd-hero">
        <div class="container">
            <div class="nd-hero-inner">
                <div>
                    <h1><i class="fas fa-hand-wave"></i><?php echo $greeting; ?>, <?php echo htmlspecialchars($company_name); ?></h1>
                    <p class="nd-hero-sub">
                        <span class="nd-clock" id="ndClock"></span> &middot; <?php echo $today_label; ?>
                    </p>
                </div>
                <div class="nd-pills">
                    <a class="nd-pill" href="my_jobs.php"><i class="fas fa-briefcase"></i> <?php echo $stats['active_jobs']; ?> Active Jobs</a>
                    <a class="nd-pill" href="view_applicants.php"><i class="fas fa-users"></i> <?php echo $stats['new_month_apps']; ?> New This Month</a>
                    <?php if ($jobs_expiring > 0): ?>
                        <a class="nd-pill nd-pill-warn" href="my_jobs.php"><i class="fas fa-hourglass-half"></i> <?php echo $jobs_expiring; ?> Expiring Soon</a>
                    <?php else: ?>
                        <span class="nd-pill"><i class="fas fa-calendar-check"></i> <?php echo $upcoming_interviews; ?> Interviews</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="container" style="margin-top: -34px;">

        <!-- ═══════════ KPI CARDS ═══════════ -->
        <div class="row">
            <div class="col-xl-3 col-md-6 mb-3 nd-reveal nd-d1">
                <div class="nd-stat" style="--nd-accent:#6366f1;--nd-accent-2:#818cf8;--nd-glow:rgba(99,102,241,.35);">
                    <div class="nd-stat-top">
                        <div class="nd-stat-ico"><i class="fas fa-briefcase"></i></div>
                        <span class="nd-stat-badge up"><i class="fas fa-caret-up"></i><?php echo $stats['active_jobs']; ?> active</span>
                    </div>
                    <div class="nd-stat-num nd-count" data-count="<?php echo $stats['total_jobs']; ?>">0</div>
                    <div class="nd-stat-label">Total Jobs Posted</div>
                    <div class="nd-stat-spark"><?php echo nd_sparkline(array_values($jobs_by_month), '#6366f1'); ?></div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3 nd-reveal nd-d2">
                <div class="nd-stat" style="--nd-accent:#0ea5e9;--nd-accent-2:#38bdf8;--nd-glow:rgba(14,165,233,.35);">
                    <div class="nd-stat-top">
                        <div class="nd-stat-ico"><i class="fas fa-file-alt"></i></div>
                        <span class="nd-stat-badge up"><i class="fas fa-caret-up"></i>+<?php echo $stats['new_month_apps']; ?> this mo</span>
                    </div>
                    <div class="nd-stat-num nd-count" data-count="<?php echo $stats['total_applications']; ?>">0</div>
                    <div class="nd-stat-label">Total Applications</div>
                    <div class="nd-stat-spark"><?php echo nd_sparkline(array_values($apps_by_month), '#0ea5e9'); ?></div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3 nd-reveal nd-d3">
                <div class="nd-stat" style="--nd-accent:#10b981;--nd-accent-2:#34d399;--nd-glow:rgba(16,185,129,.35);">
                    <div class="nd-stat-top">
                        <div class="nd-stat-ico"><i class="fas fa-user-check"></i></div>
                        <span class="nd-stat-badge flat"><?php echo $stats['shortlisted']; ?> shortlisted</span>
                    </div>
                    <div class="nd-stat-num nd-count" data-count="<?php echo $stats['qualified']; ?>">0</div>
                    <div class="nd-stat-label">Qualified Candidates</div>
                    <div class="nd-stat-spark"><?php echo nd_sparkline(array_values($pass_by_month), '#10b981'); ?></div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3 nd-reveal nd-d4">
                <div class="nd-stat" style="--nd-accent:#8b5cf6;--nd-accent-2:#a78bfa;--nd-glow:rgba(139,92,246,.35);">
                    <div class="nd-stat-top">
                        <div class="nd-stat-ico"><i class="fas fa-calendar-check"></i></div>
                        <span class="nd-stat-badge up"><i class="fas fa-caret-up"></i><?php echo $upcoming_interviews; ?> upcoming</span>
                    </div>
                    <div class="nd-stat-num nd-count" data-count="<?php echo $upcoming_interviews; ?>">0</div>
                    <div class="nd-stat-label">Interviews Scheduled</div>
                    <div class="nd-stat-spark"><?php echo nd_sparkline(array_values($intv_by_month), '#8b5cf6'); ?></div>
                </div>
            </div>
        </div>

        <!-- ═══════════ CHARTS ROW ═══════════ -->
        <div class="row mt-2">
            <div class="col-lg-8 mb-3 nd-reveal">
                <div class="nd-card" style="height:100%;">
                    <div class="nd-card-head">
                        <h5><span class="nd-ico" style="background:rgba(79,70,229,.1);color:var(--primary);"><i class="fas fa-chart-line"></i></span>Analytics Overview</h5>
                        <div class="nd-tabs">
                            <button class="nd-tab active" data-chart="apps" type="button">Applications</button>
                            <button class="nd-tab" data-chart="top" type="button">Top Jobs</button>
                        </div>
                    </div>
                    <div class="nd-card-body">
                        <div class="nd-chart-box">
                            <canvas id="ndMainChart"></canvas>
                            <div class="nd-chart-empty" id="ndChartEmpty" style="display:none;">
                                <i class="fas fa-chart-bar"></i>
                                <span>No data to display yet — post a job to get started!</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 mb-3 nd-reveal nd-d2">
                <div class="nd-card" style="height:100%;">
                    <div class="nd-card-head">
                        <h5><span class="nd-ico" style="background:rgba(16,185,129,.1);color:#059669;"><i class="fas fa-chart-pie"></i></span>Candidate Status</h5>
                    </div>
                    <div class="nd-card-body">
                        <div class="nd-donut-wrap">
                            <canvas id="ndStatusChart"></canvas>
                            <div class="nd-donut-center">
                                <strong id="ndDonutTotal">0</strong>
                                <span>Total Apps</span>
                            </div>
                        </div>
                        <div class="nd-legend" id="ndLegend"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══════════ PIPELINE + INTERVIEWS ═══════════ -->
        <div class="row mt-1">
            <div class="col-lg-5 mb-3 nd-reveal">
                <div class="nd-card" style="height:100%;">
                    <div class="nd-card-head">
                        <h5><span class="nd-ico" style="background:rgba(139,92,246,.1);color:#7c3aed;"><i class="fas fa-filter"></i></span>Hiring Pipeline</h5>
                    </div>
                    <div class="nd-card-body">
                        <div class="nd-funnel">
                            <?php foreach ($pipeline as $i => $step): ?>
                                <?php
                                    $pct = $pipeline_max > 0 ? round(($step['value'] / $pipeline_max) * 100) : 0;
                                    $target = max(4, $pct);
                                ?>
                                <div class="nd-funnel-step nd-fill" data-width="<?php echo $target; ?>">
                                    <div class="nd-funnel-head">
                                        <span class="l"><i class="nd-funnel-ico" style="background:<?php echo $step['color']; ?>;"><i class="fas <?php echo ['fa-users','fa-clipboard-check','fa-user-check','fa-calendar-check'][$i]; ?>"></i></i><?php echo $step['label']; ?></span>
                                        <span class="r"><?php echo $step['value']; ?><small>(<?php echo $pct; ?>%)</small></span>
                                    </div>
                                    <div class="nd-funnel-track">
                                        <div class="nd-funnel-fill" style="background:linear-gradient(90deg,<?php echo $step['color']; ?>,<?php echo $step['color']; ?>cc);" data-width="<?php echo $target; ?>"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <p class="nd-funnel-note"><i class="fas fa-info-circle"></i> Conversion from total applications received to interviews scheduled.</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-7 mb-3 nd-reveal nd-d2">
                <div class="nd-card" style="height:100%;">
                    <div class="nd-card-head">
                        <h5><span class="nd-ico" style="background:rgba(245,158,11,.1);color:#d97706;"><i class="fas fa-video"></i></span>Upcoming Interviews</h5>
                        <a class="nd-link-more" href="view_applicants.php"><i class="fas fa-calendar-alt"></i> Schedule</a>
                    </div>
                    <div class="nd-card-body">
                        <?php if (count($interviews) > 0): ?>
                            <?php foreach ($interviews as $intv): ?>
                                <?php $d = strtotime($intv['interview_date']); ?>
                                <div class="nd-intv">
                                    <div class="nd-intv-date">
                                        <b><?php echo date('d', $d); ?></b>
                                        <span><?php echo date('M', $d); ?></span>
                                    </div>
                                    <div class="nd-intv-info">
                                        <h6><?php echo htmlspecialchars($intv['username']); ?> <span class="nd-intv-badge"><i class="fas fa-<?php echo strtolower($intv['interview_type']) == 'online' ? 'video' : (strtolower($intv['interview_type']) == 'phone' ? 'phone' : 'building'); ?>"></i> <?php echo htmlspecialchars($intv['interview_type']); ?></span></h6>
                                        <small><i class="fas fa-briefcase"></i><?php echo htmlspecialchars($intv['job_title']); ?></small>
                                        <small><i class="fas fa-clock"></i><?php echo date('g:i A', strtotime($intv['interview_time'])); ?></small>
                                    </div>
                                    <?php if (!empty($intv['location'])): ?>
                                        <small class="text-muted d-none d-md-block" style="max-width:110px;text-align:right;"><i class="fas fa-map-marker-alt mr-1"></i><?php echo htmlspecialchars($intv['location']); ?></small>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="nd-empty">
                                <i class="fas fa-calendar-check"></i>
                                <p>No upcoming interviews scheduled.</p>
                                <a href="view_applicants.php" class="nd-view-btn" style="background:rgba(16,185,129,.12);color:#059669;"><i class="fas fa-user-check"></i> Shortlist Candidates</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══════════ RECENT APPS + RIGHT RAIL ═══════════ -->
        <div class="row mt-1">
            <div class="col-lg-8 mb-3 nd-reveal">
                <div class="nd-card" style="height:100%;">
                    <div class="nd-card-head">
                        <h5><span class="nd-ico" style="background:rgba(245,158,11,.1);color:#d97706;"><i class="fas fa-inbox"></i></span>Recent Applications</h5>
                        <input type="text" class="nd-search" id="ndAppSearch" placeholder="Search applicants...">
                    </div>
                    <div class="nd-card-body" style="padding-top:8px;">
                        <?php if (count($recent_apps) > 0): ?>
                            <?php foreach ($recent_apps as $app):
                                $qclass = $app['quiz_status'] == 'passed' ? 'nd-q-passed' : ($app['quiz_status'] == 'failed' ? 'nd-q-failed' : 'nd-q-none');
                                $qtext = $app['quiz_status'] == 'passed' ? 'Quiz Passed' : ($app['quiz_status'] == 'failed' ? 'Quiz Failed' : 'No Quiz');
                                $stcls = 'st-' . $app['application_status'];
                            ?>
                                <div class="nd-app-row" data-search="<?php echo strtolower(htmlspecialchars($app['username'] . ' ' . $app['email'] . ' ' . $app['job_title'])); ?>">
                                    <div class="nd-avatar" style="--nd-accent:<?php echo ['#4f46e5','#7c3aed','#db2777','#ea580c','#0ea5e9','#059669'][$app['id'] % 6]; ?>;--nd-accent-2:<?php echo ['#818cf8','#a78bfa','#f472b6','#fb923c','#38bdf8','#34d399'][$app['id'] % 6]; ?>;">
                                        <?php echo strtoupper(substr($app['username'], 0, 1)); ?>
                                    </div>
                                    <div class="nd-app-info">
                                        <h6><?php echo htmlspecialchars($app['username']); ?></h6>
                                        <small><i class="fas fa-envelope mr-1"></i><?php echo htmlspecialchars($app['email']); ?></small>
                                    </div>
                                    <div class="nd-app-job"><i class="fas fa-briefcase mr-1" style="font-size:.68rem;"></i><?php echo htmlspecialchars($app['job_title']); ?></div>
                                    <span class="nd-app-date"><i class="far fa-clock mr-1"></i><?php echo date('M d', strtotime($app['applied_date'])); ?></span>
                                    <span class="nd-quiz-badge <?php echo $qclass; ?>"><?php echo $qtext; ?></span>
                                    <select class="nd-status <?php echo $stcls; ?>" data-app="<?php echo $app['id']; ?>">
                                        <option value="pending" <?php echo $app['application_status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="reviewed" <?php echo $app['application_status'] == 'reviewed' ? 'selected' : ''; ?>>Reviewed</option>
                                        <option value="shortlisted" <?php echo $app['application_status'] == 'shortlisted' ? 'selected' : ''; ?>>Shortlist</option>
                                        <option value="rejected" <?php echo $app['application_status'] == 'rejected' ? 'selected' : ''; ?>>Reject</option>
                                    </select>
                                    <a href="view_applicant_detail.php?id=<?php echo $app['id']; ?>" class="nd-view-btn"><i class="fas fa-eye"></i> View</a>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="nd-empty">
                                <i class="fas fa-inbox"></i>
                                <p>No applications received yet.</p>
                                <a href="post_job.php" class="nd-view-btn" style="background:rgba(79,70,229,.1);color:var(--primary);"><i class="fas fa-plus-circle"></i> Post a Job</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 mb-3 nd-reveal nd-d2">
                <div class="nd-card mb-3">
                    <div class="nd-card-head">
                        <h5><span class="nd-ico" style="background:rgba(236,72,153,.1);color:#db2777;"><i class="fas fa-bolt"></i></span>Quick Actions</h5>
                    </div>
                    <div class="nd-card-body">
                        <div class="nd-actions">
                            <a href="post_job.php" class="nd-action" style="--nd-accent:#4f46e5;--nd-accent-2:#818cf8;"><div class="nd-action-ico"><i class="fas fa-plus-circle"></i></div><h6>Post Job</h6><small>New listing</small></a>
                            <a href="view_applicants.php" class="nd-action" style="--nd-accent:#10b981;--nd-accent-2:#34d399;"><div class="nd-action-ico"><i class="fas fa-users"></i></div><h6>Applicants</h6><small>Review</small></a>
                            <a href="my_jobs.php" class="nd-action" style="--nd-accent:#db2777;--nd-accent-2:#f472b6;"><div class="nd-action-ico"><i class="fas fa-list"></i></div><h6>My Jobs</h6><small>Manage</small></a>
                            <a href="manage_quiz.php" class="nd-action" style="--nd-accent:#f59e0b;--nd-accent-2:#fbbf24;"><div class="nd-action-ico"><i class="fas fa-question-circle"></i></div><h6>Quizzes</h6><small>Questions</small></a>
                            <a href="category_applicants.php" class="nd-action" style="--nd-accent:#7c3aed;--nd-accent-2:#a78bfa;"><div class="nd-action-ico"><i class="fas fa-user-graduate"></i></div><h6>Category</h6><small>Applicants</small></a>
                            <a href="profile.php" class="nd-action" style="--nd-accent:#0ea5e9;--nd-accent-2:#38bdf8;"><div class="nd-action-ico"><i class="fas fa-building"></i></div><h6>Profile</h6><small>Company</small></a>
                        </div>
                    </div>
                </div>

                <div class="nd-card">
                    <div class="nd-card-head">
                        <h5><span class="nd-ico" style="background:rgba(59,130,246,.1);color:#2563eb;"><i class="fas fa-stream"></i></span>Recent Activity</h5>
                        <a class="nd-link-more" href="../seeker/notifications.php">View All</a>
                    </div>
                    <div class="nd-card-body" style="padding-top:16px;">
                        <?php if (count($activity) > 0): ?>
                            <div class="nd-activity">
                                <?php foreach ($activity as $act):
                                    $colors = ['#4f46e5', '#3b82f6', '#10b981', '#f59e0b', '#ec4899', '#8b5cf6'];
                                    $icons = ['fa-bell', 'fa-file-alt', 'fa-clipboard-check', 'fa-chart-line', 'fa-envelope', 'fa-star'];
                                    $type_map = ['application_status' => 2, 'new_application' => 1, 'message' => 4, 'quiz_result' => 3, 'job_update' => 5, 'job_recommendation' => 5, 'system' => 0];
                                    $idx = $type_map[$act['notification_type']] ?? 0;
                                ?>
                                    <div class="nd-activity-item" style="--nd-dot:<?php echo $colors[$idx]; ?>;">
                                        <span class="nd-activity-dot" style="background:<?php echo $colors[$idx]; ?>;"></span>
                                        <h6><?php echo htmlspecialchars($act['title']); ?></h6>
                                        <p><?php echo strip_tags($act['message']); ?></p>
                                        <time><?php echo time_ago($act['created_at']); ?></time>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="nd-empty">
                                <i class="fas fa-stream"></i>
                                <p>No recent activity yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<footer class="nd-footer">
    <div class="container">
        <p>&copy; <?php echo date('Y'); ?> NovaHire &middot; Company Dashboard</p>
    </div>
</footer>

<div class="nd-toasts" id="ndToasts"></div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
(function () {
    /* ── Theme bridge: keep data-theme in sync with header toggle ── */
    function syncTheme() {
        var dark = document.documentElement.getAttribute('data-theme') === 'dark';
        document.body.classList.toggle('dark-theme', dark);
        return dark;
    }
    function cssVar(name, fallback) {
        var v = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
        return v || fallback;
    }

    /* ── Live clock ── */
    var clockEl = document.getElementById('ndClock');
    function tick() {
        if (!clockEl) return;
        var d = new Date();
        var h = d.getHours() % 12 || 12;
        var m = String(d.getMinutes()).padStart(2, '0');
        var s = String(d.getSeconds()).padStart(2, '0');
        clockEl.textContent = h + ':' + m + ':' + s + ' ' + (d.getHours() >= 12 ? 'PM' : 'AM');
    }
    tick();
    setInterval(tick, 1000);

    /* ── Reveal + count-up ── */
    function animateCount(el) {
        var target = parseInt(el.dataset.count, 10) || 0;
        var dur = 1100, start = null;
        function step(ts) {
            if (!start) start = ts;
            var p = Math.min((ts - start) / dur, 1);
            var eased = 1 - Math.pow(1 - p, 3);
            el.textContent = Math.round(target * eased).toLocaleString();
            if (p < 1) requestAnimationFrame(step);
        }
        requestAnimationFrame(step);
    }
    var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (e) {
            if (!e.isIntersecting) return;
            e.target.classList.add('nd-in');
            e.target.querySelectorAll('.nd-count').forEach(animateCount);
            io.unobserve(e.target);
        });
    }, { threshold: 0.12 });
    document.querySelectorAll('.nd-reveal').forEach(function (el) { io.observe(el); });

    /* ── Pipeline funnel animation ── */
    var funnelIo = new IntersectionObserver(function (entries) {
        entries.forEach(function (e) {
            if (!e.isIntersecting) return;
            e.target.querySelectorAll('.nd-funnel-fill').forEach(function (f) {
                f.style.width = f.dataset.width + '%';
            });
            funnelIo.unobserve(e.target);
        });
    }, { threshold: 0.3 });
    document.querySelectorAll('.nd-funnel').forEach(function (el) { funnelIo.observe(el); });

    /* ── Chart data from PHP ── */
    var DATA = <?php echo json_encode($chart_data); ?>;

    var mainChart = null, statusChart = null;
    var chartText = cssVar('--text-muted', '#64748b');
    var chartGrid = cssVar('--border-light', '#f1f5f9');

    function palette() {
        return {
            text: cssVar('--text-muted', '#64748b'),
            grid: cssVar('--border-light', '#f1f5f9')
        };
    }

    function buildMain(type) {
        var p = palette();
        var ctx = document.getElementById('ndMainChart');
        var empty = document.getElementById('ndChartEmpty');
        if (!ctx) return;

        var hasApps = DATA.months.some(function (v) { return v > 0; });
        var hasTop = DATA.top.values.length > 0;

        if ((type === 'apps' && !hasApps) || (type === 'top' && !hasTop)) {
            ctx.style.display = 'none';
            empty.style.display = 'flex';
            return;
        }
        ctx.style.display = 'block';
        empty.style.display = 'none';

        if (mainChart) mainChart.destroy();

        if (type === 'apps') {
            var grad = ctx.getContext('2d').createLinearGradient(0, 0, 0, 280);
            grad.addColorStop(0, 'rgba(79,70,229,0.28)');
            grad.addColorStop(1, 'rgba(79,70,229,0.01)');
            mainChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: DATA.labels,
                    datasets: [{
                        label: 'Applications',
                        data: DATA.months,
                        borderColor: '#6366f1',
                        backgroundColor: grad,
                        fill: true,
                        tension: 0.42,
                        borderWidth: 2.5,
                        pointRadius: 3,
                        pointBackgroundColor: '#6366f1',
                        pointHoverRadius: 6,
                        pointHoverBorderWidth: 2,
                        pointHoverBackgroundColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: { duration: 900, easing: 'easeOutQuart' },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: cssVar('--bg-card', '#fff'),
                            titleColor: cssVar('--text', '#1e293b'),
                            bodyColor: cssVar('--text', '#1e293b'),
                            borderColor: cssVar('--border', '#e2e8f0'),
                            borderWidth: 1,
                            padding: 12,
                            cornerRadius: 10,
                            displayColors: false,
                            callbacks: { label: function (c) { return c.parsed.y + ' application' + (c.parsed.y === 1 ? '' : 's'); } }
                        }
                    },
                    scales: {
                        x: { grid: { display: false }, ticks: { color: p.text, font: { size: 11 } } },
                        y: { beginAtZero: true, ticks: { color: p.text, precision: 0 }, grid: { color: p.grid } }
                    }
                }
            });
        } else {
            mainChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: DATA.top.labels,
                    datasets: [{
                        label: 'Applications',
                        data: DATA.top.values,
                        backgroundColor: ['#6366f1', '#8b5cf6', '#ec4899', '#f59e0b', '#10b981', '#0ea5e9'],
                        borderRadius: 8,
                        borderSkipped: false,
                        maxBarThickness: 26
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: { duration: 900, easing: 'easeOutQuart' },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: cssVar('--bg-card', '#fff'),
                            titleColor: cssVar('--text', '#1e293b'),
                            bodyColor: cssVar('--text', '#1e293b'),
                            borderColor: cssVar('--border', '#e2e8f0'),
                            borderWidth: 1,
                            padding: 12,
                            cornerRadius: 10,
                            displayColors: false
                        }
                    },
                    scales: {
                        x: { beginAtZero: true, ticks: { color: p.text, precision: 0 }, grid: { color: p.grid } },
                        y: { grid: { display: false }, ticks: { color: p.text, font: { size: 11 } } }
                    }
                }
            });
        }
    }

    function buildStatus() {
        var p = palette();
        var ctx = document.getElementById('ndStatusChart');
        if (!ctx) return;
        if (statusChart) statusChart.destroy();

        var hasData = DATA.status.values.some(function (v) { return v > 0; });
        document.getElementById('ndDonutTotal').textContent = DATA.totalApps;

        statusChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: DATA.status.labels,
                datasets: [{
                    data: DATA.status.values,
                    backgroundColor: DATA.status.colors,
                    borderWidth: 0,
                    hoverOffset: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '72%',
                animation: { animateRotate: true, duration: 1000, easing: 'easeOutQuart' },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: cssVar('--bg-card', '#fff'),
                        titleColor: cssVar('--text', '#1e293b'),
                        bodyColor: cssVar('--text', '#1e293b'),
                        borderColor: cssVar('--border', '#e2e8f0'),
                        borderWidth: 1,
                        padding: 12,
                        cornerRadius: 10,
                        callbacks: {
                            label: function (c) {
                                var total = c.dataset.data.reduce(function (a, b) { return a + b; }, 0);
                                var pct = total > 0 ? Math.round(c.parsed / total * 100) : 0;
                                return ' ' + c.label + ': ' + c.parsed + ' (' + pct + '%)';
                            }
                        }
                    }
                }
            }
        });

        /* legend */
        var legend = document.getElementById('ndLegend');
        if (!legend) return;
        var html = '';
        var total = DATA.totalApps || 1;
        DATA.status.labels.forEach(function (name, i) {
            var val = DATA.status.values[i];
            var pct = Math.round(val / total * 100);
            html += '<div class="nd-legend-row">'
                + '<span class="nd-legend-dot" style="background:' + DATA.status.colors[i] + ';"></span>'
                + '<span class="nd-legend-name">' + name + '</span>'
                + '<span class="nd-legend-val">' + val + '</span>'
                + '<span class="nd-legend-pct">' + pct + '%</span>'
                + '</div>';
        });
        legend.innerHTML = html;
    }

    /* ── Tabs ── */
    document.querySelectorAll('.nd-tab').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.nd-tab').forEach(function (b) { b.classList.remove('active'); });
            btn.classList.add('active');
            buildMain(btn.dataset.chart);
        });
    });

    /* ── Rebuild charts on theme change ── */
    function rebuildCharts() {
        var active = document.querySelector('.nd-tab.active');
        buildMain(active ? active.dataset.chart : 'apps');
        buildStatus();
    }
    document.addEventListener('click', function (e) {
        if (e.target && e.target.closest && e.target.closest('.btn-theme-toggle')) {
            setTimeout(function () { syncTheme(); rebuildCharts(); }, 60);
        }
    });

    /* ── Toasts ── */
    function toast(type, msg) {
        var wrap = document.getElementById('ndToasts');
        var el = document.createElement('div');
        el.className = 'nd-toast' + (type === 'error' ? ' err' : '');
        el.innerHTML = '<i class="fas ' + (type === 'error' ? 'fa-times' : 'fa-check') + '"></i><span>' + msg + '</span>';
        wrap.appendChild(el);
        setTimeout(function () {
            el.classList.add('out');
            setTimeout(function () { el.remove(); }, 320);
        }, 3200);
    }

    /* ── Live search ── */
    var search = document.getElementById('ndAppSearch');
    if (search) {
        search.addEventListener('input', function () {
            var f = search.value.toLowerCase();
            document.querySelectorAll('.nd-app-row').forEach(function (row) {
                row.style.display = row.dataset.search.indexOf(f) > -1 ? '' : 'none';
            });
        });
    }

    /* ── Inline status update (AJAX) ── */
    document.querySelectorAll('.nd-status').forEach(function (sel) {
        sel.addEventListener('change', function () {
            var appId = sel.dataset.app;
            var val = sel.value;
            var prev = sel.classList[1];
            fetch('update_application_status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'app_id=' + encodeURIComponent(appId) + '&status=' + encodeURIComponent(val)
            })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.ok) {
                    sel.className = 'nd-status st-' + val;
                    toast('success', res.msg);
                } else {
                    sel.className = 'nd-status ' + prev;
                    toast('error', res.msg || 'Update failed');
                }
            })
            .catch(function () {
                sel.className = 'nd-status ' + prev;
                toast('error', 'Network error, please try again');
            });
        });
    });

    /* ── Init ── */
    syncTheme();
    buildMain('apps');
    buildStatus();
})();
</script>
</body>
</html>
