<?php
    session_start();
    include '../admin/dbcon.php';
    
    // Check if company is logged in
    if (!isset($_SESSION['company_id'])) {
        header('Location: ../company_login.php');
        exit;
    }
    
    $company_id = $_SESSION['company_id'];
    
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
            // Send notification to the user about status change
            include '../includes/functions.php';
            notify_application_status($con, $app['user_id'], $company_id, $app['job_title'], $company_name, $new_status);
            
            // Also send a message
            send_message($con, 'company', $company_id, 'user', $app['user_id'], 
                "Application Update: " . ucfirst($new_status),
                "Your application for {$app['job_title']} has been updated to: " . ucfirst($new_status),
                $app['job_id']);
            
            $success_msg = '<div class="alert alert-success"><i class="fas fa-check-circle mr-2"></i>Application status updated! The candidate has been notified.</div>';
            $app['application_status'] = $new_status;
        }
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Applicant Details | Company Dashboard</title>
    <?php include '../links.php'; ?>
    <style>
        /* Theme tokens */
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
            padding-top: 70px;
            min-height: 100vh;
            background: var(--bg-accent-1), var(--bg-accent-2), var(--bg-main);
            color: var(--text-primary);
            transition: background 0.4s ease, color 0.3s ease;
        }

        .navbar {
            background: var(--nav-bg);
            border: 1px solid var(--nav-border);
            box-shadow: 0 15px 35px var(--glass-shadow);
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
        }

        .navbar-brand {
            color: var(--text-primary) !important;
            font-weight: 700;
            font-size: 1.5rem;
        }

        .nav-link {
            color: var(--text-primary) !important;
            font-weight: 500;
            margin: 0 10px;
            transition: all 0.3s ease;
        }

        .nav-link:hover {
            color: #ffffff !important;
        }

        .btn-logout {
            background: var(--glass-bg);
            color: var(--text-primary) !important;
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 8px 25px;
            font-weight: 600;
            box-shadow: 0 8px 20px var(--glass-shadow);
        }

        .theme-toggle {
            background: var(--glass-bg);
            color: var(--text-primary);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 8px 16px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            box-shadow: 0 8px 20px var(--glass-shadow);
            transition: all 0.3s ease;
        }

        .theme-toggle:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 24px var(--glass-shadow);
        }

        .content-section {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 15px;
            padding: 40px;
            margin: 30px 0;
            box-shadow: 0 10px 30px var(--glass-shadow);
            backdrop-filter: blur(16px);
            color: var(--text-primary);
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 20px;
            color: var(--text-primary);
            border-bottom: 3px solid #667eea;
            padding-bottom: 10px;
        }

        .profile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
        }

        .info-box {
            background: var(--badge-bg);
            border: 1px solid var(--glass-border);
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 25px;
            color: var(--text-secondary);
        }

        .info-item {
            margin-bottom: 20px;
            display: flex;
            align-items: start;
        }

        .info-item i {
            width: 30px;
            color: #667eea;
            font-size: 1.2rem;
            margin-top: 3px;
        }

        .info-item strong {
            display: inline-block;
            width: 150px;
            color: var(--text-primary);
        }

        .info-item a {
            color: #667eea;
            text-decoration: none;
        }

        .info-item a:hover {
            text-decoration: underline;
        }

        .skills-tag {
            display: inline-block;
            background: var(--badge-bg);
            color: var(--text-primary);
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            margin: 5px;
            font-weight: 500;
            border: 1px solid var(--glass-border);
        }

        .quiz-score-card {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            text-align: center;
        }

        .quiz-score-card h2 {
            font-size: 3rem;
            font-weight: 700;
            margin: 20px 0;
        }

        .status-badge-lg {
            padding: 15px 30px;
            font-size: 1.1rem;
            font-weight: 600;
            border-radius: 25px;
        }

        .btn-action {
            padding: 12px 30px;
            border-radius: 25px;
            font-weight: 600;
            margin: 5px;
        }

        .form-control {
            border: 1px solid var(--glass-border);
            background: var(--glass-bg);
            color: var(--text-primary);
            padding: 6px 15px;
            border-radius: 12px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
            background: rgba(255, 255, 255, 0.9);
        }

        label {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        .badge {
            background: var(--badge-bg);
            border: 1px solid var(--glass-border);
            color: var(--text-primary);
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <?php if (!empty($_SESSION['company_logo']) && file_exists('../' . $_SESSION['company_logo'])): ?>
                    <img src="../<?php echo $_SESSION['company_logo']; ?>" alt="<?php echo $_SESSION['company_name']; ?>" 
                         style="height: 35px; width: auto; object-fit: contain; margin-right: 10px; background: white; padding: 3px; border-radius: 5px;">
                <?php else: ?>
                    <i class="fas fa-building mr-2"></i>
                <?php endif; ?>
                <?php echo $_SESSION['company_name']; ?>
            </a>
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ml-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php"><i class="fas fa-home mr-1"></i>Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="my_jobs.php"><i class="fas fa-briefcase mr-1"></i>My Jobs</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="post_job.php"><i class="fas fa-plus-circle mr-1"></i>Post Job</a>
                    </li>
                    <li class="nav-item active">
                        <a class="nav-link" href="view_applicants.php"><i class="fas fa-users mr-1"></i>Job Applicants</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="category_applicants.php"><i class="fas fa-user-graduate mr-1"></i>Category Applicants</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php"><i class="fas fa-user mr-1"></i>Profile</a>
                    </li>
                    <li class="nav-item d-flex align-items-center mx-2">
                        <button class="theme-toggle" id="themeToggle" type="button">
                            <i class="fas fa-moon mr-1"></i><span id="themeLabel">Dark</span>
                        </button>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-logout ml-3" href="logout.php"><i class="fas fa-sign-out-alt mr-1"></i>Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- Profile Header -->
        <div class="profile-header mt-4">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <h2><i class="fas fa-user-circle mr-2"></i><?php echo $app['username']; ?></h2>
                    <p class="mb-0"><i class="fas fa-briefcase mr-2"></i>Applied for: <strong><?php echo $app['job_title']; ?></strong></p>
                    <p class="mb-0"><i class="fas fa-calendar mr-2"></i>Applied on: <?php echo date('M d, Y h:i A', strtotime($app['applied_date'])); ?></p>
                </div>
                <div class="text-right">
                    <?php if ($app['quiz_status'] == 'passed'): ?>
                        <span class="badge badge-success status-badge-lg">
                            <i class="fas fa-check-circle mr-2"></i>Quiz Passed
                        </span>
                    <?php elseif ($app['quiz_status'] == 'failed'): ?>
                        <span class="badge badge-danger status-badge-lg">
                            <i class="fas fa-times-circle mr-2"></i>Quiz Failed
                        </span>
                    <?php else: ?>
                        <span class="badge badge-warning status-badge-lg">
                            <i class="fas fa-hourglass-half mr-2"></i>Quiz Not Taken
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Contact & Personal Info -->
            <div class="col-md-6">
                <div class="content-section">
                    <h3 class="section-title"><i class="fas fa-id-card mr-2"></i>Contact Information</h3>
                    <div class="info-box">
                        <div class="info-item">
                            <i class="fas fa-user"></i>
                            <div>
                                <strong>Full Name:</strong><br>
                                <?php echo $app['username']; ?>
                            </div>
                        </div>
                        <div class="info-item">
                            <i class="fas fa-envelope"></i>
                            <div>
                                <strong>Email:</strong><br>
                                <a href="mailto:<?php echo $app['email']; ?>"><?php echo $app['email']; ?></a>
                            </div>
                        </div>
                        <div class="info-item">
                            <i class="fas fa-phone"></i>
                            <div>
                                <strong>Phone:</strong><br>
                                <a href="tel:<?php echo $app['phone']; ?>"><?php echo $app['phone']; ?></a>
                            </div>
                        </div>
                        <div class="info-item">
                            <i class="fas fa-graduation-cap"></i>
                            <div>
                                <strong>Education:</strong><br>
                                <?php echo $app['user_degree']; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quiz Results -->
            <div class="col-md-6">
                <div class="content-section">
                    <h3 class="section-title"><i class="fas fa-chart-bar mr-2"></i>Quiz Performance</h3>
                    <?php if ($app['quiz_status'] != 'not_taken' && $app['total_questions']): ?>
                        <div class="quiz-score-card">
                            <i class="fas fa-trophy fa-3x mb-3"></i>
                            <h2><?php echo number_format($app['score_percentage'], 1); ?>%</h2>
                            <p class="mb-0">Score</p>
                            <hr style="background: rgba(255,255,255,0.3);">
                            <div class="row mt-3">
                                <div class="col-6">
                                    <h4><?php echo $app['correct_answers']; ?>/<?php echo $app['total_questions']; ?></h4>
                                    <small>Correct Answers</small>
                                </div>
                                <div class="col-6">
                                    <h4><?php echo $app['time_taken'] ? gmdate("i:s", $app['time_taken']) : 'N/A'; ?></h4>
                                    <small>Time Taken</small>
                                </div>
                            </div>
                            <small class="mt-3 d-block">Attempted on: <?php echo date('M d, Y', strtotime($app['attempt_date'])); ?></small>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info text-center">
                            <i class="fas fa-info-circle fa-3x mb-3"></i>
                            <h5>Quiz Not Taken Yet</h5>
                            <p class="mb-0">The candidate has not attempted the quiz yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Skills -->
        <div class="content-section">
            <h3 class="section-title"><i class="fas fa-code mr-2"></i>Skills & Expertise</h3>
            <div class="info-box">
                <?php 
                    $skills = explode(',', $app['user_skills']);
                    foreach ($skills as $skill) {
                        echo '<span class="skills-tag">' . trim($skill) . '</span>';
                    }
                ?>
            </div>
        </div>

        <!-- Cover Letter -->
        <?php if ($app['cover_letter']): ?>
        <div class="content-section">
            <h3 class="section-title"><i class="fas fa-file-alt mr-2"></i>Cover Letter</h3>
            <div class="info-box">
                <?php echo nl2br($app['cover_letter']); ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Application Status Management -->
        <div class="content-section">
            <h3 class="section-title"><i class="fas fa-tasks mr-2"></i>Application Status</h3>
            
            <?php if (isset($success_msg)) echo $success_msg; ?>
            
            <div class="info-box">
                <p><strong>Current Status:</strong> 
                    <span class="badge badge-<?php 
                        echo $app['application_status'] == 'shortlisted' ? 'success' : 
                            ($app['application_status'] == 'reviewed' ? 'info' : 
                            ($app['application_status'] == 'rejected' ? 'danger' : 'warning')); 
                    ?>">
                        <?php echo strtoupper($app['application_status']); ?>
                    </span>
                </p>
                
                <form method="POST" action="" class="mt-3">
                    <div class="row align-items-end">
                        <div class="col-md-6">
                            <label>Update Application Status:</label>
                            <select name="application_status" class="form-control">
                                <option value="pending" <?php echo ($app['application_status'] == 'pending') ? 'selected' : ''; ?>>Pending Review</option>
                                <option value="reviewed" <?php echo ($app['application_status'] == 'reviewed') ? 'selected' : ''; ?>>Reviewed</option>
                                <option value="shortlisted" <?php echo ($app['application_status'] == 'shortlisted') ? 'selected' : ''; ?>>Shortlisted</option>
                                <option value="rejected" <?php echo ($app['application_status'] == 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <button type="submit" name="update_status" class="btn btn-primary">
                                <i class="fas fa-save mr-2"></i>Update Status
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Actions -->
        <div class="content-section">
            <h3 class="section-title"><i class="fas fa-tools mr-2"></i>Actions</h3>
            <div class="text-center">
                <?php if ($app['quiz_status'] == 'passed' || $app['application_status'] == 'shortlisted'): ?>
                    <a href="schedule_interview.php?application_id=<?php echo $app['id']; ?>" class="btn btn-warning btn-action mb-2">
                        <i class="fas fa-calendar-check mr-2"></i>Schedule Interview
                    </a>
                <?php endif; ?>
                <?php if ($app['profile']): ?>
                    <a href="../files/<?php echo $app['profile']; ?>" target="_blank" class="btn btn-success btn-action mb-2">
                        <i class="fas fa-file-pdf mr-2"></i>Download CV
                    </a>
                <?php endif; ?>
                <a href="../message_center.php?with=user_<?php echo $app['user_id']; ?>" class="btn btn-purple btn-action mb-2" style="background: #8b5cf6; color: white;">
                    <i class="fas fa-comments mr-2"></i>Send Message
                </a>
                <a href="mailto:<?php echo $app['email']; ?>?subject=Regarding Your Application for <?php echo urlencode($app['job_title']); ?>" 
                   class="btn btn-primary btn-action mb-2">
                    <i class="fas fa-envelope mr-2"></i>Send Email
                </a>
                <a href="tel:<?php echo $app['phone']; ?>" class="btn btn-info btn-action mb-2">
                    <i class="fas fa-phone mr-2"></i>Call Candidate
                </a>
                <a href="view_applicants.php?job_id=<?php echo $app['job_id']; ?>" class="btn btn-secondary btn-action mb-2">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Applicants
                </a>
            </div>
        </div>
    </div>

    <footer class="text-center py-4 mt-5">
        <p class="text-muted">&copy; 2026 NovaHire. All rights reserved.</p>
    </footer>

    <script>
        // Theme persistence and toggle
        (function() {
            const root = document.documentElement;
            const themeToggle = document.getElementById('themeToggle');
            const themeLabel = document.getElementById('themeLabel');
            const storageKey = 'company-theme';

            function apply(theme) {
                root.setAttribute('data-theme', theme);
                if (theme === 'dark') {
                    themeLabel.textContent = 'Dark';
                    themeToggle.innerHTML = '<i class="fas fa-moon mr-1"></i><span id="themeLabel">Dark</span>';
                } else {
                    themeLabel.textContent = 'Light';
                    themeToggle.innerHTML = '<i class="fas fa-sun mr-1"></i><span id="themeLabel">Light</span>';
                }
                localStorage.setItem(storageKey, theme);
            }

            // Apply stored theme on load
            const storedTheme = localStorage.getItem(storageKey) || 'dark';
            apply(storedTheme);

            // Toggle theme on button click
            themeToggle.addEventListener('click', function(e) {
                e.preventDefault();
                const currentTheme = root.getAttribute('data-theme') || 'dark';
                const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
                apply(newTheme);
            });
        })();
    </script>
</body>
</html>
