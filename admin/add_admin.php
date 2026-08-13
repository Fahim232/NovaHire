<?php
session_start();
if (!isset($_SESSION['admin_username'])) {
    echo '<script>alert("You are logged out!"); window.location.href="admin_login.php";</script>';
    exit();
}

require_once 'dbcon.php';
include 'header.php';

$success = null;
$errors = [];

if (isset($_POST['submit'])) {
    $username = trim(mysqli_real_escape_string($con, $_POST['username'] ?? ''));
    $password = $_POST['password'] ?? '';
    $cpassword = $_POST['cpassword'] ?? '';

    if ($username === '' || $password === '') {
        $errors[] = 'Please fill in all required fields.';
    }
    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters long.';
    }
    if ($password !== $cpassword) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        $check = mysqli_query($con, "SELECT id FROM admin_login WHERE admin_user_name = '$username'");
        if ($check && mysqli_num_rows($check) > 0) {
            $errors[] = 'This admin username already exists.';
        }
    }

    if (empty($errors)) {
        $hashed = password_hash($password, PASSWORD_BCRYPT);
        if (mysqli_query($con, "INSERT INTO admin_login (admin_user_name, admin_password) VALUES ('$username', '$hashed')")) {
            $success = 'Admin account "' . $username . '" created successfully.';
        } else {
            $errors[] = 'Failed to create admin account. Please try again.';
        }
    }
}
?>

<style>
    @keyframes adm-fade { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: none; } }

    .adm-wrap { padding: 46px 0 60px; }
    .adm-card {
        display: flex;
        background: var(--bg-card);
        border: 1px solid var(--border-light);
        border-radius: 22px;
        box-shadow: var(--shadow-lg);
        overflow: hidden;
        animation: adm-fade .5s ease;
    }

    .adm-side {
        position: relative;
        width: 38%;
        padding: 48px 40px;
        color: #fff;
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 45%, #4f46e5 130%);
        overflow: hidden;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }
    .adm-side::before, .adm-side::after {
        content: '';
        position: absolute;
        border-radius: 50%;
        pointer-events: none;
    }
    .adm-side::before { top: -90px; right: -60px; width: 260px; height: 260px; background: radial-gradient(circle, rgba(255,255,255,0.12), transparent 70%); }
    .adm-side::after { bottom: -110px; left: -40px; width: 230px; height: 230px; background: radial-gradient(circle, rgba(99,102,241,0.35), transparent 70%); }
    .adm-side-inner { position: relative; z-index: 2; }
    .adm-side-tile {
        width: 56px; height: 56px; border-radius: 16px;
        background: rgba(255,255,255,0.12);
        border: 1px solid rgba(255,255,255,0.22);
        display: flex; align-items: center; justify-content: center;
        font-size: 1.3rem; margin-bottom: 22px;
    }
    .adm-side h2 { font-family: 'Sora', 'Manrope', 'Inter', sans-serif; font-weight: 800; font-size: 1.65rem; margin-bottom: 10px; }
    .adm-side p { color: rgba(255,255,255,0.8); font-size: .93rem; line-height: 1.65; }
    .adm-side-points { margin-top: 26px; display: flex; flex-direction: column; gap: 12px; }
    .adm-side-points .sp { display: flex; align-items: center; gap: 10px; font-size: .85rem; font-weight: 600; }
    .adm-side-points .sp i { width: 30px; height: 30px; border-radius: 9px; background: rgba(255,255,255,0.14); display: inline-flex; align-items: center; justify-content: center; font-size: .78rem; }
    .adm-back {
        display: inline-flex; align-items: center; gap: 8px;
        color: #fff; font-weight: 700; font-size: .86rem;
        text-decoration: none; margin-top: 28px;
        padding: 10px 17px; border-radius: 11px;
        background: rgba(255,255,255,0.12); border: 1px solid rgba(255,255,255,0.24);
        transition: all .25s ease;
    }
    .adm-back:hover { background: rgba(255,255,255,0.22); color: #fff; text-decoration: none; transform: translateY(-2px); }

    .adm-form-side { flex: 1; padding: 44px 46px; }
    .adm-title { font-family: 'Sora', 'Manrope', 'Inter', sans-serif; font-weight: 800; font-size: 1.45rem; color: var(--text); margin-bottom: 4px; }
    .adm-sub { color: var(--text-muted); font-size: .88rem; margin-bottom: 26px; }

    .adm-field { margin-bottom: 18px; }
    .adm-field label {
        display: flex; align-items: center; gap: 7px;
        font-weight: 700; font-size: .8rem; color: var(--text); margin-bottom: 8px;
    }
    .adm-field label i { color: var(--primary); width: 15px; text-align: center; }
    .adm-req { color: #ef4444; }
    .adm-input, .adm-input:focus {
        width: 100%;
        border: 1.5px solid var(--border-light);
        border-radius: 12px;
        padding: 11px 15px;
        font-size: .9rem;
        background: var(--bg-card);
        color: var(--text);
        transition: border-color .2s ease, box-shadow .2s ease;
        outline: none;
    }
    .adm-input:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,.15); }
    .adm-input::placeholder { color: var(--text-light); }

    .adm-btn {
        width: 100%;
        border: none; padding: 13px 20px; border-radius: 13px;
        font-weight: 800; font-size: .92rem; color: #fff;
        background: linear-gradient(135deg, #6366f1, #8b5cf6);
        box-shadow: 0 8px 18px -6px rgba(99,102,241,.55);
        transition: all .3s ease;
        display: inline-flex; align-items: center; justify-content: center; gap: 9px;
    }
    .adm-btn:hover { transform: translateY(-2px); box-shadow: 0 14px 26px -8px rgba(99,102,241,.7); color: #fff; }

    .adm-alert {
        display: flex; align-items: center; gap: 10px;
        padding: 13px 16px; border-radius: 13px;
        font-weight: 600; font-size: .88rem; margin-bottom: 22px;
        border: 1px solid transparent;
    }
    .adm-alert.ok { background: rgba(16,185,129,.12); color: #047857; border-color: rgba(16,185,129,.3); }
    .adm-alert.err { background: rgba(239,68,68,.1); color: #b91c1c; border-color: rgba(239,68,68,.3); }

    @media (max-width: 991px) {
        .adm-card { flex-direction: column; }
        .adm-side { width: 100%; padding: 32px 28px; }
        .adm-form-side { padding: 32px 26px; }
    }
</style>

<div class="container adm-wrap">
    <div class="row justify-content-center">
        <div class="col-lg-9 col-xl-8">
            <div class="adm-card">
                <!-- Side panel -->
                <div class="adm-side">
                    <div class="adm-side-inner">
                        <div class="adm-side-tile"><i class="fas fa-user-shield"></i></div>
                        <h2>Add New Admin</h2>
                        <p>Create a new administrator account. They will be able to log in to the admin panel and manage the portal.</p>
                        <div class="adm-side-points">
                            <div class="sp"><i class="fas fa-shield-halved"></i> Full panel access</div>
                            <div class="sp"><i class="fas fa-lock"></i> Securely hashed password</div>
                            <div class="sp"><i class="fas fa-user-check"></i> Unique username enforced</div>
                        </div>
                    </div>
                    <div style="position:relative;z-index:2;">
                        <a href="admin_dashboard.php" class="adm-back"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
                    </div>
                </div>

                <!-- Form panel -->
                <div class="adm-form-side">
                    <h3 class="adm-title">Admin Credentials</h3>
                    <p class="adm-sub">Fill in the details below to create the account.</p>

                    <?php if ($success): ?>
                        <div class="adm-alert ok"><i class="fas fa-check-circle"></i><?php echo htmlspecialchars($success); ?></div>
                    <?php endif; ?>
                    <?php foreach ($errors as $err): ?>
                        <div class="adm-alert err"><i class="fas fa-exclamation-circle"></i><?php echo htmlspecialchars($err); ?></div>
                    <?php endforeach; ?>

                    <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="POST" id="admForm" onsubmit="return admValidate()">
                        <div class="adm-field">
                            <label for="admUsername"><i class="fas fa-user"></i>Admin Username <span class="adm-req">*</span></label>
                            <input type="text" class="adm-input" id="admUsername" name="username" placeholder="e.g. admin2" required>
                        </div>
                        <div class="adm-field">
                            <label for="admPassword"><i class="fas fa-lock"></i>Password <span class="adm-req">*</span></label>
                            <input type="password" class="adm-input" id="admPassword" name="password" placeholder="Minimum 6 characters" required minlength="6">
                            <small style="color: var(--text-muted); font-size: .76rem; margin-top: 6px; display: block;">Default admin: admin</small>
                        </div>
                        <div class="adm-field">
                            <label for="admCpassword"><i class="fas fa-lock"></i>Confirm Password <span class="adm-req">*</span></label>
                            <input type="password" class="adm-input" id="admCpassword" name="cpassword" placeholder="Repeat password" required minlength="6">
                        </div>
                        <button type="submit" name="submit" class="adm-btn"><i class="fas fa-user-shield"></i> Create Admin Account</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function admValidate() {
        var pass = document.getElementById('admPassword');
        var cpass = document.getElementById('admCpassword');
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
