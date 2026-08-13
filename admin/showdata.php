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

$tr = mysqli_query($con, "SELECT COUNT(*) AS c FROM jobregistration");
$total_records = $tr ? (int)mysqli_fetch_assoc($tr)['c'] : 0;
$total_pages = max(1, (int)ceil($total_records / $records_per_page));
if ($page > $total_pages) $page = $total_pages;
$start_from = ($page - 1) * $records_per_page;

$rows = [];
$pr = mysqli_query($con, "SELECT * FROM jobregistration ORDER BY id DESC LIMIT $start_from, $records_per_page");
if ($pr) {
    while ($r = mysqli_fetch_assoc($pr)) $rows[] = $r;
}

$stats = ['total' => $total_records, 'candidates' => 0, 'cv' => 0, 'refer' => 0];
if ($q = mysqli_query($con, "SELECT COUNT(DISTINCT email) c FROM jobregistration")) $stats['candidates'] = (int)mysqli_fetch_assoc($q)['c'];
if ($q = mysqli_query($con, "SELECT COUNT(*) c FROM jobregistration WHERE cv_doc IS NOT NULL AND cv_doc != ''")) $stats['cv'] = (int)mysqli_fetch_assoc($q)['c'];
if ($q = mysqli_query($con, "SELECT COUNT(*) c FROM jobregistration WHERE refer IS NOT NULL AND refer != ''")) $stats['refer'] = (int)mysqli_fetch_assoc($q)['c'];

function sd_avatar_initials($name) {
    $parts = preg_split('/\s+/', trim($name));
    $initials = '';
    foreach (array_slice($parts, 0, 2) as $p) { if ($p !== '') $initials .= strtoupper(mb_substr($p, 0, 1)); }
    return $initials !== '' ? $initials : '?';
}
function sd_badge_style($str) {
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
function sd_avatar_style($id) {
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

include 'header.php';
?>

<style>
    @keyframes sd-reveal { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: none; } }
    .sd-reveal { opacity: 0; }
    .sd-reveal.nd-in { animation: sd-reveal .5s ease forwards; }

    .sd-wrap { padding: 0 0 40px; }
    .sd-hero {
        position: relative;
        margin-top: -72px;
        padding: 96px 0 84px;
        background: linear-gradient(120deg, #4f46e5 0%, #7c3aed 55%, #0ea5e9 120%);
        overflow: hidden;
    }
    .sd-hero::before, .sd-hero::after {
        content: '';
        position: absolute;
        border-radius: 50%;
    }
    .sd-hero::before { top: -120px; right: -60px; width: 360px; height: 360px; background: radial-gradient(circle, rgba(255,255,255,0.14) 0%, transparent 70%); }
    .sd-hero::after { bottom: -140px; left: 12%; width: 320px; height: 320px; background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%); }
    .sd-hero-inner { position: relative; z-index: 2; display: flex; align-items: flex-end; justify-content: space-between; flex-wrap: wrap; gap: 18px; }
    .sd-hero h1 { color: #fff; font-size: 2rem; font-weight: 800; letter-spacing: -0.5px; margin: 0 0 6px; }
    .sd-hero h1 i { font-size: 1.4rem; margin-right: 6px; opacity: .9; }
    .sd-hero .sd-hero-sub { color: rgba(255,255,255,0.82); margin: 0; font-size: .98rem; }

    .sd-card {
        background: var(--bg-card);
        border: 1px solid var(--border-light);
        border-radius: 20px;
        box-shadow: var(--shadow-sm);
        overflow: hidden;
        transition: box-shadow .3s ease;
    }
    .sd-card:hover { box-shadow: var(--shadow-md); }
    .sd-card-head {
        display: flex; align-items: center; justify-content: space-between;
        gap: 12px; flex-wrap: wrap;
        padding: 18px 22px;
        border-bottom: 1px solid var(--border-light);
    }
    .sd-card-head h5 { margin: 0; font-size: 1rem; font-weight: 700; display: flex; align-items: center; gap: 10px; }
    .sd-card-head h5 .sd-ico {
        width: 34px; height: 34px; border-radius: 10px;
        display: inline-flex; align-items: center; justify-content: center;
        font-size: .82rem; flex-shrink: 0;
    }
    .sd-card-body { padding: 0; }

    /* KPI */
    .sd-stat {
        position: relative; overflow: hidden;
        background: var(--bg-card);
        border: 1px solid var(--border-light);
        border-radius: 20px;
        box-shadow: var(--shadow-sm);
        padding: 20px 20px 14px;
        transition: transform .3s cubic-bezier(.34,1.56,.64,1), box-shadow .3s ease;
        height: 100%;
    }
    .sd-stat:hover { transform: translateY(-5px); box-shadow: var(--shadow-lg); }
    .sd-stat::after {
        content: '';
        position: absolute; top: -46px; right: -46px;
        width: 120px; height: 120px; border-radius: 50%;
        background: var(--nd-accent); opacity: .08;
        transition: transform .4s ease;
    }
    .sd-stat:hover::after { transform: scale(1.35); }
    .sd-stat-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; }
    .sd-stat-ico {
        width: 48px; height: 48px; border-radius: 13px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.15rem; color: #fff;
        background: linear-gradient(135deg, var(--nd-accent), var(--nd-accent-2));
        box-shadow: 0 8px 16px -6px var(--nd-glow);
    }
    .sd-stat-badge { font-size: .7rem; font-weight: 700; padding: 5px 10px; border-radius: 999px; background: var(--bg-hover); color: var(--text-muted); }
    .sd-stat-num {
        font-family: 'Sora', 'Manrope', 'Inter', sans-serif;
        font-size: 2.1rem; font-weight: 800; line-height: 1; color: var(--text);
        font-variant-numeric: tabular-nums; letter-spacing: -0.02em;
    }
    .sd-stat-label { font-size: .82rem; color: var(--text-muted); font-weight: 600; margin-top: 6px; }

    /* Table */
    .sd-search {
        border: 1.5px solid var(--border-light); border-radius: 10px;
        padding: 8px 14px; font-size: .85rem; background: var(--bg-card);
        color: var(--text); transition: border-color .2s ease; outline: none;
        min-width: 220px;
    }
    .sd-search:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,.12); }
    .sd-search::placeholder { color: var(--text-light); }

    .sd-table { width: 100%; border-collapse: collapse; }
    .sd-table thead th {
        background: var(--bg-hover);
        padding: 12px 16px; font-size: .74rem; font-weight: 700;
        text-transform: uppercase; letter-spacing: .5px; color: var(--text-muted);
        border-bottom: 1px solid var(--border-light); white-space: nowrap; text-align: left;
    }
    .sd-table tbody td {
        padding: 13px 16px; font-size: .88rem; color: var(--text);
        border-bottom: 1px solid var(--border-light); vertical-align: middle;
    }
    .sd-table tbody tr { transition: background .15s ease; }
    .sd-table tbody tr:hover { background: var(--bg-hover); }
    .sd-table tbody tr:last-child td { border-bottom: none; }

    .sd-avatar {
        width: 38px; height: 38px; border-radius: 11px; flex-shrink: 0;
        display: inline-flex; align-items: center; justify-content: center;
        color: #fff; font-weight: 700; font-size: .82rem;
        box-shadow: 0 6px 14px -6px var(--nd-glow);
    }
    .sd-name { font-weight: 700; color: var(--text); }
    .sd-email { font-size: .78rem; color: var(--text-muted); }
    .sd-badge {
        display: inline-block;
        padding: 4px 11px; border-radius: 999px;
        font-size: .74rem; font-weight: 700;
        white-space: nowrap; max-width: 180px; overflow: hidden; text-overflow: ellipsis;
    }
    .sd-muted { color: var(--text-muted); font-size: .85rem; }

    .sd-act {
        display: inline-flex; align-items: center; justify-content: center; gap: 5px;
        padding: 6px 12px; border-radius: 9px; font-size: .76rem; font-weight: 700;
        text-decoration: none; transition: all .2s ease; white-space: nowrap;
    }
    .sd-act:hover { text-decoration: none; transform: translateY(-1px); }
    .sd-act.v { background: #eef2ff; color: #4f46e5; }
    .sd-act.v:hover { background: #e0e7ff; color: #3730a3; }
    .sd-act.e { background: rgba(14,165,233,.12); color: #0369a1; }
    .sd-act.e:hover { background: rgba(14,165,233,.22); color: #075985; }
    .sd-act.d { background: #fee2e2; color: #991b1b; }
    .sd-act.d:hover { background: #fecaca; color: #7f1d1d; }
    .sd-act.icon { padding: 8px 10px; }

    .sd-empty {
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        padding: 56px 20px; color: var(--text-muted); gap: 10px; text-align: center;
    }
    .sd-empty i { font-size: 2.4rem; opacity: .4; }

    /* Pagination */
    .sd-pagebar {
        display: flex; align-items: center; justify-content: space-between;
        flex-wrap: wrap; gap: 14px; margin-top: 22px;
    }
    .sd-pageinfo { color: var(--text-muted); font-size: .82rem; font-weight: 600; }
    .sd-pages { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
    .sd-pg {
        min-width: 38px; height: 38px; padding: 0 10px;
        display: inline-flex; align-items: center; justify-content: center;
        border-radius: 10px; font-size: .83rem; font-weight: 700;
        color: var(--text); background: var(--bg-hover);
        border: 1px solid var(--border-light);
        text-decoration: none; transition: all .2s ease;
    }
    .sd-pg:hover { color: var(--primary); border-color: #6366f1; text-decoration: none; transform: translateY(-2px); }
    .sd-pg.active {
        color: #fff;
        background: linear-gradient(135deg, #6366f1, #8b5cf6);
        border-color: transparent;
        box-shadow: 0 6px 14px -6px rgba(99,102,241,.55);
    }

    @media (max-width: 767px) {
        .sd-hero { padding: 84px 0 64px; }
        .sd-hero h1 { font-size: 1.5rem; }
        .sd-stat-num { font-size: 1.7rem; }
    }
</style>

<div class="sd-wrap">
    <!-- Hero -->
    <div class="sd-hero">
        <div class="container">
            <div class="sd-hero-inner">
                <div>
                    <h1><i class="fas fa-file-signature"></i>Candidates Applications</h1>
                    <p class="sd-hero-sub">Review candidate details, CVs and take actions on each application.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="container" style="margin-top: -34px;">

        <!-- KPI CARDS -->
        <div class="row">
            <div class="col-xl-3 col-md-6 mb-3 sd-reveal sd-d1">
                <div class="sd-stat" style="--nd-accent:#6366f1;--nd-accent-2:#818cf8;--nd-glow:rgba(99,102,241,.35);">
                    <div class="sd-stat-top">
                        <div class="sd-stat-ico"><i class="fas fa-file-alt"></i></div>
                        <span class="sd-stat-badge">Submitted</span>
                    </div>
                    <div class="sd-stat-num sd-count" data-count="<?php echo $stats['total']; ?>">0</div>
                    <div class="sd-stat-label">Total Applications</div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3 sd-reveal sd-d2">
                <div class="sd-stat" style="--nd-accent:#8b5cf6;--nd-accent-2:#a78bfa;--nd-glow:rgba(139,92,246,.35);">
                    <div class="sd-stat-top">
                        <div class="sd-stat-ico"><i class="fas fa-users"></i></div>
                        <span class="sd-stat-badge">Unique</span>
                    </div>
                    <div class="sd-stat-num sd-count" data-count="<?php echo $stats['candidates']; ?>">0</div>
                    <div class="sd-stat-label">Candidates</div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3 sd-reveal sd-d3">
                <div class="sd-stat" style="--nd-accent:#10b981;--nd-accent-2:#34d399;--nd-glow:rgba(16,185,129,.35);">
                    <div class="sd-stat-top">
                        <div class="sd-stat-ico"><i class="fas fa-file-pdf"></i></div>
                        <span class="sd-stat-badge">Uploaded</span>
                    </div>
                    <div class="sd-stat-num sd-count" data-count="<?php echo $stats['cv']; ?>">0</div>
                    <div class="sd-stat-label">With CV</div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3 sd-reveal sd-d4">
                <div class="sd-stat" style="--nd-accent:#f59e0b;--nd-accent-2:#fbbf24;--nd-glow:rgba(245,158,11,.35);">
                    <div class="sd-stat-top">
                        <div class="sd-stat-ico"><i class="fas fa-share-alt"></i></div>
                        <span class="sd-stat-badge">Mentioned</span>
                    </div>
                    <div class="sd-stat-num sd-count" data-count="<?php echo $stats['refer']; ?>">0</div>
                    <div class="sd-stat-label">With Reference</div>
                </div>
            </div>
        </div>

        <!-- TABLE CARD -->
        <div class="row mt-2 mb-3 sd-reveal">
            <div class="col-12">
                <div class="sd-card">
                    <div class="sd-card-head">
                        <h5><span class="sd-ico" style="background:rgba(79,70,229,.1);color:var(--primary);"><i class="fas fa-users"></i></span>Application Records</h5>
                        <div class="d-flex align-items-center">
                            <div class="input-group" style="width:auto;">
                                <input type="text" class="sd-search" id="sdSearch" placeholder="Search name, email, phone, degree..." onkeyup="sdFilter('sdSearch','sdTable')">
                                <div class="input-group-append" style="margin-left:-1px;">
                                    <span class="input-group-text" style="background:var(--bg-hover);border:1.5px solid var(--border-light);border-left:none;border-radius:0 10px 10px 0;color:var(--text-muted);"><i class="fas fa-search"></i></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="sd-card-body">
                        <?php if (count($rows) > 0): ?>
                            <div class="table-responsive">
                                <table class="sd-table" id="sdTable">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Candidate</th>
                                            <th>Contact</th>
                                            <th>Degree</th>
                                            <th>Reference</th>
                                            <th>Programming Lang</th>
                                            <th>CV</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($rows as $i => $result): ?>
                                            <?php $bs = sd_badge_style($result['name'] . $result['id']); ?>
                                            <tr class="sd-reveal nd-in" style="animation-delay:<?php echo min($i * 60, 400); ?>ms;">
                                                <td><span class="sd-muted">#<?php echo $result['id']; ?></span></td>
                                                <td>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <span class="sd-avatar" style="background:<?php echo sd_avatar_style((int)$result['id']); ?>;"><?php echo htmlspecialchars(sd_avatar_initials($result['name'])); ?></span>
                                                        <div>
                                                            <div class="sd-name"><?php echo htmlspecialchars($result['name']); ?></div>
                                                            <div class="sd-email d-none d-lg-block"><?php echo htmlspecialchars($result['email']); ?></div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="d-flex flex-column">
                                                        <span><i class="fas fa-envelope mr-1" style="color:var(--text-muted);font-size:.72rem;"></i><?php echo htmlspecialchars($result['email']); ?></span>
                                                        <span class="sd-muted"><i class="fas fa-phone mr-1" style="font-size:.72rem;"></i><?php echo htmlspecialchars($result['phone']); ?></span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="sd-badge" style="background:<?php echo $bs['bg']; ?>;color:<?php echo $bs['fg']; ?>;"><?php echo htmlspecialchars($result['degree']); ?></span>
                                                </td>
                                                <td><?php echo $result['refer'] !== '' ? '<span class="sd-badge" style="background:rgba(245,158,11,.14);color:#b45309;"><i class="fas fa-user-tag mr-1"></i>' . htmlspecialchars($result['refer']) . '</span>' : '<span class="sd-muted">—</span>'; ?></td>
                                                <td><span class="sd-badge" style="background:rgba(99,102,241,.1);color:#4f46e5;"><i class="fas fa-code mr-1"></i><?php echo htmlspecialchars($result['planguage']); ?></span></td>
                                                <td>
                                                    <a class="sd-act v" href="../seeker/view_cv.php?id=<?php echo $result['id']; ?>" target="_blank"><i class="fas fa-eye"></i> View</a>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center" style="gap:6px;">
                                                        <a class="sd-act e icon" href="update_application.php?id=<?php echo $result['id']; ?>" data-toggle="tooltip" title="Edit"><i class="fas fa-edit"></i></a>
                                                        <a class="sd-act d icon" href="#" data-toggle="tooltip" title="Delete" onclick="sdConfirmDelete(event, <?php echo $result['id']; ?>)"><i class="fas fa-trash"></i></a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="sd-empty" id="sdFilterEmpty" style="display:none;">
                                <i class="fas fa-search-minus"></i>
                                <span>No applications match your search.</span>
                            </div>
                        <?php else: ?>
                            <div class="sd-empty">
                                <i class="fas fa-inbox"></i>
                                <span>No applications submitted yet.</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- PAGINATION -->
                <?php if ($total_pages > 1): ?>
                    <div class="sd-pagebar">
                        <span class="sd-pageinfo">Page <?php echo $page; ?> of <?php echo $total_pages; ?> &middot; <?php echo $total_records; ?> records total</span>
                        <div class="sd-pages">
                            <?php if ($page > 1): ?>
                                <a class="sd-pg" href="showdata.php?page=<?php echo $page - 1; ?>"><i class="fas fa-chevron-left"></i></a>
                            <?php endif; ?>
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <a class="sd-pg <?php echo $i == $page ? 'active' : ''; ?>" href="showdata.php?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            <?php endfor; ?>
                            <?php if ($page < $total_pages): ?>
                                <a class="sd-pg" href="showdata.php?page=<?php echo $page + 1; ?>"><i class="fas fa-chevron-right"></i></a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <?php if ($total_records > 0): ?>
                        <div class="sd-pagebar">
                            <span class="sd-pageinfo"><?php echo $total_records; ?> record<?php echo $total_records == 1 ? '' : 's'; ?> total</span>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="text-center" style="padding: 4px 0 20px; color: var(--text-muted); font-size: .82rem;">
            NovaHire Admin &middot; Candidates Applications
        </div>
    </div>
</div>

<script>
    /* Reveal on load */
    document.addEventListener('DOMContentLoaded', function () {
        var els = document.querySelectorAll('.sd-reveal:not(.nd-in)');
        els.forEach(function (el) { el.classList.add('nd-in'); });
        var counters = document.querySelectorAll('.sd-count');
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
    function sdFilter(inputId, tableId) {
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
        var empty = document.getElementById('sdFilterEmpty');
        if (empty) empty.style.display = (visible === 0 && val) ? 'flex' : 'none';
    }

    /* Delete confirm */
    function sdConfirmDelete(e, id) {
        e.preventDefault();
        if (confirm('Are you sure you want to delete this application? This cannot be undone.')) {
            window.location.href = 'delete_application.php?id=' + id;
        }
    }

    $(document).ready(function () {
        $('[data-toggle="tooltip"]').tooltip();
    });
</script>

</body>
</html>
