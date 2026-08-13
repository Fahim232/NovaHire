<?php
// Core setup: session, DB, BASE_URL, helpers
require_once __DIR__ . '/../includes/bootstrap.php';
if (!isset($_SESSION['id'])) {
    header('location: ' . BASE_URL . '/auth/login.php');
    exit();
}
require_once __DIR__ . '/../admin/dbcon.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_GET['job_id'])) {
    header('location: browse_jobs.php');
    exit();
}

$job_id = mysqli_real_escape_string($con, $_GET['job_id']);
$user_id = $_SESSION['id'];

$job_query = "SELECT cj.*, c.company_name, c.industry
              FROM company_jobs cj
              JOIN companies c ON cj.company_id = c.id
              WHERE cj.id = '$job_id' AND cj.status = 'active'";
$job_result = mysqli_query($con, $job_query);

if (mysqli_num_rows($job_result) == 0) {
    echo "<script>alert('Job not found'); window.location.href='browse_jobs.php';</script>";
    exit();
}

$job = mysqli_fetch_assoc($job_result);

$quiz_timer = 300;
if (isset($job['quiz_timer']) && intval($job['quiz_timer']) > 0) {
    $quiz_timer = max(60, intval($job['quiz_timer']));
}

// SESSION-LEVEL LOCK: prevent retake even if DB check fails
$quiz_session_key = 'quiz_taken_' . $job_id;
$quiz_session_submitted_key = 'quiz_submitted_' . $job_id;

// Check if grooming was completed (allows retake)
$grooming_completed = false;
$grooming_check = "SELECT grooming_completed FROM user_quiz_status WHERE user_id='$user_id' AND category='" . mysqli_real_escape_string($con, $job['job_category']) . "'";
$grooming_res = mysqli_query($con, $grooming_check);
if (mysqli_num_rows($grooming_res) > 0) {
    $grooming_row = mysqli_fetch_assoc($grooming_res);
    $grooming_completed = ($grooming_row['grooming_completed'] == 1);
}

// Count total quiz attempts for this user+job
$attempt_count = 0;
$attempt_count_query = "SELECT COUNT(*) as cnt FROM job_quiz_attempts WHERE user_id='$user_id' AND job_id='$job_id'";
$attempt_count_result = mysqli_query($con, $attempt_count_query);
if (mysqli_num_rows($attempt_count_result) > 0) {
    $attempt_count_row = mysqli_fetch_assoc($attempt_count_result);
    $attempt_count = intval($attempt_count_row['cnt']);
}

// EXHAUSTED: user has 2+ attempts and hasn't passed → permanently locked out
if ($attempt_count >= 2 && !$grooming_completed) {
    // Check if they actually failed (not passed)
    $last_attempt_check = "SELECT score_percentage FROM job_quiz_attempts WHERE user_id='$user_id' AND job_id='$job_id' ORDER BY attempt_date DESC LIMIT 1";
    $last_res = mysqli_query($con, $last_attempt_check);
    if (mysqli_num_rows($last_res) > 0) {
        $last_row = mysqli_fetch_assoc($last_res);
        if ($last_row['score_percentage'] < 60) {
            echo "<script>alert('You have exhausted all assessment attempts for this position. You can no longer apply for this job.'); window.location.href='job_details.php?id=$job_id';</script>";
            exit();
        }
    }
}

// If grooming was just completed, clear the session lock so user can retake (ONE final attempt)
if ($grooming_completed && isset($_SESSION[$quiz_session_key])) {
    unset($_SESSION[$quiz_session_key]);
    unset($_SESSION[$quiz_session_submitted_key]);
}

if (isset($_SESSION[$quiz_session_key]) && $_SESSION[$quiz_session_key] === true && !$grooming_completed) {
    echo "<script>alert('You have already attempted this quiz. Complete the grooming session to retake.'); window.location.href='grooming.php?category=" . urlencode($job['job_category']) . "&job_id=$job_id';</script>";
    exit();
}

// DB-level check (backup) - allow retake if grooming completed
$quiz_check = "SELECT * FROM job_quiz_attempts WHERE user_id = '$user_id' AND job_id = '$job_id' ORDER BY attempt_date DESC LIMIT 1";
$quiz_check_result = mysqli_query($con, $quiz_check);

if (mysqli_num_rows($quiz_check_result) > 0) {
    $attempt = mysqli_fetch_assoc($quiz_check_result);
    // Lock in session so refresh/back can't bypass
    $_SESSION[$quiz_session_key] = true;
    if ($attempt['score_percentage'] >= 60) {
        echo "<script>alert('You have already passed this quiz. Redirecting to application.'); window.location.href='company_job_application.php?job_id=$job_id';</script>";
        exit();
    } elseif (!$grooming_completed) {
        echo "<script>alert('You have already attempted this quiz.\\nScore: " . $attempt['score_percentage'] . "%\\n\\nPlease complete the grooming session to retake.'); window.location.href='grooming.php?category=" . urlencode($job['job_category']) . "&job_id=$job_id';</script>";
        exit();
    }
    // If grooming completed, allow retake - clear session lock
    unset($_SESSION[$quiz_session_key]);
    unset($_SESSION[$quiz_session_submitted_key]);
}

// If form is being submitted (POST), lock immediately to prevent double-submit
if (isset($_POST['submit_quiz'])) {
    $_SESSION[$quiz_session_key] = true;
}

$questions_query = "SELECT * FROM company_job_questions WHERE job_id = '$job_id' ORDER BY id";
$questions_result = mysqli_query($con, $questions_query);

if (mysqli_num_rows($questions_result) == 0) {
    echo "<script>alert('No quiz questions available for this job'); window.location.href='browse_jobs.php';</script>";
    exit();
}

$total_questions = mysqli_num_rows($questions_result);

$show_results = false;
$result_score = 0;
$result_correct = 0;
$result_total = 0;
$result_time = 0;
$result_status = '';
$redirect_url = '';
$quiz_status = '';

if (isset($_POST['submit_quiz'])) {
    // LOCK: prevent any re-entry
    $_SESSION[$quiz_session_key] = true;
    $_SESSION[$quiz_session_submitted_key] = true;

    $start_time = intval($_POST['start_time']);
    $end_time = time();
    $time_taken = $end_time - $start_time;

    $score = 0;
    $total = 0;

    mysqli_data_seek($questions_result, 0);
    while ($question = mysqli_fetch_assoc($questions_result)) {
        $total++;
        $q_id = $question['id'];
        $user_answer = isset($_POST['q_' . $q_id]) ? $_POST['q_' . $q_id] : '';

        $correct_value = $question['correct_answer'];
        if (in_array($correct_value, ['option1','option2','option3','option4'])) {
            $correct_value = $question[$correct_value];
        }

        if ($user_answer === $correct_value) {
            $score++;
        }
    }

    $score_percentage = ($total > 0) ? ($score / $total) * 100 : 0;
    $quiz_status = ($score_percentage >= 60) ? 'passed' : 'failed';

    $company_query = "SELECT company_id FROM company_jobs WHERE id = '$job_id'";
    $company_res = mysqli_query($con, $company_query);
    $company_row = mysqli_fetch_assoc($company_res);
    $company_id = $company_row['company_id'];

    // Ensure application exists
    $app_lookup = "SELECT id FROM job_applications WHERE user_id = '$user_id' AND job_id = '$job_id' ORDER BY applied_date DESC LIMIT 1";
    $app_lookup_res = mysqli_query($con, $app_lookup);
    $application_id = null;

    if (mysqli_num_rows($app_lookup_res) > 0) {
        $app_row = mysqli_fetch_assoc($app_lookup_res);
        $application_id = $app_row['id'];
        $update_app = "UPDATE job_applications
                       SET quiz_score = '$score_percentage', quiz_status = '$quiz_status'
                       WHERE id = '$application_id'";
        mysqli_query($con, $update_app);
    } else {
        $create_app = "INSERT INTO job_applications (user_id, job_id, company_id, application_status, quiz_status, quiz_score, applied_date)
                       VALUES ('$user_id', '$job_id', '$company_id', 'pending', '$quiz_status', '$score_percentage', NOW())";
        mysqli_query($con, $create_app);
        $application_id = mysqli_insert_id($con);
    }

    // Record the quiz attempt (with error handling)
    if ($application_id > 0) {
        $insert_attempt = "INSERT INTO job_quiz_attempts
                           (application_id, job_id, user_id, total_questions, correct_answers, score_percentage, time_taken, attempt_date)
                           VALUES
                           ('$application_id', '$job_id', '$user_id', '$total', '$score', '$score_percentage', '$time_taken', NOW())";
        $attempt_inserted = mysqli_query($con, $insert_attempt);

        // Even if INSERT fails (FK constraint etc.), we already locked via session
        if (!$attempt_inserted) {
            // Fallback: record in session as backup proof
            $_SESSION['quiz_result_' . $job_id] = [
                'score' => $score_percentage,
                'status' => $quiz_status,
                'total' => $total,
                'correct' => $score,
                'time' => $time_taken,
                'date' => date('Y-m-d H:i:s')
            ];
        }
    } else {
        $_SESSION['quiz_result_' . $job_id] = [
            'score' => $score_percentage,
            'status' => $quiz_status,
            'total' => $total,
            'correct' => $score,
            'time' => $time_taken,
            'date' => date('Y-m-d H:i:s')
        ];
    }

    $show_results = true;
    $result_score = round($score_percentage, 1);
    $result_correct = $score;
    $result_total = $total;
    $result_time = $time_taken;
    $result_status = $quiz_status;

    if ($quiz_status == 'passed') {
        $redirect_url = "company_job_application.php?job_id=$job_id&quiz=passed";
    } else {
        $category = $job['job_category'];
        
        if ($grooming_completed) {
            // This was the FINAL retake after grooming — user failed → permanently locked out
            mysqli_query($con, "UPDATE user_quiz_status SET status='failed', grooming_completed=0, last_attempt=NOW() WHERE user_id='$user_id' AND category='$category'");
            $redirect_url = "job_details.php?id=$job_id&exhausted=1";
        } else {
            // First attempt failed → write to user_quiz_status so grooming.php knows this user needs training
            $check_status = "SELECT id FROM user_quiz_status WHERE user_id='$user_id' AND category='$category'";
            $check_res = mysqli_query($con, $check_status);
            if (mysqli_num_rows($check_res) > 0) {
                mysqli_query($con, "UPDATE user_quiz_status SET status='failed', grooming_completed=0, last_attempt=NOW() WHERE user_id='$user_id' AND category='$category'");
            } else {
                mysqli_query($con, "INSERT INTO user_quiz_status (user_id, category, status, grooming_completed, last_attempt) VALUES ('$user_id', '$category', 'failed', 0, NOW())");
            }
            $redirect_url = "grooming.php?category=" . urlencode($category) . "&job_id=" . $job_id;
        }
    }
}

mysqli_data_seek($questions_result, 0);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assessment Quiz - <?php echo htmlspecialchars($job['job_title']); ?></title>
    <?php require_once __DIR__ . '/../includes/links.php'; ?>
    <style>
        .quiz-page-body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }

        .quiz-wrap {
            max-width: 900px;
            margin: 0 auto 60px;
            padding: 0 20px;
        }

        /* ── Timer Bar ── */
        .timer-bar {
            position: sticky;
            top: 80px;
            z-index: 999;
            background: white;
            border-radius: 0 0 24px 24px;
            padding: 18px 30px 14px;
            box-shadow: 0 12px 40px rgba(0,0,0,0.18);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }

        .timer-top-row {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }

        .timer-label {
            font-size: 15px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .timer-time {
            font-size: 52px;
            font-weight: 900;
            font-family: 'Courier New', Courier, monospace;
            letter-spacing: 4px;
            transition: color 0.5s ease;
            line-height: 1;
        }

        .timer-time.color-green { color: #10b981; }
        .timer-time.color-yellow { color: #f59e0b; }
        .timer-time.color-red { color: #ef4444; }

        .timer-progress {
            width: 100%;
            height: 6px;
            background: #e2e8f0;
            border-radius: 10px;
            overflow: hidden;
        }

        .timer-progress-fill {
            height: 100%;
            border-radius: 10px;
            transition: width 1s linear, background 0.5s ease;
        }

        .timer-progress-fill.fill-green { background: linear-gradient(90deg, #10b981, #34d399); }
        .timer-progress-fill.fill-yellow { background: linear-gradient(90deg, #f59e0b, #fbbf24); }
        .timer-progress-fill.fill-red { background: linear-gradient(90deg, #ef4444, #f87171); }

        .timer-timeouts-msg {
            display: none;
            font-size: 13px;
            color: #ef4444;
            font-weight: 700;
        }

        @keyframes timer-pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.06); }
        }
        .timer-time.pulsing {
            animation: timer-pulse 1s ease-in-out infinite;
        }

        @keyframes timer-glow {
            0%, 100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
            50% { box-shadow: 0 0 20px 4px rgba(239, 68, 68, 0.25); }
        }
        .timer-bar.urgent {
            animation: timer-glow 1.5s ease-in-out infinite;
        }

        /* ── Quiz Header Card ── */
        .quiz-header-card {
            background: white;
            border-radius: 24px;
            padding: 40px 36px 32px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            text-align: center;
            margin-top: 24px;
        }

        .quiz-header-card h1 {
            font-size: 26px;
            font-weight: 800;
            color: #1e293b;
            margin-bottom: 8px;
        }

        .quiz-header-card .company-name {
            font-size: 16px;
            color: #64748b;
            margin-bottom: 0;
        }

        .quiz-meta {
            display: flex;
            justify-content: center;
            gap: 32px;
            flex-wrap: wrap;
            margin-top: 24px;
        }

        .quiz-meta-item {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #475569;
            font-size: 15px;
        }

        .quiz-meta-item i {
            font-size: 22px;
            color: #667eea;
            width: 24px;
            text-align: center;
        }

        .quiz-meta-item strong {
            color: #1e293b;
        }

        /* ── Warning Box ── */
        .quiz-warning {
            background: #fffbeb;
            border: 2px solid #f59e0b;
            border-radius: 16px;
            padding: 18px 24px;
            margin-bottom: 28px;
            display: flex;
            align-items: flex-start;
            gap: 14px;
        }

        .quiz-warning i {
            color: #f59e0b;
            font-size: 22px;
            margin-top: 2px;
            flex-shrink: 0;
        }

        .quiz-warning-text {
            font-size: 14px;
            color: #92400e;
            line-height: 1.5;
        }

        .quiz-warning-text strong {
            color: #78350f;
        }

        /* ── Progress Tracker ── */
        .progress-tracker {
            background: white;
            border-radius: 16px;
            padding: 18px 24px;
            margin-bottom: 28px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
        }

        .progress-bar-track {
            height: 8px;
            background: #e2e8f0;
            border-radius: 10px;
            overflow: hidden;
        }

        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea, #764ba2);
            border-radius: 10px;
            transition: width 0.4s ease;
        }

        .progress-text {
            text-align: center;
            margin-top: 10px;
            font-size: 14px;
            color: #64748b;
            font-weight: 600;
        }

        .progress-text span {
            color: #667eea;
            font-weight: 800;
        }

        /* ── Question Card ── */
        .question-card {
            background: white;
            border-radius: 24px;
            padding: 36px 40px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            margin-bottom: 24px;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .question-card:hover {
            border-color: rgba(102, 126, 234, 0.2);
        }

        .question-card.answered {
            border-color: #10b981;
        }

        .q-number {
            display: inline-block;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 6px 18px;
            border-radius: 25px;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 16px;
            letter-spacing: 0.5px;
        }

        .q-text {
            font-size: 18px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 24px;
            line-height: 1.6;
        }

        .options-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .opt-wrap {
            position: relative;
        }

        .opt-input {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }

        .opt-label {
            display: flex;
            align-items: center;
            padding: 16px 22px;
            background: #f8fafc;
            border: 2px solid #e2e8f0;
            border-radius: 14px;
            cursor: pointer;
            transition: all 0.25s ease;
            font-size: 15px;
            color: #334155;
            font-weight: 500;
        }

        .opt-label:hover {
            background: #f1f5f9;
            border-color: #667eea;
        }

        .opt-input:checked + .opt-label {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-color: transparent;
            box-shadow: 0 4px 16px rgba(102, 126, 234, 0.3);
        }

        .opt-letter {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            background: white;
            border-radius: 50%;
            font-weight: 800;
            font-size: 14px;
            color: #667eea;
            margin-right: 14px;
            flex-shrink: 0;
            transition: all 0.25s ease;
        }

        .opt-input:checked + .opt-label .opt-letter {
            background: rgba(255,255,255,0.25);
            color: white;
        }

        .q-answered-check {
            display: none;
            color: #10b981;
            font-size: 20px;
            margin-left: auto;
        }

        .question-card.answered .q-answered-check {
            display: inline-block;
        }

        /* ── Submit Section ── */
        .submit-card {
            background: white;
            border-radius: 24px;
            padding: 40px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            text-align: center;
            margin-top: 36px;
        }

        .submit-card h3 {
            font-size: 22px;
            font-weight: 800;
            color: #1e293b;
            margin-bottom: 10px;
        }

        .submit-card p {
            color: #64748b;
            margin-bottom: 28px;
        }

        .btn-submit-quiz {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            padding: 16px 60px;
            border-radius: 50px;
            border: none;
            font-size: 18px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 8px 30px rgba(16, 185, 129, 0.35);
            letter-spacing: 0.5px;
        }

        .btn-submit-quiz:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 40px rgba(16, 185, 129, 0.45);
        }

        .btn-submit-quiz:active {
            transform: translateY(-1px);
        }

        /* ── Results Section ── */
        .results-card {
            background: white;
            border-radius: 24px;
            padding: 50px 40px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            text-align: center;
            margin-top: 24px;
        }

        .results-icon {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            font-size: 48px;
        }

        .results-icon.passed {
            background: linear-gradient(135deg, #d1fae5, #a7f3d0);
            color: #059669;
        }

        .results-icon.failed {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            color: #dc2626;
        }

        .results-score-circle {
            width: 180px;
            height: 180px;
            border-radius: 50%;
            margin: 0 auto 28px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .results-score-circle::before {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: 50%;
            padding: 8px;
            background: conic-gradient(var(--ring-color, #667eea) var(--ring-pct, 0%), #e2e8f0 var(--ring-pct, 0%));
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
        }

        .results-score-circle .score-val {
            font-size: 52px;
            font-weight: 900;
            line-height: 1;
        }

        .results-score-circle .score-pct {
            font-size: 18px;
            font-weight: 700;
            color: #64748b;
        }

        .results-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 12px 32px;
            border-radius: 50px;
            font-size: 18px;
            font-weight: 800;
            margin-bottom: 20px;
        }

        .results-status-badge.passed {
            background: #d1fae5;
            color: #065f46;
        }

        .results-status-badge.failed {
            background: #fee2e2;
            color: #991b1b;
        }

        .results-stats {
            display: flex;
            justify-content: center;
            gap: 40px;
            margin: 28px 0;
            flex-wrap: wrap;
        }

        .results-stat {
            text-align: center;
        }

        .results-stat .stat-val {
            font-size: 24px;
            font-weight: 800;
            color: #1e293b;
        }

        .results-stat .stat-label {
            font-size: 13px;
            color: #64748b;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .results-redirect {
            margin-top: 30px;
            padding-top: 24px;
            border-top: 1px solid #e2e8f0;
        }

        .results-redirect p {
            color: #64748b;
            margin-bottom: 16px;
        }

        .results-redirect .countdown-num {
            font-weight: 800;
            color: #667eea;
        }

        .btn-go-now {
            display: inline-block;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 14px 40px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 700;
            font-size: 16px;
            transition: all 0.3s ease;
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.35);
        }

        .btn-go-now:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 35px rgba(102, 126, 234, 0.45);
            color: white;
            text-decoration: none;
        }

        /* ── Footer ── */
        .quiz-footer {
            text-align: center;
            padding: 24px;
            margin-top: 40px;
            color: rgba(255,255,255,0.7);
            font-size: 14px;
        }

        /* ── Responsive ── */
        @media (max-width: 768px) {
            .timer-bar {
                top: 70px;
                padding: 14px 20px 10px;
            }

            .timer-time {
                font-size: 36px;
            }

            .timer-label {
                font-size: 13px;
            }

            .quiz-header-card {
                padding: 28px 20px 24px;
            }

            .quiz-header-card h1 {
                font-size: 20px;
            }

            .quiz-meta {
                flex-direction: column;
                gap: 12px;
                align-items: center;
            }

            .question-card {
                padding: 24px 20px;
            }

            .q-text {
                font-size: 16px;
            }

            .opt-label {
                padding: 14px 16px;
                font-size: 14px;
            }

            .submit-card {
                padding: 30px 20px;
            }

            .btn-submit-quiz {
                padding: 14px 40px;
                font-size: 16px;
                width: 100%;
            }

            .results-card {
                padding: 36px 20px;
            }

            .results-score-circle {
                width: 150px;
                height: 150px;
            }

            .results-score-circle .score-val {
                font-size: 42px;
            }

            .results-stats {
                gap: 24px;
            }
        }

        @media (max-width: 480px) {
            .timer-time {
                font-size: 30px;
                letter-spacing: 2px;
            }

            .quiz-wrap {
                padding: 0 12px;
            }

            .quiz-warning {
                padding: 14px 16px;
            }
        }
    </style>
</head>
<body class="quiz-page-body">

<?php if ($show_results): ?>
    <?php
        $pass_color = ($result_status === 'passed') ? '#059669' : '#dc2626';
        $pass_bg    = ($result_status === 'passed') ? '#d1fae5' : '#fee2e2';
        $ring_pct   = $result_score;
    ?>
    <div class="quiz-wrap" style="margin-top: 30px;">
        <div class="results-card">
            <div class="results-icon <?php echo $result_status; ?>">
                <i class="fas fa-<?php echo $result_status === 'passed' ? 'check' : 'times'; ?>"></i>
            </div>

            <h2 style="font-size: 28px; font-weight: 800; color: #1e293b; margin-bottom: 6px;">Quiz Complete!</h2>
            <p style="color: #64748b; font-size: 16px; margin-bottom: 30px;">
                <?php echo htmlspecialchars($job['job_title']); ?> &mdash; <?php echo htmlspecialchars($job['company_name']); ?>
            </p>

            <div class="results-score-circle" style="--ring-color: <?php echo $pass_color; ?>; --ring-pct: <?php echo $ring_pct; ?>%;">
                <div class="score-val" style="color: <?php echo $pass_color; ?>;"><?php echo $result_score; ?>%</div>
                <div class="score-pct"><?php echo $result_correct; ?>/<?php echo $result_total; ?> correct</div>
            </div>

            <div class="results-status-badge <?php echo $result_status; ?>">
                <i class="fas fa-<?php echo $result_status === 'passed' ? 'trophy' : 'exclamation-circle'; ?>"></i>
                <?php echo $result_status === 'passed' ? 'PASSED' : 'FAILED'; ?>
                &mdash; <?php echo $result_status === 'passed' ? 'Great job!' : 'Minimum 60% required'; ?>
            </div>

            <div class="results-stats">
                <div class="results-stat">
                    <div class="stat-val"><?php echo $result_correct; ?>/<?php echo $result_total; ?></div>
                    <div class="stat-label">Correct Answers</div>
                </div>
                <div class="results-stat">
                    <div class="stat-val"><?php echo $result_score; ?>%</div>
                    <div class="stat-label">Score</div>
                </div>
                <div class="results-stat">
                    <div class="stat-val"><?php echo gmdate('i:s', $result_time); ?></div>
                    <div class="stat-label">Time Taken</div>
                </div>
            </div>

            <div class="results-redirect">
                <p>
                    Redirecting in <span class="countdown-num" id="redirectCountdown">5</span> seconds...
                </p>
                <a href="<?php echo $redirect_url; ?>" class="btn-go-now">
                    <?php
                    if ($result_status === 'passed') {
                        echo 'Continue to Application';
                    } elseif ($grooming_completed) {
                        echo 'Back to Job Details';
                    } else {
                        echo 'Go to Grooming Session';
                    }
                    ?>
                    <i class="fas fa-arrow-right ml-2"></i>
                </a>
            </div>
        </div>

        <div class="quiz-footer">
            <p class="mb-0">&copy; <?php echo date('Y'); ?> NovaHire. All rights reserved.</p>
        </div>
    </div>

    <script>
        let redirectSec = 5;
        const redirectUrl = '<?php echo $redirect_url; ?>';
        const countdownEl = document.getElementById('redirectCountdown');
        const rdInterval = setInterval(function() {
            redirectSec--;
            if (redirectSec <= 0) {
                clearInterval(rdInterval);
                window.location.href = redirectUrl;
            } else {
                countdownEl.textContent = redirectSec;
            }
        }, 1000);
    </script>
</body>
</html>

<?php else: ?>

<div class="quiz-wrap">

    <!-- Timer Bar -->
    <div class="timer-bar" id="timerBar">
        <div class="timer-top-row">
            <i class="fas fa-stopwatch" style="font-size: 22px; color: #667eea;"></i>
            <span class="timer-label">Time Remaining</span>
            <span class="timer-time color-green" id="timerDisplay">05:00</span>
            <span class="timer-timeouts-msg" id="timeoutMsg">
                <i class="fas fa-exclamation-triangle mr-1"></i> Time expired — submitting...
            </span>
        </div>
        <div class="timer-progress">
            <div class="timer-progress-fill fill-green" id="timerProgressFill" style="width: 100%;"></div>
        </div>
    </div>

    <!-- Quiz Header -->
    <div class="quiz-header-card">
        <h1><i class="fas fa-clipboard-list mr-2" style="color: #667eea;"></i><?php echo htmlspecialchars($job['job_title']); ?> Assessment</h1>
        <p class="company-name"><i class="fas fa-building mr-1"></i><?php echo htmlspecialchars($job['company_name']); ?></p>

        <div class="quiz-meta">
            <div class="quiz-meta-item">
                <i class="fas fa-question-circle"></i>
                <span><strong><?php echo $total_questions; ?></strong> Questions</span>
            </div>
            <div class="quiz-meta-item">
                <i class="fas fa-check-circle"></i>
                <span>Passing Score: <strong>60%</strong></span>
            </div>
            <div class="quiz-meta-item">
                <i class="fas fa-clock"></i>
                <span>Time Limit: <strong><?php echo floor($quiz_timer / 60); ?> min <?php echo $quiz_timer % 60; ?> sec</strong></span>
            </div>
            <div class="quiz-meta-item">
                <i class="fas fa-ban"></i>
                <span>One Attempt Only</span>
            </div>
        </div>
    </div>

    <!-- Warning -->
    <div class="quiz-warning">
        <i class="fas fa-exclamation-triangle"></i>
        <div class="quiz-warning-text">
            <strong>Important:</strong> Answer all questions carefully. The timer starts now and cannot be paused.
            You can only take this quiz once. Make sure you have a stable internet connection.
        </div>
    </div>

    <!-- Quiz Form -->
    <form method="POST" id="quizForm">
        <input type="hidden" name="start_time" id="startTimeField" value="<?php echo time(); ?>">
        <input type="hidden" name="submit_quiz" value="1">

        <!-- Progress Tracker -->
        <div class="progress-tracker">
            <div class="progress-bar-track">
                <div class="progress-bar-fill" id="progressBarFill" style="width: 0%;"></div>
            </div>
            <div class="progress-text">
                <span id="answeredNum">0</span> of <strong><?php echo $total_questions; ?></strong> questions answered
            </div>
        </div>

        <?php
        $q_number = 1;
        $opt_letters = ['A', 'B', 'C', 'D'];
        while ($question = mysqli_fetch_assoc($questions_result)):
        ?>
            <div class="question-card" id="qcard_<?php echo $question['id']; ?>">
                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 18px;">
                    <span class="q-number">Question <?php echo $q_number; ?></span>
                    <i class="fas fa-check-circle q-answered-check"></i>
                </div>

                <div class="q-text"><?php echo htmlspecialchars($question['question']); ?></div>

                <div class="options-list">
                    <?php for ($i = 1; $i <= 4; $i++): ?>
                        <div class="opt-wrap">
                            <input type="radio"
                                   class="opt-input"
                                   name="q_<?php echo $question['id']; ?>"
                                   id="q<?php echo $question['id']; ?>_<?php echo $i; ?>"
                                   value="<?php echo htmlspecialchars($question['option' . $i]); ?>"
                                   onchange="onOptionChange(<?php echo $question['id']; ?>)">
                            <label class="opt-label" for="q<?php echo $question['id']; ?>_<?php echo $i; ?>">
                                <span class="opt-letter"><?php echo $opt_letters[$i - 1]; ?></span>
                                <?php echo htmlspecialchars($question['option' . $i]); ?>
                            </label>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
        <?php
            $q_number++;
        endwhile;
        ?>

        <!-- Submit -->
        <div class="submit-card">
            <h3><i class="fas fa-flag-checkered mr-2" style="color: #667eea;"></i>Ready to Submit?</h3>
            <p>Review your answers before submitting. You cannot change your answers after submission.</p>
            <button type="submit" name="submit_quiz" id="submitBtn" class="btn-submit-quiz">
                <i class="fas fa-paper-plane mr-2"></i>Submit Quiz
            </button>
        </div>
    </form>

    <div class="quiz-footer">
        <p class="mb-0">&copy; <?php echo date('Y'); ?> NovaHire. All rights reserved.</p>
    </div>
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
(function() {
    /* ── Anti-Cheat System ── */
    let warnings = 0;
    const MAX_WARNINGS = 3;
    const overlay = document.getElementById('antiCheatOverlay');
    const resumeBtn = document.getElementById('resumeQuizBtn');
    const warningsLeft = document.getElementById('warningsLeft');
    const msg = document.getElementById('antiCheatMsg');
    const form = document.getElementById('quizForm');
    let isAntiCheatActive = false;

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
        // We will start monitoring after the user clicks anywhere in the document to allow fullscreen request
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
    const TOTAL_QUESTIONS = <?php echo $total_questions; ?>;
    const QUIZ_SECONDS   = <?php echo $quiz_timer; ?>;
    const START_TIME     = <?php echo time(); ?>;
    const FORM           = document.getElementById('quizForm');
    const SUBMIT_BTN     = document.getElementById('submitBtn');
    const TIMER_EL       = document.getElementById('timerDisplay');
    const TIMER_BAR      = document.getElementById('timerBar');
    const PROGRESS_FILL  = document.getElementById('progressBarFill');
    const PROGRESS_NUM   = document.getElementById('answeredNum');
    const TIMEOUT_MSG    = document.getElementById('timeoutMsg');
    const PROGRESS_FILL_TIMER = document.getElementById('timerProgressFill');

    let timeLeft = QUIZ_SECONDS;
    let submitted = false;

    /* ── Countdown Timer ── */
    function tickTimer() {
        if (submitted) return;

        const mins = Math.floor(timeLeft / 60);
        const secs = timeLeft % 60;
        const display = String(mins).padStart(2, '0') + ':' + String(secs).padStart(2, '0');
        TIMER_EL.textContent = display;

        const pct = (timeLeft / QUIZ_SECONDS) * 100;
        PROGRESS_FILL_TIMER.style.width = pct + '%';

        TIMER_EL.classList.remove('color-green', 'color-yellow', 'color-red', 'pulsing');
        PROGRESS_FILL_TIMER.classList.remove('fill-green', 'fill-yellow', 'fill-red');
        TIMER_BAR.classList.remove('urgent');
        TIMEOUT_MSG.style.display = 'none';

        if (pct > 50) {
            TIMER_EL.classList.add('color-green');
            PROGRESS_FILL_TIMER.classList.add('fill-green');
        } else if (pct > 20) {
            TIMER_EL.classList.add('color-yellow');
            PROGRESS_FILL_TIMER.classList.add('fill-yellow');
        } else {
            TIMER_EL.classList.add('color-red', 'pulsing');
            PROGRESS_FILL_TIMER.classList.add('fill-red');
            TIMER_BAR.classList.add('urgent');
            TIMEOUT_MSG.style.display = 'inline-flex';
        }

        if (timeLeft <= 0) {
            clearInterval(timerInterval);
            TIMER_EL.textContent = '00:00';
            TIMEOUT_MSG.innerHTML = '<i class="fas fa-exclamation-triangle mr-1"></i> Time\'s up! Submitting...';
            submitted = true;
            SUBMIT_BTN.disabled = true;
            SUBMIT_BTN.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Submitting...';
            FORM.submit();
            return;
        }

        timeLeft--;
    }

    const timerInterval = setInterval(tickTimer, 1000);
    tickTimer();

    /* ── Progress Tracking ── */
    const answeredMap = {};

    window.onOptionChange = function(qId) {
        answeredMap[qId] = true;
        const count = Object.keys(answeredMap).length;
        PROGRESS_NUM.textContent = count;
        PROGRESS_FILL.style.width = ((count / TOTAL_QUESTIONS) * 100) + '%';

        const card = document.getElementById('qcard_' + qId);
        if (card) card.classList.add('answered');
    };

    /* ── Form Submit Handling ── */
    FORM.addEventListener('submit', function(e) {
        if (submitted) return;
        submitted = true;

        const count = Object.keys(answeredMap).length;
        const unanswered = TOTAL_QUESTIONS - count;

        if (unanswered > 0 && e.submitter === SUBMIT_BTN) {
            if (!confirm('You have ' + unanswered + ' unanswered question(s). Submit anyway?')) {
                submitted = false;
                return;
            }
        }

        SUBMIT_BTN.disabled = true;
        SUBMIT_BTN.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
        clearInterval(timerInterval);
    });

    /* ── Prevent accidental leave ── */
    window.addEventListener('beforeunload', function(e) {
        if (!submitted) {
            e.preventDefault();
            e.returnValue = '';
        }
    });
})();
</script>
</body>
</html>
<?php endif; ?>
