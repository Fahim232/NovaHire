<?php
// Core setup: session, DB, BASE_URL, helpers
require_once __DIR__ . '/../includes/bootstrap.php';
/**
 * User & Role Authentication Portal (Login)
 * 
 * Handles multi-role authentication (Job Seeker, Employer/Recruiter, and System Admin).
 * Uses prepared statement queries to verify user credentials and password hashes, initializing session variables.
 */

$error_msg = '';

// Session timeout check (30 minutes inactivity)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 1800) {
    session_unset();
    session_destroy();
    $error_msg = 'Session expired due to inactivity. Please login again.';
}
$_SESSION['last_activity'] = time();

// Handle form submission
if (isset($_POST['submit'])) {
    // CSRF Verification
    require_csrf();
    
    // Rate limiting
    rate_limit_response('login');
    
    $role  = trim($_POST['role'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    // Check if account is locked
    if (is_login_locked($email)) {
        $error_msg = 'Account temporarily locked due to too many failed attempts. Please try again in 15 minutes.';
    }
    // 1. Authenticate Job Seeker Role
    elseif ($role === 'seeker') {
        $stmt = mysqli_prepare($con, "SELECT id, username, phone, email, password FROM user_info WHERE email = ?");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($result) > 0) {
            $row = mysqli_fetch_assoc($result);
            $dbpass = $row['password'];
            
            // Verify BCrypt hashed password
            if (password_verify($pass, $dbpass)) {
                // Track successful login
                track_login_attempt($email, true);

                session_regenerate_id(true);          // prevent session fixation

                $_SESSION['username'] = $row['username'];
                $_SESSION['uphone']   = $row['phone'];
                $_SESSION['email']    = $row['email'];
                $_SESSION['id']       = $row['id'];
                $_SESSION['last_activity'] = time();

                // Remember me functionality
                if ($remember) {
                    $token = bin2hex(random_bytes(32));
                    $hashed_token = password_hash($token, PASSWORD_DEFAULT);
                    setcookie('remember_token', $token, time() + (86400 * 30), '/', '', true, true);
                    $_SESSION['remember_token'] = $hashed_token;
                }

                // Log login activity
                create_activity_log('user', $row['id'], 'login', ['ip' => $_SERVER['REMOTE_ADDR'] ?? '']);

                /* Same-origin redirect only. An attacker-supplied ?redirect= must never be able
                   to send the user off-site, nor break out of the JS string below. */
                $redirect = BASE_URL . '/seeker/seeker_dashboard.php';
                if (isset($_GET['redirect'])) {
                    $want = trim((string)$_GET['redirect']);
                    // Reject absolute URLs, protocol-relative URLs and anything with a scheme.
                    if ($want !== ''
                        && strpos($want, '//') !== 0
                        && strpos($want, "\\") === false
                        && !preg_match('#^[a-z][a-z0-9+.-]*:#i', $want)) {
                        $redirect = (strpos($want, '/') === 0)
                            ? $want                       // already root-relative
                            : BASE_URL . '/seeker/' . $want; // legacy seeker page
                    }
                }
                echo "<script>alert('Login Successful!'); window.location.href=" . json_encode($redirect) . ";</script>";
                exit();
            } else {
                // Track failed login attempt
                track_login_attempt($email, false);
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
                session_regenerate_id(true);          // prevent session fixation
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

            // Verify BCrypt hashed password (no plain-text fallback — all rows must be hashed)
            if (password_verify($pass, $dbpass)) {
                session_regenerate_id(true);          // prevent session fixation
                $_SESSION['admin_username'] = $row['admin_user_name'];
                $_SESSION['admin_id']       = (int)$row['id'];
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
        * { box-sizing: border-box; }
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: auto;
            padding: 40px 16px;
            font-family: 'Manrope', 'Inter', sans-serif;
            background: #f0f4f8;
            transition: background .4s ease;
        }
        [data-theme="dark"] body {
            background: #0b1120;
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
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            box-shadow: 0 20px 60px -20px rgba(15, 23, 42, 0.15);
            overflow: hidden;
            animation: lg-rise .6s cubic-bezier(.21,1.02,.73,1) both;
        }
        [data-theme="dark"] .lg-card {
            background: #162032;
            border-color: #1e3a5f;
            box-shadow: 0 20px 60px -20px rgba(0, 0, 0, 0.5);
        }

        /* ── Visual panel ── */
        .lg-visual {
            position: relative;
            width: 44%;
            padding: 46px 40px;
            color: #ffffff;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            background: linear-gradient(160deg, #0f172a 0%, #1e3a5f 50%, #1a56db 100%);
            overflow: hidden;
        }
        .lg-visual::before, .lg-visual::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            pointer-events: none;
        }
        .lg-visual::before { top: -100px; right: -70px; width: 300px; height: 300px; background: radial-gradient(circle, rgba(59,130,246,0.2), transparent 70%); }
        .lg-visual::after { bottom: -120px; left: -60px; width: 260px; height: 260px; background: radial-gradient(circle, rgba(14,165,233,0.15), transparent 70%); }
        .lg-visual-inner { position: relative; z-index: 2; }
        .lg-logo {
            display: inline-flex; align-items: center; gap: 12px;
            font-family: 'Sora', sans-serif; font-weight: 700; font-size: 1.35rem;
            letter-spacing: -0.01em;
            margin-bottom: 34px;
        }
        .lg-logo-icon {
            width: 46px; height: 46px; border-radius: 14px;
            background: rgba(255,255,255,0.15);
            border: 1px solid rgba(255,255,255,0.25);
            color: #ffffff;
            display: flex; align-items: center; justify-content: center; font-size: 1.1rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .lg-visual h2 {
            font-family: 'Sora', sans-serif;
            font-size: 2.2rem; font-weight: 800; letter-spacing: -0.03em;
            line-height: 1.15; margin: 0 0 14px;
        }
        .lg-visual h2 span {
            color: #93c5fd;
        }
        .lg-visual p { color: rgba(255,255,255,0.75); font-size: .94rem; line-height: 1.7; margin: 0; }
        .lg-roles { display: flex; gap: 14px; margin-top: 28px; }
        .lg-role-ic {
            flex: 1;
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 16px;
            padding: 14px 8px;
            text-align: center;
            backdrop-filter: blur(4px);
            animation: lg-pop .5s both;
            transition: background .25s, transform .25s, border-color .25s;
        }
        .lg-role-ic:hover { background: rgba(255,255,255,0.15); transform: translateY(-3px); border-color: rgba(255,255,255,0.3); }
        .lg-role-ic i { font-size: 1.4rem; display: block; margin-bottom: 6px; opacity: .9; }
        .lg-role-ic span { font-size: .74rem; font-weight: 700; letter-spacing: .02em; opacity: .85; }
        .lg-visual-foot { color: rgba(255,255,255,0.5); font-size: .76rem; margin-top: 30px; position: relative; z-index: 2; }
        .lg-visual-foot i { margin-right: 5px; }

        /* ── Form panel ── */
        .lg-form { flex: 1; padding: 46px 48px; display: flex; flex-direction: column; justify-content: center; }
        .lg-title { font-family: 'Sora', sans-serif; font-weight: 800; font-size: 1.8rem; color: #0f172a; margin: 0 0 4px; letter-spacing: -0.03em; }
        [data-theme="dark"] .lg-title { color: #f1f5f9; }
        .lg-sub { color: #64748b; font-size: .9rem; margin: 0 0 26px; }
        [data-theme="dark"] .lg-sub { color: #94a3b8; }

        .lg-rolesel { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 24px; }
        .lg-role {
            position: relative;
            border: 2px solid #e2e8f0;
            border-radius: 14px;
            background: #ffffff;
            padding: 13px 8px;
            text-align: center;
            cursor: pointer;
            transition: border-color .2s, box-shadow .2s, transform .18s, background .2s;
        }
        [data-theme="dark"] .lg-role {
            border-color: #1e3a5f;
            background: #1a2744;
        }
        .lg-role:hover { transform: translateY(-2px); border-color: #93c5fd; }
        .lg-role.active {
            border-color: #1a56db;
            background: #eff6ff;
            box-shadow: 0 0 0 3px rgba(26,86,219,0.12), 0 4px 12px -4px rgba(26,86,219,0.3);
        }
        [data-theme="dark"] .lg-role.active { background: rgba(26,86,219,0.2); border-color: #3b82f6; }
        .lg-role input { position: absolute; opacity: 0; pointer-events: none; }
        .lg-role i { font-size: 1.3rem; display: block; margin-bottom: 5px; color: #94a3b8; transition: color .2s, transform .2s; }
        [data-theme="dark"] .lg-role i { color: #64748b; }
        .lg-role.active i { color: #1a56db; transform: scale(1.12); }
        [data-theme="dark"] .lg-role.active i { color: #60a5fa; }
        .lg-role span { font-size: .76rem; font-weight: 700; color: #94a3b8; transition: color .2s; }
        [data-theme="dark"] .lg-role span { color: #64748b; }
        .lg-role.active span { color: #0f172a; }
        [data-theme="dark"] .lg-role.active span { color: #f1f5f9; }

        .lg-field { position: relative; margin-bottom: 18px; }
        .lg-field > i {
            position: absolute;
            left: 16px; top: 50%; transform: translateY(-50%);
            color: #94a3b8; font-size: .9rem; z-index: 2;
            transition: color .2s;
        }
        [data-theme="dark"] .lg-field > i { color: #64748b; }
        .lg-field.focus > i { color: #1a56db; }
        [data-theme="dark"] .lg-field.focus > i { color: #60a5fa; }
        .lg-input {
            width: 100%;
            padding: 13px 46px 13px 44px;
            border: 2px solid #e2e8f0;
            border-radius: 14px;
            background: #ffffff;
            color: #0f172a;
            font-size: .92rem; font-weight: 600;
            transition: border-color .2s, box-shadow .2s, background .2s;
            outline: none;
        }
        [data-theme="dark"] .lg-input {
            border-color: #1e3a5f;
            background: #1a2744;
            color: #f1f5f9;
        }
        .lg-input::placeholder { color: #94a3b8; font-weight: 500; }
        [data-theme="dark"] .lg-input::placeholder { color: #64748b; }
        .lg-input:focus {
            border-color: #1a56db;
            box-shadow: 0 0 0 4px rgba(26,86,219,0.1);
        }
        [data-theme="dark"] .lg-input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 4px rgba(59,130,246,0.15);
        }
        .lg-toggle {
            position: absolute; right: 8px; top: 50%; transform: translateY(-50%);
            width: 34px; height: 34px; border-radius: 9px;
            border: 0; background: none; color: #94a3b8;
            display: flex; align-items: center; justify-content: center;
            transition: color .2s, background .2s; cursor: pointer;
        }
        [data-theme="dark"] .lg-toggle { color: #64748b; }
        .lg-toggle:hover { color: #0f172a; background: #f1f5f9; }
        [data-theme="dark"] .lg-toggle:hover { color: #f1f5f9; background: #1e3a5f; }

        .lg-row { display: flex; align-items: center; justify-content: space-between; margin: 2px 0 20px; gap: 10px; flex-wrap: wrap; }
        .lg-remember { display: flex; align-items: center; gap: 8px; font-size: .82rem; color: #64748b; font-weight: 600; cursor: pointer; }
        [data-theme="dark"] .lg-remember { color: #94a3b8; }
        .lg-remember input { accent-color: #1a56db; width: 15px; height: 15px; }
        .lg-forgot { color: #1a56db; font-weight: 700; font-size: .82rem; text-decoration: none; }
        .lg-forgot:hover { text-decoration: underline; color: #1e40af; }
        [data-theme="dark"] .lg-forgot { color: #60a5fa; }
        [data-theme="dark"] .lg-forgot:hover { color: #93c5fd; }

        .lg-btn {
            width: 100%;
            border: 0;
            padding: 15px 20px;
            border-radius: 14px;
            font-family: 'Sora', sans-serif;
            font-weight: 600; font-size: .98rem; letter-spacing: .01em;
            color: #ffffff;
            background: linear-gradient(135deg, #1a56db, #1e40af);
            box-shadow: 0 8px 20px -6px rgba(26,86,219,0.5);
            display: inline-flex; align-items: center; justify-content: center; gap: 10px;
            cursor: pointer;
            transition: transform .25s, box-shadow .3s, opacity .2s;
        }
        .lg-btn:hover { transform: translateY(-2px); box-shadow: 0 12px 28px -8px rgba(26,86,219,0.6); color: #ffffff; }
        .lg-btn:disabled { opacity: .75; cursor: not-allowed; transform: none; }
        .lg-btn .spin { display: none; }
        .lg-btn.loading .spin { display: inline-block; }
        .lg-btn.loading .label { visibility: hidden; position: relative; }
        .lg-btn.loading .label::after { content: 'Signing in…'; visibility: visible; position: absolute; left: 50%; transform: translateX(-50%); }

        .lg-alt { text-align: center; margin-top: 22px; font-size: .85rem; color: #64748b; font-weight: 500; }
        [data-theme="dark"] .lg-alt { color: #94a3b8; }
        .lg-alt a { color: #1a56db; font-weight: 800; text-decoration: none; }
        .lg-alt a:hover { text-decoration: underline; }
        [data-theme="dark"] .lg-alt a { color: #60a5fa; }

        .lg-alert {
            display: flex; align-items: center; gap: 11px;
            padding: 13px 16px; border-radius: 13px;
            font-weight: 600; font-size: .88rem; margin-bottom: 20px;
            border: 1px solid transparent;
            animation: lg-shake .4s ease;
        }
        .lg-alert.danger { background: #fef2f2; color: #b91c1c; border-color: #fecaca; }
        .lg-alert.danger i { font-size: 1.05rem; }
        [data-theme="dark"] .lg-alert.danger { color: #fca5a5; background: rgba(239,68,68,0.16); border-color: rgba(239,68,68,0.25); }

        .lg-secure { display: flex; align-items: center; gap: 6px; color: #94a3b8; font-size: .74rem; font-weight: 600; margin-top: 18px; }
        [data-theme="dark"] .lg-secure { color: #64748b; }
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
                    <?php echo csrf_input(); ?>
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
            registerLink.innerHTML = 'Admin accounts are created by existing admins.';
            registerLink.style.display = 'block';
        } else if (role === 'recruiter') {
            emailField.placeholder = 'Company Email';
            emailField.type = 'email';
            emailIcon.className = 'fa fa-envelope';
            registerLink.innerHTML = 'Register your company? <a href="<?php echo BASE_URL; ?>/auth/company_registration.php">Create Account</a>';
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
