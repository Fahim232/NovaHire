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
    
    if (isset($_POST['post_job'])) {
        $job_title = mysqli_real_escape_string($con, $_POST['job_title']);
        $job_category = mysqli_real_escape_string($con, $_POST['job_category']);
        $job_description = mysqli_real_escape_string($con, $_POST['job_description']);
        $requirements = mysqli_real_escape_string($con, $_POST['requirements']);
        $responsibilities = mysqli_real_escape_string($con, $_POST['responsibilities']);
        $location = mysqli_real_escape_string($con, $_POST['location']);
        $employment_type = mysqli_real_escape_string($con, $_POST['employment_type']);
        $salary_range = mysqli_real_escape_string($con, $_POST['salary_range']);
        $experience_required = mysqli_real_escape_string($con, $_POST['experience_required']);
        $skills_required = mysqli_real_escape_string($con, $_POST['skills_required']);
        $deadline = mysqli_real_escape_string($con, $_POST['deadline']);
        $vacancy_count = intval($_POST['vacancy_count']);
        $status = mysqli_real_escape_string($con, $_POST['status']);
        
        $insert_query = "INSERT INTO company_jobs (company_id, job_title, job_category, job_description, requirements, responsibilities, location, employment_type, salary_range, experience_required, skills_required, deadline, vacancy_count, status) 
                        VALUES ($company_id, '$job_title', '$job_category', '$job_description', '$requirements', '$responsibilities', '$location', '$employment_type', '$salary_range', '$experience_required', '$skills_required', '$deadline', $vacancy_count, '$status')";
        
        if (mysqli_query($con, $insert_query)) {
            $job_id = mysqli_insert_id($con);
            $success_msg = '<div class="alert alert-success">Job posted successfully! <a href="manage_quiz.php?job_id=' . $job_id . '">Add quiz questions</a></div>';
        } else {
            $error_msg = '<div class="alert alert-danger">Failed to post job. Please try again.</div>';
        }
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Post Job | Company Dashboard</title>
    <?php include '../links.php'; ?>
    <style>
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

        body {
            padding-top: 70px;
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
            transition: all 0.3s;
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
            margin-bottom: 30px;
            color: var(--text-primary);
        }

        .form-control {
            border: 1px solid var(--glass-border);
            background: rgb(238, 232, 232);
            color: var(--text-primary);
            padding: 6px 16px;
            border-radius: 10px;
            transition: all 0.3s ease;
            box-shadow: 1px 2px var(--glass-shadow);
            font-size: 1rem;
            font-weight: 500;
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

        .btn-post {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 25px;
            padding: 15px 50px;
            color: white;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s;
        }

        .btn-post:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
            color: white;
        }

        textarea {
            min-height: 120px;
        }

        .form-section {
            background: var(--badge-bg);
            border: 1px solid var(--glass-border);
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 25px;
            color: var(--text-primary);
        }

        .form-section-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #667eea;
            margin-bottom: 20px;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
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
                    <li class="nav-item active">
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
            <h2 class="section-title"><i class="fas fa-plus-circle mr-3"></i>Post a New Job</h2>
            
            <?php if (isset($error_msg)) echo $error_msg; ?>
            <?php if (isset($success_msg)) echo $success_msg; ?>
            
            <form method="POST" action="">
                <!-- Basic Information -->
                <div class="form-section">
                    <h4 class="form-section-title"><i class="fas fa-info-circle mr-2"></i>Basic Information</h4>
                    <div class="row">
                        <div class="col-md-8">
                            <div class="form-group">
                                <label for="job_title">Job Title *</label>
                                <input type="text" class="form-control" id="job_title" name="job_title" 
                                       placeholder="e.g., Senior Java Developer" required>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="job_category">Job Category *</label>
                                <select class="form-control" id="job_category" name="job_category" required>
                                    <option value="">Select Category</option>
                                    <option value="Java">Java Developer</option>
                                    <option value="Python">Python Developer</option>
                                    <option value="PHP">PHP Developer</option>
                                    <option value="Frontend">Frontend Developer</option>
                                    <option value=".NET">.NET Developer</option>
                                    <option value="Mobile">Mobile Developer</option>
                                    <option value="DevOps">DevOps Engineer</option>
                                    <option value="Data Science">Data Scientist</option>
                                    <option value="UI/UX">UI/UX Designer</option>
                                    <option value="QA">QA Engineer</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="job_description">Job Description *</label>
                                <textarea class="form-control" id="job_description" name="job_description" 
                                          placeholder="Describe the role and what the candidate will be doing..." required></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Job Details -->
                <div class="form-section">
                    <h4 class="form-section-title"><i class="fas fa-clipboard-list mr-2"></i>Job Details</h4>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="requirements">Requirements *</label>
                                <textarea class="form-control" id="requirements" name="requirements" 
                                          placeholder="List the required qualifications, education, and experience..." required></textarea>
                            </div>
                        </div>
                        
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="responsibilities">Responsibilities *</label>
                                <textarea class="form-control" id="responsibilities" name="responsibilities" 
                                          placeholder="Describe key responsibilities and duties..." required></textarea>
                            </div>
                        </div>
                        
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="skills_required">Required Skills *</label>
                                <input type="text" class="form-control" id="skills_required" name="skills_required" 
                                       placeholder="e.g., Java, Spring Boot, MySQL, REST APIs" required>
                                <small class="form-text text-muted">Separate skills with commas</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Employment Details -->
                <div class="form-section">
                    <h4 class="form-section-title"><i class="fas fa-briefcase mr-2"></i>Employment Details</h4>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="location">Location *</label>
                                <input type="text" class="form-control" id="location" name="location" 
                                       placeholder="e.g., San Francisco, CA or Remote" required>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="employment_type">Employment Type *</label>
                                <select class="form-control" id="employment_type" name="employment_type" required>
                                    <option value="">Select Type</option>
                                    <option value="Full-Time">Full-Time</option>
                                    <option value="Part-Time">Part-Time</option>
                                    <option value="Contract">Contract</option>
                                    <option value="Internship">Internship</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="experience_required">Experience Required *</label>
                                <input type="text" class="form-control" id="experience_required" name="experience_required" 
                                       placeholder="e.g., 3-5 years" required>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="salary_range">Salary Range (Optional)</label>
                                <input type="text" class="form-control" id="salary_range" name="salary_range" 
                                       placeholder="e.g., $80,000 - $120,000">
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="vacancy_count">Number of Vacancies *</label>
                                <input type="number" class="form-control" id="vacancy_count" name="vacancy_count" 
                                       min="1" value="1" required>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="deadline">Application Deadline *</label>
                                <input type="date" class="form-control" id="deadline" name="deadline" required>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="status">Status *</label>
                                <select class="form-control" id="status" name="status" required>
                                    <option value="active">Active (Publish Immediately)</option>
                                    <option value="draft">Draft (Save for Later)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="text-center mt-4">
                    <button type="submit" name="post_job" class="btn btn-post">
                        <i class="fas fa-paper-plane mr-2"></i>Post Job
                    </button>
                    <a href="my_jobs.php" class="btn btn-secondary btn-post ml-3">
                        <i class="fas fa-times mr-2"></i>Cancel
                    </a>
                </div>
            </form>
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
