<?php 
session_start();
include('../admin/dbcon.php');

// Check if company is logged in
if (!isset($_SESSION['company_id'])) {
    header('Location: ../company_login.php');
    exit();
}

$company_id = $_SESSION['company_id'];
$company_name = $_SESSION['company_name'];
$company_logo = isset($_SESSION['company_logo']) ? $_SESSION['company_logo'] : '';

// Handle status update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $application_id = intval($_POST['application_id']);
    $new_status = mysqli_real_escape_string($con, $_POST['status']);
    $company_notes = mysqli_real_escape_string($con, $_POST['company_notes']);
    
    $update_query = "UPDATE category_applications SET status = ?, company_notes = ? WHERE id = ? AND company_id = ?";
    $stmt = mysqli_prepare($con, $update_query);
    mysqli_stmt_bind_param($stmt, "ssii", $new_status, $company_notes, $application_id, $company_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $alert_message = "Application status updated successfully!";
        $alert_type = "success";
    } else {
        $alert_message = "Failed to update status.";
        $alert_type = "danger";
    }
    mysqli_stmt_close($stmt);
}

// Get filter parameters
$filter_category = isset($_GET['category']) ? mysqli_real_escape_string($con, $_GET['category']) : 'all';
$filter_status = isset($_GET['status']) ? mysqli_real_escape_string($con, $_GET['status']) : 'all';
$search = isset($_GET['search']) ? mysqli_real_escape_string($con, $_GET['search']) : '';

// Get all categories that have applications for this company
$categories_query = "SELECT DISTINCT category FROM category_applications WHERE company_id = ? ORDER BY category";
$stmt = mysqli_prepare($con, $categories_query);
mysqli_stmt_bind_param($stmt, "i", $company_id);
mysqli_stmt_execute($stmt);
$categories_result = mysqli_stmt_get_result($stmt);
$available_categories = [];
while ($row = mysqli_fetch_assoc($categories_result)) {
    $available_categories[] = $row['category'];
}
mysqli_stmt_close($stmt);

// Build query with filters
$applications_query = "SELECT ca.*, u.username, u.email, u.phone, 
                       uqs.status as quiz_status, uqs.last_attempt
                       FROM category_applications ca
                       INNER JOIN user_info u ON ca.user_id = u.id
                       LEFT JOIN user_quiz_status uqs ON ca.user_id = uqs.user_id AND ca.category = uqs.category
                       WHERE ca.company_id = ?";

$params = [$company_id];
$types = "i";

if ($filter_category != 'all') {
    $applications_query .= " AND ca.category = ?";
    $params[] = $filter_category;
    $types .= "s";
}

if ($filter_status != 'all') {
    $applications_query .= " AND ca.status = ?";
    $params[] = $filter_status;
    $types .= "s";
}

if (!empty($search)) {
    $search_param = "%$search%";
    $applications_query .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

$applications_query .= " ORDER BY ca.application_date DESC";

$stmt = mysqli_prepare($con, $applications_query);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$applications_result = mysqli_stmt_get_result($stmt);

// Get statistics
$stats_query = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'Interview' THEN 1 ELSE 0 END) as interview,
                SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) as rejected
                FROM category_applications WHERE company_id = ?";
$stmt_stats = mysqli_prepare($con, $stats_query);
mysqli_stmt_bind_param($stmt_stats, "i", $company_id);
mysqli_stmt_execute($stmt_stats);
$stats = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_stats));
mysqli_stmt_close($stmt_stats);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Category Applicants - <?php echo htmlspecialchars($company_name); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --info-color: #3b82f6;
        }
        
        body {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .navbar {
            background: white !important;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .navbar-brand {
            font-weight: bold;
            color: var(--primary-color) !important;
        }
        
        .company-logo-nav {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 8px;
            margin-right: 10px;
        }
        
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin: 10px 0;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
            text-transform: uppercase;
        }
        
        .filters-container {
            background: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .applicants-table {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .table thead {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
        }
        
        .status-badge {
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: bold;
            display: inline-block;
        }
        
        .status-Pending {
            background: #fef3c7;
            color: #92400e;
        }
        
        .status-Approved {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-Interview {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .status-Rejected {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .action-btn {
            padding: 8px 15px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.3s;
        }
        
        .btn-view {
            background: var(--info-color);
            color: white;
        }
        
        .btn-view:hover {
            background: #2563eb;
            transform: scale(1.05);
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            animation: fadeIn 0.3s;
        }
        
        .modal-content {
            background: white;
            margin: 3% auto;
            padding: 0;
            border-radius: 15px;
            width: 90%;
            max-width: 700px;
            max-height: 90vh;
            overflow-y: auto;
            animation: slideDown 0.3s;
        }
        
        .modal-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 20px 30px;
            border-radius: 15px 15px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-body {
            padding: 30px;
        }
        
        .close-modal {
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            color: white;
            opacity: 0.8;
        }
        
        .close-modal:hover {
            opacity: 1;
        }
        
        .applicant-detail {
            margin-bottom: 20px;
        }
        
        .detail-label {
            font-weight: bold;
            color: #666;
            margin-bottom: 5px;
        }
        
        .detail-value {
            color: #333;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .quiz-score {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 15px;
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            border-radius: 10px;
            margin: 15px 0;
        }
        
        .score-circle {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--success-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.2rem;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideDown {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 15px;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light sticky-top">
        <div class="container-fluid">
            <?php if (!empty($company_logo)): ?>
                <img src="../uploads/company_logos/<?php echo htmlspecialchars($company_logo); ?>" 
                     alt="<?php echo htmlspecialchars($company_name); ?>" 
                     class="company-logo-nav">
            <?php else: ?>
                <i class="fas fa-building fa-2x text-primary mr-2"></i>
            <?php endif; ?>
            <a class="navbar-brand" href="index.php"><?php echo htmlspecialchars($company_name); ?></a>
            
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ml-auto">
                    <li class="nav-item"><a class="nav-link" href="index.php"><i class="fas fa-home"></i> Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="my_jobs.php"><i class="fas fa-briefcase"></i> My Jobs</a></li>
                    <li class="nav-item active"><a class="nav-link" href="category_applicants.php"><i class="fas fa-users"></i> Category Applicants</a></li>
                    <li class="nav-item"><a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>
    
    <div class="container" style="margin-top: 30px; margin-bottom: 50px;">
        
        <?php if (isset($alert_message)): ?>
        <div class="alert alert-<?php echo $alert_type; ?> alert-dismissible fade show">
            <?php echo $alert_message; ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
        <?php endif; ?>
        
        <!-- Statistics -->
        <div class="stats-container">
            <div class="stat-card">
                <i class="fas fa-file-alt fa-3x" style="color: var(--primary-color);"></i>
                <div class="stat-number" style="color: var(--primary-color);"><?php echo $stats['total']; ?></div>
                <div class="stat-label">Total Applications</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-clock fa-3x" style="color: var(--warning-color);"></i>
                <div class="stat-number" style="color: var(--warning-color);"><?php echo $stats['pending']; ?></div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-user-check fa-3x" style="color: var(--info-color);"></i>
                <div class="stat-number" style="color: var(--info-color);"><?php echo $stats['interview']; ?></div>
                <div class="stat-label">Interview</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-check-circle fa-3x" style="color: var(--success-color);"></i>
                <div class="stat-number" style="color: var(--success-color);"><?php echo $stats['approved']; ?></div>
                <div class="stat-label">Approved</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-times-circle fa-3x" style="color: var(--danger-color);"></i>
                <div class="stat-number" style="color: var(--danger-color);"><?php echo $stats['rejected']; ?></div>
                <div class="stat-label">Rejected</div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="filters-container">
            <h4 class="mb-3"><i class="fas fa-filter"></i> Filter Applications</h4>
            <form method="GET" action="">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label><strong>Category</strong></label>
                        <select name="category" class="form-control">
                            <option value="all" <?php echo $filter_category == 'all' ? 'selected' : ''; ?>>All Categories</option>
                            <?php foreach ($available_categories as $cat): ?>
                                <option value="<?php echo $cat; ?>" <?php echo $filter_category == $cat ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label><strong>Status</strong></label>
                        <select name="status" class="form-control">
                            <option value="all" <?php echo $filter_status == 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="Pending" <?php echo $filter_status == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="Interview" <?php echo $filter_status == 'Interview' ? 'selected' : ''; ?>>Interview</option>
                            <option value="Approved" <?php echo $filter_status == 'Approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="Rejected" <?php echo $filter_status == 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label><strong>Search</strong></label>
                        <input type="text" name="search" class="form-control" placeholder="Name or email..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> Apply Filters
                </button>
                <a href="category_applicants.php" class="btn btn-secondary">
                    <i class="fas fa-redo"></i> Reset
                </a>
            </form>
        </div>
        
        <!-- Applicants Table -->
        <?php if (mysqli_num_rows($applications_result) > 0): ?>
        <div class="applicants-table">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Applicant</th>
                        <th>Category</th>
                        <th>Quiz Status</th>
                        <th>Applied Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($app = mysqli_fetch_assoc($applications_result)): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($app['username']); ?></strong><br>
                            <small class="text-muted"><?php echo htmlspecialchars($app['email']); ?></small>
                        </td>
                        <td>
                            <span class="badge badge-primary"><?php echo htmlspecialchars($app['category']); ?></span>
                        </td>
                        <td>
                            <?php if ($app['quiz_status']): ?>
                                <span class="badge badge-<?php echo $app['quiz_status'] == 'passed' ? 'success' : 'warning'; ?>">
                                    <?php echo ucfirst($app['quiz_status']); ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">N/A</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($app['application_date'])); ?></td>
                        <td><span class="status-badge status-<?php echo $app['status']; ?>"><?php echo $app['status']; ?></span></td>
                        <td>
                            <button class="action-btn btn-view" onclick="viewApplication(<?php echo $app['id']; ?>)">
                                <i class="fas fa-eye"></i> View
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-inbox fa-5x text-muted mb-3"></i>
            <h3>No Applications Found</h3>
            <p class="text-muted">No applicants match your current filters.</p>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- View Application Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="mb-0"><i class="fas fa-user"></i> Application Details</h3>
                <span class="close-modal" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body" id="modalContent">
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="sr-only">Loading...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewApplication(applicationId) {
            document.getElementById('viewModal').style.display = 'block';
            
            // Fetch application details via AJAX
            $.ajax({
                url: 'get_application_details.php',
                method: 'POST',
                data: { application_id: applicationId },
                success: function(response) {
                    document.getElementById('modalContent').innerHTML = response;
                },
                error: function() {
                    document.getElementById('modalContent').innerHTML = 
                        '<div class="alert alert-danger">Failed to load application details.</div>';
                }
            });
        }
        
        function closeModal() {
            document.getElementById('viewModal').style.display = 'none';
        }
        
        window.onclick = function(event) {
            const modal = document.getElementById('viewModal');
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>
