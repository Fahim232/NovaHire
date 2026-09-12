<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../admin/dbcon.php';

$success_msg = $error_msg = '';
$code_verified = false;

if (!isset($_SESSION['reset_email'])) {
    header('Location: forgot_password.php');
    exit;
}

$email = $_SESSION['reset_email'];
$user_type = $_SESSION['reset_user_type'];

if (isset($_POST['verify_code'])) {
    $code = trim(mysqli_real_escape_string($con, $_POST['reset_code']));
    $chk = "SELECT * FROM password_reset_codes WHERE email='$email' AND user_type='$user_type' AND reset_code='$code' AND is_used=0";
    $res = mysqli_query($con, $chk);
    
    if (!$res) {
        $error_msg = "Database error: " . mysqli_error($con);
    } elseif (mysqli_num_rows($res) > 0) {
        $row = mysqli_fetch_assoc($res);
        if (time() > strtotime($row['expires_at'])) {
            $error_msg = "Code has expired. Please request a new code.";
        } else {
            $code_verified = true;
            $_SESSION['code_verified'] = true;
            $success_msg = "Code verified! Please enter your new password.";
        }
    } else {
        $dbg = "SELECT reset_code, is_used FROM password_reset_codes WHERE email='$email' AND user_type='$user_type' ORDER BY created_at DESC LIMIT 1";
        $dbgRes = mysqli_query($con, $dbg);
        if (mysqli_num_rows($dbgRes) > 0) {
            $dbgRow = mysqli_fetch_assoc($dbgRes);
            $error_msg = $dbgRow['reset_code'] !== $code ? "Invalid code entered."
                : ($dbgRow['is_used'] == 1 ? "This code has already been used." : "Code verification failed.");
        } else {
            $error_msg = "No reset code found. Please request a new code.";
        }
    }
}

if (isset($_POST['reset_password'])) {
    if (!isset($_SESSION['code_verified'])) {
        $error_msg = "Please verify your code first.";
    } else {
        $new_pw = $_POST['new_password'];
        $confirm_pw = $_POST['confirm_password'];
        
        if ($new_pw !== $confirm_pw) {
            $error_msg = "Passwords do not match!";
        } elseif (strlen($new_pw) < 6) {
            $error_msg = "Password must be at least 6 characters long.";
        } else {
            $hashed = password_hash($new_pw, PASSWORD_DEFAULT);
            $table = $user_type === 'user' ? 'user_info' : 'companies';
            $col = $user_type === 'user' ? 'email' : 'company_email';
            
            if (mysqli_query($con, "UPDATE {$table} SET password='$hashed' WHERE {$col}='$email'")) {
                mysqli_query($con, "UPDATE password_reset_codes SET is_used=1 WHERE email='$email' AND user_type='$user_type'");
                unset($_SESSION['reset_email'], $_SESSION['reset_user_type'], $_SESSION['reset_code_display'], $_SESSION['code_verified']);
                echo "<script>alert('Password reset successful!');window.location.href='" . BASE_URL . "/auth/login.php';</script>";
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
        body{min-height:100vh;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);display:flex;align-items:center;justify-content:center;padding:40px 20px}
        .reset-container{width:100%;max-width:550px;background:rgba(255,255,255,.98);backdrop-filter:blur(20px);border-radius:30px;box-shadow:0 25px 50px rgba(0,0,0,.25);overflow:hidden}
        .reset-header{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;padding:40px;text-align:center}
        .reset-header i{font-size:3.5rem;margin-bottom:15px;opacity:.9}
        .reset-header h2{margin:0 0 10px;font-size:1.8rem;font-weight:700}
        .reset-header p{margin:0;opacity:.9;font-size:.95rem}
        .reset-body{padding:40px}
        .form-group label{font-weight:600;color:#333;margin-bottom:8px}
        .form-control{border-radius:15px;padding:15px 20px;border:2px solid #e0e0e0;font-size:1rem;transition:all .3s}
        .form-control:focus{border-color:#667eea;box-shadow:0 0 0 .2rem rgba(102,126,234,.25)}
        .btn-primary{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);border:none;border-radius:15px;padding:15px;font-size:1.1rem;font-weight:700;color:#fff;width:100%;transition:all .3s}
        .btn-primary:hover{transform:translateY(-2px);box-shadow:0 10px 25px rgba(102,126,234,.4)}
        .alert{border-radius:15px;padding:15px 20px;margin-bottom:20px}
        .password-strength{margin-top:10px;font-size:.85rem}
        .strength-bar{height:5px;border-radius:3px;background:#e0e0e0;margin-top:5px;overflow:hidden}
        .strength-fill{height:100%;transition:all .3s}
        .back-link{text-align:center;margin-top:25px}
        .back-link a{color:#667eea;text-decoration:none;font-weight:600}
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="reset-header">
            <i class="fas fa-shield-alt"></i>
            <h2>Reset Password</h2>
            <p>Email: <strong><?= $email ?></strong></p>
        </div>
        <div class="reset-body">
            <?php if ($error_msg): ?>
                <div class="alert alert-danger"><i class="fas fa-exclamation-circle mr-2"></i><?= $error_msg ?></div>
            <?php endif; ?>
            <?php if ($success_msg): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle mr-2"></i><?= $success_msg ?></div>
            <?php endif; ?>
            <?php if (!$code_verified && !isset($_SESSION['code_verified'])): ?>
                <form method="POST" action="">
                    <div class="form-group">
                        <label><i class="fas fa-key mr-2"></i>Enter 6-Digit Reset Code</label>
                        <input type="text" name="reset_code" class="form-control text-center" placeholder="000000" maxlength="6" pattern="\d{6}" style="font-size:1.5rem;letter-spacing:5px" required>
                        <small class="text-muted"><i class="fas fa-info-circle mr-1"></i>Enter the 6-digit code from previous page</small>
                    </div>
                    <button type="submit" name="verify_code" class="btn btn-primary"><i class="fas fa-check mr-2"></i>Verify Code</button>
                </form>
            <?php else: ?>
                <form method="POST" action="">
                    <div class="form-group">
                        <label><i class="fas fa-lock mr-2"></i>New Password</label>
                        <input type="password" name="new_password" id="newPassword" class="form-control" placeholder="Enter new password" minlength="6" required>
                        <div class="password-strength">
                            <small class="text-muted">Password strength:</small>
                            <div class="strength-bar"><div class="strength-fill" id="strengthBar"></div></div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-lock mr-2"></i>Confirm Password</label>
                        <input type="password" name="confirm_password" id="confirmPassword" class="form-control" placeholder="Confirm new password" minlength="6" required>
                    </div>
                    <button type="submit" name="reset_password" class="btn btn-primary"><i class="fas fa-check-circle mr-2"></i>Reset Password</button>
                </form>
            <?php endif; ?>
            <div class="back-link"><a href="forgot_password.php"><i class="fas fa-arrow-left mr-2"></i>Back</a></div>
        </div>
    </div>
    <script>
        const pw=document.getElementById('newPassword'),bar=document.getElementById('strengthBar');
        if(pw){pw.addEventListener('input',function(){let s=0;const p=this.value;if(p.length>=6)s+=25;if(p.length>=10)s+=25;if(/[a-z]/.test(p)&&/[A-Z]/.test(p))s+=25;if(/\d/.test(p))s+=25;bar.style.width=s+'%';bar.style.background=s<50?'#dc3545':s<75?'#ffc107':'#28a745'});}
        const cp=document.getElementById('confirmPassword');
        if(cp){cp.addEventListener('input',function(){this.setCustomValidity(this.value!==pw.value?'Passwords do not match':'')});}
    </script>
</body>
</html>
