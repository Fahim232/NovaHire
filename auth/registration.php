<?php
// Core setup: session, DB, BASE_URL, helpers
require_once __DIR__ . '/../includes/bootstrap.php';
    require_once __DIR__ . '/../admin/dbcon.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Create Account | NovaHire</title>
    <?php include '../includes/links.php'; ?>
    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow-y: auto; 
            padding: 40px 0;
        }

        .reg-container {
            width: 100%;
            max-width: 1000px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 30px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.15);
            overflow: hidden;
            display: flex;
            position: relative;
            margin: 20px;
        }

        .reg-visual {
            width: 40%;
            background: linear-gradient(135deg, #0984e3 0%, #00cec9 100%);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 40px;
            text-align: center;
            color: white;
            position: relative;
        }

        .reg-visual::before {
            content: '';
            position: absolute;
            width: 400px;
            height: 400px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            top: -100px;
            left: -100px;
        }

        .reg-visual h2 { font-size: 2.5rem; font-weight: 800; margin-bottom: 20px; position: relative; z-index: 2; }
        .reg-visual p { font-size: 1.1rem; opacity: 0.9; position: relative; z-index: 2; }
        
        .reg-btn-outline {
            border: 2px solid white; color: white; background: transparent;
            padding: 10px 30px; border-radius: 50px; font-weight: 700;
            margin-top: 30px; transition: all 0.3s; position: relative; z-index: 2;
        }
        .reg-btn-outline:hover {
            background: white; color: #0984e3; transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }

        .reg-form-side { width: 60%; padding: 50px; background: white; overflow-y: auto; max-height: 90vh; }
        .form-title { color: #2d3436; font-weight: 800; font-size: 2rem; margin-bottom: 30px; }

        .input-group-modern { position: relative; margin-bottom: 20px; }
        .input-group-modern > i {
            position: absolute; left: 20px; top: 50%; transform: translateY(-50%);
            color: #b2bec3; transition: color 0.3s; z-index: 5;
        }

        .form-control-modern {
            width: 100%; padding: 15px 15px 15px 50px; border: 2px solid #f1f2f6;
            border-radius: 15px; font-size: 0.95rem; color: #2d3436; font-weight: 500;
            transition: all 0.3s; background: #fdfdfd; height: auto;
        }
        .form-control-modern:focus { border-color: #00cec9; background: white; outline: none; box-shadow: 0 5px 20px rgba(0, 206, 201, 0.1); }

        .btn-modern {
            background: linear-gradient(to right, #0984e3, #00cec9); color: white; border: none;
            padding: 15px; border-radius: 15px; font-weight: 700; letter-spacing: 1px;
            text-transform: uppercase; font-size: 0.9rem; transition: transform 0.3s;
            width: 100%; cursor: pointer; margin-top: 10px;
        }
        .btn-modern:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(9, 132, 227, 0.3); color: white; }

        /* Skill Selector */
        .skills-section { margin-bottom: 20px; }
        .skills-label { font-size: 0.85rem; font-weight: 700; color: #636e72; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 10px; display: flex; align-items: center; gap: 8px; }
        .skills-label i { color: #00cec9; }
        .selected-skills-display {
            display: flex; flex-wrap: wrap; gap: 6px; min-height: 38px; padding: 10px 14px;
            border: 2px solid #f1f2f6; border-radius: 12px; background: #f8f9fa;
            margin-bottom: 10px; transition: all 0.3s;
        }
        .selected-skills-display:focus-within { border-color: #00cec9; box-shadow: 0 5px 20px rgba(0, 206, 201, 0.1); }
        .selected-skill-tag {
            display: inline-flex; align-items: center; gap: 5px;
            background: linear-gradient(135deg, #0984e3, #00cec9); color: white;
            padding: 4px 12px; border-radius: 20px; font-size: 0.78rem; font-weight: 600;
            animation: tagPop 0.2s ease;
        }
        .selected-skill-tag .remove-skill { cursor: pointer; opacity: 0.8; font-size: 0.7rem; margin-left: 2px; }
        .selected-skill-tag .remove-skill:hover { opacity: 1; }
        @keyframes tagPop { from { transform: scale(0.8); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        .no-skills-msg { color: #b2bec3; font-size: 0.85rem; font-style: italic; }

        .skill-categories-wrap {
            border: 2px solid #f1f2f6; border-radius: 12px; overflow: hidden;
            max-height: 0; transition: max-height 0.3s ease, opacity 0.3s ease; opacity: 0;
        }
        .skill-categories-wrap.open { max-height: 500px; opacity: 1; overflow-y: auto; }
        .skill-cat-header {
            padding: 10px 16px; font-size: 0.78rem; font-weight: 800; text-transform: uppercase;
            letter-spacing: 0.8px; background: #f1f5f9; color: #475569;
            border-bottom: 1px solid #e2e8f0; position: sticky; top: 0; z-index: 2;
        }
        .skill-cat-header i { margin-right: 6px; }
        .skill-options { display: flex; flex-wrap: wrap; gap: 6px; padding: 10px 14px; }
        .skill-option {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 5px 14px; border-radius: 20px; font-size: 0.8rem; font-weight: 600;
            border: 2px solid #e2e8f0; background: white; color: #475569;
            cursor: pointer; transition: all 0.2s; user-select: none;
        }
        .skill-option:hover { border-color: #0984e3; color: #0984e3; background: #eff6ff; }
        .skill-option.selected { background: linear-gradient(135deg, #0984e3, #00cec9); color: white; border-color: transparent; }
        .skill-option input { display: none; }

        .skills-toggle-btn {
            display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px;
            border: 2px dashed #00cec9; border-radius: 10px; background: transparent;
            color: #00cec9; font-weight: 700; font-size: 0.85rem; cursor: pointer;
            transition: all 0.2s; width: 100%; justify-content: center;
        }
        .skills-toggle-btn:hover { background: #f0fdfa; border-style: solid; }
        .skills-toggle-btn i.toggle-icon { transition: transform 0.3s; }
        .skills-toggle-btn.open i.toggle-icon { transform: rotate(180deg); }

        /* Responsive */
        @media (max-width: 991px) {
            .reg-container { flex-direction: column; max-width: 500px; }
            .reg-visual { width: 100%; height: 200px; padding: 20px; }
            .reg-visual::before { display: none; }
            .reg-form-side { width: 100%; padding: 30px; max-height: none; }
            .reg-visual h2 { font-size: 1.8rem; margin-bottom: 10px; }
            .reg-btn-outline { margin-top: 15px; }
        }

        /* ── Profile Photo Upload ── */
        .reg-photo-section {
            text-align: center;
            margin-bottom: 24px;
        }
        .reg-photo-wrap {
            display: inline-block;
            position: relative;
            cursor: pointer;
        }
        .reg-photo-preview {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            border: 3px dashed #00cec9;
            background: #f0fdfa;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
            overflow: hidden;
        }
        .reg-photo-preview:hover {
            border-color: #0984e3;
            background: #eff6ff;
            transform: scale(1.05);
        }
        .reg-photo-preview i {
            font-size: 1.5rem;
            color: #00cec9;
            margin-bottom: 2px;
        }
        .reg-photo-preview span {
            font-size: 0.7rem;
            font-weight: 700;
            color: #636e72;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .reg-photo-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }
        .reg-photo-preview.has-photo i,
        .reg-photo-preview.has-photo span {
            display: none;
        }
        .reg-photo-hint {
            font-size: 0.78rem;
            color: #b2bec3;
            margin-top: 6px;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <?php 
        /**
         * Candidate User Registration Logic
         * 
         * Handles candidate registration form submission, profile picture upload,
         * password hashing, email duplication check, and user database insertion.
         */
        if (isset($_POST['submit'])){
            // CSRF Verification
            require_csrf();
            
            // Extract and sanitize candidate registration details
            $username  = trim($_POST['username'] ?? '');
            $email     = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
            $phone     = trim($_POST['phone'] ?? '');
            $password  = $_POST['password'] ?? '';
            $cpassword = $_POST['cpassword'] ?? '';
            $degree    = trim($_POST['degree'] ?? '');
            $skills    = trim($_POST['user_skills'] ?? '');

            // Hash password securely using default BCrypt algorithm
            $passEncrypt  = password_hash($password, PASSWORD_BCRYPT);
            $cpassEncrypt = password_hash($cpassword, PASSWORD_BCRYPT);

            // Handle candidate profile photo upload through the hardened helper
            // (content-verified, random filename, stored in the canonical images/ folder)
            $profile_name = '';
            if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                $up = nh_store_upload($_FILES['profile_image'], nh_avatar_dir(), 'image', 'profile');
                if ($up['ok']) {
                    $profile_name = $up['filename'];
                }
                // A bad photo must not block account creation — the account is
                // created without one and the user can add it from their profile.
            }

            // 1. Check for duplicate email using prepared statement
            $email_stmt = mysqli_prepare($con, "SELECT id FROM user_info WHERE email = ?");
            mysqli_stmt_bind_param($email_stmt, "s", $email);
            mysqli_stmt_execute($email_stmt);
            $email_result = mysqli_stmt_get_result($email_stmt);
            $emailcount   = mysqli_num_rows($email_result);
            mysqli_stmt_close($email_stmt);

            if ($emailcount > 0) {
                echo '<div class="alert alert-danger fixed-top text-center m-3 shadow rounded-pill">Email already exists! <button class="close" data-dismiss="alert">&times;</button></div>';
            } else {
                // 2. Validate password match and insert user record
                if ($password === $cpassword) {
                    $ins_stmt = mysqli_prepare($con, "INSERT INTO user_info (username, email, phone, password, cpassword, user_degree, user_skills, profile) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    mysqli_stmt_bind_param($ins_stmt, "ssssssss", $username, $email, $phone, $passEncrypt, $cpassEncrypt, $degree, $skills, $profile_name);
                    $iquery = mysqli_stmt_execute($ins_stmt);
                    mysqli_stmt_close($ins_stmt);

                    if ($iquery) {
                        // Send welcome email
                        send_welcome_email($email, $username);
                        
                        echo "<script>alert('Account Created Successfully!'); window.location.href='login.php';</script>";
                        exit();
                    } else {
                        echo "<script>alert('Registration Failed! Please try again.');</script>";
                    }
                } else {
                    echo "<script>alert('Passwords do not match!');</script>";
                }
            }
        }
    ?>

    <div class="reg-container">
        <div class="reg-visual">
            <h2>Join Us</h2>
            <p>Start your professional journey with us today.</p>
            <a href="login.php" class="reg-btn-outline">Sign In</a>
            <a href="login.php" class="text-white small mt-4 opacity-75"><i class="fas fa-arrow-left mr-1"></i> Back to Login</a>
        </div>
        
        <div class="reg-form-side">
            <h1 class="form-title">Create Account</h1>
            
            <form action="<?php echo htmlentities($_SERVER['PHP_SELF']);?>" method="POST" enctype="multipart/form-data">
                <?php echo csrf_input(); ?>
                
                <!-- Profile Photo Upload -->
                <div class="reg-photo-section">
                    <div class="reg-photo-wrap" id="regPhotoWrap">
                        <div class="reg-photo-preview" id="regPhotoPreview">
                            <i class="fas fa-camera"></i>
                            <span>Add Photo</span>
                        </div>
                        <input type="file" name="profile_image" id="regPhotoInput" accept="image/*" style="display:none;">
                    </div>
                    <div class="reg-photo-hint">Optional — add a profile photo</div>
                </div>

                <div class="input-group-modern">
                    <input name="username" type="text" placeholder="Full Name" class="form-control-modern" required>
                    <i class="fas fa-user"></i>
                </div>

                <div class="input-group-modern">
                    <input name="email" type="email" placeholder="Email Address" class="form-control-modern" required>
                    <i class="fas fa-envelope"></i>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="input-group-modern">
                            <input name="phone" type="text" placeholder="Phone" class="form-control-modern" maxlength="10" required>
                            <i class="fas fa-phone"></i>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="input-group-modern">
                             <select name="degree" class="form-control-modern" required style="appearance: none; -webkit-appearance: none;">
                                <option value="" disabled selected>Select Degree</option>
                                <option value="BE/BTech">BE/BTech</option>
                                <option value="ME/MTech">ME/MTech</option>
                                <option value="BCA">BCA</option>
                                <option value="MCA">MCA</option>
                                <option value="BSc">BSc</option>
                                <option value="MSc">MSc</option>
                                <option value="Other">Other</option>
                            </select>
                            <i class="fas fa-graduation-cap"></i>
                        </div>
                    </div>
                </div>

                <!-- Skills Section -->
                <div class="skills-section">
                    <div class="skills-label"><i class="fas fa-code"></i> Your Skills (at least 1 required)</div>
                    
                    <div class="selected-skills-display" id="selectedSkillsDisplay">
                        <span class="no-skills-msg" id="noSkillsMsg">Click below to add your skills</span>
                    </div>
                    
                    <input type="hidden" name="user_skills" id="userSkillsInput" value="">
                    
                    <button type="button" class="skills-toggle-btn" id="skillsToggle" onclick="toggleSkillPanel()">
                        <i class="fas fa-plus-circle"></i> Choose Skills
                        <i class="fas fa-chevron-down toggle-icon" id="toggleIcon"></i>
                    </button>
                    
                    <div class="skill-categories-wrap" id="skillPanel">
                        <div class="skill-cat-header"><i class="fas fa-laptop-code"></i> Programming Languages</div>
                        <div class="skill-options">
                            <label class="skill-option" onclick="toggleSkill(this)"><input type="checkbox" value="PHP">PHP</label>
                            <label class="skill-option" onclick="toggleSkill(this)"><input type="checkbox" value="Java">Java</label>
                            <label class="skill-option" onclick="toggleSkill(this)"><input type="checkbox" value="Python">Python</label>
                            <label class="skill-option" onclick="toggleSkill(this)"><input type="checkbox" value="JavaScript">JavaScript</label>
                            <label class="skill-option" onclick="toggleSkill(this)"><input type="checkbox" value="C">C</label>
                            <label class="skill-option" onclick="toggleSkill(this)"><input type="checkbox" value="C++">C++</label>
                            <label class="skill-option" onclick="toggleSkill(this)"><input type="checkbox" value="C#">C#</label>
                            <label class="skill-option" onclick="toggleSkill(this)"><input type="checkbox" value="Ruby">Ruby</label>
                            <label class="skill-option" onclick="toggleSkill(this)"><input type="checkbox" value="Go">Go</label>
                            <label class="skill-option" onclick="toggleSkill(this)"><input type="checkbox" value="Swift">Swift</label>
                            <label class="skill-option" onclick="toggleSkill(this)"><input type="checkbox" value="Kotlin">Kotlin</label>
                            <label class="skill-option" onclick="toggleSkill(this)"><input type="checkbox" value="TypeScript">TypeScript</label>
                        </div>

                        <div class="skill-cat-header"><i class="fas fa-code"></i> Web Development</div>
                        <div class="skill-options">
                            <label class="skill-option" onclick="toggleSkill(this)"><input type="checkbox" value="HTML">HTML</label>
                            <label class="skill-option" onclick="toggleSkill(this)"><input type="checkbox" value="CSS">CSS</label>
                            <label class="skill-option" onclick="toggleSkill(this)"><input type="checkbox" value="React">React</label>
                            <label class="skill-option" onclick="toggleSkill(this)"><input type="checkbox" value="Angular">Angular</label>
                            <label class="skill-option" onclick="toggleSkill(this)"><input type="checkbox" value="Vue.js">Vue.js</label>
                            <label class="skill-option" onclick="toggleSkill(this)"><input type="checkbox" value="Node.js">Node.js</label>
                            <label class="skill-option" onclick="toggleSkill(this)"><input type="checkbox" value="Bootstrap">Bootstrap</label>
                            <label class="skill-option" onclick="toggleSkill(this)"><input type="checkbox" value="jQuery">jQuery</label>
                            <label class="skill-option" onclick="toggleSkill(this)"><input type="checkbox" value="WordPress">WordPress</label>
                            <label class="skill-option" onclick="toggleSkill(this)"><input type="checkbox" value="Laravel">Laravel</label>
                            <label class="skill-option" onclick="toggleSkill(this)"><input type="checkbox" value="Django">Django</label>
                            <label class="skill-option" onclick="toggleSkill(this)"><input type="checkbox" value="Spring Boot">Spring Boot</label>
                        </div>

                        <div class="skill-cat-header"><i class="fas fa-database"></i> Data & AI</div>
                        <div class="skill-options">
                            <label class="skill-option" onclick="toggleSkill(this)"><input type="checkbox" value="DataScience">DataScience</label>
                            <label class="skill-option" onclick="toggleSkill(this)"><input type="checkbox" value="Machine Learning">Machine Learning</label>
                            <label class="skill-option" onclick="toggleSkill(this)"><input type="checkbox" value="AI">AI</label>
                            <label class="skill-option" onclick="toggleSkill(this)"><input type="checkbox" value="SQL">SQL</label>
                            <label class="skill-option" onclick="toggleSkill(this)"><input type="checkbox" value="NoSQL">NoSQL</label>
                            <label class="skill-option" onclick="toggleSkill(this)"><input type="checkbox" value="MongoDB">MongoDB</label>
                            <label class="skill-option" onclick="toggleSkill(this)"><input type="checkbox" value="PostgreSQL">PostgreSQL</label>
                            <label class="skill-option" onclick="toggleSkill(this)"><input type="checkbox" value="TensorFlow">TensorFlow</label>
                            <label class="skill-option" onclick="toggleSkill(this)"><input type="checkbox" value="Power BI">Power BI</label>
                            <label class="skill-option" onclick="toggleSkill(this)"><input type="checkbox" value="Tableau">Tableau</label>
                        </div>

                        <div class="skill-cat-header"><i class="fas fa-palette"></i> Design & Creative</div>
                        <div class="skill-options">
                            <label class="skill-option" onclick="toggleSkill(this)"><input type="checkbox" value="UI/UX">UI/UX</label>
                            <label class="skill-option" onclick="toggleSkill(this)"><input type="checkbox" value="Figma">Figma</label>
                            <label class="skill-option" onclick="toggleSkill(this)"><input type="checkbox" value="Adobe XD">Adobe XD</label>
                            <label class="skill-option" onclick="toggleSkill(this)"><input type="checkbox" value="Photoshop">Photoshop</label>
                            <label class="skill-option" onclick="toggleSkill(this)"><input type="checkbox" value="Illustrator">Illustrator</label>
                            <label class="skill-option" onclick="toggleSkill(this)"><input type="checkbox" value="Frontend">Frontend</label>
                        </div>

                        <div class="skill-cat-header"><i class="fas fa-briefcase"></i> Business & Management</div>
                        <div class="skill-options">
                            <label class="skill-option" onclick="toggleSkill(this)"><input type="checkbox" value="Marketing">Marketing</label>
                            <label class="skill-option" onclick="toggleSkill(this)"><input type="checkbox" value="Finance">Finance</label>
                            <label class="skill-option" onclick="toggleSkill(this)"><input type="checkbox" value="Sales">Sales</label>
                            <label class="skill-option" onclick="toggleSkill(this)"><input type="checkbox" value="HR">HR</label>
                            <label class="skill-option" onclick="toggleSkill(this)"><input type="checkbox" value="Project Management">Project Management</label>
                            <label class="skill-option" onclick="toggleSkill(this)"><input type="checkbox" value="Business Analysis">Business Analysis</label>
                            <label class="skill-option" onclick="toggleSkill(this)"><input type="checkbox" value="Consulting">Consulting</label>
                        </div>

                        <div class="skill-cat-header"><i class="fas fa-tools"></i> DevOps & Cloud</div>
                        <div class="skill-options">
                            <label class="skill-option" onclick="toggleSkill(this)"><input type="checkbox" value="AWS">AWS</label>
                            <label class="skill-option" onclick="toggleSkill(this)"><input type="checkbox" value="Docker">Docker</label>
                            <label class="skill-option" onclick="toggleSkill(this)"><input type="checkbox" value="Kubernetes">Kubernetes</label>
                            <label class="skill-option" onclick="toggleSkill(this)"><input type="checkbox" value="Git">Git</label>
                            <label class="skill-option" onclick="toggleSkill(this)"><input type="checkbox" value="Linux">Linux</label>
                            <label class="skill-option" onclick="toggleSkill(this)"><input type="checkbox" value="CI/CD">CI/CD</label>
                            <label class="skill-option" onclick="toggleSkill(this)"><input type="checkbox" value="Azure">Azure</label>
                            <label class="skill-option" onclick="toggleSkill(this)"><input type="checkbox" value="GCP">GCP</label>
                        </div>

                        <div class="skill-cat-header"><i class="fas fa-comments"></i> Soft Skills</div>
                        <div class="skill-options">
                            <label class="skill-option" onclick="toggleSkill(this)"><input type="checkbox" value="Communication">Communication</label>
                            <label class="skill-option" onclick="toggleSkill(this)"><input type="checkbox" value="Leadership">Leadership</label>
                            <label class="skill-option" onclick="toggleSkill(this)"><input type="checkbox" value="Team Management">Team Management</label>
                            <label class="skill-option" onclick="toggleSkill(this)"><input type="checkbox" value="Problem Solving">Problem Solving</label>
                            <label class="skill-option" onclick="toggleSkill(this)"><input type="checkbox" value="Critical Thinking">Critical Thinking</label>
                            <label class="skill-option" onclick="toggleSkill(this)"><input type="checkbox" value="Time Management">Time Management</label>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="input-group-modern">
                            <input name="password" type="password" placeholder="Password" class="form-control-modern" required>
                            <i class="fas fa-lock"></i>
                        </div>
                    </div>
                    <div class="col-md-6">
                         <div class="input-group-modern">
                            <input name="cpassword" type="password" placeholder="Confirm" class="form-control-modern" required>
                            <i class="fas fa-lock"></i>
                        </div>
                    </div>
                </div>

                <button type="submit" name="submit" class="btn-modern" id="submitBtn">Register Now</button>
            </form>
        </div>
    </div>

    <script>
    let selectedSkills = [];

    function toggleSkillPanel() {
        const panel = document.getElementById('skillPanel');
        const btn = document.getElementById('skillsToggle');
        panel.classList.toggle('open');
        btn.classList.toggle('open');
    }

    function toggleSkill(label) {
        const checkbox = label.querySelector('input');
        const skill = checkbox.value;
        
        if (checkbox.checked) {
            selectedSkills.push(skill);
            label.classList.add('selected');
        } else {
            selectedSkills = selectedSkills.filter(s => s !== skill);
            label.classList.remove('selected');
        }
        updateSkillsDisplay();
    }

    function removeSkill(skill) {
        selectedSkills = selectedSkills.filter(s => s !== skill);
        document.querySelectorAll('.skill-option input').forEach(cb => {
            if (cb.value === skill) {
                cb.checked = false;
                cb.closest('.skill-option').classList.remove('selected');
            }
        });
        updateSkillsDisplay();
    }

    function updateSkillsDisplay() {
        const display = document.getElementById('selectedSkillsDisplay');
        const input = document.getElementById('userSkillsInput');
        const noMsg = document.getElementById('noSkillsMsg');
        
        input.value = selectedSkills.join(', ');
        
        display.innerHTML = '';
        if (selectedSkills.length === 0) {
            display.innerHTML = '<span class="no-skills-msg" id="noSkillsMsg">Click below to add your skills</span>';
        } else {
            selectedSkills.forEach(skill => {
                display.innerHTML += '<span class="selected-skill-tag">' + skill + ' <span class="remove-skill" onclick="removeSkill(\'' + skill + '\')"><i class="fas fa-times-circle"></i></span></span>';
            });
        }
    }

    document.getElementById('submitBtn').addEventListener('click', function(e) {
        if (selectedSkills.length === 0) {
            e.preventDefault();
            alert('Please select at least one skill.');
            toggleSkillPanel();
        }
    });
    </script>

    <script>
    // Profile photo upload preview
    var regPhotoWrap = document.getElementById('regPhotoWrap');
    var regPhotoInput = document.getElementById('regPhotoInput');
    var regPhotoPreview = document.getElementById('regPhotoPreview');

    regPhotoWrap.addEventListener('click', function() {
        regPhotoInput.click();
    });

    regPhotoInput.addEventListener('change', function(e) {
        var file = e.target.files[0];
        if (!file) return;
        var reader = new FileReader();
        reader.onload = function(ev) {
            regPhotoPreview.innerHTML = '<img src="' + ev.target.result + '" alt="Photo">';
            regPhotoPreview.classList.add('has-photo');
        };
        reader.readAsDataURL(file);
    });
    </script>
</body>
</html>
