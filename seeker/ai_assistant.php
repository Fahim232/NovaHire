<?php
require_once __DIR__ . '/../includes/bootstrap.php';
if (!isset($_SESSION['id'])) {
    header('location: ' . BASE_URL . '/auth/login.php');
    exit();
}
require_once __DIR__ . '/../admin/dbcon.php';
require_once __DIR__ . '/../ai/config.php';
require_once __DIR__ . '/../ai/helpers.php';

$user_id = $_SESSION['id'];

require_once __DIR__ . '/../includes/premium.php';
$access = nh_check_access($con, $user_id, 'ai_assistant');
if (!$access['allowed']) {
    nh_render_pro_gate('ai_assistant');
    exit;
}

// Load recent chat history
$history = array();
$stmt = mysqli_prepare($con, "SELECT role, message FROM ai_chat_history WHERE user_id = ? ORDER BY id DESC LIMIT 30");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
if ($result) {
    $rows = array();
    while ($r = mysqli_fetch_assoc($result)) $rows[] = $r;
    $history = array_reverse($rows);
}
mysqli_stmt_close($stmt);

// Load user info for greeting
$uq = mysqli_prepare($con, "SELECT username, user_skills FROM user_info WHERE id = ?");
mysqli_stmt_bind_param($uq, "i", $user_id);
mysqli_stmt_execute($uq);
$user_data = mysqli_fetch_assoc(mysqli_stmt_get_result($uq));
mysqli_stmt_close($uq);
$display_name = isset($user_data['username']) ? htmlspecialchars(explode(' ', $user_data['username'])[0]) : 'there';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>AI Career Assistant | NovaHire</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <?php echo ai_css_link(); ?>
    <style>
        body { background: #f1f5f9; margin: 0; }
        .assistant-wrap {
            max-width: 880px; margin: 0 auto;
            padding: 24px 16px 30px;
            height: calc(100vh - 70px);
            display: flex; flex-direction: column;
        }
        .chat-shell {
            background: #ffffff; border-radius: 24px; overflow: hidden;
            box-shadow: 0 20px 60px rgba(26,86,219,0.10), 0 4px 16px rgba(0,0,0,0.05);
            display: flex; flex-direction: column; flex: 1;
            min-height: 0;
        }
        .chat-shell-head {
            background: linear-gradient(135deg, #3b82f6 0%, #06b6d4 50%, #38bdf8 100%);
            color: white; padding: 20px 28px;
            display: flex; align-items: center; gap: 14px;
            flex-shrink: 0; position: relative;
        }
        .chat-shell-head::after {
            content: ''; position: absolute; bottom: 0; left: 0; right: 0;
            height: 3px; background: linear-gradient(90deg, #fbbf24, #d97706, #fbbf24);
        }
        .chat-shell-avatar {
            width: 48px; height: 48px; border-radius: 14px;
            background: rgba(255,255,255,0.2);
            backdrop-filter: blur(8px);
            border: 1.5px solid rgba(255,255,255,0.25);
            display: flex; align-items: center; justify-content: center; font-size: 1.25rem;
            flex-shrink: 0;
        }
        .chat-shell-head h5 { margin: 0; font-weight: 800; font-size: 1.05rem; letter-spacing: -0.2px; }
        .chat-shell-head small { opacity: 0.9; display: flex; align-items: center; gap: 6px; margin-top: 2px; }
        .dot-on { width: 8px; height: 8px; border-radius: 50%; background: #34d399; display: inline-block;
            box-shadow: 0 0 8px rgba(52,211,153,0.6); }
        .chat-shell-body {
            flex: 1; overflow-y: auto; padding: 24px 28px;
            background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
            display: flex; flex-direction: column; gap: 14px;
        }
        .chat-shell-body::-webkit-scrollbar { width: 5px; }
        .chat-shell-body::-webkit-scrollbar-track { background: transparent; }
        .chat-shell-body::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }

        /* Messages */
        .msg-full {
            max-width: 82%; padding: 14px 18px; border-radius: 20px;
            font-size: 0.92rem; line-height: 1.65; word-wrap: break-word;
            animation: msgSlideIn 0.3s cubic-bezier(0.34,1.56,0.64,1);
        }
        @keyframes msgSlideIn {
            from { opacity: 0; transform: translateY(10px) scale(0.97); }
            to { opacity: 1; transform: none; }
        }
        .msg-full.bot {
            background: #ffffff; color: #1e293b;
            border: 1px solid #e2e8f0;
            align-self: flex-start; border-bottom-left-radius: 6px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .msg-full.bot strong { color: #1a56db; }
        .msg-full.user {
            background: linear-gradient(135deg, #3b82f6, #06b6d4);
            color: #ffffff; align-self: flex-end; border-bottom-right-radius: 6px;
            box-shadow: 0 4px 12px rgba(59,130,246,0.3);
        }
        .msg-full .ai-quick {
            display: flex; flex-wrap: wrap; gap: 7px; margin-top: 14px;
        }
        .msg-full .ai-quick button {
            background: #eef2ff; color: #1e40af;
            border: 1px solid #c7d2fe;
            padding: 8px 16px; border-radius: 22px;
            font-size: 0.78rem; font-weight: 600; cursor: pointer;
            transition: all 0.2s; font-family: inherit;
        }
        .msg-full .ai-quick button:hover {
            background: #3b82f6; color: white; border-color: #3b82f6;
            transform: translateY(-1px); box-shadow: 0 4px 12px rgba(59,130,246,0.3);
        }

        /* Typing indicator */
        .typing-indicator {
            display: inline-flex; gap: 5px; align-items: center;
            padding: 16px 20px;
        }
        .typing-indicator span {
            width: 8px; height: 8px; border-radius: 50%;
            background: #94a3b8;
            animation: typingBounce 1.2s ease-in-out infinite;
        }
        .typing-indicator span:nth-child(2) { animation-delay: 0.15s; }
        .typing-indicator span:nth-child(3) { animation-delay: 0.3s; }
        @keyframes typingBounce {
            0%,100% { opacity: 0.2; transform: scale(0.8); }
            50% { opacity: 1; transform: scale(1.15); }
        }

        /* Footer */
        .chat-shell-foot {
            display: flex; align-items: center; gap: 12px;
            padding: 18px 28px; border-top: 1px solid #e2e8f0;
            background: #ffffff; flex-shrink: 0;
        }
        .chat-shell-foot input {
            flex: 1; border: 2px solid #e2e8f0; border-radius: 26px;
            padding: 14px 22px; font-size: 0.92rem; outline: none;
            transition: all 0.2s; background: #f8fafc; color: #1e293b;
            font-family: inherit;
        }
        .chat-shell-foot input::placeholder { color: #94a3b8; }
        .chat-shell-foot input:focus {
            border-color: #3b82f6; background: #ffffff;
            box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
        }
        .chat-shell-foot button {
            width: 50px; height: 50px; border-radius: 50%;
            background: linear-gradient(135deg, #3b82f6, #06b6d4);
            color: white; border: none; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            transition: all 0.25s; flex-shrink: 0; font-size: 1.05rem;
            box-shadow: 0 4px 14px rgba(59,130,246,0.35);
        }
        .chat-shell-foot button:hover {
            transform: scale(1.08); box-shadow: 0 6px 20px rgba(59,130,246,0.5);
        }
        .chat-shell-foot button:active { transform: scale(0.95); }
        .chat-shell-foot button:disabled {
            opacity: 0.5; cursor: not-allowed; transform: none;
        }

        /* Welcome card */
        .welcome-card {
            background: linear-gradient(135deg, #eef2ff 0%, #f5f3ff 100%);
            border: 1px solid #c7d2fe; border-radius: 18px;
            padding: 22px 26px; margin-bottom: 4px;
        }
        .welcome-card h6 { color: #1a56db; font-weight: 700; margin-bottom: 8px; }
        .welcome-card p { color: #475569; font-size: 0.88rem; margin: 0; line-height: 1.6; }
        .welcome-tags { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 12px; }
        .welcome-tags span {
            background: white; border: 1px solid #c7d2fe; color: #1e40af;
            padding: 5px 12px; border-radius: 16px; font-size: 0.72rem; font-weight: 600;
        }

        @media (max-width: 575px) {
            .assistant-wrap { padding: 12px 8px 16px; height: calc(100vh - 60px); }
            .chat-shell { border-radius: 18px; }
            .chat-shell-head { padding: 16px 18px; }
            .chat-shell-body { padding: 16px 18px; }
            .chat-shell-foot { padding: 14px 18px; }
            .msg-full { max-width: 90%; }
        }
    </style>
</head>
<body>
<div class="assistant-wrap">
    <div style="margin-bottom:14px;">
        <a href="<?php echo BASE_URL; ?>/seeker/seeker_dashboard.php" style="text-decoration:none;color:#3b82f6;font-weight:600;font-size:0.9rem;display:inline-flex;align-items:center;gap:6px;">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>
    <div class="chat-shell">
        <div class="chat-shell-head">
            <div class="chat-shell-avatar"><i class="fas fa-robot"></i></div>
            <div>
                <h5>Nova - AI Career Assistant</h5>
                <small><span class="dot-on"></span><?php echo ai_provider_label(); ?> &bull; Always online</small>
            </div>
        </div>

        <div class="chat-shell-body" id="aiChatBody">
            <?php if (empty($history)): ?>
                <div class="welcome-card">
                    <h6><i class="fas fa-sparkles mr-1"></i> Welcome, <?php echo $display_name; ?>!</h6>
                    <p>I'm Nova, your personal AI career assistant. I know your profile and the current job market. Ask me anything about jobs, resumes, interviews, or career growth.</p>
                    <div class="welcome-tags">
                        <span><i class="fas fa-briefcase mr-1"></i> Job Matching</span>
                        <span><i class="fas fa-file-alt mr-1"></i> Resume Analysis</span>
                        <span><i class="fas fa-comments mr-1"></i> Interview Prep</span>
                        <span><i class="fas fa-graduation-cap mr-1"></i> Grooming</span>
                    </div>
                </div>
                <div class="msg-full bot">
                    Hi <?php echo $display_name; ?>! I'm here to help you succeed. You can ask me to find jobs, analyze your resume, practice interviews, or just chat about your career. What would you like to start with?
                    <div class="ai-quick">
                        <button onclick="aiChatSet('Find jobs for me')">Find Jobs</button>
                        <button onclick="aiChatSet('Improve my resume')">Resume Check</button>
                        <button onclick="aiChatSet('Start mock interview')">Mock Interview</button>
                        <button onclick="aiChatSet('What can you do?')">What can you do?</button>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($history as $h): ?>
                    <div class="msg-full <?php echo $h['role'] === 'user' ? 'user' : 'bot'; ?>"><?php echo nl2br(htmlspecialchars($h['message'])); ?></div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="chat-shell-foot">
            <input type="text" id="aiChatInput" placeholder="Ask me anything about jobs, resumes, interviews..." autocomplete="off">
            <button id="aiChatSendBtn" onclick="aiChatSend()"><i class="fas fa-paper-plane"></i></button>
        </div>
    </div>
</div>

<script>
const AI_API = '<?php echo BASE_URL; ?>/api/ai_chat.php';
const chatBody = document.getElementById('aiChatBody');
const chatInput = document.getElementById('aiChatInput');
const sendBtn = document.getElementById('aiChatSendBtn');

// Enter to send
chatInput.addEventListener('keydown', e => { if (e.key === 'Enter') aiChatSend(); });

function aiChatSet(text) {
    chatInput.value = text;
    aiChatSend();
}

function aiChatAddMsg(role, html) {
    const div = document.createElement('div');
    div.className = 'msg-full ' + (role === 'user' ? 'user' : 'bot');
    div.innerHTML = html;
    chatBody.appendChild(div);
    chatBody.scrollTop = chatBody.scrollHeight;
}

function aiChatTyping(on) {
    const existing = document.getElementById('aiTyping');
    if (existing) existing.remove();
    if (on) {
        const div = document.createElement('div');
        div.className = 'msg-full bot';
        div.id = 'aiTyping';
        div.innerHTML = '<div class="typing-indicator"><span></span><span></span><span></span></div>';
        chatBody.appendChild(div);
        chatBody.scrollTop = chatBody.scrollHeight;
    }
}

function aiChatSend() {
    const msg = chatInput.value.trim();
    if (!msg) return;
    chatInput.value = '';
    sendBtn.disabled = true;

    // Add user message
    aiChatAddMsg('user', msg.replace(/</g, '&lt;'));

    // Show typing indicator with realistic delay
    setTimeout(() => aiChatTyping(true), 200);

    fetch(AI_API, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'message=' + encodeURIComponent(msg)
    })
    .then(r => r.json())
    .then(data => {
        // Simulate typing delay for realism (500-1200ms based on response length)
        const delay = Math.min(1200, Math.max(500, (data.reply || '').length * 3));
        setTimeout(() => {
            aiChatTyping(false);
            let html = (data.reply || '').replace(/\n/g, '<br>');
            if (data.buttons && data.buttons.length) {
                html += '<div class="ai-quick">' + data.buttons.map(b =>
                    '<button onclick="aiChatSet(\'' + b.replace(/\\/g, '\\\\').replace(/'/g, "\\'") + '\')">' + b + '</button>'
                ).join('') + '</div>';
            }
            aiChatAddMsg('bot', html);
            sendBtn.disabled = false;
            chatInput.focus();
        }, delay);
    })
    .catch(() => {
        aiChatTyping(false);
        aiChatAddMsg('bot', 'Sorry, something went wrong. Please try again.');
        sendBtn.disabled = false;
    });
}

// Focus input on load
chatInput.focus();
// Scroll to bottom if history exists
chatBody.scrollTop = chatBody.scrollHeight;
</script>
</body>
</html>
