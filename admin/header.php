<?php
    require_once __DIR__ . '/../includes/bootstrap.php';

    $admin_username = $_SESSION['admin_username'] ?? 'Admin';
    $admin_id = $_SESSION['admin_id'] ?? 0;
    if (!$admin_id) {
        $aq = mysqli_query($con, "SELECT id FROM admin_login WHERE admin_user_name = '" . mysqli_real_escape_string($con, $admin_username) . "' LIMIT 1");
        if ($aq && $arow = mysqli_fetch_assoc($aq)) {
            $admin_id = (int)$arow['id'];
            $_SESSION['admin_id'] = $admin_id;
        }
    }
    $unread_notifs = get_unread_count($con, 'admin', $admin_id);
    $current_page = basename($_SERVER['PHP_SELF']);
    $admin_display = ucwords(str_replace('_', ' ', $admin_username));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>NovaHire - Admin</title>
  <?php include '../includes/links.php' ?>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Sora:wght@400;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root{
      --an-primary:var(--primary, #1a56db);
      --an-secondary:var(--secondary, #0ea5e9);
      --an-grad:var(--grad, linear-gradient(135deg, var(--primary, #1a56db), var(--secondary, #0ea5e9)));
      --an-bg:rgba(255,255,255,.82);--an-bg-solid:var(--bg-card, #fff);
      --an-text:var(--text, #1e293b);--an-text-muted:var(--text-muted, #64748b);
      --an-border:var(--border, rgba(226,232,240,.6));
      --an-card:var(--bg-card, #fff);
      --an-hover:rgba(26,86,219,.06);--an-radius:var(--radius-md, 14px);
    }
    [data-theme="dark"]{
      --an-bg:rgba(15,23,42,.85);--an-bg-solid:var(--bg-card, #0f172a);
      --an-text:var(--text, #e2e8f0);--an-text-muted:var(--text-muted, #94a3b8);
      --an-border:var(--border, rgba(51,65,85,.6));
      --an-card:var(--bg-card, #1e293b);
      --an-hover:rgba(96,165,250,.15);
    }

    body{
      min-height:100vh;
      background:radial-gradient(circle at 12% 8%,rgba(59,130,246,.08),transparent 32%),radial-gradient(circle at 88% 12%,rgba(6,182,212,.06),transparent 30%),#f6f7fb;
      transition:background .4s;
    }
    [data-theme="dark"] body{
      background:radial-gradient(circle at 12% 8%,rgba(59,130,246,.18),transparent 32%),radial-gradient(circle at 88% 12%,rgba(6,182,212,.14),transparent 30%),#0f172a;
    }

    /* ═══ Top Accent Bar ═══ */
    .an-topbar{height:4px;background:var(--an-grad);position:sticky;top:0;z-index:1031;}

    /* ═══ Navbar ═══ */
    .an-nav{
      position:sticky;top:4px;z-index:1030;
      background:var(--an-bg);backdrop-filter:blur(20px) saturate(180%);-webkit-backdrop-filter:blur(20px) saturate(180%);
      border-bottom:1px solid var(--an-border);
      transition:box-shadow .3s,background .3s;
    }
    .an-nav.scrolled{box-shadow:0 8px 32px -12px rgba(15,23,42,.15);}
    [data-theme="dark"] .an-nav.scrolled{box-shadow:0 8px 32px -12px rgba(0,0,0,.4);}

    .an-inner{max-width:1400px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;padding:0 24px;height:62px;gap:12px;}

    /* ═══ Brand ═══ */
    .an-brand{display:flex;align-items:center;gap:11px;text-decoration:none;flex-shrink:0;}
    .an-brand-icon{
      width:40px;height:40px;border-radius:12px;
      background:var(--an-grad);color:#fff;
      display:flex;align-items:center;justify-content:center;
      font-size:1rem;box-shadow:0 4px 14px rgba(59,130,246,.35);
      transition:transform .3s cubic-bezier(.34,1.56,.64,1),box-shadow .3s;
      position:relative;overflow:hidden;
    }
    .an-brand-icon::before{content:'';position:absolute;inset:0;background:linear-gradient(135deg,rgba(255,255,255,.25),transparent 60%);border-radius:inherit;}
    .an-brand:hover .an-brand-icon{transform:rotate(-8deg) scale(1.08);box-shadow:0 6px 20px rgba(59,130,246,.5);}
    .an-brand-text{display:flex;flex-direction:column;line-height:1.15;}
    .an-brand-name{font-family:'Sora',sans-serif;font-weight:800;font-size:1.02rem;color:var(--an-text);letter-spacing:-.3px;}
    .an-brand-sub{font-size:.62rem;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--an-text-muted);}

    /* ═══ Center Nav ═══ */
    .an-center{display:flex;align-items:center;gap:2px;}
    .an-link{
      display:flex;align-items:center;gap:7px;
      padding:8px 14px;border-radius:9999px;
      font-weight:600;font-size:.84rem;color:var(--an-text-muted);
      text-decoration:none;white-space:nowrap;
      transition:all .25s;position:relative;
    }
    .an-link i{font-size:.82rem;opacity:.6;transition:all .25s;}
    .an-link::after{content:'';position:absolute;left:50%;bottom:2px;width:0;height:2.5px;border-radius:3px;background:var(--an-grad);transform:translateX(-50%);transition:width .3s cubic-bezier(.34,1.56,.64,1);}
    .an-link:hover{color:var(--an-primary);background:var(--an-hover);}
    .an-link:hover::after{width:40%;}
    .an-link:hover i{opacity:1;transform:scale(1.1);}
    .an-link.active{color:#fff;background:var(--an-grad);font-weight:700;box-shadow:0 4px 14px rgba(59,130,246,.35);}
    .an-link.active::after{width:50%;background:rgba(255,255,255,.4);}
    .an-link.active i{opacity:1;color:#fff;}

    /* ═══ More Dropdown ═══ */
    .an-more{position:relative;}
    .an-dropdown{
      position:fixed;top:auto;left:auto;right:auto;
      background:var(--an-card);border:1px solid var(--an-border);border-radius:16px;
      padding:8px;min-width:220px;
      box-shadow:0 20px 50px -12px rgba(15,23,42,.18);
      opacity:0;visibility:hidden;transform:translateY(-8px);
      transition:all .2s;z-index:10000;pointer-events:none;
    }
    .an-dropdown.open{opacity:1;visibility:visible;transform:translateY(0);pointer-events:auto;}
    [data-theme="dark"] .an-dropdown{box-shadow:0 20px 50px -12px rgba(0,0,0,.5);}
    .an-drop-label{font-size:.6rem;text-transform:uppercase;letter-spacing:.6px;font-weight:800;color:var(--an-text-muted);padding:8px 14px 4px;}
    .an-drop-item{display:flex;align-items:center;gap:10px;padding:9px 14px;border-radius:10px;font-weight:600;font-size:.84rem;color:var(--an-text);text-decoration:none;transition:all .2s;}
    .an-drop-item:hover{background:var(--an-hover);color:var(--an-primary);transform:translateX(3px);}
    .an-drop-item i{width:18px;text-align:center;font-size:.82rem;color:var(--an-text-muted);}
    .an-drop-divider{height:1px;background:var(--an-border);margin:6px 10px;}

    /* ═══ Right Actions ═══ */
    .an-right{display:flex;align-items:center;gap:6px;}
    .an-icon-btn{
      width:40px;height:40px;border-radius:12px;
      display:flex;align-items:center;justify-content:center;
      color:var(--an-text-muted);text-decoration:none;
      transition:all .25s;position:relative;font-size:.95rem;
      background:transparent;border:none;cursor:pointer;
    }
    .an-icon-btn:hover{background:var(--an-hover);color:var(--an-primary);transform:translateY(-1px);}
    .an-icon-btn:active{transform:scale(.92);}

    .an-badge{
      position:absolute;top:2px;right:1px;
      background:linear-gradient(135deg,#dc2626,#f97316);
      color:#fff;font-size:.56rem;font-weight:800;
      min-width:18px;height:18px;border-radius:50%;
      display:flex;align-items:center;justify-content:center;
      padding:0 4px;border:2px solid var(--an-bg-solid);
      box-shadow:0 2px 6px rgba(239,68,68,.35);
      animation:anPop .3s cubic-bezier(.34,1.56,.64,1);
    }
    @keyframes anPop{0%{transform:scale(0);}60%{transform:scale(1.2);}100%{transform:scale(1);}}

    /* ═══ Notification Panel ═══ */
    .an-notif-wrap{position:relative;}
    .an-notif-panel{
      position:fixed;top:auto;left:auto;right:auto;
      background:var(--an-card);border:1px solid var(--an-border);border-radius:16px;
      width:340px;max-height:440px;overflow:hidden;
      box-shadow:0 20px 50px -12px rgba(15,23,42,.18);
      opacity:0;visibility:hidden;transform:translateY(-8px);
      transition:all .2s;z-index:10000;pointer-events:none;
    }
    .an-notif-panel.open{opacity:1;visibility:visible;transform:translateY(0);pointer-events:auto;}
    [data-theme="dark"] .an-notif-panel{box-shadow:0 20px 50px -12px rgba(0,0,0,.5);}
    .an-notif-head{display:flex;justify-content:space-between;align-items:center;padding:16px 18px;border-bottom:1px solid var(--an-border);}
    .an-notif-head h6{margin:0;font-size:.9rem;font-weight:700;color:var(--an-text);}
    .an-notif-head a{font-size:.75rem;font-weight:600;color:var(--an-primary);text-decoration:none;}
    .an-notif-list{max-height:300px;overflow-y:auto;}
    .an-notif-list::-webkit-scrollbar{width:4px;}
    .an-notif-list::-webkit-scrollbar-thumb{background:var(--an-border);border-radius:10px;}
    .an-notif-item{display:flex;align-items:flex-start;gap:10px;padding:12px 18px;border-bottom:1px solid var(--an-border);transition:background .2s;cursor:pointer;}
    .an-notif-item:last-child{border-bottom:none;}
    .an-notif-item:hover{background:var(--an-hover);}
    .an-notif-item.unread{background:rgba(59,130,246,.04);}
    .an-notif-ico{width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:.82rem;background:var(--an-hover);color:var(--an-primary);}
    .an-notif-body{flex:1;min-width:0;}
    .an-notif-title{font-size:.8rem;font-weight:600;color:var(--an-text);margin:0 0 2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .an-notif-msg{font-size:.72rem;color:var(--an-text-muted);margin:0;line-height:1.4;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}
    .an-notif-foot{padding:12px 18px;border-top:1px solid var(--an-border);text-align:center;}
    .an-notif-foot a{font-size:.78rem;font-weight:600;color:var(--an-primary);text-decoration:none;}
    .an-notif-empty{text-align:center;padding:32px 18px;color:var(--an-text-muted);font-size:.85rem;}
    .an-notif-empty i{font-size:1.8rem;color:var(--an-border);margin-bottom:8px;display:block;}

    /* ═══ User Pill ═══ */
    .an-user{position:relative;}
    .an-user-pill{
      display:flex;align-items:center;gap:9px;
      padding:5px 14px 5px 5px;border-radius:9999px;
      border:1.5px solid var(--an-border);background:var(--an-bg-solid);
      cursor:pointer;transition:all .25s;text-decoration:none;
    }
    .an-user-pill:hover{border-color:var(--an-primary);transform:translateY(-1px);box-shadow:0 4px 12px rgba(59,130,246,.1);}
    .an-user-avatar{
      width:34px;height:34px;border-radius:50%;
      background:var(--an-grad);color:#fff;
      display:flex;align-items:center;justify-content:center;
      font-size:.78rem;font-weight:700;flex-shrink:0;
      box-shadow:0 2px 8px rgba(59,130,246,.25);position:relative;overflow:hidden;
    }
    .an-user-avatar::before{content:'';position:absolute;inset:0;background:linear-gradient(135deg,rgba(255,255,255,.25),transparent 60%);border-radius:inherit;}
    .an-user-pill:hover .an-user-avatar{transform:scale(1.05);}
    .an-user-name{font-family:'Sora',sans-serif;font-weight:700;font-size:.84rem;color:var(--an-text);max-width:110px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}

    .an-user-dropdown{
      position:fixed;top:auto;left:auto;right:auto;
      background:var(--an-card);border:1px solid var(--an-border);border-radius:16px;
      padding:8px;min-width:220px;
      box-shadow:0 20px 50px -12px rgba(15,23,42,.18);
      opacity:0;visibility:hidden;transform:translateY(-8px);
      transition:all .2s;z-index:10000;pointer-events:none;
    }
    .an-user-dropdown.open{opacity:1;visibility:visible;transform:translateY(0);pointer-events:auto;}
    [data-theme="dark"] .an-user-dropdown{box-shadow:0 20px 50px -12px rgba(0,0,0,.5);}
    .an-user-dd-head{padding:12px 14px;background:var(--an-hover);border-radius:12px;margin-bottom:6px;}
    .an-user-dd-head small{font-size:.58rem;text-transform:uppercase;letter-spacing:.5px;font-weight:800;color:var(--an-text-muted);display:block;}
    .an-user-dd-head h6{margin:2px 0 0;font-size:.86rem;font-weight:700;color:var(--an-text);}
    .an-user-dd-item{display:flex;align-items:center;gap:10px;padding:9px 14px;border-radius:10px;font-weight:600;font-size:.84rem;color:var(--an-text);text-decoration:none;transition:all .2s;}
    .an-user-dd-item:hover{background:var(--an-hover);color:var(--an-primary);}
    .an-user-dd-item i{width:18px;text-align:center;color:var(--an-text-muted);font-size:.82rem;}
    .an-user-dd-divider{height:1px;background:var(--an-border);margin:6px 10px;}
    .an-user-dd-item.logout{color:#dc2626;}
    .an-user-dd-item.logout i{color:#dc2626;}

    /* ═══ Theme Toggle ═══ */
    .an-theme{
      width:40px;height:40px;border-radius:12px;
      background:var(--an-hover);border:1px solid var(--an-border);
      color:var(--an-text);display:flex;align-items:center;justify-content:center;
      cursor:pointer;transition:all .3s;font-size:.92rem;
    }
    .an-theme i{transition:transform .4s;}
    .an-theme:hover{background:var(--an-grad);border-color:transparent;color:#fff;transform:rotate(30deg) scale(1.06);}

    /* ═══ Logout ═══ */
    .an-logout{
      display:inline-flex;align-items:center;gap:7px;
      padding:8px 16px;border-radius:9999px;
      background:linear-gradient(135deg,#dc2626,#f97316);
      color:#fff;font-weight:700;font-size:.82rem;
      text-decoration:none;box-shadow:0 4px 12px rgba(239,68,68,.3);
      transition:all .3s;
    }
    .an-logout:hover{transform:translateY(-2px);box-shadow:0 8px 20px rgba(239,68,68,.4);color:#fff;text-decoration:none;}

    /* ═══ Mobile Hamburger ═══ */
    .an-hamburger{
      display:none;border:none;background:var(--an-bg-solid);
      border:1.5px solid var(--an-border);border-radius:12px;
      width:40px;height:40px;align-items:center;justify-content:center;
      color:var(--an-text);font-size:1rem;cursor:pointer;
      transition:all .25s;position:relative;overflow:hidden;flex-shrink:0;
    }
    .an-hamburger::before{content:'';position:absolute;inset:0;background:var(--an-grad);opacity:0;transition:opacity .25s;border-radius:inherit;}
    .an-hamburger:hover{border-color:var(--an-primary);color:#fff;}
    .an-hamburger:hover::before{opacity:1;}
    .an-hamburger i{position:relative;z-index:1;}

    .an-mobile-menu{
      display:none;position:fixed;top:0;left:0;right:0;bottom:0;
      background:var(--an-bg-solid);z-index:9991;overflow-y:auto;
      padding:80px 20px 24px;
    }
    .an-mobile-menu.open{display:block;animation:anFadeIn .2s ease;}
    .an-mobile-link{
      display:flex;align-items:center;gap:12px;padding:13px 16px;
      border-radius:12px;font-weight:600;font-size:.92rem;
      color:var(--an-text);text-decoration:none;transition:all .2s;margin-bottom:4px;
    }
    .an-mobile-link:hover,.an-mobile-link.active{background:var(--an-hover);color:var(--an-primary);}
    .an-mobile-link i{width:22px;text-align:center;font-size:.9rem;}
    .an-mobile-divider{height:1px;background:var(--an-border);margin:12px 0;}
    @keyframes anFadeIn{from{opacity:0;}to{opacity:1;}}

    /* ═══ Responsive ═══ */
    @media(max-width:991px){
      .an-center{display:none !important;}
      .an-hamburger{display:flex;}
      .an-right .an-user-name{display:none;}
      .an-right .an-user-pill{padding:4px;border:none;background:transparent;}
      .an-right .an-user-pill:hover{box-shadow:none;transform:none;border-color:transparent;}
      .an-right .an-logout span{display:none;}
      .an-right .an-logout{padding:8px 12px;}
      .an-notif-panel{width:calc(100vw - 24px);left:12px !important;right:12px !important;max-height:60vh;}
      .an-dropdown{width:calc(100vw - 24px);left:12px !important;right:12px !important;}
      .an-user-dropdown{width:calc(100vw - 24px);left:12px !important;right:12px !important;}
    }
    @media(min-width:992px){
      .an-mobile-menu{display:none !important;}
    }
    @media(max-width:575px){
      .an-inner{padding:0 14px;height:56px;}
      .an-brand-name{font-size:.95rem;}
      .an-brand-icon{width:36px;height:36px;font-size:.9rem;}
      .an-icon-btn{width:36px;height:36px;font-size:.88rem;}
      .an-topbar{display:none;}
    }

    @media print{
      .an-topbar,.an-nav,.an-mobile-menu{display:none !important;}
    }
  </style>
</head>
<body>

<div class="an-topbar"></div>

<nav class="an-nav" id="anNav">
  <div class="an-inner">

    <!-- Brand -->
    <a class="an-brand" href="admin_dashboard.php">
      <div class="an-brand-icon"><i class="fas fa-shield-halved"></i></div>
      <div class="an-brand-text">
        <span class="an-brand-name">Admin Panel</span>
        <span class="an-brand-sub">NovaHire Control</span>
      </div>
    </a>

    <!-- Center Nav -->
    <div class="an-center">
      <a class="an-link <?php echo in_array($current_page, ['index.php','admin_dashboard.php']) ? 'active' : ''; ?>" href="admin_dashboard.php"><i class="fas fa-th-large"></i>Dashboard</a>
      <a class="an-link <?php echo in_array($current_page, ['show_users.php','update_user.php','add_details.php','delete_user.php']) ? 'active' : ''; ?>" href="show_users.php"><i class="fas fa-users"></i>Users</a>
      <a class="an-link <?php echo in_array($current_page, ['showdata.php','update_application.php','view_cv.php','delete_application.php']) ? 'active' : ''; ?>" href="showdata.php"><i class="fas fa-file-alt"></i>Applications</a>
      <a class="an-link <?php echo $current_page == 'mentors.php' ? 'active' : ''; ?>" href="mentors.php"><i class="fas fa-chalkboard-user"></i>Mentors</a>
      <a class="an-link <?php echo $current_page == 'revenue.php' ? 'active' : ''; ?>" href="revenue.php"><i class="fas fa-chart-line"></i>Revenue</a>

      <!-- More -->
      <div class="an-more">
        <a class="an-link" href="#" id="anMoreBtn"><i class="fas fa-ellipsis-h"></i>More</a>
        <div class="an-dropdown" id="anMoreDrop">
          <div class="an-drop-label">Quick Actions</div>
          <a class="an-drop-item" href="add_details.php"><i class="fas fa-user-plus"></i>Add User</a>
          <a class="an-drop-item" href="add_admin.php"><i class="fas fa-user-shield"></i>Add Admin</a>
          <div class="an-drop-divider"></div>
          <div class="an-drop-label">System</div>
          <a class="an-drop-item" href="ai_settings.php"><i class="fas fa-robot"></i>AI Settings</a>
          <a class="an-drop-item" href="email_settings.php"><i class="fas fa-envelope"></i>Email Settings</a>
          <a class="an-drop-item" href="notifications.php"><i class="fas fa-bell"></i>Notifications</a>
        </div>
      </div>
    </div>

    <!-- Right Actions -->
    <div class="an-right">
      <!-- Notifications -->
      <div class="an-notif-wrap">
        <button class="an-icon-btn" id="anNotifBtn" title="Notifications">
          <i class="fas fa-bell"></i>
          <?php if ($unread_notifs > 0): ?>
            <span class="an-badge" id="anNotifBadge"><?php echo $unread_notifs; ?></span>
          <?php endif; ?>
        </button>
        <div class="an-notif-panel" id="anNotifPanel">
          <div class="an-notif-head">
            <h6>Notifications</h6>
            <a href="notifications.php">View all</a>
          </div>
          <div class="an-notif-list">
            <?php
              $nq = mysqli_query($con, "SELECT * FROM notifications WHERE recipient_type='admin' AND recipient_id='$admin_id' ORDER BY created_at DESC LIMIT 6");
              if ($nq && mysqli_num_rows($nq) > 0):
                while ($n = mysqli_fetch_assoc($nq)):
            ?>
              <div class="an-notif-item <?php echo !$n['is_read'] ? 'unread' : ''; ?>">
                <div class="an-notif-ico"><i class="fas fa-bell"></i></div>
                <div class="an-notif-body">
                  <p class="an-notif-title"><?php echo htmlspecialchars($n['title'] ?? 'Notification'); ?></p>
                  <p class="an-notif-msg"><?php echo htmlspecialchars($n['message'] ?? ''); ?></p>
                </div>
              </div>
            <?php endwhile; else: ?>
              <div class="an-notif-empty"><i class="fas fa-bell-slash"></i>No notifications yet</div>
            <?php endif; ?>
          </div>
          <div class="an-notif-foot"><a href="notifications.php">View all notifications</a></div>
        </div>
      </div>

      <!-- Theme Toggle -->
      <button class="an-theme" type="button" title="Toggle Theme" onclick="anToggleTheme()">
        <i class="fas fa-moon" id="anThemeIcon"></i>
      </button>

      <!-- User Profile -->
      <div class="an-user">
        <div class="an-user-pill" id="anUserBtn">
          <div class="an-user-avatar"><i class="fas fa-user-shield"></i></div>
          <span class="an-user-name"><?php echo htmlspecialchars($admin_display); ?></span>
        </div>
        <div class="an-user-dropdown" id="anUserDrop">
          <div class="an-user-dd-head">
            <small>Signed in as</small>
            <h6><?php echo htmlspecialchars($admin_display); ?></h6>
          </div>
          <a class="an-user-dd-item" href="admin_dashboard.php"><i class="fas fa-th-large"></i>Dashboard</a>
          <a class="an-user-dd-item" href="ai_settings.php"><i class="fas fa-cog"></i>Settings</a>
          <a class="an-user-dd-item" href="notifications.php"><i class="fas fa-bell"></i>Notifications</a>
          <div class="an-user-dd-divider"></div>
          <a class="an-user-dd-item logout" href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i>Logout</a>
        </div>
      </div>

      <!-- Logout (desktop) -->
      <a class="an-logout" href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>

      <!-- Hamburger (mobile) -->
      <button class="an-hamburger" onclick="anToggleMobile()" id="anHamburger">
        <i class="fas fa-bars" id="anHamburgerIcon"></i>
      </button>
    </div>
  </div>
</nav>

<!-- Mobile Menu -->
<div class="an-mobile-menu" id="anMobileMenu">
  <a class="an-mobile-link <?php echo in_array($current_page, ['index.php','admin_dashboard.php']) ? 'active' : ''; ?>" href="admin_dashboard.php"><i class="fas fa-th-large"></i>Dashboard</a>
  <a class="an-mobile-link <?php echo in_array($current_page, ['show_users.php','update_user.php','add_details.php','delete_user.php']) ? 'active' : ''; ?>" href="show_users.php"><i class="fas fa-users"></i>Users</a>
  <a class="an-mobile-link <?php echo in_array($current_page, ['showdata.php','update_application.php','view_cv.php','delete_application.php']) ? 'active' : ''; ?>" href="showdata.php"><i class="fas fa-file-alt"></i>Applications</a>
  <a class="an-mobile-link <?php echo $current_page == 'mentors.php' ? 'active' : ''; ?>" href="mentors.php"><i class="fas fa-chalkboard-user"></i>Mentors</a>
  <a class="an-mobile-link <?php echo $current_page == 'revenue.php' ? 'active' : ''; ?>" href="revenue.php"><i class="fas fa-chart-line"></i>Revenue</a>
  <div class="an-mobile-divider"></div>
  <a class="an-mobile-link" href="add_details.php"><i class="fas fa-user-plus"></i>Add User</a>
  <a class="an-mobile-link" href="add_admin.php"><i class="fas fa-user-shield"></i>Add Admin</a>
  <a class="an-mobile-link" href="ai_settings.php"><i class="fas fa-robot"></i>AI Settings</a>
  <a class="an-mobile-link" href="email_settings.php"><i class="fas fa-envelope"></i>Email Settings</a>
  <a class="an-mobile-link" href="notifications.php"><i class="fas fa-bell"></i>Notifications</a>
  <div class="an-mobile-divider"></div>
  <a class="an-mobile-link" href="../auth/logout.php" style="color:#dc2626;"><i class="fas fa-sign-out-alt"></i>Logout</a>
</div>

<script>
(function(){
  var nav=document.getElementById('anNav');
  function onScroll(){if(nav)nav.classList.toggle('scrolled',window.scrollY>10);}
  window.addEventListener('scroll',onScroll,{passive:true});onScroll();

  function currentTheme(){return localStorage.getItem('company-theme')==='dark'?'dark':'light';}
  function applyTheme(t){
    document.documentElement.setAttribute('data-theme',t);
    document.body.classList.toggle('dark-theme',t==='dark');
    var icon=document.getElementById('anThemeIcon');
    if(icon){icon.classList.toggle('fa-sun',t==='dark');icon.classList.toggle('fa-moon',t!=='dark');}
  }
  window.anToggleTheme=function(){var n=currentTheme()==='dark'?'light':'dark';localStorage.setItem('company-theme',n);applyTheme(n);};
  document.addEventListener('DOMContentLoaded',function(){applyTheme(currentTheme());});

  function closeAll(){
    document.getElementById('anNotifPanel').classList.remove('open');
    document.getElementById('anUserDrop').classList.remove('open');
    document.getElementById('anMoreDrop').classList.remove('open');
  }

  function posBelowTrigger(btn,panel){
    var r=btn.getBoundingClientRect();
    var pw=panel.offsetWidth||340;
    var gap=8;
    var vw=window.innerWidth;
    panel.style.top=(r.bottom+gap)+'px';
    var left=r.right-pw;
    if(left<8)left=8;
    if(left+pw>vw-8)left=vw-pw-8;
    panel.style.left=left+'px';
    panel.style.right='auto';
  }

  function togglePanel(btnId,panelId,e){
    e.stopPropagation();
    var panel=document.getElementById(panelId);
    var btn=document.getElementById(btnId);
    var wasOpen=panel.classList.contains('open');
    closeAll();
    if(!wasOpen){
      panel.classList.add('open');
      posBelowTrigger(btn,panel);
    }
  }

  function toggleMore(e){
    e.stopPropagation();e.preventDefault();
    var panel=document.getElementById('anMoreDrop');
    var btn=e.currentTarget;
    var wasOpen=panel.classList.contains('open');
    closeAll();
    if(!wasOpen){
      panel.classList.add('open');
      var r=btn.getBoundingClientRect();
      var pw=panel.offsetWidth||220;
      var vw=window.innerWidth;
      panel.style.top=(r.bottom+8)+'px';
      var left=r.left;
      if(left+pw>vw-8)left=vw-pw-8;
      if(left<8)left=8;
      panel.style.left=left+'px';
      panel.style.right='auto';
    }
  }

  document.getElementById('anNotifBtn').addEventListener('click',function(e){togglePanel('anNotifBtn','anNotifPanel',e);});
  document.getElementById('anUserBtn').addEventListener('click',function(e){togglePanel('anUserBtn','anUserDrop',e);});
  document.getElementById('anMoreBtn').addEventListener('click',toggleMore);

  document.addEventListener('click',function(e){
    if(!e.target.closest('.an-notif-wrap')&&!e.target.closest('.an-user')&&!e.target.closest('.an-more'))closeAll();
  });
  window.addEventListener('resize',closeAll);
})();

function anToggleMobile(){
  var m=document.getElementById('anMobileMenu');
  var i=document.getElementById('anHamburgerIcon');
  m.classList.toggle('open');
  i.className=m.classList.contains('open')?'fas fa-times':'fas fa-bars';
  document.body.style.overflow=m.classList.contains('open')?'hidden':'';
}
</script>
</body>
</html>
