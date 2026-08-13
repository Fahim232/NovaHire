<?php
session_start();
if (!isset($_SESSION['admin_username'])) {
    echo '<script>alert("You are logged out!"); window.location.href="admin_login.php";</script>';
    exit();
}

require_once 'dbcon.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);
if ($id <= 0) {
    echo '<script>alert("Invalid user id!"); window.location.href="show_users.php";</script>';
    exit();
}

function uu_initials($name) {
    $parts = preg_split('/\s+/', trim($name));
    $initials = '';
    foreach (array_slice($parts, 0, 2) as $p) { if ($p !== '') $initials .= strtoupper(mb_substr($p, 0, 1)); }
    return $initials !== '' ? $initials : '?';
}
function uu_avatar_style($id) {
    $grads = [
        'linear-gradient(135deg,#6366f1,#818cf8)',
        'linear-gradient(135deg,#8b5cf6,#a78bfa)',
        'linear-gradient(135deg,#0ea5e9,#38bdf8)',
        'linear-gradient(135deg,#10b981,#34d399)',
        'linear-gradient(135deg,#f59e0b,#fbbf24)',
        'linear-gradient(135deg,#ec4899,#f472b6)',
    ];
    return $grads[$id % count($grads)];
}
function uu_photo_exists($profile) {
    return $profile !== '' && is_file(__DIR__ . '/../images/' . $profile);
}

$result = null;
$q = mysqli_query($con, "SELECT * FROM user_info WHERE id='$id'");
if ($q) $result = mysqli_fetch_assoc($q);
if (!$result) {
    echo '<script>alert("User not found!"); window.location.href="show_users.php";</script>';
    exit();
}

$flash = null;
$flash_type = 'success';
$field_errors = array();

if (isset($_POST['btnUpdate'])) {
    $name = trim(mysqli_real_escape_string($con, $_POST['name'] ?? ''));
    $phone = trim(mysqli_real_escape_string($con, $_POST['phone'] ?? ''));
    $email = trim(mysqli_real_escape_string($con, $_POST['email'] ?? ''));

    if ($name === '') $field_errors['name'] = 'User name is required.';
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $field_errors['email'] = 'Please enter a valid email address.';

    if (!$field_errors) {
        $new_profile = $result['profile'];
        $upload_err = null;

        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['name'] !== '') {
            $file_name = basename($_FILES['profile_image']['name']);
            $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $allowed = array('jpg', 'jpeg', 'png', 'webp', 'gif');
            if (!in_array($ext, $allowed)) {
                $upload_err = 'Only JPG, PNG, WEBP and GIF images are allowed.';
            } elseif ($_FILES['profile_image']['error'] !== UPLOAD_ERR_OK) {
                $upload_err = 'Image upload failed. Please try again.';
            } else {
                $safe = 'user_' . $id . '_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['profile_image']['tmp_name'], __DIR__ . '/../images/' . $safe)) {
                    $new_profile = $safe;
                } else {
                    $upload_err = 'Could not save the image. Check folder permissions.';
                }
            }
        }

        if ($upload_err) {
            $flash = $upload_err;
            $flash_type = 'error';
        } else {
            $new_profile_esc = mysqli_real_escape_string($con, $new_profile);
            $u = mysqli_query($con, "UPDATE user_info SET username='$name', email='$email', phone='$phone', profile='$new_profile_esc' WHERE id='$id'");
            if ($u) {
                $flash = 'User profile updated successfully.';
                $q = mysqli_query($con, "SELECT * FROM user_info WHERE id='$id'");
                if ($q) $result = mysqli_fetch_assoc($q);
            } else {
                $flash = 'Update failed. Please try again.';
                $flash_type = 'error';
            }
        }
    }
}

include 'header.php';
?>
<style>
    @keyframes uu-reveal { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: none; } }
    .uu-reveal { opacity: 0; animation: uu-reveal .5s ease forwards; }
    .uu-d1 { animation-delay: .05s; } .uu-d2 { animation-delay: .12s; } .uu-d3 { animation-delay: .19s; }

    .uu-wrap { padding: 0 0 40px; }
    .uu-hero {
        position: relative;
        margin-top: -72px;
        padding: 96px 0 70px;
        background: linear-gradient(120deg, #4f46e5 0%, #7c3aed 55%, #0ea5e9 120%);
        overflow: hidden;
    }
    .uu-hero::before, .uu-hero::after { content: ''; position: absolute; border-radius: 50%; }
    .uu-hero::before { top: -120px; right: -60px; width: 360px; height: 360px; background: radial-gradient(circle, rgba(255,255,255,0.14) 0%, transparent 70%); }
    .uu-hero::after { bottom: -140px; left: 12%; width: 320px; height: 320px; background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%); }
    .uu-hero-inner { position: relative; z-index: 2; display: flex; align-items: flex-end; justify-content: space-between; flex-wrap: wrap; gap: 18px; }
    .uu-hero h1 { color: #fff; font-size: 2rem; font-weight: 800; letter-spacing: -0.5px; margin: 0 0 6px; }
    .uu-hero h1 i { font-size: 1.4rem; margin-right: 6px; opacity: .9; }
    .uu-hero .uu-hero-sub { color: rgba(255,255,255,0.82); margin: 0; font-size: .98rem; }

    .uu-card {
        background: var(--bg-card);
        border: 1px solid var(--border-light);
        border-radius: 20px;
        box-shadow: var(--shadow-sm);
        overflow: hidden;
        transition: box-shadow .3s ease;
    }
    .uu-card:hover { box-shadow: var(--shadow-md); }
    .uu-card-head {
        display: flex; align-items: center; justify-content: space-between;
        gap: 12px; flex-wrap: wrap;
        padding: 18px 22px;
        border-bottom: 1px solid var(--border-light);
    }
    .uu-card-head h5 { margin: 0; font-size: 1rem; font-weight: 700; display: flex; align-items: center; gap: 10px; }
    .uu-card-head h5 .uu-ico {
        width: 34px; height: 34px; border-radius: 10px;
        display: inline-flex; align-items: center; justify-content: center;
        font-size: .82rem; flex-shrink: 0;
    }

    .uu-avatar-lg {
        width: 84px; height: 84px; border-radius: 50%;
        display: inline-flex; align-items: center; justify-content: center;
        color: #fff; font-weight: 800; font-size: 1.8rem;
        object-fit: cover;
        box-shadow: 0 10px 24px -8px rgba(99,102,241,.5);
        border: 3px solid #fff;
    }
    [data-theme="dark"] .uu-avatar-lg { border-color: #1e293b; }

    .uu-label {
        font-size: .76rem; font-weight: 700; letter-spacing: .04em;
        text-transform: uppercase; color: var(--text-muted); margin: 0 0 8px;
        display: flex; align-items: center; gap: 6px;
    }
    .uu-label i { font-size: .82rem; }
    .uu-input {
        width: 100%;
        border: 1.5px solid var(--border-light);
        border-radius: 12px;
        background: var(--bg-card);
        color: var(--text);
        padding: 11px 14px;
        font-size: .9rem; font-weight: 600;
        outline: none; transition: border-color .2s, box-shadow .2s, background .2s;
    }
    .uu-input:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,.12); }
    .uu-input::placeholder { color: var(--text-muted); font-weight: 500; }
    .uu-input[disabled] { background: var(--bg-hover); color: var(--text-muted); cursor: not-allowed; }
    .uu-input-wrap { position: relative; }
    .uu-input-wrap .uu-icon {
        position: absolute; right: 13px; top: 50%; transform: translateY(-50%);
        color: var(--text-muted); font-size: .85rem; pointer-events: none;
    }
    .uu-err { color: #dc2626; font-size: .76rem; font-weight: 600; margin-top: 5px; display: flex; align-items: center; gap: 5px; }
    .uu-hint { color: var(--text-muted); font-size: .78rem; margin-top: 7px; display: flex; align-items: center; gap: 6px; }
    .uu-hint i { font-size: .82rem; }

    .uu-upload {
        border: 2px dashed var(--border-light);
        border-radius: 14px;
        padding: 18px;
        text-align: center;
        cursor: pointer;
        transition: border-color .2s, background .2s, transform .15s;
        background: var(--bg-hover);
    }
    .uu-upload:hover, .uu-upload.drag { border-color: #6366f1; background: rgba(99,102,241,.06); transform: translateY(-2px); }
    .uu-upload i { font-size: 1.6rem; color: #6366f1; opacity: .75; }
    .uu-upload b { color: var(--text); font-size: .9rem; display: block; margin-top: 6px; }
    .uu-upload span { color: var(--text-muted); font-size: .76rem; }

    .uu-btn {
        display: inline-flex; align-items: center; justify-content: center; gap: 8px;
        padding: 12px 24px; border-radius: 12px;
        font-size: .88rem; font-weight: 700; border: 0;
        transition: all .25s ease; text-decoration: none; white-space: nowrap;
    }
    .uu-btn:hover { text-decoration: none; transform: translateY(-2px); }
    .uu-btn-save {
        color: #fff; background: linear-gradient(135deg, #6366f1, #8b5cf6);
        box-shadow: 0 8px 18px -8px rgba(99,102,241,.6);
    }
    .uu-btn-save:hover { color: #fff; box-shadow: 0 12px 24px -8px rgba(99,102,241,.8); }
    .uu-btn-cancel {
        color: var(--text); background: var(--bg-hover);
        border: 1px solid var(--border-light);
    }
    .uu-btn-cancel:hover { color: var(--text); background: var(--border-light); }
    .uu-btn:disabled { opacity: .7; cursor: not-allowed; transform: none !important; }

    .uu-toast {
        position: fixed; top: 88px; right: 22px; z-index: 2000;
        min-width: 320px; max-width: 420px;
        background: var(--bg-card); border: 1px solid var(--border-light);
        border-radius: 14px; padding: 14px 16px;
        box-shadow: 0 22px 55px -18px rgba(15,23,42,.4);
        display: flex; align-items: flex-start; gap: 12px;
        animation: uu-in .35s cubic-bezier(.21,1.02,.73,1);
        transition: opacity .3s, transform .3s;
    }
    .uu-toast.out { opacity: 0; transform: translateX(40px); }
    .uu-toast .ic {
        width: 38px; height: 38px; border-radius: 11px;
        display: flex; align-items: center; justify-content: center;
        color: #fff; font-size: 1rem; flex-shrink: 0;
    }
    .uu-toast.success .ic { background: linear-gradient(135deg,#10b981,#059669); }
    .uu-toast.error .ic { background: linear-gradient(135deg,#ef4444,#dc2626); }
    .uu-toast b { display: block; color: var(--text); font-size: .9rem; font-weight: 800; }
    .uu-toast span { color: var(--text-muted); font-size: .8rem; display: block; margin-top: 2px; }
    .uu-toast .close { margin-left: auto; background: none; border: 0; color: var(--text-muted); font-size: .85rem; flex-shrink: 0; }

    @keyframes uu-in { from { opacity: 0; transform: translateX(46px); } to { opacity: 1; transform: translateX(0); } }

    @media (max-width: 767px) {
        .uu-hero { padding: 84px 0 56px; }
        .uu-hero h1 { font-size: 1.5rem; }
        .uu-toast { left: 16px; right: 16px; min-width: 0; }
    }
</style>

<div class="uu-wrap">
    <div class="uu-hero">
        <div class="container">
            <div class="uu-hero-inner">
                <div>
                    <h1><i class="fas fa-user-edit"></i>Edit User</h1>
                    <p class="uu-hero-sub">Update profile details for user #<?php echo (int)$id; ?>.</p>
                </div>
                <a href="show_users.php" class="uu-btn uu-btn-cancel" style="color:#fff;background:rgba(255,255,255,.16);border-color:rgba(255,255,255,.35);">
                    <i class="fas fa-arrow-left"></i> Back to Users
                </a>
            </div>
        </div>
    </div>

    <div class="container" style="margin-top: -34px;">

        <?php if ($flash): ?>
            <div class="uu-toast <?php echo $flash_type; ?> uu-reveal" id="uuToast">
                <div class="ic"><i class="fas <?php echo $flash_type === 'success' ? 'fa-check' : 'fa-times'; ?>"></i></div>
                <div><b><?php echo $flash_type === 'success' ? 'Success' : 'Failed'; ?></b><span><?php echo $flash; ?></span></div>
                <button type="button" class="close" onclick="this.closest('.uu-toast').classList.add('out'); setTimeout(()=>this.closest('.uu-toast').remove(),300);">&times;</button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-4 mb-3 uu-reveal uu-d1">
                <div class="uu-card" style="height:100%;">
                    <div class="uu-card-head">
                        <h5><span class="uu-ico" style="background:rgba(79,70,229,.1);color:var(--primary);"><i class="fas fa-id-badge"></i></span>Profile</h5>
                    </div>
                    <div class="p-4 text-center">
                        <?php if (uu_photo_exists($result['profile'])): ?>
                            <img class="uu-avatar-lg" id="uuAvatar" src="../images/<?php echo htmlspecialchars($result['profile']); ?>" alt="avatar">
                        <?php else: ?>
                            <span class="uu-avatar-lg" id="uuAvatar" style="background:<?php echo uu_avatar_style((int)$id); ?>;"><?php echo htmlspecialchars(uu_initials($result['username'])); ?></span>
                        <?php endif; ?>
                        <h5 style="color:var(--text);font-weight:800;margin:14px 0 2px;"><?php echo htmlspecialchars(trim($result['username'])); ?></h5>
                        <div style="color:var(--text-muted);font-size:.86rem;">
                            <i class="fas fa-user-tag mr-1"></i>Job Seeker
                        </div>
                        <div class="mt-3 d-flex flex-column align-items-start" style="gap:6px;text-align:left;">
                            <?php if ($result['user_degree'] !== ''): ?>
                                <span class="badge" style="background:rgba(139,92,246,.12);color:#7c3aed;padding:6px 12px;font-size:.75rem;font-weight:700;">
                                    <i class="fas fa-graduation-cap mr-1"></i><?php echo htmlspecialchars($result['user_degree']); ?>
                                </span>
                            <?php endif; ?>
                            <?php if ($result['user_skills'] !== ''): ?>
                                <span class="badge" style="background:rgba(16,185,129,.12);color:#059669;padding:6px 12px;font-size:.75rem;font-weight:700;">
                                    <i class="fas fa-code mr-1"></i><?php echo htmlspecialchars($result['user_skills']); ?>
                                </span>
                            <?php endif; ?>
                            <?php if ($result['experience'] !== ''): ?>
                                <span class="badge" style="background:rgba(245,158,11,.14);color:#b45309;padding:6px 12px;font-size:.75rem;font-weight:700;">
                                    <i class="fas fa-briefcase mr-1"></i>Has experience
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-8 uu-reveal uu-d2">
                <div class="uu-card">
                    <div class="uu-card-head">
                        <h5><span class="uu-ico" style="background:rgba(16,185,129,.1);color:#059669;"><i class="fas fa-sliders-h"></i></span>Account Details</h5>
                        <span style="font-size:.76rem;color:var(--text-muted);font-weight:600;">
                            <i class="fas fa-fingerprint mr-1"></i>ID: #<?php echo (int)$id; ?>
                        </span>
                    </div>
                    <div class="p-4">
                        <form method="post" action="" enctype="multipart/form-data" id="uuForm" novalidate>
                            <input type="hidden" name="id" value="<?php echo (int)$id; ?>">

                            <div class="form-group mb-4">
                                <label class="uu-label"><i class="fas fa-user"></i> User Name <span class="text-danger">*</span></label>
                                <div class="uu-input-wrap">
                                    <input type="text" name="name" id="uuName" class="uu-input" value="<?php echo htmlspecialchars($result['username']); ?>" required maxlength="100">
                                    <span class="uu-icon"><i class="fas fa-user-pen"></i></span>
                                </div>
                                <?php if (isset($field_errors['name'])): ?>
                                    <div class="uu-err"><i class="fas fa-exclamation-circle"></i><?php echo $field_errors['name']; ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="row">
                                <div class="col-md-6 form-group mb-4">
                                    <label class="uu-label"><i class="fas fa-phone"></i> Phone Number</label>
                                    <div class="uu-input-wrap">
                                        <input type="text" name="phone" id="uuPhone" class="uu-input" value="<?php echo htmlspecialchars($result['phone'] ?? ''); ?>" maxlength="20" placeholder="e.g. 017XXXXXXXX">
                                        <span class="uu-icon"><i class="fas fa-phone-alt"></i></span>
                                    </div>
                                </div>
                                <div class="col-md-6 form-group mb-4">
                                    <label class="uu-label"><i class="fas fa-envelope"></i> Email Address</label>
                                    <div class="uu-input-wrap">
                                        <input type="email" name="email" id="uuEmail" class="uu-input" value="<?php echo htmlspecialchars($result['email'] ?? ''); ?>" maxlength="120" placeholder="user@example.com">
                                        <span class="uu-icon"><i class="fas fa-at"></i></span>
                                    </div>
                                    <?php if (isset($field_errors['email'])): ?>
                                        <div class="uu-err"><i class="fas fa-exclamation-circle"></i><?php echo $field_errors['email']; ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="form-group mb-4">
                                <label class="uu-label"><i class="fas fa-lock"></i> Password</label>
                                <div class="uu-input-wrap">
                                    <input type="password" class="uu-input" placeholder="Password cannot be changed by admin" disabled>
                                    <span class="uu-icon"><i class="fas fa-lock"></i></span>
                                </div>
                                <div class="uu-hint"><i class="fas fa-shield-alt"></i> Passwords are managed by the user. Admins cannot view or reset them.</div>
                            </div>

                            <div class="form-group mb-4">
                                <label class="uu-label"><i class="fas fa-image"></i> Profile Photo</label>
                                <label class="uu-upload d-block" id="uuDrop" for="uuFile">
                                    <input type="file" name="profile_image" id="uuFile" accept="image/*" style="display:none;">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <b>Click or drag an image here</b>
                                    <span>JPG, PNG, WEBP or GIF — leave empty to keep the current photo</span>
                                </label>
                                <div class="uu-hint"><i class="fas fa-info-circle"></i> A new photo replaces the current one.</div>
                            </div>

                            <div class="d-flex flex-wrap" style="gap:10px;">
                                <button type="submit" name="btnUpdate" class="uu-btn uu-btn-save" id="uuSaveBtn">
                                    <i class="fas fa-save"></i> Save Changes
                                </button>
                                <a href="show_users.php" class="uu-btn uu-btn-cancel" id="uuCancelBtn">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="text-center" style="padding: 20px 0 6px; color: var(--text-muted); font-size: .82rem;">
            NovaHire Admin &middot; Edit User #<?php echo (int)$id; ?>
        </div>
    </div>
</div>

<script>
(function () {
    var toast = document.getElementById('uuToast');
    if (toast) setTimeout(function () {
        toast.classList.add('out');
        setTimeout(function () { toast.remove(); }, 4000);
    }, 6000);

    var avatar = document.getElementById('uuAvatar');
    var file = document.getElementById('uuFile');
    var drop = document.getElementById('uuDrop');

    if (file && avatar) {
        file.addEventListener('change', function () {
            if (this.files && this.files[0]) {
                var reader = new FileReader();
                reader.onload = function (e) {
                    if (avatar.tagName === 'IMG') avatar.src = e.target.result;
                    else {
                        var img = document.createElement('img');
                        img.src = e.target.result;
                        img.id = 'uuAvatar';
                        img.className = 'uu-avatar-lg';
                        img.alt = 'avatar';
                        avatar.parentNode.replaceChild(img, avatar);
                    }
                };
                reader.readAsDataURL(this.files[0]);
            }
        });
        ['dragenter', 'dragover'].forEach(function (ev) {
            drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.add('drag'); });
        });
        ['dragleave', 'drop'].forEach(function (ev) {
            drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.remove('drag'); });
        });
        drop.addEventListener('drop', function (e) {
            if (e.dataTransfer.files && e.dataTransfer.files[0]) {
                file.files = e.dataTransfer.files;
                file.dispatchEvent(new Event('change'));
            }
        });
    }

    var form = document.getElementById('uuForm');
    var saveBtn = document.getElementById('uuSaveBtn');
    if (form) {
        form.addEventListener('submit', function () {
            var name = document.getElementById('uuName');
            if (name && name.value.trim() === '') {
                alert('User name is required.');
                name.focus();
                return false;
            }
            saveBtn.disabled = true;
            saveBtn.querySelector('i').className = 'fas fa-spinner fa-spin';
        });
    }

    var cancel = document.getElementById('uuCancelBtn');
    if (cancel) {
        cancel.addEventListener('click', function (e) {
            if (document.referrer && document.referrer.indexOf('show_users.php') > -1) {
                e.preventDefault();
                window.history.back();
            }
        });
    }
})();
</script>

</body>
</html>
