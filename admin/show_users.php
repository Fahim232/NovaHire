<?php
session_start();
if (!isset($_SESSION['admin_username'])) {
    echo '<script>alert("You are logged out!"); window.location.href="admin_login.php";</script>';
    exit();
}

require_once 'dbcon.php';

$records_per_page = 8;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;

$tr = mysqli_query($con, "SELECT COUNT(*) AS c FROM user_info");
$total_records = $tr ? (int)mysqli_fetch_assoc($tr)['c'] : 0;
$total_pages = max(1, (int)ceil($total_records / $records_per_page));
if ($page > $total_pages) $page = $total_pages;
$start_from = ($page - 1) * $records_per_page;

$rows = [];
$pr = mysqli_query($con, "SELECT * FROM user_info ORDER BY id DESC LIMIT $start_from, $records_per_page");
if ($pr) {
    while ($r = mysqli_fetch_assoc($pr)) $rows[] = $r;
}

$stats = ['total' => $total_records, 'degree' => 0, 'skills' => 0, 'photo' => 0];
if ($q = mysqli_query($con, "SELECT COUNT(*) c FROM user_info WHERE user_degree IS NOT NULL AND user_degree != ''")) $stats['degree'] = (int)mysqli_fetch_assoc($q)['c'];
if ($q = mysqli_query($con, "SELECT COUNT(*) c FROM user_info WHERE user_skills IS NOT NULL AND user_skills != ''")) $stats['skills'] = (int)mysqli_fetch_assoc($q)['c'];
if ($q = mysqli_query($con, "SELECT COUNT(*) c FROM user_info WHERE profile IS NOT NULL AND profile != ''")) $stats['photo'] = (int)mysqli_fetch_assoc($q)['c'];

function su_initials($name) {
    $parts = preg_split('/\s+/', trim($name));
    $initials = '';
    foreach (array_slice($parts, 0, 2) as $p) { if ($p !== '') $initials .= strtoupper(mb_substr($p, 0, 1)); }
    return $initials !== '' ? $initials : '?';
}
function su_avatar_style($id) {
    $grads = [
        'linear-gradient(135deg,#6366f1,#818cf8)',
        'linear-gradient(135deg,#8b5cf6,#a78bfa)',
        'linear-gradient(135deg,#0ea5e9,#38bdf8)',
        'linear-gradient(135deg,#10b981,#34d399)',
        'linear-gradient(135deg,#f59e0b,#fbbf24)',
        'linear-gradient(135deg,#ec4899,#f472b6)',
    ];
    return $grads[$id % count($grads)];
}
function su_badge_style($str) {
    $palette = [
        ['bg' => 'rgba(99,102,241,.12)', 'fg' => '#4f46e5'],
        ['bg' => 'rgba(139,92,246,.12)', 'fg' => '#7c3aed'],
        ['bg' => 'rgba(14,165,233,.12)', 'fg' => '#0284c7'],
        ['bg' => 'rgba(16,185,129,.12)', 'fg' => '#059669'],
        ['bg' => 'rgba(245,158,11,.14)', 'fg' => '#b45309'],
        ['bg' => 'rgba(236,72,153,.12)', 'fg' => '#be185d'],
    ];
    return $palette[crc32($str) % count($palette)];
}
function su_photo_exists($profile) {
    return $profile !== '' && file_exists(__DIR__ . '/../images/' . $profile);
}

include 'header.php';
?>

<style>
    @keyframes su-reveal { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: none; } }
    .su-reveal { opacity: 0; }
    .su-reveal.nd-in { animation: su-reveal .5s ease forwards; }

    .su-wrap { padding: 0 0 40px; }
    .su-hero {
        position: relative;
        margin-top: -72px;
        padding: 96px 0 84px;
        background: linear-gradient(120deg, #4f46e5 0%, #7c3aed 55%, #0ea5e9 120%);
        overflow: hidden;
    }
    .su-hero::before, .su-hero::after {
        content: '';
        position: absolute;
        border-radius: 50%;
    }
    .su-hero::before { top: -120px; right: -60px; width: 360px; height: 360px; background: radial-gradient(circle, rgba(255,255,255,0.14) 0%, transparent 70%); }
    .su-hero::after { bottom: -140px; left: 12%; width: 320px; height: 320px; background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%); }
    .su-hero-inner { position: relative; z-index: 2; display: flex; align-items: flex-end; justify-content: space-between; flex-wrap: wrap; gap: 18px; }
    .su-hero h1 { color: #fff; font-size: 2rem; font-weight: 800; letter-spacing: -0.5px; margin: 0 0 6px; }
    .su-hero h1 i { font-size: 1.4rem; margin-right: 6px; opacity: .9; }
    .su-hero .su-hero-sub { color: rgba(255,255,255,0.82); margin: 0; font-size: .98rem; }

    .su-card {
        background: var(--bg-card);
        border: 1px solid var(--border-light);
        border-radius: 20px;
        box-shadow: var(--shadow-sm);
        overflow: hidden;
        transition: box-shadow .3s ease;
    }
    .su-card:hover { box-shadow: var(--shadow-md); }
    .su-card-head {
        display: flex; align-items: center; justify-content: space-between;
        gap: 12px; flex-wrap: wrap;
        padding: 18px 22px;
        border-bottom: 1px solid var(--border-light);
    }
    .su-card-head h5 { margin: 0; font-size: 1rem; font-weight: 700; display: flex; align-items: center; gap: 10px; }
    .su-card-head h5 .su-ico {
        width: 34px; height: 34px; border-radius: 10px;
        display: inline-flex; align-items: center; justify-content: center;
        font-size: .82rem; flex-shrink: 0;
    }
    .su-card-body { padding: 0; }

    /* KPI */
    .su-stat {
        position: relative; overflow: hidden;
        background: var(--bg-card);
        border: 1px solid var(--border-light);
        border-radius: 20px;
        box-shadow: var(--shadow-sm);
        padding: 20px 20px 14px;
        transition: transform .3s cubic-bezier(.34,1.56,.64,1), box-shadow .3s ease;
        height: 100%;
    }
    .su-stat:hover { transform: translateY(-5px); box-shadow: var(--shadow-lg); }
    .su-stat::after {
        content: '';
        position: absolute; top: -46px; right: -46px;
        width: 120px; height: 120px; border-radius: 50%;
        background: var(--nd-accent); opacity: .08;
        transition: transform .4s ease;
    }
    .su-stat:hover::after { transform: scale(1.35); }
    .su-stat-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; }
    .su-stat-ico {
        width: 48px; height: 48px; border-radius: 13px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.15rem; color: #fff;
        background: linear-gradient(135deg, var(--nd-accent), var(--nd-accent-2));
        box-shadow: 0 8px 16px -6px var(--nd-glow);
    }
    .su-stat-badge { font-size: .7rem; font-weight: 700; padding: 5px 10px; border-radius: 999px; background: var(--bg-hover); color: var(--text-muted); }
    .su-stat-num {
        font-family: 'Sora', 'Manrope', 'Inter', sans-serif;
        font-size: 2.1rem; font-weight: 800; line-height: 1; color: var(--text);
        font-variant-numeric: tabular-nums; letter-spacing: -0.02em;
    }
    .su-stat-label { font-size: .82rem; color: var(--text-muted); font-weight: 600; margin-top: 6px; }

    /* Table */
    .su-search {
        border: 1.5px solid var(--border-light); border-radius: 10px;
        padding: 8px 14px; font-size: .85rem; background: var(--bg-card);
        color: var(--text); transition: border-color .2s ease; outline: none;
        min-width: 220px;
    }
    .su-search:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,.12); }
    .su-search::placeholder { color: var(--text-light); }

    .su-btn-add {
        display: inline-flex; align-items: center; gap: 7px;
        padding: 9px 16px; border-radius: 11px;
        font-size: .82rem; font-weight: 700; color: #fff; text-decoration: none;
        background: linear-gradient(135deg, #6366f1, #8b5cf6);
        box-shadow: 0 6px 14px -6px rgba(99,102,241,.55);
        transition: all .25s ease; white-space: nowrap;
    }
    .su-btn-add:hover { color: #fff; text-decoration: none; transform: translateY(-2px); box-shadow: 0 10px 20px -8px rgba(99,102,241,.7); }

    .su-table { width: 100%; border-collapse: collapse; }
    .su-table thead th {
        background: var(--bg-hover);
        padding: 12px 16px; font-size: .74rem; font-weight: 700;
        text-transform: uppercase; letter-spacing: .5px; color: var(--text-muted);
        border-bottom: 1px solid var(--border-light); white-space: nowrap; text-align: left;
    }
    .su-table tbody td {
        padding: 13px 16px; font-size: .88rem; color: var(--text);
        border-bottom: 1px solid var(--border-light); vertical-align: middle;
    }
    .su-table tbody tr { transition: background .15s ease; }
    .su-table tbody tr:hover { background: var(--bg-hover); }
    .su-table tbody tr:last-child td { border-bottom: none; }

    .su-avatar {
        width: 40px; height: 40px; border-radius: 50%; flex-shrink: 0;
        display: inline-flex; align-items: center; justify-content: center;
        color: #fff; font-weight: 700; font-size: .85rem;
        object-fit: cover;
        box-shadow: 0 6px 14px -6px var(--nd-glow);
    }
    .su-name { font-weight: 700; color: var(--text); }
    .su-muted { color: var(--text-muted); font-size: .85rem; }
    .su-badge {
        display: inline-block;
        padding: 4px 11px; border-radius: 999px;
        font-size: .74rem; font-weight: 700;
        white-space: nowrap; max-width: 170px; overflow: hidden; text-overflow: ellipsis;
    }

    .su-act {
        display: inline-flex; align-items: center; justify-content: center; gap: 5px;
        padding: 8px 10px; border-radius: 9px; font-size: .78rem; font-weight: 700;
        text-decoration: none; transition: all .2s ease; white-space: nowrap;
    }
    .su-act:hover { text-decoration: none; transform: translateY(-1px); }
    .su-act.e { background: rgba(14,165,233,.12); color: #0369a1; }
    .su-act.e:hover { background: rgba(14,165,233,.22); color: #075985; }
    .su-act.d { background: #fee2e2; color: #991b1b; }
    .su-act.d:hover { background: #fecaca; color: #7f1d1d; }

    .su-empty {
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        padding: 56px 20px; color: var(--text-muted); gap: 10px; text-align: center;
    }
    .su-empty i { font-size: 2.4rem; opacity: .4; }

    /* Pagination */
    .su-pagebar {
        display: flex; align-items: center; justify-content: space-between;
        flex-wrap: wrap; gap: 14px; margin-top: 22px;
    }
    .su-pageinfo { color: var(--text-muted); font-size: .82rem; font-weight: 600; }
    .su-pages { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
    .su-pg {
        min-width: 38px; height: 38px; padding: 0 10px;
        display: inline-flex; align-items: center; justify-content: center;
        border-radius: 10px; font-size: .83rem; font-weight: 700;
        color: var(--text); background: var(--bg-hover);
        border: 1px solid var(--border-light);
        text-decoration: none; transition: all .2s ease;
    }
    .su-pg:hover { color: var(--primary); border-color: #6366f1; text-decoration: none; transform: translateY(-2px); }
    .su-pg.active {
        color: #fff;
        background: linear-gradient(135deg, #6366f1, #8b5cf6);
        border-color: transparent;
        box-shadow: 0 6px 14px -6px rgba(99,102,241,.55);
    }

    @media (max-width: 767px) {
        .su-hero { padding: 84px 0 64px; }
        .su-hero h1 { font-size: 1.5rem; }
        .su-stat-num { font-size: 1.7rem; }
    }
</style>

<div class="su-wrap">
    <!-- Hero -->
    <div class="su-hero">
        <div class="container">
            <div class="su-hero-inner">
                <div>
                    <h1><i class="fas fa-users"></i>Registered Users</h1>
                    <p class="su-hero-sub">Manage job-seeker accounts, qualifications and profiles.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="container" style="margin-top: -34px;">

        <!-- KPI CARDS -->
        <div class="row">
            <div class="col-xl-3 col-md-6 mb-3 su-reveal su-d1">
                <div class="su-stat" style="--nd-accent:#6366f1;--nd-accent-2:#818cf8;--nd-glow:rgba(99,102,241,.35);">
                    <div class="su-stat-top">
                        <div class="su-stat-ico"><i class="fas fa-user-friends"></i></div>
                        <span class="su-stat-badge">Registered</span>
                    </div>
                    <div class="su-stat-num su-count" data-count="<?php echo $stats['total']; ?>">0</div>
                    <div class="su-stat-label">Total Users</div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3 su-reveal su-d2">
                <div class="su-stat" style="--nd-accent:#8b5cf6;--nd-accent-2:#a78bfa;--nd-glow:rgba(139,92,246,.35);">
                    <div class="su-stat-top">
                        <div class="su-stat-ico"><i class="fas fa-graduation-cap"></i></div>
                        <span class="su-stat-badge">Qualified</span>
                    </div>
                    <div class="su-stat-num su-count" data-count="<?php echo $stats['degree']; ?>">0</div>
                    <div class="su-stat-label">With Degree</div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3 su-reveal su-d3">
                <div class="su-stat" style="--nd-accent:#10b981;--nd-accent-2:#34d399;--nd-glow:rgba(16,185,129,.35);">
                    <div class="su-stat-top">
                        <div class="su-stat-ico"><i class="fas fa-code"></i></div>
                        <span class="su-stat-badge">Skilled</span>
                    </div>
                    <div class="su-stat-num su-count" data-count="<?php echo $stats['skills']; ?>">0</div>
                    <div class="su-stat-label">With Skills</div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3 su-reveal su-d4">
                <div class="su-stat" style="--nd-accent:#f59e0b;--nd-accent-2:#fbbf24;--nd-glow:rgba(245,158,11,.35);">
                    <div class="su-stat-top">
                        <div class="su-stat-ico"><i class="fas fa-camera-retro"></i></div>
                        <span class="su-stat-badge">With photo</span>
                    </div>
                    <div class="su-stat-num su-count" data-count="<?php echo $stats['photo']; ?>">0</div>
                    <div class="su-stat-label">With Profile Photo</div>
                </div>
            </div>
        </div>

        <!-- TABLE CARD -->
        <div class="row mt-2 mb-3 su-reveal">
            <div class="col-12">
                <div class="su-card">
                    <div class="su-card-head">
                        <h5><span class="su-ico" style="background:rgba(79,70,229,.1);color:var(--primary);"><i class="fas fa-users"></i></span>User List</h5>
                        <div class="d-flex align-items-center" style="gap:10px;flex-wrap:wrap;">
                            <div class="input-group" style="width:auto;">
                                <input type="text" class="su-search" id="suSearch" placeholder="Search name, email, phone, skills..." onkeyup="suFilter('suSearch','suTable')">
                                <div class="input-group-append" style="margin-left:-1px;">
                                    <span class="input-group-text" style="background:var(--bg-hover);border:1.5px solid var(--border-light);border-left:none;border-radius:0 10px 10px 0;color:var(--text-muted);"><i class="fas fa-search"></i></span>
                                </div>
                            </div>
                            <a href="add_details.php" class="su-btn-add"><i class="fas fa-user-plus"></i> Add User</a>
                        </div>
                    </div>
                    <div class="su-card-body">
                        <?php if (count($rows) > 0): ?>
                            <div class="table-responsive">
                                <table class="su-table" id="suTable">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>User</th>
                                            <th>Contact</th>
                                            <th>Degree</th>
                                            <th>Skills</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($rows as $i => $u): ?>
                                            <?php $bs = su_badge_style($u['username'] . $u['id']); ?>
                                            <tr class="su-reveal nd-in" style="animation-delay:<?php echo min($i * 60, 400); ?>ms;">
                                                <td><span class="su-muted">#<?php echo $u['id']; ?></span></td>
                                                <td>
                                                    <div class="d-flex align-items-center" style="gap:10px;">
                                                        <?php if (su_photo_exists($u['profile'])): ?>
                                                            <img class="su-avatar" src="../images/<?php echo htmlspecialchars($u['profile']); ?>" alt="photo" style="background:<?php echo su_avatar_style((int)$u['id']); ?>;">
                                                        <?php else: ?>
                                                            <span class="su-avatar" style="background:<?php echo su_avatar_style((int)$u['id']); ?>;"><?php echo htmlspecialchars(su_initials($u['username'])); ?></span>
                                                        <?php endif; ?>
                                                        <div>
                                                            <div class="su-name"><?php echo htmlspecialchars(trim($u['username'])); ?></div>
                                                            <div class="su-muted" style="font-size:.76rem;">
                                                                <i class="fas fa-genderless mr-1"></i>Job Seeker
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="d-flex flex-column">
                                                        <span><i class="fas fa-envelope mr-1" style="color:var(--text-muted);font-size:.72rem;"></i><?php echo htmlspecialchars($u['email']); ?></span>
                                                        <span class="su-muted"><i class="fas fa-phone mr-1" style="font-size:.72rem;"></i><?php echo htmlspecialchars($u['phone']); ?></span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php if ($u['user_degree'] !== ''): ?>
                                                        <span class="su-badge" style="background:<?php echo $bs['bg']; ?>;color:<?php echo $bs['fg']; ?>;"><?php echo htmlspecialchars($u['user_degree']); ?></span>
                                                    <?php else: ?>
                                                        <span class="su-muted">—</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($u['user_skills'] !== ''): ?>
                                                        <span class="su-badge" style="background:rgba(16,185,129,.1);color:#059669;"><i class="fas fa-code mr-1"></i><?php echo htmlspecialchars($u['user_skills']); ?></span>
                                                    <?php else: ?>
                                                        <span class="su-muted">—</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center" style="gap:6px;">
                                                        <a class="su-act e" href="update_user.php?id=<?php echo $u['id']; ?>" data-toggle="tooltip" title="Edit"><i class="fas fa-edit"></i></a>
                                                        <a class="su-act d" href="#" data-toggle="tooltip" title="Delete" onclick="suConfirmDelete(event, <?php echo $u['id']; ?>)"><i class="fas fa-trash"></i></a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="su-empty" id="suFilterEmpty" style="display:none;">
                                <i class="fas fa-search-minus"></i>
                                <span>No users match your search.</span>
                            </div>
                        <?php else: ?>
                            <div class="su-empty">
                                <i class="fas fa-user-slash"></i>
                                <span>No registered users yet.</span>
                                <a href="add_details.php" class="su-btn-add" style="margin-top:6px;"><i class="fas fa-user-plus"></i> Add your first user</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- PAGINATION -->
                <?php if ($total_pages > 1): ?>
                    <div class="su-pagebar">
                        <span class="su-pageinfo">Page <?php echo $page; ?> of <?php echo $total_pages; ?> &middot; <?php echo $total_records; ?> users total</span>
                        <div class="su-pages">
                            <?php if ($page > 1): ?>
                                <a class="su-pg" href="show_users.php?page=<?php echo $page - 1; ?>"><i class="fas fa-chevron-left"></i></a>
                            <?php endif; ?>
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <a class="su-pg <?php echo $i == $page ? 'active' : ''; ?>" href="show_users.php?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            <?php endfor; ?>
                            <?php if ($page < $total_pages): ?>
                                <a class="su-pg" href="show_users.php?page=<?php echo $page + 1; ?>"><i class="fas fa-chevron-right"></i></a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <?php if ($total_records > 0): ?>
                        <div class="su-pagebar">
                            <span class="su-pageinfo"><?php echo $total_records; ?> user<?php echo $total_records == 1 ? '' : 's'; ?> total</span>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="text-center" style="padding: 4px 0 20px; color: var(--text-muted); font-size: .82rem;">
            NovaHire Admin &middot; Registered Users
        </div>
    </div>
</div>

<script>
    /* Reveal + counters on load */
    document.addEventListener('DOMContentLoaded', function () {
        var els = document.querySelectorAll('.su-reveal:not(.nd-in)');
        els.forEach(function (el) { el.classList.add('nd-in'); });
        var counters = document.querySelectorAll('.su-count');
        counters.forEach(function (el) {
            var target = parseInt(el.getAttribute('data-count'), 10) || 0;
            var dur = 900, start = null;
            function step(ts) {
                if (!start) start = ts;
                var p = Math.min((ts - start) / dur, 1);
                var eased = p < 0.5 ? p * p * 2 : 1 - Math.pow(-2 * p + 2, 2) / 2;
                el.textContent = Math.floor(target * eased);
                if (p < 1) requestAnimationFrame(step); else el.textContent = target;
            }
            requestAnimationFrame(step);
        });
    });

    /* Live search filter */
    function suFilter(inputId, tableId) {
        var input = document.getElementById(inputId);
        var table = document.getElementById(tableId);
        if (!input || !table) return;
        var val = input.value.toLowerCase();
        var rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
        var visible = 0;
        for (var i = 0; i < rows.length; i++) {
            var show = rows[i].textContent.toLowerCase().indexOf(val) > -1;
            rows[i].style.display = show ? '' : 'none';
            if (show) visible++;
        }
        var empty = document.getElementById('suFilterEmpty');
        if (empty) empty.style.display = (visible === 0 && val) ? 'flex' : 'none';
    }

    /* Delete confirm */
    function suConfirmDelete(e, id) {
        e.preventDefault();
        if (confirm('Are you sure you want to delete this user? This cannot be undone.')) {
            window.location.href = 'delete_user.php?id=' + id;
        }
    }

    $(document).ready(function () {
        $('[data-toggle="tooltip"]').tooltip();
    });
</script>

</body>
</html>
