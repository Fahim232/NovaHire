<?php
// Core setup: session, DB, BASE_URL, helpers
require_once __DIR__ . '/../includes/bootstrap.php';
if (!isset($_SESSION['id'])) {
    header('location: ' . BASE_URL . '/auth/login.php');
    exit();
}
require_once __DIR__ . '/../admin/dbcon.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../ai/grooming.php';

$user_id = $_SESSION['id'];

// Available categories (from grooming videos)
$cats = array();
$cq = mysqli_query($con, "SELECT DISTINCT category FROM grooming_videos ORDER BY category");
if ($cq) { while ($c = mysqli_fetch_assoc($cq)) $cats[] = $c['category']; }
if (empty($cats)) $cats = array('PHP', 'Java', 'Python', 'Frontend');

$category = isset($_GET['category']) ? $_GET['category'] : (isset($cats[0]) ? $cats[0] : 'PHP');
$plan = ai_grooming_plan($category, $user_id);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>AI Grooming Coach | NovaHire</title>
    <?php require_once __DIR__ . '/../includes/links.php'; ?>
    <?php echo ai_css_link(); ?>
    <style>
        body { background: #f8fafc; }
        .cat-tab {
            display:inline-flex; align-items:center; gap:7px;
            padding:9px 20px; border-radius:50px; font-size:0.88rem; font-weight:600;
            border:1.5px solid #e2e8f0; color:#475569; background:white; cursor:pointer;
            text-decoration:none; transition:all 0.25s;
        }
        .cat-tab:hover { border-color:#4f46e5; color:#4f46e5; text-decoration:none; }
        .cat-tab.active { background:linear-gradient(135deg,#4f46e5,#7c3aed); color:white; border-color:transparent; }
    </style>
</head>
<body>
<?php ai_page_header('AI Grooming Coach', 'A personalised study plan built from your quiz performance and video progress.', 'graduation-cap'); ?>

<div class="container" style="padding-bottom: 60px;">
    <!-- Category tabs -->
    <div class="d-flex flex-wrap gap-2 mb-4" style="margin-top: 28px;">
        <?php foreach ($cats as $c): ?>
            <a href="ai_grooming_coach.php?category=<?php echo urlencode($c); ?>" class="cat-tab <?php echo $category === $c ? 'active' : ''; ?>">
                <i class="fas fa-code"></i> <?php echo htmlspecialchars($c); ?>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="row">
        <!-- Left: progress + plan -->
        <div class="col-lg-7 mb-4">
            <div class="ai-card mb-4">
                <h4 class="mb-3"><i class="fas fa-chart-line mr-2" style="color:#059669;"></i>Your <?php echo htmlspecialchars($category); ?> Progress</h4>
                <div class="row">
                    <div class="col-4 text-center">
                        <div class="v" style="font-size:1.6rem; font-weight:800; color:#4f46e5;"><?php echo $plan['progress']['videos_done']; ?>/<?php echo $plan['progress']['videos_total']; ?></div>
                        <div style="font-size:0.75rem; color:#64748b;">Videos done</div>
                    </div>
                    <div class="col-4 text-center">
                        <div class="v" style="font-size:1.6rem; font-weight:800; color:#d97706;"><?php echo $plan['progress']['attempts']; ?></div>
                        <div style="font-size:0.75rem; color:#64748b;">Attempts</div>
                    </div>
                    <div class="col-4 text-center">
                        <div class="v" style="font-size:1.6rem; font-weight:800; color:#059669;"><?php echo $plan['progress']['avg_score'] !== null ? $plan['progress']['avg_score'] . '%' : 'n/a'; ?></div>
                        <div style="font-size:0.75rem; color:#64748b;">Avg score</div>
                    </div>
                </div>
            </div>

            <div class="ai-card">
                <h4 class="mb-3"><i class="fas fa-bullseye mr-2" style="color:#dc2626;"></i>Weak Topics to Focus On</h4>
                <?php if (!empty($plan['weak_topics'])): ?>
                    <div class="mb-3">
                        <?php foreach ($plan['weak_topics'] as $t): ?>
                            <span class="ai-chip bad"><i class="fas fa-book-open"></i> <?php echo htmlspecialchars($t); ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted" style="font-size:0.85rem;">Great news - no weak topics identified for this category yet.</p>
                <?php endif; ?>

                <h4 class="mb-3 mt-4"><i class="fas fa-medal mr-2" style="color:#059669;"></i>Strong Topics</h4>
                <?php if (!empty($plan['strong_topics'])): ?>
                    <div class="mb-3">
                        <?php foreach ($plan['strong_topics'] as $t): ?>
                            <span class="ai-chip good"><i class="fas fa-check"></i> <?php echo htmlspecialchars($t); ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted" style="font-size:0.85rem;">Keep practicing to build up strong topics.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right: tips -->
        <div class="col-lg-5 mb-4">
            <div class="ai-card">
                <h4 class="mb-3"><i class="fas fa-lightbulb mr-2" style="color:#d97706;"></i>Coach's Study Plan</h4>
                <?php if (!empty($plan['llm_summary'])): ?>
                    <div class="mb-3 p-3" style="background:#f5f3ff; border-radius:12px; font-size:0.85rem; color:#4c1d95;">
                        <i class="fas fa-robot mr-2"></i><?php echo nl2br(htmlspecialchars($plan['llm_summary'])); ?>
                    </div>
                <?php endif; ?>
                <?php foreach ($plan['tips'] as $i => $tip): ?>
                    <div class="d-flex align-items-start mb-3">
                        <span class="badge badge-primary mr-2 mt-1" style="background:#4f46e5;"><?php echo $i + 1; ?></span>
                        <span style="font-size:0.88rem; color:#334155;"><?php echo $tip; ?></span>
                    </div>
                <?php endforeach; ?>

                <div class="d-flex flex-wrap gap-2 mt-4">
                    <a href="grooming.php?category=<?php echo urlencode($category); ?>" class="btn-ai"><i class="fas fa-play"></i> Go to Grooming Hub</a>
                    <a href="quiz.php?category=<?php echo urlencode($category); ?>" class="btn-ai-outline"><i class="fas fa-clipboard-check"></i> Take Assessment</a>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
