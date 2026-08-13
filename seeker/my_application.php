<?php
// Core setup: session, DB, BASE_URL, helpers
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../admin/dbcon.php';
require_once __DIR__ . '/../includes/header.php';

if (!isset($_SESSION['id'])) {
    header('location: ' . BASE_URL . '/auth/login.php');
}

$user_id = $_SESSION['id'];
$user_email = $_SESSION['email'];

// Hero stats
$apps_stats = ['total' => 0, 'pending' => 0, 'shortlisted' => 0, 'avg_quiz' => 0];
$stats_query = "SELECT COUNT(*) AS total,
                COALESCE(SUM(application_status = 'pending'), 0) AS pending,
                COALESCE(SUM(application_status = 'shortlisted'), 0) AS shortlisted,
                ROUND(AVG(quiz_score)) AS avg_quiz
                FROM job_applications WHERE user_id = '$user_id'";
$stats_res = mysqli_query($con, $stats_query);
if ($stats_res && $sr = mysqli_fetch_assoc($stats_res)) {
    $apps_stats['total'] = (int) $sr['total'];
    $apps_stats['pending'] = (int) $sr['pending'];
    $apps_stats['shortlisted'] = (int) $sr['shortlisted'];
    $apps_stats['avg_quiz'] = (int) $sr['avg_quiz'];
}

// Get legacy job application
$selectquery = " select * from jobregistration where email='$user_email' "; 
$query = mysqli_query($con, $selectquery);
$result = mysqli_fetch_assoc($query);
$has_application = mysqli_num_rows($query) > 0;

// Get company job applications
$company_apps_query = "SELECT ja.*, cj.job_title, cj.location, cj.employment_type, cj.job_category,
                       c.company_name, c.industry, ja.applied_date, ja.quiz_status, ja.quiz_score, ja.application_status
                       FROM job_applications ja
                       JOIN company_jobs cj ON ja.job_id = cj.id
                       JOIN companies c ON cj.company_id = c.id
                       WHERE ja.user_id = '$user_id'
                       ORDER BY ja.applied_date DESC";
$company_apps_result = mysqli_query($con, $company_apps_query);
$has_company_apps = mysqli_num_rows($company_apps_result) > 0;

// Check grooming status for each company job application
$grooming_status = [];
if ($has_company_apps) {
    $temp_result = mysqli_query($con, $company_apps_query);
    while ($app = mysqli_fetch_assoc($temp_result)) {
        $job_category = $app['job_category'];
        $status_query = "SELECT * FROM user_quiz_status WHERE user_id = '$user_id' AND category = '$job_category'";
        $status_res = mysqli_query($con, $status_query);
        
        if (mysqli_num_rows($status_res) > 0) {
            $status_row = mysqli_fetch_assoc($status_res);
            $grooming_status[$app['id']] = [
                'needs_grooming' => ($status_row['status'] == 'failed' && $status_row['grooming_completed'] == 0),
                'grooming_completed' => $status_row['grooming_completed'],
                'quiz_status' => $status_row['status'],
                'category' => $job_category,
                'job_id' => $app['job_id']
            ];
        } else {
            $grooming_status[$app['id']] = [
                'needs_grooming' => false,
                'grooming_completed' => false,
                'quiz_status' => 'not_attempted',
                'category' => $job_category,
                'job_id' => $app['job_id']
            ];
        }
    }
}

if (isset($_POST['btnUpdate'])) {
    $id = $_POST['id'];
    $name = mysqli_real_escape_string($con, $_POST['name']);
    $phone = mysqli_real_escape_string($con, $_POST['phone']);
    $degree = mysqli_real_escape_string($con, $_POST['degree']);
    $refer = mysqli_real_escape_string($con, $_POST['refer']);
    $plang = mysqli_real_escape_string($con, $_POST['plang']);

    $update_clause = "name='$name', phone='$phone', degree='$degree', refer='$refer', planguage='$plang'";

    if (isset($_FILES['pdf_file']['name']) && $_FILES['pdf_file']['name'] != '') {
        $file_name = $_FILES['pdf_file']['name'];
        $file_tmp = $_FILES['pdf_file']['tmp_name'];
        move_uploaded_file($file_tmp,"./files/".$file_name);
        $update_clause .= ", cv_doc='$file_name'";
    }

    $updatequery = " update jobregistration set $update_clause where id='$id' ";
    $uquery = mysqli_query($con, $updatequery);

    if ($uquery){
        echo '<script>alert("Application Updated Successfully"); window.location.href="my_application.php";</script>';
    } else {
        echo '<script>alert("Update Failed");</script>';
    }
}

$ma_status_style = [
    'pending'     => ['st-pending', 'Pending'],
    'reviewed'    => ['st-review', 'Reviewed'],
    'shortlisted' => ['st-short', 'Shortlisted'],
    'accepted'    => ['st-short', 'Accepted'],
    'hired'       => ['st-hired', 'Hired'],
    'rejected'    => ['st-rej', 'Rejected'],
];
$ma_quiz_style = [
    'passed'   => 'q-passed',
    'failed'   => 'q-failed',
    'not_taken' => 'q-none',
];
?>
<style>
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=Inter:wght@400;500;600;700;800&display=swap');

    :root {
        --ma-grad: linear-gradient(135deg, #2563eb 0%, #3b82f6 45%, #38bdf8 100%);
        --ma-grad-soft: linear-gradient(135deg, rgba(37,99,235,.12), rgba(56,189,248,.12));
    }
    body { font-family: 'Inter', sans-serif; }
    .ma-wrap { background: var(--bg); min-height: 70vh; }

    /* ═══ Hero ═══ */
    .ma-hero {
        position: relative;
        background: var(--ma-grad);
        margin-top: -16px;
        padding: 62px 0 160px;
        overflow: hidden;
        border-radius: 0 0 38px 38px;
    }
    .ma-hero::before, .ma-hero::after {
        content: ''; position: absolute; border-radius: 50%; pointer-events: none;
    }
    .ma-hero::before { top: -140px; right: -90px; width: 420px; height: 420px; background: radial-gradient(circle, rgba(255,255,255,.18), transparent 70%); }
    .ma-hero::after { bottom: -180px; left: -70px; width: 380px; height: 380px; background: radial-gradient(circle, rgba(255,255,255,.12), transparent 70%); }
    .ma-hero-inner { position: relative; z-index: 2; }

    .ma-breadcrumb {
        display: inline-flex; align-items: center; gap: 8px;
        background: rgba(255,255,255,.14); border: 1px solid rgba(255,255,255,.22);
        color: #fff; font-size: .76rem; font-weight: 700; letter-spacing: .04em;
        padding: 7px 15px; border-radius: 999px; margin-bottom: 20px;
    }
    .ma-breadcrumb i { font-size: .7rem; }

    .ma-hero h1 {
        font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; color: #fff;
        font-size: clamp(1.8rem, 4.2vw, 2.7rem); line-height: 1.15;
        margin: 0 0 12px; letter-spacing: -0.02em;
    }
    .ma-hero h1 span {
        background: linear-gradient(90deg, #fde68a, #fbbf24);
        -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent;
    }
    .ma-hero p.lead { color: rgba(255,255,255,.85); font-size: 1rem; font-weight: 500; max-width: 620px; margin: 0; }

    .ma-stats { display: flex; flex-wrap: wrap; gap: 16px; margin-top: 28px; }
    .ma-stat {
        display: flex; align-items: center; gap: 12px;
        background: rgba(255,255,255,.13); border: 1px solid rgba(255,255,255,.2);
        backdrop-filter: blur(8px); border-radius: 16px; padding: 11px 18px;
    }
    .ma-stat .num { font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; font-size: 1.3rem; color: #fff; line-height: 1; }
    .ma-stat .lbl { font-size: .7rem; font-weight: 600; color: rgba(255,255,255,.78); }
    .ma-stat i { font-size: 1.1rem; color: #fde68a; }

    /* ═══ Floating filter bar ═══ */
    .ma-filters {
        position: relative; z-index: 5;
        max-width: 1080px; margin: -96px auto 0; padding: 20px 24px;
        background: var(--bg-card); border: 1px solid var(--border-light);
        border-radius: 20px; box-shadow: 0 24px 50px -22px rgba(37,99,235,.4);
    }
    .ma-filters form { display: flex; gap: 12px; flex-wrap: wrap; align-items: center; }
    .ma-input, .ma-select {
        border: 1.5px solid var(--border-light); border-radius: 12px;
        background: var(--bg-hover); color: var(--text);
        font-family: 'Inter', sans-serif; font-size: .88rem; font-weight: 600;
        padding: 11px 15px; transition: all .2s;
    }
    .ma-input { flex: 1; min-width: 220px; }
    .ma-select { min-width: 160px; }
    .ma-input:focus, .ma-select:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 4px rgba(37,99,235,.12); background: var(--bg-card); }
    .ma-search-btn {
        display: inline-flex; align-items: center; gap: 8px;
        font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 700; font-size: .88rem;
        color: #fff; background: var(--ma-grad); background-size: 150% 150%;
        border: 0; border-radius: 12px; padding: 12px 26px; cursor: pointer;
        box-shadow: 0 10px 22px -10px rgba(56,189,248,.6);
        transition: transform .25s, box-shadow .3s, background-position .4s;
    }
    .ma-search-btn:hover { transform: translateY(-2px); background-position: 100% 50%; }
    .ma-reset-btn {
        display: inline-flex; align-items: center; gap: 8px;
        font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 700; font-size: .88rem;
        color: var(--text-muted); background: var(--bg-hover);
        border: 1.5px solid var(--border-light); border-radius: 12px; padding: 12px 22px;
        text-decoration: none; transition: all .2s;
    }
    .ma-reset-btn:hover { color: var(--primary); border-color: var(--primary); text-decoration: none; }

    /* ═══ Body ═══ */
    .ma-body { max-width: 1080px; padding-top: 34px; padding-bottom: 60px; }
    .ma-sec-head { display: flex; align-items: center; gap: 14px; margin-bottom: 22px; flex-wrap: wrap; }
    .ma-sec-head .ic {
        width: 46px; height: 46px; border-radius: 14px;
        display: flex; align-items: center; justify-content: center;
        color: var(--primary); font-size: 1.05rem; background: var(--ma-grad-soft);
        border: 1px solid rgba(59,130,246,.22);
    }
    .ma-sec-head h2 { font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; font-size: 1.3rem; color: var(--text); margin: 0; letter-spacing: -.01em; }
    .ma-sec-head p { color: var(--text-muted); font-size: .84rem; margin: 2px 0 0; }
    .ma-sec-count {
        margin-left: auto; display: inline-flex; align-items: center; gap: 7px;
        background: var(--ma-grad-soft); border: 1px solid rgba(59,130,246,.22);
        color: var(--primary); font-weight: 800; font-size: .82rem;
        padding: 7px 16px; border-radius: 999px;
    }

    /* application cards */
    .ma-card {
        position: relative;
        background: var(--bg-card); border: 1px solid var(--border-light);
        border-radius: 20px; padding: 24px;
        margin-bottom: 18px; box-shadow: var(--shadow-sm);
        transition: transform .3s, box-shadow .3s, border-color .3s, background .3s;
        overflow: hidden;
    }
    .ma-card::before {
        content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 4px;
        background: var(--ma-grad); opacity: 0; transition: opacity .3s;
    }
    .ma-card:hover { transform: translateY(-5px); box-shadow: 0 24px 48px -18px rgba(37,99,235,.35); border-color: rgba(56,189,248,.4); }
    .ma-card:hover::before { opacity: 1; }

    .ma-card-top { display: flex; align-items: flex-start; gap: 16px; }
    .ma-tile {
        flex: 0 0 56px; height: 56px; border-radius: 16px;
        display: flex; align-items: center; justify-content: center;
        color: #fff; font-size: 1.15rem; background: var(--ma-grad);
        box-shadow: 0 10px 20px -10px rgba(56,189,248,.6);
    }
    .ma-info { flex: 1; min-width: 0; }
    .ma-title-row { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
    .ma-title { font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; font-size: 1.2rem; color: var(--text); margin: 0; letter-spacing: -.01em; }
    .ma-company { display: inline-flex; align-items: center; gap: 7px; font-size: .82rem; font-weight: 700; color: var(--primary); margin-top: 5px; }
    .ma-company i { font-size: .85rem; }

    .ma-score {
        flex-shrink: 0; text-align: center;
        min-width: 74px; padding: 10px 14px;
        border-radius: 14px;
        background: var(--ma-grad-soft); border: 1px solid rgba(59,130,246,.25);
    }
    .ma-score .v { font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; font-size: 1.25rem; color: var(--primary); line-height: 1; }
    .ma-score .l { font-size: .58rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--text-muted); margin-top: 3px; display: block; }
    [data-theme="dark"] .ma-score .v { color: #93c5fd; }

    .ma-tags { display: flex; flex-wrap: wrap; gap: 8px; margin: 18px 0 16px; }
    .ma-tag {
        display: inline-flex; align-items: center; gap: 6px;
        background: var(--bg-hover); border: 1px solid var(--border-light);
        color: var(--text-muted); font-size: .78rem; font-weight: 700;
        padding: 6px 12px; border-radius: 10px;
    }
    .ma-tag i { color: var(--primary); font-size: .8rem; width: 14px; text-align: center; }
    .ma-tag.cat { color: var(--primary); background: var(--ma-grad-soft); border-color: rgba(59,130,246,.22); }

    .ma-status, .ma-qpill {
        display: inline-flex; align-items: center; gap: 6px;
        font-size: .72rem; font-weight: 800; padding: 6px 13px; border-radius: 999px;
    }
    .ma-status.st-pending { color: #b45309; background: rgba(245,158,11,.12); border: 1px solid rgba(245,158,11,.28); }
    .ma-status.st-review { color: #1d4ed8; background: rgba(59,130,246,.12); border: 1px solid rgba(59,130,246,.28); }
    .ma-status.st-short { color: #047857; background: rgba(16,185,129,.12); border: 1px solid rgba(16,185,129,.28); }
    .ma-status.st-hired { color: #065f46; background: rgba(16,185,129,.2); border: 1px solid rgba(16,185,129,.4); }
    .ma-status.st-rej { color: #b91c1c; background: rgba(239,68,68,.12); border: 1px solid rgba(239,68,68,.28); }
    .ma-qpill.q-passed { color: #047857; background: rgba(16,185,129,.1); border: 1px solid rgba(16,185,129,.22); }
    .ma-qpill.q-failed { color: #b91c1c; background: rgba(239,68,68,.1); border: 1px solid rgba(239,68,68,.22); }
    .ma-qpill.q-none { color: var(--text-muted); background: var(--bg-hover); border: 1px solid var(--border-light); }
    [data-theme="dark"] .ma-status.st-pending { color: #fbbf24; }
    [data-theme="dark"] .ma-status.st-review { color: #93c5fd; }
    [data-theme="dark"] .ma-status.st-short, [data-theme="dark"] .ma-status.st-hired { color: #34d399; }
    [data-theme="dark"] .ma-status.st-rej { color: #fca5a5; }
    [data-theme="dark"] .ma-qpill.q-passed { color: #34d399; }
    [data-theme="dark"] .ma-qpill.q-failed { color: #fca5a5; }

    .ma-foot { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
    .ma-link {
        display: inline-flex; align-items: center; gap: 8px;
        font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 700; font-size: .84rem;
        color: var(--primary); background: var(--ma-grad-soft);
        border: 1px solid rgba(59,130,246,.22); border-radius: 12px; padding: 10px 18px;
        text-decoration: none; transition: all .25s;
    }
    .ma-link:hover { color: #fff; background: var(--ma-grad); border-color: transparent; transform: translateY(-2px); text-decoration: none; }
    .ma-groom {
        display: inline-flex; align-items: center; gap: 8px;
        font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 700; font-size: .84rem;
        color: #fff; background: linear-gradient(135deg, #f6ad55, #ed8936); background-size: 150% 150%;
        border: 0; border-radius: 12px; padding: 10px 18px; text-decoration: none;
        box-shadow: 0 10px 22px -10px rgba(237,137,54,.6);
        transition: transform .25s, box-shadow .3s, background-position .4s;
    }
    .ma-groom:hover { transform: translateY(-2px); background-position: 100% 50%; text-decoration: none; color: #fff; }
    .ma-groom-done {
        display: inline-flex; align-items: center; gap: 8px;
        font-size: .78rem; font-weight: 800; color: #047857;
        background: rgba(16,185,129,.1); border: 1px solid rgba(16,185,129,.24);
        border-radius: 999px; padding: 8px 16px;
    }

    /* empty states */
    .ma-empty {
        text-align: center; background: var(--bg-card);
        border: 1px dashed var(--border); border-radius: 22px; padding: 64px 30px;
    }
    .ma-empty .ic {
        width: 88px; height: 88px; margin: 0 auto 22px;
        display: flex; align-items: center; justify-content: center;
        font-size: 2rem; color: var(--primary); background: var(--ma-grad-soft);
        border-radius: 26px;
    }
    .ma-empty h3 { font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; color: var(--text); }
    .ma-empty p { color: var(--text-muted); }
    .ma-empty .btn {
        display: inline-flex; align-items: center; gap: 8px;
        font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 700; font-size: .9rem;
        color: #fff; background: var(--ma-grad); border: 0; border-radius: 13px; padding: 13px 26px;
        box-shadow: 0 12px 24px -12px rgba(56,189,248,.6);
        transition: transform .25s, box-shadow .3s;
    }
    .ma-empty .btn:hover { transform: translateY(-2px); text-decoration: none; color: #fff; }
    .ma-empty .btn.ghost {
        color: var(--primary); background: var(--ma-grad-soft); border: 1.5px solid rgba(59,130,246,.25);
        box-shadow: none;
    }
    .ma-empty .btn.ghost:hover { border-color: var(--primary); }

    /* ═══ Legacy application ═══ */
    .ma-legacy {
        background: var(--bg-card); border: 1px solid var(--border-light);
        border-radius: 22px; overflow: hidden; box-shadow: var(--shadow-sm);
        margin-top: 34px;
    }
    .ma-legacy-head {
        position: relative; text-align: center; padding: 40px 30px 34px;
        background: var(--ma-grad); overflow: hidden;
    }
    .ma-legacy-head::before {
        content: ''; position: absolute; top: -80px; right: -40px; width: 260px; height: 260px;
        background: radial-gradient(circle, rgba(255,255,255,.16), transparent 70%); border-radius: 50%;
    }
    .ma-legacy-badge {
        position: relative; display: inline-flex; align-items: center; gap: 8px;
        background: rgba(255,255,255,.18); border: 1px solid rgba(255,255,255,.3);
        color: #fff; font-weight: 800; font-size: .78rem; letter-spacing: .05em;
        text-transform: uppercase; padding: 8px 18px; border-radius: 999px; margin-bottom: 16px;
    }
    .ma-legacy-head h2 { position: relative; font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; color: #fff; font-size: 1.5rem; margin: 0 0 6px; }
    .ma-legacy-head p { position: relative; color: rgba(255,255,255,.85); font-size: .86rem; margin: 0; }

    .ma-legacy-grid {
        padding: 30px 32px 26px;
        display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 20px;
    }
    .ma-detail label {
        display: flex; align-items: center; gap: 7px;
        font-size: .72rem; font-weight: 800; color: var(--text-muted);
        text-transform: uppercase; letter-spacing: .05em; margin-bottom: 7px;
    }
    .ma-detail label i { color: var(--primary); font-size: .8rem; }
    .ma-detail .value {
        background: var(--bg-hover); border: 1px solid var(--border-light);
        border-radius: 12px; padding: 12px 15px;
        font-size: .92rem; color: var(--text); font-weight: 700; min-height: 44px;
        display: flex; align-items: center;
    }
    .ma-detail .value a { color: var(--primary); font-weight: 700; }

    .ma-legacy-bar {
        display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap;
        padding: 18px 32px; background: var(--bg-hover); border-top: 1px solid var(--border-light);
    }
    .ma-bar-btn {
        display: inline-flex; align-items: center; gap: 8px;
        font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 700; font-size: .86rem;
        border-radius: 12px; padding: 11px 22px; text-decoration: none;
        transition: all .25s; cursor: pointer; border: 0;
    }
    .ma-bar-btn.ghost { color: var(--text-muted); background: var(--bg-card); border: 1.5px solid var(--border-light); }
    .ma-bar-btn.ghost:hover { color: var(--primary); border-color: var(--primary); }
    .ma-bar-btn.grad { color: #fff; background: var(--ma-grad); box-shadow: 0 10px 22px -10px rgba(56,189,248,.6); }
    .ma-bar-btn.grad:hover { transform: translateY(-2px); }

    /* edit panel */
    .ma-edit {
        background: var(--bg-card); border-top: 1px solid var(--border-light);
        padding: 0; overflow: hidden; max-height: 0;
        transition: max-height .5s cubic-bezier(.4,0,.2,1);
    }
    .ma-edit.open { max-height: 2000px; }
    .ma-edit-inner { padding: 30px 32px 34px; }
    .ma-edit-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
    .ma-edit-head h4 { font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; color: var(--text); margin: 0; }
    .ma-edit-close {
        width: 36px; height: 36px; border-radius: 11px; border: 1.5px solid var(--border-light);
        background: var(--bg-hover); color: var(--text-muted); cursor: pointer; transition: all .2s;
    }
    .ma-edit-close:hover { color: #ef4444; border-color: #ef4444; transform: rotate(90deg); }
    .ma-edit label { font-size: .74rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: .04em; margin-bottom: 7px; display: block; }
    .ma-edit input[type="text"] {
        width: 100%; border: 1.5px solid var(--border-light); border-radius: 12px;
        background: var(--bg-hover); color: var(--text); font-family: 'Inter', sans-serif;
        font-size: .9rem; font-weight: 600; padding: 12px 15px; transition: all .2s;
    }
    .ma-edit input[type="text"]:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 4px rgba(37,99,235,.12); background: var(--bg-card); }
    .ma-upload {
        background: var(--bg-hover); border: 1.5px dashed var(--border);
        border-radius: 14px; padding: 20px;
    }
    .ma-upload .custom-file-label { background: transparent; border: 0; }
    .ma-save {
        display: inline-flex; align-items: center; gap: 8px;
        font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 700; font-size: .9rem;
        color: #fff; background: var(--ma-grad); border: 0; border-radius: 12px; padding: 12px 30px;
        box-shadow: 0 10px 22px -10px rgba(56,189,248,.6); transition: all .25s;
    }
    .ma-save:hover { transform: translateY(-2px); }

    .ma-footer { text-align: center; padding: 26px 0 46px; color: var(--text-light); font-size: .82rem; font-weight: 600; }

    .ma-fade { opacity: 0; transform: translateY(16px); animation: maUp .5s ease forwards; }
    @keyframes maUp { to { opacity: 1; transform: none; } }

    @media (max-width: 860px) {
        .ma-hero { padding: 48px 0 140px; border-radius: 0 0 28px 28px; }
        .ma-filters { margin-top: -88px; padding: 18px; }
        .ma-filters form > * { width: 100%; }
        .ma-reset-btn { justify-content: center; }
        .ma-card-top { flex-wrap: wrap; }
        .ma-score { margin-left: auto; }
        .ma-legacy-grid { padding: 24px 20px 20px; }
        .ma-legacy-bar { padding: 16px 20px; }
        .ma-edit-inner { padding: 24px 20px 28px; }
    }
    @media (max-width: 480px) {
        .ma-card { padding: 18px; }
        .ma-search-btn, .ma-reset-btn { width: 100%; justify-content: center; }
        .ma-sec-count { margin-left: 0; }
        .ma-bar-btn { width: 100%; justify-content: center; }
    }
</style>

<div class="ma-wrap">

    <!-- Hero -->
    <div class="ma-hero">
        <div class="container ma-hero-inner">
            <div class="ma-breadcrumb"><i class="fas fa-home"></i> Dashboard <i class="fas fa-chevron-right"></i> My Applications</div>
            <h1>My <span>Applications</span></h1>
            <p class="lead">Track every application you've submitted, review your quiz scores, and follow up on company updates.</p>
            <div class="ma-stats">
                <div class="ma-stat"><i class="fas fa-paper-plane"></i><div><div class="num"><?php echo $apps_stats['total']; ?></div><div class="lbl">Total</div></div></div>
                <div class="ma-stat"><i class="fas fa-hourglass-half"></i><div><div class="num"><?php echo $apps_stats['pending']; ?></div><div class="lbl">Pending</div></div></div>
                <div class="ma-stat"><i class="fas fa-star"></i><div><div class="num"><?php echo $apps_stats['shortlisted']; ?></div><div class="lbl">Shortlisted</div></div></div>
                <div class="ma-stat"><i class="fas fa-chart-line"></i><div><div class="num"><?php echo $apps_stats['avg_quiz']; ?>%</div><div class="lbl">Avg Quiz</div></div></div>
            </div>
        </div>
    </div>

    <?php if ($has_company_apps): ?>
        <!-- Filter bar -->
        <div class="container">
            <div class="ma-filters ma-fade" style="animation-delay:.06s">
                <form method="GET" action="my_application.php">
                    <input type="text" name="search_company" class="ma-input"
                           placeholder="Search by company or job title..."
                           value="<?php echo isset($_GET['search_company']) ? htmlspecialchars($_GET['search_company']) : ''; ?>">
                    <select name="filter_status" class="ma-select">
                        <option value="">All Status</option>
                        <option value="pending" <?php echo (isset($_GET['filter_status']) && $_GET['filter_status'] === 'pending') ? 'selected' : ''; ?>>Pending</option>
                        <option value="reviewed" <?php echo (isset($_GET['filter_status']) && $_GET['filter_status'] === 'reviewed') ? 'selected' : ''; ?>>Reviewed</option>
                        <option value="shortlisted" <?php echo (isset($_GET['filter_status']) && $_GET['filter_status'] === 'shortlisted') ? 'selected' : ''; ?>>Shortlisted</option>
                        <option value="rejected" <?php echo (isset($_GET['filter_status']) && $_GET['filter_status'] === 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                    <button type="submit" class="ma-search-btn"><i class="fas fa-search"></i>Search</button>
                    <a href="my_application.php" class="ma-reset-btn"><i class="fas fa-redo"></i>Reset</a>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <div class="container ma-body">
        <!-- Company Job Applications Section -->
        <?php if ($has_company_apps): ?>

            <?php
                // Apply search and filter
                $filtered_apps = [];
                mysqli_data_seek($company_apps_result, 0);

                $search_query = isset($_GET['search_company']) ? strtolower($_GET['search_company']) : '';
                $status_filter = isset($_GET['filter_status']) ? $_GET['filter_status'] : '';

                while ($app = mysqli_fetch_assoc($company_apps_result)) {
                    $matches_search = true;
                    $matches_status = true;

                    if (!empty($search_query)) {
                        $job_text = strtolower($app['job_title'] . ' ' . $app['company_name'] . ' ' . $app['industry']);
                        $matches_search = strpos($job_text, $search_query) !== false;
                    }

                    if (!empty($status_filter)) {
                        $matches_status = $app['application_status'] === $status_filter;
                    }

                    if ($matches_search && $matches_status) {
                        $filtered_apps[] = $app;
                    }
                }
            ?>

            <div class="ma-sec-head ma-fade" style="animation-delay:.12s">
                <div class="ic"><i class="fas fa-briefcase"></i></div>
                <div>
                    <h2>Company Job Applications</h2>
                    <p>Your submissions to jobs posted by companies.</p>
                </div>
                <span class="ma-sec-count"><i class="fas fa-clipboard-list"></i> <?php echo count($filtered_apps); ?> shown</span>
            </div>

            <?php if (count($filtered_apps) > 0): ?>
                <?php $ma_idx = 0; foreach ($filtered_apps as $app): $ma_idx++; ?>
                    <?php $ms = $ma_status_style[$app['application_status']] ?? ['st-pending', ucfirst($app['application_status'])]; ?>
                    <?php $mq = $ma_quiz_style[$app['quiz_status']] ?? 'q-none'; ?>
                    <div class="ma-card ma-fade" style="animation-delay:<?php echo min($ma_idx * 0.06, 0.36); ?>s">
                        <div class="ma-card-top">
                            <div class="ma-tile"><i class="fas fa-briefcase"></i></div>
                            <div class="ma-info">
                                <div class="ma-title-row">
                                    <h3 class="ma-title"><?php echo htmlspecialchars($app['job_title']); ?></h3>
                                    <span class="ma-status <?php echo $ms[0]; ?>"><i class="fas fa-clipboard-check"></i><?php echo $ms[1]; ?></span>
                                    <span class="ma-qpill <?php echo $mq; ?>"><i class="fas fa-question-circle"></i>Quiz: <?php echo ucfirst(str_replace('_', ' ', $app['quiz_status'] ?: 'Not taken')); ?></span>
                                </div>
                                <div class="ma-company"><i class="fas fa-building"></i><?php echo htmlspecialchars($app['company_name']); ?> &middot; <?php echo htmlspecialchars($app['industry']); ?></div>
                            </div>
                            <?php if ($app['quiz_score'] !== null): ?>
                                <div class="ma-score">
                                    <span class="v"><?php echo round($app['quiz_score']); ?>%</span>
                                    <span class="l">Quiz</span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="ma-tags">
                            <span class="ma-tag cat"><i class="fas fa-tag"></i><?php echo htmlspecialchars($app['job_category']); ?></span>
                            <span class="ma-tag"><i class="fas fa-map-marker-alt"></i><?php echo htmlspecialchars($app['location']); ?></span>
                            <span class="ma-tag"><i class="fas fa-briefcase"></i><?php echo htmlspecialchars($app['employment_type']); ?></span>
                            <span class="ma-tag"><i class="fas fa-calendar-alt"></i>Applied: <?php echo date('M d, Y', strtotime($app['applied_date'])); ?></span>
                        </div>

                        <div class="ma-foot">
                            <a href="job_details.php?id=<?php echo $app['job_id']; ?>" class="ma-link"><i class="fas fa-eye"></i>View Job Details</a>
                            <?php
                                $groom_info = isset($grooming_status[$app['id']]) ? $grooming_status[$app['id']] : null;
                                if ($groom_info && $groom_info['needs_grooming']):
                            ?>
                                <a href="grooming.php?category=<?php echo urlencode($groom_info['category']); ?>&job_id=<?php echo $groom_info['job_id']; ?>" class="ma-groom" title="Complete grooming session to improve your score">
                                    <i class="fas fa-graduation-cap"></i>Complete Grooming
                                </a>
                            <?php elseif ($groom_info && $groom_info['grooming_completed']): ?>
                                <span class="ma-groom-done"><i class="fas fa-check-circle"></i>Grooming Completed</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="ma-empty ma-fade">
                    <div class="ic"><i class="fas fa-search"></i></div>
                    <h3>No Applications Match Your Search</h3>
                    <p>Try adjusting your search or filters.</p>
                    <a href="my_application.php" class="btn"><i class="fas fa-redo"></i>Clear Filters</a>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="ma-empty ma-fade">
                <div class="ic"><i class="fas fa-paper-plane"></i></div>
                <h3>No Company Job Applications Yet</h3>
                <p>Start applying for jobs posted by companies.</p>
                <div style="display:flex; gap:12px; justify-content:center; flex-wrap:wrap;">
                    <a href="browse_jobs.php" class="btn"><i class="fas fa-search"></i>Browse Available Jobs</a>
                    <a href="seeker_dashboard.php" class="btn ghost"><i class="fas fa-th-large"></i>Go to Dashboard</a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Legacy Application Section -->
        <?php if ($has_application): ?>
            <div class="ma-legacy ma-fade" style="animation-delay:.2s">
                <div class="ma-legacy-head">
                    <span class="ma-legacy-badge"><i class="fas fa-satellite-dish"></i> Active Details</span>
                    <h2>Developer Application</h2>
                    <p>Submitted on <?php echo date("F j, Y"); ?></p>
                </div>

                <div class="ma-legacy-grid">
                    <div class="ma-detail">
                        <label><i class="fas fa-id-badge"></i> Full Name</label>
                        <div class="value"><?php echo htmlspecialchars($result['name']); ?></div>
                    </div>
                    <div class="ma-detail">
                        <label><i class="fas fa-phone-alt"></i> Phone</label>
                        <div class="value"><?php echo htmlspecialchars($result['phone']); ?></div>
                    </div>
                    <div class="ma-detail">
                        <label><i class="fas fa-graduation-cap"></i> Degree</label>
                        <div class="value"><?php echo htmlspecialchars($result['degree']); ?></div>
                    </div>
                    <div class="ma-detail">
                        <label><i class="fas fa-code"></i> Programming Language</label>
                        <div class="value"><?php echo htmlspecialchars($result['planguage']); ?></div>
                    </div>
                    <div class="ma-detail">
                        <label><i class="fas fa-user-plus"></i> Referral</label>
                        <div class="value"><?php echo htmlspecialchars($result['refer']); ?></div>
                    </div>
                    <div class="ma-detail">
                        <label><i class="fas fa-file-pdf"></i> CV Document</label>
                        <div class="value">
                            <a href="files/<?php echo $result['cv_doc']; ?>" target="_blank"><i class="fas fa-eye mr-1"></i>View File</a>
                        </div>
                    </div>
                </div>

                <div class="ma-legacy-bar">
                    <button class="ma-bar-btn ghost" onclick="toggleEdit()"><i class="fas fa-cog mr-1"></i>Settings</button>
                    <button class="ma-bar-btn grad" onclick="toggleEdit()"><i class="fas fa-pen mr-1"></i>Edit Details</button>
                </div>

                <!-- Hidden Edit Form -->
                <div class="ma-edit" id="editForm">
                    <div class="ma-edit-inner">
                        <div class="ma-edit-head">
                            <h4><i class="fas fa-user-cog mr-2" style="color: var(--primary);"></i>Update Information</h4>
                            <button type="button" class="ma-edit-close" onclick="toggleEdit()"><i class="fas fa-times"></i></button>
                        </div>

                        <form method="post" action="" enctype="multipart/form-data">
                            <input type="hidden" name="id" value="<?php echo $result['id']; ?>" />

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label>Display Name</label>
                                    <input type="text" name="name" value="<?php echo htmlspecialchars($result['name']); ?>" />
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label>Phone Number</label>
                                    <input type="text" name="phone" value="<?php echo htmlspecialchars($result['phone']); ?>" />
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label>Qualification</label>
                                    <input type="text" name="degree" value="<?php echo htmlspecialchars($result['degree']); ?>" />
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label>Tech Stack</label>
                                    <input type="text" name="plang" value="<?php echo htmlspecialchars($result['planguage']); ?>" />
                                </div>
                            </div>

                            <div class="mb-4">
                                <label>Referral</label>
                                <input type="text" name="refer" value="<?php echo htmlspecialchars($result['refer']); ?>" />
                            </div>

                            <div class="ma-upload mb-4">
                                <label>Update CV (Optional)</label>
                                <div class="custom-file">
                                    <input type="file" name="pdf_file" class="custom-file-input" id="cvFile" accept=".pdf">
                                    <label class="custom-file-label border-0" for="cvFile" style="background: transparent;">Choose new PDF file...</label>
                                </div>
                                <small class="text-muted mt-2 d-block">Leave empty to keep current CV.</small>
                            </div>

                            <div class="text-right">
                                <button type="button" class="btn btn-link text-muted mr-3" onclick="toggleEdit()">Cancel</button>
                                <button type="submit" name="btnUpdate" class="ma-save"><i class="fas fa-check mr-1"></i>Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="ma-footer">
        <p class="mb-0">&copy; <?php echo date('Y'); ?> NovaHire. All rights reserved.</p>
    </div>
</div>

<script>
    function toggleEdit() {
        var x = document.getElementById("editForm");
        var isOpen = x.classList.contains("open");
        if (!isOpen) {
            x.classList.add("open");
            setTimeout(function() {
                x.scrollIntoView({behavior: "smooth", block: "nearest"});
            }, 150);
        } else {
            x.classList.remove("open");
        }
    }

    // Custom file input name display
    $(".custom-file-input").on("change", function() {
        var fileName = $(this).val().split("\\").pop();
        $(this).siblings(".custom-file-label").addClass("selected").html(fileName);
    });
</script>

</body>
</html>
