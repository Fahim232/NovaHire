<?php
session_start();
include 'admin/dbcon.php';
include('header.php');

// Pull logged-in user's skills for recommendations
$user_skills = [];
if (isset($_SESSION['id'])) {
    $uid = intval($_SESSION['id']);
    $skill_res = mysqli_query($con, "SELECT user_skills FROM user_info WHERE id = $uid");
    if ($skill_res && mysqli_num_rows($skill_res) === 1) {
        $skill_row = mysqli_fetch_assoc($skill_res);
        $raw_skills = strtolower($skill_row['user_skills']);
        $user_skills = array_filter(array_map('trim', explode(',', $raw_skills)));
    }
}
$has_user_skills = !empty($user_skills);

// Get filter parameters
$category_filter = isset($_GET['category']) ? $_GET['category'] : 'all';
$location_filter = isset($_GET['location']) ? $_GET['location'] : '';
$type_filter = isset($_GET['type']) ? $_GET['type'] : 'all';

// Build query
$where_clause = "cj.status = 'active' AND cj.deadline >= CURDATE()";

if ($category_filter != 'all') {
    $where_clause .= " AND cj.job_category = '" . mysqli_real_escape_string($con, $category_filter) . "'";
}

if (!empty($location_filter)) {
    $where_clause .= " AND cj.location LIKE '%" . mysqli_real_escape_string($con, $location_filter) . "%'";
}

if ($type_filter != 'all') {
    $where_clause .= " AND cj.employment_type = '" . mysqli_real_escape_string($con, $type_filter) . "'";
}

// Fetch jobs
$jobs_query = "SELECT cj.*, c.company_name, c.industry, c.logo,
                   (SELECT COUNT(*) FROM company_job_questions WHERE job_id = cj.id) as quiz_count,
                   (SELECT COUNT(*) FROM job_applications WHERE job_id = cj.id) as applicant_count
                   FROM company_jobs cj
                   JOIN companies c ON cj.company_id = c.id
                   WHERE $where_clause";
$jobs_result = mysqli_query($con, $jobs_query);

// Build jobs array with recommendation score
$jobs = [];
if ($jobs_result) {
    while ($row = mysqli_fetch_assoc($jobs_result)) {
        $match_score = 0;
        if ($has_user_skills) {
            $job_text = strtolower($row['job_category'] . ' ' . $row['skills_required'] . ' ' . $row['job_title']);
            foreach ($user_skills as $skill) {
                if ($skill !== '' && strpos($job_text, $skill) !== false) {
                    $match_score++;
                }
            }
        }
        $row['match_score'] = $match_score;
        $jobs[] = $row;
    }

    // Recommended jobs first, then newest
    usort($jobs, function ($a, $b) {
        if ($a['match_score'] === $b['match_score']) {
            return strcmp($b['posted_date'], $a['posted_date']);
        }
        return $b['match_score'] <=> $a['match_score'];
    });
}

// Get categories for filter
$categories_query = "SELECT DISTINCT job_category FROM company_jobs WHERE status = 'active'";
$categories_result = mysqli_query($con, $categories_query);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>Browse Jobs | NovaHire</title>
    <style>
        .jobs-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 80px 0 40px 0;
            margin-top: -97px;
            border-radius: 5px;
        }

        .jobs-header h1 {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 20px;
        }

        .filter-section {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            margin-top: 10px;
            margin-bottom: 28px;
        }

        .job-card {
            height: 50%;
            background: white;
            border-radius: 15px;
            padding: 16px 20px 12px 20px;
            /* tighter bottom padding */
            margin-bottom: 14px;
            /* slightly reduced spacing between cards */
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s;
            border-left: 5px solid #667eea;
        }

        .job-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
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
            /* reduce vertical gaps */
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
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            color: white;
        }

        .no-jobs {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
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

        /* Ensure last element inside card has no bottom margin to avoid extra space */
        .job-card .flex-grow-1>*:last-child {
            margin-bottom: 0 !important;
        }

        /* Clamp long descriptions to keep cards compact */
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
    <!-- Header -->
    <div class="jobs-header">
        <div class="container text-center">
            <h1><i class="fas fa-briefcase mr-3"></i>Browse Available Jobs</h1>
            <p class="lead">Find your perfect career opportunity from top companies</p>
        </div>
    </div>

    <div class="container">
        <!-- Filter Section -->
        <div class="filter-section">
            <form method="GET" action="">
                <div class="row">
                    <div class="col-md-4">
                        <label><i class="fas fa-tag mr-2"></i>Category</label>
                        <select name="category" class="form-control">
                            <option value="all">All Categories</option>
                            <?php while ($cat = mysqli_fetch_assoc($categories_result)): ?>
                                <option value="<?php echo $cat['job_category']; ?>" <?php echo ($category_filter == $cat['job_category']) ? 'selected' : ''; ?>>
                                    <?php echo $cat['job_category']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label><i class="fas fa-map-marker-alt mr-2"></i>Location</label>
                        <input type="text" name="location" class="form-control" placeholder="Enter location..." value="<?php echo $location_filter; ?>">
                    </div>
                    <div class="col-md-3">
                        <label><i class="fas fa-briefcase mr-2"></i>Employment Type</label>
                        <select name="type" class="form-control">
                            <option value="all">All Types</option>
                            <option value="Full-Time" <?php echo ($type_filter == 'Full-Time') ? 'selected' : ''; ?>>Full-Time</option>
                            <option value="Part-Time" <?php echo ($type_filter == 'Part-Time') ? 'selected' : ''; ?>>Part-Time</option>
                            <option value="Contract" <?php echo ($type_filter == 'Contract') ? 'selected' : ''; ?>>Contract</option>
                            <option value="Internship" <?php echo ($type_filter == 'Internship') ? 'selected' : ''; ?>>Internship</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="fas fa-search mr-2"></i>Search
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Jobs List -->
        <div class="mb-5">
            <?php if (count($jobs) > 0): ?>
                <h4 class="mb-4">
                    <i class="fas fa-list mr-2"></i><?php echo count($jobs); ?> Jobs Found
                </h4>

                <?php foreach ($jobs as $job): ?>
                    <?php $is_recommended = $has_user_skills && $job['match_score'] > 0; ?>
                    <div class="job-card">
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
                                    <span class="badge badge-info">
                                        <i class="fas fa-tag mr-1"></i><?php echo $job['job_category']; ?>
                                    </span>
                                    <?php if ($job['quiz_count'] > 0): ?>
                                        <?php
                                        $quiz_badge = '';
                                        if (isset($_SESSION['id'])) {
                                            $chk_quiz = mysqli_query($con, "SELECT * FROM job_applications WHERE user_id=".$_SESSION['id']." AND job_id=".$job['id']." AND quiz_status='passed'");
                                            if (mysqli_num_rows($chk_quiz) > 0) {
                                                $quiz_badge = '<span class="badge badge-success"><i class="fas fa-check-circle mr-1"></i>Quiz Passed</span>';
                                            } else {
                                                $quiz_badge = '<span class="badge badge-warning" style="background:#fff3cd;color:#856404;"><i class="fas fa-clipboard-check mr-1"></i>Quiz Required</span>';
                                            }
                                        } else {
                                            $quiz_badge = '<span class="badge badge-warning" style="background:#fff3cd;color:#856404;"><i class="fas fa-clipboard-check mr-1"></i>Quiz Required</span>';
                                        }
                                        echo $quiz_badge;
                                        ?>
                                    <?php else: ?>
                                        <span class="badge badge-light"><i class="fas fa-check-circle mr-1"></i>No Quiz</span>
                                    <?php endif; ?>
                                    <span class="stats-badge">
                                        <i class="fas fa-users mr-1"></i><?php echo $job['applicant_count']; ?> Applicants
                                    </span>
                                    <span class="stats-badge">
                                        <i class="fas fa-users-cog mr-1"></i><?php echo $job['vacancy_count']; ?> Positions
                                    </span>
                                    <?php if ($is_recommended): ?>
                                        <span class="badge badge-success">Recommended for you</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="ml-4 text-right">
                                <?php
                                $is_saved = false;
                                if (isset($_SESSION['id'])) {
                                    $chk = mysqli_query($con, "SELECT id FROM saved_jobs WHERE user_id=".$_SESSION['id']." AND job_id=".$job['id']);
                                    $is_saved = mysqli_num_rows($chk) > 0;
                                }
                                ?>
                                <button class="btn btn-sm mb-2 save-toggle-btn" style="border-radius:50%;width:42px;height:42px;padding:0;display:flex;align-items:center;justify-content:center;<?php echo $is_saved ? 'background:#fee2e2;color:#ef4444;border-color:#fca5a5;' : 'background:#f1f5f9;color:#94a3b8;border-color:#e2e8f0;'; ?>" onclick="toggleSave(this, <?php echo $job['id']; ?>)" title="<?php echo $is_saved ? 'Unsave Job' : 'Save Job'; ?>">
                                    <i class="fa<?php echo $is_saved ? 's' : 'r'; ?> fa-heart"></i>
                                </button>
                                <?php if ($job['quiz_count'] > 0): ?>
                                    <a href="job_details.php?id=<?php echo $job['id']; ?>" class="btn btn-apply d-block" style="background: linear-gradient(135deg, #f6ad55 0%, #ed8936 100%);">
                                        <i class="fas fa-clipboard-check mr-2"></i>Take Quiz to Apply
                                    </a>
                                <?php else: ?>
                                    <a href="job_details.php?id=<?php echo $job['id']; ?>" class="btn btn-apply d-block">
                                        <i class="fas fa-arrow-right mr-2"></i>View & Apply
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-jobs">
                    <i class="fas fa-search"></i>
                    <h3>No Jobs Found</h3>
                    <p class="text-muted">Try adjusting your filters to see more results</p>
                    <a href="browse_jobs.php" class="btn btn-primary mt-3">
                        <i class="fas fa-redo mr-2"></i>Clear Filters
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <footer class="text-center py-4 mt-5" style="background: #f8f9fa;">
        <p class="text-muted mb-0">&copy; 2026 NovaHire. All rights reserved.</p>
    </footer>

    <script>
    function toggleSave(btn, jobId) {
        fetch('api/toggle_save_job.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'job_id=' + jobId
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                if (data.saved) {
                    btn.style.background = '#fee2e2';
                    btn.style.color = '#ef4444';
                    btn.style.borderColor = '#fca5a5';
                    btn.querySelector('i').className = 'fas fa-heart';
                    btn.title = 'Unsave Job';
                } else {
                    btn.style.background = '#f1f5f9';
                    btn.style.color = '#94a3b8';
                    btn.style.borderColor = '#e2e8f0';
                    btn.querySelector('i').className = 'far fa-heart';
                    btn.title = 'Save Job';
                }
            }
        });
    }
    </script>
</body>

</html>