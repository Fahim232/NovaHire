<?php
// Core setup: session, DB, BASE_URL, helpers
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../admin/dbcon.php';

$success_msg = '';
$error_msg = '';
$code_verified = false;

if (!isset($_SESSION['reset_email'])) {
    header('Location: forgot_password.php');
    exit;
}

$email = $_SESSION['reset_email'];
$user_type = $_SESSION['reset_user_type'];

if (isset($_POST['verify_code'])) {
    $entered_code = trim(mysqli_real_escape_string($con, $_POST['reset_code']));
    
    // Get the reset code record (without timestamp check in SQL)
    $check_code = "SELECT * FROM password_reset_codes 
                   WHERE email = '$email' 
                   AND user_type = '$user_type' 
                   AND reset_code = '$entered_code' 
                   AND is_used = 0";
    
    $result = mysqli_query($con, $check_code);
    
    if (!$result) {
        // Database error
        $error_msg = "Database error: " . mysqli_error($con) . ". Please make sure you've imported password_reset.sql";
    } elseif (mysqli_num_rows($result) > 0) {
        $code_row = mysqli_fetch_assoc($result);
        $current_time = time();
        $expires_time = strtotime($code_row['expires_at']);
        
        // Check if code has expired (PHP-side check)
        if ($current_time > $expires_time) {
            $error_msg = "Code has expired. Please request a new code.";
        } else {
            $code_verified = true;
            $_SESSION['code_verified'] = true;
            $success_msg = "Code verified! Please enter your new password.";
        }
    } else {
        // Debug: Check if code exists at all
        $debug_query = "SELECT reset_code, expires_at, is_used 
                       FROM password_reset_codes 
                       WHERE email = '$email' AND user_type = '$user_type' 
                       ORDER BY created_at DESC LIMIT 1";
        $debug_result = mysqli_query($con, $debug_query);
        
        if (mysqli_num_rows($debug_result) > 0) {
            $debug_row = mysqli_fetch_assoc($debug_result);
            if ($debug_row['reset_code'] !== $entered_code) {
                $error_msg = "Invalid code entered. Please check and try again.";
            } elseif ($debug_row['is_used'] == 1) {
                $error_msg = "This code has already been used. Please request a new code.";
            } else {
                $error_msg = "Code verification failed. Please try again.";
            }
        } else {
            $error_msg = "No reset code found. Please request a new code.";
        }
    }
}

if (isset($_POST['reset_password'])) {
    if (!isset($_SESSION['code_verified'])) {
        $error_msg = "Please verify your code first.";
    } else {
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if ($new_password !== $confirm_password) {
            $error_msg = "Passwords do not match!";
        } elseif (strlen($new_password) < 6) {
            $error_msg = "Password must be at least 6 characters long.";
        } else {
            // Hash the new password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            // Update password
            if ($user_type === 'user') {
                $update_query = "UPDATE user_info SET password = '$hashed_password' WHERE email = '$email'";
            } else {
                $update_query = "UPDATE companies SET password = '$hashed_password' WHERE company_email = '$email'";
            }
            
            if (mysqli_query($con, $update_query)) {
                // Mark code as used
                $mark_used = "UPDATE password_reset_codes SET is_used = 1 WHERE email = '$email' AND user_type = '$user_type'";
                mysqli_query($con, $mark_used);
                
                // Clear session
                unset($_SESSION['reset_email']);
                unset($_SESSION['reset_user_type']);
                unset($_SESSION['reset_code_display']);
                unset($_SESSION['code_verified']);
                
                // Redirect to login
                $redirect_page = ($user_type === 'user') ? BASE_URL . '/auth/login.php' : BASE_URL . '/auth/login.php';
                echo "<script>
                        alert('Password reset successful! Please login with your new password.');
                        window.location.href = '$redirect_page';
                      </script>";
                exit;
            } else {
                $error_msg = "Error updating password. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Reset Password | NovaHire</title>
    <?php include '../includes/links.php'; ?>
    <style>
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }

        .reset-container {
            width: 100%;
            max-width: 550px;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            border-radius: 30px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.25);
            overflow: hidden;
        }

        .reset-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            text-align: center;
        }

        .reset-header i {
            font-size: 3.5rem;
            margin-bottom: 15px;
            opacity: 0.9;
        }

        .reset-header h2 {
            margin: 0 0 10px 0;
            font-size: 1.8rem;
            font-weight: 700;
        }

        .reset-header p {
            margin: 0;
            opacity: 0.9;
            font-size: 0.95rem;
        }

        .reset-body {
            padding: 40px;
        }

        .form-group label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }

        .form-control {
            border-radius: 15px;
            padding: 15px 20px;
            border: 2px solid #e0e0e0;
            font-size: 1rem;
            transition: all 0.3s;
        }

        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 15px;
            padding: 15px;
            font-size: 1.1rem;
            font-weight: 700;
            color: white;
            width: 100%;
            transition: all 0.3s;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }

        .alert {
            border-radius: 15px;
            padding: 15px 20px;
            margin-bottom: 20px;
        }

        .password-strength {
            margin-top: 10px;
            font-size: 0.85rem;
        }

        .strength-bar {
            height: 5px;
            border-radius: 3px;
            background: #e0e0e0;
            margin-top: 5px;
            overflow: hidden;
        }

        .strength-fill {
            height: 100%;
            transition: all 0.3s;
        }

        .back-link {
            text-align: center;
            margin-top: 25px;
        }

        .back-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="reset-header">
            <i class="fas fa-shield-alt"></i>
            <h2>Reset Password</h2>
            <p>Email: <strong><?php echo $email; ?></strong></p>
        </div>

        <div class="reset-body">
            <?php if ($error_msg): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle mr-2"></i><?php echo $error_msg; ?>
                </div>
            <?php endif; ?>

            <?php if ($success_msg): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle mr-2"></i><?php echo $success_msg; ?>
                </div>
            <?php endif; ?>

            <?php if (!$code_verified && !isset($_SESSION['code_verified'])): ?>
                <!-- Step 1: Verify Code -->
                <form method="POST" action="">
                    <div class="form-group">
                        <label><i class="fas fa-key mr-2"></i>Enter 6-Digit Reset Code</label>
                        <input type="text" name="reset_code" class="form-control text-center" 
                               placeholder="000000" maxlength="6" pattern="\d{6}" 
                               style="font-size: 1.5rem; letter-spacing: 5px;" required>
                        <small class="text-muted">
                            <i class="fas fa-info-circle mr-1"></i>Enter the 6-digit code from previous page
                        </small>
                    </div>

                    <button type="submit" name="verify_code" class="btn btn-primary">
                        <i class="fas fa-check mr-2"></i>Verify Code
                    </button>
                </form>
            <?php else: ?>
                <!-- Step 2: Reset Password -->
                <form method="POST" action="">
                    <div class="form-group">
                        <label><i class="fas fa-lock mr-2"></i>New Password</label>
                        <input type="password" name="new_password" id="newPassword" class="form-control" 
                               placeholder="Enter new password" minlength="6" required>
                        <div class="password-strength">
                            <small class="text-muted">Password strength:</small>
                            <div class="strength-bar">
                                <div class="strength-fill" id="strengthBar"></div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-lock mr-2"></i>Confirm Password</label>
                        <input type="password" name="confirm_password" id="confirmPassword" class="form-control" 
                               placeholder="Confirm new password" minlength="6" required>
                    </div>

                    <button type="submit" name="reset_password" class="btn btn-primary">
                        <i class="fas fa-check-circle mr-2"></i>Reset Password
                    </button>
                </form>
            <?php endif; ?>

            <div class="back-link">
                <a href="forgot_password.php"><i class="fas fa-arrow-left mr-2"></i>Back</a>
            </div>
        </div>
    </div>

    <script>
        // Password strength indicator
        const passwordInput = document.getElementById('newPassword');
        const strengthBar = document.getElementById('strengthBar');

        if (passwordInput) {
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                let strength = 0;

                if (password.length >= 6) strength += 25;
                if (password.length >= 10) strength += 25;
                if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength += 25;
                if (/\d/.test(password)) strength += 25;

                strengthBar.style.width = strength + '%';
                
                if (strength < 50) {
                    strengthBar.style.background = '#dc3545';
                } else if (strength < 75) {
                    strengthBar.style.background = '#ffc107';
                } else {
                    strengthBar.style.background = '#28a745';
                }
            });
        }

        // Confirm password match
        const confirmPassword = document.getElementById('confirmPassword');
        if (confirmPassword) {
            confirmPassword.addEventListener('input', function() {
                if (this.value !== passwordInput.value) {
                    this.setCustomValidity('Passwords do not match');
                } else {
                    this.setCustomValidity('');
                }
            });
        }
    </script>
</body>
</html>
