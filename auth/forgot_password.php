<?php
// Core setup: session, DB, BASE_URL, helpers
require_once __DIR__ . '/../includes/bootstrap.php';
/**
 * Password Recovery Request Portal (Forgot Password)
 * 
 * Generates a 6-digit password reset verification code for users or companies,
 * invalidating previous tokens and storing the active verification token in database.
 */

// Initialize session if not active
if (session_status() === PHP_SESSION_NONE) {

}

// Include database connection
require_once __DIR__ . '/../admin/dbcon.php';

$success_msg     = '';
$error_msg       = '';
$code_sent       = false;
$user_type_param = isset($_GET['type']) ? trim($_GET['type']) : '';

// Process reset code request
if (isset($_POST['send_code'])) {
    $email     = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $user_type = trim($_POST['user_type'] ?? '');
    
    // 1. Verify existence of target account using prepared statement
    if ($user_type === 'user') {
        $check_stmt = mysqli_prepare($con, "SELECT id FROM user_info WHERE email = ?");
    } else {
        $check_stmt = mysqli_prepare($con, "SELECT id FROM companies WHERE company_email = ?");
    }
    mysqli_stmt_bind_param($check_stmt, "s", $email);
    mysqli_stmt_execute($check_stmt);
    $res = mysqli_stmt_get_result($check_stmt);
    $account_exists = mysqli_num_rows($res) > 0;
    mysqli_stmt_close($check_stmt);
    
    if ($account_exists) {
        // Generate secure 6-digit numerical pin
        $reset_code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // Calculate expiration timestamp (valid for 1 hour)
        $expires_at = date('Y-m-d H:i:s', time() + 3600);
        
        // 2. Delete existing reset codes for this account using prepared statement
        $del_stmt = mysqli_prepare($con, "DELETE FROM password_reset_codes WHERE email = ? AND user_type = ?");
        mysqli_stmt_bind_param($del_stmt, "ss", $email, $user_type);
        mysqli_stmt_execute($del_stmt);
        mysqli_stmt_close($del_stmt);
        
        // 3. Insert newly generated reset code token
        $ins_stmt = mysqli_prepare($con, "INSERT INTO password_reset_codes (email, user_type, reset_code, expires_at) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($ins_stmt, "ssss", $email, $user_type, $reset_code, $expires_at);
        
        if (mysqli_stmt_execute($ins_stmt)) {
            $code_sent = true;
            $_SESSION['reset_email']        = $email;
            $_SESSION['reset_user_type']    = $user_type;
            $_SESSION['reset_code_display'] = $reset_code;
            $success_msg = "Reset code sent to your email! Code: <strong>$reset_code</strong> (Valid for 1 hour)";

            // Send password reset email
            require_once __DIR__ . '/../includes/mail.php';
            send_password_reset_email($email, $reset_code, $user_type);
        } else {
            $error_msg = "Error generating reset code. Please try again.";
        }
        mysqli_stmt_close($ins_stmt);
    } else {
        $error_msg = "Email not found in our system.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Forgot Password | NovaHire</title>
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
            background: linear-gradient(135deg, #059669 0%, #059669 100%);
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
