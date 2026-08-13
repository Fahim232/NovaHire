<?php
session_start();
if (!isset($_SESSION['id'])) {
    header('location: login.php');
    exit();
}

include 'admin/dbcon.php';

$user_id = $_SESSION['id'];

if (!isset($_GET['job_id'])) {
    header('location: browse_jobs.php');
    exit();
}

$job_id = mysqli_real_escape_string($con, $_GET['job_id']);

// Always verify quiz passed in DB - never trust URL params
$quiz_check = "SELECT score_percentage FROM job_quiz_attempts WHERE user_id = '$user_id' AND job_id = '$job_id' ORDER BY attempt_date DESC LIMIT 1";
$quiz_res = mysqli_query($con, $quiz_check);
$quiz_passed = false;
if (mysqli_num_rows($quiz_res) > 0) {
    $qr = mysqli_fetch_assoc($quiz_res);
    $quiz_passed = ($qr['score_percentage'] >= 60);
}

if (!$quiz_passed) {
    header('location: company_job_quiz.php?job_id=' . $job_id);
    exit();
}

include 'header.php';

$job_query = "SELECT cj.*, c.company_name, c.industry, c.logo,
               (SELECT COUNT(*) FROM company_job_questions WHERE job_id = cj.id) as quiz_count
               FROM company_jobs cj
               JOIN companies c ON cj.company_id = c.id
               WHERE cj.id = '$job_id' AND cj.status = 'active'";
$job_result = mysqli_query($con, $job_query);

if (mysqli_num_rows($job_result) == 0) {
    echo "<script>alert('Job not found'); window.location.href='browse_jobs.php';</script>";
    exit();
}
$job = mysqli_fetch_assoc($job_result);

$quiz_check2 = "SELECT score_percentage FROM job_quiz_attempts WHERE user_id = '$user_id' AND job_id = '$job_id' ORDER BY attempt_date DESC LIMIT 1";
$quiz_res2 = mysqli_query($con, $quiz_check2);
$quiz_score = 0;
if (mysqli_num_rows($quiz_res2) > 0) {
    $qrow = mysqli_fetch_assoc($quiz_res2);
    $quiz_score = round($qrow['score_percentage'], 1);
}

$check_query = "SELECT * FROM job_applications WHERE user_id = '$user_id' AND job_id = '$job_id' AND cover_letter IS NOT NULL AND cover_letter != ''";
$check_result = mysqli_query($con, $check_query);
$already_submitted = mysqli_num_rows($check_result) > 0;

if (strtotime($job['deadline']) < time()) {
    echo "<script>alert('Application deadline has passed.'); window.location.href='job_details.php?id=$job_id';</script>";
    exit();
}

$user_query = "SELECT * FROM user_info WHERE id = '$user_id'";
$user_result = mysqli_query($con, $user_query);
$user_data = mysqli_fetch_assoc($user_result);

$company_id = $job['company_id'];

$success_message = '';
$error_message = '';

if (isset($_POST['submit_application'])) {
    $cover_letter = mysqli_real_escape_string($con, $_POST['cover_letter']);

    if (empty(trim($_POST['cover_letter']))) {
        $error_message = "Please write a cover letter.";
    } else {
        $app_exists_query = "SELECT id FROM job_applications WHERE user_id = '$user_id' AND job_id = '$job_id'";
        $app_exists_result = mysqli_query($con, $app_exists_query);

        if (mysqli_num_rows($app_exists_result) > 0) {
            $existing_app = mysqli_fetch_assoc($app_exists_result);
            $quiz_status_val = $quiz_passed ? 'passed' : 'failed';
            $update_query = "UPDATE job_applications 
                            SET cover_letter = '$cover_letter', 
                                quiz_score = '$quiz_score', 
                                quiz_status = '$quiz_status_val', 
                                application_status = 'pending'
                            WHERE id = '{$existing_app['id']}'";
            $app_id = $existing_app['id'];
            mysqli_query($con, $update_query);
        } else {
            $quiz_status_val = $quiz_passed ? 'passed' : 'failed';
            $insert_query = "INSERT INTO job_applications 
                            (user_id, job_id, company_id, cover_letter, quiz_score, quiz_status, applied_date, application_status) 
                            VALUES 
                            ('$user_id', '$job_id', '$company_id', '$cover_letter', '$quiz_score', '$quiz_status_val', NOW(), 'pending')";
            if (mysqli_query($con, $insert_query)) {
                $app_id = mysqli_insert_id($con);
            } else {
                $error_message = "Failed to submit application. Please try again.";
            }
        }

        if (empty($error_message)) {
            $title = "New Application Received";
            $message = "<strong>" . htmlspecialchars($user_data['username']) . "</strong> has applied for <strong>" . htmlspecialchars($job['job_title']) . "</strong> and passed the assessment with a score of <strong>" . $quiz_score . "%</strong>.";
            create_notification($con, 'company', $company_id, 'user', $user_id, $title, $message, 'new_application', 'job_applications', $app_id);

            $msg_subject = "Application for " . $job['job_title'];
            $msg_body = "Dear " . htmlspecialchars($job['company_name']) . " team,\n\n";
            $msg_body .= "A new application has been submitted for the position of " . htmlspecialchars($job['job_title']) . ".\n\n";
            $msg_body .= "Applicant: " . htmlspecialchars($user_data['username']) . "\n";
            $msg_body .= "Quiz Score: " . $quiz_score . "%\n";
            $msg_body .= "Status: Assessment Passed\n\n";
            $msg_body .= "Please review the application at your earliest convenience.";
            send_message($con, 'user', $user_id, 'company', $company_id, $msg_subject, $msg_body, $job_id);

            $_SESSION['app_success_msg'] = "Your application for <strong>" . htmlspecialchars($job['job_title']) . "</strong> at <strong>" . htmlspecialchars($job['company_name']) . "</strong> has been submitted successfully! The company will review your application and quiz score (" . $quiz_score . "%) and get back to you.";
            echo "<script>window.location.href='seeker_dashboard.php';</script>";
            exit();
        }
    }
}

$show_success_banner = isset($_GET['submitted']) && isset($_SESSION['app_success_msg']);
$banner_message = '';
if ($show_success_banner) {
    $banner_message = $_SESSION['app_success_msg'];
    unset($_SESSION['app_success_msg']);
} elseif ($already_submitted) {
    $show_success_banner = true;
    $banner_message = "You have already submitted your application. The company will review your application and get back to you.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Apply for <?php echo htmlspecialchars($job['job_title']); ?> | NovaHire</title>
    <?php include 'links.php'; ?>
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }

        .app-container { max-width: 1100px; margin: 40px auto 60px; padding: 0 20px; }

        .app-main-card {
            background: white; border-radius: 24px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.12); overflow: hidden;
        }

        /* ── Success Banner ── */
        .success-banner {
            padding: 60px 40px; text-align: center; display: none;
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 50%, #bbf7d0 100%);
            position: relative; overflow: hidden;
        }
        .success-banner::before {
            content: ''; position: absolute; top: -50%; right: -20%;
            width: 400px; height: 400px;
            background: radial-gradient(circle, rgba(16,185,129,0.15) 0%, transparent 70%);
            border-radius: 50%;
        }
        .success-banner::after {
            content: ''; position: absolute; bottom: -30%; left: -10%;
            width: 300px; height: 300px;
            background: radial-gradient(circle, rgba(5,150,105,0.1) 0%, transparent 70%);
            border-radius: 50%;
        }
        .success-banner.show { display: block; animation: successFadeIn 0.5s ease-out; }
        @keyframes successFadeIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }
        @keyframes checkBounce {
            0% { transform: scale(0); }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }
        .success-banner .s-icon {
            width: 90px; height: 90px; border-radius: 50%; margin: 0 auto 24px;
            background: white; display: flex; align-items: center; justify-content: center;
            font-size: 40px; color: #059669;
            box-shadow: 0 8px 30px rgba(5,150,105,0.2);
            position: relative; z-index: 1;
            animation: checkBounce 0.6s cubic-bezier(0.34, 1.56, 0.64, 1) 0.2s both;
        }
        .success-banner h2 {
            font-size: 28px; font-weight: 800; color: #065f46; margin-bottom: 10px;
            position: relative; z-index: 1; letter-spacing: -0.5px;
        }
        .success-banner p {
            color: #047857; font-size: 16px; margin-bottom: 30px; max-width: 480px;
            margin-left: auto; margin-right: auto; line-height: 1.6;
            position: relative; z-index: 1;
        }
        .success-banner .btn-view-app {
            display: inline-flex; align-items: center; gap: 8px;
            background: #059669; color: white;
            padding: 14px 36px; border-radius: 12px; text-decoration: none;
            font-weight: 700; font-size: 15px; transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(5,150,105,0.3);
            position: relative; z-index: 1;
        }
        .success-banner .btn-view-app:hover {
            background: #047857; color: white; text-decoration: none;
            transform: translateY(-2px); box-shadow: 0 8px 25px rgba(5,150,105,0.4);
        }
        .success-banner .btn-back-home {
            display: inline-flex; align-items: center; gap: 8px;
            background: white; color: #059669;
            padding: 14px 36px; border-radius: 12px; text-decoration: none;
            font-weight: 700; font-size: 15px; transition: all 0.3s;
            border: 2px solid #bbf7d0;
            position: relative; z-index: 1; margin-left: 12px;
        }
        .success-banner .btn-back-home:hover {
            border-color: #059669; background: #f0fdf4; color: #047857;
            text-decoration: none; transform: translateY(-2px);
        }

        /* ── Header Section ── */
        .app-header {
            padding: 40px 44px 32px;
            border-bottom: 1px solid #f1f5f9;
        }
        .app-header .top-row {
            display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px;
        }
        .app-header .back-link {
            color: #667eea; text-decoration: none; font-weight: 600; font-size: 14px;
            display: inline-flex; align-items: center; gap: 6px; transition: color 0.2s;
        }
        .app-header .back-link:hover { color: #764ba2; text-decoration: none; }
        .app-header h1 {
            font-size: 28px; font-weight: 800; color: #1e293b; margin: 16px 0 6px;
        }
        .app-header .subtitle { color: #64748b; font-size: 15px; margin: 0; }
        .quiz-pass-badge {
            display: inline-flex; align-items: center; gap: 8px;
            background: #d1fae5; color: #065f46; padding: 8px 18px;
            border-radius: 50px; font-weight: 700; font-size: 13px;
        }

        /* ── Job Info Banner ── */
        .job-info-banner {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 16px; padding: 24px 28px; margin: 0 44px 32px; color: white;
        }
        .job-info-banner .job-title-row {
            display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
        }
        .job-info-banner h3 {
            font-size: 20px; font-weight: 800; margin: 0 0 12px 0;
        }
        .job-info-banner .meta-pills {
            display: flex; flex-wrap: wrap; gap: 12px;
        }
        .job-info-banner .pill {
            background: rgba(255,255,255,0.2); padding: 6px 14px; border-radius: 20px;
            font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px;
        }

        /* ── Section Layout ── */
        .app-body { padding: 0 44px 44px; }
        .section-block { margin-bottom: 36px; }
        .section-title {
            font-size: 18px; font-weight: 700; color: #1e293b;
            margin-bottom: 18px; display: flex; align-items: center; gap: 10px;
            padding-bottom: 12px; border-bottom: 2px solid #e2e8f0;
        }
        .section-title i {
            width: 32px; height: 32px; border-radius: 8px;
            display: flex; align-items: center; justify-content: center; font-size: 14px;
        }
        .section-title .si-purple { background: #ede9fe; color: #7c3aed; }
        .section-title .si-blue { background: #dbeafe; color: #2563eb; }
        .section-title .si-green { background: #d1fae5; color: #059669; }
        .section-title .si-amber { background: #fef3c7; color: #d97706; }

        /* ── Profile Fields ── */
        .profile-grid {
            display: grid; grid-template-columns: 1fr 1fr; gap: 18px;
        }
        .profile-field { }
        .profile-field label {
            display: block; font-size: 13px; font-weight: 700; color: #64748b;
            text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;
        }
        .profile-field .field-value {
            background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px;
            padding: 12px 16px; font-size: 15px; color: #1e293b; font-weight: 500;
            min-height: 46px; display: flex; align-items: center;
        }
        .profile-field .field-value.editable {
            background: white; border: 2px solid #e2e8f0; padding: 0;
        }
        .profile-field .field-value.editable input,
        .profile-field .field-value.editable select {
            border: none; outline: none; background: transparent; width: 100%;
            padding: 12px 16px; font-size: 15px; color: #1e293b; font-weight: 500;
        }
        .profile-field .field-value.editable input:focus,
        .profile-field .field-value.editable select:focus {
            box-shadow: none;
        }
        .profile-field.full-width { grid-column: 1 / -1; }

        /* ── Compact CV Preview ── */
        .cv-preview-wrap {
            border: 2px solid #e2e8f0; border-radius: 16px; overflow: hidden;
            background: white;
        }
        .cv-preview {
            display: grid; grid-template-columns: 30% 70%; min-height: 380px;
        }
        .cv-sidebar-preview {
            background: linear-gradient(135deg, #1a3a52, #2c3e50);
            padding: 28px 20px; color: white;
        }
        .cv-sidebar-preview .cv-profile-img {
            width: 80px; height: 80px; border-radius: 50%; border: 3px solid white;
            margin: 0 auto 16px; object-fit: cover; display: block;
        }
        .cv-sidebar-preview .cv-section-title {
            font-size: 10px; text-transform: uppercase; letter-spacing: 1.5px;
            font-weight: 700; margin: 18px 0 10px; padding-bottom: 6px;
            border-bottom: 2px solid rgba(255,255,255,0.3); color: rgba(255,255,255,0.9);
        }
        .cv-sidebar-preview .cv-contact-item {
            display: flex; align-items: flex-start; gap: 8px;
            font-size: 11px; color: rgba(255,255,255,0.85); margin-bottom: 8px; line-height: 1.4;
        }
        .cv-sidebar-preview .cv-contact-item i { color: #e74c3c; font-size: 11px; margin-top: 2px; width: 14px; flex-shrink: 0; }
        .cv-sidebar-preview .cv-skill-tag {
            display: inline-block; background: rgba(255,255,255,0.12);
            border: 1px solid rgba(255,255,255,0.25); color: white;
            padding: 3px 10px; border-radius: 12px; font-size: 10px; font-weight: 600;
            margin: 0 4px 6px 0;
        }
        .cv-main-preview { padding: 28px 24px; }
        .cv-main-preview .cv-name {
            font-family: 'Playfair Display', serif; font-size: 20px; font-weight: 700;
            color: #1a3a52; margin: 0 0 4px;
        }
        .cv-main-preview .cv-role {
            font-size: 11px; text-transform: uppercase; letter-spacing: 1.5px;
            color: #2980b9; font-weight: 600; margin: 0 0 16px;
        }
        .cv-main-preview .cv-divider {
            width: 40px; height: 3px; background: linear-gradient(90deg, #2980b9, #e74c3c);
            margin-bottom: 18px; border-radius: 2px;
        }
        .cv-main-preview .cv-section-title {
            font-family: 'Playfair Display', serif; font-size: 13px; font-weight: 700;
            color: #1a3a52; margin: 0 0 12px; padding-bottom: 6px;
            border-bottom: 2px solid #2980b9; display: flex; align-items: center;
        }
        .cv-main-preview .cv-section-title::before {
            content: ''; display: inline-block; width: 5px; height: 5px;
            background: #e74c3c; border-radius: 50%; margin-right: 8px;
        }
        .cv-main-preview .cv-summary {
            font-size: 12px; line-height: 1.7; color: #4a5568;
            background: linear-gradient(135deg, rgba(41,128,185,0.08), rgba(231,76,60,0.04));
            padding: 14px; border-left: 3px solid #2980b9; border-radius: 4px;
        }
        .cv-main-preview .cv-edu-item { margin-bottom: 14px; }
        .cv-main-preview .cv-edu-title { font-weight: 700; font-size: 13px; color: #1a3a52; margin: 0; }
        .cv-main-preview .cv-edu-sub { color: #2980b9; font-size: 11px; font-weight: 600; margin: 3px 0; }
        .cv-main-preview .cv-edu-desc { font-size: 11px; color: #64748b; line-height: 1.6; margin: 0; }
        .cv-preview-toggle {
            display: flex; align-items: center; justify-content: space-between;
            padding: 14px 20px; background: #f8fafc; border-top: 1px solid #e2e8f0;
            cursor: pointer; transition: background 0.2s;
        }
        .cv-preview-toggle:hover { background: #f1f5f9; }
        .cv-preview-toggle span { font-weight: 700; font-size: 14px; color: #475569; display: flex; align-items: center; gap: 8px; }
        .cv-preview-toggle i { color: #667eea; transition: transform 0.3s; }
        .cv-preview-toggle.collapsed i { transform: rotate(-90deg); }

        /* ── Cover Letter ── */
        .cover-textarea {
            width: 100%; min-height: 180px; border: 2px solid #e2e8f0; border-radius: 12px;
            padding: 16px 18px; font-size: 15px; color: #1e293b; resize: vertical;
            font-family: inherit; line-height: 1.7; transition: border-color 0.3s;
        }
        .cover-textarea:focus { outline: none; border-color: #667eea; box-shadow: 0 0 0 3px rgba(102,126,234,0.1); }
        .cover-textarea::placeholder { color: #94a3b8; }

        /* ── Error ── */
        .alert-error {
            background: #fef2f2; border: 1px solid #fecaca; border-radius: 12px;
            padding: 16px 20px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px;
            color: #991b1b; font-weight: 500;
        }
        .alert-error i { color: #dc2626; font-size: 20px; }

        /* ── Submit Button ── */
        .submit-section { text-align: center; padding-top: 8px; }
        .btn-submit-app {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white; padding: 16px 60px; border-radius: 50px; border: none;
            font-size: 17px; font-weight: 700; cursor: pointer;
            transition: all 0.3s; box-shadow: 0 8px 30px rgba(102,126,234,0.3);
            letter-spacing: 0.3px;
        }
        .btn-submit-app:hover { transform: translateY(-3px); box-shadow: 0 12px 40px rgba(102,126,234,0.4); }
        .btn-submit-app:active { transform: translateY(-1px); }
        .btn-submit-app:disabled { background: #cbd5e0; cursor: not-allowed; box-shadow: none; transform: none; }
        .submit-note { color: #94a3b8; font-size: 13px; margin-top: 14px; }

        /* ── Footer ── */
        .app-footer {
            text-align: center; padding: 24px; color: rgba(255,255,255,0.7); font-size: 14px; margin-top: 10px;
        }

        @media (max-width: 768px) {
            .app-header, .app-body { padding-left: 24px; padding-right: 24px; }
            .job-info-banner { margin-left: 24px; margin-right: 24px; }
            .app-header h1 { font-size: 22px; }
            .profile-grid { grid-template-columns: 1fr; }
            .cv-preview { grid-template-columns: 1fr; }
            .cv-sidebar-preview { border-bottom: none; }
            .btn-submit-app { width: 100%; padding: 14px; }
            .success-banner { padding: 40px 24px; }
            .success-banner h2 { font-size: 22px; }
            .success-banner .btn-view-app,
            .success-banner .btn-back-home { display: block; width: 100%; margin: 0 0 12px; justify-content: center; text-align: center; }
            .success-banner .btn-back-home { margin-left: 0; }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <div class="app-main-card">
            <!-- Success Banner -->
            <div class="success-banner" id="successBanner">
                <div class="s-icon"><i class="fas fa-check"></i></div>
                <h2>Application Submitted Successfully!</h2>
                <p><?php echo $show_success_banner ? htmlspecialchars($banner_message) : 'Your application has been sent to the company. They will review your profile and quiz results.'; ?></p>
                <div>
                    <a href="my_application.php" class="btn-view-app"><i class="fas fa-list mr-2"></i>View My Applications</a>
                    <a href="seeker_dashboard.php" class="btn-back-home"><i class="fas fa-home mr-2"></i>Back to Dashboard</a>
                </div>
            </div>

            <!-- Header -->
            <div class="app-header">
                <div class="top-row">
                    <div>
                        <a href="job_details.php?id=<?php echo $job_id; ?>" class="back-link">
                            <i class="fas fa-arrow-left"></i> Back to Job Details
                        </a>
                        <h1><i class="fas fa-paper-plane mr-2" style="color: #667eea;"></i>Submit Application</h1>
                        <p class="subtitle">Complete your application for this position</p>
                    </div>
                    <div class="quiz-pass-badge">
                        <i class="fas fa-check-circle"></i> Quiz Passed &mdash; <?php echo $quiz_score; ?>%
                    </div>
                </div>
            </div>

            <!-- Job Info Banner -->
            <div class="job-info-banner">
                <h3><i class="fas fa-briefcase mr-2"></i><?php echo htmlspecialchars($job['job_title']); ?></h3>
                <div class="meta-pills">
                    <span class="pill"><i class="fas fa-building"></i><?php echo htmlspecialchars($job['company_name']); ?></span>
                    <span class="pill"><i class="fas fa-map-marker-alt"></i><?php echo htmlspecialchars($job['location']); ?></span>
                    <span class="pill"><i class="fas fa-clock"></i><?php echo htmlspecialchars($job['employment_type']); ?></span>
                    <?php if ($job['salary_range']): ?>
                        <span class="pill"><i class="fas fa-dollar-sign"></i><?php echo htmlspecialchars($job['salary_range']); ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <form method="POST" id="applicationForm">
                <div class="app-body">
                    <?php if (!empty($error_message)): ?>
                        <div class="alert-error">
                            <i class="fas fa-exclamation-circle"></i>
                            <?php echo htmlspecialchars($error_message); ?>
                        </div>
                    <?php endif; ?>

                    <!-- Your Profile -->
                    <div class="section-block">
                        <div class="section-title">
                            <i class="fas fa-user si-purple"></i> Your Profile Information
                        </div>
                        <div class="profile-grid">
                            <div class="profile-field">
                                <label>Full Name</label>
                                <div class="field-value"><?php echo htmlspecialchars($user_data['username']); ?></div>
                            </div>
                            <div class="profile-field">
                                <label>Email Address</label>
                                <div class="field-value"><?php echo htmlspecialchars($user_data['email']); ?></div>
                            </div>
                            <div class="profile-field">
                                <label>Phone Number</label>
                                <div class="field-value"><?php echo htmlspecialchars($user_data['phone'] ?: 'Not provided'); ?></div>
                            </div>
                            <div class="profile-field">
                                <label>Degree / Education</label>
                                <div class="field-value"><?php echo htmlspecialchars($user_data['user_degree'] ?: 'Not provided'); ?></div>
                            </div>
                            <div class="profile-field full-width">
                                <label>Skills</label>
                                <div class="field-value" style="flex-wrap: wrap; gap: 6px;">
                                    <?php
                                    $skills = explode(',', $user_data['user_skills'] ?? '');
                                    $has_skills = false;
                                    foreach ($skills as $s) {
                                        $s = trim($s);
                                        if ($s !== '') {
                                            $has_skills = true;
                                            echo '<span style="background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 4px 14px; border-radius: 20px; font-size: 13px; font-weight: 600;">' . htmlspecialchars($s) . '</span>';
                                        }
                                    }
                                    if (!$has_skills) echo '<span style="color: #94a3b8;">Not provided</span>';
                                    ?>
                                </div>
                            </div>
                        </div>
                        <div style="margin-top: 12px; font-size: 13px; color: #94a3b8;">
                            <i class="fas fa-info-circle mr-1"></i> To update your profile, visit your <a href="profile.php" style="color: #667eea; font-weight: 600;">profile page</a>.
                        </div>
                    </div>

                    <!-- Auto-Generated CV Preview -->
                    <div class="section-block">
                        <div class="cv-preview-toggle" id="cvToggle" onclick="toggleCvPreview()">
                            <span><i class="fas fa-file-alt" style="color: #667eea;"></i> Auto-Generated CV Preview</span>
                            <i class="fas fa-chevron-up" id="cvToggleIcon"></i>
                        </div>
                        <div class="cv-preview-wrap" id="cvPreviewWrap">
                            <div class="cv-preview">
                                <!-- Sidebar -->
                                <div class="cv-sidebar-preview">
                                    <?php if (!empty($user_data['profile'])): ?>
                                        <img src="images/<?php echo htmlspecialchars($user_data['profile']); ?>" alt="Profile" class="cv-profile-img">
                                    <?php else: ?>
                                        <div class="cv-profile-img" style="background: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; font-size: 28px; color: rgba(255,255,255,0.6);">
                                            <i class="fas fa-user"></i>
                                        </div>
                                    <?php endif; ?>

                                    <div class="cv-section-title">Contact</div>
                                    <div class="cv-contact-item">
                                        <i class="fas fa-envelope"></i>
                                        <span><?php echo htmlspecialchars($user_data['email']); ?></span>
                                    </div>
                                    <div class="cv-contact-item">
                                        <i class="fas fa-phone-alt"></i>
                                        <span><?php echo htmlspecialchars($user_data['phone'] ?: 'N/A'); ?></span>
                                    </div>
                                    <div class="cv-contact-item">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <span>Bangladesh</span>
                                    </div>

                                    <div class="cv-section-title">Skills</div>
                                    <div>
                                        <?php foreach ($skills as $s): ?>
                                            <?php if (trim($s) !== ''): ?>
                                                <span class="cv-skill-tag"><?php echo htmlspecialchars(trim($s)); ?></span>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <!-- Main -->
                                <div class="cv-main-preview">
                                    <h2 class="cv-name"><?php echo htmlspecialchars($user_data['username']); ?></h2>
                                    <p class="cv-role"><?php echo htmlspecialchars($user_data['user_degree'] ?: 'Professional'); ?></p>
                                    <div class="cv-divider"></div>

                                    <div style="margin-bottom: 20px;">
                                        <h3 class="cv-section-title">About</h3>
                                        <p class="cv-summary">
                                            Motivated and detail-oriented <?php echo htmlspecialchars($user_data['user_degree'] ?? 'professional'); ?> with a strong foundation in <?php echo htmlspecialchars($user_data['user_skills'] ?? 'relevant technologies'); ?>.
                                            Eager to join the workforce and contribute to projects that require innovative thinking and problem-solving skills.
                                        </p>
                                    </div>

                                    <div>
                                        <h3 class="cv-section-title">Education</h3>
                                        <div class="cv-edu-item">
                                            <p class="cv-edu-title"><?php echo htmlspecialchars($user_data['user_degree'] ?: 'Degree'); ?></p>
                                            <p class="cv-edu-sub">United International University</p>
                                            <p class="cv-edu-desc">Successfully completed degree with focus on core computing principles.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Cover Letter -->
                    <div class="section-block">
                        <div class="section-title">
                            <i class="fas fa-pen si-blue"></i> Cover Letter
                        </div>
                        <textarea
                            name="cover_letter"
                            class="cover-textarea"
                            required
                            placeholder="Tell <?php echo htmlspecialchars($job['company_name']); ?> why you're a great fit for the <?php echo htmlspecialchars($job['job_title']); ?> position. Mention your relevant experience, skills, and what excites you about this opportunity..."
                        ></textarea>
                    </div>

                    <!-- Submit -->
                    <div class="section-block submit-section">
                        <button type="submit" name="submit_application" class="btn-submit-app" id="submitBtn">
                            <i class="fas fa-paper-plane mr-2"></i>Submit Application
                        </button>
                        <p class="submit-note">
                            <i class="fas fa-shield-alt mr-1"></i> Your quiz score (<?php echo $quiz_score; ?>%) will be shared with the employer.
                        </p>
                    </div>
                </div>
            </form>
        </div>

        <div class="app-footer">
            <p class="mb-0">&copy; <?php echo date('Y'); ?> NovaHire. All rights reserved.</p>
        </div>
    </div>

    <script>
        function toggleCvPreview() {
            const wrap = document.getElementById('cvPreviewWrap');
            const icon = document.getElementById('cvToggleIcon');
            const toggle = document.getElementById('cvToggle');
            if (wrap.style.display === 'none') {
                wrap.style.display = 'block';
                icon.classList.remove('fa-chevron-down');
                icon.classList.add('fa-chevron-up');
                toggle.classList.remove('collapsed');
            } else {
                wrap.style.display = 'none';
                icon.classList.remove('fa-chevron-up');
                icon.classList.add('fa-chevron-down');
                toggle.classList.add('collapsed');
            }
        }

        document.getElementById('applicationForm').addEventListener('submit', function(e) {
            if (!confirm('Are you sure you want to submit your application?')) {
                e.preventDefault();
                return;
            }
            const btn = document.getElementById('submitBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Submitting...';
        });

        <?php if ($show_success_banner): ?>
            document.getElementById('successBanner').classList.add('show');
            document.querySelector('.app-header').style.display = 'none';
            document.querySelector('.job-info-banner').style.display = 'none';
            document.querySelector('.app-body').style.display = 'none';
        <?php endif; ?>
    </script>
</body>
</html>
