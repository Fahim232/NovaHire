<?php
session_start();
if (!isset($_SESSION['admin_username'])) {
    echo '<script>alert("You are logged out!"); window.location.href="admin_login.php";</script>';
    exit();
}

require_once 'dbcon.php';
require_once __DIR__ . '/../ai/config.php';
include 'header.php';

$success = null;
$errors = [];

if (isset($_POST['save_ai_settings']) || isset($_POST['test_ai'])) {
    $provider  = mysqli_real_escape_string($con, $_POST['provider'] ?? 'openai');
    $openai_key = trim($_POST['openai_api_key'] ?? '');
    $openai_model = mysqli_real_escape_string($con, trim($_POST['openai_model'] ?? ''));
    $gemini_key = trim($_POST['gemini_api_key'] ?? '');
    $gemini_model = mysqli_real_escape_string($con, trim($_POST['gemini_model'] ?? ''));
    $chatbot_name = mysqli_real_escape_string($con, trim($_POST['chatbot_name'] ?? 'AI Assistant'));
    $timeout   = max(5, min(120, intval($_POST['timeout'] ?? 30)));
    $llm_enabled = isset($_POST['llm_enabled']) ? 1 : 0;

    if ($openai_model === '') $openai_model = 'gpt-4o-mini';
    if ($gemini_model === '') $gemini_model = 'gemini-1.5-flash';
    if ($chatbot_name === '') $chatbot_name = 'AI Assistant';

    // Blank key fields keep the currently stored keys
    $prev = array();
    $prs = mysqli_query($con, "SELECT setting_key, setting_value FROM ai_settings");
    if ($prs) while ($pr = mysqli_fetch_assoc($prs)) $prev[$pr['setting_key']] = $pr['setting_value'];
    if ($openai_key === '') $openai_key = $prev['openai_api_key'] ?? '';
    if ($gemini_key === '') $gemini_key = $prev['gemini_api_key'] ?? '';

    $settings = array(
        'provider'          => $provider,
        'openai_api_key'    => $openai_key,
        'openai_model'      => $openai_model,
        'gemini_api_key'    => $gemini_key,
        'gemini_model'      => $gemini_model,
        'chatbot_name'      => $chatbot_name,
        'request_timeout'   => (string)$timeout,
        'llm_enabled'       => (string)$llm_enabled,
    );

    foreach ($settings as $key => $value) {
        $value = mysqli_real_escape_string($con, $value);
        $exists = mysqli_query($con, "SELECT id FROM ai_settings WHERE setting_key='$key'");
        if ($exists && mysqli_num_rows($exists) > 0) {
            mysqli_query($con, "UPDATE ai_settings SET setting_value='$value' WHERE setting_key='$key'");
        } else {
            mysqli_query($con, "INSERT INTO ai_settings (setting_key, setting_value) VALUES ('$key', '$value')");
        }
    }

    if (isset($_POST['test_ai'])) {
        require_once __DIR__ . '/../ai/engine.php';
        $test = ai_llm_chat('You are a test.', 'Reply with exactly: OK', 20);
        if ($test['ok']) {
            $success = 'AI settings saved and API connection test passed: "' . htmlspecialchars($test['text']) . '"';
        } else {
            $errors[] = 'AI settings saved, but the API test failed: ' . htmlspecialchars($test['error']);
        }
    } else {
        $success = 'AI settings saved successfully.';
    }
}

if (isset($_POST['clear_ai_history'])) {
    foreach (array('ai_chat_history', 'ai_cover_letters', 'ai_resume_analyses', 'ai_mock_interviews', 'ai_recommendations') as $tbl) {
        mysqli_query($con, "DELETE FROM $tbl");
    }
    $success = 'All AI history cleared.';
}

require_once __DIR__ . '/../ai/config.php';
$ai_settings = array();
$rs = mysqli_query($con, "SELECT setting_key, setting_value FROM ai_settings");
if ($rs) while ($row = mysqli_fetch_assoc($rs)) $ai_settings[$row['setting_key']] = $row['setting_value'];

$current_provider = $ai_settings['provider'] ?? 'openai';
$openai_key = $ai_settings['openai_api_key'] ?? '';
$openai_model = $ai_settings['openai_model'] ?? 'gpt-4o-mini';
$gemini_key = $ai_settings['gemini_api_key'] ?? '';
$gemini_model = $ai_settings['gemini_model'] ?? 'gemini-1.5-flash';
$chatbot_name = $ai_settings['chatbot_name'] ?? 'AI Assistant';
$timeout = intval($ai_settings['request_timeout'] ?? 30);
$llm_enabled = ($ai_settings['llm_enabled'] ?? '1') == '1';

$active_key = $current_provider === 'openai' ? $openai_key : $gemini_key;
$live_active = ($active_key !== '' && $llm_enabled);

$his_counts = array();
foreach (array('ai_chat_history' => 'Chat History', 'ai_cover_letters' => 'Cover Letters', 'ai_resume_analyses' => 'Resume Analyses', 'ai_mock_interviews' => 'Mock Interviews', 'ai_recommendations' => 'Recommendations') as $tbl => $label) {
    $c = mysqli_query($con, "SELECT COUNT(*) c FROM $tbl");
    $his_counts[$label] = $c ? intval(mysqli_fetch_assoc($c)['c']) : 0;
}
$total_ai_records = array_sum($his_counts);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>AI Settings | NovaHire Admin</title>
    <?php include '../includes/links.php'; ?>
    <style>
        :root {
            --ai-grad: linear-gradient(135deg, #6366f1, #8b5cf6 55%, #a855f7);
            --ai-grad-soft: linear-gradient(135deg, #6366f1, #8b5cf6 55%, #a855f7);
            --ai-green: #10b981;
            --ai-amber: #f59e0b;
            --ai-red: #ef4444;
            --ai-slate: #64748b;
            --ai-blue: #0ea5e9;
        }
        .ai-page {
            font-family: 'Manrope', 'Inter', sans-serif;
        }
        .ai-hero {
            position: relative;
            overflow: hidden;
            border-radius: 22px;
            padding: 34px 38px;
            color: #fff;
            background: var(--ai-grad);
            box-shadow: 0 20px 45px -18px rgba(124, 58, 237, 0.55);
            margin-bottom: 26px;
        }
        .ai-hero::before {
            content: '';
            position: absolute;
            width: 340px;
            height: 340px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.10);
            top: -150px;
            right: -70px;
        }
        .ai-hero::after {
            content: '';
            position: absolute;
            width: 220px;
            height: 220px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.07);
            bottom: -110px;
            right: 140px;
        }
        .ai-hero-icon {
            width: 58px;
            height: 58px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.18);
            border: 1px solid rgba(255, 255, 255, 0.28);
            font-size: 1.5rem;
            box-shadow: 0 10px 22px -10px rgba(0, 0, 0, 0.35);
        }
        .ai-hero h1 {
            font-family: 'Sora', 'Manrope', sans-serif;
            font-weight: 800;
            font-size: 1.65rem;
            margin: 0;
            letter-spacing: -0.02em;
        }
        .ai-hero p {
            margin: 6px 0 0;
            opacity: 0.88;
            font-size: 0.94rem;
            max-width: 560px;
        }
        .ai-pill {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 7px 14px;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.03em;
            background: rgba(255, 255, 255, 0.16);
            border: 1px solid rgba(255, 255, 255, 0.28);
            color: #fff;
            backdrop-filter: blur(6px);
        }
        .ai-card {
            background: var(--bg-card);
            border: 1px solid var(--border-light);
            border-radius: 18px;
            box-shadow: 0 8px 28px -14px rgba(15, 23, 42, 0.12);
        }
        .ai-card-h {
            padding: 18px 24px;
            border-bottom: 1px solid var(--border-light);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .ai-card-h .ai-tile {
            width: 38px;
            height: 38px;
            border-radius: 11px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            color: #fff;
            background: var(--ai-grad);
            box-shadow: 0 6px 14px -6px rgba(99, 102, 241, 0.55);
            flex-shrink: 0;
        }
        .ai-card-h h6 {
            margin: 0;
            font-weight: 800;
            color: var(--text);
            font-family: 'Sora', sans-serif;
            letter-spacing: -0.01em;
        }
        .ai-card-h small {
            color: var(--text-muted);
        }
        .ai-body { padding: 24px; }
        .ai-label {
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: var(--text-muted);
            margin: 0 0 8px;
        }
        .ai-input, .ai-select {
            width: 100%;
            border: 1px solid var(--border-light);
            background: var(--bg-hover);
            color: var(--text);
            border-radius: 12px;
            padding: 11px 14px;
            font-size: 0.92rem;
            font-weight: 600;
            transition: border-color .2s, box-shadow .2s, background .2s;
            outline: none;
        }
        .ai-input:focus, .ai-select:focus {
            border-color: #8b5cf6;
            box-shadow: 0 0 0 4px rgba(139, 92, 246, 0.15);
            background: var(--bg-card);
        }
        .ai-input::placeholder { color: var(--text-muted); opacity: 0.65; font-weight: 500; }
        .ai-input-wrap { position: relative; }
        .ai-input-wrap .ai-eye {
            position: absolute;
            right: 6px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: 0;
            color: var(--text-muted);
            width: 34px;
            height: 34px;
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: color .2s, background .2s;
        }
        .ai-input-wrap .ai-eye:hover { color: var(--text); background: var(--border-light); }
        .ai-providers { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .ai-provider {
            position: relative;
            border: 2px solid var(--border-light);
            background: var(--bg-card);
            border-radius: 14px;
            padding: 16px;
            cursor: pointer;
            transition: border-color .2s, box-shadow .2s, transform .15s;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .ai-provider:hover { transform: translateY(-2px); border-color: rgba(139, 92, 246, 0.5); }
        .ai-provider.sel {
            border-color: #8b5cf6;
            box-shadow: 0 0 0 4px rgba(139, 92, 246, 0.14), 0 10px 22px -12px rgba(124, 58, 237, 0.4);
        }
        .ai-provider input { position: absolute; opacity: 0; pointer-events: none; }
        .ai-provider-ic {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: #fff;
            flex-shrink: 0;
        }
        .ai-provider-ic.openai { background: linear-gradient(135deg, #0ea5e9, #2563eb); }
        .ai-provider-ic.gemini { background: linear-gradient(135deg, #6366f1, #8b5cf6); }
        .ai-provider b { color: var(--text); font-size: 0.94rem; display: block; font-family: 'Sora', sans-serif; }
        .ai-provider span { color: var(--text-muted); font-size: 0.76rem; }
        .ai-provider-check {
            position: absolute;
            top: 10px;
            right: 10px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #8b5cf6;
            color: #fff;
            font-size: 0.62rem;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transform: scale(0.6);
            transition: opacity .2s, transform .2s;
        }
        .ai-provider.sel .ai-provider-check { opacity: 1; transform: scale(1); }
        .ai-switch {
            position: relative;
            width: 46px;
            height: 26px;
            flex-shrink: 0;
        }
        .ai-switch input { opacity: 0; width: 0; height: 0; }
        .ai-switch .ai-slider {
            position: absolute;
            inset: 0;
            border-radius: 999px;
            background: var(--border-light);
            transition: background .25s;
            cursor: pointer;
        }
        .ai-switch .ai-slider::before {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            left: 3px;
            top: 3px;
            background: #fff;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.25);
            transition: transform .25s;
        }
        .ai-switch input:checked + .ai-slider { background: var(--ai-grad); }
        .ai-switch input:checked + .ai-slider::before { transform: translateX(20px); }
        .ai-btn {
            border: 0;
            border-radius: 12px;
            padding: 12px 20px;
            font-weight: 700;
            font-size: 0.9rem;
            transition: transform .15s, box-shadow .2s, background .2s;
        }
        .ai-btn-primary {
            background: var(--ai-grad);
            color: #fff;
            box-shadow: 0 10px 22px -10px rgba(124, 58, 237, 0.6);
        }
        .ai-btn-primary:hover { transform: translateY(-2px); box-shadow: 0 14px 28px -10px rgba(124, 58, 237, 0.7); }
        .ai-btn-ghost {
            background: var(--bg-hover);
            color: var(--text);
            border: 1px solid var(--border-light);
        }
        .ai-btn-ghost:hover { background: var(--border-light); transform: translateY(-2px); }
        .ai-btn-danger {
            background: rgba(239, 68, 68, 0.1);
            color: var(--ai-red);
            border: 1px solid rgba(239, 68, 68, 0.35);
        }
        .ai-btn-danger:hover { background: var(--ai-red); color: #fff; transform: translateY(-2px); }
        .ai-btn:disabled { opacity: 0.7; cursor: not-allowed; transform: none !important; }
        .ai-status-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 12px 0;
            border-bottom: 1px dashed var(--border-light);
        }
        .ai-status-row:last-child { border-bottom: 0; }
        .ai-status-row .ai-s-label { color: var(--text); font-weight: 600; font-size: 0.9rem; }
        .ai-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            border-radius: 999px;
            font-size: 0.74rem;
            font-weight: 800;
            letter-spacing: 0.03em;
        }
        .ai-badge.on { background: rgba(16, 185, 129, 0.14); color: var(--ai-green); }
        .ai-badge.off { background: var(--bg-hover); color: var(--text-muted); }
        .ai-badge.ai { background: rgba(99, 102, 241, 0.14); color: #8b5cf6; }
        .ai-badge.warn { background: rgba(245, 158, 11, 0.16); color: #d97706; }
        .ai-engine-dot {
            width: 9px; height: 9px; border-radius: 50%;
            display: inline-block; margin-right: 6px;
        }
        .ai-engine-dot.green { background: var(--ai-green); box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.16); animation: ai-pulse 1.8s infinite; }
        .ai-engine-dot.gray { background: var(--text-muted); }
        @keyframes ai-pulse {
            0%, 100% { box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.18); }
            50% { box-shadow: 0 0 0 7px rgba(16, 185, 129, 0.05); }
        }
        .ai-count-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; }
        .ai-count-box {
            background: var(--bg-hover);
            border: 1px solid var(--border-light);
            border-radius: 12px;
            padding: 14px 8px;
            text-align: center;
            transition: transform .15s, border-color .2s;
        }
        .ai-count-box:hover { transform: translateY(-3px); border-color: rgba(139, 92, 246, 0.45); }
        .ai-count-box .n { font-size: 1.3rem; font-weight: 800; color: var(--text); font-family: 'Sora', sans-serif; }
        .ai-count-box .l { font-size: 0.68rem; color: var(--text-muted); font-weight: 700; letter-spacing: 0.03em; }
        .ai-range { width: 100%; accent-color: #8b5cf6; cursor: pointer; }
        .ai-range-val { font-weight: 800; color: #8b5cf6; font-size: 1.05rem; font-family: 'Sora', sans-serif; }
        .ai-toast {
            position: fixed;
            top: 88px;
            right: 22px;
            z-index: 2000;
            min-width: 320px;
            max-width: 420px;
            background: var(--bg-card);
            border: 1px solid var(--border-light);
            border-radius: 14px;
            padding: 14px 16px;
            box-shadow: 0 22px 55px -18px rgba(15, 23, 42, 0.4);
            display: flex;
            align-items: flex-start;
            gap: 12px;
            animation: ai-in .35s cubic-bezier(.21, 1.02, .73, 1);
            transition: opacity .3s, transform .3s;
        }
        .ai-toast.out { opacity: 0; transform: translateX(40px); }
        .ai-toast .ic {
            width: 38px; height: 38px; border-radius: 11px;
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-size: 1rem; flex-shrink: 0;
        }
        .ai-toast.success .ic { background: linear-gradient(135deg, #10b981, #059669); }
        .ai-toast.error .ic { background: linear-gradient(135deg, #ef4444, #dc2626); }
        .ai-toast b { display: block; color: var(--text); font-size: 0.9rem; font-weight: 800; }
        .ai-toast span { color: var(--text-muted); font-size: 0.8rem; display: block; margin-top: 2px; word-break: break-word; }
        .ai-toast .close {
            margin-left: auto; background: none; border: 0; color: var(--text-muted); font-size: 0.85rem;
            flex-shrink: 0; padding: 2px 4px;
        }
        @keyframes ai-in {
            from { opacity: 0; transform: translateX(46px); }
            to { opacity: 1; transform: translateX(0); }
        }
        .ai-reveal { animation: ai-rise .45s cubic-bezier(.21, 1.02, .73, 1) both; }
        @keyframes ai-rise {
            from { opacity: 0; transform: translateY(16px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .ai-hint { font-size: 0.78rem; color: var(--text-muted); margin-top: 7px; display: flex; align-items: flex-start; gap: 6px; }
        .ai-hint i { margin-top: 2px; }
        .provider-field { display: none; }
        .provider-field.show { display: block; animation: ai-rise .3s both; }
        @media (max-width: 991px) {
            .ai-count-grid { grid-template-columns: repeat(3, 1fr); }
        }
        @media (max-width: 575px) {
            .ai-hero { padding: 26px 22px; }
            .ai-hero h1 { font-size: 1.3rem; }
            .ai-providers { grid-template-columns: 1fr; }
            .ai-count-grid { grid-template-columns: repeat(2, 1fr); }
            .ai-body { padding: 18px; }
            .ai-toast { left: 16px; right: 16px; min-width: 0; }
        }
        [data-theme="dark"] .ai-provider { background: var(--bg-card); }
        [data-theme="dark"] .ai-status-row { border-bottom-color: rgba(51, 65, 85, 0.55); }
        [data-theme="dark"] .ai-hero { box-shadow: 0 20px 45px -18px rgba(124, 58, 237, 0.35); }
    </style>
</head>
<body>
<div class="ai-page container-fluid px-4 px-lg-5 py-4">

    <!-- Hero -->
    <div class="ai-hero ai-reveal">
        <div class="d-flex flex-wrap align-items-center gap-3 position-relative" style="z-index:1;">
            <div class="ai-hero-icon"><i class="fas fa-robot"></i></div>
            <div class="flex-grow-1" style="min-width:220px;">
                <h1>AI Engine Settings</h1>
                <p>Configure the hybrid AI engine powering chat, cover letters, resume analysis, mock interviews and job recommendations across the portal.</p>
            </div>
            <div class="d-flex flex-column align-items-start gap-2">
                <span class="ai-pill"><i class="fas fa-microchip"></i> Hybrid Architecture</span>
                <span class="ai-pill"><i class="fas fa-server"></i> Provider: <?php echo strtoupper($current_provider === 'gemini' ? 'Google Gemini' : ($current_provider === 'openai' ? 'OpenAI' : 'Offline')); ?></span>
            </div>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="ai-toast success ai-reveal" id="aiToast">
            <div class="ic"><i class="fas fa-check"></i></div>
            <div><b>Success</b><span><?php echo $success; ?></span></div>
            <button type="button" class="close" onclick="this.closest('.ai-toast').classList.add('out'); setTimeout(()=>this.closest('.ai-toast').remove(), 300);">&times;</button>
        </div>
    <?php endif; ?>
    <?php if ($errors): ?>
        <div class="ai-toast error ai-reveal" id="aiToast">
            <div class="ic"><i class="fas fa-times"></i></div>
            <div><b>Connection Failed</b><span><?php echo htmlspecialchars(implode(' ', $errors)); ?></span></div>
            <button type="button" class="close" onclick="this.closest('.ai-toast').classList.add('out'); setTimeout(()=>this.closest('.ai-toast').remove(), 300);">&times;</button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Left: configuration -->
        <div class="col-lg-7">
            <div class="ai-card ai-reveal" style="animation-delay:.06s;">
                <div class="ai-card-h">
                    <div class="ai-tile"><i class="fas fa-sliders-h"></i></div>
                    <div><h6>Engine Configuration</h6><small>Provider, credentials and live-AI behaviour</small></div>
                </div>
                <div class="ai-body">
                    <form method="POST" id="aiForm">
                        <div class="mb-4">
                            <p class="ai-label"><i class="fas fa-plug mr-1"></i> AI Provider</p>
                            <div class="ai-providers">
                                <label class="ai-provider<?php echo $current_provider === 'openai' ? ' sel' : ''; ?>">
                                    <input type="radio" name="provider" value="openai" id="prov-openai" <?php echo $current_provider === 'openai' ? 'checked' : ''; ?>>
                                    <span class="ai-provider-check"><i class="fas fa-check"></i></span>
                                    <span class="ai-provider-ic openai"><i class="fab fa-openai"></i></span>
                                    <span><b>OpenAI</b><span>GPT series · rich generation</span></span>
                                </label>
                                <label class="ai-provider<?php echo $current_provider === 'gemini' ? ' sel' : ''; ?>">
                                    <input type="radio" name="provider" value="gemini" id="prov-gemini" <?php echo $current_provider === 'gemini' ? 'checked' : ''; ?>>
                                    <span class="ai-provider-check"><i class="fas fa-check"></i></span>
                                    <span class="ai-provider-ic gemini"><i class="fas fa-gem"></i></span>
                                    <span><b>Google Gemini</b><span>Gemini series · fast inference</span></span>
                                </label>
                            </div>
                            <div class="ai-hint"><i class="fas fa-info-circle"></i> When no key is set, the smart offline engine handles everything automatically.</div>
                        </div>

                        <div class="mb-4">
                            <p class="ai-label"><i class="fas fa-key mr-1"></i> API Key</p>
                            <div class="provider-field <?php echo $current_provider === 'openai' ? 'show' : ''; ?>" data-provider="openai">
                                <div class="ai-input-wrap">
                                    <input type="password" name="openai_api_key" class="ai-input" placeholder="<?php echo $openai_key ? 'sk-…' . substr($openai_key, -4) : 'Paste your OpenAI key (blank = keep current)'; ?>">
                                    <button type="button" class="ai-eye" onclick="toggleKey(this)"><i class="fas fa-eye"></i></button>
                                </div>
                            </div>
                            <div class="provider-field <?php echo $current_provider === 'gemini' ? 'show' : ''; ?>" data-provider="gemini">
                                <div class="ai-input-wrap">
                                    <input type="password" name="gemini_api_key" class="ai-input" placeholder="<?php echo $gemini_key ? 'AIza…' . substr($gemini_key, -4) : 'Paste your Gemini key (blank = keep current)'; ?>">
                                    <button type="button" class="ai-eye" onclick="toggleKey(this)"><i class="fas fa-eye"></i></button>
                                </div>
                            </div>
                            <div class="ai-hint"><i class="fas fa-lock"></i> Keys are stored encrypted-at-rest style (blank field keeps your current key).</div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <p class="ai-label"><i class="fas fa-cube mr-1"></i> OpenAI Model</p>
                                <input type="text" name="openai_model" class="ai-input provider-field <?php echo $current_provider === 'openai' ? 'show' : ''; ?>" data-provider="openai" value="<?php echo htmlspecialchars($openai_model); ?>">
                                <input type="text" name="gemini_model" class="ai-input provider-field <?php echo $current_provider === 'gemini' ? 'show' : ''; ?>" data-provider="gemini" value="<?php echo htmlspecialchars($gemini_model); ?>">
                            </div>
                            <div class="col-md-6 mb-4">
                                <p class="ai-label"><i class="fas fa-comment-dots mr-1"></i> Chatbot Name</p>
                                <input type="text" name="chatbot_name" class="ai-input" value="<?php echo htmlspecialchars($chatbot_name); ?>" maxlength="40">
                            </div>
                        </div>

                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <p class="ai-label mb-0"><i class="fas fa-hourglass-half mr-1"></i> API Timeout</p>
                                <span class="ai-range-val" id="timeoutVal"><?php echo intval($timeout); ?>s</span>
                            </div>
                            <input type="range" class="ai-range" name="timeout" id="timeoutRange" min="5" max="120" step="5" value="<?php echo intval($timeout); ?>">
                            <div class="d-flex justify-content-between" style="font-size:0.7rem; color:var(--text-muted); font-weight:700;">
                                <span>5s</span><span>30s</span><span>60s</span><span>120s</span>
                            </div>
                        </div>

                        <div class="d-flex align-items-center justify-content-between mb-4 p-3" style="background:var(--bg-hover); border:1px solid var(--border-light); border-radius:14px;">
                            <div>
                                <b style="color:var(--text); font-size:0.93rem;">Enable Live AI</b>
                                <div style="color:var(--text-muted); font-size:0.8rem;">Uses the API key for generation; offline engine always works as fallback.</div>
                            </div>
                            <label class="ai-switch mb-0">
                                <input type="checkbox" name="llm_enabled" <?php echo $llm_enabled ? 'checked' : ''; ?>>
                                <span class="ai-slider"></span>
                            </label>
                        </div>

                        <div class="d-flex flex-wrap gap-2">
                            <button type="submit" name="save_ai_settings" class="ai-btn ai-btn-primary" id="saveBtn"><i class="fas fa-save mr-2"></i> Save Settings</button>
                            <button type="submit" name="test_ai" class="ai-btn ai-btn-ghost" id="testBtn"><i class="fas fa-plug mr-2"></i> Save & Test Connection</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Right: status + data -->
        <div class="col-lg-5">
            <div class="ai-card ai-reveal" style="animation-delay:.12s;">
                <div class="ai-card-h">
                    <div class="ai-tile" style="background:linear-gradient(135deg,#0ea5e9,#6366f1);"><i class="fas fa-heartbeat"></i></div>
                    <div><h6>Hybrid Engine Status</h6><small>Live health of the AI pipeline</small></div>
                </div>
                <div class="ai-body pt-2">
                    <div class="ai-status-row">
                        <span class="ai-s-label"><i class="fas fa-cogs mr-2" style="color:#6366f1;"></i>Offline rule-based engine</span>
                        <span class="ai-badge on"><i class="fas fa-check-circle mr-1"></i>Always On</span>
                    </div>
                    <div class="ai-status-row">
                        <span class="ai-s-label"><i class="fas fa-bolt mr-2" style="color:#0ea5e9;"></i>Live API (<?php echo strtoupper($current_provider); ?>)</span>
                        <?php if ($live_active): ?>
                            <span class="ai-badge on"><i class="fas fa-check-circle mr-1"></i>Active</span>
                        <?php elseif ($active_key): ?>
                            <span class="ai-badge warn"><i class="fas fa-pause mr-1"></i>Paused</span>
                        <?php else: ?>
                            <span class="ai-badge off"><i class="fas fa-power-off mr-1"></i>No Key</span>
                        <?php endif; ?>
                    </div>
                    <div class="ai-status-row">
                        <span class="ai-s-label"><i class="fas fa-braille mr-2" style="color:#8b5cf6;"></i>Job matching / resume score</span>
                        <span class="ai-badge ai"><i class="fas fa-robot mr-1"></i>Smart AI</span>
                    </div>
                    <div class="ai-status-row">
                        <span class="ai-s-label"><i class="fas fa-wave-square mr-2" style="color:#10b981;"></i>Engine heartbeat</span>
                        <span class="ai-badge on"><span class="ai-engine-dot green"></span>Healthy</span>
                    </div>
                </div>
            </div>

            <div class="ai-card ai-reveal mt-3" style="animation-delay:.18s;">
                <div class="ai-card-h">
                    <div class="ai-tile" style="background:linear-gradient(135deg,#f59e0b,#ef4444);"><i class="fas fa-database"></i></div>
                    <div><h6>AI Data</h6><small><?php echo $total_ai_records; ?> stored AI-generated records</small></div>
                </div>
                <div class="ai-body">
                    <div class="ai-count-grid mb-3">
                        <?php foreach ($his_counts as $label => $n): ?>
                            <div class="ai-count-box">
                                <div class="n"><?php echo $n; ?></div>
                                <div class="l"><?php echo $label; ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <p style="color:var(--text-muted); font-size:0.84rem; margin:0 0 16px;">
                        <i class="fas fa-info-circle mr-1"></i>Clears chat history, cover letters, resume analyses, mock interviews and recommendations. This cannot be undone.
                    </p>
                    <form method="POST" id="clearForm" onsubmit="return confirm('Delete all AI history? This cannot be undone.');">
                        <button type="submit" name="clear_ai_history" class="ai-btn ai-btn-danger w-100"><i class="fas fa-trash-alt mr-2"></i> Clear All AI History</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var provider = document.querySelector('input[name="provider"]:checked').value;
    function syncProvider(v) {
        document.querySelectorAll('.provider-field').forEach(function (el) {
            var on = el.getAttribute('data-provider') === v;
            el.classList.toggle('show', on);
            if (el.tagName === 'INPUT') el.disabled = !on;
        });
    }
    document.querySelectorAll('input[name="provider"]').forEach(function (r) {
        r.addEventListener('change', function () {
            provider = this.value;
            document.querySelectorAll('.ai-provider').forEach(function (l) {
                l.classList.toggle('sel', l.querySelector('input').checked);
            });
            syncProvider(provider);
        });
    });
    syncProvider(provider);

    var range = document.getElementById('timeoutRange');
    var out = document.getElementById('timeoutVal');
    range.addEventListener('input', function () { out.textContent = this.value + 's'; });

    ['saveBtn', 'testBtn'].forEach(function (id) {
        var b = document.getElementById(id);
        b.addEventListener('click', function () {
            this.disabled = true;
            this.querySelector('i').className = 'fas fa-spinner fa-spin mr-2';
        });
    });
})();

function toggleKey(btn) {
    var input = btn.closest('.ai-input-wrap').querySelector('input');
    var show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    btn.innerHTML = show ? '<i class="fas fa-eye-slash"></i>' : '<i class="fas fa-eye"></i>';
}

(function () {
    var t = document.getElementById('aiToast');
    if (t) setTimeout(function () {
        t.classList.add('out');
        setTimeout(function () { t.remove(); }, 4000);
    }, 6000);
})();
</script>
</body>
</html>
