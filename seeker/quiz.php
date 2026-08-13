<?php
// Core setup: session, DB, BASE_URL, helpers
require_once __DIR__ . '/../includes/bootstrap.php';
if (!isset($_SESSION['id'])) {
    header('location: ' . BASE_URL . '/auth/login.php');
    exit();
}
require_once __DIR__ . '/../admin/dbcon.php';

$category = isset($_GET['category']) ? $_GET['category'] : 'PHP';

// Check if user is allowed to take quiz
$user_id = $_SESSION['id'];
$status_query = "SELECT * FROM user_quiz_status WHERE user_id='$user_id' AND category='$category'";
$status_res = mysqli_query($con, $status_query);

if (mysqli_num_rows($status_res) > 0) {
    $row = mysqli_fetch_assoc($status_res);
    if ($row['status'] == 'failed' && $row['grooming_completed'] == 0) {
        echo "<script>alert('You must complete the grooming session before retaking the assessment.'); window.location.href='grooming.php?category=$category';</script>";
        exit();
    }
}

if (isset($_POST['submit_quiz'])) {
    $score = 0;
    $total = 0;
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'q_') === 0) {
            $qid = substr($key, 2);
            $total++;
            $q_query = "SELECT answer FROM quiz_questions WHERE id = '$qid'";
            $q_res = mysqli_query($con, $q_query);
            $q_row = mysqli_fetch_assoc($q_res);
            if ($q_row['answer'] == $value) {
                $score++;
            }
        }
    }

    $user_id = $_SESSION['id'];
    $quiz_status = '';
    

    $check_query = "SELECT * FROM user_quiz_status WHERE user_id='$user_id' AND category='$category'";
    $check_res = mysqli_query($con, $check_query);

    if ($total > 0 && $score >= 3) {
        $_SESSION['quiz_passed'] = true;
        $_SESSION['quiz_category'] = $category;
        $quiz_status = 'passed';
        $redirect = "application.php?status=passed";
        $alert = "Assessment Passed! Score: $score/$total";
    } else {
        $_SESSION['quiz_passed'] = false;
        $quiz_status = 'failed';
        $redirect = "grooming.php?category=$category";
        $alert = "Assessment Failed. Score: $score/$total";
    }

    if (mysqli_num_rows($check_res) > 0) {

        $update_sql = "UPDATE user_quiz_status SET status='$quiz_status', last_attempt=NOW()";
        if ($quiz_status == 'failed') {
            $update_sql .= ", grooming_completed=0";
        }
        $update_sql .= " WHERE user_id='$user_id' AND category='$category'";
        mysqli_query($con, $update_sql);
    } else {
       
        $grooming_completed = ($quiz_status == 'passed') ? 1 : 0;
        $insert_sql = "INSERT INTO user_quiz_status (user_id, category, status, grooming_completed) VALUES ('$user_id', '$category', '$quiz_status', '$grooming_completed')";
        mysqli_query($con, $insert_sql);
    }

    echo "<script>alert('$alert'); window.location.href='$redirect';</script>";
    exit();
}

// Fetch Questions
$query = "SELECT * FROM quiz_questions WHERE category = '$category' ORDER BY RAND() LIMIT 5";
$result = mysqli_query($con, $query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Skill Assessment - <?php echo htmlspecialchars($category); ?></title>
    <?php require_once __DIR__ . '/../includes/links.php'; ?>
    <style>
        .quiz-header {
            text-align: center;
            color: white;
            margin-bottom: 40px;
        }
        .question-card {
            background: #ffffff; /* Solid White */
            border: 1px solid rgba(0,0,0,0.1);
            padding: 30px;
            margin-bottom: 30px;
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            transition: all 0.3s;
        }
        .question-card:hover {
            box-shadow: 0 10px 35px rgba(0,0,0,0.08);
            border-color: var(--primary-color);
        }
        .question-text {
            font-weight: 800;
            color: #1e293b;
            margin-bottom: 25px;
            font-size: 1.2rem;
            line-height: 1.4;
        }
        .form-check {
            margin-bottom: 12px;
            position: relative;
        }
        .form-check-label {
            font-weight: 600;
            color: #475569;
            cursor: pointer;
            padding: 15px 20px;
            border-radius: 12px;
            width: 100%;
            transition: all 0.2s;
            border: 2px solid #f1f5f9;
            background: #f8fafc;
            display: block;
        }
        .form-check:hover .form-check-label {
            background: #f1f5f9;
            border-color: #e2e8f0;
        }
        .form-check-input:checked + .form-check-label {
            background: #eef2ff;
            color: var(--primary-color);
            border-color: var(--primary-color);
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.1);
        }
        /* Hide default radio */
        .form-check-input {
            opacity: 0;
            position: absolute;
        }
        .badge-cat {
            background: rgba(255,255,255,0.2);
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            margin-left: 10px;
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/../includes/header.php'; ?>
    
    <div class="container" style="margin-top: 50px; padding-bottom: 50px;">
        <div class="quiz-header">
            <h1 class="font-weight-bold display-4">Skill Assessment <span class="badge-cat"><i class="fas fa-code"></i> <?php echo htmlspecialchars($category); ?></span></h1>
            <p class="lead opacity-75">Answer at least 3 out of 5 correctly to proceed.</p>
        </div>
        
        <?php if(mysqli_num_rows($result) > 0): ?>
            <form action="" method="POST">
                <?php 
                $i = 1;
                while($row = mysqli_fetch_assoc($result)): 
                ?>
                <div class="question-card">
                    <div class="question-text">
                        <span class="text-primary mr-2">Q<?php echo $i++; ?>.</span> <?php echo htmlspecialchars($row['question']); ?>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-2">
                             <div class="form-check">
                                <input class="form-check-input" type="radio" name="q_<?php echo $row['id']; ?>" id="q<?php echo $row['id']; ?>_1" value="<?php echo htmlspecialchars($row['option1']); ?>" required>
                                <label class="form-check-label" for="q<?php echo $row['id']; ?>_1">
                                    <i class="far fa-circle mr-2"></i> <?php echo htmlspecialchars($row['option1']); ?>
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6 mb-2">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="q_<?php echo $row['id']; ?>" id="q<?php echo $row['id']; ?>_2" value="<?php echo htmlspecialchars($row['option2']); ?>" required>
                                <label class="form-check-label" for="q<?php echo $row['id']; ?>_2">
                                     <i class="far fa-circle mr-2"></i> <?php echo htmlspecialchars($row['option2']); ?>
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6 mb-2">
                             <div class="form-check">
                                <input class="form-check-input" type="radio" name="q_<?php echo $row['id']; ?>" id="q<?php echo $row['id']; ?>_3" value="<?php echo htmlspecialchars($row['option3']); ?>" required>
                                <label class="form-check-label" for="q<?php echo $row['id']; ?>_3">
                                     <i class="far fa-circle mr-2"></i> <?php echo htmlspecialchars($row['option3']); ?>
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6 mb-2">
                             <div class="form-check">
                                <input class="form-check-input" type="radio" name="q_<?php echo $row['id']; ?>" id="q<?php echo $row['id']; ?>_4" value="<?php echo htmlspecialchars($row['option4']); ?>" required>
                                <label class="form-check-label" for="q<?php echo $row['id']; ?>_4">
                                     <i class="far fa-circle mr-2"></i> <?php echo htmlspecialchars($row['option4']); ?>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
                
                <div class="text-center mt-5">
                    <a href="seeker_dashboard.php" class="btn btn-outline-light rounded-pill px-4 mr-3">Cancel</a>
                    <button type="submit" name="submit_quiz" class="btn btn-primary btn-lg rounded-pill px-5 shadow-lg">Submit Assessment <i class="fas fa-paper-plane ml-2"></i></button>
                </div>
            </form>
        <?php else: ?>
            <div class="glass-panel text-center text-white">
                <i class="fas fa-tools fa-3x mb-3"></i>
                <h3>Assessment Setup In Progress</h3>
                <p>Questions for this category are being updated. Please try again later.</p>
                <a href="seeker_dashboard.php" class="btn btn-light rounded-pill px-5 mt-3 text-primary font-weight-bold">Back to Dashboard</a>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Anti-Cheat Overlay -->
    <div id="antiCheatOverlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.9); z-index:9999; color:white; align-items:center; justify-content:center; flex-direction:column; text-align:center;">
        <i class="fas fa-exclamation-triangle" style="font-size: 4rem; color: #f59e0b; margin-bottom: 20px;"></i>
        <h2 style="font-weight: bold; margin-bottom: 10px;">Warning!</h2>
        <p id="antiCheatMsg" style="font-size: 1.2rem; max-width: 600px;">You are not allowed to switch tabs or exit fullscreen mode during the quiz.</p>
        <p style="font-size: 1rem; color: #cbd5e1; margin-top: 10px;">Warnings remaining: <span id="warningsLeft">3</span>/3</p>
        <button id="resumeQuizBtn" style="margin-top: 30px; padding: 12px 30px; background: #3b82f6; border: none; border-radius: 8px; color: white; font-weight: bold; font-size: 1.1rem; cursor: pointer;">Resume Quiz</button>
    </div>

    <script>
        // Simple script to toggle checked styles
        const radios = document.querySelectorAll('.form-check-input');
        radios.forEach(radio => {
            radio.addEventListener('change', function() {
                // Remove check icon from all labels in this group
                const name = this.name;
                document.querySelectorAll(`input[name="${name}"]`).forEach(r => {
                     const label = r.nextElementSibling;
                     label.querySelector('i').className = 'far fa-circle mr-2';
                });
                // Add dot icon to checked
                if(this.checked) {
                    const label = this.nextElementSibling;
                     label.querySelector('i').className = 'fas fa-dot-circle mr-2';
                }
            });
        });

        /* ── Anti-Cheat System ── */
        let warnings = 0;
        const MAX_WARNINGS = 3;
        const overlay = document.getElementById('antiCheatOverlay');
        const resumeBtn = document.getElementById('resumeQuizBtn');
        const warningsLeft = document.getElementById('warningsLeft');
        const msg = document.getElementById('antiCheatMsg');
        const form = document.querySelector('form');
        let isAntiCheatActive = false;

        if (form) {
            function triggerWarning(reason) {
                if (!isAntiCheatActive) return;
                warnings++;
                if (warnings >= MAX_WARNINGS) {
                    msg.innerText = "You have exceeded the maximum number of warnings. The quiz will now be submitted automatically.";
                    warningsLeft.innerText = "0";
                    overlay.style.display = 'flex';
                    resumeBtn.style.display = 'none';
                    // Auto submit
                    setTimeout(() => {
                        let submitBtn = document.createElement("input");
                        submitBtn.type = "hidden";
                        submitBtn.name = "submit_quiz";
                        submitBtn.value = "1";
                        form.appendChild(submitBtn);
                        form.submit();
                    }, 3000);
                } else {
                    msg.innerText = "Warning: " + reason + " is not allowed during the quiz.";
                    warningsLeft.innerText = (MAX_WARNINGS - warnings);
                    overlay.style.display = 'flex';
                }
            }

            resumeBtn.addEventListener('click', () => {
                overlay.style.display = 'none';
                if (document.documentElement.requestFullscreen) {
                    document.documentElement.requestFullscreen().catch(err => console.log(err));
                }
            });

            // Disable right click, copy, paste
            document.addEventListener('contextmenu', e => e.preventDefault());
            document.addEventListener('copy', e => { e.preventDefault(); triggerWarning("Copying text"); });
            document.addEventListener('paste', e => { e.preventDefault(); triggerWarning("Pasting text"); });

            // Detect tab switch
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) {
                    triggerWarning("Switching tabs or minimizing the browser");
                }
            });

            // Fullscreen enforcement
            document.addEventListener('fullscreenchange', () => {
                if (!document.fullscreenElement) {
                    triggerWarning("Exiting fullscreen mode");
                }
            });

            // Request fullscreen on start
            window.addEventListener('load', () => {
                document.body.addEventListener('click', function enableFullscreen() {
                    if (!isAntiCheatActive) {
                        if (document.documentElement.requestFullscreen) {
                            document.documentElement.requestFullscreen().catch(err => console.log(err));
                        }
                        isAntiCheatActive = true;
                        document.body.removeEventListener('click', enableFullscreen);
                    }
                });
            });
        }
    </script>
</body>
</html>
