<?php
/**
 * NovaHire — Advanced Job Search
 * Full-text search, filters, location, salary range
 */

if (defined('NOVAHIRE_SEARCH')) return;
define('NOVAHIRE_SEARCH', true);

/* ── Build Search Query ──────────────────────────────────────────────────── */
function build_job_search_query($filters = [], $page = 1, $per_page = 12) {
    $conditions = ["cj.status = 'active'"];
    $params = [];
    $types = '';
    
    // Text search
    if (!empty($filters['q'])) {
        $conditions[] = "(cj.job_title LIKE ? OR cj.job_description LIKE ? OR cj.skills_required LIKE ?)";
        $search = '%' . $filters['q'] . '%';
        $params[] = $search;
        $params[] = $search;
        $params[] = $search;
        $types .= 'sss';
    }
    
    // Category filter
    if (!empty($filters['category'])) {
        $conditions[] = "cj.job_category = ?";
        $params[] = $filters['category'];
        $types .= 's';
    }
    
    // Location filter
    if (!empty($filters['location'])) {
        $conditions[] = "cj.location LIKE ?";
        $params[] = '%' . $filters['location'] . '%';
        $types .= 's';
    }
    
    // Employment type filter
    if (!empty($filters['employment_type'])) {
        $conditions[] = "cj.employment_type = ?";
        $params[] = $filters['employment_type'];
        $types .= 's';
    }
    
    // Experience filter
    if (!empty($filters['experience_min'])) {
        $conditions[] = "cj.experience_required >= ?";
        $params[] = $filters['experience_min'];
        $types .= 'i';
    }
    if (!empty($filters['experience_max'])) {
        $conditions[] = "cj.experience_required <= ?";
        $params[] = $filters['experience_max'];
        $types .= 'i';
    }
    
    // Salary range filter
    if (!empty($filters['salary_min'])) {
        $conditions[] = "cj.salary_min >= ?";
        $params[] = $filters['salary_min'];
        $types .= 'i';
    }
    if (!empty($filters['salary_max'])) {
        $conditions[] = "cj.salary_max <= ?";
        $params[] = $filters['salary_max'];
        $types .= 'i';
    }
    
    // Skills filter
    if (!empty($filters['skills'])) {
        $skill_conditions = [];
        foreach (explode(',', $filters['skills']) as $skill) {
            $skill = trim($skill);
            if ($skill) {
                $skill_conditions[] = "cj.skills_required LIKE ?";
                $params[] = '%' . $skill . '%';
                $types .= 's';
            }
        }
        if (!empty($skill_conditions)) {
            $conditions[] = "(" . implode(' OR ', $skill_conditions) . ")";
        }
    }
    
    // Company filter
    if (!empty($filters['company_id'])) {
        $conditions[] = "cj.company_id = ?";
        $params[] = $filters['company_id'];
        $types .= 'i';
    }
    
    // Deadline not passed
    $conditions[] = "(cj.deadline >= CURDATE() OR cj.deadline = '0000-00-00')";
    
    // Build WHERE clause
    $where = implode(' AND ', $conditions);
    
    // Sorting
    $order_by = match($filters['sort'] ?? 'newest') {
        'oldest'    => 'cj.created_at ASC',
        'salary_high' => 'cj.salary_max DESC',
        'salary_low'  => 'cj.salary_min ASC',
        'deadline'  => 'cj.deadline ASC',
        'popular'   => '(SELECT COUNT(*) FROM job_applications ja WHERE ja.job_id = cj.job_id) DESC',
        default     => 'cj.created_at DESC',
    };
    
    // Pagination
    $offset = ($page - 1) * $per_page;
    
    // Count query
    $count_sql = "SELECT COUNT(*) as total FROM company_jobs cj WHERE $where";
    
    // Main query
    $sql = "SELECT cj.*, c.company_name, c.logo as company_logo, c.is_verified,
            (SELECT COUNT(*) FROM job_applications ja WHERE ja.job_id = cj.job_id) as application_count
            FROM company_jobs cj 
            LEFT JOIN companies c ON cj.company_id = c.id 
            WHERE $where 
            ORDER BY $order_by 
            LIMIT $per_page OFFSET $offset";
    
    return [
        'sql'      => $sql,
        'count_sql' => $count_sql,
        'params'   => $params,
        'types'    => $types,
        'page'     => $page,
        'per_page' => $per_page,
    ];
}

/* ── Execute Search ──────────────────────────────────────────────────────── */
function execute_job_search($con, $filters = [], $page = 1, $per_page = 12) {
    $query = build_job_search_query($filters, $page, $per_page);
    
    // Get total count
    $count_stmt = mysqli_prepare($con, $query['count_sql']);
    if (!empty($query['params']) && !empty($query['types'])) {
        mysqli_stmt_bind_param($count_stmt, $query['types'], ...$query['params']);
    }
    mysqli_stmt_execute($count_stmt);
    $count_result = mysqli_stmt_get_result($count_stmt);
    $total_row = mysqli_fetch_assoc($count_result);
    $total = $total_row['total'];
    mysqli_stmt_close($count_stmt);
    
    // Get results
    $stmt = mysqli_prepare($con, $query['sql']);
    if (!empty($query['params']) && !empty($query['types'])) {
        mysqli_stmt_bind_param($stmt, $query['types'], ...$query['params']);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $jobs = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $jobs[] = $row;
    }
    mysqli_stmt_close($stmt);
    
    $total_pages = ceil($total / $per_page);
    
    return [
        'jobs'        => $jobs,
        'total'       => $total,
        'page'        => $page,
        'per_page'    => $per_page,
        'total_pages' => $total_pages,
        'has_next'    => $page < $total_pages,
        'has_prev'    => $page > 1,
    ];
}

/* ── Get Filter Options ──────────────────────────────────────────────────── */
function get_job_categories($con) {
    $result = mysqli_query($con, "SELECT DISTINCT job_category, COUNT(*) as count FROM company_jobs WHERE status = 'active' GROUP BY job_category ORDER BY count DESC");
    $categories = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $categories[] = $row;
    }
    return $categories;
}

function get_job_locations($con) {
    $result = mysqli_query($con, "SELECT DISTINCT location, COUNT(*) as count FROM company_jobs WHERE status = 'active' GROUP BY location ORDER BY count DESC LIMIT 50");
    $locations = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $locations[] = $row;
    }
    return $locations;
}

function get_employment_types($con) {
    $result = mysqli_query($con, "SELECT DISTINCT employment_type, COUNT(*) as count FROM company_jobs WHERE status = 'active' GROUP BY employment_type ORDER BY count DESC");
    $types = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $types[] = $row;
    }
    return $types;
}

/* ── Search Form HTML ────────────────────────────────────────────────────── */
function render_search_form($filters = [], $categories = [], $locations = [], $types = []) {
    $html = '
    <form method="GET" action="' . BASE_URL . '/seeker/browse_jobs.php" class="search-form">
        <div class="search-row">
            <div class="search-field search-field-wide">
                <i class="fas fa-search"></i>
                <input type="text" name="q" placeholder="Job title, skills, or keywords..." 
                       value="' . e($filters['q'] ?? '') . '">
            </div>
            <div class="search-field">
                <i class="fas fa-map-marker-alt"></i>
                <input type="text" name="location" placeholder="Location" 
                       value="' . e($filters['location'] ?? '') . '">
            </div>
            <button type="submit" class="search-btn">
                <i class="fas fa-search"></i> Search
            </button>
        </div>
        
        <div class="filter-row">
            <div class="filter-group">
                <select name="category">
                    <option value="">All Categories</option>';
    
    foreach ($categories as $cat) {
        $selected = (($filters['category'] ?? '') === $cat['job_category']) ? ' selected' : '';
        $html .= '<option value="' . e($cat['job_category']) . '"' . $selected . '>' . e($cat['job_category']) . ' (' . $cat['count'] . ')</option>';
    }
    
    $html .= '
                    </select>
            </div>
            
            <div class="filter-group">
                <select name="employment_type">
                    <option value="">All Types</option>';
    
    foreach ($types as $type) {
        $selected = (($filters['employment_type'] ?? '') === $type['employment_type']) ? ' selected' : '';
        $html .= '<option value="' . e($type['employment_type']) . '"' . $selected . '>' . e($type['employment_type']) . ' (' . $type['count'] . ')</option>';
    }
    
    $html .= '
                    </select>
            </div>
            
            <div class="filter-group">
                <select name="sort">
                    <option value="newest"' . (($filters['sort'] ?? '') === 'newest' ? ' selected' : '') . '>Newest First</option>
                    <option value="oldest"' . (($filters['sort'] ?? '') === 'oldest' ? ' selected' : '') . '>Oldest First</option>
                    <option value="salary_high"' . (($filters['sort'] ?? '') === 'salary_high' ? ' selected' : '') . '>Highest Salary</option>
                    <option value="salary_low"' . (($filters['sort'] ?? '') === 'salary_low' ? ' selected' : '') . '>Lowest Salary</option>
                    <option value="deadline"' . (($filters['sort'] ?? '') === 'deadline' ? ' selected' : '') . '>Deadline Soon</option>
                </select>
            </div>
        </div>
    </form>';
    
    return $html;
}

/* ── Render Job Card ─────────────────────────────────────────────────────── */
function render_job_card($job, $match_score = null) {
    $company_name = e($job['company_name'] ?? 'Unknown Company');
    $company_logo = !empty($job['company_logo']) ? BASE_URL . '/uploads/' . e($job['company_logo']) : BASE_URL . '/images/default-company.png';
    $is_verified = !empty($job['is_verified']);
    
    $html = '
    <div class="job-card">
        <div class="job-card-header">
            <img src="' . $company_logo . '" alt="' . $company_name . '" class="company-logo">
            <div class="company-info">
                <h4 class="company-name">' . $company_name;
    
    if ($is_verified) {
        $html .= ' <i class="fas fa-check-circle verified-badge" title="Verified Company"></i>';
    }
    
    $html .= '</h4>
                <span class="job-category">' . e($job['job_category']) . '</span>
            </div>
        </div>
        
        <h3 class="job-title">' . e($job['job_title']) . '</h3>
        
        <div class="job-meta">
            <span><i class="fas fa-map-marker-alt"></i> ' . e($job['location']) . '</span>
            <span><i class="fas fa-briefcase"></i> ' . e($job['employment_type']) . '</span>';
    
    if (!empty($job['salary_range'])) {
        $html .= '<span><i class="fas fa-money-bill-wave"></i> ' . e($job['salary_range']) . '</span>';
    }
    
    $html .= '
        </div>
        
        <p class="job-description">' . e(substr($job['job_description'], 0, 150)) . '...</p>';
    
    if (!empty($job['skills_required'])) {
        $skills = array_slice(explode(',', $job['skills_required']), 0, 5);
        $html .= '<div class="job-skills">';
        foreach ($skills as $skill) {
            $html .= '<span class="skill-tag">' . e(trim($skill)) . '</span>';
        }
        if (count(explode(',', $job['skills_required'])) > 5) {
            $html .= '<span class="skill-tag more">+' . (count(explode(',', $job['skills_required'])) - 5) . ' more</span>';
        }
        $html .= '</div>';
    }
    
    $html .= '
        <div class="job-footer">
            <div class="job-stats">
                <span><i class="fas fa-users"></i> ' . ($job['application_count'] ?? 0) . ' applicants</span>';
    
    if (!empty($job['deadline']) && $job['deadline'] !== '0000-00-00') {
        $deadline = strtotime($job['deadline']);
        $days_left = max(0, ceil(($deadline - time()) / 86400));
        $html .= '<span class="' . ($days_left <= 3 ? 'urgent' : '') . '"><i class="fas fa-clock"></i> ' . $days_left . ' days left</span>';
    }
    
    $html .= '
            </div>
            <a href="' . BASE_URL . '/seeker/job_details.php?id=' . $job['job_id'] . '" class="apply-btn">View Details →</a>
        </div>';
    
    if ($match_score !== null) {
        $color = $match_score >= 80 ? '#059669' : ($match_score >= 50 ? '#d97706' : '#dc2626');
        $html .= '<div class="match-score" style="background:' . $color . ';">' . $match_score . '% Match</div>';
    }
    
    $html .= '</div>';
    
    return $html;
}

/* ── Render Pagination ───────────────────────────────────────────────────── */
function render_pagination($current_page, $total_pages, $base_url = '') {
    if ($total_pages <= 1) return '';
    
    $html = '<div class="pagination">';
    
    // Previous
    if ($current_page > 1) {
        $html .= '<a href="' . $base_url . '&page=' . ($current_page - 1) . '" class="page-link"><i class="fas fa-chevron-left"></i></a>';
    }
    
    // Page numbers
    $start = max(1, $current_page - 2);
    $end = min($total_pages, $current_page + 2);
    
    if ($start > 1) {
        $html .= '<a href="' . $base_url . '&page=1" class="page-link">1</a>';
        if ($start > 2) $html .= '<span class="page-dots">...</span>';
    }
    
    for ($i = $start; $i <= $end; $i++) {
        $active = $i === $current_page ? ' active' : '';
        $html .= '<a href="' . $base_url . '&page=' . $i . '" class="page-link' . $active . '">' . $i . '</a>';
    }
    
    if ($end < $total_pages) {
        if ($end < $total_pages - 1) $html .= '<span class="page-dots">...</span>';
        $html .= '<a href="' . $base_url . '&page=' . $total_pages . '" class="page-link">' . $total_pages . '</a>';
    }
    
    // Next
    if ($current_page < $total_pages) {
        $html .= '<a href="' . $base_url . '&page=' . ($current_page + 1) . '" class="page-link"><i class="fas fa-chevron-right"></i></a>';
    }
    
    $html .= '</div>';
    return $html;
}
?>
