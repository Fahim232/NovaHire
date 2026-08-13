<?php
session_start();
if (!isset($_SESSION['id'])) {
    header('location: login.php');
    exit();
}

include 'header.php';

$user_id = $_SESSION['id'];

$user_q = mysqli_query($con, "SELECT * FROM user_info WHERE id = '$user_id'");
$user = mysqli_fetch_assoc($user_q);

$fields = ['username' => 15, 'email' => 15, 'phone' => 15, 'user_degree' => 20, 'user_skills' => 25, 'profile' => 10];
$completion = 0;
foreach ($fields as $field => $weight) {
    if (!empty($user[$field])) $completion += $weight;
}

$app_q = mysqli_query($con, "SELECT COUNT(*) as cnt FROM job_applications WHERE user_id = '$user_id'");
$total_apps = mysqli_fetch_assoc($app_q)['cnt'];

$total_interviews = 0;
$int_check = mysqli_query($con, "SHOW TABLES LIKE 'interviews'");
if ($int_check && mysqli_num_rows($int_check) > 0) {
    $int_q = mysqli_query($con, "SELECT COUNT(*) as cnt FROM interviews WHERE user_id = '$user_id'");
    $total_interviews = mysqli_fetch_assoc($int_q)['cnt'];
}

$saved_jobs_count = 0;
$sv_check = mysqli_query($con, "SHOW TABLES LIKE 'saved_jobs'");
if ($sv_check && mysqli_num_rows($sv_check) > 0) {
    $sv_q = mysqli_query($con, "SELECT COUNT(*) as cnt FROM saved_jobs WHERE user_id = '$user_id'");
    $saved_jobs_count = mysqli_fetch_assoc($sv_q)['cnt'];
}

$user_skills = [];
if (!empty($user['user_skills'])) {
    $user_skills = array_map('trim', explode(',', $user['user_skills']));
    $user_skills = array_filter($user_skills);
}
$has_user_skills = count($user_skills) > 0;

$rec_jobs = [];
$no_match_jobs = [];

if ($has_user_skills) {
    $all_jobs_q = mysqli_query($con, "SELECT cj.*, c.company_name, c.logo, c.industry
        FROM company_jobs cj
        JOIN companies c ON cj.company_id = c.id
        WHERE cj.status = 'active' AND cj.deadline >= CURDATE()
        ORDER BY cj.posted_date DESC");
    while ($j = mysqli_fetch_assoc($all_jobs_q)) {
        $match = 0;
        $matched_skills = [];
        $job_text = strtolower($j['job_category'] . ' ' . $j['skills_required'] . ' ' . $j['job_title']);
        foreach ($user_skills as $s) {
            $s_lower = strtolower($s);
            if ($s_lower !== '' && preg_match('/\b' . preg_quote($s_lower, '/') . '\b/i', $job_text)) {
                $match++;
                $matched_skills[] = $s;
            }
        }
        $j['match_score'] = $match;
        $j['matched_skills'] = $matched_skills;
        if ($match > 0) {
            $rec_jobs[] = $j;
        } else {
            $no_match_jobs[] = $j;
        }
    }
    usort($rec_jobs, function($a, $b) { return $b['match_score'] <=> $a['match_score']; });
    $rec_jobs = array_slice($rec_jobs, 0, 6);
}

$show_no_match_msg = $has_user_skills && empty($rec_jobs);

$recent_apps_q = mysqli_query($con, "SELECT ja.*, cj.job_title, cj.location, cj.job_category, cj.employment_type,
    c.company_name, c.logo
    FROM job_applications ja
    JOIN company_jobs cj ON ja.job_id = cj.id
    JOIN companies c ON cj.company_id = c.id
    WHERE ja.user_id = '$user_id'
    ORDER BY ja.applied_date DESC LIMIT 5");

$interviews_list_q = null;
if ($int_check && mysqli_num_rows($int_check) > 0) {
    $interviews_list_q = mysqli_query($con, "SELECT i.*, c.company_name, c.logo, cj.job_title 
        FROM interviews i 
        JOIN companies c ON i.company_id = c.id 
        JOIN company_jobs cj ON i.job_id = cj.id 
        WHERE i.user_id = '$user_id' AND i.status = 'scheduled' 
        ORDER BY i.interview_date ASC, i.interview_time ASC LIMIT 3");
}

function statusBadge($status) {
    $map = [
        'pending' => ['bg' => '#fef3c7', 'color' => '#92400e', 'icon' => 'fa-clock'],
        'reviewed' => ['bg' => '#dbeafe', 'color' => '#1e40af', 'icon' => 'fa-eye'],
        'shortlisted' => ['bg' => '#dcfce7', 'color' => '#166534', 'icon' => 'fa-check-circle'],
        'rejected' => ['bg' => '#fee2e2', 'color' => '#991b1b', 'icon' => 'fa-times-circle'],
    ];
    $s = isset($map[$status]) ? $map[$status] : ['bg' => '#f1f5f9', 'color' => '#475569', 'icon' => 'fa-question'];
    return '<span style="background:' . $s['bg'] . ';color:' . $s['color'] . ';padding:4px 12px;border-radius:20px;font-size:0.75rem;font-weight:600;display:inline-flex;align-items:center;gap:4px;"><i class="fas ' . $s['icon'] . '" style="font-size:0.65rem;"></i>' . ucfirst($status) . '</span>';
}
?>

<style>
    /* ═══════════════════════════════════════════
       DASHBOARD — MODERN RESPONSIVE STYLES
       ═══════════════════════════════════════════ */

    /* ── Hero Section ── */
    .dash-hero {
        background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 50%, #a855f7 100%);
        padding: 76px 0 90px;
        margin-top: -80px;
        position: relative;
        overflow: hidden;
    }
    .dash-hero::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -20%;
        width: 600px;
        height: 600px;
        background: radial-gradient(circle, rgba(255,255,255,0.07) 0%, transparent 70%);
        border-radius: 50%;
        animation: heroFloat 8s ease-in-out infinite;
    }
    .dash-hero::after {
        content: '';
        position: absolute;
        bottom: -40%;
        left: -10%;
        width: 450px;
        height: 450px;
        background: radial-gradient(circle, rgba(236,72,153,0.12) 0%, transparent 70%);
        border-radius: 50%;
        animation: heroFloat 10s ease-in-out infinite reverse;
    }
    @keyframes heroFloat {
        0%, 100% { transform: translateY(0) scale(1); }
        50% { transform: translateY(-20px) scale(1.05); }
    }
    .dash-welcome { position: relative; z-index: 2; }
    .dash-welcome h1 {
        color: white;
        font-size: 2.2rem;
        font-weight: 800;
        margin-bottom: 6px;
        letter-spacing: -0.5px;
        line-height: 1.2;
        text-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    .dash-welcome .dash-subtitle {
        color: rgba(255,255,255,0.85);
        font-size: 1.05rem;
        font-weight: 400;
        margin: 0;
    }
    .dash-hero-bottom { position: relative; z-index: 2; margin-top: 28px; }

    /* ── Profile Completion Ring ── */
    .profile-ring-wrap {
        position: relative;
        width: 100px;
        height: 100px;
        flex-shrink: 0;
    }
    .profile-ring-svg {
        transform: rotate(-90deg);
        width: 100px;
        height: 100px;
    }
    .profile-ring-bg {
        fill: none;
        stroke: rgba(255,255,255,0.2);
        stroke-width: 6;
    }
    .profile-ring-fill {
        fill: none;
        stroke: #fbbf24;
        stroke-width: 6;
        stroke-linecap: round;
        transition: stroke-dashoffset 1.2s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .profile-ring-text {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        text-align: center;
    }
    .profile-ring-text .pct {
        color: white;
        font-size: 1.3rem;
        font-weight: 800;
        line-height: 1;
        display: block;
    }
    .profile-ring-text .lbl {
        color: rgba(255,255,255,0.7);
        font-size: 0.6rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .profile-avatar-hero {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        border: 3px solid rgba(255,255,255,0.3);
        object-fit: cover;
    }
    .profile-avatar-placeholder {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        border: 3px solid rgba(255,255,255,0.3);
        background: rgba(255,255,255,0.15);
        display: flex;
        align-items: center;
        justify-content: center;
        color: rgba(255,255,255,0.7);
        font-size: 1.4rem;
    }
    .completion-info-block {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .completion-info-block .label {
        color: rgba(255,255,255,0.9);
        font-weight: 600;
        font-size: 0.95rem;
    }
    .completion-link {
        color: #fbbf24;
        font-weight: 600;
        text-decoration: none;
        font-size: 0.88rem;
        transition: var(--transition);
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .completion-link:hover { color: #fde68a; gap: 8px; text-decoration: none; }
    .completion-done {
        color: #34d399;
        font-weight: 600;
        font-size: 0.88rem;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }

    /* ── Stat Cards ── */
    .stat-card {
        background: var(--bg-card);
        border-radius: var(--radius-lg);
        padding: 22px 20px;
        border: 1px solid var(--border-light);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        align-items: center;
        gap: 16px;
        text-decoration: none;
        position: relative;
        overflow: hidden;
        box-shadow: var(--shadow-sm);
    }
    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 4px;
        height: 100%;
        border-radius: 0 4px 4px 0;
        opacity: 0;
        transition: opacity 0.3s;
    }
    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--shadow-lg);
        text-decoration: none;
    }
    .stat-card:hover::before { opacity: 1; }
    .stat-icon {
        width: 54px;
        height: 54px;
        border-radius: var(--radius-md);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        flex-shrink: 0;
        transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    }
    .stat-card:hover .stat-icon { transform: scale(1.1) rotate(-3deg); }
    .stat-info h3 {
        font-size: 1.7rem;
        font-weight: 800;
        color: var(--text);
        margin: 0;
        line-height: 1;
    }
    .stat-info p {
        color: var(--text-muted);
        font-size: 0.85rem;
        font-weight: 500;
        margin: 5px 0 0;
        letter-spacing: 0.2px;
    }
    .stat-card.stat-apps::before { background: var(--primary); }
    .stat-card.stat-interviews::before { background: #f59e0b; }
    .stat-card.stat-saved::before { background: #ec4899; }
    .stat-card.stat-profile::before { background: var(--success); }

    /* ── Quick Actions ── */
    .action-card {
        background: var(--bg-card);
        border-radius: var(--radius-lg);
        padding: 28px 16px;
        text-align: center;
        border: 1px solid var(--border-light);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        text-decoration: none;
        display: flex;
        flex-direction: column;
        align-items: center;
        position: relative;
        overflow: hidden;
        box-shadow: var(--shadow-sm);
    }
    .action-card::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 50%;
        transform: translateX(-50%);
        width: 0;
        height: 3px;
        border-radius: 3px 3px 0 0;
        transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .action-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--shadow-lg);
        text-decoration: none;
    }
    .action-card:hover::after { width: 60%; }
    .action-icon {
        width: 56px;
        height: 56px;
        border-radius: var(--radius-md);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        margin: 0 auto 14px;
        transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    }
    .action-card:hover .action-icon { transform: scale(1.15) translateY(-3px); }
    .action-card h6 { font-weight: 700; color: var(--text); margin: 0 0 3px; font-size: 0.95rem; }
    .action-card small { color: var(--text-muted); font-size: 0.8rem; font-weight: 500; }
    .action-browse::after { background: var(--primary); }
    .action-profile::after { background: #ec4899; }
    .action-saved::after { background: #f59e0b; }
    .action-apps::after { background: var(--success); }

    /* ── Section Title ── */
    .section-title {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 20px;
    }
    .section-title h4 {
        font-size: 1.2rem;
        font-weight: 700;
        color: var(--text);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .section-title h4 i {
        width: 34px;
        height: 34px;
        border-radius: var(--radius-sm);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.85rem;
    }
    .section-title a {
        color: var(--primary);
        font-weight: 600;
        font-size: 0.88rem;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        transition: var(--transition);
        text-decoration: none;
    }
    .section-title a:hover { gap: 8px; color: var(--primary-dark); }

    /* ── Category Pills ── */
    .cat-scroll {
        display: flex;
        gap: 10px;
        overflow-x: auto;
        padding-bottom: 10px;
        scroll-snap-type: x mandatory;
        -webkit-overflow-scrolling: touch;
    }
    .cat-scroll::-webkit-scrollbar { height: 5px; }
    .cat-scroll::-webkit-scrollbar-track { background: var(--border-light); border-radius: 5px; }
    .cat-scroll::-webkit-scrollbar-thumb { background: var(--border); border-radius: 5px; }
    .cat-pill {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 9px 20px;
        border-radius: var(--radius-full);
        font-size: 0.88rem;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        border: 1.5px solid transparent;
        white-space: nowrap;
        scroll-snap-align: start;
        flex-shrink: 0;
    }
    .cat-pill:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
        text-decoration: none;
        filter: brightness(0.93);
    }
    .cat-pill .cat-count {
        font-size: 0.68rem;
        opacity: 0.7;
        font-weight: 700;
    }

    /* ── Recommended Job Cards ── */
    .rec-job-card {
        background: var(--bg-card);
        border-radius: var(--radius-lg);
        padding: 26px;
        border: 1px solid var(--border-light);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        flex-direction: column;
        height: 100%;
        position: relative;
        box-shadow: var(--shadow-sm);
    }
    .rec-job-card:hover {
        transform: translateY(-6px);
        box-shadow: var(--shadow-xl);
        border-color: var(--primary);
    }
    .rec-job-header {
        display: flex;
        align-items: flex-start;
        gap: 14px;
        margin-bottom: 16px;
    }
    .rec-job-logo {
        width: 52px;
        height: 52px;
        border-radius: var(--radius-md);
        object-fit: contain;
        border: 1px solid var(--border-light);
        padding: 5px;
        background: var(--bg);
        flex-shrink: 0;
    }
    .rec-job-logo-placeholder {
        width: 52px;
        height: 52px;
        border-radius: var(--radius-md);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        flex-shrink: 0;
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        color: white;
    }
    .rec-job-header-text {
        flex: 1;
        min-width: 0;
    }
    .rec-job-card h6 {
        font-weight: 700;
        color: var(--text);
        margin: 0 0 3px;
        font-size: 1rem;
        line-height: 1.3;
    }
    .rec-job-card .company {
        color: var(--primary);
        font-weight: 600;
        font-size: 0.85rem;
        display: flex;
        align-items: center;
        gap: 4px;
    }
    .match-badge {
        background: linear-gradient(135deg, #dcfce7, #bbf7d0);
        color: #166534;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.72rem;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 3px;
        flex-shrink: 0;
        border: 1px solid rgba(22,101,52,0.1);
    }
    .rec-job-meta {
        display: flex;
        gap: 16px;
        flex-wrap: wrap;
        margin-top: 12px;
    }
    .rec-job-meta span {
        display: flex;
        align-items: center;
        gap: 5px;
        color: var(--text-muted);
        font-size: 0.85rem;
        font-weight: 500;
    }
    .rec-job-meta span i { color: var(--text-light); font-size: 0.78rem; }
    .rec-job-salary {
        font-weight: 700;
        color: var(--success);
        font-size: 0.95rem;
        margin-top: 10px;
        display: flex;
        align-items: center;
        gap: 5px;
    }
    .rec-job-tags {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
        margin-top: 12px;
    }
    .rec-job-tags span {
        background: var(--bg-hover);
        color: var(--text-muted);
        padding: 5px 11px;
        border-radius: 8px;
        font-size: 0.75rem;
        font-weight: 600;
        border: 1px solid var(--border-light);
    }
    .rec-job-tags span.matched {
        background: #dcfce7;
        color: #166534;
        border-color: rgba(22,101,52,0.1);
    }
    .btn-rec-apply {
        background: var(--primary);
        color: white;
        border: none;
        padding: 12px 20px;
        border-radius: var(--radius-sm);
        font-weight: 600;
        font-size: 0.9rem;
        cursor: pointer;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        margin-top: auto;
        width: 100%;
    }
    .btn-rec-apply:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(79, 70, 229, 0.35);
        color: white;
        text-decoration: none;
    }
    .btn-rec-quiz {
        background: linear-gradient(135deg, #f59e0b, #d97706);
        color: white;
        border: none;
        padding: 12px 20px;
        border-radius: var(--radius-sm);
        font-weight: 600;
        font-size: 0.9rem;
        cursor: pointer;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        margin-top: auto;
        width: 100%;
    }
    .btn-rec-quiz:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(245, 158, 11, 0.35);
        color: white;
        text-decoration: none;
    }

    /* ── Recent Applications ── */
    .recent-app-row {
        display: flex;
        align-items: center;
        gap: 16px;
        background: var(--bg-card);
        border-radius: var(--radius-lg);
        padding: 18px 22px;
        border: 1px solid var(--border-light);
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        margin-bottom: 12px;
        position: relative;
        overflow: hidden;
        box-shadow: var(--shadow-xs);
    }
    .recent-app-row::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        width: 4px;
        height: 100%;
        border-radius: 4px 0 0 4px;
    }
    .recent-app-row.status-pending::before { background: #f59e0b; }
    .recent-app-row.status-reviewed::before { background: #3b82f6; }
    .recent-app-row.status-shortlisted::before { background: #10b981; }
    .recent-app-row.status-rejected::before { background: #ef4444; }
    .recent-app-row:hover {
        transform: translateX(4px);
        box-shadow: var(--shadow-md);
    }
    .recent-app-logo {
        width: 48px;
        height: 48px;
        border-radius: var(--radius-md);
        object-fit: contain;
        border: 1px solid var(--border-light);
        padding: 5px;
        background: var(--bg);
        flex-shrink: 0;
    }
    .recent-app-logo-placeholder {
        width: 48px;
        height: 48px;
        border-radius: var(--radius-md);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        flex-shrink: 0;
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        color: white;
    }
    .recent-app-info { flex: 1; min-width: 0; }
    .recent-app-title {
        font-weight: 700;
        color: var(--text);
        font-size: 0.95rem;
        line-height: 1.3;
    }
    .recent-app-company {
        color: var(--primary);
        font-weight: 600;
        font-size: 0.85rem;
        margin-top: 2px;
    }
    .recent-app-meta {
        color: var(--text-light);
        font-size: 0.8rem;
        margin-top: 4px;
        font-weight: 500;
    }

    /* ── Empty State ── */
    .empty-state {
        text-align: center;
        padding: 52px 28px;
        background: var(--bg-card);
        border-radius: var(--radius-lg);
        border: 2px dashed var(--border);
    }
    .empty-state i {
        font-size: 2.8rem;
        color: var(--border);
        margin-bottom: 16px;
        display: block;
    }
    .empty-state p {
        color: var(--text-muted);
        font-weight: 500;
        margin: 0 0 16px;
        font-size: 1rem;
    }
    .empty-state a {
        color: var(--primary);
        font-weight: 600;
        font-size: 0.92rem;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
    .empty-state a:hover { gap: 8px; text-decoration: none; }

    /* ── Footer ── */
    .site-footer {
        background: var(--dark);
        color: #94a3b8;
        padding: 48px 0 24px;
        margin-top: 60px;
    }
    .footer-brand { font-size: 1.3rem; font-weight: 800; color: white; margin-bottom: 10px; }
    .footer-brand span { color: var(--primary); }
    .footer-link {
        color: #94a3b8;
        font-size: 0.88rem;
        text-decoration: none;
        transition: var(--transition);
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .footer-link:hover { color: white; text-decoration: none; gap: 6px; }
    .footer-heading {
        color: white;
        font-weight: 700;
        font-size: 0.92rem;
        margin-bottom: 16px;
    }
    .footer-bottom {
        border-top: 1px solid rgba(255,255,255,0.06);
        margin-top: 28px;
        padding-top: 18px;
        text-align: center;
    }
    .footer-bottom p { color: #64748b; font-size: 0.85rem; margin: 0; }

    /* ── RESPONSIVE — TABLET ── */
    @media (max-width: 991px) {
        .dash-hero { padding: 72px 0 75px; }
        .dash-welcome h1 { font-size: 1.8rem; }
        .stat-card { padding: 20px 18px; }
        .stat-icon { width: 50px; height: 50px; font-size: 1.15rem; }
        .stat-info h3 { font-size: 1.5rem; }
        .stat-info p { font-size: 0.82rem; }
    }

    /* ── RESPONSIVE — LANDSCAPE MOBILE ── */
    @media (max-width: 767px) {
        .dash-hero { padding: 64px 0 65px; margin-top: -60px; }
        .dash-welcome h1 { font-size: 1.5rem; }
        .dash-welcome .dash-subtitle { font-size: 0.95rem; }
        .profile-ring-wrap { width: 82px; height: 82px; }
        .profile-ring-svg { width: 82px; height: 82px; }
        .profile-ring-text .pct { font-size: 1.1rem; }
        .profile-avatar-hero { width: 52px; height: 52px; }
        .profile-avatar-placeholder { width: 52px; height: 52px; font-size: 1.2rem; }
        .section-title h4 { font-size: 1.1rem; }
        .action-card { padding: 22px 14px; }
        .action-card h6 { font-size: 0.88rem; }
        .action-card small { font-size: 0.78rem; }
        .rec-job-card { padding: 20px; }
        .rec-job-card h6 { font-size: 0.95rem; }
        .rec-job-meta span { font-size: 0.8rem; }
        .recent-app-row { padding: 16px 18px; gap: 14px; }
        .recent-app-title { font-size: 0.9rem; }
        .recent-app-company { font-size: 0.82rem; }
        .empty-state { padding: 40px 20px; }
        .empty-state p { font-size: 0.95rem; }
    }

    /* ── RESPONSIVE — PORTRAIT MOBILE ── */
    @media (max-width: 575px) {
        .dash-hero { padding: 58px 0 55px; margin-top: -50px; }
        .dash-welcome h1 { font-size: 1.35rem; }
        .dash-welcome .dash-subtitle { font-size: 0.88rem; }
        .profile-ring-wrap { width: 72px; height: 72px; }
        .profile-ring-svg { width: 72px; height: 72px; }
        .profile-ring-text .pct { font-size: 0.95rem; }
        .profile-ring-text .lbl { font-size: 0.5rem; }
        .stat-card { padding: 16px 14px; gap: 12px; border-radius: 12px; }
        .stat-icon { width: 44px; height: 44px; font-size: 1rem; border-radius: 10px; }
        .stat-info h3 { font-size: 1.25rem; }
        .stat-info p { font-size: 0.78rem; }
        .action-card { padding: 18px 12px; border-radius: 12px; }
        .action-icon { width: 48px; height: 48px; font-size: 1.1rem; }
        .action-card h6 { font-size: 0.82rem; }
        .action-card small { font-size: 0.73rem; }
        .section-title h4 { font-size: 1rem; }
        .section-title h4 i { width: 30px; height: 30px; font-size: 0.78rem; }
        .section-title a { font-size: 0.82rem; }
        .cat-pill { padding: 8px 16px; font-size: 0.82rem; }
        .rec-job-card { padding: 18px; border-radius: 14px; }
        .rec-job-header { gap: 10px; margin-bottom: 12px; }
        .rec-job-logo, .rec-job-logo-placeholder { width: 44px; height: 44px; }
        .rec-job-card h6 { font-size: 0.9rem; }
        .rec-job-card .company { font-size: 0.8rem; }
        .rec-job-meta { gap: 10px; }
        .rec-job-meta span { font-size: 0.78rem; }
        .rec-job-salary { font-size: 0.88rem; }
        .rec-job-tags span { font-size: 0.7rem; padding: 4px 9px; }
        .btn-rec-apply, .btn-rec-quiz { padding: 11px 16px; font-size: 0.85rem; }
        .recent-app-row { padding: 14px 14px; gap: 12px; border-radius: 12px; }
        .recent-app-logo, .recent-app-logo-placeholder { width: 42px; height: 42px; }
        .recent-app-title { font-size: 0.85rem; }
        .recent-app-company { font-size: 0.78rem; }
        .recent-app-meta { font-size: 0.73rem; }
        .empty-state { padding: 36px 16px; border-radius: 14px; }
        .empty-state i { font-size: 2.2rem; }
        .empty-state p { font-size: 0.9rem; }
        .footer-brand { font-size: 1.15rem; }
        .footer-heading { font-size: 0.85rem; }
        .footer-link { font-size: 0.82rem; }
        .footer-bottom p { font-size: 0.8rem; }
    }

    /* ── VERY SMALL SCREENS ── */
    @media (max-width: 374px) {
        .dash-hero { padding: 54px 0 50px; }
        .dash-welcome h1 { font-size: 1.2rem; }
        .stat-info h3 { font-size: 1.15rem; }
        .stat-info p { font-size: 0.73rem; }
        .action-card h6 { font-size: 0.78rem; }
        .rec-job-card h6 { font-size: 0.85rem; }
    }

    /* ── Animations ── */
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .anim-fade-in { animation: fadeInUp 0.5s ease-out both; }
    .anim-delay-1 { animation-delay: 0.1s; }
    .anim-delay-2 { animation-delay: 0.2s; }
    .anim-delay-3 { animation-delay: 0.3s; }
    .anim-delay-4 { animation-delay: 0.4s; }

    /* ── Success Toast ── */
    .app-toast {
        position: fixed;
        top: 90px;
        right: 24px;
        z-index: 9999;
        max-width: 440px;
        width: calc(100% - 48px);
        background: white;
        border-radius: 16px;
        box-shadow: 0 12px 40px rgba(0,0,0,0.15);
        border-left: 5px solid #10b981;
        padding: 20px 24px;
        display: flex;
        align-items: flex-start;
        gap: 14px;
        transform: translateX(120%);
        transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
    }
    .app-toast.show { transform: translateX(0); }
    .app-toast-icon {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        background: #d1fae5;
        color: #059669;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        flex-shrink: 0;
    }
    .app-toast-content { flex: 1; }
    .app-toast-content strong {
        display: block;
        font-size: 0.95rem;
        font-weight: 700;
        color: var(--text);
        margin-bottom: 4px;
    }
    .app-toast-content p {
        margin: 0;
        font-size: 0.85rem;
        color: var(--text-muted);
        line-height: 1.5;
    }
    .app-toast-close {
        background: none;
        border: none;
        color: var(--text-light);
        font-size: 1.1rem;
        cursor: pointer;
        padding: 0;
        flex-shrink: 0;
        transition: color 0.2s;
    }
    .app-toast-close:hover { color: var(--text); }
</style>

<?php if (!empty($_SESSION['app_success_msg'])): ?>
<div class="app-toast" id="appToast">
    <div class="app-toast-icon"><i class="fas fa-check"></i></div>
    <div class="app-toast-content">
        <strong>Application Submitted!</strong>
        <p><?php echo $_SESSION['app_success_msg']; ?></p>
    </div>
    <button class="app-toast-close" onclick="closeToast()"><i class="fas fa-times"></i></button>
</div>
<script>
function closeToast() {
    document.getElementById('appToast').classList.remove('show');
}
setTimeout(function() {
    var toast = document.getElementById('appToast');
    if (toast) toast.classList.add('show');
}, 300);
setTimeout(function() {
    closeToast();
}, 8000);
</script>
<?php unset($_SESSION['app_success_msg']); endif; ?>

<!-- Welcome Hero -->
<div class="dash-hero">
    <div class="container dash-welcome">
        <div class="row align-items-center">
            <div class="col-lg-8 col-md-9">
                <div class="d-flex align-items-center gap-4 mb-3">
                    <?php if (!empty($user['profile'])): ?>
                        <img src="images/<?php echo htmlspecialchars($user['profile']); ?>" alt="Profile" class="profile-avatar-hero">
                    <?php else: ?>
                        <div class="profile-avatar-placeholder">
                            <i class="fas fa-user"></i>
                        </div>
                    <?php endif; ?>
                    <div>
                        <h1>Welcome back, <?php echo htmlspecialchars($user['username']); ?>!</h1>
                        <p class="dash-subtitle">Here's what's happening with your job search today.</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="dash-hero-bottom">
            <div class="row align-items-center">
                <div class="col-lg-6 col-md-7">
                    <div class="d-flex align-items-center gap-4">
                        <?php
                        $circumference = 2 * 3.14159 * 40;
                        $offset = $circumference - ($completion / 100) * $circumference;
                        ?>
                        <div class="profile-ring-wrap">
                            <svg class="profile-ring-svg" viewBox="0 0 100 100">
                                <circle class="profile-ring-bg" cx="50" cy="50" r="40"/>
                                <circle class="profile-ring-fill" cx="50" cy="50" r="40"
                                    stroke-dasharray="<?php echo $circumference; ?>"
                                    stroke-dashoffset="<?php echo $offset; ?>"/>
                            </svg>
                            <div class="profile-ring-text">
                                <span class="pct"><?php echo $completion; ?>%</span>
                                <span class="lbl">Done</span>
                            </div>
                        </div>
                        <div class="completion-info-block">
                            <span class="label">Profile Completion</span>
                            <?php if ($completion < 100): ?>
                                <a href="profile.php" class="completion-link">Complete your profile <i class="fas fa-arrow-right" style="font-size:0.7rem;"></i></a>
                            <?php else: ?>
                                <span class="completion-done"><i class="fas fa-check-circle"></i> Profile Complete</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container" style="margin-top: 24px;">

    <!-- Stats Cards -->
    <div class="row mb-4" style="margin-top: -40px;">
        <div class="col-lg-3 col-md-6 col-6 mb-3 anim-fade-in anim-delay-1">
            <a href="my_application.php" class="stat-card stat-apps">
                <div class="stat-icon" style="background: rgba(79,70,229,0.1); color: var(--primary);"><i class="fas fa-paper-plane"></i></div>
                <div class="stat-info">
                    <h3><?php echo $total_apps; ?></h3>
                    <p>Applications</p>
                </div>
            </a>
        </div>
        <div class="col-lg-3 col-md-6 col-6 mb-3 anim-fade-in anim-delay-2">
            <div class="stat-card stat-interviews">
                <div class="stat-icon" style="background: rgba(245,158,11,0.1); color: #d97706;"><i class="fas fa-calendar-check"></i></div>
                <div class="stat-info">
                    <h3><?php echo $total_interviews; ?></h3>
                    <p>Interviews</p>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 col-6 mb-3 anim-fade-in anim-delay-3">
            <div class="stat-card stat-saved">
                <div class="stat-icon" style="background: rgba(236,72,153,0.1); color: #db2777;"><i class="fas fa-bookmark"></i></div>
                <div class="stat-info">
                    <h3><?php echo $saved_jobs_count; ?></h3>
                    <p>Saved Jobs</p>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 col-6 mb-3 anim-fade-in anim-delay-4">
            <div class="stat-card stat-profile">
                <div class="stat-icon" style="background: rgba(16,185,129,0.1); color: var(--success);"><i class="fas fa-user-check"></i></div>
                <div class="stat-info">
                    <h3><?php echo $completion; ?>%</h3>
                    <p>Profile Done</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="mb-4 anim-fade-in">
        <div class="section-title">
            <h4><i class="fas fa-bolt" style="background: rgba(245,158,11,0.1); color: #d97706;"></i>Quick Actions</h4>
        </div>
        <div class="row">
            <div class="col-lg-3 col-md-6 col-6 mb-3">
                <a href="browse_jobs.php" class="action-card action-browse">
                    <div class="action-icon" style="background: rgba(79,70,229,0.1); color: var(--primary);"><i class="fas fa-search"></i></div>
                    <h6>Browse Jobs</h6>
                    <small>Find opportunities</small>
                </a>
            </div>
            <div class="col-lg-3 col-md-6 col-6 mb-3">
                <a href="profile.php" class="action-card action-profile">
                    <div class="action-icon" style="background: rgba(236,72,153,0.1); color: #db2777;"><i class="fas fa-user-edit"></i></div>
                    <h6>My Profile</h6>
                    <small>Edit your details</small>
                </a>
            </div>
            <div class="col-lg-3 col-md-6 col-6 mb-3">
                <a href="saved_jobs.php" class="action-card action-saved">
                    <div class="action-icon" style="background: rgba(245,158,11,0.1); color: #d97706;"><i class="fas fa-heart"></i></div>
                    <h6>Saved Jobs</h6>
                    <small>Your bookmarks</small>
                </a>
            </div>
            <div class="col-lg-3 col-md-6 col-6 mb-3">
                <a href="my_application.php" class="action-card action-apps">
                    <div class="action-icon" style="background: rgba(16,185,129,0.1); color: var(--success);"><i class="fas fa-file-alt"></i></div>
                    <h6>Applications</h6>
                    <small>Track progress</small>
                </a>
            </div>
        </div>
    </div>

    <!-- Browse by Category -->
    <div class="mb-4 anim-fade-in">
        <div class="section-title">
            <h4><i class="fas fa-th-large" style="background: rgba(79,70,229,0.1); color: var(--primary);"></i>Browse by Category</h4>
            <a href="browse_jobs.php">View All <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="cat-scroll">
            <?php
            $all_cats_q = mysqli_query($con, "SELECT DISTINCT job_category, COUNT(*) as cnt FROM company_jobs WHERE status='active' GROUP BY job_category ORDER BY cnt DESC");
            $pill_styles = [
                'PHP' => ['bg' => '#eef2ff', 'color' => '#4f46e5'],
                'Java' => ['bg' => '#fef2f2', 'color' => '#dc2626'],
                'Python' => ['bg' => '#eff6ff', 'color' => '#2563eb'],
                'Frontend' => ['bg' => '#fff7ed', 'color' => '#ea580c'],
                'Finance' => ['bg' => '#ecfdf5', 'color' => '#059669'],
                'Healthcare' => ['bg' => '#fef2f2', 'color' => '#dc2626'],
                'Education' => ['bg' => '#eff6ff', 'color' => '#2563eb'],
                'Engineering' => ['bg' => '#f5f3ff', 'color' => '#7c3aed'],
                'Sales' => ['bg' => '#fff7ed', 'color' => '#ea580c'],
                'HR' => ['bg' => '#fdf2f8', 'color' => '#db2777'],
                'Legal' => ['bg' => '#fefce8', 'color' => '#ca8a04'],
                'Media' => ['bg' => '#f0fdf4', 'color' => '#16a34a'],
                'Logistics' => ['bg' => '#fff1f2', 'color' => '#e11d48'],
                'Consulting' => ['bg' => '#ecfdf5', 'color' => '#059669'],
                'Retail' => ['bg' => '#f0f9ff', 'color' => '#0284c7'],
                'UI/UX' => ['bg' => '#fdf2f8', 'color' => '#db2777'],
                'JavaScript' => ['bg' => '#fefce8', 'color' => '#ca8a04'],
                'DB' => ['bg' => '#f0f9ff', 'color' => '#0284c7'],
                'DataScience' => ['bg' => '#f0fdf4', 'color' => '#16a34a'],
                'Marketing' => ['bg' => '#fff1f2', 'color' => '#e11d48'],
            ];
            while ($cat = mysqli_fetch_assoc($all_cats_q)):
                $style = isset($pill_styles[$cat['job_category']]) ? $pill_styles[$cat['job_category']] : ['bg' => '#f1f5f9', 'color' => '#64748b'];
            ?>
                <a href="browse_jobs.php?category=<?php echo urlencode($cat['job_category']); ?>" class="cat-pill" style="background: <?php echo $style['bg']; ?>; color: <?php echo $style['color']; ?>;">
                    <?php echo htmlspecialchars($cat['job_category']); ?>
                    <span class="cat-count"><?php echo $cat['cnt']; ?></span>
                </a>
            <?php endwhile; ?>
        </div>
    </div>

    <!-- Recommended Jobs -->
    <div class="mb-4">
        <div class="section-title">
            <h4><i class="fas fa-star" style="background: rgba(245,158,11,0.1); color: #d97706;"></i><?php echo $has_user_skills ? 'Jobs Matching Your Skills' : 'Recommended Jobs'; ?></h4>
            <a href="browse_jobs.php">View All <i class="fas fa-arrow-right"></i></a>
        </div>

        <?php if ($show_no_match_msg): ?>
            <div class="empty-state">
                <i class="fas fa-search"></i>
                <p>No active jobs currently match your selected skills.</p>
                <a href="browse_jobs.php">Browse All Jobs <i class="fas fa-arrow-right"></i></a>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($rec_jobs as $job):
                    $has_quiz = false;
                    $quiz_q = mysqli_query($con, "SELECT COUNT(*) as cnt FROM company_job_questions WHERE job_id = " . intval($job['id']));
                    $has_quiz = mysqli_fetch_assoc($quiz_q)['cnt'] > 0;
                ?>
                <div class="col-xl-4 col-lg-6 col-md-6 mb-3">
                    <div class="rec-job-card">
                        <div class="rec-job-header">
                            <?php if (!empty($job['logo']) && file_exists('uploads/company_logos/' . $job['logo'])): ?>
                                <img src="uploads/company_logos/<?php echo htmlspecialchars($job['logo']); ?>" class="rec-job-logo" alt="">
                            <?php else: ?>
                                <div class="rec-job-logo-placeholder"><i class="fas fa-building"></i></div>
                            <?php endif; ?>
                            <div class="rec-job-header-text">
                                <h6><?php echo htmlspecialchars($job['job_title']); ?></h6>
                                <div class="company"><i class="fas fa-building"></i> <?php echo htmlspecialchars($job['company_name']); ?></div>
                            </div>
                            <?php if ($job['match_score'] > 0): ?>
                                <span class="match-badge"><i class="fas fa-check"></i> <?php echo $job['match_score']; ?> match<?php echo $job['match_score'] > 1 ? 'es' : ''; ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="rec-job-meta">
                            <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($job['location']); ?></span>
                            <span><i class="fas fa-briefcase"></i> <?php echo $job['employment_type']; ?></span>
                        </div>
                        <?php if (!empty($job['salary_range'])): ?>
                            <div class="rec-job-salary"><i class="fas fa-dollar-sign"></i> <?php echo htmlspecialchars($job['salary_range']); ?></div>
                        <?php endif; ?>
                        <?php if (!empty($job['matched_skills'])): ?>
                            <div class="rec-job-tags">
                                <?php foreach (array_slice($job['matched_skills'], 0, 4) as $ms): ?>
                                    <span class="matched"><i class="fas fa-check" style="font-size: 0.55rem;"></i> <?php echo htmlspecialchars($ms); ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="rec-job-tags">
                                <?php
                                $skills = array_slice(explode(',', $job['skills_required']), 0, 3);
                                foreach ($skills as $skill):
                                ?>
                                    <span><?php echo trim($skill); ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <div class="mt-auto" style="padding-top: 16px;">
                            <?php if ($has_quiz): ?>
                                <a href="job_details.php?id=<?php echo $job['id']; ?>" class="btn-rec-quiz">
                                    <i class="fas fa-clipboard-check"></i> Take Quiz to Apply
                                </a>
                            <?php else: ?>
                                <a href="job_details.php?id=<?php echo $job['id']; ?>" class="btn-rec-apply">
                                    <i class="fas fa-paper-plane"></i> Apply Now
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Scheduled Interviews -->
    <?php if ($interviews_list_q && mysqli_num_rows($interviews_list_q) > 0): ?>
    <div class="mb-4">
        <div class="section-title">
            <h4><i class="fas fa-video" style="background: rgba(16,185,129,0.1); color: var(--success);"></i>Scheduled Interviews</h4>
        </div>
        <div class="row">
            <?php while ($int = mysqli_fetch_assoc($interviews_list_q)): ?>
            <div class="col-md-6 mb-3">
                <div class="recent-app-row status-shortlisted">
                    <?php if (!empty($int['logo']) && file_exists('uploads/company_logos/' . $int['logo'])): ?>
                        <img src="uploads/company_logos/<?php echo htmlspecialchars($int['logo']); ?>" class="recent-app-logo" alt="">
                    <?php else: ?>
                        <div class="recent-app-logo-placeholder"><i class="fas fa-building"></i></div>
                    <?php endif; ?>
                    <div class="recent-app-info" style="flex: 1;">
                        <div class="recent-app-title"><?php echo htmlspecialchars($int['job_title']); ?></div>
                        <div class="recent-app-company"><?php echo htmlspecialchars($int['company_name']); ?> • <?php echo htmlspecialchars($int['interview_type']); ?></div>
                        <div style="font-size: 0.8rem; color: #64748b; margin-top: 4px;">
                            <i class="fas fa-calendar-day"></i> <?php echo date('M d, Y', strtotime($int['interview_date'])); ?> &nbsp;
                            <i class="fas fa-clock"></i> <?php echo date('g:i A', strtotime($int['interview_time'])); ?>
                        </div>
                    </div>
                    <?php if ($int['interview_type'] == 'Online' && !empty($int['meeting_link'])): ?>
                        <a href="video_interview.php?room=<?php echo urlencode($int['meeting_link']); ?>" target="_blank" class="btn btn-sm btn-success" style="border-radius: 20px; padding: 6px 15px;">
                            <i class="fas fa-video"></i> Join
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Recent Applications -->
    <div class="mb-4">
        <div class="section-title">
            <h4><i class="fas fa-history" style="background: rgba(79,70,229,0.1); color: var(--primary);"></i>Recent Applications</h4>
            <a href="my_application.php">View All <i class="fas fa-arrow-right"></i></a>
        </div>
        <?php if (mysqli_num_rows($recent_apps_q) > 0): ?>
            <?php while ($app = mysqli_fetch_assoc($recent_apps_q)): ?>
                <div class="recent-app-row status-<?php echo htmlspecialchars($app['application_status']); ?>">
                    <?php if (!empty($app['logo']) && file_exists('uploads/company_logos/' . $app['logo'])): ?>
                        <img src="uploads/company_logos/<?php echo htmlspecialchars($app['logo']); ?>" class="recent-app-logo" alt="">
                    <?php else: ?>
                        <div class="recent-app-logo-placeholder"><i class="fas fa-building"></i></div>
                    <?php endif; ?>
                    <div class="recent-app-info">
                        <div class="recent-app-title"><?php echo htmlspecialchars($app['job_title']); ?></div>
                        <div class="recent-app-company"><?php echo htmlspecialchars($app['company_name']); ?></div>
                        <div class="recent-app-meta">
                            <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($app['location']); ?>
                            &nbsp;&bull;&nbsp;
                            <?php echo date('M d, Y', strtotime($app['applied_date'])); ?>
                        </div>
                    </div>
                    <div>
                        <?php echo statusBadge($app['application_status']); ?>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <p>No applications yet. Start applying to your dream jobs!</p>
                <a href="browse_jobs.php">Browse Jobs <i class="fas fa-arrow-right"></i></a>
            </div>
        <?php endif; ?>
    </div>

</div>

<!-- Footer -->
<footer class="site-footer">
    <div class="container">
        <div class="row">
            <div class="col-lg-4 mb-4">
                <div class="footer-brand">Nova<span>Hire</span></div>
                <p style="color: #94a3b8; font-size: 0.85rem; line-height: 1.7; max-width: 300px;">Your gateway to career success. Connect with top employers and find your dream job.</p>
            </div>
            <div class="col-lg-2 col-md-4 mb-4">
                <div class="footer-heading">For Job Seekers</div>
                <ul style="list-style:none; padding:0;">
                    <li style="margin-bottom:10px;"><a href="browse_jobs.php" class="footer-link">Browse Jobs</a></li>
                    <li style="margin-bottom:10px;"><a href="available_companies.php" class="footer-link">Companies</a></li>
                    <li style="margin-bottom:10px;"><a href="profile.php" class="footer-link">My Profile</a></li>
                    <li style="margin-bottom:10px;"><a href="my_application.php" class="footer-link">My Applications</a></li>
                </ul>
            </div>
            <div class="col-lg-2 col-md-4 mb-4">
                <div class="footer-heading">For Employers</div>
                <ul style="list-style:none; padding:0;">
                    <li style="margin-bottom:10px;"><a href="company_registration.php" class="footer-link">Register Company</a></li>
                    <li style="margin-bottom:10px;"><a href="login.php" class="footer-link">Employer Login</a></li>
                    <li style="margin-bottom:10px;"><a href="login.php" class="footer-link">Post a Job</a></li>
                </ul>
            </div>
            <div class="col-lg-2 col-md-4 mb-4">
                <div class="footer-heading">Resources</div>
                <ul style="list-style:none; padding:0;">
                    <li style="margin-bottom:10px;"><a href="browse_jobs.php" class="footer-link">Browse Jobs</a></li>
                    <li style="margin-bottom:10px;"><a href="view_cv.php" class="footer-link">Build Your CV</a></li>
                </ul>
            </div>
            <div class="col-lg-2 col-md-4 mb-4">
                <div class="footer-heading">Support</div>
                <ul style="list-style:none; padding:0;">
                    <li style="margin-bottom:10px;"><a href="#" class="footer-link">Help Center</a></li>
                    <li style="margin-bottom:10px;"><a href="#" class="footer-link">Privacy Policy</a></li>
                    <li style="margin-bottom:10px;"><a href="#" class="footer-link">Contact Us</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> NovaHire. All rights reserved.</p>
        </div>
    </div>
</footer>

</body>
</html>
