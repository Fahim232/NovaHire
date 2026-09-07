<?php
/**
 * NovaHire — Premium Gating System
 * ---------------------------------------------------------------------------
 * Central library for feature access control, usage tracking, and
 * upgrade UI components. Used by all gated seeker pages.
 */

if (defined('NOVAHIRE_PREMIUM')) return;
define('NOVAHIRE_PREMIUM', true);

/* ── Feature Configuration ────────────────────────────────────────────────── */

function nh_feature_config() {
    return [
        'ai_resume_analyzer' => [
            'label'       => 'AI Resume Analyzer',
            'icon'        => 'fa-file-lines',
            'description' => 'Get AI-powered analysis of your resume with readiness scores and improvement tips.',
            'free_limit'  => 0,
            'type'        => 'hard_gate',
        ],
        'ai_mock_interview' => [
            'label'       => 'AI Mock Interview',
            'icon'        => 'fa-user-tie',
            'description' => 'Practice with realistic AI interviewer questions and get instant feedback.',
            'free_limit'  => 0,
            'type'        => 'hard_gate',
        ],
        'ai_cover_letter' => [
            'label'       => 'AI Cover Letter Generator',
            'icon'        => 'fa-envelope-open-text',
            'description' => 'Generate tailored, professional cover letters for any job in seconds.',
            'free_limit'  => 0,
            'type'        => 'hard_gate',
        ],
        'ai_grooming_coach' => [
            'label'       => 'AI Grooming Coach',
            'icon'        => 'fa-graduation-cap',
            'description' => 'Personalized study plans and skill development guidance from AI.',
            'free_limit'  => 0,
            'type'        => 'hard_gate',
        ],
        'ai_assistant' => [
            'label'       => 'AI Career Assistant',
            'icon'        => 'fa-robot',
            'description' => 'Chat with an AI career advisor 24/7 for instant guidance and tips.',
            'free_limit'  => 0,
            'type'        => 'hard_gate',
        ],
        'skill_gap' => [
            'label'       => 'Skill Gap Analyzer',
            'icon'        => 'fa-chart-column',
            'description' => 'Discover which skills you need to develop for your target career.',
            'free_limit'  => 0,
            'type'        => 'hard_gate',
        ],
        'career_path' => [
            'label'       => 'Career Path Explorer',
            'icon'        => 'fa-route',
            'description' => 'AI-mapped career progression paths tailored to your profile.',
            'free_limit'  => 0,
            'type'        => 'hard_gate',
        ],
        'recommendations' => [
            'label'       => 'AI Job Recommendations',
            'icon'        => 'fa-wand-magic-sparkles',
            'description' => 'Smart job matches ranked by AI based on your skills and experience.',
            'free_limit'  => 0,
            'type'        => 'hard_gate',
        ],
        'resume_builder' => [
            'label'       => 'Resume Builder',
            'icon'        => 'fa-pen-ruler',
            'description' => 'Build a professional resume with templates and AI suggestions.',
            'free_limit'  => 0,
            'type'        => 'hard_gate',
        ],
        'job_apply' => [
            'label'       => 'Job Applications',
            'icon'        => 'fa-paper-plane',
            'description' => 'Apply to jobs with your profile and resume.',
            'free_limit'  => 10,
            'type'        => 'soft_limit',
        ],
    ];
}

/* ── Access Check ─────────────────────────────────────────────────────────── */

/**
 * Check if a user can access a feature.
 * Returns: ['allowed' => bool, 'remaining' => int|false, 'limit' => int|false, 'is_pro' => bool]
 */
function nh_check_access($con, $user_id, $feature_key) {
    $config = nh_feature_config();
    if (!isset($config[$feature_key])) {
        return ['allowed' => true, 'remaining' => false, 'limit' => false, 'is_pro' => false];
    }

    $feat   = $config[$feature_key];
    $is_pro = is_user_pro($con, $user_id);

    if ($is_pro) {
        return ['allowed' => true, 'remaining' => false, 'limit' => false, 'is_pro' => true];
    }

    if ($feat['free_limit'] === 0) {
        return ['allowed' => false, 'remaining' => 0, 'limit' => 0, 'is_pro' => false];
    }

    $used = nh_get_usage_count($con, $user_id, $feature_key);
    $remaining = max(0, $feat['free_limit'] - $used);

    return [
        'allowed'   => $remaining > 0,
        'remaining' => $remaining,
        'limit'     => $feat['free_limit'],
        'is_pro'    => false,
    ];
}

/* ── Usage Tracking ───────────────────────────────────────────────────────── */

function nh_record_usage($con, $user_id, $feature_key) {
    $today = date('Y-m-d');
    $stmt = mysqli_prepare($con,
        "INSERT INTO feature_usage (user_id, feature_key, usage_date, usage_count)
         VALUES (?, ?, ?, 1)
         ON DUPLICATE KEY UPDATE usage_count = usage_count + 1");
    mysqli_stmt_bind_param($stmt, 'iss', $user_id, $feature_key, $today);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

function nh_get_usage_count($con, $user_id, $feature_key, $months = 1) {
    $since = date('Y-m-d', strtotime("-{$months} months"));
    $stmt = mysqli_prepare($con,
        "SELECT COALESCE(SUM(usage_count), 0) AS cnt
         FROM feature_usage
         WHERE user_id = ? AND feature_key = ? AND usage_date >= ?");
    mysqli_stmt_bind_param($stmt, 'iss', $user_id, $feature_key, $since);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return (int)($row['cnt'] ?? 0);
}

/* ── UI: Pro Gate Overlay ─────────────────────────────────────────────────── */

function nh_render_pro_gate($feature_key, $echo = true) {
    $config = nh_feature_config();
    $feat = $config[$feature_key] ?? ['label' => 'This feature', 'icon' => 'fa-lock', 'description' => 'Upgrade to access this feature.'];

    $pro_price = nh_pricing()['pro_monthly'] ?? 499;

    $html = '
    <div class="nh-pro-gate-overlay">
      <div class="nh-pro-gate-card">
        <div class="nh-pro-gate-icon"><i class="fas ' . htmlspecialchars($feat['icon']) . '"></i></div>
        <h2 class="nh-pro-gate-title">' . htmlspecialchars($feat['label']) . '</h2>
        <p class="nh-pro-gate-sub">This is a <strong>NovaHire Pro</strong> feature</p>
        <p class="nh-pro-gate-desc">' . htmlspecialchars($feat['description']) . '</p>
        <div class="nh-pro-gate-benefits">
          <div class="nh-pro-gate-benefit"><i class="fas fa-check"></i> Unlimited access to all AI tools</div>
          <div class="nh-pro-gate-benefit"><i class="fas fa-check"></i> Unlimited job applications</div>
          <div class="nh-pro-gate-benefit"><i class="fas fa-check"></i> Priority ranking for employers</div>
          <div class="nh-pro-gate-benefit"><i class="fas fa-check"></i> Free verified certificates</div>
          <div class="nh-pro-gate-benefit"><i class="fas fa-check"></i> Pro badge on your profile</div>
        </div>
        <div class="nh-pro-gate-price">
          <span class="nh-pro-gate-amount">' . nh_price($pro_price) . '</span>
          <span class="nh-pro-gate-period">/month</span>
        </div>
        <a href="' . BASE_URL . '/seeker/pro.php" class="nh-pro-gate-btn">
          <i class="fas fa-crown"></i> Upgrade to Pro
        </a>
        <a href="' . BASE_URL . '/seeker/seeker_dashboard.php" class="nh-pro-gate-back">Continue with Free &rarr;</a>
      </div>
    </div>';

    if ($echo) echo $html;
    return $html;
}

/* ── UI: Soft Limit Gate (inline) ─────────────────────────────────────────── */

function nh_render_soft_gate($feature_key, $remaining, $limit, $echo = true) {
    $config = nh_feature_config();
    $feat = $config[$feature_key] ?? ['label' => 'This feature', 'icon' => 'fa-lock'];
    $pro_price = nh_pricing()['pro_monthly'] ?? 499;
    $used = $limit - $remaining;
    $pct = $limit > 0 ? round(($used / $limit) * 100) : 0;
    $bar_class = $pct >= 80 ? 'danger' : ($pct >= 50 ? 'warning' : '');

    $html = '
    <div class="nh-soft-gate">
      <div class="nh-soft-gate-header">
        <div class="nh-soft-gate-info">
          <i class="fas ' . htmlspecialchars($feat['icon']) . '"></i>
          <span>You\'ve used <strong>' . $used . '/' . $limit . '</strong> ' . htmlspecialchars(strtolower($feat['label'])) . ' this month</span>
        </div>
        <a href="' . BASE_URL . '/seeker/pro.php" class="nh-soft-gate-upgrade">
          <i class="fas fa-crown"></i> Get Unlimited
        </a>
      </div>
      <div class="nh-soft-gate-bar">
        <div class="nh-soft-gate-fill ' . $bar_class . '" style="width:' . $pct . '%"></div>
      </div>
      <p class="nh-soft-gate-hint">Free plan: ' . $limit . ' per month. Pro gives you unlimited access for ' . nh_price($pro_price) . '/mo.</p>
    </div>';

    if ($echo) echo $html;
    return $html;
}

/* ── UI: Usage Bar (neutral, not a gate) ──────────────────────────────────── */

function nh_render_usage_bar($con, $user_id, $feature_key, $echo = true) {
    $config = nh_feature_config();
    $feat = $config[$feature_key] ?? null;
    if (!$feat || $feat['free_limit'] === 0) return '';

    $is_pro = is_user_pro($con, $user_id);
    if ($is_pro) return '';

    $used  = nh_get_usage_count($con, $user_id, $feature_key);
    $limit = $feat['free_limit'];
    $remaining = max(0, $limit - $used);
    $pct = $limit > 0 ? round(($used / $limit) * 100) : 0;
    $bar_class = $pct >= 80 ? 'danger' : ($pct >= 50 ? 'warning' : '');

    $html = '
    <div class="nh-usage-bar-wrap">
      <div class="nh-usage-bar-text">
        <span>' . $used . ' / ' . $limit . ' used this month</span>
        <span class="nh-usage-bar-remaining">' . $remaining . ' remaining</span>
      </div>
      <div class="nh-usage-bar">
        <div class="nh-usage-bar-fill ' . $bar_class . '" style="width:' . $pct . '%"></div>
      </div>
    </div>';

    if ($echo) echo $html;
    return $html;
}

/* ── UI: Upgrade Banner (for dashboard/nav) ───────────────────────────────── */

function nh_render_upgrade_banner($echo = true) {
    $pro_price = nh_pricing()['pro_monthly'] ?? 499;

    $html = '
    <div class="nh-upgrade-banner">
      <div class="nh-upgrade-banner-content">
        <div class="nh-upgrade-banner-icon"><i class="fas fa-crown"></i></div>
        <div class="nh-upgrade-banner-text">
          <strong>Unlock your full potential</strong>
          <span>Get Pro for unlimited AI tools, applications & more &mdash; ' . nh_price($pro_price) . '/mo</span>
        </div>
        <a href="' . BASE_URL . '/seeker/pro.php" class="nh-upgrade-banner-btn">Upgrade Now</a>
      </div>
    </div>';

    if ($echo) echo $html;
    return $html;
}

/* ── Helper: check if table exists ────────────────────────────────────────── */

function nh_feature_usage_table_exists($con) {
    $r = @mysqli_query($con, "SHOW TABLES LIKE 'feature_usage'");
    return $r && mysqli_num_rows($r) > 0;
}
