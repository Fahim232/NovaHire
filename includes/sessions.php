<?php
/**
 * NovaHire — Live Grooming Sessions (Mentor Marketplace)
 * ---------------------------------------------------------------------------
 * A two-sided marketplace: industry mentors sell 1-on-1 sessions
 * (mock interviews, resume reviews, career coaching); the platform keeps a
 * commission on every booking (see nh_session_split() in monetization.php).
 */

if (defined('NOVAHIRE_SESSIONS')) return;
define('NOVAHIRE_SESSIONS', true);

/* ── Session types ─────────────────────────────────────────────────────────── */
function nh_session_types() {
    return [
        'mock_interview'  => ['label' => 'Mock Interview',   'icon' => 'fa-user-tie',    'desc' => 'Practice a realistic interview and get instant feedback.'],
        'resume_review'   => ['label' => 'Resume Review',    'icon' => 'fa-file-lines',  'desc' => 'Line-by-line review of your CV with an expert.'],
        'career_coaching' => ['label' => 'Career Coaching',  'icon' => 'fa-compass',     'desc' => 'Map your next move with a senior professional.'],
    ];
}

function nh_session_type_label($key) {
    $t = nh_session_types();
    return $t[$key]['label'] ?? ucfirst(str_replace('_', ' ', $key));
}

/* ── Mentor directory ──────────────────────────────────────────────────────── */
function nh_get_mentors($con, $filters = []) {
    $where = ["status = 'approved'"];
    $params = [];
    $types = '';

    if (!empty($filters['category'])) {
        $where[] = "category = ?";
        $params[] = $filters['category'];
        $types .= 's';
    }
    if (!empty($filters['search'])) {
        $where[] = "(name LIKE ? OR headline LIKE ? OR bio LIKE ?)";
        $like = '%' . $filters['search'] . '%';
        array_push($params, $like, $like, $like);
        $types .= 'sss';
    }
    $sql = "SELECT * FROM mentors WHERE " . implode(' AND ', $where) . " ORDER BY rating DESC, total_sessions DESC";
    $stmt = mysqli_prepare($con, $sql);
    if ($params) mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;
    mysqli_stmt_close($stmt);
    return $rows;
}

function nh_get_mentor($con, $mentor_id) {
    $stmt = mysqli_prepare($con, "SELECT * FROM mentors WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "i", $mentor_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

function nh_mentor_categories($con) {
    $cats = [];
    $r = @mysqli_query($con, "SELECT DISTINCT category FROM mentors WHERE status='approved' ORDER BY category");
    if ($r) while ($row = mysqli_fetch_assoc($r)) $cats[] = $row['category'];
    return $cats;
}

/* ── Availability slots ────────────────────────────────────────────────────── */
function nh_get_mentor_slots($con, $mentor_id, $only_available = true) {
    $sql = "SELECT * FROM mentor_slots WHERE mentor_id = ? AND CONCAT(slot_date,' ',slot_time) >= NOW()";
    if ($only_available) $sql .= " AND is_booked = 0";
    $sql .= " ORDER BY slot_date, slot_time";
    $stmt = mysqli_prepare($con, $sql);
    mysqli_stmt_bind_param($stmt, "i", $mentor_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;
    mysqli_stmt_close($stmt);
    return $rows;
}

function nh_get_slot($con, $slot_id) {
    $stmt = mysqli_prepare($con, "SELECT * FROM mentor_slots WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "i", $slot_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

function nh_add_mentor_slot($con, $mentor_id, $date, $time, $duration = 45) {
    $stmt = mysqli_prepare($con, "INSERT INTO mentor_slots (mentor_id, slot_date, slot_time, duration_min, created_at) VALUES (?, ?, ?, ?, NOW())");
    mysqli_stmt_bind_param($stmt, "issi", $mentor_id, $date, $time, $duration);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

/* ── Booking flow ──────────────────────────────────────────────────────────── */
/**
 * Create a pending-payment booking. Returns session_id (or false).
 * The slot is only locked once payment is confirmed (nh_confirm_session).
 */
function nh_create_session_booking($con, $user_id, $mentor_id, $slot_id, $session_type) {
    $mentor = nh_get_mentor($con, $mentor_id);
    $slot   = nh_get_slot($con, $slot_id);
    if (!$mentor || !$slot || $slot['is_booked'] || $slot['mentor_id'] != $mentor_id) return false;

    $price = (float)$mentor['hourly_rate'];
    $split = nh_session_split($price);
    $scheduled_at = $slot['slot_date'] . ' ' . $slot['slot_time'];
    $duration = (int)$slot['duration_min'];

    $stmt = mysqli_prepare($con, "INSERT INTO grooming_sessions
        (mentor_id, user_id, slot_id, session_type, scheduled_at, duration_min, price, commission, mentor_earning, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending_payment', NOW())");
    mysqli_stmt_bind_param($stmt, "iiissiddd", $mentor_id, $user_id, $slot_id, $session_type, $scheduled_at, $duration, $price, $split['commission'], $split['mentor_earning']);
    $ok = mysqli_stmt_execute($stmt);
    $session_id = $ok ? mysqli_insert_id($con) : false;
    mysqli_stmt_close($stmt);
    return $session_id;
}

/**
 * Called by fulfill_payment() once a session payment completes.
 * Locks the slot, generates a meeting link, credits the mentor, notifies both.
 */
function nh_confirm_session($con, $session_id, $payment_id = null) {
    $sess = nh_get_session($con, $session_id);
    if (!$sess || $sess['status'] === 'confirmed' || $sess['status'] === 'completed') return false;

    $meeting_link = nh_generate_meeting_link($session_id);
    $stmt = mysqli_prepare($con, "UPDATE grooming_sessions SET status = 'confirmed', payment_id = ?, meeting_link = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "ssi", $payment_id, $meeting_link, $session_id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    if (!$ok) return false;

    // lock the slot
    if (!empty($sess['slot_id'])) {
        @mysqli_query($con, "UPDATE mentor_slots SET is_booked = 1 WHERE id = " . (int)$sess['slot_id']);
    }

    // credit the mentor's payout ledger
    $stmt = mysqli_prepare($con, "INSERT INTO mentor_earnings (mentor_id, session_id, amount, type, status, created_at) VALUES (?, ?, ?, 'earning', 'available', NOW())");
    mysqli_stmt_bind_param($stmt, "iid", $sess['mentor_id'], $session_id, $sess['mentor_earning']);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    @mysqli_query($con, "UPDATE mentors SET total_sessions = total_sessions + 1 WHERE id = " . (int)$sess['mentor_id']);

    // notifications (notification_type must be a valid enum → 'system')
    if (function_exists('create_notification')) {
        $when = date('M j, Y g:i A', strtotime($sess['scheduled_at']));
        create_notification($con, 'user', $sess['user_id'], 'system', null,
            'Session confirmed ✅', 'Your ' . nh_session_type_label($sess['session_type']) . ' is booked for <strong>' . $when . '</strong>. Join link is in "My Sessions".', 'system', 'grooming_sessions', $session_id);
        create_notification($con, 'mentor', $sess['mentor_id'], 'system', null,
            'New booking 📅', 'You have a new ' . nh_session_type_label($sess['session_type']) . ' on <strong>' . $when . '</strong>.', 'system', 'grooming_sessions', $session_id);
    }
    return true;
}

function nh_generate_meeting_link($session_id) {
    // Deterministic room name; swap for a real Jitsi/Zoom/Meet integration later.
    return 'https://meet.jit.si/NovaHire-' . $session_id . '-' . substr(md5('nh' . $session_id), 0, 8);
}

function nh_get_session($con, $session_id) {
    $sql = "SELECT s.*, m.name AS mentor_name, m.photo AS mentor_photo, m.headline AS mentor_headline,
                   u.username AS user_name, u.email AS user_email
            FROM grooming_sessions s
            JOIN mentors m ON m.id = s.mentor_id
            JOIN user_info u ON u.id = s.user_id
            WHERE s.id = ? LIMIT 1";
    $stmt = mysqli_prepare($con, $sql);
    mysqli_stmt_bind_param($stmt, "i", $session_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

function nh_user_sessions($con, $user_id) {
    $sql = "SELECT s.*, m.name AS mentor_name, m.photo AS mentor_photo, m.headline AS mentor_headline
            FROM grooming_sessions s JOIN mentors m ON m.id = s.mentor_id
            WHERE s.user_id = ? AND s.status <> 'pending_payment'
            ORDER BY s.scheduled_at DESC";
    $stmt = mysqli_prepare($con, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;
    mysqli_stmt_close($stmt);
    return $rows;
}

function nh_mentor_sessions($con, $mentor_id) {
    $sql = "SELECT s.*, u.username AS user_name, u.email AS user_email
            FROM grooming_sessions s JOIN user_info u ON u.id = s.user_id
            WHERE s.mentor_id = ? AND s.status <> 'pending_payment'
            ORDER BY s.scheduled_at DESC";
    $stmt = mysqli_prepare($con, $sql);
    mysqli_stmt_bind_param($stmt, "i", $mentor_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;
    mysqli_stmt_close($stmt);
    return $rows;
}

function nh_complete_session($con, $session_id, $mentor_id = null) {
    $sql = "UPDATE grooming_sessions SET status = 'completed' WHERE id = ? AND status = 'confirmed'";
    if ($mentor_id) $sql .= " AND mentor_id = " . (int)$mentor_id;
    $stmt = mysqli_prepare($con, $sql);
    mysqli_stmt_bind_param($stmt, "i", $session_id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

/* ── Reviews (drive mentor rating) ─────────────────────────────────────────── */
function nh_add_session_review($con, $session_id, $user_id, $mentor_id, $rating, $comment) {
    $rating = max(1, min(5, (int)$rating));
    $stmt = mysqli_prepare($con, "INSERT INTO session_reviews (session_id, user_id, mentor_id, rating, comment, created_at) VALUES (?, ?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE rating = VALUES(rating), comment = VALUES(comment)");
    mysqli_stmt_bind_param($stmt, "iiiis", $session_id, $user_id, $mentor_id, $rating, $comment);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    nh_recalc_mentor_rating($con, $mentor_id);
    return $ok;
}

function nh_recalc_mentor_rating($con, $mentor_id) {
    $r = @mysqli_query($con, "SELECT AVG(rating) avg_r, COUNT(*) c FROM session_reviews WHERE mentor_id = " . (int)$mentor_id);
    if ($r && ($row = mysqli_fetch_assoc($r))) {
        $avg = round((float)$row['avg_r'], 2);
        $cnt = (int)$row['c'];
        $stmt = mysqli_prepare($con, "UPDATE mentors SET rating = ?, total_reviews = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "dii", $avg, $cnt, $mentor_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

function nh_session_has_review($con, $session_id) {
    $r = @mysqli_query($con, "SELECT id FROM session_reviews WHERE session_id = " . (int)$session_id . " LIMIT 1");
    return $r && mysqli_num_rows($r) > 0;
}

/* ── Mentor earnings ───────────────────────────────────────────────────────── */
function nh_mentor_earnings_summary($con, $mentor_id) {
    $out = ['available' => 0.0, 'total' => 0.0, 'paid' => 0.0];
    $r = @mysqli_query($con, "SELECT
            COALESCE(SUM(CASE WHEN type='earning' THEN amount ELSE 0 END),0) total,
            COALESCE(SUM(CASE WHEN type='earning' AND status='available' THEN amount ELSE 0 END),0) available,
            COALESCE(SUM(CASE WHEN status='paid' THEN amount ELSE 0 END),0) paid
        FROM mentor_earnings WHERE mentor_id = " . (int)$mentor_id);
    if ($r && ($row = mysqli_fetch_assoc($r))) {
        $out = ['available' => (float)$row['available'], 'total' => (float)$row['total'], 'paid' => (float)$row['paid']];
    }
    return $out;
}

/* ── Platform revenue from sessions (admin) ────────────────────────────────── */
function nh_session_revenue($con) {
    $out = ['bookings' => 0, 'gross' => 0.0, 'commission' => 0.0];
    $r = @mysqli_query($con, "SELECT COUNT(*) bookings, COALESCE(SUM(price),0) gross, COALESCE(SUM(commission),0) commission
        FROM grooming_sessions WHERE status IN ('confirmed','completed')");
    if ($r && ($row = mysqli_fetch_assoc($r))) {
        $out = ['bookings' => (int)$row['bookings'], 'gross' => (float)$row['gross'], 'commission' => (float)$row['commission']];
    }
    return $out;
}
?>
