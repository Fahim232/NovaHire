<?php
/**
 * Notification & Messaging Helper Functions
 * Include this file wherever you need notification functionality
 */

if (defined('NOVAHIRE_FUNCTIONS_LOADED')) return;
define('NOVAHIRE_FUNCTIONS_LOADED', true);

if (!isset($con)) {
    include __DIR__ . '/../admin/dbcon.php';
}

/**
 * Create a new notification
 */
function create_notification($con, $recipient_type, $recipient_id, $sender_type, $sender_id, $title, $message, $notification_type = 'system', $related_type = null, $related_id = null) {
    $stmt = mysqli_prepare($con, "INSERT INTO notifications (recipient_type, recipient_id, sender_type, sender_id, title, message, notification_type, related_type, related_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "sisisssss", $recipient_type, $recipient_id, $sender_type, $sender_id, $title, $message, $notification_type, $related_type, $related_id);
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $result;
}

/**
 * Get unread notification count
 */
function get_unread_count($con, $recipient_type, $recipient_id) {
    $stmt = mysqli_prepare($con, "SELECT COUNT(*) as cnt FROM notifications WHERE recipient_type = ? AND recipient_id = ? AND is_read = 0");
    mysqli_stmt_bind_param($stmt, "si", $recipient_type, $recipient_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    return intval($row['cnt']);
}

/**
 * Get recent notifications for a recipient
 */
function get_notifications($con, $recipient_type, $recipient_id, $limit = 10, $offset = 0) {
    $stmt = mysqli_prepare($con, "SELECT * FROM notifications WHERE recipient_type = ? AND recipient_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
    mysqli_stmt_bind_param($stmt, "siii", $recipient_type, $recipient_id, $limit, $offset);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $notifications = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $notifications[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $notifications;
}

/**
 * Mark a notification as read
 */
function mark_read($con, $notification_id) {
    $stmt = mysqli_prepare($con, "UPDATE notifications SET is_read = 1 WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $notification_id);
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $result;
}

/**
 * Mark all notifications as read for a recipient
 */
function mark_all_read($con, $recipient_type, $recipient_id) {
    $stmt = mysqli_prepare($con, "UPDATE notifications SET is_read = 1 WHERE recipient_type = ? AND recipient_id = ? AND is_read = 0");
    mysqli_stmt_bind_param($stmt, "si", $recipient_type, $recipient_id);
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $result;
}

/**
 * Delete a notification
 */
function delete_notification($con, $notification_id) {
    $stmt = mysqli_prepare($con, "DELETE FROM notifications WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $notification_id);
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $result;
}

/**
 * Send a message between users/companies
 */
function send_message($con, $sender_type, $sender_id, $receiver_type, $receiver_id, $subject, $message, $related_job_id = null) {
    $stmt = mysqli_prepare($con, "INSERT INTO messages (sender_type, sender_id, receiver_type, receiver_id, subject, message, related_job_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "sisissi", $sender_type, $sender_id, $receiver_type, $receiver_id, $subject, $message, $related_job_id);
    $result = mysqli_stmt_execute($stmt);
    $message_id = mysqli_insert_id($con);
    mysqli_stmt_close($stmt);

    // Also create a notification for the receiver
    if ($result) {
        $title = "New Message: " . $subject;
        $notif_msg = substr($message, 0, 150) . (strlen($message) > 150 ? '...' : '');
        create_notification($con, $receiver_type, $receiver_id, $sender_type, $sender_id, $title, $notif_msg, 'message', 'messages', $message_id);
    }

    return $result ? $message_id : false;
}

/**
 * Get unread message count
 */
function get_unread_message_count($con, $recipient_type, $recipient_id) {
    $stmt = mysqli_prepare($con, "SELECT COUNT(*) as cnt FROM messages WHERE receiver_type = ? AND receiver_id = ? AND is_read = 0 AND is_deleted_by_receiver = 0");
    mysqli_stmt_bind_param($stmt, "si", $recipient_type, $recipient_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    return intval($row['cnt']);
}

/**
 * Get conversations for a user/company
 */
function get_conversations($con, $my_type, $my_id) {
    $conversations = [];
    $my_id = intval($my_id);
    $my_type = mysqli_real_escape_string($con, $my_type);
    
    $sql = "SELECT m.*, 
            (SELECT COUNT(*) FROM messages 
             WHERE sender_type = m.sender_type AND sender_id = m.sender_id 
             AND receiver_type = m.receiver_type AND receiver_id = m.receiver_id 
             AND is_read = 0 AND receiver_type = '$my_type' AND receiver_id = $my_id) as unread_in_thread
        FROM messages m
        WHERE (m.sender_type = '$my_type' AND m.sender_id = $my_id AND m.is_deleted_by_sender = 0)
           OR (m.receiver_type = '$my_type' AND m.receiver_id = $my_id AND m.is_deleted_by_receiver = 0)
        ORDER BY m.created_at DESC";
    
    $result = mysqli_query($con, $sql);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $conversations[] = $row;
        }
    }
    return $conversations;
}

/**
 * Get conversation between two parties
 */
function get_conversation($con, $type1, $id1, $type2, $id2, $limit = 50) {
    $id1 = intval($id1);
    $id2 = intval($id2);
    $limit = intval($limit);
    $type1 = mysqli_real_escape_string($con, $type1);
    $type2 = mysqli_real_escape_string($con, $type2);
    
    $sql = "SELECT * FROM messages 
        WHERE ((sender_type = '$type1' AND sender_id = $id1 AND receiver_type = '$type2' AND receiver_id = $id2)
            OR (sender_type = '$type2' AND sender_id = $id2 AND receiver_type = '$type1' AND receiver_id = $id1))
        AND is_deleted_by_sender = 0 AND is_deleted_by_receiver = 0
        ORDER BY created_at ASC LIMIT $limit";
    
    $messages = [];
    $result = mysqli_query($con, $sql);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $messages[] = $row;
        }
    }
    return $messages;
}

/**
 * Mark messages as read in a conversation
 */
function mark_conversation_read($con, $my_type, $my_id, $other_type, $other_id) {
    $stmt = mysqli_prepare($con, "UPDATE messages SET is_read = 1 WHERE sender_type = ? AND sender_id = ? AND receiver_type = ? AND receiver_id = ? AND is_read = 0");
    mysqli_stmt_bind_param($stmt, "sisi", $other_type, $other_id, $my_type, $my_id);
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $result;
}

/**
 * Get total unread count (notifications + messages)
 */
function get_total_unread($con, $recipient_type, $recipient_id) {
    return get_unread_count($con, $recipient_type, $recipient_id) + get_unread_message_count($con, $recipient_type, $recipient_id);
}

/**
 * Notify user about application status change
 */
function notify_application_status($con, $user_id, $company_id, $job_title, $company_name, $new_status) {
    $status_messages = [
        'reviewed' => "Your application for <strong>$job_title</strong> at <strong>$company_name</strong> has been reviewed.",
        'shortlisted' => "Congratulations! You've been shortlisted for <strong>$job_title</strong> at <strong>$company_name</strong>.",
        'rejected' => "We're sorry, your application for <strong>$job_title</strong> at <strong>$company_name</strong> was not selected.",
    ];
    
    $title = "Application " . ucfirst($new_status);
    $message = isset($status_messages[$new_status]) ? $status_messages[$new_status] : "Your application status has been updated to $new_status.";
    
    create_notification($con, 'user', $user_id, 'company', $company_id, $title, $message, 'application_status', 'job_applications', null);
}

/**
 * Notify company about new application
 */
function notify_new_application($con, $company_id, $user_id, $username, $job_title) {
    $title = "New Application";
    $message = "<strong>$username</strong> has applied for <strong>$job_title</strong>.";
    create_notification($con, 'company', $company_id, 'user', $user_id, $title, $message, 'new_application', 'job_applications', null);
}

/**
 * Render the notification dropdown HTML for user header
 */
function render_notification_dropdown($con, $recipient_type, $recipient_id) {
    $unread_count = get_unread_count($con, $recipient_type, $recipient_id);
    $notifications = get_notifications($con, $recipient_type, $recipient_id, 8);
    
    $type_icons = [
        'application_status' => 'fa-clipboard-check',
        'new_application' => 'fa-file-alt',
        'message' => 'fa-envelope',
        'quiz_result' => 'fa-chart-line',
        'job_update' => 'fa-briefcase',
        'system' => 'fa-bell',
        'job_recommendation' => 'fa-star',
    ];
    
    $type_colors = [
        'application_status' => '#10b981',
        'new_application' => '#3b82f6',
        'message' => '#8b5cf6',
        'quiz_result' => '#f59e0b',
        'job_update' => '#06b6d4',
        'system' => '#6366f1',
        'job_recommendation' => '#ec4899',
    ];
    
    $html = '<div class="notif-dropdown-menu shadow-lg" id="notifDropdown">';
    $html .= '<div class="notif-dropdown-header">';
    $html .= '<h6 class="m-0 font-weight-bold">Notifications</h6>';
    if ($unread_count > 0) {
        $html .= '<button class="btn btn-sm btn-link text-primary font-weight-bold" onclick="markAllNotificationsRead()">Mark all read</button>';
    }
    $html .= '</div>';
    $html .= '<div class="notif-dropdown-body">';
    
    if (empty($notifications)) {
        $html .= '<div class="notif-empty"><i class="fas fa-bell-slash fa-2x text-muted mb-2"></i><p class="text-muted mb-0">No notifications yet</p></div>';
    } else {
        foreach ($notifications as $notif) {
            $icon = isset($type_icons[$notif['notification_type']]) ? $type_icons[$notif['notification_type']] : 'fa-bell';
            $color = isset($type_colors[$notif['notification_type']]) ? $type_colors[$notif['notification_type']] : '#6366f1';
            $read_class = $notif['is_read'] ? '' : 'notif-unread';
            $time = time_ago($notif['created_at']);
            
            $html .= '<div class="notif-item ' . $read_class . '" onclick="markNotificationRead(' . $notif['id'] . ', this)">';
            $html .= '<div class="notif-icon" style="background: ' . $color . '20; color: ' . $color . ';">';
            $html .= '<i class="fas ' . $icon . '"></i>';
            $html .= '</div>';
            $html .= '<div class="notif-content">';
            $html .= '<h6 class="notif-title">' . $notif['title'] . '</h6>';
            $html .= '<p class="notif-message">' . $notif['message'] . '</p>';
            $html .= '<small class="notif-time">' . $time . '</small>';
            $html .= '</div>';
            $html .= '</div>';
        }
    }
    
    $html .= '</div>';
    $html .= '<div class="notif-dropdown-footer">';
    $html .= '<a href="' . BASE_URL . '/seeker/notifications.php" class="btn btn-sm btn-primary btn-block rounded-pill">View All Notifications</a>';
    $html .= '</div>';
    $html .= '</div>';
    
    return $html;
}

/**
 * Simple time ago function
 */
function time_ago($datetime) {
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    
    if ($diff->y > 0) return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
    if ($diff->m > 0) return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    if ($diff->d > 0) return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
    if ($diff->h > 0) return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    if ($diff->i > 0) return $diff->i . ' min' . ($diff->i > 1 ? 's' : '') . ' ago';
    return 'Just now';
}
?>
