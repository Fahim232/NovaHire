<?php
// Core setup: session, DB, BASE_URL, helpers
require_once __DIR__ . '/../includes/bootstrap.php';
if (!isset($_SESSION['id'])) {
    header('location: ' . BASE_URL . '/auth/login.php');
    exit();
}
require_once __DIR__ . '/../admin/dbcon.php';
require_once __DIR__ . '/../includes/functions.php';

$user_id = $_SESSION['id'];

// Handle mark as read
if (isset($_GET['mark_read'])) {
    mark_read($con, intval($_GET['mark_read']));
    header('Location: notifications.php');
    exit;
}

// Handle mark all as read
if (isset($_GET['mark_all'])) {
    mark_all_read($con, 'user', $user_id);
    header('Location: notifications.php');
    exit;
}

// Handle delete
if (isset($_GET['delete'])) {
    delete_notification($con, intval($_GET['delete']));
    header('Location: notifications.php');
    exit;
}

// Get all notifications
$notifications = get_notifications($con, 'user', $user_id, 50);
$unread_count = get_unread_count($con, 'user', $user_id);

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
$type_labels = [
    'application_status' => 'Application Update',
    'new_application' => 'New Application',
    'message' => 'New Message',
    'quiz_result' => 'Quiz Result',
    'job_update' => 'Job Update',
    'system' => 'System',
    'job_recommendation' => 'Job Recommendation',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Notifications | NovaHire</title>
    <?php require_once __DIR__ . '/../includes/links.php'; ?>
    <style>
        .notif-container {
            max-width: 800px;
            margin: 40px auto;
        }
        .notif-page-header {
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            color: white;
            padding: 40px;
            border-radius: 20px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .notif-page-header h1 {
            font-weight: 800;
            font-size: 1.8rem;
            color: white;
            margin: 0;
        }
        .notif-page-header p {
            color: rgba(255,255,255,0.8);
            margin: 5px 0 0;
        }
        .notif-list-item {
            background: white;
            border-radius: 16px;
            padding: 20px 25px;
            margin-bottom: 12px;
            display: flex;
            align-items: flex-start;
            gap: 16px;
            border: 1px solid #f1f5f9;
            transition: all 0.3s;
            position: relative;
        }
        .notif-list-item:hover {
            box-shadow: 0 5px 20px rgba(0,0,0,0.06);
            transform: translateX(3px);
        }
        .notif-list-item.unread {
            background: #eef2ff;
            border-color: #c7d2fe;
        }
        .notif-list-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
        }
        .notif-list-content { flex: 1; }
        .notif-list-content h5 {
            font-weight: 700;
            font-size: 0.95rem;
            margin: 0 0 5px;
            color: #0f172a;
        }
        .notif-list-content p {
            font-size: 0.85rem;
            color: #64748b;
            margin: 0 0 8px;
            line-height: 1.5;
        }
        .notif-list-meta {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .notif-type-badge {
            font-size: 0.7rem;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .notif-list-time {
            font-size: 0.75rem;
            color: #94a3b8;
        }
        .notif-list-actions {
            display: flex;
            gap: 6px;
            flex-shrink: 0;
        }
        .notif-list-actions a {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            color: #94a3b8;
            transition: all 0.2s;
            text-decoration: none;
        }
        .notif-list-actions a:hover {
            background: #f1f5f9;
            color: #4f46e5;
        }
        .notif-list-actions a.delete:hover {
            background: #fee2e2;
            color: #ef4444;
        }
        .unread-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #4f46e5;
            position: absolute;
            top: 25px;
            right: 25px;
        }
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            background: white;
            border-radius: 20px;
            border: 1px solid #f1f5f9;
        }
        .empty-state i { font-size: 4rem; color: #e2e8f0; }
        .empty-state h3 { color: #475569; font-weight: 700; }
        .empty-state p { color: #94a3b8; }
    </style>
</head>
<body>
<!-- Navbar -->
<nav class="navbar navbar-expand-lg glass-nav fixed-top">
  <div class="container-fluid px-5 custom-nav-container">
      <a class="navbar-brand d-flex align-items-center" href="seeker_dashboard.php">
        <div class="brand-icon mr-2"><i class="fas fa-layer-group"></i></div>
        <span class="brand-text">Nova<span class="brand-highlight">Hire</span></span>
      </a>
      <button class="navbar-toggler custom-toggler" type="button" data-toggle="collapse" data-target="#collapsibleNavbar">
        <span class="fas fa-bars fa-lg text-dark"></span>
      </button>
      <div class="collapse navbar-collapse justify-content-between" id="collapsibleNavbar">
        <ul class="navbar-nav mx-auto center-menu">
           <li class="nav-item"><a class="nav-link" href="seeker_dashboard.php">Home</a></li>
           <li class="nav-item"><a class="nav-link" href="profile.php">Profile</a></li>
           <li class="nav-item"><a class="nav-link" href="browse_jobs.php">Browse Jobs</a></li>
           <li class="nav-item"><a class="nav-link" href="my_application.php">Applications</a></li>
           <li class="nav-item"><a class="nav-link" href="message_center.php">Messages</a></li>
        </ul>
        <ul class="navbar-nav align-items-center right-menu">
            <li class="nav-item dropdown">
                <a class="nav-link user-pill dropdown-toggle" href="#" id="userDropdown" role="button" data-toggle="dropdown">
                   <div class="user-avatar-sm"><i class="fas fa-user"></i></div>
                   <span class="user-name-text"><?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></span>
                </a>
                <div class="dropdown-menu dropdown-menu-right shadow-lg border-0 user-menu-dropdown" aria-labelledby="userDropdown">
                    <a class="dropdown-item" href="profile.php"><i class="fas fa-user-circle mr-2 text-muted"></i> My Profile</a>
                    <a class="dropdown-item" href="my_application.php"><i class="fas fa-file-alt mr-2 text-muted"></i> Applications</a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item text-danger" href="<?php echo BASE_URL; ?>/auth/logout.php"><i class="fas fa-sign-out-alt mr-2"></i> Logout</a>
                </div>
            </li>
        </ul>
      </div>
  </div>
</nav>
<div style="margin-top: 100px;"></div>

<div class="container notif-container">
    <div class="notif-page-header">
        <div>
            <h1><i class="fas fa-bell mr-3"></i>Notifications</h1>
            <p>You have <?php echo $unread_count; ?> unread notification<?php echo $unread_count != 1 ? 's' : ''; ?></p>
        </div>
        <?php if ($unread_count > 0): ?>
            <a href="notifications.php?mark_all=1" class="btn btn-light rounded-pill font-weight-bold">
                <i class="fas fa-check-double mr-2"></i>Mark All Read
            </a>
        <?php endif; ?>
    </div>
    
    <?php if (empty($notifications)): ?>
        <div class="empty-state">
            <i class="fas fa-bell-slash d-block mb-3"></i>
            <h3>No Notifications Yet</h3>
            <p class="mb-0">When you receive notifications, they'll appear here.</p>
        </div>
    <?php else: ?>
        <?php foreach ($notifications as $notif):
            $icon = isset($type_icons[$notif['notification_type']]) ? $type_icons[$notif['notification_type']] : 'fa-bell';
            $color = isset($type_colors[$notif['notification_type']]) ? $type_colors[$notif['notification_type']] : '#6366f1';
            $label = isset($type_labels[$notif['notification_type']]) ? $type_labels[$notif['notification_type']] : 'System';
            $read_class = $notif['is_read'] ? '' : 'unread';
            $time = time_ago($notif['created_at']);
        ?>
            <div class="notif-list-item <?php echo $read_class; ?>">
                <?php if (!$notif['is_read']): ?><div class="unread-dot"></div><?php endif; ?>
                
                <div class="notif-list-icon" style="background: <?php echo $color; ?>20; color: <?php echo $color; ?>;">
                    <i class="fas <?php echo $icon; ?>"></i>
                </div>
                
                <div class="notif-list-content">
                    <h5><?php echo htmlspecialchars($notif['title']); ?></h5>
                    <p><?php echo $notif['message']; ?></p>
                    <div class="notif-list-meta">
                        <span class="notif-type-badge" style="background: <?php echo $color; ?>15; color: <?php echo $color; ?>;"><?php echo $label; ?></span>
                        <span class="notif-list-time"><i class="fas fa-clock mr-1"></i><?php echo $time; ?></span>
                    </div>
                </div>
                
                <div class="notif-list-actions">
                    <?php if (!$notif['is_read']): ?>
                        <a href="notifications.php?mark_read=<?php echo $notif['id']; ?>" title="Mark as read">
                            <i class="fas fa-check"></i>
                        </a>
                    <?php endif; ?>
                    <a href="notifications.php?delete=<?php echo $notif['id']; ?>" class="delete" title="Delete" onclick="return confirm('Delete this notification?')">
                        <i class="fas fa-trash"></i>
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
</body>
</html>
