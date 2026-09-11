<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_seeker_login();

$user_id = $_SESSION['id'];
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_alert'])) {
        $keyword = trim($_POST['keyword'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $job_type = trim($_POST['job_type'] ?? '');
        $min_salary = intval($_POST['min_salary'] ?? 0);
        $frequency = trim($_POST['frequency'] ?? 'daily');
        
        if (empty($keyword) && empty($category)) {
            $error = 'Please enter a keyword or select a category';
        } else {
            $stmt = mysqli_prepare($con, "INSERT INTO job_alerts (user_id, keyword, location, category, job_type, min_salary, frequency, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())");
            mysqli_stmt_bind_param($stmt, "issssis", $user_id, $keyword, $location, $category, $job_type, $min_salary, $frequency);
            if (mysqli_stmt_execute($stmt)) {
                $success = 'Job alert created! You will receive notifications for matching jobs.';
                create_job_alert_notifications($con, $user_id);
            } else {
                $error = 'Failed to create alert. Please try again.';
            }
            mysqli_stmt_close($stmt);
        }
    } elseif (isset($_POST['delete_alert'])) {
        $alert_id = intval($_POST['alert_id'] ?? 0);
        $stmt = mysqli_prepare($con, "DELETE FROM job_alerts WHERE id = ? AND user_id = ?");
        mysqli_stmt_bind_param($stmt, "ii", $alert_id, $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $success = 'Alert deleted successfully.';
    } elseif (isset($_POST['toggle_alert'])) {
        $alert_id = intval($_POST['alert_id'] ?? 0);
        $stmt = mysqli_prepare($con, "UPDATE job_alerts SET is_active = NOT is_active WHERE id = ? AND user_id = ?");
        mysqli_stmt_bind_param($stmt, "ii", $alert_id, $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

$alert_stmt = mysqli_prepare($con, "SELECT * FROM job_alerts WHERE user_id = ? ORDER BY created_at DESC");
mysqli_stmt_bind_param($alert_stmt, "i", $user_id);
mysqli_stmt_execute($alert_stmt);
$alerts = mysqli_fetch_all(mysqli_stmt_get_result($alert_stmt), MYSQLI_ASSOC);
mysqli_stmt_close($alert_stmt);

$matching_jobs = [];
foreach (array_slice($alerts, 0, 3) as $alert) {
    $where = "WHERE cj.status = 'active'";
    $jparams = [];
    $jtypes = '';
    if ($alert['keyword']) {
        $where .= " AND (cj.job_title LIKE ? OR cj.job_description LIKE ?)";
        $jparams[] = "%{$alert['keyword']}%";
        $jparams[] = "%{$alert['keyword']}%";
        $jtypes .= 'ss';
    }
    if ($alert['category']) {
        $where .= " AND cj.job_category = ?";
        $jparams[] = $alert['category'];
        $jtypes .= 's';
    }
    if ($alert['job_type']) {
        $where .= " AND cj.employment_type = ?";
        $jparams[] = $alert['job_type'];
        $jtypes .= 's';
    }
    $j_sql = "SELECT cj.id as job_id, cj.job_title, cj.job_category, cj.employment_type, cj.location, cj.salary_min, cj.salary_max, cj.posted_date, c.company_name 
              FROM company_jobs cj LEFT JOIN companies c ON cj.company_id = c.id $where ORDER BY cj.posted_date DESC LIMIT 3";
    $j_stmt = mysqli_prepare($con, $j_sql);
    if ($jparams) mysqli_stmt_bind_param($j_stmt, $jtypes, ...$jparams);
    mysqli_stmt_execute($j_stmt);
    $jobs = mysqli_fetch_all(mysqli_stmt_get_result($j_stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($j_stmt);
    $matching_jobs[$alert['id']] = $jobs;
}

require_once __DIR__ . '/../includes/header.php';

$freq_icons = ['instant' => 'fa-bolt', 'daily' => 'fa-calendar-day', 'weekly' => 'fa-calendar-week'];
$freq_colors = ['instant' => '#d97706', 'daily' => '#3b82f6', 'weekly' => '#06b6d4'];
$cat_icons = [
    'IT' => 'fa-laptop-code', 'Marketing' => 'fa-bullhorn', 'Design' => 'fa-palette',
    'Finance' => 'fa-chart-line', 'HR' => 'fa-users', 'Sales' => 'fa-handshake',
    'Engineering' => 'fa-gears', 'Education' => 'fa-graduation-cap', 'Healthcare' => 'fa-heart-pulse',
];
?>

<style>
/* ═══ RESET ═══ */
.ja-page *, .ja-page *::before, .ja-page *::after { box-sizing: border-box; }
.ja-page {
    --ja-bg: #f1f5f9;
    --ja-card: #ffffff;
    --ja-border: #e2e8f0;
    --ja-text: #0f172a;
    --ja-text-2: #334155;
    --ja-text-3: #64748b;
    --ja-primary: #1a56db;
    --ja-primary-light: #eef2ff;
    --ja-primary-dark: #3730a3;
    --ja-success: #059669;
    --ja-success-bg: #ecfdf5;
    --ja-danger: #dc2626;
    --ja-danger-bg: #fef2f2;
    --ja-warning: #d97706;
    --ja-warning-bg: #fffbeb;
    --ja-info: #3b82f6;
    --ja-info-bg: #eff6ff;
    --ja-radius: 16px;
    font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    color: var(--ja-text);
    max-width: 1100px;
    margin: 0 auto;
    padding: 28px 24px;
    background: var(--ja-bg);
    border-radius: 20px;
}

/* ═══ HERO ═══ */
.ja-hero {
    background: linear-gradient(135deg, #1a56db 0%, #0ea5e9 50%, #38bdf8 100%);
    color: #fff;
    border-radius: var(--ja-radius);
    padding: 36px 40px;
    margin-bottom: 28px;
    position: relative;
    overflow: hidden;
}
.ja-hero::after {
    content: '';
    position: absolute;
    top: -60%;
    right: -8%;
    width: 350px;
    height: 350px;
    background: radial-gradient(circle, rgba(255,255,255,0.12) 0%, transparent 70%);
    border-radius: 50%;
}
.ja-hero h1 { font-size: 1.8rem; font-weight: 800; margin-bottom: 6px; position: relative; z-index: 1; }
.ja-hero h1 i { margin-right: 10px; opacity: 0.85; }
.ja-hero p { opacity: 0.9; font-size: 0.95rem; position: relative; z-index: 1; }

/* ═══ STATS ROW ═══ */
.ja-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 14px;
    margin-bottom: 28px;
}
.ja-stat {
    background: var(--ja-card);
    border: 1px solid var(--ja-border);
    border-radius: 14px;
    padding: 18px 20px;
    display: flex;
    align-items: center;
    gap: 14px;
    transition: all 0.2s;
}
.ja-stat:hover { transform: translateY(-2px); box-shadow: 0 4px 16px rgba(0,0,0,0.06); }
.ja-stat-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    flex-shrink: 0;
}
.ja-stat-num { font-size: 1.5rem; font-weight: 800; color: var(--ja-text); line-height: 1; }
.ja-stat-label { font-size: 0.75rem; color: var(--ja-text-3); font-weight: 500; margin-top: 2px; }

/* ═══ ALERTS GRID ═══ */
.ja-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; align-items: start; }

/* ═══ CREATE CARD ═══ */
.ja-create {
    background: var(--ja-card);
    border: 1px solid var(--ja-border);
    border-radius: var(--ja-radius);
    overflow: hidden;
}
.ja-create-head {
    padding: 20px 24px;
    border-bottom: 1px solid var(--ja-border);
    font-weight: 700;
    font-size: 0.95rem;
    display: flex;
    align-items: center;
    gap: 10px;
    color: var(--ja-text);
}
.ja-create-head i { color: var(--ja-primary); font-size: 1rem; }
.ja-create-body { padding: 24px; }

/* ═══ FORM ═══ */
.ja-fg { margin-bottom: 16px; }
.ja-fg label {
    display: block;
    font-weight: 600;
    font-size: 0.78rem;
    margin-bottom: 5px;
    color: var(--ja-text-2);
    text-transform: uppercase;
    letter-spacing: 0.3px;
}
.ja-fg input,
.ja-fg select {
    width: 100%;
    padding: 11px 14px;
    border: 1.5px solid var(--ja-border);
    border-radius: 10px;
    font-family: inherit;
    font-size: 0.88rem;
    color: var(--ja-text);
    background: #f8fafc;
    transition: border 0.2s, box-shadow 0.2s;
    appearance: none;
    -webkit-appearance: none;
}
.ja-fg select {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%2364748b' viewBox='0 0 16 16'%3E%3Cpath d='M8 11L3 6h10z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 12px center;
    padding-right: 32px;
}
.ja-fg input:focus,
.ja-fg select:focus {
    outline: none;
    border-color: var(--ja-primary);
    box-shadow: 0 0 0 3px rgba(26,86,219,0.1);
    background: #fff;
}
.ja-fg input::placeholder { color: #94a3b8; }

.ja-submit {
    width: 100%;
    padding: 13px;
    border: none;
    border-radius: 10px;
    font-family: inherit;
    font-weight: 700;
    font-size: 0.9rem;
    cursor: pointer;
    background: linear-gradient(135deg, #1a56db, #0ea5e9);
    color: #fff;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}
.ja-submit:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(26,86,219,0.3); }
.ja-submit:active { transform: scale(0.98); }

/* ═══ ALERTS LIST ═══ */
.ja-list-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16px;
}
.ja-list-title {
    font-size: 1rem;
    font-weight: 700;
    color: var(--ja-text);
    display: flex;
    align-items: center;
    gap: 8px;
}
.ja-list-title i { color: var(--ja-primary); }
.ja-list-count {
    background: var(--ja-primary-light);
    color: var(--ja-primary);
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 700;
}

/* ═══ ALERT CARD ═══ */
.ja-card {
    background: var(--ja-card);
    border: 1px solid var(--ja-border);
    border-radius: var(--ja-radius);
    padding: 0;
    margin-bottom: 14px;
    overflow: hidden;
    transition: all 0.2s;
}
.ja-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.05); }
.ja-card.off { opacity: 0.55; }
.ja-card-top {
    padding: 18px 20px;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
}
.ja-card-info { flex: 1; }
.ja-card-name {
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--ja-text);
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 4px;
}
.ja-card-name .dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
}
.ja-card-name .dot.on { background: #059669; }
.ja-card-name .dot.off { background: #cbd5e1; }
.ja-card-loc {
    font-size: 0.8rem;
    color: var(--ja-text-3);
    display: flex;
    align-items: center;
    gap: 5px;
}
.ja-card-acts { display: flex; gap: 6px; flex-shrink: 0; }
.ja-card-acts button,
.ja-card-acts a {
    padding: 7px 14px;
    border-radius: 8px;
    font-size: 0.78rem;
    font-weight: 600;
    cursor: pointer;
    border: 1px solid var(--ja-border);
    background: #fff;
    color: var(--ja-text-2);
    text-decoration: none;
    transition: all 0.15s;
    font-family: inherit;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}
.ja-card-acts button:hover,
.ja-card-acts a:hover { border-color: var(--ja-primary); color: var(--ja-primary); }
.ja-card-acts .del { color: var(--ja-danger); border-color: #fecaca; }
.ja-card-acts .del:hover { background: var(--ja-danger-bg); border-color: var(--ja-danger); }

/* Tags */
.ja-tags {
    padding: 0 20px 14px;
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}
.ja-tag {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.72rem;
    font-weight: 600;
}
.ja-tag.cat { background: var(--ja-primary-light); color: var(--ja-primary); }
.ja-tag.type { background: #f0fdf4; color: #15803d; }
.ja-tag.salary { background: var(--ja-warning-bg); color: #b45309; }
.ja-tag.freq { background: var(--ja-info-bg); color: var(--ja-info); }

/* Matches */
.ja-matches {
    border-top: 1px solid var(--ja-border);
    padding: 14px 20px;
    background: #fafbfc;
}
.ja-matches-label {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--ja-text-3);
    margin-bottom: 8px;
}
.ja-match {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 9px 14px;
    border-radius: 8px;
    background: #fff;
    border: 1px solid var(--ja-border);
    margin-bottom: 6px;
    transition: all 0.15s;
}
.ja-match:last-child { margin-bottom: 0; }
.ja-match:hover { border-color: var(--ja-primary); }
.ja-match-title { font-size: 0.85rem; font-weight: 600; color: var(--ja-text); }
.ja-match-meta { font-size: 0.75rem; color: var(--ja-text-3); }
.ja-match-link {
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--ja-primary);
    text-decoration: none;
    white-space: nowrap;
}
.ja-match-link:hover { text-decoration: underline; }

/* ═══ EMPTY STATE ═══ */
.ja-empty {
    text-align: center;
    padding: 48px 24px;
    background: var(--ja-card);
    border: 1px solid var(--ja-border);
    border-radius: var(--ja-radius);
}
.ja-empty-icon {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    background: var(--ja-primary-light);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 16px;
    font-size: 1.5rem;
    color: var(--ja-primary);
}
.ja-empty h3 { font-size: 1.05rem; font-weight: 700; color: var(--ja-text); margin-bottom: 6px; }
.ja-empty p { font-size: 0.88rem; color: var(--ja-text-3); }

/* ═══ ALERTS ═══ */
.ja-alert {
    padding: 14px 20px;
    border-radius: 12px;
    margin-bottom: 20px;
    font-weight: 600;
    font-size: 0.88rem;
    display: flex;
    align-items: center;
    gap: 8px;
}
.ja-ok { background: var(--ja-success-bg); color: #065f46; border: 1px solid #a7f3d0; }
.ja-err { background: var(--ja-danger-bg); color: #991b1b; border: 1px solid #fecaca; }

/* ═══ RESPONSIVE ═══ */
@media (max-width: 900px) {
    .ja-grid { grid-template-columns: 1fr; }
    .ja-stats { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 600px) {
    .ja-page { padding: 16px 12px; }
    .ja-hero { padding: 24px 20px; }
    .ja-hero h1 { font-size: 1.4rem; }
    .ja-stats { grid-template-columns: 1fr 1fr; gap: 10px; }
    .ja-stat { padding: 14px; gap: 10px; }
    .ja-stat-icon { width: 38px; height: 38px; font-size: 0.95rem; }
    .ja-stat-num { font-size: 1.2rem; }
    .ja-card-top { flex-direction: column; }
    .ja-card-acts { width: 100%; }
    .ja-card-acts button, .ja-card-acts a { flex: 1; justify-content: center; }
}
</style>

<div class="ja-page">
    <!-- Hero -->
    <div class="ja-hero">
        <h1><i class="fas fa-bell"></i>Job Alerts</h1>
        <p>Create alerts for your dream jobs and get notified instantly when new positions match your preferences.</p>
    </div>

    <?php if ($success): ?>
        <div class="ja-alert ja-ok"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="ja-alert ja-err"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
    <?php endif; ?>

    <!-- Stats -->
    <?php
    $total = count($alerts);
    $active = count(array_filter($alerts, fn($a) => $a['is_active']));
    $inactive = $total - $active;
    $total_matches = 0;
    foreach ($matching_jobs as $mj) $total_matches += count($mj);
    ?>
    <div class="ja-stats">
        <div class="ja-stat">
            <div class="ja-stat-icon" style="background:#eef2ff;color:#1a56db;"><i class="fas fa-bell"></i></div>
            <div><div class="ja-stat-num"><?php echo $total; ?></div><div class="ja-stat-label">Total Alerts</div></div>
        </div>
        <div class="ja-stat">
            <div class="ja-stat-icon" style="background:#ecfdf5;color:#059669;"><i class="fas fa-check-circle"></i></div>
            <div><div class="ja-stat-num"><?php echo $active; ?></div><div class="ja-stat-label">Active</div></div>
        </div>
        <div class="ja-stat">
            <div class="ja-stat-icon" style="background:#f1f5f9;color:#64748b;"><i class="fas fa-pause-circle"></i></div>
            <div><div class="ja-stat-num"><?php echo $inactive; ?></div><div class="ja-stat-label">Paused</div></div>
        </div>
        <div class="ja-stat">
            <div class="ja-stat-icon" style="background:#fffbeb;color:#b45309;"><i class="fas fa-briefcase"></i></div>
            <div><div class="ja-stat-num"><?php echo $total_matches; ?></div><div class="ja-stat-label">Job Matches</div></div>
        </div>
    </div>

    <div class="ja-grid">
        <!-- LEFT: Create Form -->
        <div class="ja-create">
            <div class="ja-create-head"><i class="fas fa-plus-circle"></i> Create New Alert</div>
            <div class="ja-create-body">
                <form method="POST">
                    <div class="ja-fg">
                        <label>Keywords</label>
                        <input type="text" name="keyword" placeholder="e.g. PHP Developer, React, Marketing">
                    </div>
                    <div class="ja-fg">
                        <label>Location</label>
                        <input type="text" name="location" placeholder="e.g. Dhaka, Remote, Chattogram">
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div class="ja-fg">
                            <label>Category</label>
                            <select name="category">
                                <option value="">All Categories</option>
                                <option value="IT">IT & Software</option>
                                <option value="Marketing">Marketing</option>
                                <option value="Design">Design</option>
                                <option value="Finance">Finance</option>
                                <option value="HR">Human Resources</option>
                                <option value="Sales">Sales</option>
                                <option value="Engineering">Engineering</option>
                                <option value="Education">Education</option>
                                <option value="Healthcare">Healthcare</option>
                            </select>
                        </div>
                        <div class="ja-fg">
                            <label>Job Type</label>
                            <select name="job_type">
                                <option value="">All Types</option>
                                <option value="Full-Time">Full-Time</option>
                                <option value="Part-Time">Part-Time</option>
                                <option value="Contract">Contract</option>
                                <option value="Internship">Internship</option>
                            </select>
                        </div>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div class="ja-fg">
                            <label>Minimum Salary (&#2547;)</label>
                            <input type="number" name="min_salary" placeholder="e.g. 30000" min="0">
                        </div>
                        <div class="ja-fg">
                            <label>Frequency</label>
                            <select name="frequency">
                                <option value="instant">Instant Alert</option>
                                <option value="daily" selected>Daily Digest</option>
                                <option value="weekly">Weekly Digest</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" name="create_alert" class="ja-submit">
                        <i class="fas fa-bell"></i> Create Alert
                    </button>
                </form>
            </div>
        </div>

        <!-- RIGHT: Alerts List -->
        <div>
            <div class="ja-list-head">
                <div class="ja-list-title"><i class="fas fa-list-check"></i> Your Alerts</div>
                <span class="ja-list-count"><?php echo $total; ?> total</span>
            </div>

            <?php if (empty($alerts)): ?>
                <div class="ja-empty">
                    <div class="ja-empty-icon"><i class="fas fa-bell-slash"></i></div>
                    <h3>No alerts yet</h3>
                    <p>Create your first job alert to start receiving notifications.</p>
                </div>
            <?php else: ?>
                <?php foreach ($alerts as $alert): ?>
                    <div class="ja-card <?php echo $alert['is_active'] ? '' : 'off'; ?>">
                        <div class="ja-card-top">
                            <div class="ja-card-info">
                                <div class="ja-card-name">
                                    <span class="dot <?php echo $alert['is_active'] ? 'on' : 'off'; ?>"></span>
                                    <?php echo htmlspecialchars($alert['keyword'] ?: 'All Jobs'); ?>
                                </div>
                                <?php if ($alert['location']): ?>
                                    <div class="ja-card-loc"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($alert['location']); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="ja-card-acts">
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="alert_id" value="<?php echo $alert['id']; ?>">
                                    <button type="submit" name="toggle_alert">
                                        <i class="fas <?php echo $alert['is_active'] ? 'fa-pause' : 'fa-play'; ?>"></i>
                                        <?php echo $alert['is_active'] ? 'Pause' : 'Resume'; ?>
                                    </button>
                                </form>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="alert_id" value="<?php echo $alert['id']; ?>">
                                    <button type="submit" name="delete_alert" class="del" onclick="return confirm('Delete this alert?')">
                                        <i class="fas fa-trash-alt"></i> Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                        <div class="ja-tags">
                            <?php if ($alert['category']): ?>
                                <span class="ja-tag cat"><i class="fas <?php echo $cat_icons[$alert['category']] ?? 'fa-tag'; ?>"></i> <?php echo htmlspecialchars($alert['category']); ?></span>
                            <?php endif; ?>
                            <?php if ($alert['job_type']): ?>
                                <span class="ja-tag type"><i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($alert['job_type']); ?></span>
                            <?php endif; ?>
                            <?php if ($alert['min_salary'] > 0): ?>
                                <span class="ja-tag salary"><i class="fas fa-coins"></i> &#2547;<?php echo number_format($alert['min_salary']); ?>+</span>
                            <?php endif; ?>
                            <span class="ja-tag freq"><i class="fas <?php echo $freq_icons[$alert['frequency']] ?? 'fa-clock'; ?>"></i> <?php echo ucfirst($alert['frequency']); ?></span>
                        </div>
                        <?php if (!empty($matching_jobs[$alert['id']])): ?>
                            <div class="ja-matches">
                                <div class="ja-matches-label"><i class="fas fa-sparkles" style="margin-right:4px;"></i> Recent Matches</div>
                                <?php foreach ($matching_jobs[$alert['id']] as $mj): ?>
                                    <div class="ja-match">
                                        <div>
                                            <div class="ja-match-title"><?php echo htmlspecialchars($mj['job_title']); ?></div>
                                            <div class="ja-match-meta"><?php echo htmlspecialchars($mj['company_name'] ?? 'Company'); ?> &bull; <?php echo htmlspecialchars($mj['location'] ?? ''); ?></div>
                                        </div>
                                        <a href="browse_jobs.php?search=<?php echo urlencode($mj['job_title']); ?>" class="ja-match-link">View <i class="fas fa-arrow-right" style="font-size:0.65rem;"></i></a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
