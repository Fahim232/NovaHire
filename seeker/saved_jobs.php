<?php
// Core setup: session, DB, BASE_URL, helpers
require_once __DIR__ . '/../includes/bootstrap.php';
if (!isset($_SESSION['id'])) {
    header('location: ' . BASE_URL . '/auth/login.php');
    exit();
}
require_once __DIR__ . '/../admin/dbcon.php';
require_once __DIR__ . '/../includes/functions.php';

$user_id = intval($_SESSION['id']);

$count_result = mysqli_query($con, "SELECT COUNT(*) as total FROM saved_jobs WHERE user_id = $user_id");
$total_saved = mysqli_fetch_assoc($count_result)['total'];

$jobs_query = "SELECT cj.*, c.company_name, c.industry, c.logo,
               (SELECT COUNT(*) FROM company_job_questions WHERE job_id = cj.id) as quiz_count,
               (SELECT COUNT(*) FROM job_applications WHERE job_id = cj.id) as applicant_count
               FROM saved_jobs sj
               JOIN company_jobs cj ON sj.job_id = cj.id
               JOIN companies c ON cj.company_id = c.id
               WHERE sj.user_id = $user_id
               ORDER BY sj.created_at DESC";
$jobs_result = mysqli_query($con, $jobs_query);
$jobs = [];
if ($jobs_result) {
    while ($row = mysqli_fetch_assoc($jobs_result)) {
        $jobs[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>My Saved Jobs | NovaHire</title>
    <?php require_once __DIR__ . '/../includes/links.php'; ?>
    <style>
        .saved-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 80px 0 40px 0;
            margin-top: -97px;
            border-radius: 5px;
        }
        .saved-header h1 {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 10px;
        }
        .saved-header .count-pill {
            background: rgba(255,255,255,0.2);
            padding: 8px 24px;
            border-radius: 50px;
            font-size: 1.1rem;
            display: inline-block;
            backdrop-filter: blur(5px);
        }
        .job-card {
            background: white;
            border-radius: 15px;
            padding: 16px 20px 12px 20px;
            margin-bottom: 14px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: all 0.3s;
            border-left: 5px solid #667eea;
        }
        .job-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(102,126,234,0.3);
        }
        .job-card h3 {
            color: #333;
            margin-bottom: 10px;
        }
        .company-name {
            color: #667eea;
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 15px;
        }
        .job-meta {
            color: #666;
            font-size: 0.95rem;
            margin: 6px 0;
        }
        .job-meta i {
            color: #667eea;
            width: 20px;
        }
        .job-tags {
            margin: 10px 0 8px 0;
        }
        .job-tag {
            display: inline-block;
            background: #e3f2fd;
            color: #1976d2;
            padding: 6px 15px;
            border-radius: 15px;
            font-size: 0.85rem;
            margin: 3px;
        }
        .btn-apply {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 10px 30px;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-apply:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102,126,234,0.4);
            color: white;
        }
        .btn-unsave {
            background: none;
            border: 2px solid #e53e3e;
            color: #e53e3e;
            border-radius: 50%;
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        .btn-unsave:hover {
            background: #e53e3e;
            color: white;
            transform: scale(1.1);
        }
        .btn-unsave.saved {
            background: #e53e3e;
            color: white;
            border-color: #e53e3e;
        }
        .no-jobs {
            text-align: center;
            padding: 80px 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .no-jobs i {
            font-size: 5rem;
            color: #ddd;
            margin-bottom: 20px;
        }
        .stats-badge {
            background: #f8f9fa;
            padding: 5px 12px;
            border-radius: 12px;
            font-size: 0.85rem;
            margin-left: 10px;
        }
        .job-description {
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
<!-- Navbar -->
<nav class="navbar navbar-expand-lg glass-nav fixed-top">
  <div class="container-fluid px-5 custom-nav-container">
      <a class="navbar-brand d-flex align-items-center" href="seeker_dashboard.php">
        <div class="brand-icon mr-2"><i class="fas fa-layer-group"></i></div>
        <span class="brand-text">Nova<span class="brand-highlight">Hire</span></span>
      </a>
      <button class="navbar-toggler custom-toggler" type="button" data-toggle="collapse" data-target="#collapsibleNavbar">
        <span class="fas fa-bars fa-lg text-dark"></span>
      </button>
      <div class="collapse navbar-collapse justify-content-between" id="collapsibleNavbar">
        <ul class="navbar-nav mx-auto center-menu">
           <li class="nav-item"><a class="nav-link" href="seeker_dashboard.php">Home</a></li>
           <li class="nav-item"><a class="nav-link" href="profile.php">Profile</a></li>
           <li class="nav-item"><a class="nav-link" href="browse_jobs.php">Browse Jobs</a></li>
           <li class="nav-item"><a class="nav-link" href="my_application.php">Applications</a></li>
           <li class="nav-item"><a class="nav-link" href="message_center.php">Messages</a></li>
        </ul>
        <ul class="navbar-nav align-items-center right-menu">
            <li class="nav-item dropdown">
                <a class="nav-link user-pill dropdown-toggle" href="#" id="userDropdown" role="button" data-toggle="dropdown">
                   <div class="user-avatar-sm"><i class="fas fa-user"></i></div>
                   <span class="user-name-text"><?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></span>
                </a>
                <div class="dropdown-menu dropdown-menu-right shadow-lg border-0 user-menu-dropdown" aria-labelledby="userDropdown">
                    <a class="dropdown-item" href="profile.php"><i class="fas fa-user-circle mr-2 text-muted"></i> My Profile</a>
                    <a class="dropdown-item" href="my_application.php"><i class="fas fa-file-alt mr-2 text-muted"></i> Applications</a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item text-danger" href="<?php echo BASE_URL; ?>/auth/logout.php"><i class="fas fa-sign-out-alt mr-2"></i> Logout</a>
                </div>
            </li>
        </ul>
      </div>
  </div>
</nav>
<div style="margin-top: 100px;"></div>

    <div class="saved-header">
        <div class="container text-center">
            <h1><i class="fas fa-heart mr-3"></i>My Saved Jobs</h1>
            <span class="count-pill"><i class="fas fa-bookmark mr-2"></i><?php echo $total_saved; ?> Saved</span>
        </div>
    </div>

    <div class="container mb-5">
        <?php if (count($jobs) > 0): ?>
            <?php foreach ($jobs as $job): ?>
                <div class="job-card" id="saved-job-<?php echo $job['id']; ?>">
                    <div class="d-flex justify-content-between align-items-start">
                        <?php if (!empty($job['logo'])): ?>
                            <div class="mr-3">
                                <img src="<?php echo $job['logo']; ?>" alt="<?php echo $job['company_name']; ?>"
                                     style="width: 80px; height: 80px; object-fit: contain; border-radius: 8px; border: 1px solid #e0e0e0; padding: 5px; background: white;">
                            </div>
                        <?php endif; ?>
                        <div class="flex-grow-1">
                            <h3><?php echo $job['job_title']; ?></h3>
                            <p class="company-name">
                                <i class="fas fa-building mr-2"></i><?php echo $job['company_name']; ?>
                            </p>
                            <div class="job-meta">
                                <i class="fas fa-map-marker-alt"></i> <?php echo $job['location']; ?>
                                <span class="ml-4"><i class="fas fa-briefcase"></i> <?php echo $job['employment_type']; ?></span>
                                <span class="ml-4"><i class="fas fa-clock"></i> <?php echo $job['experience_required']; ?></span>
                                <?php if ($job['salary_range']): ?>
                                    <span class="ml-4"><i class="fas fa-dollar-sign"></i> <?php echo $job['salary_range']; ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="job-meta">
                                <i class="fas fa-calendar"></i> Posted: <?php echo date('M d, Y', strtotime($job['posted_date'])); ?>
                                <span class="ml-4"><i class="fas fa-calendar-times"></i> Deadline: <?php echo date('M d, Y', strtotime($job['deadline'])); ?></span>
                            </div>
                            <p class="mt-3 text-muted job-description"><?php echo substr($job['job_description'], 0, 200) . '...'; ?></p>
                            <div class="job-tags">
                                <?php
                                $skills = explode(',', $job['skills_required']);
                                $display_skills = array_slice($skills, 0, 5);
                                foreach ($display_skills as $skill) {
                                    echo '<span class="job-tag">' . trim($skill) . '</span>';
                                }
                                if (count($skills) > 5) {
                                    echo '<span class="job-tag">+' . (count($skills) - 5) . ' more</span>';
                                }
                                ?>
                            </div>
                            <div class="mt-3">
                                <span class="badge badge-info"><i class="fas fa-tag mr-1"></i><?php echo $job['job_category']; ?></span>
                                <span class="stats-badge"><i class="fas fa-question-circle mr-1"></i><?php echo $job['quiz_count']; ?> Quiz</span>
                                <span class="stats-badge"><i class="fas fa-users mr-1"></i><?php echo $job['applicant_count']; ?> Applicants</span>
                                <span class="stats-badge"><i class="fas fa-users-cog mr-1"></i><?php echo $job['vacancy_count']; ?> Positions</span>
                            </div>
                        </div>
                        <div class="d-flex flex-column align-items-center ml-3">
                            <button class="btn-unsave saved mb-2" onclick="toggleSave(<?php echo $job['id']; ?>)" title="Unsave job">
                                <i class="fas fa-heart"></i>
                            </button>
                            <a href="job_details.php?id=<?php echo $job['id']; ?>" class="btn btn-apply">
                                <i class="fas fa-arrow-right"></i> View
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="no-jobs">
                <i class="fas fa-heart-broken"></i>
                <h3>No Saved Jobs Yet</h3>
                <p class="text-muted">Start exploring jobs and save the ones you like!</p>
                <a href="browse_jobs.php" class="btn btn-apply mt-3">
                    <i class="fas fa-search mr-2"></i>Browse Jobs
                </a>
            </div>
        <?php endif; ?>
    </div>

    <footer class="text-center py-4 mt-5" style="background: #f8f9fa;">
        <p class="text-muted mb-0">&copy; 2026 NovaHire. All rights reserved.</p>
    </footer>

    <script>
    function toggleSave(jobId) {
        fetch('api/toggle_save_job.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'job_id=' + jobId
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                if (!data.saved) {
                    var card = document.getElementById('saved-job-' + jobId);
                    if (card) {
                        card.style.transition = 'all 0.4s ease';
                        card.style.opacity = '0';
                        card.style.transform = 'translateX(100px)';
                        setTimeout(() => {
                            card.remove();
                            if (data.count === 0) {
                                location.reload();
                            }
                        }, 400);
                    }
                    if (typeof showToast === 'function') {
                        showToast('info', 'Removed', 'Job removed from saved list');
                    }
                }
            }
        })
        .catch(err => {
            console.error(err);
            if (typeof showToast === 'function') {
                showToast('error', 'Error', 'Could not update saved jobs');
            }
        });
    }
    </script>
</body>
</html>
