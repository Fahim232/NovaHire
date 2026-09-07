<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 9;
$offset = ($page - 1) * $per_page;
$search = trim($_GET['search'] ?? '');
$cat = trim($_GET['category'] ?? '');

$where = "WHERE status = 'published'";
$params = [];
$types = '';

if ($search) {
    $where .= " AND (title LIKE ? OR content LIKE ? OR excerpt LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= 'sss';
}
if ($cat) {
    $where .= " AND category = ?";
    $params[] = $cat;
    $types .= 's';
}

$count_stmt = mysqli_prepare($con, "SELECT COUNT(*) as total FROM blog_posts p $where");
if ($params) mysqli_stmt_bind_param($count_stmt, $types, ...$params);
mysqli_stmt_execute($count_stmt);
$total = mysqli_fetch_assoc(mysqli_stmt_get_result($count_stmt))['total'];
$total_pages = max(1, ceil($total / $per_page));
mysqli_stmt_close($count_stmt);

$sql = "SELECT p.id, p.title, p.slug, p.excerpt, p.category, p.featured_image, p.created_at, p.views_count,
               COALESCE(ui.username, 'NovaHire Team') AS author_name
        FROM blog_posts p
        LEFT JOIN user_info ui ON ui.id = p.author_id
        $where ORDER BY p.created_at DESC LIMIT $per_page OFFSET $offset";
$stmt = mysqli_prepare($con, $sql);
if ($params) mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$posts = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

$cat_sql = "SELECT category, COUNT(*) as cnt FROM blog_posts WHERE status='published' GROUP BY category ORDER BY cnt DESC";
$categories = mysqli_fetch_all(mysqli_query($con, $cat_sql), MYSQLI_ASSOC);

// Featured post (first post on page 1 without search/filter)
$featured = null;
if (!$search && !$cat && $page === 1 && !empty($posts)) {
    $featured = $posts[0];
    $posts = array_slice($posts, 1);
}

$cat_icons = [
    'Career Advice' => 'fa-compass', 'Interview Tips' => 'fa-comments', 'Resume Tips' => 'fa-file-alt',
    'Job Search' => 'fa-search', 'Salary' => 'fa-coins', 'Remote Work' => 'fa-laptop-house',
    'Leadership' => 'fa-crown', 'Technology' => 'fa-microchip', 'Marketing' => 'fa-bullhorn',
    'Finance' => 'fa-chart-line', 'Design' => 'fa-palette', 'Healthcare' => 'fa-heart-pulse',
    'Education' => 'fa-graduation-cap', 'Engineering' => 'fa-gears', 'General' => 'fa-newspaper',
];
$cat_colors = [
    'Career Advice' => ['#1a56db','#eef2ff'], 'Interview Tips' => ['#059669','#ecfdf5'],
    'Resume Tips' => ['#ea580c','#fff7ed'], 'Job Search' => ['#2563eb','#eff6ff'],
    'Salary' => ['#ca8a04','#fefce8'], 'Remote Work' => ['#0ea5e9','#f5f3ff'],
    'Technology' => ['#0891b2','#ecfeff'], 'Marketing' => ['#e11d48','#fff1f2'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Career Blog - NovaHire</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --bg: #f8fafc; --card: #ffffff; --text: #0f172a; --text-2: #334155;
            --text-3: #64748b; --border: #e2e8f0; --primary: #1a56db;
            --primary-light: #eef2ff; --primary-dark: #3730a3; --radius: 16px;
        }
        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--bg); color: var(--text); -webkit-font-smoothing: antialiased;
        }
        a { text-decoration: none; color: inherit; }

        /* ═══ NAV ═══ */
        .nav {
            background: #fff; border-bottom: 1px solid var(--border);
            padding: 14px 0; position: sticky; top: 0; z-index: 100;
            backdrop-filter: blur(12px); background: rgba(255,255,255,0.92);
        }
        .nav-inner {
            max-width: 1200px; margin: 0 auto; padding: 0 24px;
            display: flex; align-items: center; justify-content: space-between;
        }
        .nav-brand { display: flex; align-items: center; gap: 10px; font-weight: 800; font-size: 1.15rem; color: var(--text); }
        .nav-brand .icon {
            width: 36px; height: 36px; border-radius: 10px;
            background: linear-gradient(135deg, #1a56db, #0ea5e9);
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-size: 0.9rem;
        }
        .nav-brand span { color: var(--primary); }
        .nav-links { display: flex; align-items: center; gap: 6px; }
        .nav-link {
            padding: 8px 16px; border-radius: 8px; font-size: 0.88rem;
            font-weight: 500; color: var(--text-2); transition: all 0.15s;
        }
        .nav-link:hover { background: var(--primary-light); color: var(--primary); }
        .nav-link.on { background: var(--primary-light); color: var(--primary); font-weight: 600; }
        .nav-cta {
            padding: 9px 20px; border-radius: 8px; font-size: 0.85rem;
            font-weight: 700; background: var(--primary); color: #fff;
            transition: all 0.2s; margin-left: 8px;
        }
        .nav-cta:hover { background: var(--primary-dark); transform: translateY(-1px); }

        /* ═══ HERO ═══ */
        .hero {
            background: linear-gradient(135deg, #1a56db 0%, #0ea5e9 50%, #38bdf8 100%);
            padding: 72px 24px 64px; text-align: center; color: #fff;
            position: relative; overflow: hidden;
        }
        .hero::before {
            content: ''; position: absolute; top: -40%; right: -10%;
            width: 500px; height: 500px;
            background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%);
            border-radius: 50%;
        }
        .hero::after {
            content: ''; position: absolute; bottom: -30%; left: -5%;
            width: 400px; height: 400px;
            background: radial-gradient(circle, rgba(56,189,248,0.2) 0%, transparent 70%);
            border-radius: 50%;
        }
        .hero h1 { font-size: 2.6rem; font-weight: 800; margin-bottom: 10px; position: relative; z-index: 1; letter-spacing: -0.5px; }
        .hero p { opacity: 0.9; font-size: 1.05rem; max-width: 560px; margin: 0 auto 28px; position: relative; z-index: 1; line-height: 1.6; }
        .hero-search {
            max-width: 520px; margin: 0 auto; display: flex;
            background: #fff; border-radius: 14px; overflow: hidden;
            box-shadow: 0 12px 40px rgba(0,0,0,0.15); position: relative; z-index: 1;
        }
        .hero-search input {
            flex: 1; border: none; padding: 16px 22px; font-size: 0.95rem;
            font-family: inherit; color: var(--text); outline: none;
        }
        .hero-search input::placeholder { color: #94a3b8; }
        .hero-search button {
            background: var(--primary); color: #fff; border: none;
            padding: 16px 28px; cursor: pointer; font-size: 1rem;
            transition: background 0.2s;
        }
        .hero-search button:hover { background: var(--primary-dark); }

        /* ═══ CONTAINER ═══ */
        .container { max-width: 1200px; margin: 0 auto; padding: 0 24px; }

        /* ═══ CATEGORIES ═══ */
        .cats { display: flex; gap: 8px; flex-wrap: wrap; margin: 32px 0 28px; justify-content: center; }
        .cat-pill {
            padding: 8px 20px; border-radius: 24px; font-size: 0.82rem; font-weight: 600;
            border: 1.5px solid var(--border); color: var(--text-2); background: #fff;
            transition: all 0.2s; display: inline-flex; align-items: center; gap: 6px;
        }
        .cat-pill:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-light); }
        .cat-pill.on { background: var(--primary); color: #fff; border-color: var(--primary); }
        .cat-pill .cnt { font-size: 0.7rem; opacity: 0.7; }

        /* ═══ FEATURED POST ═══ */
        .featured {
            display: grid; grid-template-columns: 1.1fr 1fr; gap: 0;
            background: var(--card); border: 1px solid var(--border);
            border-radius: var(--radius); overflow: hidden; margin-bottom: 36px;
            transition: all 0.3s;
        }
        .featured:hover { box-shadow: 0 12px 40px rgba(0,0,0,0.06); transform: translateY(-3px); }
        .featured-img {
            height: 100%; min-height: 320px; background: linear-gradient(135deg, #eef2ff, #c7d2fe);
            display: flex; align-items: center; justify-content: center; overflow: hidden;
        }
        .featured-img img { width: 100%; height: 100%; object-fit: cover; }
        .featured-img i { font-size: 4rem; color: var(--primary); opacity: 0.4; }
        .featured-body { padding: 32px; display: flex; flex-direction: column; justify-content: center; }
        .featured-badge {
            display: inline-flex; align-items: center; gap: 5px;
            background: var(--primary-light); color: var(--primary);
            padding: 5px 14px; border-radius: 20px; font-size: 0.72rem;
            font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;
            width: fit-content; margin-bottom: 16px;
        }
        .featured-body h2 { font-size: 1.5rem; font-weight: 800; line-height: 1.3; margin-bottom: 12px; letter-spacing: -0.3px; }
        .featured-body h2:hover { color: var(--primary); }
        .featured-body .excerpt { color: var(--text-3); font-size: 0.92rem; line-height: 1.7; margin-bottom: 20px; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
        .featured-meta { display: flex; align-items: center; gap: 16px; font-size: 0.8rem; color: var(--text-3); flex-wrap: wrap; }
        .featured-meta span { display: flex; align-items: center; gap: 5px; }
        .featured-meta i { color: var(--primary); font-size: 0.75rem; }

        /* ═══ POST GRID ═══ */
        .section-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
        .section-title { font-size: 1.15rem; font-weight: 700; display: flex; align-items: center; gap: 8px; }
        .section-title i { color: var(--primary); }
        .section-count { font-size: 0.8rem; color: var(--text-3); font-weight: 500; }

        .grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 22px; margin-bottom: 40px; }
        .card {
            background: var(--card); border: 1px solid var(--border);
            border-radius: var(--radius); overflow: hidden;
            transition: all 0.25s; display: flex; flex-direction: column;
        }
        .card:hover { transform: translateY(-4px); box-shadow: 0 12px 32px rgba(0,0,0,0.06); }
        .card-img {
            height: 180px; background: linear-gradient(135deg, #eef2ff, #c7d2fe);
            display: flex; align-items: center; justify-content: center;
            position: relative; overflow: hidden;
        }
        .card-img img { width: 100%; height: 100%; object-fit: cover; }
        .card-img i { font-size: 2.5rem; color: var(--primary); opacity: 0.3; }
        .card-badge {
            position: absolute; top: 12px; left: 12px;
            padding: 4px 12px; border-radius: 20px; font-size: 0.68rem;
            font-weight: 700; text-transform: uppercase; letter-spacing: 0.3px;
        }
        .card-body { padding: 20px; flex: 1; display: flex; flex-direction: column; }
        .card-title { font-size: 1rem; font-weight: 700; line-height: 1.4; margin-bottom: 8px; color: var(--text); }
        .card-title:hover { color: var(--primary); }
        .card-excerpt { color: var(--text-3); font-size: 0.84rem; line-height: 1.6; flex: 1; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .card-foot {
            display: flex; align-items: center; justify-content: space-between;
            margin-top: 16px; padding-top: 14px; border-top: 1px solid var(--border);
            font-size: 0.78rem; color: var(--text-3);
        }
        .card-foot span { display: flex; align-items: center; gap: 5px; }
        .card-foot i { font-size: 0.7rem; }

        /* ═══ PAGINATION ═══ */
        .paging { display: flex; gap: 8px; justify-content: center; margin: 40px 0 50px; }
        .pg {
            width: 42px; height: 42px; display: flex; align-items: center; justify-content: center;
            border-radius: 10px; font-weight: 600; font-size: 0.88rem;
            border: 1.5px solid var(--border); color: var(--text-2);
            background: #fff; transition: all 0.15s;
        }
        .pg:hover { border-color: var(--primary); color: var(--primary); }
        .pg.on { background: var(--primary); color: #fff; border-color: var(--primary); }

        /* ═══ EMPTY ═══ */
        .empty {
            text-align: center; padding: 64px 24px;
            background: var(--card); border: 1px solid var(--border);
            border-radius: var(--radius);
        }
        .empty-icon {
            width: 72px; height: 72px; border-radius: 50%;
            background: var(--primary-light); display: flex;
            align-items: center; justify-content: center;
            margin: 0 auto 18px; font-size: 1.6rem; color: var(--primary);
        }
        .empty h3 { font-size: 1.1rem; font-weight: 700; margin-bottom: 6px; }
        .empty p { color: var(--text-3); font-size: 0.9rem; }

        /* ═══ FOOTER ═══ */
        .footer {
            background: #0f172a; color: #94a3b8; padding: 40px 24px;
            text-align: center; font-size: 0.85rem;
        }
        .footer a { color: #60a5fa; }
        .footer a:hover { color: #93c5fd; }
        .footer-brand { font-weight: 800; color: #fff; font-size: 1.1rem; margin-bottom: 6px; }
        .footer-brand span { color: #60a5fa; }

        /* ═══ RESPONSIVE ═══ */
        @media (max-width: 900px) {
            .featured { grid-template-columns: 1fr; }
            .featured-img { min-height: 220px; }
            .grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 600px) {
            .hero h1 { font-size: 1.8rem; }
            .hero { padding: 52px 20px 44px; }
            .grid { grid-template-columns: 1fr; }
            .nav-links { display: none; }
            .featured-body { padding: 22px; }
            .featured-body h2 { font-size: 1.2rem; }
        }
    </style>
</head>
<body>

<!-- Nav -->
<nav class="nav">
    <div class="nav-inner">
        <a class="nav-brand" href="../index.php">
            <div class="icon"><i class="fas fa-layer-group"></i></div>
            Nova<span>Hire</span>
        </a>
        <div class="nav-links">
            <a class="nav-link" href="../index.php">Home</a>
            <a class="nav-link on" href="index.php">Blog</a>
            <a class="nav-link" href="../seeker/browse_jobs.php">Jobs</a>
            <a class="nav-cta" href="../auth/login.php">Get Started</a>
        </div>
    </div>
</nav>

<!-- Hero -->
<div class="hero">
    <h1>Career Blog</h1>
    <p>Expert advice, industry insights, and practical tips to help you navigate your career journey and land your dream job.</p>
    <div class="hero-search">
        <form method="GET" action="" style="display:flex;flex:1;">
            <?php if ($cat): ?><input type="hidden" name="category" value="<?php echo htmlspecialchars($cat); ?>"><?php endif; ?>
            <input type="text" name="search" placeholder="Search articles... e.g. resume tips, interview" value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit"><i class="fas fa-search"></i></button>
        </form>
    </div>
</div>

<div class="container">
    <!-- Categories -->
    <div class="cats">
        <a href="?" class="cat-pill <?php echo !$cat ? 'on' : ''; ?>">All Posts</a>
        <?php foreach ($categories as $c): ?>
            <a href="?category=<?php echo urlencode($c['category']); ?>" class="cat-pill <?php echo $cat === $c['category'] ? 'on' : ''; ?>">
                <i class="fas <?php echo $cat_icons[$c['category']] ?? 'fa-tag'; ?>"></i>
                <?php echo htmlspecialchars($c['category']); ?>
                <span class="cnt">(<?php echo $c['cnt']; ?>)</span>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if ($featured && !$search && !$cat): ?>
    <!-- Featured Post -->
    <a href="post.php?slug=<?php echo urlencode($featured['slug'] ?? $featured['id']); ?>" class="featured">
        <div class="featured-img">
            <?php if ($featured['featured_image']): ?>
                <img src="<?php echo htmlspecialchars($featured['featured_image']); ?>" alt="">
            <?php else: ?>
                <i class="fas fa-newspaper"></i>
            <?php endif; ?>
        </div>
        <div class="featured-body">
            <div class="featured-badge"><i class="fas fa-fire"></i> Featured</div>
            <h2><?php echo htmlspecialchars($featured['title']); ?></h2>
            <p class="excerpt"><?php echo htmlspecialchars($featured['excerpt'] ?? mb_substr(strip_tags($featured['content'] ?? ''), 0, 180)); ?></p>
            <div class="featured-meta">
                <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($featured['author_name'] ?? 'NovaHire Team'); ?></span>
                <span><i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($featured['created_at'])); ?></span>
                <span><i class="fas fa-eye"></i> <?php echo number_format($featured['views_count'] ?? 0); ?> views</span>
                <span><i class="fas fa-clock"></i> <?php echo max(1, ceil(str_word_count(strip_tags($featured['content'] ?? '')) / 200)); ?> min read</span>
            </div>
        </div>
    </a>
    <?php endif; ?>

    <?php if (!empty($posts)): ?>
    <div class="section-head">
        <div class="section-title"><i class="fas fa-newspaper"></i> <?php echo $search ? 'Search Results' : ($cat ? htmlspecialchars($cat) . ' Articles' : 'Latest Articles'); ?></div>
        <div class="section-count"><?php echo $total; ?> article<?php echo $total !== 1 ? 's' : ''; ?></div>
    </div>

    <div class="grid">
        <?php foreach ($posts as $post):
            $cc = $cat_colors[$post['category']] ?? ['#1a56db','#eef2ff'];
        ?>
            <a href="post.php?slug=<?php echo urlencode($post['slug'] ?? $post['id']); ?>" class="card">
                <div class="card-img">
                    <?php if ($post['featured_image']): ?>
                        <img src="<?php echo htmlspecialchars($post['featured_image']); ?>" alt="">
                    <?php else: ?>
                        <i class="fas <?php echo $cat_icons[$post['category']] ?? 'fa-newspaper'; ?>"></i>
                    <?php endif; ?>
                    <span class="card-badge" style="background:<?php echo $cc[1]; ?>;color:<?php echo $cc[0]; ?>;"><?php echo htmlspecialchars($post['category'] ?? 'General'); ?></span>
                </div>
                <div class="card-body">
                    <h3 class="card-title"><?php echo htmlspecialchars($post['title']); ?></h3>
                    <p class="card-excerpt"><?php echo htmlspecialchars($post['excerpt'] ?? mb_substr(strip_tags($post['content'] ?? ''), 0, 120)); ?></p>
                    <div class="card-foot">
                        <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($post['author_name'] ?? 'Admin'); ?></span>
                        <span><i class="fas fa-eye"></i> <?php echo number_format($post['views_count'] ?? 0); ?></span>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if ($total_pages > 1): ?>
    <div class="paging">
        <?php if ($page > 1): ?>
            <a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($cat); ?>" class="pg"><i class="fas fa-chevron-left"></i></a>
        <?php endif; ?>
        <?php for ($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($cat); ?>" class="pg <?php echo $i === $page ? 'on' : ''; ?>"><?php echo $i; ?></a>
        <?php endfor; ?>
        <?php if ($page < $total_pages): ?>
            <a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($cat); ?>" class="pg"><i class="fas fa-chevron-right"></i></a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <div class="empty">
        <div class="empty-icon"><i class="fas fa-search"></i></div>
        <h3>No articles found</h3>
        <p>Try a different search term or browse all categories.</p>
    </div>
    <?php endif; ?>
</div>

<!-- Footer -->
<div class="footer">
    <div class="footer-brand">Nova<span>Hire</span></div>
    <p>&copy; <?php echo date('Y'); ?> NovaHire. All rights reserved. Built for job seekers and recruiters.</p>
</div>

</body>
</html>
