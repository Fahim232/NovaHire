<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$slug = trim($_GET['slug'] ?? '');
$id = intval($_GET['id'] ?? 0);

if (!$slug && !$id) { header('Location: index.php'); exit; }

if ($slug) {
    $stmt = mysqli_prepare($con, "SELECT * FROM blog_posts WHERE (slug = ? OR id = ?) AND status = 'published'");
    mysqli_stmt_bind_param($stmt, "si", $slug, $id);
} else {
    $stmt = mysqli_prepare($con, "SELECT * FROM blog_posts WHERE id = ? AND status = 'published'");
    mysqli_stmt_bind_param($stmt, "i", $id);
}
mysqli_stmt_execute($stmt);
$post = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$post) { header('Location: index.php'); exit; }

mysqli_query($con, "UPDATE blog_posts SET views_count = views_count + 1 WHERE id = {$post['id']}");
$post['views_count']++;

$related_stmt = mysqli_prepare($con, "SELECT p.id, p.title, p.slug, p.excerpt, p.featured_image, p.category, p.created_at, p.views_count,
        COALESCE(ui.username, 'NovaHire Team') AS author_name
        FROM blog_posts p
        LEFT JOIN user_info ui ON ui.id = p.author_id
        WHERE p.category = ? AND p.id != ? AND p.status = 'published' ORDER BY p.created_at DESC LIMIT 3");
mysqli_stmt_bind_param($related_stmt, "si", $post['category'], $post['id']);
mysqli_stmt_execute($related_stmt);
$related = mysqli_fetch_all(mysqli_stmt_get_result($related_stmt), MYSQLI_ASSOC);
mysqli_stmt_close($related_stmt);

$cat_icons = [
    'Career Advice' => 'fa-compass', 'Interview Tips' => 'fa-comments', 'Resume Tips' => 'fa-file-alt',
    'Job Search' => 'fa-search', 'Salary' => 'fa-coins', 'Remote Work' => 'fa-laptop-house',
    'Leadership' => 'fa-crown', 'Technology' => 'fa-microchip', 'Marketing' => 'fa-bullhorn',
    'Finance' => 'fa-chart-line', 'Design' => 'fa-palette', 'General' => 'fa-newspaper',
];
$cat_colors = [
    'Career Advice' => ['#1a56db','#eef2ff'], 'Interview Tips' => ['#059669','#ecfdf5'],
    'Resume Tips' => ['#ea580c','#fff7ed'], 'Job Search' => ['#2563eb','#eff6ff'],
    'Salary' => ['#ca8a04','#fefce8'], 'Remote Work' => ['#0ea5e9','#f5f3ff'],
    'Technology' => ['#0891b2','#ecfeff'], 'Marketing' => ['#e11d48','#fff1f2'],
];
$cc = $cat_colors[$post['category']] ?? ['#1a56db','#eef2ff'];
$read_time = max(1, ceil(str_word_count(strip_tags($post['content'] ?? '')) / 200));
$url = 'https://novahire.com/blog/post.php?slug=' . urlencode($post['slug'] ?? $post['id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($post['title']); ?> - NovaHire Blog</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --bg: #f8fafc; --card: #ffffff; --text: #0f172a; --text-2: #334155;
            --text-3: #64748b; --border: #e2e8f0; --primary: #1a56db;
            --primary-light: #eef2ff; --primary-dark: #3730a3;
        }
        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--bg); color: var(--text); -webkit-font-smoothing: antialiased;
        }

        /* ═══ NAV ═══ */
        .nav {
            background: rgba(255,255,255,0.92); border-bottom: 1px solid var(--border);
            padding: 14px 0; position: sticky; top: 0; z-index: 100;
            backdrop-filter: blur(12px);
        }
        .nav-inner {
            max-width: 1200px; margin: 0 auto; padding: 0 24px;
            display: flex; align-items: center; justify-content: space-between;
        }
        .nav-brand { display: flex; align-items: center; gap: 10px; font-weight: 800; font-size: 1.15rem; }
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

        /* ═══ ARTICLE ═══ */
        .article-wrap { max-width: 780px; margin: 0 auto; padding: 40px 24px 60px; }
        .article-badge {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 5px 14px; border-radius: 20px; font-size: 0.72rem;
            font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;
            margin-bottom: 18px;
            background: <?php echo $cc[1]; ?>; color: <?php echo $cc[0]; ?>;
        }
        .article-title { font-size: 2.2rem; font-weight: 800; line-height: 1.25; margin-bottom: 18px; letter-spacing: -0.5px; }
        .article-meta {
            display: flex; align-items: center; gap: 20px; flex-wrap: wrap;
            margin-bottom: 28px; padding-bottom: 24px; border-bottom: 1px solid var(--border);
        }
        .meta-item { display: flex; align-items: center; gap: 6px; font-size: 0.85rem; color: var(--text-3); }
        .meta-item i { color: var(--primary); font-size: 0.8rem; }
        .author-avatar {
            width: 40px; height: 40px; border-radius: 50%;
            background: linear-gradient(135deg, #1a56db, #0ea5e9);
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-weight: 700; font-size: 0.85rem; flex-shrink: 0;
        }

        /* Featured Image */
        .article-hero {
            width: 100%; border-radius: var(--radius, 16px); margin-bottom: 32px;
            max-height: 440px; object-fit: cover; background: linear-gradient(135deg, #eef2ff, #c7d2fe);
        }

        /* Content */
        .article-content { font-size: 1.05rem; line-height: 1.85; color: var(--text-2); }
        .article-content h2 { font-size: 1.4rem; font-weight: 700; color: var(--text); margin: 32px 0 14px; }
        .article-content h3 { font-size: 1.2rem; font-weight: 700; color: var(--text); margin: 26px 0 12px; }
        .article-content p { margin-bottom: 18px; }
        .article-content ul, .article-content ol { margin: 0 0 18px 24px; }
        .article-content li { margin-bottom: 8px; }
        .article-content blockquote {
            border-left: 4px solid var(--primary); padding: 18px 24px;
            margin: 24px 0; background: var(--primary-light);
            border-radius: 0 12px 12px 0; font-style: italic; color: var(--text-2);
        }
        .article-content code {
            background: #f1f5f9; padding: 2px 8px; border-radius: 4px;
            font-size: 0.9em; color: #e11d48;
        }
        .article-content pre {
            background: #0f172a; color: #e2e8f0; padding: 20px;
            border-radius: 12px; overflow-x: auto; margin: 20px 0;
            font-size: 0.88rem; line-height: 1.6;
        }
        .article-content pre code { background: none; color: inherit; padding: 0; }

        /* Share */
        .share-bar {
            margin-top: 40px; padding-top: 24px; border-top: 1px solid var(--border);
            display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;
        }
        .share-label { font-weight: 700; font-size: 0.9rem; display: flex; align-items: center; gap: 6px; }
        .share-label i { color: var(--primary); }
        .share-btns { display: flex; gap: 8px; }
        .share-btn {
            display: flex; align-items: center; gap: 6px;
            padding: 9px 18px; border-radius: 8px; font-size: 0.82rem;
            font-weight: 600; color: #fff; transition: all 0.2s;
        }
        .share-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        .share-btn.fb { background: #1877f2; }
        .share-btn.tw { background: #1da1f2; }
        .share-btn.li { background: #0077b5; }
        .share-btn.wa { background: #25d366; }

        /* ═══ RELATED ═══ */
        .related-section {
            max-width: 1200px; margin: 0 auto 60px; padding: 0 24px;
        }
        .related-head { display: flex; align-items: center; gap: 8px; margin-bottom: 20px; font-size: 1.1rem; font-weight: 700; }
        .related-head i { color: var(--primary); }
        .related-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
        .related-card {
            background: var(--card); border: 1px solid var(--border);
            border-radius: 14px; overflow: hidden; transition: all 0.2s;
        }
        .related-card:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(0,0,0,0.05); }
        .related-thumb {
            height: 140px; background: linear-gradient(135deg, #eef2ff, #c7d2fe);
            display: flex; align-items: center; justify-content: center; overflow: hidden;
        }
        .related-thumb img { width: 100%; height: 100%; object-fit: cover; }
        .related-thumb i { font-size: 2rem; color: var(--primary); opacity: 0.3; }
        .related-body { padding: 16px; }
        .related-cat {
            display: inline-block; padding: 3px 10px; border-radius: 12px;
            font-size: 0.65rem; font-weight: 700; text-transform: uppercase;
            margin-bottom: 8px;
        }
        .related-body h4 { font-size: 0.92rem; font-weight: 700; line-height: 1.4; margin-bottom: 8px; }
        .related-body h4:hover { color: var(--primary); }
        .related-meta { font-size: 0.75rem; color: var(--text-3); display: flex; gap: 12px; }

        /* ═══ FOOTER ═══ */
        .footer {
            background: #0f172a; color: #94a3b8; padding: 40px 24px;
            text-align: center; font-size: 0.85rem;
        }
        .footer a { color: #60a5fa; }
        .footer-brand { font-weight: 800; color: #fff; font-size: 1.1rem; margin-bottom: 6px; }
        .footer-brand span { color: #60a5fa; }

        @media (max-width: 768px) {
            .article-title { font-size: 1.6rem; }
            .related-grid { grid-template-columns: 1fr; }
            .nav-links { display: none; }
            .share-bar { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>

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
        </div>
    </div>
</nav>

<article class="article-wrap">
    <span class="article-badge"><i class="fas <?php echo $cat_icons[$post['category']] ?? 'fa-tag'; ?>"></i> <?php echo htmlspecialchars($post['category'] ?? 'General'); ?></span>
    
    <h1 class="article-title"><?php echo htmlspecialchars($post['title']); ?></h1>
    
    <div class="article-meta">
        <div class="author-avatar"><?php echo strtoupper(mb_substr($post['author_name'] ?? 'N', 0, 1)); ?></div>
        <div class="meta-item"><i class="fas fa-user"></i> <?php echo htmlspecialchars($post['author_name'] ?? 'NovaHire Team'); ?></div>
        <div class="meta-item"><i class="fas fa-calendar"></i> <?php echo date('F d, Y', strtotime($post['created_at'])); ?></div>
        <div class="meta-item"><i class="fas fa-eye"></i> <?php echo number_format($post['views_count']); ?> views</div>
        <div class="meta-item"><i class="fas fa-clock"></i> <?php echo $read_time; ?> min read</div>
    </div>

    <?php if ($post['featured_image']): ?>
        <img src="<?php echo htmlspecialchars($post['featured_image']); ?>" class="article-hero" alt="">
    <?php endif; ?>

    <div class="article-content">
        <?php echo $post['content']; ?>
    </div>

    <div class="share-bar">
        <div class="share-label"><i class="fas fa-share-alt"></i> Share this article</div>
        <div class="share-btns">
            <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode($url); ?>" target="_blank" class="share-btn fb"><i class="fab fa-facebook-f"></i> Facebook</a>
            <a href="https://twitter.com/intent/tweet?text=<?php echo urlencode($post['title']); ?>&url=<?php echo urlencode($url); ?>" target="_blank" class="share-btn tw"><i class="fab fa-twitter"></i> Twitter</a>
            <a href="https://www.linkedin.com/shareArticle?mini=true&url=<?php echo urlencode($url); ?>&title=<?php echo urlencode($post['title']); ?>" target="_blank" class="share-btn li"><i class="fab fa-linkedin-in"></i> LinkedIn</a>
            <a href="https://wa.me/?text=<?php echo urlencode($post['title'] . ' ' . $url); ?>" target="_blank" class="share-btn wa"><i class="fab fa-whatsapp"></i> WhatsApp</a>
        </div>
    </div>
</article>

<?php if (!empty($related)): ?>
<div class="related-section">
    <div class="related-head"><i class="fas fa-newspaper"></i> Related Articles</div>
    <div class="related-grid">
        <?php foreach ($related as $r):
            $rc = $cat_colors[$r['category']] ?? ['#1a56db','#eef2ff'];
        ?>
            <a href="post.php?slug=<?php echo urlencode($r['slug'] ?? $r['id']); ?>" class="related-card">
                <div class="related-thumb">
                    <?php if ($r['featured_image']): ?>
                        <img src="<?php echo htmlspecialchars($r['featured_image']); ?>" alt="">
                    <?php else: ?>
                        <i class="fas <?php echo $cat_icons[$r['category']] ?? 'fa-newspaper'; ?>"></i>
                    <?php endif; ?>
                </div>
                <div class="related-body">
                    <span class="related-cat" style="background:<?php echo $rc[1]; ?>;color:<?php echo $rc[0]; ?>;"><?php echo htmlspecialchars($r['category'] ?? 'General'); ?></span>
                    <h4><?php echo htmlspecialchars($r['title']); ?></h4>
                    <div class="related-meta">
                        <span><i class="fas fa-user" style="margin-right:3px;"></i> <?php echo htmlspecialchars($r['author_name'] ?? 'Admin'); ?></span>
                        <span><i class="fas fa-eye" style="margin-right:3px;"></i> <?php echo number_format($r['views_count'] ?? 0); ?></span>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="footer">
    <div class="footer-brand">Nova<span>Hire</span></div>
    <p>&copy; <?php echo date('Y'); ?> NovaHire. All rights reserved.</p>
</div>

</body>
</html>
