<?php
session_start();
if (!isset($_SESSION['admin_username'])) {
    echo '<script>alert("You are logged out!"); window.location.href="admin_login.php";</script>';
    exit();
}

require_once 'dbcon.php';
include 'includes/functions.php';
include 'header.php';

$admin_username = $_SESSION['admin_username'];
$admin_id = $_SESSION['admin_id'] ?? 0;
if (!$admin_id) {
    $aq = mysqli_query($con, "SELECT id FROM admin_login WHERE admin_user_name = '" . mysqli_real_escape_string($con, $admin_username) . "' LIMIT 1");
    if ($aq && $arow = mysqli_fetch_assoc($aq)) {
        $admin_id = (int)$arow['id'];
        $_SESSION['admin_id'] = $admin_id;
    }
}

if (isset($_GET['mark_read'])) {
    mark_read($con, intval($_GET['mark_read']));
    header('Location: notifications.php');
    exit;
}
if (isset($_GET['mark_all'])) {
    mark_all_read($con, 'admin', $admin_id);
    header('Location: notifications.php');
    exit;
}
if (isset($_GET['delete'])) {
    delete_notification($con, intval($_GET['delete']));
    header('Location: notifications.php');
    exit;
}

$notifications = get_notifications($con, 'admin', $admin_id, 50);
$unread_count = get_unread_count($con, 'admin', $admin_id);

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

<style>
    @keyframes an-reveal { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: none; } }
    .an-reveal { opacity: 0; }
    .an-reveal.nd-in { animation: an-reveal .5s ease forwards; }

    .an-wrap { padding: 0 0 40px; }
    .an-hero {
        position: relative;
        margin-top: -72px;
        padding: 96px 0 84px;
        background: linear-gradient(120deg, #4f46e5 0%, #7c3aed 55%, #0ea5e9 120%);
        overflow: hidden;
    }
    .an-hero::before, .an-hero::after {
        content: '';
        position: absolute;
        border-radius: 50%;
    }
    .an-hero::before { top: -120px; right: -60px; width: 360px; height: 360px; background: radial-gradient(circle, rgba(255,255,255,0.14) 0%, transparent 70%); }
    .an-hero::after { bottom: -140px; left: 12%; width: 320px; height: 320px; background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%); }
    .an-hero-inner { position: relative; z-index: 2; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 18px; }
    .an-hero h1 { color: #fff; font-size: 2rem; font-weight: 800; letter-spacing: -0.5px; margin: 0 0 6px; }
    .an-hero h1 i { font-size: 1.4rem; margin-right: 6px; opacity: .9; }
    .an-hero .an-hero-sub { color: rgba(255,255,255,0.82); margin: 0; font-size: .98rem; }
    .an-markall {
        display: inline-flex; align-items: center; gap: 8px;
        padding: 10px 18px; border-radius: 12px;
        background: rgba(255,255,255,0.16); border: 1px solid rgba(255,255,255,0.26);
        color: #fff; font-size: .84rem; font-weight: 700; text-decoration: none;
        transition: all .25s ease;
    }
    .an-markall:hover { background: rgba(255,255,255,0.28); color: #fff; text-decoration: none; transform: translateY(-2px); }

    .an-card {
        background: var(--bg-card);
        border: 1px solid var(--border-light);
        border-radius: 20px;
        box-shadow: var(--shadow-sm);
        overflow: hidden;
    }

    .an-list { display: flex; flex-direction: column; }
    .an-item {
        position: relative;
        display: flex; align-items: flex-start; gap: 16px;
        padding: 18px 22px;
        border-bottom: 1px solid var(--border-light);
        transition: background .2s ease, box-shadow .2s ease;
        animation: an-reveal .45s ease backwards;
    }
    .an-item:hover { background: var(--bg-hover); }
    .an-item:last-child { border-bottom: none; }
    .an-item.unread { background: rgba(99,102,241,.06); }
    .an-item.unread:hover { background: rgba(99,102,241,.09); }
    .an-item.unread::before {
        content: '';
        position: absolute; left: 0; top: 18px; bottom: 18px;
        width: 4px; border-radius: 0 4px 4px 0;
        background: linear-gradient(180deg, #6366f1, #8b5cf6);
    }
    .an-ico {
        width: 46px; height: 46px; border-radius: 13px; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center; font-size: 1.05rem;
    }
    .an-body { flex: 1; min-width: 0; }
    .an-body h5 { font-weight: 700; font-size: .93rem; color: var(--text); margin: 0 0 4px; }
    .an-body p { font-size: .84rem; color: var(--text-muted); margin: 0 0 8px; line-height: 1.5; word-break: break-word; }
    .an-meta { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
    .an-badge {
        font-size: .68rem; font-weight: 700; padding: 3px 10px;
        border-radius: 999px; text-transform: uppercase; letter-spacing: .3px;
    }
    .an-time { font-size: .74rem; color: var(--text-light); font-weight: 600; }
    .an-actions { display: flex; gap: 6px; flex-shrink: 0; }
    .an-act {
        width: 32px; height: 32px; border-radius: 9px;
        display: flex; align-items: center; justify-content: center;
        font-size: .8rem; color: var(--text-muted); text-decoration: none;
        transition: all .2s ease;
    }
    .an-act:hover { background: var(--bg-hover); color: var(--primary); text-decoration: none; }
    .an-act.del:hover { background: #fee2e2; color: #ef4444; }
    .an-unread-dot {
        width: 9px; height: 9px; border-radius: 50%;
        background: linear-gradient(135deg, #6366f1, #8b5cf6);
        position: absolute; top: 22px; right: 20px;
        box-shadow: 0 0 0 3px rgba(99,102,241,.15);
    }

    .an-empty {
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        padding: 64px 20px; color: var(--text-muted); gap: 10px; text-align: center;
    }
    .an-empty i { font-size: 2.6rem; opacity: .4; }
    .an-empty h5 { color: var(--text); font-weight: 700; margin: 4px 0 0; }

    @media (max-width: 767px) {
        .an-hero { padding: 84px 0 64px; }
        .an-hero h1 { font-size: 1.5rem; }
        .an-item { flex-wrap: wrap; }
    }
</style>

<div class="an-wrap">
    <!-- Hero -->
    <div class="an-hero">
        <div class="container">
            <div class="an-hero-inner">
                <div>
                    <h1><i class="fas fa-bell"></i>Notifications</h1>
                    <p class="an-hero-sub"><?php echo $unread_count; ?> unread notification<?php echo $unread_count != 1 ? 's' : ''; ?></p>
                </div>
                <?php if ($unread_count > 0): ?>
                    <a href="notifications.php?mark_all=1" class="an-markall"><i class="fas fa-check-double"></i> Mark All Read</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="container" style="margin-top: -34px;">
        <div class="row">
            <div class="col-12 an-reveal">
                <div class="an-card">
                    <?php if (empty($notifications)): ?>
                        <div class="an-empty">
                            <i class="fas fa-bell-slash"></i>
                            <h5>No Notifications Yet</h5>
                            <p class="mb-0">When you receive notifications, they'll appear here.</p>
                        </div>
                    <?php else: ?>
                        <div class="an-list">
                            <?php
                            $idx = 0;
                            foreach ($notifications as $notif):
                                $icon = isset($type_icons[$notif['notification_type']]) ? $type_icons[$notif['notification_type']] : 'fa-bell';
                                $color = isset($type_colors[$notif['notification_type']]) ? $type_colors[$notif['notification_type']] : '#6366f1';
                                $label = isset($type_labels[$notif['notification_type']]) ? $type_labels[$notif['notification_type']] : 'System';
                                $read_class = $notif['is_read'] ? '' : 'unread';
                                $time = time_ago($notif['created_at']);
                                $idx++;
                            ?>
                                <div class="an-item <?php echo $read_class; ?>" style="animation-delay:<?php echo min($idx * 45, 450); ?>ms;">
                                    <?php if (!$notif['is_read']): ?><span class="an-unread-dot"></span><?php endif; ?>
                                    <div class="an-ico" style="background: <?php echo $color; ?>18; color: <?php echo $color; ?>;">
                                        <i class="fas <?php echo $icon; ?>"></i>
                                    </div>
                                    <div class="an-body">
                                        <h5><?php echo htmlspecialchars($notif['title']); ?></h5>
                                        <p><?php echo htmlspecialchars($notif['message']); ?></p>
                                        <div class="an-meta">
                                            <span class="an-badge" style="background: <?php echo $color; ?>14; color: <?php echo $color; ?>;"><?php echo $label; ?></span>
                                            <span class="an-time"><i class="fas fa-clock mr-1"></i><?php echo $time; ?></span>
                                        </div>
                                    </div>
                                    <div class="an-actions">
                                        <?php if (!$notif['is_read']): ?>
                                            <a href="notifications.php?mark_read=<?php echo $notif['id']; ?>" class="an-act" title="Mark as read"><i class="fas fa-check"></i></a>
                                        <?php endif; ?>
                                        <a href="notifications.php?delete=<?php echo $notif['id']; ?>" class="an-act del" title="Delete" onclick="return confirm('Delete this notification?')"><i class="fas fa-trash"></i></a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="text-center" style="padding: 22px 0 20px; color: var(--text-muted); font-size: .82rem;">
            NovaHire Admin &middot; Notifications
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.an-reveal:not(.nd-in)').forEach(function (el) { el.classList.add('nd-in'); });
    });
</script>

</body>
</html>
