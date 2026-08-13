<?php
    session_start();
    include 'dbcon.php';

    $success = '';
    $error = '';

    if (isset($_POST['submit'])) {
        $username = trim(mysqli_real_escape_string($con, $_POST['username'] ?? ''));
        $password = $_POST['password'] ?? '';
        $cpassword = $_POST['cpassword'] ?? '';

        if ($username === '' || $password === '') {
            $error = 'Please fill in all fields.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters long.';
        } elseif ($password !== $cpassword) {
            $error = 'Passwords do not match.';
        } else {
            $check = mysqli_query($con, "SELECT * FROM admin_login WHERE admin_user_name = '$username'");
            if ($check && mysqli_num_rows($check) > 0) {
                $error = 'This admin username already exists.';
            } else {
                $hashed = password_hash($password, PASSWORD_BCRYPT);
                if (mysqli_query($con, "INSERT INTO admin_login (admin_user_name, admin_password) VALUES ('$username', '$hashed')")) {
                    $success = 'Admin account "' . $username . '" created successfully! You can now log in.';
                } else {
                    $error = 'Failed to create admin account. Please try again.';
                }
            }
        }
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Create Admin Account - NovaHire</title>
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

        .ra-wrap { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 30px 16px; }
        .ra-card {
            display: flex;
            width: 100%;
            max-width: 920px;
            background: var(--bg-card);
            border: 1px solid var(--border-light);
            border-radius: 24px;
            box-shadow: var(--shadow-lg);
            overflow: hidden;
            animation: ra-fade .5s ease;
        }
        @keyframes ra-fade { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: none; } }

        .ra-side {
            position: relative;
            width: 42%;
            padding: 44px 38px;
            color: #fff;
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 50%, #0ea5e9 115%);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .ra-side::before, .ra-side::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            pointer-events: none;
        }
        .ra-side::before { top: -90px; right: -60px; width: 260px; height: 260px; background: radial-gradient(circle, rgba(255,255,255,0.16), transparent 70%); }
        .ra-side::after { bottom: -110px; left: -40px; width: 230px; height: 230px; background: radial-gradient(circle, rgba(255,255,255,0.1), transparent 70%); }
        .ra-side-inner { position: relative; z-index: 2; }
        .ra-logo {
            display: inline-flex; align-items: center; gap: 12px;
            font-family: 'Sora', 'Manrope', sans-serif; font-weight: 800; font-size: 1.25rem;
            margin-bottom: 30px;
        }
        .ra-logo-icon {
            width: 44px; height: 44px; border-radius: 13px;
            background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3);
            display: flex; align-items: center; justify-content: center; font-size: 1.05rem;
        }
        .ra-side h2 { font-family: 'Sora', 'Manrope', sans-serif; font-weight: 800; font-size: 1.65rem; margin-bottom: 10px; }
        .ra-side p { color: rgba(255,255,255,0.85); font-size: .93rem; line-height: 1.65; }
        .ra-points { margin-top: 26px; display: flex; flex-direction: column; gap: 12px; }
        .ra-points .sp { display: flex; align-items: center; gap: 10px; font-size: .85rem; font-weight: 600; }
        .ra-points .sp i { width: 30px; height: 30px; border-radius: 9px; background: rgba(255,255,255,0.16); display: inline-flex; align-items: center; justify-content: center; font-size: .8rem; }
        .ra-login {
            display: inline-flex; align-items: center; gap: 8px;
            color: #fff; font-weight: 700; font-size: .86rem; text-decoration: none;
            margin-top: 28px; padding: 9px 16px; border-radius: 11px;
            background: rgba(255,255,255,0.14); border: 1px solid rgba(255,255,255,0.26);
            transition: all .25s ease;
        }
        .ra-login:hover { background: rgba(255,255,255,0.26); color: #fff; text-decoration: none; transform: translateY(-2px); }

        .ra-form-side { flex: 1; padding: 42px 46px; }
        .ra-title { font-family: 'Sora', 'Manrope', sans-serif; font-weight: 800; font-size: 1.4rem; color: var(--text); margin-bottom: 4px; }
        .ra-sub { color: var(--text-muted); font-size: .86rem; margin-bottom: 24px; }

        .ra-field { margin-bottom: 18px; }
        .ra-field label {
            display: flex; align-items: center; gap: 7px;
            font-weight: 700; font-size: .8rem; color: var(--text); margin-bottom: 8px;
        }
        .ra-field label i { color: var(--primary); width: 15px; text-align: center; }
        .ra-input, .ra-input:focus {
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
        .ra-input:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,.15); }
        .ra-input::placeholder { color: var(--text-light); }

        .ra-btn {
            width: 100%;
            border: none; padding: 13px 20px; border-radius: 13px;
            font-weight: 800; font-size: .92rem; color: #fff;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            box-shadow: 0 8px 18px -6px rgba(99,102,241,.55);
            transition: all .3s ease;
            display: inline-flex; align-items: center; justify-content: center; gap: 9px;
        }
        .ra-btn:hover { transform: translateY(-2px); box-shadow: 0 14px 26px -8px rgba(99,102,241,.7); color: #fff; }

        .ra-alert {
            display: flex; align-items: center; gap: 10px;
            padding: 12px 15px; border-radius: 12px;
            font-weight: 600; font-size: .85rem; margin-bottom: 18px;
            border: 1px solid transparent;
        }
        .ra-alert.ok { background: rgba(16,185,129,.12); color: #047857; border-color: rgba(16,185,129,.3); }
        .ra-alert.err { background: rgba(239,68,68,.1); color: #b91c1c; border-color: rgba(239,68,68,.3); }

        .ra-links { display: flex; align-items: center; justify-content: space-between; margin-top: 20px; font-size: .83rem; }
        .ra-links a { color: var(--primary); font-weight: 700; text-decoration: none; }
        .ra-links a:hover { text-decoration: underline; }

        @media (max-width: 767px) {
            .ra-card { flex-direction: column; }
            .ra-side { width: 100%; padding: 30px 26px; }
            .ra-form-side { padding: 30px 24px; }
        }
    </style>
</head>
<body>
    <div class="ra-wrap">
        <div class="ra-card">
            <!-- Side panel -->
            <div class="ra-side">
                <div class="ra-side-inner">
                    <div class="ra-logo">
                        <div class="ra-logo-icon"><i class="fas fa-briefcase"></i></div>
                        NovaHire Admin
                    </div>
                    <h2>Create Admin Account</h2>
                    <p>Register a new administrator to get access to the admin panel.</p>
                    <div class="ra-points">
                        <div class="sp"><i class="fas fa-shield-halved"></i> Full panel access</div>
                        <div class="sp"><i class="fas fa-lock"></i> Securely hashed password</div>
                        <div class="sp"><i class="fas fa-user-check"></i> Unique username enforced</div>
                    </div>
                </div>
                <div style="position:relative;z-index:2;">
                    <a href="../auth/login.php" class="ra-login"><i class="fas fa-sign-in-alt"></i> Back to Login</a>
                </div>
            </div>

            <!-- Form panel -->
            <div class="ra-form-side">
                <h3 class="ra-title">New Admin</h3>
                <p class="ra-sub">Fill in the details below to create the account.</p>

                <?php if ($success): ?>
                    <div class="ra-alert ok"><i class="fas fa-check-circle"></i><?php echo htmlspecialchars($success); ?>
                        <div style="margin-top:12px;">
                            <a href="../auth/login.php" class="btn btn-success btn-sm" style="border-radius:9px;font-weight:700;"><i class="fas fa-sign-in-alt"></i> Go to Login</a>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="ra-alert err"><i class="fas fa-exclamation-circle"></i><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="POST" id="raForm" onsubmit="return raValidate()">
                    <div class="ra-field">
                        <label for="raUsername"><i class="fas fa-user"></i>Admin Username</label>
                        <input type="text" class="ra-input" id="raUsername" name="username" placeholder="Choose a username" required>
                    </div>
                    <div class="ra-field">
                        <label for="raPassword"><i class="fas fa-lock"></i>Password</label>
                        <input type="password" class="ra-input" id="raPassword" name="password" placeholder="Minimum 6 characters" required minlength="6">
                    </div>
                    <div class="ra-field">
                        <label for="raCpassword"><i class="fas fa-lock"></i>Confirm Password</label>
                        <input type="password" class="ra-input" id="raCpassword" name="cpassword" placeholder="Repeat password" required minlength="6">
                    </div>
                    <button type="submit" name="submit" class="ra-btn"><i class="fas fa-user-shield"></i> Create Admin Account</button>
                </form>

                <div class="ra-links">
                    <span style="color:var(--text-muted);">Already have an account?</span>
                    <a href="../auth/login.php">Log in</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        function raValidate() {
            var pass = document.getElementById('raPassword');
            var cpass = document.getElementById('raCpassword');
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
