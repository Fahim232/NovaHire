<?php
/**
 * NovaHire AI - Chat API endpoint (AJAX)
 * POST: message=...
 * Returns JSON: {reply, intent, buttons}
 */
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id'])) {
    echo json_encode(array('reply' => 'Please log in to use the AI assistant.', 'intent' => 'auth', 'buttons' => array()));
    exit();
}

require_once __DIR__ . '/../ai/chatbot.php';
require_once __DIR__ . '/../ai/matching.php';
require_once __DIR__ . '/../admin/dbcon.php';

$user_id = intval($_SESSION['id']);
$message = isset($_POST['message']) ? trim($_POST['message']) : '';

if ($message === '') {
    echo json_encode(array('reply' => 'Type a message to get started.', 'intent' => 'empty', 'buttons' => array()));
    exit();
}

// Load user profile
$user = array();
$uq = mysqli_query($con, "SELECT * FROM user_info WHERE id = $user_id");
if ($uq && mysqli_num_rows($uq) > 0) $user = mysqli_fetch_assoc($uq);

// Build context
$context = array();

$apps_q = mysqli_query($con, "SELECT COUNT(*) c FROM job_applications WHERE user_id = $user_id");
$context['applications_count'] = $apps_q ? intval(mysqli_fetch_assoc($apps_q)['c']) : 0;

$saved_q = mysqli_query($con, "SELECT COUNT(*) c FROM saved_jobs WHERE user_id = $user_id");
$context['saved_count'] = $saved_q ? intval(mysqli_fetch_assoc($saved_q)['c']) : 0;

$passed_q = mysqli_query($con, "SELECT COUNT(*) c FROM job_applications WHERE user_id = $user_id AND quiz_status = 'passed'");
$context['quiz_passed'] = $passed_q ? intval(mysqli_fetch_assoc($passed_q)['c']) : 0;

// Resume score (cheap: compute from profile completeness)
$context['resume_score'] = 0;
if (!empty($user)) {
    $fields = array('username' => 15, 'email' => 15, 'phone' => 15, 'user_degree' => 15, 'user_skills' => 20, 'profile' => 10, 'about_me' => 10);
    foreach ($fields as $f => $w) if (!empty($user[$f])) $context['resume_score'] += $w;
}

// Top job match
$top = null;
if (!empty($user['user_skills'])) {
    $jq = mysqli_query($con, "SELECT cj.*, c.company_name FROM company_jobs cj JOIN companies c ON cj.company_id = c.id WHERE cj.status='active' AND cj.deadline >= CURDATE() ORDER BY cj.posted_date DESC LIMIT 40");
    if ($jq) {
        $best = null;
        while ($j = mysqli_fetch_assoc($jq)) {
            $ai = ai_match_profile_job($user, $j);
            if ($best === null || $ai['score'] > $best['ai']['score']) {
                $j['ai'] = $ai;
                $best = $j;
            }
        }
        if ($best) $top = $best;
    }
}
$context['top_match'] = $top;

// Grooming progress summary
$context['grooming_progress'] = array('total' => 0, 'done' => 0, 'category' => 'PHP');

$result = ai_chatbot_respond($user, $message, $context);

// Persist history
$stmt = mysqli_prepare($con, "INSERT INTO ai_chat_history (user_id, role, message, intent) VALUES (?, 'user', ?, NULL)");
mysqli_stmt_bind_param($stmt, "is", $user_id, $message);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare($con, "INSERT INTO ai_chat_history (user_id, role, message, intent) VALUES (?, 'assistant', ?, ?)");
$intent = $result['intent'];
mysqli_stmt_bind_param($stmt, "iss", $user_id, $result['reply'], $intent);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

echo json_encode($result);
exit();
