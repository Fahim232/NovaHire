<?php
// Core setup: session, DB, BASE_URL, helpers
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../admin/dbcon.php';
require_once __DIR__ . '/../includes/header.php';

$id = $_SESSION['id'];
$selectquery = " select * from user_info where id='$id' ";
$query = mysqli_query($con, $selectquery);
$result = mysqli_fetch_assoc($query);

// Logic remains same, only UI changes
if (isset($_POST['btnUpdate'])) {
    $name = mysqli_real_escape_string($con, $_POST['name']);
    $phone = mysqli_real_escape_string($con, $_POST['phone']);
    $email = mysqli_real_escape_string($con, $_POST['email']);
    $pass = $_POST['password'];
    
    $update_clause = "username='$name', email='$email', phone='$phone'";
    
    if(!empty($pass)) {
        $passEncrypt = password_hash($pass, PASSWORD_BCRYPT);
        $update_clause .= ", password='$passEncrypt', cpassword='$passEncrypt'"; 
    }

    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK && $_FILES['profile_image']['size'] > 0) {
        $allowed_exts = ['jpg','jpeg','png','gif','webp'];
        $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed_exts)) {
            $upload_dir = __DIR__ . '/images/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $file_name = 'profile_' . time() . '_' . rand(1000,9999) . '.' . $ext;
            $target = $upload_dir . $file_name;
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $target)) {
                $update_clause .= ", profile='$file_name'";
            }
        }
    }

    $updatequery = " update user_info set $update_clause where id='$id' ";
    $uquery = mysqli_query($con, $updatequery);

    if ($uquery){
        // Refresh
        echo "<script>alert('Profile Updated Successfully'); window.location.href='profile.php';</script>";
        exit;
    } else {
        echo '<script>alert("Update Failed");</script>';
    }
}
?>
<style>
    /* ═══════════════════════════════════════════
       PROFILE PAGE — MODERN RESPONSIVE STYLES
       ═══════════════════════════════════════════ */

    .profile-wrap {
        max-width: 1140px;
        margin: 0 auto;
        padding: 100px 20px 40px;
    }

    /* ── Profile Hero Banner ── */
    .profile-hero {
        background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 50%, #a855f7 100%);
        border-radius: var(--radius-xl);
        padding: 50px 40px 40px;
        position: relative;
        overflow: hidden;
        margin-bottom: 28px;
    }
    .profile-hero::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -15%;
        width: 400px;
        height: 400px;
        background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%);
        border-radius: 50%;
    }
    .profile-hero::after {
        content: '';
        position: absolute;
        bottom: -40%;
        left: -10%;
        width: 300px;
        height: 300px;
        background: radial-gradient(circle, rgba(236,72,153,0.12) 0%, transparent 70%);
        border-radius: 50%;
    }
    .profile-hero-inner {
        position: relative;
        z-index: 2;
        display: flex;
        align-items: center;
        gap: 28px;
    }
    .profile-hero-avatar-wrap {
        position: relative;
        flex-shrink: 0;
    }
    .profile-hero-avatar {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        border: 4px solid rgba(255,255,255,0.3);
        object-fit: cover;
        background: rgba(255,255,255,0.15);
    }
    .profile-hero-avatar-placeholder {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        border: 4px solid rgba(255,255,255,0.3);
        background: rgba(255,255,255,0.15);
        display: flex;
        align-items: center;
        justify-content: center;
        color: rgba(255,255,255,0.6);
        font-size: 2.5rem;
    }
    .online-dot {
        position: absolute;
        bottom: 8px;
        right: 8px;
        width: 18px;
        height: 18px;
        background: #10b894;
        border: 3px solid rgba(79,70,229,1);
        border-radius: 50%;
    }
    .avatar-upload-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        border-radius: 50%;
        background: rgba(0,0,0,0.45);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        opacity: 0;
        transition: opacity 0.3s;
        cursor: pointer;
        z-index: 3;
    }
    .profile-hero-avatar-wrap:hover .avatar-upload-overlay {
        opacity: 1;
    }
    .avatar-upload-overlay i {
        color: white;
        font-size: 1.5rem;
        margin-bottom: 2px;
    }
    .avatar-upload-overlay span {
        color: rgba(255,255,255,0.85);
        font-size: 0.65rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .avatar-hidden-input { display: none; }
    .profile-hero-info h1 {
        color: white;
        font-size: 1.8rem;
        font-weight: 800;
        margin: 0 0 4px;
        letter-spacing: -0.5px;
    }
    .profile-hero-info .email {
        color: rgba(255,255,255,0.75);
        font-size: 0.95rem;
        font-weight: 500;
        margin: 0 0 14px;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .profile-hero-pills {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    .profile-pill {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 5px 14px;
        border-radius: 20px;
        font-size: 0.78rem;
        font-weight: 600;
        backdrop-filter: blur(8px);
    }
    .pill-active {
        background: rgba(16,185,129,0.2);
        color: #6ee7b7;
        border: 1px solid rgba(16,185,129,0.3);
    }
    .pill-role {
        background: rgba(255,255,255,0.15);
        color: rgba(255,255,255,0.9);
        border: 1px solid rgba(255,255,255,0.2);
    }
    .profile-hero-actions {
        margin-left: auto;
        display: flex;
        gap: 10px;
        flex-shrink: 0;
    }
    .btn-hero-cv {
        background: rgba(255,255,255,0.15);
        color: white;
        border: 1.5px solid rgba(255,255,255,0.25);
        padding: 10px 22px;
        border-radius: var(--radius-sm);
        font-weight: 600;
        font-size: 0.88rem;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 7px;
        transition: all 0.25s;
        backdrop-filter: blur(8px);
    }
    .btn-hero-cv:hover {
        background: rgba(255,255,255,0.25);
        color: white;
        text-decoration: none;
        transform: translateY(-2px);
    }

    /* ── Content Cards ── */
    .profile-card {
        background: var(--bg-card);
        border-radius: var(--radius-lg);
        border: 1px solid var(--border-light);
        box-shadow: var(--shadow-sm);
        overflow: hidden;
    }
    .profile-card-header {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 24px 28px;
        border-bottom: 1px solid var(--border-light);
    }
    .profile-card-icon {
        width: 44px;
        height: 44px;
        border-radius: var(--radius-md);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        flex-shrink: 0;
    }
    .profile-card-header h4 {
        margin: 0;
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--text);
    }
    .profile-card-header small {
        color: var(--text-muted);
        font-size: 0.85rem;
        font-weight: 500;
    }
    .profile-card-body {
        padding: 28px;
    }

    /* ── Form Styles ── */
    .form-group-pro label {
        font-size: 0.82rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--text-muted);
        font-weight: 700;
        margin-bottom: 8px;
        display: block;
    }
    .form-control-pro {
        border: 1.5px solid var(--border);
        background: var(--bg);
        border-radius: var(--radius-sm);
        padding: 14px 16px;
        height: auto;
        font-size: 0.95rem;
        color: var(--text);
        font-weight: 500;
        font-family: var(--font);
        transition: all 0.25s;
        width: 100%;
    }
    .form-control-pro:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(79,70,229,0.1);
        outline: none;
        background: var(--bg-card);
    }
    .form-control-pro::placeholder {
        color: var(--text-light);
    }
    .file-upload-zone {
        border: 2px dashed var(--border);
        border-radius: var(--radius-md);
        padding: 28px 20px;
        text-align: center;
        cursor: pointer;
        transition: all 0.25s;
        background: var(--bg);
    }
    .file-upload-zone:hover {
        border-color: var(--primary);
        background: rgba(79,70,229,0.03);
    }
    .file-upload-zone i {
        font-size: 1.8rem;
        color: var(--primary);
        margin-bottom: 8px;
        display: block;
    }
    .file-upload-zone span {
        color: var(--text-muted);
        font-size: 0.88rem;
        font-weight: 500;
    }
    .file-upload-zone .file-name {
        color: var(--primary);
        font-weight: 600;
        margin-top: 4px;
        font-size: 0.85rem;
    }
    .file-upload-zone input[type="file"] {
        display: none;
    }

    /* ── Buttons ── */
    .btn-pro-save {
        background: var(--primary);
        color: white;
        border: none;
        padding: 14px 32px;
        border-radius: var(--radius-sm);
        font-weight: 600;
        font-size: 0.92rem;
        cursor: pointer;
        transition: all 0.25s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .btn-pro-save:hover {
        background: var(--primary-dark);
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(79,70,229,0.3);
    }
    .btn-pro-cancel {
        background: none;
        color: var(--text-muted);
        border: 1.5px solid var(--border);
        padding: 14px 24px;
        border-radius: var(--radius-sm);
        font-weight: 600;
        font-size: 0.92rem;
        cursor: pointer;
        transition: all 0.25s;
    }
    .btn-pro-cancel:hover {
        background: var(--bg-hover);
        color: var(--text);
    }

    /* ── Quick Stats Row ── */
    .quick-stats {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 16px;
        margin-bottom: 28px;
    }
    .quick-stat-item {
        background: var(--bg-card);
        border: 1px solid var(--border-light);
        border-radius: var(--radius-md);
        padding: 20px;
        text-align: center;
        box-shadow: var(--shadow-xs);
        transition: all 0.25s;
    }
    .quick-stat-item:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }
    .quick-stat-item .stat-num {
        font-size: 1.6rem;
        font-weight: 800;
        color: var(--text);
        line-height: 1;
        margin-bottom: 4px;
    }
    .quick-stat-item .stat-label {
        font-size: 0.78rem;
        color: var(--text-muted);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }

    /* ── RESPONSIVE — TABLET ── */
    @media (max-width: 991px) {
        .profile-hero { padding: 40px 30px 35px; }
        .profile-hero-inner { flex-wrap: wrap; }
        .profile-hero-avatar, .profile-hero-avatar-placeholder { width: 100px; height: 100px; }
        .profile-hero-info h1 { font-size: 1.5rem; }
        .profile-hero-actions { margin-left: 0; width: 100%; }
        .btn-hero-cv { flex: 1; justify-content: center; }
    }

    /* ── RESPONSIVE — LANDSCAPE MOBILE ── */
    @media (max-width: 767px) {
        .profile-wrap { padding: 80px 16px 30px; }
        .profile-hero {
            padding: 36px 24px 30px;
            border-radius: var(--radius-lg);
            margin-bottom: 20px;
        }
        .profile-hero-inner { gap: 20px; }
        .profile-hero-avatar, .profile-hero-avatar-placeholder { width: 84px; height: 84px; font-size: 2rem; }
        .online-dot { width: 14px; height: 14px; border-width: 2px; bottom: 4px; right: 4px; }
        .profile-hero-info h1 { font-size: 1.35rem; }
        .profile-hero-info .email { font-size: 0.88rem; }
        .profile-pill { font-size: 0.73rem; padding: 4px 11px; }
        .profile-card-header { padding: 20px 22px; }
        .profile-card-body { padding: 22px; }
        .quick-stats { grid-template-columns: repeat(3, 1fr); gap: 10px; }
        .quick-stat-item { padding: 16px 12px; }
        .quick-stat-item .stat-num { font-size: 1.35rem; }
        .quick-stat-item .stat-label { font-size: 0.72rem; }
        .form-group-pro label { font-size: 0.78rem; }
        .form-control-pro { padding: 12px 14px; font-size: 0.9rem; }
    }

    /* ── RESPONSIVE — PORTRAIT MOBILE ── */
    @media (max-width: 575px) {
        .profile-wrap { padding: 70px 12px 24px; }
        .profile-hero {
            padding: 30px 18px 26px;
            border-radius: 14px;
        }
        .profile-hero-inner {
            flex-direction: column;
            text-align: center;
        }
        .profile-hero-avatar, .profile-hero-avatar-placeholder { width: 80px; height: 80px; }
        .profile-hero-info h1 { font-size: 1.25rem; }
        .profile-hero-info .email { justify-content: center; font-size: 0.82rem; }
        .profile-hero-pills { justify-content: center; }
        .profile-hero-actions {
            flex-direction: column;
            width: 100%;
        }
        .btn-hero-cv { width: 100%; justify-content: center; padding: 12px 18px; font-size: 0.85rem; }
        .quick-stats { grid-template-columns: repeat(3, 1fr); gap: 8px; }
        .quick-stat-item { padding: 14px 8px; border-radius: 10px; }
        .quick-stat-item .stat-num { font-size: 1.2rem; }
        .quick-stat-item .stat-label { font-size: 0.68rem; }
        .profile-card-header { padding: 18px 16px; gap: 10px; }
        .profile-card-icon { width: 38px; height: 38px; font-size: 0.95rem; }
        .profile-card-header h4 { font-size: 1rem; }
        .profile-card-header small { font-size: 0.8rem; }
        .profile-card-body { padding: 18px 16px; }
        .form-group-pro label { font-size: 0.75rem; margin-bottom: 6px; }
        .form-control-pro { padding: 11px 13px; font-size: 0.88rem; border-radius: 10px; }
        .file-upload-zone { padding: 22px 14px; border-radius: 12px; }
        .file-upload-zone i { font-size: 1.5rem; }
        .file-upload-zone span { font-size: 0.82rem; }
        .btn-pro-save { padding: 12px 24px; font-size: 0.88rem; width: 100%; justify-content: center; }
        .btn-pro-cancel { padding: 12px 20px; font-size: 0.88rem; }
    }

    /* ── VERY SMALL SCREENS ── */
    @media (max-width: 374px) {
        .profile-hero-avatar, .profile-hero-avatar-placeholder { width: 70px; height: 70px; }
        .profile-hero-info h1 { font-size: 1.1rem; }
        .quick-stat-item .stat-num { font-size: 1.1rem; }
        .profile-card-header h4 { font-size: 0.95rem; }
    }

    /* ── Animations ── */
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(16px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .anim-profile { animation: fadeInUp 0.5s ease-out both; }
    .anim-d1 { animation-delay: 0.1s; }
    .anim-d2 { animation-delay: 0.2s; }
    .anim-d3 { animation-delay: 0.3s; }
</style>

<div class="profile-wrap">

    <!-- Profile Hero Banner -->
    <div class="profile-hero anim-profile">
        <div class="profile-hero-inner">
            <div class="profile-hero-avatar-wrap" id="avatarWrap">
                <?php if(isset($result['profile']) && $result['profile']): ?>
                    <img src="./images/<?php echo htmlspecialchars($result['profile']); ?>" class="profile-hero-avatar" alt="Profile" id="heroAvatar">
                <?php else: ?>
                    <div class="profile-hero-avatar-placeholder" id="heroAvatar">
                        <i class="fas fa-user"></i>
                    </div>
                <?php endif; ?>
                <div class="avatar-upload-overlay" id="avatarOverlay">
                    <i class="fas fa-camera"></i>
                    <span>Change</span>
                </div>
                <div class="online-dot"></div>
                <input type="file" class="avatar-hidden-input" id="avatarFileInput" accept="image/*">
            </div>
            <div class="profile-hero-info">
                <h1><?php echo htmlspecialchars($result['username']); ?></h1>
                <p class="email"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($result['email']); ?></p>
                <div class="profile-hero-pills">
                    <span class="profile-pill pill-active"><i class="fas fa-circle" style="font-size:0.45rem;"></i> Active</span>
                    <span class="profile-pill pill-role"><i class="fas fa-briefcase"></i> Job Seeker</span>
                </div>
            </div>
            <div class="profile-hero-actions">
                <a href="view_cv.php" target="_blank" class="btn-hero-cv">
                    <i class="fas fa-id-badge"></i> View CV
                </a>
            </div>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="quick-stats anim-profile anim-d1">
        <?php
        $uid = $_SESSION['id'];
        $app_count = mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) as c FROM job_applications WHERE user_id=$uid"))['c'];
        $saved_count = mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) as c FROM saved_jobs WHERE user_id=$uid"))['c'];
        ?>
        <div class="quick-stat-item">
            <div class="stat-num"><?php echo $app_count; ?></div>
            <div class="stat-label">Applications</div>
        </div>
        <div class="quick-stat-item">
            <div class="stat-num"><?php echo $saved_count; ?></div>
            <div class="stat-label">Saved Jobs</div>
        </div>
        <div class="quick-stat-item">
            <div class="stat-num">-</div>
            <div class="stat-label">Interviews</div>
        </div>
    </div>

    <!-- Edit Profile Card -->
    <div class="profile-card anim-profile anim-d2">
        <div class="profile-card-header">
            <div class="profile-card-icon" style="background: rgba(79,70,229,0.1); color: var(--primary);">
                <i class="fas fa-user-edit"></i>
            </div>
            <div>
                <h4>Account Settings</h4>
                <small>Manage your personal information</small>
            </div>
        </div>
        <div class="profile-card-body">
            <form method="post" action="" enctype="multipart/form-data">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group-pro mb-4">
                            <label>Full Name</label>
                            <input type="text" name="name" class="form-control-pro" value="<?php echo htmlspecialchars($result['username']); ?>" required/>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group-pro mb-4">
                            <label>Phone Number</label>
                            <input type="text" name="phone" class="form-control-pro" value="<?php echo htmlspecialchars($result['phone']); ?>" required/>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group-pro mb-4">
                            <label>Email Address</label>
                            <input type="email" name="email" class="form-control-pro" value="<?php echo htmlspecialchars($result['email']); ?>" required/>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group-pro mb-4">
                            <label>Change Password <span style="text-transform:none; letter-spacing:0; font-weight:500; color:var(--text-light);">(Optional)</span></label>
                            <input type="password" name="password" class="form-control-pro" placeholder="Enter new password"/>
                        </div>
                    </div>
                </div>

                <div class="form-group-pro mb-4">
                    <label>Profile Picture</label>
                    <div class="file-upload-zone" id="fileZone">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <span>Click to upload or drag and drop</span>
                        <div class="file-name" id="fileName"></div>
                        <input type="file" name="profile_image" id="profileFile" accept="image/*">
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-3" style="gap: 12px; padding-top: 8px;">
                    <a href="seeker_dashboard.php" class="btn-pro-cancel">Cancel</a>
                    <button type="submit" name="btnUpdate" class="btn-pro-save">
                        <i class="fas fa-check"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

<script>
// Avatar click → opens file picker
document.getElementById('avatarOverlay').addEventListener('click', function(e) {
    e.stopPropagation();
    document.getElementById('avatarFileInput').click();
});

// Avatar file selected → preview + sync to form input
document.getElementById('avatarFileInput').addEventListener('change', function(e) {
    var file = e.target.files[0];
    if (!file) return;

    // Preview in hero avatar
    var reader = new FileReader();
    reader.onload = function(ev) {
        var heroAvatar = document.getElementById('heroAvatar');
        if (heroAvatar.tagName === 'IMG') {
            heroAvatar.src = ev.target.result;
        } else {
            var img = document.createElement('img');
            img.src = ev.target.result;
            img.className = 'profile-hero-avatar';
            img.id = 'heroAvatar';
            img.alt = 'Profile';
            heroAvatar.parentNode.replaceChild(img, heroAvatar);
        }
    };
    reader.readAsDataURL(file);

    // Sync to form file input
    var dataTransfer = new DataTransfer();
    dataTransfer.items.add(file);
    document.getElementById('profileFile').files = dataTransfer.files;
    document.getElementById('fileName').textContent = file.name;
});

// Form file upload zone click
document.getElementById('fileZone').addEventListener('click', function() {
    document.getElementById('profileFile').click();
});

// Form file selected → preview + show name
document.getElementById('profileFile').addEventListener('change', function() {
    var file = this.files[0];
    if (!file) return;
    document.getElementById('fileName').textContent = file.name;

    // Preview in hero avatar
    var reader = new FileReader();
    reader.onload = function(ev) {
        var heroAvatar = document.getElementById('heroAvatar');
        if (heroAvatar.tagName === 'IMG') {
            heroAvatar.src = ev.target.result;
        } else {
            var img = document.createElement('img');
            img.src = ev.target.result;
            img.className = 'profile-hero-avatar';
            img.id = 'heroAvatar';
            img.alt = 'Profile';
            heroAvatar.parentNode.replaceChild(img, heroAvatar);
        }
    };
    reader.readAsDataURL(file);
});

// Reset clears file name
document.querySelector('form').addEventListener('reset', function() {
    document.getElementById('fileName').textContent = '';
});
</script>
</body>
</html>
