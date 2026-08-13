<?php
// Core setup: session, DB, BASE_URL, helpers
require_once __DIR__ . '/../includes/bootstrap.php';
if (!isset($_SESSION['id'])) {
    header('location: ' . BASE_URL . '/auth/login.php');
    exit();
}
require_once __DIR__ . '/../admin/dbcon.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../ai/resume.php';

$user_id = $_SESSION['id'];
$user_q = mysqli_query($con, "SELECT * FROM user_info WHERE id = '$user_id'");
$user = mysqli_fetch_assoc($user_q);

// Context stats
$job_counts = array();
$apps_q = mysqli_query($con, "SELECT COUNT(*) c FROM job_applications WHERE user_id = '$user_id'");
$job_counts['applications'] = $apps_q ? intval(mysqli_fetch_assoc($apps_q)['c']) : 0;
$saved_q = mysqli_query($con, "SELECT COUNT(*) c FROM saved_jobs WHERE user_id = '$user_id'");
$job_counts['saved'] = $saved_q ? intval(mysqli_fetch_assoc($saved_q)['c']) : 0;
$passed_q = mysqli_query($con, "SELECT COUNT(*) c FROM job_applications WHERE user_id = '$user_id' AND quiz_status='passed'");
$job_counts['quiz_passed'] = $passed_q ? intval(mysqli_fetch_assoc($passed_q)['c']) : 0;

$analysis = ai_analyze_resume($user, $job_counts);

// Persist snapshot
$details = json_encode(array(
    'dimensions' => $analysis['dimensions'],
    'suggestions' => $analysis['suggestions'],
));
$stmt = mysqli_prepare($con, "INSERT INTO ai_resume_analyses (user_id, score, details) VALUES (?, ?, ?)");
mysqli_stmt_bind_param($stmt, "iis", $user_id, $analysis['total'], $details);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>AI Resume Analyzer | NovaHire</title>
    <?php require_once __DIR__ . '/../includes/links.php'; ?>
    <?php echo ai_css_link(); ?>
    <style>
        body { background: #f8fafc; }
        .label-chip { display:inline-flex; align-items:center; gap:6px; padding:6px 14px; border-radius:20px; font-weight:700; font-size:0.8rem; }
        .ai-section { margin-top: 30px; }
    </style>
</head>
<body>
<?php ai_page_header('AI Resume Analyzer', 'An objective, five-dimension review of your profile with a personalised improvement plan.', 'file-lines'); ?>

<div class="container" style="padding-bottom: 60px;">
    <div class="row" style="margin-top: 28px;">
        <!-- Left: overall score -->
        <div class="col-lg-4 mb-4">
            <div class="ai-card text-center">
                <h4 class="mb-4">Overall Resume Score</h4>
                <?php ai_score_ring($analysis['total'], 'Resume Score'); ?>
                <div class="mt-3">
                    <span class="label-chip" style="background:<?php echo ai_readiness_label($analysis['total'])[1]; ?>15; color:<?php echo ai_readiness_label($analysis['total'])[1]; ?>;">
                        <i class="fas fa-circle" style="font-size:0.5rem;"></i><?php echo ai_readiness_label($analysis['total'])[0]; ?>
                    </span>
                </div>
                <?php if (!empty($analysis['llm_summary'])): ?>
                    <div class="mt-3 p-3" style="background:#f5f3ff; border-radius:12px; font-size:0.82rem; color:#4c1d95; text-align:left;">
                        <i class="fas fa-robot mr-2"></i><?php echo nl2br(htmlspecialchars($analysis['llm_summary'])); ?>
                    </div>
                <?php endif; ?>
                <a href="profile.php" class="btn-ai mt-4" style="width:100%; justify-content:center;"><i class="fas fa-user-pen"></i> Improve My Profile</a>
            </div>
        </div>

        <!-- Middle: dimensions -->
        <div class="col-lg-4 mb-4">
            <div class="ai-card">
                <h4 class="mb-3"><i class="fas fa-chart-pie mr-2" style="color:#4f46e5;"></i>Dimension Breakdown</h4>
                <?php
                $labels = array('skills' => 'Skills', 'education' => 'Education', 'experience' => 'Experience', 'completeness' => 'Profile Completeness', 'career' => 'Career Activity');
                foreach ($analysis['dimensions'] as $key => $d) {
                    $label = isset($labels[$key]) ? $labels[$key] : ucfirst($key);
                    ai_gauge_bar($label, $d['score'], $d['note']);
                }
                ?>
            </div>
        </div>

        <!-- Right: strengths & gaps -->
        <div class="col-lg-4 mb-4">
            <div class="ai-card">
                <h4 class="mb-3"><i class="fas fa-list-check mr-2" style="color:#059669;"></i>Strengths & Gaps</h4>
                <?php if (!empty($analysis['strengths'])): ?>
                    <h6 class="font-weight-bold" style="font-size:0.8rem; color:#059669; text-transform:uppercase; letter-spacing:0.4px;">Strengths</h6>
                    <?php foreach ($analysis['strengths'] as $s): ?>
                        <div class="mb-2" style="font-size:0.8rem; color:#334155;"><i class="fas fa-check-circle mr-2" style="color:#059669;"></i><?php echo htmlspecialchars($s); ?></div>
                    <?php endforeach; ?>
                <?php endif; ?>
                <?php if (!empty($analysis['gaps'])): ?>
                    <h6 class="font-weight-bold mt-3" style="font-size:0.8rem; color:#dc2626; text-transform:uppercase; letter-spacing:0.4px;">Gaps</h6>
                    <?php foreach ($analysis['gaps'] as $g): ?>
                        <div class="mb-2" style="font-size:0.8rem; color:#334155;"><i class="fas fa-circle-exclamation mr-2" style="color:#dc2626;"></i><?php echo htmlspecialchars($g); ?></div>
                    <?php endforeach; ?>
                <?php endif; ?>
                <?php if (empty($analysis['strengths']) && empty($analysis['gaps'])): ?>
                    <p style="font-size:0.85rem; color:#64748b;">Complete your profile to see a detailed breakdown.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Improvement plan -->
    <div class="ai-card">
        <h4 class="mb-3"><i class="fas fa-lightbulb mr-2" style="color:#d97706;"></i>AI Improvement Plan</h4>
        <div class="row">
            <?php foreach ($analysis['suggestions'] as $i => $s): ?>
                <div class="col-md-6 mb-2">
                    <div class="d-flex align-items-start">
                        <span class="badge badge-primary mr-2 mt-1" style="background:#4f46e5;"><?php echo $i + 1; ?></span>
                        <span style="font-size:0.88rem; color:#334155;"><?php echo $s; ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="mt-3 d-flex flex-wrap gap-2">
            <a href="ai_cover_letter_generator.php" class="btn-ai"><i class="fas fa-envelope-open-text"></i> Generate Cover Letter</a>
            <a href="ai_mock_interview.php" class="btn-ai-outline"><i class="fas fa-clipboard-question"></i> Practice Interview</a>
            <a href="ai_grooming_coach.php" class="btn-ai-outline"><i class="fas fa-graduation-cap"></i> Get Study Plan</a>
        </div>
    </div>
</div>
</body>
</html>
