<?php
session_start();
include 'admin/dbcon.php';

$success_msg = '';
$error_msg = '';
$code_sent = false;
$user_type_param = isset($_GET['type']) ? $_GET['type'] : ''; // Get type from URL parameter

if (isset($_POST['send_code'])) {
    $email = mysqli_real_escape_string($con, $_POST['email']);
    $user_type = mysqli_real_escape_string($con, $_POST['user_type']);
    
    // Check if email exists
    if ($user_type === 'user') {
        $check_query = "SELECT * FROM user_info WHERE email = '$email'";
    } else {
        $check_query = "SELECT * FROM companies WHERE company_email = '$email'";
    }
    
    $result = mysqli_query($con, $check_query);
    
    if (mysqli_num_rows($result) > 0) {
        // Generate 6-digit code
        $reset_code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // Set expiration time (1 hour from now)
        $current_time = time();
        $expires_at = date('Y-m-d H:i:s', $current_time + 3600); // 3600 seconds = 1 hour
        
        // Delete old codes for this email
        $delete_old = "DELETE FROM password_reset_codes WHERE email = '$email' AND user_type = '$user_type'";
        mysqli_query($con, $delete_old);
        
        // Insert new code
        $insert_code = "INSERT INTO password_reset_codes (email, user_type, reset_code, expires_at) 
                       VALUES ('$email', '$user_type', '$reset_code', '$expires_at')";
        
        if (mysqli_query($con, $insert_code)) {
            $code_sent = true;
            $_SESSION['reset_email'] = $email;
            $_SESSION['reset_user_type'] = $user_type;
            $_SESSION['reset_code_display'] = $reset_code; // For display only
            $success_msg = "Reset code generated successfully! Code: <strong>$reset_code</strong> (Valid for 1 hour)";
        } else {
            $error_msg = "Error generating reset code. Please try again.";
        }
    } else {
        $error_msg = "Email not found in our system.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Forgot Password | NovaHire</title>
    <?php include './links.php'; ?>
    <style>
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }

        .forgot-password-container {
            width: 100%;
            max-width: 550px;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            border-radius: 30px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.25);
            overflow: hidden;
        }

        .forgot-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            text-align: center;
        }

        .forgot-header i {
            font-size: 3.5rem;
            margin-bottom: 15px;
            opacity: 0.9;
        }

        .forgot-header h2 {
            margin: 0 0 10px 0;
            font-size: 1.8rem;
            font-weight: 700;
        }

        .forgot-header p {
            margin: 0;
            opacity: 0.9;
            font-size: 0.95rem;
        }

        .forgot-body {
            padding: 40px;
        }

        .form-group label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }

        .form-control {
            border-radius: 15px;
            padding: 1px 20px;
            border: 2px solid #e0e0e0;
            font-size: 1rem;
            transition: all 0.3s;
        }

        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .btn-send-code {
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

        .btn-send-code:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }

        .code-display {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 25px;
            border-radius: 15px;
            text-align: center;
            margin: 20px 0;
            box-shadow: 0 10px 25px rgba(16, 185, 129, 0.3);
        }

        .code-display .code {
            font-size: 2.5rem;
            font-weight: 700;
            letter-spacing: 8px;
            margin: 10px 0;
        }

        .back-link {
            text-align: center;
            margin-top: 25px;
        }

        .back-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }

        .back-link a:hover {
            color: #764ba2;
            text-decoration: underline;
        }

        .alert {
            border-radius: 15px;
            padding: 15px 20px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="forgot-password-container">
        <div class="forgot-header">
            <i class="fas fa-key"></i>
            <h2>Forgot Password?</h2>
            <p>Enter your email to receive a reset code</p>
        </div>

        <div class="forgot-body">
            <?php if ($error_msg): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle mr-2"></i><?php echo $error_msg; ?>
                </div>
            <?php endif; ?>

            <?php if ($code_sent): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle mr-2"></i><?php echo $success_msg; ?>
                </div>

                <div class="code-display">
                    <p class="mb-1"><i class="fas fa-lock mr-2"></i>Your Reset Code:</p>
                    <div class="code"><?php echo $_SESSION['reset_code_display']; ?></div>
                    <small><i class="fas fa-clock mr-1"></i>Valid for 15 minutes</small>
                </div>

                <a href="reset_password.php" class="btn btn-send-code">
                    <i class="fas fa-arrow-right mr-2"></i>Enter Code & Reset Password
                </a>
            <?php else: ?>
                <form method="POST" action="">
                    <div class="form-group">
                        <label><i class="fas fa-user-tag mr-2"></i>I am a:</label>
                        <?php if ($user_type_param === 'company'): ?>
                            <!-- Company only - hidden field -->
                            <input type="hidden" name="user_type" value="company">
                            <input type="text" class="form-control" value="Company" disabled>
                            <small style="color: #666; display: block; margin-top: 5px;">Company password recovery</small>
                        <?php elseif ($user_type_param === 'user'): ?>
                            <!-- Job Seeker only - hidden field -->
                            <input type="hidden" name="user_type" value="user">
                            <input type="text" class="form-control" value="Job Seeker" disabled>
                            <small style="color: #666; display: block; margin-top: 5px;">Job seeker password recovery</small>
                        <?php else: ?>
                            <!-- Show both options -->
                            <select name="user_type" class="form-control" required>
                                <option value="user">Job Seeker</option>
                                <option value="company">Company</option>
                            </select>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-envelope mr-2"></i>Email Address</label>
                        <input type="email" name="email" class="form-control" placeholder="Enter your email" required>
                    </div>

                    <button type="submit" name="send_code" class="btn btn-send-code">
                        <i class="fas fa-paper-plane mr-2"></i>Send Reset Code
                    </button>
                </form>
            <?php endif; ?>

            <div class="back-link">
                <a href="login.php"><i class="fas fa-arrow-left mr-2"></i>Back to Login</a>
            </div>
        </div>
    </div>
</body>
</html>
