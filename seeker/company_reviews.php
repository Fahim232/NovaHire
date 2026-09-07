<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_seeker_login();

$user_id = $_SESSION['id'];
$company_id = intval($_GET['company_id'] ?? 0);

if (!$company_id) {
    header('Location: ../seeker/browse_jobs.php');
    exit;
}

// Fetch company info
$comp_stmt = mysqli_prepare($con, "SELECT id, company_name, industry, logo, avg_rating, is_verified, description FROM companies WHERE id = ?");
mysqli_stmt_bind_param($comp_stmt, "i", $company_id);
mysqli_stmt_execute($comp_stmt);
$company = mysqli_fetch_assoc(mysqli_stmt_get_result($comp_stmt));
mysqli_stmt_close($comp_stmt);

if (!$company) { header('Location: ../seeker/browse_jobs.php'); exit; }

// Fetch reviews
$rev_stmt = mysqli_prepare($con, "SELECT cr.*, ui.username FROM company_reviews cr 
                                   LEFT JOIN user_info ui ON cr.user_id = ui.id 
                                   WHERE cr.company_id = ? ORDER BY cr.created_at DESC");
mysqli_stmt_bind_param($rev_stmt, "i", $company_id);
mysqli_stmt_execute($rev_stmt);
$reviews = mysqli_fetch_all(mysqli_stmt_get_result($rev_stmt), MYSQLI_ASSOC);
mysqli_stmt_close($rev_stmt);

// Calculate averages
$avg_ratings = [
    'overall' => $company['avg_rating'] ?? 0,
    'work_env' => 0, 'management' => 0, 'salary' => 0,
    'work_life' => 0, 'career_growth' => 0
];
if (!empty($reviews)) {
    $counts = array_fill_keys(array_keys($avg_ratings), 0);
    $sums = array_fill_keys(array_keys($avg_ratings), 0);
    foreach ($reviews as $r) {
        foreach ($avg_ratings as $k => $v) {
            $field = $k === 'overall' ? 'rating' : $k;
            if (isset($r[$field]) && $r[$field] > 0) {
                $sums[$k] += $r[$field];
                $counts[$k]++;
            }
        }
    }
    foreach ($avg_ratings as $k => $v) {
        $avg_ratings[$k] = $counts[$k] > 0 ? round($sums[$k] / $counts[$k], 1) : 0;
    }
}

// Check if user already reviewed
$check_stmt = mysqli_prepare($con, "SELECT id FROM company_reviews WHERE company_id = ? AND user_id = ?");
mysqli_stmt_bind_param($check_stmt, "ii", $company_id, $user_id);
mysqli_stmt_execute($check_stmt);
$has_reviewed = mysqli_num_rows(mysqli_stmt_get_result($check_stmt)) > 0;
mysqli_stmt_close($check_stmt);

// Handle new review submission
$success = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$has_reviewed) {
    $rating = intval($_POST['rating'] ?? 0);
    $work_env = intval($_POST['work_env'] ?? 0);
    $management = intval($_POST['management'] ?? 0);
    $salary_rating = intval($_POST['salary_rating'] ?? 0);
    $work_life = intval($_POST['work_life'] ?? 0);
    $career_growth = intval($_POST['career_growth'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $pros = trim($_POST['pros'] ?? '');
    $cons = trim($_POST['cons'] ?? '');
    $is_anonymous = isset($_POST['is_anonymous']) ? 1 : 0;
    
    if ($rating < 1 || $rating > 5) {
        $error = 'Please select an overall rating';
    } else {
        $ins_stmt = mysqli_prepare($con, "INSERT INTO company_reviews 
            (company_id, user_id, rating, work_env, management, salary, work_life, career_growth, title, pros, cons, is_anonymous, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        mysqli_stmt_bind_param($ins_stmt, "iiiiiiisssi", $company_id, $user_id, $rating, $work_env, $management, $salary_rating, $work_life, $career_growth, $title, $pros, $cons, $is_anonymous);
        
        if (mysqli_stmt_execute($ins_stmt)) {
            // Update company avg rating
            $avg_stmt = mysqli_prepare($con, "UPDATE companies SET avg_rating = (SELECT AVG(rating) FROM company_reviews WHERE company_id = ?) WHERE id = ?");
            mysqli_stmt_bind_param($avg_stmt, "ii", $company_id, $company_id);
            mysqli_stmt_execute($avg_stmt);
            mysqli_stmt_close($avg_stmt);
            
            $success = 'Review submitted successfully!';
            $has_reviewed = true;
            // Refresh reviews
            $rev_stmt = mysqli_prepare($con, "SELECT cr.*, ui.username FROM company_reviews cr LEFT JOIN user_info ui ON cr.user_id = ui.id WHERE cr.company_id = ? ORDER BY cr.created_at DESC");
            mysqli_stmt_bind_param($rev_stmt, "i", $company_id);
            mysqli_stmt_execute($rev_stmt);
            $reviews = mysqli_fetch_all(mysqli_stmt_get_result($rev_stmt), MYSQLI_ASSOC);
            mysqli_stmt_close($rev_stmt);
        } else {
            $error = 'Failed to submit review. Please try again.';
        }
        mysqli_stmt_close($ins_stmt);
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
    .rev-page { max-width: 1000px; margin: 0 auto; padding: 30px 20px; }
    .rev-hero {
        background: linear-gradient(135deg, #1a56db, #0ea5e9);
        color: white; border-radius: 20px; padding: 30px; margin-bottom: 30px;
        display: flex; align-items: center; gap: 24px;
    }
    .rev-company-logo {
        width: 80px; height: 80px; border-radius: 16px; background: white;
        display: flex; align-items: center; justify-content: center; font-size: 2rem; color: var(--purple);
        flex-shrink: 0;
    }
    .rev-company-logo img { width: 100%; height: 100%; border-radius: 16px; object-fit: cover; }
    .rev-hero h1 { font-size: 1.6rem; font-weight: 800; margin-bottom: 4px; }
    .rev-hero .verified { background: rgba(255,255,255,0.2); padding: 2px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 600; display: inline-block; margin-left: 8px; }
    .rev-hero p { opacity: 0.85; font-size: 0.9rem; }
    .rev-stats { display: grid; grid-template-columns: repeat(5, 1fr); gap: 16px; margin-bottom: 30px; }
    .rev-stat-card {
        background: var(--bg-card); border: 1px solid var(--border-light);
        border-radius: 14px; padding: 18px; text-align: center;
    }
    .rev-stat-card .label { font-size: 0.75rem; color: var(--text-muted); margin-bottom: 8px; }
    .rev-stat-card .score { font-size: 1.8rem; font-weight: 800; color: var(--purple); }
    .rev-stat-card .stars { color: #fbbf24; font-size: 0.8rem; margin-top: 4px; }
    .rev-section-title { font-size: 1.1rem; font-weight: 700; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
    .rev-card {
        background: var(--bg-card); border: 1px solid var(--border-light);
        border-radius: 14px; padding: 20px; margin-bottom: 16px;
    }
    .rev-card-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; }
    .rev-card-header .author { font-weight: 700; font-size: 0.9rem; }
    .rev-card-header .date { color: var(--text-muted); font-size: 0.8rem; }
    .rev-rating-badge {
        display: inline-flex; align-items: center; gap: 4px;
        background: rgba(26,86,219,0.1); color: var(--purple); padding: 3px 10px;
        border-radius: 8px; font-weight: 700; font-size: 0.85rem;
    }
    .rev-card h4 { font-size: 1rem; margin-bottom: 8px; }
    .rev-card .pros-cons { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 12px; }
    .rev-pros, .rev-cons { padding: 10px; border-radius: 10px; font-size: 0.85rem; }
    .rev-pros { background: rgba(5,150,105,0.08); color: #059669; }
    .rev-cons { background: rgba(239,68,68,0.08); color: #dc2626; }
    .rev-pros strong, .rev-cons strong { display: block; margin-bottom: 4px; font-size: 0.8rem; }
    .rev-form-card {
        background: var(--bg-card); border: 1px solid var(--border-light);
        border-radius: 14px; padding: 24px; margin-bottom: 30px;
    }
    .rev-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
    .rev-form-group { margin-bottom: 14px; }
    .rev-form-group label { display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 6px; }
    .rev-form-group input, .rev-form-group textarea, .rev-form-group select {
        width: 100%; padding: 10px 14px; border: 1px solid var(--border-light);
        border-radius: 10px; font-family: inherit; font-size: 0.9rem; transition: border 0.2s;
    }
    .rev-form-group input:focus, .rev-form-group textarea:focus, .rev-form-group select:focus {
        border-color: var(--purple); outline: none;
    }
    .rev-form-group textarea { resize: vertical; min-height: 80px; }
    .star-rating { display: flex; gap: 6px; }
    .star-rating input[type="radio"] { display: none; }
    .star-rating label { cursor: pointer; font-size: 1.5rem; color: #d1d5db; transition: color 0.15s; }
    .star-rating input[type="radio"]:checked ~ label,
    .star-rating label:hover, .star-rating label:hover ~ label { color: #fbbf24; }
    .rev-rating-row { display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; margin-bottom: 14px; }
    .rev-rating-item { text-align: center; }
    .rev-rating-item label { font-size: 0.75rem; color: var(--text-muted); display: block; margin-bottom: 4px; }
    .rev-rating-item select { width: 100%; padding: 6px; border: 1px solid var(--border-light); border-radius: 8px; font-size: 0.85rem; }
    @media (max-width: 700px) {
        .rev-stats { grid-template-columns: repeat(2, 1fr); }
        .rev-hero { flex-direction: column; text-align: center; }
        .rev-form-grid, .rev-rating-row { grid-template-columns: 1fr; }
    }
</style>

<div class="rev-page">
    <div class="rev-hero">
        <div class="rev-company-logo">
            <?php if ($company['logo']): ?>
                <img src="<?php echo htmlspecialchars($company['logo']); ?>" alt="">
            <?php else: ?>
                <i class="fas fa-building"></i>
            <?php endif; ?>
        </div>
        <div>
            <h1><?php echo htmlspecialchars($company['company_name']); ?>
                <?php if ($company['is_verified']): ?><span class="verified"><i class="fas fa-check-circle"></i> Verified</span><?php endif; ?>
            </h1>
            <p><?php echo htmlspecialchars($company['industry'] ?? 'Industry'); ?> • <?php echo count($reviews); ?> reviews</p>
        </div>
    </div>
    
    <div class="rev-stats">
        <?php
        $stat_labels = [
            'overall' => 'Overall', 'work_env' => 'Work Environment',
            'management' => 'Management', 'salary' => 'Salary & Benefits',
            'work_life' => 'Work-Life Balance', 'career_growth' => 'Career Growth'
        ];
        $display_stats = array_slice($stat_labels, 0, 5);
        ?>
        <?php foreach ($display_stats as $key => $label): ?>
            <div class="rev-stat-card">
                <div class="label"><?php echo $label; ?></div>
                <div class="score"><?php echo $avg_ratings[$key]; ?></div>
                <div class="stars">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <i class="fas fa-star" style="color: <?php echo $i <= round($avg_ratings[$key]) ? '#fbbf24' : '#d1d5db'; ?>;"></i>
                    <?php endfor; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <?php if ($success): ?>
        <div style="background:#d1fae5;color:#065f46;padding:14px 20px;border-radius:12px;margin-bottom:20px;font-weight:600;">
            <i class="fas fa-check-circle mr-1"></i> <?php echo $success; ?>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div style="background:#fee2e2;color:#991b1b;padding:14px 20px;border-radius:12px;margin-bottom:20px;font-weight:600;">
            <i class="fas fa-exclamation-circle mr-1"></i> <?php echo $error; ?>
        </div>
    <?php endif; ?>
    
    <?php if (!$has_reviewed): ?>
    <div class="rev-form-card">
        <h3 class="rev-section-title"><i class="fas fa-pen" style="color:var(--purple);"></i> Write a Review</h3>
        <form method="POST">
            <div class="rev-form-group">
                <label>Overall Rating *</label>
                <div class="star-rating">
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                        <input type="radio" name="rating" id="star<?php echo $i; ?>" value="<?php echo $i; ?>">
                        <label for="star<?php echo $i; ?>"><i class="fas fa-star"></i></label>
                    <?php endfor; ?>
                </div>
            </div>
            
            <div class="rev-rating-row">
                <div class="rev-rating-item"><label>Work Env</label><select name="work_env"><?php for($i=5;$i>=1;$i--):?><option value="<?=$i?>"><?=$i?></option><?php endfor;?></select></div>
                <div class="rev-rating-item"><label>Management</label><select name="management"><?php for($i=5;$i>=1;$i--):?><option value="<?=$i?>"><?=$i?></option><?php endfor;?></select></div>
                <div class="rev-rating-item"><label>Salary</label><select name="salary_rating"><?php for($i=5;$i>=1;$i--):?><option value="<?=$i?>"><?=$i?></option><?php endfor;?></select></div>
                <div class="rev-rating-item"><label>Work-Life</label><select name="work_life"><?php for($i=5;$i>=1;$i--):?><option value="<?=$i?>"><?=$i?></option><?php endfor;?></select></div>
                <div class="rev-rating-item"><label>Career Growth</label><select name="career_growth"><?php for($i=5;$i>=1;$i--):?><option value="<?=$i?>"><?=$i?></option><?php endfor;?></select></div>
            </div>
            
            <div class="rev-form-grid">
                <div class="rev-form-group">
                    <label>Review Title</label>
                    <input type="text" name="title" placeholder="e.g. Great work culture" required>
                </div>
                <div class="rev-form-group">
                    <label>&nbsp;</label>
                    <label style="display:flex;align-items:center;gap:8px;font-weight:400;cursor:pointer;">
                        <input type="checkbox" name="is_anonymous" value="1" style="width:auto;"> Post anonymously
                    </label>
                </div>
            </div>
            <div class="rev-form-grid">
                <div class="rev-form-group">
                    <label>Pros (What's good?)</label>
                    <textarea name="pros" placeholder="e.g. Good work environment, supportive team..."></textarea>
                </div>
                <div class="rev-form-group">
                    <label>Cons (What could improve?)</label>
                    <textarea name="cons" placeholder="e.g. Long hours, limited growth..."></textarea>
                </div>
            </div>
            <button type="submit" style="background:linear-gradient(135deg,#1a56db,#0ea5e9);color:white;border:none;padding:12px 30px;border-radius:10px;font-weight:700;cursor:pointer;font-size:0.9rem;">
                <i class="fas fa-paper-plane mr-1"></i> Submit Review
            </button>
        </form>
    </div>
    <?php endif; ?>
    
    <h3 class="rev-section-title"><i class="fas fa-comments" style="color:var(--purple);"></i> Employee Reviews (<?php echo count($reviews); ?>)</h3>
    
    <?php if (empty($reviews)): ?>
        <div style="text-align:center;padding:40px;color:var(--text-muted);">
            <i class="fas fa-comment-dots" style="font-size:2rem;display:block;margin-bottom:10px;"></i>
            No reviews yet. Be the first to review!
        </div>
    <?php else: ?>
        <?php foreach ($reviews as $rev): ?>
            <div class="rev-card">
                <div class="rev-card-header">
                    <div>
                        <span class="author"><?php echo $rev['is_anonymous'] ? 'Anonymous' : htmlspecialchars($rev['username'] ?? 'User'); ?></span>
                        <span class="date"> • <?php echo time_ago($rev['created_at']); ?></span>
                    </div>
                    <span class="rev-rating-badge"><i class="fas fa-star"></i> <?php echo $rev['rating']; ?>/5</span>
                </div>
                <?php if ($rev['title']): ?><h4><?php echo htmlspecialchars($rev['title']); ?></h4><?php endif; ?>
                <?php if ($rev['pros'] || $rev['cons']): ?>
                    <div class="pros-cons">
                        <?php if ($rev['pros']): ?>
                            <div class="rev-pros"><strong><i class="fas fa-plus-circle"></i> Pros</strong><?php echo htmlspecialchars($rev['pros']); ?></div>
                        <?php endif; ?>
                        <?php if ($rev['cons']): ?>
                            <div class="rev-cons"><strong><i class="fas fa-minus-circle"></i> Cons</strong><?php echo htmlspecialchars($rev['cons']); ?></div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
