<?php 
session_start();
include('../admin/dbcon.php');

// Check if company is logged in
if (!isset($_SESSION['company_id'])) {
    echo '<div class="alert alert-danger">Unauthorized access</div>';
    exit();
}

$company_id = $_SESSION['company_id'];

if (!isset($_POST['application_id'])) {
    echo '<div class="alert alert-danger">Invalid request</div>';
    exit();
}

$application_id = intval($_POST['application_id']);

// Get application details
$query = "SELECT ca.*, u.username, u.email, u.phone, u.user_degree, u.user_skills,
          uqs.status as quiz_status, uqs.last_attempt
          FROM category_applications ca
          INNER JOIN user_info u ON ca.user_id = u.id
          LEFT JOIN user_quiz_status uqs ON ca.user_id = uqs.user_id AND ca.category = uqs.category
          WHERE ca.id = ? AND ca.company_id = ?";
          
$stmt = mysqli_prepare($con, $query);
mysqli_stmt_bind_param($stmt, "ii", $application_id, $company_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$app = mysqli_fetch_assoc($result);

if (!$app) {
    echo '<div class="alert alert-danger">Application not found</div>';
    exit();
}

?>

<div class="applicant-detail">
    <div class="detail-label"><i class="fas fa-user"></i> Full Name</div>
    <div class="detail-value"><?php echo htmlspecialchars($app['username']); ?></div>
</div>

<div class="applicant-detail">
    <div class="detail-label"><i class="fas fa-envelope"></i> Email</div>
    <div class="detail-value"><?php echo htmlspecialchars($app['email']); ?></div>
</div>

<div class="applicant-detail">
    <div class="detail-label"><i class="fas fa-phone"></i> Phone</div>
    <div class="detail-value"><?php echo htmlspecialchars($app['phone'] ?: 'Not provided'); ?></div>
</div>

<div class="applicant-detail">
    <div class="detail-label"><i class="fas fa-graduation-cap"></i> Education</div>
    <div class="detail-value"><?php echo htmlspecialchars($app['user_degree'] ?: 'Not provided'); ?></div>
</div>

<div class="applicant-detail">
    <div class="detail-label"><i class="fas fa-code"></i> Skills</div>
    <div class="detail-value"><?php echo htmlspecialchars($app['user_skills'] ?: 'Not provided'); ?></div>
</div>

<div class="applicant-detail">
    <div class="detail-label"><i class="fas fa-briefcase"></i> Applied For</div>
    <div class="detail-value">
        <span class="badge badge-primary badge-lg" style="font-size: 1rem; padding: 10px 20px;">
            <?php echo htmlspecialchars($app['category']); ?> Developer
        </span>
    </div>
</div>

<?php if ($app['quiz_status']): ?>
<div class="applicant-detail">
    <div class="detail-label"><i class="fas fa-trophy"></i> Quiz Status</div>
    <div class="quiz-score">
        <div class="score-circle" style="background: <?php echo $app['quiz_status'] == 'passed' ? '#10b981' : '#f59e0b'; ?>;">
            <i class="fas fa-<?php echo $app['quiz_status'] == 'passed' ? 'check' : 'times'; ?>"></i>
        </div>
        <div>
            <strong>Status: <?php echo ucfirst($app['quiz_status']); ?></strong><br>
            <small class="text-muted">
                Last Attempt: <?php echo date('M d, Y', strtotime($app['last_attempt'])); ?>
            </small>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="applicant-detail">
    <div class="detail-label"><i class="fas fa-calendar"></i> Application Date</div>
    <div class="detail-value"><?php echo date('F d, Y \a\t h:i A', strtotime($app['application_date'])); ?></div>
</div>

<?php if ($app['user_message']): ?>
<div class="applicant-detail">
    <div class="detail-label"><i class="fas fa-comment"></i> Cover Message</div>
    <div class="detail-value" style="white-space: pre-wrap;"><?php echo htmlspecialchars($app['user_message']); ?></div>
</div>
<?php endif; ?>

<div class="applicant-detail">
    <div class="detail-label"><i class="fas fa-info-circle"></i> Current Status</div>
    <div class="detail-value">
        <span class="status-badge status-<?php echo $app['status']; ?>" style="font-size: 1rem; padding: 10px 20px;">
            <?php echo $app['status']; ?>
        </span>
    </div>
</div>

<?php if ($app['company_notes']): ?>
<div class="applicant-detail">
    <div class="detail-label"><i class="fas fa-sticky-note"></i> Your Notes</div>
    <div class="detail-value" style="white-space: pre-wrap;"><?php echo htmlspecialchars($app['company_notes']); ?></div>
</div>
<?php endif; ?>

<hr>

<!-- Update Status Form -->
<form method="POST" action="category_applicants.php">
    <input type="hidden" name="application_id" value="<?php echo $app['id']; ?>">
    
    <div class="form-group">
        <label><strong><i class="fas fa-tasks"></i> Update Status</strong></label>
        <select name="status" class="form-control" required>
            <option value="Pending" <?php echo $app['status'] == 'Pending' ? 'selected' : ''; ?>>Pending</option>
            <option value="Interview" <?php echo $app['status'] == 'Interview' ? 'selected' : ''; ?>>Call for Interview</option>
            <option value="Approved" <?php echo $app['status'] == 'Approved' ? 'selected' : ''; ?>>Approved</option>
            <option value="Rejected" <?php echo $app['status'] == 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
        </select>
    </div>
    
    <div class="form-group">
        <label><strong><i class="fas fa-comment-dots"></i> Notes (Optional)</strong></label>
        <textarea name="company_notes" class="form-control" rows="3" 
                  placeholder="Add notes about this applicant..."><?php echo htmlspecialchars($app['company_notes'] ?: ''); ?></textarea>
    </div>
    
    <button type="submit" name="update_status" class="btn btn-primary btn-block btn-lg">
        <i class="fas fa-save"></i> Update Status
    </button>
</form>

<div class="mt-3">
    <a href="../view_cv.php?id=<?php echo $app['user_id']; ?>" target="_blank" class="btn btn-info btn-block">
        <i class="fas fa-file-alt"></i> View Full CV
    </a>
</div>
