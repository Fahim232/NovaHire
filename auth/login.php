<?php
    session_start();
    include 'admin/dbcon.php';

    $error_msg = '';

    if (isset($_POST['submit'])) {
        $role = $_POST['role'];
        $email = mysqli_real_escape_string($con, $_POST['email']);
        $pass = $_POST['password'];

        if ($role === 'seeker') {
            $query = mysqli_query($con, "SELECT * FROM user_info WHERE email='$email'");
            if (mysqli_num_rows($query) > 0) {
                $row = mysqli_fetch_assoc($query);
                $dbpass = $row['password'];
                $passDecrypt = password_verify($pass, $dbpass);
                if ($passDecrypt) {
                    $_SESSION['username'] = $row['username'];
                    $_SESSION['uphone'] = $row['phone'];
                    $_SESSION['email'] = $row['email'];
                    $_SESSION['id'] = $row['id'];
                    $redirect = isset($_GET['redirect']) ? $_GET['redirect'] : 'seeker_dashboard.php';
                    echo "<script>alert('Login Successful!'); window.location.href='$redirect';</script>";
                    exit;
                } else {
                    $error_msg = 'Incorrect Password!';
                }
            } else {
                $error_msg = 'User does not exist!';
            }
        } elseif ($role === 'recruiter') {
            $query = mysqli_query($con, "SELECT * FROM companies WHERE company_email='$email' AND status='active'");
            if (mysqli_num_rows($query) > 0) {
                $row = mysqli_fetch_assoc($query);
                $dbpass = $row['password'];
                $passDecrypt = password_verify($pass, $dbpass);
                if ($passDecrypt) {
                    $_SESSION['company_id'] = $row['id'];
                    $_SESSION['company_name'] = $row['company_name'];
                    $_SESSION['company_email'] = $row['company_email'];
                    $_SESSION['company_logo'] = $row['logo'];
                    $_SESSION['user_type'] = 'company';
                    echo "<script>alert('Login Successful!'); window.location.href='company/index.php';</script>";
                    exit;
                } else {
                    $error_msg = 'Incorrect Password!';
                }
            } else {
                $error_msg = 'Company not found or inactive!';
            }
        } elseif ($role === 'admin') {
            $query = mysqli_query($con, "SELECT * FROM admin_login WHERE admin_user_name='$email'");
            if (mysqli_num_rows($query) > 0) {
                $row = mysqli_fetch_assoc($query);
                $dbpass = $row['admin_password'];
                $passDecrypt = password_verify($pass, $dbpass);
                if ($passDecrypt || $pass === $dbpass) {
                    $_SESSION['admin_username'] = $row['admin_user_name'];
                    echo "<script>alert('Login Successful!'); window.location.href='admin/index.php';</script>";
                    exit;
                } else {
                    $error_msg = 'Incorrect Password!';
                }
            } else {
                $error_msg = 'Admin not found!';
            }
        } else {
            $error_msg = 'Please select a role!';
        }
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Log In | NovaHire</title>
    <?php include './links.php'; ?>
    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: auto;
            padding: 40px 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .login-container {
            width: 100%;
            max-width: 900px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 30px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.15);
            overflow: hidden;
            display: flex;
            position: relative;
        }

        .login-visual {
            width: 45%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 40px;
            text-align: center;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .login-visual::before {
            content: '';
            position: absolute;
            width: 300px;
            height: 300px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            top: -50px;
            left: -50px;
        }

        .login-visual::after {
            content: '';
            position: absolute;
            width: 200px;
            height: 200px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            bottom: -30px;
            right: -30px;
        }

        .login-visual h2 {
            font-size: 2.2rem;
            font-weight: 800;
            margin-bottom: 15px;
            z-index: 2;
        }

        .login-visual p {
            font-size: 1rem;
            opacity: 0.9;
            z-index: 2;
            line-height: 1.6;
        }

        .role-icons {
            display: flex;
            gap: 20px;
            margin-top: 30px;
            z-index: 2;
        }

        .role-icon-item {
            text-align: center;
        }

        .role-icon-item i {
            font-size: 2rem;
            margin-bottom: 5px;
            display: block;
            opacity: 0.8;
        }

        .role-icon-item span {
            font-size: 0.8rem;
            opacity: 0.7;
        }

        .login-form-side {
            width: 55%;
            padding: 40px 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: white;
        }

        .form-title {
            color: #2d3436;
            font-weight: 800;
            font-size: 1.8rem;
            margin-bottom: 5px;
        }

        .form-subtitle {
            color: #b2bec3;
            margin-bottom: 25px;
            font-size: 0.9rem;
        }

        .role-selector {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
        }

        .role-btn {
            flex: 1;
            padding: 12px 10px;
            border: 2px solid #f1f2f6;
            border-radius: 12px;
            background: white;
            cursor: pointer;
            text-align: center;
            transition: all 0.3s;
            position: relative;
        }

        .role-btn:hover {
            border-color: #667eea;
            background: #f8f9ff;
        }

        .role-btn.active {
            border-color: #667eea;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .role-btn.active i,
        .role-btn.active span {
            color: white;
        }

        .role-btn i {
            font-size: 1.3rem;
            display: block;
            margin-bottom: 4px;
            color: #667eea;
            transition: color 0.3s;
        }

        .role-btn span {
            font-size: 0.75rem;
            font-weight: 600;
            color: #636e72;
            transition: color 0.3s;
        }

        .role-btn input[type="radio"] {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .input-group-modern {
            position: relative;
            margin-bottom: 20px;
        }

        .input-group-modern i {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: #b2bec3;
            transition: color 0.3s;
        }

        .form-control-modern {
            width: 100%;
            padding: 14px 14px 14px 50px;
            border: 2px solid #f1f2f6;
            border-radius: 15px;
            font-size: 0.95rem;
            color: #2d3436;
            font-weight: 500;
            transition: all 0.3s;
            background: #fdfdfd;
        }

        .form-control-modern:focus {
            border-color: #667eea;
            background: white;
            outline: none;
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.1);
        }

        .btn-modern {
            background: linear-gradient(to right, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 14px;
            border-radius: 15px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            font-size: 0.85rem;
            transition: transform 0.3s, box-shadow 0.3s;
            width: 100%;
            cursor: pointer;
        }

        .btn-modern:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(118, 75, 162, 0.3);
            color: white;
        }

        .login-link {
            text-align: center;
            margin-top: 20px;
            font-size: 0.85rem;
            color: #636e72;
        }

        .login-link a {
            color: #667eea;
            font-weight: 700;
            text-decoration: none;
        }

        .alert-modern {
            padding: 12px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-modern.danger {
            background: #fff5f5;
            color: #c53030;
            border: 1px solid #fed7d7;
        }

        @media (max-width: 768px) {
            .login-container {
                flex-direction: column;
                max-width: 95%;
            }
            .login-visual {
                width: 100%;
                padding: 30px;
                min-height: auto;
            }
            .login-form-side {
                width: 100%;
                padding: 30px;
            }
            .role-selector {
                flex-direction: column;
            }
        }
    </style>
</head>

<body>
    <div class="login-container">
        <!-- Visual Side -->
        <div class="login-visual">
            <h2>Welcome to<br>NovaHire</h2>
            <p>Find your dream job, connect with top companies, and grow your career.</p>
            <div class="role-icons">
                <div class="role-icon-item">
                    <i class="fas fa-user-tie"></i>
                    <span>Job Seeker</span>
                </div>
                <div class="role-icon-item">
                    <i class="fas fa-building"></i>
                    <span>Recruiter</span>
                </div>
                <div class="role-icon-item">
                    <i class="fas fa-user-shield"></i>
                    <span>Admin</span>
                </div>
            </div>
        </div>

        <!-- Form Side -->
        <div class="login-form-side">
            <h1 class="form-title">Log In</h1>
            <p class="form-subtitle">Select your role and enter your credentials.</p>

            <?php if (!empty($error_msg)): ?>
                <div class="alert-modern danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error_msg; ?>
                </div>
            <?php endif; ?>

            <form action="login.php<?php echo isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : ''; ?>" method="POST">
                <!-- Role Selector -->
                <div class="role-selector">
                    <label class="role-btn active" id="role-seeker" onclick="selectRole('seeker')">
                        <input type="radio" name="role" value="seeker" checked>
                        <i class="fas fa-user-tie"></i>
                        <span>Job Seeker</span>
                    </label>
                    <label class="role-btn" id="role-recruiter" onclick="selectRole('recruiter')">
                        <input type="radio" name="role" value="recruiter">
                        <i class="fas fa-building"></i>
                        <span>Recruiter</span>
                    </label>
                    <label class="role-btn" id="role-admin" onclick="selectRole('admin')">
                        <input type="radio" name="role" value="admin">
                        <i class="fas fa-user-shield"></i>
                        <span>Admin</span>
                    </label>
                </div>

                <!-- Email/Username field -->
                <div class="input-group-modern">
                    <input name="email" id="emailField" type="email" placeholder="Email Address" class="form-control-modern" required>
                    <i class="fa fa-envelope" id="emailIcon"></i>
                </div>

                <div class="input-group-modern">
                    <input name="password" type="password" placeholder="Password" class="form-control-modern" required>
                    <i class="fa fa-lock"></i>
                </div>

                <div style="text-align: right; margin: 10px 0;">
                    <a href="forgot_password.php?type=user" style="color: #667eea; text-decoration: none; font-size: 0.85rem; font-weight: 600;">
                        <i class="fas fa-key mr-1"></i>Forgot Password?
                    </a>
                </div>

                <button type="submit" name="submit" class="btn-modern">
                    Sign In <i class="fas fa-arrow-right ml-2"></i>
                </button>

                <div class="login-link" id="registerLink">
                    Don't have an account? <a href="registration.php">Create Account</a>
                </div>

            </form>
        </div>
    </div>

    <script>
    function selectRole(role) {
        document.querySelectorAll('.role-btn').forEach(function(btn) {
            btn.classList.remove('active');
        });
        document.getElementById('role-' + role).classList.add('active');

        var emailField = document.getElementById('emailField');
        var emailIcon = document.getElementById('emailIcon');
        var registerLink = document.getElementById('registerLink');

        if (role === 'admin') {
            emailField.placeholder = 'Admin Username';
            emailField.type = 'text';
            emailIcon.className = 'fa fa-user';
            registerLink.innerHTML = 'Create admin account? <a href="admin/register_admin.php">Create Account</a>';
            registerLink.style.display = 'block';
        } else if (role === 'recruiter') {
            emailField.placeholder = 'Company Email';
            emailField.type = 'email';
            emailIcon.className = 'fa fa-envelope';
            registerLink.innerHTML = 'Register your company? <a href="company_registration.php">Create Account</a>';
            registerLink.style.display = 'block';
        } else {
            emailField.placeholder = 'Email Address';
            emailField.type = 'email';
            emailIcon.className = 'fa fa-envelope';
            registerLink.innerHTML = 'Don\'t have an account? <a href="registration.php">Create Account</a>';
            registerLink.style.display = 'block';
        }
    }
    </script>
</body>
</html>
