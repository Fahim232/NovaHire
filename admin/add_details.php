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
$old = ['username' => '', 'email' => '', 'phone' => '', 'degree' => '', 'skills' => ''];

if (isset($_POST['submit'])) {
    $username = trim(mysqli_real_escape_string($con, $_POST['username'] ?? ''));
    $email = trim(mysqli_real_escape_string($con, $_POST['email'] ?? ''));
    $phone = trim(mysqli_real_escape_string($con, $_POST['phone'] ?? ''));
    $degree = trim(mysqli_real_escape_string($con, $_POST['degree'] ?? ''));
    $skills = trim(mysqli_real_escape_string($con, $_POST['skills'] ?? ''));
    $password = $_POST['password'] ?? '';
    $cpassword = $_POST['cpassword'] ?? '';

    $old = compact('username', 'email', 'phone', 'degree', 'skills');

    if ($username === '' || $email === '' || $phone === '' || $password === '') {
        $errors[] = 'Please fill in all required fields.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters long.';
    }
    if ($password !== $cpassword) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        $check = mysqli_query($con, "SELECT id FROM user_info WHERE email = '$email'");
        if ($check && mysqli_num_rows($check) > 0) {
            $errors[] = 'An account with this email already exists.';
        }
    }

    $profile_name = '';
    if (empty($errors) && isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK && $_FILES['profile_image']['size'] > 0) {
        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed_exts)) {
            $upload_dir = __DIR__ . '/../images/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $profile_name = 'profile_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
            if (!move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_dir . $profile_name)) {
                $profile_name = '';
            }
        } else {
            $errors[] = 'Profile image must be JPG, PNG, GIF or WebP.';
        }
    }

    if (empty($errors)) {
        $passEncrypt = password_hash($password, PASSWORD_BCRYPT);
        $insert = "INSERT INTO user_info(username, email, phone, password, cpassword, user_degree, user_skills, profile)
                   VALUES('$username', '$email', '$phone', '$passEncrypt', '$passEncrypt', '$degree', '$skills', '$profile_name')";
        if (mysqli_query($con, $insert)) {
            $success = 'Account created successfully for ' . $username . '.';
            $old = ['username' => '', 'email' => '', 'phone' => '', 'degree' => '', 'skills' => ''];
        } else {
            $errors[] = 'Failed to create account. Please try again.';
        }
    }
}
?>

<style>
    @keyframes ac-fade { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: none; } }

    .ac-wrap { padding: 46px 0 60px; }
    .ac-card {
        display: flex;
        background: var(--bg-card);
        border: 1px solid var(--border-light);
        border-radius: 22px;
        box-shadow: var(--shadow-lg);
        overflow: hidden;
        animation: ac-fade .5s ease;
    }

    .ac-side {
        position: relative;
        width: 40%;
        padding: 48px 40px;
        color: #fff;
        background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 50%, #0ea5e9 115%);
        overflow: hidden;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }
    .ac-side::before, .ac-side::after {
        content: '';
        position: absolute;
        border-radius: 50%;
        pointer-events: none;
    }
    .ac-side::before { top: -90px; right: -60px; width: 260px; height: 260px; background: radial-gradient(circle, rgba(255,255,255,0.16), transparent 70%); }
    .ac-side::after { bottom: -110px; left: -40px; width: 230px; height: 230px; background: radial-gradient(circle, rgba(255,255,255,0.1), transparent 70%); }
    .ac-side-inner { position: relative; z-index: 2; }
    .ac-side-tile {
        width: 56px; height: 56px; border-radius: 16px;
        background: rgba(255,255,255,0.18);
        border: 1px solid rgba(255,255,255,0.28);
        display: flex; align-items: center; justify-content: center;
        font-size: 1.3rem; margin-bottom: 22px;
    }
    .ac-side h2 { font-weight: 800; font-size: 1.7rem; margin-bottom: 10px; }
    .ac-side p { color: rgba(255,255,255,0.85); font-size: .95rem; line-height: 1.6; }
    .ac-side-points { margin-top: 26px; display: flex; flex-direction: column; gap: 12px; }
    .ac-side-points .sp { display: flex; align-items: center; gap: 10px; font-size: .86rem; font-weight: 600; }
    .ac-side-points .sp i { width: 30px; height: 30px; border-radius: 9px; background: rgba(255,255,255,0.16); display: inline-flex; align-items: center; justify-content: center; font-size: .8rem; }
    .ac-back {
        display: inline-flex; align-items: center; gap: 8px;
        color: #fff; font-weight: 700; font-size: .88rem;
        text-decoration: none; margin-top: 30px;
        padding: 10px 18px; border-radius: 12px;
        background: rgba(255,255,255,0.14); border: 1px solid rgba(255,255,255,0.26);
        transition: all .25s ease;
    }
    .ac-back:hover { background: rgba(255,255,255,0.26); color: #fff; text-decoration: none; transform: translateY(-2px); }

    .ac-form-side { flex: 1; padding: 44px 46px; }
    .ac-form-title { font-family: 'Sora', 'Manrope', 'Inter', sans-serif; font-weight: 800; font-size: 1.45rem; color: var(--text); margin-bottom: 4px; }
    .ac-form-sub { color: var(--text-muted); font-size: .88rem; margin-bottom: 26px; }

    .ac-field { margin-bottom: 18px; }
    .ac-field label {
        display: flex; align-items: center; gap: 7px;
        font-weight: 700; font-size: .8rem; color: var(--text);
        margin-bottom: 8px;
    }
    .ac-field label i { color: var(--primary); width: 15px; text-align: center; }
    .ac-field .ac-req { color: #ef4444; }
    .ac-input, .ac-input:focus {
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
    .ac-input:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,.15); }
    .ac-input::placeholder { color: var(--text-light); }

    .ac-photo-wrap { display: flex; align-items: center; gap: 16px; }
    .ac-photo-preview {
        width: 64px; height: 64px; border-radius: 16px;
        background: var(--bg-hover);
        border: 1.5px dashed var(--border);
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        color: var(--text-muted); font-size: .72rem; font-weight: 600; gap: 4px;
        overflow: hidden; flex-shrink: 0;
        transition: all .25s ease;
    }
    .ac-photo-preview.has-photo { border-style: solid; border-color: #10b981; }
    .ac-photo-preview img { width: 100%; height: 100%; object-fit: cover; }
    .ac-photo-preview.has-photo i, .ac-photo-preview.has-photo span { display: none; }
    .ac-file-input { position: relative; overflow: hidden; }
    .ac-file-input input[type="file"] { position: absolute; inset: 0; opacity: 0; cursor: pointer; }

    .ac-btn {
        width: 100%;
        border: none;
        padding: 13px 20px;
        border-radius: 13px;
        font-weight: 800;
        font-size: .92rem;
        color: #fff;
        background: linear-gradient(135deg, #6366f1, #8b5cf6);
        box-shadow: 0 8px 18px -6px rgba(99,102,241,.55);
        transition: all .3s ease;
        display: inline-flex; align-items: center; justify-content: center; gap: 9px;
    }
    .ac-btn:hover { transform: translateY(-2px); box-shadow: 0 14px 26px -8px rgba(99,102,241,.7); color: #fff; }

    .ac-alert {
        display: flex; align-items: center; gap: 10px;
        padding: 13px 16px; border-radius: 13px;
        font-weight: 600; font-size: .88rem; margin-bottom: 22px;
        border: 1px solid transparent;
    }
    .ac-alert.ok { background: rgba(16,185,129,.12); color: #047857; border-color: rgba(16,185,129,.3); }
    .ac-alert.err { background: rgba(239,68,68,.1); color: #b91c1c; border-color: rgba(239,68,68,.3); }
    .ac-alert i { font-size: 1rem; }

    @media (max-width: 991px) {
        .ac-card { flex-direction: column; }
        .ac-side { width: 100%; padding: 32px 28px; }
        .ac-form-side { padding: 32px 26px; }
    }
    @media (max-width: 575px) {
        .ac-wrap { padding: 24px 0 40px; }
        .ac-form-side { padding: 26px 18px; }
    }
</style>

<div class="container ac-wrap">
    <div class="row justify-content-center">
        <div class="col-lg-10 col-xl-9">
            <div class="ac-card">
                <!-- Side panel -->
                <div class="ac-side">
                    <div class="ac-side-inner">
                        <div class="ac-side-tile"><i class="fas fa-user-plus"></i></div>
                        <h2>Create Account</h2>
                        <p>Add a new job-seeker account to the platform. Their profile will be ready to browse jobs, apply and take quizzes right away.</p>
                        <div class="ac-side-points">
                            <div class="sp"><i class="fas fa-shield-halved"></i> Secure credential storage</div>
                            <div class="sp"><i class="fas fa-check-circle"></i> Email uniqueness enforced</div>
                            <div class="sp"><i class="fas fa-palette"></i> Optional profile photo</div>
                        </div>
                    </div>
                    <div style="position:relative;z-index:2;">
                        <a href="show_users.php" class="ac-back"><i class="fas fa-arrow-left"></i> Back to Users</a>
                    </div>
                </div>

                <!-- Form panel -->
                <div class="ac-form-side">
                    <h3 class="ac-form-title">New User</h3>
                    <p class="ac-form-sub">Fill in the details below to create the account.</p>

                    <?php if ($success): ?>
                        <div class="ac-alert ok"><i class="fas fa-check-circle"></i><?php echo htmlspecialchars($success); ?></div>
                    <?php endif; ?>
                    <?php if (count($errors) > 0): ?>
                        <?php foreach ($errors as $err): ?>
                            <div class="ac-alert err"><i class="fas fa-exclamation-circle"></i><?php echo htmlspecialchars($err); ?></div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="POST" enctype="multipart/form-data" id="acForm" onsubmit="return acValidate()">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="ac-field">
                                    <label for="acUsername"><i class="fas fa-user"></i>Full Name <span class="ac-req">*</span></label>
                                    <input type="text" class="ac-input" id="acUsername" name="username" placeholder="e.g. Tanvir Ahmed" value="<?php echo htmlspecialchars($old['username']); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="ac-field">
                                    <label for="acEmail"><i class="fas fa-envelope"></i>Email Address <span class="ac-req">*</span></label>
                                    <input type="email" class="ac-input" id="acEmail" name="email" placeholder="e.g. user@mail.com" value="<?php echo htmlspecialchars($old['email']); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="ac-field">
                                    <label for="acPhone"><i class="fas fa-phone"></i>Phone Number <span class="ac-req">*</span></label>
                                    <input type="tel" class="ac-input" id="acPhone" name="phone" placeholder="e.g. 01XXXXXXXXX" value="<?php echo htmlspecialchars($old['phone']); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="ac-field">
                                    <label for="acDegree"><i class="fas fa-graduation-cap"></i>Qualification</label>
                                    <input type="text" class="ac-input" id="acDegree" name="degree" placeholder="e.g. B.Sc in CSE" value="<?php echo htmlspecialchars($old['degree']); ?>">
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="ac-field">
                                    <label for="acSkills"><i class="fas fa-code"></i>Skills</label>
                                    <input type="text" class="ac-input" id="acSkills" name="skills" placeholder="e.g. PHP, MySQL, JavaScript" value="<?php echo htmlspecialchars($old['skills']); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="ac-field">
                                    <label for="acPassword"><i class="fas fa-lock"></i>Password <span class="ac-req">*</span></label>
                                    <input type="password" class="ac-input" id="acPassword" name="password" placeholder="Minimum 6 characters" required minlength="6">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="ac-field">
                                    <label for="acCpassword"><i class="fas fa-lock"></i>Confirm Password <span class="ac-req">*</span></label>
                                    <input type="password" class="ac-input" id="acCpassword" name="cpassword" placeholder="Repeat password" required minlength="6">
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="ac-field">
                                    <label><i class="fas fa-image"></i>Profile Photo <span style="color:var(--text-light);font-weight:500;">(optional)</span></label>
                                    <div class="ac-photo-wrap">
                                        <div class="ac-photo-preview" id="acPhotoPreview">
                                            <i class="fas fa-camera"></i>
                                            <span>Add Photo</span>
                                        </div>
                                        <button type="button" class="ac-input ac-file-input" style="max-width:260px;text-align:left;color:var(--text-muted);font-weight:600;">
                                            <i class="fas fa-cloud-upload-alt mr-2"></i>Choose image
                                            <input type="file" name="profile_image" id="acPhotoInput" accept="image/*">
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <button type="submit" name="submit" class="ac-btn"><i class="fas fa-user-plus"></i> Create Account</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    /* Live photo preview */
    var acPreview = document.getElementById('acPhotoPreview');
    var acInput = document.getElementById('acPhotoInput');
    if (acPreview && acInput) {
        acInput.addEventListener('change', function () {
            var file = acInput.files[0];
            if (!file) return;
            var reader = new FileReader();
            reader.onload = function (e) {
                acPreview.innerHTML = '<img src="' + e.target.result + '" alt="preview">';
                acPreview.classList.add('has-photo');
            };
            reader.readAsDataURL(file);
        });
    }

    /* Client-side validation */
    function acValidate() {
        var pass = document.getElementById('acPassword');
        var cpass = document.getElementById('acCpassword');
        var email = document.getElementById('acEmail');
        if (email && email.value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) {
            alert('Please enter a valid email address.');
            email.focus();
            return false;
        }
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
