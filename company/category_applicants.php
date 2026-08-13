<?php 
session_start();
include('../admin/dbcon.php');

// Check if company is logged in
if (!isset($_SESSION['company_id'])) {
    header('Location: ../company_login.php');
    exit();
}

$company_id = $_SESSION['company_id'];
$company_name = $_SESSION['company_name'];

// Handle status update
$alert_message = '';
$alert_type = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $application_id = intval($_POST['application_id']);
    $new_status = mysqli_real_escape_string($con, $_POST['status']);
    $company_notes = mysqli_real_escape_string($con, $_POST['company_notes']);
    
    $update_query = "UPDATE category_applications SET status = ?, company_notes = ? WHERE id = ? AND company_id = ?";
    $stmt = mysqli_prepare($con, $update_query);
    mysqli_stmt_bind_param($stmt, "ssii", $new_status, $company_notes, $application_id, $company_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $alert_message = "Application status updated successfully!";
        $alert_type = "success";
    } else {
        $alert_message = "Failed to update status.";
        $alert_type = "danger";
    }
    mysqli_stmt_close($stmt);
}

// Get filter parameters
$filter_category = isset($_GET['category']) ? mysqli_real_escape_string($con, $_GET['category']) : 'all';
$filter_status = isset($_GET['status']) ? mysqli_real_escape_string($con, $_GET['status']) : 'all';
$search = isset($_GET['search']) ? mysqli_real_escape_string($con, $_GET['search']) : '';

// Get all categories that have applications for this company
$categories_query = "SELECT DISTINCT category FROM category_applications WHERE company_id = ? ORDER BY category";
$stmt = mysqli_prepare($con, $categories_query);
mysqli_stmt_bind_param($stmt, "i", $company_id);
mysqli_stmt_execute($stmt);
$categories_result = mysqli_stmt_get_result($stmt);
$available_categories = [];
while ($row = mysqli_fetch_assoc($categories_result)) {
    $available_categories[] = $row['category'];
}
mysqli_stmt_close($stmt);

// Build query with filters
$applications_query = "SELECT ca.*, u.username, u.email, u.phone, u.user_degree, u.user_skills,
                       uqs.status as quiz_status, uqs.last_attempt
                       FROM category_applications ca
                       INNER JOIN user_info u ON ca.user_id = u.id
                       LEFT JOIN user_quiz_status uqs ON ca.user_id = uqs.user_id AND ca.category = uqs.category
                       WHERE ca.company_id = ?";

$params = [$company_id];
$types = "i";

if ($filter_category != 'all') {
    $applications_query .= " AND ca.category = ?";
    $params[] = $filter_category;
    $types .= "s";
}

if ($filter_status != 'all') {
    $applications_query .= " AND ca.status = ?";
    $params[] = $filter_status;
    $types .= "s";
}

if (!empty($search)) {
    $search_param = "%$search%";
    $applications_query .= " AND (u.username LIKE ? OR u.email LIKE ? OR ca.category LIKE ?)";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

$applications_query .= " ORDER BY ca.application_date DESC";

$stmt = mysqli_prepare($con, $applications_query);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$applications_result = mysqli_stmt_get_result($stmt);

// Get statistics
$stats_query = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'Interview' THEN 1 ELSE 0 END) as interview,
                SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) as rejected
                FROM category_applications WHERE company_id = ?";
$stmt_stats = mysqli_prepare($con, $stats_query);
mysqli_stmt_bind_param($stmt_stats, "i", $company_id);
mysqli_stmt_execute($stmt_stats);
$stats = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_stats));
mysqli_stmt_close($stmt_stats);

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
    'Mobile'      => ['icon' => 'fa-mobile-screen', 'color' => '#0ea5e9'],
    'DevOps'      => ['icon' => 'fa-server', 'color' => '#f97316'],
    'Data Science'=> ['icon' => 'fa-chart-bar', 'color' => '#06b6d4'],
    'UI/UX'       => ['icon' => 'fa-pen-ruler', 'color' => '#ec4899'],
    'Other'       => ['icon' => 'fa-briefcase', 'color' => '#4f46e5'],
];
$default_style = ['icon' => 'fa-briefcase', 'color' => '#4f46e5'];

$avatar_gradients = [
    ['#6366f1', '#8b5cf6'],
    ['#0ea5e9', '#06b6d4'],
    ['#10b981', '#34d399'],
    ['#f59e0b', '#f97316'],
    ['#ec4899', '#f43f5e'],
    ['#14b8a6', '#0d9488'],
];

function category_applicant_avatar($username, $gradients) {
    $initial = strtoupper(substr(trim($username), 0, 1) ?: '?');
    $g = $gradients[abs(crc32($username)) % count($gradients)];
    return '<div class="ca-avatar" style="background: linear-gradient(135deg, ' . $g[0] . ', ' . $g[1] . ');">' . $initial . '</div>';
}

$status_meta = [
    'Pending'   => ['icon' => 'fa-clock', 'color' => '#f59e0b', 'bg' => 'rgba(245,158,11,.12)'],
    'Interview' => ['icon' => 'fa-calendar-check', 'color' => '#3b82f6', 'bg' => 'rgba(59,130,246,.12)'],
    'Approved'  => ['icon' => 'fa-circle-check', 'color' => '#10b981', 'bg' => 'rgba(16,185,129,.12)'],
    'Rejected'  => ['icon' => 'fa-circle-xmark', 'color' => '#ef4444', 'bg' => 'rgba(239,68,68,.12)'],
];

function category_style_for($category, $category_styles, $default_style) {
    return $category_styles[$category] ?? $default_style;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Category Applicants | Company Dashboard</title>
    <?php include '../includes/links.php'; ?>
    <style>
        :root {
            --ca-bg: #f4f6fb;
            --ca-card: #ffffff;
            --ca-border: #e5e9f2;
            --ca-text: #1e293b;
            --ca-muted: #64748b;
            --ca-primary: #4f46e5;
            --ca-primary-2: #7c3aed;
            --ca-soft: #eef2ff;
            --ca-input: #f8fafc;
            --ca-shadow: 0 10px 30px rgba(15, 23, 42, 0.07);
        }
        [data-theme="dark"] {
            --ca-bg: #0f172a;
            --ca-card: #111827;
            --ca-border: #28334a;
            --ca-text: #e8edff;
            --ca-muted: #94a3b8;
            --ca-primary: #8b5cf6;
            --ca-primary-2: #a78bfa;
            --ca-soft: #1e293b;
            --ca-input: #0d1526;
            --ca-shadow: 0 10px 30px rgba(0, 0, 0, 0.45);
        }

        body {
            background:
                radial-gradient(circle at 8% 12%, rgba(99, 102, 241, 0.10), transparent 28%),
                radial-gradient(circle at 92% 8%, rgba(217, 70, 239, 0.08), transparent 26%),
                var(--ca-bg);
            color: var(--ca-text);
            min-height: 100vh;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }

        .ca-wrap { max-width: 1200px; margin: 0 auto; padding: 34px 24px 60px; }

        /* ── Hero ── */
        .ca-hero {
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 55%, #a855f7 100%);
            border-radius: 22px;
            padding: 30px 34px;
            color: #fff;
            box-shadow: 0 20px 40px rgba(79, 70, 229, 0.28);
        }
        .ca-hero::before {
            content: '';
            position: absolute;
            right: -80px; top: -80px;
            width: 260px; height: 260px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.10);
        }
        .ca-hero::after {
            content: '';
            position: absolute;
            right: 60px; bottom: -110px;
            width: 220px; height: 220px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.08);
        }
        .ca-hero h1 { font-weight: 800; font-size: 1.75rem; color: #fff; margin: 0 0 6px; }
        .ca-hero p { color: rgba(255, 255, 255, 0.85); margin: 0; font-size: 0.95rem; }

        /* ── Stats ── */
        .ca-stats { display: grid; grid-template-columns: repeat(5, 1fr); gap: 16px; margin-top: 22px; }
        .ca-stat {
            background: var(--ca-card);
            border: 1px solid var(--ca-border);
            border-radius: 16px;
            padding: 18px 20px;
            display: flex;
            align-items: center;
            gap: 14px;
            box-shadow: var(--ca-shadow);
            transition: transform .2s ease, box-shadow .2s ease;
            cursor: pointer;
            text-decoration: none;
        }
        .ca-stat:hover { transform: translateY(-4px); box-shadow: 0 18px 38px rgba(79, 70, 229, 0.14); text-decoration: none; }
        .ca-stat-ico {
            width: 46px; height: 46px;
            border-radius: 13px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.15rem;
            flex-shrink: 0;
        }
        .ca-stat b { display: block; font-size: 1.45rem; line-height: 1.1; color: var(--ca-text); }
        .ca-stat span { font-size: 0.72rem; color: var(--ca-muted); font-weight: 600; text-transform: uppercase; letter-spacing: .4px; }
        .ca-stat.on {
            border-color: var(--ca-primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15), var(--ca-shadow);
        }

        /* ── Toolbar ── */
        .ca-toolbar {
            display: flex; justify-content: space-between; align-items: center;
            flex-wrap: wrap; gap: 14px;
            margin: 30px 0 20px;
        }
        .ca-filters { display: flex; gap: 8px; flex-wrap: wrap; }
        .ca-fbtn {
            border: 1.5px solid var(--ca-border);
            background: var(--ca-card);
            color: var(--ca-muted);
            font-weight: 600; font-size: 0.83rem;
            padding: 9px 16px;
            border-radius: 12px;
            cursor: pointer;
            transition: all .2s ease;
            text-decoration: none;
        }
        .ca-fbtn:hover { border-color: var(--ca-primary); color: var(--ca-primary); text-decoration: none; }
        .ca-fbtn.active {
            background: linear-gradient(135deg, var(--ca-primary), var(--ca-primary-2));
            color: #fff; border-color: transparent;
            box-shadow: 0 8px 20px rgba(79, 70, 229, 0.3);
        }
        .ca-fbtn .cnt {
            display: inline-block; margin-left: 6px;
            background: rgba(0, 0, 0, 0.08);
            border-radius: 20px; padding: 1px 8px; font-size: 0.72rem;
        }
        .ca-fbtn.active .cnt { background: rgba(255, 255, 255, 0.22); }

        .ca-catsel, .ca-search input {
            background: var(--ca-card);
            border: 1.5px solid var(--ca-border);
            color: var(--ca-text);
            border-radius: 13px;
            padding: 12px 16px;
            font-size: 0.92rem;
            outline: none;
            transition: border-color .2s ease, box-shadow .2s ease;
        }
        .ca-catsel:focus, .ca-search input:focus { border-color: var(--ca-primary); box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.15); }

        .ca-right { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .ca-search { position: relative; }
        .ca-search i { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--ca-muted); font-size: 0.9rem; }
        .ca-search input { padding-left: 42px; min-width: 260px; }
        .ca-search input::placeholder { color: var(--ca-muted); }

        .ca-count { font-size: 0.85rem; color: var(--ca-muted); font-weight: 500; }

        /* ── Cards ── */
        .ca-card {
            display: flex;
            gap: 20px;
            background: var(--ca-card);
            border: 1px solid var(--ca-border);
            border-radius: 18px;
            padding: 22px 24px;
            margin-bottom: 16px;
            box-shadow: var(--ca-shadow);
            transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
            animation: caRise .5s ease both;
        }
        @keyframes caRise {
            from { opacity: 0; transform: translateY(14px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .ca-card:hover { transform: translateY(-4px); box-shadow: 0 20px 42px rgba(15, 23, 42, 0.12); border-color: rgba(99, 102, 241, 0.35); }
        .ca-card.hidden { display: none; }

        .ca-avatar {
            width: 58px; height: 58px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem; font-weight: 800; color: #fff;
            flex-shrink: 0;
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.18);
        }

        .ca-main { flex: 1; min-width: 0; }

        .ca-name-row { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .ca-name-row h3 { font-size: 1.12rem; font-weight: 700; color: var(--ca-text); margin: 0; }

        .ca-badge {
            display: inline-flex; align-items: center; gap: 6px;
            font-size: 0.72rem; font-weight: 700;
            padding: 4px 12px; border-radius: 20px;
        }
        .ca-badge i { font-size: 0.55rem; }
        .ca-badge.passed { background: rgba(16,185,129,.12); color: #10b981; }
        .ca-badge.failed { background: rgba(239,68,68,.12); color: #ef4444; }
        .ca-badge.none { background: var(--ca-soft); color: var(--ca-muted); }
        .ca-badge.Interview { background: rgba(59,130,246,.12); color: #3b82f6; }
        .ca-badge.Pending { background: rgba(245,158,11,.12); color: #f59e0b; }
        .ca-badge.Approved { background: rgba(16,185,129,.12); color: #10b981; }
        .ca-badge.Rejected { background: rgba(239,68,68,.12); color: #ef4444; }

        .ca-catchip {
            display: inline-flex; align-items: center; gap: 7px;
            font-size: 0.76rem; font-weight: 700;
            padding: 5px 13px; border-radius: 20px;
        }

        .ca-meta { display: flex; flex-wrap: wrap; gap: 6px 18px; margin-top: 12px; }
        .ca-meta span { font-size: 0.83rem; color: var(--ca-muted); }
        .ca-meta i { margin-right: 6px; opacity: .8; }
        .ca-meta b { color: var(--ca-text); font-weight: 600; }

        .ca-cover {
            display: flex; gap: 10px;
            margin-top: 12px;
            background: var(--ca-soft);
            border-radius: 12px;
            padding: 11px 14px;
            font-size: 0.87rem; color: var(--ca-muted);
            line-height: 1.55;
        }
        .ca-cover i { color: var(--ca-primary); flex-shrink: 0; margin-top: 3px; }

        .ca-skills { display: flex; flex-wrap: wrap; gap: 7px; margin-top: 13px; }
        .ca-skill {
            font-size: 0.73rem; font-weight: 600;
            background: var(--ca-input);
            border: 1px solid var(--ca-border);
            color: var(--ca-text);
            padding: 4px 11px; border-radius: 20px;
        }

        .ca-actions {
            display: flex; flex-direction: column; gap: 9px;
            flex-shrink: 0;
            justify-content: center;
            min-width: 150px;
        }
        .ca-act {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            padding: 10px 16px; border-radius: 11px;
            font-size: 0.82rem; font-weight: 600;
            border: 1.5px solid var(--ca-border);
            background: var(--ca-card);
            color: var(--ca-text);
            text-decoration: none;
            transition: all .18s ease;
            white-space: nowrap;
        }
        .ca-act:hover { transform: translateY(-2px); text-decoration: none; }
        .ca-act-detail { background: rgba(79, 70, 229, 0.10); border-color: rgba(79, 70, 229, 0.35); color: var(--ca-primary); }
        .ca-act-detail:hover { background: var(--ca-primary); color: #fff; }
        .ca-act-cv { background: rgba(16, 185, 129, 0.10); border-color: rgba(16, 185, 129, 0.35); color: #10b981; }
        .ca-act-cv:hover { background: #10b981; color: #fff; }
        .ca-act-contact { background: rgba(59, 130, 246, 0.10); border-color: rgba(59, 130, 246, 0.35); color: #3b82f6; }
        .ca-act-contact:hover { background: #3b82f6; color: #fff; }

        /* ── Empty ── */
        .ca-empty {
            text-align: center;
            padding: 70px 24px;
            background: var(--ca-card);
            border: 1.5px dashed var(--ca-border);
            border-radius: 18px;
        }
        .ca-empty i { font-size: 3.4rem; color: var(--ca-primary); opacity: .35; }
        .ca-empty h3 { font-weight: 700; color: var(--ca-text); margin-top: 16px; }
        .ca-empty p { color: var(--ca-muted); }

        /* ── Modal ── */
        .ca-modal {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 1050;
            background: rgba(15, 23, 42, 0.65);
            backdrop-filter: blur(4px);
            animation: caFade .25s ease;
        }
        @keyframes caFade { from { opacity: 0; } to { opacity: 1; } }
        .ca-modal.show { display: flex; align-items: center; justify-content: center; padding: 20px; }
        .ca-modal-card {
            background: var(--ca-card);
            border-radius: 20px;
            width: 100%;
            max-width: 720px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 30px 70px rgba(0, 0, 0, 0.35);
            animation: caSlide .3s ease;
        }
        @keyframes caSlide { from { transform: translateY(26px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .ca-modal-head {
            position: sticky; top: 0;
            display: flex; justify-content: space-between; align-items: center;
            padding: 18px 24px;
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            color: #fff;
            border-radius: 20px 20px 0 0;
            z-index: 2;
        }
        .ca-modal-head h4 { margin: 0; font-size: 1.1rem; font-weight: 700; }
        .ca-modal-close {
            background: rgba(255, 255, 255, 0.18);
            border: none; color: #fff;
            width: 34px; height: 34px;
            border-radius: 10px;
            font-size: 1rem;
            cursor: pointer;
            transition: background .2s ease;
        }
        .ca-modal-close:hover { background: rgba(255, 255, 255, 0.32); }
        .ca-modal-body { padding: 24px; }

        /* AJAX modal content (from get_application_details.php) */
        .ca-modal-body .applicant-detail { margin-bottom: 18px; }
        .ca-modal-body .detail-label {
            font-weight: 700; color: var(--ca-muted);
            font-size: 0.8rem; text-transform: uppercase; letter-spacing: .5px;
            margin-bottom: 7px;
        }
        .ca-modal-body .detail-label i { margin-right: 6px; color: var(--ca-primary); }
        .ca-modal-body .detail-value {
            color: var(--ca-text);
            background: var(--ca-soft);
            border: 1px solid var(--ca-border);
            border-radius: 11px;
            padding: 12px 14px;
            font-size: 0.93rem;
        }
        .ca-modal-body .status-badge {
            display: inline-block;
            padding: 6px 16px; border-radius: 20px;
            font-size: 0.83rem; font-weight: 700;
        }
        .ca-modal-body .status-Pending { background: rgba(245,158,11,.14); color: #f59e0b; }
        .ca-modal-body .status-Interview { background: rgba(59,130,246,.14); color: #3b82f6; }
        .ca-modal-body .status-Approved { background: rgba(16,185,129,.14); color: #10b981; }
        .ca-modal-body .status-Rejected { background: rgba(239,68,68,.14); color: #ef4444; }
        .ca-modal-body .quiz-score {
            display: flex; align-items: center; gap: 14px;
            padding: 14px 16px;
            background: var(--ca-soft);
            border: 1px solid var(--ca-border);
            border-radius: 12px;
        }
        .ca-modal-body .score-circle {
            width: 54px; height: 54px;
            border-radius: 50%;
            background: #10b981;
            color: #fff;
            display: flex; align-items: center; justify-content: center;
            font-weight: 800; font-size: 1.1rem;
            flex-shrink: 0;
        }
        .ca-modal-body form label { font-weight: 600; color: var(--ca-text); font-size: 0.9rem; }
        .ca-modal-body .form-control, .ca-modal-body select.form-control, .ca-modal-body textarea.form-control {
            background: var(--ca-input);
            border: 1.5px solid var(--ca-border);
            color: var(--ca-text);
            border-radius: 11px;
            padding: 11px 14px;
        }
        .ca-modal-body .form-control:focus {
            border-color: var(--ca-primary);
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.15);
        }
        .ca-modal-body .btn-primary {
            background: linear-gradient(135deg, var(--ca-primary), var(--ca-primary-2));
            border: none; border-radius: 11px;
            font-weight: 600; padding: 12px 18px;
        }
        .ca-modal-body .btn-info {
            background: linear-gradient(135deg, #0ea5e9, #06b6d4);
            border: none; border-radius: 11px;
            font-weight: 600; padding: 12px 18px;
        }
        .ca-modal-body .badge { border-radius: 20px; font-size: 0.82rem; padding: 8px 16px; }

        /* ── Toast ── */
        .ca-toast {
            position: fixed;
            top: 90px; right: 24px;
            z-index: 1200;
            display: flex; align-items: center; gap: 12px;
            background: var(--ca-card);
            border: 1px solid var(--ca-border);
            border-left: 4px solid #10b981;
            border-radius: 13px;
            padding: 14px 18px;
            box-shadow: 0 18px 44px rgba(15, 23, 42, 0.2);
            font-size: 0.9rem; font-weight: 600; color: var(--ca-text);
            animation: caToastIn .35s ease;
        }
        .ca-toast.danger { border-left-color: #ef4444; }
        .ca-toast .ca-toast-ico { font-size: 1.2rem; }
        @keyframes caToastIn { from { transform: translateX(60px); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

        @media (max-width: 992px) {
            .ca-stats { grid-template-columns: repeat(3, 1fr); }
            .ca-card { flex-wrap: wrap; }
            .ca-actions { width: 100%; flex-direction: row; flex-wrap: wrap; }
            .ca-actions .ca-act { flex: 1; }
        }
        @media (max-width: 640px) {
            .ca-stats { grid-template-columns: repeat(2, 1fr); }
            .ca-toolbar { flex-direction: column; align-items: stretch; }
            .ca-right { width: 100%; }
            .ca-search, .ca-search input { width: 100%; }
            .ca-catsel { width: 100%; }
            .ca-actions { flex-direction: column; }
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/company_header.php'; ?>

    <div class="ca-wrap">
        <!-- Hero -->
        <div class="ca-hero">
            <h1><i class="fas fa-layer-group mr-2"></i>Category Applicants</h1>
            <p>Review, filter and manage candidates who applied by category.</p>
        </div>

        <!-- Stats (clickable filters) -->
        <?php
            $qs = $filter_category != 'all' ? '&category=' . urlencode($filter_category) : '';
            if (!empty($search)) $qs .= '&search=' . urlencode($search);
        ?>
        <div class="ca-stats">
            <a class="ca-stat <?php echo $filter_status == 'all' ? 'on' : ''; ?>" href="?status=all<?php echo $qs; ?>">
                <div class="ca-stat-ico" style="background: rgba(99,102,241,.12); color:#6366f1;"><i class="fas fa-file-signature"></i></div>
                <div><b><?php echo intval($stats['total']); ?></b><span>Applications</span></div>
            </a>
            <a class="ca-stat <?php echo $filter_status == 'Pending' ? 'on' : ''; ?>" href="?status=Pending<?php echo $qs; ?>">
                <div class="ca-stat-ico" style="background: rgba(245,158,11,.12); color:#f59e0b;"><i class="fas fa-clock"></i></div>
                <div><b><?php echo intval($stats['pending']); ?></b><span>Pending</span></div>
            </a>
            <a class="ca-stat <?php echo $filter_status == 'Interview' ? 'on' : ''; ?>" href="?status=Interview<?php echo $qs; ?>">
                <div class="ca-stat-ico" style="background: rgba(59,130,246,.12); color:#3b82f6;"><i class="fas fa-calendar-check"></i></div>
                <div><b><?php echo intval($stats['interview']); ?></b><span>Interview</span></div>
            </a>
            <a class="ca-stat <?php echo $filter_status == 'Approved' ? 'on' : ''; ?>" href="?status=Approved<?php echo $qs; ?>">
                <div class="ca-stat-ico" style="background: rgba(16,185,129,.12); color:#10b981;"><i class="fas fa-circle-check"></i></div>
                <div><b><?php echo intval($stats['approved']); ?></b><span>Approved</span></div>
            </a>
            <a class="ca-stat <?php echo $filter_status == 'Rejected' ? 'on' : ''; ?>" href="?status=Rejected<?php echo $qs; ?>">
                <div class="ca-stat-ico" style="background: rgba(239,68,68,.12); color:#ef4444;"><i class="fas fa-circle-xmark"></i></div>
                <div><b><?php echo intval($stats['rejected']); ?></b><span>Rejected</span></div>
            </a>
        </div>

        <!-- Toolbar -->
        <div class="ca-toolbar">
            <div class="ca-filters">
                <a class="ca-fbtn <?php echo $filter_status == 'all' ? 'active' : ''; ?>" href="?status=all<?php echo $qs; ?>">All<span class="cnt"><?php echo intval($stats['total']); ?></span></a>
                <a class="ca-fbtn <?php echo $filter_status == 'Pending' ? 'active' : ''; ?>" href="?status=Pending<?php echo $qs; ?>">Pending</a>
                <a class="ca-fbtn <?php echo $filter_status == 'Interview' ? 'active' : ''; ?>" href="?status=Interview<?php echo $qs; ?>">Interview</a>
                <a class="ca-fbtn <?php echo $filter_status == 'Approved' ? 'active' : ''; ?>" href="?status=Approved<?php echo $qs; ?>">Approved</a>
                <a class="ca-fbtn <?php echo $filter_status == 'Rejected' ? 'active' : ''; ?>" href="?status=Rejected<?php echo $qs; ?>">Rejected</a>
            </div>

            <div class="ca-right">
                <div class="ca-count" id="caCount"></div>
                <div class="ca-search">
                    <i class="fas fa-magnifying-glass"></i>
                    <input type="text" id="caSearch" placeholder="Search applicants..." oninput="filterApplicants()">
                </div>
                <select class="ca-catsel" onchange="location.href='?status=<?php echo urlencode($filter_status); ?>&category=' + encodeURIComponent(this.value);">
                    <option value="all">All Categories</option>
                    <?php foreach ($available_categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $filter_category == $cat ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Applicant list -->
        <?php if (mysqli_num_rows($applications_result) > 0): ?>
            <?php $total_count = mysqli_num_rows($applications_result); ?>
            <div class="ca-list" id="caList">
                <?php while ($app = mysqli_fetch_assoc($applications_result)):
                    $cstyle = category_style_for($app['category'], $category_styles, $default_style);
                    $smeta = $status_meta[$app['status']] ?? $status_meta['Pending'];
                    $qstatus = $app['quiz_status'] ?: 'none';
                    $data_search = strtolower(htmlspecialchars(trim($app['username']) . ' ' . $app['email'] . ' ' . $app['category'] . ' ' . ($app['user_degree'] ?? '') . ' ' . ($app['user_skills'] ?? '')));
                    $skills = array_filter(array_map('trim', explode(',', $app['user_skills'])));
                ?>
                    <div class="ca-card" data-search="<?php echo $data_search; ?>">
                        <?php echo category_applicant_avatar($app['username'], $avatar_gradients); ?>

                        <div class="ca-main">
                            <div class="ca-name-row">
                                <h3><?php echo htmlspecialchars(trim($app['username'])); ?></h3>
                                <span class="ca-catchip" style="background: <?php echo $cstyle['color']; ?>1a; color: <?php echo $cstyle['color']; ?>;">
                                    <i class="<?php echo $cstyle['icon']; ?>"></i><?php echo htmlspecialchars($app['category']); ?>
                                </span>
                                <span class="ca-badge <?php echo $qstatus; ?>">
                                    <i class="fas fa-circle"></i>
                                    <?php echo $qstatus == 'passed' ? 'Quiz Passed' : ($qstatus == 'failed' ? 'Quiz Failed' : 'No Quiz'); ?>
                                </span>
                            </div>

                            <div class="ca-meta">
                                <span><i class="fas fa-envelope"></i><?php echo htmlspecialchars($app['email']); ?></span>
                                <?php if (!empty($app['phone'])): ?>
                                    <span><i class="fas fa-phone"></i><?php echo htmlspecialchars($app['phone']); ?></span>
                                <?php endif; ?>
                                <span><i class="far fa-calendar-alt"></i>Applied: <?php echo date('M d, Y', strtotime($app['application_date'])); ?></span>
                                <?php if (!empty($app['interview_date'])): ?>
                                    <span><i class="fas fa-calendar-check"></i>Interview: <?php echo date('M d, Y', strtotime($app['interview_date'])); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($app['user_degree'])): ?>
                                    <span><i class="fas fa-graduation-cap"></i><?php echo htmlspecialchars($app['user_degree']); ?></span>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($app['user_message'])): ?>
                                <div class="ca-cover">
                                    <i class="fas fa-quote-left"></i>
                                    <span><?php echo nl2br(htmlspecialchars(mb_substr($app['user_message'], 0, 200))); ?><?php echo mb_strlen($app['user_message']) > 200 ? '...' : ''; ?></span>
                                </div>
                            <?php endif; ?>

                            <?php if (count($skills) > 0): ?>
                                <div class="ca-skills">
                                    <?php foreach (array_slice($skills, 0, 8) as $skill): ?>
                                        <span class="ca-skill"><?php echo htmlspecialchars($skill); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="ca-actions">
                            <span class="ca-badge <?php echo $app['status']; ?>" style="justify-content:center;">
                                <i class="<?php echo $smeta['icon']; ?>"></i><?php echo $app['status']; ?>
                            </span>
                            <a class="ca-act ca-act-detail" href="javascript:void(0)" onclick="viewApplication(<?php echo intval($app['id']); ?>)">
                                <i class="fas fa-eye"></i>View Details
                            </a>
                            <a class="ca-act ca-act-cv" href="../seeker/view_cv.php?id=<?php echo intval($app['user_id']); ?>" target="_blank">
                                <i class="fas fa-file-pdf"></i>View CV
                            </a>
                            <a class="ca-act ca-act-contact" href="mailto:<?php echo htmlspecialchars($app['email']); ?>">
                                <i class="fas fa-envelope"></i>Contact
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
            <div class="ca-empty" id="caNoMatch" style="display:none;">
                <i class="fas fa-user-magnifying-glass"></i>
                <h3>No Applicants Found</h3>
                <p>Try a different search term.</p>
            </div>
        <?php else: ?>
            <div class="ca-empty">
                <i class="fas fa-inbox"></i>
                <h3>No Category Applications Yet</h3>
                <p>Candidates who apply by category will appear here.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- View Application Modal -->
    <div id="viewModal" class="ca-modal">
        <div class="ca-modal-card">
            <div class="ca-modal-head">
                <h4><i class="fas fa-user mr-2"></i>Application Details</h4>
                <button type="button" class="ca-modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="ca-modal-body" id="modalContent">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="sr-only">Loading...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($alert_message): ?>
        <div class="ca-toast <?php echo $alert_type == 'danger' ? 'danger' : ''; ?>" id="caToast">
            <span class="ca-toast-ico"><i class="fas <?php echo $alert_type == 'danger' ? 'fa-circle-xmark' : 'fa-circle-check'; ?>"></i></span>
            <span><?php echo htmlspecialchars($alert_message); ?></span>
        </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script>
        function filterApplicants() {
            const q = (document.getElementById('caSearch').value || '').toLowerCase();
            const cards = document.querySelectorAll('.ca-card');
            let visible = 0;
            cards.forEach(card => {
                const ok = !q || card.dataset.search.includes(q);
                card.classList.toggle('hidden', !ok);
                if (ok) visible++;
            });
            const noMatch = document.getElementById('caNoMatch');
            if (noMatch) noMatch.style.display = visible === 0 ? '' : 'none';
            const count = document.getElementById('caCount');
            if (count) count.textContent = visible + ' of <?php echo $total_count ?? 0; ?> applicants';
        }

        function viewApplication(applicationId) {
            const modal = document.getElementById('viewModal');
            modal.classList.add('show');
            modal.style.display = 'flex';
            document.getElementById('modalContent').innerHTML =
                '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="sr-only">Loading...</span></div></div>';

            $.ajax({
                url: 'get_application_details.php',
                method: 'POST',
                data: { application_id: applicationId },
                success: function(response) {
                    document.getElementById('modalContent').innerHTML = response;
                },
                error: function() {
                    document.getElementById('modalContent').innerHTML =
                        '<div class="alert alert-danger">Failed to load application details.</div>';
                }
            });
        }

        function closeModal() {
            const modal = document.getElementById('viewModal');
            modal.classList.remove('show');
            modal.style.display = 'none';
        }

        window.onclick = function(event) {
            const modal = document.getElementById('viewModal');
            if (event.target == modal) {
                closeModal();
            }
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeModal();
        });

        document.addEventListener('DOMContentLoaded', function() {
            filterApplicants();
            const toast = document.getElementById('caToast');
            if (toast) {
                setTimeout(function() {
                    toast.style.transition = 'opacity .5s ease, transform .5s ease';
                    toast.style.opacity = '0';
                    toast.style.transform = 'translateX(60px)';
                    setTimeout(function() { toast.remove(); }, 500);
                }, 3500);
            }
        });
    </script>
</body>
</html>
