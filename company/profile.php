<?php
    session_start();
    include '../admin/dbcon.php';
    
    // Check if company is logged in
    if (!isset($_SESSION['company_id'])) {
        header('Location: ../company_login.php');
        exit;
    }
    
    $company_id = $_SESSION['company_id'];
    
    // Fetch company details
    $company_query = "SELECT * FROM companies WHERE id = $company_id";
    $company_result = mysqli_query($con, $company_query);
    $company = mysqli_fetch_assoc($company_result);
    
    // Update profile
    if (isset($_POST['update_profile'])) {
        $company_name = mysqli_real_escape_string($con, $_POST['company_name']);
        $phone = mysqli_real_escape_string($con, $_POST['phone']);
        $address = mysqli_real_escape_string($con, $_POST['address']);
        $website = mysqli_real_escape_string($con, $_POST['website']);
        $industry = mysqli_real_escape_string($con, $_POST['industry']);
        $company_size = mysqli_real_escape_string($con, $_POST['company_size']);
        $description = mysqli_real_escape_string($con, $_POST['description']);
        
        // Handle logo upload
        $logo_path = $company['logo']; // Keep existing logo by default
        if (isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] == 0) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
            $max_size = 2 * 1024 * 1024; // 2MB
            
            if (in_array($_FILES['company_logo']['type'], $allowed_types) && $_FILES['company_logo']['size'] <= $max_size) {
                $upload_dir = '../uploads/company_logos/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $file_extension = pathinfo($_FILES['company_logo']['name'], PATHINFO_EXTENSION);
                $new_filename = 'company_' . $company_id . '_' . time() . '.' . $file_extension;
                $logo_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['company_logo']['tmp_name'], $logo_path)) {
                    // Delete old logo if exists
                    if (!empty($company['logo']) && file_exists($company['logo'])) {
                        unlink($company['logo']);
                    }
                } else {
                    $error_msg = '<div class="alert alert-danger">Failed to upload logo.</div>';
                    $logo_path = $company['logo'];
                }
            } else {
                $error_msg = '<div class="alert alert-danger">Invalid file. Please upload an image (JPG, PNG, GIF) under 2MB.</div>';
            }
        }
        
        $update_query = "UPDATE companies SET 
                        company_name = '$company_name',
                        company_phone = '$phone',
                        company_address = '$address',
                        company_website = '$website',
                        industry = '$industry',
                        company_size = '$company_size',
                        description = '$description',
                        logo = '$logo_path'
                        WHERE id = $company_id";
        
        if (mysqli_query($con, $update_query)) {
            $_SESSION['company_name'] = $company_name;
            $_SESSION['company_logo'] = $logo_path;
            $success_msg = '<div class="alert alert-success">Profile updated successfully!</div>';
            // Refresh company data
            $company_result = mysqli_query($con, $company_query);
            $company = mysqli_fetch_assoc($company_result);
        } else {
            $error_msg = '<div class="alert alert-danger">Failed to update profile.</div>';
        }
    }
    
    // Change password
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (password_verify($current_password, $company['password'])) {
            if ($new_password === $confirm_password) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $password_query = "UPDATE companies SET password = '$hashed_password' WHERE id = $company_id";
                if (mysqli_query($con, $password_query)) {
                    $success_msg = '<div class="alert alert-success">Password changed successfully!</div>';
                } else {
                    $error_msg = '<div class="alert alert-danger">Failed to change password.</div>';
                }
            } else {
                $error_msg = '<div class="alert alert-danger">New passwords do not match.</div>';
            }
        } else {
            $error_msg = '<div class="alert alert-danger">Current password is incorrect.</div>';
        }
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Company Profile | Dashboard</title>
    <?php include '../links.php'; ?>
    <style>
        /* Theme tokens */
        :root {
            --bg-main: linear-gradient(135deg, #9da2b3 0%, #f5f7ff 100%);
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
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 30px;
            color: var(--text-primary);
            border-bottom: 3px solid #667eea;
            padding-bottom: 10px;
        }

        .form-control {
            border: 1px solid var(--glass-border);
            background: var(--glass-bg);
            color: var(--text-primary);
            padding: 6px 15px;
            border-radius: 12px;
            transition: all 0.3s ease;
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

        .btn-update {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 25px;
            padding: 12px 40px;
            color: white;
            font-weight: 600;
        }

        .btn-update:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
            color: white;
        }

        .profile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            text-align: center;
        }

        .profile-header h2 {
            margin: 10px 0;
            font-weight: 700;
        }

        .profile-header p {
            margin: 0;
            opacity: 0.9;
        }

        .info-box {
            background: var(--badge-bg);
            border: 1px solid var(--glass-border);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            color: var(--text-secondary);
        }

        .info-item {
            margin-bottom: 15px;
        }

        .info-item strong {
            color: #667eea;
            display: inline-block;
            width: 150px;
        }

        .badge {
            background: var(--badge-bg);
            border: 1px solid var(--glass-border);
            color: var(--text-primary);
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <?php include 'company_header.php'; ?>

    <div class="container">
        <!-- Profile Header -->
        <div class="profile-header mt-4">
            <?php if (!empty($company['logo']) && file_exists($company['logo'])): ?>
                <img src="<?php echo $company['logo']; ?>" alt="<?php echo $company['company_name']; ?>" 
                     style="width: 100px; height: 100px; object-fit: contain; border-radius: 10px; background: white; padding: 10px; margin-bottom: 15px;">
            <?php else: ?>
                <i class="fas fa-building fa-3x mb-3"></i>
            <?php endif; ?>
            <h2><?php echo $company['company_name']; ?></h2>
            <p><i class="fas fa-envelope mr-2"></i><?php echo $company['company_email']; ?></p>
            <p><small>Member since <?php echo date('M Y', strtotime($company['registration_date'])); ?></small></p>
        </div>

        <!-- Current Information -->
        <div class="content-section">
            <h3 class="section-title"><i class="fas fa-info-circle mr-2"></i>Current Information</h3>
            <div class="info-box">
                <div class="info-item">
                    <strong><i class="fas fa-building mr-2"></i>Company:</strong>
                    <?php echo $company['company_name']; ?>
                </div>
                <div class="info-item">
                    <strong><i class="fas fa-envelope mr-2"></i>Email:</strong>
                    <?php echo $company['company_email']; ?>
                </div>
                <div class="info-item">
                    <strong><i class="fas fa-phone mr-2"></i>Phone:</strong>
                    <?php echo $company['company_phone']; ?>
                </div>
                <div class="info-item">
                    <strong><i class="fas fa-industry mr-2"></i>Industry:</strong>
                    <?php echo $company['industry']; ?>
                </div>
                <div class="info-item">
                    <strong><i class="fas fa-users mr-2"></i>Company Size:</strong>
                    <?php echo $company['company_size']; ?> employees
                </div>
                <div class="info-item">
                    <strong><i class="fas fa-globe mr-2"></i>Website:</strong>
                    <?php echo $company['company_website'] ? '<a href="' . $company['company_website'] . '" target="_blank">' . $company['company_website'] . '</a>' : 'Not provided'; ?>
                </div>
                <div class="info-item">
                    <strong><i class="fas fa-map-marker-alt mr-2"></i>Address:</strong>
                    <?php echo $company['company_address']; ?>
                </div>
                <div class="info-item">
                    <strong><i class="fas fa-file-alt mr-2"></i>Description:</strong>
                    <p><?php echo $company['description']; ?></p>
                </div>
                <div class="info-item">
                    <strong><i class="fas fa-image mr-2"></i>Company Logo:</strong>
                    <?php if (!empty($company['logo']) && file_exists($company['logo'])): ?>
                        <div class="mt-2">
                            <img src="<?php echo $company['logo']; ?>" alt="Company Logo" 
                                 style="max-width: 200px; max-height: 100px; object-fit: contain; border-radius: 8px; border: 1px solid #e0e0e0; padding: 10px; background: white;">
                        </div>
                    <?php else: ?>
                        <span class="text-muted">No logo uploaded</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Edit Profile -->
        <div class="content-section">
            <h3 class="section-title"><i class="fas fa-edit mr-2"></i>Edit Profile</h3>
            
            <?php if (isset($error_msg)) echo $error_msg; ?>
            <?php if (isset($success_msg)) echo $success_msg; ?>
            
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="company_name">Company Name *</label>
                            <input type="text" class="form-control" id="company_name" name="company_name" 
                                   value="<?php echo $company['company_name']; ?>" required>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="phone">Phone Number *</label>
                            <input type="tel" class="form-control" id="phone" name="phone" 
                                   value="<?php echo $company['company_phone']; ?>" required>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="website">Website</label>
                            <input type="url" class="form-control" id="website" name="website" 
                                   value="<?php echo $company['company_website']; ?>">
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="industry">Industry *</label>
                            <select class="form-control" id="industry" name="industry" required>
                                <option value="Information Technology" <?php echo ($company['industry'] == 'Information Technology') ? 'selected' : ''; ?>>Information Technology</option>
                                <option value="Software Development" <?php echo ($company['industry'] == 'Software Development') ? 'selected' : ''; ?>>Software Development</option>
                                <option value="Web Development" <?php echo ($company['industry'] == 'Web Development') ? 'selected' : ''; ?>>Web Development</option>
                                <option value="Mobile App Development" <?php echo ($company['industry'] == 'Mobile App Development') ? 'selected' : ''; ?>>Mobile App Development</option>
                                <option value="E-commerce" <?php echo ($company['industry'] == 'E-commerce') ? 'selected' : ''; ?>>E-commerce</option>
                                <option value="Finance" <?php echo ($company['industry'] == 'Finance') ? 'selected' : ''; ?>>Finance</option>
                                <option value="Healthcare" <?php echo ($company['industry'] == 'Healthcare') ? 'selected' : ''; ?>>Healthcare</option>
                                <option value="Education" <?php echo ($company['industry'] == 'Education') ? 'selected' : ''; ?>>Education</option>
                                <option value="Consulting" <?php echo ($company['industry'] == 'Consulting') ? 'selected' : ''; ?>>Consulting</option>
                                <option value="Other" <?php echo ($company['industry'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="company_size">Company Size *</label>
                            <select class="form-control" id="company_size" name="company_size" required>
                                <option value="1-10" <?php echo ($company['company_size'] == '1-10') ? 'selected' : ''; ?>>1-10 employees</option>
                                <option value="11-50" <?php echo ($company['company_size'] == '11-50') ? 'selected' : ''; ?>>11-50 employees</option>
                                <option value="51-200" <?php echo ($company['company_size'] == '51-200') ? 'selected' : ''; ?>>51-200 employees</option>
                                <option value="201-500" <?php echo ($company['company_size'] == '201-500') ? 'selected' : ''; ?>>201-500 employees</option>
                                <option value="501-1000" <?php echo ($company['company_size'] == '501-1000') ? 'selected' : ''; ?>>501-1000 employees</option>
                                <option value="1000+" <?php echo ($company['company_size'] == '1000+') ? 'selected' : ''; ?>>1000+ employees</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="col-md-12">
                        <div class="form-group">
                    
                            <label for="address">Company Address *</label>
                            <textarea class="form-control" id="address" name="address" rows="2" required><?php echo $company['company_address']; ?></textarea>
                        </div>
                    </div>
                    
                    <div class="col-md-12">
                        <div class="form-group">
                            <label for="description">Company Description *</label>
                            <textarea class="form-control" id="description" name="description" rows="4" required><?php echo $company['description']; ?></textarea>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                            <label for="company_logo">Company Logo</label>
                            <?php if (!empty($company['logo']) && file_exists($company['logo'])): ?>
                                <div class="mb-2">
                                    <img src="<?php echo $company['logo']; ?>" alt="Current Logo" 
                                         style="max-width: 150px; max-height: 80px; object-fit: contain; border-radius: 8px; border: 1px solid #e0e0e0; padding: 8px; background: white;">
                                    <p class="text-muted small mt-1">Current logo</p>
                                </div>
                            <?php endif; ?>
                            <input type="file" class="form-control" id="company_logo" name="company_logo" accept="image/*">
                            <small class="form-text text-muted">Upload JPG, PNG, or GIF (max 2MB). Recommended size: 200x100px</small>
                        </div>
                <button type="submit" name="update_profile" class="btn btn-update">
                    <i class="fas fa-save mr-2"></i>Update Profile
                </button>
            </form>
        </div>

        <!-- Change Password -->
        <div class="content-section">
            <h3 class="section-title"><i class="fas fa-lock mr-2"></i>Change Password</h3>
            
            <form method="POST" action="">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="current_password">Current Password *</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="new_password">New Password *</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" required minlength="6">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password *</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="6">
                        </div>
                    </div>
                </div>
                
                <button type="submit" name="change_password" class="btn btn-update">
                    <i class="fas fa-key mr-2"></i>Change Password
                </button>
            </form>
        </div>
    </div>

    <footer class="text-center py-4 mt-5">
        <p class="text-muted">&copy; 2026 NovaHire. All rights reserved.</p>
    </footer>
</body>
</html>
