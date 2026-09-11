<?php
/**
 * NovaHire — Placement Engine
 * ---------------------------------------------------------------------------
 * Turns the existing AI match score into a real placement funnel:
 *   • Personalised job recommendations for seekers
 *   • Application pipeline (applied → … → hired)
 *   • Ranked talent pool for companies
 *   • Placement records → basis for the placement success fee
 */

if (defined('NOVAHIRE_PLACEMENT')) return;
define('NOVAHIRE_PLACEMENT', true);

// The rule-based matching engine (ai_match_profile_job / ai_rank_jobs) is loaded
// lazily — only when a placement function that needs it actually runs — so we
// don't pull the whole AI engine (+ its DB read) into every page via bootstrap.
function nh_load_matching() {
    if (!function_exists('ai_rank_jobs') && file_exists(__DIR__ . '/../ai/matching.php')) {
        require_once __DIR__ . '/../ai/matching.php';
    }
    return function_exists('ai_rank_jobs');
}

/* ── Pipeline definition ───────────────────────────────────────────────────── */
function nh_pipeline_stages() {
    return ['applied', 'reviewed', 'shortlisted', 'interview', 'offered', 'hired', 'rejected'];
}

function nh_stage_label($stage) {
    $labels = [
        'applied' => 'Applied', 'reviewed' => 'Reviewed', 'shortlisted' => 'Shortlisted',
        'interview' => 'Interview', 'offered' => 'Offer', 'hired' => 'Hired', 'rejected' => 'Not selected',
    ];
    return $labels[$stage] ?? ucfirst($stage);
}

function nh_stage_color($stage) {
    $colors = [
        'applied' => '#3b82f6', 'reviewed' => '#0ea5e9', 'shortlisted' => '#06b6d4',
        'interview' => '#d97706', 'offered' => '#059669', 'hired' => '#059669', 'rejected' => '#dc2626',
    ];
    return $colors[$stage] ?? '#64748b';
}

/* ── Recommendations ───────────────────────────────────────────────────────── */
function nh_user_applied_job_ids($con, $user_id) {
    $ids = [];
    $stmt = mysqli_prepare($con, "SELECT job_id FROM job_applications WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($r = mysqli_fetch_assoc($res)) $ids[] = (int)$r['job_id'];
    mysqli_stmt_close($stmt);
    return $ids;
}

function nh_get_active_jobs($con) {
    $jobs = [];
    $sql = "SELECT j.*, c.company_name, c.logo AS company_logo
            FROM company_jobs j
            JOIN companies c ON c.id = j.company_id
            WHERE j.status = 'active'
            ORDER BY j.is_featured DESC, j.posted_date DESC";
    $res = @mysqli_query($con, $sql);
    if ($res) while ($r = mysqli_fetch_assoc($res)) $jobs[] = $r;
    return $jobs;
}

/**
 * Top job recommendations for a user, each with the full 'ai' match payload.
 * Already-applied jobs are flagged but still ranked.
 */
function nh_get_recommendations($con, $user, $limit = 6) {
    if (!nh_load_matching()) return [];
    $jobs = nh_get_active_jobs($con);
    if (empty($jobs)) return [];
    $applied = nh_user_applied_job_ids($con, $user['id']);
    $ranked = ai_rank_jobs($user, $jobs);
    foreach ($ranked as &$j) $j['already_applied'] = in_array((int)$j['id'], $applied);
    unset($j);
    return array_slice($ranked, 0, $limit);
}

/* ── Pipeline management ───────────────────────────────────────────────────── */
function nh_set_pipeline_stage($con, $application_id, $stage, $notify = true) {
    if (!in_array($stage, nh_pipeline_stages())) return false;

    // fetch application context for notifications / placement
    $app = nh_get_application($con, $application_id);
    if (!$app) return false;

    $stmt = mysqli_prepare($con, "UPDATE job_applications SET pipeline_stage = ?, stage_updated_at = NOW() WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "si", $stage, $application_id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    // keep the original application_status roughly in sync (non-breaking)
    $legacy = in_array($stage, ['reviewed','shortlisted','rejected']) ? $stage
            : ($stage === 'hired' || $stage === 'offered' ? 'shortlisted' : 'pending');
    @mysqli_query($con, "UPDATE job_applications SET application_status = '" . mysqli_real_escape_string($con, $legacy) . "' WHERE id = " . (int)$application_id);

    if ($ok && $stage === 'hired') {
        nh_record_placement($con, $application_id);
    }

    if ($ok && $notify && function_exists('create_notification')) {
        $title = 'Application update: ' . nh_stage_label($stage);
        $msg = 'Your application for <strong>' . htmlspecialchars($app['job_title']) . '</strong> at <strong>' . htmlspecialchars($app['company_name']) . '</strong> moved to <strong>' . nh_stage_label($stage) . '</strong>.';
        create_notification($con, 'user', $app['user_id'], 'company', $app['company_id'], $title, $msg, 'application_status', 'job_applications', $application_id);
    }
    return $ok;
}

function nh_get_application($con, $application_id) {
    $sql = "SELECT a.*, j.job_title, j.salary_range, c.company_name
            FROM job_applications a
            JOIN company_jobs j ON j.id = a.job_id
            JOIN companies c ON c.id = a.company_id
            WHERE a.id = ? LIMIT 1";
    $stmt = mysqli_prepare($con, $sql);
    mysqli_stmt_bind_param($stmt, "i", $application_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

/* ── Placements (success fee) ──────────────────────────────────────────────── */
function nh_parse_salary($salary_range) {
    if (!$salary_range) return null;
    if (preg_match('/([\d,]{3,})/', $salary_range, $m)) {
        $n = (float)str_replace(',', '', $m[1]);
        return $n > 0 ? $n : null;
    }
    return null;
}

function nh_record_placement($con, $application_id) {
    $app = nh_get_application($con, $application_id);
    if (!$app) return false;

    // already recorded?
    $chk = mysqli_prepare($con, "SELECT id FROM placements WHERE application_id = ? LIMIT 1");
    mysqli_stmt_bind_param($chk, "i", $application_id);
    mysqli_stmt_execute($chk);
    $exists = mysqli_fetch_assoc(mysqli_stmt_get_result($chk));
    mysqli_stmt_close($chk);
    if ($exists) return true;

    $pricing = nh_pricing();
    $salary  = nh_parse_salary($app['salary_range']);
    // Flat success fee keeps the demo predictable; salary stored for reporting.
    $fee = $pricing['placement_fee_flat'];

    $stmt = mysqli_prepare($con, "INSERT INTO placements (application_id, job_id, user_id, company_id, salary_amount, placement_fee, fee_status, hired_at, created_at) VALUES (?, ?, ?, ?, ?, ?, 'pending', CURDATE(), NOW())");
    mysqli_stmt_bind_param($stmt, "iiiidd", $application_id, $app['job_id'], $app['user_id'], $app['company_id'], $salary, $fee);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($ok && function_exists('create_notification')) {
        create_notification($con, 'user', $app['user_id'], 'company', $app['company_id'],
            '🎉 You got hired!', 'Congratulations! You were hired for <strong>' . htmlspecialchars($app['job_title']) . '</strong> at <strong>' . htmlspecialchars($app['company_name']) . '</strong>.', 'application_status', 'placements', $application_id);
    }
    return $ok;
}

/* ── Company talent pool (ranked applicants) ───────────────────────────────── */
function nh_company_talent_pool($con, $company_id) {
    $sql = "SELECT a.id AS application_id, a.pipeline_stage, a.quiz_score, a.applied_date,
                   u.id AS user_id, u.username, u.email, u.user_skills, u.user_degree,
                   u.experience, u.about_me, u.profile,
                   j.id AS job_id, j.job_title, j.job_category, j.skills_required,
                   j.requirements, j.responsibilities, j.job_description, j.experience_required
            FROM job_applications a
            JOIN user_info u ON u.id = a.user_id
            JOIN company_jobs j ON j.id = a.job_id
            WHERE a.company_id = ?
            ORDER BY a.applied_date DESC";
    $stmt = mysqli_prepare($con, $sql);
    mysqli_stmt_bind_param($stmt, "i", $company_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $rows = [];
    while ($r = mysqli_fetch_assoc($res)) {
        if (nh_load_matching()) {
            $ai = ai_match_profile_job($r, $r); // row carries both user_* and job_* fields
            $r['match_score'] = $ai['score'];
        } else {
            $r['match_score'] = 0;
        }
        $r['is_pro'] = function_exists('is_user_pro') ? is_user_pro($con, $r['user_id']) : false;
        $r['grooming_passed'] = nh_user_passed_count($con, $r['user_id']);
        $rows[] = $r;
    }
    mysqli_stmt_close($stmt);

    // Rank: Pro first, then match score, then quiz score
    usort($rows, function ($a, $b) {
        if ($a['is_pro'] !== $b['is_pro']) return $b['is_pro'] <=> $a['is_pro'];
        if ($a['match_score'] !== $b['match_score']) return $b['match_score'] <=> $a['match_score'];
        return (int)$b['quiz_score'] <=> (int)$a['quiz_score'];
    });
    return $rows;
}

function nh_user_passed_count($con, $user_id) {
    $stmt = mysqli_prepare($con, "SELECT COUNT(*) c FROM user_quiz_status WHERE user_id = ? AND status = 'passed'");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return (int)($row['c'] ?? 0);
}

/* ── Stats (for company + admin dashboards) ────────────────────────────────── */
function nh_placement_stats($con, $company_id = null) {
    $where = $company_id ? (" WHERE company_id = " . (int)$company_id) : "";
    $stats = ['total' => 0, 'fee_pending' => 0, 'fee_paid' => 0, 'revenue' => 0.0];
    $r = @mysqli_query($con, "SELECT COUNT(*) total,
                SUM(fee_status='pending') fee_pending,
                SUM(fee_status='paid') fee_paid,
                COALESCE(SUM(CASE WHEN fee_status='paid' THEN placement_fee ELSE 0 END),0) revenue
             FROM placements" . $where);
    if ($r && ($row = mysqli_fetch_assoc($r))) {
        $stats = [
            'total' => (int)$row['total'],
            'fee_pending' => (int)$row['fee_pending'],
            'fee_paid' => (int)$row['fee_paid'],
            'revenue' => (float)$row['revenue'],
        ];
    }
    return $stats;
}
?>
