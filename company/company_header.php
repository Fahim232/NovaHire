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
    // Normalize to a path relative to this company/ directory.
    // Accepts a bare filename (site convention), "uploads/..." or "../uploads/...".
    if (strpos($company_logo, '../') !== 0 && strpos($company_logo, 'uploads/') !== 0) {
        $logo_src = '../uploads/company_logos/' . $company_logo;
    } elseif (strpos($company_logo, 'uploads/') === 0) {
        $logo_src = '../' . $company_logo;
    }
    $logo_file = $logo_src;
}
$logo_exists = !empty($company_logo) && file_exists($logo_file);

$company_initial = mb_strtoupper(mb_substr(trim($company_name), 0, 1));
?>

<nav class="cmp-nav" id="cmpNav">
    <div class="cmp-nav-inner">
        <!-- Brand -->
        <a class="cmp-brand" href="index.php">
            <span class="cmp-brand-tile">
                <?php if ($logo_exists): ?>
                    <img src="<?php echo $logo_src; ?>" alt="<?php echo htmlspecialchars($company_name); ?>">
                <?php else: ?>
                    <span><?php echo htmlspecialchars($company_initial); ?></span>
                <?php endif; ?>
            </span>
            <span class="cmp-brand-txt">
                <span class="cmp-brand-name"><?php echo htmlspecialchars($company_name); ?></span>
                <span class="cmp-brand-sub">Company Portal</span>
            </span>
        </a>

        <!-- Toggler (mobile) -->
        <button class="cmp-toggler" type="button" id="cmpToggler"
                aria-controls="cmpNavMenu" aria-expanded="false" aria-label="Toggle navigation">
            <span class="cmp-tg-bar"></span>
            <span class="cmp-tg-bar"></span>
            <span class="cmp-tg-bar"></span>
        </button>

        <!-- Menu -->
        <div class="cmp-menu" id="cmpNavMenu">
            <ul class="cmp-links">
                <li class="cmp-item <?php echo $current_page == 'index.php' ? 'is-active' : ''; ?>">
                    <a class="cmp-link" href="index.php"><i class="fas fa-grip"></i><span>Dashboard</span></a>
                </li>

                <li class="cmp-item cmp-drop <?php echo $jobs_active ? 'is-active' : ''; ?>">
                    <a class="cmp-link" href="#" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="fas fa-briefcase"></i><span>Jobs</span><i class="fas fa-chevron-down cmp-caret"></i>
                    </a>
                    <div class="cmp-dropdown">
                        <a class="cmp-drop-item <?php echo $current_page == 'my_jobs.php' ? 'is-active' : ''; ?>" href="my_jobs.php">
                            <span class="cmp-drop-ico"><i class="fas fa-layer-group"></i></span>
                            <span class="cmp-drop-txt">My Posted Jobs<small>Manage &amp; track listings</small></span>
                        </a>
                        <a class="cmp-drop-item <?php echo $current_page == 'post_job.php' ? 'is-active' : ''; ?>" href="post_job.php">
                            <span class="cmp-drop-ico"><i class="fas fa-plus"></i></span>
                            <span class="cmp-drop-txt">Post New Job<small>Create a fresh listing</small></span>
                        </a>
                    </div>
                </li>

                <li class="cmp-item cmp-drop <?php echo $applicants_active ? 'is-active' : ''; ?>">
                    <a class="cmp-link" href="#" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="fas fa-users"></i><span>Applicants</span><i class="fas fa-chevron-down cmp-caret"></i>
                    </a>
                    <div class="cmp-dropdown">
                        <a class="cmp-drop-item <?php echo $current_page == 'view_applicants.php' ? 'is-active' : ''; ?>" href="view_applicants.php">
                            <span class="cmp-drop-ico"><i class="fas fa-file-lines"></i></span>
                            <span class="cmp-drop-txt">Job Applicants<small>Review applications</small></span>
                        </a>
                        <a class="cmp-drop-item <?php echo $current_page == 'category_applicants.php' ? 'is-active' : ''; ?>" href="category_applicants.php">
                            <span class="cmp-drop-ico"><i class="fas fa-user-graduate"></i></span>
                            <span class="cmp-drop-txt">Category Applicants<small>Browse by category</small></span>
                        </a>
                    </div>
                </li>

                <li class="cmp-item <?php echo $current_page == 'live_chat.php' ? 'is-active' : ''; ?>">
                    <a class="cmp-link" href="live_chat.php" style="position:relative;"><i class="fas fa-comments"></i><span>Live Chat</span><span class="cmp-badge" id="lcNotifBadge" style="display:none;">0</span></a>
                </li>
            </ul>

            <div class="cmp-actions">
                <button class="cmp-ghost cmp-theme" type="button" title="Toggle theme" aria-label="Toggle theme">
                    <i class="fas fa-moon" id="cmpThemeIcon"></i>
                </button>

                <a class="cmp-ghost" href="notifications.php" title="Notifications">
                    <i class="fas fa-bell"></i>
                    <?php if ($unread_notifs > 0): ?><span class="cmp-badge"><?php echo $unread_notifs; ?></span><?php endif; ?>
                </a>

                <a class="cmp-ghost" href="message_center.php" title="Messages">
                    <i class="fas fa-envelope"></i>
                    <?php if ($unread_messages > 0): ?><span class="cmp-badge cmp-badge-violet"><?php echo $unread_messages; ?></span><?php endif; ?>
                </a>

                <!-- User menu -->
                <div class="cmp-user cmp-drop">
                    <a class="cmp-user-btn" href="#" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <span class="cmp-avatar">
                            <?php if ($logo_exists): ?>
                                <img src="<?php echo $logo_src; ?>" alt="">
                            <?php else: ?>
                                <span><?php echo htmlspecialchars($company_initial); ?></span>
                            <?php endif; ?>
                        </span>
                        <span class="cmp-user-txt"><?php echo htmlspecialchars($company_name); ?></span>
                        <i class="fas fa-chevron-down cmp-caret"></i>
                    </a>
                    <div class="cmp-dropdown cmp-user-drop">
                        <div class="cmp-user-head">
                            <span class="cmp-avatar"><?php echo htmlspecialchars($company_initial); ?></span>
                            <span class="cmp-user-txt">
                                <strong><?php echo htmlspecialchars($company_name); ?></strong>
                                <small>Recruiter</small>
                            </span>
                        </div>
                        <div class="cmp-drop-sep"></div>
                        <a class="cmp-drop-item" href="profile.php">
                            <span class="cmp-drop-ico"><i class="fas fa-user-gear"></i></span>
                            <span class="cmp-drop-txt">Company Profile</span>
                        </a>
                        <a class="cmp-drop-item" href="message_center.php">
                            <span class="cmp-drop-ico"><i class="fas fa-envelope-open-text"></i></span>
                            <span class="cmp-drop-txt">Messages</span>
                        </a>
                        <div class="cmp-drop-sep"></div>
                        <a class="cmp-drop-item cmp-danger" href="logout.php">
                            <span class="cmp-drop-ico"><i class="fas fa-sign-out-alt"></i></span>
                            <span class="cmp-drop-txt">Logout</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</nav>

<style>
    /* ═══ NovaHire — Company Nav (minimal, refined) ═══ */
    .cmp-nav {
        --bg: #ffffff;
        --bg-card: #ffffff;
        --bg-hover: #f1f5f9;
        --border-light: #e2e8f0;
        --text: #1e293b;
        --text-muted: #64748b;
        --primary: #4f46e5;
        --danger: #ef4444;
    }
    [data-theme="dark"] .cmp-nav {
        --bg: #0f172a;
        --bg-card: #111827;
        --bg-hover: #1e293b;
        --border-light: #334155;
        --text: #e8edff;
        --text-muted: #94a3b8;
        --primary: #8b5cf6;
        --danger: #f87171;
    }
    .cmp-nav {
        position: sticky;
        top: 0;
        z-index: 1030;
        background: var(--bg);
        border-bottom: 1px solid var(--border-light);
        transition: box-shadow .35s ease, background .35s ease;
    }
    .cmp-nav.is-scrolled {
        box-shadow: 0 10px 30px -18px rgba(15, 23, 42, .35);
    }
    [data-theme="dark"] .cmp-nav {
        background: var(--bg);
        border-bottom-color: rgba(51, 65, 85, .5);
    }

    .cmp-nav-inner {
        display: flex;
        align-items: center;
        gap: 18px;
        height: 66px;
        padding: 0 26px;
        max-width: 1440px;
        margin: 0 auto;
    }

    /* ── Brand ── */
    .cmp-brand {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-shrink: 0;
        text-decoration: none !important;
    }
    .cmp-brand-tile {
        width: 40px;
        height: 40px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(140deg, #6366f1, #8b5cf6);
        color: #fff;
        font-family: 'Sora', sans-serif;
        font-weight: 700;
        font-size: 1rem;
        overflow: hidden;
        box-shadow: 0 8px 18px -8px rgba(99, 102, 241, .55);
        transition: transform .3s ease, box-shadow .3s ease;
    }
    .cmp-brand-tile img { width: 100%; height: 100%; object-fit: cover; }
    .cmp-brand:hover .cmp-brand-tile { transform: translateY(-1px) scale(1.03); }
    .cmp-brand-txt { display: flex; flex-direction: column; line-height: 1.15; min-width: 0; }
    .cmp-brand-name {
        font-family: 'Sora', 'Manrope', sans-serif;
        font-weight: 700;
        font-size: .95rem;
        letter-spacing: -.01em;
        color: var(--text);
        white-space: nowrap;
        max-width: 200px;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .cmp-brand-sub {
        font-size: .6rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .12em;
        color: var(--text-muted);
    }

    /* ── Layout split ── */
    .cmp-menu { display: flex; align-items: center; gap: 10px; flex: 1; min-width: 0; }
    .cmp-links { display: flex; align-items: center; gap: 4px; list-style: none; margin: 0; padding: 0; }
    .cmp-actions {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-left: auto;
        padding-left: 12px;
        border-left: 1px solid var(--border-light);
    }

    /* ── Nav links ── */
    .cmp-link {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 14px;
        border-radius: 10px;
        color: var(--text) !important;
        font-size: .85rem;
        font-weight: 600;
        text-decoration: none !important;
        position: relative;
        transition: background .25s ease, color .25s ease;
    }
    .cmp-link i { font-size: .9rem; width: 16px; text-align: center; color: var(--text-muted); transition: color .25s ease, transform .25s ease; }
    .cmp-link:hover { background: var(--bg-hover); color: var(--primary) !important; }
    .cmp-link:hover i { color: var(--primary); transform: scale(1.08); }
    .cmp-caret {
        font-size: .58rem !important;
        width: auto !important;
        opacity: .65;
        margin-left: 2px;
        transition: transform .3s ease;
    }
    .cmp-drop:hover .cmp-caret, .cmp-drop.show .cmp-caret { transform: rotate(180deg); }

    .cmp-item.is-active > .cmp-link {
        background: rgba(99, 102, 241, .1);
        color: var(--primary) !important;
    }
    .cmp-item.is-active > .cmp-link i { color: var(--primary); }
    .cmp-item.is-active > .cmp-link::after {
        content: '';
        position: absolute;
        left: 14px;
        right: 14px;
        bottom: 2px;
        height: 2px;
        border-radius: 2px;
        background: var(--primary);
        opacity: .8;
    }

    /* ── Dropdowns ── */
    .cmp-drop { position: relative; }
    .cmp-dropdown {
        position: absolute;
        top: calc(100% + 8px);
        left: 0;
        min-width: 250px;
        padding: 8px;
        background: var(--bg-card);
        border: 1px solid var(--border-light);
        border-radius: 14px;
        box-shadow: 0 20px 45px -14px rgba(15, 23, 42, .3);
        opacity: 0;
        visibility: hidden;
        transform: translateY(8px);
        transition: opacity .22s ease, transform .22s ease, visibility .22s;
        z-index: 1050;
    }
    .cmp-drop:hover .cmp-dropdown,
    .cmp-drop.show .cmp-dropdown { opacity: 1; visibility: visible; transform: translateY(0); }

    .cmp-drop-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 9px 12px;
        border-radius: 10px;
        color: var(--text) !important;
        font-size: .85rem;
        font-weight: 600;
        text-decoration: none !important;
        transition: background .2s ease, color .2s ease;
    }
    .cmp-drop-item:hover { background: var(--bg-hover); color: var(--primary) !important; }
    .cmp-drop-item.is-active { background: rgba(99, 102, 241, .1); color: var(--primary) !important; }
    .cmp-drop-ico {
        width: 32px;
        height: 32px;
        border-radius: 9px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: var(--bg-hover);
        color: var(--primary);
        font-size: .82rem;
        flex-shrink: 0;
        transition: background .2s ease, color .2s ease;
    }
    .cmp-drop-item:hover .cmp-drop-ico { background: rgba(99, 102, 241, .14); }
    .cmp-drop-txt { display: flex; flex-direction: column; line-height: 1.25; min-width: 0; }
    .cmp-drop-txt small { font-weight: 500; font-size: .7rem; color: var(--text-muted); }
    .cmp-drop-sep { height: 1px; margin: 6px 4px; background: var(--border-light); }
    .cmp-danger { color: var(--danger) !important; }
    .cmp-danger .cmp-drop-ico { color: var(--danger); background: rgba(239, 68, 68, .1); }
    .cmp-danger:hover { color: var(--danger) !important; background: rgba(239, 68, 68, .08); }

    /* ── Ghost icon buttons ── */
    .cmp-ghost {
        position: relative;
        width: 38px;
        height: 38px;
        border: none;
        border-radius: 11px;
        background: var(--bg-hover);
        color: var(--text);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: .88rem;
        text-decoration: none !important;
        cursor: pointer;
        transition: background .25s ease, color .25s ease, transform .25s ease;
    }
    .cmp-ghost:hover { background: rgba(99, 102, 241, .12); color: var(--primary); transform: translateY(-1px); }
    .cmp-ghost i { transition: transform .35s ease; }
    .cmp-ghost:hover i { transform: scale(1.08) rotate(-4deg); }
    .cmp-theme:hover i { transform: rotate(25deg) scale(1.05); }

    .cmp-badge {
        position: absolute;
        top: -4px;
        right: -4px;
        min-width: 17px;
        height: 17px;
        padding: 0 5px;
        border-radius: 999px;
        background: linear-gradient(140deg, #ef4444, #f97316);
        color: #fff;
        font-size: .58rem;
        font-weight: 800;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 3px 8px -3px rgba(239, 68, 68, .6);
    }
    .cmp-badge-violet { background: linear-gradient(140deg, #8b5cf6, #6366f1); box-shadow: 0 3px 8px -3px rgba(139, 92, 246, .6); }

    /* ── User menu ── */
    .cmp-user { margin-left: 2px; }
    .cmp-user-btn {
        display: flex;
        align-items: center;
        gap: 9px;
        padding: 5px 10px 5px 5px;
        border-radius: 999px;
        border: 1px solid var(--border-light);
        background: transparent;
        text-decoration: none !important;
        transition: border-color .25s ease, background .25s ease;
    }
    .cmp-user-btn:hover { background: var(--bg-hover); border-color: rgba(99, 102, 241, .35); }
    .cmp-avatar {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(140deg, #6366f1, #8b5cf6);
        color: #fff;
        font-family: 'Sora', sans-serif;
        font-weight: 700;
        font-size: .82rem;
        overflow: hidden;
        flex-shrink: 0;
    }
    .cmp-avatar img { width: 100%; height: 100%; object-fit: cover; }
    .cmp-user-txt {
        font-size: .8rem;
        font-weight: 700;
        color: var(--text);
        white-space: nowrap;
        max-width: 130px;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .cmp-user-btn .cmp-caret { font-size: .58rem; }
    .cmp-user-drop { right: 0; left: auto; min-width: 240px; }
    .cmp-user-head {
        display: flex;
        align-items: center;
        gap: 11px;
        padding: 10px 12px;
    }
    .cmp-user-head .cmp-user-txt { display: flex; flex-direction: column; line-height: 1.2; max-width: 160px; }
    .cmp-user-head strong { font-size: .82rem; font-weight: 700; color: var(--text); }
    .cmp-user-head small { font-size: .68rem; font-weight: 600; color: var(--text-muted); }

    /* ── Mobile toggler ── */
    .cmp-toggler {
        display: flex;
        width: 40px;
        height: 40px;
        border-radius: 11px;
        background: var(--bg-hover);
        border: 1px solid var(--border-light);
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 4px;
        cursor: pointer;
        margin-left: auto;
        order: 2;
    }    .cmp-tg-bar {
        width: 17px;
        height: 2px;
        border-radius: 2px;
        background: var(--text);
        transition: transform .3s ease, opacity .3s ease, background .3s ease;
    }
    .cmp-toggler[aria-expanded="true"] .cmp-tg-bar:nth-child(1) { transform: translateY(6px) rotate(45deg); background: var(--primary); }
    .cmp-toggler[aria-expanded="true"] .cmp-tg-bar:nth-child(2) { opacity: 0; }
    .cmp-toggler[aria-expanded="true"] .cmp-tg-bar:nth-child(3) { transform: translateY(-6px) rotate(-45deg); background: var(--primary); }

    /* ── Responsive ── */
    @media (max-width: 991.98px) {
        .cmp-nav-inner { height: auto; min-height: 66px; padding: 10px 16px; flex-wrap: wrap; }
        .cmp-toggler { display: flex; }
        .cmp-menu {
            display: none;
            width: 100%;
            flex-direction: column;
            align-items: stretch;
            gap: 4px;
            padding: 14px;
            margin-top: 8px;
            border-radius: 16px;
            background: var(--bg-card);
            border: 1px solid var(--border-light);
        }
        .cmp-menu.show { display: flex; }
        .cmp-links { flex-direction: column; align-items: stretch; width: 100%; }
        .cmp-item.is-active > .cmp-link::after { display: none; }
        .cmp-link { padding: 11px 14px; }
        .cmp-actions {
            margin-left: 0;
            padding-left: 0;
            border-left: none;
            flex-wrap: wrap;
            width: 100%;
            padding-top: 10px;
            margin-top: 4px;
            border-top: 1px solid var(--border-light);
        }
        .cmp-user { margin-left: 0; }
        .cmp-user-btn { width: 100%; }
        .cmp-dropdown, .cmp-user-drop {
            position: static;
            opacity: 1;
            visibility: visible;
            transform: none;
            box-shadow: none;
            border: none;
            background: transparent;
            padding: 4px 0 4px 12px;
            min-width: 0;
        }
    }
</style>

<script>
    (function () {
        var nav = document.getElementById('cmpNav');
        function onScroll() { if (nav) nav.classList.toggle('is-scrolled', window.scrollY > 10); }
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();

        // Hamburger toggle (independent of Bootstrap collapse)
        var toggler = document.getElementById('cmpToggler');
        var menu = document.getElementById('cmpNavMenu');
        if (toggler && menu) {
            toggler.addEventListener('click', function () {
                var open = menu.classList.toggle('show');
                toggler.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
            document.addEventListener('click', function (e) {
                if (menu.classList.contains('show') &&
                    !menu.contains(e.target) && !toggler.contains(e.target)) {
                    menu.classList.remove('show');
                    toggler.setAttribute('aria-expanded', 'false');
                }
            });
        }

        var KEY = 'company-theme';
        function currentTheme() { return localStorage.getItem(KEY) === 'dark' ? 'dark' : 'light'; }
        function applyTheme(t) {
            document.documentElement.setAttribute('data-theme', t);
            document.body.classList.toggle('dark-theme', t === 'dark');
            var icon = document.getElementById('cmpThemeIcon');
            if (icon) {
                icon.classList.toggle('fa-sun', t === 'dark');
                icon.classList.toggle('fa-moon', t !== 'dark');
            }
        }
        function toggleTheme() {
            var next = currentTheme() === 'dark' ? 'light' : 'dark';
            localStorage.setItem(KEY, next);
            applyTheme(next);
        }
        document.addEventListener('click', function (e) {
            if (e.target && e.target.closest && e.target.closest('.cmp-theme')) toggleTheme();
        });
        document.addEventListener('DOMContentLoaded', function () { applyTheme(currentTheme()); });
    })();
</script>

<style>
    #lcToastWrap { position: fixed; top: 20px; right: 20px; z-index: 99999; display: flex; flex-direction: column; gap: 10px; max-width: 340px; }
    .lc-toast {
        display: flex; align-items: flex-start; gap: 12px;
        background: #fff; border: 1px solid #e5e9f2; border-radius: 16px;
        padding: 14px 16px; box-shadow: 0 18px 45px -18px rgba(15, 23, 42, 0.35);
        cursor: pointer; animation: lcToastIn 0.35s ease; transition: opacity 0.3s, transform 0.3s;
    }
    .lc-toast.lc-out { opacity: 0; transform: translateX(20px); }
    .lc-toast-ico {
        width: 42px; height: 42px; flex-shrink: 0; border-radius: 13px; overflow: hidden;
        background: linear-gradient(135deg, #4f46e5, #7c3aed);
        display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 800; font-size: 1rem;
    }
    .lc-toast-ico img { width: 100%; height: 100%; object-fit: cover; }
    .lc-toast-body { flex: 1; min-width: 0; }
    .lc-toast-body h6 { margin: 0 0 3px; font-size: 0.86rem; font-weight: 800; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .lc-toast-body p { margin: 0; font-size: 0.8rem; color: #64748b; line-height: 1.45; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .lc-toast-close { background: none; border: none; color: #94a3b8; cursor: pointer; font-size: 1rem; padding: 2px; flex-shrink: 0; line-height: 1; }
    @keyframes lcToastIn { from { transform: translateX(30px); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
</style>
<div id="lcToastWrap"></div>
<script>
(function () {
    function lcToast(name, msg, logo, link) {
        var wrap = document.getElementById('lcToastWrap');
        var t = document.createElement('div');
        t.className = 'lc-toast';
        var ico = logo && logo !== ''
            ? '<div class="lc-toast-ico"><img src="' + logo + '" alt=""></div>'
            : '<div class="lc-toast-ico">' + (name.charAt(0).toUpperCase() || '<i class="fas fa-comment"></i>') + '</div>';
        t.innerHTML = ico +
            '<div class="lc-toast-body"><h6>' + name + '</h6><p>' + msg + '</p></div>' +
            '<button class="lc-toast-close" onclick="event.stopPropagation();this.parentElement.remove()">&times;</button>';
        t.addEventListener('click', function () {
            window.location.href = link;
        });
        wrap.appendChild(t);
        setTimeout(function () {
            t.classList.add('lc-out');
            setTimeout(function () { t.remove(); }, 300);
        }, 7000);
    }

    function lcPoll() {
        fetch('../api/live_chat_alerts.php', { cache: 'no-store' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success || !data.alerts || !data.alerts.length) return;
                var badge = document.getElementById('lcNotifBadge');
                var missed = 0;
                data.alerts.forEach(function (a) {
                    var activeId = (window.LC_ACTIVE_ID !== undefined && window.LC_ACTIVE_ID !== null) ? parseInt(window.LC_ACTIVE_ID, 10) : null;
                    if (activeId !== null && parseInt(a.sender_id, 10) === activeId) return;
                    missed++;
                    var logo = (a.sender_logo && a.sender_logo !== '') ? '../uploads/company_logos/' + a.sender_logo : '';
                    lcToast(a.sender_name, a.message, logo, 'live_chat.php');
                });
                if (missed > 0 && badge) {
                    var prev = parseInt(badge.textContent, 10) || 0;
                    badge.textContent = prev + missed;
                    badge.style.display = 'flex';
                }
            })
            .catch(function () {});
    }
    lcPoll();
    setInterval(lcPoll, 10000);
})();
</script>
