<?php
    session_start();
    include '../admin/dbcon.php';

    // Check if company is logged in
    if (!isset($_SESSION['company_id'])) {
        header('Location: ../company_login.php');
        exit;
    }

    $company_id = $_SESSION['company_id'];
    $company_name = $_SESSION['company_name'];

    if (isset($_POST['post_job'])) {
        $job_title = mysqli_real_escape_string($con, $_POST['job_title']);
        $job_category = mysqli_real_escape_string($con, $_POST['job_category']);
        $job_description = mysqli_real_escape_string($con, $_POST['job_description']);
        $requirements = mysqli_real_escape_string($con, $_POST['requirements']);
        $responsibilities = mysqli_real_escape_string($con, $_POST['responsibilities']);
        $location = mysqli_real_escape_string($con, $_POST['location']);
        $employment_type = mysqli_real_escape_string($con, $_POST['employment_type']);
        $salary_range = mysqli_real_escape_string($con, $_POST['salary_range']);
        $experience_required = mysqli_real_escape_string($con, $_POST['experience_required']);
        $skills_required = mysqli_real_escape_string($con, $_POST['skills_required']);
        $deadline = mysqli_real_escape_string($con, $_POST['deadline']);
        $vacancy_count = intval($_POST['vacancy_count']);
        $status = mysqli_real_escape_string($con, $_POST['status']);

        $insert_query = "INSERT INTO company_jobs (company_id, job_title, job_category, job_description, requirements, responsibilities, location, employment_type, salary_range, experience_required, skills_required, deadline, vacancy_count, status) 
                        VALUES ($company_id, '$job_title', '$job_category', '$job_description', '$requirements', '$responsibilities', '$location', '$employment_type', '$salary_range', '$experience_required', '$skills_required', '$deadline', $vacancy_count, '$status')";

        if (mysqli_query($con, $insert_query)) {
            $job_id = mysqli_insert_id($con);
            header("Location: post_job.php?success=$job_id");
            exit;
        } else {
            $error_msg = "Failed to post job. Please try again.";
        }
    }

    $categories = [
        'Java', 'Python', 'Frontend', 'PHP', 'Finance', 'Healthcare', 'Education',
        'Engineering', 'Sales', 'HR', 'Legal', 'Media', 'Logistics', 'Consulting',
        'Retail', 'QA', 'Mobile', 'DevOps', 'Data Science', 'UI/UX', 'Other'
    ];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Post Job | Company Dashboard</title>
    <?php include '../includes/links.php'; ?>
    <style>
        :root {
            --pj-bg: #f4f6fb;
            --pj-card: #ffffff;
            --pj-border: #e5e9f2;
            --pj-text: #1e293b;
            --pj-muted: #64748b;
            --pj-primary: #4f46e5;
            --pj-primary-2: #7c3aed;
            --pj-soft: #eef2ff;
            --pj-input: #f8fafc;
            --pj-shadow: 0 10px 30px rgba(15, 23, 42, 0.07);
        }
        [data-theme="dark"] {
            --pj-bg: #0f172a;
            --pj-card: #111827;
            --pj-border: #28334a;
            --pj-text: #e8edff;
            --pj-muted: #94a3b8;
            --pj-primary: #8b5cf6;
            --pj-primary-2: #a78bfa;
            --pj-soft: #1e293b;
            --pj-input: #0d1526;
            --pj-shadow: 0 10px 30px rgba(0, 0, 0, 0.45);
        }

        body {
            background:
                radial-gradient(circle at 8% 12%, rgba(99, 102, 241, 0.10), transparent 28%),
                radial-gradient(circle at 92% 8%, rgba(217, 70, 239, 0.08), transparent 26%),
                var(--pj-bg);
            color: var(--pj-text);
            min-height: 100vh;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }

        .pj-wrap { max-width: 980px; margin: 0 auto; padding: 34px 24px 100px; }

        /* ── Hero ── */
        .pj-hero {
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 55%, #a855f7 100%);
            border-radius: 22px;
            padding: 30px 34px;
            color: #fff;
            box-shadow: 0 20px 40px rgba(79, 70, 229, 0.28);
        }
        .pj-hero::before {
            content: '';
            position: absolute;
            right: -80px; top: -80px;
            width: 260px; height: 260px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.10);
        }
        .pj-hero h1 { font-weight: 800; font-size: 1.75rem; color: #fff; margin: 0 0 6px; }
        .pj-hero p { color: rgba(255, 255, 255, 0.85); margin: 0; font-size: 0.95rem; }
        .pj-steps {
            position: relative; z-index: 1;
            display: flex; gap: 10px; margin-top: 20px; flex-wrap: wrap;
        }
        .pj-step {
            display: inline-flex; align-items: center; gap: 9px;
            background: rgba(255, 255, 255, 0.16);
            border: 1px solid rgba(255, 255, 255, 0.28);
            border-radius: 30px;
            padding: 8px 16px;
            font-size: 0.82rem; font-weight: 600;
        }
        .pj-step .n {
            width: 22px; height: 22px;
            border-radius: 50%;
            background: #fff; color: #4f46e5;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 0.72rem; font-weight: 800;
        }

        /* ── Section cards ── */
        .pj-section {
            background: var(--pj-card);
            border: 1px solid var(--pj-border);
            border-radius: 18px;
            padding: 26px 28px;
            margin-top: 20px;
            box-shadow: var(--pj-shadow);
            animation: pjIn .4s ease both;
        }
        @keyframes pjIn {
            from { opacity: 0; transform: translateY(14px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .pj-section-head {
            display: flex; align-items: center; gap: 14px;
            margin-bottom: 22px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--pj-border);
        }
        .pj-section-head .ico {
            width: 44px; height: 44px;
            border-radius: 13px;
            background: linear-gradient(135deg, var(--pj-primary), var(--pj-primary-2));
            color: #fff;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem;
            box-shadow: 0 8px 18px rgba(79, 70, 229, 0.3);
        }
        .pj-section-head h3 { font-size: 1.1rem; font-weight: 700; margin: 0; color: var(--pj-text); }
        .pj-section-head p { font-size: 0.82rem; color: var(--pj-muted); margin: 2px 0 0; }

        /* ── Fields ── */
        .pj-field { margin-bottom: 18px; }
        .pj-label {
            display: flex; justify-content: space-between; align-items: center;
            font-size: 0.86rem; font-weight: 600; color: var(--pj-text);
            margin-bottom: 7px;
        }
        .pj-label .req { color: #ef4444; margin-left: 3px; }
        .pj-label .opt { color: var(--pj-muted); font-size: 0.75rem; font-weight: 500; }
        .pj-count { color: var(--pj-muted); font-size: 0.72rem; font-weight: 500; }

        .pj-input {
            width: 100%;
            background: var(--pj-input);
            border: 1.5px solid var(--pj-border);
            color: var(--pj-text);
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 0.92rem;
            outline: none;
            transition: border-color .2s ease, box-shadow .2s ease, background .2s ease;
        }
        .pj-input:focus {
            border-color: var(--pj-primary);
            background: var(--pj-card);
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.14);
        }
        .pj-input::placeholder { color: var(--pj-muted); opacity: .7; }
        select.pj-input {
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3e%3cpath fill='%2394a3b8' d='M1.4 0l4.6 4.6L10.6 0 12 1.4 6 7.4 0 1.4z'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 16px center;
            padding-right: 40px;
        }
        [data-theme="dark"] select.pj-input { background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3e%3cpath fill='%23a78bfa' d='M1.4 0l4.6 4.6L10.6 0 12 1.4 6 7.4 0 1.4z'/%3e%3c/svg%3e"); }

        textarea.pj-input { min-height: 120px; resize: vertical; line-height: 1.6; }
        .pj-hint { font-size: 0.75rem; color: var(--pj-muted); margin-top: 6px; }

        /* AI button */
        .pj-ai-btn {
            display: inline-flex; align-items: center; gap: 7px;
            background: linear-gradient(135deg, #f59e0b, #f97316);
            border: none; color: #fff;
            font-size: 0.8rem; font-weight: 700;
            padding: 8px 16px; border-radius: 30px;
            transition: transform .2s ease, box-shadow .2s ease;
            box-shadow: 0 8px 18px rgba(245, 158, 11, 0.35);
        }
        .pj-ai-btn:hover { transform: translateY(-2px); box-shadow: 0 12px 24px rgba(245, 158, 11, 0.45); }
        .pj-ai-btn:disabled { opacity: .6; cursor: not-allowed; transform: none; }

        .pj-ai-status {
            margin-top: 10px;
            display: none;
            align-items: center; gap: 10px;
            background: var(--pj-soft);
            border: 1px solid var(--pj-border);
            border-radius: 12px;
            padding: 11px 16px;
            font-size: 0.83rem; color: var(--pj-text);
        }
        .pj-ai-status .spin {
            width: 16px; height: 16px;
            border: 2px solid var(--pj-primary);
            border-top-color: transparent;
            border-radius: 50%;
            animation: pjSpin .7s linear infinite;
        }
        @keyframes pjSpin { to { transform: rotate(360deg); } }

        /* Skills tags */
        .pj-tags {
            display: flex; flex-wrap: wrap; gap: 8px;
            margin-top: 10px;
        }
        .pj-tag {
            display: inline-flex; align-items: center; gap: 8px;
            background: var(--pj-soft);
            border: 1px solid var(--pj-border);
            color: var(--pj-text);
            font-size: 0.8rem; font-weight: 600;
            padding: 6px 12px; border-radius: 20px;
            animation: pjTag .2s ease both;
        }
        @keyframes pjTag { from { transform: scale(.85); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        .pj-tag button {
            background: none; border: none; color: var(--pj-muted);
            font-size: 0.8rem; cursor: pointer; line-height: 1;
            padding: 0;
        }
        .pj-tag button:hover { color: #ef4444; }

        /* Toggle status pills */
        .pj-status { display: flex; gap: 10px; }
        .pj-status-pill {
            flex: 1;
            border: 1.5px solid var(--pj-border);
            background: var(--pj-input);
            color: var(--pj-muted);
            border-radius: 14px;
            padding: 16px 18px;
            cursor: pointer;
            text-align: left;
            transition: all .2s ease;
        }
        .pj-status-pill b { display: block; font-size: 0.92rem; color: var(--pj-text); margin-bottom: 3px; }
        .pj-status-pill span { font-size: 0.76rem; color: var(--pj-muted); }
        .pj-status-pill input { display: none; }
        .pj-status-pill.sel {
            border-color: var(--pj-primary);
            background: var(--pj-soft);
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.12);
        }
        .pj-status-pill.sel b { color: var(--pj-primary); }
        .pj-status-pill.sel span { color: var(--pj-primary); }

        /* Sticky action bar */
        .pj-actions {
            position: fixed;
            left: 0; right: 0; bottom: 0;
            background: var(--pj-card);
            border-top: 1px solid var(--pj-border);
            padding: 14px 24px;
            box-shadow: 0 -10px 30px rgba(15, 23, 42, 0.08);
            z-index: 999;
        }
        .pj-actions-inner {
            max-width: 980px; margin: 0 auto;
            display: flex; justify-content: flex-end; align-items: center; gap: 12px;
        }
        .pj-actions .progress-txt { font-size: 0.8rem; color: var(--pj-muted); font-weight: 600; margin-right: auto; }
        .pj-actions .progress-txt b { color: var(--pj-primary); }
        .pj-btn {
            display: inline-flex; align-items: center; gap: 9px;
            padding: 13px 28px; border-radius: 13px;
            font-size: 0.92rem; font-weight: 700;
            border: none; cursor: pointer;
            transition: transform .2s ease, box-shadow .2s ease;
        }
        .pj-btn:hover { transform: translateY(-2px); }
        .pj-btn-primary {
            background: linear-gradient(135deg, var(--pj-primary), var(--pj-primary-2));
            color: #fff;
            box-shadow: 0 10px 22px rgba(79, 70, 229, 0.35);
        }
        .pj-btn-primary:hover { box-shadow: 0 14px 28px rgba(79, 70, 229, 0.45); }
        .pj-btn-ghost {
            background: transparent;
            border: 1.5px solid var(--pj-border);
            color: var(--pj-muted);
        }
        .pj-btn-ghost:hover { border-color: var(--pj-primary); color: var(--pj-primary); }

        /* Toast */
        .pj-toast {
            position: fixed; top: 84px; right: 24px; z-index: 9999;
            background: var(--pj-card);
            border: 1px solid var(--pj-border);
            border-left: 4px solid #10b981;
            border-radius: 14px;
            padding: 15px 20px;
            display: flex; align-items: center; gap: 12px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.18);
            opacity: 0; transform: translateX(30px);
            transition: all .35s ease;
            pointer-events: none;
        }
        .pj-toast.show { opacity: 1; transform: translateX(0); }
        .pj-toast i { color: #10b981; font-size: 1.3rem; }
        .pj-toast b { color: var(--pj-text); font-size: 0.9rem; }
        .pj-toast a { color: var(--pj-primary); font-weight: 700; margin-left: 6px; }

        @media (max-width: 768px) {
            .pj-wrap { padding: 22px 14px 110px; }
            .pj-section { padding: 20px 18px; }
            .pj-actions-inner { flex-wrap: wrap; }
            .pj-actions .progress-txt { width: 100%; margin: 0 0 6px; }
            .pj-btn { flex: 1; justify-content: center; }
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/company_header.php'; ?>

    <div class="pj-wrap">
        <!-- Hero -->
        <div class="pj-hero">
            <h1><i class="fas fa-wand-magic-sparkles mr-2"></i>Post a New Job</h1>
            <p>Create a compelling job posting and start receiving applications in minutes.</p>
            <div class="pj-steps">
                <span class="pj-step"><span class="n">1</span>Basic Information</span>
                <span class="pj-step"><span class="n">2</span>Job Details</span>
                <span class="pj-step"><span class="n">3</span>Employment Details</span>
            </div>
        </div>

        <?php if (isset($error_msg)): ?>
            <div class="pj-section" style="border-left: 4px solid #ef4444;">
                <div style="display:flex;align-items:center;gap:12px;color:var(--pj-text);">
                    <i class="fas fa-circle-exclamation" style="color:#ef4444;font-size:1.3rem;"></i>
                    <span style="font-size:.92rem;font-weight:600;"><?php echo htmlspecialchars($error_msg); ?></span>
                </div>
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="postJobForm" onsubmit="return validateJobForm()">

            <!-- Step 1: Basic Information -->
            <div class="pj-section">
                <div class="pj-section-head">
                    <div class="ico"><i class="fas fa-info-circle"></i></div>
                    <div>
                        <h3>Basic Information</h3>
                        <p>Give your job a clear, searchable title and description.</p>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-7">
                        <div class="pj-field">
                            <label class="pj-label" for="job_title">Job Title <span class="req">*</span></label>
                            <input type="text" class="pj-input" id="job_title" name="job_title"
                                   placeholder="e.g., Senior Java Developer" maxlength="120" required>
                            <div class="pj-hint"><span class="pj-count" id="titleCount">0/120</span></div>
                        </div>
                    </div>

                    <div class="col-md-5">
                        <div class="pj-field">
                            <label class="pj-label" for="job_category">Job Category <span class="req">*</span></label>
                            <select class="pj-input" id="job_category" name="job_category" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="pj-field">
                            <label class="pj-label" for="job_description">
                                <span>Job Description <span class="req">*</span></span>
                                <button type="button" class="pj-ai-btn" id="aiGenerateBtn">
                                    <i class="fas fa-wand-magic-sparkles"></i> Generate with AI
                                </button>
                            </label>
                            <textarea class="pj-input" id="job_description" name="job_description"
                                      placeholder="Describe the role and what the candidate will be doing..." required></textarea>
                            <div class="pj-hint"><span class="pj-count" id="descCount">0 characters</span></div>
                            <div class="pj-ai-status" id="aiGenerateStatus">
                                <span class="spin"></span>
                                <span id="aiGenerateMsg">AI is writing your job posting...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step 2: Job Details -->
            <div class="pj-section">
                <div class="pj-section-head">
                    <div class="ico"><i class="fas fa-clipboard-list"></i></div>
                    <div>
                        <h3>Job Details</h3>
                        <p>Requirements, responsibilities, and skills candidates need.</p>
                    </div>
                </div>

                <div class="pj-field">
                    <label class="pj-label" for="requirements">Requirements <span class="req">*</span></label>
                    <textarea class="pj-input" id="requirements" name="requirements"
                              placeholder="List the required qualifications, education, and experience..." required></textarea>
                </div>

                <div class="pj-field">
                    <label class="pj-label" for="responsibilities">Responsibilities <span class="req">*</span></label>
                    <textarea class="pj-input" id="responsibilities" name="responsibilities"
                              placeholder="Describe key responsibilities and duties..." required></textarea>
                </div>

                <div class="pj-field">
                    <label class="pj-label" for="skills_required">Required Skills <span class="req">*</span>
                        <span class="opt">press Enter or comma to add</span>
                    </label>
                    <input type="text" class="pj-input" id="skillsInput"
                           placeholder="e.g., Java, Spring Boot, MySQL, REST APIs">
                    <input type="hidden" id="skills_required" name="skills_required">
                    <div class="pj-tags" id="skillsTags"></div>
                </div>
            </div>

            <!-- Step 3: Employment Details -->
            <div class="pj-section">
                <div class="pj-section-head">
                    <div class="ico"><i class="fas fa-briefcase"></i></div>
                    <div>
                        <h3>Employment Details</h3>
                        <p>Location, pay, experience, and how candidates should apply.</p>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="pj-field">
                            <label class="pj-label" for="location">Location <span class="req">*</span></label>
                            <input type="text" class="pj-input" id="location" name="location"
                                   placeholder="e.g., San Francisco, CA or Remote" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="pj-field">
                            <label class="pj-label" for="employment_type">Employment Type <span class="req">*</span></label>
                            <select class="pj-input" id="employment_type" name="employment_type" required>
                                <option value="">Select Type</option>
                                <option value="Full-Time">Full-Time</option>
                                <option value="Part-Time">Part-Time</option>
                                <option value="Contract">Contract</option>
                                <option value="Internship">Internship</option>
                                <option value="Remote">Remote</option>
                                <option value="Hybrid">Hybrid</option>
                            </select>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="pj-field">
                            <label class="pj-label" for="experience_required">Experience <span class="req">*</span></label>
                            <input type="text" class="pj-input" id="experience_required" name="experience_required"
                                   placeholder="e.g., 3-5 years" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="pj-field">
                            <label class="pj-label" for="salary_range">Salary Range <span class="opt">optional</span></label>
                            <input type="text" class="pj-input" id="salary_range" name="salary_range"
                                   placeholder="e.g., $80,000 - $120,000">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="pj-field">
                            <label class="pj-label" for="vacancy_count">Vacancies <span class="req">*</span></label>
                            <input type="number" class="pj-input" id="vacancy_count" name="vacancy_count"
                                   min="1" value="1" required>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="pj-field">
                            <label class="pj-label" for="deadline">Application Deadline <span class="req">*</span></label>
                            <input type="date" class="pj-input" id="deadline" name="deadline" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="pj-field">
                            <label class="pj-label">Status <span class="req">*</span></label>
                            <div class="pj-status">
                                <label class="pj-status-pill sel">
                                    <input type="radio" name="status" value="active" checked>
                                    <b><i class="fas fa-bullseye mr-1"></i> Active</b>
                                    <span>Publish immediately</span>
                                </label>
                                <label class="pj-status-pill">
                                    <input type="radio" name="status" value="draft">
                                    <b><i class="fas fa-pen-ruler mr-1"></i> Draft</b>
                                    <span>Save for later</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sticky actions -->
            <div class="pj-actions">
                <div class="pj-actions-inner">
                    <span class="progress-txt" id="progressTxt">Ready to publish</span>
                    <a href="my_jobs.php" class="pj-btn pj-btn-ghost"><i class="fas fa-times"></i> Cancel</a>
                    <button type="submit" name="post_job" class="pj-btn pj-btn-primary">
                        <i class="fas fa-paper-plane"></i> Post Job
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Success toast -->
    <div class="pj-toast" id="pjToast">
        <i class="fas fa-circle-check"></i>
        <span><b>Job posted successfully!</b><a href="#" id="toastQuizLink">Add quiz questions</a></span>
    </div>

    <script>
        // Toast helper
        function pjToast(msg, quizId) {
            const t = document.getElementById('pjToast');
            document.getElementById('toastQuizLink').style.display = quizId ? '' : 'none';
            if (quizId) document.getElementById('toastQuizLink').href = 'manage_quiz.php?job_id=' + quizId;
            t.querySelector('b').textContent = msg;
            t.classList.add('show');
            setTimeout(() => t.classList.remove('show'), 5000);
        }
        <?php if (isset($_GET['success'])): ?>pjToast('Job posted successfully!', <?php echo intval($_GET['success']); ?>);<?php endif; ?>

        // Live counters
        const titleInput = document.getElementById('job_title');
        const titleCount = document.getElementById('titleCount');
        const descInput = document.getElementById('job_description');
        const descCount = document.getElementById('descCount');
        titleInput.addEventListener('input', () => titleCount.textContent = titleInput.value.length + '/120');
        descInput.addEventListener('input', () => descCount.textContent = descInput.value.length + ' characters');

        // Skills tags input
        const skillsInput = document.getElementById('skillsInput');
        const skillsTags = document.getElementById('skillsTags');
        const skillsHidden = document.getElementById('skills_required');
        let skills = [];
        function renderSkills() {
            skillsTags.innerHTML = '';
            skills.forEach((s, i) => {
                const tag = document.createElement('span');
                tag.className = 'pj-tag';
                tag.innerHTML = s + '<button type="button" onclick="removeSkill(' + i + ')">&times;</button>';
                skillsTags.appendChild(tag);
            });
            skillsHidden.value = skills.join(', ');
        }
        window.removeSkill = function(i) { skills.splice(i, 1); renderSkills(); };
        skillsInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ',') {
                e.preventDefault();
                addSkill();
            }
        });
        skillsInput.addEventListener('blur', addSkill);
        function addSkill() {
            const v = skillsInput.value.trim();
            if (v && !skills.includes(v)) { skills.push(v); renderSkills(); }
            skillsInput.value = '';
        }

        // Status pill toggle
        document.querySelectorAll('.pj-status-pill').forEach(pill => {
            pill.addEventListener('click', () => {
                document.querySelectorAll('.pj-status-pill').forEach(p => p.classList.remove('sel'));
                pill.classList.add('sel');
            });
        });

        // Validate
        function validateJobForm() {
            const required = document.querySelectorAll('#postJobForm .pj-input[required]');
            let missing = [];
            required.forEach(f => {
                if (!f.value.trim()) missing.push(f.name);
            });
            if (!skillsHidden.value.trim()) missing.push('skills_required');
            const deadline = new Date(document.getElementById('deadline').value);
            if (document.getElementById('deadline').value && deadline < new Date(new Date().toDateString())) {
                alert('Deadline cannot be in the past.');
                return false;
            }
            if (missing.length) {
                alert('Please fill in the following required fields: ' + missing.join(', '));
                document.getElementById('deadline').setAttribute('required', '');
                return false;
            }
            return true;
        }

        // Deadline min = today
        const dl = document.getElementById('deadline');
        if (dl) dl.min = new Date().toISOString().split('T')[0];

        // ── AI Job Description Generator ──
        (function() {
            const aiBtn = document.getElementById('aiGenerateBtn');
            if (aiBtn) {
                aiBtn.addEventListener('click', function () {
                    const title = document.getElementById('job_title').value.trim();
                    const category = document.getElementById('job_category').value;
                    const skills = skillsHidden.value.trim();
                    if (!title) {
                        alert('Please fill in the Job Title first, then click Generate with AI.');
                        return;
                    }
                    const status = document.getElementById('aiGenerateStatus');
                    status.style.display = 'flex';
                    aiBtn.disabled = true;

                    fetch('../api/ai_generate_jd.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'title=' + encodeURIComponent(title) + '&category=' + encodeURIComponent(category) + '&skills=' + encodeURIComponent(skills)
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (!data.ok) { alert(data.error || 'Generation failed'); return; }
                        document.getElementById('job_description').value = data.description || '';
                        document.getElementById('requirements').value = data.requirements || '';
                        document.getElementById('responsibilities').value = data.responsibilities || '';
                        document.getElementById('descCount').textContent = (data.description || '').length + ' characters';
                        const msg = document.getElementById('aiGenerateMsg');
                        msg.textContent = 'Generated ' + (data.llm ? 'by live AI' : 'by NovaHire AI Engine') + '. Review and edit before posting.';
                    })
                    .catch(() => alert('Could not reach the AI generator.'))
                    .finally(() => {
                        aiBtn.disabled = false;
                        setTimeout(() => { status.style.display = 'none'; }, 6000);
                    });
                });
            }
        })();
    </script>
</body>
</html>
