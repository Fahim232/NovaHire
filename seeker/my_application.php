<?php
session_start();
include 'admin/dbcon.php';
include('header.php');

if (!isset($_SESSION['id'])) {
    header('location: login.php');
}

$user_id = $_SESSION['id'];
$user_email = $_SESSION['email'];

// Get legacy job application
$selectquery = " select * from jobregistration where email='$user_email' "; 
$query = mysqli_query($con, $selectquery);
$result = mysqli_fetch_assoc($query);
$has_application = mysqli_num_rows($query) > 0;

// Get company job applications
$company_apps_query = "SELECT ja.*, cj.job_title, cj.location, cj.employment_type, cj.job_category,
                       c.company_name, c.industry, ja.applied_date, ja.quiz_status, ja.quiz_score, ja.application_status
                       FROM job_applications ja
                       JOIN company_jobs cj ON ja.job_id = cj.id
                       JOIN companies c ON cj.company_id = c.id
                       WHERE ja.user_id = '$user_id'
                       ORDER BY ja.applied_date DESC";
$company_apps_result = mysqli_query($con, $company_apps_query);
$has_company_apps = mysqli_num_rows($company_apps_result) > 0;

// Check grooming status for each company job application
$grooming_status = [];
if ($has_company_apps) {
    $temp_result = mysqli_query($con, $company_apps_query);
    while ($app = mysqli_fetch_assoc($temp_result)) {
        $job_category = $app['job_category'];
        $status_query = "SELECT * FROM user_quiz_status WHERE user_id = '$user_id' AND category = '$job_category'";
        $status_res = mysqli_query($con, $status_query);
        
        if (mysqli_num_rows($status_res) > 0) {
            $status_row = mysqli_fetch_assoc($status_res);
            $grooming_status[$app['id']] = [
                'needs_grooming' => ($status_row['status'] == 'failed' && $status_row['grooming_completed'] == 0),
                'grooming_completed' => $status_row['grooming_completed'],
                'quiz_status' => $status_row['status'],
                'category' => $job_category,
                'job_id' => $app['job_id']
            ];
        } else {
            $grooming_status[$app['id']] = [
                'needs_grooming' => false,
                'grooming_completed' => false,
                'quiz_status' => 'not_attempted',
                'category' => $job_category,
                'job_id' => $app['job_id']
            ];
        }
    }
}

if (isset($_POST['btnUpdate'])) {
    $id = $_POST['id'];
    $name = mysqli_real_escape_string($con, $_POST['name']);
    $phone = mysqli_real_escape_string($con, $_POST['phone']);
    $degree = mysqli_real_escape_string($con, $_POST['degree']);
    $refer = mysqli_real_escape_string($con, $_POST['refer']);
    $plang = mysqli_real_escape_string($con, $_POST['plang']);

    $update_clause = "name='$name', phone='$phone', degree='$degree', refer='$refer', planguage='$plang'";

    if (isset($_FILES['pdf_file']['name']) && $_FILES['pdf_file']['name'] != '') {
        $file_name = $_FILES['pdf_file']['name'];
        $file_tmp = $_FILES['pdf_file']['tmp_name'];
        move_uploaded_file($file_tmp,"./files/".$file_name);
        $update_clause .= ", cv_doc='$file_name'";
    }

    $updatequery = " update jobregistration set $update_clause where id='$id' ";
    $uquery = mysqli_query($con, $updatequery);

    if ($uquery){
        echo '<script>alert("Application Updated Successfully"); window.location.href="my_application.php";</script>';
    } else {
        echo '<script>alert("Update Failed");</script>';
    }
}
?>
<style>
    /* Minimalist colorful accents */
    .dashboard-container {
        max-width: 900px;
        margin: 80px auto;
    }

    .app-card {
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(20px);
        border-radius: 24px;
        border: 1px solid rgba(255,255,255,0.8);
        box-shadow: 0 20px 50px rgba(0,0,0,0.05);
        overflow: hidden;
        margin-bottom: 30px;
        transition: transform 0.3s;
    }

    .app-card:hover {
        transform: translateY(-5px);
    }

    .status-header {
        background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
        padding: 40px;
        text-align: center;
        position: relative;
    }

    .status-badge-lg {
        background: white;
        color: #2d3436;
        padding: 10px 25px;
        border-radius: 50px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
        font-size: 0.9rem;
        box-shadow: 0 10px 20px rgba(0,0,0,0.05);
        display: inline-block;
        margin-bottom: 20px;
    }

    .app-details-grid {
        padding: 40px;
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 30px;
    }

    .detail-item label {
        display: block;
        font-size: 0.75rem;
        text-transform: uppercase;
        color: #b2bec3;
        font-weight: 700;
        letter-spacing: 1px;
        margin-bottom: 8px;
    }

    .detail-item .value {
        font-size: 1.1rem;
        font-weight: 600;
        color: #2d3436;
    }

    .action-bar {
        padding: 20px 40px;
        background: rgba(245, 246, 250, 0.5);
        border-top: 1px solid rgba(0,0,0,0.05);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    /* Edit Form Styles */
    .edit-panel {
        background: white;
        border-radius: 24px;
        padding: 40px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.08);
        display: none; /* Hidden by default */
        animation: fadeIn 0.5s ease;
        margin-top: 20px;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .form-control-minimal {
        border: none;
        background: #f1f2f6;
        border-radius: 12px;
        padding: 15px 20px;
        width: 100%;
        font-weight: 500;
        color: #2d3436;
    }

    .form-control-minimal:focus {
        background: white;
        box-shadow: 0 0 0 2px var(--primary-color);
        outline: none;
    }
    
    /* Company Applications Section */
    .section-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: #2d3436;
        margin-bottom: 25px;
        padding-bottom: 15px;
        border-bottom: 3px solid #667eea;
    }
    
    .company-app-card {
        background: white;
        border-radius: 20px;
        padding: 30px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        margin-bottom: 20px;
        transition: all 0.3s ease;
        border-left: 5px solid #667eea;
    }
    
    .company-app-card:hover {
        transform: translateX(5px);
        box-shadow: 0 10px 30px rgba(0,0,0,0.12);
    }
    
    .company-app-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 20px;
        flex-wrap: wrap;
        gap: 15px;
    }
    
    .app-title {
        font-size: 1.3rem;
        font-weight: 700;
        color: #2d3436;
        margin-bottom: 5px;
    }
    
    .app-company {
        color: #667eea;
        font-weight: 600;
        font-size: 1rem;
    }
    
    .app-badges {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin: 15px 0;
    }
    
    .app-badge {
        padding: 6px 15px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
    }
    
    .badge-pending {
        background: #fff3cd;
        color: #856404;
    }
    
    .badge-reviewed {
        background: #d1ecf1;
        color: #0c5460;
    }
    
    .badge-shortlisted {
        background: #d4edda;
        color: #155724;
    }
    
    .badge-rejected {
        background: #f8d7da;
        color: #721c24;
    }
    
    .badge-passed {
        background: #d4edda;
        color: #155724;
    }
    
    .badge-failed {
        background: #f8d7da;
        color: #721c24;
    }
    
    .badge-not-taken {
        background: #e2e3e5;
        color: #383d41;
    }
    
    .quiz-score {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 8px 20px;
        border-radius: 25px;
        font-weight: 700;
        font-size: 1rem;
    }
    
    .app-meta {
        display: flex;
        gap: 20px;
        flex-wrap: wrap;
        color: #636e72;
        font-size: 0.9rem;
        margin-top: 15px;
    }
    
    .app-meta-item {
        display: flex;
        align-items: center;
        gap: 5px;
    }
    
    .app-actions {
        margin-top: 20px;
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    
    .no-apps-placeholder {
        text-align: center;
        padding: 60px 20px;
        background: rgba(255,255,255,0.5);
        border-radius: 20px;
        margin-bottom: 30px;
    }
    
    .no-apps-placeholder i {
        font-size: 4rem;
        color: #dfe6e9;
        margin-bottom: 20px;
    }

    .search-filter-bar {
        background: white;
        padding: 20px;
        border-radius: 15px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        margin-bottom: 25px;
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        align-items: center;
    }

    .search-filter-bar input,
    .search-filter-bar select {
        border-radius: 8px;
        border: 1px solid #e0e0e0;
        padding: 1px 15px;
        font-size: 0.95rem;
        transition: all 0.3s;
        margin-bottom: 5px;
    }

    .search-filter-bar input:focus,
    .search-filter-bar select:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        outline: none;
    }

    .search-filter-bar .btn {
        padding: 10px 25px;
        font-weight: 600;
        border-radius: 8px;
    }

    .search-filter-bar .btn-secondary {
        background: #f0f0f0;
        color: #333;
        border: none;
    }

    .search-filter-bar .btn-secondary:hover {
        background: #e0e0e0;
    }
</style>

<div class="container dashboard-container">
    <!-- Company Job Applications Section -->
    <?php if ($has_company_apps): ?>
    <div class="mb-5">
        <h2 class="section-title">
            <i class="fas fa-briefcase mr-2"></i>Company Job Applications
            <span class="badge badge-primary ml-2"><?php echo mysqli_num_rows($company_apps_result); ?></span>
        </h2>
        
        <!-- Search & Filter Bar -->
        <div class="search-filter-bar">
            <form method="GET" action="my_application.php" class="w-100 d-flex gap-2 flex-wrap align-items-center">
                <input 
                    type="text" 
                    name="search_company" 
                    class="form-control" 
                    placeholder="Search by company or job title..."
                    value="<?php echo isset($_GET['search_company']) ? htmlspecialchars($_GET['search_company']) : ''; ?>"
                    style="flex: 1; min-width: 200px;"
                >
                
                <select name="filter_status" class="form-control" style="min-width: 150px;">
                    <option value="">All Status</option>
                    <option value="pending" <?php echo (isset($_GET['filter_status']) && $_GET['filter_status'] === 'pending') ? 'selected' : ''; ?>>Pending</option>
                    <option value="reviewed" <?php echo (isset($_GET['filter_status']) && $_GET['filter_status'] === 'reviewed') ? 'selected' : ''; ?>>Reviewed</option>
                    <option value="shortlisted" <?php echo (isset($_GET['filter_status']) && $_GET['filter_status'] === 'shortlisted') ? 'selected' : ''; ?>>Shortlisted</option>
                    <option value="rejected" <?php echo (isset($_GET['filter_status']) && $_GET['filter_status'] === 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                </select>
                
                <button type="submit" class="btn btn-primary" style="padding: 10px 30px;">
                    <i class="fas fa-search mr-2"></i>Search
                </button>
                
                <a href="my_application.php" class="btn btn-secondary">
                    <i class="fas fa-redo mr-2"></i>Reset
                </a>
            </form>
        </div>
        
        <?php 
            // Apply search and filter
            $filtered_apps = [];
            mysqli_data_seek($company_apps_result, 0);
            
            $search_query = isset($_GET['search_company']) ? strtolower($_GET['search_company']) : '';
            $status_filter = isset($_GET['filter_status']) ? $_GET['filter_status'] : '';
            
            while ($app = mysqli_fetch_assoc($company_apps_result)) {
                $matches_search = true;
                $matches_status = true;
                
                if (!empty($search_query)) {
                    $job_text = strtolower($app['job_title'] . ' ' . $app['company_name'] . ' ' . $app['industry']);
                    $matches_search = strpos($job_text, $search_query) !== false;
                }
                
                if (!empty($status_filter)) {
                    $matches_status = $app['application_status'] === $status_filter;
                }
                
                if ($matches_search && $matches_status) {
                    $filtered_apps[] = $app;
                }
            }
            
            // Display filtered results or no results message
            if (count($filtered_apps) > 0):
        ?>
        
        <?php foreach ($filtered_apps as $app): ?>
        <div class="company-app-card">
            <div class="company-app-header">
                <div>
                    <div class="app-title"><?php echo $app['job_title']; ?></div>
                    <div class="app-company">
                        <i class="fas fa-building mr-1"></i><?php echo $app['company_name']; ?> • <?php echo $app['industry']; ?>
                    </div>
                </div>
                <div>
                    <?php if ($app['quiz_score'] !== null): ?>
                        <div class="quiz-score">
                            <i class="fas fa-chart-line"></i>
                            Quiz: <?php echo round($app['quiz_score']); ?>%
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="app-badges">
                <span class="app-badge badge-<?php echo $app['application_status']; ?>">
                    <i class="fas fa-clipboard-check mr-1"></i><?php echo ucfirst($app['application_status']); ?>
                </span>
                
                <?php if ($app['quiz_status']): ?>
                    <span class="app-badge badge-<?php echo str_replace('_', '-', $app['quiz_status']); ?>">
                        <i class="fas fa-question-circle mr-1"></i>Quiz: <?php echo ucfirst(str_replace('_', ' ', $app['quiz_status'])); ?>
                    </span>
                <?php endif; ?>
                
                <span class="app-badge" style="background: #e3f2fd; color: #1565c0;">
                    <i class="fas fa-tag mr-1"></i><?php echo $app['job_category']; ?>
                </span>
            </div>
            
            <div class="app-meta">
                <div class="app-meta-item">
                    <i class="fas fa-map-marker-alt"></i>
                    <?php echo $app['location']; ?>
                </div>
                <div class="app-meta-item">
                    <i class="fas fa-briefcase"></i>
                    <?php echo $app['employment_type']; ?>
                </div>
                <div class="app-meta-item">
                    <i class="fas fa-calendar"></i>
                    Applied: <?php echo date('M d, Y', strtotime($app['applied_date'])); ?>
                </div>
            </div>
            
            <div class="app-actions">
                <a href="job_details.php?id=<?php echo $app['job_id']; ?>" class="btn btn-outline-primary btn-sm rounded-pill">
                    <i class="fas fa-eye mr-1"></i>View Job Details
                </a>
                
                <?php 
                    $groom_info = isset($grooming_status[$app['id']]) ? $grooming_status[$app['id']] : null;
                    if ($groom_info && $groom_info['needs_grooming']): 
                ?>
                    <a href="grooming.php?category=<?php echo urlencode($groom_info['category']); ?>&job_id=<?php echo $groom_info['job_id']; ?>" class="btn btn-warning btn-sm rounded-pill" title="Complete grooming session to improve your score">
                        <i class="fas fa-graduation-cap mr-1"></i>Complete Grooming
                    </a>
                <?php elseif ($groom_info && $groom_info['grooming_completed']): ?>
                    <span class="btn btn-success btn-sm rounded-pill disabled">
                        <i class="fas fa-check mr-1"></i>Grooming Completed
                    </span>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        
        <?php else: ?>
            <div class="no-apps-placeholder">
                <i class="fas fa-search"></i>
                <h4 class="font-weight-bold text-dark">No Applications Match Your Search</h4>
                <p class="text-muted mb-4">Try adjusting your search or filters</p>
                <a href="my_application.php" class="btn btn-primary rounded-pill px-4 font-weight-bold">
                    <i class="fas fa-redo mr-2"></i>Clear Filters
                </a>
            </div>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="no-apps-placeholder">
        <i class="fas fa-search"></i>
        <h4 class="font-weight-bold text-dark">No Company Job Applications Yet</h4>
        <p class="text-muted mb-4">Start applying for jobs posted by companies</p>
        <a href="browse_jobs.php" class="btn btn-primary rounded-pill px-4 font-weight-bold">
            <i class="fas fa-search mr-2"></i>Browse Available Jobs
        </a>
    </div>
    <?php endif; ?>
    
    <!-- Legacy Application Section -->
    <?php if($has_application): ?>
    <!-- Legacy Application Section -->
    <?php if($has_application): ?>
      <div class="mb-5">
        <h2 class="section-title">
            <i class="fas fa-clipboard-list mr-2"></i>Portal Application (Legacy)
        </h2>
      <!-- Main Application Card -->
      <div class="app-card">
          <div class="status-header">
              <span class="status-badge-lg"><i class="fas fa-satellite-dish mr-2 text-primary"></i> Active Details</span>
              <h2 class="mb-0 font-weight-bold text-dark">Developer Application</h2>
              <p class="text-muted mt-2 mb-0">Submitted on <?php echo date("F j, Y"); ?></p>
          </div>
          
          <div class="app-details-grid">
             <div class="detail-item">
                 <label>Full Name</label>
                 <div class="value"><?php echo htmlspecialchars($result['name']); ?></div>
             </div>
             <div class="detail-item">
                 <label>Phone</label>
                 <div class="value"><?php echo htmlspecialchars($result['phone']); ?></div>
             </div>
             <div class="detail-item">
                 <label>Degree</label>
                 <div class="value"><?php echo htmlspecialchars($result['degree']); ?></div>
             </div>
             <div class="detail-item">
                 <label>Programming Language</label>
                 <div class="value"><?php echo htmlspecialchars($result['planguage']); ?></div>
             </div>
             <div class="detail-item">
                 <label>Referral</label>
                 <div class="value"><?php echo htmlspecialchars($result['refer']); ?></div>
             </div>
             <div class="detail-item">
                 <label>CV Document</label>
                 <div class="value">
                    <a href="files/<?php echo $result['cv_doc']; ?>" target="_blank" class="text-primary font-weight-bold">
                        <i class="fas fa-file-pdf mr-1"></i> View File
                    </a>
                 </div>
             </div>
          </div>

          <div class="action-bar">
              <button class="btn btn-light rounded-pill px-4 font-weight-bold text-muted" onclick="toggleEdit()">
                  <i class="fas fa-cog mr-2"></i> Settings
              </button>
              <button class="btn btn-primary rounded-pill px-5 font-weight-bold shadow-sm" onclick="toggleEdit()">
                  <i class="fas fa-pen mr-2"></i> Edit Details
              </button>
          </div>
      </div>

      <!-- Hidden Edit Form -->
      <div class="edit-panel" id="editForm">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="font-weight-bold m-0">Update Information</h4>
            <button class="btn btn-sm btn-light rounded-circle" onclick="toggleEdit()"><i class="fas fa-times"></i></button>
        </div>
        
        <form method="post" action="" enctype="multipart/form-data">
            <input type="hidden" name="id" value="<?php echo $result['id']; ?>" />
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="small font-weight-bold text-muted ml-2">Display Name</label>
                    <input type="text" name="name" class="form-control-minimal" value="<?php echo htmlspecialchars($result['name']); ?>" />
                </div>
                <div class="col-md-6 mb-3">
                    <label class="small font-weight-bold text-muted ml-2">Phone Number</label>
                    <input type="text" name="phone" class="form-control-minimal" value="<?php echo htmlspecialchars($result['phone']); ?>" />
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="small font-weight-bold text-muted ml-2">Qualification</label>
                    <input type="text" name="degree" class="form-control-minimal" value="<?php echo htmlspecialchars($result['degree']); ?>" />
                </div>
                 <div class="col-md-6 mb-3">
                    <label class="small font-weight-bold text-muted ml-2">Tech Stack</label>
                    <input type="text" name="plang" class="form-control-minimal" value="<?php echo htmlspecialchars($result['planguage']); ?>" />
                </div>
            </div>
            
            <div class="mb-4">
                <label class="small font-weight-bold text-muted ml-2">Referral</label>
                <input type="text" name="refer" class="form-control-minimal" value="<?php echo htmlspecialchars($result['refer']); ?>" />
            </div>

            <div class="p-4 rounded mb-4" style="background: #f8f9fa; border: 1px dashed #ced6e0;">
                <label class="small font-weight-bold text-muted mb-2 d-block">Update CV (Optional)</label>
                <div class="custom-file">
                    <input type="file" name="pdf_file" class="custom-file-input" id="cvFile" accept=".pdf">
                    <label class="custom-file-label border-0" for="cvFile" style="background: transparent;">Choose new PDF file...</label>
                </div>
                <small class="text-muted mt-2 d-block">Leave empty to keep current CV.</small>
            </div>

            <div class="text-right">
                <button type="button" class="btn btn-link text-muted mr-3" onclick="toggleEdit()">Cancel</button>
                <button type="submit" name="btnUpdate" class="btn btn-success rounded-pill px-5 font-weight-bold shadow">Save Changes</button>
            </div>
        </form>
      </div>
      </div>
      
    <?php endif; ?>
    
    <?php if (!$has_company_apps): ?>
        <div class="text-center" style="padding: 100px 0;">
            <div class="mb-4">
                <span style="display: inline-block; padding: 30px; background: rgba(255,255,255,0.1); border-radius: 50%; backdrop-filter: blur(10px);">
                    <i class="fas fa-folder-open fa-4x text-white-50"></i>
                </span>
            </div>
            <h2 class="text-white font-weight-bold mb-3">No Active Applications</h2>
            <p class="text-white-50 mb-4">Start your journey by selecting a role from the dashboard or browse company jobs.</p>
            <a href="index.php" class="btn btn-light rounded-pill px-5 py-3 font-weight-bold text-primary shadow-lg hover-lift mr-2">
                Browse Portal Openings
            </a>
            <a href="browse_jobs.php" class="btn btn-outline-light rounded-pill px-5 py-3 font-weight-bold shadow-lg hover-lift">
                Browse Company Jobs
            </a>
        </div>
        <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script>
    function toggleEdit() {
        var x = document.getElementById("editForm");
        if (x.style.display === "none" || x.style.display === "") {
            x.style.display = "block";
            // Scroll to edit form
            x.scrollIntoView({behavior: "smooth"});
        } else {
            x.style.display = "none";
        }
    }

    // Custom file input name display
    $(".custom-file-input").on("change", function() {
      var fileName = $(this).val().split("\\").pop();
      $(this).siblings(".custom-file-label").addClass("selected").html(fileName);
    });
</script>

</body>
</html>
