<?php
// Core setup: session, DB, BASE_URL, helpers
require_once __DIR__ . '/../includes/bootstrap.php';
if (!isset($_SESSION['id'])) {
    header('location: ' . BASE_URL . '/auth/login.php');
    exit();
}
require_once __DIR__ . '/../admin/dbcon.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../ai/interview.php';

$user_id = $_SESSION['id'];
$user_q = mysqli_query($con, "SELECT * FROM user_info WHERE id = '$user_id'");
$user = mysqli_fetch_assoc($user_q);

// Categories for the selector
$cats = array('PHP', 'Java', 'Python', 'Frontend', 'DataScience', 'Finance', 'HR', 'Sales');
$category = isset($_GET['category']) ? $_GET['category'] : (isset($cats[0]) ? $cats[0] : 'PHP');

// Load DB questions for the category if any
$db_questions = array();
$djq = mysqli_query($con, "SELECT q.* FROM company_job_questions q JOIN company_jobs j ON q.job_id = j.id WHERE j.job_category = '" . mysqli_real_escape_string($con, $category) . "'");
if ($djq) { while ($dq = mysqli_fetch_assoc($djq)) $db_questions[] = $dq; }

$questions = ai_get_interview_questions($category, 5, $db_questions);
$tips = ai_interview_tips($user);

// Handle scoring submission
$results = array();
if (isset($_POST['submit_answers'])) {
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'answer_') === 0) {
            $idx = substr($key, 7);
            $qid = isset($_POST['qid_' . $idx]) ? $_POST['qid_' . $idx] : '';
            $answer = trim($value);
            // find question by id
            foreach ($questions as $i => $q) {
                if ($q['id'] === $qid) {
                    $scored = ai_score_answer($q['question'], $q['keywords'], $answer);
                    $scored['question'] = $q['question'];
                    $scored['answer'] = $answer;
                    $results[] = $scored;
                    // Persist
                    $score = $scored['score'];
                    $feedback = $scored['feedback'];
                    $stmt = mysqli_prepare($con, "INSERT INTO ai_mock_interviews (user_id, category, question, user_answer, score, feedback) VALUES (?, ?, ?, ?, ?, ?)");
                    mysqli_stmt_bind_param($stmt, "isssis", $user_id, $category, $q['question'], $answer, $score, $feedback);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);
                    break;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>AI Mock Interview | NovaHire</title>
    <?php require_once __DIR__ . '/../includes/links.php'; ?>
    <?php echo ai_css_link(); ?>
    <style>
        body { background: #f8fafc; }
        .cat-tab {
            display:inline-flex; align-items:center; gap:7px;
            padding:9px 18px; border-radius:50px; font-size:0.85rem; font-weight:600;
            border:1.5px solid #e2e8f0; color:#475569; background:white; cursor:pointer;
            text-decoration:none; transition:all 0.25s;
        }
        .cat-tab:hover { border-color:#4f46e5; color:#4f46e5; text-decoration:none; }
        .cat-tab.active { background:linear-gradient(135deg,#4f46e5,#7c3aed); color:white; border-color:transparent; }
        .res-score { font-weight:800; font-size:1.1rem; }
    </style>
</head>
<body>
<?php ai_page_header('AI Mock Interview', 'Answer real interview questions, get instant scoring and feedback to sharpen your performance.', 'clipboard-question'); ?>

<div class="container" style="padding-bottom: 60px;">
    <!-- Category tabs -->
    <div class="d-flex flex-wrap gap-2 mb-4" style="margin-top: 28px;">
        <?php foreach ($cats as $c): ?>
            <a href="ai_mock_interview.php?category=<?php echo urlencode($c); ?>" class="cat-tab <?php echo $category === $c ? 'active' : ''; ?>">
                <?php echo htmlspecialchars($c); ?>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="row">
        <div class="col-lg-8 mb-4">
            <?php if (empty($results)): ?>
                <form method="POST" action="ai_mock_interview.php?category=<?php echo urlencode($category); ?>">
                    <?php foreach ($questions as $i => $q): ?>
                        <div class="ai-qa-box">
                            <div class="q"><span class="badge badge-primary mr-2" style="background:#4f46e5;">Q<?php echo $i + 1; ?></span><?php echo htmlspecialchars($q['question']); ?></div>
                            <input type="hidden" name="qid_<?php echo $i; ?>" value="<?php echo htmlspecialchars($q['id']); ?>">
                            <textarea name="answer_<?php echo $i; ?>" rows="4" placeholder="Type your answer here..." required></textarea>
                            <small class="text-muted"><?php echo $q['source'] === 'db' ? '<i class="fas fa-database mr-1"></i>Real company assessment question' : '<i class="fas fa-robot mr-1"></i>AI practice question'; ?></small>
                        </div>
                    <?php endforeach; ?>
                    <button type="submit" name="submit_answers" class="btn-ai"><i class="fas fa-paper-plane"></i> Submit & Get Feedback</button>
                </form>
            <?php else: ?>
                <div class="ai-card mb-3">
                    <h4 class="mb-2">Your Results</h4>
                    <?php
                    $total = 0; $cnt = 0;
                    foreach ($results as $r) { $total += $r['score']; $cnt++; }
                    $avg = $cnt > 0 ? round($total / $cnt) : 0;
                    ?>
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <?php ai_score_ring($avg, 'Average', 110); ?>
                        <div>
                            <h5 class="mb-1">Average score: <span style="color:<?php echo ai_readiness_label($avg)[1]; ?>;"><?php echo $avg; ?>%</span></h5>
                            <p class="text-muted mb-0" style="font-size:0.85rem;"><?php echo ai_readiness_label($avg)[0]; ?> - review the feedback below to improve.</p>
                        </div>
                    </div>
                    <a href="ai_mock_interview.php?category=<?php echo urlencode($category); ?>" class="btn-ai-outline"><i class="fas fa-redo"></i> Try Again</a>
                </div>
                <?php foreach ($results as $i => $r): ?>
                    <div class="ai-qa-box">
                        <div class="q"><span class="badge badge-primary mr-2" style="background:#4f46e5;">Q<?php echo $i + 1; ?></span><?php echo htmlspecialchars($r['question']); ?></div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="res-score" style="color:<?php echo ai_readiness_label($r['score'])[1]; ?>;"><?php echo $r['score']; ?>/100</span>
                            <span class="label-chip" style="background:<?php echo ai_readiness_label($r['score'])[1]; ?>15; color:<?php echo ai_readiness_label($r['score'])[1]; ?>;"><?php echo ai_readiness_label($r['score'])[0]; ?></span>
                        </div>
                        <div class="mb-2" style="background:#eef2ff; padding:10px 14px; border-radius:10px; font-size:0.82rem; color:#312e81;">
                            <strong>Your answer:</strong> <?php echo htmlspecialchars($r['answer']); ?>
                        </div>
                        <div style="font-size:0.85rem; color:#334155;"><?php echo $r['feedback']; ?></div>
                        <?php if (!empty($r['missing'])): ?>
                            <div class="mt-2">
                                <small class="font-weight-bold text-danger"><i class="fas fa-list mr-1"></i>Consider mentioning:</small>
                                <div class="mt-1">
                                    <?php foreach ($r['missing'] as $m): ?><span class="ai-chip bad"><?php echo htmlspecialchars(ucfirst($m)); ?></span><?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($r['tips'])): ?>
                            <div class="mt-2">
                                <small class="font-weight-bold" style="color:#059669;"><i class="fas fa-lightbulb mr-1"></i>Tips:</small>
                                <ul class="mb-0 mt-1" style="font-size:0.8rem; color:#475569;">
                                    <?php foreach ($r['tips'] as $t): ?><li><?php echo $t; ?></li><?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <a href="ai_mock_interview.php?category=<?php echo urlencode($category); ?>" class="btn-ai"><i class="fas fa-redo"></i> Practice Again</a>
            <?php endif; ?>
        </div>

        <!-- Sidebar: tips + actions -->
        <div class="col-lg-4 mb-4">
            <div class="ai-card mb-4">
                <h4 class="mb-3"><i class="fas fa-bullhorn mr-2" style="color:#d97706;"></i>Interview Tips</h4>
                <?php foreach ($tips as $i => $t): ?>
                    <div class="d-flex align-items-start mb-2">
                        <span class="badge badge-primary mr-2 mt-1" style="background:#d97706;"><?php echo $i + 1; ?></span>
                        <span style="font-size:0.85rem; color:#334155;"><?php echo $t; ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="ai-card">
                <h4 class="mb-3"><i class="fas fa-link mr-2" style="color:#4f46e5;"></i>Related</h4>
                <div class="d-flex flex-column gap-2">
                    <a href="ai_grooming_coach.php?category=<?php echo urlencode($category); ?>" class="btn-ai-outline"><i class="fas fa-graduation-cap"></i> Grooming Coach</a>
                    <a href="ai_resume_analyzer.php" class="btn-ai-outline"><i class="fas fa-file-lines"></i> Resume Analyzer</a>
                    <a href="ai_cover_letter_generator.php" class="btn-ai-outline"><i class="fas fa-envelope-open-text"></i> Cover Letter</a>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
