<?php
/**
 * Employer / Company Registration Portal
 * 
 * Handles company account registration, logo upload processing,
 * email duplication check, BCrypt password hashing, and database insertion.
 */

// Include database connection
include 'admin/dbcon.php';

// Process company registration form submission
if (isset($_POST['register'])) {
    // Extract and sanitize company form fields
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

    // 1. Check for duplicate company email using prepared statement
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
    } elseif (strlen($password) < 6) {
        $error_msg = 'Password must be at least 6 characters!';
    } elseif (!$terms) {
        $error_msg = 'You must agree to the Terms of Service!';
    } else {
        // Hash company account password securely
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Process company logo file upload if attached
        $logo_name = null;
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $file_type = $_FILES['logo']['type'];
            if (in_array($file_type, $allowed) && $_FILES['logo']['size'] <= 5 * 1024 * 1024) {
                $ext = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
                $logo_name = 'logo_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                
                $upload_path = __DIR__ . '/uploads/company_logos/';
                if (!is_dir($upload_path)) {
                    mkdir($upload_path, 0755, true);
                }
                move_uploaded_file($_FILES['logo']['tmp_name'], $upload_path . $logo_name);
            } else {
                $error_msg = 'Logo must be JPG, PNG, GIF or WebP (max 5MB).';
                $error = true;
            }
        }

        // Insert new active company account into database using prepared statement
        if (!isset($error)) {
            $status = 'active';
            $ins_stmt = mysqli_prepare($con, "INSERT INTO companies (company_name, company_email, company_phone, company_address, company_website, industry, company_size, description, logo, password, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($ins_stmt, "sssssssssss", $company_name, $email, $phone, $address, $website, $industry, $company_size, $description, $logo_name, $hashed_password, $status);
            
            if (mysqli_stmt_execute($ins_stmt)) {
                $success_msg = 'Company registered successfully!';
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
    <title>Register Company | NovaHire</title>
    <?php include 'links.php'; ?>
    <style>
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #334155 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px 15px;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }

        .reg-wrapper {
            width: 100%;
            max-width: 780px;
        }

        .reg-card {
            background: white;
            border-radius: 24px;
            box-shadow: 0 25px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }

        .reg-top {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            padding: 35px 40px 30px;
            color: white;
            position: relative;
            overflow: hidden;
        }
        .reg-top::before {
            content: '';
            position: absolute;
            width: 200px;
            height: 200px;
            background: rgba(255,255,255,0.08);
            border-radius: 50%;
            top: -60px;
            right: -40px;
        }
        .reg-top::after {
            content: '';
            position: absolute;
            width: 120px;
            height: 120px;
            background: rgba(255,255,255,0.06);
            border-radius: 50%;
            bottom: -30px;
            left: 30%;
        }
        .reg-top h2 {
            font-size: 1.6rem;
            font-weight: 800;
            margin: 0 0 6px;
            position: relative;
            z-index: 2;
        }
        .reg-top p {
            margin: 0;
            opacity: 0.85;
            font-size: 0.95rem;
            position: relative;
            z-index: 2;
        }

        /* Step Indicators */
        .step-bar {
            display: flex;
            justify-content: center;
            gap: 0;
            padding: 20px 40px 0;
            background: white;
        }
        .step-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.82rem;
            font-weight: 600;
            color: #cbd5e1;
            position: relative;
        }
        .step-item.active { color: #4f46e5; }
        .step-item.done { color: #10b981; }
        .step-num {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: 700;
            background: #f1f5f9;
            color: #94a3b8;
            flex-shrink: 0;
        }
        .step-item.active .step-num { background: #4f46e5; color: white; }
        .step-item.done .step-num { background: #10b981; color: white; }
        .step-line {
            width: 50px;
            height: 2px;
            background: #e2e8f0;
            margin: 0 8px;
            align-self: center;
        }
        .step-item.done + .step-line { background: #10b981; }

        .reg-body {
            padding: 30px 40px 35px;
        }

        /* Form Steps */
        .form-step { display: none; }
        .form-step.active { display: block; animation: fadeSlide 0.35s ease; }
        @keyframes fadeSlide {
            from { opacity: 0; transform: translateX(20px); }
            to { opacity: 1; transform: translateX(0); }
        }

        .field-group {
            margin-bottom: 20px;
        }
        .field-group label {
            display: block;
            font-size: 0.85rem;
            font-weight: 700;
            color: #334155;
            margin-bottom: 6px;
        }
        .field-group label .req { color: #ef4444; }

        .input-wrap {
            position: relative;
        }
        .input-wrap i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 0.9rem;
            transition: color 0.3s;
            pointer-events: none;
        }
        .input-wrap input,
        .input-wrap textarea,
        .input-wrap select {
            width: 100%;
            padding: 12px 14px 12px 42px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 0.92rem;
            color: #1e293b;
            font-weight: 500;
            transition: all 0.3s;
            background: #f8fafc;
            outline: none;
        }
        .input-wrap input:focus,
        .input-wrap textarea:focus,
        .input-wrap select:focus {
            border-color: #4f46e5;
            background: white;
            box-shadow: 0 0 0 3px rgba(79,70,229,0.1);
        }
        .input-wrap input:focus + i,
        .input-wrap textarea:focus ~ i { color: #4f46e5; }

        .input-wrap select { padding-left: 42px; cursor: pointer; appearance: none; }
        .input-wrap textarea { resize: vertical; min-height: 80px; padding-top: 12px; }

        .input-plain {
            width: 100%;
            padding: 12px 14px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 0.92rem;
            color: #1e293b;
            font-weight: 500;
            transition: all 0.3s;
            background: #f8fafc;
            outline: none;
        }
        .input-plain:focus {
            border-color: #4f46e5;
            background: white;
            box-shadow: 0 0 0 3px rgba(79,70,229,0.1);
        }

        .pw-toggle {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #94a3b8;
            font-size: 1rem;
            transition: color 0.3s;
        }
        .pw-toggle:hover { color: #4f46e5; }

        .pw-strength {
            height: 4px;
            border-radius: 4px;
            margin-top: 8px;
            background: #e2e8f0;
            overflow: hidden;
            transition: all 0.3s;
        }
        .pw-strength-bar {
            height: 100%;
            border-radius: 4px;
            width: 0;
            transition: all 0.4s;
        }

        /* Logo Upload */
        .logo-upload {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 20px;
            border: 2px dashed #e2e8f0;
            border-radius: 16px;
            transition: all 0.3s;
            cursor: pointer;
            background: #f8fafc;
        }
        .logo-upload:hover { border-color: #4f46e5; background: #f0f0ff; }
        .logo-upload.has-file { border-color: #10b981; background: #f0fdf4; }
        .logo-preview {
            width: 72px;
            height: 72px;
            border-radius: 16px;
            background: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            color: #94a3b8;
            overflow: hidden;
            flex-shrink: 0;
        }
        .logo-preview img { width: 100%; height: 100%; object-fit: cover; }
        .logo-info h6 { font-weight: 700; color: #334155; margin: 0 0 3px; font-size: 0.9rem; }
        .logo-info small { color: #94a3b8; font-size: 0.78rem; }

        /* Terms */
        .terms-check {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 14px 16px;
            background: #f8fafc;
            border-radius: 12px;
            border: 2px solid #e2e8f0;
            cursor: pointer;
            transition: all 0.3s;
        }
        .terms-check:hover { border-color: #4f46e5; }
        .terms-check input[type="checkbox"] {
            width: 18px;
            height: 18px;
            margin-top: 2px;
            accent-color: #4f46e5;
            flex-shrink: 0;
        }
        .terms-check label {
            font-size: 0.85rem;
            color: #475569;
            line-height: 1.5;
            cursor: pointer;
            margin: 0;
        }
        .terms-check a { color: #4f46e5; font-weight: 600; }

        /* Buttons */
        .btn-row {
            display: flex;
            gap: 12px;
            margin-top: 25px;
        }
        .btn-next, .btn-submit {
            flex: 1;
            padding: 14px;
            border: none;
            border-radius: 12px;
            font-weight: 700;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-next {
            background: #f1f5f9;
            color: #475569;
        }
        .btn-next:hover { background: #e2e8f0; }
        .btn-submit {
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            color: white;
        }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(79,70,229,0.35); }
        .btn-back {
            flex: 0.6;
            padding: 14px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            background: white;
            font-weight: 700;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.3s;
            color: #475569;
        }
        .btn-back:hover { border-color: #4f46e5; color: #4f46e5; }

        .field-hint {
            font-size: 0.78rem;
            color: #94a3b8;
            margin-top: 4px;
        }

        /* Alert Messages */
        .alert-msg {
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 0.88rem;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: fadeSlide 0.3s ease;
        }
        .alert-msg.error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
        .alert-msg.success { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }

        .login-link {
            text-align: center;
            margin-top: 20px;
            font-size: 0.88rem;
            color: #64748b;
        }
        .login-link a { color: #4f46e5; font-weight: 700; text-decoration: none; }
        .login-link a:hover { text-decoration: underline; }

        @media (max-width: 600px) {
            .reg-top { padding: 25px 20px 20px; }
            .reg-top h2 { font-size: 1.3rem; }
            .reg-body { padding: 20px; }
            .step-bar { padding: 15px 20px 0; }
            .step-item { font-size: 0.72rem; }
            .step-line { width: 25px; }
            .logo-upload { flex-direction: column; text-align: center; }
            .btn-row { flex-direction: column-reverse; }
            .btn-back { flex: 1; }
        }
    </style>
</head>
<body>
    <div class="reg-wrapper">
        <div class="reg-card">
            <div class="reg-top">
                <h2><i class="fas fa-building mr-2"></i>Register Your Company</h2>
                <p>Create an account and start hiring the best talent</p>
            </div>

            <!-- Step Indicators -->
            <div class="step-bar">
                <div class="step-item active" id="stepIndicator1">
                    <span class="step-num">1</span>
                    <span class="step-label">Company Info</span>
                </div>
                <div class="step-line"></div>
                <div class="step-item" id="stepIndicator2">
                    <span class="step-num">2</span>
                    <span class="step-label">Contact & Industry</span>
                </div>
                <div class="step-line"></div>
                <div class="step-item" id="stepIndicator3">
                    <span class="step-num">3</span>
                    <span class="step-label">Account Setup</span>
                </div>
            </div>

            <div class="reg-body">
                <?php if (isset($error_msg)): ?>
                    <div class="alert-msg error"><i class="fas fa-exclamation-circle"></i><?php echo $error_msg; ?></div>
                <?php endif; ?>
                <?php if (isset($success_msg)): ?>
                    <div class="alert-msg success"><i class="fas fa-check-circle"></i><?php echo $success_msg; ?>
                        Redirecting to login... <i class="fas fa-spinner fa-spin ml-2"></i>
                    </div>
                    <script>setTimeout(function(){ window.location.href='auth/login.php'; }, 2000);</script>
                <?php endif; ?>

                <form method="POST" action="" enctype="multipart/form-data" id="regForm">

                    <!-- Step 1: Company Info -->
                    <div class="form-step active" id="step1">
                        <div class="field-group">
                            <label>Company Name <span class="req">*</span></label>
                            <div class="input-wrap">
                                <input type="text" name="company_name" placeholder="e.g. Tech Solutions Inc." required maxlength="255" id="companyName">
                                <i class="fas fa-building"></i>
                            </div>
                        </div>

                        <div class="field-group">
                            <label>Company Logo <small style="font-weight:400; color:#94a3b8;">(optional)</small></label>
                            <label class="logo-upload" for="logoInput" id="logoLabel">
                                <div class="logo-preview" id="logoPreview"><i class="fas fa-cloud-upload-alt"></i></div>
                                <div class="logo-info">
                                    <h6>Click to upload logo</h6>
                                    <small>JPG, PNG, GIF or WebP - Max 5MB</small>
                                </div>
                            </label>
                            <input type="file" name="logo" id="logoInput" accept="image/*" style="display:none;">
                        </div>

                        <div class="field-group">
                            <label>Company Description <span class="req">*</span></label>
                            <div class="input-wrap">
                                <textarea name="description" rows="3" placeholder="Tell us about your company, mission, and culture..." required id="companyDesc"></textarea>
                                <i class="fas fa-info-circle" style="top: 30px; transform: none;"></i>
                            </div>
                        </div>

                        <div class="btn-row">
                            <button type="button" class="btn-next" onclick="nextStep(2)">Continue <i class="fas fa-arrow-right"></i></button>
                        </div>
                    </div>

                    <!-- Step 2: Contact & Industry -->
                    <div class="form-step" id="step2">
                        <div class="field-group">
                            <label>Company Email <span class="req">*</span></label>
                            <div class="input-wrap">
                                <input type="email" name="email" placeholder="hr@company.com" required id="companyEmail">
                                <i class="fas fa-envelope"></i>
                            </div>
                        </div>

                        <div class="field-group">
                            <label>Phone Number <span class="req">*</span></label>
                            <div class="input-wrap">
                                <input type="tel" name="phone" placeholder="+880 1XXXXXXXXX" required id="companyPhone">
                                <i class="fas fa-phone"></i>
                            </div>
                        </div>

                        <div class="field-group">
                            <label>Website <small style="font-weight:400; color:#94a3b8;">(optional)</small></label>
                            <div class="input-wrap">
                                <input type="url" name="website" placeholder="https://example.com">
                                <i class="fas fa-globe"></i>
                            </div>
                        </div>

                        <div class="field-group">
                            <label>Company Address <span class="req">*</span></label>
                            <div class="input-wrap">
                                <textarea name="address" rows="2" placeholder="Street, City, Country" required></textarea>
                                <i class="fas fa-map-marker-alt" style="top: 30px; transform: none;"></i>
                            </div>
                        </div>

                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                            <div class="field-group" style="margin:0;">
                                <label>Industry <span class="req">*</span></label>
                                <div class="input-wrap">
                                    <select name="industry" required>
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

                            <div class="field-group" style="margin:0;">
                                <label>Company Size <span class="req">*</span></label>
                                <div class="input-wrap">
                                    <select name="company_size" required>
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

                        <div class="btn-row">
                            <button type="button" class="btn-back" onclick="prevStep(1)"><i class="fas fa-arrow-left"></i> Back</button>
                            <button type="button" class="btn-next" onclick="nextStep(3)">Continue <i class="fas fa-arrow-right"></i></button>
                        </div>
                    </div>

                    <!-- Step 3: Account Setup -->
                    <div class="form-step" id="step3">
                        <div class="field-group">
                            <label>Password <span class="req">*</span></label>
                            <div class="input-wrap">
                                <input type="password" name="password" placeholder="Min. 6 characters" required minlength="6" id="regPassword" oninput="checkStrength(this.value)">
                                <i class="fas fa-lock"></i>
                                <span class="pw-toggle" onclick="togglePw('regPassword', this)"><i class="fas fa-eye"></i></span>
                            </div>
                            <div class="pw-strength"><div class="pw-strength-bar" id="pwBar"></div></div>
                            <div class="field-hint" id="pwHint"></div>
                        </div>

                        <div class="field-group">
                            <label>Confirm Password <span class="req">*</span></label>
                            <div class="input-wrap">
                                <input type="password" name="cpassword" placeholder="Re-enter password" required minlength="6" id="regCpw" oninput="matchPw()">
                                <i class="fas fa-lock"></i>
                                <span class="pw-toggle" onclick="togglePw('regCpw', this)"><i class="fas fa-eye"></i></span>
                            </div>
                            <div class="field-hint" id="pwMatch"></div>
                        </div>

                        <div class="terms-check" style="margin-top: 10px;">
                            <input type="checkbox" name="terms" id="termsCheck" required>
                            <label for="termsCheck">I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a>. I confirm that I am authorized to register this company.</label>
                        </div>

                        <div class="btn-row">
                            <button type="button" class="btn-back" onclick="prevStep(2)"><i class="fas fa-arrow-left"></i> Back</button>
                            <button type="submit" name="register" class="btn-submit" id="submitBtn" disabled>
                                <i class="fas fa-check-circle"></i> Create Account
                            </button>
                        </div>
                    </div>
                </form>

                <div class="login-link">
                    Already have an account? <a href="auth/login.php">Sign in here</a>
                </div>
            </div>
        </div>
    </div>

    <script>
    let currentStep = 1;

    function showStep(step) {
        document.querySelectorAll('.form-step').forEach(s => s.classList.remove('active'));
        document.getElementById('step' + step).classList.add('active');

        for (let i = 1; i <= 3; i++) {
            const ind = document.getElementById('stepIndicator' + i);
            ind.classList.remove('active', 'done');
            if (i < step) ind.classList.add('done');
            else if (i === step) ind.classList.add('active');
        }
        currentStep = step;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function validateStep1() {
        const name = document.getElementById('companyName').value.trim();
        const desc = document.getElementById('companyDesc').value.trim();
        if (!name || !desc) { shakeEmpty(['companyName', 'companyDesc']); return false; }
        return true;
    }

    function validateStep2() {
        const email = document.getElementById('companyEmail').value.trim();
        const phone = document.getElementById('companyPhone').value.trim();
        const industry = document.querySelector('#step2 select[name="industry"]').value;
        const size = document.querySelector('#step2 select[name="company_size"]').value;
        const address = document.querySelector('#step2 textarea[name="address"]').value.trim();
        if (!email || !phone || !industry || !size || !address) {
            shakeEmpty(['companyEmail', 'companyPhone']);
            return false;
        }
        return true;
    }

    function nextStep(step) {
        if (step === 2 && !validateStep1()) return;
        if (step === 3 && !validateStep2()) return;
        showStep(step);
    }

    function prevStep(step) { showStep(step); }

    function shakeEmpty(ids) {
        ids.forEach(id => {
            const el = document.getElementById(id);
            if (el && !el.value.trim()) {
                el.style.borderColor = '#ef4444';
                el.style.animation = 'shake 0.4s ease';
                setTimeout(() => { el.style.animation = ''; }, 500);
            }
        });
    }

    // Logo Preview
    document.getElementById('logoInput').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(ev) {
                document.getElementById('logoPreview').innerHTML = '<img src="' + ev.target.result + '">';
                document.getElementById('logoLabel').classList.add('has-file');
            };
            reader.readAsDataURL(file);
        }
    });

    // Password Toggle
    function togglePw(inputId, toggle) {
        const inp = document.getElementById(inputId);
        const icon = toggle.querySelector('i');
        if (inp.type === 'password') {
            inp.type = 'text';
            icon.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            inp.type = 'password';
            icon.classList.replace('fa-eye-slash', 'fa-eye');
        }
    }

    // Password Strength
    function checkStrength(pw) {
        const bar = document.getElementById('pwBar');
        const hint = document.getElementById('pwHint');
        let score = 0;
        if (pw.length >= 6) score++;
        if (pw.length >= 8) score++;
        if (/[A-Z]/.test(pw)) score++;
        if (/[0-9]/.test(pw)) score++;
        if (/[^A-Za-z0-9]/.test(pw)) score++;

        const colors = ['#ef4444', '#f97316', '#eab308', '#22c55e', '#10b981'];
        const labels = ['Very Weak', 'Weak', 'Fair', 'Strong', 'Very Strong'];
        const idx = Math.min(score, 4);

        if (pw.length === 0) {
            bar.style.width = '0';
            hint.textContent = '';
        } else {
            bar.style.width = ((idx + 1) * 20) + '%';
            bar.style.background = colors[idx];
            hint.textContent = labels[idx];
            hint.style.color = colors[idx];
        }
        matchPw();
        checkSubmitReady();
    }

    // Password Match
    function matchPw() {
        const pw = document.getElementById('regPassword').value;
        const cpw = document.getElementById('regCpw').value;
        const hint = document.getElementById('pwMatch');
        if (cpw.length === 0) { hint.textContent = ''; return; }
        if (pw === cpw) {
            hint.innerHTML = '<i class="fas fa-check-circle"></i> Passwords match';
            hint.style.color = '#10b981';
        } else {
            hint.innerHTML = '<i class="fas fa-times-circle"></i> Passwords do not match';
            hint.style.color = '#ef4444';
        }
        checkSubmitReady();
    }

    // Submit Button State
    function checkSubmitReady() {
        const pw = document.getElementById('regPassword').value;
        const cpw = document.getElementById('regCpw').value;
        const terms = document.getElementById('termsCheck').checked;
        const btn = document.getElementById('submitBtn');
        btn.disabled = !(pw.length >= 6 && pw === cpw && terms);
    }

    document.getElementById('termsCheck').addEventListener('change', checkSubmitReady);

    // Shake animation
    const style = document.createElement('style');
    style.textContent = '@keyframes shake { 0%,100%{transform:translateX(0)} 25%{transform:translateX(-6px)} 75%{transform:translateX(6px)} }';
    document.head.appendChild(style);
    </script>
</body>
</html>
