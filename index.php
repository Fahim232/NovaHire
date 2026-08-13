<?php 
    include 'admin/dbcon.php';
    include 'includes/functions.php';
    session_start();
    
    $is_logged_in = isset($_SESSION['id']);
    $user_id = $is_logged_in ? $_SESSION['id'] : 0;
    $is_seeker_logged_in = $is_logged_in && !isset($_SESSION['company_id']);
    $is_company_logged_in = isset($_SESSION['company_id']);
    
    $total_jobs_q = mysqli_query($con, "SELECT COUNT(*) as cnt FROM company_jobs WHERE status='active'");
    $total_jobs = mysqli_fetch_assoc($total_jobs_q)['cnt'];
    
    $total_companies_q = mysqli_query($con, "SELECT COUNT(*) as cnt FROM companies WHERE status='active'");
    $total_companies = mysqli_fetch_assoc($total_companies_q)['cnt'];
    
    $total_users_q = mysqli_query($con, "SELECT COUNT(*) as cnt FROM user_info");
    $total_users = mysqli_fetch_assoc($total_users_q)['cnt'];
    
    $total_applications_q = mysqli_query($con, "SELECT COUNT(*) as cnt FROM job_applications");
    $total_applications = mysqli_fetch_assoc($total_applications_q)['cnt'];
    
    $featured_companies_q = mysqli_query($con, "SELECT c.*, 
        (SELECT COUNT(*) FROM company_jobs WHERE company_id = c.id AND status='active') as job_count 
        FROM companies c WHERE c.status='active' ORDER BY c.registration_date DESC LIMIT 6");
    
    $latest_jobs_q = mysqli_query($con, "SELECT cj.*, c.company_name, c.logo, c.industry
        FROM company_jobs cj 
        JOIN companies c ON cj.company_id = c.id 
        WHERE cj.status = 'active' AND cj.deadline >= CURDATE()
        ORDER BY cj.posted_date DESC LIMIT 9");
    
    $categories_q = mysqli_query($con, "SELECT job_category, COUNT(*) as cnt 
        FROM company_jobs WHERE status='active' GROUP BY job_category ORDER BY cnt DESC");
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subscribe_email'])) {
        $email = mysqli_real_escape_string($con, $_POST['subscribe_email']);
        $check = mysqli_query($con, "SELECT id FROM newsletter_subscribers WHERE email='$email'");
        if (mysqli_num_rows($check) === 0) {
            mysqli_query($con, "INSERT INTO newsletter_subscribers (email) VALUES ('$email')");
            $sub_success = true;
        } else {
            $sub_exists = true;
        }
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>NovaHire - Find Your Dream Job | Top Companies Hiring Now</title>
    <?php include 'includes/links.php' ?>
    <script>
        (function() {
            var saved = localStorage.getItem('theme') || localStorage.getItem('company-theme');
            if (saved) {
                document.documentElement.setAttribute('data-theme', saved);
            }
        })();
    </script>
    <style>
        * { box-sizing: border-box; }

        /* Hero */
        .hero-wrapper {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 50%, #a855f7 100%);
            padding: 0;
            position: relative;
            overflow: hidden;
        }
        .hero-wrapper::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            border-radius: 50%;
        }
        .hero-wrapper::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -10%;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(236,72,153,0.2) 0%, transparent 70%);
            border-radius: 50%;
        }
        .hero-content {
            padding: 100px 0 80px;
            position: relative;
            z-index: 2;
        }
        .hero-title {
            font-size: 3rem;
            font-weight: 800;
            color: white;
            line-height: 1.15;
            letter-spacing: -1.5px;
            margin-bottom: 18px;
        }
        .hero-subtitle {
            font-size: 1.1rem;
            color: rgba(255,255,255,0.85);
            font-weight: 400;
            margin-bottom: 32px;
            max-width: 480px;
        }
        .hero-search {
            background: white;
            border-radius: 14px;
            padding: 6px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.2);
            display: flex;
            gap: 0;
            max-width: 680px;
        }
        .hero-search .search-input {
            flex: 1;
            border: none;
            padding: 14px 18px;
            font-size: 0.95rem;
            outline: none;
            background: transparent;
            min-width: 0;
            font-family: var(--font);
        }
        .hero-search .search-select {
            border: none;
            border-left: 1px solid #e5e7eb;
            padding: 14px 14px;
            font-size: 0.85rem;
            color: #6b7280;
            outline: none;
            background: transparent;
            cursor: pointer;
            min-width: 150px;
            font-family: var(--font);
        }
        .hero-search .search-btn {
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            color: white;
            border: none;
            padding: 14px 30px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 0.9rem;
            cursor: pointer;
            transition: var(--transition);
            white-space: nowrap;
            font-family: var(--font);
        }
        .hero-search .search-btn:hover {
            transform: scale(1.02);
            box-shadow: 0 4px 16px rgba(79, 70, 229, 0.4);
        }
        .hero-stats {
            display: flex;
            gap: 36px;
            margin-top: 44px;
        }
        .hero-stat { text-align: center; }
        .hero-stat h3 {
            color: white;
            font-size: 1.8rem;
            font-weight: 800;
            margin-bottom: 4px;
        }
        .hero-stat p {
            color: rgba(255,255,255,0.7);
            font-size: 0.85rem;
            font-weight: 500;
        }
        .hero-image-area { position: relative; z-index: 2; }
        .hero-floating-card {
            background: white;
            border-radius: 14px;
            padding: 14px 18px;
            box-shadow: 0 12px 35px rgba(0,0,0,0.15);
            display: flex;
            align-items: center;
            gap: 12px;
            animation: float-card 6s ease-in-out infinite;
        }
        .hero-floating-card:nth-child(2) { animation-delay: -2s; }
        .hero-floating-card:nth-child(3) { animation-delay: -4s; }
        @keyframes float-card {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-12px); }
        }
        .fc-icon {
            width: 44px;
            height: 44px;
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        /* Sections */
        .section-header {
            text-align: center;
            margin-bottom: 44px;
        }
        .section-header h2 {
            font-size: 2rem;
            font-weight: 800;
            color: var(--text);
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }
        .section-header p {
            color: var(--text-muted);
            font-size: 1rem;
            max-width: 560px;
            margin: 0 auto;
        }

        /* Category Cards */
        .category-card {
            background: var(--bg-card);
            border-radius: var(--radius-md);
            padding: 28px 18px;
            text-align: center;
            border: 1px solid var(--border-light);
            transition: var(--transition);
            cursor: pointer;
            text-decoration: none;
            display: block;
        }
        .category-card:hover {
            transform: translateY(-6px);
            box-shadow: var(--shadow-lg);
            border-color: transparent;
            text-decoration: none;
        }
        .category-icon {
            width: 60px;
            height: 60px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin: 0 auto 14px;
            transition: var(--transition);
        }
        .category-card:hover .category-icon {
            transform: scale(1.1) rotate(-3deg);
        }
        .category-card h5 {
            font-weight: 700;
            color: var(--text);
            margin-bottom: 4px;
            font-size: 0.95rem;
        }
        .category-card .count {
            color: var(--text-muted);
            font-size: 0.82rem;
            font-weight: 500;
        }

        /* Job Listings */
        .job-listing {
            background: var(--bg-card);
            border-radius: var(--radius-md);
            padding: 22px;
            border: 1px solid var(--border-light);
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 18px;
            text-decoration: none;
            margin-bottom: 12px;
        }
        .job-listing:hover {
            transform: translateX(4px);
            box-shadow: var(--shadow-md);
            border-color: var(--primary);
            text-decoration: none;
        }
        .job-logo {
            width: 54px;
            height: 54px;
            border-radius: var(--radius-sm);
            object-fit: contain;
            border: 1px solid var(--border-light);
            padding: 4px;
            background: var(--bg);
            flex-shrink: 0;
        }
        .job-logo-placeholder {
            width: 54px;
            height: 54px;
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            flex-shrink: 0;
        }
        .job-info { flex: 1; min-width: 0; }
        .job-info h5 {
            font-weight: 700;
            color: var(--text);
            margin-bottom: 3px;
            font-size: 1rem;
        }
        .job-info .company-name {
            color: var(--primary);
            font-weight: 600;
            font-size: 0.85rem;
        }
        .job-meta-row {
            display: flex;
            gap: 14px;
            flex-wrap: wrap;
            margin-top: 6px;
        }
        .job-meta-item {
            display: flex;
            align-items: center;
            gap: 4px;
            color: var(--text-muted);
            font-size: 0.82rem;
        }
        .job-meta-item i { color: var(--text-light); }
        .job-tags {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
            margin-top: 6px;
        }
        .job-tag {
            background: var(--bg-hover);
            color: var(--text-muted);
            padding: 3px 9px;
            border-radius: 6px;
            font-size: 0.72rem;
            font-weight: 600;
        }
        .job-salary {
            font-weight: 700;
            color: var(--success);
            font-size: 0.9rem;
            white-space: nowrap;
        }
        .job-apply-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 9px 20px;
            border-radius: var(--radius-sm);
            font-weight: 600;
            font-size: 0.82rem;
            cursor: pointer;
            transition: var(--transition);
            white-space: nowrap;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .job-apply-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
            color: white;
            text-decoration: none;
        }

        /* Company Cards */
        .company-card {
            background: var(--bg-card);
            border-radius: var(--radius-md);
            padding: 28px;
            text-align: center;
            border: 1px solid var(--border-light);
            transition: var(--transition);
            text-decoration: none;
            display: block;
        }
        .company-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
            text-decoration: none;
        }
        .company-card-logo {
            width: 72px;
            height: 72px;
            border-radius: var(--radius-md);
            object-fit: contain;
            margin: 0 auto 12px;
            border: 2px solid var(--border-light);
            padding: 6px;
            background: var(--bg);
        }
        .company-card h5 {
            font-weight: 700;
            color: var(--text);
            margin-bottom: 4px;
            font-size: 1rem;
        }
        .company-card .industry {
            color: var(--text-muted);
            font-size: 0.82rem;
            margin-bottom: 10px;
        }
        .company-card .job-count-badge {
            background: rgba(79,70,229,0.08);
            color: var(--primary);
            padding: 5px 14px;
            border-radius: 20px;
            font-size: 0.78rem;
            font-weight: 600;
            display: inline-block;
        }

        /* Stats */
        .stats-section {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            padding: 60px 0;
        }
        .stat-box {
            text-align: center;
            color: white;
        }
        .stat-box h2 {
            font-size: 2.5rem;
            font-weight: 800;
            color: white;
            margin-bottom: 4px;
        }
        .stat-box p {
            color: rgba(255,255,255,0.7);
            font-size: 0.9rem;
            font-weight: 500;
        }
        .stat-box i {
            font-size: 2.2rem;
            color: rgba(255,255,255,0.25);
            margin-bottom: 12px;
        }

        /* Newsletter */
        .newsletter-section {
            background: var(--bg-card);
            border-radius: var(--radius-xl);
            padding: 44px;
            box-shadow: var(--shadow-sm);
            text-align: center;
            margin: -44px auto 0;
            position: relative;
            z-index: 10;
            max-width: 760px;
            border: 1px solid var(--border-light);
        }
        .newsletter-form {
            display: flex;
            gap: 8px;
            max-width: 480px;
            margin: 22px auto 0;
        }
        .newsletter-form input {
            flex: 1;
            border: 2px solid var(--border);
            padding: 12px 18px;
            border-radius: var(--radius-sm);
            font-size: 0.9rem;
            outline: none;
            transition: border-color 0.3s;
            font-family: var(--font);
        }
        .newsletter-form input:focus {
            border-color: var(--primary);
        }
        .newsletter-form button {
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            color: white;
            border: none;
            padding: 12px 28px;
            border-radius: var(--radius-sm);
            font-weight: 700;
            font-size: 0.88rem;
            cursor: pointer;
            transition: var(--transition);
            white-space: nowrap;
            font-family: var(--font);
        }
        .newsletter-form button:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 16px rgba(79, 70, 229, 0.3);
        }

        /* Footer */
        .site-footer {
            background: var(--dark);
            color: #94a3b8;
            padding: 60px 0 28px;
            margin-top: 70px;
        }
        .footer-brand {
            font-size: 1.4rem;
            font-weight: 800;
            color: white;
            margin-bottom: 12px;
        }
        .footer-brand span { color: var(--primary); }
        .footer-desc {
            color: #94a3b8;
            font-size: 0.85rem;
            line-height: 1.7;
            max-width: 280px;
        }
        .footer-title {
            color: white;
            font-weight: 700;
            font-size: 0.95rem;
            margin-bottom: 18px;
        }
        .footer-links {
            list-style: none;
            padding: 0;
        }
        .footer-links li { margin-bottom: 8px; }
        .footer-links a {
            color: #94a3b8;
            font-size: 0.85rem;
            transition: var(--transition);
            text-decoration: none;
        }
        .footer-links a:hover {
            color: var(--primary);
            padding-left: 4px;
        }
        .footer-social {
            display: flex;
            gap: 10px;
            margin-top: 18px;
        }
        .footer-social a {
            width: 38px;
            height: 38px;
            border-radius: var(--radius-sm);
            background: rgba(255,255,255,0.06);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #94a3b8;
            transition: var(--transition);
            text-decoration: none;
        }
        .footer-social a:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-2px);
        }
        .footer-bottom {
            border-top: 1px solid rgba(255,255,255,0.06);
            margin-top: 36px;
            padding-top: 22px;
            text-align: center;
        }
        .footer-bottom p {
            color: #64748b;
            font-size: 0.82rem;
            margin: 0;
        }

        /* Responsive */
        @media (max-width: 991px) {
            .hero-content { padding: 80px 0 60px; }
            .hero-title { font-size: 2.2rem; }
            .hero-stats { gap: 24px; }
            .hero-stat h3 { font-size: 1.5rem; }
        }
        @media (max-width: 768px) {
            .hero-title { font-size: 2rem; letter-spacing: -1px; }
            .hero-search { flex-direction: column; }
            .hero-search .search-select { border-left: none; border-top: 1px solid #e5e7eb; }
            .hero-stats { gap: 16px; flex-wrap: wrap; }
            .hero-stat h3 { font-size: 1.4rem; }
            .newsletter-section { padding: 28px 20px; margin: -28px 16px 0; }
            .newsletter-form { flex-direction: column; }
            .job-listing { flex-direction: column; align-items: flex-start; gap: 12px; }
            .job-apply-btn { width: 100%; justify-content: center; }
        }
        @media (max-width: 575px) {
            .hero-title { font-size: 1.7rem; }
            .hero-subtitle { font-size: 0.95rem; }
            .hero-content { padding: 70px 0 50px; }
        }
    </style>
</head>
<body>

<!-- Public Navigation -->
<nav class="navbar navbar-expand-lg glass-nav fixed-top">
    <div class="container-fluid px-4 px-lg-5 custom-nav-container">
        <a class="navbar-brand d-flex align-items-center" href="index.php">
            <div class="brand-icon mr-2"><i class="fas fa-layer-group"></i></div>
            <span class="brand-text">Nova<span class="brand-highlight">Hire</span></span>
        </a>

        <button class="navbar-toggler custom-toggler" type="button" data-toggle="collapse" data-target="#publicNavbar">
            <span class="fas fa-bars fa-lg" style="color: var(--text);"></span>
        </button>

        <div class="collapse navbar-collapse justify-content-between" id="publicNavbar">
            <ul class="navbar-nav mx-auto center-menu">
                <li class="nav-item">
                    <a class="nav-link active" href="index.php"><i class="fas fa-home mr-1"></i>Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="seeker/browse_jobs.php"><i class="fas fa-briefcase mr-1"></i>Jobs</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="seeker/available_companies.php"><i class="fas fa-building mr-1"></i>Companies</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="seeker/grooming.php"><i class="fas fa-user-graduate mr-1"></i>Skill Grooming</a>
                </li>
            </ul>

            <ul class="navbar-nav align-items-center right-menu">
                <li class="nav-item mr-2">
                    <a class="nav-link nav-icon-btn d-flex align-items-center justify-content-center" href="#" id="themeToggle" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="Switch Theme">
                        <i class="fas fa-swatchbook"></i>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right theme-dropdown" aria-labelledby="themeToggle">
                        <h6 class="dropdown-header text-uppercase font-weight-bold pl-3 mb-2" style="font-size: 0.65rem; color: var(--text-light); letter-spacing: 0.5px;">Theme</h6>
                        <a class="dropdown-item" href="#" onclick="setTheme('default'); return false;"><span class="dot mr-2" style="background: #4f46e5;"></span>Default</a>
                        <a class="dropdown-item" href="#" onclick="setTheme('ocean'); return false;"><span class="dot mr-2" style="background: #0891b2;"></span>Ocean</a>
                        <a class="dropdown-item" href="#" onclick="setTheme('sunset'); return false;"><span class="dot mr-2" style="background: #ea580c;"></span>Sunset</a>
                        <a class="dropdown-item" href="#" onclick="setTheme('dark'); return false;"><span class="dot mr-2" style="background: #1e293b;"></span>Dark</a>
                    </div>
                </li>

                <?php if ($is_seeker_logged_in): ?>
                    <li class="nav-item mr-2">
                        <a class="btn btn-primary btn-sm rounded-pill px-3 font-weight-bold" href="seeker/seeker_dashboard.php" style="white-space: nowrap;">
                            <i class="fas fa-tachometer-alt mr-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link user-pill dropdown-toggle" href="#" id="userDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <div class="user-avatar-sm"><i class="fas fa-user"></i></div>
                            <span class="user-name-text"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right user-menu-dropdown" aria-labelledby="userDropdown">
                            <a class="dropdown-item" href="seeker/profile.php"><i class="fas fa-user-circle mr-2" style="color: var(--text-light);"></i> My Profile</a>
                            <a class="dropdown-item" href="seeker/my_application.php"><i class="fas fa-file-alt mr-2" style="color: var(--text-light);"></i> Applications</a>
                            <a class="dropdown-item" href="seeker/saved_jobs.php"><i class="fas fa-bookmark mr-2" style="color: var(--text-light);"></i> Saved Jobs</a>
                            <div class="dropdown-divider" style="border-color: var(--border-light);"></div>
                            <a class="dropdown-item" href="auth/logout.php" style="color: var(--danger);"><i class="fas fa-sign-out-alt mr-2"></i> Logout</a>
                        </div>
                    </li>
                <?php elseif ($is_company_logged_in): ?>
                    <li class="nav-item mr-2">
                        <a class="btn btn-primary btn-sm rounded-pill px-3 font-weight-bold" href="company/index.php" style="white-space: nowrap;">
                            <i class="fas fa-tachometer-alt mr-1"></i>Company Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-outline-secondary btn-sm rounded-pill px-3 font-weight-bold" href="auth/logout.php" style="white-space: nowrap;">
                            <i class="fas fa-sign-out-alt mr-1"></i>Logout
                        </a>
                    </li>
                <?php else: ?>
                    <li class="nav-item mr-2">
                        <a class="btn btn-outline-primary btn-sm rounded-pill px-3 font-weight-bold" href="auth/login.php">
                            <i class="fas fa-sign-in-alt mr-1"></i>Login
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-primary btn-sm rounded-pill px-3 font-weight-bold" href="auth/registration.php">
                            <i class="fas fa-user-plus mr-1"></i>Register
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<!-- Hero -->
<div class="hero-wrapper">
    <div class="container hero-content">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <h1 class="hero-title">Find Your <span style="color: #fbbf24;">Dream Job</span> Today</h1>
                <p class="hero-subtitle">Discover thousands of job opportunities from top companies. Build your profile and get hired faster.</p>
                
                <form class="hero-search" action="seeker/browse_jobs.php" method="GET">
                    <input type="text" name="location" class="search-input" placeholder="Job title, keyword, or company...">
                    <select name="category" class="search-select">
                        <option value="all">All Categories</option>
                        <option value="PHP">PHP Developer</option>
                        <option value="Java">Java Developer</option>
                        <option value="Python">Python Developer</option>
                        <option value="Frontend">Frontend Dev</option>
                        <option value="JavaScript">JavaScript Dev</option>
                        <option value="UI/UX">UI/UX Design</option>
                        <option value="DataScience">Data Science</option>
                        <option value="Marketing">Marketing</option>
                        <option value="Finance">Finance</option>
                        <option value="Healthcare">Healthcare</option>
                        <option value="Education">Education</option>
                        <option value="Engineering">Engineering</option>
                        <option value="Sales">Sales</option>
                        <option value="HR">Human Resources</option>
                        <option value="Legal">Legal</option>
                        <option value="Media">Media & Communications</option>
                        <option value="Logistics">Logistics</option>
                        <option value="Consulting">Consulting</option>
                        <option value="Retail">Retail</option>
                    </select>
                    <button type="submit" class="search-btn"><i class="fas fa-search mr-2"></i>Search</button>
                </form>
                
                <div class="hero-stats">
                    <div class="hero-stat">
                        <h3><?php echo number_format($total_jobs); ?>+</h3>
                        <p>Live Jobs</p>
                    </div>
                    <div class="hero-stat">
                        <h3><?php echo number_format($total_companies); ?>+</h3>
                        <p>Companies</p>
                    </div>
                    <div class="hero-stat">
                        <h3><?php echo number_format($total_users); ?>+</h3>
                        <p>Job Seekers</p>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6 d-none d-lg-block">
                <div class="hero-image-area text-center">
                    <div class="hero-floating-card" style="max-width: 300px; margin: 0 auto 16px;">
                        <div class="fc-icon" style="background: #dbeafe; color: #2563eb;"><i class="fas fa-check-circle"></i></div>
                        <div>
                            <h6 style="margin:0; font-weight:700; font-size:0.88rem; color:var(--text);">Skill Verified</h6>
                            <small style="color:var(--text-muted); font-size:0.78rem;">PHP Assessment Passed</small>
                        </div>
                    </div>
                    <div class="hero-floating-card" style="max-width: 300px; margin: 0 auto 16px; animation-delay: -2s;">
                        <div class="fc-icon" style="background: #dcfce7; color: #16a34a;"><i class="fas fa-paper-plane"></i></div>
                        <div>
                            <h6 style="margin:0; font-weight:700; font-size:0.88rem; color:var(--text);">Application Sent</h6>
                            <small style="color:var(--text-muted); font-size:0.78rem;">Senior Developer at TechCo</small>
                        </div>
                    </div>
                    <div class="hero-floating-card" style="max-width: 300px; margin: 0 auto; animation-delay: -4s;">
                        <div class="fc-icon" style="background: #fef3c7; color: #d97706;"><i class="fas fa-bell"></i></div>
                        <div>
                            <h6 style="margin:0; font-weight:700; font-size:0.88rem; color:var(--text);">Interview Scheduled</h6>
                            <small style="color:var(--text-muted); font-size:0.78rem;">Tomorrow at 10:00 AM</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container" style="margin-top: 70px;">

    <!-- Categories -->
    <div class="section-header">
        <h2>Browse by Category</h2>
        <p>Explore job opportunities across various technology fields</p>
    </div>
    
    <?php
    $cat_styles = [
        'PHP' => ['icon' => 'fab fa-php', 'bg' => '#eef2ff', 'color' => '#4f46e5'],
        'Java' => ['icon' => 'fab fa-java', 'bg' => '#fef2f2', 'color' => '#dc2626'],
        'Python' => ['icon' => 'fab fa-python', 'bg' => '#eff6ff', 'color' => '#2563eb'],
        'Frontend' => ['icon' => 'fab fa-html5', 'bg' => '#fff7ed', 'color' => '#ea580c'],
        'JavaScript' => ['icon' => 'fab fa-js-square', 'bg' => '#fefce8', 'color' => '#ca8a04'],
        'UI/UX' => ['icon' => 'fas fa-palette', 'bg' => '#fdf2f8', 'color' => '#db2777'],
        'DataScience' => ['icon' => 'fas fa-chart-line', 'bg' => '#f0fdf4', 'color' => '#16a34a'],
        'Marketing' => ['icon' => 'fas fa-bullhorn', 'bg' => '#fff1f2', 'color' => '#e11d48'],
        'DB' => ['icon' => 'fas fa-database', 'bg' => '#f0f9ff', 'color' => '#0284c7'],
        'Finance' => ['icon' => 'fas fa-dollar-sign', 'bg' => '#ecfdf5', 'color' => '#059669'],
        'Healthcare' => ['icon' => 'fas fa-heartbeat', 'bg' => '#fef2f2', 'color' => '#dc2626'],
        'Education' => ['icon' => 'fas fa-graduation-cap', 'bg' => '#eff6ff', 'color' => '#2563eb'],
        'Engineering' => ['icon' => 'fas fa-cogs', 'bg' => '#f5f3ff', 'color' => '#7c3aed'],
        'Sales' => ['icon' => 'fas fa-handshake', 'bg' => '#fff7ed', 'color' => '#ea580c'],
        'HR' => ['icon' => 'fas fa-users', 'bg' => '#fdf2f8', 'color' => '#db2777'],
        'Legal' => ['icon' => 'fas fa-gavel', 'bg' => '#fefce8', 'color' => '#ca8a04'],
        'Media' => ['icon' => 'fas fa-tv', 'bg' => '#f0fdf4', 'color' => '#16a34a'],
        'Logistics' => ['icon' => 'fas fa-truck', 'bg' => '#fff1f2', 'color' => '#e11d48'],
        'Consulting' => ['icon' => 'fas fa-lightbulb', 'bg' => '#ecfdf5', 'color' => '#059669'],
        'Retail' => ['icon' => 'fas fa-shopping-cart', 'bg' => '#f0f9ff', 'color' => '#0284c7'],
    ];
    ?>
    <div class="row mb-5">
        <?php while ($cat = mysqli_fetch_assoc($categories_q)): ?>
            <?php 
            $cat_name = $cat['job_category'];
            $style = isset($cat_styles[$cat_name]) ? $cat_styles[$cat_name] : ['icon' => 'fas fa-code', 'bg' => '#f1f5f9', 'color' => '#64748b'];
            ?>
            <div class="col-lg-3 col-md-4 col-6 mb-4">
                <a href="seeker/browse_jobs.php?category=<?php echo urlencode($cat_name); ?>" class="category-card">
                    <div class="category-icon" style="background: <?php echo $style['bg']; ?>; color: <?php echo $style['color']; ?>;">
                        <i class="<?php echo $style['icon']; ?>"></i>
                    </div>
                    <h5><?php echo htmlspecialchars($cat_name); ?></h5>
                    <span class="count"><?php echo $cat['cnt']; ?> <?php echo $cat['cnt'] == 1 ? 'Job' : 'Jobs'; ?></span>
                </a>
            </div>
        <?php endwhile; ?>
    </div>

    <!-- Latest Jobs -->
    <div class="section-header">
        <h2>Latest Job Openings</h2>
        <p>Don't miss out on the newest opportunities from top companies</p>
    </div>
    
    <div class="mb-5">
        <?php while ($job = mysqli_fetch_assoc($latest_jobs_q)): ?>
            <a href="seeker/job_details.php?id=<?php echo $job['id']; ?>" class="job-listing">
                <?php if (!empty($job['logo']) && file_exists('uploads/company_logos/' . $job['logo'])): ?>
                    <img src="uploads/company_logos/<?php echo htmlspecialchars($job['logo']); ?>" class="job-logo" alt="<?php echo htmlspecialchars($job['company_name']); ?>">
                <?php else: ?>
                    <div class="job-logo-placeholder" style="background: linear-gradient(135deg, #4f46e5, #7c3aed); color: white; border-radius: var(--radius-sm);">
                        <i class="fas fa-building"></i>
                    </div>
                <?php endif; ?>
                
                <div class="job-info">
                    <h5><?php echo htmlspecialchars($job['job_title']); ?></h5>
                    <div class="company-name"><i class="fas fa-building mr-1"></i><?php echo htmlspecialchars($job['company_name']); ?></div>
                    <div class="job-meta-row">
                        <span class="job-meta-item"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($job['location']); ?></span>
                        <span class="job-meta-item"><i class="fas fa-briefcase"></i> <?php echo $job['employment_type']; ?></span>
                        <span class="job-meta-item"><i class="fas fa-clock"></i> <?php echo $job['experience_required']; ?></span>
                        <span class="job-meta-item"><i class="fas fa-calendar"></i> <?php echo date('M d', strtotime($job['posted_date'])); ?></span>
                    </div>
                    <div class="job-tags">
                        <?php 
                        $skills = array_slice(explode(',', $job['skills_required']), 0, 4);
                        foreach ($skills as $skill): ?>
                            <span class="job-tag"><?php echo trim($skill); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <?php if ($job['salary_range']): ?>
                    <div class="job-salary"><?php echo htmlspecialchars($job['salary_range']); ?></div>
                <?php endif; ?>
                
                <span class="job-apply-btn"><i class="fas fa-arrow-right"></i> View</span>
            </a>
        <?php endwhile; ?>
        
        <div class="text-center mt-4">
            <a href="seeker/browse_jobs.php" class="btn btn-outline-primary rounded-pill px-5 py-3 font-weight-bold">
                View All Jobs <i class="fas fa-arrow-right ml-2"></i>
            </a>
        </div>
    </div>

    <!-- Featured Companies -->
    <div class="section-header">
        <h2>Featured Companies</h2>
        <p>Top employers actively hiring on our platform</p>
    </div>
    
    <div class="row mb-5">
        <?php while ($company = mysqli_fetch_assoc($featured_companies_q)): ?>
            <div class="col-lg-4 col-md-6 mb-4">
                <a href="seeker/browse_jobs.php?company=<?php echo $company['id']; ?>" class="company-card">
                    <?php if (!empty($company['logo']) && file_exists('uploads/company_logos/' . $company['logo'])): ?>
                        <img src="uploads/company_logos/<?php echo htmlspecialchars($company['logo']); ?>" class="company-card-logo" alt="<?php echo htmlspecialchars($company['company_name']); ?>">
                    <?php else: ?>
                        <div class="company-card-logo" style="background: linear-gradient(135deg, #4f46e5, #7c3aed); color: white; display: flex; align-items: center; justify-content: center; font-size: 1.8rem;">
                            <i class="fas fa-building"></i>
                        </div>
                    <?php endif; ?>
                    <h5><?php echo htmlspecialchars($company['company_name']); ?></h5>
                    <div class="industry"><?php echo htmlspecialchars($company['industry']); ?></div>
                    <span class="job-count-badge"><i class="fas fa-briefcase mr-1"></i><?php echo $company['job_count']; ?> Open Positions</span>
                </a>
            </div>
        <?php endwhile; ?>
    </div>
</div>

<!-- Stats -->
<div class="stats-section">
    <div class="container">
        <div class="row">
            <div class="col-md-3 col-6 mb-3">
                <div class="stat-box">
                    <i class="fas fa-briefcase d-block"></i>
                    <h2><?php echo number_format($total_jobs); ?>+</h2>
                    <p>Job Opportunities</p>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-3">
                <div class="stat-box">
                    <i class="fas fa-building d-block"></i>
                    <h2><?php echo number_format($total_companies); ?>+</h2>
                    <p>Registered Companies</p>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-3">
                <div class="stat-box">
                    <i class="fas fa-users d-block"></i>
                    <h2><?php echo number_format($total_users); ?>+</h2>
                    <p>Active Job Seekers</p>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-3">
                <div class="stat-box">
                    <i class="fas fa-file-alt d-block"></i>
                    <h2><?php echo number_format($total_applications); ?>+</h2>
                    <p>Applications Sent</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Newsletter -->
<div class="container">
    <div class="newsletter-section">
        <h3 style="font-weight: 800; color: var(--text); margin-bottom: 8px; letter-spacing: -0.3px;">Stay Updated on New Opportunities</h3>
        <p style="color: var(--text-muted); margin: 0; font-size: 0.92rem;">Subscribe to our newsletter and never miss a job opening.</p>
        
        <?php if (isset($sub_success)): ?>
            <div class="alert alert-success mt-3" style="max-width: 480px; margin-left: auto; margin-right: auto;">
                <i class="fas fa-check-circle mr-2"></i>You've been subscribed successfully!
            </div>
        <?php elseif (isset($sub_exists)): ?>
            <div class="alert alert-info mt-3" style="max-width: 480px; margin-left: auto; margin-right: auto;">
                <i class="fas fa-info-circle mr-2"></i>This email is already subscribed.
            </div>
        <?php endif; ?>
        
        <form method="POST" class="newsletter-form">
            <input type="email" name="subscribe_email" placeholder="Enter your email address" required>
            <button type="submit"><i class="fas fa-paper-plane mr-2"></i>Subscribe</button>
        </form>
    </div>
</div>

<!-- Footer -->
<footer class="site-footer">
    <div class="container">
        <div class="row">
            <div class="col-lg-4 mb-4">
                <div class="footer-brand">Nova<span>Hire</span></div>
                <p class="footer-desc">Your gateway to career success. Connect with top employers and find your dream job.</p>
                <div class="footer-social">
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-linkedin-in"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 mb-4">
                <div class="footer-title">For Job Seekers</div>
                <ul class="footer-links">
                    <li><a href="seeker/browse_jobs.php">Browse Jobs</a></li>
                    <li><a href="seeker/available_companies.php">Companies</a></li>
                    <li><a href="seeker/profile.php">My Profile</a></li>
                    <li><a href="seeker/my_application.php">My Applications</a></li>
                </ul>
            </div>
            <div class="col-lg-2 col-md-4 mb-4">
                <div class="footer-title">For Employers</div>
                <ul class="footer-links">
                    <li><a href="company_registration.php">Register Company</a></li>
                    <li><a href="auth/login.php">Employer Login</a></li>
                    <li><a href="auth/login.php">Post a Job</a></li>
                </ul>
            </div>
            <div class="col-lg-2 col-md-4 mb-4">
                <div class="footer-title">Resources</div>
                <ul class="footer-links">
                    <li><a href="seeker/browse_jobs.php">Browse Jobs</a></li>
                    <li><a href="seeker/view_cv.php">Build Your CV</a></li>
                </ul>
            </div>
            <div class="col-lg-2 col-md-4 mb-4">
                <div class="footer-title">Support</div>
                <ul class="footer-links">
                    <li><a href="#">Help Center</a></li>
                    <li><a href="#">Privacy Policy</a></li>
                    <li><a href="#">Terms of Use</a></li>
                    <li><a href="#">Contact Us</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> NovaHire. All rights reserved.</p>
        </div>
    </div>
</footer>

<script>
    function setTheme(themeName) {
        document.body.setAttribute('data-theme', themeName);
        localStorage.setItem('theme', themeName);
    }

    (function() {
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme) {
            document.body.setAttribute('data-theme', savedTheme);
        }
    })();
</script>

</body>
</html>
