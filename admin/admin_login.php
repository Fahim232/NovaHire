<?php
require_once __DIR__ . '/../includes/bootstrap.php';
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    $login_error = '';
    $register_success = '';
    $register_error = '';
    $show_register = false;

    /* Is there ANY admin yet? If not, we allow a one-time bootstrap signup.
       Once an admin exists, self-registration is closed — new admins must be
       created from inside the panel (admin/add_admin.php). */
    $admin_count = 0;
    if ($r = mysqli_query($con, "SELECT COUNT(*) c FROM admin_login")) {
        $admin_count = (int)(mysqli_fetch_assoc($r)['c'] ?? 0);
    }
    $first_run = ($admin_count === 0);

    if (isset($_POST['submit'])) {
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
            $login_error = 'Your session expired. Please refresh and try again.';
        } else {
            $admin_username = trim($_POST['admin_username'] ?? '');
            $pass = $_POST['password'] ?? '';

            $stmt = mysqli_prepare($con, "SELECT id, admin_user_name, admin_password FROM admin_login WHERE admin_user_name = ? LIMIT 1");
            mysqli_stmt_bind_param($stmt, "s", $admin_username);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $row = $res ? mysqli_fetch_assoc($res) : null;
            mysqli_stmt_close($stmt);

            // Constant-ish behaviour: same message whether the user or the password is wrong.
            if ($row && password_verify($pass, $row['admin_password'])) {
                session_regenerate_id(true);            // prevent session fixation
                $_SESSION['admin_username'] = $row['admin_user_name'];
                $_SESSION['admin_id'] = (int)$row['id'];
                header('location: admin_dashboard.php');
                exit();
            } else {
                $login_error = 'Invalid username or password.';
            }
        }
    }

    if (isset($_POST['register_submit'])) {
        $show_register = true;
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
            $register_error = 'Your session expired. Please refresh and try again.';
        } elseif (!$first_run) {
            // SECURITY: public admin self-registration is disabled once an admin exists.
            $register_error = 'Admin registration is closed. Ask an existing admin to create your account.';
            $show_register = false;
        } else {
            $username  = trim($_POST['reg_username'] ?? '');
            $password  = $_POST['reg_password'] ?? '';
            $cpassword = $_POST['reg_cpassword'] ?? '';

            if ($username === '' || $password === '') {
                $register_error = 'Please fill in all fields.';
            } elseif (strlen($password) < 8) {
                $register_error = 'Password must be at least 8 characters long.';
            } elseif ($password !== $cpassword) {
                $register_error = 'Passwords do not match.';
            } else {
                $stmt = mysqli_prepare($con, "SELECT id FROM admin_login WHERE admin_user_name = ? LIMIT 1");
                mysqli_stmt_bind_param($stmt, "s", $username);
                mysqli_stmt_execute($stmt);
                $exists = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
                mysqli_stmt_close($stmt);

                if ($exists) {
                    $register_error = 'This admin username already exists.';
                } else {
                    $hashed = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = mysqli_prepare($con, "INSERT INTO admin_login (admin_user_name, admin_password) VALUES (?, ?)");
                    mysqli_stmt_bind_param($stmt, "ss", $username, $hashed);
                    if (mysqli_stmt_execute($stmt)) {
                        $register_success = 'Admin account created successfully! You can now log in.';
                        $show_register = false;
                        $first_run = false;
                    } else {
                        $register_error = 'Failed to create account. Please try again.';
                    }
                    mysqli_stmt_close($stmt);
                }
            }
        }
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Admin Login - NovaHire</title>
    <?php include '../includes/links.php'; ?>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Sora:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            font-family: 'Manrope', 'Inter', sans-serif;
            background:
                radial-gradient(circle at 12% 8%, rgba(99, 102, 241, 0.18), transparent 32%),
                radial-gradient(circle at 88% 12%, rgba(139, 92, 246, 0.14), transparent 30%),
                radial-gradient(circle at 50% 100%, rgba(14, 165, 233, 0.12), transparent 40%),
                #f6f7fb;
        }
        [data-theme="dark"] body {
            background:
                radial-gradient(circle at 12% 8%, rgba(99, 102, 241, 0.26), transparent 32%),
                radial-gradient(circle at 88% 12%, rgba(139, 92, 246, 0.2), transparent 30%),
                radial-gradient(circle at 50% 100%, rgba(14, 165, 233, 0.16), transparent 40%),
                #0f172a;
        }

        .al-wrap { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 30px 16px; }
        .al-card {
            display: flex;
            width: 100%;
            max-width: 940px;
            background: var(--bg-card);
            border: 1px solid var(--border-light);
            border-radius: 24px;
            box-shadow: var(--shadow-lg);
            overflow: hidden;
            animation: al-fade .5s ease;
        }
        @keyframes al-fade { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: none; } }

        .al-side {
            position: relative;
            width: 42%;
            padding: 44px 38px;
            color: #fff;
            background: linear-gradient(135deg, #1a56db 0%, #0ea5e9 50%, #0ea5e9 115%);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .al-side::before, .al-side::after {
            content: '';
            position: absolute;
            border-radius: 50%;
        }
        .al-side::before { top: -90px; right: -60px; width: 260px; height: 260px; background: radial-gradient(circle, rgba(255,255,255,0.16), transparent 70%); }
        .al-side::after { bottom: -110px; left: -40px; width: 230px; height: 230px; background: radial-gradient(circle, rgba(255,255,255,0.1), transparent 70%); }
        .al-side-inner { position: relative; z-index: 2; }
        .al-logo {
            display: inline-flex; align-items: center; gap: 12px;
            font-family: 'Sora', 'Manrope', sans-serif; font-weight: 800; font-size: 1.25rem;
            margin-bottom: 30px;
        }
        .al-logo-icon {
            width: 44px; height: 44px; border-radius: 13px;
            background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3);
            display: flex; align-items: center; justify-content: center; font-size: 1.05rem;
        }
        .al-side h2 { font-family: 'Sora', 'Manrope', sans-serif; font-weight: 800; font-size: 1.65rem; margin-bottom: 10px; }
        .al-side p { color: rgba(255,255,255,0.85); font-size: .93rem; line-height: 1.65; }
        .al-points { margin-top: 26px; display: flex; flex-direction: column; gap: 12px; }
        .al-points .sp { display: flex; align-items: center; gap: 10px; font-size: .85rem; font-weight: 600; }
        .al-points .sp i { width: 30px; height: 30px; border-radius: 9px; background: rgba(255,255,255,0.16); display: inline-flex; align-items: center; justify-content: center; font-size: .8rem; }
        .al-home {
            display: inline-flex; align-items: center; gap: 8px;
            color: #fff; font-weight: 700; font-size: .86rem; text-decoration: none;
            margin-top: 28px; padding: 9px 16px; border-radius: 11px;
            background: rgba(255,255,255,0.14); border: 1px solid rgba(255,255,255,0.26);
            transition: all .25s ease;
        }
        .al-home:hover { background: rgba(255,255,255,0.26); color: #fff; text-decoration: none; transform: translateY(-2px); }

        .al-form-side { flex: 1; padding: 42px 46px; }
        .al-tabs {
            display: flex; gap: 8px; background: var(--bg-hover);
            border: 1px solid var(--border-light); border-radius: 14px; padding: 6px;
            margin-bottom: 26px;
        }
        .al-tab {
            flex: 1; border: none; cursor: pointer;
            padding: 11px 8px; border-radius: 10px;
            font-weight: 700; font-size: .85rem;
            color: var(--text-muted);
            background: transparent;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            transition: all .25s ease;
        }
        .al-tab.al-active {
            color: #fff;
            background: linear-gradient(135deg, #3b82f6, #06b6d4);
            box-shadow: 0 6px 14px -6px rgba(59,130,246,.55);
        }
        .al-pane { display: none; animation: al-fade .35s ease; }
        .al-pane.al-active { display: block; }

        .al-title { font-family: 'Sora', 'Manrope', sans-serif; font-weight: 800; font-size: 1.4rem; color: var(--text); margin-bottom: 4px; }
        .al-sub { color: var(--text-muted); font-size: .86rem; margin-bottom: 24px; }

        .al-field { margin-bottom: 18px; }
        .al-field label {
            display: flex; align-items: center; gap: 7px;
            font-weight: 700; font-size: .8rem; color: var(--text); margin-bottom: 8px;
        }
        .al-field label i { color: var(--primary); width: 15px; text-align: center; }
        .al-input, .al-input:focus {
            width: 100%;
            border: 1.5px solid var(--border-light);
            border-radius: 12px;
            padding: 11px 15px;
            font-size: .9rem;
            background: var(--bg-card);
            color: var(--text);
            outline: none;
            transition: border-color .2s ease, box-shadow .2s ease;
        }
        .al-input:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,.15); }
        .al-input::placeholder { color: var(--text-light); }

        .al-btn {
            width: 100%;
            border: none; padding: 13px 20px; border-radius: 13px;
            font-weight: 800; font-size: .92rem; color: #fff;
            background: linear-gradient(135deg, #3b82f6, #06b6d4);
            box-shadow: 0 8px 18px -6px rgba(59,130,246,.55);
            transition: all .3s ease;
            display: inline-flex; align-items: center; justify-content: center; gap: 9px;
        }
        .al-btn:hover { transform: translateY(-2px); box-shadow: 0 14px 26px -8px rgba(59,130,246,.7); color: #fff; }

        .al-alert {
            display: flex; align-items: center; gap: 10px;
            padding: 12px 15px; border-radius: 12px;
            font-weight: 600; font-size: .85rem; margin-bottom: 18px;
            border: 1px solid transparent;
        }
        .al-alert.ok { background: rgba(5,150,105,.12); color: #047857; border-color: rgba(5,150,105,.3); }
        .al-alert.err { background: rgba(239,68,68,.1); color: #b91c1c; border-color: rgba(239,68,68,.3); }

        .al-links { display: flex; align-items: center; justify-content: space-between; margin-top: 20px; font-size: .83rem; }
        .al-links a { color: var(--primary); font-weight: 700; text-decoration: none; }
        .al-links a:hover { text-decoration: underline; }

        @media (max-width: 767px) {
            .al-card { flex-direction: column; }
            .al-side { width: 100%; padding: 30px 26px; }
            .al-form-side { padding: 30px 24px; }
        }
    </style>
</head>
<body>
    <div class="al-wrap">
        <div class="al-card">
            <!-- Side panel -->
            <div class="al-side">
                <div class="al-side-inner">
                    <div class="al-logo">
                        <div class="al-logo-icon"><i class="fas fa-briefcase"></i></div>
                        NovaHire Admin
                    </div>
                    <h2>Manage Your Job Portal</h2>
                    <p>Login to your admin panel to manage jobs, users, applications and more.</p>
                    <div class="al-points">
                        <div class="sp"><i class="fas fa-chart-line"></i> Dashboard insights</div>
                        <div class="sp"><i class="fas fa-users"></i> User management</div>
                        <div class="sp"><i class="fas fa-file-signature"></i> Application tracking</div>
                    </div>
                </div>
                <div>
                    <a href="../index.php" class="al-home"><i class="fas fa-home"></i> Back to Home</a>
                </div>
            </div>

            <!-- Form panel -->
            <div class="al-form-side">
                <div class="al-tabs">
                    <button type="button" class="al-tab <?php echo $show_register ? '' : 'al-active'; ?>" data-tab="login" id="alTabLogin"><i class="fas fa-sign-in-alt"></i> Login</button>
                    <?php if ($first_run): ?>
                    <button type="button" class="al-tab <?php echo $show_register ? 'al-active' : ''; ?>" data-tab="register" id="alTabRegister"><i class="fas fa-user-plus"></i> Create Account</button>
                    <?php endif; ?>
                </div>

                <!-- LOGIN PANE -->
                <div class="al-pane <?php echo $show_register ? '' : 'al-active'; ?>" id="alPaneLogin">
                    <h3 class="al-title">Welcome Back</h3>
                    <p class="al-sub">Log in to continue to the admin panel.</p>

                    <?php if ($login_error): ?>
                        <div class="al-alert err"><i class="fas fa-exclamation-circle"></i><?php echo htmlspecialchars($login_error); ?></div>
                    <?php endif; ?>
                    <?php if ($register_success): ?>
                        <div class="al-alert ok"><i class="fas fa-check-circle"></i><?php echo htmlspecialchars($register_success); ?></div>
                    <?php endif; ?>

                    <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="POST">
                        <?php echo csrf_field(); ?>
                        <div class="al-field">
                            <label for="alUsername"><i class="fas fa-user"></i>Admin Username</label>
                            <input type="text" class="al-input" id="alUsername" name="admin_username" placeholder="Enter admin username" required>
                        </div>
                        <div class="al-field">
                            <label for="alPassword"><i class="fas fa-lock"></i>Password</label>
                            <input type="password" class="al-input" id="alPassword" name="password" placeholder="Enter password" required>
                        </div>
                        <button type="submit" name="submit" class="al-btn"><i class="fas fa-sign-in-alt"></i> Log In</button>
                    </form>

                    <?php if ($first_run): ?>
                    <div style="display:flex;align-items:center;gap:14px;margin:20px 0 4px;">
                        <span style="flex:1;height:1px;background:var(--border-light);"></span>
                        <span style="color:var(--text-light);font-size:.75rem;font-weight:700;">FIRST-TIME SETUP</span>
                        <span style="flex:1;height:1px;background:var(--border-light);"></span>
                    </div>
                    <p style="color:var(--text-muted);font-size:.82rem;text-align:center;margin-top:10px;">
                        No admin account exists yet. Use <strong>Create Account</strong> above to set one up —
                        this option closes automatically once the first admin is created.
                    </p>
                    <?php endif; ?>
                </div>

                <!-- REGISTER PANE (first-run bootstrap only) -->
                <div class="al-pane <?php echo $show_register ? 'al-active' : ''; ?>" id="alPaneRegister">
                    <h3 class="al-title">Create Admin Account</h3>
                    <p class="al-sub">Register a new admin to access the panel.</p>

                    <?php if ($register_error): ?>
                        <div class="al-alert err"><i class="fas fa-exclamation-circle"></i><?php echo htmlspecialchars($register_error); ?></div>
                    <?php endif; ?>

                    <?php if (!$first_run): ?>
                        <div class="al-alert err"><i class="fas fa-lock"></i>Admin registration is closed. Ask an existing admin to create your account from the panel.</div>
                    <?php else: ?>
                    <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="POST" id="alRegForm" onsubmit="return alValidate()">
                        <?php echo csrf_field(); ?>
                        <div class="al-field">
                            <label for="alRegUsername"><i class="fas fa-user"></i>Admin Username</label>
                            <input type="text" class="al-input" id="alRegUsername" name="reg_username" placeholder="Choose a username" required>
                        </div>
                        <div class="al-field">
                            <label for="alRegPassword"><i class="fas fa-lock"></i>Password</label>
                            <input type="password" class="al-input" id="alRegPassword" name="reg_password" placeholder="Minimum 8 characters" required minlength="8">
                        </div>
                        <div class="al-field">
                            <label for="alRegCpassword"><i class="fas fa-lock"></i>Confirm Password</label>
                            <input type="password" class="al-input" id="alRegCpassword" name="reg_cpassword" placeholder="Repeat password" required minlength="8">
                        </div>
                        <button type="submit" name="register_submit" class="al-btn"><i class="fas fa-user-plus"></i> Create Account</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        function switchTab(name) {
            var tabs = document.querySelectorAll('.al-tab');
            tabs.forEach(function (t) {
                t.classList.toggle('al-active', t.dataset.tab === name);
            });
            document.getElementById('alPaneLogin').classList.toggle('al-active', name === 'login');
            document.getElementById('alPaneRegister').classList.toggle('al-active', name === 'register');
        }
        var tabLogin = document.getElementById('alTabLogin');
        var tabRegister = document.getElementById('alTabRegister');
        if (tabLogin) tabLogin.addEventListener('click', function () { switchTab('login'); });
        if (tabRegister) tabRegister.addEventListener('click', function () { switchTab('register'); });

        function alValidate() {
            var pass = document.getElementById('alRegPassword');
            var cpass = document.getElementById('alRegCpassword');
            if (pass && cpass && pass.value !== cpass.value) {
                alert('Passwords do not match.');
                cpass.focus();
                return false;
            }
            return true;
        }
    </script>
</body>
</html>
