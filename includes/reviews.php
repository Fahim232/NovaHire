<?php
/**
 * NovaHire — Company Reviews System
 * Reviews, ratings, and verification
 */

if (defined('NOVAHIRE_REVIEWS')) return;
define('NOVAHIRE_REVIEWS', true);

/* ── Add Review ─────────────────────────────────────────────────────────── */
function add_company_review($con, $company_id, $user_id, $rating, $title, $review_text, $pros = '', $cons = '', $ratings = []) {
    // Check if user already reviewed this company
    $check_stmt = mysqli_prepare($con, "SELECT id FROM company_reviews WHERE company_id = ? AND user_id = ?");
    mysqli_stmt_bind_param($check_stmt, "ii", $company_id, $user_id);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    
    if (mysqli_num_rows($check_result) > 0) {
        mysqli_stmt_close($check_stmt);
        return ['success' => false, 'message' => 'You have already reviewed this company'];
    }
    mysqli_stmt_close($check_stmt);
    
    $work_env = $ratings['work_environment'] ?? null;
    $management = $ratings['management'] ?? null;
    $salary = $ratings['salary_benefits'] ?? null;
    $balance = $ratings['work_life_balance'] ?? null;
    $growth = $ratings['career_growth'] ?? null;
    
    $stmt = mysqli_prepare($con, "INSERT INTO company_reviews (company_id, user_id, rating, title, review_text, pros, cons, work_environment, management, salary_benefits, work_life_balance, career_growth) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "iiisssiiiiii", $company_id, $user_id, $rating, $title, $review_text, $pros, $cons, $work_env, $management, $salary, $balance, $growth);
    
    if (mysqli_stmt_execute($stmt)) {
        $review_id = mysqli_insert_id($con);
        mysqli_stmt_close($stmt);
        
        // Update company average rating
        update_company_rating($con, $company_id);
        
        return ['success' => true, 'review_id' => $review_id];
    }
    mysqli_stmt_close($stmt);
    
    return ['success' => false, 'message' => 'Failed to add review'];
}

/* ── Get Company Reviews ────────────────────────────────────────────────── */
function get_company_reviews($con, $company_id, $page = 1, $per_page = 10) {
    // Check if table exists
    $check = @mysqli_query($con, "SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'company_reviews'");
    if (!$check || mysqli_num_rows($check) == 0) return ['reviews' => [], 'total' => 0, 'page' => 1, 'total_pages' => 0];
    
    $offset = ($page - 1) * $per_page;
    
    // Get total count
    $count_stmt = mysqli_prepare($con, "SELECT COUNT(*) as total FROM company_reviews WHERE company_id = ? AND status = 'approved'");
    mysqli_stmt_bind_param($count_stmt, "i", $company_id);
    mysqli_stmt_execute($count_stmt);
    $count_result = mysqli_stmt_get_result($count_stmt);
    $total = mysqli_fetch_assoc($count_result)['total'];
    mysqli_stmt_close($count_stmt);
    
    // Get reviews
    $stmt = mysqli_prepare($con, "SELECT cr.*, ui.username, ui.profile 
                                   FROM company_reviews cr 
                                   LEFT JOIN user_info ui ON cr.user_id = ui.id 
                                   WHERE cr.company_id = ? AND cr.status = 'approved' 
                                   ORDER BY cr.created_at DESC 
                                   LIMIT ? OFFSET ?");
    mysqli_stmt_bind_param($stmt, "iii", $company_id, $per_page, $offset);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $reviews = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $reviews[] = $row;
    }
    mysqli_stmt_close($stmt);
    
    return [
        'reviews' => $reviews,
        'total' => $total,
        'page' => $page,
        'total_pages' => ceil($total / $per_page),
    ];
}

/* ── Update Company Rating ──────────────────────────────────────────────── */
function update_company_rating($con, $company_id) {
    $stmt = mysqli_prepare($con, "SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews FROM company_reviews WHERE company_id = ? AND status = 'approved'");
    mysqli_stmt_bind_param($stmt, "i", $company_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    $avg_rating = round($row['avg_rating'] ?? 0, 2);
    $total_reviews = $row['total_reviews'] ?? 0;
    
    $update_stmt = mysqli_prepare($con, "UPDATE companies SET avg_rating = ?, total_reviews = ? WHERE id = ?");
    mysqli_stmt_bind_param($update_stmt, "dii", $avg_rating, $total_reviews, $company_id);
    mysqli_stmt_execute($update_stmt);
    mysqli_stmt_close($update_stmt);
}

/* ── Get Rating Distribution ────────────────────────────────────────────── */
function get_rating_distribution($con, $company_id) {
    $stmt = mysqli_prepare($con, "SELECT rating, COUNT(*) as count FROM company_reviews WHERE company_id = ? AND status = 'approved' GROUP BY rating ORDER BY rating DESC");
    mysqli_stmt_bind_param($stmt, "i", $company_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $distribution = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
    $total = 0;
    $sum = 0;
    
    while ($row = mysqli_fetch_assoc($result)) {
        $distribution[intval($row['rating'])] = intval($row['count']);
        $total += $row['count'];
        $sum += $row['rating'] * $row['count'];
    }
    mysqli_stmt_close($stmt);
    
    return [
        'distribution' => $distribution,
        'total' => $total,
        'average' => $total > 0 ? round($sum / $total, 1) : 0,
    ];
}

/* ── Render Star Rating ─────────────────────────────────────────────────── */
function render_star_rating($rating, $size = '1rem') {
    $html = '<div class="star-rating" style="font-size:' . $size . ';">';
    
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= $rating) {
            $html .= '<i class="fas fa-star" style="color:#fbbf24;"></i>';
        } elseif ($i - 0.5 <= $rating) {
            $html .= '<i class="fas fa-star-half-alt" style="color:#fbbf24;"></i>';
        } else {
            $html .= '<i class="far fa-star" style="color:#d1d5db;"></i>';
        }
    }
    
    $html .= '</div>';
    return $html;
}

/* ── Render Rating Bar ──────────────────────────────────────────────────── */
function render_rating_bar($star, $count, $total) {
    $percentage = $total > 0 ? ($count / $total) * 100 : 0;
    
    return '
    <div class="rating-bar">
        <span class="star-count">' . $star . ' <i class="fas fa-star"></i></span>
        <div class="bar-track">
            <div class="bar-fill" style="width: ' . $percentage . '%;"></div>
        </div>
        <span class="count">' . $count . '</span>
    </div>';
}

/* ── Render Review Card ─────────────────────────────────────────────────── */
function render_review_card($review) {
    $username = $review['is_anonymous'] ? 'Anonymous' : htmlspecialchars($review['username'] ?? 'User');
    $profile = $review['profile'] ?? '';
    $profile_url = !empty($profile) ? BASE_URL . '/images/' . htmlspecialchars($profile) : BASE_URL . '/images/default-avatar.png';
    
    $html = '
    <div class="review-card">
        <div class="review-header">
            <div class="reviewer-info">
                <img src="' . $profile_url . '" alt="' . $username . '" class="reviewer-avatar">
                <div>
                    <h4 class="reviewer-name">' . $username . '</h4>
                    <span class="review-date">' . date('M d, Y', strtotime($review['created_at'])) . '</span>
                </div>
            </div>
            <div class="review-rating">
                ' . render_star_rating($review['rating']) . '
            </div>
        </div>
        
        <h5 class="review-title">' . htmlspecialchars($review['title'] ?? '') . '</h5>
        <p class="review-text">' . htmlspecialchars($review['review_text']) . '</p>';
    
    if (!empty($review['pros'])) {
        $html .= '
        <div class="review-pros">
            <h6><i class="fas fa-plus-circle" style="color:#059669;"></i> Pros</h6>
            <p>' . htmlspecialchars($review['pros']) . '</p>
        </div>';
    }
    
    if (!empty($review['cons'])) {
        $html .= '
        <div class="review-cons">
            <h6><i class="fas fa-minus-circle" style="color:#dc2626;"></i> Cons</h6>
            <p>' . htmlspecialchars($review['cons']) . '</p>
        </div>';
    }
    
    // Category ratings
    $categories = [
        'work_environment' => 'Work Environment',
        'management' => 'Management',
        'salary_benefits' => 'Salary & Benefits',
        'work_life_balance' => 'Work-Life Balance',
        'career_growth' => 'Career Growth',
    ];
    
    $has_ratings = false;
    foreach ($categories as $key => $label) {
        if (!empty($review[$key])) {
            $has_ratings = true;
            break;
        }
    }
    
    if ($has_ratings) {
        $html .= '<div class="review-categories">';
        foreach ($categories as $key => $label) {
            if (!empty($review[$key])) {
                $html .= '
                <div class="category-rating">
                    <span>' . $label . '</span>
                    ' . render_star_rating($review[$key], '0.8rem') . '
                </div>';
            }
        }
        $html .= '</div>';
    }
    
    $html .= '</div>';
    
    return $html;
}

/* ── Submit Review Form ─────────────────────────────────────────────────── */
function render_review_form($company_id) {
    return '
    <form id="reviewForm" class="review-form">
        <h4>Write a Review</h4>
        
        <div class="form-group">
            <label>Overall Rating *</label>
            <div class="rating-input" id="overallRating">
                <i class="far fa-star" data-rating="1"></i>
                <i class="far fa-star" data-rating="2"></i>
                <i class="far fa-star" data-rating="3"></i>
                <i class="far fa-star" data-rating="4"></i>
                <i class="far fa-star" data-rating="5"></i>
            </div>
            <input type="hidden" name="rating" id="ratingValue" required>
        </div>
        
        <div class="form-group">
            <label>Review Title</label>
            <input type="text" name="title" class="form-control" placeholder="Summarize your experience">
        </div>
        
        <div class="form-group">
            <label>Your Review *</label>
            <textarea name="review_text" class="form-control" rows="4" placeholder="Share your experience working at this company..." required></textarea>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Pros</label>
                <textarea name="pros" class="form-control" rows="2" placeholder="What do you like about this company?"></textarea>
            </div>
            <div class="form-group">
                <label>Cons</label>
                <textarea name="cons" class="form-control" rows="2" placeholder="What could be improved?"></textarea>
            </div>
        </div>
        
        <h5>Rate Specific Areas (Optional)</h5>
        
        <div class="rating-categories">
            <div class="form-group">
                <label>Work Environment</label>
                <div class="rating-input category-rating-input" data-category="work_environment">
                    <i class="far fa-star" data-rating="1"></i>
                    <i class="far fa-star" data-rating="2"></i>
                    <i class="far fa-star" data-rating="3"></i>
                    <i class="far fa-star" data-rating="4"></i>
                    <i class="far fa-star" data-rating="5"></i>
                </div>
                <input type="hidden" name="work_environment" value="">
            </div>
            
            <div class="form-group">
                <label>Management</label>
                <div class="rating-input category-rating-input" data-category="management">
                    <i class="far fa-star" data-rating="1"></i>
                    <i class="far fa-star" data-rating="2"></i>
                    <i class="far fa-star" data-rating="3"></i>
                    <i class="far fa-star" data-rating="4"></i>
                    <i class="far fa-star" data-rating="5"></i>
                </div>
                <input type="hidden" name="management" value="">
            </div>
            
            <div class="form-group">
                <label>Salary & Benefits</label>
                <div class="rating-input category-rating-input" data-category="salary_benefits">
                    <i class="far fa-star" data-rating="1"></i>
                    <i class="far fa-star" data-rating="2"></i>
                    <i class="far fa-star" data-rating="3"></i>
                    <i class="far fa-star" data-rating="4"></i>
                    <i class="far fa-star" data-rating="5"></i>
                </div>
                <input type="hidden" name="salary_benefits" value="">
            </div>
            
            <div class="form-group">
                <label>Work-Life Balance</label>
                <div class="rating-input category-rating-input" data-category="work_life_balance">
                    <i class="far fa-star" data-rating="1"></i>
                    <i class="far fa-star" data-rating="2"></i>
                    <i class="far fa-star" data-rating="3"></i>
                    <i class="far fa-star" data-rating="4"></i>
                    <i class="far fa-star" data-rating="5"></i>
                </div>
                <input type="hidden" name="work_life_balance" value="">
            </div>
            
            <div class="form-group">
                <label>Career Growth</label>
                <div class="rating-input category-rating-input" data-category="career_growth">
                    <i class="far fa-star" data-rating="1"></i>
                    <i class="far fa-star" data-rating="2"></i>
                    <i class="far fa-star" data-rating="3"></i>
                    <i class="far fa-star" data-rating="4"></i>
                    <i class="far fa-star" data-rating="5"></i>
                </div>
                <input type="hidden" name="career_growth" value="">
            </div>
        </div>
        
        <div class="form-group">
            <label>
                <input type="checkbox" name="is_anonymous" value="1"> Post anonymously
            </label>
        </div>
        
        <button type="submit" class="btn btn-primary">Submit Review</button>
    </form>';
}
?>
