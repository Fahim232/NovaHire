<?php
    session_start();
    include '../admin/dbcon.php';

    // Check if company is logged in
    if (!isset($_SESSION['company_id'])) {
        header('Location: ../company_login.php');
        exit;
    }

    $company_id = $_SESSION['company_id'];

    // Fetch company details
    $company_query = "SELECT * FROM companies WHERE id = $company_id";
    $company_result = mysqli_query($con, $company_query);
    $company = mysqli_fetch_assoc($company_result);

    $flash_message = '';
    $flash_type = '';

    // Update profile
    if (isset($_POST['update_profile'])) {
        $company_name = mysqli_real_escape_string($con, trim($_POST['company_name']));
        $phone = mysqli_real_escape_string($con, trim($_POST['phone']));
        $address = mysqli_real_escape_string($con, trim($_POST['address']));
        $website = mysqli_real_escape_string($con, trim($_POST['website']));
        $industry = mysqli_real_escape_string($con, trim($_POST['industry']));
        $company_size = mysqli_real_escape_string($con, trim($_POST['company_size']));
        $description = mysqli_real_escape_string($con, trim($_POST['description']));

        // Handle logo upload
        $logo_name = $company['logo']; // Keep existing logo by default (bare filename)
        if (isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] == 0) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp'];
            $max_size = 2 * 1024 * 1024; // 2MB

            if (in_array($_FILES['company_logo']['type'], $allowed_types) && $_FILES['company_logo']['size'] <= $max_size) {
                $upload_dir = '../uploads/company_logos/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                $file_extension = pathinfo($_FILES['company_logo']['name'], PATHINFO_EXTENSION);
                $new_filename = 'company_' . $company_id . '_' . time() . '.' . $file_extension;
                $target_path = $upload_dir . $new_filename;

                if (move_uploaded_file($_FILES['company_logo']['tmp_name'], $target_path)) {
                    // Delete old logo if exists (bare filename)
                    $old_path = $upload_dir . $company['logo'];
                    if (!empty($company['logo']) && file_exists($old_path)) {
                        @unlink($old_path);
                    }
                    $logo_name = $new_filename; // store bare filename (site convention)
                } else {
                    $flash_message = 'Failed to upload logo. Please check folder permissions.';
                    $flash_type = 'danger';
                }
            } else {
                $flash_message = 'Invalid file. Please upload an image (JPG, PNG, GIF, WEBP) under 2MB.';
                $flash_type = 'danger';
            }
        }

        if (empty($flash_message)) {
            $update_query = "UPDATE companies SET
                            company_name = '$company_name',
                            company_phone = '$phone',
                            company_address = '$address',
                            company_website = '$website',
                            industry = '$industry',
                            company_size = '$company_size',
                            description = '$description',
                            logo = '$logo_name'
                            WHERE id = $company_id";

            if (mysqli_query($con, $update_query)) {
                $_SESSION['company_name'] = $company_name;
                $_SESSION['company_logo'] = $logo_name;
                $flash_message = 'Profile updated successfully!';
                $flash_type = 'success';
                // Refresh company data
                $company_result = mysqli_query($con, $company_query);
                $company = mysqli_fetch_assoc($company_result);
            } else {
                $flash_message = 'Failed to update profile.';
                $flash_type = 'danger';
            }
        }
    }

    // Change password
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        if (password_verify($current_password, $company['password'])) {
            if ($new_password === $confirm_password) {
                if (strlen($new_password) >= 6) {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $password_query = "UPDATE companies SET password = '$hashed_password' WHERE id = $company_id";
                    if (mysqli_query($con, $password_query)) {
                        $flash_message = 'Password changed successfully!';
                        $flash_type = 'success';
                    } else {
                        $flash_message = 'Failed to change password.';
                        $flash_type = 'danger';
                    }
                } else {
                    $flash_message = 'New password must be at least 6 characters.';
                    $flash_type = 'danger';
                }
            } else {
                $flash_message = 'New passwords do not match.';
                $flash_type = 'danger';
            }
        } else {
            $flash_message = 'Current password is incorrect.';
            $flash_type = 'danger';
        }
    }

    // Profile stats
    $stats = ['jobs' => 0, 'active' => 0, 'applications' => 0, 'category_apps' => 0];
    $sjobs = mysqli_query($con, "SELECT COUNT(*) c, SUM(status = 'active') act FROM company_jobs WHERE company_id = $company_id");
    if ($sjobs) { $jr = mysqli_fetch_assoc($sjobs); $stats['jobs'] = intval($jr['c']); $stats['active'] = intval($jr['act']); }
    $sapps = mysqli_query($con, "SELECT COUNT(*) c FROM job_applications ja JOIN company_jobs cj ON ja.job_id = cj.id WHERE cj.company_id = $company_id");
    if ($sapps) $stats['applications'] = intval(mysqli_fetch_assoc($sapps)['c']);
    $scat = mysqli_query($con, "SELECT COUNT(*) c FROM category_applications WHERE company_id = $company_id");
    if ($scat) $stats['category_apps'] = intval(mysqli_fetch_assoc($scat)['c']);

    $logo_file = $company['logo'];
    if (!empty($logo_file)) {
        $logo_path = '../uploads/company_logos/' . basename($logo_file);
        $logo_ok = file_exists($logo_path);
    } else {
        $logo_path = '';
        $logo_ok = false;
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Company Profile | Dashboard</title>
    <?php include '../includes/links.php'; ?>
    <style>
        :root {
            --pp-bg: #f4f6fb;
            --pp-card: #ffffff;
            --pp-border: #e5e9f2;
            --pp-text: #1e293b;
            --pp-muted: #64748b;
            --pp-primary: #4f46e5;
            --pp-primary-2: #7c3aed;
            --pp-soft: #eef2ff;
            --pp-input: #f8fafc;
            --pp-shadow: 0 10px 30px rgba(15, 23, 42, 0.07);
        }
        [data-theme="dark"] {
            --pp-bg: #0f172a;
            --pp-card: #111827;
            --pp-border: #28334a;
            --pp-text: #e8edff;
            --pp-muted: #94a3b8;
            --pp-primary: #8b5cf6;
            --pp-primary-2: #a78bfa;
            --pp-soft: #1e293b;
            --pp-input: #0d1526;
            --pp-shadow: 0 10px 30px rgba(0, 0, 0, 0.45);
        }

        body {
            background:
                radial-gradient(circle at 8% 12%, rgba(99, 102, 241, 0.10), transparent 28%),
                radial-gradient(circle at 92% 8%, rgba(217, 70, 239, 0.08), transparent 26%),
                var(--pp-bg);
            color: var(--pp-text);
            min-height: 100vh;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }

        .pp-wrap { max-width: 1080px; margin: 0 auto; padding: 34px 24px 60px; }

        /* ── Hero ── */
        .pp-hero {
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 55%, #a855f7 100%);
            border-radius: 22px;
            padding: 30px 34px;
            color: #fff;
            box-shadow: 0 20px 40px rgba(79, 70, 229, 0.28);
            display: flex; align-items: center; gap: 24px;
            flex-wrap: wrap;
        }
        .pp-hero::before {
            content: '';
            position: absolute;
            right: -80px; top: -80px;
            width: 260px; height: 260px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.10);
        }
        .pp-hero::after {
            content: '';
            position: absolute;
            right: 60px; bottom: -110px;
            width: 220px; height: 220px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.08);
        }
        .pp-hero-logo {
            width: 96px; height: 96px;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.16);
            border: 2px solid rgba(255, 255, 255, 0.3);
            display: flex; align-items: center; justify-content: center;
            overflow: hidden;
            flex-shrink: 0;
            box-shadow: 0 10px 24px rgba(0, 0, 0, 0.2);
        }
        .pp-hero-logo img { width: 100%; height: 100%; object-fit: cover; }
        .pp-hero-logo i { font-size: 2.4rem; color: rgba(255, 255, 255, 0.9); }
        .pp-hero-txt { flex: 1; min-width: 220px; position: relative; z-index: 1; }
        .pp-hero-txt h1 { font-weight: 800; font-size: 1.8rem; color: #fff; margin: 0 0 6px; }
        .pp-hero-txt .pp-email { color: rgba(255, 255, 255, 0.9); margin: 0; font-size: 0.95rem; }
        .pp-hero-txt .pp-since { color: rgba(255, 255, 255, 0.7); margin: 10px 0 0; font-size: 0.8rem; font-weight: 600; }
        .pp-hero-badges { position: relative; z-index: 1; display: flex; gap: 10px; flex-wrap: wrap; }
        .pp-hbadge {
            background: rgba(255, 255, 255, 0.16);
            border: 1px solid rgba(255, 255, 255, 0.28);
            padding: 10px 18px;
            border-radius: 14px;
            text-align: center;
            min-width: 110px;
        }
        .pp-hbadge b { display: block; font-size: 1.35rem; font-weight: 800; line-height: 1.1; }
        .pp-hbadge span { font-size: 0.68rem; text-transform: uppercase; letter-spacing: .5px; opacity: .85; font-weight: 600; }

        /* ── Stats ── */
        .pp-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-top: 22px; }
        .pp-stat {
            background: var(--pp-card);
            border: 1px solid var(--pp-border);
            border-radius: 16px;
            padding: 18px 20px;
            display: flex;
            align-items: center;
            gap: 14px;
            box-shadow: var(--pp-shadow);
            transition: transform .2s ease, box-shadow .2s ease;
        }
        .pp-stat:hover { transform: translateY(-4px); box-shadow: 0 18px 38px rgba(79, 70, 229, 0.14); }
        .pp-stat-ico {
            width: 46px; height: 46px;
            border-radius: 13px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.15rem;
            flex-shrink: 0;
        }
        .pp-stat b { display: block; font-size: 1.45rem; line-height: 1.1; color: var(--pp-text); }
        .pp-stat span { font-size: 0.72rem; color: var(--pp-muted); font-weight: 600; text-transform: uppercase; letter-spacing: .4px; }

        /* ── Tabs ── */
        .pp-tabs {
            display: flex; gap: 8px; flex-wrap: wrap;
            margin: 26px 0 20px;
            background: var(--pp-card);
            border: 1px solid var(--pp-border);
            padding: 8px;
            border-radius: 16px;
            box-shadow: var(--pp-shadow);
        }
        .pp-tab {
            flex: 1;
            display: inline-flex; align-items: center; justify-content: center; gap: 9px;
            padding: 13px 18px;
            border-radius: 12px;
            border: none;
            background: transparent;
            color: var(--pp-muted);
            font-weight: 700; font-size: 0.9rem;
            cursor: pointer;
            transition: all .2s ease;
            white-space: nowrap;
        }
        .pp-tab:hover { color: var(--pp-primary); background: var(--pp-soft); }
        .pp-tab.active {
            background: linear-gradient(135deg, var(--pp-primary), var(--pp-primary-2));
            color: #fff;
            box-shadow: 0 8px 20px rgba(79, 70, 229, 0.3);
        }

        /* ── Panel ── */
        .pp-panel { display: none; }
        .pp-panel.active { display: block; animation: ppFade .3s ease; }
        @keyframes ppFade { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        .pp-card {
            background: var(--pp-card);
            border: 1px solid var(--pp-border);
            border-radius: 18px;
            padding: 26px;
            box-shadow: var(--pp-shadow);
        }
        .pp-card-title {
            font-size: 1.15rem; font-weight: 800;
            color: var(--pp-text);
            margin: 0 0 20px;
            display: flex; align-items: center; gap: 10px;
        }
        .pp-card-title i {
            width: 38px; height: 38px;
            border-radius: 11px;
            background: linear-gradient(135deg, var(--pp-primary), var(--pp-primary-2));
            color: #fff;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 0.95rem;
        }

        /* Info grid */
        .pp-info-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; }
        .pp-info {
            background: var(--pp-soft);
            border: 1px solid var(--pp-border);
            border-radius: 14px;
            padding: 16px 18px;
        }
        .pp-info span { display: flex; align-items: center; gap: 9px; font-size: 0.74rem; color: var(--pp-muted); font-weight: 700; text-transform: uppercase; letter-spacing: .4px; margin-bottom: 8px; }
        .pp-info span i { color: var(--pp-primary); width: 16px; text-align: center; }
        .pp-info p { margin: 0; font-size: 0.93rem; font-weight: 600; color: var(--pp-text); word-break: break-word; }
        .pp-info a { color: var(--pp-primary); font-weight: 600; }
        .pp-info.full { grid-column: 1 / -1; }
        .pp-logo-preview {
            display: inline-block;
            background: var(--pp-card);
            border: 1px dashed var(--pp-border);
            border-radius: 12px;
            padding: 10px;
            max-width: 220px;
        }
        .pp-logo-preview img { max-width: 200px; max-height: 90px; object-fit: contain; }

        /* Form */
        .pp-field { margin-bottom: 18px; }
        .pp-field label { display: flex; align-items: center; gap: 8px; font-weight: 700; font-size: 0.86rem; color: var(--pp-text); margin-bottom: 8px; }
        .pp-field label i { color: var(--pp-primary); width: 16px; text-align: center; }
        .pp-field small { color: var(--pp-muted); display: block; margin-top: 6px; font-size: 0.74rem; }
        .pp-input, .pp-select, .pp-textarea {
            width: 100%;
            background: var(--pp-input);
            border: 1.5px solid var(--pp-border);
            color: var(--pp-text);
            border-radius: 12px;
            padding: 12px 15px;
            font-size: 0.92rem;
            font-family: inherit;
            outline: none;
            transition: border-color .2s ease, box-shadow .2s ease;
        }
        .pp-input:focus, .pp-select:focus, .pp-textarea:focus {
            border-color: var(--pp-primary);
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.15);
        }
        .pp-textarea { resize: vertical; min-height: 90px; }
        .pp-char { text-align: right; font-size: 0.72rem; color: var(--pp-muted); margin-top: 4px; }
        .pp-form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 0 20px; }

        .pp-upload {
            border: 2px dashed var(--pp-border);
            border-radius: 14px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            background: var(--pp-soft);
            transition: all .2s ease;
            position: relative;
        }
        .pp-upload:hover { border-color: var(--pp-primary); background: rgba(99, 102, 241, 0.06); }
        .pp-upload i { font-size: 1.6rem; color: var(--pp-primary); display: block; margin-bottom: 8px; }
        .pp-upload b { display: block; font-size: 0.9rem; color: var(--pp-text); }
        .pp-upload small { color: var(--pp-muted); font-size: 0.75rem; }
        .pp-upload input { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
        .pp-upload-preview {
            display: none;
            margin-top: 14px;
            text-align: center;
            background: var(--pp-card);
            border: 1px solid var(--pp-border);
            border-radius: 12px;
            padding: 12px;
        }
        .pp-upload-preview img { max-width: 200px; max-height: 90px; object-fit: contain; }
        .pp-upload-preview.show { display: block; animation: ppFade .3s ease; }

        .pp-submit {
            background: linear-gradient(135deg, var(--pp-primary), var(--pp-primary-2));
            border: none; color: #fff;
            padding: 14px 34px;
            border-radius: 13px;
            font-weight: 700; font-size: 0.95rem;
            display: inline-flex; align-items: center; gap: 10px;
            cursor: pointer;
            box-shadow: 0 8px 20px rgba(79, 70, 229, 0.32);
            transition: all .18s ease;
        }
        .pp-submit:hover { transform: translateY(-2px); box-shadow: 0 14px 30px rgba(79, 70, 229, 0.4); }
        .pp-submit:active { transform: translateY(0); }

        /* Password */
        .pp-pass-wrap { position: relative; }
        .pp-eye {
            position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
            background: none; border: none; color: var(--pp-muted);
            cursor: pointer; font-size: 0.95rem;
        }
        .pp-eye:hover { color: var(--pp-primary); }
        .pp-strength { height: 6px; border-radius: 6px; background: var(--pp-border); margin-top: 8px; overflow: hidden; }
        .pp-strength-bar { height: 100%; width: 0; border-radius: 6px; transition: width .3s ease, background .3s ease; }
        .pp-strength-txt { font-size: 0.72rem; color: var(--pp-muted); margin-top: 5px; font-weight: 600; }

        /* Toast */
        .pp-toast {
            position: fixed;
            top: 90px; right: 24px;
            z-index: 1200;
            display: flex; align-items: center; gap: 12px;
            background: var(--pp-card);
            border: 1px solid var(--pp-border);
            border-left: 4px solid #10b981;
            border-radius: 13px;
            padding: 14px 18px;
            box-shadow: 0 18px 44px rgba(15, 23, 42, 0.2);
            font-size: 0.9rem; font-weight: 600; color: var(--pp-text);
            animation: ppToastIn .35s ease;
        }
        .pp-toast.danger { border-left-color: #ef4444; }
        @keyframes ppToastIn { from { transform: translateX(60px); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

        @media (max-width: 768px) {
            .pp-stats { grid-template-columns: repeat(2, 1fr); }
            .pp-info-grid { grid-template-columns: 1fr; }
            .pp-form-grid { grid-template-columns: 1fr; }
            .pp-hero { flex-direction: column; align-items: flex-start; }
        }
        @media (max-width: 480px) {
            .pp-stats { grid-template-columns: repeat(2, 1fr); }
            .pp-hero-badges { width: 100%; }
            .pp-hbadge { flex: 1; }
        }
    </style>
</head>
<body>
    <?php include 'company_header.php'; ?>

    <div class="pp-wrap">
        <!-- Hero -->
        <div class="pp-hero">
            <div class="pp-hero-logo">
                <?php if ($logo_ok): ?>
                    <img src="<?php echo htmlspecialchars($logo_path); ?>" alt="<?php echo htmlspecialchars($company['company_name']); ?>">
                <?php else: ?>
                    <i class="fas fa-building"></i>
                <?php endif; ?>
            </div>
            <div class="pp-hero-txt">
                <h1><?php echo htmlspecialchars($company['company_name']); ?></h1>
                <p class="pp-email"><i class="fas fa-envelope mr-2"></i><?php echo htmlspecialchars($company['company_email']); ?></p>
                <p class="pp-since"><i class="far fa-calendar-alt mr-2"></i>Member since <?php echo date('M Y', strtotime($company['registration_date'])); ?></p>
            </div>
            <div class="pp-hero-badges">
                <div class="pp-hbadge"><b><?php echo $stats['jobs']; ?></b><span>Jobs</span></div>
                <div class="pp-hbadge"><b><?php echo $stats['active']; ?></b><span>Active</span></div>
                <div class="pp-hbadge"><b><?php echo $stats['applications']; ?></b><span>Applications</span></div>
            </div>
        </div>

        <!-- Stats -->
        <div class="pp-stats">
            <div class="pp-stat">
                <div class="pp-stat-ico" style="background: rgba(99,102,241,.12); color:#6366f1;"><i class="fas fa-briefcase"></i></div>
                <div><b><?php echo $stats['jobs']; ?></b><span>Total Jobs</span></div>
            </div>
            <div class="pp-stat">
                <div class="pp-stat-ico" style="background: rgba(16,185,129,.12); color:#10b981;"><i class="fas fa-circle-check"></i></div>
                <div><b><?php echo $stats['active']; ?></b><span>Active Jobs</span></div>
            </div>
            <div class="pp-stat">
                <div class="pp-stat-ico" style="background: rgba(59,130,246,.12); color:#3b82f6;"><i class="fas fa-file-signature"></i></div>
                <div><b><?php echo $stats['applications']; ?></b><span>Applications</span></div>
            </div>
            <div class="pp-stat">
                <div class="pp-stat-ico" style="background: rgba(245,158,11,.12); color:#f59e0b;"><i class="fas fa-layer-group"></i></div>
                <div><b><?php echo $stats['category_apps']; ?></b><span>Category Apps</span></div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="pp-tabs">
            <button type="button" class="pp-tab active" data-tab="overview" onclick="switchTab('overview')"><i class="fas fa-circle-info"></i>Overview</button>
            <button type="button" class="pp-tab" data-tab="edit" onclick="switchTab('edit')"><i class="fas fa-pen-to-square"></i>Edit Profile</button>
            <button type="button" class="pp-tab" data-tab="security" onclick="switchTab('security')"><i class="fas fa-shield-halved"></i>Security</button>
        </div>

        <!-- Overview -->
        <div class="pp-panel active" id="panel-overview">
            <div class="pp-card">
                <h3 class="pp-card-title"><i class="fas fa-circle-info"></i>Company Information</h3>
                <div class="pp-info-grid">
                    <div class="pp-info">
                        <span><i class="fas fa-building"></i>Company</span>
                        <p><?php echo htmlspecialchars($company['company_name']); ?></p>
                    </div>
                    <div class="pp-info">
                        <span><i class="fas fa-envelope"></i>Email</span>
                        <p><?php echo htmlspecialchars($company['company_email']); ?></p>
                    </div>
                    <div class="pp-info">
                        <span><i class="fas fa-phone"></i>Phone</span>
                        <p><?php echo htmlspecialchars($company['company_phone'] ?: 'Not provided'); ?></p>
                    </div>
                    <div class="pp-info">
                        <span><i class="fas fa-industry"></i>Industry</span>
                        <p><?php echo htmlspecialchars($company['industry'] ?: 'Not provided'); ?></p>
                    </div>
                    <div class="pp-info">
                        <span><i class="fas fa-users"></i>Company Size</span>
                        <p><?php echo htmlspecialchars($company['company_size']); ?> employees</p>
                    </div>
                    <div class="pp-info">
                        <span><i class="fas fa-globe"></i>Website</span>
                        <p><?php echo !empty($company['company_website']) ? '<a href="' . htmlspecialchars($company['company_website']) . '" target="_blank">' . htmlspecialchars($company['company_website']) . '</a>' : 'Not provided'; ?></p>
                    </div>
                    <div class="pp-info">
                        <span><i class="fas fa-map-location-dot"></i>Address</span>
                        <p><?php echo htmlspecialchars($company['company_address'] ?: 'Not provided'); ?></p>
                    </div>
                    <div class="pp-info full">
                        <span><i class="fas fa-file-lines"></i>Description</span>
                        <p><?php echo htmlspecialchars($company['description'] ?: 'No description provided.'); ?></p>
                    </div>
                    <div class="pp-info full">
                        <span><i class="fas fa-image"></i>Company Logo</span>
                        <div class="pp-logo-preview">
                            <?php if ($logo_ok): ?>
                                <img src="<?php echo htmlspecialchars($logo_path); ?>" alt="Company Logo">
                            <?php else: ?>
                                <p style="color: var(--pp-muted); font-weight: 500; font-size: 0.85rem;">No logo uploaded</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Profile -->
        <div class="pp-panel" id="panel-edit">
            <div class="pp-card">
                <h3 class="pp-card-title"><i class="fas fa-pen-to-square"></i>Edit Profile</h3>

                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="pp-form-grid">
                        <div class="pp-field">
                            <label for="company_name"><i class="fas fa-building"></i>Company Name *</label>
                            <input type="text" class="pp-input" id="company_name" name="company_name" value="<?php echo htmlspecialchars($company['company_name']); ?>" required>
                        </div>
                        <div class="pp-field">
                            <label for="phone"><i class="fas fa-phone"></i>Phone Number *</label>
                            <input type="tel" class="pp-input" id="phone" name="phone" value="<?php echo htmlspecialchars($company['company_phone']); ?>" required>
                        </div>
                        <div class="pp-field">
                            <label for="website"><i class="fas fa-globe"></i>Website</label>
                            <input type="url" class="pp-input" id="website" name="website" value="<?php echo htmlspecialchars($company['company_website']); ?>">
                        </div>
                        <div class="pp-field">
                            <label for="industry"><i class="fas fa-industry"></i>Industry *</label>
                            <select class="pp-select" id="industry" name="industry" required>
                                <option value="Information Technology" <?php echo ($company['industry'] == 'Information Technology') ? 'selected' : ''; ?>>Information Technology</option>
                                <option value="Software Development" <?php echo ($company['industry'] == 'Software Development') ? 'selected' : ''; ?>>Software Development</option>
                                <option value="Web Development" <?php echo ($company['industry'] == 'Web Development') ? 'selected' : ''; ?>>Web Development</option>
                                <option value="Mobile App Development" <?php echo ($company['industry'] == 'Mobile App Development') ? 'selected' : ''; ?>>Mobile App Development</option>
                                <option value="E-commerce" <?php echo ($company['industry'] == 'E-commerce') ? 'selected' : ''; ?>>E-commerce</option>
                                <option value="Finance" <?php echo ($company['industry'] == 'Finance') ? 'selected' : ''; ?>>Finance</option>
                                <option value="Healthcare" <?php echo ($company['industry'] == 'Healthcare') ? 'selected' : ''; ?>>Healthcare</option>
                                <option value="Education" <?php echo ($company['industry'] == 'Education') ? 'selected' : ''; ?>>Education</option>
                                <option value="Consulting" <?php echo ($company['industry'] == 'Consulting') ? 'selected' : ''; ?>>Consulting</option>
                                <option value="Other" <?php echo ($company['industry'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        <div class="pp-field">
                            <label for="company_size"><i class="fas fa-users"></i>Company Size *</label>
                            <select class="pp-select" id="company_size" name="company_size" required>
                                <option value="1-10" <?php echo ($company['company_size'] == '1-10') ? 'selected' : ''; ?>>1-10 employees</option>
                                <option value="11-50" <?php echo ($company['company_size'] == '11-50') ? 'selected' : ''; ?>>11-50 employees</option>
                                <option value="51-200" <?php echo ($company['company_size'] == '51-200') ? 'selected' : ''; ?>>51-200 employees</option>
                                <option value="201-500" <?php echo ($company['company_size'] == '201-500') ? 'selected' : ''; ?>>201-500 employees</option>
                                <option value="501-1000" <?php echo ($company['company_size'] == '501-1000') ? 'selected' : ''; ?>>501-1000 employees</option>
                                <option value="1000+" <?php echo ($company['company_size'] == '1000+') ? 'selected' : ''; ?>>1000+ employees</option>
                            </select>
                        </div>
                        <div class="pp-field" style="grid-column: 1 / -1;">
                            <label for="address"><i class="fas fa-map-location-dot"></i>Company Address *</label>
                            <textarea class="pp-textarea" id="address" name="address" rows="2" required data-count="addrCount"><?php echo htmlspecialchars($company['company_address']); ?></textarea>
                            <div class="pp-char" id="addrCount"></div>
                        </div>
                        <div class="pp-field" style="grid-column: 1 / -1;">
                            <label for="description"><i class="fas fa-file-lines"></i>Company Description *</label>
                            <textarea class="pp-textarea" id="description" name="description" rows="4" required data-count="descCount"><?php echo htmlspecialchars($company['description']); ?></textarea>
                            <div class="pp-char" id="descCount"></div>
                        </div>
                        <div class="pp-field" style="grid-column: 1 / -1;">
                            <label for="company_logo"><i class="fas fa-image"></i>Company Logo</label>
                            <div class="pp-upload">
                                <i class="fas fa-cloud-arrow-up"></i>
                                <b>Click to upload a new logo</b>
                                <small>JPG, PNG, GIF or WEBP — max 2MB</small>
                                <input type="file" id="company_logo" name="company_logo" accept="image/*">
                            </div>
                            <div class="pp-upload-preview" id="logoPreview">
                                <?php if ($logo_ok): ?>
                                    <img src="<?php echo htmlspecialchars($logo_path); ?>" alt="Logo Preview">
                                <?php endif; ?>
                            </div>
                            <small>If no new file is selected, your current logo will be kept.</small>
                        </div>
                    </div>
                    <button type="submit" name="update_profile" class="pp-submit"><i class="fas fa-save"></i>Update Profile</button>
                </form>
            </div>
        </div>

        <!-- Security -->
        <div class="pp-panel" id="panel-security">
            <div class="pp-card">
                <h3 class="pp-card-title"><i class="fas fa-shield-halved"></i>Change Password</h3>

                <form method="POST" action="">
                    <div class="pp-form-grid">
                        <div class="pp-field">
                            <label for="current_password"><i class="fas fa-key"></i>Current Password *</label>
                            <div class="pp-pass-wrap">
                                <input type="password" class="pp-input" id="current_password" name="current_password" required>
                                <button type="button" class="pp-eye" onclick="togglePass('current_password', this)"><i class="far fa-eye"></i></button>
                            </div>
                        </div>
                        <div class="pp-field">
                            <label for="new_password"><i class="fas fa-lock"></i>New Password *</label>
                            <div class="pp-pass-wrap">
                                <input type="password" class="pp-input" id="new_password" name="new_password" required minlength="6" oninput="strengthMeter(this.value)">
                                <button type="button" class="pp-eye" onclick="togglePass('new_password', this)"><i class="far fa-eye"></i></button>
                            </div>
                            <div class="pp-strength"><div class="pp-strength-bar" id="strengthBar"></div></div>
                            <div class="pp-strength-txt" id="strengthTxt">Use at least 6 characters</div>
                        </div>
                        <div class="pp-field" style="grid-column: 1 / -1;">
                            <label for="confirm_password"><i class="fas fa-lock"></i>Confirm New Password *</label>
                            <div class="pp-pass-wrap">
                                <input type="password" class="pp-input" id="confirm_password" name="confirm_password" required minlength="6">
                                <button type="button" class="pp-eye" onclick="togglePass('confirm_password', this)"><i class="far fa-eye"></i></button>
                            </div>
                            <small id="confirmHint"></small>
                        </div>
                    </div>
                    <button type="submit" name="change_password" class="pp-submit"><i class="fas fa-key"></i>Change Password</button>
                </form>
            </div>
        </div>
    </div>

    <?php if ($flash_message): ?>
        <div class="pp-toast <?php echo $flash_type == 'danger' ? 'danger' : ''; ?>" id="ppToast">
            <span><i class="fas <?php echo $flash_type == 'danger' ? 'fa-circle-xmark' : 'fa-circle-check'; ?>"></i></span>
            <span><?php echo htmlspecialchars($flash_message); ?></span>
        </div>
    <?php endif; ?>

    <script>
        function switchTab(name) {
            document.querySelectorAll('.pp-tab').forEach(t => t.classList.toggle('active', t.dataset.tab === name));
            document.querySelectorAll('.pp-panel').forEach(p => p.classList.toggle('active', p.id === 'panel-' + name));
        }

        function togglePass(id, btn) {
            const inp = document.getElementById(id);
            const show = inp.type === 'password';
            inp.type = show ? 'text' : 'password';
            btn.innerHTML = show ? '<i class="far fa-eye-slash"></i>' : '<i class="far fa-eye"></i>';
        }

        function strengthMeter(v) {
            const bar = document.getElementById('strengthBar');
            const txt = document.getElementById('strengthTxt');
            let score = 0;
            if (v.length >= 6) score++;
            if (v.length >= 10) score++;
            if (/[A-Z]/.test(v) && /[a-z]/.test(v)) score++;
            if (/\d/.test(v)) score++;
            if (/[^A-Za-z0-9]/.test(v)) score++;
            const levels = [
                { l: 0, w: '0%', c: '#e2e8f0', t: 'Use at least 6 characters' },
                { l: 1, w: '25%', c: '#ef4444', t: 'Weak' },
                { l: 2, w: '50%', c: '#f59e0b', t: 'Fair' },
                { l: 3, w: '75%', c: '#3b82f6', t: 'Good' },
                { l: 4, w: '100%', c: '#10b981', t: 'Strong' }
            ];
            const lv = levels[Math.min(score, 4)];
            bar.style.width = lv.w;
            bar.style.background = lv.c;
            txt.textContent = lv.t;
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Char counters
            document.querySelectorAll('.pp-textarea[data-count]').forEach(ta => {
                const counter = document.getElementById(ta.dataset.count);
                const update = () => { if (counter) counter.textContent = ta.value.length + ' characters'; };
                ta.addEventListener('input', update);
                update();
            });

            // Logo preview
            const logoInput = document.getElementById('company_logo');
            const preview = document.getElementById('logoPreview');
            if (logoInput) {
                logoInput.addEventListener('change', function() {
                    const file = this.files[0];
                    if (!file) return;
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        preview.classList.add('show');
                        preview.innerHTML = '<img src="' + e.target.result + '" alt="Logo Preview">';
                    };
                    reader.readAsDataURL(file);
                });
            }

            // Password confirm hint
            const np = document.getElementById('new_password');
            const cp = document.getElementById('confirm_password');
            const hint = document.getElementById('confirmHint');
            if (np && cp && hint) {
                const check = () => {
                    if (!cp.value) { hint.textContent = ''; return; }
                    hint.textContent = np.value === cp.value ? 'Passwords match' : 'Passwords do not match';
                    hint.style.color = np.value === cp.value ? '#10b981' : '#ef4444';
                };
                np.addEventListener('input', check);
                cp.addEventListener('input', check);
            }

            // Toast auto-hide
            const toast = document.getElementById('ppToast');
            if (toast) {
                setTimeout(function() {
                    toast.style.transition = 'opacity .5s ease, transform .5s ease';
                    toast.style.opacity = '0';
                    toast.style.transform = 'translateX(60px)';
                    setTimeout(function() { toast.remove(); }, 500);
                }, 3500);
            }
        });
    </script>
</body>
</html>
