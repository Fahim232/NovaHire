<?php 
session_start();
include('admin/dbcon.php');
include('header.php');

// Check if user is logged in
if (!isset($_SESSION['id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['id'];

// Get categories where user has passed the quiz
$passed_categories = [];
$query_passed = "SELECT DISTINCT category FROM user_quiz_status WHERE user_id = ? AND status = 'Passed'";
$stmt = mysqli_prepare($con, $query_passed);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result_passed = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result_passed)) {
    $passed_categories[] = $row['category'];
}
mysqli_stmt_close($stmt);

// Get selected category from URL or use first passed category
$selected_category = isset($_GET['category']) ? mysqli_real_escape_string($con, $_GET['category']) : (count($passed_categories) > 0 ? $passed_categories[0] : '');

// Handle application submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['apply_company'])) {
    $company_id = intval($_POST['company_id']);
    $category = mysqli_real_escape_string($con, $_POST['category']);
    $user_message = mysqli_real_escape_string($con, $_POST['user_message']);
    
    // Check if already applied
    $check_query = "SELECT id FROM category_applications WHERE user_id = ? AND company_id = ? AND category = ?";
    $stmt = mysqli_prepare($con, $check_query);
    mysqli_stmt_bind_param($stmt, "iis", $user_id, $company_id, $category);
    mysqli_stmt_execute($stmt);
    $check_result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($check_result) > 0) {
        $alert_message = "You have already applied to this company for $category position.";
        $alert_type = "warning";
    } else {
        // Insert application
        $insert_query = "INSERT INTO category_applications (user_id, company_id, category, user_message, status) VALUES (?, ?, ?, ?, 'Pending')";
        $stmt = mysqli_prepare($con, $insert_query);
        mysqli_stmt_bind_param($stmt, "iiss", $user_id, $company_id, $category, $user_message);
        
        if (mysqli_stmt_execute($stmt)) {
            $alert_message = "Application submitted successfully!";
            $alert_type = "success";
        } else {
            $alert_message = "Failed to submit application. Please try again.";
            $alert_type = "danger";
        }
    }
    mysqli_stmt_close($stmt);
}

// Category descriptions mapping
$category_info = [
    'PHP' => ['title' => 'PHP Developer', 'desc' => 'Backend Logic, MySQL, Server-side scripting', 'icon' => 'fab fa-php', 'color' => '#777BB3'],
    'Java' => ['title' => 'Java Developer', 'desc' => 'OOPs, Spring Boot, Enterprise Applications', 'icon' => 'fab fa-java', 'color' => '#007396'],
    'Python' => ['title' => 'Python Developer', 'desc' => 'Scripting, Automation, Django/Flask', 'icon' => 'fab fa-python', 'color' => '#3776AB'],
    'Frontend' => ['title' => 'Frontend Developer', 'desc' => 'HTML5, CSS3, Responsive Design', 'icon' => 'fab fa-html5', 'color' => '#E34F26'],
    'JavaScript' => ['title' => 'JavaScript Developer', 'desc' => 'ES6+, DOM Manipulation, Async Programming', 'icon' => 'fab fa-js', 'color' => '#F7DF1E'],
    'UI/UX' => ['title' => 'UI/UX Designer', 'desc' => 'User Research, Wireframing, Prototyping', 'icon' => 'fas fa-pencil-ruler', 'color' => '#FF6B6B'],
    'DataScience' => ['title' => 'Data Scientist', 'desc' => 'Data Analysis, ML, Python/Pandas', 'icon' => 'fas fa-chart-line', 'color' => '#4ECDC4'],
    'Marketing' => ['title' => 'Digital Marketing', 'desc' => 'SEO, Content Strategy, Social Media', 'icon' => 'fas fa-bullhorn', 'color' => '#FF6B6B'],
    'DB' => ['title' => 'Database Admin', 'desc' => 'SQL Optimization, Schema Design, Security', 'icon' => 'fas fa-database', 'color' => '#336791']
];

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Available Companies - Apply by Category</title>
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        
        .companies-header {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin: 30px auto;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .category-selector {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 20px;
        }
        
        .category-badge {
            padding: 10px 20px;
            border-radius: 25px;
            border: 2px solid #ddd;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            color: #333;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .category-badge:hover, .category-badge.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: #667eea;
            transform: translateY(-2px);
        }
        
        .company-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }
        
        .company-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }
        
        .company-logo {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 10px;
            border: 2px solid #f0f0f0;
        }
        
        .company-name {
            font-size: 1.5rem;
            font-weight: bold;
            color: #333;
            margin: 0;
        }
        
        .company-info {
            display: flex;
            gap: 15px;
            margin-top: 15px;
            flex-wrap: wrap;
        }
        
        .info-badge {
            background: #f8f9fa;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .apply-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .apply-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .already-applied {
            background: #28a745;
            color: white;
            padding: 10px 20px;
            border-radius: 20px;
            font-size: 0.9rem;
        }
        
        .no-companies {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 15px;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            animation: fadeIn 0.3s;
        }
        
        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            animation: slideDown 0.3s;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideDown {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .close-modal {
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            color: #999;
        }
        
        .close-modal:hover {
            color: #333;
        }
    </style>
</head>
<body>
    <div class="container" style="margin-top: 80px;">
        
        <?php if (isset($alert_message)): ?>
        <div class="alert alert-<?php echo $alert_type; ?> alert-dismissible fade show" role="alert">
            <strong><?php echo $alert_message; ?></strong>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
        <?php endif; ?>
        
        <!-- Header Section -->
        <div class="companies-header">
            <h1 class="mb-2"><i class="fas fa-building"></i> Available Companies</h1>
            <p class="text-muted mb-0">Apply to companies in categories where you've passed the quiz</p>
            
            <?php if (count($passed_categories) == 0): ?>
                <div class="alert alert-info mt-3">
                    <i class="fas fa-info-circle"></i> You haven't passed any quiz yet. 
                    <a href="index.php" class="alert-link">Take a quiz</a> to unlock company applications!
                </div>
            <?php else: ?>
                <div class="category-selector">
                    <?php foreach ($passed_categories as $cat): ?>
                        <?php $info = isset($category_info[$cat]) ? $category_info[$cat] : ['title' => $cat, 'icon' => 'fas fa-code', 'color' => '#999']; ?>
                        <a href="?category=<?php echo urlencode($cat); ?>" 
                           class="category-badge <?php echo $selected_category == $cat ? 'active' : ''; ?>">
                            <i class="<?php echo $info['icon']; ?>" style="color: <?php echo $info['color']; ?>;"></i>
                            <?php echo $info['title']; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($selected_category)): ?>
            <?php
            // Get companies that have posted jobs or are registered
            $companies_query = "SELECT DISTINCT c.* FROM companies c 
                               WHERE c.id IS NOT NULL 
                               ORDER BY c.company_name ASC";
            $companies_result = mysqli_query($con, $companies_query);
            
            if (mysqli_num_rows($companies_result) > 0):
            ?>
                <div class="row">
                    <?php while ($company = mysqli_fetch_assoc($companies_result)): ?>
                        <?php
                        // Check if already applied
                        $check_applied = "SELECT id, status FROM category_applications 
                                         WHERE user_id = ? AND company_id = ? AND category = ?";
                        $stmt = mysqli_prepare($con, $check_applied);
                        mysqli_stmt_bind_param($stmt, "iis", $user_id, $company['id'], $selected_category);
                        mysqli_stmt_execute($stmt);
                        $applied_result = mysqli_stmt_get_result($stmt);
                        $application = mysqli_fetch_assoc($applied_result);
                        mysqli_stmt_close($stmt);
                        ?>
                        
                        <div class="col-md-6 mb-4">
                            <div class="company-card">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div class="d-flex align-items-center">
                                        <?php if (!empty($company['logo'])): ?>
                                            <img src="uploads/company_logos/<?php echo htmlspecialchars($company['logo']); ?>" 
                                                 alt="<?php echo htmlspecialchars($company['company_name']); ?>" 
                                                 class="company-logo mr-3">
                                        <?php else: ?>
                                            <div class="company-logo mr-3 d-flex align-items-center justify-content-center" 
                                                 style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                                <i class="fas fa-building text-white fa-2x"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <h3 class="company-name"><?php echo htmlspecialchars($company['company_name']); ?></h3>
                                            <p class="text-muted mb-0"><?php echo htmlspecialchars($company['company_email'] ?? 'N/A'); ?></p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="company-info">
                                    <span class="info-badge">
                                        <i class="fas fa-briefcase" style="color: <?php echo $category_info[$selected_category]['color']; ?>;"></i>
                                        <?php echo $category_info[$selected_category]['title']; ?>
                                    </span>
                                    <span class="info-badge">
                                        <i class="fas fa-map-marker-alt text-danger"></i>
                                        Remote
                                    </span>
                                    <span class="info-badge">
                                        <i class="fas fa-clock text-info"></i>
                                        Full-time
                                    </span>
                                </div>
                                
                                <p class="mt-3 text-muted">
                                    <i class="fas fa-info-circle"></i> <?php echo $category_info[$selected_category]['desc']; ?>
                                </p>
                                
                                <div class="mt-3">
                                    <?php if ($application): ?>
                                        <span class="already-applied">
                                            <i class="fas fa-check-circle"></i> Applied - Status: <?php echo $application['status']; ?>
                                        </span>
                                    <?php else: ?>
                                        <button class="apply-btn" onclick="openApplyModal(<?php echo $company['id']; ?>, '<?php echo htmlspecialchars($company['company_name']); ?>', '<?php echo $selected_category; ?>')">
                                            <i class="fas fa-paper-plane"></i> Apply Now
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="no-companies">
                    <i class="fas fa-building fa-5x text-muted mb-3"></i>
                    <h3>No companies available</h3>
                    <p class="text-muted">Check back later for new opportunities!</p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <!-- Application Modal -->
    <div id="applyModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeApplyModal()">&times;</span>
            <h3 class="mb-4"><i class="fas fa-paper-plane"></i> Apply to <span id="modalCompanyName"></span></h3>
            
            <form method="POST" action="">
                <input type="hidden" name="company_id" id="modalCompanyId">
                <input type="hidden" name="category" id="modalCategory">
                
                <div class="form-group">
                    <label for="user_message"><strong>Cover Message (Optional)</strong></label>
                    <textarea name="user_message" id="user_message" class="form-control" rows="4" 
                              placeholder="Tell the company why you're a great fit for this position..."></textarea>
                    <small class="form-text text-muted">
                        <i class="fas fa-lightbulb"></i> Tip: Mention your experience and skills related to <?php echo $selected_category; ?>
                    </small>
                </div>
                
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> Your profile and quiz scores will be shared with the company.
                </div>
                
                <button type="submit" name="apply_company" class="apply-btn btn-block">
                    <i class="fas fa-check"></i> Submit Application
                </button>
            </form>
        </div>
    </div>
    
    <script>
        function openApplyModal(companyId, companyName, category) {
            document.getElementById('modalCompanyId').value = companyId;
            document.getElementById('modalCompanyName').textContent = companyName;
            document.getElementById('modalCategory').value = category;
            document.getElementById('applyModal').style.display = 'block';
        }
        
        function closeApplyModal() {
            document.getElementById('applyModal').style.display = 'none';
            document.getElementById('user_message').value = '';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('applyModal');
            if (event.target == modal) {
                closeApplyModal();
            }
        }
    </script>
</body>
</html>
