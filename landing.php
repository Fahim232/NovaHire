<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NovaHire - Welcome</title>
    <?php include 'includes/links.php'; ?>
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: white;
        }

        .hero-section {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 50px 20px;
        }

        .hero-content h1 {
            font-size: 4rem;
            font-weight: 800;
            margin-bottom: 30px;
            text-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }

        .hero-content p {
            font-size: 1.5rem;
            margin-bottom: 50px;
            opacity: 0.95;
        }

        .role-cards {
            display: flex;
            justify-content: center;
            gap: 40px;
            flex-wrap: wrap;
            margin-top: 60px;
        }

        .role-card {
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(10px);
            border-radius: 25px;
            padding: 50px 40px;
            width: 350px;
            transition: all 0.3s;
            border: 2px solid rgba(255,255,255,0.2);
        }

        .role-card:hover {
            transform: translateY(-10px);
            background: rgba(255,255,255,0.25);
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
        }

        .role-card i {
            font-size: 5rem;
            margin-bottom: 25px;
            display: block;
        }

        .role-card h3 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 20px;
        }

        .role-card p {
            font-size: 1.1rem;
            margin-bottom: 30px;
            opacity: 0.9;
        }

        .btn-role {
            background: white;
            color: #667eea;
            border: none;
            padding: 15px 40px;
            border-radius: 25px;
            font-weight: 700;
            font-size: 1.1rem;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            margin: 5px;
        }

        .btn-role:hover {
            transform: scale(1.05);
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            color: #667eea;
            text-decoration: none;
        }

        .btn-role.secondary {
            background: transparent;
            color: white;
            border: 2px solid white;
        }

        .btn-role.secondary:hover {
            background: white;
            color: #667eea;
        }

        .features-section {
            background: white;
            color: #333;
            padding: 100px 0;
        }

        .feature-box {
            text-align: center;
            padding: 30px;
        }

        .feature-box i {
            font-size: 3rem;
            color: #667eea;
            margin-bottom: 20px;
        }

        .feature-box h4 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 15px;
        }

        .stats-section {
            padding: 60px 0;
            text-align: center;
        }

        .stat-box {
            padding: 20px;
        }

        .stat-box h2 {
            font-size: 3.5rem;
            font-weight: 800;
            margin-bottom: 10px;
        }

        .stat-box p {
            font-size: 1.2rem;
            opacity: 0.9;
        }

        @media (max-width: 768px) {
            .hero-content h1 {
                font-size: 2.5rem;
            }
            
            .hero-content p {
                font-size: 1.2rem;
            }
            
            .role-card {
                width: 100%;
                max-width: 350px;
            }
        }
    </style>
</head>
<body>
    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="hero-content">
                <h1>Welcome to NovaHire</h1>
                <p>Connect talented professionals with amazing companies</p>
                
                <!-- Statistics -->
                <div class="stats-section">
                    <div class="row">
                        <div class="col-md-4 stat-box">
                            <h2>2000+</h2>
                            <p>Job Seekers</p>
                        </div>
                        <div class="col-md-4 stat-box">
                            <h2>150+</h2>
                            <p>Companies</p>
                        </div>
                        <div class="col-md-4 stat-box">
                            <h2>500+</h2>
                            <p>Jobs Posted</p>
                        </div>
                    </div>
                </div>

                <!-- Role Cards -->
                <div class="role-cards">
                    <!-- Job Seeker Card -->
                    <div class="role-card">
                        <i class="fas fa-user-graduate"></i>
                        <h3>Job Seeker</h3>
                        <p>Find your dream job, take skill assessments, and apply to top companies</p>
                        <a href="auth/login.php" class="btn-role">Login</a>
                        <a href="auth/registration.php" class="btn-role secondary">Register</a>
                        <div class="mt-3">
                            <a href="seeker/browse_jobs.php" class="btn-role secondary" style="width: 100%;">
                                <i class="fas fa-search mr-2"></i>Browse Jobs
                            </a>
                        </div>
                    </div>

                    <!-- Company Card -->
                    <div class="role-card">
                        <i class="fas fa-building"></i>
                        <h3>Company</h3>
                        <p>Post jobs, create custom quizzes, and find qualified candidates</p>
                        <a href="company_login.php" class="btn-role">Login</a>
                        <a href="company_registration.php" class="btn-role secondary">Register</a>
                        <div class="mt-3">
                            <small style="opacity: 0.8;">Post unlimited jobs & manage applicants</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features-section">
        <div class="container">
            <h2 class="text-center mb-5" style="font-size: 3rem; font-weight: 700;">Why Choose Us?</h2>
            <div class="row">
                <div class="col-md-3 feature-box">
                    <i class="fas fa-clipboard-check"></i>
                    <h4>Skill Assessments</h4>
                    <p>Take quizzes to prove your skills and stand out</p>
                </div>
                <div class="col-md-3 feature-box">
                    <i class="fas fa-briefcase"></i>
                    <h4>Quality Jobs</h4>
                    <p>Browse jobs from verified companies across industries</p>
                </div>
                <div class="col-md-3 feature-box">
                    <i class="fas fa-users"></i>
                    <h4>Easy Applications</h4>
                    <p>Apply to multiple jobs with one click</p>
                </div>
                <div class="col-md-3 feature-box">
                    <i class="fas fa-chart-line"></i>
                    <h4>Track Progress</h4>
                    <p>Monitor your applications and quiz results</p>
                </div>
            </div>

            <h2 class="text-center mt-5 mb-5" style="font-size: 3rem; font-weight: 700;">For Companies</h2>
            <div class="row">
                <div class="col-md-3 feature-box">
                    <i class="fas fa-plus-circle"></i>
                    <h4>Post Jobs</h4>
                    <p>Create detailed job postings in minutes</p>
                </div>
                <div class="col-md-3 feature-box">
                    <i class="fas fa-question-circle"></i>
                    <h4>Custom Quizzes</h4>
                    <p>Create job-specific assessments</p>
                </div>
                <div class="col-md-3 feature-box">
                    <i class="fas fa-user-check"></i>
                    <h4>View Applicants</h4>
                    <p>Access CVs and quiz results instantly</p>
                </div>
                <div class="col-md-3 feature-box">
                    <i class="fas fa-filter"></i>
                    <h4>Smart Filtering</h4>
                    <p>Find qualified candidates quickly</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer style="background: #333; color: white; padding: 40px 0;">
        <div class="container text-center">
            <h4 style="margin-bottom: 20px;">Ready to Get Started?</h4>
            <div class="mb-4">
                <a href="auth/registration.php" class="btn-role mr-3">Job Seeker Signup</a>
                <a href="company_registration.php" class="btn-role">Company Signup</a>
            </div>
            <div class="mt-4">
                <a href="seeker/browse_jobs.php" style="color: white; text-decoration: underline;">
                    <i class="fas fa-search mr-2"></i>Browse Available Jobs
                </a>
            </div>
            <p class="mt-4 mb-0">&copy; 2026 NovaHire. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>
