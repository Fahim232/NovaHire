<?php
/**
 * Employer / Company Registration Portal
 */
require_once __DIR__ . '/../includes/bootstrap.php';

if (isset($_POST['register'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_msg = 'Your session expired. Please refresh the page and try again.';
        $error = true;
    }
}

if (isset($_POST['register']) && !isset($error)) {
    $company_name = trim($_POST['company_name'] ?? '');
    $email        = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $phone        = trim($_POST['phone'] ?? '');
    $address      = trim($_POST['address'] ?? '');
    $website      = trim($_POST['website'] ?? '');
    $industry     = trim($_POST['industry'] ?? '');
    $company_size = trim($_POST['company_size'] ?? '');
    $description  = trim($_POST['description'] ?? '');
    $password     = $_POST['password'] ?? '';
    $cpassword    = $_POST['cpassword'] ?? '';
    $terms        = isset($_POST['terms']) ? 1 : 0;

    $email_stmt = mysqli_prepare($con, "SELECT id FROM companies WHERE company_email = ?");
    mysqli_stmt_bind_param($email_stmt, "s", $email);
    mysqli_stmt_execute($email_stmt);
    $email_res  = mysqli_stmt_get_result($email_stmt);
    $email_exists = mysqli_num_rows($email_res) > 0;
    mysqli_stmt_close($email_stmt);

    if ($email_exists) {
        $error_msg = 'Company email already registered!';
    } elseif ($password !== $cpassword) {
        $error_msg = 'Passwords do not match!';
    } elseif (strlen($password) < 8) {
        $error_msg = 'Password must be at least 8 characters!';
    } elseif (!$terms) {
        $error_msg = 'You must agree to the Terms of Service!';
    } else {
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
        $logo_name = null;
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
            $up = nh_store_upload($_FILES['logo'], __DIR__ . '/uploads/company_logos', 'image', 'logo');
            if ($up['ok']) {
                $logo_name = $up['filename'];
            } else {
                $error_msg = $up['error'];
                $error = true;
            }
        }
        if (!isset($error)) {
            $status = 'active';
            $ins_stmt = mysqli_prepare($con, "INSERT INTO companies (company_name, company_email, company_phone, company_address, company_website, industry, company_size, description, logo, password, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($ins_stmt, "sssssssssss", $company_name, $email, $phone, $address, $website, $industry, $company_size, $description, $logo_name, $hashed_password, $status);
            if (mysqli_stmt_execute($ins_stmt)) {
                $success_msg = 'Company registered successfully!';
                // Send welcome email
                require_once __DIR__ . '/includes/mail.php';
                send_company_welcome_email($email, $company_name);
            } else {
                $error_msg = 'Registration failed! Please try again.';
            }
            mysqli_stmt_close($ins_stmt);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Company | NovaHire</title>
    <?php include 'includes/links.php'; ?>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
    :root{--cr-primary:#3b82f6;--cr-secondary:#06b6d4;--cr-accent:#0ea5e9;--cr-grad:linear-gradient(135deg,#3b82f6,#06b6d4 50%,#0ea5e9);--cr-text:#1e293b;--cr-muted:#64748b;--cr-border:#e2e8f0;--cr-bg:#f8fafc;--cr-card:#fff;--cr-success:#059669;--cr-danger:#dc2626;--cr-radius:16px;}
    *{margin:0;padding:0;box-sizing:border-box;}
    body{font-family:'Plus Jakarta Sans','Inter',sans-serif;min-height:100vh;display:flex;background:var(--cr-bg);}

    /* ═══ Split Layout ═══ */
    .cr-split{display:grid;grid-template-columns:1fr 1fr;min-height:100vh;width:100%;}

    /* ═══ Left Branding Panel ═══ */
    .cr-brand{background:linear-gradient(135deg,#0f172a 0%,#1e293b 50%,#334155 100%);color:#fff;display:flex;flex-direction:column;justify-content:center;padding:60px 50px;position:relative;overflow:hidden;}
    .cr-brand::before{content:'';position:absolute;top:-120px;right:-80px;width:400px;height:400px;background:radial-gradient(circle,rgba(59,130,246,.2),transparent 70%);border-radius:50%;}
    .cr-brand::after{content:'';position:absolute;bottom:-100px;left:-60px;width:350px;height:350px;background:radial-gradient(circle,rgba(6,182,212,.15),transparent 70%);border-radius:50%;}
    .cr-brand-content{position:relative;z-index:2;}
    .cr-brand-logo{display:flex;align-items:center;gap:12px;margin-bottom:40px;}
    .cr-brand-logo-icon{width:48px;height:48px;border-radius:14px;background:var(--cr-grad);display:flex;align-items:center;justify-content:center;font-size:1.2rem;color:#fff;box-shadow:0 6px 20px rgba(59,130,246,.4);}
    .cr-brand-logo-text{font-size:1.4rem;font-weight:800;letter-spacing:-.5px;}
    .cr-brand-logo-text span{background:var(--cr-grad);-webkit-background-clip:text;-webkit-text-fill-color:transparent;}
    .cr-brand h1{font-size:2.4rem;font-weight:800;line-height:1.15;margin-bottom:16px;letter-spacing:-.5px;}
    .cr-brand h1 i{font-size:1.6rem;margin-right:8px;opacity:.8;}
    .cr-brand p{font-size:1.05rem;opacity:.75;line-height:1.7;margin-bottom:40px;max-width:440px;}

    .cr-features{display:flex;flex-direction:column;gap:18px;margin-bottom:40px;}
    .cr-feature{display:flex;align-items:center;gap:14px;}
    .cr-feature-icon{width:42px;height:42px;border-radius:12px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.1);display:flex;align-items:center;justify-content:center;font-size:.9rem;color:#38bdf8;flex-shrink:0;}
    .cr-feature-text h6{font-size:.88rem;font-weight:700;color:#fff;margin:0;}
    .cr-feature-text small{font-size:.78rem;opacity:.6;}

    .cr-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;}
    .cr-stat{text-align:center;padding:16px 8px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);border-radius:14px;}
    .cr-stat .num{font-size:1.5rem;font-weight:800;color:#fff;line-height:1;}
    .cr-stat .lbl{font-size:.68rem;font-weight:600;opacity:.55;margin-top:4px;text-transform:uppercase;letter-spacing:.5px;}

    .cr-brand-footer{position:relative;z-index:2;margin-top:auto;padding-top:30px;border-top:1px solid rgba(255,255,255,.08);display:flex;align-items:center;gap:10px;font-size:.82rem;opacity:.5;}
    .cr-brand-footer i{font-size:.9rem;}

    /* ═══ Right Form Panel ═══ */
    .cr-form-panel{display:flex;flex-direction:column;align-items:center;justify-content:center;padding:40px;overflow-y:auto;}
    .cr-form-wrap{width:100%;max-width:500px;}

    .cr-form-header{text-align:center;margin-bottom:32px;}
    .cr-form-header h2{font-size:1.5rem;font-weight:800;color:var(--cr-text);margin-bottom:6px;}
    .cr-form-header p{font-size:.9rem;color:var(--cr-muted);}

    /* ═══ Step Progress ═══ */
    .cr-steps{display:flex;align-items:center;justify-content:center;gap:0;margin-bottom:36px;}
    .cr-step{display:flex;align-items:center;gap:8px;}
    .cr-step-num{width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.82rem;font-weight:700;background:var(--cr-bg);color:var(--cr-muted);border:2px solid var(--cr-border);transition:all .4s cubic-bezier(.34,1.56,.64,1);}
    .cr-step.active .cr-step-num{background:var(--cr-grad);color:#fff;border-color:transparent;box-shadow:0 4px 14px rgba(59,130,246,.35);transform:scale(1.1);}
    .cr-step.done .cr-step-num{background:var(--cr-success);color:#fff;border-color:transparent;}
    .cr-step-label{font-size:.78rem;font-weight:600;color:var(--cr-muted);transition:color .3s;}
    .cr-step.active .cr-step-label{color:var(--cr-primary);font-weight:700;}
    .cr-step.done .cr-step-label{color:var(--cr-success);}
    .cr-step-line{width:40px;height:2px;background:var(--cr-border);margin:0 6px;transition:background .4s;}
    .cr-step-line.done{background:var(--cr-success);}

    /* ═══ Form ═══ */
    .cr-form-step{display:none;}
    .cr-form-step.active{display:block;animation:crSlideIn .4s ease;}
    @keyframes crSlideIn{from{opacity:0;transform:translateX(24px);}to{opacity:1;transform:none;}}
    @keyframes crSlideOut{from{opacity:1;transform:none;}to{opacity:0;transform:translateX(-24px);}}

    .cr-field{margin-bottom:20px;}
    .cr-field label{display:block;font-size:.82rem;font-weight:700;color:var(--cr-text);margin-bottom:6px;}
    .cr-field label .req{color:var(--cr-danger);}
    .cr-field label small{font-weight:400;color:var(--cr-muted);}

    .cr-input-wrap{position:relative;}
    .cr-input-wrap i{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--cr-muted);font-size:.88rem;transition:color .3s;pointer-events:none;z-index:1;}
    .cr-input-wrap input,.cr-input-wrap textarea,.cr-input-wrap select{
        width:100%;padding:13px 14px 13px 42px;border:2px solid var(--cr-border);border-radius:var(--cr-radius);
        font-size:.9rem;color:var(--cr-text);font-weight:500;font-family:inherit;
        transition:all .3s;background:var(--cr-bg);outline:none;
    }
    .cr-input-wrap input:focus,.cr-input-wrap textarea:focus,.cr-input-wrap select:focus{border-color:var(--cr-primary);background:#fff;box-shadow:0 0 0 3px rgba(59,130,246,.1);}
    .cr-input-wrap input:focus+i,.cr-input-wrap textarea:focus~i{color:var(--cr-primary);}
    .cr-input-wrap select{cursor:pointer;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%2364748b' viewBox='0 0 16 16'%3E%3Cpath d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 14px center;}
    .cr-input-wrap textarea{resize:vertical;min-height:80px;}

    .cr-pw-toggle{position:absolute;right:14px;top:50%;transform:translateY(-50%);cursor:pointer;color:var(--cr-muted);font-size:.95rem;transition:color .3s;z-index:1;}
    .cr-pw-toggle:hover{color:var(--cr-primary);}

    .cr-pw-strength{height:4px;border-radius:4px;margin-top:8px;background:var(--cr-border);overflow:hidden;}
    .cr-pw-strength-bar{height:100%;border-radius:4px;width:0;transition:all .4s;}
    .cr-hint{font-size:.76rem;margin-top:4px;font-weight:600;}

    /* Logo Upload */
    .cr-logo-upload{display:flex;align-items:center;gap:18px;padding:18px;border:2px dashed var(--cr-border);border-radius:var(--cr-radius);transition:all .3s;cursor:pointer;background:var(--cr-bg);}
    .cr-logo-upload:hover{border-color:var(--cr-primary);background:#f0f0ff;}
    .cr-logo-upload.has-file{border-color:var(--cr-success);background:#f0fdf4;}
    .cr-logo-preview{width:68px;height:68px;border-radius:14px;background:var(--cr-border);display:flex;align-items:center;justify-content:center;font-size:1.6rem;color:var(--cr-muted);overflow:hidden;flex-shrink:0;}
    .cr-logo-preview img{width:100%;height:100%;object-fit:cover;}
    .cr-logo-info h6{font-weight:700;color:var(--cr-text);margin:0 0 2px;font-size:.88rem;}
    .cr-logo-info small{color:var(--cr-muted);font-size:.76rem;}

    /* Terms */
    .cr-terms{display:flex;align-items:flex-start;gap:10px;padding:14px 16px;background:var(--cr-bg);border-radius:var(--cr-radius);border:2px solid var(--cr-border);cursor:pointer;transition:all .3s;}
    .cr-terms:hover{border-color:var(--cr-primary);}
    .cr-terms input[type="checkbox"]{width:18px;height:18px;margin-top:2px;accent-color:var(--cr-primary);flex-shrink:0;}
    .cr-terms label{font-size:.82rem;color:var(--cr-muted);line-height:1.5;cursor:pointer;margin:0;}
    .cr-terms a{color:var(--cr-primary);font-weight:600;}

    /* Buttons */
    .cr-btn-row{display:flex;gap:12px;margin-top:28px;}
    .cr-btn{flex:1;padding:14px;border:none;border-radius:var(--cr-radius);font-weight:700;font-size:.92rem;cursor:pointer;transition:all .3s;display:flex;align-items:center;justify-content:center;gap:8px;font-family:inherit;}
    .cr-btn-next{background:var(--cr-grad);color:#fff;box-shadow:0 4px 14px rgba(59,130,246,.3);}
    .cr-btn-next:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(59,130,246,.4);}
    .cr-btn-next:active{transform:scale(.97);}
    .cr-btn-back{flex:.6;background:#fff;color:var(--cr-muted);border:2px solid var(--cr-border);}
    .cr-btn-back:hover{border-color:var(--cr-primary);color:var(--cr-primary);}
    .cr-btn-submit{background:var(--cr-grad);color:#fff;box-shadow:0 4px 14px rgba(59,130,246,.3);}
    .cr-btn-submit:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(59,130,246,.4);}
    .cr-btn-submit:disabled{opacity:.5;cursor:not-allowed;transform:none;box-shadow:none;}

    /* Alerts */
    .cr-alert{padding:13px 16px;border-radius:var(--cr-radius);font-size:.86rem;font-weight:600;margin-bottom:20px;display:flex;align-items:center;gap:10px;animation:crSlideIn .3s ease;}
    .cr-alert.error{background:#fef2f2;color:#b91c1c;border:1px solid #fecaca;}
    .cr-alert.success{background:#f0fdf4;color:#15803d;border:1px solid #bbf7d0;}

    .cr-login-link{text-align:center;margin-top:24px;font-size:.86rem;color:var(--cr-muted);}
    .cr-login-link a{color:var(--cr-primary);font-weight:700;text-decoration:none;}
    .cr-login-link a:hover{text-decoration:underline;}

    /* Grid */
    .cr-grid-2{display:grid;grid-template-columns:1fr 1fr;gap:16px;}

    @media(max-width:991px){
        .cr-split{grid-template-columns:1fr;}
        .cr-brand{display:none;}
    }
    @media(max-width:575px){
        .cr-form-panel{padding:24px 16px;}
        .cr-step-label{display:none;}
        .cr-step-line{width:24px;}
        .cr-grid-2{grid-template-columns:1fr;}
        .cr-btn-row{flex-direction:column-reverse;}
        .cr-btn-back{flex:1;}
        .cr-brand{display:none;}
    }
    </style>
</head>
<body>
    <div class="cr-split">

        <!-- Left Branding Panel -->
        <div class="cr-brand">
            <div class="cr-brand-content">
                <div class="cr-brand-logo">
                    <div class="cr-brand-logo-icon"><i class="fas fa-layer-group"></i></div>
                    <div class="cr-brand-logo-text">Nova<span>Hire</span></div>
                </div>
                <h1><i class="fas fa-building"></i>Register Your Company</h1>
                <p>Create an employer account and start hiring the best talent. Post jobs, review applications, and build your dream team.</p>

                <div class="cr-features">
                    <div class="cr-feature">
                        <div class="cr-feature-icon"><i class="fas fa-bolt"></i></div>
                        <div class="cr-feature-text"><h6>Post Jobs in Minutes</h6><small>Reach thousands of qualified candidates instantly</small></div>
                    </div>
                    <div class="cr-feature">
                        <div class="cr-feature-icon"><i class="fas fa-robot"></i></div>
                        <div class="cr-feature-text"><h6>AI-Powered Matching</h6><small>Smart candidate recommendations for every role</small></div>
                    </div>
                    <div class="cr-feature">
                        <div class="cr-feature-icon"><i class="fas fa-chart-line"></i></div>
                        <div class="cr-feature-text"><h6>Hiring Analytics</h6><small>Track performance and optimize your pipeline</small></div>
                    </div>
                </div>

                <div class="cr-stats">
                    <div class="cr-stat"><div class="num">12K+</div><div class="lbl">Companies</div></div>
                    <div class="cr-stat"><div class="num">50K+</div><div class="lbl">Candidates</div></div>
                    <div class="cr-stat"><div class="num">8K+</div><div class="lbl">Jobs Posted</div></div>
                </div>
            </div>
            <div class="cr-brand-footer">
                <i class="fas fa-shield-halved"></i> Trusted by leading companies across Bangladesh
            </div>
        </div>

        <!-- Right Form Panel -->
        <div class="cr-form-panel">
            <div class="cr-form-wrap">
                <div class="cr-form-header">
                    <h2>Create Employer Account</h2>
                    <p>Fill in the details to get started</p>
                </div>

                <!-- Step Progress -->
                <div class="cr-steps">
                    <div class="cr-step active" id="crStepInd1">
                        <span class="cr-step-num">1</span>
                        <span class="cr-step-label">Company</span>
                    </div>
                    <div class="cr-step-line" id="crLine1"></div>
                    <div class="cr-step" id="crStepInd2">
                        <span class="cr-step-num">2</span>
                        <span class="cr-step-label">Contact</span>
                    </div>
                    <div class="cr-step-line" id="crLine2"></div>
                    <div class="cr-step" id="crStepInd3">
                        <span class="cr-step-num">3</span>
                        <span class="cr-step-label">Account</span>
                    </div>
                </div>

                <?php if (isset($error_msg)): ?>
                    <div class="cr-alert error"><i class="fas fa-exclamation-circle"></i><?php echo $error_msg; ?></div>
                <?php endif; ?>
                <?php if (isset($success_msg)): ?>
                    <div class="cr-alert success"><i class="fas fa-check-circle"></i><?php echo $success_msg; ?> Redirecting... <i class="fas fa-spinner fa-spin ml-2"></i></div>
                    <script>setTimeout(function(){window.location.href='auth/login.php';},2000);</script>
                <?php endif; ?>

                <form method="POST" action="" enctype="multipart/form-data" id="crForm">
                    <?php echo csrf_field(); ?>

                    <!-- Step 1: Company Info -->
                    <div class="cr-form-step active" id="crStep1">
                        <div class="cr-field">
                            <label>Company Name <span class="req">*</span></label>
                            <div class="cr-input-wrap">
                                <input type="text" name="company_name" placeholder="e.g. Tech Solutions Inc." required maxlength="255" id="crCompanyName">
                                <i class="fas fa-building"></i>
                            </div>
                        </div>

                        <div class="cr-field">
                            <label>Company Logo <small>(optional)</small></label>
                            <label class="cr-logo-upload" for="crLogoInput" id="crLogoLabel">
                                <div class="cr-logo-preview" id="crLogoPreview"><i class="fas fa-cloud-arrow-up"></i></div>
                                <div class="cr-logo-info">
                                    <h6>Click to upload logo</h6>
                                    <small>JPG, PNG, GIF or WebP - Max 5MB</small>
                                </div>
                            </label>
                            <input type="file" name="logo" id="crLogoInput" accept="image/*" style="display:none;">
                        </div>

                        <div class="cr-field">
                            <label>Company Description <span class="req">*</span></label>
                            <div class="cr-input-wrap">
                                <textarea name="description" rows="3" placeholder="Tell us about your company, mission, and culture..." required id="crCompanyDesc"></textarea>
                                <i class="fas fa-info-circle" style="top:30px;transform:none;"></i>
                            </div>
                        </div>

                        <div class="cr-btn-row">
                            <button type="button" class="cr-btn cr-btn-next" onclick="crNext(2)">Continue <i class="fas fa-arrow-right"></i></button>
                        </div>
                    </div>

                    <!-- Step 2: Contact & Industry -->
                    <div class="cr-form-step" id="crStep2">
                        <div class="cr-field">
                            <label>Company Email <span class="req">*</span></label>
                            <div class="cr-input-wrap">
                                <input type="email" name="email" placeholder="hr@company.com" required id="crEmail">
                                <i class="fas fa-envelope"></i>
                            </div>
                        </div>

                        <div class="cr-field">
                            <label>Phone Number <span class="req">*</span></label>
                            <div class="cr-input-wrap">
                                <input type="tel" name="phone" placeholder="+880 1XXXXXXXXX" required id="crPhone">
                                <i class="fas fa-phone"></i>
                            </div>
                        </div>

                        <div class="cr-field">
                            <label>Website <small>(optional)</small></label>
                            <div class="cr-input-wrap">
                                <input type="url" name="website" placeholder="https://example.com">
                                <i class="fas fa-globe"></i>
                            </div>
                        </div>

                        <div class="cr-field">
                            <label>Company Address <span class="req">*</span></label>
                            <div class="cr-input-wrap">
                                <textarea name="address" rows="2" placeholder="Street, City, Country" required id="crAddress"></textarea>
                                <i class="fas fa-location-dot" style="top:30px;transform:none;"></i>
                            </div>
                        </div>

                        <div class="cr-grid-2">
                            <div class="cr-field" style="margin:0;">
                                <label>Industry <span class="req">*</span></label>
                                <div class="cr-input-wrap">
                                    <select name="industry" required id="crIndustry">
                                        <option value="">Select Industry</option>
                                        <option value="Information Technology">Information Technology</option>
                                        <option value="Software Development">Software Development</option>
                                        <option value="Web Development">Web Development</option>
                                        <option value="Mobile App Development">Mobile App Development</option>
                                        <option value="E-commerce">E-commerce</option>
                                        <option value="Finance">Finance</option>
                                        <option value="Healthcare">Healthcare</option>
                                        <option value="Education">Education</option>
                                        <option value="Marketing">Marketing</option>
                                        <option value="Consulting">Consulting</option>
                                        <option value="Manufacturing">Manufacturing</option>
                                        <option value="Retail">Retail</option>
                                        <option value="Media">Media & Entertainment</option>
                                        <option value="Other">Other</option>
                                    </select>
                                    <i class="fas fa-industry"></i>
                                </div>
                            </div>
                            <div class="cr-field" style="margin:0;">
                                <label>Company Size <span class="req">*</span></label>
                                <div class="cr-input-wrap">
                                    <select name="company_size" required id="crSize">
                                        <option value="">Select Size</option>
                                        <option value="1-10">1-10 employees</option>
                                        <option value="11-50">11-50 employees</option>
                                        <option value="51-200">51-200 employees</option>
                                        <option value="201-500">201-500 employees</option>
                                        <option value="501-1000">501-1000 employees</option>
                                        <option value="1000+">1000+ employees</option>
                                    </select>
                                    <i class="fas fa-users"></i>
                                </div>
                            </div>
                        </div>

                        <div class="cr-btn-row">
                            <button type="button" class="cr-btn cr-btn-back" onclick="crPrev(1)"><i class="fas fa-arrow-left"></i> Back</button>
                            <button type="button" class="cr-btn cr-btn-next" onclick="crNext(3)">Continue <i class="fas fa-arrow-right"></i></button>
                        </div>
                    </div>

                    <!-- Step 3: Account Setup -->
                    <div class="cr-form-step" id="crStep3">
                        <div class="cr-field">
                            <label>Password <span class="req">*</span></label>
                            <div class="cr-input-wrap">
                                <input type="password" name="password" placeholder="Min. 8 characters" required minlength="8" id="crPw" oninput="crCheckStrength(this.value)">
                                <i class="fas fa-lock"></i>
                                <span class="cr-pw-toggle" onclick="crTogglePw('crPw',this)"><i class="fas fa-eye"></i></span>
                            </div>
                            <div class="cr-pw-strength"><div class="cr-pw-strength-bar" id="crPwBar"></div></div>
                            <div class="cr-hint" id="crPwHint"></div>
                        </div>

                        <div class="cr-field">
                            <label>Confirm Password <span class="req">*</span></label>
                            <div class="cr-input-wrap">
                                <input type="password" name="cpassword" placeholder="Re-enter password" required minlength="8" id="crCpw" oninput="crMatchPw()">
                                <i class="fas fa-lock"></i>
                                <span class="cr-pw-toggle" onclick="crTogglePw('crCpw',this)"><i class="fas fa-eye"></i></span>
                            </div>
                            <div class="cr-hint" id="crPwMatch"></div>
                        </div>

                        <div class="cr-terms" style="margin-top:12px;">
                            <input type="checkbox" name="terms" id="crTerms" required>
                            <label for="crTerms">I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a>. I confirm that I am authorized to register this company.</label>
                        </div>

                        <div class="cr-btn-row">
                            <button type="button" class="cr-btn cr-btn-back" onclick="crPrev(2)"><i class="fas fa-arrow-left"></i> Back</button>
                            <button type="submit" name="register" class="cr-btn cr-btn-submit" id="crSubmitBtn" disabled>
                                <i class="fas fa-check-circle"></i> Create Account
                            </button>
                        </div>
                    </div>
                </form>

                <div class="cr-login-link">
                    Already have an account? <a href="auth/login.php">Sign in here</a>
                </div>
            </div>
        </div>
    </div>

    <script>
    let crCurrent = 1;

    function crShow(step) {
        document.querySelectorAll('.cr-form-step').forEach(function(s) {
            s.classList.remove('active');
        });
        document.getElementById('crStep' + step).classList.add('active');

        for (var i = 1; i <= 3; i++) {
            var ind = document.getElementById('crStepInd' + i);
            ind.classList.remove('active', 'done');
            var line = document.getElementById('crLine' + (i - 1));
            if (i < step) { ind.classList.add('done'); if (line) line.classList.add('done'); }
            else if (i === step) { ind.classList.add('active'); }
            else { if (line) line.classList.remove('done'); }
        }
        crCurrent = step;
        window.scrollTo({top: 0, behavior: 'smooth'});
    }

    function crValidate1() {
        var n = document.getElementById('crCompanyName').value.trim();
        var d = document.getElementById('crCompanyDesc').value.trim();
        if (!n || !d) { crShake(['crCompanyName', 'crCompanyDesc']); return false; }
        return true;
    }

    function crValidate2() {
        var e = document.getElementById('crEmail').value.trim();
        var p = document.getElementById('crPhone').value.trim();
        var i = document.getElementById('crIndustry').value;
        var s = document.getElementById('crSize').value;
        var a = document.getElementById('crAddress').value.trim();
        if (!e || !p || !i || !s || !a) { crShake(['crEmail', 'crPhone']); return false; }
        return true;
    }

    function crNext(step) {
        if (step === 2 && !crValidate1()) return;
        if (step === 3 && !crValidate2()) return;
        crShow(step);
    }

    function crPrev(step) { crShow(step); }

    function crShake(ids) {
        ids.forEach(function(id) {
            var el = document.getElementById(id);
            if (el && !el.value.trim()) {
                el.style.borderColor = '#dc2626';
                el.style.animation = 'crShake 0.4s ease';
                setTimeout(function() { el.style.animation = ''; }, 500);
            }
        });
    }

    // Logo Preview
    document.getElementById('crLogoInput').addEventListener('change', function(e) {
        var file = e.target.files[0];
        if (file) {
            var reader = new FileReader();
            reader.onload = function(ev) {
                document.getElementById('crLogoPreview').innerHTML = '<img src="' + ev.target.result + '">';
                document.getElementById('crLogoLabel').classList.add('has-file');
            };
            reader.readAsDataURL(file);
        }
    });

    // Password Toggle
    function crTogglePw(id, toggle) {
        var inp = document.getElementById(id);
        var icon = toggle.querySelector('i');
        if (inp.type === 'password') { inp.type = 'text'; icon.classList.replace('fa-eye', 'fa-eye-slash'); }
        else { inp.type = 'password'; icon.classList.replace('fa-eye-slash', 'fa-eye'); }
    }

    // Password Strength
    function crCheckStrength(pw) {
        var bar = document.getElementById('crPwBar');
        var hint = document.getElementById('crPwHint');
        var score = 0;
        if (pw.length >= 6) score++;
        if (pw.length >= 8) score++;
        if (/[A-Z]/.test(pw)) score++;
        if (/[0-9]/.test(pw)) score++;
        if (/[^A-Za-z0-9]/.test(pw)) score++;
        var colors = ['#dc2626', '#f97316', '#eab308', '#22c55e', '#059669'];
        var labels = ['Very Weak', 'Weak', 'Fair', 'Strong', 'Very Strong'];
        var idx = Math.min(score, 4);
        if (pw.length === 0) { bar.style.width = '0'; hint.textContent = ''; }
        else { bar.style.width = ((idx + 1) * 20) + '%'; bar.style.background = colors[idx]; hint.textContent = labels[idx]; hint.style.color = colors[idx]; }
        crMatchPw();
        crCheckReady();
    }

    // Password Match
    function crMatchPw() {
        var pw = document.getElementById('crPw').value;
        var cpw = document.getElementById('crCpw').value;
        var hint = document.getElementById('crPwMatch');
        if (cpw.length === 0) { hint.textContent = ''; return; }
        if (pw === cpw) { hint.innerHTML = '<i class="fas fa-check-circle"></i> Passwords match'; hint.style.color = '#059669'; }
        else { hint.innerHTML = '<i class="fas fa-times-circle"></i> Passwords do not match'; hint.style.color = '#dc2626'; }
        crCheckReady();
    }

    // Submit Ready
    function crCheckReady() {
        var pw = document.getElementById('crPw').value;
        var cpw = document.getElementById('crCpw').value;
        var t = document.getElementById('crTerms').checked;
        var btn = document.getElementById('crSubmitBtn');
        btn.disabled = !(pw.length >= 8 && pw === cpw && t);
    }

    document.getElementById('crTerms').addEventListener('change', crCheckReady);

    // Shake animation
    var s = document.createElement('style');
    s.textContent = '@keyframes crShake{0%,100%{transform:translateX(0)}25%{transform:translateX(-6px)}75%{transform:translateX(6px)}}';
    document.head.appendChild(s);
    </script>
</body>
</html>
