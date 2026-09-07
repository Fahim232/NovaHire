<?php
require_once __DIR__ . '/../includes/bootstrap.php';

if (!isset($_SESSION['admin_username'])) {
    header('Location: admin_login.php');
    exit;
}

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: admin_dashboard.php');
    exit;
}

// Get company data
$stmt = mysqli_prepare($con, "SELECT * FROM companies WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$company = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$company) {
    header('Location: admin_dashboard.php?error=not_found');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    
    $company_name = trim($_POST['company_name'] ?? '');
    $company_email = trim($_POST['company_email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $industry = trim($_POST['industry'] ?? '');
    $status = trim($_POST['status'] ?? 'active');
    
    $update_stmt = mysqli_prepare($con, "UPDATE companies SET company_name = ?, company_email = ?, phone = ?, address = ?, industry = ?, status = ? WHERE id = ?");
    mysqli_stmt_bind_param($update_stmt, "ssssssi", $company_name, $company_email, $phone, $address, $industry, $status, $id);
    
    if (mysqli_stmt_execute($update_stmt)) {
        header('Location: admin_dashboard.php?success=updated');
        exit;
    } else {
        $error = "Failed to update company.";
    }
    mysqli_stmt_close($update_stmt);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Edit Company | NovaHire Admin</title>
    <?php include 'header.php'; ?>
    <style>
        .edit-page { max-width: 700px; margin: 40px auto; padding: 0 20px; }
        .form-card { background: var(--bg-card); border: 1px solid var(--border-light); border-radius: 20px; padding: 30px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 8px; }
        .form-control { width: 100%; padding: 12px 16px; border: 1px solid var(--border-light); border-radius: 10px; font-size: 0.95rem; }
        .form-control:focus { border-color: #0ea5e9; outline: none; }
        .btn { padding: 12px 24px; border-radius: 10px; font-weight: 600; cursor: pointer; border: none; }
        .btn-primary { background: linear-gradient(135deg, #1a56db, #0ea5e9); color: white; }
        .btn-secondary { background: #f1f5f9; color: #475569; }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="edit-page">
        <h1>Edit Company</h1>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="form-card">
            <form method="POST">
                <?php echo csrf_input(); ?>
                
                <div class="form-group">
                    <label>Company Name</label>
                    <input type="text" name="company_name" class="form-control" value="<?php echo e($company['company_name']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="company_email" class="form-control" value="<?php echo e($company['company_email']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="phone" class="form-control" value="<?php echo e($company['phone'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label>Address</label>
                    <textarea name="address" class="form-control" rows="2"><?php echo e($company['address'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label>Industry</label>
                    <input type="text" name="industry" class="form-control" value="<?php echo e($company['industry'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" class="form-control">
                        <option value="active" <?php echo $company['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $company['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                
                <div style="display:flex; gap:10px;">
                    <a href="admin_dashboard.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
