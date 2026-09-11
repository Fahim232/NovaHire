<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_seeker_login();

$user_id = $_SESSION['id'];

require_once __DIR__ . '/../includes/premium.php';
$access = nh_check_access($con, $user_id, 'resume_builder');
if (!$access['allowed']) {
    nh_render_pro_gate('resume_builder');
    exit;
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_resume'])) {
    $data = [
        'about' => trim($_POST['about'] ?? ''),
        'experience_years' => intval($_POST['experience_years'] ?? 0),
        'expected_salary' => intval($_POST['expected_salary'] ?? 0),
    ];
    if (save_resume_data($con, $user_id, $data)) {
        $success = 'Resume data saved successfully!';
    } else {
        $error = 'Failed to save. Please try again.';
    }
}

$resume_data = get_resume_data($con, $user_id);
$templates = get_resume_templates();
$selected = $_GET['template'] ?? 'professional';

if (isset($_GET['download']) && $_GET['download'] === 'pdf') {
    $html = render_resume_template($resume_data, $selected);
    header('Content-Type: text/html; charset=UTF-8');
    header('Content-Disposition: inline; filename="resume_' . $user_id . '_' . $selected . '.html"');
    echo $html;
    exit;
}

require_once __DIR__ . '/../includes/header.php';
$preview_url = 'resume_preview.php?template=' . urlencode($selected);
$tpl_name = $templates[$selected]['name'] ?? 'Professional';
$tpl_icon = $templates[$selected]['icon'] ?? 'fa-building';
$tpl_color = $templates[$selected]['preview_color'] ?? '#1a56db';
?>
<style>
/* ═══ RESET — ignore global theme vars ═══ */
.rb-page *, .rb-page *::before, .rb-page *::after { box-sizing: border-box; }
.rb-page {
    --rb-bg: #f1f5f9;
    --rb-card: #ffffff;
    --rb-border: #e2e8f0;
    --rb-text: #0f172a;
    --rb-text-2: #334155;
    --rb-text-3: #64748b;
    --rb-primary: #1a56db;
    --rb-primary-light: #eef2ff;
    --rb-primary-dark: #3730a3;
    --rb-success: #059669;
    --rb-success-bg: #ecfdf5;
    --rb-danger: #dc2626;
    --rb-danger-bg: #fef2f2;
    --rb-radius: 14px;
    font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    color: var(--rb-text);
    max-width: 1440px;
    margin: 0 auto;
    padding: 28px 24px;
    background: var(--rb-bg);
    border-radius: 20px;
}

/* ═══ HERO ═══ */
.rb-hero {
    background: linear-gradient(135deg, #1a56db 0%, #0ea5e9 50%, #38bdf8 100%);
    color: #fff;
    border-radius: var(--rb-radius);
    padding: 32px 36px;
    margin-bottom: 24px;
    position: relative;
    overflow: hidden;
}
.rb-hero::after {
    content: '';
    position: absolute;
    top: -60%;
    right: -8%;
    width: 350px;
    height: 350px;
    background: radial-gradient(circle, rgba(255,255,255,0.12) 0%, transparent 70%);
    border-radius: 50%;
}
.rb-hero h1 { font-size: 1.75rem; font-weight: 800; margin-bottom: 4px; position: relative; z-index: 1; }
.rb-hero h1 i { margin-right: 10px; opacity: 0.85; }
.rb-hero p { opacity: 0.9; font-size: 0.95rem; position: relative; z-index: 1; }

/* ═══ LAYOUT ═══ */
.rb-grid { display: grid; grid-template-columns: 360px 1fr; gap: 24px; align-items: start; }

/* ═══ SIDEBAR CARD ═══ */
.rb-side {
    background: var(--rb-card);
    border: 1px solid var(--rb-border);
    border-radius: var(--rb-radius);
    overflow: hidden;
    position: sticky;
    top: 90px;
}
.rb-side-head {
    padding: 18px 22px;
    border-bottom: 1px solid var(--rb-border);
    font-weight: 700;
    font-size: 0.92rem;
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--rb-text);
}
.rb-side-head i { color: var(--rb-primary); }
.rb-side-body { padding: 20px 22px; }

/* ═══ TEMPLATE PICKER ═══ */
.rb-tpl-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-bottom: 22px; }
.rb-tpl {
    border: 2px solid var(--rb-border);
    border-radius: 10px;
    padding: 10px 6px;
    text-align: center;
    text-decoration: none;
    color: var(--rb-text);
    transition: all 0.2s;
    display: block;
}
.rb-tpl:hover { border-color: var(--rb-primary); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(26,86,219,0.12); }
.rb-tpl.on { border-color: var(--rb-primary); background: var(--rb-primary-light); box-shadow: 0 0 0 3px rgba(26,86,219,0.12); }
.rb-tpl i { font-size: 1.3rem; margin-bottom: 4px; display: block; }
.rb-tpl span { font-size: 0.7rem; font-weight: 700; display: block; }

/* ═══ FORM ═══ */
.rb-fg { margin-bottom: 14px; }
.rb-fg label {
    display: block;
    font-weight: 600;
    font-size: 0.78rem;
    margin-bottom: 5px;
    color: var(--rb-text-2);
    text-transform: uppercase;
    letter-spacing: 0.3px;
}
.rb-fg input[type="text"],
.rb-fg input[type="email"],
.rb-fg input[type="number"],
.rb-fg textarea {
    width: 100%;
    padding: 10px 14px;
    border: 1.5px solid var(--rb-border);
    border-radius: 10px;
    font-family: inherit;
    font-size: 0.88rem;
    color: var(--rb-text);
    background: #f8fafc;
    transition: border 0.2s, box-shadow 0.2s;
}
.rb-fg input:focus,
.rb-fg textarea:focus {
    outline: none;
    border-color: var(--rb-primary);
    box-shadow: 0 0 0 3px rgba(26,86,219,0.1);
    background: #fff;
}
.rb-fg textarea { resize: vertical; min-height: 90px; }
.rb-fg input:disabled { opacity: 0.55; cursor: not-allowed; background: #f1f5f9; }
.rb-fg small { display: block; margin-top: 4px; color: var(--rb-text-3); font-size: 0.72rem; }
.rb-fg small a { color: var(--rb-primary); text-decoration: none; font-weight: 600; }
.rb-fg small a:hover { text-decoration: underline; }

/* ═══ BUTTONS ═══ */
.rb-btns { display: flex; flex-direction: column; gap: 10px; margin-top: 20px; }
.rb-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px 16px;
    border-radius: 10px;
    font-weight: 700;
    font-size: 0.88rem;
    cursor: pointer;
    border: none;
    transition: all 0.2s;
    text-decoration: none;
    font-family: inherit;
}
.rb-btn:active { transform: scale(0.98); }
.rb-btn-save { background: var(--rb-success); color: #fff; }
.rb-btn-save:hover { background: #047857; box-shadow: 0 4px 14px rgba(5,150,105,0.3); }
.rb-btn-dl { background: var(--rb-primary); color: #fff; }
.rb-btn-dl:hover { background: var(--rb-primary-dark); box-shadow: 0 4px 14px rgba(26,86,219,0.3); }
.rb-btn-print { background: #fff; border: 1.5px solid var(--rb-border); color: var(--rb-text-2); }
.rb-btn-print:hover { border-color: var(--rb-primary); color: var(--rb-primary); }
.rb-btn-edit { background: #fff; border: 1.5px solid var(--rb-border); color: var(--rb-text-3); }
.rb-btn-edit:hover { border-color: var(--rb-primary); color: var(--rb-primary); }

/* ═══ PREVIEW PANEL ═══ */
.rb-prev {
    background: var(--rb-card);
    border: 1px solid var(--rb-border);
    border-radius: var(--rb-radius);
    overflow: hidden;
    box-shadow: 0 4px 24px rgba(0,0,0,0.04);
}
.rb-prev-bar {
    background: #f8fafc;
    border-bottom: 1px solid var(--rb-border);
    padding: 12px 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.rb-prev-bar .lbl { font-weight: 600; font-size: 0.85rem; display: flex; align-items: center; gap: 8px; color: var(--rb-text); }
.rb-prev-bar .dot { width: 8px; height: 8px; border-radius: 50%; background: var(--rb-success); }
.rb-prev-acts { display: flex; gap: 8px; }
.rb-prev-acts a, .rb-prev-acts button {
    padding: 6px 14px;
    border-radius: 8px;
    font-size: 0.78rem;
    font-weight: 600;
    cursor: pointer;
    border: 1px solid var(--rb-border);
    background: #fff;
    color: var(--rb-text-2);
    text-decoration: none;
    transition: all 0.15s;
    display: flex;
    align-items: center;
    gap: 5px;
    font-family: inherit;
}
.rb-prev-acts a:hover, .rb-prev-acts button:hover { border-color: var(--rb-primary); color: var(--rb-primary); }
.rb-frame-wrap { background: #94a3b8; padding: 20px; }
.rb-frame { width: 100%; min-height: 900px; border: none; background: #fff; border-radius: 4px; }

/* ═══ ALERTS ═══ */
.rb-alert {
    padding: 14px 20px;
    border-radius: 12px;
    margin-bottom: 20px;
    font-weight: 600;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 8px;
}
.rb-ok { background: var(--rb-success-bg); color: #065f46; border: 1px solid #a7f3d0; }
.rb-err { background: var(--rb-danger-bg); color: #991b1b; border: 1px solid #fecaca; }

/* ═══ RESPONSIVE ═══ */
@media (max-width: 1024px) {
    .rb-grid { grid-template-columns: 1fr; }
    .rb-side { position: static; }
    .rb-tpl-grid { grid-template-columns: repeat(4, 1fr); }
}
@media (max-width: 600px) {
    .rb-page { padding: 16px 12px; }
    .rb-hero { padding: 22px 18px; }
    .rb-hero h1 { font-size: 1.3rem; }
    .rb-tpl-grid { grid-template-columns: repeat(2, 1fr); }
    .rb-frame-wrap { padding: 10px; }
}
</style>

<div class="rb-page">
    <div class="rb-hero">
        <h1><i class="fas fa-file-alt"></i>Resume Builder</h1>
        <p>Create a professional resume from your NovaHire profile. Pick a style, customize, and download.</p>
    </div>

    <?php if ($success): ?>
        <div class="rb-alert rb-ok"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="rb-alert rb-err"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
    <?php endif; ?>

    <div class="rb-grid">
        <!-- ─── LEFT: Controls ─── -->
        <div class="rb-side">
            <div class="rb-side-head"><i class="fas fa-palette"></i> Choose Template</div>
            <div class="rb-side-body">
                <div class="rb-tpl-grid">
                    <?php foreach ($templates as $key => $t): ?>
                        <a href="?template=<?php echo $key; ?>" class="rb-tpl <?php echo $selected === $key ? 'on' : ''; ?>">
                            <i class="fas <?php echo $t['icon']; ?>" style="color:<?php echo $t['preview_color']; ?>;"></i>
                            <span><?php echo $t['name']; ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>

                <form method="POST" id="rForm">
                    <div class="rb-fg">
                        <label>Full Name</label>
                        <input type="text" value="<?php echo esc($resume_data['name'] ?? ''); ?>" disabled>
                        <small>Edit in <a href="profile.php">Profile Settings</a></small>
                    </div>
                    <div class="rb-fg">
                        <label>Email Address</label>
                        <input type="email" value="<?php echo esc($resume_data['email'] ?? ''); ?>" disabled>
                    </div>
                    <div class="rb-fg">
                        <label>Phone Number</label>
                        <input type="text" value="<?php echo esc($resume_data['phone'] ?? ''); ?>" disabled>
                    </div>
                    <div class="rb-fg">
                        <label>Education</label>
                        <input type="text" value="<?php echo esc($resume_data['degree'] ?? ''); ?>" disabled>
                    </div>
                    <div class="rb-fg">
                        <label>Skills</label>
                        <input type="text" value="<?php echo esc(implode(', ', $resume_data['skills'] ?? [])); ?>" disabled>
                        <small>Auto-filled from your profile</small>
                    </div>
                    <div class="rb-fg">
                        <label>Years of Experience</label>
                        <input type="number" name="experience_years" value="<?php echo intval($resume_data['experience_years'] ?? 0); ?>" min="0" max="50">
                    </div>
                    <div class="rb-fg">
                        <label>Expected Salary (&#2547;)</label>
                        <input type="number" name="expected_salary" value="<?php echo intval($resume_data['expected_salary'] ?? 0); ?>" min="0">
                    </div>
                    <div class="rb-fg">
                        <label>Professional Summary</label>
                        <textarea name="about" placeholder="Write 2-3 sentences about your expertise and career goals..."><?php echo esc($resume_data['about'] ?? ''); ?></textarea>
                    </div>

                    <div class="rb-btns">
                        <button type="submit" name="save_resume" class="rb-btn rb-btn-save"><i class="fas fa-save"></i> Save Changes</button>
                        <a href="?template=<?php echo $selected; ?>&download=pdf" class="rb-btn rb-btn-dl" target="_blank"><i class="fas fa-download"></i> Download PDF</a>
                        <button type="button" onclick="printResume()" class="rb-btn rb-btn-print"><i class="fas fa-print"></i> Print Resume</button>
                        <a href="profile.php" class="rb-btn rb-btn-edit"><i class="fas fa-user-edit"></i> Edit Full Profile</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- ─── RIGHT: Preview ─── -->
        <div class="rb-prev">
            <div class="rb-prev-bar">
                <div class="lbl"><span class="dot"></span> Live Preview &mdash; <?php echo $tpl_name; ?></div>
                <div class="rb-prev-acts">
                    <a href="?template=<?php echo $selected; ?>&download=pdf" target="_blank"><i class="fas fa-download"></i> PDF</a>
                    <button onclick="printResume()"><i class="fas fa-print"></i> Print</button>
                </div>
            </div>
            <div class="rb-frame-wrap">
                <iframe id="resumeFrame" class="rb-frame" src="<?php echo $preview_url; ?>"></iframe>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('resumeFrame').onload = function() {
    try { this.style.height = this.contentDocument.body.scrollHeight + 50 + 'px'; }
    catch(e) { this.style.height = '900px'; }
};

// Reload iframe when template changes (page already reloads via <a> tags)
// But also refresh on save
document.getElementById('rForm').addEventListener('submit', function() {
    setTimeout(function() {
        var f = document.getElementById('resumeFrame');
        f.src = f.src; // reload
    }, 100);
});

function printResume() {
    var f = document.getElementById('resumeFrame');
    var url = f.src;
    var w = window.open(url, '_blank');
    setTimeout(function(){ w.print(); }, 800);
}
</script>
