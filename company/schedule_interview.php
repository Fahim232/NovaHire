<?php
session_start();
include '../admin/dbcon.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['company_id'])) {
    header('Location: ../company_login.php');
    exit;
}

$company_id = $_SESSION['company_id'];
$company_name = $_SESSION['company_name'] ?? 'Company';
$success_msg = '';
$error_msg = '';

// Ensure interviews table exists (create if not)
mysqli_query($con, "CREATE TABLE IF NOT EXISTS `interviews` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `application_id` int(11) NOT NULL,
    `company_id` int(11) NOT NULL,
    `user_id` int(11) NOT NULL,
    `job_id` int(11) NOT NULL,
    `interview_date` date NOT NULL,
    `interview_time` time NOT NULL,
    `interview_type` enum('Online','Phone','In-Person') NOT NULL DEFAULT 'Online',
    `location` varchar(255) DEFAULT NULL,
    `meeting_link` varchar(255) DEFAULT NULL,
    `notes` text DEFAULT NULL,
    `status` enum('scheduled','completed','cancelled') DEFAULT 'scheduled',
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `application_id` (`application_id`),
    KEY `company_id` (`company_id`),
    KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// --- Handle Mark Completed / Cancel actions ---
if (isset($_POST['mark_completed'])) {
    $int_id = intval($_POST['interview_id']);
    mysqli_query($con, "UPDATE interviews SET status='completed' WHERE id=$int_id AND company_id=$company_id");
    header("Location: schedule_interview.php?done=completed");
    exit;
}
if (isset($_POST['cancel_interview'])) {
    $int_id = intval($_POST['interview_id']);
    mysqli_query($con, "UPDATE interviews SET status='cancelled' WHERE id=$int_id AND company_id=$company_id");
    header("Location: schedule_interview.php?done=cancelled");
    exit;
}

// --- Fetch application details if application_id is provided ---
$app = null;
if (isset($_GET['application_id'])) {
    $app_id = intval($_GET['application_id']);
    $app_query = "SELECT ja.*, cj.job_title, cj.job_category, ui.username, ui.email, ui.phone
                  FROM job_applications ja
                  JOIN company_jobs cj ON ja.job_id = cj.id
                  JOIN user_info ui ON ja.user_id = ui.id
                  WHERE ja.id = $app_id AND ja.company_id = $company_id";
    $app_result = mysqli_query($con, $app_query);
    if (mysqli_num_rows($app_result) > 0) {
        $app = mysqli_fetch_assoc($app_result);
    }
}

// --- Handle Schedule Interview form submission ---
if (isset($_POST['schedule_interview']) && $app) {
    $int_date = mysqli_real_escape_string($con, $_POST['interview_date']);
    $int_time = mysqli_real_escape_string($con, $_POST['interview_time']);
    $int_type = mysqli_real_escape_string($con, $_POST['interview_type']);
    $location = mysqli_real_escape_string($con, trim($_POST['location']));
    $notes    = mysqli_real_escape_string($con, trim($_POST['notes']));

    if (empty($int_date) || empty($int_time)) {
        $error_msg = "Please select both a date and time for the interview.";
    } else {
        $meeting_link = '';
        if ($int_type == 'Online') {
            $meeting_link = 'jobportal_interview_' . uniqid();
        }

        // Insert into interviews table
        $insert_query = "INSERT INTO interviews (application_id, company_id, user_id, job_id, interview_date, interview_time, interview_type, location, meeting_link, notes)
                         VALUES ({$app['id']}, $company_id, {$app['user_id']}, {$app['job_id']}, '$int_date', '$int_time', '$int_type', '$location', '$meeting_link', '$notes')";
        $insert_ok = mysqli_query($con, $insert_query);

        if ($insert_ok) {
            // Update application status to shortlisted
            mysqli_query($con, "UPDATE job_applications SET application_status='shortlisted' WHERE id={$app['id']}");

            // Create notification for the user
            $formatted_date = date('F j, Y', strtotime($int_date));
            $formatted_time = date('g:i A', strtotime($int_time));
            $notif_title = "Interview Scheduled";
            $notif_message = "Your interview for <strong>{$app['job_title']}</strong> at <strong>$company_name</strong> has been scheduled for <strong>$formatted_date</strong> at <strong>$formatted_time</strong> ($int_type).";
            create_notification($con, 'user', $app['user_id'], 'company', $company_id, $notif_title, $notif_message, 'interview', 'interviews', mysqli_insert_id($con));

            header("Location: schedule_interview.php?done=scheduled&app=" . $app['id']);
            exit;
        } else {
            $error_msg = "Failed to schedule the interview. Please try again.";
        }
    }
}

// --- Fetch all scheduled interviews for this company ---
$interviews_query = "SELECT i.*, ui.username, cj.job_title
                     FROM interviews i
                     JOIN user_info ui ON i.user_id = ui.id
                     JOIN company_jobs cj ON i.job_id = cj.id
                     WHERE i.company_id = $company_id
                     ORDER BY i.interview_date ASC, i.interview_time ASC";
$interviews_result = mysqli_query($con, $interviews_query);
$interviews = [];
if ($interviews_result) {
    while ($row = mysqli_fetch_assoc($interviews_result)) {
        $interviews[] = $row;
    }
}

// Stats
$scheduled_count = 0;
$completed_count = 0;
$cancelled_count = 0;
foreach ($interviews as $int) {
    if ($int['status'] == 'scheduled') $scheduled_count++;
    elseif ($int['status'] == 'completed') $completed_count++;
    elseif ($int['status'] == 'cancelled') $cancelled_count++;
}

$type_colors = [
    'Online'    => ['#6366f1', 'fa-video'],
    'Phone'     => ['#f59e0b', 'fa-phone'],
    'In-Person' => ['#10b981', 'fa-building'],
];
$status_colors = [
    'scheduled' => ['#3b82f6', 'fa-calendar-check'],
    'completed' => ['#10b981', 'fa-circle-check'],
    'cancelled' => ['#ef4444', 'fa-ban'],
];
$avatar_gradients = [
    ['#6366f1', '#8b5cf6'],
    ['#0ea5e9', '#06b6d4'],
    ['#10b981', '#34d399'],
    ['#f59e0b', '#f97316'],
    ['#ec4899', '#f43f5e'],
    ['#14b8a6', '#0d9488'],
];

function si_avatar($username, $gradients) {
    $initial = strtoupper(substr(trim($username), 0, 1) ?: '?');
    $g = $gradients[abs(crc32($username)) % count($gradients)];
    return '<div class="si-avatar" style="background: linear-gradient(135deg, ' . $g[0] . ', ' . $g[1] . ');">' . $initial . '</div>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Schedule Interview | Company Dashboard</title>
    <?php include '../includes/links.php'; ?>
    <style>
        :root {
            --si-bg: #f4f6fb;
            --si-card: #ffffff;
            --si-border: #e5e9f2;
            --si-text: #1e293b;
            --si-muted: #64748b;
            --si-primary: #4f46e5;
            --si-primary-2: #7c3aed;
            --si-soft: #eef2ff;
            --si-input: #f8fafc;
            --si-shadow: 0 10px 30px rgba(15, 23, 42, 0.07);
        }
        [data-theme="dark"] {
            --si-bg: #0f172a;
            --si-card: #111827;
            --si-border: #28334a;
            --si-text: #e8edff;
            --si-muted: #94a3b8;
            --si-primary: #8b5cf6;
            --si-primary-2: #a78bfa;
            --si-soft: #1e293b;
            --si-input: #0d1526;
            --si-shadow: 0 10px 30px rgba(0, 0, 0, 0.45);
        }

        body {
            background:
                radial-gradient(circle at 8% 12%, rgba(99, 102, 241, 0.10), transparent 28%),
                radial-gradient(circle at 92% 8%, rgba(217, 70, 239, 0.08), transparent 26%),
                var(--si-bg);
            color: var(--si-text);
            min-height: 100vh;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }

        .si-wrap { max-width: 1200px; margin: 0 auto; padding: 34px 24px 60px; }

        /* ── Hero ── */
        .si-hero {
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 55%, #a855f7 100%);
            border-radius: 22px;
            padding: 30px 34px;
            color: #fff;
            box-shadow: 0 20px 40px rgba(79, 70, 229, 0.28);
        }
        .si-hero::before {
            content: '';
            position: absolute;
            right: -80px; top: -80px;
            width: 260px; height: 260px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.10);
        }
        .si-hero h1 { font-weight: 800; font-size: 1.75rem; color: #fff; margin: 0 0 6px; }
        .si-hero p { color: rgba(255, 255, 255, 0.85); margin: 0; font-size: 0.95rem; }
        .si-hero-btn {
            position: relative; z-index: 1;
            background: #fff; color: #4f46e5;
            font-weight: 700; border: none;
            padding: 11px 22px; border-radius: 13px;
            display: inline-flex; align-items: center; gap: 8px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
            transition: transform .2s ease, box-shadow .2s ease;
        }
        .si-hero-btn:hover { transform: translateY(-2px); color: #4f46e5; text-decoration: none; }
        .si-hero-btn.ghost { background: rgba(255, 255, 255, 0.16); color: #fff; border: 1px solid rgba(255, 255, 255, 0.35); }
        .si-hero-btn.ghost:hover { background: #fff; color: #4f46e5; }

        /* ── Stats ── */
        .si-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-top: 22px; }
        .si-stat {
            background: var(--si-card);
            border: 1px solid var(--si-border);
            border-radius: 16px;
            padding: 18px 20px;
            display: flex;
            align-items: center;
            gap: 14px;
            box-shadow: var(--si-shadow);
            transition: transform .2s ease, box-shadow .2s ease;
        }
        .si-stat:hover { transform: translateY(-4px); box-shadow: 0 18px 38px rgba(79, 70, 229, 0.14); }
        .si-stat-ico {
            width: 46px; height: 46px;
            border-radius: 13px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.15rem;
            flex-shrink: 0;
        }
        .si-stat b { display: block; font-size: 1.45rem; line-height: 1.1; color: var(--si-text); }
        .si-stat span { font-size: 0.76rem; color: var(--si-muted); font-weight: 600; text-transform: uppercase; letter-spacing: .4px; }

        /* ── Section cards ── */
        .si-section {
            background: var(--si-card);
            border: 1px solid var(--si-border);
            border-radius: 18px;
            padding: 24px 26px;
            margin-top: 20px;
            box-shadow: var(--si-shadow);
            animation: siIn .4s ease both;
        }
        @keyframes siIn {
            from { opacity: 0; transform: translateY(14px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .si-section-head {
            display: flex; align-items: center; gap: 13px;
            margin-bottom: 18px;
            padding-bottom: 14px;
            border-bottom: 1px solid var(--si-border);
        }
        .si-section-head .ico {
            width: 42px; height: 42px;
            border-radius: 13px;
            background: linear-gradient(135deg, var(--si-primary), var(--si-primary-2));
            color: #fff;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.05rem;
            box-shadow: 0 8px 18px rgba(79, 70, 229, 0.3);
        }
        .si-section-head h3 { font-size: 1.05rem; font-weight: 700; margin: 0; color: var(--si-text); }
        .si-section-head p { font-size: 0.8rem; color: var(--si-muted); margin: 2px 0 0; }

        /* Candidate summary */
        .si-candidate {
            display: flex; align-items: center; gap: 18px;
            background: var(--si-input);
            border: 1px solid var(--si-border);
            border-radius: 15px;
            padding: 18px 20px;
            margin-bottom: 22px;
            flex-wrap: wrap;
        }
        .si-avatar {
            width: 52px; height: 52px;
            border-radius: 16px;
            color: #fff;
            font-weight: 800; font-size: 1.25rem;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 8px 18px rgba(0, 0, 0, 0.15);
        }
        .si-cand-main h4 { font-size: 1rem; font-weight: 700; margin: 0 0 3px; color: var(--si-text); }
        .si-cand-main p { font-size: 0.82rem; color: var(--si-muted); margin: 0; }
        .si-cand-main p i { margin-right: 5px; color: var(--si-primary); }
        .si-cand-chip {
            margin-left: auto;
            display: inline-flex; align-items: center; gap: 7px;
            font-size: 0.72rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: .4px;
            background: var(--si-soft);
            border: 1px solid var(--si-border);
            color: var(--si-primary);
            padding: 6px 14px; border-radius: 30px;
        }

        /* Form fields */
        .si-field { margin-bottom: 18px; }
        .si-label {
            font-size: 0.86rem; font-weight: 600; color: var(--si-text);
            margin-bottom: 7px;
        }
        .si-label .req { color: #ef4444; margin-left: 3px; }
        .si-label i { color: var(--si-primary); margin-right: 6px; }
        .si-input {
            width: 100%;
            background: var(--si-input);
            border: 1.5px solid var(--si-border);
            color: var(--si-text);
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 0.92rem;
            outline: none;
            transition: border-color .2s ease, box-shadow .2s ease, background .2s ease;
        }
        .si-input:focus {
            border-color: var(--si-primary);
            background: var(--si-card);
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.14);
        }
        .si-input::placeholder { color: var(--si-muted); opacity: .7; }
        textarea.si-input { min-height: 110px; resize: vertical; line-height: 1.6; }
        select.si-input {
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3e%3cpath fill='%2394a3b8' d='M1.4 0l4.6 4.6L10.6 0 12 1.4 6 7.4 0 1.4z'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 16px center;
            padding-right: 40px;
        }
        [data-theme="dark"] select.si-input { background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3e%3cpath fill='%23a78bfa' d='M1.4 0l4.6 4.6L10.6 0 12 1.4 6 7.4 0 1.4z'/%3e%3c/svg%3e"); }
        select.si-input option { background: var(--si-card); color: var(--si-text); }

        /* Type pills */
        .si-type { display: flex; gap: 10px; flex-wrap: wrap; }
        .si-type-pill {
            flex: 1; min-width: 130px;
            border: 1.5px solid var(--si-border);
            background: var(--si-input);
            border-radius: 14px;
            padding: 15px 16px;
            cursor: pointer;
            text-align: center;
            transition: all .2s ease;
        }
        .si-type-pill i { font-size: 1.1rem; display: block; margin-bottom: 7px; color: var(--si-muted); }
        .si-type-pill b { font-size: 0.85rem; font-weight: 700; color: var(--si-text); display: block; }
        .si-type-pill input { display: none; }
        .si-type-pill.sel { border-color: var(--si-primary); background: var(--si-soft); box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.12); }
        .si-type-pill.sel i, .si-type-pill.sel b { color: var(--si-primary); }

        .si-btn {
            display: inline-flex; align-items: center; gap: 9px;
            padding: 13px 30px; border-radius: 13px;
            font-size: 0.92rem; font-weight: 700;
            border: none; cursor: pointer;
            transition: transform .2s ease, box-shadow .2s ease;
        }
        .si-btn:hover { transform: translateY(-2px); }
        .si-btn-primary {
            background: linear-gradient(135deg, var(--si-primary), var(--si-primary-2));
            color: #fff;
            box-shadow: 0 10px 22px rgba(79, 70, 229, 0.35);
        }
        .si-btn-ghost {
            background: transparent;
            border: 1.5px solid var(--si-border);
            color: var(--si-muted);
        }
        .si-btn-ghost:hover { border-color: var(--si-primary); color: var(--si-primary); }

        /* ── Interviews list ── */
        .si-list { display: flex; flex-direction: column; gap: 14px; }
        .si-card {
            background: var(--si-input);
            border: 1px solid var(--si-border);
            border-radius: 15px;
            padding: 18px 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
            transition: border-color .2s ease, transform .2s ease;
            animation: siIn .35s ease both;
        }
        .si-card:hover { border-color: var(--si-primary); transform: translateY(-2px); }
        .si-card-main { flex: 1; min-width: 220px; }
        .si-card-main h4 { font-size: 0.98rem; font-weight: 700; margin: 0 0 2px; color: var(--si-text); }
        .si-card-main p { font-size: 0.8rem; color: var(--si-muted); margin: 0; }
        .si-card-main p i { margin-right: 5px; color: var(--si-primary); }
        .si-when { text-align: center; flex-shrink: 0; min-width: 110px; }
        .si-when b { display: block; font-size: 1.15rem; font-weight: 800; color: var(--si-text); }
        .si-when span { font-size: 0.72rem; color: var(--si-muted); font-weight: 600; text-transform: uppercase; letter-spacing: .4px; }
        .si-badge {
            font-size: 0.7rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: .5px;
            padding: 5px 13px; border-radius: 20px;
            display: inline-flex; align-items: center; gap: 6px;
            flex-shrink: 0;
        }
        .si-badge i { font-size: 0.6rem; }
        .si-badge.scheduled { background: rgba(59, 130, 246, 0.14); color: #3b82f6; }
        .si-badge.completed { background: rgba(16, 185, 129, 0.14); color: #10b981; }
        .si-badge.cancelled { background: rgba(239, 68, 68, 0.14); color: #ef4444; }
        .si-actions { display: flex; gap: 8px; flex-shrink: 0; margin-left: auto; }
        .si-act {
            display: inline-flex; align-items: center; justify-content: center; gap: 7px;
            padding: 9px 15px; border-radius: 11px;
            font-size: 0.8rem; font-weight: 600;
            border: 1.5px solid var(--si-border);
            background: var(--si-card);
            color: var(--si-text);
            text-decoration: none;
            cursor: pointer;
            transition: all .18s ease;
            white-space: nowrap;
        }
        .si-act:hover { transform: translateY(-2px); text-decoration: none; }
        .si-act-join { background: rgba(6, 182, 212, 0.12); border-color: rgba(6, 182, 212, 0.4); color: #06b6d4; }
        .si-act-join:hover { background: #06b6d4; color: #fff; }
        .si-act-complete { background: rgba(16, 185, 129, 0.12); border-color: rgba(16, 185, 129, 0.4); color: #10b981; }
        .si-act-complete:hover { background: #10b981; color: #fff; }
        .si-act-cancel { background: rgba(239, 68, 68, 0.12); border-color: rgba(239, 68, 68, 0.4); color: #ef4444; }
        .si-act-cancel:hover { background: #ef4444; color: #fff; }

        .si-location {
            display: inline-flex; align-items: center; gap: 6px;
            font-size: 0.76rem; color: var(--si-muted);
            background: var(--si-card);
            border: 1px solid var(--si-border);
            padding: 4px 11px; border-radius: 20px;
            margin-top: 7px;
        }

        /* Empty */
        .si-empty {
            text-align: center;
            padding: 56px 24px;
            background: var(--si-input);
            border: 1.5px dashed var(--si-border);
            border-radius: 16px;
        }
        .si-empty i { font-size: 3rem; color: var(--si-primary); opacity: .35; }
        .si-empty h4 { font-weight: 700; color: var(--si-text); margin-top: 14px; }
        .si-empty p { color: var(--si-muted); }

        /* Toast */
        .si-toast {
            position: fixed; top: 84px; right: 24px; z-index: 9999;
            background: var(--si-card);
            border: 1px solid var(--si-border);
            border-left: 4px solid #10b981;
            border-radius: 14px;
            padding: 15px 20px;
            display: flex; align-items: center; gap: 12px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.18);
            opacity: 0; transform: translateX(30px);
            transition: all .35s ease;
            pointer-events: none;
        }
        .si-toast.show { opacity: 1; transform: translateX(0); }
        .si-toast i { color: #10b981; font-size: 1.3rem; }
        .si-toast b { color: var(--si-text); font-size: 0.9rem; }

        /* Message box for errors */
        .si-alert {
            display: flex; align-items: center; gap: 12px;
            border-radius: 13px;
            padding: 14px 18px;
            font-size: 0.9rem; font-weight: 600;
            margin-bottom: 16px;
        }
        .si-alert.error { background: rgba(239, 68, 68, 0.10); border: 1px solid rgba(239, 68, 68, 0.35); color: #ef4444; }

        @media (max-width: 768px) {
            .si-wrap { padding: 22px 14px 60px; }
            .si-stats { grid-template-columns: repeat(2, 1fr); }
            .si-section { padding: 20px 18px; }
            .si-cand-chip { margin-left: 0; }
            .si-actions { width: 100%; margin-left: 0; }
            .si-actions .si-act { flex: 1; }
            .si-when { text-align: left; min-width: 0; }
        }
    </style>
</head>
<body>
    <?php include 'company_header.php'; ?>

    <div class="si-wrap">
        <!-- Hero -->
        <div class="si-hero">
            <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap: 14px;">
                <div>
                    <h1><i class="fas fa-calendar-check mr-2"></i>Schedule Interview</h1>
                    <p>Manage candidate interviews for <?php echo htmlspecialchars($company_name); ?></p>
                </div>
                <div style="display:flex; gap:10px; position:relative; z-index:1;">
                    <a href="view_applicants.php" class="si-hero-btn ghost"><i class="fas fa-users"></i>Applicants</a>
                    <?php if (isset($_GET['application_id'])): ?>
                        <a href="schedule_interview.php" class="si-hero-btn"><i class="fas fa-list"></i>All Interviews</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Stats -->
        <div class="si-stats">
            <div class="si-stat">
                <div class="si-stat-ico" style="background: rgba(99,102,241,.12); color:#6366f1;"><i class="fas fa-calendar-alt"></i></div>
                <div><b><?php echo count($interviews); ?></b><span>Total</span></div>
            </div>
            <div class="si-stat">
                <div class="si-stat-ico" style="background: rgba(59,130,246,.12); color:#3b82f6;"><i class="fas fa-hourglass-half"></i></div>
                <div><b><?php echo $scheduled_count; ?></b><span>Upcoming</span></div>
            </div>
            <div class="si-stat">
                <div class="si-stat-ico" style="background: rgba(16,185,129,.12); color:#10b981;"><i class="fas fa-circle-check"></i></div>
                <div><b><?php echo $completed_count; ?></b><span>Completed</span></div>
            </div>
            <div class="si-stat">
                <div class="si-stat-ico" style="background: rgba(239,68,68,.12); color:#ef4444;"><i class="fas fa-ban"></i></div>
                <div><b><?php echo $cancelled_count; ?></b><span>Cancelled</span></div>
            </div>
        </div>

        <!-- Schedule New Interview -->
        <div class="si-section">
            <div class="si-section-head">
                <div class="ico"><i class="fas fa-plus"></i></div>
                <div>
                    <h3>Schedule New Interview</h3>
                    <p>Pick a date, time and format for the candidate.</p>
                </div>
            </div>

            <?php if (!empty($error_msg)): ?>
                <div class="si-alert error"><i class="fas fa-circle-exclamation"></i><?php echo htmlspecialchars($error_msg); ?></div>
            <?php endif; ?>

            <?php if ($app): ?>
                <!-- Selected applicant -->
                <div class="si-candidate">
                    <?php echo si_avatar($app['username'], $avatar_gradients); ?>
                    <div class="si-cand-main">
                        <h4><?php echo htmlspecialchars($app['username']); ?></h4>
                        <p><i class="fas fa-briefcase"></i><?php echo htmlspecialchars($app['job_title']); ?> &middot; <i class="fas fa-envelope"></i><?php echo htmlspecialchars($app['email']); ?></p>
                    </div>
                    <span class="si-cand-chip"><i class="fas fa-tag"></i><?php echo htmlspecialchars($app['job_category']); ?></span>
                </div>

                <form method="POST" action="schedule_interview.php?application_id=<?php echo $app['id']; ?>" onsubmit="return siValidate()">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="si-field">
                                <label class="si-label"><i class="fas fa-calendar"></i>Interview Date <span class="req">*</span></label>
                                <input type="date" name="interview_date" class="si-input" required id="siDate"
                                       min="<?php echo date('Y-m-d'); ?>"
                                       value="<?php echo date('Y-m-d', strtotime('+3 days')); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="si-field">
                                <label class="si-label"><i class="fas fa-clock"></i>Interview Time <span class="req">*</span></label>
                                <input type="time" name="interview_time" class="si-input" required value="10:00">
                            </div>
                        </div>
                    </div>

                    <div class="si-field">
                        <label class="si-label"><i class="fas fa-video"></i>Interview Type <span class="req">*</span></label>
                        <div class="si-type">
                            <label class="si-type-pill sel">
                                <input type="radio" name="interview_type" value="Online" checked>
                                <i class="fas fa-video"></i><b>Online</b>
                            </label>
                            <label class="si-type-pill">
                                <input type="radio" name="interview_type" value="Phone">
                                <i class="fas fa-phone"></i><b>Phone</b>
                            </label>
                            <label class="si-type-pill">
                                <input type="radio" name="interview_type" value="In-Person">
                                <i class="fas fa-building"></i><b>In-Person</b>
                            </label>
                        </div>
                    </div>

                    <div class="si-field">
                        <label class="si-label"><i class="fas fa-map-marker-alt"></i>Location / Meeting Link</label>
                        <input type="text" name="location" class="si-input" placeholder="e.g. Zoom link, office address, phone number...">
                    </div>

                    <div class="si-field">
                        <label class="si-label"><i class="fas fa-sticky-note"></i>Notes / Instructions</label>
                        <textarea name="notes" class="si-input" rows="4" placeholder="Any preparation instructions, documents to bring, interview panel details..."></textarea>
                    </div>

                    <div style="display:flex; gap:12px; flex-wrap:wrap;">
                        <button type="submit" name="schedule_interview" class="si-btn si-btn-primary">
                            <i class="fas fa-calendar-check"></i>Schedule Interview
                        </button>
                        <a href="schedule_interview.php" class="si-btn si-btn-ghost"><i class="fas fa-times"></i>Cancel</a>
                    </div>
                </form>
            <?php else: ?>
                <?php if (isset($_GET['application_id']) && !$app): ?>
                    <div class="si-alert error"><i class="fas fa-circle-exclamation"></i>Application not found or you don't have permission to view it.</div>
                <?php else: ?>
                    <div class="si-empty">
                        <i class="fas fa-user-plus d-block"></i>
                        <h4>Select a Candidate</h4>
                        <p class="mb-0">Go to your applicants list and click "Schedule Interview" next to a candidate to get started.</p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Scheduled Interviews -->
        <div class="si-section">
            <div class="si-section-head">
                <div class="ico"><i class="fas fa-calendar-alt"></i></div>
                <div>
                    <h3>Scheduled Interviews</h3>
                    <p>All interviews scheduled for your job openings.</p>
                </div>
            </div>

            <?php if (empty($interviews)): ?>
                <div class="si-empty">
                    <i class="fas fa-calendar-times d-block"></i>
                    <h4>No Interviews Scheduled Yet</h4>
                    <p class="mb-0">Scheduled interviews will appear here once you start scheduling candidates.</p>
                </div>
            <?php else: ?>
                <div class="si-list">
                    <?php foreach ($interviews as $int):
                        $tcolor = $type_colors[$int['interview_type']] ?? ['#6366f1', 'fa-video'];
                        $scolor = $status_colors[$int['status']] ?? ['#3b82f6', 'fa-calendar-check'];
                        $is_upcoming = $int['status'] == 'scheduled' && strtotime($int['interview_date'] . ' ' . $int['interview_time']) >= time();
                    ?>
                        <div class="si-card">
                            <?php echo si_avatar($int['username'], $avatar_gradients); ?>

                            <div class="si-card-main">
                                <h4><?php echo htmlspecialchars($int['username']); ?></h4>
                                <p><i class="fas fa-briefcase"></i><?php echo htmlspecialchars($int['job_title']); ?> &middot; <i class="fas fa-video" style="color:<?php echo $tcolor[0]; ?>;"></i><?php echo htmlspecialchars($int['interview_type']); ?></p>
                                <?php if (!empty($int['location']) || (!empty($int['meeting_link']) && $int['interview_type'] == 'Online')): ?>
                                    <span class="si-location"><i class="fas fa-map-marker-alt"></i><?php echo htmlspecialchars($int['location'] ?: 'Meeting link auto-generated'); ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="si-when">
                                <b><?php echo date('M d', strtotime($int['interview_date'])); ?></b>
                                <span><?php echo date('g:i A', strtotime($int['interview_time'])); ?></span>
                                <?php if ($is_upcoming): ?>
                                    <span style="color:#f59e0b; font-size:.66rem; text-transform:none; letter-spacing:0;">Upcoming</span>
                                <?php endif; ?>
                            </div>

                            <span class="si-badge <?php echo $int['status']; ?>">
                                <i class="fas <?php echo $scolor[1]; ?>"></i><?php echo ucfirst($int['status']); ?>
                            </span>

                            <div class="si-actions">
                                <?php if ($int['status'] == 'scheduled'): ?>
                                    <?php if ($int['interview_type'] == 'Online' && !empty($int['meeting_link'])): ?>
                                        <a href="../seeker/video_interview.php?room=<?php echo urlencode($int['meeting_link']); ?>" target="_blank" class="si-act si-act-join">
                                            <i class="fas fa-video"></i>Join
                                        </a>
                                    <?php endif; ?>
                                    <form method="POST" action="schedule_interview.php" style="display:inline;" onsubmit="return confirm('Mark this interview as completed?');">
                                        <input type="hidden" name="interview_id" value="<?php echo $int['id']; ?>">
                                        <button type="submit" name="mark_completed" class="si-act si-act-complete">
                                            <i class="fas fa-check"></i>Complete
                                        </button>
                                    </form>
                                    <form method="POST" action="schedule_interview.php" style="display:inline;" onsubmit="return confirm('Are you sure you want to cancel this interview?');">
                                        <input type="hidden" name="interview_id" value="<?php echo $int['id']; ?>">
                                        <button type="submit" name="cancel_interview" class="si-act si-act-cancel">
                                            <i class="fas fa-times"></i>Cancel
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span style="font-size:.78rem; color:var(--si-muted);"><?php echo ucfirst($int['status']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Toast -->
    <div class="si-toast" id="siToast"><i class="fas fa-circle-check"></i><b id="siToastMsg"></b></div>

    <script>
        function siToast(msg) {
            const t = document.getElementById('siToast');
            document.getElementById('siToastMsg').textContent = msg;
            t.classList.add('show');
            clearTimeout(window._siToastT);
            window._siToastT = setTimeout(() => t.classList.remove('show'), 4000);
        }
        <?php if (isset($_GET['done'])): ?>
            <?php if ($_GET['done'] == 'scheduled'): ?>siToast('Interview scheduled! The candidate has been notified.');<?php endif; ?>
            <?php if ($_GET['done'] == 'completed'): ?>siToast('Interview marked as completed.');<?php endif; ?>
            <?php if ($_GET['done'] == 'cancelled'): ?>siToast('Interview has been cancelled.');<?php endif; ?>
        <?php endif; ?>

        // Interview type pills
        document.querySelectorAll('.si-type-pill').forEach(pill => {
            pill.addEventListener('click', () => {
                document.querySelectorAll('.si-type-pill').forEach(p => p.classList.remove('sel'));
                pill.classList.add('sel');
            });
        });

        // Date/time validation
        function siValidate() {
            const dateVal = document.getElementById('siDate').value;
            if (dateVal) {
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                if (new Date(dateVal) < today) {
                    alert('Interview date cannot be in the past.');
                    return false;
                }
            }
            return true;
        }
        const siDate = document.getElementById('siDate');
        if (siDate) siDate.min = new Date().toISOString().split('T')[0];
    </script>
</body>
</html>
