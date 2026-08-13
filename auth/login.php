<?php
// Core setup: session, DB, BASE_URL, helpers
require_once __DIR__ . '/../includes/bootstrap.php';
/**
 * User & Role Authentication Portal (Login)
 * 
 * Handles multi-role authentication (Job Seeker, Employer/Recruiter, and System Admin).
 * Uses prepared statement queries to verify user credentials and password hashes, initializing session variables.
 */

// Initialize session if not active
if (session_status() === PHP_SESSION_NONE) {

}

// Include database connection handle
require_once __DIR__ . '/../admin/dbcon.php';

$error_msg = '';

// Handle form submission
if (isset($_POST['submit'])) {
    $role  = trim($_POST['role'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

    // 1. Authenticate Job Seeker Role
    if ($role === 'seeker') {
        $stmt = mysqli_prepare($con, "SELECT id, username, phone, email, password FROM user_info WHERE email = ?");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($result) > 0) {
            $row = mysqli_fetch_assoc($result);
            $dbpass = $row['password'];
            
            // Verify BCrypt hashed password
            if (password_verify($pass, $dbpass)) {
                $_SESSION['username'] = $row['username'];
                $_SESSION['uphone']   = $row['phone'];
                $_SESSION['email']    = $row['email'];
                $_SESSION['id']       = $row['id'];
                
                $redirect = isset($_GET['redirect']) ? filter_var($_GET['redirect'], FILTER_SANITIZE_URL) : BASE_URL . '/seeker/seeker_dashboard.php';
                // Normalise so redirects never resolve against the /auth/ folder:
                // absolute (leading / or http) → keep as-is; relative → assume a legacy seeker page (now in /seeker/).
                if ($redirect !== '' && strpos($redirect, '/') !== 0 && strpos($redirect, 'http://') !== 0 && strpos($redirect, 'https://') !== 0) {
                    $redirect = BASE_URL . '/seeker/' . $redirect;
                }
                echo "<script>alert('Login Successful!'); window.location.href='$redirect';</script>";
                exit();
            } else {
                $error_msg = 'Incorrect Password!';
            }
        } else {
            $error_msg = 'User does not exist!';
        }
        mysqli_stmt_close($stmt);

    // 2. Authenticate Recruiter / Company Role
    } elseif ($role === 'recruiter') {
        $stmt = mysqli_prepare($con, "SELECT id, company_name, company_email, logo, password FROM companies WHERE company_email = ? AND status = 'active'");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($result) > 0) {
            $row = mysqli_fetch_assoc($result);
            $dbpass = $row['password'];

            // Verify BCrypt hashed password
            if (password_verify($pass, $dbpass)) {
                $_SESSION['company_id']    = $row['id'];
                $_SESSION['company_name']  = $row['company_name'];
                $_SESSION['company_email'] = $row['company_email'];
                $_SESSION['company_logo']  = $row['logo'];
                $_SESSION['user_type']      = 'company';
                echo "<script>alert('Login Successful!'); window.location.href='" . BASE_URL . "/company/index.php';</script>";
                exit();
            } else {
                $error_msg = 'Incorrect Password!';
            }
        } else {
            $error_msg = 'Company not found or inactive!';
        }
        mysqli_stmt_close($stmt);

    // 3. Authenticate System Admin Role
    } elseif ($role === 'admin') {
        $stmt = mysqli_prepare($con, "SELECT id, admin_user_name, admin_password FROM admin_login WHERE admin_user_name = ?");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($result) > 0) {
            $row = mysqli_fetch_assoc($result);
            $dbpass = $row['admin_password'];

            // Verify hashed password or legacy plain-text match
            if (password_verify($pass, $dbpass) || $pass === $dbpass) {
                $_SESSION['admin_username'] = $row['admin_user_name'];
                echo "<script>alert('Login Successful!'); window.location.href='" . BASE_URL . "/admin/index.php';</script>";
                exit();
            } else {
                $error_msg = 'Incorrect Password!';
            }
        } else {
            $error_msg = 'Admin not found!';
        }
        mysqli_stmt_close($stmt);

    } else {
        $error_msg = 'Please select a role!';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Log In | NovaHire</title>
    <?php include '../includes/links.php'; ?>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: auto;
            padding: 40px 16px;
            font-family: 'Manrope', 'Inter', sans-serif;
            background:
                radial-gradient(circle at 15% 15%, rgba(99, 102, 241, 0.24), transparent 34%),
                radial-gradient(circle at 85% 20%, rgba(217, 70, 239, 0.16), transparent 32%),
                radial-gradient(circle at 50% 100%, rgba(20, 184, 166, 0.14), transparent 42%),
                #f6f7fb;
            transition: background .4s ease;
        }
        [data-theme="dark"] body {
            background:
                radial-gradient(circle at 15% 15%, rgba(99, 102, 241, 0.30), transparent 34%),
                radial-gradient(circle at 85% 20%, rgba(217, 70, 239, 0.20), transparent 32%),
                radial-gradient(circle at 50% 100%, rgba(20, 184, 166, 0.16), transparent 42%),
                #0f172a;
        }

        @keyframes lg-rise { from { opacity: 0; transform: translateY(18px); } to { opacity: 1; transform: none; } }
        @keyframes lg-pop { 0% { transform: scale(.92); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
        @keyframes lg-shake {
            0%, 100% { transform: translateX(0); }
            20%, 60% { transform: translateX(-7px); }
            40%, 80% { transform: translateX(7px); }
        }

        .lg-wrap { width: 100%; max-width: 980px; }
        .lg-card {
            display: flex;
            background: var(--bg-card);
            border: 1px solid var(--border-light);
            border-radius: 26px;
            box-shadow: 0 30px 70px -28px rgba(15, 23, 42, 0.45);
            overflow: hidden;
            animation: lg-rise .6s cubic-bezier(.21,1.02,.73,1) both;
        }
        [data-theme="dark"] .lg-card { box-shadow: 0 30px 70px -28px rgba(0, 0, 0, 0.75); }

        /* ── Visual panel ── */
        .lg-visual {
            position: relative;
            width: 44%;
            padding: 46px 40px;
            color: #fff;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            background: linear-gradient(135deg, #6d5efc 0%, #8b5cf6 40%, #d946ef 100%);
            overflow: hidden;
        }
        .lg-visual::before, .lg-visual::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            pointer-events: none;
        }
        .lg-visual::before { top: -100px; right: -70px; width: 300px; height: 300px; background: radial-gradient(circle, rgba(255,255,255,0.16), transparent 70%); }
        .lg-visual::after { bottom: -120px; left: -60px; width: 260px; height: 260px; background: radial-gradient(circle, rgba(255,255,255,0.1), transparent 70%); }
        .lg-visual-inner { position: relative; z-index: 2; }
        .lg-logo {
            display: inline-flex; align-items: center; gap: 12px;
            font-family: 'Sora', sans-serif; font-weight: 700; font-size: 1.35rem;
            letter-spacing: -0.01em;
            margin-bottom: 34px;
        }
        .lg-logo-icon {
            width: 46px; height: 46px; border-radius: 14px;
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            border: 1px solid rgba(255,255,255,0.35);
            color: #1e293b;
            display: flex; align-items: center; justify-content: center; font-size: 1.1rem;
            box-shadow: 0 8px 18px -8px rgba(245,158,11,0.8);
        }
        .lg-visual h2 {
            font-family: 'Sora', sans-serif;
            font-size: 2.2rem; font-weight: 800; letter-spacing: -0.03em;
            line-height: 1.15; margin: 0 0 14px;
        }
        .lg-visual h2 span {
            background: linear-gradient(90deg, #fde68a, #fbbf24, #f59e0b);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .lg-visual p { color: rgba(255,255,255,0.85); font-size: .94rem; line-height: 1.7; margin: 0; }
        .lg-roles { display: flex; gap: 14px; margin-top: 28px; }
        .lg-role-ic {
            flex: 1;
            background: rgba(255,255,255,0.12);
            border: 1px solid rgba(255,255,255,0.26);
            border-radius: 16px;
            padding: 14px 8px;
            text-align: center;
            backdrop-filter: blur(4px);
            animation: lg-pop .5s both;
            transition: background .25s, transform .25s, border-color .25s;
        }
        .lg-role-ic:hover { background: rgba(255,255,255,0.24); transform: translateY(-3px); border-color: rgba(255,255,255,0.4); }
        .lg-role-ic i { font-size: 1.4rem; display: block; margin-bottom: 6px; opacity: .95; }
        .lg-role-ic span { font-size: .74rem; font-weight: 700; letter-spacing: .02em; opacity: .9; }
        .lg-visual-foot { color: rgba(255,255,255,0.6); font-size: .76rem; margin-top: 30px; position: relative; z-index: 2; }
        .lg-visual-foot i { margin-right: 5px; }

        /* ── Form panel ── */
        .lg-form { flex: 1; padding: 46px 48px; display: flex; flex-direction: column; justify-content: center; }
        .lg-title { font-family: 'Sora', sans-serif; font-weight: 800; font-size: 1.8rem; color: var(--text); margin: 0 0 4px; letter-spacing: -0.03em; }
        .lg-sub { color: var(--text-muted); font-size: .9rem; margin: 0 0 26px; }

        .lg-rolesel { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 24px; }
        .lg-role {
            position: relative;
            border: 2px solid var(--border-light);
            border-radius: 14px;
            background: var(--bg-card);
            padding: 13px 8px;
            text-align: center;
            cursor: pointer;
            transition: border-color .2s, box-shadow .2s, transform .18s, background .2s;
        }
        .lg-role:hover { transform: translateY(-2px); border-color: rgba(99, 102, 241, 0.55); }
        .lg-role.active {
            border-color: #8b5cf6;
            background: linear-gradient(135deg, rgba(109,94,252,0.12), rgba(217,70,239,0.12));
            box-shadow: 0 0 0 3px rgba(139,92,246,0.16), 0 8px 18px -10px rgba(139,92,246,0.5);
        }
        [data-theme="dark"] .lg-role.active { background: rgba(139,92,246,0.20); }
        .lg-role input { position: absolute; opacity: 0; pointer-events: none; }
        .lg-role i { font-size: 1.3rem; display: block; margin-bottom: 5px; color: var(--text-muted); transition: color .2s, transform .2s; }
        .lg-role.active i { color: #8b5cf6; transform: scale(1.12); }
        [data-theme="dark"] .lg-role.active i { color: #c4b5fd; }
        .lg-role span { font-size: .76rem; font-weight: 700; color: var(--text-muted); transition: color .2s; }
        .lg-role.active span { color: var(--text); }

        .lg-field { position: relative; margin-bottom: 18px; }
        .lg-field > i {
            position: absolute;
            left: 16px; top: 50%; transform: translateY(-50%);
            color: var(--text-muted); font-size: .9rem; z-index: 2;
            transition: color .2s;
        }
        .lg-field.focus > i { color: #8b5cf6; }
        .lg-input {
            width: 100%;
            padding: 13px 46px 13px 44px;
            border: 2px solid var(--border-light);
            border-radius: 14px;
            background: var(--bg-card);
            color: var(--text);
            font-size: .92rem; font-weight: 600;
            transition: border-color .2s, box-shadow .2s, background .2s;
            outline: none;
        }
        .lg-input::placeholder { color: var(--text-light); font-weight: 500; }
        .lg-input:focus {
            border-color: #8b5cf6;
            box-shadow: 0 0 0 4px rgba(139,92,246,0.15);
        }
        .lg-toggle {
            position: absolute; right: 8px; top: 50%; transform: translateY(-50%);
            width: 34px; height: 34px; border-radius: 9px;
            border: 0; background: none; color: var(--text-muted);
            display: flex; align-items: center; justify-content: center;
            transition: color .2s, background .2s; cursor: pointer;
        }
        .lg-toggle:hover { color: var(--text); background: var(--bg-hover); }

        .lg-row { display: flex; align-items: center; justify-content: space-between; margin: 2px 0 20px; gap: 10px; flex-wrap: wrap; }
        .lg-remember { display: flex; align-items: center; gap: 8px; font-size: .82rem; color: var(--text-muted); font-weight: 600; cursor: pointer; }
        .lg-remember input { accent-color: #6366f1; width: 15px; height: 15px; }
        .lg-forgot { color: #8b5cf6; font-weight: 700; font-size: .82rem; text-decoration: none; }
        .lg-forgot:hover { text-decoration: underline; color: #6d5efc; }
        [data-theme="dark"] .lg-forgot { color: #c4b5fd; }

        .lg-btn {
            width: 100%;
            border: 0;
            padding: 15px 20px;
            border-radius: 14px;
            font-family: 'Sora', sans-serif;
            font-weight: 600; font-size: .98rem; letter-spacing: .01em;
            color: #fff;
            background: linear-gradient(135deg, #6d5efc, #8b5cf6 55%, #d946ef);
            background-size: 150% 150%;
            box-shadow: 0 10px 24px -10px rgba(139,92,246,0.65);
            display: inline-flex; align-items: center; justify-content: center; gap: 10px;
            cursor: pointer;
            transition: transform .25s, box-shadow .3s, opacity .2s, background-position .4s;
        }
        .lg-btn:hover { transform: translateY(-2px); box-shadow: 0 16px 32px -12px rgba(217,70,239,0.7); color: #fff; background-position: 100% 50%; }
        .lg-btn:disabled { opacity: .75; cursor: not-allowed; transform: none; }
        .lg-btn .spin { display: none; }
        .lg-btn.loading .spin { display: inline-block; }
        .lg-btn.loading .label { visibility: hidden; position: relative; }
        .lg-btn.loading .label::after { content: 'Signing in…'; visibility: visible; position: absolute; left: 50%; transform: translateX(-50%); }

        .lg-alt { text-align: center; margin-top: 22px; font-size: .85rem; color: var(--text-muted); font-weight: 500; }
        .lg-alt a { color: #8b5cf6; font-weight: 800; text-decoration: none; }
        .lg-alt a:hover { text-decoration: underline; }
        [data-theme="dark"] .lg-alt a { color: #c4b5fd; }

        .lg-alert {
            display: flex; align-items: center; gap: 11px;
            padding: 13px 16px; border-radius: 13px;
            font-weight: 600; font-size: .88rem; margin-bottom: 20px;
            border: 1px solid transparent;
            animation: lg-shake .4s ease;
        }
        .lg-alert.danger { background: rgba(239,68,68,0.1); color: #b91c1c; border-color: rgba(239,68,68,0.3); }
        .lg-alert.danger i { font-size: 1.05rem; }
        [data-theme="dark"] .lg-alert.danger { color: #fca5a5; background: rgba(239,68,68,0.16); }

        .lg-secure { display: flex; align-items: center; gap: 6px; color: var(--text-light); font-size: .74rem; font-weight: 600; margin-top: 18px; }
        .lg-secure i { font-size: .8rem; }

        @media (max-width: 860px) {
            .lg-card { flex-direction: column; max-width: 520px; }
            .lg-visual { width: 100%; padding: 32px 30px; }
            .lg-logo { margin-bottom: 20px; }
            .lg-visual h2 { font-size: 1.7rem; }
            .lg-form { padding: 34px 28px; }
        }
        @media (max-width: 420px) {
            .lg-rolesel { grid-template-columns: 1fr; }
            .lg-role { display: flex; align-items: center; justify-content: center; gap: 10px; padding: 11px; }
            .lg-role i { margin: 0; font-size: 1.1rem; }
            .lg-form { padding: 28px 20px; }
        }
    </style>
</head>

<body>
    <div class="lg-wrap">
        <div class="lg-card">
            <!-- Visual Side -->
            <div class="lg-visual">
                <div class="lg-visual-inner">
                    <div class="lg-logo">
                        <div class="lg-logo-icon"><i class="fas fa-briefcase"></i></div>
                        NovaHire
                    </div>
                    <h2>Welcome back to<br><span>NovaHire</span></h2>
                    <p>Your gateway to career growth. Sign in to browse jobs, connect with top companies and advance your professional journey.</p>
                    <div class="lg-roles">
                        <div class="lg-role-ic" style="animation-delay:.15s;"><i class="fas fa-user-tie"></i><span>Job Seeker</span></div>
                        <div class="lg-role-ic" style="animation-delay:.25s;"><i class="fas fa-building"></i><span>Recruiter</span></div>
                        <div class="lg-role-ic" style="animation-delay:.35s;"><i class="fas fa-user-shield"></i><span>Admin</span></div>
                    </div>
                </div>
                <div class="lg-visual-foot"><i class="fas fa-lock"></i>Secure 256-bit encrypted sign-in</div>
            </div>

            <!-- Form Side -->
            <div class="lg-form">
                <h1 class="lg-title">Log In</h1>
                <p class="lg-sub">Select your role and enter your credentials.</p>

                <?php if (!empty($error_msg)): ?>
                    <div class="lg-alert danger">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo $error_msg; ?>
                    </div>
                <?php endif; ?>

                <form action="login.php<?php echo isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : ''; ?>" method="POST" id="lgForm">
                    <!-- Role Selector -->
                    <div class="lg-rolesel">
                        <label class="lg-role active" id="role-seeker" onclick="selectRole('seeker')">
                            <input type="radio" name="role" value="seeker" checked>
                            <i class="fas fa-user-tie"></i>
                            <span>Job Seeker</span>
                        </label>
                        <label class="lg-role" id="role-recruiter" onclick="selectRole('recruiter')">
                            <input type="radio" name="role" value="recruiter">
                            <i class="fas fa-building"></i>
                            <span>Recruiter</span>
                        </label>
                        <label class="lg-role" id="role-admin" onclick="selectRole('admin')">
                            <input type="radio" name="role" value="admin">
                            <i class="fas fa-user-shield"></i>
                            <span>Admin</span>
                        </label>
                    </div>

                    <!-- Email/Username field -->
                    <div class="lg-field" id="emailFieldWrap">
                        <i class="fa fa-envelope" id="emailIcon"></i>
                        <input name="email" id="emailField" type="email" placeholder="Email Address" class="lg-input" required autocomplete="email">
                    </div>

                    <div class="lg-field" id="passFieldWrap">
                        <i class="fa fa-lock"></i>
                        <input name="password" id="passField" type="password" placeholder="Password" class="lg-input" required autocomplete="current-password">
                        <button type="button" class="lg-toggle" id="passToggle" onclick="togglePass()"><i class="fas fa-eye"></i></button>
                    </div>

                    <div class="lg-row">
                        <label class="lg-remember">
                            <input type="checkbox" id="rememberMe"> Remember me
                        </label>
                        <a href="forgot_password.php?type=user" class="lg-forgot"><i class="fas fa-key mr-1"></i>Forgot Password?</a>
                    </div>

                    <button type="submit" name="submit" class="lg-btn" id="lgSubmit">
                        <span class="spin"><i class="fas fa-spinner fa-spin"></i></span>
                        <span class="label"><i class="fas fa-sign-in-alt mr-2"></i>Sign In</span>
                    </button>

                    <div class="lg-alt" id="registerLink">
                        Don't have an account? <a href="registration.php">Create Account</a>
                    </div>

                    <div class="lg-secure"><i class="fas fa-shield-halved"></i>Your information is protected with NovaHire security.</div>
                </form>
            </div>
        </div>
    </div>

    <script>
    function selectRole(role) {
        document.querySelectorAll('.lg-role').forEach(function(btn) {
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
            registerLink.innerHTML = 'Create admin account? <a href="<?php echo BASE_URL; ?>/admin/register_admin.php">Create Account</a>';
            registerLink.style.display = 'block';
        } else if (role === 'recruiter') {
            emailField.placeholder = 'Company Email';
            emailField.type = 'email';
            emailIcon.className = 'fa fa-envelope';
            registerLink.innerHTML = 'Register your company? <a href="<?php echo BASE_URL; ?>/company_registration.php">Create Account</a>';
            registerLink.style.display = 'block';
        } else {
            emailField.placeholder = 'Email Address';
            emailField.type = 'email';
            emailIcon.className = 'fa fa-envelope';
            registerLink.innerHTML = 'Don\'t have an account? <a href="registration.php">Create Account</a>';
            registerLink.style.display = 'block';
        }
    }

    function togglePass() {
        var f = document.getElementById('passField');
        var b = document.getElementById('passToggle');
        var show = f.type === 'password';
        f.type = show ? 'text' : 'password';
        b.innerHTML = show ? '<i class="fas fa-eye-slash"></i>' : '<i class="fas fa-eye"></i>';
    }

    (function () {
        document.querySelectorAll('.lg-field').forEach(function (w) {
            var input = w.querySelector('input');
            if (!input) return;
            input.addEventListener('focus', function () { w.classList.add('focus'); });
            input.addEventListener('blur', function () { w.classList.remove('focus'); });
        });

        var submit = document.getElementById('lgSubmit');
        var submitting = false;
        document.getElementById('lgForm').addEventListener('submit', function () {
            if (submitting) return false;
            submitting = true;
            submit.classList.add('loading');
        });
    })();
    </script>
</body>
</html>
