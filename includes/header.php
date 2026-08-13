<?php
require_once __DIR__ . '/bootstrap.php';
require_seeker_login();
require_once dirname(__DIR__) . '/ai/config.php';
require_once dirname(__DIR__) . '/ai/helpers.php';

$user_id = $_SESSION['id'];
$unread_notifs = get_unread_count($con, 'user', $user_id);
$unread_messages = get_unread_message_count($con, 'user', $user_id);
$unread_total = $unread_notifs + $unread_messages;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <title>NovaHire</title>
  <?php include __DIR__ . '/links.php' ?>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
  <?php echo ai_css_link(); ?>
</head>
<body class="dashboard-body">

<nav class="navbar navbar-expand-lg glass-nav fixed-top" id="mainNav">
  <div class="container-fluid px-4 px-lg-5 custom-nav-container">
      <a class="navbar-brand d-flex align-items-center order-1" href="seeker_dashboard.php">
        <div class="brand-icon mr-2"><i class="fas fa-layer-group"></i></div>
        <span class="brand-text">Nova<span class="brand-highlight">Hire</span></span>
      </a>

      <button class="navbar-toggler custom-toggler order-3 order-lg-2" type="button" data-toggle="collapse" data-target="#collapsibleNavbar" aria-controls="collapsibleNavbar" aria-expanded="false" aria-label="Toggle navigation" id="navToggler">
        <i class="fas fa-bars" id="togglerIcon"></i>
      </button>

      <div class="collapse navbar-collapse justify-content-center order-lg-2" id="collapsibleNavbar">
        <?php $nav_page = basename($_SERVER['PHP_SELF']); ?>
        <ul class="navbar-nav mx-auto center-menu">
          <li class="nav-item">
            <a class="nav-link <?php echo $nav_page == 'seeker_dashboard.php' ? 'active' : ''; ?>" href="seeker_dashboard.php">Home</a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?php echo $nav_page == 'profile.php' ? 'active' : ''; ?>" href="profile.php">Profile</a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?php echo in_array($nav_page, ['browse_jobs.php', 'job_details.php']) ? 'active' : ''; ?>" href="browse_jobs.php">Jobs</a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?php echo in_array($nav_page, ['available_companies.php', 'company_job_application.php', 'company_job_quiz.php', 'quiz.php']) ? 'active' : ''; ?>" href="available_companies.php">Companies</a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?php echo in_array($nav_page, ['my_application.php', 'application.php']) ? 'active' : ''; ?>" href="my_application.php">Applications</a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?php echo $nav_page == 'live_chat.php' ? 'active' : ''; ?>" href="live_chat.php" style="color: var(--primary); font-weight: 600;">
              <i class="fas fa-comment-dots mr-1"></i>Live Chat
              <span class="lc-nav-badge" id="lcNotifBadge" style="display:none;">0</span>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?php echo strpos($nav_page, 'ai_') !== false || $nav_page == 'grooming.php' ? 'active' : ''; ?>" href="ai_hub.php" style="color: var(--primary); font-weight: 600;">
              <i class="fas fa-robot mr-1"></i>AI Center
            </a>
          </li>
        </ul>
      </div>

      <ul class="navbar-nav align-items-center right-menu order-2 order-lg-3">
            <li class="nav-item dropdown mr-2">
                <a class="nav-link nav-icon-btn d-flex align-items-center justify-content-center position-relative" href="#" id="notifDropdownToggle" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="Notifications">
                    <i class="fas fa-bell"></i>
                    <?php if ($unread_notifs > 0): ?>
                        <span class="notification-badge" id="notifBadge"><?php echo $unread_notifs; ?></span>
                    <?php endif; ?>
                </a>
                <div class="dropdown-menu dropdown-menu-right notif-dropdown shadow-lg border-0" aria-labelledby="notifDropdownToggle">
                    <div class="notif-dropdown-header">
                        <h6 class="m-0 font-weight-bold">Notifications</h6>
                        <?php if ($unread_notifs > 0): ?>
                            <button class="btn btn-sm btn-link p-0" onclick="markAllNotificationsRead()" style="font-size: 0.78rem; color: var(--primary); font-weight: 600;">Mark all read</button>
                        <?php endif; ?>
                    </div>
                    <div class="notif-dropdown-body" id="notifList">
                        <?php
                        $notifications = get_notifications($con, 'user', $user_id, 6);
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
                        
                        if (empty($notifications)):
                        ?>
                            <div class="notif-empty">
                                <i class="fas fa-bell-slash fa-2x mb-2" style="color: var(--text-light);"></i>
                                <p class="mb-0" style="font-size: 0.88rem; color: var(--text-muted);">No notifications yet</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($notifications as $notif): 
                                $icon = isset($type_icons[$notif['notification_type']]) ? $type_icons[$notif['notification_type']] : 'fa-bell';
                                $color = isset($type_colors[$notif['notification_type']]) ? $type_colors[$notif['notification_type']] : '#6366f1';
                                $read_class = $notif['is_read'] ? '' : 'notif-unread';
                                $time = time_ago($notif['created_at']);
                            ?>
                                <div class="notif-item <?php echo $read_class; ?>" onclick="markNotificationRead(<?php echo $notif['id']; ?>, this)">
                                    <div class="notif-icon" style="background: <?php echo $color; ?>15; color: <?php echo $color; ?>;">
                                        <i class="fas <?php echo $icon; ?>"></i>
                                    </div>
                                    <div class="notif-content">
                                        <h6 class="notif-title"><?php echo htmlspecialchars($notif['title']); ?></h6>
                                        <p class="notif-message"><?php echo $notif['message']; ?></p>
                                        <small class="notif-time"><?php echo $time; ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div class="notif-dropdown-footer">
                        <a href="notifications.php" class="btn btn-sm btn-primary btn-block rounded-pill">View All</a>
                    </div>
                </div>
            </li>
            
            <li class="nav-item dropdown mr-2">
                <a class="nav-link nav-icon-btn d-flex align-items-center justify-content-center position-relative" href="#" id="msgDropdownToggle" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="Messages">
                    <i class="fas fa-envelope"></i>
                    <?php if ($unread_messages > 0): ?>
                        <span class="notification-badge msg-badge"><?php echo $unread_messages; ?></span>
                    <?php endif; ?>
                </a>
                <div class="dropdown-menu dropdown-menu-right msg-dropdown shadow-lg border-0" aria-labelledby="msgDropdownToggle">
                    <div class="notif-dropdown-header">
                        <h6 class="m-0 font-weight-bold">Messages</h6>
                        <?php if ($unread_messages > 0): ?>
                            <a href="message_center.php" class="btn btn-sm btn-link p-0" style="font-size: 0.78rem; color: var(--primary); font-weight: 600;">Inbox</a>
                        <?php endif; ?>
                    </div>
                    <div class="notif-dropdown-body">
                        <?php
                        $recent_messages_q = mysqli_query($con, "SELECT m.*, 
                            CASE 
                                WHEN m.sender_type = 'company' THEN (SELECT company_name FROM companies WHERE id = m.sender_id)
                                WHEN m.sender_type = 'user' THEN (SELECT username FROM user_info WHERE id = m.sender_id)
                                ELSE 'System'
                            END as sender_name
                            FROM messages m
                            WHERE (m.receiver_type = 'user' AND m.receiver_id = $user_id AND m.is_deleted_by_receiver = 0)
                            ORDER BY m.created_at DESC LIMIT 5");
                        
                        if (mysqli_num_rows($recent_messages_q) === 0):
                        ?>
                            <div class="notif-empty">
                                <i class="fas fa-envelope-open fa-2x mb-2" style="color: var(--text-light);"></i>
                                <p class="mb-0" style="font-size: 0.88rem; color: var(--text-muted);">No messages yet</p>
                            </div>
                        <?php else: ?>
                            <?php while ($msg = mysqli_fetch_assoc($recent_messages_q)):
                                $msg_read_class = $msg['is_read'] ? '' : 'notif-unread';
                                $time = time_ago($msg['created_at']);
                            ?>
                                <div class="notif-item <?php echo $msg_read_class; ?>" onclick="window.location.href='message_center.php?with=<?php echo $msg['sender_type'] . '_' . $msg['sender_id']; ?>'">
                                    <div class="notif-icon" style="background: rgba(139, 92, 246, 0.1); color: #8b5cf6;">
                                        <i class="fas fa-user"></i>
                                    </div>
                                    <div class="notif-content">
                                        <h6 class="notif-title"><?php echo htmlspecialchars($msg['sender_name']); ?></h6>
                                        <p class="notif-message"><?php echo htmlspecialchars(substr($msg['subject'], 0, 50)); ?></p>
                                        <small class="notif-time"><?php echo $time; ?></small>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </div>
                    <div class="notif-dropdown-footer">
                        <a href="message_center.php" class="btn btn-sm btn-primary btn-block rounded-pill">Open Message Center</a>
                    </div>
                </div>
            </li>
            
            <li class="nav-item dropdown mr-2 theme-nav-item">
                <a class="nav-link nav-icon-btn d-flex align-items-center justify-content-center" href="#" id="themeDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="Switch Theme">
                    <i class="fas fa-swatchbook"></i>
                </a>
                <div class="dropdown-menu dropdown-menu-right theme-dropdown" aria-labelledby="themeDropdown">
                    <h6 class="dropdown-header text-uppercase font-weight-bold pl-3 mb-2" style="font-size: 0.65rem; color: var(--text-light); letter-spacing: 0.5px;">Theme</h6>
                    <a class="dropdown-item" href="#" onclick="setTheme('default'); return false;"><span class="dot mr-2" style="background: #4f46e5;"></span>Default</a>
                    <a class="dropdown-item" href="#" onclick="setTheme('ocean'); return false;"><span class="dot mr-2" style="background: #0891b2;"></span>Ocean</a>
                    <a class="dropdown-item" href="#" onclick="setTheme('sunset'); return false;"><span class="dot mr-2" style="background: #ea580c;"></span>Sunset</a>
                    <a class="dropdown-item" href="#" onclick="setTheme('dark'); return false;"><span class="dot mr-2" style="background: #1e293b;"></span>Dark</a>
                </div>
            </li>
            
             <li class="nav-item dropdown">
                <a class="nav-link user-pill dropdown-toggle" href="#" id="userDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                   <div class="user-avatar-sm">
                        <i class="fas fa-user"></i>
                   </div>
                   <span class="user-name-text"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                </a>
                <div class="dropdown-menu dropdown-menu-right user-menu-dropdown" aria-labelledby="userDropdown">
                    <div class="px-3 py-2 mb-1" style="background: var(--bg-hover); border-radius: var(--radius-sm);">
                        <small class="d-block text-uppercase font-weight-bold" style="font-size: 0.6rem; color: var(--text-light); letter-spacing: 0.5px;">Signed in as</small>
                        <h6 class="mb-0 font-weight-bold" style="font-size: 0.88rem; color: var(--text);"><?php echo htmlspecialchars($_SESSION['username']); ?></h6>
                    </div>
                    <a class="dropdown-item" href="profile.php"><i class="fas fa-user-circle mr-2" style="color: var(--text-light);"></i> My Profile</a>
                    <a class="dropdown-item" href="my_application.php"><i class="fas fa-file-alt mr-2" style="color: var(--text-light);"></i> Applications</a>
                    <a class="dropdown-item" href="message_center.php"><i class="fas fa-envelope mr-2" style="color: var(--text-light);"></i> Messages <?php if ($unread_messages > 0): ?><span class="badge badge-primary ml-1"><?php echo $unread_messages; ?></span><?php endif; ?></a>
                    <a class="dropdown-item" href="notifications.php"><i class="fas fa-bell mr-2" style="color: var(--text-light);"></i> Notifications <?php if ($unread_notifs > 0): ?><span class="badge badge-primary ml-1"><?php echo $unread_notifs; ?></span><?php endif; ?></a>
                    <div class="dropdown-divider" style="border-color: var(--border-light);"></div>
                    <a class="dropdown-item" href="<?php echo BASE_URL; ?>/auth/logout.php" style="color: var(--danger);"><i class="fas fa-sign-out-alt mr-2"></i> Logout</a>
                </div>
            </li>
        </ul>
  </div>
</nav>

<div style="margin-top: 80px;"></div>

<div id="toastContainer" class="toast-container"></div>

<script>
    function setTheme(themeName) {
        document.body.setAttribute('data-theme', themeName);
        localStorage.setItem('theme', themeName);
    }

    (function() {
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme) {
            document.body.setAttribute('data-theme', savedTheme);
        }
    })();
    
    function markNotificationRead(notifId, element) {
        fetch('api/mark_notification_read.php?id=' + notifId)
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    element.classList.remove('notif-unread');
                    updateNotifBadge(-1);
                }
            });
    }
    
    function markAllNotificationsRead() {
        fetch('api/mark_all_read.php')
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    document.querySelectorAll('.notif-unread').forEach(el => el.classList.remove('notif-unread'));
                    const badge = document.getElementById('notifBadge');
                    if (badge) badge.remove();
                }
            });
    }
    
    function updateNotifBadge(change) {
        const badge = document.getElementById('notifBadge');
        if (badge) {
            let count = parseInt(badge.textContent) + change;
            if (count <= 0) {
                badge.remove();
            } else {
                badge.textContent = count;
            }
        }
    }
    
    function showToast(type, title, message, duration = 5000) {
        const container = document.getElementById('toastContainer');
        const icons = {
            success: 'fa-check-circle',
            info: 'fa-info-circle',
            warning: 'fa-exclamation-triangle',
            error: 'fa-times-circle'
        };
        
        const toast = document.createElement('div');
        toast.className = 'toast-notification toast-' + type;
        toast.innerHTML = `
            <div class="toast-icon"><i class="fas ${icons[type] || icons.info}"></i></div>
            <div class="toast-body">
                <h6>${title}</h6>
                <p>${message}</p>
            </div>
            <button class="toast-close" onclick="this.parentElement.remove()">&times;</button>
        `;
        
        container.appendChild(toast);
        
        setTimeout(() => {
            toast.style.animation = 'toastSlideOut 0.3s ease forwards';
            setTimeout(() => toast.remove(), 300);
        }, duration);
    }
    
    setInterval(function() {
        fetch('api/get_notification_count.php')
            .then(r => r.json())
            .then(data => {
                if (data.count !== undefined) {
                    const badge = document.getElementById('notifBadge');
                    if (data.count > 0) {
                        if (badge) {
                            badge.textContent = data.count;
                        } else {
                            location.reload();
                        }
                    } else if (badge) {
                        badge.remove();
                    }
                }
            });
    }, 30000);

    /* ═══ Live Chat alerts — never miss a message ═══ */
    function lcPoll() {
        var base = (window.APP_URL || '') + '/api/live_chat_alerts.php';
        fetch(base, { cache: 'no-store' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success || !data.alerts || !data.alerts.length) return;
                var badge = document.getElementById('lcNotifBadge');
                var missed = 0;
                data.alerts.forEach(function (a) {
                    var activeId = (window.LC_ACTIVE_ID !== undefined && window.LC_ACTIVE_ID !== null) ? parseInt(window.LC_ACTIVE_ID, 10) : null;
                    var isOpenChat = (activeId !== null && parseInt(a.sender_id, 10) === activeId);
                    if (isOpenChat) return;
                    missed++;
                    var logo = (a.sender_logo && a.sender_logo !== '') ? (base.replace('/api/live_chat_alerts.php', '/uploads/company_logos/') + a.sender_logo) : '';
                    if (typeof showToast === 'function') {
                        showToast('info', a.sender_name, a.message, 7000);
                    }
                });
                if (missed > 0) {
                    var prev = badge ? parseInt(badge.textContent, 10) || 0 : 0;
                    var total = prev + missed;
                    if (badge) {
                        badge.textContent = total;
                        badge.style.display = 'inline-flex';
                    }
                }
            })
            .catch(function () {});
    }
    setTimeout(lcPoll, 500);
    setInterval(lcPoll, 10000);

    /* ═══ Navbar: scrolled state + hamburger animation ═══ */
    (function () {
        var nav = document.getElementById('mainNav');
        function onScroll() {
            if (nav) nav.classList.toggle('nav-scrolled', window.scrollY > 12);
        }
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();

        var icon = document.getElementById('togglerIcon');
        var collapseEl = document.getElementById('collapsibleNavbar');
        if (collapseEl) {
            $(collapseEl).on('show.bs.collapse', function () {
                if (icon) icon.className = 'fas fa-times';
            }).on('hidden.bs.collapse', function () {
                if (icon) icon.className = 'fas fa-bars';
            });
        }
    })();
</script>
<script>window.APP_URL = '<?php echo BASE_URL; ?>';</script>
<script src="<?php echo BASE_URL; ?>/ai/assets/js/chat.js"></script>
<?php ai_chat_widget(); ?>
</body>
</html>
