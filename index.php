<?php
require_once __DIR__ . '/includes/bootstrap.php';

session_start();

$is_logged_in = isset($_SESSION['id']);
$is_company_logged_in = isset($_SESSION['company_id']);
$is_admin_logged_in = isset($_SESSION['admin_username']);

// If logged in, redirect to appropriate dashboard
if ($is_logged_in && !$is_company_logged_in) {
    header('Location: ' . BASE_URL . '/seeker/seeker_dashboard.php');
    exit;
} elseif ($is_company_logged_in) {
    header('Location: ' . BASE_URL . '/company/index.php');
    exit;
} elseif ($is_admin_logged_in) {
    header('Location: ' . BASE_URL . '/admin/admin_dashboard.php');
    exit;
}

// Stats
$con_db = @mysqli_connect('127.0.0.1', 'root', '', 'projects');
$total_jobs = 0;
$total_companies = 0;
$total_users = 0;
$total_applications = 0;
if ($con_db) {
    $r = mysqli_query($con_db, "SELECT COUNT(*) as cnt FROM company_jobs WHERE status='active'");
    $total_jobs = mysqli_fetch_assoc($r)['cnt'] ?? 0;
    $r = mysqli_query($con_db, "SELECT COUNT(*) as cnt FROM companies WHERE status='active'");
    $total_companies = mysqli_fetch_assoc($r)['cnt'] ?? 0;
    $r = mysqli_query($con_db, "SELECT COUNT(*) as cnt FROM user_info");
    $total_users = mysqli_fetch_assoc($r)['cnt'] ?? 0;
    $r = mysqli_query($con_db, "SELECT COUNT(*) as cnt FROM job_applications");
    $total_applications = mysqli_fetch_assoc($r)['cnt'] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NovaHire — Find Your Dream Job | Top Companies Hiring Now</title>
    <meta name="description" content="NovaHire - AI-powered job portal connecting job seekers with top companies. Find your dream job, build your career, and get hired faster.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Sora:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.1/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <style>
        :root {
            --lh-primary: var(--primary, #1a56db);
            --lh-primary-dark: var(--primary-dark, #1e40af);
            --lh-secondary: var(--secondary, #0ea5e9);
            --lh-accent: var(--accent, #d97706);
            --lh-gradient: var(--grad, linear-gradient(135deg, #1a56db 0%, #0ea5e9 100%));
            --lh-gradient-hero: linear-gradient(135deg, #0c1222 0%, var(--primary, #1a56db) 35%, var(--secondary, #0ea5e9) 100%);
            --lh-text: var(--dark, #0f172a);
            --lh-text-muted: var(--text-muted, #64748b);
            --lh-bg: var(--bg-card, #ffffff);
            --lh-bg-alt: var(--bg, #f8fafc);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            color: var(--lh-text);
            background: var(--lh-bg);
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }
        h1, h2, h3, h4, h5, h6 { font-family: 'Sora', 'Inter', sans-serif; }

        /* ═══ NAVBAR ═══ */
        .lh-nav {
            position: fixed; top: 0; left: 0; right: 0; z-index: 1000;
            padding: 16px 0;
            transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .lh-nav.scrolled {
            background: rgba(255,255,255,0.92);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            box-shadow: 0 1px 3px rgba(0,0,0,0.08), 0 4px 14px rgba(0,0,0,0.04);
            padding: 10px 0;
        }
        .lh-nav-inner {
            max-width: 1200px; margin: 0 auto; padding: 0 24px;
            display: flex; align-items: center; justify-content: space-between;
        }
        .lh-logo {
            display: flex; align-items: center; gap: 10px;
            font-family: 'Sora', sans-serif; font-weight: 800; font-size: 1.4rem;
            color: #fff; text-decoration: none; letter-spacing: -0.5px;
        }
        .lh-nav.scrolled .lh-logo { color: var(--lh-text); }
        .lh-logo-icon {
            width: 40px; height: 40px; border-radius: 12px;
            background: linear-gradient(135deg, #fbbf24, #d97706);
            display: flex; align-items: center; justify-content: center;
            font-size: 1rem; color: #1e293b;
            box-shadow: 0 4px 12px rgba(217,119,6,0.4);
        }
        .lh-logo span { color: #fbbf24; }
        .lh-nav.scrolled .lh-logo span { color: var(--lh-primary); }

        .lh-nav-links { display: flex; align-items: center; gap: 8px; list-style: none; margin: 0; padding: 0; }
        .lh-nav-links a {
            color: rgba(255,255,255,0.8); font-size: 0.88rem; font-weight: 600;
            padding: 8px 16px; border-radius: 10px; text-decoration: none;
            transition: all 0.25s;
        }
        .lh-nav-links a:hover { color: #fff; background: rgba(255,255,255,0.12); }
        .lh-nav.scrolled .lh-nav-links a { color: var(--lh-text-muted); }
        .lh-nav.scrolled .lh-nav-links a:hover { color: var(--lh-primary); background: rgba(59,130,246,0.08); }

        .lh-nav-btns { display: flex; align-items: center; gap: 10px; }
        .lh-btn-getstarted {
            background: linear-gradient(135deg, #fbbf24, #d97706);
            color: #1e293b; font-weight: 800; font-size: 0.88rem;
            padding: 10px 24px; border-radius: 12px; text-decoration: none;
            border: none; transition: all 0.3s;
            box-shadow: 0 4px 14px rgba(217,119,6,0.35);
        }
        .lh-btn-getstarted:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(217,119,6,0.5);
            color: #1e293b; text-decoration: none;
        }

        .lh-mobile-toggle {
            display: none; background: none; border: none; color: #fff;
            font-size: 1.4rem; cursor: pointer; padding: 8px;
        }
        .lh-nav.scrolled .lh-mobile-toggle { color: var(--lh-text); }

        /* ═══ HERO ═══ */
        .lh-hero {
            position: relative; min-height: 100vh;
            background: var(--lh-gradient-hero);
            display: flex; align-items: center;
            overflow: hidden; padding: 120px 0 80px;
        }
        .lh-hero::before {
            content: ''; position: absolute; top: -40%; right: -15%;
            width: 800px; height: 800px; border-radius: 50%;
            background: radial-gradient(circle, rgba(251,191,36,0.15) 0%, transparent 70%);
            animation: heroPulse 8s ease-in-out infinite;
        }
        .lh-hero::after {
            content: ''; position: absolute; bottom: -30%; left: -10%;
            width: 600px; height: 600px; border-radius: 50%;
            background: radial-gradient(circle, rgba(6,182,212,0.2) 0%, transparent 70%);
            animation: heroPulse 10s ease-in-out infinite reverse;
        }
        @keyframes heroPulse {
            0%, 100% { transform: scale(1); opacity: 0.6; }
            50% { transform: scale(1.1); opacity: 1; }
        }

        .lh-hero-content { position: relative; z-index: 3; }
        .lh-hero-badge {
            display: inline-flex; align-items: center; gap: 8px;
            background: rgba(255,255,255,0.12); border: 1px solid rgba(255,255,255,0.2);
            backdrop-filter: blur(8px); border-radius: 999px;
            padding: 8px 20px; margin-bottom: 28px;
            color: rgba(255,255,255,0.9); font-size: 0.82rem; font-weight: 700;
            letter-spacing: 0.02em;
            animation: fadeInUp 0.8s ease both;
        }
        .lh-hero-badge i { color: #fbbf24; }
        .lh-hero-badge .pulse-dot {
            width: 8px; height: 8px; border-radius: 50%; background: #34d399;
            animation: pulseDot 2s ease-in-out infinite;
        }
        @keyframes pulseDot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(1.4); }
        }

        .lh-hero h1 {
            font-size: clamp(2.5rem, 5.5vw, 4rem);
            font-weight: 900; color: #fff; line-height: 1.08;
            letter-spacing: -2px; margin-bottom: 22px;
            animation: fadeInUp 0.8s ease 0.15s both;
        }
        .lh-hero h1 .highlight {
            background: linear-gradient(90deg, #fde68a, #fbbf24, #d97706);
            -webkit-background-clip: text; background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .lh-hero-desc {
            font-size: 1.15rem; color: rgba(255,255,255,0.8);
            max-width: 520px; line-height: 1.7; margin-bottom: 36px;
            animation: fadeInUp 0.8s ease 0.3s both;
        }

        .lh-hero-actions {
            display: flex; gap: 14px; flex-wrap: wrap;
            animation: fadeInUp 0.8s ease 0.45s both;
        }
        .lh-hero-btn-primary {
            display: inline-flex; align-items: center; gap: 10px;
            background: linear-gradient(135deg, #fbbf24, #d97706);
            color: #1e293b; font-weight: 800; font-size: 1rem;
            padding: 16px 36px; border-radius: 16px; text-decoration: none;
            border: none; transition: all 0.3s;
            box-shadow: 0 8px 30px rgba(217,119,6,0.4);
        }
        .lh-hero-btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 14px 40px rgba(217,119,6,0.55);
            color: #1e293b; text-decoration: none;
        }
        .lh-hero-btn-secondary {
            display: inline-flex; align-items: center; gap: 10px;
            background: rgba(255,255,255,0.1); border: 1.5px solid rgba(255,255,255,0.25);
            color: #fff; font-weight: 700; font-size: 1rem;
            padding: 16px 36px; border-radius: 16px; text-decoration: none;
            backdrop-filter: blur(4px); transition: all 0.3s;
        }
        .lh-hero-btn-secondary:hover {
            background: rgba(255,255,255,0.2); border-color: rgba(255,255,255,0.4);
            color: #fff; text-decoration: none; transform: translateY(-2px);
        }

        .lh-hero-stats {
            display: flex; gap: 40px; margin-top: 50px;
            animation: fadeInUp 0.8s ease 0.6s both;
        }
        .lh-hero-stat h3 {
            font-size: 2rem; font-weight: 900; color: #fff; margin-bottom: 2px;
        }
        .lh-hero-stat p {
            color: rgba(255,255,255,0.6); font-size: 0.85rem; font-weight: 600;
        }

        /* Hero floating visuals */
        .lh-hero-visuals {
            position: relative; z-index: 2; height: 500px;
            animation: fadeInRight 1s ease 0.3s both;
        }
        .lh-float-card {
            position: absolute; background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px); border-radius: 18px;
            padding: 16px 20px; box-shadow: 0 20px 50px rgba(0,0,0,0.15);
            display: flex; align-items: center; gap: 14px;
            animation: floatCard 6s ease-in-out infinite;
        }
        .lh-float-card:nth-child(1) { top: 10%; left: 5%; animation-delay: 0s; }
        .lh-float-card:nth-child(2) { top: 35%; right: 0; animation-delay: -2s; }
        .lh-float-card:nth-child(3) { bottom: 15%; left: 10%; animation-delay: -4s; }
        .lh-float-card:nth-child(4) { top: 60%; left: -5%; animation-delay: -1s; }
        @keyframes floatCard {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-14px); }
        }
        .lh-fc-icon {
            width: 48px; height: 48px; border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem; flex-shrink: 0;
        }
        .lh-fc-text h6 { margin: 0; font-weight: 700; font-size: 0.88rem; color: #1e293b; }
        .lh-fc-text small { color: #64748b; font-size: 0.78rem; }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: none; }
        }
        @keyframes fadeInRight {
            from { opacity: 0; transform: translateX(40px); }
            to { opacity: 1; transform: none; }
        }

        /* ═══ TRUSTED BY ═══ */
        .lh-trusted {
            padding: 50px 0; background: var(--lh-bg-alt);
            border-bottom: 1px solid #f1f5f9;
        }
        .lh-trusted p {
            text-align: center; color: #94a3b8; font-size: 0.82rem;
            font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em;
            margin-bottom: 24px;
        }
        .lh-trusted-logos {
            display: flex; align-items: center; justify-content: center;
            gap: 48px; flex-wrap: wrap; opacity: 0.5;
        }
        .lh-trusted-logos i { font-size: 2.2rem; color: #94a3b8; }

        /* ═══ FEATURES ═══ */
        .lh-features { padding: 100px 0; }
        .lh-section-badge {
            display: inline-flex; align-items: center; gap: 6px;
            background: rgba(59,130,246,0.08); border: 1px solid rgba(59,130,246,0.15);
            color: var(--lh-primary); font-size: 0.78rem; font-weight: 700;
            padding: 6px 16px; border-radius: 999px; margin-bottom: 16px;
            text-transform: uppercase; letter-spacing: 0.05em;
        }
        .lh-section-title {
            font-size: clamp(1.8rem, 3.5vw, 2.5rem);
            font-weight: 900; color: var(--lh-text);
            letter-spacing: -1px; margin-bottom: 14px;
        }
        .lh-section-desc {
            color: var(--lh-text-muted); font-size: 1.05rem;
            max-width: 560px; line-height: 1.7;
        }
        .lh-section-center { text-align: center; margin-bottom: 56px; }
        .lh-section-center .lh-section-desc { margin: 0 auto; }

        .lh-feature-card {
            background: #fff; border: 1px solid #f1f5f9;
            border-radius: 20px; padding: 36px 28px;
            transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative; overflow: hidden;
        }
        .lh-feature-card::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px;
            background: var(--lh-gradient); opacity: 0;
            transition: opacity 0.35s;
        }
        .lh-feature-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 25px 60px rgba(59,130,246,0.12);
            border-color: transparent;
        }
        .lh-feature-card:hover::before { opacity: 1; }
        .lh-feature-icon {
            width: 64px; height: 64px; border-radius: 18px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem; margin-bottom: 20px;
            transition: transform 0.35s;
        }
        .lh-feature-card:hover .lh-feature-icon { transform: scale(1.08) rotate(-3deg); }
        .lh-feature-card h4 {
            font-size: 1.15rem; font-weight: 800; margin-bottom: 10px;
            color: var(--lh-text);
        }
        .lh-feature-card p {
            color: var(--lh-text-muted); font-size: 0.9rem; line-height: 1.7;
            margin: 0;
        }

        /* ═══ HOW IT WORKS ═══ */
        .lh-how { padding: 100px 0; background: var(--lh-bg-alt); }
        .lh-steps {
            display: grid; grid-template-columns: repeat(4, 1fr); gap: 24px;
            position: relative;
        }
        .lh-steps::before {
            content: ''; position: absolute; top: 48px; left: 12.5%; right: 12.5%;
            height: 3px; background: linear-gradient(90deg, #e0e7ff, #c7d2fe, #93c5fd, #60a5fa);
            border-radius: 2px; z-index: 0;
        }
        .lh-step {
            text-align: center; position: relative; z-index: 1;
        }
        .lh-step-num {
            width: 64px; height: 64px; border-radius: 50%;
            background: linear-gradient(135deg, #3b82f6, #06b6d4);
            color: #fff; font-size: 1.4rem; font-weight: 900;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 20px;
            box-shadow: 0 8px 24px rgba(59,130,246,0.35);
            border: 4px solid #fff;
        }
        .lh-step h4 { font-size: 1.05rem; font-weight: 800; margin-bottom: 8px; color: var(--lh-text); }
        .lh-step p { color: var(--lh-text-muted); font-size: 0.88rem; line-height: 1.6; }

        /* ═══ STATS ═══ */
        .lh-stats {
            padding: 80px 0;
            background: var(--lh-gradient-hero);
            position: relative; overflow: hidden;
        }
        .lh-stats::before {
            content: ''; position: absolute; top: -50%; right: -10%;
            width: 500px; height: 500px; border-radius: 50%;
            background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%);
        }
        .lh-stat-item { text-align: center; position: relative; z-index: 2; }
        .lh-stat-item .icon {
            font-size: 2rem; color: rgba(255,255,255,0.25); margin-bottom: 14px;
        }
        .lh-stat-item h2 {
            font-size: 2.8rem; font-weight: 900; color: #fff;
            margin-bottom: 4px; letter-spacing: -1px;
        }
        .lh-stat-item p { color: rgba(255,255,255,0.7); font-size: 0.9rem; font-weight: 600; }

        /* ═══ TESTIMONIALS ═══ */
        .lh-testimonials { padding: 100px 0; }
        .lh-testimonial-card {
            background: #fff; border: 1px solid #f1f5f9;
            border-radius: 20px; padding: 32px;
            transition: all 0.3s;
            position: relative;
        }
        .lh-testimonial-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 50px rgba(0,0,0,0.08);
        }
        .lh-testimonial-card .stars { color: #fbbf24; font-size: 0.9rem; margin-bottom: 16px; }
        .lh-testimonial-card .quote {
            color: var(--lh-text); font-size: 0.95rem; line-height: 1.7;
            margin-bottom: 20px; font-style: italic;
        }
        .lh-testimonial-author {
            display: flex; align-items: center; gap: 14px;
        }
        .lh-ta-avatar {
            width: 48px; height: 48px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-weight: 800; font-size: 1rem; color: #fff; flex-shrink: 0;
        }
        .lh-ta-info h6 { margin: 0; font-weight: 700; font-size: 0.92rem; color: var(--lh-text); }
        .lh-ta-info small { color: var(--lh-text-muted); font-size: 0.8rem; }

        /* ═══ CTA ═══ */
        .lh-cta {
            padding: 100px 0;
            background: var(--lh-bg-alt);
        }
        .lh-cta-card {
            background: var(--lh-gradient-hero);
            border-radius: 28px; padding: 70px 50px;
            text-align: center; position: relative; overflow: hidden;
        }
        .lh-cta-card::before {
            content: ''; position: absolute; top: -40%; right: -15%;
            width: 500px; height: 500px; border-radius: 50%;
            background: radial-gradient(circle, rgba(251,191,36,0.12) 0%, transparent 70%);
        }
        .lh-cta-card h2 {
            font-size: clamp(1.8rem, 3.5vw, 2.5rem);
            font-weight: 900; color: #fff; margin-bottom: 16px;
            letter-spacing: -1px; position: relative; z-index: 2;
        }
        .lh-cta-card p {
            color: rgba(255,255,255,0.8); font-size: 1.1rem;
            max-width: 520px; margin: 0 auto 36px;
            position: relative; z-index: 2;
        }
        .lh-cta-btns {
            display: flex; gap: 14px; justify-content: center; flex-wrap: wrap;
            position: relative; z-index: 2;
        }

        /* ═══ FOOTER ═══ */
        .lh-footer {
            background: #0f172a; color: #94a3b8; padding: 60px 0 0;
        }
        .lh-footer-brand {
            font-family: 'Sora', sans-serif; font-weight: 800;
            font-size: 1.4rem; color: #fff; margin-bottom: 12px;
        }
        .lh-footer-brand span { color: #fbbf24; }
        .lh-footer-desc { font-size: 0.88rem; line-height: 1.7; max-width: 300px; }
        .lh-footer h5 {
            color: #fff; font-weight: 700; font-size: 0.95rem;
            margin-bottom: 18px;
        }
        .lh-footer-links { list-style: none; padding: 0; margin: 0; }
        .lh-footer-links li { margin-bottom: 10px; }
        .lh-footer-links a {
            color: #94a3b8; font-size: 0.88rem; text-decoration: none;
            transition: all 0.25s;
        }
        .lh-footer-links a:hover { color: #fbbf24; padding-left: 4px; }
        .lh-footer-bottom {
            border-top: 1px solid rgba(255,255,255,0.06);
            margin-top: 40px; padding: 22px 0; text-align: center;
        }
        .lh-footer-bottom p { color: #475569; font-size: 0.82rem; margin: 0; }
        .lh-footer-social { display: flex; gap: 10px; margin-top: 18px; }
        .lh-footer-social a {
            width: 40px; height: 40px; border-radius: 12px;
            background: rgba(255,255,255,0.06);
            display: flex; align-items: center; justify-content: center;
            color: #94a3b8; transition: all 0.25s; text-decoration: none;
        }
        .lh-footer-social a:hover {
            background: var(--lh-primary); color: #fff; transform: translateY(-2px);
        }

        /* ═══ RESPONSIVE ═══ */
        @media (max-width: 991px) {
            .lh-nav-links { display: none; }
            .lh-mobile-toggle { display: block; }
            .lh-hero { padding: 100px 0 60px; min-height: auto; }
            .lh-hero-visuals { display: none; }
            .lh-hero h1 { font-size: 2.5rem; }
            .lh-hero-stats { gap: 24px; }
            .lh-hero-stat h3 { font-size: 1.6rem; }
            .lh-steps { grid-template-columns: repeat(2, 1fr); gap: 32px; }
            .lh-steps::before { display: none; }
            .lh-trusted-logos { gap: 28px; }
        }
        @media (max-width: 767px) {
            .lh-hero { padding: 90px 0 50px; }
            .lh-hero h1 { font-size: 2rem; letter-spacing: -1px; }
            .lh-hero-desc { font-size: 1rem; }
            .lh-hero-actions { flex-direction: column; }
            .lh-hero-btn-primary, .lh-hero-btn-secondary { width: 100%; justify-content: center; }
            .lh-hero-stats { gap: 16px; flex-wrap: wrap; }
            .lh-hero-stat h3 { font-size: 1.3rem; }
            .lh-hero-stat p { font-size: 0.78rem; }
            .lh-features, .lh-how, .lh-testimonials, .lh-cta { padding: 60px 0; }
            .lh-steps { grid-template-columns: 1fr; }
            .lh-cta-card { padding: 40px 24px; }
            .lh-footer { padding: 40px 0 0; }
        }
        @media (max-width: 575px) {
            .lh-hero h1 { font-size: 1.7rem; }
            .lh-section-title { font-size: 1.5rem; }
        }

        /* ═══ MOBILE MENU ═══ */
        .lh-mobile-menu {
            display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(15,23,42,0.95); backdrop-filter: blur(10px);
            z-index: 2000; padding: 80px 24px 40px;
            flex-direction: column; gap: 8px;
        }
        .lh-mobile-menu.active { display: flex; }
        .lh-mobile-menu a {
            color: #fff; font-size: 1.1rem; font-weight: 700;
            padding: 16px 20px; border-radius: 14px;
            text-decoration: none; transition: all 0.25s;
        }
        .lh-mobile-menu a:hover { background: rgba(255,255,255,0.1); }
        .lh-mobile-close {
            position: absolute; top: 20px; right: 20px;
            background: none; border: none; color: #fff;
            font-size: 1.5rem; cursor: pointer;
        }

        /* ═══ SCROLL ANIMATIONS ═══ */
        .reveal {
            opacity: 0; transform: translateY(30px);
            transition: all 0.7s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .reveal.visible { opacity: 1; transform: none; }
    </style>
</head>
<body>

<!-- ═══ NAVBAR ═══ -->
<nav class="lh-nav" id="mainNav">
    <div class="lh-nav-inner">
        <a href="index.php" class="lh-logo">
            <div class="lh-logo-icon"><i class="fas fa-layer-group"></i></div>
            Nova<span>Hire</span>
        </a>
        <ul class="lh-nav-links">
            <li><a href="#features">Features</a></li>
            <li><a href="#how-it-works">How It Works</a></li>
            <li><a href="seeker/browse_jobs.php">Browse Jobs</a></li>
            <li><a href="blog/">Blog</a></li>
        </ul>
        <div class="lh-nav-btns">
            <a href="auth/login.php" class="lh-btn-getstarted">
                <i class="fas fa-rocket mr-1"></i> Get Started
            </a>
        </div>
        <button class="lh-mobile-toggle" onclick="toggleMobileMenu()">
            <i class="fas fa-bars"></i>
        </button>
    </div>
</nav>

<!-- Mobile Menu -->
<div class="lh-mobile-menu" id="mobileMenu">
    <button class="lh-mobile-close" onclick="toggleMobileMenu()"><i class="fas fa-times"></i></button>
    <a href="#features" onclick="toggleMobileMenu()">Features</a>
    <a href="#how-it-works" onclick="toggleMobileMenu()">How It Works</a>
    <a href="seeker/browse_jobs.php">Browse Jobs</a>
    <a href="blog/">Blog</a>
    <a href="auth/login.php" style="background: rgba(251,191,36,0.15); color: #fbbf24; margin-top: 16px;">
        <i class="fas fa-rocket mr-2"></i> Get Started
    </a>
</div>

<!-- ═══ HERO ═══ -->
<section class="lh-hero">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6 lh-hero-content">
                <div class="lh-hero-badge">
                    <span class="pulse-dot"></span>
                    AI-Powered Career Platform
                </div>
                <h1>
                    Find Your<br>
                    <span class="highlight">Dream Job</span><br>
                    Today
                </h1>
                <p class="lh-hero-desc">
                    Connect with top companies, showcase your skills, and land your perfect role.
                    Our AI-powered platform matches you with the best opportunities.
                </p>
                <div class="lh-hero-actions">
                    <a href="auth/login.php" class="lh-hero-btn-primary">
                        <i class="fas fa-rocket"></i> Get Started Free
                    </a>
                    <a href="seeker/browse_jobs.php" class="lh-hero-btn-secondary">
                        <i class="fas fa-search"></i> Browse Jobs
                    </a>
                </div>
                <div class="lh-hero-stats">
                    <div class="lh-hero-stat">
                        <h3><?php echo number_format($total_jobs); ?>+</h3>
                        <p>Active Jobs</p>
                    </div>
                    <div class="lh-hero-stat">
                        <h3><?php echo number_format($total_companies); ?>+</h3>
                        <p>Companies</p>
                    </div>
                    <div class="lh-hero-stat">
                        <h3><?php echo number_format($total_users); ?>+</h3>
                        <p>Job Seekers</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 d-none d-lg-block">
                <div class="lh-hero-visuals">
                    <div class="lh-float-card">
                        <div class="lh-fc-icon" style="background: #dbeafe; color: #2563eb;"><i class="fas fa-check-circle"></i></div>
                        <div class="lh-fc-text">
                            <h6>Skill Verified</h6>
                            <small>PHP Assessment Passed</small>
                        </div>
                    </div>
                    <div class="lh-float-card">
                        <div class="lh-fc-icon" style="background: #dcfce7; color: #16a34a;"><i class="fas fa-paper-plane"></i></div>
                        <div class="lh-fc-text">
                            <h6>Application Sent</h6>
                            <small>Senior Developer at TechCo</small>
                        </div>
                    </div>
                    <div class="lh-float-card">
                        <div class="lh-fc-icon" style="background: #fef3c7; color: #d97706;"><i class="fas fa-bell"></i></div>
                        <div class="lh-fc-text">
                            <h6>Interview Scheduled</h6>
                            <small>Tomorrow at 10:00 AM</small>
                        </div>
                    </div>
                    <div class="lh-float-card">
                        <div class="lh-fc-icon" style="background: #f3e8ff; color: #0ea5e9;"><i class="fas fa-robot"></i></div>
                        <div class="lh-fc-text">
                            <h6>AI Career Coach</h6>
                            <small>Personalized guidance ready</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ═══ TRUSTED BY ═══ -->
<div class="lh-trusted">
    <div class="container">
        <p>Trusted by professionals from leading companies</p>
        <div class="lh-trusted-logos">
            <i class="fab fa-google"></i>
            <i class="fab fa-microsoft"></i>
            <i class="fab fa-amazon"></i>
            <i class="fab fa-meta"></i>
            <i class="fab fa-apple"></i>
            <i class="fab fa-spotify"></i>
        </div>
    </div>
</div>

<!-- ═══ FEATURES ═══ -->
<section class="lh-features" id="features">
    <div class="container">
        <div class="lh-section-center">
            <span class="lh-section-badge"><i class="fas fa-star"></i> Features</span>
            <h2 class="lh-section-title">Everything You Need to Succeed</h2>
            <p class="lh-section-desc">From job search to career growth, NovaHire provides all the tools you need in one platform.</p>
        </div>
        <div class="row">
            <div class="col-lg-4 col-md-6 mb-4 reveal">
                <div class="lh-feature-card">
                    <div class="lh-feature-icon" style="background: #eef2ff; color: #1a56db;">
                        <i class="fas fa-search"></i>
                    </div>
                    <h4>Smart Job Search</h4>
                    <p>AI-powered job matching that understands your skills, experience, and career goals to find the perfect fit.</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 mb-4 reveal">
                <div class="lh-feature-card">
                    <div class="lh-feature-icon" style="background: #fef3c7; color: #d97706;">
                        <i class="fas fa-robot"></i>
                    </div>
                    <h4>AI Career Coach</h4>
                    <p>Get personalized career guidance, resume analysis, and interview preparation powered by artificial intelligence.</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 mb-4 reveal">
                <div class="lh-feature-card">
                    <div class="lh-feature-icon" style="background: #dcfce7; color: #16a34a;">
                        <i class="fas fa-certificate"></i>
                    </div>
                    <h4>Skill Certifications</h4>
                    <p>Earn verified skill certificates through assessments and grooming sessions to stand out from the crowd.</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 mb-4 reveal">
                <div class="lh-feature-card">
                    <div class="lh-feature-icon" style="background: #fce7f3; color: #db2777;">
                        <i class="fas fa-comments"></i>
                    </div>
                    <h4>Live Chat & Messaging</h4>
                    <p>Connect directly with recruiters and companies through real-time messaging and video interviews.</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 mb-4 reveal">
                <div class="lh-feature-card">
                    <div class="lh-feature-icon" style="background: #f3e8ff; color: #0ea5e9;">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <h4>Mentor Marketplace</h4>
                    <p>Book 1-on-1 sessions with industry mentors for career coaching, resume review, and mock interviews.</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 mb-4 reveal">
                <div class="lh-feature-card">
                    <div class="lh-feature-icon" style="background: #e0f2fe; color: #0284c7;">
                        <i class="fas fa-building"></i>
                    </div>
                    <h4>Employer Dashboard</h4>
                    <p>Post jobs, manage applicants, schedule interviews, and build your employer brand all in one place.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ═══ HOW IT WORKS ═══ -->
<section class="lh-how" id="how-it-works">
    <div class="container">
        <div class="lh-section-center">
            <span class="lh-section-badge"><i class="fas fa-lightbulb"></i> How It Works</span>
            <h2 class="lh-section-title">Your Journey to a New Career</h2>
            <p class="lh-section-desc">Four simple steps to land your dream job with NovaHire.</p>
        </div>
        <div class="lh-steps">
            <div class="lh-step reveal">
                <div class="lh-step-num">1</div>
                <h4>Create Your Profile</h4>
                <p>Sign up for free and build your professional profile with skills, experience, and preferences.</p>
            </div>
            <div class="lh-step reveal">
                <div class="lh-step-num">2</div>
                <h4>Complete Assessments</h4>
                <p>Take skill assessments and grooming sessions to earn certifications and improve your match score.</p>
            </div>
            <div class="lh-step reveal">
                <div class="lh-step-num">3</div>
                <h4>Apply & Connect</h4>
                <p>Apply to jobs, chat with recruiters, and schedule interviews directly through the platform.</p>
            </div>
            <div class="lh-step reveal">
                <div class="lh-step-num">4</div>
                <h4>Get Hired</h4>
                <p>Receive offers, negotiate terms, and start your new career journey with confidence.</p>
            </div>
        </div>
    </div>
</section>

<!-- ═══ STATS ═══ -->
<section class="lh-stats">
    <div class="container">
        <div class="row text-center">
            <div class="col-md-3 col-6 mb-4 reveal">
                <div class="lh-stat-item">
                    <div class="icon"><i class="fas fa-briefcase"></i></div>
                    <h2><?php echo number_format($total_jobs); ?>+</h2>
                    <p>Job Opportunities</p>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-4 reveal">
                <div class="lh-stat-item">
                    <div class="icon"><i class="fas fa-building"></i></div>
                    <h2><?php echo number_format($total_companies); ?>+</h2>
                    <p>Registered Companies</p>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-4 reveal">
                <div class="lh-stat-item">
                    <div class="icon"><i class="fas fa-users"></i></div>
                    <h2><?php echo number_format($total_users); ?>+</h2>
                    <p>Active Job Seekers</p>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-4 reveal">
                <div class="lh-stat-item">
                    <div class="icon"><i class="fas fa-file-alt"></i></div>
                    <h2><?php echo number_format($total_applications); ?>+</h2>
                    <p>Applications Sent</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ═══ TESTIMONIALS ═══ -->
<section class="lh-testimonials">
    <div class="container">
        <div class="lh-section-center">
            <span class="lh-section-badge"><i class="fas fa-heart"></i> Testimonials</span>
            <h2 class="lh-section-title">Loved by Job Seekers & Employers</h2>
            <p class="lh-section-desc">See what our users have to say about their experience with NovaHire.</p>
        </div>
        <div class="row">
            <div class="col-lg-4 col-md-6 mb-4 reveal">
                <div class="lh-testimonial-card">
                    <div class="stars">
                        <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                    </div>
                    <p class="quote">"NovaHire's AI career coach helped me identify skill gaps and prepare for interviews. I landed my dream job at a top tech company within 2 months!"</p>
                    <div class="lh-testimonial-author">
                        <div class="lh-ta-avatar" style="background: linear-gradient(135deg, #3b82f6, #06b6d4);">SK</div>
                        <div class="lh-ta-info">
                            <h6>Sarah Khan</h6>
                            <small>Software Engineer at TechCorp</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 mb-4 reveal">
                <div class="lh-testimonial-card">
                    <div class="stars">
                        <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                    </div>
                    <p class="quote">"As an employer, the recruitment tools are incredible. We reduced our hiring time by 60% using the applicant management and quiz features."</p>
                    <div class="lh-testimonial-author">
                        <div class="lh-ta-avatar" style="background: linear-gradient(135deg, #059669, #34d399);">AR</div>
                        <div class="lh-ta-info">
                            <h6>Ahmed Rahman</h6>
                            <small>HR Director at InnovateBD</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 mb-4 reveal">
                <div class="lh-testimonial-card">
                    <div class="stars">
                        <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star-half-alt"></i>
                    </div>
                    <p class="quote">"The mentor sessions were game-changing. My mentor helped me polish my resume and nail the mock interview. Highly recommended for fresh graduates!"</p>
                    <div class="lh-testimonial-author">
                        <div class="lh-ta-avatar" style="background: linear-gradient(135deg, #d97706, #f97316);">MP</div>
                        <div class="lh-ta-info">
                            <h6>Maya Patel</h6>
                            <small>Frontend Developer at StartupX</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ═══ CTA ═══ -->
<section class="lh-cta">
    <div class="container">
        <div class="lh-cta-card reveal">
            <h2>Ready to Start Your Career Journey?</h2>
            <p>Join thousands of job seekers and employers who are already succeeding with NovaHire.</p>
            <div class="lh-cta-btns">
                <a href="auth/login.php" class="lh-hero-btn-primary">
                    <i class="fas fa-rocket"></i> Get Started Free
                </a>
                <a href="auth/company_registration.php" class="lh-hero-btn-secondary">
                    <i class="fas fa-building"></i> Register as Employer
                </a>
            </div>
        </div>
    </div>
</section>

<!-- ═══ FOOTER ═══ -->
<footer class="lh-footer">
    <div class="container">
        <div class="row">
            <div class="col-lg-4 mb-4">
                <div class="lh-footer-brand">Nova<span>Hire</span></div>
                <p class="lh-footer-desc">AI-powered job portal connecting talented professionals with top companies. Your gateway to career success.</p>
                <div class="lh-footer-social">
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-linkedin-in"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 mb-4">
                <h5>For Job Seekers</h5>
                <ul class="lh-footer-links">
                    <li><a href="seeker/browse_jobs.php">Browse Jobs</a></li>
                    <li><a href="seeker/available_companies.php">Companies</a></li>
                    <li><a href="seeker/ai_hub.php">AI Career Tools</a></li>
                    <li><a href="seeker/grooming.php">Skill Grooming</a></li>
                </ul>
            </div>
            <div class="col-lg-2 col-md-4 mb-4">
                <h5>For Employers</h5>
                <ul class="lh-footer-links">
                    <li><a href="auth/company_registration.php">Register Company</a></li>
                    <li><a href="auth/login.php">Employer Login</a></li>
                    <li><a href="auth/login.php">Post a Job</a></li>
                    <li><a href="auth/login.php">Talent Search</a></li>
                </ul>
            </div>
            <div class="col-lg-2 col-md-4 mb-4">
                <h5>Resources</h5>
                <ul class="lh-footer-links">
                    <li><a href="blog/">Blog</a></li>
                    <li><a href="seeker/view_cv.php">Build Your CV</a></li>
                    <li><a href="#">Help Center</a></li>
                    <li><a href="#">API Docs</a></li>
                </ul>
            </div>
            <div class="col-lg-2 col-md-4 mb-4">
                <h5>Company</h5>
                <ul class="lh-footer-links">
                    <li><a href="#">About Us</a></li>
                    <li><a href="#">Privacy Policy</a></li>
                    <li><a href="#">Terms of Service</a></li>
                    <li><a href="#">Contact Us</a></li>
                </ul>
            </div>
        </div>
        <div class="lh-footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> NovaHire. All rights reserved. Built with <i class="fas fa-heart" style="color: #dc2626;"></i> for your career success.</p>
        </div>
    </div>
</footer>

<script>
// Navbar scroll effect
window.addEventListener('scroll', function() {
    var nav = document.getElementById('mainNav');
    if (window.scrollY > 50) {
        nav.classList.add('scrolled');
    } else {
        nav.classList.remove('scrolled');
    }
});

// Mobile menu
function toggleMobileMenu() {
    document.getElementById('mobileMenu').classList.toggle('active');
}

// Scroll reveal animations
function revealOnScroll() {
    var reveals = document.querySelectorAll('.reveal');
    reveals.forEach(function(el) {
        var windowHeight = window.innerHeight;
        var elementTop = el.getBoundingClientRect().top;
        var revealPoint = 120;
        if (elementTop < windowHeight - revealPoint) {
            el.classList.add('visible');
        }
    });
}
window.addEventListener('scroll', revealOnScroll);
window.addEventListener('load', revealOnScroll);

// Counter animation
function animateCounters() {
    var counters = document.querySelectorAll('.lh-stat-item h2');
    counters.forEach(function(counter) {
        var target = parseInt(counter.textContent.replace(/[^0-9]/g, ''));
        if (target === 0) return;
        var duration = 2000;
        var step = target / (duration / 16);
        var current = 0;
        var timer = setInterval(function() {
            current += step;
            if (current >= target) {
                counter.textContent = target.toLocaleString() + '+';
                clearInterval(timer);
            } else {
                counter.textContent = Math.floor(current).toLocaleString() + '+';
            }
        }, 16);
    });
}

// Trigger counter animation when stats section is in view
var statsObserver = new IntersectionObserver(function(entries) {
    entries.forEach(function(entry) {
        if (entry.isIntersecting) {
            animateCounters();
            statsObserver.unobserve(entry.target);
        }
    });
}, { threshold: 0.3 });

var statsSection = document.querySelector('.lh-stats');
if (statsSection) statsObserver.observe(statsSection);
</script>

</body>
</html>
