<?php
    require_once 'dbcon.php';
    require_once '../includes/functions.php';

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <title>NovaHire - Admin</title>
  <?php include '../includes/links.php' ?>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Sora:wght@400;600;700;800&display=swap" rel="stylesheet">
  <style>
    @keyframes badge-pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.18); }
    }
    @keyframes ad-ping {
        0% { transform: scale(1); opacity: .55; }
        75%, 100% { transform: scale(1.9); opacity: 0; }
    }
    @keyframes ad-drop {
        from { opacity: 0; transform: translateY(-8px) scale(.97); }
        to   { opacity: 1; transform: translateY(0) scale(1); }
    }

    body {
        min-height: 100vh;
        background:
            radial-gradient(circle at 12% 8%, rgba(99, 102, 241, 0.12), transparent 32%),
            radial-gradient(circle at 88% 12%, rgba(139, 92, 246, 0.10), transparent 30%),
            #f6f7fb;
        transition: background .4s ease;
    }
    [data-theme="dark"] body {
        background:
            radial-gradient(circle at 12% 8%, rgba(99, 102, 241, 0.22), transparent 32%),
            radial-gradient(circle at 88% 12%, rgba(139, 92, 246, 0.18), transparent 30%),
            #0f172a;
    }

    .admin-nav {
        position: sticky;
        top: 0;
        z-index: 1030;
        padding: 0;
        background: rgba(255, 255, 255, 0.78);
        backdrop-filter: blur(18px) saturate(160%);
        -webkit-backdrop-filter: blur(18px) saturate(160%);
        border-bottom: 1px solid var(--border-light);
        box-shadow: 0 1px 0 rgba(15, 23, 42, 0.04), 0 8px 24px -18px rgba(15, 23, 42, 0.25);
        transition: box-shadow .35s ease, background .35s ease;
    }
    [data-theme="dark"] .admin-nav {
        background: rgba(15, 23, 42, 0.8);
        border-bottom-color: rgba(51, 65, 85, 0.55);
    }
    .admin-nav.scrolled {
        box-shadow: 0 12px 32px -14px rgba(15, 23, 42, 0.45);
    }

    .admin-brand {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 0;
        margin: 0;
    }
    .admin-brand-tile {
        width: 42px;
        height: 42px;
        border-radius: 13px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #6366f1, #8b5cf6 55%, #0ea5e9);
        color: #fff;
        font-size: 1.05rem;
        box-shadow: 0 6px 14px -6px rgba(99, 102, 241, 0.6);
        transition: transform .3s ease, box-shadow .3s ease;
        flex-shrink: 0;
    }
    .admin-brand:hover .admin-brand-tile {
        transform: rotate(-6deg) scale(1.06);
        box-shadow: 0 10px 20px -6px rgba(99, 102, 241, 0.75);
    }
    .admin-name-wrap { display: flex; flex-direction: column; line-height: 1.2; }
    .admin-name-text {
        font-family: 'Sora', 'Manrope', 'Inter', sans-serif;
        font-weight: 800;
        font-size: 1.02rem;
        letter-spacing: -0.01em;
        color: var(--text);
        white-space: nowrap;
    }
    .admin-name-sub {
        font-size: .66rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .1em;
        color: var(--text-muted);
    }

    .nav-link-modern {
        display: flex;
        align-items: center;
        gap: 8px;
        color: var(--text) !important;
        font-weight: 600;
        font-size: .86rem;
        padding: 9px 14px !important;
        margin: 6px 3px;
        border-radius: 12px;
        position: relative;
        transition: background .25s ease, color .25s ease, transform .25s ease;
    }
    .nav-link-modern:hover {
        background: var(--bg-hover);
        transform: translateY(-1px);
    }
    .nav-link-modern i {
        font-size: .96rem;
        width: 18px;
        text-align: center;
        color: var(--text-muted);
        transition: color .25s ease, transform .25s ease;
    }
    .nav-link-modern:hover i { color: var(--primary); transform: scale(1.1); }

    .nav-item.active > .nav-link-modern {
        background: linear-gradient(135deg, #6366f1, #8b5cf6);
        color: #fff !important;
        box-shadow: 0 6px 16px -6px rgba(99, 102, 241, 0.65);
    }
    .nav-item.active > .nav-link-modern i { color: #fff; }

    .nav-item.nd-icon-btn-wrap { margin: 6px 3px; }
    .nav-link-modern.nd-icon-btn {
        width: 40px;
        height: 40px;
        padding: 0 !important;
        justify-content: center;
        background: var(--bg-hover);
    }
    .nav-link-modern.nd-icon-btn i { width: auto; }
    .nav-link-modern.nd-icon-btn:hover { background: linear-gradient(135deg, #6366f1, #8b5cf6); }
    .nav-link-modern.nd-icon-btn:hover i { color: #fff; }

    .admin-badge {
        position: absolute;
        top: -4px;
        right: -4px;
        min-width: 18px;
        height: 18px;
        padding: 0 5px;
        border-radius: 999px;
        background: linear-gradient(135deg, #ef4444, #f97316);
        color: #fff;
        font-size: .6rem;
        font-weight: 800;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 3px 8px -2px rgba(239, 68, 68, 0.6);
        animation: badge-pulse 2.4s infinite;
        z-index: 2;
    }
    .admin-badge::before {
        content: '';
        position: absolute;
        inset: -4px;
        border-radius: 999px;
        background: inherit;
        opacity: .45;
        z-index: -1;
        animation: ad-ping 1.8s cubic-bezier(0, 0, .2, 1) infinite;
    }

    .admin-welcome {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 7px 14px;
        border-radius: 12px;
        background: var(--bg-hover);
        color: var(--text);
        font-size: .85rem;
        font-weight: 600;
        white-space: nowrap;
    }
    .admin-welcome .aw-ico {
        width: 26px;
        height: 26px;
        border-radius: 9px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: .72rem;
        color: #fff;
        background: linear-gradient(135deg, #6366f1, #8b5cf6);
        flex-shrink: 0;
    }

    .btn-theme-toggle {
        width: 40px;
        height: 40px;
        border-radius: 12px;
        background: var(--bg-hover);
        border: 1px solid var(--border-light);
        color: var(--text);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all .3s ease;
    }
    .btn-theme-toggle i { transition: transform .4s ease; font-size: .95rem; }
    .btn-theme-toggle:hover {
        background: linear-gradient(135deg, #6366f1, #8b5cf6);
        border-color: transparent;
        color: #fff;
        transform: rotate(30deg) scale(1.06);
    }

    .btn-admin-logout {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 9px 18px;
        border-radius: 12px;
        background: linear-gradient(135deg, #ef4444, #f97316);
        color: #fff !important;
        font-weight: 700;
        font-size: .84rem;
        box-shadow: 0 6px 14px -6px rgba(239, 68, 68, 0.55);
        transition: all .3s ease;
        margin-left: 4px;
    }
    .btn-admin-logout:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 22px -8px rgba(239, 68, 68, 0.65);
        color: #fff !important;
        text-decoration: none;
    }

    .admin-toggler {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        background: var(--bg-hover);
        border: 1px solid var(--border-light);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 5px;
        padding: 0;
        transition: background .3s ease;
    }
    .admin-toggler .tg-bar {
        width: 20px;
        height: 2px;
        border-radius: 2px;
        background: var(--text);
        transition: transform .3s ease, opacity .3s ease, background .3s ease;
    }
    .admin-toggler[aria-expanded="true"] .tg-bar:nth-child(1) { transform: translateY(7px) rotate(45deg); background: var(--primary); }
    .admin-toggler[aria-expanded="true"] .tg-bar:nth-child(2) { opacity: 0; }
    .admin-toggler[aria-expanded="true"] .tg-bar:nth-child(3) { transform: translateY(-7px) rotate(-45deg); background: var(--primary); }

    @media (max-width: 991.98px) {
        .admin-nav { padding: 8px 0; }
        .admin-collapse {
            margin-top: 10px;
            padding: 14px;
            border-radius: 16px;
            background: var(--bg-card);
            border: 1px solid var(--border-light);
            box-shadow: 0 22px 44px -12px rgba(15, 23, 42, 0.3);
        }
        .admin-collapse .nav-link-modern { margin: 4px 0; }
        .admin-welcome { width: 100%; justify-content: center; margin: 6px 0; }
        .btn-admin-logout, .btn-theme-toggle { margin: 8px 0; width: 100%; justify-content: center; }
        .nav-item.nd-icon-btn-wrap { margin: 4px 0; }
        .nav-link-modern.nd-icon-btn { width: 100%; height: 42px; }
    }
  </style>
</head>
<body>

<nav class="navbar navbar-expand-lg admin-nav">
  <div class="container-fluid px-4 px-lg-5">
      <a class="navbar-brand admin-brand" href="admin_dashboard.php">
          <span class="admin-brand-tile"><i class="fas fa-shield-halved"></i></span>
          <span class="admin-name-wrap">
              <span class="admin-name-text">Admin Panel</span>
              <span class="admin-name-sub">NovaHire Control</span>
          </span>
      </a>
      <button class="navbar-toggler admin-toggler" type="button" data-toggle="collapse" data-target="#adminNavCollapse"
              aria-controls="adminNavCollapse" aria-expanded="false" aria-label="Toggle navigation">
          <span class="tg-bar"></span>
          <span class="tg-bar"></span>
          <span class="tg-bar"></span>
      </button>
      <div class="collapse navbar-collapse admin-collapse" id="adminNavCollapse">
          <ul class="navbar-nav">
              <li class="nav-item <?php echo $current_page == 'index.php' || $current_page == 'admin_dashboard.php' ? 'active' : ''; ?>">
                  <a class="nav-link nav-link-modern" href="admin_dashboard.php"><i class="fas fa-home"></i><span>Dashboard</span></a>
              </li>
              <li class="nav-item <?php echo in_array($current_page, ['show_users.php', 'update_user.php', 'add_details.php', 'delete_user.php']) ? 'active' : ''; ?>">
                  <a class="nav-link nav-link-modern" href="show_users.php"><i class="fas fa-users"></i><span>Users</span></a>
              </li>
              <li class="nav-item <?php echo in_array($current_page, ['showdata.php', 'update_application.php', 'view_cv.php', 'delete_application.php']) ? 'active' : ''; ?>">
                  <a class="nav-link nav-link-modern" href="showdata.php"><i class="fas fa-file-alt"></i><span>Applications</span></a>
              </li>
              <li class="nav-item <?php echo $current_page == 'add_details.php' ? 'active' : ''; ?>">
                  <a class="nav-link nav-link-modern" href="add_details.php"><i class="fas fa-user-plus"></i><span>Add User</span></a>
              </li>
              <li class="nav-item <?php echo $current_page == 'add_admin.php' ? 'active' : ''; ?>">
                  <a class="nav-link nav-link-modern" href="add_admin.php"><i class="fas fa-user-shield"></i><span>Add Admin</span></a>
              </li>
              <li class="nav-item <?php echo $current_page == 'ai_settings.php' ? 'active' : ''; ?>">
                  <a class="nav-link nav-link-modern" href="ai_settings.php"><i class="fas fa-robot"></i><span>AI Settings</span></a>
              </li>
          </ul>
          <ul class="navbar-nav ml-auto align-items-center">
              <li class="nav-item nd-icon-btn-wrap">
                  <a class="nav-link nav-link-modern position-relative nd-icon-btn" href="notifications.php" title="Notifications">
                      <i class="fas fa-bell"></i>
                      <?php if ($unread_notifs > 0): ?>
                          <span class="admin-badge"><?php echo $unread_notifs; ?></span>
                      <?php endif; ?>
                  </a>
              </li>
              <li class="nav-item mr-2">
                  <span class="admin-welcome">
                      <span class="aw-ico"><i class="fas fa-user-shield"></i></span>
                      Welcome, <strong><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $admin_username))); ?></strong>
                  </span>
              </li>
              <li class="nav-item mx-2 d-none d-sm-block">
                  <button class="btn-theme-toggle" type="button" title="Toggle Theme">
                      <i class="fas fa-moon" id="themeIcon"></i>
                  </button>
              </li>
              <li class="nav-item">
                  <a class="btn-admin-logout" href="../auth/logout.php">
                      <i class="fas fa-sign-out-alt"></i><span>Logout</span>
                  </a>
              </li>
          </ul>
      </div>
  </div>
</nav>

<script>
    (function () {
        var nav = document.querySelector('.admin-nav');

        function onScroll() {
            if (nav) nav.classList.toggle('scrolled', window.scrollY > 10);
        }
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();

        function currentTheme() {
            return localStorage.getItem('company-theme') === 'dark' ? 'dark' : 'light';
        }

        function applyTheme(theme) {
            document.documentElement.setAttribute('data-theme', theme);
            document.body.classList.toggle('dark-theme', theme === 'dark');
            var icon = document.getElementById('themeIcon');
            if (icon) {
                icon.classList.toggle('fa-sun', theme === 'dark');
                icon.classList.toggle('fa-moon', theme !== 'dark');
            }
        }

        function toggleTheme() {
            var next = currentTheme() === 'dark' ? 'light' : 'dark';
            localStorage.setItem('company-theme', next);
            applyTheme(next);
        }

        document.addEventListener('click', function (e) {
            if (e.target && e.target.closest && e.target.closest('.btn-theme-toggle')) {
                toggleTheme();
            }
        });

        document.addEventListener('DOMContentLoaded', function () {
            applyTheme(currentTheme());
        });
    })();
</script>
