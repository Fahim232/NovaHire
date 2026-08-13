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
    $success_msg = '<div class="alert alert-success"><i class="fas fa-check-circle mr-2"></i>Interview marked as completed.</div>';
}
if (isset($_POST['cancel_interview'])) {
    $int_id = intval($_POST['interview_id']);
    mysqli_query($con, "UPDATE interviews SET status='cancelled' WHERE id=$int_id AND company_id=$company_id");
    $success_msg = '<div class="alert alert-warning"><i class="fas fa-times-circle mr-2"></i>Interview has been cancelled.</div>';
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
        $error_msg = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle mr-2"></i>Please select both a date and time for the interview.</div>';
    } else {
        $meeting_link = '';
        if ($int_type == 'Online') {
            $meeting_link = 'jobportal_interview_' . uniqid();
        }

        // Insert into interviews table
        $insert_query = "INSERT INTO interviews (application_id, company_id, user_id, job_id, interview_date, interview_time, interview_type, location, meeting_link, notes)
                         VALUES ({$app['id']}, $company_id, {$app['user_id']}, {$app['job_id']}, '$int_date', '$int_time', '$int_type', '$location', '$meeting_link', '$notes')";
        mysqli_query($con, $insert_query);

        // Update application status to shortlisted
        mysqli_query($con, "UPDATE job_applications SET application_status='shortlisted' WHERE id={$app['id']}");

        // Create notification for the user
        $formatted_date = date('F j, Y', strtotime($int_date));
        $formatted_time = date('g:i A', strtotime($int_time));
        $notif_title = "Interview Scheduled";
        $notif_message = "Your interview for <strong>{$app['job_title']}</strong> at <strong>$company_name</strong> has been scheduled for <strong>$formatted_date</strong> at <strong>$formatted_time</strong> ($int_type).";
        create_notification($con, 'user', $app['user_id'], 'company', $company_id, $notif_title, $notif_message, 'interview', 'interviews', mysqli_insert_id($con));

        $success_msg = '<div class="alert alert-success">
            <i class="fas fa-check-circle mr-2"></i><strong>Interview scheduled successfully!</strong><br>
            <small>Candidate has been notified. Application status updated to Shortlisted.</small>
        </div>';
        $app = null; // reset form
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Schedule Interview | Company Dashboard</title>
    <?php include '../links.php'; ?>
    <style>
        :root {
            --bg-main: linear-gradient(135deg, #eef2ff 0%, #f5f7ff 100%);
            --bg-accent-1: radial-gradient(circle at 10% 20%, rgba(102, 126, 234, 0.18), transparent 25%);
            --bg-accent-2: radial-gradient(circle at 90% 10%, rgba(244, 114, 182, 0.14), transparent 28%);
            --glass-bg: rgba(255, 255, 255, 0.65);
            --glass-border: rgba(255, 255, 255, 0.6);
            --glass-shadow: rgba(102, 126, 234, 0.2);
            --text-primary: #0f172a;
            --text-secondary: rgba(15, 23, 42, 0.75);
            --nav-bg: rgba(255, 255, 255, 0.75);
            --nav-border: rgba(255, 255, 255, 0.6);
            --badge-bg: rgba(255, 255, 255, 0.6);
        }
        [data-theme="dark"] {
            --bg-main: linear-gradient(135deg, #0f172a 0%, #111827 50%, #0b1021 100%);
            --bg-accent-1: radial-gradient(circle at 10% 20%, rgba(102, 126, 234, 0.32), transparent 25%);
            --bg-accent-2: radial-gradient(circle at 90% 10%, rgba(244, 114, 182, 0.26), transparent 28%);
            --glass-bg: rgba(255, 255, 255, 0.08);
            --glass-border: rgba(255, 255, 255, 0.14);
            --glass-shadow: rgba(0, 0, 0, 0.35);
            --text-primary: #e8edff;
            --text-secondary: rgba(232, 237, 255, 0.8);
            --nav-bg: rgba(255, 255, 255, 0.08);
            --nav-border: rgba(255, 255, 255, 0.15);
            --badge-bg: rgba(255, 255, 255, 0.12);
        }

        body {
            padding-top: 80px;
            min-height: 100vh;
            background: var(--bg-accent-1), var(--bg-accent-2), var(--bg-main);
            color: var(--text-primary);
            transition: background 0.4s ease, color 0.3s ease;
        }

        .glass-card {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            padding: 35px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px var(--glass-shadow);
            backdrop-filter: blur(16px);
            color: var(--text-primary);
        }

        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 35px 40px;
            border-radius: 16px;
            margin-bottom: 30px;
            box-shadow: 0 8px 30px rgba(102, 126, 234, 0.3);
        }
        .page-header h2 {
            margin: 0;
            font-weight: 700;
            font-size: 1.7rem;
        }
        .page-header p {
            margin: 5px 0 0;
            opacity: 0.9;
        }

        .section-title {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 20px;
            color: var(--text-primary);
            border-bottom: 3px solid #667eea;
            padding-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .section-title i {
            color: #667eea;
        }

        .applicant-card {
            background: var(--badge-bg);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .applicant-card .label {
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .applicant-card .value {
            color: var(--text-primary);
            font-size: 1.05rem;
            font-weight: 500;
        }

        .form-control {
            border: 1px solid var(--glass-border);
            background: var(--glass-bg);
            color: var(--text-primary);
            padding: 10px 15px;
            border-radius: 12px;
            transition: all 0.3s ease;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
            background: rgba(255, 255, 255, 0.9);
        }
        .form-control option {
            background: #fff;
            color: #333;
        }
        [data-theme="dark"] .form-control option {
            background: #1e293b;
            color: #e8edff;
        }

        label {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 6px;
        }
        label i {
            color: #667eea;
            margin-right: 6px;
        }

        .btn-schedule {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 40px;
            border-radius: 25px;
            font-weight: 600;
            font-size: 1.05rem;
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.35);
            transition: all 0.3s ease;
        }
        .btn-schedule:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.5);
            color: white;
        }

        .btn-complete {
            background: linear-gradient(135deg, #10b981 0%, #38ef7d 100%);
            color: white;
            border: none;
            padding: 6px 18px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.3s;
        }
        .btn-complete:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
            color: white;
        }

        .btn-cancel {
            background: linear-gradient(135deg, #ef4444 0%, #f87171 100%);
            color: white;
            border: none;
            padding: 6px 18px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.3s;
        }
        .btn-cancel:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
            color: white;
        }

        .interview-table {
            width: 100%;
        }
        .interview-table thead th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 14px 16px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        .interview-table thead th:first-child {
            border-radius: 12px 0 0 0;
        }
        .interview-table thead th:last-child {
            border-radius: 0 12px 0 0;
        }
        .interview-table tbody td {
            padding: 14px 16px;
            vertical-align: middle;
            border-bottom: 1px solid var(--glass-border);
            color: var(--text-primary);
        }
        .interview-table tbody tr:hover {
            background: rgba(102, 126, 234, 0.06);
        }
        .interview-table tbody tr:last-child td:first-child {
            border-radius: 0 0 0 12px;
        }
        .interview-table tbody tr:last-child td:last-child {
            border-radius: 0 0 12px 0;
        }

        .status-badge {
            padding: 5px 14px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
        }
        .status-scheduled {
            background: rgba(59, 130, 246, 0.15);
            color: #2563eb;
            border: 1px solid rgba(59, 130, 246, 0.3);
        }
        .status-completed {
            background: rgba(16, 185, 129, 0.15);
            color: #059669;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        .status-cancelled {
            background: rgba(239, 68, 68, 0.15);
            color: #dc2626;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .type-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.78rem;
            font-weight: 600;
        }
        .type-online { background: rgba(99, 102, 241, 0.15); color: #6366f1; }
        .type-phone { background: rgba(245, 158, 11, 0.15); color: #d97706; }
        .type-in-person { background: rgba(16, 185, 129, 0.15); color: #059669; }

        .no-interviews {
            text-align: center;
            padding: 50px 20px;
            color: var(--text-secondary);
        }
        .no-interviews i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: #667eea;
            opacity: 0.4;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .stat-row {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .stat-item {
            flex: 1;
            min-width: 120px;
            text-align: center;
            background: var(--badge-bg);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            padding: 18px 10px;
        }
        .stat-item .stat-num {
            font-size: 1.8rem;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .stat-item .stat-label {
            font-size: 0.8rem;
            color: var(--text-secondary);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .back-link {
            color: #667eea;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s;
        }
        .back-link:hover {
            text-decoration: underline;
            color: #764ba2;
        }

        @media (max-width: 768px) {
            .glass-card { padding: 20px; }
            .page-header { padding: 25px 20px; }
            .page-header h2 { font-size: 1.3rem; }
            .stat-row { gap: 10px; }
            .stat-item { min-width: 90px; padding: 12px 8px; }
            .stat-item .stat-num { font-size: 1.4rem; }
        }
    </style>
</head>
<body>
    <?php include 'company_header.php'; ?>

    <div class="container mt-4">
        <!-- Page Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h2><i class="fas fa-calendar-check mr-2"></i>Schedule Interview</h2>
                    <p class="mb-0">Manage candidate interviews for <?php echo htmlspecialchars($company_name); ?></p>
                </div>
                <div>
                    <?php if (isset($_GET['application_id'])): ?>
                        <a href="schedule_interview.php" class="btn btn-light btn-sm rounded-pill font-weight-bold">
                            <i class="fas fa-list mr-1"></i>View All Interviews
                        </a>
                    <?php endif; ?>
                    <a href="view_applicants.php" class="btn btn-outline-light btn-sm rounded-pill ml-2">
                        <i class="fas fa-arrow-left mr-1"></i>Back to Applicants
                    </a>
                </div>
            </div>
        </div>

        <!-- Stats -->
        <div class="stat-row">
            <div class="stat-item">
                <div class="stat-num"><?php
                    $scheduled_count = 0;
                    $completed_count = 0;
                    $cancelled_count = 0;
                    foreach ($interviews as $int) {
                        if ($int['status'] == 'scheduled') $scheduled_count++;
                        elseif ($int['status'] == 'completed') $completed_count++;
                        elseif ($int['status'] == 'cancelled') $cancelled_count++;
                    }
                    echo count($interviews);
                ?></div>
                <div class="stat-label">Total</div>
            </div>
            <div class="stat-item">
                <div class="stat-num"><?php echo $scheduled_count; ?></div>
                <div class="stat-label">Upcoming</div>
            </div>
            <div class="stat-item">
                <div class="stat-num"><?php echo $completed_count; ?></div>
                <div class="stat-label">Completed</div>
            </div>
            <div class="stat-item">
                <div class="stat-num"><?php echo $cancelled_count; ?></div>
                <div class="stat-label">Cancelled</div>
            </div>
        </div>

        <?php echo $success_msg; ?>
        <?php echo $error_msg; ?>

        <!-- Schedule Interview Form -->
        <div class="glass-card">
            <h3 class="section-title"><i class="fas fa-plus-circle"></i>Schedule New Interview</h3>

            <?php if ($app): ?>
                <!-- Selected Applicant Info -->
                <div class="applicant-card">
                    <div class="row">
                        <div class="col-md-4 mb-3 mb-md-0">
                            <div class="label"><i class="fas fa-user mr-1"></i>Candidate</div>
                            <div class="value"><?php echo htmlspecialchars($app['username']); ?></div>
                        </div>
                        <div class="col-md-4 mb-3 mb-md-0">
                            <div class="label"><i class="fas fa-briefcase mr-1"></i>Position</div>
                            <div class="value"><?php echo htmlspecialchars($app['job_title']); ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="label"><i class="fas fa-envelope mr-1"></i>Contact</div>
                            <div class="value">
                                <a href="mailto:<?php echo htmlspecialchars($app['email']); ?>" class="text-primary"><?php echo htmlspecialchars($app['email']); ?></a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Interview Form -->
                <form method="POST" action="schedule_interview.php?application_id=<?php echo $app['id']; ?>">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label><i class="fas fa-calendar"></i>Interview Date *</label>
                                <input type="date" name="interview_date" class="form-control" required
                                       min="<?php echo date('Y-m-d'); ?>"
                                       value="<?php echo date('Y-m-d', strtotime('+3 days')); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label><i class="fas fa-clock"></i>Interview Time *</label>
                                <input type="time" name="interview_time" class="form-control" required
                                       value="10:00">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label><i class="fas fa-video"></i>Interview Type *</label>
                                <select name="interview_type" class="form-control" required>
                                    <option value="Online">Online (Video Call)</option>
                                    <option value="Phone">Phone Call</option>
                                    <option value="In-Person">In-Person</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label><i class="fas fa-map-marker-alt"></i>Location / Meeting Link</label>
                                <input type="text" name="location" class="form-control"
                                       placeholder="e.g. Zoom link, office address, phone number...">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-sticky-note"></i>Notes / Instructions</label>
                        <textarea name="notes" class="form-control" rows="4"
                                  placeholder="Any preparation instructions, documents to bring, interview panel details..."></textarea>
                    </div>

                    <div class="text-center mt-4">
                        <button type="submit" name="schedule_interview" class="btn btn-schedule">
                            <i class="fas fa-calendar-check mr-2"></i>Schedule Interview
                        </button>
                        <a href="schedule_interview.php" class="btn btn-outline-secondary rounded-pill ml-3" style="padding: 12px 30px;">
                            <i class="fas fa-times mr-1"></i>Cancel
                        </a>
                    </div>
                </form>
            <?php else: ?>
                <!-- No application selected - show message -->
                <?php if (isset($_GET['application_id']) && !$app): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle mr-2"></i>Application not found or you don't have permission to view it.
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-user-plus fa-3x text-muted mb-3" style="opacity: 0.4;"></i>
                        <h5 class="text-muted">Select a Candidate to Schedule Interview</h5>
                        <p class="text-muted mb-3">Go to your applicants list and click "Schedule Interview" next to a candidate, or use the link format:</p>
                        <code class="bg-light p-2 rounded">schedule_interview.php?application_id=ID</code>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Scheduled Interviews Section -->
        <div class="glass-card">
            <h3 class="section-title"><i class="fas fa-calendar-alt"></i>Scheduled Interviews</h3>

            <?php if (empty($interviews)): ?>
                <div class="no-interviews">
                    <i class="fas fa-calendar-times d-block"></i>
                    <h5>No Interviews Scheduled Yet</h5>
                    <p>Scheduled interviews will appear here once you start scheduling candidates.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="interview-table">
                        <thead>
                            <tr>
                                <th><i class="fas fa-user mr-1"></i>Candidate</th>
                                <th><i class="fas fa-briefcase mr-1"></i>Position</th>
                                <th><i class="fas fa-calendar mr-1"></i>Date</th>
                                <th><i class="fas fa-clock mr-1"></i>Time</th>
                                <th><i class="fas fa-video mr-1"></i>Type</th>
                                <th><i class="fas fa-info-circle mr-1"></i>Status</th>
                                <th><i class="fas fa-cogs mr-1"></i>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($interviews as $int): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($int['username']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($int['job_title']); ?></td>
                                    <td>
                                        <i class="fas fa-calendar-day text-primary mr-1"></i>
                                        <?php echo date('M d, Y', strtotime($int['interview_date'])); ?>
                                    </td>
                                    <td>
                                        <i class="fas fa-clock text-info mr-1"></i>
                                        <?php echo date('g:i A', strtotime($int['interview_time'])); ?>
                                    </td>
                                    <td>
                                        <?php
                                            $type_class = strtolower(str_replace('-', '', $int['interview_type']));
                                            if ($int['interview_type'] == 'In-Person') $type_class = 'in-person';
                                        ?>
                                        <span class="type-badge type-<?php echo $type_class; ?>">
                                            <?php echo htmlspecialchars($int['interview_type']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $int['status']; ?>">
                                            <?php echo ucfirst($int['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($int['status'] == 'scheduled'): ?>
                                            <?php if ($int['interview_type'] == 'Online' && !empty($int['meeting_link'])): ?>
                                                <a href="../video_interview.php?room=<?php echo urlencode($int['meeting_link']); ?>" target="_blank" class="btn btn-sm btn-info" title="Join Video Interview">
                                                    <i class="fas fa-video"></i> Join
                                                </a>
                                            <?php endif; ?>
                                            <form method="POST" action="schedule_interview.php" style="display:inline;">
                                                <input type="hidden" name="interview_id" value="<?php echo $int['id']; ?>">
                                                <button type="submit" name="mark_completed" class="btn btn-complete" title="Mark as Completed">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                            <form method="POST" action="schedule_interview.php" style="display:inline;" onsubmit="return confirm('Are you sure you want to cancel this interview?');">
                                                <input type="hidden" name="interview_id" value="<?php echo $int['id']; ?>">
                                                <button type="submit" name="cancel_interview" class="btn btn-cancel" title="Cancel Interview">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-muted" style="font-size: 0.85rem;">
                                                <?php if ($int['location']): ?>
                                                    <i class="fas fa-map-marker-alt mr-1" title="<?php echo htmlspecialchars($int['location']); ?>"></i>
                                                <?php endif; ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <footer class="text-center py-4 mt-4">
        <p class="text-muted">&copy; 2026 NovaHire. All rights reserved.</p>
    </footer>

    <script>
        // Theme toggle
        (function() {
            const root = document.documentElement;
            const themeToggle = document.getElementById('themeToggle');
            const themeIcon = document.getElementById('themeIcon');
            const storageKey = 'company-theme';

            function apply(theme) {
                root.setAttribute('data-theme', theme);
                if (themeIcon) {
                    if (theme === 'dark') {
                        themeIcon.classList.remove('fa-sun');
                        themeIcon.classList.add('fa-moon');
                    } else {
                        themeIcon.classList.remove('fa-moon');
                        themeIcon.classList.add('fa-sun');
                    }
                }
                localStorage.setItem(storageKey, theme);
            }

            const stored = localStorage.getItem(storageKey) || 'dark';
            apply(stored);

            if (themeToggle) {
                themeToggle.addEventListener('click', function() {
                    const current = root.getAttribute('data-theme') || 'dark';
                    apply(current === 'dark' ? 'light' : 'dark');
                });
            }
        })();

        // Confirm before marking completed
        document.querySelectorAll('[name="mark_completed"]').forEach(function(btn) {
            btn.closest('form').addEventListener('submit', function(e) {
                if (!confirm('Mark this interview as completed?')) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>
