<?php
/**
 * NovaHire — Job Alert System
 * Send email alerts for new jobs matching user preferences
 */

if (defined('NOVAHIRE_ALERTS')) return;
define('NOVAHIRE_ALERTS', true);

/* ── Subscribe to Job Alerts ───────────────────────────────────────────── */
function create_job_alert($con, $user_id, $alert_name, $keywords, $category, $location, $employment_type, $salary_min = 0, $frequency = 'daily') {
    $stmt = mysqli_prepare($con, "INSERT INTO job_alerts (user_id, alert_name, keywords, category, location, employment_type, salary_min, frequency) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "isssssis", $user_id, $alert_name, $keywords, $category, $location, $employment_type, $salary_min, $frequency);
    $result = mysqli_stmt_execute($stmt);
    $alert_id = mysqli_insert_id($con);
    mysqli_stmt_close($stmt);
    return $result ? $alert_id : false;
}

function get_user_alerts($con, $user_id) {
    // Check if table exists
    $check = @mysqli_query($con, "SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'job_alerts'");
    if (!$check || mysqli_num_rows($check) == 0) return [];
    
    $stmt = mysqli_prepare($con, "SELECT * FROM job_alerts WHERE user_id = ? ORDER BY created_at DESC");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $alerts = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $alerts[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $alerts;
}

function delete_job_alert($con, $alert_id, $user_id) {
    $stmt = mysqli_prepare($con, "DELETE FROM job_alerts WHERE id = ? AND user_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $alert_id, $user_id);
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $result;
}

function toggle_job_alert($con, $alert_id, $user_id, $is_active) {
    $stmt = mysqli_prepare($con, "UPDATE job_alerts SET is_active = ? WHERE id = ? AND user_id = ?");
    mysqli_stmt_bind_param($stmt, "iii", $is_active, $alert_id, $user_id);
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $result;
}

/* ── Match Job Against Alerts ───────────────────────────────────────────── */
function match_job_to_alerts($con, $job) {
    // Check if table exists
    $check = @mysqli_query($con, "SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'job_alerts'");
    if (!$check || mysqli_num_rows($check) == 0) return [];
    
    $conditions = ["ja.is_active = 1"];
    $params = [];
    $types = '';
    
    if (!empty($job['job_category'])) {
        $conditions[] = "(ja.category = ? OR ja.category = '')";
        $params[] = $job['job_category'];
        $types .= 's';
    }
    
    if (!empty($job['location'])) {
        $conditions[] = "(ja.location = ? OR ja.location = '')";
        $params[] = $job['location'];
        $types .= 's';
    }
    
    if (!empty($job['employment_type'])) {
        $conditions[] = "(ja.employment_type = ? OR ja.employment_type = '')";
        $params[] = $job['employment_type'];
        $types .= 's';
    }
    
    if (!empty($job['salary_min']) && $job['salary_min'] > 0) {
        $conditions[] = "(ja.salary_min <= ? OR ja.salary_min = 0)";
        $params[] = $job['salary_min'];
        $types .= 'i';
    }
    
    if (!empty($job['skills_required'])) {
        $skills = explode(',', $job['skills_required']);
        $skill_conditions = [];
        foreach ($skills as $skill) {
            $skill = trim($skill);
            if ($skill) {
                $skill_conditions[] = "ja.keywords LIKE ?";
                $params[] = '%' . $skill . '%';
                $types .= 's';
            }
        }
        if (!empty($skill_conditions)) {
            $conditions[] = "(" . implode(' OR ', $skill_conditions) . ")";
        }
    }
    
    $where = implode(' AND ', $conditions);
    
    $sql = "SELECT ja.*, ui.username, ui.email 
            FROM job_alerts ja 
            INNER JOIN user_info ui ON ja.user_id = ui.id 
            WHERE $where";
    
    $stmt = mysqli_prepare($con, $sql);
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $matched_users = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $matched_users[] = $row;
    }
    mysqli_stmt_close($stmt);
    
    return $matched_users;
}

/* ── Send Job Alerts ────────────────────────────────────────────────────── */
function send_job_alerts_to_subscribers($con, $job_id) {
    // Get job details
    $stmt = mysqli_prepare($con, "SELECT cj.*, c.company_name FROM company_jobs cj LEFT JOIN companies c ON cj.company_id = c.id WHERE cj.job_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $job_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $job = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if (!$job) return;
    
    // Find matching alerts
    $matched_users = match_job_to_alerts($con, $job);
    
    // Send emails
    $sent_count = 0;
    foreach ($matched_users as $user) {
        if (send_job_alert($user['email'], $user['username'], $job)) {
            $sent_count++;
            
            // Update last_sent_at
            $alert_stmt = mysqli_prepare($con, "UPDATE job_alerts SET last_sent_at = NOW() WHERE id = ?");
            mysqli_stmt_bind_param($alert_stmt, "i", $user['id']);
            mysqli_stmt_execute($alert_stmt);
            mysqli_stmt_close($alert_stmt);
        }
    }
    
    return $sent_count;
}

/* ── Send Weekly Digest ─────────────────────────────────────────────────── */
function send_weekly_digests($con) {
    // Get users with weekly digest preference
    $result = mysqli_query($con, "SELECT ui.id, ui.username, ui.email 
                                  FROM user_info ui 
                                  INNER JOIN notification_preferences np ON np.user_id = ui.id AND np.user_type = 'user'
                                  WHERE np.job_alerts = 1");
    
    $sent_count = 0;
    while ($user = mysqli_fetch_assoc($result)) {
        // Get jobs from last week matching user skills
        $skills = '';
        $skill_result = mysqli_query($con, "SELECT user_skills FROM user_info WHERE id = " . intval($user['id']));
        if ($skill_row = mysqli_fetch_assoc($skill_result)) {
            $skills = $skill_row['user_skills'];
        }
        
        if (empty($skills)) continue;
        
        $skill_array = array_map('trim', explode(',', $skills));
        $skill_conditions = [];
        $params = [];
        $types = '';
        
        foreach ($skill_array as $skill) {
            if ($skill) {
                $skill_conditions[] = "cj.skills_required LIKE ?";
                $params[] = '%' . $skill . '%';
                $types .= 's';
            }
        }
        
        if (empty($skill_conditions)) continue;
        
        $where = implode(' OR ', $skill_conditions);
        $sql = "SELECT cj.*, c.company_name 
                FROM company_jobs cj 
                LEFT JOIN companies c ON cj.company_id = c.id 
                WHERE cj.status = 'active' 
                AND cj.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                AND ($where)
                ORDER BY cj.created_at DESC 
                LIMIT 5";
        
        $stmt = mysqli_prepare($con, $sql);
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $job_result = mysqli_stmt_get_result($stmt);
        
        $jobs = [];
        while ($job = mysqli_fetch_assoc($job_result)) {
            $jobs[] = $job;
        }
        mysqli_stmt_close($stmt);
        
        if (!empty($jobs)) {
            if (send_weekly_job_digest($user['email'], $user['username'], $jobs)) {
                $sent_count++;
            }
        }
    }
    
    return $sent_count;
}

/* ── Render Alert Form ──────────────────────────────────────────────────── */
function render_job_alert_form($categories = [], $types = []) {
    $html = '
    <form method="POST" action="' . BASE_URL . '/api/create_job_alert.php" class="alert-form">
        <div class="form-row">
            <div class="form-group">
                <label>Alert Name</label>
                <input type="text" name="alert_name" class="form-control" placeholder="e.g., PHP Developer Jobs" required>
            </div>
            <div class="form-group">
                <label>Keywords</label>
                <input type="text" name="keywords" class="form-control" placeholder="e.g., PHP, Laravel, Remote">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Category</label>
                <select name="category" class="form-control">
                    <option value="">All Categories</option>';
    
    foreach ($categories as $cat) {
        $html .= '<option value="' . e($cat) . '">' . e($cat) . '</option>';
    }
    
    $html .= '
                    </select>
            </div>
            <div class="form-group">
                <label>Location</label>
                <input type="text" name="location" class="form-control" placeholder="e.g., Dhaka, Remote">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Employment Type</label>
                <select name="employment_type" class="form-control">
                    <option value="">All Types</option>';
    
    foreach ($types as $type) {
        $html .= '<option value="' . e($type) . '">' . e($type) . '</option>';
    }
    
    $html .= '
                    </select>
            </div>
            <div class="form-group">
                <label>Frequency</label>
                <select name="frequency" class="form-control">
                    <option value="instant">Instant</option>
                    <option value="daily" selected>Daily</option>
                    <option value="weekly">Weekly</option>
                </select>
            </div>
        </div>
        
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-bell"></i> Create Alert
        </button>
    </form>';
    
    return $html;
}

/* ── Render Alert List ──────────────────────────────────────────────────── */
function render_alert_list($alerts) {
    if (empty($alerts)) {
        return '<div class="empty-state"><i class="fas fa-bell-slash"></i><p>No job alerts yet. Create one to get notified about matching jobs!</p></div>';
    }
    
    $html = '<div class="alert-list">';
    foreach ($alerts as $alert) {
        $active_class = $alert['is_active'] ? 'active' : 'inactive';
        $html .= '
        <div class="alert-item ' . $active_class . '">
            <div class="alert-info">
                <h4>' . e($alert['alert_name']) . '</h4>
                <div class="alert-criteria">';
        
        if ($alert['keywords']) $html .= '<span><i class="fas fa-key"></i> ' . e($alert['keywords']) . '</span>';
        if ($alert['category']) $html .= '<span><i class="fas fa-folder"></i> ' . e($alert['category']) . '</span>';
        if ($alert['location']) $html .= '<span><i class="fas fa-map-marker-alt"></i> ' . e($alert['location']) . '</span>';
        
        $html .= '
                    <span><i class="fas fa-clock"></i> ' . ucfirst($alert['frequency']) . '</span>
                </div>
            </div>
            <div class="alert-actions">
                <button class="btn btn-sm btn-toggle" onclick="toggleAlert(' . $alert['id'] . ', ' . ($alert['is_active'] ? 0 : 1) . ')">
                    ' . ($alert['is_active'] ? '<i class="fas fa-pause"></i>' : '<i class="fas fa-play"></i>') . '
                </button>
                <button class="btn btn-sm btn-danger" onclick="deleteAlert(' . $alert['id'] . ')">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>';
    }
    $html .= '</div>';
    
    return $html;
}
?>
