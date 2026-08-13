<?php
// Core setup: session, DB, BASE_URL, helpers
require_once __DIR__ . '/../includes/bootstrap.php';
 

    if (!isset($_SESSION['id'])) {
        header('location: ' . BASE_URL . '/auth/login.php');
        exit();
    }
    require_once __DIR__ . '/../admin/dbcon.php';
    require_once __DIR__ . '/../includes/header.php';

    $quiz_passed = isset($_SESSION['quiz_passed']) && $_SESSION['quiz_passed'] === true;
    $quiz_category = isset($_SESSION['quiz_category']) ? $_SESSION['quiz_category'] : '';
    
    if(isset($_GET['status']) && $_GET['status'] == 'passed') {
        $quiz_passed = true;
    }

    $user_id = $_SESSION['id'];
    $u_query = "SELECT * FROM user_info WHERE id = '$user_id'";
    $u_res = mysqli_query($con, $u_query);
    $user_data = mysqli_fetch_assoc($u_res);

    $fullname = $user_data['username'];
    $uphone = $user_data['phone'];
    $uemail = $user_data['email'];
    $udegree = $user_data['user_degree'];
    $uskills = $user_data['user_skills'];
    
    $submission_status = null; 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Apply for Job</title>
    <style>
        .step-container {
            max-width: 800px;
            margin: 80px auto;
        }
        .step-card {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            border-radius: 24px;
            padding: 60px 80px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.08); /* Softer shadow */
            border: 1px solid rgba(255,255,255,0.6);
            animation: fadeIn 0.8s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        h2 {
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
            color: var(--dark-text);
            letter-spacing: -0.5px;
        }
        
        .section-separator {
            border-bottom: 1px solid rgba(0,0,0,0.06);
            margin: 30px 0;
        }
        .form-control {
            background: rgba(255,255,255,0.6);
            border: 1px solid rgba(0,0,0,0.08);
            border-radius: 12px;
            padding: 25px 20px;
            font-size: 1rem;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            background: white;
            border-color: var(--primary-color);
            box-shadow: 0 4px 15px rgba(108, 92, 231, 0.08);
        }

        .input-group-text {
            background: transparent;
            border: none;
            color: #b2bec3;
            padding-right: 15px;
        }
        
        .cv-option-card {
            border: 1px solid rgba(0,0,0,0.08);
            border-radius: 16px;
            padding: 25px;
            cursor: pointer;
            transition: all 0.2s;
            background: white;
            text-align: center;
        }
        
        .cv-option-card:hover {
            border-color: var(--secondary-color);
            transform: translateY(-2px);
        }

        .cv-option-card.active {
            border: 2px solid var(--primary-color);
            background: rgba(108, 92, 231, 0.03);
        }
        
        .cv-icon {
            font-size: 1.5rem;
            color: #b2bec3;
            margin-bottom: 10px;
            display: block;
        }
        
        .cv-option-card.active .cv-icon {
            color: var(--primary-color);
        }

        .hidden-radio { display: none; }

    </style>
</head>
<body>
    <div class="container step-container">
        
        <?php
            if (isset($_POST['submit_application'])) {
                
                $name = mysqli_real_escape_string($con, $_POST['name']);
                $phone = mysqli_real_escape_string($con, $_POST['phone']);
                $email = mysqli_real_escape_string($con, $_POST['email']);
                $degree = mysqli_real_escape_string($con, $_POST['degree']);
                $refer = mysqli_real_escape_string($con, $_POST['refer']);
                $plang = mysqli_real_escape_string($con, $_POST['plang']);
                
               
                $cv_method = $_POST['cv_method'];
                $final_file_name = "";

                $proceed = true;

               
                $check_q = "SELECT * FROM jobregistration WHERE email='$email'";
                $check_res = mysqli_query($con, $check_q);
                if (mysqli_num_rows($check_res) > 0) {
                    $submission_status = 'duplicate';
                    $proceed = false;
                }

                if ($proceed) {
                    if ($cv_method == 'auto') {
                        $final_file_name = "Auto-Generated (View Profile)";
                    } else {
                        if (isset($_FILES['pdf_file']['name']) && $_FILES['pdf_file']['name'] != '') {
                            $file_name = $_FILES['pdf_file']['name'];
                            $file_tmp = $_FILES['pdf_file']['tmp_name'];
                            move_uploaded_file($file_tmp, "./files/" . $file_name);
                            $final_file_name = $file_name;
                        } else {
                            $submission_status = 'nocv';
                            $proceed = false;
                        }
                    }
                }

                if ($proceed) {
                    $insertquery = "INSERT INTO jobregistration (name, phone, email, degree, refer, planguage, cv_doc)  
                                    VALUES('$name', '$phone', '$email', '$degree', '$refer', '$plang', '$final_file_name')";
                    $iquery = mysqli_query($con, $insertquery);

                    if ($iquery) {
                        $submission_status = 'success';
                    } else {
                        $submission_status = 'fail';
                    }
                }
            }
        ?>

        <?php if ($submission_status): ?>
            <div class="step-card text-center" style="padding: 50px 40px;">
                <?php if ($submission_status == 'success'): ?>
                    <div style="width: 90px; height: 90px; border-radius: 50%; background: #dcfce7; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px; animation: checkBounce 0.6s cubic-bezier(0.34, 1.56, 0.64, 1) 0.2s both;">
                        <i class="fas fa-check" style="font-size: 40px; color: #059669;"></i>
                    </div>
                    <h2 style="font-size: 26px; font-weight: 800; color: #065f46; margin-bottom: 10px;">Application Submitted!</h2>
                    <p style="color: #047857; font-size: 16px; margin-bottom: 30px; max-width: 440px; margin-left: auto; margin-right: auto; line-height: 1.6;">Your application for the <strong><?php echo htmlspecialchars($quiz_category); ?></strong> role has been received successfully. We'll notify you once the employer reviews your application.</p>
                    <div>
                        <a href="my_application.php" class="btn btn-primary rounded-pill px-5 py-3 font-weight-bold" style="margin-right: 12px;">
                            <i class="fas fa-list mr-2"></i>Track Status
                        </a>
                        <a href="seeker_dashboard.php" class="btn btn-light rounded-pill px-5 py-3 font-weight-bold">
                            <i class="fas fa-home mr-2"></i>Back to Dashboard
                        </a>
                    </div>

                <?php elseif ($submission_status == 'duplicate'): ?>
                    <div style="width: 90px; height: 90px; border-radius: 50%; background: #fef3c7; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px;">
                        <i class="fas fa-info-circle" style="font-size: 40px; color: #d97706;"></i>
                    </div>
                    <h2 style="font-size: 26px; font-weight: 800; color: #92400e; margin-bottom: 10px;">Already Applied</h2>
                    <p style="color: #92400e; font-size: 16px; margin-bottom: 30px;">You have already submitted an application for this position.</p>
                    <a href="my_application.php" class="btn btn-primary rounded-pill px-5 py-3 font-weight-bold">View Application</a>

                <?php else: ?>
                    <div style="width: 90px; height: 90px; border-radius: 50%; background: #fee2e2; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px;">
                        <i class="fas fa-times-circle" style="font-size: 40px; color: #dc2626;"></i>
                    </div>
                    <h2 style="font-size: 26px; font-weight: 800; color: #991b1b; margin-bottom: 10px;">Submission Failed</h2>
                    <p style="color: #991b1b; font-size: 16px; margin-bottom: 30px;">There was an error processing your request. Please try again.</p>
                    <a href="application.php?status=passed" class="btn btn-primary rounded-pill px-5 py-3 font-weight-bold">Try Again</a>
                <?php endif; ?>
            </div>
            <style>
                @keyframes checkBounce {
                    0% { transform: scale(0); }
                    50% { transform: scale(1.2); }
                    100% { transform: scale(1); }
                }
            </style>

        <?php elseif (!$quiz_passed): ?>
        <!-- STEP 1: QUIZ SELECTION (Stylish) -->
        <div class="step-card text-center">
            <div class="step-icon"><i class="fas fa-clipboard-check"></i></div>
            <h2 class="mb-3 font-weight-bold">Skill Assessment</h2>
            <p class="lead text-muted mb-4">Demonstrate your expertise to unlock the application form.</p>
            
            <form action="quiz.php" method="GET" class="mt-4 mx-auto" style="max-width: 450px;">
                <div class="form-group position-relative">
                    <select name="category" class="form-control" style="height: 60px; padding-left: 25px; font-size: 1.1rem; border-radius: 15px;" required>
                        <option value="" disabled selected>Select Your Domain</option>
                        <option value="PHP">PHP Developer</option>
                        <option value="Java">Java Developer</option>
                        <option value="Python">Python Developer</option>
                        <option value="Database">Database Admin</option>
                    </select>
                    <div class="position-absolute" style="right: 20px; top: 20px; pointer-events: none;">
                        <i class="fas fa-chevron-down text-muted"></i>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-block mt-4 rounded-pill py-3 shadow font-weight-bold" style="font-size: 1.1rem;">
                    Start Assessment <i class="fas fa-arrow-right ml-2"></i>
                </button>
            </form>
             <a href="<?php echo BASE_URL; ?>/index.php" class="btn btn-link text-muted mt-3">Cancel & Return</a>
        </div>
        
        <?php else: ?>
        <!-- STEP 2: MINIMALIST APPLICATION FORM -->
        <div class="step-card">
            <div class="text-center mb-5">
                <h5 class="text-uppercase text-muted small font-weight-bold letter-spacing-2">Application for</h5>
                <h2 class="font-weight-bold mb-3"><?php echo htmlspecialchars($quiz_category); ?> Developer</h2>
                <div class="d-inline-flex align-items-center px-3 py-1 bg-light rounded-pill border">
                    <i class="fas fa-check-circle text-success mr-2"></i> 
                    <span class="small font-weight-bold text-success">Skill Verified</span>
                </div>
            </div>

            <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" enctype="multipart/form-data">
                
                <div class="row justify-content-center">
                    <div class="col-md-10">
                        <!-- Contact Info Group -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-4">
                                    <label class="small font-weight-bold text-muted ml-2">Full Name</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend"><span class="input-group-text"><i class="far fa-user"></i></span></div>
                                        <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($fullname); ?>" readonly>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-4">
                                    <label class="small font-weight-bold text-muted ml-2">Email Address</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend"><span class="input-group-text"><i class="far fa-envelope"></i></span></div>
                                        <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($uemail); ?>" readonly>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-4">
                                    <label class="small font-weight-bold text-muted ml-2">Phone</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-mobile-alt"></i></span></div>
                                        <input type="tel" class="form-control" name="phone" value="<?php echo htmlspecialchars($uphone); ?>" readonly>
                                    </div>
                                </div>
                            </div>
                             <div class="col-md-6">
                                <div class="form-group mb-4">
                                    <label class="small font-weight-bold text-muted ml-2">Qualification</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-graduation-cap"></i></span></div>
                                        <input type="text" class="form-control" name="degree" value="<?php echo htmlspecialchars($udegree); ?>" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="section-separator"></div>

                        <!-- Professional Details -->
                        <div class="form-group mb-4">
                             <label class="small font-weight-bold text-muted ml-2">Key Skills (Comma Separated)</label>
                            <div class="input-group">
                                <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-code"></i></span></div>
                                <input type="text" class="form-control" name="plang" value="<?php echo htmlspecialchars($uskills); ?>" required>
                            </div>
                        </div>
                        
                        <!-- Resume Selection -->
                        <div class="form-group mt-4 mb-4">
                            <label class="small font-weight-bold text-muted ml-2 mb-3 d-block">Resume Attachment</label>
                            <div class="row">
                                <div class="col-sm-6 mb-3">
                                    <label class="cv-option-card active" id="card_auto" onclick="selectCV('auto')">
                                        <input type="radio" name="cv_method" value="auto" class="hidden-radio" checked>
                                        <i class="fas fa-magic cv-icon"></i>
                                        <span class="font-weight-bold d-block">Auto-Generate CV</span>
                                        <small class="text-muted">Uses your profile data</small>
                                    </label>
                                </div>
                                <div class="col-sm-6">
                                    <label class="cv-option-card" id="card_manual" onclick="selectCV('manual')">
                                        <input type="radio" name="cv_method" value="manual" class="hidden-radio">
                                        <i class="fas fa-cloud-upload-alt cv-icon"></i>
                                        <span class="font-weight-bold d-block">Upload PDF</span>
                                        <small class="text-muted">Select file from device</small>
                                    </label>
                                </div>
                            </div>
                            
                            <div id="manual_cv_input" style="display: none; margin-top: 20px;" class="animated fadeIn">
                                <div class="custom-file">
                                    <input type="file" name="pdf_file" class="custom-file-input" id="customFile" accept=".pdf">
                                    <label class="custom-file-label" for="customFile" style="border-radius: 12px; height: 50px; line-height: 40px;">Choose PDF file...</label>
                                </div>
                            </div>
                        </div>

                        <div class="form-group mt-5">
                            <button type="submit" class="btn btn-primary btn-lg btn-block rounded-pill shadow-sm py-3 font-weight-bold text-uppercase letter-spacing-1" name="submit_application" style="letter-spacing: 1px;">
                                Confirm Application
                            </button>
                        </div>
                    </div>
                </div>
            </form>
            <div class="text-center mt-4">
                 <a href="<?php echo BASE_URL; ?>/index.php" class="text-muted small">Cancel</a>
            </div>
        </div>
        <?php endif; ?>
        
    </div>

    <script>
        function selectCV(method) {
            // Update UI cards
            document.querySelectorAll('.cv-option-card').forEach(el => el.classList.remove('active'));
            document.getElementById('card_' + method).classList.add('active');
            
            // Check radio hidden
            document.querySelector(`input[name="cv_method"][value="${method}"]`).checked = true;

            // Show/Hide input
            if (method === 'manual') {
                document.getElementById('manual_cv_input').style.display = 'block';
            } else {
                document.getElementById('manual_cv_input').style.display = 'none';
            }
        }
        
        // Custom file input name display
        $(".custom-file-input").on("change", function() {
          var fileName = $(this).val().split("\\").pop();
          $(this).siblings(".custom-file-label").addClass("selected").html(fileName);
        });
    </script>
</body>
</html>
