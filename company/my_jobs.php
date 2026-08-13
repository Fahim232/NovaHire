<?php
    session_start();
    include '../admin/dbcon.php';
    
    // Check if company is logged in
    if (!isset($_SESSION['company_id'])) {
        header('Location: ../company_login.php');
        exit;
    }
    
    $company_id = $_SESSION['company_id'];
    
    // Fetch jobs
    $jobs_query = "SELECT cj.*, 
                   (SELECT COUNT(*) FROM job_applications WHERE job_id = cj.id) as application_count,
                   (SELECT COUNT(*) FROM job_applications WHERE job_id = cj.id AND quiz_status = 'passed') as qualified_count,
                   (SELECT COUNT(*) FROM company_job_questions WHERE job_id = cj.id) as quiz_count
                   FROM company_jobs cj 
                   WHERE cj.company_id = $company_id 
                   ORDER BY cj.posted_date DESC";
    $jobs_result = mysqli_query($con, $jobs_query);
    
    // Handle job status update
    if (isset($_POST['update_status'])) {
        $job_id = intval($_POST['job_id']);
        $new_status = mysqli_real_escape_string($con, $_POST['status']);
        $update_query = "UPDATE company_jobs SET status = '$new_status' WHERE id = $job_id AND company_id = $company_id";
        if (mysqli_query($con, $update_query)) {
            $success_msg = '<div class="alert alert-success">Job status updated successfully!</div>';
            header("Refresh:1");
        }
    }
    
    // Handle job deletion
    if (isset($_GET['delete_id'])) {
        $delete_id = intval($_GET['delete_id']);
        $delete_query = "DELETE FROM company_jobs WHERE id = $delete_id AND company_id = $company_id";
        if (mysqli_query($con, $delete_query)) {
            $success_msg = '<div class="alert alert-success">Job deleted successfully!</div>';
            header("Refresh:1");
        }
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>My Jobs | Company Dashboard</title>
    <?php include '../links.php'; ?>
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }

        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
        }

        body {
            padding-top: 70px;
        }

        .navbar-brand {
            color: white !important;
            font-weight: 700;
            font-size: 1.5rem;
        }

        .nav-link {
            color: rgba(255,255,255,0.9) !important;
            font-weight: 500;
            margin: 0 10px;
        }

        .btn-logout {
            background: var(--glass-bg);
            color: var(--text-primary) !important;
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 8px 25px;
            font-weight: 600;
            box-shadow: 0 8px 20px var(--glass-shadow);
            transition: all 0.25s ease;
        }

        .content-section {
            background: white;
            border-radius: 15px;
            padding: 40px;
            margin: 30px 0;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .section-title {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 30px;
            color: #333;
        }

        .job-card {
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            transition: all 0.3s;
        }

        .job-card:hover {
            border-color: #667eea;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.2);
        }

        .job-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }

        .badge {
            padding: 8px 15px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .btn-action {
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            margin: 3px;
        }

        .job-stats {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;

        /* Theme tokens + glass overrides */
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
            background: var(--bg-accent-1), var(--bg-accent-2), var(--bg-main);
            color: var(--text-primary);
            transition: background 0.4s ease, color 0.3s ease;
        }

        .navbar {
            background: var(--nav-bg);
            border: 1px solid var(--nav-border);
            box-shadow: 0 15px 35px var(--glass-shadow);
            color: var(--text-primary);
        }

        .navbar-brand, .nav-link {
            color: var(--text-primary) !important;
        }

        .content-section,
        .job-card,
        .card {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            box-shadow: 0 10px 30px var(--glass-shadow);
            backdrop-filter: blur(18px);
            color: var(--text-primary);
        }

        .badge {
            background: var(--badge-bg);
            color: var(--text-primary);
        }

        .section-title {
            color: var(--text-primary);
        }

        p, label, .text-muted {
            color: var(--text-secondary) !important;
        }

        .form-control {
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: var(--text-primary);
        }

        .theme-toggle {
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 30px;
            padding: 6px 14px;
            background: var(--glass-bg);
            color: var(--text-primary);
            box-shadow: 0 8px 20px var(--glass-shadow);
            backdrop-filter: blur(14px);
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .theme-toggle:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 25px var(--glass-shadow);
        }

        /* Glassmorphism theme */
        body {
            padding-top: 70px;
            min-height: 100vh;
            background: radial-gradient(circle at 10% 20%, rgba(102, 126, 234, 0.28), transparent 25%),
                        radial-gradient(circle at 90% 10%, rgba(244, 114, 182, 0.22), transparent 28%),
                        linear-gradient(135deg, #0f172a 0%, #111827 50%, #0b1021 100%);
            color: #e8edff;
        }

        body::before {
            content: '';
            position: fixed;
            inset: 0;
            pointer-events: none;
            background: radial-gradient(circle at 50% 50%, rgba(255, 255, 255, 0.06), transparent 45%);
            z-index: 0;
        }

        .navbar {
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.15);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.35);
            backdrop-filter: blur(16px);
        }

        .navbar-brand, .nav-link {
            color: #e8edff !important;
        }

        .nav-link:hover {
            color: #ffffff !important;
            text-shadow: 0 0 10px rgba(255, 255, 255, 0.35);
        }

        .content-section,
        .stat-card,
        .job-card,
        .quick-action-btn,
        .profile-card,
        .quiz-card,
        .applicant-card,
        .detail-card,
        .card {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.14);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.35);
            backdrop-filter: blur(18px);
            color: #e8edff;
        }

        .quick-action-btn {
            color: #f8f9ff !important;
            text-shadow: 0 5px 20px rgba(0, 0, 0, 0.35);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.35);
        }

        h1, h2, h3, h4, h5, h6 {
            color: #f3f4ff;
        }

        p, label, .text-muted {
            color: rgba(232, 237, 255, 0.85) !important;
        }

        .form-control {
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #e8edff;
        }

        .form-control:focus {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(255, 255, 255, 0.35);
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
            color: #ffffff;
        }

        .badge {
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.25);
            color: #e8edff;
        }
        }

        .stat-item {
            display: inline-block;
            margin-right: 20px;
        }

        .stat-item i {
            color: #667eea;
            margin-right: 5px;
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
                    <li class="nav-item active">
                        <a class="nav-link" href="my_jobs.php"><i class="fas fa-briefcase mr-1"></i>My Jobs</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="post_job.php"><i class="fas fa-plus-circle mr-1"></i>Post Job</a>
                    </li>
                    <li class="nav-item">
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
        <div class="content-section">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="section-title mb-0"><i class="fas fa-briefcase mr-3"></i>My Job Postings</h2>
                <a href="post_job.php" class="btn btn-primary">
                    <i class="fas fa-plus-circle mr-2"></i>Post New Job
                </a>
            </div>
            
            <?php if (isset($success_msg)) echo $success_msg; ?>
            
            <?php if (mysqli_num_rows($jobs_result) > 0): ?>
                <?php while ($job = mysqli_fetch_assoc($jobs_result)): ?>
                    <div class="job-card">
                        <div class="job-header">
                            <div>
                                <h4><?php echo $job['job_title']; ?></h4>

                        <script>
                            (function() {
                                const root = document.documentElement;
                                const toggle = document.getElementById('themeToggle');
                                const label = document.getElementById('themeLabel');
                                const stored = localStorage.getItem('company-theme') || 'dark';

                                function apply(theme) {
                                    root.setAttribute('data-theme', theme);
                                    label.textContent = theme === 'dark' ? 'Dark' : 'Light';
                                    toggle.innerHTML = (theme === 'dark' ? '<i class="fas fa-moon mr-1"></i>' : '<i class="fas fa-sun mr-1"></i>') + label.textContent;
                                }

                                apply(stored);

                                toggle.addEventListener('click', () => {
                                    const next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
                                    localStorage.setItem('company-theme', next);
                                    apply(next);
                                });
                            })();
                        </script>
                                <p class="text-muted mb-2">
                                    <i class="fas fa-tag mr-2"></i><?php echo $job['job_category']; ?>
                                    <span class="ml-3"><i class="fas fa-map-marker-alt mr-2"></i><?php echo $job['location']; ?></span>
                                    <span class="ml-3"><i class="fas fa-briefcase mr-2"></i><?php echo $job['employment_type']; ?></span>
                                </p>
                                <p class="text-muted mb-0">
                                    <i class="fas fa-calendar mr-2"></i>Posted: <?php echo date('M d, Y', strtotime($job['posted_date'])); ?>
                                    <?php if ($job['deadline']): ?>
                                        <span class="ml-3"><i class="fas fa-clock mr-2"></i>Deadline: <?php echo date('M d, Y', strtotime($job['deadline'])); ?></span>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div>
                                <span class="badge badge-<?php echo $job['status'] == 'active' ? 'success' : ($job['status'] == 'closed' ? 'danger' : 'secondary'); ?>">
                                    <?php echo strtoupper($job['status']); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="job-stats">
                            <div class="stat-item">
                                <i class="fas fa-users"></i>
                                <strong><?php echo $job['application_count']; ?></strong> Applications
                            </div>
                            <div class="stat-item">
                                <i class="fas fa-user-check"></i>
                                <strong><?php echo $job['qualified_count']; ?></strong> Qualified
                            </div>
                            <div class="stat-item">
                                <i class="fas fa-question-circle"></i>
                                <strong><?php echo $job['quiz_count']; ?></strong> Quiz Questions
                            </div>
                            <div class="stat-item">
                                <i class="fas fa-users-cog"></i>
                                <strong><?php echo $job['vacancy_count']; ?></strong> Vacancies
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <a href="edit_job.php?id=<?php echo $job['id']; ?>" class="btn btn-primary btn-action">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <a href="manage_quiz.php?job_id=<?php echo $job['id']; ?>" class="btn btn-info btn-action">
                                <i class="fas fa-question-circle"></i> Manage Quiz (<?php echo $job['quiz_count']; ?>)
                            </a>
                            <a href="view_applicants.php?job_id=<?php echo $job['id']; ?>" class="btn btn-success btn-action">
                                <i class="fas fa-users"></i> View Applicants (<?php echo $job['application_count']; ?>)
                            </a>
                            
                            <!-- Status Update -->
                            <div class="btn-group" role="group">
                                <button id="btnGroupDrop<?php echo $job['id']; ?>" type="button" class="btn btn-warning btn-action dropdown-toggle" data-toggle="dropdown">
                                    <i class="fas fa-cog"></i> Change Status
                                </button>
                                <div class="dropdown-menu">
                                    <form method="POST" action="" style="display:inline;">
                                        <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                                        <button type="submit" name="update_status" value="active" class="dropdown-item">
                                            <i class="fas fa-check-circle text-success"></i> Active
                                        </button>
                                        <button type="submit" name="update_status" value="closed" class="dropdown-item">
                                            <i class="fas fa-times-circle text-danger"></i> Closed
                                        </button>
                                        <button type="submit" name="update_status" value="draft" class="dropdown-item">
                                            <i class="fas fa-file text-secondary"></i> Draft
                                        </button>
                                        <input type="hidden" name="status" id="status<?php echo $job['id']; ?>">
                                    </form>
                                </div>
                            </div>
                            
                            <a href="?delete_id=<?php echo $job['id']; ?>" class="btn btn-danger btn-action" 
                               onclick="return confirm('Are you sure you want to delete this job? This will also delete all applications and quiz questions.')">
                                <i class="fas fa-trash"></i> Delete
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="alert alert-info text-center">
                    <i class="fas fa-info-circle mr-2"></i>No jobs posted yet. <a href="post_job.php">Post your first job!</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <footer class="text-center py-4 mt-5">
        <p class="text-muted">&copy; 2026 NovaHire. All rights reserved.</p>
    </footer>

    <script>
        // Handle status update
        document.querySelectorAll('.dropdown-item[name="update_status"]').forEach(function(button) {
            button.addEventListener('click', function(e) {
                const form = this.closest('form');
                const jobId = form.querySelector('input[name="job_id"]').value;
                const statusInput = document.getElementById('status' + jobId);
                statusInput.value = this.value;
            });
        });
    </script>
</body>
</html>
