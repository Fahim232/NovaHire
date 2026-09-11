<?php
require_once __DIR__ . '/bootstrap.php';
require_seeker_login();
require_once dirname(__DIR__) . '/ai/config.php';
require_once dirname(__DIR__) . '/ai/helpers.php';

$user_id = $_SESSION['id'];
require_once __DIR__ . '/premium.php';
$nh_is_pro = is_user_pro($con, $user_id);
$unread_notifs = get_unread_count($con, 'user', $user_id);
$unread_messages = get_unread_message_count($con, 'user', $user_id);
$unread_total = $unread_notifs + $unread_messages;

$nav_page = basename($_SERVER['PHP_SELF']);
$username = htmlspecialchars($_SESSION['username']);
$initial = strtoupper(substr($_SESSION['username'], 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>NovaHire</title>
  <?php include __DIR__ . '/links.php' ?>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
  <link rel="manifest" href="/Job-portal-and-grooming/public/manifest.json">
  <meta name="theme-color" content="#1a56db">
  <?php echo ai_css_link(); ?>
  <style>
    /* ══════════════════════════════════════════════
       MODERN NAVBAR — Complete Redesign
       ══════════════════════════════════════════════ */
    .nh-topbar{background:linear-gradient(135deg,var(--primary),var(--secondary));padding:5px 0;color:#fff;font-size:.75rem;}
    .nh-topbar a{color:rgba(255,255,255,.85);text-decoration:none;}
    .nh-topbar a:hover{color:#fff;}
    .nh-topbar .container-fluid{display:flex;justify-content:flex-end;align-items:center;gap:18px;}

    .nh-nav{background:rgba(255,255,255,.88);backdrop-filter:blur(20px) saturate(180%);-webkit-backdrop-filter:blur(20px) saturate(180%);border-bottom:1px solid rgba(226,232,240,.5);padding:0;position:sticky;top:0;z-index:9990;transition:all .3s;}
    .nh-nav.scrolled{box-shadow:0 8px 32px -8px rgba(26,86,219,.12);background:rgba(255,255,255,.95);}
    [data-theme="dark"] .nh-nav{background:rgba(15,23,42,.88);border-bottom-color:rgba(51,65,85,.5);}
    [data-theme="dark"] .nh-nav.scrolled{background:rgba(15,23,42,.95);box-shadow:0 8px 32px -8px rgba(0,0,0,.5);}

    .nh-nav-inner{max-width:1340px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;padding:0 24px;height:64px;gap:12px;}

    /* Brand */
    .nh-brand{display:flex;align-items:center;gap:10px;text-decoration:none;}
    .nh-brand-icon{width:38px;height:38px;background:linear-gradient(135deg,var(--primary),var(--secondary));border-radius:12px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:1rem;box-shadow:0 4px 12px rgba(26,86,219,.3);transition:all .3s cubic-bezier(.34,1.56,.64,1);position:relative;overflow:hidden;}
    .nh-brand-icon::before{content:'';position:absolute;inset:0;background:linear-gradient(135deg,rgba(255,255,255,.25),transparent 50%);border-radius:inherit;}
    .nh-brand:hover .nh-brand-icon{transform:rotate(-8deg) scale(1.08);box-shadow:0 6px 20px rgba(26,86,219,.45);}
    .nh-brand-text{font-family:'Plus Jakarta Sans',sans-serif;font-size:1.35rem;font-weight:800;letter-spacing:-.6px;color:var(--text);text-decoration:none;}
    .nh-brand-text span{background:linear-gradient(135deg,var(--primary),var(--secondary));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;}

    /* Center Nav */
    .nh-center{display:flex;align-items:center;gap:2px;}
    .nh-link{font-family:'Plus Jakarta Sans',sans-serif;color:var(--text-muted);font-weight:600;padding:8px 16px;border-radius:9999px;font-size:.87rem;text-decoration:none;transition:all .25s;display:flex;align-items:center;gap:7px;position:relative;white-space:nowrap;}
    .nh-link i{font-size:.8rem;opacity:.65;transition:all .25s;}
    .nh-link::after{content:'';position:absolute;left:50%;bottom:2px;width:0;height:2.5px;border-radius:3px;background:linear-gradient(90deg,var(--primary),var(--secondary));transform:translateX(-50%);transition:width .3s cubic-bezier(.34,1.56,.64,1);}
    .nh-link:hover{color:var(--primary);background:rgba(26,86,219,.06);}
    .nh-link:hover::after{width:40%;}
    .nh-link:hover i{opacity:1;transform:scale(1.1);}
    .nh-link.active{color:var(--primary);background:rgba(26,86,219,.1);font-weight:700;}
    .nh-link.active::after{width:50%;}
    .nh-link.active i{opacity:1;}

    /* Special Links */
    .nh-link-special{background:linear-gradient(135deg,rgba(26,86,219,.08),rgba(14,165,233,.08));color:var(--primary);font-weight:700;border:1px solid rgba(26,86,219,.12);}
    .nh-link-special:hover{background:linear-gradient(135deg,rgba(26,86,219,.14),rgba(14,165,233,.14));border-color:rgba(26,86,219,.2);transform:translateY(-1px);box-shadow:0 4px 12px rgba(26,86,219,.12);}

    /* More Dropdown */
    .nh-more{position:relative;}
    .nh-more-menu{position:absolute;top:calc(100% + 8px);right:0;background:var(--bg-card);border:1px solid var(--border);border-radius:16px;padding:8px;min-width:260px;box-shadow:0 20px 50px -12px rgba(15,23,42,.18);opacity:0;visibility:hidden;transform:translateY(-8px);transition:all .2s;z-index:9999;}
    .nh-more:hover .nh-more-menu,.nh-more.open .nh-more-menu{opacity:1;visibility:visible;transform:translateY(0);}
    .nh-more-header{font-size:.63rem;text-transform:uppercase;letter-spacing:.6px;font-weight:800;color:var(--text-light);padding:8px 14px 4px;}
    .nh-more-item{display:flex;align-items:center;gap:10px;padding:9px 14px;border-radius:10px;font-weight:600;font-size:.86rem;color:var(--text);text-decoration:none;transition:all .2s;}
    .nh-more-item:hover{background:var(--bg-hover);color:var(--primary);transform:translateX(3px);}
    .nh-more-item i{width:20px;text-align:center;font-size:.85rem;}
    .nh-more-divider{height:1px;background:var(--border-light);margin:6px 10px;}
    .nh-more-pro{background:linear-gradient(135deg,#d97706,#f97316);color:#fff !important;font-weight:800;}
    .nh-more-pro:hover{background:linear-gradient(135deg,#d97706,#f97316);color:#fff !important;opacity:.94;}

    /* Right Actions */
    .nh-right{display:flex;align-items:center;gap:6px;}
    .nh-icon-btn{width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:var(--text-muted);text-decoration:none;transition:all .25s;position:relative;font-size:1rem;background:transparent;border:none;cursor:pointer;}
    .nh-icon-btn:hover{background:rgba(26,86,219,.08);color:var(--primary);transform:translateY(-1px);}
    .nh-icon-btn:active{transform:scale(.92);}

    /* Badges */
    .nh-badge{position:absolute;top:2px;right:1px;background:linear-gradient(135deg,#dc2626,#f97316);color:#fff;font-size:.58rem;font-weight:800;min-width:18px;height:18px;border-radius:50%;display:flex;align-items:center;justify-content:center;padding:0 4px;border:2px solid var(--bg-card);box-shadow:0 2px 6px rgba(239,68,68,.35);animation:nhPop .3s cubic-bezier(.34,1.56,.64,1);}
    .nh-badge-msg{background:linear-gradient(135deg,#06b6d4,#38bdf8);box-shadow:0 2px 6px rgba(6,182,212,.35);}
    @keyframes nhPop{0%{transform:scale(0);}60%{transform:scale(1.2);}100%{transform:scale(1);}}

    /* User Pill */
    .nh-user{display:flex;align-items:center;gap:10px;padding:5px 16px 5px 5px;border-radius:9999px;border:1.5px solid var(--border);background:var(--bg-card);cursor:pointer;transition:all .25s;text-decoration:none;}
    .nh-user:hover{border-color:var(--primary);transform:translateY(-1px);box-shadow:0 4px 12px rgba(26,86,219,.1);}
    .nh-user-avatar{width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--secondary));display:flex;align-items:center;justify-content:center;color:#fff;font-size:.8rem;font-weight:700;box-shadow:0 2px 8px rgba(26,86,219,.25);position:relative;overflow:hidden;flex-shrink:0;}
    .nh-user-avatar::before{content:'';position:absolute;inset:0;background:linear-gradient(135deg,rgba(255,255,255,.25),transparent 60%);border-radius:inherit;}
    .nh-user:hover .nh-user-avatar{transform:scale(1.05);}
    .nh-user-name{font-family:'Plus Jakarta Sans',sans-serif;font-weight:700;font-size:.85rem;color:var(--text);max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}

    /* User Dropdown */
    .nh-user-menu{position:absolute;top:calc(100% + 8px);right:0;background:var(--bg-card);border:1px solid var(--border);border-radius:16px;padding:8px;min-width:240px;box-shadow:0 20px 50px -12px rgba(15,23,42,.18);opacity:0;visibility:hidden;transform:translateY(-8px);transition:all .2s;z-index:9999;}
    .nh-user:hover .nh-user-menu{opacity:1;visibility:visible;transform:translateY(0);}
    .nh-user-menu-header{padding:12px 14px;background:var(--bg-hover);border-radius:12px;margin-bottom:6px;}
    .nh-user-menu-header small{font-size:.6rem;text-transform:uppercase;letter-spacing:.5px;font-weight:800;color:var(--text-light);display:block;}
    .nh-user-menu-header h6{margin:2px 0 0;font-size:.88rem;font-weight:700;color:var(--text);}
    .nh-user-menu-item{display:flex;align-items:center;gap:10px;padding:9px 14px;border-radius:10px;font-weight:600;font-size:.86rem;color:var(--text);text-decoration:none;transition:all .2s;}
    .nh-user-menu-item:hover{background:var(--bg-hover);color:var(--primary);}
    .nh-user-menu-item i{width:18px;text-align:center;color:var(--text-light);font-size:.85rem;}
    .nh-user-menu-divider{height:1px;background:var(--border-light);margin:6px 10px;}
    .nh-user-menu-item.logout{color:var(--danger);}
    .nh-user-menu-item.logout i{color:var(--danger);}

    /* Notification Dropdown */
    .nh-notif-panel{position:fixed;top:auto;left:auto;right:auto;background:var(--bg-card);border:1px solid var(--border);border-radius:16px;width:370px;max-height:480px;overflow:hidden;box-shadow:0 20px 50px -12px rgba(15,23,42,.18);opacity:0;visibility:hidden;transform:translateY(-8px);transition:opacity .2s,visibility .2s,transform .2s;z-index:9999;pointer-events:none;}
    .nh-notif-panel.open{opacity:1;visibility:visible;transform:translateY(0);pointer-events:auto;}
    .nh-notif-head{display:flex;justify-content:space-between;align-items:center;padding:16px 20px;border-bottom:1px solid var(--border-light);}
    .nh-notif-head h6{margin:0;font-size:.92rem;font-weight:700;}
    .nh-notif-body{max-height:320px;overflow-y:auto;}
    .nh-notif-body::-webkit-scrollbar{width:4px;}
    .nh-notif-body::-webkit-scrollbar-track{background:transparent;}
    .nh-notif-body::-webkit-scrollbar-thumb{background:var(--border);border-radius:10px;}
    .nh-notif-foot{padding:12px 20px;border-top:1px solid var(--border-light);}
    .nh-notif-item{display:flex;align-items:flex-start;gap:12px;padding:12px 20px;cursor:pointer;transition:all .2s;border-bottom:1px solid var(--border-light);}
    .nh-notif-item:last-child{border-bottom:none;}
    .nh-notif-item:hover{background:var(--bg-hover);}
    .nh-notif-item.unread{background:rgba(26,86,219,.04);}
    .nh-notif-item.unread:hover{background:rgba(26,86,219,.08);}
    .nh-notif-icon{width:40px;height:40px;border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:.88rem;}
    .nh-notif-content{flex:1;min-width:0;}
    .nh-notif-title{font-size:.82rem;font-weight:600;color:var(--text);margin:0 0 2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .nh-notif-msg{font-size:.78rem;color:var(--text-muted);margin:0 0 2px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;line-height:1.4;}
    .nh-notif-time{font-size:.7rem;color:var(--text-light);font-weight:500;}
    .nh-notif-empty{text-align:center;padding:36px 20px;}
    .nh-notif-empty i{font-size:2rem;color:var(--text-light);margin-bottom:8px;display:block;}

    /* ═══ Mobile ═══ */
    .nh-hamburger{display:none;border:none;background:var(--bg-card);border:1.5px solid var(--border);border-radius:12px;width:40px;height:40px;align-items:center;justify-content:center;color:var(--text);font-size:1rem;cursor:pointer;transition:all .25s;position:relative;overflow:hidden;flex-shrink:0;}
    .nh-hamburger::before{content:'';position:absolute;inset:0;background:linear-gradient(135deg,var(--primary),var(--secondary));opacity:0;transition:opacity .25s;border-radius:inherit;}
    .nh-hamburger:hover{border-color:var(--primary);color:#fff;}
    .nh-hamburger:hover::before{opacity:1;}
    .nh-hamburger i{position:relative;z-index:1;}

    .nh-mobile-menu{display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:var(--bg-card);z-index:9991;overflow-y:auto;padding:80px 16px 24px;}
    .nh-mobile-menu.open{display:block;animation:fadeIn .2s ease;}
    .nh-mobile-link{display:flex;align-items:center;gap:12px;padding:13px 16px;border-radius:12px;font-weight:600;font-size:.92rem;color:var(--text);text-decoration:none;transition:all .2s;margin-bottom:4px;}
    .nh-mobile-link:hover,.nh-mobile-link.active{background:rgba(26,86,219,.08);color:var(--primary);}
    .nh-mobile-link i{width:22px;text-align:center;font-size:.9rem;}
    .nh-mobile-divider{height:1px;background:var(--border-light);margin:12px 0;}
    @keyframes fadeIn{from{opacity:0;}to{opacity:1;}}

    /* ═══ Responsive ═══ */
    @media(max-width:991px){
      .nh-center{display:none !important;}
      .nh-hamburger{display:flex;}
      .nh-right{gap:4px;}
      .nh-right .nh-user-name{display:none;}
      .nh-right .nh-user{padding:4px;border:none;background:transparent;}
      .nh-right .nh-user:hover{box-shadow:none;transform:none;border-color:transparent;}
      .nh-notif-panel{width:calc(100vw - 32px);max-height:60vh;}
    }
    @media(min-width:992px){
      .nh-mobile-menu{display:none !important;}
    }
    @media(max-width:575px){
      .nh-nav-inner{padding:0 12px;height:56px;}
      .nh-brand-text{font-size:1.1rem;}
      .nh-brand-icon{width:34px;height:34px;font-size:.9rem;}
      .nh-icon-btn{width:36px;height:36px;font-size:.92rem;}
      .nh-right .mr-2{margin-right:2px !important;}
      .nh-topbar{display:none;}
    }

    /* Toast */
    .toast-container{position:fixed;top:80px;right:20px;z-index:10000;display:flex;flex-direction:column;gap:10px;}
    .toast-notification{background:var(--bg-card);border-radius:12px;padding:14px 18px;box-shadow:0 20px 25px -5px rgba(0,0,0,.1);display:flex;align-items:flex-start;gap:12px;min-width:300px;max-width:380px;animation:toastSlideIn .3s ease;border-left:4px solid;}
    .toast-notification.toast-success{border-color:var(--success);}
    .toast-notification.toast-info{border-color:var(--info);}
    .toast-notification.toast-warning{border-color:var(--warning);}
    .toast-notification.toast-error{border-color:var(--danger);}
    .toast-notification .toast-icon{width:34px;height:34px;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:.85rem;}
    .toast-notification.toast-success .toast-icon{background:#dcfce7;color:var(--success);}
    .toast-notification.toast-info .toast-icon{background:#dbeafe;color:var(--info);}
    .toast-notification.toast-warning .toast-icon{background:#fef3c7;color:var(--warning);}
    .toast-notification.toast-error .toast-icon{background:#fee2e2;color:var(--danger);}
    .toast-body{flex:1;}
    .toast-body h6{font-weight:700;font-size:.82rem;margin:0 0 2px;color:var(--text);}
    .toast-body p{font-size:.78rem;color:var(--text-muted);margin:0;line-height:1.4;}
    .toast-close{background:none;border:none;color:var(--text-light);cursor:pointer;font-size:1.2rem;padding:0;line-height:1;}
    .toast-close:hover{color:var(--text);}
    @keyframes toastSlideIn{from{transform:translateX(100%);opacity:0;}to{transform:translateX(0);opacity:1;}}
    @keyframes toastSlideOut{from{transform:translateX(0);opacity:1;}to{transform:translateX(100%);opacity:0;}}
  </style>
</head>
<body class="dashboard-body">

<!-- Top Bar -->
<div class="nh-topbar">
  <div class="container-fluid">
    <a href="#"><i class="fas fa-headset mr-1"></i> Support</a>
    <a href="#"><i class="fas fa-globe mr-1"></i> EN</a>
  </div>
</div>

<!-- Main Navbar -->
<nav class="nh-nav" id="mainNav">
  <div class="nh-nav-inner">

    <!-- Brand -->
    <a class="nh-brand" href="seeker_dashboard.php">
      <div class="nh-brand-icon"><i class="fas fa-layer-group"></i></div>
      <div class="nh-brand-text">Nova<span>Hire</span></div>
    </a>

    <!-- Center Nav -->
    <div class="nh-center">
      <a class="nh-link <?php echo $nav_page == 'seeker_dashboard.php' ? 'active' : ''; ?>" href="seeker_dashboard.php"><i class="fas fa-home"></i>Home</a>
      <a class="nh-link <?php echo $nav_page == 'profile.php' ? 'active' : ''; ?>" href="profile.php"><i class="fas fa-user"></i>Profile</a>
      <a class="nh-link <?php echo in_array($nav_page, ['browse_jobs.php','job_details.php']) ? 'active' : ''; ?>" href="browse_jobs.php"><i class="fas fa-briefcase"></i>Jobs</a>
      <a class="nh-link <?php echo in_array($nav_page, ['available_companies.php','company_job_application.php','company_job_quiz.php','quiz.php']) ? 'active' : ''; ?>" href="available_companies.php"><i class="fas fa-building"></i>Companies</a>
      <a class="nh-link <?php echo in_array($nav_page, ['my_application.php','application.php']) ? 'active' : ''; ?>" href="my_application.php"><i class="fas fa-file-alt"></i>Applications</a>
      <a class="nh-link nh-link-special <?php echo $nav_page == 'live_chat.php' ? 'active' : ''; ?>" href="live_chat.php"><i class="fas fa-comment-dots"></i>Live Chat<span class="nh-badge nh-badge-msg" id="lcNotifBadge" style="display:none;position:relative;top:auto;right:auto;margin-left:4px;">0</span></a>
      <a class="nh-link nh-link-special <?php echo strpos($nav_page,'ai_')!==false||$nav_page=='grooming.php'?'active':''; ?>" href="ai_hub.php"><i class="fas fa-robot"></i>AI Center</a>

      <!-- More -->
      <div class="nh-more">
        <a class="nh-link" href="#"><i class="fas fa-ellipsis-h"></i>More</a>
        <div class="nh-more-menu">
          <div class="nh-more-header">Grow your career</div>
          <a class="nh-more-item" href="recommendations.php"><i class="fas fa-wand-magic-sparkles" style="color:#3b82f6;"></i>Job Matches</a>
          <a class="nh-more-item" href="skill_gap.php"><i class="fas fa-chart-simple" style="color:#d97706;"></i>Skill Gap Analyzer</a>
          <a class="nh-more-item" href="career_path.php"><i class="fas fa-signs-post" style="color:#0d9488;"></i>Career Path</a>
          <div class="nh-more-divider"></div>
          <div class="nh-more-header">Coaching & credentials</div>
          <a class="nh-more-item" href="mentors.php"><i class="fas fa-chalkboard-user" style="color:#ec4899;"></i>Find a Mentor</a>
          <a class="nh-more-item" href="my_sessions.php"><i class="fas fa-video" style="color:#06b6d4;"></i>My Sessions</a>
          <a class="nh-more-item" href="certificates.php"><i class="fas fa-award" style="color:#059669;"></i>My Certificates</a>
          <div class="nh-more-divider"></div>
          <div class="nh-more-header">Tools</div>
          <a class="nh-more-item" href="resume_builder.php"><i class="fas fa-file-alt" style="color:var(--text-light);"></i>Resume Builder</a>
          <a class="nh-more-item" href="job_alerts.php"><i class="fas fa-bell" style="color:var(--text-light);"></i>Job Alerts</a>
          <a class="nh-more-item" href="../blog/"><i class="fas fa-pen-nib" style="color:var(--text-light);"></i>Career Blog</a>
          <div class="nh-more-divider"></div>
          <a class="nh-more-item nh-more-pro" href="pro.php"><i class="fas fa-crown"></i>Upgrade to Pro</a>
        </div>
      </div>
    </div>

    <!-- Right Actions -->
    <div class="nh-right">
      <!-- Notifications -->
      <div class="nh-notif-trigger" style="position:relative;">
        <button class="nh-icon-btn" title="Notifications">
          <i class="fas fa-bell"></i>
          <?php if($unread_notifs>0): ?><span class="nh-badge" id="notifBadge"><?php echo $unread_notifs; ?></span><?php endif; ?>
        </button>
        <div class="nh-notif-panel">
          <div class="nh-notif-head">
            <h6>Notifications</h6>
            <?php if($unread_notifs>0): ?><button class="nh-link" style="font-size:.78rem;padding:0;" onclick="markAllNotificationsRead()">Mark all read</button><?php endif; ?>
          </div>
          <div class="nh-notif-body" id="notifList">
            <?php
            $notifications = get_notifications($con, 'user', $user_id, 6);
            $type_icons=['application_status'=>'fa-clipboard-check','new_application'=>'fa-file-alt','message'=>'fa-envelope','quiz_result'=>'fa-chart-line','job_update'=>'fa-briefcase','system'=>'fa-bell','job_recommendation'=>'fa-star'];
            $type_colors=['application_status'=>'#059669','new_application'=>'#3b82f6','message'=>'#06b6d4','quiz_result'=>'#d97706','job_update'=>'#06b6d4','system'=>'#3b82f6','job_recommendation'=>'#ec4899'];
            if(empty($notifications)): ?>
              <div class="nh-notif-empty"><i class="fas fa-bell-slash"></i><p style="font-size:.88rem;color:var(--text-muted);margin:0;">No notifications yet</p></div>
            <?php else: foreach($notifications as $n):
              $icon=$type_icons[$n['notification_type']]??'fa-bell';
              $color=$type_colors[$n['notification_type']]??'#3b82f6';
              $cls=$n['is_read']?'':'unread';
            ?>
              <div class="nh-notif-item <?php echo $cls; ?>" onclick="markNotificationRead(<?php echo $n['id']; ?>,this)">
                <div class="nh-notif-icon" style="background:<?php echo $color; ?>15;color:<?php echo $color; ?>;"><i class="fas <?php echo $icon; ?>"></i></div>
                <div class="nh-notif-content">
                  <h6 class="nh-notif-title"><?php echo htmlspecialchars($n['title']); ?></h6>
                  <p class="nh-notif-msg"><?php echo $n['message']; ?></p>
                  <small class="nh-notif-time"><?php echo time_ago($n['created_at']); ?></small>
                </div>
              </div>
            <?php endforeach; endif; ?>
          </div>
          <div class="nh-notif-foot"><a href="notifications.php" class="btn btn-sm btn-primary btn-block rounded-pill">View All</a></div>
        </div>
      </div>

      <!-- Messages -->
      <div class="nh-notif-trigger" style="position:relative;">
        <button class="nh-icon-btn" title="Messages">
          <i class="fas fa-envelope"></i>
          <?php if($unread_messages>0): ?><span class="nh-badge nh-badge-msg"><?php echo $unread_messages; ?></span><?php endif; ?>
        </button>
        <div class="nh-notif-panel">
          <div class="nh-notif-head">
            <h6>Messages</h6>
            <?php if($unread_messages>0): ?><a href="message_center.php" class="nh-link" style="font-size:.78rem;padding:0;">Inbox</a><?php endif; ?>
          </div>
          <div class="nh-notif-body">
            <?php
            $mq=mysqli_query($con,"SELECT m.*,CASE WHEN m.sender_type='company' THEN (SELECT company_name FROM companies WHERE id=m.sender_id) WHEN m.sender_type='user' THEN (SELECT username FROM user_info WHERE id=m.sender_id) ELSE 'System' END as sender_name FROM messages m WHERE (m.receiver_type='user' AND m.receiver_id=$user_id AND m.is_deleted_by_receiver=0) ORDER BY m.created_at DESC LIMIT 5");
            if(mysqli_num_rows($mq)===0): ?>
              <div class="nh-notif-empty"><i class="fas fa-envelope-open"></i><p style="font-size:.88rem;color:var(--text-muted);margin:0;">No messages yet</p></div>
            <?php else: while($msg=mysqli_fetch_assoc($mq)):
              $mc=$msg['is_read']?'':'unread';
            ?>
              <div class="nh-notif-item <?php echo $mc; ?>" onclick="window.location.href='message_center.php?with=<?php echo $msg['sender_type'].'_'.$msg['sender_id']; ?>'">
                <div class="nh-notif-icon" style="background:rgba(6,182,212,.1);color:#06b6d4;"><i class="fas fa-user"></i></div>
                <div class="nh-notif-content">
                  <h6 class="nh-notif-title"><?php echo htmlspecialchars($msg['sender_name']); ?></h6>
                  <p class="nh-notif-msg"><?php echo htmlspecialchars(substr($msg['subject'],0,50)); ?></p>
                  <small class="nh-notif-time"><?php echo time_ago($msg['created_at']); ?></small>
                </div>
              </div>
            <?php endwhile; endif; ?>
          </div>
          <div class="nh-notif-foot"><a href="message_center.php" class="btn btn-sm btn-primary btn-block rounded-pill">Open Message Center</a></div>
        </div>
      </div>

      <!-- Theme -->
      <div class="nh-notif-trigger" style="position:relative;">
        <button class="nh-icon-btn" title="Theme">
          <i class="fas fa-palette"></i>
        </button>
        <div class="nh-notif-panel" style="width:200px;">
          <div class="nh-notif-head"><h6>Theme</h6></div>
          <div style="padding:8px 12px;">
            <a class="nh-more-item" href="#" onclick="setTheme('default');return false;" style="margin-bottom:4px;"><span style="width:12px;height:12px;border-radius:50%;background:#1a56db;display:inline-block;margin-right:8px;"></span>Default</a>
            <a class="nh-more-item" href="#" onclick="setTheme('ocean');return false;" style="margin-bottom:4px;"><span style="width:12px;height:12px;border-radius:50%;background:#0891b2;display:inline-block;margin-right:8px;"></span>Ocean</a>
            <a class="nh-more-item" href="#" onclick="setTheme('sunset');return false;" style="margin-bottom:4px;"><span style="width:12px;height:12px;border-radius:50%;background:#ea580c;display:inline-block;margin-right:8px;"></span>Sunset</a>
            <a class="nh-more-item" href="#" onclick="setTheme('dark');return false;"><span style="width:12px;height:12px;border-radius:50%;background:#1e293b;display:inline-block;margin-right:8px;"></span>Dark</a>
          </div>
        </div>
      </div>

      <!-- User -->
      <div class="nh-user" style="position:relative;">
        <div class="nh-user-avatar"><?php echo $initial; ?></div>
        <span class="nh-user-name"><?php echo $username; ?><?php if ($nh_is_pro): ?> <?php echo nh_pro_badge(); ?><?php endif; ?></span>
        <div class="nh-user-menu">
          <div class="nh-user-menu-header">
            <small>Signed in as</small>
            <h6><?php echo $username; ?><?php if ($nh_is_pro): ?> <?php echo nh_pro_badge(); ?><?php endif; ?></h6>
          </div>
          <a class="nh-user-menu-item" href="profile.php"><i class="fas fa-user-circle"></i>My Profile</a>
          <a class="nh-user-menu-item" href="my_application.php"><i class="fas fa-file-alt"></i>Applications</a>
          <a class="nh-user-menu-item" href="message_center.php"><i class="fas fa-envelope"></i>Messages<?php if($unread_messages>0): ?><span class="nh-badge nh-badge-msg" style="position:static;margin-left:auto;"><?php echo $unread_messages; ?></span><?php endif; ?></a>
          <a class="nh-user-menu-item" href="notifications.php"><i class="fas fa-bell"></i>Notifications<?php if($unread_notifs>0): ?><span class="nh-badge" style="position:static;margin-left:auto;"><?php echo $unread_notifs; ?></span><?php endif; ?></a>
          <div class="nh-user-menu-divider"></div>
          <?php if (!$nh_is_pro): ?>
          <a class="nh-user-menu-item" href="pro.php" style="color:#1a56db"><i class="fas fa-crown"></i>Upgrade to Pro</a>
          <?php endif; ?>
          <a class="nh-user-menu-item logout" href="<?php echo BASE_URL; ?>/auth/logout.php"><i class="fas fa-sign-out-alt"></i>Logout</a>
        </div>
      </div>

      <!-- Hamburger -->
      <button class="nh-hamburger" id="nhHamburger" onclick="toggleMobileMenu()">
        <i class="fas fa-bars" id="nhHamburgerIcon"></i>
      </button>
    </div>

  </div>
</nav>

<!-- Mobile Menu -->
<div class="nh-mobile-menu" id="nhMobileMenu">
  <a class="nh-mobile-link <?php echo $nav_page=='seeker_dashboard.php'?'active':''; ?>" href="seeker_dashboard.php"><i class="fas fa-home"></i>Home</a>
  <a class="nh-mobile-link <?php echo $nav_page=='profile.php'?'active':''; ?>" href="profile.php"><i class="fas fa-user"></i>Profile</a>
  <a class="nh-mobile-link <?php echo in_array($nav_page,['browse_jobs.php','job_details.php'])?'active':''; ?>" href="browse_jobs.php"><i class="fas fa-briefcase"></i>Jobs</a>
  <a class="nh-mobile-link <?php echo in_array($nav_page,['available_companies.php','company_job_application.php','company_job_quiz.php','quiz.php'])?'active':''; ?>" href="available_companies.php"><i class="fas fa-building"></i>Companies</a>
  <a class="nh-mobile-link <?php echo in_array($nav_page,['my_application.php','application.php'])?'active':''; ?>" href="my_application.php"><i class="fas fa-file-alt"></i>Applications</a>
  <div class="nh-mobile-divider"></div>
  <a class="nh-mobile-link <?php echo $nav_page=='live_chat.php'?'active':''; ?>" href="live_chat.php"><i class="fas fa-comment-dots"></i>Live Chat</a>
  <a class="nh-mobile-link <?php echo strpos($nav_page,'ai_')!==false||$nav_page=='grooming.php'?'active':''; ?>" href="ai_hub.php"><i class="fas fa-robot"></i>AI Center</a>
  <div class="nh-mobile-divider"></div>
  <a class="nh-mobile-link" href="recommendations.php"><i class="fas fa-wand-magic-sparkles"></i>Job Matches</a>
  <a class="nh-mobile-link" href="skill_gap.php"><i class="fas fa-chart-simple"></i>Skill Gap Analyzer</a>
  <a class="nh-mobile-link" href="career_path.php"><i class="fas fa-signs-post"></i>Career Path</a>
  <a class="nh-mobile-link" href="mentors.php"><i class="fas fa-chalkboard-user"></i>Find a Mentor</a>
  <a class="nh-mobile-link" href="resume_builder.php"><i class="fas fa-file-alt"></i>Resume Builder</a>
  <a class="nh-mobile-link" href="job_alerts.php"><i class="fas fa-bell"></i>Job Alerts</a>
  <div class="nh-mobile-divider"></div>
  <a class="nh-mobile-link" href="pro.php" style="color:#d97706;"><i class="fas fa-crown"></i>Upgrade to Pro</a>
  <a class="nh-mobile-link" href="<?php echo BASE_URL; ?>/auth/logout.php" style="color:var(--danger);"><i class="fas fa-sign-out-alt"></i>Logout</a>
</div>

<div style="height:72px;"></div>
<div id="toastContainer" class="toast-container"></div>

<script>
function setTheme(t){document.body.setAttribute('data-theme',t);localStorage.setItem('theme',t);}
(function(){var s=localStorage.getItem('theme');if(s)document.body.setAttribute('data-theme',s);})();

function toggleMobileMenu(){
  var m=document.getElementById('nhMobileMenu');
  var i=document.getElementById('nhHamburgerIcon');
  m.classList.toggle('open');
  i.className=m.classList.contains('open')?'fas fa-times':'fas fa-bars';
}

function markNotificationRead(id,el){
  fetch('api/mark_notification_read.php?id='+id).then(r=>r.json()).then(d=>{
    if(d.success){el.classList.remove('unread');updateNotifBadge(-1);}
  });
}
function markAllNotificationsRead(){
  fetch('api/mark_all_read.php').then(r=>r.json()).then(d=>{
    if(d.success){document.querySelectorAll('.nh-notif-item.unread').forEach(e=>e.classList.remove('unread'));var b=document.getElementById('notifBadge');if(b)b.remove();}
  });
}
function updateNotifBadge(c){
  var b=document.getElementById('notifBadge');
  if(b){var n=parseInt(b.textContent)+c;if(n<=0)b.remove();else b.textContent=n;}
}

function showToast(type,title,msg,dur){
  dur=dur||5000;
  var c=document.getElementById('toastContainer');
  var icons={success:'fa-check-circle',info:'fa-info-circle',warning:'fa-exclamation-triangle',error:'fa-times-circle'};
  var t=document.createElement('div');
  t.className='toast-notification toast-'+type;
  t.innerHTML='<div class="toast-icon"><i class="fas '+(icons[type]||icons.info)+'"></i></div><div class="toast-body"><h6>'+title+'</h6><p>'+msg+'</p></div><button class="toast-close" onclick="this.parentElement.remove()">&times;</button>';
  c.appendChild(t);
  setTimeout(function(){t.style.animation='toastSlideOut .3s ease forwards';setTimeout(function(){t.remove();},300);},dur);
}

setInterval(function(){
  fetch('api/get_notification_count.php').then(r=>r.json()).then(d=>{
    if(d.count!==undefined){var b=document.getElementById('notifBadge');if(d.count>0){if(b)b.textContent=d.count;else location.reload();}else if(b)b.remove();}
  });
},30000);

function lcPoll(){
  var base=(window.APP_URL||'')+'/api/live_chat_alerts.php';
  fetch(base,{cache:'no-store'}).then(function(r){return r.json();}).then(function(d){
    if(!d.success||!d.alerts||!d.alerts.length)return;
    var badge=document.getElementById('lcNotifBadge');
    var missed=0;
    d.alerts.forEach(function(a){
      var activeId=(window.LC_ACTIVE_ID!==undefined&&window.LC_ACTIVE_ID!==null)?parseInt(window.LC_ACTIVE_ID,10):null;
      if(activeId!==null&&parseInt(a.sender_id,10)===activeId)return;
      missed++;showToast('info',a.sender_name,a.message,7000);
    });
    if(missed>0&&badge){var prev=parseInt(badge.textContent,10)||0;badge.textContent=prev+missed;badge.style.display='inline-flex';}
  }).catch(function(){});
}
setTimeout(lcPoll,500);setInterval(lcPoll,10000);

(function(){
  var nav=document.getElementById('mainNav');
  function onScroll(){if(nav)nav.classList.toggle('scrolled',window.scrollY>12);}
  window.addEventListener('scroll',onScroll,{passive:true});onScroll();

  var triggers=document.querySelectorAll('.nh-notif-trigger');
  triggers.forEach(function(trig){
    var btn=trig.querySelector('.nh-icon-btn');
    var panel=trig.querySelector('.nh-notif-panel');
    if(!btn||!panel)return;
    btn.addEventListener('click',function(e){
      e.stopPropagation();
      var wasOpen=panel.classList.contains('open');
      closeAllNhPanels();
      if(!wasOpen){
        panel.classList.add('open');
        posNhPanel(btn,panel);
      }
    });
  });

  function posNhPanel(btn,panel){
    var r=btn.getBoundingClientRect();
    var pw=panel.offsetWidth||370;
    var ph=panel.offsetHeight||400;
    var gap=8;
    var vw=window.innerWidth;
    var vh=window.innerHeight;
    panel.style.top=(r.bottom+gap)+'px';
    var left=r.right-pw;
    if(left<8)left=8;
    if(left+pw>vw-8)left=vw-pw-8;
    panel.style.left=left+'px';
    panel.style.right='auto';
  }

  function closeAllNhPanels(){
    document.querySelectorAll('.nh-notif-panel.open').forEach(function(p){p.classList.remove('open');});
  }

  document.addEventListener('click',function(e){
    if(!e.target.closest('.nh-notif-trigger'))closeAllNhPanels();
  });
  window.addEventListener('resize',closeAllNhPanels);
})();
</script>
<script>window.APP_URL='<?php echo BASE_URL; ?>';</script>
<script src="<?php echo BASE_URL; ?>/ai/assets/js/chat.js"></script>
<?php ai_chat_widget(); ?>
<script>
if('serviceWorker' in navigator){navigator.serviceWorker.register('/Job-portal-and-grooming/public/sw.js').then(function(r){console.log('SW registered:',r.scope);}).catch(function(e){console.log('SW failed:',e);});}
</script>
</body>
</html>
