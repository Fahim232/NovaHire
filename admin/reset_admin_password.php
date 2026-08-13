<?php
include 'dbcon.php';

$message = '';
$success = false;

if (isset($_POST['reset'])) {
    $username = mysqli_real_escape_string($con, $_POST['username']);
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if ($new_password !== $confirm_password) {
        $message = "Passwords do not match!";
    } elseif (strlen($new_password) < 6) {
        $message = "Password must be at least 6 characters!";
    } else {
        // Hash the new password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Check if admin user exists
        $check_query = "SELECT * FROM admin_login WHERE admin_user_name = '$username'";
        $result = mysqli_query($con, $check_query);
        
        if (mysqli_num_rows($result) > 0) {
            // Update existing admin password
            $update_query = "UPDATE admin_login SET admin_password = '$hashed_password' WHERE admin_user_name = '$username'";
            if (mysqli_query($con, $update_query)) {
                $message = "Password updated successfully! You can now login.";
                $success = true;
            } else {
                $message = "Error updating password: " . mysqli_error($con);
            }
        } else {
            // Create new admin user
            $insert_query = "INSERT INTO admin_login (admin_user_name, admin_password) VALUES ('$username', '$hashed_password')";
            if (mysqli_query($con, $insert_query)) {
                $message = "Admin account created successfully! You can now login.";
                $success = true;
            } else {
                $message = "Error creating admin: " . mysqli_error($con);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Reset Admin Password</title>
    <?php include '../includes/links.php'; ?>
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .reset-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
            padding: 40px;
            max-width: 500px;
            width: 100%;
        }
        .reset-card h2 {
            color: #667eea;
            margin-bottom: 10px;
            font-weight: 700;
        }
        .reset-card p {
            color: #666;
            margin-bottom: 30px;
        }
        .form-control {
            border-radius: 8px;
            border: 2px solid #e0e0e0;
            padding: 12px 15px;
            transition: all 0.3s;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .btn-reset {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            color: white;
            transition: all 0.3s;
        }
        .btn-reset:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            color: white;
        }
        .btn-login {
            background: #27ae60;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            color: white;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }
        .btn-login:hover {
            background: #229954;
            color: white;
            text-decoration: none;
            transform: translateY(-2px);
        }
        .alert {
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .icon-wrapper {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        .icon-wrapper i {
            color: white;
            font-size: 32px;
        }
        .success-icon {
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%) !important;
        }
    </style>
</head>
<body>
    <div class="reset-card">
        <?php if ($success): ?>
            <div class="icon-wrapper success-icon">
                <i class="fas fa-check"></i>
            </div>
            <h2 class="text-center">Success!</h2>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $message; ?>
            </div>
            <div class="text-center mt-4">
                <a href="admin_login.php" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i> Go to Login Page
                </a>
            </div>
        <?php else: ?>
            <div class="icon-wrapper">
                <i class="fas fa-key"></i>
            </div>
            <h2 class="text-center">Reset Admin Password</h2>
            <p class="text-center">Create or update your admin credentials</p>
            
            <?php if (!empty($message)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label><i class="fas fa-user"></i> Admin Username</label>
                    <input type="text" name="username" class="form-control" 
                           placeholder="Enter admin username" value="admin" required>
                    <small class="form-text text-muted">Default: admin</small>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> New Password</label>
                    <input type="password" name="new_password" class="form-control" 
                           placeholder="Enter new password" required>
                    <small class="form-text text-muted">At least 6 characters</small>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Confirm Password</label>
                    <input type="password" name="confirm_password" class="form-control" 
                           placeholder="Confirm new password" required>
                </div>
                
                <div class="form-group mb-0">
                    <button type="submit" name="reset" class="btn btn-reset btn-block">
                        <i class="fas fa-sync-alt"></i> Reset Password
                    </button>
                </div>
            </form>
            
            <div class="text-center mt-3">
                <a href="admin_login.php" style="color: #667eea;">
                    <i class="fas fa-arrow-left"></i> Back to Login
                </a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
