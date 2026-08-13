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
    
    // Filter by job_id if provided
    $job_filter = isset($_GET['job_id']) ? intval($_GET['job_id']) : 0;
    $status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
    
    // Build query
    $where_clause = "cj.company_id = $company_id";
    
    if ($job_filter > 0) {
        $where_clause .= " AND ja.job_id = $job_filter";
    }
    
    if ($status_filter != 'all') {
        $where_clause .= " AND ja.quiz_status = '$status_filter'";
    }
    
    // Fetch applications
    $applications_query = "SELECT ja.*, cj.job_title, cj.job_category, ui.username, ui.email, ui.phone, ui.user_degree, ui.user_skills, ui.profile 
                          FROM job_applications ja
                          JOIN company_jobs cj ON ja.job_id = cj.id
                          JOIN user_info ui ON ja.user_id = ui.id
                          WHERE $where_clause
                          ORDER BY ja.applied_date DESC";
    $applications_result = mysqli_query($con, $applications_query);
    
    // Fetch jobs for filter dropdown
    $jobs_query = "SELECT id, job_title FROM company_jobs WHERE company_id = $company_id ORDER BY job_title";
    $jobs_result = mysqli_query($con, $jobs_query);
    
    // Count statistics
    $stats_query = "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN ja.quiz_status = 'passed' THEN 1 ELSE 0 END) as passed,
        SUM(CASE WHEN ja.quiz_status = 'failed' THEN 1 ELSE 0 END) as failed,
        SUM(CASE WHEN ja.quiz_status = 'not_taken' THEN 1 ELSE 0 END) as not_taken
        FROM job_applications ja
        JOIN company_jobs cj ON ja.job_id = cj.id
        WHERE $where_clause";
    $stats_result = mysqli_query($con, $stats_query);
    $stats = mysqli_fetch_assoc($stats_result);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>View Applicants | Company Dashboard</title>
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
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 20px;
            color: var(--text-primary);
        }

        .stats-row {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }

        .stat-box {
            flex: 1 1 200px;
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 8px 22px var(--glass-shadow);
        }

        .stat-box h3 {
            font-size: 2rem;
            font-weight: 700;
            margin: 10px 0;
            color: var(--text-primary);
        }

        .stat-box p {
            color: var(--text-secondary);
            margin: 0;
        }

        .stat-box.blue { border-left: 4px solid #667eea; }
        .stat-box.green { border-left: 4px solid #28a745; }
        .stat-box.red { border-left: 4px solid #dc3545; }
        .stat-box.yellow { border-left: 4px solid #ffc107; }

        .filter-section {
            background: var(--badge-bg);
            border: 1px solid var(--glass-border);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            color: var(--text-primary);
        }

        .applicant-card {
            border: 1px solid var(--glass-border);
            background: var(--glass-bg);
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            transition: all 0.3s;
            box-shadow: 0 8px 20px var(--glass-shadow);
            color: var(--text-primary);
        }

        .applicant-card:hover {
            border-color: #667eea;
            box-shadow: 0 12px 28px var(--glass-shadow);
        }

        .applicant-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }

        .applicant-info h4 {
            margin: 0 0 10px 0;
            color: var(--text-primary);
        }

        .applicant-details {
            color: var(--text-secondary);
            font-size: 0.95rem;
        }

        .applicant-details i {
            width: 20px;
            color: #667eea;
        }

        .badge {
            padding: 8px 15px;
            font-size: 0.85rem;
            font-weight: 600;
            background: var(--badge-bg);
            border: 1px solid var(--glass-border);
            color: var(--text-primary);
        }

        .btn-action {
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 0.9rem;
            margin: 5px;
        }

        .skills-tag {
            display: inline-block;
            background: var(--badge-bg);
            color: var(--text-primary);
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.85rem;
            margin: 3px;
            border: 1px solid var(--glass-border);
        }

        .table thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .form-control {
            border: 1px solid var(--glass-border);
            background: var(--glass-bg);
            color: var(--text-primary);
            padding: 5px 15px;
            border-radius: 12px;
            box-shadow: 1px 2px var(--glass-shadow);
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
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <?php if (!empty($_SESSION['company_logo']) && file_exists('../' . $_SESSION['company_logo'])): ?>
                    <img src="../<?php echo $_SESSION['company_logo']; ?>" alt="<?php echo $company_name; ?>" 
                         style="height: 35px; width: auto; object-fit: contain; margin-right: 10px; background: white; padding: 3px; border-radius: 5px;">
                <?php else: ?>
                    <i class="fas fa-building mr-2"></i>
                <?php endif; ?>
                <?php echo $company_name; ?>
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

    <div class="container mt-4">
        <!-- Statistics -->
        <div class="stats-row">
            <div class="stat-box blue">
                <i class="fas fa-users fa-2x mb-2" style="color: #667eea;"></i>
                <h3><?php echo $stats['total']; ?></h3>
                <p>Total Applications</p>
            </div>
            <div class="stat-box green">
                <i class="fas fa-check-circle fa-2x mb-2" style="color: #28a745;"></i>
                <h3><?php echo $stats['passed']; ?></h3>
                <p>Passed Quiz</p>
            </div>
            <div class="stat-box red">
                <i class="fas fa-times-circle fa-2x mb-2" style="color: #dc3545;"></i>
                <h3><?php echo $stats['failed']; ?></h3>
                <p>Failed Quiz</p>
            </div>
            <div class="stat-box yellow">
                <i class="fas fa-hourglass-half fa-2x mb-2" style="color: #ffc107;"></i>
                <h3><?php echo $stats['not_taken']; ?></h3>
                <p>Not Taken</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="content-section">
            <h3 class="section-title"><i class="fas fa-filter mr-2"></i>Filter Applicants</h3>
            <div class="filter-section">
                <form method="GET" action="">
                    <div class="row">
                        <div class="col-md-5">
                            <label>Filter by Job</label>
                            <select name="job_id" class="form-control">
                                <option value="0">All Jobs</option>
                                <?php while ($job = mysqli_fetch_assoc($jobs_result)): ?>
                                    <option value="<?php echo $job['id']; ?>" <?php echo ($job_filter == $job['id']) ? 'selected' : ''; ?>>
                                        <?php echo $job['job_title']; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label>Filter by Status</label>
                            <select name="status" class="form-control">
                                <option value="all" <?php echo ($status_filter == 'all') ? 'selected' : ''; ?>>All Status</option>
                                <option value="passed" <?php echo ($status_filter == 'passed') ? 'selected' : ''; ?>>Passed</option>
                                <option value="failed" <?php echo ($status_filter == 'failed') ? 'selected' : ''; ?>>Failed</option>
                                <option value="not_taken" <?php echo ($status_filter == 'not_taken') ? 'selected' : ''; ?>>Not Taken</option>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary btn-block">
                                <i class="fas fa-search mr-2"></i>Filter
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Applicants List -->
        <div class="content-section">
            <h3 class="section-title"><i class="fas fa-users mr-2"></i>Applicants</h3>
            
            <?php if (mysqli_num_rows($applications_result) > 0): ?>
                <?php while ($app = mysqli_fetch_assoc($applications_result)): ?>
                    <div class="applicant-card">
                        <div class="applicant-header">
                            <div class="applicant-info">
                                <h4><i class="fas fa-user mr-2"></i><?php echo $app['username']; ?></h4>
                                <div class="applicant-details">
                                    <div><i class="fas fa-envelope"></i><?php echo $app['email']; ?></div>
                                    <div><i class="fas fa-phone"></i><?php echo $app['phone']; ?></div>
                                    <div><i class="fas fa-briefcase"></i>Applied for: <strong><?php echo $app['job_title']; ?></strong></div>
                                    <div><i class="fas fa-calendar"></i>Applied on: <?php echo date('M d, Y', strtotime($app['applied_date'])); ?></div>
                                </div>
                            </div>
                            <div class="text-right">
                                <?php if ($app['quiz_status'] == 'passed'): ?>
                                    <span class="badge badge-success mb-2">
                                        <i class="fas fa-check-circle mr-1"></i>Quiz Passed
                                    </span>
                                <?php elseif ($app['quiz_status'] == 'failed'): ?>
                                    <span class="badge badge-danger mb-2">
                                        <i class="fas fa-times-circle mr-1"></i>Quiz Failed
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-warning mb-2">
                                        <i class="fas fa-hourglass-half mr-1"></i>Quiz Not Taken
                                    </span>
                                <?php endif; ?>
                                <?php if ($app['quiz_score'] !== null): ?>
                                    <div class="mt-2">
                                        <span class="badge badge-info">Score: <?php echo $app['quiz_score']; ?>%</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <strong><i class="fas fa-graduation-cap mr-2"></i>Education:</strong> 
                            <?php echo $app['user_degree']; ?>
                        </div>
                        
                        <div class="mb-3">
                            <strong><i class="fas fa-code mr-2"></i>Skills:</strong>
                            <?php 
                                $skills = explode(',', $app['user_skills']);
                                foreach ($skills as $skill) {
                                    echo '<span class="skills-tag">' . trim($skill) . '</span>';
                                }
                            ?>
                        </div>
                        
                        <div class="mt-3">
                            <a href="view_applicant_detail.php?id=<?php echo $app['id']; ?>" class="btn btn-primary btn-action">
                                <i class="fas fa-eye mr-2"></i>View Details
                            </a>
                            <?php if ($app['profile']): ?>
                                <a href="../files/<?php echo $app['profile']; ?>" target="_blank" class="btn btn-success btn-action">
                                    <i class="fas fa-file-pdf mr-2"></i>View CV
                                </a>
                            <?php else: ?>
                                <button class="btn btn-secondary btn-action" disabled>
                                    <i class="fas fa-file-pdf mr-2"></i>No CV Uploaded
                                </button>
                            <?php endif; ?>
                            <a href="mailto:<?php echo $app['email']; ?>" class="btn btn-info btn-action">
                                <i class="fas fa-envelope mr-2"></i>Contact
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="alert alert-info text-center">
                    <i class="fas fa-info-circle mr-2"></i>No applicants found matching your criteria.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <footer class="text-center py-4 mt-5">
        <p class="text-muted">&copy; 2026 NovaHire. All rights reserved.</p>
    </footer>
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
</body>
</html>
