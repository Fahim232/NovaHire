<?php
session_start();
if (!isset($_SESSION['admin_username'])) {
    echo '<script>alert("You are logged out!"); window.location.href="admin_login.php";</script>';
    exit();
}

require_once 'dbcon.php';
include 'header.php';

$total_users = (int)(mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) as cnt FROM user_info"))['cnt'] ?? 0);
$total_companies = (int)(mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) as cnt FROM companies"))['cnt'] ?? 0);
$total_jobs = (int)(mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) as cnt FROM company_jobs"))['cnt'] ?? 0);
$total_applications = (int)(mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) as cnt FROM job_applications"))['cnt'] ?? 0);

$stats = ['total_users' => $total_users, 'total_companies' => $total_companies, 'total_jobs' => $total_jobs, 'total_applications' => $total_applications];
if ($r = mysqli_query($con, "SELECT COUNT(*) c FROM company_jobs WHERE status = 'active'")) {
    $stats['active_jobs'] = (int)mysqli_fetch_assoc($r)['c'];
}
if ($r = mysqli_query($con, "SELECT COUNT(*) c FROM job_applications WHERE applied_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)")) {
    $stats['new_apps_30'] = (int)mysqli_fetch_assoc($r)['c'];
}
foreach (['active_jobs', 'new_apps_30'] as $k) { if (!isset($stats[$k])) $stats[$k] = 0; }

$months = [];
for ($i = 5; $i >= 0; $i--) {
    $months[] = date('Y-m', strtotime("-$i month"));
}
$apps_by_month = array_fill_keys($months, 0);
$jobs_by_month = array_fill_keys($months, 0);

if ($r = mysqli_query($con, "SELECT DATE_FORMAT(applied_date, '%Y-%m') m, COUNT(*) c FROM job_applications GROUP BY m")) {
    while ($row = mysqli_fetch_assoc($r)) {
        if (isset($apps_by_month[$row['m']])) $apps_by_month[$row['m']] = (int)$row['c'];
    }
}
if ($r = mysqli_query($con, "SELECT DATE_FORMAT(posted_date, '%Y-%m') m, COUNT(*) c FROM company_jobs GROUP BY m")) {
    while ($row = mysqli_fetch_assoc($r)) {
        if (isset($jobs_by_month[$row['m']])) $jobs_by_month[$row['m']] = (int)$row['c'];
    }
}

$status_counts = ['pending' => 0, 'reviewed' => 0, 'shortlisted' => 0, 'rejected' => 0];
if ($r = mysqli_query($con, "SELECT application_status, COUNT(*) c FROM job_applications GROUP BY application_status")) {
    while ($row = mysqli_fetch_assoc($r)) {
        if (isset($status_counts[$row['application_status']])) $status_counts[$row['application_status']] = (int)$row['c'];
    }
}

$top_jobs = ['labels' => [], 'values' => []];
if ($r = mysqli_query($con, "SELECT cj.job_title, COUNT(ja.id) c FROM job_applications ja
                             JOIN company_jobs cj ON ja.job_id = cj.id
                             GROUP BY cj.id, cj.job_title ORDER BY c DESC LIMIT 6")) {
    while ($row = mysqli_fetch_assoc($r)) {
        $top_jobs['labels'][] = html_entity_decode($row['job_title'], ENT_QUOTES);
        $top_jobs['values'][] = (int)$row['c'];
    }
}

$recent_apps = [];
if ($r = mysqli_query($con, "SELECT ja.*, u.username, cj.job_title, c.company_name
                             FROM job_applications ja
                             LEFT JOIN user_info u ON ja.user_id = u.id
                             LEFT JOIN company_jobs cj ON ja.job_id = cj.id
                             LEFT JOIN companies c ON ja.company_id = c.id
                             ORDER BY ja.applied_date DESC LIMIT 8")) {
    while ($row = mysqli_fetch_assoc($r)) $recent_apps[] = $row;
}

$recent_users = [];
if ($r = mysqli_query($con, "SELECT * FROM user_info ORDER BY id DESC LIMIT 8")) {
    while ($row = mysqli_fetch_assoc($r)) $recent_users[] = $row;
}

function ad_sparkline($data, $color = '#6366f1', $w = 130, $h = 38) {
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
    $gid = 'adsp' . substr(md5($color . implode('', $data)), 0, 8);
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
    'apps'  => array_values($apps_by_month),
    'jobs'  => array_values($jobs_by_month),
    'labels' => $month_labels,
    'top'    => $top_jobs,
    'status' => [
        'labels' => ['Pending', 'Reviewed', 'Shortlisted', 'Rejected'],
        'values' => array_values($status_counts),
        'colors' => ['#f59e0b', '#0ea5e9', '#10b981', '#ef4444'],
    ],
    'totalApps' => $total_applications,
];

$now = new DateTime();
$hour = (int)$now->format('G');
if ($hour < 12) $greeting = 'Good morning';
elseif ($hour < 17) $greeting = 'Good afternoon';
else $greeting = 'Good evening';
$today_label = $now->format('l, F j, Y');
$admin_name = ucwords(str_replace('_', ' ', $_SESSION['admin_username']));
?>

<style>
    @keyframes ad-reveal { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: none; } }
    .ad-reveal { opacity: 0; }
    .ad-reveal.nd-in { animation: ad-reveal .5s ease forwards; }
    .ad-d1 { animation-delay: .05s; } .ad-d2 { animation-delay: .12s; } .ad-d3 { animation-delay: .19s; } .ad-d4 { animation-delay: .26s; }

    /* ═══════════ LAYOUT ═══════════ */
    .ad-wrap { padding: 0 0 40px; }

    .ad-hero {
        position: relative;
        margin-top: -72px;
        padding: 96px 0 84px;
        background: linear-gradient(120deg, #4f46e5 0%, #7c3aed 55%, #0ea5e9 120%);
        overflow: hidden;
    }
    .ad-hero::before, .ad-hero::after {
        content: '';
        position: absolute;
        border-radius: 50%;
        filter: blur(2px);
    }
    .ad-hero::before {
        top: -120px; right: -60px;
        width: 360px; height: 360px;
        background: radial-gradient(circle, rgba(255,255,255,0.14) 0%, transparent 70%);
    }
    .ad-hero::after {
        bottom: -140px; left: 12%;
        width: 320px; height: 320px;
        background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%);
    }
    .ad-hero-inner {
        position: relative; z-index: 2;
        display: flex; align-items: flex-end; justify-content: space-between;
        flex-wrap: wrap; gap: 18px;
    }
    .ad-hero h1 {
        color: #fff; font-size: 2rem; font-weight: 800;
        letter-spacing: -0.5px; margin: 0 0 6px;
    }
    .ad-hero h1 i { font-size: 1.4rem; margin-right: 6px; opacity: .9; }
    .ad-hero .ad-hero-sub { color: rgba(255,255,255,0.82); margin: 0; font-size: .98rem; }
    .ad-hero .ad-hero-sub .ad-clock { display: inline-block; min-width: 104px; font-variant-numeric: tabular-nums; font-weight: 600; color: #fff; }
    .ad-pills { display: flex; gap: 10px; flex-wrap: wrap; }
    .ad-pill {
        display: inline-flex; align-items: center; gap: 7px;
        padding: 9px 16px; border-radius: 999px;
        background: rgba(255,255,255,0.16); border: 1px solid rgba(255,255,255,0.24);
        color: #fff; font-size: .82rem; font-weight: 600;
        backdrop-filter: blur(8px);
        transition: transform .25s ease, background .25s ease;
        text-decoration: none;
    }
    .ad-pill:hover { background: rgba(255,255,255,0.26); color: #fff; transform: translateY(-2px); }
    .ad-pill i { font-size: .8rem; }

    .ad-card {
        background: var(--bg-card);
        border: 1px solid var(--border-light);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-sm);
        overflow: hidden;
        transition: box-shadow .3s ease, transform .3s ease;
    }
    .ad-card:hover { box-shadow: var(--shadow-md); }
    .ad-card-head {
        display: flex; align-items: center; justify-content: space-between;
        gap: 12px; flex-wrap: wrap;
        padding: 18px 22px;
        border-bottom: 1px solid var(--border-light);
    }
    .ad-card-head h5 {
        margin: 0; font-size: 1rem; font-weight: 700;
        display: flex; align-items: center; gap: 10px;
    }
    .ad-card-head h5 .ad-ico {
        width: 34px; height: 34px; border-radius: 10px;
        display: inline-flex; align-items: center; justify-content: center;
        font-size: .82rem; flex-shrink: 0;
    }
    .ad-card-body { padding: 22px; }
    .ad-link-more {
        color: var(--primary); font-weight: 600; font-size: .82rem;
        display: inline-flex; align-items: center; gap: 5px; text-decoration: none;
        white-space: nowrap;
    }
    .ad-link-more:hover { color: var(--primary-dark); text-decoration: none; }

    /* ═══════════ KPI CARDS ═══════════ */
    .ad-stat {
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
    .ad-stat:hover { transform: translateY(-5px); box-shadow: var(--shadow-lg); }
    .ad-stat::after {
        content: '';
        position: absolute; top: -46px; right: -46px;
        width: 120px; height: 120px; border-radius: 50%;
        background: var(--nd-accent);
        opacity: .08;
        transition: transform .4s ease;
    }
    .ad-stat:hover::after { transform: scale(1.35); }
    .ad-stat-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; }
    .ad-stat-ico {
        width: 48px; height: 48px; border-radius: 13px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.15rem; color: #fff;
        background: linear-gradient(135deg, var(--nd-accent), var(--nd-accent-2));
        box-shadow: 0 8px 16px -6px var(--nd-glow);
    }
    .ad-stat-badge {
        font-size: .7rem; font-weight: 700; padding: 5px 10px; border-radius: 999px;
        display: inline-flex; align-items: center; gap: 4px;
        background: rgba(16,185,129,.12); color: #059669;
    }
    .ad-stat-badge.flat { background: var(--bg-hover); color: var(--text-muted); }
    .ad-stat-num {
        font-family: 'Sora', 'Manrope', 'Inter', sans-serif;
        font-size: 2.1rem; font-weight: 800; line-height: 1; color: var(--text);
        font-variant-numeric: tabular-nums; letter-spacing: -0.02em;
    }
    .ad-stat-label { font-size: .82rem; color: var(--text-muted); font-weight: 600; margin-top: 6px; }
    .ad-stat-spark { margin-top: 12px; }
    .nd-spark { display: block; }

    /* ═══════════ TABS + CHARTS ═══════════ */
    .ad-tabs {
        display: inline-flex; gap: 4px; padding: 4px;
        background: var(--bg-hover); border-radius: 12px;
    }
    .ad-tab {
        border: none; background: transparent;
        padding: 7px 14px; border-radius: 9px;
        font-size: .78rem; font-weight: 700; color: var(--text-muted);
        cursor: pointer; transition: all .25s ease;
    }
    .ad-tab.active {
        background: linear-gradient(135deg, #6366f1, #8b5cf6);
        color: #fff; box-shadow: 0 4px 12px -4px rgba(99,102,241,.6);
    }
    .ad-chart-box { position: relative; height: 290px; }
    .ad-empty { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; color: var(--text-muted); gap: 10px; }
    .ad-empty i { font-size: 2.2rem; opacity: .5; }

    .ad-legend { display: flex; flex-direction: column; gap: 10px; margin-top: 18px; }
    .ad-legend-row { display: flex; align-items: center; gap: 10px; font-size: .85rem; }
    .ad-legend-dot { width: 10px; height: 10px; border-radius: 3px; flex-shrink: 0; }
    .ad-legend-name { flex: 1; font-weight: 600; color: var(--text); }
    .ad-legend-val { font-weight: 800; color: var(--text); }
    .ad-legend-pct { color: var(--text-muted); font-weight: 600; min-width: 44px; text-align: right; }

    .ad-donut-wrap { position: relative; height: 210px; }
    .ad-donut-center {
        position: absolute; inset: 0; display: flex; flex-direction: column;
        align-items: center; justify-content: center; pointer-events: none;
    }
    .ad-donut-center b { font-family: 'Sora', 'Manrope', sans-serif; font-size: 1.7rem; color: var(--text); }
    .ad-donut-center small { color: var(--text-muted); font-weight: 600; font-size: .72rem; text-transform: uppercase; letter-spacing: .06em; }

    /* ═══════════ QUICK ACTIONS ═══════════ */
    .ad-actions { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; }
    .ad-action {
        display: flex; flex-direction: column; align-items: flex-start; gap: 8px;
        padding: 18px; border-radius: 14px; text-decoration: none;
        background: var(--bg-hover);
        border: 1px solid transparent;
        transition: all .25s ease;
    }
    .ad-action:hover { background: var(--bg-card); border-color: var(--border-light); box-shadow: var(--shadow-md); transform: translateY(-3px); text-decoration: none; }
    .ad-action-ico {
        width: 44px; height: 44px; border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1rem; color: #fff;
        background: linear-gradient(135deg, var(--nd-accent), var(--nd-accent-2));
        box-shadow: 0 6px 14px -6px var(--nd-glow);
    }
    .ad-action h6 { margin: 0; font-weight: 700; font-size: .92rem; color: var(--text); }
    .ad-action small { color: var(--text-muted); font-size: .78rem; }

    /* ═══════════ TABLES ═══════════ */
    .ad-table { width: 100%; border-collapse: collapse; }
    .ad-table thead th {
        background: var(--bg-hover);
        padding: 12px 16px; font-size: .74rem; font-weight: 700;
        text-transform: uppercase; letter-spacing: .5px; color: var(--text-muted);
        border-bottom: 1px solid var(--border-light); white-space: nowrap; text-align: left;
    }
    .ad-table tbody td {
        padding: 13px 16px; font-size: .88rem; color: var(--text);
        border-bottom: 1px solid var(--border-light); vertical-align: middle;
    }
    .ad-table tbody tr { transition: background .15s ease; }
    .ad-table tbody tr:hover { background: var(--bg-hover); }
    .ad-avatar {
        width: 34px; height: 34px; border-radius: 10px; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        color: #fff; font-weight: 700; font-size: .8rem;
        background: linear-gradient(135deg, var(--nd-accent), var(--nd-accent-2));
    }
    .ad-badge {
        display: inline-flex; align-items: center; gap: 5px;
        padding: 4px 12px; border-radius: 999px;
        font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .3px;
    }
    .ad-badge.ok { background: rgba(16,185,129,.12); color: #059669; }
    .ad-badge.warn { background: rgba(245,158,11,.14); color: #b45309; }
    .ad-badge.info { background: rgba(14,165,233,.12); color: #0369a1; }
    .ad-badge.err { background: rgba(239,68,68,.12); color: #b91c1c; }
    .ad-badge.gray { background: var(--bg-hover); color: var(--text-muted); }

    .ad-search {
        border: 1.5px solid var(--border-light); border-radius: 10px;
        padding: 8px 14px; font-size: .85rem; background: var(--bg-card);
        color: var(--text); transition: border-color .2s ease; outline: none;
        min-width: 210px;
    }
    .ad-search:focus { border-color: #6366f1; }
    .ad-search::placeholder { color: var(--text-light); }

    .ad-row-link { color: var(--text); font-weight: 600; text-decoration: none; }
    .ad-row-link:hover { color: var(--primary); text-decoration: none; }
    .ad-act {
        display: inline-flex; align-items: center; gap: 4px;
        padding: 5px 11px; border-radius: 8px; font-size: .76rem; font-weight: 600;
        text-decoration: none; transition: all .2s ease;
    }
    .ad-act i { font-size: .8rem; }
    .ad-act.v { background: #eef2ff; color: #4f46e5; }
    .ad-act.v:hover { background: #e0e7ff; color: #3730a3; text-decoration: none; }
    .ad-act.d { background: #fee2e2; color: #991b1b; }
    .ad-act.d:hover { background: #fecaca; color: #7f1d1d; text-decoration: none; }

    /* ═══════════ FOOTER + TOASTS ═══════════ */
    .ad-footer { padding: 26px 0 34px; text-align: center; color: var(--text-muted); font-size: .85rem; }
    .nd-toasts { position: fixed; bottom: 22px; right: 22px; z-index: 1200; display: flex; flex-direction: column; gap: 10px; }
    .nd-toast {
        display: flex; align-items: center; gap: 10px;
        background: var(--bg-card); color: var(--text);
        border: 1px solid var(--border-light); border-left: 4px solid #10b981;
        border-radius: 12px; padding: 12px 16px; min-width: 260px;
        box-shadow: var(--shadow-lg); font-size: .86rem; font-weight: 600;
        animation: nd-toast-in .3s ease;
    }
    .nd-toast.err { border-left-color: #ef4444; }
    .nd-toast i { color: #10b981; }
    .nd-toast.err i { color: #ef4444; }
    .nd-toast.out { opacity: 0; transform: translateX(20px); transition: all .3s ease; }
    @keyframes nd-toast-in { from { opacity: 0; transform: translateX(20px); } to { opacity: 1; transform: none; } }

    @media (max-width: 991px) {
        .ad-actions { grid-template-columns: repeat(2, 1fr); gap: 10px; }
    }
    @media (max-width: 767px) {
        .ad-hero { padding: 84px 0 64px; }
        .ad-stat-num { font-size: 1.7rem; }
    }
    @media (max-width: 575px) {
        .ad-hero h1 { font-size: 1.5rem; }
        .ad-actions { grid-template-columns: 1fr; }
        .ad-chart-box { height: 230px; }
        .nd-toasts { left: 16px; right: 16px; }
    }
</style>

<div class="ad-wrap">
    <!-- Hero -->
    <div class="ad-hero">
        <div class="container">
            <div class="ad-hero-inner">
                <div>
                    <h1><i class="fas fa-shield-halved"></i><?php echo $greeting; ?>, <?php echo htmlspecialchars($admin_name); ?></h1>
                    <p class="ad-hero-sub">
                        <span class="ad-clock" id="adClock"></span> &middot; <?php echo $today_label; ?>
                    </p>
                </div>
                <div class="ad-pills">
                    <a class="ad-pill" href="showdata.php"><i class="fas fa-file-alt"></i> <?php echo $stats['new_apps_30']; ?> Apps This Month</a>
                    <a class="ad-pill" href="show_users.php"><i class="fas fa-users"></i> <?php echo $stats['total_users']; ?> Users</a>
                    <a class="ad-pill" href="showdata.php"><i class="fas fa-briefcase"></i> <?php echo $stats['active_jobs']; ?> Active Jobs</a>
                </div>
            </div>
        </div>
    </div>

    <div class="container" style="margin-top: -34px;">

        <!-- KPI CARDS -->
        <div class="row">
            <div class="col-xl-3 col-md-6 mb-3 ad-reveal ad-d1">
                <div class="ad-stat" style="--nd-accent:#6366f1;--nd-accent-2:#818cf8;--nd-glow:rgba(99,102,241,.35);">
                    <div class="ad-stat-top">
                        <div class="ad-stat-ico"><i class="fas fa-users"></i></div>
                        <span class="ad-stat-badge flat">All registered</span>
                    </div>
                    <div class="ad-stat-num ad-count" data-count="<?php echo $stats['total_users']; ?>">0</div>
                    <div class="ad-stat-label">Total Users</div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3 ad-reveal ad-d2">
                <div class="ad-stat" style="--nd-accent:#8b5cf6;--nd-accent-2:#a78bfa;--nd-glow:rgba(139,92,246,.35);">
                    <div class="ad-stat-top">
                        <div class="ad-stat-ico"><i class="fas fa-building"></i></div>
                        <span class="ad-stat-badge flat">On platform</span>
                    </div>
                    <div class="ad-stat-num ad-count" data-count="<?php echo $stats['total_companies']; ?>">0</div>
                    <div class="ad-stat-label">Total Companies</div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3 ad-reveal ad-d3">
                <div class="ad-stat" style="--nd-accent:#0ea5e9;--nd-accent-2:#38bdf8;--nd-glow:rgba(14,165,233,.35);">
                    <div class="ad-stat-top">
                        <div class="ad-stat-ico"><i class="fas fa-briefcase"></i></div>
                        <span class="ad-stat-badge"><i class="fas fa-circle"></i><?php echo $stats['active_jobs']; ?> active</span>
                    </div>
                    <div class="ad-stat-num ad-count" data-count="<?php echo $stats['total_jobs']; ?>">0</div>
                    <div class="ad-stat-label">Total Jobs</div>
                    <div class="ad-stat-spark"><?php echo ad_sparkline(array_values($jobs_by_month), '#0ea5e9'); ?></div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3 ad-reveal ad-d4">
                <div class="ad-stat" style="--nd-accent:#10b981;--nd-accent-2:#34d399;--nd-glow:rgba(16,185,129,.35);">
                    <div class="ad-stat-top">
                        <div class="ad-stat-ico"><i class="fas fa-file-alt"></i></div>
                        <span class="ad-stat-badge"><i class="fas fa-caret-up"></i>+<?php echo $stats['new_apps_30']; ?> this mo</span>
                    </div>
                    <div class="ad-stat-num ad-count" data-count="<?php echo $stats['total_applications']; ?>">0</div>
                    <div class="ad-stat-label">Total Applications</div>
                    <div class="ad-stat-spark"><?php echo ad_sparkline(array_values($apps_by_month), '#10b981'); ?></div>
                </div>
            </div>
        </div>

        <!-- CHARTS ROW -->
        <div class="row mt-2">
            <div class="col-lg-8 mb-3 ad-reveal">
                <div class="ad-card" style="height:100%;">
                    <div class="ad-card-head">
                        <h5><span class="ad-ico" style="background:rgba(79,70,229,.1);color:var(--primary);"><i class="fas fa-chart-line"></i></span>Analytics Overview</h5>
                        <div class="ad-tabs">
                            <button class="ad-tab active" data-chart="apps" type="button">Applications</button>
                            <button class="ad-tab" data-chart="jobs" type="button">Jobs</button>
                            <button class="ad-tab" data-chart="top" type="button">Top Jobs</button>
                        </div>
                    </div>
                    <div class="ad-card-body">
                        <div class="ad-chart-box">
                            <canvas id="adMainChart"></canvas>
                            <div class="ad-empty" id="adChartEmpty" style="display:none;">
                                <i class="fas fa-chart-bar"></i><span>No data available yet</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 mb-3 ad-reveal">
                <div class="ad-card" style="height:100%;">
                    <div class="ad-card-head">
                        <h5><span class="ad-ico" style="background:rgba(16,185,129,.1);color:#059669;"><i class="fas fa-chart-pie"></i></span>Application Status</h5>
                    </div>
                    <div class="ad-card-body">
                        <div class="ad-donut-wrap">
                            <canvas id="adStatusChart"></canvas>
                            <div class="ad-donut-center"><b id="adDonutTotal">0</b><small>Total</small></div>
                        </div>
                        <div class="ad-legend" id="adLegend"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- QUICK ACTIONS -->
        <div class="row mt-2 mb-3 ad-reveal">
            <div class="col-12">
                <div class="ad-card">
                    <div class="ad-card-head">
                        <h5><span class="ad-ico" style="background:rgba(245,158,11,.12);color:#d97706;"><i class="fas fa-bolt"></i></span>Quick Actions</h5>
                    </div>
                    <div class="ad-card-body">
                        <div class="ad-actions">
                            <a href="add_details.php" class="ad-action" style="--nd-accent:#6366f1;--nd-accent-2:#818cf8;--nd-glow:rgba(99,102,241,.35);">
                                <div class="ad-action-ico"><i class="fas fa-user-plus"></i></div>
                                <h6>Add User</h6><small>Create a new user account</small>
                            </a>
                            <a href="add_admin.php" class="ad-action" style="--nd-accent:#0f172a;--nd-accent-2:#334155;--nd-glow:rgba(15,23,42,.3);">
                                <div class="ad-action-ico"><i class="fas fa-user-shield"></i></div>
                                <h6>Add Admin</h6><small>Create a new admin account</small>
                            </a>
                            <a href="showdata.php" class="ad-action" style="--nd-accent:#8b5cf6;--nd-accent-2:#a78bfa;--nd-glow:rgba(139,92,246,.35);">
                                <div class="ad-action-ico"><i class="fas fa-chart-bar"></i></div>
                                <h6>View Reports</h6><small>Review application reports</small>
                            </a>
                            <a href="../company_job_quiz.php" class="ad-action" style="--nd-accent:#0ea5e9;--nd-accent-2:#38bdf8;--nd-glow:rgba(14,165,233,.35);">
                                <div class="ad-action-ico"><i class="fas fa-question-circle"></i></div>
                                <h6>Quiz Questions</h6><small>Manage quiz content</small>
                            </a>
                            <a href="../browse_jobs.php" class="ad-action" style="--nd-accent:#10b981;--nd-accent-2:#34d399;--nd-glow:rgba(16,185,129,.35);">
                                <div class="ad-action-ico"><i class="fas fa-search"></i></div>
                                <h6>Browse Jobs</h6><small>View all posted jobs</small>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- RECENT APPLICATIONS + RECENT USERS -->
        <div class="row">
            <div class="col-lg-7 mb-3 ad-reveal">
                <div class="ad-card" style="height:100%;">
                    <div class="ad-card-head">
                        <h5><span class="ad-ico" style="background:rgba(14,165,233,.12);color:#0369a1;"><i class="fas fa-clock"></i></span>Recent Applications</h5>
                        <a href="showdata.php" class="ad-link-more">View All <i class="fas fa-arrow-right"></i></a>
                    </div>
                    <div class="ad-card-body" style="padding:0;">
                        <?php if (count($recent_apps) > 0): ?>
                            <div class="table-responsive">
                                <table class="ad-table">
                                    <thead>
                                        <tr>
                                            <th>#</th><th>User</th><th>Job Title</th><th>Company</th><th>Status</th><th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_apps as $app): ?>
                                        <tr>
                                            <td><strong><?php echo $app['id']; ?></strong></td>
                                            <td><strong><?php echo htmlspecialchars($app['username'] ?? 'N/A'); ?></strong></td>
                                            <td><?php echo htmlspecialchars($app['job_title'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($app['company_name'] ?? 'N/A'); ?></td>
                                            <td>
                                                <?php
                                                    $badge = 'ad-badge warn'; $label = ucfirst($app['application_status']);
                                                    if ($app['application_status'] === 'reviewed') { $badge = 'ad-badge info'; }
                                                    elseif ($app['application_status'] === 'shortlisted') { $badge = 'ad-badge ok'; }
                                                    elseif ($app['application_status'] === 'rejected') { $badge = 'ad-badge err'; }
                                                ?>
                                                <span class="<?php echo $badge; ?>"><?php echo $label; ?></span>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($app['applied_date'])); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="ad-empty" style="padding:40px 0;"><i class="fas fa-inbox"></i><span>No applications yet</span></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-5 mb-3 ad-reveal">
                <div class="ad-card" style="height:100%;">
                    <div class="ad-card-head">
                        <h5><span class="ad-ico" style="background:rgba(139,92,246,.12);color:#7c3aed;"><i class="fas fa-user-clock"></i></span>Latest Users</h5>
                        <a href="show_users.php" class="ad-link-more">View All <i class="fas fa-arrow-right"></i></a>
                    </div>
                    <div class="ad-card-body" style="padding:12px 22px;">
                        <?php if (count($recent_users) > 0): ?>
                            <?php $avatar_colors = ['#6366f1','#8b5cf6','#0ea5e9','#10b981','#f59e0b','#ec4899']; ?>
                            <?php foreach ($recent_users as $u): ?>
                                <div class="d-flex align-items-center" style="padding:10px 0;border-bottom:1px solid var(--border-light);">
                                    <div class="ad-avatar" style="--nd-accent:<?php echo $avatar_colors[$u['id'] % 6]; ?>;--nd-accent-2:<?php echo $avatar_colors[$u['id'] % 6]; ?>;">
                                        <?php echo strtoupper(substr($u['username'], 0, 1)); ?>
                                    </div>
                                    <div class="ml-3 flex-grow-1" style="min-width:0;">
                                        <strong class="ad-row-link"><?php echo htmlspecialchars($u['username']); ?></strong>
                                        <div style="font-size:.78rem;color:var(--text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo htmlspecialchars($u['email']); ?></div>
                                    </div>
                                    <span class="ad-badge gray">#<?php echo $u['id']; ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="ad-empty" style="padding:40px 0;"><i class="fas fa-users-slash"></i><span>No users yet</span></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- MANAGE USERS -->
        <div class="row mt-2 mb-3 ad-reveal">
            <div class="col-12">
                <div class="ad-card">
                    <div class="ad-card-head">
                        <h5><span class="ad-ico" style="background:rgba(99,102,241,.1);color:#4f46e5;"><i class="fas fa-users"></i></span>Manage Users</h5>
                        <input type="text" class="ad-search" id="userSearch" placeholder="Search users..." onkeyup="adFilter('usersTable','userSearch')">
                    </div>
                    <div class="ad-card-body" style="padding:0;">
                        <div class="table-responsive">
                            <table class="ad-table" id="usersTable">
                                <thead>
                                    <tr><th>#</th><th>Name</th><th>Email</th><th>Phone</th><th>Degree</th><th>Actions</th></tr>
                                </thead>
                                <tbody>
                                <?php
                                $users_r = mysqli_query($con, "SELECT * FROM user_info ORDER BY id DESC LIMIT 20");
                                if ($users_r && mysqli_num_rows($users_r) > 0):
                                    while ($u = mysqli_fetch_assoc($users_r)):
                                ?>
                                    <tr>
                                        <td><strong><?php echo $u['id']; ?></strong></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="ad-avatar mr-2" style="--nd-accent:#6366f1;--nd-accent-2:#818cf8;"><?php echo strtoupper(substr($u['username'],0,1)); ?></div>
                                                <strong class="ad-row-link"><?php echo htmlspecialchars($u['username']); ?></strong>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($u['email']); ?></td>
                                        <td><?php echo htmlspecialchars($u['phone']); ?></td>
                                        <td><?php echo htmlspecialchars($u['user_degree'] ?? 'N/A'); ?></td>
                                        <td>
                                            <a href="update_user.php?id=<?php echo $u['id']; ?>" class="ad-act v"><i class="fas fa-pen"></i> Edit</a>
                                            <a href="delete_user.php?id=<?php echo $u['id']; ?>" class="ad-act d" onclick="return confirm('Delete this user?')"><i class="fas fa-trash"></i></a>
                                        </td>
                                    </tr>
                                <?php endwhile; else: ?>
                                    <tr><td colspan="6"><div class="ad-empty" style="padding:30px 0;"><i class="fas fa-users-slash"></i><span>No users found</span></div></td></tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- MANAGE COMPANIES -->
        <div class="row mb-3 ad-reveal">
            <div class="col-12">
                <div class="ad-card">
                    <div class="ad-card-head">
                        <h5><span class="ad-ico" style="background:rgba(139,92,246,.12);color:#7c3aed;"><i class="fas fa-building"></i></span>Manage Companies</h5>
                        <input type="text" class="ad-search" id="companySearch" placeholder="Search companies..." onkeyup="adFilter('companiesTable','companySearch')">
                    </div>
                    <div class="ad-card-body" style="padding:0;">
                        <div class="table-responsive">
                            <table class="ad-table" id="companiesTable">
                                <thead>
                                    <tr><th>#</th><th>Company</th><th>Email</th><th>Industry</th><th>Size</th><th>Status</th><th>Actions</th></tr>
                                </thead>
                                <tbody>
                                <?php
                                $comp_r = mysqli_query($con, "SELECT * FROM companies ORDER BY id DESC LIMIT 20");
                                if ($comp_r && mysqli_num_rows($comp_r) > 0):
                                    while ($c = mysqli_fetch_assoc($comp_r)):
                                ?>
                                    <tr>
                                        <td><strong><?php echo $c['id']; ?></strong></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="ad-avatar mr-2" style="--nd-accent:#8b5cf6;--nd-accent-2:#a78bfa;"><?php echo strtoupper(substr($c['company_name'],0,1)); ?></div>
                                                <strong class="ad-row-link"><?php echo htmlspecialchars($c['company_name']); ?></strong>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($c['company_email']); ?></td>
                                        <td><?php echo htmlspecialchars($c['industry']); ?></td>
                                        <td><?php echo htmlspecialchars($c['company_size']); ?></td>
                                        <td><span class="ad-badge <?php echo $c['status'] === 'active' ? 'ok' : 'err'; ?>"><?php echo ucfirst($c['status']); ?></span></td>
                                        <td>
                                            <a href="update_company.php?id=<?php echo $c['id']; ?>" class="ad-act v"><i class="fas fa-pen"></i> Edit</a>
                                            <a href="toggle_company_status.php?id=<?php echo $c['id']; ?>" class="ad-act v" onclick="return confirm('Toggle company status?')"><i class="fas fa-power-off"></i></a>
                                            <a href="delete_company.php?id=<?php echo $c['id']; ?>" class="ad-act d" onclick="return confirm('Delete this company?')"><i class="fas fa-trash"></i></a>
                                        </td>
                                    </tr>
                                <?php endwhile; else: ?>
                                    <tr><td colspan="7"><div class="ad-empty" style="padding:30px 0;"><i class="fas fa-building"></i><span>No companies found</span></div></td></tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- MANAGE JOBS -->
        <div class="row mb-3 ad-reveal">
            <div class="col-12">
                <div class="ad-card">
                    <div class="ad-card-head">
                        <h5><span class="ad-ico" style="background:rgba(14,165,233,.12);color:#0369a1;"><i class="fas fa-briefcase"></i></span>Manage Jobs</h5>
                        <input type="text" class="ad-search" id="jobSearch" placeholder="Search jobs..." onkeyup="adFilter('jobsTable','jobSearch')">
                    </div>
                    <div class="ad-card-body" style="padding:0;">
                        <div class="table-responsive">
                            <table class="ad-table" id="jobsTable">
                                <thead>
                                    <tr><th>#</th><th>Job Title</th><th>Company</th><th>Location</th><th>Type</th><th>Deadline</th><th>Status</th><th>Actions</th></tr>
                                </thead>
                                <tbody>
                                <?php
                                $jobs_r = mysqli_query($con, "SELECT cj.*, c.company_name FROM company_jobs cj LEFT JOIN companies c ON cj.company_id = c.id ORDER BY cj.id DESC LIMIT 20");
                                if ($jobs_r && mysqli_num_rows($jobs_r) > 0):
                                    while ($j = mysqli_fetch_assoc($jobs_r)):
                                        $jb = $j['status'] === 'active' ? 'ad-badge ok' : ($j['status'] === 'closed' ? 'ad-badge gray' : 'ad-badge warn');
                                        $deadline_passed = strtotime($j['deadline']) < time();
                                ?>
                                    <tr>
                                        <td><strong><?php echo $j['id']; ?></strong></td>
                                        <td><strong class="ad-row-link"><?php echo htmlspecialchars($j['job_title']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($j['company_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($j['location']); ?></td>
                                        <td><?php echo htmlspecialchars($j['employment_type']); ?></td>
                                        <td>
                                            <?php echo date('M d, Y', strtotime($j['deadline'])); ?>
                                            <?php if ($deadline_passed): ?><br><small style="color:#dc2626;font-weight:700;">Expired</small><?php endif; ?>
                                        </td>
                                        <td><span class="<?php echo $jb; ?>"><?php echo ucfirst($j['status']); ?></span></td>
                                        <td>
                                            <a href="../job_details.php?id=<?php echo $j['id']; ?>" class="ad-act v"><i class="fas fa-eye"></i></a>
                                            <a href="delete_job.php?id=<?php echo $j['id']; ?>" class="ad-act d" onclick="return confirm('Delete this job?')"><i class="fas fa-trash"></i></a>
                                        </td>
                                    </tr>
                                <?php endwhile; else: ?>
                                    <tr><td colspan="8"><div class="ad-empty" style="padding:30px 0;"><i class="fas fa-briefcase"></i><span>No jobs posted yet</span></div></td></tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<footer class="ad-footer">
    &copy; <?php echo date('Y'); ?> NovaHire &middot; Admin Dashboard
</footer>

<div class="nd-toasts" id="ndToasts"></div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
(function () {
    /* Theme bridge */
    function syncTheme() {
        var dark = document.documentElement.getAttribute('data-theme') === 'dark';
        document.body.classList.toggle('dark-theme', dark);
        return dark;
    }
    function cssVar(name, fallback) {
        var v = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
        return v || fallback;
    }

    /* Live clock */
    var clockEl = document.getElementById('adClock');
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

    /* Reveal + count-up */
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
            e.target.querySelectorAll('.ad-count').forEach(animateCount);
            io.unobserve(e.target);
        });
    }, { threshold: 0.12 });
    document.querySelectorAll('.ad-reveal').forEach(function (el) { io.observe(el); });

    /* Chart data from PHP */
    var DATA = <?php echo json_encode($chart_data); ?>;

    var mainChart = null, statusChart = null;
    function palette() {
        return { text: cssVar('--text-muted', '#64748b'), grid: cssVar('--border-light', '#f1f5f9') };
    }

    function buildMain(type) {
        var p = palette();
        var ctx = document.getElementById('adMainChart');
        var empty = document.getElementById('adChartEmpty');
        if (!ctx) return;

        var hasApps = DATA.apps.some(function (v) { return v > 0; });
        var hasJobs = DATA.jobs.some(function (v) { return v > 0; });
        var hasTop = DATA.top.values.length > 0;

        if ((type === 'apps' && !hasApps) || (type === 'jobs' && !hasJobs) || (type === 'top' && !hasTop)) {
            ctx.style.display = 'none';
            empty.style.display = 'flex';
            return;
        }
        ctx.style.display = 'block';
        empty.style.display = 'none';

        if (mainChart) mainChart.destroy();

        if (type === 'apps') {
            var grad = ctx.getContext('2d').createLinearGradient(0, 0, 0, 280);
            grad.addColorStop(0, 'rgba(16,185,129,0.28)');
            grad.addColorStop(1, 'rgba(16,185,129,0.01)');
            mainChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: DATA.labels,
                    datasets: [{
                        label: 'Applications',
                        data: DATA.apps,
                        borderColor: '#10b981',
                        backgroundColor: grad,
                        fill: true,
                        tension: 0.42,
                        borderWidth: 2.5,
                        pointRadius: 3,
                        pointBackgroundColor: '#10b981',
                        pointHoverRadius: 6,
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
                            borderWidth: 1, padding: 12, cornerRadius: 10, displayColors: false,
                            callbacks: { label: function (c) { return c.parsed.y + ' application' + (c.parsed.y === 1 ? '' : 's'); } }
                        }
                    },
                    scales: {
                        x: { grid: { display: false }, ticks: { color: p.text, font: { size: 11 } } },
                        y: { beginAtZero: true, ticks: { color: p.text, precision: 0 }, grid: { color: p.grid } }
                    }
                }
            });
        } else if (type === 'jobs') {
            var grad2 = ctx.getContext('2d').createLinearGradient(0, 0, 0, 280);
            grad2.addColorStop(0, 'rgba(14,165,233,0.28)');
            grad2.addColorStop(1, 'rgba(14,165,233,0.01)');
            mainChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: DATA.labels,
                    datasets: [{
                        label: 'Jobs',
                        data: DATA.jobs,
                        borderColor: '#0ea5e9',
                        backgroundColor: grad2,
                        fill: true,
                        tension: 0.42,
                        borderWidth: 2.5,
                        pointRadius: 3,
                        pointBackgroundColor: '#0ea5e9',
                        pointHoverRadius: 6,
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
                            borderWidth: 1, padding: 12, cornerRadius: 10, displayColors: false,
                            callbacks: { label: function (c) { return c.parsed.y + ' job' + (c.parsed.y === 1 ? '' : 's'); } }
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
                            borderWidth: 1, padding: 12, cornerRadius: 10, displayColors: false
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
        var ctx = document.getElementById('adStatusChart');
        if (!ctx) return;
        if (statusChart) statusChart.destroy();

        var hasData = DATA.status.values.some(function (v) { return v > 0; });
        document.getElementById('adDonutTotal').textContent = DATA.totalApps;

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
                cutout: '74%',
                animation: { animateRotate: true, duration: 1000, easing: 'easeOutQuart' },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: cssVar('--bg-card', '#fff'),
                        titleColor: cssVar('--text', '#1e293b'),
                        bodyColor: cssVar('--text', '#1e293b'),
                        borderColor: cssVar('--border', '#e2e8f0'),
                        borderWidth: 1, padding: 12, cornerRadius: 10,
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

        var legend = document.getElementById('adLegend');
        if (!legend) return;
        var html = '';
        var total = DATA.totalApps || 1;
        DATA.status.labels.forEach(function (name, i) {
            var val = DATA.status.values[i];
            var pct = Math.round(val / total * 100);
            html += '<div class="ad-legend-row">'
                + '<span class="ad-legend-dot" style="background:' + DATA.status.colors[i] + ';"></span>'
                + '<span class="ad-legend-name">' + name + '</span>'
                + '<span class="ad-legend-val">' + val + '</span>'
                + '<span class="ad-legend-pct">' + pct + '%</span>'
                + '</div>';
        });
        legend.innerHTML = html;
    }

    /* Tabs */
    document.querySelectorAll('.ad-tab').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.ad-tab').forEach(function (b) { b.classList.remove('active'); });
            btn.classList.add('active');
            buildMain(btn.dataset.chart);
        });
    });

    /* Rebuild on theme change */
    function rebuildCharts() {
        var active = document.querySelector('.ad-tab.active');
        buildMain(active ? active.dataset.chart : 'apps');
        buildStatus();
    }
    document.addEventListener('click', function (e) {
        if (e.target && e.target.closest && e.target.closest('.btn-theme-toggle')) {
            setTimeout(function () { syncTheme(); rebuildCharts(); }, 60);
        }
    });

    /* Init */
    syncTheme();
    buildMain('apps');
    buildStatus();
})();
</script>

<script>
function adFilter(tableId, inputId) {
    var input = document.getElementById(inputId);
    var filter = input.value.toLowerCase();
    var table = document.getElementById(tableId);
    var rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
    for (var i = 0; i < rows.length; i++) {
        var text = rows[i].textContent.toLowerCase();
        rows[i].style.display = text.indexOf(filter) > -1 ? '' : 'none';
    }
}
</script>

</body>
</html>
