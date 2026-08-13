<?php
// Core setup: session, DB, BASE_URL, helpers
require_once __DIR__ . '/../includes/bootstrap.php';
if (!isset($_SESSION['id'])) {
    header('location: ' . BASE_URL . '/auth/login.php');
    exit();
}
require_once __DIR__ . '/../admin/dbcon.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../ai/cover_letter.php';

$user_id = $_SESSION['id'];
$user_q = mysqli_query($con, "SELECT * FROM user_info WHERE id = '$user_id'");
$user = mysqli_fetch_assoc($user_q);

// Fetch active jobs for the selector
$jobs = array();
$jq = mysqli_query($con, "SELECT cj.*, c.company_name FROM company_jobs cj JOIN companies c ON cj.company_id = c.id WHERE cj.status='active' AND cj.deadline >= CURDATE() ORDER BY cj.posted_date DESC");
if ($jq) { while ($j = mysqli_fetch_assoc($jq)) $jobs[] = $j; }

$letter = null;
$selected_job = null;

if (isset($_POST['generate'])) {
    $job_id = intval($_POST['job_id']);
    foreach ($jobs as $j) if ($j['id'] === $job_id) $selected_job = $j;
    if ($selected_job) {
        $letter = ai_generate_cover_letter($user, $selected_job);
        // Persist
        $title = $letter['title'];
        $content = $letter['content'];
        $mode = $letter['mode'];
        $stmt = mysqli_prepare($con, "INSERT INTO ai_cover_letters (user_id, job_id, title, content, mode) VALUES (?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "iisss", $user_id, $job_id, $title, $content, $mode);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>AI Cover Letter Generator | NovaHire</title>
    <?php require_once __DIR__ . '/../includes/links.php'; ?>
    <?php echo ai_css_link(); ?>
    <style>
        body { background: #f8fafc; }
        .letter-meta { font-size:0.8rem; color:#64748b; }
    </style>
</head>
<body>
<?php ai_page_header('AI Cover Letter Generator', 'Pick a job and get a personalised cover letter written from your real profile.', 'envelope-open-text'); ?>

<div class="container" style="padding-bottom: 60px;">
    <div class="row" style="margin-top: 28px;">
        <div class="col-lg-4 mb-4">
            <div class="ai-card">
                <h4 class="mb-3"><i class="fas fa-briefcase mr-2" style="color:#4f46e5;"></i>Choose a Job</h4>
                <?php if (empty($jobs)): ?>
                    <p class="text-muted" style="font-size:0.85rem;">No active jobs available right now.</p>
                <?php else: ?>
                    <form method="POST" action="">
                        <label class="font-weight-bold" style="font-size:0.82rem;">Job Position</label>
                        <select name="job_id" class="form-control mb-3" required>
                            <option value="">Select a job...</option>
                            <?php foreach ($jobs as $j): ?>
                                <option value="<?php echo $j['id']; ?>" <?php echo (isset($_POST['job_id']) && $_POST['job_id'] == $j['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($j['job_title'] . ' - ' . $j['company_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" name="generate" class="btn-ai" style="width:100%; justify-content:center;"><i class="fas fa-wand-magic-sparkles"></i> Generate Letter</button>
                    </form>
                <?php endif; ?>

                <hr>
                <h6 class="font-weight-bold" style="font-size:0.85rem;"><i class="fas fa-circle-info mr-1" style="color:#4f46e5;"></i>How it works</h6>
                <p class="text-muted" style="font-size:0.8rem; line-height:1.6;">The AI matches your real skills against the job requirements, then writes a professional letter. When an LLM key is configured, OpenAI/Gemini drafts the text; otherwise the smart template engine uses your data.</p>
            </div>
        </div>

        <div class="col-lg-8 mb-4">
            <?php if ($letter): ?>
                <div class="ai-card">
                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                        <div>
                            <h4 class="mb-1"><?php echo htmlspecialchars($letter['title']); ?></h4>
                            <div class="letter-meta">
                                Generated for <?php echo htmlspecialchars($selected_job['company_name']); ?>
                                &bull; Mode: <?php echo $letter['mode'] === 'llm' ? 'AI (LLM)' : 'Smart Template'; ?>
                                &bull; <?php echo date('M d, Y'); ?>
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn-ai-outline" onclick="copyLetter()"><i class="fas fa-copy"></i> Copy</button>
                            <button class="btn-ai" onclick="downloadLetter()"><i class="fas fa-download"></i> Download</button>
                        </div>
                    </div>
                    <div class="ai-letter-preview" id="letterPreview"><?php echo htmlspecialchars($letter['content']); ?></div>
                </div>
            <?php else: ?>
                <div class="ai-card text-center" style="padding: 70px 30px;">
                    <i class="fas fa-envelope-open-text fa-4x mb-4" style="color:#c7d2fe;"></i>
                    <h4>Ready when you are</h4>
                    <p class="text-muted mb-0">Select a job on the left and click "Generate Letter". Your personalised cover letter will appear here.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function copyLetter() {
    const txt = document.getElementById('letterPreview').innerText;
    navigator.clipboard.writeText(txt).then(() => {
        alert('Cover letter copied to clipboard!');
    }).catch(() => {
        const ta = document.createElement('textarea');
        ta.value = txt;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        ta.remove();
        alert('Cover letter copied to clipboard!');
    });
}
function downloadLetter() {
    const txt = document.getElementById('letterPreview').innerText;
    const blob = new Blob([txt], {type: 'text/plain;charset=utf-8'});
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'cover-letter.txt';
    a.click();
    URL.revokeObjectURL(a.href);
}
</script>
</body>
</html>
