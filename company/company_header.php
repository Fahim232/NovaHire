<?php
if (!isset($con)) {
    require_once '../admin/dbcon.php';
}
require_once '../includes/functions.php';

$current_page = basename($_SERVER['PHP_SELF']);
$company_name = $_SESSION['company_name'] ?? 'Company Dashboard';
$company_logo = $_SESSION['company_logo'] ?? '';
$company_id = $_SESSION['company_id'] ?? 0;

$unread_notifs = get_unread_count($con, 'company', $company_id);
$unread_messages = get_unread_message_count($con, 'company', $company_id);

$jobs_active = in_array($current_page, ['my_jobs.php', 'post_job.php']);
$applicants_active = in_array($current_page, ['view_applicants.php', 'category_applicants.php']);

$logo_src = $company_logo;
$logo_file = $company_logo;
if (!empty($company_logo)) {
    if (strpos($company_logo, '../') !== 0 && strpos($company_logo, 'uploads/') === 0) {
        $logo_src = '../' . $company_logo;
    }
    $logo_file = preg_replace('#^\.\./#', '', $company_logo);
}
$logo_exists = !empty($company_logo) && file_exists($logo_file);
?>

<nav class="navbar navbar-expand-lg company-navbar" id="companyNav">
    <div class="container-fluid px-4 px-lg-5">
        <a class="navbar-brand company-brand" href="index.php">
            <span class="company-brand-tile">
                <?php if ($logo_exists): ?>
                    <img src="<?php echo $logo_src; ?>" alt="<?php echo htmlspecialchars($company_name); ?>">
                <?php else: ?>
                    <i class="fas fa-building"></i>
                <?php endif; ?>
            </span>
            <span class="company-name-wrap">
                <span class="company-name-text"><?php echo htmlspecialchars($company_name); ?></span>
                <span class="company-name-sub">Recruiter Portal</span>
            </span>
        </a>

        <button class="navbar-toggler company-toggler" type="button" data-toggle="collapse" data-target="#companyNavbar"
                aria-controls="companyNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="tg-bar"></span>
            <span class="tg-bar"></span>
            <span class="tg-bar"></span>
        </button>

        <div class="collapse navbar-collapse company-collapse" id="companyNavbar">
            <ul class="navbar-nav ml-auto align-items-lg-center">
                <li class="nav-item <?php echo $current_page == 'index.php' ? 'active' : ''; ?>">
                    <a class="nav-link nav-link-modern" href="index.php">
                        <i class="fas fa-home"></i>
                        <span>Dashboard</span>
                    </a>
                </li>

                <li class="nav-item dropdown <?php echo $jobs_active ? 'active' : ''; ?>">
                    <a class="nav-link nav-link-modern dropdown-toggle" href="#" id="jobsDropdown"
                       role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="fas fa-briefcase"></i>
                        <span>Jobs</span>
                        <i class="fas fa-chevron-down nd-caret"></i>
                    </a>
                    <div class="dropdown-menu dropdown-menu-modern shadow-lg" aria-labelledby="jobsDropdown">
                        <a class="dropdown-item <?php echo $current_page == 'my_jobs.php' ? 'active' : ''; ?>" href="my_jobs.php">
                            <span class="dd-ico"><i class="fas fa-list"></i></span>
                            <span class="dd-txt">My Posted Jobs<small>Manage &amp; track listings</small></span>
                        </a>
                        <a class="dropdown-item <?php echo $current_page == 'post_job.php' ? 'active' : ''; ?>" href="post_job.php">
                            <span class="dd-ico"><i class="fas fa-plus-circle"></i></span>
                            <span class="dd-txt">Post New Job<small>Create a fresh listing</small></span>
                        </a>
                    </div>
                </li>

                <li class="nav-item dropdown <?php echo $applicants_active ? 'active' : ''; ?>">
                    <a class="nav-link nav-link-modern dropdown-toggle" href="#" id="applicantsDropdown"
                       role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="fas fa-users"></i>
                        <span>Applicants</span>
                        <i class="fas fa-chevron-down nd-caret"></i>
                    </a>
                    <div class="dropdown-menu dropdown-menu-modern shadow-lg" aria-labelledby="applicantsDropdown">
                        <a class="dropdown-item <?php echo $current_page == 'view_applicants.php' ? 'active' : ''; ?>" href="view_applicants.php">
                            <span class="dd-ico"><i class="fas fa-file-alt"></i></span>
                            <span class="dd-txt">Job Applicants<small>Review applications</small></span>
                        </a>
                        <a class="dropdown-item <?php echo $current_page == 'category_applicants.php' ? 'active' : ''; ?>" href="category_applicants.php">
                            <span class="dd-ico"><i class="fas fa-user-graduate"></i></span>
                            <span class="dd-txt">Category Applicants<small>Browse by category</small></span>
                        </a>
                    </div>
                </li>

                <li class="nav-item <?php echo $current_page == 'profile.php' ? 'active' : ''; ?>">
                    <a class="nav-link nav-link-modern" href="profile.php">
                        <i class="fas fa-user-circle"></i>
                        <span>Profile</span>
                    </a>
                </li>

                <li class="nav-item nd-icon-btn-wrap">
                    <a class="nav-link nav-link-modern position-relative nd-icon-btn" href="../notifications.php" title="Notifications">
                        <i class="fas fa-bell"></i>
                        <?php if ($unread_notifs > 0): ?>
                            <span class="company-badge"><?php echo $unread_notifs; ?></span>
                        <?php endif; ?>
                    </a>
                </li>

                <li class="nav-item nd-icon-btn-wrap">
                    <a class="nav-link nav-link-modern position-relative nd-icon-btn" href="../message_center.php" title="Messages">
                        <i class="fas fa-envelope"></i>
                        <?php if ($unread_messages > 0): ?>
                            <span class="company-badge msg"><?php echo $unread_messages; ?></span>
                        <?php endif; ?>
                    </a>
                </li>

                <li class="nav-item <?php echo $current_page == 'live_chat.php' ? 'active' : ''; ?>">
                    <a class="nav-link nav-link-modern nd-live" href="live_chat.php" title="Live Chat">
                        <i class="fas fa-comment-dots"></i>
                        <span>Live Chat</span>
                        <span class="nd-live-dot"></span>
                    </a>
                </li>

                <li class="nav-item mx-2 d-none d-sm-block">
                    <button class="btn-theme-toggle" type="button" title="Toggle Theme">
                        <i class="fas fa-moon" id="themeIcon"></i>
                    </button>
                </li>

                <li class="nav-item">
                    <a class="btn btn-logout-modern" href="logout.php">
                        <i class="fas fa-sign-out-alt"></i><span>Logout</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<style>
    @keyframes badge-pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.18); }
    }
    @keyframes nd-ping {
        0% { transform: scale(1); opacity: .55; }
        75%, 100% { transform: scale(1.9); opacity: 0; }
    }
    @keyframes nd-live-ring {
        0% { transform: scale(.6); opacity: .85; }
        100% { transform: scale(1.9); opacity: 0; }
    }
    @keyframes nd-drop {
        from { opacity: 0; transform: translateY(-8px) scale(.97); }
        to   { opacity: 1; transform: translateY(0) scale(1); }
    }

    .company-navbar {
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
    [data-theme="dark"] .company-navbar {
        background: rgba(15, 23, 42, 0.8);
        border-bottom-color: rgba(51, 65, 85, 0.55);
    }
    .company-navbar.scrolled {
        box-shadow: 0 12px 32px -14px rgba(15, 23, 42, 0.45);
    }

    /* ── Brand ── */
    .company-brand {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 0;
        margin: 0;
    }
    .company-brand-tile {
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
        overflow: hidden;
        transition: transform .3s ease, box-shadow .3s ease;
        flex-shrink: 0;
    }
    .company-brand-tile img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .company-brand:hover .company-brand-tile {
        transform: rotate(-6deg) scale(1.06);
        box-shadow: 0 10px 20px -6px rgba(99, 102, 241, 0.75);
    }
    .company-name-wrap {
        display: flex;
        flex-direction: column;
        line-height: 1.2;
    }
    .company-name-text {
        font-family: 'Sora', 'Manrope', 'Inter', sans-serif;
        font-weight: 800;
        font-size: 1.02rem;
        letter-spacing: -0.01em;
        color: var(--text);
        white-space: nowrap;
        max-width: 220px;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .company-name-sub {
        font-size: .66rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .1em;
        color: var(--text-muted);
    }

    /* ── Nav links ── */
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
    .dropdown-toggle.nav-link-modern::after { display: none; }

    .nav-item.active > .nav-link-modern {
        background: linear-gradient(135deg, #6366f1, #8b5cf6);
        color: #fff !important;
        box-shadow: 0 6px 16px -6px rgba(99, 102, 241, 0.65);
    }
    .nav-item.active > .nav-link-modern i { color: #fff; }

    .nd-caret {
        font-size: .66rem;
        margin-left: 2px;
        color: inherit;
        opacity: .7;
        transition: transform .3s ease;
        width: auto !important;
    }
    .dropdown.show .nd-caret { transform: rotate(180deg); }

    /* ── Dropdowns ── */
    .dropdown-menu-modern {
        border: 1px solid var(--border-light);
        border-radius: 14px;
        background: var(--bg-card);
        box-shadow: 0 22px 44px -12px rgba(15, 23, 42, 0.28);
        padding: 8px;
        margin-top: 10px;
        min-width: 240px;
        animation: nd-drop .22s ease;
    }
    .dropdown-menu-modern .dropdown-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 9px 12px;
        border-radius: 10px;
        font-weight: 600;
        font-size: .86rem;
        color: var(--text);
        transition: background .25s ease, color .25s ease, transform .25s ease;
    }
    .dropdown-menu-modern .dropdown-item:hover {
        background: var(--bg-hover);
        color: var(--primary);
        transform: translateX(3px);
    }
    .dropdown-menu-modern .dropdown-item.active {
        background: rgba(99, 102, 241, 0.1);
        color: var(--primary);
    }
    .dropdown-menu-modern .dd-ico {
        width: 34px;
        height: 34px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: var(--bg-hover);
        color: var(--primary);
        font-size: .9rem;
        flex-shrink: 0;
        transition: background .25s ease, color .25s ease, transform .25s ease;
    }
    .dropdown-menu-modern .dropdown-item:hover .dd-ico {
        background: linear-gradient(135deg, #6366f1, #8b5cf6);
        color: #fff;
        transform: scale(1.08);
    }
    .dropdown-menu-modern .dd-txt {
        display: flex;
        flex-direction: column;
        line-height: 1.25;
    }
    .dropdown-menu-modern .dd-txt small {
        font-weight: 500;
        font-size: .72rem;
        color: var(--text-muted);
    }

    /* ── Icon buttons (bell / envelope) ── */
    .nav-item.nd-icon-btn-wrap { margin: 6px 3px; }
    .nav-link-modern.nd-icon-btn {
        width: 40px;
        height: 40px;
        padding: 0 !important;
        justify-content: center;
        background: var(--bg-hover);
    }
    .nav-link-modern.nd-icon-btn i { width: auto; }
    .nav-link-modern.nd-icon-btn:hover {
        background: linear-gradient(135deg, #6366f1, #8b5cf6);
    }
    .nav-link-modern.nd-icon-btn:hover i { color: #fff; }

    .company-badge {
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
    .company-badge::before {
        content: '';
        position: absolute;
        inset: -4px;
        border-radius: 999px;
        background: inherit;
        opacity: .45;
        z-index: -1;
        animation: nd-ping 1.8s cubic-bezier(0, 0, .2, 1) infinite;
    }
    .company-badge.msg {
        background: linear-gradient(135deg, #8b5cf6, #6366f1);
        box-shadow: 0 3px 8px -2px rgba(139, 92, 246, 0.6);
    }

    /* ── Live chat ── */
    .nav-link-modern.nd-live { color: #059669 !important; }
    .nav-link-modern.nd-live i { color: #10b981; }
    .nav-item.active > .nav-link-modern.nd-live { color: #fff !important; }
    .nav-item.active > .nav-link-modern.nd-live i { color: #fff; }
    .nd-live-dot {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: #10b981;
        position: relative;
        flex-shrink: 0;
    }
    .nd-live-dot::after {
        content: '';
        position: absolute;
        inset: -4px;
        border-radius: 50%;
        background: rgba(16, 185, 129, 0.55);
        animation: nd-live-ring 1.6s ease-out infinite;
    }

    /* ── Theme toggle ── */
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

    /* ── Logout ── */
    .btn-logout-modern {
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
    .btn-logout-modern:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 22px -8px rgba(239, 68, 68, 0.65);
        color: #fff !important;
    }

    /* ── Mobile toggler ── */
    .company-toggler {
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
    .company-toggler .tg-bar {
        width: 20px;
        height: 2px;
        border-radius: 2px;
        background: var(--text);
        transition: transform .3s ease, opacity .3s ease, background .3s ease;
    }
    .company-toggler[aria-expanded="true"] .tg-bar:nth-child(1) { transform: translateY(7px) rotate(45deg); background: var(--primary); }
    .company-toggler[aria-expanded="true"] .tg-bar:nth-child(2) { opacity: 0; }
    .company-toggler[aria-expanded="true"] .tg-bar:nth-child(3) { transform: translateY(-7px) rotate(-45deg); background: var(--primary); }

    /* ── Responsive ── */
    @media (max-width: 991.98px) {
        .company-navbar { padding: 8px 0; }
        .company-collapse {
            margin-top: 10px;
            padding: 14px;
            border-radius: 16px;
            background: var(--bg-card);
            border: 1px solid var(--border-light);
            box-shadow: 0 22px 44px -12px rgba(15, 23, 42, 0.3);
        }
        .company-collapse .nav-link-modern { margin: 4px 0; }
        .nav-item.dropdown .dropdown-menu-modern {
            box-shadow: none;
            margin: 4px 0 4px 14px;
            padding: 4px;
            border: none;
            background: transparent;
            animation: none;
        }
        .dropdown-menu-modern .dropdown-item { padding: 8px 10px; }
        .btn-logout-modern, .btn-theme-toggle { margin: 8px 0; width: 100%; }
        .btn-theme-toggle { justify-content: center; }
        .nav-item.nd-icon-btn-wrap { margin: 4px 0; }
        .nav-link-modern.nd-icon-btn { width: 100%; height: 42px; justify-content: center; }
        .company-name-text { max-width: 160px; }
    }
</style>

<script>
    (function () {
        var nav = document.querySelector('.company-navbar');

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
